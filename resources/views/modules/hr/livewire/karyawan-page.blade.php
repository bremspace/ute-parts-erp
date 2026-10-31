<div class="space-y-6 w-full min-w-0 max-w-full overflow-hidden">
    <!-- ═══ HEADER: Judul & Aksi Rekrutmen Karyawan ═══ -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-black/10 dark:border-white/5 pb-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-bold text-ink-900 dark:text-white tracking-wide">Manajemen Karyawan</h1>
                <span class="text-[10px] font-mono text-ink-500 bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 px-2 py-0.5 rounded-full">HR &amp; RBAC</span>
            </div>
            <p class="text-xs text-ink-500 dark:text-ink-400 mt-1">
                Kelola siklus hidup karyawan: rekrutmen (onboarding), integrasi akun login aplikasi, penugasan cabang, hingga pemutusan hubungan kerja (offboarding).
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <x-prism.prism-button wire:click="openCreateModal" size="sm" class="min-h-11 shadow-md shadow-up-primary/20">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" /></svg>
                <span>+ Rekrut Karyawan Baru</span>
            </x-prism.prism-button>
        </div>
    </div>

    <!-- ═══ METRIK RINGKASAN KARYAWAN ═══ -->
    <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div class="p-4 rounded-2xl glass-panel border border-black/10 dark:border-white/10 bg-white/60 dark:bg-ink-900/40">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-semibold text-ink-500 dark:text-ink-400 uppercase tracking-wider">Total Karyawan</span>
                <span class="p-1.5 rounded-lg bg-up-primary/10 text-up-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                </span>
            </div>
            <div class="mt-2 text-2xl font-black tabular-nums text-ink-900 dark:text-white">{{ $totalKaryawan }}</div>
            <span class="text-[10px] text-ink-500">Terdaftar di cabang aktif</span>
        </div>

        <div class="p-4 rounded-2xl glass-panel border border-black/10 dark:border-white/10 bg-white/60 dark:bg-ink-900/40">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-semibold text-up-mint uppercase tracking-wider">Karyawan Aktif</span>
                <span class="p-1.5 rounded-lg bg-up-mint/10 text-up-mint">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </span>
            </div>
            <div class="mt-2 text-2xl font-black tabular-nums text-up-mint">{{ $totalAktif }}</div>
            <span class="text-[10px] text-ink-500">Bisa absensi &amp; payroll</span>
        </div>

        <div class="p-4 rounded-2xl glass-panel border border-black/10 dark:border-white/10 bg-white/60 dark:bg-ink-900/40">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-semibold text-up-red uppercase tracking-wider">Diberhentikan</span>
                <span class="p-1.5 rounded-lg bg-up-red/10 text-up-red">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </span>
            </div>
            <div class="mt-2 text-2xl font-black tabular-nums text-up-red">{{ $totalTerminated }}</div>
            <span class="text-[10px] text-ink-500">Resign / PHK / non-aktif</span>
        </div>

        <div class="p-4 rounded-2xl glass-panel border border-black/10 dark:border-white/10 bg-white/60 dark:bg-ink-900/40">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-semibold text-up-accent uppercase tracking-wider">Bertaut Akun Login</span>
                <span class="p-1.5 rounded-lg bg-up-accent/10 text-up-accent">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
                </span>
            </div>
            <div class="mt-2 text-2xl font-black tabular-nums text-up-accent">{{ $totalLinkedUser }}</div>
            <span class="text-[10px] text-ink-500">Punya kredensial aplikasi</span>
        </div>
    </div>

    <!-- ═══ TOOLBAR FILTER & PENCARIAN ═══ -->
    <div class="p-4 rounded-2xl glass-panel border border-black/10 dark:border-white/10 bg-white/50 dark:bg-ink-900/30">
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 flex-1">
                <!-- Search Input -->
                <div class="relative flex-1 min-w-[220px]">
                    <svg class="absolute left-3 top-3 w-4 h-4 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Cari nama, NIK, email, atau telepon..."
                        class="w-full pl-9 pr-3 py-2 rounded-xl glass-input text-xs font-medium min-h-[42px]"
                    />
                </div>

                <!-- Filter Status Ketenagakerjaan -->
                <select wire:model.live="filterStatus" class="px-3 py-2 rounded-xl glass-input text-xs font-medium min-h-[42px]">
                    <option value="semua">Semua Status</option>
                    <option value="aktif">Hanya Aktif</option>
                    <option value="nonaktif">Hanya Nonaktif (Diberhentikan)</option>
                </select>

                <!-- Filter Jabatan -->
                <select wire:model.live="filterJabatan" class="px-3 py-2 rounded-xl glass-input text-xs font-medium min-h-[42px]">
                    <option value="">Semua Jabatan</option>
                    <option value="teknisi">Teknisi</option>
                    <option value="kasir">Kasir</option>
                    <option value="admin">Admin</option>
                    <option value="marketing">Marketing</option>
                    <option value="manager">Manager</option>
                    <option value="staff">Staff</option>
                    <option value="other">Lainnya</option>
                </select>

                <!-- Filter Cabang (Super Admin / Owner) -->
                @if($isSuperAdmin)
                    <select wire:model.live="filterCabangId" class="px-3 py-2 rounded-xl glass-input text-xs font-medium min-h-[42px]">
                        <option value="">Semua Cabang</option>
                        @foreach($daftarCabang as $cb)
                            <option value="{{ $cb->id }}">{{ $cb->nama }}</option>
                        @endforeach
                    </select>
                @endif
            </div>

            <div class="text-xs text-ink-500 font-medium whitespace-nowrap self-end lg:self-center">
                Total hasil: <strong class="text-ink-900 dark:text-white tabular-nums">{{ $karyawans->total() }}</strong>
            </div>
        </div>
    </div>

    <!-- ═══ TABEL DATA KARYAWAN ═══ -->
    <x-prism.data-table :headers="['Karyawan / NIK', 'Kontak', 'Jabatan & Cabang', 'Status Kerja', 'Akun Login Aplikasi', 'Gaji Pokok', 'Aksi']">
        @forelse($karyawans as $k)
            <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors text-xs">
                <!-- Kolom 1: Profil & NIK -->
                <td class="py-3.5 px-4">
                    <div class="font-bold text-ink-900 dark:text-white flex items-center gap-1.5">
                        <span>{{ $k->nama }}</span>
                        @if(! $k->status_aktif)
                            <span class="text-[9px] uppercase font-bold px-1.5 py-0.5 rounded bg-up-red/10 text-up-red border border-up-red/20">Terminated</span>
                        @endif
                    </div>
                    <div class="font-mono text-[11px] text-ink-500 dark:text-ink-400 mt-0.5">{{ $k->nik ?: '-' }}</div>
                </td>

                <!-- Kolom 2: Kontak -->
                <td class="py-3.5 px-4">
                    <div class="text-ink-700 dark:text-ink-200">{{ $k->email ?: $k->user?->email ?: '-' }}</div>
                    <div class="text-[11px] text-ink-500 font-mono mt-0.5">{{ $k->telepon ?: $k->user?->phone ?: '-' }}</div>
                </td>

                <!-- Kolom 3: Jabatan & Cabang -->
                <td class="py-3.5 px-4">
                    @php
                        $badgeJabatan = match($k->jabatan) {
                            'teknisi' => 'bg-up-mint/10 text-up-mint border-up-mint/20',
                            'kasir' => 'bg-up-accent/10 text-up-accent border-up-accent/20',
                            'manager' => 'bg-up-amber/10 text-up-amber border-up-amber/20',
                            'marketing' => 'bg-purple-500/10 text-purple-400 border-purple-500/20',
                            default => 'bg-black/5 dark:bg-white/5 text-ink-700 dark:text-ink-300 border-black/10 dark:border-white/10'
                        };
                    @endphp
                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold uppercase border {{ $badgeJabatan }}">
                        {{ $k->jabatan }}
                    </span>
                    <div class="text-[11px] text-ink-500 dark:text-ink-400 mt-1 font-medium">
                        {{ $k->cabang?->nama ?? 'Cabang Tidak Ditentukan' }}
                    </div>
                </td>

                <!-- Kolom 4: Status Ketenagakerjaan -->
                <td class="py-3.5 px-4">
                    @if($k->status_aktif)
                        <span class="inline-flex items-center gap-1.5 text-up-mint font-semibold">
                            <span class="w-1.5 h-1.5 rounded-full bg-up-mint"></span>
                            <span>Aktif</span>
                        </span>
                        <div class="text-[10px] text-ink-500 mt-0.5">
                            Masuk: {{ $k->tgl_masuk ? $k->tgl_masuk->format('d/m/Y') : '-' }}
                        </div>
                    @else
                        <span class="inline-flex items-center gap-1.5 text-up-red font-semibold">
                            <span class="w-1.5 h-1.5 rounded-full bg-up-red"></span>
                            <span>Nonaktif (Keluar)</span>
                        </span>
                        <div class="text-[10px] text-ink-500 mt-0.5">
                            Keluar: {{ $k->tgl_keluar ? $k->tgl_keluar->format('d/m/Y') : '-' }}
                            @if($k->alasan_keluar)
                                <span class="capitalize">({{ $k->alasan_keluar }})</span>
                            @endif
                        </div>
                    @endif
                </td>

                <!-- Kolom 5: Akun Login Sistem & Role -->
                <td class="py-3.5 px-4">
                    @if($k->user)
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full {{ $k->user->is_active ? 'bg-up-mint' : 'bg-up-red' }}" title="{{ $k->user->is_active ? 'Akun login aktif' : 'Akun login terkunci/nonaktif' }}"></span>
                            <span class="font-mono text-[11px] text-ink-800 dark:text-ink-100 font-semibold truncate max-w-[150px]" title="{{ $k->user->email }}">
                                {{ $k->user->name }}
                            </span>
                        </div>
                        <div class="flex items-center gap-1.5 mt-1">
                            <span class="text-[10px] font-bold uppercase text-up-primary bg-up-primary/10 px-1.5 py-0.5 rounded">
                                {{ $k->user->roles->first()?->name ?? 'User' }}
                            </span>
                            @if(! $k->user->is_active)
                                <span class="text-[9px] font-bold text-up-red bg-up-red/10 px-1 rounded">TERKUNCI</span>
                            @endif
                        </div>
                    @else
                        <div class="text-ink-400 text-[11px] italic">Belum punya akun login</div>
                        <button type="button" wire:click="openLinkUserModal({{ $k->id }})" class="text-[10px] text-up-primary hover:underline font-bold mt-1 cursor-pointer">
                            + Tautkan Akun
                        </button>
                    @endif
                </td>

                <!-- Kolom 6: Gaji Pokok -->
                <td class="py-3.5 px-4 tabular-nums font-bold text-ink-900 dark:text-white">
                    Rp {{ number_format($k->gaji_pokok, 0, ',', '.') }}
                </td>

                <!-- Kolom 7: Aksi -->
                <td class="py-3.5 px-4">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <button
                            type="button"
                            wire:click="openHistoryModal({{ $k->id }})"
                            class="px-2.5 py-1 rounded-lg bg-up-primary/10 hover:bg-up-primary/20 text-up-primary border border-up-primary/25 font-bold text-[10px] transition cursor-pointer whitespace-nowrap"
                            title="Lihat Riwayat & Timeline Karir Karyawan"
                        >
                            Riwayat
                        </button>

                        <button
                            type="button"
                            wire:click="openEditModal({{ $k->id }})"
                            class="px-2.5 py-1 rounded-lg bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 border border-black/10 dark:border-white/10 text-ink-700 dark:text-ink-200 hover:text-ink-900 dark:hover:text-white font-bold text-[10px] transition cursor-pointer"
                            title="Edit Data Karyawan"
                        >
                            Edit
                        </button>

                        @if($k->status_aktif)
                            <button
                                type="button"
                                wire:click="openTerminationModal({{ $k->id }})"
                                class="px-2.5 py-1 rounded-lg bg-up-red/10 hover:bg-up-red/20 text-up-red border border-up-red/25 font-bold text-[10px] transition cursor-pointer whitespace-nowrap"
                                title="Proses Pemutusan Hubungan Kerja (Offboarding)"
                            >
                                Pemutusan
                            </button>
                        @else
                            <button
                                type="button"
                                wire:click="rehireKaryawan({{ $k->id }})"
                                class="px-2.5 py-1 rounded-lg bg-up-mint/10 hover:bg-up-mint/20 text-up-mint border border-up-mint/25 font-bold text-[10px] transition cursor-pointer whitespace-nowrap"
                                title="Aktifkan Kembali Karyawan (Re-hire)"
                            >
                                Aktifkan Lagi
                            </button>
                        @endif

                        @if($k->user_id)
                            <button
                                type="button"
                                wire:click="unlinkUser({{ $k->id }})"
                                wire:confirm="Lepas kaitan akun login user '{{ $k->user?->name }}' dari karyawan ini?"
                                class="px-2 py-1 text-ink-400 hover:text-up-amber text-[10px] font-semibold"
                                title="Lepas tautan user"
                            >
                                Lepas Akun
                            </button>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="py-12 text-center text-ink-400">
                    Tidak ditemukan data karyawan yang sesuai dengan kriteria filter.
                </td>
            </tr>
        @endforelse

        <x-slot:pagination>
            {{ $karyawans->links() }}
        </x-slot:pagination>
    </x-prism.data-table>

    <!-- ═══ MODAL REKRUT / EDIT KARYAWAN ═══ -->
    @if($showKaryawanModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 overflow-y-auto">
            <x-prism.glass-card
                :title="$isEditMode ? 'Edit Data Karyawan' : 'Onboarding / Rekrut Karyawan Baru'"
                :subtitle="$isEditMode ? 'Perbarui informasi identitas, penugasan cabang, dan gaji' : 'Masukkan informasi karyawan dan konfigurasi akun login aplikasi'"
                class="w-full max-w-2xl my-auto"
            >
                <form wire:submit.prevent="saveKaryawan" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <!-- NIK -->
                        <div>
                            <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1.5">Nomor Induk Karyawan (NIK) *</label>
                            <input
                                type="text"
                                wire:model="karyawanForm.nik"
                                class="w-full px-3 py-2 rounded-xl glass-input text-xs font-mono font-medium"
                                placeholder="EMP-0001"
                            />
                            @error('karyawanForm.nik') <p class="text-[11px] text-up-red font-medium mt-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- Nama Lengkap -->
                        <div>
                            <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1.5">Nama Lengkap *</label>
                            <input
                                type="text"
                                wire:model="karyawanForm.nama"
                                class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium"
                                placeholder="Contoh: Budi Santoso"
                            />
                            @error('karyawanForm.nama') <p class="text-[11px] text-up-red font-medium mt-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- Kontak Email -->
                        <div>
                            <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1.5">Email Karyawan</label>
                            <input
                                type="email"
                                wire:model="karyawanForm.email"
                                class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium"
                                placeholder="budi@uteparts.id"
                            />
                            @error('karyawanForm.email') <p class="text-[11px] text-up-red font-medium mt-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- Kontak Telepon -->
                        <div>
                            <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1.5">Nomor Telepon / WhatsApp</label>
                            <input
                                type="text"
                                wire:model="karyawanForm.telepon"
                                class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium"
                                placeholder="08123456789"
                            />
                            @error('karyawanForm.telepon') <p class="text-[11px] text-up-red font-medium mt-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- Jabatan -->
                        <div>
                            <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1.5">Jabatan / Posisi *</label>
                            <select wire:model="karyawanForm.jabatan" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium">
                                <option value="teknisi">Teknisi (Perbaikan &amp; Servis)</option>
                                <option value="kasir">Kasir (POS &amp; Toko)</option>
                                <option value="admin">Admin / Staff Gudang</option>
                                <option value="marketing">Marketing / Sales</option>
                                <option value="manager">Manager / Kepala Cabang</option>
                                <option value="staff">Staff Umum</option>
                                <option value="other">Lainnya</option>
                            </select>
                            @error('karyawanForm.jabatan') <p class="text-[11px] text-up-red font-medium mt-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- Cabang Penugasan -->
                        <div>
                            <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1.5">Cabang Penempatan *</label>
                            <select wire:model="karyawanForm.cabang_id" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium">
                                @foreach($daftarCabang as $cb)
                                    <option value="{{ $cb->id }}">{{ $cb->nama }}</option>
                                @endforeach
                            </select>
                            @error('karyawanForm.cabang_id') <p class="text-[11px] text-up-red font-medium mt-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- Tanggal Masuk -->
                        <div>
                            <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1.5">Tanggal Mulai Bekerja *</label>
                            <input
                                type="date"
                                wire:model="karyawanForm.tgl_masuk"
                                class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium"
                            />
                            @error('karyawanForm.tgl_masuk') <p class="text-[11px] text-up-red font-medium mt-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- Gaji Pokok -->
                        <div>
                            <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1.5">Gaji Pokok Bulanan (Rp)</label>
                            <input
                                type="number"
                                min="0"
                                wire:model="karyawanForm.gaji_pokok"
                                class="w-full px-3 py-2 rounded-xl glass-input text-xs font-mono font-medium"
                                placeholder="0"
                            />
                            @error('karyawanForm.gaji_pokok') <p class="text-[11px] text-up-red font-medium mt-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- Rekening Bank -->
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1.5">Informasi Rekening Bank (Payroll Transfer)</label>
                            <input
                                type="text"
                                wire:model="karyawanForm.rekening_bank"
                                class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium"
                                placeholder="Contoh: BCA - 1234567890 a/n Budi Santoso"
                            />
                        </div>
                    </div>

                    <!-- ═══ OPSI INTEGRASI USER LOGIN (Khusus Onboarding Baru) ═══ -->
                    @if(! $isEditMode)
                        <div class="pt-3 border-t border-black/10 dark:border-white/10 space-y-3">
                            <div class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="toggleBuatUser"
                                    wire:model.live="karyawanForm.buat_user"
                                    class="w-4 h-4 rounded text-up-primary focus:ring-up-primary cursor-pointer"
                                />
                                <label for="toggleBuatUser" class="text-xs font-bold text-ink-900 dark:text-white cursor-pointer">
                                    Buatkan Akun Login Pengguna untuk Karyawan Ini Sekarang
                                </label>
                            </div>

                            @if($karyawanForm['buat_user'])
                                <div class="p-3.5 rounded-xl border border-up-primary/20 bg-up-primary/5 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1">Password Akun Login *</label>
                                        <input
                                            type="password"
                                            wire:model="karyawanForm.user_password"
                                            class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium"
                                            placeholder="Minimal 6 karakter"
                                        />
                                        @error('karyawanForm.user_password') <p class="text-[11px] text-up-red font-medium mt-1">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1">Role / Hak Akses Sistem *</label>
                                        <select wire:model="karyawanForm.user_role" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium">
                                            @foreach($roles as $role)
                                                <option value="{{ $role }}">{{ ucfirst($role) }}</option>
                                            @endforeach
                                        </select>
                                        @error('karyawanForm.user_role') <p class="text-[11px] text-up-red font-medium mt-1">{{ $message }}</p> @enderror
                                    </div>
                                    <p class="sm:col-span-2 text-[10px] text-ink-500">
                                        Email login akan menggunakan alamat email yang diinput pada data kontak di atas. Akun otomatis memiliki akses ke cabang penempatan.
                                    </p>
                                </div>
                            @endif
                        </div>
                    @endif

                    <!-- Tombol Aksi Modal -->
                    <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-black/10 dark:border-white/10">
                        <button
                            type="button"
                            wire:click="$set('showKaryawanModal', false)"
                            class="px-4 py-2.5 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 font-bold text-xs cursor-pointer transition min-h-[42px]"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="px-5 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs shadow-md shadow-up-primary/25 cursor-pointer transition min-h-[42px]"
                        >
                            {{ $isEditMode ? 'Simpan Perubahan' : 'Selesaikan Rekrutmen' }}
                        </button>
                    </div>
                </form>
            </x-prism.glass-card>
        </div>
    @endif

    <!-- ═══ MODAL PEMUTUSAN HUBUNGAN KERJA (OFFBOARDING) ═══ -->
    @if($showTerminationModal && $karyawanToTerminate)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-sm p-4">
            <x-prism.glass-card
                title="Pemutusan Hubungan Kerja (Offboarding)"
                subtitle="Penonaktifan karyawan dan pencabutan hak akses sistem"
                class="w-full max-w-lg border-up-red/30"
            >
                <form wire:submit.prevent="executeTermination" class="space-y-4">
                    <div class="p-3.5 rounded-xl border border-up-red/25 bg-up-red/10 space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-up-red"></span>
                            <span class="text-xs font-bold text-up-red">Perhatian Keamanan &amp; Integritas Akses:</span>
                        </div>
                        <ul class="text-[11px] text-ink-700 dark:text-ink-200 list-disc list-inside space-y-1">
                            <li>Karyawan <strong>{{ $karyawanToTerminate->nama }}</strong> ({{ $karyawanToTerminate->nik }}) akan dinonaktifkan dari absensi &amp; payroll.</li>
                            @if($karyawanToTerminate->user)
                                <li>Akun login <strong>{{ $karyawanToTerminate->user->email }}</strong> otomatis dinonaktifkan (<code>is_active = false</code>).</li>
                                <li>Seluruh token API Sanctum dan sesi login aktif akan <strong>seketika dicabut (force logout)</strong> untuk mencegah akses tidak sah.</li>
                            @endif
                            <li>Histori slip gaji, log absensi, dan riwayat komisi masa lalu tetap tersimpan utuh sebagai arsip audit.</li>
                        </ul>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1.5">Tanggal Efektif Berhenti / Keluar *</label>
                            <input
                                type="date"
                                wire:model="terminationForm.tgl_keluar"
                                class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium"
                            />
                            @error('terminationForm.tgl_keluar') <p class="text-[11px] text-up-red font-medium mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1.5">Alasan Pemutusan *</label>
                            <select wire:model="terminationForm.alasan_keluar" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium">
                                <option value="resign">Pengunduran Diri (Resign)</option>
                                <option value="phk">Pemutusan Hubungan Kerja (PHK)</option>
                                <option value="habis_kontrak">Habis Masa Kontrak Kerja</option>
                                <option value="mutasi">Mutasi / Rotasi Eksternal</option>
                                <option value="pensiun">Pensiun</option>
                                <option value="pelanggaran">Pelanggaran Disiplin / SOP</option>
                                <option value="lainnya">Lainnya</option>
                            </select>
                            @error('terminationForm.alasan_keluar') <p class="text-[11px] text-up-red font-medium mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1.5">Catatan / Berita Acara Pemutusan</label>
                            <textarea
                                wire:model="terminationForm.catatan_keluar"
                                rows="3"
                                class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium"
                                placeholder="Tuliskan keterangan serah terima aset, inventaris toko, atau catatan offboarding..."
                            ></textarea>
                            @error('terminationForm.catatan_keluar') <p class="text-[11px] text-up-red font-medium mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-black/10 dark:border-white/10">
                        <button
                            type="button"
                            wire:click="$set('showTerminationModal', false)"
                            class="px-4 py-2.5 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 font-bold text-xs cursor-pointer transition min-h-[42px]"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="px-5 py-2.5 rounded-xl bg-up-red hover:bg-up-red/90 text-white font-bold text-xs shadow-md shadow-up-red/25 cursor-pointer transition min-h-[42px]"
                        >
                            Konfirmasi Pemutusan Karyawan
                        </button>
                    </div>
                </form>
            </x-prism.glass-card>
        </div>
    @endif

    <!-- ═══ MODAL TAUTKAN AKUN USER EXISTING ═══ -->
    @if($showLinkUserModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <x-prism.glass-card
                title="Tautkan Akun Login Pengguna"
                subtitle="Hubungkan profil karyawan ke akun pengguna sistem yang sudah ada"
                class="w-full max-w-md"
            >
                <form wire:submit.prevent="saveLinkUser" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1.5">Pilih Akun Pengguna *</label>
                        <select wire:model="linkUserId" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            <option value="">-- Pilih Akun User --</option>
                            @foreach($unlinkedUsers as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                        @error('linkUserId') <p class="text-[11px] text-up-red font-medium mt-1">{{ $message }}</p> @enderror
                    </div>

                    <p class="text-[11px] text-ink-500">
                        Hanya akun pengguna yang belum terhubung dengan karyawan lain yang ditampilkan.
                    </p>

                    <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-black/10 dark:border-white/10">
                        <button
                            type="button"
                            wire:click="$set('showLinkUserModal', false)"
                            class="px-4 py-2 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 font-bold text-xs cursor-pointer transition"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs shadow-md shadow-up-primary/25 cursor-pointer transition"
                        >
                            Tautkan Akun
                        </button>
                    </div>
                </form>
            </x-prism.glass-card>
        </div>
    @endif

    <!-- ═══ MODAL RIWAYAT / TIMELINE KARYAWAN ═══ -->
    @if($showHistoryModal && $historyKaryawan)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 overflow-y-auto">
            <div class="w-full max-w-3xl my-auto p-5 sm:p-6 rounded-2xl glass-panel border border-black/10 dark:border-white/10 bg-white/95 dark:bg-ink-950/95 shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto">
                <!-- Header Riwayat -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-black/10 dark:border-white/10 pb-4">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-lg font-bold text-ink-900 dark:text-white">Riwayat Karir &amp; Peristiwa Karyawan</h2>
                            <span class="text-[11px] font-mono font-bold px-2 py-0.5 rounded-full bg-up-primary/10 text-up-primary border border-up-primary/20">
                                {{ $historyKaryawan->nik ?: 'Tanpa NIK' }}
                            </span>
                        </div>
                        <p class="text-xs text-ink-600 dark:text-ink-400 mt-1">
                            <strong class="text-ink-900 dark:text-white">{{ $historyKaryawan->nama }}</strong> • 
                            <span class="capitalize">{{ $historyKaryawan->jabatan }}</span> • 
                            <span>{{ $historyKaryawan->cabang?->nama ?? 'Semua Cabang' }}</span> • 
                            @if($historyKaryawan->status_aktif)
                                <span class="text-up-mint font-semibold">Aktif Bekerja</span>
                            @else
                                <span class="text-up-red font-semibold">Nonaktif</span>
                            @endif
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        @if(! $showAddHistoryForm)
                            <button
                                type="button"
                                wire:click="showCreateHistory"
                                class="px-3 py-1.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs flex items-center gap-1.5 shadow-sm shadow-up-primary/25 cursor-pointer transition min-h-[36px]"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                <span>+ Catat Riwayat</span>
                            </button>
                        @else
                            <button
                                type="button"
                                wire:click="$set('showAddHistoryForm', false)"
                                class="px-3 py-1.5 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 font-bold text-xs cursor-pointer transition min-h-[36px]"
                            >
                                Tutup Form
                            </button>
                        @endif

                        <button
                            type="button"
                            wire:click="$set('showHistoryModal', false)"
                            class="p-2 rounded-xl text-ink-400 hover:text-ink-700 dark:hover:text-white hover:bg-black/5 dark:hover:bg-white/5 cursor-pointer transition"
                            title="Tutup Modal"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                </div>

                <!-- ═══ FORM TAMBAH / EDIT RIWAYAT ═══ -->
                @if($showAddHistoryForm)
                    <div class="p-4 sm:p-5 rounded-2xl border border-up-primary/25 bg-up-primary/5 space-y-4">
                        <div class="flex items-center justify-between border-b border-up-primary/15 pb-2">
                            <h3 class="text-xs font-bold text-up-primary uppercase tracking-wider">
                                {{ $editingHistoryId ? 'Edit Catatan Riwayat' : 'Tambah Peristiwa / Catatan Riwayat Baru' }}
                            </h3>
                            <span class="text-[10px] text-ink-500">* Wajib diisi</span>
                        </div>

                        <form wire:submit.prevent="saveHistory" class="space-y-3.5">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <!-- Tipe Event -->
                                <div>
                                    <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1">Jenis Peristiwa / Event *</label>
                                    <select wire:model.live="historyForm.tipe_event" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium">
                                        <option value="catatan">Catatan Umum / Review</option>
                                        <option value="promosi">Promosi / Kenaikan Jabatan</option>
                                        <option value="mutasi">Mutasi / Rotasi Cabang</option>
                                        <option value="perubahan_gaji">Penyesuaian Gaji Pokok</option>
                                        <option value="peringatan">Surat Peringatan (SP) / Disiplin</option>
                                        <option value="penghargaan">Penghargaan / Prestasi</option>
                                        <option value="rekrut">Perekrutan Awal</option>
                                        <option value="terminasi">Pemutusan / Resign</option>
                                        <option value="rehire">Re-hire (Aktif Kembali)</option>
                                    </select>
                                    @error('historyForm.tipe_event') <p class="text-[11px] text-up-red mt-1">{{ $message }}</p> @enderror
                                </div>

                                <!-- Tanggal Efektif -->
                                <div>
                                    <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1">Tanggal Efektif Peristiwa *</label>
                                    <input
                                        type="date"
                                        wire:model="historyForm.tanggal_efektif"
                                        class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium"
                                    />
                                    @error('historyForm.tanggal_efektif') <p class="text-[11px] text-up-red mt-1">{{ $message }}</p> @enderror
                                </div>

                                <!-- Judul Peristiwa -->
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1">Judul Ringkasan *</label>
                                    <input
                                        type="text"
                                        wire:model="historyForm.judul"
                                        class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium"
                                        placeholder="Contoh: Kenaikan Jabatan Senior Teknisi / SP 1 Keterlambatan"
                                    />
                                    @error('historyForm.judul') <p class="text-[11px] text-up-red mt-1">{{ $message }}</p> @enderror
                                </div>

                                <!-- Dokumen Referensi / No SK -->
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1">Nomor Dokumen / SK / Surat Tugas</label>
                                    <input
                                        type="text"
                                        wire:model="historyForm.dokumen_referensi"
                                        class="w-full px-3 py-2 rounded-xl glass-input text-xs font-mono font-medium"
                                        placeholder="Contoh: SK-DIR/2026/X/012 atau SP-01/HR/2026"
                                    />
                                </div>

                                <!-- Deskripsi / Keterangan -->
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1">Deskripsi Lengkap &amp; Catatan Evaluasi</label>
                                    <textarea
                                        wire:model="historyForm.deskripsi"
                                        rows="3"
                                        class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium"
                                        placeholder="Tuliskan alasan, catatan penilaian kinerja, detail sanksi, atau instruksi mutasi..."
                                    ></textarea>
                                </div>

                                <!-- Field Dinamis sesuai tipe event -->
                                @if(in_array($historyForm['tipe_event'], ['promosi', 'mutasi']))
                                    <div>
                                        <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1">Jabatan Baru (Opsional)</label>
                                        <select wire:model="historyForm.jabatan_baru" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium">
                                            <option value="">-- Tetap / Tidak Berubah --</option>
                                            <option value="teknisi">Teknisi</option>
                                            <option value="kasir">Kasir</option>
                                            <option value="admin">Admin</option>
                                            <option value="marketing">Marketing</option>
                                            <option value="manager">Manager</option>
                                            <option value="staff">Staff</option>
                                            <option value="other">Lainnya</option>
                                        </select>
                                    </div>
                                @endif

                                @if($historyForm['tipe_event'] === 'mutasi')
                                    <div>
                                        <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1">Cabang Tujuan Baru (Opsional)</label>
                                        <select wire:model="historyForm.cabang_baru_id" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium">
                                            <option value="">-- Tetap / Tidak Berubah --</option>
                                            @foreach($daftarCabang as $cb)
                                                <option value="{{ $cb->id }}">{{ $cb->nama }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                @if(in_array($historyForm['tipe_event'], ['perubahan_gaji', 'promosi']))
                                    <div>
                                        <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1">Nominal Gaji Pokok Baru (Rp)</label>
                                        <input
                                            type="number"
                                            min="0"
                                            wire:model="historyForm.gaji_baru"
                                            class="w-full px-3 py-2 rounded-xl glass-input text-xs font-mono font-medium"
                                            placeholder="Contoh: 5000000"
                                        />
                                    </div>
                                @endif
                            </div>

                            <div class="flex items-center justify-end gap-2 pt-3 border-t border-up-primary/15">
                                <button
                                    type="button"
                                    wire:click="$set('showAddHistoryForm', false)"
                                    class="px-3.5 py-1.5 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 font-bold text-xs cursor-pointer transition min-h-[38px]"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    class="px-4 py-1.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs shadow-md shadow-up-primary/25 cursor-pointer transition min-h-[38px]"
                                >
                                    {{ $editingHistoryId ? 'Perbarui Riwayat' : 'Simpan Riwayat' }}
                                </button>
                            </div>
                        </form>
                    </div>
                @endif

                <!-- ═══ VISUAL TIMELINE RIWAYAT ═══ -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider">
                            Timeline Perjalanan Karyawan ({{ $historyKaryawan->riwayats->count() }} Peristiwa)
                        </span>
                        <span class="text-[11px] text-ink-400">Terurut dari yang terbaru</span>
                    </div>

                    @if($historyKaryawan->riwayats->isEmpty())
                        <div class="p-8 text-center rounded-2xl border border-dashed border-black/15 dark:border-white/10 space-y-2">
                            <svg class="w-8 h-8 mx-auto text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <p class="text-xs text-ink-500 font-medium">Belum ada catatan riwayat atau peristiwa karir untuk karyawan ini.</p>
                            <button
                                type="button"
                                wire:click="showCreateHistory"
                                class="text-xs text-up-primary font-bold hover:underline cursor-pointer"
                            >
                                + Tambahkan catatan riwayat pertama
                            </button>
                        </div>
                    @else
                        <div class="relative pl-6 sm:pl-8 space-y-6 before:content-[''] before:absolute before:left-3 sm:before:left-4 before:top-2 before:bottom-2 before:w-0.5 before:bg-black/10 dark:before:bg-white/10">
                            @foreach($historyKaryawan->riwayats as $item)
                                @php
                                    $themeMap = match($item->tipe_event) {
                                        'rekrut' => ['dot' => 'bg-up-mint', 'badge' => 'bg-up-mint/10 text-up-mint border-up-mint/25', 'label' => 'Perekrutan'],
                                        'promosi' => ['dot' => 'bg-up-primary', 'badge' => 'bg-up-primary/10 text-up-primary border-up-primary/25', 'label' => 'Promosi'],
                                        'mutasi' => ['dot' => 'bg-up-amber', 'badge' => 'bg-up-amber/10 text-up-amber border-up-amber/25', 'label' => 'Mutasi'],
                                        'perubahan_gaji' => ['dot' => 'bg-blue-500', 'badge' => 'bg-blue-500/10 text-blue-400 border-blue-500/25', 'label' => 'Gaji Pokok'],
                                        'peringatan' => ['dot' => 'bg-up-red', 'badge' => 'bg-up-red/10 text-up-red border-up-red/25', 'label' => 'Peringatan / SP'],
                                        'penghargaan' => ['dot' => 'bg-yellow-400', 'badge' => 'bg-yellow-400/10 text-yellow-500 border-yellow-400/25', 'label' => 'Penghargaan'],
                                        'terminasi' => ['dot' => 'bg-up-red', 'badge' => 'bg-up-red/10 text-up-red border-up-red/25', 'label' => 'Pemutusan'],
                                        'rehire' => ['dot' => 'bg-teal-400', 'badge' => 'bg-teal-400/10 text-teal-400 border-teal-400/25', 'label' => 'Re-hire'],
                                        default => ['dot' => 'bg-ink-400', 'badge' => 'bg-black/5 dark:bg-white/5 text-ink-600 dark:text-ink-400 border-black/10 dark:border-white/10', 'label' => 'Catatan']
                                    };
                                @endphp

                                <div class="relative group">
                                    <!-- Dot Timeline -->
                                    <span class="absolute -left-6 sm:-left-8 top-1.5 w-3 h-3 rounded-full {{ $themeMap['dot'] }} ring-4 ring-white dark:ring-ink-950"></span>

                                    <!-- Content Card Peristiwa -->
                                    <div class="p-4 rounded-xl border border-black/10 dark:border-white/10 bg-black/[0.01] dark:bg-white/[0.02] hover:bg-black/[0.02] dark:hover:bg-white/[0.03] transition space-y-2.5">
                                        <!-- Header Item: Event badge, Judul, Tanggal, dan Aksi Edit/Delete -->
                                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase border {{ $themeMap['badge'] }}">
                                                    {{ $themeMap['label'] }}
                                                </span>
                                                <h4 class="text-xs font-bold text-ink-900 dark:text-white">{{ $item->judul }}</h4>
                                                @if($item->dokumen_referensi)
                                                    <span class="text-[10px] font-mono text-ink-500 bg-black/5 dark:bg-white/5 px-1.5 py-0.5 rounded">
                                                        Doc: {{ $item->dokumen_referensi }}
                                                    </span>
                                                @endif
                                            </div>

                                            <div class="flex items-center gap-2 self-end sm:self-center">
                                                <span class="text-[11px] font-mono text-ink-500 font-medium">
                                                    {{ $item->tanggal_efektif ? $item->tanggal_efektif->format('d/m/Y') : '-' }}
                                                </span>

                                                <!-- Action Buttons: Edit & Delete -->
                                                <div class="flex items-center gap-1 opacity-90 sm:opacity-0 group-hover:opacity-100 transition">
                                                    <button
                                                        type="button"
                                                        wire:click="editHistory({{ $item->id }})"
                                                        class="px-2 py-0.5 rounded text-[10px] font-bold bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 text-ink-700 dark:text-ink-200 cursor-pointer"
                                                        title="Edit Riwayat Ini"
                                                    >
                                                        Edit
                                                    </button>
                                                    <button
                                                        type="button"
                                                        wire:click="deleteHistory({{ $item->id }})"
                                                        wire:confirm="Hapus catatan peristiwa '{{ $item->judul }}' dari timeline karyawan ini?"
                                                        class="px-2 py-0.5 rounded text-[10px] font-bold bg-up-red/10 hover:bg-up-red/20 text-up-red cursor-pointer"
                                                        title="Hapus Riwayat Ini"
                                                    >
                                                        Hapus
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Deskripsi Peristiwa -->
                                        @if($item->deskripsi)
                                            <p class="text-xs text-ink-600 dark:text-ink-300 leading-relaxed whitespace-pre-line">
                                                {{ $item->deskripsi }}
                                            </p>
                                        @endif

                                        <!-- Snapshot Perubahan Parameter -->
                                        @if($item->jabatan_lama || $item->jabatan_baru || $item->cabang_lama_id || $item->cabang_baru_id || $item->gaji_lama || $item->gaji_baru)
                                            <div class="pt-2 border-t border-black/5 dark:border-white/5 flex items-center gap-3 flex-wrap text-[11px]">
                                                @if($item->jabatan_lama || $item->jabatan_baru)
                                                    <div class="flex items-center gap-1 text-ink-600 dark:text-ink-300">
                                                        <span class="font-semibold text-ink-400">Jabatan:</span>
                                                        <span class="capitalize">{{ $item->jabatan_lama ?: '-' }}</span>
                                                        <span>&rarr;</span>
                                                        <strong class="capitalize text-ink-900 dark:text-white">{{ $item->jabatan_baru ?: '-' }}</strong>
                                                    </div>
                                                @endif

                                                @if($item->cabang_lama_id || $item->cabang_baru_id)
                                                    <div class="flex items-center gap-1 text-ink-600 dark:text-ink-300">
                                                        <span class="font-semibold text-ink-400">Cabang:</span>
                                                        <span>{{ $item->cabangLama?->nama ?: '-' }}</span>
                                                        <span>&rarr;</span>
                                                        <strong class="text-ink-900 dark:text-white">{{ $item->cabangBaru?->nama ?: '-' }}</strong>
                                                    </div>
                                                @endif

                                                @if($item->gaji_lama || $item->gaji_baru)
                                                    <div class="flex items-center gap-1 text-ink-600 dark:text-ink-300 font-mono">
                                                        <span class="font-semibold text-ink-400 font-sans">Gaji Pokok:</span>
                                                        <span>Rp {{ number_format((float) $item->gaji_lama, 0, ',', '.') }}</span>
                                                        <span>&rarr;</span>
                                                        <strong class="text-ink-900 dark:text-white">Rp {{ number_format((float) $item->gaji_baru, 0, ',', '.') }}</strong>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif

                                        <!-- Footer Item: Dicatat oleh -->
                                        <div class="text-[10px] text-ink-400 flex items-center justify-between">
                                            <span>Dicatat oleh: <strong>{{ $item->author?->name ?? 'Sistem' }}</strong></span>
                                            <span>{{ $item->created_at ? $item->created_at->diffForHumans() : '' }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Footer Modal -->
                <div class="flex items-center justify-end pt-4 border-t border-black/10 dark:border-white/10">
                    <button
                        type="button"
                        wire:click="$set('showHistoryModal', false)"
                        class="px-5 py-2.5 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 font-bold text-xs cursor-pointer transition min-h-[40px]"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
