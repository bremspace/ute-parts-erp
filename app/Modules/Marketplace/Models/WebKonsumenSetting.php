<?php

namespace App\Modules\Marketplace\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebKonsumenSetting extends Model
{
    protected $table = 'web_konsumen_settings';

    protected $fillable = [
        'group_key',
        'setting_key',
        'setting_value',
        'value_type',
        'deskripsi',
        'updated_by',
    ];

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Parse setting_value ke tipe data asli.
     */
    public function getParsedValue(): mixed
    {
        return match ($this->value_type) {
            'boolean' => filter_var($this->setting_value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->setting_value,
            'json' => json_decode($this->setting_value ?: '[]', true),
            default => $this->setting_value,
        };
    }
}
