<?php

namespace App\Modules\Rbac\Services;

use App\Modules\Rbac\Models\AuditLog;

/**
 * Audit log (PRD §6): semua perubahan sensitif tercatat:
 * harga, stok manual, approval komisi, override servis, user, tier, COA.
 * who = auth()->id(), what = entitas+aksi+deskripsi, before/after = snapshot.
 */
class AuditService
{
    public function catat(
        string $entitas,
        string $aksi,
        ?int $entitasId,
        string $deskripsi,
        array|object|null $sebelum = null,
        array|object|null $sesudah = null
    ): AuditLog {
        return AuditLog::create([
            'user_id' => auth()->id() ?? auth('customer')->id(),
            'entitas' => $entitas,
            'aksi' => $aksi,
            'entitas_id' => $entitasId,
            'deskripsi' => $deskripsi,
            'sebelum' => $sebelum ? json_decode(json_encode($sebelum), true) : null,
            'sesudah' => $sesudah ? json_decode(json_encode($sesudah), true) : null,
            'ip' => request()->ip(),
        ]);
    }
}