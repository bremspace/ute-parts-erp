<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Livewire\ServisBoard;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Services\ServisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServisPolaBookingDanDitolakTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Pusat',
            'kode' => 'CP01',
            'alamat' => 'Jl. Pusat No. 1',
            'telepon' => '081234567890',
            'is_aktif' => true,
        ]);

        session(['cabang_id' => $this->cabang->id]);

        $permissions = [
            'servis.view',
            'servis.create',
            'servis.update-status',
            'laporan.cabang',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $superRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superRole->syncPermissions($permissions);

        Role::firstOrCreate(['name' => 'teknisi', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin-toko', 'guard_name' => 'web']);

        $this->user = User::factory()->create();
        $this->user->assignRole('super-admin');
    }

    public function test_accessor_terjemahan_pola_pada_tiket_servis(): void
    {
        $tiket = TiketServis::create([
            'cabang_id' => $this->cabang->id,
            'no_tiket' => 'SRV-TEST-001',
            'nama_pelanggan' => 'Budi',
            'no_hp' => '081234567890',
            'jenis_hp' => 'Samsung A52',
            'keluhan' => 'Layar bergaris',
            'status' => 'diterima',
            'tipe_kunci' => 'pola',
            'kunci_terenkripsi' => '1-2-3-6-9',
        ]);

        $terjemahan = $tiket->terjemahan_pola;
        $this->assertIsArray($terjemahan);
        $this->assertEquals('1 ➔ 2 ➔ 3 ➔ 6 ➔ 9', $terjemahan['urutan']);
        $this->assertStringContainsString('Kiri Atas', $terjemahan['posisi']);
        $this->assertStringContainsString('Kanan Bawah', $terjemahan['posisi']);
        $this->assertStringContainsString('Kanan', $terjemahan['arah']);
        $this->assertStringContainsString('Bawah', $terjemahan['arah']);
    }

    public function test_booking_online_intake_modal_dan_simpan_di_kanban(): void
    {
        $tiket = TiketServis::create([
            'cabang_id' => $this->cabang->id,
            'no_tiket' => 'SRV-BOOK-001',
            'nama_pelanggan' => 'Andi',
            'no_hp' => '081987654321',
            'jenis_hp' => 'iPhone 11',
            'keluhan' => 'Baterai drop',
            'status' => 'diajukan_online',
            'kondisi_fisik' => 'Layar mulus',
            'kelengkapan' => 'Unit only',
        ]);

        Livewire::actingAs($this->user)
            ->test(ServisBoard::class)
            ->call('openIntakeBookingModal', $tiket->id)
            ->assertSet('showIntakeBookingModal', true)
            ->assertSet('intakeBookingTiketId', $tiket->id)
            ->call('toggleIntakeKondisiFisik', 'Layar baret halus')
            ->call('toggleIntakeKelengkapan', 'Dus box')
            ->set('intakeForm.catatan_intake', 'Diserahkan langsung oleh konsumen di meja gerai')
            ->call('simpanIntakeBooking')
            ->assertSet('showIntakeBookingModal', false);

        $tiket->refresh();
        $this->assertEquals('diterima', $tiket->status);
        $kondisiFisikString = is_array($tiket->kondisi_fisik) ? implode(' ', $tiket->kondisi_fisik) : (string) $tiket->kondisi_fisik;
        $this->assertStringContainsString('baret halus', $kondisiFisikString);
        $this->assertStringContainsString('Dus box', $kondisiFisikString);
        $this->assertNotNull($tiket->tanggal_terima);
    }

    public function test_penolakan_estimasi_via_tracking_publik_dengan_alasan_khusus(): void
    {
        $service = app(ServisService::class);
        $tiket = TiketServis::create([
            'cabang_id' => $this->cabang->id,
            'no_tiket' => 'SRV-REJ-001',
            'nama_pelanggan' => 'Siti',
            'no_hp' => '081234567899',
            'jenis_hp' => 'Xiaomi Redmi Note 10',
            'keluhan' => 'Mati total',
            'status' => 'diagnosa',
        ]);

        $service->setEstimasi($tiket, 450000, 'Perlu ganti IC Power dan reballing', $this->user, [
            ['nama_item' => 'IC Power', 'tipe' => 'part', 'qty' => 1, 'harga' => 300000, 'subtotal' => 300000],
            ['nama_item' => 'Jasa Reball', 'tipe' => 'jasa', 'qty' => 1, 'harga' => 150000, 'subtotal' => 150000],
        ]);

        $tiket->refresh();
        $this->assertEquals('menunggu_approval', $tiket->status);
        $this->assertNotNull($tiket->token_approval);

        // Konsumen menolak via route public tracking dengan alasan kustom
        $response = $this->post(route('servis.tracking.reject', $tiket->token_approval), [
            'alasan' => 'Biaya perbaikan melebihi anggaran saya saat ini',
        ]);

        $response->assertSessionHas('info');

        $tiket->refresh();
        $this->assertEquals('ditolak', $tiket->status);
        $this->assertEquals('Biaya perbaikan melebihi anggaran saya saat ini', $tiket->alasan_ditolak);
    }

    public function test_kanban_workflow_revisi_estimasi_dan_retur_batal_unit(): void
    {
        $tiket = TiketServis::create([
            'cabang_id' => $this->cabang->id,
            'no_tiket' => 'SRV-REVISI-001',
            'nama_pelanggan' => 'Doni',
            'no_hp' => '081234567891',
            'jenis_hp' => 'Oppo Reno 6',
            'keluhan' => 'Speaker kresek',
            'status' => 'ditolak',
        ]);

        // Aksi 1: Revisi Estimasi (kembali ke diagnosa)
        Livewire::actingAs($this->user)
            ->test(ServisBoard::class)
            ->call('revisiEstimasiDitolak', $tiket->id);

        $tiket->refresh();
        $this->assertEquals('diagnosa', $tiket->status);

        // Ubah kembali ke ditolak untuk menguji retur/batal
        $tiket->update(['status' => 'ditolak']);

        // Aksi 2: Batal / Retur Unit ke Pelanggan (status diambil)
        Livewire::actingAs($this->user)
            ->test(ServisBoard::class)
            ->call('openBatalReturModal', $tiket->id)
            ->assertSet('showBatalReturModal', true)
            ->set('batalReturCatatan', 'Unit diserahkan kembali tanpa perbaikan. Bebas biaya.')
            ->call('prosesBatalRetur')
            ->assertSet('showBatalReturModal', false);

        $tiket->refresh();
        $this->assertEquals('diambil', $tiket->status);
        $this->assertNotNull($tiket->tanggal_diambil);
    }
}
