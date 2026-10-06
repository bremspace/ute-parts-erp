<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'superadmin@uteparts.id'],
            [
                'name' => 'Super Admin UteParts',
                'phone' => '081234567801',
                'password' => Hash::make('Password123!'),
                'is_active' => true,
            ]
        );

        $user->assignRole('super-admin');

        $cabangs = Cabang::orderBy('id')->get();

        foreach ($cabangs as $cabang) {
            if (! $user->cabangs()->where('cabang_id', $cabang->id)->exists()) {
                $user->cabangs()->attach($cabang->id, ['is_default' => $cabang->kode === 'CBG-01']);
            }
        }
    }
}
