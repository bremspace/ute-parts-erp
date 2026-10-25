<?php

namespace App\Modules\Wms\Services;

use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BulkProductImageImportService
{
    /**
     * Seluruh ekstensi format gambar yang didukung sistem (standar web, smartphone, kamera, format modern, dan RAW).
     */
    public const SUPPORTED_EXTENSIONS = [
        'jpg', 'jpeg', 'jpe', 'jif', 'jfif',
        'png',
        'webp',
        'gif',
        'bmp', 'dib',
        'avif',
        'heic', 'heif',
        'tiff', 'tif',
        'ico',
        'svg',
        'psd',
        'tga',
        'dng', 'cr2', 'nef', 'arw', 'raw',
    ];

    public function __construct(
        protected ProductImageService $imageService
    ) {}

    /**
     * Cek secara cerdas apakah berkas merupakan format gambar yang valid
     * (berdasarkan ekstensi maupun verifikasi MIME tipe biner).
     */
    public function isImageFile(string $filename, ?string $tempPath = null): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, self::SUPPORTED_EXTENSIONS, true)) {
            return true;
        }

        if ($tempPath && file_exists($tempPath)) {
            $info = @getimagesize($tempPath);
            if ($info && ! empty($info['mime']) && str_starts_with($info['mime'], 'image/')) {
                return true;
            }
            if (function_exists('mime_content_type')) {
                $mime = @mime_content_type($tempPath);
                if ($mime && str_starts_with($mime, 'image/')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Dapatkan daftar kandidat SKU dari nama file dan jalur subfolder secara berurutan.
     *
     * @return array<int, string>
     */
    public function getPossibleSkuCandidates(string $filename, ?string $relativePath = null): array
    {
        $candidates = [];
        $cleanRel = $relativePath ? trim(str_replace('\\', '/', $relativePath), '/') : '';

        // 1. Jika dalam subfolder, prioritaskan nama folder
        if ($cleanRel !== '' && str_contains($cleanRel, '/')) {
            $parts = explode('/', $cleanRel);
            $folderName = trim($parts[0]);
            $ignoredFolders = ['images', 'foto', 'fotos', 'produk', 'products', 'upload', 'uploads', 'assets', 'img'];
            if (! in_array(strtolower($folderName), $ignoredFolders, true)) {
                $candidates[] = $this->sanitizeSkuString($folderName);
            }
        }

        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        $cleanBase = $this->sanitizeSkuString($baseName);
        if ($cleanBase !== '') {
            $candidates[] = $cleanBase;
        }

        // 2. Coba stripping penanda urutan seperti _1, -1, (1), _main, -cover, dll.
        $pattern = '/[_\-\s]+(main|cover|utama|depan|front|belakang|back|thumb|\(\d+\)|\d+)$/i';
        $stripped = preg_replace($pattern, '', $baseName);
        $cleanStripped = $this->sanitizeSkuString($stripped ?: '');
        if ($cleanStripped !== '' && ! in_array($cleanStripped, $candidates, true)) {
            $candidates[] = $cleanStripped;
        }

        return array_values(array_filter($candidates));
    }

    /**
     * Parsing dan ekstraksi SKU terbaik dari nama file dan/atau relative subfolder path.
     */
    public function parseSkuFromFilename(string $filename, ?string $relativePath = null): string
    {
        $cleanRel = $relativePath ? trim(str_replace('\\', '/', $relativePath), '/') : '';

        // Jika dalam subfolder valid (misal: "LCD-IP13-PRO/front.jpg"), folder induk adalah SKU utama
        if ($cleanRel !== '' && str_contains($cleanRel, '/')) {
            $parts = explode('/', $cleanRel);
            $folderName = trim($parts[0]);
            $ignoredFolders = ['images', 'foto', 'fotos', 'produk', 'products', 'upload', 'uploads', 'assets', 'img'];
            if (! in_array(strtolower($folderName), $ignoredFolders, true)) {
                return $this->sanitizeSkuString($folderName);
            }
        }

        // Jika bukan subfolder, cek apakah ada versi stripped (_1, -1, dll.)
        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        $pattern = '/[_\-\s]+(main|cover|utama|depan|front|belakang|back|thumb|\(\d+\)|\d+)$/i';
        $stripped = preg_replace($pattern, '', $baseName);
        $cleanStripped = $this->sanitizeSkuString($stripped ?: '');

        return $cleanStripped !== '' ? $cleanStripped : $this->sanitizeSkuString($baseName);
    }

    /**
     * Sanitasi string SKU kandidat agar rapi dan seragam.
     */
    public function sanitizeSkuString(string $raw): string
    {
        // Hilangkan karakter aneh yang sering terbawa dari sistem operasi atau download
        $clean = trim($raw);
        $clean = preg_replace('/[^\w\-\.\/]/u', ' ', $clean);
        $clean = preg_replace('/\s+/', ' ', $clean);

        return trim((string) $clean);
    }

    /**
     * Deteksi apakah file ini memiliki suffix penanda sebagai foto utama (primary).
     */
    public function isPrimaryDesignation(string $filename): bool
    {
        $baseName = strtolower(pathinfo($filename, PATHINFO_FILENAME));

        return (bool) preg_match('/[_\-\s]*(main|cover|utama|depan|front|_1|-1|\(1\))$/i', $baseName);
    }

    /**
     * Pre-scan daftar file atau nama file terhadap database master produk.
     *
     * @param  array<int, array{name: string, relative_path?: string, size?: int, temp_path?: string}>  $files
     * @return array{
     *     total: int,
     *     matched_count: int,
     *     skipped_count: int,
     *     matched: array<int, array>,
     *     skipped: array<int, array>
     * }
     */
    public function preScanFiles(array $files): array
    {
        $matched = [];
        $skipped = [];

        // Kumpulkan semua kandidat SKU unik untuk batch query agar super cepat
        $skuCandidates = [];
        $fileCandidateMap = [];
        foreach ($files as $idx => $f) {
            $name = $f['name'] ?? '';
            $rel = $f['relative_path'] ?? null;
            $candidates = $this->getPossibleSkuCandidates($name, $rel);
            $fileCandidateMap[$idx] = $candidates;
            foreach ($candidates as $cand) {
                if ($cand !== '') {
                    $skuCandidates[strtolower($cand)][] = $idx;
                }
            }
        }

        $lookupKeys = array_keys($skuCandidates);

        $dbVariants = collect();
        $dbBarcodes = collect();

        if (! empty($lookupKeys)) {
            // Cari di tabel sku_variants yang berelasi ke produk berdasarkan SKU
            $dbVariants = SkuVariant::query()
                ->with(['produk:id,nama,gambar,foto'])
                ->where(function ($q) use ($lookupKeys) {
                    foreach (array_chunk($lookupKeys, 200) as $chunk) {
                        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                        $q->orWhereRaw("LOWER(TRIM(sku)) IN ({$placeholders})", $chunk);
                    }
                })
                ->get()
                ->keyBy(fn ($item) => strtolower(trim((string) $item->sku)));

            // Cari juga berdasarkan Barcode pada sku_variants
            $dbBarcodes = SkuVariant::query()
                ->with(['produk:id,nama,gambar,foto'])
                ->where(function ($q) use ($lookupKeys) {
                    foreach (array_chunk($lookupKeys, 200) as $chunk) {
                        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                        $q->orWhereRaw("LOWER(TRIM(barcode)) IN ({$placeholders})", $chunk);
                    }
                })
                ->get()
                ->keyBy(fn ($item) => strtolower(trim((string) $item->barcode)));
        }

        foreach ($files as $idx => $f) {
            $name = $f['name'] ?? 'file_'.$idx;
            $rel = $f['relative_path'] ?? null;
            $size = (int) ($f['size'] ?? 0);
            $tempPath = $f['temp_path'] ?? null;

            // Validasi format ekstensi atau deteksi cerdas format gambar
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (! $this->isImageFile($name, $tempPath)) {
                $skipped[] = [
                    'index' => $idx,
                    'filename' => $name,
                    'relative_path' => $rel,
                    'sku' => '',
                    'reason' => 'Format file tidak didukung (.'.$ext.')',
                    'size' => $size,
                ];

                continue;
            }

            $candidates = $fileCandidateMap[$idx] ?? [];
            $matchedVariant = null;
            $matchedSku = null;

            // Coba satu per satu kandidat terhadap hasil database (SKU dan Barcode)
            foreach ($candidates as $cand) {
                $norm = strtolower($cand);
                if (isset($dbVariants[$norm]) && $dbVariants[$norm]->produk) {
                    $matchedVariant = $dbVariants[$norm];
                    $matchedSku = $matchedVariant->sku;
                    break;
                }
                if (isset($dbBarcodes[$norm]) && $dbBarcodes[$norm]->produk) {
                    $matchedVariant = $dbBarcodes[$norm];
                    $matchedSku = $matchedVariant->sku;
                    break;
                }
            }

            if (! $matchedVariant) {
                $bestGuess = $candidates[1] ?? ($candidates[0] ?? '');
                $skipped[] = [
                    'index' => $idx,
                    'filename' => $name,
                    'relative_path' => $rel,
                    'sku' => $bestGuess,
                    'reason' => 'SKU "'.$bestGuess.'" tidak ditemukan di database produk',
                    'size' => $size,
                ];

                continue;
            }

            $produk = $matchedVariant->produk;
            $hasExisting = ! empty($produk->gambar) || ! empty($produk->foto);

            $matched[] = [
                'index' => $idx,
                'filename' => $name,
                'relative_path' => $rel,
                'sku' => $matchedSku,
                'produk_id' => $produk->id,
                'produk_nama' => $produk->nama,
                'has_existing' => $hasExisting,
                'is_primary' => $this->isPrimaryDesignation($name),
                'size' => $size,
                'temp_path' => $tempPath,
            ];
        }

        return [
            'total' => count($files),
            'matched_count' => count($matched),
            'skipped_count' => count($skipped),
            'matched' => $matched,
            'skipped' => $skipped,
        ];
    }

    /**
     * Memproses satu file foto ke storage dan memperbarui data produk.
     *
     * @param  UploadedFile|string  $file  Path lokal atau UploadedFile
     * @param  int  $produkId  ID produk tujuan
     * @param  string  $mode  'append' | 'replace' | 'skip_existing'
     * @param  bool  $forcePrimary  Jika true, jadikan cover utama
     * @return array{success: bool, message: string, data?: array}
     */
    public function processAndAttach(
        UploadedFile|string $file,
        int $produkId,
        string $mode = 'append',
        bool $forcePrimary = false
    ): array {
        $produk = Produk::find($produkId);
        if (! $produk) {
            return [
                'success' => false,
                'message' => "Produk ID {$produkId} tidak ditemukan",
            ];
        }

        // Jika mode 'skip_existing' dan produk sudah punya foto
        if ($mode === 'skip_existing' && (! empty($produk->gambar) || ! empty($produk->foto))) {
            return [
                'success' => true,
                'message' => 'Dilewati karena produk sudah memiliki foto',
                'skipped' => true,
            ];
        }

        // Kompresi dan simpan ke disk WebP
        try {
            $saved = $this->imageService->prosesDanSimpan($file);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Gagal kompresi foto: '.$e->getMessage(),
            ];
        }

        return DB::transaction(function () use ($produk, $saved, $mode, $forcePrimary) {
            $existingFoto = is_array($produk->foto) ? $produk->foto : (json_decode((string) $produk->foto, true) ?: []);

            if ($mode === 'replace') {
                // Hapus foto lama dari disk storage untuk efisiensi ruang
                foreach ($existingFoto as $old) {
                    if (is_array($old) && ! empty($old['url'])) {
                        $this->imageService->hapusFoto($old['url'], $old['thumb'] ?? null);
                    }
                }
                $saved['is_primary'] = true;
                $newFotoList = [$saved];
                $primaryUrl = $saved['url'];
            } else {
                // Mode 'append'
                $isCurrentlyEmpty = empty($existingFoto) && empty($produk->gambar);
                $makePrimary = $forcePrimary || $isCurrentlyEmpty;

                if ($makePrimary) {
                    // Set status is_primary pada item lama menjadi false
                    foreach ($existingFoto as &$old) {
                        if (is_array($old)) {
                            $old['is_primary'] = false;
                        }
                    }
                    unset($old);
                    $saved['is_primary'] = true;
                    $primaryUrl = $saved['url'];
                } else {
                    $saved['is_primary'] = false;
                    $primaryUrl = $produk->gambar ?: $saved['url'];
                }

                $newFotoList = $existingFoto;
                $newFotoList[] = $saved;
            }

            $produk->update([
                'gambar' => $primaryUrl,
                'foto' => array_values($newFotoList),
            ]);

            return [
                'success' => true,
                'message' => 'Foto berhasil disimpan dan dipetakan',
                'data' => [
                    'produk_id' => $produk->id,
                    'sku' => $produk->sku,
                    'gambar' => $primaryUrl,
                    'foto_count' => count($newFotoList),
                ],
            ];
        });
    }

    /**
     * Ekstrak arsip file .zip ke direktori sementara dan kembalikan daftar file yang valid.
     *
     * @return array<int, array{name: string, relative_path: string, size: int, temp_path: string}>
     */
    public function extractZipArchive(string $zipFilePath, string $extractTargetDir): array
    {
        if (! file_exists($zipFilePath)) {
            throw new \InvalidArgumentException('File ZIP tidak ditemukan');
        }

        $zip = new \ZipArchive;
        if ($zip->open($zipFilePath) !== true) {
            throw new \RuntimeException('Gagal membuka berkas ZIP');
        }

        if (! is_dir($extractTargetDir)) {
            @mkdir($extractTargetDir, 0775, true);
        }

        $extractedFiles = [];
        $validExts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $entryName = $stat['name'] ?? '';

            // Abaikan folder, hidden files, dan direktori metadata Mac / Windows
            if (str_ends_with($entryName, '/') || str_starts_with(basename($entryName), '.') || str_contains($entryName, '__MACOSX')) {
                continue;
            }

            if (! $this->isImageFile($entryName)) {
                continue;
            }

            // Ekstrak single file
            $stream = $zip->getStream($entryName);
            if (! $stream) {
                continue;
            }

            $ext = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));
            $safeFilename = Str::slug(pathinfo($entryName, PATHINFO_FILENAME)).'_'.Str::random(8).'.'.$ext;
            $destPath = $extractTargetDir.'/'.$safeFilename;
            $destStream = fopen($destPath, 'wb');
            if ($destStream) {
                stream_copy_to_stream($stream, $destStream);
                fclose($destStream);
                fclose($stream);

                $extractedFiles[] = [
                    'name' => basename($entryName),
                    'relative_path' => $entryName,
                    'size' => (int) ($stat['size'] ?? filesize($destPath)),
                    'temp_path' => $destPath,
                ];
            }
        }

        $zip->close();

        return $extractedFiles;
    }
}
