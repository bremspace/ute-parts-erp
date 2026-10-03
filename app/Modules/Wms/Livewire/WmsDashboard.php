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
    public string $activeTab = 'stok'; // stok, produk, transfer, opname, po, grn, retur

    public function mount(): void
    {
        // [FIX] Dukung deep-link ke tab via query param ?tab=...
        // (mis. widget "Stok Kritis" di dashboard → /app/wms?tab=produk).
        // Sebelumnya tab hanya bisa dipindah via $set('activeTab', ...) /
        // event wms-pindah-tab (in-page), jadi link eksternal seperti
        // /app/wms/produk tidak punya route → 404.
        $allowed = ['stok', 'produk', 'transfer', 'opname', 'po', 'grn', 'retur'];
        $tab = (string) request('tab', 'stok');

        $this->activeTab = in_array($tab, $allowed, true) ? $tab : 'stok';
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
