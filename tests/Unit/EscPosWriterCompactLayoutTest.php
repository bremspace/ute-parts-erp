<?php

namespace Tests\Unit;

use App\Modules\Pos\Services\EscPosWriter;
use Tests\TestCase;

class EscPosWriterCompactLayoutTest extends TestCase
{
    public function test_qr_size_lama_dinormalisasi_ke_modul_empat(): void
    {
        $this->assertSame(4, EscPosWriter::normalizeQrSize(80));
        $this->assertSame(4, EscPosWriter::normalizeQrSize('60'));
        $this->assertSame(3, EscPosWriter::normalizeQrSize(3));
        $this->assertSame(2, EscPosWriter::normalizeQrSize(1));
        $this->assertSame(4, EscPosWriter::normalizeQrSize(null));
    }

    public function test_struk_memakai_layout_padat_opsi_b(): void
    {
        $writer = new EscPosWriter;

        $output = $writer->receipt([
            'no_transaksi' => 'MP-C01-20261124-0001',
            'alamat' => 'Jl. Veteran No. 12',
            'telepon' => '0812-3456',
            'website_medsos' => 'www.bremspace.com',
            'tanggal' => '24-11-2026',
            'qr_caption' => 'Scan QR ini untuk retur & klaim garansi',
            'footer_text' => 'Terima kasih atas kunjungan Anda!',
            'qr_size' => 80, // nilai lama dari DB production
            'font_size' => 'small', // 42 kolom, sesuai mockup Opsi B
            'items' => [
                [
                    'nama' => 'ALAT PEMBUKA KAISI K-3331',
                    'qty' => 1,
                    'harga' => 13000,
                    'subtotal' => 13000,
                ],
            ],
            'subtotal' => 13000,
            'total' => 13000,
            'bayar' => 13000,
            'kembali' => 0,
            'metode_bayar' => 'MENUNGGU',
        ]);

        // Alamat + telepon digabung dalam satu baris.
        $this->assertStringContainsString('Jl. Veteran No. 12 - Telp/WA: 0812-3456', $output);

        // QR memakai module size 4 (bukan 8 dari nilai lama 80).
        $this->assertStringContainsString("\x1d\x28\x6b\x03\x00\x31\x43\x04", $output);

        // Web + tanggal digabung, caption QR ikut baris footer (bukan blok besar di header).
        $this->assertStringContainsString('Web: www.bremspace.com | Tgl: 24-11-2026', $output);
        $this->assertStringContainsString("Terima kasih atas kunjungan Anda!\nScan QR ini untuk retur & klaim garansi", $output);
        $this->assertStringNotContainsString("\x1b\x61\x01Scan QR ini untuk retur & klaim garansi\n", $output);

        // Footer padat masih terbawa.
        $this->assertStringContainsString('Terima kasih atas kunjungan Anda!', $output);
        $this->assertStringContainsString('BAYAR (MENUNGGU):', $output);
    }
}
