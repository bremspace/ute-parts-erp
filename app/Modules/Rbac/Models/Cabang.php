<?php

namespace App\Modules\Rbac\Models;

use App\Models\User;
use App\Modules\Rbac\Traits\CatatAktivitas;
use App\Modules\Wms\Models\Gudang;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['nama', 'alamat', 'telepon', 'kode', 'is_active'])]
class Cabang extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'cabang';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Cabang');
    }

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function gudang(): HasMany
    {
        return $this->hasMany(Gudang::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_cabang')
            ->withPivot('is_default')
            ->withTimestamps();
    }
}
