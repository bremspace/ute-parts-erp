<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Livewire\ServisBoard;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Models\TiketServisItem;
use App\Modules\Servis\Services\ServisService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * [P2] Hardening tiga temuan audit fase 2:
 * T-04 permission `servis.payment` terpisah,
 * T-05 fallback `estimasi_biaya` tidak berlaku saat jasa eksplisit dibatalkan,
 * T-06 validasi akses cabang pada `terimaUnit`.
 */
class ServisP2HardeningTest extends TestCase
{
    use RefreshDatabase;

    private \Illuminate\Contracts\Auth\Authenticatable&User $user;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CabangSeeder::class);
        $this->seed(AkunCoaSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::first();

        $this->user = User::create([
            'name' => 'Admin Servis P2',
            'email' => 'admin-p2@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user, 'web');
    }

    private function buatTiketSelesai(float $estimasi = 350000): TiketServis
    {
        return TiketServis::create([
            'no_tiket' => 'SRV-P2-'.uniqid(),
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Pelanggan P2',
            'telepon_pelanggan' => '081255544433',
            'jenis_hp' => 'iPhone 12',
            'keluhan' => 'Ganti baterai',
            'status' => 'selesai',
            'status_pembayaran' => 'belum_bayar',
            'tanggal_selesai' => now(),
            'sumber' => 'walkin',
            'estimasi_biaya' => $estimasi,
        ]);
    }

    // --- T-04: `servis.payment` terpisah ---

    public function test_t04_api_bayar_ditolak_bagi_yang_hanya_punya_update_status(): void
    {
        // Simulasi pola lama: pemegang `servis.update-status` yang belum dapat
        // `servis.payment` — upgrade permission harus MENERUSKAN hak (decoupling).
        $teknisi = User::create([
            'name' => 'Teknisi Tanpa Payment',
            'email' => 'teknisi-nopay@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $teknisi->assignRole('teknisi');
        // Pembayaran masuk via ROLE — revoke langsung ke user tidak berpengaruh.
        Role::findByName('teknisi')->revokePermissionTo('servis.payment');
        $this->actingAs($teknisi, 'web');
        $teknisi->cabangs()->attach($this->cabang->id);
        $this->actingAs($teknisi, 'web');

        $this->assertTrue($teknisi->can('servis.update-status'));
        $this->assertFalse($teknisi->can('servis.payment'));

        $tiket = $this->buatTiketSelesai(150000);

        $this->postJson("/api/servis/{$tiket->id}/bayar", ['metode_pembayaran' => 'tunai'])
            ->assertStatus(403);
    }

    public function test_t04_api_bayar_lolos_bagi_pemegang_payment(): void
    {
        // kasir = role yang titik kerjanya memang pembayaran di meja kasir POS.
        $kasir = User::create([
            'name' => 'Kasir Servis P2',
            'email' => 'kasir-p2@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $kasir->assignRole('kasir');
        $kasir->cabangs()->attach($this->cabang->id);
        $this->actingAs($kasir, 'web');

        $this->assertTrue($kasir->can('servis.payment'));

        $tiket = $this->buatTiketSelesai(150000);
        TiketServisItem::create([
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'jasa',
            'nama_item' => 'Jasa Ganti Baterai',
            'qty' => 1,
            'harga' => 150000,
        ]);

        $this->postJson("/api/servis/{$tiket->id}/bayar", ['metode_pembayaran' => 'tunai'])
            ->assertStatus(200);

        $this->assertSame('lunas', $tiket->fresh()->status_pembayaran);
    }

    public function test_t04_seeder_menambahkan_servis_payment_ke_role_kunci(): void
    {
        foreach (['super-admin', 'owner', 'admin-toko', 'kasir', 'teknisi'] as $roleName) {
            $role = Role::findByName($roleName);

            $this->assertTrue(
                $role->hasPermissionTo('servis.payment'),
                "Role {$roleName} harus punya servis.payment"
            );
        }
    }

    public function test_t04_poskasir_gate_izinkan_pemegang_payment(): void
    {
        // PosKasir::prosesBayarServis dulu TANPA gate sama sekali; sekarang
        // dibguard `servis.payment`. Pemegang permission harus lolos gate —
        // dibuktikan lewat alert TAHAP BERIKUTNYA (pilih tiket), bukan alert
        // pembayaran. Distinguis kegagalan gate dari kegagalan flow lain.
        $kasir = User::create([
            'name' => 'Kasir Gate OK',
            'email' => 'kasir-gate@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $kasir->assignRole('kasir');
        $kasir->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($kasir, 'web');

        \Livewire\Livewire::test(\App\Modules\Pos\Livewire\PosKasir::class)
            ->call('prosesBayarServis')
            ->assertDispatched('alert', fn ($event, $params) => str_contains(
                (string) ($params[0]['message'] ?? ''),
                'Pilih tiket servis'
            ));
    }

    public function test_t04_board_prosesbayar_ditolak_tanpa_permission(): void
    {
        $teknisiNoPay = User::create([
            'name' => 'Teknisi Board No Pay',
            'email' => 'teknisi-board@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $teknisiNoPay->assignRole('teknisi');
        // Pembayaran masuk via ROLE — revoke langsung ke user tidak berpengaruh.
        Role::findByName('teknisi')->revokePermissionTo('servis.payment');
        $teknisiNoPay->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($teknisiNoPay, 'web');

        $tiket = $this->buatTiketSelesai(100000);

        Livewire::test(ServisBoard::class)
            ->set('bayarTiketId', $tiket->id)
            ->call('prosesBayar')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type'] ?? null) === 'error'
                && str_contains($params[0]['message'] ?? $params['message'] ?? '', 'pembayaran'));

        $this->assertSame('belum_bayar', $tiket->fresh()->status_pembayaran);
    }

    // --- T-05: fallback estimasi tidak jalan bila jasa eksplisit dibatalkan ---

    public function test_t05_jasa_eksplisit_dibatalkan_semua_tidak_ditagih_dari_estimasi(): void
    {
        $tiket = $this->buatTiketSelesai(350000);

        // Pekerjaan jasa pernah dibuat lalu dibatalkan semua (mis. goodwill /
        // pekerjaan tidak jadi) — overbill dulu: estimasi 350k tetap ditagih.
        // `dibatalkan_at` sengaja non-fillable (reversal terkontrol) → forceFill.
        TiketServisItem::create([
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'jasa',
            'nama_item' => 'Jasa Diagnosis',
            'qty' => 1,
            'harga' => 350000,
        ])->forceFill(['dibatalkan_at' => now()])->save();

        $this->assertTrue($tiket->punyaRiwayatJasaEksplisit());

        // Rincian di modal bayar: total = 0, bukan 350k dari estimasi.
        $rincian = $tiket->getRincianBiayaLengkap();
        $this->assertSame(0.0, (float) $rincian['total_jasa']);
        $this->assertSame(0.0, (float) $rincian['total']);

        // Pelunasan: jasa = 0 tanpa part → tidak ada jurnal bayar (nomor
        // jurnal tidak pernah dikaitkan; referensi perangkat tiket kosong).
        app(ServisService::class)->bayar($tiket, 'tunai', $this->user);

        $this->assertSame('lunas', $tiket->fresh()->status_pembayaran);
        $this->assertNull($tiket->fresh()->no_jurnal_bayar, 'Jasa dibatalkan semua TIDAK boleh memposting jurnal dari estimasi');
        $this->assertSame(0, JurnalAkuntansi::where('referensi_tipe', TiketServis::class)
            ->where('referensi_id', $tiket->id)
            ->count());
    }

    public function test_t05_tiket_legacy_tanpa_item_jasa_tetap_ditagih_estimasi(): void
    {
        // Tiket legacy: estimasi diisi langsung, TIDAK pernah ada item jasa →
        // fallback estimasi harus tetap berlaku (tidak ada regresi).
        $tiket = $this->buatTiketSelesai(250000);

        $this->assertFalse($tiket->punyaRiwayatJasaEksplisit());

        $rincian = $tiket->getRincianBiayaLengkap();
        $this->assertSame(250000.0, (float) $rincian['total_jasa']);
        $this->assertSame(250000.0, (float) $rincian['total']);

        app(ServisService::class)->bayar($tiket, 'tunai', $this->user);

        $this->assertSame('lunas', $tiket->fresh()->status_pembayaran);

        // Bayar memposting jurnal kas = estimasi (fallback legacy berjalan).
        $kasLines = JurnalAkuntansi::where('referensi_tipe', TiketServis::class)
            ->where('referensi_id', $tiket->id)
            ->whereHas('akun', fn ($q) => $q->whereIn('kode', ['110-01', '110-04']))
            ->get();
        $this->assertTrue($kasLines->isNotEmpty());

        $totalDebitKas = (float) $kasLines->sum('debit');
        $this->assertEquals(250000.0, round($totalDebitKas, 2));
    }

    public function test_t05_onselesai_jurnal_jasa_riil_bukan_estimasi(): void
    {
        // onSelesai: jasa AKTIF 200k ≠ estimasi 500k → jurnal harus pakai 200k.
        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-P2B-'.uniqid(),
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Pelanggan P2B',
            'jenis_hp' => 'Samsung S21',
            'keluhan' => 'Mati total',
            'status' => 'qc',
            'estimasi_biaya' => 500000,
            'pelanggan_id' => null,
        ]);

        TiketServisItem::create([
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'jasa',
            'nama_item' => 'Jasa Cek Board',
            'qty' => 1,
            'harga' => 200000,
        ]);

        app(ServisService::class)->updateStatus($tiket, 'selesai', $this->user);

        $jasaKredit = JurnalAkuntansi::where('referensi_tipe', TiketServis::class)
            ->where('referensi_id', $tiket->id)
            ->whereHas('akun', fn ($q) => $q->where('kode', '420-01'))
            ->get();
        $this->assertTrue($jasaKredit->isNotEmpty());
        $this->assertEquals(200000.0, round((float) $jasaKredit->sum('kredit'), 2), 'Jurnal 420-01 harus memakai jasa item 200k, bukan estimasi 500k');
    }

    // --- T-06: validasi cabang pada terimaUnit ---

    public function test_t06_staff_lintas_cabang_ditolak(): void
    {
        $cabangSelatan = Cabang::where('nama', 'Cabang Selatan')->firstOrFail();

        // Admin-toko cabang aktif = Cabang Pusat, TIDAK ditugaskan di Selatan.
        $this->user->syncRoles(['admin-toko']);
        $this->user->cabangs()->sync([$this->cabang->id]);

        $this->assertTrue($this->user->can('servis.create'));
        $this->assertFalse($this->user->bisaAksesCabang($cabangSelatan->id));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Anda tidak memiliki akses ke cabang ini');

        app(ServisService::class)->terimaUnit([
            'cabang_id' => $cabangSelatan->id,
            'nama_pelanggan' => 'Cross Branch',
            'jenis_hp' => 'Oppo A5',
            'keluhan' => 'Speaker rusak',
        ], $this->user);
    }

    public function test_t06_super_admin_lintas_cabang_lolos(): void
    {
        $cabangSelatan = Cabang::where('nama', 'Cabang Selatan')->firstOrFail();

        $tiket = app(ServisService::class)->terimaUnit([
            'cabang_id' => $cabangSelatan->id,
            'nama_pelanggan' => 'HQ Cross Branch',
            'jenis_hp' => 'Xiaomi 13',
            'keluhan' => 'Layar blank',
        ], $this->user);

        $this->assertSame($cabangSelatan->id, $tiket->cabang_id);
        $this->assertMatchesRegularExpression('/^SRV-C'.sprintf('%02d', $cabangSelatan->id).'-\d{8}-\d{4}$/', $tiket->no_tiket);
    }
}
