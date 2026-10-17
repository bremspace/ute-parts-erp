<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserPreference;
use App\Modules\Notifikasi\Livewire\NotificationCenter;
use App\Modules\Notifikasi\Models\NotifikasiKeluar;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'kode' => 'CBG-01',
            'nama' => 'Cabang Pusat',
            'alamat' => 'Jl. Test No. 1',
            'telepon' => '08123456789',
            'is_pusat' => true,
        ]);

        $role = Role::create(['name' => 'super-admin']);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);
        $this->user->cabangs()->attach($this->cabang->id, ['is_default' => true]);

        session(['cabang_id' => $this->cabang->id]);
    }

    public function test_notification_service_saves_tracking_columns(): void
    {
        $this->actingAs($this->user);

        $service = app(NotificationService::class);
        $notif = $service->kirim(
            'inapp',
            $this->user->email,
            'Peringatan Stok Minimum',
            'Stok produk Kampas Rem menipis.',
            [
                'level' => 'warning',
                'action_url' => '/app/wms?tab=po',
            ]
        );

        $this->assertInstanceOf(NotifikasiKeluar::class, $notif);
        $this->assertNull($notif->read_at);
        $this->assertEquals($this->user->id, $notif->user_id);
        $this->assertEquals($this->cabang->id, $notif->cabang_id);
        $this->assertEquals('/app/wms?tab=po', $notif->action_url);
        $this->assertEquals('stok', $notif->kategori);
        $this->assertEquals('staff-gudang', $notif->target_role);
    }

    public function test_notification_center_renders_and_counts_unprocessed_items(): void
    {
        $this->actingAs($this->user);

        NotifikasiKeluar::create([
            'tipe' => 'inapp',
            'kategori' => 'transaksi',
            'judul' => 'Order Masuk',
            'konten' => 'Order baru dari POS #101',
            'status' => 'terkirim',
            'user_id' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        NotifikasiKeluar::create([
            'tipe' => 'inapp',
            'kategori' => 'stok',
            'judul' => 'Stok Habis',
            'konten' => 'Stok Busi Iridium sisa 0',
            'status' => 'terkirim',
            'user_id' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        Livewire::test(NotificationCenter::class)
            ->assertSet('totalUnprocessedCount', 2)
            ->assertSee('Order Masuk')
            ->assertSee('Stok Habis')
            ->assertSee('Belum Diproses');
    }

    public function test_notification_center_mark_as_read_and_mark_all_as_read(): void
    {
        $this->actingAs($this->user);

        $notif1 = NotifikasiKeluar::create([
            'tipe' => 'inapp',
            'kategori' => 'sistem',
            'judul' => 'Notif 1',
            'konten' => 'Isi notif 1',
            'status' => 'terkirim',
            'user_id' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $notif2 = NotifikasiKeluar::create([
            'tipe' => 'inapp',
            'kategori' => 'sistem',
            'judul' => 'Notif 2',
            'konten' => 'Isi notif 2',
            'status' => 'terkirim',
            'user_id' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        Livewire::test(NotificationCenter::class)
            ->call('markAsRead', $notif1->id)
            ->assertDispatched('alert');

        $this->assertNotNull($notif1->fresh()->read_at);
        $this->assertNull($notif2->fresh()->read_at);

        Livewire::test(NotificationCenter::class)
            ->call('markAllAsRead')
            ->assertDispatched('alert');

        $this->assertNotNull($notif2->fresh()->read_at);
    }

    public function test_notification_center_active_polling_dispatches_event_on_new_item(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test(NotificationCenter::class);

        $newNotif = NotifikasiKeluar::create([
            'tipe' => 'inapp',
            'kategori' => 'servis',
            'judul' => 'Servis Selesai',
            'konten' => 'Tiket SRV-001 siap diambil',
            'status' => 'terkirim',
            'user_id' => $this->user->id,
            'cabang_id' => $this->cabang->id,
            'action_url' => '/app/servis',
        ]);

        $component->call('checkNewNotifications')
            ->assertDispatched('ute:notification-arrived');
    }

    public function test_notification_center_scopes_notifications_by_user_role(): void
    {
        $roleKasir = Role::create(['name' => 'kasir']);
        $roleTeknisi = Role::create(['name' => 'teknisi']);

        $userKasir = User::factory()->create();
        $userKasir->assignRole($roleKasir);
        $userKasir->cabangs()->attach($this->cabang->id, ['is_default' => true]);

        // Buat notif untuk kasir (transaksi)
        NotifikasiKeluar::create([
            'tipe' => 'inapp',
            'kategori' => 'transaksi',
            'target_role' => 'kasir',
            'judul' => 'Kas Masuk',
            'konten' => 'Pembayaran pesanan lunas',
            'cabang_id' => $this->cabang->id,
        ]);

        // Buat notif khusus teknisi (servis)
        NotifikasiKeluar::create([
            'tipe' => 'inapp',
            'kategori' => 'servis',
            'target_role' => 'teknisi',
            'judul' => 'Tugas Servis Baru',
            'konten' => 'Periksa LCD iPhone 13',
            'cabang_id' => $this->cabang->id,
        ]);

        // Kasir hanya melihat notif kasir, bukan teknisi
        $this->actingAs($userKasir);
        Livewire::test(NotificationCenter::class)
            ->assertSet('totalUnprocessedCount', 1)
            ->assertSee('Kas Masuk')
            ->assertDontSee('Tugas Servis Baru');
    }

    public function test_notification_center_user_event_preference_toggle_and_filtering(): void
    {
        $this->actingAs($this->user);

        NotifikasiKeluar::create([
            'tipe' => 'inapp',
            'kategori' => 'stok',
            'judul' => 'Stok Filter Oli Tipis',
            'konten' => 'Sisa 2 pcs',
            'cabang_id' => $this->cabang->id,
        ]);

        NotifikasiKeluar::create([
            'tipe' => 'inapp',
            'kategori' => 'servis',
            'judul' => 'Unit Servis Masuk',
            'konten' => 'Pengecekan unit konsumen',
            'cabang_id' => $this->cabang->id,
        ]);

        $component = Livewire::test(NotificationCenter::class)
            ->assertSet('totalUnprocessedCount', 2)
            ->assertSee('Stok Filter Oli Tipis')
            ->assertSee('Unit Servis Masuk');

        // Matikan event kategori 'stok'
        $component->call('toggleEventPreference', 'stok')
            ->assertSet('totalUnprocessedCount', 1)
            ->assertDontSee('Stok Filter Oli Tipis')
            ->assertSee('Unit Servis Masuk');

        // Pastikan preferensi tersimpan di DB UserPreference
        $savedPref = UserPreference::getPref($this->user->id, 'notification_events');
        $this->assertIsArray($savedPref);
        $this->assertNotContains('stok', $savedPref);
        $this->assertContains('servis', $savedPref);
    }

    public function test_notification_center_buka_aksi_redirects_and_marks_as_read(): void
    {
        $this->actingAs($this->user);

        $notif = NotifikasiKeluar::create([
            'tipe' => 'inapp',
            'kategori' => 'servis',
            'judul' => 'Perbaikan Siap',
            'konten' => 'Tiket #102 selesai',
            'action_url' => '/app/servis',
            'cabang_id' => $this->cabang->id,
            'user_id' => $this->user->id,
        ]);

        Livewire::test(NotificationCenter::class)
            ->call('bukaAksi', $notif->id)
            ->assertRedirect('/app/servis');

        $this->assertNotNull($notif->fresh()->read_at);
    }

    public function test_backoffice_layout_includes_notification_center_for_authed_user(): void
    {
        $this->actingAs($this->user);

        $response = $this->get('/app/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Pusat Notifikasi');
    }
}
