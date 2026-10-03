<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Pos\Services\EscPosWriter;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Livewire\ServisBoard;
use App\Modules\Servis\Models\JenisServis;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Models\TiketServisEstimasiItem;
use App\Modules\Servis\Models\TiketServisItem;
use App\Modules\Wms\Models\Produk;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ThermalPrintReceiptOptimizationTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Test Ute',
            'kode' => 'CTU',
            'alamat' => 'Jl. Merdeka No. 10',
            'telepon' => '081234567890',
            'is_pusat' => true,
        ]);

        $this->user = User::factory()->create([
            'name' => 'Kasir Test',
        ]);
        $this->user->cabangs()->attach($this->cabang->id);

        $this->seed(AkunCoaSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->user->assignRole('super-admin');

        $this->actingAs($this->user);
        session(['cabang_id' => $this->cabang->id]);
    }

    public function test_service_ticket_cost_breakdown_includes_both_labor_and_spareparts(): void
    {
        $jenis = JenisServis::create(['kode' => 'JS-LCD', 'nama' => 'Ganti LCD', 'biaya_default' => 50000]);
        $produk = Produk::create([
            'nama' => 'LCD iPhone 11 OEM',
            'kode' => 'LCD-IP11',
            'harga_jual_retail' => 250000,
            'harga_beli' => 150000,
            'cabang_id' => $this->cabang->id,
        ]);

        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-TEST-001',
            'cabang_id' => $this->cabang->id,
            'jenis_servis_id' => $jenis->id,
            'nama_pelanggan' => 'Budi Santoso',
            'telepon_pelanggan' => '08123456789',
            'jenis_hp' => 'iPhone 11',
            'keluhan' => 'Layar retak',
            'status' => 'selesai',
            'status_pembayaran' => 'belum_bayar',
            'estimasi_biaya' => 300000,
        ]);

        TiketServisItem::create([
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'jasa',
            'nama_item' => 'Jasa Pasang LCD',
            'qty' => 1,
            'harga' => 50000,
            'hpp' => 0,
        ]);

        TiketServisItem::create([
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'part',
            'produk_id' => $produk->id,
            'nama_item' => $produk->nama,
            'qty' => 1,
            'harga' => 250000,
            'hpp' => 150000,
        ]);

        $rincian = $tiket->getRincianBiayaLengkap();

        $this->assertSame(300000.0, $rincian['total']);
        $this->assertSame(50000.0, $rincian['total_jasa']);
        $this->assertSame(250000.0, $rincian['total_part']);
        $this->assertCount(2, $rincian['items']);
        $this->assertSame('[Jasa] Jasa Pasang LCD', $rincian['items'][0]['nama']);
        $this->assertSame('[Part] LCD iPhone 11 OEM', $rincian['items'][1]['nama']);
    }

    public function test_service_ticket_fallback_to_estimasi_items_when_work_items_empty(): void
    {
        $jenis = JenisServis::create(['kode' => 'JS-MESIN', 'nama' => 'Servis Mesin', 'biaya_default' => 75000]);
        $produk = Produk::create([
            'nama' => 'IC Power PM6150',
            'kode' => 'IC-PM6150',
            'harga_jual_retail' => 60000,
            'harga_beli' => 30000,
            'cabang_id' => $this->cabang->id,
        ]);

        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-TEST-002',
            'cabang_id' => $this->cabang->id,
            'jenis_servis_id' => $jenis->id,
            'nama_pelanggan' => 'Siti Rahma',
            'telepon_pelanggan' => '08987654321',
            'jenis_hp' => 'Redmi Note 9',
            'keluhan' => 'Mati total',
            'status' => 'selesai',
            'status_pembayaran' => 'belum_bayar',
            'estimasi_biaya' => 135000,
        ]);

        TiketServisEstimasiItem::create([
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'jasa',
            'nama_item' => 'Jasa Ganti IC Power',
            'qty' => 1,
            'harga' => 75000,
            'subtotal' => 75000,
        ]);

        TiketServisEstimasiItem::create([
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'part',
            'produk_id' => $produk->id,
            'nama_item' => $produk->nama,
            'qty' => 1,
            'harga' => 60000,
            'subtotal' => 60000,
        ]);

        $rincian = $tiket->getRincianBiayaLengkap();

        $this->assertSame(135000.0, $rincian['total']);
        $this->assertSame(75000.0, $rincian['total_jasa']);
        $this->assertSame(60000.0, $rincian['total_part']);
        $this->assertCount(2, $rincian['items']);
        $this->assertStringContainsString('IC Power', $rincian['items'][1]['nama']);
    }

    public function test_pos_kasir_receipt_data_contains_both_jasa_and_part(): void
    {
        $jenis = JenisServis::create(['kode' => 'JS-CHG', 'nama' => 'Perbaikan Port Charger', 'biaya_default' => 40000]);
        $part = Produk::create([
            'nama' => 'Flex Charger Type C',
            'kode' => 'FLEX-CHG',
            'harga_jual_retail' => 45000,
            'harga_beli' => 20000,
            'cabang_id' => $this->cabang->id,
        ]);

        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-TEST-003',
            'cabang_id' => $this->cabang->id,
            'jenis_servis_id' => $jenis->id,
            'nama_pelanggan' => 'Rian Hidayat',
            'jenis_hp' => 'Samsung A51',
            'keluhan' => 'Tidak bisa cas',
            'status' => 'selesai',
            'status_pembayaran' => 'belum_bayar',
            'estimasi_biaya' => 85000,
        ]);

        TiketServisItem::create([
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'jasa',
            'nama_item' => 'Jasa Pasang Board Charger',
            'qty' => 1,
            'harga' => 40000,
            'hpp' => 0,
        ]);

        TiketServisItem::create([
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'part',
            'produk_id' => $part->id,
            'nama_item' => $part->nama,
            'qty' => 1,
            'harga' => 45000,
            'hpp' => 20000,
        ]);

        $component = Livewire::test(PosKasir::class)
            ->set('servisMetodeBayar', 'transfer')
            ->call('pilihTiketServis', $tiket->id);

        $detail = $component->get('selectedServisDetail');
        $this->assertNotNull($detail);
        $this->assertSame(40000.0, (float) $detail['jasa']);
        $this->assertSame(45000.0, (float) $detail['part']);
        $this->assertSame(85000.0, (float) $detail['total']);
        $this->assertCount(2, $detail['items']);

        $component->call('prosesBayarServis');

        $receiptData = $component->get('receiptData');
        $this->assertNotNull($receiptData);
        $this->assertSame('SRV-TEST-003', $receiptData['no_tiket']);
        $this->assertSame('Samsung A51', $receiptData['jenis_hp']);
        $this->assertSame(40000.0, (float) $receiptData['subtotal_jasa']);
        $this->assertSame(45000.0, (float) $receiptData['subtotal_part']);
        $this->assertSame(85000.0, (float) $receiptData['total']);
        $this->assertCount(2, $receiptData['items']);
    }

    public function test_servis_board_can_open_thermal_receipt(): void
    {
        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-TEST-004',
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Dewi Lestari',
            'jenis_hp' => 'Oppo Reno 6',
            'keluhan' => 'Baterai kembung',
            'status' => 'selesai',
            'status_pembayaran' => 'lunas',
            'estimasi_biaya' => 120000,
        ]);

        TiketServisItem::create([
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'jasa',
            'nama_item' => 'Jasa Ganti Baterai',
            'qty' => 1,
            'harga' => 50000,
            'hpp' => 0,
        ]);

        TiketServisItem::create([
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'part',
            'nama_item' => 'Baterai BLP800',
            'qty' => 1,
            'harga' => 70000,
            'hpp' => 40000,
        ]);

        $component = Livewire::test(ServisBoard::class)
            ->call('bukaStrukServis', $tiket->id);

        $this->assertTrue($component->get('showReceiptModal'));
        $receipt = $component->get('receiptData');
        $this->assertNotNull($receipt);
        $this->assertSame('SRV-TEST-004', $receipt['no_tiket']);
        $this->assertSame(120000.0, (float) $receipt['total']);
        $this->assertSame(50000.0, (float) $receipt['subtotal_jasa']);
        $this->assertSame(70000.0, (float) $receipt['subtotal_part']);
        $this->assertCount(2, $receipt['items']);
    }

    public function test_escpos_writer_formats_ticket_and_items_within_58mm(): void
    {
        $writer = app(EscPosWriter::class);
        $data = [
            'headerLines' => ['UTE PARTS', 'Pusat Sparepart HP'],
            'no_transaksi' => 'TRX-C01-20261003-0001',
            'no_tiket' => 'SRV-C01-20261003-0001',
            'jenis_hp' => 'iPhone 13 Pro',
            'tanggal' => '03/10/2026 14:00',
            'kasir' => 'Budi Kasir',
            'cabang' => 'Pusat',
            'items' => [
                ['nama' => '[Jasa] Ganti LCD', 'qty' => 1, 'harga' => 75000, 'subtotal' => 75000],
                ['nama' => '[Part] LCD Original OLED', 'qty' => 1, 'harga' => 1200000, 'subtotal' => 1200000],
            ],
            'total' => 1275000,
            'bayar' => 1300000,
            'kembali' => 25000,
            'metode_bayar' => 'TUNAI',
        ];

        $output = $writer->receipt($data);

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('SRV-C01-20261003-0001', $output);
        $this->assertStringContainsString('iPhone 13 Pro', $output);
        $this->assertStringContainsString('[Jasa] Ganti LCD', $output);
        $this->assertStringContainsString('[Part] LCD Original OLED', $output);
    }
}
