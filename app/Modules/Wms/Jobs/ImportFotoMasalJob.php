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
            'detail' => null,
        ]);

        $sukses = 0;
        $gagal = 0;
        $detail = [];

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
                    $detail[] = [
                        'baris' => $nomor,
                        'file' => $filename,
                        'sku' => $sku,
                        'nama' => $produkNama,
                        'status' => 'skip',
                        'catatan' => 'Dilewati oleh pengguna',
                    ];

                    continue;
                }

                if (! $tempPath || ! file_exists($tempPath) || ! $produkId) {
                    $gagal++;
                    $detail[] = [
                        'baris' => $nomor,
                        'file' => $filename,
                        'sku' => $sku,
                        'nama' => $produkNama,
                        'status' => 'gagal',
                        'error' => 'Berkas sementara tidak ditemukan atau ID produk tidak valid',
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
                            $detail[] = [
                                'baris' => $nomor,
                                'file' => $filename,
                                'sku' => $sku,
                                'nama' => $produkNama,
                                'status' => 'skip',
                                'catatan' => $res['message'] ?? 'Dilewati karena produk sudah memiliki foto',
                            ];
                        } elseif ($res['success']) {
                            $sukses++;
                            $detail[] = [
                                'baris' => $nomor,
                                'file' => $filename,
                                'sku' => $sku,
                                'nama' => $produkNama,
                                'status' => 'ok',
                                'catatan' => $res['message'] ?? 'Foto berhasil dikompresi WebP & disimpan',
                            ];
                        } else {
                            $gagal++;
                            $detail[] = [
                                'baris' => $nomor,
                                'file' => $filename,
                                'sku' => $sku,
                                'nama' => $produkNama,
                                'status' => 'gagal',
                                'error' => $res['message'] ?? 'Gagal memproses foto',
                            ];
                        }
                    } catch (\Throwable $e) {
                        $gagal++;
                        $detail[] = [
                            'baris' => $nomor,
                            'file' => $filename,
                            'sku' => $sku,
                            'nama' => $produkNama,
                            'status' => 'gagal',
                            'error' => $e->getMessage(),
                        ];
                    }
                }

                // Update ticker progress real-time per item / small batches
                if ($nomor % 2 === 0 || $nomor === $totalItems) {
                    $log->update([
                        'total_baris' => $totalItems,
                        'sukses' => $sukses,
                        'gagal' => $gagal,
                        'detail' => [
                            'current_sku' => $sku,
                            'current_nama' => Str::limit($produkNama.' ('.$filename.')', 70),
                            'current_baris' => $nomor,
                            'total_baris' => $totalItems,
                        ],
                    ]);
                }

                if ($nomor % 10 === 0 && function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }

            $log->update([
                'total_baris' => $totalItems,
                'sukses' => $sukses,
                'gagal' => $gagal,
                'status' => 'selesai',
                'detail' => $detail,
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
