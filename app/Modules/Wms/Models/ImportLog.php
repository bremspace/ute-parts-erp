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

    public function getStatusLabelAttribute(): string
    {
        if ($this->status === 'proses') {
            return 'Sedang Diproses';
        }
        if ($this->status === 'gagal' || ($this->total_baris > 0 && $this->sukses === 0)) {
            return 'Gagal Total';
        }
        if ($this->gagal > 0 && $this->sukses > 0) {
            return 'Diterima Sebagian';
        }

        return 'Diterima Sempurna';
    }

    public function getStatusBadgeClassAttribute(): string
    {
        if ($this->status === 'proses') {
            return 'bg-up-primary/20 text-up-primary border-up-primary/40';
        }
        if ($this->status === 'gagal' || ($this->total_baris > 0 && $this->sukses === 0)) {
            return 'bg-up-red/20 text-up-red border-up-red/40';
        }
        if ($this->gagal > 0 && $this->sukses > 0) {
            return 'bg-up-amber/20 text-up-amber border-up-amber/40';
        }

        return 'bg-up-mint/20 text-up-mint border-up-mint/40';
    }

    public function getCurrentSkuAttribute(): ?string
    {
        return is_array($this->detail) ? ($this->detail['current_sku'] ?? null) : null;
    }

    public function getCurrentNamaAttribute(): ?string
    {
        return is_array($this->detail) ? ($this->detail['current_nama'] ?? null) : null;
    }

    public function getCurrentBarisAttribute(): ?int
    {
        return is_array($this->detail) ? ($this->detail['current_baris'] ?? null) : null;
    }

    public function getDetailListAttribute(): array
    {
        if (! is_array($this->detail)) {
            return [];
        }

        if (isset($this->detail['items']) && is_array($this->detail['items'])) {
            return $this->detail['items'];
        }

        if (isset($this->detail[0]) && is_array($this->detail[0])) {
            return $this->detail;
        }

        return [];
    }

    public function getPeringatanListAttribute(): array
    {
        return array_values(array_filter($this->detail_list, fn ($d) => ! empty($d['warning'])));
    }

    public function getGagalListAttribute(): array
    {
        return array_values(array_filter($this->detail_list, fn ($d) => is_array($d) && ($d['status'] ?? '') === 'gagal'));
    }

    public function getSkipListAttribute(): array
    {
        return array_values(array_filter($this->detail_list, fn ($d) => is_array($d) && ($d['status'] ?? '') === 'skip'));
    }

    public function getBermasalahListAttribute(): array
    {
        return array_values(array_filter($this->detail_list, fn ($d) => is_array($d) && in_array($d['status'] ?? '', ['gagal', 'skip'], true)));
    }

    public function getGagalCountAttribute(): int
    {
        return count($this->gagal_list);
    }

    public function getSkipCountAttribute(): int
    {
        if (isset($this->detail['summary']['skip'])) {
            return (int) $this->detail['summary']['skip'];
        }

        if (isset($this->detail['skip'])) {
            return (int) $this->detail['skip'];
        }

        return count($this->skip_list);
    }
}
