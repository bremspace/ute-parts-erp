<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Livewire\CrmDashboard;
use App\Modules\Crm\Models\KampanyeBroadcast;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Crm\Services\BroadcastService;
use App\Modules\Dashboard\Livewire\DashboardIndex;
use App\Modules\Notifikasi\Jobs\KirimNotifikasiJob;
use App\Modules\Notifikasi\Models\NotifikasiKeluar;
use App\Modules\Rbac\Models\Cabang;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BroadcastPromoTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private User $marketing;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Broadcast',
            'kode' => 'CBG-BROADCAST',
            'is_active' => true,
        ]);

        $this->marketing = $this->makeUser('marketing-broadcast@test.com', 'marketing');
        $this->actingAs($this->marketing, 'web');
        session(['cabang_id' => $this->cabang->id]);
    }

    public function test_campaign_foreign_key_and_broadcast_log_are_persisted(): void
    {
        $tier = $this->makeTier('Gold', 'gold-broadcast');
        $customer = $this->makeCustomer('Pelanggan Broadcast', $tier);
        $campaign = $this->makeCampaign($tier->id);

        app(BroadcastService::class)->kirimSekarang($campaign);

        $this->assertDatabaseHas('notifikasi_keluar', [
            'kampanye_broadcast_id' => $campaign->id,
        ]);
        $log = NotifikasiKeluar::where('kampanye_broadcast_id', $campaign->id)->firstOrFail();
        $this->assertSame($customer->id, (int) $log->payload['pelanggan_id']);
        $campaign->refresh();
        $this->assertSame(1, (int) $campaign->total_target);
        $this->assertSame(1, $campaign->notifikasi()->count());
        $this->assertSame($campaign->id, $campaign->notifikasi()->first()->kampanye_broadcast_id);

        $log = app(BroadcastService::class)->logPengiriman($campaign->id);
        $this->assertSame(1, $log->total());
        $this->assertCount(1, $log->items());
    }

    public function test_livewire_broadcast_requires_crm_broadcast_permission(): void
    {
        $kasir = $this->makeUser('kasir-broadcast@test.com', 'kasir');
        $this->actingAs($kasir, 'web');

        Livewire::test(CrmDashboard::class)
            ->assertDontSee('Broadcast Promo')
            ->set('broadcastJudul', 'Promo kasir')
            ->set('broadcastPesan', 'Tidak boleh terkirim')
            ->call('kirimBroadcast')
            ->assertForbidden();

        $this->assertDatabaseCount('kampanye_broadcast', 0);
    }

    public function test_livewire_broadcast_creates_campaign_when_permitted(): void
    {
        $this->makeCustomer('Pelanggan Livewire', null);

        Livewire::test(CrmDashboard::class)
            ->set('broadcastJudul', 'Promo Livewire')
            ->set('broadcastPesan', 'Hemat hari ini')
            ->call('kirimBroadcast')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('kampanye_broadcast', [
            'judul' => 'Promo Livewire',
            'channel' => 'inapp',
            'status' => 'terkirim',
            'total_target' => 1,
        ]);
        $this->assertDatabaseHas('notifikasi_keluar', [
            'tipe' => 'inapp',
            'judul' => 'Promo Livewire',
            'tujuan' => null,
        ]);
    }

    public function test_legacy_crm_05_also_creates_campaign(): void
    {
        $this->makeCustomer('Pelanggan Legacy', null);

        $response = $this->postJson('/api/crm/broadcast', [
            'judul' => 'Promo Legacy',
            'pesan' => 'Promo tetap tercatat',
        ])->assertSuccessful();

        $campaignId = $response->json('data.kampanye_id');
        $this->assertNotNull($campaignId);
        $this->assertDatabaseHas('kampanye_broadcast', [
            'id' => $campaignId,
            'judul' => 'Promo Legacy',
            'channel' => 'inapp',
        ]);
        $this->assertDatabaseHas('notifikasi_keluar', [
            'kampanye_broadcast_id' => $campaignId,
            'tipe' => 'inapp',
        ]);
    }

    public function test_schedule_field_and_legacy_alias_are_stored_in_correct_column(): void
    {
        $canonical = now()->addHour();
        $response = $this->postJson('/api/crm/broadcast/kampanye', [
            'judul' => 'Jadwal Baru',
            'pesan' => 'Besok promo',
            'channel' => 'inapp',
            'dijadwalkan_at' => $canonical->toDateTimeString(),
        ])->assertCreated();

        $canonicalId = $response->json('data.id');
        $this->assertDatabaseHas('kampanye_broadcast', [
            'id' => $canonicalId,
            'status' => 'terjadwal',
            'dijadwalkan_at' => $canonical->format('Y-m-d H:i:s'),
        ]);

        $legacy = now()->addHours(2);
        $response = $this->postJson('/api/crm/broadcast/kampanye', [
            'judul' => 'Jadwal Alias',
            'pesan' => 'Alias typo tetap kompatibel',
            'channel' => 'inapp',
            'jadiwalkan_at' => $legacy->toDateTimeString(),
        ])->assertCreated();

        $this->assertDatabaseHas('kampanye_broadcast', [
            'id' => $response->json('data.id'),
            'dijadwalkan_at' => $legacy->format('Y-m-d H:i:s'),
        ]);
    }

    public function test_broadcast_validation_rejects_unknown_segment_past_schedule_and_day_without_month(): void
    {
        $this->postJson('/api/crm/broadcast/kampanye', [
            'judul' => 'Segment Salah',
            'pesan' => 'Test',
            'channel' => 'inapp',
            'segment' => [['tipe' => 'tidak_dikenal', 'nilai' => 1]],
        ])->assertJsonValidationErrors(['segment.0.tipe']);

        $this->postJson('/api/crm/broadcast/kampanye', [
            'judul' => 'Jadwal Lampau',
            'pesan' => 'Test',
            'channel' => 'inapp',
            'dijadwalkan_at' => now()->subMinute()->toDateTimeString(),
        ])->assertJsonValidationErrors(['dijadwalkan_at']);

        $this->postJson('/api/crm/broadcast/kampanye', [
            'judul' => 'Ulang Tahun',
            'pesan' => 'Test',
            'channel' => 'inapp',
            'segment' => [['tipe' => 'birthday_day', 'nilai' => 10]],
        ])->assertJsonValidationErrors(['segment']);
    }

    public function test_wa_without_configuration_and_email_without_destination_fail(): void
    {
        config(['services.wa' => ['url' => null, 'token' => null, 'device_id' => null]]);

        $wa = NotifikasiKeluar::create([
            'tipe' => 'wa',
            'tujuan' => '08123456789',
            'judul' => 'WA',
            'konten' => 'Pesan',
            'status' => 'pending',
        ]);
        $email = NotifikasiKeluar::create([
            'tipe' => 'email',
            'tujuan' => null,
            'judul' => 'Email',
            'konten' => 'Pesan',
            'status' => 'pending',
        ]);

        (new KirimNotifikasiJob($wa->id))->handle();
        (new KirimNotifikasiJob($email->id))->handle();

        $this->assertDatabaseHas('notifikasi_keluar', [
            'id' => $wa->id,
            'status' => 'gagal',
        ]);
        $this->assertStringContainsString(
            'gateway WA belum dikonfigurasi',
            (string) NotifikasiKeluar::find($wa->id)->error
        );
        $this->assertDatabaseHas('notifikasi_keluar', [
            'id' => $email->id,
            'status' => 'gagal',
        ]);
    }

    public function test_dashboard_broadcast_metrics_use_campaign_statuses(): void
    {
        foreach (['draft', 'terjadwal', 'terkirim', 'terkirim_sebagian', 'gagal'] as $status) {
            KampanyeBroadcast::create([
                'judul' => 'Kampanye '.$status,
                'pesan' => 'Pesan',
                'channel' => 'inapp',
                'status' => $status,
            ]);
        }

        $dashboard = new DashboardIndex;
        $broadcast = $dashboard->marketInsight['broadcast'];

        $this->assertSame(5, (int) $broadcast['total_kampanye']);
        $this->assertSame(1, (int) $broadcast['draft']);
        $this->assertSame(1, (int) $broadcast['terjadwal']);
        $this->assertSame(1, (int) $broadcast['terkirim']);
        $this->assertSame(1, (int) $broadcast['terkirim_sebagian']);
        $this->assertSame(1, (int) $broadcast['gagal']);
    }

    private function makeUser(string $email, string $roleName): User
    {
        $user = User::create([
            'name' => $email,
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole(Role::where('name', $roleName)->firstOrFail());
        $user->cabangs()->attach($this->cabang->id);

        return $user;
    }

    private function makeTier(string $nama, string $kode): TierMembership
    {
        return TierMembership::create([
            'nama' => $nama,
            'kode' => $kode,
            'min_belanja_12bulan' => 0,
            'diskon_persen' => 0,
            'poin_multiplier' => 1,
            'urutan' => 1,
        ]);
    }

    private function makeCustomer(string $nama, ?TierMembership $tier): Pelanggan
    {
        return Pelanggan::create([
            'nama' => $nama,
            'tier_membership_id' => $tier?->id,
        ]);
    }

    private function makeCampaign(?int $tierId = null): KampanyeBroadcast
    {
        return KampanyeBroadcast::create([
            'judul' => 'Kampanye Uji',
            'pesan' => 'Halo {nama}',
            'channel' => 'inapp',
            'segment' => $tierId ? [['tipe' => 'tier', 'nilai' => $tierId]] : [],
            'status' => 'draft',
            'user_id' => $this->marketing->id,
        ]);
    }
}
