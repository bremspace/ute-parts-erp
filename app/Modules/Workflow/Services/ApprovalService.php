<?php

namespace App\Modules\Workflow\Services;

use App\Modules\Workflow\Models\ApprovalRequest;
use App\Modules\Workflow\Models\ApprovalRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Validation\ValidationException;

class ApprovalService
{
    public function ajukan(string $entityType, int $entityId, ?string $cabangId, array $payload, int $requestedBy): ?ApprovalRequest
    {
        $amount = $payload['amount'] ?? null;

        $rule = ApprovalRule::where('entity_type', $entityType)
            ->where('is_aktif', true)
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->when(!$cabangId, fn ($q) => $q->whereNull('cabang_id'))
            ->when($amount !== null, fn ($q) => $q->where(function ($sub) use ($amount) {
                $sub->where('min_amount', '<=', $amount)
                    ->where(function ($inner) use ($amount) {
                        $inner->whereNull('max_amount')->orWhere('max_amount', '>=', $amount);
                    });
            }))
            ->first();

        if (! $rule) {
            return null; // tidak perlu approval
        }

        // cek apakah sudah ada request pending untuk entity yang sama
        $existing = ApprovalRequest::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('approval_rule_id', $rule->id)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($rule, $entityType, $entityId, $cabangId, $payload, $requestedBy) {
            $request = ApprovalRequest::create([
                'approval_rule_id' => $rule->id,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'cabang_id' => $cabangId,
                'payload_json' => $payload,
                'status' => 'pending',
                'requested_by' => $requestedBy,
                'approver_role' => $rule->approver_role,
            ]);

            // TODO: dispatch queue notification ke user dengan role $rule->approver_role
            return $request;
        });
    }

    public function proses(int $requestId, string $action, int $actionedBy, ?string $catatan = null): ApprovalRequest
    {
        $request = ApprovalRequest::findOrFail($requestId);

        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['msg' => 'Request sudah diproses']);
        }

        // validasi user memiliki approver_role snapshot ATAU permission approve-workflow
        $user = \App\Models\User::find($actionedBy);
        $hasRole = $user && $user->hasRole($request->approver_role);
        $hasPermission = $user && $user->hasPermissionTo('approve-workflow');

        if (! ($hasRole || $hasPermission)) {
            throw ValidationException::withMessages(['msg' => 'Anda tidak memiliki hak untuk menyetujui permintaan ini']);
        }

        $status = $action === 'approved' ? 'approved' : 'rejected';

        return DB::transaction(function () use ($request, $status, $actionedBy, $catatan) {
            $request->update([
                'status' => $status,
                'actioned_by' => $actionedBy,
                'actioned_at' => now(),
                'catatan' => $catatan,
            ]);

            // TODO: dispatch queue notification ke requested_by
            return $request->fresh();
        });
    }

    public function adaPending(string $entityType, int $entityId): bool
    {
        return ApprovalRequest::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('status', 'pending')
            ->exists();
    }
}