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

        // Ukuran efektif mengikuti ukuran QR yang dipilih (default modul 4).
        $this->assertSame(4, EscPosWriter::effectiveQrSize(80));
        $this->assertSame(4, EscPosWriter::effectiveQrSize(4));
        $this->assertSame(3, EscPosWriter::effectiveQrSize(3));
        $this->assertSame(2, EscPosWriter::effectiveQrSize(2));
    }

    public function test_struk_memakai_layout_padat_opsi_b(): void
    {
        $writer = new EscPosWriter;

        $output = $writer->receipt([
            'no_transaksi' => 'MP-C01-20261124-0001',
            'nama_toko' => 'UTEParts',
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

        // Header: nama toko besar (double width + height), alamat + nomor HP tanpa label.
        $this->assertStringContainsString("\x1b\x45\x01\x1d\x21\x90UTEParts", $output);
        $this->assertStringContainsString('Jl. Veteran No. 12 - 0812-3456', $output);
        $this->assertStringNotContainsString('Telp/WA:', $output);

        // QR native ukuran penuh (modul 4), ada jarak setelah QR.
        $this->assertStringContainsString("\x1d\x28\x6b\x04\x00\x31\x41\x32\x00", $output);
        $this->assertStringContainsString("\x1d\x28\x6b\x03\x00\x31\x43\x04", $output);
        $this->assertStringNotContainsString("\x1b\x2a\x00", $output);
        $this->assertStringContainsString('No. TRX:', $output);
        $this->assertStringContainsString('MP-C01-20261124-0001', $output);

        // Web/caption dihapus; tanggal dan saldo poin tetap ada.
        $this->assertStringNotContainsString('Web:', $output);
        $this->assertStringContainsString('Tgl: 24-11-2026', $output);
        $this->assertStringNotContainsString('Scan QR ini untuk retur & klaim garansi', $output);
        $this->assertStringContainsString('Sisa Saldo Poin:', $output);

        // TOTAL lebih besar (double width), bold, ringkas satu baris.
        $this->assertStringNotContainsString("\x1d\x21\x10TOTAL:", $output);
        $this->assertStringContainsString("\x1b\x45\x01\x1d\x21\x80TOTAL: Rp 13.000", $output);

        // Footer teks/caption tidak dicetak; yang ada hanya garansi bila tersedia.
        $this->assertStringNotContainsString('Terima kasih atas kunjungan Anda!', $output);
        $this->assertStringContainsString('BAYAR (MENUNGGU):', $output);
        $this->assertStringNotContainsString("\n\n\x1d\x56\x42\x00", $output);
    }
}
