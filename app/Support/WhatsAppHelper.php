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
     * Buat URL intent WhatsApp yang kompatibel di HP (aplikasi WA) & PC (WhatsApp Web / App).
     * Jika nomor tidak ada, mengarah ke dialog kirim pesan (pengguna memilih kontak).
     */
    public static function buatLink(?string $nomor, string $pesan): ?string
    {
        $nomorFormatted = self::formatNomor($nomor);
        if ($nomorFormatted) {
            return 'https://wa.me/'.$nomorFormatted.'?text='.rawurlencode($pesan);
        }

        // Jika nomor kosong, fallback ke intent universal WhatsApp agar kasir tetap bisa kirim struk
        return 'https://api.whatsapp.com/send?text='.rawurlencode($pesan);
    }

    /**
     * Template pesan struk penjualan kasir POS (format bersih, bebas simbol rusak di WA).
     */
    public static function draftStrukPos(array $receiptData): string
    {
        $tokoRaw = $receiptData['cabang'] ?? config('app.name', 'Bremspace');
        $toko = strip_tags(html_entity_decode((string) $tokoRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        $noTrx = trim((string) ($receiptData['no_transaksi'] ?? '-'));
        $waktu = $receiptData['waktu'] ?? now()->format('d/m/Y H:i');

        $pelangganRaw = $receiptData['pelanggan'] ?? 'Pelanggan';
        $pelanggan = trim(strip_tags(html_entity_decode((string) $pelangganRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        $isUmum = in_array(strtolower($pelanggan), ['pelanggan umum', 'umum', 'guest', 'retail', 'pelanggan', '-']) || blank($pelanggan);
        $salam = $isUmum
            ? "Halo kak,\nTerima kasih telah berbelanja di *{$toko}*! 🙏"
            : "Halo kak *{$pelanggan}*,\nTerima kasih telah berbelanja di *{$toko}*! 🙏";

        $total = number_format((float) ($receiptData['total'] ?? 0), 0, ',', '.');
        $metode = strtoupper(trim((string) ($receiptData['metode'] ?? 'TUNAI')));

        $items = '';
        foreach (($receiptData['items'] ?? []) as $it) {
            $namaRaw = $it['nama'] ?? $it['nama_produk'] ?? 'Item';
            $nama = strip_tags(html_entity_decode((string) $namaRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            // Bersihkan prefix teknis seperti [Jasa] atau [Part] jika ada
            $nama = preg_replace('/^\[(Jasa|Part)\]\s*/i', '', $nama);
            $nama = trim(preg_replace('/\s+/', ' ', (string) $nama));

            $qty = (int) ($it['qty'] ?? 1);
            $harga = number_format((float) ($it['harga'] ?? 0), 0, ',', '.');
            $subtotal = number_format((float) ($it['subtotal'] ?? 0), 0, ',', '.');

            $items .= "- {$nama}\n  {$qty}x Rp {$harga} = Rp {$subtotal}\n";
        }

        $garansiText = ! empty($receiptData['garansi']) ? "Garansi: {$receiptData['garansi']}\n" : '';

        $pembayaranText = "*Total: Rp {$total}*\nMetode: {$metode}";
        if (isset($receiptData['bayar']) && (float) $receiptData['bayar'] > 0 && (float) $receiptData['bayar'] !== (float) ($receiptData['total'] ?? 0)) {
            $bayarFmt = number_format((float) $receiptData['bayar'], 0, ',', '.');
            $pembayaranText .= "\nBayar: Rp {$bayarFmt}";
        }
        if (isset($receiptData['kembali']) && (float) $receiptData['kembali'] > 0) {
            $kembaliFmt = number_format((float) $receiptData['kembali'], 0, ',', '.');
            $pembayaranText .= "\nKembali: Rp {$kembaliFmt}";
        }

        return "{$salam}\n\n"
            ."*STRUK PEMBELIAN*\n"
            ."No. Transaksi: {$noTrx}\n"
            ."Waktu: {$waktu}\n"
            ."-------------------------\n"
            ."{$items}"
            ."-------------------------\n"
            ."{$pembayaranText}\n"
            .($garansiText ? "{$garansiText}" : '')
            ."\nSimpan pesan ini sebagai bukti transaksi resmi Anda.\n"
            .'Semoga harimu menyenangkan! ✨';
    }

    /**
     * Template pesan penerimaan unit servis (Nota Servis & Tracking).
     */
    public static function draftTerimaServis(string $pelanggan, string $noTiket, string $jenisHp, string $token): string
    {
        $toko = strip_tags(html_entity_decode(config('app.name', 'Bremspace'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $pelangganClean = trim(strip_tags(html_entity_decode($pelanggan, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $jenisHpClean = trim(strip_tags(html_entity_decode($jenisHp, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $trackingUrl = url("/tracking/{$token}");

        return "Halo kak *{$pelangganClean}*,\n"
            ."Unit Anda telah kami terima di *{$toko}*. 📱🔧\n\n"
            ."📋 *NOTA SERVIS*\n"
            ."No. Tiket: *#{$noTiket}*\n"
            ."Perangkat: {$jenisHpClean}\n"
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
        $toko = strip_tags(html_entity_decode(config('app.name', 'Bremspace'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $pelangganClean = trim(strip_tags(html_entity_decode($pelanggan, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $jenisHpClean = trim(strip_tags(html_entity_decode($jenisHp, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $alasanClean = trim(strip_tags(html_entity_decode($alasan, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $nominal = number_format($biaya, 0, ',', '.');
        $approvalUrl = url("/tracking/{$token}");

        return "Halo kak *{$pelangganClean}*,\n"
            ."Hasil diagnosa teknisi *{$toko}* untuk tiket *#{$noTiket}* ({$jenisHpClean}):\n\n"
            ."📝 Pengerjaan: {$alasanClean}\n"
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
        $toko = strip_tags(html_entity_decode(config('app.name', 'Bremspace'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $pelangganClean = trim(strip_tags(html_entity_decode($pelanggan, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $jenisHpClean = trim(strip_tags(html_entity_decode($jenisHp, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $nominal = number_format($total, 0, ',', '.');
        $trackingUrl = $token ? url("/tracking/{$token}") : '';

        $pesan = "Halo kak *{$pelangganClean}*,\n"
            ."Kabar baik! Perbaikan gadget *{$jenisHpClean}* (Tiket *#{$noTiket}*) telah *SELESAI* dan lolos uji Quality Control (QC). 🎉\n\n"
            ."Total Biaya: *Rp {$nominal}*\n"
            ."Unit sudah siap untuk diambil di toko *{$toko}*.\n\n";

        if ($trackingUrl) {
            $pesan .= "Rincian servis & status garansi dapat dilihat di:\n{$trackingUrl}\n\n";
        }

        $pesan .= 'Mohon tunjukkan tiket ini saat pengambilan. Terima kasih!';

        return $pesan;
    }
}
