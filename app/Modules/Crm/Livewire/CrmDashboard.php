<?php

namespace App\Modules\Crm\Livewire;

use App\Modules\Crm\Models\KampanyeBroadcast;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Crm\Services\BroadcastService;
use App\Modules\Crm\Services\PelangganService;
use App\Modules\Crm\Services\TierService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Servis\Models\TiketServis;
use App\Traits\ParsesNominal;
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

    public string $activeTab = 'pelanggan'; // 'pelanggan' | 'tier'

    protected $queryString = [
        'search' => ['except' => ''],
        'filterTierId' => ['except' => null],
        'filterReseller' => ['except' => false],
        'filterTipeKonsumen' => ['except' => ''],
        'activeTab' => ['except' => 'pelanggan'],
    ];

    public function switchTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['pelanggan', 'tier']) ? $tab : 'pelanggan';
    }

    // Detail customer modal
    public ?int $selectedCustomerId = null;

    // Tier modal
    public bool $showTierModal = false;

    public array $tierForm = [
        'id' => null, 'nama' => '', 'kode' => '', 'min_belanja_12bulan' => 0,
        'diskon_persen' => 0, 'poin_multiplier' => 1.0, 'urutan' => 0, 'is_active' => true,
    ];

    // Broadcast modal
    public bool $showBroadcastModal = false;

    public string $broadcastJudul = '';

    public string $broadcastPesan = '';

    public string $broadcastChannel = 'inapp';

    public ?int $broadcastTierId = null;

    public bool $broadcastReseller = false;

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

    public function simpanPelangganBaruCrm()
    {
        if (! $this->boleh('crm.create', 'Anda tidak memiliki izin menambah pelanggan.')) {
            return;
        }

        $this->validate([
            'pelangganBaruForm.nama' => 'required|string|max:255',
            'pelangganBaruForm.telepon' => 'required|string|max:20|unique:pelanggan,telepon',
            'pelangganBaruForm.email' => 'nullable|email|unique:pelanggan,email',
            'pelangganBaruForm.kode_agen' => 'nullable|string|max:40|unique:pelanggan,kode_agen',
            'pelangganBaruForm.referral_kode' => 'nullable|string|max:40',
        ]);

        $pelanggan = app(PelangganService::class)->create([
            'nama' => $this->pelangganBaruForm['nama'],
            'telepon' => $this->pelangganBaruForm['telepon'],
            'email' => $this->pelangganBaruForm['email'] ?: null,
            'alamat' => $this->pelangganBaruForm['alamat'] ?: null,
            'tanggal_lahir' => $this->pelangganBaruForm['tanggal_lahir'] ?: null,
            'is_reseller' => $this->pelangganBaruForm['is_reseller'] || $this->pelangganBaruForm['tipe_konsumen'] === 'agen' || $this->pelangganBaruForm['tipe_konsumen'] === 'reseller',
            'tipe_konsumen' => $this->pelangganBaruForm['tipe_konsumen'] ?: 'retail',
            'kode_agen' => $this->pelangganBaruForm['kode_agen'] ?: null,
            'referral_kode' => $this->pelangganBaruForm['referral_kode'] ?: null,
        ]);

        $this->showPelangganBaruModal = false;
        $this->pelangganBaruForm = [
            'nama' => '', 'telepon' => '', 'email' => '', 'alamat' => '',
            'tanggal_lahir' => '', 'is_reseller' => false, 'tipe_konsumen' => 'retail',
            'kode_agen' => '', 'referral_kode' => '',
        ];
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Pelanggan baru disimpan (tersedia di POS & Servis)']);
    }

    public function openEditPelangganModal(int $id): void
    {
        $user = auth()->user();
        if (! isSuperAdminOrOwner($user) && ! ($user && ($user->hasRole('admin-toko') || $user->hasRole('marketing') || $user->can('crm.create')))) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Hanya Super Admin, Owner, Admin Toko, atau Marketing yang berhak mengedit data pelanggan.']);

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

    public function simpanEditPelanggan(): void
    {
        $user = auth()->user();
        if (! isSuperAdminOrOwner($user) && ! ($user && ($user->hasRole('admin-toko') || $user->hasRole('marketing') || $user->can('crm.create')))) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Hanya Super Admin, Owner, Admin Toko, atau Marketing yang berhak mengedit data pelanggan.']);

            return;
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
                'diskon_persen' => 0, 'poin_multiplier' => 1.0, 'urutan' => 0, 'is_active' => true,
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

        $this->validate([
            'tierForm.nama' => 'required|string|max:255',
            'tierForm.kode' => 'required|string|max:50',
            'tierForm.min_belanja_12bulan' => 'required|numeric|min:0',
            'tierForm.diskon_persen' => 'required|numeric|min:0|max:100',
            'tierForm.poin_multiplier' => 'required|numeric|min:0',
        ]);

        if ($this->tierForm['id']) {
            TierMembership::findOrFail($this->tierForm['id'])->update($this->tierForm);
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Tier diperbarui']);
        } else {
            TierMembership::create($this->tierForm);
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Tier baru dibuat']);
        }

        $this->showTierModal = false;
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
        if ($this->broadcastTierId) {
            $segment[] = ['tipe' => 'tier', 'nilai' => $this->broadcastTierId];
        }
        if ($this->broadcastReseller) {
            $segment[] = ['tipe' => 'reseller'];
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
        $this->dispatch('alert', [
            'type' => 'success',
            'message' => "Broadcast dikirim ke {$kampanye->total_target} pelanggan via antrian",
        ]);
    }

    protected function boleh(string $permission, string $pesan = 'Anda tidak memiliki hak akses untuk tindakan ini.'): bool
    {
        if (auth()->user()?->can($permission)) {
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
            'activeTab' => $this->activeTab,
        ])->layout('layouts.backoffice', ['header' => 'CRM & Membership']);
    }
}
