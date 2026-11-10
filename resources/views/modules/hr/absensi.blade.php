<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-ink-900 dark:text-white">Absensi, Shift &amp; KPI</h1>
            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Roster shift, log absensi, dan KPI karyawan dari data real</p>
        </div>
    </div>

    {{-- Tabs & Period Picker --}}
    <div class="glass-card p-2 sm:p-2.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-2 overflow-x-auto scrollbar-none py-1 -my-1 flex-nowrap">
            @foreach(['kiosk' => '⚡ Mesin Absensi', 'shift' => 'Shift', 'roster' => 'Roster', 'absensi' => 'Absensi', 'kpi' => 'KPI'] as $key => $label)
                <a
                    href="{{ url('/app/hr/absensi/'.$key) }}"
                    wire:click.prevent="pilihTab('{{ $key }}')"
                    class="px-4 py-2.5 text-xs sm:text-sm font-bold rounded-xl transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer flex items-center justify-center
                    {{ $tab === $key ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-600 dark:text-ink-400 hover:bg-black/5 dark:hover:bg-white/5 hover:text-ink-900 dark:hover:text-white' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
        @if(!in_array($tab, ['shift', 'kiosk']))
            <div class="flex items-center gap-2 px-1">
                <label class="text-xs font-semibold text-ink-500 dark:text-ink-400 uppercase tracking-wider whitespace-nowrap">Periode:</label>
                <input type="month" wire:model.live="kpiPeriode"
                    class="rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-1.5">
            </div>
        @endif
    </div>

    {{-- Tab: Mesin Absensi Kiosk --}}
    @if($tab === 'kiosk')
    <div
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
            playBeep(success) {
                try {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    if (success) {
                        osc.frequency.setValueAtTime(880, ctx.currentTime);
                        osc.frequency.setValueAtTime(1320, ctx.currentTime + 0.1);
                        gain.gain.setValueAtTime(0.2, ctx.currentTime);
                        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.25);
                        osc.start(ctx.currentTime);
                        osc.stop(ctx.currentTime + 0.25);
                    } else {
                        osc.frequency.setValueAtTime(300, ctx.currentTime);
                        osc.frequency.setValueAtTime(200, ctx.currentTime + 0.15);
                        gain.gain.setValueAtTime(0.3, ctx.currentTime);
                        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.35);
                        osc.start(ctx.currentTime);
                        osc.stop(ctx.currentTime + 0.35);
                    }
                } catch(e) {}
            }
        }"
        x-on:kiosk-beep.window="playBeep($event.detail.success)"
        class="space-y-6">

        {{-- Hero Header Mesin Absensi --}}
        <div class="glass-card p-6 sm:p-8 bg-gradient-to-br from-up-primary/10 via-transparent to-up-mint/5 border border-up-primary/20 rounded-2xl relative overflow-hidden">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6 relative z-10">
                <div class="text-center md:text-left space-y-2">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-up-primary/20 border border-up-primary/30 text-up-primary text-xs font-bold uppercase tracking-wider">
                        <span class="w-2 h-2 rounded-full bg-up-mint animate-ping"></span>
                        Mesin Absensi Karyawan Aktif
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-ink-900 dark:text-white tracking-tight">
                        Scan Barcode / NIK untuk Check-In &amp; Check-Out
                    </h2>
                    <p class="text-sm text-ink-600 dark:text-ink-400 max-w-xl">
                        Arahkan barcode scanner fisik atau ketik NIK/ID karyawan. Sistem secara otomatis mencatat kehadiran real-time dan memperbarui skor KPI kehadiran.
                    </p>
                </div>

                {{-- Live Clock Display --}}
                <div class="bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 rounded-2xl px-6 py-4 text-center min-w-[220px] shadow-sm backdrop-blur-md">
                    <div class="text-3xl sm:text-4xl font-mono font-black text-up-primary tracking-widest" x-text="timeStr || '--:--:--'">--:--:--</div>
                    <div class="text-xs font-semibold text-ink-500 dark:text-ink-400 mt-1 uppercase" x-text="dateStr || 'Hari ini'">Hari ini</div>
                </div>
            </div>

            {{-- Scanner Input Form --}}
            <div class="mt-8 max-w-2xl mx-auto">
                <form wire:submit="scanBarcodeKiosk" class="relative flex items-center shadow-lg rounded-2xl">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-up-primary">
                        <svg class="w-6 h-6 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                        </svg>
                    </div>
                    <input
                        type="text"
                        wire:model="barcodeInput"
                        wire:keydown.enter.prevent="scanBarcodeKiosk"
                        autofocus
                        id="kiosk-barcode-input"
                        placeholder="Scan barcode kartu atau ketik NIK (misal: EMP-0001 lalu tekan Enter)..."
                        class="w-full pl-12 pr-32 py-4 text-base sm:text-lg font-mono rounded-2xl border-2 border-up-primary/40 focus:border-up-primary bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-inner focus:ring-4 focus:ring-up-primary/20 transition-all outline-none"
                    />
                    <button
                        type="button"
                        wire:click="scanBarcodeKiosk"
                        class="absolute right-2 px-5 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-sm shadow-md transition-all active:scale-95 flex items-center gap-1.5 cursor-pointer">
                        <span>Check</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </button>
                </form>

                {{-- Quick employee scan chips for testing --}}
                <div class="mt-3 flex flex-wrap items-center justify-center gap-2 text-xs text-ink-500">
                    <span class="font-medium">Scan Cepat Demo:</span>
                    @foreach($karyawans as $k)
                        <button
                            type="button"
                            wire:click="scanKaryawan('{{ $k->nik ?? ('EMP-'.str_pad($k->id, 4, '0', STR_PAD_LEFT)) }}')"
                            class="px-2.5 py-1 rounded-lg bg-black/5 dark:bg-white/5 hover:bg-up-primary/10 hover:text-up-primary border border-black/10 dark:border-white/10 font-mono transition-colors cursor-pointer">
                            {{ $k->nama }} ({{ $k->nik ?? 'EMP-'.$k->id }})
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Result Banner Popup/Card --}}
        @if($kioskResult)
            <div class="glass-card p-6 rounded-2xl border-2 {{ ($kioskResult['success'] ?? false) ? (($kioskResult['action'] ?? '') === 'checkin' ? 'border-emerald-500/50 bg-emerald-500/10' : (($kioskResult['action'] ?? '') === 'checkout' ? 'border-blue-500/50 bg-blue-500/10' : 'border-amber-500/50 bg-amber-500/10')) : 'border-red-500/50 bg-red-500/10' }} transition-all">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-2xl flex items-center justify-center font-bold text-2xl shadow-md {{ ($kioskResult['success'] ?? false) ? (($kioskResult['action'] ?? '') === 'checkin' ? 'bg-emerald-500 text-white' : 'bg-blue-600 text-white') : 'bg-red-500 text-white' }}">
                            @if(($kioskResult['success'] ?? false))
                                @if(($kioskResult['action'] ?? '') === 'checkin')
                                    ✓
                                @elseif(($kioskResult['action'] ?? '') === 'checkout')
                                    👋
                                @else
                                    ℹ
                                @endif
                            @else
                                ✕
                            @endif
                        </div>
                        <div>
                            <div class="text-xs font-bold uppercase tracking-wider {{ ($kioskResult['success'] ?? false) ? 'text-up-primary' : 'text-red-500' }}">
                                {{ ($kioskResult['success'] ?? false) ? (strtoupper($kioskResult['action'] ?? 'ABSENSI')) : 'GAGAL' }}
                            </div>
                            <h3 class="text-xl font-black text-ink-900 dark:text-white mt-0.5">
                                {{ $kioskResult['message'] ?? '' }}
                            </h3>
                            @if(isset($kioskResult['karyawan']))
                                <p class="text-xs text-ink-600 dark:text-ink-400 mt-1">
                                    <span class="font-bold">{{ $kioskResult['karyawan']['nama'] }}</span> •
                                    NIK: <span class="font-mono">{{ $kioskResult['karyawan']['nik'] }}</span> •
                                    Posisi: {{ ucfirst($kioskResult['karyawan']['jabatan']) }} •
                                    Lokasi: {{ $kioskResult['karyawan']['cabang'] }}
                                </p>
                            @endif
                        </div>
                    </div>
                    <button
                        type="button"
                        wire:click="resetKioskResult"
                        class="px-4 py-2 text-xs font-semibold rounded-xl bg-black/10 dark:bg-white/10 hover:bg-black/20 dark:hover:bg-white/20 text-ink-700 dark:text-ink-300 transition-colors">
                        Tutup
                    </button>
                </div>
            </div>
        @endif

        {{-- Stream Aktivitas Absensi Hari Ini --}}
        <div class="glass-card p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-up-mint animate-pulse"></div>
                    <h3 class="text-lg font-bold text-ink-900 dark:text-white">Log Kehadiran Hari Ini ({{ now()->translatedFormat('d F Y') }})</h3>
                </div>
                <span class="text-xs text-ink-500">Total Hari Ini: {{ count($todayLogs) }} Karyawan</span>
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
                                    {{ $tl->lokasi_masuk ?? 'Kiosk Barcode' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-ink-400 text-sm">
                                    Belum ada absensi tercatat hari ini. Scan kartu barcode karyawan untuk memulai.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- Tab: Shift --}}
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

    {{-- Tab: Roster --}}
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

    {{-- Tab: Absensi --}}
    @if($tab === 'absensi')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="glass-card p-5">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white mb-4">Tandai Status Manual</h2>
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
    @endif

    {{-- Tab: KPI --}}
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
