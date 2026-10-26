<?php

namespace Tests\Unit;

use App\Modules\Wms\Services\ScanResolverService;
use PHPUnit\Framework\TestCase;

class ScanResolverServiceTest extends TestCase
{
    protected ScanResolverService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ScanResolverService;
    }

    public function test_clean_code_trims_and_removes_control_characters(): void
    {
        $raw = "  \x008991234567890\r\n  ";
        $cleaned = $this->service->cleanCode($raw);
        $this->assertEquals('8991234567890', $cleaned);
    }

    public function test_clean_code_parses_json_qr(): void
    {
        $json = json_encode(['sku' => 'SKU-IPHONE-11', 'name' => 'LCD']);
        $cleaned = $this->service->cleanCode($json);
        $this->assertEquals('SKU-IPHONE-11', $cleaned);
    }

    public function test_clean_code_parses_url_qr_with_query_param(): void
    {
        $url = 'https://uteparts.id/shop?barcode=899778899';
        $cleaned = $this->service->cleanCode($url);
        $this->assertEquals('899778899', $cleaned);
    }

    public function test_clean_code_parses_url_qr_path_slug(): void
    {
        $url = 'https://uteparts.id/shop/produk/lcd-iphone-11-original';
        $cleaned = $this->service->cleanCode($url);
        $this->assertEquals('lcd-iphone-11-original', $cleaned);
    }
}
