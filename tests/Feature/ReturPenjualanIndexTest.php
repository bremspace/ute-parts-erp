<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCoa;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Pos\Livewire\ReturPenjualanIndex;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Livewire\SettingsRbac;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReturPenjualanIndexTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'kasir', 'guard_name' => 'web']);
        Permission::findOrCreate('pos.view', 'web');
        Permission::findOrCreate('pos.view-own', 'web');
        $role->givePermissionTo(['pos.view', 'pos.view-own']);

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Retur Test',
            'kode' => 'CBR',
            'alamat' => 'Jl. Retur No 1',
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Utama Retur',
            'kode' => 'G-RETUR',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
        $this->user->assignRole('kasir');

        $this->produk = Produk::create([
            'kode' => 'LCD-TEST',
            'nama' => 'LCD Screen Test',
            'harga_beli' => 100000,
            'harga_jual' => 150000,
            'status' => 'aktif',
        ]);

        // Akun COA
        $coas = [
            ['kode' => '110-01', 'nama' => 'Kas Toko', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit'],
            ['kode' => '120-01', 'nama' => 'Piutang Usaha', 'tipe' => 'aset', 'kelompok' => 'piutang', 'saldo_normal' => 'debit'],
            ['kode' => '130-01', 'nama' => 'Persediaan Barang Dagang', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit'],
            ['kode' => '210-01', 'nama' => 'Utang Usaha', 'tipe' => 'kewajiban', 'kelompok' => 'utang_usaha', 'saldo_normal' => 'kredit'],
            ['kode' => '410-01', 'nama' => 'Pendapatan Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '410-02', 'nama' => 'Retur Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'retur_penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '510-02', 'nama' => 'HPP', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit'],
        ];

        foreach ($coas as $c) {
            AkunCoa::create($c);
        }
    }

    public function test_halaman_manajemen_retur_dapat_diakses_oleh_user_pos(): void
    {
        $this->actingAs($this->user)
            ->withSession(['cabang_id' => $this->cabang->id])
            ->get('/app/pos/retur')
            ->assertStatus(200)
            ->assertSee('Manajemen Retur Konsumen');
    }

    public function test_verifikasi_nomor_kwitansi_memvalidasi_transaksi_cabang_aktif(): void
    {
        session(['cabang_id' => $this->cabang->id]);

        $pelanggan = Pelanggan::create(['nama' => 'Budi Retur', 'tipe' => 'retail']);

        $transaksi = Transaksi::create([
            'no_transaksi' => 'TRX-RETUR-001',
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'kasir_id' => $this->user->id,
            'pelanggan_id' => $pelanggan->id,
            'total_barang' => 2,
            'subtotal' => 300000,
            'total_akhir' => 300000,
            'status' => 'selesai',
        ]);

        $trxItem = TransaksiItem::create([
            'transaksi_id' => $transaksi->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 2,
            'harga_satuan' => 150000,
            'subtotal' => 300000,
            'hpp' => 100000,
        ]);

        Livewire::actingAs($this->user)
            ->test(ReturPenjualanIndex::class)
            ->set('kwitansiInput', 'TRX-RETUR-001')
            ->call('cariKwitansi')
            ->assertSet('selectedTransaksi.id', $transaksi->id)
            ->assertSee('LCD Screen Test');
    }

    public function test_verifikasi_faktur_palsu_atau_salah_cabang_ditolak(): void
    {
        session(['cabang_id' => $this->cabang->id]);

        Livewire::actingAs($this->user)
            ->test(ReturPenjualanIndex::class)
            ->set('kwitansiInput', 'TRX-TIDAK-ADA-999')
            ->call('cariKwitansi')
            ->assertSet('selectedTransaksi', null)
            ->assertDispatched('alert');
    }

    public function test_proses_retur_penjualan_berhasil_via_halaman_retur(): void
    {
        session(['cabang_id' => $this->cabang->id]);

        $pelanggan = Pelanggan::create(['nama' => 'Siti Retur', 'tipe' => 'retail']);

        $transaksi = Transaksi::create([
            'no_transaksi' => 'TRX-RETUR-002',
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'kasir_id' => $this->user->id,
            'pelanggan_id' => $pelanggan->id,
            'total_barang' => 1,
            'subtotal' => 150000,
            'total_akhir' => 150000,
            'status' => 'selesai',
        ]);

        $trxItem = TransaksiItem::create([
            'transaksi_id' => $transaksi->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 1,
            'harga_satuan' => 150000,
            'subtotal' => 150000,
            'hpp' => 100000,
        ]);

        StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 5,
        ]);

        Livewire::actingAs($this->user)
            ->test(ReturPenjualanIndex::class)
            ->set('kwitansiInput', 'TRX-RETUR-002')
            ->call('cariKwitansi')
            ->set("itemInputs.{$trxItem->id}.jumlah", 1)
            ->set("itemInputs.{$trxItem->id}.kondisi_barang", 'rusak')
            ->set('tipePenyelesaian', 'refund')
            ->set('metodePengembalian', 'kas')
            ->set('alasan', 'Layar blank setelah dipasang')
            ->call('simpanRetur')
            ->assertDispatched('alert');

        $this->assertDatabaseHas('return_penjualan', [
            'transaksi_id' => $transaksi->id,
            'tipe_penyelesaian' => 'refund',
            'status' => 'selesai',
        ]);
    }

    public function test_kustomisasi_template_struk_tersimpan_dengan_fitur_qr_faktur(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Permission::findOrCreate('pengaturan.manage', 'web');
        $adminRole->givePermissionTo('pengaturan.manage');
        $this->user->assignRole('super-admin');

        Livewire::actingAs($this->user)
            ->test(SettingsRbac::class)
            ->set('activeTab', 'printer')
            ->set('printerSubTab', 'struk')
            ->set('strukForm.nama_toko', 'Toko Parts Jaya')
            ->set('strukForm.slogan', 'Spesialis LCD & Baterai')
            ->set('strukForm.alamat', 'Jl. Barcode No. 88')
            ->set('strukForm.telepon', '081299998888')
            ->set('strukForm.show_qr_retur', true)
            ->set('strukForm.qr_caption', 'Scan QR untuk klaim retur')
            ->set('strukForm.footer_text', 'Matur Nuwun!')
            ->call('simpanTemplateStruk')
            ->assertDispatched('alert');

        $cfg = app(KonfigurasiService::class)->getStrukConfig();
        $this->assertEquals('Toko Parts Jaya', $cfg['nama_toko']);
        $this->assertEquals('Spesialis LCD & Baterai', $cfg['slogan']);
        $this->assertTrue($cfg['show_qr_retur']);
        $this->assertEquals('Scan QR untuk klaim retur', $cfg['qr_caption']);
    }
}
