<?php

namespace App\Modules\Rbac\Livewire;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Services\PajakService;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Services\AuditService;
use App\Modules\Reseller\Models\SkemaKomisi;
use App\Modules\Servis\Models\JenisServis;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\TipeHp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Pengaturan & RBAC (PRD Frontend §5.9, §5.11):
 * Manajemen user + role + cabang, master data (cabang/gudang/jenis servis/COA).
 */
class SettingsRbac extends Component
{
    use WithPagination;

    public string $activeTab = 'users'; // users, role, cabang, master, loyalitas, pajak

    // [T-22] Strategi Loyalitas (editable tanpa deploy, non-retroaktif)
    public array $loyalitasForm = [
        'poin_earn_persen' => 5,
        'poin_redeem_rupiah' => 100,
        'diskon_silver' => 3,
        'diskon_gold' => 5,
        'diskon_platinum' => 10,
    ];

    public array $skemaKomisiForm = [];

    // Opsi Konfigurasi Pajak (PPN, PPh, PB1 / Tanpa Pajak)
    public array $pajakForm = [
        'target' => 'global',
        'enabled' => false,
        'nama' => 'PPN',
        'percent' => 11.0,
        'mode' => 'exclusive',
    ];

    public function mount(): void
    {
        $config = app(KonfigurasiService::class);

        // Nilai TERSIMPAN dari tabel konfigurasi, fallback default bila kosong
        foreach (array_keys($this->loyalitasForm) as $kunci) {
            $this->loyalitasForm[$kunci] = (float) ($config->get($kunci) ?? $config->defaults()[$kunci]);
        }

        // Skema komisi default (tabel skema_komisi)
        $this->skemaKomisiForm = SkemaKomisi::orderBy('id')
            ->get(['id', 'nama', 'kategori', 'tipe', 'nilai', 'is_active'])
            ->toArray();

        $this->loadPajakConfig();
    }

    public function updatedPajakFormTarget(): void
    {
        $this->loadPajakConfig();
    }

    public function loadPajakConfig(): void
    {
        $pajakService = app(PajakService::class);
        $cabangId = $this->pajakForm['target'] === 'global' ? null : (int) $this->pajakForm['target'];
        $cfg = $pajakService->getConfig($cabangId);

        $this->pajakForm['enabled'] = (bool) $cfg['enabled'];
        $this->pajakForm['nama'] = (string) $cfg['nama'];
        $this->pajakForm['percent'] = (float) $cfg['percent'];
        $this->pajakForm['mode'] = (string) $cfg['mode'];
    }

