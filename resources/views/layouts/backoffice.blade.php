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

    <title>{{ $title ?? 'Backoffice ERP/POS' }} — Ute Parts</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-ink-950 text-ink-100 font-sans antialiased min-h-screen flex flex-col overflow-x-clip selection:bg-up-primary selection:text-white"
      x-data="sidebarManager()"
      @keydown.escape.window="sidebarOpen = false"
      data-cabang-count="{{ auth()->user()?->cabangs->count() ?? 0 }}"
      data-cabang-id="{{ session('cabang_id') ?? '' }}"
      style="padding-bottom: env(safe-area-inset-bottom, 0);">
    <div class="flex-1 flex overflow-hidden relative">
        <!-- Mobile Sidebar Overlay (hanya < md — drawer) -->
        <div x-show="sidebarOpen"
             x-transition:enter="transition-opacity ease-linear duration-150"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-black/60 z-40 md:hidden sidebar-backdrop"
             @click="sidebarOpen = false"
             aria-hidden="true"
             :class="{ 'open': sidebarOpen }"></div>

        <!-- [T-46] Sidebar 3 kondisi:
             < 768px  : drawer slide-in + backdrop (hamburger)
             768-1023 : kolapsibel icon-rail (toggle, state localStorage)
             >= 1024  : selalu tampil penuh (expanded) -->
        <aside
            class="w-64 bg-ink-900 border-r border-black/10 dark:border-white/5 flex flex-col flex-shrink-0
                   fixed inset-y-0 left-0 z-50 transform-gpu
                   transition-transform duration-200 ease-out
                   md:static md:inset-auto md:z-30 md:translate-x-0 md:transform-none
                   lg:z-30"
            :class="[window.innerWidth < 768 ? (sidebarOpen ? 'translate-x-0' : '-translate-x-full') : '', sidebarCollapsed ? 'sidebar-collapsed' : '']">
            <!-- Brand -->
            <div class="sidebar-brand h-16 flex items-center px-6 border-b border-black/10 dark:border-white/5 gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-up-primary via-indigo-500 to-up-accent flex items-center justify-center shadow-lg shadow-up-primary/30 flex-shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div class="sidebar-brand-text min-w-0">
                    <h1 class="font-bold text-ink-50 dark:text-white tracking-wider text-base leading-none truncate">UTE PARTS</h1>
                    <span class="text-[10px] text-up-accent tracking-widest uppercase font-semibold">Backoffice ERP</span>
                </div>
            </div>

            <!-- Branch Context Badge -->
            <div class="sidebar-branch px-4 py-3 border-b border-black/10 dark:border-white/5 bg-black/[0.02] dark:bg-white/[0.02]">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 overflow-hidden">
                        <span class="w-2 h-2 rounded-full bg-up-mint flex-shrink-0"></span>
                        <span class="sidebar-branch-label text-xs font-semibold text-ink-50 dark:text-white truncate">
                            {{ session('cabang_nama', 'Cabang Pusat (CBG-01)') }}
                        </span>
                    </div>
                    <button
                        type="button"
                        class="sidebar-branch-ganti text-[11px] text-up-primary hover:text-indigo-400 font-medium cursor-pointer"
                        onclick="window.dispatchEvent(new CustomEvent('open-branch-modal'))"
                    >
                        Ganti
                    </button>
                </div>
            </div>

            <!-- Navigation Links (RBAC Aware) -->
            <nav class="flex-1 px-3 py-4 space-y-1.5 overflow-y-auto">
                <!-- Dashboard -->
                <a href="/app/dashboard" title="Dashboard" class="sidebar-nav-link flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->is('app/dashboard') ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-ink-50 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }} transition-all">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span class="sidebar-label">Dashboard</span>
                </a>

                <!-- POS (Kasir) -->
                <a href="/app/pos" title="Kasir POS" class="sidebar-nav-link flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->is('app/pos*') ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-ink-50 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }} transition-all">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    <span class="sidebar-label">Kasir POS</span>
                    <span class="sidebar-badge ml-auto text-[10px] font-mono text-ink-500 bg-black/10 dark:bg-white/10 dark:text-white/50 px-1.5 py-0.5 rounded">F2</span>
                </a>

                <!-- WMS (Gudang & Stok) -->
                <a href="/app/wms" title="Gudang & Stok" class="sidebar-nav-link flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->is('app/wms*') ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-ink-50 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }} transition-all">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                    <span class="sidebar-label">Gudang & Stok</span>
                </a>

                <!-- Servis HP -->
                <a href="/app/servis" title="Servis HP" class="sidebar-nav-link flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->is('app/servis*') ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-ink-50 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }} transition-all">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="sidebar-label">Servis HP</span>
                </a>

                <!-- CRM & Member -->
                <a href="/app/crm" title="Pelanggan & CRM" class="sidebar-nav-link flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->is('app/crm*') ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-ink-50 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }} transition-all">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span class="sidebar-label">Pelanggan & CRM</span>
                </a>

                <!-- Reseller & Komisi -->
                <a href="/app/reseller" title="Reseller & Komisi" class="sidebar-nav-link flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->is('app/reseller*') ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-ink-50 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }} transition-all">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="sidebar-label">Reseller & Komisi</span>
                </a>

                <!-- [F3-8] HR & Payroll (RBAC aware) -->
                @can('kelola-hr')
                    <a href="/app/hr/absensi" title="HR & Payroll (absensi, payroll, rule komisi)" class="sidebar-nav-link flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->is('app/hr*') ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-ink-50 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }} transition-all">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span class="sidebar-label">HR & Payroll</span>
                        <span class="sidebar-badge ml-auto text-[10px] font-mono text-ink-500 bg-black/10 dark:bg-white/10 dark:text-white/50 px-1.5 py-0.5 rounded">F3</span>
                    </a>
                @endcan

                <!-- Akunting -->
                <a href="/app/akunting" title="Akunting & Laporan" class="sidebar-nav-link flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->is('app/akunting*') ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-ink-50 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }} transition-all">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <span class="sidebar-label">Akunting & Laporan</span>
                </a>

                <!-- [F3-3] Register Aset Tetap & Depresiasi — sub-menu Akunting, RBAC: akunting.view -->
                @can('akunting.view')
                    <a href="/app/akunting/aset" title="Aset Tetap & Depresiasi" class="sidebar-nav-link flex items-center gap-3 pl-9 pr-3.5 py-2 rounded-xl text-xs font-medium {{ request()->is('app/akunting/aset*') ? 'bg-up-primary/15 text-up-primary border border-up-primary/30' : 'text-ink-500 hover:text-ink-50 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }} transition-all">
                        <span class="w-1.5 h-1.5 rounded-full bg-up-accent flex-shrink-0" aria-hidden="true"></span>
                        <span class="sidebar-label">Aset Tetap &amp; Depresiasi</span>
                    </a>
                @endcan

                <!-- Omnichannel -->
                <a href="/app/omnichannel" title="Omnichannel" class="sidebar-nav-link flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->is('app/omnichannel*') ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-ink-50 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }} transition-all">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                    </svg>
                    <span class="sidebar-label">Omnichannel</span>
                    <span class="sidebar-badge ml-auto text-[9px] font-semibold text-up-mint bg-up-mint/10 border border-up-mint/20 px-1.5 py-0.5 rounded">Shopee</span>
                </a>

                <!-- [F1-1] Approval Inbox + badge pending (cabang-aware) -->
                @can('approve-workflow')
                    @php
                        // [B-15d] Badge ini di-query di SETIAP halaman backoffice
                        // (hit-rate ~100%) tanpa cache. Cache 60 detik per cabang
                        // aktif; gate `approve-workflow` itu per-role (bukan
                        // per-user) jadi key cukup cabang — semua user dgn role
                        // yg sama melihat angka sama.
                        $pendingApprovalCount = \Illuminate\Support\Facades\Cache::remember(
                            'backoffice-approval-badge-'.(session('cabang_id') ?? 'all'),
                            60,
                            fn (): int => \App\Modules\Workflow\Models\ApprovalRequest::query()
                                ->where('status', 'pending')
                                ->where(fn ($q) => $q->whereNull('cabang_id')->orWhere('cabang_id', session('cabang_id')))
                                ->count()
                        );
                    @endphp
                    <a href="/app/approvals" title="Approval Inbox" class="sidebar-nav-link flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->is('app/approvals*') ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-ink-50 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }} transition-all">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="sidebar-label">Approval</span>
                        @if($pendingApprovalCount > 0)
                            <span class="sidebar-badge ml-auto text-[10px] font-semibold text-white bg-up-red px-1.5 py-0.5 rounded">{{ $pendingApprovalCount }}</span>
                        @endif
                    </a>
                @endcan

                <!-- Pengaturan -->
                <a href="/app/pengaturan" title="Pengaturan & RBAC" class="sidebar-nav-link flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->is('app/pengaturan*') ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-ink-50 hover:bg-black/5 dark:hover:text-white dark:hover:bg-white/5' }} transition-all">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                    </svg>
                    <span class="sidebar-label">Pengaturan & RBAC</span>
                </a>
            </nav>

            <!-- User Footer -->
            <div class="sidebar-user p-4 border-t border-black/10 dark:border-white/5 bg-ink-850">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3 overflow-hidden">
                        <div class="w-9 h-9 rounded-full bg-up-primary/20 border border-up-primary/40 flex items-center justify-center font-bold text-up-primary text-sm flex-shrink-0">
                            {{ substr(auth()->user()?->name ?? 'A', 0, 1) }}
                        </div>
                        <div class="sidebar-user-meta overflow-hidden">
                            <p class="text-sm font-semibold text-ink-50 dark:text-white truncate">{{ auth()->user()?->name ?? 'Staff Kasir' }}</p>
                            <p class="text-[11px] text-ink-400 capitalize truncate">{{ auth()->user()?->getRoleNames()->first() ?? 'Admin Toko' }}</p>
                        </div>
                    </div>

                    <form action="{{ route('logout') }}" method="POST" class="flex-shrink-0">
                        @csrf
                        <button type="submit" title="Logout" aria-label="Logout" class="p-2.5 text-ink-400 hover:text-up-red rounded-lg hover:bg-black/5 dark:hover:bg-white/5 transition-colors cursor-pointer">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden bg-ink-950">
            <!-- Top App Bar -->
            <header class="h-16 border-b border-black/10 dark:border-white/5 bg-ink-900/60 backdrop-blur-md px-4 lg:px-6 flex items-center justify-between z-20">
                <div class="flex items-center gap-3">
                    <!-- Menu Button: <768 toggle drawer · 768-1023 toggle collapse (persist) · lg sembunyi -->
                    <button @click="toggleSidebar()"
                            class="lg:hidden p-2.5 rounded-lg text-ink-300 hover:bg-black/5 dark:hover:bg-white/5 hover:text-ink-100 dark:hover:text-white transition-colors cursor-pointer"
                            aria-label="Toggle menu"
                            aria-expanded="false"
                            :aria-expanded="(window.innerWidth < 768 ? sidebarOpen : sidebarCollapsed).toString()">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                             :class="{ 'rotate-90': (window.innerWidth < 768 ? sidebarOpen : sidebarCollapsed) }">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <h2 class="text-lg font-bold text-ink-50 dark:text-white tracking-wide lg:text-xl">{{ $header ?? 'Ute Parts ERP' }}</h2>
                </div>

                <!-- Shortcuts & Status Indicator -->
                <div class="flex items-center gap-4">
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

                    <div class="flex items-center gap-2 text-xs font-medium text-up-mint">
                        <span class="w-2 h-2 rounded-full bg-up-mint animate-pulse"></span>
                        <span>Online</span>
                    </div>

                    <!-- [T-32] Toggle tema: sun (gelap) / moon (terang) / monitor (auto) -->
                    <button type="button" x-data="themeManager()" @click="cycleTheme()"
                            title="Ganti tema" aria-label="Ganti tema"
                            class="p-2.5 rounded-lg text-ink-500 hover:bg-black/5 hover:text-ink-100 dark:text-ink-300 dark:hover:bg-white/5 dark:hover:text-white transition-colors cursor-pointer">
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
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-3 lg:p-6 bg-gradient-to-b from-ink-900/20 to-ink-950">
                {{-- [F1-3] Banner paksa setup 2FA untuk role wajib (tanpa lock-out) --}}
                @if(auth()->check() && auth()->user()->requiresTwoFactor() && ! auth()->user()->hasEnabledTwoFactor() && ! request()->routeIs('two-factor.*'))
                    <div class="mb-4 p-3.5 rounded-xl bg-up-amber/15 border border-up-amber/30 text-up-amber text-sm font-medium flex items-center justify-between gap-3 flex-wrap">
                        <span>Keamanan akun: aktifkan verifikasi dua faktor (2FA) untuk role Anda.</span>
                        <a href="{{ route('two-factor.setup') }}" class="px-3 py-1.5 rounded-lg bg-up-amber text-ink-950 text-xs font-bold hover:opacity-90 transition-opacity whitespace-nowrap">
                            Aktifkan 2FA
                        </a>
                    </div>
                @endif

                {{ $slot }}
            </main>
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
                <h3 class="text-lg font-bold text-ink-50 dark:text-white">Pilih Cabang Aktif</h3>
                <button @click="branchModal = false" class="text-ink-400 hover:text-ink-50 dark:hover:text-white">✕</button>
            </div>

            <p class="text-xs text-ink-400 mb-3">Cabang aktif akan dipakai Dashboard, POS, WMS, dan modul lain.</p>

            <div class="space-y-2">
                @foreach(auth()->user()->cabangs as $cb)
                    <button type="button"
                            @click="switchBranch({{ $cb->id }})"
                            class="w-full text-left px-4 py-3 rounded-xl border transition-all cursor-pointer
                                    {{ session('cabang_id') == $cb->id
                                        ? 'bg-up-primary/15 border-up-primary/50 text-ink-50 dark:text-white'
                                        : 'bg-black/[0.03] border-black/10 text-ink-100 hover:bg-black/[0.07] dark:bg-white/[0.03] dark:border-white/10 dark:text-ink-200 dark:hover:bg-white/[0.07]' }}"
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
            // [T-46] Sidebar: drawer (<768) + collapsible md (768-1023, localStorage) + selalu tampil lg
            Alpine.data('sidebarManager', () => ({
                sidebarOpen: false,
                sidebarCollapsed: false,

                init() {
                    try {
                        this.sidebarCollapsed = localStorage.getItem('ute-sidebar-md-collapsed') === '1';
                    } catch (e) { /* mode privat — default expanded */ }
                    
                    // Handle resize: close drawer on mobile when resizing to tablet/desktop
                    window.addEventListener('resize', () => {
                        if (window.innerWidth >= 768) {
                            this.sidebarOpen = false;
                        }
                    });
                },

                toggleSidebar() {
                    if (window.innerWidth < 768) {
                        this.sidebarOpen = !this.sidebarOpen;
                    } else {
                        this.sidebarCollapsed = !this.sidebarCollapsed;
                        try {
                            localStorage.setItem('ute-sidebar-md-collapsed', this.sidebarCollapsed ? '1' : '0');
                        } catch (e) { /* mode privat — abaikan */ }
                    }
                },
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
