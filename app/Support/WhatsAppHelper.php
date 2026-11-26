<?php

namespace App\Support;

use App\Modules\Notifikasi\Services\WaTemplateService;

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

        $standar = NomorTeleponIndonesia::standar($nomor);

        return $standar !== '' ? $standar : null;
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
        // Satu sumber dengan struk otomatis (NotificationService) agar pesan
        // manual via wa.me dan pengiriman gateway tidak pernah berbeda.
        return app(WaTemplateService::class)->renderStrukKasir($receiptData);
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
