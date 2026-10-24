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
    public string $activeTab = 'produk'; // produk, transfer, opname, po, grn, retur, stok, histori_po

    public function mount(): void
    {
        // [FIX] Dukung deep-link ke tab via query param ?tab=...
        // Default langsung ke 'produk' (Master Produk & Stok terpadu)
        $allowed = ['produk', 'stok', 'transfer', 'opname', 'po', 'grn', 'retur', 'histori_po'];
        $tab = (string) request('tab', 'produk');

        $this->activeTab = in_array($tab, $allowed, true) ? $tab : 'produk';
    }

    #[On('wms-pindah-tab')]
    public function pindahTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function render()
    {
        return view('modules.wms.livewire.wms-dashboard')
            ->layout('layouts.backoffice', ['header' => 'Gudang & Manajemen Stok (WMS)']);
    }
}
