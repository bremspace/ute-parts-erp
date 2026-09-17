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
            'cabangs' => $this->cabangs,
            'gudangs' => $this->gudangs,
            'users' => $this->users,
            'jenisServis' => $this->jenisServis,
            'coaList' => $this->coaList,
        ])->layout('layouts.backoffice', ['header' => 'Pengaturan & RBAC']);
    }
}