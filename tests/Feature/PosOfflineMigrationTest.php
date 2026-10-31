<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Pos\Models\PosOfflineSyncLog;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosOfflineMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaksi_table_supports_offline_fields(): void
    {
        $cabang = Cabang::create([
            'nama' => 'Cabang Test',
            'kode' => 'CBG-TST',
            'alamat' => 'Jl. Test No. 1',
            'telepon' => '08123456789',
        ]);

        $uuid = (string) Str::uuid();
        $transaksi = Transaksi::create([
            'no_transaksi' => 'TRX-TST-001',
            'offline_uuid' => $uuid,
            'cabang_id' => $cabang->id,
            'sumber' => 'pos',
            'subtotal' => 100000,
            'total_akhir' => 100000,
            'metode_bayar' => 'tunai',
            'jumlah_bayar' => 100000,
            'kembalian' => 0,
            'status' => 'selesai',
            'is_offline' => true,
            'client_created_at' => now()->subMinutes(10),
            'synced_at' => now(),
            'sync_status' => 'synced',
            'discrepancy_amount' => 0,
        ]);

        $this->assertDatabaseHas('transaksi', [
            'id' => $transaksi->id,
            'offline_uuid' => $uuid,
            'is_offline' => 1,
            'sync_status' => 'synced',
        ]);

        $this->assertTrue($transaksi->is_offline);
        $this->assertEquals($uuid, $transaksi->offline_uuid);
    }

    public function test_pos_offline_sync_logs_table_and_model(): void
    {
        $cabang = Cabang::create([
            'nama' => 'Cabang Log',
            'kode' => 'CBG-LOG',
            'alamat' => 'Jl. Log No. 2',
            'telepon' => '08123456780',
        ]);

        $user = User::factory()->create();

        $log = PosOfflineSyncLog::create([
            'cabang_id' => $cabang->id,
            'user_id' => $user->id,
            'batch_id' => 'BATCH-TEST-001',
            'total_payload' => 5,
            'total_success' => 4,
            'total_conflicts' => 1,
            'conflict_details' => [
                'errors' => ['uuid-1' => 'Price mismatch'],
            ],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'UtePartsPWA/1.0',
        ]);

        $this->assertDatabaseHas('pos_offline_sync_logs', [
            'id' => $log->id,
            'batch_id' => 'BATCH-TEST-001',
            'total_payload' => 5,
        ]);

        $this->assertEquals(5, $log->total_payload);
        $this->assertIsArray($log->conflict_details);
        $this->assertEquals('Price mismatch', $log->conflict_details['errors']['uuid-1']);
    }
}
