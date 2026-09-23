<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Livewire\RiwayatAktivitas;
use App\Modules\Rbac\Models\AktivitasLog;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\Supplier;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * [F1-4] Audit Trail — spatie/laravel-activitylog.
 *
 * Cakupan: log create/update/delete 6+ model kritis dgn before/after JSON,
 * causer (auth user / "sistem"), filter riwayat, dan scoping cabang.
 * Catatan: method wajib prefix test_ (PHPUnit 12 tidak deteksi @test docblock).
 */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Gudang $gudang;

    private Produk $produk;

    private Pelanggan $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create([
            'kode' => 'UTPAKT', 'nama' => 'Cabang Aktivitas', 'alamat' => 'Jl. Test',
            'telepon' => '08123456789', 'is_active' => true,
        ]);
        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id, 'nama' => 'Gudang Aktivitas',
            'kode' => 'GDG-AKT', 'is_active' => true,
        ]);
        $this->produk = Produk::create([
            'nama' => 'LCD Aktivitas', 'slug' => 'lcd-aktivitas', 'kategori' => 'LCD',
            'kondisi' => 'baru', 'harga_beli' => 500000, 'harga_jual_retail' => 750000,
        ]);
        $this->pelanggan = Pelanggan::create([
            'nama' => 'Pelanggan Audit', 'telepon' => '0899'.Str::random(8),
            'password' => Hash::make('rahasia'), 'is_reseller' => false,
        ]);

        AkunCOA::firstOrCreate(['kode' => '110-01'], ['nama' => 'Kas', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '410-01'], ['nama' => 'Pendapatan Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit']);

        session(['cabang_id' => $this->cabang->id]);
    }

    private function userAuthed(string $role = 'super-admin'): User
    {
        $user = User::create([
            'name' => 'Tester '.Str::random(5),
            'email' => 'tester'.Str::random(6).'@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        $this->actingAs($user, 'web');

        return $user;
    }

    private function buatTransaksi(): Transaksi
    {
        return Transaksi::create([
            'no_transaksi' => 'TRX-AKT-'.Str::random(5),
            'cabang_id' => $this->cabang->id,
            'subtotal' => 100000,
            'diskon_persen' => 0,
            'diskon_nominal' => 0,
            'total_akhir' => 100000,
            'status' => 'selesai',
        ]);
    }

    // ===== 1. LOG 6 MODEL: create / update / delete + before/after =====

    public function test_transaksi_terlog_create_update_delete_dengan_before_after(): void
    {
        $user = $this->userAuthed();

        $transaksi = $this->buatTransaksi();

        $created = AktivitasLog::where('subject_type', Transaksi::class)
            ->where('subject_id', $transaksi->id)->where('event', 'created')->first();
        $this->assertNotNull($created, 'Create Transaksi wajib ter-log');
        $this->assertEquals('Transaksi dibuat', $created->description);
        // before/after JSON: create → attributes terisi, old kosong/null
        $this->assertEquals(100000, (float) $created->attribute_changes->get('attributes')['total_akhir']);
        // causer = auth user
        $this->assertEquals($user->id, (int) $created->causer_id);
        $this->assertEquals($user->name, $created->causer_nama);
        // cabang_id di properties + kolom
        $this->assertEquals($this->cabang->id, (int) $created->getProperty('cabang_id'));
        $this->assertEquals($this->cabang->id, (int) $created->cabang_id);

        $transaksi->update(['total_akhir' => 150000, 'status' => 'dibatalkan']);
        $updated = AktivitasLog::where('subject_type', Transaksi::class)
            ->where('subject_id', $transaksi->id)->where('event', 'updated')->first();
        $this->assertNotNull($updated, 'Update Transaksi wajib ter-log');
        $this->assertEquals('Transaksi diperbarui', $updated->description);
        // before (old) + after (attributes)
        $this->assertEquals(100000, (float) $updated->attribute_changes->get('old')['total_akhir']);
        $this->assertEquals(150000, (float) $updated->attribute_changes->get('attributes')['total_akhir']);
        $this->assertEquals('selesai', $updated->attribute_changes->get('old')['status']);
        $this->assertEquals('dibatalkan', $updated->attribute_changes->get('attributes')['status']);

        $transaksi->delete();
        $deleted = AktivitasLog::where('subject_type', Transaksi::class)
            ->where('subject_id', $transaksi->id)->where('event', 'deleted')->first();
        $this->assertNotNull($deleted, 'Delete Transaksi wajib ter-log');
        $this->assertEquals('Transaksi dihapus', $deleted->description);
        $this->assertNotNull($deleted->attribute_changes->get('old'));
        $this->assertNull($deleted->attribute_changes->get('attributes'));
        $this->assertEquals($user->id, (int) $deleted->causer_id);

        $this->assertEquals(3, AktivitasLog::where('subject_type', Transaksi::class)
            ->where('subject_id', $transaksi->id)->count());
    }

    public function test_jurnal_stok_po_piutang_utang_produk_terlog_create_update_delete(): void
    {
        $user = $this->userAuthed();

        $jurnal = JurnalAkuntansi::create([
            'no_jurnal' => 'JRL-AKT-0001', 'tanggal' => now()->toDateString(),
            'cabang_id' => $this->cabang->id, 'akun_coa_id' => AkunCOA::where('kode', '110-01')->first()->id,
            'sumber' => 'manual', 'deskripsi' => 'Jurnal audit', 'debit' => 1000, 'kredit' => 0,
        ]);
        $stok = StokItem::create([
            'produk_id' => $this->produk->id, 'gudang_id' => $this->gudang->id,
            'jumlah' => 10, 'jumlah_minimum' => 2,
        ]);
        $supplier = Supplier::create(['nama' => 'Supplier Audit']);
        $po = PurchaseOrder::create([
            'no_po' => 'PO-AKT-'.Str::random(4), 'supplier_id' => $supplier->id,
            'gudang_tujuan_id' => $this->gudang->id, 'status' => 'draft',
            'metode_bayar' => 'kredit', 'total' => 5000000, 'total_dibayar' => 0,
        ]);
        $piutang = Piutang::create([
            'no_piutang' => 'AR-AKT-0001', 'cabang_id' => $this->cabang->id,
            'pelanggan_id' => $this->pelanggan->id,
            'jumlah' => 200000, 'jumlah_dibayar' => 0,
            'jatuh_tempo' => now()->addDays(30)->toDateString(), 'status' => 'belum_lunas',
        ]);
        $utang = Utang::create([
            'no_utang' => 'AP-AKT-0001', 'cabang_id' => $this->cabang->id,
            'referensi_tipe' => 'pembelian', 'referensi_id' => 1,
            'pelanggan_id' => $this->pelanggan->id,
            'kreditor_nama' => 'Supplier Audit', 'jumlah' => 300000, 'jumlah_dibayar' => 0,
            'jatuh_tempo' => now()->addDays(30)->toDateString(), 'status' => 'belum_lunas',
        ]);

        $kasus = [
            // model, update atribut, kunci cabang utk verifikasi properties
            [$jurnal, ['deskripsi' => 'Jurnal audit diperbarui'], 'Jurnal', $this->cabang->id],
            [$stok, ['jumlah' => 7], 'StokItem', $this->cabang->id], // cabang via gudang
            [$po, ['status' => 'dikirim'], 'Purchase Order', $this->cabang->id], // cabang via gudang_tujuan
            [$piutang, ['jumlah_dibayar' => 50000], 'Piutang', $this->cabang->id],
            [$utang, ['jumlah_dibayar' => 100000], 'Utang', $this->cabang->id],
            [$this->produk, ['harga_jual_retail' => 800000], 'Produk', null], // master global
        ];

        foreach ($kasus as [$model, $ubah, $nama, $cabangId]) {
            $tipe = $model::class;
            $id = $model->id;

            // CREATE (model sudah dibuat di atas)
            $this->assertNotNull(
                AktivitasLog::where('subject_type', $tipe)->where('subject_id', $id)->where('event', 'created')->first(),
                "Create {$tipe} wajib ter-log"
            );

            // UPDATE + before/after
            $model->update($ubah);
            $updated = AktivitasLog::where('subject_type', $tipe)->where('subject_id', $id)->where('event', 'updated')->first();
            $this->assertNotNull($updated, "Update {$tipe} wajib ter-log");
            $this->assertEquals("{$nama} diperbarui", $updated->description);
            foreach ($ubah as $kolom => $nilai) {
                $this->assertArrayHasKey($kolom, $updated->attribute_changes->get('attributes'), "after.{$kolom} wajib ada ({$tipe})");
                $this->assertArrayHasKey($kolom, $updated->attribute_changes->get('old'), "before.{$kolom} wajib ada ({$tipe})");
            }

            // cabang_id tersimpan utk filter
            if ($cabangId !== null) {
                $this->assertEquals($cabangId, (int) $updated->getProperty('cabang_id'), "properties.cabang_id {$tipe}");
                $this->assertEquals($cabangId, (int) $updated->cabang_id, "kolom cabang_id {$tipe}");
            } else {
                $this->assertNull($updated->cabang_id, "{$tipe} global tanpa cabang");
            }

            // causer
            $this->assertEquals($user->id, (int) $updated->causer_id, "causer {$tipe}");

            // DELETE
            $model->delete();
            $this->assertNotNull(
                AktivitasLog::where('subject_type', $tipe)->where('subject_id', $id)->where('event', 'deleted')->first(),
                "Delete {$tipe} wajib ter-log"
            );
            $this->assertEquals(3, AktivitasLog::where('subject_type', $tipe)->where('subject_id', $id)->count(), "3 event {$tipe}");
        }
    }

    // ===== 2. CAUSER =====

    public function test_causer_sistem_saat_tanpa_login_job_atau_seed(): void
    {
        $this->assertNull(auth()->user());

        $transaksi = $this->buatTransaksi();
        $aktivitas = AktivitasLog::where('subject_type', Transaksi::class)
            ->where('subject_id', $transaksi->id)->where('event', 'created')->first();

        $this->assertNotNull($aktivitas);
        $this->assertNull($aktivitas->causer_id, 'Tanpa auth → causer null (job/seed)');
        $this->assertEquals('sistem', $aktivitas->causer_nama, 'Fallback causer = sistem');
    }

    // ===== 3. UI + FILTER =====

    public function test_ui_riwayat_menampilkan_log_dan_filter_aksi_tanggal_pengguna(): void
    {
        $user = $this->userAuthed('super-admin');
        $transaksi = $this->buatTransaksi();
        $transaksi->update(['total_akhir' => 123456]);

        $aksi = AktivitasLog::where('subject_type', Transaksi::class)
            ->where('subject_id', $transaksi->id)->pluck('event')->all();
        sort($aksi);
        $this->assertEquals(['created', 'updated'], $aksi);

        $component = Livewire::test(RiwayatAktivitas::class, ['tipe' => 'transaksi', 'entityId' => $transaksi->id]);
        $component->assertSee($transaksi->no_transaksi)
            ->assertSee('Dibuat')
            ->assertSee('Diperbarui')
            ->assertSee($user->name)
            // format ribuan titik (ADR 0011)
            ->assertSee('123.456');
        $this->assertEquals(2, $component->viewData('aktivitas')->total());

        // Filter aksi
        $component->set('filterAksi', 'created');
        $this->assertEquals(1, $component->viewData('aktivitas')->total());
        $this->assertEquals('created', $component->viewData('aktivitas')->first()->event);

        $component->set('filterAksi', 'deleted');
        $this->assertEquals(0, $component->viewData('aktivitas')->total());
        $component->assertSee('Tidak ada aktivitas tercatat');

        // Filter tanggal: rentang di masa depan → kosong; rentang hari ini → penuh
        $component->set('filterAksi', '')->set('filterDari', now()->addDay()->toDateString());
        $this->assertEquals(0, $component->viewData('aktivitas')->total());
        $component->set('filterDari', now()->subDay()->toDateString())
            ->set('filterSampai', now()->addDay()->toDateString());
        $this->assertEquals(2, $component->viewData('aktivitas')->total());

        // Filter pengguna
        $component->set('filterUser', $user->name);
        $this->assertEquals(2, $component->viewData('aktivitas')->total());
        $component->set('filterUser', 'NamaUserYangTidakAda');
        $this->assertEquals(0, $component->viewData('aktivitas')->total());

        // Reset filter → log tampil lagi
        $component->call('resetFilter');
        $this->assertEquals(2, $component->viewData('aktivitas')->total());
    }

    // ===== 4. RBAC + SCOPE CABANG =====

    public function test_ui_riwayat_tanpa_permission_lihat_audit_log_ditolak(): void
    {
        $this->userAuthed('teknisi'); // teknisi tidak punya lihat-audit-log
        $transaksi = $this->buatTransaksi();

        Livewire::test(RiwayatAktivitas::class, ['tipe' => 'transaksi', 'entityId' => $transaksi->id])
            ->assertSee('tidak memiliki izin');
    }

    public function test_scope_cabang_user_lain_tidak_lihat_log_entitas_cabang_lain(): void
    {
        $this->userAuthed('admin-toko'); // punya lihat-audit-log

        // Entitas di cabang LAIN
        $cabangLain = Cabang::create([
            'kode' => 'UTPLAN', 'nama' => 'Cabang Lain', 'alamat' => 'Jl. Lain',
            'telepon' => '08123456789', 'is_active' => true,
        ]);
        $gudangLain = Gudang::create(['cabang_id' => $cabangLain->id, 'nama' => 'Gudang Lain', 'kode' => 'GDG-LAN', 'is_active' => true]);
        $stokLain = StokItem::create([
            'produk_id' => $this->produk->id, 'gudang_id' => $gudangLain->id,
            'jumlah' => 3, 'jumlah_minimum' => 1,
        ]);
        $stokSendiri = StokItem::create([
            'produk_id' => $this->produk->id, 'gudang_id' => $this->gudang->id,
            'jumlah' => 5, 'jumlah_minimum' => 1,
        ]);

        // Sesi aktif = cabang sendiri
        session(['cabang_id' => $this->cabang->id]);

        // Mode per entitas milik cabang lain → ditolak
        Livewire::test(RiwayatAktivitas::class, ['tipe' => 'stok', 'entityId' => $stokLain->id])
            ->assertSee('tidak memiliki akses')
            ->assertDontSee('Dibuat');

        // Mode daftar per tipe: hanya log cabang sendiri (+ global) yang tampil
        $daftar = Livewire::test(RiwayatAktivitas::class, ['tipe' => 'stok']);
        $subjectIds = collect($daftar->viewData('aktivitas')->items())->pluck('subject_id');
        $this->assertTrue($subjectIds->contains($stokSendiri->id), 'Log cabang sendiri wajib tampil');
        $this->assertFalse($subjectIds->contains($stokLain->id), 'Log cabang lain TIDAK boleh tampil');

        // Super-admin bebas lintas cabang
        $super = User::create([
            'name' => 'Super Aktivitas', 'email' => 'super-aktivitas@test.com',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $super->assignRole('super-admin');
        $this->actingAs($super, 'web');
        session(['cabang_id' => $this->cabang->id]);

        Livewire::test(RiwayatAktivitas::class, ['tipe' => 'stok', 'entityId' => $stokLain->id])
            ->assertDontSee('tidak memiliki akses')
            ->assertSee('Dibuat');
    }

    public function test_permission_dan_migrasi_membuat_lihat_audit_log(): void
    {
        $this->assertTrue(Schema::hasColumn('activity_log', 'cabang_id'));
        $this->assertDatabaseHas('permissions', ['name' => 'lihat-audit-log', 'guard_name' => 'web']);

        foreach (['super-admin', 'finance', 'admin-toko'] as $namaRole) {
            $this->assertTrue(
                Role::where('name', $namaRole)->first()->hasPermissionTo('lihat-audit-log'),
                "Role {$namaRole} wajib punya lihat-audit-log"
            );
        }
    }
}
