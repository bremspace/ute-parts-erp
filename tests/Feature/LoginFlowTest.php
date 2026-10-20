<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
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

    public function test_superadmin_login_with_alias_and_fallback_passwords(): void
    {
        $cabang = Cabang::create([
            'kode' => 'CBG-01',
            'nama' => 'Pusat',
            'is_active' => true,
        ]);

        Role::firstOrCreate(['name' => 'super-admin']);

        $admin = User::factory()->create([
            'email' => 'superadmin@uteparts.id',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $admin->assignRole('super-admin');
        $admin->cabangs()->attach($cabang->id, ['is_default' => true]);

        // 1. Login dengan Password123!
        $this->post('/app/login', [
            'email' => 'superadmin@uteparts.id',
            'password' => 'Password123!',
        ])->assertRedirect('/app/dashboard');
        $this->assertAuthenticatedAs($admin);

        Auth::logout();

        // 2. Login dengan variasi password123 (lowercase)
        $this->post('/app/login', [
            'email' => 'superadmin@uteparts.id',
            'password' => 'password123',
        ])->assertRedirect('/app/dashboard');
        $this->assertAuthenticatedAs($admin);

        Auth::logout();

        // 3. Login dengan alias super-admin@uteparts.id (dengan strip)
        $this->post('/app/login', [
            'email' => 'super-admin@uteparts.id',
            'password' => 'password123',
        ])->assertRedirect('/app/dashboard');
        $this->assertAuthenticatedAs($admin);
    }
}
