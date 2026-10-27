<?php

declare(strict_types=1);

namespace App\Modules\Pos\Services;

use App\Modules\Pos\Models\QrisCabang;
use App\Modules\Wms\Services\QrCodeService;
use InvalidArgumentException;
use Zxing\QrReader;

/**
 * Service untuk parsing, validasi, dan generate QRIS Dinamis standar EMVCo / ASPI.
 */
class QrisService
{
    public function __construct(
        protected QrCodeService $qrCodeService
    ) {}

    /**
     * Hitung CRC16-CCITT (False: poly 0x1021, init 0xFFFF).
     */
    public static function crc16(string $data): string
    {
        $crc = 0xFFFF;
        $length = strlen($data);

        for ($i = 0; $i < $length; $i++) {
            $crc ^= (ord($data[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                if (($crc & 0x8000) !== 0) {
                    $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }

        return sprintf('%04X', $crc);
    }

    /**
     * Parse raw string EMVCo menjadi array TLV (Tag-Length-Value).
     *
     * @return array<string, string>
     */
    public function parseTlv(string $payload): array
    {
        $payload = trim($payload);
        $length = strlen($payload);
        $offset = 0;
        $tags = [];

        while ($offset < $length) {
            if ($offset + 4 > $length) {
                break;
            }

            $tag = substr($payload, $offset, 2);
            $valLen = (int) substr($payload, $offset + 2, 2);
            $offset += 4;

            if ($offset + $valLen > $length) {
                break;
            }

            $val = substr($payload, $offset, $valLen);
            $tags[$tag] = $val;
            $offset += $valLen;
        }

        return $tags;
    }

    /**
     * Ekstrak metadata penting dari static QRIS.
     *
     * @return array{
     *     valid: bool,
     *     merchant_name: string|null,
     *     merchant_city: string|null,
     *     postal_code: string|null,
     *     nmid: string|null,
     *     currency: string|null,
     *     country: string|null,
     *     error: string|null
     * }
     */
    public function extractMetadata(string $payload): array
    {
        $payload = trim($payload);
        $tags = $this->parseTlv($payload);

        if (($tags['00'] ?? '') !== '01') {
            return [
                'valid' => false,
                'merchant_name' => null,
                'merchant_city' => null,
                'postal_code' => null,
                'nmid' => null,
                'currency' => null,
                'country' => null,
                'error' => 'Format QRIS tidak valid: Tag 00 bukan 01.',
            ];
        }

        if (($tags['58'] ?? '') !== 'ID') {
            return [
                'valid' => false,
                'merchant_name' => null,
                'merchant_city' => null,
                'postal_code' => null,
                'nmid' => null,
                'currency' => null,
                'country' => null,
                'error' => 'QRIS bukan standar Indonesia (Tag 58 bukan ID).',
            ];
        }

        // Cari NMID pada Tag 26-51 (Merchant Account Information)
        $nmid = null;
        for ($i = 26; $i <= 51; $i++) {
            $tagKey = sprintf('%02d', $i);
            if (isset($tags[$tagKey])) {
                $subTags = $this->parseTlv($tags[$tagKey]);
                // Sub-tag 02 atau 03 biasanya menyimpan NMID (ID10...)
                foreach (['02', '03', '01'] as $subKey) {
                    if (isset($subTags[$subKey]) && str_starts_with(strtoupper($subTags[$subKey]), 'ID')) {
                        $nmid = $subTags[$subKey];
                        break 2;
                    }
                }
            }
        }

        return [
            'valid' => true,
            'merchant_name' => $tags['59'] ?? null,
            'merchant_city' => $tags['60'] ?? null,
            'postal_code' => $tags['61'] ?? null,
            'nmid' => $nmid,
            'currency' => $tags['53'] ?? '360',
            'country' => $tags['58'] ?? 'ID',
            'error' => null,
        ];
    }

    /**
     * Konversi Static QRIS payload menjadi Dynamic QRIS dengan nominal tertentu.
     */
    public function generateDynamic(string $staticPayload, float|int $nominal, ?string $invoice = null): string
    {
        if ($nominal <= 0) {
            throw new InvalidArgumentException('Nominal pembayaran QRIS harus lebih besar dari 0.');
        }

        $tags = $this->parseTlv($staticPayload);

        if (($tags['00'] ?? '') !== '01') {
            throw new InvalidArgumentException('Format QRIS tidak valid.');
        }

        // 1. Ubah Point of Initiation Method menjadi 12 (Dynamic)
        $tags['01'] = '12';

        // 2. Set Mata Uang IDR (360)
        $tags['53'] = '360';

        // 3. Set Nominal Transaksi (Tag 54)
        $amountStr = (string) (int) round($nominal);
        $tags['54'] = $amountStr;

        // 4. Set Invoice / Reference di Tag 62 jika ada
        if (! empty($invoice)) {
            $invoiceClean = preg_replace('/[^A-Za-z0-9\-_]/', '', $invoice);
            $invoiceSubtag = '01'.sprintf('%02d', strlen($invoiceClean)).$invoiceClean;
            $tags['62'] = $invoiceSubtag;
        }

        // Hapus Tag 63 lama
        unset($tags['63']);

        // 5. Susun ulang urutan tags sesuai standar EMVCo
        // Tag urutan penting: 00, 01, 02..51, 52, 53, 54, 55, 56, 57, 58, 59, 60, 61, 62
        uksort($tags, function ($a, $b) {
            return (int) $a <=> (int) $b;
        });

        $serialized = '';
        foreach ($tags as $tag => $val) {
            $serialized .= sprintf('%02s%02d%s', $tag, strlen((string) $val), (string) $val);
        }

        // 6. Tambahkan prefix Tag 63 ('6304') dan hitung CRC16
        $dataForCrc = $serialized.'6304';
        $crc = self::crc16($dataForCrc);

        return $dataForCrc.$crc;
    }

    /**
     * Render SVG string untuk QR Code dinamis.
     */
    public function renderSvg(string $dynamicPayload, int $size = 220): string
    {
        return $this->qrCodeService->generateSvg($dynamicPayload, $size, 1);
    }

    /**
     * Ambil data QRIS aktif untuk cabang tertentu, dengan fallback ke QRIS global (cabang_id null).
     */
    public function getActiveQris(?int $cabangId = null): ?QrisCabang
    {
        if ($cabangId) {
            $qris = QrisCabang::query()
                ->where('cabang_id', $cabangId)
                ->where('is_active', true)
                ->first();

            if ($qris) {
                return $qris;
            }
        }

        return QrisCabang::query()
            ->whereNull('cabang_id')
            ->where('is_active', true)
            ->first();
    }

    /**
     * Format pesan WhatsApp berisi instruksi & rincian pembayaran QRIS.
     */
    public function formatWhatsAppMessage(
        string $storeName,
        string $invoice,
        float|int $total,
        string $customerName,
        ?string $qrUrl = null
    ): string {
        $formattedNominal = number_format($total, 0, ',', '.');

        $msg = "*Tagihan Pembayaran QRIS - {$storeName}*\n";
        $msg .= "Halo *{$customerName}*,\n\n";
        $msg .= "Berikut adalah rincian tagihan belanja Anda:\n";
        $msg .= "• No. Transaksi: *{$invoice}*\n";
        $msg .= "• Total Tagihan: *Rp {$formattedNominal}*\n\n";
        $msg .= "Silakan scan kode QRIS yang tampil di layar kasir atau buka tautan berikut untuk scan:\n";
        if ($qrUrl) {
            $msg .= "{$qrUrl}\n\n";
        }
        $msg .= "Pastikan nominal pembayaran sesuai (*Rp {$formattedNominal}*). Simpan bukti transaksi setelah berhasil.\n\n";
        $msg .= "Terima kasih telah berbelanja di *{$storeName}*!";

        return $msg;
    }

    /**
     * Decode kode QR dari file gambar secara multi-pass (server-side decoder).
     */
    public function decodeFromImage(string $filePath): ?string
    {
        if (! file_exists($filePath)) {
            return null;
        }

        // 1. Scan langsung file asli
        try {
            $reader = new QrReader($filePath);
            $text = $reader->text();
            if (! empty($text) && is_string($text)) {
                return trim($text);
            }
        } catch (\Throwable $e) {
            // Lanjut ke manipulasi GD
        }

        // 2. Baca buffer gambar via GD
        $content = @file_get_contents($filePath);
        if (! $content) {
            return null;
        }

        $img = @imagecreatefromstring($content);
        if (! $img) {
            return null;
        }

        $w = imagesx($img);
        $h = imagesy($img);

        $testResource = function ($res): ?string {
            ob_start();
            imagepng($res);
            $data = ob_get_clean();
            try {
                $r = new QrReader($data, QrReader::SOURCE_TYPE_BLOB);
                $t = $r->text();
                if (! empty($t) && is_string($t)) {
                    return trim($t);
                }
            } catch (\Throwable $e) {
            }

            return null;
        };

        // 3. Coba rotasi (foto HP potret sering terbalik EXIF)
        foreach ([90, 180, 270] as $angle) {
            $rot = imagerotate($img, $angle, 0);
            if ($res = $testResource($rot)) {
                imagedestroy($rot);
                imagedestroy($img);

                return $res;
            }
            imagedestroy($rot);
        }

        // 4. Downscale jika resolusi tinggi kamera HP (> 1000px)
        if ($w > 1000 || $h > 1000) {
            foreach ([1000, 750, 500] as $maxDim) {
                $scale = min($maxDim / $w, $maxDim / $h);
                $nw = (int) round($w * $scale);
                $nh = (int) round($h * $scale);
                $scaled = imagecreatetruecolor($nw, $nh);
                imagecopyresampled($scaled, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                if ($res = $testResource($scaled)) {
                    imagedestroy($scaled);
                    imagedestroy($img);

                    return $res;
                }
                imagedestroy($scaled);
            }
        }

        // 5. Center crop 65% (Fokus area QR pada stand akrilik / meja kasir)
        $cw = (int) round($w * 0.65);
        $ch = (int) round($h * 0.65);
        $cx = (int) round(($w - $cw) / 2);
        $cy = (int) round(($h - $ch) / 2);
        $cropped = imagecreatetruecolor($cw, $ch);
        imagecopy($cropped, $img, 0, 0, $cx, $cy, $cw, $ch);
        if ($res = $testResource($cropped)) {
            imagedestroy($cropped);
            imagedestroy($img);

            return $res;
        }
        imagedestroy($cropped);

        // 6. Grayscale + kontras tinggi
        $gray = imagecreatetruecolor($w, $h);
        imagecopy($gray, $img, 0, 0, 0, 0, $w, $h);
        imagefilter($gray, IMG_FILTER_GRAYSCALE);
        imagefilter($gray, IMG_FILTER_CONTRAST, -30);
        if ($res = $testResource($gray)) {
            imagedestroy($gray);
            imagedestroy($img);

            return $res;
        }
        imagedestroy($gray);

        imagedestroy($img);

        return null;
    }
}
