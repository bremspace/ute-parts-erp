<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\OpnameTab;
use App\Modules\Wms\Livewire\TransferTab;
use App\Modules\Wms\Models\CycleCountSchedule;
use App\Modules\Wms\Models\CycleCountTask;
use App\Modules\Wms\Models\Grn;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\StokOpname;
use App\Modules\Wms\Models\StokTransfer;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Services\CycleCountService;
use App\Modules\Wms\Services\GrnService;
use App\Modules\Wms\Services\ProdukService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * B-10i — jejak pelaku pada StockMutationLog di SETIAP writer mutasi stok.
 *
 * B-10b sudah menambahkan kolom `stock_mutation_log.user_id` (nullable) dan B-10f
 * sudah mengisinya di writer kanonik `StokDeductionService::kurangi()`. Namun 9
 * call site lain masih `StockMutationLog::create()` tanpa `user_id` → mutasi stok
 * (opname, transfer, stok masuk, GRN, cycle count) tidak bisa dibuktikan pelakunya.
 *
 * Test ini memverifikasi 3 jalur utama yang diminta (opname, transfer, stok masuk)
 * plus jalur mutasi stok lain yang disentuh B-10i, bahwa `user_id` terisi user
 * yang benar dan KONSISTEN dengan `stok_log.user_id` pada operasi yang sama.
 */
class StockMutationActorTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Gudang $gudang;

    private Gudang $gudangTujuan;

    private User $user;

    private Produk $produk;

    private SkuVariant $varian;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // COA wajib untuk JurnalService::post (opname 130-01/520-08, GRN 130-01/210-01)
        foreach ([
            '130-01' => ['Persediaan', 'aset', 'persediaan', 'debit'],
            '210-01' => ['Utang Usaha', 'kewajiban', 'utang_usaha', 'kredit'],
            '520-08' => ['Selisih Stok', 'beban', 'hpp', 'debit'],
        ] as $kode => [$nama, $tipe, $kelompok, $saldo]) {
            AkunCOA::firstOrCreate(['kode' => $kode], [
                'nama' => $nama, 'tipe' => $tipe, 'kelompok' => $kelompok, 'saldo_normal' => $saldo,
            ]);
        }

        $this->cabang = Cabang::create([
            'kode' => 'CBG-B10I', 'nama' => 'Cabang B10i', 'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id, 'nama' => 'Gudang Asal', 'kode' => 'GDG-B10I-A', 'is_active' => true,
        ]);
        $this->gudangTujuan = Gudang::create([
            'cabang_id' => $this->cabang->id, 'nama' => 'Gudang Tujuan', 'kode' => 'GDG-B10I-B', 'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Pelaku B10i', 'email' => 'aktor-b10i@test.local',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);

        $this->produk = Produk::create([
            'nama' => 'Baterai B10i', 'slug' => 'baterai-b10i', 'kategori' => 'Baterai',
            'kondisi' => 'baru', 'harga_beli' => 25000, 'harga_jual_retail' => 60000,
            'is_active' => true, 'sn' => false,
        ]);
        $this->varian = SkuVariant::create([
            'produk_id' => $this->produk->id, 'sku' => 'B10I-'.Str::random(6),
            'nama_varian' => 'Standar', 'harga_beli' => 25000, 'harga_jual_retail' => 60000,
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create(['nama' => 'Supplier B10i', 'termin_hari' => 30]);

        $this->actingAs($this->user, 'web');
        session(['cabang_id' => $this->cabang->id]);
    }

    private function buatStok(int $qty = 10, ?Gudang $gudang = null): StokItem
    {
        return StokItem::create([
            'produk_id' => $this->produk->id,
            'sku_variant_id' => $this->varian->id,
            'gudang_id' => ($gudang ?? $this->gudang)->id,
            'jumlah' => $qty,
            'jumlah_minimum' => 1,
        ]);
    }

    /** Assert mutasi SOT terisi pelaku yang benar + konsisten dgn StokLog operasi yang sama. */
    private function assertPelaku(string $sumber, int $referensiId, ?int $delta = null): void
    {
        $mutasi = StockMutationLog::where('sumber', $sumber)
            ->where('referensi_id', $referensiId)
            ->first();

        $this->assertNotNull($mutasi, "StockMutationLog sumber '{$sumber}' harus tercatat");
        $this->assertSame(
            $this->user->id,
            (int) $mutasi->user_id,
            "StockMutationLog sumber '{$sumber}' wajib mengisi user_id pelaku"
        );
        $this->assertSame($this->user->id, (int) $mutasi->user?->id, 'Relasi user harus ter-resolve');

        if ($delta !== null) {
            $this->assertSame($delta, (int) $mutasi->delta);
        }

        // StokLog sibling wajib satu pelaku (bukan 2 sumber kebenaran berbeda)
        $stokLog = StokLog::where('referensi_id', $referensiId)
            ->where('referensi_tipe', $mutasi->referensi_tipe)
            ->first();
        if ($stokLog) {
            $this->assertSame(
                (int) $stokLog->user_id,
                (int) $mutasi->user_id,
                'stok_log & stock_mutation_log harus mencantumkan pelaku yang sama'
            );
        }
    }

    // ============================================================
    // JALUR 1 — OPNAME (WmsController + Livewire OpnameTab)
    // ============================================================

    /** [Jalur 1a] API: POST opname → input items → approve supervisor. */
    public function test_api_wms_opname_approve_mengisi_user_id(): void
    {
        $this->buatStok(10);

        $opname = $this->postJson('/api/wms/opname', ['gudang_id' => $this->gudang->id])
            ->assertSuccessful()->json('data');

        $this->postJson("/api/wms/opname/{$opname['id']}/items", [
            'items' => [[
                'produk_id' => $this->produk->id,
                'sku_variant_id' => $this->varian->id,
                'stok_fisik' => 8, // selisih -2
            ]],
        ])->assertSuccessful();

        $this->putJson("/api/wms/opname/{$opname['id']}/approve", ['action' => 'approve'])
            ->assertSuccessful();

        $this->assertPelaku('opname', (int) $opname['id'], -2);
        $this->assertSame(8, (int) StokItem::where('gudang_id', $this->gudang->id)->value('jumlah'));
    }

    /** [Jalur 1b] Livewire: OpnameTab::approveOpname (paritas dgn API). */
    public function test_livewire_opname_tab_approve_mengisi_user_id(): void
    {
        $this->buatStok(10);

        $opname = StokOpname::create([
            'no_opname' => 'OPN-B10I-0001',
            'gudang_id' => $this->gudang->id,
            'rak_id' => null,
            'user_id' => $this->user->id,
            'status' => 'menunggu_approval',
        ]);
        $opname->items()->create([
            'produk_id' => $this->produk->id,
            'sku_variant_id' => $this->varian->id,
            'rak_id' => null,
            'stok_sistem' => 10,
            'stok_fisik' => 12, // selisih +2
            'selisih' => 2,
        ]);

        Livewire::test(OpnameTab::class)
            ->call('approveOpname', $opname->id)
            ->assertHasNoErrors();

        $this->assertPelaku('opname', (int) $opname->id, 2);
        $this->assertSame(12, (int) StokItem::where('gudang_id', $this->gudang->id)->value('jumlah'));
    }

    // ============================================================
    // JALUR 2 — TRANSFER (WmsController + Livewire TransferTab)
    // ============================================================

    /** [Jalur 2a] API: kirim (stok keluar) + terima (stok masuk) → dua mutasi ter-actor. */
    public function test_api_wms_transfer_kirim_dan_terima_mengisi_user_id(): void
    {
        $this->buatStok(10);

        $transfer = $this->postJson('/api/wms/transfer', [
            'gudang_asal_id' => $this->gudang->id,
            'gudang_tujuan_id' => $this->gudangTujuan->id,
            'items' => [[
                'produk_id' => $this->produk->id,
                'sku_variant_id' => $this->varian->id,
                'jumlah' => 4,
            ]],
        ])->assertSuccessful()->json('data');

        $this->putJson("/api/wms/transfer/{$transfer['id']}/kirim")->assertSuccessful();
        $this->assertPelaku('transfer:out', (int) $transfer['id'], -4);

        $this->putJson("/api/wms/transfer/{$transfer['id']}/terima")->assertSuccessful();
        $this->assertPelaku('transfer:in', (int) $transfer['id'], 4);

        $this->assertSame(6, (int) StokItem::where('gudang_id', $this->gudang->id)->value('jumlah'));
        $this->assertSame(4, (int) StokItem::where('gudang_id', $this->gudangTujuan->id)->value('jumlah'));
    }

    /** [Jalur 2b] Livewire: TransferTab::kirimTransfer + terimaTransfer. */
    public function test_livewire_transfer_tab_kirim_dan_terima_mengisi_user_id(): void
    {
        $this->buatStok(10);

        $transfer = StokTransfer::create([
            'no_transfer' => 'TRF-B10I-0001',
            'gudang_asal_id' => $this->gudang->id,
            'gudang_tujuan_id' => $this->gudangTujuan->id,
            'user_pengirim_id' => $this->user->id,
            'status' => 'draft',
        ]);
        $transfer->items()->create([
            'produk_id' => $this->produk->id,
            'sku_variant_id' => $this->varian->id,
            'rak_id' => null,
            'jumlah' => 3,
            'created_by' => $this->user->id,
        ]);

        Livewire::test(TransferTab::class)
            ->call('kirimTransfer', $transfer->id)
            ->assertHasNoErrors();
        $this->assertPelaku('transfer:out', (int) $transfer->id, -3);

        Livewire::test(TransferTab::class)
            ->call('terimaTransfer', $transfer->id)
            ->assertHasNoErrors();
        $this->assertPelaku('transfer:in', (int) $transfer->id, 3);
    }

    // ============================================================
    // JALUR 3 — STOK MASUK (ProdukService / tab Produk, ProductsController)
    // ============================================================

    /** [Jalur 3a] ProdukService::tambahStokPembelian — stok masuk dari pembelian supplier. */
    public function test_produk_service_tambah_stok_mengisi_user_id(): void
    {
        app(ProdukService::class)->tambahStokPembelian(
            produkId: $this->produk->id,
            variantId: $this->varian->id,
            gudangId: $this->gudang->id,
            qty: 7,
            hargaBeli: 25000,
            keterangan: 'Pembelian B10i',
            userId: $this->user->id
        );

        $mutasi = StockMutationLog::where('sumber', 'po')->first();
        $this->assertNotNull($mutasi, 'Stok masuk harus tercatat di StockMutationLog');
        $this->assertSame($this->user->id, (int) $mutasi->user_id);
        $this->assertSame(7, (int) $mutasi->delta);
        $this->assertSame(
            (int) StokLog::where('referensi_id', $this->produk->id)->value('user_id'),
            (int) $mutasi->user_id
        );
    }

    /** [Jalur 3b] ProdukService::buatProduk dg stok awal → mutasi awal ter-actor. */
    public function test_buat_produk_dengan_stok_awal_mengisi_user_id(): void
    {
        $produkBaru = app(ProdukService::class)->buatProduk(
            nama: 'Produk Baru B10i',
            kategori: 'Umum',
            brand: 'Merk',
            model: 'X1',
            kondisi: 'baru',
            hargaBeli: 10000,
            hargaJual: 20000,
            gudangId: $this->gudang->id,
            stokAwal: 5,
            userId: $this->user->id
        );

        $mutasi = StockMutationLog::where('referensi_id', $produkBaru->id)->first();
        $this->assertNotNull($mutasi, 'Stok awal produk baru harus tercatat di StockMutationLog');
        $this->assertSame($this->user->id, (int) $mutasi->user_id);
    }

    /** [Jalur 3c] ProdukService tanpa konteks user (CLI/import) → NULL, bukan user fiktif. */
    public function test_tambah_stok_tanpa_user_id_tetap_null(): void
    {
        app(ProdukService::class)->tambahStokPembelian(
            produkId: $this->produk->id,
            variantId: $this->varian->id,
            gudangId: $this->gudang->id,
            qty: 2,
            hargaBeli: 25000,
            keterangan: 'Tanpa aktor',
            userId: null,
            postJurnal: false
        );

        $mutasi = StockMutationLog::where('sumber', 'po')->first();
        $this->assertNotNull($mutasi);
        $this->assertNull($mutasi->user_id, 'Tanpa konteks user tidak boleh mengarang aktor');
    }

    // ============================================================
    // JALUR TAMBAHAN — GRN & CYCLE COUNT (juga disentuh B-10i)
    // ============================================================

    /** [GRN] Terima barang PO → stok masuk tercatat atas nama petugas finalize. */
    public function test_grn_terima_barang_mengisi_user_id(): void
    {
        $po = PurchaseOrder::create([
            'no_po' => 'PO-B10I-0001',
            'supplier_id' => $this->supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'status' => 'dikirim',
            'metode_bayar' => 'kredit',
            'total' => 5 * 25000,
            'total_dibayar' => 0,
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'produk_id' => $this->produk->id,
            'sku_variant_id' => $this->varian->id,
            'harga_beli' => 25000,
            'jumlah' => 5,
            'subtotal' => 5 * 25000,
        ]);

        $grn = app(GrnService::class)->inputGudang($po->fresh(), [$this->produk->id => 5], $this->user->id);

        $this->assertSame('terima', $grn->fresh()->status);
        $this->assertPelaku('grn', (int) $grn->id, 5);
        $this->assertSame(5, (int) StokItem::where('gudang_id', $this->gudang->id)->value('jumlah'));
    }

    /** [GRN] Setujui GRN partial (jalur approval) → tetap ter-actor pending. */
    public function test_grn_setujui_mengisi_user_id(): void
    {
        $po = PurchaseOrder::create([
            'no_po' => 'PO-B10I-0002',
            'supplier_id' => $this->supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'status' => 'dikirim',
            'metode_bayar' => 'tunai',
            'total' => 6 * 25000,
            'total_dibayar' => 0,
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'produk_id' => $this->produk->id,
            'sku_variant_id' => $this->varian->id,
            'harga_beli' => 25000,
            'jumlah' => 6,
            'subtotal' => 6 * 25000,
        ]);

        $grn = app(GrnService::class)->inputGudang($po->fresh(), [$this->produk->id => 4], $this->user->id);
        $this->assertSame('draft', $grn->fresh()->status, 'Qty partial → draft + approval');

        $setujui = User::create([
            'name' => 'Pending B10i', 'email' => 'pending-b10i@test.local',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $setujui->assignRole('super-admin');
        $setujui->cabangs()->attach($this->cabang->id);

        $this->actingAs($setujui, 'web');
        app(GrnService::class)->setujuiGrn((int) $grn->id, $setujui->id);

        $mutasi = StockMutationLog::where('sumber', 'grn')
            ->where('referensi_id', $grn->id)->first();
        $this->assertNotNull($mutasi);
        $this->assertSame(
            $setujui->id,
            (int) $mutasi->user_id,
            'Pelaku GRN = petugas yang menyetujui, bukan pemohon'
        );
        $this->assertSame(4, (int) $mutasi->delta);
    }

    /** [Cycle Count] Selisih minor → koreksi langsung → mutasi ter-actor petugas count. */
    public function test_cycle_count_koreksi_mengisi_user_id(): void
    {
        $stok = $this->buatStok(10);

        $jadwal = CycleCountSchedule::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Harian B10i',
            'tipe_target' => 'gudang',
            'target_id' => $this->gudang->id,
            'frekuensi' => 'mingguan',
            'hari' => 1,
            'jam' => '01:00',
            'sample_size' => 5,
            'threshold_unit' => 3,   // |selisih| <= 3 → minor → koreksi langsung
            'threshold_persen' => 10,
            'is_aktif' => true,
        ]);

        $service = app(CycleCountService::class);
        $task = $service->generateTask($jadwal);
        $this->assertNotNull($task);
        $this->assertSame(1, count($task->sample_items));

        $service->hitung($task, [$stok->id => 9], $this->user->id); // selisih -1 (minor)

        $this->assertSame('selesai', $task->fresh()->status);
        $this->assertPelaku('cycle_count', (int) $task->id, -1);
        $this->assertSame(9, (int) StokItem::whereKey($stok->id)->value('jumlah'));
    }

    /** [Cycle Count] Jalur approval (terapkanKoreksi) → pelaku = approver, bukan pemohon. */
    public function test_cycle_count_approval_mengisi_user_id_approver(): void
    {
        $stok = $this->buatStok(10);

        $jadwal = CycleCountSchedule::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Major B10i',
            'tipe_target' => 'gudang',
            'target_id' => $this->gudang->id,
            'frekuensi' => 'mingguan',
            'hari' => 1,
            'jam' => '01:00',
            'sample_size' => 5,
            'threshold_unit' => 0,
            'threshold_persen' => 0,
            'is_aktif' => true,
        ]);

        $task = CycleCountTask::create([
            'cycle_count_schedule_id' => $jadwal->id,
            'cabang_id' => $this->cabang->id,
            'no_task' => 'CC-B10I-0001',
            'tanggal' => now()->toDateString(),
            'tipe_target' => 'gudang',
            'target_id' => $this->gudang->id,
            'target_label' => 'Gudang Asal',
            'seed' => 12345,
            'sample_items' => [[
                'stok_item_id' => $stok->id,
                'produk_id' => $this->produk->id,
                'sku_variant_id' => $this->varian->id,
                'gudang_id' => $this->gudang->id,
                'rak_id' => null,
                'nama' => $this->produk->nama,
                'stok_sistem' => 10,
            ]],
            'status' => 'menunggu_approval',
            'hasil' => [[
                'stok_item_id' => $stok->id,
                'produk_id' => $this->produk->id,
                'sku_variant_id' => $this->varian->id,
                'gudang_id' => $this->gudang->id,
                'nama' => $this->produk->nama,
                'stok_sistem' => 10,
                'stok_fisik' => 9,
                'selisih' => -1,
                'klasifikasi' => 'major',
            ]],
            'threshold_unit' => 0,     // apa pun jadi major
            'threshold_persen' => 0,
        ]);

        $approver = User::create([
            'name' => 'Approver B10i', 'email' => 'approver-b10i@test.local',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $approver->assignRole('super-admin');
        $approver->cabangs()->attach($this->cabang->id);

        $this->actingAs($approver, 'web');
        app(CycleCountService::class)->terapkanKoreksi((int) $task->id, $approver->id);

        $mutasi = StockMutationLog::where('sumber', 'cycle_count')
            ->where('referensi_id', $task->id)->first();
        $this->assertNotNull($mutasi, 'Cycle count harus mencatat mutasi');
        $this->assertSame($approver->id, (int) $mutasi->user_id);
        $this->assertSame(-1, (int) $mutasi->delta);
    }

    /** Regression guard: TIDAK ada mutasi SOT tanpa user_id pada jalur yang ter-actor. */
    public function test_tidak_ada_mutation_log_tanpa_actor_dari_jalur_ini(): void
    {
        $this->buatStok(10);

        $opname = $this->postJson('/api/wms/opname', ['gudang_id' => $this->gudang->id])
            ->assertSuccessful()->json('data');
        $this->postJson("/api/wms/opname/{$opname['id']}/items", [
            'items' => [[
                'produk_id' => $this->produk->id,
                'sku_variant_id' => $this->varian->id,
                'stok_fisik' => 7,
            ]],
        ])->assertSuccessful();
        $this->putJson("/api/wms/opname/{$opname['id']}/approve")->assertSuccessful();

        $transfer = $this->postJson('/api/wms/transfer', [
            'gudang_asal_id' => $this->gudang->id,
            'gudang_tujuan_id' => $this->gudangTujuan->id,
            'items' => [[
                'produk_id' => $this->produk->id,
                'sku_variant_id' => $this->varian->id,
                'jumlah' => 2,
            ]],
        ])->assertSuccessful()->json('data');
        $this->putJson("/api/wms/transfer/{$transfer['id']}/kirim")->assertSuccessful();
        $this->putJson("/api/wms/transfer/{$transfer['id']}/terima")->assertSuccessful();

        $tanpaAktor = StockMutationLog::whereNull('user_id')
            ->whereIn('sumber', ['opname', 'transfer:out', 'transfer:in', 'grn', 'po'])
            ->pluck('sumber')->all();

        $this->assertSame(
            [],
            $tanpaAktor,
            'Tidak boleh ada mutasi stok tanpa pelaku pada jalur WMS yang ter-autentikasi'
        );
        $this->assertGreaterThanOrEqual(3, StockMutationLog::count());
    }
}
