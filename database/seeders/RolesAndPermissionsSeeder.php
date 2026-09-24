<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Clear permission cache via Spatie registrar (not static method on model)
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // [API: RBAC-01] Permissions per module
        $permissions = [
            // POS
            'pos.create', 'pos.view', 'pos.view-own', 'pos.void',
            // WMS
            'wms.view', 'wms.create', 'wms.transfer', 'wms.opname', 'wms.approve-opname', 'wms.receive-po',
            // SERVIS
            'servis.view', 'servis.create', 'servis.update-status', 'servis.input-sparepart', 'servis.approve-estimasi', 'servis.override-status',
            // CRM
            'crm.view', 'crm.create', 'crm.edit', 'crm.delete', 'crm.broadcast',
            // TIER
            'tier.manage',
            // RESELLER
            'reseller.view', 'reseller.manage',
            // KOMISI
            'komisi.view', 'komisi.approve',
            // AKUNTING
            'akunting.view', 'akunting.create', 'akunting.edit', 'akunting.approve',
            // PIUTANG
            'piutang.view', 'piutang.manage',
            // UTANG
            'utang.view', 'utang.manage',
            // LAPORAN — [P2-9] `laporan.kustom` dihapus: PRD-Advanced F2-4 tidak
            // mendefinisikan permission terpisah; PRD-Backend §3 matriks RBAC hanya
            // mencantumkan `laporan.cabang` (admin-toko). Route builder /laporan
            // tetap memakai `permission:laporan.cabang`.
            'laporan.cabang', 'laporan.konsolidasi',
            // USER
            'user.view', 'user.create', 'user.edit', 'user.delete',
            // CABANG
            'cabang.view', 'cabang.manage',
            // PENGATURAN
            'pengaturan.manage',
            // OMNICHANNEL
            'omnichannel.view', 'omnichannel.manage',
            // WORKFLOW (F1-1)
            'approve-workflow',
            // AUDIT TRAIL (F1-4)
            'lihat-audit-log',
            // [F3-1] Field-level security permissions — baru untuk menghide harga_beli/margin dari role kasir/staff
            'lihat.harga_beli',
            'lihat.margin',
            // [F3-8] HR & Payroll permissions — pakai di givePermissionTo role kelola-hr
            'kelola-hr',
            'payroll.view',
            'payroll.create',
            'payroll.approve',
            // [F3-8b] HR Absensi & KPI — karyawan boleh lihat absensi/KPI sendiri
            'hr.lihat-sendiri',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Roles (idempoten — pakai findOrCreate agar bisa di-seed ulang)
        $superAdmin = Role::findOrCreate('super-admin');
        $superAdmin->givePermissionTo(Permission::all());

        $adminToko = Role::findOrCreate('admin-toko');
        $adminToko->givePermissionTo([
            'pos.create', 'pos.view', 'pos.view-own', 'pos.void',
            'wms.view', 'wms.transfer',
            'servis.view', 'servis.create', 'servis.update-status', 'servis.input-sparepart', 'servis.approve-estimasi', 'servis.override-status',
            'crm.view',
            'laporan.cabang',
            'approve-workflow',
            'lihat-audit-log', // [F1-4] admin melihat riwayat audit cabangnya
            // [F3-1] Field-level security: admin bisa lihat harga_beli/margin
            'lihat.harga_beli',
            'lihat.margin',
        ]);

        $kasir = Role::findOrCreate('kasir');
        $kasir->givePermissionTo([
            'pos.create', 'pos.view-own', 'wms.view',
        ]);

        $teknisi = Role::findOrCreate('teknisi');
        $teknisi->givePermissionTo([
            'servis.view', 'servis.update-status', 'servis.input-sparepart',
        ]);

        $staffGudang = Role::findOrCreate('staff-gudang');
        $staffGudang->givePermissionTo([
            'wms.view', 'wms.create', 'wms.transfer', 'wms.opname', 'wms.approve-opname', 'wms.receive-po',
        ]);

        $finance = Role::findOrCreate('finance');
        $finance->givePermissionTo([
            'akunting.view', 'akunting.create', 'akunting.edit', 'akunting.approve',
            'piutang.view', 'piutang.manage',
            'utang.view', 'utang.manage',
            'komisi.approve',
            'laporan.cabang', 'laporan.konsolidasi',
            'approve-workflow',
            'lihat-audit-log', // [F1-4] finance melihat riwayat audit
            // [F3-1] Field-level security: finance bisa lihat harga_beli/margin
            'lihat.harga_beli',
            'lihat.margin',
        ]);

        $marketing = Role::findOrCreate('marketing');
        $marketing->givePermissionTo([
            'crm.view', 'crm.create', 'crm.edit', 'crm.delete', 'crm.broadcast',
            'tier.manage',
            'reseller.view',
        ]);

        // [F3-8] HR & Payroll permissions
        $hr = Role::findOrCreate('kelola-hr');
        $hr->givePermissionTo([
            'kelola-hr', 'payroll.view', 'payroll.create', 'payroll.approve', 'hr.lihat-sendiri',
        ]);
        $superAdmin->givePermissionTo(['kelola-hr', 'payroll.view', 'payroll.create', 'payroll.approve', 'hr.lihat-sendiri']);
        $finance->givePermissionTo(['kelola-hr', 'payroll.view', 'payroll.create', 'payroll.approve', 'hr.lihat-sendiri']);
        $adminToko->givePermissionTo(['kelola-hr', 'payroll.view', 'hr.lihat-sendiri']);

        // [F3-8b] Karyawan (kasir/teknisi/marketing/staff) lihat absensi & KPI sendiri
        foreach ([$kasir, $teknisi, $marketing, $staffGudang] as $roleKaryawan) {
            $roleKaryawan->givePermissionTo('hr.lihat-sendiri');
        }

        // Refresh cache after all roles/permissions created
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
