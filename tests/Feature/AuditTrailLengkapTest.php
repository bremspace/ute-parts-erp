<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Jobs\DepresiasiAsetJob;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Crm\Models\KampanyeBroadcast;
use App\Modules\Rbac\Models\AktivitasLog;
use App\Modules\Rbac\Models\AuditLog;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Services\AuditService;
use App\Modules\Servis\Models\ServisStatusLog;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Workflow\Models\ApprovalRequest;
use App\Modules\Workflow\Models\ApprovalRule;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * [B-10d / P1-3] Audit trail — model yang sebelumnya belum ter-log.
 *
 * Cakupan:
 * - create + update ter-log utk AkunCOA, StokLog, ApprovalRequest,
 *   ServisStatusLog, KampanyeBroadcast;
 * - snapshot TIDAK memuat field sensitif (pesan, segment, payload_json);
 * - update yang tidak menyentuh kolom dilog → tidak ada baris log kosong;
 * - event "jurnal diposting" tercatat 1× per post (bukan per baris jurnal);
 * - AuditService (audit_logs) menulis cabang_id + snapshot.
 *
 * [B-10d / P1-4] DepresiasiAsetJob terdaftar di scheduler bulanan.
 */
class AuditTrailLengkapTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Gudang $gudang;

    private Produk $produk;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create([
            'kode' => 'UTPB10', 'nama' => 'Cabang B-10d', 'alamat' => 'Jl. Audit',
            'telepon' => '0812000000', 'is_active' => true,
        ]);
        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id, 'nama' => 'Gudang B-10d',
            'kode' => 'GDG-B10', 'is_active' => true,
        ]);
        $this->produk = Produk::create([
            'nama' => 'LCD B-10d', 'slug' => 'lcd-b10d', 'kategori' => 'LCD',
            'kondisi' => 'baru', 'harga_beli' => 500000, 'harga_jual_retail' => 750000,
        ]);

        $this->user = User::create([
            'name' => 'Auditor B10', 'email' => 'auditor-b10@test.com',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);
        $this->actingAs($this->user, 'web');

        session(['cabang_id' => $this->cabang->id]);
    }

    private function logFor(string $tipe, int $id, string $event = 'created'): ?AktivitasLog
    {
        return AktivitasLog::where('subject_type', $tipe)
            ->where('subject_id', $id)
            ->where('event', $event)
            ->first();
    }

    // ===== 1. AKUN COA =====

    public function test_akun_coa_terlog_create_dan_update(): void
    {
        $akun = AkunCOA::create([
            'kode' => '700-01', 'nama' => 'Beban Sewa', 'tipe' => 'beban',
            'kelompok' => 'sewa', 'saldo_normal' => 'debit', 'is_active' => true,
        ]);

        $created = $this->logFor(AkunCOA::class, $akun->id);
        $this->assertNotNull($created, 'Create AkunCOA wajib ter-log');
        $this->assertSame('Akun COA dibuat', $created->description);
        $this->assertSame('700-01', $created->attribute_changes->get('attributes')['kode']);
        $this->assertSame('debit', $created->attribute_changes->get('attributes')['saldo_normal']);
        $this->assertSame($this->user->id, (int) $created->causer_id);

        $akun->update(['saldo_normal' => 'kredit', 'is_active' => false]);
        $updated = $this->logFor(AkunCOA::class, $akun->id, 'updated');
        $this->assertNotNull($updated, 'Update AkunCOA wajib ter-log');
        $this->assertSame('Akun COA diperbarui', $updated->description);
        $this->assertSame('debit', $updated->attribute_changes->get('old')['saldo_normal']);
        $this->assertSame('kredit', $updated->attribute_changes->get('attributes')['saldo_normal']);

        // Akun COA master global → tidak ada stamp cabang (tidak boleh dikarang)
        $this->assertNull($updated->cabang_id);
    }

    // ===== 2. STOK LOG (cabang diturunkan dari gudang) =====

    public function test_stok_log_terlog_dengan_cabang_dari_gudang(): void
    {
        $stok = StokLog::create([
            'gudang_id' => $this->gudang->id, 'produk_id' => $this->produk->id,
            'jenis' => 'pembelian', 'jumlah_sebelum' => 0, 'perubahan' => 5, 'jumlah_setelah' => 5,
        ]);

        $created = $this->logFor(StokLog::class, $stok->id);
        $this->assertNotNull($created, 'Create StokLog wajib ter-log');
        $this->assertSame('Log Stok dibuat', $created->description);
        $this->assertSame(5, (int) $created->attribute_changes->get('attributes')['perubahan']);

        // Tidak ada kolom cabang_id di stok_log → diturunkan lewat gudang_id
        $this->assertSame($this->cabang->id, (int) $created->cabang_id);
        $this->assertSame($this->cabang->id, (int) $created->getProperty('cabang_id'));

        $stok->update(['catatan' => 'koreksiinternal']);
        // `catatan` tidak ada di logOnly → tidak ada event update sama sekali
        $this->assertNull($this->logFor(StokLog::class, $stok->id, 'updated'));
    }

    // ===== 3. APPROVAL REQUEST (tanpa payload_json) =====

    public function test_approval_request_terlog_tanpa_payload_json(): void
    {
        $rule = ApprovalRule::create([
            'cabang_id' => $this->cabang->id, 'nama' => 'Komisi B-10d',
            'entity_type' => 'komisi_reseller', 'min_amount' => 1000000,
            'approver_role' => 'finance', 'is_active' => true,
        ]);

        $request = ApprovalRequest::create([
            'approval_rule_id' => $rule->id,
            'entity_type' => 'komisi_reseller',
            'entity_id' => 77,
            'cabang_id' => $this->cabang->id,
            'payload_json' => ['gaji' => 9000000, 'rekening' => '1234567890'],
            'status' => 'pending',
            'requested_by' => $this->user->id,
            'approver_role' => 'finance',
        ]);

        $created = $this->logFor(ApprovalRequest::class, $request->id);
        $this->assertNotNull($created, 'Create ApprovalRequest wajib ter-log');
        $this->assertSame('Permintaan Approval dibuat', $created->description);
        $this->assertSame($this->cabang->id, (int) $created->cabang_id);

        $atribut = $created->attribute_changes->get('attributes');
        $this->assertSame('pending', $atribut['status']);
        $this->assertArrayNotHasKey('payload_json', $atribut, 'payload_json tidak boleh masuk audit log');
        $this->assertStringNotContainsString('9000000', json_encode($created->attribute_changes));

        $request->update(['status' => 'disetujui', 'actioned_by' => $this->user->id]);
        $updated = $this->logFor(ApprovalRequest::class, $request->id, 'updated');
        $this->assertNotNull($updated, 'Update status approval wajib ter-log');
        $this->assertSame('pending', $updated->attribute_changes->get('old')['status']);
        $this->assertSame('disetujui', $updated->attribute_changes->get('attributes')['status']);
    }

    // ===== 4. SERVIS STATUS LOG =====

    public function test_servis_status_log_terlog(): void
    {
        $tiket = TiketServis::create([
            'no_tiket' => 'TS-B10-'.Str::random(4),
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Pelanggan B-10d',
            'telepon_pelanggan' => '0812000000',
            'jenis_hp' => 'iPhone 13',
            'keluhan' => 'Layar retak',
            'status' => 'diterima',
        ]);

        $log = ServisStatusLog::create([
            'tiket_servis_id' => $tiket->id,
            'status_dari' => 'diterima',
            'status_ke' => 'diperiksa',
            'user_id' => $this->user->id,
            'aksi' => 'transisi',
        ]);

        $created = $this->logFor(ServisStatusLog::class, $log->id);
        $this->assertNotNull($created, 'Create ServisStatusLog wajib ter-log');
        $this->assertSame('Riwayat Status Servis dibuat', $created->description);
        $this->assertSame('diperiksa', $created->attribute_changes->get('attributes')['status_ke']);
    }

    // ===== 5. KAMPANYE BROADCAST (tanpa pesan & segment) =====

    public function test_kampanye_broadcast_terlog_tanpa_pesan_dan_segment(): void
    {
        $kampanye = KampanyeBroadcast::create([
            'judul' => 'Promo Ramadan B-10d',
            'pesan' => 'Pelanggan setia UA {nama}, ada diskon 20%!',
            'channel' => 'wa',
            'segment' => [['tipe' => 'tier', 'nilai' => 'gold']],
            'status' => 'draft',
            'user_id' => $this->user->id,
        ]);

        $created = $this->logFor(KampanyeBroadcast::class, $kampanye->id);
        $this->assertNotNull($created, 'Create KampanyeBroadcast wajib ter-log');
        $this->assertSame('Kampanye Broadcast dibuat', $created->description);

        $atribut = $created->attribute_changes->get('attributes');
        $this->assertSame('Promo Ramadan B-10d', $atribut['judul']);
        $this->assertArrayNotHasKey('pesan', $atribut, 'isi pesan tidak boleh masuk audit log');
        $this->assertArrayNotHasKey('segment', $atribut, 'segment pelanggan tidak boleh masuk audit log');

        $kasar = json_encode($created->attribute_changes);
        $this->assertStringNotContainsString('diskon 20%', $kasar);
        $this->assertStringNotContainsString('gold', $kasar);

        $kampanye->update(['status' => 'terkirim', 'total_terkirim' => 120]);
        $updated = $this->logFor(KampanyeBroadcast::class, $kampanye->id, 'updated');
        $this->assertNotNull($updated, 'Update KampanyeBroadcast wajib ter-log');
        $this->assertSame('draft', $updated->attribute_changes->get('old')['status']);
        $this->assertSame(120, (int) $updated->attribute_changes->get('attributes')['total_terkirim']);
    }

    // ===== 6. TIDAK ADA LOG KOSONG =====

    public function test_update_tanpa_perubahan_tidak_membuat_log_kosong(): void
    {
        $akun = AkunCOA::create([
            'kode' => '701-01', 'nama' => 'Beban Listrik', 'tipe' => 'beban',
            'kelompok' => 'listrik', 'saldo_normal' => 'debit', 'is_active' => true,
        ]);

        $jumlahSebelum = AktivitasLog::where('subject_type', AkunCOA::class)
            ->where('subject_id', $akun->id)->count();

        // Simpan nilai identik → tidak ada kolom dilog yang berubah
        $akun->update(['nama' => 'Beban Listrik']);

        $jumlahSesudah = AktivitasLog::where('subject_type', AkunCOA::class)
            ->where('subject_id', $akun->id)->count();

        $this->assertSame($jumlahSebelum, $jumlahSesudah, 'dontLogEmptyChanges() harus menahan log kosong');
    }

    // ===== 7. EVENT JURNAL DIPOSTING (1× per post, bukan per baris) =====

    public function test_event_jurnal_diposting_terlog_satu_kali_per_post(): void
    {
        AkunCOA::create(['kode' => '110-01', 'nama' => 'Kas', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit']);
        AkunCOA::create(['kode' => '130-01', 'nama' => 'Persediaan', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit']);
        AkunCOA::create(['kode' => '410-01', 'nama' => 'Pendapatan', 'tipe' => 'pendapatan', 'kelompok' => 'penjualan', 'saldo_normal' => 'kredit']);
        AkunCOA::create(['kode' => '510-02', 'nama' => 'HPP', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit']);

        $baris = app(JurnalService::class)->post(
            noJurnal: 'JRL-B10-0001',
            tanggal: now(),
            sumber: 'pembelian',
            lines: [
                ['akun_kode' => '130-01', 'debit' => 100000, 'kredit' => 0],
                ['akun_kode' => '510-02', 'debit' => 0, 'kredit' => 0],
                ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 100000],
            ],
            deskripsi: 'Pembelian 3 baris',
            cabangId: $this->cabang->id,
            userId: $this->user->id,
        );

        $this->assertCount(3, $baris, 'Jurnal harus punya 3 baris');

        $header = JurnalHeader::where('no_jurnal', 'JRL-B10-0001')->firstOrFail();
        $logs = AktivitasLog::where('subject_type', JurnalHeader::class)
            ->where('subject_id', $header->id)
            ->get();

        // 1 event per POST — bukan 1 per baris jurnal
        $this->assertCount(1, $logs, 'Event jurnal posted harus 1× per post, bukan per baris');
        $this->assertSame('Jurnal JRL-B10-0001 diposting — 3 baris, total debit Rp 100.000', $logs->first()->description);

        $props = $logs->first()->properties->toArray();
        $this->assertSame('JRL-B10-0001', $props['no_jurnal']);
        $this->assertSame(3, (int) $props['jumlah_baris']);
        $this->assertSame(100000.0, (float) $props['total_debit']);
        $this->assertSame(100000.0, (float) $props['total_kredit']);
        $this->assertSame($this->cabang->id, (int) $props['cabang_id']);
        $this->assertSame('pembelian', $props['sumber']);
        $this->assertArrayHasKey('tanggal', $props);

        // Isi baris jurnal TIDAK ikut dilog
        $json = json_encode($props);
        $this->assertStringNotContainsString('akun_kode', $json);
        $this->assertStringNotContainsString('130-01', $json);
        $this->assertStringNotContainsString('Pembelian 3 baris', $json);
    }

    public function test_posting_gagal_tidak_membuat_event_jurnal_diposting(): void
    {
        AkunCOA::create(['kode' => '110-01', 'nama' => 'Kas', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit']);
        AkunCOA::create(['kode' => '410-01', 'nama' => 'Pendapatan', 'tipe' => 'pendapatan', 'kelompok' => 'penjualan', 'saldo_normal' => 'kredit']);

        $jumlahAwal = AktivitasLog::where('subject_type', JurnalHeader::class)->count();

        try {
            // Tidak balance → rollback, tidak boleh ada activity "diposting"
            app(JurnalService::class)->post(
                noJurnal: 'JRL-B10-GAGAL',
                tanggal: now(),
                sumber: 'manual',
                lines: [
                    ['akun_kode' => '110-01', 'debit' => 100000, 'kredit' => 0],
                    ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 90000],
                ],
                cabangId: $this->cabang->id,
            );
            $this->fail('Jurnal tidak balance wajib ditolak');
        } catch (\Exception $e) {
            $this->assertStringContainsString('tidak balance', $e->getMessage());
        }

        $this->assertSame(
            $jumlahAwal,
            AktivitasLog::where('subject_type', JurnalHeader::class)->count(),
            'Posting gagal tidak boleh menyisakan activity jurnal'
        );
    }

    // ===== 8. AUDIT_SERVICE (audit_logs) =====

    public function test_audit_service_menulis_cabang_id_dan_snapshot_dari_model(): void
    {
        $log = app(AuditService::class)->catat(
            entitas: 'Produk',
            aksi: 'update',
            entitasId: null,
            deskripsi: 'Ubah harga jual manual',
            sebelum: ['harga_jual_retail' => 750000],
            sesudah: $this->produk,
        );

        $log->refresh();
        $this->assertSame($this->cabang->id, (int) $log->cabang_id, 'audit_logs wajib punya cabang_id sesi');
        $this->assertSame($this->produk->id, (int) $log->entitas_id, 'entitas_id diambil dari key model');
        $this->assertIsArray($log->sebelum);
        $this->assertSame(750000.0, (float) $log->sebelum['harga_jual_retail']);
        $this->assertIsArray($log->sesudah);
        $this->assertSame('LCD B-10d', $log->sesudah['nama']);
    }

    public function test_audit_service_tidak_mengisi_snapshot_kosong(): void
    {
        $log = app(AuditService::class)->catat(
            entitas: 'StokItem',
            aksi: 'adjust',
            entitasId: 12,
            deskripsi: 'Penyesuaian stok tanpa snapshot',
        );

        $log->refresh();
        $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'aksi' => 'adjust']);
        $this->assertNull($log->sebelum, 'Snapshot kosong harus NULL, bukan {}');
        $this->assertNull($log->sesudah, 'Snapshot kosong harus NULL, bukan {}');
    }

    public function test_audit_service_membuang_field_sensitif_dari_snapshot(): void
    {
        $audit = app(AuditService::class);

        $log = $audit->catat(
            entitas: 'User',
            aksi: 'update',
            entitasId: $this->user->id,
            deskripsi: 'Ubah profil',
            sebelum: ['nama' => 'Auditor B10', 'password' => 'rahasia'],
            sesudah: ['nama' => 'Auditor B10 Baru', 'kunci_terenkripsi' => '1122-3344'],
        );

        $log->refresh();
        $this->assertArrayNotHasKey('password', $log->sebelum);
        $this->assertArrayNotHasKey('kunci_terenkripsi', $log->sesudah);
        $this->assertSame('Auditor B10 Baru', $log->sesudah['nama']);
        $this->assertStringNotContainsString('rahasia', json_encode($log->sebelum));
    }

    public function test_migrasi_audit_logs_punya_kolom_cabang_id(): void
    {
        $this->assertTrue(
            Schema::hasColumn('audit_logs', 'cabang_id'),
            'Migrasi B-10d wajib menambah kolom cabang_id ke audit_logs'
        );
        $this->assertNull(AuditLog::query()->latest('id')->first()?->cabang_id ?? null);
    }

    // ===== 9. P1-4: JADWAL DEPRESIASI =====

    public function test_depresiasi_aset_job_terjadwal_bulanan(): void
    {
        $events = $this->jadwalDepresiasi();

        $this->assertNotEmpty($events, 'DepresiasiAsetJob wajib terdaftar di scheduler');

        // RefreshDatabase sudah menjalankan artisan (migrate:fresh) sehingga
        // callback ArtisanStarting pernah dieksekusi; hitung pola UNIK supaya
        // test tidak rapuh kalau artisan di-boot lebih dari sekali.
        $pola = $events->map(fn (Event $e) => $e->expression.'|'.($e->withoutOverlapping ? 'wo' : 'tanpa-wo'))
            ->unique()->values();

        $this->assertCount(1, $pola, 'DepresiasiAsetJob hanya boleh punya SATU pola jadwal');
        // cron "minute hour day-of-month month *" → 01:30 tanggal 1 tiap bulan
        $this->assertSame('30 1 1 * *|wo', $pola->first(), 'Jadwal = bulanan tgl 1 jam 01:30 + withoutOverlapping()');
    }

    /**
     * Event schedule milik DepresiasiAsetJob (schedule di-register via
     * callback ArtisanStarting milik Laravel 11+).
     *
     * @return Collection<int, Event>
     */
    private function jadwalDepresiasi(): Collection
    {
        if (app(Schedule::class)->events() === []) {
            // Schedule belum pernah ter-resolve → boot console kernel sekali.
            Artisan::call('schedule:list');
        }

        return collect(app(Schedule::class)->events())
            ->filter(fn (Event $e) => $e->description === DepresiasiAsetJob::class)
            ->values();
    }
}
