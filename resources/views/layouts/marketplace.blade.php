<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- [T-32] Tema: flag zona + status staf untuk themeManager -->
    <script>
        window.UTE_ZONE = 'marketplace';
        window.UTE_AUTHED = false;
        window.UTE_THEME = null;
    </script>
    <script>
        // [T-32] Bootstrap tema — terapkan sebelum cat pertama untuk mencegah FOUC
        (function () {
            var pref = window.UTE_THEME;
            if (!pref) { try { pref = localStorage.getItem('ute-theme'); } catch (e) {} }
            if (!pref) pref = window.UTE_ZONE === 'marketplace' ? 'light' : 'dark';
            var dark = pref === 'dark' || (pref === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            var root = document.documentElement;
            root.dataset.theme = dark ? 'dark' : 'light';
            root.classList.toggle('dark', dark);
        })();
    </script>

    <!-- PWA Meta Tags -->
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#5B4FE9">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Ute Parts">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192x192.png') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/icon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">

    <title>{{ $title ?? 'Ute Parts' }} — Toko Sparepart & Servis HP</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-ink-950 text-ink-100 font-sans antialiased min-h-screen flex flex-col overflow-x-clip selection:bg-up-primary selection:text-white"
      style="padding-bottom: env(safe-area-inset-bottom, 0);">

    <!-- Top Bar -->
    <header class="bg-white/80 dark:bg-ink-900/80 backdrop-blur-xl border-b border-ink-500/25 dark:border-white/10 sticky top-0 z-40"
        style="padding-top: env(safe-area-inset-top, 0);">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 h-16 flex items-center justify-between gap-2 sm:gap-4">
            <!-- Brand -->
            <x-prism.logo size="md" :with-text="true" mode="auto" subtitle="Sparepart & Servis HP" :href="route('shop')" text-class="hidden xs:block" />

            <!-- Nav -->
            <nav class="flex items-center gap-1 sm:gap-2">
                <a href="{{ route('shop') }}" class="min-h-11 px-2.5 sm:px-3.5 py-2 rounded-xl text-xs sm:text-sm font-semibold {{ request()->is('shop') || request()->is('shop/*') ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-500 hover:bg-ink-50 hover:text-ink-900 dark:hover:bg-white/5 dark:hover:text-ink-100' }} transition-colors inline-flex items-center">Katalog</a>
                <a href="{{ route('servis.booking') }}" class="min-h-11 px-2.5 sm:px-3.5 py-2 rounded-xl text-xs sm:text-sm font-semibold {{ request()->routeIs('servis.booking') ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-500 hover:bg-ink-50 hover:text-ink-900 dark:hover:bg-white/5 dark:hover:text-ink-100' }} transition-colors inline-flex items-center">Booking Servis</a>

                <!-- Cart -->
                <a href="{{ route('cart') }}" class="relative min-h-11 px-2.5 sm:px-3.5 py-2 rounded-xl text-xs sm:text-sm font-semibold {{ request()->is('cart') || request()->is('checkout') ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-500 hover:bg-ink-50 hover:text-ink-900 dark:hover:bg-white/5 dark:hover:text-ink-100' }} transition-colors flex items-center gap-1.5" aria-label="Keranjang belanja">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span class="hidden sm:inline">Keranjang</span>
                    @if(session('shop.cart'))
                        <span class="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-up-accent text-white text-[10px] font-bold flex items-center justify-center shadow">
                            {{ array_sum(array_column(session('shop.cart'), 'qty')) }}
                        </span>
                    @endif
                </a>

                <!-- [T-32] Toggle tema: sun (gelap) / moon (terang) / monitor (auto) -->
                <button type="button" x-data="themeManager()" @click="cycleTheme()"
                        title="Ganti tema" aria-label="Ganti tema"
                        class="min-h-11 min-w-11 p-2.5 rounded-xl text-ink-500 hover:bg-ink-50 hover:text-ink-900 dark:text-ink-100 dark:hover:bg-white/5 dark:hover:text-white transition-colors cursor-pointer">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-show="theme === 'dark'" x-cloak>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-show="theme === 'light'" x-cloak>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-show="theme === 'auto'" x-cloak>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </button>

                <!-- Customer / Login -->
                @auth('customer')
                    <a href="{{ route('customer.account') }}" class="min-h-11 px-2.5 sm:px-3.5 py-2 rounded-xl text-xs sm:text-sm font-semibold text-ink-500 hover:bg-ink-50 hover:text-ink-900 dark:hover:bg-white/5 dark:hover:text-ink-100 transition-colors flex items-center gap-2" aria-label="Akun saya">
                        <span class="w-7 h-7 rounded-full bg-up-mint/20 text-up-mint flex items-center justify-center text-xs font-bold flex-shrink-0">
                            {{ substr(auth('customer')->user()->nama, 0, 1) }}
                        </span>
                        <span class="hidden sm:inline max-w-[100px] truncate">{{ auth('customer')->user()->nama }}</span>
                    </a>
                    <form action="{{ route('customer.logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="min-h-11 px-2.5 sm:px-3.5 py-2 rounded-xl text-xs sm:text-sm font-semibold text-up-red hover:bg-up-red/10 transition-colors cursor-pointer inline-flex items-center gap-1.5" aria-label="Keluar">
                            <svg class="w-4 h-4 sm:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            <span class="hidden sm:inline">Keluar</span>
                        </button>
                    </form>
                @else
                    <a href="{{ route('customer.login') }}" class="min-h-11 px-2.5 sm:px-3.5 py-2 rounded-xl text-xs sm:text-sm font-semibold border border-ink-200 text-ink-500 hover:bg-ink-50 dark:text-ink-100 dark:hover:bg-white/5 transition-colors inline-flex items-center gap-1.5" aria-label="Masuk">
                        <svg class="w-4 h-4 sm:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                        </svg>
                        <span class="hidden sm:inline">Masuk</span>
                    </a>
                @endauth
            </nav>
        </div>
    </header>

    <!-- Content -->
    <main class="flex-1 pb-20 md:pb-0">
        {{ $slot }}
    </main>

    <!-- [T-49] Mobile Bottom Navigation Bar (md:hidden) -->
    <nav class="md:hidden fixed bottom-0 inset-x-0 bg-white/90 dark:bg-ink-900/90 backdrop-blur-xl border-t border-ink-500/20 dark:border-white/10 z-40 px-3 py-1 flex items-center justify-around"
         style="padding-bottom: max(env(safe-area-inset-bottom, 0px), 0.5rem);">
        <a href="{{ route('shop') }}" class="flex flex-col items-center justify-center min-h-[44px] min-w-[56px] text-xs font-medium {{ request()->is('shop') || request()->is('shop/*') ? 'text-up-primary' : 'text-ink-400 hover:text-ink-100' }}">
            <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
            </svg>
            <span>Katalog</span>
        </a>

        <a href="{{ route('servis.booking') }}" class="flex flex-col items-center justify-center min-h-[44px] min-w-[56px] text-xs font-medium {{ request()->routeIs('servis.booking') ? 'text-up-primary' : 'text-ink-400 hover:text-ink-100' }}">
            <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            <span>Booking</span>
        </a>

        <a href="{{ route('cart') }}" class="relative flex flex-col items-center justify-center min-h-[44px] min-w-[56px] text-xs font-medium {{ request()->is('cart') || request()->is('checkout') ? 'text-up-primary' : 'text-ink-400 hover:text-ink-100' }}">
            <div class="relative">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                @if(session('shop.cart'))
                    <span class="absolute -top-1 -right-2 w-4 h-4 rounded-full bg-up-accent text-white text-[9px] font-bold flex items-center justify-center shadow">
                        {{ array_sum(array_column(session('shop.cart'), 'qty')) }}
                    </span>
                @endif
            </div>
            <span>Keranjang</span>
        </a>

        @auth('customer')
            <a href="{{ route('customer.account') }}" class="flex flex-col items-center justify-center min-h-[44px] min-w-[56px] text-xs font-medium {{ request()->routeIs('customer.account') ? 'text-up-primary' : 'text-ink-400 hover:text-ink-100' }}">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span>Akun</span>
            </a>
        @else
            <a href="{{ route('customer.login') }}" class="flex flex-col items-center justify-center min-h-[44px] min-w-[56px] text-xs font-medium {{ request()->routeIs('customer.login') ? 'text-up-primary' : 'text-ink-400 hover:text-ink-100' }}">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                </svg>
                <span>Masuk</span>
            </a>
        @endauth
    </nav>

    <!-- Footer -->
    <footer class="border-t border-ink-500/25 bg-white dark:bg-ink-950 dark:border-white/10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <x-prism.logo size="sm" :with-text="true" mode="auto" subtitle="Pusat Sparepart & Servis HP, multi-cabang." :href="route('shop')" />
            </div>
            <p class="text-xs text-ink-400">© {{ date('Y') }} Ute Parts. Semua hak dilindungi.</p>
        </div>
    </footer>

    @livewireScripts
</body>
</html>