<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Marketplace\Services\DuitkuPaymentProcessor;
use App\Modules\Notifikasi\Livewire\NotificationCenter;
use App\Modules\Notifikasi\Models\NotifikasiKeluar;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Workflow\Models\ApprovalRequest;
use App\Modules\Workflow\Models\ApprovalRule;
use App\Modules\Workflow\Services\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationAutoUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;

    protected User $kasir;

    protected User $approver;

    protected Pelanggan $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'kode' => 'CBG-TEST',
            'nama' => 'Cabang Test',
            'alamat' => 'Jl. Merdeka 1',
            'telepon' => '0812345678',
            'is_pusat' => true,
        ]);

        $roleKasir = Role::create(['name' => 'kasir']);
        $roleAdmin = Role::create(['name' => 'admin-toko']);

        $this->kasir = User::factory()->create();
        $this->kasir->assignRole($roleKasir);
        $this->kasir->cabangs()->attach($this->cabang->id, ['is_default' => true]);

        $this->approver = User::factory()->create();
        $this->approver->assignRole($roleAdmin);
        $this->approver->cabangs()->attach($this->cabang->id, ['is_default' => true]);

        session(['cabang_id' => $this->cabang->id]);

        $this->pelanggan = Pelanggan::create([
            'nama' => 'Budi Pembeli',
            'telepon' => '081299998888',
            'email' => 'budi@test.com',
            'is_reseller' => false,
        ]);

        // Setup COA standar agar prosesLunas jurnal tidak gagal
        AkunCOA::firstOrCreate(['kode' => '110-01'], ['nama' => 'Kas', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '120-02'], ['nama' => 'Kliring', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '410-03'], ['nama' => 'Pendapatan Web', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit']);
        AkunCOA::firstOrCreate(['kode' => '510-02'], ['nama' => 'HPP Web', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '130-01'], ['nama' => 'Persediaan', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit']);
    }

    public function test_notifikasi_menunggu_bayar_otomatis_selesai_saat_transaksi_lunas(): void
    {
        $transaksi = Transaksi::create([
            'no_transaksi' => 'MP-AUTO-001',
            'cabang_id' => $this->cabang->id,
            'pelanggan_id' => $this->pelanggan->id,
            'status' => 'menunggu_pembayaran',
            'total_akhir' => 50000,
        ]);

        $notifService = app(NotificationService::class);

        // 1. Notifikasi staf: menunggu pembayaran
        $notifStaf = $notifService->kirim(
            'inapp',
            null,
            "Pesanan Online Baru ({$transaksi->no_transaksi})",
            'Pesanan baru dari Budi Pembeli menunggu pembayaran.',
            [
                'transaksi_id' => $transaksi->id,
                'cabang_id' => $this->cabang->id,
                'target_role' => 'kasir',
                'kategori' => 'transaksi',
                'action_url' => '/app/pos/online-orders',
                'audience' => 'staf',
            ]
        );

        // 2. Notifikasi pelanggan: silakan bayar
        $notifPelanggan = $notifService->kirim(
            'inapp',
            $this->pelanggan->email,
            'Pesanan Berhasil Didaftarkan',
            "Pesanan {$transaksi->no_transaksi} telah terdaftar. Silakan selesaikan pembayaran agar barang segera disiapkan.",
            [
                'transaksi_id' => $transaksi->id,
                'pelanggan_id' => $this->pelanggan->id,
                'kategori' => 'transaksi',
                'action_url' => '/account',
                'audience' => 'pelanggan',
            ]
        );

        $this->assertNull($notifStaf->fresh()->read_at);
        $this->assertNull($notifPelanggan->fresh()->read_at);

        // 3. Pelunasan Duitku
        $processor = app(DuitkuPaymentProcessor::class);
        $res = $processor->prosesLunas($transaksi->no_transaksi, 50000, 'REF-AUTO-001');

        $this->assertSame('sukses', $res['kode']);
        $this->assertSame('lunas', $transaksi->fresh()->status);

        // 4. Verifikasi notifikasi lama otomatis diselesaikan (read_at terisi otomatis tanpa manual konfirmasi)
        $this->assertNotNull($notifStaf->fresh()->read_at);
        $this->assertNotNull($notifPelanggan->fresh()->read_at);
        $this->assertTrue($notifStaf->fresh()->payload['auto_resolved'] ?? false);
        $this->assertSame('lunas', $notifStaf->fresh()->payload['resolved_by_status'] ?? null);

        // 5. Verifikasi notifikasi baru pembayaran lunas telah diterbitkan
        $notifLunas = NotifikasiKeluar::where('payload->transaksi_id', $transaksi->id)
            ->where('judul', 'like', '%Pembayaran Pesanan%')
            ->first();

        $this->assertNotNull($notifLunas);
        $this->assertNull($notifLunas->read_at);
    }

    public function test_notification_center_auto_resolves_outdated_notifications_during_refresh(): void
    {
        $this->actingAs($this->kasir);

        $transaksi = Transaksi::create([
            'no_transaksi' => 'MP-AUTO-002',
            'cabang_id' => $this->cabang->id,
            'pelanggan_id' => $this->pelanggan->id,
            'status' => 'lunas', // Transaksi sudah lunas di backend
            'total_akhir' => 75000,
        ]);

        // Notifikasi lama yang belum terbaca
        $notif = NotifikasiKeluar::create([
            'tipe' => 'inapp',
            'kategori' => 'transaksi',
            'judul' => 'Pesanan Online Baru (MP-AUTO-002)',
            'konten' => 'Pesanan menunggu pembayaran.',
            'status' => 'terkirim',
            'cabang_id' => $this->cabang->id,
            'target_role' => 'kasir',
            'action_url' => '/app/pos/online-orders',
            'payload' => [
                'transaksi_id' => $transaksi->id,
                'target_role' => 'kasir',
                'audience' => 'staf',
            ],
            'read_at' => null,
        ]);

        $component = Livewire::test(NotificationCenter::class);
        $component->call('refreshCounts');

        // Notifikasi usang harus otomatis ter-resolve
        $this->assertNotNull($notif->fresh()->read_at);
        $this->assertSame(0, $component->get('unreadNotifCount'));
    }

    public function test_approval_notification_auto_resolved_when_actioned(): void
    {
        $rule = ApprovalRule::create([
            'nama_rule' => 'Approval Diskon',
            'entity_type' => 'diskon_kasir',
            'level' => 1,
            'approver_role' => 'admin-toko',
            'is_aktif' => true,
        ]);

        $request = ApprovalRequest::create([
            'approval_rule_id' => $rule->id,
            'entity_type' => 'diskon_kasir',
            'entity_id' => 101,
            'cabang_id' => $this->cabang->id,
            'status' => 'pending',
            'requested_by' => $this->kasir->id,
            'approver_role' => 'admin-toko',
            'payload_json' => ['amount' => 50000],
        ]);

        $notif = NotifikasiKeluar::create([
            'tipe' => 'inapp',
            'kategori' => 'approval',
            'judul' => 'Permintaan Approval Diskon #101',
            'konten' => 'Menunggu persetujuan Anda.',
            'status' => 'terkirim',
            'cabang_id' => $this->cabang->id,
            'target_role' => 'admin-toko',
            'payload' => [
                'approval_request_id' => $request->id,
                'entity_type' => 'diskon_kasir',
                'entity_id' => 101,
            ],
            'read_at' => null,
        ]);

        $service = app(ApprovalService::class);
        $service->proses($request->id, 'approved', $this->approver->id, 'Disetujui manajer');

        // Notifikasi approval otomatis terselesaikan
        $this->assertNotNull($notif->fresh()->read_at);
        $this->assertTrue($notif->fresh()->payload['auto_resolved'] ?? false);
    }
}
