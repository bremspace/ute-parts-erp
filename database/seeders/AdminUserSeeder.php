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
        $emails = [
            'superadmin@uteparts.id' => 'Super Admin UteParts',
            'super-admin@uteparts.id' => 'Super Admin UteParts (Alias)',
        ];

        $cabangs = Cabang::orderBy('id')->get();

        foreach ($emails as $email => $name) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'phone' => '081234567801',
                    'password' => Hash::make('Password123!'),
                    'is_active' => true,
                ]
            );

            $user->assignRole('super-admin');

            foreach ($cabangs as $cabang) {
                if (! $user->cabangs()->where('cabang_id', $cabang->id)->exists()) {
                    $user->cabangs()->attach($cabang->id, ['is_default' => $cabang->kode === 'CBG-01']);
                }
            }
        }
    }
}
