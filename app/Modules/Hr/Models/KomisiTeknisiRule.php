<?php

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'cabang_id', 'jabatan_target', 'jenis', 'nominal', 'persen',
    'min_status_tiket', 'is_aktif',
])]
class KomisiTeknisiRule extends Model
{
    use LogsActivity;

    protected $table = 'komisi_teknisi_rules';

    protected $casts = ['nominal' => 'decimal:2', 'persen' => 'decimal:2', 'is_aktif' => 'boolean'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}
