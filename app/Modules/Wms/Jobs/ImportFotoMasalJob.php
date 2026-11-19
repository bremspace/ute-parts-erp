<?php

namespace App\Modules\Wms\Jobs;

use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Services\BulkProductImageImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ImportFotoMasalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800; // 30 menit untuk pemrosesan gambar besar

    /**
     * @param  array<int, array{temp_path: string, filename: string, sku: string, produk_id: int, produk_nama: string, is_primary?: bool}>  $items
     */
    public function __construct(
        public int $importLogId,
        public array $items,
        public ?int $userId = null,
        public string $mode = 'append',
        public ?string $tempDir = null,
        public array $resolusiErrorFoto = []
    ) {}

    public function handle(
        BulkProductImageImportService $service,
        NotificationService $notifikasi
    ): void {
        @ini_set('memory_limit', '512M');
        @set_time_limit(0);

        $log = ImportLog::find($this->importLogId);
        if (! $log || $log->status === 'selesai') {
            return;
        }

        $totalItems = count($this->items);
        $log->update([
            'status' => 'proses',
            'total_baris' => $totalItems,
            'sukses' => 0,
            'gagal' => 0,
            'detail' => [
                'current_sku' => null,
                'current_nama' => null,
                'current_baris' => 0,
                'total_baris' => $totalItems,
                'sukses' => 0,
                'gagal' => 0,
                'skip' => 0,
                'recent' => [],
            ],
        ]);

        $sukses = 0;
        $gagal = 0;
        $skip = 0;
        $detail = [];
        $recentLog = [];

        try {
            foreach ($this->items as $idx => $item) {
                $nomor = $idx + 1;
                $filename = $item['filename'] ?? basename((string) ($item['temp_path'] ?? ''));
                $sku = $item['sku'] ?? '-';
                $produkNama = $item['produk_nama'] ?? '-';
                $tempPath = $item['temp_path'] ?? null;
                $produkId = $item['produk_id'] ?? null;
                $isPrimary = ! empty($item['is_primary']);

                // Resolusi error/skip dari input user
                $resErr = $this->resolusiErrorFoto[$nomor] ?? ($this->resolusiErrorFoto[$filename] ?? null);
                if ($resErr === 'skip') {
                    $skip++;
                    $detailItem = [
                        'baris' => $nomor,
                        'file' => $filename,
                        'filename' => $filename,
                        'sku' => $sku,
                        'nama' => $produkNama,
                        'produk_nama' => $produkNama,
                        'status' => 'skip',
                        'catatan' => 'Dilewati oleh pengguna',
                        'error' => null,
                        'time' => date('H:i:s'),
                    ];
                    $detail[] = $detailItem;
                    $recentLog[] = [
                        'nomor' => $nomor,
                        'sku' => $sku,
                        'file' => $filename,
                        'nama' => Str::limit($produkNama, 35),
                        'status' => 'skip',
                        'catatan' => 'Dilewati oleh pengguna',
                        'time' => date('H:i:s'),
                    ];
                    if (count($recentLog) > 8) {
                        array_shift($recentLog);
                    }

                    continue;
                }

                if (! $tempPath || ! file_exists($tempPath) || ! $produkId) {
                    $gagal++;
                    $errMsg = 'Berkas sementara tidak ditemukan atau ID produk tidak valid';
                    $detailItem = [
                        'baris' => $nomor,
                        'file' => $filename,
                        'filename' => $filename,
                        'sku' => $sku,
                        'nama' => $produkNama,
                        'produk_nama' => $produkNama,
                        'status' => 'gagal',
                        'catatan' => null,
                        'error' => $errMsg,
                        'time' => date('H:i:s'),
                    ];
                    $detail[] = $detailItem;
                    $recentLog[] = [
                        'nomor' => $nomor,
                        'sku' => $sku,
                        'file' => $filename,
                        'nama' => Str::limit($produkNama, 35),
                        'status' => 'gagal',
                        'catatan' => $errMsg,
                        'time' => date('H:i:s'),
                    ];
                } else {
                    try {
                        $res = $service->processAndAttach(
                            $tempPath,
                            $produkId,
                            $this->mode,
                            $isPrimary
                        );

                        if (! empty($res['skipped'])) {
                            $skip++;
                            $catatan = $res['message'] ?? 'Dilewati karena produk sudah memiliki foto';
                            $detailItem = [
                                'baris' => $nomor,
                                'file' => $filename,
                                'filename' => $filename,
                                'sku' => $sku,
                                'nama' => $produkNama,
                                'produk_nama' => $produkNama,
                                'status' => 'skip',
                                'catatan' => $catatan,
                                'error' => null,
                                'time' => date('H:i:s'),
                            ];
                            $detail[] = $detailItem;
                            $recentLog[] = [
                                'nomor' => $nomor,
                                'sku' => $sku,
                                'file' => $filename,
                                'nama' => Str::limit($produkNama, 35),
                                'status' => 'skip',
                                'catatan' => $catatan,
                                'time' => date('H:i:s'),
                            ];
                        } elseif ($res['success']) {
                            $sukses++;
                            $catatan = $res['message'] ?? 'Foto berhasil dikompresi WebP & disimpan';
                            $detailItem = [
                                'baris' => $nomor,
                                'file' => $filename,
                                'filename' => $filename,
                                'sku' => $sku,
                                'nama' => $produkNama,
                                'produk_nama' => $produkNama,
                                'status' => 'ok',
                                'catatan' => $catatan,
                                'error' => null,
                                'is_primary' => $isPrimary,
                                'time' => date('H:i:s'),
                            ];
                            $detail[] = $detailItem;
                            $recentLog[] = [
                                'nomor' => $nomor,
                                'sku' => $sku,
                                'file' => $filename,
                                'nama' => Str::limit($produkNama, 35),
                                'status' => 'ok',
                                'catatan' => $catatan,
                                'time' => date('H:i:s'),
                            ];
                        } else {
                            $gagal++;
                            $errMsg = $res['message'] ?? 'Gagal memproses foto';
                            $detailItem = [
                                'baris' => $nomor,
                                'file' => $filename,
                                'filename' => $filename,
                                'sku' => $sku,
                                'nama' => $produkNama,
                                'produk_nama' => $produkNama,
                                'status' => 'gagal',
                                'catatan' => null,
                                'error' => $errMsg,
                                'time' => date('H:i:s'),
                            ];
                            $detail[] = $detailItem;
                            $recentLog[] = [
                                'nomor' => $nomor,
                                'sku' => $sku,
                                'file' => $filename,
                                'nama' => Str::limit($produkNama, 35),
                                'status' => 'gagal',
                                'catatan' => $errMsg,
                                'time' => date('H:i:s'),
                            ];
                        }
                    } catch (\Throwable $e) {
                        $gagal++;
                        $errMsg = $e->getMessage();
                        $detailItem = [
                            'baris' => $nomor,
                            'file' => $filename,
                            'filename' => $filename,
                            'sku' => $sku,
                            'nama' => $produkNama,
                            'produk_nama' => $produkNama,
                            'status' => 'gagal',
                            'catatan' => null,
                            'error' => $errMsg,
                            'time' => date('H:i:s'),
                        ];
                        $detail[] = $detailItem;
                        $recentLog[] = [
                            'nomor' => $nomor,
                            'sku' => $sku,
                            'file' => $filename,
                            'nama' => Str::limit($produkNama, 35),
                            'status' => 'gagal',
                            'catatan' => $errMsg,
                            'time' => date('H:i:s'),
                        ];
                    }
                }

                if (count($recentLog) > 8) {
                    array_shift($recentLog);
                }

                // Update ticker progress real-time per item / small batches
                $updateInterval = $totalItems > 500 ? 10 : ($totalItems > 100 ? 5 : 1);
                if ($nomor % $updateInterval === 0 || $nomor === $totalItems) {
                    $log->update([
                        'total_baris' => $totalItems,
                        'sukses' => $sukses,
                        'gagal' => $gagal,
                        'detail' => [
                            'current_sku' => $sku,
                            'current_nama' => Str::limit($produkNama.' ('.$filename.')', 70),
                            'current_baris' => $nomor,
                            'total_baris' => $totalItems,
                            'sukses' => $sukses,
                            'gagal' => $gagal,
                            'skip' => $skip,
                            'recent' => $recentLog,
                        ],
                    ]);
                }

                if ($nomor % 10 === 0 && function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }

            // Simpan detail hasil: jika lebih dari 500 item, ringkas log untuk efisiensi RAM/DB
            $finalDetail = $totalItems > 500
                ? array_values(array_filter($detail, fn ($d) => in_array($d['status'], ['gagal', 'skip'], true)))
                : $detail;

            $log->update([
                'total_baris' => $totalItems,
                'sukses' => $sukses,
                'gagal' => $gagal,
                'status' => 'selesai',
                'detail' => $finalDetail,
            ]);

            $notifikasi->kirim(
                'inapp',
                null,
                'Import Foto Produk Selesai',
                "Import foto produk massal selesai: {$sukses} sukses, {$gagal} gagal dari {$totalItems} foto.",
                [
                    'import_log_id' => $this->importLogId,
                    'status_keseluruhan' => $gagal > 0 ? 'sukses_sebagian' : 'sukses_penuh',
                    'sukses' => $sukses,
                    'gagal' => $gagal,
                    'type' => $gagal > 0 ? 'warning' : 'success',
                ]
            );

            // Bersihkan direktori temp jika ada
            $this->cleanTempDir();
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'gagal',
                'detail' => ['fatal' => $e->getMessage()],
            ]);

            $notifikasi->kirim(
                'inapp',
                null,
                'Import Foto Produk Gagal',
                'Import foto produk gagal: '.$e->getMessage(),
                ['import_log_id' => $this->importLogId, 'type' => 'error']
            );

            $this->cleanTempDir();

            throw $e;
        }
    }

    protected function cleanTempDir(): void
    {
        if ($this->tempDir && is_dir($this->tempDir)) {
            $files = glob($this->tempDir.'/*');
            if (is_array($files)) {
                foreach ($files as $f) {
                    if (is_file($f)) {
                        @unlink($f);
                    }
                }
            }
            @rmdir($this->tempDir);
        }
    }
}
