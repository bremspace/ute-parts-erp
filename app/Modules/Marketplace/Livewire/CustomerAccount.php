<?php

namespace App\Modules\Marketplace\Livewire;

use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Akunting\Services\PajakService;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Crm\Services\PelangganService;
use App\Modules\Notifikasi\Models\NotifikasiKeluar;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Services\StokDeductionService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Dashboard pelanggan marketplace (ACCOUNT-01..05):
 * riwayat order, tracking servis, komisi reseller, status membership & poin loyalty.
 */
class CustomerAccount extends Component
{
    public string $activeTab = 'orders'; // orders, servis, notifikasi, komisi, membership

    public function getOrdersProperty()
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            return collect();
        }

        return Transaksi::with(['items.produk', 'cabang', 'pengiriman'])
            ->where('pelanggan_id', $customer->id)
            ->latest()
            ->limit(20)
            ->get();
    }

    public function getNotifikasiProperty()
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            return collect();
        }

        return NotifikasiKeluar::forPelanggan($customer->id)
            ->latest()
            ->limit(20)
            ->get();
    }

    public function markNotifikasiRead(int $id): void
    {
        $notif = NotifikasiKeluar::find($id);
        if ($notif && $notif->pelanggan_id === auth('customer')->id()) {
            $notif->update(['read_at' => now()]);
        }
    }

    /**
     * Bayar pesanan online langsung (simulasi / konfirmasi pembayaran).
     */
    public function bayarPesanan(int $id): void
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            return;
        }

        $transaksi = Transaksi::with(['items.produk', 'pelanggan', 'cabang'])
            ->where('pelanggan_id', $customer->id)
            ->where('id', $id)
            ->firstOrFail();

        if ($transaksi->status !== 'menunggu_pembayaran') {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Pesanan ini sudah bukan status menunggu pembayaran.']);

            return;
        }

        DB::transaction(function () use ($transaksi, $customer) {
            $transaksi->update([
                'status' => 'lunas',
                'metode_bayar' => 'duitku_gateway',
                'jumlah_bayar' => (float) $transaksi->total_akhir,
                'paid_at' => now(),
            ]);

            // Potong stok produk
            $stokDeduction = app(StokDeductionService::class);
            $cabangId = $transaksi->cabang_id ?: Cabang::firstOrFail()->id;

            foreach ($transaksi->items as $item) {
                $stokDeduction->kurangiDariGudangTersedia(
                    $item->produk_id,
                    $item->sku_variant_id,
                    (int) $item->jumlah,
                    $cabangId,
                    'penjualan',
                    Transaksi::class,
                    $transaksi->id,
                    null,
                    "Order online consumer {$transaksi->no_transaksi}"
                );
            }

            // Jurnal akuntansi
            $jurnalService = app(JurnalService::class);
            $pajakService = app(PajakService::class);

            $totalAkhir = (float) $transaksi->total_akhir;
            $ppnNominal = max(0.0, (float) ($transaksi->ppn_nominal ?? 0));
            $dpp = $ppnNominal > 0 ? round($totalAkhir - $ppnNominal, 2) : $totalAkhir;
            $noJurnal = $jurnalService->generateNoJurnal('pos', $cabangId);

            $lines = [
                ['akun_kode' => '120-02', 'debit' => $totalAkhir, 'kredit' => 0],
                ['akun_kode' => '410-03', 'debit' => 0, 'kredit' => $dpp],
            ];
            foreach ($pajakService->jurnalLines($ppnNominal, $noJurnal, (int) $cabangId, 0) as $ppnLine) {
                $lines[] = $ppnLine;
            }

            $totalHpp = (float) $transaksi->items->sum(fn ($i) => (float) $i->hpp * (int) $i->jumlah);
            if ($totalHpp > 0) {
                $lines[] = ['akun_kode' => '510-02', 'debit' => $totalHpp, 'kredit' => 0];
                $lines[] = ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => $totalHpp];
            }

            $jurnalService->post(
                $noJurnal,
                now(),
                'pos',
                $lines,
                "Jurnal order online {$transaksi->no_transaksi} (lunas)",
                $cabangId,
                null,
                Transaksi::class,
                $transaksi->id
            );

            // Integrasi CRM: loyalty points
            app(PelangganService::class)->tambahBelanjaDanPoin($customer->id, $totalAkhir);

            // Notifikasi ke Backoffice (Staf Gudang & Kasir)
            $notif = app(NotificationService::class);
            $notif->kirim(
                'inapp',
                null,
                "Pembayaran Pesanan {$transaksi->no_transaksi} Diterima",
                "Pesanan online {$transaksi->no_transaksi} dari {$customer->nama} senilai Rp ".number_format($totalAkhir, 0, ',', '.').' telah lunas. Segera kemas dan siapkan suku cadang.',
                [
                    'transaksi_id' => $transaksi->id,
                    'cabang_id' => $cabangId,
                    'target_role' => 'staff-gudang',
                    'kategori' => 'transaksi',
                    'sumber' => 'marketplace',
                    'action_url' => '/app/pos/online-orders',
                    'audience' => 'staf',
                ]
            );

            // Notifikasi in-app ke Konsumen
            $notif->kirim(
                'inapp',
                $customer->email,
                'Pembayaran Berhasil',
                "Pembayaran untuk pesanan {$transaksi->no_transaksi} berhasil diterima. Tim toko kami segera memproses pesanan Anda.",
                [
                    'transaksi_id' => $transaksi->id,
                    'pelanggan_id' => $customer->id,
                    'kategori' => 'transaksi',
                    'action_url' => '/account',
                    'audience' => 'pelanggan',
                ]
            );
        });

        $this->dispatch('alert', ['type' => 'success', 'message' => "Pembayaran pesanan {$transaksi->no_transaksi} berhasil."]);
    }

    /**
     * Batalkan pesanan oleh konsumen jika belum dibayar.
     */
    public function batalkanPesanan(int $id): void
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            return;
        }

        $transaksi = Transaksi::where('pelanggan_id', $customer->id)
            ->where('id', $id)
            ->where('status', 'menunggu_pembayaran')
            ->firstOrFail();

        $transaksi->update([
            'status' => 'batal',
            'catatan' => trim(($transaksi->catatan ?? '').' [Dibatalkan oleh konsumen]'),
        ]);

        $this->dispatch('alert', ['type' => 'info', 'message' => "Pesanan {$transaksi->no_transaksi} telah dibatalkan."]);
    }

    public function getServisProperty()
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            return collect();
        }

        // [B-15c] Select eksplisit — `foto_unit` (base64, ratusan KB per tiket) tidak
        // pernah dirender di halaman pelanggan (hanya status/keluhan/garansi/link
        // tracking), tapi ikut ter-fetch karena `select *`.
        //
        // CATATAN: baris TIDAK dipotong dengan limit — kartu statistik di header
        // memakai `$servis->count()` dari koleksi yang sama, jadi limit akan
        // diam-diam mengubah angka. Scope per pelanggan (where pelanggan_id) tetap.
        return TiketServis::with('garansi', 'jenisServis')
            ->where('pelanggan_id', $customer->id)
            ->select([
                'id', 'no_tiket', 'pelanggan_id', 'cabang_id', 'jenis_servis_id', 'teknisi_id',
                'jenis_hp', 'keluhan', 'status', 'token_approval', 'estimasi_biaya', 'status_pembayaran',
                'tanggal_terima', 'tanggal_selesai', 'tanggal_diambil', 'created_at',
            ])
            ->latest()
            ->get();
    }

    public function getKomisiProperty()
    {
        $customer = auth('customer')->user();
        if (! $customer || ! $customer->is_reseller) {
            return collect();
        }

        // [B-15c] Select eksplisit (angka di header = sum koleksi ini → tidak boleh
        // dipotong; hanya kolom yang tidak dirender yang dibuang).
        return $customer->komisi()
            ->select(['id', 'no_komisi', 'status', 'keterangan', 'nominal_komisi', 'created_at'])
            ->latest()
            ->get();
    }

    public function getTiersProperty()
    {
        return TierMembership::where('is_active', true)
            ->orderBy('min_belanja_12bulan', 'asc')
            ->get();
    }

    public function getMembershipInfoProperty(): array
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            return [];
        }

        $customer->loadMissing('tierMembership');
        $currentTier = $customer->tierMembership;
        $totalBelanja = (float) ($customer->total_belanja_12bulan ?? 0);
        $tiers = $this->tiers;

        $nextTier = $tiers->first(fn ($t) => (float) $t->min_belanja_12bulan > $totalBelanja);

        $progress = 100;
        $kekurangan = 0.0;
        if ($nextTier) {
            $minCurrent = $currentTier ? (float) $currentTier->min_belanja_12bulan : 0.0;
            $minNext = (float) $nextTier->min_belanja_12bulan;
            $range = max(1.0, $minNext - $minCurrent);
            $progress = (int) min(100, max(0, round((($totalBelanja - $minCurrent) / $range) * 100)));
            $kekurangan = max(0.0, $minNext - $totalBelanja);
        }

        return [
            'currentTier' => $currentTier,
            'tierName' => $currentTier?->nama ?? ($customer->is_reseller ? 'Reseller' : 'Retail / Standar'),
            'diskonPersen' => (float) ($currentTier?->diskon_persen ?? 0),
            'poinMultiplier' => (float) ($currentTier?->poin_multiplier ?? 1.0),
            'totalBelanja' => $totalBelanja,
            'poinLoyalty' => (int) ($customer->poin_loyalty ?? 0),
            'nextTier' => $nextTier,
            'kekurangan' => $kekurangan,
            'progress' => $progress,
            'allTiers' => $tiers,
        ];
    }

    public function render()
    {
        // [B-15c] Computed property WAJIB di-pass eksplisit ke view — blade memakai
        // `$orders` / `$servis` / `$komisi` (bukan `$this->...`), sedangkan variabel
        // computed tidak otomatis tersedia di view (pola wajib project, AGENTS.md).
        return view('modules.marketplace.livewire.customer-account', [
            'customer' => auth('customer')->user(),
            'orders' => $this->orders,
            'servis' => $this->servis,
            'notifikasi' => $this->notifikasi,
            'komisi' => $this->komisi,
            'membershipInfo' => $this->membershipInfo,
        ])->layout('layouts.marketplace', ['title' => 'Akun Saya']);
    }
}
