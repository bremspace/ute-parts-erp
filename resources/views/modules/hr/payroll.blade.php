<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    {{-- Top Header & Quick Links --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-ink-900 dark:text-white">HR &amp; Payroll Management</h1>
            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Penggajian terpadu, master kompensasi karyawan, tunjangan, potongan, dan posting jurnal double-entry otomatis.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="/app/hr/komisi-skema" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl border border-amber-500/30 text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/30 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span>Skema Komisi</span>
            </a>
            <a href="/app/hr/absensi" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/30 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                <span>Absensi &amp; KPI</span>
            </a>
            <button wire:click="bukaBuatPeriode"
                class="inline-flex items-center gap-1.5 px-4 py-2 border border-transparent text-sm font-semibold rounded-xl shadow-sm text-white bg-up-primary hover:bg-up-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-up-primary active:scale-[0.98] transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                <span>Buat Periode Baru</span>
            </button>
        </div>
    </div>

    {{-- Navigation Tabs --}}
    <div class="flex items-center gap-3 border-b border-ink-200 dark:border-white/10 pb-3">
        <button wire:click="setTab('payroll')"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition {{ $activeTab === 'payroll' ? 'bg-up-primary text-white shadow-md shadow-up-primary/30' : 'text-ink-600 dark:text-ink-400 hover:bg-black/5 dark:hover:bg-white/5' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" /></svg>
            <span>Pemrosesan Payroll &amp; Slip</span>
        </button>
        <button wire:click="setTab('karyawan')"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition {{ $activeTab === 'karyawan' ? 'bg-up-primary text-white shadow-md shadow-up-primary/30' : 'text-ink-600 dark:text-ink-400 hover:bg-black/5 dark:hover:bg-white/5' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
            <span>Pengaturan Gaji &amp; Komponen Karyawan</span>
        </button>
    </div>

    {{-- TAB 1: PEMROSESAN PAYROLL --}}
    @if($activeTab === 'payroll')
        {{-- Periode Selector & Pipeline Controls --}}
        <div class="glass-card p-5 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-3 border-b border-ink-100 dark:border-white/10 gap-2">
                <div class="flex items-center gap-2">
                    <span class="text-xs uppercase font-bold text-ink-500">Status Periode {{ $periode }}:</span>
                    @php
                        $pStatus = $periodeRecord?->status ?? 'draft';
                        $pBadge = match($pStatus) {
                            'draft' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-950/40 dark:text-yellow-300',
                            'selesai' => 'bg-blue-100 text-blue-800 dark:bg-blue-950/40 dark:text-blue-300',
                            'dibayar' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
                            default => 'bg-gray-100 text-gray-800'
                        };
                    @endphp
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-bold rounded-full {{ $pBadge }}">
                        {{ strtoupper($pStatus) }}
                    </span>
                    @if($approvalRequest)
                        <span class="text-xs text-ink-400">|</span>
                        <span class="text-xs text-ink-500">Approval Engine:</span>
                        <span class="inline-flex px-2 py-0.5 text-[11px] font-semibold rounded-full {{ $approvalRequest->status === 'disetujui' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                            {{ ucfirst($approvalRequest->status) }}
                        </span>
                    @endif
                </div>
                <button wire:click="bukaBuatPeriode" class="text-xs font-semibold text-up-primary hover:underline self-start sm:self-auto">
                    + Buat Periode Baru
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400 mb-1.5">Pilih Periode Penggajian</label>
                    <select wire:model.live="periode"
                        class="block w-full rounded-xl border-ink-300 dark:border-white/10 dark:bg-black/40 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-up-primary text-sm min-h-[44px]">
                        <option value="">-- Pilih Periode --</option>
                        @foreach($periodeList as $id => $label)
                            <option value="{{ $label }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400 mb-1.5">Filter Status Slip</label>
                    <select wire:model.live="statusFilter"
                        class="block w-full rounded-xl border-ink-300 dark:border-white/10 dark:bg-black/40 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-up-primary text-sm min-h-[44px]">
                        <option value="semua">Semua Status</option>
                        <option value="draft">Draft</option>
                        <option value="approved">Approved / Disetujui</option>
                        <option value="dibayar">Dibayar</option>
                    </select>
                </div>
                <div class="flex items-center gap-2 pt-2 sm:pt-0">
                    <button wire:click="hitungDraft"
                        @if(($periodeRecord?->status ?? '') === 'dibayar') disabled @endif
                        class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2.5 text-xs font-semibold rounded-xl text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed min-h-[44px] shadow-sm transition active:scale-[0.98]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                        <span>Hitung Draft</span>
                    </button>
                    <button wire:click="bukaApproveModal"
                        @if(($periodeRecord?->status ?? '') === 'dibayar') disabled @endif
                        class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2.5 text-xs font-semibold rounded-xl text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed min-h-[44px] shadow-sm transition active:scale-[0.98]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>Approval / Jurnal</span>
                    </button>
                    <button wire:click="bukaBayarModal"
                        @if(($periodeRecord?->status ?? '') === 'dibayar') disabled @endif
                        class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2.5 text-xs font-semibold rounded-xl text-white bg-purple-600 hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed min-h-[44px] shadow-sm transition active:scale-[0.98]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        <span>Bayar Payroll</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Ringkasan Total & Jurnal --}}
        @if($totalGaji > 0)
            <div class="glass-card p-5 border-l-4 border-up-primary flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <span class="text-xs uppercase font-semibold text-ink-500 dark:text-ink-400">Total Pengeluaran Payroll Periode {{ $periode }}</span>
                    <p class="text-2xl font-bold tabular-nums text-up-primary">Rp {{ number_format($totalGaji, 0, ',', '.') }}</p>
                    <span class="text-xs text-ink-500">Mencakup {{ count($slips) }} karyawan (Gaji Pokok, Tunjangan, Komisi, dipotong Disiplin &amp; Kasbon)</span>
                </div>
                <div class="text-xs text-ink-500 space-y-1 bg-black/5 dark:bg-white/5 p-3 rounded-xl">
                    <p class="font-medium text-ink-800 dark:text-ink-200">Kesesuaian Akuntansi Jurnal:</p>
                    <p>• Finalisasi: <span class="font-mono text-ink-700 dark:text-ink-300">D 520-01, D 520-09 / K 210-02 (Utang Gaji)</span></p>
                    <p>• Pembayaran: <span class="font-mono text-ink-700 dark:text-ink-300">D 210-02 / K 110-01 (Kas/Bank)</span></p>
                </div>
            </div>
        @endif

        {{-- Tabel Slip Gaji --}}
        <div class="glass-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-ink-200 dark:divide-white/10">
                    <thead class="bg-black/5 dark:bg-white/5">
                        <tr>
                            <th class="px-5 py-3.5 text-left text-xs font-semibold text-ink-600 dark:text-ink-300 uppercase tracking-wider">Karyawan</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold text-ink-600 dark:text-ink-300 uppercase tracking-wider">Jabatan &amp; Cabang</th>
                            <th class="px-4 py-3.5 text-right text-xs font-semibold text-ink-600 dark:text-ink-300 uppercase tracking-wider">Gaji Pokok</th>
                            <th class="px-4 py-3.5 text-right text-xs font-semibold text-ink-600 dark:text-ink-300 uppercase tracking-wider">Tunjangan</th>
                            <th class="px-4 py-3.5 text-right text-xs font-semibold text-ink-600 dark:text-ink-300 uppercase tracking-wider">Potongan</th>
                            <th class="px-4 py-3.5 text-right text-xs font-semibold text-ink-600 dark:text-ink-300 uppercase tracking-wider">Komisi</th>
                            <th class="px-4 py-3.5 text-right text-xs font-semibold text-ink-600 dark:text-ink-300 uppercase tracking-wider">Net Gaji</th>
                            <th class="px-4 py-3.5 text-center text-xs font-semibold text-ink-600 dark:text-ink-300 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3.5 text-center text-xs font-semibold text-ink-600 dark:text-ink-300 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100 dark:divide-white/5 text-sm">
                        @forelse($slips as $slip)
                            <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition">
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <div class="font-semibold text-ink-900 dark:text-white">{{ $slip['karyawan_nama'] }}</div>
                                    <div class="text-xs text-ink-400 font-mono">{{ $slip['karyawan_nik'] }}</div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-ink-800 dark:text-ink-200 capitalize">{{ $slip['karyawan_jabatan'] }}</div>
                                    <div class="text-xs text-ink-400">{{ $slip['karyawan_cabang'] }}</div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-right tabular-nums text-ink-900 dark:text-white">
                                    Rp {{ number_format($slip['gaji_pokok'], 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-right tabular-nums text-emerald-600 dark:text-emerald-400">
                                    +Rp {{ number_format($slip['total_tunjangan'], 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-right tabular-nums text-red-600 dark:text-red-400">
                                    -Rp {{ number_format($slip['total_potongan'], 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-right tabular-nums text-amber-600 dark:text-amber-400">
                                    +Rp {{ number_format($slip['total_komisi'], 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-right tabular-nums font-bold text-up-primary">
                                    Rp {{ number_format($slip['total_gaji'], 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-center">
                                    @php
                                        $badgeClass = match($slip['status']) {
                                            'draft' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-950/40 dark:text-yellow-300',
                                            'approved', 'disetujui' => 'bg-blue-100 text-blue-800 dark:bg-blue-950/40 dark:text-blue-300',
                                            'dibayar' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
                                            default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300'
                                        };
                                    @endphp
                                    <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full {{ $badgeClass }}">
                                        {{ ucfirst($slip['status']) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-center">
                                    <button wire:click="bukaDetailSlip({{ $slip['id'] }})"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg text-ink-700 dark:text-ink-200 bg-black/5 dark:bg-white/10 hover:bg-black/10 dark:hover:bg-white/20 transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        <span>Rincian</span>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-12 text-center text-sm text-ink-500">
                                    Belum ada data slip pada periode ini. Klik <strong>"Hitung Draft"</strong> untuk memproses kalkulasi gaji otomatis seluruh karyawan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- TAB 2: PENGATURAN GAJI & KOMPONEN KARYAWAN --}}
    @if($activeTab === 'karyawan')
        <div class="space-y-4">
            {{-- Search & Info Card --}}
            <div class="glass-card p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="w-full sm:w-72">
                        <div class="relative">
                            <input type="text" wire:model.live.debounce.300ms="searchKaryawan"
                                placeholder="Cari nama, NIK, jabatan..."
                                class="block w-full pl-9 pr-3 py-2 text-sm rounded-xl border-ink-300 dark:border-white/10 dark:bg-black/40 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-up-primary">
                            <svg class="w-4 h-4 text-ink-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>
                    </div>
                    <button wire:click="bukaTambahKaryawan"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-xl text-white bg-up-primary hover:bg-up-primary/90 shadow-sm transition active:scale-[0.98]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" /></svg>
                        <span>Tambah Karyawan</span>
                    </button>
                </div>
                <div class="text-xs text-ink-500 dark:text-ink-400">
                    Setiap karyawan dapat memiliki <strong>Gaji Pokok</strong> dan komponen berulang seperti <strong>Tunjangan Makan/Transport</strong>, <strong>Bonus/Insentif</strong>, atau <strong>Potongan Kasbon/BPJS</strong>.
                </div>
            </div>

            {{-- Table Karyawan Master Compensation --}}
            <div class="glass-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-ink-200 dark:divide-white/10">
                        <thead class="bg-black/5 dark:bg-white/5">
                            <tr>
                                <th class="px-5 py-3.5 text-left text-xs font-semibold text-ink-600 dark:text-ink-300 uppercase tracking-wider">Karyawan &amp; NIK</th>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold text-ink-600 dark:text-ink-300 uppercase tracking-wider">Jabatan / Cabang</th>
                                <th class="px-4 py-3.5 text-right text-xs font-semibold text-ink-600 dark:text-ink-300 uppercase tracking-wider">Gaji Pokok</th>
                                <th class="px-4 py-3.5 text-right text-xs font-semibold text-ink-600 dark:text-ink-300 uppercase tracking-wider">Tunjangan Aktif</th>
                                <th class="px-4 py-3.5 text-right text-xs font-semibold text-ink-600 dark:text-ink-300 uppercase tracking-wider">Potongan Aktif</th>
                                <th class="px-4 py-3.5 text-right text-xs font-semibold text-ink-600 dark:text-ink-300 uppercase tracking-wider">Estimasi Bersih</th>
                                <th class="px-4 py-3.5 text-center text-xs font-semibold text-ink-600 dark:text-ink-300 uppercase tracking-wider">Komponen</th>
                                <th class="px-4 py-3.5 text-center text-xs font-semibold text-ink-600 dark:text-ink-300 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100 dark:divide-white/5 text-sm">
                            @forelse($karyawans as $k)
                                <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition">
                                    <td class="px-5 py-4 whitespace-nowrap">
                                        <div class="font-semibold text-ink-900 dark:text-white">{{ $k['nama'] }}</div>
                                        <div class="text-xs text-ink-400 font-mono">{{ $k['nik'] }} • {{ $k['rekening_bank'] }}</div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-ink-800 dark:text-ink-200 capitalize">{{ $k['jabatan'] }}</div>
                                        <div class="text-xs text-ink-400">{{ $k['cabang'] }}</div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-right tabular-nums font-semibold text-ink-900 dark:text-white">
                                        Rp {{ number_format($k['gaji_pokok'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-right tabular-nums text-emerald-600 dark:text-emerald-400">
                                        +Rp {{ number_format($k['tunjangan'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-right tabular-nums text-red-600 dark:text-red-400">
                                        -Rp {{ number_format($k['potongan'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-right tabular-nums font-bold text-up-primary">
                                        Rp {{ number_format($k['estimasi_bersih'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-center">
                                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-black/5 dark:bg-white/10 text-ink-700 dark:text-ink-300">
                                            {{ $k['komponen_count'] }} item
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-center space-x-1">
                                        <button wire:click="bukaEditKaryawan({{ $k['id'] }})"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold rounded-xl text-ink-700 dark:text-ink-200 bg-black/5 dark:bg-white/10 hover:bg-black/10 dark:hover:bg-white/20 transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            <span>Edit</span>
                                        </button>
                                        <button wire:click="bukaKompensasi({{ $k['id'] }})"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-xl text-white bg-up-primary hover:bg-up-primary/90 shadow-sm transition active:scale-[0.98]">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            <span>Kompensasi</span>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-12 text-center text-sm text-ink-500">
                                        Tidak ada data karyawan yang cocok dengan pencarian.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL 1: BUAT PERIODE BARU --}}
    @if($showBuatPeriodeModal)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="glass-card max-w-md w-full p-6 space-y-4 bg-white dark:bg-ink-950 border border-ink-200 dark:border-white/10 rounded-2xl shadow-2xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-ink-900 dark:text-white">Buat Periode Payroll Baru</h3>
                    <button wire:click="tutupBuatPeriode" class="text-ink-400 hover:text-ink-600 dark:hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase mb-1">Format Periode (YYYY-MM)</label>
                        <input type="text" wire:model="inputPeriodeBaru" placeholder="Contoh: 2026-04"
                            class="block w-full rounded-xl border-ink-300 dark:border-white/10 dark:bg-black/40 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-up-primary text-sm min-h-[44px]">
                        @error('inputPeriodeBaru') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <p class="text-xs text-ink-500 dark:text-ink-400">Periode akan mengunci tanggal awal bulan sampai tanggal akhir bulan untuk seluruh kalkulasi slip dan komisi servis.</p>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-ink-100 dark:border-white/10">
                    <button wire:click="tutupBuatPeriode"
                        class="px-4 py-2 text-xs font-medium rounded-xl text-ink-700 dark:text-ink-300 hover:bg-black/5 dark:hover:bg-white/5 transition">
                        Batal
                    </button>
                    <button wire:click="buatPeriode"
                        class="px-4 py-2 text-xs font-semibold rounded-xl text-white bg-up-primary hover:bg-up-primary/90 transition shadow-sm">
                        Simpan &amp; Buka Periode
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL 2: DETAIL SLIP GAJI --}}
    @if($showDetailSlipModal && $detailSlip)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="glass-card max-w-lg w-full p-6 space-y-5 bg-white dark:bg-ink-950 border border-ink-200 dark:border-white/10 rounded-2xl shadow-2xl">
                <div class="flex items-center justify-between border-b border-ink-100 dark:border-white/10 pb-3">
                    <div>
                        <h3 class="text-lg font-bold text-ink-900 dark:text-white">Rincian Slip Gaji Karyawan</h3>
                        <p class="text-xs text-ink-500">Periode: {{ $detailSlip['periode'] }}</p>
                    </div>
                    <button wire:click="tutupDetailSlip" class="text-ink-400 hover:text-ink-600 dark:hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-3 text-xs bg-black/5 dark:bg-white/5 p-3 rounded-xl">
                    <div>
                        <span class="text-ink-500">Nama:</span>
                        <p class="font-semibold text-ink-900 dark:text-white">{{ $detailSlip['karyawan_nama'] }}</p>
                    </div>
                    <div>
                        <span class="text-ink-500">NIK:</span>
                        <p class="font-semibold text-ink-900 dark:text-white">{{ $detailSlip['karyawan_nik'] }}</p>
                    </div>
                    <div>
                        <span class="text-ink-500">Jabatan &amp; Cabang:</span>
                        <p class="font-semibold text-ink-900 dark:text-white capitalize">{{ $detailSlip['karyawan_jabatan'] }} ({{ $detailSlip['karyawan_cabang'] }})</p>
                    </div>
                    <div>
                        <span class="text-ink-500">Rekening Bank:</span>
                        <p class="font-semibold text-ink-900 dark:text-white">{{ $detailSlip['rekening_bank'] }}</p>
                    </div>
                </div>

                {{-- Breakdown Items --}}
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between py-1.5 border-b border-ink-100 dark:border-white/5">
                        <span class="text-ink-600 dark:text-ink-400">Gaji Pokok:</span>
                        <span class="font-semibold tabular-nums text-ink-900 dark:text-white">Rp {{ number_format($detailSlip['gaji_pokok'], 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-ink-100 dark:border-white/5">
                        <span class="text-emerald-600 dark:text-emerald-400">Tunjangan &amp; Insentif Tetap:</span>
                        <span class="font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">+Rp {{ number_format($detailSlip['total_tunjangan'], 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-ink-100 dark:border-white/5">
                        <span class="text-amber-600 dark:text-amber-400">Komisi Teknisi (Tiket Selesai):</span>
                        <span class="font-semibold tabular-nums text-amber-600 dark:text-amber-400">+Rp {{ number_format($detailSlip['komisi_teknisi'], 0, ',', '.') }}</span>
                    </div>
                    @if($detailSlip['komisi_internal'] > 0)
                        <div class="flex justify-between py-1.5 border-b border-ink-100 dark:border-white/5">
                            <span class="text-amber-600 dark:text-amber-400">Komisi Multi-Aktor / Penjualan:</span>
                            <span class="font-semibold tabular-nums text-amber-600 dark:text-amber-400">+Rp {{ number_format($detailSlip['komisi_internal'], 0, ',', '.') }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between py-1.5 border-b border-ink-100 dark:border-white/5">
                        <span class="text-red-600 dark:text-red-400">Total Potongan (Disiplin &amp; Komponen):</span>
                        <span class="font-semibold tabular-nums text-red-600 dark:text-red-400">-Rp {{ number_format($detailSlip['total_potongan'], 0, ',', '.') }}</span>
                    </div>
                    @if($detailSlip['potongan_absen'] > 0)
                        <div class="flex justify-between py-1 pl-4 text-ink-500">
                            <span>↳ Porsi Potongan Absensi Tanpa Izin:</span>
                            <span class="tabular-nums">-Rp {{ number_format($detailSlip['potongan_absen'], 0, ',', '.') }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between py-2.5 mt-2 bg-up-primary/10 dark:bg-up-primary/20 p-3 rounded-xl text-sm font-bold text-up-primary">
                        <span>Total Take Home Pay (Net):</span>
                        <span class="tabular-nums">Rp {{ number_format($detailSlip['total_gaji'], 0, ',', '.') }}</span>
                    </div>

                    @if(!empty($detailSlip['komisi_details']))
                        <div class="pt-2 border-t border-ink-100 dark:border-white/10 space-y-1">
                            <span class="text-[11px] font-bold uppercase text-ink-500">Rincian Komisi Terkait ({{ count($detailSlip['komisi_details']) }} item):</span>
                            <div class="max-h-28 overflow-y-auto divide-y divide-ink-50 dark:divide-white/5 text-[11px]">
                                @foreach($detailSlip['komisi_details'] as $kd)
                                    <div class="flex justify-between py-1 text-ink-600 dark:text-ink-400">
                                        <span>{{ $kd['no_tiket'] !== '-' ? 'Tiket '.$kd['no_tiket'] : 'Komisi' }} ({{ $kd['jenis'] }})</span>
                                        <span class="tabular-nums font-medium text-amber-600">+Rp {{ number_format($kd['nominal'], 0, ',', '.') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex items-center justify-between text-xs text-ink-500 pt-2 border-t border-ink-100 dark:border-white/10">
                    <div>
                        Status Slip: <span class="font-semibold uppercase text-ink-800 dark:text-ink-200">{{ $detailSlip['status'] }}</span>
                        @if($detailSlip['jurnal_id'])
                            <span class="ml-2 font-mono text-[10px] text-emerald-600 dark:text-emerald-400">✓ Jurnal #{{ $detailSlip['jurnal_id'] }}</span>
                        @endif
                    </div>
                    <button wire:click="tutupDetailSlip"
                        class="px-4 py-2 text-xs font-semibold rounded-xl text-ink-700 dark:text-ink-300 hover:bg-black/5 dark:hover:bg-white/5 transition">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL 3: KELOLA KOMPENSASI KARYAWAN --}}
    @if($showKompensasiModal && $selectedKaryawan)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="glass-card max-w-2xl w-full p-6 space-y-6 bg-white dark:bg-ink-950 border border-ink-200 dark:border-white/10 rounded-2xl shadow-2xl">
                <div class="flex items-center justify-between border-b border-ink-100 dark:border-white/10 pb-3">
                    <div>
                        <h3 class="text-lg font-bold text-ink-900 dark:text-white">Pengaturan Kompensasi &amp; Gaji Karyawan</h3>
                        <p class="text-xs text-ink-500">{{ $selectedKaryawan['nama'] }} ({{ $selectedKaryawan['nik'] }}) • {{ ucfirst($selectedKaryawan['jabatan']) }} - {{ $selectedKaryawan['cabang'] }}</p>
                    </div>
                    <button wire:click="tutupKompensasi" class="text-ink-400 hover:text-ink-600 dark:hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                {{-- Edit Gaji Pokok --}}
                <div class="p-4 rounded-xl border border-ink-200 dark:border-white/10 bg-black/[0.02] dark:bg-white/[0.02] space-y-2">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-ink-600 dark:text-ink-300">Gaji Pokok Bulanan (IDR)</label>
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <span class="absolute left-3 top-2.5 text-sm font-semibold text-ink-400">Rp</span>
                            <input type="text" wire:model="inputGajiPokok"
                                class="block w-full pl-10 pr-3 py-2 text-sm font-semibold rounded-xl border-ink-300 dark:border-white/10 dark:bg-black/40 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-up-primary">
                        </div>
                        <button wire:click="simpanGajiPokok"
                            class="px-4 py-2 text-xs font-semibold rounded-xl text-white bg-up-primary hover:bg-up-primary/90 transition shadow-sm active:scale-[0.98]">
                            Simpan Pokok
                        </button>
                    </div>
                </div>

                {{-- Daftar Komponen Gaji Tambahan --}}
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-semibold text-ink-900 dark:text-white">Komponen Tambahan (Tunjangan, Potongan, Bonus)</h4>
                        <span class="text-xs text-ink-400">{{ count($komponenList) }} komponen terdaftar</span>
                    </div>

                    <div class="divide-y divide-ink-100 dark:divide-white/5 border border-ink-200 dark:border-white/10 rounded-xl overflow-hidden max-h-56 overflow-y-auto">
                        @forelse($komponenList as $item)
                            <div class="p-3 flex items-center justify-between hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition">
                                <div class="flex items-center gap-2.5">
                                    @php
                                        $badge = match($item['tipe']) {
                                            'tunjangan' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
                                            'bonus' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
                                            'potongan' => 'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300',
                                            default => 'bg-gray-100 text-gray-800'
                                        };
                                    @endphp
                                    <span class="px-2 py-0.5 text-[10px] font-semibold uppercase rounded-md {{ $badge }}">
                                        {{ $item['tipe'] }}
                                    </span>
                                    <div>
                                        <p class="text-xs font-semibold text-ink-900 dark:text-white">{{ $item['nama'] }}</p>
                                        <p class="text-[11px] tabular-nums font-mono text-ink-500">
                                            {{ $item['tipe'] === 'potongan' ? '-' : '+' }}Rp {{ number_format($item['nominal_bulanan'], 0, ',', '.') }} / bulan
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button wire:click="toggleKomponen({{ $item['id'] }})"
                                        class="px-2.5 py-1 text-[11px] font-medium rounded-lg transition {{ $item['is_aktif'] ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400' }}">
                                        {{ $item['is_aktif'] ? 'Aktif' : 'Non-aktif' }}
                                    </button>
                                    <button wire:click="hapusKomponen({{ $item['id'] }})"
                                        class="p-1 text-ink-400 hover:text-red-600 transition" title="Hapus">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="p-6 text-center text-xs text-ink-400">
                                Belum ada komponen tunjangan / potongan tambahan untuk karyawan ini.
                            </div>
                        @endforelse
                    </div>

                    {{-- Form Tambah Komponen Baru --}}
                    <div class="p-4 rounded-xl border border-dashed border-ink-300 dark:border-white/10 space-y-3 bg-black/[0.01] dark:bg-white/[0.01]">
                        <h5 class="text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase">Tambah Komponen Gaji Baru</h5>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <div>
                                <select wire:model="newKomponenTipe"
                                    class="block w-full text-xs rounded-xl border-ink-300 dark:border-white/10 dark:bg-black/40 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-up-primary">
                                    <option value="tunjangan">Tunjangan (+)</option>
                                    <option value="bonus">Bonus / Insentif (+)</option>
                                    <option value="potongan">Potongan (-)</option>
                                </select>
                            </div>
                            <div>
                                <input type="text" wire:model="newKomponenNama" placeholder="Nama (e.g. Uang Makan, BPJS)"
                                    class="block w-full text-xs rounded-xl border-ink-300 dark:border-white/10 dark:bg-black/40 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-up-primary">
                            </div>
                            <div>
                                <input type="text" wire:model="newKomponenNominal" placeholder="Nominal Rp"
                                    class="block w-full text-xs rounded-xl border-ink-300 dark:border-white/10 dark:bg-black/40 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-up-primary">
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button wire:click="tambahKomponen"
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-xl text-white bg-emerald-600 hover:bg-emerald-700 transition shadow-sm active:scale-[0.98]">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                <span>Tambah Komponen</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-3 border-t border-ink-100 dark:border-white/10">
                    <button wire:click="tutupKompensasi"
                        class="px-4 py-2 text-xs font-semibold rounded-xl text-ink-700 dark:text-ink-300 hover:bg-black/5 dark:hover:bg-white/5 transition">
                        Selesai
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL 4: APPROVAL / FINALISASI PAYROLL --}}
    @if($showApproveModal)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="glass-card max-w-md w-full p-6 space-y-4 bg-white dark:bg-ink-950 border border-ink-200 dark:border-white/10 rounded-2xl shadow-2xl">
                <div class="flex items-center justify-between border-b border-ink-100 dark:border-white/10 pb-3">
                    <h3 class="text-lg font-bold text-ink-900 dark:text-white">Approval &amp; Finalisasi Payroll</h3>
                    <button wire:click="tutupApproveModal" class="text-ink-400 hover:text-ink-600 dark:hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="space-y-3 text-xs text-ink-600 dark:text-ink-300">
                    <p>Periode: <strong class="text-ink-900 dark:text-white">{{ $periode }}</strong></p>
                    <p>Total Beban Payroll: <strong class="text-lg tabular-nums text-up-primary block">Rp {{ number_format($totalGaji, 0, ',', '.') }}</strong></p>
                    
                    @if($totalGaji > \App\Modules\Hr\Services\PayrollService::THRESHOLD_APPROVAL)
                        <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/30 text-amber-800 dark:text-amber-200 space-y-1">
                            <p class="font-bold">Perhatian Approval F1-1:</p>
                            <p>Total payroll melebihi ambang batas (Rp {{ number_format(\App\Modules\Hr\Services\PayrollService::THRESHOLD_APPROVAL, 0, ',', '.') }}). Sistem memerlukan persetujuan dari Finance / Super Admin.</p>
                            @if($approvalRequest)
                                <p class="mt-1">Status Request: <strong class="uppercase font-bold">{{ $approvalRequest->status }}</strong></p>
                            @endif
                        </div>
                    @endif

                    <div class="p-3 rounded-xl bg-black/5 dark:bg-white/5 space-y-1 font-mono text-[11px]">
                        <p class="font-sans font-bold text-ink-700 dark:text-ink-200">Pratinjau Jurnal Akuntansi:</p>
                        <p>• D Beban Gaji (520-01) &amp; Komisi (520-09)</p>
                        <p>• K Utang Gaji Karyawan (210-02)</p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-ink-100 dark:border-white/10">
                    <button wire:click="tutupApproveModal"
                        class="px-4 py-2 text-xs font-semibold rounded-xl text-ink-700 dark:text-ink-300 hover:bg-black/5 dark:hover:bg-white/5 transition">
                        Batal
                    </button>
                    @if($approvalRequest && $approvalRequest->status === 'disetujui' || $totalGaji <= \App\Modules\Hr\Services\PayrollService::THRESHOLD_APPROVAL)
                        <button wire:click="finalisasiDisetujui"
                            class="px-4 py-2 text-xs font-bold rounded-xl text-white bg-emerald-600 hover:bg-emerald-700 transition shadow-sm">
                            Finalisasi &amp; Posting Jurnal
                        </button>
                    @else
                        <button wire:click="ajukanApproval"
                            class="px-4 py-2 text-xs font-bold rounded-xl text-white bg-blue-600 hover:bg-blue-700 transition shadow-sm">
                            Kirim Pengajuan Approval
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL 5: PEMBAYARAN PAYROLL --}}
    @if($showBayarModal)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="glass-card max-w-md w-full p-6 space-y-4 bg-white dark:bg-ink-950 border border-ink-200 dark:border-white/10 rounded-2xl shadow-2xl">
                <div class="flex items-center justify-between border-b border-ink-100 dark:border-white/10 pb-3">
                    <h3 class="text-lg font-bold text-ink-900 dark:text-white">Eksekusi Pembayaran Payroll</h3>
                    <button wire:click="tutupBayarModal" class="text-ink-400 hover:text-ink-600 dark:hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <span class="text-ink-500">Periode:</span>
                        <p class="font-bold text-ink-900 dark:text-white text-sm">{{ $periode }}</p>
                    </div>
                    <div>
                        <span class="text-ink-500">Total Nominal Dibayar:</span>
                        <p class="text-xl font-extrabold tabular-nums text-up-primary">Rp {{ number_format($totalGaji, 0, ',', '.') }}</p>
                    </div>

                    <div>
                        <label class="block font-semibold uppercase tracking-wider text-ink-700 dark:text-ink-300 mb-1">Pilih Akun Sumber Dana (Kas / Bank)</label>
                        <select wire:model="akunBayarKode"
                            class="block w-full text-xs rounded-xl border-ink-300 dark:border-white/10 dark:bg-black/40 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-up-primary">
                            @foreach($akunKasBankList as $akun)
                                <option value="{{ $akun->kode }}">{{ $akun->kode }} - {{ $akun->nama }} ({{ ucfirst($akun->kelompok) }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="p-3 rounded-xl bg-purple-50 dark:bg-purple-950/30 border border-purple-200 dark:border-purple-800/30 text-purple-800 dark:text-purple-200 space-y-1">
                        <p class="font-bold">Efek Pembayaran Akuntansi:</p>
                        <p class="font-mono text-[11px]">• D Utang Gaji (210-02) Rp {{ number_format($totalGaji, 0, ',', '.') }}</p>
                        <p class="font-mono text-[11px]">• K {{ $akunBayarKode }} Rp {{ number_format($totalGaji, 0, ',', '.') }}</p>
                        <p class="text-[11px] mt-1">Status periode akan beralih ke <strong>DIBAYAR</strong>.</p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-ink-100 dark:border-white/10">
                    <button wire:click="tutupBayarModal"
                        class="px-4 py-2 text-xs font-semibold rounded-xl text-ink-700 dark:text-ink-300 hover:bg-black/5 dark:hover:bg-white/5 transition">
                        Batal
                    </button>
                    <button wire:click="bayarPayroll"
                        class="px-5 py-2 text-xs font-bold rounded-xl text-white bg-purple-600 hover:bg-purple-700 transition shadow-sm active:scale-[0.98]">
                        Konfirmasi Pembayaran
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL 6: MASTER KARYAWAN (TAMBAH / EDIT) --}}
    @if($showTambahKaryawanModal)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="glass-card max-w-xl w-full p-6 space-y-5 bg-white dark:bg-ink-950 border border-ink-200 dark:border-white/10 rounded-2xl shadow-2xl">
                <div class="flex items-center justify-between border-b border-ink-100 dark:border-white/10 pb-3">
                    <h3 class="text-lg font-bold text-ink-900 dark:text-white">
                        {{ $karyawanEditId ? 'Edit Data Karyawan' : 'Tambah Karyawan Baru' }}
                    </h3>
                    <button wire:click="tutupTambahKaryawan" class="text-ink-400 hover:text-ink-600 dark:hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                        <label class="block font-semibold uppercase text-ink-600 dark:text-ink-300 mb-1">Nama Lengkap *</label>
                        <input type="text" wire:model="inputNama" placeholder="Nama Karyawan"
                            class="block w-full text-xs rounded-xl border-ink-300 dark:border-white/10 dark:bg-black/40 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-up-primary">
                        @error('inputNama') <span class="text-red-500 text-[11px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-semibold uppercase text-ink-600 dark:text-ink-300 mb-1">NIK *</label>
                        <input type="text" wire:model="inputNik" placeholder="EMP-0001"
                            class="block w-full text-xs rounded-xl border-ink-300 dark:border-white/10 dark:bg-black/40 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-up-primary">
                        @error('inputNik') <span class="text-red-500 text-[11px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-semibold uppercase text-ink-600 dark:text-ink-300 mb-1">Jabatan *</label>
                        <select wire:model="inputJabatan"
                            class="block w-full text-xs rounded-xl border-ink-300 dark:border-white/10 dark:bg-black/40 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-up-primary">
                            <option value="teknisi">Teknisi Servis</option>
                            <option value="kasir">Kasir POS</option>
                            <option value="admin">Admin Toko / Gudang</option>
                            <option value="marketing">Marketing / Sales</option>
                            <option value="manager">Manager / Supervisor</option>
                            <option value="staff">Staff Umum</option>
                        </select>
                        @error('inputJabatan') <span class="text-red-500 text-[11px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-semibold uppercase text-ink-600 dark:text-ink-300 mb-1">Cabang Penempatan</label>
                        <select wire:model="inputCabangId"
                            class="block w-full text-xs rounded-xl border-ink-300 dark:border-white/10 dark:bg-black/40 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-up-primary">
                            <option value="">Kantor Pusat</option>
                            @foreach($cabangList as $cab)
                                <option value="{{ $cab->id }}">{{ $cab->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold uppercase text-ink-600 dark:text-ink-300 mb-1">Hubungkan Akun User Login</label>
                        <select wire:model="inputUserId"
                            class="block w-full text-xs rounded-xl border-ink-300 dark:border-white/10 dark:bg-black/40 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-up-primary">
                            <option value="">-- Tidak Terhubung Akun --</option>
                            @foreach($userList as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                        <p class="text-[10px] text-ink-400 mt-1">Dihubungkan agar karyawan dapat presensi &amp; lihat slip di menu HR Saya.</p>
                    </div>

                    <div>
                        <label class="block font-semibold uppercase text-ink-600 dark:text-ink-300 mb-1">Tanggal Mulai Masuk *</label>
                        <input type="date" wire:model="inputTglMasuk"
                            class="block w-full text-xs rounded-xl border-ink-300 dark:border-white/10 dark:bg-black/40 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-up-primary">
                        @error('inputTglMasuk') <span class="text-red-500 text-[11px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-semibold uppercase text-ink-600 dark:text-ink-300 mb-1">Gaji Pokok Default (IDR)</label>
                        <input type="text" wire:model="inputKaryawanGajiPokok" placeholder="Contoh: 3500000"
                            class="block w-full text-xs rounded-xl border-ink-300 dark:border-white/10 dark:bg-black/40 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-up-primary">
                    </div>

                    <div>
                        <label class="block font-semibold uppercase text-ink-600 dark:text-ink-300 mb-1">Nomor Rekening Bank</label>
                        <input type="text" wire:model="inputRekeningBank" placeholder="BCA 1234567890 a/n..."
                            class="block w-full text-xs rounded-xl border-ink-300 dark:border-white/10 dark:bg-black/40 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-up-primary">
                    </div>

                    <div class="sm:col-span-2 flex items-center gap-2 pt-2">
                        <input type="checkbox" wire:model="inputStatusAktif" id="inputStatusAktif"
                            class="rounded border-ink-300 dark:border-white/10 text-up-primary focus:ring-up-primary">
                        <label for="inputStatusAktif" class="text-xs text-ink-800 dark:text-ink-200 font-medium">
                            Karyawan Aktif (diikutsertakan dalam payroll &amp; absensi)
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-ink-100 dark:border-white/10">
                    <button wire:click="tutupTambahKaryawan"
                        class="px-4 py-2 text-xs font-semibold rounded-xl text-ink-700 dark:text-ink-300 hover:bg-black/5 dark:hover:bg-white/5 transition">
                        Batal
                    </button>
                    <button wire:click="simpanKaryawan"
                        class="px-5 py-2 text-xs font-bold rounded-xl text-white bg-up-primary hover:bg-up-primary/90 transition shadow-sm active:scale-[0.98]">
                        Simpan Karyawan
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
