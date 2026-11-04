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
        $this->get('/app/login')->assertStatus(200)->assertSee('Masuk ke Backoffice');
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

    public function test_superadmin_login_requires_valid_credentials(): void
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

        // 1. Login dengan password yang valid berhasil
        $this->post('/app/login', [
            'email' => 'superadmin@uteparts.id',
            'password' => 'Password123!',
        ])->assertRedirect('/app/dashboard');
        $this->assertAuthenticatedAs($admin);

        Auth::logout();

        // 2. Login dengan password yang salah ditolak (tanpa backdoor fallback bypass)
        $this->post('/app/login', [
            'email' => 'superadmin@uteparts.id',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
