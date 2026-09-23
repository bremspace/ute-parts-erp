<div>
    <!-- TAB 3: STOCK OPNAME -->
        <div class="space-y-4">
            <x-prism.data-table :headers="['No. Opname', 'Gudang', 'Pembuat', 'Total Item', 'Tanggal', 'Status', 'Aksi Supervisor']">
                @forelse($opnames as $opn)
                    <tr class="hover:bg-white/[0.02] transition-colors text-xs">
                        <td class="py-3.5 px-4 font-mono font-bold text-white">{{ $opn->no_opname }}</td>
                        <td class="py-3.5 px-4 text-ink-300 font-medium">{{ $opn->gudang?->nama }}</td>
                        <td class="py-3.5 px-4 text-ink-400">{{ $opn->pembuat?->name }}</td>
                        <td class="py-3.5 px-4 text-white font-bold tabular-nums">{{ $opn->items->count() }} sparepart</td>
                        <td class="py-3.5 px-4 text-ink-400">{{ $opn->created_at->format('d/m/Y H:i') }}</td>
                        <td class="py-3.5 px-4">
                            <x-prism.status-pill :status="$opn->status" />
                        </td>
                        <td class="py-3.5 px-4">
                            @if($opn->status === 'menunggu_approval')
                                <button
                                    wire:click="approveOpname({{ $opn->id }})"
                                    class="px-3 py-1 rounded-lg bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs transition-all shadow-md shadow-up-mint/20 cursor-pointer"
                                >
                                    Approve & Sesuaikan
                                </button>
                            @else
                                <span class="text-ink-500 text-[11px]">
                                    {{ $opn->approver ? "Approved by {$opn->approver->name}" : '-' }}
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-12 text-center text-ink-400">
                            Belum ada riwayat stock opname
                        </td>
                    </tr>
                @endforelse
            </x-prism.data-table>
        </div>

    <!-- MODAL: MULAI STOCK OPNAME -->
    @if($showOpnameModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-3xl glass-panel p-6 rounded-3xl border border-white/10 shadow-2xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Input Hasil Stock Opname Fisik</h3>
                    <button wire:click="$set('showOpnameModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-semibold text-ink-300 mb-1.5">Lokasi Gudang yang Di-Opname</label>
                    <select wire:model.live="opnameGudangId" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                        @foreach($gudangs as $g)
                            <option value="{{ $g->id }}" class="bg-ink-900">{{ $g->nama }} ({{ $g->kode }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-ink-300 mb-1.5">Rak (Opsional — filter per lokasi)</label>
                    <select wire:model.live="opnameRakId" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                        <option value="" class="bg-ink-900">Semua Rak di Gudang</option>
                        @foreach($raks as $rk)
                            @if($rk->gudang_id == $opnameGudangId)
                                <option value="{{ $rk->id }}" class="bg-ink-900">{{ $rk->kode }} - {{ $rk->nama }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>

                <!-- Opname Spreadsheet Table -->
                <div class="max-h-72 overflow-y-auto mb-4 rounded-xl border border-white/10 overflow-hidden">
                    <table class="w-full text-xs text-left text-ink-100">
                        <thead class="bg-white/5 text-ink-400 uppercase text-[10px] font-semibold sticky top-0 bg-ink-900">
                            <tr>
                                <th class="p-3">Produk</th>
                                <th class="p-3 text-center">Stok Sistem</th>
                                <th class="p-3 text-center w-28">Stok Fisik</th>
                                <th class="p-3 text-center">Selisih</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach($opnameRows as $idx => $r)
                                <tr>
                                    <td class="p-3 font-medium text-white">{{ $r['nama'] }}</td>
                                    <td class="p-3 text-center font-bold tabular-nums text-ink-400">{{ $r['stok_sistem'] }}</td>
                                    <td class="p-2 text-center">
                                        <input
                                            type="number"
                                            wire:change="updateOpnameFisik({{ $idx }}, $event.target.value)"
                                            value="{{ $r['stok_fisik'] }}"
                                            class="w-20 px-2 py-1.5 rounded-lg glass-input text-center font-bold text-white tabular-nums mx-auto block"
                                        />
                                    </td>
                                    <td class="p-3 text-center font-bold tabular-nums {{ $r['selisih'] < 0 ? 'text-up-red' : ($r['selisih'] > 0 ? 'text-up-mint' : 'text-ink-400') }}">
                                        {{ $r['selisih'] > 0 ? "+{$r['selisih']}" : $r['selisih'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex gap-3">
                    <button wire:click="$set('showOpnameModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="saveOpname" class="flex-2 py-3 rounded-xl bg-up-primary text-white font-bold text-xs shadow-lg shadow-up-primary/25 cursor-pointer min-h-[44px]">Kirim ke Supervisor untuk Approval</button>
                </div>
            </div>
        </div>
    @endif
</div>
