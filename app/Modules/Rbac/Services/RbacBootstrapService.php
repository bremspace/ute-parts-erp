<?php

namespace App\Modules\Rbac\Services;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Katalog tunggal role & permission.
 *
 * Dipakai oleh:
 * - RolesAndPermissionsSeeder (node cloud).
 * - SetupWizard (node client Docker yang sengaja TANPA seeder).
 *
 * Semua operasi idempoten, aman dijalankan berulang kali.
 */
class RbacBootstrapService
{
    /** @return array<int, string> */
    public function permissions(): array
    {
        return [
            // POS
            'pos.create', 'pos.view', 'pos.view-own', 'pos.void',

            // WMS
            'wms.view', 'wms.create', 'wms.delete', 'wms.transfer', 'wms.opname', 'wms.approve-opname', 'wms.receive-po',

            // SERVIS — `servis.manage` adalah permission resmi master jenis servis.
            // `servis.payment` terpisah dari `servis.update-status`: pembayaran
            // bukan wewenang mengubah status pengerjaan.
            'servis.view', 'servis.create', 'servis.update-status', 'servis.payment', 'servis.input-sparepart', 'servis.approve-estimasi', 'servis.override-status', 'servis.manage',

            // CRM
            'crm.view', 'crm.create', 'crm.edit', 'crm.delete', 'crm.broadcast',

            // TIER & RESELLER
            'tier.manage',
            'reseller.view', 'reseller.manage',

            // KOMISI
            'komisi.view', 'komisi.approve',

            // AKUNTING / PIUTANG / UTANG
            'akunting.view', 'akunting.create', 'akunting.edit', 'akunting.approve',
            'piutang.view', 'piutang.manage',
            'utang.view', 'utang.manage',

            // LAPORAN
            'laporan.cabang', 'laporan.konsolidasi',

            // USER & CABANG
            'user.view', 'user.create', 'user.edit', 'user.delete',
            'cabang.view', 'cabang.manage',

            // PENGATURAN & OMNICHANNEL
            'pengaturan.manage',
            'omnichannel.view', 'omnichannel.manage',

            // WORKFLOW, AUDIT, SESI, HARGA
            'approve-workflow',
            'lihat-audit-log',
            'kelola-sesi',
            'atur-harga-fleksibel',

            // FIELD LEVEL SECURITY
            'lihat.harga_beli',
            'lihat.margin',

            // HR & PAYROLL
            'kelola-hr',
            'kelola-payroll',
            'kelola-bagi-hasil',
            'payroll.view',
            'payroll.create',
            'payroll.approve',
            'hr.lihat-sendiri',
        ];
    }

    /**
     * null = semua permission yang tersedia.
     *
     * @return array<string, array<int, string>|null>
     */
    public function rolePermissions(): array
    {
        return [
            'super-admin' => null,
            'owner' => null,

            'admin-toko' => [
                'pos.create', 'pos.view', 'pos.view-own', 'pos.void',
                'wms.view', 'wms.transfer', 'wms.opname', 'wms.approve-opname', 'wms.receive-po',
                'servis.view', 'servis.create', 'servis.update-status', 'servis.payment', 'servis.input-sparepart', 'servis.approve-estimasi', 'servis.override-status', 'servis.manage',
                'crm.view',
                'laporan.cabang',
                'approve-workflow',
                'lihat-audit-log',
                'kelola-sesi',
                'lihat.harga_beli',
                'lihat.margin',
                'kelola-hr',
                'hr.lihat-sendiri',
            ],

            'kasir' => [
                'pos.create', 'pos.view-own', 'wms.view',
                'servis.payment',
                'servis.view',
                'hr.lihat-sendiri',
            ],

            'teknisi' => [
                // `servis.create` ditambahkan agar guard server-side ServisBoard
                // konsisten dengan POST /api/servis (`permission:servis.create`).
                'servis.view', 'servis.create', 'servis.update-status', 'servis.payment', 'servis.input-sparepart',
                'hr.lihat-sendiri',
            ],

            'staff-gudang' => [
                'wms.view', 'wms.create', 'wms.transfer', 'wms.opname', 'wms.receive-po',
                'hr.lihat-sendiri',
            ],

            'finance' => [
                'akunting.view', 'akunting.create', 'akunting.edit', 'akunting.approve',
                'piutang.view', 'piutang.manage',
                'utang.view', 'utang.manage',
                'komisi.approve',
                'laporan.cabang', 'laporan.konsolidasi',
                'approve-workflow',
                'wms.receive-po',
                'lihat-audit-log',
                'lihat.harga_beli',
                'lihat.margin',
                'kelola-hr',
                'kelola-payroll',
                'kelola-bagi-hasil',
                'payroll.view', 'payroll.create', 'payroll.approve',
                'hr.lihat-sendiri',
            ],

            'marketing' => [
                'crm.view', 'crm.create', 'crm.edit', 'crm.delete', 'crm.broadcast',
                'tier.manage',
                'reseller.view',
                'servis.view',
                'hr.lihat-sendiri',
            ],

            'kelola-hr' => [
                'kelola-hr', 'payroll.view', 'payroll.create', 'payroll.approve', 'hr.lihat-sendiri',
            ],

            'karyawan' => [
                'hr.lihat-sendiri',
            ],
        ];
    }

    /**
     * Buat permission & role yang belum ada, lalu pastikan hak aksesnya sesuai katalog.
     */
    public function bootstrap(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach ($this->permissions() as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        foreach ($this->rolePermissions() as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');

            if ($permissions === null) {
                $role->givePermissionTo(Permission::all());

                continue;
            }

            if ($permissions !== []) {
                $role->givePermissionTo($permissions);
            }
        }

        $registrar->forgetCachedPermissions();
    }
}
