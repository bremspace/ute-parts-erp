<?php

namespace App\Modules\Wms\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Peta lokasi fisik barang (rak/gudang) untuk keperluan picking pesanan.
 */
class LokasiBarangService
{
    /**
     * @param  array<int, mixed>|Collection  $produkIds
     * @return array<int, array<int, string>> produk_id => daftar lokasi
     */
    public function peta(array|Collection $produkIds, ?int $gudangId = null): array
    {
        $ids = collect($produkIds)->filter()->unique()->map(fn ($id) => (int) $id)->values()->all();

        if ($ids === []) {
            return [];
        }

        $query = DB::table('stok_items')
            ->join('rak', 'rak.id', '=', 'stok_items.rak_id')
            ->leftJoin('gudang', 'gudang.id', '=', 'stok_items.gudang_id')
            ->whereIn('stok_items.produk_id', $ids)
            ->whereNotNull('stok_items.rak_id')
            ->select(
                'stok_items.produk_id',
                'rak.kode as rak_kode',
                'rak.nama as rak_nama',
                'rak.zona as rak_zona',
                'gudang.nama as gudang_nama'
            );

        if ($gudangId) {
            $query->where('stok_items.gudang_id', $gudangId);
        }

        return $query->distinct()->get()
            ->groupBy('produk_id')
            ->map(function ($rows) {
                return $rows->map(function ($row) {
                    $rak = trim((string) ($row->rak_kode ?: $row->rak_nama));
                    if ($rak === '') {
                        return null;
                    }
                    if (! empty($row->rak_zona)) {
                        $rak .= ' · Zona '.$row->rak_zona;
                    }
                    if (! empty($row->gudang_nama)) {
                        $rak = $row->gudang_nama.' · '.$rak;
                    }

                    return $rak;
                })->filter()->unique()->values()->all();
            })
            ->filter(fn ($lokasi) => $lokasi !== [])
            ->all();
    }
}
