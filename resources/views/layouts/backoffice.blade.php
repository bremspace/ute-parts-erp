<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Backoffice ERP/POS' }} — Ute Parts</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-ink-950 text-ink-100 font-sans antialiased min-h-screen flex flex-col selection:bg-up-primary selection:text-white"
      x-data="{ branchModal: false }"
      @open-branch-modal.window="branchModal = true">
    <div class="flex-1 flex overflow-hidden">
        <!-- Sidebar -->
        <aside class="w-64 bg-ink-900 border-r border-white/5 flex flex-col flex-shrink-0 z-30">
            <!-- Brand -->
            <div class="h-16 flex items-center px-6 border-b border-white/5 gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-up-primary via-indigo-500 to-up-accent flex items-center justify-center shadow-lg shadow-up-primary/30">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div>
                    <h1 class="font-bold text-white tracking-wider text-base leading-none">UTE PARTS</h1>
                    <span class="text-[10px] text-up-accent tracking-widest uppercase font-semibold">Backoffice ERP</span>
                </div>
            </div>

            <!-- Branch Context Badge -->
            <div class="px-4 py-3 border-b border-white/5 bg-white/[0.02]">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 overflow-hidden">
                        <span class="w-2 h-2 rounded-full bg-up-mint"></span>
                        <span class="text-xs font-semibold text-white truncate">
                            {{ session('cabang_nama', 'Cabang Pusat (CBG-01)') }}
                        </span>
                    </div>
                    <button
                        type="button"
                        class="text-[11px] text-up-primary hover:text-indigo-400 font-medium cursor-pointer"
                        onclick="window.dispatchEvent(new CustomEvent('open-branch-modal'))"
                    >
                        Ganti
                    </button>
                </div>
            </div>

            <!-- Navigation Links (RBAC Aware) -->
            <nav class="flex-1 px-3 py-4 space-y-1.5 overflow-y-auto">
                <!-- Dashboard -->
                <a href="/app/dashboard" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->is('app/dashboard') ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-white hover:bg-white/5' }} transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span>Dashboard</span>
                </a>

                <!-- POS (Kasir) -->
                <a href="/app/pos" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->is('app/pos*') ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-white hover:bg-white/5' }} transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    <span>Kasir POS</span>
                    <span class="ml-auto text-[10px] font-mono text-white/50 bg-white/10 px-1.5 py-0.5 rounded">F2</span>
                </a>

                <!-- WMS (Gudang & Stok) -->
                <a href="/app/wms" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->is('app/wms*') ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-white hover:bg-white/5' }} transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                    <span>Gudang & Stok</span>
                </a>

                <!-- Servis HP -->
                <a href="/app/servis" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->is('app/servis*') ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-white hover:bg-white/5' }} transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>Servis HP</span>
                </a>

                <!-- CRM & Member -->
                <a href="/app/crm" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->is('app/crm*') ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-white hover:bg-white/5' }} transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span>Pelanggan & CRM</span>
                </a>

                <!-- Reseller & Komisi -->
                <a href="/app/reseller" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->is('app/reseller*') ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-white hover:bg-white/5' }} transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Reseller & Komisi</span>
                </a>

                <!-- Akunting -->
                <a href="/app/akunting" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->is('app/akunting*') ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-white hover:bg-white/5' }} transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <span>Akunting & Laporan</span>
                </a>

                <!-- Omnichannel -->
                <a href="/app/omnichannel" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->is('app/omnichannel*') ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-white hover:bg-white/5' }} transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                    </svg>
                    <span>Omnichannel</span>
                    <span class="ml-auto text-[9px] font-semibold text-up-mint bg-up-mint/10 border border-up-mint/20 px-1.5 py-0.5 rounded">Shopee</span>
                </a>

                <!-- Pengaturan -->
                <a href="/app/pengaturan" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium {{ request()->is('app/pengaturan*') ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-400 hover:text-white hover:bg-white/5' }} transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                    </svg>
                    <span>Pengaturan & RBAC</span>
                </a>
            </nav>

            <!-- User Footer -->
            <div class="p-4 border-t border-white/5 bg-ink-850">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3 overflow-hidden">
                        <div class="w-9 h-9 rounded-full bg-up-primary/20 border border-up-primary/40 flex items-center justify-center font-bold text-up-primary text-sm">
                            {{ substr(auth()->user()?->name ?? 'A', 0, 1) }}
                        </div>
                        <div class="overflow-hidden">
                            <p class="text-sm font-semibold text-white truncate">{{ auth()->user()?->name ?? 'Staff Kasir' }}</p>
                            <p class="text-[11px] text-ink-400 capitalize truncate">{{ auth()->user()?->getRoleNames()->first() ?? 'Admin Toko' }}</p>
                        </div>
                    </div>

                    <form action="/api/logout" method="POST">
                        @csrf
                        <button type="submit" title="Logout" class="p-2 text-ink-400 hover:text-up-red rounded-lg hover:bg-white/5 transition-colors cursor-pointer">
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
            <header class="h-16 border-b border-white/5 bg-ink-900/60 backdrop-blur-md px-6 flex items-center justify-between z-20">
                <div>
                    <h2 class="text-lg font-bold text-white tracking-wide">{{ $header ?? 'Ute Parts ERP' }}</h2>
                </div>

                <!-- Shortcuts & Status Indicator -->
                <div class="flex items-center gap-4">
                    <div class="hidden lg:flex items-center gap-2 text-xs text-ink-400">
                        <span class="px-1.5 py-0.5 rounded bg-white/5 border border-white/10 font-mono text-[11px]">F2</span>
                        <span>Cari</span>
                        <span class="mx-1 text-white/20">|</span>
                        <span class="px-1.5 py-0.5 rounded bg-white/5 border border-white/10 font-mono text-[11px]">F4</span>
                        <span>Bayar</span>
                        <span class="mx-1 text-white/20">|</span>
                        <span class="px-1.5 py-0.5 rounded bg-white/5 border border-white/10 font-mono text-[11px]">ESC</span>
                        <span>Batal</span>
                    </div>

                    <div class="h-4 w-[1px] bg-white/10 hidden lg:block"></div>

                    <div class="flex items-center gap-2 text-xs font-medium text-up-mint">
                        <span class="w-2 h-2 rounded-full bg-up-mint animate-pulse"></span>
                        <span>Online</span>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-6 bg-gradient-to-b from-ink-900/20 to-ink-950">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts

    <!-- [T-02] Modal Ganti Cabang — full reload agar semua modul re-query cabang baru -->
    <div x-cloak x-show="branchModal" x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4"
         @keydown.escape.window="branchModal = false">
        <div class="w-full max-w-sm glass-panel p-6 rounded-3xl relative" @click.outside="branchModal = false">
            <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                <h3 class="text-lg font-bold text-white">Pilih Cabang Aktif</h3>
                <button @click="branchModal = false" class="text-ink-400 hover:text-white">✕</button>
            </div>

            <p class="text-xs text-ink-400 mb-3">Cabang aktif akan dipakai Dashboard, POS, WMS, dan modul lain.</p>

            <div class="space-y-2">
                @foreach(auth()->user()->cabangs as $cb)
                    <form method="POST" action="{{ route('pilih-cabang') }}">
                        @csrf
                        <input type="hidden" name="cabang_id" value="{{ $cb->id }}" />
                        <button type="submit"
                                class="w-full text-left px-4 py-3 rounded-xl border transition-all cursor-pointer
                                    {{ session('cabang_id') == $cb->id
                                        ? 'bg-up-primary/15 border-up-primary/50 text-white'
                                        : 'bg-white/[0.03] border-white/10 text-ink-200 hover:bg-white/[0.07]' }}">
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
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
    </div>
</body>
</html>
