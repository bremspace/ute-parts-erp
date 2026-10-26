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
        if ($this->value_type === 'json' || in_array($this->setting_key, ['hero_features', 'hero_stats', 'allowed_filters', 'social_links'], true)) {
            if (is_array($this->setting_value)) {
                return $this->setting_value;
            }
            $decoded = json_decode((string) $this->setting_value, true);

            return is_array($decoded) ? $decoded : [];
        }

        if ($this->value_type === 'boolean') {
            return filter_var($this->setting_value, FILTER_VALIDATE_BOOLEAN);
        }

        if ($this->value_type === 'integer') {
            return (int) $this->setting_value;
        }

        // Defensive: bila value diawali '[' atau '{', periksa apakah valid JSON array/object
        $val = (string) $this->setting_value;
        if ($val !== '' && ($val[0] === '[' || $val[0] === '{')) {
            $decoded = json_decode($val, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return $this->setting_value;
    }
}