    public function simpanPajak(): void
    {
        if (! $this->boleh('pengaturan.manage', 'Anda tidak memiliki izin mengubah konfigurasi pajak.')) {
            return;
        }

        $this->validate([
            'pajakForm.enabled' => 'required|boolean',
            'pajakForm.nama' => 'required|string|max:50',
            'pajakForm.percent' => 'required|numeric|min:0|max:100',
            'pajakForm.mode' => 'required|in:exclusive,inclusive',
        ]);

        $cabangId = $this->pajakForm['target'] === 'global' ? null : (int) $this->pajakForm['target'];
        $pajakService = app(PajakService::class);

        $pajakService->saveConfig($cabangId, [
            'enabled' => (bool) $this->pajakForm['enabled'],
            'nama' => $this->pajakForm['nama'],
            'percent' => (float) $this->pajakForm['percent'],
            'mode' => $this->pajakForm['mode'],
        ]);

        $targetText = $cabangId ? "Cabang #{$cabangId}" : 'Global (Semua Cabang)';
        $statusText = $this->pajakForm['enabled']
            ? "Aktif ({$this->pajakForm['nama']} {$this->pajakForm['percent']}%, {$this->pajakForm['mode']})"
            : 'Nonaktif (Tanpa Pajak)';

        app(AuditService::class)->catat('Konfigurasi', 'update', null, "Konfigurasi Pajak {$targetText} diubah: {$statusText}");

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => "Pengaturan pajak {$targetText} berhasil disimpan ({$statusText})",
        ]);
    }

    /** [T-22] Simpan strategi loyalitas → tabel konfigurasi + diskon tier + skema komisi default. Non-retroaktif: hanya berlaku utk transaksi baru. */
    public function simpanLoyalitas()
    {
        if (! $this->boleh('pengaturan.manage', 'Anda tidak memiliki izin mengubah konfigurasi loyalitas.')) {
            return;
        }

        $this->validate([
            'loyalitasForm.poin_earn_persen' => 'required|numeric|min:0|max:100',
            'loyalitasForm.poin_redeem_rupiah' => 'required|numeric|min:0',
            'loyalitasForm.diskon_silver' => 'required|numeric|min:0|max:100',
            'loyalitasForm.diskon_gold' => 'required|numeric|min:0|max:100',
            'loyalitasForm.diskon_platinum' => 'required|numeric|min:0|max:100',
            'skemaKomisiForm.*.tipe' => 'required|in:persen,nominal',
            'skemaKomisiForm.*.nilai' => 'required|numeric|min:0',
        ]);

        $config = app(KonfigurasiService::class);
        foreach ($this->loyalitasForm as $kunci => $nilai) {
            $config->set($kunci, (float) $nilai, 'Strategi loyalitas');
        }

        // Terapkan diskon tier (sama dengan PUT /api/crm/config)
        foreach (['silver', 'gold', 'platinum'] as $kode) {
            TierMembership::where('kode', $kode)
                ->update(['diskon_persen' => (float) $this->loyalitasForm["diskon_{$kode}"]]);
        }

        // Skema komisi default
        foreach ($this->skemaKomisiForm as $row) {
            SkemaKomisi::findOrFail($row['id'])->update([
                'tipe' => $row['tipe'],
                'nilai' => (float) $row['nilai'],
            ]);
        }

        app(AuditService::class)->catat('Konfigurasi', 'update', null, 'Strategi loyalitas diperbarui (poin/diskon/skema komisi) — non-retroaktif');

        $this->dispatch('alert', ['type' => 'success', 'message' => 'Strategi loyalitas tersimpan (berlaku utk transaksi baru)']);
    }

    // User CRUD
    public bool $showUserModal = false;

    public array $userForm = [
        'id' => null, 'name' => '', 'email' => '', 'password' => '', 'phone' => '',
        'role' => '', 'cabang_ids' => [], 'cabang_default_id' => null, 'is_active' => true,
    ];

    // Cabang CRUD
    public bool $showCabangModal = false;

    public array $cabangForm = ['id' => null, 'nama' => '', 'kode' => '', 'alamat' => '', 'telepon' => '', 'is_active' => true];

    // Gudang CRUD
    public bool $showGudangModal = false;

    public array $gudangForm = ['id' => null, 'cabang_id' => null, 'nama' => '', 'kode' => '', 'is_active' => true];

    // Master Produk & Katalog Sub-Tab & Modals
    public string $masterProdukSubTab = 'kategori'; // kategori, brand, kualitas, satuan, kondisi, tipe_hp

    public bool $showKategoriModal = false;

    public array $kategoriForm = ['id' => null, 'nama' => '', 'parent_id' => null, 'icon' => null, 'urutan' => 0, 'is_active' => true];

    public bool $showBrandModal = false;

    public array $brandForm = ['id' => null, 'nama' => '', 'keterangan' => '', 'is_active' => true];

    public bool $showKualitasModal = false;

    public array $kualitasForm = ['id' => null, 'nama' => '', 'keterangan' => '', 'is_active' => true];

    public bool $showSatuanModal = false;

    public array $satuanForm = ['id' => null, 'kode' => '', 'nama' => '', 'is_active' => true];

    public bool $showKondisiModal = false;

    public array $kondisiForm = ['kode' => '', 'nama' => '', 'is_active' => true, 'is_edit' => false];

    public bool $showTipeHpModal = false;

    public array $tipeHpForm = ['id' => null, 'merk' => '', 'model' => '', 'nama' => '', 'is_active' => true];

    // [T-16] JenisServis CRUD editable
    public bool $showJenisServisModal = false;

    public array $jenisServisForm = [
        'id' => null, 'nama' => '', 'kode' => '', 'kategori' => 'hardware',
        'estimasi_durasi' => 120, 'durasi_garansi_hari' => 30, 'butuh_part' => true,
        'is_part_original' => false, 'is_active' => true,
    ];

    // [T-25] RBAC fleksibel: role baru + permission matrix per role
    public bool $showRoleModal = false;

    public string $roleBaruNama = '';

    public array $roleBaruPermissions = [];

    public ?int $editRoleId = null;

    public array $editRolePermissions = [];

    public function getPermissionsListProperty()
    {
        return Permission::orderBy('name')->get();
    }

    // Role: semua role + permission (recompute)
    public function getRolesFullProperty()
    {
        return Role::with('permissions')->orderBy('name')->get();
    }

    public function openRoleModal()
    {
        $this->roleBaruNama = '';
        $this->roleBaruPermissions = [];
        $this->showRoleModal = true;
    }

    public function saveRoleBaru()
    {
        if (! $this->boleh('user.create', 'Anda tidak memiliki izin membuat role baru.')) {
            return;
        }

        $this->validate(['roleBaruNama' => 'required|string|max:255|unique:roles,name']);

        $role = Role::create(['name' => $this->roleBaruNama]);
        if (! empty($this->roleBaruPermissions)) {
            $role->syncPermissions($this->roleBaruPermissions);
        }

        app(AuditService::class)->catat('Role', 'create', $role->id, "Role {$role->name} dibuat");

        $this->showRoleModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Role baru dibuat']);
    }

    public function openEditRole(int $roleId)
    {
        $role = Role::with('permissions')->findOrFail($roleId);
        $this->editRoleId = $roleId;
        $this->editRolePermissions = $role->permissions->pluck('name')->toArray();
    }

    public function saveEditRolePermissions()
    {
        if (! $this->boleh('user.edit', 'Anda tidak memiliki izin mengubah hak akses role.')) {
            return;
        }

        $role = Role::findOrFail($this->editRoleId);

        // Guardrail: super-admin tidak boleh dikosongkan (anti lockout)
        if ($role->name === 'super-admin' && empty($this->editRolePermissions)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'super-admin wajib punya minimal 1 permission']);

            return;
        }

        $role->syncPermissions($this->editRolePermissions);
        app(AuditService::class)->catat('Role', 'update', $role->id, "Permission role {$role->name} diperbarui");

        $this->editRoleId = null;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Permission role diperbarui']);
    }

    public function getRolesProperty()
    {
        return Role::all();
    }

    public function getCabangsProperty()
    {
        return Cabang::withCount('gudang')->withCount('users')->get();
    }

    public function getGudangsProperty()
    {
        return Gudang::with('cabang')->get();
    }

    public function getUsersProperty()
    {
        return User::with('roles', 'cabangs')->latest()->paginate(15);
    }

    public function getJenisServisProperty()
    {
        return JenisServis::all();
    }

    /** [T-16] open modal edit/tambah jenis servis */
    public function openJenisServisModal(?int $id = null)
    {
        if ($id) {
            $j = JenisServis::findOrFail($id);
            $this->jenisServisForm = $j->toArray();
            $this->jenisServisForm['biaya_jasa'] = (float) ($j->biaya_jasa ?? 0);
        } else {
            $this->jenisServisForm = [
                'id' => null, 'nama' => '', 'kode' => '', 'kategori' => 'hardware',
                'estimasi_durasi' => 120, 'durasi_garansi_hari' => 30, 'biaya_jasa' => 0, 'butuh_part' => true,
                'is_part_original' => false, 'is_active' => true,
            ];
        }
        $this->showJenisServisModal = true;
    }

    public function simpanJenisServis()
    {
        $this->validate([
            'jenisServisForm.nama' => 'required|string|max:255',
            'jenisServisForm.kode' => 'required|string|max:20|unique:jenis_servis,kode,'.($this->jenisServisForm['id'] ?? 'NULL'),
            'jenisServisForm.biaya_jasa' => 'nullable|numeric|min:0',
        ]);

        $data = $this->jenisServisForm;
        $data['biaya_jasa'] = (float) ($data['biaya_jasa'] ?? 0);

        if ($this->jenisServisForm['id']) {
            JenisServis::findOrFail($this->jenisServisForm['id'])->update($data);
        } else {
            JenisServis::create($data);
        }

        $this->showJenisServisModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Jenis servis disimpan']);
    }

    public function getCoaListProperty()
    {
        return AkunCOA::orderBy('kode')->get();
    }

    // ===== USER =====
    public function openUserModal(?int $id = null)
    {
        if ($id) {
            $u = User::with('roles', 'cabangs')->findOrFail($id);
            $defaultCabang = $u->cabangs->first(fn ($c) => (bool) $c->pivot->is_default);
            $this->userForm = [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'password' => '',
                'phone' => $u->phone ?? '', 'role' => $u->roles->first()?->name ?? '',
                'cabang_ids' => $u->cabangs->pluck('id')->toArray(),
                'cabang_default_id' => $defaultCabang?->id ?? $u->cabangs->first()?->id,
                'is_active' => (bool) $u->is_active,
            ];
        } else {
            $this->userForm = [
                'id' => null, 'name' => '', 'email' => '', 'password' => '', 'phone' => '',
                'role' => 'kasir', 'cabang_ids' => [], 'cabang_default_id' => null, 'is_active' => true,
            ];
        }
        $this->showUserModal = true;
    }

    public function saveUser()
    {
        $perm = ! empty($this->userForm['id']) ? 'user.edit' : 'user.create';
        if (! $this->boleh($perm, 'Anda tidak memiliki izin mengelola data pengguna.')) {
            return;
        }

        $this->validate([
            'userForm.name' => 'required|string|max:255',
            'userForm.email' => 'required|email|unique:users,email,'.($this->userForm['id'] ?? 'NULL'),
        ]);

        if (! $this->userForm['id']) {
            $this->validate(['userForm.password' => 'required|string|min:6']);

            $user = User::create([
                'name' => $this->userForm['name'],
                'email' => $this->userForm['email'],
                'phone' => $this->userForm['phone'],
                'password' => Hash::make($this->userForm['password']),
                'is_active' => $this->userForm['is_active'],
            ]);
            $aksi = 'create';
        } else {
            $user = User::findOrFail($this->userForm['id']);
            $user->update([
                'name' => $this->userForm['name'],
                'email' => $this->userForm['email'],
                'phone' => $this->userForm['phone'],
                'is_active' => $this->userForm['is_active'],
            ]);
            if ($this->userForm['password']) {
                $user->update(['password' => Hash::make($this->userForm['password'])]);
            }
            $aksi = 'update';
        }

        if ($this->userForm['role']) {
            $user->syncRoles([$this->userForm['role']]);
        }

        $cabangIds = array_map('intval', $this->userForm['cabang_ids'] ?? []);
        $defaultId = ! empty($this->userForm['cabang_default_id']) ? (int) $this->userForm['cabang_default_id'] : null;

        if (! in_array($defaultId, $cabangIds, true) && ! empty($cabangIds)) {
            $defaultId = $cabangIds[0];
        }

        $syncData = [];
        foreach ($cabangIds as $cid) {
            $syncData[$cid] = ['is_default' => ($cid === $defaultId)];
        }
        $user->cabangs()->sync($syncData);

        app(AuditService::class)->catat('User', $aksi, $user->id, "User {$user->name} dikelola ({$this->userForm['role']})");

        $this->showUserModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'User berhasil disimpan']);
    }

    public function toggleUserActive(int $id)
    {
        if (! $this->boleh('user.edit', 'Anda tidak memiliki izin mengubah status pengguna.')) {
            return;
        }

        $u = User::findOrFail($id);
        $u->update(['is_active' => ! $u->is_active]);
        app(AuditService::class)->catat('User', 'update', $u->id, "Status user {$u->name} → ".($u->is_active ? 'aktif' : 'nonaktif'));
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Status user diubah']);
    }

    // ===== CABANG =====
    public function openCabangModal(?int $id = null)
    {
        if ($id) {
            $c = Cabang::findOrFail($id);
            $this->cabangForm = $c->toArray();
        } else {
            $this->cabangForm = ['id' => null, 'nama' => '', 'kode' => '', 'alamat' => '', 'telepon' => '', 'is_active' => true];
        }
        $this->showCabangModal = true;
    }

    public function saveCabang()
    {
        if (! $this->boleh('cabang.manage', 'Anda tidak memiliki izin mengelola cabang.')) {
            return;
        }

        $this->validate([
            'cabangForm.nama' => 'required|string|max:255',
            'cabangForm.kode' => 'required|string|max:20|unique:cabang,kode,'.($this->cabangForm['id'] ?? 'NULL'),
        ]);

        if ($this->cabangForm['id']) {
            Cabang::findOrFail($this->cabangForm['id'])->update($this->cabangForm);
        } else {
            Cabang::create($this->cabangForm);
        }

        $this->showCabangModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Cabang disimpan']);
    }

    // ===== GUDANG =====
    public function openGudangModal(?int $id = null)
    {
        if ($id) {
            $g = Gudang::findOrFail($id);
            $this->gudangForm = $g->toArray();
        } else {
            $this->gudangForm = ['id' => null, 'cabang_id' => null, 'nama' => '', 'kode' => '', 'is_active' => true];
        }
        $this->showGudangModal = true;
    }

    public function saveGudang()
    {
        if (! $this->boleh('cabang.manage', 'Anda tidak memiliki izin mengelola gudang.')) {
            return;
        }

        $this->validate([
            'gudangForm.cabang_id' => 'required|exists:cabang,id',
            'gudangForm.nama' => 'required|string|max:255',
            'gudangForm.kode' => 'required|string|max:20|unique:gudang,kode,'.($this->gudangForm['id'] ?? 'NULL'),
        ]);

        if ($this->gudangForm['id']) {
            Gudang::findOrFail($this->gudangForm['id'])->update($this->gudangForm);
        } else {
            Gudang::create($this->gudangForm);
        }

        $this->showGudangModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Gudang disimpan']);
    }

    // ===== MASTER DATA PRODUK & KATALOG =====

    // 1. Kategori
    public function openKategoriModal(?int $id = null): void
    {
        if ($id) {
            $kat = KategoriProduk::findOrFail($id);
            $this->kategoriForm = [
                'id' => $kat->id,
                'nama' => $kat->nama,
                'parent_id' => $kat->parent_id,
                'icon' => $kat->icon,
                'urutan' => $kat->urutan,
                'is_active' => (bool) $kat->is_active,
            ];
        } else {
            $this->kategoriForm = [
                'id' => null,
                'nama' => '',
                'parent_id' => null,
                'icon' => null,
                'urutan' => 0,
                'is_active' => true,
            ];
        }
        $this->showKategoriModal = true;
    }

    public function simpanKategori(): void
    {
        $this->validate([
            'kategoriForm.nama' => 'required|string|max:255',
            'kategoriForm.parent_id' => 'nullable|exists:kategori_produk,id',
            'kategoriForm.urutan' => 'nullable|integer',
        ]);

        if ($this->kategoriForm['id'] && $this->kategoriForm['parent_id'] == $this->kategoriForm['id']) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Kategori tidak bisa menjadi induk untuk dirinya sendiri']);

            return;
        }

        if ($this->kategoriForm['id']) {
            $kat = KategoriProduk::findOrFail($this->kategoriForm['id']);
            $kat->update([
                'nama' => $this->kategoriForm['nama'],
                'parent_id' => $this->kategoriForm['parent_id'] ?: null,
                'icon' => $this->kategoriForm['icon'] ?: null,
                'urutan' => (int) ($this->kategoriForm['urutan'] ?? 0),
                'is_active' => (bool) $this->kategoriForm['is_active'],
            ]);
        } else {
            KategoriProduk::create([
                'nama' => $this->kategoriForm['nama'],
                'parent_id' => $this->kategoriForm['parent_id'] ?: null,
                'icon' => $this->kategoriForm['icon'] ?: null,
                'urutan' => (int) ($this->kategoriForm['urutan'] ?? 0),
                'is_active' => (bool) $this->kategoriForm['is_active'],
            ]);
        }

        $this->showKategoriModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Kategori produk disimpan']);
    }

    public function toggleKategoriStatus(int $id): void
    {
        $kat = KategoriProduk::findOrFail($id);
        $kat->update(['is_active' => ! $kat->is_active]);
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Status kategori diubah']);
    }

    public function hapusKategori(int $id): void
    {
        $kat = KategoriProduk::withCount(['produk', 'children'])->findOrFail($id);

        if ($kat->produk_count > 0 || $kat->children_count > 0) {
            $kat->update(['is_active' => false]);
            $this->dispatch('alert', [
                'type' => 'warning',
                'message' => "Kategori memiliki {$kat->produk_count} produk / {$kat->children_count} sub-kategori. Status dinonaktifkan (tidak dihapus permanen).",
            ]);

            return;
        }

        $kat->delete();
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Kategori berhasil dihapus']);
    }

    // 2. Brand
    public function openBrandModal(?int $id = null): void
    {
        if ($id) {
            $brand = Brand::findOrFail($id);
            $this->brandForm = [
                'id' => $brand->id,
                'nama' => $brand->nama,
                'keterangan' => $brand->keterangan ?? '',
                'is_active' => (bool) $brand->is_active,
            ];
        } else {
            $this->brandForm = ['id' => null, 'nama' => '', 'keterangan' => '', 'is_active' => true];
        }
        $this->showBrandModal = true;
    }

    public function simpanBrand(): void
    {
        $this->validate([
            'brandForm.nama' => 'required|string|max:255',
        ]);

        if ($this->brandForm['id']) {
            Brand::findOrFail($this->brandForm['id'])->update([
                'nama' => $this->brandForm['nama'],
                'keterangan' => $this->brandForm['keterangan'] ?: null,
                'is_active' => (bool) $this->brandForm['is_active'],
            ]);
        } else {
            Brand::create([
                'nama' => $this->brandForm['nama'],
                'keterangan' => $this->brandForm['keterangan'] ?: null,
                'is_active' => (bool) $this->brandForm['is_active'],
            ]);
        }

        $this->showBrandModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Brand disimpan']);
    }

    public function toggleBrandStatus(int $id): void
    {
        $brand = Brand::findOrFail($id);
        $brand->update(['is_active' => ! $brand->is_active]);
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Status brand diubah']);
    }

    public function hapusBrand(int $id): void
    {
        $brand = Brand::withCount('produk')->findOrFail($id);
        if ($brand->produk_count > 0) {
            $brand->update(['is_active' => false]);
            $this->dispatch('alert', [
                'type' => 'warning',
                'message' => "Brand terkait dengan {$brand->produk_count} produk. Status dinonaktifkan.",
            ]);

            return;
        }

        $brand->delete();
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Brand berhasil dihapus']);
    }

    // 3. Kualitas
    public function openKualitasModal(?int $id = null): void
    {
        if ($id) {
            $k = KualitasProduk::findOrFail($id);
            $this->kualitasForm = [
                'id' => $k->id,
                'nama' => $k->nama,
                'keterangan' => $k->keterangan ?? '',
                'is_active' => (bool) $k->is_active,
            ];
        } else {
            $this->kualitasForm = ['id' => null, 'nama' => '', 'keterangan' => '', 'is_active' => true];
        }
        $this->showKualitasModal = true;
    }

    public function simpanKualitas(): void
    {
        $this->validate([
            'kualitasForm.nama' => 'required|string|max:255',
        ]);

        if ($this->kualitasForm['id']) {
            KualitasProduk::findOrFail($this->kualitasForm['id'])->update([
                'nama' => $this->kualitasForm['nama'],
                'keterangan' => $this->kualitasForm['keterangan'] ?: null,
                'is_active' => (bool) $this->kualitasForm['is_active'],
            ]);
        } else {
            KualitasProduk::create([
                'nama' => $this->kualitasForm['nama'],
                'keterangan' => $this->kualitasForm['keterangan'] ?: null,
                'is_active' => (bool) $this->kualitasForm['is_active'],
            ]);
        }

        $this->showKualitasModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Tingkat kualitas disimpan']);
    }

    public function toggleKualitasStatus(int $id): void
    {
        $k = KualitasProduk::findOrFail($id);
        $k->update(['is_active' => ! $k->is_active]);
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Status kualitas diubah']);
    }

    public function hapusKualitas(int $id): void
    {
        $k = KualitasProduk::withCount('produk')->findOrFail($id);
        if ($k->produk_count > 0) {
            $k->update(['is_active' => false]);
            $this->dispatch('alert', [
                'type' => 'warning',
                'message' => "Kualitas terkait dengan {$k->produk_count} produk. Status dinonaktifkan.",
            ]);

            return;
        }

        $k->delete();
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Kualitas berhasil dihapus']);
    }

    // 4. Satuan
    public function openSatuanModal(?int $id = null): void
    {
        if ($id) {
            $s = SatuanUnit::findOrFail($id);
            $this->satuanForm = [
                'id' => $s->id,
                'kode' => $s->kode,
                'nama' => $s->nama,
                'is_active' => (bool) $s->is_active,
            ];
        } else {
            $this->satuanForm = ['id' => null, 'kode' => '', 'nama' => '', 'is_active' => true];
        }
        $this->showSatuanModal = true;
    }

    public function simpanSatuan(): void
    {
        $id = $this->satuanForm['id'] ?? 'NULL';
        $this->validate([
            'satuanForm.kode' => "required|string|max:20|unique:satuan_unit,kode,{$id}",
            'satuanForm.nama' => 'required|string|max:100',
        ]);

        $kode = strtolower(trim($this->satuanForm['kode']));

        if ($this->satuanForm['id']) {
            SatuanUnit::findOrFail($this->satuanForm['id'])->update([
                'kode' => $kode,
                'nama' => $this->satuanForm['nama'],
                'is_active' => (bool) $this->satuanForm['is_active'],
            ]);
        } else {
            SatuanUnit::create([
                'kode' => $kode,
                'nama' => $this->satuanForm['nama'],
                'is_active' => (bool) $this->satuanForm['is_active'],
            ]);
        }

        $this->showSatuanModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Satuan unit disimpan']);
    }

    public function toggleSatuanStatus(int $id): void
    {
        $s = SatuanUnit::findOrFail($id);
        $s->update(['is_active' => ! $s->is_active]);
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Status satuan diubah']);
    }

    public function hapusSatuan(int $id): void
    {
        $s = SatuanUnit::findOrFail($id);
        $terpakai = Produk::where('satuan_kode', $s->kode)->count();
        if ($terpakai > 0) {
            $s->update(['is_active' => false]);
            $this->dispatch('alert', [
                'type' => 'warning',
                'message' => "Satuan '{$s->kode}' digunakan oleh {$terpakai} produk. Status dinonaktifkan.",
            ]);

            return;
        }

        $s->delete();
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Satuan berhasil dihapus']);
    }

    // 5. Kondisi (JSON via KonfigurasiService)
    public function openKondisiModal(?string $kode = null): void
    {
        if ($kode) {
            $list = $this->getKondisiList();
            $item = collect($list)->firstWhere('kode', $kode);
            if ($item) {
                $this->kondisiForm = [
                    'kode' => $item['kode'],
                    'nama' => $item['nama'],
                    'is_active' => (bool) ($item['is_active'] ?? true),
                    'is_edit' => true,
                ];
            }
        } else {
            $this->kondisiForm = [
                'kode' => '',
                'nama' => '',
                'is_active' => true,
                'is_edit' => false,
            ];
        }
        $this->showKondisiModal = true;
    }

    public function simpanKondisi(): void
    {
        $this->validate([
            'kondisiForm.nama' => 'required|string|max:100',
        ]);

        $list = $this->getKondisiList();
        $nama = trim($this->kondisiForm['nama']);
        $kode = $this->kondisiForm['is_edit']
            ? $this->kondisiForm['kode']
            : (Str::slug($this->kondisiForm['kode'] ?: $nama, '_') ?: 'k_'.time());

        $ada = false;
        foreach ($list as &$item) {
            if ($item['kode'] === $kode) {
                $item['nama'] = $nama;
                $item['is_active'] = (bool) $this->kondisiForm['is_active'];
                $ada = true;
                break;
            }
        }
        if (! $ada) {
            $list[] = [
                'kode' => $kode,
                'nama' => $nama,
                'is_active' => (bool) $this->kondisiForm['is_active'],
            ];
        }

        app(KonfigurasiService::class)->set('master_produk_kondisi_list', array_values($list));

        $this->showKondisiModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Kondisi produk disimpan']);
    }

    public function toggleKondisiStatus(string $kode): void
    {
        $list = $this->getKondisiList();
        foreach ($list as &$item) {
            if ($item['kode'] === $kode) {
                $item['is_active'] = ! ($item['is_active'] ?? true);
                break;
            }
        }
        app(KonfigurasiService::class)->set('master_produk_kondisi_list', array_values($list));
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Status kondisi diubah']);
    }

    public function hapusKondisi(string $kode): void
    {
        $terpakai = Produk::where('kondisi', $kode)->count();
        if ($terpakai > 0) {
            $this->toggleKondisiStatus($kode);
            $this->dispatch('alert', [
                'type' => 'warning',
                'message' => "Kondisi '{$kode}' dipakai pada {$terpakai} produk. Status dinonaktifkan.",
            ]);

            return;
        }

        $list = collect($this->getKondisiList())->reject(fn ($i) => $i['kode'] === $kode)->values()->all();
        app(KonfigurasiService::class)->set('master_produk_kondisi_list', $list);
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Kondisi berhasil dihapus']);
    }

    private function getKondisiList(): array
    {
        $config = app(KonfigurasiService::class);
        $saved = $config->get('master_produk_kondisi_list');

        return (is_array($saved) && ! empty($saved))
            ? $saved
            : [
                ['kode' => 'baru', 'nama' => 'Baru', 'is_active' => true],
                ['kode' => 'oem', 'nama' => 'OEM', 'is_active' => true],
                ['kode' => 'compatible', 'nama' => 'Compatible', 'is_active' => true],
                ['kode' => 'bekas', 'nama' => 'Bekas / Copotan', 'is_active' => true],
            ];
    }

    // 6. Tipe HP
    public function openTipeHpModal(?int $id = null): void
    {
        if ($id) {
            $t = TipeHp::findOrFail($id);
            $this->tipeHpForm = [
                'id' => $t->id,
                'merk' => $t->merk,
                'model' => $t->model,
                'nama' => $t->nama ?? '',
                'is_active' => (bool) $t->is_active,
            ];
        } else {
            $this->tipeHpForm = ['id' => null, 'merk' => '', 'model' => '', 'nama' => '', 'is_active' => true];
        }
        $this->showTipeHpModal = true;
    }

    public function simpanTipeHp(): void
    {
        $this->validate([
            'tipeHpForm.merk' => 'required|string|max:100',
            'tipeHpForm.model' => 'required|string|max:100',
        ]);

        $nama = trim($this->tipeHpForm['nama']) ?: "{$this->tipeHpForm['merk']} {$this->tipeHpForm['model']}";

        if ($this->tipeHpForm['id']) {
            TipeHp::findOrFail($this->tipeHpForm['id'])->update([
                'merk' => $this->tipeHpForm['merk'],
                'model' => $this->tipeHpForm['model'],
                'nama' => $nama,
                'is_active' => (bool) $this->tipeHpForm['is_active'],
            ]);
        } else {
            TipeHp::create([
                'merk' => $this->tipeHpForm['merk'],
                'model' => $this->tipeHpForm['model'],
                'nama' => $nama,
                'is_active' => (bool) $this->tipeHpForm['is_active'],
            ]);
        }

        $this->showTipeHpModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Tipe HP berhasil disimpan']);
    }

    public function toggleTipeHpStatus(int $id): void
    {
        $t = TipeHp::findOrFail($id);
        $t->update(['is_active' => ! $t->is_active]);
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Status tipe HP diubah']);
    }

    public function hapusTipeHp(int $id): void
    {
        $t = TipeHp::withCount('produk')->findOrFail($id);
        if ($t->produk_count > 0) {
            $t->update(['is_active' => false]);
            $this->dispatch('alert', [
                'type' => 'warning',
                'message' => "Tipe HP ini terhubung ke {$t->produk_count} produk. Status dinonaktifkan.",
            ]);

            return;
        }

        $t->delete();
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Tipe HP berhasil dihapus']);
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
        return view('modules.rbac.livewire.settings-rbac', [
            'roles' => $this->roles,
            'rolesFull' => $this->rolesFull,
            'permissionsList' => $this->permissionsList,
            'cabangs' => $this->cabangs,
            'gudangs' => $this->gudangs,
            'users' => $this->users,
            'jenisServis' => $this->jenisServis,
            'coaList' => $this->coaList,
            // Master Produk & Katalog
            'kategoriList' => KategoriProduk::with('parent')->withCount('produk', 'children')->orderBy('urutan')->orderBy('nama')->get(),
            'kategoriTree' => KategoriProduk::getTree(),
            'brandList' => Brand::withCount('produk')->orderBy('nama')->get(),
            'kualitasList' => KualitasProduk::withCount('produk')->orderBy('nama')->get(),
            'satuanList' => SatuanUnit::orderBy('kode')->get(),
            'kondisiList' => $this->getKondisiList(),
            'tipeHpList' => TipeHp::withCount('produk')->orderBy('merk')->orderBy('model')->get(),
        ])->layout('layouts.backoffice', ['header' => 'Pengaturan & RBAC']);
    }
}
