<?php

namespace App\Modules\Crm\Controllers;

use App\Modules\Crm\Models\KampanyeBroadcast;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Crm\Services\BroadcastService;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Crm\Services\LeadService;
use App\Modules\Crm\Services\PelangganService;
use App\Modules\Crm\Services\TierService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Services\AuditService;
use App\Modules\Reseller\Models\SkemaKomisi;
use App\Modules\Servis\Models\TiketServis;
use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CrmController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TierService $tierService,
        protected LeadService $leadService
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
        $pelanggan = app(PelangganService::class)->create($request->all());

        return $this->success($pelanggan, 'Pelanggan berhasil dibuat', 201);
    }

    // [API: CRM-07][T-22] Konfigurasi strategi loyalitas (editable tanpa deploy)
    public function config(Request $request)
    {
        $config = app(KonfigurasiService::class);

        if ($request->isMethod('get')) {
            // [T-22] GET harus membaca nilai TERSIMPAN (bukan hardcode defaults()):
            // tiap kunci: nilai DB bila ada, fallback default bila belum pernah disimpan.
            // [B-15d] 1 query `whereIn` utk 5 kunci (sebelumnya 1 query per kunci).
            $defaults = $config->defaults();
            $terimpan = $config->getMany(array_keys($defaults));

            $stored = [];
            foreach ($defaults as $kunci => $default) {
                $stored[$kunci] = $terimpan[$kunci] ?? $default;
            }
            $stored['skema_komisi_default'] = SkemaKomisi::orderBy('id')->get();

            return $this->success($stored, 'Konfigurasi loyalitas berhasil dimuat');
        }

        $request->validate([
            'poin_earn_persen' => 'required|numeric|min:0|max:100',
            'poin_redeem_rupiah' => 'required|numeric|min:0',
            'diskon_silver' => 'required|numeric|min:0|max:100',
            'diskon_gold' => 'required|numeric|min:0|max:100',
            'diskon_platinum' => 'required|numeric|min:0|max:100',
        ]);

        foreach ($request->only(['poin_earn_persen', 'poin_redeem_rupiah', 'diskon_silver', 'diskon_gold', 'diskon_platinum']) as $k => $v) {
            $config->set($k, (float) $v, 'Strategi loyalitas');
        }

        // Terapkan diskon tier
        TierMembership::where('kode', 'silver')->update(['diskon_persen' => $request->diskon_silver]);
        TierMembership::where('kode', 'gold')->update(['diskon_persen' => $request->diskon_gold]);
        TierMembership::where('kode', 'platinum')->update(['diskon_persen' => $request->diskon_platinum]);

        return $this->success(null, 'Konfigurasi loyalitas tersimpan');
    }

    // [API: CRM-08][T-23] Buat & kirim broadcast kampanye
    public function broadcastKampanye(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'judul' => 'required|string|max:255',
            'pesan' => 'required|string',
            'channel' => 'required|in:wa,email,inapp',
            'segment' => 'nullable|array',
            'segment.*.tipe' => ['required', 'string', Rule::in(BroadcastService::SEGMENT_TYPES)],
            'segment.*.nilai' => 'nullable',
            'dijadwalkan_at' => 'nullable|date|after_or_equal:now',
            // Alias typo lama; tetap diterima untuk backward-compat.
            'jadiwalkan_at' => 'nullable|date|after_or_equal:now',
        ], [
            'segment.*.tipe.required' => 'Tipe segmen wajib diisi.',
            'segment.*.tipe.string' => 'Tipe segmen harus berupa teks.',
            'segment.*.tipe.in' => 'Tipe segmen tidak dikenal.',
            'dijadwalkan_at.date' => 'Format tanggal jadwal tidak valid.',
            'dijadwalkan_at.after_or_equal' => 'Jadwal tidak boleh berada di masa lalu.',
            'jadiwalkan_at.date' => 'Format tanggal jadwal tidak valid.',
            'jadiwalkan_at.after_or_equal' => 'Jadwal tidak boleh berada di masa lalu.',
        ]);

        $validator->after(function (ValidatorContract $validator) use ($request): void {
            $segments = $request->input('segment');
            if (! is_array($segments)) {
                return;
            }

            $hasBirthdayMonth = false;
            $hasBirthdayDay = false;

            foreach ($segments as $index => $segment) {
                if (! is_array($segment)) {
                    continue;
                }

                $tipe = $segment['tipe'] ?? null;
                $nilai = $segment['nilai'] ?? null;
                $hasBirthdayMonth = $hasBirthdayMonth || $tipe === 'birthday_month';
                $hasBirthdayDay = $hasBirthdayDay || $tipe === 'birthday_day';

                if (in_array($tipe, ['tier', 'belum_belanja_hari', 'birthday_month', 'birthday_day'], true)
                    && ($nilai === null || $nilai === '')) {
                    $validator->errors()->add("segment.{$index}.nilai", 'Nilai segmen wajib diisi.');
                }

                if ($tipe === 'belum_belanja_hari'
                    && ($nilai === null || $nilai === '' || (int) $nilai < 1)) {
                    $validator->errors()->add("segment.{$index}.nilai", 'Jumlah hari untuk segmen belum belanja harus minimal 1.');
                }

                if ($tipe === 'birthday_month'
                    && ($nilai === null || $nilai === '' || (int) $nilai < 1 || (int) $nilai > 12)) {
                    $validator->errors()->add("segment.{$index}.nilai", 'Bulan ulang tahun harus berada di antara 1 sampai 12.');
                }

                if ($tipe === 'birthday_day'
                    && ($nilai === null || $nilai === '' || (int) $nilai < 1 || (int) $nilai > 31)) {
                    $validator->errors()->add("segment.{$index}.nilai", 'Hari ulang tahun harus berada di antara 1 sampai 31.');
                }
            }

            if ($hasBirthdayDay && ! $hasBirthdayMonth) {
                $validator->errors()->add(
                    'segment',
                    'Segmen birthday_day wajib menyertakan birthday_month.'
                );
            }
        });

        $validated = $validator->validate();

        $jadwal = $validated['dijadwalkan_at'] ?? null;
        if ($jadwal === null || $jadwal === '') {
            $jadwal = $validated['jadiwalkan_at'] ?? null;
        }

        $kampanye = KampanyeBroadcast::create([
            'judul' => $validated['judul'],
            'pesan' => $validated['pesan'],
            'channel' => $validated['channel'],
            'segment' => $validated['segment'] ?? [],
            'status' => $jadwal ? 'terjadwal' : 'draft',
            'dijadwalkan_at' => $jadwal,
            'user_id' => auth()->id(),
        ]);

        if (! $jadwal) {
            app(BroadcastService::class)->kirimSekarang($kampanye);
        }

        return $this->success($kampanye, 'Broadcast kampanye dibuat', 201);
    }

    // [API: CRM-09][T-23] Log pengiriman kampanye
    public function broadcastLog(Request $request, $id)
    {
        return $this->success(
            app(BroadcastService::class)->logPengiriman($id),
            'Log pengiriman kampanye berhasil dimuat'
        );
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
            'kode' => 'sometimes|string|max:50|unique:tier_memberships,kode,'.$id,
            'min_belanja_12bulan' => 'sometimes|numeric|min:0',
            'diskon_persen' => 'sometimes|numeric|min:0|max:100',
            'poin_multiplier' => 'sometimes|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $tier->update($request->all());

        app(AuditService::class)->catat(
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

    // [API: CRM-05] Broadcast promo ke pelanggan (legacy; tetap membuat campaign)
    public function broadcast(Request $request)
    {
        $validated = $request->validate([
            'tier_id' => 'nullable|exists:tier_memberships,id',
            'is_reseller' => 'nullable|boolean',
            'judul' => 'required|string|max:255',
            'pesan' => 'required|string',
        ]);

        $segment = [];
        if ($request->filled('tier_id')) {
            $segment[] = ['tipe' => 'tier', 'nilai' => (int) $validated['tier_id']];
        }
        if ($request->has('is_reseller') && $request->input('is_reseller') !== null) {
            $segment[] = [
                'tipe' => 'reseller',
                'nilai' => filter_var($validated['is_reseller'], FILTER_VALIDATE_BOOLEAN),
            ];
        }

        // CRM-05 tetap dipertahankan, tetapi memakai satu alur BroadcastService
        // supaya setiap request legacy juga menghasilkan KampanyeBroadcast + log outbox.
        $kampanye = KampanyeBroadcast::create([
            'judul' => $validated['judul'],
            'pesan' => "[Broadcast] {$validated['pesan']}",
            'channel' => 'inapp',
            'segment' => $segment,
            'status' => 'draft',
            'user_id' => auth()->id(),
        ]);

        $kampanye = app(BroadcastService::class)->kirimSekarang($kampanye);

        return $this->success([
            'target_pelanggan' => (int) $kampanye->total_target,
            'kampanye_id' => $kampanye->id,
        ], "Broadcast promo dikirim ke {$kampanye->total_target} pelanggan via antrian");
    }

    // [API: PRICING-support] Progress tier berikutnya
    private function nextTierProgress(Pelanggan $pelanggan): array
    {
        $belanja = (float) $pelanggan->total_belanja_12bulan;
        $tiers = TierMembership::where('is_active', true)->orderBy('min_belanja_12bulan')->get();

        $current = $tiers->firstWhere('id', $pelanggan->tier_membership_id);
        $next = $tiers->first(fn ($t) => (float) $t->min_belanja_12bulan > $belanja);

        if (! $next) {
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

    // ========== LEAD PIPELINE (F2-1) ==========

    // [API: CRM-10] Daftar lead (paginated, filterable)
    public function indexLeads(Request $request)
    {
        $search = $request->query('search');
        $stage = $request->query('stage');
        $sumber = $request->query('sumber');
        $assignedTo = $request->query('assigned_to');

        $query = Lead::with(['assignedTo', 'pelanggan'])
            ->forCabang()
            ->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('telepon', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($stage) {
            $query->where('stage', $stage);
        }

        if ($sumber) {
            $query->where('sumber', $sumber);
        }

        if ($assignedTo) {
            $query->where('assigned_to', $assignedTo);
        }

        return $this->success($query->paginate(20), 'Daftar lead berhasil diambil');
    }

    // [API: CRM-11] Detail lead
    public function showLead(Request $request, $id)
    {
        $lead = Lead::with(['assignedTo', 'pelanggan', 'cabang'])
            ->forCabang()
            ->findOrFail($id);

        return $this->success($lead, 'Detail lead berhasil diambil');
    }

    // [API: CRM-12] Create lead
    public function storeLead(Request $request)
    {
        $request->validate([
            'sumber' => 'required|string|in:walkin,phone,website,referral,social_media,marketplace,lain',
            'stage' => 'sometimes|in:baru,kontak,kualifikasi,negosiasi,won,lost',
            'nama' => 'required|string|max:255',
            'telepon' => 'nullable|string|max:20|unique:leads,telepon',
            'email' => 'nullable|email|unique:leads,email',
            'nilai_estimasi' => 'sometimes|numeric|min:0',
            'assigned_to' => 'nullable|exists:users,id',
            'catatan' => 'nullable|string',
        ]);

        $data = $request->all();
        $data['cabang_id'] = session('cabang_id');

        $lead = $this->leadService->create($data);

        return $this->success($lead, 'Lead berhasil dibuat', 201);
    }

    // [API: CRM-13] Update lead
    public function updateLead(Request $request, $id)
    {
        $lead = Lead::forCabang()->findOrFail($id);

        $request->validate([
            'sumber' => 'sometimes|string|in:walkin,phone,website,referral,social_media,marketplace,lain',
            'stage' => 'sometimes|in:baru,kontak,kualifikasi,negosiasi,won,lost',
            'nama' => 'sometimes|string|max:255',
            'telepon' => 'nullable|string|max:20|unique:leads,telepon,'.$id,
            'email' => 'nullable|email|unique:leads,email,'.$id,
            'nilai_estimasi' => 'sometimes|numeric|min:0',
            'assigned_to' => 'nullable|exists:users,id',
            'catatan' => 'nullable|string',
            'lost_reason' => 'nullable|string|in:harga,kompetitor,tidak_butuh,lain',
        ]);

        $lead = $this->leadService->update($lead, $request->all());

        return $this->success($lead, 'Lead berhasil diperbarui');
    }

    // [API: CRM-14] Bulk update lead stage (drag-and-drop kanban)
    public function bulkUpdateLeadStage(Request $request)
    {
        $request->validate([
            'lead_ids' => 'required|array|min:1',
            'lead_ids.*' => 'exists:leads,id',
            'stage' => 'required|in:baru,kontak,kualifikasi,negosiasi,won,lost',
        ]);

        $updated = $this->leadService->bulkUpdateStage(
            $request->lead_ids,
            $request->stage
        );

        return $this->success(['updated' => $updated], "{$updated} lead diperbarui ke stage {$request->stage}");
    }

    // [API: CRM-15] Convert won lead to pelanggan
    public function convertLead(Request $request, $id)
    {
        $lead = Lead::forCabang()->findOrFail($id);

        if (! $lead->canConvert()) {
            return $this->error('Lead tidak dapat dikonversi: harus stage Won dan belum memiliki pelanggan.', 422);
        }

        $pelanggan = $this->leadService->convertToPelanggan($lead);

        return $this->success($pelanggan, 'Lead berhasil dikonversi ke pelanggan');
    }

    // [API: CRM-16] Lead kanban data (grouped by stage)
    public function kanbanLeads(Request $request)
    {
        $assignedTo = $request->query('assigned_to') ? (int) $request->query('assigned_to') : null;

        $data = $this->leadService->getKanbanData(null, $assignedTo);

        return $this->success($data, 'Data kanban lead berhasil diambil');
    }

    // [API: CRM-17] Lead funnel data (for dashboard)
    public function funnelLeads(Request $request)
    {
        $data = $this->leadService->getFunnelData();

        return $this->success($data, 'Data funnel lead berhasil diambil');
    }

    // [API: CRM-18] Delete lead
    public function destroyLead(Request $request, $id)
    {
        $lead = Lead::forCabang()->findOrFail($id);

        $this->leadService->delete($lead);

        return $this->success(null, 'Lead berhasil dihapus');
    }
}
