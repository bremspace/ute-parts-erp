<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Livewire\AsetRegister;
use App\Modules\Akunting\Models\AkunCoa;
use App\Modules\Akunting\Models\AsetTetap;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Crm\Services\LeadService;
use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\ReturnPenjualanItem;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Livewire\ServisBoard;
use App\Modules\Servis\Services\ServisService;
use App\Modules\Wms\Livewire\PoTab;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\ReturnPembelian;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Workflow\Models\ApprovalRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StandarisasiFiturTambahTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang1;

    protected Cabang $cabang2;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin-toko', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'teknisi', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff-gudang', 'guard_name' => 'web']);

        Permission::firstOrCreate(['name' => 'servis.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'servis.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'wms.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'wms.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'user.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'user.view', 'guard_name' => 'web']);

        $this->cabang1 = Cabang::create([
            'nama' => 'Cabang Pusat',
            'kode' => 'CP01',
            'is_active' => true,
        ]);

        $this->cabang2 = Cabang::create([
            'nama' => 'Cabang Timur',
            'kode' => 'CT02',
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('super-admin');
        $this->adminUser->cabangs()->attach($this->cabang1->id, ['is_default' => true]);

        session(['cabang_id' => $this->cabang1->id]);

        $coas = [
            ['kode' => '110-01', 'nama' => 'Kas Toko', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit'],
            ['kode' => '110-02', 'nama' => 'Bank BCA', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit'],
            ['kode' => '110-04', 'nama' => 'Kas Laci Kasir', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit'],
            ['kode' => '120-01', 'nama' => 'Piutang Usaha', 'tipe' => 'aset', 'kelompok' => 'piutang', 'saldo_normal' => 'debit'],
            ['kode' => '130-01', 'nama' => 'Persediaan Barang Dagang', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit'],
            ['kode' => '210-01', 'nama' => 'Utang Usaha', 'tipe' => 'kewajiban', 'kelompok' => 'utang_usaha', 'saldo_normal' => 'kredit'],
            ['kode' => '410-01', 'nama' => 'Pendapatan Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '410-02', 'nama' => 'Retur Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'retur_penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '510-02', 'nama' => 'HPP', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit'],
        ];
        foreach ($coas as $c) {
            AkunCoa::firstOrCreate(['kode' => $c['kode']], $c);
        }
    }

    public function test_servis_board_quick_add_pelanggan_standardized(): void
    {
        ApprovalRule::firstOrCreate(
            ['entity_type' => 'member', 'level' => 1],
            [
                'cabang_id' => null,
                'min_amount' => 0,
                'max_amount' => null,
                'approver_role' => 'owner',
                'is_aktif' => true,
            ]
        );

        $teknisi = User::factory()->create();
        $teknisi->assignRole('teknisi');
        $teknisi->cabangs()->attach($this->cabang1->id, ['is_default' => true]);
        $teknisi->givePermissionTo('servis.view');
        $teknisi->givePermissionTo('servis.create');

        Livewire::actingAs($teknisi)
            ->test(ServisBoard::class)
            ->call('openPelangganBaruServis')
            ->set('pelangganBaruForm.nama', 'Pelanggan Servis Baru')
            ->set('pelangganBaruForm.telepon', '+6281299998888')
            ->set('pelangganBaruForm.tipe_konsumen', 'agen')
            ->set('pelangganBaruForm.kode_agen', 'AGN-SRV01')
            ->call('simpanPelangganBaruServis')
            ->assertHasNoErrors();

        $pelanggan = Pelanggan::where('telepon', '081299998888')->first();
        $this->assertNotNull($pelanggan);
        $this->assertEquals('Pelanggan Servis Baru', $pelanggan->nama);
        $this->assertEquals('agen', $pelanggan->tipe_konsumen);
        $this->assertEquals('AGN-SRV01', $pelanggan->kode_agen);
        $this->assertEquals('pending', $pelanggan->status); // Approval workflow triggered
    }

    public function test_po_tab_and_wms_controller_supplier_creation_standardized(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff-gudang');
        $staff->cabangs()->attach($this->cabang1->id, ['is_default' => true]);
        $staff->givePermissionTo('wms.view');
        $staff->givePermissionTo('wms.create');

        // Test Livewire PoTab simpanSupplier
        Livewire::actingAs($staff)
            ->test(PoTab::class)
            ->set('supplierForm.nama', 'PT Sumber Jaya')
            ->set('supplierForm.kontak', 'Pak Budi')
            ->set('supplierForm.telepon', '0812345678')
            ->set('supplierForm.alamat', 'Jl. Industri No 5')
            ->set('supplierForm.termin_hari', 45)
            ->call('simpanSupplier')
            ->assertHasNoErrors();

        $supplier = Supplier::where('nama', 'PT Sumber Jaya')->first();
        $this->assertNotNull($supplier);
        $this->assertEquals('Pak Budi', $supplier->kontak);
        $this->assertEquals(45, $supplier->termin_hari);

        // Test API storeSupplier with alias termin_hutang_hari
        $response = $this->actingAs($staff)->postJson('/api/wms/supplier', [
            'nama' => 'CV Mega Parts',
            'kontak' => 'Ibu Siti',
            'telepon' => '0823456789',
            'alamat' => 'Jl. Niaga No 10',
            'termin_hutang_hari' => 60,
        ]);

        $response->assertStatus(201);
        $supplierApi = Supplier::where('nama', 'CV Mega Parts')->first();
        $this->assertNotNull($supplierApi);
        $this->assertEquals('Ibu Siti', $supplierApi->kontak);
        $this->assertEquals(60, $supplierApi->termin_hari);
    }

    public function test_wms_controller_store_po_persists_satuan_and_jumlah_dasar(): void
    {
        $gudang = Gudang::create([
            'nama' => 'Gudang Pusat',
            'kode' => 'GDP01',
            'cabang_id' => $this->cabang1->id,
            'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'nama' => 'Distributor Utama',
            'termin_hari' => 30,
        ]);

        $produk = Produk::create([
            'nama' => 'LCD Samsung A12',
            'satuan_unit' => 'pcs',
            'harga_beli' => 150000,
            'harga_jual_retail' => 200000,
        ]);

        $response = $this->actingAs($this->adminUser)->postJson('/api/wms/po', [
            'supplier_id' => $supplier->id,
            'gudang_tujuan_id' => $gudang->id,
            'metode_bayar' => 'kredit',
            'items' => [
                [
                    'produk_id' => $produk->id,
                    'satuan' => 'box',
                    'konversi' => 10,
                    'harga_beli' => 1400000,
                    'jumlah' => 2,
                ],
            ],
        ]);

        $response->assertStatus(201);

        $po = PurchaseOrder::with('items')->latest()->first();
        $this->assertNotNull($po);
        $this->assertCount(1, $po->items);
        $item = $po->items->first();
        $this->assertEquals('box', $item->satuan);
        $this->assertEquals(10, (float) $item->konversi);
        $this->assertEquals(2, (float) $item->jumlah);
        $this->assertEquals(20, (float) $item->jumlah_dasar); // 2 box * 10 = 20 pcs
    }

    public function test_wms_controller_creation_enforces_cabang_scoping(): void
    {
        // Gudang 2 belongs to Cabang 2
        $gudang2 = Gudang::create([
            'nama' => 'Gudang Cabang 2',
            'kode' => 'GDC02',
            'cabang_id' => $this->cabang2->id,
            'is_active' => true,
        ]);

        $staffCabang1 = User::factory()->create();
        $staffCabang1->assignRole('staff-gudang');
        $staffCabang1->cabangs()->attach($this->cabang1->id, ['is_default' => true]);

        // Set session to Cabang 1
        session(['cabang_id' => $this->cabang1->id]);

        // Attempt to create rak in Gudang Cabang 2
        $responseRak = $this->actingAs($staffCabang1)->postJson('/api/wms/rak', [
            'gudang_id' => $gudang2->id,
            'nama' => 'Rak Cross Branch',
            'kode' => 'RAK-CB-01',
        ]);
        $responseRak->assertStatus(403);

        // Attempt to start opname in Gudang Cabang 2
        $responseOpname = $this->actingAs($staffCabang1)->postJson('/api/wms/opname', [
            'gudang_id' => $gudang2->id,
        ]);
        $responseOpname->assertStatus(403);
    }

    public function test_rbac_controller_store_user_assigns_default_branch(): void
    {
        $this->adminUser->givePermissionTo('user.create');

        $response = $this->actingAs($this->adminUser)->postJson('/api/users', [
            'name' => 'User Baru API',
            'email' => 'user.baru@example.com',
            'password' => 'secret1234',
            'role' => 'staff-gudang',
            'cabang_ids' => [$this->cabang1->id, $this->cabang2->id],
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'user.baru@example.com')->first();
        $this->assertNotNull($user);

        // Verify default branch flag on pivot
        $defaultCabang = $user->cabangDefault();
        $this->assertNotNull($defaultCabang);
        $this->assertEquals($this->cabang1->id, $defaultCabang->id);
    }

    public function test_crm_controller_store_tier_syncs_with_konfigurasi(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/api/crm/tiers', [
            'nama' => 'Gold Special',
            'kode' => 'gold',
            'min_belanja_12bulan' => 7500000,
            'diskon_persen' => 9.5,
            'poin_multiplier' => 1.8,
            'poin_redeem_rupiah' => 120,
        ]);

        $response->assertStatus(201);

        $tier = TierMembership::where('kode', 'gold')->first();
        $this->assertNotNull($tier);
        $this->assertEquals(9.5, (float) $tier->diskon_persen);
        $this->assertEquals(120, (int) $tier->poin_redeem_rupiah);

        $config = app(KonfigurasiService::class);
        $this->assertEquals(9.5, (float) $config->get('diskon_gold'));
    }

    public function test_return_penjualan_controller_store_accepts_extended_parameters(): void
    {
        Permission::firstOrCreate(['name' => 'pos.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'pos.view', 'guard_name' => 'web']);
        $this->adminUser->givePermissionTo('pos.create');
        $this->adminUser->givePermissionTo('pos.view');

        $coas = [
            ['kode' => '110-01', 'nama' => 'Kas Toko', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit'],
            ['kode' => '120-01', 'nama' => 'Piutang Usaha', 'tipe' => 'aset', 'kelompok' => 'piutang', 'saldo_normal' => 'debit'],
            ['kode' => '130-01', 'nama' => 'Persediaan Barang Dagang', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit'],
            ['kode' => '210-01', 'nama' => 'Utang Usaha', 'tipe' => 'kewajiban', 'kelompok' => 'utang_usaha', 'saldo_normal' => 'kredit'],
            ['kode' => '410-01', 'nama' => 'Pendapatan Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '410-02', 'nama' => 'Retur Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'retur_penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '510-02', 'nama' => 'HPP', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit'],
        ];
        foreach ($coas as $c) {
            AkunCoa::firstOrCreate(['kode' => $c['kode']], $c);
        }

        $gudang = Gudang::create([
            'nama' => 'Gudang Kasir',
            'kode' => 'GDK01',
            'cabang_id' => $this->cabang1->id,
            'is_active' => true,
        ]);

        $pelanggan = Pelanggan::create([
            'nama' => 'Budi Customer',
            'telepon' => '081234567890',
            'status' => 'aktif',
            'tipe_konsumen' => 'retail',
        ]);

        $produk = Produk::create([
            'nama' => 'Baterai iPhone 11',
            'satuan_unit' => 'pcs',
            'harga_beli' => 100000,
            'harga_jual_retail' => 200000,
        ]);

        $supplier = Supplier::create([
            'nama' => 'Supplier Baterai',
            'telepon' => '+628999888777',
            'termin_hari' => 14,
        ]);

        $transaksi = Transaksi::create([
            'no_transaksi' => 'TRX-TEST-001',
            'cabang_id' => $this->cabang1->id,
            'kasir_id' => $this->adminUser->id,
            'pelanggan_id' => $pelanggan->id,
            'gudang_id' => $gudang->id,
            'subtotal' => 200000,
            'total_akhir' => 200000,
            'metode_bayar' => 'cash',
            'jumlah_bayar' => 200000,
            'kembalian' => 0,
            'status' => 'selesai',
        ]);

        $trxItem = TransaksiItem::create([
            'transaksi_id' => $transaksi->id,
            'produk_id' => $produk->id,
            'jumlah' => 1,
            'harga_satuan' => 200000,
            'harga_final' => 200000,
            'hpp' => 100000,
            'subtotal' => 200000,
        ]);

        $response = $this->actingAs($this->adminUser)->postJson('/api/pos/retur', [
            'transaksi_id' => $transaksi->id,
            'alasan' => 'Baterai kembung setelah unboxing',
            'metode_pengembalian' => 'kas',
            'tipe_penyelesaian' => 'refund',
            'channel' => 'pos',
            'catatan_qc' => 'Fisik cacat dari pabrik',
            'items' => [
                [
                    'transaksi_item_id' => $trxItem->id,
                    'jumlah' => 1,
                    'kondisi_barang' => 'rusak',
                    'supplier_id' => $supplier->id,
                ],
            ],
        ]);

        $response->assertStatus(201);

        $retur = ReturnPenjualan::with('items')->latest()->first();
        $this->assertNotNull($retur);
        $this->assertEquals('refund', $retur->tipe_penyelesaian);
        $this->assertEquals('Fisik cacat dari pabrik', $retur->catatan_qc);
        $this->assertCount(1, $retur->items);
        $item = $retur->items->first();
        $this->assertEquals('rusak', $item->kondisi_barang);
        $this->assertEquals($supplier->id, $item->supplier_id);
    }

    public function test_lead_service_normalizes_phone_and_converts_to_pelanggan(): void
    {
        $leadService = app(LeadService::class);

        $lead = $leadService->create([
            'cabang_id' => $this->cabang1->id,
            'nama' => 'Lead Calon Agen',
            'telepon' => '+6287711223344',
            'email' => 'calon.agen@example.com',
            'sumber' => 'walkin',
            'stage' => 'won',
            'nilai_estimasi' => 5000000,
        ]);

        $this->assertEquals('087711223344', $lead->telepon);

        $pelanggan = $leadService->convertToPelanggan($lead);
        $this->assertNotNull($pelanggan);
        $this->assertEquals('087711223344', $pelanggan->telepon);
        $this->assertEquals('Lead Calon Agen', $pelanggan->nama);
        $this->assertEquals($pelanggan->id, $lead->fresh()->pelanggan_id);
    }

    public function test_servis_service_terima_unit_populates_customer_fallback_and_normalizes_phone(): void
    {
        $pelanggan = Pelanggan::create([
            'nama' => 'Joko Toko',
            'telepon' => '+6285566778899',
            'status' => 'aktif',
            'tipe_konsumen' => 'retail',
        ]);

        $servisService = app(ServisService::class);
        $tiket = $servisService->terimaUnit([
            'cabang_id' => $this->cabang1->id,
            'pelanggan_id' => $pelanggan->id,
            'jenis_hp' => 'iPhone 13',
            'keluhan' => 'Layar bergaris',
        ], $this->adminUser);

        $this->assertEquals('Joko Toko', $tiket->nama_pelanggan);
        $this->assertEquals('085566778899', $tiket->telepon_pelanggan);
    }

    public function test_aset_register_simpan_alert_contains_nama_aset(): void
    {
        Permission::firstOrCreate(['name' => 'akunting.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'akunting.view', 'guard_name' => 'web']);
        $this->adminUser->givePermissionTo('akunting.create');
        $this->adminUser->givePermissionTo('akunting.view');

        session(['cabang_id' => $this->cabang1->id]);

        Livewire::actingAs($this->adminUser)
            ->test(AsetRegister::class)
            ->call('toggleForm')
            ->set('form.nama', 'Mesin Blower Quick 857DW+')
            ->set('form.kategori', 'peralatan')
            ->set('form.harga_perolehan', '1.500.000')
            ->set('form.tanggal_perolehan', '2026-01-15')
            ->set('form.umur_bulan', 24)
            ->call('simpan')
            ->assertHasNoErrors()
            ->assertDispatched('alert', function ($event, $params) {
                $payload = $params[0] ?? $params;

                return ($payload['type'] ?? '') === 'success'
                    && str_contains($payload['message'] ?? '', 'Mesin Blower Quick 857DW+');
            });

        $aset = AsetTetap::where('nama', 'Mesin Blower Quick 857DW+')->first();
        $this->assertNotNull($aset);
        $this->assertEquals(1500000, (float) $aset->harga_perolehan);
        $this->assertEquals($this->cabang1->id, $aset->cabang_id);
    }

    public function test_pos_controller_store_handles_multi_satuan_and_bank_journal(): void
    {
        Permission::firstOrCreate(['name' => 'pos.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'pos.view', 'guard_name' => 'web']);
        $this->adminUser->givePermissionTo('pos.create');
        $this->adminUser->givePermissionTo('pos.view');

        $coas = [
            ['kode' => '110-02', 'nama' => 'Bank BCA', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit'],
            ['kode' => '110-04', 'nama' => 'Kas Laci Kasir', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit'],
            ['kode' => '130-01', 'nama' => 'Persediaan Barang Dagang', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit'],
            ['kode' => '410-01', 'nama' => 'Pendapatan Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '510-02', 'nama' => 'HPP', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit'],
        ];
        foreach ($coas as $c) {
            AkunCoa::firstOrCreate(['kode' => $c['kode']], $c);
        }

        $gudang = Gudang::create([
            'nama' => 'Gudang POS CP',
            'kode' => 'G-POS-01',
            'cabang_id' => $this->cabang1->id,
            'is_active' => true,
        ]);

        $produk = Produk::create([
            'nama' => 'Kabel Data Type-C Fast Charge',
            'satuan_unit' => 'pcs',
            'harga_beli' => 15000,
            'harga_jual_retail' => 30000,
        ]);

        StokItem::create([
            'produk_id' => $produk->id,
            'gudang_id' => $gudang->id,
            'jumlah' => 100, // 100 pcs tersedia
        ]);

        // Customer buys 2 box (1 box = 10 pcs, konversi = 10 -> total 20 pcs dasar) via QRIS
        $response = $this->actingAs($this->adminUser)->postJson('/api/pos/transaksi', [
            'gudang_id' => $gudang->id,
            'metode_bayar' => 'qris',
            'jumlah_bayar' => 500000,
            'items' => [
                [
                    'produk_id' => $produk->id,
                    'satuan' => 'box',
                    'konversi' => 10,
                    'jumlah' => 2,
                    'harga_satuan' => 250000,
                ],
            ],
        ]);

        $response->assertStatus(201);

        $transaksi = Transaksi::with('items')->latest()->first();
        $this->assertNotNull($transaksi);
        $this->assertEquals(500000, (float) $transaksi->total_akhir);
        $item = $transaksi->items->first();
        $this->assertEquals('box', $item->satuan);
        $this->assertEquals(10, (float) $item->konversi);
        $this->assertEquals(2, (float) $item->jumlah);

        // Verify stock reduced by 20 pcs (100 - 20 = 80 pcs)
        $sisaStok = StokItem::where('produk_id', $produk->id)
            ->where('gudang_id', $gudang->id)
            ->value('jumlah');
        $this->assertEquals(80, (int) $sisaStok);

        // Verify journal debits 110-02 (Bank) because payment is QRIS, not 110-04 (Kas Laci)
        $jurnal = JurnalAkuntansi::where('no_jurnal', 'like', 'JRL-POS%')
            ->where('akun_coa_id', AkunCoa::where('kode', '110-02')->value('id'))
            ->first();
        $this->assertNotNull($jurnal);
        $this->assertEquals(500000, (float) $jurnal->debit);
    }

    public function test_return_pembelian_controller_store_pool_supplier(): void
    {
        Permission::firstOrCreate(['name' => 'wms.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'wms.view', 'guard_name' => 'web']);
        $this->adminUser->givePermissionTo('wms.create');
        $this->adminUser->givePermissionTo('wms.view');

        $gudang = Gudang::create([
            'nama' => 'Gudang Pusat WMS',
            'kode' => 'G-WMS-01',
            'cabang_id' => $this->cabang1->id,
            'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'nama' => 'Supplier Pool Karantina',
            'telepon' => '081234567899',
            'termin_hari' => 30,
        ]);

        $produk = Produk::create([
            'nama' => 'Baterai Kembung Pool',
            'satuan_unit' => 'pcs',
            'harga_beli' => 80000,
            'harga_jual_retail' => 150000,
        ]);

        $returJual = ReturnPenjualan::create([
            'no_return' => 'RTJ-DUMMY-01',
            'cabang_id' => $this->cabang1->id,
            'gudang_id' => $gudang->id,
            'user_id' => $this->adminUser->id,
            'tanggal' => now()->toDateString(),
            'jumlah' => 150000,
            'status' => 'selesai',
            'alasan' => 'Barang rusak',
        ]);

        $poolItem = ReturnPenjualanItem::create([
            'return_penjualan_id' => $returJual->id,
            'produk_id' => $produk->id,
            'jumlah' => 2,
            'harga_satuan' => 150000,
            'subtotal' => 300000,
            'hpp' => 80000,
            'kondisi_barang' => 'rusak',
            'supplier_id' => $supplier->id,
            'status_karantina' => 'karantina',
        ]);

        $response = $this->actingAs($this->adminUser)->postJson('/api/wms/retur-pembelian', [
            'supplier_id' => $supplier->id,
            'alasan' => 'Retur batch klaim garansi vendor',
            'metode_pengembalian' => 'saldo',
            'item_ids' => [$poolItem->id],
        ]);

        $response->assertStatus(201);

        $returBeli = ReturnPembelian::with('items')->latest()->first();
        $this->assertNotNull($returBeli);
        $this->assertEquals($supplier->id, $returBeli->supplier_id);
        $this->assertEquals('saldo', $returBeli->metode_pengembalian);
        $this->assertCount(1, $returBeli->items);
        $this->assertEquals('sudah_diretur_supplier', $poolItem->fresh()->status_karantina);
    }
}
