<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Services\KasSesiState;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Models\TiketServisItem;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PosBayarServisIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CabangSeeder::class);
        $this->seed(AkunCoaSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::first();

        $this->user = User::create([
            'name' => 'Kasir POS Staging',
            'email' => 'kasir-staging@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user, 'web');
    }

    private function buatTiketSelesai(float $biaya = 350000): TiketServis
    {
        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-TEST-'.uniqid(),
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Budi Santoso',
            'telepon_pelanggan' => '081299887766',
            'jenis_hp' => 'Samsung S21',
            'tipe_kunci' => 'tidak_ada',
            'keluhan' => 'Ganti Konektor Charger',
            'status' => 'selesai',
            'status_pembayaran' => 'belum_bayar',
            'sumber' => 'walkin',
            'estimasi_biaya' => $biaya,
        ]);

        TiketServisItem::create([
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'jasa',
            'nama_item' => 'Jasa Solder Konektor',
            'qty' => 1,
            'harga' => 150000,
        ]);

        TiketServisItem::create([
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'part',
            'nama_item' => 'Konektor Type-C Original',
            'qty' => 1,
            'harga' => 200000,
        ]);

        return $tiket;
    }

    public function test_pembayaran_servis_di_pos_terintegrasi_dengan_kas_laci_dan_status_diambil(): void
    {
        // 1. Buka sesi kas POS
        app(KasSesiState::class)->bukaKas(
            cabangId: $this->cabang->id,
            userId: $this->user->id,
            saldoAwal: 500000,
            sumber: 'manual',
            akunSumberKode: '110-01'
        );

        $tiket = $this->buatTiketSelesai(350000);

        // 2. Eksekusi pembayaran servis di PosKasir
        Livewire::test(PosKasir::class)
            ->call('bukaBayarServisModal', $tiket->id)
            ->assertSet('selectedServisId', $tiket->id)
            ->assertSet('selectedServisDetail.total', 350000)
            ->set('servisMetodeBayar', 'tunai')
            ->set('servisJumlahBayar', 400000)
            ->call('hitungKembalianServis')
            ->assertSet('servisKembalian', 50000)
            ->set('servisUbahStatusDiambil', true)
            ->call('prosesBayarServis')
            ->assertSet('showBayarServisModal', false)
            ->assertSet('showReceiptModal', true)
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type'] ?? null) === 'success');

        // 3. Verifikasi tiket servis terupdate
        $tiket->refresh();
        $this->assertSame('lunas', $tiket->status_pembayaran);
        $this->assertSame('diambil', $tiket->status);
        $this->assertNotNull($tiket->tanggal_bayar);
        $this->assertNotNull($tiket->transaksi_id);
        $this->assertNotNull($tiket->no_jurnal_bayar);

        // 4. Verifikasi Transaksi POS terbuat
        $transaksi = Transaksi::find($tiket->transaksi_id);
        $this->assertNotNull($transaksi);
        $this->assertSame('servis', $transaksi->sumber);
        $this->assertEquals($tiket->id, $transaksi->tiket_servis_id);
        $this->assertEquals(350000, $transaksi->total_akhir);
        $this->assertEquals(400000, $transaksi->jumlah_bayar);
        $this->assertEquals(50000, $transaksi->kembalian);
        $this->assertSame('tunai', $transaksi->metode_bayar);

        // 5. Verifikasi Jurnal: Kas Laci (110-04) bertambah, Piutang Usaha (120-01) berkurang
        $jurnalKasLaci = JurnalAkuntansi::where('no_jurnal', $tiket->no_jurnal_bayar)
            ->whereHas('akun', fn ($q) => $q->where('kode', '110-04'))
            ->first();
        $jurnalPiutang = JurnalAkuntansi::where('no_jurnal', $tiket->no_jurnal_bayar)
            ->whereHas('akun', fn ($q) => $q->where('kode', '120-01'))
            ->first();

        $this->assertNotNull($jurnalKasLaci, 'Jurnal Kas Laci 110-04 harus ter-debit');
        $this->assertEquals(350000, $jurnalKasLaci->debit);
        $this->assertNotNull($jurnalPiutang, 'Jurnal Piutang Usaha 120-01 harus ter-kredit');
        $this->assertEquals(350000, $jurnalPiutang->kredit);

        // 6. Verifikasi Saldo Laci KasSesiState menghitung kas masuk transaksi servis
        $kasState = app(KasSesiState::class);
        $saldoSistem = $kasState->hitungSaldoSistem();
        // Saldo awal (500.000) + Transaksi Servis Tunai (350.000) = 850.000
        $this->assertEquals(850000, $saldoSistem);
    }

    public function test_pembayaran_tunai_servis_ditolak_jika_kas_belum_dibuka(): void
    {
        $tiket = $this->buatTiketSelesai(200000);

        // Kas belum dibuka
        Livewire::test(PosKasir::class)
            ->call('bukaBayarServisModal', $tiket->id)
            ->set('servisMetodeBayar', 'tunai')
            ->set('servisJumlahBayar', 200000)
            ->call('prosesBayarServis')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type'] ?? null) === 'error' && str_contains($params[0]['message'] ?? $params['message'] ?? '', 'Kas belum dibuka'))
            ->assertSet('showKasModal', true);

        $tiket->refresh();
        $this->assertSame('belum_bayar', $tiket->status_pembayaran);
    }
}
