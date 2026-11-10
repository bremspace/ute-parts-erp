<?php

namespace App\Modules\Pos\Services;

use App\Models\User;
use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReturnOfflineSyncService
{
    public function __construct(
        protected ReturnPenjualanService $returnPenjualanService
    ) {}

    /**
     * Sinkronisasi batch retur offline dari kasir.
     * Menerapkan aturan: No-Cash-Refund Offline (Hanya Tukar Unit 1-to-1).
     *
     * @param  array<int, array{
     *     retur_uuid: string,
     *     no_transaksi_asal: string,
     *     alasan?: string,
     *     tipe_retur?: string,
     *     items: array<int, array{
     *         produk_id: int,
     *         qty: int|float,
     *         kondisi_barang?: string,
     *         produk_pengganti_id?: int,
     *         qty_pengganti?: int|float
     *     }>,
     *     client_created_at?: string
     * }>  $returns
     * @return array{
     *     synced_count: int,
     *     failed_count: int,
     *     results: array<int, array{uuid: string, no_return: ?string, status: string, message: string}>
     * }
     */
    public function syncReturBatch(
        array $returns,
        int $cabangId,
        int $userId,
        ?string $batchId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $results = [];
        $syncedCount = 0;
        $failedCount = 0;

        $user = User::find($userId) ?? User::first();

        foreach ($returns as $returData) {
            $uuid = (string) ($returData['retur_uuid'] ?? '');
            $noTransaksiAsal = (string) ($returData['no_transaksi_asal'] ?? '');
            $alasan = (string) ($returData['alasan'] ?? 'Retur tukar barang offline kasir');
            $items = $returData['items'] ?? [];
            $clientCreatedAt = isset($returData['client_created_at'])
                ? rescue(fn () => \Carbon\Carbon::parse($returData['client_created_at']), now())
                : now();

            if (empty($uuid)) {
                $failedCount++;
                $results[] = [
                    'uuid' => $uuid,
                    'no_return' => null,
                    'status' => 'failed',
                    'message' => 'retur_uuid wajib diisi',
                ];
                continue;
            }

            if (empty($noTransaksiAsal) || empty($items)) {
                $failedCount++;
                $results[] = [
                    'uuid' => $uuid,
                    'no_return' => null,
                    'status' => 'failed',
                    'message' => 'Nomor nota asal dan rincian item wajib disertakan',
                ];
                continue;
            }

            try {
                $res = DB::transaction(function () use (
                    $uuid, $noTransaksiAsal, $alasan, $items,
                    $cabangId, $user, $clientCreatedAt
                ) {
                    // 1. Idempotency check: jika retur_uuid sudah terdaftar
                    $existing = ReturnPenjualan::where('offline_uuid', $uuid)->first();
                    if ($existing) {
                        return [
                            'uuid' => $uuid,
                            'no_return' => $existing->no_return,
                            'status' => 'already_synced',
                            'message' => 'Transaksi retur sudah pernah disinkronkan sebelumnya',
                        ];
                    }

                    // 2. Cari transaksi asal di cabang ini
                    $transaksi = Transaksi::where('cabang_id', $cabangId)
                        ->where(function ($q) use ($noTransaksiAsal) {
                            $q->where('no_transaksi', $noTransaksiAsal)
                                ->orWhere('offline_uuid', $noTransaksiAsal);
                        })
                        ->with('items')
                        ->first();

                    if (! $transaksi) {
                        // Transaksi asal tidak ditemukan di server: simpan sebagai draft bersyarat
                        $noReturn = $this->returnPenjualanService->generateNoReturn('RTJ', $cabangId);
                        $gudangId = $this->returnPenjualanService->resolveGudangId(null, $cabangId);

                        $returDraft = ReturnPenjualan::create([
                            'no_return' => $noReturn,
                            'transaksi_id' => null,
                            'pelanggan_id' => null,
                            'cabang_id' => $cabangId,
                            'gudang_id' => $gudangId,
                            'user_id' => $user->id,
                            'tanggal' => $clientCreatedAt->toDateString(),
                            'jumlah' => 0,
                            'status' => 'draft',
                            'metode_pengembalian' => 'tukar_produk',
                            'tipe_penyelesaian' => 'tukar_produk',
                            'alasan' => "[OFFLINE DRAFT: Nota {$noTransaksiAsal}] {$alasan}",
                            'catatan_qc' => 'Menunggu verifikasi manual nota fisik asli oleh supervisor.',
                            'channel' => 'pos',
                            'offline_uuid' => $uuid,
                            'is_offline' => true,
                            'client_created_at' => $clientCreatedAt,
                            'synced_at' => now(),
                            'sync_status' => 'draft_pending_approval',
                        ]);

                        return [
                            'uuid' => $uuid,
                            'no_return' => $returDraft->no_return,
                            'status' => 'draft_created',
                            'message' => "Nota {$noTransaksiAsal} tidak ditemukan di server; retur disimpan sebagai draft menunggu approval supervisor",
                        ];
                    }

                    // 3. Persiapkan items retur dan tukar unit
                    $returItemsPayload = [];
                    $tukarItemsPayload = [];

                    foreach ($items as $item) {
                        $produkId = (int) ($item['produk_id'] ?? 0);
                        $qty = (float) ($item['qty'] ?? 1);
                        $kondisi = (string) ($item['kondisi_barang'] ?? 'rusak');

                        // Cocokkan ke transaksi_item_id transaksi asal
                        $trxItem = $transaksi->items->firstWhere('produk_id', $produkId);
                        if (! $trxItem) {
                            throw new \Exception("Produk ID [{$produkId}] tidak terdapat pada nota {$noTransaksiAsal}");
                        }

                        $returItemsPayload[] = [
                            'transaksi_item_id' => $trxItem->id,
                            'jumlah' => $qty,
                            'kondisi_barang' => $kondisi,
                            'sn' => $item['sn'] ?? [],
                        ];

                        // Unit pengganti (aturan: No-Cash-Refund, tukar unit 1-to-1)
                        $produkPenggantiId = (int) ($item['produk_pengganti_id'] ?? $produkId);
                        $qtyPengganti = (float) ($item['qty_pengganti'] ?? $qty);

                        $tukarItemsPayload[] = [
                            'produk_id' => $produkPenggantiId,
                            'jumlah' => $qtyPengganti,
                            'sku_variant_id' => $item['sku_variant_id'] ?? null,
                            'harga_satuan' => (float) ($trxItem->harga_satuan ?? 0),
                        ];
                    }

                    // 4. Proses retur melalui ReturnPenjualanService
                    $retur = $this->returnPenjualanService->buatRetur(
                        transaksi: $transaksi,
                        items: $returItemsPayload,
                        alasan: "[OFFLINE RETUR] {$alasan}",
                        metodePengembalian: 'tukar_produk',
                        userId: $user->id,
                        tipePenyelesaian: 'tukar_produk',
                        tukarItems: $tukarItemsPayload,
                        channel: 'pos',
                        fotoBukti: [],
                        catatanQc: 'Verifikasi nota fisik offline berhasil disinkronkan'
                    );

                    // 5. Tandai metadata sinkronisasi offline
                    $retur->update([
                        'offline_uuid' => $uuid,
                        'is_offline' => true,
                        'client_created_at' => $clientCreatedAt,
                        'synced_at' => now(),
                        'sync_status' => 'synced',
                    ]);

                    return [
                        'uuid' => $uuid,
                        'no_return' => $retur->no_return,
                        'status' => 'synced',
                        'message' => 'Retur tukar barang berhasil disinkronkan',
                    ];
                });

                if (in_array($res['status'], ['synced', 'draft_created', 'already_synced'], true)) {
                    $syncedCount++;
                } else {
                    $failedCount++;
                }
                $results[] = $res;
            } catch (\Throwable $e) {
                Log::error("ReturnOfflineSync error [UUID: {$uuid}]: ".$e->getMessage(), [
                    'retur' => $returData,
                    'trace' => $e->getTraceAsString(),
                ]);

                $failedCount++;
                $results[] = [
                    'uuid' => $uuid,
                    'no_return' => null,
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return [
            'synced_count' => $syncedCount,
            'failed_count' => $failedCount,
            'batch_id' => $batchId,
            'results' => $results,
        ];
    }
}
