<div class="space-y-4" x-data="{
    playChime() {
        try {
            if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            gain.gain.setValueAtTime(0.2, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
            osc.start();
            osc.stop(ctx.currentTime + 0.4);
        } catch(e) {}
    }
}" x-init="
    $wire.on('absensi-success', () => { playChime(); });
">
    {{-- Header --}}
    <div class="text-center pt-2 pb-1">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-indigo-600/20 text-indigo-400 mb-2 border border-indigo-500/30">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
        </div>
        <h1 class="text-xl font-bold text-white tracking-tight">UTEParts Absensi</h1>
        <p class="text-xs text-slate-400">Verifikasi Presensi Toko Mandiri</p>
    </div>

    {{-- Alert Error --}}
    @if ($errorMessage)
        <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm flex items-start gap-3 animate-shake">
            <svg class="w-5 h-5 text-rose-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div class="flex-1">
                <p class="font-semibold">Absensi Ditolak</p>
                <p class="text-xs mt-0.5 opacity-90">{{ $errorMessage }}</p>
                <div class="mt-3">
                    <a href="javascript:history.back()" class="inline-block text-xs font-semibold px-3 py-1.5 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 text-rose-200 transition-colors">
                        ← Kembali / Scan Ulang
                    </a>
                </div>
            </div>
        </div>
    @endif

    {{-- Result Card --}}
    @if ($result)
        @php
            $isCheckin = ($result['action'] ?? '') === 'checkin';
            $bgColor = $isCheckin ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-300' : 'bg-cyan-500/15 border-cyan-500/40 text-cyan-300';
            $badgeBg = $isCheckin ? 'bg-emerald-500 text-white' : 'bg-cyan-500 text-white';
        @endphp
        <div class="p-6 rounded-2xl border {{ $bgColor }} text-center relative overflow-hidden backdrop-blur-sm shadow-xl">
            <div class="w-16 h-16 rounded-full mx-auto flex items-center justify-center mb-3 {{ $badgeBg }} shadow-lg">
                @if ($isCheckin)
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                @else
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                @endif
            </div>

            <span class="inline-block px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider {{ $badgeBg }} mb-2">
                {{ $isCheckin ? 'CHECK-IN SUKSES' : 'CHECK-OUT SUKSES' }}
            </span>

            <h2 class="text-xl font-bold text-white mt-1">{{ $result['karyawan']['nama'] ?? 'Karyawan' }}</h2>
            <p class="text-xs text-slate-300 font-mono mt-0.5">
                NIK: {{ $result['karyawan']['nik'] ?? '-' }} • {{ ucfirst($result['karyawan']['jabatan'] ?? 'Staff') }}
            </p>

            <div class="mt-4 p-3 rounded-xl bg-black/30 border border-white/10 text-left text-xs space-y-1">
                <div class="flex justify-between">
                    <span class="text-slate-400">Jam Presensi:</span>
                    <span class="font-mono font-bold text-white">
                        {{ $isCheckin ? ($result['jam_masuk'] ?? now()->format('H:i:s')) : ($result['jam_keluar'] ?? now()->format('H:i:s')) }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Status Kehadiran:</span>
                    <span class="font-semibold {{ ($result['status'] ?? '') === 'terlambat' ? 'text-amber-400' : 'text-emerald-400' }}">
                        {{ ucfirst($result['status'] ?? 'Hadir') }}
                        @if(!empty($result['menit_terlambat']))
                            ({{ $result['menit_terlambat'] }} mnt)
                        @endif
                    </span>
                </div>
                @if(!$isCheckin && !empty($result['durasi_kerja']))
                    <div class="flex justify-between">
                        <span class="text-slate-400">Total Durasi Kerja:</span>
                        <span class="font-bold text-cyan-300">{{ $result['durasi_kerja'] }}</span>
                    </div>
                @endif
            </div>

            <p class="text-[11px] text-slate-400 mt-4">
                ✓ KPI Kehadiran tersinkronisasi otomatis. Anda dapat menutup halaman ini.
            </p>
        </div>
    @elseif (!$errorMessage)
        {{-- Submission Form --}}
        <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 backdrop-blur-md shadow-2xl">
            <div class="flex items-center gap-2 mb-4 pb-3 border-b border-slate-800">
                <div class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></div>
                <span class="text-xs font-semibold text-emerald-400">QR Toko Terverifikasi & Aktif</span>
            </div>

            <form wire:submit.prevent="submitAbsensi" class="space-y-4">
                <div>
                    <label for="nik-input" class="block text-xs font-medium text-slate-300 mb-1.5">
                        NIK / ID Karyawan Anda:
                    </label>
                    <div class="relative">
                        <input
                            type="text"
                            id="nik-input"
                            wire:model="nikInput"
                            autofocus
                            required
                            placeholder="Contoh: EMP-0001 / MAR2026302"
                            class="w-full px-4 py-3 rounded-xl bg-slate-800/80 border border-slate-700 text-white font-mono placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm uppercase"
                        />
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">
                        Masukkan NIK yang terdaftar pada kartu karyawan Anda.
                    </p>
                </div>

                <div class="flex items-center gap-2 pt-1">
                    <input
                        type="checkbox"
                        id="remember"
                        wire:model="rememberDevice"
                        class="w-4 h-4 rounded text-indigo-600 bg-slate-800 border-slate-700 focus:ring-indigo-500 focus:ring-offset-slate-900"
                    />
                    <label for="remember" class="text-xs text-slate-300 cursor-pointer">
                        Ingat akun di HP ini (otomatis untuk scan berikutnya)
                    </label>
                </div>

                <button
                    type="submit"
                    class="w-full py-3.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm shadow-lg shadow-indigo-600/30 active:scale-[0.98] transition-all flex items-center justify-center gap-2 cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Konfirmasi Absensi Sekarang</span>
                </button>
            </form>
        </div>
    @endif

    {{-- Footer Info --}}
    <div class="text-center text-[11px] text-slate-500 pt-2">
        <p>Sistem Kehadiran & KPI Terintegrasi</p>
        <p class="font-mono mt-0.5">UTEParts ERP v2.4 • Keamanan Presensi Realtime</p>
    </div>
</div>
