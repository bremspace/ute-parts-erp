<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get('/app/login')->assertStatus(200)->assertSee('UTE PARTS');
    }

    public function test_guest_is_redirected_from_app_to_login(): void
    {
        $this->get('/app/pos')->assertRedirect('/app/login');
        $this->get('/app/akunting')->assertRedirect('/app/login');
    }

    public function test_valid_credentials_redirect_to_panel(): void
    {
        $cabang = Cabang::create([
            'kode' => 'CBG-01',
            'nama' => 'Pusat',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'email' => 'staff@uteparts.com',
            'password' => 'password123',
            'is_active' => true,
        ]);
        $user->cabangs()->attach($cabang->id, ['is_default' => true]);

        $this->post('/app/login', [
            'email' => 'staff@uteparts.com',
            'password' => 'password123',
        ])->assertRedirect('/app/dashboard');

        $this->assertAuthenticated();
        $this->get('/app/dashboard')->assertStatus(200);
    }

    public function test_invalid_credentials_return_error(): void
    {
        $this->post('/app/login', [
            'email' => 'nobody@nowhere.com',
            'password' => 'wrong',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
