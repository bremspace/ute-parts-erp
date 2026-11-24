<?php

namespace App\Modules\Marketplace\Livewire;

use App\Modules\Marketplace\Services\CartService;
use App\Modules\Marketplace\Services\OrderService;
use App\Modules\Marketplace\Support\HargaTampilan;
use App\Modules\Pos\Services\PricingService;
use App\Modules\Wms\Models\Produk;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class CartCheckout extends Component
{
    public string $mode = 'cart'; // cart | checkout

    public ?int $selectedCabangId = null;

    public string $metodeAmbil = 'ambil_ke_toko';

    public string $catatan = '';

    public string $namaPenerima = '';

    public string $teleponPenerima = '';

    public string $alamatPengiriman = '';

    public ?string $orderNo = null;

    public ?float $orderTotal = null;

    public bool $orderSuccess = false;

    public string $cartSearch = '';

    public function mount()
    {
        // Parameter route bukan penting — deteksi dari URL
        $this->mode = request()->is('checkout') ? 'checkout' : 'cart';
        if (! $this->selectedCabangId) {
            $this->selectedCabangId = Cabang::where('is_active', true)->first()?->id;
        }

        $customer = auth('customer')->user();
        if ($customer) {
            $this->namaPenerima = $customer->nama ?? '';
            $this->teleponPenerima = $customer->telepon ?? '';
            $this->alamatPengiriman = $customer->alamat ?? '';
        }
    }

    public function getCartProperty(): array
    {
        return app(CartService::class)->all();
    }

    public function getSubtotalProperty(): float
    {
        return app(CartService::class)->hitungSubtotal();
    }

    public function getSubtotalRetailProperty(): float
    {
        return app(CartService::class)->hitungSubtotalRetail();
    }

    public function getDiskonTierProperty(): float
    {
        return app(CartService::class)->hitungDiskonTier();
    }

    public function getPotensiKomisiProperty(): float
    {
        return app(CartService::class)->hitungPotensiKomisi();
    }

    public function getPartaiInfoProperty(): array
    {
        return app(CartService::class)->hitungDiskonPartai(auth('customer')->user());
    }

    public function getDiskonPartaiProperty(): float
    {
        return (float) ($this->partaiInfo['diskon_nominal'] ?? 0.0);
    }

    public function getJumlahItemProperty(): int
    {
        return app(CartService::class)->jumlahItem();
    }

    /**
     * Data coret/potongan per item untuk cart & checkout.
     */
    public function getHargaTampilanProperty(): array
    {
        $rows = [];
        foreach ($this->cart as $key => $item) {
            $rows[$key] = HargaTampilan::dariCart($item);
        }

        return $rows;
    }

    public function getPotonganKemasanProperty(): float
    {
        return (float) array_sum(array_map(
            fn (array $row) => $row['is_kemasan'] ? $row['potongan_total'] : 0.0,
            $this->hargaTampilan
        ));
    }

    public function getPotonganMemberProperty(): float
    {
        return (float) array_sum(array_map(
            fn (array $row) => ! $row['is_kemasan'] ? $row['potongan_total'] : 0.0,
            $this->hargaTampilan
        ));
    }

    /**
     * Produk rekomendasi keranjang kosong (maksimal 3, stok tersedia).
     */
    public function getRekomendasiProperty(): array
    {
        return Produk::query()
            ->where('is_active', true)
            ->withSum('stokItems', 'jumlah')
            ->having('stok_items_sum_jumlah', '>', 0)
            ->orderBy('produk.nama')
            ->limit(3)
            ->get()
            ->map(fn (Produk $p) => [
                'id' => $p->id,
                'nama' => $p->nama,
                'sku' => $p->sku,
                'gambar' => $p->thumbnail_url ?: $p->gambar,
                'harga' => (float) ($p->harga_jual_retail ?? 0),
            ])
            ->all();
    }

    /**
     * Tambah produk rekomendasi langsung ke keranjang.
     */
    public function tambahRekomendasi(int $produkId): void
    {
        try {
            app(CartService::class)->isi($produkId, null, 1);
            $this->dispatch('cart-updated', count: app(CartService::class)->jumlahItem());
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Produk ditambahkan ke keranjang']);
        } catch (\Throwable $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Suggestion pencarian cepat di dalam keranjang.
     */
    #[Computed]
    public function cartSearchSuggestions(): array
    {
        $q = trim($this->cartSearch);
        if (mb_strlen($q) < 2) {
            return [];
        }

        return Produk::query()
            ->where('produk.is_active', true)
            ->withSum('stokItems', 'jumlah')
            ->with(['hargaTier', 'satuanKonversi'])
            ->filterKatalog([
                'search' => $q,
                'auto_relevance' => true,
            ])
            ->having('stok_items_sum_jumlah', '>', 0)
            ->limit(6)
            ->get()
            ->map(function (Produk $p) {
                $price = app(PricingService::class)->resolve($p, auth('customer')->user());

                return [
                    'id' => $p->id,
                    'nama' => $p->nama,
                    'sku' => $p->sku,
                    'gambar' => $p->thumbnail_url ?: $p->gambar,
                    'harga' => (float) ($price['harga'] ?? $p->harga_jual_retail ?? 0),
                    'stok' => (int) ($p->stok_items_sum_jumlah ?? 0),
                ];
            })
            ->all();
    }

    /**
     * Tambah produk dari pencarian cepat keranjang.
     */
    public function tambahDariPencarian(int $produkId): void
    {
        try {
            app(CartService::class)->isi($produkId, null, 1);
            $this->dispatch('cart-updated', count: app(CartService::class)->jumlahItem());
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Produk ditambahkan ke keranjang']);
            $this->cartSearch = '';
        } catch (\Throwable $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function getCabangsProperty()
    {
        return Cabang::where('is_active', true)->get();
    }

    public function updateQty(string $key, int|string $qty)
    {
        $qty = max(1, (int) $qty);
        app(CartService::class)->updateQty($key, $qty);
        $this->dispatch('cart-updated', count: app(CartService::class)->jumlahItem());
    }

    public ?string $lastRemovedKey = null;

    public ?array $lastRemovedItem = null;

    public function hapus(string $key)
    {
        $cart = app(CartService::class)->all();
        if (! isset($cart[$key])) {
            return;
        }

        $this->lastRemovedKey = $key;
        $this->lastRemovedItem = $cart[$key];
        app(CartService::class)->hapus($key);
        $this->dispatch('cart-updated', count: app(CartService::class)->jumlahItem());
        $this->dispatch('cart-removed', [
            'key' => $key,
            'nama' => $cart[$key]['name'] ?? 'Produk',
            'message' => 'Produk dihapus dari keranjang',
        ]);
    }

    #[On('undoHapus')]
    public function undoHapus(): void
    {
        if (! $this->lastRemovedKey || ! $this->lastRemovedItem) {
            return;
        }

        $item = $this->lastRemovedItem;
        try {
            app(CartService::class)->isi(
                (int) ($item['product_id'] ?? 0),
                $item['variant_id'] ?? null,
                (int) ($item['qty'] ?? 1),
                $item['catatan'] ?? null,
                $item['satuan'] ?? null
            );
            $this->dispatch('cart-updated', count: app(CartService::class)->jumlahItem());
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Produk dikembalikan ke keranjang']);
        } catch (\Throwable $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        $this->lastRemovedKey = null;
        $this->lastRemovedItem = null;
    }

    public function lanjutCheckout()
    {
        if (count($this->cart) === 0) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Keranjang kosong']);

            return;
        }

        return redirect()->to('/checkout');
    }

    public function placeOrder()
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            return redirect('/login-pelanggan');
        }

        if (empty($this->cart)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Keranjang kosong']);

            return;
        }

        $rules = [
            'selectedCabangId' => 'required|exists:cabang,id',
            'metodeAmbil' => 'required|in:ambil_ke_toko,pengiriman',
            'namaPenerima' => 'nullable|string|max:100',
            'teleponPenerima' => 'nullable|string|max:20',
            'alamatPengiriman' => $this->metodeAmbil === 'pengiriman' ? 'required|string|min:5' : 'nullable|string',
        ];

        $this->validate($rules, [
            'alamatPengiriman.required' => 'Alamat pengiriman wajib diisi untuk pengiriman kurir.',
            'alamatPengiriman.min' => 'Alamat pengiriman minimal 5 karakter.',
        ]);

        $items = [];
        foreach ($this->cart as $item) {
            $items[] = [
                'produk_id' => $item['product_id'],
                'sku_variant_id' => $item['variant_id'],
                'jumlah' => $item['qty'],
                'satuan' => $item['satuan'] ?? null,
                'sisa_pcs' => $item['sisa_pcs'] ?? 0,
                'catatan' => $item['catatan'] ?? null,
            ];
        }

        try {
            $shippingData = [
                'nama_penerima' => $this->namaPenerima ?: $customer->nama,
                'telepon_tujuan' => $this->teleponPenerima ?: $customer->telepon,
                'alamat_tujuan' => $this->alamatPengiriman ?: $customer->alamat,
            ];

            $order = app(OrderService::class)->buatOrder(
                $customer,
                $items,
                $this->selectedCabangId,
                $this->metodeAmbil,
                $this->catatan,
                $shippingData
            );

            app(CartService::class)->kosongkan();
            $this->dispatch('cart-updated', count: 0);

            $this->orderNo = $order->no_transaksi;
            $this->orderTotal = (float) $order->total_akhir;
            $this->orderSuccess = true;
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function getPajakPreviewProperty(): array
    {
        if (! $this->selectedCabangId) {
            $dpp = max(0.0, $this->subtotal - $this->diskonPartai);

            return [
                'enabled' => false,
                'ppn_nominal' => 0.0,
                'ppn_percent' => 0.0,
                'dpp' => $dpp,
                'total_akhir' => $dpp,
            ];
        }

        return app(OrderService::class)->previewPajak($this->selectedCabangId, $this->subtotal, $this->diskonPartai);
    }

    public function render()
    {
        return view('modules.marketplace.livewire.cart-checkout', [
            'mode' => $this->mode,
            'pelanggan' => auth('customer')->user(),
            'cart' => $this->cart,
            'subtotal' => $this->subtotal,
            'subtotalRetail' => $this->subtotalRetail,
            'diskonTier' => $this->diskonTier,
            'potensiKomisi' => $this->potensiKomisi,
            'partaiInfo' => $this->partaiInfo,
            'diskonPartai' => $this->diskonPartai,
            'pajakPreview' => $this->pajakPreview,
            'jumlahItem' => $this->jumlahItem,
            'rekomendasi' => $this->rekomendasi,
            'cartSearchSuggestions' => $this->cartSearchSuggestions,
            'hargaTampilan' => $this->hargaTampilan,
            'potonganKemasan' => $this->potonganKemasan,
            'potonganMember' => $this->potonganMember,
            'cabangs' => $this->cabangs,
            'riwayatOrders' => $this->orderSuccess ? collect() : Transaksi::where('pelanggan_id', auth('customer')->id())
                ->where('sumber', 'marketplace')
                ->latest()->take(5)->get(),
        ])->layout('layouts.marketplace', ['title' => $this->mode === 'checkout' ? 'Checkout' : 'Keranjang']);
    }
}
