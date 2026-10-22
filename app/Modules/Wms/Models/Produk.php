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
    'brand_id', 'kualitas_id', 'supplier_id',
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
                $base = Str::limit(Str::slug($model->nama), 180, '') ?: 'produk';
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
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

    public function satuanKonversi(): HasMany
    {
        return $this->hasMany(ProdukSatuanKonversi::class)->orderBy('urutan')->orderBy('id');
    }

    /**
     * Daftar seluruh opsi satuan produk (satuan dasar + konversi) beserta faktor konversi ke satuan dasar.
     */
    public function getDaftarSatuan(): array
    {
        $base = $this->satuan ?: 'pcs';
        $list = [
            [
                'satuan_kode' => $base,
                'satuan_asal' => $base,
                'pengali' => 1.0,
                'faktor_konversi' => 1.0,
                'is_dasar' => true,
                'label' => "{$base} (Dasar)",
                'keterangan' => "1 {$base} (satuan dasar)",
                'harga_beli' => (float) $this->harga_beli,
                'harga_jual' => (float) $this->harga_jual_retail,
                'barcode' => $this->barcode,
            ],
        ];

        foreach ($this->satuanKonversi as $k) {
            $fk = (float) $k->faktor_konversi ?: 1.0;
            $hb = $k->harga_beli !== null ? (float) $k->harga_beli : round((float) $this->harga_beli * $fk, 2);
            $hj = $k->harga_jual !== null ? (float) $k->harga_jual : round((float) $this->harga_jual_retail * $fk, 2);

            $keterangan = "1 {$k->satuan_kode} = ".((float) $k->pengali + 0)." {$k->satuan_asal}";
            if (strtolower($k->satuan_asal) !== strtolower($base)) {
                $keterangan .= ' = '.($fk + 0)." {$base}";
            }

            $list[] = [
                'id' => $k->id,
                'satuan_kode' => $k->satuan_kode,
                'satuan_asal' => $k->satuan_asal,
                'pengali' => (float) $k->pengali,
                'faktor_konversi' => $fk,
                'is_dasar' => false,
                'label' => "{$k->satuan_kode} ({$keterangan})",
                'keterangan' => $keterangan,
                'harga_beli' => $hb,
                'harga_jual' => $hj,
                'barcode' => $k->barcode,
            ];
        }

        return $list;
    }

    public function konversiKeBaseUnit(float $jumlah, string $satuanKode): float
    {
        $base = $this->satuan ?: 'pcs';
        if (strtolower(trim($satuanKode)) === strtolower(trim($base))) {
            return $jumlah;
        }

        $konv = $this->satuanKonversi()->where('satuan_kode', $satuanKode)->first();
        if ($konv && (float) $konv->faktor_konversi > 0) {
            return $jumlah * (float) $konv->faktor_konversi;
        }

        return $jumlah;
    }

    public function konversiDariBaseUnit(float $jumlahBase, string $satuanKode): float
    {
        $base = $this->satuan ?: 'pcs';
        if (strtolower(trim($satuanKode)) === strtolower(trim($base))) {
            return $jumlahBase;
        }

        $konv = $this->satuanKonversi()->where('satuan_kode', $satuanKode)->first();
        if ($konv && (float) $konv->faktor_konversi > 0) {
            return $jumlahBase / (float) $konv->faktor_konversi;
        }

        return $jumlahBase;
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

    public function stokLogs(): HasMany
    {
        return $this->hasMany(StokLog::class);
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

        $words = array_values(array_filter(preg_split('/\s+/', $term), fn ($w) => strlen($w) > 0));

        // Pre-fetch matching foreign keys dari master data agar pencarian instan tanpa correlated subquery per-row
        $matchingBrandIds = Brand::query()
            ->where(function ($bq) use ($term, $words) {
                $bq->where('nama', 'like', "%{$term}%");
                foreach ($words as $w) {
                    $bq->orWhere('nama', 'like', "%{$w}%");
                }
            })
            ->pluck('id')
            ->all();

        $matchingKualitasIds = KualitasProduk::query()
            ->where(function ($kq) use ($term, $words) {
                $kq->where('nama', 'like', "%{$term}%");
                foreach ($words as $w) {
                    $kq->orWhere('nama', 'like', "%{$w}%");
                }
            })
            ->pluck('id')
            ->all();

        $matchingKategoriIds = KategoriProduk::query()
            ->where(function ($cq) use ($term, $words) {
                $cq->where('nama', 'like', "%{$term}%");
                foreach ($words as $w) {
                    $cq->orWhere('nama', 'like', "%{$w}%");
                }
            })
            ->pluck('id')
            ->all();

        $matchingSupplierIds = Supplier::query()
            ->where(function ($sq) use ($term, $words) {
                $sq->where('nama', 'like', "%{$term}%");
                foreach ($words as $w) {
                    $sq->orWhere('nama', 'like', "%{$w}%");
                }
            })
            ->pluck('id')
            ->all();

        return $query->where(function ($sub) use ($term, $words, $matchingBrandIds, $matchingKualitasIds, $matchingKategoriIds, $matchingSupplierIds) {
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

            // 4. Pencarian teks nama, brand, model kompatibel, kategori, kondisi, satuan, supplier
            if (count($words) > 1) {
                $sub->orWhere(function ($multiQ) use ($words, $matchingBrandIds, $matchingKualitasIds, $matchingKategoriIds, $matchingSupplierIds) {
                    foreach ($words as $word) {
                        $multiQ->where(function ($wordQ) use ($word, $matchingBrandIds, $matchingKualitasIds, $matchingKategoriIds, $matchingSupplierIds) {
                            $wordQ->where('nama', 'like', "%{$word}%")
                                ->orWhere('brand_kompatibel', 'like', "%{$word}%")
                                ->orWhere('model_kompatibel', 'like', "%{$word}%")
                                ->orWhere('kompatibilitas_hp', 'like', "%{$word}%")
                                ->orWhere('kategori', 'like', "%{$word}%")
                                ->orWhere('kondisi', 'like', "%{$word}%")
                                ->orWhere('satuan', 'like', "%{$word}%")
                                ->orWhere('deskripsi', 'like', "%{$word}%");

                            if (! empty($matchingBrandIds)) {
                                $wordQ->orWhereIn('brand_id', $matchingBrandIds);
                            }
                            if (! empty($matchingKualitasIds)) {
                                $wordQ->orWhereIn('kualitas_id', $matchingKualitasIds);
                            }
                            if (! empty($matchingKategoriIds)) {
                                $wordQ->orWhereIn('kategori_id', $matchingKategoriIds);
                            }
                            if (! empty($matchingSupplierIds)) {
                                $wordQ->orWhereIn('supplier_id', $matchingSupplierIds);
                            }
                        });
                    }
                });
            } else {
                $sub->orWhere('nama', 'like', "%{$term}%")
                    ->orWhere('brand_kompatibel', 'like', "%{$term}%")
                    ->orWhere('model_kompatibel', 'like', "%{$term}%")
                    ->orWhere('kompatibilitas_hp', 'like', "%{$term}%")
                    ->orWhere('kategori', 'like', "%{$term}%")
                    ->orWhere('kondisi', 'like', "%{$term}%")
                    ->orWhere('satuan', 'like', "%{$term}%")
                    ->orWhere('deskripsi', 'like', "%{$term}%");

                if (! empty($matchingBrandIds)) {
                    $sub->orWhereIn('brand_id', $matchingBrandIds);
                }
                if (! empty($matchingKualitasIds)) {
                    $sub->orWhereIn('kualitas_id', $matchingKualitasIds);
                }
                if (! empty($matchingKategoriIds)) {
                    $sub->orWhereIn('kategori_id', $matchingKategoriIds);
                }
                if (! empty($matchingSupplierIds)) {
                    $sub->orWhereIn('supplier_id', $matchingSupplierIds);
                }
            }
        });
    }

    /**
     * Urutkan hasil pencarian berdasarkan skor kecocokan tertinggi (relevance-based ranking).
     * Exact barcode (250) > Exact SKU (220) > Exact nama (200) > Prefix nama (150)
     * > Frasa penuh nama (100) > Brand/Model exact (80) > Brand/Model like (50)
     * > Word matches (30/15 per kata) > Ready stock boost (+10).
     */
    public function scopeUrutkanRelevansi($query, ?string $q)
    {
        if (empty($q)) {
            return $query;
        }

        $term = trim($q);
        if ($term === '') {
            return $query;
        }

        $words = array_values(array_filter(preg_split('/\s+/', $term), fn ($w) => strlen($w) > 0));

        $cases = [];
        $bindings = [];

        // 1. Exact Barcode Match (+250)
        $cases[] = 'CASE WHEN produk.barcode = ? THEN 250 ELSE 0 END';
        $bindings[] = $term;

        // 2. Exact SKU Varian Match (+220)
        $cases[] = 'CASE WHEN EXISTS (SELECT 1 FROM sku_variants WHERE sku_variants.produk_id = produk.id AND sku_variants.sku = ?) THEN 220 ELSE 0 END';
        $bindings[] = $term;

        // 3. Exact Nama Match (+200)
        $cases[] = 'CASE WHEN LOWER(produk.nama) = LOWER(?) THEN 200 ELSE 0 END';
        $bindings[] = $term;

        // 4. Nama Awalan Sama Persis (+150)
        $cases[] = 'CASE WHEN LOWER(produk.nama) LIKE LOWER(?) THEN 150 ELSE 0 END';
        $bindings[] = $term.'%';

        // 5. Nama Mengandung Seluruh Frasa (+100)
        $cases[] = 'CASE WHEN LOWER(produk.nama) LIKE LOWER(?) THEN 100 ELSE 0 END';
        $bindings[] = '%'.$term.'%';

        // 6. Brand atau Model Kompatibel Cocok Persis (+80)
        $cases[] = 'CASE WHEN LOWER(produk.brand_kompatibel) = LOWER(?) OR LOWER(produk.model_kompatibel) = LOWER(?) THEN 80 ELSE 0 END';
        $bindings[] = $term;
        $bindings[] = $term;

        // 7. Brand atau Model Kompatibel Mengandung Frasa (+50)
        $cases[] = 'CASE WHEN LOWER(produk.brand_kompatibel) LIKE LOWER(?) OR LOWER(produk.model_kompatibel) LIKE LOWER(?) THEN 50 ELSE 0 END';
        $bindings[] = '%'.$term.'%';
        $bindings[] = '%'.$term.'%';

        // 7b. Presisi Kombinasi Merk HP & Model HP pada relasi Tipe HP (+110)
        if (count($words) > 1) {
            $cases[] = 'CASE WHEN EXISTS (SELECT 1 FROM tipe_hp JOIN kompatibilitas_produk_tipe_hp ON kompatibilitas_produk_tipe_hp.tipe_hp_id = tipe_hp.id WHERE kompatibilitas_produk_tipe_hp.produk_id = produk.id AND LOWER(tipe_hp.merk) LIKE LOWER(?) AND LOWER(tipe_hp.model) LIKE LOWER(?)) THEN 110 ELSE 0 END';
            $bindings[] = '%'.$words[0].'%';
            $bindings[] = '%'.$words[count($words) - 1].'%';
        }

        // 8. Kecocokan Kata Per Kata (Nama +30 per kata, Model/Brand +15 per kata)
        if (count($words) > 1) {
            foreach ($words as $w) {
                $cases[] = 'CASE WHEN LOWER(produk.nama) LIKE LOWER(?) THEN 30 ELSE 0 END';
                $bindings[] = '%'.$w.'%';

                $cases[] = 'CASE WHEN LOWER(produk.brand_kompatibel) LIKE LOWER(?) OR LOWER(produk.model_kompatibel) LIKE LOWER(?) THEN 15 ELSE 0 END';
                $bindings[] = '%'.$w.'%';
                $bindings[] = '%'.$w.'%';
            }
        }

        // 9. Prioritas Produk yang Memiliki Stok Fisik (+10)
        $cases[] = 'CASE WHEN EXISTS (SELECT 1 FROM stok_items WHERE stok_items.produk_id = produk.id AND stok_items.jumlah > 0) THEN 10 ELSE 0 END';

        $sql = '('.implode(' + ', $cases).')';

        return $query->orderByRaw($sql.' DESC', $bindings);
    }

    /**
     * Urutkan hasil berdasarkan prioritas kompatibilitas saat filter Tipe HP aktif tanpa search input:
     * 1. Exact primary model (model_kompatibel cocok persis) -> paling atas (misal semua LCD A11 dari brand OG, Braderpart, Crown)
     * 2. Nama memuat model kompatibel -> urutan kedua
     * 3. Kompatibel silang / multi-kompatibel (lewat relasi tipeHps, kompatibilitas_antar_produk, atau JSON) -> tepat di bawahnya (misal LCD A15 yang kompatibel A11)
     */
    public function scopeUrutkanKompatibilitas($query, string $model, ?string $merk = null)
    {
        $targetModel = trim($model);
        if ($targetModel === '') {
            return $query;
        }

        $targetMerk = $merk ? trim($merk) : null;
        $cases = [];
        $bindings = [];

        // 1. Model Utama Cocok Persis (+200)
        $cases[] = 'CASE WHEN LOWER(produk.model_kompatibel) = LOWER(?) THEN 200 ELSE 0 END';
        $bindings[] = $targetModel;

        // 2. Nama Produk Mengandung Model (+100)
        $cases[] = 'CASE WHEN LOWER(produk.nama) LIKE LOWER(?) THEN 100 ELSE 0 END';
        $bindings[] = '%'.$targetModel.'%';

        // 3. Relasi Tipe HP Cocok (+50)
        if ($targetMerk) {
            $cases[] = 'CASE WHEN EXISTS (SELECT 1 FROM tipe_hp JOIN kompatibilitas_produk_tipe_hp ON kompatibilitas_produk_tipe_hp.tipe_hp_id = tipe_hp.id WHERE kompatibilitas_produk_tipe_hp.produk_id = produk.id AND LOWER(tipe_hp.merk) = LOWER(?) AND LOWER(tipe_hp.model) = LOWER(?)) THEN 50 ELSE 0 END';
            $bindings[] = $targetMerk;
            $bindings[] = $targetModel;
        } else {
            $cases[] = 'CASE WHEN EXISTS (SELECT 1 FROM tipe_hp JOIN kompatibilitas_produk_tipe_hp ON kompatibilitas_produk_tipe_hp.tipe_hp_id = tipe_hp.id WHERE kompatibilitas_produk_tipe_hp.produk_id = produk.id AND LOWER(tipe_hp.model) = LOWER(?)) THEN 50 ELSE 0 END';
            $bindings[] = $targetModel;
        }

        // 4. Kompatibilitas Antar Produk / Substitusi Silang (+40)
        $cases[] = 'CASE WHEN EXISTS (SELECT 1 FROM kompatibilitas_antar_produk kap JOIN produk p_sub ON kap.kompatibel_produk_id = p_sub.id WHERE kap.produk_id = produk.id AND (LOWER(p_sub.model_kompatibel) = LOWER(?) OR LOWER(p_sub.nama) LIKE LOWER(?))) THEN 40 ELSE 0 END';
        $bindings[] = $targetModel;
        $bindings[] = '%'.$targetModel.'%';

        $cases[] = 'CASE WHEN EXISTS (SELECT 1 FROM kompatibilitas_antar_produk kap JOIN produk p_sub ON kap.produk_id = p_sub.id WHERE kap.kompatibel_produk_id = produk.id AND (LOWER(p_sub.model_kompatibel) = LOWER(?) OR LOWER(p_sub.nama) LIKE LOWER(?))) THEN 40 ELSE 0 END';
        $bindings[] = $targetModel;
        $bindings[] = '%'.$targetModel.'%';

        // 5. JSON Kompatibilitas HP (+30)
        $cases[] = 'CASE WHEN LOWER(produk.kompatibilitas_hp) LIKE LOWER(?) THEN 30 ELSE 0 END';
        $bindings[] = '%'.$targetModel.'%';

        $sql = '('.implode(' + ', $cases).')';

        return $query->orderByRaw($sql.' DESC', $bindings);
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
