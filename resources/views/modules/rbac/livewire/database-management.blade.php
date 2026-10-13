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
                Pusat kontrol pencadangan (backup), pemulihan (restore), sinkronisasi cloud, serta pembersihan data aman Ute Parts.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" wire:click="openResetModal"
                class="px-4 py-2.5 rounded-xl bg-up-red/10 hover:bg-up-red/20 border border-up-red/30 text-up-red text-xs font-bold transition-all duration-150 active:scale-[0.97] flex items-center gap-2 shadow-sm min-h-[44px]">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Reset Data Aman
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
                        class="px-5 py-2.5 rounded-xl bg-up-primary hover:bg-indigo-600 text-white text-xs font-bold shadow-md transition-all duration-150 active:scale-[0.97] flex items-center gap-2 min-h-[44px]">
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
                                        class="p-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white transition-all"
                                        title="Download ke Laptop/PC ini">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                    </button>

                                    <!-- Restore Server -->
                                    <button type="button" wire:click="openRestoreModal('{{ $b['filename'] }}')"
                                        class="px-2.5 py-1 rounded-lg bg-up-primary/10 hover:bg-up-primary/20 border border-up-primary/30 text-up-primary font-bold text-[11px] transition-all"
                                        title="Pulihkan database dari berkas ini">
                                        Restore
                                    </button>

                                    <!-- Hapus -->
                                    <button type="button" wire:click="hapusBackup('{{ $b['filename'] }}')"
                                        wire:confirm="Yakin ingin menghapus berkas backup ini dari server?"
                                        class="p-1.5 rounded-lg bg-up-red/10 hover:bg-up-red/20 text-up-red transition-all"
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
                        class="px-4 py-2 rounded-xl bg-up-amber hover:bg-amber-600 text-gray-900 text-xs font-bold shadow-md transition-all">
                        <span wire:loading.remove wire:target="eksekusiRestore">Ya, Pulihkan Database</span>
                        <span wire:loading wire:target="eksekusiRestore">Memulihkan...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Konfirmasi Safe Reset -->
    @if($showResetModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in">
            <div class="relative w-full max-w-lg bg-[#0f172a] border border-up-red/30 rounded-2xl shadow-2xl p-6 text-white space-y-4">
                <div class="flex items-center gap-3 text-up-red">
                    <svg class="w-7 h-7 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    <div>
                        <h3 class="text-base font-black text-white">Safe Reset: Kosongkan Transaksional</h3>
                        <span class="text-[10px] text-up-red font-bold uppercase tracking-wider">Tindakan Sangat Berbahaya</span>
                    </div>
                </div>

                <div class="p-3.5 rounded-xl bg-up-red/10 border border-up-red/20 text-xs space-y-1.5 text-ink-300">
                    <p class="font-bold text-white">Data yang akan DIHAPUS BERSIH:</p>
                    <ul class="list-disc list-inside space-y-0.5 text-[11px] text-ink-300">
                        <li>Seluruh transaksi POS, keranjang, dan order penjualan</li>
                        <li>Seluruh tiket servis, estimasi, dan pemakaian sparepart</li>
                        <li>Seluruh riwayat mutasi stok, stok opname (stok fisik kembali 0)</li>
                        <li>Seluruh jurnal akuntansi, piutang, utang, dan riwayat kas laci</li>
                    </ul>
                    <p class="font-bold text-up-mint pt-1">Data yang AMAN & DIPERTAHANKAN:</p>
                    <p class="text-[11px] text-ink-300">
                        Akun pengguna (Super Admin, Owner, Kasir, dll) tetap ada, password direset ke standar: <code class="text-white bg-black/40 px-1 py-0.5 rounded font-mono">Password123!</code>.
                    </p>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1">
                            Ketik persis teks: <span class="text-white font-mono font-bold select-all">SAYA SETUJU RESET DATA</span>
                        </label>
                        <input type="text" wire:model="confirmResetText" placeholder="Ketik teks konfirmasi di sini"
                            class="w-full px-3 py-2 rounded-xl bg-white/[0.04] border border-white/10 text-xs text-white focus:border-up-red focus:ring-0">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1">Masukkan Kata Sandi Akun Anda:</label>
                        <input type="password" wire:model="confirmPassword" placeholder="Kata sandi akun saat ini"
                            class="w-full px-3 py-2 rounded-xl bg-white/[0.04] border border-white/10 text-xs text-white focus:border-up-red focus:ring-0">
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="$set('showResetModal', false)"
                        class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-ink-400 hover:text-white text-xs font-semibold">
                        Batal
                    </button>
                    <button type="button" wire:click="eksekusiReset" wire:loading.attr="disabled"
                        class="px-4 py-2 rounded-xl bg-up-red hover:bg-red-600 text-white text-xs font-bold shadow-md transition-all">
                        <span wire:loading.remove wire:target="eksekusiReset">Ya, Eksekusi Safe Reset</span>
                        <span wire:loading wire:target="eksekusiReset">Memproses Reset...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
