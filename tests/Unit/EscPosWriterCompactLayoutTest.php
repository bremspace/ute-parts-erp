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
            'saldo_poin' => 0,
        ]);

        // Alamat + telepon digabung dalam satu baris.
        $this->assertStringContainsString('Jl. Veteran No. 12 - Telp/WA: 0812-3456', $output);

        // QR memakai perintah native (printer lapangan tidak stabil dengan bit-image).
        $this->assertStringContainsString("\x1d\x28\x6b\x04\x00\x31\x41\x32\x00", $output);
        $this->assertStringContainsString("\x1d\x28\x6b\x03\x00\x31\x43\x04", $output);
        $this->assertStringNotContainsString("\x1b\x2a\x00", $output);
        // Label No. TRX dihapus, cukup nomor transaksi saja.
        $this->assertStringNotContainsString('No. TRX:', $output);
        $this->assertStringContainsString('MP-C01-20261124-0001', $output);

        // Web + tanggal digabung; caption QR dan saldo poin 0 tidak dicetak.
        $this->assertStringContainsString('Web: www.bremspace.com | Tgl: 24-11-2026', $output);
        $this->assertStringNotContainsString('Scan QR ini untuk retur & klaim garansi', $output);
        $this->assertStringNotContainsString('Sisa Saldo Poin:', $output);

        // TOTAL satu baris, tanpa sizeDoubleHeight.
        $this->assertStringNotContainsString("\x1d\x21\x10TOTAL:", $output);
        $this->assertMatchesRegularExpression('/TOTAL: +Rp 13\.000/', $output);

        // Footer padat masih terbawa, tanpa feed kosong sebelum cut.
        $this->assertStringContainsString('Terima kasih atas kunjungan Anda!', $output);
        $this->assertStringContainsString('BAYAR (MENUNGGU):', $output);
        $this->assertStringNotContainsString("\n\n\x1d\x56\x42\x00", $output);
    }
}
