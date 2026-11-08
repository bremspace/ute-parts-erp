<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Omnichannel\Models\Channel;
use App\Modules\Omnichannel\Models\ChannelOrder;
use App\Modules\Omnichannel\Models\ChannelProductMapping;
use App\Modules\Omnichannel\Services\ChannelSyncService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Workflow\Models\ApprovalRequest;
use App\Modules\Workflow\Models\ApprovalRule;
use App\Modules\Workflow\Services\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FaseLimaOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected User $user;

    protected Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create(['nama' => 'Cabang Utama', 'kode' => 'C01', 'is_active' => true]);
        $this->gudang = Gudang::create(['cabang_id' => $this->cabang->id, 'nama' => 'Gudang Pusat', 'kode' => 'GD-01', 'is_active' => true]);

        $this->user = User::factory()->create();
        $this->user->cabangs()->attach($this->cabang->id, ['is_default' => true]);

        $kategori = KategoriProduk::create(['nama' => 'Aksesoris', 'kode' => 'AKS']);
        $this->produk = Produk::create([
            'kategori_id' => $kategori->id,
            'kode' => 'PRD-ACC-01',
            'nama' => 'Kabel Type-C 100W',
            'harga_beli' => 20000,
            'harga_jual_retail' => 45000,
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $this->produk->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 10,
        ]);

        // COA dasar
        AkunCOA::firstOrCreate(['kode' => '120-03'], ['nama' => 'Piutang Shopee', 'tipe' => 'aset', 'kelompok' => 'piutang', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '410-04'], ['nama' => 'Pendapatan Channel', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit']);
        AkunCOA::firstOrCreate(['kode' => '510-02'], ['nama' => 'HPP', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '130-01'], ['nama' => 'Persediaan', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit']);
    }

    /**
     * T-23: Handler approval PO di ApprovalService mengubah status PO menjadi 'disetujui' atau 'ditolak'.
     */
    public function test_t23_approval_po_di_workflow_mengubah_status_po(): void
    {
        $approver = User::factory()->create();
        $approver->cabangs()->attach($this->cabang->id, ['is_default' => true]);
        Role::firstOrCreate(['name' => 'super-admin']);
        $approver->assignRole('super-admin');

        $supplier = Supplier::create(['nama' => 'Vendor Kabel', 'kode' => 'V-KBL', 'is_active' => true]);
        $po = PurchaseOrder::create([
            'cabang_id' => $this->cabang->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'supplier_id' => $supplier->id,
            'no_po' => 'PO-WF-001',
            'status' => 'draft',
            'total' => 500000,
        ]);

        $rule = ApprovalRule::create([
            'cabang_id' => $this->cabang->id,
            'entity_type' => 'po',
            'min_amount' => 0,
            'max_amount' => 10000000,
            'approver_role' => 'admin-toko',
            'level' => 1,
            'is_aktif' => true,
        ]);

        $request = ApprovalRequest::create([
            'approval_rule_id' => $rule->id,
            'cabang_id' => $this->cabang->id,
            'entity_type' => 'po',
            'entity_id' => $po->id,
            'requested_by' => $this->user->id,
            'status' => 'pending',
            'payload_json' => ['amount' => 500000],
        ]);

        $service = app(ApprovalService::class);

        // 1. Eksekusi persetujuan oleh approver
        $service->proses($request->id, 'disetujui', $approver->id, 'Disetujui untuk restock bulanan');

        $po->refresh();
        $this->assertSame('disetujui', $po->status);
        $this->assertStringContainsString('Disetujui oleh', (string) $po->catatan);

        // Eksekusi penolakan pada PO kedua (ApprovalRequest dibuat otomatis oleh PurchaseOrderObserver)
        $poDitolak = PurchaseOrder::create([
            'cabang_id' => $this->cabang->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'supplier_id' => $supplier->id,
            'no_po' => 'PO-WF-002',
            'status' => 'draft',
            'total' => 700000,
        ]);

        $requestTolak = ApprovalRequest::where('entity_type', 'po')
            ->where('entity_id', $poDitolak->id)
            ->first();

        $this->assertNotNull($requestTolak, 'ApprovalRequest PO seharusnya dibuat otomatis oleh PurchaseOrderObserver.');

        $service->proses($requestTolak->id, 'ditolak', $approver->id, 'Budget pengadaan habis');

        $poDitolak->refresh();
        $this->assertSame('ditolak', $poDitolak->status);
        $this->assertStringContainsString('Ditolak oleh', (string) $poDitolak->catatan);
    }

    /**
     * T-24: Fail-closed pemotongan stok omnichannel: jika stok fisik tidak mencukupi,
     * status ChannelOrder dialihkan ke 'stok_kurang' dan transaksi tidak selesai.
     */
    public function test_t24_pemotongan_stok_omnichannel_fail_closed_saat_stok_kurang(): void
    {
        $channel = Channel::create([
            'nama' => 'Shopee Official Store',
            'platform' => 'shopee',
            'is_active' => true,
            'kredensial' => [
                'cabang_id' => $this->cabang->id,
                'partner_key' => 'shopee-secret-key',
                'biaya_persen' => 5,
            ],
        ]);

        ChannelProductMapping::create([
            'channel_id' => $channel->id,
            'produk_id' => $this->produk->id,
            'gudang_id' => $this->gudang->id,
            'channel_item_id' => 'SHP-ITEM-99',
            'channel_sku' => 'PRD-ACC-01',
        ]);

        // Stok produk di gudang hanya 10, tetapi order channel meminta 15 (oversell)
        $orderPayload = [
            'order_sn' => '261108SHP001',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 675000,
            'item_list' => [
                [
                    'item_id' => 'SHP-ITEM-99',
                    'quantity_purchased' => 15,
                    'item_price' => 45000,
                ],
            ],
        ];

        $syncService = app(ChannelSyncService::class);
        $channelOrder = $syncService->prosesOrderMasuk($channel, $orderPayload);

        // ChannelOrder harus fail-closed dan berstatus 'stok_kurang'
        $this->assertSame('stok_kurang', $channelOrder->status);
        $this->assertStringContainsString('Insiden stok', (string) $channelOrder->catatan);

        // Transaksi lokal TIDAK BOLEH dibuat atau selesai
        $this->assertNull($channelOrder->transaksi_id);
        $this->assertDatabaseMissing('transaksi', [
            'no_transaksi' => 'TRX-CH-'.$channel->id.'-261108SHP001',
        ]);

        // Stok fisik di gudang tetap utuh 10
        $stokTersisa = StokItem::where('produk_id', $this->produk->id)->where('gudang_id', $this->gudang->id)->value('jumlah');
        $this->assertSame(10, (int) $stokTersisa);
    }

    /**
     * T-25: Ingestion order channel menggunakan transaksi database dengan pessimistic lock (lockForUpdate)
     * untuk mencegah duplikasi pemrosesan order pada webhook paralel.
     */
    public function test_t25_proses_order_masuk_idempoten_dan_aman_dari_race_condition(): void
    {
        $channel = Channel::create([
            'nama' => 'Shopee Official Store 2',
            'platform' => 'shopee',
            'is_active' => true,
            'kredensial' => [
                'cabang_id' => $this->cabang->id,
                'partner_key' => 'shopee-secret-key',
                'biaya_persen' => 5,
            ],
        ]);

        ChannelProductMapping::create([
            'channel_id' => $channel->id,
            'produk_id' => $this->produk->id,
            'gudang_id' => $this->gudang->id,
            'channel_item_id' => 'SHP-ITEM-88',
            'channel_sku' => 'PRD-ACC-01',
        ]);

        $orderPayload = [
            'order_sn' => '261108SHP002',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 90000,
            'item_list' => [
                [
                    'item_id' => 'SHP-ITEM-88',
                    'quantity_purchased' => 2,
                    'item_price' => 45000,
                ],
            ],
        ];

        $syncService = app(ChannelSyncService::class);

        // Simulasi webhook pertama masuk
        $order1 = $syncService->prosesOrderMasuk($channel, $orderPayload);
        $this->assertNotNull($order1->transaksi_id);

        // Simulasi webhook kedua untuk order_sn yang sama (idempoten dan tidak duplikasi transaksi)
        $order2 = $syncService->prosesOrderMasuk($channel, $orderPayload);
        $this->assertSame($order1->id, $order2->id);
        $this->assertSame($order1->transaksi_id, $order2->transaksi_id);

        $countTransaksi = Transaksi::where('no_transaksi', 'TRX-CH-'.$channel->id.'-261108SHP002')->count();
        $this->assertSame(1, $countTransaksi);
    }

    /**
     * T-26: Webhook Shopee wajib menolak request jika partner_key kosong atau signature tidak valid.
     */
    public function test_t26_webhook_shopee_tolak_jika_partner_key_kosong_atau_signature_salah(): void
    {
        // 1. Channel tanpa partner_key (kosong)
        $channelTanpaKey = Channel::create([
            'nama' => 'Shopee Unconfigured',
            'platform' => 'shopee',
            'is_active' => true,
            'kredensial' => [
                'partner_key' => '', // sengaja kosong
            ],
        ]);

        $responseTanpaKey = $this->postJson("/webhook/channel/{$channelTanpaKey->id}", [
            'order_sn' => '261108SHP999',
        ]);

        $responseTanpaKey->assertStatus(401);
        $responseTanpaKey->assertJsonFragment(['message' => 'Partner key belum dikonfigurasi pada channel.']);

        // 2. Channel dengan partner_key tetapi tanpa signature di header
        $channelValid = Channel::create([
            'nama' => 'Shopee Configured',
            'platform' => 'shopee',
            'is_active' => true,
            'kredensial' => [
                'partner_key' => 'valid-secret-key-123',
            ],
        ]);

        $responseTanpaSignature = $this->postJson("/webhook/channel/{$channelValid->id}", [
            'order_sn' => '261108SHP888',
        ]);

        $responseTanpaSignature->assertStatus(401);
        $responseTanpaSignature->assertJsonFragment(['message' => 'Signature wajib disertakan']);

        // 3. Signature tidak valid (palsu)
        $responseSignatureSalah = $this->withHeaders([
            'Authorization' => 'invalid-signature-hash',
        ])->postJson("/webhook/channel/{$channelValid->id}", [
            'order_sn' => '261108SHP888',
        ]);

        $responseSignatureSalah->assertStatus(401);
        $responseSignatureSalah->assertJsonFragment(['message' => 'Signature tidak valid']);
    }

    /**
     * T-27: Proteksi RBAC pada route DELETE /api/wms/produk/{id} dan POST /api/workflow/proses/{id}.
     */
    public function test_t27_rbac_middleware_pada_delete_produk_dan_proses_workflow(): void
    {
        Permission::firstOrCreate(['name' => 'wms.delete', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'approve-workflow', 'guard_name' => 'web']);

        $userBiasa = User::factory()->create();
        $userBiasa->cabangs()->attach($this->cabang->id, ['is_default' => true]);
        Sanctum::actingAs($userBiasa, ['*']);
        session(['cabang_id' => $this->cabang->id]);

        $produkHapus = Produk::create([
            'kategori_id' => $this->produk->kategori_id,
            'kode' => 'PRD-DEL-01',
            'nama' => 'Produk Akan Dihapus',
            'harga_beli' => 10000,
            'harga_jual_retail' => 20000,
            'is_active' => true,
        ]);

        // 1. User biasa tanpa permission wms.delete -> 403 Forbidden
        $responseHapus = $this->withSession(['cabang_id' => $this->cabang->id])
            ->deleteJson("/api/wms/produk/{$produkHapus->id}");
        $responseHapus->assertStatus(403);

        // 2. Berikan permission wms.delete -> 200 OK
        $userBiasa->givePermissionTo('wms.delete');
        $responseHapusSukses = $this->withSession(['cabang_id' => $this->cabang->id])
            ->deleteJson("/api/wms/produk/{$produkHapus->id}");
        $responseHapusSukses->assertStatus(200);

        // Pastikan produk terhapus (soft delete)
        $this->assertSoftDeleted('produk', ['id' => $produkHapus->id]);

        // 3. Test route workflow proses tanpa permission approve-workflow
        $userNonApprover = User::factory()->create();
        $userNonApprover->cabangs()->attach($this->cabang->id, ['is_default' => true]);
        Sanctum::actingAs($userNonApprover, ['*']);

        $rule = ApprovalRule::create([
            'cabang_id' => $this->cabang->id,
            'entity_type' => 'po',
            'min_amount' => 0,
            'max_amount' => 10000000,
            'approver_role' => 'admin-toko',
            'level' => 1,
            'is_aktif' => true,
        ]);

        $approvalReq = ApprovalRequest::create([
            'approval_rule_id' => $rule->id,
            'cabang_id' => $this->cabang->id,
            'entity_type' => 'po',
            'entity_id' => 1,
            'requested_by' => $userBiasa->id,
            'status' => 'pending',
            'payload_json' => ['amount' => 100000],
        ]);

        $responseProses = $this->withSession(['cabang_id' => $this->cabang->id])
            ->postJson("/api/workflow/proses/{$approvalReq->id}", [
                'action' => 'approved',
                'catatan' => 'Coba approve tanpa izin',
            ]);

        $responseProses->assertStatus(403);

        // 4. Berikan permission approve-workflow -> berhasil diproses
        $userNonApprover->givePermissionTo('approve-workflow');
        $responseProsesSukses = $this->withSession(['cabang_id' => $this->cabang->id])
            ->postJson("/api/workflow/proses/{$approvalReq->id}", [
                'action' => 'approved',
                'catatan' => 'Approve dengan izin resmi',
            ]);

        $responseProsesSukses->assertStatus(200);
    }
}
