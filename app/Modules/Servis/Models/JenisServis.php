<?php

namespace App\Modules\Servis\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'nama', 'kode', 'durasi_garansi_hari', 'kategori', 'estimasi_durasi', 'biaya_jasa',
    'batas_waktu_menit', 'peringatan_menit', 'auto_cancel_jika_overdue',
    'butuh_part', 'is_part_original', 'is_active', 'is_default',
])]
class JenisServis extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'jenis_servis';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Jenis Servis');
    }

    protected $casts = [
        'durasi_garansi_hari' => 'integer',
        'estimasi_durasi' => 'integer',
        'batas_waktu_menit' => 'integer',
        'peringatan_menit' => 'integer',
        'auto_cancel_jika_overdue' => 'boolean',
        'biaya_jasa' => 'float',
        'is_part_original' => 'boolean',
        'butuh_part' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public static function getDefault(): self
    {
        return static::firstOrCreate(
            ['kode' => 'SVC-UMUM'],
            [
                'nama' => 'Servis Umum (Software & Sistem)',
                'biaya_jasa' => 50000,
                'durasi_garansi_hari' => 30,
                'is_part_original' => false,
                'is_active' => true,
                'is_default' => true,
            ]
        );
    }

    protected static function booted(): void
    {
        static::deleting(function (self $model) {
            if ($model->is_default || $model->kode === 'SVC-UMUM') {
                throw new \DomainException("Jenis servis '{$model->nama}' adalah jenis servis default sistem dan tidak dapat dihapus.");
            }

            $default = static::getDefault();

            DB::table('tiket_servis')->where('jenis_servis_id', $model->id)->update([
                'jenis_servis_id' => $default->id,
            ]);

            DB::table('tiket_servis_estimasi_item')->where('jenis_servis_id', $model->id)->update([
                'jenis_servis_id' => $default->id,
            ]);
        });
    }

    public function tiketServis(): HasMany
    {
        return $this->hasMany(TiketServis::class);
    }
}
