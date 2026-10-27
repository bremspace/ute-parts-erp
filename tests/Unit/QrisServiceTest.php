<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Pos\Services\QrisService;
use App\Modules\Wms\Services\QrCodeService;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use PHPUnit\Framework\TestCase;

class QrisServiceTest extends TestCase
{
    private QrisService $service;

    // Contoh QRIS Statis standar Bank Indonesia / ASPI
    private string $sampleStaticQris = '00020101021126610016ID.CO.QRIS.WWW01189360000201100123450210G1234567890303UMI51530014ID.LINKAJA.WWW011893600999001122334402091234567895204541153033605802ID5915UTE PARTS STORE6007JAKARTA61051234563047686';

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new QrisService(new QrCodeService);
    }

    public function test_crc16_calculation(): void
    {
        // Standar test vector CRC16-CCITT False untuk '123456789' adalah 0x29B1
        $crc = QrisService::crc16('123456789');
        $this->assertSame('29B1', $crc);
    }

    public function test_parse_tlv(): void
    {
        $tags = $this->service->parseTlv($this->sampleStaticQris);

        $this->assertSame('01', $tags['00']);
        $this->assertSame('11', $tags['01']);
        $this->assertSame('5411', $tags['52']);
        $this->assertSame('360', $tags['53']);
        $this->assertSame('ID', $tags['58']);
        $this->assertSame('UTE PARTS STORE', $tags['59']);
        $this->assertSame('JAKARTA', $tags['60']);
        $this->assertSame('12345', $tags['61']);
    }

    public function test_extract_metadata(): void
    {
        $meta = $this->service->extractMetadata($this->sampleStaticQris);

        $this->assertTrue($meta['valid']);
        $this->assertSame('UTE PARTS STORE', $meta['merchant_name']);
        $this->assertSame('JAKARTA', $meta['merchant_city']);
        $this->assertSame('12345', $meta['postal_code']);
        $this->assertSame('360', $meta['currency']);
        $this->assertSame('ID', $meta['country']);
    }

    public function test_generate_dynamic_qris(): void
    {
        $nominal = 75000;
        $invoice = 'TRX-20261027-001';

        $dynamicPayload = $this->service->generateDynamic($this->sampleStaticQris, $nominal, $invoice);

        $tags = $this->service->parseTlv($dynamicPayload);

        // Tag 01 harus menjadi 12 (Dynamic)
        $this->assertSame('12', $tags['01']);

        // Tag 54 harus memiliki nominal 75000
        $this->assertSame('75000', $tags['54']);

        // Tag 63 harus ada dan CRC16 valid
        $this->assertArrayHasKey('63', $tags);
        $payloadWithoutCrc = substr($dynamicPayload, 0, -4);
        $expectedCrc = QrisService::crc16($payloadWithoutCrc);
        $this->assertSame($expectedCrc, $tags['63']);
    }

    public function test_render_svg(): void
    {
        $dynamicPayload = $this->service->generateDynamic($this->sampleStaticQris, 50000);
        $svg = $this->service->renderSvg($dynamicPayload, 200);

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
    }

    public function test_decode_from_image(): void
    {
        $renderer = new GDLibRenderer(300);
        $writer = new Writer($renderer);
        $imgBinary = $writer->writeString($this->sampleStaticQris);

        $tempPath = sys_get_temp_dir().'/test_unit_qris_'.uniqid().'.png';
        file_put_contents($tempPath, $imgBinary);

        try {
            $decoded = $this->service->decodeFromImage($tempPath);
            $this->assertSame($this->sampleStaticQris, $decoded);
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    public function test_decode_from_rotated_image(): void
    {
        $renderer = new GDLibRenderer(300);
        $writer = new Writer($renderer);
        $imgBinary = $writer->writeString($this->sampleStaticQris);

        $img = imagecreatefromstring($imgBinary);
        $rotated = imagerotate($img, 90, 0);

        $tempPath = sys_get_temp_dir().'/test_unit_qris_rot_'.uniqid().'.png';
        imagepng($rotated, $tempPath);
        imagedestroy($img);
        imagedestroy($rotated);

        try {
            $decoded = $this->service->decodeFromImage($tempPath);
            $this->assertSame($this->sampleStaticQris, $decoded);
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }
}
