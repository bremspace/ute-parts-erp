<?php

namespace App\Modules\Report\Models;

use App\Models\User;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'name', 'source_model', 'columns', 'filters', 'group_by',
    'export_format', 'user_id', 'cabang_id', 'shared',
])]
class SavedReport extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'saved_reports';

    protected $casts = [
        'columns' => 'array',
        'filters' => 'array',
        'group_by' => 'array',
        'shared' => 'boolean',
    ];

    protected $fillable = [
        'name', 'source_model', 'columns', 'filters', 'group_by',
        'export_format', 'user_id', 'cabang_id', 'shared',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Saved Report');
    }
}
