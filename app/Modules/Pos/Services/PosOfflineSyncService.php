<?php

namespace App\Modules\Pos\Services;

use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Akunting\Services\PajakService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Services\PelangganService;
use App\Modules\Pos\Models\PosOfflineSyncLog;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Services\StokDeductionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PosOfflineSyncService
{
    public function __construct(
        protected PricingService $pricingService,
        protected StokDeductionService $stokDeductionService,
        protected JurnalService $jurnalService,
        protected PajakService $pajakService,
        protected PelangganService $pelangganService
    ) {}

    /**
     * Sinkronisasi batch transaksi POS offline.
     *
     * @param  array  $transactions  Daftar payload transaksi offline dari klien
     * @param  int  $cabangId  Cabang aktif kasir
     * @param  int  $userId  User kasir yang melakukan sinkronisasi
     * @param  string|null  $batchId  ID batch pengiriman (opsional)
     * @return array Rekapitulasi hasil sinkronisasi
     */
    public function syncBatch(
        array $transactions,
        int $cabangId,
        int $userId,
        ?string $batchId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $batchId = $batchId ?: 'BATCH-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(4));
        $results = [];
        $totalSuccess = 0;
        $totalConflicts = 0;
        $conflictDetails = [];

        foreach ($transactions as $txData) {
            $uuid = $txData['offline_uuid'] ?? null;
            if (! $uuid) {
                $totalConflicts++;
                $results[] = [
                    'offline_uuid' => null,
                    'status' => 'conflict',
                    'message' => 'offline_uuid wajib diisi.',
                ];
                $conflictDetails[] = ['error' => 'Missing offline_uuid', 'payload' => $txData];

                continue;
            }

            try {
                $syncResult = $this->processSingleTransaction($txData, $cabangId, $userId);
                $results[] = $syncResult;

                if ($syncResult['status'] === 'synced' || $syncResult['status'] === 'already_synced') {
                    $totalSuccess++;
                } else {
                    $totalConflicts++;
                    $conflictDetails[] = [
                        'offline_uuid' => $uuid,
                        'message' => $syncResult['message'] ?? 'Konflik tidak diketahui',
                    ];
                }
            } catch (\Throwable $e) {
                Log::error("[PosOfflineSync] Gagal memproses transaksi {$uuid}: ".$e->getMessage(), [
                    'exception' => $e,
                    'txData' => $txData,
                ]);

                $totalConflicts++;
                $conflictDetails[] = [
                    'offline_uuid' => $uuid,
                    'error' => $e->getMessage(),
                ];

                $results[] = [
                    'offline_uuid' => $uuid,
                    'status' => 'conflict',
                    'message' => $e->getMessage(),
                ];
            }
        }

        // Catat ke pos_offline_sync_logs
        PosOfflineSyncLog::create([
            'cabang_id' => $cabangId,
            'user_id' => $userId,
            'batch_id' => $batchId,
            'total_payload' => count($transactions),
            'total_success' => $totalSuccess,
            'total_conflicts' => $totalConflicts,
            'conflict_details' => count($conflictDetails) > 0 ? $conflictDetails : null,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        return [
            'batch_id' => $batchId,
            'total_payload' => count($transactions),
            'total_success' => $totalSuccess,
            'total_conflicts' => $totalConflicts,
            'results' => $results,
        ];
    }

    /**
     * Memproses satu transaksi offline secara atomik.
     */
    public function processSingleTransaction(array $tx, int $cabangId, int $userId): array
    {
        $uuid = $tx['offline_uuid'];

        return DB::transaction(function () use ($tx, $cabangId, $userId, $uuid) {
            // 1. Cek idempotensi mutlak (apakah offline_uuid sudah pernah tercatat)
            $existing = Transaksi::where('offline_uuid', $uuid)->first();
            if ($existing) {
                return [
                    'offline_uuid' => $uuid,
                    'status' => 'already_synced',
                    'transaksi_id' => $existing->id,
                    'no_transaksi' => $existing->no_transaksi,
                    'total_akhir' => (float) $existing->total_akhir,
                    'message' => 'Transaksi sudah pernah tersinkronisasi sebelumnya.',
                ];
            }

            // 2. Resolusi Gudang
            $gudangId = $tx['gudang_id'] ?? null;
            if ($gudangId) {
                $gudangValid = Gudang::where('id', $gudangId)->where('cabang_id', $cabangId)->exists();
                if (! $gudangValid) {
                    $gudangId = Gudang::where('cabang_id', $cabangId)->value('id');
                }
            } else {
                $gudangId = Gudang::where('cabang_id', $cabangId)->value('id');
            }

            // 3. Rekalkulasi Harga & Anti-Tamper (Prinsip Server Pricing Authority)
            $pelangganId = $tx['pelanggan_id'] ?? null;
            $pelanggan = $pelangganId ? Pelanggan::with('tierMembership')->whereIn('status', ['aktif', 'approved'])->find($pelangganId) : null;
            if ($pelangganId && ! $pelanggan) {
                // Pelanggan belum diapprove / non-aktif: jangan gunakan benefit member
                $pelangganId = null;
            }

            $itemsData = [];
            $serverCalculatedSubtotal = 0.0;
            $rawClientSubtotal = 0.0;
            $items = $tx['items'] ?? [];

            if (empty($items)) {
                throw new \InvalidArgumentException('Daftar item transaksi offline tidak boleh kosong.');
            }

            foreach ($items as $item) {
                $produk = Produk::find($item['produk_id']);
                if (! $produk) {
                    throw new \InvalidArgumentException("Produk dengan ID {$item['produk_id']} tidak ditemukan.");
                }

                $qty = max(1, (int) ($item['qty'] ?? $item['jumlah'] ?? 1));
                $satuan = $item['satuan'] ?? ($produk->satuan ?: 'pcs');
                $konversi = max(0.0001, (float) ($item['konversi'] ?? 1));
                $qtyDasar = (int) round($qty * $konversi);

                $variant = ! empty($item['sku_variant_id']) ? SkuVariant::find($item['sku_variant_id']) : null;
                $resolvedPricing = $this->pricingService->resolve($produk, $pelanggan, $variant);

                $serverUnitPrice = (float) $resolvedPricing['harga'];
                $clientUnitPrice = (float) ($item['harga_satuan'] ?? $serverUnitPrice);
                $diskonItem = (float) ($item['diskon_nominal'] ?? 0);

                $lineSubtotalServer = max(0, ($serverUnitPrice * $qty) - $diskonItem);
                $lineSubtotalClient = max(0, ($clientUnitPrice * $qty) - $diskonItem);

                $serverCalculatedSubtotal += $lineSubtotalServer;
                $rawClientSubtotal += $lineSubtotalClient;

                $itemsData[] = [
                    'produk' => $produk,
                    'sku_variant_id' => $variant?->id,
                    'satuan' => $satuan,
                    'konversi' => $konversi,
                    'qty_dasar' => $qtyDasar,
                    'jumlah' => $qty,
                    'harga_satuan_server' => $serverUnitPrice,
                    'harga_satuan_client' => $clientUnitPrice,
                    'diskon_nominal' => $diskonItem,
                    'subtotal_server' => $lineSubtotalServer,
                    'subtotal_client' => $lineSubtotalClient,
                    'hpp' => (float) $produk->harga_beli,
                ];
            }

            $clientTotalNominal = (float) ($tx['total_nominal'] ?? $tx['total_akhir'] ?? $rawClientSubtotal);
            $discrepancy = round($serverCalculatedSubtotal - $clientTotalNominal, 2);
            $syncStatus = abs($discrepancy) > 0 ? 'discrepancy_fixed' : 'synced';

            // 4. Nomor Transaksi
            $clientCreatedAt = ! empty($tx['client_created_at'])
                ? Carbon::parse($tx['client_created_at'])
                : now();

            $dateStr = $clientCreatedAt->format('Ymd');
            $countToday = Transaksi::whereDate('created_at', $clientCreatedAt->toDateString())
                ->where('cabang_id', $cabangId)
                ->count() + 1;
            $noTransaksi = sprintf('TRX-OFF-C%02d-%s-%04d', $cabangId, $dateStr, $countToday);
            while (Transaksi::where('no_transaksi', $noTransaksi)->exists()) {
                $countToday++;
                $noTransaksi = sprintf('TRX-OFF-C%02d-%s-%04d', $cabangId, $dateStr, $countToday);
            }

            // 5. Kalkulasi PPN & Nilai Akhir
            // Fakta fisik kasir: kasir sudah menerima uang fisik sesuai $clientTotalNominal.
            // Nilai total_akhir dicatat sesuai kesepakatan transaksi fisik kasir.
            $dpp = $clientTotalNominal;
            $ppnNominal = 0.0;
            $pajak = $this->pajakService->hitung($cabangId, $dpp);
            if (! empty($pajak['ppn_nominal'])) {
                $ppnNominal = (float) $pajak['ppn_nominal'];
            }

            $uangDiterima = (float) ($tx['uang_diterima'] ?? $tx['jumlah_bayar'] ?? $clientTotalNominal);
            $kembalian = max(0, $uangDiterima - $clientTotalNominal);

            // 6. Simpan Header Transaksi
            $transaksi = Transaksi::create([
                'offline_uuid' => $uuid,
                'no_transaksi' => $noTransaksi,
                'cabang_id' => $cabangId,
                'kasir_id' => $userId,
                'pelanggan_id' => $pelangganId,
                'gudang_id' => $gudangId,
                'sumber' => 'pos',
                'subtotal' => $rawClientSubtotal,
                'diskon_persen' => 0,
                'diskon_nominal' => 0,
                'dpp' => $dpp,
                'pajak_nominal' => $ppnNominal,
                'ppn_nominal' => $ppnNominal,
                'total_akhir' => $clientTotalNominal,
                'metode_bayar' => $tx['metode_bayar'] ?? 'tunai',
                'jumlah_bayar' => $uangDiterima,
                'kembalian' => $kembalian,
                'status' => 'selesai',
                'catatan' => $tx['catatan'] ?? 'Transaksi POS Offline ter-sinkronisasi otomatis',
                'is_offline' => true,
                'client_created_at' => $clientCreatedAt,
                'synced_at' => now(),
                'sync_status' => $syncStatus,
                'discrepancy_amount' => $discrepancy,
                'created_at' => $clientCreatedAt,
            ]);

            // 7. Simpan Transaksi Items & Potong Stok (izinkanNegatif = true untuk fakta fisik kasir)
            $totalHpp = 0.0;
            foreach ($itemsData as $row) {
                TransaksiItem::create([
                    'transaksi_id' => $transaksi->id,
                    'produk_id' => $row['produk']->id,
                    'sku_variant_id' => $row['sku_variant_id'],
                    'jumlah' => $row['jumlah'],
                    'satuan' => $row['satuan'],
                    'konversi' => $row['konversi'],
                    'harga_satuan' => $row['harga_satuan_client'],
                    'diskon_nominal' => $row['diskon_nominal'],
                    'subtotal' => $row['subtotal_client'],
                    'hpp' => (float) $row['hpp'] * $row['konversi'],
                ]);

                $totalHpp += ((float) $row['hpp'] * $row['konversi'] * $row['jumlah']);

                if ($gudangId) {
                    $this->stokDeductionService->kurangi(
                        produkId: (int) $row['produk']->id,
                        skuVariantId: $row['sku_variant_id'] ?: null,
                        gudangId: (int) $gudangId,
                        qty: $row['qty_dasar'],
                        jenis: 'penjualan',
                        referensiTipe: Transaksi::class,
                        referensiId: $transaksi->id,
                        userId: $userId,
                        catatan: "POS Offline {$transaksi->no_transaksi} ({$row['jumlah']} {$row['satuan']})",
                        izinkanNegatif: true // Mengizinkan stok minus pasca offline sync (R-01)
                    );
                }
            }

            // 8. Update Akumulasi Pelanggan CRM
            if ($pelangganId) {
                $this->pelangganService->tambahBelanjaDanPoin($pelangganId, (float) $clientTotalNominal);
            }

            // 9. Posting Jurnal Double-Entry di Server (PRD §4.6 & PRD-OFFLINE §6.1)
            $barisJurnal = [
                [
                    'akun_kode' => '110-01', // Kas
                    'nama_akun' => 'Kas',
                    'debit' => (float) $clientTotalNominal,
                    'kredit' => 0.0,
                    'keterangan' => "Penerimaan Kas POS Offline {$transaksi->no_transaksi}",
                ],
                [
                    'akun_kode' => '410-01', // Pendapatan Penjualan
                    'nama_akun' => 'Pendapatan Penjualan',
                    'debit' => 0.0,
                    'kredit' => (float) $clientTotalNominal,
                    'keterangan' => "Penjualan POS Offline {$transaksi->no_transaksi}",
                ],
            ];

            if ($totalHpp > 0) {
                $barisJurnal[] = [
                    'akun_kode' => '510-02', // HPP
                    'nama_akun' => 'Harga Pokok Penjualan',
                    'debit' => (float) $totalHpp,
                    'kredit' => 0.0,
                    'keterangan' => "HPP POS Offline {$transaksi->no_transaksi}",
                ];
                $barisJurnal[] = [
                    'akun_kode' => '130-01', // Persediaan
                    'nama_akun' => 'Persediaan Barang Dagang',
                    'debit' => 0.0,
                    'kredit' => (float) $totalHpp,
                    'keterangan' => "Pengurangan Persediaan POS Offline {$transaksi->no_transaksi}",
                ];
            }

            // Jika ada selisih manipulasi harga, bukukan catatan audit
            if (abs($discrepancy) > 0) {
                Log::warning("[PosOfflineSync] Selisih harga terdeteksi pada {$uuid}: Discrepancy Rp {$discrepancy}");
            }

            try {
                $noJurnal = $this->jurnalService->generateNoJurnal('pos', $cabangId);
                $this->jurnalService->post(
                    noJurnal: $noJurnal,
                    tanggal: $clientCreatedAt->toDateString(),
                    sumber: 'pos',
                    lines: $barisJurnal,
                    deskripsi: "Jurnal Transaksi POS Offline {$transaksi->no_transaksi}",
                    cabangId: $cabangId,
                    userId: $transaksi->kasir_id,
                    referensiTipe: Transaksi::class,
                    referensiId: $transaksi->id
                );
            } catch (\Throwable $jurnalEx) {
                Log::error("[PosOfflineSync] Gagal membuat jurnal POS Offline {$transaksi->no_transaksi}: ".$jurnalEx->getMessage());
            }

            return [
                'offline_uuid' => $uuid,
                'status' => 'synced',
                'transaksi_id' => $transaksi->id,
                'no_transaksi' => $transaksi->no_transaksi,
                'total_akhir' => (float) $transaksi->total_akhir,
                'has_discrepancy' => abs($discrepancy) > 0,
                'discrepancy_amount' => $discrepancy,
                'message' => 'Transaksi berhasil disinkronisasi.',
            ];
        });
    }
}
