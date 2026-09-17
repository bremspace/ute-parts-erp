<?php

namespace App\Modules\Crm\Controllers;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Crm\Services\TierService;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Servis\Models\TiketServis;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CrmController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TierService $tierService,
        protected NotificationService $notifService
    ) {}

    // [API: CRM-01] Profil pelanggan 360°
    public function show(Request $request, $id)
    {
        $pelanggan = Pelanggan::with([
            'tierMembership',
        ])->findOrFail($id);

        // Riwayat pembelian
        $transaksi = Transaksi::with('items.produk')
            ->where('pelanggan_id', $id)
            ->latest()
            ->take(10)
            ->get();

        // Riwayat servis
        $servis = TiketServis::with('garansi')
            ->where('pelanggan_id', $id)
            ->latest()
            ->take(10)
            ->get();

        // Komisi (jika reseller)
        $komisi = $pelanggan->is_reseller
            ? $pelanggan->komisi()->selectRaw('status, sum(nominal_komisi) as total')->groupBy('status')->get()
            : collect();

        // Progress ke tier berikutnya
        $progress = $this->nextTierProgress($pelanggan);

        return $this->success([
            'pelanggan' => $pelanggan,
            'riwayat_transaksi' => $transaksi,
            'riwayat_servis' => $servis,
            'komisi' => $komisi,
            'progress_tier' => $progress,
        ], 'Profil pelanggan 360° berhasil diambil');
    }

    // [API: CRM-06][T-04] Create pelanggan dari backoffice (satu sumber: POS/Servis/CRM)
    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'telepon' => 'required|string|max:20|unique:pelanggan,telepon',
            'email' => 'nullable|email|unique:pelanggan,email',
            'alamat' => 'nullable|string',
            'is_reseller' => 'sometimes|boolean',
        ]);

        $tierTerendah = TierMembership::where('is_active', true)->orderBy('min_belanja_12bulan')->first();

        $pelanggan = Pelanggan::create([
            'nama' => $request->nama,
            'telepon' => $request->telepon,
            'email' => $request->email,
            'alamat' => $request->alamat,
            'tier_membership_id' => $tierTerendah?->id,
            'is_reseller' => (bool) ($request->is_reseller ?? false),
            'total_belanja_12bulan' => 0,
            'poin_loyalty' => 0,
        ]);

        return $this->success($pelanggan->load('tierMembership'), 'Pelanggan berhasil dibuat', 201);
    }

    // [API: CRM-02] Daftar pelanggan
    public function index(Request $request)
    {
        $search = $request->query('search');
        $tier = $request->query('tier');
        $reseller = $request->query('reseller');

        $query = Pelanggan::with('tierMembership')->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('telepon', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($tier) {
            $query->where('tier_membership_id', $tier);
        }

        if ($reseller !== null && $reseller !== '') {
            $query->where('is_reseller', filter_var($reseller, FILTER_VALIDATE_BOOLEAN));
        }

        return $this->success($query->paginate(20), 'Daftar pelanggan berhasil diambil');
    }

    // [API: CRM-03] CRUD Tier Membership
    public function indexTiers()
    {
        return $this->success(TierMembership::orderBy('urutan')->get(), 'Daftar tier berhasil diambil');
    }

    public function storeTier(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'kode' => 'required|string|max:50|unique:tier_memberships,kode',
            'min_belanja_12bulan' => 'required|numeric|min:0',
            'diskon_persen' => 'required|numeric|min:0|max:100',
            'poin_multiplier' => 'required|numeric|min:0',
            'urutan' => 'nullable|integer',
        ]);

        $tier = TierMembership::create($request->all());

        return $this->success($tier, 'Tier berhasil dibuat', 201);
    }

    public function updateTier(Request $request, $id)
    {
        $tier = TierMembership::findOrFail($id);

        $request->validate([
            'nama' => 'sometimes|string|max:255',
            'kode' => 'sometimes|string|max:50|unique:tier_memberships,kode,' . $id,
            'min_belanja_12bulan' => 'sometimes|numeric|min:0',
            'diskon_persen' => 'sometimes|numeric|min:0|max:100',
            'poin_multiplier' => 'sometimes|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $tier->update($request->all());

        app(\App\Modules\Rbac\Services\AuditService::class)->catat(
            'TierMembership', 'update', $tier->id,
            "Konfigurasi tier {$tier->nama} diperbarui (diskon {$tier->diskon_persen}%, min belanja {$tier->min_belanja_12bulan})"
        );

        return $this->success($tier, 'Tier berhasil diperbarui');
    }

    // [API: CRM-04] Rekalkulasi tier manual
    public function recalcTiers()
    {
        $updated = $this->tierService->recalcSemua();

        return $this->success(['diperbarui' => $updated], "Rekalkulasi tier selesai, {$updated} pelanggan diperbarui");
    }

    // [API: CRM-05] Broadcast promo ke pelanggan (via queue)
    public function broadcast(Request $request)
    {
        $request->validate([
            'tier_id' => 'nullable|exists:tier_memberships,id',
            'is_reseller' => 'nullable|boolean',
            'judul' => 'required|string|max:255',
            'pesan' => 'required|string',
        ]);

        $query = Pelanggan::query();
        if ($request->tier_id) {
            $query->where('tier_membership_id', $request->tier_id);
        }
        if ($request->has('is_reseller') && $request->is_reseller !== null) {
            $query->where('is_reseller', filter_var($request->is_reseller, FILTER_VALIDATE_BOOLEAN));
        }

        $targetCount = $query->count();

        // Broadcast via queue (PRD §4.9) — batch dari semua pelanggan
        $query->chunkById(100, function ($pelangganList) use ($request) {
            foreach ($pelangganList as $p) {
                $this->notifService->kirim(
                    'inapp', null,
                    $request->judul,
                    "[Broadcast] {$request->pesan}",
                    ['pelanggan_id' => $p->id]
                );
            }
        });

        return $this->success(['target_pelanggan' => $targetCount], "Broadcast promo dikirim ke {$targetCount} pelanggan via antrian");
    }

    // [API: PRICING-support] Progress tier berikutnya
    private function nextTierProgress(Pelanggan $pelanggan): array
    {
        $belanja = (float) $pelanggan->total_belanja_12bulan;
        $tiers = TierMembership::where('is_active', true)->orderBy('min_belanja_12bulan')->get();

        $current = $tiers->firstWhere('id', $pelanggan->tier_membership_id);
        $next = $tiers->first(fn ($t) => (float) $t->min_belanja_12bulan > $belanja);

        if (!$next) {
            return ['tier_berikutnya' => null, 'progress_persen' => 100, 'sisa_belanja' => 0];
        }

        $prevThreshold = (float) ($current->min_belanja_12bulan ?? 0);
        $nextThreshold = (float) $next->min_belanja_12bulan;
        $range = max(1, $nextThreshold - $prevThreshold);
        $progress = round((($belanja - $prevThreshold) / $range) * 100, 1);

        return [
            'tier_berikutnya' => $next->nama,
            'progress_persen' => min(100, max(0, $progress)),
            'sisa_belanja' => max(0, $nextThreshold - $belanja),
        ];
    }
}