<?php

namespace App\Modules\Wms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tipe', 'nama_file', 'total_baris', 'sukses', 'gagal', 'status', 'detail', 'user_id',
])]
class ImportLog extends Model
{
    protected $table = 'import_log';

    protected $casts = [
        'total_baris' => 'integer',
        'sukses' => 'integer',
        'gagal' => 'integer',
        'detail' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
