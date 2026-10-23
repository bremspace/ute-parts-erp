<?php

namespace Tests\Feature;

use App\Modules\Crm\Models\Pelanggan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
}
