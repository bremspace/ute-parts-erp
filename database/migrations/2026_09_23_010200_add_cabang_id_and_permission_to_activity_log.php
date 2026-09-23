<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        // [F1-4] Kolom cabang_id pada activity_log — diisi dari row subject
        // (Transaksi/Jurnal/Piutang/Utang langsung; StokItem/PO via gudang)
        // agar filter cabang-aware bisa query index (bukan LIKE JSON).
        if (Schema::hasTable('activity_log') && ! Schema::hasColumn('activity_log', 'cabang_id')) {
            Schema::table('activity_log', function (Blueprint $table) {
                $table->unsignedBigInteger('cabang_id')->nullable()->after('event')->index();
            });
        }

        // Permission lihat-audit-log (idempotent — pola atur-harga-fleksibel)
        $permission = Permission::firstOrCreate([
            'name' => 'lihat-audit-log',
            'guard_name' => 'web',
        ]);

        // Assign: super-admin, finance, admin (admin-toko)
        foreach (['super-admin', 'finance', 'admin-toko'] as $namaRole) {
            $role = Role::where('name', $namaRole)->first();
            if ($role && ! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (Schema::hasTable('activity_log') && Schema::hasColumn('activity_log', 'cabang_id')) {
            Schema::table('activity_log', function (Blueprint $table) {
                $table->dropIndex(['cabang_id']);
                $table->dropColumn('cabang_id');
            });
        }

        // Permission tidak di-hapus agar tidak mempengaruhi role existing
        // \Spatie\Permission\Models\Permission::where('name', 'lihat-audit-log')->delete();
    }
};
