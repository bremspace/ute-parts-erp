<?php

namespace App\Modules\Crm\Livewire;

use App\Modules\Crm\Models\KampanyeBroadcast;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Crm\Services\BroadcastService;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Crm\Services\PelangganService;
use App\Modules\Crm\Services\TierService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Workflow\Services\ApprovalService;
use App\Traits\ParsesNominal;
use Database\Seeders\TierMembershipSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class CrmDashboard extends Component
{
    use ParsesNominal;
    use WithPagination;

    public string $search = '';

    public ?int $filterTierId = null;

    public bool $filterReseller = false;

    public string $filterTipeKonsumen = ''; // '', 'retail', 'reseller', 'agen'

    public string $activeTab = 'pelanggan'; // 'pelanggan' | 'tier' | 'broadcast'

    protected $queryString = [
        'search' => ['except' => ''],
        'filterTierId' => ['except' => null],
        'filterReseller' => ['except' => false],
        'filterTipeKonsumen' => ['except' => ''],
        'activeTab' => ['except' => 'pelanggan'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterTierId(): void
    {
        $this->resetPage();
    }

    public function updatingFilterTipeKonsumen(): void
    {
        $this->resetPage();
    }

    public function updatingFilterReseller(): void
    {
        $this->resetPage();
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['pelanggan', 'tier', 'broadcast']) ? $tab : 'pelanggan';
    }

    public function resetFilter(): void
    {
        $this->search = '';
        $this->filterTierId = null;
        $this->filterReseller = false;
        $this->filterTipeKonsumen = '';
        $this->resetPage();
    }

    // Detail customer modal
    public ?int $selectedCustomerId = null;

    // Tier modal
    public bool $showTierModal = false;

    public array $tierForm = [
        'id' => null, 'nama' => '', 'kode' => '', 'min_belanja_12bulan' => 0,
        'diskon_persen' => 0, 'poin_multiplier' => 1.0, 'poin_redeem_rupiah' => null, 'urutan' => 0, 'is_active' => true,
    ];

    // Konfigurasi Global Loyalty & Partai
    public float $poinEarnPersenGlobal = 10.0;

    public float $poinRedeemRupiahGlobal = 100.0;

    public bool $hargaPartaiAktif = true;

    public string $hargaPartaiBerlakuUntuk = 'semua';

    public bool $hargaPartaiOpsiQty = true;

    public ?int $hargaPartaiMinQty = 5;

    public ?float $hargaPartaiQtyDiskon = 3.0;

    public bool $hargaPartaiOpsiNominal = true;

    public ?float $hargaPartaiMinNominal = 1000000.0;

    public ?float $hargaPartaiNominalDiskon = 2.0;

    // Multi-tier / Multi-level Diskon Partai
    public array $hargaPartaiTiersQty = [];

    public array $hargaPartaiTiersNominal = [];

    // Skema Default Mitra (Reseller & Agen) & Proteksi Poin
    public float $resellerDefaultPersen = 5.0;

    public float $agenDefaultPersen = 10.0;

    public bool $mitraDapatPoin = false;

    // Komponen Potongan & Buffer Biaya Margin Guard (SSOT)
    public float $marginGuardBufferPersen = 5.0;

    public float $paymentGatewayFeePersen = 0.0;

    public float $poinMaksPotonganPersen = 50.0;

    public float $komisiAgenDefaultPersen = 5.0;

    public function mount(): void
    {
        $config = app(KonfigurasiService::class);
        $this->poinEarnPersenGlobal = (float) ($config->get('poin_earn_persen') ?? $config->defaults()['poin_earn_persen'] ?? 10.0);
        $this->poinRedeemRupiahGlobal = $config->poinRedeemRupiah();
        $this->hargaPartaiAktif = $config->hargaPartaiAktif();
        $this->hargaPartaiBerlakuUntuk = $config->hargaPartaiBerlakuUntuk();
        $this->resellerDefaultPersen = $config->resellerDefaultPersen();
        $this->agenDefaultPersen = $config->agenDefaultPersen();
        $this->mitraDapatPoin = $config->mitraDapatPoin();

        $this->marginGuardBufferPersen = $config->marginGuardBufferPersen();
        $this->paymentGatewayFeePersen = $config->paymentGatewayFeePersen();
        $this->poinMaksPotonganPersen = $config->poinMaksPotonganPersen();
        $this->komisiAgenDefaultPersen = $config->komisiAgenDefaultPersen();

        $minQty = $config->hargaPartaiMinQty();
        $qtyDiskon = $config->hargaPartaiQtyDiskonPersen();
        $minNominal = $config->hargaPartaiMinNominal();
        $nominalDiskon = $config->hargaPartaiNominalDiskonPersen();

        $this->hargaPartaiOpsiQty = ($minQty > 0 && $qtyDiskon > 0);
        $this->hargaPartaiMinQty = $minQty > 0 ? $minQty : 5;
        $this->hargaPartaiQtyDiskon = $qtyDiskon > 0 ? $qtyDiskon : 3.0;

        $this->hargaPartaiOpsiNominal = ($minNominal > 0 && $nominalDiskon > 0);
        $this->hargaPartaiMinNominal = $minNominal > 0 ? $minNominal : 1000000.0;
        $this->hargaPartaiNominalDiskon = $nominalDiskon > 0 ? $nominalDiskon : 2.0;

        $this->hargaPartaiTiersQty = $config->hargaPartaiTiersQty();
        if (empty($this->hargaPartaiTiersQty) && $this->hargaPartaiMinQty > 0 && $this->hargaPartaiQtyDiskon > 0) {
            $this->hargaPartaiTiersQty = [['min_qty' => $this->hargaPartaiMinQty, 'diskon_persen' => $this->hargaPartaiQtyDiskon]];
        }

        $this->hargaPartaiTiersNominal = $config->hargaPartaiTiersNominal();
        if (empty($this->hargaPartaiTiersNominal) && $this->hargaPartaiMinNominal > 0 && $this->hargaPartaiNominalDiskon > 0) {
            $this->hargaPartaiTiersNominal = [['min_nominal' => $this->hargaPartaiMinNominal, 'diskon_persen' => $this->hargaPartaiNominalDiskon]];
        }
    }

    // Broadcast modal
    public bool $showBroadcastModal = false;

    public string $broadcastJudul = '';

    public string $broadcastPesan = '';

    public string $broadcastChannel = 'inapp';

    public string $broadcastSegmentType = 'semua'; // 'semua', 'tier', 'reseller', 'belum_belanja', 'birthday'

    public ?int $broadcastTierId = null;

    public bool $broadcastReseller = false;

    public int $broadcastHariBelanja = 30;

    public int $broadcastBulanLahir = 1;

    // [T-04] Tambah pelanggan dari CRM (CRM-06)
    public bool $showPelangganBaruModal = false;

    public array $pelangganBaruForm = [
        'nama' => '', 'telepon' => '', 'email' => '', 'alamat' => '',
        'tanggal_lahir' => '', 'is_reseller' => false, 'tipe_konsumen' => 'retail',
        'kode_agen' => '', 'referral_kode' => '',
    ];

    public bool $showEditPelangganModal = false;

    public ?int $editPelangganId = null;

    public array $editPelangganForm = [
        'nama' => '', 'telepon' => '', 'email' => '', 'alamat' => '',
        'tanggal_lahir' => '', 'tier_membership_id' => null, 'is_reseller' => false,
        'tipe_konsumen' => 'retail', 'kode_agen' => '', 'referral_kode' => '',
    ];

    // Reset Password Konsumen Modal
    public bool $showResetPasswordModal = false;

    public ?int $resetPasswordPelangganId = null;

    public array $resetPasswordForm = [
        'nama' => '',
        'telepon' => '',
        'username' => '',
        'new_password' => '',
        'alasan' => '',
    ];

    public function simpanPelangganBaruCrm()
    {
        if (! $this->boleh('crm.create', 'Anda tidak memiliki izin menambah pelanggan.')) {
            return;
        }

        try {
            $pelanggan = app(PelangganService::class)->create(
                $this->pelangganBaruForm,
                session('cabang_id'),
                auth()->id()
            );

            $this->showPelangganBaruModal = false;
            $this->pelangganBaruForm = [
                'nama' => '', 'telepon' => '', 'email' => '', 'alamat' => '',
                'tanggal_lahir' => '', 'is_reseller' => false, 'tipe_konsumen' => 'retail',
                'kode_agen' => '', 'referral_kode' => '',
            ];

            $pesan = $pelanggan->status === 'pending'
                ? 'Pelanggan baru disimpan dan diajukan ke approval owner (status: pending)'
                : 'Pelanggan baru disimpan (tersedia di POS & Servis)';
            $this->dispatch('alert', ['type' => 'success', 'message' => $pesan]);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->getMessageBag());
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function openEditPelangganModal(int $id): void
    {
        $user = auth()->user();
        if (! isSuperAdminOrOwner($user) && ! ($user && ($user->hasRole('admin-toko') || $user->hasRole('marketing') || $user->can('crm.edit') || $user->can('crm.create')))) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Hanya Super Admin, Owner, Admin Toko, atau staf berwenang yang berhak mengedit data pelanggan.']);

            return;
        }

        $p = Pelanggan::findOrFail($id);
        $this->editPelangganId = $p->id;
        $this->editPelangganForm = [
            'nama' => $p->nama,
            'telepon' => $p->telepon,
            'email' => $p->email ?? '',
            'alamat' => $p->alamat ?? '',
            'tanggal_lahir' => $p->tanggal_lahir?->format('Y-m-d') ?? '',
            'tier_membership_id' => $p->tier_membership_id,
            'is_reseller' => (bool) $p->is_reseller,
            'tipe_konsumen' => $p->tipe_konsumen ?? ($p->is_reseller ? 'reseller' : 'retail'),
            'kode_agen' => $p->kode_agen ?? '',
            'referral_kode' => $p->referral_kode ?? '',
        ];
        $this->showEditPelangganModal = true;
    }

    public function updatedEditPelangganFormTipeKonsumen($value): void
    {
        $this->editPelangganForm['is_reseller'] = in_array($value, ['reseller', 'agen'], true);
        if ($value !== 'agen') {
            $this->editPelangganForm['kode_agen'] = '';
        }
    }

    public function simpanEditPelanggan(): void
    {
        $user = auth()->user();
        if (! isSuperAdminOrOwner($user) && ! ($user && ($user->hasRole('admin-toko') || $user->hasRole('marketing') || $user->can('crm.edit') || $user->can('crm.create')))) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Hanya Super Admin, Owner, Admin Toko, atau staf berwenang yang berhak mengedit data pelanggan.']);

            return;
        }

        if ($this->editPelangganForm['tipe_konsumen'] === 'retail') {
            $this->editPelangganForm['is_reseller'] = false;
            $this->editPelangganForm['kode_agen'] = null;
        } elseif ($this->editPelangganForm['tipe_konsumen'] === 'reseller') {
            $this->editPelangganForm['is_reseller'] = true;
            $this->editPelangganForm['kode_agen'] = null;
        } elseif ($this->editPelangganForm['tipe_konsumen'] === 'agen') {
            $this->editPelangganForm['is_reseller'] = true;
        }

        if (empty($this->editPelangganForm['email'])) {
            $this->editPelangganForm['email'] = null;
        }
        if (empty($this->editPelangganForm['kode_agen'])) {
            $this->editPelangganForm['kode_agen'] = null;
        }
        if (empty($this->editPelangganForm['referral_kode'])) {
            $this->editPelangganForm['referral_kode'] = null;
        }
        if (empty($this->editPelangganForm['tanggal_lahir'])) {
            $this->editPelangganForm['tanggal_lahir'] = null;
        }
        if (empty($this->editPelangganForm['alamat'])) {
            $this->editPelangganForm['alamat'] = null;
        }

        $this->validate([
            'editPelangganForm.nama' => 'required|string|max:255',
            'editPelangganForm.telepon' => 'required|string|max:20|unique:pelanggan,telepon,'.$this->editPelangganId,
            'editPelangganForm.email' => 'nullable|email|unique:pelanggan,email,'.$this->editPelangganId,
            'editPelangganForm.kode_agen' => 'nullable|string|max:40|unique:pelanggan,kode_agen,'.$this->editPelangganId,
            'editPelangganForm.referral_kode' => 'nullable|string|max:40',
        ]);

        $pelanggan = Pelanggan::findOrFail($this->editPelangganId);
        app(PelangganService::class)->update($pelanggan, $this->editPelangganForm);

        $this->showEditPelangganModal = false;
        $this->editPelangganId = null;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Data pelanggan berhasil diperbarui']);
    }

    public function hapusPelanggan(int $id): void
    {
        $user = auth()->user();
        if (! isSuperAdminOrOwner($user)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Hanya Super Admin / Owner yang berhak menghapus data pelanggan.']);

            return;
        }

        $pelanggan = Pelanggan::find($id);
        if (! $pelanggan) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Pelanggan tidak ditemukan.']);

            return;
        }

        $nama = $pelanggan->nama;

        try {
            app(PelangganService::class)->delete($pelanggan);
        } catch (\DomainException $e) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);

            return;
        }

        if ($this->selectedCustomerId === $id) {
            $this->selectedCustomerId = null;
        }
        $this->dispatch('alert', ['type' => 'success', 'message' => "Pelanggan '{$nama}' berhasil dihapus."]);
    }

    public function openResetPasswordModal(int $pelangganId): void
    {
        $pelanggan = Pelanggan::findOrFail($pelangganId);
        $this->resetPasswordPelangganId = $pelanggan->id;
        $this->resetPasswordForm = [
            'nama' => $pelanggan->nama,
            'telepon' => $pelanggan->telepon ?? '-',
            'username' => $pelanggan->username ?? '',
            'new_password' => Str::random(8),
            'alasan' => 'Konsumen menghubungi CS via WhatsApp / datang langsung ke toko.',
        ];
        $this->showResetPasswordModal = true;
    }

    public function closeResetPasswordModal(): void
    {
        $this->showResetPasswordModal = false;
        $this->resetPasswordPelangganId = null;
        $this->reset(['resetPasswordForm']);
    }

    public function ajukanResetPassword(ApprovalService $approvalService): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $this->validate([
            'resetPasswordForm.new_password' => 'required|string|min:6',
            'resetPasswordForm.alasan' => 'required|string|max:255',
        ], [
            'resetPasswordForm.new_password.required' => 'Kata sandi baru wajib diisi.',
            'resetPasswordForm.new_password.min' => 'Kata sandi baru minimal 6 karakter.',
            'resetPasswordForm.alasan.required' => 'Alasan reset kata sandi wajib diisi.',
        ]);

        $pelanggan = Pelanggan::findOrFail($this->resetPasswordPelangganId);

        // Cegah permohonan ganda bila sudah ada approval pending
        if ($approvalService->adaPending('reset_password_pelanggan', $pelanggan->id)) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => "Permohonan reset password untuk {$pelanggan->nama} sedang dalam antrean approval.",
            ]);
            $this->closeResetPasswordModal();

            return;
        }

        $cabangId = session('cabang_id');

        $payload = [
            'nama' => $pelanggan->nama,
            'telepon' => $pelanggan->telepon,
            'username' => $pelanggan->username,
            'new_password' => $this->resetPasswordForm['new_password'],
            'alasan' => $this->resetPasswordForm['alasan'],
            'requested_by_name' => $user->name,
            'amount' => 0,
        ];

        $approvalService->ajukan(
            'reset_password_pelanggan',
            $pelanggan->id,
            $cabangId,
            $payload,
            $user->id
        );

        Cache::forget('backoffice-approval-badge-'.($cabangId ?? 'all'));
        Cache::forget('backoffice-approval-badge-all');

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => "Permohonan reset kata sandi untuk '{$pelanggan->nama}' berhasil diajukan ke Approval Inbox.",
        ]);

        $this->closeResetPasswordModal();
    }

    public function getTiersProperty()
    {
        return TierMembership::withCount('pelanggan')->orderBy('urutan')->get();
    }

    public function getCustomersProperty()
    {
        $query = Pelanggan::with('tierMembership')->latest();
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('telepon', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
                    ->orWhere('kode_agen', 'like', "%{$this->search}%")
                    ->orWhere('referral_kode', 'like', "%{$this->search}%");
            });
        }
        if ($this->filterTierId) {
            $query->where('tier_membership_id', $this->filterTierId);
        }
        if ($this->filterTipeKonsumen) {
            $query->where('tipe_konsumen', $this->filterTipeKonsumen);
        } elseif ($this->filterReseller) {
            $query->where('is_reseller', true);
        }

        return $query->paginate(15);
    }

    // ===== DETAIL 360 =====
    public function getSelectedCustomerProperty(): ?array
    {
        if (! $this->selectedCustomerId) {
            return null;
        }

        $pelanggan = Pelanggan::with('tierMembership')->find($this->selectedCustomerId);
        if (! $pelanggan) {
            return null;
        }

        $transaksi = Transaksi::with('items.produk')
            ->where('pelanggan_id', $pelanggan->id)
            ->latest()->take(8)->get();

        $servis = TiketServis::with('garansi')
            ->where('pelanggan_id', $pelanggan->id)
            ->latest()->take(8)->get();

        $komisi = $pelanggan->is_reseller
            ? $pelanggan->komisi()->selectRaw('status, sum(nominal_komisi) as total')->groupBy('status')->pluck('total', 'status')
            : collect();

        // Progress tier
        $belanja = (float) $pelanggan->total_belanja_12bulan;
        $tiers = TierMembership::where('is_active', true)->orderBy('min_belanja_12bulan')->get();
        $next = $tiers->first(fn ($t) => (float) $t->min_belanja_12bulan > $belanja);
        $progress = 100;
        $sisa = 0;
        if ($next) {
            $prev = (float) ($pelanggan->tierMembership->min_belanja_12bulan ?? 0);
            $range = max(1, (float) $next->min_belanja_12bulan - $prev);
            $progress = round((($belanja - $prev) / $range) * 100, 1);
            $sisa = max(0, (float) $next->min_belanja_12bulan - $belanja);
        }

        // Data Jaringan Downline & Upline jika Agen atau memakai Referral
        $downlines = collect();
        $downlineCount = 0;
        $downlineOmzet = 0.0;
        if ($pelanggan->kode_agen) {
            $downlineQuery = Pelanggan::where('referral_kode', $pelanggan->kode_agen);
            $downlineCount = $downlineQuery->count();
            $downlineOmzet = (float) $downlineQuery->sum('total_belanja_12bulan');
            $downlines = $downlineQuery->take(5)->get();
        }

        $uplineAgen = null;
        if ($pelanggan->referral_kode) {
            $uplineAgen = Pelanggan::where('kode_agen', $pelanggan->referral_kode)->first();
        }

        $poinLogs = $pelanggan->poinLogs()->take(10)->get();

        return [
            'pelanggan' => $pelanggan,
            'transaksi' => $transaksi,
            'servis' => $servis,
            'komisi' => $komisi,
            'tier_berikutnya' => $next?->nama,
            'progress_tier' => min(100, max(0, $progress)),
            'sisa_belanja' => $sisa,
            'downlines' => $downlines,
            'downline_count' => $downlineCount,
            'downline_omzet' => $downlineOmzet,
            'upline_agen' => $uplineAgen,
            'poin_logs' => $poinLogs,
        ];
    }

    // ===== TIER CRUD =====
    public function openTierModal(?int $id = null)
    {
        if (! $this->boleh('tier.manage', 'Anda tidak memiliki izin mengelola tier.')) {
            return;
        }

        if ($id) {
            $tier = TierMembership::findOrFail($id);
            $this->tierForm = $tier->toArray();
        } else {
            $this->tierForm = [
                'id' => null, 'nama' => '', 'kode' => '', 'min_belanja_12bulan' => 0,
                'diskon_persen' => 0, 'poin_multiplier' => 1.0, 'poin_redeem_rupiah' => null, 'urutan' => 0, 'is_active' => true,
            ];
        }
        $this->showTierModal = true;
    }

    public function simpanTier()
    {
        if (! $this->boleh('tier.manage', 'Anda tidak memiliki izin mengelola tier.')) {
            return;
        }

        $this->tierForm['min_belanja_12bulan'] = $this->parseNominal($this->tierForm['min_belanja_12bulan'] ?? 0);
        $this->tierForm['poin_redeem_rupiah'] = (! empty($this->tierForm['poin_redeem_rupiah']) && $this->tierForm['poin_redeem_rupiah'] > 0)
            ? (float) $this->parseNominal($this->tierForm['poin_redeem_rupiah'])
            : null;

        $tierId = $this->tierForm['id'] ?? null;

        $this->validate([
            'tierForm.nama' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tier_memberships', 'nama')->ignore($tierId),
            ],
            'tierForm.kode' => [
                'required',
                'string',
                'max:50',
                Rule::unique('tier_memberships', 'kode')->ignore($tierId),
            ],
            'tierForm.min_belanja_12bulan' => 'required|numeric|min:0',
            'tierForm.diskon_persen' => 'required|numeric|min:0|max:100',
            'tierForm.poin_multiplier' => 'required|numeric|min:0',
            'tierForm.poin_redeem_rupiah' => 'nullable|numeric|min:0',
        ]);

        if ($this->tierForm['id']) {
            $tier = TierMembership::findOrFail($this->tierForm['id']);
            $tier->update($this->tierForm);
            $kode = strtolower($tier->kode);
            app(KonfigurasiService::class)->set("diskon_{$kode}", (float) $tier->diskon_persen, "Diskon tier {$tier->nama}");
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Tier diperbarui']);
        } else {
            $tier = TierMembership::create($this->tierForm);
            $kode = strtolower($tier->kode);
            app(KonfigurasiService::class)->set("diskon_{$kode}", (float) $tier->diskon_persen, "Diskon tier {$tier->nama}");
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Tier baru dibuat']);
        }

        $this->showTierModal = false;
    }

    public function tambahTierQty(): void
    {
        $lastQty = count($this->hargaPartaiTiersQty) > 0 ? (int) end($this->hargaPartaiTiersQty)['min_qty'] : 5;
        $lastDiskon = count($this->hargaPartaiTiersQty) > 0 ? (float) end($this->hargaPartaiTiersQty)['diskon_persen'] : 3.0;
        $this->hargaPartaiTiersQty[] = [
            'min_qty' => $lastQty + 5,
            'diskon_persen' => $lastDiskon + 2.0,
        ];
    }

    public function hapusTierQty(int $index): void
    {
        unset($this->hargaPartaiTiersQty[$index]);
        $this->hargaPartaiTiersQty = array_values($this->hargaPartaiTiersQty);
    }

    public function tambahTierNominal(): void
    {
        $lastNominal = count($this->hargaPartaiTiersNominal) > 0 ? (float) end($this->hargaPartaiTiersNominal)['min_nominal'] : 1000000.0;
        $lastDiskon = count($this->hargaPartaiTiersNominal) > 0 ? (float) end($this->hargaPartaiTiersNominal)['diskon_persen'] : 2.0;
        $this->hargaPartaiTiersNominal[] = [
            'min_nominal' => $lastNominal + 1000000.0,
            'diskon_persen' => $lastDiskon + 1.0,
        ];
    }

    public function hapusTierNominal(int $index): void
    {
        unset($this->hargaPartaiTiersNominal[$index]);
        $this->hargaPartaiTiersNominal = array_values($this->hargaPartaiTiersNominal);
    }

    public function simpanPengaturanLoyaltyGlobal(): void
    {
        if (! $this->boleh('tier.manage', 'Anda tidak memiliki izin mengelola konfigurasi loyalty.')) {
            return;
        }

        $rules = [
            'poinEarnPersenGlobal' => 'required|numeric|min:0|max:100',
            'poinRedeemRupiahGlobal' => 'required|numeric|min:0',
            'resellerDefaultPersen' => 'required|numeric|min:0|max:100',
            'agenDefaultPersen' => 'required|numeric|min:0|max:100',
            'mitraDapatPoin' => 'boolean',
            'marginGuardBufferPersen' => 'required|numeric|min:0|max:50',
            'paymentGatewayFeePersen' => 'required|numeric|min:0|max:20',
            'poinMaksPotonganPersen' => 'required|numeric|min:1|max:100',
            'komisiAgenDefaultPersen' => 'required|numeric|min:0|max:50',
        ];

        if ($this->hargaPartaiAktif) {
            $rules['hargaPartaiBerlakuUntuk'] = 'required|in:semua,member,umum';

            if (! $this->hargaPartaiOpsiQty && ! $this->hargaPartaiOpsiNominal) {
                $this->dispatch('alert', [
                    'type' => 'error',
                    'message' => 'Pilih setidaknya salah satu metode harga partai (Qty atau Nominal) saat status diaktifkan.',
                ]);

                return;
            }

            if ($this->hargaPartaiOpsiQty) {
                $rules['hargaPartaiMinQty'] = 'required|integer|min:1';
                $rules['hargaPartaiQtyDiskon'] = 'required|numeric|gt:0|max:100';
            }

            if ($this->hargaPartaiOpsiNominal) {
                $rules['hargaPartaiMinNominal'] = 'required|numeric|gt:0';
                $rules['hargaPartaiNominalDiskon'] = 'required|numeric|gt:0|max:100';
            }
        }

        $this->validate($rules);

        $config = app(KonfigurasiService::class);
        $config->set('poin_earn_persen', (float) $this->poinEarnPersenGlobal, 'Rasio earn poin dari nominal transaksi (%)');
        $config->set('poin_redeem_rupiah', (float) $this->poinRedeemRupiahGlobal, 'Nilai tukar 1 poin loyalitas menjadi rupiah (diskon)');
        $config->set('reseller_default_persen', (float) $this->resellerDefaultPersen, 'Default diskon % harga reseller jika master produk kosong');
        $config->set('agen_default_persen', (float) $this->agenDefaultPersen, 'Default diskon % harga agen jika master produk kosong');
        $config->set('mitra_dapat_poin', (bool) $this->mitraDapatPoin, 'Apakah reseller & agen berhak mendapatkan poin loyalty konsumen');
        $config->set('harga_partai_aktif', (bool) $this->hargaPartaiAktif, 'Status aktif diskon harga partai member');
        $config->set('harga_partai_berlaku_untuk', $this->hargaPartaiBerlakuUntuk, 'Cakupan pembeli harga partai (semua / member)');

        $finalMinQty = ($this->hargaPartaiAktif && $this->hargaPartaiOpsiQty) ? max(1, (int) $this->hargaPartaiMinQty) : 0;
        $finalQtyDiskon = ($this->hargaPartaiAktif && $this->hargaPartaiOpsiQty) ? (float) $this->hargaPartaiQtyDiskon : 0.0;

        $finalMinNominal = ($this->hargaPartaiAktif && $this->hargaPartaiOpsiNominal) ? max(0.0, (float) $this->hargaPartaiMinNominal) : 0.0;
        $finalNominalDiskon = ($this->hargaPartaiAktif && $this->hargaPartaiOpsiNominal) ? (float) $this->hargaPartaiNominalDiskon : 0.0;

        // Sinkronkan tier pertama dengan form utama jika tier tunggal
        if (count($this->hargaPartaiTiersQty) <= 1 && $finalMinQty > 0 && $finalQtyDiskon > 0) {
            $this->hargaPartaiTiersQty = [['min_qty' => $finalMinQty, 'diskon_persen' => $finalQtyDiskon]];
        }
        if (count($this->hargaPartaiTiersNominal) <= 1 && $finalMinNominal > 0 && $finalNominalDiskon > 0) {
            $this->hargaPartaiTiersNominal = [['min_nominal' => $finalMinNominal, 'diskon_persen' => $finalNominalDiskon]];
        }

        // Bersihkan dan susun array multi-tier
        $cleanedTiersQty = [];
        if ($this->hargaPartaiAktif && $this->hargaPartaiOpsiQty) {
            foreach ($this->hargaPartaiTiersQty as $t) {
                $q = (int) ($t['min_qty'] ?? 0);
                $d = (float) ($t['diskon_persen'] ?? 0);
                if ($q > 0 && $d > 0) {
                    $cleanedTiersQty[] = ['min_qty' => $q, 'diskon_persen' => min(50.0, $d)];
                }
            }
            if (empty($cleanedTiersQty) && $finalMinQty > 0 && $finalQtyDiskon > 0) {
                $cleanedTiersQty[] = ['min_qty' => $finalMinQty, 'diskon_persen' => $finalQtyDiskon];
            }
            if (! empty($cleanedTiersQty)) {
                $finalMinQty = $cleanedTiersQty[0]['min_qty'];
                $finalQtyDiskon = $cleanedTiersQty[0]['diskon_persen'];
            }
        }

        $cleanedTiersNominal = [];
        if ($this->hargaPartaiAktif && $this->hargaPartaiOpsiNominal) {
            foreach ($this->hargaPartaiTiersNominal as $t) {
                $nom = (float) ($t['min_nominal'] ?? 0);
                $d = (float) ($t['diskon_persen'] ?? 0);
                if ($nom > 0 && $d > 0) {
                    $cleanedTiersNominal[] = ['min_nominal' => $nom, 'diskon_persen' => min(50.0, $d)];
                }
            }
            if (empty($cleanedTiersNominal) && $finalMinNominal > 0 && $finalNominalDiskon > 0) {
                $cleanedTiersNominal[] = ['min_nominal' => $finalMinNominal, 'diskon_persen' => $finalNominalDiskon];
            }
            if (! empty($cleanedTiersNominal)) {
                $finalMinNominal = $cleanedTiersNominal[0]['min_nominal'];
                $finalNominalDiskon = $cleanedTiersNominal[0]['diskon_persen'];
            }
        }

        $config->set('harga_partai_tiers_qty', json_encode($cleanedTiersQty), 'Daftar multi-tier diskon kuantitas partai');
        $config->set('harga_partai_tiers_nominal', json_encode($cleanedTiersNominal), 'Daftar multi-tier diskon nominal partai');

        $config->set('harga_partai_min_qty', $finalMinQty, 'Minimal kuantitas barang untuk mendapatkan harga partai');
        $config->set('harga_partai_qty_diskon_persen', $finalQtyDiskon, 'Persentase diskon harga partai berbasis qty');
        $config->set('harga_partai_min_nominal', $finalMinNominal, 'Minimal belanja untuk mendapatkan diskon partai nominal');
        $config->set('harga_partai_nominal_diskon_persen', $finalNominalDiskon, 'Persentase diskon harga partai berbasis nominal');

        // Parameter Potongan SSOT Margin Guard
        $config->set('margin_guard_buffer_persen', (float) $this->marginGuardBufferPersen, 'Buffer margin error, packaging, & operasional (%)');
        $config->set('payment_gateway_fee_persen', (float) $this->paymentGatewayFeePersen, 'Biaya transaksi / MDR payment gateway toko (%)');
        $config->set('poin_maks_potongan_persen', (float) $this->poinMaksPotonganPersen, 'Batas maksimal subtotal yang boleh dipotong poin per transaksi (%)');
        $config->set('komisi_agen_default_persen', (float) $this->komisiAgenDefaultPersen, 'Estimasi default komisi penjualan agen (%)');

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Pengaturan SSOT potongan, loyalty, dan proteksi margin berhasil disimpan',
        ]);
    }

    public function hapusTier(int $id): void
    {
        $user = auth()->user();
        if (! isSuperAdminOrOwner($user) && ! ($user && $user->can('tier.manage'))) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Hanya Super Admin / Owner yang berhak menghapus tier membership.']);

            return;
        }

        $tier = TierMembership::find($id);
        if (! $tier) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Tier tidak ditemukan.']);

            return;
        }

        $countPelanggan = Pelanggan::where('tier_membership_id', $id)->count();
        if ($countPelanggan > 0) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => "Tier '{$tier->nama}' sedang digunakan oleh {$countPelanggan} pelanggan. Pindahkan pelanggan ke tier lain terlebih dahulu.",
            ]);

            return;
        }

        $nama = $tier->nama;
        $tier->delete();
        $this->showTierModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => "Tier '{$nama}' berhasil dihapus."]);
    }

    public function recalcTier()
    {
        if (! $this->boleh('tier.manage', 'Anda tidak memiliki izin rekalkulasi tier.')) {
            return;
        }

        $updated = app(TierService::class)->recalcSemua();
        $this->dispatch('alert', ['type' => 'success', 'message' => "Rekalkulasi tier: {$updated} pelanggan diperbarui"]);
    }

    public function resetTierKeDefault()
    {
        if (! $this->boleh('tier.manage', 'Anda tidak memiliki izin mereset tier membership.')) {
            return;
        }

        (new TierMembershipSeeder)->run();
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Setelan tier membership berhasil dikembalikan ke standar default sistem (Silver, Gold, Platinum)']);
    }

    // ===== BROADCAST =====
    public function kirimBroadcast()
    {
        abort_unless(auth()->user()?->can('crm.broadcast'), 403, 'Anda tidak memiliki izin untuk broadcast.');

        $this->validate([
            'broadcastJudul' => 'required|string|max:255',
            'broadcastPesan' => 'required|string',
            'broadcastChannel' => 'required|in:inapp,wa,email',
        ]);

        $segment = [];
        if ($this->broadcastSegmentType === 'tier' && $this->broadcastTierId) {
            $segment[] = ['tipe' => 'tier', 'nilai' => $this->broadcastTierId];
        } elseif ($this->broadcastSegmentType === 'reseller') {
            $segment[] = ['tipe' => 'reseller', 'nilai' => true];
        } elseif ($this->broadcastSegmentType === 'belum_belanja') {
            $segment[] = ['tipe' => 'belum_belanja_hari', 'nilai' => max(1, (int) $this->broadcastHariBelanja)];
        } elseif ($this->broadcastSegmentType === 'birthday') {
            $segment[] = ['tipe' => 'birthday_month', 'nilai' => max(1, min(12, (int) $this->broadcastBulanLahir))];
        } else {
            if ($this->broadcastTierId) {
                $segment[] = ['tipe' => 'tier', 'nilai' => $this->broadcastTierId];
            }
            if ($this->broadcastReseller) {
                $segment[] = ['tipe' => 'reseller', 'nilai' => true];
            }
        }

        $kampanye = KampanyeBroadcast::create([
            'judul' => $this->broadcastJudul,
            'pesan' => "[Broadcast] {$this->broadcastPesan}",
            'channel' => $this->broadcastChannel ?: 'inapp',
            'segment' => $segment,
            'status' => 'draft',
            'user_id' => auth()->id(),
        ]);

        $kampanye = app(BroadcastService::class)->kirimSekarang($kampanye);

        $this->showBroadcastModal = false;
        $this->broadcastJudul = '';
        $this->broadcastPesan = '';
        $this->dispatch('alert', [
            'type' => 'success',
            'message' => "Broadcast dikirim ke {$kampanye->total_target} pelanggan via antrian",
        ]);
    }

    protected function boleh(string $permission, string $pesan = 'Anda tidak memiliki hak akses untuk tindakan ini.'): bool
    {
        $user = auth()->user();
        if (isSuperAdminOrOwner($user) || $user?->can($permission)) {
            return true;
        }

        $this->dispatch('alert', ['type' => 'error', 'message' => $pesan]);

        return false;
    }

    public function render()
    {
        return view('modules.crm.livewire.crm-dashboard', [
            'tiers' => $this->tiers,
            'customers' => $this->customers,
            'selectedCustomer' => $this->selectedCustomer,
            'totalPelanggan' => Pelanggan::count(),
            'riwayatKampanye' => KampanyeBroadcast::with('user')->latest()->take(15)->get(),
            'activeTab' => $this->activeTab,
            'ringkasanPotongan' => app(KonfigurasiService::class)->getRingkasanPotonganSistem(),
        ])->layout('layouts.backoffice', ['header' => 'CRM & Membership']);
    }
}
