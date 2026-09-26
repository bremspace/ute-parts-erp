<?php

namespace App\Modules\Servis\Controllers;

use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Services\ServisService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;

class ServisController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ServisService $servisService
    ) {}

    // [API: SERVICE-01] Buat tiket servis (terima unit)
    public function store(Request $request)
    {
        $request->validate([
            'jenis_servis_id' => 'nullable|exists:jenis_servis,id',
            'pelanggan_id' => 'nullable|exists:pelanggan,id',
            'nama_pelanggan' => 'required_without:pelanggan_id|string|max:255',
            'telepon_pelanggan' => 'required_without:pelanggan_id|nullable|string|max:20',
            'jenis_hp' => 'required|string|max:255',
            'seri_hp' => 'nullable|string|max:255',
            // [T-19] kunci gadget
            'tipe_kunci' => 'nullable|in:pola,pin,password,tidak_ada',
            'kunci_terenkripsi' => 'required_with:tipe_kunci|nullable|string',
            'keluhan' => 'required|string',
            'kondisi_fisik' => 'nullable|array',
            'foto_unit' => 'nullable|array', // [B-06] foto terima unit OPSIONAL (min 2 dihapus)
            'foto_unit.*' => 'string',
            'cabang_id' => 'nullable|exists:cabang,id',
        ]);

        try {
            $tiket = $this->servisService->terimaUnit($request->all(), $request->user());

            return $this->success($tiket->load('jenisServis', 'pelanggan'), 'Unit servis diterima', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    // [API: SERVICE-02] Daftar tiket servis (all statuses, Kanban data)
    public function index(Request $request)
    {
        $status = $request->query('status');
        $search = $request->query('search');
        $cabangId = session('cabang_id');

        // [B-07] Urut & paginasi dulu di kolom SEMPIT (`id`), baru hydrate row penuh.
        // Alasan: `foto_unit` (base64, bisa >400KB/baris) membuat filesort
        // `ORDER BY created_at` melebihi sort_buffer_size → SQLSTATE[HY001] 1038
        // "Out of sort memory". Kolom sempit sort-nya muat di buffer; query hydrate
        // (`whereIn id`) tidak punya ORDER BY sehingga tidak memicu filesort sama sekali.
        $base = TiketServis::query();

        if ($cabangId) {
            $base->where('cabang_id', $cabangId);
        }

        if ($status) {
            $base->where('status', $status);
        }

        if ($search) {
            $base->where(function ($q) use ($search) {
                $q->where('no_tiket', 'like', "%{$search}%")
                    ->orWhere('jenis_hp', 'like', "%{$search}%")
                    ->orWhere('nama_pelanggan', 'like', "%{$search}%")
                    ->orWhere('telepon_pelanggan', 'like', "%{$search}%");
            });
        }

        $tikets = $base->select('id')->latest()->paginate(20);

        // Ganti koleksi ID dgn model penuh (eager load + spareparts_count),
        // urutan terbaru-dulu dipertahankan spt query lama (`latest()`).
        $ids = $tikets->getCollection()->pluck('id');

        $tikets->setCollection(
            TiketServis::with(['jenisServis', 'pelanggan', 'teknisi', 'garansi'])
                ->withCount('spareparts')
                ->whereIn('id', $ids)
                ->get()
                ->sortByDesc('created_at')
                ->values()
        );

        return $this->success($tikets, 'Daftar tiket servis berhasil diambil');
    }

    // [API: SERVICE-03] Detail tiket servis
    public function show(Request $request, $id)
    {
        $tiket = TiketServis::with([
            'jenisServis', 'pelanggan.tierMembership', 'teknisi', 'garansi',
            'statusLogs.user', 'spareparts.produk', 'spareparts.skuVariant', 'cabang',
            'items', // [T-17]
        ])->findOrFail($id);

        // [B-06] Kunci gadget tampil utk SEMUA role yg berhak membuka tiket servis.
        // Pintu akses = middleware route `permission:servis.view` (teknisi, admin-toko,
        // super-admin) — bukan lagi pembatasan role teknisi-yang-ditugaskan saja.
        // Nilai tetap terenkripsi at-rest (cast `encrypted`) & tampil ter-mask di UI
        // sampai user menekan tombol "Tampilkan" (lihat servis-board.blade).
        return $this->success($tiket, 'Detail tiket servis berhasil diambil');
    }

    // [API: SERVICE-04] Update status tiket (state machine)
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string',
            'alasan' => 'nullable|string',
        ]);

        $tiket = TiketServis::findOrFail($id);

        try {
            $tiket = $this->servisService->updateStatus(
                $tiket,
                $request->status,
                $request->user(),
                $request->alasan ?? ''
            );

            return $this->success($tiket->load('garansi'), 'Status tiket servis diperbarui');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    // [API: SERVICE-05] Input estimasi biaya → menunggu_approval
    public function setEstimasi(Request $request, $id)
    {
        $request->validate([
            'estimasi_biaya' => 'required|numeric|min:0',
            'alasan' => 'required|string',
        ]);

        $tiket = TiketServis::findOrFail($id);

        try {
            $tiket = $this->servisService->setEstimasi(
                $tiket,
                (float) $request->estimasi_biaya,
                $request->alasan,
                $request->user()
            );

            return $this->success($tiket, 'Estimasi biaya tersimpan, menunggu approval pelanggan');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    // [API: SERVICE-06] Input sparepart terpakai (kurangi stok)
    public function inputSparepart(Request $request, $id)
    {
        $request->validate([
            'gudang_id' => 'required|exists:gudang,id',
            'items' => 'required|array|min:1',
            'items.*.produk_id' => 'required|exists:produk,id',
            'items.*.sku_variant_id' => 'nullable|exists:sku_variants,id',
            'items.*.jumlah' => 'required|integer|min:1',
            'items.*.harga_satuan' => 'nullable|numeric|min:0',
        ]);

        $tiket = TiketServis::findOrFail($id);

        try {
            $parts = $this->servisService->inputSparepart(
                $tiket,
                $request->items,
                (int) $request->gudang_id,
                $request->user()
            );

            return $this->success($parts, 'Sparepart dicatat & stok berkurang');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    // [API: SERVICE-06c][T-17] Input pekerjaan teknisi (split part & jasa via TiketServisItem)
    public function inputPekerjaan(Request $request, $id)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.tipe' => 'required|in:part,jasa',
            'items.*.nama_item' => 'required|string|max:255',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.harga' => 'required|numeric|min:0',
            'items.*.produk_id' => 'required_if:items.*.tipe,part|exists:produk,id',
            'items.*.gudang_id' => 'required_if:items.*.tipe,part|exists:gudang,id',
        ]);

        $tiket = TiketServis::findOrFail($id);

        try {
            $rows = $this->servisService->inputPekerjaan($tiket, $request->items, $request->user());

            return $this->success($rows, 'Pekerjaan servis dicatat (part & jasa terpisah)');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    // [API: SERVICE-06b] Approve / reject publik via token (tanpa login)

    // [API: SERVICE-07] Booking servis online dari marketplace (public)
    public function bookingOnline(Request $request)
    {
        $rateKey = 'servis-booking:'.$request->ip();
        if (RateLimiter::tooManyAttempts($rateKey, 3)) {
            return $this->error('Terlalu banyak booking, coba lagi nanti', 429);
        }
        RateLimiter::hit($rateKey, 60);

        $request->validate([
            'cabang_id' => 'nullable|exists:cabang,id',
            'nama' => 'required|string|max:255',
            'telepon' => 'required|string|max:20',
            'jenis_hp' => 'required|string|max:255',
            'seri_hp' => 'nullable|string|max:255',
            'keluhan' => 'required|string',
            'foto_unit' => 'nullable|array',
        ]);

        try {
            $tiket = $this->servisService->bookingOnline($request->all());

            return $this->success($tiket, 'Booking servis online berhasil, tunggu konfirmasi cabang', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    // [API: SERVICE-06b] Approve / reject publik via token (tanpa login)
    public function publicApprove(string $token, Request $request)
    {
        // Rate limit publik: 5 per menit per token
        $rateKey = 'servis-approve:'.$token;
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            return $this->error('Terlalu banyak percobaan, coba lagi dalam 1 menit', 429);
        }
        RateLimiter::hit($rateKey, 60);

        $request->validate([
            'action' => 'required|in:approve,reject',
            'alasan' => 'nullable|string',
        ]);

        try {
            $tiket = $this->servisService->approveByToken($token, $request->action, $request->alasan ?? '');

            return $this->success([
                'no_tiket' => $tiket->no_tiket,
                'status' => $tiket->status,
                'message' => $request->action === 'approve'
                    ? 'Estimasi disetujui, servis akan dikerjakan'
                    : 'Estimasi ditolak',
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    // [API: SERVICE-08] Tracking servis publik oleh token (status penuh hanya bila login)
    public function trackingPublik(Request $request, string $token)
    {
        $tiket = TiketServis::with(['garansi', 'jenisServis', 'cabang'])
            ->where('token_approval', $token)
            ->first();

        if (! $tiket) {
            return $this->error('Tiket servis tidak ditemukan', 404);
        }

        // Status dasar publik; detail pandangan penuh via handle
        $data = [
            'no_tiket' => $tiket->no_tiket,
            'jenis_hp' => $tiket->jenis_hp,
            'status' => $tiket->status,
            'estimasi_biaya' => $tiket->estimasi_biaya,
            'tanggal_terima' => $tiket->tanggal_terima?->format('d/m/Y H:i'),
            'tanggal_selesai' => $tiket->tanggal_selesai?->format('d/m/Y H:i'),
            'cabang' => $tiket->cabang?->nama,
            'garansi' => $tiket->garansi ? [
                'mulai' => $tiket->garansi->tanggal_mulai->format('d/m/Y'),
                'berakhir' => $tiket->garansi->tanggal_berakhir->format('d/m/Y'),
                'aktif' => $tiket->garansi->active,
            ] : null,
        ];

        return $this->success($data, 'Status servis berhasil dimuat');
    }
}
