<?php

namespace App\Modules\Workflow\Controllers;

use App\Modules\Workflow\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ApprovalController
{
    public function ajukan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => 'required|string|in:po,retur,retur_pembelian,diskon',
            'entity_id' => 'required|integer',
            'payload' => 'required|array',
            'payload.amount' => 'required|numeric|min:0',
        ]);

        $cabangId = session('cabang_id');
        $requestedBy = $request->user()->id;

        $service = app(ApprovalService::class);
        $approvalRequest = $service->ajukan(
            $validated['entity_type'],
            $validated['entity_id'],
            $cabangId,
            $validated['payload'],
            $requestedBy
        );

        if (! $approvalRequest) {
            return response()->json([
                'success' => true,
                'data' => ['message' => 'Tidak ada aturan approval yang sesuai untuk permintaan ini.'],
                'approval_required' => false,
            ], 200);
        }

        return response()->json([
            'success' => true,
            'data' => $approvalRequest,
            'approval_required' => true,
        ], 200);
    }

    public function proses(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:approved,rejected,disetujui,ditolak',
            'catatan' => 'nullable|string|max:255',
        ]);

        $actionedBy = $request->user()->id;

        try {
            $service = app(ApprovalService::class);
            // Service menormalisasi action (approved/disetujui → 'disetujui', rejected/ditolak → 'ditolak')
            $approvalRequest = $service->proses(
                $id,
                $validated['action'],
                $actionedBy,
                $validated['catatan'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $approvalRequest,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function adaPending(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => 'required|string|in:po,retur,retur_pembelian,diskon',
            'entity_id' => 'required|integer',
        ]);

        $service = app(ApprovalService::class);
        $ada = $service->adaPending($validated['entity_type'], $validated['entity_id']);

        return response()->json([
            'success' => true,
            'data' => ['ada_pending' => $ada],
        ], 200);
    }
}
