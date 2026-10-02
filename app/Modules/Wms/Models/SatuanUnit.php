<?php

namespace App\Modules\Wms\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['kode', 'nama', 'is_active'])]
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
    ];
}
