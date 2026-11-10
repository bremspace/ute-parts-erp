<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReturnOfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected User $user;

    protected Produk $produkOriginal;

    protected Produk $produkPengganti;

    protected Transaksi $transaksi;

    protected TransaksiItem $transaksiItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Retur Offline',
            'kode' => 'CBG-RTR-OFF',
            'alamat' => 'Jl. Retur No. 12',
            'telepon' => '081233445566',
        ]);

        $this->gudang = Gudang::create([
            'kode' => 'GDG-RTR-01',
            'nama' => 'Gudang Retur',
            'cabang_id' => $this->cabang->id,
            'is_utama' => true,
        ]);

        $this->user = User::factory()->create();
        $this->user->assignRole('admin-toko');
        $this->user->cabangs()->attach($this->cabang->id);

        $this->produkOriginal = Produk::create([
            'nama' => 'Headset Bluetooth Bass',
            'barcode' => '899123456001',
            'harga_beli' => 50000,
            'harga_jual_retail' => 85000,
            'is_active' => true,
        ]);

        $this->produkPengganti = Produk::create([
            'nama' => 'Headset Bluetooth Bass Baru',
            'barcode' => '899123456002',
            'harga_beli' => 50000,
            'harga_jual_retail' => 85000,
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $this->produkOriginal->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 10,
        ]);

        StokItem::create([
            'produk_id' => $this->produkPengganti->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 10,
        ]);

        $this->transaksi = Transaksi::create([
            'no_transaksi' => 'TRX-RTR-999',
            'cabang_id' => $this->cabang->id,
            'user_id' => $this->user->id,
            'total_harga' => 85000,
            'total_bayar' => 85000,
            'metode_bayar' => 'tunai',
            'status' => 'selesai',
        ]);

        $this->transaksiItem = TransaksiItem::create([
            'transaksi_id' => $this->transaksi->id,
            'produk_id' => $this->produkOriginal->id,
            'jumlah' => 1,
            'harga_satuan' => 85000,
            'subtotal' => 85000,
        ]);
    }

    public function test_sync_retur_offline_processes_tukar_unit_successfully(): void
    {
        $uuid = (string) Str::uuid();

        $payload = [
            'client_batch_id' => 'BATCH-RTR-001',
            'returns' => [
                [
                    'retur_uuid' => $uuid,
                    'no_transaksi_asal' => $this->transaksi->no_transaksi,
                    'alasan' => 'Mic tidak bersuara, tukar unit baru',
                    'tipe_retur' => 'tukar_unit',
                    'client_created_at' => now()->subMinutes(10)->toIso8601String(),
                    'items' => [
                        [
                            'produk_id' => $this->produkOriginal->id,
                            'qty' => 1,
                            'kondisi_barang' => 'rusak',
                            'produk_pengganti_id' => $this->produkPengganti->id,
                            'qty_pengganti' => 1,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->withSession(['cabang_id' => $this->cabang->id])
            ->postJson('/api/pos/sync-retur?cabang_id='.$this->cabang->id, $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('data.synced_count', 1);
        $response->assertJsonPath('data.failed_count', 0);

        $retur = ReturnPenjualan::where('offline_uuid', $uuid)->first();
        $this->assertNotNull($retur);
        $this->assertTrue((bool) $retur->is_offline);
        $this->assertEquals('tukar_produk', $retur->tipe_penyelesaian);
        $this->assertEquals('synced', $retur->sync_status);
    }

    public function test_sync_retur_offline_is_idempotent(): void
    {
        $uuid = (string) Str::uuid();

        $payload = [
            'returns' => [
                [
                    'retur_uuid' => $uuid,
                    'no_transaksi_asal' => $this->transaksi->no_transaksi,
                    'alasan' => 'Tukar unit 1-to-1',
                    'items' => [
                        [
                            'produk_id' => $this->produkOriginal->id,
                            'qty' => 1,
                            'kondisi_barang' => 'rusak',
                            'produk_pengganti_id' => $this->produkPengganti->id,
                            'qty_pengganti' => 1,
                        ],
                    ],
                ],
            ],
        ];

        // Call 1
        $res1 = $this->actingAs($this->user, 'sanctum')
            ->withSession(['cabang_id' => $this->cabang->id])
            ->postJson('/api/pos/sync-retur?cabang_id='.$this->cabang->id, $payload);
        $res1->assertStatus(200);
        $res1->assertJsonPath('data.synced_count', 1);

        // Call 2
        $res2 = $this->actingAs($this->user, 'sanctum')
            ->withSession(['cabang_id' => $this->cabang->id])
            ->postJson('/api/pos/sync-retur?cabang_id='.$this->cabang->id, $payload);
        $res2->assertStatus(200);
        $res2->assertJsonPath('data.synced_count', 1);
        $res2->assertJsonPath('data.results.0.status', 'already_synced');

        // Pastikan hanya 1 record yang tersimpan di DB
        $this->assertEquals(1, ReturnPenjualan::where('offline_uuid', $uuid)->count());
    }

    public function test_sync_retur_offline_creates_draft_when_receipt_not_found(): void
    {
        $uuid = (string) Str::uuid();

        $payload = [
            'returns' => [
                [
                    'retur_uuid' => $uuid,
                    'no_transaksi_asal' => 'TRX-UNKNOWN-NOTA-FISIK-001',
                    'alasan' => 'Nota lama struk thermal buram',
                    'items' => [
                        [
                            'produk_id' => $this->produkOriginal->id,
                            'qty' => 1,
                            'kondisi_barang' => 'rusak',
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->withSession(['cabang_id' => $this->cabang->id])
            ->postJson('/api/pos/sync-retur?cabang_id='.$this->cabang->id, $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('data.synced_count', 1);
        $response->assertJsonPath('data.results.0.status', 'draft_created');

        $returDraft = ReturnPenjualan::where('offline_uuid', $uuid)->first();
        $this->assertNotNull($returDraft);
        $this->assertEquals('draft', $returDraft->status);
        $this->assertEquals('draft_pending_approval', $returDraft->sync_status);
        $this->assertTrue((bool) $returDraft->is_offline);
    }
}
