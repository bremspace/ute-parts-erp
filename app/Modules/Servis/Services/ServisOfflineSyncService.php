<?php

namespace App\Modules\Servis\Services;

use App\Models\User;
use App\Modules\Servis\Models\TiketServis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ServisOfflineSyncService
{
    public function __construct(
        protected ServisService $servisService
    ) {}

    /**
     * Ambil tiket aktif berstatus siap diambil (qc / selesai) untuk di-cache di IndexedDB kasir lokal.
     *
     * @return array<int, array{
     *     id: int,
     *     no_tiket: string,
     *     nama_pelanggan: string,
     *     telepon_pelanggan: ?string,
     *     jenis_hp: string,
     *     seri_hp: ?string,
     *     status: string,
     *     status_pembayaran: string,
     *     total_biaya: float,
     *     estimasi_biaya: float
     * }>
     */
    public function getActiveTicketsForCache(int $cabangId, int $limit = 200): array
    {
        $tickets = TiketServis::query()
            ->where('cabang_id', $cabangId)
            ->whereIn('status', ['qc', 'selesai'])
            ->with(['items' => fn ($q) => $q->whereNull('dibatalkan_at')])
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        return $tickets->map(function (TiketServis $t) {
            $rincian = $t->getRincianBiayaLengkap();
            $totalBiaya = (float) ($rincian['total'] ?? $t->estimasi_biaya ?? 0);
            $sisaTagihan = $t->status_pembayaran === 'lunas' ? 0.0 : $totalBiaya;

            return [
                'id' => $t->id,
                'no_tiket' => $t->no_tiket,
                'nama_pelanggan' => $t->nama_pelanggan,
                'telepon_pelanggan' => $t->telepon_pelanggan,
                'jenis_hp' => $t->jenis_hp,
                'seri_hp' => $t->seri_hp,
                'tipe_gadget' => trim($t->jenis_hp.' '.($t->seri_hp ?? '')),
                'status' => $t->status,
                'status_pembayaran' => $t->status_pembayaran,
                'total_biaya' => $totalBiaya,
                'sisa_tagihan' => $sisaTagihan,
                'estimasi_biaya' => (float) ($t->estimasi_biaya ?? 0),
                'updated_at' => $t->updated_at?->toIso8601String(),
            ];
        })->values()->all();
    }

    /**
     * Sinkronisasi batch pengambilan servis offline dari kasir.
     * Idempoten berdasarkan pickup_uuid.
     *
     * @param  array<int, array{
     *     pickup_uuid: string,
     *     tiket_id?: int,
     *     no_tiket?: string,
     *     nama_pengambil?: string,
     *     metode_pembayaran?: string,
     *     nominal_pelunasan?: float,
     *     client_created_at?: string
     * }>  $pickups
     * @return array{
     *     synced_count: int,
     *     failed_count: int,
     *     results: array<int, array{uuid: string, no_tiket: ?string, status: string, message: string}>
     * }
     */
    public function syncPickupBatch(
        array $pickups,
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

        foreach ($pickups as $item) {
            $uuid = (string) ($item['pickup_uuid'] ?? '');
            $noTiket = (string) ($item['no_tiket'] ?? '');
            $tiketId = isset($item['tiket_id']) ? (int) $item['tiket_id'] : null;
            $namaPengambil = (string) ($item['nama_pengambil'] ?? 'Pelanggan Langsung');
            $metodeBayar = (string) ($item['metode_pembayaran'] ?? 'tunai');
            $clientCreatedAt = isset($item['client_created_at']) ? rescue(fn () => \Carbon\Carbon::parse($item['client_created_at']), now()) : now();

            if (empty($uuid)) {
                $failedCount++;
                $results[] = [
                    'uuid' => $uuid,
                    'no_tiket' => $noTiket,
                    'status' => 'failed',
                    'message' => 'pickup_uuid wajib diisi',
                ];
                continue;
            }

            try {
                $res = DB::transaction(function () use (
                    $uuid, $tiketId, $noTiket, $namaPengambil, $metodeBayar,
                    $cabangId, $user, $clientCreatedAt
                ) {
                    // 1. Cek Idempotensi: jika sudah pernah diproses dengan UUID yang sama
                    $existing = TiketServis::where('offline_pickup_uuid', $uuid)->first();
                    if ($existing) {
                        return [
                            'uuid' => $uuid,
                            'no_tiket' => $existing->no_tiket,
                            'status' => 'already_synced',
                            'message' => 'Pengambilan tiket sudah pernah disinkronkan sebelumnya',
                        ];
                    }

                    // 2. Cari tiket
                    $query = TiketServis::where('cabang_id', $cabangId)->lockForUpdate();
                    if ($tiketId) {
                        $query->where('id', $tiketId);
                    } else {
                        $query->where('no_tiket', $noTiket);
                    }
                    $tiket = $query->first();

                    if (! $tiket) {
                        return [
                            'uuid' => $uuid,
                            'no_tiket' => $noTiket,
                            'status' => 'failed',
                            'message' => "Tiket servis [{$noTiket}] tidak ditemukan pada cabang terkait",
                        ];
                    }

                    // 3. Jika tiket sudah berstatus diambil
                    if ($tiket->status === 'diambil') {
                        if ($tiket->offline_pickup_uuid === $uuid) {
                            return [
                                'uuid' => $uuid,
                                'no_tiket' => $tiket->no_tiket,
                                'status' => 'already_synced',
                                'message' => 'Tiket sudah pernah disinkronkan sebelumnya',
                            ];
                        }

                        return [
                            'uuid' => $uuid,
                            'no_tiket' => $tiket->no_tiket,
                            'status' => 'conflict',
                            'message' => 'Tiket sudah diserahkan oleh kasir/perangkat lain (Unit sudah berstatus diambil)',
                        ];
                    }

                    // 4. Validasi alur transisi: wajib 'qc' atau 'selesai'
                    if (! in_array($tiket->status, ['qc', 'selesai'], true)) {
                        return [
                            'uuid' => $uuid,
                            'no_tiket' => $tiket->no_tiket,
                            'status' => 'conflict',
                            'message' => "Status tiket saat ini ({$tiket->status}) belum siap diserahkan",
                        ];
                    }

                    // Jika status 'qc', majukan dulu ke 'selesai'
                    if ($tiket->status === 'qc') {
                        $tiket = $this->servisService->updateStatus(
                            $tiket,
                            'selesai',
                            $user,
                            'Auto-QC pass via sinkronisasi penyerahan kasir offline'
                        );
                    }

                    // 5. Pembayaran pelunasan bila belum lunas
                    if ($tiket->status_pembayaran !== 'lunas') {
                        $tiket = $this->servisService->bayar(
                            $tiket,
                            $metodeBayar,
                            $user
                        );
                    }

                    // 6. Transisi ke status 'diambil' (serah terima unit)
                    $tiket = $this->servisService->updateStatus(
                        $tiket,
                        'diambil',
                        $user,
                        "Unit diserahkan offline kepada: {$namaPengambil}"
                    );

                    // 7. Tandai metadata offline pickup
                    $tiket->update([
                        'offline_pickup_uuid' => $uuid,
                        'is_offline_pickup' => true,
                        'pickup_synced_at' => now(),
                        'tanggal_diambil' => $clientCreatedAt,
                    ]);

                    return [
                        'uuid' => $uuid,
                        'no_tiket' => $tiket->no_tiket,
                        'status' => 'synced',
                        'message' => 'Pengambilan unit berhasil disinkronkan',
                    ];
                });

                if (in_array($res['status'], ['synced', 'already_synced'], true)) {
                    $syncedCount++;
                } else {
                    $failedCount++;
                }
                $results[] = $res;
            } catch (\Throwable $e) {
                Log::error("ServisOfflineSync error [UUID: {$uuid}]: ".$e->getMessage(), [
                    'item' => $item,
                    'trace' => $e->getTraceAsString(),
                ]);

                $failedCount++;
                $results[] = [
                    'uuid' => $uuid,
                    'no_tiket' => $noTiket,
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
