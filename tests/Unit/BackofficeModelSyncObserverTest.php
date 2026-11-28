<?php

namespace Tests\Unit;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\RekonsiliasiBank;
use App\Modules\Akunting\Models\RekonsiliasiBankItem;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\PelangganKodeAktivasi;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Pos\Models\QrisCabang;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Sync\Models\HybridSyncOutbox;
use App\Modules\Sync\Services\SyncExecutionGuard;
use App\Modules\Sync\SyncObserverBootstrap;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Grn;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackofficeModelSyncObserverTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Gudang $gudang;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        SyncObserverBootstrap::register();

        config([
            'sync.enabled' => true,
            'sync.node_id' => 'TEST-NODE',
            'sync.remote_url' => 'https://remote.bremspace.test',
        ]);

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Observer',
            'kode' => 'CBG-OBS-01',
            'alamat' => 'Jl. Observer 1',
            'telepon' => '081200011133',
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Observer',
            'kode' => 'GDG-OBS-01',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create(['email' => 'ops@bremspace.id']);
    }

    public function test_generic_observer_emits_on_both_roles_and_attaches_fk_refs(): void
    {
        foreach (['primary_store', 'cloud_storefront'] as $idx => $role) {
            config(['sync.node_role' => $role]);

            $supplier = Supplier::create([
                'nama' => 'Supplier '.$role,
                'kode_lama' => 'SUP-'.$idx,
                'telepon' => '0812345600'.$idx,
                'status_aktif' => true,
            ]);

            $po = PurchaseOrder::create([
                'no_po' => 'PO-OBS-00'.$idx,
                'supplier_id' => $supplier->id,
                'gudang_tujuan_id' => $this->gudang->id,
                'status' => 'draft',
                'metode_bayar' => 'tunai',
                'total' => 500000,
            ]);

            $outbox = HybridSyncOutbox::where('event_type', 'purchase_order.upsert')
                ->where('entity_id', $po->id)
                ->first();

            $this->assertNotNull($outbox, "Outbox must be created for node_role={$role}");
            $this->assertNotEmpty($outbox->payload['sync_uuid'] ?? null);
            $this->assertSame($this->cabang->id, $outbox->cabang_id);
            $this->assertSame('Supplier '.$role, $outbox->payload['_refs']['supplier_id']['nama'] ?? null);
            $this->assertSame('GDG-OBS-01', $outbox->payload['_refs']['gudang_tujuan_id']['kode'] ?? null);

            $po->update(['catatan' => 'Revisi PO '.$role]);
            $this->assertSame(
                2,
                HybridSyncOutbox::where('event_type', 'purchase_order.upsert')
                    ->where('entity_id', $po->id)
                    ->count()
            );

            $po->delete();
            $this->assertDatabaseHas('hybrid_sync_outbox', [
                'event_type' => 'purchase_order.delete',
                'entity_id' => $po->id,
            ]);
        }
    }

    public function test_array_cast_decoded_and_sensitive_columns_stripped(): void
    {
        config(['sync.node_role' => 'cloud_storefront']);

        $supplier = Supplier::create([
            'nama' => 'Supplier Cast',
            'kode_lama' => 'SUP-CAST',
            'status_aktif' => true,
        ]);

        $po = PurchaseOrder::create([
            'no_po' => 'PO-CAST-01',
            'supplier_id' => $supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'status' => 'disetujui',
            'metode_bayar' => 'tunai',
            'total' => 100000,
        ]);

        $grn = Grn::create([
            'no_grn' => 'GRN-CAST-01',
            'po_id' => $po->id,
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'user_id' => $this->user->id,
            'status' => 'terima',
            'total_hpp' => 100000,
            'dpp' => 100000,
            'ppn_nominal' => 0,
            'ppn_percent' => 0,
            'item_qty_received' => [
                ['produk_id' => 1, 'qty_received' => 5],
            ],
        ]);

        $grnOutbox = HybridSyncOutbox::where('event_type', 'grn.upsert')
            ->where('entity_id', $grn->id)
            ->firstOrFail();

        // Payload harus sudah terdekode menjadi array PHP (bukan JSON string mentah)
        $this->assertIsArray($grnOutbox->payload['item_qty_received']);
        $this->assertSame(5, $grnOutbox->payload['item_qty_received'][0]['qty_received']);

        $qris = QrisCabang::create([
            'cabang_id' => $this->cabang->id,
            'payload_static' => '000201010211',
            'merchant_name' => 'Bremspace',
            'merchant_city' => 'Jakarta',
            'postal_code' => '10110',
            'nmid' => 'ID12345',
            'is_active' => true,
            'webhook_secret' => 'rahasia-qris-tidak-boleh-sinkron',
        ]);

        $qrisOutbox = HybridSyncOutbox::where('event_type', 'qris_cabang.upsert')
            ->where('entity_id', $qris->id)
            ->firstOrFail();

        $this->assertArrayNotHasKey('webhook_secret', $qrisOutbox->payload);
    }

    public function test_execution_guard_and_disabled_flag_suppress_generic_observer(): void
    {
        $initialCount = HybridSyncOutbox::count();

        SyncExecutionGuard::runWithoutSync(function () {
            Supplier::create([
                'nama' => 'Supplier Guarded',
                'kode_lama' => 'SUP-GRD-'.Str::random(4),
                'status_aktif' => true,
            ]);
        });
        $this->assertSame($initialCount, HybridSyncOutbox::count());

        config(['sync.enabled' => false]);
        Supplier::create([
            'nama' => 'Supplier Disabled',
            'kode_lama' => 'SUP-DIS-'.Str::random(4),
            'status_aktif' => true,
        ]);
        $this->assertSame($initialCount, HybridSyncOutbox::count());
    }

    public function test_hard_delete_and_create_delete_in_same_transaction_preserve_refs_without_errors(): void
    {
        config(['sync.node_role' => 'primary_store']);

        $supplier = Supplier::create([
            'nama' => 'Supplier Delete Tx',
            'kode_lama' => 'SUP-DEL-TX',
            'status_aktif' => true,
        ]);

        $po = PurchaseOrder::create([
            'no_po' => 'PO-DEL-TX-01',
            'supplier_id' => $supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'status' => 'draft',
            'metode_bayar' => 'tunai',
            'total' => 250000,
        ]);

        HybridSyncOutbox::query()->delete();

        // Hapus child dan parent sekaligus di dalam satu transaksi database
        DB::transaction(function () use ($po, $supplier): void {
            $po->delete();
            $supplier->delete();
        });

        $poDelete = HybridSyncOutbox::where('event_type', 'purchase_order.delete')
            ->where('entity_id', $po->id)
            ->firstOrFail();

        $this->assertSame('PO-DEL-TX-01', $poDelete->payload['no_po'] ?? null);
        $this->assertSame('Supplier Delete Tx', $poDelete->payload['_refs']['supplier_id']['nama'] ?? null);
        $this->assertDatabaseHas('hybrid_sync_outbox', [
            'event_type' => 'supplier.delete',
            'entity_id' => $supplier->id,
        ]);

        HybridSyncOutbox::query()->delete();

        // Buat & hapus dalam 1 transaksi: upsert tidak boleh lempar error/tumbuh hantu, delete tercatat aman
        $ephemeralId = DB::transaction(function (): int {
            $s = Supplier::create([
                'nama' => 'Supplier Ephemeral',
                'kode_lama' => 'SUP-EPH',
                'status_aktif' => true,
            ]);
            $id = (int) $s->id;
            $s->delete();

            return $id;
        });

        $this->assertDatabaseMissing('hybrid_sync_outbox', [
            'event_type' => 'supplier.upsert',
            'entity_id' => $ephemeralId,
        ]);
        $this->assertDatabaseHas('hybrid_sync_outbox', [
            'event_type' => 'supplier.delete',
            'entity_id' => $ephemeralId,
        ]);
    }

    public function test_specialized_master_observers_emit_delete_and_fk_metadata(): void
    {
        config(['sync.node_role' => 'primary_store']);

        AkunCOA::firstOrCreate(
            ['kode' => '110-04'],
            ['nama' => 'Kas Toko', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit', 'is_active' => true]
        );
        AkunCOA::firstOrCreate(
            ['kode' => '410-01'],
            ['nama' => 'Pendapatan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit', 'is_active' => true]
        );

        $supplier = Supplier::create([
            'nama' => 'Supplier Produk Master',
            'kode_lama' => 'SUP-PRD-M',
            'status_aktif' => true,
        ]);

        $brand = Brand::create(['nama' => 'Brand Hapus Test', 'is_active' => true]);
        $kategori = KategoriProduk::create(['nama' => 'Kategori Hapus Test', 'slug' => 'kategori-hapus-test', 'is_active' => true]);
        $produk = Produk::create([
            'nama' => 'Produk Dengan Supplier',
            'slug' => 'produk-dengan-supplier',
            'barcode' => 'BC-SUP-M-1',
            'kategori' => 'Umum',
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'supplier_id' => $supplier->id,
            'harga_beli' => 10000,
            'harga_jual_retail' => 20000,
            'is_active' => true,
        ]);
        $variant = SkuVariant::create([
            'produk_id' => $produk->id,
            'sku' => 'SKU-DEL-01',
            'nama_varian' => 'Hitam',
            'is_active' => true,
        ]);
        $pelanggan = Pelanggan::create([
            'nama' => 'Pelanggan Approved',
            'telepon' => '081234500999',
            'approved_by' => $this->user->id,
        ]);
        $tiket = TiketServis::create([
            'cabang_id' => $this->cabang->id,
            'no_tiket' => 'SRV-OBS-DEL-1',
            'nama_pelanggan' => 'Budi',
            'telepon_pelanggan' => '081234500999',
            'jenis_hp' => 'iPhone 13',
            'keluhan' => 'Cek',
            'status' => 'diterima',
        ]);

        $jurnalNo = app(JurnalService::class)->generateNoJurnal('servis', $this->cabang->id);
        app(JurnalService::class)->post(
            noJurnal: $jurnalNo,
            tanggal: now(),
            sumber: 'servis',
            lines: [
                ['akun_kode' => '110-04', 'debit' => 50000, 'kredit' => 0],
                ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 50000],
            ],
            deskripsi: 'Servis obs ref test',
            cabangId: $this->cabang->id,
            userId: $this->user->id,
            referensiTipe: TiketServis::class,
            referensiId: $tiket->id
        );

        $produkOutbox = HybridSyncOutbox::where('event_type', 'produk.upsert')->where('entity_id', $produk->id)->firstOrFail();
        $this->assertSame('Supplier Produk Master', $produkOutbox->payload['supplier_nama'] ?? null);
        $this->assertSame($supplier->sync_uuid, $produkOutbox->payload['supplier_sync_uuid'] ?? null);

        $pelangganOutbox = HybridSyncOutbox::where('event_type', 'pelanggan.upsert')->where('entity_id', $pelanggan->id)->firstOrFail();
        $this->assertSame($this->user->email, $pelangganOutbox->payload['approved_by_email'] ?? null);

        $jurnalOutbox = HybridSyncOutbox::where('event_type', 'jurnal.upsert')->latest('id')->firstOrFail();
        $this->assertSame(['no_tiket' => 'SRV-OBS-DEL-1'], $jurnalOutbox->payload['referensi_natural'] ?? null);
        $this->assertNotEmpty($jurnalOutbox->payload['referensi_sync_uuid'] ?? null);

        HybridSyncOutbox::query()->delete();

        $brand->delete();
        $kategori->delete();
        $variant->delete();
        $tiket->delete();
        $produk->forceDelete();

        $this->assertTrue(HybridSyncOutbox::where('event_type', 'brand.upsert')->get()->contains(fn ($o) => ($o->payload['deleted'] ?? false) === true && ($o->payload['nama'] ?? '') === 'Brand Hapus Test'));
        $this->assertTrue(HybridSyncOutbox::where('event_type', 'kategori_produk.upsert')->get()->contains(fn ($o) => ($o->payload['deleted'] ?? false) === true && ($o->payload['slug'] ?? '') === 'kategori-hapus-test'));
        $this->assertTrue(HybridSyncOutbox::where('event_type', 'sku_variant.upsert')->get()->contains(fn ($o) => ($o->payload['deleted'] ?? false) === true && ($o->payload['sku'] ?? '') === 'SKU-DEL-01'));
        $this->assertTrue(HybridSyncOutbox::where('event_type', 'servis.update')->get()->contains(fn ($o) => ($o->payload['deleted'] ?? false) === true && ($o->payload['no_tiket'] ?? '') === 'SRV-OBS-DEL-1'));
        $this->assertTrue(HybridSyncOutbox::where('event_type', 'produk.upsert')->get()->contains(fn ($o) => ($o->payload['force_deleted'] ?? false) === true && ($o->payload['slug'] ?? '') === 'produk-dengan-supplier'));
    }

    public function test_rekonsiliasi_pelanggan_aktivasi_and_safe_konfigurasi_emit_sync_events(): void
    {
        config(['sync.node_role' => 'primary_store']);

        $coa = AkunCOA::firstOrCreate(
            ['kode' => '110-02'],
            ['nama' => 'Bank BCA', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit', 'is_active' => true]
        );
        $pelanggan = Pelanggan::create([
            'nama' => 'Member Aktivasi',
            'telepon' => '081299988877',
        ]);

        HybridSyncOutbox::query()->delete();

        $rekon = RekonsiliasiBank::create([
            'cabang_id' => $this->cabang->id,
            'akun_coa_id' => $coa->id,
            'nama_bank' => 'BCA',
            'periode_dari' => '2026-11-01',
            'periode_sampai' => '2026-11-30',
            'nama_file' => 'mutasi-nov.csv',
            'format_file' => 'csv',
            'status' => 'draft',
            'user_id' => $this->user->id,
        ]);
        $rekonItem = RekonsiliasiBankItem::create([
            'rekonsiliasi_bank_id' => $rekon->id,
            'tanggal' => '2026-11-10',
            'deskripsi' => 'Setoran QRIS',
            'tipe' => 'CR',
            'nominal' => 150000,
            'status' => 'unmatched',
        ]);
        PelangganKodeAktivasi::create([
            'pelanggan_id' => $pelanggan->id,
            'kode' => 'AKT9988',
            'kedaluwarsa_at' => now()->addDay(),
            'dibuat_oleh' => $this->user->id,
        ]);

        $cfg = app(KonfigurasiService::class);
        $cfg->set('ppn_percent', 12, 'Update PPN');
        $cfg->set('hybrid_sync_config', ['api_secret' => 'rahasia-lokal']);
        $cfg->set('wa_api_key', 'token-wa-rahasia');

        $this->assertDatabaseHas('hybrid_sync_outbox', ['event_type' => 'rekonsiliasi_bank.upsert', 'entity_id' => $rekon->id, 'cabang_id' => $this->cabang->id]);
        $this->assertDatabaseHas('hybrid_sync_outbox', ['event_type' => 'rekonsiliasi_bank_item.upsert', 'entity_id' => $rekonItem->id, 'cabang_id' => $this->cabang->id]);
        $this->assertDatabaseHas('hybrid_sync_outbox', ['event_type' => 'pelanggan_kode_aktivasi.upsert']);
        $konfigEvents = HybridSyncOutbox::where('event_type', 'konfigurasi.upsert')->get();
        $this->assertCount(1, $konfigEvents);
        $this->assertSame('ppn_percent', $konfigEvents->first()->payload['kunci'] ?? null);
    }
}
