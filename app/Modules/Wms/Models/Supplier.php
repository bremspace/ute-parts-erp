<?php

namespace App\Modules\Wms\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['nama', 'kontak', 'telepon', 'alamat', 'termin_hari', 'is_active'])]
class Supplier extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'supplier';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Supplier');
    }

    protected $casts = [
        'termin_hari' => 'integer',
        'is_active' => 'boolean',
    ];

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
