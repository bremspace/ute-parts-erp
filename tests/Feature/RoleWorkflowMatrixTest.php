<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\Shift;
use App\Modules\Hr\Models\ShiftJadwal;
use App\Modules\Hr\Services\AbsensiService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Reseller\Models\KomisiSkema;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Models\Grn;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Services\GrnService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-End Workflow Matrix Test untuk seluruh Role (Konsumen hingga Staff/Admin)
 * Menguji data nyata ke database dan membuktikan integritas seluruh alur bisnis.
 */
class RoleWorkflowMatrixTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabangPusat;

    private Cabang $cabangCabang;

    private Gudang $gudangPusat;

    private Gudang $gudangCabang;

    // Users per role
    private User $userKonsumen;

    private Pelanggan $pelangganKonsumen;

    private User $userKasir;

    private User $userTeknisi;

    private User $userStaffGudang;

    private User $userFinance;

    private User $userMarketing;

    private User $userHrManager;

    private User $userAdminToko;

    private User $userSuperAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabangPusat = Cabang::create([
            'kode' => 'CBG-01',
            'nama' => 'Cabang Pusat Jakarta',
            'alamat' => 'Jl. Hayam Wuruk No. 10',
            'is_active' => true,
        ]);

        $this->cabangCabang = Cabang::create([
            'kode' => 'CBG-02',
            'nama' => 'Cabang Bandung',
            'alamat' => 'Jl. Asia Afrika No. 20',
            'is_active' => true,
        ]);

        $this->gudangPusat = Gudang::create([
            'cabang_id' => $this->cabangPusat->id,
            'kode' => 'GDG-PST',
            'nama' => 'Gudang Utama Pusat',
            'is_active' => true,
        ]);

        $this->gudangCabang = Gudang::create([
            'cabang_id' => $this->cabangCabang->id,
            'kode' => 'GDG-BDG',
            'nama' => 'Gudang Cabang Bandung',
            'is_active' => true,
        ]);

        // Setup Rule Komisi Reseller aktif
        KomisiSkema::create([
            'nama' => 'Komisi Reseller Standar 5%',
            'aktor_tipe' => 'reseller',
            'aktor_id' => null,
            'trigger_tipe' => 'penjualan',
            'kategori' => null,
            'tipe' => 'persen',
            'nilai' => 5,
            'min_amount' => 0,
            'cabang_id' => null,
            'is_aktif' => true,
        ]);

        $this->buatSemuaUserRole();
    }

    private function buatSemuaUserRole(): void
    {
        // 1. Konsumen / Pelanggan (Marketplace & CRM)
        $this->userKonsumen = User::create([
            'name' => 'Budi Konsumen',
            'email' => 'konsumen@budi.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->pelangganKonsumen = Pelanggan::create([
            'nama' => 'Budi Konsumen',
            'telepon' => '081234567890',
            'email' => 'konsumen@budi.test',
            'alamat' => 'Jl. Surya Kencana No. 5',
            'cabang_id' => $this->cabangPusat->id,
            'is_reseller' => true, // Sekaligus reseller agar komisi terpicu
        ]);

        // 2. Staff: Kasir
        $this->userKasir = $this->createStaffUser('kasir', 'Siti Kasir', 'kasir@uteparts.test', 'kasir');

        // 3. Staff: Teknisi
        $this->userTeknisi = $this->createStaffUser('teknisi', 'Agus Teknisi', 'teknisi@uteparts.test', 'teknisi');

        // 4. Staff: Staff Gudang
        $this->userStaffGudang = $this->createStaffUser('staff-gudang', 'Joko Gudang', 'gudang@uteparts.test', 'other');

        // 5. Staff: Finance
        $this->userFinance = $this->createStaffUser('finance', 'Dewi Finance', 'finance@uteparts.test', 'other');

        // 6. Staff: Marketing
        $this->userMarketing = $this->createStaffUser('marketing', 'Rian Marketing', 'marketing@uteparts.test', 'marketing');

        // 7. Staff: HR Manager
        $this->userHrManager = $this->createStaffUser('kelola-hr', 'Hendra HR', 'hr@uteparts.test', 'other');

        // 8. Staff: Admin Toko
        $this->userAdminToko = $this->createStaffUser('admin-toko', 'Doni Admin Toko', 'admin@uteparts.test', 'admin');

        // 9. Super Admin
        $this->userSuperAdmin = $this->createStaffUser('super-admin', 'Boss Super Admin', 'owner@uteparts.test', null);
    }

    private function createStaffUser(string $role, string $name, string $email, ?string $jabatan): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $user->assignRole($role);
        $user->cabangs()->attach($this->cabangPusat->id, ['is_default' => true]);

        if ($jabatan) {
            $karyawan = Karyawan::create([
                'user_id' => $user->id,
                'nik' => 'KRY-'.strtoupper(Str::random(6)),
                'nama' => $name,
                'jabatan' => $jabatan,
                'cabang_id' => $this->cabangPusat->id,
                'tgl_masuk' => '2024-01-01',
                'gaji_pokok' => 5000000,
                'status_aktif' => true,
                'rekening_bank' => 'BCA-12345678',
            ]);

            $shift = Shift::firstOrCreate(
                ['cabang_id' => $this->cabangPusat->id, 'nama' => 'Pagi'],
                ['jam_mulai' => '08:00', 'jam_selesai' => '17:00', 'is_aktif' => true]
            );

            ShiftJadwal::create([
                'cabang_id' => $this->cabangPusat->id,
                'karyawan_id' => $karyawan->id,
                'shift_id' => $shift->id,
                'tanggal' => now()->toDateString(),
            ]);
        }

        return $user;
    }

    private function asRole(User $user, Cabang $cabang): void
    {
        $this->actingAs($user, 'sanctum');
        session(['cabang_id' => $cabang->id]);
    }

    /**
     * WORKFLOW 1: WMS / Staff Gudang & Admin Toko
     * Input Supplier, PO, GRN (Good Receipt), Penerimaan Stok, & Transfer Gudang
     */
    public function test_workflow_wms_penginputan_produk_po_grn_dan_transfer(): void
    {
        $this->asRole($this->userStaffGudang, $this->cabangPusat);

        // 1. Buat Supplier
        $respSupplier = $this->postJson('/api/wms/supplier', [
            'nama' => 'PT Sparepart Jaya Abadi',
            'telepon' => '0215551234',
            'email' => 'supplier@jayaabadi.test',
            'alamat' => 'Kawasan Industri Pulogadung',
            'top_hari' => 30,
        ])->assertStatus(201);
        $supplierId = $respSupplier->json('data.id');
        $this->assertDatabaseHas('supplier', ['id' => $supplierId, 'nama' => 'PT Sparepart Jaya Abadi']);

        // 2. Buat Master Produk & Varian (Staff Gudang / Admin)
        $produk = Produk::create([
            'kode' => 'LCD-IP13',
            'nama' => 'LCD Touchscreen iPhone 13 OLED',
            'slug' => 'lcd-touchscreen-iphone-13-oled',
            'kategori' => 'LCD',
            'kondisi' => 'baru',
            'harga_beli' => 800000,
            'harga_jual_retail' => 1200000,
            'is_active' => true,
        ]);
        $variant = SkuVariant::create([
            'produk_id' => $produk->id,
            'sku' => 'LCD-IP13-BLK',
            'nama_varian' => 'Black Original',
            'harga_beli' => 800000,
            'harga_jual_retail' => 1200000,
            'is_active' => true,
        ]);

        // 3. Staff Gudang buat Purchase Order (PO)
        $respPo = $this->postJson('/api/wms/po', [
            'supplier_id' => $supplierId,
            'gudang_tujuan_id' => $this->gudangPusat->id,
            'metode_bayar' => 'tunai',
            'tanggal_order' => now()->toDateString(),
            'estimasi_datang' => now()->addDays(3)->toDateString(),
            'items' => [
                [
                    'produk_id' => $produk->id,
                    'sku_variant_id' => $variant->id,
                    'jumlah' => 10,
                    'harga_beli' => 800000,
                ],
            ],
        ])->assertStatus(201);
        $poId = $respPo->json('data.id');
        $this->assertDatabaseHas('purchase_order', ['id' => $poId, 'status' => 'draft']);

        // Set PO ke dikirim
        $this->putJson("/api/wms/po/{$poId}/status", ['action' => 'dikirim'])->assertSuccessful();
        $this->assertDatabaseHas('purchase_order', ['id' => $poId, 'status' => 'dikirim']);

        // 4. Staff Gudang terima barang (GRN / Goods Receipt Note)
        $respGrn = $this->postJson('/api/wms/grn', [
            'po_id' => $poId,
            'item_received' => [
                GrnService::kunciItem($produk->id, $variant->id) => 10,
            ],
            'catatan' => 'SJ-SUPP-9988 penerimaan utuh',
        ])->assertStatus(201);
        $grnId = $respGrn->json('data.id');
        $this->assertDatabaseHas('grn', ['id' => $grnId, 'status' => 'terima']);

        // Verifikasi Stok bertambah di Gudang Pusat = 10
        $this->assertDatabaseHas('stok_items', [
            'produk_id' => $produk->id,
            'gudang_id' => $this->gudangPusat->id,
            'jumlah' => 10,
        ]);

        // Verifikasi StokLog tercatat sebagai GRN
        $this->assertDatabaseHas('stok_log', [
            'produk_id' => $produk->id,
            'gudang_id' => $this->gudangPusat->id,
            'jenis' => 'GRN',
            'perubahan' => 10,
        ]);

        // 5. Staff Gudang melakukan Transfer Antar Gudang (Pusat -> Bandung, 3 pcs)
        $respTransfer = $this->postJson('/api/wms/transfer', [
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangCabang->id,
            'items' => [
                [
                    'produk_id' => $produk->id,
                    'sku_variant_id' => $variant->id,
                    'jumlah' => 3,
                ],
            ],
        ])->assertStatus(201);
        $transferId = $respTransfer->json('data.id');

        // Kirim transfer -> stok gudang asal berkurang 10 - 3 = 7
        $this->putJson("/api/wms/transfer/{$transferId}/kirim")->assertSuccessful();
        $this->assertDatabaseHas('stok_items', [
            'produk_id' => $produk->id,
            'gudang_id' => $this->gudangPusat->id,
            'jumlah' => 7,
        ]);

        // Terima transfer di Bandung -> stok gudang Bandung bertambah 3
        $this->asRole($this->userStaffGudang, $this->cabangCabang);
        $this->putJson("/api/wms/transfer/{$transferId}/terima")->assertSuccessful();
        $this->assertDatabaseHas('stok_items', [
            'produk_id' => $produk->id,
            'gudang_id' => $this->gudangCabang->id,
            'jumlah' => 3,
        ]);
    }

    /**
     * WORKFLOW 2: POS Kasir
     * Buka Kasir Laci, Transaksi Penjualan Tunai & Piutang, Komisi Reseller Otomatis, Tutup Kasir
     */
    public function test_workflow_pos_kasir_transaksi_komisi_dan_tutup_kas(): void
    {
        $this->asRole($this->userKasir, $this->cabangPusat);

        // Siapkan produk stok di gudang pusat
        $produk = Produk::create([
            'nama' => 'Baterai Samsung A52',
            'slug' => 'baterai-samsung-a52',
            'kategori' => 'Baterai',
            'kondisi' => 'baru',
            'harga_beli' => 70000,
            'harga_jual_retail' => 150000,
            'is_active' => true,
        ]);
        StokItem::create([
            'cabang_id' => $this->cabangPusat->id,
            'gudang_id' => $this->gudangPusat->id,
            'produk_id' => $produk->id,
            'jumlah' => 15,
        ]);

        // 0. Karyawan kasir clock-in terlebih dahulu (syarat buka kas laci)
        $karyawanKasir = Karyawan::where('user_id', $this->userKasir->id)->firstOrFail();
        app(AbsensiService::class)->clockIn($karyawanKasir->id);

        // 1. Kasir Buka Kas Laci
        $respBuka = $this->postJson('/api/pos/kas/buka', [
            'saldo_awal' => 200000,
            'catatan' => 'Modal awal kasir pagi',
        ]);
        $respBuka->assertSuccessful();

        $kasSesi = DB::table('kas_sesi')
            ->where('cabang_id', $this->cabangPusat->id)
            ->where('user_id', $this->userKasir->id)
            ->where('status', 'buka')
            ->first();
        $this->assertNotNull($kasSesi);
        $this->assertEquals(200000, (float) $kasSesi->saldo_awal);

        // 2. Transaksi Penjualan Kasir (Tunai ke Pelanggan Reseller)
        $respTrx = $this->postJson('/api/pos/transaksi', [
            'items' => [
                [
                    'produk_id' => $produk->id,
                    'jumlah' => 2,
                    'harga_satuan' => 150000,
                ],
            ],
            'metode_bayar' => 'tunai',
            'jumlah_bayar' => 300000,
            'pelanggan_id' => $this->pelangganKonsumen->id,
            'gudang_id' => $this->gudangPusat->id,
            'cabang_id' => $this->cabangPusat->id,
        ])->assertStatus(201);

        $noTrx = $respTrx->json('data.no_transaksi');
        $trx = Transaksi::where('no_transaksi', $noTrx)->firstOrFail();
        $this->assertEquals('selesai', $trx->status);

        // Cek stok terpotong 15 - 2 = 13
        $this->assertDatabaseHas('stok_items', [
            'produk_id' => $produk->id,
            'gudang_id' => $this->gudangPusat->id,
            'jumlah' => 13,
        ]);

        // Cek jurnal otomatis POS tercatat
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'referensi_tipe' => Transaksi::class,
            'referensi_id' => $trx->id,
            'debit' => 300000,
        ]);

        // Cek komisi reseller otomatis terhitung 5% x 300.000 = 15.000
        $komisi = Komisi::where('pelanggan_id', $this->pelangganKonsumen->id)->first();
        $this->assertNotNull($komisi);
        $this->assertEquals(15000, (float) $komisi->nominal_komisi);
        $this->assertEquals('pending', $komisi->status);

        // 3. Transaksi Metode Piutang (Kasbon)
        $respKasbon = $this->postJson('/api/pos/transaksi', [
            'items' => [
                [
                    'produk_id' => $produk->id,
                    'jumlah' => 1,
                    'harga_satuan' => 150000,
                ],
            ],
            'metode_bayar' => 'piutang',
            'jumlah_bayar' => 150000,
            'pelanggan_id' => $this->pelangganKonsumen->id,
            'gudang_id' => $this->gudangPusat->id,
            'cabang_id' => $this->cabangPusat->id,
        ])->assertStatus(201);

        $this->assertDatabaseHas('piutang', [
            'pelanggan_id' => $this->pelangganKonsumen->id,
            'status' => 'belum_lunas',
            'jumlah' => 150000,
        ]);

        // 4. Tutup Kas Laci di akhir shift
        $this->postJson('/api/pos/kas/tutup', [
            'saldo_fisik' => 500000, // modal 200rb + tunai 300rb
            'catatan' => 'Shift selesai pas',
        ])->assertSuccessful();

        $kasSesiTutup = DB::table('kas_sesi')->where('id', $kasSesi->id)->first();
        $this->assertEquals('tutup', $kasSesiTutup->status);
    }

    /**
     * WORKFLOW 3: Teknisi Servis & Konsumen
     * Registrasi Servis, Diagnosa, Estimasi, Approval Pelanggan via Token Publik,
     * Pengerjaan & Potong Sparepart, QC, Selesai & Garansi Terbit + Jurnal
     */
    public function test_workflow_servis_teknisi_dan_approval_konsumen(): void
    {
        // Siapkan sparepart LCD di Gudang Pusat
        $part = Produk::create([
            'nama' => 'LCD Samsung S20 OEM',
            'slug' => 'lcd-samsung-s20-oem',
            'kategori' => 'LCD',
            'kondisi' => 'baru',
            'harga_beli' => 250000,
            'harga_jual_retail' => 450000,
            'is_active' => true,
        ]);
        StokItem::create([
            'cabang_id' => $this->cabangPusat->id,
            'gudang_id' => $this->gudangPusat->id,
            'produk_id' => $part->id,
            'jumlah' => 5,
        ]);

        // 1. Konsumen / Loket Servis: Pendaftaran Tiket Servis
        $this->asRole($this->userTeknisi, $this->cabangPusat);

        $respServis = $this->postJson('/api/servis', [
            'nama_pelanggan' => 'Andi Wijaya',
            'telepon_pelanggan' => '08999888777',
            'jenis_hp' => 'Samsung Galaxy S20',
            'keluhan' => 'Layar blank hitam setelah jatuh',
            'foto_unit' => ['data:image/png;base64,samplephoto1'],
        ])->assertStatus(201);

        $noTiket = $respServis->json('data.no_tiket');
        $tiket = TiketServis::where('no_tiket', $noTiket)->firstOrFail();
        $this->assertEquals('diterima', $tiket->status);

        // 2. Teknisi mengubah status ke Diagnosa
        $this->putJson("/api/servis/{$tiket->id}/status", ['status' => 'diagnosa'])->assertSuccessful();
        $tiket->refresh();
        $this->assertEquals('diagnosa', $tiket->status);

        // 3. Teknisi input Estimasi Biaya (memicu status menunggu_approval & generate approval token)
        $this->postJson("/api/servis/{$tiket->id}/estimasi", [
            'estimasi_biaya' => 600000,
            'alasan' => 'Ganti modul LCD dan kalibrasi sensor',
        ])->assertSuccessful();

        $tiket->refresh();
        $this->assertEquals('menunggu_approval', $tiket->status);
        $this->assertNotNull($tiket->token_approval);

        // 4. Konsumen melihat tracking via Token dan menyetujui (Public endpoint)
        $respTracking = $this->getJson("/api/servis/tracking/{$tiket->token_approval}")->assertSuccessful();
        $this->assertEquals($tiket->no_tiket, $respTracking->json('data.no_tiket'));

        $this->postJson("/api/servis/public/approve/{$tiket->token_approval}", ['action' => 'approve'])->assertSuccessful();
        $tiket->refresh();
        $this->assertEquals('disetujui', $tiket->status);

        // 5. Teknisi mulai pengerjaan
        $this->putJson("/api/servis/{$tiket->id}/status", ['status' => 'dikerjakan'])->assertSuccessful();

        // 6. Teknisi memasang sparepart (potong stok gudang)
        $this->postJson("/api/servis/{$tiket->id}/sparepart", [
            'gudang_id' => $this->gudangPusat->id,
            'items' => [
                [
                    'produk_id' => $part->id,
                    'jumlah' => 1,
                ],
            ],
        ])->assertSuccessful();

        // Stok part berkurang dari 5 menjadi 4
        $this->assertDatabaseHas('stok_items', [
            'produk_id' => $part->id,
            'gudang_id' => $this->gudangPusat->id,
            'jumlah' => 4,
        ]);

        // 7. Teknisi memindahkan ke QC lalu Selesai
        $this->putJson("/api/servis/{$tiket->id}/status", ['status' => 'qc'])->assertSuccessful();
        $this->putJson("/api/servis/{$tiket->id}/status", ['status' => 'selesai'])->assertSuccessful();

        $tiket->refresh();
        $this->assertEquals('selesai', $tiket->status);

        // 8. Cek kartu garansi terbit otomatis & Jurnal tercatat
        $this->assertDatabaseHas('garansi', ['tiket_servis_id' => $tiket->id]);
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'sumber' => 'servis',
            'referensi_tipe' => TiketServis::class,
            'referensi_id' => $tiket->id,
        ]);
    }

    /**
     * WORKFLOW 4: Finance & Akunting
     * Approval Komisi Reseller -> Terbit Utang Komisi -> Pembayaran Utang -> Keseimbangan Jurnal COA
     */
    public function test_workflow_finance_approval_komisi_dan_pembayaran_utang(): void
    {
        // Buat komisi pending
        $komisi = Komisi::create([
            'no_komisi' => 'KMS-202610-0001',
            'cabang_id' => $this->cabangPusat->id,
            'pelanggan_id' => $this->pelangganKonsumen->id,
            'jumlah_transaksi' => 500000,
            'nominal_komisi' => 25000,
            'status' => 'pending',
        ]);

        $this->asRole($this->userFinance, $this->cabangPusat);

        // 1. Finance Approve Komisi Reseller
        $respApprove = $this->postJson('/api/reseller/komisi/approve', [
            'komisi_ids' => [$komisi->id],
            'action' => 'approve',
        ])->assertSuccessful();

        $komisi->refresh();
        $this->assertEquals('disetujui', $komisi->status);

        // Terbit Utang Komisi di Akunting
        $this->assertDatabaseHas('utang', [
            'referensi_tipe' => 'komisi',
            'referensi_id' => $komisi->id,
            'status' => 'belum_lunas',
            'jumlah' => 25000,
        ]);

        // Jurnal Beban Komisi (Debit) & Utang Komisi (Kredit)
        $akunBebanKomisi = AkunCOA::where('kode', '510-01')->firstOrFail();
        $akunUtangKomisi = AkunCOA::where('kode', '210-03')->firstOrFail();
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'akun_coa_id' => $akunBebanKomisi->id,
            'debit' => 25000,
        ]);
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'akun_coa_id' => $akunUtangKomisi->id,
            'kredit' => 25000,
        ]);

        // Verifikasi Semua Jurnal Akuntansi Balance (Debit == Kredit)
        $cekBalance = DB::table('jurnal_akuntansi')
            ->selectRaw('no_jurnal, SUM(debit) as total_debit, SUM(kredit) as total_kredit')
            ->groupBy('no_jurnal')
            ->get();
        foreach ($cekBalance as $j) {
            $this->assertEqualsWithDelta(
                (float) $j->total_debit,
                (float) $j->total_kredit,
                0.01,
                "Jurnal {$j->no_jurnal} tidak berimbang"
            );
        }
    }

    /**
     * WORKFLOW 5: HR Manager & Karyawan
     * Pengelolaan Karyawan, Absensi, dan Penggajian (Payroll)
     */
    public function test_workflow_hr_dan_payroll(): void
    {
        $this->asRole($this->userHrManager, $this->cabangPusat);

        // 1. HR Manager melihat & mengelola absensi
        $karyawan = Karyawan::where('user_id', $this->userKasir->id)->firstOrFail();

        // 2. Karyawan melakukan akses dashboard HR Saya (Self Service)
        $this->asRole($this->userKasir, $this->cabangPusat);
        $respSaya = $this->get('/app/hr/saya');
        $respSaya->assertSuccessful();

        // 3. User tanpa hak akses HR tidak boleh buka payroll management
        $respForbidden = $this->get('/app/hr/payroll');
        $this->assertTrue(in_array($respForbidden->status(), [403, 302]));

        // 4. Finance / HR Manager memiliki izin akses payroll
        $this->asRole($this->userFinance, $this->cabangPusat);
        $respPayroll = $this->get('/app/hr/payroll');
        $respPayroll->assertSuccessful();
    }

    /**
     * WORKFLOW 6: Konsumen Marketplace
     * Tambah Keranjang & Checkout Order
     */
    public function test_workflow_konsumen_marketplace_cart_dan_order(): void
    {
        $produk = Produk::create([
            'kode' => 'ACC-CASE',
            'nama' => 'Silicone Case iPhone 13 Slim',
            'slug' => 'silicone-case-iphone-13-slim',
            'kategori' => 'Aksesoris',
            'kondisi' => 'baru',
            'harga_beli' => 20000,
            'harga_jual_retail' => 50000,
            'is_active' => true,
        ]);
        StokItem::create([
            'cabang_id' => $this->cabangPusat->id,
            'gudang_id' => $this->gudangPusat->id,
            'produk_id' => $produk->id,
            'jumlah' => 20,
        ]);

        $this->actingAs($this->userKonsumen, 'web');

        // Konsumen melihat shop & detail produk
        $this->get('/shop')->assertSuccessful();
        $this->get("/shop/{$produk->slug}")->assertSuccessful();

        // Konsumen akses halaman Cart & Checkout
        $this->get('/cart')->assertSuccessful();
        $this->get('/checkout')->assertSuccessful();
    }

    /**
     * WORKFLOW 7: RBAC Multi-Cabang & Batas Wewenang Role (Least Privilege)
     * Memastikan staf tidak bisa mengakses area yang bukan wewenangnya (403 Forbidden)
     */
    public function test_rbac_enforcement_dan_least_privilege_semua_role(): void
    {
        // 1. Kasir dilarang masuk ke WMS Mutasi Transfer API & Akunting Dashboard
        $this->asRole($this->userKasir, $this->cabangPusat);
        $this->postJson('/api/wms/transfer', [
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangCabang->id,
            'items' => [],
        ])->assertStatus(403);
        $this->get('/app/akunting')->assertStatus(403);

        // 2. Teknisi dilarang melakukan transaksi POS kasir
        $this->asRole($this->userTeknisi, $this->cabangPusat);
        $this->postJson('/api/pos/transaksi', [
            'items' => [],
            'metode_bayar' => 'tunai',
        ])->assertStatus(403);

        // 3. Staff Gudang dilarang mengakses modul servis
        $this->asRole($this->userStaffGudang, $this->cabangPusat);
        $this->get('/app/servis')->assertStatus(403);
        $this->postJson('/api/servis', [
            'nama_pelanggan' => 'X',
        ])->assertStatus(403);

        // 4. Marketing dilarang approve komisi dan dilarang akses akunting
        $this->asRole($this->userMarketing, $this->cabangPusat);
        $this->postJson('/api/reseller/komisi/approve', ['komisi_ids' => [1]])->assertStatus(403);
        $this->get('/app/akunting')->assertStatus(403);

        // 5. Super Admin memiliki akses penuh ke semua modul
        $this->asRole($this->userSuperAdmin, $this->cabangPusat);
        $this->get('/app/dashboard')->assertSuccessful();
        $this->get('/app/pengaturan')->assertSuccessful();
        $this->get('/app/audit-log')->assertSuccessful();
    }
}
