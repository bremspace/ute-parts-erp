<?php

namespace App\Modules\Marketplace\Livewire;

use App\Modules\Marketplace\Services\WebKonsumenService;
use App\Modules\Servis\Models\JenisServis;
use App\Modules\Wms\Models\Produk;
use Livewire\Component;

class EstimasiBiayaServisPage extends Component
{
    public ?int $selectedJenisServisId = null;

    public string $gadgetModel = '';

    public ?int $selectedPartId = null;

    public function mount(): void
    {
        $firstJenis = JenisServis::where('is_active', true)->first();
        if ($firstJenis) {
            $this->selectedJenisServisId = $firstJenis->id;
        }
    }

    public function selectJenis(int $id): void
    {
        $this->selectedJenisServisId = $id;
        $this->selectedPartId = null;
    }

    public function render()
    {
        $webConfig = app(WebKonsumenService::class)->getAll();
        $listJenis = JenisServis::where('is_active', true)->orderBy('nama')->get();

        $activeJenis = $this->selectedJenisServisId
            ? JenisServis::find($this->selectedJenisServisId)
            : null;

        // Query kandidat suku cadang berdasarkan nama jenis servis atau model gadget
        $partCandidates = collect();
        if ($activeJenis) {
            $query = Produk::where('is_active', true);
            if (! empty($this->gadgetModel)) {
                $query->where('nama', 'like', '%'.$this->gadgetModel.'%');
            } elseif ($activeJenis->kategori) {
                $query->where(function ($q) use ($activeJenis) {
                    $q->where('nama', 'like', '%'.$activeJenis->kategori.'%')
                        ->orWhere('nama', 'like', '%'.explode(' ', $activeJenis->nama)[0].'%');
                });
            }
            $partCandidates = $query->limit(8)->get();
        }

        $selectedPart = $this->selectedPartId ? Produk::find($this->selectedPartId) : null;

        $biayaJasa = (float) ($activeJenis?->biaya_jasa ?? 0);
        $biayaPart = (float) ($selectedPart?->harga_jual_retail ?? 0);
        $totalEstimasi = $biayaJasa + $biayaPart;

        return view('modules.marketplace.livewire.estimasi-biaya-servis', [
            'webConfig' => $webConfig,
            'listJenis' => $listJenis,
            'activeJenis' => $activeJenis,
            'partCandidates' => $partCandidates,
            'selectedPart' => $selectedPart,
            'biayaJasa' => $biayaJasa,
            'biayaPart' => $biayaPart,
            'totalEstimasi' => $totalEstimasi,
        ])->layout('layouts.marketplace', [
            'title' => 'Kalkulator Estimasi Biaya Servis HP & Gadget — '.($webConfig['store_name'] ?? config('app.name', 'Toko')),
        ]);
    }
}
