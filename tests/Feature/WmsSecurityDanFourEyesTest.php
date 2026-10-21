<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\OpnameTab;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokOpname;
use App\Modules\Wms\Models\StokOpnameItem;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class WmsSecurityDanFourEyesTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Gudang $gudang;

    private User $adminToko1;

    private User $adminToko2;

    private User $superAdmin;

    private User $staffGudang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create(['nama' => 'Cabang Utama', 'kode' => 'CBG-01', 'is_active' => true]);
        $this->gudang = Gudang::create(['cabang_id' => $this->cabang->id, 'nama' => 'Gudang Utama', 'kode' => 'GDG-01', 'is_active' => true]);

        $this->adminToko1 = User::create([
            'name' => 'Admin Toko 1',
            'email' => 'admintoko1@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->adminToko1->assignRole('admin-toko');
        $this->adminToko1->cabangs()->attach($this->cabang->id);

        $this->adminToko2 = User::create([
            'name' => 'Admin Toko 2',
            'email' => 'admintoko2@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->adminToko2->assignRole('admin-toko');
        $this->adminToko2->cabangs()->attach($this->cabang->id);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole('super-admin');
        $this->superAdmin->cabangs()->attach($this->cabang->id);

        $this->staffGudang = User::create([
            'name' => 'Staff Gudang',
            'email' => 'staffgudang@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->staffGudang->assignRole('staff-gudang');
        $this->staffGudang->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);
    }

    public function test_four_eyes_principle_pembuat_opname_ditolak_approve_buatannya_sendiri(): void
    {
        $produk = Produk::create([
            'nama' => 'LCD Test Four Eyes',
            'slug' => 'lcd-test-four-eyes',
            'kategori' => 'LCD',
            'harga_beli' => 100000,
            'harga_jual_retail' => 150000,
            'is_active' => true,
        ]);

        StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $produk->id,
            'jumlah' => 10,
        ]);

        // Admin Toko 1 membuat dokumen opname
        $opname = StokOpname::create([
            'no_opname' => 'OPN-TEST-4EYES-01',
            'gudang_id' => $this->gudang->id,
            'user_id' => $this->adminToko1->id,
            'status' => 'menunggu_approval',
            'catatan' => 'Opname oleh Admin 1',
        ]);

        StokOpnameItem::create([
            'stok_opname_id' => $opname->id,
            'produk_id' => $produk->id,
            'stok_sistem' => 10,
            'stok_fisik' => 8,
            'selisih' => -2,
        ]);

        // Coba approve oleh pembuatnya sendiri (Admin Toko 1) -> HARUS DITOLAK
        $this->actingAs($this->adminToko1, 'web');

        Livewire::test(OpnameTab::class)
            ->call('approveOpname', $opname->id)
            ->assertDispatched('alert', function ($event, $params) {
                $type = $params[0]['type'] ?? $params['type'] ?? '';
                $message = $params[0]['message'] ?? $params['message'] ?? '';

                return $type === 'error' && str_contains($message, 'Four-Eyes Principle');
            });

        // Status opname harus TETAP menunggu_approval
        $this->assertSame('menunggu_approval', $opname->fresh()->status);
        $this->assertSame(10, (int) StokItem::where('produk_id', $produk->id)->value('jumlah'));

        // Coba via API oleh pembuatnya sendiri -> HARUS 403 Forbidden
        $response = $this->putJson("/api/wms/opname/{$opname->id}/approve");
        $response->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->assertSame('menunggu_approval', $opname->fresh()->status);
    }

    public function test_four_eyes_principle_user_berbeda_berhasil_approve_opname(): void
    {
        $produk = Produk::create([
            'nama' => 'Baterai Test Four Eyes',
            'slug' => 'baterai-test-four-eyes',
            'kategori' => 'Baterai',
            'harga_beli' => 50000,
            'harga_jual_retail' => 80000,
            'is_active' => true,
        ]);

        StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $produk->id,
            'jumlah' => 10,
        ]);

        // Admin Toko 1 yang mengajukan opname
        $opname = StokOpname::create([
            'no_opname' => 'OPN-TEST-4EYES-02',
            'gudang_id' => $this->gudang->id,
            'user_id' => $this->adminToko1->id,
            'status' => 'menunggu_approval',
            'catatan' => 'Opname oleh Admin 1',
        ]);

        StokOpnameItem::create([
            'stok_opname_id' => $opname->id,
            'produk_id' => $produk->id,
            'stok_sistem' => 10,
            'stok_fisik' => 7,
            'selisih' => -3,
        ]);

        // Admin Toko 2 (orang berbeda) yang menyetujui -> HARUS BERHASIL
        $this->actingAs($this->adminToko2, 'web');

        Livewire::test(OpnameTab::class)
            ->call('approveOpname', $opname->id)
            ->assertDispatched('alert', function ($event, $params) {
                $type = $params[0]['type'] ?? $params['type'] ?? '';

                return $type === 'success';
            });

        $this->assertSame('disetujui', $opname->fresh()->status);
        $this->assertSame($this->adminToko2->id, $opname->fresh()->approver_id);
        $this->assertSame(7, (int) StokItem::where('produk_id', $produk->id)->value('jumlah'));
    }

    public function test_hapus_produk_hanya_bisa_dilakukan_oleh_superadmin_atau_owner(): void
    {
        $produk = Produk::create([
            'nama' => 'Produk Coba Hapus',
            'slug' => 'produk-coba-hapus',
            'kategori' => 'Aksesoris',
            'harga_beli' => 10000,
            'harga_jual_retail' => 20000,
            'is_active' => true,
        ]);

        // 1. Staff gudang coba hapus via Livewire -> Ditolak
        $this->actingAs($this->staffGudang, 'web');
        Livewire::test(ProdukTab::class)
            ->call('hapusProduk', $produk->id)
            ->assertDispatched('alert', function ($event, $params) {
                $type = $params[0]['type'] ?? $params['type'] ?? '';
                $message = $params[0]['message'] ?? $params['message'] ?? '';

                return $type === 'error' && str_contains($message, 'Super Admin / Owner');
            });
        $this->assertDatabaseHas('produk', ['id' => $produk->id]);

        // 2. Admin toko coba hapus via API -> Ditolak (403)
        $this->actingAs($this->adminToko1, 'web');
        $this->deleteJson("/api/wms/produk/{$produk->id}")
            ->assertStatus(403);
        $this->assertDatabaseHas('produk', ['id' => $produk->id]);

        // 3. Super admin hapus produk bersih tanpa transaksi dan stok 0 -> Berhasil terhapus permanen
        $this->actingAs($this->superAdmin, 'web');
        Livewire::test(ProdukTab::class)
            ->call('hapusProduk', $produk->id)
            ->assertDispatched('alert', function ($event, $params) {
                $type = $params[0]['type'] ?? $params['type'] ?? '';
                $message = $params[0]['message'] ?? $params['message'] ?? '';

                return $type === 'success' && str_contains($message, 'berhasil dihapus permanen');
            });
        $this->assertDatabaseMissing('produk', ['id' => $produk->id]);
    }

    public function test_hapus_produk_dengan_riwayat_transaksi_beralih_ke_nonaktif(): void
    {
        $produk = Produk::create([
            'nama' => 'Produk Ada Riwayat Transaksi',
            'slug' => 'produk-ada-riwayat-transaksi',
            'kategori' => 'Sparepart',
            'harga_beli' => 10000,
            'harga_jual_retail' => 20000,
            'is_active' => true,
        ]);

        // Buat dummy riwayat transaksi
        $trx = Transaksi::create([
            'cabang_id' => $this->cabang->id,
            'no_transaksi' => 'TRX-TEST-001',
            'user_id' => $this->adminToko1->id,
            'total' => 20000,
            'subtotal' => 20000,
            'metode_bayar' => 'tunai',
            'status' => 'selesai',
        ]);

        TransaksiItem::create([
            'transaksi_id' => $trx->id,
            'produk_id' => $produk->id,
            'jumlah' => 1,
            'harga_satuan' => 20000,
            'subtotal' => 20000,
            'hpp' => 10000,
        ]);

        $this->actingAs($this->superAdmin, 'web');

        // Super Admin mencoba hapus produk yang ada jejak transaksi
        Livewire::test(ProdukTab::class)
            ->call('hapusProduk', $produk->id)
            ->assertDispatched('alert', function ($event, $params) {
                $type = $params[0]['type'] ?? $params['type'] ?? '';
                $message = $params[0]['message'] ?? $params['message'] ?? '';

                return $type === 'warning' && str_contains($message, 'NONAKTIF');
            });

        // Produk tetap ada di database demi keutuhan audit, tapi statusnya jadi NONAKTIF
        $this->assertDatabaseHas('produk', [
            'id' => $produk->id,
            'is_active' => false,
        ]);
    }
}
