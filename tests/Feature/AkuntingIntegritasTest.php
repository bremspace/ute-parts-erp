<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Livewire\AkuntingDashboard;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Rbac\Models\Cabang;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * B-10a — 4 temuan P0 integritas akunting.
 *
 * P0-1 AR/AP berubah status tanpa jurnal (Livewire bahkan tidak pernah post jurnal)
 * P0-2 /app/akunting tanpa permission & bocor data antar cabang
 * P0-3 jurnal tanpa idempotensi DB atomic
 * P0-4 fail-open: status bisnis commit walau jurnal gagal
 */
class AkuntingIntegritasTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabangA;

    private Cabang $cabangB;

    private User $finance;

    private Pelanggan $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seedCoa();

        $this->cabangA = Cabang::create(['kode' => 'B10A', 'nama' => 'Cabang A', 'is_active' => true]);
        $this->cabangB = Cabang::create(['kode' => 'B10B', 'nama' => 'Cabang B', 'is_active' => true]);

        $this->pelanggan = Pelanggan::create(['nama' => 'Pelanggan B10A', 'telepon' => '0812000010']);

        $this->finance = $this->buatUserAkuntan('finance-b10a@test.com');
        $this->finance->cabangs()->attach([$this->cabangA->id, $this->cabangB->id]);
        session(['cabang_id' => $this->cabangA->id]);
        $this->actingAs($this->finance, 'web');
    }

    private function seedCoa(): void
    {
        foreach ([
            '110-01' => ['Kas', 'aset', 'kas', 'debit'],
            '120-01' => ['Piutang Usaha', 'aset', 'piutang_usaha', 'debit'],
            '210-01' => ['Utang Usaha', 'kewajiban', 'utang_usaha', 'kredit'],
        ] as $kode => [$nama, $tipe, $kelompok, $saldo]) {
            AkunCOA::create([
                'kode' => $kode,
                'nama' => $nama,
                'tipe' => $tipe,
                'kelompok' => $kelompok,
                'saldo_normal' => $saldo,
                'is_active' => true,
            ]);
        }
    }

    private function buatUserAkuntan(string $email): User
    {
        $user = User::create([
            'name' => 'Akuntan B10A',
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole('finance');

        return $user;
    }

    private function buatPiutang(int $cabangId, float $jumlah, float $dibayar = 0): Piutang
    {
        return Piutang::create([
            'no_piutang' => 'PTG-B10-'.(Piutang::count() + 1),
            'pelanggan_id' => $this->pelanggan->id,
            'cabang_id' => $cabangId,
            'jumlah' => $jumlah,
            'jumlah_dibayar' => $dibayar,
            'status' => $dibayar > 0 ? 'sebagian' : 'belum_lunas',
        ]);
    }

    private function buatUtang(int $cabangId, float $jumlah, float $dibayar = 0): Utang
    {
        return Utang::create([
            'no_utang' => 'UTG-B10-'.(Utang::count() + 1),
            'referensi_tipe' => 'pembelian',
            'referensi_id' => 1,
            'cabang_id' => $cabangId,
            'kreditor_nama' => 'Supplier B10A',
            'jumlah' => $jumlah,
            'jumlah_dibayar' => $dibayar,
            'status' => $dibayar > 0 ? 'sebagian' : 'belum_lunas',
        ]);
    }

    // =============================================================
    // (1) bayarPiutang / bayarUtang via Livewire → jurnal + subledger sinkron
    // =============================================================

    public function test_livewire_bayar_piutang_membuat_jurnal_dan_subledger_sinkron(): void
    {
        $piutang = $this->buatPiutang($this->cabangA->id, 500000);

        Livewire::test(AkuntingDashboard::class)
            ->call('openBayarPiutangModal', $piutang->id)
            ->assertSet('showBayarPiutangModal', true)
            ->set('bayarPiutangJumlah', 200000)
            ->call('bayarPiutang')
            ->assertSet('showBayarPiutangModal', false)
            ->assertDispatched('alert');

        // Subledger ikut berubah
        $piutang->refresh();
        $this->assertSame(200000.0, (float) $piutang->jumlah_dibayar);
        $this->assertSame('sebagian', $piutang->status);

        // Jurnal WAJIB ada: Kas debit 200.000 / Piutang kredit 200.000
        $this->assertSame(2, JurnalAkuntansi::count(), 'Livewire wajib mem-post jurnal (regresi P0-1)');

        $kas = AkunCOA::where('kode', '110-01')->first();
        $piutangAkun = AkunCOA::where('kode', '120-01')->first();

        $this->assertDatabaseHas('jurnal_akuntansi', [
            'akun_coa_id' => $kas->id,
            'debit' => 200000,
            'kredit' => 0,
            'cabang_id' => $this->cabangA->id,
        ]);
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'akun_coa_id' => $piutangAkun->id,
            'debit' => 0,
            'kredit' => 200000,
            'cabang_id' => $this->cabangA->id,
        ]);

        // referensinya menunjuk baris pembayaran untuk rekonsiliasi
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'referensi_tipe' => Piutang::class,
            'referensi_id' => $piutang->id,
        ]);
        $this->assertDatabaseHas('jurnal_header', [
            'no_jurnal' => JurnalAkuntansi::value('no_jurnal'),
            'total_debit' => 200000,
            'total_kredit' => 200000,
        ]);
    }

    public function test_livewire_bayar_utang_membuat_jurnal_dan_subledger_sinkron(): void
    {
        $utang = $this->buatUtang($this->cabangA->id, 800000);

        Livewire::test(AkuntingDashboard::class)
            ->call('openBayarUtangModal', $utang->id)
            ->set('bayarUtangJumlah', 300000)
            ->call('bayarUtang')
            ->assertSet('showBayarUtangModal', false);

        $utang->refresh();
        $this->assertSame(300000.0, (float) $utang->jumlah_dibayar);
        $this->assertSame('sebagian', $utang->status);

        $this->assertSame(2, JurnalAkuntansi::count());

        $kas = AkunCOA::where('kode', '110-01')->first();
        $utangAkun = AkunCOA::where('kode', '210-01')->first();

        $this->assertDatabaseHas('jurnal_akuntansi', [
            'akun_coa_id' => $utangAkun->id,
            'debit' => 300000,
            'kredit' => 0,
        ]);
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'akun_coa_id' => $kas->id,
            'debit' => 0,
            'kredit' => 300000,
        ]);
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'referensi_tipe' => Utang::class,
            'referensi_id' => $utang->id,
        ]);
    }

    public function test_api_bayar_piutang_menolak_pembayaran_melebihi_sisa_tanpa_menulis_jurnal(): void
    {
        $piutang = $this->buatPiutang($this->cabangA->id, 100000);

        $this->postJson("/api/akunting/piutang/{$piutang->id}/bayar", ['jumlah' => 150000])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pembayaran melebihi sisa piutang');

        $this->assertSame(0.0, (float) $piutang->fresh()->jumlah_dibayar);
        $this->assertSame(0, JurnalAkuntansi::count());
    }

    // =============================================================
    // (2) Jurnal gagal → subledger TIDAK berubah (rollback)
    // =============================================================

    public function test_jurnal_gagal_tidak_mengubah_subledger_piutang(): void
    {
        $piutang = $this->buatPiutang($this->cabangA->id, 500000);

        // Hapus akun 120-01 → JurnalService::post() gagal, subledger HARUS rollback
        AkunCOA::where('kode', '120-01')->delete();

        Livewire::test(AkuntingDashboard::class)
            ->call('openBayarPiutangModal', $piutang->id)
            ->set('bayarPiutangJumlah', 200000)
            ->call('bayarPiutang')
            ->assertSet('showBayarPiutangModal', true, 'Modal tidak boleh tertutup saat jurnal gagal');

        $piutang->refresh();
        $this->assertSame(0.0, (float) $piutang->jumlah_dibayar, 'Subledger wajib rollback saat jurnal gagal');
        $this->assertSame('belum_lunas', $piutang->status);
        $this->assertSame(0, JurnalAkuntansi::count());
        $this->assertSame(0, JurnalHeader::count());
    }

    public function test_jurnal_gagal_tidak_mengubah_subledger_utang(): void
    {
        $utang = $this->buatUtang($this->cabangA->id, 500000);

        // Hapus akun Kas (110-01) → jurnal pembayaran utang gagal
        AkunCOA::where('kode', '110-01')->delete();

        $this->postJson("/api/akunting/utang/{$utang->id}/bayar", ['jumlah' => 250000])
            ->assertStatus(422);

        $utang->refresh();
        $this->assertSame(0.0, (float) $utang->jumlah_dibayar, 'Subledger utang wajib rollback');
        $this->assertSame('belum_lunas', $utang->status);
        $this->assertSame(0, JurnalAkuntansi::count());
    }

    public function test_pembayaran_ganda_dengan_kunci_sama_ditolak(): void
    {
        $piutang = $this->buatPiutang($this->cabangA->id, 500000);

        $headers = ['Idempotency-Key' => 'bayar-ar-kunci-1'];

        $this->withHeaders($headers)
            ->postJson("/api/akunting/piutang/{$piutang->id}/bayar", ['jumlah' => 200000])
            ->assertSuccessful();

        // Retry / double submit dengan kunci sama → tidak boleh menambah jurnal
        $this->withHeaders($headers)
            ->postJson("/api/akunting/piutang/{$piutang->id}/bayar", ['jumlah' => 200000])
            ->assertStatus(422);

        $this->assertSame(2, JurnalAkuntansi::count(), 'Tidak boleh ada jurnal ganda');
        $this->assertSame(1, JurnalHeader::count());
        $this->assertSame(200000.0, (float) $piutang->fresh()->jumlah_dibayar);
    }

    // =============================================================
    // (3) User tanpa permission → 403
    // =============================================================

    public function test_halaman_akunting_403_bagi_user_tanpa_akunting_view(): void
    {
        $kasir = User::create([
            'name' => 'Kasir B10A',
            'email' => 'kasir-b10a@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $kasir->assignRole('kasir');
        $kasir->cabangs()->attach($this->cabangA->id);
        session(['cabang_id' => $this->cabangA->id]);
        $this->actingAs($kasir, 'web');

        $this->get('/app/akunting')->assertForbidden();
    }

    public function test_mutasi_akunting_403_bagi_user_tanpa_permission(): void
    {
        $staf = User::create([
            'name' => 'Staf B10A',
            'email' => 'staf-b10a@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        // [P0-2] permission yang dipakai WAJIB yang sudah ada di seeder
        $staf->assignRole('kasir');
        $staf->cabangs()->attach($this->cabangA->id);
        session(['cabang_id' => $this->cabangA->id]);
        $this->actingAs($staf, 'web');

        $this->get('/app/akunting')->assertForbidden();

        // Guard server-side di komponen Livewire (UI-only guard tidak cukup)
        Livewire::test(AkuntingDashboard::class)->call('simpanCoa')->assertForbidden();
        Livewire::test(AkuntingDashboard::class)->call('simpanJurnalManual')->assertForbidden();
        Livewire::test(AkuntingDashboard::class)->call('bayarPiutang')->assertForbidden();
        Livewire::test(AkuntingDashboard::class)->call('bayarUtang')->assertForbidden();
    }

    // =============================================================
    // (4) Query AR/AP tidak membocorkan cabang lain
    // =============================================================

    public function test_daftar_ar_ap_tidak_membocorkan_cabang_lain(): void
    {
        $milikA = $this->buatPiutang($this->cabangA->id, 100000);
        $this->buatPiutang($this->cabangB->id, 999000);

        $utangA = $this->buatUtang($this->cabangA->id, 100000);
        $this->buatUtang($this->cabangB->id, 999000);

        $response = $this->getJson('/api/akunting/piutang')->assertSuccessful();
        $ids = collect($response->json('data.data'))->pluck('id')->all();
        $this->assertContains($milikA->id, $ids);
        $this->assertCount(1, $ids, 'Piutang cabang lain tidak boleh bocor');

        $response = $this->getJson('/api/akunting/utang')->assertSuccessful();
        $ids = collect($response->json('data.data'))->pluck('id')->all();
        $this->assertContains($utangA->id, $ids);
        $this->assertCount(1, $ids, 'Utang cabang lain tidak boleh bocor');
    }

    public function test_livewire_daftar_ar_ap_scoped_cabang(): void
    {
        $milikA = $this->buatPiutang($this->cabangA->id, 100000);
        $this->buatPiutang($this->cabangB->id, 999000);

        $utangA = $this->buatUtang($this->cabangA->id, 100000);
        $this->buatUtang($this->cabangB->id, 999000);

        $component = Livewire::test(AkuntingDashboard::class);

        $this->assertSame([$milikA->id], $component->get('piutangs')->pluck('id')->all());
        $this->assertSame([$utangA->id], $component->get('utangs')->pluck('id')->all());
    }

    public function test_laporan_dan_buku_besar_tidak_membocorkan_cabang_lain(): void
    {
        $kas = AkunCOA::where('kode', '110-01')->first();

        JurnalAkuntansi::create([
            'no_jurnal' => 'JRL-B10-CAB-A', 'tanggal' => now(), 'cabang_id' => $this->cabangA->id,
            'akun_coa_id' => $kas->id, 'sumber' => 'manual', 'deskripsi' => 'Jurnal Rahasia Cabang A',
            'debit' => 100000, 'kredit' => 0,
        ]);
        JurnalAkuntansi::create([
            'no_jurnal' => 'JRL-B10-CAB-B', 'tanggal' => now(), 'cabang_id' => $this->cabangB->id,
            'akun_coa_id' => $kas->id, 'sumber' => 'manual', 'deskripsi' => 'Jurnal Rahasia Cabang B',
            'debit' => 700000, 'kredit' => 0,
        ]);

        // Buku besar tanpa filter cabang = kebocoran total
        $entries = $this->getJson("/api/akunting/buku-besar/{$kas->id}")
            ->assertSuccessful()
            ->json('data.entries');

        $noJurnalTerlihat = collect($entries)->pluck('no_jurnal')->all();
        $this->assertContains('JRL-B10-CAB-A', $noJurnalTerlihat);
        $this->assertNotContains('JRL-B10-CAB-B', $noJurnalTerlihat, 'Buku besar bocor jurnal cabang lain');
    }

    public function test_membayar_ar_utang_kabang_lain_ditolak(): void
    {
        $piutangLain = $this->buatPiutang($this->cabangB->id, 100000);
        $utangLain = $this->buatUtang($this->cabangB->id, 100000);

        $this->postJson("/api/akunting/piutang/{$piutangLain->id}/bayar", ['jumlah' => 10000])
            ->assertStatus(404);

        $this->postJson("/api/akunting/utang/{$utangLain->id}/bayar", ['jumlah' => 10000])
            ->assertStatus(404);

        $this->assertSame(0.0, (float) $piutangLain->fresh()->jumlah_dibayar);
        $this->assertSame(0.0, (float) $utangLain->fresh()->jumlah_dibayar);
        $this->assertSame(0, JurnalAkuntansi::count());
    }

    // =============================================================
    // (5) post() dua kali dengan no_jurnal sama → tidak ada jurnal ganda
    // =============================================================

    public function test_post_no_jurnal_sama_tidak_menghasilkan_jurnal_ganda(): void
    {
        $lines = [
            ['akun_kode' => '110-01', 'debit' => 50000, 'kredit' => 0],
            ['akun_kode' => '210-01', 'debit' => 0, 'kredit' => 50000],
        ];

        $service = app(JurnalService::class);
        $service->post('JRL-B10-DUP-1', now(), 'manual', $lines, 'Doubled', $this->cabangA->id, $this->finance->id);

        try {
            $service->post('JRL-B10-DUP-1', now(), 'manual', $lines, 'Doubled', $this->cabangA->id, $this->finance->id);
            $this->fail('Posting ulang no_jurnal yang sama harus ditolak');
        } catch (\Exception $e) {
            $this->assertStringContainsString('sudah pernah diposting', $e->getMessage());
        }

        $this->assertSame(2, JurnalAkuntansi::where('no_jurnal', 'JRL-B10-DUP-1')->count());
        $this->assertSame(1, JurnalHeader::where('no_jurnal', 'JRL-B10-DUP-1')->count());
    }

    public function test_generate_no_jurnal_maju_setelah_jurnal_diposting_dan_terpisah_per_cabang(): void
    {
        $service = app(JurnalService::class);

        $lines = [
            ['akun_kode' => '110-01', 'debit' => 50000, 'kredit' => 0],
            ['akun_kode' => '210-01', 'debit' => 0, 'kredit' => 50000],
        ];

        $a1 = $service->generateNoJurnal('pembayaran', $this->cabangA->id);
        $b1 = $service->generateNoJurnal('pembayaran', $this->cabangB->id);

        $this->assertStringContainsString("-{$this->cabangA->id}-", $a1, 'LIKE wajib include cabangId');
        $this->assertStringContainsString("-{$this->cabangB->id}-", $b1, 'Counter terpisah per cabang');
        $this->assertNotSame($a1, $b1);

        $service->post($a1, now(), 'manual', $lines, 'A', $this->cabangA->id, $this->finance->id);

        // Setelah jurnal tercatat, counter cabang A harus maju (tidak bentrok)
        $this->assertNotSame($a1, $service->generateNoJurnal('pembayaran', $this->cabangA->id));
        // Cabang B tetap pada nomornya sendiri
        $this->assertSame($b1, $service->generateNoJurnal('pembayaran', $this->cabangB->id));
    }

    public function test_backfill_membuat_header_dari_jurnal_existing(): void
    {
        $kas = AkunCOA::where('kode', '110-01')->first();
        $utang = AkunCOA::where('kode', '210-01')->first();

        foreach ([
            ['no_jurnal' => 'JRL-B10-LEGACY', 'akun' => $kas, 'debit' => 1000, 'kredit' => 0],
            ['no_jurnal' => 'JRL-B10-LEGACY', 'akun' => $utang, 'debit' => 0, 'kredit' => 1000],
        ] as $baris) {
            JurnalAkuntansi::create([
                'no_jurnal' => $baris['no_jurnal'],
                'tanggal' => now(),
                'cabang_id' => $this->cabangA->id,
                'akun_coa_id' => $baris['akun']->id,
                'sumber' => 'manual',
                'deskripsi' => 'Legacy',
                'debit' => $baris['debit'],
                'kredit' => $baris['kredit'],
            ]);
        }

        // Backfill idempoten & boleh dijalankan ulang
        $migration = require database_path('migrations/2026_09_25_030000_create_jurnal_header_table.php');
        $backfill = (new \ReflectionClass($migration))->getMethod('backfill');
        $backfill->invoke($migration);
        $backfill->invoke($migration);

        $this->assertSame(
            1,
            JurnalHeader::where('no_jurnal', 'JRL-B10-LEGACY')->count(),
            'Satu entri = satu header (group by cabang_id + no_jurnal)'
        );
        $this->assertSame(2, (int) JurnalHeader::where('no_jurnal', 'JRL-B10-LEGACY')->value('jumlah_baris'));
    }
}
