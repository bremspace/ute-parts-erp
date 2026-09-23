<?php

use App\Modules\Workflow\Models\ApprovalRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * [F1-1] Seeder idempoten — rule approval tambahan + permission approve-workflow
 * (pola atur-harga-fleksibel: firstOrCreate di migration, aman dijalankan berulang).
 *
 * Applies: PO > 10jt (finance), retur penjualan/pembelian > 5jt (super-admin),
 * diskon besar > 1jt (admin-toko).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('approval_rules')) {
            return;
        }

        // Permission approve-workflow (idempoten)
        $permission = Permission::firstOrCreate([
            'name' => 'approve-workflow',
            'guard_name' => 'web',
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Assign role default: super-admin all, finance + admin-toko subset (PRD §5.3)
        foreach (['super-admin', 'finance', 'admin-toko'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role && ! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Rule global default (cabang_id null = berlaku semua cabang)
        $rules = [
            ['entity_type' => 'po', 'min_amount' => 10000000, 'approver_role' => 'finance', 'level' => 1],
            ['entity_type' => 'retur', 'min_amount' => 5000000, 'approver_role' => 'super-admin', 'level' => 1],
            ['entity_type' => 'retur_pembelian', 'min_amount' => 5000000, 'approver_role' => 'super-admin', 'level' => 1],
            ['entity_type' => 'diskon', 'min_amount' => 1000000, 'approver_role' => 'admin-toko', 'level' => 1],
        ];

        foreach ($rules as $rule) {
            ApprovalRule::firstOrCreate(
                ['entity_type' => $rule['entity_type'], 'cabang_id' => null],
                $rule + ['max_amount' => null, 'is_aktif' => true]
            );
        }
    }

    public function down(): void
    {
        // Seeder idempoten — tidak ada yang dibatalkan (aturan dikonfigurasi via UI/DB)
    }
};
