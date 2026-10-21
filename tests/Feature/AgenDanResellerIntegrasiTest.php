<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Livewire\CrmDashboard;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Services\PelangganService;
use App\Modules\Reseller\Livewire\ResellerDashboard;
use App\Modules\Reseller\Models\Komisi;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TierMembershipSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgenDanResellerIntegrasiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(TierMembershipSeeder::class);

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@uteparts.dev',
            'password' => bcrypt('password'),
        ]);
        $this->admin->assignRole('super-admin');
    }

    public function test_pelanggan_service_auto_generate_kode_agen_dan_flag_reseller(): void
    {
        $service = app(PelangganService::class);

        // Pelanggan Agen baru tanpa kode_agen manual
        $agen = $service->create([
            'nama' => 'Agen B2B Nusantara',
            'telepon' => '081299990001',
            'email' => 'agen@b2b.com',
            'tipe_konsumen' => 'agen',
        ]);

        $this->assertTrue((bool) $agen->is_reseller);
        $this->assertEquals('agen', $agen->tipe_konsumen);
        $this->assertNotNull($agen->kode_agen);
        $this->assertStringStartsWith('AGN-', $agen->kode_agen);

        // Downline mendaftar dengan referral_kode agen
        $downline = $service->create([
            'nama' => 'Toko Mitra Downline',
            'telepon' => '081299990002',
            'referral_kode' => $agen->kode_agen,
            'tipe_konsumen' => 'reseller',
        ]);

        $this->assertEquals($agen->kode_agen, $downline->referral_kode);
        $this->assertEquals(1, $agen->fresh()->downlines()->count());
        $this->assertEquals($agen->id, $downline->uplineAgen?->id);
    }

    public function test_crm_dashboard_filter_tipe_konsumen_dan_downline_360(): void
    {
        $service = app(PelangganService::class);

        $agen = $service->create([
            'nama' => 'Agen Sukses',
            'telepon' => '081288880001',
            'tipe_konsumen' => 'agen',
        ]);

        $service->create([
            'nama' => 'Pelanggan Retail Biasa',
            'telepon' => '081288880002',
            'tipe_konsumen' => 'retail',
        ]);

        Livewire::actingAs($this->admin)
            ->test(CrmDashboard::class)
            ->set('filterTipeKonsumen', 'agen')
            ->assertSee('Agen Sukses')
            ->assertDontSee('Pelanggan Retail Biasa')
            ->set('selectedCustomerId', $agen->id)
            ->assertSee('Kode Agen:')
            ->assertSee($agen->kode_agen);
    }

    public function test_reseller_dashboard_menampilkan_agen_dan_filter_komisi_agen(): void
    {
        $service = app(PelangganService::class);

        $agen = $service->create([
            'nama' => 'Distributor Utama',
            'telepon' => '081277770001',
            'tipe_konsumen' => 'agen',
        ]);

        $reseller = $service->create([
            'nama' => 'Reseller Kecil',
            'telepon' => '081277770002',
            'tipe_konsumen' => 'reseller',
        ]);

        // Komisi untuk agen
        $komisiAgen = Komisi::create([
            'no_komisi' => 'KMS-AGN-001',
            'pelanggan_id' => $agen->id,
            'aktor_tipe' => 'agen',
            'aktor_id' => $agen->id,
            'jumlah_transaksi' => 5000000,
            'nominal_komisi' => 250000,
            'status' => 'pending',
            'keterangan' => 'Komisi Referral Agen',
        ]);

        $this->assertEquals('Distributor Utama', $komisiAgen->nama_penerima);

        Livewire::actingAs($this->admin)
            ->test(ResellerDashboard::class)
            ->assertSee('Distributor Utama')
            ->assertSee('Reseller Kecil')
            ->assertSee('AGEN B2B')
            ->set('filterMitra', 'agen')
            ->assertSee('Distributor Utama')
            ->assertDontSee('Reseller Kecil')
            ->set('activeTab', 'komisi')
            ->set('filterAktor', 'agen')
            ->assertSee('KMS-AGN-001')
            ->assertSee('Agen B2B');
    }
}
