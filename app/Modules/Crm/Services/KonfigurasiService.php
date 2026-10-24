<?php

namespace App\Modules\Crm\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * [T-22] Konfigurasi operasional (strategi loyalitas) — editable tanpa deploy.
 * Tabel `konfigurasi`: kunci → nilai (JSON utk kompleks).
 * Perubahan hanya berlaku untuk transaksi baru (tidak retroaktif).
 */
class KonfigurasiService
{
    /**
     * [B-15d] TTL cache konfigurasi. Nilai konfigurasi jarang berubah (di-set dari
     * halaman Pengaturan / API CRM), jadi 5 menit aman; setiap `set()` wajib
     * forget key terkait (lihat set()) supaya perubahan tetap langsung berlaku.
     */
    private const CACHE_TTL = 300;

    private const CACHE_PREFIX = 'crm-konfigurasi:';

    public function get(string $kunci, mixed $default = null): mixed
    {
        // [B-15d] Cache per-kunci (invalidasi di set()) — 1 query per kunci hilang.
        //
        // Nilai dibungkus array `['nilai' => …]`: `Cache::remember()` memakai
        // `! is_null($value)` sebagai penanda hit, jadi nilai NULL (kunci yang
        // belum pernah disimpan) akan MISS terus-menerus dan query terus kena.
        $hit = Cache::remember(
            self::CACHE_PREFIX.$kunci,
            self::CACHE_TTL,
            fn (): array => ['nilai' => $this->ambilNilai($kunci) ?? $default]
        );

        return $hit['nilai'];
    }

    /**
     * [B-15d] Ambil banyak kunci dalam SATU query (`whereIn`).
     * Dipakai CrmController::config() GET yang butuh 5 kunci sekaligus —
     * sebelumnya 5 query (1 per kunci), sekarang 1.
     *
     * @param  array<int, string>  $kunci
     * @return array<string, mixed> hanya kunci yang ADA di tabel konfigurasi
     */
    public function getMany(array $kunci): array
    {
        $kunci = array_values(array_unique($kunci));
        if ($kunci === []) {
            return [];
        }

        $rows = DB::table('konfigurasi')->whereIn('kunci', $kunci)->get();
        if ($rows->isEmpty()) {
            return [];
        }

        $hasil = [];
        foreach ($rows as $row) {
            $hasil[$row->kunci] = $this->decode((string) $row->nilai);
        }

        return $hasil;
    }

