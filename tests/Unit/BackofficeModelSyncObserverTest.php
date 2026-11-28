<?php

namespace Tests\Unit;

use App\Models\User;
use App\Modules\Pos\Models\QrisCabang;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Sync\Models\HybridSyncOutbox;
use App\Modules\Sync\Services\SyncExecutionGuard;
use App\Modules\Sync\SyncObserverBootstrap;
use App\Modules\Wms\Models\Grn;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
