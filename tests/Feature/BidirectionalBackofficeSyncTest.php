<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\AsetTetap;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Akunting\Models\RekonsiliasiBank;
use App\Modules\Akunting\Models\RekonsiliasiBankItem;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\PelangganKodeAktivasi;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\PayrollKomisiDetail;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Sync\Jobs\PushHybridSyncJob;
use App\Modules\Sync\Models\HybridSyncOutbox;
use App\Modules\Sync\Providers\SyncServiceProvider;
use App\Modules\Sync\Services\HybridSyncOutboxService;
use App\Modules\Sync\Services\HybridSyncProcessorService;
use App\Modules\Sync\SyncObserverBootstrap;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Grn;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\StokOpname;
use App\Modules\Wms\Models\Supplier;
use App\Services\BranchContextManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

    public function test_pull_command_cabang_filter_sorts_query_params_and_breaks_failed_all_loop(): void
    {
        config([
            'sync.enabled' => true,
            'sync.node_role' => 'primary_store',
            'sync.node_id' => 'STORE-01',
            'sync.remote_url' => 'https://cloud.bremspace.test',
            'sync.api_secret' => 'pull-sort-secret-456',
        ]);

        // Test Request signature dengan param terurut alfabetis (cabang_id lalu limit)
        $sortedQuery = 'cabang_id='.$this->cabang->id.'&limit=10';
        $ts = (string) time();
        $sig = hash_hmac('sha256', $ts.'.'.$sortedQuery, 'pull-sort-secret-456');

        // Simulasikan node cloud melayani GET pull?limit=10&cabang_id=X dengan VerifySyncSignature asli
        config(['sync.node_role' => 'cloud_storefront']);
        $httpRes = $this->withHeaders([
            'X-Sync-Node' => 'STORE-01',
            'X-Sync-Timestamp' => $ts,
            'X-Sync-Signature' => $sig,
        ])->getJson('/api/internal/sync/pull?limit=10&cabang_id='.$this->cabang->id);
        $httpRes->assertStatus(200);

        // Sekarang uji SyncPullCommand: wajib mengirim & menandatangani cabang_id=...&limit=...
        config(['sync.node_role' => 'primary_store']);
        Http::fake([
            'https://cloud.bremspace.test/api/internal/sync/pull*' => Http::response([
                'success' => true,
                'data' => [
                    // 2 event gagal sengaja dengan count == limit=2 untuk menguji anti infinite loop pada --all
                    ['sync_uuid' => (string) Str::uuid(), 'event_type' => 'order.online_paid', 'payload' => []],
                    ['sync_uuid' => (string) Str::uuid(), 'event_type' => 'order.online_paid', 'payload' => []],
                ],
            ], 200),
        ]);

        $this->artisan('sync:pull --limit=2 --cabang_id='.$this->cabang->id.' --all')
            ->expectsOutputToContain('Sinkronisasi pull selesai: 0 diproses, 2 gagal.')
            ->assertExitCode(0);

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            $tsHdr = $request->header('X-Sync-Timestamp')[0] ?? '';
            $sigHdr = $request->header('X-Sync-Signature')[0] ?? '';
            $expectedQs = 'cabang_id='.$this->cabang->id.'&limit=2';

            return str_contains($request->url(), '?'.$expectedQs)
                && hash_equals(hash_hmac('sha256', $tsHdr.'.'.$expectedQs, 'pull-sort-secret-456'), $sigHdr);
        });
    }

    public function test_same_second_outbox_ordering_and_live_config_reload_without_worker_restart(): void
    {
        HybridSyncOutbox::query()->delete();

        $sameTs = now()->startOfSecond();
        $first = HybridSyncOutbox::create([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'supplier.upsert',
            'entity_type' => Supplier::class,
            'entity_id' => 10,
            'cabang_id' => $this->cabang->id,
            'payload' => ['nama' => 'First'],
            'status' => 'pending',
            'created_at' => $sameTs,
            'updated_at' => $sameTs,
        ]);
        $second = HybridSyncOutbox::create([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'purchase_order.upsert',
            'entity_type' => PurchaseOrder::class,
            'entity_id' => 20,
            'cabang_id' => $this->cabang->id,
            'payload' => ['no_po' => 'PO-2'],
            'status' => 'pending',
            'created_at' => $sameTs,
            'updated_at' => $sameTs,
        ]);
        // Pastikan urutan berdasarkan ID naik meskipun timestamp identik
        $pendingIds = app(HybridSyncOutboxService::class)->getPendingItems(10)->pluck('id')->all();
        $this->assertSame([$first->id, $second->id], $pendingIds);

        // Simulasikan perubahan secret dari Settings UI saat queue worker masih hidup
        config([
            'sync.enabled' => true,
            'sync.node_role' => 'primary_store',
            'sync.remote_url' => 'https://cloud.bremspace.test',
            'sync.api_secret' => 'old-boot-secret',
        ]);
        $newSecret = 'rotated-secret-after-boot-789';
        app(KonfigurasiService::class)->set('hybrid_sync_config', [
            'enabled' => true,
            'node_role' => 'primary_store',
            'node_id' => 'STORE-01',
            'remote_url' => 'https://cloud.bremspace.test',
            'api_secret' => Crypt::encryptString($newSecret),
            'pull_interval_seconds' => 30,
        ]);
        SyncServiceProvider::markRuntimeConfigUpdated();

        Http::fake([
            'https://cloud.bremspace.test/api/internal/sync/push' => Http::response(['success' => true], 200),
        ]);

        (new PushHybridSyncJob($first->id))->handle();

        Http::assertSent(function ($request) use ($newSecret) {
            $tsHdr = $request->header('X-Sync-Timestamp')[0] ?? '';
            $sigHdr = $request->header('X-Sync-Signature')[0] ?? '';

            return hash_equals(hash_hmac('sha256', $tsHdr.'.'.$request->body(), $newSecret), $sigHdr);
        });
    }

    public function test_processor_remaps_supplier_approver_jurnal_referensi_payroll_komisi_master_deletes_and_mysql_dates(): void
    {
        config(['sync.node_role' => 'primary_store']);
        $processor = app(HybridSyncProcessorService::class);

        // 1. Produk -> Supplier remap (ID lokal beda dari 8888)
        $localSupplier = Supplier::create([
            'nama' => 'Supplier Lokal Remap',
            'kode_lama' => 'SUP-REMAP-01',
            'status_aktif' => true,
        ]);
        $resProduk = $processor->process([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'produk.upsert',
            'source_node' => 'CLOUD-VPS',
            'payload' => [
                'sync_uuid' => (string) Str::uuid(),
                'nama' => 'IC Power Remap',
                'slug' => 'ic-power-remap',
                'supplier_id' => 8888,
                'supplier_kode_lama' => 'SUP-REMAP-01',
                'supplier_nama' => 'Supplier Lokal Remap',
                'harga_beli' => 15000,
                'harga_jual_retail' => 35000,
                'is_active' => true,
            ],
        ]);
        $this->assertTrue($resProduk['success'], $resProduk['message'] ?? '');
        $this->assertSame($localSupplier->id, Produk::where('slug', 'ic-power-remap')->value('supplier_id'));

        // 2. Pelanggan -> approved_by remap by email
        $resPelanggan = $processor->process([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'pelanggan.upsert',
            'source_node' => 'CLOUD-VPS',
            'payload' => [
                'sync_uuid' => (string) Str::uuid(),
                'nama' => 'Reseller Approved Remap',
                'telepon' => '081277700011',
                'approved_by' => 7777,
                'approved_by_email' => $this->user->email,
                'status' => 'aktif',
            ],
        ]);
        $this->assertTrue($resPelanggan['success'], $resPelanggan['message'] ?? '');
        $pelangganSynced = Pelanggan::where('nama', 'Reseller Approved Remap')->firstOrFail();
        $this->assertSame($this->user->id, $pelangganSynced->approved_by);

        // 3. JurnalHeader -> referensi_id remap + PayrollPeriode -> komisi_details
        $tiket = TiketServis::create([
            'cabang_id' => $this->cabang->id,
            'no_tiket' => 'SRV-REMAP-901',
            'nama_pelanggan' => 'Adi',
            'telepon_pelanggan' => '081234567891',
            'jenis_hp' => 'Xiaomi',
            'keluhan' => 'LCD',
            'status' => 'selesai',
        ]);
        $karyawan = Karyawan::create([
            'nik' => 'NIK-KMS-01',
            'nama' => 'Teknisi Komisi',
            'cabang_id' => $this->cabang->id,
            'jabatan' => 'Teknisi',
            'tgl_masuk' => '2026-01-01',
            'gaji_pokok' => 3000000,
            'status_aktif' => true,
        ]);
        $komisi = Komisi::create([
            'no_komisi' => 'KMS-REMAP-901',
            'aktor_tipe' => 'karyawan',
            'aktor_id' => $karyawan->id,
            'tiket_servis_id' => $tiket->id,
            'jumlah_transaksi' => 100000,
            'nominal_komisi' => 25000,
            'status' => 'pending',
            'idempotensi_key' => 'kms-remap-901',
        ]);

        $resJurnal = $processor->process([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'jurnal.upsert',
            'source_node' => 'CLOUD-VPS',
            'payload' => [
                'sync_uuid' => (string) Str::uuid(),
                'no_jurnal' => 'JRN-SRV-REMAP-1',
                'cabang_id' => $this->cabang->id,
                'cabang_key' => $this->cabang->id,
                'tanggal' => now()->toIso8601String(),
                'sumber' => 'servis',
                'deskripsi' => 'Jurnal referensi servis',
                'referensi_tipe' => TiketServis::class,
                'referensi_id' => 99999,
                'referensi_natural' => ['no_tiket' => 'SRV-REMAP-901'],
                'user_email' => $this->user->email,
                'lines' => [
                    ['akun_kode' => '110-04', 'akun_nama' => 'Kas Toko', 'debit' => 100000, 'kredit' => 0],
                    ['akun_kode' => '610-01', 'akun_nama' => 'Biaya Operasional', 'debit' => 0, 'kredit' => 100000],
                ],
            ],
        ]);
        $this->assertTrue($resJurnal['success'], $resJurnal['message'] ?? '');
        $this->assertSame($tiket->id, JurnalHeader::where('no_jurnal', 'JRN-SRV-REMAP-1')->value('referensi_id'));

        $resPayroll = $processor->process([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'payroll.upsert',
            'source_node' => 'CLOUD-VPS',
            'payload' => [
                'sync_uuid' => (string) Str::uuid(),
                'cabang_id' => $this->cabang->id,
                'periode' => '2026-12',
                'tanggal_mulai' => '2026-12-01',
                'tanggal_selesai' => '2026-12-31',
                'status' => PayrollPeriode::STATUS_SELESAI,
                'slips' => [[
                    'karyawan_id' => 8888,
                    'karyawan_nik' => 'NIK-KMS-01',
                    'gaji_pokok' => 3000000,
                    'total_komisi' => 25000,
                    'total_gaji' => 3025000,
                    'komisi_details' => [[
                        'komisi_id' => 7777,
                        'komisi_idempotensi_key' => 'kms-remap-901',
                        'tiket_servis_id' => 6666,
                        'tiket_no_tiket' => 'SRV-REMAP-901',
                        'teknisi_id' => 8888,
                        'teknisi_nik' => 'NIK-KMS-01',
                        'jenis' => 'servis',
                        'nominal' => 25000,
                    ]],
                ]],
            ],
        ]);
        $this->assertTrue($resPayroll['success'], $resPayroll['message'] ?? '');
        $komisiDetail = PayrollKomisiDetail::firstOrFail();
        $this->assertSame($komisi->id, $komisiDetail->komisi_id);
        $this->assertSame($tiket->id, $komisiDetail->tiket_servis_id);
        $this->assertSame($karyawan->id, $komisiDetail->teknisi_id);

        // 4. Master delete cascade (Brand & KategoriProduk) + MySQL ISO-8601 date/datetime normalization + PelangganKodeAktivasi + Konfigurasi allowlist
        $brandToDelete = Brand::create(['nama' => 'Brand Remote Del', 'is_active' => true]);
        $katToDelete = KategoriProduk::create(['nama' => 'Kat Remote Del', 'slug' => 'kat-remote-del', 'is_active' => true]);
        $produkLinked = Produk::create([
            'nama' => 'Produk Cascade Check',
            'slug' => 'produk-cascade-check',
            'kategori' => $katToDelete->nama,
            'kategori_id' => $katToDelete->id,
            'brand_id' => $brandToDelete->id,
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 1000,
            'harga_jual_retail' => 2000,
            'is_active' => true,
        ]);

        $this->assertTrue($processor->process([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'brand.upsert',
            'source_node' => 'CLOUD-VPS',
            'payload' => ['nama' => 'Brand Remote Del', 'deleted' => true],
        ])['success']);
        $this->assertTrue($processor->process([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'kategori_produk.upsert',
            'source_node' => 'CLOUD-VPS',
            'payload' => ['slug' => 'kat-remote-del', 'deleted' => true],
        ])['success']);

        $produkLinked->refresh();
        $this->assertSame(Brand::getDefault()->id, $produkLinked->brand_id);
        $this->assertSame(KategoriProduk::getDefault()->id, $produkLinked->kategori_id);

        // MySQL 8 strict-safe ISO-8601 datetime & date normalization pada generic upsert (PelangganKodeAktivasi & RekonsiliasiBank)
        $rekonUuid = (string) Str::uuid();
        $coaBank = AkunCOA::firstOrCreate(
            ['kode' => '110-02'],
            ['nama' => 'Bank BCA', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit', 'is_active' => true]
        );
        $this->assertTrue($processor->process([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'rekonsiliasi_bank.upsert',
            'source_node' => 'CLOUD-VPS',
            'payload' => [
                'sync_uuid' => $rekonUuid,
                'cabang_id' => 9999,
                'akun_coa_id' => 9999,
                'nama_bank' => 'BCA',
                'periode_dari' => '2026-11-01T00:00:00+07:00',
                'periode_sampai' => '2026-11-30T00:00:00+07:00',
                'nama_file' => 'rekon-sync.csv',
                'format_file' => 'csv',
                'status' => 'draft',
                '_refs' => [
                    'cabang_id' => ['id' => 9999, 'kode' => $this->cabang->kode],
                    'akun_coa_id' => ['id' => 9999, 'kode' => $coaBank->kode],
                ],
            ],
        ])['success']);

        $this->assertTrue($processor->process([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'pelanggan_kode_aktivasi.upsert',
            'source_node' => 'CLOUD-VPS',
            'payload' => [
                'sync_uuid' => (string) Str::uuid(),
                'pelanggan_id' => 9999,
                'kode' => 'SYNC7788',
                'kedaluwarsa_at' => '2026-12-05T15:30:00+07:00',
                'dibuat_oleh' => 9999,
                '_refs' => [
                    'pelanggan_id' => ['id' => 9999, 'telepon' => $pelangganSynced->telepon],
                    'dibuat_oleh' => ['id' => 9999, 'email' => $this->user->email],
                ],
            ],
        ])['success']);

        $rekonSaved = RekonsiliasiBank::where('sync_uuid', $rekonUuid)->firstOrFail();
        $this->assertSame('2026-11-01', $rekonSaved->periode_dari->toDateString());
        $this->assertSame('2026-11-30', $rekonSaved->periode_sampai->toDateString());

        // Uji juga resolusi RekonsiliasiBankItem dengan no_jurnal_matched tanpa salah mengikat ID line node asal
        $this->assertTrue($processor->process([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'rekonsiliasi_bank_item.upsert',
            'source_node' => 'CLOUD-VPS',
            'payload' => [
                'sync_uuid' => (string) Str::uuid(),
                'rekonsiliasi_bank_id' => 9999,
                'tanggal' => '2026-11-15T08:00:00+07:00',
                'deskripsi' => 'Transfer masuk',
                'tipe' => 'CR',
                'nominal' => 100000,
                'status' => 'matched',
                'jurnal_akuntansi_id' => 9999,
                'no_jurnal_matched' => 'JRN-SRV-REMAP-1',
                '_refs' => [
                    'rekonsiliasi_bank_id' => ['id' => 9999, 'sync_uuid' => $rekonUuid],
                ],
            ],
        ])['success']);
        $rekonItemSaved = RekonsiliasiBankItem::where('rekonsiliasi_bank_id', $rekonSaved->id)->firstOrFail();
        $this->assertSame(JurnalAkuntansi::where('no_jurnal', 'JRN-SRV-REMAP-1')->value('id'), $rekonItemSaved->jurnal_akuntansi_id);

        $akt = PelangganKodeAktivasi::where('kode', 'SYNC7788')->firstOrFail();
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $akt->getRawOriginal('kedaluwarsa_at'));

        // Konfigurasi upsert: allowlisted key diterima, rahasia lokal ditolak
        $this->assertTrue($processor->process([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'konfigurasi.upsert',
            'source_node' => 'CLOUD-VPS',
            'payload' => ['kunci' => 'ppn_percent', 'nilai' => '12'],
        ])['success']);
        $this->assertSame('12', DB::table('konfigurasi')->where('kunci', 'ppn_percent')->value('nilai'));

        $this->assertTrue($processor->process([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => 'konfigurasi.upsert',
            'source_node' => 'CLOUD-VPS',
            'payload' => ['kunci' => 'hybrid_sync_config', 'nilai' => 'hijack'],
        ])['success']);
        $this->assertNotSame('hijack', DB::table('konfigurasi')->where('kunci', 'hybrid_sync_config')->value('nilai'));
    }
}
