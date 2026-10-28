<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Livewire\ServisBoard;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Models\TiketServisProgress;
use App\Modules\Servis\Services\ServisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServisFase4FixTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super-admin', 'owner', 'admin-toko', 'teknisi', 'kasir', 'customer'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Ute Parts Pusat',
            'kode' => 'UPP',
            'is_pusat' => true,
            'aktif' => true,
        ]);

        $this->user = User::create([
            'name' => 'Teknisi Admin',
            'email' => 'teknisi@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->cabangs()->attach($this->cabang->id);

        Permission::firstOrCreate(['name' => 'servis.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'servis.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'servis.update-status', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'servis.pekerjaan', 'guard_name' => 'web']);
        $this->user->givePermissionTo(['servis.view', 'servis.create', 'servis.update-status', 'servis.pekerjaan']);

        $this->actingAs($this->user);
        session(['cabang_id' => $this->cabang->id]);
    }

    public function test_t13_auto_sync_pelanggan_crm_saat_terima_unit_dan_booking(): void
    {
        $service = app(ServisService::class);

        // Walk-in intake baru
        $tiket = $service->terimaUnit([
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Budi Santoso',
            'telepon_pelanggan' => '081298765432',
            'alamat_pelanggan' => 'Jl. Merdeka No. 10',
            'jenis_hp' => 'iPhone 13',
            'keluhan' => 'Layar bergaris',
        ], $this->user);

        $this->assertDatabaseHas('pelanggan', [
            'telepon' => '081298765432',
            'nama' => 'Budi Santoso',
        ]);
        $this->assertNotNull($tiket->pelanggan_id);

        // Booking online baru
        $booking = $service->bookingOnline([
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Siti Aminah',
            'telepon_pelanggan' => '081311223344',
            'jenis_hp' => 'Samsung S22',
            'keluhan' => 'Baterai boros',
        ]);

        $this->assertDatabaseHas('pelanggan', [
            'telepon' => '081311223344',
            'nama' => 'Siti Aminah',
        ]);
        $this->assertNotNull($booking->pelanggan_id);
    }

    public function test_t14_pattern_lock_view_mockup_dan_glow_render(): void
    {
        $view = $this->blade('<x-pattern-lock wire:model="pola" :dark="true" />');

        $view->assertSee('Buka Pola Layar');
        $view->assertSee('dot-active-glow');
        $view->assertSee('rounded-[2.5rem]');
    }

    public function test_t15_kanban_struk_modal_spk_vs_kwitansi_dan_item_pekerjaan(): void
    {
        $tiket = TiketServis::create([
            'cabang_id' => $this->cabang->id,
            'no_tiket' => 'SRV-TEST-001',
            'nama_pelanggan' => 'Rian Hidayat',
            'telepon_pelanggan' => '085712345678',
            'jenis_hp' => 'Xiaomi Redmi Note 11',
            'keluhan' => 'Mati total',
            'status' => 'dikerjakan',
            'status_pembayaran' => 'belum_bayar',
            'estimasi_biaya' => 250000,
            'pengerjaan_deadline_at' => now()->addHours(3),
        ]);

        // Cek buka struk SPK sebelum lunas
        Livewire::test(ServisBoard::class)
            ->call('bukaStrukServis', $tiket->id)
            ->assertSet('showReceiptModal', true)
            ->assertSet('receiptData.is_spk', true)
            ->assertSet('receiptData.is_lunas', false)
            ->assertSet('receiptData.label_struk', 'SPK / TANDA TERIMA ESTIMASI');

        // Update jadi lunas -> cek kwitansi
        $tiket->update(['status_pembayaran' => 'lunas']);

        Livewire::test(ServisBoard::class)
            ->call('bukaStrukServis', $tiket->id)
            ->assertSet('showReceiptModal', true)
            ->assertSet('receiptData.is_spk', false)
            ->assertSet('receiptData.is_lunas', true)
            ->assertSet('receiptData.label_struk', 'KWITANSI PELUNASAN SERVIS');

        // Tambah baris pekerjaan part & jasa
        Livewire::test(ServisBoard::class)
            ->call('addPekerjaanRow', 'jasa')
            ->assertCount('pekerjaanItems', 1)
            ->call('addPekerjaanRow', 'part')
            ->assertCount('pekerjaanItems', 2);
    }

    public function test_t16_progress_bar_dan_validasi_catatan_foto_sebelum_qc_selesai(): void
    {
        $tiket = TiketServis::create([
            'cabang_id' => $this->cabang->id,
            'no_tiket' => 'SRV-TEST-002',
            'nama_pelanggan' => 'Dewi Lestari',
            'telepon_pelanggan' => '089876543210',
            'jenis_hp' => 'Oppo Reno 8',
            'keluhan' => 'Ganti LCD',
            'status' => 'dikerjakan',
            'status_pembayaran' => 'belum_bayar',
            'estimasi_biaya' => 450000,
        ]);

        // Coba transisi ke QC tanpa catatan & foto -> wajib ditolak & buka modal progress
        Livewire::test(ServisBoard::class)
            ->call('updateStatus', $tiket->id, 'qc')
            ->assertSet('showProgressModal', true)
            ->assertDispatched('alert');

        $this->assertEquals('dikerjakan', $tiket->fresh()->status);

        // Catat progress dengan deskripsi & foto
        TiketServisProgress::create([
            'tiket_servis_id' => $tiket->id,
            'user_id' => $this->user->id,
            'persentase' => 80,
            'judul' => 'Perakitan LCD Selesai',
            'deskripsi' => 'Modul LCD terpasang dan touch responsif normal',
            'foto' => ['https://example.com/foto-unit.jpg'],
            'tampilkan_ke_konsumen' => true,
        ]);

        // Transisi ke QC setelah ada catatan & foto -> berhasil
        Livewire::test(ServisBoard::class)
            ->call('updateStatus', $tiket->id, 'qc')
            ->assertDispatched('alert');

        $this->assertEquals('qc', $tiket->fresh()->status);
    }
}
