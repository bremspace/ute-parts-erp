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
            'crm.view', 'crm.create', 'crm.edit', 'crm.broadcast',
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
            // LAPORAN
            'laporan.cabang', 'laporan.konsolidasi',
            // USER
            'user.view', 'user.create', 'user.edit', 'user.delete',
            // CABANG
            'cabang.view', 'cabang.manage',
            // PENGATURAN
            'pengaturan.manage',
            // OMNICHANNEL
            'omnichannel.view', 'omnichannel.manage',
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
        ]);

        $marketing = Role::findOrCreate('marketing');
        $marketing->givePermissionTo([
            'crm.view', 'crm.create', 'crm.edit', 'crm.broadcast',
            'tier.manage',
            'reseller.view',
        ]);

        // Refresh cache after all roles/permissions created
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
