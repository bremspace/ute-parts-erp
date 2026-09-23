<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Notifikasi\Jobs\KirimNotifikasiJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin-toko', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'finance', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'kasir', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function createUser(array $attributes = [], ?string $role = null): User
    {
        $user = User::create(array_merge([
            'name' => 'Uji 2FA '.Str::random(4),
            'email' => '2fa-'.Str::random(6).'@uteparts.test',
            'password' => 'password123',
            'is_active' => true,
        ], $attributes));

        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }

    /** Set secret + confirmed_at langsung (tanpa lewat UI). */
    protected function enableTwoFactorDirectly(User $user): string
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();
        $user->two_factor_secret = $secret;
        $user->two_factor_confirmed_at = now();
        $user->save();

        return $secret;
    }

    // ===== Setup / secret generation =====

    public function test_setup_page_generates_secret_when_missing(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->get(route('two-factor.setup'))
            ->assertOk()
            ->assertSee('Langkah 1', false)
            ->assertSee('Langkah 2', false);

        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_enable_with_valid_totp_activates_and_shows_eight_backup_codes_once(): void
    {
        Queue::fake();

        $user = $this->createUser();
        $this->actingAs($user)->get(route('two-factor.setup'));

        $user->refresh();
        $secret = $user->two_factor_secret;
        $this->assertNotNull($secret);

        $otp = (new Google2FA)->getCurrentOtp($secret);

        $this->post(route('two-factor.enable'), ['code' => $otp])
            ->assertRedirect(route('two-factor.setup'));

        $user->refresh();
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertTrue($user->hasEnabledTwoFactor());
        $this->assertCount(8, $user->two_factor_backup_codes);

        // Plaintext hanya di flash session — panel muncul sekali lalu hilang
        $this->get(route('two-factor.setup'))
            ->assertOk()
            ->assertSee('Backup Code (tampil sekali)', false);

        // GET kedua: session pull → panel backup hilang
        $this->get(route('two-factor.setup'))
            ->assertOk()
            ->assertDontSee('Backup Code (tampil sekali)', false);

        // Event 2FA → queue only (KirimNotifikasiJob), tidak sync
        Queue::assertPushed(KirimNotifikasiJob::class);
    }

    public function test_enable_with_invalid_totp_rejects(): void
    {
        $user = $this->createUser();
        $this->actingAs($user)->get(route('two-factor.setup'));

        $this->post(route('two-factor.enable'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $user->refresh();
        $this->assertNull($user->two_factor_confirmed_at);
    }

    // ===== Backup codes =====

    public function test_backup_codes_are_hashed_and_consume_on_use(): void
    {
        $user = $this->createUser();
        $this->enableTwoFactorDirectly($user);

        $plain = $user->generateTwoFactorBackupCodes(8);
        $user->refresh();

        $this->assertCount(8, $plain);
        $this->assertCount(8, $user->two_factor_backup_codes);
        // Hash — plaintext tidak tersimpan
        $this->assertNotContains($plain[0], $user->two_factor_backup_codes);
        $this->assertStringStartsWith('$2y$', $user->two_factor_backup_codes[0]);

        // Pakai code pertama → consume-on-use
        $this->assertTrue($user->fresh()->confirmTwoFactorBackupCode($plain[0]));
        $this->assertCount(7, $user->fresh()->two_factor_backup_codes);

        // Code yang sama tidak bisa dipakai lagi
        $this->assertFalse($user->fresh()->confirmTwoFactorBackupCode($plain[0]));
        // Code berbeda masih valid
        $this->assertTrue($user->fresh()->confirmTwoFactorBackupCode($plain[7]));
    }

    public function test_backup_code_format_with_dash_is_accepted(): void
    {
        $user = $this->createUser();
        $this->enableTwoFactorDirectly($user);

        $plain = $user->generateTwoFactorBackupCodes(8);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{5}-[A-Z0-9]{5}$/', $plain[0]);

        // Input tanpa strip non-alnum di confirmTwoFactorBackupCode → tetap match
        $this->assertTrue($user->fresh()->confirmTwoFactorBackupCode(str_replace('-', '', $plain[0])));
    }

    // ===== Login challenge flow =====

    public function test_login_with_2fa_active_redirects_to_challenge_not_full_session(): void
    {
        $user = $this->createUser(['password' => 'password123']);
        $this->enableTwoFactorDirectly($user);

        $loginResponse = $this->post('/app/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);
        $loginResponse->assertRedirect(route('two-factor.challenge'));

        // Belum grant session penuh — pending id di session, bukan authenticated
        $this->assertGuest();
        $loginResponse->assertSessionHas('two_factor_login_id', $user->id);

        // Challenge page render
        $this->get(route('two-factor.challenge'))
            ->assertOk()
            ->assertSee('Verifikasi Dua Faktor', false);
    }

    public function test_challenge_success_with_valid_totp_grants_session(): void
    {
        $user = $this->createUser();
        $secret = $this->enableTwoFactorDirectly($user);

        $this->post('/app/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect(route('two-factor.challenge'));

        $otp = (new Google2FA)->getCurrentOtp($secret);

        $this->post(route('two-factor.challenge.post'), ['code' => $otp])
            ->assertRedirect('/app/dashboard')
            ->assertSessionMissing('two_factor_login_id');

        $this->assertAuthenticated();
        $this->get('/app/dashboard')->assertStatus(200);
    }

    public function test_challenge_failure_shows_indonesian_error_and_keeps_guest(): void
    {
        $user = $this->createUser();
        $this->enableTwoFactorDirectly($user);

        $this->post('/app/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $this->post(route('two-factor.challenge.post'), ['code' => '999999'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $error = session('errors')->first('code');
        $this->assertStringContainsString('Kode verifikasi salah', $error);
    }

    public function test_challenge_allows_backup_code_and_consumes_it(): void
    {
        $user = $this->createUser();
        $this->enableTwoFactorDirectly($user);
        $plain = $user->generateTwoFactorBackupCodes(8);

        $this->post('/app/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $this->post(route('two-factor.challenge.post'), ['code' => $plain[3]])
            ->assertRedirect('/app/dashboard');

        $this->assertAuthenticated();
        $this->assertCount(7, $user->fresh()->two_factor_backup_codes);
    }

    public function test_challenge_rate_limited_after_five_attempts(): void
    {
        $user = $this->createUser();
        $this->enableTwoFactorDirectly($user);

        $this->post('/app/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('two-factor.challenge.post'), ['code' => '000000'])
                ->assertSessionHasErrors('code');
        }

        // Percobaan ke-6 → diblokir RateLimiter (pesan "Terlalu banyak percobaan")
        $this->post(route('two-factor.challenge.post'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $error = session('errors')->first('code');
        $this->assertStringContainsString('Terlalu banyak percobaan', $error);
        $this->assertGuest();
    }

    public function test_challenge_without_pending_session_redirects_to_login(): void
    {
        $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
        $this->post(route('two-factor.challenge.post'), ['code' => '123456'])
            ->assertRedirect(route('login'));
    }

    // ===== Role enforce (G-03) =====

    public function test_required_role_with_2fa_is_challenged_on_login(): void
    {
        $user = $this->createUser([], 'super-admin');
        $this->enableTwoFactorDirectly($user);

        $this->post('/app/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect(route('two-factor.challenge'));

        $this->assertGuest();
    }

    public function test_required_role_without_setup_is_not_locked_out(): void
    {
        // MVP policy: role wajib 2FA tanpa setup → login sukses + flag banner, BUKAN lock-out
        $user = $this->createUser([], 'finance');

        $this->post('/app/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect('/app/dashboard')
            ->assertSessionHas('two_factor_setup_required', true);

        $this->assertAuthenticated();
    }

    public function test_non_required_role_without_2fa_logs_in_normally(): void
    {
        $user = $this->createUser([], 'kasir');

        $this->post('/app/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect('/app/dashboard')
            ->assertSessionMissing('two_factor_setup_required');

        $this->assertAuthenticated();
    }

    public function test_setup_routes_require_auth(): void
    {
        $this->get(route('two-factor.setup'))->assertRedirect('/app/login');
        $this->post(route('two-factor.enable'), ['code' => '123456'])->assertRedirect('/app/login');
    }

    // ===== Disable / regen =====

    public function test_disable_requires_correct_password_and_queue_notifies(): void
    {
        Queue::fake();

        $user = $this->createUser(['password' => 'password123']);
        $this->enableTwoFactorDirectly($user);
        $user->generateTwoFactorBackupCodes(8);

        $this->actingAs($user)
            ->post(route('two-factor.disable'), ['password' => 'salah'])
            ->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->hasEnabledTwoFactor());

        $this->actingAs($user)
            ->post(route('two-factor.disable'), ['password' => 'password123'])
            ->assertRedirect(route('two-factor.setup'));

        $user->refresh();
        $this->assertFalse($user->hasEnabledTwoFactor());
        $this->assertNull($user->two_factor_backup_codes);

        Queue::assertPushed(KirimNotifikasiJob::class);
    }

    public function test_regenerate_backup_codes_replaces_old_hashes_and_queue_notifies(): void
    {
        Queue::fake();

        $user = $this->createUser(['password' => 'password123']);
        $this->enableTwoFactorDirectly($user);
        $user->generateTwoFactorBackupCodes(8);
        $oldHashes = $user->fresh()->two_factor_backup_codes;

        $this->actingAs($user)
            ->post(route('two-factor.backup-codes'), ['password' => 'password123'])
            ->assertRedirect(route('two-factor.setup'));

        $user->refresh();
        $this->assertCount(8, $user->two_factor_backup_codes);
        $this->assertNotSame($oldHashes, $user->two_factor_backup_codes);
        // Hash lama tidak ada lagi di kolom
        foreach ($user->two_factor_backup_codes as $hash) {
            $this->assertNotContains($hash, $oldHashes);
        }

        Queue::assertPushed(KirimNotifikasiJob::class);
    }

    public function test_2fa_setup_requires_password_not_only_session_for_disable(): void
    {
        $user = $this->createUser(['password' => 'password123']);
        $this->enableTwoFactorDirectly($user);

        // Tanpa password field → validation error, 2FA tetap aktif
        $this->actingAs($user)
            ->post(route('two-factor.disable'), [])
            ->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->hasEnabledTwoFactor());
    }
}
