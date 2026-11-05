<?php

namespace App\Modules\Wms\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['merk', 'model', 'kode_produk', 'nama', 'is_active', 'is_default'])]
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
        'is_default' => 'boolean',
    ];

    public static ?array $cachedResolutionMap = null;

    public static function getDefault(): self
    {
        return static::firstOrCreate(
            ['merk' => 'Universal', 'model' => 'Semua Tipe HP'],
            [
                'nama' => 'Universal Semua Tipe HP',
                'is_active' => true,
                'is_default' => true,
            ]
        );
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::clearResolutionCache());
        static::deleted(fn () => static::clearResolutionCache());

        static::deleting(function (self $model) {
            if ($model->is_default || ($model->merk === 'Universal' && $model->model === 'Semua Tipe HP')) {
                throw new \DomainException("Tipe HP '{$model->nama_tampilan}' adalah tipe HP default sistem dan tidak dapat dihapus.");
            }

            $default = static::getDefault();

            // Ambil semua produk yang terhubung ke tipe_hp yang akan dihapus
            $productIds = DB::table('kompatibilitas_produk_tipe_hp')
                ->where('tipe_hp_id', $model->id)
                ->pluck('produk_id');

            // Hapus relasi pivot tipe_hp ini
            DB::table('kompatibilitas_produk_tipe_hp')
                ->where('tipe_hp_id', $model->id)
                ->delete();

            // Jika produk tidak lagi memiliki tipe HP sama sekali, hubungkan ke default Universal
            foreach ($productIds as $pId) {
                $hasOther = DB::table('kompatibilitas_produk_tipe_hp')
                    ->where('produk_id', $pId)
                    ->exists();

                if (! $hasOther) {
                    DB::table('kompatibilitas_produk_tipe_hp')->insertOrIgnore([
                        'produk_id' => $pId,
                        'tipe_hp_id' => $default->id,
                    ]);
                }
            }
        });
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
        return static::formatNamaTampilan($this->merk, $this->model, $this->nama, $this->kode_produk);
    }

    /**
     * Format label tampilan ponsel untuk konsistensi di seluruh modul:
     * e.g. "Apple iPhone 16", "Apple iPhone 16 (A3287)", atau "Apple iPhone 11 (A2221)".
     */
    public static function formatNamaTampilan(?string $merk, ?string $model, ?string $nama = null, ?string $kodeProduk = null): string
    {
        $merk = trim((string) $merk);
        $model = trim((string) $model);
        $nama = trim((string) $nama);
        $kodeProduk = trim((string) $kodeProduk);

        // 1. Jika nama lengkap tampilan terisi di katalog atribut, gunakan nama lengkap
        if ($nama !== '') {
            $formattedNama = $nama;
            if ($merk !== '' && ! in_array(strtolower($merk), ['universal', 'oem', 'accessories', 'tg', 'tools service'], true)) {
                if (! str_starts_with(strtolower($nama), strtolower($merk))) {
                    $formattedNama = $merk.' '.$nama;
                }
            }

            // Jika ada kode_produk dan belum tertulis di dalam $nama
            if ($kodeProduk !== '' && ! str_contains(strtolower($formattedNama), strtolower($kodeProduk))) {
                return $formattedNama.' ('.$kodeProduk.')';
            }

            return $formattedNama;
        }

        // 2. Jika model kosong
        if ($model === '') {
            return $merk;
        }

        $lowerModel = strtolower($model);
        $lowerMerk = strtolower($merk);

        // Perangkat generic / universal
        if (in_array($lowerModel, ['universal', 'semua tipe hp', 'all devices', 'multi-brand'], true)) {
            return $kodeProduk !== '' ? $model.' ('.$kodeProduk.')' : $model;
        }

        if ($merk === '' || in_array($lowerMerk, ['universal', 'oem', 'accessories', 'tg', 'tools service'], true)) {
            return $kodeProduk !== '' ? $model.' ('.$kodeProduk.')' : $model;
        }

        $base = $model;

        // Jika model belum diawali dengan nama merk (misal "Galaxy A52" vs "Samsung Galaxy A52")
        if (! str_starts_with($lowerModel, $lowerMerk)) {
            // Khusus Apple jika model hanya angka e.g. "11", "11 Pro", "13"
            if (strcasecmp($merk, 'Apple') === 0 && preg_match('/^\d+/i', $model)) {
                $base = 'Apple iPhone '.$model;
            } else {
                $base = $merk.' '.$model;
            }
        }

        if ($kodeProduk !== '' && ! str_contains(strtolower($base), strtolower($kodeProduk))) {
            return $base.' ('.$kodeProduk.')';
        }

        return $base;
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
                    ->select('merk', 'model', 'kode_produk', 'nama')
                    ->get()
                    ->groupBy(fn ($t) => strtolower(trim($t->model)))
                    ->map(fn ($group) => $group->map(fn ($item) => [
                        'merk' => $item->merk,
                        'model' => $item->model,
                        'kode_produk' => $item->kode_produk,
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
                        return static::formatNamaTampilan($e['merk'], $e['model'], $e['nama'], $e['kode_produk'] ?? null);
                    }
                }
            }
            $first = $entries[0];

            return static::formatNamaTampilan($first['merk'], $first['model'], $first['nama'], $first['kode_produk'] ?? null);
        }

        return static::formatNamaTampilan($brandHint, $cleanModel, null);
    }

    /**
     * Resolusi nama merk/brand HP untuk model ponsel tertentu.
     */
    public static function resolveBrandForModel(string $model, ?string $brandHint = null): string
    {
        $cleanModel = trim($model);
        if ($cleanModel === '') {
            return $brandHint ? trim($brandHint) : '';
        }

        $lowerModel = strtolower($cleanModel);
        if (in_array($lowerModel, ['universal', 'semua tipe hp', 'all devices', 'multi-brand', 'multi-brand qualcomm'], true)) {
            return 'Universal';
        }

        if ($brandHint && ! in_array(strtolower(trim($brandHint)), ['universal', 'semua', 'all', ''], true)) {
            return trim($brandHint);
        }

        if (static::$cachedResolutionMap === null) {
            static::resolveLabelForModel('dummy');
        }

        $entries = static::$cachedResolutionMap[$lowerModel] ?? null;
        if (! empty($entries)) {
            return (string) $entries[0]['merk'];
        }

        return Cache::remember('tipe_hp_brand_for_'.md5($lowerModel), 300, function () use ($cleanModel, $lowerModel) {
            $p = Produk::where('model_kompatibel', $cleanModel)
                ->orWhere('model_kompatibel', 'like', "%{$cleanModel}%")
                ->whereNotNull('brand_kompatibel')
                ->where('brand_kompatibel', '!=', '')
                ->first();

            if ($p && $p->brand_kompatibel) {
                return trim($p->brand_kompatibel);
            }

            $knownBrands = [
                'Apple' => ['iphone', 'ipad', 'ipod', 'watch'],
                'Samsung' => ['galaxy', 'samsung'],
                'Xiaomi' => ['redmi', 'mi ', 'poco', 'blackshark'],
                'Oppo' => ['oppo', 'find', 'reno'],
                'Vivo' => ['vivo', 'iqoo'],
                'Realme' => ['realme'],
                'Infinix' => ['infinix', 'hot ', 'smart ', 'note '],
                'Tecno' => ['tecno', 'spark', 'camon', 'pova'],
                'Asus' => ['asus', 'zenfone', 'rog'],
                'Huawei' => ['huawei', 'mate', 'nova', 'p30', 'p40', 'p50'],
                'Google' => ['pixel'],
                'Nokia' => ['nokia'],
                'Sony' => ['xperia', 'sony'],
            ];

            foreach ($knownBrands as $bName => $prefixes) {
                foreach ($prefixes as $prf) {
                    if (str_starts_with($lowerModel, $prf) || str_contains($lowerModel, $prf)) {
                        return $bName;
                    }
                }
            }

            return 'Lainnya';
        });
    }

    /**
     * Urutkan daftar model HP berdasarkan abjad dengan acuan utama Merek HP (A-Z).
     *
     * @param  iterable<string>  $models
     * @return array<string>
     */
    public static function sortModelsByBrandAndAlphabet(iterable $models, ?string $brandHint = null): array
    {
        $items = collect($models)
            ->map(fn ($m) => trim((string) $m))
            ->filter()
            ->unique()
            ->map(function ($m) use ($brandHint) {
                $brand = static::resolveBrandForModel($m, $brandHint);
                $label = static::resolveLabelForModel($m, $brand);

                return [
                    'model' => $m,
                    'brand' => $brand,
                    'label' => $label,
                ];
            })
            ->all();

        usort($items, function ($a, $b) {
            $brandA = (strcasecmp($a['brand'], 'Universal') === 0) ? 'ZZZ' : $a['brand'];
            $brandB = (strcasecmp($b['brand'], 'Universal') === 0) ? 'ZZZ' : $b['brand'];

            $cmp = strcasecmp($brandA, $brandB);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strnatcasecmp($a['label'], $b['label']);
        });

        return array_column($items, 'model');
    }

    /**
     * Kelompokkan model HP berdasarkan Merek HP (A-Z) dan diurutkan abjad di dalam setiap grup.
     *
     * @param  iterable<string>  $models
     * @return array<string, array<string>>
     */
    public static function groupModelsByBrand(iterable $models, ?string $brandHint = null): array
    {
        $sorted = static::sortModelsByBrandAndAlphabet($models, $brandHint);
        $grouped = [];

        foreach ($sorted as $m) {
            $brand = static::resolveBrandForModel($m, $brandHint);
            $grouped[$brand][] = $m;
        }

        return $grouped;
    }
}
