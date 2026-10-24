<div class="space-y-6 max-w-7xl mx-auto pb-12" @if($importStep === 'selesai' || ($activeImportLog && $activeImportLog['status'] === 'proses')) wire:poll.2s="cekStatusImport" @endif>
    <!-- Top Bar Navigation & Back to WMS -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-black/10 dark:border-white/5 pb-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-ink-400">
                <a href="/app/wms" wire:navigate class="hover:text-up-primary transition flex items-center gap-1">
                    <span>📦 WMS</span>
                </a>
                <span>/</span>
                <span class="text-ink-200">Export &amp; Import Produk</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-black text-ink-950 dark:text-white mt-1">
                Workspace Export &amp; Import Produk
            </h1>
            <p class="text-xs text-ink-500 dark:text-ink-400">
                Inisialisasi katalog master produk, migrasi data SID Retail, pencocokan duplikasi SKU, dan ekspor data inventori.
            </p>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
            <a href="/app/wms" wire:navigate
               class="min-h-[44px] px-4 py-2.5 rounded-xl bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 hover:bg-black/10 dark:hover:bg-white/10 text-ink-700 dark:text-ink-200 font-bold text-xs transition flex items-center justify-center gap-1.5 cursor-pointer">
                <span>← Kembali ke Produk</span>
            </a>
            <button wire:click="downloadTemplate"
                    class="min-h-[44px] px-4 py-2.5 rounded-xl bg-up-primary/10 hover:bg-up-primary/20 text-up-primary border border-up-primary/30 font-bold text-xs transition flex items-center justify-center gap-1.5 cursor-pointer">
                <span>📥 Unduh Template Excel</span>
            </button>
        </div>
    </div>

    <!-- Main Navigation Tabs: Import vs Export -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 p-1.5 bg-black/5 dark:bg-white/[0.03] rounded-2xl border border-black/10 dark:border-white/5 w-full sm:w-fit">
        <button type="button"
                wire:click="setTab('import')"
                class="min-h-[44px] px-5 py-2.5 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer {{ $activeTab === 'import' ? 'bg-up-primary text-white shadow-md shadow-up-primary/20' : 'text-ink-400 hover:text-white' }}">
            <span>📥</span>
            <span>Import Produk &amp; Penyesuaian</span>
        </button>
        <button type="button"
                wire:click="setTab('export')"
                class="min-h-[44px] px-5 py-2.5 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer {{ $activeTab === 'export' ? 'bg-up-primary text-white shadow-md shadow-up-primary/20' : 'text-ink-400 hover:text-white' }}">
            <span>📤</span>
            <span>Ekspor Master &amp; Template</span>
        </button>
    </div>

    <!-- TAB 1: IMPORT WORKSPACE -->
    @if($activeTab === 'import')
        @if($importStep === 'upload')
            <!-- Step 1: Upload Form -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left: Upload Box -->
                <div class="lg:col-span-2 p-6 rounded-2xl bg-black/5 dark:bg-white/[0.02] border border-black/10 dark:border-white/5 space-y-5">
                    <div class="flex items-center justify-between border-b border-black/5 dark:border-white/5 pb-3">
                        <h2 class="font-bold text-sm text-ink-900 dark:text-white flex items-center gap-2">
                            <span>1.</span>
                            <span>Pilih Format &amp; Unggah File</span>
                        </h2>
                        <span class="text-[10px] text-ink-400">Maks. 10MB (.xlsx, .xls, .csv)</span>
                    </div>

                    <!-- Pilihan Format -->
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-ink-700 dark:text-ink-300">Format Sumber Data:</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="p-3.5 rounded-xl border cursor-pointer transition flex items-start gap-3 {{ $importFormat === 'standar' ? 'bg-up-primary/10 border-up-primary text-white' : 'bg-black/5 dark:bg-white/5 border-black/10 dark:border-white/10 text-ink-400 hover:bg-black/10' }}">
                                <input type="radio" wire:model.live="importFormat" value="standar" class="mt-0.5 text-up-primary">
                                <div class="space-y-0.5">
                                    <div class="font-bold text-xs text-ink-900 dark:text-white">Format Standar Ute Parts</div>
                                    <div class="text-[10px] text-ink-400 leading-relaxed">Template resmi dengan multi-tier harga, tipe HP, multi-satuan, lokasi rak &amp; gudang.</div>
                                </div>
                            </label>
                            <label class="p-3.5 rounded-xl border cursor-pointer transition flex items-start gap-3 {{ $importFormat === 'sid_retail' ? 'bg-up-primary/10 border-up-primary text-white' : 'bg-black/5 dark:bg-white/5 border-black/10 dark:border-white/10 text-ink-400 hover:bg-black/10' }}">
                                <input type="radio" wire:model.live="importFormat" value="sid_retail" class="mt-0.5 text-up-primary">
                                <div class="space-y-0.5">
                                    <div class="font-bold text-xs text-ink-900 dark:text-white">Format SID Retail Pro</div>
                                    <div class="text-[10px] text-ink-400 leading-relaxed">Ekspor dari software lama SID Retail (kolom kode, nama, hargabeli, hargajual, stok, dll).</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Pengaturan Threshold Kemiripan (Sensitivitas Pencocokan Nama Produk) -->
                    <div class="p-4 rounded-xl bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 space-y-2.5">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-ink-700 dark:text-ink-300 flex items-center gap-1.5">
                                <span>🎚️</span>
                                <span>Sensitivitas Threshold Kemiripan Nama Produk:</span>
                            </label>
                            <span class="px-2.5 py-0.5 rounded-full font-mono font-black text-xs bg-up-primary/20 text-up-primary border border-up-primary/30">
                                {{ $thresholdKemiripan }}%
                            </span>
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="range" wire:model.live.debounce.300ms="thresholdKemiripan" min="50" max="100" step="1"
                                   class="w-full accent-up-primary cursor-pointer">
                            <div class="flex items-center gap-1">
                                <input type="number" wire:model.live.debounce.300ms="thresholdKemiripan" min="50" max="100"
                                       class="w-16 text-center text-xs font-mono font-bold rounded-lg bg-black/30 border border-white/10 py-1.5 text-white">
                                <span class="text-xs text-ink-400">%</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between flex-wrap gap-2 text-[10px] text-ink-400 pt-1 border-t border-white/5">
                            <span>Makin tinggi (cth: 99%), makin selektif &amp; hanya nama yang hampir identik yang dicocokkan.</span>
                            <div class="flex items-center gap-1.5">
                                <button type="button" wire:click="$set('thresholdKemiripan', 90)" class="px-2 py-0.5 rounded bg-white/5 hover:bg-white/10 text-ink-300 cursor-pointer">90%</button>
                                <button type="button" wire:click="$set('thresholdKemiripan', 95)" class="px-2 py-0.5 rounded bg-white/5 hover:bg-white/10 text-ink-300 cursor-pointer">95%</button>
                                <button type="button" wire:click="$set('thresholdKemiripan', 99)" class="px-2 py-0.5 rounded bg-up-primary/30 text-up-primary font-bold cursor-pointer">99% (Default)</button>
                                <button type="button" wire:click="$set('thresholdKemiripan', 100)" class="px-2 py-0.5 rounded bg-white/5 hover:bg-white/10 text-ink-300 cursor-pointer">100% (Identik)</button>
                            </div>
                        </div>
                    </div>

                    @if($importFormat === 'sid_retail')
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 rounded-xl bg-up-primary/5 border border-up-primary/20">
                            <div class="space-y-1">
                                <label class="text-[11px] font-bold text-ink-300">Gudang Toko (Stok Toko):</label>
                                <select wire:model="gudangTokoId" class="w-full text-xs rounded-lg bg-black/40 border border-white/10 px-3 py-2 text-white">
                                    @foreach($gudangList as $g)
                                        <option value="{{ $g->id }}">{{ $g->nama }} ({{ $g->kode }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label class="text-[11px] font-bold text-ink-300">Gudang Pusat (Stok Gudang):</label>
                                <select wire:model="gudangPusatId" class="w-full text-xs rounded-lg bg-black/40 border border-white/10 px-3 py-2 text-white">
                                    @foreach($gudangList as $g)
                                        <option value="{{ $g->id }}">{{ $g->nama }} ({{ $g->kode }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endif

                    <!-- Dropzone File Upload -->
                    <div class="space-y-2"
                         x-data="{ uploading: false, progress: 0 }"
                         x-on:livewire-upload-start="uploading = true; progress = 0"
                         x-on:livewire-upload-finish="uploading = false"
                         x-on:livewire-upload-error="uploading = false"
                         x-on:livewire-upload-progress="progress = $event.detail.progress">
                        <label class="text-xs font-bold text-ink-700 dark:text-ink-300">File Excel / CSV:</label>
                        <div class="border-2 border-dashed rounded-2xl p-6 text-center transition-all duration-300 relative bg-black/5 dark:bg-white/[0.01] {{ $importFile ? 'border-up-mint/60 bg-up-mint/[0.03]' : 'border-black/15 dark:border-white/15 hover:border-up-primary/50' }}">
                            <input type="file" wire:model="importFile" accept=".xlsx,.xls,.csv" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                            
                            <div class="space-y-3 pointer-events-none">
                                <div class="relative inline-flex items-center justify-center">
                                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-2xl transition-all duration-300 {{ $importFile ? 'bg-up-mint/20 text-up-mint shadow-lg shadow-up-mint/20 scale-105' : 'bg-black/10 dark:bg-white/5 text-ink-400' }}">
                                        @if($importFile)
                                            <span class="animate-bounce">✓</span>
                                        @else
                                            <span>📁</span>
                                        @endif
                                    </div>
                                    @if($importFile)
                                        <div class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-up-mint ring-2 ring-black flex items-center justify-center text-[10px] text-ink-950 font-black">✓</div>
                                    @endif
                                </div>

                                <div class="space-y-1">
                                    <div class="text-xs font-bold text-ink-900 dark:text-white">
                                        @if($importFile)
                                            <span class="text-up-mint font-semibold flex items-center justify-center gap-1.5">
                                                <span>{{ $importFile->getClientOriginalName() }}</span>
                                                <span class="text-[10px] text-ink-400 font-normal">({{ number_format($importFile->getSize() / 1024, 0) }} KB)</span>
                                            </span>
                                        @else
                                            <span>Klik atau seret file ke sini untuk diunggah</span>
                                        @endif
                                    </div>
                                    <p class="text-[10px] text-ink-400">Pastikan baris pertama berisi nama kolom / header template.</p>
                                </div>

                                <!-- Live Upload Progress Bar Alpine -->
                                <div x-show="uploading" x-cloak class="w-full max-w-xs mx-auto space-y-1.5 pt-2">
                                    <div class="flex items-center justify-between text-[11px] font-mono">
                                        <span class="text-up-primary font-bold animate-pulse">Mengunggah file...</span>
                                        <span class="text-up-mint font-black" x-text="`${progress}%`"></span>
                                    </div>
                                    <div class="w-full h-2 rounded-full bg-black/40 overflow-hidden p-0.5 border border-white/10">
                                        <div class="h-full rounded-full bg-gradient-to-r from-up-primary via-up-mint to-up-mint transition-all duration-150 shadow-sm"
                                             :style="`width: ${progress}%`"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @error('importFile')
                            <p class="text-up-red text-xs font-semibold mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-black/5 dark:border-white/5">
                        <button type="button"
                                wire:click="previewImport"
                                wire:loading.attr="disabled"
                                class="px-6 py-3 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs cursor-pointer shadow-md shadow-up-primary/20 transition flex items-center gap-2">
                            <span wire:loading.remove wire:target="previewImport">Periksa File &amp; Preview →</span>
                            <span wire:loading wire:target="previewImport">Membaca &amp; Menganalisis...</span>
                        </button>
                    </div>
                </div>

                <!-- Right: Rules & Instructions -->
                <div class="p-6 rounded-2xl bg-black/5 dark:bg-white/[0.02] border border-black/10 dark:border-white/5 space-y-4">
                    <h3 class="font-bold text-xs text-ink-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                        <span>💡</span>
                        <span>Panduan Cerdas Import</span>
                    </h3>
                    <ul class="text-[11px] text-ink-400 space-y-3 leading-relaxed">
                        <li class="flex items-start gap-2">
                            <span class="text-up-mint font-bold">✓</span>
                            <span><strong>Auto-Registrasi Master:</strong> Brand, kategori, supplier, satuan, dan lokasi rak baru otomatis didaftarkan ke sistem tanpa perlu dibuat manual sebelumnya.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-up-primary font-bold">⚡</span>
                            <span><strong>Deteksi Duplikat SKU:</strong> Jika ada SKU yang sama, Anda dapat langsung mengedit SKU baru di tabel penyesuaian atau memilih opsi timpa produk eksisting.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-up-amber font-bold">🔍</span>
                            <span><strong>Pencocokan Kemiripan (Threshold 99%):</strong> Produk yang hampir identik (&ge; 99%) akan ditawarkan untuk digabungkan varian &amp; tipe HP-nya agar master data tetap rapi.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-up-mint font-bold">🛡️</span>
                            <span><strong>Aman &amp; Teruji:</strong> Import diproses per batch 100 baris dengan pencatatan jurnal saldo awal dan mutasi stok transparan.</span>
                        </li>
                    </ul>
                </div>
            </div>
        @elseif($importStep === 'preview')
            <!-- Step 2: Interactive Preview & Resolution Workspace -->
            <div class="space-y-6">
                <!-- Status Bar & Metrics -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="p-4 rounded-xl bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10">
                        <div class="text-[10px] text-ink-400 uppercase font-semibold">Total Baris File</div>
                        <div class="text-2xl font-black text-ink-950 dark:text-white tabular-nums mt-0.5">{{ $importPreview['total_baris'] ?? 0 }}</div>
                    </div>
                    <div class="p-4 rounded-xl bg-up-mint/10 border border-up-mint/30">
                        <div class="text-[10px] text-up-mint uppercase font-semibold">Valid &amp; Siap Diimpor</div>
                        <div class="text-2xl font-black text-up-mint tabular-nums mt-0.5">{{ $importPreview['valid'] ?? 0 }}</div>
                    </div>
                    <div class="p-4 rounded-xl {{ ($importPreview['total_duplikat_rows'] ?? 0) > 0 ? 'bg-up-primary/10 border border-up-primary/30' : 'bg-black/5 dark:bg-white/5 border-black/10' }}">
                        <div class="text-[10px] {{ ($importPreview['total_duplikat_rows'] ?? 0) > 0 ? 'text-up-primary font-bold' : 'text-ink-400' }} uppercase">Duplikasi SKU / Barcode</div>
                        <div class="text-2xl font-black {{ ($importPreview['total_duplikat_rows'] ?? 0) > 0 ? 'text-up-primary' : 'text-ink-300' }} tabular-nums mt-0.5">{{ $importPreview['total_duplikat_rows'] ?? 0 }}</div>
                    </div>
                    <div class="p-4 rounded-xl {{ ($importPreview['invalid'] ?? 0) > 0 ? 'bg-up-red/10 border border-up-red/30' : 'bg-black/5 dark:bg-white/5 border-black/10' }}">
                        <div class="text-[10px] {{ ($importPreview['invalid'] ?? 0) > 0 ? 'text-up-red font-bold' : 'text-ink-400' }} uppercase">Total Baris Bermasalah</div>
                        <div class="text-2xl font-black {{ ($importPreview['invalid'] ?? 0) > 0 ? 'text-up-red' : 'text-ink-300' }} tabular-nums mt-0.5">{{ $importPreview['invalid'] ?? 0 }}</div>
                    </div>
                </div>

                <!-- PANEL 1: PENYESUAIAN DUPLIKASI SKU / BARCODE -->
                @if(! empty($importPreview['duplikat_rows']))
                    <div class="p-5 rounded-2xl bg-up-primary/10 border border-up-primary/30 space-y-4">
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <div>
                                <h3 class="text-sm font-bold text-up-primary flex items-center gap-2">
                                    <span>⚡</span>
                                    <span>Penyesuaian Duplikasi SKU / Barcode ({{ count($importPreview['duplikat_rows']) }} Baris)</span>
                                </h3>
                                <p class="text-[11px] text-ink-300 mt-0.5">
                                    Daripada baris ini dilewati, Anda dapat <strong>mengubah SKU langsung</strong> di sini, memilih opsi <strong>timpa/update data produk eksisting</strong>, atau melewati baris tersebut.
                                </p>
                            </div>
                        </div>

                        <!-- Desktop Table View (md and up) -->
                        <div class="hidden md:block overflow-x-auto rounded-xl border border-white/10 bg-black/40">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="text-ink-400 border-b border-white/10 bg-white/5 text-[10px] uppercase tracking-wider">
                                        <th class="py-2.5 px-3 font-mono">Baris</th>
                                        <th class="py-2.5 px-3">SKU Asli</th>
                                        <th class="py-2.5 px-3">Nama Produk di File</th>
                                        <th class="py-2.5 px-3">Status Duplikat</th>
                                        <th class="py-2.5 px-3">Tindakan Penyelesaian</th>
                                        <th class="py-2.5 px-3 min-w-[200px]">Input SKU Baru (Jika Edit)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5 text-[11px]">
                                    @foreach($importPreview['duplikat_rows'] as $dr)
                                        @php
                                            $currentAksi = $resolusiDuplikat[$dr['baris']]['aksi'] ?? ($dr['lokasi'] === 'database' ? 'timpa_update' : 'ganti_sku');
                                            $currentSkuBaru = $resolusiDuplikat[$dr['baris']]['sku_baru'] ?? $dr['sku_rekomendasi'];
                                        @endphp
                                        <tr class="hover:bg-white/[0.02] transition">
                                            <td class="py-2.5 px-3 font-mono text-ink-400">#{{ $dr['baris'] }}</td>
                                            <td class="py-2.5 px-3 font-mono font-bold text-white">{{ $dr['sku'] }}</td>
                                            <td class="py-2.5 px-3 text-ink-200 max-w-[220px] truncate" title="{{ $dr['nama'] }}">{{ $dr['nama'] }}</td>
                                            <td class="py-2.5 px-3">
                                                @if($dr['lokasi'] === 'database')
                                                    <span class="px-2 py-0.5 rounded bg-up-red/20 text-up-red border border-up-red/30 text-[10px] font-bold">
                                                        Di Database: {{ $dr['target_produk_nama'] }}
                                                    </span>
                                                @else
                                                    <span class="px-2 py-0.5 rounded bg-up-amber/20 text-up-amber border border-up-amber/30 text-[10px] font-bold">
                                                        Sama dgn Baris #{{ $dr['target_baris'] }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="py-2.5 px-3">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <button type="button"
                                                            wire:click="setAksiDuplikat({{ $dr['baris'] }}, 'ganti_sku')"
                                                            class="min-h-[32px] px-2.5 py-1 rounded-lg text-[10px] font-bold cursor-pointer transition {{ $currentAksi === 'ganti_sku' ? 'bg-up-primary text-white ring-1 ring-up-primary/50' : 'bg-white/5 hover:bg-white/10 text-ink-300' }}">
                                                        ✏️ Ganti SKU
                                                    </button>
                                                    @if($dr['lokasi'] === 'database')
                                                        <button type="button"
                                                                wire:click="setAksiDuplikat({{ $dr['baris'] }}, 'timpa_update')"
                                                                class="min-h-[32px] px-2.5 py-1 rounded-lg text-[10px] font-bold cursor-pointer transition {{ $currentAksi === 'timpa_update' ? 'bg-up-mint text-ink-950 font-black ring-1 ring-up-mint/50' : 'bg-white/5 hover:bg-white/10 text-ink-300' }}"
                                                                title="Perbarui harga dan tambahkan stok ke produk eksisting">
                                                            🔄 Timpa/Update
                                                        </button>
                                                    @endif
                                                    <button type="button"
                                                            wire:click="setAksiDuplikat({{ $dr['baris'] }}, 'skip')"
                                                            class="min-h-[32px] px-2.5 py-1 rounded-lg text-[10px] font-bold cursor-pointer transition {{ $currentAksi === 'skip' ? 'bg-up-red text-white ring-1 ring-up-red/50' : 'bg-white/5 hover:bg-white/10 text-ink-300' }}">
                                                        ⏭️ Skip
                                                    </button>
                                                </div>
                                            </td>
                                            <td class="py-2.5 px-3">
                                                @if($currentAksi === 'ganti_sku')
                                                    <input type="text"
                                                           value="{{ $currentSkuBaru }}"
                                                           wire:change="updateSkuBaru({{ $dr['baris'] }}, $event.target.value)"
                                                           placeholder="Masukkan SKU pengganti"
                                                           class="w-full text-xs font-mono font-bold rounded-lg bg-black/60 border border-up-primary/50 px-2.5 py-1.5 text-white focus:ring-1 focus:ring-up-primary">
                                                @elseif($currentAksi === 'timpa_update')
                                                    <span class="text-[10px] text-up-mint font-semibold">Akan memperbarui data &amp; stok produk ID: {{ $dr['target_produk_id'] }}</span>
                                                @else
                                                    <span class="text-[10px] text-ink-500 italic">Baris ini akan dilewati</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Mobile Card Stack View (< md) -->
                        <div class="block md:hidden space-y-3">
                            @foreach($importPreview['duplikat_rows'] as $dr)
                                @php
                                    $currentAksi = $resolusiDuplikat[$dr['baris']]['aksi'] ?? ($dr['lokasi'] === 'database' ? 'timpa_update' : 'ganti_sku');
                                    $currentSkuBaru = $resolusiDuplikat[$dr['baris']]['sku_baru'] ?? $dr['sku_rekomendasi'];
                                @endphp
                                <div class="p-3.5 rounded-xl bg-black/40 border border-white/10 space-y-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="space-y-0.5">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="px-1.5 py-0.5 rounded bg-white/10 font-mono text-[10px] text-ink-300 font-bold">#{{ $dr['baris'] }}</span>
                                                <span class="font-mono font-bold text-xs text-white">{{ $dr['sku'] }}</span>
                                            </div>
                                            <div class="text-xs font-semibold text-ink-100">{{ $dr['nama'] }}</div>
                                        </div>
                                        <div>
                                            @if($dr['lokasi'] === 'database')
                                                <span class="px-2 py-0.5 rounded bg-up-red/20 text-up-red border border-up-red/30 text-[10px] font-bold inline-block text-right">
                                                    Di DB: {{ \Illuminate\Support\Str::limit($dr['target_produk_nama'], 18) }}
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 rounded bg-up-amber/20 text-up-amber border border-up-amber/30 text-[10px] font-bold inline-block text-right">
                                                    Duplikat #{{ $dr['target_baris'] }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Tombol Pilihan Aksi Mobile: Touch Target min-h-[40px] -->
                                    <div class="space-y-1.5 pt-1 border-t border-white/5">
                                        <div class="text-[10px] text-ink-400 font-bold uppercase tracking-wider">Aksi Penyelesaian:</div>
                                        <div class="grid grid-cols-3 gap-1.5">
                                            <button type="button"
                                                    wire:click="setAksiDuplikat({{ $dr['baris'] }}, 'ganti_sku')"
                                                    class="min-h-[40px] px-2 py-2 rounded-xl text-xs font-bold cursor-pointer transition flex items-center justify-center text-center {{ $currentAksi === 'ganti_sku' ? 'bg-up-primary text-white ring-2 ring-up-primary/60 shadow-sm' : 'bg-white/5 hover:bg-white/10 text-ink-300' }}">
                                                ✏️ Ganti
                                            </button>
                                            @if($dr['lokasi'] === 'database')
                                                <button type="button"
                                                        wire:click="setAksiDuplikat({{ $dr['baris'] }}, 'timpa_update')"
                                                        class="min-h-[40px] px-2 py-2 rounded-xl text-xs font-black cursor-pointer transition flex items-center justify-center text-center {{ $currentAksi === 'timpa_update' ? 'bg-up-mint text-ink-950 ring-2 ring-up-mint/60 shadow-sm' : 'bg-white/5 hover:bg-white/10 text-ink-300' }}">
                                                    🔄 Timpa
                                                </button>
                                            @else
                                                <div class="hidden"></div>
                                            @endif
                                            <button type="button"
                                                    wire:click="setAksiDuplikat({{ $dr['baris'] }}, 'skip')"
                                                    class="min-h-[40px] px-2 py-2 rounded-xl text-xs font-bold cursor-pointer transition flex items-center justify-center text-center {{ $currentAksi === 'skip' ? 'bg-up-red text-white ring-2 ring-up-red/60 shadow-sm' : 'bg-white/5 hover:bg-white/10 text-ink-300' }}">
                                                ⏭️ Skip
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Input SKU Pengganti Mobile -->
                                    @if($currentAksi === 'ganti_sku')
                                        <div class="pt-1.5 space-y-1">
                                            <label class="text-[10px] text-up-primary font-bold">SKU Pengganti:</label>
                                            <input type="text"
                                                   value="{{ $currentSkuBaru }}"
                                                   wire:change="updateSkuBaru({{ $dr['baris'] }}, $event.target.value)"
                                                   placeholder="Masukkan SKU baru..."
                                                   class="w-full min-h-[42px] text-xs font-mono font-bold rounded-xl bg-black/60 border border-up-primary/50 px-3 py-2 text-white focus:ring-1 focus:ring-up-primary">
                                        </div>
                                    @elseif($currentAksi === 'timpa_update')
                                        <div class="text-[11px] text-up-mint font-semibold bg-up-mint/10 p-2 rounded-lg border border-up-mint/20">
                                            ✓ Data &amp; stok akan ditambahkan ke produk eksisting (ID: {{ $dr['target_produk_id'] }}).
                                        </div>
                                    @else
                                        <div class="text-[11px] text-ink-500 italic bg-white/5 p-2 rounded-lg">
                                            Baris #{{ $dr['baris'] }} akan dilewati saat proses import.
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- PANEL 2: PENCOCOKAN KEMIRIPAN NAMA PRODUK DENGAN THRESHOLD FLEKSIBEL -->
                <div class="p-5 rounded-2xl bg-black/5 dark:bg-white/[0.02] border border-black/10 dark:border-white/5 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-white/5 pb-3">
                        <div>
                            <h3 class="text-sm font-bold text-ink-900 dark:text-white flex items-center gap-2">
                                <span>🔍</span>
                                <span>Pencocokan Kemiripan Nama Produk ({{ count($importPreview['kemiripan_rows'] ?? []) }} Item Terdeteksi)</span>
                            </h3>
                            <p class="text-[11px] text-ink-400 mt-0.5">
                                Atur slider threshold di samping untuk memperketat (&ge; 99%) atau memperluas (&le; 95%) deteksi kemiripan secara langsung.
                            </p>
                        </div>
                        <!-- Live Slider Threshold -->
                        <div class="flex items-center gap-2.5 p-2 rounded-xl bg-black/20 border border-white/10 shrink-0">
                            <span class="text-[10px] text-ink-300 font-bold whitespace-nowrap">Threshold:</span>
                            <input type="range" wire:model.live.debounce.300ms="thresholdKemiripan" min="50" max="100" step="1"
                                   class="w-24 sm:w-32 accent-up-primary cursor-pointer" title="Ubah threshold kemiripan">
                            <span class="px-2 py-0.5 rounded font-mono font-black text-xs bg-up-primary/20 text-up-primary border border-up-primary/30 min-w-[42px] text-center">
                                {{ $thresholdKemiripan }}%
                            </span>
                        </div>
                    </div>

                    @if(! empty($importPreview['kemiripan_rows']))
                        <div class="space-y-3">
                            @foreach($importPreview['kemiripan_rows'] as $kr)
                                @php
                                    $aksiKmr = $aksiKemiripan[$kr['baris']] ?? $kr['default_aksi'];
                                @endphp
                                <div class="p-3.5 sm:p-4 rounded-xl bg-black/40 border {{ $aksiKmr === 'skip' ? 'border-up-red/30' : ($aksiKmr === 'gabung' ? 'border-up-primary/40' : 'border-up-amber/40') }} flex flex-col md:flex-row md:items-center justify-between gap-3.5">
                                    <div class="space-y-1.5 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-up-primary/20 text-up-primary font-mono">Baris #{{ $kr['baris'] }}</span>
                                            <span class="font-bold text-white text-xs break-all sm:break-normal">{{ $kr['nama'] }}</span>
                                            <span class="text-[10px] font-mono text-ink-400">[{{ $kr['sku'] }}]</span>
                                            <span class="text-[10px] px-2 py-0.5 rounded-full font-bold bg-up-amber/20 text-up-amber border border-up-amber/30">
                                                {{ $kr['persentase'] }}% Mirip
                                            </span>
                                        </div>
                                        <div class="text-[11px] text-ink-300 flex items-center gap-1.5 flex-wrap">
                                            <span class="text-ink-400">Target Rekomendasi:</span>
                                            <strong class="text-white">{{ $kr['target_nama'] }}</strong>
                                            <span class="font-mono text-ink-400">[{{ $kr['target_sku'] }}]</span>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-3 sm:flex sm:items-center gap-1.5 shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-white/5">
                                        <button type="button"
                                                wire:click="setAksiKemiripan({{ $kr['baris'] }}, 'gabung')"
                                                class="min-h-[40px] px-3 py-1.5 rounded-xl text-xs font-bold cursor-pointer transition flex items-center justify-center text-center {{ $aksiKmr === 'gabung' ? 'bg-up-primary text-white shadow-sm ring-1 ring-up-primary/50' : 'bg-white/5 hover:bg-white/10 text-ink-300' }}">
                                            🔗 Gabung
                                        </button>
                                        <button type="button"
                                                wire:click="setAksiKemiripan({{ $kr['baris'] }}, 'terpisah')"
                                                class="min-h-[40px] px-3 py-1.5 rounded-xl text-xs font-bold cursor-pointer transition flex items-center justify-center text-center {{ $aksiKmr === 'terpisah' ? 'bg-up-amber text-ink-950 shadow-sm ring-1 ring-up-amber/50 font-black' : 'bg-white/5 hover:bg-white/10 text-ink-300' }}">
                                            📄 Pisah
                                        </button>
                                        <button type="button"
                                                wire:click="setAksiKemiripan({{ $kr['baris'] }}, 'skip')"
                                                class="min-h-[40px] px-3 py-1.5 rounded-xl text-xs font-bold cursor-pointer transition flex items-center justify-center text-center {{ $aksiKmr === 'skip' ? 'bg-up-red text-white shadow-sm ring-1 ring-up-red/50' : 'bg-white/5 hover:bg-white/10 text-ink-300' }}">
                                            ⏭️ Skip
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-4 rounded-xl bg-black/20 text-center text-xs text-ink-400">
                            Tidak ada produk dengan kemiripan &ge; {{ $thresholdKemiripan }}%. Jika ingin mendeteksi variasi produk yang lebih luas, geser slider threshold ke bawah (misal 90% atau 95%).
                        </div>
                    @endif
                </div>

                <!-- PANEL 3: RINCIAN SELURUH BARIS ERROR -->
                @if(! empty($importPreview['error_rows']))
                    <div class="p-5 rounded-2xl bg-up-red/10 border border-up-red/30 space-y-4">
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <h3 class="text-sm font-bold text-up-red flex items-center gap-2">
                                <span>⛔</span>
                                <span>Rincian Seluruh Baris Error ({{ count($importPreview['error_rows']) }} Baris)</span>
                            </h3>
                            <span class="text-[10px] text-ink-400">Rincian error lengkap agar Anda dapat memperbaiki data pada file</span>
                        </div>

                        <div class="max-h-80 overflow-y-auto space-y-2.5 pr-1 divide-y divide-white/5">
                            @foreach($importPreview['error_rows'] as $errRow)
                                <div class="pt-2.5 first:pt-0 p-3 rounded-xl bg-black/40 border border-up-red/20 space-y-1.5">
                                    <div class="flex items-center justify-between text-xs font-mono flex-wrap gap-1">
                                        <span class="font-bold px-2 py-0.5 rounded bg-up-red/20 text-up-red border border-up-red/30">Baris #{{ $errRow['baris'] }}</span>
                                        <span class="text-ink-400 text-[11px]">SKU: <strong class="text-white">{{ $errRow['sku'] ?: '(kosong)' }}</strong></span>
                                    </div>
                                    @if(! empty($errRow['nama']))
                                        <div class="text-white text-xs font-semibold">{{ $errRow['nama'] }}</div>
                                    @endif
                                    <ul class="space-y-1 mt-1 pt-1 border-t border-white/5">
                                        @foreach($errRow['errors'] as $errMsg)
                                            <li class="text-up-red text-[11px] flex items-start gap-1.5 leading-snug">
                                                <span class="font-bold shrink-0">•</span>
                                                <span>{{ $errMsg }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- PANEL 4: KONFIRMASI AUTO-REGISTRASI MASTER BARU -->
                @if(! empty($importPreview['konfirmasi']))
                    <div class="p-5 rounded-2xl bg-black/5 dark:bg-white/[0.02] border border-black/10 dark:border-white/5 space-y-3">
                        <h4 class="text-xs font-bold text-ink-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                            <span>✨</span>
                            <span>Konfirmasi Auto-Registrasi Master Data Baru ({{ count($importPreview['konfirmasi']) }} Entitas):</span>
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($importPreview['konfirmasi'] as $konf)
                                <div class="p-3 rounded-xl bg-black/20 border border-white/5 space-y-1">
                                    <div class="flex items-center justify-between text-[10px]">
                                        <span class="font-bold text-up-primary uppercase">{{ $konf['tipe'] }}</span>
                                        <span class="text-up-mint font-semibold">Auto-Create</span>
                                    </div>
                                    <div class="font-bold text-xs text-white truncate" title="{{ $konf['nilai'] }}">{{ $konf['nilai'] }}</div>
                                    <div class="text-[10px] text-ink-400">{{ $konf['keterangan'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Action Buttons: Commit Import & Reset -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-black/10 dark:border-white/5">
                    <button type="button"
                            wire:click="resetUpload"
                            class="w-full sm:w-auto px-5 py-3 rounded-xl bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 hover:bg-black/10 text-ink-300 font-bold text-xs cursor-pointer transition">
                        ← Upload Ulang / Ganti File
                    </button>

                    <button type="button"
                            x-data="{ submitting: false }"
                            x-on:click="if (submitting) return false; submitting = true;"
                            wire:click="commitImport"
                            wire:loading.attr="disabled"
                            class="w-full sm:w-auto px-8 py-3.5 rounded-xl bg-up-mint hover:bg-up-mint/90 text-ink-950 font-black text-xs cursor-pointer shadow-lg shadow-up-mint/20 transition flex items-center justify-center gap-2">
                        <span wire:loading.remove wire:target="commitImport">✅ Eksekusi &amp; Import Sekarang</span>
                        <span wire:loading wire:target="commitImport">Sedang Memproses...</span>
                    </button>
                </div>
            </div>
        @elseif($importStep === 'selesai')
            <!-- Step 3: Finished / Live Processing Status -->
            <div class="p-6 sm:p-8 rounded-2xl bg-black/5 dark:bg-white/[0.02] border border-black/10 dark:border-white/5 text-center space-y-6">
                @php
                    $isProses = $activeImportLog && $activeImportLog['status'] === 'proses';
                    $isSelesai = $activeImportLog && $activeImportLog['status'] === 'selesai';
                    $isGagal = $activeImportLog && $activeImportLog['status'] === 'gagal';
                    $totalBaris = (int) ($activeImportLog['total_baris'] ?? 0);
                    $sukses = (int) ($activeImportLog['sukses'] ?? 0);
                    $gagal = (int) ($activeImportLog['gagal'] ?? 0);
                    $selesaiCount = $sukses + $gagal;
                    $persen = $totalBaris > 0 ? (int) min(100, round(($selesaiCount / $totalBaris) * 100)) : ($isSelesai ? 100 : 0);
                @endphp

                <div class="relative inline-flex items-center justify-center">
                    @if($isProses)
                        <div class="w-16 h-16 rounded-2xl bg-up-primary/10 border border-up-primary/30 flex items-center justify-center text-3xl animate-pulse">
                            ⏳
                        </div>
                    @elseif($isGagal)
                        <div class="w-16 h-16 rounded-2xl bg-up-red/10 border border-up-red/30 flex items-center justify-center text-3xl">
                            ❌
                        </div>
                    @else
                        <div class="w-16 h-16 rounded-2xl bg-up-mint/10 border border-up-mint/30 flex items-center justify-center text-3xl animate-bounce">
                            🎉
                        </div>
                    @endif
                </div>

                <div class="space-y-1">
                    <h2 class="text-xl sm:text-2xl font-black text-ink-950 dark:text-white">
                        @if($isProses)
                            Import Sedang Berjalan...
                        @elseif($isGagal)
                            Import Mengalami Kendala
                        @else
                            Import Produk Berhasil Diajukan
                        @endif
                    </h2>
                    <p class="text-xs text-ink-400">
                        @if($isProses)
                            Sistem sedang membaca baris dan menyinkronkan stok serta katalog. Halaman ini diperbarui otomatis.
                        @elseif($isGagal)
                            Proses import terhenti karena error teknis. Silakan periksa detail log di bawah.
                        @else
                            Proses impor master produk telah diproses oleh worker antrian sistem.
                        @endif
                    </p>
                </div>

                @if($activeImportLog)
                    <div class="max-w-lg mx-auto p-5 rounded-2xl bg-black/40 border border-white/10 text-left text-xs space-y-4 shadow-xl">
                        <!-- Header Status Log -->
                        <div class="flex items-center justify-between font-mono pb-3 border-b border-white/10">
                            <span class="text-ink-400 font-bold">Import Log #{{ $activeImportLog['id'] }}</span>
                            @if($isProses)
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-up-primary/20 text-up-primary border border-up-primary/30 flex items-center gap-1.5 animate-pulse">
                                    <span class="w-1.5 h-1.5 rounded-full bg-up-primary animate-ping"></span>
                                    <span>Sedang Memproses</span>
                                </span>
                            @elseif($isGagal)
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-up-red/20 text-up-red border border-up-red/30">
                                    Gagal
                                </span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-up-mint/20 text-up-mint border border-up-mint/30">
                                    Selesai
                                </span>
                            @endif
                        </div>

                        <!-- Live Progress Bar -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-[11px] font-mono">
                                <span class="text-ink-400 font-semibold">Kemajuan Proses:</span>
                                @if($totalBaris > 0)
                                    <span class="text-up-mint font-black">{{ $persen }}% ({{ $selesaiCount }} dari {{ $totalBaris }} baris)</span>
                                @else
                                    <span class="text-up-mint font-black">{{ $persen }}%</span>
                                @endif
                            </div>
                            <div class="w-full h-3 rounded-full bg-black/60 overflow-hidden p-0.5 border border-white/10">
                                <div class="h-full rounded-full bg-gradient-to-r from-up-primary via-up-mint to-up-mint transition-all duration-300 {{ $isProses ? 'animate-pulse' : '' }}"
                                     style="width: {{ $persen }}%"></div>
                            </div>
                        </div>

                        <!-- Metrik Grid -->
                        <div class="grid grid-cols-3 gap-2 pt-1 text-center font-mono">
                            <div class="p-2 rounded-xl bg-white/5 border border-white/5">
                                <div class="text-[9px] uppercase text-ink-400">Total</div>
                                <div class="text-sm font-black text-white mt-0.5">{{ $totalBaris }}</div>
                            </div>
                            <div class="p-2 rounded-xl bg-up-mint/10 border border-up-mint/20">
                                <div class="text-[9px] uppercase text-up-mint">Sukses</div>
                                <div class="text-sm font-black text-up-mint mt-0.5">{{ $sukses }}</div>
                            </div>
                            <div class="p-2 rounded-xl bg-up-red/10 border border-up-red/20">
                                <div class="text-[9px] uppercase text-up-red">Gagal</div>
                                <div class="text-sm font-black text-up-red mt-0.5">{{ $gagal }}</div>
                            </div>
                        </div>

                        <!-- Catatan Ringkasan / Detail Error bila ada -->
                        @if(! empty($activeImportLog['detail']['gagal_rows']) || ! empty($activeImportLog['detail']['errors']))
                            <div class="pt-2 border-t border-white/5 space-y-1">
                                <div class="text-[10px] text-up-red font-bold uppercase tracking-wider">Catatan Baris Gagal:</div>
                                <div class="max-h-32 overflow-y-auto space-y-1 pr-1 text-[11px]">
                                    @foreach($activeImportLog['detail']['gagal_rows'] ?? $activeImportLog['detail']['errors'] ?? [] as $err)
                                        <div class="text-up-red/90 p-1.5 rounded bg-up-red/5 border border-up-red/10">
                                            {{ is_array($err) ? json_encode($err) : $err }}
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-3">
                    <button wire:click="resetUpload" class="w-full sm:w-auto min-h-[44px] px-6 py-2.5 rounded-xl bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 text-ink-300 font-bold text-xs cursor-pointer hover:bg-black/10 transition flex items-center justify-center">
                        Import File Lainnya
                    </button>
                    <a href="/app/wms" wire:navigate class="w-full sm:w-auto min-h-[44px] px-6 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs cursor-pointer shadow-md shadow-up-primary/20 transition flex items-center justify-center">
                        Buka Daftar Produk WMS →
                    </a>
                </div>
            </div>
        @endif
    @else
        <!-- TAB 2: EXPORT WORKSPACE -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Ekspor Master Produk -->
            <div class="p-6 rounded-2xl bg-black/5 dark:bg-white/[0.02] border border-black/10 dark:border-white/5 space-y-4">
                <div class="space-y-1">
                    <h3 class="text-sm font-bold text-ink-900 dark:text-white flex items-center gap-2">
                        <span>📊</span>
                        <span>Ekspor Master Data Katalog Produk</span>
                    </h3>
                    <p class="text-xs text-ink-400">
                        Unduh seluruh data produk, SKU varian, harga tier membership, tipe HP kompatibel, dan saldo stok gudang aktif. File hasil ekspor memiliki struktur yang identik 100% dengan template impor resmi sehingga dapat diedit massal dan langsung diimpor kembali (bidirectional / round-trip compatible).
                    </p>
                </div>

                <div class="space-y-3 pt-2">
                    <button type="button"
                            wire:click="exportData('xlsx')"
                            class="w-full py-3 px-4 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs transition flex items-center justify-between cursor-pointer shadow-md shadow-up-primary/20">
                        <span class="flex items-center gap-2">
                            <span>📊</span>
                            <span>Ekspor Data Master Produk (.xlsx)</span>
                        </span>
                        <span>Unduh .XLSX ↓</span>
                    </button>

                    <button type="button"
                            wire:click="exportData('csv')"
                            class="w-full py-3 px-4 rounded-xl bg-black/5 dark:bg-white/10 hover:bg-black/10 dark:hover:bg-white/15 border border-black/10 dark:border-white/10 text-ink-900 dark:text-white font-bold text-xs transition flex items-center justify-between cursor-pointer">
                        <span class="flex items-center gap-2">
                            <span>📄</span>
                            <span>Ekspor Data Master Produk (.csv)</span>
                        </span>
                        <span>Unduh .CSV ↓</span>
                    </button>

                    <a href="/api/wms/produk/import/template"
                       class="w-full py-3 px-4 rounded-xl bg-up-primary/10 hover:bg-up-primary/20 border border-up-primary/30 text-up-primary font-bold text-xs transition flex items-center justify-between cursor-pointer">
                        <span class="flex items-center gap-2">
                            <span>📗</span>
                            <span>Template Import Resmi (.xlsx)</span>
                        </span>
                        <span>Unduh Template ↓</span>
                    </a>
                </div>
            </div>

            <!-- Petunjuk Pengisian -->
            <div class="p-6 rounded-2xl bg-black/5 dark:bg-white/[0.02] border border-black/10 dark:border-white/5 space-y-4">
                <div class="space-y-1">
                    <h3 class="text-sm font-bold text-ink-900 dark:text-white flex items-center gap-2">
                        <span>📋</span>
                        <span>Struktur Kolom Wajib Template</span>
                    </h3>
                    <p class="text-xs text-ink-400">
                        Format kolom yang diterima oleh sistem parser WMS Ute Parts:
                    </p>
                </div>

                <div class="space-y-2 text-xs text-ink-300">
                    <div class="p-2.5 rounded-lg bg-black/20 font-mono text-[11px] text-up-mint">
                        sku, nama, satuan, harga_beli, harga_jual, brand, kategori, sub_kategori, tipe_hp, gudang, rak, stok_awal
                    </div>
                    <p class="text-[11px] text-ink-400">
                        Kolom selain SKU, Nama, dan Harga bersifat fleksibel. Kolom yang dikosongkan akan dialokasikan otomatis ke master default bawaan sistem (Rak Default, Gudang Utama, pcs, OEM, Original).
                    </p>
                </div>
            </div>
        </div>
    @endif
</div>
