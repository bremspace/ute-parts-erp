<?php

namespace App\Modules\Pos\Controllers;

use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Services\PajakService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Exceptions\StokTidakCukupException;
use App\Modules\Pos\Jobs\PrintThermalJob;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Services\KasSesiState;
use App\Modules\Pos\Services\PosCheckoutService;
use App\Modules\Pos\Services\PosOfflineSyncService;
use App\Modules\Pos\Services\PricingService;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Services\StockReservationService;
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

    private function resolveCabangId(Request $request): int
    {
        $requestedCabangId = $request->query('cabang_id', $request->input('cabang_id'));
        $sessionCabangId = session('cabang_id');
        $user = $request->user();

        $cabangId = $requestedCabangId ? (int) $requestedCabangId : ($sessionCabangId ? (int) $sessionCabangId : null);

        if (! $cabangId && $user) {
            $cabangId = $user->cabangs()->first()?->id ?? 1;
        }

        $cabangId = $cabangId ?? 1;

        if ($user && ! $user->bisaAksesCabang($cabangId)) {
            abort(403, 'Anda tidak memiliki akses ke cabang ini.');
        }

        return $cabangId;
    }

    // [API: POS-02] Cari produk + stok untuk kasir
    public function products(Request $request)
    {
        $gudangId = $request->query('gudang_id', session('gudang_id'));
        $cabangId = $this->resolveCabangId($request);
        $customerId = $request->query('customer_id');

        // [T-07] Mode ringkas utk autocomplete/debounce: ?q= atau ?query= → payload kecil, limit 10
        $q = $request->query('q', $request->query('query', ''));
        if ($q !== '') {
            $query = Produk::query()
                ->where('is_active', true)
                ->with(['skuVariants' => fn ($sq) => $sq->where('is_active', true), 'hargaTier'])
                ->cariPintar($q)
                ->urutKetersediaanDanTerlaris($gudangId, $cabangId);

            $pelanggan = $customerId ? Pelanggan::with('tierMembership')->find($customerId) : null;

            $rawProducts = $query->limit(10)->get();

            $items = $rawProducts->map(fn ($product) => [
                'id' => $product->id,
                'nama' => $product->nama,
                'sku' => $product->skuVariants->first()?->sku,
                'harga' => (float) $this->pricingService->resolve($product, $pelanggan)['harga'],
                'stok' => (int) ($product->stok_katalog ?? 0),
                'foto' => $product->foto[0] ?? $product->gambar,
            ]);

            return $this->success($items, 'Hasil pencarian produk (ringkas)');
        }

        // Jalur legacy (param `search`) — respons paginasi penuh, tidak berubah
        $search = $request->query('search', '');

        $pelanggan = $customerId ? Pelanggan::with('tierMembership')->find($customerId) : null;

        $query = Produk::query()
            ->where('is_active', true)
            ->with([
                'skuVariants' => fn ($q) => $q->where('is_active', true),
                'hargaTier',
            ])
            ->urutKetersediaanDanTerlaris($gudangId, $cabangId);

        if (! empty($search)) {
            $query->cariPintar($search);
        }

        $products = $query->paginate(24);

        $productIds = $products->getCollection()->pluck('id')->all();
        $stokItems = ($gudangId && ! empty($productIds))
            ? StokItem::whereIn('produk_id', $productIds)
                ->where('gudang_id', $gudangId)
                ->select(['produk_id', 'sku_variant_id', 'jumlah'])
                ->get()
            : collect();

        $stokMap = [];
        $stokVariantMap = [];
        foreach ($stokItems as $si) {
            $stokMap[$si->produk_id] = ($stokMap[$si->produk_id] ?? 0) + $si->jumlah;
            if ($si->sku_variant_id) {
                $stokVariantMap[$si->produk_id.'_'.$si->sku_variant_id] = $si->jumlah;
            }
        }

        // Append real-time stock and resolved price (bebas N+1)
        $items = $products->getCollection()->map(function ($product) use ($stokMap, $stokVariantMap, $pelanggan) {
            $stokTotal = (int) ($stokMap[$product->id] ?? 0);
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
                'variants' => $product->skuVariants->map(function ($variant) use ($stokVariantMap, $product, $pelanggan) {
                    $stokVariant = (int) ($stokVariantMap[$product->id.'_'.$variant->id] ?? 0);
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

    // [API: POS-OFFLINE-01] Master data produk dan pelanggan untuk cache offline
    public function masterOffline(Request $request)
    {
        $cabangId = $this->resolveCabangId($request);
        $gudangIds = Gudang::where('cabang_id', $cabangId)->pluck('id');

        $products = Produk::query()
            ->where('is_active', true)
            ->with(['hargaTier', 'skuVariants' => fn ($q) => $q->where('is_active', true)])
            ->get()
            ->map(function ($p) use ($gudangIds, $cabangId) {
                $stok = (int) StokItem::where('produk_id', $p->id)
                    ->whereIn('gudang_id', $gudangIds)
                    ->sum('jumlah');

                $bengkelTier = $p->hargaTier->firstWhere('tipe_konsumen', 'reseller')
                    ?? $p->hargaTier->firstWhere('tier_membership_id', 2);
                $grosirTier = $p->hargaTier->firstWhere('tipe_konsumen', 'agen')
                    ?? $p->hargaTier->firstWhere('tier_membership_id', 3);

                $hargaBengkel = $bengkelTier?->nominal_tetap ?? $bengkelTier?->harga ?? $p->harga_jual_retail;
                $hargaGrosir = $grosirTier?->nominal_tetap ?? $grosirTier?->harga ?? $p->harga_jual_retail;

                return [
                    'id' => $p->id,
                    'kode' => $p->skuVariants->first()?->sku ?? ('PRD-'.$p->id),
                    'barcode' => $p->barcode ?? $p->skuVariants->first()?->sku ?? ('PRD-'.$p->id),
                    'nama' => $p->nama,
                    'kategori' => $p->kategori ?? 'Umum',
                    'harga_retail' => (float) $p->harga_jual_retail,
                    'harga_bengkel' => (float) $hargaBengkel,
                    'harga_grosir' => (float) $hargaGrosir,
                    'stok_terakhir' => $stok,
                    'gudang_id' => $gudangIds->first() ?? null,
                    'cabang_id' => $cabangId,
                    'updated_at' => $p->updated_at?->toIso8601String(),
                ];
            });

        $customers = Pelanggan::query()
            ->where('status', 'aktif')
            ->with('tierMembership')
            ->limit(200)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'nama' => $c->nama,
                'telepon' => $c->telepon,
                'tier' => strtolower($c->tierMembership?->nama ?? 'retail'),
            ]);

        return $this->success([
            'cabang_id' => $cabangId,
            'synced_at' => now()->toIso8601String(),
            'products' => $products,
            'customers' => $customers,
        ], 'Master data offline berhasil dimuat');
    }

    // [API: POS-OFFLINE-02] Sinkronisasi antrean transaksi offline dari kasir
    public function syncOffline(Request $request)
    {
        $request->validate([
            'schema_version' => 'nullable|integer',
            'transactions' => 'required|array|min:1',
            'transactions.*.offline_uuid' => 'required|string',
            'transactions.*.items' => 'required|array|min:1',
            'transactions.*.items.*.produk_id' => 'required|integer',
            'client_batch_id' => 'nullable|string|max:100',
        ]);

        $cabangId = $this->resolveCabangId($request);
        $userId = auth()->id() ?? (int) $request->input('user_id', 1);

        $syncService = app(PosOfflineSyncService::class);
        $result = $syncService->syncBatch(
            transactions: $request->input('transactions'),
            cabangId: $cabangId,
            userId: $userId,
            batchId: $request->input('client_batch_id'),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return $this->success($result, 'Proses sinkronisasi batch offline selesai diproses');
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
            'items.*.satuan' => 'nullable|string|max:50',
            'items.*.konversi' => 'nullable|numeric|min:0.0001',
            'metode_bayar' => 'required|in:tunai,transfer,qris,split,piutang',
            'jumlah_bayar' => 'required|numeric|min:0',
            'pelanggan_id' => 'nullable|exists:pelanggan,id',
            'gudang_id' => 'nullable|exists:gudang,id',
            'diskon_nominal' => 'nullable|numeric|min:0',
            'diskon_persen' => 'nullable|numeric|min:0',
            'diskon_poin' => 'nullable|numeric|min:0',
            'poin_ditukar' => 'nullable|integer|min:0',
            'diskon_partai' => 'nullable|numeric|min:0',
            'diskon_manual_alasan' => 'nullable|string',
            'diskon_manual_authorized_by_id' => 'nullable|exists:users,id',
            'durasi_garansi_hari' => 'nullable|integer|min:0',
            'garansi_teks' => 'nullable|string|max:255',
            'garansi_berakhir_pada' => 'nullable|date',
            'catatan_garansi' => 'nullable|string|max:1000',
            'mode_reward_mitra' => 'nullable|string|in:diskon_langsung,komisi,deposit',
            'komisi_mitra_nominal' => 'nullable|numeric|min:0',
            'catatan' => 'nullable|string',
            'split_detail' => 'nullable|array',
        ]);

        // Validasi kasbon / piutang wajib memilih pelanggan
        if ($request->metode_bayar === 'piutang' && ! $request->pelanggan_id) {
            return $this->error('Pelanggan wajib dipilih untuk transaksi kasbon / piutang', 422);
        }

        // [T-09] Validasi sesi kas utk pembayaran tunai (blokir bila belum buka kas)
        if ($request->metode_bayar === 'tunai') {
            $kasSesi = app(KasSesiState::class);
            if (! $kasSesi->isActiveSesi()) {
                return $this->error('Kas belum dibuka — buka sesi kas terlebih dahulu sebelum transaksi tunai', 422);
            }
        }

        $cabangId = $this->resolveCabangId($request);
        if (! $cabangId) {
            return $this->error('Cabang aktif belum dipilih', 400);
        }

        if ($request->filled('gudang_id')) {
            $gudang = Gudang::where('id', $request->gudang_id)->where('cabang_id', $cabangId)->first();
            if (! $gudang) {
                return $this->error('Gudang bukan milik cabang aktif', 403);
            }
            $gudangId = $gudang->id;
        } else {
            $gudangId = session('gudang_id');
            if (! $gudangId || ! Gudang::where('id', $gudangId)->where('cabang_id', $cabangId)->exists()) {
                $firstGudang = Gudang::where('cabang_id', $cabangId)->first();
                $gudangId = $firstGudang?->id;
            }
        }

        try {
            return DB::transaction(function () use ($request, $cabangId, $gudangId) {
                $subtotal = 0;
                $itemsData = [];

                // Validasi & siapkan stok
                foreach ($request->items as $item) {
                    $produk = Produk::findOrFail($item['produk_id']);
                    $qty = (int) $item['jumlah'];
                    $satuan = $item['satuan'] ?? ($produk->satuan_unit ?: 'pcs');
                    $konversi = max(0.0001, (float) ($item['konversi'] ?? 1));
                    $qtyDasar = (int) round($qty * $konversi);
                    $hargaSatuan = (float) $item['harga_satuan'];
                    $diskonItem = (float) ($item['diskon_nominal'] ?? 0);
                    $lineSubtotal = max(0, ($hargaSatuan * $qty) - $diskonItem);
                    $subtotal += $lineSubtotal;

                    // Cek ketersediaan stok di gudang jika gudang ditentukan (memperhitungkan reservasi aktif)
                    if ($gudangId) {
                        $variantId = $item['sku_variant_id'] ?? null;
                        $stokFisik = (int) StokItem::where('produk_id', $produk->id)
                            ->where('gudang_id', $gudangId)
                            ->when($variantId, fn ($q) => $q->where('sku_variant_id', $variantId))
                            ->sum('jumlah');

                        $reserved = app(StockReservationService::class)->getActiveReservation(
                            $cabangId,
                            $produk->id,
                            $variantId
                        );
                        $stokBisaJual = max(0, $stokFisik - $reserved);

                        if ($stokBisaJual < $qtyDasar) {
                            throw new StokTidakCukupException(
                                "Stok tidak mencukupi untuk {$produk->nama}. Tersedia: {$stokBisaJual}, diminta: {$qtyDasar}",
                                (int) $produk->id,
                                (int) $stokBisaJual,
                                (int) $qtyDasar,
                            );
                        }
                    }

                    $itemsData[] = [
                        'produk_id' => $produk->id,
                        'sku_variant_id' => $item['sku_variant_id'] ?? null,
                        'satuan' => $satuan,
                        'konversi' => $konversi,
                        'jumlah' => $qty,
                        'harga_satuan' => $hargaSatuan,
                        'diskon_nominal' => $diskonItem,
                        'subtotal' => $lineSubtotal,
                        'hpp' => (float) $produk->harga_beli * $konversi,
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
                if ($request->metode_bayar === 'tunai') {
                    $jumlahBayar = (float) $request->jumlah_bayar;
                    $kembalian = max(0, $jumlahBayar - $totalAkhir);
                } elseif ($request->metode_bayar === 'split' && is_array($request->split_detail)) {
                    $tunai = (float) ($request->split_detail['tunai'] ?? 0);
                    $nonTunai = (float) ($request->split_detail['non_tunai'] ?? 0);
                    $jumlahBayar = $tunai + $nonTunai;
                    $kembalian = max(0, $jumlahBayar - $totalAkhir);
                } elseif ($request->metode_bayar === 'piutang') {
                    $jumlahBayar = 0;
                    $kembalian = 0;
                } else {
                    $jumlahBayar = (float) ($request->jumlah_bayar ?: $totalAkhir);
                    $kembalian = 0;
                }

                $transaksi = app(PosCheckoutService::class)->checkout([
                    'cabang_id' => $cabangId,
                    'kasir_id' => auth()->id(),
                    'pelanggan_id' => $request->pelanggan_id,
                    'gudang_id' => $gudangId,
                    'sumber' => 'pos',
                    'subtotal' => $subtotal,
                    'diskon_persen' => (float) ($request->diskon_persen ?? 0),
                    'diskon_nominal' => $diskonHeader,
                    'diskon_poin' => (float) ($request->diskon_poin ?? 0),
                    'poin_ditukar' => (int) ($request->poin_ditukar ?? 0),
                    'diskon_partai' => (float) ($request->diskon_partai ?? 0),
                    'diskon_manual_alasan' => $request->diskon_manual_alasan ?: null,
                    'diskon_manual_authorized_by_id' => $request->diskon_manual_authorized_by_id,
                    'durasi_garansi_hari' => (int) ($request->durasi_garansi_hari ?? 0),
                    'garansi_teks' => $request->garansi_teks,
                    'garansi_berakhir_pada' => $request->garansi_berakhir_pada,
                    'catatan_garansi' => $request->catatan_garansi,
                    'mode_reward_mitra' => $request->mode_reward_mitra,
                    'komisi_mitra_nominal' => (float) ($request->komisi_mitra_nominal ?? 0),
                    'dpp' => $dpp,
                    'pajak_nominal' => $ppnNominal,
                    'total_akhir' => $totalAkhir,
                    'metode_bayar' => $request->metode_bayar,
                    'jumlah_bayar' => $jumlahBayar,
                    'kembalian' => $kembalian,
                    'split_detail' => $request->split_detail,
                    'catatan' => $request->catatan,
                ], $itemsData);

                return $this->success(
                    $transaksi->load(['items.produk', 'items.skuVariant', 'pelanggan', 'cabang']),
                    'Transaksi berhasil diproses',
                    201
                );
            });
        } catch (StokTidakCukupException $e) {
            // [B-02 revisi] Stok kurang → 422 + pesan Indonesia. Transaksi sudah
            // rollback oleh DB::transaction, jadi tidak ada transaksi/jurnal/stok
            // yang tertinggal. Error lain tetap naik sebagai 500 (tidak ditutupi).
            return $this->error($e->getMessage(), 422);
        }
    }

    // [API: POS-05] Daftar transaksi (filter status, di-scope cabang; default cabang aktif)
    public function index(Request $request)
    {
        $status = $request->query('status');
        $cabangId = $this->resolveCabangId($request);

        $query = Transaksi::with(['items', 'pelanggan', 'kasir', 'cabang'])
            ->where('cabang_id', $cabangId)
            ->latest();

        if ($status) {
            $query->where('status', $status);
        }

        return $this->success($query->paginate($request->query('per_page', 20)), 'Daftar transaksi berhasil dimuat');
    }

    // [API: POS-04] Tahan transaksi (park) — simpan cart ke split_detail, status ditahan (belum kurangi stok)
    public function tahan(Request $request, $id)
    {
        $cabangId = $this->resolveCabangId($request);

        $transaksi = Transaksi::where('id', $id)
            ->where('cabang_id', $cabangId)
            ->where('status', 'draft')
            ->firstOrFail();

        $transaksi->update(['status' => 'ditahan']); // split_detail sudah berisi cart

        return $this->success($transaksi, 'Transaksi ditahan — bisa dilanjutkan kasir lain');
    }

    // [API: POS-04b] Lanjutkan transaksi ditahan (resume) — status kembali draft utk dilanjutkan
    public function resume(Request $request, $id)
    {
        $cabangId = $this->resolveCabangId($request);

        $transaksi = Transaksi::where('id', $id)
            ->where('cabang_id', $cabangId)
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
            'akun_sumber_kode' => 'nullable|string|max:20',
        ]);

        $cabangId = $this->resolveCabangId($request);

        try {
            $sesi = app(KasSesiState::class)->bukaKas(
                (float) $request->saldo_awal,
                $cabangId,
                auth()->id(),
                'manual',
                $request->input('akun_sumber_kode', '110-01')
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
        $cabangId = $this->resolveCabangId($request);

        return $this->success(
            app(KasSesiState::class)->riwayat($cabangId),
            'Riwayat sesi kas berhasil dimuat'
        );
    }

    // [API: POS-10][T-35] Cetak struk thermal — hanya antri, tidak blocking request
    public function printStruk(int $id)
    {
        $cabangId = $this->resolveCabangId(request());

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
