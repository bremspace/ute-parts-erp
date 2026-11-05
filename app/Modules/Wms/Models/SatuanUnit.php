<?php

namespace App\Modules\Wms\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['kode', 'nama', 'is_active', 'is_default'])]
class SatuanUnit extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'satuan_unit';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Satuan Unit');
    }

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public static function getDefault(): self
    {
        return static::firstOrCreate(
            ['kode' => 'pcs'],
            [
                'nama' => 'Pcs',
                'is_active' => true,
                'is_default' => true,
            ]
        );
    }

    protected static function booted(): void
    {
        static::deleting(function (self $model) {
            if ($model->is_default || strtolower($model->kode) === 'pcs') {
                throw new \DomainException("Satuan '{$model->kode}' adalah satuan default sistem dan tidak dapat dihapus.");
            }

            $default = static::getDefault();

            // 1. Alihkan satuan produk & satuan_beli
            DB::table('produk')->where('satuan', $model->kode)->update([
                'satuan' => $default->kode,
            ]);

            DB::table('produk')->where('satuan_beli', $model->kode)->update([
                'satuan_beli' => $default->kode,
            ]);

            // 2. Hapus baris konversi dengan satuan_kode ini
            if (Schema::hasTable('produk_satuan_konversi')) {
                DB::table('produk_satuan_konversi')->where('satuan_kode', $model->kode)->delete();
            }
        });
    }
}
