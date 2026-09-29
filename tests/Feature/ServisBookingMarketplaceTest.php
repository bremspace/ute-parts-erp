<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Marketplace\Livewire\BookingServis;
use App\Modules\Marketplace\Livewire\CustomerAccount;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Livewire\ServisBoard;
use App\Modules\Servis\Models\TiketServis;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ServisBookingMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Utama',
            'kode' => 'CBG-UTAMA',
            'is_active' => true,
        ]);
        session(['cabang_id' => $this->cabang->id]);
    }

    private function buatUser(string $role, string $email): User
    {
        $user = User::create([
            'name' => 'User '.$role,
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole($role);
        $user->cabangs()->attach($this->cabang->id);

        return $user;
    }

    public function test_booking_servis_online_berhasil_dan_redirect_ke_tracking(): void
    {
        $customer = Pelanggan::create([
            'nama' => 'Pelanggan Online',
            'telepon' => '081299998888',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($customer, 'customer');

        $component = Livewire::test(BookingServis::class)
            ->assertSet('nama', 'Pelanggan Online')
            ->assertSet('telepon', '081299998888')
            ->assertSet('cabang_id', $this->cabang->id)
            ->set('jenis_hp', 'iPhone 13 Pro')
            ->set('seri_hp', '256GB Sierra Blue')
            ->set('keluhan', 'Layar blank setelah jatuh')
            ->call('simpanBooking');

        $tiket = TiketServis::where('telepon_pelanggan', '081299998888')->first();
        $this->assertNotNull($tiket);
        $this->assertEquals('diajukan_online', $tiket->status);
        $this->assertEquals('online', $tiket->sumber);
        $this->assertEquals('iPhone 13 Pro', $tiket->jenis_hp);
        $this->assertNotEmpty($tiket->token_approval);

        $component->assertRedirect(url('/tracking/'.$tiket->token_approval));
    }

    public function test_servis_board_antrean_booking_online_dan_konfirmasi(): void
    {
        $teknisi = $this->buatUser('teknisi', 'teknisi@uteparts.test');
        $this->actingAs($teknisi);

        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-ONL-TEST-001',
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Budi Online',
            'telepon_pelanggan' => '08123456789',
            'jenis_hp' => 'Samsung S21',
            'keluhan' => 'Baterai kembung',
            'status' => 'diajukan_online',
            'sumber' => 'online',
            'token_approval' => 'sample-token-12345',
        ]);

        $component = Livewire::test(ServisBoard::class);

        $bookingList = $component->get('bookingOnline');
        $this->assertTrue($bookingList->contains('id', $tiket->id));

        $component->call('konfirmasiBookingOnline', $tiket->id);

        $tiket->refresh();
        $this->assertEquals('diterima', $tiket->status);
    }

    public function test_customer_account_memuat_estimasi_biaya_dan_status_pembayaran(): void
    {
        $customer = Pelanggan::create([
            'nama' => 'Akun Konsumen',
            'telepon' => '087711223344',
            'password' => Hash::make('password'),
        ]);

        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-TEST-ACC-01',
            'cabang_id' => $this->cabang->id,
            'pelanggan_id' => $customer->id,
            'nama_pelanggan' => 'Akun Konsumen',
            'telepon_pelanggan' => '087711223344',
            'jenis_hp' => 'Xiaomi Mi 11',
            'keluhan' => 'Ganti LCD',
            'status' => 'menunggu_approval',
            'estimasi_biaya' => 450000,
            'status_pembayaran' => 'belum_bayar',
            'token_approval' => 'token-customer-123',
        ]);

        $this->actingAs($customer, 'customer');

        $component = Livewire::test(CustomerAccount::class)
            ->set('activeTab', 'servis')
            ->assertOk();

        $servisList = $component->get('servis');
        $this->assertTrue($servisList->contains('id', $tiket->id));

        $tiketFound = $servisList->firstWhere('id', $tiket->id);
        $this->assertEquals(450000, $tiketFound->estimasi_biaya);
        $this->assertEquals('belum_bayar', $tiketFound->status_pembayaran);
    }

    public function test_customer_account_menampilkan_status_membership_dan_poin_loyalty(): void
    {
        $silver = TierMembership::create([
            'nama' => 'Silver',
            'kode' => 'silver',
            'min_belanja_12bulan' => 500000,
            'diskon_persen' => 3.0,
            'poin_multiplier' => 1.0,
            'urutan' => 1,
            'is_active' => true,
        ]);

        $gold = TierMembership::create([
            'nama' => 'Gold',
            'kode' => 'gold',
            'min_belanja_12bulan' => 2000000,
            'diskon_persen' => 5.0,
            'poin_multiplier' => 1.5,
            'urutan' => 2,
            'is_active' => true,
        ]);

        $customer = Pelanggan::create([
            'nama' => 'Member Setia',
            'telepon' => '081233445566',
            'password' => Hash::make('password'),
            'tier_membership_id' => $silver->id,
            'poin_loyalty' => 350,
            'total_belanja_12bulan' => 1200000,
        ]);

        $this->actingAs($customer, 'customer');

        $component = Livewire::test(CustomerAccount::class)
            ->set('activeTab', 'membership')
            ->assertOk()
            ->assertSee('Silver')
            ->assertSee('350')
            ->assertSee('Gold')
            ->assertSee('Status Membership & Poin');

        $info = $component->get('membershipInfo');
        $this->assertEquals('Silver', $info['tierName']);
        $this->assertEquals(350, $info['poinLoyalty']);
        $this->assertEquals(1200000, $info['totalBelanja']);
        $this->assertEquals('Gold', $info['nextTier']->nama);
        $this->assertEquals(800000, $info['kekurangan']);
    }
}
