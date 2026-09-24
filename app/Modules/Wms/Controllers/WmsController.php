<?php

namespace App\Modules\Wms\Controllers;

use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Rbac\Services\AuditService;
use App\Modules\Wms\Exports\ImportProdukTemplateExport;
use App\Modules\Wms\Jobs\ImportProdukExcelJob;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\StokOpname;
use App\Modules\Wms\Models\StokOpnameItem;
use App\Modules\Wms\Models\StokTransfer;
use App\Modules\Wms\Models\StokTransferItem;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Models\TipeHp;
use App\Modules\Wms\Services\ImportProdukService;
use App\Modules\Wms\Services\PurchaseOrderService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class WmsController extends Controller
{
    use ApiResponse;

    // [API: WMS-01] List stok per gudang
    public function stok(Request $request)
    {
        $gudangId = $request->query('gudang_id');
        $search = $request->query('search');
        $kategori = $request->query('kategori');

        $query = StokItem::with(['produk', 'skuVariant', 'gudang.cabang']);

        if ($gudangId) {
            $query->where('gudang_id', $gudangId);
        } else {
            // Filter by active cabang if set
            $cabangId = session('cabang_id');
            if ($cabangId) {
                $query->whereHas('gudang', function ($q) use ($cabangId) {
                    $q->where('cabang_id', $cabangId);
                });
            }
        }

        if ($search) {
            $query->whereHas('produk', function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('brand_kompatibel', 'like', "%{$search}%")
                    ->orWhere('model_kompatibel', 'like', "%{$search}%");
            })->orWhereHas('skuVariant', function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%");
            });
        }

        if ($kategori) {
            $query->whereHas('produk', function ($q) use ($kategori) {
                $q->where('kategori', $kategori);
            });
        }

        $stokItems = $query->paginate(20);

        // [T-13] Warning: tampilkan qty terkunci transfer pending per item stok
        foreach ($stokItems as $stok) {
            $stok->stok_dikunci = StokTransfer::pendingLockedFor($stok);
        }

        return $this->success($stokItems, 'Data stok berhasil diambil');
    }

    // [API: WMS-02] Buat draft transfer antar gudang
    public function storeTransfer(Request $request)
    {
        $request->validate([
            'gudang_asal_id' => 'required|exists:gudang,id',
            'gudang_tujuan_id' => 'required|exists:gudang,id|different:gudang_asal_id',
            'catatan' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.produk_id' => 'required|exists:produk,id',
            'items.*.sku_variant_id' => 'nullable|exists:sku_variants,id',
            'items.*.rak_id' => 'nullable|exists:rak,id', // [T-12] rak tujuan di gudang tujuan
            'items.*.jumlah' => 'required|integer|min:1',
        ]);

        // [T-41] Validasi server: qty_transfer ≤ stok tersedia gudang sumber
        // (stok_fisik − stok_dikunci draft transfer pending) — per item, blokir submit.
        $lockedByKey = StokTransfer::pendingLockedByGudang((int) $request->gudang_asal_id);
        foreach ($request->items as $item) {
            $stok = StokItem::where('gudang_id', $request->gudang_asal_id)
                ->where('produk_id', $item['produk_id'])
                ->where('sku_variant_id', $item['sku_variant_id'] ?? null)
                ->first();
            $key = $item['produk_id'].':'.($item['sku_variant_id'] ?? 'null');
            $tersedia = ($stok?->jumlah ?? 0) - (int) ($lockedByKey[$key] ?? 0);
            if ($tersedia < (int) $item['jumlah']) {
                return $this->error(
                    'Qty melebihi stok tersedia di gudang sumber (tersedia: '.max(0, $tersedia).' unit) untuk produk ID '.$item['produk_id'],
                    422
                );
            }
        }

        return DB::transaction(function () use ($request) {
            $today = now()->format('Ymd');
            $count = StokTransfer::whereDate('created_at', now()->toDateString())->count() + 1;
            $noTransfer = sprintf('TRF-%s-%04d', $today, $count);

            $transfer = StokTransfer::create([
                'no_transfer' => $noTransfer,
                'gudang_asal_id' => $request->gudang_asal_id,
                'gudang_tujuan_id' => $request->gudang_tujuan_id,
                'user_pengirim_id' => auth()->id(), // created_by
                'status' => 'draft',
                'catatan' => $request->catatan,
            ]);

            foreach ($request->items as $item) {
                StokTransferItem::create([
                    'stok_transfer_id' => $transfer->id,
                    'produk_id' => $item['produk_id'],
                    'sku_variant_id' => $item['sku_variant_id'] ?? null,
                    'rak_id' => $item['rak_id'] ?? null,
                    'jumlah' => $item['jumlah'],
                    'created_by' => auth()->id(), // [T-41] audit trail
                ]);
            }

            return $this->success($transfer->load('items.produk'), 'Draft transfer stok berhasil dibuat', 201);
        });
    }

    // [API: WMS-03] Kirim transfer (kurangi stok asal)
    public function kirimTransfer(Request $request, $id)
    {
        $transfer = StokTransfer::with('items')->findOrFail($id);

        if ($transfer->status !== 'draft') {
            return $this->error('Hanya transfer dengan status draft yang dapat dikirim', 400);
        }

        return DB::transaction(function () use ($transfer) {
            // Kurangi stok dari gudang asal & catat log — lockForUpdate anti race (T-13)
            foreach ($transfer->items as $item) {
                $stokAsal = StokItem::where('gudang_id', $transfer->gudang_asal_id)
                    ->where('produk_id', $item->produk_id)
                    ->where('sku_variant_id', $item->sku_variant_id)
                    ->lockForUpdate()
                    ->first();

                $sebelum = $stokAsal ? $stokAsal->jumlah : 0;
                // [T-13] stok yang terkunci transfer pending lain tidak boleh dipakai
                $locked = $stokAsal ? StokTransfer::pendingLockedFor($stokAsal, $transfer->id) : 0;
                if ($sebelum - $locked < $item->jumlah) {
                    throw new \Exception("Stok gudang asal tidak mencukupi untuk item ID {$item->produk_id}".($locked > 0 ? " ({$locked} unit terkunci transfer pending)" : ''));
                }

                $setelah = $sebelum - $item->jumlah;
                $stokAsal->update(['jumlah' => $setelah]);

                // [T-26] SOT — [T-41] sumber 'transfer:out' (mutasi keluar dari gudang asal)
                StockMutationLog::create([
                    'produk_id' => $item->produk_id, 'sku_variant_id' => $item->sku_variant_id,
                    'gudang_id' => $transfer->gudang_asal_id, 'delta' => -$item->jumlah,
                    'sumber' => 'transfer:out', 'referensi_tipe' => StokTransfer::class,
                    'referensi_id' => $transfer->id, 'terjadi_at' => now(),
                ]);

                StokLog::create([
                    'gudang_id' => $transfer->gudang_asal_id,
                    'produk_id' => $item->produk_id,
                    'sku_variant_id' => $item->sku_variant_id,
                    'user_id' => auth()->id(),
                    'jenis' => 'transfer_keluar',
                    'referensi_tipe' => StokTransfer::class,
                    'referensi_id' => $transfer->id,
                    'jumlah_sebelum' => $sebelum,
                    'perubahan' => -$item->jumlah,
                    'jumlah_setelah' => $setelah,
                    'catatan' => "Kirim transfer {$transfer->no_transfer}",
                ]);
            }

            $transfer->update([
                'status' => 'dikirim',
                'tanggal_kirim' => now(),
                'approved_by' => auth()->id(), // [T-41] audit trail
                'approved_at' => now(),
            ]);

            return $this->success($transfer, 'Transfer stok berhasil dikirim');
        });
    }

    // [API: WMS-04] Terima transfer (tambah stok tujuan)
    public function terimaTransfer(Request $request, $id)
    {
        $transfer = StokTransfer::with('items')->findOrFail($id);

        if ($transfer->status !== 'dikirim') {
            return $this->error('Hanya transfer berstatus dikirim yang dapat dikonfirmasi terima', 400);
        }

        return DB::transaction(function () use ($transfer) {
            // Tambah stok ke gudang tujuan & catat log
            foreach ($transfer->items as $item) {
                $stokTujuan = StokItem::lockForUpdate()->firstOrCreate(
                    [
                        'gudang_id' => $transfer->gudang_tujuan_id,
                        'produk_id' => $item->produk_id,
                        'sku_variant_id' => $item->sku_variant_id,
                    ],
                    ['jumlah' => 0, 'jumlah_minimum' => 0]
                );

                $sebelum = $stokTujuan->jumlah;
                $setelah = $sebelum + $item->jumlah;
                $update = ['jumlah' => $setelah];
                if ($item->rak_id) {
                    $update['rak_id'] = $item->rak_id; // [T-12] stok masuk rak tujuan terpilih
                }
                $stokTujuan->update($update);

                // [T-26] SOT — [T-41] sumber 'transfer:in' (mutasi masuk ke gudang tujuan)
                StockMutationLog::create([
                    'produk_id' => $item->produk_id, 'sku_variant_id' => $item->sku_variant_id,
                    'gudang_id' => $transfer->gudang_tujuan_id, 'delta' => $item->jumlah,
                    'sumber' => 'transfer:in', 'referensi_tipe' => StokTransfer::class,
                    'referensi_id' => $transfer->id, 'terjadi_at' => now(),
                ]);

                StokLog::create([
                    'gudang_id' => $transfer->gudang_tujuan_id,
                    'produk_id' => $item->produk_id,
                    'sku_variant_id' => $item->sku_variant_id,
                    'user_id' => auth()->id(),
                    'jenis' => 'transfer_masuk',
                    'referensi_tipe' => StokTransfer::class,
                    'referensi_id' => $transfer->id,
                    'jumlah_sebelum' => $sebelum,
                    'perubahan' => $item->jumlah,
                    'jumlah_setelah' => $setelah,
                    'catatan' => "Terima transfer {$transfer->no_transfer}",
                ]);
            }

            $transfer->update([
                'status' => 'diterima',
                'tanggal_terima' => now(),
                'user_penerima_id' => auth()->id(),
            ]);

            return $this->success($transfer, 'Transfer stok berhasil diterima');
        });
    }

    // [API: WMS-05] Buat draft stock opname
    public function storeOpname(Request $request)
    {
        $request->validate([
            'gudang_id' => 'required|exists:gudang,id',
            'rak_id' => 'nullable|exists:rak,id', // [T-14] scope sesi per rak/lokasi
            'catatan' => 'nullable|string',
        ]);

        $today = now()->format('Ymd');
        $count = StokOpname::whereDate('created_at', now()->toDateString())->count() + 1;
        $noOpname = sprintf('OPN-%s-%04d', $today, $count);

        $opname = StokOpname::create([
            'no_opname' => $noOpname,
            'gudang_id' => $request->gudang_id,
            'rak_id' => $request->rak_id,
            'user_id' => auth()->id(),
            'status' => 'draft',
            'catatan' => $request->catatan,
        ]);

        return $this->success($opname, 'Stock opname berhasil dimulai', 201);
    }

    // [API: WMS-06] Input hasil opname fisik
    public function inputOpnameItems(Request $request, $id)
    {
        $opname = StokOpname::findOrFail($id);

        if ($opname->status !== 'draft') {
            return $this->error('Hanya opname status draft yang dapat diisi item fisik', 400);
        }

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.produk_id' => 'required|exists:produk,id',
            'items.*.sku_variant_id' => 'nullable|exists:sku_variants,id',
            'items.*.rak_id' => 'nullable|exists:rak,id', // [T-14] item per rak
            'items.*.stok_fisik' => 'required|integer|min:0',
            'items.*.catatan' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request, $opname) {
            foreach ($request->items as $item) {
                // [T-14] stok_sistem dibaca dari StokItem per rak bila opname scope rak
                $stokQuery = StokItem::where('gudang_id', $opname->gudang_id)
                    ->where('produk_id', $item['produk_id'])
                    ->where('sku_variant_id', $item['sku_variant_id'] ?? null);
                if ($opname->rak_id) {
                    $stokQuery->where('rak_id', $opname->rak_id);
                } elseif (! empty($item['rak_id'])) {
                    $stokQuery->where('rak_id', $item['rak_id']);
                }
                $stokSistem = $stokQuery->value('jumlah') ?? 0;

                $stokFisik = (int) $item['stok_fisik'];
                $selisih = $stokFisik - $stokSistem;

                StokOpnameItem::updateOrCreate(
                    [
                        'stok_opname_id' => $opname->id,
                        'produk_id' => $item['produk_id'],
                        'sku_variant_id' => $item['sku_variant_id'] ?? null,
                    ],
                    [
                        'rak_id' => $item['rak_id'] ?? null,
                        'stok_sistem' => $stokSistem,
                        'stok_fisik' => $stokFisik,
                        'selisih' => $selisih,
                        'catatan' => $item['catatan'] ?? null,
                    ]
                );
            }

            $opname->update(['status' => 'menunggu_approval']);

            return $this->success(
                $opname->load('items.produk'),
                'Data opname fisik tersimpan dan menunggu approval supervisor'
            );
        });
    }

    // [API: WMS-07] Approval supervisor opname (sesuaikan stok otomatis)
    public function approveOpname(Request $request, $id)
    {
        $opname = StokOpname::with('items')->findOrFail($id);

        if ($opname->status !== 'menunggu_approval') {
            return $this->error('Hanya opname berstatus menunggu_approval yang dapat disetujui', 400);
        }

        $action = $request->input('action', 'approve'); // approve / reject

        if ($action === 'reject') {
            $opname->update([
                'status' => 'ditolak',
                'approver_id' => auth()->id(),
                'tanggal_approval' => now(),
                'catatan_approval' => $request->catatan_approval,
            ]);

            return $this->success($opname, 'Stock opname ditolak');
        }

        return DB::transaction(function () use ($opname, $request) {
            $jurnalLines = [];
            $totalSelisihAbs = 0;

            foreach ($opname->items as $item) {
                $stok = StokItem::firstOrCreate(
                    [
                        'gudang_id' => $opname->gudang_id,
                        'produk_id' => $item->produk_id,
                        'sku_variant_id' => $item->sku_variant_id,
                    ],
                    ['jumlah' => 0, 'jumlah_minimum' => 0]
                );

                $sebelum = $stok->jumlah;
                $stok->update(['jumlah' => $item->stok_fisik]);

                if ($item->selisih !== 0) {
                    // [T-26] SOT
                    StockMutationLog::create([
                        'produk_id' => $item->produk_id, 'sku_variant_id' => $item->sku_variant_id,
                        'gudang_id' => $opname->gudang_id, 'delta' => $item->selisih,
                        'sumber' => 'opname', 'referensi_tipe' => StokOpname::class,
                        'referensi_id' => $opname->id, 'terjadi_at' => now(),
                    ]);

                    StokLog::create([
                        'gudang_id' => $opname->gudang_id,
                        'produk_id' => $item->produk_id,
                        'sku_variant_id' => $item->sku_variant_id,
                        'user_id' => auth()->id(),
                        'jenis' => 'opname',
                        'referensi_tipe' => StokOpname::class,
                        'referensi_id' => $opname->id,
                        'jumlah_sebelum' => $sebelum,
                        'perubahan' => $item->selisih,
                        'jumlah_setelah' => $item->stok_fisik,
                        'catatan' => "Penyesuaian Opname {$opname->no_opname}: ".($item->catatan ?? ''),
                    ]);

                    // [T-14] Akumulasi jurnal penyesuaian stok: Persediaan (130-01) vs Selisih Stok (520-08)
                    // selisih > 0 (fisik > sistem): Persediaan debit, 520-08 kredit (penemuan stok)
                    // selisih < 0 (fisik < sistem): Persediaan kredit, 520-08 debit (kehilangan stok)
                    $jurnalLines[] = $item->selisih > 0
                        ? ['akun_kode' => '130-01', 'debit' => abs($item->selisih), 'kredit' => 0]
                        : ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => abs($item->selisih)];
                    $jurnalLines[] = $item->selisih > 0
                        ? ['akun_kode' => '520-08', 'debit' => 0, 'kredit' => abs($item->selisih)]
                        : ['akun_kode' => '520-08', 'debit' => abs($item->selisih), 'kredit' => 0];

                    $totalSelisihAbs += abs($item->selisih);
                }
            }

            // Post jurnal balance jika ada selisih (T-14)
            if (! empty($jurnalLines)) {
                $cabangId = $opname->gudang?->cabang_id;
                app(JurnalService::class)->post(
                    app(JurnalService::class)->generateNoJurnal('opname', $cabangId),
                    now(),
                    'opname',
                    $jurnalLines,
                    "Penyesuaian Stock Opname {$opname->no_opname}",
                    $cabangId,
                    auth()->id(),
                    StokOpname::class,
                    $opname->id
                );
            }

            $opname->update([
                'status' => 'disetujui',
                'approver_id' => auth()->id(),
                'tanggal_approval' => now(),
                'catatan_approval' => $request->catatan_approval,
            ]);

            // Audit stok manual (PRD §6)
            app(AuditService::class)->catat(
                'StokItem', 'adjust', $opname->gudang_id,
                "Stock opname {$opname->no_opname} disetujui — ".$opname->items->count().' item disesuaikan',
                null, ['status' => $opname->status]
            );

            return $this->success($opname, 'Stock opname disetujui dan stok telah disesuaikan'.($totalSelisihAbs ? ' + jurnal penyesuaian dibuat' : ''));
        });
    }

    // [API: WMS-08] Riwayat pergerakan stok (kartu stok)
    public function kartuStok(Request $request, $produk_id)
    {
        $gudangId = $request->query('gudang_id');

        $query = StokLog::with(['gudang', 'user'])
            ->where('produk_id', $produk_id)
            ->latest();

        if ($gudangId) {
            $query->where('gudang_id', $gudangId);
        }

        $logs = $query->paginate(30);

        return $this->success($logs, 'Riwayat kartu stok berhasil diambil');
    }

    // ==================== [T-10] SUPPLIER & PO ====================

    // [API: WMS-09] CRUD supplier
    public function supplier(Request $request)
    {
        return $this->success(Supplier::orderBy('nama')->get(), 'Daftar supplier berhasil dimuat');
    }

    public function storeSupplier(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'telepon' => 'nullable|string|max:20',
            'termin_hari' => 'nullable|integer|min:0',
        ]);

        $supplier = Supplier::create($request->all());

        return $this->success($supplier, 'Supplier berhasil dibuat', 201);
    }

    public function updateSupplier(Request $request, $id)
    {
        $supplier = Supplier::findOrFail($id);
        $request->validate([
            'nama' => 'required|string|max:255',
            'kontak' => 'nullable|string|max:255',
            'telepon' => 'nullable|string|max:20',
            'alamat' => 'nullable|string',
            'termin_hari' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $supplier->update($request->all());

        return $this->success($supplier, 'Supplier berhasil diperbarui');
    }

    public function destroySupplier($id)
    {
        $supplier = Supplier::findOrFail($id);

        // Cek relasi PO
        if ($supplier->purchaseOrders()->exists()) {
            return $this->error('Supplier memiliki PO terkait, tidak bisa dihapus. Nonaktifkan saja.', 422);
        }

        $supplier->delete();

        return $this->success(null, 'Supplier berhasil dihapus');
    }

    // [T-12] Rak CRUD (scope cabang via gudang)
    public function indexRak(Request $request)
    {
        $cabangId = session('cabang_id');
        $query = Rak::with('gudang.cabang');
        if ($cabangId) {
            $query->whereHas('gudang', fn ($q) => $q->where('cabang_id', $cabangId));
        }

        return $this->success($query->orderBy('kode')->get(), 'Daftar rak berhasil dimuat');
    }

    public function storeRak(Request $request)
    {
        $request->validate([
            'gudang_id' => 'required|exists:gudang,id',
            'nama' => 'required|string|max:255',
            'kode' => 'required|string|max:20|unique:rak,kode',
            'zona' => 'nullable|string|max:50',
        ]);

        $rak = Rak::create($request->all());

        return $this->success($rak, 'Rak berhasil dibuat', 201);
    }

    public function updateRak(Request $request, $id)
    {
        $rak = Rak::findOrFail($id);
        $request->validate([
            'nama' => 'required|string|max:255',
            'kode' => 'required|string|max:20|unique:rak,kode,'.$id,
            'zona' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        $rak->update($request->all());

        return $this->success($rak, 'Rak berhasil diperbarui');
    }

    public function destroyRak($id)
    {
        $rak = Rak::findOrFail($id);

        // Cek relasi stok/transfer/opname
        if (StokItem::where('rak_id', $id)->exists() ||
            StokTransferItem::where('rak_id', $id)->exists() ||
            StokOpname::where('rak_id', $id)->exists() ||
            StokOpnameItem::where('rak_id', $id)->exists()) {
            return $this->error('Rak memiliki data stok/transfer/opname terkait, tidak bisa dihapus. Nonaktifkan saja.', 422);
        }

        $rak->delete();

        return $this->success(null, 'Rak berhasil dihapus');
    }

    // [API: WMS-10] CRUD PO + ubah status
    public function indexPo(Request $request)
    {
        return $this->success(
            PurchaseOrder::with(['supplier', 'gudangTujuan', 'items.produk'])
                ->latest()->paginate(20),
            'Daftar PO berhasil dimuat'
        );
    }

    public function storePo(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:supplier,id',
            'gudang_tujuan_id' => 'required|exists:gudang,id',
            'metode_bayar' => 'required|in:tunai,kredit',
            'jatuh_tempo' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.produk_id' => 'required|exists:produk,id',
            'items.*.sku_variant_id' => 'nullable|exists:sku_variants,id',
            'items.*.harga_beli' => 'required|numeric|min:0',
            'items.*.jumlah' => 'required|integer|min:1',
        ]);

        $today = now()->format('Ymd');
        $count = PurchaseOrder::whereDate('created_at', now()->toDateString())->count() + 1;
        $noPo = sprintf('PO-%s-%03d', $today, $count);

        return DB::transaction(function () use ($request, $noPo) {
            $total = 0;
            foreach ($request->items as $i) {
                $total += (float) $i['harga_beli'] * (int) $i['jumlah'];
            }

            $po = PurchaseOrder::create([
                'no_po' => $noPo,
                'supplier_id' => $request->supplier_id,
                'gudang_tujuan_id' => $request->gudang_tujuan_id,
                'status' => 'draft',
                'metode_bayar' => $request->metode_bayar,
                'jatuh_tempo' => $request->jatuh_tempo ?? now()->addDays((int) (Supplier::find($request->supplier_id)?->termin_hari ?? 30))->toDateString(),
                'total' => $total,
                'total_dibayar' => 0,
                'catatan' => $request->catatan,
            ]);

            foreach ($request->items as $i) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'produk_id' => $i['produk_id'],
                    'sku_variant_id' => $i['sku_variant_id'] ?? null,
                    'harga_beli' => (float) $i['harga_beli'],
                    'jumlah' => (int) $i['jumlah'],
                    'subtotal' => (float) $i['harga_beli'] * (int) $i['jumlah'],
                ]);
            }

            return $this->success($po->load('items', 'supplier'), 'PO berhasil dibuat', 201);
        });
    }

    public function updatePoStatus(Request $request, $id)
    {
        $po = PurchaseOrder::with('gudangTujuan')->findOrFail($id);
        $action = $request->input('action', 'diterima'); // dikirim | diterima | dibatalkan

        // [P1-x] Scope cabang aktif — PO cabang lain ditolak (pola GrnTab::tolakJikaBukanCabang).
        $cabangId = session('cabang_id');
        if ($cabangId !== null && $cabangId !== '' && (int) $po->gudangTujuan?->cabang_id !== (int) $cabangId) {
            return $this->error('PO bukan milik cabang aktif', 403);
        }

        if ($action === 'dibatalkan' && in_array($po->status, ['draft', 'dikirim'], true)) {
            $po->update(['status' => 'dibatalkan']);

            return $this->success($po, 'PO dibatalkan');
        }

        if ($action === 'dikirim' && $po->status === 'draft') {
            $po->update(['status' => 'dikirim']);

            return $this->success($po, 'PO dikirim');
        }

        // [F2-2] Penerimaan barang HANYA lewat GRN (GrnService finalisasi → PO 'diterima').
        // Endpoint ini tidak boleh jadi bypass stok+jurnal tanpa GRN/approval.
        if ($action === 'diterima' && $po->status === 'dikirim') {
            return $this->error('PO hanya bisa diterima melalui GRN — buka tab GRN untuk menerima PO ini.', 422);
        }

        return $this->error('Transisi status tidak valid', 422);
    }

    // [API: WMS-14] Generate barcode utk produk tanpa barcode (format UTP-{id}-{checksum})
    public function generateBarcode(Request $request, $id)
    {
        $produk = Produk::findOrFail($id);

        if (empty($produk->barcode)) {
            $checksum = substr(hash('crc32b', (string) $produk->id), 0, 4);
            $produk->update(['barcode' => sprintf('UTP-%05d-%s', $produk->id, strtoupper($checksum))]);
        }

        // Varian juga (jika belum)
        $variant = $produk->skuVariants()->first();
        if ($variant && empty($variant->barcode)) {
            $variant->update(['barcode' => $produk->barcode.'-'.$variant->id]);
        }

        return $this->success($produk->fresh(), 'Barcode berhasil digenerate');
    }

    // [API: WMS-12] Bayar PO
    public function bayarPo(Request $request, $id)
    {
        $request->validate(['jumlah' => 'required|numeric|min:1']);

        try {
            $po = app(PurchaseOrderService::class)->bayarPO(
                PurchaseOrder::findOrFail($id),
                (float) $request->jumlah,
                auth()->id()
            );

            return $this->success($po, 'Pembayaran PO tercatat — sisa utang terupdate');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // ============================================================
    // [T-44] Master data pendukung produk (brand, kualitas, tipe HP)
    // ============================================================

    /** [API: WMS-BRAND] Daftar brand untuk form Master Produk. */
    public function indexBrand()
    {
        return $this->success(Brand::orderBy('nama')->get(['id', 'nama']), 'Daftar brand');
    }

    /** [API: WMS-KUALITAS] Daftar kualitas produk. */
    public function indexKualitas()
    {
        return $this->success(KualitasProduk::orderBy('nama')->get(['id', 'nama']), 'Daftar kualitas produk');
    }

    /** [API: WMS-TIPEHP] Daftar tipe HP. */
    public function indexTipeHp()
    {
        return $this->success(TipeHp::orderBy('merk')->orderBy('model')->get(['id', 'merk', 'model', 'nama']), 'Daftar tipe HP');
    }

    // ============================================================
    // [T-43] Import master produk Excel (template → preview → commit via queue)
    // ============================================================

    /** [API: WMS-IMP-01] Download template Excel import produk. */
    public function downloadTemplateProduk()
    {
        return Excel::download(
            new ImportProdukTemplateExport,
            'template-import-produk.xlsx'
        );
    }

    /** [API: WMS-IMP-02] Preview (dry-run) — validasi seluruh baris, tanpa mengubah data. */
    public function previewImportProduk(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {
            $path = $request->file('file')->store('import-tmp');
            $hasil = app(ImportProdukService::class)->preview(storage_path('app/'.$path));
            @unlink(storage_path('app/'.$path));

            return $this->success($hasil, 'Preview import produk selesai — belum ada data diubah');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /** [API: WMS-IMP-03] Commit — antrikan import via ImportProdukExcelJob (database queue). */
    public function commitImportProduk(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
            'confirmed' => 'required|boolean|accepted', // wajib konfirmasi hasil preview
        ]);

        try {
            $path = $request->file('file')->store('import-tmp');

            $log = ImportLog::create([
                'tipe' => 'produk_excel',
                'nama_file' => $request->file('file')->getClientOriginalName(),
                'status' => 'proses',
                'user_id' => auth()->id(),
            ]);

            ImportProdukExcelJob::dispatch($log->id, $path, auth()->id());

            return $this->success(
                ['import_log_id' => $log->id],
                'Import diproses via antrian — hasil akan masuk notifikasi'
            );
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /** [API: WMS-IMP-04] Status import log (hasil sukses/gagal per baris). */
    public function importLog(Request $request, $id)
    {
        $log = ImportLog::findOrFail($id);

        return $this->success($log, 'Status import');
    }
}
