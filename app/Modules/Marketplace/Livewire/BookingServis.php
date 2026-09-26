<?php

namespace App\Modules\Marketplace\Livewire;

use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Services\ServisService;
use Livewire\Component;

class BookingServis extends Component
{
    public ?int $cabang_id = null;

    public string $nama = '';

    public string $telepon = '';

    public string $jenis_hp = '';

    public string $seri_hp = '';

    public string $keluhan = '';

    public function mount(): void
    {
        $firstCabang = Cabang::where('is_active', true)->first();
        if ($firstCabang) {
            $this->cabang_id = $firstCabang->id;
        }

        if (auth('customer')->check()) {
            $customer = auth('customer')->user();
            $this->nama = (string) ($customer->nama ?? '');
            $this->telepon = (string) ($customer->telepon ?? '');
        }
    }

    protected function rules(): array
    {
        return [
            'cabang_id' => 'required|exists:cabang,id',
            'nama' => 'required|string|max:255',
            'telepon' => 'required|string|max:30',
            'jenis_hp' => 'required|string|max:255',
            'seri_hp' => 'nullable|string|max:255',
            'keluhan' => 'required|string',
        ];
    }

    public function simpanBooking(ServisService $servisService)
    {
        $validated = $this->validate();

        $payload = array_merge($validated, [
            'pelanggan_id' => auth('customer')->id(),
        ]);

        $tiket = $servisService->bookingOnline($payload);

        session()->flash('success', 'Booking servis berhasil diajukan! Simpan halaman ini untuk memantau status perbaikan Anda.');

        return redirect()->to(url('/tracking/'.$tiket->token_approval));
    }

    public function render()
    {
        $cabangs = Cabang::where('is_active', true)->get();

        return view('modules.marketplace.livewire.booking-servis', [
            'cabangs' => $cabangs,
        ])->layout('layouts.marketplace', ['title' => 'Booking Servis HP']);
    }
}
