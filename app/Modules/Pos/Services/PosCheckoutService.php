<?php

namespace App\Modules\Pos\Services;

use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Akunting\Services\PajakService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Services\PelangganService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Services\TransactionActivityLogService;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Reseller\Services\KomisiService;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Services\NomorSeriService;
use App\Modules\Wms\Services\StokDeductionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * [Phase 2 / T-05] Unified POS Checkout Domain Service.
 * Centralizes transactional consistency, inventory deductions, serial numbers, CRM loyalty, AR, and accounting journals.
 */
class PosCheckoutService
{
    public function __construct(
        protected TransaksiSequenceService $sequenceService,
        protected StokDeductionService $stokService,
        protected PelangganService $pelangganService,
        protected JurnalService $jurnalService,
        protected PajakService $pajakService,
        protected KomisiService $komisiService
    ) {}

    /**
     * Execute POS checkout atomically.
     *
     * @param array{
     *     cabang_id: int,
     *     kasir_id?: ?int,
     *     pelanggan_id?: ?int,
     *     gudang_id?: ?int,
     *     sumber?: string,
     *     subtotal: float,
     *     diskon_persen?: float,
     *     diskon_nominal?: float,
     *     diskon_manual_authorized_by_id?: ?int,
     *     diskon_manual_alasan?: ?string,
     *     diskon_poin?: float,
     *     poin_ditukar?: int,
     *     diskon_partai?: float,
     *     mode_reward_mitra?: ?string,
     *     komisi_mitra_nominal?: float,
     *     dpp: float,
     *     pajak_nominal?: float,
     *     total_akhir: float,
     *     metode_bayar: string,
     *     jumlah_bayar: float,
     *     kembalian: float,
     *     split_detail?: ?array,
     *     catatan?: ?string,
     *     durasi_garansi_hari?: int,
     *     garansi_teks?: ?string,
     *     garansi_berakhir_pada?: ?string,
     *     catatan_garansi?: ?string,
     *     tiket_servis_id?: ?int
     * } $data
     * @param array<int, array{
     *     produk_id: int,
     *     sku_variant_id?: ?int,
     *     jumlah: int|float,
     *     satuan?: ?string,
     *     konversi?: float,
     *     harga_satuan: float,
     *     diskon_nominal?: float,
     *     subtotal: float,
     *     hpp?: float,
     *     catatan?: ?string,
     *     sn_list?: array,
     *     has_sn?: bool
     * }> $items
     */
    public function checkout(array $data, array $items): Transaksi
    {
        $execute = function () use ($data, $items) {
            $cabangId = (int) $data['cabang_id'];

            // [T-18] Transaksi piutang / kasbon wajib memilih data pelanggan
            $isPiutang = in_array(strtolower((string) ($data['metode_bayar'] ?? '')), ['piutang', 'kasbon'], true);
            if ($isPiutang && empty($data['pelanggan_id'])) {
                throw new \InvalidArgumentException('Transaksi kasbon/piutang wajib memilih data pelanggan.');
            }

            // Validasi pelanggan wajib berstatus aktif (tidak boleh pending/unapproved/nonaktif/ditolak)
            if (! empty($data['pelanggan_id'])) {
                $pelanggan = Pelanggan::find($data['pelanggan_id']);
                if (! $pelanggan) {
                    throw new \InvalidArgumentException('Data pelanggan tidak ditemukan.');
                }
                $isAllowedStatus = in_array($pelanggan->status, ['aktif', 'approved'], true);
                if (! $isAllowedStatus) {
                    throw new \InvalidArgumentException("Pelanggan {$pelanggan->nama} belum diverifikasi/diapprove (status: {$pelanggan->status}).");
                }
            }

            $noTransaksi = $this->sequenceService->generateNoTransaksi($cabangId);

            $transaksi = Transaksi::create([
                'no_transaksi' => $noTransaksi,
                'cabang_id' => $cabangId,
                'kasir_id' => $data['kasir_id'] ?? auth()->id() ?? 1,
                'pelanggan_id' => $data['pelanggan_id'] ?? null,
                'gudang_id' => $data['gudang_id'] ?? null,
                'sumber' => $data['sumber'] ?? 'pos',
                'subtotal' => (float) $data['subtotal'],
                'diskon_persen' => (float) ($data['diskon_persen'] ?? 0),
                'diskon_nominal' => (float) ($data['diskon_nominal'] ?? 0),
                'diskon_manual_authorized_by_id' => $data['diskon_manual_authorized_by_id'] ?? null,
                'diskon_manual_alasan' => $data['diskon_manual_alasan'] ?? null,
                'diskon_poin' => (float) ($data['diskon_poin'] ?? 0),
                'poin_ditukar' => (int) ($data['poin_ditukar'] ?? 0),
                'diskon_partai' => (float) ($data['diskon_partai'] ?? 0),
                'mode_reward_mitra' => $data['mode_reward_mitra'] ?? null,
                'komisi_mitra_nominal' => (float) ($data['komisi_mitra_nominal'] ?? 0),
                'dpp' => (float) $data['dpp'],
                'pajak_nominal' => (float) ($data['pajak_nominal'] ?? 0),
                'ppn_nominal' => (float) ($data['pajak_nominal'] ?? 0),
                'total_akhir' => (float) $data['total_akhir'],
                'metode_bayar' => $data['metode_bayar'],
                'jumlah_bayar' => (float) $data['jumlah_bayar'],
                'kembalian' => (float) $data['kembalian'],
                'split_detail' => $data['split_detail'] ?? null,
                'status' => 'selesai',
                'catatan' => $data['catatan'] ?? null,
                'durasi_garansi_hari' => (int) ($data['durasi_garansi_hari'] ?? 0),
                'garansi_teks' => $data['garansi_teks'] ?? null,
                'garansi_berakhir_pada' => $data['garansi_berakhir_pada'] ?? null,
                'catatan_garansi' => $data['catatan_garansi'] ?? null,
                'tiket_servis_id' => $data['tiket_servis_id'] ?? null,
            ]);

            $produkIds = array_unique(array_filter(array_column($items, 'produk_id')));
            $produkMap = ! empty($produkIds) ? Produk::whereIn('id', $produkIds)->get()->keyBy('id') : collect();

            $totalHpp = 0.0;

            foreach ($items as $item) {
                $produk = $produkMap->get($item['produk_id']);
                $satuanDasar = $produk?->satuan ?: 'pcs';
                $satuanItem = $item['satuan'] ?? $satuanDasar;
                $konversi = max(0.0001, (float) ($item['konversi'] ?? 1));
                $qty = (float) $item['jumlah'];
                $qtyDasar = (int) round($qty * $konversi);
                $hppPerSatuan = isset($item['hpp']) ? (float) $item['hpp'] : ($produk ? (float) $produk->harga_beli * $konversi : 0);

                $transaksiItem = TransaksiItem::create([
                    'transaksi_id' => $transaksi->id,
                    'produk_id' => $item['produk_id'],
                    'sku_variant_id' => $item['sku_variant_id'] ?? null,
                    'jumlah' => $qty,
                    'satuan' => $satuanItem,
                    'konversi' => $konversi,
                    'harga_satuan' => (float) $item['harga_satuan'],
                    'diskon_nominal' => (float) ($item['diskon_nominal'] ?? 0),
                    'subtotal' => (float) $item['subtotal'],
                    'hpp' => $hppPerSatuan,
                    'catatan' => $item['catatan'] ?? null,
                ]);

                // Serial Number Claim if applicable
                $hasSn = $item['has_sn'] ?? ($produk?->garansi_tipe === 'sn' || (bool) $produk?->has_sn);
                if ($hasSn && ! empty($item['sn_list'])) {
                    app(NomorSeriService::class)->klaimJual(
                        $item['sn_list'],
                        (int) $item['produk_id'],
                        $cabangId,
                        $qtyDasar,
                        $transaksiItem->id
                    );
                }

                // Inventory Deduction
                if (! empty($data['gudang_id'])) {
                    $this->stokService->kurangi(
                        produkId: (int) $item['produk_id'],
                        skuVariantId: $item['sku_variant_id'] ?? null,
                        gudangId: (int) $data['gudang_id'],
                        qty: $qtyDasar,
                        jenis: 'penjualan',
                        referensiTipe: Transaksi::class,
                        referensiId: $transaksi->id,
                        userId: $data['kasir_id'] ?? auth()->id(),
                        catatan: "POS Penjualan {$transaksi->no_transaksi} ({$qty} {$satuanItem})",
                    );
                }

                $hppSatuanDasar = $produk ? (float) $produk->harga_beli : 0;
                $totalHpp += $hppSatuanDasar * $qtyDasar;
            }

            // Customer Loyalty & CRM Points
            $pelangganId = $data['pelanggan_id'] ?? null;
            $poinDitukar = (int) ($data['poin_ditukar'] ?? 0);
            if ($pelangganId) {
                if ($poinDitukar > 0) {
                    $this->pelangganService->tukarPoin(
                        $pelangganId,
                        $poinDitukar,
                        $transaksi->id,
                        "Tukar {$poinDitukar} poin pada transaksi {$transaksi->no_transaksi}"
                    );
                }
                $this->pelangganService->tambahBelanjaDanPoin(
                    $pelangganId,
                    (float) $data['total_akhir'],
                    $transaksi->id
                );
            }

            // Accounting Journal
            $noJurnal = $this->jurnalService->generateNoJurnal('pos', $cabangId);
            $kasbon = $data['metode_bayar'] === 'piutang';
            $ppnNominal = (float) ($data['pajak_nominal'] ?? 0);
            $dpp = (float) $data['dpp'];
            $totalAkhir = (float) $data['total_akhir'];

            $debitLines = [];
            if ($kasbon) {
                $debitLines[] = ['akun_kode' => '120-01', 'debit' => $totalAkhir, 'kredit' => 0];
            } elseif ($data['metode_bayar'] === 'tunai') {
                $debitLines[] = ['akun_kode' => '110-04', 'debit' => $totalAkhir, 'kredit' => 0];
            } elseif ($data['metode_bayar'] === 'split' && ! empty($data['split_detail'])) {
                $nonTunai = (float) ($data['split_detail']['non_tunai'] ?? 0);
                $porsiBank = min($totalAkhir, $nonTunai);
                $porsiKas = max(0.0, $totalAkhir - $porsiBank);

                if ($porsiKas > 0) {
                    $debitLines[] = ['akun_kode' => '110-04', 'debit' => $porsiKas, 'kredit' => 0];
                }
                if ($porsiBank > 0) {
                    $debitLines[] = ['akun_kode' => '110-02', 'debit' => $porsiBank, 'kredit' => 0];
                }
            } else {
                $debitLines[] = ['akun_kode' => '110-02', 'debit' => $totalAkhir, 'kredit' => 0];
            }

            $lines = array_merge($debitLines, [
                ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => $ppnNominal > 0 ? $dpp : $totalAkhir],
            ]);

            foreach ($this->pajakService->jurnalLines($ppnNominal, $noJurnal, $cabangId, $data['kasir_id'] ?? auth()->id() ?? 0) as $ppnLine) {
                $lines[] = $ppnLine;
            }

            if ($totalHpp > 0) {
                $lines[] = ['akun_kode' => '510-02', 'debit' => $totalHpp, 'kredit' => 0];
                $lines[] = ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => $totalHpp];
            }

