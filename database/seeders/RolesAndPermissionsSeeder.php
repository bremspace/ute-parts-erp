<?php

namespace Database\Seeders;

use App\Modules\Rbac\Services\RbacBootstrapService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Node cloud memakai seeder ini. Katalog aslinya ada di RbacBootstrapService
     * agar SetupWizard node client Docker bisa memakai katalog yang sama tanpa
     * menjalankan seeder.
     */
    public function run(): void
    {
        app(RbacBootstrapService::class)->bootstrap();
    }
}
