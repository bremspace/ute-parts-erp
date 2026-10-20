<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- [T-32] Tema: flag zona + status staf untuk themeManager -->
    <script>
        window.UTE_ZONE = 'backoffice';
        window.UTE_AUTHED = {!! auth()->check() ? 'true' : 'false' !!};
        window.UTE_THEME = {!! json_encode(auth()->user()?->theme_preference) !!};
    </script>
    <script>
        // [T-32] Bootstrap tema & ukuran font — terapkan sebelum cat pertama untuk mencegah FOUC
        (function () {
            var pref = null;
            try { pref = localStorage.getItem('ute-theme'); } catch (e) {}
            if (!pref) pref = window.UTE_THEME;
            if (!pref) pref = window.UTE_ZONE === 'marketplace' ? 'light' : 'dark';
            var dark = pref === 'dark' || (pref === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            var root = document.documentElement;
            root.dataset.theme = dark ? 'dark' : 'light';
            root.classList.toggle('dark', dark);

            var size = 'md';
            try { size = localStorage.getItem('ute-font-size') || 'md'; } catch (e) {}
            root.dataset.fontSize = size;
        })();
    </script>

    <!-- PWA Meta Tags for Backoffice ERP -->
    <link rel="manifest" href="{{ asset('manifest-backoffice.json') }}">
    <meta name="theme-color" content="#070A14">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Ute ERP">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192x192.png') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/icon-erp.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">

    <title>{{ $title ?? 'Backoffice ERP/POS' }} — Ute Parts</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-[#F1F5F9] dark:bg-[#090D16] text-ink-900 dark:text-ink-100 font-sans antialiased h-screen h-[100dvh] flex flex-col overflow-hidden selection:bg-up-primary selection:text-white"
      x-data="sidebarManager()"
      @keydown.escape.window="sidebarOpen = false"
      data-cabang-count="{{ auth()->user()?->daftarCabangAkses()->count() ?? 0 }}"
      data-cabang-id="{{ session('cabang_id') ?? '' }}"
      style="padding-bottom: env(safe-area-inset-bottom, 0);">
    <div class="flex-1 flex overflow-hidden relative h-full">
        <!-- Mobile Sidebar Overlay (hanya < md — drawer) -->
        <div x-show="sidebarOpen"
             x-transition:enter="transition-opacity ease-linear duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-ink-950/70 backdrop-blur-sm z-40 md:hidden sidebar-backdrop"
             @click="sidebarOpen = false"
             aria-hidden="true"
             :class="{ 'open': sidebarOpen }"></div>

        <!-- [2026] Sidebar Desktop Spacer (zero jitter/reflow on hover) -->
        <div class="hidden md:block sidebar-spacer" :class="{ 'is-collapsed': sidebarCollapsed }"></div>

        <!-- [T-46 & 2026] Sidebar 3 kondisi:
             < 768px  : drawer slide-in + backdrop (hamburger)
             >= 768px : auto-collapse icon-rail + fluid hover expand (zero layout shift) -->
        <aside
            class="w-64 bg-white/95 dark:bg-[#0B1120]/95 backdrop-blur-xl border-r border-ink-200/60 dark:border-white/5 flex flex-col flex-shrink-0
                   fixed inset-y-0 left-0 z-50 transform-gpu shadow-xl md:shadow-none
                   transition-[width,transform] duration-200 ease-out
                   md:sticky md:top-0 md:h-screen md:max-h-screen md:inset-auto md:z-30 md:translate-x-0 md:transform-none"
            :class="[isMobile ? (sidebarOpen ? 'translate-x-0' : '-translate-x-full') : '', sidebarCollapsed ? 'sidebar-collapsed' : '']">
            <!-- Brand -->
            <div class="sidebar-brand h-16 flex items-center justify-between px-5 border-b border-ink-200/60 dark:border-white/5">
                <x-prism.logo size="md" :with-text="true" mode="auto" subtitle="Backoffice ERP" text-class="sidebar-brand-text" href="/app/dashboard" />
                <button type="button" @click="sidebarOpen = false" class="md:hidden p-1.5 rounded-xl text-ink-400 hover:text-ink-900 dark:hover:text-white hover:bg-black/5 dark:hover:bg-white/5 transition-colors cursor-pointer" aria-label="Tutup menu">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Branch Context Badge -->
            <div class="sidebar-branch px-4 py-2.5 border-b border-ink-200/60 dark:border-white/5 bg-ink-100/40 dark:bg-white/[0.02]">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 overflow-hidden">
                        <span class="w-2 h-2 rounded-full bg-up-mint flex-shrink-0 ring-4 ring-up-mint/20"></span>
                        <span class="sidebar-branch-label text-xs font-semibold text-ink-800 dark:text-ink-200 truncate tracking-tight">
                            {{ session('cabang_nama', 'Cabang Pusat (CBG-01)') }}
                        </span>
                    </div>
                    @if(auth()->check() && auth()->user()->daftarCabangAkses()->count() > 1)
                        <button
                            type="button"
                            class="sidebar-branch-ganti text-[11px] font-semibold text-up-primary hover:text-indigo-400 cursor-pointer transition-colors"
                            @click="$dispatch('open-branch-modal')"
                            onclick="window.dispatchEvent(new CustomEvent('open-branch-modal'))"
                        >
                            Ganti
                        </button>
                    @endif
                </div>
            </div>

            <!-- Navigation Links (RBAC Aware) -->
            <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                <!-- 1. Dashboard -->
                <div class="sidebar-group">
                    <button
                        type="button"
                        @click="toggleDropdown('dashboard')"
                        class="sidebar-nav-link w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-[transform,background-color,color] duration-150 cursor-pointer active:scale-[0.98] {{ request()->is('app/dashboard*', 'app/laporan*') ? 'bg-up-primary/15 text-up-primary border border-up-primary/30 dark:bg-up-primary/20 dark:text-white' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}"
                        title="Dashboard"
                    >
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <span class="sidebar-label">Dashboard</span>
                        <svg class="sidebar-chevron ml-auto w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': isDropdownOpen('dashboard') }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div x-show="isDropdownOpen('dashboard')"
                         x-collapse
                         class="sidebar-submenu ml-4 pl-3.5 my-1 space-y-1 border-l border-black/10 dark:border-white/10">
                        <a href="/app/dashboard" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request()->is('app/dashboard') ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->is('app/dashboard') ? 'bg-white' : 'bg-up-primary/60' }}"></span>
                            <span>Ringkasan Eksekutif</span>
                        </a>
                        @can('laporan.cabang')
                            <a href="/app/laporan" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request()->is('app/laporan*') ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ request()->is('app/laporan*') ? 'bg-white' : 'bg-up-accent/60' }}"></span>
                                <span>Laporan & Drill-Down</span>
                            </a>
                        @endcan
                    </div>
                </div>

                <!-- 2. Menu Transaksi (Kasir POS, Servis HP, Riwayat Transaksi) -->
                <div class="sidebar-group">
                    <button
                        type="button"
                        @click="toggleDropdown('transaksi')"
                        class="sidebar-nav-link w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-[transform,background-color,color] duration-150 cursor-pointer active:scale-[0.98] {{ request()->is('app/pos*', 'app/servis*') ? 'bg-up-primary/15 text-up-primary border border-up-primary/30 dark:bg-up-primary/20 dark:text-white' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}"
                        title="Transaksi & Operasional Kasir"
                    >
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        <span class="sidebar-label">Transaksi</span>
                        <svg class="sidebar-chevron ml-auto w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': isDropdownOpen('transaksi') }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div x-show="isDropdownOpen('transaksi')"
                         x-collapse
                         class="sidebar-submenu ml-4 pl-3.5 my-1 space-y-1 border-l border-black/10 dark:border-white/10">
                        @canany(['pos.view', 'pos.view-own'])
                            <a href="/app/pos" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request()->is('app/pos') ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ request()->is('app/pos') ? 'bg-white' : 'bg-up-mint/70' }}"></span>
                                <span>Kasir POS (F2)</span>
                            </a>

                            <a href="/app/pos/online-orders" class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request()->is('app/pos/online-orders*') ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                                <div class="flex items-center gap-2.5">
                                    <span class="w-1.5 h-1.5 rounded-full {{ request()->is('app/pos/online-orders*') ? 'bg-white' : 'bg-sky-400' }}"></span>
                                    <span>Pesanan Online</span>
                                </div>
                                @php
                                    $pendingOnlineCount = \App\Modules\Pos\Models\Transaksi::where('sumber', 'marketplace')->whereIn('status', ['lunas', 'menunggu_pembayaran'])->when(session('cabang_id'), fn($q, $cid) => $q->where('cabang_id', $cid))->count();
                                @endphp
                                @if($pendingOnlineCount > 0)
                                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold bg-up-primary/20 text-up-primary border border-up-primary/30">
                                        {{ $pendingOnlineCount }}
                                    </span>
                                @endif
                            </a>

                            <a href="/app/pos/retur" class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request()->is('app/pos/retur*') ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                                <div class="flex items-center gap-2.5">
                                    <span class="w-1.5 h-1.5 rounded-full {{ request()->is('app/pos/retur*') ? 'bg-white' : 'bg-up-red/70' }}"></span>
                                    <span>Retur Konsumen</span>
                                </div>
                                @php
                                    $pendingReturCount = \App\Modules\Pos\Models\ReturnPenjualan::where('status', 'draft')->when(session('cabang_id'), fn($q, $cid) => $q->where('cabang_id', $cid))->count();
                                @endphp
                                @if($pendingReturCount > 0)
                                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold bg-up-red/20 text-up-red border border-up-red/30">
                                        {{ $pendingReturCount }}
                                    </span>
                                @endif
                            </a>
                        @endcanany

                        @can('servis.view')
                            <a href="/app/servis" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request()->is('app/servis*') ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ request()->is('app/servis*') ? 'bg-white' : 'bg-up-primary/60' }}"></span>
                                <span>Servis HP (Kanban)</span>
                            </a>
                        @endcan

                        @canany(['pos.view', 'pos.view-own', 'servis.view'])
                            <a href="/app/pos/riwayat" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request()->is('app/pos/riwayat*') ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ request()->is('app/pos/riwayat*') ? 'bg-white' : 'bg-up-accent/60' }}"></span>
                                <span>Riwayat Transaksi</span>
                            </a>
                        @endcanany
                    </div>
                </div>

                <!-- 3. Gudang & WMS -->
                <div class="sidebar-group">
                    <button
                        type="button"
                        @click="toggleDropdown('wms')"
                        class="sidebar-nav-link w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-[transform,background-color,color] duration-150 cursor-pointer active:scale-[0.98] {{ request()->is('app/wms*', 'app/laporan/nomor-seri*') ? 'bg-up-primary/15 text-up-primary border border-up-primary/30 dark:bg-up-primary/20 dark:text-white' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}"
                        title="Gudang & WMS"
                    >
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <span class="sidebar-label">Gudang & Stok</span>
                        <svg class="sidebar-chevron ml-auto w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': isDropdownOpen('wms') }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div x-show="isDropdownOpen('wms')"
                         x-collapse
                         class="sidebar-submenu ml-4 pl-3.5 my-1 space-y-1 border-l border-black/10 dark:border-white/10">
                        <a href="/app/wms" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request()->is('app/wms') ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->is('app/wms') ? 'bg-white' : 'bg-up-primary/60' }}"></span>
                            <span>Inventori & Stok</span>
                        </a>
                        <a href="/app/wms/cycle-count" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request()->is('app/wms/cycle-count*') ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->is('app/wms/cycle-count*') ? 'bg-white' : 'bg-up-mint/60' }}"></span>
                            <span>Cycle Count & Opname</span>
                        </a>
                        <a href="/app/laporan/nomor-seri" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request()->is('app/laporan/nomor-seri*') ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->is('app/laporan/nomor-seri*') ? 'bg-white' : 'bg-up-accent/60' }}"></span>
                            <span>Nomor Seri & Garansi</span>
                        </a>
                    </div>
                </div>

                <!-- 5. CRM & Member -->
                <div class="sidebar-group">
                    <button
                        type="button"
                        @click="toggleDropdown('crm')"
                        class="sidebar-nav-link w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-[transform,background-color,color] duration-150 cursor-pointer active:scale-[0.98] {{ request()->is('app/crm*', 'app/reseller*') ? 'bg-up-primary/15 text-up-primary border border-up-primary/30 dark:bg-up-primary/20 dark:text-white' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}"
                        title="Pelanggan & CRM"
                    >
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span class="sidebar-label">Pelanggan & CRM</span>
                        <svg class="sidebar-chevron ml-auto w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': isDropdownOpen('crm') }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div x-show="isDropdownOpen('crm')"
                         x-collapse
                         class="sidebar-submenu ml-4 pl-3.5 my-1 space-y-1 border-l border-black/10 dark:border-white/10">
                        <a href="/app/crm" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request()->is('app/crm') ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->is('app/crm') ? 'bg-white' : 'bg-up-primary/60' }}"></span>
                            <span>Data Pelanggan & Loyalty</span>
                        </a>
                        @can('crm.view')
                            <a href="/app/crm/leads" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request()->is('app/crm/leads*') ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ request()->is('app/crm/leads*') ? 'bg-white' : 'bg-up-accent/60' }}"></span>
                                <span>Pipeline Leads Kanban</span>
                            </a>
                        @endcan
                        @can('reseller.view')
                            <a href="/app/reseller" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request()->is('app/reseller*') ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ request()->is('app/reseller*') ? 'bg-white' : 'bg-up-mint/60' }}"></span>
                                <span>Mitra & Komisi Reseller</span>
                            </a>
                        @endcan
                    </div>
                </div>

                <!-- 6. Akunting & Laporan -->
                <div class="sidebar-group">
                    <button
                        type="button"
                        @click="toggleDropdown('akunting')"
                        class="sidebar-nav-link w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-[transform,background-color,color] duration-150 cursor-pointer active:scale-[0.98] {{ request()->is('app/akunting*', 'app/laporan-pajak*') ? 'bg-up-primary/15 text-up-primary border border-up-primary/30 dark:bg-up-primary/20 dark:text-white' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}"
                        title="Akunting & Keuangan"
                    >
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <span class="sidebar-label">Akunting & Laporan</span>
                        <svg class="sidebar-chevron ml-auto w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': isDropdownOpen('akunting') }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div x-show="isDropdownOpen('akunting')"
                         x-collapse
                         class="sidebar-submenu ml-4 pl-3.5 my-1 space-y-1 border-l border-black/10 dark:border-white/10">
                        <a href="/app/akunting" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request()->is('app/akunting') ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->is('app/akunting') ? 'bg-white' : 'bg-up-primary/60' }}"></span>
                            <span>Buku Besar & Neraca</span>
                        </a>
                        @can('akunting.view')
                            <a href="/app/akunting/aset" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request()->is('app/akunting/aset*') ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ request()->is('app/akunting/aset*') ? 'bg-white' : 'bg-up-accent/60' }}"></span>
                                <span>Aset Tetap & Depresiasi</span>
                            </a>
                            <a href="{{ route('laporan.pajak') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request()->is('app/laporan-pajak*') ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ request()->is('app/laporan-pajak*') ? 'bg-white' : 'bg-up-mint/60' }}"></span>
                                <span>Laporan Pajak (PPN/PPh)</span>
                            </a>
                        @endcan
                    </div>
                </div>

                <!-- 7. HR & Payroll (RBAC: kelola-hr) -->
                @can('kelola-hr')
                    <div class="sidebar-group">
                        <button
                            type="button"
                            @click="toggleDropdown('hr')"
                            class="sidebar-nav-link w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-[transform,background-color,color] duration-150 cursor-pointer active:scale-[0.98] {{ request()->is('app/hr*') ? 'bg-up-primary/15 text-up-primary border border-up-primary/30 dark:bg-up-primary/20 dark:text-white' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}"
                            title="HR & Payroll"
                        >
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span class="sidebar-label">HR & Payroll</span>
                            <span class="sidebar-badge ml-auto text-[10px] font-mono text-ink-500 bg-black/10 dark:bg-white/10 dark:text-white/50 px-1.5 py-0.5 rounded">F3</span>
                            <svg class="sidebar-chevron ml-1.5 w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': isDropdownOpen('hr') }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="isDropdownOpen('hr')"
                             x-collapse
                             class="sidebar-submenu ml-4 pl-3.5 my-1 space-y-1 border-l border-black/10 dark:border-white/10">
                            <a href="/app/hr/absensi" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request()->is('app/hr/absensi*') ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ request()->is('app/hr/absensi*') ? 'bg-white' : 'bg-up-mint/60' }}"></span>
                                <span>Absensi &amp; KPI</span>
                            </a>
                            <a href="/app/hr/payroll" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request()->is('app/hr/payroll*') ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ request()->is('app/hr/payroll*') ? 'bg-white' : 'bg-up-primary/60' }}"></span>
                                <span>Payroll Karyawan</span>
                            </a>
                            <a href="/app/hr/saya" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request()->is('app/hr/saya*') ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ request()->is('app/hr/saya*') ? 'bg-white' : 'bg-up-accent/60' }}"></span>
                                <span>Portal Karyawan</span>
                            </a>
                            <a href="/app/hr/komisi-skema" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request()->is('app/hr/komisi-skema*') ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ request()->is('app/hr/komisi-skema*') ? 'bg-white' : 'bg-up-amber/60' }}"></span>
                                <span>Skema Komisi</span>
                            </a>
                        </div>
                    </div>
                @endcan

                <!-- 8. Omnichannel -->
                <div class="sidebar-group">
                    <a href="/app/omnichannel" title="Omnichannel" class="sidebar-nav-link flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->is('app/omnichannel*') ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }} transition-[background-color,color,box-shadow] duration-140">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                        </svg>
                        <span class="sidebar-label">Omnichannel</span>
                        <span class="sidebar-badge ml-auto text-[9px] font-semibold text-up-mint bg-up-mint/10 border border-up-mint/20 px-1.5 py-0.5 rounded">Shopee</span>
                    </a>
                </div>

                <!-- 10. Approval Inbox (RBAC: approve-workflow) -->
                @can('approve-workflow')
                    @php
                        $pendingApprovalCount = \Illuminate\Support\Facades\Cache::remember(
                            'backoffice-approval-badge-'.(session('cabang_id') ?? 'all'),
                            60,
                            fn (): int => \App\Modules\Workflow\Models\ApprovalRequest::query()
                                ->where('status', 'pending')
                                ->where(fn ($q) => $q->whereNull('cabang_id')->orWhere('cabang_id', session('cabang_id')))
                                ->count()
                        );
                    @endphp
                    <div class="sidebar-group">
                        <a href="/app/approvals" title="Approval Inbox" class="sidebar-nav-link flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->is('app/approvals*') ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }} transition-[background-color,color,box-shadow] duration-140">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="sidebar-label">Approval</span>
                            @if($pendingApprovalCount > 0)
                                <span class="sidebar-badge ml-auto text-[10px] font-extrabold text-white bg-up-red animate-pulse ring-2 ring-up-red/40 px-1.5 py-0.5 rounded">{{ $pendingApprovalCount }}</span>
                            @endif
                        </a>
                    </div>
                @endcan

                <!-- 11. Pengaturan & Sistem -->
                <div class="sidebar-group">
                    <button
                        type="button"
                        @click="toggleDropdown('pengaturan')"
                        class="sidebar-nav-link w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-[transform,background-color,color] duration-150 cursor-pointer active:scale-[0.98] {{ request()->is('app/pengaturan*', 'app/keamanan*', 'app/audit-log*') ? 'bg-up-primary/15 text-up-primary border border-up-primary/30 dark:bg-up-primary/20 dark:text-white' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}"
                        title="Pengaturan & Sistem"
                    >
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                        </svg>
                        <span class="sidebar-label">Pengaturan &amp; Sistem</span>
                        <svg class="sidebar-chevron ml-auto w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': isDropdownOpen('pengaturan') }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div x-show="isDropdownOpen('pengaturan')"
                         x-collapse
                         class="sidebar-submenu ml-4 pl-3.5 my-1 space-y-1 border-l border-black/10 dark:border-white/10">
                        <a href="/app/pengaturan" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request()->is('app/pengaturan') && !request()->has('tab') ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->is('app/pengaturan') && !request()->has('tab') ? 'bg-white' : 'bg-up-primary/60' }}"></span>
                            <span>Pusat Pengaturan</span>
                        </a>
                        <a href="/app/pengaturan?tab=users" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request('tab') === 'users' || request('tab') === 'role' || request('tab') === 'cabang' ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request('tab') === 'users' || request('tab') === 'role' || request('tab') === 'cabang' ? 'bg-white' : 'bg-indigo-400' }}"></span>
                            <span>Akses, User &amp; Cabang</span>
                        </a>
                        <a href="/app/pengaturan?tab=master_produk" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request('tab') === 'master_produk' ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request('tab') === 'master_produk' ? 'bg-white' : 'bg-sky-400' }}"></span>
                            <span>Katalog &amp; Master Part</span>
                        </a>
                        <a href="/app/pengaturan?tab=loyalitas" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request('tab') === 'loyalitas' || request('tab') === 'pajak' ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request('tab') === 'loyalitas' || request('tab') === 'pajak' ? 'bg-white' : 'bg-up-amber' }}"></span>
                            <span>Kebijakan Poin &amp; PPN</span>
                        </a>
                        <a href="/app/pengaturan?tab=printer" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request('tab') === 'printer' || request('tab') === 'master' ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request('tab') === 'printer' || request('tab') === 'master' ? 'bg-white' : 'bg-up-mint' }}"></span>
                            <span>Servis &amp; Struk Kasir</span>
                        </a>
                        <a href="/app/pengaturan?tab=integrasi" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request('tab') === 'integrasi' ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request('tab') === 'integrasi' ? 'bg-white' : 'bg-emerald-400' }}"></span>
                            <span>Gateway &amp; Integrasi</span>
                        </a>
                        @if(isSuperAdminOrOwner())
                        <a href="/app/pengaturan?tab=database" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request('tab') === 'database' ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request('tab') === 'database' ? 'bg-white' : 'bg-purple-400' }}"></span>
                            <span>Basis Data &amp; Backup</span>
                        </a>
                        @endif
                        <a href="{{ route('two-factor.setup') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request()->is('app/keamanan/dua-faktor*') ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ request()->is('app/keamanan/dua-faktor*') ? 'bg-white' : 'bg-up-accent/60' }}"></span>
                            <span>Keamanan Akun &amp; 2FA</span>
                        </a>
                        @can('kelola-sesi')
                            <a href="{{ route('keamanan.sesi') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request()->is('app/keamanan/sesi*') ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ request()->is('app/keamanan/sesi*') ? 'bg-white' : 'bg-up-mint/60' }}"></span>
                                <span>Sesi Perangkat</span>
                            </a>
                        @endcan
                        @can('lihat-audit-log')
                            <a href="/app/audit-log" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ request()->is('app/audit-log*') ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-semibold' : 'text-ink-400 hover:text-ink-900 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ request()->is('app/audit-log*') ? 'bg-white' : 'bg-up-amber/60' }}"></span>
                                <span>Audit Trail &amp; Log</span>
                            </a>
                        @endcan
                    </div>
                </div>
            </nav>

            <!-- User Footer -->
            <div class="sidebar-user p-3 border-t border-ink-200/60 dark:border-white/5 bg-ink-100/40 dark:bg-white/[0.02]">
                <div class="flex items-center justify-between gap-2.5 p-1 rounded-2xl transition-colors">
                    <div class="flex items-center gap-3 overflow-hidden min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-up-primary to-indigo-500 text-white flex items-center justify-center font-bold text-sm flex-shrink-0 shadow-sm shadow-up-primary/20">
                            {{ substr(auth()->user()?->name ?? 'A', 0, 1) }}
                        </div>
                        <div class="sidebar-user-meta overflow-hidden">
                            <p class="text-xs font-bold text-ink-900 dark:text-ink-100 truncate tracking-tight">{{ auth()->user()?->name ?? 'Staff Kasir' }}</p>
                            <p class="text-[11px] text-ink-500 dark:text-ink-400 font-medium capitalize truncate">{{ auth()->user()?->getRoleNames()->first() ?? 'Admin Toko' }}</p>
                        </div>
                    </div>

                    <form action="{{ route('logout') }}" method="POST" class="flex-shrink-0">
                        @csrf
                        <button type="submit" title="Keluar / Logout" aria-label="Keluar / Logout" class="p-2 text-ink-400 hover:text-up-red rounded-xl hover:bg-up-red/10 dark:hover:bg-up-red/15 transition-[color,background-color] cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden bg-ink-50 dark:bg-ink-950">
            <!-- Top App Bar -->
            <header class="h-14 sm:h-16 border-b border-ink-200/80 dark:border-white/5 bg-white/80 dark:bg-ink-900/60 backdrop-blur-md px-2.5 sm:px-4 lg:px-6 flex items-center justify-between z-20">
                <div class="flex items-center gap-1.5 sm:gap-3 min-w-0 flex-1 sm:flex-initial mr-1 sm:mr-0">
                    <!-- Menu Button: <768 toggle drawer · >=768 toggle collapse icon-rail (persist) -->
                    <button @click="toggleSidebar()"
                            class="p-1.5 sm:p-2 rounded-xl text-ink-500 dark:text-ink-300 hover:bg-black/5 dark:hover:bg-white/5 hover:text-ink-900 dark:hover:text-white transition-colors cursor-pointer flex-shrink-0"
                            aria-label="Toggle menu"
                            aria-expanded="false"
                            :aria-expanded="(isMobile ? sidebarOpen : sidebarCollapsed).toString()">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                             :class="{ 'rotate-90': (isMobile ? sidebarOpen : sidebarCollapsed) }">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <h2 class="text-sm sm:text-base lg:text-xl font-bold text-ink-900 dark:text-white tracking-tight truncate max-w-[130px] xs:max-w-[200px] sm:max-w-none">{{ $header ?? 'Ute Parts ERP' }}</h2>
                </div>

                <!-- Shortcuts, Install Button & Status Indicator -->
                <div class="flex items-center gap-1 sm:gap-2 lg:gap-3 flex-shrink-0">
                    <x-prism.pwa-install-prompt variant="button" />

                    <div class="hidden lg:flex items-center gap-2 text-xs text-ink-400">
                        <span class="px-1.5 py-0.5 rounded bg-black/5 border border-black/10 dark:bg-white/5 dark:border-white/10 font-mono text-[11px]">F2</span>
                        <span>Cari</span>
                        <span class="mx-1 text-ink-600 dark:text-white/20">|</span>
                        <span class="px-1.5 py-0.5 rounded bg-black/5 border border-black/10 dark:bg-white/5 dark:border-white/10 font-mono text-[11px]">F4</span>
                        <span>Bayar</span>
                        <span class="mx-1 text-ink-600 dark:text-white/20">|</span>
                        <span class="px-1.5 py-0.5 rounded bg-black/5 border border-black/10 dark:bg-white/5 dark:border-white/10 font-mono text-[11px]">ESC</span>
                        <span>Batal</span>
                    </div>

                    <div class="h-4 w-[1px] bg-black/10 dark:bg-white/10 hidden lg:block"></div>

                    <!-- Online Status Indicator: Green Dot (Hidden label on mobile) -->
                    <div class="flex items-center gap-1.5 text-xs font-medium text-up-mint px-1 py-1 flex-shrink-0" title="Status Database & Sistem Online">
                        <span class="relative flex h-2 w-2 flex-shrink-0">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-up-mint opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-up-mint"></span>
                        </span>
                        <span class="hidden sm:inline text-[11px] font-semibold">DB Online</span>
                    </div>

                    <!-- [2026] Font Size Switcher (Fluid rem scaling) -->
                    <div class="relative flex-shrink-0" x-data="fontSizeManager()" @click.outside="open = false">
                        <button type="button" @click="open = !open"
                                title="Ukuran Font" aria-label="Ukuran Font"
                                class="p-1.5 sm:p-2 rounded-xl text-ink-500 hover:bg-black/5 hover:text-ink-900 dark:text-ink-300 dark:hover:bg-white/5 dark:hover:text-white transition-colors cursor-pointer flex items-center justify-center gap-0.5 sm:gap-1 min-w-[34px] min-h-[34px] sm:min-w-[38px] sm:min-h-[38px]">
                            <span class="font-bold text-xs tracking-tighter">A<span class="text-[10px] font-normal">A</span></span>
                            <span class="hidden md:inline-block text-[10px] uppercase font-mono px-1 py-0.2 rounded bg-black/5 dark:bg-white/10" x-text="size"></span>
                        </button>

                        <div x-show="open" x-cloak
                             x-transition:enter="transition ease-out duration-120"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute right-0 mt-2 w-48 py-2 rounded-2xl bg-white dark:bg-[#0F172A] border border-ink-200/80 dark:border-white/10 shadow-xl z-50">
                            <div class="px-3 py-1 text-[11px] font-semibold text-ink-400 uppercase tracking-wider">
                                Ukuran Font
                            </div>
                            <template x-for="item in sizes" :key="item.id">
                                <button type="button" @click="setSize(item.id)"
                                        class="w-full px-3 py-2 text-left text-xs flex items-center justify-between transition-colors hover:bg-black/5 dark:hover:bg-white/5"
                                        :class="size === item.id ? 'font-bold text-up-primary' : 'text-ink-700 dark:text-ink-300'">
                                    <div class="flex items-center gap-2">
                                        <span x-text="item.label"></span>
                                        <span class="text-[10px] text-ink-400 font-mono" x-text="'(' + item.px + ')'"></span>
                                    </div>
                                    <span x-show="size === item.id" class="w-1.5 h-1.5 rounded-full bg-up-primary"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- [2026] Pusat Notifikasi Aktif -->
                    @auth
                        @livewire(\App\Modules\Notifikasi\Livewire\NotificationCenter::class)
                    @endauth

                    <!-- [T-32] Screen Wake Lock Toggle (Kunci Layar Selalu Menyala) — HANYA DI MENU KASIR POS -->
                    @if(request()->routeIs('pos*'))
                        <div x-data="screenWakeLock()" x-init="init()" x-show="isSupported" class="flex-shrink-0" x-cloak>
                            <button
                                type="button"
                                @click="toggle()"
                                :class="isActive ? 'bg-up-mint/20 text-up-mint border-up-mint/40 dark:bg-up-mint/25' : 'text-ink-500 hover:text-ink-900 dark:text-ink-300 dark:hover:text-white hover:bg-black/5 dark:hover:bg-white/5 border-transparent'"
                                class="p-1.5 sm:p-2 rounded-xl border transition flex items-center justify-center gap-1 sm:gap-1.5 cursor-pointer min-w-[34px] min-h-[34px] sm:min-w-[38px] sm:min-h-[38px]"
                                :title="isActive ? 'Layar selalu menyala (Aktif) - Klik untuk matikan' : 'Kunci Layar: Cegah layar mati / sleep saat kasir & operasional'"
                                aria-label="Kunci Layar Tetap Menyala"
                            >
                                <svg class="w-5 h-5 sm:w-5.5 sm:h-5.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                                <span class="text-[11px] font-bold hidden xl:inline" x-text="isActive ? 'Layar ON' : 'Kunci Layar'"></span>
                            </button>
                        </div>
                    @endif

                    <!-- [T-32] Toggle tema: sun (gelap) / moon (terang) / monitor (auto) -->
                    <button type="button" x-data="themeManager()" @click="cycleTheme()"
                            title="Ganti tema" aria-label="Ganti tema"
                            class="p-1.5 sm:p-2 rounded-xl text-ink-500 hover:bg-black/5 hover:text-ink-900 dark:text-ink-300 dark:hover:bg-white/5 dark:hover:text-white transition-colors cursor-pointer min-w-[34px] min-h-[34px] sm:min-w-[38px] sm:min-h-[38px] flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 sm:w-5.5 sm:h-5.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-show="theme === 'dark'" x-cloak>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <svg class="w-5 h-5 sm:w-5.5 sm:h-5.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-show="theme === 'light'" x-cloak>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                        </svg>
                        <svg class="w-5 h-5 sm:w-5.5 sm:h-5.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-show="theme === 'auto'" x-cloak>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </button>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-2.5 sm:p-4 lg:p-6 pb-24 md:pb-6 bg-transparent">
                {{-- [F1-3] Banner paksa setup 2FA untuk role wajib (tanpa lock-out, bisa skip per sesi/disimpan) --}}
                @if(auth()->check() && auth()->user()->requiresTwoFactor() && ! auth()->user()->hasEnabledTwoFactor() && ! request()->routeIs('two-factor.*'))
                    <div x-data="{
                            dismissed: (localStorage.getItem('dismiss_2fa_banner_{{ auth()->id() }}') === '1' || sessionStorage.getItem('dismiss_2fa_banner') === '1'),
                            dismiss() {
                                this.dismissed = true;
                                try {
                                    localStorage.setItem('dismiss_2fa_banner_{{ auth()->id() }}', '1');
                                    sessionStorage.setItem('dismiss_2fa_banner', '1');
                                } catch (e) {}
                            }
                         }"
                         x-show="!dismissed"
                         x-cloak
                         class="mb-4 p-3 sm:p-3.5 rounded-xl bg-up-amber/15 border border-up-amber/30 text-up-amber text-xs sm:text-sm font-medium flex items-center justify-between gap-2.5 sm:gap-3">
                        <div class="flex items-center gap-2 min-w-0">
                            <svg class="w-4 h-4 flex-shrink-0 text-up-amber" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <span class="truncate sm:whitespace-normal">Keamanan akun: aktifkan verifikasi 2FA untuk role Anda.</span>
                        </div>
                        <div class="flex items-center gap-1.5 sm:gap-2 flex-shrink-0">
                            <a href="{{ route('two-factor.setup') }}" class="px-2.5 py-1 sm:px-3 sm:py-1.5 rounded-lg bg-up-amber text-ink-950 text-xs font-bold hover:opacity-90 transition-opacity whitespace-nowrap">
                                Aktifkan 2FA
                            </a>
                            <button type="button"
                                    @click="dismiss()"
                                    class="p-1 rounded-lg text-up-amber/70 hover:text-up-amber hover:bg-up-amber/20 transition-colors cursor-pointer"
                                    aria-label="Tutup notifikasi"
                                    title="Lewati untuk sekarang">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                @endif

                {{ $slot }}
            </main>

            <!-- Backoffice Floating Pill Mobile Dock (md:hidden) -->
            <div class="md:hidden fixed bottom-3 inset-x-0 w-full flex justify-center px-4 z-40 pointer-events-none"
                 style="padding-bottom: max(env(safe-area-inset-bottom, 0px), 0.25rem);">
                <nav class="pointer-events-auto bg-white/90 dark:bg-ink-950/90 backdrop-blur-2xl border border-ink-200/90 dark:border-white/15 shadow-[0_12px_36px_rgba(0,0,0,0.18)] dark:shadow-[0_16px_40px_rgba(0,0,0,0.7)] rounded-full px-2 py-1 flex items-center justify-between gap-0.5 sm:gap-1 max-w-sm w-full mx-auto">
                    <a href="/app/dashboard"
                       class="group relative flex-1 flex flex-col items-center justify-center min-h-[44px] px-1 py-1 rounded-full text-[11px] font-semibold transition-[background-color,color,transform] duration-140 active:scale-[0.93] {{ request()->is('app/dashboard') ? 'text-up-primary dark:text-white bg-up-primary/10 dark:bg-white/10' : 'text-ink-500 dark:text-ink-400 hover:text-ink-900 dark:hover:text-white' }}">
                        <div class="relative flex items-center justify-center">
                            <svg class="w-5 h-5 transition-transform duration-140 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                        </div>
                        <span class="mt-0.5 tracking-tight text-[10px] sm:text-[11px] truncate">Home</span>
                    </a>

                    <a href="/app/pos"
                       class="group relative flex-1 flex flex-col items-center justify-center min-h-[44px] px-1 py-1 rounded-full text-[11px] font-semibold transition-[background-color,color,transform] duration-140 active:scale-[0.93] {{ request()->is('app/pos*') ? 'text-up-primary dark:text-white bg-up-primary/10 dark:bg-white/10' : 'text-ink-500 dark:text-ink-400 hover:text-ink-900 dark:hover:text-white' }}">
                        <div class="relative flex items-center justify-center">
                            <svg class="w-5 h-5 transition-transform duration-140 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <span class="mt-0.5 tracking-tight text-[10px] sm:text-[11px] truncate">Kasir</span>
                    </a>

                    <a href="/app/servis"
                       class="group relative flex-1 flex flex-col items-center justify-center min-h-[44px] px-1 py-1 rounded-full text-[11px] font-semibold transition-[background-color,color,transform] duration-140 active:scale-[0.93] {{ request()->is('app/servis*') ? 'text-up-primary dark:text-white bg-up-primary/10 dark:bg-white/10' : 'text-ink-500 dark:text-ink-400 hover:text-ink-900 dark:hover:text-white' }}">
                        <div class="relative flex items-center justify-center">
                            <svg class="w-5 h-5 transition-transform duration-140 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <span class="mt-0.5 tracking-tight text-[10px] sm:text-[11px] truncate">Servis</span>
                    </a>

                    <a href="/app/wms"
                       class="group relative flex-1 flex flex-col items-center justify-center min-h-[44px] px-1 py-1 rounded-full text-[11px] font-semibold transition-[background-color,color,transform] duration-140 active:scale-[0.93] {{ request()->is('app/wms*') ? 'text-up-primary dark:text-white bg-up-primary/10 dark:bg-white/10' : 'text-ink-500 dark:text-ink-400 hover:text-ink-900 dark:hover:text-white' }}">
                        <div class="relative flex items-center justify-center">
                            <svg class="w-5 h-5 transition-transform duration-140 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                        <span class="mt-0.5 tracking-tight text-[10px] sm:text-[11px] truncate">WMS</span>
                    </a>

                    <button type="button" @click="sidebarOpen = true"
                            class="group relative flex-1 flex flex-col items-center justify-center min-h-[44px] px-1 py-1 rounded-full text-[11px] font-semibold text-ink-500 dark:text-ink-400 hover:text-ink-900 dark:hover:text-white transition-[background-color,color,transform] duration-140 active:scale-[0.93] cursor-pointer">
                        <div class="relative flex items-center justify-center">
                            <svg class="w-5 h-5 transition-transform duration-140 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </div>
                        <span class="mt-0.5 tracking-tight text-[10px] sm:text-[11px] truncate">Menu</span>
                    </button>
                </nav>
            </div>
        </div>
    </div>

    @livewireScripts

    <!-- [T-29] Modal Ganti Cabang — panggil API AUTH-02 lalu reload penuh agar semua modul re-query cabang baru -->
    <div id="branch-switcher-modal" x-cloak x-show="branchModal" x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4"
         @keydown.escape.window="branchModal = false"
         x-data="branchSwitcher()">
        <div class="w-full max-w-sm glass-panel p-6 rounded-3xl relative" @click.outside="branchModal = false">
            <div class="flex items-center justify-between pb-4 mb-4 border-b border-black/10 dark:border-white/10">
                <h3 class="text-lg font-bold text-ink-900 dark:text-white">Pilih Cabang Aktif</h3>
                <button @click="branchModal = false" class="text-ink-400 hover:text-ink-900 dark:hover:text-white">✕</button>
            </div>

            <p class="text-xs text-ink-500 dark:text-ink-400 mb-3">Cabang aktif akan dipakai Dashboard, POS, WMS, dan modul lain.</p>

            <div class="space-y-2">
                @foreach(auth()->user()->daftarCabangAkses() as $cb)
                    <button type="button"
                            @click="switchBranch({{ $cb->id }})"
                            class="w-full text-left px-4 py-3 rounded-xl border transition-[background-color,border-color,transform] duration-140 cursor-pointer active:scale-[0.98]
                                    {{ session('cabang_id') == $cb->id
                                        ? 'bg-up-primary/15 border-up-primary/50 text-up-primary dark:text-white font-bold'
                                        : 'bg-black/[0.03] border-black/10 text-ink-800 hover:bg-black/[0.07] dark:bg-white/[0.03] dark:border-white/10 dark:text-ink-200 dark:hover:bg-white/[0.07]' }}"
                            :disabled="switchingBranch === {{ $cb->id }}">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-bold">{{ $cb->nama }}</span>
                            <span class="text-[10px] font-mono text-ink-400">{{ $cb->kode }}</span>
                        </div>
                        @if($cb->alamat)
                            <span class="text-[11px] text-ink-400 block mt-0.5 truncate">{{ $cb->alamat }}</span>
                        @endif
                        @if(session('cabang_id') == $cb->id)
                            <span class="text-[10px] text-up-mint font-bold mt-1 block">● Aktif</span>
                        @endif
                        <span x-show="switchingBranch === {{ $cb->id }}" class="text-[10px] text-up-amber font-bold mt-1 block">⟳ Beralih...</span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            // [T-46] Sidebar: drawer (<768) + collapsible md (768-1023, localStorage) + selalu tampil lg + accordion dropdown
            Alpine.data('sidebarManager', () => ({
                sidebarOpen: false,
                sidebarCollapsed: false,
                isMobile: window.innerWidth < 768,
                activeDropdown: '{{ request()->is('app/dashboard*', 'app/laporan*') ? 'dashboard' : (request()->is('app/pos*', 'app/servis*') ? 'transaksi' : (request()->is('app/wms*') ? 'wms' : (request()->is('app/crm*', 'app/reseller*') ? 'crm' : (request()->is('app/akunting*', 'app/laporan-pajak*') ? 'akunting' : (request()->is('app/hr*') ? 'hr' : (request()->is('app/pengaturan*', 'app/keamanan*', 'app/audit-log*') ? 'pengaturan' : (request()->is('app/omnichannel*') ? 'omnichannel' : ''))))))) }}',

                init() {
                    this.isMobile = window.innerWidth < 768;
                    try {
                        const stored = localStorage.getItem('ute-sidebar-md-collapsed');
                        // Di mode PC/desktop (>= 768px): default auto-collapse agar icon-rail aktif dengan hover expand
                        this.sidebarCollapsed = stored !== null ? stored === '1' : true;
                    } catch (e) {
                        this.sidebarCollapsed = true;
                    }
                    
                    // Handle resize: close drawer on mobile when resizing to tablet/desktop
                    window.addEventListener('resize', () => {
                        this.isMobile = window.innerWidth < 768;
                        if (!this.isMobile) {
                            this.sidebarOpen = false;
                        }
                    });
                },

                toggleSidebar() {
                    if (this.isMobile) {
                        this.sidebarOpen = !this.sidebarOpen;
                    } else {
                        this.sidebarCollapsed = !this.sidebarCollapsed;
                        try {
                            localStorage.setItem('ute-sidebar-md-collapsed', this.sidebarCollapsed ? '1' : '0');
                        } catch (e) { /* mode privat — abaikan */ }
                    }
                },

                toggleDropdown(name) {
                    if (this.sidebarCollapsed && window.innerWidth >= 768) {
                        this.sidebarCollapsed = false;
                        try {
                            localStorage.setItem('ute-sidebar-md-collapsed', '0');
                        } catch (e) {}
                        this.activeDropdown = name;
                        return;
                    }
                    this.activeDropdown = this.activeDropdown === name ? null : name;
                },

                isDropdownOpen(name) {
                    return this.activeDropdown === name;
                }
            }));

            Alpine.data('branchSwitcher', () => ({
                branchModal: false,
                switchingBranch: null,

                async switchBranch(cabangId) {
                    if (this.switchingBranch) return;
                    this.switchingBranch = cabangId;

                    try {
                        // Call API AUTH-02: POST /api/select-branch
                        const response = await fetch('/api/select-branch', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ cabang_id: cabangId }),
                            credentials: 'same-origin',
                        });

                        const data = await response.json();

                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'Gagal beralih cabang');
                        }

                        // Session updated on server, now reload to re-query all modules
                        window.location.reload();
                    } catch (error) {
                        console.error('Branch switch error:', error);
                        alert('Gagal beralih cabang: ' + error.message);
                        this.switchingBranch = null;
                    }
                },
            }));

            // Global event to open modal from sidebar button
            window.addEventListener('open-branch-modal', () => {
                const modalEl = document.getElementById('branch-switcher-modal');
                if (modalEl) {
                    const data = Alpine.$data(modalEl);
                    if (data) data.branchModal = true;
                }
            });

            // Auto-open branch modal if user has multiple cabangs but no session cabang_id
            const cabangCount = parseInt(document.body.getAttribute('data-cabang-count') || '0');
            const cabangId = document.body.getAttribute('data-cabang-id') || '';
            if (cabangCount > 1 && !cabangId) {
                window.dispatchEvent(new CustomEvent('open-branch-modal'));
            }
        });
    </script>
