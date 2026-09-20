<?php

namespace App\Modules\Marketplace\Services;

use App\Modules\Pos\Services\PricingService;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Support\Facades\Session;

/**
 * Keranjang belanja marketplace berbasis session (tanpa tabel DB).
 * Struktur: cart => [ productId => ['product_id','variant_id','qty','price','name'] ]
 */
class CartService
{
    public function __construct(
        protected PricingService $pricingService
    ) {}

    private function cartKey(?int $variantId): string
    {
        return $variantId ? 'v' . $variantId : 'p0';
    }

    public function all(): array
    {
        return Session::get('shop.cart', []);
    }

    public function isi(int $produkId, ?int $variantId = null, int $qty = 1): array
    {
        $produk = Produk::findOrFail($produkId);
        $key = $this->cartKey($variantId);

        // Cek stok total tersedia
        $stokTotal = StokItem::where('produk_id', $produkId)
            ->when($variantId, fn($q) => $q->where('sku_variant_id', $variantId))
            ->sum('jumlah');

        $cart = $this->all();
        $existing = $cart[$key] ?? null;

        if ($existing) {
            $newQty = $existing['qty'] + $qty;
        } else {
            $newQty = $qty;
        }

        if ($newQty > $stokTotal) {
            throw new \Exception("Stok tidak mencukupi untuk {$produk->nama}");
        }

        $customer = auth('customer')->user();
        $variant = $variantId
            ? \App\Modules\Wms\Models\SkuVariant::find($variantId)
            : null;

        $pricing = $this->pricingService->resolve($produk, $customer, $variant);

        $cart[$key] = [
            'product_id'   => $produkId,
            'variant_id'   => $variantId,
            'name'         => $produk->nama,
            'variant_name' => $variant?->nama_varian,
            'harga'        => $pricing['harga'],
            'harga_retail' => (float) $produk->harga_jual_retail,
            'qty'          => $newQty,
            'alasan_harga' => $pricing['alasan'],
            'stok_max'     => (int) $stokTotal,
        ];

        Session::put('shop.cart', $cart);

        return $cart;
    }

    public function updateQty(?int $variantId, int $qty): array
    {
        $key = $this->cartKey($variantId);
        $cart = $this->all();

        if (!isset($cart[$key])) {
            return $cart;
        }

        if ($qty <= 0) {
            unset($cart[$key]);
        } else {
            if ($qty > $cart[$key]['stok_max']) {
                $qty = $cart[$key]['stok_max'];
            }
            $cart[$key]['qty'] = $qty;
        }

        Session::put('shop.cart', $cart);

        return $cart;
    }

    public function hapus(?int $variantId): array
    {
        $key = $this->cartKey($variantId);
        $cart = $this->all();
        unset($cart[$key]);
        Session::put('shop.cart', $cart);

        return $cart;
    }

    public function kosongkan(): void
    {
        Session::forget('shop.cart');
    }

    public function hitungSubtotal(): float
    {
        $total = 0.0;
        foreach ($this->all() as $item) {
            $total += (float) $item['harga'] * (int) $item['qty'];
        }
        return $total;
    }

    public function jumlahItem(): int
    {
        return array_sum(array_column($this->all(), 'qty'));
    }
}