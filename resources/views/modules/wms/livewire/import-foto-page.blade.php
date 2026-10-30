<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-lg font-black text-ink-950 dark:text-white">Import Foto Produk Massal</h1>
            <p class="text-xs text-ink-400 mt-0.5">Unggah foto produk dalam jumlah besar. Sistem otomatis mencocokkan nama file dengan SKU produk.</p>
        </div>
        <a href="/app/wms" class="text-xs text-up-primary hover:underline font-semibold">← Kembali ke WMS</a>
    </div>

    @if($fotoStep === 'upload')
        <!-- Step 1: Upload Form -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left: Settings & Dropzone -->
            <div class="p-6 rounded-2xl bg-black/5 dark:bg-white/[0.02] border border-black/10 dark:border-white/5 space-y-5">
                <div class="space-y-1">
                    <h3 class="text-sm font-bold text-ink-900 dark:text-white flex items-center gap-2">
                        <span>⚙️</span>
                        <span>Pengaturan Import</span>
                    </h3>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-[11px] font-bold text-ink-700 dark:text-ink-300 mb-1.5">Sumber Foto:</label>
                        <div class="flex items-center gap-3">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" wire:model="fotoSourceType" value="files" class="radio radio-primary">
                                <span class="text-xs font-medium text-ink-700 dark:text-ink-300">File (Multi-select / Folder)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" wire:model="fotoSourceType" value="zip" class="radio radio-primary">
                                <span class="text-xs font-medium text-ink-700 dark:text-ink-300">Arsip .ZIP</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-ink-700 dark:text-ink-300 mb-1.5">Mode Simpan:</label>
                        <div class="grid grid-cols-3 gap-2">
                            <label class="relative p-3 rounded-xl border-2 cursor-pointer transition {{ $fotoMode === 'append' ? 'border-up-primary bg-up-primary/5' : 'border-black/10 dark:border-white/10 hover:border-up-primary' }}">
                                <input type="radio" wire:model="fotoMode" value="append" class="sr-only">
                                <div class="text-center">
                                    <div class="font-bold text-xs text-ink-900 dark:text-white">Tambah (Append)</div>
                                    <div class="text-[10px] text-ink-400 mt-0.5">Hanya isi produk tanpa foto</div>
                                </div>
                            </label>
                            <label class="relative p-3 rounded-xl border-2 cursor-pointer transition {{ $fotoMode === 'replace' ? 'border-up-primary bg-up-primary/5' : 'border-black/10 dark:border-white/10 hover:border-up-primary' }}">
                                <input type="radio" wire:model="fotoMode" value="replace" class="sr-only">
                                <div class="text-center">
                                    <div class="font-bold text-xs text-ink-900 dark:text-white">Ganti (Replace)</div>
                                    <div class="text-[10px] text-ink-400 mt-0.5">Timpa foto existing</div>
                                </div>
                            </label>
                            <label class="relative p-3 rounded-xl border-2 cursor-pointer transition {{ $fotoMode === 'skip_existing' ? 'border-up-primary bg-up-primary/5' : 'border-black/10 dark:border-white/10 hover:border-up-primary' }}">
                                <input type="radio" wire:model="fotoMode" value="skip_existing" class="sr-only">
                                <div class="text-center">
                                    <div class="font-bold text-xs text-ink-900 dark:text-white">Lewati (Skip)</div>
                                    <div class="text-[10px] text-ink-400 mt-0.5">Biarkan foto lama</div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="relative overflow-hidden p-6 rounded-2xl border-2 border-dashed {{ $fotoUploads || $fotoZip ? 'border-up-mint bg-up-mint/[0.03]' : 'border-black/15 dark:border-white/15 bg-black/5 dark:bg-white/[0.01]' }} hover:border-up-primary transition text-center space-y-2 cursor-pointer group">
                    <input type="file"
                           wire:model="fotoSourceType === 'files' ? 'fotoUploads' : 'fotoZip'"
                           accept="{{ $fotoSourceType === 'files' ? '.jpg,.jpeg,.png,.webp' : '.zip,application/zip' }}"
                           multiple="{{ $fotoSourceType === 'files' }}"
                           webkitdirectory="{{ $fotoSourceType === 'files' }}"
                           directory="{{ $fotoSourceType === 'files' }}"
                           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">

                    @if($fotoSourceType === 'files' && $fotoUploads)
                        <div class="text-3xl text-up-mint">✓</div>
                        <div class="font-bold text-xs text-ink-900 dark:text-white">{{ count($fotoUploads) }} file dipilih</div>
                    @elseif($fotoSourceType === 'zip' && $fotoZip)
                        <div class="text-3xl text-up-mint">✓</div>
                        <div class="font-bold text-xs text-ink-900 dark:text-white">{{ $fotoZip->getClientOriginalName() }}</div>
                        <div class="text-[11px] text-ink-400">{{ number_format($fotoZip->getSize() / 1024 / 1024, 1) }} MB</div>
                    @elseif($fotoSourceType === 'files')
                        <div class="text-3xl group-hover:scale-110 transition-transform">📂</div>
                        <div class="font-bold text-xs text-ink-900 dark:text-white">Pilih Folder / File Manager</div>
                        <p class="text-[11px] text-ink-400">Buka File Manager HP/Laptop. Menjaga 100% nama file asli sesuai SKU.</p>
                    @else
                        <div class="text-3xl group-hover:scale-110 transition-transform">🗜️</div>
                        <div class="font-bold text-xs text-ink-900 dark:text-white">Pilih Berkas Arsip (.ZIP)</div>
                        <p class="text-[11px] text-ink-400">Sistem membaca isi ZIP di browser & hanya ekstrak foto yang cocok SKU</p>
                    @endif
                </div>

                <button type="button"
                        wire:click="scanUploadedFoto"
                        wire:loading.attr="disabled"
                        class="w-full py-3 px-4 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs transition flex items-center justify-center gap-2 cursor-pointer shadow-md shadow-up-primary/20 disabled:opacity-50">
                    <span wire:loading.remove wire:target="scanUploadedFoto">Scan & Cocokkan SKU →</span>
                    <span wire:loading wire:target="scanUploadedFoto" class="flex items-center gap-2">
                        <span class="animate-spin">⏳</span>
                        <span>Menganalisis Nama File...</span>
                    </span>
                </button>
            </div>

            <!-- Right: Rules & Info -->
            <div class="p-6 rounded-2xl bg-black/5 dark:bg-white/[0.02] border border-black/10 dark:border-white/5 space-y-4">
                <h3 class="text-sm font-bold text-ink-900 dark:text-white flex items-center gap-2">
                    <span>💡</span>
                    <span>Aturan Penamaan & Kompresi</span>
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
                            <li>• <strong class="text-up-primary">[SKU]/depan.jpg</strong> (Subfolder)</li>
                        </ul>
                    </div>

                    <div class="p-3 rounded-xl bg-up-mint/5 border border-up-mint/20 space-y-1">
                        <div class="font-bold text-up-mint flex items-center gap-1.5">
                            <span>⚡</span>
                            <span>Kompresi WebP Otomatis:</span>
                        </div>
                        <p class="text-[11px]">Semua gambar dikonversi ke WebP (Full max 1200x1200px @ 82% & Thumbnail 250x250px @ 75%). Hemat ruang hingga 70%.</p>
                    </div>

                    <div class="p-3 rounded-xl bg-up-amber/5 border border-up-amber/20 space-y-1">
                        <div class="font-bold text-up-amber flex items-center gap-1.5">
                            <span>🛡️</span>
                            <span>Jika SKU Tidak Ditemukan:</span>
                        </div>
                        <p class="text-[11px]">File dilewati aman (skip). Unduh daftar file skip (Excel) untuk perbaiki nama file.</p>
                    </div>
                </div>
            </div>
        </div>

    @elseif($fotoStep === 'preview')
        <!-- Step 2: Preview & Verification -->
        <div class="space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="p-4 rounded-2xl bg-black/5 dark:bg-white/[0.02] border border-black/10 dark:border-white/5 space-y-1">
                    <div class="text-[10px] uppercase font-bold text-ink-400">Total File Terbaca</div>
                    <div class="text-2xl font-black text-ink-950 dark:text-white">{{ $fotoScanResult['total'] ?? 0 }} File</div>
                </div>
                <div class="p-4 rounded-2xl bg-up-mint/10 border border-up-mint/30 space-y-1">
                    <div class="text-[10px] uppercase font-bold text-up-mint">SKU Cocok & Siap Proses</div>
                    <div class="text-2xl font-black text-up-mint">{{ $fotoScanResult['matched_count'] ?? 0 }} Foto</div>
                </div>
                <div class="p-4 rounded-2xl bg-up-red/10 border border-up-red/30 space-y-1">
                    <div class="text-[10px] uppercase font-bold text-up-red">Dilewati / SKU Tidak Ada</div>
                    <div class="text-2xl font-black text-up-red">{{ $fotoScanResult['skipped_count'] ?? 0 }} File</div>
                </div>
            </div>

            @if(($fotoScanResult['skipped_count'] ?? 0) > 0)
                <div class="p-4 rounded-2xl bg-up-red/10 border border-up-red/30 space-y-3">
                    <div class="flex items-center justify-between flex-wrap gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">⚠️</span>
                            <div>
                                <h3 class="text-sm font-bold text-up-red">Terdeteksi {{ $fotoScanResult['skipped_count'] ?? 0 }} Berkas Foto Tidak Cocok</h3>
                                <p class="text-[11px] text-ink-400">Berkas tanpa SKU di katalog dilewati aman. Unduh daftarnya untuk perbaiki nama.</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <button type="button" wire:click="exportFotoErrorRows" class="px-2.5 py-1.5 rounded-lg text-[10px] font-bold bg-white/10 hover:bg-white/20 text-white cursor-pointer transition shadow flex items-center gap-1.5">
                                <span>📥</span> Unduh CSV Error
                            </button>
                            <button type="button" wire:click="abaikanSemuaFotoError" class="px-2.5 py-1.5 rounded-lg text-[10px] font-bold bg-up-red/80 hover:bg-up-red text-white cursor-pointer transition shadow flex items-center gap-1.5">
                                <span>✓</span> Abaikan Semua Lanjut Proses
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Filter Bar & Table of Scanned Files -->
            <div class="p-5 rounded-2xl bg-black/5 dark:bg-white/[0.02] border border-black/10 dark:border-white/5 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-black/5 dark:border-white/5 pb-3">
                    <div class="flex items-center gap-2">
                        <button type="button"
                                wire:click="setFotoFilterTab('all')"
                                class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $fotoFilterTab === 'all' ? 'bg-up-primary text-white' : 'text-ink-400 hover:text-white' }}">
                            Semua Berkas ({{ $fotoScanResult['total'] ?? 0 }})
                        </button>
                        <button type="button"
                                wire:click="setFotoFilterTab('matched')"
                                class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $fotoFilterTab === 'matched' ? 'bg-up-mint text-ink-950' : 'text-ink-400 hover:text-white' }}">
                            Cocok SKU ({{ $fotoScanResult['matched_count'] ?? 0 }})
                        </button>
                        <button type="button"
                                wire:click="setFotoFilterTab('skipped')"
                                class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $fotoFilterTab === 'skipped' ? 'bg-up-red text-white' : 'text-ink-400 hover:text-white' }}">
                            Dilewati ({{ $fotoScanResult['skipped_count'] ?? 0 }})
                        </button>
                    </div>

                    @if(! empty($fotoScanResult['skipped']))
                        <button type="button"
                                wire:click="downloadFotoSkippedExcel"
                                class="text-xs font-bold text-up-primary hover:underline flex items-center gap-1 cursor-pointer">
                            <span>📥</span>
                            <span>Unduh Laporan File Dilewati (.xlsx)</span>
                        </button>
                    @endif
                </div>

                <!-- Table Preview -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-black/10 dark:border-white/10 text-ink-400 text-[10px] uppercase font-bold">
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
        <!-- Step 3 & 4: Live Processing & Completed -->
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
        @endphp

        <div class="space-y-6 text-center">
            <div class="flex items-center justify-center">
                @if($isProses)
                    <div class="w-16 h-16 rounded-full bg-up-primary/10 border border-up-primary/20 flex items-center justify-center text-up-primary text-2xl animate-pulse">
                        <span class="animate-spin inline-block">⏳</span>
                    </div>
                @elseif($isGagal)
                    <div class="w-16 h-16 rounded-full bg-up-red/10 border border-up-red/20 flex items-center justify-center text-up-red text-2xl">⚠️</div>
                @else
                    <div class="w-16 h-16 rounded-full bg-up-mint/10 border border-up-mint/20 flex items-center justify-center text-up-mint text-2xl shadow-lg shadow-up-mint/10">✓</div>
                @endif
            </div>

            <div class="space-y-1">
                <h2 class="text-xl sm:text-2xl font-black text-ink-950 dark:text-white">
                    @if($isProses) Import Foto Sedang Berjalan... @elseif($isGagal) Import Foto Mengalami Kendala @else Import Foto Produk Selesai! @endif
                </h2>
                <p class="text-xs text-ink-400 max-w-lg mx-auto">
                    @if($isProses) Sistem mengompresi gambar ke WebP dan menyinkronkan ke katalog produk. Halaman diperbarui otomatis. @elseif($isGagal) Proses terhenti karena kendala teknis. Periksa detail di bawah. @else Seluruh foto berhasil diproses, dikompresi ke WebP, dan disinkronkan ke katalog master produk WMS, POS Kasir, serta Marketplace. @endif
                </p>
            </div>

            <div class="max-w-lg mx-auto p-5 rounded-2xl bg-black/40 border border-white/10 text-left text-xs space-y-4 shadow-xl">
                <div class="flex items-center justify-between font-mono pb-3 border-b border-white/10">
                    <span class="text-ink-400 font-bold">Import Foto Log #{{ $log['id'] ?? ($fotoImportLogId ?? '-') }}</span>
                    @if($isProses)
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-up-primary/20 text-up-primary border border-up-primary/30 flex items-center gap-1.5 animate-pulse">
                            <span class="w-1.5 h-1.5 rounded-full bg-up-primary animate-ping"></span>
                            <span>Sedang Memproses</span>
                        </span>
                    @elseif($isGagal)
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-up-red/20 text-up-red border border-up-red/30">Gagal</span>
                    @else
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-up-mint/20 text-up-mint border border-up-mint/30">Selesai</span>
                    @endif
                </div>

                <div class="space-y-1.5">
                    <div class="flex items-center justify-between text-[11px] font-mono">
                        <span class="text-ink-400 font-semibold">Kemajuan:</span>
                        @if($totalItems > 0)
                            <span class="text-up-mint font-black">{{ $persen }}% ({{ $selesaiCount }} dari {{ $totalItems }} foto)</span>
                        @else
                            <span class="text-up-mint font-black">{{ $persen }}%</span>
                        @endif
                    </div>
                    <div class="w-full h-3 rounded-full bg-black/60 overflow-hidden p-0.5 border border-white/10">
                        <div class="h-full rounded-full bg-gradient-to-r from-up-primary via-up-mint to-up-mint transition-all duration-300 {{ $isProses ? 'animate-pulse' : '' }}" style="width: {{ $persen }}%"></div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 text-center">
                    <div class="p-2.5 rounded-xl bg-up-mint/15 border border-up-mint/30">
                        <div class="text-2xl font-black text-up-mint">{{ $suksesCount }}</div>
                        <div class="text-[10px] uppercase text-up-mint/80">Berhasil</div>
                    </div>
                    <div class="p-2.5 rounded-xl bg-up-red/15 border border-up-red/30">
                        <div class="text-2xl font-black text-up-red">{{ $gagalCount }}</div>
                        <div class="text-[10px] uppercase text-up-red/80">Gagal</div>
                    </div>
                    <div class="p-2.5 rounded-xl bg-ink-200/50 dark:bg-white/10 border border-ink-200/30 dark:border-white/20">
                        <div class="text-2xl font-black text-ink-900 dark:text-white">{{ $totalItems }}</div>
                        <div class="text-[10px] uppercase text-ink-500">Total</div>
                    </div>
                </div>

                @if($isProses)
                    <div class="text-center text-[11px] text-ink-400">
                        Memproses: <strong>{{ $log['current_sku'] ?? '' }}</strong> — {{ $log['current_nama'] ?? '' }}
                    </div>
                @endif
            </div>

            @if($totalBermasalah > 0)
                <div class="p-5 rounded-2xl bg-black/5 dark:bg-white/[0.02] border border-black/10 dark:border-white/5 space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-bold text-ink-900 dark:text-white">Detail Error & Skip ({{ $totalBermasalah }})</h3>
                        <div class="flex items-center gap-1.5">
                            <button type="button" wire:click="setFotoFilterTab('semua')" class="px-2.5 py-1 rounded-lg text-[10px] font-bold {{ $fotoErrorFilterTab === 'semua' ? 'bg-up-primary text-white' : 'bg-black/5 dark:bg-white/5 text-ink-400' }}">Semua</button>
                            <button type="button" wire:click="setFotoFilterTab('gagal')" class="px-2.5 py-1 rounded-lg text-[10px] font-bold {{ $fotoErrorFilterTab === 'gagal' ? 'bg-up-red text-white' : 'bg-black/5 dark:bg-white/5 text-ink-400' }}">Gagal</button>
                            <button type="button" wire:click="setFotoFilterTab('skip')" class="px-2.5 py-1 rounded-lg text-[10px] font-bold {{ $fotoErrorFilterTab === 'skip' ? 'bg-up-amber text-white' : 'bg-black/5 dark:bg-white/5 text-ink-400' }}">Skip</button>
                        </div>
                    </div>

                    <div class="max-h-96 overflow-y-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-black/10 dark:border-white/10 text-ink-400 text-[10px] uppercase font-bold">
                                    <th class="py-2 px-3">File / SKU</th>
                                    <th class="py-2 px-3">Status</th>
                                    <th class="py-2 px-3">Alasan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-black/5 dark:divide-white/5">
                                @foreach(array_slice($bermasalahItems, 0, $fotoErrorDisplayLimit) as $item)
                                    <tr class="hover:bg-white/[0.02]">
                                        <td class="py-2 px-3">
                                            <div class="font-mono text-up-primary">{{ $item['sku'] ?? $item['filename'] ?? '-' }}</div>
                                            <div class="text-[10px] text-ink-400">{{ $item['filename'] ?? '' }}</div>
                                        </td>
                                        <td class="py-2 px-3">
                                            @if(($item['status'] ?? '') === 'gagal')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-red/20 text-up-red">⚠ Gagal</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-amber/20 text-up-amber">⊘ Skip</span>
                                            @endif
                                        </td>
                                        <td class="py-2 px-3 text-ink-400">{{ $item['error'] ?? $item['reason'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if($totalBermasalah > $fotoErrorDisplayLimit)
                            <div class="text-center pt-2">
                                <button type="button" wire:click="$set('fotoErrorDisplayLimit', $fotoErrorDisplayLimit + 50)" class="px-4 py-2 rounded-xl text-xs font-bold bg-white/5 hover:bg-white/10 text-ink-200 border border-white/10 cursor-pointer transition">Muat 50 Lainnya ({{ $fotoErrorDisplayLimit }} dari {{ $totalBermasalah }})</button>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
                <button type="button" wire:click="resetFotoUpload" class="w-full sm:w-auto min-h-[44px] px-6 py-2.5 rounded-xl bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 text-ink-300 font-bold text-xs cursor-pointer hover:bg-black/10 transition flex items-center justify-center">Import Foto Lainnya</button>
                <a href="/app/wms" wire:navigate class="w-full sm:w-auto min-h-[44px] px-6 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs cursor-pointer shadow-md shadow-up-primary/20 transition flex items-center justify-center">Buka Daftar Produk WMS →</a>
            </div>
        </div>
    @endif
</div>