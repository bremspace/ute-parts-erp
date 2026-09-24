<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Models\DeviceSession;
use App\Modules\Rbac\Services\SessionManagementService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * [F3-4] Session Management — device list, force logout, timeout.
 */
class SessionManagementTest extends TestCase
{
    use RefreshDatabase;

    private function authed(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $cabang = Cabang::firstOrCreate(
            ['kode' => 'TST'],
            ['nama' => 'Cabang Test', 'is_active' => true]
        );

        $user = User::factory()->create([
            'name' => 'Test User', 'email' => 'test@test.com',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $user->assignRole('super-admin');
        $user->cabangs()->attach($cabang->id);
        session(['cabang_id' => $cabang->id]);
        $this->actingAs($user);

        return ['user' => $user, 'cabang' => $cabang];
    }

    /** T-F3-4-01: Register device session. */
    public function test_register_device(): void
    {
        $ctx = $this->authed();
        $service = app(SessionManagementService::class);

        $device = $service->registerDevice(
            $ctx['user']->id, 'iPhone 15', 'token-iphone-1', '192.168.1.1', 'iOS'
        );

        $this->assertNotNull($device->id);
        $this->assertEquals('iPhone 15', $device->device_name);
        $this->assertTrue($device->is_active);
    }

    /** T-F3-4-02: Daftar device aktif. */
    public function test_get_device_list(): void
    {
        $ctx = $this->authed();
        $service = app(SessionManagementService::class);

        $service->registerDevice($ctx['user']->id, 'iPhone 15', 'token-1', null, null);
        $service->registerDevice($ctx['user']->id, 'MacBook', 'token-2', null, null);

        $devices = $service->getDeviceList($ctx['user']->id);

        $this->assertCount(2, $devices);
        $this->assertTrue($devices->every('is_active'));
    }

    /** T-F3-4-03: Force logout device. */
    public function test_force_logout_device(): void
    {
        $ctx = $this->authed();
        $service = app(SessionManagementService::class);

        $service->registerDevice($ctx['user']->id, 'iPhone 15', 'token-logout', null, null);

        $result = $service->forceLogoutDevice('token-logout', $ctx['user']->id);

        $this->assertTrue($result);
        $this->assertFalse(
            DeviceSession::where('device_token', 'token-logout')->first()->is_active
        );
    }

    /** T-F3-4-04: Force logout semua device. */
    public function test_force_logout_all(): void
    {
        $ctx = $this->authed();
        $service = app(SessionManagementService::class);

        $service->registerDevice($ctx['user']->id, 'Device 1', 'token-all-1', null, null);
        $service->registerDevice($ctx['user']->id, 'Device 2', 'token-all-2', null, null);

        $count = $service->forceLogoutAll($ctx['user']->id);

        $this->assertEquals(2, $count);
        $this->assertEquals(0, DeviceSession::where('user_id', $ctx['user']->id)->where('is_active', true)->count());
    }

    /** T-F3-4-05: Cleanup session expired. */
    public function test_cleanup_expired(): void
    {
        $ctx = $this->authed();
        $service = app(SessionManagementService::class);

        // Register device dengan last_activity di masa lalu
        DeviceSession::create([
            'user_id' => $ctx['user']->id,
            'device_name' => 'Old Device',
            'device_token' => 'token-expired',
            'is_active' => true,
            'last_activity' => now()->subMinutes(60),
            'expires_at' => now()->subMinutes(30),
        ]);

        // Device aktif terbaru
        $service->registerDevice($ctx['user']->id, 'New Device', 'token-active', null, null);

        $cleaned = $service->cleanupExpired(30);

        $this->assertEquals(1, $cleaned);
        $this->assertEquals(1, DeviceSession::where('is_active', true)->count());
    }

    /** T-F3-4-06: Device token unik. */
    public function test_device_token_unik(): void
    {
        $ctx = $this->authed();
        $service = app(SessionManagementService::class);

        $device1 = $service->registerDevice($ctx['user']->id, 'Device 1', 'same-token', null, null);
        $device2 = $service->registerDevice($ctx['user']->id, 'Device 2', 'same-token', null, null);

        // updateOrCreate — should be same record
        $this->assertEquals($device1->id, $device2->id);
    }
}
