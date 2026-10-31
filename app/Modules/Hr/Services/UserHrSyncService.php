<?php

namespace App\Modules\Hr\Services;

use App\Models\User;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\KaryawanRiwayat;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Services\AuditService;
use App\Modules\Rbac\Services\SessionManagementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Service tersentralisasi untuk sinkronisasi User dan Data Karyawan HR.
 * Mengatur onboarding (rekrut) dan offboarding (pemutusan/terminasi/resign).
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
     * Resolusi default role sistem dari jabatan HR.
     */
    public function resolveRoleFromJabatan(string $jabatan): string
    {
        return match ($jabatan) {
            'teknisi' => 'teknisi',
            'kasir' => 'kasir',
            'admin' => 'admin-toko',
            'marketing' => 'marketing',
            'manager' => 'kelola-hr',
            default => 'karyawan',
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
                    'email' => $user->email,
                    'telepon' => $user->phone ?? $karyawan?->telepon,
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
            'tgl_keluar' => $user->is_active ? null : now()->toDateString(),
            'alasan_keluar' => $user->is_active ? null : 'Akun login dinonaktifkan oleh administrator',
        ]);

        if (! $user->is_active) {
            $this->revokeUserAccess($user);
        }
    }

    /**
     * Buat akun User baru dan tautkan langsung ke profil Karyawan (Onboarding).
     */
    public function createUserFromKaryawan(Karyawan $karyawan, string $email, string $password, string $role, ?int $cabangId = null): User
    {
        return DB::transaction(function () use ($karyawan, $email, $password, $role, $cabangId) {
            $user = User::create([
                'name' => $karyawan->nama,
                'email' => trim($email),
                'phone' => $karyawan->telepon,
                'password' => Hash::make($password),
                'is_active' => (bool) $karyawan->status_aktif,
            ]);

            $user->syncRoles([$role]);

            $targetCabangId = $cabangId ?: $karyawan->cabang_id ?: Cabang::where('is_active', true)->value('id');
            if ($targetCabangId) {
                $user->cabangs()->sync([(int) $targetCabangId => ['is_default' => true]]);
            }

            $karyawan->update([
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            app(AuditService::class)->catat('Karyawan', 'create', $karyawan->id, "Akun user {$user->email} dibuat & ditautkan ke karyawan {$karyawan->nama} (Role: {$role})");

            return $user;
        });
    }

    /**
     * Hubungkan Karyawan ke akun User yang sudah ada.
     */
    public function linkUserToKaryawan(Karyawan $karyawan, int $userId): void
    {
        DB::transaction(function () use ($karyawan, $userId) {
            $user = User::findOrFail($userId);

            // Lepas kaitan karyawan lama jika user pernah terkait
            Karyawan::where('user_id', $user->id)
                ->where('id', '!=', $karyawan->id)
                ->update(['user_id' => null]);

            $karyawan->update([
                'user_id' => $user->id,
                'email' => $karyawan->email ?: $user->email,
                'telepon' => $karyawan->telepon ?: $user->phone,
            ]);

            app(AuditService::class)->catat('Karyawan', 'update', $karyawan->id, "Karyawan {$karyawan->nama} ditautkan ke akun user {$user->name} ({$user->email})");
        });
    }

    /**
     * Putus kaitan User dari Karyawan.
     */
    public function unlinkUserFromKaryawan(Karyawan $karyawan): void
    {
        $userId = $karyawan->user_id;
        $karyawan->update(['user_id' => null]);

        if ($userId) {
            app(AuditService::class)->catat('Karyawan', 'update', $karyawan->id, "Kaitan akun user #{$userId} dilepas dari karyawan {$karyawan->nama}");
        }
    }

    /**
     * Pemutusan Hubungan Kerja (Offboarding / Terminasi / Resign).
     * Menonaktifkan Karyawan, menonaktifkan User login, memutus sesi aktif & token.
     */
    public function terminateKaryawan(Karyawan $karyawan, string $alasan, ?string $tglKeluar = null, ?string $catatan = null): void
    {
        DB::transaction(function () use ($karyawan, $alasan, $tglKeluar, $catatan) {
            $tglKeluar = $tglKeluar ?: now()->toDateString();

            $karyawan->update([
                'status_aktif' => false,
                'tgl_keluar' => $tglKeluar,
                'alasan_keluar' => $alasan,
                'catatan_keluar' => $catatan,
            ]);

            // Jika punya user login, nonaktifkan dan putus seluruh akses seketika
            if ($karyawan->user_id && $user = $karyawan->user) {
                $user->update(['is_active' => false]);
                $this->revokeUserAccess($user);
            }

            // Catat ke riwayat ketenagakerjaan karyawan
            KaryawanRiwayat::create([
                'karyawan_id' => $karyawan->id,
                'user_id' => auth()->id(),
                'tipe_event' => 'terminasi',
                'tanggal_efektif' => $tglKeluar,
                'judul' => 'Pemutusan Hubungan Kerja ('.ucfirst(str_replace('_', ' ', $alasan)).')',
                'deskripsi' => $catatan ?: "Karyawan diberhentikan / non-aktif dengan alasan: {$alasan}",
                'jabatan_lama' => $karyawan->jabatan,
                'cabang_lama_id' => $karyawan->cabang_id,
                'gaji_lama' => $karyawan->gaji_pokok,
            ]);

            app(AuditService::class)->catat(
                'Karyawan',
                'update',
                $karyawan->id,
                "Pemutusan Karyawan: {$karyawan->nama} ({$karyawan->nik}) dinonaktifkan. Alasan: {$alasan}. Tgl Keluar: {$tglKeluar}"
            );
        });
    }

    /**
     * Mempekerjakan kembali karyawan yang sebelumnya terminated (Re-hire).
     */
    public function rehireKaryawan(Karyawan $karyawan, ?string $tglMasukBaru = null): void
    {
        DB::transaction(function () use ($karyawan, $tglMasukBaru) {
            $karyawan->update([
                'status_aktif' => true,
                'tgl_masuk' => $tglMasukBaru ?: now()->toDateString(),
                'tgl_keluar' => null,
                'alasan_keluar' => null,
                'catatan_keluar' => null,
            ]);

            if ($karyawan->user_id && $user = $karyawan->user) {
                $user->update(['is_active' => true]);
            }

            // Catat ke riwayat ketenagakerjaan karyawan
            KaryawanRiwayat::create([
                'karyawan_id' => $karyawan->id,
                'user_id' => auth()->id(),
                'tipe_event' => 'rehire',
                'tanggal_efektif' => $tglMasukBaru ?: now()->toDateString(),
                'judul' => 'Pengaktifan Kembali (Re-hire)',
                'deskripsi' => 'Karyawan diaktifkan kembali dan hak akses login sistem dibuka kembali.',
                'jabatan_baru' => $karyawan->jabatan,
                'cabang_baru_id' => $karyawan->cabang_id,
                'gaji_baru' => $karyawan->gaji_pokok,
            ]);

            app(AuditService::class)->catat(
                'Karyawan',
                'update',
                $karyawan->id,
                "Re-hire Karyawan: {$karyawan->nama} ({$karyawan->nik}) diaktifkan kembali"
            );
        });
    }

    /**
     * Revoke total akses pengguna: cabut Sanctum tokens & hapus database sessions.
     */
    public function revokeUserAccess(User $user): void
    {
        // 1. Revoke API Sanctum tokens
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        // 2. Terminate Web Sessions jika ada tabel sessions
        try {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        } catch (\Throwable) {
            // Abaikan jika session driver bukan database
        }

        // 3. Terminate Device Sessions via SessionManagementService jika tersedia
        try {
            app(SessionManagementService::class)->forceLogoutAll($user->id);
        } catch (\Throwable) {
            // fallback
        }
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
