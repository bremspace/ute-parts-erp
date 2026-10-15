<?php

namespace App\Modules\Wms\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'produk_id',
    'satuan_kode',
    'satuan_asal',
    'pengali',
    'faktor_konversi',
    'harga_beli',
    'harga_jual',
    'barcode',
    'is_satuan_beli',
    'is_satuan_jual',
    'urutan',
])]
class ProdukSatuanKonversi extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'produk_satuan_konversi';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Produk Satuan Konversi');
    }

    protected $casts = [
        'pengali' => 'decimal:4',
        'faktor_konversi' => 'decimal:4',
        'harga_beli' => 'decimal:2',
        'harga_jual' => 'decimal:2',
        'is_satuan_beli' => 'boolean',
        'is_satuan_jual' => 'boolean',
        'urutan' => 'integer',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    /**
     * Hitung teks representasi konversi rantai.
     * Cth: "1 karton = 10 box = 100 pcs"
     */
    public function getKeteranganKonversiAttribute(): string
    {
        $base = $this->produk?->satuan ?: 'pcs';
        $pengaliFormat = (float) $this->pengali + 0;
        $faktorFormat = (float) $this->faktor_konversi + 0;

        $text = "1 {$this->satuan_kode} = {$pengaliFormat} {$this->satuan_asal}";
        if (strtolower($this->satuan_asal) !== strtolower($base)) {
            $text .= " = {$faktorFormat} {$base}";
        }

        return $text;
    }
}
