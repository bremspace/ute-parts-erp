<?php

namespace App\Modules\Reseller\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'nama', 'kategori', 'tipe', 'nilai', 'is_active',
])]
class SkemaKomisi extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'skema_komisi';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Skema Komisi');
    }

    protected $casts = [
        'nilai' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function komisi()
    {
        return $this->hasMany(Komisi::class);
    }
}
