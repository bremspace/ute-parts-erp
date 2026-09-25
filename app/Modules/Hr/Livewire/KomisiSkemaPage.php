<?php

namespace App\Modules\Hr\Livewire;

use App\Modules\Reseller\Models\KomisiSkema;
use Illuminate\View\View;
use Livewire\Component;

/**
 * [F3-8c] Rule builder komisi multi-aktor (PRD §4.3) — admin/finance (kelola-hr).
 * CRUD rule: aktor (karyawan/reseller/agen) × trigger (penjualan/lead_won/
 * tiket_servis/target_kpi), nominal/persen, min_amount, status aktif.
 */
class KomisiSkemaPage extends Component
{
    public string $filterAktor = 'semua';

    public string $filterTrigger = 'semua';

    public ?int $editId = null;

    public string $nama = '';

    public string $aktorTipe = 'karyawan';

    public ?int $aktorId = null;

    public string $triggerTipe = 'penjualan';

    public string $kategori = '';

    public string $tipe = 'persen';

    public string $nilai = '0';

    public string $minAmount = '0';

    public bool $isAktif = true;

    public ?string $pesan = null;

    protected function rules(): array
    {
        return [
            'nama' => 'required|string|max:120',
            'aktorTipe' => 'required|in:karyawan,reseller,agen',
            'aktorId' => 'nullable|integer|min:1',
            'triggerTipe' => 'required|in:penjualan,lead_won,tiket_servis,target_kpi',
            'kategori' => 'nullable|string|max:100',
            'tipe' => 'required|in:persen,nominal',
            'nilai' => 'required|numeric|min:0',
            'minAmount' => 'nullable|numeric|min:0',
            'isAktif' => 'boolean',
        ];
    }

    public function simpan(): void
    {
        $this->validate();

        $data = [
            'nama' => $this->nama,
            'aktor_tipe' => $this->aktorTipe,
            'aktor_id' => $this->aktorId ?: null,
            'trigger_tipe' => $this->triggerTipe,
            'kategori' => $this->kategori ?: null,
            'tipe' => $this->tipe,
            'nilai' => (float) $this->nilai,
            'min_amount' => (float) $this->minAmount,
            'cabang_id' => null, // UI MVP: rule global; filter cabang ready di engine
            'is_aktif' => $this->isAktif,
        ];

        if ($this->editId) {
            KomisiSkema::findOrFail($this->editId)->update($data);
            $this->pesan = 'Skema komisi diperbarui.';
        } else {
            KomisiSkema::create($data);
            $this->pesan = 'Skema komisi baru dibuat.';
        }

        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $skema = KomisiSkema::findOrFail($id);
        $this->editId = $skema->id;
        $this->nama = $skema->nama;
        $this->aktorTipe = $skema->aktor_tipe;
        $this->aktorId = $skema->aktor_id;
        $this->triggerTipe = $skema->trigger_tipe;
        $this->kategori = (string) ($skema->kategori ?? '');
        $this->tipe = $skema->tipe;
        $this->nilai = (string) $skema->nilai;
        $this->minAmount = (string) $skema->min_amount;
        $this->isAktif = (bool) $skema->is_aktif;
    }

    public function toggle(int $id): void
    {
        $skema = KomisiSkema::find($id);
        if ($skema) {
            $skema->update(['is_aktif' => ! $skema->is_aktif]);
            $this->pesan = $skema->is_aktif ? 'Skema diaktifkan.' : 'Skema dinonaktifkan.';
        }
    }

    public function hapus(int $id): void
    {
        KomisiSkema::where('id', $id)->delete();
        $this->pesan = 'Skema komisi dihapus.';
    }

    public function resetForm(): void
    {
        $this->editId = null;
        $this->nama = '';
        $this->aktorTipe = 'karyawan';
        $this->aktorId = null;
        $this->triggerTipe = 'penjualan';
        $this->kategori = '';
        $this->tipe = 'persen';
        $this->nilai = '0';
        $this->minAmount = '0';
        $this->isAktif = true;
    }

    public function render(): View
    {
        return view('modules.hr.komisi-skema', [
            'skemas' => KomisiSkema::query()
                ->when($this->filterAktor !== 'semua', fn ($q) => $q->where('aktor_tipe', $this->filterAktor))
                ->when($this->filterTrigger !== 'semua', fn ($q) => $q->where('trigger_tipe', $this->filterTrigger))
                ->orderByDesc('is_aktif')
                ->orderByDesc('id')
                ->get(),
        ])->layout('layouts.backoffice', ['header' => 'Komisi Multi-Aktor', 'title' => 'Rule Komisi Multi-Aktor']);
    }
}
