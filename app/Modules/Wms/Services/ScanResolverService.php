<?php

namespace App\Modules\Wms\Services;

use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\ProdukSatuanKonversi;
use App\Modules\Wms\Models\SkuVariant;

/**
 * Service Standarisasi Resolusi Pemindaian Barcode & QR Code.
 * Mendukung kode 1D (EAN/Code128/UPC), QR Code teks, QR Code URL, JSON payload,
 * format label stiker toko (UTP-XXXXX), SKU varian, dan barcode multi-satuan.
 */
class ScanResolverService
{
    /**
     * Bersihkan string barcode / QR code dari URL, prefix label, whitespace, control char.
     */
    public function cleanCode(string $raw): string
    {
        $code = trim($raw);

        // Hapus ASCII control characters (0-31 dan 127)
        $code = (string) preg_replace('/[\x00-\x1F\x7F]/', '', $code);

        if ($code === '') {
            return '';
        }

        // Cek jika payload berupa JSON (mis. QR sistem inventaris / vendor)
        if (str_starts_with($code, '{') && str_ends_with($code, '}')) {
            $json = json_decode($code, true);
            if (is_array($json)) {
                if (! empty($json['sku'])) {
                    return trim((string) $json['sku']);
                }
                if (! empty($json['barcode'])) {
                    return trim((string) $json['barcode']);
                }
                if (! empty($json['code'])) {
                    return trim((string) $json['code']);
                }
                if (! empty($json['id'])) {
                    return (string) $json['id'];
                }
            }
        }

        // Cek jika payload berupa URL (mis. QR katalog web konsumen / tautan produk)
        if (str_starts_with($code, 'http://') || str_starts_with($code, 'https://')) {
            $parts = parse_url($code);

            // Cek query parameters
            if (! empty($parts['query'])) {
                parse_str($parts['query'], $queryParams);
                if (! empty($queryParams['barcode'])) {
                    return trim((string) $queryParams['barcode']);
                }
                if (! empty($queryParams['sku'])) {
                    return trim((string) $queryParams['sku']);
                }
                if (! empty($queryParams['code'])) {
                    return trim((string) $queryParams['code']);
                }
                if (! empty($queryParams['id'])) {
                    return (string) $queryParams['id'];
                }
            }

            // Ambil slug / identifier dari segmen terakhir path URL
            if (! empty($parts['path'])) {
                $segments = array_values(array_filter(explode('/', trim($parts['path'], '/'))));
                if (! empty($segments)) {
                    $lastSegment = end($segments);

                    return urldecode($lastSegment);
                }
            }
        }

        return $code;
    }

    /**
     * Resolusi produk dari hasil pemindaian Barcode / QR Code.
     *
     * @return array{
     *     found: bool,
     *     type: string|null,
     *     produk: Produk|null,
     *     variant: SkuVariant|null,
     *     satuan_konversi: ProdukSatuanKonversi|null,
     *     satuan_kode: string|null,
     *     search_term: string
     * }
     */
    public function resolveProduct(string $rawCode): array
    {
        $clean = $this->cleanCode($rawCode);
        if ($clean === '') {
            return [
                'found' => false,
                'type' => null,
                'produk' => null,
                'variant' => null,
                'satuan_konversi' => null,
                'satuan_kode' => null,
                'search_term' => $clean,
            ];
        }

        // 1. Cek exact match varian (barcode atau SKU)
        $variant = SkuVariant::where('is_active', true)
            ->where(function ($q) use ($clean) {
                $q->where('barcode', $clean)->orWhere('sku', $clean);
            })
            ->with(['produk' => fn ($p) => $p->with('satuanKonversi')])
            ->first();

        if ($variant && $variant->produk && $variant->produk->is_active) {
            return [
                'found' => true,
                'type' => 'variant',
                'produk' => $variant->produk,
                'variant' => $variant,
                'satuan_konversi' => null,
                'satuan_kode' => $variant->produk->satuan ?: 'pcs',
                'search_term' => $clean,
            ];
        }

        // 2. Cek exact match kemasan multi-satuan (barcode satuan konversi)
        $satuanKonversi = ProdukSatuanKonversi::where('barcode', $clean)
            ->whereNotNull('barcode')
            ->where('barcode', '!=', '')
            ->with(['produk' => fn ($p) => $p->with('satuanKonversi')])
            ->first();

        if ($satuanKonversi && $satuanKonversi->produk && $satuanKonversi->produk->is_active) {
            return [
                'found' => true,
                'type' => 'satuan_konversi',
                'produk' => $satuanKonversi->produk,
                'variant' => null,
                'satuan_konversi' => $satuanKonversi,
                'satuan_kode' => $satuanKonversi->satuan_kode,
                'search_term' => $clean,
            ];
        }

        // 3. Cek format UTP-XXXXX (format cetak QR label stiker produk bawaan)
        if (preg_match('/^UTP-0*([1-9]\d*)$/i', $clean, $matches)) {
            $id = (int) $matches[1];
            $produk = Produk::where('is_active', true)->with('satuanKonversi')->find($id);
            if ($produk) {
                return [
                    'found' => true,
                    'type' => 'produk',
                    'produk' => $produk,
                    'variant' => null,
                    'satuan_konversi' => null,
                    'satuan_kode' => $produk->satuan ?: 'pcs',
                    'search_term' => $clean,
                ];
            }
        }

        // 4. Cek produk: barcode, slug, ID numerik, atau nama produk
        $produk = Produk::where('is_active', true)
            ->where(function ($query) use ($clean) {
                $query->where('barcode', $clean)
                    ->orWhere('slug', $clean)
                    ->when(is_numeric($clean) && (float) $clean <= 2147483647, fn ($sub) => $sub->orWhere('id', (int) $clean))
                    ->orWhere('nama', $clean);
            })
            ->with('satuanKonversi')
            ->first();

        if ($produk) {
            return [
                'found' => true,
                'type' => 'produk',
                'produk' => $produk,
                'variant' => null,
                'satuan_konversi' => null,
                'satuan_kode' => $produk->satuan ?: 'pcs',
                'search_term' => $clean,
            ];
        }

        // 5. Cek produk yang memiliki skuVariant dengan SKU / Barcode cocok
        $produkByVariant = Produk::where('is_active', true)
            ->whereHas('skuVariants', fn ($sv) => $sv->where('barcode', $clean)->orWhere('sku', $clean))
            ->with([
                'satuanKonversi',
                'skuVariants' => fn ($sv) => $sv->where('barcode', $clean)->orWhere('sku', $clean),
            ])
            ->first();

        if ($produkByVariant && $produkByVariant->skuVariants->isNotEmpty()) {
            return [
                'found' => true,
                'type' => 'variant',
                'produk' => $produkByVariant,
                'variant' => $produkByVariant->skuVariants->first(),
                'satuan_konversi' => null,
                'satuan_kode' => $produkByVariant->satuan ?: 'pcs',
                'search_term' => $clean,
            ];
        }

        return [
            'found' => false,
            'type' => null,
            'produk' => null,
            'variant' => null,
            'satuan_konversi' => null,
            'satuan_kode' => null,
            'search_term' => $clean,
        ];
    }
}
