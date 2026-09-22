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

        return TiketServis::with('garansi', 'jenisServis')
            ->where('pelanggan_id', $customer->id)
            ->latest()
            ->get();
    }

    public function getKomisiProperty()
    {
        $customer = auth('customer')->user();
        if (! $customer || ! $customer->is_reseller) {
            return collect();
        }

        return $customer->komisi()->latest()->get();
    }

    public function render()
    {
        return view('modules.marketplace.livewire.customer-account', [
            'customer' => auth('customer')->user(),
        ])->layout('layouts.marketplace', ['title' => 'Akun Saya']);
    }
}
