<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Akunting\Services\PembayaranSubledgerService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Grn;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\PembayaranSupplier;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Services\GrnService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PengadaanWmsTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Gudang $gudang;

    private Supplier $supplier;

    private Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create(['nama' => 'Cabang Utama', 'kode' => 'C-01', 'is_active' => true]);
        $this->gudang = Gudang::create(['nama' => 'Gudang Pusat', 'kode' => 'G-01', 'cabang_id' => $this->cabang->id, 'is_active' => true]);
        $this->supplier = Supplier::create(['nama' => 'PT Sparepart Indo', 'telepon' => '0812345678', 'termin_hari' => 30]);
        $this->produk = Produk::create(['nama' => 'LCD iPhone 13', 'kode' => 'LCD-IP13', 'harga_beli' => 500000, 'harga_jual' => 800000, 'is_active' => true]);

        session(['cabang_id' => $this->cabang->id]);
    }

    private function buatUserDenganRole(string $role): User
    {
        $user = User::create([
            'name' => "User {$role}",
            'email' => "{$role}@test.com",
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole($role);
        $user->cabangs()->attach($this->cabang->id);

        return $user;
    }

    private function buatPo(string $metode = 'kredit', string $status = 'dikirim', int $qty = 2, float $harga = 500000): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'no_po' => 'PO-TEST-001',
            'supplier_id' => $this->supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'status' => $status,
            'metode_bayar' => $metode,
            'jatuh_tempo' => now()->addDays(30),
            'total' => $qty * $harga,
            'total_dibayar' => 0,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'produk_id' => $this->produk->id,
            'jumlah' => $qty,
            'harga_beli' => $harga,
            'subtotal' => $qty * $harga,
        ]);

        return $po;
    }

    public function test_staff_gudang_dilarang_bayar_po_lewat_api(): void
    {
        $staffGudang = $this->buatUserDenganRole('staff-gudang');
        $po = $this->buatPo('kredit', 'diterima');

        $this->actingAs($staffGudang)
            ->postJson("/api/wms/po/{$po->id}/bayar", ['jumlah' => 100000])
            ->assertStatus(403);
    }

    public function test_finance_berhasil_bayar_po_lewat_api_dan_buku_utang_terupdate(): void
    {
        $finance = $this->buatUserDenganRole('finance');
        $po = $this->buatPo('kredit', 'dikirim', 2, 500000);

        // Finalisasi via GRN
        $grn = app(GrnService::class)->inputGudang($po, [GrnService::kunciItem($this->produk->id, 0) => 2], $finance->id);
        $this->assertEquals('terima', $grn->status);
        $this->assertEquals('diterima', $po->fresh()->status);

        $utang = Utang::where('referensi_tipe', PurchaseOrder::class)->where('referensi_id', $po->id)->first();
        $this->assertNotNull($utang);
        $this->assertEquals(1000000, (float) $utang->jumlah);

        // Finance membayar PO
        $response = $this->actingAs($finance)
            ->postJson("/api/wms/po/{$po->id}/bayar", [
                'jumlah' => 400000,
                'akun_kas_bank' => '110-01',
            ]);

        $response->assertSuccessful();

        $po->refresh();
        $this->assertEquals(400000, (float) $po->total_dibayar);
        $this->assertEquals(600000, (float) $po->sisa);

        $utang->refresh();
        $this->assertEquals(400000, (float) $utang->jumlah_dibayar);
        $this->assertEquals('sebagian', $utang->status);

        // Verifikasi PembayaranSupplier & Jurnal
        $this->assertDatabaseHas('pembayaran_supplier', [
            'po_id' => $po->id,
            'jumlah' => 400000,
            'user_id' => $finance->id,
        ]);

        $akunUtang = AkunCOA::where('kode', '210-01')->firstOrFail();
        $akunKas = AkunCOA::where('kode', '110-01')->firstOrFail();

        $this->assertDatabaseHas('jurnal_akuntansi', [
            'akun_coa_id' => $akunUtang->id,
            'debit' => 400000,
        ]);
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'akun_coa_id' => $akunKas->id,
            'kredit' => 400000,
        ]);
    }

    public function test_pembayaran_utang_via_akunting_tersinkron_ke_po(): void
    {
        $finance = $this->buatUserDenganRole('finance');
        $po = $this->buatPo('kredit', 'dikirim', 2, 300000);

        app(GrnService::class)->inputGudang($po, [GrnService::kunciItem($this->produk->id, 0) => 2], $finance->id);
        $utang = Utang::where('referensi_tipe', PurchaseOrder::class)->where('referensi_id', $po->id)->firstOrFail();

        // Bayar via PembayaranSubledgerService
        app(PembayaranSubledgerService::class)->bayarUtang($utang->id, 200000, $this->cabang->id, $finance->id);

        $po->refresh();
        $this->assertEquals(200000, (float) $po->total_dibayar);
        $this->assertEquals(400000, (float) $po->sisa);

        $this->assertDatabaseHas('pembayaran_supplier', [
            'po_id' => $po->id,
            'jumlah' => 200000,
        ]);
    }

    public function test_grn_tunai_tidak_mencatat_utang_dan_kredit_kas(): void
    {
        $user = $this->buatUserDenganRole('super-admin');
        $po = $this->buatPo('tunai', 'dikirim', 1, 250000);

        $grn = app(GrnService::class)->inputGudang($po, [GrnService::kunciItem($this->produk->id, 0) => 1], $user->id);

        $this->assertEquals('terima', $grn->status);

        // Utang subledger tidak boleh dibuat untuk pembelian tunai
        $utangCount = Utang::where('referensi_tipe', PurchaseOrder::class)->where('referensi_id', $po->id)->count();
        $this->assertSame(0, $utangCount);

        // PO harus ditandai lunas (total_dibayar = total)
        $po->refresh();
        $this->assertEquals(250000, (float) $po->total_dibayar);
        $this->assertEquals(0, (float) $po->sisa);

        // Jurnal harus 130-01 D / 110-01 K
        $akunPersediaan = AkunCOA::where('kode', '130-01')->firstOrFail();
        $akunKas = AkunCOA::where('kode', '110-01')->firstOrFail();

        $this->assertDatabaseHas('jurnal_akuntansi', [
            'no_jurnal' => $grn->no_grn,
            'akun_coa_id' => $akunPersediaan->id,
            'debit' => 250000,
        ]);
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'no_jurnal' => $grn->no_grn,
            'akun_coa_id' => $akunKas->id,
            'kredit' => 250000,
        ]);
    }

    public function test_api_grn_endpoint_lengkap(): void
    {
        $user = $this->buatUserDenganRole('super-admin');
        $po = $this->buatPo('kredit', 'dikirim', 2, 100000);

        // POST /api/wms/grn
        $resStore = $this->actingAs($user)->postJson('/api/wms/grn', [
            'po_id' => $po->id,
            'item_received' => [
                GrnService::kunciItem($this->produk->id, 0) => 2,
            ],
            'catatan' => 'Penerimaan utuh',
        ]);

        $resStore->assertStatus(201);
        $grnId = $resStore->json('data.id');

        // GET /api/wms/grn
        $this->actingAs($user)->getJson('/api/wms/grn')->assertSuccessful();

        // GET /api/wms/grn/{id}
        $this->actingAs($user)->getJson("/api/wms/grn/{$grnId}")->assertSuccessful();

        // GET /api/wms/po/{id}
        $this->actingAs($user)->getJson("/api/wms/po/{$po->id}")->assertSuccessful();
    }

    public function test_grn_tunai_dengan_pilihan_akun_bank_kredit_bank(): void
    {
        $user = $this->buatUserDenganRole('super-admin');
        $po = $this->buatPo('tunai', 'dikirim', 1, 350000);
        $po->update(['akun_kas_bank' => '110-02']); // Bank

        $grn = app(GrnService::class)->inputGudang($po, [GrnService::kunciItem($this->produk->id, 0) => 1], $user->id);

        $this->assertEquals('terima', $grn->status);

        $akunBank = AkunCOA::where('kode', '110-02')->firstOrFail();
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'no_jurnal' => $grn->no_grn,
            'akun_coa_id' => $akunBank->id,
            'kredit' => 350000,
        ]);
    }

    public function test_superadmin_bisa_bayar_po_dengan_akun_bank_dinamis(): void
    {
        $superadmin = $this->buatUserDenganRole('super-admin');
        $po = $this->buatPo('kredit', 'dikirim', 2, 200000);

        app(GrnService::class)->inputGudang($po, [GrnService::kunciItem($this->produk->id, 0) => 2], $superadmin->id);

        $response = $this->actingAs($superadmin)->postJson("/api/wms/po/{$po->id}/bayar", [
            'jumlah' => 400000,
            'akun_kas_bank' => '110-02',
        ]);

        $response->assertSuccessful();

        $akunBank = AkunCOA::where('kode', '110-02')->firstOrFail();
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'referensi_tipe' => PurchaseOrder::class,
            'referensi_id' => $po->id,
            'akun_coa_id' => $akunBank->id,
            'kredit' => 400000,
        ]);
    }
}
