<?php

namespace App\Modules\Rbac\Models;

use Spatie\Activitylog\Models\Activity;

/**
 * [F1-4] Baris audit trail (tabel activity_log, package spatie/laravel-activitylog).
 * Dipilih lewat config('activitylog.activity_model').
 *
 * causerNama: "sistem" bila tidak ada user login (job queue / seed / webhook) —
 * bukan "null" supaya jelas di UI riwayat.
 */
class AktivitasLog extends Activity
{
    public function getCauserNamaAttribute(): string
    {
        return $this->causer?->name ?? 'sistem';
    }

    /** Label aksi Bahasa Indonesia (created → Dibuat, dst). */
    public function getAksiLabelAttribute(): string
    {
        return match ($this->event) {
            'created' => 'Dibuat',
            'updated' => 'Diperbarui',
            'deleted' => 'Dihapus',
            'restored' => 'Dipulihkan',
            default => (string) $this->event,
        };
    }
}