    public function set(string $kunci, mixed $nilai, ?string $deskripsi = null): void
    {
        $existing = DB::table('konfigurasi')->where('kunci', $kunci)->first();
        $now = now();

        if ($existing) {
            DB::table('konfigurasi')->where('kunci', $kunci)->update([
                'nilai' => is_scalar($nilai) ? (string) $nilai : json_encode($nilai),
                'deskripsi' => $deskripsi,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('konfigurasi')->insert([
                'kunci' => $kunci,
                'nilai' => is_scalar($nilai) ? (string) $nilai : json_encode($nilai),
                'deskripsi' => $deskripsi,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // [B-15d] Invalidasi cache WAJIB di titik tulis (satu-satunya jalur tulis).
        Cache::forget(self::CACHE_PREFIX.$kunci);
    }

    /**
     * [B-15d] Baca 1 kunci langsung dari DB (tanpa cache) — dipakai `get()`.
     */
    private function ambilNilai(string $kunci): mixed
    {
        $row = DB::table('konfigurasi')->where('kunci', $kunci)->first();
        if (! $row) {
            return null;
        }

        return $this->decode((string) $row->nilai);
    }

    /**
     * Nilai konfigurasi disimpan sebagai string; string JSON → decode, selain itu
     * dikembalikan apa adanya (paritas dgn perilaku lama).
     */
    private function decode(string $val): mixed
    {
        $decoded = json_decode($val, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $val;
    }

    /**
     * Default strategi (dipakai PricingService/TierService bila config kosong).
     */
    public function defaults(): array
    {
        return [
            'poin_earn_persen' => 5,          // % dari nominal transaksi → poin
            'poin_redeem_rupiah' => 100,       // 1 poin = Rp X diskon
            'diskon_silver' => 3,
            'diskon_gold' => 5,
            'diskon_platinum' => 10,
        ];
    }

    public function poinEarnPersen(): float
    {
        return (float) ($this->get('poin_earn_persen') ?? 0);
    }

    public function poinRedeemRupiah(): float
    {
        return (float) ($this->get('poin_redeem_rupiah') ?? 0);
    }

    /**
     * Apakah Pajak Otomatis diaktifkan di cabang ini?
     * Returning nullable utk backward compatibility.
     */
    public function getPpnEnabled(?int $cabangId = null): ?bool
    {
        $key = $cabangId ? "ppn_enabled.{$cabangId}" : 'ppn_enabled';
        $val = $this->get($key);

        return $val === null ? null : (bool) $val;
    }

    /**
     * Persen PPN utk cabang ini (default 11).
     */
    public function getPpnPercent(?int $cabangId = null): float
    {
        $key = $cabangId ? "ppn_percent.{$cabangId}" : 'ppn_percent';

        return (float) ($this->get($key) ?? 11);
    }

    /**
     * Simpan konfigurasi Pajak Otomatis.
     */
    public function setPpn(?int $cabangId, bool $enabled, float $percent): void
    {
        $this->set("ppn_enabled.{$cabangId}", $enabled, 'Pajak Otomatis diaktifkan di cabang');
        $this->set("ppn_percent.{$cabangId}", $percent, 'Persen PPN per cabang');
    }

    /**
     * Setup konfigurasi PPN default (idempotent).
     */
    public function seedPpnDefaults(): void
    {
        $this->set('ppn_enabled', false, 'Aktifkan Pajak Otomatis per cabang');
        $this->set('ppn_percent', 11, 'Persen PPN nasional default');
    }

    /**
     * Konfigurasi cetak label QR barcode produk.
     * Default preset 33mm x 15mm sesuai kebutuhan label rak/produk.
     */
    public function getLabelQrConfig(): array
    {
        $default = [
            'preset' => '33x15',
            'width' => 33.0,
            'height' => 15.0,
            'margin_top' => 1.0,
            'margin_bottom' => 1.0,
            'margin_left' => 1.5,
            'margin_right' => 1.5,
            'show_brand' => true,
            'show_price' => true,
            'show_sku' => true,
        ];

        $cfg = $this->get('label_qr_config');

        return is_array($cfg) ? array_merge($default, $cfg) : $default;
    }

    /**
     * Konfigurasi struk kasir / POS thermal print.
     * Default lebar 80mm, lengkap dengan header toko, QR code retur, dan garansi.
     */
    public function getStrukConfig(): array
    {
        $default = [
            // Header Toko
            'nama_toko' => 'UTE PARTS',
            'slogan' => 'Pusat Sparepart & Servis HP',
            'alamat' => 'Jl. Veteran No. 12',
            'telepon' => '0812-3456-7890',
            'website_medsos' => 'www.uteparts.id',

            // Komponen Informasi
            'show_cabang' => true,
            'show_kasir' => true,
            'show_pelanggan' => true,
            'show_waktu' => true,
            'show_subtotal_breakdown' => true,

            // QR Code Faktur Retur & Garansi
            'show_qr_retur' => true,
            'qr_size' => 80,
            'qr_caption' => 'Scan QR ini untuk retur & klaim garansi',

            // Footer & Ketentuan
            'footer_text' => 'Terima kasih atas kunjungan Anda!',
            'kebijakan_retur' => 'Maksimal klaim retur / garansi 3 hari dengan membawa struk ber-QR ini.',
            'catatan_kaki' => 'Segel toko wajib utuh & fisik barang tidak cacat.',

            // Format Cetak
            'default_width' => '80mm',
            'font_size' => 'normal',
            'auto_print' => false,
        ];

        $cfg = $this->get('struk_config');

        return is_array($cfg) ? array_merge($default, $cfg) : $default;
    }

    /**
     * Konfigurasi format cetak dokumen WMS (Purchase Order, Bukti GRN, Berita Acara).
     * Default format: Setengah A4 (A5 portrait) dengan margin 8mm.
     */
    public function getWmsDocConfig(): array
    {
        $default = [
            'paper_size' => 'a5', // a5, a4
            'orientation' => 'portrait', // portrait, landscape
            'margin_mm' => 8,
            'font_size' => '10pt',
            'show_kop' => true,
            'show_ttd' => true,
        ];

        $cfg = $this->get('wms_doc_config');

        return is_array($cfg) ? array_merge($default, $cfg) : $default;
    }
}
