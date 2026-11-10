<div class="space-y-4" x-data="{
    showPassword: false,
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

    {{-- Status Akun Logged In --}}
    @if ($isLoggedIn && $karyawan)
        <div class="px-3 py-2 rounded-xl bg-slate-800/80 border border-slate-700/80 flex items-center justify-between text-xs">
            <div class="flex items-center gap-2">
                <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                <span class="text-slate-300">
                    Akun: <strong class="text-white">{{ $karyawan->nama }}</strong>
                    <span class="text-[10px] text-slate-400 font-mono">({{ $karyawan->nik ?? '-' }})</span>
                </span>
            </div>
            <button
                type="button"
                wire:click="logout"
                class="text-[11px] text-rose-400 hover:text-rose-300 transition-colors cursor-pointer">
                Keluar
            </button>
        </div>
    @endif

    {{-- Alert Error --}}
    @if ($errorMessage)
        <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm flex items-start gap-3">
            <svg class="w-5 h-5 text-rose-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div class="flex-1">
                <p class="font-semibold">Perhatian</p>
                <p class="text-xs mt-0.5 opacity-90">{{ $errorMessage }}</p>
                @if ($isLoggedIn)
                    <div class="mt-3">
                        <span class="inline-block text-[11px] text-slate-400">
                            Silakan arahkan kamera HP ke QR Code terbaru di monitor kasir.
                        </span>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Result Card (Presensi Berhasil) --}}
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
                ✓ Otomatis terverifikasi via akun login Anda. Halaman dapat ditutup.
            </p>
        </div>
    @elseif (!$isLoggedIn)
        {{-- Form Login Karyawan (Belum Login) --}}
        <div class="p-6 rounded-2xl bg-slate-900/90 border border-slate-800 backdrop-blur-md shadow-2xl">
            <div class="mb-4 pb-3 border-b border-slate-800">
                <h2 class="text-sm font-bold text-white flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    Masuk ke Akun Karyawan
                </h2>
                <p class="text-xs text-slate-400 mt-1">
                    Login sekali di perangkat ini agar setiap scan QR berikutnya langsung otomatis check-in.
                </p>
            </div>

            <form wire:submit.prevent="loginDanAbsen" class="space-y-4">
                <div>
                    <label for="login-id" class="block text-xs font-medium text-slate-300 mb-1">
                        Email atau NIK Karyawan:
                    </label>
                    <input
                        type="text"
                        id="login-id"
                        wire:model="loginIdentifier"
                        autofocus
                        required
                        placeholder="nama@uteparts.id / EMP-0002"
                        class="w-full px-4 py-3 rounded-xl bg-slate-800/80 border border-slate-700 text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm"
                    />
                </div>

                <div>
                    <label for="login-pass" class="block text-xs font-medium text-slate-300 mb-1">
                        Kata Sandi:
                    </label>
                    <div class="relative">
                        <input
                            :type="showPassword ? 'text' : 'password'"
                            id="login-pass"
                            wire:model="password"
                            required
                            placeholder="••••••••"
                            class="w-full px-4 py-3 rounded-xl bg-slate-800/80 border border-slate-700 text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm"
                        />
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white text-xs">
                            <span x-text="showPassword ? 'Sembunyikan' : 'Lihat'"></span>
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-2 pt-1">
                    <input
                        type="checkbox"
                        id="remember-me"
                        wire:model="rememberMe"
                        class="w-4 h-4 rounded text-indigo-600 bg-slate-800 border-slate-700 focus:ring-indigo-500 focus:ring-offset-slate-900"
                    />
                    <label for="remember-me" class="text-xs text-slate-300 cursor-pointer">
                        Ingat saya di perangkat ini (Auto Check-in)
                    </label>
                </div>

                <button
                    type="submit"
                    class="w-full py-3.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm shadow-lg shadow-indigo-600/30 active:scale-[0.98] transition-all flex items-center justify-center gap-2 cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                    </svg>
                    <span>Masuk & Check-in Otomatis</span>
                </button>
            </form>
        </div>
    @endif

    {{-- Footer Info --}}
    <div class="text-center text-[11px] text-slate-500 pt-2">
        <p>Sistem Kehadiran & KPI Terintegrasi</p>
        <p class="font-mono mt-0.5">UTEParts ERP • Autentikasi Karyawan Terpadu</p>
    </div>
</div>
