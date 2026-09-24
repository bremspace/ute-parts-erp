@extends('layouts.backoffice')

@section('title', 'Rule Komisi Multi-Aktor')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-up-primary">Rule Komisi Multi-Aktor</h1>
            <p class="mt-1 text-sm text-ink-500">Komisi karyawan / reseller / agen dari penjualan, lead won, tiket servis, dan target KPI</p>
        </div>
    </div>

    @if($pesan)
        <div class="mb-4 px-4 py-3 rounded-md bg-up-mint/10 border border-up-mint/30 text-sm text-ink-700">
            {{ $pesan }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Form rule --}}
        <div class="lg:col-span-1">
            <div class="glass-card p-5">
                <h2 class="text-lg font-semibold text-ink-800 mb-4">{{ $editId ? 'Ubah Skema' : 'Tambah Skema' }}</h2>
                <form wire:submit="simpan" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-ink-700 mb-1">Nama Skema</label>
                        <input type="text" wire:model="nama" placeholder="mis. Reseller umum 3%"
                            class="block w-full rounded-md border-ink-300 shadow-sm focus:border-up-primary focus:ring-up-primary sm:text-sm">
                        @error('nama') <span class="text-xs text-up-red">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-ink-700 mb-1">Tipe Aktor</label>
                            <select wire:model="aktorTipe" class="block w-full rounded-md border-ink-300 shadow-sm focus:border-up-primary focus:ring-up-primary sm:text-sm">
                                <option value="karyawan">Karyawan</option>
                                <option value="reseller">Reseller</option>
                                <option value="agen">Agen</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-ink-700 mb-1">Trigger</label>
                            <select wire:model="triggerTipe" class="block w-full rounded-md border-ink-300 shadow-sm focus:border-up-primary focus:ring-up-primary sm:text-sm">
                                <option value="penjualan">Penjualan</option>
                                <option value="lead_won">Lead Won</option>
                                <option value="tiket_servis">Tiket Servis</option>
                                <option value="target_kpi">Target KPI</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-ink-700 mb-1">
                            Aktor ID <span class="text-ink-400 font-normal">(opsional — kosong = semua aktor tipe ini)</span>
                        </label>
                        <input type="number" wire:model="aktorId" placeholder="mis. 3"
                            class="block w-full rounded-md border-ink-300 shadow-sm focus:border-up-primary focus:ring-up-primary sm:text-sm">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-ink-700 mb-1">Tipe Nilai</label>
                            <select wire:model="tipe" class="block w-full rounded-md border-ink-300 shadow-sm focus:border-up-primary focus:ring-up-primary sm:text-sm">
                                <option value="persen">Persen (%)</option>
                                <option value="nominal">Nominal (Rp)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-ink-700 mb-1">Nilai</label>
                            <input type="number" step="any" wire:model="nilai" placeholder="mis. 5"
                                class="block w-full rounded-md border-ink-300 shadow-sm focus:border-up-primary focus:ring-up-primary sm:text-sm">
                            @error('nilai') <span class="text-xs text-up-red">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-ink-700 mb-1">Min. Amount (Rp)</label>
                            <input type="number" step="any" wire:model="minAmount" placeholder="0 = tanpa ambang"
                                class="block w-full rounded-md border-ink-300 shadow-sm focus:border-up-primary focus:ring-up-primary sm:text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-ink-700 mb-1">Kategori <span class="text-ink-400 font-normal">(opsional)</span></label>
                            <input type="text" wire:model="kategori" placeholder="mis. LCD"
                                class="block w-full rounded-md border-ink-300 shadow-sm focus:border-up-primary focus:ring-up-primary sm:text-sm">
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-ink-700">
                        <input type="checkbox" wire:model="isAktif" class="rounded border-ink-300">
                        Aktif
                    </label>

                    <div class="flex gap-2">
                        <button type="submit"
                            class="inline-flex justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-up-primary hover:bg-up-primary/80 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-up-primary">
                            {{ $editId ? 'Perbarui Skema' : 'Simpan Skema' }}
                        </button>
                        @if($editId)
                            <button type="button" wire:click="resetForm"
                                class="inline-flex justify-center px-4 py-2 text-sm font-medium rounded-md border border-ink-300 text-ink-600 hover:bg-ink-50">
                                Batal
                            </button>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        {{-- Daftar rule --}}
        <div class="lg:col-span-2">
            <div class="glass-card p-5">
                <div class="flex flex-wrap gap-2 mb-4">
                    <select wire:model.live="filterAktor" class="rounded-md border-ink-300 shadow-sm text-sm focus:border-up-primary focus:ring-up-primary">
                        <option value="semua">Semua Aktor</option>
                        <option value="karyawan">Karyawan</option>
                        <option value="reseller">Reseller</option>
                        <option value="agen">Agen</option>
                    </select>
                    <select wire:model.live="filterTrigger" class="rounded-md border-ink-300 shadow-sm text-sm focus:border-up-primary focus:ring-up-primary">
                        <option value="semua">Semua Trigger</option>
                        <option value="penjualan">Penjualan</option>
                        <option value="lead_won">Lead Won</option>
                        <option value="tiket_servis">Tiket Servis</option>
                        <option value="target_kpi">Target KPI</option>
                    </select>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-ink-100">
                        <thead class="bg-ink-50/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-ink-500 uppercase tracking-wider">Nama</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-ink-500 uppercase tracking-wider">Aktor</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-ink-500 uppercase tracking-wider">Trigger</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-ink-500 uppercase tracking-wider">Nilai</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-ink-500 uppercase tracking-wider">Min</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-ink-500 uppercase tracking-wider">Aktif</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-ink-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @forelse($skemas as $skema)
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-ink-800">{{ $skema->nama }}</td>
                                    <td class="px-4 py-3 text-sm text-ink-600 capitalize">
                                        {{ $skema->aktor_tipe }}
                                        @if($skema->aktor_id) <span class="text-xs text-ink-400">#{{ $skema->aktor_id }}</span> @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-ink-600 capitalize">{{ str_replace('_', ' ', $skema->trigger_tipe) }}</td>
                                    <td class="px-4 py-3 text-sm tabular-nums text-ink-800">
                                        {{ $skema->tipe === 'persen' ? number_format((float) $skema->nilai, 1) . '%' : 'Rp ' . number_format((float) $skema->nilai, 0, ',', '.') }}
                                        @if($skema->kategori) <span class="text-xs text-ink-400">({{ $skema->kategori }})</span> @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm tabular-nums text-ink-600">{{ $skema->min_amount > 0 ? 'Rp ' . number_format((float) $skema->min_amount, 0, ',', '.') : '-' }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <button wire:click="toggle({{ $skema->id }})" class="cursor-pointer"
                                            title="{{ $skema->is_aktif ? 'Nonaktifkan' : 'Aktifkan' }}">
                                            <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $skema->is_aktif ? 'bg-up-mint/20 text-up-mint' : 'bg-ink-100 text-ink-400' }}">
                                                {{ $skema->is_aktif ? 'Aktif' : 'Nonaktif' }}
                                            </span>
                                        </button>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm space-x-2">
                                        <button wire:click="edit({{ $skema->id }})" class="text-up-primary hover:underline cursor-pointer">Ubah</button>
                                        <button wire:click="hapus({{ $skema->id }})" wire:confirm="Hapus skema ini?"
                                            class="text-up-red hover:underline cursor-pointer">Hapus</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-sm text-ink-400">
                                        Belum ada skema komisi. Tambahkan di form sebelah kiri.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection