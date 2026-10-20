<div class="space-y-6" wire:poll.60s
     x-data="{
        showCustomizer: false,
        dragKey: null,
        overKey: null,
        defaultOrder: ['kpi_metrics', 'analisis_penjualan', 'charts_omzet_kategori', 'operational_funnel', 'feed_transaksi_harga', 'role_specific'],
        layoutConfig: {
            kpi_metrics: { visible: true, label: '4 Kartu KPI Utama' },
            analisis_penjualan: { visible: true, label: 'Data & Analisis Penjualan' },
            charts_omzet_kategori: { visible: true, label: 'Tren Omzet 30 Hari & Komposisi Produk' },
            operational_funnel: { visible: true, label: 'Status Servis & Umur Piutang' },
            feed_transaksi_harga: { visible: true, label: 'Perubahan Harga & Transaksi Terkini' },
            role_specific: { visible: true, label: 'Widget Sesuai Role (Kasir/Finance/dsb)' }
        },
        widgetOrder: ['kpi_metrics', 'analisis_penjualan', 'charts_omzet_kategori', 'operational_funnel', 'feed_transaksi_harga', 'role_specific'],
        init() {
            try {
                const savedOrder = JSON.parse(localStorage.getItem('ute-dash-order'));
                if (Array.isArray(savedOrder) && savedOrder.length > 0) {
                    this.widgetOrder = savedOrder.filter(k => this.defaultOrder.includes(k));
                    this.defaultOrder.forEach(k => {
                        if (!this.widgetOrder.includes(k)) this.widgetOrder.push(k);
                    });
                }
                const savedConfig = JSON.parse(localStorage.getItem('ute-dash-visibility'));
                if (savedConfig && typeof savedConfig === 'object') {
                    for (const [k, val] of Object.entries(savedConfig)) {
                        if (this.layoutConfig[k]) {
                            this.layoutConfig[k].visible = Boolean(val);
                        }
                    }
                }
            } catch (e) {
                console.warn('Dashboard layout init fallback to default', e);
            }
        },
        saveLayout() {
            try {
                localStorage.setItem('ute-dash-order', JSON.stringify(this.widgetOrder));
                const vis = {};
                for (const [k, obj] of Object.entries(this.layoutConfig)) {
                    vis[k] = obj.visible;
                }
                localStorage.setItem('ute-dash-visibility', JSON.stringify(vis));
            } catch (e) {}
        },
        toggleVisibility(key) {
            if (this.layoutConfig[key]) {
                this.layoutConfig[key].visible = !this.layoutConfig[key].visible;
                this.saveLayout();
            }
        },
        moveUp(index) {
            if (index > 0) {
                const item = this.widgetOrder.splice(index, 1)[0];
                this.widgetOrder.splice(index - 1, 0, item);
                this.saveLayout();
            }
        },
        moveDown(index) {
            if (index < this.widgetOrder.length - 1) {
                const item = this.widgetOrder.splice(index, 1)[0];
                this.widgetOrder.splice(index + 1, 0, item);
                this.saveLayout();
            }
        },
        resetLayout() {
            this.widgetOrder = [...this.defaultOrder];
            for (const k of this.defaultOrder) {
                this.layoutConfig[k].visible = true;
            }
            try {
                localStorage.removeItem('ute-dash-order');
                localStorage.removeItem('ute-dash-visibility');
            } catch (e) {}
        },
        onDragStart(key) {
            this.dragKey = key;
        },
        onDrop(targetKey) {
            if (this.dragKey && this.dragKey !== targetKey) {
                const fromIdx = this.widgetOrder.indexOf(this.dragKey);
                const toIdx = this.widgetOrder.indexOf(targetKey);
                if (fromIdx > -1 && toIdx > -1) {
                    const item = this.widgetOrder.splice(fromIdx, 1)[0];
                    this.widgetOrder.splice(toIdx, 0, item);
                    this.saveLayout();
                }
            }
            this.dragKey = null;
            this.overKey = null;
        }
     }">
    @php
        $palette = ['#4F46E5', '#10B981', '#F59E0B', '#D97706', '#38BDF8', '#EC4899', '#A855F7'];

        $hour = (int) now()->format('H');
        $salam = match(true) {
            $hour < 11 => 'Selamat Pagi',
            $hour < 15 => 'Selamat Siang',
            $hour < 18 => 'Selamat Sore',
            default => 'Selamat Malam',
        };
    @endphp

    <!-- Greeting & Quick Actions Bar -->
    <div class="p-4 sm:p-5 rounded-2xl bg-white/[0.02] border border-white/10 backdrop-blur-md space-y-3 sm:space-y-0 sm:flex sm:items-center sm:justify-between sm:gap-4">
        <div class="space-y-1.5 flex-1 min-w-0">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold tracking-wide uppercase bg-up-primary/20 text-indigo-300 border border-up-primary/30">
                        {{ session('cabang_nama', 'Semua Cabang') }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-[11px] text-ink-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-up-mint animate-pulse"></span>
                        Live Sync 60s
                    </span>
                </div>

                <!-- Tombol Atur Widget: pojok kanan atas (mobile) -->
                <button type="button" @click="showCustomizer = true"
                    class="sm:hidden inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-white/[0.04] hover:bg-white/[0.1] text-ink-300 hover:text-white text-[11px] font-semibold border border-white/10 transition-all active:scale-[0.97] cursor-pointer"
                    title="Atur Tata Letak / Urutan Widget">
                    <svg class="w-3.5 h-3.5 text-up-mint" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                    </svg>
                    <span>Atur Widget</span>
                </button>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                {{ $salam }}, {{ auth()->user()?->name }} <span class="text-lg">👋</span>
            </h1>
            <p class="text-xs text-ink-400">
                {{ now()->isoFormat('dddd, D MMMM Y') }} — Ringkasan performa & kesehatan operasional bengkel/toko.
            </p>
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto pt-1 sm:pt-0">
            @canany(['pos.view', 'pos.view-own', 'pos.create'])
                <a href="/app/pos" class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl bg-up-primary hover:bg-indigo-600 text-white text-xs font-semibold shadow-md transition-all duration-150 active:scale-[0.97] whitespace-nowrap min-h-[44px]">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    Buka Kasir
                </a>
            @endcanany
            @can('servis.create')
                <a href="/app/servis" class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl bg-white/[0.06] hover:bg-white/[0.1] text-ink-200 hover:text-white text-xs font-semibold border border-white/10 transition-all duration-150 active:scale-[0.97] whitespace-nowrap min-h-[44px]">
                    <svg class="w-3.5 h-3.5 text-up-amber" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Tiket Servis
                </a>
            @endcan

            <!-- Tombol Atur Tata Letak / Urutan Widget (Desktop) -->
            <button type="button" @click="showCustomizer = true"
                class="hidden sm:inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white/[0.04] hover:bg-white/[0.1] text-ink-300 hover:text-white text-xs font-semibold border border-white/10 transition-all duration-150 active:scale-[0.97] whitespace-nowrap min-h-[44px] cursor-pointer">
                <svg class="w-3.5 h-3.5 text-up-mint" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                </svg>
                Atur Widget
            </button>
        </div>
    </div>

    <!-- Main Dynamic Widgets Container (Flex Col dengan CSS Order dinamis) -->
    <div class="flex flex-col gap-6">

    <!-- 1. 4 KPI Metrics Utama -->
    <div wire:key="widget-kpi-metrics"
         class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 transition-all duration-200"
         x-show="layoutConfig?.kpi_metrics?.visible !== false"
         :style="'order: ' + (widgetOrder.indexOf('kpi_metrics') + 1)"
         data-widget-key="kpi_metrics">
        <!-- 1. Omzet Hari Ini -->
        <x-prism.glass-card class="p-4 sm:p-5 relative overflow-hidden group hover:border-up-mint/40 transition-all duration-200">
            <div class="flex items-center justify-between">
                <span class="text-[10px] sm:text-[11px] font-semibold uppercase tracking-wider text-ink-400">Omzet Hari Ini</span>
                <div class="w-8 h-8 rounded-xl bg-up-mint/10 border border-up-mint/20 flex items-center justify-center text-up-mint">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div class="mt-2.5">
                <p class="text-2xl font-black text-white tabular-nums tracking-tight">
                    Rp {{ number_format($omzetHariIni['cabang_aktif'] ?? 0, 0, ',', '.') }}
                </p>
                <div class="flex items-center justify-between text-[11px] text-ink-400 mt-2 pt-2 border-t border-white/5">
                    <span>Semua cabang:</span>
                    <span class="font-medium text-ink-300 tabular-nums">Rp {{ number_format($omzetHariIni['semua_cabang'] ?? 0, 0, ',', '.') }}</span>
                </div>
            </div>
        </x-prism.glass-card>

        <!-- 2. Antrian Servis -->
        @canany(['servis.view', 'servis.create'])
        <a href="/app/servis" class="block group relative overflow-hidden rounded-2xl bg-white/[0.03] border border-white/10 p-4 sm:p-5 hover:border-up-amber/40 transition-all duration-200 active:scale-[0.98]">
        @else
        <div class="block relative overflow-hidden rounded-2xl bg-white/[0.03] border border-white/10 p-4 sm:p-5">
        @endcanany
            <div class="flex items-center justify-between">
                <span class="text-[10px] sm:text-[11px] font-semibold uppercase tracking-wider text-ink-400 group-hover:text-ink-200">Antrian Servis</span>
                <div class="w-8 h-8 rounded-xl bg-up-amber/10 border border-up-amber/20 flex items-center justify-center text-up-amber">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                </div>
            </div>
            <div class="mt-2.5">
                <p class="text-2xl font-black text-white tabular-nums tracking-tight flex items-baseline gap-1.5">
                    {{ $antrianServis['total_aktif'] ?? 0 }} <span class="text-xs font-normal text-ink-400">unit</span>
                </p>
                <div class="flex items-center justify-between text-[11px] text-ink-400 mt-2 pt-2 border-t border-white/5">
                    <span>Selesai hari ini:</span>
                    <span class="font-bold text-up-mint tabular-nums">{{ $antrianServis['selesai_hari_ini'] ?? 0 }} unit</span>
                </div>
            </div>
        @canany(['servis.view', 'servis.create'])
        </a>
        @else
        </div>
        @endcanany

        <!-- 3. Stok Kritis -->
        @can('wms.view')
        <a href="/app/wms?tab=produk" class="block group relative overflow-hidden rounded-2xl bg-white/[0.03] border border-white/10 p-4 sm:p-5 hover:border-up-red/40 transition-all duration-200 active:scale-[0.98]">
        @else
        <div class="block relative overflow-hidden rounded-2xl bg-white/[0.03] border border-white/10 p-4 sm:p-5">
        @endcan
            <div class="flex items-center justify-between">
                <span class="text-[10px] sm:text-[11px] font-semibold uppercase tracking-wider text-ink-400 group-hover:text-ink-200">Stok Kritis</span>
                <div class="w-8 h-8 rounded-xl bg-up-red/10 border border-up-red/20 flex items-center justify-center text-up-red">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
            </div>
            <div class="mt-2.5">
                <p class="text-2xl font-black text-up-red tabular-nums tracking-tight flex items-baseline gap-1.5">
                    {{ $stokKritis['total'] ?? 0 }} <span class="text-xs font-normal text-ink-400">SKU</span>
                </p>
                <div class="flex items-center justify-between text-[11px] text-ink-400 mt-2 pt-2 border-t border-white/5">
                    <span>Di bawah minimum:</span>
                    <span class="font-bold text-up-red tabular-nums">Perlu PO</span>
                </div>
            </div>
        @can('wms.view')
        </a>
        @else
        </div>
        @endcan

        <!-- 4. Piutang Jatuh Tempo -->
        <x-prism.glass-card class="p-4 sm:p-5 relative overflow-hidden group hover:border-up-accent/40 transition-all duration-200">
            <div class="flex items-center justify-between">
                <span class="text-[10px] sm:text-[11px] font-semibold uppercase tracking-wider text-ink-400">Piutang Jatuh Tempo</span>
                <div class="w-8 h-8 rounded-xl bg-up-accent/10 border border-up-accent/20 flex items-center justify-center text-up-accent">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
            </div>
            <div class="mt-2.5">
                <p class="text-2xl font-black text-white tabular-nums tracking-tight">
                    Rp {{ number_format($piutangJatuhTempo['total_nominal'] ?? 0, 0, ',', '.') }}
                </p>
                <div class="flex items-center justify-between text-[11px] text-ink-400 mt-2 pt-2 border-t border-white/5">
                    <span>Total tagihan lewat:</span>
                    <span class="font-bold text-up-accent tabular-nums">{{ $piutangJatuhTempo['jumlah_invoice'] ?? 0 }} invoice</span>
                </div>
            </div>
        </x-prism.glass-card>
    </div>

    <!-- 2. Charts Row: Tren Omzet 30 Hari (Smooth SVG Spline) & Donut Kategori -->
    <div wire:key="widget-charts-omzet-kategori"
         class="grid grid-cols-1 lg:grid-cols-3 gap-6 transition-all duration-200"
         x-show="layoutConfig?.charts_omzet_kategori?.visible !== false"
         :style="'order: ' + (widgetOrder.indexOf('charts_omzet_kategori') + 1)"
         data-widget-key="charts_omzet_kategori">
        <!-- Tren Omzet 30 Hari -->
        <x-prism.glass-card class="lg:col-span-2 p-5 flex flex-col justify-between" title="Tren Omzet 30 Hari" subtitle="Riwayat akumulasi penjualan POS & online cabang aktif">
            @php
                $values = $chartOmzet30['values'] ?? [];
                $labels = $chartOmzet30['labels'] ?? [];
                $count = count($values);
                $maxVal = max(1, ...($values ?: [1]));
                $svgW = 600;
                $svgH = 180;
                $padX = 15;
                $padY = 20;
                $chartW = $svgW - ($padX * 2);
                $chartH = $svgH - ($padY * 2);

                $pts = [];
                for ($i = 0; $i < $count; $i++) {
                    $x = $padX + ($count > 1 ? ($i / ($count - 1)) * $chartW : $chartW / 2);
                    $y = $padY + $chartH - (($values[$i] / $maxVal) * $chartH);
                    $pts[] = ['x' => round($x, 1), 'y' => round($y, 1), 'val' => $values[$i], 'label' => $labels[$i] ?? ''];
                }

                $lineD = '';
                $areaD = '';
                if ($count > 0) {
                    $lineD = 'M ' . $pts[0]['x'] . ' ' . $pts[0]['y'];
                    for ($i = 1; $i < $count; $i++) {
                        $prev = $pts[$i - 1];
                        $curr = $pts[$i];
                        $cp1x = round($prev['x'] + ($curr['x'] - $prev['x']) * 0.45, 1);
                        $cp1y = $prev['y'];
                        $cp2x = round($prev['x'] + ($curr['x'] - $prev['x']) * 0.55, 1);
                        $cp2y = $curr['y'];
                        $lineD .= ' C ' . $cp1x . ' ' . $cp1y . ', ' . $cp2x . ' ' . $cp2y . ', ' . $curr['x'] . ' ' . $curr['y'];
                    }
                    $bottomY = $padY + $chartH;
                    $areaD = $lineD . ' L ' . $pts[$count - 1]['x'] . ' ' . $bottomY . ' L ' . $pts[0]['x'] . ' ' . $bottomY . ' Z';
                }
            @endphp

            <div class="flex items-center justify-end gap-4 text-xs -mt-2 mb-3">
                <div>
                    <span class="text-[10px] uppercase text-ink-500 font-bold block">Total 30 Hari</span>
                    <span class="text-sm font-black text-white tabular-nums">Rp {{ number_format($chartOmzet30['total'] ?? array_sum($chartOmzet30['values'] ?? []), 0, ',', '.') }}</span>
                </div>
                <div class="border-l border-white/10 pl-4">
                    <span class="text-[10px] uppercase text-ink-500 font-bold block">Rata-rata/Hari</span>
                    <span class="text-sm font-black text-up-mint tabular-nums">Rp {{ number_format($chartOmzet30['avg'] ?? 0, 0, ',', '.') }}</span>
                </div>
            </div>

            <!-- Interactive Area Chart Container -->
            <div x-data="{
                    activeIdx: {{ $count > 0 ? $count - 1 : 0 }},
                    points: {{ json_encode($pts) }},
                    setActive(idx) { this.activeIdx = idx; },
                    resetActive() { this.activeIdx = {{ $count > 0 ? $count - 1 : 0 }}; }
                 }" 
                 class="relative mt-2" 
                 x-on:mouseleave="resetActive()">

                <!-- Tooltip Display -->
                <div class="flex items-center justify-between mb-2 px-1 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-up-primary"></span>
                        <span class="text-ink-400">Tanggal:</span>
                        <span class="font-bold text-white" x-text="points[activeIdx]?.label || '-'"></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-ink-400">Omzet:</span>
                        <span class="font-extrabold text-up-mint tabular-nums" x-text="'Rp ' + (points[activeIdx]?.val || 0).toLocaleString('id-ID')"></span>
                    </div>
                </div>

                <div class="w-full aspect-[600/180] relative">
                    <svg viewBox="0 0 {{ $svgW }} {{ $svgH }}" class="w-full h-full overflow-visible">
                        <defs>
                            <linearGradient id="omzetGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" stop-color="#4F46E5" stop-opacity="0.35" />
                                <stop offset="80%" stop-color="#4F46E5" stop-opacity="0.05" />
                                <stop offset="100%" stop-color="#4F46E5" stop-opacity="0.0" />
                            </linearGradient>
                        </defs>

                        @for($g = 0; $g <= 3; $g++)
                            @php
                                $gy = $padY + ($g / 3) * $chartH;
                            @endphp
                            <line x1="{{ $padX }}" y1="{{ $gy }}" x2="{{ $svgW - $padX }}" y2="{{ $gy }}" stroke="rgba(255,255,255,0.06)" stroke-width="1" stroke-dasharray="3 3"/>
                        @endfor

                        @if(!empty($areaD))
                            <path d="{{ $areaD }}" fill="url(#omzetGrad)" />
                            <path d="{{ $lineD }}" fill="none" stroke="#4F46E5" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                        @endif

                        <template x-for="(pt, idx) in points" :key="idx">
                            <g>
                                <rect :x="pt.x - 10" y="0" width="20" height="{{ $svgH }}" fill="transparent" class="cursor-pointer" x-on:mouseenter="setActive(idx)" />
                                <line x1="0" y1="{{ $padY }}" x2="0" y2="{{ $svgH - $padY }}" stroke="rgba(255,255,255,0.3)" stroke-width="1" stroke-dasharray="2 2" :transform="'translate(' + pt.x + ', 0)'" x-show="activeIdx === idx" />
                                <circle :cx="pt.x" :cy="pt.y" r="5" fill="#4F46E5" stroke="#ffffff" stroke-width="2" x-show="activeIdx === idx" />
                            </g>
                        </template>
                    </svg>
                </div>

                <div class="flex justify-between items-center text-[10px] text-ink-500 mt-2 px-1">
                    <span>{{ $labels[0] ?? '' }}</span>
                    <span>{{ $labels[intdiv($count, 2)] ?? '' }}</span>
                    <span>{{ $labels[$count - 1] ?? '' }}</span>
                </div>
            </div>
        </x-prism.glass-card>

        <!-- Donut Chart: Komposisi Omzet per Kategori -->
        <x-prism.glass-card class="p-5 flex flex-col justify-between" title="Komposisi Produk" subtitle="Proporsi omzet per kategori barang bulan ini">
            @php
                $katLabels = $chartKategori['labels'] ?? [];
                $katValues = $chartKategori['values'] ?? [];
                $katTotal = max(1, array_sum($katValues));
                $donutRadius = 36;
                $circumference = 2 * M_PI * $donutRadius;
                $currentOffset = 0;
                $slices = [];
                foreach ($katLabels as $idx => $lbl) {
                    $val = $katValues[$idx] ?? 0;
                    $pct = round(($val / $katTotal) * 100, 1);
                    $dashLength = ($pct / 100) * $circumference;
                    $dashSpace = $circumference - $dashLength;
                    $slices[] = [
                        'label' => $lbl,
                        'value' => $val,
                        'pct' => $pct,
                        'color' => $palette[$idx % count($palette)],
                        'dasharray' => $dashLength . ' ' . $dashSpace,
                        'dashoffset' => -$currentOffset,
                    ];
                    $currentOffset += $dashLength;
                }
            @endphp

            <div x-data="{
                    hoveredSlice: null,
                    totalVal: {{ $katTotal }},
                    slices: {{ json_encode($slices) }}
                 }" 
                 class="mt-1 flex flex-col items-center">

                <div class="relative w-36 h-36">
                    <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
                        <circle cx="50" cy="50" r="{{ $donutRadius }}" fill="transparent" stroke="rgba(255,255,255,0.05)" stroke-width="12" />

                        @forelse($slices as $idx => $s)
                            <circle cx="50" cy="50" r="{{ $donutRadius }}" 
                                    fill="transparent" 
                                    stroke="{{ $s['color'] }}" 
                                    stroke-width="12" 
                                    stroke-dasharray="{{ $s['dasharray'] }}" 
                                    stroke-dashoffset="{{ $s['dashoffset'] }}" 
                                    stroke-linecap="round"
                                    class="cursor-pointer transition-all duration-150 hover:opacity-80"
                                    x-on:mouseenter="hoveredSlice = {{ $idx }}"
                                    x-on:mouseleave="hoveredSlice = null" />
                        @empty
                            <circle cx="50" cy="50" r="{{ $donutRadius }}" fill="transparent" stroke="rgba(255,255,255,0.1)" stroke-width="12" />
                        @endforelse
                    </svg>

                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none text-center px-2">
                        <span class="text-[9px] uppercase tracking-wider text-ink-400 font-bold" x-text="hoveredSlice !== null ? slices[hoveredSlice]?.label : 'Total'"></span>
                        <span class="text-sm font-black text-white tabular-nums tracking-tight" x-text="hoveredSlice !== null ? (slices[hoveredSlice]?.pct + '%') : 'Rp {{ number_format($katTotal, 0, ',', '.') }}'"></span>
                    </div>
                </div>

                <div class="w-full space-y-1.5 mt-3 pt-3 border-t border-white/5">
                    @forelse($slices as $idx => $s)
                        <div class="flex items-center justify-between text-xs py-0.5 cursor-pointer rounded-lg px-2 transition-colors duration-150"
                             :class="hoveredSlice === {{ $idx }} ? 'bg-white/10' : 'hover:bg-white/5'"
                             x-on:mouseenter="hoveredSlice = {{ $idx }}"
                             x-on:mouseleave="hoveredSlice = null">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $s['color'] }}"></span>
                                <span class="text-ink-300 truncate font-medium">{{ $s['label'] }}</span>
                            </div>
                            <div class="flex items-center gap-2 tabular-nums">
                                <span class="text-white font-bold">{{ $s['pct'] }}%</span>
                                <span class="text-[11px] text-ink-500">Rp {{ number_format($s['value'], 0, ',', '.') }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-ink-500 text-center py-2">Belum ada transaksi bulan ini.</p>
                    @endforelse
                </div>
            </div>
        </x-prism.glass-card>
    </div>

    <!-- 3. Operational Visual Funnels: Status Servis & Piutang Aging Risk Matrix -->
    <div wire:key="widget-operational-funnel"
         class="grid grid-cols-1 lg:grid-cols-2 gap-6 transition-all duration-200"
         x-show="layoutConfig?.operational_funnel?.visible !== false"
         :style="'order: ' + (widgetOrder.indexOf('operational_funnel') + 1)"
         data-widget-key="operational_funnel">
        <!-- 1. Pipeline Tahap Servis Aktif (Kanban Funnel) -->
        <x-prism.glass-card title="Status Servis Aktif" subtitle="Distribusi tahap kanban unit di bengkel">
            @php
                $servisLabels = $chartServisStatus['labels'] ?? [];
                $servisValues = $chartServisStatus['values'] ?? [];
                $maxServis = max(1, ...($servisValues ?: [1]));
                $totalServis = array_sum($servisValues);
            @endphp

            <div class="space-y-3">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    @foreach($servisLabels as $i => $label)
                        @php
                            $val = $servisValues[$i] ?? 0;
                            $stageColor = match(strtolower(trim($label))) {
                                'diajukan online' => 'text-ink-400 bg-white/5 border-white/10',
                                'diterima' => 'text-up-primary bg-up-primary/10 border-up-primary/30',
                                'diagnosa' => 'text-sky-400 bg-sky-500/10 border-sky-500/30',
                                'menunggu approval' => 'text-up-amber bg-up-amber/10 border-up-amber/30',
                                'disetujui' => 'text-indigo-300 bg-indigo-500/10 border-indigo-500/30',
                                'dikerjakan' => 'text-orange-400 bg-orange-500/10 border-orange-500/30',
                                'qc' => 'text-pink-400 bg-pink-500/10 border-pink-500/30',
                                'selesai' => 'text-up-mint bg-up-mint/10 border-up-mint/30',
                                default => 'text-ink-300 bg-white/5 border-white/10',
                            };
                        @endphp
                        <div class="p-2.5 rounded-xl border {{ $stageColor }} flex flex-col justify-between">
                            <span class="text-[10px] font-semibold uppercase tracking-wider block truncate">{{ $label }}</span>
                            <div class="flex items-baseline justify-between mt-1">
                                <span class="text-xl font-black tabular-nums">{{ $val }}</span>
                                <span class="text-[10px] opacity-60">unit</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="pt-3 border-t border-white/5">
                    <div class="flex justify-between text-xs text-ink-400 mb-1.5 font-medium">
                        <span>Total Antrian: <strong class="text-white tabular-nums">{{ $totalServis }} unit</strong></span>
                        @canany(['servis.view', 'servis.create'])
                            <a href="/app/servis" class="text-up-primary hover:text-indigo-400 font-semibold">Buka Board Kanban →</a>
                        @endcanany
                    </div>
                    <div class="h-2 w-full rounded-full bg-white/5 overflow-hidden flex">
                        @foreach($servisLabels as $i => $label)
                            @php
                                $val = $servisValues[$i] ?? 0;
                                $pct = $totalServis > 0 ? ($val / $totalServis) * 100 : 0;
                            @endphp
                            @if($pct > 0)
                                <div class="h-full" style="width: {{ $pct }}%; background-color: {{ $palette[$i % count($palette)] }};" title="{{ $label }}: {{ $val }} unit"></div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        </x-prism.glass-card>

        <!-- 2. Piutang Aging Risk Matrix (Barometer Risiko) -->
        <x-prism.glass-card title="Analisis Umur Piutang" subtitle="Klasifikasi risiko tagihan berdasarkan jatuh tempo">
            @php
                $agingLabels = $chartPiutangAging['labels'] ?? ['0-30 hari', '31-60 hari', '61-90 hari', '>90 hari'];
                $agingValues = $chartPiutangAging['values'] ?? [0, 0, 0, 0];
                $totalAging = max(1, array_sum($agingValues));

                $riskConfig = [
                    0 => ['title' => 'Lancar (0-30h)', 'color' => '#1FBF8F', 'bg' => 'bg-up-mint/10 border-up-mint/30 text-up-mint', 'desc' => 'Risiko Rendah'],
                    1 => ['title' => 'Waspada (31-60h)', 'color' => '#F5A623', 'bg' => 'bg-up-amber/10 border-up-amber/30 text-up-amber', 'desc' => 'Perlu Follow Up'],
                    2 => ['title' => 'Tinggi (61-90h)', 'color' => '#E8873B', 'bg' => 'bg-up-accent/10 border-up-accent/30 text-up-accent', 'desc' => 'Peringatan SP'],
                    3 => ['title' => 'Kritis (>90h)', 'color' => '#EF4444', 'bg' => 'bg-up-red/10 border-up-red/30 text-up-red', 'desc' => 'Macet / Somasi'],
                ];
            @endphp

            <div class="space-y-4">
                <div class="h-3 w-full rounded-full bg-white/5 overflow-hidden flex">
                    @foreach($agingValues as $idx => $v)
                        @php
                            $pct = ($v / $totalAging) * 100;
                        @endphp
                        @if($pct > 0)
                            <div class="h-full transition-all duration-300" style="width: {{ $pct }}%; background-color: {{ $riskConfig[$idx]['color'] }}" title="{{ $agingLabels[$idx] }}: Rp {{ number_format($v, 0, ',', '.') }}"></div>
                        @endif
                    @endforeach
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                    @foreach($agingLabels as $idx => $lbl)
                        @php
                            $nominal = $agingValues[$idx] ?? 0;
                            $pct = round(($nominal / $totalAging) * 100, 1);
                            $cfg = $riskConfig[$idx] ?? $riskConfig[0];
                        @endphp
                        <div class="p-3 rounded-xl border {{ $cfg['bg'] }} flex flex-col justify-between">
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider block">{{ $cfg['title'] }}</span>
                                <span class="text-[9px] opacity-70 block">{{ $cfg['desc'] }}</span>
                            </div>
                            <div class="mt-2">
                                <p class="text-sm font-black tabular-nums tracking-tight">
                                    Rp {{ number_format($nominal, 0, ',', '.') }}
                                </p>
                                <span class="text-[10px] opacity-75 font-semibold tabular-nums mt-0.5 block">
                                    {{ $pct }}% dari total
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-prism.glass-card>
    </div>

    <!-- 4. Stock Kritis & Transaksi Terkini -->
    <div wire:key="widget-feed-transaksi-harga"
         class="grid grid-cols-1 lg:grid-cols-3 gap-6 transition-all duration-200"
         x-show="layoutConfig?.feed_transaksi_harga?.visible !== false"
         :style="'order: ' + (widgetOrder.indexOf('feed_transaksi_harga') + 1)"
         data-widget-key="feed_transaksi_harga">
        <!-- Perubahan Harga Terbaru (dari GRN / Supplier) -->
        <x-prism.glass-card title="Perubahan Harga Terbaru" subtitle="Harga beli masuk dari GRN barang datang">
            <div class="divide-y divide-white/5 max-h-80 overflow-y-auto pr-1 -mt-1">
                @forelse($perubahanHargaTerbaru['items'] as $item)
                    <div class="py-2.5 flex items-center justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold text-white truncate">{{ $item->produk_nama }}</p>
                            <div class="flex items-center gap-1.5 mt-0.5 text-[10px] text-ink-400">
                                <span class="tabular-nums">Rp {{ number_format($item->harga_lama, 0, ',', '.') }}</span>
                                <span>&rarr;</span>
                                <span class="tabular-nums font-medium text-white">Rp {{ number_format($item->harga_baru, 0, ',', '.') }}</span>
                            </div>
                            <span class="block text-[9px] text-ink-500 mt-0.5">{{ $item->waktu }}</span>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold tabular-nums {{ $item->is_naik ? 'bg-up-red/15 text-up-red border border-up-red/30' : 'bg-up-mint/15 text-up-mint border border-up-mint/30' }}">
                                <span>{{ $item->is_naik ? '+' : '' }}{{ number_format($item->persen, 1) }}%</span>
                                <svg class="w-3 h-3 {{ $item->is_naik ? 'rotate-0' : 'rotate-180' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                                </svg>
                            </span>
                            <span class="block text-[9px] text-ink-400 mt-0.5 tabular-nums">
                                {{ $item->is_naik ? '+' : '' }}Rp {{ number_format($item->selisih, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center">
                        <div class="w-10 h-10 rounded-full bg-white/5 border border-white/10 text-ink-400 mx-auto flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <p class="text-xs text-ink-400 font-medium mt-2">Belum ada perubahan harga beli dari penerimaan barang.</p>
                    </div>
                @endforelse
            </div>
        </x-prism.glass-card>

        <!-- Transaksi Terkini Feed -->
        <x-prism.glass-card class="lg:col-span-2" title="Aktivitas Transaksi Terkini" subtitle="Penjualan dan pesanan yang baru masuk di cabang">
            <x-prism.dual-scroll class="-mt-1">
                <table class="w-full text-left text-xs min-w-[580px]">
                    <thead>
                        <tr class="text-[10px] uppercase tracking-wider text-ink-400 font-semibold border-b border-white/5 whitespace-nowrap">
                            <th class="py-2.5 px-3">No. Transaksi</th>
                            <th class="py-2.5 px-3">Sumber</th>
                            <th class="py-2.5 px-3">Cabang</th>
                            <th class="py-2.5 px-3">Waktu</th>
                            <th class="py-2.5 px-3 text-right">Total Akhir</th>
                            <th class="py-2.5 px-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse($transaksiTerbaru as $t)
                            <tr class="hover:bg-white/[0.02] transition-colors duration-150 whitespace-nowrap">
                                <td class="py-2.5 px-3 font-mono font-semibold text-white">
                                    {{ $t->no_transaksi }}
                                </td>
                                <td class="py-2.5 px-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-white/5 border border-white/10 text-ink-300">
                                        {{ ucfirst($t->sumber) }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-ink-400">
                                    {{ $t->cabang?->nama ?? '-' }}
                                </td>
                                <td class="py-2.5 px-3 text-ink-500 tabular-nums">
                                    {{ $t->created_at->format('H:i') }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-black text-white tabular-nums">
                                    Rp {{ number_format($t->total_akhir, 0, ',', '.') }}
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <x-prism.status-pill :status="$t->status" size="sm" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-ink-500">
                                    Belum ada transaksi hari ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </x-prism.dual-scroll>
        </x-prism.glass-card>
    </div>

    <!-- 5. Widget Analisis & Tracking Penjualan Interaktif -->
    <div wire:key="widget-analisis-penjualan"
         class="transition-all duration-200"
         x-show="layoutConfig?.analisis_penjualan?.visible !== false"
         :style="'order: ' + (widgetOrder.indexOf('analisis_penjualan') + 1)"
         data-widget-key="analisis_penjualan">
        <x-prism.glass-card title="Data & Analisis Penjualan" subtitle="{{ $dataPenjualan['periode_label'] ?? 'Hari Ini' }}">
        <div class="space-y-5">
            <!-- Filter Bar & Kontrol Periode -->
            <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3 pb-4 border-b border-white/5">
                <!-- Pills Pilihan Periode Dinamis (Horizontal Scroll on Mobile, No Wrap to Avoid Stacking) -->
                <div class="flex items-center gap-1.5 p-1 rounded-xl bg-white/[0.03] border border-white/5 text-xs overflow-x-auto scrollbar-none flex-nowrap -mx-1 px-1 sm:mx-0 sm:px-1">
                    <button type="button" wire:click="setPeriodePenjualan('today')"
                        class="px-3 py-1.5 rounded-lg font-medium transition-all whitespace-nowrap flex-shrink-0 cursor-pointer {{ $periodePenjualan === 'today' ? 'bg-up-primary text-white shadow-sm' : 'text-ink-400 hover:text-white hover:bg-white/5' }}">
                        Hari Ini
                    </button>
                    <button type="button" wire:click="setPeriodePenjualan('yesterday')"
                        class="px-3 py-1.5 rounded-lg font-medium transition-all whitespace-nowrap flex-shrink-0 cursor-pointer {{ $periodePenjualan === 'yesterday' ? 'bg-up-primary text-white shadow-sm' : 'text-ink-400 hover:text-white hover:bg-white/5' }}">
                        Kemarin
                    </button>
                    <button type="button" wire:click="setPeriodePenjualan('this_week')"
                        class="px-3 py-1.5 rounded-lg font-medium transition-all whitespace-nowrap flex-shrink-0 cursor-pointer {{ $periodePenjualan === 'this_week' ? 'bg-up-primary text-white shadow-sm' : 'text-ink-400 hover:text-white hover:bg-white/5' }}">
                        Minggu Ini
                    </button>
                    <button type="button" wire:click="setPeriodePenjualan('last_week')"
                        class="px-3 py-1.5 rounded-lg font-medium transition-all whitespace-nowrap flex-shrink-0 cursor-pointer {{ $periodePenjualan === 'last_week' ? 'bg-up-primary text-white shadow-sm' : 'text-ink-400 hover:text-white hover:bg-white/5' }}">
                        Minggu Lalu
                    </button>
                    <button type="button" wire:click="setPeriodePenjualan('this_month')"
                        class="px-3 py-1.5 rounded-lg font-medium transition-all whitespace-nowrap flex-shrink-0 cursor-pointer {{ $periodePenjualan === 'this_month' ? 'bg-up-primary text-white shadow-sm' : 'text-ink-400 hover:text-white hover:bg-white/5' }}">
                        Bulan Ini
                    </button>
                    <button type="button" wire:click="setPeriodePenjualan('last_month')"
                        class="px-3 py-1.5 rounded-lg font-medium transition-all whitespace-nowrap flex-shrink-0 cursor-pointer {{ $periodePenjualan === 'last_month' ? 'bg-up-primary text-white shadow-sm' : 'text-ink-400 hover:text-white hover:bg-white/5' }}">
                        Bulan Lalu
                    </button>
                    <button type="button" wire:click="setPeriodePenjualan('custom')"
                        class="px-3 py-1.5 rounded-lg font-medium transition-all whitespace-nowrap flex-shrink-0 cursor-pointer {{ $periodePenjualan === 'custom' ? 'bg-up-primary text-white shadow-sm' : 'text-ink-400 hover:text-white hover:bg-white/5' }}">
                        Kustom
                    </button>
                </div>

                <!-- Input Custom & Filter Dropdown & Aksi Export -->
                <div class="flex flex-wrap items-center gap-2">
                    @if($periodePenjualan === 'custom')
                        <div class="flex items-center gap-1.5 bg-white/[0.02] border border-white/10 rounded-xl px-2 py-1 text-xs w-full sm:w-auto">
                            <input type="date" wire:model.live="penjualanCustomDari"
                                class="bg-transparent text-white border-0 text-xs p-1 focus:ring-0 flex-1 sm:flex-initial">
                            <span class="text-ink-500">s/d</span>
                            <input type="date" wire:model.live="penjualanCustomSampai"
                                class="bg-transparent text-white border-0 text-xs p-1 focus:ring-0 flex-1 sm:flex-initial">
                        </div>
                    @endif

                    <!-- Filter Sumber -->
                    <select wire:model.live="penjualanFilterSumber"
                        class="flex-1 sm:flex-initial min-w-[120px] px-2.5 py-1.5 rounded-xl bg-white/[0.04] border border-white/10 text-xs text-white focus:border-up-primary focus:ring-0">
                        <option value="" class="bg-gray-900 text-white">Semua Sumber</option>
                        <option value="pos" class="bg-gray-900 text-white">Kasir (POS)</option>
                        <option value="marketplace" class="bg-gray-900 text-white">Marketplace</option>
                        <option value="website" class="bg-gray-900 text-white">Website</option>
                        <option value="servis" class="bg-gray-900 text-white">Servis</option>
                    </select>

                    <!-- Filter Status -->
                    <select wire:model.live="penjualanFilterStatus"
                        class="flex-1 sm:flex-initial min-w-[120px] px-2.5 py-1.5 rounded-xl bg-white/[0.04] border border-white/10 text-xs text-white focus:border-up-primary focus:ring-0">
                        <option value="" class="bg-gray-900 text-white">Semua Status</option>
                        <option value="selesai" class="bg-gray-900 text-white">Selesai</option>
                        <option value="pending" class="bg-gray-900 text-white">Pending</option>
                        <option value="dibatalkan" class="bg-gray-900 text-white">Dibatalkan</option>
                    </select>

                    <!-- Search Input -->
                    <div class="relative w-full sm:w-48">
                        <input type="text" wire:model.live.debounce.300ms="penjualanSearch"
                            placeholder="Cari no trx / pelanggan..."
                            class="w-full pl-7 pr-2.5 py-1.5 rounded-xl bg-white/[0.04] border border-white/10 text-xs text-white placeholder-ink-500 focus:border-up-primary focus:ring-0">
                        <svg class="w-3.5 h-3.5 absolute left-2 top-2 text-ink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>

                    <!-- Tombol Export Excel -->
                    <button type="button" wire:click="exportPenjualanExcel" wire:loading.attr="disabled"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-xl bg-up-mint/20 hover:bg-up-mint/30 border border-up-mint/40 text-up-mint text-xs font-semibold shadow-sm transition-all active:scale-[0.98]">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        <span wire:loading.remove wire:target="exportPenjualanExcel">Export Excel</span>
                        <span wire:loading wire:target="exportPenjualanExcel">Mengunduh...</span>
                    </button>
                </div>
            </div>

            <!-- Ringkasan Metrik Terfilter -->
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
                <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5">
                    <span class="text-[10px] uppercase font-bold text-ink-400">Omzet Selesai</span>
                    <p class="text-base sm:text-lg font-black text-up-mint tabular-nums mt-0.5">
                        Rp {{ number_format($dataPenjualan['omzet_selesai'] ?? 0, 0, ',', '.') }}
                    </p>
                    <span class="text-[10px] text-ink-500">{{ $dataPenjualan['trx_selesai'] ?? 0 }} transaksi</span>
                </div>
                <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5">
                    <span class="text-[10px] uppercase font-bold text-ink-400">Total Transaksi</span>
                    <p class="text-base sm:text-lg font-black text-white tabular-nums mt-0.5">
                        {{ $dataPenjualan['total_trx'] ?? 0 }}
                    </p>
                    <span class="text-[10px] text-ink-500">Semua status</span>
                </div>
                <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5">
                    <span class="text-[10px] uppercase font-bold text-ink-400">Pending / Tertahan</span>
                    <p class="text-base sm:text-lg font-black text-up-amber tabular-nums mt-0.5">
                        Rp {{ number_format($dataPenjualan['omzet_pending'] ?? 0, 0, ',', '.') }}
                    </p>
                    <span class="text-[10px] text-ink-500">{{ $dataPenjualan['trx_pending'] ?? 0 }} transaksi</span>
                </div>
                <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5">
                    <span class="text-[10px] uppercase font-bold text-ink-400">Batal</span>
                    <p class="text-base sm:text-lg font-black text-up-red tabular-nums mt-0.5">
                        {{ $dataPenjualan['trx_batal'] ?? 0 }}
                    </p>
                    <span class="text-[10px] text-ink-500">Transaksi void/dibatalkan</span>
                </div>
                <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5">
                    <span class="text-[10px] uppercase font-bold text-ink-400">Total Diskon</span>
                    <p class="text-base sm:text-lg font-black text-up-accent tabular-nums mt-0.5">
                        Rp {{ number_format($dataPenjualan['total_diskon'] ?? 0, 0, ',', '.') }}
                    </p>
                    <span class="text-[10px] text-ink-500">Potongan harga</span>
                </div>
                <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5">
                    <span class="text-[10px] uppercase font-bold text-ink-400">Total Pajak</span>
                    <p class="text-base sm:text-lg font-black text-white tabular-nums mt-0.5">
                        Rp {{ number_format($dataPenjualan['total_pajak'] ?? 0, 0, ',', '.') }}
                    </p>
                    <span class="text-[10px] text-ink-500">PPN terkumpul</span>
                </div>
            </div>

            <!-- Breakdown Pembayaran & Sumber (Mini visualisasi) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-1">
                <!-- Breakdown Metode Pembayaran -->
                <div class="p-3.5 rounded-xl bg-white/[0.02] border border-white/5">
                    <p class="text-xs font-bold text-white mb-2 flex items-center justify-between">
                        <span>Breakdown Metode Pembayaran</span>
                        <span class="text-[10px] text-ink-400 font-normal">Omzet Berhasil</span>
                    </p>
                    <div class="space-y-2">
                        @forelse($dataPenjualan['by_metode'] ?? [] as $m)
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-ink-300 font-medium">{{ $m['metode'] }} ({{ $m['jml'] }})</span>
                                <span class="font-bold text-white tabular-nums">Rp {{ number_format($m['total'], 0, ',', '.') }}</span>
                            </div>
                        @empty
                            <p class="text-xs text-ink-500 py-2 text-center">Belum ada transaksi di periode ini.</p>
                        @endforelse
                    </div>
                </div>

                <!-- Breakdown Sumber Penjualan -->
                <div class="p-3.5 rounded-xl bg-white/[0.02] border border-white/5">
                    <p class="text-xs font-bold text-white mb-2 flex items-center justify-between">
                        <span>Breakdown Kanal / Sumber</span>
                        <span class="text-[10px] text-ink-400 font-normal">Kanal Transaksi</span>
                    </p>
                    <div class="space-y-2">
                        @forelse($dataPenjualan['by_sumber'] ?? [] as $s)
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-ink-300 font-medium">{{ $s['sumber'] }} ({{ $s['jml'] }})</span>
                                <span class="font-bold text-white tabular-nums">Rp {{ number_format($s['total'], 0, ',', '.') }}</span>
                            </div>
                        @empty
                            <p class="text-xs text-ink-500 py-2 text-center">Belum ada transaksi di periode ini.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Tabel Tracking Detail Transaksi -->
            <div class="pt-2">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-ink-300">Tracking Transaksi Terfilter</h4>
                    <span class="text-[11px] text-ink-500">Menampilkan 15 transaksi teratas</span>
                </div>
                <x-prism.dual-scroll>
                    <table class="w-full text-left text-xs min-w-[820px]">
                        <thead>
                            <tr class="text-[10px] uppercase tracking-wider text-ink-400 font-semibold border-b border-white/5 whitespace-nowrap">
                                <th class="py-2.5 px-3">No. Transaksi</th>
                                <th class="py-2.5 px-3">Sumber</th>
                                <th class="py-2.5 px-3">Pelanggan</th>
                                <th class="py-2.5 px-3">Kasir</th>
                                <th class="py-2.5 px-3">Waktu</th>
                                <th class="py-2.5 px-3 text-center">Metode</th>
                                <th class="py-2.5 px-3 text-right">Total Akhir</th>
                                <th class="py-2.5 px-3 text-center">Status</th>
                                <th class="py-2.5 px-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @forelse($dataPenjualan['items'] ?? [] as $item)
                                <tr wire:key="penjualan-row-{{ $item['id'] }}" class="hover:bg-white/[0.02] transition-colors whitespace-nowrap">
                                    <td class="py-2.5 px-3 font-mono font-semibold text-white">
                                        {{ $item['no_transaksi'] }}
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-white/5 border border-white/10 text-ink-300">
                                            {{ ucfirst($item['sumber']) }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 text-ink-300 max-w-[160px] truncate" title="{{ $item['pelanggan_nama'] }}">
                                        {{ $item['pelanggan_nama'] }}
                                    </td>
                                    <td class="py-2.5 px-3 text-ink-400">
                                        {{ $item['kasir_nama'] }}
                                    </td>
                                    <td class="py-2.5 px-3 text-ink-500 tabular-nums">
                                        {{ $item['waktu'] }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center text-ink-300 text-[11px] font-mono">
                                        {{ $item['metode_bayar'] }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-black text-white tabular-nums">
                                        Rp {{ number_format($item['total_akhir'], 0, ',', '.') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <x-prism.status-pill :status="$item['status']" size="sm" />
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <button type="button" wire:click="lihatDetailTransaksi({{ $item['id'] }})"
                                            class="px-2.5 py-1 rounded-lg bg-up-primary/10 hover:bg-up-primary/20 border border-up-primary/30 text-up-primary text-[10px] font-semibold transition-all cursor-pointer whitespace-nowrap">
                                            Detail & Tracking
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="py-6 text-center text-ink-500">
                                        Tidak ada transaksi yang cocok dengan filter.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-prism.dual-scroll>
            </div>
        </div>
    </x-prism.glass-card>
    </div>

    <!-- Modal Detail & Tracking Transaksi Langsung -->
    @if($showDetailTransaksiModal && $selectedDetailTransaksi)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in"
             wire:keydown.escape="tutupDetailTransaksiModal">
            <div class="relative w-full max-w-2xl bg-[#0f172a] border border-white/10 rounded-2xl shadow-2xl p-6 text-white max-h-[90vh] overflow-y-auto">
                <div class="flex items-start justify-between pb-3 border-b border-white/10">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-black tracking-tight font-mono text-up-mint">
                                {{ $selectedDetailTransaksi['no_transaksi'] }}
                            </h3>
                            <x-prism.status-pill :status="$selectedDetailTransaksi['status']" size="sm" />
                        </div>
                        <p class="text-xs text-ink-400 mt-0.5">
                            Sumber: <span class="font-semibold text-white uppercase">{{ $selectedDetailTransaksi['sumber'] }}</span> &bull; 
                            Cabang: {{ $selectedDetailTransaksi['cabang'] }} &bull; 
                            Waktu: {{ $selectedDetailTransaksi['waktu'] }}
                        </p>
                    </div>
                    <button type="button" wire:click="tutupDetailTransaksiModal"
                        class="p-1 rounded-lg text-ink-400 hover:text-white hover:bg-white/10 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Info Pelanggan & Kasir -->
                <div class="grid grid-cols-2 gap-4 py-3 border-b border-white/5 text-xs">
                    <div>
                        <span class="text-ink-500 uppercase font-semibold text-[10px]">Pelanggan</span>
                        <p class="font-bold text-white mt-0.5">{{ $selectedDetailTransaksi['pelanggan'] }}</p>
                        <p class="text-ink-400 text-[11px]">{{ $selectedDetailTransaksi['telepon'] }}</p>
                    </div>
                    <div>
                        <span class="text-ink-500 uppercase font-semibold text-[10px]">Kasir / Petugas</span>
                        <p class="font-bold text-white mt-0.5">{{ $selectedDetailTransaksi['kasir'] }}</p>
                        <p class="text-ink-400 text-[11px]">Metode: {{ $selectedDetailTransaksi['metode_bayar'] }}</p>
                    </div>
                </div>

                @if(!empty($selectedDetailTransaksi['no_tiket']))
                    <div class="py-2 px-3 my-2 rounded-xl bg-up-amber/10 border border-up-amber/20 text-xs flex items-center justify-between">
                        <span class="text-up-amber font-semibold">Terkait Tiket Servis:</span>
                        <span class="font-mono text-white font-bold">{{ $selectedDetailTransaksi['no_tiket'] }}</span>
                    </div>
                @endif

                <!-- Rincian Produk/Item -->
                <div class="py-3">
                    <span class="text-ink-400 uppercase font-bold text-[10px] tracking-wider block mb-2">Item Produk Terjual</span>
                    <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                        @forelse($selectedDetailTransaksi['items'] as $it)
                            <div class="flex items-center justify-between text-xs p-2 rounded-lg bg-white/[0.02] border border-white/5">
                                <div>
                                    <p class="font-semibold text-white">{{ $it['nama'] }}</p>
                                    <p class="text-[10px] text-ink-500 font-mono">SKU: {{ $it['sku'] }} &bull; {{ $it['qty'] }} x Rp {{ number_format($it['harga'], 0, ',', '.') }}</p>
                                </div>
                                <span class="font-bold text-white tabular-nums">
                                    Rp {{ number_format($it['subtotal'], 0, ',', '.') }}
                                </span>
                            </div>
                        @empty
                            <p class="text-xs text-ink-500 text-center py-2">Tidak ada item terdaftar.</p>
                        @endforelse
                    </div>
                </div>

                <!-- Ringkasan Keuangan Transaksi -->
                <div class="pt-3 border-t border-white/10 space-y-1.5 text-xs">
                    <div class="flex justify-between text-ink-400">
                        <span>Subtotal</span>
                        <span class="tabular-nums">Rp {{ number_format($selectedDetailTransaksi['subtotal'], 0, ',', '.') }}</span>
                    </div>
                    @if($selectedDetailTransaksi['diskon'] > 0)
                        <div class="flex justify-between text-up-accent">
                            <span>Diskon</span>
                            <span class="tabular-nums">- Rp {{ number_format($selectedDetailTransaksi['diskon'], 0, ',', '.') }}</span>
                        </div>
                    @endif
                    @if($selectedDetailTransaksi['pajak'] > 0)
                        <div class="flex justify-between text-ink-400">
                            <span>Pajak</span>
                            <span class="tabular-nums">+ Rp {{ number_format($selectedDetailTransaksi['pajak'], 0, ',', '.') }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between text-sm font-black text-white pt-1 border-t border-white/5">
                        <span>Total Akhir</span>
                        <span class="text-up-mint tabular-nums">Rp {{ number_format($selectedDetailTransaksi['total'], 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-xs text-ink-400">
                        <span>Jumlah Bayar ({{ $selectedDetailTransaksi['metode_bayar'] }})</span>
                        <span class="tabular-nums">Rp {{ number_format($selectedDetailTransaksi['bayar'], 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-xs text-ink-400">
                        <span>Kembalian</span>
                        <span class="tabular-nums">Rp {{ number_format($selectedDetailTransaksi['kembalian'], 0, ',', '.') }}</span>
                    </div>
                </div>

                <div class="mt-5 flex justify-end">
                    <button type="button" wire:click="tutupDetailTransaksiModal"
                        class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-semibold transition-all">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- 6. Widgets Khusus Role (Kasir, Finance, Super-Admin, Marketing, Staff Gudang) -->
    <div wire:key="widget-role-specific"
         class="space-y-6 transition-all duration-200"
         x-show="layoutConfig?.role_specific?.visible !== false"
         :style="'order: ' + (widgetOrder.indexOf('role_specific') + 1)"
         data-widget-key="role_specific">
    @role('kasir')
        @if(!empty($omzetShiftKasir))
            <x-prism.glass-card title="Omzet Shift Anda (Kasir)" subtitle="Status sesi: {{ $omzetShiftKasir['status_sesi'] ?? '-' }}">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full {{ ($omzetShiftKasir['status_sesi'] ?? '') === 'buka' ? 'bg-up-mint animate-pulse' : 'bg-ink-500' }}"></span>
                            <span class="text-xs uppercase font-bold tracking-wider text-ink-300">Sesi Kasir Aktif</span>
                        </div>
                        <h3 class="text-lg font-bold text-white mt-1">
                            Shift Anda: <span class="text-up-mint tabular-nums">Rp {{ number_format($omzetShiftKasir['omzet'] ?? 0, 0, ',', '.') }}</span>
                        </h3>
                        <p class="text-xs text-ink-400">Total {{ $omzetShiftKasir['jumlah_transaksi'] ?? 0 }} transaksi berhasil pada shift ini.</p>
                    </div>
                    @canany(['pos.view', 'pos.view-own', 'pos.create'])
                    <a href="/app/pos" class="px-4 py-2 rounded-xl bg-up-primary hover:bg-indigo-600 text-white font-semibold text-xs shadow-md transition-all active:scale-[0.98]">
                        Lanjutkan Transaksi Kasir →
                    </a>
                    @endcanany
                </div>
            </x-prism.glass-card>
        @endif
    @endrole

    @role('finance')
        <x-prism.glass-card title="Ringkasan Keuangan Bulan Ini" subtitle="Laba/rugi + Piutang">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                    <p class="text-[10px] uppercase tracking-wider text-ink-400 font-bold">Laba Bersih</p>
                    <p class="text-xl font-black {{ ($ringkasanKeuangan['laba_bulan_ini'] ?? 0) >= 0 ? 'text-up-mint' : 'text-up-red' }} tabular-nums mt-1">
                        Rp {{ number_format(abs($ringkasanKeuangan['laba_bulan_ini'] ?? 0), 0, ',', '.') }}
                    </p>
                </div>
                <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                    <p class="text-[10px] uppercase tracking-wider text-ink-400 font-bold">Total Piutang</p>
                    <p class="text-xl font-black text-up-amber tabular-nums mt-1">
                        Rp {{ number_format($ringkasanKeuangan['total_piutang'] ?? 0, 0, ',', '.') }}
                    </p>
                </div>
                <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                    <p class="text-[10px] uppercase tracking-wider text-ink-400 font-bold">Piutang Lewat Tempo</p>
                    <p class="text-xl font-black text-up-red tabular-nums mt-1">{{ $ringkasanKeuangan['piutang_lewat'] ?? 0 }}</p>
                </div>
            </div>
        </x-prism.glass-card>

        <x-prism.glass-card title="Proyeksi Arus Kas 30 Hari" subtitle="Kas + Piutang 30d − Utang 30d">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="p-3 rounded-xl bg-up-mint/10 border border-up-mint/30">
                    <p class="text-[10px] uppercase tracking-wider text-ink-400 font-bold">Saldo Kas Sekarang</p>
                    <p class="text-xl font-black text-up-mint tabular-nums mt-1">Rp {{ number_format($treasuryProjection['saldo_kas'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div class="p-3 rounded-xl bg-up-primary/10 border border-up-primary/30">
                    <p class="text-[10px] uppercase tracking-wider text-ink-400 font-bold">Piutang Jatuh Tempo 30d</p>
                    <p class="text-xl font-black text-up-primary tabular-nums mt-1">Rp {{ number_format($treasuryProjection['piutang_30d'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div class="p-3 rounded-xl bg-up-red/10 border border-up-red/30">
                    <p class="text-[10px] uppercase tracking-wider text-ink-400 font-bold">Utang Jatuh Tempo 30d</p>
                    <p class="text-xl font-black text-up-red tabular-nums mt-1">Rp {{ number_format($treasuryProjection['utang_30d'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div class="p-3 rounded-xl bg-up-accent/10 border border-up-accent/30">
                    <p class="text-[10px] uppercase tracking-wider text-ink-400 font-bold">Proyeksi 30 Hari</p>
                    <p class="text-xl font-black {{ ($treasuryProjection['proyeksi_30d'] ?? 0) >= 0 ? 'text-up-mint' : 'text-up-red' }} tabular-nums mt-1">Rp {{ number_format(abs($treasuryProjection['proyeksi_30d'] ?? 0), 0, ',', '.') }}</p>
                </div>
            </div>
            <div class="flex gap-3 mt-4 flex-wrap">
                @can('laporan.cabang')
                    <a href="{{ $treasuryProjection['drill_piutang'] ?? route('laporan.drill', ['model' => 'Piutang']) }}" class="text-[11px] text-up-primary hover:text-indigo-400 font-semibold">Drill-down Piutang →</a>
                    <a href="{{ $treasuryProjection['drill_utang'] ?? route('laporan.drill', ['model' => 'Utang']) }}" class="text-[11px] text-up-amber hover:text-orange-400 font-semibold">Drill-down Utang →</a>
                @endcan
            </div>
        </x-prism.glass-card>
    @endrole

    @role('super-admin')
        <x-prism.glass-card title="Proyeksi Arus Kas 30 Hari" subtitle="Kas + Piutang 30d − Utang 30d">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="p-3 rounded-xl bg-up-mint/10 border border-up-mint/30">
                    <p class="text-[10px] uppercase tracking-wider text-ink-400 font-bold">Saldo Kas Sekarang</p>
                    <p class="text-xl font-black text-up-mint tabular-nums mt-1">Rp {{ number_format($treasuryProjection['saldo_kas'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div class="p-3 rounded-xl bg-up-primary/10 border border-up-primary/30">
                    <p class="text-[10px] uppercase tracking-wider text-ink-400 font-bold">Piutang Jatuh Tempo 30d</p>
                    <p class="text-xl font-black text-up-primary tabular-nums mt-1">Rp {{ number_format($treasuryProjection['piutang_30d'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div class="p-3 rounded-xl bg-up-red/10 border border-up-red/30">
                    <p class="text-[10px] uppercase tracking-wider text-ink-400 font-bold">Utang Jatuh Tempo 30d</p>
                    <p class="text-xl font-black text-up-red tabular-nums mt-1">Rp {{ number_format($treasuryProjection['utang_30d'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div class="p-3 rounded-xl bg-up-accent/10 border border-up-accent/30">
                    <p class="text-[10px] uppercase tracking-wider text-ink-400 font-bold">Proyeksi 30 Hari</p>
                    <p class="text-xl font-black {{ ($treasuryProjection['proyeksi_30d'] ?? 0) >= 0 ? 'text-up-mint' : 'text-up-red' }} tabular-nums mt-1">Rp {{ number_format(abs($treasuryProjection['proyeksi_30d'] ?? 0), 0, ',', '.') }}</p>
                </div>
            </div>
            <div class="flex gap-3 mt-4 flex-wrap">
                @can('laporan.cabang')
                    <a href="{{ $treasuryProjection['drill_piutang'] ?? route('laporan.drill', ['model' => 'Piutang']) }}" class="text-[11px] text-up-primary hover:text-indigo-400 font-semibold">Drill-down Piutang →</a>
                    <a href="{{ $treasuryProjection['drill_utang'] ?? route('laporan.drill', ['model' => 'Utang']) }}" class="text-[11px] text-up-amber hover:text-orange-400 font-semibold">Drill-down Utang →</a>
                @endcan
            </div>
        </x-prism.glass-card>
    @endrole

    @role('marketing')
        <x-prism.glass-card title="Komposisi Tier Pelanggan" subtitle="Total: {{$marketInsight['total_pelanggan'] ?? 0}} pelanggan">
            <div class="space-y-2">
                @foreach($marketInsight['tier'] as $t)
                    <div class="flex items-center gap-2 text-[11px]">
                        <span class="w-24 truncate text-ink-300">{{ $t['nama'] }}</span>
                        <div class="flex-1 h-3 rounded bg-white/5 overflow-hidden">
                            <div class="h-full" style="width: {{ $t['persen'] }}%; background-color: {{ $palette[$loop->index % count($palette)] }}"></div>
                        </div>
                        <span class="tabular-nums text-white font-semibold">{{ $t['persen'] }}%</span>
                        <span class="text-ink-500">({{ $t['jumlah'] }})</span>
                    </div>
                @endforeach
            </div>
        </x-prism.glass-card>
        <x-prism.glass-card title="Performa Broadcast" subtitle="{{$marketInsight['broadcast']['total_kampanye'] ?? 0}} kampanye · status lifecycle">
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-center">
                <div>
                    <p class="text-xl font-black text-ink-300 tabular-nums">{{ $marketInsight['broadcast']['draft'] ?? 0 }}</p>
                    <p class="text-[10px] text-ink-500">Draft</p>
                </div>
                <div>
                    <p class="text-xl font-black text-up-amber tabular-nums">{{ $marketInsight['broadcast']['terjadwal'] ?? 0 }}</p>
                    <p class="text-[10px] text-ink-500">Terjadwal</p>
                </div>
                <div>
                    <p class="text-xl font-black text-up-mint tabular-nums">{{ $marketInsight['broadcast']['terkirim'] ?? 0 }}</p>
                    <p class="text-[10px] text-ink-500">Terkirim</p>
                </div>
                <div>
                    <p class="text-xl font-black text-up-accent tabular-nums">{{ $marketInsight['broadcast']['terkirim_sebagian'] ?? 0 }}</p>
                    <p class="text-[10px] text-ink-500">Sebagian</p>
                </div>
                <div>
                    <p class="text-xl font-black text-up-red tabular-nums">{{ $marketInsight['broadcast']['gagal'] ?? 0 }}</p>
                    <p class="text-[10px] text-ink-500">Gagal</p>
                </div>
            </div>
        </x-prism.glass-card>
    @endrole

    @role('staff-gudang')
        <x-prism.glass-card title="PO Pending (Usulan/Draft/Dikirim)" subtitle="Total nilai: Rp {{ number_format($poPending['total_nilai'] ?? 0, 0, ',', '.') }} ({{ $poPending['total'] ?? 0 }} PO)">
            <div class="space-y-2 max-h-48 overflow-y-auto">
                @forelse($poPending['items'] as $po)
                    <div class="flex justify-between items-center py-2 border-b border-white/5 last:border-0">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold text-white font-mono">{{ $po->no_po }}</p>
                            <p class="text-[10px] text-ink-500 truncate">{{ $po->supplier?->nama }}</p>
                        </div>
                        <div class="text-right flex-shrink-0 ml-3">
                            <p class="text-xs font-bold text-up-amber tabular-nums">Rp {{ number_format($po->total, 0, ',', '.') }}</p>
                            <x-prism.status-pill :status="$po->status" size="sm" />
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-ink-500 py-4 text-center">Tidak ada PO pending.</p>
                @endforelse
            </div>
        </x-prism.glass-card>
    @endrole
    </div>
    </div>
    <!-- Modal Kustomisasi Layout & Urutan Widget Dashboard -->
    <div x-show="showCustomizer"
         style="display: none;"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in"
         @keydown.escape.window="showCustomizer = false">
        <div class="relative w-full max-w-lg bg-[#0f172a] border border-white/10 rounded-2xl shadow-2xl p-4 sm:p-6 text-white max-h-[90vh] overflow-y-auto"
             @click.away="showCustomizer = false">
            <div class="flex items-center justify-between pb-3 border-b border-white/10">
                <div class="min-w-0 pr-2">
                    <h3 class="text-sm sm:text-base font-bold text-white flex items-center gap-2 truncate">
                        <svg class="w-4 h-4 text-up-mint flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                        </svg>
                        <span>Atur Susunan Widget Dashboard</span>
                    </h3>
                    <p class="text-[11px] sm:text-xs text-ink-400 mt-0.5">Ubah urutan (geser / tombol panah) atau sembunyikan/tampilkan widget.</p>
                </div>
                <button type="button" @click="showCustomizer = false"
                    class="p-2 rounded-xl text-ink-400 hover:text-white hover:bg-white/10 transition-colors min-h-[44px] min-w-[44px] flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- List Widget dengan Drag & Drop / Tombol Pindah Posisi -->
            <div class="py-4 space-y-2.5">
                <template x-for="(wKey, index) in widgetOrder" :key="wKey">
                    <div class="p-3 sm:p-3.5 rounded-xl border transition-all duration-150 flex items-center justify-between gap-2.5 select-none"
                         :class="{
                             'bg-white/[0.04] border-white/10 hover:border-white/20': layoutConfig[wKey]?.visible,
                             'bg-white/[0.01] border-white/5 opacity-50': !layoutConfig[wKey]?.visible,
                             'border-up-primary ring-1 ring-up-primary': overKey === wKey
                         }"
                         draggable="true"
                         @dragstart="onDragStart(wKey)"
                         @dragover.prevent="overKey = wKey"
                         @dragleave="if (overKey === wKey) overKey = null"
                         @drop.prevent="onDrop(wKey)">

                        <div class="flex items-center gap-2.5 min-w-0 flex-1">
                            <!-- Drag Handle Icon (hidden di layar sentuh sangat kecil agar ringkas, tap panah tetap ada) -->
                            <div class="hidden sm:flex cursor-grab active:cursor-grabbing text-ink-500 hover:text-ink-300 p-1 flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/>
                                </svg>
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="text-xs sm:text-sm font-semibold text-white truncate" x-text="layoutConfig[wKey]?.label || wKey"></p>
                                <span class="text-[10px] text-ink-400 font-mono" x-text="'Urutan #' + (index + 1)"></span>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 flex-shrink-0">
                            <!-- Tombol Naik (Min-h 36px untuk ramah sentuhan mobile) -->
                            <button type="button" @click="moveUp(index)" :disabled="index === 0"
                                class="p-2 sm:p-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white disabled:opacity-20 disabled:cursor-not-allowed transition-all min-h-[36px] min-w-[36px] flex items-center justify-center active:scale-95"
                                title="Pindah ke Atas">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                                </svg>
                            </button>

                            <!-- Tombol Turun -->
                            <button type="button" @click="moveDown(index)" :disabled="index === widgetOrder.length - 1"
                                class="p-2 sm:p-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white disabled:opacity-20 disabled:cursor-not-allowed transition-all min-h-[36px] min-w-[36px] flex items-center justify-center active:scale-95"
                                title="Pindah ke Bawah">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>

                            <!-- Tombol Toggle Tampil/Sembunyi -->
                            <button type="button" @click="toggleVisibility(wKey)"
                                class="px-2.5 py-1.5 rounded-lg text-[11px] font-bold transition-all min-h-[36px] flex items-center justify-center active:scale-95"
                                :class="layoutConfig[wKey]?.visible ? 'bg-up-mint/20 border border-up-mint/30 text-up-mint hover:bg-up-mint/30' : 'bg-white/10 text-ink-400 hover:bg-white/15 hover:text-white'">
                                <span x-text="layoutConfig[wKey]?.visible ? 'Tampil' : 'Sembunyi'"></span>
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Footer Aksi -->
            <div class="pt-4 border-t border-white/10 flex items-center justify-between gap-3">
                <button type="button" @click="resetLayout()"
                    class="px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-ink-400 hover:text-white text-xs font-semibold transition-all min-h-[40px] flex items-center">
                    Reset Default
                </button>
                <button type="button" @click="showCustomizer = false"
                    class="px-4 py-2 rounded-xl bg-up-primary hover:bg-indigo-600 text-white text-xs font-semibold shadow-md transition-all active:scale-[0.98] min-h-[40px] flex items-center">
                    Simpan & Tutup
                </button>
            </div>
        </div>
    </div>
</div>