            $this->jurnalService->post(
                $noJurnal,
                now(),
                'pos',
                $lines,
                "Jurnal POS {$noTransaksi}",
                $cabangId,
                $data['kasir_id'] ?? auth()->id(),
                Transaksi::class,
                $transaksi->id
            );

            // Accounts Receivable (Piutang)
            if ($kasbon && $pelangganId) {
                $countPiutang = Piutang::where('cabang_id', $cabangId)
                    ->whereDate('created_at', now()->toDateString())
                    ->count() + 1;
                Piutang::create([
                    'no_piutang' => sprintf('AR-%s-%04d', now()->format('Ymd'), $countPiutang),
                    'pelanggan_id' => $pelangganId,
                    'transaksi_id' => $transaksi->id,
                    'cabang_id' => $cabangId,
                    'jumlah' => $totalAkhir,
                    'jumlah_dibayar' => 0,
                    'sisa' => $totalAkhir,
                    'jatuh_tempo' => now()->addDays(30)->toDateString(),
                    'status' => 'belum_lunas',
                    'keterangan' => 'Kasbon POS '.$noTransaksi,
                ]);
                Pelanggan::where('id', $pelangganId)->increment('saldo_piutang', $totalAkhir);
            }

            // Multi-Actor Reseller Commissions
            $this->komisiService->hitungKomisiMultiAktor('penjualan', ['transaksi_id' => $transaksi->id]);

