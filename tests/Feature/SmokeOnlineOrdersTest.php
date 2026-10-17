<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Pos\Livewire\PesananOnlineIndex;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SmokeOnlineOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create(['kode' => 'CBG-01', 'nama' => 'Pusat', 'alamat' => 'Jl. 1', 'telepon' => '0812', 'is_pusat' => true]);
        $perm = Permission::firstOrCreate(['name' => 'pos.view']);
        $role = Role::create(['name' => 'super-admin-smoke']);
        $role->givePermissionTo($perm);
        $this->user = User::factory()->create();
        $this->user->assignRole($role);
        $this->user->cabangs()->attach($this->cabang->id, ['is_default' => true]);
        session(['cabang_id' => $this->cabang->id]);

        AkunCOA::firstOrCreate(['kode' => '110-01'], ['nama' => 'Kas', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '110-04'], ['nama' => 'Kas Laci', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '110-02'], ['nama' => 'Bank', 'tipe' => 'aset', 'kelompok' => 'bank', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '120-02'], ['nama' => 'Kliring Duitku', 'tipe' => 'aset', 'kelompok' => 'piutang', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '410-03'], ['nama' => 'Pendapatan Web', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit']);
        AkunCOA::firstOrCreate(['kode' => '510-02'], ['nama' => 'HPP', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '130-01'], ['nama' => 'Persediaan', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit']);
    }

    public function test_online_orders_page_renders(): void
    {
        $this->actingAs($this->user);
        $this->get('/app/pos/online-orders')->assertStatus(200);
    }

    public function test_source_and_method_filters_render(): void
    {
        $this->actingAs($this->user);
        Livewire::test(PesananOnlineIndex::class)
            ->set('sumberFilter', 'shopee')
            ->set('metodeFilter', 'ambil_ke_toko')
            ->set('statusFilter', 'semua')
            ->assertSee('Pesanan Online');
    }
}
