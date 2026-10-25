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
    public const MAX_FULL_WIDTH = 1200;

    public const MAX_FULL_HEIGHT = 1200;

    public const FULL_QUALITY = 82;

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

        $srcImage = $this->loadAnyImageToGd($realPath);
        if (! $srcImage) {
            throw new \RuntimeException('Gagal memuat gambar: format tidak dikenali atau file rusak');
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
        $absPathFull = $storageDisk->path($relPathFull);
        $absPathThumb = $storageDisk->path($relPathThumb);

        // Pastikan direktori tujuan tersedia dan dapat ditulis
        $targetDir = dirname($absPathFull);
        if (! is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        // 1. Generate Full Size Image (Max 800x800)
        $fullImage = $this->resizeProporsional($srcImage, $origWidth, $origHeight, self::MAX_FULL_WIDTH, self::MAX_FULL_HEIGHT);
        $savedFull = @imagewebp($fullImage, $absPathFull, self::FULL_QUALITY);
        imagedestroy($fullImage);

        // 2. Generate Thumbnail Image (Max 250x250)
        $thumbImage = $this->resizeProporsional($srcImage, $origWidth, $origHeight, self::MAX_THUMB_WIDTH, self::MAX_THUMB_HEIGHT);
        $savedThumb = @imagewebp($thumbImage, $absPathThumb, self::THUMB_QUALITY);
        imagedestroy($thumbImage);

        imagedestroy($srcImage);

        if (! $savedFull || ! file_exists($absPathFull) || filesize($absPathFull) === 0) {
            throw new \RuntimeException('Gagal menyimpan file gambar WebP ke storage');
        }

        if (! $savedThumb || ! file_exists($absPathThumb) || filesize($absPathThumb) === 0) {
            throw new \RuntimeException('Gagal menyimpan thumbnail WebP ke storage');
        }

        $fullSize = filesize($absPathFull);
        $thumbSize = filesize($absPathThumb);

        return [
            'url' => '/storage/'.$relPathFull,
            'thumb' => '/storage/'.$relPathThumb,
            'path' => $relPathFull,
            'thumb_path' => $relPathThumb,
            'size' => (int) $fullSize,
            'thumb_size' => (int) $thumbSize,
            'is_primary' => false,
        ];
    }

    /**
     * Memuat file gambar dari berbagai format (JPEG, PNG, WebP, GIF, BMP, AVIF, HEIC, TIFF, PSD, SVG, dll.)
     * menjadi resource GD Image yang siap diproses dan dikompresi ke WebP.
     *
     * @return \GdImage|null
     */
    public function loadAnyImageToGd(string $realPath)
    {
        $imageInfo = @getimagesize($realPath);
        $mime = $imageInfo ? ($imageInfo['mime'] ?? null) : null;
        $srcImage = null;

        // 1. Coba loader GD spesifik sesuai MIME
        if ($mime) {
            $srcImage = match ($mime) {
                'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($realPath),
                'image/png' => @imagecreatefrompng($realPath),
                'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($realPath) : null,
                'image/gif' => @imagecreatefromgif($realPath),
                'image/bmp', 'image/x-ms-bmp' => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($realPath) : null,
                'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($realPath) : null,
                default => null,
            };
        }

        // 2. Coba loader biner otomatis GD imagecreatefromstring
        if (! $srcImage) {
            $rawBytes = @file_get_contents($realPath);
            if ($rawBytes !== false && $rawBytes !== '') {
                $srcImage = @imagecreatefromstring($rawBytes);
            }
        }

        // 3. Jika belum berhasil dan Imagick tersedia (mendukung HEIC, TIFF, PSD, SVG, RAW dll.)
        if (! $srcImage && class_exists(\Imagick::class)) {
            try {
                $im = new \Imagick;
                $im->readImage($realPath);
                $im->autoOrient();
                $im->setImageFormat('png');
                $pngBlob = $im->getImageBlob();
                $im->clear();
                $im->destroy();

                if ($pngBlob !== '') {
                    $srcImage = @imagecreatefromstring($pngBlob);
                }
            } catch (\Throwable $e) {
                // Fallback graceful
            }
        }

        // 4. Auto-orientasi EXIF jika dimuat via GD JPEG
        if ($srcImage && ($mime === 'image/jpeg' || $mime === 'image/jpg') && function_exists('exif_read_data')) {
            $exif = @exif_read_data($realPath);
            $orientation = $exif['Orientation'] ?? null;
            if ($orientation) {
                $rotated = match ((int) $orientation) {
                    3 => imagerotate($srcImage, 180, 0),
                    6 => imagerotate($srcImage, -90, 0),
                    8 => imagerotate($srcImage, 90, 0),
                    default => null,
                };
                if ($rotated) {
                    imagedestroy($srcImage);
                    $srcImage = $rotated;
                }
            }
        }

        return $srcImage ?: null;
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
