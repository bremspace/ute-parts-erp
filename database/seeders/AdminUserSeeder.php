<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@uteparts.com'],
            [
                'name' => 'Super Admin',
                'phone' => '08123456789',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );

        $user->assignRole('super-admin');

        $cabang = Cabang::first();
        if ($cabang && !$user->cabangs()->where('cabang_id', $cabang->id)->exists()) {
            $user->cabangs()->attach($cabang->id, ['is_default' => true]);
        }
    }
}
