<?php

namespace App\Modules\Marketplace\Controllers;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Marketplace\Services\OrderService;
use App\Modules\Pos\Services\PricingService;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ShopController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PricingService $pricingService,
        protected OrderService $orderService
    ) {}

    /**
     * Ambil customer aktif (dari guard customer) — null jika guest.
     */
    private function customerAktif(): ?Pelanggan
    {
        $customer = auth('customer')->user();
        return $customer ? $customer->load('tierMembership') : null;
    }

    // [API: SHOP-01] Katalog produk publik
    public function produk(Request $request)
    {
        $search = $request->query('search');
        $kategori = $request->query('kategori');
        $kondisi = $request->query('kondisi');
        $brand = $request->query('brand');
        $hargaMin = $request->query('harga_min');
        $hargaMax = $request->query('harga_max');
        $tersedia = filter_var($request->query('tersedia', true), FILTER_VALIDATE_BOOLEAN);

        $query = Produk::query()
            ->where('is_active', true)
            ->with(['skuVariants' => fn($q) => $q->where('is_active', true)])
            ->withCount('stokItems');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('brand_kompatibel', 'like', "%{$search}%")
                  ->orWhere('model_kompatibel', 'like', "%{$search}%")
                  ->orWhere('kategori', 'like', "%{$search}%");
            });
        }
        if ($kategori) {
            $query->where('kategori', $kategori);
        }
        if ($kondisi) {
            $query->where('kondisi', $kondisi);
        }
        if ($brand) {
            $query->where('brand_kompatibel', $brand);
        }
        if ($hargaMin) {
            $query->where('harga_jual_retail', '>=', (float) $hargaMin);
        }
        if ($hargaMax) {
            $query->where('harga_jual_retail', '<=', (float) $hargaMax);
        }

        $customer = $this->customerAktif();

        $products = $query->paginate(24)->through(function ($p) use ($customer) {
            $pricing = $this->pricingService->resolve($p, $customer);
            $totalStok = StokItem::where('produk_id', $p->id)->sum('jumlah');

            return [
                'id' => $p->id,
                'nama' => $p->nama,
                'slug' => $p->slug,
                'kategori' => $p->kategori,
                'kondisi' => $p->kondisi,
                'brand_kompatibel' => $p->brand_kompatibel,
                'model_kompatibel' => $p->model_kompatibel,
                'gambar' => $p->gambar,
                'harga_retail' => (float) $p->harga_jual_retail,
                'harga_final' => $pricing['harga'],
                'diskon_nominal' => $pricing['diskon_nominal'],
                'alasan_harga' => $pricing['alasan'],
                'tier' => $pricing['tier'],
                'stok_total' => (int) $totalStok,
                'tersedia' => $totalStok > 0,
            ];
        });

        return $this->success($products, 'Katalog produk berhasil dimuat');
    }

    // [API: SHOP-02] Detail produk publik
    public function produkDetail(string $slug)
    {
        $produk = Produk::where('slug', $slug)
            ->where('is_active', true)
            ->with(['skuVariants' => fn($q) => $q->where('is_active', true)])
            ->firstOrFail();

        $customer = $this->customerAktif();
        $pricing = $this->pricingService->resolve($produk, $customer);

        // Stok per cabang (gabungan gudang tiap cabang)
        $stokPerCabang = StokItem::with('gudang.cabang')
            ->where('produk_id', $produk->id)
            ->get()
            ->groupBy(fn($s) => $s->gudang?->cabang_id)
            ->map(fn($rows) => [
                'cabang' => $rows->first()->gudang?->cabang?->nama,
                'stok' => $rows->sum('jumlah'),
            ])
            ->values();

        $variants = $produk->skuVariants->map(function ($v) use ($produk, $customer) {
            $vp = $this->pricingService->resolve($produk, $customer, $v);
            return [
                'id' => $v->id,
                'sku' => $v->sku,
                'nama_varian' => $v->nama_varian,
                'atribut' => $v->atribut,
                'harga_final' => $vp['harga'],
                'stok' => (int) StokItem::where('produk_id', $produk->id)->where('sku_variant_id', $v->id)->sum('jumlah'),
            ];
        });

        return $this->success([
            'produk' => $produk,
            'harga' => $pricing,
            'stok_per_cabang' => $stokPerCabang,
            'stok_total' => (int) StokItem::where('produk_id', $produk->id)->sum('jumlah'),
            'variants' => $variants,
        ], 'Detail produk berhasil dimuat');
    }

    // Checkout: buat order (guest ditolak — wajib akun, PRD Frontend §6.4)
    public function checkout(Request $request)
    {
        $customer = auth('customer')->user();
        if (!$customer) {
            return $this->error('Silakan login sebagai pelanggan untuk checkout', 401);
        }

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.produk_id' => 'required|exists:produk,id',
            'items.*.sku_variant_id' => 'nullable|exists:sku_variants,id',
            'items.*.jumlah' => 'required|integer|min:1',
            'cabang_id' => 'required|exists:cabang,id',
            'metode_ambil' => 'required|in:ambil_ke_toko,pengiriman',
            'catatan' => 'nullable|string',
        ]);

        try {
            $order = $this->orderService->buatOrder(
                $customer,
                $request->items,
                (int) $request->cabang_id,
                $request->metode_ambil,
                $request->catatan
            );

            return $this->success([
                'no_transaksi' => $order->no_transaksi,
                'total_akhir' => $order->total_akhir,
                'status' => $order->status,
                'order' => $order->load('items.produk', 'cabang'),
            ], 'Pesanan dibuat — menunggu pembayaran', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    // [API: ACCOUNT-01] Dashboard pelanggan (riwayat order)
    public function accountOrders(Request $request)
    {
        $customer = auth('customer')->user();
        if (!$customer) {
            return $this->error('Silakan login', 401);
        }

        $orders = \App\Modules\Pos\Models\Transaksi::with(['items.produk', 'cabang'])
            ->where('pelanggan_id', $customer->id)
            ->latest()
            ->paginate(10);

        return $this->success($orders, 'Riwayat pesanan berhasil dimuat');
    }

    // [API: ACCOUNT-02] Tracking servis publik (by telepon pelanggan auth)
    public function accountServis(Request $request)
    {
        $customer = auth('customer')->user();
        if (!$customer) {
            return $this->error('Silakan login', 401);
        }

        $servis = \App\Modules\Servis\Models\TiketServis::with('garansi', 'jenisServis')
            ->where('pelanggan_id', $customer->id)
            ->latest()
            ->get();

        return $this->success($servis, 'Riwayat servis berhasil dimuat');
    }

    // Kategori unik untuk filter
    public function kategoriFilter()
    {
        $kategori = Produk::where('is_active', true)
            ->distinct()
            ->orderBy('kategori')
            ->pluck('kategori')
            ->filter()
            ->values();

        return $this->success($kategori, 'Daftar kategori berhasil dimuat');
    }
}