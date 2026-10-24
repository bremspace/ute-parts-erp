<div class="space-y-6">
    <!-- Navigasi Kembali ke WMS -->
    <div class="flex items-center justify-between gap-3">
        <a href="{{ route('wms', ['tab' => 'opname']) }}" wire:navigate class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 dark:bg-white/5 dark:hover:bg-white/10 text-ink-700 dark:text-ink-200 border border-black/10 dark:border-white/10 text-xs font-bold transition active:scale-95 cursor-pointer shadow-sm">
            <span>←</span>
            <span>Kembali ke WMS (Opname)</span>
        </a>
    </div>

    <!-- [F3-7] Cycle Count Otomatis (G-18) -->
    <x-prism.glass-card title="Cycle Count Otomatis" subtitle="Jadwal & tugas hitung stok berkala">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-ink-100">Jadwal</h3>
            @can('wms.approve-opname')
                <button wire:click="bukaFormSchedule" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-sm transition">
                    + Jadwal Baru
                </button>
            @endcan
        </div>

        {{-- Form tambah jadwal --}}
        @if($showScheduleForm)
            <div class="p-5 rounded-2xl bg-white/[0.04] border border-white/10 space-y-4 mb-5 shadow-xl">
                <div class="flex items-center justify-between pb-3 border-b border-white/10">
                    <div>
                        <h4 class="text-sm font-bold text-white">Buat Jadwal Cycle Count Baru</h4>
                        <p class="text-xs text-ink-400">Konfigurasi jadwal audit stok acak berkala secara otomatis</p>
                    </div>
                    <button type="button" wire:click="batalSchedule" class="text-xs text-ink-400 hover:text-white cursor-pointer">✕ Batal</button>
                </div>

                {{-- 1. Nama Jadwal --}}
                <div>
                    <label class="block text-xs font-semibold text-ink-200 mb-1">
                        Nama Jadwal <span class="text-up-red">*</span>
                    </label>
                    <input
                        type="text"
                        wire:model="scheduleNama"
                        placeholder="Contoh: Audit Mingguan Rak Fast Moving / Cek Rutin Baterai"
                        class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-xl text-ink-100 text-xs min-h-[44px] focus:border-teal-500 transition"
                    >
                    <p class="text-[11px] text-ink-400 mt-1">Nama pengenal jadwal agar mudah diidentifikasi oleh tim gudang.</p>
                    @error('scheduleNama') <span class="text-[11px] text-up-red font-semibold block mt-0.5">{{ $message }}</span> @enderror
                </div>

                {{-- 2. Target Sampling: Tipe & Lokasi/Kategori/Brand/Semua --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-3.5 rounded-xl bg-white/[0.02] border border-white/5">
                    <div>
                        <label class="block text-xs font-semibold text-ink-200 mb-1">
                            Tipe Target Sampling <span class="text-up-red">*</span>
                        </label>
                        <select
                            wire:model.live="tipeTarget"
                            class="w-full px-3 py-2 bg-ink-900 border border-white/10 rounded-xl text-ink-100 text-xs min-h-[44px]"
                        >
                            <option value="rak">Per Rak (Berdasarkan Lokasi Bin)</option>
                            <option value="kategori">Per Kategori Produk</option>
                            <option value="brand">Per Brand Produk</option>
                            <option value="semua">Semua Stok di Cabang Ini</option>
                        </select>
                        <p class="text-[11px] text-ink-400 mt-1">Metode pengelompokan produk yang akan diacak.</p>
                    </div>

                    @if($tipeTarget === 'rak')
                        <div>
                            <label class="block text-xs font-semibold text-ink-200 mb-1">
                                Pilih Rak Sasaran
                            </label>
                            <select
                                wire:model="targetId"
                                class="w-full px-3 py-2 bg-ink-900 border border-white/10 rounded-xl text-ink-100 text-xs min-h-[44px]"
                            >
                                <option value="0">Semua Rak di Cabang Ini</option>
                                @foreach($raks as $rk)
                                    <option value="{{ $rk->id }}">
                                        {{ $rk->kode }} - {{ $rk->nama }} (Gudang: {{ $rk->gudang?->nama ?? '-' }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-ink-400 mt-1">Hanya sparepart pada rak ini yang akan diundi menjadi sampel.</p>
                        </div>
                    @elseif($tipeTarget === 'kategori')
                        <div>
                            <label class="block text-xs font-semibold text-ink-200 mb-1">
                                Pilih Kategori Sasaran
                            </label>
                            <select
                                wire:model="targetKategori"
                                class="w-full px-3 py-2 bg-ink-900 border border-white/10 rounded-xl text-ink-100 text-xs min-h-[44px]"
                            >
                                <option value="">Semua Kategori Produk</option>
                                @foreach($kategoris as $kat)
                                    <option value="{{ $kat }}">{{ $kat }}</option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-ink-400 mt-1">Hanya sparepart dalam kategori ini yang akan diundi menjadi sampel.</p>
                        </div>
                    @elseif($tipeTarget === 'brand')
                        <div>
                            <label class="block text-xs font-semibold text-ink-200 mb-1">
                                Pilih Brand Sasaran
                            </label>
                            <select
                                wire:model="targetBrandId"
                                class="w-full px-3 py-2 bg-ink-900 border border-white/10 rounded-xl text-ink-100 text-xs min-h-[44px]"
                            >
                                <option value="">Semua Brand</option>
                                @foreach($brands as $b)
                                    <option value="{{ $b->id }}">{{ $b->nama }}</option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-ink-400 mt-1">Hanya sparepart dengan brand ini yang akan diundi.</p>
                        </div>
                    @else
                        <div class="flex items-center">
                            <div class="p-3 rounded-xl bg-white/5 border border-white/10 text-xs text-ink-300 w-full">
                                <span class="font-bold text-white block mb-0.5">Semua Barang:</span>
                                Seluruh sparepart aktif pada seluruh gudang di cabang ini akan masuk ke dalam undian sampel acak.
                            </div>
                        </div>
                    @endif
                </div>

                {{-- 3. Waktu & Frekuensi Eksekusi --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 p-3.5 rounded-xl bg-white/[0.02] border border-white/5">
                    <div>
                        <label class="block text-xs font-semibold text-ink-200 mb-1">
                            Frekuensi Pelaksanaan <span class="text-up-red">*</span>
                        </label>
                        <select
                            wire:model.live="frekuensi"
                            class="w-full px-3 py-2 bg-ink-900 border border-white/10 rounded-xl text-ink-100 text-xs min-h-[44px]"
                        >
                            <option value="mingguan">Mingguan (1x seminggu)</option>
                            <option value="bulanan">Bulanan (1x sebulan)</option>
                        </select>
                        <p class="text-[11px] text-ink-400 mt-1">Seberapa sering tugas otomatis dibuat.</p>
                    </div>

                    @if($frekuensi === 'mingguan')
                        <div>
                            <label class="block text-xs font-semibold text-ink-200 mb-1">
                                Hari Pelaksanaan <span class="text-up-red">*</span>
                            </label>
                            <select
                                wire:model="hari"
                                class="w-full px-3 py-2 bg-ink-900 border border-white/10 rounded-xl text-ink-100 text-xs min-h-[44px]"
                            >
                                <option value="1">Senin</option>
                                <option value="2">Selasa</option>
                                <option value="3">Rabu</option>
                                <option value="4">Kamis</option>
                                <option value="5">Jumat</option>
                                <option value="6">Sabtu</option>
                                <option value="7">Minggu</option>
                            </select>
                            <p class="text-[11px] text-ink-400 mt-1">Hari tugas hitung fisik akan diterbitkan.</p>
                        </div>
                    @else
                        <div>
                            <label class="block text-xs font-semibold text-ink-200 mb-1">
                                Tanggal Pelaksanaan (1 - 31) <span class="text-up-red">*</span>
                            </label>
                            <input
                                type="number"
                                wire:model="hari"
                                min="1"
                                max="31"
                                class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-ink-100 text-xs min-h-[44px] tabular-nums"
                            >
                            <p class="text-[11px] text-ink-400 mt-1">Tanggal tugas dibuat setiap bulannya.</p>
                        </div>
                    @endif

                    <div>
                        <label class="block text-xs font-semibold text-ink-200 mb-1">
                            Jam Eksekusi Otomatis (WIB) <span class="text-up-red">*</span>
                        </label>
                        <input
                            type="time"
                            wire:model="jam"
                            class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-ink-100 text-xs min-h-[44px] tabular-nums"
                        >
                        <p class="text-[11px] text-ink-400 mt-1">Jam saat sistem mengundi sampel dan membuat tugas.</p>
                        @error('jam') <span class="text-[11px] text-up-red font-semibold block mt-0.5">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- 4. Parameter Sampling & Batas Ambang (Threshold) --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 p-3.5 rounded-xl bg-white/[0.02] border border-white/5">
                    <div>
                        <label class="block text-xs font-semibold text-ink-200 mb-1">
                            Jumlah Sampel Produk <span class="text-up-red">*</span>
                        </label>
                        <div class="relative">
                            <input
                                type="number"
                                wire:model="sampleSize"
                                min="1"
                                max="100"
                                placeholder="10"
                                class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-ink-100 text-xs min-h-[44px] tabular-nums pr-12"
                            >
                            <span class="absolute right-3 top-3 text-[11px] text-ink-400">item</span>
                        </div>
                        <p class="text-[11px] text-ink-400 mt-1">
                            Berapa banyak jenis sparepart yang diambil acak untuk dihitung (disarankan 5–15 item agar cepat).
                        </p>
                        @error('sampleSize') <span class="text-[11px] text-up-red font-semibold block mt-0.5">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-200 mb-1">
                            Toleransi Unit (Threshold Unit) <span class="text-up-red">*</span>
                        </label>
                        <div class="relative">
                            <input
                                type="number"
                                wire:model="thresholdUnit"
                                min="0"
                                placeholder="0"
                                class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-ink-100 text-xs min-h-[44px] tabular-nums pr-12"
                            >
                            <span class="absolute right-3 top-3 text-[11px] text-ink-400">unit</span>
                        </div>
                        <p class="text-[11px] text-ink-400 mt-1">
                            Standar: <strong>0 (Zero-Tolerance)</strong>. Selisih sekecil apapun (termasuk 1 unit) akan berstatus Major dan memerlukan persetujuan Supervisor sebelum stok disesuaikan.
                        </p>
                        @error('thresholdUnit') <span class="text-[11px] text-up-red font-semibold block mt-0.5">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-200 mb-1">
                            Toleransi Persen (Threshold %) <span class="text-up-red">*</span>
                        </label>
                        <div class="relative">
                            <input
                                type="number"
                                wire:model="thresholdPersen"
                                min="0"
                                max="100"
                                placeholder="0"
                                class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-ink-100 text-xs min-h-[44px] tabular-nums pr-8"
                            >
                            <span class="absolute right-3 top-3 text-[11px] text-ink-400">%</span>
                        </div>
                        <p class="text-[11px] text-ink-400 mt-1">
                            Standar: <strong>0%</strong>. Selisih persentase di atas angka ini mewajibkan persetujuan Supervisor untuk menjamin integritas pembukuan stok & akuntansi.
                        </p>
                        @error('thresholdPersen') <span class="text-[11px] text-up-red font-semibold block mt-0.5">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="flex items-center gap-3 pt-2">
                    <button
                        type="button"
                        wire:click="simpanSchedule"
                        class="px-5 py-2.5 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-bold transition active:scale-[0.97] min-h-[44px] cursor-pointer shadow-lg shadow-teal-600/20"
                    >
                        Simpan Jadwal Cycle Count
                    </button>
                    <button
                        type="button"
                        wire:click="batalSchedule"
                        class="px-5 py-2.5 bg-white/10 hover:bg-white/15 text-ink-300 rounded-xl text-xs font-semibold transition active:scale-[0.97] min-h-[44px] cursor-pointer"
                    >
                        Batal
                    </button>
                </div>
            </div>
        @endif

        <div class="space-y-2">
            @foreach($schedules as $schedule)
                <div class="flex flex-col sm:flex-row sm:items-center justify-between p-3.5 rounded-xl bg-white/[0.03] border border-white/5 gap-2">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-ink-100 text-sm">{{ $schedule->nama }}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-teal-500/10 text-teal-400 border border-teal-500/20">
                                {{ $schedule->target_label }}
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 mt-1 text-xs text-ink-400">
                            <span>
                                {{ ucfirst($schedule->frekuensi) }}
                                ({{ $schedule->frekuensi === 'mingguan' ? (['1' => 'Senin', '2' => 'Selasa', '3' => 'Rabu', '4' => 'Kamis', '5' => 'Jumat', '6' => 'Sabtu', '7' => 'Minggu'][(string)$schedule->hari] ?? 'Hari '.$schedule->hari) : 'Tgl '.$schedule->hari }})
                                pk {{ $schedule->jam }} WIB
                            </span>
                            <span>•</span>
                            <span>Sampel: <strong class="text-ink-200">{{ $schedule->sample_size }} produk</strong></span>
                            <span>•</span>
                            <span>Toleransi: <strong class="text-ink-200">&le; {{ $schedule->threshold_unit }} unit / {{ $schedule->threshold_persen }}%</strong></span>
                        </div>
                    </div>
                    @can('wms.approve-opname')
                        <button
                            wire:click="jalankanSchedule({{ $schedule->id }})"
                            class="px-3.5 py-1.5 bg-teal-600/80 hover:bg-teal-600 text-white rounded-lg text-xs font-semibold transition cursor-pointer self-start sm:self-auto"
                        >
                            Jalankan Sekarang
                        </button>
                    @endcan
                </div>
            @endforeach
        </div>
    </x-prism.glass-card>

    <x-prism.glass-card title="Tugas Cycle Count" subtitle="Hasil count dan status persetujuan">
        <div class="flex flex-col sm:flex-row gap-2 mb-4">
            <input type="text" wire:model="search" placeholder="Cari tugas..." class="flex-1 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-ink-100 text-sm min-h-[44px]">
            <select wire:model="filterStatus" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-ink-100 text-sm min-h-[44px]">
                <option value="semua">Semua</option>
                <option value="menunggu_count">Menunggu Count</option>
                <option value="menunggu_approval">Menunggu Approval</option>
                <option value="selesai">Selesai</option>
                <option value="ditolak">Ditolak</option>
            </select>
        </div>

        @foreach($tasks as $task)
            <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-black/10 dark:border-white/5 mb-2.5 hover:bg-white/[0.05] transition-colors shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                wire:click="bukaDetail({{ $task->id }})"
                                class="font-mono text-sm text-ink-900 dark:text-ink-100 font-bold hover:text-teal-400 hover:underline transition-colors text-left cursor-pointer"
                            >
                                {{ $task->no_task }}
                            </button>
                            <x-prism.status-pill :status="$task->status" />
                            @if($task->target_label)
                                <span class="text-xs text-ink-500 dark:text-ink-400 font-medium">({{ $task->target_label }})</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 text-[11px] text-ink-500 dark:text-ink-400 flex-wrap">
                            <span>📅 {{ $task->tanggal ? $task->tanggal->format('d/m/Y') : '-' }}</span>
                            <span>•</span>
                            @if($task->user || $task->pembuat)
                                <span class="inline-flex items-center gap-1 text-teal-400 font-medium">
                                    <span>👤</span>
                                    <span>Pelaksana: {{ $task->user?->name ?? $task->pembuat?->name }}</span>
                                </span>
                            @else
                                <span class="text-ink-400 italic">Pelaksana: Belum ada</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-2 self-start sm:self-auto shrink-0 flex-wrap">
                        <button
                            type="button"
                            wire:click="bukaDetail({{ $task->id }})"
                            class="px-3 py-1.5 bg-black/5 dark:bg-white/10 hover:bg-black/10 dark:hover:bg-white/15 text-ink-900 dark:text-white rounded-xl text-xs font-semibold transition cursor-pointer border border-black/10 dark:border-white/10 min-h-[36px]"
                        >
                            Detail
                        </button>
                        @if($task->status === 'menunggu_count')
                            <button wire:click="mulaiCount({{ $task->id }})" class="px-3.5 py-1.5 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-bold cursor-pointer transition shadow-md shadow-teal-600/20 active:scale-95 min-h-[36px] flex items-center gap-1.5">
                                <span>📝</span>
                                <span>Input Count</span>
                            </button>
                        @elseif($task->status === 'menunggu_approval')
                            @can('wms.approve-opname')
                                <button wire:click="approveTask({{ $task->id }})" class="px-3 py-1.5 bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs rounded-xl transition shadow-sm cursor-pointer min-h-[36px]">Approve</button>
                                <button wire:click="tolakTask({{ $task->id }})" class="px-3 py-1.5 bg-up-red/20 hover:bg-up-red/30 text-up-red font-bold text-xs rounded-xl transition cursor-pointer min-h-[36px]">Tolak</button>
                            @endcan
                        @endif
                    </div>
                </div>
            </div>
        @endforeach

        {{-- Count form (Blind Count: Stok Sistem disembunyikan dari pelaksana) --}}
        @if($selectedTaskId)
            <div class="mt-4 p-5 rounded-2xl bg-white/[0.04] border border-white/10 space-y-4 shadow-xl">
                <div class="flex items-center justify-between pb-3 border-b border-white/10">
                    <div>
                        <div class="flex items-center gap-2">
                            <h4 class="font-bold text-ink-100 text-sm">Input Hitungan Fisik Sampel</h4>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-teal-500/10 text-teal-400 border border-teal-500/20">
                                🔒 Blind Count
                            </span>
                        </div>
                        <p class="text-xs text-ink-400 mt-0.5">
                            Angka stok sistem disembunyikan agar pelaksana menghitung fisik aktual di rak secara objektif tanpa bias.
                        </p>
                    </div>
                    <button type="button" wire:click="batalCount" class="text-xs text-ink-400 hover:text-white cursor-pointer">✕ Batal</button>
                </div>

                @php
                    $countTask = $activeTask ?? $tasks->firstWhere('id', $selectedTaskId);
                    $sampleItems = $countTask->sample_items ?? [];
                @endphp

                <!-- Barcode Scanner Cepat untuk Hitung Sampel -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 p-3 rounded-xl bg-white/[0.02] border border-white/5">
                    <div class="relative flex-1">
                        <input
                            type="text"
                            wire:model="countScanInput"
                            wire:keydown.enter.prevent="scanBarcodeDirect(countScanInput)"
                            placeholder="Scan barcode / ketik SKU barang sampel (Enter untuk hitung +1)..."
                            class="w-full px-3.5 py-2 rounded-xl glass-input text-xs text-white placeholder-ink-400 min-h-[42px] focus:ring-1 focus:ring-teal-500"
                        />
                    </div>
                    <button
                        type="button"
                        wire:click="scanBarcodeDirect(countScanInput)"
                        class="px-3.5 py-2 rounded-xl bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold min-h-[42px] flex items-center justify-center cursor-pointer transition active:scale-95 shrink-0"
                    >
                        + Hitung Scan
                    </button>
                    <button
                        type="button"
                        x-data="{}"
                        @click="if (window.uteBarcode && window.uteBarcode.openCameraScanner) {
                            window.uteBarcode.openCameraScanner({
                                title: 'Scan Barcode Cycle Count',
                                continuous: true,
                                onScan: (code) => { $wire.scanBarcodeDirect(code); }
                            });
                        } else {
                            alert('Modul scanner kamera sedang memuat...');
                        }"
                        class="px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-teal-300 text-xs font-bold min-h-[42px] flex items-center justify-center gap-1 cursor-pointer transition shrink-0 active:scale-95"
                        title="Scan Barcode via Kamera HP"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" /></svg>
                        <span class="hidden sm:inline">Kamera</span>
                    </button>
                </div>

                <div class="space-y-2">
                    @forelse($sampleItems as $item)
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-2xl bg-white/[0.02] border border-white/5 hover:border-white/10 transition">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs sm:text-sm text-white font-bold block truncate">
                                        {{ !empty($item['nama']) ? $item['nama'] : ('Produk #' . ($item['produk_id'] ?? $item['stok_item_id'])) }}
                                    </span>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 text-[11px] text-ink-400 mt-1">
                                    @if(!empty($item['rak_nama']))
                                        <span class="px-1.5 py-0.5 rounded bg-white/5 text-ink-300 font-mono text-[10px] border border-white/10">
                                            📍 Rak: {{ $item['rak_nama'] }}
                                        </span>
                                    @elseif(!empty($item['rak_id']))
                                        <span class="px-1.5 py-0.5 rounded bg-white/5 text-ink-300 font-mono text-[10px] border border-white/10">
                                            📍 Rak ID: #{{ $item['rak_id'] }}
                                        </span>
                                    @endif
                                    <span>Item ID: <strong class="text-ink-300 font-mono">#{{ $item['stok_item_id'] }}</strong></span>
                                    @if(!empty($item['sku_variant_id']))
                                        <span>• Varian ID: #{{ $item['sku_variant_id'] }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center justify-between sm:justify-end gap-2 self-stretch sm:self-center shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-white/5">
                                <label class="text-xs text-ink-300 font-medium whitespace-nowrap">Hitungan Fisik:</label>
                                <div class="flex items-center gap-1">
                                    <button
                                        type="button"
                                        wire:click="kurangHitungan({{ $item['stok_item_id'] }})"
                                        class="w-8 h-8 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white flex items-center justify-center font-bold text-sm cursor-pointer active:scale-95"
                                    >-</button>
                                    <input
                                        type="number"
                                        min="0"
                                        wire:model="fisikPerItem.{{ $item['stok_item_id'] }}"
                                        placeholder="0"
                                        class="w-20 px-2 py-1.5 bg-white/5 border border-white/10 rounded-xl text-white text-xs font-bold text-center tabular-nums min-h-[40px] focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                                    >
                                    <button
                                        type="button"
                                        wire:click="tambahHitungan({{ $item['stok_item_id'] }})"
                                        class="w-8 h-8 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white flex items-center justify-center font-bold text-sm cursor-pointer active:scale-95"
                                    >+</button>
                                    <span class="text-xs text-ink-400 ml-1">unit</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-center text-xs text-ink-400">
                            Tidak ada item sampel pada tugas ini.
                        </div>
                    @endforelse
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button
                        wire:click="submitCount"
                        class="px-5 py-2.5 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-bold transition active:scale-[0.97] min-h-[44px] cursor-pointer shadow-lg shadow-teal-600/20"
                    >
                        Kirim Hasil Hitung
                    </button>
                    <button
                        wire:click="batalCount"
                        class="px-5 py-2.5 bg-white/10 hover:bg-white/15 text-ink-300 rounded-xl text-xs font-semibold transition active:scale-[0.97] min-h-[44px] cursor-pointer"
                    >
                        Batal
                    </button>
                </div>
            </div>
        @endif
    </x-prism.glass-card>

    <!-- MODAL: DETAIL TUGAS CYCLE COUNT & AUDIT MUTASI -->
    @if($showDetailModal && $detailTask)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-3xl glass-panel p-6 rounded-3xl border border-white/10 shadow-2xl relative max-h-[90vh] overflow-y-auto space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-white/10">
                    <div class="flex items-center gap-3">
                        <h3 class="text-base font-bold text-white font-mono">{{ $detailTask->no_task }}</h3>
                        <x-prism.status-pill :status="$detailTask->status" />
                    </div>
                    <button type="button" wire:click="tutupDetail" class="text-ink-400 hover:text-white text-base">✕</button>
                </div>

                <!-- Info Header Ringkasan -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                        <span class="text-ink-400 block text-[11px]">Jadwal / Target</span>
                        <strong class="text-white">{{ $detailTask->schedule?->nama ?? $detailTask->target_label ?? 'Manual' }}</strong>
                        <span class="text-[10px] text-ink-400 block">Tipe: {{ ucfirst($detailTask->tipe_target) }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                        <span class="text-ink-400 block text-[11px]">Tanggal Tugas</span>
                        <strong class="text-white">{{ $detailTask->tanggal ? $detailTask->tanggal->format('d/m/Y') : '-' }}</strong>
                        <span class="text-[10px] text-ink-400 block">Threshold: {{ $detailTask->threshold_unit }} unit / {{ $detailTask->threshold_persen }}%</span>
                    </div>
                    <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                        <span class="text-ink-400 block text-[11px]">Petugas Count</span>
                        <strong class="text-white">{{ $detailTask->pembuat?->name ?? '-' }}</strong>
                        @if($detailTask->counted_at)
                            <span class="text-[10px] text-ink-400 block">{{ $detailTask->counted_at->format('d/m/Y H:i') }}</span>
                        @endif
                    </div>
                    <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                        <span class="text-ink-400 block text-[11px]">Approval / Eksekusi</span>
                        <strong class="text-white">{{ $detailTask->approver?->name ?? '-' }}</strong>
                        @if($detailTask->applied_at)
                            <span class="text-[10px] text-ink-400 block">{{ $detailTask->applied_at->format('d/m/Y H:i') }}</span>
                        @endif
                    </div>
                </div>

                @if($detailTask->catatan)
                    <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5 text-xs">
                        <span class="text-ink-400 block text-[11px] font-semibold">Catatan:</span>
                        <p class="text-ink-200 mt-0.5">{{ $detailTask->catatan }}</p>
                    </div>
                @endif

                <!-- Item Sample & Hasil Perhitungan -->
                <div>
                    <h4 class="text-xs font-bold text-white uppercase tracking-wider mb-2">
                        {{ !empty($detailTask->hasil) ? 'Hasil Perhitungan Fisik & Klasifikasi' : 'Sample Item Terjadwal' }}
                    </h4>

                    @if(!empty($detailTask->hasil))
                        <div class="rounded-xl border border-white/10 overflow-hidden">
                            <table class="w-full text-xs text-left text-ink-100">
                                <thead class="bg-white/5 text-ink-400 uppercase text-[10px] font-semibold">
                                    <tr>
                                        <th class="p-3">Produk</th>
                                        <th class="p-3 text-center">Stok Snapshot</th>
                                        <th class="p-3 text-center">Stok Sistem</th>
                                        <th class="p-3 text-center">Stok Fisik</th>
                                        <th class="p-3 text-center">Selisih</th>
                                        <th class="p-3 text-center">Klasifikasi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($detailTask->hasil as $h)
                                        <tr>
                                            <td class="p-3">
                                                <div class="font-medium text-white">{{ !empty($h['nama']) ? $h['nama'] : ('Item #' . ($h['stok_item_id'] ?? '-')) }}</div>
                                                @if(!empty($h['stok_item_id']))
                                                    <div class="text-[10px] text-ink-400 font-mono">Item ID: #{{ $h['stok_item_id'] }}</div>
                                                @endif
                                            </td>
                                            <td class="p-3 text-center tabular-nums text-ink-400">{{ $h['stok_snapshot'] ?? '-' }}</td>
                                            <td class="p-3 text-center tabular-nums text-ink-300">{{ $h['stok_sistem'] ?? '-' }}</td>
                                            <td class="p-3 text-center tabular-nums font-bold text-white">{{ $h['stok_fisik'] ?? '-' }}</td>
                                            <td class="p-3 text-center tabular-nums font-bold {{ ($h['selisih'] ?? 0) < 0 ? 'text-up-red' : (($h['selisih'] ?? 0) > 0 ? 'text-up-mint' : 'text-ink-400') }}">
                                                {{ ($h['selisih'] ?? 0) > 0 ? "+{$h['selisih']}" : ($h['selisih'] ?? 0) }}
                                            </td>
                                            <td class="p-3 text-center">
                                                @php
                                                    $klas = $h['klasifikasi'] ?? 'cocok';
                                                @endphp
                                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold {{ $klas === 'cocok' ? 'bg-up-mint/20 text-up-mint' : ($klas === 'minor' ? 'bg-up-amber/20 text-up-amber' : 'bg-up-red/20 text-up-red') }}">
                                                    {{ strtoupper($klas) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="rounded-xl border border-white/10 overflow-hidden">
                            <table class="w-full text-xs text-left text-ink-100">
                                <thead class="bg-white/5 text-ink-400 uppercase text-[10px] font-semibold">
                                    <tr>
                                        <th class="p-3">Produk</th>
                                        <th class="p-3 text-center">Stok Sistem Terjadwal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($detailTask->sample_items ?? [] as $sample)
                                        <tr>
                                            <td class="p-3">
                                                <div class="font-medium text-white">{{ !empty($sample['nama']) ? $sample['nama'] : ('Item #' . ($sample['stok_item_id'] ?? '-')) }}</div>
                                                <div class="text-[10px] text-ink-400 font-mono">
                                                    @if(!empty($sample['rak_nama']))
                                                        <span>Rak: {{ $sample['rak_nama'] }} • </span>
                                                    @elseif(!empty($sample['rak_id']))
                                                        <span>Rak ID: #{{ $sample['rak_id'] }} • </span>
                                                    @endif
                                                    <span>Item ID: #{{ $sample['stok_item_id'] ?? '-' }}</span>
                                                </div>
                                            </td>
                                            <td class="p-3 text-center tabular-nums text-ink-300">{{ $sample['stok_sistem'] ?? '-' }} unit</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <!-- Audit Mutasi Stok (StokLog) Terkait -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-xs font-bold text-white uppercase tracking-wider">Audit Mutasi Stok (StokLog)</h4>
                        <span class="text-[11px] text-ink-400">{{ $mutasiLogs->count() }} penyesuaian tercatat</span>
                    </div>

                    @if($mutasiLogs->isEmpty())
                        <div class="p-4 rounded-xl bg-white/[0.02] border border-white/5 text-center text-xs text-ink-400">
                            Belum ada pergerakan stok tercatat (belum dihitung, selisih 0 / cocok, atau menunggu approval).
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
                                        <th class="p-3">User</th>
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

                <div class="flex items-center justify-between pt-2">
                    <div>
                        @if($detailTask->status === 'menunggu_approval')
                            @can('wms.approve-opname')
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        wire:click="approveTask({{ $detailTask->id }}); tutupDetail();"
                                        class="px-4 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs transition cursor-pointer shadow-md shadow-up-mint/20"
                                    >
                                        Approve & Sesuaikan Stok
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="tolakTask({{ $detailTask->id }}); tutupDetail();"
                                        class="px-4 py-2.5 rounded-xl bg-up-red/20 hover:bg-up-red/30 text-up-red font-bold text-xs transition cursor-pointer"
                                    >
                                        Tolak Tugas
                                    </button>
                                </div>
                            @endcan
                        @endif
                    </div>
                    <button type="button" wire:click="tutupDetail" class="px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/15 text-white font-semibold text-xs cursor-pointer transition">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
