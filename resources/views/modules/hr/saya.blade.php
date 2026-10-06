<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-ink-900 dark:text-white">Portal HR &amp; Absensi Saya</h1>
            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">
                {{ $karyawan ? "{$karyawan->nama} ({$karyawan->nik}) — ".ucfirst($karyawan->jabatan).' ('.($karyawan->cabang?->nama ?? 'Pusat').')' : 'Informasi Akun Karyawan' }}
            </p>
        </div>
        @if($karyawan)
            <div class="flex items-center gap-2">
                <label class="text-xs font-semibold text-ink-500 uppercase">Periode:</label>
                <input type="month" wire:model.live="periode"
                    class="rounded-xl border-ink-300 dark:border-white/10 dark:bg-black/40 text-sm font-semibold text-ink-900 dark:text-white focus:border-up-primary focus:ring-up-primary py-1.5 px-3">
            </div>
        @endif
    </div>

    @if(! $karyawan)
        <div class="glass-card p-8 text-center space-y-3">
            <div class="w-12 h-12 mx-auto rounded-full bg-amber-100 dark:bg-amber-950/40 text-amber-600 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
            </div>
            <p class="text-base font-semibold text-ink-800 dark:text-ink-200">Akun Anda belum terhubung ke data karyawan aktif.</p>
            <p class="text-xs text-ink-500 max-w-md mx-auto">Silakan hubungi administrator atau HR untuk menghubungkan User login ini dengan Master Karyawan di menu HR &amp; Payroll.</p>
        </div>
    @else
        {{-- CARD 1: AKSI ABSENSI HARI INI --}}
        <div class="glass-card p-5 border-l-4 border-emerald-500 flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-emerald-500/5">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="inline-flex w-2.5 h-2.5 rounded-full {{ $logHariIni?->jam_masuk ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500' }}"></span>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-ink-800 dark:text-ink-100">Presensi Hari Ini — {{ now()->translatedFormat('l, d F Y') }}</h2>
                </div>
                <div class="text-xs text-ink-600 dark:text-ink-400 flex flex-wrap items-center gap-x-4 gap-y-1 pt-1">
                    <span>Masuk: <strong class="text-ink-900 dark:text-white tabular-nums">{{ $logHariIni?->jam_masuk ? substr($logHariIni->jam_masuk, 0, 5) : 'Belum absen' }}</strong></span>
                    <span>Keluar: <strong class="text-ink-900 dark:text-white tabular-nums">{{ $logHariIni?->jam_keluar ? substr($logHariIni->jam_keluar, 0, 5) : 'Belum absen' }}</strong></span>
                    <span>Status: <strong class="capitalize {{ $logHariIni?->status === 'terlambat' ? 'text-amber-600' : 'text-emerald-600' }}">{{ $logHariIni?->status ?? 'Belum' }}</strong></span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if(! $logHariIni?->jam_masuk)
                    <button wire:click="clockIn"
                        class="inline-flex items-center gap-1.5 px-5 py-2.5 text-xs font-bold rounded-xl text-white bg-emerald-600 hover:bg-emerald-700 shadow-md shadow-emerald-600/20 active:scale-95 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" /></svg>
                        <span>Clock-In Sekarang</span>
                    </button>
                @elseif(! $logHariIni?->jam_keluar)
                    <button wire:click="clockOut"
                        class="inline-flex items-center gap-1.5 px-5 py-2.5 text-xs font-bold rounded-xl text-white bg-blue-600 hover:bg-blue-700 shadow-md shadow-blue-600/20 active:scale-95 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                        <span>Clock-Out (Pulang)</span>
                    </button>
                @else
                    <span class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-xl bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <span>Presensi Hari Ini Selesai</span>
                    </span>
                @endif
            </div>
        </div>

        {{-- CARD 2: REKAP BULANAN & SLIP GAJI SAYA --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- Rekap Bulanan --}}
            <div class="md:col-span-2 glass-card p-5 space-y-4">
                <h3 class="text-sm font-bold uppercase tracking-wider text-ink-700 dark:text-ink-300">Rekapitulasi Kehadiran ({{ $periode }})</h3>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                    @foreach([
                        ['hadir', 'Hadir', 'text-emerald-700 bg-emerald-50 dark:bg-emerald-950/30 dark:text-emerald-300'],
                        ['terlambat', 'Terlambat', 'text-amber-700 bg-amber-50 dark:bg-amber-950/30 dark:text-amber-300'],
                        ['absen', 'Absen', 'text-red-700 bg-red-50 dark:bg-red-950/30 dark:text-red-300'],
                        ['izin', 'Izin', 'text-sky-700 bg-sky-50 dark:bg-sky-950/30 dark:text-sky-300'],
                        ['cuti', 'Cuti', 'text-purple-700 bg-purple-50 dark:bg-purple-950/30 dark:text-purple-300'],
                    ] as [$key, $label, $style])
                        <div class="p-3.5 {{ $style }} rounded-xl text-center border border-black/5 dark:border-white/5">
                            <p class="text-2xl font-black tabular-nums">{{ $rekap[$key] ?? 0 }}</p>
                            <p class="text-xs font-semibold mt-0.5">{{ $label }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Kartu Slip Gaji Saya --}}
            <div class="glass-card p-5 flex flex-col justify-between space-y-4 border-l-4 border-up-primary">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs uppercase font-bold text-ink-500">Slip Gaji Saya</span>
                        @if($slip)
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $slip->status === 'dibayar' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800' }}">
                                {{ ucfirst($slip->status) }}
                            </span>
                        @else
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-gray-100 text-gray-700">Belum Dihitung</span>
                        @endif
                    </div>
                    <p class="text-xs text-ink-400 mt-1">Periode {{ $periode }}</p>

                    @if($slip)
                        <div class="mt-3">
                            <span class="text-xs text-ink-500">Total Take Home Pay (Net):</span>
                            <p class="text-2xl font-black tabular-nums text-up-primary">Rp {{ number_format($slip->total_gaji, 0, ',', '.') }}</p>
                            <div class="text-[11px] text-ink-500 space-y-0.5 mt-2">
                                <div class="flex justify-between"><span>Pokok:</span><span class="tabular-nums">Rp {{ number_format($slip->gaji_pokok, 0, ',', '.') }}</span></div>
                                <div class="flex justify-between text-emerald-600"><span>Tunjangan:</span><span class="tabular-nums">+Rp {{ number_format($slip->total_tunjangan, 0, ',', '.') }}</span></div>
                                <div class="flex justify-between text-amber-600"><span>Komisi:</span><span class="tabular-nums">+Rp {{ number_format($slip->total_komisi, 0, ',', '.') }}</span></div>
                                <div class="flex justify-between text-red-600"><span>Potongan:</span><span class="tabular-nums">-Rp {{ number_format($slip->total_potongan, 0, ',', '.') }}</span></div>
                            </div>
                        </div>
                    @else
                        <p class="text-xs text-ink-500 mt-4 italic">Kalkulasi slip gaji untuk periode ini belum diproses oleh tim HR/Finance.</p>
                    @endif
                </div>

                @if($slip)
                    <button wire:click="bukaDetailSlip"
                        class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 text-xs font-bold rounded-xl text-white bg-up-primary hover:bg-up-primary/90 shadow-md shadow-up-primary/20 transition active:scale-98">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        <span>Lihat &amp; Cetak Slip Gaji</span>
                    </button>
                @endif
            </div>
        </div>

        {{-- CARD 3: TABEL LOG ABSENSI & KPI --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Log Absensi --}}
            <div class="glass-card p-5 space-y-3">
                <h3 class="text-sm font-bold uppercase tracking-wider text-ink-800 dark:text-ink-100">Riwayat Presensi Bulanan</h3>
                <div class="overflow-x-auto max-h-96 overflow-y-auto">
                    <table class="min-w-full divide-y divide-ink-200 dark:divide-white/10 text-xs">
                        <thead class="bg-black/5 dark:bg-white/5 sticky top-0">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-ink-600 dark:text-ink-300">Tanggal</th>
                                <th class="px-3 py-2 text-center font-semibold text-ink-600 dark:text-ink-300">Masuk</th>
                                <th class="px-3 py-2 text-center font-semibold text-ink-600 dark:text-ink-300">Keluar</th>
                                <th class="px-3 py-2 text-left font-semibold text-ink-600 dark:text-ink-300">Shift</th>
                                <th class="px-3 py-2 text-center font-semibold text-ink-600 dark:text-ink-300">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100 dark:divide-white/5">
                            @forelse($logs as $a)
                                <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02]">
                                    <td class="px-3 py-2 text-ink-800 dark:text-ink-200 font-medium">{{ \Carbon\Carbon::parse($a->tanggal)->format('d M Y') }}</td>
                                    <td class="px-3 py-2 text-center tabular-nums text-ink-700 dark:text-ink-300">{{ $a->jam_masuk ? substr($a->jam_masuk, 0, 5) : '-' }}</td>
                                    <td class="px-3 py-2 text-center tabular-nums text-ink-700 dark:text-ink-300">{{ $a->jam_keluar ? substr($a->jam_keluar, 0, 5) : '-' }}</td>
                                    <td class="px-3 py-2 text-ink-600 dark:text-ink-400">{{ $a->shift?->nama ?? 'Standar' }}</td>
                                    <td class="px-3 py-2 text-center">
                                        @php
                                            $warna = match($a->status) {
                                                'terlambat' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
                                                'absen' => 'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300',
                                                'izin' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/40 dark:text-sky-300',
                                                'cuti' => 'bg-purple-100 text-purple-800 dark:bg-purple-950/40 dark:text-purple-300',
                                                default => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
                                            };
                                        @endphp
                                        <span class="inline-flex px-2 py-0.5 text-[10px] font-bold rounded-full {{ $warna }}">{{ ucfirst($a->status) }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-3 py-8 text-center text-ink-400 italic">Belum ada catatan presensi pada periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- KPI Saya --}}
            <div class="glass-card p-5 space-y-3">
                <h3 class="text-sm font-bold uppercase tracking-wider text-ink-800 dark:text-ink-100">Capaian KPI Periode Ini</h3>
                <div class="overflow-x-auto max-h-96 overflow-y-auto">
                    <table class="min-w-full divide-y divide-ink-200 dark:divide-white/10 text-xs">
                        <thead class="bg-black/5 dark:bg-white/5 sticky top-0">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-ink-600 dark:text-ink-300">Indikator Kinerja</th>
                                <th class="px-3 py-2 text-right font-semibold text-ink-600 dark:text-ink-300">Aktual</th>
                                <th class="px-3 py-2 text-right font-semibold text-ink-600 dark:text-ink-300">Target</th>
                                <th class="px-3 py-2 text-center font-semibold text-ink-600 dark:text-ink-300">Capaian</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100 dark:divide-white/5">
                            @forelse($kpi as $h)
                                <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02]">
                                    <td class="px-3 py-2 font-medium text-ink-800 dark:text-ink-200">{{ $h->metric?->nama ?? '-' }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-ink-700 dark:text-ink-300">
                                        {{ number_format((float) $h->nilai_aktual, 0, ',', '.') }} {{ $h->metric?->satuan }}
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums text-ink-500">
                                        {{ number_format((float) ($h->metric?->target ?? 0), 0, ',', '.') }} {{ $h->metric?->satuan }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        @php
                                            $persen = (float) $h->persen_capaian;
                                            $warna = $persen >= 100 ? 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/30' : ($persen >= 70 ? 'text-amber-600 bg-amber-50 dark:bg-amber-950/30' : 'text-red-600 bg-red-50 dark:bg-red-950/30');
                                        @endphp
                                        <span class="inline-flex px-2 py-0.5 text-[10px] font-bold rounded-full {{ $warna }} tabular-nums">
                                            {{ number_format($persen, 1, ',', '.') }}%
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-3 py-8 text-center text-ink-400 italic">Belum ada evaluasi KPI untuk periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- MODAL SLIP GAJI SAYA --}}
        @if($showDetailSlipModal && $detailSlip)
            <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm print:p-0 print:bg-white">
                <div class="glass-card max-w-lg w-full p-6 space-y-5 bg-white dark:bg-ink-950 border border-ink-200 dark:border-white/10 rounded-2xl shadow-2xl print:border-none print:shadow-none print:max-w-none">
                    <div class="flex items-center justify-between border-b border-ink-100 dark:border-white/10 pb-3">
                        <div>
                            <h3 class="text-lg font-bold text-ink-900 dark:text-white">Slip Gaji Karyawan</h3>
                            <p class="text-xs text-ink-500">Periode: {{ $detailSlip['periode'] }}</p>
                        </div>
                        <div class="flex items-center gap-2 print:hidden">
                            <button onclick="window.print()" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-xl bg-ink-100 dark:bg-white/10 text-ink-800 dark:text-white hover:bg-ink-200 transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                                <span>Cetak</span>
                            </button>
                            <button wire:click="tutupDetailSlip" class="text-ink-400 hover:text-ink-600 dark:hover:text-white">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-xs bg-black/5 dark:bg-white/5 p-3 rounded-xl">
                        <div><span class="text-ink-500">Nama:</span><p class="font-semibold text-ink-900 dark:text-white">{{ $detailSlip['karyawan_nama'] }}</p></div>
                        <div><span class="text-ink-500">NIK:</span><p class="font-semibold text-ink-900 dark:text-white">{{ $detailSlip['karyawan_nik'] }}</p></div>
                        <div><span class="text-ink-500">Jabatan &amp; Cabang:</span><p class="font-semibold text-ink-900 dark:text-white capitalize">{{ $detailSlip['karyawan_jabatan'] }} ({{ $detailSlip['karyawan_cabang'] }})</p></div>
                        <div><span class="text-ink-500">Rekening:</span><p class="font-semibold text-ink-900 dark:text-white">{{ $detailSlip['rekening_bank'] }}</p></div>
                    </div>

                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between py-1.5 border-b border-ink-100 dark:border-white/5">
                            <span class="text-ink-600 dark:text-ink-400">Gaji Pokok:</span>
                            <span class="font-semibold tabular-nums text-ink-900 dark:text-white">Rp {{ number_format($detailSlip['gaji_pokok'], 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between py-1.5 border-b border-ink-100 dark:border-white/5">
                            <span class="text-emerald-600 dark:text-emerald-400">Tunjangan &amp; Insentif:</span>
                            <span class="font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">+Rp {{ number_format($detailSlip['total_tunjangan'], 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between py-1.5 border-b border-ink-100 dark:border-white/5">
                            <span class="text-amber-600 dark:text-amber-400">Komisi:</span>
                            <span class="font-semibold tabular-nums text-amber-600 dark:text-amber-400">+Rp {{ number_format($detailSlip['total_komisi'], 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between py-1.5 border-b border-ink-100 dark:border-white/5">
                            <span class="text-red-600 dark:text-red-400">Potongan:</span>
                            <span class="font-semibold tabular-nums text-red-600 dark:text-red-400">-Rp {{ number_format($detailSlip['total_potongan'], 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between py-2.5 mt-2 bg-up-primary/10 dark:bg-up-primary/20 p-3 rounded-xl text-sm font-bold text-up-primary">
                            <span>Total Take Home Pay (Net):</span>
                            <span class="tabular-nums">Rp {{ number_format($detailSlip['total_gaji'], 0, ',', '.') }}</span>
                        </div>
                    </div>

                    @if(!empty($detailSlip['komisi_details']))
                        <div class="pt-2 border-t border-ink-100 dark:border-white/10 space-y-1">
                            <span class="text-[11px] font-bold uppercase text-ink-500">Rincian Komisi ({{ count($detailSlip['komisi_details']) }} tiket):</span>
                            <div class="max-h-28 overflow-y-auto divide-y divide-ink-50 dark:divide-white/5 text-[11px]">
                                @foreach($detailSlip['komisi_details'] as $kd)
                                    <div class="flex justify-between py-1 text-ink-600 dark:text-ink-400">
                                        <span>{{ $kd['no_tiket'] }} ({{ $kd['jenis'] }})</span>
                                        <span class="tabular-nums font-medium text-amber-600">+Rp {{ number_format($kd['nominal'], 0, ',', '.') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="flex items-center justify-between text-xs text-ink-500 pt-2 border-t border-ink-100 dark:border-white/10 print:hidden">
                        <div>Status: <span class="font-bold uppercase text-ink-800 dark:text-ink-200">{{ $detailSlip['status'] }}</span></div>
                        <button wire:click="tutupDetailSlip" class="px-4 py-2 text-xs font-semibold rounded-xl text-ink-700 dark:text-ink-300 hover:bg-black/5 dark:hover:bg-white/5">Tutup</button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
