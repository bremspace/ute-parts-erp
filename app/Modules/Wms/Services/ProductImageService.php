<?php

namespace App\Modules\Wms\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Service pemrosesan foto produk ultra-hemat storage & responsive UI.
 *
 * Mengonversi gambar ke WebP (kompresi 80% tanpa kehilangan ketajaman visual),
 * membuat thumbnail 250x250 untuk katalog & kasir, serta full image 800x800.
 */
class ProductImageService
{
    public const MAX_FULL_WIDTH = 800;

    public const MAX_FULL_HEIGHT = 800;

    public const FULL_QUALITY = 80;

    public const MAX_THUMB_WIDTH = 250;

    public const MAX_THUMB_HEIGHT = 250;

    public const THUMB_QUALITY = 75;

    /**
     * Proses file gambar mentah dan simpan dalam format WebP (full + thumbnail).
     *
     * @param  UploadedFile|string  $file  Objek UploadedFile atau path file lokal
     * @param  string  $disk  Disk storage (default 'public')
     * @return array{url: string, thumb: string, path: string, thumb_path: string, size: int, thumb_size: int, is_primary: bool}
     */
    public function prosesDanSimpan(UploadedFile|string $file, string $disk = 'public'): array
    {
        $realPath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        if (! file_exists($realPath) || ! is_readable($realPath)) {
            throw new \InvalidArgumentException('File gambar tidak ditemukan atau tidak dapat dibaca');
        }

        $imageInfo = @getimagesize($realPath);
        if ($imageInfo === false) {
            throw new \InvalidArgumentException('File bukan format gambar yang valid');
        }

        $mime = $imageInfo['mime'];
        $srcImage = match ($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($realPath),
            'image/png' => @imagecreatefrompng($realPath),
            'image/webp' => @imagecreatefromwebp($realPath),
            'image/gif' => @imagecreatefromgif($realPath),
            default => null,
        };

        if (! $srcImage) {
            throw new \RuntimeException('Gagal memuat gambar: format tidak didukung oleh GD');
        }

        $origWidth = imagesx($srcImage);
        $origHeight = imagesy($srcImage);

        // Subfolder berbasis tahun/bulan agar direktori tidak bottleneck
        $year = date('Y');
        $month = date('m');
        $random = Str::lower(Str::random(24));
        $folder = "produk/{$year}/{$month}";

        $relPathFull = "{$folder}/{$random}.webp";
        $relPathThumb = "{$folder}/{$random}_thumb.webp";

        $storageDisk = Storage::disk($disk);
        $storageDisk->makeDirectory($folder);

        $absPathFull = $storageDisk->path($relPathFull);
        $absPathThumb = $storageDisk->path($relPathThumb);

        // 1. Generate Full Size Image (Max 800x800)
        $fullImage = $this->resizeProporsional($srcImage, $origWidth, $origHeight, self::MAX_FULL_WIDTH, self::MAX_FULL_HEIGHT);
        imagewebp($fullImage, $absPathFull, self::FULL_QUALITY);
        imagedestroy($fullImage);

        // 2. Generate Thumbnail Image (Max 250x250)
        $thumbImage = $this->resizeProporsional($srcImage, $origWidth, $origHeight, self::MAX_THUMB_WIDTH, self::MAX_THUMB_HEIGHT);
        imagewebp($thumbImage, $absPathThumb, self::THUMB_QUALITY);
        imagedestroy($thumbImage);

        imagedestroy($srcImage);

        $fullSize = file_exists($absPathFull) ? filesize($absPathFull) : 0;
        $thumbSize = file_exists($absPathThumb) ? filesize($absPathThumb) : 0;

        return [
            'url' => Storage::disk($disk)->url($relPathFull),
            'thumb' => Storage::disk($disk)->url($relPathThumb),
            'path' => $relPathFull,
            'thumb_path' => $relPathThumb,
            'size' => (int) $fullSize,
            'thumb_size' => (int) $thumbSize,
            'is_primary' => false,
        ];
    }

    /**
     * Hapus file gambar dan thumbnail dari storage.
     */
    public function hapusFoto(string $urlOrPath, ?string $thumbUrlOrPath = null, string $disk = 'public'): void
    {
        $storage = Storage::disk($disk);

        $cleanPath = $this->extractRelativePath($urlOrPath);
        if ($cleanPath && $storage->exists($cleanPath)) {
            $storage->delete($cleanPath);
        }

        if ($thumbUrlOrPath) {
            $cleanThumb = $this->extractRelativePath($thumbUrlOrPath);
            if ($cleanThumb && $storage->exists($cleanThumb)) {
                $storage->delete($cleanThumb);
            }
        }
    }

    /**
     * Resize gambar dengan mempertahankan aspect ratio asli tanpa distorsi.
     *
     * @return \GdImage
     */
    protected function resizeProporsional($srcImage, int $origWidth, int $origHeight, int $maxW, int $maxH)
    {
        if ($origWidth <= $maxW && $origHeight <= $maxH) {
            $newW = $origWidth;
            $newH = $origHeight;
        } else {
            $ratio = min($maxW / $origWidth, $maxH / $origHeight);
            $newW = max(1, (int) round($origWidth * $ratio));
            $newH = max(1, (int) round($origHeight * $ratio));
        }

        $dest = imagecreatetruecolor($newW, $newH);

        // Pertahankan transparansi PNG / WebP jika ada
        imagealphablending($dest, false);
        imagesavealpha($dest, true);
        $transparent = imagecolorallocatealpha($dest, 255, 255, 255, 127);
        imagefilledrectangle($dest, 0, 0, $newW, $newH, $transparent);
        imagealphablending($dest, true);

        imagecopyresampled($dest, $srcImage, 0, 0, 0, 0, $newW, $newH, $origWidth, $origHeight);

        return $dest;
    }

    /**
     * Ubah URL publik (e.g. /storage/produk/...) menjadi storage relative path (produk/...).
     */
    protected function extractRelativePath(string $urlOrPath): ?string
    {
        $urlOrPath = parse_url($urlOrPath, PHP_URL_PATH) ?? $urlOrPath;
        if (str_starts_with($urlOrPath, '/storage/')) {
            return substr($urlOrPath, 9);
        }
        if (str_starts_with($urlOrPath, 'storage/')) {
            return substr($urlOrPath, 8);
        }

        return ltrim($urlOrPath, '/');
    }
}
