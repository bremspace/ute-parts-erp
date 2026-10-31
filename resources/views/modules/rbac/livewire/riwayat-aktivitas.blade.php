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
        <x-prism.data-table :headers="empty($tipe) ? ['Waktu & Cabang', 'Entitas / Dokumen', 'Aksi / Alur', 'Pelaku', 'Ringkasan & Rincian Mutasi'] : ['Waktu', 'Aksi / Alur', 'Pelaku', 'Ringkasan & Rincian Mutasi']">
            @forelse($aktivitas as $baris)
                @php
                    $isTrx = $baris->is_transaksi;
                    $itemsList = $baris->items_list;
                    $itemsTukarList = $baris->items_tukar_list;
                    $daftarMutasi = $baris->daftar_mutasi;
                    $ringkasan = $baris->ringkasan;
                    $props = $baris->normalized_properties;
                @endphp
                <tr class="hover:bg-white/[0.02] transition-colors text-xs align-top">
                    <td class="py-3.5 px-4 tabular-nums text-ink-300 whitespace-nowrap">
                        <span class="block font-semibold text-white">{{ $baris->created_at?->format('d/m/Y') }}</span>
                        <span class="text-[10px] text-ink-400 font-mono">{{ $baris->created_at?->format('H:i:s') }} WIB</span>
                        @if(empty($tipe) && $baris->cabang_id)
                            <span class="inline-block mt-1 px-1.5 py-0.5 rounded text-[9px] font-semibold bg-white/5 border border-white/10 text-ink-300">
                                {{ $baris->cabang_nama ?: 'Cabang #'.$baris->cabang_id }}
                            </span>
                        @endif
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
                                'retur_penjualan', 'return_penjualan', 'retur_pembelian', 'return_pembelian' => 'bg-up-accent/15 text-up-accent border border-up-accent/30',
                                'po', 'po_dibuat' => 'bg-blue-500/15 text-blue-400 border border-blue-500/30',
                                'grn', 'grn_diterima' => 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30',
                                'transfer_stok' => 'bg-purple-500/15 text-purple-400 border border-purple-500/30',
                                'stok_opname' => 'bg-amber-500/15 text-amber-400 border border-amber-500/30',
                                'servis' => 'bg-cyan-500/15 text-cyan-400 border border-cyan-500/30',
                                'created' => 'bg-up-mint/10 text-up-mint border border-up-mint/20',
                                'deleted' => 'bg-up-red/10 text-up-red border border-up-red/20',
                                default => 'bg-up-primary/10 text-up-primary border border-up-primary/20',
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
                    <td class="py-3.5 px-4 min-w-[320px]">
                        <div class="space-y-2.5">
                            {{-- 1. Narasi Manusiawi & Ringkasan Aktivitas --}}
                            @if(!empty($ringkasan))
                                <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5 space-y-1.5">
                                    <div class="flex items-start gap-2">
                                        <span class="text-xs mt-0.5">📝</span>
                                        <div class="flex-1">
                                            <p class="text-xs text-ink-100 font-medium leading-relaxed">
                                                {{ $ringkasan }}
                                            </p>
                                            {{-- Metadata Pelengkap Transaksi / Aksi --}}
                                            <div class="flex flex-wrap gap-1.5 mt-2">
                                                @if(!empty($props['metode_bayar']))
                                                    <span class="px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-[10px] text-ink-300">
                                                        Metode: <strong class="text-white">{{ strtoupper(str_replace('_', ' ', (string) $props['metode_bayar'])) }}</strong>
                                                    </span>
                                                @endif
                                                @if(!empty($props['tipe_penyelesaian']))
                                                    <span class="px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-[10px] text-ink-300">
                                                        Penyelesaian: <strong class="text-up-mint">{{ $props['tipe_penyelesaian'] === 'tukar_produk' ? 'Tukar Produk' : 'Refund Dana' }}</strong>
                                                    </span>
                                                @endif
                                                @if(!empty($props['gudang_asal']) && !empty($props['gudang_tujuan']))
                                                    <span class="px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-[10px] text-ink-300">
                                                        Alur Gudang: <strong class="text-white">{{ $props['gudang_asal'] }}</strong> ➔ <strong class="text-up-mint">{{ $props['gudang_tujuan'] }}</strong>
                                                    </span>
                                                @endif
                                                @if(!empty($props['alasan']))
                                                    <span class="px-2 py-0.5 rounded-md bg-up-red/10 border border-up-red/25 text-[10px] text-up-red">
                                                        Alasan: <strong>{{ $props['alasan'] }}</strong>
                                                    </span>
                                                @endif
                                                @if(!empty($props['selisih_nominal']) && (float)$props['selisih_nominal'] > 0)
                                                    <span class="px-2 py-0.5 rounded-md bg-up-mint/10 border border-up-mint/25 text-[10px] text-up-mint">
                                                        Selisih Bayar: <strong>Rp {{ number_format((float)$props['selisih_nominal'], 0, ',', '.') }}</strong>
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- 2. Rincian Mutasi Kolom (Diff Sebelum vs Sesudah Tanpa Pemotongan) --}}
                            @if(!empty($daftarMutasi))
                                <div class="rounded-xl border border-white/10 overflow-hidden bg-black/25">
                                    <div class="px-3 py-1.5 bg-white/5 border-b border-white/10 flex items-center justify-between">
                                        <span class="text-[11px] font-bold text-ink-200 flex items-center gap-1.5">
                                            <span>🔍</span>
                                            <span>Rincian Perubahan Data ({{ count($daftarMutasi) }} Kolom)</span>
                                        </span>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-xs">
                                            <thead class="bg-white/[0.02] text-[10px] uppercase font-semibold text-ink-400 border-b border-white/5">
                                                <tr>
                                                    <th class="py-2 px-3 w-1/3">Kolom / Informasi</th>
                                                    @if($baris->event === 'created')
                                                        <th class="py-2 px-3">Nilai Diinput</th>
                                                    @elseif($baris->event === 'deleted')
                                                        <th class="py-2 px-3">Nilai Sebelum Dihapus</th>
                                                    @else
                                                        <th class="py-2 px-3 w-1/3">Nilai Semula (Sebelum)</th>
                                                        <th class="py-2 px-3 w-1/3">Nilai Baru (Sesudah)</th>
                                                    @endif
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-white/5">
                                                @foreach($daftarMutasi as $m)
                                                    <tr class="hover:bg-white/[0.02] transition">
                                                        <td class="py-2 px-3">
                                                            <span class="font-semibold text-white block">{{ $m['label'] }}</span>
                                                            <span class="text-[10px] text-ink-500 font-mono">{{ $m['kunci'] }}</span>
                                                        </td>
                                                        @if($m['tipe'] === 'tambah')
                                                            <td class="py-2 px-3 text-up-mint font-semibold tabular-nums">
                                                                <div class="inline-block p-1.5 rounded-lg bg-up-mint/10 border border-up-mint/20 text-up-mint">
                                                                    {{ $m['sesudah'] }}
                                                                </div>
                                                            </td>
                                                        @elseif($m['tipe'] === 'hapus')
                                                            <td class="py-2 px-3 text-up-red font-medium tabular-nums">
                                                                <div class="inline-block p-1.5 rounded-lg bg-up-red/10 border border-up-red/20 text-up-red line-through">
                                                                    {{ $m['sebelum'] }}
                                                                </div>
                                                            </td>
                                                        @else
                                                            <td class="py-2 px-3 tabular-nums">
                                                                <div class="p-1.5 rounded-lg bg-white/[0.02] border border-white/5 text-ink-300">
                                                                    <span class="text-[10px] text-ink-500 block uppercase font-bold">Semula</span>
                                                                    <span class="font-medium text-ink-200">{{ $m['sebelum'] }}</span>
                                                                </div>
                                                            </td>
                                                            <td class="py-2 px-3 tabular-nums">
                                                                <div class="p-1.5 rounded-lg bg-up-mint/10 border border-up-mint/20 text-up-mint">
                                                                    <span class="text-[10px] text-up-mint/70 block uppercase font-bold">Menjadi</span>
                                                                    <span class="font-semibold text-up-mint flex items-center gap-1">
                                                                        <span>➔</span>
                                                                        <span>{{ $m['sesudah'] }}</span>
                                                                    </span>
                                                                </div>
                                                            </td>
                                                        @endif
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif

                            {{-- 3. Rincian Barang / Produk Transaksi (Lengkap Tanpa Pemotongan) --}}
                            @if(!empty($itemsList))
                                <div class="rounded-xl border border-white/10 overflow-hidden bg-black/25">
                                    <div class="px-3 py-1.5 bg-white/5 border-b border-white/10 flex items-center justify-between">
                                        <span class="text-[11px] font-bold text-ink-200 flex items-center gap-1.5">
                                            <span>📦</span>
                                            <span>Rincian Barang Terlibat ({{ count($itemsList) }} Jenis Barang)</span>
                                        </span>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-xs">
                                            <thead class="bg-white/[0.02] text-[10px] uppercase font-semibold text-ink-400 border-b border-white/5">
                                                <tr>
                                                    <th class="py-2 px-3">No</th>
                                                    <th class="py-2 px-3">Produk / Part</th>
                                                    <th class="py-2 px-3 text-center">Qty</th>
                                                    @if(isset($itemsList[0]['stok_sistem']))
                                                        <th class="py-2 px-3 text-center">Stok Sistem</th>
                                                        <th class="py-2 px-3 text-center">Stok Fisik</th>
                                                        <th class="py-2 px-3 text-center">Selisih</th>
                                                    @endif
                                                    @if(isset($itemsList[0]['harga_satuan']))
                                                        <th class="py-2 px-3 text-right">Harga Satuan</th>
                                                        <th class="py-2 px-3 text-right">Subtotal</th>
                                                    @endif
                                                    @if(isset($itemsList[0]['kondisi']) || isset($itemsList[0]['alasan']))
                                                        <th class="py-2 px-3 text-right">Kondisi &amp; Alasan</th>
                                                    @endif
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-white/5">
                                                @foreach($itemsList as $idx => $it)
                                                    <tr class="hover:bg-white/[0.02] transition">
                                                        <td class="py-2 px-3 text-ink-500 tabular-nums">{{ $idx + 1 }}</td>
                                                        <td class="py-2 px-3 font-medium text-white">
                                                            {{ $it['nama_produk'] ?? 'Produk' }}
                                                            @if(!empty($it['sku']))
                                                                <span class="text-[10px] text-ink-400 font-mono block">{{ $it['sku'] }}</span>
                                                            @endif
                                                        </td>
                                                        <td class="py-2 px-3 text-center tabular-nums text-up-mint font-bold">
                                                            {{ $it['qty'] ?? ($it['jumlah'] ?? 1) }} unit
                                                        </td>
                                                        @if(isset($it['stok_sistem']))
                                                            <td class="py-2 px-3 text-center tabular-nums text-ink-300">{{ $it['stok_sistem'] }}</td>
                                                            <td class="py-2 px-3 text-center tabular-nums text-white font-semibold">{{ $it['stok_fisik'] }}</td>
                                                            <td class="py-2 px-3 text-center tabular-nums font-bold {{ (int)($it['selisih'] ?? 0) === 0 ? 'text-ink-400' : ((int)($it['selisih'] ?? 0) > 0 ? 'text-up-mint' : 'text-up-red') }}">
                                                                {{ (int)($it['selisih'] ?? 0) > 0 ? '+'.$it['selisih'] : $it['selisih'] }}
                                                            </td>
                                                        @endif
                                                        @if(isset($it['harga_satuan']))
                                                            <td class="py-2 px-3 text-right tabular-nums text-ink-300">
                                                                Rp {{ number_format((float) $it['harga_satuan'], 0, ',', '.') }}
                                                            </td>
                                                            <td class="py-2 px-3 text-right tabular-nums text-white font-semibold">
                                                                Rp {{ number_format((float) ($it['subtotal'] ?? (($it['qty'] ?? 1) * $it['harga_satuan'])), 0, ',', '.') }}
                                                            </td>
                                                        @endif
                                                        @if(isset($it['kondisi']) || isset($it['alasan']))
                                                            <td class="py-2 px-3 text-right text-[11px] text-ink-300">
                                                                <span class="inline-block px-1.5 py-0.5 rounded bg-white/5 border border-white/10 text-white font-medium">
                                                                    {{ ucfirst($it['kondisi'] ?? 'normal') }}
                                                                </span>
                                                                @if(!empty($it['alasan']))
                                                                    <span class="text-[10px] text-ink-400 block mt-0.5">{{ $it['alasan'] }}</span>
                                                                @endif
                                                            </td>
                                                        @endif
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif

                            {{-- 4. Rincian Barang Pengganti (Tukar Produk) --}}
                            @if(!empty($itemsTukarList))
                                <div class="rounded-xl border border-up-mint/20 overflow-hidden bg-up-mint/[0.03]">
                                    <div class="px-3 py-1.5 bg-up-mint/10 border-b border-up-mint/20 flex items-center justify-between">
                                        <span class="text-[11px] font-bold text-up-mint flex items-center gap-1.5">
                                            <span>🔄</span>
                                            <span>Barang Pengganti / Tukar Produk ({{ count($itemsTukarList) }} Item)</span>
                                        </span>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-xs">
                                            <thead class="bg-white/[0.02] text-[10px] uppercase font-semibold text-ink-400 border-b border-white/5">
                                                <tr>
                                                    <th class="py-2 px-3">No</th>
                                                    <th class="py-2 px-3">Produk Pengganti</th>
                                                    <th class="py-2 px-3 text-center">Qty</th>
                                                    <th class="py-2 px-3 text-right">Harga Satuan</th>
                                                    <th class="py-2 px-3 text-right">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-white/5">
                                                @foreach($itemsTukarList as $idx => $tIt)
                                                    <tr>
                                                        <td class="py-2 px-3 text-ink-500 tabular-nums">{{ $idx + 1 }}</td>
                                                        <td class="py-2 px-3 font-medium text-white">
                                                            {{ $tIt['nama_produk'] ?? 'Produk' }}
                                                            @if(!empty($tIt['sku']))
                                                                <span class="text-[10px] text-ink-400 font-mono block">{{ $tIt['sku'] }}</span>
                                                            @endif
                                                        </td>
                                                        <td class="py-2 px-3 text-center tabular-nums text-up-mint font-bold">
                                                            {{ $tIt['qty'] ?? 1 }} unit
                                                        </td>
                                                        <td class="py-2 px-3 text-right tabular-nums text-ink-300">
                                                            Rp {{ number_format((float) ($tIt['harga_satuan'] ?? 0), 0, ',', '.') }}
                                                        </td>
                                                        <td class="py-2 px-3 text-right tabular-nums text-up-mint font-semibold">
                                                            Rp {{ number_format((float) ($tIt['subtotal'] ?? 0), 0, ',', '.') }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif

                            {{-- Fallback jika tidak ada perubahan spesifik --}}
                            @if(empty($daftarMutasi) && empty($itemsList) && empty($itemsTukarList) && empty($ringkasan))
                                <span class="text-ink-500 text-xs italic">Aktivitas tercatat oleh sistem tanpa perubahan nilai.</span>
                            @endif
                        </div>
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
