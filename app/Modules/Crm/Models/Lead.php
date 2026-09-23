<?php

namespace App\Modules\Crm\Models;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cabang_id', 'sumber', 'stage', 'nama', 'telepon', 'email',
    'nilai_estimasi', 'assigned_to', 'catatan', 'lost_reason', 'pelanggan_id',
    'won_at', 'lost_at',
])]
class Lead extends Model
{
    protected $table = 'leads';

    protected $casts = [
        'nilai_estimasi' => 'decimal:2',
        'won_at' => 'datetime',
        'lost_at' => 'datetime',
    ];

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class);
    }

    /**
     * Scope to current cabang (for multi-branch scoping).
     */
    public function scopeForCabang($query, ?int $cabangId = null)
    {
        $cabangId = $cabangId ?? session('cabang_aktif_id');

        return $query->where('cabang_id', $cabangId);
    }

    /**
     * Check if lead is in won stage.
     */
    public function isWon(): bool
    {
        return $this->stage === 'won';
    }

    /**
     * Check if lead is in lost stage.
     */
    public function isLost(): bool
    {
        return $this->stage === 'lost';
    }

    /**
     * Check if lead can be converted to customer.
     */
    public function canConvert(): bool
    {
        return $this->stage === 'won' && ! $this->pelanggan_id;
    }

    /**
     * Get stage label for display.
     */
    public function getStageLabelAttribute(): string
    {
        return match ($this->stage) {
            'baru' => 'Baru',
            'kontak' => 'Kontak',
            'kualifikasi' => 'Kualifikasi',
            'negosiasi' => 'Negosiasi',
            'won' => 'Menang',
            'lost' => 'Kalah',
            default => $this->stage,
        };
    }

    /**
     * Get stage color for badge.
     */
    public function getStageColorAttribute(): string
    {
        return match ($this->stage) {
            'baru' => 'ink',
            'kontak' => 'primary',
            'kualifikasi' => 'mint',
            'negosiasi' => 'amber',
            'won' => 'mint',
            'lost' => 'red',
            default => 'ink',
        };
    }

    /**
     * Get source label for display.
     */
    public function getSumberLabelAttribute(): string
    {
        return match ($this->sumber) {
            'walkin' => 'Walk-in',
            'phone' => 'Telepon',
            'website' => 'Website',
            'referral' => 'Referral',
            'social_media' => 'Media Sosial',
            'marketplace' => 'Marketplace',
            default => ucfirst($this->sumber),
        };
    }

    /**
     * Get stage label from static stage string.
     */
    public static function getStageLabelFromStageStatic(string $stage): string
    {
        return match ($stage) {
            'baru' => 'Baru',
            'kontak' => 'Kontak',
            'kualifikasi' => 'Kualifikasi',
            'negosiasi' => 'Negosiasi',
            'won' => 'Menang',
            'lost' => 'Kalah',
            default => $stage,
        };
    }

    /**
     * Get stage label from stage string (instance method).
     */
    public function getStageLabelFromStage(string $stage): string
    {
        return self::getStageLabelFromStageStatic($stage);
    }
}
