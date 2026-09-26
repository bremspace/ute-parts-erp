<?php

namespace App\Modules\Marketplace\Livewire;

use App\Modules\Pos\Models\Transaksi;
use App\Modules\Servis\Models\TiketServis;
use Livewire\Component;

/**
 * Dashboard pelanggan marketplace (ACCOUNT-01..05):
 * riwayat order, tracking servis, komisi reseller.
 */
class CustomerAccount extends Component
{
    public string $activeTab = 'orders'; // orders, servis, komisi

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
                'jenis_hp', 'keluhan', 'status', 'token_approval', 'tanggal_terima',
                'tanggal_selesai', 'tanggal_diambil', 'created_at',
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
        ])->layout('layouts.marketplace', ['title' => 'Akun Saya']);
    }
}
