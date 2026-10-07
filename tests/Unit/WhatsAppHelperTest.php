<?php

namespace Tests\Unit;

use App\Support\WhatsAppHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppHelperTest extends TestCase
{
    use RefreshDatabase;

    public function test_format_nomor_indonesia(): void
    {
        $this->assertEquals('6281234567890', WhatsAppHelper::formatNomor('081234567890'));
        $this->assertEquals('6281234567890', WhatsAppHelper::formatNomor('+62 812-3456-7890'));
        $this->assertEquals('6281234567890', WhatsAppHelper::formatNomor('812-3456-7890'));
        $this->assertNull(WhatsAppHelper::formatNomor(''));
        $this->assertNull(WhatsAppHelper::formatNomor(null));
    }

    public function test_buat_link_wa(): void
    {
        $link = WhatsAppHelper::buatLink('081234567890', 'Halo Dunia!');
        $this->assertNotNull($link);
        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $link);
        $this->assertStringContainsString('Halo%20Dunia%21', $link);

        $this->assertNull(WhatsAppHelper::buatLink('', 'Pesan'));
        $this->assertNull(WhatsAppHelper::buatLink(null, 'Pesan'));
    }

    public function test_draft_struk_pos(): string
    {
        $draft = WhatsAppHelper::draftStrukPos([
            'no_transaksi' => 'TRX-2026-001',
            'pelanggan' => 'Andi',
            'total' => 150000,
            'metode' => 'TUNAI',
            'items' => [
                ['nama' => 'Baterai IP11', 'qty' => 1, 'subtotal' => 150000],
            ],
        ]);

        $this->assertStringContainsString('Andi', $draft);
        $this->assertStringContainsString('#TRX-2026-001', $draft);
        $this->assertStringContainsString('Baterai IP11', $draft);
        $this->assertStringContainsString('150.000', $draft);

        return $draft;
    }

    public function test_draft_servis(): void
    {
        $draftTerima = WhatsAppHelper::draftTerimaServis('Budi', 'SRV-001', 'Samsung A52', 'token123');
        $this->assertStringContainsString('SRV-001', $draftTerima);
        $this->assertStringContainsString('tracking/token123', $draftTerima);

        $draftEstimasi = WhatsAppHelper::draftEstimasiServis('Budi', 'SRV-001', 'Samsung A52', 500000, 'Ganti LCD', 'token123');
        $this->assertStringContainsString('500.000', $draftEstimasi);
        $this->assertStringContainsString('Ganti LCD', $draftEstimasi);

        $draftSelesai = WhatsAppHelper::draftServisSelesai('Budi', 'SRV-001', 'Samsung A52', 500000, 'token123');
        $this->assertStringContainsString('SELESAI', $draftSelesai);
    }
}
