<?php

namespace App\Modules\Wms\Livewire;

use Livewire\Component;

/**
 * [F1-8 / S-01] Shell tipis — hanya tab navigation + host 5 tab component fokus
 * (StokTab, ProdukTab, TransferTab, OpnameTab, PoTab, GrnTab). Route, view name, dan
 * perilaku user tetap sama dengan sebelum split (behavior parity).
 */
class WmsDashboard extends Component
{
    public string $activeTab = 'stok'; // stok, produk, transfer, opname, po, grn

    public function render()
    {
        return view('modules.wms.livewire.wms-dashboard')
            ->layout('layouts.backoffice', ['header' => 'Gudang & Manajemen Stok (WMS)']);
    }
}
