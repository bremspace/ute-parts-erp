<?php

namespace App\Modules\Marketplace\Livewire;

use App\Modules\Crm\Models\TierMembership;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Servis\Models\TiketServis;
use Livewire\Component;

/**
 * Dashboard pelanggan marketplace (ACCOUNT-01..05):
 * riwayat order, tracking servis, komisi reseller, status membership & poin loyalty.
 */
class CustomerAccount extends Component
{
    public string $activeTab = 'orders'; // orders, servis, komisi, membership

    public function getOrdersProperty()
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            return collect();
        }

        return Transaksi::with(['items.produk', 'cabang'])
            ->where('pelanggan_id', $customer->id)
            ->latest()
            ->limit(20)
            ->get();
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
            'komisi' => $this->komisi,
            'membershipInfo' => $this->membershipInfo,
        ])->layout('layouts.marketplace', ['title' => 'Akun Saya']);
    }
}
