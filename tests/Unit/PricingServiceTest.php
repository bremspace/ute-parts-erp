<?php

namespace Tests\Unit;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Pos\Services\PricingService;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use PHPUnit\Framework\TestCase;

class PricingServiceTest extends TestCase
{
    protected PricingService $pricingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricingService = new PricingService;
    }

    public function test_resolves_retail_default_when_no_customer_provided(): void
    {
        $produk = new Produk;
        $produk->id = 1;
        $produk->harga_jual_retail = 100000;

        $res = $this->pricingService->resolve($produk, null);

        $this->assertEquals(100000, $res['harga']);
        $this->assertEquals(0, $res['diskon_nominal']);
        $this->assertEquals('Harga Retail Standar', $res['alasan']);
        $this->assertNull($res['tier']);
    }

    public function test_resolves_tier_percentage_discount(): void
    {
        $produk = new Produk;
        $produk->id = 1;
        $produk->harga_jual_retail = 100000;

        $tier = new TierMembership;
        $tier->id = 10;
        $tier->nama = 'Gold';
        $tier->diskon_persen = 10.0;

        $pelanggan = new Pelanggan;
        $pelanggan->is_reseller = false;
        $pelanggan->tier_membership_id = 10;
        $pelanggan->setRelation('tierMembership', $tier);

        $res = $this->pricingService->resolve($produk, $pelanggan);

        $this->assertEquals(90000, $res['harga']);
        $this->assertEquals(10000, $res['diskon_nominal']);
        $this->assertEquals('Gold', $res['tier']);
    }

    public function test_variant_price_overrides_base_product_price(): void
    {
        $produk = new Produk;
        $produk->id = 1;
        $produk->harga_jual_retail = 100000;

        $variant = new SkuVariant;
        $variant->id = 5;
        $variant->harga_jual_retail = 120000;

        $res = $this->pricingService->resolve($produk, null, $variant);

        $this->assertEquals(120000, $res['harga']);
        $this->assertEquals(120000, $res['harga_dasar']);
    }
}
