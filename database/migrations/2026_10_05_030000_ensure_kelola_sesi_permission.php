<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Pastikan permission 'kelola-sesi' tersedia di database
        $perm = Permission::firstOrCreate(['name' => 'kelola-sesi', 'guard_name' => 'web']);

        // Berikan ke super-admin dan owner
        $superAdmin = Role::where('name', 'super-admin')->first();
        if ($superAdmin && ! $superAdmin->hasPermissionTo('kelola-sesi')) {
            $superAdmin->givePermissionTo($perm);
        }

        $owner = Role::where('name', 'owner')->first();
        if ($owner && ! $owner->hasPermissionTo('kelola-sesi')) {
            $owner->givePermissionTo($perm);
        }

        // Berikan ke admin-toko
        $adminToko = Role::where('name', 'admin-toko')->first();
        if ($adminToko && ! $adminToko->hasPermissionTo('kelola-sesi')) {
            $adminToko->givePermissionTo($perm);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $perm = Permission::where('name', 'kelola-sesi')->first();
        if ($perm) {
            $perm->delete();
        }
    }
};
