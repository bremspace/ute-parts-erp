<?php

namespace App\Modules\Hr\Services;

use App\Models\User;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Service tersentralisasi untuk sinkronisasi User dan Data Karyawan HR.
 */
class UserHrSyncService
{
    /**
     * Resolusi jabatan standar HR berdasarkan role sistem.
     */
    public function resolveJabatanFromRole(?string $role): string
    {
        return match ($role) {
            'teknisi' => 'teknisi',
            'kasir' => 'kasir',
            'admin-toko', 'staff-gudang' => 'admin',
            'marketing' => 'marketing',
            'kelola-hr', 'finance' => 'manager',
            default => 'staff',
        };
    }

    /**
     * Generate NIK sequential unik (format EMP-xxxx).
     */
    public function generateNik(): string
    {
        $maxId = Karyawan::max('id') ?? 0;
        $next = $maxId + 1;
        $prefix = 'EMP-';

        $candidate = $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        while (Karyawan::where('nik', $candidate)->exists()) {
            $next++;
            $candidate = $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        }

        return $candidate;
    }

    /**
     * Sinkronisasi data User ke entitas Karyawan HR (idempoten).
     */
    public function syncUserToKaryawan(User $user, ?string $nik = null, ?string $jabatan = null, ?int $cabangId = null): Karyawan
    {
        return DB::transaction(function () use ($user, $nik, $jabatan, $cabangId) {
            $karyawan = Karyawan::where('user_id', $user->id)->first();

            $finalNik = trim($nik ?? '');
            if ($finalNik === '') {
                $finalNik = $karyawan?->nik ?: $this->generateNik();
            }

            $primaryRole = $user->roles->first()?->name;
            $finalJabatan = $jabatan ?: $this->resolveJabatanFromRole($primaryRole);

            $defaultCabangId = $cabangId
                ?: $user->cabangs->first(fn ($c) => (bool) $c->pivot->is_default)?->id
                ?? $user->cabangs->first()?->id
                ?? Cabang::where('is_active', true)->value('id');

            return Karyawan::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'nama' => $user->name,
                    'nik' => $finalNik,
                    'jabatan' => $finalJabatan,
                    'cabang_id' => $defaultCabangId,
                    'tgl_masuk' => $karyawan?->tgl_masuk ?? now()->toDateString(),
                    'gaji_pokok' => $karyawan?->gaji_pokok ?? 0,
                    'status_aktif' => (bool) $user->is_active,
                ]
            );
        });
    }

    /**
     * Sinkronisasi status aktif user ke karyawan.
     */
    public function syncUserStatus(User $user): void
    {
        Karyawan::where('user_id', $user->id)->update([
            'status_aktif' => (bool) $user->is_active,
        ]);
    }

    /**
     * Buat akun User baru dan tautkan langsung ke profil Karyawan.
     */
    public function createUserFromKaryawan(Karyawan $karyawan, string $email, string $password, string $role, ?int $cabangId = null): User
    {
        return DB::transaction(function () use ($karyawan, $email, $password, $role, $cabangId) {
            $user = User::create([
                'name' => $karyawan->nama,
                'email' => trim($email),
                'password' => Hash::make($password),
                'is_active' => (bool) $karyawan->status_aktif,
            ]);

            $user->syncRoles([$role]);

            $targetCabangId = $cabangId ?: $karyawan->cabang_id ?: Cabang::where('is_active', true)->value('id');
            if ($targetCabangId) {
                $user->cabangs()->sync([(int) $targetCabangId => ['is_default' => true]]);
            }

            $karyawan->update(['user_id' => $user->id]);

            return $user;
        });
    }

    /**
     * Sinkronisasi seluruh user backoffice yang ada ke HR Karyawan.
     */
    public function syncAllExistingUsers(): int
    {
        $users = User::with(['roles', 'cabangs'])->get();
        $count = 0;

        foreach ($users as $user) {
            $this->syncUserToKaryawan($user);
            $count++;
        }

        return $count;
    }
}
