<?php

namespace App\Modules\Wms\Controllers;

use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\ReturnPembelian;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Services\ReturnPembelianService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ReturnPembelianController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ReturnPembelianService $returnService
    ) {}

    public function index(Request $request)
    {
        $cabangId = session('cabang_id');

        $query = ReturnPembelian::with(['purchaseOrder', 'supplier', 'items.produk', 'user'])
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId));

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('no_return', 'like', "%{$term}%")
                    ->orWhereHas('purchaseOrder', fn ($q2) => $q2->where('no_po', 'like', "%{$term}%"))
                    ->orWhereHas('supplier', fn ($q2) => $q2->where('nama', 'like', "%{$term}%"));
            });
        }

        return $this->success($query->latest()->paginate(20), 'Daftar retur pembelian berhasil dimuat');
    }

    public function show(Request $request, $id)
    {
        $cabangId = session('cabang_id');
        $retur = ReturnPembelian::with(['purchaseOrder.items.produk', 'supplier', 'items.produk', 'user', 'gudang'])
            ->findOrFail($id);

        if ($cabangId && (int) $retur->cabang_id !== (int) $cabangId) {
            return $this->error('Retur bukan milik cabang aktif', 403);
        }

        return $this->success($retur, 'Detail retur pembelian berhasil dimuat');
    }

    public function store(Request $request)
    {
        $request->validate([
            'purchase_order_id' => 'required_without:supplier_id|nullable|exists:purchase_order,id',
            'supplier_id' => 'required_without:purchase_order_id|nullable|exists:supplier,id',
            'alasan' => 'required|string|max:500',
            'metode_pengembalian' => 'nullable|in:utang,kas,saldo',
            'items' => 'required_with:purchase_order_id|array',
            'items.*.purchase_order_item_id' => 'required_with:items|exists:purchase_order_item,id',
            'items.*.jumlah' => 'required_with:items|numeric|min:0.01',
            'items.*.sn' => 'nullable|array',
            'item_ids' => 'required_with:supplier_id|array',
            'item_ids.*' => 'integer|exists:return_penjualan_item,id',
        ]);

        $cabangId = session('cabang_id');

        try {
            if ($request->filled('supplier_id')) {
                $supplier = Supplier::findOrFail($request->supplier_id);
                $retur = $this->returnService->buatReturDariPoolSupplier(
                    $supplier,
                    $request->item_ids,
                    $request->alasan,
                    $request->input('metode_pengembalian', 'saldo'),
                    $cabangId,
                    null,
                    auth()->id()
                );
            } else {
                $po = PurchaseOrder::with('gudangTujuan')->findOrFail($request->purchase_order_id);

                if ($cabangId && $po->gudangTujuan && (int) $po->gudangTujuan->cabang_id !== (int) $cabangId) {
                    return $this->error('PO bukan milik cabang aktif', 403);
                }

                $retur = $this->returnService->buatRetur(
                    $po,
                    $request->items,
                    $request->alasan,
                    $request->input('metode_pengembalian', 'utang'),
                    auth()->id()
                );
            }

            return $this->success(
                $retur,
                $retur->status === 'selesai'
                    ? 'Retur pembelian berhasil diproses — stok & pembukuan terupdate'
                    : 'Retur pembelian diajukan — menunggu approval supervisor',
                201
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
