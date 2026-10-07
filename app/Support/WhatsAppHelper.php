<?php

namespace App\Support;

class WhatsAppHelper
{
    /**
     * Format nomor HP Indonesia/internasional ke format standar WhatsApp (hanya digit, awalan 62).
     */
    public static function formatNomor(?string $nomor): ?string
    {
        if (blank($nomor)) {
            return null;
        }

        // Ambil hanya angka
        $digits = preg_replace('/[^0-9]/', '', (string) $nomor);
        if (blank($digits)) {
            return null;
        }

        // Ubah 08xxx -> 628xxx
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        return $digits;
    }

    /**
     * Buat URL intent WhatsApp (wa.me) yang kompatibel di HP (aplikasi WA) & PC (WhatsApp Web / App).
     */
    public static function buatLink(?string $nomor, string $pesan): ?string
    {
        $nomorFormatted = self::formatNomor($nomor);
        if (! $nomorFormatted) {
            return null;
        }

        return 'https://wa.me/'.$nomorFormatted.'?text='.rawurlencode($pesan);
    }

    /**
     * Template pesan struk penjualan kasir POS.
     */
    public static function draftStrukPos(array $receiptData): string
    {
        $toko = $receiptData['cabang'] ?? config('app.name', 'Ute Parts');
        $noTrx = $receiptData['no_transaksi'] ?? '-';
        $waktu = $receiptData['waktu'] ?? now()->format('d/m/Y H:i');
        $pelanggan = $receiptData['pelanggan'] ?? 'Pelanggan';
        $total = number_format((float) ($receiptData['total'] ?? 0), 0, ',', '.');
        $metode = $receiptData['metode'] ?? 'TUNAI';

        $items = '';
        foreach (($receiptData['items'] ?? []) as $it) {
            $nama = $it['nama'] ?? $it['nama_produk'] ?? 'Item';
            $qty = (int) ($it['qty'] ?? 1);
            $subtotal = number_format((float) ($it['subtotal'] ?? 0), 0, ',', '.');
            $items .= "• {$nama} (x{$qty}): Rp {$subtotal}\n";
        }

        return "Halo kak *{$pelanggan}*,\n"
            ."Terima kasih telah berbelanja di *{$toko}*! 🙏\n\n"
            ."📄 *STRUK PEMBELIAN*\n"
            ."No Transaksi: #{$noTrx}\n"
            ."Waktu: {$waktu}\n"
            ."--------------------------------\n"
            ."{$items}"
            ."--------------------------------\n"
            ."💰 *Total: Rp {$total}* ({$metode})\n\n"
            ."Simpan pesan ini sebagai bukti transaksi resmi Anda.\n"
            .'Semoga harimu menyenangkan! ✨';
    }

    /**
     * Template pesan penerimaan unit servis (Nota Servis & Tracking).
     */
    public static function draftTerimaServis(string $pelanggan, string $noTiket, string $jenisHp, string $token): string
    {
        $toko = config('app.name', 'Ute Parts');
        $trackingUrl = url("/tracking/{$token}");

        return "Halo kak *{$pelanggan}*,\n"
            ."Unit Anda telah kami terima di *{$toko}*. 📱🔧\n\n"
            ."📋 *NOTA SERVIS*\n"
            ."No Tiket: *#{$noTiket}*\n"
            ."Perangkat: {$jenisHp}\n"
            ."Status: Sedang dalam antrean diagnosa teknisi.\n\n"
            ."🔍 Anda dapat memantau proses perbaikan secara real-time di sini:\n"
            ."{$trackingUrl}\n\n"
            .'Kami akan mengabari kembali setelah estimasi biaya selesai. Terima kasih!';
    }

    /**
     * Template pesan estimasi biaya servis & persetujuan online.
     */
    public static function draftEstimasiServis(string $pelanggan, string $noTiket, string $jenisHp, float $biaya, string $alasan, string $token): string
    {
        $toko = config('app.name', 'Ute Parts');
        $nominal = number_format($biaya, 0, ',', '.');
        $approvalUrl = url("/tracking/{$token}");

        return "Halo kak *{$pelanggan}*,\n"
            ."Hasil diagnosa teknisi *{$toko}* untuk tiket *#{$noTiket}* ({$jenisHp}):\n\n"
            ."📝 Pengerjaan: {$alasan}\n"
            ."💰 Estimasi Biaya: *Rp {$nominal}*\n\n"
            ."Silakan klik link berikut untuk konfirmasi persetujuan pengerjaan:\n"
            ."👉 {$approvalUrl}\n\n"
            .'Pengerjaan akan segera dimulai setelah Anda memberikan konfirmasi. Terima kasih!';
    }

    /**
     * Template pesan unit servis selesai & siap diambil.
     */
    public static function draftServisSelesai(string $pelanggan, string $noTiket, string $jenisHp, float $total, ?string $token): string
    {
        $toko = config('app.name', 'Ute Parts');
        $nominal = number_format($total, 0, ',', '.');
        $trackingUrl = $token ? url("/tracking/{$token}") : '';

        $pesan = "Halo kak *{$pelanggan}*,\n"
            ."Kabar baik! Perbaikan gadget *{$jenisHp}* (Tiket *#{$noTiket}*) telah *SELESAI* dan lolos uji Quality Control (QC). 🎉\n\n"
            ."Total Biaya: *Rp {$nominal}*\n"
            ."Unit sudah siap untuk diambil di toko *{$toko}*.\n\n";

        if ($trackingUrl) {
            $pesan .= "Rincian servis & status garansi dapat dilihat di:\n{$trackingUrl}\n\n";
        }

        $pesan .= 'Mohon tunjukkan tiket ini saat pengambilan. Terima kasih!';

        return $pesan;
    }
}
