<?php

namespace App\Modules\Pos\Controllers;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Pos\Services\PricingService;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class PosController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PricingService $pricingService
    ) {}

    // [API: POS-02] Cari produk + stok untuk kasir
    public function products(Request $request)
    {
        $search = $request->query('search', '');
        $gudangId = $request->query('gudang_id', session('gudang_id'));
        $customerId = $request->query('customer_id');

        $pelanggan = $customerId ? Pelanggan::with('tierMembership')->find($customerId) : null;

        $query = Produk::query()
            ->where('is_active', true)
            ->with(['skuVariants' => function ($q) {
                $q->where('is_active', true);
            }]);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kategori', 'like', "%{$search}%")
                  ->orWhere('brand_kompatibel', 'like', "%{$search}%")
                  ->orWhere('model_kompatibel', 'like', "%{$search}%")
                  ->orWhereHas('skuVariants', function ($sq) use ($search) {
                      $sq->where('sku', 'like', "%{$search}%")
                         ->orWhere('nama_varian', 'like', "%{$search}%");
                  });
            });
        }

        $products = $query->paginate(24);

        // Append real-time stock and resolved price
        $items = $products->getCollection()->map(function ($product) use ($gudangId, $pelanggan) {
            $stokTotal = 0;
            if ($gudangId) {
                $stokTotal = StokItem::where('produk_id', $product->id)
                    ->where('gudang_id', $gudangId)
                    ->sum('jumlah');
            }

            $pricing = $this->pricingService->resolve($product, $pelanggan);

            return [
                'id' => $product->id,
                'nama' => $product->nama,
                'slug' => $product->slug,
                'kategori' => $product->kategori,
                'brand_kompatibel' => $product->brand_kompatibel,
                'model_kompatibel' => $product->model_kompatibel,
                'kondisi' => $product->kondisi,
                'satuan' => $product->satuan,
                'harga_retail' => (float) $product->harga_jual_retail,
                'harga_final' => $pricing['harga'],
                'diskon_nominal' => $pricing['diskon_nominal'],
                'alasan_harga' => $pricing['alasan'],
                'stok' => (int) $stokTotal,
                'gambar' => $product->gambar,
                'variants' => $product->skuVariants->map(function ($variant) use ($gudangId, $product, $pelanggan) {
                    $stokVariant = 0;
                    if ($gudangId) {
                        $stokVariant = StokItem::where('produk_id', $product->id)
                            ->where('sku_variant_id', $variant->id)
                            ->where('gudang_id', $gudangId)
                            ->value('jumlah') ?? 0;
                    }
                    $variantPricing = $this->pricingService->resolve($product, $pelanggan, $variant);

                    return [
                        'id' => $variant->id,
                        'sku' => $variant->sku,
                        'nama_varian' => $variant->nama_varian,
                        'atribut' => $variant->atribut,
                        'harga_final' => $variantPricing['harga'],
                        'stok' => (int) $stokVariant,
                    ];
                }),
            ];
        });

        return $this->success([
            'items' => $items,
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'total' => $products->total(),
        ], 'Daftar produk berhasil dimuat');
    }

    // [API: PRICING-01] Resolusi harga final per pelanggan
    public function resolvePrice(Request $request, $produk_id)
    {
        $produk = Produk::findOrFail($produk_id);
        $pelanggan = $request->customer_id ? Pelanggan::with('tierMembership')->find($request->customer_id) : null;
        $variant = $request->variant_id ? SkuVariant::find($request->variant_id) : null;

        $pricing = $this->pricingService->resolve($produk, $pelanggan, $variant);

        return $this->success($pricing, 'Resolusi harga berhasil');
    }

    // [API: POS-01] Buat transaksi kasir
    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.produk_id' => 'required|exists:produk,id',
            'items.*.sku_variant_id' => 'nullable|exists:sku_variants,id',
            'items.*.jumlah' => 'required|integer|min:1',
            'items.*.harga_satuan' => 'required|numeric|min:0',
            'items.*.diskon_nominal' => 'nullable|numeric|min:0',
            'metode_bayar' => 'required|in:tunai,transfer,qris,split,piutang',
            'jumlah_bayar' => 'required|numeric|min:0',
            'pelanggan_id' => 'nullable|exists:pelanggan,id',
            'gudang_id' => 'nullable|exists:gudang,id',
            'diskon_nominal' => 'nullable|numeric|min:0',
            'diskon_persen' => 'nullable|numeric|min:0',
            'catatan' => 'nullable|string',
            'split_detail' => 'nullable|array',
        ]);

        $cabangId = session('cabang_id') ?? auth()->user()->cabangs()->first()?->id;
        if (!$cabangId) {
            return $this->error('Cabang aktif belum dipilih', 400);
        }

        $gudangId = $request->gudang_id ?? session('gudang_id');
        if (!$gudangId) {
            $firstGudang = Gudang::where('cabang_id', $cabangId)->first();
            $gudangId = $firstGudang?->id;
        }

        return DB::transaction(function () use ($request, $cabangId, $gudangId) {
            // Generate nomor transaksi unik
            $today = now()->format('Ymd');
            $countToday = Transaksi::whereDate('created_at', now()->toDateString())
                ->where('cabang_id', $cabangId)
                ->count() + 1;
            $noTransaksi = sprintf('TRX-C%02d-%s-%04d', $cabangId, $today, $countToday);

            $subtotal = 0;
            $itemsData = [];

            // Validasi & siapkan stok
            foreach ($request->items as $item) {
                $produk = Produk::findOrFail($item['produk_id']);
                $qty = (int) $item['jumlah'];
                $hargaSatuan = (float) $item['harga_satuan'];
                $diskonItem = (float) ($item['diskon_nominal'] ?? 0);
                $lineSubtotal = max(0, ($hargaSatuan * $qty) - $diskonItem);
                $subtotal += $lineSubtotal;

                // Cek ketersediaan stok di gudang jika gudang ditentukan
                if ($gudangId) {
                    $stokItem = StokItem::firstOrCreate(
                        [
                            'produk_id' => $produk->id,
                            'sku_variant_id' => $item['sku_variant_id'] ?? null,
                            'gudang_id' => $gudangId,
                        ],
                        ['jumlah' => 0, 'jumlah_minimum' => 0]
                    );

                    if ($stokItem->jumlah < $qty) {
                        throw new \Exception("Stok tidak mencukupi untuk {$produk->nama}. Tersedia: {$stokItem->jumlah}, diminta: {$qty}");
                    }
                }

                $itemsData[] = [
                    'produk' => $produk,
                    'sku_variant_id' => $item['sku_variant_id'] ?? null,
                    'jumlah' => $qty,
                    'harga_satuan' => $hargaSatuan,
                    'diskon_nominal' => $diskonItem,
                    'subtotal' => $lineSubtotal,
                    'hpp' => (float) $produk->harga_beli,
                ];
            }

            $diskonHeader = (float) ($request->diskon_nominal ?? 0);
            if ($request->diskon_persen > 0) {
                $diskonHeader += round(($subtotal * (float) $request->diskon_persen) / 100, 2);
            }

            $totalAkhir = max(0, $subtotal - $diskonHeader);
            $jumlahBayar = (float) $request->jumlah_bayar;
            $kembalian = max(0, $jumlahBayar - $totalAkhir);

            $transaksi = Transaksi::create([
                'no_transaksi' => $noTransaksi,
                'cabang_id' => $cabangId,
                'kasir_id' => auth()->id(),
                'pelanggan_id' => $request->pelanggan_id,
                'gudang_id' => $gudangId,
                'sumber' => 'pos',
                'subtotal' => $subtotal,
                'diskon_persen' => (float) ($request->diskon_persen ?? 0),
                'diskon_nominal' => $diskonHeader,
                'pajak_nominal' => 0,
                'total_akhir' => $totalAkhir,
                'metode_bayar' => $request->metode_bayar,
                'jumlah_bayar' => $jumlahBayar,
                'kembalian' => $kembalian,
                'split_detail' => $request->split_detail,
                'status' => 'selesai',
                'catatan' => $request->catatan,
            ]);

            // Buat item transaksi & kurangi stok
            foreach ($itemsData as $row) {
                TransaksiItem::create([
                    'transaksi_id' => $transaksi->id,
                    'produk_id' => $row['produk']->id,
                    'sku_variant_id' => $row['sku_variant_id'],
                    'jumlah' => $row['jumlah'],
                    'harga_satuan' => $row['harga_satuan'],
                    'diskon_nominal' => $row['diskon_nominal'],
                    'subtotal' => $row['subtotal'],
                    'hpp' => $row['hpp'],
                ]);

                if ($gudangId) {
                    $stok = StokItem::where('produk_id', $row['produk']->id)
                        ->where('sku_variant_id', $row['sku_variant_id'])
                        ->where('gudang_id', $gudangId)
                        ->first();

                    $sebelum = $stok ? $stok->jumlah : 0;
                    $setelah = $sebelum - $row['jumlah'];

                    if ($stok) {
                        $stok->update(['jumlah' => $setelah]);
                    }

                    StokLog::create([
                        'gudang_id' => $gudangId,
                        'produk_id' => $row['produk']->id,
                        'sku_variant_id' => $row['sku_variant_id'],
                        'user_id' => auth()->id(),
                        'jenis' => 'penjualan',
                        'referensi_tipe' => Transaksi::class,
                        'referensi_id' => $transaksi->id,
                        'jumlah_sebelum' => $sebelum,
                        'perubahan' => -$row['jumlah'],
                        'jumlah_setelah' => $setelah,
                        'catatan' => "POS Penjualan {$transaksi->no_transaksi}",
                    ]);
                }
            }

            // Jika pelanggan terdaftar, update akumulasi belanja & poin loyalty
            if ($request->pelanggan_id) {
                $pelanggan = Pelanggan::find($request->pelanggan_id);
                if ($pelanggan) {
                    $pelanggan->increment('total_belanja_12bulan', $totalAkhir);
                    // Poin: 1% nominal belanja dikali multiplier tier
                    $multiplier = $pelanggan->tierMembership ? (float) $pelanggan->tierMembership->poin_multiplier : 1.0;
                    $poinTambahan = (int) floor(($totalAkhir / 1000) * $multiplier);
                    if ($poinTambahan > 0) {
                        $pelanggan->increment('poin_loyalty', $poinTambahan);
                    }
                }
            }

// Jurnal akuntansi otomatis (PRD §4.6): Kas masuk, Pendapatan, HPP, Persediaan turun
            $jurnalService = app(\App\Modules\Akunting\Services\JurnalService::class);
            $totalHpp = 0.0;
            foreach ($itemsData as $row) {
                $totalHpp += (float) $row['hpp'] * $row['jumlah'];
            }

            $noJurnal = $jurnalService->generateNoJurnal('pos', $cabangId);
            $kasbon = $request->metode_bayar === 'piutang';

            // Kasbon (piutang): debit Piutang Usaha 120-01, bukan Kas 110-01
            $lines = [
                ['akun_kode' => $kasbon ? '120-01' : '110-01', 'debit' => (float) $totalAkhir, 'kredit' => 0],
                ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => (float) $totalAkhir],  // Pendapatan Penjualan
            ];
            if ($totalHpp > 0) {
                $lines[] = ['akun_kode' => '510-02', 'debit' => $totalHpp, 'kredit' => 0]; // HPP
                $lines[] = ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => $totalHpp]; // Persediaan turun
            }

            $jurnalService->post(
                $noJurnal,
                now(),
                'pos',
                $lines,
                "Jurnal POS {$noTransaksi}",
                $cabangId,
                auth()->id(),
                Transaksi::class,
                $transaksi->id
            );

            // Kasbon → catat Piutang (AR)
            if ($kasbon && $request->pelanggan_id) {
                $countPiutang = \App\Modules\Akunting\Models\Piutang::whereDate('created_at', now()->toDateString())->count() + 1;
                \App\Modules\Akunting\Models\Piutang::create([
                    'no_piutang'    => sprintf('AR-%s-%04d', now()->format('Ymd'), $countPiutang),
                    'pelanggan_id'  => $request->pelanggan_id,
                    'transaksi_id'  => $transaksi->id,
                    'jumlah'        => $totalAkhir,
                    'jumlah_dibayar'=> 0,
                    'jatuh_tempo'   => now()->addDays(30)->toDateString(),
                    'status'        => 'belum_lunas',
                    'keterangan'    => 'Kasbon POS ' . $noTransaksi,
                ]);
            }

            // Komisi reseller (PRD §4.5): jika pembeli reseller, hitung komisi pending
            if ($request->pelanggan_id) {
                $pelanggan = Pelanggan::find($request->pelanggan_id);
                if ($pelanggan && $pelanggan->is_reseller) {
                    app(\App\Modules\Reseller\Services\KomisiService::class)->hitungKomisi($transaksi, $pelanggan);
                }
            }

            return $this->success(
                $transaksi->load(['items.produk', 'items.skuVariant', 'pelanggan', 'cabang']),
                'Transaksi berhasil diproses',
                201
            );
        });
    }
}
