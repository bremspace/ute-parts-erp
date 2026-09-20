<?php

namespace App\Modules\Omnichannel\Models;

use App\Modules\Wms\Models\Produk;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'channel_id', 'produk_id', 'gudang_id', 'channel_sku',
    'channel_item_id', 'status', 'error_message',
])]
class ChannelProductMapping extends Model
{
    protected $table = 'channel_product_mapping';

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }
}
