<div class="space-y-6"
     @if($fotoStep === 'processing' || ($activeFotoImportLog && ($activeFotoImportLog['status'] ?? '') === 'proses')) wire:poll.1000ms="cekStatusFotoImport" @endif>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-ink-400">
                <a href="/app/wms" wire:navigate class="hover:text-up-primary transition flex items-center gap-1">
                    <span>📦 WMS</span>
                </a>
                <span>/</span>
                <span class="text-ink-600 dark:text-ink-300">Import Foto Produk</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-black text-ink-950 dark:text-white mt-1">Import Foto Produk Massal</h1>
            <p class="text-xs text-ink-500 dark:text-ink-400 mt-0.5">Unggah foto produk dalam jumlah besar. Sistem otomatis mencocokkan nama berkas dengan SKU master produk, mengonversi ke WebP, dan membuat thumbnail kasir.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="/app/wms" wire:navigate class="min-h-[40px] px-4 py-2 rounded-xl bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 hover:bg-black/10 dark:hover:bg-white/10 text-xs font-bold text-ink-700 dark:text-ink-200 transition flex items-center gap-1.5 cursor-pointer">
                <span>← Kembali ke WMS</span>
            </a>
        </div>
    </div>

    @if($fotoStep === 'upload')
        <!-- Step 1: Upload Form -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6"
             x-data="{ isUploading: false, uploadProgress: 0 }"
             x-on:livewire-upload-start="isUploading = true; uploadProgress = 0"
             x-on:livewire-upload-finish="isUploading = false"
             x-on:livewire-upload-error="isUploading = false"
             x-on:livewire-upload-progress="uploadProgress = $event.detail.progress">
            <!-- Left: Settings & Dropzone -->
            <div class="p-6 rounded-2xl bg-black/5 dark:bg-white/[0.02] border border-black/10 dark:border-white/5 space-y-5">
                <div class="flex items-center justify-between border-b border-black/5 dark:border-white/5 pb-3">
                    <h3 class="text-sm font-bold text-ink-900 dark:text-white flex items-center gap-2">
                        <span>⚙️</span>
                        <span>Pengaturan &amp; Unggah Berkas</span>
                    </h3>
                    <span class="text-[10px] font-mono text-up-mint font-semibold">WebP Auto-Compress (1200px &amp; 250px)</span>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-[11px] font-bold text-ink-700 dark:text-ink-300 mb-1.5">Sumber Foto:</label>
                        <div class="flex items-center gap-3 flex-wrap">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" wire:model.live="fotoSourceType" value="files" class="radio radio-primary">
                                <span class="text-xs font-medium text-ink-700 dark:text-ink-300">File Foto (1 atau Banyak)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" wire:model.live="fotoSourceType" value="folder" class="radio radio-primary">
                                <span class="text-xs font-medium text-ink-700 dark:text-ink-300">Folder Sekaligus</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" wire:model.live="fotoSourceType" value="zip" class="radio radio-primary">
                                <span class="text-xs font-medium text-ink-700 dark:text-ink-300">Berkas Arsip (.ZIP)</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-ink-700 dark:text-ink-300 mb-1.5">Mode Simpan Foto:</label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <label class="relative p-3 rounded-xl border-2 cursor-pointer transition {{ $fotoMode === 'append' ? 'border-up-primary bg-up-primary/10 dark:bg-up-primary/15' : 'border-black/10 dark:border-white/10 hover:border-up-primary' }}">
                                <input type="radio" wire:model.live="fotoMode" value="append" class="sr-only">
                                <div class="text-left space-y-0.5">
                                    <div class="font-bold text-xs text-ink-900 dark:text-white flex items-center justify-between">
                                        <span>Tambah (Append)</span>
                                        @if($fotoMode === 'append')<span class="text-[9px] text-up-primary font-black">✓</span>@endif
                                    </div>
                                    <div class="text-[10px] text-ink-500 dark:text-ink-400">Tambahkan ke galeri foto produk</div>
                                </div>
                            </label>
                            <label class="relative p-3 rounded-xl border-2 cursor-pointer transition {{ $fotoMode === 'replace' ? 'border-up-primary bg-up-primary/10 dark:bg-up-primary/15' : 'border-black/10 dark:border-white/10 hover:border-up-primary' }}">
                                <input type="radio" wire:model.live="fotoMode" value="replace" class="sr-only">
                                <div class="text-left space-y-0.5">
                                    <div class="font-bold text-xs text-ink-900 dark:text-white flex items-center justify-between">
                                        <span>Ganti (Replace)</span>
                                        @if($fotoMode === 'replace')<span class="text-[9px] text-up-primary font-black">✓</span>@endif
                                    </div>
                                    <div class="text-[10px] text-ink-500 dark:text-ink-400">Timpa seluruh foto lama produk</div>
                                </div>
                            </label>
                            <label class="relative p-3 rounded-xl border-2 cursor-pointer transition {{ $fotoMode === 'skip_existing' ? 'border-up-primary bg-up-primary/10 dark:bg-up-primary/15' : 'border-black/10 dark:border-white/10 hover:border-up-primary' }}">
                                <input type="radio" wire:model.live="fotoMode" value="skip_existing" class="sr-only">
                                <div class="text-left space-y-0.5">
                                    <div class="font-bold text-xs text-ink-900 dark:text-white flex items-center justify-between">
                                        <span>Lewati (Skip)</span>
                                        @if($fotoMode === 'skip_existing')<span class="text-[9px] text-up-primary font-black">✓</span>@endif
                                    </div>
                                    <div class="text-[10px] text-ink-500 dark:text-ink-400">Lewati jika produk sudah punya foto</div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Dropzone Area -->
                <div class="relative overflow-hidden p-6 rounded-2xl border-2 border-dashed {{ $fotoUploads || $fotoZip ? 'border-up-mint bg-up-mint/[0.03]' : 'border-black/15 dark:border-white/15 bg-black/5 dark:bg-white/[0.01]' }} hover:border-up-primary transition text-center space-y-2 cursor-pointer group">
                    @if($fotoSourceType === 'files')
                        <input type="file"
                               wire:model="fotoUploads"
                               accept=".jpg,.jpeg,.png,.webp,.gif,.bmp,image/*"
                               multiple
                               class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                    @elseif($fotoSourceType === 'folder')
                        <input type="file"
                               wire:model="fotoUploads"
                               accept=".jpg,.jpeg,.png,.webp,.gif,.bmp,image/*"
                               multiple
                               webkitdirectory
                               directory
                               class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                    @else
                        <input type="file"
                               wire:model="fotoZip"
                               accept=".zip,application/zip"
                               class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                    @endif

                    @if(in_array($fotoSourceType, ['files', 'folder']) && $fotoUploads)
                        <div class="text-3xl text-up-mint font-black">✓</div>
                        <div class="font-bold text-xs text-ink-900 dark:text-white">{{ count($fotoUploads) }} file dipilih</div>
                        <p class="text-[11px] text-ink-500 dark:text-ink-400">Siap dievaluasi kecocokannya dengan SKU master produk.</p>
                    @elseif($fotoSourceType === 'zip' && $fotoZip)
                        <div class="text-3xl text-up-mint font-black">✓</div>
                        <div class="font-bold text-xs text-ink-900 dark:text-white">{{ $fotoZip->getClientOriginalName() }}</div>
                        <div class="text-[11px] text-ink-500 dark:text-ink-400">{{ number_format($fotoZip->getSize() / 1024 / 1024, 1) }} MB</div>
                    @elseif($fotoSourceType === 'files')
                        <div class="text-3xl group-hover:scale-110 transition-transform">🖼️</div>
                        <div class="font-bold text-xs text-ink-900 dark:text-white">Pilih File Foto (Bebas 1 atau Banyak)</div>
                        <p class="text-[11px] text-ink-500 dark:text-ink-400">Pilih 1 foto atau blok banyak foto sekaligus. Nama file foto otomatis dicocokkan ke SKU produk.</p>
                    @elseif($fotoSourceType === 'folder')
                        <div class="text-3xl group-hover:scale-110 transition-transform">📂</div>
                        <div class="font-bold text-xs text-ink-900 dark:text-white">Pilih Seluruh Folder Foto</div>
                        <p class="text-[11px] text-ink-500 dark:text-ink-400">Pilih satu folder berisi kumpulan foto produk dari PC / File Manager.</p>
                    @else
                        <div class="text-3xl group-hover:scale-110 transition-transform">🗜️</div>
                        <div class="font-bold text-xs text-ink-900 dark:text-white">Pilih Berkas Arsip (.ZIP)</div>
                        <p class="text-[11px] text-ink-500 dark:text-ink-400">Sistem mengekstrak isi arsip .ZIP dan menyaring berkas foto yang cocok dengan SKU.</p>
                    @endif
                </div>

                <!-- Livewire Upload Progress Bar -->
                <div x-show="isUploading" class="space-y-1.5 p-3 rounded-xl bg-up-primary/10 border border-up-primary/20">
                    <div class="flex items-center justify-between text-xs font-mono">
                        <span class="text-up-primary font-bold flex items-center gap-1.5">
                            <span class="animate-spin inline-block">⏳</span>
                            <span>Mengunggah berkas ke server...</span>
                        </span>
                        <span class="font-black text-up-primary" x-text="`${uploadProgress}%`"></span>
                    </div>
                    <div class="w-full h-2 rounded-full bg-black/20 dark:bg-white/10 overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-up-primary to-up-mint rounded-full transition-all duration-200"
                             :style="`width: ${uploadProgress}%`"></div>
                    </div>
                </div>

                @error('fotoUploads')
                    <div class="p-3 rounded-xl bg-up-red/10 border border-up-red/30 text-xs text-up-red font-semibold flex items-start gap-2">
                        <span>⚠️</span>
                        <span>{{ $message }}</span>
                    </div>
                @enderror
                @error('fotoZip')
                    <div class="p-3 rounded-xl bg-up-red/10 border border-up-red/30 text-xs text-up-red font-semibold flex items-start gap-2">
                        <span>⚠️</span>
                        <span>{{ $message }}</span>
                    </div>
                @enderror

                <div class="flex items-center gap-2">
                    <button type="button"
                            wire:click="autoImportFoto"
                            wire:loading.attr="disabled"
                            class="flex-1 py-3 px-4 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs transition flex items-center justify-center gap-2 cursor-pointer shadow-md shadow-up-primary/20 disabled:opacity-50">
                        <span wire:loading.remove wire:target="autoImportFoto">⚡ Auto Import Langsung</span>
                        <span wire:loading wire:target="autoImportFoto" class="flex items-center gap-2">
                            <span class="animate-spin">⏳</span>
                            <span>Mengevaluasi &amp; Memproses...</span>
                        </span>
                    </button>
                    <button type="button"
                            wire:click="scanUploadedFoto"
                            wire:loading.attr="disabled"
                            class="py-3 px-4 rounded-xl bg-black/5 dark:bg-white/10 hover:bg-black/10 dark:hover:bg-white/15 text-ink-700 dark:text-ink-200 font-bold text-xs transition flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50"
                            title="Preview hasil pencocokan SKU sebelum simpan">
                        <span wire:loading.remove wire:target="scanUploadedFoto">Preview &amp; Filter →</span>
                        <span wire:loading wire:target="scanUploadedFoto" class="flex items-center gap-1">
                            <span class="animate-spin">⏳</span>
                        </span>
                    </button>
                </div>
            </div>

            <!-- Right: Rules & Info -->
            <div class="p-6 rounded-2xl bg-black/5 dark:bg-white/[0.02] border border-black/10 dark:border-white/5 space-y-4">
                <h3 class="text-sm font-bold text-ink-900 dark:text-white flex items-center gap-2">
                    <span>💡</span>
                    <span>Aturan Penamaan &amp; Alur Kompresi</span>
                </h3>

                <div class="space-y-3 text-xs text-ink-600 dark:text-ink-400 leading-relaxed">
                    <div class="p-3.5 rounded-xl bg-white/5 border border-white/5 space-y-2">
                        <div class="font-bold text-ink-900 dark:text-white flex items-center gap-1.5">
                            <span>🎯</span>
                            <span>Format Nama File yang Dikenali Otomatis:</span>
                        </div>
                        <ul class="space-y-1 font-mono text-[11px] text-ink-800 dark:text-ink-200">
                            <li>• <strong class="text-up-primary">[SKU].jpg</strong> (Otomatis Foto Utama / Cover)</li>
                            <li>• <strong class="text-up-primary">[SKU]_1.png</strong>, <strong class="text-up-primary">[SKU]_2.png</strong> (Galeri Foto)</li>
                            <li>• <strong class="text-up-primary">[SKU]-1.jpeg</strong>, <strong class="text-up-primary">[SKU]-main.jpg</strong></li>
                            <li>• <strong class="text-up-primary">[SKU] (1).webp</strong>, <strong class="text-up-primary">[SKU]/depan.jpg</strong></li>
                        </ul>
                    </div>

                    <div class="p-3.5 rounded-xl bg-up-mint/5 border border-up-mint/20 space-y-1">
                        <div class="font-bold text-up-mint flex items-center gap-1.5">
                            <span>⚡</span>
                            <span>Kompresi WebP &amp; Dual Thumbnail:</span>
                        </div>
                        <p class="text-[11px]">Setiap gambar diproses otomatis menjadi WebP resolusi penuh (maks. 1200x1200px) dan thumbnail mikro (250x250px) untuk kecepatan kasir POS &amp; katalog online.</p>
                    </div>

                    <div class="p-3.5 rounded-xl bg-up-amber/5 border border-up-amber/20 space-y-1">
                        <div class="font-bold text-up-amber flex items-center gap-1.5">
                            <span>🛡️</span>
                            <span>Keamanan &amp; Laporan File Dilewati:</span>
                        </div>
                        <p class="text-[11px]">Berkas dengan nama yang tidak cocok SKU tidak akan merusak database; berkas dilewati aman dan dapat diunduh laporannya dalam format Excel/CSV untuk perbaikan nama.</p>
                    </div>
                </div>
            </div>
        </div>

    @elseif($fotoStep === 'preview')
        <!-- Step 2: Preview & Verification -->
        <div class="space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="p-4 rounded-2xl bg-black/5 dark:bg-white/[0.02] border border-black/10 dark:border-white/5 space-y-1">
                    <div class="text-[10px] uppercase font-bold text-ink-400">Total Berkas Dipindai</div>
                    <div class="text-2xl font-black text-ink-950 dark:text-white">{{ $fotoScanResult['total'] ?? 0 }} File</div>
                </div>
                <div class="p-4 rounded-2xl bg-up-mint/10 border border-up-mint/30 space-y-1">
                    <div class="text-[10px] uppercase font-bold text-up-mint">Cocok SKU Master (Siap Proses)</div>
                    <div class="text-2xl font-black text-up-mint">{{ $fotoScanResult['matched_count'] ?? 0 }} Foto</div>
                </div>
                <div class="p-4 rounded-2xl bg-up-red/10 border border-up-red/30 space-y-1">
                    <div class="text-[10px] uppercase font-bold text-up-red">SKU Tidak Ditemukan (Dilewati)</div>
                    <div class="text-2xl font-black text-up-red">{{ $fotoScanResult['skipped_count'] ?? 0 }} File</div>
                </div>
            </div>

            @if(($fotoScanResult['skipped_count'] ?? 0) > 0)
                <div class="p-4 rounded-2xl bg-up-amber/10 border border-up-amber/30 space-y-3">
                    <div class="flex items-center justify-between flex-wrap gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">⚠️</span>
                            <div>
                                <h3 class="text-sm font-bold text-up-amber dark:text-up-amber">Terdeteksi {{ $fotoScanResult['skipped_count'] ?? 0 }} Berkas Foto Tanpa SKU Terdaftar</h3>
                                <p class="text-[11px] text-ink-500 dark:text-ink-400">Berkas ini akan otomatis dilewati saat proses penyimpanan agar tidak membebani server.</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <button type="button" wire:click="exportFotoErrorRows" class="px-3 py-1.5 rounded-lg text-xs font-bold bg-white/10 hover:bg-white/20 text-white cursor-pointer transition shadow flex items-center gap-1.5">
                                <span>📥</span> Unduh Laporan File Dilewati (.CSV)
                            </button>
                            <button type="button" wire:click="abaikanSemuaFotoError" class="px-3 py-1.5 rounded-lg text-xs font-bold bg-up-amber/80 hover:bg-up-amber text-white cursor-pointer transition shadow flex items-center gap-1.5">
                                <span>✓</span> Abaikan &amp; Lanjutkan Proses
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Filter Bar & Table of Scanned Files -->
            <div class="p-5 rounded-2xl bg-black/5 dark:bg-white/[0.02] border border-black/10 dark:border-white/5 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-black/5 dark:border-white/5 pb-3">
                    <div class="flex items-center gap-2 flex-wrap">
                        <button type="button"
                                wire:click="setFotoFilterTab('all')"
                                class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $fotoFilterTab === 'all' ? 'bg-up-primary text-white shadow' : 'text-ink-400 hover:text-white bg-black/5 dark:bg-white/5' }}">
                            Semua Berkas ({{ $fotoScanResult['total'] ?? 0 }})
                        </button>
                        <button type="button"
                                wire:click="setFotoFilterTab('matched')"
                                class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $fotoFilterTab === 'matched' ? 'bg-up-mint text-ink-950 shadow' : 'text-ink-400 hover:text-white bg-black/5 dark:bg-white/5' }}">
                            Cocok SKU ({{ $fotoScanResult['matched_count'] ?? 0 }})
                        </button>
                        <button type="button"
                                wire:click="setFotoFilterTab('skipped')"
                                class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $fotoFilterTab === 'skipped' ? 'bg-up-red text-white shadow' : 'text-ink-400 hover:text-white bg-black/5 dark:bg-white/5' }}">
                            Dilewati ({{ $fotoScanResult['skipped_count'] ?? 0 }})
                        </button>
                    </div>

                    @if(! empty($fotoScanResult['skipped']))
                        <button type="button"
                                wire:click="downloadFotoSkippedExcel"
                                class="text-xs font-bold text-up-primary hover:underline flex items-center gap-1 cursor-pointer">
                            <span>📊</span>
                            <span>Unduh Laporan File Dilewati (.xlsx)</span>
                        </button>
                    @endif
                </div>

                <!-- Table Preview -->
                <div class="overflow-x-auto rounded-xl border border-black/10 dark:border-white/5">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-black/10 dark:border-white/10 text-ink-400 text-[10px] uppercase font-bold bg-black/5 dark:bg-white/5">
                                <th class="py-2.5 px-3">No</th>
                                <th class="py-2.5 px-3">Nama Berkas Asli</th>
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
                                    <td class="py-2 px-3 font-semibold text-ink-900 dark:text-white truncate max-w-xs" title="{{ $row['filename'] ?? ($row['name'] ?? '-') }}">
                                        {{ $row['filename'] ?? ($row['name'] ?? '-') }}
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
                                            <span class="text-ink-800 dark:text-ink-200">{{ $row['produk_nama'] ?? '-' }}</span>
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
                                    <td colspan="6" class="py-6 text-center text-ink-400">Tidak ada berkas dalam filter ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($totalDisplayCount > 100)
                    <div class="p-2.5 rounded-xl bg-white/5 border border-white/10 text-[11px] text-ink-400 text-center font-mono">
                        Menampilkan 100 dari total {{ number_format($totalDisplayCount, 0, ',', '.') }} berkas foto pertama. Seluruh berkas akan diproses oleh antrian sistem.
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
        <!-- Step 3 & 4: Live Processing & Completed Report -->
        @php
            $log = $activeFotoImportLog;
            $isProses = ($log['status'] ?? '') === 'proses' || $fotoStep === 'processing';
            $isGagal = ($log['status'] ?? '') === 'gagal';
            $isSelesai = ($log['status'] ?? '') === 'selesai' || $fotoStep === 'completed';

            $totalItems = (int) ($log['total_baris'] ?? ($fotoScanResult['matched_count'] ?? ($fotoCompletedSummary['total'] ?? 0)));
            $suksesCount = (int) ($log['sukses'] ?? ($fotoSuccessCount ?: ($fotoCompletedSummary['success'] ?? 0)));
            $gagalCount = (int) ($log['gagal'] ?? ($fotoErrorCount ?: ($fotoCompletedSummary['error'] ?? 0)));
            $skipCount = (int) ($log['skip'] ?? 0);
            $selesaiCount = (int) ($log['selesai'] ?? ($suksesCount + $gagalCount + $skipCount));
            $persen = (int) ($log['persen'] ?? ($totalItems > 0 ? min(100, round(($selesaiCount / $totalItems) * 100)) : 0));

            $detailList = $log['detail'] ?? [];
            $gagalItems = $log['gagal_rows'] ?? array_values(array_filter($detailList, fn ($d) => is_array($d) && ($d['status'] ?? '') === 'gagal'));
            $skipItems = $log['skip_rows'] ?? array_values(array_filter($detailList, fn ($d) => is_array($d) && ($d['status'] ?? '') === 'skip'));
            $okItems = $log['ok_rows'] ?? array_values(array_filter($detailList, fn ($d) => is_array($d) && in_array($d['status'] ?? '', ['ok', 'success'], true)));

            // Saring berdasarkan filter tab
            $filteredItems = match ($fotoErrorFilterTab) {
                'sukses' => $okItems,
                'gagal' => $gagalItems,
                'skip' => $skipItems,
                default => ! empty($detailList) ? $detailList : array_merge($okItems, $skipItems, $gagalItems),
            };

            // Saring berdasarkan input pencarian jika ada
            if ($fotoResultSearch !== '') {
                $searchQuery = strtolower(trim($fotoResultSearch));
                $filteredItems = array_values(array_filter($filteredItems, function ($item) use ($searchQuery) {
                    $sku = strtolower((string) ($item['sku'] ?? ''));
                    $file = strtolower((string) ($item['file'] ?? ($item['filename'] ?? '')));
                    $nama = strtolower((string) ($item['nama'] ?? ($item['produk_nama'] ?? '')));
                    return str_contains($sku, $searchQuery) || str_contains($file, $searchQuery) || str_contains($nama, $searchQuery);
                }));
            }

            $totalFilteredCount = count($filteredItems);
            $recentFeed = $log['recent'] ?? ($fotoRecentStream ?? []);
        @endphp

        <div class="space-y-6">
            <!-- Processing Status / Summary Card -->
            <div class="p-6 sm:p-8 rounded-3xl bg-black/5 dark:bg-white/[0.02] border border-black/10 dark:border-white/10 text-center space-y-5 shadow-2xl relative overflow-hidden">
                <!-- Glowing ambient background light -->
                <div class="absolute -top-24 left-1/2 -translate-x-1/2 w-96 h-96 {{ $isProses ? 'bg-up-primary/10' : ($isGagal ? 'bg-up-red/10' : 'bg-up-mint/10') }} rounded-full blur-3xl pointer-events-none"></div>

                <div class="flex items-center justify-center relative">
                    @if($isProses)
                        <div class="w-16 h-16 rounded-2xl bg-up-primary/15 border border-up-primary/30 flex items-center justify-center text-up-primary text-2xl animate-pulse shadow-lg shadow-up-primary/20">
                            <span class="animate-spin inline-block">⏳</span>
                        </div>
                    @elseif($isGagal)
                        <div class="w-16 h-16 rounded-2xl bg-up-red/15 border border-up-red/30 flex items-center justify-center text-up-red text-3xl shadow-lg shadow-up-red/20">
                            ⚠️
                        </div>
                    @else
                        <div class="w-16 h-16 rounded-2xl bg-up-mint/15 border border-up-mint/30 flex items-center justify-center text-up-mint text-3xl shadow-lg shadow-up-mint/20">
                            ✓
                        </div>
                    @endif
                </div>

                <div class="space-y-1 relative">
                    <h2 class="text-xl sm:text-2xl font-black text-ink-950 dark:text-white">
                        @if($isProses)
                            Import &amp; Kompresi Foto Sedang Berjalan...
                        @elseif($isGagal)
                            Import Foto Mengalami Kendala
                        @else
                            Import Foto Produk Berhasil Selesai!
                        @endif
                    </h2>
                    <p class="text-xs text-ink-500 dark:text-ink-400 max-w-xl mx-auto">
                        @if($isProses)
                            Sistem mengompresi gambar ke WebP (1200px &amp; 250px) dan menyinkronkan ke katalog master WMS, Kasir POS, dan Marketplace. Halaman diperbarui secara real-time.
                        @elseif($isGagal)
                            Proses import terhenti karena kendala teknis. Silakan periksa rincian di bawah atau unduh log error.
                        @else
                            Seluruh foto telah selesai diproses, dikompresi ke format modern WebP hemat kuota, dan otomatis disinkronkan ke seluruh kanal penjualan.
                        @endif
                    </p>
                </div>

                <!-- Central Progress Bar Container -->
                <div class="max-w-xl mx-auto p-5 rounded-2xl bg-white/80 dark:bg-black/50 border border-black/10 dark:border-white/10 text-left text-xs space-y-4 shadow-xl backdrop-blur">
                    <div class="flex items-center justify-between font-mono pb-2.5 border-b border-black/5 dark:border-white/10">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-ink-900 dark:text-white">Log Import Foto #{{ $log['id'] ?? ($fotoImportLogId ?? '-') }}</span>
                            <span class="text-[10px] text-ink-400">({{ $log['created_at'] ?? date('d/m/Y H:i') }})</span>
                        </div>
                        @if($isProses)
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-up-primary/20 text-up-primary border border-up-primary/30 flex items-center gap-1.5 animate-pulse">
                                <span class="w-1.5 h-1.5 rounded-full bg-up-primary animate-ping"></span>
                                <span>Sedang Berjalan</span>
                            </span>
                        @elseif($isGagal)
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-up-red/20 text-up-red border border-up-red/30">
                                Gagal
                            </span>
                        @else
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-up-mint/20 text-up-mint border border-up-mint/30 flex items-center gap-1">
                                <span>✓</span>
                                <span>100% Selesai</span>
                            </span>
                        @endif
                    </div>

                    <!-- Progress Bar & Percentage Indicator -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-[11px] font-mono">
                            <span class="text-ink-600 dark:text-ink-400 font-semibold">Progres Pengerjaan:</span>
                            <span class="font-black {{ $isProses ? 'text-up-primary' : 'text-up-mint' }}">
                                {{ $persen }}% ({{ $selesaiCount }} dari {{ $totalItems }} foto)
                            </span>
                        </div>
                        <div class="w-full h-3.5 rounded-full bg-black/10 dark:bg-black/60 overflow-hidden p-0.5 border border-black/10 dark:border-white/10">
                            <div class="h-full rounded-full bg-gradient-to-r from-up-primary via-up-primary to-up-mint transition-all duration-300 {{ $isProses ? 'animate-pulse' : '' }}"
                                 style="width: {{ $persen }}%"></div>
                        </div>
                    </div>

                    <!-- 4 Metric Cards Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-1 font-mono text-center">
                        <div class="p-2.5 rounded-xl bg-black/5 dark:bg-white/5 border border-black/5 dark:border-white/5">
                            <div class="text-[9px] uppercase font-bold text-ink-400">Total Berkas</div>
                            <div class="text-base font-black text-ink-950 dark:text-white mt-0.5">{{ $totalItems }}</div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-up-mint/10 border border-up-mint/30">
                            <div class="text-[9px] uppercase font-bold text-up-mint">Berhasil (WebP)</div>
                            <div class="text-base font-black text-up-mint mt-0.5">{{ $suksesCount }}</div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-up-amber/10 border border-up-amber/30">
                            <div class="text-[9px] uppercase font-bold text-up-amber">Dilewati (Skip)</div>
                            <div class="text-base font-black text-up-amber mt-0.5">{{ $skipCount }}</div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-up-red/10 border border-up-red/30">
                            <div class="text-[9px] uppercase font-bold text-up-red">Gagal (Error)</div>
                            <div class="text-base font-black text-up-red mt-0.5">{{ $gagalCount }}</div>
                        </div>
                    </div>

                    <!-- Live Processing Item Ticker -->
                    @if($isProses && (! empty($log['current_sku']) || ! empty($log['current_nama'])))
                        <div class="p-3 rounded-xl bg-up-primary/10 border border-up-primary/25 space-y-1">
                            <div class="flex items-center justify-between text-[10px] text-ink-500 dark:text-ink-400">
                                <span class="flex items-center gap-1.5 font-bold text-up-primary">
                                    <span class="w-1.5 h-1.5 rounded-full bg-up-primary animate-ping"></span>
                                    Sedang Memproses Foto #{{ $log['current_baris'] ?? '-' }}...
                                </span>
                                <span class="font-mono font-bold text-up-primary">SKU: {{ $log['current_sku'] ?? '-' }}</span>
                            </div>
                            <div class="text-xs font-semibold text-ink-900 dark:text-white truncate" title="{{ $log['current_nama'] ?? '' }}">
                                {{ $log['current_nama'] ?? '-' }}
                            </div>
                        </div>
                    @endif

                    <!-- Rolling Recent Activity Feed (Live Stream) -->
                    @if(! empty($recentFeed))
                        <div class="space-y-1.5 pt-1">
                            <div class="text-[10px] font-bold text-ink-400 uppercase tracking-wider font-mono flex items-center justify-between">
                                <span>Aktivitas Terakhir:</span>
                                <span>Live Stream</span>
                            </div>
                            <div class="space-y-1 max-h-36 overflow-y-auto font-mono text-[11px] divide-y divide-black/5 dark:divide-white/5 bg-black/5 dark:bg-black/30 rounded-xl p-2 border border-black/5 dark:border-white/5">
                                @foreach($recentFeed as $feed)
                                    <div class="py-1 flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-1.5 truncate">
                                            @if(($feed['status'] ?? '') === 'ok')
                                                <span class="text-up-mint font-bold">✓</span>
                                            @elseif(($feed['status'] ?? '') === 'skip')
                                                <span class="text-up-amber font-bold">⊘</span>
                                            @else
                                                <span class="text-up-red font-bold">✕</span>
                                            @endif
                                            <span class="font-bold text-up-primary">{{ $feed['sku'] ?? '-' }}</span>
                                            <span class="text-ink-400 truncate max-w-xs">{{ $feed['file'] ?? ($feed['nama'] ?? '') }}</span>
                                        </div>
                                        <span class="text-[10px] text-ink-400 whitespace-nowrap">{{ $feed['time'] ?? '' }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Action Button Toolbar -->
                <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
                    <button type="button"
                            wire:click="exportFotoLengkap"
                            class="min-h-[42px] px-5 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs transition flex items-center gap-1.5 cursor-pointer shadow-md shadow-up-primary/20">
                        <span>📥 Unduh Laporan Lengkap (.CSV)</span>
                    </button>

                    @if($gagalCount > 0 || $skipCount > 0 || ! empty($fotoScanResult['skipped']))
                        <button type="button"
                                wire:click="exportFotoErrorRows"
                                class="min-h-[42px] px-4 py-2.5 rounded-xl bg-up-red/15 hover:bg-up-red/25 border border-up-red/30 text-up-red font-bold text-xs transition flex items-center gap-1.5 cursor-pointer">
                            <span>⚠️ Unduh Laporan Masalah / Error (.CSV)</span>
                        </button>
                    @endif

                    @if(! empty($fotoScanResult['skipped']))
                        <button type="button"
                                wire:click="downloadFotoSkippedExcel"
                                class="min-h-[42px] px-4 py-2.5 rounded-xl bg-black/5 dark:bg-white/10 hover:bg-black/10 dark:hover:bg-white/15 text-ink-700 dark:text-ink-200 border border-black/10 dark:border-white/10 font-bold text-xs transition flex items-center gap-1.5 cursor-pointer">
                            <span>📊 Unduh Excel Dilewati (.XLSX)</span>
                        </button>
                    @endif

                    <button type="button"
                            wire:click="resetFotoUpload"
                            class="min-h-[42px] px-4 py-2.5 rounded-xl bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 text-ink-700 dark:text-ink-300 font-bold text-xs cursor-pointer hover:bg-black/10 transition flex items-center gap-1.5">
                        <span>🔄 Import Foto Lainnya</span>
                    </button>

                    <a href="/app/wms" wire:navigate
                       class="min-h-[42px] px-5 py-2.5 rounded-xl bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 hover:bg-black/10 text-ink-700 dark:text-ink-200 font-bold text-xs transition flex items-center gap-1.5">
                        <span>📦 Master Produk WMS →</span>
                    </a>
                </div>
            </div>

            <!-- Detailed Interactive Report Explorer (Completed & Processed) -->
            <div class="p-6 rounded-3xl bg-black/5 dark:bg-white/[0.02] border border-black/10 dark:border-white/5 space-y-4">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-black/5 dark:border-white/5 pb-4">
                    <div>
                        <h3 class="text-sm font-bold text-ink-950 dark:text-white flex items-center gap-2">
                            <span>📋</span>
                            <span>Rincian Hasil Pengerjaan Berkas ({{ $totalFilteredCount }} Data)</span>
                        </h3>
                        <p class="text-xs text-ink-500 dark:text-ink-400 mt-0.5">Filter, cari SKU, dan periksa status pemetaan masing-masing berkas foto.</p>
                    </div>

                    <!-- Search Input -->
                    <div class="w-full md:w-72">
                        <div class="relative">
                            <input type="text"
                                   wire:model.live.debounce.300ms="fotoResultSearch"
                                   placeholder="Cari SKU, berkas, produk..."
                                   class="w-full px-3.5 py-2 pl-9 rounded-xl bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 text-xs text-ink-900 dark:text-white placeholder:text-ink-400 focus:outline-none focus:ring-2 focus:ring-up-primary/40">
                            <span class="absolute left-3 top-2.5 text-xs text-ink-400">🔍</span>
                            @if($fotoResultSearch !== '')
                                <button type="button" wire:click="$set('fotoResultSearch', '')" class="absolute right-3 top-2 text-xs text-ink-400 hover:text-white">✕</button>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Tab Filters for Report Table -->
                <div class="flex items-center gap-2 flex-wrap pb-1">
                    <button type="button"
                            wire:click="setFotoErrorFilterTab('semua')"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer {{ $fotoErrorFilterTab === 'semua' ? 'bg-up-primary text-white shadow' : 'bg-black/5 dark:bg-white/5 text-ink-400 hover:text-white' }}">
                        Semua Hasil
                    </button>
                    <button type="button"
                            wire:click="setFotoErrorFilterTab('sukses')"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer {{ $fotoErrorFilterTab === 'sukses' ? 'bg-up-mint text-ink-950 shadow' : 'bg-black/5 dark:bg-white/5 text-ink-400 hover:text-up-mint' }}">
                        Berhasil Disimpan ({{ $suksesCount }})
                    </button>
                    <button type="button"
                            wire:click="setFotoErrorFilterTab('skip')"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer {{ $fotoErrorFilterTab === 'skip' ? 'bg-up-amber text-white shadow' : 'bg-black/5 dark:bg-white/5 text-ink-400 hover:text-up-amber' }}">
                        Dilewati ({{ $skipCount }})
                    </button>
                    <button type="button"
                            wire:click="setFotoErrorFilterTab('gagal')"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer {{ $fotoErrorFilterTab === 'gagal' ? 'bg-up-red text-white shadow' : 'bg-black/5 dark:bg-white/5 text-ink-400 hover:text-up-red' }}">
                        Gagal ({{ $gagalCount }})
                    </button>
                </div>

                <!-- Results Data Table -->
                <div class="overflow-x-auto rounded-2xl border border-black/10 dark:border-white/5 bg-white/40 dark:bg-black/20">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-black/10 dark:border-white/10 text-ink-400 text-[10px] uppercase font-bold bg-black/5 dark:bg-white/5 font-mono">
                                <th class="py-2.5 px-3">No</th>
                                <th class="py-2.5 px-3">Nama Berkas</th>
                                <th class="py-2.5 px-3">SKU Terdeteksi</th>
                                <th class="py-2.5 px-3">Produk Sistem</th>
                                <th class="py-2.5 px-3">Status</th>
                                <th class="py-2.5 px-3">Keterangan / Alasan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-black/5 dark:divide-white/5 font-mono text-[11px]">
                            @forelse(array_slice($filteredItems, 0, $fotoErrorDisplayLimit) as $idx => $row)
                                @php
                                    $st = $row['status'] ?? '';
                                    $isOk = in_array($st, ['ok', 'success'], true);
                                    $isSkip = $st === 'skip';
                                    $fileName = $row['file'] ?? ($row['filename'] ?? ($row['name'] ?? '-'));
                                    $sku = $row['sku'] ?? '-';
                                    $prodName = $row['nama'] ?? ($row['produk_nama'] ?? '-');
                                    $keterangan = $row['catatan'] ?? ($row['error'] ?? ($row['reason'] ?? '-'));
                                @endphp
                                <tr class="hover:bg-black/5 dark:hover:bg-white/[0.02] transition">
                                    <td class="py-2 px-3 text-ink-400">#{{ $row['baris'] ?? ($idx + 1) }}</td>
                                    <td class="py-2 px-3 font-semibold text-ink-900 dark:text-white truncate max-w-xs" title="{{ $fileName }}">
                                        {{ $fileName }}
                                    </td>
                                    <td class="py-2 px-3">
                                        @if(! empty($sku) && $sku !== '-')
                                            <span class="px-1.5 py-0.5 rounded bg-black/10 dark:bg-white/10 font-bold text-up-primary">
                                                {{ $sku }}
                                            </span>
                                        @else
                                            <span class="text-ink-400 italic">(tidak cocok)</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3 font-sans text-ink-800 dark:text-ink-200 truncate max-w-xs" title="{{ $prodName }}">
                                        {{ $prodName }}
                                    </td>
                                    <td class="py-2 px-3">
                                        @if($isOk)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-mint/20 text-up-mint border border-up-mint/30">
                                                ✓ WebP Sukses
                                            </span>
                                        @elseif($isSkip)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-amber/20 text-up-amber border border-up-amber/30">
                                                ⊘ Dilewati
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-red/20 text-up-red border border-up-red/30">
                                                ✕ Gagal
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3 font-sans text-xs text-ink-600 dark:text-ink-400">
                                        {{ $keterangan }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-ink-400 italic">
                                        Tidak ada berkas yang cocok dengan filter atau pencarian saat ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($totalFilteredCount > $fotoErrorDisplayLimit)
                    <div class="text-center pt-2">
                        <button type="button"
                                wire:click="loadMoreFotoErrors"
                                class="px-4 py-2 rounded-xl text-xs font-bold bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 text-ink-700 dark:text-ink-200 border border-black/10 dark:border-white/10 cursor-pointer transition">
                            Muat 50 Berkas Lainnya (Menampilkan {{ $fotoErrorDisplayLimit }} dari {{ $totalFilteredCount }})
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
