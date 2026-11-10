<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    {{-- Header & Action Button --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-ink-900 dark:text-white">Absensi, Shift &amp; KPI</h1>
            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Roster shift, log absensi presensi toko, dan integrasi otomatis KPI karyawan</p>
        </div>
        <div class="flex items-center gap-3">
            <button
                type="button"
                wire:click="bukaQrModal"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-up-primary to-indigo-600 hover:from-up-primary/90 hover:to-indigo-500 text-white font-bold text-sm shadow-lg shadow-up-primary/25 active:scale-95 transition-all cursor-pointer">
                <svg class="w-5 h-5 text-up-mint animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                </svg>
                <span>Buka Mesin Absensi QR Toko</span>
            </button>
        </div>
    </div>

    {{-- Tabs & Period Picker --}}
    <div class="glass-card p-2 sm:p-2.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-2 overflow-x-auto scrollbar-none py-1 -my-1 flex-nowrap">
            @foreach(['absensi' => 'Presensi Hari Ini & Log', 'shift' => 'Shift Kerja', 'roster' => 'Roster Jadwal', 'kpi' => 'KPI Karyawan'] as $key => $label)
                <a
                    href="{{ url('/app/hr/absensi/'.$key) }}"
                    wire:click.prevent="pilihTab('{{ $key }}')"
                    class="px-4 py-2.5 text-xs sm:text-sm font-bold rounded-xl transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer flex items-center justify-center
                    {{ $tab === $key ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-600 dark:text-ink-400 hover:bg-black/5 dark:hover:bg-white/5 hover:text-ink-900 dark:hover:text-white' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
        @if($tab !== 'shift')
            <div class="flex items-center gap-2 px-1">
                <label class="text-xs font-semibold text-ink-500 dark:text-ink-400 uppercase tracking-wider whitespace-nowrap">Periode:</label>
                <input type="month" wire:model.live="kpiPeriode"
                    class="rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-1.5">
            </div>
        @endif
    </div>

    {{-- MODAL MESIN ABSENSI DYNAMIC ROLLING QR (EKSKLUSIF PC TOKO / HR & PAYROLL) --}}
    @if($showQrModal)
    <div
        wire:poll.2s="pollQrStatus"
        x-data="{
            timeStr: '',
            dateStr: '',
            init() {
                const updateTime = () => {
                    const now = new Date();
                    this.timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                    this.dateStr = now.toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                };
                updateTime();
                setInterval(updateTime, 1000);
            },
            playBeep() {
                try {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.frequency.setValueAtTime(880, ctx.currentTime);
                    osc.frequency.setValueAtTime(1320, ctx.currentTime + 0.1);
                    gain.gain.setValueAtTime(0.2, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
                    osc.start(ctx.currentTime);
                    osc.stop(ctx.currentTime + 0.3);
                } catch(e) {}
            }
        }"
        x-init="
            $wire.on('kiosk-beep', () => { playBeep(); });
        "
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md overflow-y-auto"
        style="display: flex;">

        <div class="relative w-full max-w-lg bg-[#0f172a] border border-white/20 rounded-3xl p-6 sm:p-8 text-center text-white shadow-2xl space-y-5 animate-scale-up my-auto">
            {{-- Modal Topbar --}}
            <div class="flex items-center justify-between pb-3 border-b border-white/10">
                <div class="flex items-center gap-2">
                    <span class="flex h-3 w-3 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                    </span>
                    <span class="font-bold text-sm tracking-wide text-white uppercase">Mesin Absensi QR Toko</span>
                </div>
                <button
                    type="button"
                    wire:click="tutupQrModal"
                    class="p-2 rounded-xl text-ink-400 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
                    title="Tutup (Esc)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Realtime Digital Clock --}}
            <div>
                <div class="font-mono text-3xl sm:text-4xl font-black text-emerald-400 tracking-tight" x-text="timeStr">--:--:--</div>
                <div class="text-xs text-slate-400 font-medium mt-1" x-text="dateStr">--</div>
            </div>

            {{-- Feedback Alert jika baru saja ada scan sukses --}}
            @if($latestScannedEmployee)
                <div class="p-4 rounded-2xl bg-emerald-500/20 border-2 border-emerald-500/60 text-emerald-200 text-left flex items-start gap-3 animate-pulse shadow-lg">
                    <div class="w-8 h-8 rounded-full bg-emerald-500 flex items-center justify-center text-white font-bold shrink-0 mt-0.5">
                        ✓
                    </div>
                    <div class="flex-1">
                        <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-emerald-500 text-white">
                            {{ strtoupper($latestScannedEmployee['action'] ?? 'ABSEN') }} SUKSES
                        </span>
                        <h4 class="text-base font-bold text-white mt-1">
                            {{ $latestScannedEmployee['karyawan']['nama'] ?? 'Karyawan' }}
                        </h4>
                        <p class="text-xs text-emerald-300 font-mono mt-0.5">
                            Jam: {{ ($latestScannedEmployee['action'] ?? '') === 'checkin' ? ($latestScannedEmployee['jam_masuk'] ?? now()->format('H:i:s')) : ($latestScannedEmployee['jam_keluar'] ?? now()->format('H:i:s')) }}
                            • Status: {{ ucfirst($latestScannedEmployee['status'] ?? 'Hadir') }}
                        </p>
                    </div>
                </div>
            @endif

            {{-- Dynamic QR Code Display Container --}}
            <div class="relative mx-auto inline-block p-4 bg-white rounded-3xl shadow-2xl border-4 border-indigo-500/40">
                @if(!empty($qrSvg))
                    <div class="w-56 h-56 sm:w-64 sm:h-64 flex items-center justify-center">
                        {!! $qrSvg !!}
                    </div>
                @else
                    <div class="w-56 h-56 flex items-center justify-center text-slate-400 text-sm font-medium">
                        Memuat QR Code...
                    </div>
                @endif
                <div class="absolute -bottom-3 left-1/2 -translate-x-1/2 px-3 py-1 bg-indigo-600 rounded-full text-[11px] font-bold text-white shadow-md tracking-wider flex items-center gap-1.5 whitespace-nowrap">
                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Dynamic Rolling Token</span>
                </div>
            </div>

            {{-- Instructions & Security Notice --}}
            <div class="space-y-2 pt-2">
                <p class="text-sm font-semibold text-white">
                    Karyawan: Buka kamera HP &amp; scan QR Code di atas
                </p>
                <p class="text-xs text-slate-400 max-w-sm mx-auto leading-relaxed">
                    Anti-Fraud: Kode ini otomatis berganti tiap 30 detik atau seketika setelah 1 karyawan selesai absen. Foto QR tidak bisa dibagikan untuk titip absen.
                </p>
            </div>

            {{-- Controls & Actions --}}
            <div class="pt-2 flex items-center justify-center gap-3">
                <button
                    type="button"
                    wire:click="generateNextQr"
                    class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-xs font-semibold text-white transition-colors flex items-center gap-1.5 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Ganti QR Manual</span>
                </button>
                <button
                    type="button"
                    wire:click="tutupQrModal"
                    class="px-4 py-2 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 text-xs font-semibold transition-colors cursor-pointer">
                    Tutup Mesin
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- TAB 1: ABSENSI (PRESENSI HARI INI & RIWAYAT) --}}
    @if($tab === 'absensi' || $tab === 'kiosk')
    <div class="space-y-6">
        {{-- Banner Mesin Absensi Prominent --}}
        <div class="glass-card p-6 bg-gradient-to-r from-up-primary/10 via-indigo-500/10 to-transparent border border-up-primary/20 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-up-primary/20 text-up-primary flex items-center justify-center shrink-0 border border-up-primary/30">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-ink-900 dark:text-white">Mesin Dynamic QR Absensi Toko</h3>
                    <p class="text-xs text-ink-500 dark:text-ink-400 mt-0.5">
                        Buka layar modal QR di monitor PC toko / kasir. Karyawan cukup scan QR dengan kamera HP saat masuk &amp; pulang kerja.
                    </p>
                </div>
            </div>
            <button
                type="button"
                wire:click="bukaQrModal"
                class="px-6 py-3 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-sm shadow-md active:scale-95 transition-all shrink-0 cursor-pointer">
                Tampilkan QR Absensi di Layar Ini
            </button>
        </div>

        {{-- Log Kehadiran Hari Ini --}}
        <div class="glass-card p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-up-mint animate-pulse"></div>
                    <h3 class="text-lg font-bold text-ink-900 dark:text-white">Log Kehadiran Hari Ini ({{ now()->translatedFormat('d F Y') }})</h3>
                </div>
                <span class="text-xs font-semibold text-ink-500">Total: {{ count($todayLogs) }} Karyawan Tercatat</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-ink-100 dark:border-white/10 text-xs font-bold text-ink-500 uppercase tracking-wider">
                            <th class="py-3 px-4">Karyawan</th>
                            <th class="py-3 px-4">Shift</th>
                            <th class="py-3 px-4 text-center">Jam Masuk</th>
                            <th class="py-3 px-4 text-center">Jam Pulang</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-right">Metode</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100 dark:divide-white/5 font-medium">
                        @forelse($todayLogs as $tl)
                            <tr class="hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
                                <td class="py-3 px-4">
                                    <div class="font-bold text-ink-900 dark:text-white">{{ $tl->karyawan?->nama ?? '-' }}</div>
                                    <div class="text-xs text-ink-400 font-mono">{{ $tl->karyawan?->nik ?? ('EMP-'.$tl->karyawan_id) }} • {{ ucfirst($tl->karyawan?->jabatan ?? '') }}</div>
                                </td>
                                <td class="py-3 px-4 text-xs text-ink-600 dark:text-ink-400">
                                    {{ $tl->shift?->nama ?? 'Reguler' }}
                                </td>
                                <td class="py-3 px-4 text-center font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                    {{ $tl->jam_masuk ?? '-' }}
                                </td>
                                <td class="py-3 px-4 text-center font-mono font-bold text-blue-600 dark:text-blue-400">
                                    {{ $tl->jam_keluar ?? '-' }}
                                </td>
                                <td class="py-3 px-4 text-center">
                                    @php
                                        $badgeColor = match($tl->status) {
                                            'hadir' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
                                            'terlambat' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
                                            'izin', 'cuti' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
                                            default => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
                                        };
                                    @endphp
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-bold rounded-full {{ $badgeColor }}">
                                        {{ ucfirst($tl->status) }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right text-xs text-ink-500">
                                    {{ $tl->lokasi_masuk ?? 'QR Kiosk' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-ink-400 text-sm">
                                    Belum ada absensi tercatat hari ini. Klik "Buka Mesin Absensi QR Toko" untuk memunculkan scanner presensi.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Form Koreksi Manual & Log Bulanan --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1">
                <div class="glass-card p-5">
                    <h2 class="text-lg font-bold text-ink-900 dark:text-white mb-4">Tandai Status Manual / Koreksi</h2>
                    <form wire:submit="tandaiStatusAbsensi" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Karyawan</label>
                            <select wire:model="absensiKaryawanId"
                                class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-2">
                                <option value="">-- Pilih Karyawan --</option>
                                @foreach($karyawans as $k)
                                    <option value="{{ $k->id }}">{{ $k->nama }} ({{ $k->jabatan }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Tanggal</label>
                            <input type="date" wire:model="absensiTanggal"
                                class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Status</label>
                            <select wire:model="absensiStatus"
                                class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-2">
                                <option value="izin">Izin</option>
                                <option value="cuti">Cuti</option>
                                <option value="absen">Absen</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Catatan</label>
                            <textarea wire:model="absensiCatatan" rows="2"
                                class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm px-3 py-2"></textarea>
                        </div>
                        <button type="submit"
                            class="w-full inline-flex justify-center items-center px-4 py-2.5 border border-transparent text-sm font-bold rounded-xl shadow-sm text-white bg-up-primary hover:bg-up-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-up-primary active:scale-[0.97] transition min-h-[44px]">
                            Simpan Status
                        </button>
                        <button type="button" wire:click="autoAbsen"
                            class="w-full inline-flex justify-center items-center px-4 py-2.5 border border-ink-200 dark:border-white/10 text-sm font-semibold rounded-xl text-ink-700 dark:text-ink-200 bg-white dark:bg-ink-900 hover:bg-ink-50 dark:hover:bg-white/5 active:scale-[0.97] transition min-h-[44px]">
                            Auto Absen Bulan Ini
                        </button>
                    </form>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="glass-card p-5">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-2">
                        <h2 class="text-lg font-bold text-ink-900 dark:text-white">Log Absensi {{ $kpiPeriode }}</h2>
                        <select wire:model.live="absensiFilter"
                            class="rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-2">
                            <option value="semua">Semua Status</option>
                            <option value="hadir">Hadir</option>
                            <option value="terlambat">Terlambat</option>
                            <option value="absen">Absen</option>
                            <option value="izin">Izin</option>
                            <option value="cuti">Cuti</option>
                        </select>
                    </div>
                    <x-prism.dual-scroll>
                        <table class="min-w-full divide-y divide-ink-200 dark:divide-white/10 text-sm">
                            <thead class="bg-ink-50/70 dark:bg-white/[0.03]">
                                <tr>
                                    <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Tanggal</th>
                                    <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Karyawan</th>
                                    <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Masuk</th>
                                    <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Keluar</th>
                                    <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Shift</th>
                                    <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink-100 dark:divide-white/5">
                                @forelse($absensi as $a)
                                    <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors">
                                        <td class="px-3 py-2.5 text-ink-600 dark:text-ink-300">{{ \Carbon\Carbon::parse($a->tanggal)->format('d M Y') }}</td>
                                        <td class="px-3 py-2.5 font-medium text-ink-900 dark:text-white">{{ $a->karyawan?->nama ?? '-' }}</td>
                                        <td class="px-3 py-2.5 text-ink-600 dark:text-ink-300 tabular-nums">{{ $a->jam_masuk ? substr($a->jam_masuk, 0, 5) : '-' }}</td>
                                        <td class="px-3 py-2.5 text-ink-600 dark:text-ink-300 tabular-nums">{{ $a->jam_keluar ? substr($a->jam_keluar, 0, 5) : '-' }}</td>
                                        <td class="px-3 py-2.5 text-ink-600 dark:text-ink-300">{{ $a->shift?->nama ?? '-' }}</td>
                                        <td class="px-3 py-2.5">
                                            @php
                                                $warna = match($a->status) {
                                                    'terlambat' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
                                                    'absen' => 'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300',
                                                    'izin' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/40 dark:text-sky-300',
                                                    'cuti' => 'bg-purple-100 text-purple-800 dark:bg-purple-950/40 dark:text-purple-300',
                                                    default => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
                                                };
                                            @endphp
                                            <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $warna }}">{{ ucfirst($a->status) }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-3 py-8 text-center text-ink-400 dark:text-ink-500">Belum ada log absensi pada periode ini.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </x-prism.dual-scroll>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- TAB 2: SHIFT --}}
    @if($tab === 'shift')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="glass-card p-5">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white mb-4">Tambah / Ubah Shift</h2>
                <form wire:submit="simpanShift" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Nama Shift</label>
                        <input type="text" wire:model="shiftNama" placeholder="mis. Pagi"
                            class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-2">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Jam Mulai</label>
                            <input type="time" wire:model="shiftMulai"
                                class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Jam Selesai</label>
                            <input type="time" wire:model="shiftSelesai"
                                class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-2">
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-ink-700 dark:text-ink-300 cursor-pointer">
                        <input type="checkbox" wire:model="shiftAktif" class="rounded border-ink-300 dark:border-white/20 bg-white dark:bg-ink-900 text-up-primary focus:ring-up-primary">
                        <span>Aktif</span>
                    </label>
                    <button type="submit"
                        class="w-full inline-flex justify-center items-center px-4 py-2.5 border border-transparent text-sm font-bold rounded-xl shadow-sm text-white bg-up-primary hover:bg-up-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-up-primary active:scale-[0.97] transition min-h-[44px]">
                        Simpan Shift
                    </button>
                </form>
                <p class="mt-3 text-xs text-ink-500 dark:text-ink-400">Jam selesai &lt; jam mulai = shift lintas hari (overnight).</p>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="glass-card p-5">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white mb-4">Daftar Shift</h2>
                <x-prism.dual-scroll>
                    <table class="min-w-full divide-y divide-ink-200 dark:divide-white/10 text-sm">
                        <thead class="bg-ink-50/70 dark:bg-white/[0.03]">
                            <tr>
                                <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Nama</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Jam</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Tipe</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Status</th>
                                <th class="px-3 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100 dark:divide-white/5">
                            @forelse($shifts as $s)
                                <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors">
                                    <td class="px-3 py-2.5 font-medium text-ink-900 dark:text-white">{{ $s->nama }}</td>
                                    <td class="px-3 py-2.5 text-ink-600 dark:text-ink-300 tabular-nums">{{ substr($s->jam_mulai, 0, 5) }} - {{ substr($s->jam_selesai, 0, 5) }}</td>
                                    <td class="px-3 py-2.5">
                                        @if($s->apakahOvernight())
                                            <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">Overnight</span>
                                        @else
                                            <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">Reguler</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <button wire:click="toggleShift({{ $s->id }})"
                                            class="inline-flex px-2.5 py-0.5 text-xs font-semibold rounded-full cursor-pointer active:scale-95 transition {{ $s->is_aktif ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-ink-100 text-ink-600 dark:bg-white/10 dark:text-ink-400' }}">
                                            {{ $s->is_aktif ? 'Aktif' : 'Nonaktif' }}
                                        </button>
                                    </td>
                                    <td class="px-3 py-2.5 text-right text-xs text-ink-400 dark:text-ink-500 font-mono">#{{ $s->id }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-3 py-8 text-center text-ink-400 dark:text-ink-500">Belum ada shift. Tambahkan di form sebelah kiri.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-prism.dual-scroll>
            </div>
        </div>
    </div>
    @endif

    {{-- TAB 3: ROSTER --}}
    @if($tab === 'roster')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="glass-card p-5">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white mb-4">Assign Shift</h2>
                <form wire:submit="simpanRoster" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Karyawan</label>
                        <select wire:model="rosterKaryawanId"
                            class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-2">
                            <option value="">-- Pilih Karyawan --</option>
                            @foreach($karyawans as $k)
                                <option value="{{ $k->id }}">{{ $k->nama }} ({{ $k->jabatan }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Tanggal</label>
                        <input type="date" wire:model="rosterTanggal"
                            class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Shift</label>
                        <select wire:model="rosterShiftId"
                            class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-2">
                            <option value="">-- Pilih Shift --</option>
                            @foreach($shifts->where('is_aktif', true) as $s)
                                <option value="{{ $s->id }}">{{ $s->nama }} ({{ substr($s->jam_mulai, 0, 5) }}-{{ substr($s->jam_selesai, 0, 5) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit"
                        class="w-full inline-flex justify-center items-center px-4 py-2.5 border border-transparent text-sm font-bold rounded-xl shadow-sm text-white bg-up-primary hover:bg-up-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-up-primary active:scale-[0.97] transition min-h-[44px]">
                        Simpan Jadwal
                    </button>
                </form>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="glass-card p-5">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white mb-4">Jadwal Bulan {{ $kpiPeriode }}</h2>
                <x-prism.dual-scroll>
                    <table class="min-w-full divide-y divide-ink-200 dark:divide-white/10 text-sm">
                        <thead class="bg-ink-50/70 dark:bg-white/[0.03]">
                            <tr>
                                <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Tanggal</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Karyawan</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Shift</th>
                                <th class="px-3 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100 dark:divide-white/5">
                            @forelse($roster as $r)
                                <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors">
                                    <td class="px-3 py-2.5 text-ink-600 dark:text-ink-300">{{ \Carbon\Carbon::parse($r->tanggal)->format('d M Y') }}</td>
                                    <td class="px-3 py-2.5 font-medium text-ink-900 dark:text-white">{{ $r->karyawan?->nama ?? '-' }}</td>
                                    <td class="px-3 py-2.5 text-ink-600 dark:text-ink-300">{{ $r->shift?->nama ?? '-' }}</td>
                                    <td class="px-3 py-2.5 text-right">
                                        <button wire:click="hapusRoster({{ $r->id }})" class="text-xs font-semibold text-up-red hover:underline cursor-pointer active:scale-95 transition">Hapus</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-3 py-8 text-center text-ink-400 dark:text-ink-500">Belum ada jadwal pada bulan ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-prism.dual-scroll>
            </div>
        </div>
    </div>
    @endif

    {{-- TAB 4: KPI --}}
    @if($tab === 'kpi')
    <div class="glass-card p-5">
        <div class="flex flex-col sm:flex-row gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Periode (YYYY-MM)</label>
                <input type="month" wire:model.live="kpiPeriode"
                    class="block w-44 rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-2">
            </div>
            <button wire:click="hitungKpi"
                class="inline-flex items-center justify-center px-4 py-2.5 border border-transparent text-sm font-bold rounded-xl shadow-sm text-white bg-up-primary hover:bg-up-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-up-primary active:scale-[0.97] transition min-h-[44px]">
                Hitung KPI
            </button>
        </div>
        <p class="mt-2 text-xs text-ink-500 dark:text-ink-400">KPI dihitung dari data real (tiket servis, transaksi, selisih kas, lead won) — bukan input manual. Rumus whitelist, tanpa eval bebas.</p>
    </div>

    <div class="glass-card p-5">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white mb-4">Hasil KPI {{ $kpiPeriode }}</h2>
        <x-prism.dual-scroll>
            <table class="min-w-full divide-y divide-ink-200 dark:divide-white/10 text-sm">
                <thead class="bg-ink-50/70 dark:bg-white/[0.03]">
                    <tr>
                        <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Karyawan</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Metric</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Nilai Aktual</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Target</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Capaian</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100 dark:divide-white/5">
                    @forelse($kpiHasil as $h)
                        <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors">
                            <td class="px-3 py-2.5 font-medium text-ink-900 dark:text-white">{{ $h->karyawan?->nama ?? '-' }}</td>
                            <td class="px-3 py-2.5 text-ink-600 dark:text-ink-300">{{ $h->metric?->nama ?? $h->metric_id }}</td>
                            <td class="px-3 py-2.5 text-ink-600 dark:text-ink-300 tabular-nums">{{ number_format((float) $h->nilai_aktual, 0, ',', '.') }} {{ $h->metric?->satuan }}</td>
                            <td class="px-3 py-2.5 text-ink-600 dark:text-ink-300 tabular-nums">{{ number_format((float) ($h->metric?->target ?? 0), 0, ',', '.') }}</td>
                            <td class="px-3 py-2.5">
                                @php
                                    $persen = (float) $h->persen_capaian;
                                    $warna = $persen >= 100 ? 'text-up-mint font-bold' : ($persen >= 70 ? 'text-up-amber font-semibold' : 'text-up-red font-semibold');
                                @endphp
                                <span class="tabular-nums {{ $warna }}">{{ number_format($persen, 1, ',', '.') }}%</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-8 text-center text-ink-400 dark:text-ink-500">Belum ada hasil KPI. Klik "Hitung KPI".</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-prism.dual-scroll>
    </div>
    @endif

    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('tab-changed', (event) => {
                if (event.url && window.location.href !== event.url) {
                    window.history.pushState(null, '', event.url);
                }
            });

            window.addEventListener('popstate', () => {
                const parts = window.location.pathname.split('/');
                const last = parts[parts.length - 1];
                if (last && @this) {
                    @this.call('pilihTab', last);
                }
            });
        });
    </script>
</div>
