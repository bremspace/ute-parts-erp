<?php

namespace App\Modules\Reseller\Controllers;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Reseller\Models\SkemaKomisi;
use App\Modules\Reseller\Services\KomisiService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ResellerController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected KomisiService $komisiService
    ) {}

    // [API: RESELLER-05][T-21] Daftarkan reseller baru + skema komisi custom per kategori
    public function daftarReseller(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'telepon' => 'required|string|max:20|unique:pelanggan,telepon',
            'email' => 'nullable|email',
            'skema' => 'required|array|min:1',
            'skema.*.kategori' => 'nullable|string|max:100',
            'skema.*.tipe' => 'required|in:persen,nominal',
            'skema.*.nilai' => 'required|numeric|min:0',
        ]);

        $tier = \App\Modules\Crm\Models\TierMembership::where('is_active', true)->first();

        $pelanggan = \App\Modules\Crm\Models\Pelanggan::create([
            'nama' => $request->nama,
            'telepon' => $request->telepon,
            'email' => $request->email,
            'tier_membership_id' => $tier?->id,
            'is_reseller' => true,
        ]);

        foreach ($request->skema as $s) {
            \App\Modules\Reseller\Models\SkemaKomisiReseller::create([
                'pelanggan_id' => $pelanggan->id,
                'kategori' => $s['kategori'] ?: null,
                'tipe' => $s['tipe'],
                'nilai' => $s['nilai'],
                'is_active' => true,
            ]);
        }

        return $this->success($pelanggan->load('komisi'), 'Reseller terdaftar dengan skema custom', 201);
    }

    // [API: RESELLER-06][T-21] List skema custom per reseller
    public function skemaReseller(Request $request, $id)
    {
        return $this->success(
            \App\Modules\Reseller\Models\SkemaKomisiReseller::where('pelanggan_id', $id)->get(),
            'Skema custom reseller berhasil dimuat'
        );
    }

    // [API: RESELLER-01] Daftar reseller + omzet + komisi terhutang/terbayar
    public function index(Request $request)
    {
        $search = $request->query('search');

        $query = Pelanggan::with(['tierMembership', 'komisi'])
            ->where('is_reseller', true)
            ->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('telepon', 'like', "%{$search}%");
            });
        }

        $resellers = $query->paginate(20)->through(function ($r) {
            $lunas = $r->komisi->where('status', 'disetujui')->sum('nominal_komisi');
            $pending = $r->komisi->where('status', 'pending')->sum('nominal_komisi');

            return [
                'id' => $r->id,
                'nama' => $r->nama,
                'telepon' => $r->telepon,
                'email' => $r->email,
                'total_belanja_12bulan' => $r->total_belanja_12bulan,
                'tier' => $r->tierMembership?->nama,
                'komisi_terhutang' => $lunas, // sudah disetujui (menjadi utang)
                'komisi_pending' => $pending, // menunggu approval
                'total_komisi' => $lunas + $pending,
            ];
        });

        return $this->success($resellers, 'Daftar reseller berhasil diambil');
    }

    // [API: RESELLER-02] Daftar komisi (filter status)
    public function indexKomisi(Request $request)
    {
        $status = $request->query('status'); // pending, disetujui, ditolak
        $resellerId = $request->query('reseller_id');

        $query = Komisi::with(['pelanggan', 'transaksi', 'approver'])
            ->latest();

        if ($status) {
            $query->where('status', $status);
        }

        if ($resellerId) {
            $query->where('pelanggan_id', $resellerId);
        }

        $page = $request->query('all', false)
            ? $query->get()
            : $query->paginate(15);

        return $this->success($page, 'Daftar komisi berhasil diambil');
    }

    // [API: RESELLER-03] Approval komisi (role finance) → jurnal + utang
    public function approveKomisi(Request $request)
    {
        $request->validate([
            'komisi_ids' => 'required|array|min:1',
            'komisi_ids.*' => 'integer',
            'action' => 'required|in:approve,reject',
        ]);

        try {
            $approvedIds = $this->komisiService->prosesApproval(
                $request->komisi_ids,
                $request->action,
                auth()->id()
            );

            return $this->success(
                ['diproses' => count($approvedIds)],
                $request->action === 'approve'
                    ? 'Komisi disetujui, jurnal & utang komisi dibuat otomatis'
                    : 'Komisi ditolak'
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    // [API: RESELLER-04] Skema komisi CRUD (marketing/super-admin)
    public function indexSkemaKomisi()
    {
        return $this->success(SkemaKomisi::orderBy('id')->get(), 'Daftar skema komisi berhasil diambil');
    }

    public function storeSkemaKomisi(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'kategori' => 'nullable|string|max:255',
            'tipe' => 'required|in:persen,nominal',
            'nilai' => 'required|numeric|min:0',
        ]);

        $skema = SkemaKomisi::create($request->all());

        return $this->success($skema, 'Skema komisi berhasil dibuat', 201);
    }
}