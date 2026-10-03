<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Services\ExportLaporanService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Livewire\ServisBoard;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Models\TiketServisItem;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ServisBoardBayarTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CabangSeeder::class);
        $this->seed(AkunCoaSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::first();

        $this->user = User::create([
            'name' => 'Admin Kasir Servis',
            'email' => 'admin-kasir-servis@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user, 'web');
    }

    private function buatTiket(string $status = 'selesai', float $estimasi = 250000, string $statusBayar = 'belum_bayar'): TiketServis
    {
        return TiketServis::create([
            'no_tiket' => 'SRV-TEST-'.uniqid(),
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Pelanggan Uji',
            'telepon_pelanggan' => '081234567890',
            'jenis_hp' => 'iPhone 13',
            'tipe_kunci' => 'tidak_ada',
            'keluhan' => 'Ganti LCD',
            'status' => $status,
            'status_pembayaran' => $statusBayar,
            'sumber' => 'walkin',
            'estimasi_biaya' => $estimasi,
        ]);
    }

    public function test_buka_modal_bayar_pada_tiket_selesai(): void
    {
        $tiket = $this->buatTiket('selesai', 150000);

        TiketServisItem::create([
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'jasa',
            'nama_item' => 'Jasa Pemasangan LCD',
            'qty' => 1,
            'harga' => 150000,
        ]);

        Livewire::test(ServisBoard::class)
            ->call('openBayarModal', $tiket->id)
            ->assertSet('showBayarModal', true)
            ->assertSet('bayarTiketId', $tiket->id)
            ->assertSet('bayarTotalTagihan', 150000)
            ->assertSet('bayarMetode', 'tunai');
    }

    public function test_proses_bayar_berhasil_lunas_dan_jurnal_tercatat(): void
    {
        $tiket = $this->buatTiket('selesai', 200000);

        TiketServisItem::create([
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'jasa',
            'nama_item' => 'Jasa Ganti Baterai',
            'qty' => 1,
            'harga' => 200000,
        ]);

        Livewire::test(ServisBoard::class)
            ->call('openBayarModal', $tiket->id)
            ->set('bayarMetode', 'transfer')
            ->set('bayarCatatan', 'Ref TF BCA-12345')
            ->call('prosesBayar')
            ->assertSet('showBayarModal', false)
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type'] ?? null) === 'success');

        $tiket->refresh();
        $this->assertSame('lunas', $tiket->status_pembayaran);
        $this->assertSame('transfer', $tiket->metode_pembayaran);
        $this->assertNotNull($tiket->tanggal_bayar);
        $this->assertNotNull($tiket->no_jurnal_bayar);

        // Verifikasi jurnal pelunasan: Kas/Bank (debit) & Piutang (kredit)
        $jurnalKas = JurnalAkuntansi::where('no_jurnal', $tiket->no_jurnal_bayar)
            ->whereHas('akun', fn ($q) => $q->whereIn('kode', ['110-01', '110-02', '110-04']))
            ->first();
        $jurnalPiutang = JurnalAkuntansi::where('no_jurnal', $tiket->no_jurnal_bayar)
            ->whereHas('akun', fn ($q) => $q->where('kode', '120-01'))
            ->first();

        $this->assertNotNull($jurnalKas);
        $this->assertEquals(200000, $jurnalKas->debit);
        $this->assertNotNull($jurnalPiutang);
        $this->assertEquals(200000, $jurnalPiutang->kredit);
    }

    public function test_tiket_selesai_belum_lunas_tidak_bisa_diambil(): void
    {
        $tiket = $this->buatTiket('selesai', 300000, 'belum_bayar');

        Livewire::test(ServisBoard::class)
            ->call('updateStatus', $tiket->id, 'diambil')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type'] ?? null) === 'error' && str_contains($params[0]['message'] ?? $params['message'] ?? '', 'lunas'));

        $tiket->refresh();
        $this->assertSame('selesai', $tiket->status, 'Status tiket tidak boleh berpindah ke diambil jika belum lunas');
    }

    public function test_tiket_selesai_sudah_lunas_bisa_diambil(): void
    {
        $tiket = $this->buatTiket('selesai', 300000, 'belum_bayar');

        TiketServisItem::create([
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'jasa',
            'nama_item' => 'Jasa Servis Lengkap',
            'qty' => 1,
            'harga' => 300000,
        ]);

        Livewire::test(ServisBoard::class)
            ->call('openBayarModal', $tiket->id)
            ->call('prosesBayar');

        $tiket->refresh();
        $this->assertSame('lunas', $tiket->status_pembayaran);

        Livewire::test(ServisBoard::class)
            ->call('updateStatus', $tiket->id, 'diambil')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type'] ?? null) === 'success');

        $tiket->refresh();
        $this->assertSame('diambil', $tiket->status);
    }

    public function test_export_laporan_servis_menyertakan_kolom_pembayaran(): void
    {
        $tiket = $this->buatTiket('selesai', 175000, 'lunas');
        $tiket->update([
            'metode_pembayaran' => 'qris',
            'tanggal_selesai' => now(),
            'tanggal_bayar' => now(),
        ]);

        $exportService = app(ExportLaporanService::class);
        $path = $exportService->export('servis', now()->subDay()->toDateString(), now()->addDay()->toDateString(), $this->cabang->id, null, 'csv');

        $this->assertTrue(Storage::disk('local')->exists($path));
        $content = Storage::disk('local')->get($path);
        $this->assertStringContainsString('STATUS BAYAR', $content);
        $this->assertStringContainsString('METODE', $content);
        $this->assertStringContainsString('TGL BAYAR', $content);
        $this->assertStringContainsString('lunas', $content);
        $this->assertStringContainsString('qris', $content);

        Storage::disk('local')->delete($path);
    }
}
