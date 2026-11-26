<?php

namespace App\Modules\Marketplace\Services;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Services\PartaiPricingService;
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

    public function cartKey(int $produkId, ?int $variantId = null, ?string $catatan = null, ?string $satuan = null): string
    {
        $base = $variantId ? 'p'.$produkId.'_v'.$variantId : 'p'.$produkId;
        if ($catatan) {
            $base .= '_c'.substr(md5(trim($catatan)), 0, 8);
        }
        if ($satuan) {
            $base .= '_s'.substr(md5(trim($satuan)), 0, 6);
        }

        return $base;
    }

    public function all(): array
    {
        $cart = Session::get('shop.cart', []);
        // Backfill satuan/qty_satuan/harga_satuan untuk item keranjang lama (termasuk harga per box yang benar)
        $needSave = false;
        foreach ($cart as $k => $it) {
            $produk = ! empty($it['product_id']) ? Produk::find($it['product_id']) : null;
            $baseSatuan = $produk?->satuan ?: 'pcs';

            if (! isset($it['satuan'])) {
                $cart[$k]['satuan'] = $baseSatuan;
                $needSave = true;
            }
            if (! array_key_exists('sku', $it)) {
                $cart[$k]['sku'] = $produk?->sku;
                $needSave = true;
            }
            if (! array_key_exists('gambar', $it)) {
                $cart[$k]['gambar'] = $produk?->thumbnail_url ?: $produk?->gambar;
                $needSave = true;
            }
            $gambarPreview = $this->gambarPreviewProduk($produk);
            if (($cart[$k]['gambar_preview'] ?? null) !== $gambarPreview) {
                $cart[$k]['gambar_preview'] = $gambarPreview;
                $needSave = true;
            }

            // Info harga box dari master dipakai untuk iming-iming "beli box lebih murah"
            $infoBox = $this->infoBoxProduk($produk);
            if ((int) ($cart[$k]['box_faktor'] ?? 0) !== $infoBox['faktor']
                || abs((float) ($cart[$k]['box_harga'] ?? 0) - $infoBox['harga']) > 0.001) {
                $cart[$k]['box_faktor'] = $infoBox['faktor'];
                $cart[$k]['box_harga'] = $infoBox['harga'];
                $needSave = true;
            }

            $satuan = $cart[$k]['satuan'];

            // faktor & harga per satuan dari master (bukan asumsi 1)
            $faktor = 1.0;
            $hargaSatuanMaster = null;
            if ($produk && strcasecmp($satuan, $baseSatuan) !== 0) {
                foreach ($produk->getDaftarSatuan() as $s) {
                    if (strcasecmp($s['satuan_kode'], $satuan) === 0) {
                        $faktor = (float) ($s['faktor_konversi'] ?? 1.0);
                        if (! empty($s['harga_jual'])) {
                            $hargaSatuanMaster = (float) $s['harga_jual'];
                        }
                        break;
                    }
                }
            }
            if (! isset($it['faktor_konversi']) || (float) $it['faktor_konversi'] === 1.0 && $faktor !== 1.0) {
                $cart[$k]['faktor_konversi'] = $faktor;
                $needSave = true;
            }
            $fk = (float) ($cart[$k]['faktor_konversi'] ?? 1.0);
            if ($fk <= 0) {
                $fk = 1.0;
            }

            // harga per base unit yang efektif
            if (! isset($it['harga']) || (float) $it['harga'] <= 0) {
                $cart[$k]['harga'] = (float) ($produk?->harga_jual_retail ?? 0);
                $needSave = true;
            }
            $hargaBase = (float) $cart[$k]['harga'];

            if (! isset($it['qty_satuan'])) {
                $cart[$k]['qty_satuan'] = (int) round(($it['qty'] ?? 1) / $fk);
                if ($cart[$k]['qty_satuan'] < 1) {
                    $cart[$k]['qty_satuan'] = 1;
                }
                $needSave = true;
            }
            $faktorInt = max(1, (int) round($fk));
            if (! array_key_exists('sisa_pcs', $it)) {
                $cart[$k]['sisa_pcs'] = $fk > 1 ? ((int) ($it['qty'] ?? 1) % $faktorInt) : 0;
                $needSave = true;
            }
            if (empty($cart[$k]['breakdown']) && $fk > 1 && (int) ($cart[$k]['qty_satuan'] ?? 0) > 0 && (int) ($cart[$k]['sisa_pcs'] ?? 0) > 0) {
                $cart[$k]['breakdown'] = (int) $cart[$k]['qty_satuan'].' '.$satuan.' + '.(int) $cart[$k]['sisa_pcs'].' pcs';
                $needSave = true;
            }
            if (! isset($it['harga_satuan'])) {
                if ($hargaSatuanMaster !== null) {
                    $cart[$k]['harga_satuan'] = $hargaSatuanMaster;
                } else {
                    $cart[$k]['harga_satuan'] = round($hargaBase * $fk, 2);
                }
                $needSave = true;
            } elseif ($hargaSatuanMaster !== null && (float) $cart[$k]['harga_satuan'] !== $hargaSatuanMaster) {
                // koreksi harga per satuan jika master berbeda (mis. harga lama salah)
                $cart[$k]['harga_satuan'] = $hargaSatuanMaster;
                $cart[$k]['harga'] = $this->hargaBaseEfektif($cart[$k], $produk);
                $needSave = true;
            }

            // Pastikan harga (per base unit) konsisten dengan harga_satuan per satuan.
            // Untuk campuran box + pcs, sisa pcs tetap diharga retail (bukan harga box).
            if (isset($cart[$k]['harga_satuan']) && (float) $cart[$k]['harga_satuan'] > 0 && $fk > 0) {
                $expectedBase = $this->hargaBaseEfektif($cart[$k], $produk);
                if (abs((float) $cart[$k]['harga'] - $expectedBase) > 0.001) {
                    $cart[$k]['harga'] = $expectedBase;
                    $needSave = true;
                }
            }

            // Diskon satuan (box) sudah termasuk di harga; jangan hitung sebagai potongan tier
            if (strcasecmp($satuan, $baseSatuan) !== 0) {
                if ((float) ($cart[$k]['diskon_tier'] ?? 0) !== 0.0) {
                    $cart[$k]['diskon_tier'] = 0.0;
                    $needSave = true;
                }
            }
        }
        if ($needSave) {
            Session::put('shop.cart', $cart);
        }

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
                    $potensiKomisi = (float) ($pricing['potensi_komisi'] ?? 0.0);

                    $isSatuanNonBase = ! empty($item['satuan']) && strcasecmp($item['satuan'], $produk->satuan ?: 'pcs') !== 0;

                    // Selalu perbarui diskon_tier (termasuk reset ke 0 untuk retail / satuan non-base)
                    // agar keranjang lama tidak menyimpan potongan tier yang stale.
                    $newDiskonTier = $isSatuanNonBase ? 0.0 : $diskonTier;
                    $needUpdate = false;
                    if (abs((float) ($item['diskon_tier'] ?? 0) - $newDiskonTier) > 0.001) {
                        $needUpdate = true;
                    }
                    if (! $isSatuanNonBase && abs((float) ($item['harga'] ?? 0) - (float) $pricing['harga']) > 0.001) {
                        $needUpdate = true;
                    }
                    if (abs((float) ($item['harga_retail'] ?? 0) - $hargaRetail) > 0.001) {
                        $needUpdate = true;
                    }
                    if (empty($item['tier_nama'])) {
                        $needUpdate = true;
                    }

                    if ($needUpdate) {
                        if (! $isSatuanNonBase) {
                            $cart[$key]['harga'] = (float) $pricing['harga'];
                        }
                        $cart[$key]['harga_retail'] = $hargaRetail;
                        $cart[$key]['diskon_tier'] = $newDiskonTier;
                        $cart[$key]['potensi_komisi'] = $potensiKomisi;
                        $cart[$key]['mode_reward'] = $pricing['mode_reward'] ?? 'diskon_langsung';
                        $cart[$key]['tier_nama'] = $pricing['tier'] ?? null;
                        $cart[$key]['alasan_harga'] = $pricing['alasan'];
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

    public function tambah(int $produkId, ?int $variantId = null, int $qty = 1, ?string $catatan = null, ?string $satuan = null): array
    {
        return $this->isi($produkId, $variantId, $qty, $catatan, $satuan);
    }

    public function isi(int $produkId, ?int $variantId = null, int $qty = 1, ?string $catatan = null, ?string $satuan = null): array
    {
        $produk = Produk::findOrFail($produkId);
        $infoBox = $this->infoBoxProduk($produk);
        $gambarPreview = $this->gambarPreviewProduk($produk);
        $cleanCatatan = $catatan ? trim($catatan) : null;
        $cleanSatuan = $satuan ? trim($satuan) : null;
        $key = $this->cartKey($produkId, $variantId, $cleanCatatan, $cleanSatuan);

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

        // [SECURITY] Harga per satuan dihitung server-side via resolver yang sama
        // dengan checkout (OrderService) — cart & tagihan dijamin identik.
        $satuanInfo = $this->pricingService->resolveHargaSatuan($produk, $customer, $variant, $cleanSatuan);
        $pricing = $satuanInfo['pricing'];
        $hargaRetail = $variant && $variant->harga_jual_retail !== null
            ? (float) $variant->harga_jual_retail
            : (float) $produk->harga_jual_retail;
        $diskonTier = max(0.0, $hargaRetail - (float) $pricing['harga']);

        // Harga & qty per satuan yang dipilih (untuk tampilan & subtotal per satuan)
        $baseSatuan = $produk->satuan ?: 'pcs';
        $faktorKonversi = (float) $satuanInfo['faktor_konversi'];
        $hargaSatuanPilih = (float) $satuanInfo['harga']; // per base unit
        // qty_satuan = jumlah satuan yang dibeli (bukan base unit)
        $faktorInt = max(1, (int) round($faktorKonversi));
        $qtySatuan = (int) round($newQty / max(0.0001, $faktorKonversi));
        if ($qtySatuan < 1) {
            $qtySatuan = 1;
        }
        $sisaPcs = $faktorKonversi > 1 ? ($newQty % $faktorInt) : 0;
        $breakdown = '';
        if ($faktorKonversi > 1) {
            $breakdown = $qtySatuan.' '.$cleanSatuan.($sisaPcs > 0 ? ' + '.$sisaPcs.' pcs' : '');
        }
        $hargaSatuanPilihPerSatuan = round($hargaSatuanPilih * $faktorKonversi, 2);
        $hargaEfektif = $hargaSatuanPilih;
        if ($sisaPcs > 0) {
            $hargaEfektif = round((($qtySatuan * $hargaSatuanPilihPerSatuan) + ($sisaPcs * $hargaRetail)) / max(1, $newQty), 2);
        }

        $cart[$key] = [
            'key' => $key,
            'product_id' => $produkId,
            'variant_id' => $variantId,
            'catatan' => $cleanCatatan,
            'name' => $produk->nama,
            'sku' => $produk->sku,
            'kategori' => $produk->kategori ?: ($produk->kategoriRelasi?->nama ?? null),
            'kualitas' => $produk->kualitas?->nama ?? null,
            'brand' => $produk->brand?->nama ?? ($produk->brand_kompatibel ?? null),
            'kompatibel' => $produk->tipeHps->isNotEmpty()
                ? $produk->tipeHps->take(2)->map(fn ($t) => trim((string) $t->merk.' '.(string) $t->model))->implode(', ')
                : null,
            'gambar' => $produk->thumbnail_url ?: $produk->gambar,
            'gambar_preview' => $gambarPreview,
            'variant_name' => $variant?->nama_varian,
            'satuan' => $cleanSatuan ?: $baseSatuan,
            'harga' => $hargaEfektif,
            'harga_tier' => (float) $pricing['harga'],
            'harga_satuan' => $hargaSatuanPilihPerSatuan,
            'faktor_konversi' => $faktorKonversi,
            'qty' => $newQty,
            'qty_satuan' => $qtySatuan,
            'harga_retail' => $hargaRetail,
            'diskon_tier' => $diskonTier,
            'potensi_komisi' => (float) ($pricing['potensi_komisi'] ?? 0.0),
            'mode_reward' => $pricing['mode_reward'] ?? 'diskon_langsung',
            'tier_nama' => $pricing['tier'] ?? null,
            'alasan_harga' => $faktorKonversi > 1 ? 'Harga Kemasan / Per Box' : $pricing['alasan'],
            'box_faktor' => $infoBox['faktor'],
            'box_harga' => $infoBox['harga'],
            'stok_max' => (int) $stokTotal,
            'sisa_pcs' => $sisaPcs,
            'breakdown' => $breakdown,
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
            Session::put('shop.cart', $cart);

            return $cart;
        }

        if ($qty > $cart[$key]['stok_max']) {
            $qty = $cart[$key]['stok_max'];
        }

        $item = $cart[$key];
        $satuan = $item['satuan'] ?? 'pcs';
        $faktor = (float) ($item['faktor_konversi'] ?? 1.0);
        if ($faktor <= 0) {
            $faktor = 1.0;
        }
        $faktorInt = (int) round($faktor);
        if ($faktorInt < 1) {
            $faktorInt = 1;
        }

        $produk = ! empty($item['product_id']) ? Produk::find($item['product_id']) : null;
        $baseSatuan = $produk?->satuan ?: 'pcs';

        // Jika satuan pcs tapi qty mencukupi 1 box, coba konversi ke box
        if ($satuan === 'pcs' && $produk) {
            $boxFaktor = 0;
            $boxPrice = 0;
            foreach ($produk->getDaftarSatuan() as $s) {
                if (strcasecmp($s['satuan_kode'], 'box') === 0) {
                    $boxFaktor = (int) round((float) ($s['faktor_konversi'] ?? 1.0));
                    $boxPrice = (float) ($s['harga_jual'] ?? 0);
                    break;
                }
            }
            if ($boxFaktor >= 2 && $qty >= $boxFaktor) {
                $satuan = 'box';
                $faktor = (float) $boxFaktor;
                $faktorInt = $boxFaktor;
                // pastikan faktor_konversi tersimpan
                $cart[$key]['faktor_konversi'] = $faktor;
                $cart[$key]['harga_satuan'] = $boxPrice > 0 ? $boxPrice : (float) ($item['harga'] ?? 0) * $faktor;
                $item['satuan'] = 'box';
                $item['faktor_konversi'] = $faktor;
                $item['harga_satuan'] = $cart[$key]['harga_satuan'];
            }
        }

        // Harga per box & per pcs dari master
        $boxPrice = (float) ($item['harga_satuan'] ?? 0);
        if ($boxPrice <= 0 && $produk) {
            foreach ($produk->getDaftarSatuan() as $s) {
                if (strcasecmp($s['satuan_kode'], 'box') === 0) {
                    $boxPrice = (float) ($s['harga_jual'] ?? 0);
                    break;
                }
            }
        }
        if ($boxPrice <= 0) {
            $boxPrice = (float) ($item['harga'] ?? 0) * $faktor;
        }
        $pcsPrice = (float) ($produk?->harga_jual_retail ?? $item['harga_retail'] ?? $item['harga'] ?? 0);

        $customer = auth('customer')->user();

        if ($satuan !== 'pcs' && $qty >= $faktorInt) {
            // Hitung campuran box + pcs
            $boxes = (int) floor($qty / $faktorInt);
            $rem = $qty % $faktorInt;
            $total = ($boxes * $boxPrice) + ($rem * $pcsPrice);
            $effectiveBase = $total / max(1, $qty);

            $cart[$key]['satuan'] = 'box';
            $cart[$key]['faktor_konversi'] = $faktor;
            $cart[$key]['qty_satuan'] = $boxes;
            $cart[$key]['harga_satuan'] = $boxPrice;
            $cart[$key]['harga'] = $effectiveBase;
            $cart[$key]['diskon_tier'] = 0.0;
            $cart[$key]['sisa_pcs'] = $rem;
            $cart[$key]['breakdown'] = $boxes.' '.$satuan.($rem > 0 ? ' + '.$rem.' pcs' : '');
            $cart[$key]['alasan_harga'] = 'Harga Kemasan / Per Box';
        } elseif ($satuan !== 'pcs' && $qty < $faktorInt) {
            // Tidak cukup untuk 1 box -> revert ke pcs
            $cart[$key]['satuan'] = $baseSatuan;
            $cart[$key]['faktor_konversi'] = 1.0;
            $cart[$key]['qty_satuan'] = $qty;
            $cart[$key]['harga_satuan'] = $pcsPrice;
            $cart[$key]['sisa_pcs'] = 0;
            $cart[$key]['breakdown'] = '';
            if ($customer && $produk) {
                $pricing = app(PricingService::class)->resolve($produk, $customer);
                $cart[$key]['harga'] = (float) $pricing['harga'];
                $cart[$key]['diskon_tier'] = (float) $pricing['diskon_nominal'];
                $cart[$key]['alasan_harga'] = $pricing['alasan'];
                $cart[$key]['tier_nama'] = $pricing['tier'] ?? $cart[$key]['tier_nama'];
            } else {
                // [FIX] $produk null (produk dihapus) -> jangan panggil resolve() (TypeError);
                // fallback ke harga pcs yang sudah dihitung di atas.
                $cart[$key]['harga'] = $pcsPrice;
                $cart[$key]['diskon_tier'] = 0.0;
            }
        } else {
            $cart[$key]['qty'] = $qty;
            $cart[$key]['qty_satuan'] = $qty;
            $cart[$key]['sisa_pcs'] = 0;
            $cart[$key]['breakdown'] = '';
        }

        $cart[$key]['qty'] = $qty;

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

    /**
     * URL gambar full-size (utama) untuk preview.
     */
    private function gambarPreviewProduk(?Produk $produk): ?string
    {
        if (! $produk) {
            return null;
        }

        foreach ($produk->galeri_foto as $foto) {
            if (! empty($foto['is_primary']) && ! empty($foto['url'])) {
                return (string) $foto['url'];
            }
        }

        return ! empty($produk->gambar)
            ? Produk::formatFotoUrl($produk->gambar)
            : null;
    }

    /**
     * Faktor & harga jual box dari master produk (0 bila tidak ada).
     *
     * @return array{faktor: int, harga: float}
     */
    private function infoBoxProduk(?Produk $produk): array
    {
        if (! $produk) {
            return ['faktor' => 0, 'harga' => 0.0];
        }

        foreach ($produk->getDaftarSatuan() as $s) {
            if (strcasecmp((string) ($s['satuan_kode'] ?? ''), 'box') === 0) {
                return [
                    'faktor' => max(0, (int) round((float) ($s['faktor_konversi'] ?? 0))),
                    'harga' => (float) ($s['harga_jual'] ?? 0),
                ];
            }
        }

        return ['faktor' => 0, 'harga' => 0.0];
    }

    /**
     * Harga per base unit yang benar untuk satu baris keranjang.
     * Box penuh memakai harga box; sisa pcs memakai harga retail.
     */
    private function hargaBaseEfektif(array $item, ?Produk $produk): float
    {
        $fk = max(1.0, (float) ($item['faktor_konversi'] ?? 1));
        $satuan = (string) ($item['satuan'] ?? 'pcs');
        $baseSatuan = $produk?->satuan ?: 'pcs';
        $qty = max(1, (int) ($item['qty'] ?? 1));
        $hargaSatuan = (float) ($item['harga_satuan'] ?? 0);
        $pcsPrice = (float) ($item['harga_retail'] ?? $produk?->harga_jual_retail ?? $item['harga'] ?? 0);

        if ($fk <= 1 || strcasecmp($satuan, $baseSatuan) === 0 || $hargaSatuan <= 0 || $pcsPrice <= 0) {
            return (float) ($item['harga'] ?? $pcsPrice);
        }

        $faktorInt = max(1, (int) round($fk));
        $boxes = (int) ($item['qty_satuan'] ?? intdiv($qty, $faktorInt));
        $rem = (int) ($item['sisa_pcs'] ?? ($qty % $faktorInt));

        if ($boxes <= 0) {
            return $pcsPrice;
        }
        if ($rem <= 0) {
            return round($hargaSatuan / $fk, 2);
        }

        return round((($boxes * $hargaSatuan) + ($rem * $pcsPrice)) / $qty, 2);
    }

    public function hitungSubtotal(): float
    {
        $total = 0.0;
        foreach ($this->all() as $item) {
            $produk = ! empty($item['product_id']) ? Produk::find($item['product_id']) : null;
            $total += $this->hargaBaseEfektif($item, $produk) * (int) $item['qty'];
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

    public function hitungPotensiKomisi(): float
    {
        $total = 0.0;
        foreach ($this->all() as $item) {
            $total += ((float) ($item['potensi_komisi'] ?? 0.0)) * (int) ($item['qty'] ?? 1);
        }

        return $total;
    }

    public function hitungDiskonPartai(?Pelanggan $customer = null): array
    {
        $pelanggan = $customer ?: auth('customer')->user();
        $items = $this->all();

        return app(PartaiPricingService::class)->hitung($items, $pelanggan);
    }

    public function jumlahItem(): int
    {
        return array_sum(array_column($this->all(), 'qty'));
    }
}
