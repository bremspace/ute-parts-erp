<?php

namespace App\Modules\Wms\Models;

use App\Models\User;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['po_id', 'jumlah', 'dibayar_at', 'user_id', 'keterangan'])]
class PembayaranSupplier extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'pembayaran_supplier';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Pembayaran Supplier');
    }

    protected $casts = [
        'jumlah' => 'decimal:2',
        'dibayar_at' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
