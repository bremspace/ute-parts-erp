<?php

namespace Tests\Unit;

use App\Modules\Crm\Services\PelangganService;
use App\Support\NomorTeleponIndonesia;
use App\Support\WhatsAppHelper;
use Tests\TestCase;

class NomorTeleponIndonesiaTest extends TestCase
{
    public function test_semua_format_dinormalisasi_ke_62(): void
    {
        $this->assertSame('6281234567890', NomorTeleponIndonesia::standar('081234567890'));
        $this->assertSame('6281234567890', NomorTeleponIndonesia::standar('81234567890'));
        $this->assertSame('6281234567890', NomorTeleponIndonesia::standar('+62 812-3456-7890'));
        $this->assertSame('6281234567890', NomorTeleponIndonesia::standar('62.812.3456.7890'));
        $this->assertSame('62815', NomorTeleponIndonesia::standar('0815'));
        $this->assertSame('', NomorTeleponIndonesia::standar(''));
    }

    public function test_kandidat_tetap_mencakup_format_lama(): void
    {
        $kandidat = NomorTeleponIndonesia::kandidat('081234567890');

        $this->assertSame('6281234567890', $kandidat[0]);
        $this->assertContains('081234567890', $kandidat);
        $this->assertContains('81234567890', $kandidat);
        $this->assertSame([], NomorTeleponIndonesia::kandidat('budi_parts'));
    }

    public function test_pelanggan_service_memakai_normalisasi_62(): void
    {
        $service = app(PelangganService::class);

        $this->assertSame('6281234567890', $service->normalizePhone('0812-3456-7890'));
        $this->assertSame('6281234567890', $service->normalizePhone('+62 812 3456 7890'));
        $this->assertSame('62815', $service->normalizePhone('0815'));
    }

    public function test_whatsapp_helper_tetap_memakai_62(): void
    {
        $this->assertSame('6281234567890', WhatsAppHelper::formatNomor('081234567890'));
        $this->assertSame('6281234567890', WhatsAppHelper::formatNomor('+62 812-3456-7890'));
    }
}
