{{-- [F2-3] Trace histori per SN (garansi) — Ute Prism DataTable, desktop-first --}}
<div class="space-y-5">
    {{-- Toolbar pencarian (scan / ketik SN) --}}
    <div class="rounded-2xl glass-panel p-4">
        <div class="flex items-end gap-3 flex-wrap">
            <div class="flex-1 min-w-[260px]">
                <label class="block text-xs font-bold text-ink-300 uppercase mb-1.5">Cari Nomor Seri</label>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Scan / ketik SN — contoh: SN-IP15-0001"
                    class="w-full px-3 py-2.5 rounded-xl glass-input text-sm font-mono"
                    autofocus
                />
            </div>
            <div class="text-[11px] text-ink-400 pb-2.5">
                Histori: GRN → Penjualan → Servis → Garansi · scope cabang aktif
            </div>
        </div>
    </div>

    {{-- Tabel SN + rantai riwayat --}}
    <x-prism.data-table :headers="['Nomor Seri', 'Produk', 'Status', 'Masuk (GRN)', 'Penjualan', 'Tiket Servis', 'Garansi s/d']">
        @forelse($rows as $sn)
            @php($r = $riwayat[$sn->id] ?? [])
            <tr class="hover:bg-white/[0.02] transition-colors text-xs" wire:key="sn-row-{{ $sn->id }}">
                <td class="py-3.5 px-4 font-mono font-bold text-white">{{ $sn->nomor_seri }}</td>
                <td class="py-3.5 px-4 text-ink-200">
                    {{ $sn->produk?->nama ?? '-' }}
                    <span class="block text-[10px] text-ink-400">Cabang #{{ $sn->cabang_id ?? '-' }}</span>
                </td>
                <td class="py-3.5 px-4">
                    <x-prism.status-pill :status="$sn->status" />
                </td>
                <td class="py-3.5 px-4 text-ink-300">
                    <span class="font-mono text-[11px] text-white">{{ $r['grn'] ?? '-' }}</span>
                    <span class="block text-[10px] text-ink-400 tabular-nums">{{ $r['tanggal_masuk'] ?? '-' }}</span>
                </td>
                <td class="py-3.5 px-4">
                    @if($r['no_transaksi'] ?? null)
                        <span class="font-mono text-[11px] font-bold text-up-mint">{{ $r['no_transaksi'] }}</span>
                        <span class="block text-[10px] text-ink-400 tabular-nums">{{ $r['tanggal_jual'] }}</span>
                    @else
                        <span class="text-ink-400">—</span>
                    @endif
                </td>
                <td class="py-3.5 px-4">
                    @if($r['no_tiket'] ?? null)
                        <span class="font-mono text-[11px] font-bold text-up-primary">{{ $r['no_tiket'] }}</span>
                        <span class="block text-[10px] text-ink-400">{{ $r['status_tiket'] }}</span>
                    @else
                        <span class="text-ink-400">—</span>
                    @endif
                </td>
                <td class="py-3.5 px-4 tabular-nums {{ ($r['garansi_sampai'] ?? null) ? 'text-up-mint font-bold' : 'text-ink-400' }}">
                    {{ $r['garansi_sampai'] ?? '—' }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="py-6 text-center text-ink-400 text-xs">
                    {{ trim($search) !== '' ? 'Nomor seri tidak ditemukan di cabang ini.' : 'Belum ada nomor seri tercatat (masuk lewat GRN).' }}
                </td>
            </tr>
        @endforelse
    </x-prism.data-table>

    <div>{{ $rows->links() }}</div>
</div>
