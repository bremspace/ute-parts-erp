<?php

namespace App\Modules\Wms\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['nama', 'keterangan', 'is_active', 'is_default'])]
class Brand extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Brand');
    }

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public static function getDefault(): self
    {
        return static::firstOrCreate(
            ['nama' => 'Tanpa Merek'],
            [
                'keterangan' => 'Brand default sistem',
                'is_active' => true,
                'is_default' => true,
            ]
        );
    }

    protected static function booted(): void
    {
        static::deleting(function (self $model) {
            if ($model->is_default || $model->nama === 'Tanpa Merek') {
                throw new \DomainException("Brand '{$model->nama}' adalah brand default sistem dan tidak dapat dihapus.");
            }

            $default = static::getDefault();

            // 1. Alihkan produk dengan brand_id ini ke brand default
            DB::table('produk')->where('brand_id', $model->id)->update([
                'brand_id' => $default->id,
            ]);

            // 2. Alihkan kualitas_produk yang terikat brand ini ke universal (null)
            DB::table('kualitas_produk')->where('brand_id', $model->id)->update([
                'brand_id' => null,
            ]);
        });
    }

    public function produk(): HasMany
    {
        return $this->hasMany(Produk::class);
    }

    public function kualitas(): HasMany
    {
        return $this->hasMany(KualitasProduk::class, 'brand_id');
    }
}
