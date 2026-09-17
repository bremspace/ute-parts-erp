<?php

namespace App\Modules\Rbac\Livewire;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Services\AuditService;
use App\Modules\Servis\Models\JenisServis;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Wms\Models\Gudang;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

/**
 * Pengaturan & RBAC (PRD Frontend §5.9, §5.11):
 * Manajemen user + role + cabang, master data (cabang/gudang/jenis servis/COA).
 */
class SettingsRbac extends Component
{
    use WithPagination;

    public string $activeTab = 'users'; // users, cabang, master

    // User CRUD
    public bool $showUserModal = false;
    public array $userForm = [
        'id' => null, 'name' => '', 'email' => '', 'password' => '', 'phone' => '',
        'role' => '', 'cabang_ids' => [], 'is_active' => true,
    ];

    // Cabang CRUD
    public bool $showCabangModal = false;
    public array $cabangForm = ['id' => null, 'nama' => '', 'kode' => '', 'alamat' => '', 'telepon' => '', 'is_active' => true];

    // Gudang CRUD
    public bool $showGudangModal = false;
    public array $gudangForm = ['id' => null, 'cabang_id' => null, 'nama' => '', 'kode' => '', 'is_active' => true];

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
        return \Spatie\Permission\Models\Permission::orderBy('name')->get();
    }

    // Role: semua role + permission (recompute)
    public function getRolesFullProperty()
    {
        return \Spatie\Permission\Models\Role::with('permissions')->orderBy('name')->get();
    }

    public function openRoleModal()
    {
        $this->roleBaruNama = '';
        $this->roleBaruPermissions = [];
        $this->showRoleModal = true;
    }

    public function saveRoleBaru()
    {
        $this->validate(['roleBaruNama' => 'required|string|max:255|unique:roles,name']);

        $role = \Spatie\Permission\Models\Role::create(['name' => $this->roleBaruNama]);
        if (!empty($this->roleBaruPermissions)) {
            $role->syncPermissions($this->roleBaruPermissions);
        }

        app(AuditService::class)->catat('Role', 'create', $role->id, "Role {$role->name} dibuat");

        $this->showRoleModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Role baru dibuat']);
    }

    public function openEditRole(int $roleId)
    {
        $role = \Spatie\Permission\Models\Role::with('permissions')->findOrFail($roleId);
        $this->editRoleId = $roleId;
        $this->editRolePermissions = $role->permissions->pluck('name')->toArray();
    }

    public function saveEditRolePermissions()
    {
        $role = \Spatie\Permission\Models\Role::findOrFail($this->editRoleId);

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
        } else {
            $this->jenisServisForm = [
                'id' => null, 'nama' => '', 'kode' => '', 'kategori' => 'hardware',
                'estimasi_durasi' => 120, 'durasi_garansi_hari' => 30, 'butuh_part' => true,
                'is_part_original' => false, 'is_active' => true,
            ];
        }
        $this->showJenisServisModal = true;
    }

    public function simpanJenisServis()
    {
        $this->validate([
            'jenisServisForm.nama' => 'required|string|max:255',
            'jenisServisForm.kode' => 'required|string|max:20|unique:jenis_servis,kode,' . ($this->jenisServisForm['id'] ?? 'NULL'),
        ]);

        if ($this->jenisServisForm['id']) {
            JenisServis::findOrFail($this->jenisServisForm['id'])->update($this->jenisServisForm);
        } else {
            JenisServis::create($this->jenisServisForm);
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
            $this->userForm = [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'password' => '',
                'phone' => $u->phone ?? '', 'role' => $u->roles->first()?->name ?? '',
                'cabang_ids' => $u->cabangs->pluck('id')->toArray(), 'is_active' => (bool) $u->is_active,
            ];
        } else {
            $this->userForm = [
                'id' => null, 'name' => '', 'email' => '', 'password' => '', 'phone' => '',
                'role' => 'kasir', 'cabang_ids' => [], 'is_active' => true,
            ];
        }
        $this->showUserModal = true;
    }

    public function saveUser()
    {
        $this->validate([
            'userForm.name' => 'required|string|max:255',
            'userForm.email' => 'required|email|unique:users,email,' . ($this->userForm['id'] ?? 'NULL'),
        ]);

        if (!$this->userForm['id']) {
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
        $user->cabangs()->sync($this->userForm['cabang_ids'] ?? []);

        app(AuditService::class)->catat('User', $aksi, $user->id, "User {$user->name} dikelola ({$this->userForm['role']})");

        $this->showUserModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'User berhasil disimpan']);
    }

    public function toggleUserActive(int $id)
    {
        $u = User::findOrFail($id);
        $u->update(['is_active' => !$u->is_active]);
        app(AuditService::class)->catat('User', 'update', $u->id, "Status user {$u->name} → " . ($u->is_active ? 'aktif' : 'nonaktif'));
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
        $this->validate([
            'cabangForm.nama' => 'required|string|max:255',
            'cabangForm.kode' => 'required|string|max:20|unique:cabang,kode,' . ($this->cabangForm['id'] ?? 'NULL'),
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
        $this->validate([
            'gudangForm.cabang_id' => 'required|exists:cabang,id',
            'gudangForm.nama' => 'required|string|max:255',
            'gudangForm.kode' => 'required|string|max:20|unique:gudang,kode,' . ($this->gudangForm['id'] ?? 'NULL'),
        ]);

        if ($this->gudangForm['id']) {
            Gudang::findOrFail($this->gudangForm['id'])->update($this->gudangForm);
        } else {
            Gudang::create($this->gudangForm);
        }

        $this->showGudangModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Gudang disimpan']);
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
        ])->layout('layouts.backoffice', ['header' => 'Pengaturan & RBAC']);
    }
}