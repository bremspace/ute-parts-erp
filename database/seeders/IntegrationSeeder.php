<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Hr\Models\AbsensiLog;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\KpiHasil;
use App\Modules\Hr\Models\KpiMetric;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Hr\Models\PayrollSlip;
use App\Modules\Hr\Models\Shift;
use App\Modules\Hr\Models\ShiftJadwal;
use App\Modules\Hr\Services\AbsensiService;
use App\Modules\Hr\Services\KpiService;
use App\Modules\Hr\Services\PayrollService;
use App\Modules\Notifikasi\Models\NotifikasiKeluar;
use App\Modules\Omnichannel\Models\Channel;
use App\Modules\Omnichannel\Models\ChannelOrder;
use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\AktivitasLog;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Reseller\Models\KomisiSkema;
use App\Modules\Reseller\Services\KomisiService;
use App\Modules\Servis\Models\Garansi;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Models\TiketServisItem;
use App\Modules\Servis\Services\ServisService;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\CycleCountSchedule;
use App\Modules\Wms\Models\CycleCountTask;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\PembayaranSupplier;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\ReturnPembelian;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\StokOpname;
use App\Modules\Wms\Models\StokOpnameItem;
use App\Modules\Wms\Models\StokTransfer;
use App\Modules\Wms\Models\StokTransferItem;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Models\SupplierScore;
use App\Modules\Wms\Models\TipeHp;
use App\Modules\Workflow\Models\ApprovalRequest;
use App\Modules\Workflow\Models\ApprovalRule;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * IntegrationSeeder — Data end-to-end realistis untuk review semua modul & role.
 *
 * Prinsip:
 * - Idempotent (bisa dijalankan ulang tanpa duplikat)
 * - Memastikan semua seeder existing jalan dulu (memanggil $this->call)
 * - Membuat user per role + karyawan terikat
 * - Transaksi end-to-end: PO → GRN → Stok → POS/Marketplace/Servis → Jurnal → Komisi → Payroll
 * - Cover semua status servis, cycle count, return, transfer antar cabang
 * - Data realistis untuk review manual per role
 */
class IntegrationSeeder extends Seeder
{
    // Role → permission matrix (dari RolesAndPermissionsSeeder)
    private const ROLES = [
        'super-admin' => ['label' => 'Super Admin', 'perms' => '*'],
        'admin-toko' => ['label' => 'Admin Toko', 'perms' => ['pos.*', 'wms.view', 'wms.transfer', 'servis.*', 'crm.view', 'laporan.cabang', 'approve-workflow', 'lihat-audit-log', 'lihat.harga_beli', 'lihat.margin', 'kelola-hr', 'hr.lihat-sendiri']],
        'kasir' => ['label' => 'Kasir', 'perms' => ['pos.create', 'pos.view-own', 'wms.view', 'hr.lihat-sendiri']],
        'teknisi' => ['label' => 'Teknisi', 'perms' => ['servis.view', 'servis.update-status', 'servis.input-sparepart', 'hr.lihat-sendiri']],
        'staff-gudang' => ['label' => 'Staff Gudang', 'perms' => ['wms.view', 'wms.create', 'wms.transfer', 'wms.opname', 'wms.approve-opname', 'wms.receive-po', 'hr.lihat-sendiri']],
        'finance' => ['label' => 'Finance', 'perms' => ['akunting.*', 'piutang.*', 'utang.*', 'komisi.approve', 'laporan.*', 'approve-workflow', 'lihat-audit-log', 'lihat.harga_beli', 'lihat.margin', 'kelola-hr', 'kelola-payroll', 'payroll.*', 'hr.lihat-sendiri']],
        'marketing' => ['label' => 'Marketing', 'perms' => ['crm.*', 'tier.manage', 'reseller.view', 'hr.lihat-sendiri']],
        'kelola-hr' => ['label' => 'HR Manager', 'perms' => ['kelola-hr', 'payroll.view', 'payroll.create', 'payroll.approve', 'hr.lihat-sendiri']],
    ];

    private JurnalService $jurnal;

    private AbsensiService $absensiService;

    private KpiService $kpiService;

    private PayrollService $payrollService;

    private ServisService $servisService;

    private KomisiService $komisiService;

    private ?Cabang $cabang1;

    private ?Cabang $cabang2;

    private ?Gudang $gudangUtama;

    private ?Gudang $gudangToko;

    private ?Gudang $gudangCabang2;

    /** User per role */
    private array $users = [];

