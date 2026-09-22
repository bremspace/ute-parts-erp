<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * [T-01] User contoh per role (PRD Backend §3) — idempotent.
 * Kredensial jelas untuk verifikasi batasan akses tiap role.
 */
class RoleUserSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['super-admin', 'admin-toko', 'kasir', 'teknisi', 'staff-gudang', 'finance', 'marketing'];

        // Pastikan semua role ada (idempotent + aman jika seeder role belum jalan)
        foreach ($roles as $roleName) {
            Role::findOrCreate($roleName);
        }

        $cabangs = Cabang::orderBy('id')->get();

        $users = [
            'super-admin' => ['name' => 'Super Admin Demo',   'email' => 'super-admin@uteparts.test'],
            'admin-toko' => ['name' => 'Admin Toko Demo',    'email' => 'admin-toko@uteparts.test'],
            'kasir' => ['name' => 'Kasir Demo',         'email' => 'kasir@uteparts.test'],
            'teknisi' => ['name' => 'Teknisi Demo',       'email' => 'teknisi@uteparts.test'],
            'staff-gudang' => ['name' => 'Staff Gudang Demo',  'email' => 'staff-gudang@uteparts.test'],
            'finance' => ['name' => 'Finance Demo',       'email' => 'finance@uteparts.test'],
            'marketing' => ['name' => 'Marketing Demo',     'email' => 'marketing@uteparts.test'],
        ];

        foreach ($users as $role => $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'phone' => '08'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ]
            );

            $user->syncRoles([$role]);

            // User multi-cabang: semua role dapat akses ke semua cabang yang ada
            foreach ($cabangs as $cabang) {
                if (! $user->cabangs()->where('cabang_id', $cabang->id)->exists()) {
                    $user->cabangs()->attach($cabang->id);
                }
            }
        }
    }
}
