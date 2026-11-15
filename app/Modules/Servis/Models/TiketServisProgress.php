<?php

namespace App\Modules\Servis\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tiket_servis_id',
    'user_id',
    'persentase',
    'judul',
    'deskripsi',
    'foto',
    'tampilkan_ke_konsumen',
])]
class TiketServisProgress extends Model
{
    protected $table = 'tiket_servis_progress';

    protected $casts = [
        'persentase' => 'integer',
        'foto' => 'array',
        'tampilkan_ke_konsumen' => 'boolean',
    ];

    public function tiketServis(): BelongsTo
    {
        return $this->belongsTo(TiketServis::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * URL tampilan foto progress. Mendukung dua format:
     * - data URL base64 lama (dipakai apa adanya)
     * - path file di disk public (storage/app/public)
     */
    public static function urlFoto(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        if (str_starts_with($value, 'data:')) {
            return $value;
        }

        try {
            return \Illuminate\Support\Facades\Storage::disk('public')->url($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * @return array<int, string>
     */
    public function getFotoUrls(): array
    {
        return collect($this->foto ?? [])
            ->map(fn ($f) => self::urlFoto((string) $f))
            ->filter()
            ->values()
            ->all();
    }
}
