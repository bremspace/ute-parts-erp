<?php

namespace App\Modules\Pos\Controllers;

use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Services\ReturnPenjualanService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ReturnPenjualanController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ReturnPenjualanService $returnService
    ) {}

    public function index(Request $request)
    {
        $cabangId = session('cabang_id');

        $query = ReturnPenjualan::with(['transaksi', 'pelanggan', 'items.produk', 'user'])
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId));

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('no_return', 'like', "%{$term}%")
                    ->orWhereHas('transaksi', fn ($q2) => $q2->where('no_transaksi', 'like', "%{$term}%"))
                    ->orWhereHas('pelanggan', fn ($q2) => $q2->where('nama', 'like', "%{$term}%"));
            });
        }

        return $this->success($query->latest()->paginate(20), 'Daftar retur penjualan berhasil dimuat');
    }

    public function show(Request $request, $id)
    {
        $cabangId = session('cabang_id');
        $retur = ReturnPenjualan::with(['transaksi.items.produk', 'pelanggan', 'items.produk', 'user', 'gudang'])
            ->findOrFail($id);

        if ($cabangId && (int) $retur->cabang_id !== (int) $cabangId) {
            return $this->error('Retur bukan milik cabang aktif', 403);
        }

        return $this->success($retur, 'Detail retur penjualan berhasil dimuat');
    }

    public function store(Request $request)
    {
        $request->validate([
            'transaksi_id' => 'required|exists:transaksi,id',
            'alasan' => 'required|string|max:500',
            'metode_pengembalian' => 'nullable|in:kas,piutang,saldo',
            'items' => 'required|array|min:1',
            'items.*.transaksi_item_id' => 'required|exists:transaksi_item,id',
            'items.*.jumlah' => 'required|numeric|min:0.01',
            'items.*.sn' => 'nullable|array',
        ]);

        $transaksi = Transaksi::findOrFail($request->transaksi_id);
        $cabangId = session('cabang_id');

        if ($cabangId && (int) $transaksi->cabang_id !== (int) $cabangId) {
            return $this->error('Transaksi bukan milik cabang aktif', 403);
        }

        try {
            $retur = $this->returnService->buatRetur(
                $transaksi,
                $request->items,
                $request->alasan,
                $request->input('metode_pengembalian', 'kas'),
                auth()->id()
            );

            return $this->success(
                $retur,
                $retur->status === 'selesai'
                    ? 'Retur penjualan berhasil diproses — stok & pembukuan terupdate'
                    : 'Retur penjualan diajukan — menunggu approval supervisor',
                201
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
