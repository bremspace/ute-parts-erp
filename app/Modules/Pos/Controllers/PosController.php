<?php

namespace App\Modules\Pos\Controllers;

use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Akunting\Services\PajakService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Jobs\PrintThermalJob;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Pos\Services\KasSesiState;
use App\Modules\Pos\Services\PricingService;
use App\Modules\Reseller\Services\KomisiService;
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
        $gudangId = $request->query('gudang_id', session('gudang_id'));
        $customerId = $request->query('customer_id');

        // [T-07] Mode ringkas utk autocomplete/debounce: ?q= atau ?query= → payload kecil, limit 10
        $q = $request->query('q', $request->query('query', ''));
        if ($q !== '') {
            $query = Produk::query()
                ->where('is_active', true)
                ->with(['skuVariants' => fn ($sq) => $sq->where('is_active', true)])
                ->where(fn ($sub) => $sub
                    ->where('nama', 'like', "%{$q}%")
                    ->orWhere('kategori', 'like', "%{$q}%")
                    ->orWhere('brand_kompatibel', 'like', "%{$q}%")
                    ->orWhere('model_kompatibel', 'like', "%{$q}%")
                    ->orWhereHas('skuVariants', fn ($sq) => $sq->where('sku', 'like', "%{$q}%"))
                );

            $pelanggan = $customerId ? Pelanggan::with('tierMembership')->find($customerId) : null;

            $items = $query->limit(10)->get()->map(fn ($product) => [
                'id' => $product->id,
                'nama' => $product->nama,
                'sku' => $product->skuVariants->first()?->sku,
                'harga' => (float) $this->pricingService->resolve($product, $pelanggan)['harga'],
                'stok' => $gudangId
                    ? (int) StokItem::where('produk_id', $product->id)->where('gudang_id', $gudangId)->sum('jumlah')
                    : 0,
                'foto' => $product->foto[0] ?? $product->gambar,
            ]);

            return $this->success($items, 'Hasil pencarian produk (ringkas)');
        }

        // Jalur legacy (param `search`) — respons paginasi penuh, tidak berubah
        $search = $request->query('search', '');

        $pelanggan = $customerId ? Pelanggan::with('tierMembership')->find($customerId) : null;

        $query = Produk::query()
            ->where('is_active', true)
            ->with(['skuVariants' => function ($q) {
                $q->where('is_active', true);
            }]);

        if (! empty($search)) {
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

        // [T-09] Validasi sesi kas utk pembayaran tunai (blokir bila belum buka kas)
        if ($request->metode_bayar === 'tunai') {
            $kasSesi = app(KasSesiState::class);
            if (! $kasSesi->isActiveSesi()) {
                return $this->error('Kas belum dibuka — buka sesi kas terlebih dahulu sebelum transaksi tunai', 422);
            }
        }

        $cabangId = session('cabang_id') ?? auth()->user()->cabangs()->first()?->id;
        if (! $cabangId) {
            return $this->error('Cabang aktif belum dipilih', 400);
        }

        $gudangId = $request->gudang_id ?? session('gudang_id');
        if (! $gudangId) {
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

            // [F1-2] PPN per cabang: DPP = subtotal - diskon; totalAkhir = DPP + PPN
            $dpp = max(0, $subtotal - $diskonHeader);
            $pajak = app(PajakService::class)->hitung($cabangId, $dpp);
            $ppnNominal = (float) $pajak['ppn_nominal'];

            $totalAkhir = $dpp + $ppnNominal;
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
                'dpp' => $dpp,
                'pajak_nominal' => $ppnNominal,
                'ppn_nominal' => $ppnNominal,
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
            $jurnalService = app(JurnalService::class);
            $totalHpp = 0.0;
            foreach ($itemsData as $row) {
                $totalHpp += (float) $row['hpp'] * $row['jumlah'];
            }

            $noJurnal = $jurnalService->generateNoJurnal('pos', $cabangId);
            $kasbon = $request->metode_bayar === 'piutang';

            // Kasbon (piutang): debit Piutang Usaha 120-01, bukan Kas 110-01
            // [F1-2] Balance: debit totalAkhir = kredit (DPP 410-01 + PPN 220-01)
            $lines = [
                ['akun_kode' => $kasbon ? '120-01' : '110-01', 'debit' => (float) $totalAkhir, 'kredit' => 0],
                ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => $ppnNominal > 0 ? (float) $dpp : (float) $totalAkhir],  // Pendapatan = DPP
            ];
            // PPN Keluaran → akun 220-01 (kontrak AC F1-2)
            foreach (app(PajakService::class)->jurnalLines($ppnNominal, $noJurnal, $cabangId, auth()->id() ?? 0) as $ppnLine) {
                $lines[] = $ppnLine;
            }
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
                $countPiutang = Piutang::where('cabang_id', $cabangId)
                    ->whereDate('created_at', now()->toDateString())
                    ->count() + 1;
                Piutang::create([
                    'no_piutang' => sprintf('AR-%s-%04d', now()->format('Ymd'), $countPiutang),
                    'pelanggan_id' => $request->pelanggan_id,
                    'transaksi_id' => $transaksi->id,
                    'cabang_id' => $cabangId,
                    'jumlah' => $totalAkhir,
                    'jumlah_dibayar' => 0,
                    'jatuh_tempo' => now()->addDays(30)->toDateString(),
                    'status' => 'belum_lunas',
                    'keterangan' => 'Kasbon POS '.$noTransaksi,
                ]);
            }

            // Komisi reseller (PRD §4.5): jika pembeli reseller, hitung komisi pending
            if ($request->pelanggan_id) {
                $pelanggan = Pelanggan::find($request->pelanggan_id);
                if ($pelanggan && $pelanggan->is_reseller) {
                    app(KomisiService::class)->hitungKomisi($transaksi, $pelanggan);
                }
            }

            return $this->success(
                $transaksi->load(['items.produk', 'items.skuVariant', 'pelanggan', 'cabang']),
                'Transaksi berhasil diproses',
                201
            );
        });
    }

    // [API: POS-05] Daftar transaksi (filter status, di-scope cabang; default cabang aktif)
    public function index(Request $request)
    {
        $status = $request->query('status');
        $cabangId = $request->query('cabang_id') ?? session('cabang_id');

        $query = Transaksi::with(['items', 'pelanggan', 'kasir', 'cabang'])
            ->latest();

        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        return $this->success($query->paginate($request->query('per_page', 20)), 'Daftar transaksi berhasil dimuat');
    }

    // [API: POS-04] Tahan transaksi (park) — simpan cart ke split_detail, status ditahan (belum kurangi stok)
    public function tahan(Request $request, $id)
    {
        $transaksi = Transaksi::where('id', $id)
            ->where('cabang_id', session('cabang_id') ?? $request->user()->cabangs()->first()?->id)
            ->where('status', 'draft')
            ->firstOrFail();

        $transaksi->update(['status' => 'ditahan']); // split_detail sudah berisi cart

        return $this->success($transaksi, 'Transaksi ditahan — bisa dilanjutkan kasir lain');
    }

    // [API: POS-04b] Lanjutkan transaksi ditahan (resume) — status kembali draft utk dilanjutkan
    public function resume(Request $request, $id)
    {
        $transaksi = Transaksi::where('id', $id)
            ->where('cabang_id', session('cabang_id') ?? $request->user()->cabangs()->first()?->id)
            ->where('status', 'ditahan')
            ->firstOrFail();

        $transaksi->update([
            'status' => 'draft',
            'kasir_id' => auth()->id(),
        ]);

        return $this->success($transaksi->load('items'), 'Transaksi dilanjutkan');
    }

    // [API: POS-06] Cari pelanggan utk POS (satu sumber: pelanggan CRM)
    public function pelanggan(Request $request)
    {
        $search = $request->query('search', '');

        $query = Pelanggan::with('tierMembership')
            ->where('is_active', true)
            ->latest();

        if (strlen($search) >= 2) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('telepon', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $this->success($query->limit(10)->get(), 'Daftar pelanggan berhasil dimuat');
    }

    // [API: POS-07] Buka kas sesi
    public function bukaKas(Request $request)
    {
        $request->validate([
            'saldo_awal' => 'required|numeric|min:0',
            'cabang_id' => 'nullable|exists:cabang,id',
        ]);

        try {
            $sesi = app(KasSesiState::class)->bukaKas(
                (float) $request->saldo_awal,
                $request->cabang_id ?? session('cabang_id'),
                auth()->id()
            );

            return $this->success($sesi, 'Kas berhasil dibuka');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // [API: POS-08] Tutup kas sesi
    public function tutupKas(Request $request)
    {
        $request->validate([
            'saldo_fisik' => 'required|numeric|min:0',
        ]);

        try {
            $hasil = app(KasSesiState::class)->tutupKas((float) $request->saldo_fisik);

            return $this->success($hasil, 'Kas berhasil ditutup');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // [API: POS-09] Riwayat sesi kas per cabang
    public function riwayatKas(Request $request)
    {
        return $this->success(
            app(KasSesiState::class)->riwayat($request->cabang_id ?? session('cabang_id')),
            'Riwayat sesi kas berhasil dimuat'
        );
    }

    // [API: POS-10][T-35] Cetak struk thermal — hanya antri, tidak blocking request
    public function printStruk(int $id)
    {
        $cabangId = session('cabang_id') ?? auth()->user()->cabangs()->first()?->id;

        $transaksi = Transaksi::where('id', $id)
            ->where('cabang_id', $cabangId)
            ->first();

        if (! $transaksi) {
            return $this->error('Transaksi tidak ditemukan', 404);
        }

        PrintThermalJob::dispatch($transaksi->id);

        return $this->success(
            ['no_transaksi' => $transaksi->no_transaksi],
            'Struk masuk antrian cetak'
        );
    }
}
