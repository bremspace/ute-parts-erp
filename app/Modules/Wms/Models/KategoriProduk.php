<?php

namespace App\Modules\Wms\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'parent_id', 'nama', 'slug', 'icon', 'urutan', 'is_active',
])]
class KategoriProduk extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'kategori_produk';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Kategori Produk');
    }

    protected $casts = [
        'is_active' => 'boolean',
        'urutan' => 'integer',
        'parent_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->slug) && ! empty($model->nama)) {
                $base = Str::slug($model->nama);
                $slug = $base;
                $counter = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $base.'-'.$counter++;
                }
                $model->slug = $slug;
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('urutan')->orderBy('nama');
    }

    public function produks(): HasMany
    {
        return $this->hasMany(Produk::class, 'kategori_id');
    }

    public function produk(): HasMany
    {
        return $this->hasMany(Produk::class, 'kategori_id');
    }

    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id')->where('is_active', true)->orderBy('urutan')->orderBy('nama');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Ambil nama lengkap berjenjang (e.g. "Sparepart HP > LCD & Touchscreen").
     */
    public function getNamaLengkapAttribute(): string
    {
        if ($this->parent) {
            return "{$this->parent->nama} > {$this->nama}";
        }

        return $this->nama;
    }

    /**
     * Ambil seluruh ID kategori ini beserta seluruh sub-kategorinya secara rekursif.
     * Sangat berguna untuk query produk (misal user pilih kategori utama 'Sparepart HP',
     * semua produk di subkategori LCD, Baterai, dll ikut tersaring secara efisien).
     *
     * @return array<int>
     */
    public function semuaKeturunanIds(): array
    {
        $ids = [$this->id];
        $children = $this->children()->get();

        foreach ($children as $child) {
            $ids = array_merge($ids, $child->semuaKeturunanIds());
        }

        return array_values(array_unique($ids));
    }

    /**
     * Dapatkan pohon kategori (tree) lengkap dengan cache.
     */
    public static function getTree(): Collection
    {
        return static::root()
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('urutan')->orderBy('nama')])
            ->get();
    }

    /**
     * Dapatkan pohon kategori yang hanya memiliki produk di master produk (atau fallback ke semua jika kosong).
     */
    public static function getTreeTersediaDiProduk(): Collection
    {
        $usedCatIds = Produk::whereNotNull('kategori_id')->distinct()->pluck('kategori_id')->all();
        if (empty($usedCatIds)) {
            return static::getTree();
        }

        $parentIds = static::whereIn('id', $usedCatIds)->whereNotNull('parent_id')->pluck('parent_id')->all();
        $allRelevantIds = array_values(array_unique(array_merge($usedCatIds, $parentIds)));

        $tree = static::root()
            ->whereIn('id', $allRelevantIds)
            ->with(['children' => fn ($q) => $q->where('is_active', true)->whereIn('id', $allRelevantIds)->orderBy('urutan')->orderBy('nama')])
            ->get();

        return $tree->isNotEmpty() ? $tree : static::getTree();
    }
}
