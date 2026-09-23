<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Notifikasi\Jobs\KirimNotifikasiJob;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\PoTab;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Services\ReorderService;
use App\Modules\Workflow\Models\ApprovalRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F1-5 — Reorder Otomatis (PRD-Advanced-UteParts §4, §6).
 *
 * Trigger stok < minimum → usulan PO (status usulan) grouped per supplier
 * terakhir; observer F1-1 tidak auto-fire; notifikasi via queue;
 * konfirmasi 1 klik usulan → draft (+ approval idempoten ≥ threshold).
 */
class ReorderTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Gudang $gudang;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create([
            'kode' => 'UTP-RDL',
            'nama' => 'Cabang Reorder',
            'alamat' => 'Jl. Test',
            'telepon' => '08123456789',
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Reorder',
            'kode' => 'GDG-RDL',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Admin Reorder',
            'email' => 'admin-reorder@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);
    }

    private function buatProdukStok(string $nama, int $jumlah, int $minimum, float $hargaBeli = 50000): Produk
    {
        $produk = Produk::create([
            'nama' => $nama,
            'slug' => Str::slug($nama).'-'.Str::random(5),
            'kategori' => 'Sparepart',
            'kondisi' => 'baru',
            'harga_beli' => $hargaBeli,
            'harga_jual_retail' => $hargaBeli * 2,
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $produk->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => $jumlah,
            'jumlah_minimum' => $minimum,
        ]);

        return $produk;
    }

    private function riwayatPo(Supplier $supplier, Produk $produk): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'no_po' => 'PO-HIST-'.Str::random(6),
            'supplier_id' => $supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'status' => 'diterima',
            'metode_bayar' => 'kredit',
            'total' => 100000,
            'total_dibayar' => 0,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'produk_id' => $produk->id,
            'harga_beli' => 50000,
            'jumlah' => 1,
            'subtotal' => 50000,
        ]);

        return $po;
    }

    private function buatUsulanPo(float $total): PurchaseOrder
    {
        $supplier = Supplier::create(['nama' => 'Supplier Usulan'.Str::random(4), 'termin_hari' => 30]);

        return PurchaseOrder::create([
            'no_po' => 'PO-USL-'.Str::random(6),
            'supplier_id' => $supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'status' => 'usulan',
            'metode_bayar' => 'kredit',
            'total' => $total,
            'total_dibayar' => 0,
            'catatan' => 'Usulan reorder otomatis',
        ]);
    }

    // ===== Trigger + grouping =====

    public function test_stok_di_bawah_minimum_membuat_usulan_po(): void
    {
        Queue::fake();

        $supplier = Supplier::create(['nama' => 'Supplier Trigger', 'termin_hari' => 30]);
        $produk = $this->buatProdukStok('Aki Kering Low', 3, 10); // qty usulan = 7
        $this->riwayatPo($supplier, $produk);

        // Stok aman — tidak boleh ikut usulan
        $this->buatProdukStok('Filter Aman', 20, 2);

        $pos = app(ReorderService::class)->jalankan();

        $this->assertCount(1, $pos, 'Hanya produk di bawah minimum yang diusulkan');
        $po = $pos->first();
        $this->assertEquals('usulan', $po->status);
        $this->assertEquals($supplier->id, $po->supplier_id);
        $this->assertEquals($this->gudang->id, $po->gudang_tujuan_id);
        $this->assertStringStartsWith('PO-'.now()->format('Ymd'), $po->no_po);
        $this->assertCount(1, $po->items);
        $this->assertEquals(7, $po->items->first()->jumlah, 'Qty = jumlah_minimum - jumlah');
        $this->assertEquals(7 * 50000, (float) $po->total);
    }

    public function test_grouping_usulan_per_supplier_terakhir(): void
    {
        Queue::fake();

        $supA = Supplier::create(['nama' => 'Supplier A', 'termin_hari' => 30]);
        $supB = Supplier::create(['nama' => 'Supplier B', 'termin_hari' => 30]);

        $p1 = $this->buatProdukStok('Sparepart A1', 1, 5);
        $p2 = $this->buatProdukStok('Sparepart A2', 1, 5);
        $p3 = $this->buatProdukStok('Sparepart B1', 1, 5);

        $this->riwayatPo($supA, $p1);
        $this->riwayatPo($supA, $p2);
        $this->riwayatPo($supB, $p3);

        $pos = app(ReorderService::class)->jalankan();

        $this->assertCount(2, $pos, 'Dua supplier → dua PO usulan terpisah');

        $bySupA = $pos->firstWhere('supplier_id', $supA->id);
        $bySupB = $pos->firstWhere('supplier_id', $supB->id);

        $this->assertNotNull($bySupA);
        $this->assertNotNull($bySupB);
        $this->assertCount(2, $bySupA->items, 'Produk supplier A digabung dalam satu PO');
        $this->assertCount(1, $bySupB->items);
    }

    public function test_anti_duplikat_skip_jika_masih_ada_po_pending(): void
    {
        Queue::fake();

        $supplier = Supplier::create(['nama' => 'Supplier Dup', 'termin_hari' => 30]);
        $produk = $this->buatProdukStok('Sparepart Dup', 1, 10);
        $this->riwayatPo($supplier, $produk);

        // Draft PO existing sudah memuat produk yang sama
        $draft = PurchaseOrder::create([
            'no_po' => 'PO-DRAFT-DUP',
            'supplier_id' => $supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'status' => 'draft',
            'metode_bayar' => 'kredit',
            'total' => 100000,
            'total_dibayar' => 0,
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $draft->id,
            'produk_id' => $produk->id,
            'harga_beli' => 50000,
            'jumlah' => 5,
            'subtotal' => 250000,
        ]);

        $pos = app(ReorderService::class)->jalankan();

        $this->assertCount(0, $pos, 'Produk dengan PO draft pending wajib di-skip');

        // Run kedua setelah usulan pertama dibuat → tidak dobel
        $draft->update(['status' => 'dibatalkan']);
        $pertama = app(ReorderService::class)->jalankan();
        $kedua = app(ReorderService::class)->jalankan();

        $this->assertCount(1, $pertama);
        $this->assertCount(0, $kedua, 'Usulan kedua wajib di-skip selama usulan pertama masih pending');
    }

    public function test_observer_tidak_auto_fire_approval_untuk_usulan(): void
    {
        Queue::fake();

        $po = $this->buatUsulanPo(15000000); // di atas threshold 10jt

        $this->assertSame(
            0,
            ApprovalRequest::where('entity_type', 'po')->where('entity_id', $po->id)->count(),
            'PO status usulan tidak boleh auto-fire approval F1-1'
        );

        // Jalankan service end-to-end: semua PO usulan hasil job juga tanpa approval
        $supplier = Supplier::create(['nama' => 'Supplier Job', 'termin_hari' => 30]);
        $produk = $this->buatProdukStok('Sparepart Job', 1, 10, 2000000); // total usulan 18jt > 10jt
        $this->riwayatPo($supplier, $produk);

        $pos = app(ReorderService::class)->jalankan();

        $this->assertCount(1, $pos);
        $this->assertGreaterThan(10000000, (float) $pos->first()->total);
        $this->assertSame(
            0,
            ApprovalRequest::where('entity_type', 'po')->where('entity_id', $pos->first()->id)->count(),
            'Usulan hasil job juga wajib tanpa auto-fire approval'
        );
    }

    public function test_notifikasi_reorder_lewat_queue(): void
    {
        Queue::fake();

        $supplier = Supplier::create(['nama' => 'Supplier Notif', 'termin_hari' => 30]);
        $produk = $this->buatProdukStok('Sparepart Notif', 1, 5);
        $this->riwayatPo($supplier, $produk);

        app(ReorderService::class)->jalankan();

        Queue::assertPushed(KirimNotifikasiJob::class, 1);
        $this->assertDatabaseHas('notifikasi_keluar', [
            'tipe' => 'inapp',
            'tujuan' => null,
            'judul' => 'Usulan Reorder Otomatis',
            'status' => 'pending', // belum diproses → bukti tidak sync
        ]);
    }

    // ===== Konfirmasi 1 klik =====

    public function test_konfirmasi_usulan_di_atas_threshold_trigger_approval(): void
    {
        Queue::fake();
        $this->actingAs($this->user, 'web');

        $po = $this->buatUsulanPo(15000000);
        $this->assertSame(0, ApprovalRequest::where('entity_type', 'po')->where('entity_id', $po->id)->count());

        Livewire::test(PoTab::class)
            ->call('konfirmasiUsulan', $po->id)
            ->assertDispatched('alert');

        $this->assertEquals('draft', $po->fresh()->status, 'Konfirmasi wajib ubah usulan → draft');

        $req = ApprovalRequest::where('entity_type', 'po')->where('entity_id', $po->id)->first();
        $this->assertNotNull($req, 'PO ≥ threshold wajib diajukan approval setelah konfirmasi');
        $this->assertEquals('pending', $req->status);
        $this->assertEquals('finance', $req->approver_role);
        $this->assertEquals((int) $this->cabang->id, (int) $req->cabang_id);

        // Konfirmasi ulang (bukan usulan lagi) → tidak dobel approval
        Livewire::test(PoTab::class)
            ->call('konfirmasiUsulan', $po->id)
            ->assertDispatched('alert');
        $this->assertEquals('draft', $po->fresh()->status);
        $this->assertSame(
            1,
            ApprovalRequest::where('entity_type', 'po')->where('entity_id', $po->id)->count(),
            'Approval idempoten — tidak double-create'
        );
    }

    public function test_konfirmasi_usulan_di_bawah_threshold_tanpa_approval(): void
    {
        Queue::fake();
        $this->actingAs($this->user, 'web');

        $po = $this->buatUsulanPo(5000000); // < 10jt

        Livewire::test(PoTab::class)
            ->call('konfirmasiUsulan', $po->id)
            ->assertDispatched('alert');

        $this->assertEquals('draft', $po->fresh()->status);
        $this->assertSame(
            0,
            ApprovalRequest::where('entity_type', 'po')->where('entity_id', $po->id)->count(),
            'Di bawah threshold tidak perlu approval (alur PO normal)'
        );
    }

    public function test_konfirmasi_hanya_berlaku_untuk_status_usulan(): void
    {
        Queue::fake();
        $this->actingAs($this->user, 'web');

        $supplier = Supplier::create(['nama' => 'Supplier Draft', 'termin_hari' => 30]);
        $po = PurchaseOrder::create([
            'no_po' => 'PO-ONLY-DRAFT',
            'supplier_id' => $supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'status' => 'draft',
            'metode_bayar' => 'kredit',
            'total' => 15000000,
            'total_dibayar' => 0,
        ]);
        // Draft > threshold → observer F1-1 fire (kontrol positif regresi)
        $this->assertSame(1, ApprovalRequest::where('entity_type', 'po')->where('entity_id', $po->id)->count());

        Livewire::test(PoTab::class)
            ->call('konfirmasiUsulan', $po->id)
            ->assertDispatched('alert');

        $this->assertEquals('draft', $po->fresh()->status, 'Status draft tidak boleh diubah konfirmasiUsulan');
        $this->assertSame(1, ApprovalRequest::where('entity_type', 'po')->where('entity_id', $po->id)->count());
    }

    public function test_ui_usulan_tampil_dengan_status_pill_dan_tombol_konfirmasi(): void
    {
        $this->actingAs($this->user, 'web');
        $this->buatUsulanPo(1000000);

        Livewire::test(PoTab::class)
            ->assertSee('Usulan')
            ->assertSee('Konfirmasi')
            ->assertSee('Riwayat'); // F1-4 tombol riwayat tetap ada
    }

    // ===== Schedule =====

    public function test_jadwal_reorder_terdaftar_dan_entri_lama_intact(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('ReorderOtomatisJob')
            ->expectsOutputToContain('tier:recalc')
            ->expectsOutputToContain('crm:broadcast-terjadwal')
            ->expectsOutputToContain('ute:backup')
            ->assertExitCode(0);
    }
}
