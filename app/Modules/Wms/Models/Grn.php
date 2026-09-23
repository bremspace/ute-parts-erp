<?php

namespace App\Modules\Wms\Models;

use App\Models\User;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * [F2-2] Goods Received Note. Data per-item disimpan di JSON item_qty_received
 * (produk_id, sku_variant_id, qty_po, qty_received, harga_beli, status).
 * [F1-4] Model kritis — wajib activity log.
 */
#[Fillable([
    'no_grn', 'po_id', 'cabang_id', 'gudang_id', 'user_id', 'status',
    'total_hpp', 'item_qty_received', 'catatan',
])]
class Grn extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'grn';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('GRN');
    }

    protected $casts = [
        'no_grn' => 'string',
        'total_hpp' => 'decimal:2',
        'item_qty_received' => 'array',
        'status' => 'string',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getTotalReceivedAttribute(): float
    {
        $total = 0;
        foreach ($this->item_qty_received ?? [] as $item) {
            $total += (float) ($item['qty_received'] ?? 0);
        }

        return $total;
    }
}
