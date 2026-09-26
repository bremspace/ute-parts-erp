<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Livewire\ServisBoard;
use App\Modules\Servis\Models\ServisSparepart;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * [B-07] Daftar tiket servis (papan kanban `/app/servis` + API [SERVICE-02]).
 *
 * Regresi QueryException: SQLSTATE[HY001] 1038 "Out of sort memory, consider
 * increasing server sort buffer size" pada
 * `select tiket_servis.*, (subquery spareparts_count) … order by created_at desc limit 100`.
 *
 * Root cause (terbukti di MySQL 8.0.46, sort_buffer_size 256KB): MySQL mem-packing
 * seluruh baris yang di-sort ke dalam sort buffer, sedangkan `foto_unit` menyimpan
 * base64 foto (431.406 byte/baris terukur di db_staging) → 1 baris > buffer → 1038.
 * Fix aplikasi = ORDER BY + LIMIT hanya menyentuh kolom sempit `id` (query 2 tahap),
 * plus index `created_at` / `(cabang_id, created_at)` agar step-1 jadi covering scan.
 *
 * Test di bawah membuktikan: (1) index tercipta, (2) query list jalan dgn row berfoto
 * lebar, (3) ORDER BY created_at tidak pernah menyertakan kolom lebar,
 * (4) scoping cabang aktif & spareparts_count akurat, (5) filter status/search tetap jalan.
 *
 * Catatan: SQLite (media test) tidak punya sort_buffer_size sehingga error 1038 tidak
 * bisa direproduksi di sini — test struktur query (butir 3) adalah gardu regresinya.
 */
class ServisListQueryTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create(['nama' => 'Pusat', 'kode' => 'CBG-B07', 'is_active' => true]);
        session(['cabang_id' => $this->cabang->id]);

        $this->user = User::create([
            'name' => 'Admin B07',
            'email' => 'admin-b07@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);
        $this->actingAs($this->user, 'web');
    }

    private function buatTiket(string $noTiket, array $overrides = []): TiketServis
    {
        $tiket = TiketServis::create(array_merge([
            'no_tiket' => $noTiket,
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Pelanggan B07',
            'telepon_pelanggan' => '0812000000',
            'jenis_hp' => 'Samsung A52',
            'keluhan' => 'Layar retak',
            'status' => 'diterima',
            'tanggal_terima' => now(),
        ], $overrides));

        if (isset($overrides['created_at'])) {
            $tiket->created_at = $overrides['created_at'];
            $tiket->save();
        }

        return $tiket;
    }

    /** Satu baris `foto_unit` selebar yang terukur di db_staging (> sort_buffer 256KB). */
    private function fotoLebar(): array
    {
        return ['data:image/jpeg;base64,'.str_repeat('A', 400_000)];
    }

    private function buatSparepart(TiketServis $tiket): ServisSparepart
    {
        $produk = Produk::create([
            'nama' => 'LCD B07', 'slug' => 'lcd-b07', 'kategori' => 'LCD',
            'kondisi' => 'baru', 'harga_beli' => 100000, 'harga_jual_retail' => 150000,
        ]);
        $gudang = Gudang::create([
            'cabang_id' => $this->cabang->id, 'nama' => 'Gudang B07', 'kode' => 'GDG-B07', 'is_active' => true,
        ]);

        return ServisSparepart::create([
            'tiket_servis_id' => $tiket->id,
            'produk_id' => $produk->id,
            'gudang_id' => $gudang->id,
            'jumlah' => 1,
            'harga_satuan' => 150000,
            'hpp' => 100000,
        ]);
    }

    /** (1) Index baru tercipta (migration idempotent `2026_09_25_020000`). */
    public function test_index_created_at_pada_tiket_servis_terbuat(): void
    {
        $this->assertTrue(
            Schema::hasIndex('tiket_servis', 'tiket_servis_created_at_idx'),
            'Index tiket_servis(created_at) wajib ada — ORDER BY list tidak boleh filesort baris lebar'
        );
        $this->assertTrue(
            Schema::hasIndex('tiket_servis', 'tiket_servis_cabang_id_created_at_idx'),
            'Index tiket_servis(cabang_id, created_at) wajib ada utk SERVICE-02 scoping cabang'
        );
    }

    /** (2) Papan kanban render dgn row berfoto LEBAR + spareparts_count benar & terbaru dulu. */
    public function test_papan_kanban_render_dengan_foto_lebar_dan_spareparts_count(): void
    {
        $tiketLama = $this->buatTiket('SRV-B07-0001', [
            'created_at' => now()->subMinutes(10),
            'foto_unit' => $this->fotoLebar(),
        ]);
        $tiketBaru = $this->buatTiket('SRV-B07-0002', ['created_at' => now()]);
        $this->buatSparepart($tiketBaru);

        $component = Livewire::test(ServisBoard::class)
            ->assertSee('SRV-B07-0001')
            ->assertSee('SRV-B07-0002')
            ->assertSee('128295', false); // badge sparepart (&#128295;) muncul krn count > 0

        $grouped = $component->viewData('groupedTikets');

        $this->assertArrayHasKey('diterima', $grouped);
        $this->assertSame(
            [$tiketBaru->id, $tiketLama->id],
            $grouped['diterima']->pluck('id')->all(),
            'Urutan terbaru-dulu (`latest()`) harus dipertahankan setelah query 2 tahap'
        );

        $this->assertSame(1, $grouped['diterima']->firstWhere('id', $tiketBaru->id)->spareparts_count);
        $this->assertSame(0, $grouped['diterima']->firstWhere('id', $tiketLama->id)->spareparts_count);
    }

    /** (3) Gardu regresi: ORDER BY created_at untuk tiket_servis HANYA boleh menyentuh kolom sempit. */
    public function test_order_by_created_at_tiket_servis_tidak_menyertakan_kolom_lebar(): void
    {
        $this->buatTiket('SRV-B07-0101', ['foto_unit' => $this->fotoLebar()]);
        $this->buatTiket('SRV-B07-0102', ['status' => 'diagnosa']);

        DB::enableQueryLog();

        Livewire::test(ServisBoard::class);
        Livewire::test(ServisBoard::class)->set('filterStatus', 'diagnosa');
        $this->getJson('/api/servis');
        $this->getJson('/api/servis?status=diagnosa');

        $sortTiket = collect(DB::getQueryLog())->pluck('query')
            ->filter(fn (string $q) => str_contains(strtolower($q), 'order by')
                && str_contains(strtolower($q), 'created_at')
                && str_contains(strtolower($q), 'tiket_servis'))
            ->values();

        DB::disableQueryLog();

        $this->assertNotEmpty($sortTiket->all(), 'Harus ada query ORDER BY created_at utk tiket_servis (B-07)');

        foreach ($sortTiket as $q) {
            $this->assertMatchesRegularExpression(
                '/^select\s+(?:"tiket_servis"\.)?"id"\s+from\s+"tiket_servis"/',
                trim($q),
                'ORDER BY created_at utk tiket_servis hanya boleh menyentuh kolom sempit `id` — '.substr($q, 0, 160)
            );
        }
    }

    /** (4) API [SERVICE-02]: scoping cabang aktif + spareparts_count terisi + struktur paginator. */
    public function test_api_service_02_scoping_cabang_dan_spareparts_count(): void
    {
        $cabangLain = Cabang::create(['nama' => 'Cabang Lain', 'kode' => 'CBG-B07B', 'is_active' => true]);

        $tiketMilik = $this->buatTiket('SRV-B07-0201', ['foto_unit' => $this->fotoLebar()]);
        $this->buatSparepart($tiketMilik);

        $tiketLain = TiketServis::create([
            'no_tiket' => 'SRV-B07-0202',
            'cabang_id' => $cabangLain->id,
            'nama_pelanggan' => 'Pelanggan Lain',
            'telepon_pelanggan' => '0813000000',
            'jenis_hp' => 'Xiaomi 12',
            'keluhan' => 'Tidak bisa nyala',
            'status' => 'diterima',
            'tanggal_terima' => now(),
        ]);

        $response = $this->getJson('/api/servis')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $rows = collect($response->json('data.data'));
        $ids = $rows->pluck('id');

        $this->assertTrue($ids->contains($tiketMilik->id), 'Tiket cabang aktif harus tampil');
        $this->assertFalse($ids->contains($tiketLain->id), 'Tiket cabang lain TIDAK boleh bocor (scoping cabang)');
        $this->assertSame(1, $rows->firstWhere('id', $tiketMilik->id)['spareparts_count']);

        // Struktur paginator SERVICE-02 tidak berubah
        $this->assertArrayHasKey('current_page', $response->json('data'));
        $this->assertArrayHasKey('total', $response->json('data'));
        $this->assertSame(1, $response->json('data.current_page'));
    }

    /** (5) Filter status & search (varian query yang paling rawan filesort) tetap berfungsi. */
    public function test_api_service_02_filter_status_dan_search(): void
    {
        $diterima = $this->buatTiket('SRV-B07-0301', ['foto_unit' => $this->fotoLebar()]);
        $diagnosa = $this->buatTiket('SRV-B07-0302', ['status' => 'diagnosa', 'jenis_hp' => 'iPhone 15 Pro']);

        $byStatus = $this->getJson('/api/servis?status=diagnosa')->assertStatus(200);
        $idsStatus = collect($byStatus->json('data.data'))->pluck('id');

        $this->assertTrue($idsStatus->contains($diagnosa->id));
        $this->assertFalse($idsStatus->contains($diterima->id));

        $bySearch = $this->getJson('/api/servis?search=iPhone%2015')->assertStatus(200);
        $idsSearch = collect($bySearch->json('data.data'))->pluck('id');

        $this->assertSame([$diagnosa->id], $idsSearch->all());

        // Papan kanban: filter status via computed property
        $component = Livewire::test(ServisBoard::class)->set('filterStatus', 'diagnosa');
        $grouped = $component->viewData('groupedTikets');

        $this->assertSame([$diagnosa->id], $grouped['diagnosa']->pluck('id')->all());
        $this->assertEmpty($grouped['diterima']);
    }
}
