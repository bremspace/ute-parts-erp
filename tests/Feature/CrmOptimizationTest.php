<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Models\KampanyeBroadcast;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Crm\Services\BroadcastService;
use App\Modules\Crm\Services\LeadService;
use App\Modules\Crm\Services\PelangganService;
use App\Modules\Crm\Services\TierService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Services\ServisService;
use Database\Seeders\AkunCoaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CrmOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'crm.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'crm.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'crm.edit', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'crm.delete', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'crm.broadcast', 'guard_name' => 'web']);

        $role = Role::where('name', 'admin')->first();
        $role->givePermissionTo(['crm.view', 'crm.create', 'crm.edit', 'crm.delete', 'crm.broadcast']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Queue::fake();

        $this->cabang = Cabang::create([
            'kode' => 'CAB-TEST',
            'nama' => 'Cabang Test',
            'alamat' => 'Jl. Ute Parts No. 1',
            'telepon' => '08123456789',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Admin CRM',
            'email' => 'admin.crm@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->user->assignRole('admin');

        $this->actingAs($this->user, 'web');
        session(['cabang_id' => $this->cabang->id]);
    }

    public function test_lead_kanban_update_stage_berhasil_tanpa_validasi_error(): void
    {
        $lead = Lead::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Prospek Toko ABC',
            'telepon' => '081211112222',
            'sumber' => 'walkin',
            'stage' => 'baru',
            'assigned_to' => $this->user->id,
        ]);

        $leadService = app(LeadService::class);

        // Simulasi perpindahan stage pada kanban board (hanya mengirimkan atribut stage & catatan)
        $updated = $leadService->update($lead, [
            'stage' => 'negosiasi',
            'catatan' => 'Tertarik paket sparepart LCD',
        ]);

        $this->assertEquals('negosiasi', $updated->stage);
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'stage' => 'negosiasi',
        ]);
    }

    public function test_lead_convert_ke_pelanggan_baru_dan_pelanggan_eksisting(): void
    {
        $leadService = app(LeadService::class);

        // Kasus 1: Lead baru dengan telepon baru -> jadi pelanggan baru
        $lead1 = Lead::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Mitra Baru',
            'telepon' => '081299990001',
            'email' => 'mitrabaru@test.com',
            'sumber' => 'whatsapp',
            'stage' => 'won',
            'assigned_to' => $this->user->id,
        ]);

        $pelanggan1 = $leadService->convertToPelanggan($lead1);
        $this->assertNotNull($pelanggan1);
        $this->assertEquals('Mitra Baru', $pelanggan1->nama);
        $this->assertEquals($pelanggan1->id, $lead1->fresh()->pelanggan_id);

        // Kasus 2: Lead dengan nomor telepon sama dengan pelanggan lama -> hubungkan ke pelanggan lama tanpa duplicate error
        $lead2 = Lead::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Mitra Baru Repeat Lead',
            'telepon' => '081299990001',
            'sumber' => 'tiktok',
            'stage' => 'won',
            'assigned_to' => $this->user->id,
        ]);

        $pelanggan2 = $leadService->convertToPelanggan($lead2);
        $this->assertEquals($pelanggan1->id, $pelanggan2->id);
        $this->assertEquals($pelanggan1->id, $lead2->fresh()->pelanggan_id);

        // Kasus 3: Lead tanpa nomor telepon -> konversi tetap sukses dengan fallback telepon
        $lead3 = Lead::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Lead Walkin Anonim',
            'telepon' => null,
            'sumber' => 'walkin',
            'stage' => 'won',
            'assigned_to' => $this->user->id,
        ]);

        $pelanggan3 = $leadService->convertToPelanggan($lead3);
        $this->assertNotNull($pelanggan3);
        $this->assertNotNull($pelanggan3->telepon);
        $this->assertEquals($pelanggan3->id, $lead3->fresh()->pelanggan_id);
    }

    public function test_pelanggan_tambah_belanja_poin_dan_kenaikan_tier_otomatis(): void
    {
        // Setup Tier Membership
        $silver = TierMembership::create([
            'kode' => 'SLV',
            'nama' => 'Silver',
            'min_belanja_12bulan' => 1_000_000,
            'diskon_persen' => 5,
            'poin_multiplier' => 1.0,
            'urutan' => 1,
            'is_active' => true,
        ]);

        $gold = TierMembership::create([
            'kode' => 'GLD',
            'nama' => 'Gold',
            'min_belanja_12bulan' => 5_000_000,
            'diskon_persen' => 10,
            'poin_multiplier' => 1.5,
            'urutan' => 2,
            'is_active' => true,
        ]);

        $pelanggan = Pelanggan::create([
            'nama' => 'Customer Loyalty',
            'telepon' => '085712345678',
            'email' => 'loyal@test.com',
            'total_belanja_12bulan' => 0,
            'poin_loyalty' => 0,
            'tier_membership_id' => null,
        ]);

        $pelangganService = app(PelangganService::class);

        // Belanja 1.500.000 -> harus dapat 1500 poin (1 poin / 1.000) dan promosi ke tier Silver
        $pelangganService->tambahBelanjaDanPoin($pelanggan, 1_500_000);
        $pelanggan->refresh();

        $this->assertEquals(1_500_000, (float) $pelanggan->total_belanja_12bulan);
        $this->assertEquals(1500, $pelanggan->poin_loyalty);
        $this->assertEquals($silver->id, $pelanggan->tier_membership_id);

        // Belanja tambahan 4.000.000 -> total belanja 5.500.000 -> promosi ke tier Gold dan tambah 4000 poin
        $pelangganService->tambahBelanjaDanPoin($pelanggan, 4_000_000);
        $pelanggan->refresh();

        $this->assertEquals(5_500_000, (float) $pelanggan->total_belanja_12bulan);
        $this->assertEquals(5500, $pelanggan->poin_loyalty);
        $this->assertEquals($gold->id, $pelanggan->tier_membership_id);
    }

    public function test_pelanggan_delete_aman_mencegah_penghapusan_jika_memiliki_riwayat(): void
    {
        $pelanggan = Pelanggan::create([
            'nama' => 'Customer Aktif',
            'telepon' => '087711223344',
            'total_belanja_12bulan' => 0,
            'poin_loyalty' => 0,
        ]);

        // Buat transaksi terhubung
        Transaksi::create([
            'no_transaksi' => 'TRX-TEST-001',
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $this->user->id,
            'pelanggan_id' => $pelanggan->id,
            'subtotal' => 250_000,
            'total_akhir' => 250_000,
            'status' => 'selesai',
            'metode_bayar' => 'cash',
        ]);

        $pelangganService = app(PelangganService::class);

        $this->expectException(\DomainException::class);
        $pelangganService->delete($pelanggan);
    }

    public function test_recalc_tier_12_bulan_rolling_berdasarkan_transaksi_lunas(): void
    {
        $tier = TierMembership::create([
            'kode' => 'VIP',
            'nama' => 'VIP',
            'min_belanja_12bulan' => 2_000_000,
            'diskon_persen' => 7,
            'poin_multiplier' => 1.0,
            'urutan' => 1,
            'is_active' => true,
        ]);

        $pelanggan = Pelanggan::create([
            'nama' => 'Member Rolling',
            'telepon' => '089912345678',
            'total_belanja_12bulan' => 5_000_000, // angka lama
            'tier_membership_id' => $tier->id,
        ]);

        // Transaksi 1: Transaksi 15 bulan lalu (di luar 12 bulan) senilai 4.000.000
        $trxLama = Transaksi::create([
            'no_transaksi' => 'TRX-LAMA-001',
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $this->user->id,
            'pelanggan_id' => $pelanggan->id,
            'subtotal' => 4_000_000,
            'total_akhir' => 4_000_000,
            'status' => 'selesai',
            'metode_bayar' => 'cash',
        ]);
        $trxLama->created_at = Carbon::now()->subMonths(15);
        $trxLama->save();

        // Transaksi 2: Transaksi 2 bulan lalu (dalam 12 bulan) senilai 500.000
        $trxBaru = Transaksi::create([
            'no_transaksi' => 'TRX-BARU-001',
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $this->user->id,
            'pelanggan_id' => $pelanggan->id,
            'subtotal' => 500_000,
            'total_akhir' => 500_000,
            'status' => 'selesai',
            'metode_bayar' => 'cash',
        ]);
        $trxBaru->created_at = Carbon::now()->subMonths(2);
        $trxBaru->save();

        // Jalankan rekalkulasi tier rolling dengan flag rehitungBelanja=true
        $tierService = app(TierService::class);
        $tierService->recalcSatu($pelanggan, null, true);
        $pelanggan->refresh();

        // Belanja 12 bulan harusnya hanya 500.000, sehingga turun dari tier VIP (< 2.000.000)
        $this->assertEquals(500_000, (float) $pelanggan->total_belanja_12bulan);
        $this->assertNull($pelanggan->tier_membership_id);
    }

    public function test_api_crm_pelanggan_update_dan_destroy(): void
    {
        $pelanggan = Pelanggan::create([
            'nama' => 'Pelanggan API',
            'telepon' => '082100001111',
            'email' => 'api.cust@test.com',
            'tipe_konsumen' => 'retail',
        ]);

        // Test Update API
        $responseUpdate = $this->putJson("/api/crm/pelanggan/{$pelanggan->id}", [
            'nama' => 'Pelanggan API Updated',
            'tipe_konsumen' => 'reseller',
        ]);

        $responseUpdate->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('pelanggan', [
            'id' => $pelanggan->id,
            'nama' => 'Pelanggan API Updated',
            'tipe_konsumen' => 'reseller',
        ]);

        // Test Destroy API
        $responseDelete = $this->deleteJson("/api/crm/pelanggan/{$pelanggan->id}");
        $responseDelete->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('pelanggan', [
            'id' => $pelanggan->id,
        ]);
    }

    public function test_broadcast_segmentasi_channel_wa_dan_email(): void
    {
        // Pelanggan 1: punya HP dan Email
        $p1 = Pelanggan::create([
            'nama' => 'User Lengkap',
            'telepon' => '081234567890',
            'email' => 'lengkap@test.com',
        ]);

        // Pelanggan 2: hanya punya HP (tanpa email)
        $p2 = Pelanggan::create([
            'nama' => 'User Hanya WA',
            'telepon' => '081234567891',
            'email' => null,
        ]);

        // Pelanggan 3: hanya punya Email (tanpa telepon)
        $p3 = Pelanggan::create([
            'nama' => 'User Hanya Email',
            'telepon' => null,
            'email' => 'emailonly@test.com',
        ]);

        $broadcastService = app(BroadcastService::class);

        // Kampanye channel WA -> harus menyaring p1 dan p2 saja
        $kampanyeWa = new KampanyeBroadcast(['channel' => 'wa', 'segment' => []]);
        $targetWa = $broadcastService->resolveTarget($kampanyeWa)->get();
        $this->assertTrue($targetWa->contains('id', $p1->id));
        $this->assertTrue($targetWa->contains('id', $p2->id));
        $this->assertFalse($targetWa->contains('id', $p3->id));

        // Kampanye channel Email -> harus menyaring p1 dan p3 saja
        $kampanyeEmail = new KampanyeBroadcast(['channel' => 'email', 'segment' => []]);
        $targetEmail = $broadcastService->resolveTarget($kampanyeEmail)->get();
        $this->assertTrue($targetEmail->contains('id', $p1->id));
        $this->assertFalse($targetEmail->contains('id', $p2->id));
        $this->assertTrue($targetEmail->contains('id', $p3->id));
    }

    public function test_pelunasan_servis_menambah_belanja_dan_poin_pelanggan_crm(): void
    {
        $this->seed(AkunCoaSeeder::class);

        $pelanggan = Pelanggan::create([
            'nama' => 'Pelanggan Servis HP',
            'telepon' => '081234567999',
            'total_belanja_12bulan' => 0,
            'poin_loyalty' => 0,
        ]);

        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-CRM-'.uniqid(),
            'cabang_id' => $this->cabang->id,
            'pelanggan_id' => $pelanggan->id,
            'nama_pelanggan' => $pelanggan->nama,
            'telepon_pelanggan' => $pelanggan->telepon,
            'jenis_hp' => 'iPhone 13',
            'tipe_kunci' => 'tidak_ada',
            'keluhan' => 'Ganti Baterai',
            'status' => 'qc',
            'sumber' => 'walkin',
            'estimasi_biaya' => 500_000,
        ]);

        $servisService = app(ServisService::class);
        $servisService->updateStatus($tiket, 'selesai', $this->user, 'Selesai ganti baterai');

        // Lakukan pembayaran servis 500.000
        $servisService->bayar($tiket->fresh(), 'tunai', $this->user, 'Lunas kasir');

        $pelanggan->refresh();

        // 500.000 belanja -> total_belanja_12bulan = 500.000, poin_loyalty = 500 (1 poin per 1.000)
        $this->assertEquals(500_000, (float) $pelanggan->total_belanja_12bulan);
        $this->assertEquals(500, $pelanggan->poin_loyalty);
    }
}
