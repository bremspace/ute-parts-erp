<?php

namespace App\Modules\Marketplace\Services;

use App\Modules\Marketplace\Models\WebBanner;
use App\Modules\Marketplace\Models\WebKonsumenSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class WebKonsumenService
{
    public const CACHE_KEY = 'web_konsumen_config_v1';

    public const CACHE_TTL = 86400; // 24 jam

    private static ?array $memoizedConfig = null;

    private static ?Collection $memoizedBanners = null;

    /**
     * Dapatkan semua setting dalam format array asosiatif ter-parse.
     * Menggunakan cache 24 jam untuk menjaga performa server 1GB RAM.
     *
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        if (self::$memoizedConfig !== null) {
            return self::$memoizedConfig;
        }

        return self::$memoizedConfig = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            try {
                $settings = WebKonsumenSetting::all();
                $data = [];

                foreach ($settings as $setting) {
                    $data[$setting->setting_key] = $setting->getParsedValue();
                }

                return $data;
            } catch (\Throwable) {
                return [];
            }
        });
    }

    /**
     * Dapatkan satu nilai setting spesifik.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->getAll();

        return $all[$key] ?? $default;
    }

    /**
     * Simpan kumpulan setting dan invalidate cache.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveSettings(array $data, ?int $userId = null): void
    {
        foreach ($data as $key => $value) {
            $isJsonTarget = is_array($value) || in_array($key, ['hero_features', 'hero_stats', 'allowed_filters', 'social_links'], true);
            $type = match (true) {
                $isJsonTarget => 'json',
                is_bool($value) => 'boolean',
                is_int($value) => 'integer',
                default => 'string',
            };

            $valStr = match ($type) {
                'json' => is_array($value) ? json_encode($value) : (string) $value,
                'boolean' => $value ? 'true' : 'false',
                'integer' => (string) $value,
                default => (string) $value,
            };

            $setting = WebKonsumenSetting::where('setting_key', $key)->first();
            if ($setting) {
                $finalType = ($setting->value_type === 'image' && $type === 'string') ? 'image' : $type;

                $setting->update([
                    'setting_value' => $valStr,
                    'value_type' => $finalType,
                    'updated_by' => $userId,
                ]);
            } else {
                WebKonsumenSetting::create([
                    'group_key' => 'general',
                    'setting_key' => $key,
                    'setting_value' => $valStr,
                    'value_type' => $type,
                    'deskripsi' => $key,
                    'updated_by' => $userId,
                ]);
            }
        }

        $this->clearCache();
    }

    /**
     * Hapus cache setting & banner.
     */
    public function clearCache(): void
    {
        self::$memoizedConfig = null;
        self::$memoizedBanners = null;

        Cache::forget(self::CACHE_KEY);
        Cache::forget('web_banners_active_all');
        Cache::forget('web_banners_active_hero_slider');
        Cache::forget('web_banners_active_hero_side');
        Cache::forget('web_banners_active_mid_catalog');
        Cache::forget('web_banners_active_popup_modal');
    }

    /**
     * Dapatkan banner aktif berdasarkan posisi (ter-cache 1 jam).
     * Cache menyimpan array mentah agar tidak terjadi __PHP_Incomplete_Class
     * saat unserialize model sebelum autoload.
     */
    public function getActiveBanners(?string $posisi = null): Collection
    {
        if ($posisi === null && self::$memoizedBanners !== null) {
            return self::$memoizedBanners;
        }

        $cacheKey = 'web_banners_active_'.($posisi ?? 'all');

        $rows = Cache::remember($cacheKey, 3600, function () use ($posisi) {
            try {
                $query = WebBanner::active();
                if ($posisi) {
                    $query->posisi($posisi);
                }

                return $query->get()->map->toArray()->all();
            } catch (\Throwable) {
                return [];
            }
        });

        $banners = WebBanner::hydrate($rows);

        if ($posisi === null) {
            self::$memoizedBanners = $banners;
        }

        return $banners;
    }

    /**
     * Helper formatting stok seragam sesuai stock_display_mode & stock_threshold_limit.
     *
     * @return array{
     *     mode: string,
     *     label: string,
     *     sublabel: string,
     *     status: 'tersedia'|'terbatas'|'habis',
     *     badge_class: string,
     *     is_available: bool,
     *     is_low: bool,
     *     exact_qty: int
     * }
     */
    public function formatStockDisplay(int $stockTotal): array
    {
        $mode = (string) $this->get('stock_display_mode', 'threshold_only');
        $threshold = (int) $this->get('stock_threshold_limit', 5);

        $isAvailable = $stockTotal > 0;
        $isLow = $isAvailable && $stockTotal <= $threshold;

        if (! $isAvailable) {
            return [
                'mode' => $mode,
                'label' => 'Stok Habis',
                'sublabel' => 'Dapat dipesan inden/PO',
                'status' => 'habis',
                'badge_class' => 'bg-red-500/10 text-red-500 border-red-500/20',
                'is_available' => false,
                'is_low' => false,
                'exact_qty' => 0,
            ];
        }

        if ($mode === 'exact_number') {
            return [
                'mode' => $mode,
                'label' => "Stok: {$stockTotal} pcs",
                'sublabel' => $isLow ? 'Stok menipis' : 'Siap kirim',
                'status' => $isLow ? 'terbatas' : 'tersedia',
                'badge_class' => $isLow ? 'bg-amber-500/10 text-amber-500 border-amber-500/20' : 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20',
                'is_available' => true,
                'is_low' => $isLow,
                'exact_qty' => $stockTotal,
            ];
        }

        if ($mode === 'badge_only') {
            return [
                'mode' => $mode,
                'label' => 'Stok Tersedia',
                'sublabel' => 'Siap dikirim ke seluruh cabang',
                'status' => 'tersedia',
                'badge_class' => 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20',
                'is_available' => true,
                'is_low' => false,
                'exact_qty' => $stockTotal,
            ];
        }

        // Default: threshold_only
        if ($isLow) {
            return [
                'mode' => $mode,
                'label' => "Sisa {$stockTotal} pcs",
                'sublabel' => 'Segera habis, pesan sebelum kehabisan',
                'status' => 'terbatas',
                'badge_class' => 'bg-amber-500/10 text-amber-500 border-amber-500/20 animate-pulse',
                'is_available' => true,
                'is_low' => true,
                'exact_qty' => $stockTotal,
            ];
        }

        return [
            'mode' => $mode,
            'label' => 'Stok Tersedia',
            'sublabel' => 'Stok aman di gudang & cabang',
            'status' => 'tersedia',
            'badge_class' => 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20',
            'is_available' => true,
            'is_low' => false,
            'exact_qty' => $stockTotal,
        ];
    }
}
