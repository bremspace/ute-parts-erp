<?php

namespace App\Modules\Webhook\Models;

use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * [F3-5] Endpoint tujuan webhook outbound (G-16).
 * secret: cast encrypted at-rest + TAMPILAN selalu masked (maskedSecret()).
 */
#[Fillable([
    'nama', 'url', 'secret', 'events', 'is_aktif',
    'cabang_id', 'last_delivery_at', 'last_status',
])]
class WebhookEndpoint extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'webhook_endpoint';

    protected $casts = [
        'events' => 'array',
        'is_aktif' => 'boolean',
        'secret' => 'encrypted', // [T-19 pola] teks terenkripsi at-rest — kolom text, bukan json
        'last_delivery_at' => 'datetime',
    ];

    /**
     * Activity log (F1-4) — secret dibuang oleh
     * config('activitylog.default_except_attributes') yang memuat 'secret'.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Webhook endpoint');
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * Secret hanya boleh tampil masked — 4 karakter terakhir saja.
     */
    public function maskedSecret(): string
    {
        $secret = (string) $this->secret;

        return '••••••••'.substr($secret, -4);
    }
}
