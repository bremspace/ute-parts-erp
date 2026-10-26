<?php

namespace Tests\Unit;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Crm\Services\PartaiPricingService;
use Mockery;
use PHPUnit\Framework\TestCase;

class PartaiPricingServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_inactive_partai_pricing_returns_zero_discount(): void
    {
        $mockConfig = Mockery::mock(KonfigurasiService::class);
        $mockConfig->shouldReceive('hargaPartaiMinQty')->andReturn(10);
        $mockConfig->shouldReceive('hargaPartaiMinNominal')->andReturn(1000000.0);
        $mockConfig->shouldReceive('hargaPartaiAktif')->andReturn(false);

        $service = new PartaiPricingService($mockConfig);
        $items = [
            ['harga' => 100000, 'qty' => 15, 'subtotal' => 1500000],
        ];

        $res = $service->hitung($items, null);

        $this->assertFalse($res['aktif']);
        $this->assertEquals(0.0, $res['diskon_nominal']);
        $this->assertEquals(0.0, $res['persen']);
    }

    public function test_applies_qty_discount_when_min_qty_reached(): void
    {
        $mockConfig = Mockery::mock(KonfigurasiService::class);
        $mockConfig->shouldReceive('hargaPartaiMinQty')->andReturn(10);
        $mockConfig->shouldReceive('hargaPartaiMinNominal')->andReturn(1000000.0);
        $mockConfig->shouldReceive('hargaPartaiAktif')->andReturn(true);
        $mockConfig->shouldReceive('hargaPartaiBerlakuUntuk')->andReturn('semua');
        $mockConfig->shouldReceive('hargaPartaiQtyDiskonPersen')->andReturn(5.0);
        $mockConfig->shouldReceive('hargaPartaiNominalDiskonPersen')->andReturn(0.0);

        $service = new PartaiPricingService($mockConfig);
        $items = [
            ['harga' => 50000, 'qty' => 10, 'subtotal' => 500000],
        ];

        $res = $service->hitung($items, null);

        $this->assertTrue($res['aktif']);
        $this->assertEquals(5.0, $res['persen']);
        $this->assertEquals(25000.0, $res['diskon_nominal']); // 5% of 500,000
        $this->assertStringContainsString('Qty >= 10', $res['alasan']);
    }

    public function test_applies_nominal_discount_when_min_nominal_reached(): void
    {
        $mockConfig = Mockery::mock(KonfigurasiService::class);
        $mockConfig->shouldReceive('hargaPartaiMinQty')->andReturn(10);
        $mockConfig->shouldReceive('hargaPartaiMinNominal')->andReturn(1000000.0);
        $mockConfig->shouldReceive('hargaPartaiAktif')->andReturn(true);
        $mockConfig->shouldReceive('hargaPartaiBerlakuUntuk')->andReturn('semua');
        $mockConfig->shouldReceive('hargaPartaiQtyDiskonPersen')->andReturn(3.0);
        $mockConfig->shouldReceive('hargaPartaiNominalDiskonPersen')->andReturn(7.0);

        $service = new PartaiPricingService($mockConfig);
        $items = [
            ['harga' => 600000, 'qty' => 2, 'subtotal' => 1200000], // qty < 10, but nominal >= 1,000,000
        ];

        $res = $service->hitung($items, null);

        $this->assertTrue($res['aktif']);
        $this->assertEquals(7.0, $res['persen']);
        $this->assertEquals(84000.0, $res['diskon_nominal']); // 7% of 1,200,000
        $this->assertStringContainsString('Belanja >= Rp 1.000.000', $res['alasan']);
    }

    public function test_chooses_higher_discount_and_appends_reason_when_both_conditions_met(): void
    {
        $mockConfig = Mockery::mock(KonfigurasiService::class);
        $mockConfig->shouldReceive('hargaPartaiMinQty')->andReturn(10);
        $mockConfig->shouldReceive('hargaPartaiMinNominal')->andReturn(1000000.0);
        $mockConfig->shouldReceive('hargaPartaiAktif')->andReturn(true);
        $mockConfig->shouldReceive('hargaPartaiBerlakuUntuk')->andReturn('semua');
        $mockConfig->shouldReceive('hargaPartaiQtyDiskonPersen')->andReturn(5.0);
        $mockConfig->shouldReceive('hargaPartaiNominalDiskonPersen')->andReturn(2.0);

        $service = new PartaiPricingService($mockConfig);
        $items = [
            ['harga' => 100000, 'qty' => 12, 'subtotal' => 1200000], // qty >= 10 and nominal >= 1,000,000
        ];

        $res = $service->hitung($items, null);

        $this->assertTrue($res['aktif']);
        $this->assertEquals(5.0, $res['persen']);
        $this->assertEquals(60000.0, $res['diskon_nominal']); // 5% of 1,200,000
        $this->assertStringContainsString('+ Belanja Besar', $res['alasan']);
    }

    public function test_respects_member_only_restriction(): void
    {
        $mockConfig = Mockery::mock(KonfigurasiService::class);
        $mockConfig->shouldReceive('hargaPartaiMinQty')->andReturn(5);
        $mockConfig->shouldReceive('hargaPartaiMinNominal')->andReturn(500000.0);
        $mockConfig->shouldReceive('hargaPartaiAktif')->andReturn(true);
        $mockConfig->shouldReceive('hargaPartaiBerlakuUntuk')->andReturn('member');
        $mockConfig->shouldReceive('hargaPartaiQtyDiskonPersen')->andReturn(4.0);
        $mockConfig->shouldReceive('hargaPartaiNominalDiskonPersen')->andReturn(0.0);

        $service = new PartaiPricingService($mockConfig);
        $items = [
            ['harga' => 100000, 'qty' => 10, 'subtotal' => 1000000],
        ];

        // Guest / non-member
        $resGuest = $service->hitung($items, null);
        $this->assertFalse($resGuest['aktif']);
        $this->assertEquals(0.0, $resGuest['diskon_nominal']);

        // Registered member
        $pelanggan = new Pelanggan;
        $pelanggan->id = 1;
        $pelanggan->nama = 'Member Ute';

        $resMember = $service->hitung($items, $pelanggan);
        $this->assertTrue($resMember['aktif']);
        $this->assertEquals(4.0, $resMember['persen']);
        $this->assertEquals(40000.0, $resMember['diskon_nominal']);
    }

    public function test_skips_direct_discount_for_accumulated_commission_reward_mode(): void
    {
        $mockConfig = Mockery::mock(KonfigurasiService::class);
        $mockConfig->shouldReceive('hargaPartaiMinQty')->andReturn(5);
        $mockConfig->shouldReceive('hargaPartaiMinNominal')->andReturn(500000.0);
        $mockConfig->shouldReceive('hargaPartaiAktif')->andReturn(true);

        $service = new PartaiPricingService($mockConfig);
        $items = [
            ['harga' => 100000, 'qty' => 10, 'subtotal' => 1000000],
        ];

        $res = $service->hitung($items, null, 'komisi_akumulasi');
        $this->assertFalse($res['aktif']);
        $this->assertEquals(0.0, $res['diskon_nominal']);
    }
}
