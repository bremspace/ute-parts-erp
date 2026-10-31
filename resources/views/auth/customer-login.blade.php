<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk Pelanggan — {{ config('app.name', 'Bremspace') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/icon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $bgImage = $webConfig['customer_login_bg_image'] ?? '';
    $overlayMode = $webConfig['customer_login_bg_overlay'] ?? 'scrim_dark';
    $waNumber = $webConfig['wa_number'] ?? '6281234567890';
    $waNumberClean = preg_replace('/[^0-9]/', '', $waNumber);
    $waMsg = urlencode("Halo Admin " . config('app.name', 'Bremspace') . ", saya lupa kata sandi akun pelanggan saya. Mohon bantuan reset kata sandi.");
@endphp
<body class="bg-[#0B0F19] text-ink-900 font-sans antialiased min-h-screen flex flex-col items-center justify-center p-4 relative overflow-hidden selection:bg-up-primary selection:text-white">
    <!-- Dynamic Custom Background Foto & Scrim Overlay -->
    @if(!empty($bgImage))
        <img src="{{ $bgImage }}" alt="Background" class="absolute inset-0 w-full h-full object-cover select-none pointer-events-none z-0">
        @if($overlayMode === 'scrim_heavy')
            <div class="absolute inset-0 bg-[#070B14]/85 backdrop-blur-[2px] z-[1]"></div>
        @elseif($overlayMode === 'frosted_glass')
            <div class="absolute inset-0 bg-black/45 backdrop-blur-md z-[1]"></div>
        @else
            <!-- scrim_dark default -->
            <div class="absolute inset-0 bg-gradient-to-t from-[#070B14] via-[#070B14]/75 to-transparent z-[1]"></div>
        @endif
    @else
        <!-- Modern Default Atmospheric Background -->
        <div class="absolute inset-0 bg-gradient-to-br from-[#0B0F19] via-[#0F172A] to-[#0A0E1A] z-0"></div>
        <div class="absolute top-[-10%] left-[-10%] w-[50vw] h-[50vw] rounded-full bg-up-primary/15 blur-[120px] pointer-events-none"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[40vw] h-[40vw] rounded-full bg-up-mint/10 blur-[120px] pointer-events-none"></div>
    @endif

    <div class="w-full max-w-md relative z-10 my-6">
        <div class="text-center mb-6 flex flex-col items-center justify-center">
            <x-prism.logo size="lg" layout="vertical" mode="dark" title="Masuk Akun" subtitle="Lihat harga member & lanjutkan checkout" :href="route('shop')" />
        </div>

        <div class="rounded-3xl shadow-2xl p-6 sm:p-8 relative overflow-hidden transition-all duration-200 {{ !empty($bgImage) ? 'bg-white/90 dark:bg-[#0F172A]/90 backdrop-blur-xl border border-white/20' : 'bg-white dark:bg-[#0F172A] border border-slate-200 dark:border-white/10' }}">
            <div class="circuit-line absolute top-0 left-0 w-full h-[2px]"></div>

            @if(session('info'))
                <div class="mb-5 p-3.5 rounded-xl bg-up-primary/10 border border-up-primary/20 text-up-primary text-xs font-semibold leading-relaxed flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-up-primary animate-ping"></span>
                    <span>{{ session('info') }}</span>
                </div>
            @endif

            @if(session('status'))
                <div class="mb-5 p-3.5 rounded-xl bg-up-mint/10 border border-up-mint/20 text-up-mint text-xs font-semibold leading-relaxed">
                    {{ session('status') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-5 p-3.5 rounded-xl bg-up-red/10 border border-up-red/20 text-up-red text-xs font-medium">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('customer.login.post') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="telepon" class="block text-xs font-semibold text-ink-700 dark:text-ink-200 mb-1.5">
                        Nomor HP / WhatsApp / Username
                    </label>
                    <div class="relative">
                        <input type="text" id="telepon" name="telepon" value="{{ old('telepon') }}" required autofocus placeholder="08xx atau username"
                            class="w-full px-4 py-3 rounded-xl bg-ink-50 dark:bg-black/30 border border-ink-200 dark:border-white/10 focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 outline-none text-sm font-medium text-ink-900 dark:text-white placeholder:text-ink-400" />
                    </div>
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-xs font-semibold text-ink-700 dark:text-ink-200">
                            Kata Sandi
                        </label>
                        <!-- Trigger Pop-up Info Lupa Kata Sandi via CS WhatsApp / Toko -->
                        <a href="https://wa.me/{{ $waNumberClean }}?text={{ $waMsg }}" target="_blank" rel="noopener noreferrer"
                           class="text-[11px] font-semibold text-up-primary hover:text-up-primary-dark dark:hover:text-indigo-400 transition-colors">
                            Lupa kata sandi?
                        </a>
                    </div>
                    <input type="password" id="password" name="password" required placeholder="••••••••"
                        class="w-full px-4 py-3 rounded-xl bg-ink-50 dark:bg-black/30 border border-ink-200 dark:border-white/10 focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 outline-none text-sm font-medium text-ink-900 dark:text-white placeholder:text-ink-400" />
                </div>

                <button type="submit"
                        class="w-full py-3.5 rounded-xl bg-gradient-to-r from-up-primary to-indigo-600 hover:from-up-primary-dark text-white font-bold text-sm shadow-lg shadow-up-primary/25 transition-[transform,background-color] duration-140 cursor-pointer active:scale-[0.97] min-h-[44px]">
                    Masuk ke Akun
                </button>
            </form>

            <div class="mt-5 pt-4 border-t border-slate-100 dark:border-white/10 text-center space-y-2">
                <p class="text-xs text-ink-500 dark:text-ink-400">
                    Belum punya akun?
                    <a href="{{ route('customer.register') }}" class="text-up-primary font-bold hover:underline">Daftar gratis</a>
                </p>

                <p class="text-[11px] text-ink-400 dark:text-ink-500 flex items-center justify-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span>Lupa password? Hubungi CS Toko untuk reset instan.</span>
                </p>
            </div>
        </div>

        <p class="text-center text-xs text-ink-400 mt-6">
            <a href="{{ route('shop') }}" class="hover:text-up-primary transition-colors">← Kembali ke katalog produk</a>
        </p>
    </div>
</body>
</html>