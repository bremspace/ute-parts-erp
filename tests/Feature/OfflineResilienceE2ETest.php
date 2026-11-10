<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflineResilienceE2ETest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected User $kasir1;

    protected User $kasir2;

    protected Produk $produkA;

    protected Produk $produkB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create([
            'nama' => 'Cabang E2E Hardening',
            'kode' => 'CBG-E2E',
            'alamat' => 'Jl. Pengujian Resiliensi No. 88',
            'telepon' => '081122334455',
        ]);

        $this->gudang = Gudang::create([
            'kode' => 'GDG-E2E-01',
            'nama' => 'Gudang Utama E2E',
            'cabang_id' => $this->cabang->id,
            'is_utama' => true,
        ]);

        $this->kasir1 = User::factory()->create(['name' => 'Kasir 1 Device A']);
        $this->kasir1->assignRole('admin-toko');
        $this->kasir1->cabangs()->attach($this->cabang->id);

        $this->kasir2 = User::factory()->create(['name' => 'Kasir 2 Device B']);
        $this->kasir2->assignRole('admin-toko');
        $this->kasir2->cabangs()->attach($this->cabang->id);

        $this->produkA = Produk::create([
            'nama' => 'Tempered Glass Privacy 9D',
            'barcode' => '89990001',
            'harga_beli' => 15000,
            'harga_jual_retail' => 45000,
            'is_active' => true,
        ]);

        $this->produkB = Produk::create([
            'nama' => 'Adaptor Charger 20W Fast',
            'barcode' => '89990002',
            'harga_beli' => 35000,
            'harga_jual_retail' => 95000,
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $this->produkA->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 50,
        ]);

        StokItem::create([
            'produk_id' => $this->produkB->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 20,
        ]);
    }

    /**
     * E2E Simulasi Mode Pesawat:
     * 1. Cashier offline actions (POS, Servis Handover, Retur Unit Swap)
     * 2. Reconnection & Batch Sync verification
     * 3. Stock deduction & state consistency checks
     */
    public function test_airplane_mode_simulation_full_flow(): void
    {
        // 1. Setup existing state on server before airplane mode
        $tiketServis = TiketServis::create([
            'no_tiket' => 'SRV-E2E-001',
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Hendro Wijaya',
            'telepon_pelanggan' => '081234567890',
            'jenis_hp' => 'Samsung S21',
            'keluhan' => 'Ganti LCD',
            'status' => 'selesai',
            'status_pembayaran' => 'belum_lunas',
            'estimasi_biaya' => 250000,
            'tanggal_terima' => now()->subDay(),
            'tanggal_selesai' => now()->subHours(2),
        ]);

        $transaksiAsal = Transaksi::create([
            'no_transaksi' => 'TRX-ORIGIN-001',
            'cabang_id' => $this->cabang->id,
            'user_id' => $this->kasir1->id,
            'total_harga' => 95000,
            'total_bayar' => 95000,
            'metode_bayar' => 'tunai',
            'status' => 'selesai',
        ]);

        TransaksiItem::create([
            'transaksi_id' => $transaksiAsal->id,
            'produk_id' => $this->produkB->id,
            'jumlah' => 1,
            'harga_satuan' => 95000,
            'subtotal' => 95000,
        ]);

        // ================= SIMULASI OFFLINE (MODE PESAWAT) =================
        // Client Outbox UUIDs
        $posUuid = (string) Str::uuid();
        $servisUuid = (string) Str::uuid();
        $returUuid = (string) Str::uuid();

        // ================= KONEKSI PULIH: BATCH SYNC =================

        // 1. Sync POS Penjualan (2x Tempered Glass)
        $posPayload = [
            'client_batch_id' => 'BATCH-E2E-POS-01',
            'transactions' => [
                [
                    'offline_uuid' => $posUuid,
                    'client_created_at' => now()->subMinutes(10)->toIso8601String(),
                    'gudang_id' => $this->gudang->id,
                    'metode_bayar' => 'tunai',
                    'total_nominal' => 90000,
                    'uang_diterima' => 100000,
                    'kembalian' => 10000,
                    'items' => [
                        [
                            'produk_id' => $this->produkA->id,
                            'qty' => 2,
                            'harga_satuan' => 45000,
                        ],
                    ],
                ],
            ],
        ];

        $posRes = $this->actingAs($this->kasir1, 'sanctum')
            ->withSession(['cabang_id' => $this->cabang->id])
            ->postJson('/api/pos/sync-offline?cabang_id='.$this->cabang->id, $posPayload);

        $posRes->assertStatus(200);
        $posRes->assertJsonPath('data.total_success', 1);

        // Verifikasi stok produk A berkurang (50 - 2 = 48)
        $stokA = StokItem::where('produk_id', $this->produkA->id)->where('gudang_id', $this->gudang->id)->value('jumlah');
        $this->assertEquals(48, $stokA);

        // 2. Sync Pengambilan Servis (Handover unit & pelunasan Rp 250.000)
        $servisPayload = [
            'client_batch_id' => 'BATCH-E2E-SRV-01',
            'pickups' => [
                [
                    'pickup_uuid' => $servisUuid,
                    'no_tiket' => $tiketServis->no_tiket,
                    'nama_pengambil' => 'Hendro Sendiri',
                    'metode_pembayaran' => 'tunai',
                    'nominal_pelunasan' => 250000,
                    'client_created_at' => now()->subMinutes(8)->toIso8601String(),
                ],
            ],
        ];

        $servisRes = $this->actingAs($this->kasir1, 'sanctum')
            ->withSession(['cabang_id' => $this->cabang->id])
            ->postJson('/api/servis/sync-pickup?cabang_id='.$this->cabang->id, $servisPayload);

        $servisRes->assertStatus(200);
        $servisRes->assertJsonPath('data.synced_count', 1);

        $tiketServis->refresh();
        $this->assertEquals('diambil', $tiketServis->status);
        $this->assertEquals('lunas', $tiketServis->status_pembayaran);
        $this->assertEquals($servisUuid, $tiketServis->offline_pickup_uuid);

        // 3. Sync Retur Tukar Unit (1x Adaptor Charger rusak tukar baru)
        $returPayload = [
            'client_batch_id' => 'BATCH-E2E-RTR-01',
            'returns' => [
                [
                    'retur_uuid' => $returUuid,
                    'no_transaksi_asal' => $transaksiAsal->no_transaksi,
                    'alasan' => 'Port charger kendor mati, tukar baru',
                    'tipe_retur' => 'tukar_unit',
                    'items' => [
                        [
                            'produk_id' => $this->produkB->id,
                            'qty' => 1,
                            'kondisi' => 'rusak',
                            'keterangan' => 'Unit lama mati total',
                            'produk_pengganti_id' => $this->produkB->id,
                            'qty_pengganti' => 1,
                        ],
                    ],
                    'client_created_at' => now()->subMinutes(5)->toIso8601String(),
                ],
            ],
        ];

        $returRes = $this->actingAs($this->kasir1, 'sanctum')
            ->withSession(['cabang_id' => $this->cabang->id])
            ->postJson('/api/pos/sync-retur?cabang_id='.$this->cabang->id, $returPayload);

        $returRes->assertStatus(200);
        $returRes->assertJsonPath('data.synced_count', 1);

        $returRecord = ReturnPenjualan::where('offline_uuid', $returUuid)->first();
        $this->assertNotNull($returRecord);
        $this->assertEquals('selesai', $returRecord->status);
        $this->assertEquals('tukar_produk', $returRecord->tipe_penyelesaian);
    }

    /**
     * Multi-device Race Condition:
     * Kasir 1 (Device A) dan Kasir 2 (Device B) menyerahkan tiket servis yang sama saat offline.
     * Device A sync lebih dulu -> berhasil.
     * Device B sync berikutnya -> ditolak dengan status conflict (bukan overwrite dan bukan double-payment).
     */
    public function test_multi_device_conflict_servis_handover(): void
    {
        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-RACE-001',
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Siti Nurhaliza',
            'telepon_pelanggan' => '081299998888',
            'jenis_hp' => 'iPhone 11',
            'keluhan' => 'Speaker kresek-kresek',
            'status' => 'selesai',
            'status_pembayaran' => 'belum_lunas',
            'estimasi_biaya' => 150000,
            'tanggal_terima' => now()->subDays(3),
            'tanggal_selesai' => now()->subHours(1),
        ]);

        $uuidDeviceA = (string) Str::uuid();
        $uuidDeviceB = (string) Str::uuid();

        // Device A syncs first
        $resA = $this->actingAs($this->kasir1, 'sanctum')
            ->withSession(['cabang_id' => $this->cabang->id])
            ->postJson('/api/servis/sync-pickup?cabang_id='.$this->cabang->id, [
                'pickups' => [
                    [
                        'pickup_uuid' => $uuidDeviceA,
                        'no_tiket' => $tiket->no_tiket,
                        'nama_pengambil' => 'Siti (Kasir 1)',
                        'metode_pembayaran' => 'tunai',
                        'nominal_pelunasan' => 150000,
                    ],
                ],
            ]);

        $resA->assertStatus(200);
        $resA->assertJsonPath('data.synced_count', 1);
        $resA->assertJsonPath('data.results.0.status', 'synced');

        // Device B tries to sync the same ticket with another UUID
        $resB = $this->actingAs($this->kasir2, 'sanctum')
            ->withSession(['cabang_id' => $this->cabang->id])
            ->postJson('/api/servis/sync-pickup?cabang_id='.$this->cabang->id, [
                'pickups' => [
                    [
                        'pickup_uuid' => $uuidDeviceB,
                        'no_tiket' => $tiket->no_tiket,
                        'nama_pengambil' => 'Adik Siti (Kasir 2)',
                        'metode_pembayaran' => 'tunai',
                        'nominal_pelunasan' => 150000,
                    ],
                ],
            ]);

        $resB->assertStatus(200);
        $resB->assertJsonPath('data.synced_count', 0);
        $resB->assertJsonPath('data.failed_count', 1);
        $resB->assertJsonPath('data.results.0.status', 'conflict');
        $this->assertStringContainsString('sudah diserahkan', $resB->json('data.results.0.message'));

        // Tiket tetap memegang UUID milik Device A
        $tiket->refresh();
        $this->assertEquals($uuidDeviceA, $tiket->offline_pickup_uuid);
    }

    /**
     * Memverifikasi aturan ISO / Enterprise:
     * Penolakan pengembalian uang tunai saat offline.
     */
    public function test_offline_retur_rejects_cash_refund(): void
    {
        $uuid = (string) Str::uuid();

        $payload = [
            'returns' => [
                [
                    'retur_uuid' => $uuid,
                    'no_transaksi_asal' => 'TRX-DUMMY-001',
                    'alasan' => 'Minta uang kembali tunai saat offline',
                    'tipe_retur' => 'refund',
                    'items' => [
                        [
                            'produk_id' => $this->produkA->id,
                            'qty' => 1,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->kasir1, 'sanctum')
            ->withSession(['cabang_id' => $this->cabang->id])
            ->postJson('/api/pos/sync-retur?cabang_id='.$this->cabang->id, $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('data.synced_count', 0);
        $response->assertJsonPath('data.failed_count', 1);
        $this->assertStringContainsString('tidak diizinkan', $response->json('data.results.0.message'));
    }

    /**
     * Audit Penggunaan Memori VPS (< 32MB Peak Delta) saat Batch Sync.
     */
    public function test_memory_usage_during_large_batch_sync_is_well_under_limit(): void
    {
        $transactions = [];
        for ($i = 1; $i <= 30; $i++) {
            $transactions[] = [
                'offline_uuid' => (string) Str::uuid(),
                'client_created_at' => now()->subMinutes(rand(1, 100))->toIso8601String(),
                'gudang_id' => $this->gudang->id,
                'metode_bayar' => 'tunai',
                'total_nominal' => 45000,
                'uang_diterima' => 50000,
                'kembalian' => 5000,
                'items' => [
                    [
                        'produk_id' => $this->produkA->id,
                        'qty' => 1,
                        'harga_satuan' => 45000,
                    ],
                ],
            ];
        }

        $baselineMemory = memory_get_usage(true);

        $response = $this->actingAs($this->kasir1, 'sanctum')
            ->withSession(['cabang_id' => $this->cabang->id])
            ->postJson('/api/pos/sync-offline?cabang_id='.$this->cabang->id, [
                'client_batch_id' => 'BATCH-PERF-TEST',
                'transactions' => $transactions,
            ]);

        $peakMemory = memory_get_peak_usage(true);
        $memoryDeltaBytes = $peakMemory - $baselineMemory;
        $memoryDeltaMb = $memoryDeltaBytes / (1024 * 1024);

        $response->assertStatus(200);
        $response->assertJsonPath('data.total_success', 30);

        // PRD Spesifikasi: Audit Penggunaan Memori VPS (< 32MB peak)
        $this->assertLessThan(
            32.0,
            $memoryDeltaMb,
            "Penggunaan memori sinkronisasi batch ({$memoryDeltaMb} MB) melebihi batas toleransi 32 MB."
        );
    }
}
