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
                                    class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-ink-200 font-semibold text-xs transition-[background-color,color] duration-140 cursor-pointer active:scale-95"
                                >
                                    Detail
                                </button>
                                @if($opn->status === 'menunggu_approval')
                                    @can('wms.approve-opname')
                                        @if($opn->user_id !== auth()->id() || isSuperAdminOrOwner())
                                            <button
                                                wire:click="approveOpname({{ $opn->id }})"
                                                wire:confirm="Setujui stock opname ini? Stok fisik akan disesuaikan dan jurnal penyesuaian akuntansi otomatis diposting."
                                                class="px-3 py-1 rounded-lg bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs transition-[opacity,transform] duration-100 active:scale-95 shadow-md shadow-up-mint/20 cursor-pointer"
                                            >
                                                Approve & Sesuaikan
                                            </button>
                                        @else
                                            <span class="text-ink-400 text-[11px] italic" title="Prinsip 4-Mata: Anda pembuat pengajuan ini. Persetujuan harus dilakukan oleh staf lain atau atasan.">
                                                Menunggu Approval Rekan/Atasan
                                            </span>
                                        @endif
                                    @endcan
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
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-3 sm:p-4 overflow-y-auto"
            x-data="{
                init() {
                    window.addEventListener('focus-scan-input', () => {
                        $nextTick(() => { $refs.opnameSearchInput?.focus(); });
                    });
                    window.addEventListener('focus-active-qty', () => {
                        $nextTick(() => {
                            if ($refs.activeFisikInput) {
                                $refs.activeFisikInput.focus();
                                $refs.activeFisikInput.select();
                            }
                        });
                    });
                }
            }"
        >
            <div class="w-full max-w-4xl glass-panel p-4 sm:p-6 rounded-3xl border border-white/10 shadow-2xl relative my-auto max-h-[92vh] flex flex-col">
                <!-- Header -->
                <div class="flex items-center justify-between pb-3 sm:pb-4 mb-3 border-b border-white/10 shrink-0">
                    <div>
                        <h3 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
                            <span>Input Hasil Stock Opname Fisik</span>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-teal-500/20 text-teal-300 border border-teal-500/30">Opname</span>
                        </h3>
                        <p class="text-[11px] text-ink-400">Verifikasi fisik inventaris gudang secara langsung di lapangan</p>
                    </div>
                    <button wire:click="$set('showOpnameModal', false)" class="p-1.5 rounded-xl text-ink-400 hover:text-white hover:bg-white/5 transition cursor-pointer">✕</button>
                </div>

                <div class="overflow-y-auto flex-1 pr-1 space-y-3.5">
                    <!-- 1. Pilihan Gudang & Filter Target Variatif (Rak, Kategori, Brand, Semua) -->
                    <div class="p-3.5 rounded-2xl bg-white/[0.02] border border-white/5 space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-ink-300 mb-1">Lokasi Gudang yang Di-Opname</label>
                                <select wire:model.live="opnameGudangId" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium min-h-[44px]">
                                    @foreach($gudangs as $g)
                                        <option value="{{ $g->id }}" class="bg-ink-900">{{ $g->nama }} ({{ $g->kode }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-ink-300 mb-1">Tipe Target Stock Opname</label>
                                <div class="grid grid-cols-4 gap-1 p-1 bg-white/5 rounded-xl border border-white/10">
                                    <button
                                        type="button"
                                        wire:click="$set('opnameTipeTarget', 'rak')"
                                        class="py-1.5 px-2 rounded-lg text-xs font-bold transition text-center cursor-pointer {{ $opnameTipeTarget === 'rak' ? 'bg-up-primary text-white shadow' : 'text-ink-300 hover:text-white' }}"
                                    >
                                        Rak
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="$set('opnameTipeTarget', 'kategori')"
                                        class="py-1.5 px-2 rounded-lg text-xs font-bold transition text-center cursor-pointer {{ $opnameTipeTarget === 'kategori' ? 'bg-up-primary text-white shadow' : 'text-ink-300 hover:text-white' }}"
                                    >
                                        Kategori
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="$set('opnameTipeTarget', 'brand')"
                                        class="py-1.5 px-2 rounded-lg text-xs font-bold transition text-center cursor-pointer {{ $opnameTipeTarget === 'brand' ? 'bg-up-primary text-white shadow' : 'text-ink-300 hover:text-white' }}"
                                    >
                                        Brand
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="$set('opnameTipeTarget', 'semua')"
                                        class="py-1.5 px-2 rounded-lg text-xs font-bold transition text-center cursor-pointer {{ $opnameTipeTarget === 'semua' ? 'bg-up-primary text-white shadow' : 'text-ink-300 hover:text-white' }}"
                                    >
                                        Semua
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Dropdown Spesifik Sesuai Target -->
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 pt-1">
                            @if($opnameTipeTarget === 'rak')
                                <div class="flex-1">
                                    <select wire:model.live="opnameRakId" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium min-h-[42px]">
                                        <option value="" class="bg-ink-900">Semua Rak di Gudang Ini</option>
                                        @foreach($raks as $rk)
                                            @if($rk->gudang_id == $opnameGudangId)
                                                <option value="{{ $rk->id }}" class="bg-ink-900">{{ $rk->kode }} - {{ $rk->nama }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                            @elseif($opnameTipeTarget === 'kategori')
                                <div class="flex-1">
                                    <select wire:model.live="opnameKategoriId" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium min-h-[42px]">
                                        <option value="" class="bg-ink-900">Pilih Kategori Produk...</option>
                                        @foreach($kategoris as $kat)
                                            <option value="{{ $kat->id }}" class="bg-ink-900">{{ $kat->nama }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @elseif($opnameTipeTarget === 'brand')
                                <div class="flex-1">
                                    <select wire:model.live="opnameBrandId" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium min-h-[42px]">
                                        <option value="" class="bg-ink-900">Pilih Brand...</option>
                                        @foreach($brands as $b)
                                            <option value="{{ $b->id }}" class="bg-ink-900">{{ $b->nama }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <button
                                type="button"
                                wire:click="muatItemOpname"
                                class="px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-ink-200 border border-white/10 text-xs font-bold min-h-[42px] flex items-center justify-center gap-1.5 transition cursor-pointer active:scale-95 shrink-0"
                            >
                                <svg class="w-3.5 h-3.5 text-up-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                <span>Muat Ulang Item</span>
                            </button>
                        </div>
                    </div>

                    <!-- Blind Opname Notice & Status Ringkasan -->
                    <div class="px-3.5 py-2.5 rounded-xl bg-teal-500/10 border border-teal-500/20 text-xs text-teal-300 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="text-sm">🔒</span>
                            <div>
                                <span class="font-bold">Mode Blind Opname Aktif:</span>
                                <span class="text-ink-300">Stok sistem disembunyikan agar pelaksana menghitung unit fisik riil secara independen tanpa bias.</span>
                            </div>
                        </div>
                        <div class="text-[11px] font-mono font-bold text-teal-300 bg-teal-500/15 px-2.5 py-1 rounded-lg shrink-0 flex items-center gap-2">
                            <span>Progres:</span>
                            <span class="text-white">{{ collect($opnameRows)->filter(fn($r) => is_numeric($r['stok_fisik']))->count() }} / {{ count($opnameRows) }} Item ({{ count($opnameRows) > 0 ? round((collect($opnameRows)->filter(fn($r) => is_numeric($r['stok_fisik']))->count() / count($opnameRows)) * 100) : 0 }}%)</span>
                        </div>
                    </div>

                    <!-- Scan Barcode Terintegrasi Menu Pencarian (Fast Input) -->
                    <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/10 space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-bold text-white flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" /></svg>
                                <span>Pencarian Cepat & Scan Barcode Produk</span>
                            </span>
                            <span class="text-[10px] text-ink-400 font-medium">Scan barcode atau ketik SKU / nama lalu Enter</span>
                        </div>

                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                            <div class="relative flex-1">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <svg class="h-4 w-4 text-ink-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input
                                    type="text"
                                    x-ref="opnameSearchInput"
                                    wire:model.live.debounce.250ms="opnameScanInput"
                                    wire:keydown.enter.prevent="scanBarcodeDirect(opnameScanInput)"
                                    placeholder="Scan barcode fisik atau ketik nama part / SKU..."
                                    class="w-full pl-9 pr-10 py-2.5 rounded-xl glass-input text-xs sm:text-sm text-white placeholder-ink-400 min-h-[44px] focus:ring-1 focus:ring-teal-500"
                                    autofocus
                                />
                                @if(!empty($opnameScanInput))
                                    <button
                                        type="button"
                                        wire:click="$set('opnameScanInput', '')"
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-ink-400 hover:text-white cursor-pointer"
                                    >
                                        ✕
                                    </button>
                                @endif
                            </div>

                            <button
                                type="button"
                                wire:click="scanBarcodeDirect(opnameScanInput)"
                                class="px-4 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold min-h-[44px] flex items-center justify-center gap-1.5 cursor-pointer transition active:scale-95 shrink-0 shadow-lg shadow-teal-600/20"
                            >
                                <span>Pilih / Hitung</span>
                            </button>

                            <!-- Tombol Kamera Scanner -->
                            <button
                                type="button"
                                x-data="{}"
                                @click="if (window.uteBarcode && window.uteBarcode.openCameraScanner) {
                                    window.uteBarcode.openCameraScanner({
                                        title: 'Scan Barcode Opname',
                                        continuous: true,
                                        onScan: (code) => { $wire.scanBarcodeDirect(code); }
                                    });
                                } else {
                                    alert('Modul scanner kamera sedang memuat...');
                                }"
                                class="px-3.5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-teal-300 text-xs font-bold min-h-[44px] flex items-center justify-center gap-1.5 cursor-pointer transition shrink-0 active:scale-95"
                                title="Buka Kamera HP untuk Scan Barcode"
                            >
                                <svg class="w-4 h-4 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" /></svg>
                                <span>Scan Kamera</span>
                            </button>
                        </div>
                    </div>

                    <!-- Panel Produk Aktif / Sedang Dihitung (Alur: User Scan -> UI Menampilkan Produk & Input Stok -> Lanjut) -->
                    @if($activeItemIndex !== null && isset($opnameRows[$activeItemIndex]))
                        @php
                            $activeItem = $opnameRows[$activeItemIndex];
                        @endphp
                        <div class="p-4 sm:p-5 rounded-2xl bg-teal-500/[0.08] border border-teal-500/40 ring-1 ring-teal-500/30 space-y-3.5 transition animate-in fade-in zoom-in-95 duration-150">
                            <div class="flex items-start justify-between gap-3 pb-3 border-b border-teal-500/20">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-teal-400 text-ink-950 uppercase tracking-wider animate-pulse">
                                            ● Produk Aktif Dihitung
                                        </span>
                                        <span class="text-xs text-ink-400 font-mono">#{{ $activeItemIndex + 1 }}</span>
                                    </div>
                                    <h4 class="text-sm sm:text-base font-bold text-white mt-1.5">{{ $activeItem['nama'] }}</h4>
                                    <div class="flex flex-wrap items-center gap-1.5 text-[11px] text-ink-300 font-mono mt-1">
                                        @if(!empty($activeItem['sku']))
                                            <span class="px-2 py-0.5 rounded bg-white/10 text-teal-300 border border-teal-400/20">SKU: {{ $activeItem['sku'] }}</span>
                                        @endif
                                        @if(!empty($activeItem['barcode']))
                                            <span class="px-2 py-0.5 rounded bg-white/10 text-ink-200">Barcode: {{ $activeItem['barcode'] }}</span>
                                        @endif
                                        @if(!empty($activeItem['brand']))
                                            <span class="px-2 py-0.5 rounded bg-white/5 text-ink-400">{{ $activeItem['brand'] }}</span>
                                        @endif
                                        @if(!empty($activeItem['kategori']))
                                            <span class="px-2 py-0.5 rounded bg-white/5 text-ink-400">{{ $activeItem['kategori'] }}</span>
                                        @endif
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    wire:click="lewatiActiveItem"
                                    class="p-1 text-ink-400 hover:text-white rounded-lg hover:bg-white/10 transition cursor-pointer"
                                    title="Tutup / Ganti Produk"
                                >
                                    ✕
                                </button>
                            </div>

                            <!-- Input Jumlah Hitungan Fisik Aktual -->
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-teal-200 font-semibold whitespace-nowrap">Input Fisik Aktual:</span>
                                    <div class="flex items-center bg-ink-950/80 border border-teal-500/40 rounded-xl p-0.5 shadow-inner">
                                        <button
                                            type="button"
                                            wire:click="kurangActiveFisik"
                                            class="w-10 h-10 rounded-lg bg-white/5 hover:bg-white/15 text-white flex items-center justify-center font-bold text-base cursor-pointer active:scale-95 transition"
                                        >-</button>
                                        <input
                                            type="number"
                                            min="0"
                                            x-ref="activeFisikInput"
                                            wire:model.live="activeItemFisik"
                                            wire:keydown.enter.prevent="simpanDanLanjut"
                                            placeholder="0"
                                            class="w-24 sm:w-28 px-2 py-1 bg-transparent text-center text-lg sm:text-xl font-extrabold text-white tabular-nums focus:outline-none"
                                        />
                                        <button
                                            type="button"
                                            wire:click="tambahActiveFisik"
                                            class="w-10 h-10 rounded-lg bg-white/5 hover:bg-white/15 text-white flex items-center justify-center font-bold text-base cursor-pointer active:scale-95 transition"
                                        >+</button>
                                    </div>
                                    <span class="text-xs text-ink-300 font-medium">unit</span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        wire:click="simpanDanLanjut"
                                        class="flex-1 sm:flex-none px-4 py-2.5 rounded-xl bg-teal-500 hover:bg-teal-400 text-ink-950 font-extrabold text-xs flex items-center justify-center gap-1.5 cursor-pointer transition active:scale-95 shadow-lg shadow-teal-500/25"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        <span>Simpan & Lanjut Scan (Enter)</span>
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="lewatiActiveItem"
                                        class="px-3 py-2.5 rounded-xl bg-white/10 hover:bg-white/15 text-ink-300 text-xs font-semibold cursor-pointer transition"
                                    >
                                        Lewati
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Filter Status List Opname (Semua / Sudah Dihitung / Belum Dihitung) -->
                    <div class="flex items-center justify-between gap-2 pt-1 border-t border-white/5">
                        <div class="flex items-center gap-1 p-0.5 bg-white/5 rounded-xl border border-white/10 text-xs">
                            <button
                                type="button"
                                wire:click="$set('filterStatusHitung', 'semua')"
                                class="px-3 py-1.5 rounded-lg font-bold transition cursor-pointer {{ $filterStatusHitung === 'semua' ? 'bg-teal-600 text-white shadow' : 'text-ink-400 hover:text-white' }}"
                            >
                                Semua ({{ count($opnameRows) }})
                            </button>
                            <button
                                type="button"
                                wire:click="$set('filterStatusHitung', 'terhitung')"
                                class="px-3 py-1.5 rounded-lg font-bold transition cursor-pointer {{ $filterStatusHitung === 'terhitung' ? 'bg-teal-600 text-white shadow' : 'text-ink-400 hover:text-white' }}"
                            >
                                Sudah Dihitung ({{ collect($opnameRows)->filter(fn($r) => is_numeric($r['stok_fisik']))->count() }})
                            </button>
                            <button
                                type="button"
                                wire:click="$set('filterStatusHitung', 'belum')"
                                class="px-3 py-1.5 rounded-lg font-bold transition cursor-pointer {{ $filterStatusHitung === 'belum' ? 'bg-teal-600 text-white shadow' : 'text-ink-400 hover:text-white' }}"
                            >
                                Belum Dihitung ({{ collect($opnameRows)->filter(fn($r) => !is_numeric($r['stok_fisik']))->count() }})
                            </button>
                        </div>
                    </div>

                    <!-- Opname Spreadsheet Table (Blind Opname) Desktop View -->
                    @php
                        $filteredRows = collect($opnameRows)->filter(function($r, $idx) use ($filterStatusHitung, $opnameScanInput) {
                            if ($filterStatusHitung === 'terhitung' && !is_numeric($r['stok_fisik'])) {
                                return false;
                            }
                            if ($filterStatusHitung === 'belum' && is_numeric($r['stok_fisik'])) {
                                return false;
                            }
                            if (!empty($opnameScanInput)) {
                                $term = trim($opnameScanInput);
                                $matchName = stripos((string)$r['nama'], $term) !== false;
                                $matchSku = stripos((string)($r['sku'] ?? ''), $term) !== false;
                                $matchBarcode = stripos((string)($r['barcode'] ?? ''), $term) !== false;
                                if (!$matchName && !$matchSku && !$matchBarcode) {
                                    return false;
                                }
                            }
                            return true;
                        });
                    @endphp

                    <div class="hidden sm:block max-h-72 overflow-y-auto rounded-2xl border border-white/10 overflow-hidden">
                        <table class="w-full text-xs text-left text-ink-100">
                            <thead class="bg-white/5 text-ink-400 uppercase text-[10px] font-semibold sticky top-0 bg-ink-900 border-b border-white/10">
                                <tr>
                                    <th class="p-3 w-12 text-center">No</th>
                                    <th class="p-3">Produk</th>
                                    <th class="p-3 w-32 text-center">Status</th>
                                    <th class="p-3 text-center w-52">Hitungan Fisik Riil</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @forelse($filteredRows as $idx => $r)
                                    @php
                                        $isActive = ($activeItemIndex === $idx);
                                        $isCounted = is_numeric($r['stok_fisik']);
                                    @endphp
                                    <tr class="hover:bg-white/[0.04] transition {{ $isActive ? 'bg-teal-500/15 border-l-4 border-l-teal-400' : '' }}">
                                        <td class="p-3 text-center text-ink-400 tabular-nums">{{ $idx + 1 }}</td>
                                        <td class="p-3">
                                            <div class="font-medium text-white flex items-center gap-1.5">
                                                <span>{{ $r['nama'] }}</span>
                                                @if($isActive)
                                                    <span class="text-[9px] font-bold text-teal-300 bg-teal-500/20 px-1.5 py-0.5 rounded">Aktif</span>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-2 mt-0.5 text-[10px] text-ink-400 font-mono">
                                                @if(!empty($r['sku'])) <span>SKU: {{ $r['sku'] }}</span> @endif
                                                @if(!empty($r['barcode'])) <span>• Barcode: {{ $r['barcode'] }}</span> @endif
                                                @if(!empty($r['brand'])) <span>• {{ $r['brand'] }}</span> @endif
                                            </div>
                                        </td>
                                        <td class="p-3 text-center">
                                            @if($isCounted)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-500/20 text-teal-300 border border-teal-500/30">
                                                    ✓ {{ $r['stok_fisik'] }} unit
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-white/5 text-ink-400 border border-white/10">
                                                    Belum Dihitung
                                                </span>
                                            @endif
                                        </td>
                                        <td class="p-2 text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <button
                                                    type="button"
                                                    wire:click="pilihItemAktif({{ $idx }})"
                                                    class="px-2 py-1.5 rounded-lg bg-teal-500/10 hover:bg-teal-500/20 text-teal-300 text-[11px] font-bold transition mr-1 cursor-pointer"
                                                    title="Fokuskan untuk hitung"
                                                >
                                                    Pilih
                                                </button>
                                                <button
                                                    type="button"
                                                    wire:click="kurangFisik({{ $idx }})"
                                                    class="w-7 h-7 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white flex items-center justify-center font-bold text-xs cursor-pointer active:scale-95"
                                                >-</button>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    placeholder="0"
                                                    wire:change="updateOpnameFisik({{ $idx }}, $event.target.value)"
                                                    value="{{ $r['stok_fisik'] }}"
                                                    class="w-20 px-2 py-1.5 rounded-lg glass-input text-center font-bold text-white tabular-nums block min-h-[36px]"
                                                />
                                                <button
                                                    type="button"
                                                    wire:click="tambahFisik({{ $idx }})"
                                                    class="w-7 h-7 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white flex items-center justify-center font-bold text-xs cursor-pointer active:scale-95"
                                                >+</button>
                                                <span class="text-[11px] text-ink-400 ml-1">unit</span>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="p-6 text-center text-ink-400">
                                            Tidak ada produk yang sesuai dengan filter target / pencarian opname ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Card List View -->
                    <div class="sm:hidden space-y-2.5 max-h-72 overflow-y-auto pr-1">
                        @forelse($filteredRows as $idx => $r)
                            @php
                                $isActive = ($activeItemIndex === $idx);
                                $isCounted = is_numeric($r['stok_fisik']);
                            @endphp
                            <div class="p-3 rounded-2xl bg-white/[0.02] border {{ $isActive ? 'border-teal-500/50 bg-teal-500/10' : 'border-white/5' }} space-y-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <span class="text-xs font-bold text-white block">{{ $r['nama'] }}</span>
                                        <div class="flex flex-wrap items-center gap-1.5 text-[10px] text-ink-400 font-mono mt-0.5">
                                            @if(!empty($r['sku'])) <span>SKU: {{ $r['sku'] }}</span> @endif
                                            @if(!empty($r['barcode'])) <span>• {{ $r['barcode'] }}</span> @endif
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        @if($isCounted)
                                            <span class="text-[10px] font-bold text-teal-300 bg-teal-500/20 px-2 py-0.5 rounded-full border border-teal-500/30">
                                                ✓ {{ $r['stok_fisik'] }} unit
                                            </span>
                                        @else
                                            <span class="text-[10px] text-ink-400 bg-white/5 px-2 py-0.5 rounded-full">Belum</span>
                                        @endif
                                        <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-white/5 text-ink-400">#{{ $idx + 1 }}</span>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between pt-1 border-t border-white/5">
                                    <button
                                        type="button"
                                        wire:click="pilihItemAktif({{ $idx }})"
                                        class="px-2.5 py-1 rounded-lg bg-teal-500/15 hover:bg-teal-500/25 text-teal-300 text-xs font-bold cursor-pointer transition active:scale-95"
                                    >
                                        Pilih Hitung
                                    </button>
                                    <div class="flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            wire:click="kurangFisik({{ $idx }})"
                                            class="w-8 h-8 rounded-xl bg-white/5 hover:bg-white/10 text-ink-200 flex items-center justify-center font-bold text-sm cursor-pointer active:scale-95"
                                        >-</button>
                                        <input
                                            type="number"
                                            min="0"
                                            placeholder="0"
                                            wire:change="updateOpnameFisik({{ $idx }}, $event.target.value)"
                                            value="{{ $r['stok_fisik'] }}"
                                            class="w-16 px-2 py-1.5 rounded-xl glass-input text-center font-bold text-white tabular-nums text-sm min-h-[38px]"
                                        />
                                        <button
                                            type="button"
                                            wire:click="tambahFisik({{ $idx }})"
                                            class="w-8 h-8 rounded-xl bg-white/5 hover:bg-white/10 text-ink-200 flex items-center justify-center font-bold text-sm cursor-pointer active:scale-95"
                                        >+</button>
                                        <span class="text-xs text-ink-400">unit</span>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="p-6 text-center text-ink-400 text-xs">
                                Tidak ada produk yang sesuai dengan filter target / pencarian opname ini.
                            </div>
                        @endforelse
                    </div>

                    <div class="pt-1">
                        <label class="block text-xs font-semibold text-ink-300 mb-1">Catatan Tambahan Opname</label>
                        <textarea wire:model="opnameCatatan" rows="2" class="w-full px-3 py-2 rounded-xl glass-input text-xs" placeholder="Misal: Opname berkala akhir bulan rak A1..."></textarea>
                    </div>
                </div>

                <!-- Footer Modal -->
                <div class="flex flex-col sm:flex-row gap-2.5 sm:gap-3 pt-3 mt-3 border-t border-white/10 shrink-0">
                    <button wire:click="$set('showOpnameModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px] transition active:scale-[0.97]">Batal</button>
                    <button wire:click="saveOpname" class="flex-1 sm:flex-2 py-3 rounded-xl bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs shadow-lg shadow-teal-600/25 cursor-pointer min-h-[44px] transition active:scale-[0.97]">Kirim ke Supervisor untuk Approval</button>
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
