<?php

namespace Tests\Feature;

use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Notifikasi\Jobs\KirimNotifikasiJob;
use App\Modules\Notifikasi\Models\NotifikasiKeluar;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Notifikasi\Services\WaTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WaTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(KonfigurasiService::class)->set('struk_config', ['nama_toko' => 'UTEParts Test']);
    }

    public function test_template_referensi_dirender_dengan_variabel_dan_nama_toko(): void
    {
        $hasil = app(WaTemplateService::class)->render('order_menunggu_pembayaran', [
            'nama' => 'Budi',
            'no_transaksi' => 'TRX-001',
            'total' => '1.250.000',
            'url_pesanan' => 'https://contoh.test/account',
        ]);

        $this->assertStringContainsString('Halo Budi', $hasil);
        $this->assertStringContainsString('TRX-001', $hasil);
        $this->assertStringContainsString('Rp 1.250.000', $hasil);
        $this->assertStringContainsString('UTEParts Test', $hasil);
    }

    public function test_hierarki_aktif_master_grup_dan_event(): void
    {
        $service = app(WaTemplateService::class);
        $cfg = $service->config();

        $cfg['master_enabled'] = false;
        $this->assertFalse($this->simpanDanCek($service, $cfg, 'order_menunggu_pembayaran'));

        $cfg = $service->config();
        $cfg['master_enabled'] = true;
        $cfg['groups']['transaksi']['enabled'] = false;
        $this->assertFalse($this->simpanDanCek($service, $cfg, 'order_menunggu_pembayaran'));

        $cfg = $service->config();
        $cfg['groups']['transaksi']['enabled'] = true;
        $cfg['events']['order_menunggu_pembayaran']['enabled'] = false;
        $this->assertFalse($this->simpanDanCek($service, $cfg, 'order_menunggu_pembayaran'));

        $cfg = $service->config();
        $cfg['events']['order_menunggu_pembayaran']['enabled'] = true;
        $this->assertTrue($this->simpanDanCek($service, $cfg, 'order_menunggu_pembayaran'));
    }

    public function test_event_nonaktif_tidak_diantrikan_tetap_tercatat_sebagai_terlewat(): void
    {
        Queue::fake();

        $service = app(WaTemplateService::class);
        $cfg = $service->config();
        $cfg['events']['order_menunggu_pembayaran']['enabled'] = false;
        $this->assertTrue($service->save($cfg)['ok']);

        $log = app(NotificationService::class)->kirim('wa', '081234567890', 'Invoice Pesanan', '', [
            'wa_template' => 'order_menunggu_pembayaran',
            'wa_vars' => [
                'nama' => 'Budi',
                'no_transaksi' => 'TRX-001',
                'total' => '1.250.000',
                'url_pesanan' => '/account',
            ],
        ]);

        $this->assertSame('terlewat', $log->status);
        $this->assertStringContainsString('DINONAKTIFKAN', (string) $log->error);
        $this->assertSame('order_menunggu_pembayaran', $log->payload['wa_template']);
        Queue::assertNothingPushed();
    }

    public function test_template_kustom_dipakai_sebagai_konten_notifikasi(): void
    {
        Queue::fake();

        $service = app(WaTemplateService::class);
        $cfg = $service->config();
        $cfg['events']['order_menunggu_pembayaran']['template'] = 'Halo {nama}, pesanan {no_transaksi} siap. - {nama_toko}';
        $this->assertTrue($service->save($cfg)['ok']);

        $log = app(NotificationService::class)->kirim('wa', '081234567890', 'Invoice Pesanan', '', [
            'wa_template' => 'order_menunggu_pembayaran',
            'wa_vars' => [
                'nama' => 'Siti',
                'no_transaksi' => 'TRX-009',
                'total' => '99.000',
                'url_pesanan' => '/account',
            ],
        ]);

        $this->assertSame('pending', $log->status);
        $this->assertSame('Halo Siti, pesanan TRX-009 siap. - UTEParts Test', $log->konten);
        $this->assertSame('kustom', $log->payload['wa_render_source']);
        Queue::assertPushed(KirimNotifikasiJob::class);
    }

    public function test_template_menolak_placeholder_yang_tidak_dikenal(): void
    {
        $service = app(WaTemplateService::class);
        $cfg = $service->config();
        $cfg['events']['order_menunggu_pembayaran']['template'] = 'Halo {nama}, kode {kode_rahasia}';

        $hasil = $service->save($cfg);

        $this->assertFalse($hasil['ok']);
        $this->assertArrayHasKey('order_menunggu_pembayaran', $hasil['errors']);
        $this->assertStringContainsString('{kode_rahasia}', $hasil['errors']['order_menunggu_pembayaran']);
        $this->assertSame([], NotifikasiKeluar::query()->get()->all());
    }

    public function test_override_status_servis_dipakai_bila_status_key_cocok(): void
    {
        $service = app(WaTemplateService::class);
        $cfg = $service->config();
        $cfg['events']['servis_status']['overrides']['selesai'] = 'Selesai {no_tiket} - ambil di {nama_cabang}';
        $this->assertTrue($service->save($cfg)['ok']);

        $ubah = $service->render('servis_status', [
            'nama' => 'Budi',
            'no_tiket' => 'SV-1',
            'jenis_hp' => 'iPhone',
            'status' => 'Perbaikan Selesai',
            'status_key' => 'selesai',
            'nama_cabang' => 'Cabang Pusat',
            'url_tracking' => '/tracking/x',
        ]);

        $umum = $service->render('servis_status', [
            'nama' => 'Budi',
            'no_tiket' => 'SV-1',
            'jenis_hp' => 'iPhone',
            'status' => 'Sedang Diperbaiki',
            'status_key' => 'dikerjakan',
            'nama_cabang' => 'Cabang Pusat',
            'url_tracking' => '/tracking/x',
        ]);

        $this->assertSame('Selesai SV-1 - ambil di Cabang Pusat', $ubah);
        $this->assertStringContainsString('Sedang Diperbaiki', $umum);
    }

    public function test_broadcast_memakai_pesan_kampanye_sebagai_template_default(): void
    {
        $hasil = app(WaTemplateService::class)->render('broadcast', [
            'nama' => 'Budi',
            'tier' => 'Gold',
            'pesan' => 'Promo Halo {nama}!',
        ], true);

        // Teks kampanye sudah di-replace sebelum masuk template, jadi apa adanya.
        $this->assertSame('Promo Halo {nama}!', $hasil);
    }

    public function test_struk_kasir_memakai_template_yang_sama_dengan_tombol_manual(): void
    {
        $struk = app(WaTemplateService::class)->renderStrukKasir([
            'no_transaksi' => 'TRX-2026-0100',
            'waktu' => '26/11/2026 13:45',
            'kasir' => 'Dewi',
            'pelanggan' => 'Budi',
            'items' => [
                ['nama' => '[Jasa] Ganti LCD', 'qty' => 1, 'satuan' => 'pcs', 'harga' => 250000, 'subtotal' => 250000],
            ],
            'subtotal' => 250000,
            'diskon' => 10000,
            'total' => 240000,
            'metode' => 'TUNAI',
            'cabang' => 'Cabang Pusat',
        ]);

        $this->assertStringContainsString('Halo kak *Budi*,', $struk);
        $this->assertStringContainsString('TRX-2026-0100', $struk);
        $this->assertStringContainsString('Ganti LCD', $struk);
        $this->assertStringContainsString('Diskon Kasir: -Rp 10.000', $struk);
        $this->assertStringContainsString('Rp 240.000', $struk);
        $this->assertStringContainsString('Cabang Pusat', $struk);
    }

    public function test_event_member_dan_internal_terdaftar_dengan_default_aktif(): void
    {
        $service = app(WaTemplateService::class);
        $cfg = $service->config();

        foreach ([
            'transaksi_kasir_selesai',
            'member_registrasi_pending',
            'member_registrasi_disetujui',
            'member_registrasi_ditolak',
            'member_kode_aktivasi',
            'member_aktivasi_berhasil',
            'member_reset_password_berhasil',
            'member_status_nonaktif',
            'approval_baru',
            'approval_pengingat',
            'stok_reorder',
            'backup_gagal',
            'depresiasi_gagal',
            'database_crash',
        ] as $event) {
            $this->assertNotNull($service->event($event), "Event {$event} harus terdaftar");
            $this->assertTrue($cfg['events'][$event]['enabled'], "Event {$event} default aktif");
        }

        $this->assertArrayHasKey('member', $service->groups());
        $this->assertArrayHasKey('approval_internal', $service->groups());
    }

    public function test_template_kode_aktivasi_tidak_mengandung_kata_sandi(): void
    {
        $hasil = app(WaTemplateService::class)->render('member_kode_aktivasi', [
            'nama' => 'Budi',
            'kode_aktivasi' => '123456',
            'berlaku_sampai' => '27 Nov 2026 13:45',
            'url_aktivasi' => 'https://contoh.test/aktivasi-akun',
        ]);

        $this->assertStringContainsString('123456', $hasil);
        $this->assertStringContainsString('27 Nov 2026 13:45', $hasil);
        $this->assertStringNotContainsString('password', strtolower($hasil));
    }

    private function simpanDanCek(WaTemplateService $service, array $cfg, string $event): bool
    {
        $this->assertTrue($service->save($cfg)['ok']);

        return $service->isEnabled($event);
    }
}
