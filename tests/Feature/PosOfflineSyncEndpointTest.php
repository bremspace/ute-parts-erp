<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosOfflineSyncEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected User $user;

    protected Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Sync Test',
            'kode' => 'CBG-SYNC',
            'alamat' => 'Jl. Offline No. 99',
            'telepon' => '081299999999',
        ]);

        $this->gudang = Gudang::create([
            'kode' => 'GDG-SYNC-01',
            'nama' => 'Gudang Sync',
            'cabang_id' => $this->cabang->id,
            'is_utama' => true,
        ]);

        $this->user = User::factory()->create();
        $this->user->assignRole('admin-toko');
        $this->user->cabangs()->attach($this->cabang->id);

        $this->produk = Produk::create([
            'nama' => 'Kabel Data Type C Super',
            'barcode' => '899888777111',
            'harga_beli' => 10000,
            'harga_jual_retail' => 25000,
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $this->produk->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 20,
        ]);
    }

    public function test_sync_offline_processes_transactions_successfully(): void
    {
        $uuid = (string) Str::uuid();

        $payload = [
            'client_batch_id' => 'BATCH-TEST-001',
            'transactions' => [
                [
                    'offline_uuid' => $uuid,
                    'client_created_at' => now()->subMinutes(15)->toIso8601String(),
                    'gudang_id' => $this->gudang->id,
                    'metode_bayar' => 'tunai',
                    'total_nominal' => 50000,
                    'uang_diterima' => 50000,
                    'kembalian' => 0,
                    'items' => [
                        [
                            'produk_id' => $this->produk->id,
                            'qty' => 2,
                            'harga_satuan' => 25000,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->withSession(['cabang_id' => $this->cabang->id])
            ->postJson('/api/pos/sync-offline?cabang_id='.$this->cabang->id, $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'total_payload' => 1,
                'total_success' => 1,
                'total_conflicts' => 0,
            ],
        ]);

        $this->assertDatabaseHas('transaksi', [
            'offline_uuid' => $uuid,
            'cabang_id' => $this->cabang->id,
            'is_offline' => 1,
            'sync_status' => 'synced',
        ]);

        $stokTersisa = StokItem::where('produk_id', $this->produk->id)
            ->where('gudang_id', $this->gudang->id)
            ->value('jumlah');

        $this->assertEquals(18, $stokTersisa);
    }

    public function test_sync_offline_is_idempotent(): void
    {
        $uuid = (string) Str::uuid();

        $payload = [
            'client_batch_id' => 'BATCH-IDEMPOTENT',
            'transactions' => [
                [
                    'offline_uuid' => $uuid,
                    'client_created_at' => now()->subMinutes(10)->toIso8601String(),
                    'gudang_id' => $this->gudang->id,
                    'metode_bayar' => 'tunai',
                    'total_nominal' => 25000,
                    'uang_diterima' => 30000,
                    'kembalian' => 5000,
                    'items' => [
                        [
                            'produk_id' => $this->produk->id,
                            'qty' => 1,
                            'harga_satuan' => 25000,
                        ],
                    ],
                ],
            ],
        ];

        // First call
        $first = $this->actingAs($this->user, 'sanctum')
            ->withSession(['cabang_id' => $this->cabang->id])
            ->postJson('/api/pos/sync-offline?cabang_id='.$this->cabang->id, $payload);

        $first->assertStatus(200);
        $this->assertEquals(1, Transaksi::where('offline_uuid', $uuid)->count());

        // Second call with same UUID
        $second = $this->actingAs($this->user, 'sanctum')
            ->withSession(['cabang_id' => $this->cabang->id])
            ->postJson('/api/pos/sync-offline?cabang_id='.$this->cabang->id, $payload);

        $second->assertStatus(200);
        $second->assertJson([
            'success' => true,
            'data' => [
                'total_payload' => 1,
                'total_success' => 1,
                'results' => [
                    [
                        'offline_uuid' => $uuid,
                        'status' => 'already_synced',
                    ],
                ],
            ],
        ]);

        // Still only 1 transaction, stock deducted only once (20 - 1 = 19)
        $this->assertEquals(1, Transaksi::where('offline_uuid', $uuid)->count());
        $stokTersisa = StokItem::where('produk_id', $this->produk->id)
            ->where('gudang_id', $this->gudang->id)
            ->value('jumlah');
        $this->assertEquals(19, $stokTersisa);
    }

    public function test_sync_offline_handles_negative_stock_reconciliation(): void
    {
        $uuid = (string) Str::uuid();

        // Kuantitas diminta 25, sedangkan stok cuma 20
        $payload = [
            'transactions' => [
                [
                    'offline_uuid' => $uuid,
                    'client_created_at' => now()->toIso8601String(),
                    'gudang_id' => $this->gudang->id,
                    'metode_bayar' => 'tunai',
                    'total_nominal' => 25000 * 25,
                    'items' => [
                        [
                            'produk_id' => $this->produk->id,
                            'qty' => 25,
                            'harga_satuan' => 25000,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->withSession(['cabang_id' => $this->cabang->id])
            ->postJson('/api/pos/sync-offline?cabang_id='.$this->cabang->id, $payload);

        $response->assertStatus(200);

        // Transaksi tetap tersimpan dan stok menjadi -5 (20 - 25)
        $this->assertDatabaseHas('transaksi', [
            'offline_uuid' => $uuid,
            'is_offline' => 1,
        ]);

        $stokTersisa = StokItem::where('produk_id', $this->produk->id)
            ->where('gudang_id', $this->gudang->id)
            ->value('jumlah');

        $this->assertEquals(-5, $stokTersisa);
    }
}
