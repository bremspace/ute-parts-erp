<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-ink-900 dark:text-white">Rule Komisi Multi-Aktor</h1>
            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Komisi karyawan / reseller / agen dari penjualan, lead won, tiket servis, dan target KPI</p>
        </div>
    </div>

    @if($pesan)
        <div class="px-4 py-3 rounded-xl bg-up-mint/10 border border-up-mint/30 text-sm text-up-mint font-medium flex items-center gap-2">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ $pesan }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Form rule --}}
        <div class="lg:col-span-1">
            <div class="glass-card p-5">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white mb-4">{{ $editId ? 'Ubah Skema' : 'Tambah Skema' }}</h2>
                <form wire:submit="simpan" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Nama Skema</label>
                        <input type="text" wire:model="nama" placeholder="mis. Reseller umum 3%"
                            class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-2">
                        @error('nama') <span class="text-xs text-up-red font-medium mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Tipe Aktor</label>
                            <select wire:model="aktorTipe" class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-2">
                                <option value="karyawan">Karyawan</option>
                                <option value="reseller">Reseller</option>
                                <option value="agen">Agen</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Trigger</label>
                            <select wire:model="triggerTipe" class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-2">
                                <option value="penjualan">Penjualan</option>
                                <option value="lead_won">Lead Won</option>
                                <option value="tiket_servis">Tiket Servis</option>
                                <option value="target_kpi">Target KPI</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">
                            Aktor ID <span class="text-ink-400 dark:text-ink-500 font-normal">(opsional — kosong = semua)</span>
                        </label>
                        <input type="number" wire:model="aktorId" placeholder="mis. 3"
                            class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-2">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Tipe Nilai</label>
                            <select wire:model="tipe" class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-2">
                                <option value="persen">Persen (%)</option>
                                <option value="nominal">Nominal (Rp)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Nilai</label>
                            <input type="text" inputmode="numeric" x-format-number wire:model="nilai" placeholder="mis. 5"
                                class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-2 tabular-nums">
                            @error('nilai') <span class="text-xs text-up-red font-medium mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Min. Amount (Rp)</label>
                            <input type="text" inputmode="numeric" x-format-number wire:model="minAmount" placeholder="0 = tanpa ambang"
                                class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-2 tabular-nums">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Kategori <span class="text-ink-400 dark:text-ink-500 font-normal">(opsional)</span></label>
                            <input type="text" wire:model="kategori" placeholder="mis. LCD"
                                class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-2">
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-ink-700 dark:text-ink-300 cursor-pointer">
                        <input type="checkbox" wire:model="isAktif" class="rounded border-ink-300 dark:border-white/20 bg-white dark:bg-ink-900 text-up-primary focus:ring-up-primary">
                        <span>Aktif</span>
                    </label>

                    <div class="flex items-center gap-2 pt-2">
                        <button type="submit"
                            class="inline-flex justify-center items-center px-4 py-2.5 border border-transparent text-sm font-bold rounded-xl shadow-sm text-white bg-up-primary hover:bg-up-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-up-primary min-h-[44px] active:scale-[0.97] transition flex-1">
                            {{ $editId ? 'Perbarui Skema' : 'Simpan Skema' }}
                        </button>
                        @if($editId)
                            <button type="button" wire:click="resetForm"
                                class="inline-flex justify-center items-center px-4 py-2.5 text-sm font-semibold rounded-xl border border-ink-200 dark:border-white/10 text-ink-700 dark:text-ink-300 hover:bg-black/5 dark:hover:bg-white/5 min-h-[44px] active:scale-[0.97] transition">
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
                <div class="flex items-center gap-2 mb-4 overflow-x-auto scrollbar-none py-1 -my-1 flex-nowrap sm:flex-wrap">
                    <select wire:model.live="filterAktor" class="rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm text-base sm:text-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 min-h-[44px] px-3 py-2">
                        <option value="semua">Semua Aktor</option>
                        <option value="karyawan">Karyawan</option>
                        <option value="reseller">Reseller</option>
                        <option value="agen">Agen</option>
                    </select>
                    <select wire:model.live="filterTrigger" class="rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm text-base sm:text-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 min-h-[44px] px-3 py-2">
                        <option value="semua">Semua Trigger</option>
                        <option value="penjualan">Penjualan</option>
                        <option value="lead_won">Lead Won</option>
                        <option value="tiket_servis">Tiket Servis</option>
                        <option value="target_kpi">Target KPI</option>
                    </select>
                </div>

                <x-prism.dual-scroll>
                    <table class="min-w-full divide-y divide-ink-200 dark:divide-white/10 text-sm">
                        <thead class="bg-ink-50/70 dark:bg-white/[0.03]">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase tracking-wider">Nama</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase tracking-wider">Aktor</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase tracking-wider">Trigger</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase tracking-wider">Nilai</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase tracking-wider">Min</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase tracking-wider">Aktif</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100 dark:divide-white/5">
                            @forelse($skemas as $skema)
                                <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors">
                                    <td class="px-4 py-3 text-sm font-medium text-ink-900 dark:text-white">{{ $skema->nama }}</td>
                                    <td class="px-4 py-3 text-sm text-ink-600 dark:text-ink-300 capitalize">
                                        {{ $skema->aktor_tipe }}
                                        @if($skema->aktor_id) <span class="text-xs text-ink-400 dark:text-ink-500 font-mono">#{{ $skema->aktor_id }}</span> @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-ink-600 dark:text-ink-300 capitalize">{{ str_replace('_', ' ', $skema->trigger_tipe) }}</td>
                                    <td class="px-4 py-3 text-sm tabular-nums text-ink-900 dark:text-white font-medium">
                                        {{ $skema->tipe === 'persen' ? number_format((float) $skema->nilai, 1) . '%' : 'Rp ' . number_format((float) $skema->nilai, 0, ',', '.') }}
                                        @if($skema->kategori) <span class="text-xs text-ink-400 dark:text-ink-500">({{ $skema->kategori }})</span> @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm tabular-nums text-ink-600 dark:text-ink-300">{{ $skema->min_amount > 0 ? 'Rp ' . number_format((float) $skema->min_amount, 0, ',', '.') : '-' }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <button wire:click="toggle({{ $skema->id }})" class="cursor-pointer active:scale-95 transition"
                                            title="{{ $skema->is_aktif ? 'Nonaktifkan' : 'Aktifkan' }}">
                                            <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $skema->is_aktif ? 'bg-up-mint/20 text-up-mint' : 'bg-ink-100 text-ink-500 dark:bg-white/10 dark:text-ink-400' }}">
                                                {{ $skema->is_aktif ? 'Aktif' : 'Nonaktif' }}
                                            </span>
                                        </button>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm space-x-2 whitespace-nowrap">
                                        <button wire:click="edit({{ $skema->id }})" class="text-up-primary hover:underline cursor-pointer active:scale-95 transition font-medium">Ubah</button>
                                        <button wire:click="hapus({{ $skema->id }})" wire:confirm="Hapus skema ini?"
                                            class="text-up-red hover:underline cursor-pointer active:scale-95 transition font-medium">Hapus</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-sm text-ink-400 dark:text-ink-500">
                                        Belum ada skema komisi. Tambahkan di form sebelah kiri.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-prism.dual-scroll>
            </div>
        </div>
    </div>
</div>
