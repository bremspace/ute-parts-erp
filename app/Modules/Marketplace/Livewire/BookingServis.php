<?php

namespace App\Modules\Marketplace\Livewire;

use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\JenisServis;
use App\Modules\Servis\Services\ServisService;
use Livewire\Component;

class BookingServis extends Component
{
    public ?int $cabang_id = null;

    public ?int $jenis_servis_id = null;

    public string $nama = '';

    public string $telepon = '';

    public string $jenis_hp = '';

    public string $seri_hp = '';

    public string $keluhan = '';

    public string $tipe_kunci = '';

    public string $kunci_terenkripsi = '';

    public array $kondisi_fisik = [];

    public array $kelengkapan = [];

    public string $rencana_datang = '';

    public string $catatan = '';

    public function mount(): void
    {
        $firstCabang = Cabang::where('is_active', true)->first();
        if ($firstCabang) {
            $this->cabang_id = $firstCabang->id;
        }

        $firstJenis = JenisServis::where('is_active', true)->first();
        if ($firstJenis) {
            $this->jenis_servis_id = $firstJenis->id;
        }

        if (auth('customer')->check()) {
            $customer = auth('customer')->user();
            $this->nama = (string) ($customer->nama ?? '');
            $this->telepon = (string) ($customer->telepon ?? '');
        }
    }

    public function toggleKondisiFisik(string $item): void
    {
        if (in_array($item, $this->kondisi_fisik, true)) {
            $this->kondisi_fisik = array_values(array_diff($this->kondisi_fisik, [$item]));
        } else {
            $this->kondisi_fisik[] = $item;
        }
    }

    public function toggleKelengkapan(string $item): void
    {
        if (in_array($item, $this->kelengkapan, true)) {
            $this->kelengkapan = array_values(array_diff($this->kelengkapan, [$item]));
        } else {
            $this->kelengkapan[] = $item;
        }
    }

    protected function rules(): array
    {
        return [
            'cabang_id' => 'required|exists:cabang,id',
            'jenis_servis_id' => 'nullable|exists:jenis_servis,id',
            'nama' => 'required|string|max:255',
            'telepon' => 'required|string|max:30',
            'jenis_hp' => 'required|string|max:255',
            'seri_hp' => 'nullable|string|max:255',
            'keluhan' => 'required|string',
            'tipe_kunci' => 'nullable|string|in:pola,pin,password,tidak_ada',
            'kunci_terenkripsi' => 'nullable|string|max:255',
            'kondisi_fisik' => 'nullable|array',
            'kelengkapan' => 'nullable|array',
            'rencana_datang' => 'nullable|string|max:100',
            'catatan' => 'nullable|string|max:500',
        ];
    }

    public function simpanBooking(ServisService $servisService)
    {
        $validated = $this->validate();

        $catatanBooking = [];
        if (! empty($this->rencana_datang)) {
            $catatanBooking[] = 'Rencana Datang: '.$this->rencana_datang;
        }
        if (! empty($this->kelengkapan)) {
            $catatanBooking[] = 'Kelengkapan: '.implode(', ', $this->kelengkapan);
        }
        if (! empty($this->catatan)) {
            $catatanBooking[] = 'Catatan: '.$this->catatan;
        }

        $payload = array_merge($validated, [
            'pelanggan_id' => auth('customer')->id(),
            'catatan_booking' => implode(' | ', $catatanBooking) ?: null,
        ]);

        $tiket = $servisService->bookingOnline($payload);

        session()->flash('success', 'Booking servis berhasil diajukan! Simpan halaman ini untuk memantau status perbaikan Anda.');

        return redirect()->to(url('/tracking/'.$tiket->token_approval));
    }

    public function render()
    {
        $cabangs = Cabang::where('is_active', true)->get();
        $jenisServisList = JenisServis::where('is_active', true)->get();

        return view('modules.marketplace.livewire.booking-servis', [
            'cabangs' => $cabangs,
            'jenisServisList' => $jenisServisList,
        ])->layout('layouts.marketplace', ['title' => 'Booking Servis HP']);
    }
}
