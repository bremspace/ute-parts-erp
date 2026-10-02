<?php

namespace App\Modules\Servis\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['nama', 'kode', 'durasi_garansi_hari', 'kategori', 'estimasi_durasi', 'biaya_jasa', 'butuh_part', 'is_part_original', 'is_active'])]
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
        'biaya_jasa' => 'float',
        'is_part_original' => 'boolean',
        'butuh_part' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function tiketServis(): HasMany
    {
        return $this->hasMany(TiketServis::class);
    }
}
