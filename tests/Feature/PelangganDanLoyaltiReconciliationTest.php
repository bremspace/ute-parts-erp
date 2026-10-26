<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Crm\Services\PelangganService;
use App\Modules\Crm\Services\TierService;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Rbac\Livewire\SettingsRbac;
use App\Modules\Rbac\Models\Konfigurasi;
use App\Modules\Workflow\Models\ApprovalRequest;
use App\Modules\Workflow\Models\ApprovalRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PelangganDanLoyaltiReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'kasir', 'guard_name' => 'web']);
    }

    public function test_phone_normalization_and_approval_routing_in_pelanggan_service(): void
    {
        ApprovalRule::firstOrCreate(
            ['entity_type' => 'member', 'level' => 1],
            [
                'cabang_id' => null,
                'min_amount' => 0,
                'max_amount' => null,
                'approver_role' => 'owner',
                'is_aktif' => true,
            ]
        );

        $service = app(PelangganService::class);

        // Normalize +62
        $p1 = $service->create([
            'nama' => 'Test Customer +62',
            'telepon' => '+6281234567891',
        ]);
        $this->assertEquals('081234567891', $p1->telepon);
        $this->assertEquals('pending', $p1->status);

        // Verify approval request was generated
        $req = ApprovalRequest::where('entity_type', 'member')
            ->where('entity_id', $p1->id)
            ->first();
        $this->assertNotNull($req);
        $this->assertEquals('pending', $req->status);

        // Normalize 62 without plus
        $p2 = $service->create([
            'nama' => 'Test Customer 62',
            'telepon' => '6281234567892',
        ]);
        $this->assertEquals('081234567892', $p2->telepon);
    }

    public function test_pelanggan_auto_active_when_no_member_approval_rule_exists(): void
    {
        ApprovalRule::where('entity_type', 'member')->delete();

        $service = app(PelangganService::class);
        $pelanggan = $service->create([
            'nama' => 'Pelanggan Langsung Aktif',
            'telepon' => '081299887766',
        ]);

        $this->assertEquals('aktif', $pelanggan->status);
        $this->assertDatabaseMissing('approval_requests', [
            'entity_type' => 'member',
            'entity_id' => $pelanggan->id,
        ]);
    }

    public function test_pos_kasir_quick_add_customer_triggers_approval_when_rule_active(): void
    {
        ApprovalRule::firstOrCreate(
            ['entity_type' => 'member', 'level' => 1],
            [
                'cabang_id' => null,
                'min_amount' => 0,
                'max_amount' => null,
                'approver_role' => 'owner',
                'is_aktif' => true,
            ]
        );

        $kasir = User::factory()->create();
        $kasir->assignRole('kasir');

        Livewire::actingAs($kasir)
            ->test(PosKasir::class)
            ->set('pelangganBaruForm.nama', 'Pelanggan POS Baru')
            ->set('pelangganBaruForm.telepon', '+6287712345678')
            ->call('simpanPelangganBaru');

        $pelanggan = Pelanggan::where('telepon', '087712345678')->first();
        $this->assertNotNull($pelanggan);
        $this->assertEquals('pending', $pelanggan->status);

        $this->assertDatabaseHas('approval_requests', [
            'entity_type' => 'member',
            'entity_id' => $pelanggan->id,
            'status' => 'pending',
        ]);
    }

    public function test_tier_service_hitung_poin_with_custom_earn_persen_and_redeem(): void
    {
        $tier = TierMembership::create([
            'kode' => 'vip',
            'nama' => 'VIP',
            'min_belanja_12bulan' => 10000000,
            'diskon_persen' => 10,
            'poin_multiplier' => 2.0,
            'poin_redeem_rupiah' => 100,
            'urutan' => 1,
            'is_active' => true,
        ]);

        $pelanggan = Pelanggan::create([
            'nama' => 'VIP Customer',
            'telepon' => '081333444555',
            'tier_membership_id' => $tier->id,
            'status' => 'aktif',
        ]);

        $tierService = app(TierService::class);
        $config = app(KonfigurasiService::class);

        // Case 1: Custom poin_earn_persen = 10%
        // Belanja 1.000.000 -> 10% = Rp 100.000 nilai poin.
        // Dengan tier poin_redeem_rupiah = 100, poin dasar = 100.000 / 100 = 1000 poin.
        // Dikali multiplier 2.0 = 2000 poin.
        $config->set('poin_earn_persen', 10.0);
        $poin = $tierService->hitungPoin(1000000, $tier);
        $this->assertEquals(2000, $poin);

        // Case 2: Custom poin_earn_persen = 5%
        // Belanja 1.000.000 -> 5% = Rp 50.000 nilai poin / 100 = 500 * 2.0 = 1000 poin.
        $config->set('poin_earn_persen', 5.0);
        $poin = $tierService->hitungPoin(1000000, $tier);
        $this->assertEquals(1000, $poin);
    }

    public function test_settings_rbac_syncs_tier_discounts_with_tier_membership(): void
    {
        TierMembership::create([
            'kode' => 'silver',
            'nama' => 'Silver',
            'min_belanja_12bulan' => 1000000,
            'diskon_persen' => 3.0,
            'poin_multiplier' => 1.0,
            'urutan' => 1,
            'is_active' => true,
        ]);

        TierMembership::create([
            'kode' => 'gold',
            'nama' => 'Gold',
            'min_belanja_12bulan' => 5000000,
            'diskon_persen' => 7.0,
            'poin_multiplier' => 1.5,
            'urutan' => 2,
            'is_active' => true,
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        Livewire::actingAs($admin)
            ->test(SettingsRbac::class)
            ->set('loyalitasForm.poin_earn_persen', 8.5)
            ->set('loyalitasForm.poin_redeem_rupiah', 150)
            ->set('loyalitasForm.diskon_silver', 4.5)
            ->set('loyalitasForm.diskon_gold', 8.0)
            ->call('simpanLoyalitas')
            ->assertHasNoErrors();

        // Check tier membership was updated
        $silver = TierMembership::where('kode', 'silver')->first();
        $this->assertEquals(4.5, (float) $silver->diskon_persen);

        $gold = TierMembership::where('kode', 'gold')->first();
        $this->assertEquals(8.0, (float) $gold->diskon_persen);

        // Check konfigurasi was also updated
        $config = app(KonfigurasiService::class);
        $this->assertEquals(8.5, (float) $config->get('poin_earn_persen'));
        $this->assertEquals(150, (int) $config->get('poin_redeem_rupiah'));
        $this->assertEquals(4.5, (float) $config->get('diskon_silver'));
        $this->assertEquals(8.0, (float) $config->get('diskon_gold'));
    }
}
