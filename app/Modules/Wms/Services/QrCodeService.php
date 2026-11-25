<?php

declare(strict_types=1);

namespace App\Modules\Wms\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Throwable;

class QrCodeService
{
    /**
     * Generate vector SVG string untuk QR Code.
     * Output aman di-embed langsung ke dalam HTML template.
     */
    public function generateSvg(string $data, int $size = 120, int $margin = 0): string
    {
        try {
            $renderer = new ImageRenderer(
                new RendererStyle($size, $margin),
                new SvgImageBackEnd
            );

            $writer = new Writer($renderer);
            $svg = $writer->writeString($data);

            // Bersihkan xml tag agar inline SVG valid di Blade HTML5
            $svg = preg_replace('/<\?xml[^>]*\?>/i', '', $svg) ?? $svg;
            $svg = preg_replace('/<!DOCTYPE[^>]*>/i', '', $svg) ?? $svg;

            return trim($svg);
        } catch (Throwable $e) {
            // Fallback sederhana bila terjadi kegagalan
            return sprintf(
                '<svg width="%d" height="%d" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><rect width="100" height="100" fill="#f3f4f6"/><text x="50" y="55" font-size="10" text-anchor="middle" fill="#6b7280">QR Error</text></svg>',
                $size,
                $size
            );
        }
    }
}
