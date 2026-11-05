<?php

namespace App\Modules\Wms\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'parent_id', 'nama', 'slug', 'icon', 'urutan', 'is_active', 'is_default',
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
        'is_default' => 'boolean',
        'urutan' => 'integer',
        'parent_id' => 'integer',
    ];

    public static function getDefault(): self
    {
        return static::firstOrCreate(
            ['slug' => 'umum'],
            [
                'nama' => 'Umum',
                'parent_id' => null,
                'urutan' => 0,
                'is_active' => true,
                'is_default' => true,
            ]
        );
    }

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

        static::deleting(function (self $model) {
            if ($model->is_default || $model->slug === 'umum') {
                throw new \DomainException("Kategori '{$model->nama}' adalah kategori default sistem dan tidak dapat dihapus.");
            }

            $default = static::getDefault();

            // 1. Alihkan seluruh sub-kategori ke parent kategori yang dihapus
            static::where('parent_id', $model->id)->update([
                'parent_id' => $model->parent_id,
            ]);

            // 2. Alihkan seluruh produk yang terhubung ke kategori default 'Umum'
            DB::table('produk')->where('kategori_id', $model->id)->update([
                'kategori_id' => $default->id,
                'kategori' => $default->nama,
            ]);

            DB::table('produk')->where('kategori', $model->nama)->whereNull('kategori_id')->update([
                'kategori_id' => $default->id,
                'kategori' => $default->nama,
            ]);
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
        return $query->where(fn ($q) => $q->whereNull('parent_id')->orWhere('parent_id', 0))
            ->where('is_active', true)
            ->orderBy('urutan')
            ->orderBy('nama');
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
     * Dapatkan pohon kategori (tree) lengkap dengan penanganan kategori yatim/flat.
     */
    public static function getTree(): Collection
    {
        $all = static::where('is_active', true)
            ->with('parent')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();

        $byParent = $all->groupBy(fn ($k) => (int) ($k->parent_id ?? 0));
        $roots = $byParent->get(0, collect());

        foreach ($roots as $root) {
            $root->setRelation('children', $byParent->get($root->id, collect()));
        }

        // Tangani kategori yatim (orphaned categories)
        $rootIds = $roots->pluck('id')->all();
        $handledIds = array_merge($rootIds, $roots->flatMap(fn ($r) => $r->children->pluck('id'))->all());
        $orphans = $all->whereNotIn('id', $handledIds);

        foreach ($orphans as $orphan) {
            $orphan->setRelation('children', collect());
            $roots->push($orphan);
        }

        return $roots;
    }

    /**
     * Dapatkan pohon kategori aktif lengkap untuk dropdown dan filter master produk.
     */
    public static function getTreeTersediaDiProduk(): Collection
    {
        return static::getTree();
    }
}
