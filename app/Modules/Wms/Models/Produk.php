<?php

namespace App\Modules\Wms\Models;

use App\Modules\Omnichannel\Models\ChannelProductMapping;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'nama', 'slug', 'barcode', 'deskripsi', 'kategori', 'kategori_id', 'brand_kompatibel',
    'model_kompatibel', 'kondisi', 'satuan', 'harga_beli',
    'metode_harga_beli', 'harga_jual_retail', 'margin_persen', 'gambar', 'foto', 'meta_title', 'meta_description',
    'kompatibilitas_hp', 'is_active',
    // [T-44]
    'brand_id', 'kualitas_id',
    // [HARGA FLEKSIBEL]
    'harga_fleksibel',
    // [F2-3] Serial number tracking
    'sn',
    // [PROCUREMENT: ABC, ROP, Min-Max, JIT]
    'abc_class', 'reorder_point', 'min_stock', 'max_stock', 'is_ondemand',
])]
class Produk extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'produk';

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

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Produk');
    }

    protected $casts = [
        'is_active' => 'boolean',
        'sn' => 'boolean', // [F2-3] wajib SN di GRN/POS/servis
        'harga_beli' => 'decimal:2',
        'metode_harga_beli' => 'string',
        'harga_jual_retail' => 'decimal:2',
        'margin_persen' => 'decimal:2',
        'foto' => 'array', // [T-11]
        'kompatibilitas_hp' => 'array', // [T-11] terstruktur [{merk, model}]
        'is_ondemand' => 'boolean',
        'reorder_point' => 'integer',
        'min_stock' => 'integer',
        'max_stock' => 'integer',
    ];

    public function skuVariants(): HasMany
    {
        return $this->hasMany(SkuVariant::class)->orderBy('is_active', 'desc');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function kualitas(): BelongsTo
    {
        return $this->belongsTo(KualitasProduk::class, 'kualitas_id');
    }

    public function kategoriRelasi(): BelongsTo
    {
        return $this->belongsTo(KategoriProduk::class, 'kategori_id');
    }

    public function produkKompatibel(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'kompatibilitas_antar_produk',
            'produk_id',
            'kompatibel_produk_id'
        )->withPivot('catatan')->withTimestamps();
    }

    public function disubstitusiOleh(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'kompatibilitas_antar_produk',
            'kompatibel_produk_id',
            'produk_id'
        )->withPivot('catatan')->withTimestamps();
    }

    public function tipeHps(): BelongsToMany
    {
        return $this->belongsToMany(
            TipeHp::class,
            'kompatibilitas_produk_tipe_hp',
            'produk_id',
            'tipe_hp_id'
        );
    }

    public function channelMappings(): HasMany
    {
        return $this->hasMany(ChannelProductMapping::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function stokItems(): HasMany
    {
        return $this->hasMany(StokItem::class);
    }

    public function hargaTier(): HasMany
    {
        return $this->hasMany(HargaTier::class);
    }

    public function nomorSeris(): HasMany
    {
        return $this->hasMany(NomorSeri::class);
    }

    public function transaksiItems(): HasMany
    {
        return $this->hasMany(TransaksiItem::class);
    }

    /**
     * Normalisasi URL foto agar selalu adaptif terhadap domain, skema (HTTP/HTTPS),
     * dan port aktif, serta dapat memverifikasi keberadaan file di storage.
     */
    public static function formatFotoUrl(?string $url, bool $checkFileExists = false): ?string
    {
        if (empty($url)) {
            return null;
        }

        $trimmed = trim($url);
        if ($trimmed === '') {
            return null;
        }

        // Data URI atau blob (preview client-side)
        if (str_starts_with($trimmed, 'data:') || str_starts_with($trimmed, 'blob:')) {
            return $trimmed;
        }

        // Ekstrak path storage relatif jika URL merupakan link storage
        $relStoragePath = null;
        if (str_starts_with($trimmed, '/storage/')) {
            $relStoragePath = substr($trimmed, 9);
        } elseif (str_starts_with($trimmed, 'storage/')) {
            $relStoragePath = substr($trimmed, 8);
        } elseif (preg_match('#https?://[^/]+/storage/(.+)#i', $trimmed, $m)) {
            $relStoragePath = $m[1];
        } elseif (! str_starts_with($trimmed, 'http://') && ! str_starts_with($trimmed, 'https://') && ! str_starts_with($trimmed, '/')) {
            $relStoragePath = $trimmed;
        }

        if ($relStoragePath !== null) {
            $cleanPath = ltrim(urldecode($relStoragePath), '/');
            if ($checkFileExists && ! Storage::disk('public')->exists($cleanPath)) {
                return null;
            }

            return '/storage/'.$cleanPath;
        }

        // URL eksternal: upgrade http ke https jika halaman saat ini berjalan di https
        if (request()?->isSecure() && str_starts_with($trimmed, 'http://')) {
            return preg_replace('/^http:/i', 'https:', $trimmed);
        }

        return $trimmed;
    }

    /**
     * URL Foto Utama (WebP atau fallback ke gambar atau null).
     */
    public function getFotoUtamaAttribute(): ?string
    {
        if (is_array($this->foto) && count($this->foto) > 0) {
            foreach ($this->foto as $f) {
                if (is_array($f) && ! empty($f['is_primary']) && ! empty($f['url'])) {
                    return self::formatFotoUrl($f['url']);
                }
            }
            $pertama = $this->foto[0];
            if (is_array($pertama) && ! empty($pertama['url'])) {
                return self::formatFotoUrl($pertama['url']);
            }
            if (is_string($pertama)) {
                return self::formatFotoUrl($pertama);
            }
        }

        return self::formatFotoUrl($this->gambar ?: null);
    }

    /**
     * URL Thumbnail Foto (WebP thumb ultra-ringan 250px atau fallback).
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        if (is_array($this->foto) && count($this->foto) > 0) {
            foreach ($this->foto as $f) {
                if (is_array($f) && ! empty($f['is_primary'])) {
                    $target = $f['thumb'] ?? $f['url'] ?? null;

                    return self::formatFotoUrl($target);
                }
            }
            $pertama = $this->foto[0];
            if (is_array($pertama)) {
                $target = $pertama['thumb'] ?? $pertama['url'] ?? null;

                return self::formatFotoUrl($target);
            }
            if (is_string($pertama)) {
                return self::formatFotoUrl($pertama);
            }
        }

        return self::formatFotoUrl($this->gambar ?: null);
    }

    /**
     * Normalisasi Galeri Foto untuk UI Marketplace & Backoffice.
     *
     * @return array<int, array{url: string, thumb: string, path: ?string, thumb_path: ?string, is_primary: bool}>
     */
    public function getGaleriFotoAttribute(): array
    {
        $hasil = [];
        if (is_array($this->foto)) {
            foreach ($this->foto as $index => $item) {
                if (is_array($item) && ! empty($item['url'])) {
                    $url = self::formatFotoUrl($item['url']);
                    $thumb = self::formatFotoUrl($item['thumb'] ?? $item['url']);
                    if ($url) {
                        $hasil[] = [
                            'url' => $url,
                            'thumb' => $thumb ?? $url,
                            'path' => $item['path'] ?? null,
                            'thumb_path' => $item['thumb_path'] ?? null,
                            'is_primary' => (bool) ($item['is_primary'] ?? ($index === 0)),
                        ];
                    }
                } elseif (is_string($item) && $item !== '') {
                    $url = self::formatFotoUrl($item);
                    if ($url) {
                        $hasil[] = [
                            'url' => $url,
                            'thumb' => $url,
                            'path' => null,
                            'thumb_path' => null,
                            'is_primary' => $index === 0,
                        ];
                    }
                }
            }
        }

        if (empty($hasil) && $this->gambar) {
            $url = self::formatFotoUrl($this->gambar);
            if ($url) {
                $hasil[] = [
                    'url' => $url,
                    'thumb' => $url,
                    'path' => null,
                    'thumb_path' => null,
                    'is_primary' => true,
                ];
            }
        }

        return $hasil;
    }

    /**
     * Scope query pencarian optimal untuk ratusan ribu item.
     * Menggunakan subquery terindeks (uncorrelated) untuk menghindari N+1 subquery dan full scan per-row.
     * Mencari berdasarkan nama, barcode, SKU, brand/tipe hp kompatibel.
     */
    public function scopeCariPintar($query, ?string $q)
    {
        if (empty($q)) {
            return $query;
        }

        $term = trim($q);
        if ($term === '') {
            return $query;
        }

        return $query->where(function ($sub) use ($term) {
            // 1. Exact match barcode produk (B-Tree index unik)
            $sub->where('barcode', $term)
                // 2. Barcode & SKU varian produk (subquery terindeks, 1x eksekusi)
                ->orWhereIn('id', SkuVariant::query()
                    ->where('is_active', true)
                    ->where(function ($sq) use ($term) {
                        $sq->where('barcode', $term)
                            ->orWhere('sku', $term)
                            ->orWhere('sku', 'like', "%{$term}%");
                    })
                    ->select('produk_id')
                )
                // 3. Tipe HP kompatibel via tabel pivot terindeks
                ->orWhereIn('id', function ($pivotQuery) use ($term) {
                    $pivotQuery->select('kompatibilitas_produk_tipe_hp.produk_id')
                        ->from('kompatibilitas_produk_tipe_hp')
                        ->join('tipe_hp', 'tipe_hp.id', '=', 'kompatibilitas_produk_tipe_hp.tipe_hp_id')
                        ->where(function ($tq) use ($term) {
                            $tq->where('tipe_hp.merk', 'like', "%{$term}%")
                                ->orWhere('tipe_hp.model', 'like', "%{$term}%");
                        });
                });

            // 4. Pencarian teks nama, brand, model kompatibel, kategori, kondisi, satuan
            $words = array_values(array_filter(preg_split('/\s+/', $term), fn ($w) => strlen($w) > 0));
            if (count($words) > 1) {
                $sub->orWhere(function ($multiQ) use ($words) {
                    foreach ($words as $word) {
                        $multiQ->where(function ($wordQ) use ($word) {
                            $wordQ->where('nama', 'like', "%{$word}%")
                                ->orWhere('brand_kompatibel', 'like', "%{$word}%")
                                ->orWhere('model_kompatibel', 'like', "%{$word}%")
                                ->orWhere('kategori', 'like', "%{$word}%")
                                ->orWhere('kondisi', 'like', "%{$word}%")
                                ->orWhere('satuan', 'like', "%{$word}%")
                                ->orWhere('deskripsi', 'like', "%{$word}%")
                                ->orWhereHas('brand', fn ($bq) => $bq->where('nama', 'like', "%{$word}%"))
                                ->orWhereHas('kualitas', fn ($kq) => $kq->where('nama', 'like', "%{$word}%"))
                                ->orWhereHas('kategoriRelasi', fn ($cq) => $cq->where('nama', 'like', "%{$word}%"));
                        });
                    }
                });
            } else {
                $sub->orWhere('nama', 'like', "%{$term}%")
                    ->orWhere('brand_kompatibel', 'like', "%{$term}%")
                    ->orWhere('model_kompatibel', 'like', "%{$term}%")
                    ->orWhere('kategori', 'like', "%{$term}%")
                    ->orWhere('kondisi', 'like', "%{$term}%")
                    ->orWhere('satuan', 'like', "%{$term}%")
                    ->orWhere('deskripsi', 'like', "%{$term}%")
                    ->orWhereHas('brand', fn ($bq) => $bq->where('nama', 'like', "%{$term}%"))
                    ->orWhereHas('kualitas', fn ($kq) => $kq->where('nama', 'like', "%{$term}%"))
                    ->orWhereHas('kategoriRelasi', fn ($cq) => $cq->where('nama', 'like', "%{$term}%"));
            }
        });
    }

    /**
     * Urutkan produk berdasarkan ketersediaan stok (tersedia > habis) dan terlaris (total terjual terbanyak).
     * Memudahkan kasir menemukan fast-moving item yang ready stock dan membantu pelanggan toko online.
     */
    public function scopeUrutKetersediaanDanTerlaris($query, ?int $gudangId = null, ?int $cabangId = null)
    {
        $stokSubquery = StokItem::query()
            ->selectRaw('COALESCE(SUM(stok_items.jumlah), 0)')
            ->whereColumn('stok_items.produk_id', 'produk.id');

        if ($gudangId) {
            $stokSubquery->where('stok_items.gudang_id', $gudangId);
        } elseif ($cabangId) {
            $stokSubquery->whereIn('stok_items.gudang_id', function ($gq) use ($cabangId) {
                $gq->select('id')->from('gudang')->where('cabang_id', $cabangId);
            });
        }

        $terjualSubquery = TransaksiItem::query()
            ->selectRaw('COALESCE(SUM(transaksi_item.jumlah), 0)')
            ->whereColumn('transaksi_item.produk_id', 'produk.id');

        if (empty($query->getQuery()->columns)) {
            $query->select('produk.*');
        }

        $query->selectSub($stokSubquery, 'stok_katalog')
            ->selectSub($terjualSubquery, 'total_terjual');

        return $query
            ->orderByRaw('CASE WHEN ('.$stokSubquery->toSql().') > 0 THEN 1 ELSE 0 END DESC', $stokSubquery->getBindings())
            ->orderByDesc('total_terjual')
            ->orderByDesc('stok_katalog')
            ->orderBy('produk.id', 'asc');
    }
}
