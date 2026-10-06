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
            var pref = null;
            try { pref = localStorage.getItem('ute-theme'); } catch (e) {}
            if (!pref) pref = window.UTE_THEME;
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
<body class="bg-up-ink-50 dark:bg-ink-950 text-ink-900 dark:text-ink-100 font-sans antialiased min-h-screen flex flex-col overflow-x-clip selection:bg-up-primary selection:text-white"
      style="padding-bottom: env(safe-area-inset-bottom, 0);">

    <!-- Top Bar: Apple-inspired frosted glass bar with refined typography & micro-details -->
    <header class="bg-white/75 dark:bg-ink-950/75 backdrop-blur-2xl border-b border-ink-100/80 dark:border-white/[0.08] sticky top-0 z-40 transition-colors duration-200 shadow-[0_2px_12px_rgba(0,0,0,0.03)] dark:shadow-[0_4px_20px_rgba(0,0,0,0.4)]"
        style="padding-top: env(safe-area-inset-top, 0);">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 h-16 flex items-center justify-between gap-2 sm:gap-3">
            <!-- Brand -->
            <div class="flex items-center gap-3 sm:gap-6 min-w-0 flex-shrink">
                <x-prism.logo size="md" :with-text="true" mode="auto" subtitle="Precision Parts & Repair" :href="route('shop')" text-class="hidden xs:block tracking-tight font-bold" />
                <div class="hidden lg:flex items-center gap-1 pl-4 border-l border-ink-200/50 dark:border-white/10 text-xs font-medium text-ink-500 dark:text-ink-400">
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-up-mint/10 text-up-mint text-[11px] font-semibold">
                        <span class="w-1.5 h-1.5 rounded-full bg-up-mint animate-pulse"></span> Certified Labs
                    </span>
                    <span class="text-ink-400 dark:text-ink-600">•</span>
                    <span>Garansi Toko Resmi</span>
                </div>
            </div>

            <!-- Nav -->
            <nav class="flex items-center gap-1 sm:gap-2 flex-shrink-0">
                <a href="{{ route('shop') }}"
                   class="min-h-[44px] px-2.5 sm:px-3.5 py-2 rounded-full text-xs sm:text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.97] inline-flex items-center gap-1.5 {{ request()->is('shop') || request()->is('shop/*') ? 'bg-ink-900 text-white dark:bg-white dark:text-ink-950 shadow-sm' : 'text-ink-600 hover:text-ink-950 hover:bg-ink-100/60 dark:text-ink-400 dark:hover:text-white dark:hover:bg-white/5' }}">
                    <svg class="w-4 h-4 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <span class="hidden sm:inline">Katalog</span>
                </a>
                <a href="{{ route('servis.booking') }}"
                   class="min-h-[44px] px-2.5 sm:px-3.5 py-2 rounded-full text-xs sm:text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.97] inline-flex items-center gap-1.5 {{ request()->routeIs('servis.booking') ? 'bg-ink-900 text-white dark:bg-white dark:text-ink-950 shadow-sm' : 'text-ink-600 hover:text-ink-950 hover:bg-ink-100/60 dark:text-ink-400 dark:hover:text-white dark:hover:bg-white/5' }}">
                    <svg class="w-4 h-4 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    <span class="hidden sm:inline">Booking</span>
                </a>

                <!-- Cart -->
                <a href="{{ route('cart') }}"
                   class="relative min-h-[44px] px-2.5 sm:px-3.5 py-2 rounded-full text-xs sm:text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.97] flex items-center gap-2 {{ request()->is('cart') || request()->is('checkout') ? 'bg-ink-900 text-white dark:bg-white dark:text-ink-950 shadow-sm' : 'text-ink-600 hover:text-ink-950 hover:bg-ink-100/60 dark:text-ink-400 dark:hover:text-white dark:hover:bg-white/5' }}"
                   aria-label="Keranjang belanja">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <span class="hidden sm:inline">Keranjang</span>
                    @if(session('shop.cart') && array_sum(array_column(session('shop.cart'), 'qty')) > 0)
                        <span class="px-1.5 py-0.2 min-w-[20px] h-5 rounded-full bg-up-primary text-white text-[10px] font-bold tabular-nums inline-flex items-center justify-center shadow-[0_2px_8px_rgba(91,79,233,0.5)] animate-scale-in">
                            {{ array_sum(array_column(session('shop.cart'), 'qty')) }}
                        </span>
                    @endif
                </a>

                <div class="h-5 w-px bg-ink-200/60 dark:bg-white/10 mx-0.5 hidden sm:block"></div>

                <!-- Theme Toggle -->
                <button type="button" x-data="themeManager()" @click="cycleTheme()"
                        title="Ganti mode tampilan" aria-label="Ganti mode tampilan"
                        class="min-h-[44px] min-w-[44px] p-2.5 rounded-full text-ink-500 hover:text-ink-950 hover:bg-ink-100/60 dark:text-ink-400 dark:hover:text-white dark:hover:bg-white/5 transition-all duration-200 active:scale-[0.95] cursor-pointer flex items-center justify-center flex-shrink-0">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-show="theme === 'dark'" x-cloak>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-show="theme === 'light'" x-cloak>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-show="theme === 'auto'" x-cloak>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </button>

                <!-- Customer / Login -->
                @auth('customer')
                    <a href="{{ route('customer.account') }}"
                       class="min-h-[44px] px-2.5 sm:px-3 py-1.5 rounded-full text-xs sm:text-sm font-semibold text-ink-700 dark:text-ink-300 hover:bg-ink-100/60 dark:hover:bg-white/5 transition-all duration-200 active:scale-[0.97] flex items-center gap-1.5 sm:gap-2 flex-shrink-0"
                       aria-label="Akun saya">
                        <span class="w-7 h-7 rounded-full bg-gradient-to-tr from-up-primary to-indigo-500 text-white flex items-center justify-center text-xs font-bold shadow-sm flex-shrink-0">
                            {{ substr(auth('customer')->user()->nama, 0, 1) }}
                        </span>
                        <span class="hidden sm:inline max-w-[100px] truncate">{{ auth('customer')->user()->nama }}</span>
                    </a>
                    <form action="{{ route('customer.logout') }}" method="POST" class="flex-shrink-0">
                        @csrf
                        <button type="submit"
                                class="min-h-[44px] px-2.5 sm:px-3 py-2 rounded-full text-xs font-semibold text-ink-400 hover:text-up-red hover:bg-up-red/10 transition-all duration-200 active:scale-[0.97] cursor-pointer inline-flex items-center gap-1"
                                aria-label="Keluar">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            <span class="hidden sm:inline">Keluar</span>
                        </button>
                    </form>
                @else
                    <a href="{{ route('customer.login') }}"
                       class="min-h-[44px] px-3 sm:px-4 py-2 rounded-full text-xs sm:text-sm font-semibold bg-up-primary/10 text-up-primary hover:bg-up-primary hover:text-white dark:bg-white/10 dark:text-white dark:hover:bg-white dark:hover:text-ink-950 transition-all duration-200 active:scale-[0.97] inline-flex items-center gap-1 sm:gap-1.5 shadow-sm flex-shrink-0"
                       aria-label="Masuk">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                        </svg>
                        <span class="text-xs sm:text-sm">Masuk</span>
                    </a>
                @endauth
            </nav>
        </div>
    </header>

    <!-- Content -->
    <main class="flex-1 pb-28 md:pb-0">
        {{ $slot }}
    </main>

    <!-- [T-49] Floating Pill Mobile Dock (md:hidden) -->
    <div class="md:hidden fixed bottom-3 inset-x-0 w-full flex justify-center px-4 z-50 pointer-events-none"
         style="padding-bottom: max(env(safe-area-inset-bottom, 0px), 0.25rem);">
        <nav class="pointer-events-auto bg-white/90 dark:bg-ink-950/90 backdrop-blur-2xl border border-ink-200/90 dark:border-white/15 shadow-[0_12px_36px_rgba(0,0,0,0.18)] dark:shadow-[0_16px_40px_rgba(0,0,0,0.7)] rounded-full px-2 py-1 flex items-center justify-between gap-0.5 sm:gap-1 max-w-xs sm:max-w-sm w-full mx-auto">
            <a href="{{ route('shop') }}"
               class="group relative flex-1 flex flex-col items-center justify-center min-h-[44px] px-1 py-1 rounded-full text-[11px] font-semibold transition-all duration-200 active:scale-[0.93] {{ request()->is('shop') || request()->is('shop/*') ? 'text-up-primary dark:text-white bg-up-primary/10 dark:bg-white/10' : 'text-ink-500 dark:text-ink-400 hover:text-ink-900 dark:hover:text-white' }}">
                <div class="relative flex items-center justify-center">
                    <svg class="w-5 h-5 transition-transform duration-200 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    @if(request()->is('shop') || request()->is('shop/*'))
                        <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full bg-up-primary dark:bg-white shadow-[0_0_8px_rgba(91,79,233,1)]"></span>
                    @endif
                </div>
                <span class="mt-0.5 tracking-tight text-[10px] sm:text-[11px] truncate">Katalog</span>
            </a>

            <a href="{{ route('servis.booking') }}"
               class="group relative flex-1 flex flex-col items-center justify-center min-h-[44px] px-1 py-1 rounded-full text-[11px] font-semibold transition-all duration-200 active:scale-[0.93] {{ request()->routeIs('servis.booking') ? 'text-up-primary dark:text-white bg-up-primary/10 dark:bg-white/10' : 'text-ink-500 dark:text-ink-400 hover:text-ink-900 dark:hover:text-white' }}">
                <div class="relative flex items-center justify-center">
                    <svg class="w-5 h-5 transition-transform duration-200 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    @if(request()->routeIs('servis.booking'))
                        <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full bg-up-primary dark:bg-white shadow-[0_0_8px_rgba(91,79,233,1)]"></span>
                    @endif
                </div>
                <span class="mt-0.5 tracking-tight text-[10px] sm:text-[11px] truncate">Booking</span>
            </a>

            <a href="{{ route('cart') }}"
               class="group relative flex-1 flex flex-col items-center justify-center min-h-[44px] px-1 py-1 rounded-full text-[11px] font-semibold transition-all duration-200 active:scale-[0.93] {{ request()->is('cart') || request()->is('checkout') ? 'text-up-primary dark:text-white bg-up-primary/10 dark:bg-white/10' : 'text-ink-500 dark:text-ink-400 hover:text-ink-900 dark:hover:text-white' }}">
                <div class="relative flex items-center justify-center">
                    <svg class="w-5 h-5 transition-transform duration-200 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    @if(session('shop.cart') && array_sum(array_column(session('shop.cart'), 'qty')) > 0)
                        <span class="absolute -top-1 -right-2 px-1 min-w-[16px] h-4 rounded-full bg-up-accent text-white text-[9px] font-bold flex items-center justify-center shadow">
                            {{ array_sum(array_column(session('shop.cart'), 'qty')) }}
                        </span>
                    @endif
                    @if(request()->is('cart') || request()->is('checkout'))
                        <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full bg-up-primary dark:bg-white shadow-[0_0_8px_rgba(91,79,233,1)]"></span>
                    @endif
                </div>
                <span class="mt-0.5 tracking-tight text-[10px] sm:text-[11px] truncate">Keranjang</span>
            </a>

            @auth('customer')
                <a href="{{ route('customer.account') }}"
                   class="group relative flex-1 flex flex-col items-center justify-center min-h-[44px] px-1 py-1 rounded-full text-[11px] font-semibold transition-all duration-200 active:scale-[0.93] {{ request()->routeIs('customer.account') ? 'text-up-primary dark:text-white bg-up-primary/10 dark:bg-white/10' : 'text-ink-500 dark:text-ink-400 hover:text-ink-900 dark:hover:text-white' }}">
                    <div class="relative flex items-center justify-center">
                        <svg class="w-5 h-5 transition-transform duration-200 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        @if(request()->routeIs('customer.account'))
                            <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full bg-up-primary dark:bg-white shadow-[0_0_8px_rgba(91,79,233,1)]"></span>
                        @endif
                    </div>
                    <span class="mt-0.5 tracking-tight text-[10px] sm:text-[11px] truncate">Akun</span>
                </a>
            @else
                <a href="{{ route('customer.login') }}"
                   class="group relative flex-1 flex flex-col items-center justify-center min-h-[44px] px-1 py-1 rounded-full text-[11px] font-semibold transition-all duration-200 active:scale-[0.93] {{ request()->routeIs('customer.login') ? 'text-up-primary dark:text-white bg-up-primary/10 dark:bg-white/10' : 'text-ink-500 dark:text-ink-400 hover:text-ink-900 dark:hover:text-white' }}">
                    <div class="relative flex items-center justify-center">
                        <svg class="w-5 h-5 transition-transform duration-200 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                        </svg>
                        @if(request()->routeIs('customer.login'))
                            <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full bg-up-primary dark:bg-white shadow-[0_0_8px_rgba(91,79,233,1)]"></span>
                        @endif
                    </div>
                    <span class="mt-0.5 tracking-tight text-[10px] sm:text-[11px] truncate">Masuk</span>
                </a>
            @endauth
        </nav>
    </div>

            @auth('customer')
                <a href="{{ route('customer.account') }}"
                   class="group relative flex-1 flex flex-col items-center justify-center min-h-[44px] px-1.5 py-1 rounded-full text-[11px] font-semibold transition-all duration-200 active:scale-[0.93] {{ request()->routeIs('customer.account') ? 'text-up-primary dark:text-white bg-up-primary/10 dark:bg-white/10' : 'text-ink-500 dark:text-ink-400 hover:text-ink-900 dark:hover:text-white' }}">
                    <div class="relative flex items-center justify-center">
                        <svg class="w-5 h-5 transition-transform duration-200 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        @if(request()->routeIs('customer.account'))
                            <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full bg-up-primary dark:bg-white shadow-[0_0_8px_rgba(91,79,233,1)]"></span>
                        @endif
                    </div>
                    <span class="mt-0.5 tracking-tight text-[10px] sm:text-[11px]">Akun</span>
                </a>
            @else
                <a href="{{ route('customer.login') }}"
                   class="group relative flex-1 flex flex-col items-center justify-center min-h-[44px] px-1.5 py-1 rounded-full text-[11px] font-semibold transition-all duration-200 active:scale-[0.93] {{ request()->routeIs('customer.login') ? 'text-up-primary dark:text-white bg-up-primary/10 dark:bg-white/10' : 'text-ink-500 dark:text-ink-400 hover:text-ink-900 dark:hover:text-white' }}">
                    <div class="relative flex items-center justify-center">
                        <svg class="w-5 h-5 transition-transform duration-200 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                        </svg>
                        @if(request()->routeIs('customer.login'))
                            <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full bg-up-primary dark:bg-white shadow-[0_0_8px_rgba(91,79,233,1)]"></span>
                        @endif
                    </div>
                    <span class="mt-0.5 tracking-tight text-[10px] sm:text-[11px]">Masuk</span>
                </a>
            @endauth
        </nav>
    </div>

    <!-- Footer: High-tech editorial trust badges + store info -->
    <footer class="mt-auto border-t border-ink-200/60 dark:border-white/[0.08] bg-white/70 dark:bg-ink-950 backdrop-blur-xl">
        <!-- Trust badges -->
        <div class="border-b border-ink-100 dark:border-white/[0.05]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="flex items-start gap-3.5 min-w-0">
                        <div class="w-10 h-10 rounded-2xl bg-up-primary/10 dark:bg-up-primary/20 text-up-primary flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-xs sm:text-sm font-bold text-ink-900 dark:text-white tracking-tight">Garansi Toko Nyata</h4>
                            <p class="text-[11px] text-ink-500 dark:text-ink-400 mt-0.5 leading-relaxed break-words">Garansi ganti baru untuk LCD & baterai dengan QC ketat.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3.5 min-w-0">
                        <div class="w-10 h-10 rounded-2xl bg-up-mint/10 dark:bg-up-mint/20 text-up-mint flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-xs sm:text-sm font-bold text-ink-900 dark:text-white tracking-tight">Jaringan Multi-Cabang</h4>
                            <p class="text-[11px] text-ink-500 dark:text-ink-400 mt-0.5 leading-relaxed break-words">Ambil langsung di cabang terdekat tanpa biaya ongkir.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3.5 min-w-0">
                        <div class="w-10 h-10 rounded-2xl bg-up-accent/10 dark:bg-up-accent/20 text-up-accent flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-xs sm:text-sm font-bold text-ink-900 dark:text-white tracking-tight">Pengiriman Cepat</h4>
                            <p class="text-[11px] text-ink-500 dark:text-ink-400 mt-0.5 leading-relaxed break-words">Packing anti-statis ekstra tebal dengan proteksi busa.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3.5 min-w-0">
                        <div class="w-10 h-10 rounded-2xl bg-indigo-500/10 dark:bg-indigo-500/20 text-indigo-500 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-xs sm:text-sm font-bold text-ink-900 dark:text-white tracking-tight">Teknisi Bersertifikat</h4>
                            <p class="text-[11px] text-ink-500 dark:text-ink-400 mt-0.5 leading-relaxed break-words">Peralatan presisi standar industri untuk servis aman.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
            <div class="space-y-2 max-w-sm">
                <x-prism.logo size="sm" :with-text="true" mode="auto" subtitle="Pusat Sparepart & Servis HP Terpercaya." :href="route('shop')" />
                <p class="text-xs text-ink-500 dark:text-ink-400 leading-relaxed">
                    Menyediakan komponen layar, baterai, kamera, dan IC terlengkap dengan kalibrasi uji kualitas tinggi untuk teknisi & pengguna.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-xs font-medium text-ink-600 dark:text-ink-400">
                <a href="{{ route('shop') }}" class="hover:text-up-primary transition-colors">Katalog Sparepart</a>
                <a href="{{ route('servis.booking') }}" class="hover:text-up-primary transition-colors">Booking Servis</a>
                <a href="{{ url('/tracking') }}" class="hover:text-up-primary transition-colors">Lacak Servis</a>
                <a href="{{ route('customer.account') }}" class="hover:text-up-primary transition-colors">Akun & Membership</a>
            </div>
            <p class="text-xs text-ink-400 dark:text-ink-500">© {{ date('Y') }} Ute Parts Ecosystem. All rights reserved.</p>
        </div>
    </footer>

    @livewireScripts
</body>
</html>