    /** Karyawan per user */
    private array $karyawans = [];

    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            CabangSeeder::class,
            TierMembershipSeeder::class,
            AdminUserSeeder::class,
            RoleUserSeeder::class,
            ProdukDanStokSeeder::class,
            JenisServisSeeder::class,
            AkunCoaSeeder::class,
            KomisiSkemaSeeder::class,
            HrSeeder::class,
            TransaksiDemoSeeder::class,
            ReportSeeder::class,
        ]);

        $this->initServices();
        $this->initReferences();

        // 1. User & Karyawan per role (idempotent)
        $this->seedUsersAndKaryawan();

        // 2. Data referensi tambahan (Rak, TipeHP, Brand, Satuan, SupplierScore)
        $this->seedReferenceData();

        // 3. Workflow: ApprovalRule tambahan (GRN, Return, Opname, PO)
        $this->seedApprovalRules();

        // 4. End-to-end: PO → GRN → Stok → Transaksi
        $this->seedPurchaseToSalesFlow();

        // 5. Servis full lifecycle (tiap status + sparepart + garansi + jurnal)
        $this->seedServisFullLifecycle();

        // 6. Cycle Count & Stock Opname & Transfer antar cabang
        $this->seedInventoryOps();

        // 6b. Return Penjualan & Return Pembelian
        $this->seedReturns();

        // 7. HR: Absensi, Shift Jadwal, KPI, Payroll (periode + slip + approval)
        $this->seedHrFull();

        // 8. Komisi multi-aktor settlement
        $this->seedKomisiSettlement();

        // 9. Piutang & Utang realistis (aging)
        $this->seedPiutangUtang();

        // 10. Notifikasi & Broadcast
        $this->seedNotifikasi();

        // 11. Marketplace & Omnichannel
        $this->seedMarketplaceOmnichannel();

        // 12. Audit log samples
        $this->seedAuditLogs();

        Log::info('IntegrationSeeder completed successfully');
    }

    private function initServices(): void
    {
        $this->jurnal = app(JurnalService::class);
        $this->absensiService = app(AbsensiService::class);
        $this->kpiService = app(KpiService::class);
        $this->payrollService = app(PayrollService::class);
        $this->servisService = app(ServisService::class);
        $this->komisiService = app(KomisiService::class);
    }

    private function initReferences(): void
    {
        $this->cabang1 = Cabang::where('kode', 'CBG-01')->first() ?? Cabang::first();
        $this->cabang2 = Cabang::where('kode', 'CBG-02')->first();

        $this->gudangUtama = Gudang::where('kode', 'GDG-01')->first();
        $this->gudangToko = Gudang::where('kode', 'GDG-02')->first();
        $this->gudangCabang2 = $this->cabang2 ? Gudang::where('cabang_id', $this->cabang2->id)->first() : null;

        if (! $this->cabang1 || ! $this->gudangUtama) {
            Log::warning('IntegrationSeeder: cabang/gudang utama tidak ditemukan, abort');

            return;
        }
    }

    private function seedUsersAndKaryawan(): void
    {
        // Mapping role → jabatan enum valid (teknisi, kasir, admin, marketing, other)
        $jabatanMap = [
            'super-admin' => 'admin',
            'admin-toko' => 'admin',
            'kasir' => 'kasir',
            'teknisi' => 'teknisi',
            'staff-gudang' => 'other',
            'finance' => 'other',
            'marketing' => 'marketing',
            'kelola-hr' => 'other',
        ];

        foreach (self::ROLES as $roleName => $config) {
            // Buat user untuk role ini
            $email = Str::slug($roleName).'@uteparts.test';
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $config['label'],
                    'password' => bcrypt('password123'),
                    'is_active' => true,
                ]
            );
            $user->assignRole($roleName);
            $this->users[$roleName] = $user;

            // Buat karyawan terikat (kecuali super-admin yang tidak butuh)
            if ($roleName !== 'super-admin') {
                $jabatanEnum = $jabatanMap[$roleName] ?? 'other';
                $karyawan = Karyawan::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'nik' => 'KRY-'.strtoupper(Str::random(6)),
                        'nama' => $config['label'],
                        'jabatan' => $jabatanEnum,
                        'cabang_id' => $this->cabang1?->id ?? 1,
                        'tgl_masuk' => '2024-01-15',
                        'gaji_pokok' => match ($roleName) {
                            'teknisi' => 5500000,
                            'kasir' => 4500000,
                            'staff-gudang' => 4800000,
                            'marketing' => 5000000,
                            'finance' => 8000000,
                            'admin-toko' => 10000000,
                            'kelola-hr' => 12000000,
                            default => 4500000,
                        },
                        'status_aktif' => true,
                        'rekening_bank' => 'BCA-'.rand(100000, 999999),
                    ]
                );
                $this->karyawans[$roleName] = $karyawan;

                // Shift jadwal harian untuk karyawan ini (Pagi)
                $shiftPagi = Shift::where('cabang_id', $this->cabang1?->id)->where('nama', 'Pagi')->first();
                if ($shiftPagi) {
                    ShiftJadwal::updateOrCreate(
                        [
                            'cabang_id' => $this->cabang1?->id ?? 1,
                            'karyawan_id' => $karyawan->id,
                            'tanggal' => now()->toDateString(),
                        ],
                        ['shift_id' => $shiftPagi->id]
                    );
                }
            }
        }

        // Additional: Finance tambahan untuk approval payroll
        $finance2 = User::updateOrCreate(
            ['email' => 'finance2@uteparts.test'],
            ['name' => 'Finance Approver', 'password' => bcrypt('password123'), 'is_active' => true]
        );
        $finance2->assignRole('finance');
        $this->users['finance-approver'] = $finance2;
    }

    private function seedReferenceData(): void
    {
        // Rak per gudang
        if ($this->gudangUtama) {
            foreach (['A', 'B', 'C'] as $i => $prefix) {
                Rak::firstOrCreate(
                    ['gudang_id' => $this->gudangUtama->id, 'kode' => $prefix.'-01'],
                    ['nama' => "Rak {$prefix} - Gudang Utama", 'is_active' => true]
                );
                Rak::firstOrCreate(
                    ['gudang_id' => $this->gudangUtama->id, 'kode' => $prefix.'-02'],
                    ['nama' => "Rak {$prefix} - Gudang Utama (Overflow)", 'is_active' => true]
                );
            }
        }

        // Tipe HP (kolom: merk, model — tidak ada kategori di migration)
        $tipeData = [
            ['merk' => 'Apple', 'model' => 'iPhone 13'],
            ['merk' => 'Apple', 'model' => 'iPhone 11'],
            ['merk' => 'Samsung', 'model' => 'Galaxy A52'],
            ['merk' => 'Samsung', 'model' => 'Galaxy S22'],
            ['merk' => 'Xiaomi', 'model' => 'Redmi Note 10 Pro'],
            ['merk' => 'Xiaomi', 'model' => 'Poco X3 Pro'],
        ];
        foreach ($tipeData as $t) {
            TipeHp::firstOrCreate(
                ['merk' => $t['merk'], 'model' => $t['model']],
                ['is_active' => true]
            );
        }

        // Brand
        foreach (['Apple', 'Samsung', 'Xiaomi', 'Oppo', 'Vivo'] as $b) {
            Brand::firstOrCreate(['nama' => $b], ['is_active' => true]);
        }

        // Satuan Unit
        foreach (['pcs', 'set', 'box', 'meter', 'liter'] as $s) {
            SatuanUnit::firstOrCreate(['nama' => $s], ['is_active' => true]);
        }

        // Supplier Score - table is scoring per supplier per period, not master data
        // Skip - no master data table for supplier score codes
    }

    private function seedApprovalRules(): void
    {
        $rules = [
            // GRN (Goods Receipt Note)
            ['entity_type' => 'grn', 'level' => 1, 'cabang_id' => $this->cabang1?->id, 'min_amount' => 1000000, 'max_amount' => 50000000, 'approver_role' => 'staff-gudang', 'is_aktif' => true],
            ['entity_type' => 'grn', 'level' => 2, 'cabang_id' => $this->cabang1?->id, 'min_amount' => 50000001, 'max_amount' => null, 'approver_role' => 'admin-toko', 'is_aktif' => true],

            // Return Pembelian
            ['entity_type' => 'return_pembelian', 'level' => 1, 'cabang_id' => $this->cabang1?->id, 'min_amount' => 0, 'max_amount' => 20000000, 'approver_role' => 'staff-gudang', 'is_aktif' => true],
            ['entity_type' => 'return_pembelian', 'level' => 2, 'cabang_id' => $this->cabang1?->id, 'min_amount' => 20000001, 'max_amount' => null, 'approver_role' => 'admin-toko', 'is_aktif' => true],

            // Return Penjualan
            ['entity_type' => 'return_penjualan', 'level' => 1, 'cabang_id' => $this->cabang1?->id, 'min_amount' => 0, 'max_amount' => 5000000, 'approver_role' => 'kasir', 'is_aktif' => true],
            ['entity_type' => 'return_penjualan', 'level' => 2, 'cabang_id' => $this->cabang1?->id, 'min_amount' => 5000001, 'max_amount' => null, 'approver_role' => 'admin-toko', 'is_aktif' => true],

            // Stock Opname
            ['entity_type' => 'stok_opname', 'level' => 1, 'cabang_id' => $this->cabang1?->id, 'min_amount' => 0, 'max_amount' => 10000000, 'approver_role' => 'staff-gudang', 'is_aktif' => true],
            ['entity_type' => 'stok_opname', 'level' => 2, 'cabang_id' => $this->cabang1?->id, 'min_amount' => 10000001, 'max_amount' => null, 'approver_role' => 'admin-toko', 'is_aktif' => true],

            // Cycle Count
            ['entity_type' => 'cycle_count', 'level' => 1, 'cabang_id' => $this->cabang1?->id, 'min_amount' => 0, 'max_amount' => null, 'approver_role' => 'staff-gudang', 'is_aktif' => true],

            // Purchase Order
            ['entity_type' => 'purchase_order', 'level' => 1, 'cabang_id' => $this->cabang1?->id, 'min_amount' => 0, 'max_amount' => 50000000, 'approver_role' => 'admin-toko', 'is_aktif' => true],
            ['entity_type' => 'purchase_order', 'level' => 2, 'cabang_id' => $this->cabang1?->id, 'min_amount' => 50000001, 'max_amount' => null, 'approver_role' => 'finance', 'is_aktif' => true],
        ];

        foreach ($rules as $r) {
            if (! $r['cabang_id']) {
                continue;
            }
            ApprovalRule::updateOrCreate(
                ['entity_type' => $r['entity_type'], 'level' => $r['level'], 'cabang_id' => $r['cabang_id']],
                $r
            );
        }
    }

    private function seedPurchaseToSalesFlow(): void
    {
        if (! $this->cabang1 || ! $this->gudangUtama) {
            return;
        }

        $supplier = Supplier::firstOrCreate(
            ['nama' => 'Supplier Integration Utama'],
            ['kontak' => 'Bpk. Integration', 'telepon' => '081100000001', 'alamat' => 'Jl. Integration No. 1', 'termin_hari' => 30, 'is_active' => true]
        );

        $produk = Produk::first();
        if (! $produk) {
            return;
        }

        // PO Kredit baru (berbeda dari TransaksiDemoSeeder)
        $noPo = 'INT-PO-'.now()->format('Ymd').'-0001';
        if (! PurchaseOrder::where('no_po', $noPo)->exists()) {
            $po = PurchaseOrder::create([
                'no_po' => $noPo,
                'supplier_id' => $supplier->id,
                'gudang_tujuan_id' => $this->gudangUtama->id,
                'status' => 'diterima',
                'metode_bayar' => 'kredit',
                'jatuh_tempo' => now()->addDays(30),
                'total' => 7500000,
                'total_dibayar' => 0,
                'catatan' => 'Integration seeder PO Kredit - siklus penuh',
            ]);

            PurchaseOrderItem::create([
                'purchase_order_id' => $po->id,
                'produk_id' => $produk->id,
                'sku_variant_id' => $produk->skuVariants()->first()?->id,
                'harga_beli' => $produk->harga_beli,
                'jumlah' => 20,
                'subtotal' => $produk->harga_beli * 20,
            ]);

            // GRN (approval workflow)
            $approval = ApprovalRequest::create([
                'entity_type' => 'grn',
                'entity_id' => $po->id,
                'cabang_id' => $this->cabang1?->id,
                'requested_by' => $this->users['staff-gudang']?->id,
                'status' => 'disetujui',
                'catatan' => 'GRN untuk PO Integration',
                'actioned_at' => now(),
                'actioned_by' => $this->users['admin-toko']?->id,
            ]);

            // Jurnal PO diterima kredit
            $this->jurnal->post(
                $this->jurnal->generateNoJurnal('beli', $this->cabang1?->id),
                now(),
                'pembelian',
                [
                    ['akun_kode' => '130-01', 'debit' => 7500000, 'kredit' => 0],
                    ['akun_kode' => '210-01', 'debit' => 0, 'kredit' => 7500000],
                ],
                "GRN {$noPo} diterima (kredit)",
                $this->cabang1?->id,
                $this->users['staff-gudang']?->id,
                PurchaseOrder::class,
                $po->id
            );

            // Pembayaran parsial (50%)
            PembayaranSupplier::create([
                'po_id' => $po->id,
                'jumlah' => 3750000,
                'dibayar_at' => now()->subDays(5),
                'user_id' => $this->users['finance']?->id,
                'keterangan' => 'Bayar 50% PO Integration',
            ]);

            $this->jurnal->post(
                $this->jurnal->generateNoJurnal('bayar', $this->cabang1?->id),
                now()->subDays(5),
                'manual',
                [
                    ['akun_kode' => '210-01', 'debit' => 3750000, 'kredit' => 0],
                    ['akun_kode' => '110-01', 'debit' => 0, 'kredit' => 3750000],
                ],
                "Bayar parsial 50% PO {$noPo}",
                $this->cabang1?->id,
                $this->users['finance']?->id
            );
        }

        // POS Tunai (kasir user)
        $kasir = $this->users['kasir'] ?? $this->users['admin-toko'];
        $produkPos = Produk::first();
        if ($produkPos && $kasir) {
            $harga = (float) $produkPos->harga_jual_retail;
            $noTrx = 'INT-POS-'.now()->format('Ymd').'-0001';
            if (! Transaksi::where('no_transaksi', $noTrx)->exists()) {
                $trx = Transaksi::create([
                    'no_transaksi' => $noTrx,
                    'cabang_id' => $this->cabang1?->id,
                    'kasir_id' => $kasir->id,
                    'gudang_id' => $this->gudangUtama->id,
                    'sumber' => 'pos',
                    'subtotal' => $harga,
                    'diskon_persen' => 0,
                    'diskon_nominal' => 0,
                    'pajak_nominal' => 0,
                    'total_akhir' => $harga,
                    'metode_bayar' => 'tunai',
                    'jumlah_bayar' => $harga,
                    'kembalian' => 0,
                    'status' => 'selesai',
                    'catatan' => 'Integration seeder POS kasir',
                ]);

                TransaksiItem::create([
                    'transaksi_id' => $trx->id,
                    'produk_id' => $produkPos->id,
                    'sku_variant_id' => $produkPos->skuVariants()->first()?->id,
                    'jumlah' => 1,
                    'harga_satuan' => $harga,
                    'diskon_nominal' => 0,
                    'subtotal' => $harga,
                    'hpp' => (float) $produkPos->harga_beli,
                ]);

                // Stok berkurang
                $stok = StokItem::where('produk_id', $produkPos->id)->where('gudang_id', $this->gudangUtama->id)->first();
                if ($stok) {
                    $stok->decrement('jumlah', 1);
                    StokLog::create([
                        'gudang_id' => $this->gudangUtama->id,
                        'produk_id' => $produkPos->id,
                        'user_id' => $kasir->id,
                        'jenis' => 'penjualan',
                        'referensi_tipe' => Transaksi::class,
                        'referensi_id' => $trx->id,
                        'jumlah_sebelum' => $stok->jumlah + 1,
                        'perubahan' => -1,
                        'jumlah_setelah' => $stok->jumlah,
                        'catatan' => $noTrx,
                    ]);
                }

                // Jurnal POS
                $this->jurnal->post(
                    $this->jurnal->generateNoJurnal('pos', $this->cabang1?->id),
                    now(),
                    'pos',
                    [
                        ['akun_kode' => '110-01', 'debit' => $harga, 'kredit' => 0],
                        ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => $harga],
                        ['akun_kode' => '510-02', 'debit' => (float) $produkPos->harga_beli, 'kredit' => 0],
                        ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => (float) $produkPos->harga_beli],
                    ],
                    "Integration POS {$noTrx}",
                    $this->cabang1?->id,
                    $kasir->id,
                    Transaksi::class,
                    $trx->id
                );

                // Komisi reseller jika ada
                $reseller = Pelanggan::where('is_reseller', true)->first();
                if ($reseller) {
                    $this->komisiService->hitungKomisi($trx, $reseller);
                }
            }
        }
    }

    private function seedServisFullLifecycle(): void
    {
        $teknisiUser = $this->users['teknisi'] ?? $this->users['admin-toko'];
        $member = Pelanggan::where('telepon', 'demo-member-gold')->first();
        if (! $teknisiUser || ! $this->cabang1) {
            return;
        }

        $servisService = $this->servisService;
        $teknisiKaryawan = $this->karyawans['teknisi'] ?? Karyawan::where('user_id', $teknisiUser->id)->first();

        $statusFlow = [
            'diterima' => 'diagnosa',
            'diagnosa' => 'menunggu_approval',
            'menunggu_approval' => 'disetujui',
            'disetujui' => 'dikerjakan',
            'dikerjakan' => 'qc',
            'qc' => 'selesai',
            'selesai' => 'diambil',
        ];

        // Buat tiket untuk setiap status akhir
        foreach ($statusFlow as $from => $to) {
            // Simulasi sampai status $to
            $no = 'INT-SRV-'.now()->format('Ymd').'-'.Str::upper(Str::substr($to, 0, 3)).rand(10, 99);
            if (TiketServis::where('no_tiket', $no)->exists()) {
                continue;
            }

            $tiket = TiketServis::create([
                'no_tiket' => $no,
                'cabang_id' => $this->cabang1?->id,
                'pelanggan_id' => $member?->id,
                'nama_pelanggan' => $member?->nama ?? 'Customer Integration',
                'telepon_pelanggan' => $member?->telepon ?? '08000000000',
                'jenis_hp' => 'Integration Test Phone',
                'keluhan' => "Integration test - target status {$to}",
                'kondisi_fisik' => ['Body', 'Screen'],
                'foto_unit' => null,
                'status' => 'diterima',
                'sumber' => 'walkin',
                'estimasi_biaya' => 300000,
                'token_approval' => Str::random(64),
                'tanggal_terima' => now()->subDays(rand(1, 10)),
                'teknisi_id' => $teknisiKaryawan?->id ?? $teknisiUser->id,
            ]);

            // Transisi sampai target
            $current = 'diterima';
            while ($current !== $to && isset($statusFlow[$current])) {
                $next = $statusFlow[$current];
                try {
                    $tiket = $servisService->updateStatus(
                        $tiket, $next, $teknisiKaryawan ?? $teknisiUser,
                        "Integration transisi: {$current} → {$next}"
                    );
                    $current = $next;
                } catch (\Throwable $e) {
                    Log::warning("Integration servis transisi gagal: {$e->getMessage()}");
                    break;
                }
            }

            // Sparepart untuk status dikerjakan/selesai
            if (in_array($to, ['dikerjakan', 'qc', 'selesai', 'diambil'])) {
                $produkSparepart = Produk::where('kategori', 'Baterai')->first();
                if ($produkSparepart) {
                    TiketServisItem::create([
                        'tiket_servis_id' => $tiket->id,
                        'tipe' => 'part',
                        'produk_id' => $produkSparepart->id,
                        'sku_variant_id' => $produkSparepart->skuVariants()->first()?->id,
                        'nama_item' => $produkSparepart->nama,
                        'qty' => 1,
                        'harga' => $produkSparepart->harga_jual_retail,
                        'hpp' => (float) $produkSparepart->harga_beli,
                    ]);
                }
            }

            // Garansi untuk tiket selesai
            if (in_array($to, ['selesai', 'diambil'])) {
                $tiket->update(['tanggal_selesai' => now()->subDays(rand(1, 20))]);
                Garansi::firstOrCreate(
                    ['tiket_servis_id' => $tiket->id],
                    [
                        'tanggal_mulai' => now()->subDays(rand(20, 60)),
                        'tanggal_berakhir' => now()->addDays(30),
                        'durasi_hari' => 30,
                        'keterangan' => 'Garansi sparepart 30 hari',
                    ]
                );
            }
        }
    }

    private function seedInventoryOps(): void
    {
        if (! $this->cabang1 || ! $this->gudangUtama) {
            return;
        }

        // Cycle Count Schedule
        $schedule = CycleCountSchedule::firstOrCreate(
            ['cabang_id' => $this->cabang1?->id, 'nama' => 'Siklus Bulanan Integration'],
            [
                'tipe_target' => 'kategori',
                'target_kategori' => 'Baterai',
                'frekuensi' => 'bulanan',
                'hari' => 1,
                'jam' => '09:00',
                'is_aktif' => true,
            ]
        );

        // Only create one cycle count task per schedule per day (unique constraint on schedule_id + tanggal)
        $existingTask = CycleCountTask::where('cycle_count_schedule_id', $schedule->id)
            ->where('tanggal', now()->toDateString())
            ->first();

        if (! $existingTask) {
            $produk = Produk::take(5)->get();
            $sampleItems = [];
            foreach ($produk as $p) {
                $stokItem = StokItem::where('produk_id', $p->id)->where('gudang_id', $this->gudangUtama->id)->first();
                if ($stokItem) {
                    $sampleItems[] = [
                        'stok_item_id' => $stokItem->id,
                        'produk_id' => $p->id,
                        'gudang_id' => $this->gudangUtama->id,
                        'rak_id' => $stokItem->rak_id,
                        'nama' => $p->nama,
                        'stok_sistem' => $stokItem->jumlah,
                    ];
                }
            }

            if (! empty($sampleItems)) {
                CycleCountTask::create([
                    'cycle_count_schedule_id' => $schedule->id,
                    'no_task' => 'INT-CCT-'.now()->format('Ymd').'-'.Str::random(6),
                    'cabang_id' => $this->cabang1?->id,
                    'tanggal' => now()->toDateString(),
                    'tipe_target' => 'kategori',
                    'target_kategori' => 'Baterai',
                    'target_label' => 'Baterai',
                    'seed' => rand(1000, 9999),
                    'sample_items' => $sampleItems,
                    'status' => 'pending',
                    'threshold_unit' => 3,
                    'threshold_persen' => 5.0,
                    'catatan' => 'Integration cycle count task (multiple products)',
                ]);
            }
        }

        // Stock Transfer antar cabang (jika cabang2 ada)
        if ($this->cabang2 && $this->gudangCabang2) {
            $produk = Produk::first();
            if ($produk) {
                $noTrf = 'INT-TRF-'.now()->format('Ymd').'-001';
                if (! StokTransfer::where('no_transfer', $noTrf)->exists()) {
                    $trf = StokTransfer::create([
                        'no_transfer' => $noTrf,
                        'gudang_asal_id' => $this->gudangUtama->id,
                        'gudang_tujuan_id' => $this->gudangCabang2->id,
                        'user_pengirim_id' => $this->users['staff-gudang']?->id ?? $this->users['admin-toko']?->id,
                        'user_penerima_id' => $this->users['staff-gudang']?->id ?? $this->users['admin-toko']?->id,
                        'status' => 'diterima',
                        'tanggal_kirim' => now()->subDays(2),
                        'tanggal_terima' => now()->subDay(),
                        'catatan' => 'Integration seeder transfer antar cabang',
                    ]);
                    StokTransferItem::create([
                        'stok_transfer_id' => $trf->id,
                        'produk_id' => $produk->id,
                        'sku_variant_id' => $produk->skuVariants()->first()?->id,
                        'jumlah' => 3,
                    ]);
                }
            }
        }

        // Stock Opname dengan approval
        $produk = Produk::take(3)->get();
        $opname = StokOpname::firstOrCreate(
            ['no_opname' => 'INT-OPN-'.now()->format('Ymd').'-001', 'gudang_id' => $this->gudangUtama->id],
            [
                'user_id' => $this->users['staff-gudang']?->id,
                'status' => 'disetujui',
                'catatan' => 'Integration seeder opname',
            ]
        );

        foreach ($produk as $i => $p) {
            $stok = StokItem::where('produk_id', $p->id)->where('gudang_id', $this->gudangUtama->id)->first();
            $sistem = $stok?->jumlah ?? 0;
            $fisik = max(0, $sistem + rand(-2, 2));
            $selisih = $fisik - $sistem;

            StokOpnameItem::firstOrCreate(
                ['stok_opname_id' => $opname->id, 'produk_id' => $p->id],
                [
                    'sku_variant_id' => $p->skuVariants()->first()?->id,
                    'stok_sistem' => $sistem,
                    'stok_fisik' => $fisik,
                    'selisih' => $selisih,
                ]
            );

            // Jurnal selisih
            if ($selisih !== 0) {
                $hpp = (float) $p->harga_beli;
                $nilai = round($hpp * abs($selisih), 2);
                if ($nilai > 0) {
                    $this->jurnal->post(
                        $this->jurnal->generateNoJurnal('opname', $this->cabang1?->id),
                        now(),
                        'opname',
                        [
                            ['akun_kode' => $selisih > 0 ? '130-01' : '520-07', 'debit' => $nilai, 'kredit' => 0],
                            ['akun_kode' => $selisih > 0 ? '520-07' : '130-01', 'debit' => 0, 'kredit' => $nilai],
                        ],
                        "Selisih opname {$opname->no_opname} produk {$p->nama} ({$selisih} unit)",
                        $this->cabang1?->id,
                        $this->users['staff-gudang']?->id,
                        StokOpname::class,
                        $opname->id
                    );
                }
            }
        }
    }

    private function seedReturns(): void
    {
        if (! $this->cabang1 || ! $this->gudangUtama) {
            return;
        }

        // Return Penjualan
        $trx = Transaksi::where('sumber', 'pos')->where('status', 'selesai')->first();
        if ($trx) {
            $noRet = 'INT-RET-'.now()->format('Ymd').'-001';
            if (! ReturnPenjualan::where('no_return', $noRet)->exists()) {
                $ret = ReturnPenjualan::create([
                    'no_return' => $noRet,
                    'transaksi_id' => $trx->id,
                    'pelanggan_id' => $trx->pelanggan_id,
                    'tanggal' => now()->toDateString(),
                    'jumlah' => (float) $trx->total_akhir,
                    'status' => 'selesai',
                    'alasan' => 'Integration seeder return pembeli tidak cocok',
                    'is_migrasi_sid' => false,
                ]);

                // Jurnal return
                $this->jurnal->post(
                    $this->jurnal->generateNoJurnal('retur_jual', $this->cabang1?->id),
                    now(),
                    'retur_jual',
                    [
                        ['akun_kode' => '410-01', 'debit' => (float) $trx->total_akhir, 'kredit' => 0],
                        ['akun_kode' => '110-01', 'debit' => 0, 'kredit' => (float) $trx->total_akhir],
                        ['akun_kode' => '130-01', 'debit' => (float) $trx->items->first()->hpp ?? 0, 'kredit' => 0],
                        ['akun_kode' => '510-02', 'debit' => 0, 'kredit' => (float) $trx->items->first()->hpp ?? 0],
                    ],
                    "Return penjualan {$noRet}",
                    $this->cabang1?->id,
                    $this->users['admin-toko']?->id,
                    ReturnPenjualan::class,
                    $ret->id
                );
            }
        }

        // Return Pembelian
        $po = PurchaseOrder::where('status', 'diterima')->first();
        if ($po) {
            $noRetP = 'INT-RET-P-'.now()->format('Ymd').'-001';
            if (! ReturnPembelian::where('no_return', $noRetP)->exists()) {
                $retP = ReturnPembelian::create([
                    'no_return' => $noRetP,
                    'purchase_order_id' => $po->id,
                    'supplier_id' => $po->supplier_id,
                    'tanggal' => now()->toDateString(),
                    'jumlah' => 500000,
                    'status' => 'selesai',
                    'alasan' => 'Integration seeder return barang cacat',
                    'is_migrasi_sid' => false,
                ]);

                // Jurnal return pembelian
                $this->jurnal->post(
                    $this->jurnal->generateNoJurnal('retur_beli', $this->cabang1?->id),
                    now(),
                    'retur_beli',
                    [
                        ['akun_kode' => '210-01', 'debit' => 500000, 'kredit' => 0],
                        ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => 500000],
                    ],
                    "Return pembelian {$noRetP}",
                    $this->cabang1?->id,
                    $this->users['admin-toko']?->id,
                    ReturnPembelian::class,
                    $retP->id
                );
            }
        }
    }

    private function seedHrFull(): void
    {
        if (! $this->cabang1) {
            return;
        }

        // Absensi per karyawan (30 hari terakhir)
        foreach ($this->karyawans as $role => $karyawan) {
            if (! $karyawan) {
                continue;
            }

            for ($d = 29; $d >= 0; $d--) {
                $tanggal = Carbon::now()->subDays($d)->format('Y-m-d');
                $status = $d % 7 == 0 ? 'izin' : ($d % 7 == 6 ? 'cuti' : 'hadir');
                if ($status === 'hadir') {
                    // Skip weekend
                    if (Carbon::parse($tanggal)->isWeekend()) {
                        $status = 'izin';
                    }
                }

                AbsensiLog::updateOrCreate(
                    [
                        'karyawan_id' => $karyawan->id,
                        'tanggal' => $tanggal,
                    ],
                    [
                        'shift_id' => Shift::where('cabang_id', $this->cabang1?->id)->where('nama', 'Pagi')->first()?->id,
                        'status' => $status,
                        'jam_masuk' => $status === 'hadir' ? '08:00:00' : null,
                        'jam_keluar' => $status === 'hadir' ? '16:00:00' : null,
                        'catatan' => $status !== 'hadir' ? 'Integration seeder: '.$status : 'Integration seeder: hadir normal',
                    ]
                );
            }
        }

        // KPI Hitung untuk periode ini
        $periode = now()->format('Y-m');
        $metricTiket = KpiMetric::where('kode', 'tiket_selesai')->first();
        foreach ($this->karyawans as $role => $karyawan) {
            if (! $karyawan || ! $metricTiket) {
                continue;
            }
            KpiHasil::updateOrCreate(
                ['karyawan_id' => $karyawan->id, 'kpi_metric_id' => $metricTiket->id, 'periode' => $periode],
                [
                    'nilai_aktual' => rand(15, 25),
                    'persen_capaian' => rand(75, 125),
                ]
            );
        }

        // Payroll Periode + Slip + Approval
        $payrollPeriode = PayrollPeriode::firstOrCreate(
            ['periode' => $periode],
            [
                'tanggal_mulai' => Carbon::parse($periode.'-01')->startOfMonth(),
                'tanggal_selesai' => Carbon::parse($periode.'-01')->endOfMonth(),
                'status' => 'selesai',
                'catatan' => 'Integration seeder payroll periode',
            ]
        );

        foreach ($this->karyawans as $role => $karyawan) {
            if (! $karyawan) {
                continue;
            }

            $komisi = $role === 'teknisi' ? 500000 : ($role === 'kasir' ? 300000 : ($role === 'marketing' ? 400000 : 0));
            $tunjangan = 500000;
            $total = $karyawan->gaji_pokok + $tunjangan + $komisi;

            // Hitung slip
            $slip = PayrollSlip::updateOrCreate(
                ['payroll_periode_id' => $payrollPeriode->id, 'karyawan_id' => $karyawan->id],
                [
                    'gaji_pokok' => $karyawan->gaji_pokok,
                    'total_tunjangan' => $tunjangan,
                    'total_potongan' => 0,
                    'total_komisi' => $komisi,
                    'total_gaji' => $total,
                    'status' => 'disetujui',
                ]
            );
        }

        // Skip total_gaji update - kolom tidak ada di tabel payroll_periode

        // Approval request untuk payroll
        $approvalRulePayroll = ApprovalRule::where('entity_type', 'payroll')->where('level', 1)->first();
        if ($approvalRulePayroll) {
            ApprovalRequest::firstOrCreate(
                [
                    'entity_type' => 'payroll',
                    'entity_id' => $payrollPeriode->id,
                    'approval_rule_id' => $approvalRulePayroll->id,
                ],
                [
                    'cabang_id' => $this->cabang1?->id,
                    'payload_json' => ['periode' => $periode, 'total_karyawan' => count($this->karyawans)],
                    'requested_by' => $this->users['kelola-hr']?->id,
                    'status' => 'disetujui',
                    'catatan' => 'Approval payroll periode '.$periode,
                    'actioned_at' => now(),
                    'actioned_by' => $this->users['finance-approver']?->id ?? $this->users['finance']?->id,
                ]
            );
        }
    }

    private function seedKomisiSettlement(): void
    {
        // Komisi reseller
        $reseller = Pelanggan::where('is_reseller', true)->first();
        if ($reseller) {
            $transaksis = Transaksi::where('pelanggan_id', $reseller->id)->where('status', 'selesai')->get();
            foreach ($transaksis as $trx) {
                $this->komisiService->hitungKomisi($trx, $reseller);
            }
        }

        // Komisi teknisi (dari tiket servis selesai)
        $tikets = TiketServis::where('status', 'selesai')->whereHas('teknisi')->get();
        foreach ($tikets as $tiket) {
            if ($tiket->teknisi) {
                Komisi::firstOrCreate(
                    [
                        'komisi_skema_id' => KomisiSkema::where('aktor_tipe', 'karyawan')->where('trigger_tipe', 'tiket_servis')->first()?->id,
                        'aktor_id' => $tiket->teknisi->id,
                        'trigger_id' => $tiket->id,
                        'trigger_tipe' => 'tiket_servis',
                    ],
                    [
                        'nominal' => 50000,
                        'status' => 'menunggu_pembayaran',
                        'periode' => now()->format('Y-m'),
                        'catatan' => 'Integration seeder komisi teknisi tiket '.$tiket->no_tiket,
                    ]
                );
            }
        }

        // Komisi internal (marketing lead won)
        $leads = Lead::where('stage', 'won')->get();
        foreach ($leads as $lead) {
            if ($lead->assigned_to) {
                Komisi::firstOrCreate(
                    [
                        'komisi_skema_id' => KomisiSkema::where('aktor_tipe', 'karyawan')->where('trigger_tipe', 'lead_won')->first()?->id,
                        'aktor_id' => $lead->assigned_to,
                        'trigger_id' => $lead->id,
                        'trigger_tipe' => 'lead_won',
                    ],
                    [
                        'nominal' => 50000,
                        'status' => 'menunggu_pembayaran',
                        'periode' => now()->format('Y-m'),
                        'catatan' => 'Integration seeder komisi lead won',
                    ]
                );
            }
        }
    }

    private function seedPiutangUtang(): void
    {
        if (! $this->cabang1) {
            return;
        }

        // Piutang - kolom: jumlah, jumlah_dibayar, status enum: belum_lunas|sebagian|lunas (no tanggal column)
        $pelanggans = Pelanggan::take(5)->get();
        foreach ($pelanggans as $i => $p) {
            $jumlah = rand(500000, 5000000);
            $dibayar = rand(0, min(3000000, $jumlah));
            $status = $dibayar == 0 ? 'belum_lunas' : ($dibayar >= $jumlah ? 'lunas' : 'sebagian');

            Piutang::firstOrCreate(
                [
                    'pelanggan_id' => $p->id,
                    'no_piutang' => 'INT-PIU-'.now()->format('Ymd').'-'.sprintf('%03d', $i + 1),
                ],
                [
                    'transaksi_id' => null,
                    // Wajib: tanpa cabang_id, piutang disembunyikan oleh widget dashboard
                    // yang scoped cabang (P0-B) — selalu isi cabang aktif seeder.
                    'cabang_id' => $this->cabang1->id,
                    'jumlah' => $jumlah,
                    'jumlah_dibayar' => $dibayar,
                    'jatuh_tempo' => now()->addDays(rand(7, 60)),
                    'status' => $status,
                    'keterangan' => 'Integration seeder piutang aging',
                ]
            );
        }

        // Utang - kolom: jumlah, jumlah_dibayar, status enum: belum_lunas|sebagian|lunas (no supplier_id column)
        $suppliers = Supplier::take(3)->get();
        foreach ($suppliers as $i => $s) {
            $jumlah = rand(1000000, 10000000);
            $dibayar = rand(0, min(5000000, $jumlah));
            $status = $dibayar == 0 ? 'belum_lunas' : ($dibayar >= $jumlah ? 'lunas' : 'sebagian');

            Utang::firstOrCreate(
                [
                    'no_utang' => 'INT-UTG-'.now()->format('Ymd').'-'.sprintf('%03d', $i + 1),
                ],
                [
                    'referensi_tipe' => 'pembelian',
                    'referensi_id' => 0,
                    'cabang_id' => $this->cabang1->id,
                    'pelanggan_id' => null,
                    'kreditor_nama' => $s->nama,
                    'jumlah' => $jumlah,
                    'jumlah_dibayar' => $dibayar,
                    'jatuh_tempo' => now()->addDays(rand(7, 60)),
                    'status' => $status,
                    'keterangan' => 'Integration seeder utang aging',
                ]
            );
        }
    }

    private function seedNotifikasi(): void
    {
        $notifs = [
            ['tipe' => 'inapp', 'judul' => 'Integration: Selamat Datang', 'konten' => 'Selamat datang di Ute Parts ERP - data integrasi siap digunakan', 'status' => 'pending'],
            ['tipe' => 'inapp', 'judul' => 'Integration: PO Perlu Approval', 'konten' => 'PO Integration memerlukan persetujuan Finance', 'status' => 'pending'],
            ['tipe' => 'inapp', 'judul' => 'Integration: Payroll Periode Baru', 'konten' => 'Payroll periode '.now()->format('F Y').' siap diproses', 'status' => 'terkirim'],
            ['tipe' => 'inapp', 'judul' => 'Integration: Stok Menipis', 'konten' => 'Beberapa produk di bawah minimum stok', 'status' => 'pending'],
        ];

        foreach ($notifs as $n) {
            NotifikasiKeluar::firstOrCreate(
                ['judul' => $n['judul']],
                $n
            );
        }
    }

    private function seedMarketplaceOmnichannel(): void
    {
        // Channel Shopee
        $channel = Channel::firstOrCreate(
            ['platform' => 'shopee', 'nama' => 'Ute Parts Official — Shopee'],
            [
                'status' => 'terhubung',
                'kredensial' => ['shop_id' => '12345', 'access_token' => 'demo-token'],
                'is_active' => true,
            ]
        );

        ChannelOrder::firstOrCreate(
            ['channel_id' => $channel->id, 'channel_order_id' => 'INT-SHP-'.now()->timestamp],
            [
                'channel_status' => 'COMPLETED',
                'payload' => ['integration' => true, 'items' => [['item_id' => 'SKU-001', 'qty' => 2]]],
                'status' => 'selesai',
            ]
        );
    }

    private function seedAuditLogs(): void
    {
        // Sample audit log untuk F1-4 (menggunakan struktur spatie/activitylog)
        $activities = [
            ['event' => 'create', 'description' => 'Membuat PO Integration via seeder', 'subject_type' => PurchaseOrder::class, 'subject_id' => 0],
            ['event' => 'update', 'description' => 'Update status PO menjadi diterima', 'subject_type' => PurchaseOrder::class, 'subject_id' => 0],
            ['event' => 'approve', 'description' => 'Approval payroll periode '.now()->format('F Y'), 'subject_type' => PayrollPeriode::class, 'subject_id' => 0],
            ['event' => 'create', 'description' => 'Membuat tiket servis via integration', 'subject_type' => TiketServis::class, 'subject_id' => 0],
            ['event' => 'update_status', 'description' => 'Tiket servis berubah status menjadi selesai', 'subject_type' => TiketServis::class, 'subject_id' => 0],
        ];

        foreach ($activities as $a) {
            AktivitasLog::create([
                'log_name' => 'integration',
                'description' => $a['description'],
                'subject_type' => $a['subject_type'],
                'subject_id' => $a['subject_id'],
                'event' => $a['event'],
                'causer_type' => User::class,
                'causer_id' => $this->users['admin-toko']?->id,
                'properties' => ['integration' => true],
            ]);
        }
    }

    /**
     * Guard: cek apakah seeder sudah pernah jalan (untuk idempotency keseluruhan)
     */
    private function sudahAdaData(): bool
    {
        return Transaksi::where('no_transaksi', 'like', 'INT-%')->exists()
            || TiketServis::where('no_tiket', 'like', 'INT-%')->exists()
            || PurchaseOrder::where('no_po', 'like', 'INT-%')->exists();
    }
}
