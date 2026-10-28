<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Livewire\ReturPenjualanIndex;
use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Livewire\RiwayatAktivitas;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditFase3FixTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'lihat-audit-log', 'guard_name' => 'web']);
        Role::findByName('super-admin', 'web')->givePermissionTo('lihat-audit-log');

        $this->cabang = Cabang::create(['nama' => 'Cabang Audit Test', 'kode' => 'CAT01', 'is_active' => true]);

        $this->user = User::create([
            'name' => 'Auditor Admin',
            'email' => 'auditor@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);
    }

    public function test_t11_label_kolom_dan_entitas_audit_trail_ramah_awam(): void
    {
        // Uji terjemahan kolom teknis ke bahasa Indonesia
        $this->assertEquals('Nomor Transaksi', RiwayatAktivitas::labelKolom('no_transaksi'));
        $this->assertEquals('Total Tagihan', RiwayatAktivitas::labelKolom('total_akhir'));
        $this->assertEquals('Jumlah Bayar', RiwayatAktivitas::labelKolom('jumlah_bayar'));
        $this->assertEquals('Alasan Batal / Void', RiwayatAktivitas::labelKolom('void_alasan'));
        $this->assertEquals('Potongan Diskon', RiwayatAktivitas::labelKolom('diskon_nominal'));
        $this->assertEquals('ID Pelanggan', RiwayatAktivitas::labelKolom('pelanggan_id'));
        $this->assertEquals('Bentuk Penyelesaian Retur', RiwayatAktivitas::labelKolom('tipe_penyelesaian'));

        // Uji terjemahan entitas model
        $this->assertEquals('Transaksi Penjualan', RiwayatAktivitas::labelEntitas(Transaksi::class));
        $this->assertEquals('Retur Penjualan', RiwayatAktivitas::labelEntitas(ReturnPenjualan::class));

        // Uji format nilai ramah awam
        $this->assertEquals('Rp 150.000', RiwayatAktivitas::formatNilai(150000, 'total_akhir'));
        $this->assertEquals('Lunas', RiwayatAktivitas::formatNilai('lunas', 'status'));
        $this->assertEquals('Pengembalian Dana (Refund)', RiwayatAktivitas::formatNilai('refund', 'tipe_penyelesaian'));
        $this->assertEquals('Ya / Aktif', RiwayatAktivitas::formatNilai(true, 'is_active'));
    }

    public function test_t12_tombol_audit_retur_konsumen_membuka_modal(): void
    {
        session(['cabang_id' => $this->cabang->id]);

        $pelanggan = Pelanggan::create([
            'nama' => 'Konsumen Retur',
            'telepon' => '0899999999',
            'tipe_konsumen' => 'retail',
        ]);

        $trx = Transaksi::create([
            'no_transaksi' => 'TRX-RET-001',
            'cabang_id' => $this->cabang->id,
            'user_id' => $this->user->id,
            'pelanggan_id' => $pelanggan->id,
            'status' => 'lunas',
            'total_akhir' => 100000,
            'subtotal' => 100000,
            'jumlah_bayar' => 100000,
            'kembalian' => 0,
            'diskon_nominal' => 0,
            'pajak_nominal' => 0,
        ]);

        $retur = ReturnPenjualan::create([
            'no_return' => 'RET-POS-001',
            'cabang_id' => $this->cabang->id,
            'user_id' => $this->user->id,
            'transaksi_id' => $trx->id,
            'pelanggan_id' => $pelanggan->id,
            'tanggal' => now()->toDateString(),
            'status' => 'selesai',
            'jumlah' => 50000,
            'tipe_penyelesaian' => 'refund',
            'metode_pengembalian' => 'kas',
            'alasan' => 'Barang cacat pabrik',
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(ReturPenjualanIndex::class)
            ->call('bukaRiwayat', 'retur_penjualan', $retur->id);

        $this->assertTrue($component->get('showRiwayatModal'));
        $this->assertEquals('retur_penjualan', $component->get('riwayatTipe'));
        $this->assertEquals($retur->id, $component->get('riwayatId'));

        // Cek tutupRiwayat
        $component->call('tutupRiwayat');
        $this->assertFalse($component->get('showRiwayatModal'));

        // Cek bahwa view me-render partial riwayat-modal
        $bladeContent = file_get_contents(resource_path('views/modules/pos/livewire/retur-penjualan-index.blade.php'));
        $this->assertStringContainsString("@include('partials.riwayat-modal')", $bladeContent);
    }
}
