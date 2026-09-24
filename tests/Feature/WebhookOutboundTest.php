<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Webhook\Enums\WebhookEvent;
use App\Modules\Webhook\Jobs\SendWebhookJob;
use App\Modules\Webhook\Models\WebhookDelivery;
use App\Modules\Webhook\Models\WebhookEndpoint;
use App\Modules\Webhook\Services\WebhookDispatcher;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * [F3-5] Webhook Outbound — Dispatcher, HMAC signature, retry, scoping.
 * Test whitelist event, scoping cabang, delivery log, dan event validation.
 */
class WebhookOutboundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

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

    /** T-F3-5-01: Event di luar whitelist ditolak (return 0). */
    public function test_event_di_outside_whitelist_ditolak(): void
    {
        $this->authed();
        $dispatcher = app(WebhookDispatcher::class);

        $result = $dispatcher->kirim('event.invalid', [], 1);

        $this->assertEquals(0, $result);
        $this->assertEmpty(WebhookDelivery::all());
    }

    /** T-F3-5-02: Whitelist event valid membuat delivery. */
    public function test_whitelist_event_valid(): void
    {
        $ctx = $this->authed();
        $cabang = $ctx['cabang'];

        WebhookEndpoint::create([
            'nama' => 'Test Endpoint',
            'url' => 'https://example.com/webhook',
            'secret' => 'testsecret123',
            'events' => [WebhookEvent::TransaksiSelesai->value],
            'is_aktif' => true,
            'cabang_id' => $cabang->id,
        ]);

        $dispatcher = app(WebhookDispatcher::class);
        $result = $dispatcher->kirim(WebhookEvent::TransaksiSelesai->value, ['transaksi_id' => 1], $cabang->id);

        $this->assertEquals(1, $result);
        $this->assertCount(1, WebhookDelivery::all());
        Queue::assertPushed(SendWebhookJob::class);
    }

    /** T-F3-5-03: Silent no-op saat tabel endpoint kosong. */
    public function test_silent_no_op_saat_tabel_endpoint_kosong(): void
    {
        $this->authed();
        $dispatcher = app(WebhookDispatcher::class);

        $result = $dispatcher->kirim(WebhookEvent::TransaksiSelesai->value, [], 1);

        $this->assertEquals(0, $result);
    }

    /** T-F3-5-04: Scoping cabang — endpoint cabang A tidak cocok untuk cabang B. */
    public function test_scoping_cabang(): void
    {
        $ctx = $this->authed();
        $cabang1 = $ctx['cabang'];

        $this->seed(RolesAndPermissionsSeeder::class);
        $cabang2 = Cabang::create(['nama' => 'Cabang 2', 'kode' => 'CBG-02', 'is_active' => true]);

        WebhookEndpoint::create([
            'nama' => 'Endpoint Cabang 1',
            'url' => 'https://cabang1.com/webhook',
            'secret' => 'secret1',
            'events' => [WebhookEvent::TransaksiSelesai->value],
            'is_aktif' => true,
            'cabang_id' => $cabang1->id,
        ]);

        $dispatcher = app(WebhookDispatcher::class);
        $result = $dispatcher->kirim(WebhookEvent::TransaksiSelesai->value, [], $cabang2->id);

        $this->assertEquals(0, $result);
    }

    /** T-F3-5-05: Endpoint global cocok untuk semua cabang. */
    public function test_endpoint_global_mencocokkan_semua_cabang(): void
    {
        $ctx = $this->authed();
        $cabang = $ctx['cabang'];

        WebhookEndpoint::create([
            'nama' => 'Endpoint Global',
            'url' => 'https://global.com/webhook',
            'secret' => 'secret-global',
            'events' => [WebhookEvent::TransaksiSelesai->value],
            'is_aktif' => true,
            'cabang_id' => null,
        ]);

        $dispatcher = app(WebhookDispatcher::class);
        $result = $dispatcher->kirim(WebhookEvent::TransaksiSelesai->value, [], $cabang->id);

        $this->assertEquals(1, $result);
    }

    /** T-F3-5-06: Delivery dibuat dengan status pending. */
    public function test_delivery_status_pending(): void
    {
        $ctx = $this->authed();
        $cabang = $ctx['cabang'];

        WebhookEndpoint::create([
            'nama' => 'Test Endpoint',
            'url' => 'https://example.com/webhook',
            'secret' => 'testsecret',
            'events' => [WebhookEvent::StokBerubah->value],
            'is_aktif' => true,
            'cabang_id' => $cabang->id,
        ]);

        $dispatcher = app(WebhookDispatcher::class);
        $dispatcher->kirim(WebhookEvent::StokBerubah->value, ['stok_log_id' => 1], $cabang->id);

        $delivery = WebhookDelivery::first();
        $this->assertNotNull($delivery);
        $this->assertEquals('pending', $delivery->status);
        $this->assertEquals(0, $delivery->attempt);
        $this->assertEquals(WebhookEvent::StokBerubah->value, $delivery->event);
    }

    /** T-F3-5-07: Context berisi data yang benar. */
    public function test_delivery_context_berisi_data(): void
    {
        $this->authed();
        $cabang = $this->authed()['cabang'];

        WebhookEndpoint::create([
            'nama' => 'Context Test',
            'url' => 'https://ctx.test/webhook',
            'secret' => 'secret',
            'events' => [WebhookEvent::ServisSelesai->value],
            'is_aktif' => true,
            'cabang_id' => $cabang->id,
        ]);

        $dispatcher = app(WebhookDispatcher::class);
        $dispatcher->kirim(WebhookEvent::ServisSelesai->value, ['tiket_servis_id' => 5], $cabang->id);

        $delivery = WebhookDelivery::first();
        $this->assertNotNull($delivery);
        $this->assertEquals(5, $delivery->context['tiket_servis_id']);
    }

    /** T-F3-5-08: Banyak endpoint — semua delivery dibuat. */
    public function test_banyak_endpoint_semua_delivery(): void
    {
        $ctx = $this->authed();
        $cabang = $ctx['cabang'];

        WebhookEndpoint::create([
            'nama' => 'Endpoint 1', 'url' => 'https://ep1.com', 'secret' => 's1',
            'events' => [WebhookEvent::TransaksiSelesai->value], 'is_aktif' => true, 'cabang_id' => $cabang->id,
        ]);
        WebhookEndpoint::create([
            'nama' => 'Endpoint 2', 'url' => 'https://ep2.com', 'secret' => 's2',
            'events' => [WebhookEvent::TransaksiSelesai->value], 'is_aktif' => true, 'cabang_id' => $cabang->id,
        ]);

        $dispatcher = app(WebhookDispatcher::class);
        $result = $dispatcher->kirim(WebhookEvent::TransaksiSelesai->value, [], $cabang->id);

        $this->assertEquals(2, $result);
        $this->assertCount(2, WebhookDelivery::all());
    }

    /** T-F3-5-09: Endpoint non-aktif tidak memicu delivery. */
    public function test_endpoint_nonaktif_tidak_memiliki_delivery(): void
    {
        $this->authed();
        $cabang = $this->authed()['cabang'];

        WebhookEndpoint::create([
            'nama' => 'Inactive Endpoint', 'url' => 'https://inactive.com', 'secret' => 'secret',
            'events' => [WebhookEvent::TransaksiSelesai->value], 'is_aktif' => false, 'cabang_id' => $cabang->id,
        ]);

        $dispatcher = app(WebhookDispatcher::class);
        $result = $dispatcher->kirim(WebhookEvent::TransaksiSelesai->value, [], $cabang->id);

        $this->assertEquals(0, $result);
    }

    /** T-F3-5-10: WebhookEvent::valid() validasi enum. */
    public function test_webhook_event_valid_method(): void
    {
        $this->assertTrue(WebhookEvent::valid(WebhookEvent::TransaksiSelesai->value));
        $this->assertTrue(WebhookEvent::valid(WebhookEvent::StokBerubah->value));
        $this->assertTrue(WebhookEvent::valid(WebhookEvent::ServisSelesai->value));
        $this->assertFalse(WebhookEvent::valid('invalid.event'));
    }
}
