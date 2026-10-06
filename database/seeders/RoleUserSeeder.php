<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * [T-01] User akun resmi UteParts (@uteparts.id) per role — idempotent.
 * Akun default production & staging untuk seluruh operasional backoffice.
 */
class RoleUserSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'super-admin',
            'owner',
            'admin-toko',
            'kasir',
            'teknisi',
            'staff-gudang',
            'finance',
            'marketing',
            'kelola-hr',
        ];

        // Pastikan semua role ada (idempotent + aman jika seeder role belum jalan)
        foreach ($roles as $roleName) {
            Role::findOrCreate($roleName);
        }

        $cabangs = Cabang::orderBy('id')->get();

        $users = [
            'super-admin' => ['name' => 'Super Admin UteParts',   'email' => 'superadmin@uteparts.id', 'phone' => '081234567801'],
            'owner' => ['name' => 'Owner UteParts',         'email' => 'owner@uteparts.id',      'phone' => '081234567802'],
            'admin-toko' => ['name' => 'Admin Toko UteParts',    'email' => 'admintoko@uteparts.id',  'phone' => '081234567803'],
            'kasir' => ['name' => 'Kasir UteParts',         'email' => 'kasir@uteparts.id',      'phone' => '081234567804'],
            'teknisi' => ['name' => 'Teknisi UteParts',       'email' => 'teknisi@uteparts.id',    'phone' => '081234567805'],
            'staff-gudang' => ['name' => 'Staff Gudang UteParts',  'email' => 'gudang@uteparts.id',     'phone' => '081234567806'],
            'finance' => ['name' => 'Finance UteParts',       'email' => 'finance@uteparts.id',    'phone' => '081234567807'],
            'marketing' => ['name' => 'Marketing UteParts',     'email' => 'marketing@uteparts.id',  'phone' => '081234567808'],
            'kelola-hr' => ['name' => 'HR Manager UteParts',    'email' => 'hr@uteparts.id',         'phone' => '081234567809'],
        ];

        foreach ($users as $role => $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'password' => Hash::make('Password123!'),
                    'is_active' => true,
                ]
            );

            $user->syncRoles([$role]);

            // User multi-cabang: semua role dapat akses ke semua cabang yang ada
            foreach ($cabangs as $cabang) {
                if (! $user->cabangs()->where('cabang_id', $cabang->id)->exists()) {
                    $user->cabangs()->attach($cabang->id, ['is_default' => $cabang->kode === 'CBG-01']);
                }
            }
        }
    }
}
