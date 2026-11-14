<?php

namespace Tests\Unit;

use App\Modules\Pos\Services\EscPosWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EscPosWriterItemNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_escpos_writer_mencetak_catatan_item_di_struk(): void
    {
        $writer = new EscPosWriter;

        $data = [
            'no_transaksi' => 'TRX-TEST-001',
            'tanggal' => '26-10-2026 12:00',
            'kasir' => 'Kasir 1',
            'cabang' => 'Cabang Pusat',
            'items' => [
                [
                    'nama' => 'LCD Touchscreen Xiaomi',
                    'qty' => 1,
                    'harga' => 350000,
                    'subtotal' => 350000,
                    'catatan' => 'Model: Redmi Note 10',
                ],
            ],
            'total' => 350000,
            'bayar' => 350000,
            'kembali' => 0,
            'metode_bayar' => 'TUNAI',
        ];

        $output = $writer->receipt($data);

        $this->assertStringContainsString('LCD Touchscreen Xiaomi', $output);
        $this->assertStringContainsString('Model: Redmi Note 10', $output);
    }

    public function test_escpos_writer_inline_strikethrough_discount(): void
    {
        $writer = new EscPosWriter;

        $data = [
            'no_transaksi' => 'TRX-C01-20261113-0016',
            'items' => [
                [
                    'nama' => 'LCD+TS VIVO Y20 BLACK OG SUPER',
                    'qty' => 1,
                    'harga' => 110000,
                    'harga_retail' => 125000,
                    'subtotal' => 110000,
                ],
            ],
            'total' => 110000,
            'bayar' => 110000,
            'kembali' => 0,
            'metode_bayar' => 'TUNAI',
            'garansi' => '3 Hari Toko (s/d 16/11/2026)',
        ];

        $output = $writer->receipt($data);

        // Inline strikethrough discount on same row
        $this->assertStringContainsString('1 PCS x (Rp 125.000) -> Rp 110.000', $output);
        $this->assertStringContainsString('Rp 110.000', $output);
        $this->assertStringContainsString('BAYAR (TUNAI):', $output);
        $this->assertStringContainsString('Garansi: 3 Hari Toko (s/d 16/11/2026)', $output);
    }
}
