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
        <div class="flex items-end gap-2 overflow-x-auto scrollbar-none py-1 -my-1 flex-nowrap md:flex-wrap">
            @if(empty($entityId))
                <div class="flex-shrink-0">
                    <label class="block text-[10px] text-ink-400 font-semibold mb-1">Modul / Entitas</label>
                    <select wire:model.live="tipe" class="px-2.5 py-2 rounded-lg glass-input text-xs font-medium min-h-[44px]">
                        @foreach($opsiTipe as $kode => $label)
                            <option value="{{ $kode }}" class="bg-ink-900">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-shrink-0">
                    <label class="block text-[10px] text-ink-400 font-semibold mb-1">Alur Transaksi</label>
                    <select wire:model.live="filterKategoriTransaksi" class="px-2.5 py-2 rounded-lg glass-input text-xs font-medium min-h-[44px]">
                        @foreach($opsiKategoriTransaksi as $kode => $label)
                            <option value="{{ $kode }}" class="bg-ink-900">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-shrink-0">
                    <label class="block text-[10px] text-ink-400 font-semibold mb-1">Cari Produk / SKU</label>
                    <input type="text" wire:model.live.debounce.400ms="filterProduk" placeholder="Nama / SKU / ID produk"
                        class="px-2.5 py-2 rounded-lg glass-input text-xs font-medium w-44 min-h-[44px]" />
                </div>
            @endif
            <div class="flex-shrink-0">
                <label class="block text-[10px] text-ink-400 font-semibold mb-1">Pengguna</label>
                <input type="text" wire:model.live.debounce.400ms="filterUser" placeholder="Nama / id / sistem"
                    class="px-2.5 py-2 rounded-lg glass-input text-xs font-medium w-36 min-h-[44px]" />
            </div>
            <div class="flex-shrink-0">
                <label class="block text-[10px] text-ink-400 font-semibold mb-1">Aksi</label>
                <select wire:model.live="filterAksi" class="px-2.5 py-2 rounded-lg glass-input text-xs font-medium min-h-[44px]">
                    <option value="">Semua aksi</option>
                    @foreach($opsiAksi as $kode => $label)
                        <option value="{{ $kode }}" class="bg-ink-900">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-shrink-0">
                <label class="block text-[10px] text-ink-400 font-semibold mb-1">Dari</label>
                <input type="date" wire:model.live="filterDari" class="px-2.5 py-2 rounded-lg glass-input text-xs font-medium min-h-[44px]" />
            </div>
            <div class="flex-shrink-0">
                <label class="block text-[10px] text-ink-400 font-semibold mb-1">Sampai</label>
                <input type="date" wire:model.live="filterSampai" class="px-2.5 py-2 rounded-lg glass-input text-xs font-medium min-h-[44px]" />
            </div>
            <div class="flex-shrink-0">
                <button wire:click="resetFilter"
                    class="px-3.5 py-2 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 font-bold text-xs cursor-pointer whitespace-nowrap min-h-[44px] active:scale-[0.97] transition">
                    Reset
                </button>
            </div>
        </div>

        {{-- ===== Tabel riwayat ===== --}}
        <x-prism.data-table :headers="empty($tipe) ? ['Waktu', 'Entitas / Dokumen', 'Aksi / Alur', 'Oleh', 'Ringkasan & Rincian Perubahan'] : ['Waktu', 'Aksi / Alur', 'Oleh', 'Ringkasan & Rincian Perubahan']">
            @forelse($aktivitas as $baris)
                @php
                    $isTrx = $baris->is_transaksi;
                    $itemsList = $baris->items_list;
                    $changes = $baris->attribute_changes ?? collect();
                    $sesudah = $changes->get('attributes') ?? [];
                    $sebelum = $changes->get('old') ?? [];
                    $lewati = ['id', 'created_at', 'updated_at'];

                    $kunciSemua = array_values(array_unique(array_merge(array_keys($sesudah), array_keys($sebelum))));
                    $kunciSemua = array_values(array_filter($kunciSemua, fn ($k) => ! in_array($k, $lewati, true)));
                    $kunci = array_values(array_slice($kunciSemua, 0, 6));
                    $sisa = max(0, count($kunciSemua) - count($kunci));
                @endphp
                <tr class="hover:bg-white/[0.02] transition-colors text-xs align-top">
                    <td class="py-3.5 px-4 tabular-nums text-ink-300 whitespace-nowrap">
                        <span class="block font-semibold text-white">{{ $baris->created_at?->format('d/m/Y') }}</span>
                        <span class="text-[10px] text-ink-400">{{ $baris->created_at?->format('H:i:s') }}</span>
                    </td>
                    @if(empty($tipe))
                        <td class="py-3.5 px-4 font-medium text-ink-200 whitespace-nowrap">
                            @if($isTrx && $baris->no_referensi)
                                <span class="text-up-primary font-bold block">{{ $baris->tipe_transaksi_label }}</span>
                                <span class="text-up-mint text-[11px] font-mono font-bold">{{ $baris->no_referensi }}</span>
                            @else
                                <span class="text-up-primary font-semibold block">{{ \App\Modules\Rbac\Livewire\RiwayatAktivitas::labelEntitas($baris->subject_type ?? null) }}</span>
                                <span class="text-ink-400 text-[10px] font-mono">ID: #{{ $baris->subject_id }}</span>
                            @endif
                        </td>
                    @endif
                    <td class="py-3.5 px-4">
                        @php
                            $badgeColor = match($baris->event) {
                                'penjualan' => 'bg-up-mint/15 text-up-mint border border-up-mint/30',
                                'void' => 'bg-up-red/15 text-up-red border border-up-red/30',
                                'retur_penjualan', 'retur_pembelian' => 'bg-up-accent/15 text-up-accent border border-up-accent/30',
                                'po', 'po_dibuat' => 'bg-blue-500/15 text-blue-400 border border-blue-500/30',
                                'grn', 'grn_diterima' => 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30',
                                'transfer_stok' => 'bg-purple-500/15 text-purple-400 border border-purple-500/30',
                                'stok_opname' => 'bg-amber-500/15 text-amber-400 border border-amber-500/30',
                                'servis' => 'bg-cyan-500/15 text-cyan-400 border border-cyan-500/30',
                                'created' => 'bg-up-mint/10 text-up-mint',
                                'deleted' => 'bg-up-red/10 text-up-red',
                                default => 'bg-up-primary/10 text-up-primary',
                            };
                        @endphp
                        <span class="inline-block px-2.5 py-1 rounded-full text-[10px] font-bold {{ $badgeColor }}">
                            {{ $baris->aksi_label }}
                        </span>
                        @if($isTrx && !empty($tipe) && $baris->no_referensi)
                            <span class="block text-[10px] font-mono text-ink-400 mt-1">{{ $baris->no_referensi }}</span>
                        @endif
                    </td>
                    <td class="py-3.5 px-4 text-ink-200 font-medium whitespace-nowrap">
                        <div class="flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-up-primary"></span>
                            <span class="font-semibold">{{ $baris->causer_nama }}</span>
                        </div>
                    </td>
                    <td class="py-3.5 px-4">
                        @if($isTrx)
                            {{-- Format Transaction-Centric: Narasi Jelas untuk Orang Awam --}}
                            <div class="space-y-2">
                                <p class="text-xs text-ink-100 font-medium leading-relaxed bg-white/[0.02] p-2.5 rounded-xl border border-white/5">
                                    {{ $baris->ringkasan }}
                                </p>

                                @if(!empty($itemsList))
                                    <div x-data="{ openDetail: false }" class="mt-1">
                                        <button type="button" @click="openDetail = !openDetail"
                                            class="text-[11px] font-bold text-up-primary hover:text-up-primary/80 flex items-center gap-1 cursor-pointer transition">
                                            <span x-text="openDetail ? '▲ Tutup Rincian Barang' : '▼ Lihat ' + {{ count($itemsList) }} + ' Barang Terkait'"></span>
                                        </button>
                                        <div x-show="openDetail" x-cloak class="mt-2 p-2 rounded-xl bg-black/30 border border-white/10 space-y-1.5">
                                            <table class="w-full text-left text-[11px]">
                                                <thead>
                                                    <tr class="text-ink-400 border-b border-white/10">
                                                        <th class="pb-1">Produk / Part</th>
                                                        <th class="pb-1 text-center">Qty</th>
                                                        @if(isset($itemsList[0]['harga_satuan']))
                                                            <th class="pb-1 text-right">Harga Satuan</th>
                                                        @endif
                                                        @if(isset($itemsList[0]['kondisi']))
                                                            <th class="pb-1 text-right">Kondisi / Alasan</th>
                                                        @endif
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-white/5">
                                                    @foreach($itemsList as $it)
                                                        <tr>
                                                            <td class="py-1 text-white font-medium">
                                                                {{ $it['nama_produk'] ?? 'Produk' }}
                                                                @if(!empty($it['sku']))
                                                                    <span class="text-[10px] text-ink-400 font-mono block">{{ $it['sku'] }}</span>
                                                                @endif
                                                            </td>
                                                            <td class="py-1 text-center tabular-nums text-up-mint font-bold">
                                                                {{ $it['qty'] ?? ($it['jumlah'] ?? 1) }}
                                                            </td>
                                                            @if(isset($it['harga_satuan']))
                                                                <td class="py-1 text-right tabular-nums text-ink-300">
                                                                    Rp {{ number_format((float) $it['harga_satuan'], 0, ',', '.') }}
                                                                </td>
                                                            @endif
                                                            @if(isset($it['kondisi']) || isset($it['alasan']))
                                                                <td class="py-1 text-right text-[10px] text-ink-300">
                                                                    {{ ucfirst($it['kondisi'] ?? '') }} {{ !empty($it['alasan']) ? '('.$it['alasan'].')' : '' }}
                                                                </td>
                                                            @endif
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @else
                            {{-- Format Entity CRUD Standar --}}
                            @empty($kunci)
                                <span class="text-ink-500">Tidak ada perubahan detail</span>
                            @else
                                <div class="space-y-1">
                                    @foreach($kunci as $key)
                                        <div class="flex flex-wrap gap-1.5 items-baseline">
                                            <span class="font-medium text-[11px] text-ink-300">{{ \App\Modules\Rbac\Livewire\RiwayatAktivitas::labelKolom($key) }}:</span>
                                            @if($baris->event === 'created')
                                                <span class="font-semibold text-white tabular-nums">{{ \App\Modules\Rbac\Livewire\RiwayatAktivitas::formatNilai($sesudah[$key] ?? null, $key) }}</span>
                                            @elseif($baris->event === 'deleted')
                                                <span class="text-up-red tabular-nums line-through">{{ \App\Modules\Rbac\Livewire\RiwayatAktivitas::formatNilai($sebelum[$key] ?? null, $key) }}</span>
                                            @else
                                                <span class="text-up-red tabular-nums line-through">{{ \App\Modules\Rbac\Livewire\RiwayatAktivitas::formatNilai($sebelum[$key] ?? null, $key) }}</span>
                                                <span class="text-ink-500">→</span>
                                                <span class="text-up-mint font-semibold tabular-nums">{{ \App\Modules\Rbac\Livewire\RiwayatAktivitas::formatNilai($sesudah[$key] ?? null, $key) }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                    @if($sisa > 0)
                                        <p class="text-[10px] text-ink-500">+{{ $sisa }} data lainnya</p>
                                    @endif
                                </div>
                            @endempty
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="py-10 text-center text-ink-400 text-xs">
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
