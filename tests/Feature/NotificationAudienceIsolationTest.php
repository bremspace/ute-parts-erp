<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Notifikasi\Livewire\NotificationCenter;
use App\Modules\Notifikasi\Models\NotifikasiKeluar;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Isolasi audience notifikasi: baris konsumen (pelanggan_id) tidak boleh
 * terlihat/diakses dari bell backoffice, dan baris staf tidak boleh punya
 * pelanggan_id (bocor ke inbox konsumen) — termasuk saat dibuat dari konteks
 * login auth('customer'). Redirect bukaAksi tidak boleh keluar zona /app.
 */
class NotificationAudienceIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;

    protected User $superAdmin;

    protected Pelanggan $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'kode' => 'CBG-ISO',
            'nama' => 'Cabang Isolasi',
            'alamat' => 'Jl. Test No. 9',
            'telepon' => '0812000000',
            'is_pusat' => true,
        ]);

        $role = Role::create(['name' => 'super-admin']);
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole($role);
        $this->superAdmin->cabangs()->attach($this->cabang->id, ['is_default' => true]);

        session(['cabang_id' => $this->cabang->id]);

        $this->pelanggan = Pelanggan::create([
            'nama' => 'Konsumen Isolasi',
            'telepon' => '0811999999',
            'is_reseller' => false,
        ]);
    }

    public function test_consumer_notification_never_appears_in_backoffice_center(): void
    {
        $notif = app(NotificationService::class)->kirim(
            'inapp',
            $this->pelanggan->email,
            'Pembayaran Diterima',
            'Pembayaran pesanan INV-ISO-1 diterima.',
            [
                'pelanggan_id' => $this->pelanggan->id,
                'kategori' => 'transaksi',
                'action_url' => '/account',
            ]
        );

        // Audience pelanggan: tidak boleh punya target_role/user_id yang menariknya ke bell staf
        $this->assertNull($notif->target_role);
        $this->assertNull($notif->user_id);
        $this->assertEquals('/account', $notif->action_url);

        // Super-admin backoffice pun tidak melihat baris ini
        $this->actingAs($this->superAdmin);
        Livewire::test(NotificationCenter::class)
            ->assertSet('totalUnprocessedCount', 0)
            ->assertDontSee('Pembayaran Diterima');

        // Tetap terlihat di inbox konsumen
        $this->assertTrue(
            NotifikasiKeluar::forPelanggan($this->pelanggan->id)->where('id', $notif->id)->exists()
        );
    }

    public function test_staff_notification_created_in_customer_context_keeps_pelanggan_id_null(): void
    {
        // Konteks halaman konsumen: auth('customer') aktif, auth() (web/staf) tidak
        $this->actingAs($this->pelanggan, 'customer');

        $notif = app(NotificationService::class)->kirim(
            'inapp',
            null,
            'Pesanan Online Baru (INV-ISO-2)',
            'Pesanan baru senilai Rp 150.000 menunggu pembayaran.',
            [
                'cabang_id' => $this->cabang->id,
                'target_role' => 'kasir',
                'kategori' => 'transaksi',
                'action_url' => '/app/pos/online-orders',
            ]
        );

        // Audience staf: pelanggan_id dipaksa null — tidak masuk inbox konsumen
        $this->assertNull($notif->pelanggan_id);
        $this->assertFalse(
            NotifikasiKeluar::forPelanggan($this->pelanggan->id)->where('id', $notif->id)->exists()
        );

        // Tapi tetap tampil di bell backoffice utk target_role kasir / super-admin
        $this->actingAs($this->superAdmin);
        Livewire::test(NotificationCenter::class)
            ->assertSet('totalUnprocessedCount', 1)
            ->assertSee('Pesanan Online Baru (INV-ISO-2)');
    }

    public function test_buka_aksi_maps_consumer_url_to_backoffice_target(): void
    {
        $notif = NotifikasiKeluar::create([
            'tipe' => 'inapp',
            'kategori' => 'transaksi',
            'judul' => 'Order Lunas ISO',
            'konten' => 'Pembayaran diterima.',
            'status' => 'terkirim',
            'user_id' => $this->superAdmin->id,
            'cabang_id' => $this->cabang->id,
            'action_url' => '/account',
        ]);

        $this->actingAs($this->superAdmin);
        Livewire::test(NotificationCenter::class)
            ->call('bukaAksi', $notif->id)
            ->assertRedirect('/app/pos/online-orders');

        $this->assertNotNull($notif->fresh()->read_at);
    }

    public function test_buka_aksi_cannot_touch_consumer_row_even_for_super_admin(): void
    {
        $notif = NotifikasiKeluar::create([
            'tipe' => 'inapp',
            'kategori' => 'transaksi',
            'judul' => 'Baris Konsumen Tersembunyi',
            'konten' => 'Isi internal konsumen.',
            'status' => 'terkirim',
            'pelanggan_id' => $this->pelanggan->id,
            'cabang_id' => $this->cabang->id,
            'action_url' => '/account',
        ]);

        $this->actingAs($this->superAdmin);
        Livewire::test(NotificationCenter::class)
            ->call('bukaAksi', $notif->id)
            ->assertNoRedirect();

        $this->assertNull($notif->fresh()->read_at);
    }

    public function test_mark_as_read_cannot_touch_consumer_row(): void
    {
        $notif = NotifikasiKeluar::create([
            'tipe' => 'inapp',
            'kategori' => 'transaksi',
            'judul' => 'Baris Konsumen Mark',
            'konten' => 'Isi.',
            'status' => 'terkirim',
            'pelanggan_id' => $this->pelanggan->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $this->actingAs($this->superAdmin);
        Livewire::test(NotificationCenter::class)
            ->call('markAsRead', $notif->id);

        // Tidak boleh menandai baris milik zona lain (scoped find → no-op)
        $this->assertNull($notif->fresh()->read_at);
    }

    public function test_safe_action_url_maps_only_consumer_zone_urls(): void
    {
        $this->assertEquals('/app/pos/online-orders', NotifikasiKeluar::safeActionUrlForStaff('/account', 'transaksi'));
        $this->assertEquals('/app/servis', NotifikasiKeluar::safeActionUrlForStaff('/account?tab=x', 'servis'));
        $this->assertEquals('/app/dashboard', NotifikasiKeluar::safeActionUrlForStaff('/checkout', 'sistem'));
        $this->assertEquals('/app/wms?tab=po', NotifikasiKeluar::safeActionUrlForStaff('/app/wms?tab=po', 'stok'));
        $this->assertNull(NotifikasiKeluar::safeActionUrlForStaff(null));
    }

    public function test_polling_dispatches_staff_rows_only(): void
    {
        $this->actingAs($this->superAdmin);
        $component = Livewire::test(NotificationCenter::class);

        NotifikasiKeluar::create([
            'tipe' => 'inapp',
            'kategori' => 'stok',
            'judul' => 'Stok Menipis ISO',
            'konten' => 'Sisa 2 pcs.',
            'status' => 'terkirim',
            'user_id' => $this->superAdmin->id,
            'cabang_id' => $this->cabang->id,
            'action_url' => '/app/wms',
        ]);
        NotifikasiKeluar::create([
            'tipe' => 'inapp',
            'kategori' => 'transaksi',
            'judul' => 'Baris Konsumen Poll',
            'konten' => 'Isi konsumen.',
            'status' => 'terkirim',
            'pelanggan_id' => $this->pelanggan->id,
            'cabang_id' => $this->cabang->id,
            'action_url' => '/account',
        ]);

        // Hanya baris staf yang dipoll — baris konsumen tidak pernah di-dispatch ke JS bell
        $component->call('checkNewNotifications')
            ->assertDispatched('ute:notification-arrived');
    }

    public function test_polling_ignores_consumer_rows(): void
    {
        $this->actingAs($this->superAdmin);
        $component = Livewire::test(NotificationCenter::class);

        NotifikasiKeluar::create([
            'tipe' => 'inapp',
            'kategori' => 'transaksi',
            'judul' => 'Hanya Konsumen ISO',
            'konten' => 'Isi.',
            'status' => 'terkirim',
            'pelanggan_id' => $this->pelanggan->id,
            'cabang_id' => $this->cabang->id,
            'action_url' => '/account',
        ]);

        $component->call('checkNewNotifications')
            ->assertNotDispatched('ute:notification-arrived');
    }
}