            if ($transaksi->mode_reward_mitra === 'komisi_akumulasi' && (float) $transaksi->komisi_mitra_nominal > 0 && $pelangganId) {
                $sudahAda = Komisi::where('transaksi_id', $transaksi->id)->where('pelanggan_id', $pelangganId)->exists();
                if (! $sudahAda) {
                    $today = now()->format('Ymd');
                    $count = Komisi::whereDate('created_at', now()->toDateString())->count() + 1;
                    $noKomisi = sprintf('KMS-%s-%04d', $today, $count);
                    $pelangganModel = Pelanggan::find($pelangganId);
                    $tipeKonsumen = $pelangganModel ? $this->pelangganService->tipeKonsumen($pelangganModel) : 'reseller';
                    Komisi::create([
                        'no_komisi' => $noKomisi,
                        'pelanggan_id' => $pelangganId,
                        'transaksi_id' => $transaksi->id,
                        'aktor_tipe' => $tipeKonsumen === 'agen' ? 'agen' : 'reseller',
                        'aktor_id' => $pelangganId,
                        'nominal_komisi' => $transaksi->komisi_mitra_nominal,
                        'jumlah_transaksi' => $transaksi->total_akhir,
                        'status' => Komisi::STATUS_PENDING,
                        'keterangan' => 'Komisi akumulasi mitra transaksi '.$transaksi->no_transaksi,
                    ]);
                }
            }

            try {
                app(TransactionActivityLogService::class)->catatPenjualan($transaksi);
            } catch (\Throwable $e) {
                Log::warning('Gagal mencatat audit log checkout: '.$e->getMessage());
            }

            return $transaksi;
        };

        return DB::transactionLevel() > 0 ? $execute() : DB::transaction($execute);
    }
}
