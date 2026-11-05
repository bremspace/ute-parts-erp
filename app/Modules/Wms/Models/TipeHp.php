<?php

namespace App\Modules\Wms\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['merk', 'model', 'nama', 'is_active'])]
class TipeHp extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'tipe_hp';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Tipe HP');
    }

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static ?array $cachedResolutionMap = null;

    protected static function booted(): void
    {
        static::saved(fn () => static::clearResolutionCache());
        static::deleted(fn () => static::clearResolutionCache());
    }

    public static function clearResolutionCache(): void
    {
        static::$cachedResolutionMap = null;
        Cache::forget('tipe_hp_resolution_map');
    }

    public function produk(): BelongsToMany
    {
        return $this->belongsToMany(
            Produk::class,
            'kompatibilitas_produk_tipe_hp',
            'tipe_hp_id',
            'produk_id'
        );
    }

    public function getNamaTampilanAttribute(): string
    {
        return static::formatNamaTampilan($this->merk, $this->model, $this->nama);
    }

    /**
     * Format label tampilan ponsel untuk konsistensi di seluruh modul:
     * e.g. "Apple iPhone 11", atau "Apple iPhone 11 (A2221)" jika nama katalog diisi.
     */
    public static function formatNamaTampilan(?string $merk, ?string $model, ?string $nama = null): string
    {
        $merk = trim((string) $merk);
        $model = trim((string) $model);
        $nama = trim((string) $nama);

        // 1. Jika nama lengkap tampilan terisi di katalog atribut, gunakan nama lengkap
        if ($nama !== '') {
            if ($merk !== '' && ! in_array(strtolower($merk), ['universal', 'oem', 'accessories', 'tg', 'tools service'], true)) {
                if (! str_starts_with(strtolower($nama), strtolower($merk))) {
                    return $merk.' '.$nama;
                }
            }

            return $nama;
        }

        // 2. Jika model kosong
        if ($model === '') {
            return $merk;
        }

        $lowerModel = strtolower($model);
        $lowerMerk = strtolower($merk);

        // Perangkat generic / universal
        if (in_array($lowerModel, ['universal', 'semua tipe hp', 'all devices', 'multi-brand'], true)) {
            return $model;
        }

        if ($merk === '' || in_array($lowerMerk, ['universal', 'oem', 'accessories', 'tg', 'tools service'], true)) {
            return $model;
        }

        // Jika model sudah diawali dengan nama merk (misal "Samsung Galaxy A52" atau "Apple iPhone 11")
        if (str_starts_with($lowerModel, $lowerMerk)) {
            return $model;
        }

        // Khusus Apple jika model hanya angka e.g. "11", "11 Pro", "13"
        if (strcasecmp($merk, 'Apple') === 0 && preg_match('/^\d+/i', $model)) {
            return 'Apple iPhone '.$model;
        }

        return $merk.' '.$model;
    }

    /**
     * Resolusi label untuk string model pada dropdown filter (WMS, POS Kasir, Marketplace)
     */
    public static function resolveLabelForModel(string $model, ?string $brandHint = null): string
    {
        $cleanModel = trim($model);
        if ($cleanModel === '') {
            return '';
        }

        $lowerModel = strtolower($cleanModel);
        if (in_array($lowerModel, ['universal', 'semua tipe hp', 'all devices'], true)) {
            return $cleanModel;
        }

        if (static::$cachedResolutionMap === null) {
            static::$cachedResolutionMap = Cache::remember('tipe_hp_resolution_map', 300, function () {
                return static::where('is_active', true)
                    ->select('merk', 'model', 'nama')
                    ->get()
                    ->groupBy(fn ($t) => strtolower(trim($t->model)))
                    ->map(fn ($group) => $group->map(fn ($item) => [
                        'merk' => $item->merk,
                        'model' => $item->model,
                        'nama' => $item->nama,
                    ])->all())
                    ->all();
            });
        }

        $entries = static::$cachedResolutionMap[$lowerModel] ?? null;
        if (! empty($entries)) {
            if ($brandHint) {
                foreach ($entries as $e) {
                    if (strcasecmp($e['merk'], $brandHint) === 0) {
                        return static::formatNamaTampilan($e['merk'], $e['model'], $e['nama']);
                    }
                }
            }
            $first = $entries[0];

            return static::formatNamaTampilan($first['merk'], $first['model'], $first['nama']);
        }

        return static::formatNamaTampilan($brandHint, $cleanModel, null);
    }
}
