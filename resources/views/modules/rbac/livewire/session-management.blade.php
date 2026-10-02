<div class="space-y-4">
    {{-- [F3-4] Session Management — device list, force logout per device --}}

    {{-- ===== Search + aksi global ===== --}}
    <div class="flex items-end gap-2 flex-wrap">
        <div class="flex-shrink-0">
            <label class="block text-[10px] text-ink-400 font-semibold mb-1">Cari user</label>
            <input type="text" wire:model.live.debounce.400ms="search" placeholder="Cari berdasarkan nama user…"
                class="px-2.5 py-2 rounded-lg glass-input text-xs font-medium w-56 min-h-[44px]" />
        </div>
        <div class="ml-auto flex-shrink-0">
            <button wire:click="logoutAll"
                wire:confirm="Yakin ingin mengakhiri SEMUA sesi selain sesi Anda saat ini?"
                class="px-3 py-2 rounded-lg bg-up-red/10 border border-up-red/30 text-up-red text-xs font-bold hover:bg-up-red/20 cursor-pointer min-h-[44px] transition-colors">
                Akhiri semua sesi
            </button>
        </div>
    </div>

    {{-- ===== Tabel device ===== --}}
    <x-prism.data-table :headers="['User', 'Device', 'IP', 'Aktivitas Terakhir', 'Kedaluwarsa', 'Status', 'Aksi']">
        @forelse($devices as $device)
            <tr class="border-b border-white/5 hover:bg-white/[0.02] transition-colors">
                {{-- User --}}
                <td class="py-3.5 px-4 whitespace-nowrap">
                    @if($device->user)
                        <p class="text-ink-200 font-medium text-xs">{{ $device->user->name }}</p>
                        <p class="text-ink-500 text-[10px]">{{ $device->user->email }}</p>
                    @else
                        <span class="text-ink-500 text-xs italic">User dihapus</span>
                    @endif
                </td>
                {{-- Device name --}}
                <td class="py-3.5 px-4 text-ink-200 text-xs font-medium max-w-[200px] truncate" title="{{ $device->device_name }}">
                    {{ $device->device_name ?? '-' }}
                </td>
                {{-- IP --}}
                <td class="py-3.5 px-4 text-ink-300 text-xs tabular-nums whitespace-nowrap">
                    {{ $device->ip_address ?? '-' }}
                </td>
                {{-- Last activity --}}
                <td class="py-3.5 px-4 text-ink-300 text-xs tabular-nums whitespace-nowrap" title="{{ $device->last_activity?->format('d/m/Y H:i:s') }}">
                    {{ $device->last_activity?->diffForHumans() ?? '-' }}
                </td>
                {{-- Expires --}}
                <td class="py-3.5 px-4 text-ink-300 text-xs tabular-nums whitespace-nowrap" title="{{ $device->expires_at?->format('d/m/Y H:i:s') }}">
                    {{ $device->expires_at?->diffForHumans() ?? '-' }}
                </td>
                {{-- Status --}}
                <td class="py-3.5 px-4">
                    <x-prism.status-pill :status="$device->is_active ? 'aktif' : 'batal'" size="sm" />
                </td>
                {{-- Aksi --}}
                <td class="py-3.5 px-4">
                    <button wire:click="logoutDevice('{{ $device->device_token }}')"
                        wire:confirm="Yakin ingin mengakhiri sesi device ini?"
                        class="text-up-red hover:underline text-[11px] cursor-pointer font-semibold">
                        Akhiri sesi
                    </button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="py-10 text-center text-ink-400 text-xs">
                    Tidak ada sesi device yang tercatat saat ini.
                </td>
            </tr>
        @endforelse
    </x-prism.data-table>

    {{-- ===== Paginasi ===== --}}
    @if($devices->total() > $devices->count())
        <div class="flex items-center justify-between text-[11px] text-ink-400">
            <span class="tabular-nums">Halaman {{ $devices->currentPage() }} / {{ max(1, $devices->lastPage()) }}</span>
            <div class="flex gap-2">
                @if($devices->onFirstPage())
                    <span class="px-3 py-1.5 rounded-lg bg-white/5 border border-white/10 font-bold opacity-40">← Sebelumnya</span>
                @else
                    <button wire:click="previousPage" wire:loading.attr="disabled"
                        class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 font-bold cursor-pointer">
                        ← Sebelumnya
                    </button>
                @endif
                @if($devices->hasMorePages())
                    <button wire:click="nextPage" wire:loading.attr="disabled"
                        class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 font-bold cursor-pointer">
                        Berikutnya →
                    </button>
                @else
                    <span class="px-3 py-1.5 rounded-lg bg-white/5 border border-white/10 font-bold opacity-40">Berikutnya →</span>
                @endif
            </div>
        </div>
    @endif
</div>
