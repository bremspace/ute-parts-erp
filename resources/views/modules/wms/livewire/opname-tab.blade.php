<div>
    <!-- TAB 3: STOCK OPNAME -->
        <div class="space-y-4">
            <x-prism.data-table :headers="['No. Opname', 'Gudang', 'Pembuat', 'Total Item', 'Tanggal', 'Status', 'Aksi Supervisor']">
                @forelse($opnames as $opn)
                    <tr class="hover:bg-white/[0.02] transition-colors text-xs">
                        <td class="py-3.5 px-4 font-mono font-bold text-white">
                            <button
                                type="button"
                                wire:click="bukaDetail({{ $opn->id }})"
                                class="hover:text-up-primary hover:underline transition-colors text-left font-mono font-bold cursor-pointer"
                            >
                                {{ $opn->no_opname }}
                            </button>
                        </td>
                        <td class="py-3.5 px-4 text-ink-300 font-medium">{{ $opn->gudang?->nama }}</td>
                        <td class="py-3.5 px-4 text-ink-400">{{ $opn->pembuat?->name }}</td>
                        <td class="py-3.5 px-4 text-white font-bold tabular-nums">{{ $opn->items_count }} sparepart</td>
                        <td class="py-3.5 px-4 text-ink-400">{{ $opn->created_at->format('d/m/Y H:i') }}</td>
                        <td class="py-3.5 px-4">
                            <x-prism.status-pill :status="$opn->status" />
                        </td>
                        <td class="py-3.5 px-4">
                            <div class="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    wire:click="bukaDetail({{ $opn->id }})"
                                    class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-ink-200 font-semibold text-xs transition-all cursor-pointer"
                                >
                                    Detail
                                </button>
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
                            </div>
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

                <!-- Blind Opname Notice -->
                <div class="mb-3 px-3.5 py-2.5 rounded-xl bg-teal-500/10 border border-teal-500/20 text-xs text-teal-300 flex items-center gap-2">
                    <span class="text-sm">🔒</span>
                    <div>
                        <span class="font-bold">Mode Blind Opname Aktif:</span>
                        <span class="text-ink-300">Stok sistem disembunyikan agar pelaksana menghitung unit fisik riil secara independen tanpa bias.</span>
                    </div>
                </div>

                <!-- Opname Spreadsheet Table (Blind Opname) -->
                <div class="max-h-72 overflow-y-auto mb-4 rounded-xl border border-white/10 overflow-hidden">
                    <table class="w-full text-xs text-left text-ink-100">
                        <thead class="bg-white/5 text-ink-400 uppercase text-[10px] font-semibold sticky top-0 bg-ink-900">
                            <tr>
                                <th class="p-3 w-12 text-center">No</th>
                                <th class="p-3">Produk</th>
                                <th class="p-3 text-center w-36">Hitungan Fisik Riil</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach($opnameRows as $idx => $r)
                                <tr>
                                    <td class="p-3 text-center text-ink-400 tabular-nums">{{ $loop->iteration }}</td>
                                    <td class="p-3 font-medium text-white">{{ $r['nama'] }}</td>
                                    <td class="p-2 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <input
                                                type="number"
                                                min="0"
                                                placeholder="0"
                                                wire:change="updateOpnameFisik({{ $idx }}, $event.target.value)"
                                                value="{{ $r['stok_fisik'] }}"
                                                class="w-24 px-2.5 py-1.5 rounded-lg glass-input text-center font-bold text-white tabular-nums block min-h-[38px]"
                                            />
                                            <span class="text-[11px] text-ink-400">unit</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col sm:flex-row gap-2.5 sm:gap-3">
                    <button wire:click="$set('showOpnameModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px] transition active:scale-[0.97]">Batal</button>
                    <button wire:click="saveOpname" class="flex-1 sm:flex-2 py-3 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs shadow-lg shadow-up-primary/25 cursor-pointer min-h-[44px] transition active:scale-[0.97]">Kirim ke Supervisor untuk Approval</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: DETAIL STOCK OPNAME & AUDIT MUTASI -->
    @if($showDetailModal && $selectedOpname)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-3xl glass-panel p-6 rounded-3xl border border-white/10 shadow-2xl relative max-h-[90vh] overflow-y-auto space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-white/10">
                    <div class="flex items-center gap-3">
                        <h3 class="text-base font-bold text-white font-mono">{{ $selectedOpname->no_opname }}</h3>
                        <x-prism.status-pill :status="$selectedOpname->status" />
                    </div>
                    <button type="button" wire:click="tutupDetail" class="text-ink-400 hover:text-white text-base">✕</button>
                </div>

                <!-- Info Header Ringkasan -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                        <span class="text-ink-400 block text-[11px]">Lokasi Gudang</span>
                        <strong class="text-white">{{ $selectedOpname->gudang?->nama ?? '-' }}</strong>
                    </div>
                    <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                        <span class="text-ink-400 block text-[11px]">Filter Rak</span>
                        <strong class="text-white">{{ $selectedOpname->rak?->kode ?? 'Semua Rak' }}</strong>
                    </div>
                    <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                        <span class="text-ink-400 block text-[11px]">Petugas Input</span>
                        <strong class="text-white">{{ $selectedOpname->pembuat?->name ?? '-' }}</strong>
                        <span class="text-[10px] text-ink-400 block">{{ $selectedOpname->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                        <span class="text-ink-400 block text-[11px]">Approval Supervisor</span>
                        <strong class="text-white">{{ $selectedOpname->approver?->name ?? '-' }}</strong>
                        @if($selectedOpname->tanggal_approval)
                            <span class="text-[10px] text-ink-400 block">{{ $selectedOpname->tanggal_approval->format('d/m/Y H:i') }}</span>
                        @endif
                    </div>
                </div>

                @if($selectedOpname->catatan)
                    <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5 text-xs">
                        <span class="text-ink-400 block text-[11px] font-semibold">Catatan:</span>
                        <p class="text-ink-200 mt-0.5">{{ $selectedOpname->catatan }}</p>
                    </div>
                @endif

                <!-- Spreadsheet Hasil Opname -->
                <div>
                    <h4 class="text-xs font-bold text-white uppercase tracking-wider mb-2">Hasil Perhitungan Opname Fisik</h4>
                    <div class="rounded-xl border border-white/10 overflow-hidden">
                        <table class="w-full text-xs text-left text-ink-100">
                            <thead class="bg-white/5 text-ink-400 uppercase text-[10px] font-semibold">
                                <tr>
                                    <th class="p-3">Produk</th>
                                    <th class="p-3">SKU / Varian</th>
                                    <th class="p-3">Rak</th>
                                    <th class="p-3 text-center">Stok Sistem</th>
                                    <th class="p-3 text-center">Stok Fisik</th>
                                    <th class="p-3 text-center">Selisih</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @foreach($selectedOpname->items as $item)
                                    <tr>
                                        <td class="p-3 font-medium text-white">{{ $item->produk?->nama ?? '-' }}</td>
                                        <td class="p-3 text-ink-300 font-mono text-[11px]">
                                            {{ $item->skuVariant?->sku ?? '-' }}
                                            @if($item->skuVariant?->nama_varian)
                                                <span class="text-ink-400">({{ $item->skuVariant->nama_varian }})</span>
                                            @endif
                                        </td>
                                        <td class="p-3 text-ink-300">{{ $item->rak?->kode ?? '-' }}</td>
                                        <td class="p-3 text-center tabular-nums text-ink-300">{{ $item->stok_sistem }}</td>
                                        <td class="p-3 text-center tabular-nums font-bold text-white">{{ $item->stok_fisik }}</td>
                                        <td class="p-3 text-center tabular-nums font-bold {{ $item->selisih < 0 ? 'text-up-red' : ($item->selisih > 0 ? 'text-up-mint' : 'text-ink-400') }}">
                                            {{ $item->selisih > 0 ? "+{$item->selisih}" : $item->selisih }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Audit Mutasi Stok (StokLog) Terkait -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-xs font-bold text-white uppercase tracking-wider">Audit Mutasi Stok (StokLog)</h4>
                        <span class="text-[11px] text-ink-400">{{ $mutasiLogs->count() }} penyesuaian tercatat</span>
                    </div>

                    @if($mutasiLogs->isEmpty())
                        <div class="p-4 rounded-xl bg-white/[0.02] border border-white/5 text-center text-xs text-ink-400">
                            Belum ada penyesuaian stok tercatat (menunggu approval atau tidak ada selisih fisik).
                        </div>
                    @else
                        <div class="rounded-xl border border-white/10 overflow-hidden">
                            <table class="w-full text-xs text-left text-ink-100">
                                <thead class="bg-white/5 text-ink-400 uppercase text-[10px] font-semibold">
                                    <tr>
                                        <th class="p-3">Waktu</th>
                                        <th class="p-3">Gudang</th>
                                        <th class="p-3">Produk</th>
                                        <th class="p-3 text-center">Sebelum</th>
                                        <th class="p-3 text-center">Perubahan</th>
                                        <th class="p-3 text-center">Setelah</th>
                                        <th class="p-3">Approver</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($mutasiLogs as $log)
                                        <tr>
                                            <td class="p-3 text-ink-400 whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                                            <td class="p-3 text-ink-300">{{ $log->gudang?->nama ?? '-' }}</td>
                                            <td class="p-3 text-white font-medium">{{ $log->produk?->nama ?? '-' }}</td>
                                            <td class="p-3 text-center tabular-nums text-ink-300">{{ $log->jumlah_sebelum }}</td>
                                            <td class="p-3 text-center tabular-nums font-bold {{ $log->perubahan > 0 ? 'text-up-mint' : 'text-up-red' }}">
                                                {{ $log->perubahan > 0 ? "+{$log->perubahan}" : $log->perubahan }}
                                            </td>
                                            <td class="p-3 text-center tabular-nums font-bold text-white">{{ $log->jumlah_setelah }}</td>
                                            <td class="p-3 text-ink-400">{{ $log->user?->name ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end pt-2">
                    <button type="button" wire:click="tutupDetail" class="px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/15 text-white font-semibold text-xs cursor-pointer transition">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
