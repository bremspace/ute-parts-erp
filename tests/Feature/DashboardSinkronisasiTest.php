<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Dashboard\Livewire\DashboardIndex;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * [B-01] Sinkronisasi / akurasi / realtime Dashboard backoffice (/app/dashboard).
 *
 * Membuktikan:
 *  1. Omzet (hari ini, tren, kategori, shift) menghitung transaksi marketplace 'lunas'.
 *  2. Widget Piutang Jatuh Tempo scoped cabang + migrasi backfill cabang_id idempoten.
 *  3. super-admin mendapat data Treasury (bukan Rp 0 karena variabel tak dikirim).
 *  4. Render aman utk user multi-role (semua variabel blade selalu terkirim).
 *  5. Definisi stok kritis selaras dgn ReorderService (jumlah < minimum AND minimum > 0).
 */
class DashboardSinkronisasiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Cabang, 2: Cabang} [user, cabangAktif, cabangLain] */
    private function authed(array $roles = ['super-admin']): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $cabangAktif = Cabang::create(['nama' => 'Pusat', 'kode' => 'CBG-01', 'is_active' => true]);
        $cabangLain = Cabang::create(['nama' => 'Cabang Dua', 'kode' => 'CBG-02', 'is_active' => true]);
        Gudang::create(['cabang_id' => $cabangAktif->id, 'nama' => 'Gudang 1', 'kode' => 'GDG-01', 'is_active' => true]);

        $user = User::create([
            'name' => 'Tester Dashboard', 'email' => 'dash@test.com',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        foreach ($roles as $role) {
            $user->assignRole($role);
        }
        $user->cabangs()->attach($cabangAktif->id);

        session(['cabang_id' => $cabangAktif->id]);
        $this->actingAs($user, 'web');

        return [$user, $cabangAktif, $cabangLain];
    }

    private function buatTrx(int $cabangId, string $status, float $total, ?int $kasirId = null): Transaksi
    {
        return Transaksi::create([
            'no_transaksi' => 'TRX-DASH-'.Str::random(8),
            'cabang_id' => $cabangId,
            'kasir_id' => $kasirId,
            'total_akhir' => $total,
            'status' => $status,
        ]);
    }

    private function buatPiutang(int $cabangId, Pelanggan $pelanggan, float $jumlah, int $hari): Piutang
    {
        return Piutang::create([
            'no_piutang' => 'PIU-DASH-'.Str::random(8),
            'pelanggan_id' => $pelanggan->id,
            'cabang_id' => $cabangId,
            'jumlah' => $jumlah,
            'jumlah_dibayar' => 0,
            'jatuh_tempo' => now()->addDays($hari),
            'status' => 'belum_lunas',
        ]);
    }

    // ===== P0-A: omzet menghitung transaksi marketplace 'lunas' =====

    public function test_omzet_hari_ini_menghitung_transaksi_lunas_marketplace(): void
    {
        [, $cabangAktif, $cabangLain] = $this->authed(['finance']);

        $trxLunas = $this->buatTrx($cabangAktif->id, 'lunas', 250000);     // marketplace (PaymentController)
        $this->buatTrx($cabangAktif->id, 'selesai', 100000);  // POS
        $this->buatTrx($cabangLain->id, 'selesai', 50000);     // cabang lain → tak boleh bocor ke cabang_aktif
        $this->buatTrx($cabangAktif->id, 'menunggu_pembayaran', 999000); // belum masuk omzet

        // Item utk trx lunas → wajib ikut Komposisi Omzet per Kategori
        TransaksiItem::create([
            'transaksi_id' => $trxLunas->id,
            'produk_id' => $this->produk('lcd-dash-omzet')->id,
            'jumlah' => 1,
            'harga_satuan' => 250000,
            'subtotal' => 250000,
        ]);

        $dashboard = new DashboardIndex;
        $omzet = $dashboard->omzetHariIni;

        $this->assertEquals(350000, (float) $omzet['cabang_aktif'], 'trx lunas cabang aktif wajib ikut omzet');
        $this->assertEquals(400000, (float) $omzet['semua_cabang'], 'semua cabang termasuk trx lunas');
        $this->assertSame(3, (int) $omzet['jumlah_transaksi'], 'hanya status selesai+lunas yang dihitung');

        // Tren 30 hari & komposisi kategori ikut menangkap status 'lunas'
        $tren = $dashboard->chartOmzet30Hari;
        $this->assertEquals(350000, array_sum($tren['values']), 'tren 30 hari scoped cabang harus 350.000');

        $kategori = $dashboard->chartKategori;
        $this->assertEquals(250000, array_sum($kategori['values']), 'komposisi kategori wajib ikut trx lunas');
        $this->assertContains('LCD', $kategori['labels']);
    }

    public function test_omzet_shift_kasir_juga_hitung_transaksi_lunas(): void
    {
        [$user, $cabangAktif] = $this->authed(['kasir']);

        $this->buatTrx($cabangAktif->id, 'selesai', 75000, $user->id);
        $this->buatTrx($cabangAktif->id, 'lunas', 125000, $user->id);

        $dashboard = new DashboardIndex;
        $shift = $dashboard->omzetShiftKasir;

        $this->assertEquals(200000, (float) $shift['omzet'], 'omzet shift harus mencakup trx lunas kasir tsb');
        $this->assertSame(2, (int) $shift['jumlah_transaksi']);
        $this->assertNotNull($user->id);
    }

    // ===== P0-B: piutang jatuh tempo scoped cabang =====

    public function test_piutang_jatuh_tempo_widget_scoped_cabang(): void
    {
        [, $cabangAktif, $cabangLain] = $this->authed(['finance']);

        $pelanggan = Pelanggan::create(['nama' => 'Pelanggan Dash', 'email' => 'pel-dash@test.com']);
        $piutangAktif = $this->buatPiutang($cabangAktif->id, $pelanggan, 1000000, 3);
        $this->buatPiutang($cabangLain->id, $pelanggan, 7000000, 2); // cabang lain → tak boleh muncul

        $dashboard = new DashboardIndex;
        $widget = $dashboard->piutangJatuhTempo;

        $this->assertSame(1, (int) $widget['total'], 'total harus scoped cabang aktif');
        $this->assertSame([$piutangAktif->id], $widget['items']->pluck('id')->all());

        // Konsistensi dgn Ringkasan Keuangan (juga scoped) dalam halaman yang sama
        $keuangan = $dashboard->ringkasanKeuangan;
        $this->assertEquals(1000000, (float) $keuangan['total_piutang']);
        $this->assertSame(0, (int) $keuangan['piutang_lewat']);
    }

    // ===== P0-B: migrasi backfill cabang_id idempoten =====

    public function test_migrasi_backfill_cabang_id_piutang_idempoten(): void
    {
        [, $cabangAktif, $cabangLain] = $this->authed(['finance']);

        $pelanggan = Pelanggan::create(['nama' => 'Pelanggan Backfill', 'email' => 'pel-bf@test.com']);

        // Baris legacy tanpa cabang (dari seeder lama) + baris yang sudah benar
        $null1 = $this->buatPiutang($cabangAktif->id, $pelanggan, 100000, 5);
        $null2 = $this->buatPiutang($cabangAktif->id, $pelanggan, 200000, 6);
        $sudahAda = $this->buatPiutang($cabangLain->id, $pelanggan, 300000, 7);

        // Paksa NULL persis seperti kondisi data lama
        \DB::table('piutang')->whereIn('id', [$null1->id, $null2->id])->update(['cabang_id' => null]);

        $migration = require database_path('migrations/2026_09_25_000100_backfill_cabang_id_piutang_utang.php');
        $migration->up();
        $migration->up(); // dijalankan berulang → tidak error, tidak dobel

        $defaultCabangId = Cabang::orderBy('id')->value('id');
        $this->assertEquals($cabangAktif->id, $defaultCabangId, 'default = cabang id terkecil');

        foreach ([$null1->id, $null2->id] as $id) {
            $this->assertEquals($defaultCabangId, \DB::table('piutang')->where('id', $id)->value('cabang_id'));
        }
        $this->assertEquals($cabangLain->id, \DB::table('piutang')->where('id', $sudahAda->id)->value('cabang_id'),
            'baris yang sudah punya cabang tidak boleh diubah');
        $this->assertSame(0, \DB::table('piutang')->whereNull('cabang_id')->count(), 'tidak ada sisa NULL');
    }

    // ===== P1-A: super-admin mendapat data treasury (bukan 0) =====

    public function test_super_admin_mendapat_data_treasury(): void
    {
        [$user, $cabangAktif] = $this->authed(['super-admin']);

        $akunKasId = (int) AkunCOA::where('kode', '110-01')->value('id');
        $this->assertGreaterThan(0, $akunKasId, 'COA 110-01 wajib ada');

        JurnalAkuntansi::create([
            'no_jurnal' => 'JRL-DASH-SA-1',
            'tanggal' => now(),
            'cabang_id' => $cabangAktif->id,
            'akun_coa_id' => $akunKasId,
            'sumber' => 'test',
            'deskripsi' => 'Kas dashboard',
            'debit' => 8000000,
            'kredit' => 0,
            'user_id' => $user->id,
        ]);

        $pelanggan = Pelanggan::create(['nama' => 'Pelanggan Treasury SA', 'email' => 'pel-sa@test.com']);
        $this->buatPiutang($cabangAktif->id, $pelanggan, 4321000, 10);

        $component = Livewire::test(DashboardIndex::class);
        $treasury = $component->viewData('treasuryProjection');

        $this->assertIsArray($treasury, 'treasuryProjection wajib dikirim utk super-admin');
        $this->assertEquals(8000000, (float) $treasury['saldo_kas'], 'saldo kas super-admin jangan 0');
        $this->assertEquals(4321000, (float) $treasury['piutang_30d']);
        $this->assertEquals(12321000, (float) $treasury['proyeksi_30d'], 'proyeksi 30 hari ≠ 0');

        // Kartu Treasury super-admin dirender dgn angka nyata (bukan Rp 0)
        $component->assertSee('12.321.000');
        $component->assertSee('Proyeksi Arus Kas 30 Hari');
    }

    // ===== P1-A: render aman utk user multi-role =====

    public function test_render_multi_role_tanpa_undefined_variable(): void
    {
        $this->authed(['kasir', 'finance', 'marketing', 'staff-gudang']);

        $component = Livewire::test(DashboardIndex::class);

        $this->assertIsArray($component->viewData('omzetShiftKasir'));
        $this->assertIsArray($component->viewData('ringkasanKeuangan'));
        $this->assertIsArray($component->viewData('marketInsight'));
        $this->assertIsArray($component->viewData('poPending'));
        $this->assertIsArray($component->viewData('treasuryProjection'));
    }

    // ===== P1-C: definisi stok kritis = ReorderService =====

    public function test_stok_kritis_selaras_dengan_reorder_service(): void
    {
        [, $cabangAktif] = $this->authed(['staff-gudang']);
        $gudang = Gudang::where('cabang_id', $cabangAktif->id)->firstOrFail();

        $dibuat = fn (string $slug, int $jumlah, int $minimum) => StokItem::create([
            'produk_id' => $this->produk($slug)->id,
            'gudang_id' => $gudang->id,
            'jumlah' => $jumlah,
            'jumlah_minimum' => $minimum,
        ]);

        $dibuat('kritis-1', 1, 5);    // kritis (1 < 5)
        $dibuat('kritis-2', 5, 5);    // TIDAK kritis — sama dgn minimum (definisi lama salah)
        $dibuat('kritis-3', 0, 0);    // TIDAK kritis — minimum 0 (ReorderService: jumlah_minimum > 0)

        $dashboard = new DashboardIndex;
        $stok = $dashboard->stokKritis;

        $this->assertSame(1, (int) $stok['total'], 'hanya jumlah < minimum AND minimum > 0 yang kritis');
        $this->assertCount(1, $stok['items']);
    }

    private function produk(string $slug): Produk
    {
        return Produk::create([
            'nama' => 'Produk '.Str::upper($slug), 'slug' => $slug,
            'kategori' => 'LCD', 'kondisi' => 'baru',
            'harga_beli' => 750000, 'harga_jual_retail' => 1100000,
        ]);
    }
}
