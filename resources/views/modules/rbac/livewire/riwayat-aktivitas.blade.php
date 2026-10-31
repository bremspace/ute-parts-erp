<div x-data="{
    openRows: {},
    allOpen: false,
    toggle(id) {
        this.openRows[id] = !(this.openRows[id] !== undefined ? this.openRows[id] : this.allOpen);
    },
    isOpen(id) {
        return this.openRows[id] !== undefined ? this.openRows[id] : this.allOpen;
    },
    toggleAll() {
        this.allOpen = !this.allOpen;
        this.openRows = {};
    }
}" class="space-y-4">
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
        {{-- ===== Toolbar & Pencarian Kontekstual ===== --}}
        <div class="space-y-2.5">
            <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-2.5">
                {{-- Pencarian Bebas / Kontekstual Multi-Kolom --}}
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-ink-400">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input type="text"
                        wire:model.live.debounce.400ms="filterCari"
                        placeholder="Pencarian audit: no. transaksi, tiket, PO, GRN, retur, nama produk, pelanggan, user, catatan..."
                        class="w-full pl-9 pr-8 py-2 rounded-xl glass-input text-xs font-medium text-white placeholder-ink-400 focus:border-up-primary min-h-[44px]" />
                    @if($filterCari !== '')
                        <button type="button" wire:click="$set('filterCari', '')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-ink-400 hover:text-white cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    @endif
                </div>

                {{-- Aksi Cepat: Toggle Semua Rincian & Export Excel --}}
                <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
                    <button type="button"
                        @click="toggleAll()"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 hover:text-white font-bold text-xs cursor-pointer min-h-[44px] transition active:scale-[0.98]">
                        <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="allOpen ? 'rotate-180 text-up-mint' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                        <span x-text="allOpen ? 'Tutup Semua Rincian' : 'Buka Semua Rincian'"></span>
                    </button>

                    <button type="button"
                        wire:click="exportExcel"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600/20 hover:bg-emerald-600/30 border border-emerald-500/30 text-emerald-400 font-bold text-xs cursor-pointer min-h-[44px] transition active:scale-[0.98] disabled:opacity-50">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span wire:loading.remove wire:target="exportExcel">Export Excel</span>
                        <span wire:loading wire:target="exportExcel">Menyiapkan File...</span>
                    </button>
                </div>
            </div>

            {{-- Filter Parameter Spesifik --}}
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
                        <input type="text" wire:model.live.debounce.400ms="filterProduk" placeholder="Nama / SKU / ID"
                            class="px-2.5 py-2 rounded-lg glass-input text-xs font-medium w-40 min-h-[44px]" />
                    </div>
                @endif
                <div class="flex-shrink-0">
                    <label class="block text-[10px] text-ink-400 font-semibold mb-1">Pengguna</label>
                    <input type="text" wire:model.live.debounce.400ms="filterUser" placeholder="Nama / ID / sistem"
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
                        Reset Filter
                    </button>
                </div>
            </div>
        </div>

        {{-- ===== Tabel riwayat ===== --}}
        <x-prism.data-table :headers="empty($tipe) ? ['Waktu & Cabang', 'Entitas / Dokumen', 'Aksi / Alur', 'Pelaku', 'Ringkasan & Rincian Perubahan'] : ['Waktu', 'Aksi / Alur', 'Pelaku', 'Ringkasan & Rincian Perubahan']">
            @forelse($aktivitas as $baris)
                @php
                    $isTrx = $baris->is_transaksi;
                    $itemsList = $baris->items_list;
                    $itemsTukarList = $baris->items_tukar_list;
                    $daftarMutasi = $baris->daftar_mutasi;
                    $ringkasan = $baris->ringkasan;
                    $props = $baris->normalized_properties;
                    $hasDetails = !empty($daftarMutasi) || !empty($itemsList) || !empty($itemsTukarList);
                    $totalDetailCount = count($daftarMutasi) + count($itemsList) + count($itemsTukarList);
                @endphp
                <tr class="hover:bg-white/[0.02] transition-colors text-xs align-top">
                    {{-- 1. Waktu & Cabang --}}
                    <td class="py-3.5 px-4 tabular-nums text-ink-300 whitespace-nowrap">
                        <span class="block font-semibold text-white">{{ $baris->created_at?->format('d/m/Y') }}</span>
                        <span class="text-[10px] text-ink-400 font-mono">{{ $baris->created_at?->format('H:i:s') }} WIB</span>
                        @if(empty($tipe) && $baris->cabang_id)
                            <span class="inline-block mt-1 px-1.5 py-0.5 rounded text-[9px] font-semibold bg-white/5 border border-white/10 text-ink-300">
                                {{ $baris->cabang_nama ?: 'Cabang #'.$baris->cabang_id }}
                            </span>
                        @endif
                    </td>

                    {{-- 2. Entitas / Dokumen (jika tabel umum) --}}
                    @if(empty($tipe))
                        <td class="py-3.5 px-4 font-medium text-ink-200">
                            @if($isTrx && $baris->no_referensi)
                                <span class="text-up-primary font-bold block">{{ $baris->tipe_transaksi_label }}</span>
                                <span class="text-up-mint text-[11px] font-mono font-bold">{{ $baris->no_referensi }}</span>
                            @else
                                @php
                                    $namaSubjek = null;
                                    if ($baris->subject_type && $baris->subject_id) {
                                        $baseSubj = class_basename($baris->subject_type);
                                        $keyMap = match($baseSubj) {
                                            'Produk' => 'produk_id',
                                            'SkuVariant' => 'sku_variant_id',
                                            'Pelanggan' => 'pelanggan_id',
                                            'Supplier' => 'supplier_id',
                                            'Cabang' => 'cabang_id',
                                            'Gudang' => 'gudang_id',
                                            'User' => 'user_id',
                                            'Karyawan' => 'karyawan_id',
                                            'Brand' => 'brand_id',
                                            'KategoriProduk' => 'kategori_id',
                                            'KualitasProduk' => 'kualitas_id',
                                            'SatuanUnit' => 'satuan_id',
                                            'TipeHp' => 'tipe_hp_id',
                                            default => null,
                                        };
                                        if ($keyMap) {
                                            $namaSubjek = \App\Modules\Rbac\Livewire\RiwayatAktivitas::resolveNamaEntitas($keyMap, $baris->subject_id);
                                        }
                                    }
                                @endphp
                                <span class="text-up-primary font-semibold block">{{ \App\Modules\Rbac\Livewire\RiwayatAktivitas::labelEntitas($baris->subject_type ?? null) }}</span>
                                @if($namaSubjek)
                                    <span class="text-white text-[11px] font-medium block truncate max-w-[200px]" title="{{ $namaSubjek }}">{{ $namaSubjek }}</span>
                                @else
                                    <span class="text-ink-400 text-[10px] font-mono">ID: #{{ $baris->subject_id }}</span>
                                @endif
                            @endif
                        </td>
                    @endif

                    {{-- 3. Aksi / Alur --}}
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

                    {{-- 4. Pelaku --}}
                    <td class="py-3.5 px-4 text-ink-200 font-medium whitespace-nowrap">
                        <div class="flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-up-primary"></span>
                            <span class="font-semibold">{{ $baris->causer_nama }}</span>
                        </div>
                        @if($baris->ip)
                            <div class="mt-1 flex items-center gap-1 text-[10px] text-ink-400 font-mono" title="{{ $baris->user_agent }}">
                                <span class="text-[9px] text-ink-500">IP:</span>
                                <span>{{ $baris->ip }}</span>
                            </div>
                        @endif
                    </td>

                    {{-- 5. Ringkasan & Rincian Mutasi (Expand / Collapse) --}}
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

                            {{-- Tombol Expand / Collapse Per Baris --}}
                            @if($hasDetails)
                                <div class="pt-0.5">
                                    <button type="button"
                                        @click="toggle({{ $baris->id }})"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-[11px] font-semibold text-ink-300 hover:text-white transition cursor-pointer active:scale-95">
                                        <svg class="w-3 h-3 text-ink-400 transition-transform duration-200" :class="isOpen({{ $baris->id }}) ? 'rotate-180 text-up-mint' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                        <span x-text="isOpen({{ $baris->id }}) ? 'Sembunyikan Rincian' : 'Lihat Rincian ({{ $totalDetailCount }} data)'"></span>
                                    </button>
                                </div>

                                {{-- Container Rincian Perubahan & Item Produk (Collapsible) --}}
                                <div x-show="isOpen({{ $baris->id }})" x-collapse class="space-y-3 pt-2">
                                    {{-- 2. Rincian Mutasi Kolom (Diff Sebelum vs Sesudah Tanpa Pemotongan) --}}
                                    @if(!empty($daftarMutasi))
                                        <div class="rounded-xl border border-white/10 overflow-hidden bg-black/25">
                                            <div class="px-3 py-1.5 bg-white/5 border-b border-white/10 flex items-center justify-between">
                                                <span class="text-[11px] font-bold text-ink-200 flex items-center gap-1.5">
                                                    <span>🔍</span>
                                                    <span>Rincian Perubahan Nilai ({{ count($daftarMutasi) }} Kolom)</span>
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
                <span class="tabular-nums">Halaman {{ $aktivitas->currentPage() }} / {{ max(1, $aktivitas->lastPage()) }} (Total {{ $aktivitas->total() }} aktivitas)</span>
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
