<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Jobs\DepresiasiAsetJob;
use App\Modules\Akunting\Livewire\AsetRegister;
use App\Modules\Akunting\Models\AsetTetap;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Services\DepresiasiService;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * [F3-3 / B-10h] Register Aset Tetap — route + view + aksi komponen.
 *
 * Sebelumnya `AsetRegister` tidak punya view dan tidak punya route sehingga fitur
 * tidak bisa diakses user sama sekali. Test ini mengunci:
 *   - route /app/akunting/aset (2xx utk akunting.view, 403 utk yang tidak punya)
 *   - view `modules.akunting.livewire.aset-register` benar-benar render
 *   - empty state informatif (bukan layar kosong)
 *   - aksi utama: tambah aset, jalankan depresiasi manual, disposal
 *   - guard permission & scoping cabang
 */
class AsetRegisterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cabang $cabang;

    private Cabang $cabangLain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create(['kode' => 'CBG-A10', 'nama' => 'Cabang Aset', 'is_active' => true]);
        $this->cabangLain = Cabang::create(['kode' => 'CBG-B10', 'nama' => 'Cabang Lain', 'is_active' => true]);

        foreach ([$this->cabang, $this->cabangLain] as $cb) {
            Gudang::create(['cabang_id' => $cb->id, 'nama' => 'Gudang '.$cb->kode, 'kode' => 'GDG-'.$cb->kode, 'is_active' => true]);
        }

        $this->user = User::create([
            'name' => 'Akuntan Aset',
            'email' => 'akuntan-aset@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->cabangs()->attach([$this->cabang->id, $this->cabangLain->id]);
        $this->user->assignRole('finance'); // akunting.view + create + edit + approve

        session(['cabang_id' => $this->cabang->id, 'cabang_nama' => $this->cabang->nama]);
        $this->actingAs($this->user, 'web');
    }

    /** User dengan akunting.view SAJA (tanpa create/approve) — role khusus test. */
    private function jadiLihatSaja(): User
    {
        $role = Role::findOrCreate('akunting-lihat-saja');
        $role->givePermissionTo('akunting.view');

        $user = User::create([
            'name' => 'Pembaca Aset',
            'email' => 'pembaca-aset@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->cabangs()->attach($this->cabang->id);
        $user->assignRole('akunting-lihat-saja');
        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($user, 'web');

        return $user;
    }

    private function buatAset(array $override = []): AsetTetap
    {
        return AsetTetap::create(array_merge([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Laptop Kasir 01',
            'kategori' => 'it',
            'harga_perolehan' => 12000000,
            'tanggal_perolehan' => now()->subMonths(6)->toDateString(),
            'umur_bulan' => 24,
            'metode' => 'garis_lurus',
            'status' => 'aktif',
            'user_id' => $this->user->id,
        ], $override));
    }

    // =============================================================
    // (1) Route
    // =============================================================

    public function test_route_aset_terdaftar_dengan_nama_dan_middleware(): void
    {
        $route = app('router')->getRoutes()->getByName('aset.register');

        $this->assertNotNull($route, 'Route dengan name aset.register harus terdaftar');
        $this->assertSame('app/akunting/aset', $route->uri());
        $this->assertSame(['GET', 'HEAD'], array_values(array_diff($route->methods(), ['OPTIONS'])));
        $this->assertContains('permission:akunting.view', $route->gatherMiddleware());
        $this->assertContains('auth', $route->gatherMiddleware());
    }

    public function test_halaman_aset_2xx_untuk_user_akunting_view(): void
    {
        $response = $this->get('/app/akunting/aset');

        $response->assertSuccessful();
        $response->assertSee('Aset Tetap &amp; Depresiasi', false);
        $response->assertSee('Jalankan Depresiasi');
        // Halaman bisa ditemukan dari sidebar (sub-menu Akunting, RBAC akunting.view)
        $response->assertSee('href="/app/akunting/aset"', false);
    }

    public function test_halaman_aset_403_bagi_user_tanpa_akunting_view(): void
    {
        $kasir = User::create([
            'name' => 'Kasir Tanpa Aset',
            'email' => 'kasir-tanpa-aset@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $kasir->assignRole('kasir');
        $kasir->cabangs()->attach($this->cabang->id);
        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($kasir, 'web');

        $this->get('/app/akunting/aset')->assertForbidden();
    }

    public function test_halaman_aset_403_bagi_tamu(): void
    {
        auth()->logout();
        session()->forget('cabang_id');

        $this->get('/app/akunting/aset')->assertRedirect();
    }

    // =============================================================
    // (2) View komponen
    // =============================================================

    public function test_komponen_render_dengan_view_yang_benar(): void
    {
        Livewire::test(AsetRegister::class)
            ->assertOk()
            ->assertViewIs('modules.akunting.livewire.aset-register')
            ->assertViewHas('asetRows')
            ->assertViewHas('rekap')
            ->assertViewHas('kategoriOptions')
            ->assertViewHas('filterStatusOptions')
            ->assertViewHas('periodeDepresiasi');
    }

    public function test_empty_state_informatif_bukan_layar_kosong(): void
    {
        Livewire::test(AsetRegister::class)
            ->assertOk()
            ->assertSee('Belum ada aset tetap di cabang ini')
            ->assertSee('Depresiasi berjalan otomatis tiap awal bulan.')
            ->assertSee('+ Tambah Aset Pertama');
    }

    public function test_tabel_menampilkan_aset_dengan_kategori_dan_nominal(): void
    {
        $this->buatAset(['nama' => 'Liftinverse', 'kategori' => 'kendaraan', 'harga_perolehan' => 750000000, 'umur_bulan' => 120]);

        Livewire::test(AsetRegister::class)
            ->assertOk()
            ->assertSee('Liftinverse')
            // kategori diterjemahkan ke label Bahasa Indonesia
            ->assertSee('Kendaraan')
            // format ribuan titik (ADR 0011): 750.000.000
            ->assertSee('750.000.000')
            // sisa buku = harga − akumulasi 0
            ->assertSee('Aktif');
    }

    public function test_rekap_mencerminkan_seluruh_aset_cabang(): void
    {
        $this->buatAset(['nama' => 'Aset A', 'harga_perolehan' => 1000000, 'akumulasi_depresiasi' => 250000]);
        $this->buatAset(['nama' => 'Aset B', 'harga_perolehan' => 3000000, 'akumulasi_depresiasi' => 500000]);

        Livewire::test(AsetRegister::class)
            ->assertOk()
            ->assertViewHas('rekap', fn (array $rekap) => $rekap['jumlah'] === 2
                && $rekap['harga'] === 4000000.0
                && $rekap['akumulasi'] === 750000.0
                && $rekap['sisa'] === 3250000.0);
    }

    public function test_aset_cabang_lain_tidak_bocor_ke_halaman(): void
    {
        $this->buatAset(['nama' => 'Aset Cabang Sendiri']);
        AsetTetap::create([
            'cabang_id' => $this->cabangLain->id,
            'nama' => 'Aset Cabang Lain',
            'kategori' => 'it',
            'harga_perolehan' => 9000000,
            'tanggal_perolehan' => now()->subYear()->toDateString(),
            'umur_bulan' => 36,
            'metode' => 'garis_lurus',
            'status' => 'aktif',
            'user_id' => $this->user->id,
        ]);

        Livewire::test(AsetRegister::class)
            ->assertOk()
            ->assertSee('Aset Cabang Sendiri')
            ->assertDontSee('Aset Cabang Lain')
            ->assertViewHas('rekap', fn (array $rekap) => $rekap['jumlah'] === 1);
    }

    public function test_tanpa_cabang_aktif_halaman_tetap_informatif(): void
    {
        session()->forget('cabang_id');

        Livewire::test(AsetRegister::class)
            ->assertOk()
            ->assertSee('Cabang aktif belum dipilih')
            ->assertViewHas('rekap', fn (array $rekap) => $rekap['jumlah'] === 0);
    }

    // =============================================================
    // (3) Aksi: tambah aset
    // =============================================================

    public function test_tambah_aset_berhasil(): void
    {
        Livewire::test(AsetRegister::class)
            ->assertOk()
            ->call('toggleForm')
            ->assertSet('showForm', true)
            ->set('form.nama', 'Rak Gudang B')
            ->set('form.kategori', 'peralatan')
            ->set('form.harga_perolehan', 4500000)
            ->set('form.tanggal_perolehan', now()->subMonth()->toDateString())
            ->set('form.umur_bulan', 48)
            ->set('form.catatan', 'Rak berat 2 ton')
            ->call('simpan')
            ->assertSet('showForm', false)
            ->assertHasNoErrors()
            ->assertDispatched('alert');

        $this->assertDatabaseHas('aset_tetap', [
            'cabang_id' => $this->cabang->id,
            'nama' => 'Rak Gudang B',
            'kategori' => 'peralatan',
            'umur_bulan' => 48,
            'status' => 'aktif',
            'metode' => 'garis_lurus',
            'user_id' => $this->user->id,
        ]);

        $aset = AsetTetap::where('nama', 'Rak Gudang B')->firstOrFail();
        $this->assertSame(4500000.0, (float) $aset->harga_perolehan);
    }

    public function test_harga_berformat_ribuan_diterima_sebagai_angka_bersih(): void
    {
        // [ADR 0011] Input nominal diformat ribuan di klien ("12.000.000") / paste apa adanya.
        Livewire::test(AsetRegister::class)
            ->call('toggleForm')
            ->set('form.nama', 'Monitor 24 inch')
            ->set('form.harga_perolehan', '12.000.000')
            ->set('form.tanggal_perolehan', now()->toDateString())
            ->set('form.umur_bulan', 36)
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame(12000000.0, (float) AsetTetap::where('nama', 'Monitor 24 inch')->firstOrFail()->harga_perolehan);
    }

    public function test_validasi_formulir_memakai_pesan_bahasa_indonesia(): void
    {
        Livewire::test(AsetRegister::class)
            ->call('toggleForm')
            ->set('form.nama', '')
            ->set('form.harga_perolehan', 'abc')
            ->set('form.umur_bulan', 0)
            ->call('simpan')
            ->assertHasErrors([
                'form.nama' => 'Nama aset wajib diisi.',
                'form.harga_perolehan' => 'Harga perolehan harus berupa angka.',
                'form.umur_bulan' => 'Umur depresiasi minimal 1 bulan.',
            ])
            ->assertSet('showForm', true);

        $this->assertDatabaseCount('aset_tetap', 0);
    }

    public function test_error_lama_dibersihkan_saat_formulir_dibuka_ulang(): void
    {
        Livewire::test(AsetRegister::class)
            ->call('toggleForm')
            ->set('form.nama', '')
            ->call('simpan')
            ->assertHasErrors('form.nama')
            ->call('toggleForm') // tutup
            ->call('toggleForm') // buka lagi
            ->assertHasNoErrors();
    }

    public function test_tambah_aset_ditolak_tanpa_akunting_create(): void
    {
        $this->jadiLihatSaja();

        Livewire::test(AsetRegister::class)
            ->call('simpan')
            ->assertSet('showForm', false)
            ->assertDispatched('alert', fn ($nama, $params) => ($params[0]['type'] ?? null) === 'error'
                && str_contains($params[0]['message'] ?? '', 'tidak punya izin menambah aset'));

        $this->assertDatabaseCount('aset_tetap', 0);
    }

    // =============================================================
    // (4) Aksi: jalankan depresiasi manual
    //
    // [B-15c] Aksi ini TIDAK sinkron lagi: request hanya dispatch
    // `DepresiasiAsetJob` (queue database) supaya request tidak menahan worker
    // LSAPI selama ±5 query × 20-200 aset. Test di bawah karena itu memverifikasi
    // (1) job itu diantre dengan scope cabang aktif, (2) job tersebut membentuk
    // jurnal + update aset, (3) idempoten saat dijalankan dua kali.
    // =============================================================

    /** Jalankan job persis seperti worker queue (handle + dependency aslinya). */
    private function jalankanJobDepresiasi(string $periode, int $cabangId): array
    {
        return (new DepresiasiAsetJob($periode, $cabangId))->handle(
            app(DepresiasiService::class),
            app(NotificationService::class)
        );
    }

    public function test_jalankan_depresiasi_menantrekan_job_bukan_menjalankan_sinkron(): void
    {
        Queue::fake();

        Livewire::test(AsetRegister::class)
            ->set('periodeDepresiasi', now()->format('Y-m'))
            ->call('jalankanDepresiasi')
            ->assertHasNoErrors()
            ->assertDispatched('alert', fn ($nama, $params) => ($params[0]['type'] ?? null) === 'success'
                && str_contains($params[0]['message'] ?? '', now()->format('Y-m')));

        Queue::assertPushed(DepresiasiAsetJob::class, function (DepresiasiAsetJob $job) {
            return $job->periode === now()->format('Y-m')
                && $job->cabangId === $this->cabang->id;
        });

        // Request TIDAK boleh memposting jurnal (itu kerja job, bukan request).
        $this->assertDatabaseCount('jurnal_akuntansi', 0);
    }

    public function test_jalankan_depresiasi_memposting_jurnal_dan_memperbarui_aset(): void
    {
        Queue::fake();
        $aset = $this->buatAset(['harga_perolehan' => 12000000, 'umur_bulan' => 24]);

        Livewire::test(AsetRegister::class)
            ->set('periodeDepresiasi', now()->format('Y-m'))
            ->call('jalankanDepresiasi');

        $this->jalankanJobDepresiasi(now()->format('Y-m'), $this->cabang->id);

        // 12jt / 24 = 500rb per bulan
        $aset->refresh();
        $this->assertSame(500000.0, (float) $aset->akumulasi_depresiasi);
        $this->assertSame(now()->format('Y-m'), $aset->depresiasi_terakhir_bulan);
        $this->assertSame('aktif', $aset->status);

        $jurnal = JurnalAkuntansi::where('cabang_id', $this->cabang->id)->where('sumber', 'depresiasi')->get();
        $this->assertCount(2, $jurnal, 'Depresiasi = 2 baris jurnal (beban + akumulasi)');
        $this->assertEqualsWithDelta(500000.0, (float) $jurnal->sum('debit'), 0.01);
        $this->assertEqualsWithDelta(500000.0, (float) $jurnal->sum('kredit'), 0.01);
    }

    public function test_depresiasi_ulang_periode_yang_sama_idempoten(): void
    {
        Queue::fake();
        $this->buatAset();

        $pertama = $this->jalankanJobDepresiasi(now()->format('Y-m'), $this->cabang->id);
        $kedua = $this->jalankanJobDepresiasi(now()->format('Y-m'), $this->cabang->id);

        $this->assertSame(1, $pertama['diproses']);
        $this->assertSame(0, $kedua['diproses'], 'Jalankan kedua tidak boleh menambah aset terproses');
        $this->assertSame(2, JurnalAkuntansi::where('sumber', 'depresiasi')->count(), 'Jurnal tidak boleh dobel');
    }

    public function test_periode_depresiasi_tidak_valid_ditolak_dengan_pesan_indonesia(): void
    {
        Livewire::test(AsetRegister::class)
            ->set('periodeDepresiasi', '2026-13')
            ->call('jalankanDepresiasi')
            ->assertHasErrors(['periodeDepresiasi' => fn ($rules, $messages) => in_array('Format periode harus YYYY-MM (contoh: 2026-09).', $messages)]);
    }

    public function test_jalankan_depresiasi_ditolak_tanpa_akunting_approve(): void
    {
        $this->buatAset();
        $this->jadiLihatSaja();

        Livewire::test(AsetRegister::class)
            ->set('periodeDepresiasi', now()->format('Y-m'))
            ->call('jalankanDepresiasi')
            ->assertDispatched('alert', fn ($nama, $params) => ($params[0]['type'] ?? null) === 'error'
                && str_contains($params[0]['message'] ?? '', 'tidak punya izin menjalankan depresiasi'));

        $this->assertDatabaseCount('jurnal_akuntansi', 0);
    }

    public function test_depresiasi_hanya_menyentuh_aset_cabang_aktif(): void
    {
        AsetTetap::create([
            'cabang_id' => $this->cabangLain->id,
            'nama' => 'Aset Cabang Lain',
            'kategori' => 'it',
            'harga_perolehan' => 6000000,
            'tanggal_perolehan' => now()->subMonths(6)->toDateString(),
            'umur_bulan' => 24,
            'metode' => 'garis_lurus',
            'status' => 'aktif',
            'user_id' => $this->user->id,
        ]);

        // [B-15c] Job wajib menerima scope cabang — kalau null, aset cabang lain ikut
        // disusut (jurnal bocor lintas cabang).
        $hasil = $this->jalankanJobDepresiasi(now()->format('Y-m'), $this->cabang->id);

        $this->assertSame(0, $hasil['diproses']);
        $this->assertDatabaseCount('jurnal_akuntansi', 0); // jurnal wajib scoped ke cabang aktif
        $this->assertDatabaseHas('aset_tetap', [
            'cabang_id' => $this->cabangLain->id,
            'nama' => 'Aset Cabang Lain',
            'akumulasi_depresiasi' => 0,
        ]);
    }

    // =============================================================
    // (5) Aksi: disposal
    // =============================================================

    public function test_konfirmasi_disposal_menyiapkan_ringkasan_lalu_menulis_jurnal(): void
    {
        $aset = $this->buatAset(['harga_perolehan' => 10000000, 'akumulasi_depresiasi' => 4000000]);

        Livewire::test(AsetRegister::class)
            ->call('konfirmasiDisposal', $aset->id)
            ->assertSet('showDisposalConfirm', true)
            ->assertSet('disposalNama', $aset->nama)
            ->assertSet('disposalHarga', 10000000.0)
            ->assertSet('disposalAkumulasi', 4000000.0)
            ->assertSet('disposalSisaBuku', 6000000.0)
            ->assertSee('Konfirmasi Disposal Aset')
            // 2 langkah: belum ada jurnal sebelum dikonfirmasi
            ->call('eksekusiDisposal')
            ->assertSet('showDisposalConfirm', false)
            ->assertDispatched('alert', fn ($nama, $params) => ($params[0]['type'] ?? null) === 'success'
                && str_contains($params[0]['message'] ?? '', 'JRL-DSP-'));

        $this->assertSame('disposal', $aset->fresh()->status);

        $jurnal = JurnalAkuntansi::where('sumber', 'disposal')->get();
        $this->assertGreaterThan(0, $jurnal->count());
        $this->assertEqualsWithDelta((float) $jurnal->sum('debit'), (float) $jurnal->sum('kredit'), 0.01);
        $this->assertEqualsWithDelta(10000000.0, (float) $jurnal->sum('kredit'), 0.01);
    }

    public function test_batal_disposal_menutup_dialog_tanpa_efek(): void
    {
        $aset = $this->buatAset();

        Livewire::test(AsetRegister::class)
            ->call('konfirmasiDisposal', $aset->id)
            ->assertSet('showDisposalConfirm', true)
            ->call('batalDisposal')
            ->assertSet('showDisposalConfirm', false)
            ->assertSet('disposalId', null)
            ->assertSet('disposalHarga', 0.0)
            ->assertSet('disposalSisaBuku', 0.0);

        $this->assertDatabaseCount('jurnal_akuntansi', 0);
        $this->assertSame('aktif', $aset->fresh()->status);
    }

    public function test_disposal_ditolak_tanpa_akunting_approve(): void
    {
        $aset = $this->buatAset();
        $this->jadiLihatSaja();

        Livewire::test(AsetRegister::class)
            ->call('konfirmasiDisposal', $aset->id)
            ->call('eksekusiDisposal')
            ->assertDispatched('alert', fn ($nama, $params) => ($params[0]['type'] ?? null) === 'error'
                && str_contains($params[0]['message'] ?? '', 'tidak punya izin melakukan disposal'));

        $this->assertSame('aktif', $aset->fresh()->status);
        $this->assertDatabaseCount('jurnal_akuntansi', 0);
    }

    public function test_disposal_aset_yang_sudah_disposal_ditolak(): void
    {
        $aset = $this->buatAset(['status' => 'disposal']);

        Livewire::test(AsetRegister::class)
            ->call('konfirmasiDisposal', $aset->id)
            ->assertSet('showDisposalConfirm', false)
            ->assertDispatched('alert', fn ($nama, $params) => str_contains($params[0]['message'] ?? '', 'sudah pernah didisposal'));
    }

    public function test_disposal_aset_cabang_lain_ditolak(): void
    {
        $asetLain = AsetTetap::create([
            'cabang_id' => $this->cabangLain->id,
            'nama' => 'Aset Milik Cabang Lain',
            'kategori' => 'it',
            'harga_perolehan' => 5000000,
            'tanggal_perolehan' => now()->subYear()->toDateString(),
            'umur_bulan' => 36,
            'metode' => 'garis_lurus',
            'status' => 'aktif',
            'user_id' => $this->user->id,
        ]);

        Livewire::test(AsetRegister::class)
            ->call('konfirmasiDisposal', $asetLain->id)
            ->assertSet('showDisposalConfirm', false)
            ->assertDispatched('alert', fn ($nama, $params) => str_contains($params[0]['message'] ?? '', 'tidak ditemukan di cabang aktif'));

        $this->assertDatabaseCount('jurnal_akuntansi', 0);
    }

    // =============================================================
    // (6) Filter status
    // =============================================================

    public function test_filter_status_menyaring_tanpa_mengubah_rekap(): void
    {
        $this->buatAset(['nama' => 'Aset Aktif', 'status' => 'aktif']);
        $this->buatAset(['nama' => 'Aset Habis', 'status' => 'fully_dep']);
        $this->buatAset(['nama' => 'Aset Buang', 'status' => 'disposal']);

        Livewire::test(AsetRegister::class)
            ->set('filterStatus', 'fully_dep')
            ->assertOk()
            ->assertSee('Aset Habis')
            ->assertDontSee('Aset Aktif')
            // rekap tetap seluruh aset cabang (tidak ikut berubah saat filter)
            ->assertViewHas('rekap', fn (array $rekap) => $rekap['jumlah'] === 3)
            // aset yang tidak cocok filter → ada jalan keluar, bukan layar kosong
            ->set('filterStatus', 'aktif')
            ->assertSee('Aset Aktif')
            ->assertDontSee('Aset Habis');
    }
}
