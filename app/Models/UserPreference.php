<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    protected $fillable = [
        'user_id',
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Helper to get user preference with fallback.
     */
    public static function getPref(int $userId, string $key, mixed $default = null): mixed
    {
        $pref = static::where('user_id', $userId)->where('key', $key)->first();

        return $pref ? $pref->value : $default;
    }

    /**
     * Helper to set user preference.
     */
    public static function setPref(int $userId, string $key, mixed $value): static
    {
        return static::updateOrCreate(
            ['user_id' => $userId, 'key' => $key],
            ['value' => $value]
        );
    }
}
