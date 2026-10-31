<div class="space-y-6">
    <!-- Header & Health Status Banner -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-5 rounded-2xl bg-white/[0.02] border border-white/10 backdrop-blur-md">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider {{ ($health['status'] ?? '') === 'healthy' ? 'bg-up-mint/20 text-up-mint border border-up-mint/30' : 'bg-up-red/20 text-up-red border border-up-red/30' }}">
                    <span class="w-2 h-2 rounded-full {{ ($health['status'] ?? '') === 'healthy' ? 'bg-up-mint animate-pulse' : 'bg-up-red animate-ping' }}"></span>
                    Status: {{ strtoupper($health['status'] ?? 'NORMAL') }}
                </span>
                <span class="text-xs text-ink-400 font-mono">Dedicated Server Storage</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-black tracking-tight text-white mt-1.5 flex items-center gap-2">
                Manajemen Basis Data & Keselamatan Sistem
            </h1>
            <p class="text-xs text-ink-400 mt-0.5">
                Pusat kontrol pencadangan (backup), pemulihan (restore), sinkronisasi cloud, serta pembersihan data aman.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" wire:click="clearCacheServer" wire:loading.attr="disabled"
                wire:confirm="Bersihkan seluruh cache server sekarang? (Cache aplikasi, rute, view, konfigurasi, dan OPcache akan direset)"
                class="px-4 py-2.5 rounded-xl bg-up-amber/10 hover:bg-up-amber/20 border border-up-amber/30 text-up-amber text-xs font-bold transition-[background-color,border-color,transform] duration-140 active:scale-[0.97] flex items-center gap-2 shadow-sm min-h-[44px] cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                <span wire:loading.remove wire:target="clearCacheServer">Clear Cache Server</span>
                <span wire:loading wire:target="clearCacheServer">Membersihkan Cache...</span>
            </button>

            <button type="button" wire:click="openResetModal('transaksi_only')"
                class="px-4 py-2.5 rounded-xl bg-up-red/10 hover:bg-up-red/20 border border-up-red/30 text-up-red text-xs font-bold transition-[background-color,border-color,transform] duration-140 active:scale-[0.97] flex items-center gap-2 shadow-sm min-h-[44px] cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Reset &amp; Pembersihan Data
            </button>
        </div>
    </div>

    <!-- Quick Action: Backup Sekarang & Upload Restore -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- 1. Card Buat Backup Baru -->
        <x-prism.glass-card class="lg:col-span-2 p-5" title="Buat Cadangan (Backup) Sekarang" subtitle="Amankan seluruh skema database & data transaksi ke snapshot terkompresi">
            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Destinasi / Tujuan Penyimpanan</label>
                        <select wire:model.live="backupDestination"
                            class="w-full px-3 py-2 rounded-xl bg-white/[0.04] border border-white/10 text-xs text-white focus:border-up-primary focus:ring-0">
                            <option value="local" class="bg-gray-900 text-white">Simpan di Server Lokal (Direktori Backups)</option>
                            <option value="download" class="bg-gray-900 text-white">Langsung Download ke Laptop/PC Saat Ini (.sql.gz)</option>
                            <option value="cloud" class="bg-gray-900 text-white">Kirim ke Cloud Storage (S3 / Google Drive via adapter)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Retensi Rotasi Server (Hari)</label>
                        <input type="number" wire:model.live="keepDays" min="1" max="90"
                            class="w-full px-3 py-2 rounded-xl bg-white/[0.04] border border-white/10 text-xs text-white focus:border-up-primary focus:ring-0">
                    </div>
                </div>

                @if($backupDestination === 'cloud')
                    <div class="p-3 rounded-xl bg-up-primary/10 border border-up-primary/20 text-xs text-indigo-300 flex items-center justify-between">
                        <div>
                            <span class="font-bold">Koneksi Cloud Storage Disk:</span>
                            <span class="font-mono text-white ml-1">{{ $cloudDisk }}</span>
                        </div>
                        <span class="text-[10px] text-ink-400">Pastikan AWS_BUCKET atau adapter cloud terisi di .env</span>
                    </div>
                @endif

                <div class="pt-2 flex justify-end">
                    <button type="button" wire:click="buatBackup" wire:loading.attr="disabled"
                        class="px-5 py-2.5 rounded-xl bg-up-primary hover:bg-indigo-600 text-white text-xs font-bold shadow-md transition-[background-color,transform] duration-140 active:scale-[0.97] flex items-center gap-2 min-h-[44px]">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                        </svg>
                        <span wire:loading.remove wire:target="buatBackup">Eksekusi Backup Sekarang</span>
                        <span wire:loading wire:target="buatBackup">Memproses Dump & Gzip...</span>
                    </button>
                </div>
            </div>
        </x-prism.glass-card>

        <!-- 2. Card Upload Restore dari PC Lokal -->
        <x-prism.glass-card class="p-5" title="Pulihkan dari Berkas Luar" subtitle="Upload file .sql.gz dari laptop atau PC Anda untuk dipulihkan ke server">
            <div class="space-y-3">
                <div class="p-4 border-2 border-dashed border-white/10 rounded-xl text-center hover:border-up-mint/40 transition-colors">
                    <input type="file" wire:model="uploadedBackupFile" accept=".gz,.sql" class="hidden" id="upload-backup-input">
                    <label for="upload-backup-input" class="cursor-pointer block">
                        <svg class="w-8 h-8 text-ink-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        <span class="text-xs font-bold text-white block">Pilih Berkas Backup (.sql.gz)</span>
                        <span class="text-[10px] text-ink-400">Maksimum ukuran sesuai batas upload PHP</span>
                    </label>
                </div>

                @if($uploadedBackupFile)
                    <div class="p-2.5 rounded-lg bg-up-mint/10 border border-up-mint/30 text-xs text-up-mint flex items-center justify-between">
                        <span class="truncate max-w-[200px]">{{ $uploadedBackupFile->getClientOriginalName() }}</span>
                        <button type="button" wire:click="openRestoreModal('uploaded')" class="font-bold underline text-white">
                            Lanjut Restore →
                        </button>
                    </div>
                @endif
            </div>
        </x-prism.glass-card>
    </div>

    <!-- Riwayat File Backup di Server -->
    <x-prism.glass-card title="Daftar Berkas Cadangan di Server" subtitle="Riwayat snapshot database yang tersimpan di storage lokal PC server">
        <x-prism.dual-scroll>
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="text-[10px] uppercase tracking-wider text-ink-400 font-semibold border-b border-white/5">
                        <th class="pb-2.5">Nama Berkas</th>
                        <th class="pb-2.5">Ukuran</th>
                        <th class="pb-2.5">Waktu Dibuat</th>
                        <th class="pb-2.5 text-center">Aksi / Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse($backups as $b)
                        <tr class="hover:bg-white/[0.02] transition-colors">
                            <td class="py-3 font-mono font-bold text-white flex items-center gap-2">
                                <svg class="w-4 h-4 text-up-mint" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                {{ $b['filename'] }}
                            </td>
                            <td class="py-3 text-ink-300 tabular-nums">
                                {{ $b['size_formatted'] }}
                            </td>
                            <td class="py-3 text-ink-400">
                                {{ $b['created_at'] }}
                            </td>
                            <td class="py-3 text-center">
                                <div class="inline-flex items-center gap-1.5">
                                    <!-- Download ke Perangkat Klien -->
                                    <button type="button" wire:click="downloadBackup('{{ $b['filename'] }}')"
                                        class="p-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white transition-[background-color,color] duration-140 active:scale-95 cursor-pointer"
                                        title="Download ke Laptop/PC ini">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                    </button>

                                    <!-- Restore Server -->
                                    <button type="button" wire:click="openRestoreModal('{{ $b['filename'] }}')"
                                        class="px-2.5 py-1 rounded-lg bg-up-primary/10 hover:bg-up-primary/20 border border-up-primary/30 text-up-primary font-bold text-[11px] transition-[background-color,transform] duration-100 active:scale-[0.98] cursor-pointer"
                                        title="Pulihkan database dari berkas ini">
                                        Restore
                                    </button>

                                    <!-- Hapus -->
                                    <button type="button" wire:click="hapusBackup('{{ $b['filename'] }}')"
                                        wire:confirm="Yakin ingin menghapus berkas backup ini dari server?"
                                        class="p-1.5 rounded-lg bg-up-red/10 hover:bg-up-red/20 text-up-red transition-[background-color,color] duration-140 active:scale-95 cursor-pointer"
                                        title="Hapus berkas">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 text-center text-ink-500">
                                Belum ada berkas backup yang tersimpan di server.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </x-prism.dual-scroll>
    </x-prism.glass-card>

    <!-- Modal Konfirmasi Restore -->
    @if($showRestoreModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in">
            <div class="relative w-full max-w-md bg-[#0f172a] border border-white/10 rounded-2xl shadow-2xl p-6 text-white space-y-4">
                <div class="flex items-center gap-3 text-up-amber">
                    <svg class="w-6 h-6 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <h3 class="text-base font-bold text-white">Konfirmasi Pemulihan Database</h3>
                </div>

                <p class="text-xs text-ink-300">
                    Memulihkan database akan menimpa seluruh data sistem saat ini dengan isi berkas:
                    <strong class="text-white block mt-1 font-mono">{{ $selectedRestoreFile }}</strong>
                </p>

                <div class="p-3 rounded-xl bg-up-primary/10 border border-up-primary/20 text-[11px] text-indigo-300">
                    🛡️ Sistem akan otomatis membuat snapshot darurat pra-restore sebelum proses penimpaan dilakukan.
                </div>

                <div>
                    <label class="block text-xs font-semibold text-ink-300 mb-1">Masukkan Kata Sandi Anda untuk Otorisasi:</label>
                    <input type="password" wire:model="confirmPassword" placeholder="Kata sandi akun saat ini"
                        class="w-full px-3 py-2 rounded-xl bg-white/[0.04] border border-white/10 text-xs text-white focus:border-up-primary focus:ring-0">
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="$set('showRestoreModal', false)"
                        class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-ink-400 hover:text-white text-xs font-semibold">
                        Batal
                    </button>
                    <button type="button" wire:click="eksekusiRestore" wire:loading.attr="disabled"
                        class="px-4 py-2 rounded-xl bg-up-amber hover:bg-amber-400 text-ink-950 text-xs font-bold shadow-md transition-[background-color,transform] duration-100 active:scale-[0.98] cursor-pointer">
                        <span wire:loading.remove wire:target="eksekusiRestore">Ya, Pulihkan Database</span>
                        <span wire:loading wire:target="eksekusiRestore">Memulihkan...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Konfirmasi Enterprise Reset & Purge Data -->
    @if($showResetModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md animate-fade-in overflow-y-auto">
            <div class="relative w-full max-w-2xl bg-[#0b1120] border border-up-red/40 rounded-3xl shadow-2xl p-5 sm:p-7 text-white space-y-5 my-6 max-h-[92vh] overflow-y-auto">
                <!-- Modal Header -->
                <div class="flex items-start justify-between gap-3 pb-3 border-b border-white/10">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-up-red/15 border border-up-red/30 flex items-center justify-center text-up-red flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base sm:text-lg font-black text-white tracking-tight">Manajemen &amp; Reset Data Sistem (Enterprise Purge)</h3>
                            <p class="text-xs text-ink-400">Pilih skenario pembersihan data yang sesuai dengan tahapan operasional bisnis Anda.</p>
                        </div>
                    </div>
                    <button type="button" wire:click="$set('showResetModal', false)" class="text-ink-400 hover:text-white p-1.5 rounded-xl hover:bg-white/5 transition-colors cursor-pointer">
                        ✕
                    </button>
                </div>

                <!-- Pre-Backup Auto-Protection Notice -->
                <div class="flex items-center gap-2.5 p-3 rounded-2xl bg-up-mint/10 border border-up-mint/25 text-xs text-up-mint">
                    <span class="text-base">🛡️</span>
                    <div>
                        <span class="font-bold">Perlindungan Snapshot Otomatis:</span>
                        <span class="text-ink-300"> Sistem secara otomatis membuat file cadangan SQL darurat sebelum operasi pembersihan dijalankan.</span>
                    </div>
                </div>

                <!-- Pilihan Skenario Reset (4 Opsi Standar Industri) -->
                <div>
                    <label class="block text-xs font-bold text-ink-200 mb-2.5">Pilih Skenario Pembersihan Data:</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <!-- Opsi 1: Transaksi Saja (Go-Live) -->
                        <div wire:click="$set('resetMode', 'transaksi_only')"
                            class="p-3.5 rounded-2xl border text-left cursor-pointer transition-[transform,background-color,border-color] duration-140 active:scale-[0.98] {{ $resetMode === 'transaksi_only' ? 'bg-up-mint/15 border-up-mint text-white shadow-md shadow-up-mint/15' : 'bg-white/[0.03] border-white/10 hover:bg-white/[0.06] text-ink-300' }}">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-xl">🛒</span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $resetMode === 'transaksi_only' ? 'bg-up-mint text-ink-950' : 'bg-white/10 text-ink-400' }}">SIAP GO-LIVE</span>
                            </div>
                            <h4 class="text-xs font-bold text-white">Reset Transaksional</h4>
                            <p class="text-[11px] text-ink-400 mt-1 leading-snug">Hapus data uji coba (kasir, servis, jurnal, stok opname). Master part &amp; akun tetap utuh.</p>
                        </div>

                        <!-- Opsi 2: Transaksi + Master Katalog -->
                        <div wire:click="$set('resetMode', 'master_and_transaksi')"
                            class="p-3.5 rounded-2xl border text-left cursor-pointer transition-[transform,background-color,border-color] duration-140 active:scale-[0.98] {{ $resetMode === 'master_and_transaksi' ? 'bg-sky-500/15 border-sky-400 text-white shadow-md shadow-sky-500/15' : 'bg-white/[0.03] border-white/10 hover:bg-white/[0.06] text-ink-300' }}">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-xl">📦</span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $resetMode === 'master_and_transaksi' ? 'bg-sky-400 text-ink-950' : 'bg-white/10 text-ink-400' }}">RE-MIGRASI</span>
                            </div>
                            <h4 class="text-xs font-bold text-white">Transaksi + Katalog Part</h4>
                            <p class="text-[11px] text-ink-400 mt-1 leading-snug">Hapus transaksi &amp; master produk, brand, supplier, pelanggan. Cabang &amp; akun staf aman.</p>
                        </div>

                        <!-- Opsi 3: Factory Reset -->
                        <div wire:click="$set('resetMode', 'factory_reset')"
                            class="p-3.5 rounded-2xl border text-left cursor-pointer transition-[transform,background-color,border-color] duration-140 active:scale-[0.98] {{ $resetMode === 'factory_reset' ? 'bg-up-amber/15 border-up-amber text-white shadow-md shadow-up-amber/15' : 'bg-white/[0.03] border-white/10 hover:bg-white/[0.06] text-ink-300' }}">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-xl">🏭</span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $resetMode === 'factory_reset' ? 'bg-up-amber text-ink-950' : 'bg-white/10 text-ink-400' }}">SETELAN AWAL</span>
                            </div>
                            <h4 class="text-xs font-bold text-white">Factory Reset</h4>
                            <p class="text-[11px] text-ink-400 mt-1 leading-snug">Reset ke konfigurasi bawaan awal rilis. Seeder standar dipasang ulang, user kembali ke default.</p>
                        </div>

                        <!-- Opsi 4: Full Wipe -->
                        <div wire:click="$set('resetMode', 'full_wipe')"
                            class="p-3.5 rounded-2xl border text-left cursor-pointer transition-[transform,background-color,border-color] duration-140 active:scale-[0.98] {{ $resetMode === 'full_wipe' ? 'bg-up-red/20 border-up-red text-white shadow-md shadow-up-red/20' : 'bg-white/[0.03] border-white/10 hover:bg-white/[0.06] text-ink-300' }}">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-xl">⚠️</span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $resetMode === 'full_wipe' ? 'bg-up-red text-white' : 'bg-white/10 text-ink-400' }}">TOTAL ERASE</span>
                            </div>
                            <h4 class="text-xs font-bold text-white">Pembersihan Total (Full Wipe)</h4>
                            <p class="text-[11px] text-ink-400 mt-1 leading-snug">Kosongkan seluruh data tabel aplikasi. Mempertahankan 1 akun super admin aktif.</p>
                        </div>
                    </div>
                </div>

                <!-- Dynamic Impact Box -->
                <div class="p-4 rounded-2xl bg-white/[0.02] border border-white/10 space-y-2 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full {{ in_array($resetMode, ['factory_reset', 'full_wipe']) ? 'bg-up-red' : 'bg-up-amber' }}"></span>
                        <h5 class="font-bold text-white uppercase tracking-wider text-[11px]">Dampak Pembersihan Terpilih:</h5>
                    </div>

                    @if($resetMode === 'transaksi_only')
                        <div class="space-y-1.5 text-[11px] text-ink-300">
                            <p class="font-semibold text-up-red">Data yang DIHAPUS:</p>
                            <p>• Transaksi POS Kasir, tiket servis &amp; pemakaian sparepart, riwayat kas laci, pergerakan stok/mutasi/opname, PO, GRN, jurnal akuntansi, piutang &amp; utang.</p>
                            <p class="font-semibold text-up-mint pt-1">Data yang DIPERTAHANKAN:</p>
                            <p>• Master Produk, Varian SKU, Kategori, Brand, Tipe HP, Data Pelanggan, Supplier, Cabang Toko, Akun Staf &amp; Hak Akses RBAC.</p>
                        </div>
                    @elseif($resetMode === 'master_and_transaksi')
                        <div class="space-y-1.5 text-[11px] text-ink-300">
                            <p class="font-semibold text-up-red">Data yang DIHAPUS:</p>
                            <p>• Seluruh data transaksi + seluruh katalog master part (produk, varian SKU, brand, kategori, tipe HP, harga tier) serta data kontak pelanggan &amp; supplier.</p>
                            <p class="font-semibold text-up-mint pt-1">Data yang DIPERTAHANKAN:</p>
                            <p>• Lokasi Cabang Toko Fisik, Gudang, Rak Part, Akun Staf &amp; Role Spatie RBAC, Chart of Accounts (COA), dan Konfigurasi Toko.</p>
                        </div>
                    @elseif($resetMode === 'factory_reset')
                        <div class="space-y-1.5 text-[11px] text-ink-300">
                            <p class="font-semibold text-up-red">Data yang DIHAPUS:</p>
                            <p>• Seluruh data operasional, master kustom, cabang kustom, dan akun pengguna tambahan.</p>
                            <p class="font-semibold text-up-mint pt-1">Setelan yang DIPASANG ULANG (Seeder Bawaan):</p>
                            <p>• Role &amp; Izin standar (Spatie), Cabang Utama (CBG-01), Tier Membership standar, COA standar retail, dan Akun resmi bawaan (Super Admin, Owner, Kasir, Teknisi, dll) dengan password <code class="text-white bg-black/40 px-1 py-0.5 rounded font-mono">Password123!</code>.</p>
                        </div>
                    @elseif($resetMode === 'full_wipe')
                        <div class="space-y-1.5 text-[11px] text-ink-300">
                            <p class="font-semibold text-up-red">Data yang DIHAPUS BERSIH:</p>
                            <p>• Seluruh tabel data aplikasi tanpa terkecuali (transaksi, produk, kontak, log, pengaturan, dan cabang).</p>
                            <p class="font-semibold text-up-mint pt-1">Jaminan Anti-Lockout:</p>
                            <p>• Sistem secara otomatis menjaga 1 akun Super Administrator aktif dan Cabang Utama agar sesi login Anda tidak terputus dan aplikasi tidak mengalami galat 500.</p>
                        </div>
                    @endif
                </div>

                <!-- Opsi Tambahan (Khusus Transaksi Only) -->
                @if($resetMode === 'transaksi_only')
                    <div class="p-3.5 rounded-2xl bg-white/[0.02] border border-white/5 space-y-2">
                        <label class="flex items-center gap-2.5 text-xs text-ink-300 cursor-pointer">
                            <input type="checkbox" wire:model="resetZeroStock" class="accent-up-mint w-4 h-4 rounded" />
                            <span>Nolkan kuantitas stok fisik di gudang (<code class="text-white font-mono">jumlah = 0</code>) untuk persiapan stock opname awal</span>
                        </label>
                        <label class="flex items-center gap-2.5 text-xs text-ink-300 cursor-pointer">
                            <input type="checkbox" wire:model="resetUserPasswords" class="accent-up-mint w-4 h-4 rounded" />
                            <span>Reset kata sandi seluruh staf ke standar (<code class="text-white font-mono">Password123!</code>)</span>
                        </label>
                    </div>
                @endif

                <!-- Form Konfirmasi Keamanan -->
                <div class="space-y-3 pt-2 border-t border-white/10">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1">
                            Ketik persis teks konfirmasi: <span class="text-white font-mono font-bold select-all bg-white/10 px-1.5 py-0.5 rounded">{{ $this->expectedConfirmationText }}</span>
                        </label>
                        <input type="text" wire:model="confirmResetText" placeholder="Ketik teks konfirmasi di sini"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-white/[0.04] border border-white/15 text-xs text-white focus:border-up-red focus:ring-0 font-mono">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1">Masukkan Kata Sandi Akun Anda Saat Ini:</label>
                        <input type="password" wire:model="confirmPassword" placeholder="Kata sandi akun Anda untuk validasi"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-white/[0.04] border border-white/15 text-xs text-white focus:border-up-red focus:ring-0">
                    </div>
                </div>

                <!-- Footer Aksi -->
                <div class="flex flex-col-reverse sm:flex-row items-center justify-end gap-2.5 pt-2">
                    <button type="button" wire:click="$set('showResetModal', false)"
                        class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-400 hover:text-white text-xs font-semibold cursor-pointer">
                        Batal
                    </button>
                    <button type="button" wire:click="eksekusiReset" wire:loading.attr="disabled"
                        class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-up-red hover:bg-red-600 text-white text-xs font-bold shadow-md shadow-up-red/25 transition-[background-color,transform] duration-100 active:scale-[0.98] cursor-pointer flex items-center justify-center gap-2">
                        <span wire:loading.remove wire:target="eksekusiReset">⚠️ Eksekusi Pembersihan Data</span>
                        <span wire:loading wire:target="eksekusiReset">Memproses Pembersihan Database...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
