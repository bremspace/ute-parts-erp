<div class="space-y-6 max-w-7xl mx-auto pb-12"
     @if($importStep === 'selesai' || ($activeImportLog && $activeImportLog['status'] === 'proses')) wire:poll.1200ms="cekStatusImport" @endif
     @if($activeTab === 'foto' && ($fotoStep === 'processing' || ($activeFotoImportLog && $activeFotoImportLog['status'] === 'proses'))) wire:poll.1200ms="cekStatusFotoImport" @endif>
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

    <!-- Main Navigation Tabs: Import vs Foto vs Export -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 p-1.5 bg-black/5 dark:bg-white/[0.03] rounded-2xl border border-black/10 dark:border-white/5 w-full sm:w-fit">
        <button type="button"
                wire:click="setTab('import')"
                class="min-h-[44px] px-5 py-2.5 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer {{ $activeTab === 'import' ? 'bg-up-primary text-white shadow-md shadow-up-primary/20' : 'text-ink-400 hover:text-white' }}">
            <span>📥</span>
            <span>Import Produk &amp; Penyesuaian</span>
        </button>
        <button type="button"
                wire:click="setTab('foto')"
                class="min-h-[44px] px-5 py-2.5 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer {{ $activeTab === 'foto' ? 'bg-up-primary text-white shadow-md shadow-up-primary/20' : 'text-ink-400 hover:text-white' }}">
            <span>📸</span>
            <span>Import Foto Massal (Auto SKU)</span>
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
                    @php
                        $duplikatList = $importPreview['duplikat_rows'] ?? [];
                        $totalDuplikatCount = count($duplikatList);
                        $displayedDuplikatRows = array_slice($duplikatList, 0, 100);
                    @endphp
                    <div class="p-5 rounded-2xl bg-up-primary/10 border border-up-primary/30 space-y-4">
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <div>
                                <h3 class="text-sm font-bold text-up-primary flex items-center gap-2">
                                    <span>⚡</span>
                                    <span>Penyesuaian Duplikasi SKU / Barcode ({{ $totalDuplikatCount }} Baris)</span>
                                </h3>
                                <p class="text-[11px] text-ink-300 mt-0.5">
                                    Daripada baris ini dilewati, Anda dapat <strong>mengubah SKU langsung</strong> di sini, memilih opsi <strong>timpa/update data produk eksisting</strong>, atau melewati baris tersebut.
                                </p>
                            </div>
                        </div>

                        <!-- Toolbar: Pilih Semua Tindakan Duplikat -->
                        <div class="p-3 rounded-xl bg-black/30 border border-white/10 flex items-center justify-between flex-wrap gap-3">
                            <span class="text-[11px] text-ink-300 font-semibold">Terapkan ke Seluruh Duplikat (Pilih Semua):</span>
                            <div class="flex items-center gap-2 flex-wrap">
                                <button type="button"
                                        wire:click="setAksiDuplikatSemua('ganti_sku')"
                                        class="px-2.5 py-1.5 rounded-lg text-[10px] font-bold bg-up-primary hover:bg-up-primary/80 text-white cursor-pointer transition shadow flex items-center gap-1.5">
                                    <span>✏️</span> Ganti Semua SKU Otomatis
                                </button>
                                <button type="button"
                                        wire:click="setAksiDuplikatSemua('timpa_update')"
                                        class="px-2.5 py-1.5 rounded-lg text-[10px] font-bold bg-up-mint hover:bg-up-mint/80 text-ink-950 font-black cursor-pointer transition shadow flex items-center gap-1.5"
                                        title="Timpa/update data produk eksisting di database">
                                    <span>🔄</span> Timpa Semua Data DB
                                </button>
                                <button type="button"
                                        wire:click="kosongkanSemuaBarcodeDuplikat"
                                        class="px-2.5 py-1.5 rounded-lg text-[10px] font-bold bg-up-amber hover:bg-up-amber/80 text-ink-950 font-black cursor-pointer transition shadow flex items-center gap-1.5"
                                        title="Kosongkan barcode yang duplikat agar produk tetap diimpor">
                                    <span>🚫</span> Kosongkan Semua Barcode Duplikat
                                </button>
                                <button type="button"
                                        wire:click="setAksiDuplikatSemua('skip')"
                                        class="px-2.5 py-1.5 rounded-lg text-[10px] font-bold bg-up-red/80 hover:bg-up-red text-white cursor-pointer transition shadow flex items-center gap-1.5">
                                    <span>⏭️</span> Lewati Semua Duplikat
                                </button>
                            </div>
                        </div>

                        @if($totalDuplikatCount > 100)
                            <div class="p-2.5 rounded-lg bg-white/5 border border-white/10 text-[11px] text-ink-300 flex items-center justify-between">
                                <span>Menampilkan 100 dari {{ $totalDuplikatCount }} baris duplikat.</span>
                                <span class="text-ink-400">Gunakan tombol 'Terapkan ke Seluruh Duplikat' di atas untuk mengatur tindakan massal.</span>
                            </div>
                        @endif

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
                                    @foreach($displayedDuplikatRows as $dr)
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
                                                    @if(($dr['tipe'] ?? '') === 'barcode' || ! empty($dr['barcode']))
                                                        <button type="button"
                                                                wire:click="setBarcodeAction({{ $dr['baris'] }}, 'kosongkan_barcode')"
                                                                class="min-h-[32px] px-2.5 py-1 rounded-lg text-[10px] font-bold cursor-pointer transition {{ $currentAksi === 'kosongkan_barcode' ? 'bg-up-amber text-ink-950 font-black ring-1 ring-up-amber/50' : 'bg-white/5 hover:bg-white/10 text-ink-300' }}"
                                                                title="Kosongkan nilai barcode agar produk baru tetap diimpor">
                                                            🚫 Kosongkan Barcode
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
                                                @elseif($currentAksi === 'kosongkan_barcode')
                                                    <span class="text-[10px] text-up-amber font-semibold">Barcode akan dikosongkan (produk baru tetap diimpor)</span>
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
                            @foreach($displayedDuplikatRows as $dr)
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
                                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5">
                                            <button type="button"
                                                    wire:click="setAksiDuplikat({{ $dr['baris'] }}, 'ganti_sku')"
                                                    class="min-h-[40px] px-2 py-2 rounded-xl text-xs font-bold cursor-pointer transition flex items-center justify-center text-center {{ $currentAksi === 'ganti_sku' ? 'bg-up-primary text-white ring-2 ring-up-primary/60 shadow-sm' : 'bg-white/5 hover:bg-white/10 text-ink-300' }}">
                                                ✏️ Ganti SKU
                                            </button>
                                            @if($dr['lokasi'] === 'database')
                                                <button type="button"
                                                        wire:click="setAksiDuplikat({{ $dr['baris'] }}, 'timpa_update')"
                                                        class="min-h-[40px] px-2 py-2 rounded-xl text-xs font-black cursor-pointer transition flex items-center justify-center text-center {{ $currentAksi === 'timpa_update' ? 'bg-up-mint text-ink-950 ring-2 ring-up-mint/60 shadow-sm' : 'bg-white/5 hover:bg-white/10 text-ink-300' }}">
                                                    🔄 Timpa
                                                </button>
                                            @endif
                                            @if(($dr['tipe'] ?? '') === 'barcode' || ! empty($dr['barcode']))
                                                <button type="button"
                                                        wire:click="setBarcodeAction({{ $dr['baris'] }}, 'kosongkan_barcode')"
                                                        class="min-h-[40px] px-2 py-2 rounded-xl text-xs font-black cursor-pointer transition flex items-center justify-center text-center {{ $currentAksi === 'kosongkan_barcode' ? 'bg-up-amber text-ink-950 ring-2 ring-up-amber/60 shadow-sm' : 'bg-white/5 hover:bg-white/10 text-ink-300' }}">
                                                    🚫 Kosongkan
                                                </button>
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
                                    @elseif($currentAksi === 'kosongkan_barcode')
                                        <div class="text-[11px] text-up-amber font-semibold bg-up-amber/10 p-2 rounded-lg border border-up-amber/20">
                                            ✓ Barcode akan dikosongkan (produk baru tetap diimpor).
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
                @php
                    $allErrorRows = $importPreview['error_rows'] ?? [];
                    $activeErrorRows = array_values(array_filter($allErrorRows, function ($errRow) use ($resolusiDuplikat, $resolusiBarcode) {
                        $baris = $errRow['baris'];
                        $sku = $errRow['sku'];
                        $res = $resolusiDuplikat[$baris] ?? ($sku !== '' ? ($resolusiDuplikat[$sku] ?? null) : null);
                        $aksi = is_array($res) ? ($res['aksi'] ?? null) : $res;
                        $resBc = $resolusiBarcode[$baris] ?? null;
                        $aksiBc = is_array($resBc) ? ($resBc['aksi'] ?? null) : $resBc;

                        $nonDup = array_filter($errRow['errors'] ?? [], function ($e) use ($aksi, $aksiBc) {
                            if (in_array($aksi, ['ganti_sku', 'timpa_update', 'skip'], true) && str_contains($e, 'SKU') && str_contains($e, 'duplikat')) {
                                return false;
                            }
                            if (($aksiBc === 'kosongkan_barcode' || $aksi === 'kosongkan_barcode') && str_contains($e, 'Barcode') && str_contains($e, 'duplikat')) {
                                return false;
                            }
                            return true;
                        });

                        return ! empty($nonDup);
                    }));
                    $totalErrorCount = count($activeErrorRows);
                    $displayedErrorRows = array_slice($activeErrorRows, 0, 100);
                @endphp
                @if($totalErrorCount > 0)
                    <div class="p-5 rounded-2xl bg-up-red/10 border border-up-red/30 space-y-4">
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <div>
                                <h3 class="text-sm font-bold text-up-red flex items-center gap-2">
                                    <span>⛔</span>
                                    <span>Rincian Seluruh Baris Error ({{ $totalErrorCount }} Baris)</span>
                                </h3>
                                <p class="text-[10px] text-ink-400 mt-0.5">Rincian error lengkap — Anda dapat memilih untuk mengabaikan (skip) baris error ini atau memperbaikinya.</p>
                            </div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <button type="button"
                                        wire:click="exportErrorRows"
                                        class="px-2.5 py-1.5 rounded-lg text-[10px] font-bold bg-white/10 hover:bg-white/20 text-white cursor-pointer transition shadow flex items-center gap-1.5"
                                        title="Unduh daftar baris error dalam format CSV">
                                    <span>📥</span> Unduh CSV Error
                                </button>
                                <button type="button"
                                        wire:click="abaikanSemuaBarisError"
                                        class="px-2.5 py-1.5 rounded-lg text-[10px] font-bold bg-up-red/80 hover:bg-up-red text-white cursor-pointer transition shadow flex items-center gap-1.5"
                                        title="Lewati semua baris yang memiliki error agar proses import tetap dapat berjalan">
                                    <span>⏭️</span> Abaikan Semua Error (Skip)
                                </button>
                                @if(! empty($resolusiErrorBaris))
                                    <button type="button"
                                            wire:click="batalAbaikanSemuaBarisError"
                                            class="px-2.5 py-1.5 rounded-lg text-[10px] font-bold bg-white/5 hover:bg-white/10 text-ink-300 cursor-pointer transition">
                                        Batal Abaikan
                                    </button>
                                @endif
                            </div>
                        </div>

                        @if($totalErrorCount > 100)
                            <div class="p-2.5 rounded-lg bg-white/5 border border-white/10 text-[11px] text-ink-300">
                                Menampilkan 100 dari {{ $totalErrorCount }} baris error pertama. Gunakan tombol 'Abaikan Semua Error' di atas untuk mengabaikan seluruh baris error secara massal.
                            </div>
                        @endif

                        <div class="max-h-80 overflow-y-auto space-y-2.5 pr-1 divide-y divide-white/5">
                            @foreach($displayedErrorRows as $errRow)
                                @php
                                    $isSkipped = ($resolusiErrorBaris[$errRow['baris']] ?? '') === 'skip';
                                @endphp
                                <div class="pt-2.5 first:pt-0 p-3 rounded-xl bg-black/40 border {{ $isSkipped ? 'border-up-amber/40 bg-up-amber/5' : 'border-up-red/20' }} space-y-1.5 transition">
                                    <div class="flex items-center justify-between text-xs font-mono flex-wrap gap-2">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold px-2 py-0.5 rounded {{ $isSkipped ? 'bg-up-amber/20 text-up-amber border border-up-amber/30' : 'bg-up-red/20 text-up-red border border-up-red/30' }}">
                                                Baris #{{ $errRow['baris'] }}
                                            </span>
                                            <span class="text-ink-400 text-[11px]">SKU: <strong class="text-white">{{ $errRow['sku'] ?: '(kosong)' }}</strong></span>
                                        </div>
                                        <div>
                                            @if($isSkipped)
                                                <div class="flex items-center gap-2">
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-up-amber/20 text-up-amber border border-up-amber/40">
                                                        ⏭️ Akan Dilewati (Skip)
                                                    </span>
                                                    <button type="button"
                                                            wire:click="setErrorAction({{ $errRow['baris'] }}, 'none')"
                                                            class="text-[10px] text-ink-400 hover:text-white underline cursor-pointer">
                                                        Batal
                                                    </button>
                                                </div>
                                            @else
                                                <button type="button"
                                                        wire:click="setErrorAction({{ $errRow['baris'] }}, 'skip')"
                                                        class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-white/5 hover:bg-white/15 text-ink-200 hover:text-white cursor-pointer transition border border-white/10 flex items-center gap-1">
                                                    <span>⏭️</span> Abaikan Baris Ini (Skip)
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                    @if(! empty($errRow['nama']))
                                        <div class="text-white text-xs font-semibold">{{ $errRow['nama'] }}</div>
                                    @endif
                                    <ul class="space-y-1 mt-1 pt-1 border-t border-white/5">
                                        @foreach($errRow['errors'] as $errMsg)
                                            <li class="{{ $isSkipped ? 'text-up-amber' : 'text-up-red' }} text-[11px] flex items-start gap-1.5 leading-snug">
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
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-1 text-center font-mono">
                            <div class="p-2 rounded-xl bg-white/5 border border-white/5">
                                <div class="text-[9px] uppercase text-ink-400">Total</div>
                                <div class="text-sm font-black text-white mt-0.5">{{ $totalBaris }}</div>
                            </div>
                            <div class="p-2 rounded-xl bg-up-mint/10 border border-up-mint/20">
                                <div class="text-[9px] uppercase text-up-mint">Sukses</div>
                                <div class="text-sm font-black text-up-mint mt-0.5">{{ $sukses }}</div>
                            </div>
                            <div class="p-2 rounded-xl bg-up-amber/10 border border-up-amber/20">
                                <div class="text-[9px] uppercase text-up-amber">Dilewati</div>
                                <div class="text-sm font-black text-up-amber mt-0.5">{{ $activeImportLog['skip'] ?? 0 }}</div>
                            </div>
                            <div class="p-2 rounded-xl bg-up-red/10 border border-up-red/20">
                                <div class="text-[9px] uppercase text-up-red">Gagal</div>
                                <div class="text-sm font-black text-up-red mt-0.5">{{ $gagal }}</div>
                            </div>
                        </div>

                        <!-- Real-time Processing Item Ticker -->
                        @if($isProses && (! empty($activeImportLog['current_sku']) || ! empty($activeImportLog['current_nama'])))
                            <div class="p-3 rounded-xl bg-up-primary/10 border border-up-primary/20 space-y-1">
                                <div class="flex items-center justify-between text-[10px] text-ink-400">
                                    <span class="flex items-center gap-1.5 font-bold text-up-primary">
                                        <span class="w-1.5 h-1.5 rounded-full bg-up-primary animate-ping"></span>
                                        Sedang Memproses Baris {{ $activeImportLog['current_baris'] ?? '-' }}...
                                    </span>
                                    <span class="font-mono text-ink-400">SKU: {{ $activeImportLog['current_sku'] ?? '-' }}</span>
                                </div>
                                <div class="text-xs font-semibold text-white truncate" title="{{ $activeImportLog['current_nama'] ?? '' }}">
                                    {{ $activeImportLog['current_nama'] ?? '-' }}
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                @php
                    $detailList = $activeImportLog['detail'] ?? [];
                    $gagalItems = $activeImportLog['gagal_rows'] ?? array_values(array_filter($detailList, fn ($d) => is_array($d) && ($d['status'] ?? '') === 'gagal'));
                    $skipItems = $activeImportLog['skip_rows'] ?? array_values(array_filter($detailList, fn ($d) => is_array($d) && ($d['status'] ?? '') === 'skip'));
                    $bermasalahItems = match ($errorFilterTab) {
                        'gagal' => $gagalItems,
                        'skip' => $skipItems,
                        default => array_values(array_filter($detailList, fn ($d) => is_array($d) && in_array($d['status'] ?? '', ['gagal', 'skip'], true))),
                    };
                    $totalBermasalah = count($gagalItems) + count($skipItems);
                @endphp

                <!-- Interaktif: Rincian Seluruh Baris Bermasalah (Gagal & Dilewati) -->
                @if($totalBermasalah > 0)
                    <div class="max-w-4xl mx-auto p-5 sm:p-6 rounded-2xl bg-black/40 border border-white/10 text-left space-y-4 shadow-xl">
                        <div class="flex items-center justify-between flex-wrap gap-3 pb-3 border-b border-white/10">
                            <div>
                                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                                    <span>📋</span>
                                    <span>Rincian Baris Bermasalah ({{ $totalBermasalah }} Baris)</span>
                                </h3>
                                <p class="text-[11px] text-ink-400 mt-0.5">Daftar baris data yang mengalami error atau dilewati saat proses import.</p>
                            </div>
                            <button type="button"
                                    wire:click="exportErrorRows"
                                    class="px-3 py-2 rounded-xl text-xs font-bold bg-up-primary hover:bg-up-primary/80 text-white cursor-pointer transition shadow flex items-center gap-1.5">
                                <span>📥</span> Unduh Laporan Error (.CSV)
                            </button>
                        </div>

                        <!-- Filter Tabs -->
                        <div class="flex items-center gap-2 border-b border-white/10 pb-2">
                            <button type="button"
                                    wire:click="setErrorFilterTab('semua')"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold cursor-pointer transition {{ $errorFilterTab === 'semua' ? 'bg-white/15 text-white ring-1 ring-white/30' : 'text-ink-400 hover:text-white' }}">
                                Semua ({{ $totalBermasalah }})
                            </button>
                            <button type="button"
                                    wire:click="setErrorFilterTab('gagal')"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold cursor-pointer transition {{ $errorFilterTab === 'gagal' ? 'bg-up-red/20 text-up-red ring-1 ring-up-red/40' : 'text-ink-400 hover:text-up-red' }}">
                                Gagal ({{ count($gagalItems) }})
                            </button>
                            <button type="button"
                                    wire:click="setErrorFilterTab('skip')"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold cursor-pointer transition {{ $errorFilterTab === 'skip' ? 'bg-up-amber/20 text-up-amber ring-1 ring-up-amber/40' : 'text-ink-400 hover:text-up-amber' }}">
                                Dilewati / Skip ({{ count($skipItems) }})
                            </button>
                        </div>

                        <!-- Table Bermasalah -->
                        <div class="overflow-x-auto rounded-xl border border-white/10 bg-black/30">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="text-ink-400 border-b border-white/10 bg-white/5 text-[10px] uppercase tracking-wider font-mono">
                                        <th class="py-2.5 px-3">Baris</th>
                                        <th class="py-2.5 px-3">SKU</th>
                                        <th class="py-2.5 px-3">Nama Produk</th>
                                        <th class="py-2.5 px-3">Status</th>
                                        <th class="py-2.5 px-3">Keterangan / Alasan Error</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5 text-[11px]">
                                    @forelse(array_slice($bermasalahItems, 0, $errorDisplayLimit) as $item)
                                        <tr class="hover:bg-white/[0.02] transition">
                                            <td class="py-2.5 px-3 font-mono font-bold text-ink-300">#{{ $item['baris'] ?? '-' }}</td>
                                            <td class="py-2.5 px-3 font-mono text-white font-bold">{{ $item['sku'] ?? ($item['kode'] ?? '-') }}</td>
                                            <td class="py-2.5 px-3 text-ink-200 max-w-[200px] truncate" title="{{ $item['nama'] ?? '' }}">{{ $item['nama'] ?? '-' }}</td>
                                            <td class="py-2.5 px-3">
                                                @if(($item['status'] ?? '') === 'skip')
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-amber/20 text-up-amber border border-up-amber/30">
                                                        Dilewati
                                                    </span>
                                                @else
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-red/20 text-up-red border border-up-red/30">
                                                        Gagal
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="py-2.5 px-3">
                                                <div class="text-[11px] {{ ($item['status'] ?? '') === 'gagal' ? 'text-up-red' : 'text-ink-300' }}">
                                                    {{ $item['error'] ?? ($item['catatan'] ?? 'Terjadi kendala pada baris ini') }}
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="py-4 text-center text-ink-500 italic">
                                                Tidak ada baris data pada kategori ini.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if(count($bermasalahItems) > $errorDisplayLimit)
                            <div class="text-center pt-2">
                                <button type="button"
                                        wire:click="loadMoreErrors"
                                        class="px-4 py-2 rounded-xl text-xs font-bold bg-white/5 hover:bg-white/10 text-ink-200 border border-white/10 cursor-pointer transition">
                                    Muat 50 Baris Lainnya (Menampilkan {{ $errorDisplayLimit }} dari {{ count($bermasalahItems) }})
                                </button>
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
    @elseif($activeTab === 'foto')
        <!-- TAB 3: IMPORT FOTO BULK WORKSPACE -->
        @if($fotoStep === 'upload')
            <!-- Step 1: Upload Form & Options -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left: Upload Box -->
                <div class="lg:col-span-2 p-6 rounded-2xl bg-black/5 dark:bg-white/[0.02] border border-black/10 dark:border-white/5 space-y-6">
                    <div class="flex items-center justify-between border-b border-black/5 dark:border-white/5 pb-3">
                        <h2 class="font-bold text-sm text-ink-900 dark:text-white flex items-center gap-2">
                            <span>1.</span>
                            <span>Pilih Sumber Foto &amp; Opsi Penggabungan</span>
                        </h2>
                        <span class="text-[10px] text-ink-400">WebP Auto-Compress (1200px &amp; 250px)</span>
                    </div>

                    <!-- Pilihan Sumber Upload (Folder / Multi-File vs ZIP) -->
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-ink-700 dark:text-ink-300">Metode Pengunggahan:</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="p-3.5 rounded-xl border cursor-pointer transition flex items-start gap-3 {{ $fotoSourceType === 'files' ? 'bg-up-primary/10 border-up-primary ring-1 ring-up-primary/30' : 'bg-black/5 dark:bg-white/5 border-black/10 dark:border-white/10 hover:bg-black/10 dark:hover:bg-white/10' }}">
                                <input type="radio" wire:model.live="fotoSourceType" value="files" class="mt-0.5 text-up-primary">
                                <div class="space-y-0.5">
                                    <div class="font-bold text-xs text-ink-950 dark:text-white flex items-center gap-1.5">
                                        <span>📁</span>
                                        <span>Folder Lokal / Multi-File</span>
                                        @if($fotoSourceType === 'files')
                                            <span class="px-1.5 py-0.2 rounded text-[9px] bg-up-primary text-white font-bold ml-1">Aktif</span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-ink-600 dark:text-ink-400">Pilih satu folder langsung dari PC atau seleksi puluhan foto</div>
                                </div>
                            </label>
                            <label class="p-3.5 rounded-xl border cursor-pointer transition flex items-start gap-3 {{ $fotoSourceType === 'zip' ? 'bg-up-primary/10 border-up-primary ring-1 ring-up-primary/30' : 'bg-black/5 dark:bg-white/5 border-black/10 dark:border-white/10 hover:bg-black/10 dark:hover:bg-white/10' }}">
                                <input type="radio" wire:model.live="fotoSourceType" value="zip" class="mt-0.5 text-up-primary">
                                <div class="space-y-0.5">
                                    <div class="font-bold text-xs text-ink-950 dark:text-white flex items-center gap-1.5">
                                        <span>🗜️</span>
                                        <span>Berkas Arsip (.ZIP)</span>
                                        @if($fotoSourceType === 'zip')
                                            <span class="px-1.5 py-0.2 rounded text-[9px] bg-up-primary text-white font-bold ml-1">Aktif</span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-ink-600 dark:text-ink-400">Unggah satu file .zip (diekstrak otomatis di server)</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Opsi Mode Penggabungan Foto -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-ink-700 dark:text-ink-300">Strategi Terhadap Foto Produk Eksisting:</label>
                            <span class="text-[10px] text-ink-400">Tindakan jika produk di sistem sudah memiliki foto</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <label class="p-3 rounded-xl border cursor-pointer transition flex items-start gap-2.5 {{ $fotoMode === 'append' ? 'bg-up-primary/10 border-up-primary ring-1 ring-up-primary/30' : 'bg-black/5 dark:bg-white/5 border-black/10 dark:border-white/10 hover:bg-black/10 dark:hover:bg-white/10' }}">
                                <input type="radio" wire:model.live="fotoMode" value="append" class="mt-0.5 text-up-primary">
                                <div class="space-y-0.5">
                                    <div class="font-bold text-[11px] text-ink-950 dark:text-white flex items-center gap-1">
                                        <span>🖼️</span>
                                        <span>Tambahkan ke Galeri (Append)</span>
                                        @if($fotoMode === 'append')
                                            <span class="px-1.5 py-0.2 rounded text-[9px] bg-up-primary text-white font-bold ml-1">Dipilih</span>
                                        @endif
                                    </div>
                                    <div class="text-[10px] text-ink-600 dark:text-ink-400 leading-tight">Foto baru dari komputer akan di-append ke daftar galeri produk (multi-foto didukung di Marketplace &amp; POS)</div>
                                </div>
                            </label>
                            <label class="p-3 rounded-xl border cursor-pointer transition flex items-start gap-2.5 {{ $fotoMode === 'replace' ? 'bg-up-primary/10 border-up-primary ring-1 ring-up-primary/30' : 'bg-black/5 dark:bg-white/5 border-black/10 dark:border-white/10 hover:bg-black/10 dark:hover:bg-white/10' }}">
                                <input type="radio" wire:model.live="fotoMode" value="replace" class="mt-0.5 text-up-primary">
                                <div class="space-y-0.5">
                                    <div class="font-bold text-[11px] text-ink-950 dark:text-white flex items-center gap-1">
                                        <span>🔄</span>
                                        <span>Ganti Total (Replace)</span>
                                        @if($fotoMode === 'replace')
                                            <span class="px-1.5 py-0.2 rounded text-[9px] bg-up-primary text-white font-bold ml-1">Dipilih</span>
                                        @endif
                                    </div>
                                    <div class="text-[10px] text-ink-600 dark:text-ink-400 leading-tight">Hapus seluruh foto lama dari disk storage &amp; gantikan sepenuhnya dengan foto baru</div>
                                </div>
                            </label>
                            <label class="p-3 rounded-xl border cursor-pointer transition flex items-start gap-2.5 {{ $fotoMode === 'skip_existing' ? 'bg-up-primary/10 border-up-primary ring-1 ring-up-primary/30' : 'bg-black/5 dark:bg-white/5 border-black/10 dark:border-white/10 hover:bg-black/10 dark:hover:bg-white/10' }}">
                                <input type="radio" wire:model.live="fotoMode" value="skip_existing" class="mt-0.5 text-up-primary">
                                <div class="space-y-0.5">
                                    <div class="font-bold text-[11px] text-ink-950 dark:text-white flex items-center gap-1">
                                        <span>⏭️</span>
                                        <span>Hanya Jika Kosong (Skip)</span>
                                        @if($fotoMode === 'skip_existing')
                                            <span class="px-1.5 py-0.2 rounded text-[9px] bg-up-primary text-white font-bold ml-1">Dipilih</span>
                                        @endif
                                    </div>
                                    <div class="text-[10px] text-ink-600 dark:text-ink-400 leading-tight">Lewati jika produk di sistem sudah memiliki foto (hanya isi produk tanpa foto)</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Input Dropzone & Pre-Flight Sorter -->
                    <div x-data="bulkFotoUploader($wire)"
                         wire:key="bulk-uploader-files-dropzone-panel"
                         class="space-y-4">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-ink-700 dark:text-ink-300">Pilih Berkas Foto Produk (HP / Tablet / Laptop):</label>
                            <span class="text-[10px] text-ink-400">Sortasi otomatis SKU sebelum diunggah</span>
                        </div>

                        <!-- Mode Selector & Pickers (Tergantung fotoSourceType) -->
                        <div x-show="!isUploading && !isPreChecking">
                            @if($fotoSourceType === 'files')
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <!-- Option 1: Mobile-friendly Multi-File / Gallery / Camera -->
                                    <div class="relative overflow-hidden p-5 rounded-xl border-2 border-dashed border-up-primary/40 hover:border-up-primary bg-up-primary/[0.03] dark:bg-up-primary/[0.05] transition text-center space-y-2 cursor-pointer group">
                                        <input type="file"
                                               @change="onFilesSelected($event)"
                                               multiple
                                               accept="image/*,.heic,.heif,.avif,.webp,.png,.jpg,.jpeg,.bmp"
                                               class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20">
                                        <div class="text-3xl group-hover:scale-110 transition-transform">📸</div>
                                        <div class="font-bold text-xs text-ink-900 dark:text-white">Pilih Foto dari Galeri / Kamera / File</div>
                                        <p class="text-[11px] text-ink-400">Dukungan penuh smartphone &amp; tablet. Pilih banyak foto sekaligus (JPG, PNG, WebP, HEIC, dll.)</p>
                                        <div class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-up-primary text-white font-bold text-xs shadow-md shadow-up-primary/20 pointer-events-none">
                                            <span>📂</span>
                                            <span>Buka Galeri / Pilih Foto...</span>
                                        </div>
                                    </div>

                                    <!-- Option 2: Folder Upload (Khusus Laptop / PC) -->
                                    <div class="relative overflow-hidden p-5 rounded-xl border-2 border-dashed border-black/15 dark:border-white/15 hover:border-up-primary dark:hover:border-up-primary bg-black/5 dark:bg-white/[0.01] transition text-center space-y-2 cursor-pointer group hidden sm:block">
                                        <input type="file"
                                               @change="onFilesSelected($event)"
                                               multiple
                                               webkitdirectory
                                               directory
                                               class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20">
                                        <div class="text-3xl group-hover:scale-110 transition-transform">📁</div>
                                        <div class="font-bold text-xs text-ink-900 dark:text-white">Unggah Satu Folder (Laptop / PC)</div>
                                        <p class="text-[11px] text-ink-400">Pilih satu folder lokal di PC (termasuk ribuan sub-file foto di dalamnya)</p>
                                        <div class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-black/10 dark:bg-white/10 text-ink-700 dark:text-ink-200 font-bold text-xs border border-black/10 dark:border-white/10 pointer-events-none">
                                            <span>📁</span>
                                            <span>Pilih Folder PC...</span>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <!-- Option 3: ZIP Archive (Client-Side Selective) -->
                                <div class="relative overflow-hidden p-6 rounded-xl border-2 border-dashed border-black/15 dark:border-white/15 hover:border-up-primary dark:hover:border-up-primary bg-black/5 dark:bg-white/[0.01] transition text-center space-y-2 cursor-pointer group">
                                    <input type="file"
                                           @change="onZipSelected($event)"
                                           accept=".zip,application/zip"
                                           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20">
                                    <div class="text-3xl group-hover:scale-110 transition-transform">🗜️</div>
                                    <div class="font-bold text-xs text-ink-900 dark:text-white">Pilih Berkas Arsip (.ZIP)</div>
                                    <p class="text-[11px] text-ink-400">Sistem akan membaca isi ZIP di browser &amp; hanya mengekstrak/mengunggah foto yang cocok dengan SKU produk</p>
                                    <div class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-up-primary text-white font-bold text-xs shadow-md shadow-up-primary/20 pointer-events-none">
                                        <span>🗜️</span>
                                        <span>Pilih Berkas .ZIP...</span>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Petunjuk awal jika belum ada file dipilih -->
                        <div x-show="rawFilesList.length === 0 && !isUploading && !isPreChecking" class="p-3 rounded-xl bg-black/5 dark:bg-white/[0.02] border border-black/5 dark:border-white/5 flex items-start gap-2.5 text-[11px] text-ink-500 dark:text-ink-400">
                            <span class="text-base leading-none">💡</span>
                            <div>
                                <span class="font-bold text-ink-700 dark:text-ink-300">Sortasi Otomatis Sebelum Upload (Pre-Flight Filter):</span>
                                Sistem akan memindai nama berkas terlebih dahulu tanpa membuang kuota/waktu Anda. Berkas yang tidak cocok dengan SKU master produk atau yang tidak sesuai strategi akan otomatis diabaikan, sehingga <strong>hanya berkas yang valid yang akan diunggah ke server</strong>.
                            </div>
                        </div>

                        <!-- Status Sedang Memindai / Pre-Checking -->
                        <div x-show="isPreChecking" class="p-5 rounded-xl bg-up-primary/10 border border-up-primary/30 text-center space-y-2">
                            <div class="inline-block animate-spin w-5 h-5 border-2 border-up-primary border-t-transparent rounded-full"></div>
                            <div class="font-bold text-xs text-ink-900 dark:text-white" x-text="statusText"></div>
                            <p class="text-[11px] text-ink-500">Mencocokkan nama berkas terhadap SKU katalog produk di database...</p>
                        </div>

                        <!-- Hasil Sortasi Pre-Flight (Siap Upload) -->
                        <div x-show="preCheckDone && !isUploading && !isPreChecking" class="p-4 rounded-xl bg-black/5 dark:bg-white/[0.03] border border-black/10 dark:border-white/10 space-y-4">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-black/5 dark:border-white/5 pb-3">
                                <div>
                                    <h3 class="font-bold text-xs text-ink-900 dark:text-white flex items-center gap-2">
                                        <span>🔍 Hasil Sortasi SKU:</span>
                                        <span class="text-ink-500 font-normal" x-text="sourceName"></span>
                                    </h3>
                                    <p class="text-[11px] text-ink-500">
                                        Total <strong class="text-ink-900 dark:text-white tabular-nums" x-text="totalScanned"></strong> foto terdeteksi. Hanya foto berstatus cocok yang akan diunggah.
                                    </p>
                                </div>
                                <button type="button" @click="resetUploader()" class="text-[11px] text-ink-400 hover:text-up-red underline cursor-pointer">
                                    Ganti Berkas / Reset
                                </button>
                            </div>

                            <!-- Statistik Sortasi Chips -->
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                <div class="p-3 rounded-xl bg-up-mint/10 border border-up-mint/30 space-y-0.5">
                                    <div class="text-[10px] text-up-mint font-bold uppercase tracking-wider">Foto Cocok (Siap Upload)</div>
                                    <div class="text-lg font-black text-up-mint tabular-nums" x-text="matchedCount"></div>
                                    <div class="text-[10px] text-ink-600 dark:text-ink-400">
                                        <span x-show="$wire.fotoMode === 'append'">Akan di-append ke galeri</span>
                                        <span x-show="$wire.fotoMode === 'replace'">Akan menggantikan foto lama</span>
                                        <span x-show="$wire.fotoMode === 'skip_existing'">Hanya untuk produk tanpa foto</span>
                                    </div>
                                </div>
                                <div class="p-3 rounded-xl bg-up-amber/10 border border-up-amber/30 space-y-0.5">
                                    <div class="text-[10px] text-up-amber font-bold uppercase tracking-wider">Dilewati / Tidak Cocok</div>
                                    <div class="text-lg font-black text-up-amber tabular-nums" x-text="skippedCount"></div>
                                    <div class="text-[10px] text-ink-600 dark:text-ink-400">Diabaikan (tidak diunggah)</div>
                                </div>
                                <div class="col-span-2 sm:col-span-1 p-3 rounded-xl bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 space-y-0.5">
                                    <div class="text-[10px] text-ink-500 font-bold uppercase tracking-wider">Efisiensi Bandwidth</div>
                                    <div class="text-lg font-black text-up-primary tabular-nums" x-text="Math.round((matchedCount / (totalScanned || 1)) * 100) + '%'"></div>
                                    <div class="text-[10px] text-ink-600 dark:text-ink-400">Hanya transfer data yang valid</div>
                                </div>
                            </div>

                            <!-- Toggle Lihat Detail Hasil Sortasi -->
                            <div class="space-y-2">
                                <button type="button" @click="showDetail = !showDetail" class="text-[11px] text-up-primary hover:underline font-bold flex items-center gap-1 cursor-pointer">
                                    <span x-text="showDetail ? '▼ Sembunyikan Rincian File' : '▶ Lihat Rincian File yang Cocok & Dilewati'"></span>
                                </button>

                                <div x-show="showDetail" class="max-h-52 overflow-y-auto rounded-xl border border-black/10 dark:border-white/10 bg-black/5 dark:bg-white/[0.02] p-2 space-y-1 text-[11px]">
                                    <template x-for="item in matchedList.slice(0, 50)" :key="item.filename">
                                        <div class="flex items-center justify-between p-1.5 rounded-lg bg-up-mint/5 border border-up-mint/20">
                                            <div class="flex items-center gap-2 truncate">
                                                <span class="text-up-mint">✓</span>
                                                <span class="font-mono text-ink-900 dark:text-white" x-text="item.filename"></span>
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0">
                                                <span class="px-1.5 py-0.2 rounded text-[9px] bg-up-mint/20 text-up-mint font-bold" x-text="'SKU: ' + item.sku"></span>
                                                <span x-show="item.has_existing" class="text-[9px] text-ink-400">(ada foto lama)</span>
                                            </div>
                                        </div>
                                    </template>
                                    <template x-for="item in skippedList.slice(0, 50)" :key="item.filename">
                                        <div class="flex items-center justify-between p-1.5 rounded-lg bg-up-amber/5 border border-up-amber/20">
                                            <div class="flex items-center gap-2 truncate text-ink-500">
                                                <span class="text-up-amber">✕</span>
                                                <span class="font-mono" x-text="item.filename"></span>
                                            </div>
                                            <span class="text-[9px] text-up-amber font-semibold shrink-0" x-text="item.reason"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Tombol Aksi Upload Terfilter -->
                            <div class="flex items-center justify-between pt-2 border-t border-black/5 dark:border-white/5">
                                <div class="text-[11px] text-ink-500">
                                    Unggah dalam batch 5 file per chunk. Server memproses kompresi WebP di background queue.
                                </div>
                                <div class="flex items-center gap-3">
                                    <button type="button"
                                            @click="startFilteredUpload()"
                                            :disabled="matchedCount === 0"
                                            class="min-h-[44px] px-6 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs transition flex items-center gap-2 cursor-pointer shadow-md shadow-up-primary/20 disabled:opacity-50 disabled:cursor-not-allowed">
                                        <span>🚀 Mulai Upload (<span x-text="matchedCount"></span> Foto Valid)</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Upload Progress Bar & Status (Active Uploading) -->
                        <div x-show="isUploading" class="p-4 rounded-xl bg-black/5 dark:bg-white/[0.03] border border-black/10 dark:border-white/10 space-y-3">
                            <div class="flex items-center justify-between text-xs font-bold text-ink-900 dark:text-white">
                                <div class="flex items-center gap-2">
                                    <span class="animate-spin inline-block w-3.5 h-3.5 border-2 border-up-primary border-t-transparent rounded-full"></span>
                                    <span x-text="statusText"></span>
                                </div>
                                <div class="tabular-nums font-mono text-up-primary" x-text="percent + '%'"></div>
                            </div>

                            <!-- Progress bar container -->
                            <div class="w-full bg-black/10 dark:bg-white/10 rounded-full h-2.5 overflow-hidden">
                                <div class="bg-gradient-to-r from-up-primary to-up-mint h-2.5 rounded-full transition-all duration-300"
                                     :style="'width: ' + percent + '%'"></div>
                            </div>

                            <div class="flex items-center justify-between text-[11px] text-ink-500 pt-1">
                                <div>
                                    Terunggah: <strong class="text-ink-900 dark:text-white tabular-nums" x-text="uploadedFiles.toLocaleString('id-ID') + ' / ' + matchedCount.toLocaleString('id-ID')"></strong> foto valid
                                </div>
                                <button type="button" @click="cancelUpload()" class="text-up-red hover:underline font-bold cursor-pointer">
                                    Batalkan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Rules & Info Box -->
                <div class="p-6 rounded-2xl bg-black/5 dark:bg-white/[0.02] border border-black/10 dark:border-white/5 space-y-4">
                    <h3 class="font-bold text-sm text-ink-900 dark:text-white flex items-center gap-2">
                        <span>💡</span>
                        <span>Aturan Penamaan &amp; Kompresi</span>
                    </h3>

                    <div class="space-y-3 text-xs text-ink-600 dark:text-ink-400 leading-relaxed">
                        <div class="p-3 rounded-xl bg-white/5 border border-white/5 space-y-1.5">
                            <div class="font-bold text-ink-900 dark:text-white flex items-center gap-1.5">
                                <span>🎯</span>
                                <span>Format Nama File yang Dikenali:</span>
                            </div>
                            <ul class="space-y-1 font-mono text-[11px] text-ink-800 dark:text-ink-200">
                                <li>• <strong class="text-up-primary">[SKU].jpg</strong> (Foto Utama)</li>
                                <li>• <strong class="text-up-primary">[SKU]_1.png</strong>, <strong class="text-up-primary">[SKU]_2.png</strong> (Galeri)</li>
                                <li>• <strong class="text-up-primary">[SKU]-1.jpeg</strong>, <strong class="text-up-primary">[SKU]-2.jpeg</strong></li>
                                <li>• <strong class="text-up-primary">[SKU] (1).webp</strong>, <strong class="text-up-primary">[SKU]-main.jpg</strong></li>
                                <li>• <strong class="text-up-primary">[SKU]/depan.jpg</strong> (Nama Subfolder)</li>
                            </ul>
                        </div>

                        <div class="p-3 rounded-xl bg-up-mint/5 border border-up-mint/20 space-y-1">
                            <div class="font-bold text-up-mint flex items-center gap-1.5">
                                <span>⚡</span>
                                <span>Kompresi WebP Otomatis:</span>
                            </div>
                            <p class="text-[11px]">
                                Semua gambar otomatis dikonversi ke format WebP (Full max 1200x1200px @ 82% &amp; Thumbnail 250x250px @ 75%). Hemat ruang simpan hingga 70% dan mempercepat loading kasir POS &amp; marketplace.
                            </p>
                        </div>

                        <div class="p-3 rounded-xl bg-up-amber/5 border border-up-amber/20 space-y-1">
                            <div class="font-bold text-up-amber flex items-center gap-1.5">
                                <span>🛡️</span>
                                <span>Jika SKU Tidak Ditemukan:</span>
                            </div>
                            <p class="text-[11px]">
                                File tidak membatalkan proses, melainkan dilewati secara aman (skip). Anda dapat mengunduh daftar file yang dilewati dalam format Excel (.xlsx) untuk perbaikan nama.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        @elseif($fotoStep === 'preview')
            <!-- Step 2: Preview & Mapping Verification -->
            <div class="space-y-5">
                <!-- Top Summary Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="p-4 rounded-2xl bg-black/5 dark:bg-white/[0.02] border border-black/10 dark:border-white/5 space-y-1">
                        <div class="text-[10px] uppercase font-bold text-ink-400">Total File Terbaca</div>
                        <div class="text-2xl font-black text-ink-950 dark:text-white">{{ $fotoScanResult['total'] ?? 0 }} File</div>
                        <div class="text-[11px] text-ink-400">Total berkas gambar dianalisis</div>
                    </div>
                    <div class="p-4 rounded-2xl bg-up-mint/10 border border-up-mint/30 space-y-1">
                        <div class="text-[10px] uppercase font-bold text-up-mint">SKU Cocok &amp; Siap Diproses</div>
                        <div class="text-2xl font-black text-up-mint">{{ $fotoScanResult['matched_count'] ?? 0 }} Foto</div>
                        <div class="text-[11px] text-up-mint/80">Terhubung langsung ke master produk</div>
                    </div>
                    <div class="p-4 rounded-2xl bg-up-red/10 border border-up-red/30 space-y-1">
                        <div class="text-[10px] uppercase font-bold text-up-red">Dilewati / SKU Tidak Ada</div>
                        <div class="text-2xl font-black text-up-red">{{ $fotoScanResult['skipped_count'] ?? 0 }} File</div>
                        <div class="text-[11px] text-up-red/80">Tidak merusak proses, dapat diunduh laporannya</div>
                    </div>
                </div>

                @if(($fotoScanResult['skipped_count'] ?? 0) > 0)
                    <!-- Error & Skip Management Banner -->
                    <div class="p-4 sm:p-5 rounded-2xl bg-up-red/10 border border-up-red/30 space-y-3">
                        <div class="flex items-center justify-between flex-wrap gap-3">
                            <div class="flex items-center gap-2">
                                <span class="text-xl">⚠️</span>
                                <div>
                                    <h3 class="text-sm font-bold text-up-red">
                                        Terdeteksi {{ $fotoScanResult['skipped_count'] ?? 0 }} Berkas Foto Tidak Cocok / SKU Tidak Ada
                                    </h3>
                                    <p class="text-[11px] text-ink-400">
                                        Berkas yang tidak memiliki SKU di katalog sistem akan dilewati secara aman tanpa menghentikan import. Anda dapat mengabaikan/melewati seluruh foto error ini atau mengunduh daftarnya.
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <button type="button"
                                        wire:click="exportFotoErrorRows"
                                        class="px-2.5 py-1.5 rounded-lg text-[10px] font-bold bg-white/10 hover:bg-white/20 text-white cursor-pointer transition shadow flex items-center gap-1.5"
                                        title="Unduh daftar baris foto error dalam format CSV">
                                    <span>📥</span> Unduh CSV Error
                                </button>
                                <button type="button"
                                        wire:click="abaikanSemuaFotoError"
                                        class="px-2.5 py-1.5 rounded-lg text-[10px] font-bold bg-up-red/80 hover:bg-up-red text-white cursor-pointer transition shadow flex items-center gap-1.5"
                                        title="Lewati semua foto yang tidak cocok agar proses import tetap berjalan">
                                    <span>⏭️</span> Abaikan Semua Error (Skip)
                                </button>
                                @if(! empty($resolusiErrorFoto))
                                    <button type="button"
                                            wire:click="batalAbaikanSemuaFotoError"
                                            class="px-2.5 py-1.5 rounded-lg text-[10px] font-bold bg-white/5 hover:bg-white/10 text-ink-300 cursor-pointer transition">
                                        Batal Abaikan
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Table Preview with Filter Tabs -->
                <div class="p-5 rounded-2xl bg-black/5 dark:bg-white/[0.02] border border-black/10 dark:border-white/5 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-black/5 dark:border-white/5 pb-3">
                        <div class="flex items-center gap-2">
                            <button type="button"
                                    wire:click="setFotoFilterTab('all')"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer {{ $fotoFilterTab === 'all' ? 'bg-up-primary text-white' : 'bg-black/5 dark:bg-white/5 text-ink-400 hover:text-white' }}">
                                Semua ({{ $fotoScanResult['total'] ?? 0 }})
                            </button>
                            <button type="button"
                                    wire:click="setFotoFilterTab('matched')"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer {{ $fotoFilterTab === 'matched' ? 'bg-up-mint text-ink-950 font-black' : 'bg-black/5 dark:bg-white/5 text-up-mint hover:bg-up-mint/20' }}">
                                Cocok ({{ $fotoScanResult['matched_count'] ?? 0 }})
                            </button>
                            <button type="button"
                                    wire:click="setFotoFilterTab('skipped')"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer {{ $fotoFilterTab === 'skipped' ? 'bg-up-red text-white' : 'bg-black/5 dark:bg-white/5 text-up-red hover:bg-up-red/20' }}">
                                Dilewati ({{ $fotoScanResult['skipped_count'] ?? 0 }})
                            </button>
                        </div>

                        @if(($fotoScanResult['skipped_count'] ?? 0) > 0)
                            <button type="button"
                                    wire:click="downloadFotoSkippedExcel"
                                    class="px-3.5 py-1.5 rounded-xl bg-up-red/10 hover:bg-up-red/20 text-up-red border border-up-red/30 font-bold text-xs transition flex items-center gap-1.5 cursor-pointer">
                                <span>📥</span>
                                <span>Unduh Daftar Dilewati (.xlsx)</span>
                            </button>
                        @endif
                    </div>

                    <!-- List Table -->
                    <div class="overflow-x-auto max-h-96 overflow-y-auto rounded-xl border border-black/10 dark:border-white/5">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-black/10 dark:bg-white/5 sticky top-0 backdrop-blur z-10 text-[10px] uppercase font-bold text-ink-400">
                                <tr>
                                    <th class="py-2.5 px-3">No</th>
                                    <th class="py-2.5 px-3">Nama File Asli</th>
                                    <th class="py-2.5 px-3">SKU Terdeteksi</th>
                                    <th class="py-2.5 px-3">Produk Sistem</th>
                                    <th class="py-2.5 px-3">Ukuran</th>
                                    <th class="py-2.5 px-3">Status Pemetaan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-black/5 dark:divide-white/5 font-mono">
                                @php
                                    $matchedList = $fotoScanResult['matched'] ?? [];
                                    $skippedList = $fotoScanResult['skipped'] ?? [];
                                    $displayList = match($fotoFilterTab) {
                                        'matched' => $matchedList,
                                        'skipped' => $skippedList,
                                        default => array_merge($matchedList, $skippedList),
                                    };
                                    $totalDisplayCount = count($displayList);
                                    $slicedDisplayList = array_slice($displayList, 0, 100);
                                @endphp

                                @forelse($slicedDisplayList as $idx => $row)
                                    @php $isMatched = isset($row['produk_id']); @endphp
                                    <tr class="hover:bg-black/5 dark:hover:bg-white/[0.02] transition">
                                        <td class="py-2 px-3 text-ink-400">{{ $idx + 1 }}</td>
                                        <td class="py-2 px-3 font-semibold text-ink-900 dark:text-white truncate max-w-xs" title="{{ $row['filename'] }}">
                                            {{ $row['filename'] }}
                                        </td>
                                        <td class="py-2 px-3">
                                            @if(! empty($row['sku']))
                                                <span class="px-1.5 py-0.5 rounded bg-black/10 dark:bg-white/10 font-bold text-up-primary">
                                                    {{ $row['sku'] }}
                                                </span>
                                            @else
                                                <span class="text-ink-400 italic">(kosong)</span>
                                            @endif
                                        </td>
                                        <td class="py-2 px-3 font-sans">
                                            @if($isMatched)
                                                <span class="text-ink-800 dark:text-ink-200">{{ $row['produk_nama'] }}</span>
                                                @if(! empty($row['is_primary']))
                                                    <span class="ml-1 text-[9px] px-1.5 py-0.2 rounded bg-up-primary/20 text-up-primary font-bold">Cover</span>
                                                @endif
                                            @else
                                                <span class="text-up-red text-[11px]">{{ $row['reason'] ?? 'Dilewati' }}</span>
                                            @endif
                                        </td>
                                        <td class="py-2 px-3 text-ink-400 text-[11px]">
                                            {{ ! empty($row['size']) ? round($row['size'] / 1024, 1).' KB' : '-' }}
                                        </td>
                                        <td class="py-2 px-3">
                                            @if($isMatched)
                                                <span class="inline-flex items-center gap-1 text-[11px] text-up-mint font-bold">
                                                    <span>●</span>
                                                    <span>Siap Dimetakan</span>
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 text-[11px] text-up-red font-semibold">
                                                    <span>✕</span>
                                                    <span>Dilewati (Skip)</span>
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-6 text-center text-ink-400">Tidak ada file dalam filter ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($totalDisplayCount > 100)
                        <div class="p-2.5 rounded-xl bg-white/5 border border-white/10 text-[11px] text-ink-400 text-center">
                            Menampilkan 100 dari total {{ number_format($totalDisplayCount, 0, ',', '.') }} berkas foto pertama. Seluruh berkas akan diproses oleh antrian sistem di latar belakang.
                        </div>
                    @endif

                    <!-- Bottom Action Controls -->
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-black/5 dark:border-white/5">
                        <button type="button"
                                wire:click="resetFotoUpload"
                                class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 text-ink-400 hover:text-white font-bold text-xs transition cursor-pointer">
                            ← Batal &amp; Pilih Ulang
                        </button>

                        <div class="flex items-center gap-3 w-full sm:w-auto">
                            <button type="button"
                                    wire:click="startFotoProcessing"
                                    @if(($fotoScanResult['matched_count'] ?? 0) === 0) disabled @endif
                                    class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs transition flex items-center justify-center gap-2 cursor-pointer shadow-md shadow-up-primary/20 disabled:opacity-50 disabled:cursor-not-allowed">
                                <span>🚀 Mulai Proses Kompresi &amp; Simpan ({{ $fotoScanResult['matched_count'] ?? 0 }} Foto)</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        @elseif($fotoStep === 'processing' || $fotoStep === 'completed')
            <!-- Step 3 & 4: Live Processing Ticker & Completed Error Breakdown -->
            @php
                $log = $activeFotoImportLog;
                $isProses = ($log['status'] ?? '') === 'proses' || $fotoStep === 'processing';
                $isGagal = ($log['status'] ?? '') === 'gagal';
                $isSelesai = ($log['status'] ?? '') === 'selesai' || $fotoStep === 'completed';
                $totalItems = $log['total_baris'] ?? ($fotoScanResult['matched_count'] ?? ($fotoCompletedSummary['total'] ?? 0));
                $suksesCount = $log['sukses'] ?? ($fotoSuccessCount ?: ($fotoCompletedSummary['success'] ?? 0));
                $gagalCount = $log['gagal'] ?? ($fotoErrorCount ?: ($fotoCompletedSummary['error'] ?? 0));
                $selesaiCount = $suksesCount + $gagalCount;
                $persen = $log['persen'] ?? ($totalItems > 0 ? (int) min(100, round(($selesaiCount / $totalItems) * 100)) : 0);

                $detailList = $log['detail'] ?? [];
                $gagalItems = $log['gagal_rows'] ?? array_values(array_filter($detailList, fn ($d) => is_array($d) && ($d['status'] ?? '') === 'gagal'));
                $skipItems = $log['skip_rows'] ?? array_values(array_filter($detailList, fn ($d) => is_array($d) && ($d['status'] ?? '') === 'skip'));
                $bermasalahItems = match ($fotoErrorFilterTab) {
                    'gagal' => $gagalItems,
                    'skip' => $skipItems,
                    default => array_values(array_filter($detailList, fn ($d) => is_array($d) && (in_array($d['status'] ?? '', ['gagal', 'skip'], true) || ! empty($d['error'])))),
                };
                $totalBermasalah = count($gagalItems) + count($skipItems);
                if ($totalBermasalah === 0 && ! empty($fotoScanResult['skipped'])) {
                    $totalBermasalah = count($fotoScanResult['skipped']);
                }
            @endphp

            <div class="space-y-6 text-center">
                <!-- Status Icon -->
                <div class="flex items-center justify-center">
                    @if($isProses)
                        <div class="w-16 h-16 rounded-full bg-up-primary/10 border border-up-primary/20 flex items-center justify-center text-up-primary text-2xl animate-pulse">
                            <span class="animate-spin inline-block">⏳</span>
                        </div>
                    @elseif($isGagal)
                        <div class="w-16 h-16 rounded-full bg-up-red/10 border border-up-red/20 flex items-center justify-center text-up-red text-2xl">
                            ⚠️
                        </div>
                    @else
                        <div class="w-16 h-16 rounded-full bg-up-mint/10 border border-up-mint/20 flex items-center justify-center text-up-mint text-2xl shadow-lg shadow-up-mint/10">
                            ✓
                        </div>
                    @endif
                </div>

                <div class="space-y-1">
                    <h2 class="text-xl sm:text-2xl font-black text-ink-950 dark:text-white">
                        @if($isProses)
                            Import Foto Sedang Berjalan...
                        @elseif($isGagal)
                            Import Foto Mengalami Kendala
                        @else
                            Import Foto Produk Selesai!
                        @endif
                    </h2>
                    <p class="text-xs text-ink-400 max-w-lg mx-auto">
                        @if($isProses)
                            Sistem sedang mengompresi gambar ke WebP (1200px &amp; thumbnail 250px) dan menyinkronkan ke katalog produk. Halaman ini diperbarui otomatis secara real-time.
                        @elseif($isGagal)
                            Proses import foto terhenti karena kendala teknis. Silakan periksa detail rincian di bawah.
                        @else
                            Seluruh foto berhasil diproses, dikompresi ke WebP, dan disinkronkan ke katalog master produk WMS, POS Kasir, serta Marketplace.
                        @endif
                    </p>
                </div>

                <!-- Main Status & Metrics Card -->
                <div class="max-w-lg mx-auto p-5 rounded-2xl bg-black/40 border border-white/10 text-left text-xs space-y-4 shadow-xl">
                    <!-- Header Status Log -->
                    <div class="flex items-center justify-between font-mono pb-3 border-b border-white/10">
                        <span class="text-ink-400 font-bold">Import Foto Log #{{ $log['id'] ?? ($fotoImportLogId ?? '-') }}</span>
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
                            @if($totalItems > 0)
                                <span class="text-up-mint font-black">{{ $persen }}% ({{ $selesaiCount }} dari {{ $totalItems }} foto)</span>
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
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-1 text-center font-mono">
                        <div class="p-2 rounded-xl bg-white/5 border border-white/5">
                            <div class="text-[9px] uppercase text-ink-400">Total</div>
                            <div class="text-sm font-black text-white mt-0.5">{{ $totalItems }}</div>
                        </div>
                        <div class="p-2 rounded-xl bg-up-mint/10 border border-up-mint/20">
                            <div class="text-[9px] uppercase text-up-mint">Sukses</div>
                            <div class="text-sm font-black text-up-mint mt-0.5">{{ $suksesCount }}</div>
                        </div>
                        <div class="p-2 rounded-xl bg-up-amber/10 border border-up-amber/20">
                            <div class="text-[9px] uppercase text-up-amber">Dilewati</div>
                            <div class="text-sm font-black text-up-amber mt-0.5">{{ $log['skip'] ?? ($fotoCompletedSummary['skipped'] ?? 0) }}</div>
                        </div>
                        <div class="p-2 rounded-xl bg-up-red/10 border border-up-red/20">
                            <div class="text-[9px] uppercase text-up-red">Gagal</div>
                            <div class="text-sm font-black text-up-red mt-0.5">{{ $gagalCount }}</div>
                        </div>
                    </div>

                    <!-- Real-time Processing Item Ticker -->
                    @if($isProses && (! empty($log['current_sku']) || ! empty($log['current_nama'])))
                        <div class="p-3 rounded-xl bg-up-primary/10 border border-up-primary/20 space-y-1">
                            <div class="flex items-center justify-between text-[10px] text-ink-400">
                                <span class="flex items-center gap-1.5 font-bold text-up-primary">
                                    <span class="w-1.5 h-1.5 rounded-full bg-up-primary animate-ping"></span>
                                    Sedang Memproses Foto #{{ $log['current_baris'] ?? '-' }}...
                                </span>
                                <span class="font-mono text-ink-400">SKU: {{ $log['current_sku'] ?? '-' }}</span>
                            </div>
                            <div class="text-xs font-semibold text-white truncate" title="{{ $log['current_nama'] ?? '' }}">
                                {{ $log['current_nama'] ?? '-' }}
                            </div>
                        </div>
                    @endif

                    <div class="p-2.5 rounded-xl bg-white/5 border border-white/5 text-[10px] text-ink-400 text-center">
                        🛡️ <strong>Proteksi Memori Server:</strong> Dijalankan di queue background dengan WebP compression &amp; pembersihan RAM bertahap untuk menjaga stabilitas VPS.
                    </div>
                </div>

                <!-- Interaktif: Rincian Seluruh File Bermasalah (Gagal & Dilewati) -->
                @if($totalBermasalah > 0)
                    <div class="max-w-4xl mx-auto p-5 sm:p-6 rounded-2xl bg-black/40 border border-white/10 text-left space-y-4 shadow-xl">
                        <div class="flex items-center justify-between flex-wrap gap-3 pb-3 border-b border-white/10">
                            <div>
                                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                                    <span>📋</span>
                                    <span>Rincian Berkas Bermasalah ({{ $totalBermasalah }} Berkas)</span>
                                </h3>
                                <p class="text-[11px] text-ink-400 mt-0.5">Daftar foto yang mengalami kegagalan proses atau dilewati karena SKU tidak cocok.</p>
                            </div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <button type="button"
                                        wire:click="exportFotoErrorRows"
                                        class="px-3 py-2 rounded-xl text-xs font-bold bg-up-primary hover:bg-up-primary/80 text-white cursor-pointer transition shadow flex items-center gap-1.5">
                                    <span>📥</span> Unduh Laporan Error (.CSV)
                                </button>
                                @if(! empty($fotoScanResult['skipped']))
                                    <button type="button"
                                            wire:click="downloadFotoSkippedExcel"
                                            class="px-3 py-2 rounded-xl text-xs font-bold bg-white/10 hover:bg-white/20 text-white cursor-pointer transition shadow flex items-center gap-1.5">
                                        <span>📊</span> Unduh Excel Dilewati (.xlsx)
                                    </button>
                                @endif
                            </div>
                        </div>

                        <!-- Filter Tabs -->
                        <div class="flex items-center gap-2 border-b border-white/10 pb-2">
                            <button type="button"
                                    wire:click="setFotoErrorFilterTab('semua')"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold cursor-pointer transition {{ $fotoErrorFilterTab === 'semua' ? 'bg-white/15 text-white ring-1 ring-white/30' : 'text-ink-400 hover:text-white' }}">
                                Semua ({{ $totalBermasalah }})
                            </button>
                            <button type="button"
                                    wire:click="setFotoErrorFilterTab('gagal')"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold cursor-pointer transition {{ $fotoErrorFilterTab === 'gagal' ? 'bg-up-red/20 text-up-red ring-1 ring-up-red/40' : 'text-ink-400 hover:text-up-red' }}">
                                Gagal ({{ count($gagalItems) }})
                            </button>
                            <button type="button"
                                    wire:click="setFotoErrorFilterTab('skip')"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold cursor-pointer transition {{ $fotoErrorFilterTab === 'skip' ? 'bg-up-amber/20 text-up-amber ring-1 ring-up-amber/40' : 'text-ink-400 hover:text-up-amber' }}">
                                Dilewati / Skip ({{ count($skipItems) ?: (count($fotoScanResult['skipped'] ?? [])) }})
                            </button>
                        </div>

                        <!-- Table Bermasalah -->
                        <div class="overflow-x-auto rounded-xl border border-white/10 bg-black/30">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="text-ink-400 border-b border-white/10 bg-white/5 text-[10px] uppercase tracking-wider font-mono">
                                        <th class="py-2.5 px-3">No</th>
                                        <th class="py-2.5 px-3">Nama Berkas</th>
                                        <th class="py-2.5 px-3">SKU Terdeteksi</th>
                                        <th class="py-2.5 px-3">Nama Produk</th>
                                        <th class="py-2.5 px-3">Status</th>
                                        <th class="py-2.5 px-3">Keterangan / Alasan Error</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5 text-[11px]">
                                    @php
                                        $displayRows = ! empty($bermasalahItems)
                                            ? array_slice($bermasalahItems, 0, $fotoErrorDisplayLimit)
                                            : (! empty($fotoScanResult['skipped']) ? array_slice($fotoScanResult['skipped'], 0, $fotoErrorDisplayLimit) : []);
                                    @endphp
                                    @forelse($displayRows as $idx => $item)
                                        @php
                                            $isSkipped = ($item['status'] ?? '') === 'skip' || ! empty($item['reason']);
                                            $filename = $item['file'] ?? ($item['filename'] ?? ($item['name'] ?? '-'));
                                            $sku = $item['sku'] ?? '-';
                                            $nama = $item['nama'] ?? '-';
                                            $ket = $item['error'] ?? ($item['catatan'] ?? ($item['reason'] ?? 'Terjadi kendala pada berkas ini'));
                                        @endphp
                                        <tr class="hover:bg-white/[0.02] transition">
                                            <td class="py-2.5 px-3 font-mono font-bold text-ink-300">#{{ $item['baris'] ?? ($idx + 1) }}</td>
                                            <td class="py-2.5 px-3 font-mono text-white font-semibold truncate max-w-xs" title="{{ $filename }}">{{ $filename }}</td>
                                            <td class="py-2.5 px-3 font-mono text-up-primary font-bold">{{ $sku }}</td>
                                            <td class="py-2.5 px-3 text-ink-200 max-w-[200px] truncate" title="{{ $nama }}">{{ $nama }}</td>
                                            <td class="py-2.5 px-3">
                                                @if($isSkipped)
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-amber/20 text-up-amber border border-up-amber/30">
                                                        Dilewati
                                                    </span>
                                                @else
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-red/20 text-up-red border border-up-red/30">
                                                        Gagal
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="py-2.5 px-3">
                                                <div class="text-[11px] {{ ! $isSkipped ? 'text-up-red' : 'text-ink-300' }}">
                                                    {{ $ket }}
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="py-4 text-center text-ink-500 italic">
                                                Tidak ada berkas data pada kategori ini.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if($totalBermasalah > $fotoErrorDisplayLimit)
                            <div class="text-center pt-2">
                                <button type="button"
                                        wire:click="loadMoreFotoErrors"
                                        class="px-4 py-2 rounded-xl text-xs font-bold bg-white/5 hover:bg-white/10 text-ink-200 border border-white/10 cursor-pointer transition">
                                    Muat 50 Berkas Lainnya (Menampilkan {{ $fotoErrorDisplayLimit }} dari {{ $totalBermasalah }})
                                </button>
                            </div>
                        @endif
                    </div>
                @endif

                <!-- Bottom Action Controls -->
                <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-3">
                    <button type="button"
                            wire:click="resetFotoUpload"
                            class="w-full sm:w-auto min-h-[44px] px-6 py-2.5 rounded-xl bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 text-ink-300 font-bold text-xs cursor-pointer hover:bg-black/10 transition flex items-center justify-center">
                        Import Foto Lainnya
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
