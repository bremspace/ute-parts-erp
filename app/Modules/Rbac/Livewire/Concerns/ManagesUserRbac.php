<?php

namespace App\Modules\Rbac\Livewire\Concerns;

use App\Models\User;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Services\UserHrSyncService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Services\AuditService;
use Illuminate\Support\Facades\Hash;

/**
 * Trait ManagesUserRbac
 *
 * Menangani fungsi CRUD User, assignment Role Spatie, sinkronisasi cabang user,
 * guardrail anti self-lockout, dan integrasi UserHrSyncService.
 */
trait ManagesUserRbac
{
    public function getUsersProperty()
    {
        return User::with(['roles', 'cabangs', 'karyawan'])->latest()->paginate(15);
    }

    public function openUserModal(?int $id = null)
    {
        if ($id) {
            $u = User::with(['roles', 'cabangs', 'karyawan'])->findOrFail($id);
            $defaultCabang = $u->cabangs->first(fn ($c) => (bool) $c->pivot->is_default);
            $this->userForm = [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'password' => '',
                'phone' => $u->phone ?? '', 'role' => $u->roles->first()?->name ?? 'karyawan',
                'cabang_ids' => $u->cabangs->pluck('id')->toArray(),
                'cabang_default_id' => $defaultCabang?->id ?? $u->cabangs->first()?->id,
                'is_active' => (bool) $u->is_active,
                'buat_karyawan' => (bool) $u->karyawan,
                'nik_karyawan' => $u->karyawan?->nik ?? '',
            ];
        } else {
            $this->userForm = [
                'id' => null, 'name' => '', 'email' => '', 'password' => '', 'phone' => '',
                'role' => 'karyawan', 'cabang_ids' => [], 'cabang_default_id' => null, 'is_active' => true,
                'buat_karyawan' => true, 'nik_karyawan' => '',
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

        $rules = [
            'userForm.name' => 'required|string|max:255',
            'userForm.email' => 'required|email|unique:users,email,'.($this->userForm['id'] ?? 'NULL'),
            'userForm.role' => 'required|string|exists:roles,name',
        ];

        if (! $this->userForm['id']) {
            $rules['userForm.password'] = 'required|string|min:6';
        } else {
            $rules['userForm.password'] = 'nullable|string|min:6';
        }

        if (! empty($this->userForm['buat_karyawan']) && ! empty($this->userForm['nik_karyawan'])) {
            $existingKaryawanId = Karyawan::where('user_id', $this->userForm['id'])->value('id');
            $rules['userForm.nik_karyawan'] = 'nullable|string|max:50|unique:karyawan,nik,'.($existingKaryawanId ?: 'NULL').',id';
        }

        $this->validate($rules, [
            'userForm.name.required' => 'Nama pengguna wajib diisi.',
            'userForm.email.required' => 'Email pengguna wajib diisi.',
            'userForm.email.email' => 'Format email tidak valid.',
            'userForm.email.unique' => 'Email sudah digunakan.',
            'userForm.password.required' => 'Password wajib diisi untuk pengguna baru.',
            'userForm.password.min' => 'Password minimal 6 karakter.',
            'userForm.role.required' => 'Role wajib dipilih.',
            'userForm.role.exists' => 'Role yang dipilih tidak valid.',
            'userForm.nik_karyawan.unique' => 'NIK karyawan sudah digunakan.',
        ]);

        if (! $this->userForm['id']) {
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

            // Guardrail anti self-lockout
            if ($user->id === auth()->id()) {
                if (! $this->userForm['is_active']) {
                    $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri (anti-lockout).']);

                    return;
                }
                if ($user->hasRole('super-admin') && $this->userForm['role'] !== 'super-admin') {
                    $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda tidak dapat mencabut role super-admin dari akun Anda sendiri (anti-lockout).']);

                    return;
                }
            }

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

        if (empty($cabangIds)) {
            $firstCabang = Cabang::where('is_active', true)->first();
            if ($firstCabang) {
                $cabangIds = [(int) $firstCabang->id];
                $defaultId = (int) $firstCabang->id;
            }
        } elseif (! in_array($defaultId, $cabangIds, true)) {
            $defaultId = $cabangIds[0];
        }

        $syncData = [];
        foreach ($cabangIds as $cid) {
            $syncData[$cid] = ['is_default' => ($cid === $defaultId)];
        }
        $user->cabangs()->sync($syncData);

        if (! empty($this->userForm['buat_karyawan'])) {
            app(UserHrSyncService::class)->syncUserToKaryawan(
                $user,
                $this->userForm['nik_karyawan'] ?: null,
                null,
                $defaultId
            );
        }

        app(AuditService::class)->catat('User', $aksi, $user->id, "User {$user->name} dikelola ({$this->userForm['role']})");

        $this->showUserModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'User berhasil disimpan']);
    }

    public function toggleUserActive(int $id)
    {
        if (! $this->boleh('user.edit', 'Anda tidak memiliki izin mengubah status pengguna.')) {
            return;
        }

        if ($id === (int) auth()->id()) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri (anti-lockout).']);

            return;
        }

        $u = User::findOrFail($id);
        $u->update(['is_active' => ! $u->is_active]);
        app(UserHrSyncService::class)->syncUserStatus($u);
        app(AuditService::class)->catat('User', 'update', $u->id, "Status user {$u->name} → ".($u->is_active ? 'aktif' : 'nonaktif'));
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Status user diubah']);
    }
}
