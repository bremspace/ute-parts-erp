<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokTransfer;
use App\Modules\Wms\Models\StokTransferItem;
use App\Modules\Wms\Services\TransferStokJournalService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferStokJurnalTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabangA;

    private Cabang $cabangB;

    private Gudang $gudangA;

    private Gudang $gudangA2;

    private Gudang $gudangB;

    private Produk $produk;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabangA = Cabang::create(['nama' => 'Cabang Surabaya', 'kode' => 'SBY', 'is_active' => true]);
        $this->cabangB = Cabang::create(['nama' => 'Cabang Malang', 'kode' => 'MLG', 'is_active' => true]);

        $this->gudangA = Gudang::create(['cabang_id' => $this->cabangA->id, 'nama' => 'Gudang Pusat SBY', 'kode' => 'G-SBY', 'is_active' => true]);
        $this->gudangA2 = Gudang::create(['cabang_id' => $this->cabangA->id, 'nama' => 'Gudang Toko SBY', 'kode' => 'G-SBY2', 'is_active' => true]);
        $this->gudangB = Gudang::create(['cabang_id' => $this->cabangB->id, 'nama' => 'Gudang MLG', 'kode' => 'G-MLG', 'is_active' => true]);

        $this->produk = Produk::create([
            'nama' => 'LCD iPhone 13 Pro',
            'kode' => 'LCD-IP13P',
            'harga_beli' => 250000,
            'harga_jual' => 450000,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
        $this->user->assignRole('super-admin');
    }

    public function test_transfer_antar_cabang_membuat_jurnal_rak_di_kedua_cabang(): void
    {
        // Setup transfer dari Cabang A ke Cabang B
        $transfer = StokTransfer::create([
            'no_transfer' => 'TRF-TEST-001',
            'gudang_asal_id' => $this->gudangA->id,
            'gudang_tujuan_id' => $this->gudangB->id,
            'user_pengirim_id' => $this->user->id,
            'status' => 'dikirim',
            'tanggal_kirim' => now(),
        ]);

        StokTransferItem::create([
            'stok_transfer_id' => $transfer->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 4, // 4 * 250.000 = 1.000.000
        ]);

        app(TransferStokJournalService::class)->catatJurnalTerimaTransfer($transfer, $this->user->id);

        // 1. Verifikasi jurnal Cabang A (Pengirim): Dr RAK 190-01 (1.000.000) / Cr Persediaan 130-01 (1.000.000)
        $jurnalA = JurnalAkuntansi::with('akun')->where('cabang_id', $this->cabangA->id)
            ->where('referensi_tipe', StokTransfer::class)
            ->where('referensi_id', $transfer->id)
            ->get();

        $this->assertCount(2, $jurnalA);
        $this->assertEquals(1000000, (float) $jurnalA->firstWhere('akun.kode', '190-01')?->debit);
        $this->assertEquals(1000000, (float) $jurnalA->firstWhere('akun.kode', '130-01')?->kredit);

        // 2. Verifikasi jurnal Cabang B (Penerima): Dr Persediaan 130-01 (1.000.000) / Cr RAK 190-01 (1.000.000)
        $jurnalB = JurnalAkuntansi::with('akun')->where('cabang_id', $this->cabangB->id)
            ->where('referensi_tipe', StokTransfer::class)
            ->where('referensi_id', $transfer->id)
            ->get();

        $this->assertCount(2, $jurnalB);
        $this->assertEquals(1000000, (float) $jurnalB->firstWhere('akun.kode', '130-01')?->debit);
        $this->assertEquals(1000000, (float) $jurnalB->firstWhere('akun.kode', '190-01')?->kredit);
    }

    public function test_transfer_dalam_cabang_sama_tidak_membuat_jurnal(): void
    {
        // Transfer sesama gudang di Cabang A
        $transfer = StokTransfer::create([
            'no_transfer' => 'TRF-TEST-SAME',
            'gudang_asal_id' => $this->gudangA->id,
            'gudang_tujuan_id' => $this->gudangA2->id,
            'user_pengirim_id' => $this->user->id,
            'status' => 'dikirim',
            'tanggal_kirim' => now(),
        ]);

        StokTransferItem::create([
            'stok_transfer_id' => $transfer->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 2,
        ]);

        app(TransferStokJournalService::class)->catatJurnalTerimaTransfer($transfer, $this->user->id);

        $jurnals = JurnalAkuntansi::where('referensi_tipe', StokTransfer::class)
            ->where('referensi_id', $transfer->id)
            ->get();

        $this->assertCount(0, $jurnals);
    }

    public function test_api_terima_transfer_otomatis_memicu_jurnal_rak(): void
    {
        $transfer = StokTransfer::create([
            'no_transfer' => 'TRF-API-TEST',
            'gudang_asal_id' => $this->gudangA->id,
            'gudang_tujuan_id' => $this->gudangB->id,
            'user_pengirim_id' => $this->user->id,
            'status' => 'dikirim',
            'tanggal_kirim' => now(),
        ]);

        StokTransferItem::create([
            'stok_transfer_id' => $transfer->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 2, // 2 * 250.000 = 500.000
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/wms/transfer/{$transfer->id}/terima");
        $response->assertStatus(200);

        $jurnalA = JurnalAkuntansi::with('akun')->where('cabang_id', $this->cabangA->id)
            ->where('referensi_tipe', StokTransfer::class)
            ->where('referensi_id', $transfer->id)
            ->get();

        $this->assertCount(2, $jurnalA);
        $this->assertEquals(500000, (float) $jurnalA->firstWhere('akun.kode', '190-01')?->debit);
        $this->assertEquals(500000, (float) $jurnalA->firstWhere('akun.kode', '130-01')?->kredit);

        $jurnalB = JurnalAkuntansi::with('akun')->where('cabang_id', $this->cabangB->id)
            ->where('referensi_tipe', StokTransfer::class)
            ->where('referensi_id', $transfer->id)
            ->get();

        $this->assertCount(2, $jurnalB);
        $this->assertEquals(500000, (float) $jurnalB->firstWhere('akun.kode', '130-01')?->debit);
        $this->assertEquals(500000, (float) $jurnalB->firstWhere('akun.kode', '190-01')?->kredit);
    }
}
