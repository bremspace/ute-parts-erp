<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Livewire\CrmDashboard;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Marketplace\Livewire\CustomerAccount;
use App\Modules\Workflow\Livewire\ApprovalInbox;
use App\Modules\Workflow\Models\ApprovalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_login_with_phone_normalization_and_redirects_to_shop_when_cart_empty(): void
    {
        Pelanggan::create([
            'nama' => 'Budi Santoso',
            'telepon' => '081234567890',
            'password' => Hash::make('secret123'),
            'status' => 'aktif',
        ]);

        $response = $this->post('/login-pelanggan', [
            'telepon' => '6281234567890',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/shop');
        $this->assertAuthenticated('customer');
    }

    public function test_customer_login_redirects_to_checkout_when_cart_is_not_empty(): void
    {
        Pelanggan::create([
            'nama' => 'Budi Santoso',
            'telepon' => '081234567890',
            'password' => Hash::make('secret123'),
            'status' => 'aktif',
        ]);

        $this->withSession([
            'shop.cart' => [
                'p1' => ['product_id' => 1, 'qty' => 2, 'harga' => 50000],
            ],
        ]);

        $response = $this->post('/login-pelanggan', [
            'telepon' => '081234567890',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/checkout');
        $this->assertAuthenticated('customer');
    }

    public function test_customer_login_fails_with_invalid_credentials_and_preserves_input(): void
    {
        Pelanggan::create([
            'nama' => 'Budi Santoso',
            'telepon' => '081234567890',
            'password' => Hash::make('secret123'),
            'status' => 'aktif',
        ]);

        $response = $this->from('/login-pelanggan')->post('/login-pelanggan', [
            'telepon' => '081234567890',
            'password' => 'wrongpassword',
        ]);

        $response->assertRedirect('/login-pelanggan');
        $response->assertSessionHasErrors('telepon');
        $this->assertGuest('customer');
    }

    public function test_customer_can_login_with_username(): void
    {
        Pelanggan::create([
            'nama' => 'Budi Santoso',
            'username' => 'budi_part99',
            'telepon' => '081234567890',
            'password' => Hash::make('secret123'),
            'status' => 'aktif',
        ]);

        $response = $this->post('/login-pelanggan', [
            'telepon' => 'budi_part99',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/shop');
        $this->assertAuthenticated('customer');
    }

    public function test_customer_can_update_profile_and_password(): void
    {
        $pelanggan = Pelanggan::create([
            'nama' => 'Budi Santoso',
            'username' => 'budi_lama',
            'telepon' => '081234567890',
            'email' => 'budi@old.com',
            'password' => Hash::make('oldpassword123'),
            'status' => 'aktif',
        ]);

        $this->actingAs($pelanggan, 'customer');

        Livewire::test(CustomerAccount::class)
            ->set('nama', 'Budi Santoso Updated')
            ->set('username', 'budi_baru')
            ->set('email', 'budi@new.com')
            ->call('simpanProfil')
            ->assertHasNoErrors()
            ->set('currentPassword', 'oldpassword123')
            ->set('newPassword', 'newpassword456')
            ->set('newPassword_confirmation', 'newpassword456')
            ->call('updatePassword')
            ->assertHasNoErrors();

        $pelanggan->refresh();
        $this->assertEquals('Budi Santoso Updated', $pelanggan->nama);
        $this->assertEquals('budi_baru', $pelanggan->username);
        $this->assertEquals('budi@new.com', $pelanggan->email);
        $this->assertTrue(Hash::check('newpassword456', $pelanggan->password));
    }

    public function test_workflow_reset_password_pelanggan_dan_login_dengan_password_baru(): void
    {
        // 1. Pelanggan terdaftar
        $pelanggan = Pelanggan::create([
            'nama' => 'Siti Aisyah',
            'telepon' => '081987654321',
            'username' => 'siti_aisyah',
            'password' => Hash::make('password_lama_terlupa'),
            'status' => 'aktif',
        ]);

        // 2. Staff CS membuat pengajuan reset password di CRM Dashboard
        $csUser = User::factory()->create();
        $ownerUser = User::factory()->create();
        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $ownerUser->assignRole($ownerRole);

        $this->actingAs($csUser);

        Livewire::test(CrmDashboard::class)
            ->call('openResetPasswordModal', $pelanggan->id)
            ->set('resetPasswordForm.new_password', 'passwordBaru2026')
            ->set('resetPasswordForm.alasan', 'Konsumen menghubungi CS via WA lupa password')
            ->call('ajukanResetPassword');

        $request = ApprovalRequest::where('entity_type', 'reset_password_pelanggan')
            ->where('entity_id', $pelanggan->id)
            ->first();

        $this->assertNotNull($request);
        $this->assertEquals('pending', $request->status);

        // 3. Owner approve permohonan reset password di Approval Inbox
        $this->actingAs($ownerUser);

        Livewire::test(ApprovalInbox::class)
            ->call('openApproveResetPasswordModal', $request->id)
            ->call('approveResetPasswordSubmit');

        $request->refresh();
        $this->assertEquals('disetujui', $request->status);

        $pelanggan->refresh();
        $this->assertTrue(Hash::check('passwordBaru2026', $pelanggan->password));

        // 4. Konsumen langsung bisa login menggunakan password baru
        auth('customer')->logout();
        $response = $this->post('/login-pelanggan', [
            'telepon' => '081987654321',
            'password' => 'passwordBaru2026',
        ]);

        $response->assertRedirect('/shop');
        $this->assertAuthenticated('customer');
    }
}
