<?php

namespace App\Modules\Webhook\Enums;

/**
 * [F3-5] Whitelist event webhook outbound (G-16).
 * String bebas DITOLAK — hanya nilai enum ini yang boleh di-dispatch/di-subscribe.
 */
enum WebhookEvent: string
{
    case TransaksiSelesai = 'transaksi.selesai';
    case StokBerubah = 'stok.berubah';
    case ServisSelesai = 'servis.selesai';

    /**
     * Semua nilai event yang sah (untuk validasi & checklist UI).
     *
     * @return array<int, string>
     */
    public static function semua(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function valid(string $event): bool
    {
        return in_array($event, self::semua(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::TransaksiSelesai => 'Transaksi POS selesai',
            self::StokBerubah => 'Stok berubah',
            self::ServisSelesai => 'Tiket servis selesai',
        };
    }
}
