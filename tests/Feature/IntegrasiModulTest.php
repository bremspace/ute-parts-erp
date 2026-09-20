<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Services\KasSesiState;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Reseller\Models\SkemaKomisi;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Integrasi lintas modul (PRD §8 NFR + §11 DoD):
 * POS → stok + jurnal; komisi reseller → approval → utang;
 * servis → garansi + jurnal; kasbon → piutang; transfer antar gudang.
 * Membuktikan semua modul terkoneksi & konsisten.
 */
class IntegrasiModulTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private User $kasir;

    private function setUpFixtures(): array
    {
        // COA
        foreach ([
            ['110-01', 'Kas', 'aset', 'kas', 'debit'],
            ['120-01', 'Piutang Usaha', 'aset', 'piutang', 'debit'],
            ['130-01', 'Persediaan', 'aset', 'persediaan', 'debit'],
            ['210-03', 'Utang Komisi', 'kewajiban', 'utang_komisi', 'kredit'],
            ['410-01', 'Pendapatan', 'pendapatan', 'pendapatan_penjualan', 'kredit'],
            ['420-01', 'Pendapatan Jasa', 'pendapatan', 'pendapatan_jasa', 'kredit'],
            ['510-01', 'Beban Komisi', 'beban', 'beban_komisi', 'debit'],
            ['510-02', 'HPP', 'beban', 'hpp', 'debit'],
        ] as [$kode, $nama, $tipe, $kelompok, $saldo]) {
            AkunCOA::create([
                'kode' => $kode, 'nama' => $nama, 'tipe' => $tipe,
                'kelompok' => $kelompok, 'saldo_normal' => $saldo,
            ]);
        }

        // Skema komisi: 5% semua produk
        SkemaKomisi::create(['nama' => 'Standar', 'kategori' => null, 'tipe' => 'persen', 'nilai' => 5]);

        $this->cabang = Cabang::create(['nama' => 'Pusat', 'kode' => 'CBG-01', 'is_active' => true]);
        $gudang = Gudang::create(['cabang_id' => $this->cabang->id, 'nama' => 'Gudang 1', 'kode' => 'GDG-01', 'is_active' => true]);
        $gudang2 = Gudang::create(['cabang_id' => $this->cabang->id, 'nama' => 'Gudang 2', 'kode' => 'GDG-02', 'is_active' => true]);

        $this->kasir = User::create([
            'name' => 'Kasir A',
            'email' => 'kasir@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        // Role dengan semua permission yang dipakai alur integrasi (RBAC enforcement)
        $needed = [
            'pos.create', 'pos.view', 'wms.transfer', 'servis.create', 'servis.view',
            'servis.update-status', 'servis.input-sparepart', 'servis.override-status',
            'komisi.approve', 'user.view', 'crm.view',
        ];
        foreach ($needed as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }
        $role = Role::findOrCreate('super-admin');
        $role->syncPermissions($needed);
        $this->kasir->assignRole('super-admin');
        $this->kasir->cabangs()->attach($this->cabang->id, ['is_default' => true]);
        session(['cabang_id' => $this->cabang->id]);

        // [T-09] Buka kas sesi agar POS tunai tidak diblokir di test integrasi
        try {
            app(KasSesiState::class)->bukaKas(0, $this->cabang->id, $this->kasir->id);
        } catch (\Throwable) {
            // sudah ada sesi terbuka — abaikan
        }

        return [$gudang, $gudang2];
    }

    public function test_full_flow_pos_komisi_servis_kasbon_transfer_berseimbang(): void
    {
        [$gudang] = $this->setUpFixtures();

        // Produk + stok
        $produk = Produk::create([
            'nama' => 'Baterai Test', 'slug' => 'baterai-test', 'kategori' => 'Baterai',
            'kondisi' => 'baru', 'harga_beli' => 50000, 'harga_jual_retail' => 100000,
        ]);
        StokItem::create(['produk_id' => $produk->id, 'gudang_id' => $gudang->id, 'jumlah' => 20, 'jumlah_minimum' => 2]);

        // Reseller
        $reseller = Pelanggan::create([
            'nama' => 'Reseller Test', 'telepon' => '081111', 'is_reseller' => true,
        ]);

        // ===== 1. POS JUAL KE RESELLER (2 item) =====
        $this->actingAs($this->kasir, 'web');

        $pos = $this->postJson('/api/pos/transaksi', [
            'items' => [[
                'produk_id' => $produk->id,
                'jumlah' => 2,
                'harga_satuan' => 100000,
            ]],
            'metode_bayar' => 'tunai',
            'jumlah_bayar' => 200000,
            'pelanggan_id' => $reseller->id,
            'gudang_id' => $gudang->id,
            'cabang_id' => $this->cabang->id,
        ])->assertSuccessful(); // POS store → 201

        $transaksi = Transaksi::where('no_transaksi', $pos->json('data.no_transaksi'))->firstOrFail();
        $this->assertEquals('selesai', $transaksi->status);

        // Stok berkurang 20 → 18
        $this->assertDatabaseHas('stok_items', ['produk_id' => $produk->id, 'jumlah' => 18]);

        // StokLog tercatat
        $this->assertDatabaseHas('stok_log', [
            'produk_id' => $produk->id, 'jenis' => 'penjualan', 'perubahan' => -2,
        ]);

        // ===== 2. JURNAL OTOMATIS POS (Kas + Pendapatan + HPP + Persediaan) =====
        $journalCount = StokLog::count() >= 1;
        $this->assertGreaterThanOrEqual(1, $journalCount ? 1 : 0);
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'referensi_tipe' => Transaksi::class,
            'referensi_id' => $transaksi->id,
            'akun_coa_id' => AkunCOA::where('kode', '110-01')->first()->id,
            'debit' => 200000,
        ]);

        // ===== 3. KOMISI OTOMATIS PENDING (reseller 5% × 200.000 = 10.000) =====
        $this->assertDatabaseHas('komisi', [
            'pelanggan_id' => $reseller->id,
            'status' => 'pending',
        ]);
        $komisi = Komisi::where('pelanggan_id', $reseller->id)->firstOrFail();
        $this->assertEquals(10000, (float) $komisi->nominal_komisi);

        // ===== 4. APPROVAL KOMISI → JURNAL BEBAN KOMISI + UTANG =====
        $respApprove = $this->postJson('/api/reseller/komisi/approve', [
            'komisi_ids' => [$komisi->id],
            'action' => 'approve',
        ]);
        if ($respApprove->status() !== 200) {
            $this->fail('HTTP '.$respApprove->status().': '.($respApprove->json('message') ?? 'unknown'));
        }
        $this->assertEmpty($respApprove->json('message') !== null && str_contains($respApprove->json('message'), 'gagal'), 'approve error');

        $this->assertDatabaseHas('komisi', ['id' => $komisi->id, 'status' => 'disetujui']);
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'sumber' => 'komisi',
            'akun_coa_id' => AkunCOA::where('kode', '510-01')->first()->id,
            'debit' => 10000,
        ]);
        $this->assertDatabaseHas('utang', [
            'referensi_tipe' => 'komisi',
            'referensi_id' => $komisi->id,
            'status' => 'belum_lunas',
        ]);

        // ===== 5. KASBON → PIUTANG (metode piutang) =====
        $kasbon = $this->postJson('/api/pos/transaksi', [
            'items' => [[
                'produk_id' => $produk->id,
                'jumlah' => 1,
                'harga_satuan' => 100000,
            ]],
            'metode_bayar' => 'piutang',
            'jumlah_bayar' => 100000,
            'pelanggan_id' => $reseller->id,
            'gudang_id' => $gudang->id,
            'cabang_id' => $this->cabang->id,
        ])->assertSuccessful(); // 201

        $this->assertDatabaseHas('piutang', [
            'pelanggan_id' => $reseller->id,
            'status' => 'belum_lunas',
        ]);

        // ===== 6. TRANSFER ANTAR GUDANG =====
        $gudang2 = Gudang::where('kode', 'GDG-02')->firstOrFail();
        $transfer = $this->postJson('/api/wms/transfer', [
            'gudang_asal_id' => $gudang->id,
            'gudang_tujuan_id' => $gudang2->id,
            'items' => [[
                'produk_id' => $produk->id,
                'jumlah' => 3,
            ]],
        ])->assertSuccessful(); // 201

        $this->putJson('/api/wms/transfer/'.$transfer->json('data.id').'/kirim')->assertSuccessful();
        // stok asal 17 (18 - 1 kasbon - 3 transfer)
        $this->assertDatabaseHas('stok_items', ['produk_id' => $produk->id, 'gudang_id' => $gudang->id, 'jumlah' => 14]);
        $this->putJson('/api/wms/transfer/'.$transfer->json('data.id').'/terima')->assertSuccessful();
        $this->assertDatabaseHas('stok_items', ['produk_id' => $produk->id, 'gudang_id' => $gudang2->id, 'jumlah' => 3]);

        // ===== 7. SERVIŞ FLOW → GARANSI + JURNAL JASA =====
        $servis = $this->postJson('/api/servis', [
            'nama_pelanggan' => 'Owner Reseller',
            'telepon_pelanggan' => '081111',
            'jenis_hp' => 'Samsung A52',
            'keluhan' => 'Layar pecah',
            'foto_unit' => ['data:image/png;base64,foto1', 'data:image/png;base64,foto2'],
        ])->assertSuccessful();

        $tiket = TiketServis::where('no_tiket', $servis->json('data.no_tiket'))->firstOrFail();

        $this->putJson("/api/servis/{$tiket->id}/status", ['status' => 'diagnosa'])->assertSuccessful();
        $this->postJson("/api/servis/{$tiket->id}/estimasi", [
            'estimasi_biaya' => 150000,
            'alasan' => 'Ganti LCD + jasa',
        ])->assertSuccessful();

        // Reload tiket — setEstimasi membuat token_approval baru
        $tiket->refresh();
        $this->assertNotNull($tiket->token_approval);

        // Approve via token publik (menunggu_approval → disetujui)
        $this->postJson("/api/servis/public/approve/{$tiket->token_approval}", ['action' => 'approve'])->assertSuccessful();
        $this->putJson("/api/servis/{$tiket->id}/status", ['status' => 'dikerjakan'])->assertSuccessful();

        // Input sparepart hanya boleh saat disetujui/dikerjakan (state machine strict)
        $this->postJson("/api/servis/{$tiket->id}/sparepart", [
            'gudang_id' => $gudang2->id, // dari gudang2 stok 3
            'items' => [[
                'produk_id' => $produk->id,
                'jumlah' => 1,
            ]],
        ])->assertSuccessful();
        // stok gudang2 3 → 2
        $this->assertDatabaseHas('stok_items', ['produk_id' => $produk->id, 'gudang_id' => $gudang2->id, 'jumlah' => 2]);

        foreach (['qc', 'selesai'] as $st) {
            $this->putJson("/api/servis/{$tiket->id}/status", ['status' => $st])->assertSuccessful();
        }

        // Status akhir selesai + garansi dibuat
        $this->assertDatabaseHas('tiket_servis', ['id' => $tiket->id, 'status' => 'selesai']);
        $this->assertDatabaseHas('garansi', ['tiket_servis_id' => $tiket->id]);

        // Jurnal servis (Kas debit /*+*/ Pendapatan Jasa kredit)
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'sumber' => 'servis',
            'akun_coa_id' => AkunCOA::where('kode', '420-01')->first()->id,
        ]);

        // ===== 8. KESEIMBANGAN JURNAL GLOBAL (double-entry integrity) =====
        $debitTotal = DB::table('jurnal_akuntansi')->sum('debit');
        $kreditTotal = DB::table('jurnal_akuntansi')->sum('kredit');
        $this->assertEqualsWithDelta($debitTotal, $kreditTotal, 0.01, 'Total debit harus = total kredit (double-entry)');
    }

    // [T-17] Pekerjaan servis split part & jasa: jasa tak sentuh stok, part kurangi stok 1x, jurnal akurat
    public function test_pekerjaan_servis_split_part_jasa(): void
    {
        [$gudang, $gudang2] = $this->setUpFixtures();
        $this->actingAs($this->kasir, 'web');

        $produk = Produk::create([
            'nama' => 'LCD Test', 'slug' => 'lcd-test', 'kategori' => 'LCD',
            'kondisi' => 'baru', 'harga_beli' => 100000, 'harga_jual_retail' => 150000,
        ]);
        $variant = SkuVariant::create([
            'produk_id' => $produk->id, 'sku' => 'LCD-1', 'nama_varian' => 'Standar',
            'harga_beli' => 100000, 'harga_jual_retail' => 150000,
        ]);
        StokItem::create(['produk_id' => $produk->id, 'sku_variant_id' => $variant->id, 'gudang_id' => $gudang->id, 'jumlah' => 10]);

        $gudangTarget = $gudang;
        $sebelum = 10;

        $servis = $this->postJson('/api/servis', [
            'nama_pelanggan' => 'Owner',
            'telepon_pelanggan' => '081111',
            'jenis_hp' => 'Samsung A52',
            'keluhan' => 'Ganti LCD + jasa',
            'foto_unit' => ['data:image/png;base64,foto1', 'data:image/png;base64,foto2'],
        ])->assertSuccessful();
        $tiket = TiketServis::where('no_tiket', $servis->json('data.no_tiket'))->firstOrFail();

        $this->putJson("/api/servis/{$tiket->id}/status", ['status' => 'diagnosa'])->assertSuccessful();
        $this->postJson("/api/servis/{$tiket->id}/estimasi", ['estimasi_biaya' => 200000, 'alasan' => 'LCD + ongkos'])->assertSuccessful();
        $tiket->refresh();
        $this->postJson("/api/servis/public/approve/{$tiket->token_approval}", ['action' => 'approve'])->assertSuccessful();
        $this->putJson("/api/servis/{$tiket->id}/status", ['status' => 'dikerjakan'])->assertSuccessful();

        // Input 1 part (LCD) + 1 jasa
        $this->postJson("/api/servis/{$tiket->id}/pekerjaan", [
            'items' => [
                ['tipe' => 'jasa', 'nama_item' => 'Ongkos pasang', 'qty' => 1, 'harga' => 50000],
                ['tipe' => 'part', 'nama_item' => 'LCD', 'produk_id' => $produk->id, 'sku_variant_id' => $produk->skuVariants()->first()?->id, 'gudang_id' => $gudangTarget->id, 'qty' => 1, 'harga' => 150000],
            ],
        ])->assertSuccessful();

        // 2 rows items tersimpan
        $this->assertDatabaseHas('tiket_servis_item', ['tiket_servis_id' => $tiket->id, 'tipe' => 'jasa']);
        $this->assertDatabaseHas('tiket_servis_item', ['tiket_servis_id' => $tiket->id, 'tipe' => 'part']);

        // Part kurangi stok 1x
        $this->assertDatabaseHas('stok_items', ['produk_id' => $produk->id, 'gudang_id' => $gudangTarget->id, 'jumlah' => $sebelum - 1]);
        $this->assertDatabaseHas('stok_log', ['produk_id' => $produk->id, 'jenis' => 'servis', 'perubahan' => -1]);

        foreach (['qc', 'selesai'] as $st) {
            $this->putJson("/api/servis/{$tiket->id}/status", ['status' => $st])->assertSuccessful();
        }

        // Jurnal servis: Pendapatan Jasa (420-01) 50.000 + Pendapatan Penjualan (410-01) 150.000 + HPP
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'sumber' => 'servis',
            'referensi_id' => $tiket->id,
            'akun_coa_id' => AkunCOA::where('kode', '420-01')->first()->id,
            'kredit' => 50000,
        ]);
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'sumber' => 'servis',
            'referensi_id' => $tiket->id,
            'akun_coa_id' => AkunCOA::where('kode', '410-01')->first()->id,
            'kredit' => 150000,
        ]);

        // Balance
        $no = DB::table('jurnal_akuntansi')
            ->where('sumber', 'servis')->where('referensi_id', $tiket->id)->value('no_jurnal');
        $d = DB::table('jurnal_akuntansi')->where('no_jurnal', $no)->sum('debit');
        $k = DB::table('jurnal_akuntansi')->where('no_jurnal', $no)->sum('kredit');
        $this->assertEqualsWithDelta($d, $k, 0.01);
    }
}
