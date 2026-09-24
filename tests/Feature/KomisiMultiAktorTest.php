<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\KpiMetric;
use App\Modules\Hr\Services\KpiService;
use App\Modules\Hr\Services\PayrollService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Reseller\Models\KomisiSkema;
use App\Modules\Reseller\Models\SkemaKomisi;
use App\Modules\Reseller\Models\SkemaKomisiReseller;
use App\Modules\Reseller\Services\KomisiService;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Models\Produk as WmsProduk;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KomisiMultiAktorTest extends TestCase
{
    use RefreshDatabase;

    protected KomisiService $komisi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CabangSeeder::class);
        $this->seed(AkunCoaSeeder::class);
        $this->komisi = app(KomisiService::class);
        session(['cabang_id' => 1]);
    }

    private function buatUser(string $email): User
    {
        return User::create([
            'name' => 'User '.$email,
            'email' => $email,
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }

    private function buatKaryawan(User $user, string $jabatan, float $pokok = 4000000): Karyawan
    {
        return Karyawan::create([
            'user_id' => $user->id,
            'nik' => 'NIK-'.uniqid(),
            'nama' => 'Karyawan '.$jabatan,
            'jabatan' => $jabatan,
            'cabang_id' => 1,
            'tgl_masuk' => '2024-01-01',
            'gaji_pokok' => $pokok,
            'status_aktif' => true,
        ]);
    }

    private function buatProduk(string $nama, string $kategori): WmsProduk
    {
        return WmsProduk::create([
            'nama' => $nama,
            'slug' => str()->slug($nama).'-'.uniqid(),
            'kategori' => $kategori,
            'kondisi' => 'baru',
            'harga_beli' => 50000,
            'harga_jual_retail' => 100000,
        ]);
    }

    private function buatTransaksi(Pelanggan $pelanggan, array $items): Transaksi
    {
        $subtotal = array_sum($items);
        $transaksi = Transaksi::create([
            'no_transaksi' => 'KS-'.uniqid(),
            'cabang_id' => 1,
            'pelanggan_id' => $pelanggan->id,
            'sumber' => 'kasir',
            'subtotal' => $subtotal,
            'total_akhir' => $subtotal,
            'metode_bayar' => 'tunai',
            'status' => 'lunas',
        ]);

        foreach ($items as $nama => $harga) {
            $produk = $this->buatProduk($nama, $nama);
            TransaksiItem::create([
                'transaksi_id' => $transaksi->id,
                'produk_id' => $produk->id,
                'jumlah' => 1,
                'harga_satuan' => $harga,
                'subtotal' => $harga,
                'hpp' => 50000,
            ]);
        }

        return $transaksi;
    }

    private function buatReseller(string $nama = 'Reseller A'): Pelanggan
    {
        return Pelanggan::create([
            'nama' => $nama,
            'telepon' => '0812'.rand(1000000, 9999999),
            'is_reseller' => true,
        ]);
    }

    private function buatAgen(): Pelanggan
    {
        return Pelanggan::create([
            'nama' => 'Agen X',
            'telepon' => '0813'.rand(1000000, 9999999),
            'kode_agen' => 'AGN-'.uniqid(),
        ]);
    }

    private function buatRule(
        string $aktorTipe,
        string $triggerTipe,
        string $tipe,
        float $nilai,
        ?int $aktorId = null,
        ?string $kategori = null,
        float $minAmount = 0
    ): KomisiSkema {
        return KomisiSkema::create([
            'nama' => sprintf('%s %s %s', $aktorTipe, $triggerTipe, $tipe),
            'aktor_tipe' => $aktorTipe,
            'aktor_id' => $aktorId,
            'trigger_tipe' => $triggerTipe,
            'kategori' => $kategori,
            'tipe' => $tipe,
            'nilai' => $nilai,
            'min_amount' => $minAmount,
            'cabang_id' => null,
            'is_aktif' => true,
        ]);
    }

    public function test_migrasi_skema_reseller_menjadi_rule_baru(): void
    {
        $reseller = $this->buatReseller();
        SkemaKomisi::create(['nama' => 'Default', 'kategori' => null, 'tipe' => 'persen', 'nilai' => 5]);
        SkemaKomisiReseller::create([
            'pelanggan_id' => $reseller->id,
            'kategori' => 'LCD',
            'tipe' => 'persen',
            'nilai' => 10,
        ]);

        $jumlah = $this->komisi->migrasiSkemaResellerKeRuleBaru();

        $this->assertEquals(2, $jumlah);
        $this->assertDatabaseHas('komisi_skema', [
            'aktor_tipe' => 'reseller',
            'trigger_tipe' => 'penjualan',
            'aktor_id' => null,
            'kategori' => null,
            'tipe' => 'persen',
            'nilai' => 5,
        ]);
        $this->assertDatabaseHas('komisi_skema', [
            'aktor_tipe' => 'reseller',
            'trigger_tipe' => 'penjualan',
            'aktor_id' => $reseller->id,
            'kategori' => 'LCD',
            'tipe' => 'persen',
            'nilai' => 10,
        ]);

        // Idempotent: panggil ulang → tidak menambah rule
        $this->assertEquals(0, $this->komisi->migrasiSkemaResellerKeRuleBaru());
    }

    public function test_paritas_engine_baru_sama_dengan_alur_lama(): void
    {
        $reseller = $this->buatReseller();
        SkemaKomisi::create(['nama' => 'Default', 'kategori' => null, 'tipe' => 'persen', 'nilai' => 5]);
        SkemaKomisiReseller::create([
            'pelanggan_id' => $reseller->id,
            'kategori' => 'LCD',
            'tipe' => 'persen',
            'nilai' => 10,
        ]);
        $this->komisi->migrasiSkemaResellerKeRuleBaru();

        // Transaksi: LCD 100.000 (override 10%) + Aki 50.000 (default 5%) = 12.500
        $transaksi = $this->buatTransaksi($reseller, ['LCD' => 100000, 'Aki' => 50000]);

        $legacy = $this->komisi->hitungKomisi($transaksi, $reseller);
        $engine = $this->komisi->hitungKomisiMultiAktor('penjualan', ['transaksi_id' => $transaksi->id]);

        $this->assertNotNull($legacy);
        $this->assertEquals(12500.0, (float) $legacy->nominal_komisi);

        // Paritas nominal: engine mencatat per rule (10.000 override LCD + 2.500 default Aki),
        // alur lama satu baris total 12.500 — nilai total harus identik.
        $this->assertCount(2, $engine);
        $totalEngine = array_sum(array_map(fn ($k) => (float) $k->nominal_komisi, $engine));
        $this->assertEquals(12500.0, $totalEngine);
        $this->assertEquals([10000.0, 2500.0], array_map(fn ($k) => (float) $k->nominal_komisi, $engine));

        foreach ($engine as $baris) {
            $this->assertEquals($reseller->id, $baris->pelanggan_id);
            $this->assertEquals('reseller', $baris->aktor_tipe);
            $this->assertNotNull($baris->komisi_skema_id);
        }
    }

    public function test_rule_match_penjualan_multi_aktor(): void
    {
        // Satu transaksi: pembeli reseller + pakai referral (agen) + lead source (karyawan marketing)
        $agen = $this->buatAgen();
        $reseller = $this->buatReseller('Reseller via Lead');
        $reseller->update(['referral_kode' => $agen->kode_agen]);

        $marketing = $this->buatKaryawan($this->buatUser('mkt@test.dev'), 'marketing');
        Lead::create([
            'cabang_id' => 1,
            'sumber' => 'website',
            'stage' => 'won',
            'nama' => 'Lead Reseller',
            'telepon' => '0815'.rand(1000000, 9999999),
            'nilai_estimasi' => 100000,
            'assigned_to' => $marketing->user_id,
            'pelanggan_id' => $reseller->id,
            'won_at' => now(),
        ]);

        $this->buatRule('reseller', 'penjualan', 'persen', 3);
        $this->buatRule('agen', 'penjualan', 'persen', 5);
        $this->buatRule('karyawan', 'penjualan', 'persen', 2);

        $transaksi = $this->buatTransaksi($reseller, ['LCD' => 100000]);

        $hasil = $this->komisi->hitungKomisiMultiAktor('penjualan', ['transaksi_id' => $transaksi->id]);

        $this->assertCount(3, $hasil);

        $perAktor = collect($hasil)->mapWithKeys(fn ($k) => [$k->aktor_tipe => $k]);
        $this->assertEquals(3000.0, (float) $perAktor['reseller']->nominal_komisi);
        $this->assertEquals(5000.0, (float) $perAktor['agen']->nominal_komisi);
        $this->assertEquals(2000.0, (float) $perAktor['karyawan']->nominal_komisi);

        // Agen → pelanggan_id mengarah ke pelanggan agen (pemilik kode) utk payout existing flow
        $this->assertEquals($agen->id, $perAktor['agen']->pelanggan_id);
        // Karyawan → tanpa pelanggan (masuk payroll via aktor_id)
        $this->assertNull($perAktor['karyawan']->pelanggan_id);
        $this->assertEquals($marketing->id, $perAktor['karyawan']->aktor_id);
    }

    public function test_idempotent_per_trigger_rule(): void
    {
        $reseller = $this->buatReseller();
        $this->buatRule('reseller', 'penjualan', 'persen', 5);

        $transaksi = $this->buatTransaksi($reseller, ['LCD' => 100000]);

        $pertama = $this->komisi->hitungKomisiMultiAktor('penjualan', ['transaksi_id' => $transaksi->id]);
        $kedua = $this->komisi->hitungKomisiMultiAktor('penjualan', ['transaksi_id' => $transaksi->id]);

        $this->assertCount(1, $pertama);
        $this->assertCount(1, $kedua);
        $this->assertSame($pertama[0]->id, $kedua[0]->id);
        $this->assertEquals(1, Komisi::where('transaksi_id', $transaksi->id)->count());
    }

    public function test_tiket_servis_trigger_teknisi(): void
    {
        $teknisi = $this->buatKaryawan($this->buatUser('tek@test.dev'), 'teknisi');
        $this->buatRule('karyawan', 'tiket_servis', 'nominal', 10000);

        $tiket = TiketServis::create([
            'no_tiket' => 'TS-KMS-'.uniqid(),
            'cabang_id' => 1,
            'teknisi_id' => $teknisi->user_id,
            'jenis_hp' => 'iPhone 14',
            'tipe_kunci' => 'tidak_ada',
            'keluhan' => 'Kerusakan layar',
            'kondisi_fisik' => ['kerusakan' => 'layar retak'],
            'foto_unit' => [],
            'status' => 'selesai',
            'estimasi_biaya' => 200000,
            'tanggal_terima' => now()->toDateString(),
            'tanggal_selesai' => now(),
        ]);

        $hasil = $this->komisi->hitungKomisiMultiAktor('tiket_servis', ['tiket_servis_id' => $tiket->id]);
        // Idempotent
        $this->komisi->hitungKomisiMultiAktor('tiket_servis', ['tiket_servis_id' => $tiket->id]);

        $this->assertCount(1, $hasil);
        $this->assertEquals(10000.0, (float) $hasil[0]->nominal_komisi);
        $this->assertEquals('karyawan', $hasil[0]->aktor_tipe);
        $this->assertEquals($teknisi->id, $hasil[0]->aktor_id);
        $this->assertEquals(1, Komisi::where('tiket_servis_id', $tiket->id)->count());
    }

    public function test_lead_won_trigger_karyawan(): void
    {
        $marketing = $this->buatKaryawan($this->buatUser('mkt2@test.dev'), 'marketing');
        $this->buatRule('karyawan', 'lead_won', 'nominal', 50000);

        $lead = Lead::create([
            'cabang_id' => 1,
            'sumber' => 'website',
            'stage' => 'won',
            'nama' => 'Lead Won X',
            'telepon' => '0816'.rand(1000000, 9999999),
            'nilai_estimasi' => 1000000,
            'assigned_to' => $marketing->user_id,
            'won_at' => now(),
        ]);

        $hasil = $this->komisi->hitungKomisiMultiAktor('lead_won', ['lead_id' => $lead->id]);
        $this->komisi->hitungKomisiMultiAktor('lead_won', ['lead_id' => $lead->id]);

        $this->assertCount(1, $hasil);
        $this->assertEquals(50000.0, (float) $hasil[0]->nominal_komisi);
        $this->assertEquals($marketing->id, $hasil[0]->aktor_id);
        $this->assertEquals($lead->id, $hasil[0]->lead_id);
        $this->assertEquals(1, Komisi::where('lead_id', $lead->id)->count());
    }

    public function test_target_kpi_trigger(): void
    {
        $teknisi = $this->buatKaryawan($this->buatUser('tek2@test.dev'), 'teknisi');
        $metric = KpiMetric::create([
            'kode' => 'tiket_selesai_kms',
            'nama' => 'Tiket Selesai (KMS)',
            'rumus' => 'tiket_selesai',
            'target' => 1,
            'satuan' => 'tiket',
            'periode' => 'bulanan',
            'is_aktif' => true,
        ]);

        TiketServis::create([
            'no_tiket' => 'TS-KPI-KMS-'.uniqid(),
            'cabang_id' => 1,
            'teknisi_id' => $teknisi->user_id,
            'jenis_hp' => 'iPhone 14',
            'tipe_kunci' => 'tidak_ada',
            'keluhan' => 'Baterai',
            'kondisi_fisik' => ['kerusakan' => 'baterai'],
            'foto_unit' => [],
            'status' => 'selesai',
            'estimasi_biaya' => 150000,
            'tanggal_terima' => now()->toDateString(),
            'tanggal_selesai' => now(),
        ]);

        app(KpiService::class)->hitung(now()->format('Y-m'));

        $this->buatRule('karyawan', 'target_kpi', 'nominal', 100000, null, (string) $metric->id, 0);

        $periode = now()->format('Y-m');
        $hasil = $this->komisi->hitungKomisiMultiAktor('target_kpi', ['periode' => $periode]);
        $this->komisi->hitungKomisiMultiAktor('target_kpi', ['periode' => $periode]);

        $this->assertCount(1, $hasil);
        $this->assertEquals(100000.0, (float) $hasil[0]->nominal_komisi);
        $this->assertEquals($teknisi->id, $hasil[0]->aktor_id);
        $this->assertEquals(1, Komisi::where('aktor_tipe', 'karyawan')->where('komisi_skema_id', $hasil[0]->komisi_skema_id)->count());
    }

    public function test_komisi_internal_masuk_payroll(): void
    {
        $marketing = $this->buatKaryawan($this->buatUser('mkt3@test.dev'), 'marketing', 4000000);

        // Komisi internal (lead_won) + komisi reseller lama yg TIDAK masuk payroll karyawan
        Komisi::create([
            'no_komisi' => 'KMS-TST-0001',
            'aktor_tipe' => 'karyawan',
            'aktor_id' => $marketing->id,
            'komisi_skema_id' => null,
            'jumlah_transaksi' => 1000000,
            'nominal_komisi' => 75000,
            'status' => 'pending',
            'keterangan' => 'Komisi lead won',
        ]);
        Komisi::create([
            'no_komisi' => 'KMS-TST-0002',
            'pelanggan_id' => $this->buatReseller('R')->id,
            'aktor_tipe' => 'reseller',
            'aktor_id' => null,
            'jumlah_transaksi' => 100000,
            'nominal_komisi' => 5000,
            'status' => 'pending',
            'keterangan' => 'Komisi reseller',
        ]);

        $periode = now()->format('Y-m');
        app(PayrollService::class)->hitungDraft($periode);

        $this->assertDatabaseHas('payroll_slip', [
            'karyawan_id' => $marketing->id,
            'total_komisi' => 75000,
            'total_gaji' => 4000000 + 75000,
        ]);
    }
}
