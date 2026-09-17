<?php

namespace App\Modules\Wms\Controllers;

use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\StokOpname;
use App\Modules\Wms\Models\StokOpnameItem;
use App\Modules\Wms\Models\StokTransfer;
use App\Modules\Wms\Models\StokTransferItem;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

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
            'items.*.jumlah' => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($request) {
            $today = now()->format('Ymd');
            $count = StokTransfer::whereDate('created_at', now()->toDateString())->count() + 1;
            $noTransfer = sprintf('TRF-%s-%04d', $today, $count);

            $transfer = StokTransfer::create([
                'no_transfer' => $noTransfer,
                'gudang_asal_id' => $request->gudang_asal_id,
                'gudang_tujuan_id' => $request->gudang_tujuan_id,
                'user_pengirim_id' => auth()->id(),
                'status' => 'draft',
                'catatan' => $request->catatan,
            ]);

            foreach ($request->items as $item) {
                StokTransferItem::create([
                    'stok_transfer_id' => $transfer->id,
                    'produk_id' => $item['produk_id'],
                    'sku_variant_id' => $item['sku_variant_id'] ?? null,
                    'jumlah' => $item['jumlah'],
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
            // Kurangi stok dari gudang asal & catat log
            foreach ($transfer->items as $item) {
                $stokAsal = StokItem::where('gudang_id', $transfer->gudang_asal_id)
                    ->where('produk_id', $item->produk_id)
                    ->where('sku_variant_id', $item->sku_variant_id)
                    ->first();

                $sebelum = $stokAsal ? $stokAsal->jumlah : 0;
                if ($sebelum < $item->jumlah) {
                    throw new \Exception("Stok gudang asal tidak mencukupi untuk item ID {$item->produk_id}");
                }

                $setelah = $sebelum - $item->jumlah;
                $stokAsal->update(['jumlah' => $setelah]);

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
                $stokTujuan = StokItem::firstOrCreate(
                    [
                        'gudang_id' => $transfer->gudang_tujuan_id,
                        'produk_id' => $item->produk_id,
                        'sku_variant_id' => $item->sku_variant_id,
                    ],
                    ['jumlah' => 0, 'jumlah_minimum' => 0]
                );

                $sebelum = $stokTujuan->jumlah;
                $setelah = $sebelum + $item->jumlah;
                $stokTujuan->update(['jumlah' => $setelah]);

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
            'catatan' => 'nullable|string',
        ]);

        $today = now()->format('Ymd');
        $count = StokOpname::whereDate('created_at', now()->toDateString())->count() + 1;
        $noOpname = sprintf('OPN-%s-%04d', $today, $count);

        $opname = StokOpname::create([
            'no_opname' => $noOpname,
            'gudang_id' => $request->gudang_id,
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
            'items.*.stok_fisik' => 'required|integer|min:0',
            'items.*.catatan' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request, $opname) {
            foreach ($request->items as $item) {
                $stokSistem = StokItem::where('gudang_id', $opname->gudang_id)
                    ->where('produk_id', $item['produk_id'])
                    ->where('sku_variant_id', $item['sku_variant_id'] ?? null)
                    ->value('jumlah') ?? 0;

                $stokFisik = (int) $item['stok_fisik'];
                $selisih = $stokFisik - $stokSistem;

                StokOpnameItem::updateOrCreate(
                    [
                        'stok_opname_id' => $opname->id,
                        'produk_id' => $item['produk_id'],
                        'sku_variant_id' => $item['sku_variant_id'] ?? null,
                    ],
                    [
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
                        'catatan' => "Penyesuaian Opname {$opname->no_opname}: " . ($item->catatan ?? ''),
                    ]);
                }
            }

            $opname->update([
                'status' => 'disetujui',
                'approver_id' => auth()->id(),
                'tanggal_approval' => now(),
                'catatan_approval' => $request->catatan_approval,
            ]);

            // Audit stok manual (PRD §6)
            app(\App\Modules\Rbac\Services\AuditService::class)->catat(
                'StokItem', 'adjust', $opname->gudang_id,
                "Stock opname {$opname->no_opname} disetujui — " . $opname->items->count() . " item disesuaikan",
                null, ['status' => $opname->status]
            );

            return $this->success($opname, 'Stock opname disetujui dan stok telah disesuaikan');
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
}
