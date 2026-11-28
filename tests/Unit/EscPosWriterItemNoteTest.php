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

        // Opsi B: seluruh baris Harga Retail + nominal dicoret via raster GS v 0.
        $this->assertStringContainsString("\x1d\x76\x30\x00", $output);
        $this->assertStringContainsString('LCD+TS VIVO Y20 BLACK OG SUPER', $output);
        $this->assertStringContainsString('TOTAL: Rp 110.000', $output);
        $this->assertStringNotContainsString('Subtotal:', $output);
        $this->assertStringContainsString('BAYAR (TUNAI):', $output);
        $this->assertStringContainsString('Garansi: 3 Hari Toko s/d 16/11', $output);
        $this->assertStringNotContainsString('2026)', $output);
    }

    public function test_escpos_writer_menampilkan_harga_satuan_setelah_diskon_partai(): void
    {
        $writer = new EscPosWriter;

        $output = $writer->receipt([
            'no_transaksi' => 'TRX-PARTAI-001',
            'items' => [
                [
                    'nama' => 'LCD REDMI 9A',
                    'qty' => 10,
                    'satuan' => 'PCS',
                    'harga' => 106000,
                    'subtotal' => 1060000,
                ],
            ],
            'subtotal' => 1284000,
            'diskon_partai' => 38520,
            'total' => 1245480,
            'bayar' => 1245480,
        ]);

        // Fallback: 38.520 / 1.284.000 = 3% sesuai settingan partai.
        // Faktor partai = 0,97 → harga 106.000 jadi 102.820.
        $this->assertStringContainsString('Harga Partai/PCS: Rp 102.820', $output);
        $this->assertMatchesRegularExpression('/Diskon Partai: 3% +-Rp 38\.520/', $output);
        $this->assertStringContainsString('-Rp 38.520', $output);
    }

    public function test_persen_diskon_partai_memakai_nilai_settingan_bila_tersedia(): void
    {
        $writer = new EscPosWriter;

        $output = $writer->receipt([
            'no_transaksi' => 'TRX-PARTAI-002',
            'items' => [
                ['nama' => 'ITEM', 'qty' => 1, 'satuan' => 'PCS', 'harga' => 100000, 'subtotal' => 100000],
            ],
            'subtotal' => 100000,
            'diskon_partai' => 3500,
            'diskon_partai_persen' => 3.5,
            'total' => 96500,
            'bayar' => 96500,
        ]);

        $this->assertMatchesRegularExpression('/Diskon Partai: 3\.5% +-Rp 3\.500/', $output);
    }
}
