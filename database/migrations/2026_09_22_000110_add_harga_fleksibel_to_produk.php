<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kolom harga_fleksibel pada tabel produk
        if (! Schema::hasColumn('produk', 'harga_fleksibel')) {
            Schema::table('produk', function (Blueprint $table) {
                $table->unsignedTinyInteger('harga_fleksibel')->default(0)->after('is_migrasi_sid');
            });
        }

        // Permission atur-harga-fleksibel (idempotent)
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate([
            'name' => 'atur-harga-fleksibel',
            'guard_name' => 'web',
        ]);

        // Assign ke role super-admin
        $superAdminRole = \Spatie\Permission\Models\Role::where('name', 'super-admin')->first();
        if ($superAdminRole && ! $superAdminRole->hasPermissionTo($permission)) {
            $superAdminRole->givePermissionTo($permission);
        }

        // Clear permission cache
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (Schema::hasColumn('produk', 'harga_fleksibel')) {
            Schema::table('produk', function (Blueprint $table) {
                $table->dropColumn('harga_fleksibel');
            });
        }

        // Permission tidak di-hapus agar tidak mempengaruhi role existing
        // Jika benar-benar perlu rollback permission:
        // \Spatie\Permission\Models\Permission::where('name', 'atur-harga-fleksibel')->delete();
        // app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};