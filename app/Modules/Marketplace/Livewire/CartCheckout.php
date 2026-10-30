<?php

namespace App\Modules\Marketplace\Livewire;

use App\Modules\Marketplace\Services\CartService;
use App\Modules\Marketplace\Services\OrderService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
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

    public function hapus(string $key)
    {
        app(CartService::class)->hapus($key);
        $this->dispatch('cart-updated', count: app(CartService::class)->jumlahItem());
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
            'cabangs' => $this->cabangs,
            'riwayatOrders' => $this->orderSuccess ? collect() : Transaksi::where('pelanggan_id', auth('customer')->id())
                ->where('sumber', 'marketplace')
                ->latest()->take(5)->get(),
        ])->layout('layouts.marketplace', ['title' => $this->mode === 'checkout' ? 'Checkout' : 'Keranjang']);
    }
}
