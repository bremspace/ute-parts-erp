<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\AsetTetap;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Sync\Models\HybridSyncOutbox;
use App\Modules\Sync\Services\HybridSyncProcessorService;
use App\Modules\Sync\SyncObserverBootstrap;
use App\Modules\Wms\Models\Grn;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\StokOpname;
use App\Modules\Wms\Models\Supplier;
use App\Services\BranchContextManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class BidirectionalBackofficeSyncTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Cabang $cabangDua;

    private Gudang $gudang;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        SyncObserverBootstrap::register();

        config([
            'sync.enabled' => true,
            'sync.api_secret' => 'bidir-sync-secret-123',
            'sync.node_id' => 'CLOUD-VPS',
            'sync.node_role' => 'cloud_storefront',
        ]);

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Pusat',
            'kode' => 'CBG-BIDIR-01',
            'alamat' => 'Jl. Bidir 1',
            'telepon' => '081200010001',
            'is_active' => true,
        ]);

        $this->cabangDua = Cabang::create([
            'nama' => 'Cabang Kedua',
            'kode' => 'CBG-BIDIR-02',
            'alamat' => 'Jl. Bidir 2',
            'telepon' => '081200010002',
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Bidir',
            'kode' => 'GDG-BIDIR-01',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create(['email' => 'admin.bidir@uteparts.id']);

        foreach ([
            ['110-04', 'Kas Toko', 'aset', 'kas', 'debit'],
            ['130-01', 'Persediaan', 'aset', 'persediaan', 'debit'],
            ['210-01', 'Utang Usaha', 'kewajiban', 'hutang', 'kredit'],
            ['610-01', 'Biaya Operasional', 'beban', 'beban_operasional', 'debit'],
        ] as [$kode, $nama, $tipe, $kelompok, $saldo]) {
            AkunCOA::firstOrCreate(
                ['kode' => $kode],
                ['nama' => $nama, 'tipe' => $tipe, 'kelompok' => $kelompok, 'saldo_normal' => $saldo, 'is_active' => true]
            );
        }
    }

    /**
     * Helper panggil GET /api/internal/sync/pull dengan signature HMAC valid.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pullFromCloud(): array
    {
        $ts = (string) time();
        $sig = hash_hmac('sha256', $ts.'.', (string) config('sync.api_secret'));

        $response = $this->withHeaders([
            'X-Sync-Node' => 'STORE-01',
            'X-Sync-Timestamp' => $ts,
            'X-Sync-Signature' => $sig,
        ])->getJson('/api/internal/sync/pull');

        $response->assertStatus(200);

        return $response->json('data', []);
    }

    public function test_cloud_backoffice_events_are_pulled_and_remapped_without_pk_collision(): void
    {
        // 1. Produk sudah ada di Store dengan ID berbeda, sync_uuid identik
        $sharedProduk = Produk::create([
            'nama' => 'Produk Bidir Sync',
            'slug' => 'produk-bidir-sync',
            'barcode' => 'BC-BIDIR-01',
            'kategori' => 'Umum',
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 45000,
            'harga_jual_retail' => 75000,
            'is_active' => true,
        ]);

        $supplierBeda = Supplier::create([
            'nama' => 'Supplier Eksklusif Store',
            'kode_lama' => 'SUP-STORE-LOCAL',
            'status_aktif' => true,
        ]);

        $poBeda = PurchaseOrder::create([
            'no_po' => 'PO-STORE-LOCAL',
            'supplier_id' => $supplierBeda->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'status' => 'draft',
            'metode_bayar' => 'tunai',
            'total' => 10000,
        ]);

        HybridSyncOutbox::query()->delete();

        // 2. Cloud membuat PO + Item + GRN + AsetTetap untuk Cabang 2
        $supplierUuid = (string) Str::uuid();
        $poUuid = (string) Str::uuid();
        $poItemUuid = (string) Str::uuid();
        $grnUuid = (string) Str::uuid();
        $asetUuid = (string) Str::uuid();

        $supplierPayload = [
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'supplier.upsert',
            'source_node' => 'CLOUD-VPS',
            'payload' => [
                'id' => $supplierBeda->id, // PK bertabrakan dgn SUP-STORE-LOCAL
                'sync_uuid' => $supplierUuid,
                'nama' => 'Supplier Cloud Baru',
                'kode_lama' => 'SUP-CLOUD-NEW',
                'status_aktif' => true,
            ],
        ];

        $poPayload = [
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'purchase_order.upsert',
            'source_node' => 'CLOUD-VPS',
            'payload' => [
                'id' => $poBeda->id, // PK bertabrakan dgn PO-STORE-LOCAL
                'sync_uuid' => $poUuid,
                'no_po' => 'PO-CLOUD-999',
                'supplier_id' => 9999,
                'gudang_tujuan_id' => 9999,
                'status' => 'disetujui',
                'metode_bayar' => 'kredit',
                'total' => 450000,
                '_refs' => [
                    'supplier_id' => ['id' => 9999, 'sync_uuid' => $supplierUuid, 'kode_lama' => 'SUP-CLOUD-NEW'],
                    'gudang_tujuan_id' => ['id' => 9999, 'kode' => $this->gudang->kode],
                ],
            ],
        ];

        $poItemPayload = [
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'purchase_order_item.upsert',
            'source_node' => 'CLOUD-VPS',
            'payload' => [
                'id' => 1,
                'sync_uuid' => $poItemUuid,
                'purchase_order_id' => 9999,
                'produk_id' => 9999,
                'satuan' => 'pcs',
                'konversi' => 1,
                'jumlah' => 10,
                'jumlah_dasar' => 10,
                'harga_beli' => 45000,
                'subtotal' => 450000,
                '_refs' => [
                    'purchase_order_id' => ['id' => 9999, 'sync_uuid' => $poUuid, 'no_po' => 'PO-CLOUD-999'],
                    'produk_id' => ['id' => 9999, 'slug' => 'produk-bidir-sync'],
                ],
            ],
        ];

        $grnPayload = [
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'grn.upsert',
            'source_node' => 'CLOUD-VPS',
            'payload' => [
                'id' => 1,
                'sync_uuid' => $grnUuid,
                'no_grn' => 'GRN-CLOUD-999',
                'po_id' => 9999,
                'cabang_id' => 9999,
                'gudang_id' => 9999,
                'user_id' => 9999,
                'status' => 'terima',
                'total_hpp' => 450000,
                'dpp' => 450000,
                'ppn_nominal' => 0,
                'ppn_percent' => 0,
                'item_qty_received' => [['produk_id' => $sharedProduk->id, 'qty_received' => 10]],
                '_refs' => [
                    'po_id' => ['id' => 9999, 'sync_uuid' => $poUuid, 'no_po' => 'PO-CLOUD-999'],
                    'cabang_id' => ['id' => 9999, 'kode' => $this->cabang->kode],
                    'gudang_id' => ['id' => 9999, 'kode' => $this->gudang->kode],
                    'user_id' => ['id' => 9999, 'email' => $this->user->email],
                ],
            ],
        ];

        $asetPayload = [
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'aset_tetap.upsert',
            'source_node' => 'CLOUD-VPS',
            'payload' => [
                'id' => 1,
                'sync_uuid' => $asetUuid,
                'cabang_id' => 9999,
                'nama' => 'Mesin Laminating Cabang 2',
                'kategori' => 'Peralatan',
                'harga_perolehan' => 3000000,
                'tanggal_perolehan' => '2026-11-01',
                'umur_bulan' => 24,
                'metode' => 'garis_lurus',
                'akumulasi_depresiasi' => 0,
                'status' => 'aktif',
                '_refs' => [
                    'cabang_id' => ['id' => 9999, 'kode' => $this->cabangDua->kode],
                ],
            ],
        ];

        // 3. Primary store aktif di Cabang 1 saat memproses -> aset Cabang 2 tetap tidak bocor ke Cabang 1
        app(BranchContextManager::class)->setActiveCabangId($this->cabang->id);
        config(['sync.node_role' => 'primary_store']);
        $processor = app(HybridSyncProcessorService::class);

        foreach ([$supplierPayload, $poPayload, $poItemPayload, $grnPayload, $asetPayload] as $event) {
            $res = $processor->process($event);
            $this->assertTrue($res['success'], $res['message'] ?? '');
        }

        // Row lokal yang ID-nya tabrakan tetap utuh
        $this->assertDatabaseHas('supplier', ['id' => $supplierBeda->id, 'nama' => 'Supplier Eksklusif Store']);
        $this->assertDatabaseHas('purchase_order', ['id' => $poBeda->id, 'no_po' => 'PO-STORE-LOCAL']);

        // Data cloud tersimpan rapi dengan sync_uuid dan relasi FK ter-remap benar
        $syncedSupplier = Supplier::where('sync_uuid', $supplierUuid)->firstOrFail();
        $syncedPo = PurchaseOrder::where('sync_uuid', $poUuid)->firstOrFail();
        $this->assertSame($syncedSupplier->id, $syncedPo->supplier_id);
        $this->assertSame($this->gudang->id, $syncedPo->gudang_tujuan_id);

        $syncedItem = PurchaseOrderItem::where('sync_uuid', $poItemUuid)->firstOrFail();
        $this->assertSame($syncedPo->id, $syncedItem->purchase_order_id);
        $this->assertSame($sharedProduk->id, $syncedItem->produk_id);

        $syncedGrn = Grn::where('sync_uuid', $grnUuid)->firstOrFail();
        $this->assertSame($syncedPo->id, $syncedGrn->po_id);
        $this->assertSame($this->user->id, $syncedGrn->user_id);
        $this->assertIsArray($syncedGrn->item_qty_received);
        $this->assertSame(10, $syncedGrn->item_qty_received[0]['qty_received']);

        $syncedAset = AsetTetap::withoutCabangScope()->where('sync_uuid', $asetUuid)->firstOrFail();
        $this->assertSame($this->cabangDua->id, $syncedAset->cabang_id);

        // Hapus GRN lewat delete sync -> terhapus di penerima
        $deleteRes = $processor->process([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'grn.delete',
            'source_node' => 'CLOUD-VPS',
            'payload' => [
                'id' => 1,
                'sync_uuid' => $grnUuid,
            ],
        ]);
        $this->assertTrue($deleteRes['success']);
        $this->assertNull(Grn::where('sync_uuid', $grnUuid)->first());
    }

    public function test_cloud_servis_jurnal_payroll_and_wms_stok_pull_bidirectionally_without_pos_double_deduction(): void
    {
        config(['sync.node_role' => 'cloud_storefront']);

        $produk = Produk::create([
            'nama' => 'Layar OLED Cloud Test',
            'slug' => 'layar-oled-cloud-test',
            'barcode' => 'BC-OLED-CLOUD',
            'kategori' => 'LCD',
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 200000,
            'harga_jual_retail' => 350000,
            'is_active' => true,
        ]);

        $stok = StokItem::create([
            'produk_id' => $produk->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 20,
        ]);

        HybridSyncOutbox::query()->delete();

        // A. Tiket Servis dibuat di Cloud VPS
        $tiket = TiketServis::create([
            'cabang_id' => $this->cabang->id,
            'no_tiket' => 'SRV-CLOUD-001',
            'nama_pelanggan' => 'Budi Cloud',
            'telepon_pelanggan' => '081234567899',
            'jenis_hp' => 'iPhone 15',
            'keluhan' => 'Ganti layar via admin VPS',
            'status' => 'diterima',
        ]);

        // B. Jurnal GRN dibuat di Cloud VPS
        $jurnalNo = app(JurnalService::class)->generateNoJurnal('grn', $this->cabang->id);
        app(JurnalService::class)->post(
            noJurnal: $jurnalNo,
            tanggal: now(),
            sumber: 'grn',
            lines: [
                ['akun_kode' => '130-01', 'debit' => 200000, 'kredit' => 0],
                ['akun_kode' => '210-01', 'debit' => 0, 'kredit' => 200000],
            ],
            deskripsi: 'Penerimaan PO dari VPS',
            cabangId: $this->cabang->id,
            userId: $this->user->id
        );

        // C. Payroll final dibuat di Cloud VPS
        $karyawan = Karyawan::create([
            'nik' => 'NIK-CLOUD-01',
            'nama' => 'Teknisi Cloud',
            'email' => 'teknisi.cloud@uteparts.id',
            'jabatan' => 'Teknisi',
            'cabang_id' => $this->cabang->id,
            'tgl_masuk' => '2026-01-01',
            'gaji_pokok' => 3500000,
            'status_aktif' => true,
        ]);

        $payroll = PayrollPeriode::create([
            'cabang_id' => $this->cabang->id,
            'periode' => '2026-11',
            'tanggal_mulai' => '2026-11-01',
            'tanggal_selesai' => '2026-11-30',
            'status' => PayrollPeriode::STATUS_SELESAI,
            'catatan' => 'Diproses di VPS',
        ]);

        // D. Mutasi stok Opname di Cloud VPS (+5) -> wajib ikut pull
        StokLog::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $produk->id,
            'user_id' => $this->user->id,
            'jenis' => 'masuk',
            'referensi_tipe' => StokOpname::class,
            'referensi_id' => 1,
            'jumlah_sebelum' => 20,
            'perubahan' => 5,
            'jumlah_setelah' => 25,
            'catatan' => 'Opname dari backoffice VPS',
        ]);

        // E. Mutasi stok milik transaksi marketplace / pos_cloud (VPS-*) -> TIDAK boleh emit stok.mutasi ganda
        $trxOnline = Transaksi::create([
            'no_transaksi' => 'ONLINE-CLOUD-77',
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'sumber' => 'marketplace',
            'subtotal' => 350000,
            'total_akhir' => 350000,
            'metode_bayar' => 'duitku',
            'status' => 'lunas',
        ]);

        StokLog::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $produk->id,
            'user_id' => $this->user->id,
            'jenis' => 'penjualan',
            'referensi_tipe' => Transaksi::class,
            'referensi_id' => $trxOnline->id,
            'jumlah_sebelum' => 25,
            'perubahan' => -2,
            'jumlah_setelah' => 23,
            'catatan' => 'Order online di VPS',
        ]);

        $pulled = $this->pullFromCloud();
        $eventTypes = collect($pulled)->pluck('event_type')->all();

        $this->assertContains('servis.update', $eventTypes);
        $this->assertContains('jurnal.upsert', $eventTypes);
        $this->assertContains('payroll.upsert', $eventTypes);
        $this->assertContains('karyawan.upsert', $eventTypes);
        $this->assertContains('stok.mutasi', $eventTypes);
        // Pastikan hanya 1 stok.mutasi (dari Opname), bukan dari transaksi marketplace!
        $stokEvents = collect($pulled)->where('event_type', 'stok.mutasi')->values();
        $this->assertCount(1, $stokEvents);
        $this->assertSame(5, $stokEvents[0]['payload']['perubahan']);

        // Proses stok.mutasi dari Cloud di node primary_store
        config(['sync.node_role' => 'primary_store']);
        $stok->update(['jumlah' => 20]); // Reset saldo store lokal
        $resStok = app(HybridSyncProcessorService::class)->process($stokEvents[0]);
        $this->assertTrue($resStok['success'], $resStok['message'] ?? '');

        $stok->refresh();
        $this->assertSame(25, (int) $stok->jumlah);

        // Proses servis.update & jurnal.upsert dari Cloud di primary_store setelah dihapus lokal (simulasi store baru)
        $tiket->delete();
        JurnalAkuntansi::where('no_jurnal', $jurnalNo)->delete();
        JurnalHeader::where('no_jurnal', $jurnalNo)->delete();

        $processor = app(HybridSyncProcessorService::class);
        foreach ($pulled as $ev) {
            if (in_array($ev['event_type'], ['servis.update', 'jurnal.upsert'], true)) {
                $res = $processor->process($ev);
                $this->assertTrue($res['success'], $res['message'] ?? '');
            }
        }

        $this->assertDatabaseHas('tiket_servis', ['no_tiket' => 'SRV-CLOUD-001', 'status' => 'diterima']);
        $this->assertDatabaseHas('jurnal_header', ['no_jurnal' => $jurnalNo, 'sumber' => 'grn']);
        // Payroll tetap ada dari seed / sync
        $this->assertSame($karyawan->nik, Karyawan::where('nik', 'NIK-CLOUD-01')->value('nik'));
        $this->assertDatabaseHas('payroll_periode', ['id' => $payroll->id, 'status' => 'selesai']);
    }
}
