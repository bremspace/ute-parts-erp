<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\PoTab;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Supplier;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierDatabaseDanSaldoMutasiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create(['nama' => 'Cabang Utama', 'kode' => 'C01', 'is_active' => true]);
        $this->gudang = Gudang::create(['nama' => 'Gudang Utama', 'kode' => 'G01', 'cabang_id' => $this->cabang->id, 'is_active' => true]);

        $this->user = User::factory()->create();
        $this->user->cabangs()->attach($this->cabang->id, ['is_default' => true]);
        $this->user->assignRole('super-admin');

        $this->supplier = Supplier::create([
            'nama' => 'PT Mitra Sparepart Sejati',
            'kontak' => 'Budi Santoso',
            'telepon' => '08123456789',
            'email' => 'budi@mitrasparepart.id',
            'alamat' => 'Jl. Industri No. 45 Jakarta Barat',
            'termin_hari' => 30,
            'saldo_deposit' => 0,
            'bank_nama' => 'BCA',
            'nomor_rekening' => '8820192831',
            'nama_rekening' => 'PT Mitra Sparepart Sejati',
            'catatan' => 'Supplier LCD & Baterai original',
            'is_active' => true,
        ]);

        session(['cabang_id' => $this->cabang->id]);
    }

    public function test_supplier_bisa_dibuat_dengan_profil_lengkap_dan_rekening_di_po_tab(): void
    {
        Livewire::actingAs($this->user)
            ->test(PoTab::class)
            ->set('supplierForm', [
                'nama' => 'CV Mega Partsindo',
                'kontak' => 'Dewi',
                'telepon' => '08987654321',
                'email' => 'dewi@megaparts.com',
                'alamat' => 'Komp. Ruko Harco Mas Blok D1',
                'termin_hari' => 14,
                'bank_nama' => 'Mandiri',
                'nomor_rekening' => '1370001234567',
                'nama_rekening' => 'CV Mega Partsindo',
                'catatan' => 'Garansi 3 bulan retur',
            ])
            ->call('simpanSupplier')
            ->assertHasNoErrors()
            ->assertDispatched('alert');

        $this->assertDatabaseHas('supplier', [
            'nama' => 'CV Mega Partsindo',
            'email' => 'dewi@megaparts.com',
            'bank_nama' => 'Mandiri',
            'nomor_rekening' => '1370001234567',
        ]);
    }

    public function test_supplier_profil_dapat_diperbarui_di_po_tab(): void
    {
        Livewire::actingAs($this->user)
            ->test(PoTab::class)
            ->call('bukaEditSupplier', $this->supplier->id)
            ->assertSet('editSupplierForm.nama', $this->supplier->nama)
            ->assertSet('editSupplierForm.bank_nama', 'BCA')
            ->set('editSupplierForm.bank_nama', 'BCA Digital (Blu)')
            ->set('editSupplierForm.nomor_rekening', '0011223344')
            ->call('perbaruiSupplier')
            ->assertHasNoErrors()
            ->assertDispatched('alert');

        $this->supplier->refresh();
        $this->assertEquals('BCA Digital (Blu)', $this->supplier->bank_nama);
        $this->assertEquals('0011223344', $this->supplier->nomor_rekening);
    }

    public function test_mutasi_saldo_deposit_supplier_tercatat_dan_terbaca_di_modal_profil(): void
    {
        // 1. Tambah saldo deposit
        $mutasiMasuk = $this->supplier->tambahSaldoDeposit(
            500000,
            'Deposit dari klaim retur barang garansi',
            null,
            $this->cabang->id,
            $this->user->id
        );

        $this->assertEquals(500000, (float) $this->supplier->saldo_deposit);
        $this->assertEquals('masuk', $mutasiMasuk->jenis);
        $this->assertEquals(0, (float) $mutasiMasuk->saldo_sebelum);
        $this->assertEquals(500000, (float) $mutasiMasuk->saldo_setelah);

        // 2. Kurangi saldo deposit
        $mutasiKeluar = $this->supplier->kurangiSaldoDeposit(
            200000,
            'Potong tagihan PO-2026-001',
            null,
            $this->cabang->id,
            $this->user->id
        );

        $this->assertEquals(300000, (float) $this->supplier->saldo_deposit);
        $this->assertEquals('keluar', $mutasiKeluar->jenis);
        $this->assertEquals(500000, (float) $mutasiKeluar->saldo_sebelum);
        $this->assertEquals(300000, (float) $mutasiKeluar->saldo_setelah);

        // 3. Verifikasi pembacaan di Livewire PoTab
        Livewire::actingAs($this->user)
            ->test(PoTab::class)
            ->call('bukaProfilSupplier', $this->supplier->id)
            ->assertSet('showSupplierProfileModal', true)
            ->assertSet('detailSupplierId', $this->supplier->id)
            ->assertSee('PT Mitra Sparepart Sejati')
            ->assertSee('8820192831')
            ->assertSee('Rp 300.000');
    }
}
