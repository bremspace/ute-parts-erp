<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Services\KasSesiState;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PosKasirMemberTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected User $kasir;

    protected Produk $produk;

    protected Pelanggan $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach ([
            '110-01' => ['Kas', 'aset', 'kas', 'debit'],
            '110-02' => ['Bank', 'aset', 'kas', 'debit'],
            '110-04' => ['Kas Laci', 'aset', 'kas', 'debit'],
            '120-01' => ['Piutang Usaha', 'aset', 'piutang_usaha', 'debit'],
            '130-01' => ['Persediaan', 'aset', 'persediaan', 'debit'],
            '220-01' => ['PPN Keluaran', 'kewajiban', 'pajak', 'kredit'],
            '410-01' => ['Pendapatan Penjualan', 'pendapatan', 'pendapatan_penjualan', 'kredit'],
            '510-02' => ['HPP', 'beban', 'hpp', 'debit'],
        ] as $kode => [$nama, $tipe, $kelompok, $saldo]) {
            AkunCOA::firstOrCreate(['kode' => $kode], [
                'nama' => $nama, 'tipe' => $tipe, 'kelompok' => $kelompok, 'saldo_normal' => $saldo,
            ]);
        }

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Utama',
            'kode' => 'C01',
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Utama',
            'kode' => 'GDG-01',
            'is_active' => true,
        ]);

        $this->kasir = User::create([
            'name' => 'Kasir POS',
            'email' => 'kasir@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->kasir->assignRole('super-admin');
        $this->kasir->cabangs()->attach($this->cabang->id);

        $tier = TierMembership::create([
            'nama' => 'Gold',
            'kode' => 'GOLD',
            'min_belanja_12bulan' => 1000000,
            'diskon_persen' => 10,
            'poin_multiplier' => 1,
            'urutan' => 1,
            'is_active' => true,
        ]);

        $this->member = Pelanggan::create([
            'nama' => 'Member Test',
            'telepon' => '08123456789',
            'email' => 'member@test.com',
            'tier_membership_id' => $tier->id,
            'is_reseller' => false,
            'tipe_konsumen' => 'retail',
            'status' => 'approved',
        ]);

        $this->produk = Produk::create([
            'nama' => 'Produk Stok 1',
            'slug' => Str::slug('Produk Stok 1').'-'.Str::random(4),
            'harga_beli' => 10000,
            'harga_jual_retail' => 15000,
            'satuan' => 'pcs',
            'is_active' => true,
        ]);

        SkuVariant::create([
            'produk_id' => $this->produk->id,
            'sku' => 'SKU-001',
            'barcode' => 'BAR-001',
            'nama_varian' => 'Standar',
            'satuan_kode' => 'pcs',
            'harga_beli' => 10000,
            'harga_jual_retail' => 15000,
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $this->produk->id,
            'sku_variant_id' => SkuVariant::first()->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 1,
        ]);

        // Baris phantom varian NULL saldo 0 (seperti yang terjadi di staging karena PosController::store)
        StokItem::create([
            'produk_id' => $this->produk->id,
            'sku_variant_id' => null,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 0,
        ]);

        session(['cabang_id' => $this->cabang->id]);

        app(KasSesiState::class)->bukaKas(
            saldoAwal: 50000,
            cabangId: $this->cabang->id,
            userId: $this->kasir->id,
            sumber: 'manual',
            akunSumberKode: '110-01'
        );
    }

    public function test_transaksi_dengan_member_stok_1(): void
    {
        $this->actingAs($this->kasir);

        Livewire::test(PosKasir::class)
            ->call('addToCart', $this->produk->id)
            ->call('setPelanggan', $this->member->id)
            ->call('openPaymentModal')
            ->set('metodeBayar', 'tunai')
            ->set('jumlahBayar', 20000)
            ->call('processTransaction');

        $this->assertSame(1, Transaksi::count());
        $trx = Transaksi::first();
        $this->assertSame($this->member->id, $trx->pelanggan_id);
        $this->assertSame('selesai', $trx->status);
    }

    public function test_transaksi_multi_varian_dengan_baris_phantom_null(): void
    {
        $this->actingAs($this->kasir);

        $produkMulti = Produk::create([
            'nama' => 'Produk Multi Varian',
            'slug' => Str::slug('Produk Multi Varian').'-'.Str::random(4),
            'harga_beli' => 20000,
            'harga_jual_retail' => 30000,
            'satuan' => 'pcs',
            'is_active' => true,
        ]);

        $v1 = SkuVariant::create([
            'produk_id' => $produkMulti->id,
            'sku' => 'SKU-MV-1',
            'barcode' => 'BAR-MV-1',
            'nama_varian' => 'Merah',
            'satuan_kode' => 'pcs',
            'harga_beli' => 20000,
            'harga_jual_retail' => 30000,
            'is_active' => true,
        ]);

        $v2 = SkuVariant::create([
            'produk_id' => $produkMulti->id,
            'sku' => 'SKU-MV-2',
            'barcode' => 'BAR-MV-2',
            'nama_varian' => 'Biru',
            'satuan_kode' => 'pcs',
            'harga_beli' => 20000,
            'harga_jual_retail' => 30000,
            'is_active' => true,
        ]);

        // Varian 1 kosong
        StokItem::create([
            'produk_id' => $produkMulti->id,
            'sku_variant_id' => $v1->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 0,
        ]);

        // Varian 2 stok 1
        StokItem::create([
            'produk_id' => $produkMulti->id,
            'sku_variant_id' => $v2->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 1,
        ]);

        // Baris phantom null stok 0
        StokItem::create([
            'produk_id' => $produkMulti->id,
            'sku_variant_id' => null,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 0,
        ]);

        // Tambah ke keranjang tanpa varian eksplisit (misal kasir klik kartu produk)
        Livewire::test(PosKasir::class)
            ->call('addToCart', $produkMulti->id)
            ->call('setPelanggan', $this->member->id)
            ->call('openPaymentModal')
            ->set('metodeBayar', 'tunai')
            ->set('jumlahBayar', 50000)
            ->call('processTransaction');

        $this->assertSame(1, Transaksi::count());
        $trx = Transaksi::latest('id')->first();
        $this->assertSame($this->member->id, $trx->pelanggan_id);
        $this->assertSame('selesai', $trx->status);
    }

    public function test_pos_controller_store_tidak_membuat_baris_phantom_null(): void
    {
        $this->actingAs($this->kasir);

        $payload = [
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'pelanggan_id' => $this->member->id,
            'items' => [
                [
                    'produk_id' => $this->produk->id,
                    'sku_variant_id' => null, // Omitted or null
                    'jumlah' => 1,
                    'harga_satuan' => 15000,
                ],
            ],
            'metode_bayar' => 'tunai',
            'jumlah_bayar' => 20000,
        ];

        // Hitung baris null sebelum
        $nullRowsBefore = StokItem::where('produk_id', $this->produk->id)->whereNull('sku_variant_id')->count();

        $response = $this->postJson('/api/pos/transaksi', $payload);
        $response->assertStatus(201);

        $nullRowsAfter = StokItem::where('produk_id', $this->produk->id)->whereNull('sku_variant_id')->count();
        $this->assertSame($nullRowsBefore, $nullRowsAfter);
    }
}
