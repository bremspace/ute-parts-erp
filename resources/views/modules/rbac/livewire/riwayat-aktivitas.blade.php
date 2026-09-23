<div class="space-y-4">
    {{-- [F1-4] Header entitas --}}
    @if($judul !== '')
        <div class="flex items-center justify-between gap-2 flex-wrap">
            <div>
                <p class="text-[10px] text-up-mint uppercase font-bold tracking-wider">{{ $labelTipe }} · Riwayat Aktivitas</p>
                <p class="text-sm font-bold text-white">{{ $judul }}</p>
            </div>
            <span class="text-[11px] text-ink-400 tabular-nums">{{ $aktivitas->total() }} aktivitas</span>
        </div>
    @endif

    @if(! $izin)
        <div class="p-4 rounded-xl bg-up-amber/10 border border-up-amber/30 text-xs text-up-amber font-semibold">
            Anda tidak memiliki izin <span class="font-mono">lihat-audit-log</span> untuk melihat riwayat aktivitas.
        </div>
    @elseif($akses === 'cabang-lain')
        <div class="p-4 rounded-xl bg-up-red/10 border border-up-red/30 text-xs text-up-red font-semibold">
            Anda tidak memiliki akses ke data cabang lain. Hanya riwayat entitas cabang aktif yang dapat dilihat.
        </div>
    @elseif($akses === 'tidak-dikenal')
        <div class="p-4 rounded-xl bg-white/[0.03] border border-white/10 text-xs text-ink-400">
            Entitas tidak ditemukan.
        </div>
    @else
        {{-- ===== Filter ===== --}}
        <div class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-[10px] text-ink-400 font-semibold mb-1">Pengguna</label>
                <input type="text" wire:model.live.debounce.400ms="filterUser" placeholder="Nama / id / sistem"
                    class="px-2.5 py-2 rounded-lg glass-input text-xs font-medium w-36" />
            </div>
            <div>
                <label class="block text-[10px] text-ink-400 font-semibold mb-1">Aksi</label>
                <select wire:model.live="filterAksi" class="px-2.5 py-2 rounded-lg glass-input text-xs font-medium">
                    <option value="">Semua aksi</option>
                    @foreach($opsiAksi as $kode => $label)
                        <option value="{{ $kode }}" class="bg-ink-900">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[10px] text-ink-400 font-semibold mb-1">Dari</label>
                <input type="date" wire:model.live="filterDari" class="px-2.5 py-2 rounded-lg glass-input text-xs font-medium" />
            </div>
            <div>
                <label class="block text-[10px] text-ink-400 font-semibold mb-1">Sampai</label>
                <input type="date" wire:model.live="filterSampai" class="px-2.5 py-2 rounded-lg glass-input text-xs font-medium" />
            </div>
            <button wire:click="resetFilter"
                class="px-3 py-2 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 font-bold text-[11px] cursor-pointer">
                Reset
            </button>
        </div>

        {{-- ===== Tabel riwayat ===== --}}
        <x-prism.data-table :headers="['Waktu', 'Aksi', 'Oleh', 'Perubahan (sebelum → sesudah)']">
            @forelse($aktivitas as $baris)
                @php
                    $changes = $baris->attribute_changes ?? collect();
                    $sesudah = $changes->get('attributes') ?? [];
                    $sebelum = $changes->get('old') ?? [];
                    $lewati = ['id', 'created_at', 'updated_at'];

                    // Update → hanya field dirty (before/after); create/delete → snapshot penuh tanpa id/timestamp
                    $kunciSemua = array_values(array_unique(array_merge(array_keys($sesudah), array_keys($sebelum))));
                    $kunciSemua = array_values(array_filter($kunciSemua, fn ($k) => ! in_array($k, $lewati, true)));
                    $kunci = array_values(array_slice($kunciSemua, 0, 6));
                    $sisa = max(0, count($kunciSemua) - count($kunci));

                    $fmt = function ($v, $key) {
                        if ($v === null) {
                            return '—';
                        }
                        if (is_bool($v)) {
                            return $v ? 'Ya' : 'Tidak';
                        }
                        if (is_numeric($v) && $key !== 'id' && ! str_ends_with((string) $key, '_id') && ! str_ends_with((string) $key, '_persen')) {
                            return number_format((float) $v, 0, ',', '.');
                        }
                        if (is_array($v)) {
                            return '[...]';
                        }

                        return (string) $v;
                    };
                @endphp
                <tr class="hover:bg-white/[0.02] transition-colors text-xs align-top">
                    <td class="py-3.5 px-4 tabular-nums text-ink-300 whitespace-nowrap">
                        {{ $baris->created_at?->format('d/m/Y H:i:s') }}
                    </td>
                    <td class="py-3.5 px-4">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold
                            {{ $baris->event === 'created' ? 'bg-up-mint/10 text-up-mint'
                                : ($baris->event === 'deleted' ? 'bg-up-red/10 text-up-red' : 'bg-up-primary/10 text-up-primary') }}">
                            {{ $baris->aksi_label }}
                        </span>
                    </td>
                    <td class="py-3.5 px-4 text-ink-200 font-medium whitespace-nowrap">
                        {{ $baris->causer_nama }}
                    </td>
                    <td class="py-3.5 px-4">
                        @empty($kunci)
                            <span class="text-ink-500">Tidak ada perubahan detail</span>
                        @else
                            <div class="space-y-1">
                                @foreach($kunci as $key)
                                    <div class="flex flex-wrap gap-1 items-baseline">
                                        <span class="font-mono text-[10px] text-ink-400">{{ $key }}</span>
                                        @if($baris->event === 'created')
                                            <span class="font-semibold text-white tabular-nums">{{ $fmt($sesudah[$key] ?? null, $key) }}</span>
                                        @elseif($baris->event === 'deleted')
                                            <span class="text-up-red tabular-nums line-through">{{ $fmt($sebelum[$key] ?? null, $key) }}</span>
                                        @else
                                            <span class="text-up-red tabular-nums line-through">{{ $fmt($sebelum[$key] ?? null, $key) }}</span>
                                            <span class="text-ink-500">→</span>
                                            <span class="text-up-mint font-semibold tabular-nums">{{ $fmt($sesudah[$key] ?? null, $key) }}</span>
                                        @endif
                                    </div>
                                @endforeach
                                @if($sisa > 0)
                                    <p class="text-[10px] text-ink-500">+{{ $sisa }} field lainnya</p>
                                @endif
                            </div>
                        @endempty
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="py-10 text-center text-ink-400 text-xs">
                        Tidak ada aktivitas tercatat untuk filter ini.
                    </td>
                </tr>
            @endforelse
        </x-prism.data-table>

        {{-- ===== Paginasi sederhana ===== --}}
        @if($aktivitas->total() > $aktivitas->count())
            <div class="flex items-center justify-between text-[11px] text-ink-400">
                <span class="tabular-nums">Halaman {{ $aktivitas->currentPage() }} / {{ max(1, $aktivitas->lastPage()) }}</span>
                <div class="flex gap-2">
                    <button wire:click="halamanSebelumnya" wire:loading.attr="disabled"
                        class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 font-bold cursor-pointer disabled:opacity-40">
                        ← Sebelumnya
                    </button>
                    <button wire:click="halamanBerikutnya" wire:loading.attr="disabled" @disabled(! $aktivitas->hasMorePages())
                        class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 font-bold cursor-pointer disabled:opacity-40">
                        Berikutnya →
                    </button>
                </div>
            </div>
        @endif
    @endif
</div>
