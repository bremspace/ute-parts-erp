<?php

namespace App\Modules\Marketplace\Controllers;

use App\Modules\Marketplace\Models\Pengiriman;
use App\Modules\Marketplace\Services\BiteshipService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class ShippingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected BiteshipService $biteship
    ) {}

    /**
     * [API: SHIP-01] Cek ongkir Biteship — multi-origin (cabang/gudang terdekat dgn stok).
     */
    public function rates(Request $request)
    {
        $request->validate([
            'kode_pos_tujuan' => 'required|string|max:10',
            'items' => 'required|array|min:1',
            'items.*.produk_id' => 'required|exists:produk,id',
            'items.*.jumlah' => 'required|integer|min:1',
            'kurir' => 'nullable|string|max:100',
        ]);

        if (! $this->biteship->isConfigured()) {
            return $this->error('Biteship belum dikonfigurasi — set BITESHIP_API_KEY di .env', 503);
        }

        try {
            // Origin: semua cabang yang punya stok untuk item-order
            $origins = $this->resolveOrigins($request->items);

            if (empty($origins)) {
                return $this->error('Tidak ada cabang dengan stok mencukupi untuk seluruh item', 422);
            }

            // Items format Biteship: [{name, description, value, length, width, height, weight, quantity}]
            $items = collect($request->items)->map(function ($it) {
                $produk = Produk::find($it['produk_id']);

                return [
                    'name' => $produk?->nama ?? 'Sparepart',
                    'description' => $produk?->kategori ?? 'Sparepart',
                    'value' => (int) round($produk?->harga_jual_retail ?? 0),
                    'length' => 15, 'width' => 10, 'height' => 3,
                    'weight' => 200, // gram
                    'quantity' => (int) $it['jumlah'],
                ];
            })->values()->toArray();

            $rates = $this->biteship->getRates(
                $origins,
                $request->kode_pos_tujuan,
                $items,
                $request->kurir ?? 'jne'
            );

            return $this->success([
                'origins' => $origins,
                'rates' => $rates,
            ], 'Tarif kurir berhasil dimuat');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 502);
        }
    }

    /**
     * Buat pengiriman setelah order lunas → simpan tracking_id.
     */
    public function createShipment(Request $request)
    {
        $request->validate([
            'transaksi_id' => 'required|exists:transaksi,id',
            'kurir' => 'required|string',
            'layanan' => 'required|string',
            'ongkir' => 'required|numeric|min:0',
            'nama_penerima' => 'required|string|max:255',
            'alamat_tujuan' => 'required|string',
            'telepon_tujuan' => 'required|string|max:20',
            'kode_pos_tujuan' => 'required|string|max:10',
        ]);

        $transaksi = Transaksi::with('items.produk')->findOrFail($request->transaksi_id);

        if ($transaksi->status !== 'lunas') {
            return $this->error('Order harus lunas dahulu sebelum dikirim', 422);
        }

        $pengiriman = Pengiriman::create([
            'transaksi_id' => $transaksi->id,
            'kurir' => $request->kurir,
            'layanan' => $request->layanan,
            'ongkir' => $request->ongkir,
            'nama_penerima' => $request->nama_penerima,
            'alamat_tujuan' => $request->alamat_tujuan,
            'telepon_tujuan' => $request->telepon_tujuan,
            'status' => 'diproses',
        ]);

        // Panggil Biteship Order API jika terkonfigurasi
        if ($this->biteship->isConfigured()) {
            try {
                $origins = $this->resolveOrigins(
                    $transaksi->items->map(fn ($i) => ['produk_id' => $i->produk_id, 'jumlah' => (int) $i->jumlah])->toArray()
                );
                $origin = $origins[0] ?? [0, 0];

                $order = $this->biteship->createOrder([
                    'origin_coordinates' => [$origin],
                    'destination' => [
                        'contact_name' => $request->nama_penerima,
                        'contact_phone' => $request->telepon_tujuan,
                        'address' => $request->alamat_tujuan,
                        'postal_code' => $request->kode_pos_tujuan,
                    ],
                    'courier_company' => $request->kurir,
                    'courier_type' => $request->layanan,
                    'items' => $transaksi->items->map(fn ($i) => [
                        'name' => $i->produk?->nama ?? 'Item',
                        'quantity' => (int) $i->jumlah,
                    ])->values()->toArray(),
                ]);

                $pengiriman->update([
                    'tracking_id' => $order['tracking_id'],
                    'status' => 'dikirim',
                ]);
            } catch (\Exception $e) {
                // Jika Biteship gagal, pengiriman tetap tercatat "diproses" — bisa di-retry manual
                Log::warning('Biteship createOrder gagal: '.$e->getMessage());
            }
        }

        return $this->success($pengiriman, 'Pengiriman dibuat');
    }

    /**
     * Resolusi origin (cabang-coordinate) yang punya semua item stok.
     * Koordinat dummy per cabang — siap konsumsi mapping lat/lon real dari tabel cabang (kolom belum tersedia, pakai indeks).
     */
    private function resolveOrigins(array $items): array
    {
        $cabangs = Cabang::where('is_active', true)->get();
        $origins = [];

        foreach ($cabangs as $cabang) {
            $stokCukup = true;
            foreach ($items as $item) {
                $total = StokItem::where('produk_id', $item['produk_id'])
                    ->whereHas('gudang', fn ($q) => $q->where('cabang_id', $cabang->id))
                    ->sum('jumlah');

                if ($total < (int) $item['jumlah']) {
                    $stokCukup = false;
                    break;
                }
            }

            if ($stokCukup) {
                $hash = crc32($cabang->kode);
                // Pseudo lat/lon deterministik per cabang (± ekstensi Jakarta)
                $origins[] = [
                    round(-6.2 + (($hash % 100) - 50) / 1000, 6),
                    round(106.8 + (($hash % 71) - 35) / 1000, 6),
                ];
            }
        }

        return $origins;
    }
}
