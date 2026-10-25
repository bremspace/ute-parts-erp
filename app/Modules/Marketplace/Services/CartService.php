<?php

namespace App\Modules\Marketplace\Services;

use App\Modules\Pos\Services\PricingService;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
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

    public function cartKey(int $produkId, ?int $variantId = null): string
    {
        return $variantId ? 'p'.$produkId.'_v'.$variantId : 'p'.$produkId;
    }

    public function all(): array
    {
        $cart = Session::get('shop.cart', []);
        $customer = auth('customer')->user();
        if ($customer && ! empty($cart)) {
            $changed = false;
            foreach ($cart as $key => $item) {
                $produk = Produk::find($item['product_id']);
                if ($produk) {
                    $variant = ! empty($item['variant_id']) ? SkuVariant::find($item['variant_id']) : null;
                    $pricing = $this->pricingService->resolve($produk, $customer, $variant);
                    $hargaRetail = $variant && $variant->harga_jual_retail !== null
                        ? (float) $variant->harga_jual_retail
                        : (float) $produk->harga_jual_retail;
                    $diskonTier = max(0.0, $hargaRetail - (float) $pricing['harga']);

                    if (
                        (float) ($item['harga'] ?? 0) !== (float) $pricing['harga']
                        || (float) ($item['harga_retail'] ?? 0) !== $hargaRetail
                        || empty($item['tier_nama'])
                    ) {
                        $cart[$key]['harga'] = (float) $pricing['harga'];
                        $cart[$key]['harga_retail'] = $hargaRetail;
                        $cart[$key]['diskon_tier'] = $diskonTier;
                        $cart[$key]['tier_nama'] = $pricing['tier'] ?? null;
                        $cart[$key]['alasan_harga'] = $pricing['alasan'] ?? null;
                        $changed = true;
                    }
                }
            }
            if ($changed) {
                Session::put('shop.cart', $cart);
            }
        }

        return $cart;
    }

    public function isi(int $produkId, ?int $variantId = null, int $qty = 1): array
    {
        $produk = Produk::findOrFail($produkId);
        $key = $this->cartKey($produkId, $variantId);

        // Cek stok total tersedia
        $stokTotal = StokItem::where('produk_id', $produkId)
            ->when($variantId, fn ($q) => $q->where('sku_variant_id', $variantId))
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
            ? SkuVariant::find($variantId)
            : null;

        $pricing = $this->pricingService->resolve($produk, $customer, $variant);
        $hargaRetail = $variant && $variant->harga_jual_retail !== null
            ? (float) $variant->harga_jual_retail
            : (float) $produk->harga_jual_retail;
        $diskonTier = max(0.0, $hargaRetail - (float) $pricing['harga']);

        $cart[$key] = [
            'key' => $key,
            'product_id' => $produkId,
            'variant_id' => $variantId,
            'name' => $produk->nama,
            'variant_name' => $variant?->nama_varian,
            'harga' => (float) $pricing['harga'],
            'harga_retail' => $hargaRetail,
            'diskon_tier' => $diskonTier,
            'tier_nama' => $pricing['tier'] ?? null,
            'qty' => $newQty,
            'alasan_harga' => $pricing['alasan'],
            'stok_max' => (int) $stokTotal,
        ];

        Session::put('shop.cart', $cart);

        return $cart;
    }

    public function updateQty(string $key, int $qty): array
    {
        $cart = $this->all();

        if (! isset($cart[$key])) {
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

    public function hapus(string $key): array
    {
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

    public function hitungSubtotalRetail(): float
    {
        $total = 0.0;
        foreach ($this->all() as $item) {
            $retail = (float) ($item['harga_retail'] ?? $item['harga']);
            $total += $retail * (int) $item['qty'];
        }

        return $total;
    }

    public function hitungDiskonTier(): float
    {
        return max(0.0, $this->hitungSubtotalRetail() - $this->hitungSubtotal());
    }

    public function jumlahItem(): int
    {
        return array_sum(array_column($this->all(), 'qty'));
    }
}
