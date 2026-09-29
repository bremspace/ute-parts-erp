<?php

namespace App\Modules\Wms\Controllers;

use App\Modules\Wms\Services\ProcurementService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProcurementController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ProcurementService $procurementService
    ) {}

    /**
     * [API: WMS-PROCUREMENT-01] Ambil daftar rekomendasi pengadaan stok.
     * Mengkombinasikan Analisis ABC, ROP, Min-Max, dan Modified JIT.
     */
    public function recommendations(Request $request): JsonResponse
    {
        $cabangId = $request->query('cabang_id') ? (int) $request->query('cabang_id') : session('cabang_id');

        $filters = [
            'abc_class' => $request->query('abc_class'),
            'is_ondemand' => $request->has('is_ondemand') && $request->query('is_ondemand') !== ''
                ? filter_var($request->query('is_ondemand'), FILTER_VALIDATE_BOOLEAN)
                : null,
            'only_reorder' => filter_var($request->query('only_reorder', false), FILTER_VALIDATE_BOOLEAN),
            'search' => $request->query('search'),
            'kategori' => $request->query('kategori'),
        ];

        $data = $this->procurementService->getProcurementRecommendations($cabangId, $filters);

        return $this->success($data, 'Rekomendasi pengadaan berhasil dimuat');
    }

    /**
     * [API: WMS-PROCUREMENT-02] Ringkasan metrik pengadaan (KPI & Breakdown).
     */
    public function summary(Request $request): JsonResponse
    {
        $cabangId = $request->query('cabang_id') ? (int) $request->query('cabang_id') : session('cabang_id');

        $summary = $this->procurementService->getProcurementSummary($cabangId);

        return $this->success($summary, 'Ringkasan pengadaan berhasil dimuat');
    }
}
