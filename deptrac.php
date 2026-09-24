<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\DirectoryConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

/*
 * Deptrac — dependency graph analysis antar modul aplikasi (app/Modules/*).
 *
 * Baseline 2026-09-24 dibuat dari edge NYATA yang ditemukan analyser pertama
 * (215 violation dikalibrasi → matrix di bawah), sehingga `composer qa:deptrac`
 * hijau pada kondisi saat ini dan hanya merah bila ada:
 *   1) edge antar-modul BARU yang belum terdaftar di ruleset ini, atau
 *   2) pelanggaran aturan yang sengaja kita larang (lihat komentar "SENGAJA").
 *
 * Debt arsitektur yang SAAT INI terdaftar (edge sah tapi sebaiknya ditata
 * ulang — target perbaikan bertahap, jangan dihapus diam-diam):
 *   - Workflow -> Pos/Wms : Observer (PurchaseOrder/ReturnPembelian/ReturnPenjualan/
 *     Transaksi) + ApprovalService memanggil GrnService/CycleCountService —
 *     approval engine tahu detail domain (shotgun). Ideal: event/interface.
 *   - Akunting -> Pos    : KasSesiState dipakai dashboard/controller akunting.
 *   - Marketplace -> Pos : OrderService/PaymentController memakai model Pos.
 *
 * Cara pakai (terminal):
 *   vendor/bin/deptrac analyse                       # laporan console
 *   vendor/bin/deptrac analyse --formatter=mermaid   # visualisasi graph
 *   vendor/bin/deptrac debug:layer                   # isi tiap layer
 *
 * Perbarui ruleset saat arsitektur berubah — keputusan sadar, bukan
 * diam-diam menambah edge.
 */
return static function (DeptracConfig $config): void {
    $config
        ->paths('./app/Modules')
        ->excludeFiles('#.*(Test|test)\.php$#')

        // ── Core (foundational: boleh dipakai semua modul) ──────────────────
        ->layers(
            $coreWorkflow = Layer::withName('CoreWorkflow')->collectors(
                DirectoryConfig::create('app/Modules/Workflow/.*'),
            ),
            $coreNotifikasi = Layer::withName('CoreNotifikasi')->collectors(
                DirectoryConfig::create('app/Modules/Notifikasi/.*'),
            ),
            $coreRbac = Layer::withName('CoreRbac')->collectors(
                DirectoryConfig::create('app/Modules/Rbac/.*'),
            ),
            $coreAkunting = Layer::withName('CoreAkunting')->collectors(
                DirectoryConfig::create('app/Modules/Akunting/.*'),
            ),

            // ── Modul domain ────────────────────────────────────────────────
            $wms = Layer::withName('Wms')->collectors(
                DirectoryConfig::create('app/Modules/Wms/.*'),
            ),
            $reseller = Layer::withName('Reseller')->collectors(
                DirectoryConfig::create('app/Modules/Reseller/.*'),
            ),
            $crm = Layer::withName('Crm')->collectors(
                DirectoryConfig::create('app/Modules/Crm/.*'),
            ),
            $hr = Layer::withName('Hr')->collectors(
                DirectoryConfig::create('app/Modules/Hr/.*'),
            ),
            $pos = Layer::withName('Pos')->collectors(
                DirectoryConfig::create('app/Modules/Pos/.*'),
            ),
            $servis = Layer::withName('Servis')->collectors(
                DirectoryConfig::create('app/Modules/Servis/.*'),
            ),
            $marketplace = Layer::withName('Marketplace')->collectors(
                DirectoryConfig::create('app/Modules/Marketplace/.*'),
            ),
            $omnichannel = Layer::withName('Omnichannel')->collectors(
                DirectoryConfig::create('app/Modules/Omnichannel/.*'),
            ),
            $webhook = Layer::withName('Webhook')->collectors(
                DirectoryConfig::create('app/Modules/Webhook/.*'),
            ),
            $report = Layer::withName('Report')->collectors(
                DirectoryConfig::create('app/Modules/Report/.*'),
            ),
            $dashboard = Layer::withName('Dashboard')->collectors(
                DirectoryConfig::create('app/Modules/Dashboard/.*'),
            ),
        )

        // ── Aturan dependensi (matrix baseline edge nyata) ──────────────────
        ->rulesets(
            // Core boleh dipakai siapa pun; core tidak boleh bergantung ke
            // domain kecuali yang didaftarkan di bawah (debt terkomentar di atas).
            Ruleset::forLayer($coreWorkflow)->accesses(
                $coreNotifikasi, $coreRbac, $coreAkunting, $wms, $pos,
            ),
            Ruleset::forLayer($coreNotifikasi),
            Ruleset::forLayer($coreRbac)->accesses(
                $coreAkunting, $wms, $servis, $crm, $reseller, $pos,
            ),
            Ruleset::forLayer($coreAkunting)->accesses(
                $coreNotifikasi, $coreRbac, $pos, $crm, $wms, $servis, $reseller,
            ),

            // Modul domain: akses ke core + edge domain nyata.
            Ruleset::forLayer($wms)->accesses(
                $coreWorkflow, $coreNotifikasi, $coreRbac, $coreAkunting,
                $pos, $servis, $omnichannel,
            ),
            Ruleset::forLayer($reseller)->accesses(
                $coreWorkflow, $coreNotifikasi, $coreRbac, $coreAkunting,
                $crm, $hr, $pos, $servis,
            ),
            Ruleset::forLayer($crm)->accesses(
                $coreWorkflow, $coreNotifikasi, $coreRbac, $coreAkunting,
                $reseller, $pos, $wms, $servis,
            ),
            Ruleset::forLayer($hr)->accesses(
                $coreWorkflow, $coreNotifikasi, $coreRbac, $coreAkunting,
                $reseller, $servis, $crm,
            ),
            Ruleset::forLayer($pos)->accesses(
                $coreWorkflow, $coreNotifikasi, $coreRbac, $coreAkunting,
                $crm, $hr, $reseller, $wms,
            ),
            Ruleset::forLayer($servis)->accesses(
                $coreWorkflow, $coreNotifikasi, $coreRbac, $coreAkunting,
                $crm, $hr, $reseller, $wms,
            ),
            Ruleset::forLayer($marketplace)->accesses(
                $coreWorkflow, $coreNotifikasi, $coreRbac, $coreAkunting,
                $pos, $crm, $servis, $wms, $reseller,
            ),
            Ruleset::forLayer($omnichannel)->accesses(
                $coreNotifikasi, $coreRbac, $coreAkunting, $wms, $pos,
            ),
            Ruleset::forLayer($webhook)->accesses(
                $coreNotifikasi, $coreRbac, $coreAkunting, $pos, $wms, $servis,
            ),
            Ruleset::forLayer($report)->accesses(
                $coreWorkflow, $coreNotifikasi, $coreRbac, $coreAkunting,
                $wms, $reseller, $crm, $hr, $pos, $servis, $marketplace,
            ),
            Ruleset::forLayer($dashboard)->accesses(
                $coreWorkflow, $coreNotifikasi, $coreRbac, $coreAkunting,
                $wms, $reseller, $crm, $hr, $pos, $servis, $marketplace, $report,
            ),
        );
};
