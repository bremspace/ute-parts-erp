<?php

namespace App\Modules\Marketplace\Livewire;

use App\Modules\Marketplace\Services\CartService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Pos\Models\Transaksi;
use Livewire\Component;

class CartCheckout extends Component
{
    public string $mode = 'cart'; // cart | checkout
    public ?int $selectedCabangId = null;
    public string $metodeAmbil = 'ambil_ke_toko';
    public string $catatan = '';

    public ?string $orderNo = null;
    public ?float $orderTotal = null;
    public bool $orderSuccess = false;

    public function mount()
    {
        // Parameter route bukan penting — deteksi dari URL
        $this->mode = request()->is('checkout') ? 'checkout' : 'cart';
        if (!$this->selectedCabangId) {
            $this->selectedCabangId = Cabang::where('is_active', true)->first()?->id;
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

    public function getJumlahItemProperty(): int
    {
        return app(CartService::class)->jumlahItem();
    }

    public function getCabangsProperty()
    {
        return Cabang::where('is_active', true)->get();
    }

    public function updateQty(?int $variantId, int $qty)
    {
        app(CartService::class)->updateQty($variantId, $qty);
    }

    public function hapus(?int $variantId)
    {
        app(CartService::class)->hapus($variantId);
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
        if (!$customer) {
            return redirect('/login-pelanggan');
        }

        if (empty($this->cart)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Keranjang kosong']);
            return;
        }

        $this->validate([
            'selectedCabangId' => 'required|exists:cabang,id',
            'metodeAmbil' => 'required|in:ambil_ke_toko,pengiriman',
        ]);

        $items = [];
        foreach ($this->cart as $item) {
            $items[] = [
                'produk_id' => $item['product_id'],
                'sku_variant_id' => $item['variant_id'],
                'jumlah' => $item['qty'],
            ];
        }

        try {
            $order = app(\App\Modules\Marketplace\Services\OrderService::class)->buatOrder(
                $customer,
                $items,
                $this->selectedCabangId,
                $this->metodeAmbil,
                $this->catatan
            );

            app(CartService::class)->kosongkan();

            $this->orderNo = $order->no_transaksi;
            $this->orderTotal = (float) $order->total_akhir;
            $this->orderSuccess = true;
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function render()
    {
        return view('modules.marketplace.livewire.cart-checkout', [
            'pelanggan' => auth('customer')->user(),
            'cart' => $this->cart,
            'subtotal' => $this->subtotal,
            'jumlahItem' => $this->jumlahItem,
            'cabangs' => $this->cabangs,
            'riwayatOrders' => $this->orderSuccess ? collect() : Transaksi::where('pelanggan_id', auth('customer')->id())
                ->where('sumber', 'marketplace')
                ->latest()->take(5)->get(),
        ])->layout('layouts.marketplace', ['title' => $this->mode === 'checkout' ? 'Checkout' : 'Keranjang']);
    }
}