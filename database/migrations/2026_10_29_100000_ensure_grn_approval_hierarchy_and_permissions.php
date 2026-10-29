<?php

use App\Modules\Workflow\Models\ApprovalRule;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Pastikan permission wms.receive-po ada dan diberikan ke role pemilik otoritas
        $perm = Permission::firstOrCreate(['name' => 'wms.receive-po']);

        $rolesToGrant = ['super-admin', 'owner', 'admin-toko', 'finance'];
        foreach ($rolesToGrant as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role && ! $role->hasPermissionTo('wms.receive-po')) {
                $role->givePermissionTo($perm);
            }
        }

        // 2. Pastikan ApprovalRule untuk GRN memiliki hierarki role yang benar
        // Level 1: admin-toko (manajer toko / kepala cabang)
        ApprovalRule::updateOrCreate(
            ['entity_type' => 'grn', 'cabang_id' => null, 'level' => 1],
            [
                'approver_role' => 'admin-toko',
                'min_amount' => 0,
                'max_amount' => null,
                'is_aktif' => true,
            ]
        );

        // Level 2: finance (bagian keuangan untuk otorisasi pembayaran nominal besar bila perlu)
        ApprovalRule::firstOrCreate(
            ['entity_type' => 'grn', 'cabang_id' => null, 'level' => 2],
            [
                'approver_role' => 'finance',
                'min_amount' => 10000000, // 10 juta ke atas
                'max_amount' => null,
                'is_aktif' => true,
            ]
        );
    }

    public function down(): void
    {
        // Idempotent migration - tidak perlu drop permission inti
    }
};
