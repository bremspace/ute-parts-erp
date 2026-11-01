<?php

namespace App\Modules\Wms\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

/**
 * [F1-8 / S-01] Shell tipis — hanya tab navigation + host 5 tab component fokus
 * (StokTab, ProdukTab, TransferTab, OpnameTab, PoTab, GrnTab). Route, view name, dan
 * perilaku user tetap sama dengan sebelum split (behavior parity).
 */
class WmsDashboard extends Component
{
    public const VALID_TABS = [
        'produk', 'transfer', 'opname', 'po', 'grn', 'retur', 'stok', 'histori_po', 'monitoring_harga',
    ];

    public string $activeTab = 'produk'; // produk, transfer, opname, po, grn, retur, stok, histori_po, monitoring_harga

    public function mount(?string $tab = null): void
    {
        // [FIX] Dukung deep-link ke tab via route param, query param ?tab=... atau ?sub=...
        // Default langsung ke 'produk' (Master Produk & Stok terpadu)
        $tabParam = $tab ?: (string) request('tab', '');
        $sub = (string) request('sub', '');

        if (! $tabParam && $sub) {
            $tabParam = match ($sub) {
                'operasional' => 'transfer',
                'pengadaan' => 'po',
                default => 'produk',
            };
        }

        if (! $tabParam) {
            $tabParam = 'produk';
        }

        if ($tabParam === 'monitoring_harga' && ! isSuperAdminOrOwner() && ! auth()->user()?->can('pengaturan.manage')) {
            $tabParam = 'produk';
        }

        $this->activeTab = in_array($tabParam, self::VALID_TABS, true) ? $tabParam : 'produk';
    }

    #[On('wms-pindah-tab')]
    public function pindahTab(string $tab): void
    {
        if (! in_array($tab, self::VALID_TABS, true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->dispatch('tab-changed', tab: $tab, url: url('/app/wms/'.$tab));
    }

    public function render()
    {
        return view('modules.wms.livewire.wms-dashboard')
            ->layout('layouts.backoffice', ['header' => 'Gudang & Manajemen Stok (WMS)']);
    }
}
