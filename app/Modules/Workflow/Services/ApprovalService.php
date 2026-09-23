<?php

namespace App\Modules\Workflow\Services;

use App\Models\User;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Wms\Services\GrnService;
use App\Modules\Workflow\Models\ApprovalRequest;
use App\Modules\Workflow\Models\ApprovalRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class ApprovalService
{
    /**
     * [F1-1] Normalisasi action → status PRD: pending / disetujui / ditolak.
     * Alias lama 'approved'/'rejected' tetap diterima (konsumen API lama).
     */
    public static function normalisasiStatus(string $action): string
    {
        return in_array($action, ['approved', 'disetujui', 'setuju'], true)
            ? 'disetujui'
            : 'ditolak';
    }

    /**
     * Resolver user pemohon untuk auto-fire observer (tanpa konteks request HTTP).
     */
    public static function pemohon(?int $fallback = null): ?int
    {
        if ($id = auth()->id()) {
            return $id;
        }

        if ($fallback) {
            return $fallback;
        }

        return User::whereHas('roles', fn ($q) => $q->where('name', 'super-admin'))->orderBy('id')->value('id')
            ?? User::orderBy('id')->value('id');
    }

    public function ajukan(string $entityType, int $entityId, int|string|null $cabangId, array $payload, int $requestedBy): ?ApprovalRequest
    {
        $amount = isset($payload['amount']) ? (float) $payload['amount'] : null;

        // Level terendah yang cocok (multi-level: L1 diajukan dulu)
        $rule = $this->cariRule($entityType, $cabangId, $amount, 1);

        if (! $rule) {
            return null; // tidak perlu approval
        }

        // Idempoten: satu request per (entity, rule) — status apa pun, jangan duplikat
        $existing = ApprovalRequest::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('approval_rule_id', $rule->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($rule, $entityType, $entityId, $cabangId, $payload, $requestedBy) {
            $request = ApprovalRequest::create([
                'approval_rule_id' => $rule->id,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'cabang_id' => $cabangId !== null && $cabangId !== '' ? (int) $cabangId : null,
                'payload_json' => $payload,
                'status' => 'pending',
                'requested_by' => $requestedBy,
                'approver_role' => $rule->approver_role,
            ]);

            // Notifikasi approver via queue (PRD §5.2 — dilarang sync)
            $this->notifikasiRole(
                $rule->approver_role,
                "Permintaan approval {$entityType} #{$entityId}",
                'Amount Rp '.number_format((float) ($payload['amount'] ?? 0), 0, ',', '.')
                    ." menunggu persetujuan Anda (level {$rule->level}).",
                ['approval_request_id' => $request->id, 'entity_type' => $entityType, 'entity_id' => $entityId]
            );

            return $request;
        });
    }

    public function proses(int $requestId, string $action, int $actionedBy, ?string $catatan = null): ApprovalRequest
    {
        $request = ApprovalRequest::with('rule')->findOrFail($requestId);

        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['msg' => 'Request sudah diproses']);
        }

        // validasi user memiliki approver_role snapshot ATAU permission approve-workflow
        $user = User::find($actionedBy);
        $hasRole = $user && $user->hasRole($request->approver_role);
        $hasPermission = $user && $user->hasPermissionTo('approve-workflow');

        if (! ($hasRole || $hasPermission)) {
            throw ValidationException::withMessages(['msg' => 'Anda tidak memiliki hak untuk menyetujui permintaan ini']);
        }

        $status = self::normalisasiStatus($action);

        return DB::transaction(function () use ($request, $status, $actionedBy, $catatan) {
            $request->update([
                'status' => $status,
                'actioned_by' => $actionedBy,
                'actioned_at' => now(),
                'catatan' => $catatan,
            ]);

            // Multi-level: disetujui → buka request level berikutnya bila ada rule cocok
            $lanjut = false;
            if ($status === 'disetujui') {
                $lanjut = $this->lanjutLevelBerikut($request->fresh());
            }

            // Rantai selesai (semua level disetujui / ditolak) → efek entitas + kabari pemohon via queue
            if (! $lanjut) {
                $this->selesaikanEntity($request, $status, $actionedBy, $catatan);

                $this->notifikasiUser(
                    $request->requested_by,
                    "Approval {$request->entity_type} #{$request->entity_id} ".($status === 'disetujui' ? 'disetujui' : 'ditolak'),
                    $status === 'disetujui'
                        ? 'Permintaan approval Anda telah DISETUJUI.'
                        : 'Permintaan approval Anda DITOLAK'.($catatan ? ': '.$catatan : '.'),
                    ['approval_request_id' => $request->id]
                );
            }

            return $request->fresh();
        });
    }

    /**
     * [F2-2] Efek samping rantai final pada entitas — hook khusus GRN.
     * Disetujui → GrnService finalisasi (jurnal AP + stok masuk); ditolak → status ditolak.
     * Idempoten di sisi GrnService (hanya transisi dari status draft).
     */
    protected function selesaikanEntity(ApprovalRequest $request, string $status, int $actionedBy, ?string $catatan): void
    {
        if ($request->entity_type !== 'grn') {
            return;
        }

        $grnService = app(GrnService::class);

        if ($status === 'disetujui') {
            $grnService->setujuiGrn($request->entity_id, $actionedBy);
        } else {
            $grnService->tolakGrn($request->entity_id, $actionedBy, (string) $catatan);
        }
    }

    /**
     * [F1-1 multi-level] Setelah level N disetujui, buat request level berikutnya bila ada rule cocok.
     *
     * @return bool true bila rantai masih berlanjut (ada level berikutnya)
     */
    protected function lanjutLevelBerikut(ApprovalRequest $selesai): bool
    {
        $rule = $selesai->rule;

        if (! $rule) {
            return false;
        }

        $amount = isset($selesai->payload_json['amount']) ? (float) $selesai->payload_json['amount'] : null;
        $next = $this->cariRule($selesai->entity_type, $selesai->cabang_id, $amount, $rule->level + 1);

        if (! $next) {
            return false; // tidak ada level berikutnya → rantai selesai
        }

        $exists = ApprovalRequest::where('entity_type', $selesai->entity_type)
            ->where('entity_id', $selesai->entity_id)
            ->where('approval_rule_id', $next->id)
            ->first();

        if ($exists) {
            return $exists->status === 'pending' ? true : $this->lanjutLevelBerikut($exists);
        }

        $nextRequest = ApprovalRequest::create([
            'approval_rule_id' => $next->id,
            'entity_type' => $selesai->entity_type,
            'entity_id' => $selesai->entity_id,
            'cabang_id' => $selesai->cabang_id,
            'payload_json' => $selesai->payload_json,
            'status' => 'pending',
            'requested_by' => $selesai->requested_by,
            'approver_role' => $next->approver_role,
        ]);

        $this->notifikasiRole(
            $next->approver_role,
            "Permintaan approval {$selesai->entity_type} #{$selesai->entity_id}",
            'Amount Rp '.number_format((float) ($selesai->payload_json['amount'] ?? 0), 0, ',', '.')
                ." menunggu persetujuan Anda (level {$next->level}).",
            ['approval_request_id' => $nextRequest->id, 'entity_type' => $selesai->entity_type, 'entity_id' => $selesai->entity_id]
        );

        return true;
    }

    public function adaPending(string $entityType, int $entityId): bool
    {
        return ApprovalRequest::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('status', 'pending')
            ->exists();
    }

    /**
     * Cari rule aktif paling sesuai:
     * - range amount cocok (min_amount ≤ amount ≤ max_amount, null = tanpa batas)
     * - rule cabang spesifik menang atas rule global; tanpa cabang → hanya rule global
     * - level terendah ≥ $minLevel (untuk multi-level chain)
     */
    protected function cariRule(string $entityType, int|string|null $cabangId, ?float $amount, int $minLevel): ?ApprovalRule
    {
        $cabangKey = $cabangId !== null && $cabangId !== '' ? (int) $cabangId : null;

        return ApprovalRule::where('entity_type', $entityType)
            ->where('is_aktif', true)
            ->where('level', '>=', $minLevel)
            ->when(
                $cabangKey !== null,
                fn ($q) => $q->where(fn ($sub) => $sub->where('cabang_id', $cabangKey)->orWhereNull('cabang_id')),
                fn ($q) => $q->whereNull('cabang_id')
            )
            ->when($amount !== null, fn ($q) => $q->where(function ($sub) use ($amount) {
                $sub->where(fn ($w) => $w->whereNull('min_amount')->orWhere('min_amount', '<=', $amount))
                    ->where(fn ($w) => $w->whereNull('max_amount')->orWhere('max_amount', '>=', $amount));
            }))
            ->orderBy('level')
            ->orderByRaw('(cabang_id IS NULL)') // rule cabang spesifik dulu per level
            ->first();
    }

    /**
     * Notifikasi in-app ke semua pemegang role approver — via NotificationService (queue).
     */
    protected function notifikasiRole(string $role, string $judul, string $konten, array $payload = []): void
    {
        if (! Role::where('name', $role)->exists()) {
            return;
        }

        foreach (User::role($role)->get() as $user) {
            $this->kirimInapp($user, $judul, $konten, $payload);
        }
    }

    /**
     * Notifikasi in-app ke satu user (pemohon) — via NotificationService (queue).
     */
    protected function notifikasiUser(?int $userId, string $judul, string $konten, array $payload = []): void
    {
        if (! $userId) {
            return;
        }

        $user = User::find($userId);

        if ($user) {
            $this->kirimInapp($user, $judul, $konten, $payload);
        }
    }

    protected function kirimInapp(User $user, string $judul, string $konten, array $payload = []): void
    {
        // Queue (database driver + Supervisor) — PRD §5.2, dilarang dispatch sync
        app(NotificationService::class)->kirim(
            'inapp',
            $user->email,
            $judul,
            $konten,
            $payload + ['user_id' => $user->id]
        );
    }
}
