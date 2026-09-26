<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Services\ServisService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServisPembayaranFaseBTest extends TestCase
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
            'name' => 'Teknisi Admin',
            'email' => 'teknisi-admin@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user, 'web');
    }

    private function buatTiket(string $status = 'qc', float $estimasi = 200000): TiketServis
    {
        return TiketServis::create([
            'no_tiket' => 'SRV-TEST-'.uniqid(),
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Pelanggan Test',
            'telepon_pelanggan' => '08123456789',
            'jenis_hp' => 'Samsung S21',
            'tipe_kunci' => 'tidak_ada',
            'keluhan' => 'Layar bergaris',
            'status' => $status,
            'sumber' => 'walkin',
            'estimasi_biaya' => $estimasi,
        ]);
    }

    /**
     * Test 1: onSelesai memposting jurnal dengan Debit 120-01 (Piutang Usaha) dan status_pembayaran 'belum_bayar'.
     */
    public function test_onselesai_memposting_jurnal_piutang_dan_status_belum_bayar(): void
    {
        $tiket = $this->buatTiket('qc', 250000);

        app(ServisService::class)->updateStatus($tiket, 'selesai', $this->user, 'Pekerjaan selesai');

        $tiket->refresh();
        $this->assertSame('selesai', $tiket->status);
        $this->assertSame('belum_bayar', $tiket->status_pembayaran);
        $this->assertNull($tiket->tanggal_bayar);

        $piutang = AkunCOA::where('kode', '120-01')->firstOrFail();
        $pendapatan = AkunCOA::where('kode', '420-01')->firstOrFail();

        $this->assertDatabaseHas('jurnal_akuntansi', [
            'akun_coa_id' => $piutang->id,
            'debit' => 250000,
            'kredit' => 0,
            'sumber' => 'servis',
        ]);

        $this->assertDatabaseHas('jurnal_akuntansi', [
            'akun_coa_id' => $pendapatan->id,
            'debit' => 0,
            'kredit' => 250000,
            'sumber' => 'servis',
        ]);

        // Pastikan tidak ada akun kas di jurnal onSelesai
        $kas = AkunCOA::where('kode', '110-01')->firstOrFail();
        $this->assertDatabaseMissing('jurnal_akuntansi', [
            'akun_coa_id' => $kas->id,
            'sumber' => 'servis',
        ]);
    }

    /**
     * Test 2: transisi ke diambil DITOLAK jika status_pembayaran belum lunas.
     */
    public function test_transisi_ke_diambil_ditolak_jika_belum_lunas(): void
    {
        $tiket = $this->buatTiket('qc', 150000);
        $svc = app(ServisService::class);
        $svc->updateStatus($tiket, 'selesai', $this->user, 'Selesai');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unit tidak dapat diserahkan/diambil sebelum pembayaran lunas');

        $svc->updateStatus($tiket->fresh(), 'diambil', $this->user, 'Diambil');
    }

    /**
     * Test 3: pelunasan via bayar() memposting jurnal Debit 110-01 (Kas) dan Kredit 120-01 (Piutang),
     * mengupdate status_pembayaran menjadi 'lunas'.
     */
    public function test_pelunasan_via_bayar_memposting_jurnal_kas_dan_piutang(): void
    {
        $tiket = $this->buatTiket('qc', 300000);
        $svc = app(ServisService::class);
        $svc->updateStatus($tiket, 'selesai', $this->user, 'Selesai');

        $tiketLunas = $svc->bayar($tiket->fresh(), 'tunai', $this->user, 'Pembayaran lunas kasir');

        $this->assertSame('lunas', $tiketLunas->status_pembayaran);
        $this->assertNotNull($tiketLunas->tanggal_bayar);
        $this->assertSame('tunai', $tiketLunas->metode_pembayaran);
        $this->assertNotNull($tiketLunas->no_jurnal_bayar);

        $kas = AkunCOA::where('kode', '110-01')->firstOrFail();
        $piutang = AkunCOA::where('kode', '120-01')->firstOrFail();

        // Cek jurnal pelunasan
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'no_jurnal' => $tiketLunas->no_jurnal_bayar,
            'akun_coa_id' => $kas->id,
            'debit' => 300000,
            'kredit' => 0,
        ]);

        $this->assertDatabaseHas('jurnal_akuntansi', [
            'no_jurnal' => $tiketLunas->no_jurnal_bayar,
            'akun_coa_id' => $piutang->id,
            'debit' => 0,
            'kredit' => 300000,
        ]);

        $this->assertDatabaseHas('servis_status_log', [
            'tiket_servis_id' => $tiket->id,
            'aksi' => 'pembayaran',
        ]);
    }

    /**
     * Test 4: setelah lunas, transisi ke diambil BERHASIL.
     */
    public function test_transisi_ke_diambil_berhasil_setelah_lunas(): void
    {
        $tiket = $this->buatTiket('qc', 100000);
        $svc = app(ServisService::class);
        $svc->updateStatus($tiket, 'selesai', $this->user, 'Selesai');
        $svc->bayar($tiket->fresh(), 'transfer', $this->user);

        $tiketDiambil = $svc->updateStatus($tiket->fresh(), 'diambil', $this->user, 'Diambil pemilik');

        $this->assertSame('diambil', $tiketDiambil->status);
        $this->assertNotNull($tiketDiambil->tanggal_diambil);
    }

    /**
     * Test 5: pemanggilan bayar() kedua pada tiket yang sudah lunas DITOLAK.
     */
    public function test_bayar_kedua_pada_tiket_lunas_ditolak(): void
    {
        $tiket = $this->buatTiket('qc', 100000);
        $svc = app(ServisService::class);
        $svc->updateStatus($tiket, 'selesai', $this->user, 'Selesai');
        $svc->bayar($tiket->fresh(), 'qris', $this->user);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Tiket servis sudah lunas');

        $svc->bayar($tiket->fresh(), 'qris', $this->user);
    }

    /**
     * Test 6: API POST /api/servis/{id}/bayar bekerja dan ter-scope cabang.
     */
    public function test_api_bayar_berhasil_dan_ter_scope_cabang(): void
    {
        $tiket = $this->buatTiket('qc', 175000);
        $svc = app(ServisService::class);
        $svc->updateStatus($tiket, 'selesai', $this->user, 'Selesai');

        // Request API sukses
        $response = $this->postJson("/api/servis/{$tiket->id}/bayar", [
            'metode_pembayaran' => 'kartu',
            'catatan' => 'Debit BCA',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'id' => $tiket->id,
                'status_pembayaran' => 'lunas',
                'metode_pembayaran' => 'kartu',
            ],
        ]);

        // Cek cross-cabang: user cabang lain tidak boleh bayar (404)
        $cabangLain = Cabang::create([
            'nama' => 'Cabang Lain',
            'kode' => 'CBG-LAIN',
            'is_pusat' => false,
            'is_aktif' => true,
        ]);
        session(['cabang_id' => $cabangLain->id]);

        $responseCross = $this->postJson("/api/servis/{$tiket->id}/bayar", [
            'metode_pembayaran' => 'tunai',
        ]);
        $responseCross->assertStatus(404);
    }
}
