<div class="space-y-6" wire:poll.60s>
    @php
        $palette = ['#5B4FE9', '#1FBF8F', '#F5A623', '#E8873B', '#38BDF8', '#EC4899', '#A855F7'];

        $hour = (int) now()->format('H');
        $salam = match(true) {
            $hour < 11 => 'Selamat Pagi',
            $hour < 15 => 'Selamat Siang',
            $hour < 18 => 'Selamat Sore',
            default => 'Selamat Malam',
        };
    @endphp

    <!-- Greeting & Quick Actions Bar -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 p-4 sm:p-5 rounded-2xl bg-white/[0.02] border border-white/10 backdrop-blur-md">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold tracking-wide uppercase bg-up-primary/20 text-indigo-300 border border-up-primary/30">
                    {{ session('cabang_nama', 'Semua Cabang') }}
                </span>
                <span class="inline-flex items-center gap-1.5 text-[11px] text-ink-400">
                    <span class="w-1.5 h-1.5 rounded-full bg-up-mint animate-pulse"></span>
                    Live Sync 60s
                </span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                {{ $salam }}, {{ auth()->user()?->name }} <span class="text-lg">👋</span>
            </h1>
            <p class="text-xs text-ink-400">
                {{ now()->isoFormat('dddd, D MMMM Y') }} — Ringkasan performa & kesehatan operasional bengkel/toko.
            </p>
        </div>

        <div class="flex items-center gap-2 overflow-x-auto scrollbar-none py-1 -my-1 flex-nowrap sm:flex-wrap">
            @can('pos.kasir')
                <a href="/app/pos" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-up-primary hover:bg-indigo-600 text-white text-xs font-semibold shadow-md transition-all duration-150 active:scale-[0.97] whitespace-nowrap min-h-[44px]">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    Buka Kasir
                </a>
            @endcan
            @can('servis.tiket')
                <a href="/app/servis" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white/[0.06] hover:bg-white/[0.1] text-ink-200 hover:text-white text-xs font-semibold border border-white/10 transition-all duration-150 active:scale-[0.97] whitespace-nowrap min-h-[44px]">
                    <svg class="w-3.5 h-3.5 text-up-amber" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Tiket Servis
                </a>
            @endcan
            @role('super-admin')
                <span class="text-[10px] font-mono text-ink-400 bg-black/40 border border-white/10 px-2.5 py-2 rounded-lg whitespace-nowrap min-h-[44px] flex items-center">
                    {{ $serverStatus['env'] }} · {{ $serverStatus['queue'] }}
                </span>
            @endrole
        </div>
    </div>

    <!-- 4 KPI Metrics Utama -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
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
        <a href="/app/servis" class="block group relative overflow-hidden rounded-2xl bg-white/[0.03] border border-white/10 p-4 sm:p-5 hover:border-up-amber/40 transition-all duration-200 active:scale-[0.98]">
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
        </a>

        <!-- 3. Stok Kritis -->
        <a href="/app/wms?tab=produk" class="block group relative overflow-hidden rounded-2xl bg-white/[0.03] border border-white/10 p-4 sm:p-5 hover:border-up-red/40 transition-all duration-200 active:scale-[0.98]">
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
        </a>

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

    <!-- Charts Row: Tren Omzet 30 Hari (Smooth SVG Spline) & Donut Kategori -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
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
                                <stop offset="0%" stop-color="#5B4FE9" stop-opacity="0.45" />
                                <stop offset="80%" stop-color="#5B4FE9" stop-opacity="0.05" />
                                <stop offset="100%" stop-color="#5B4FE9" stop-opacity="0.0" />
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
                            <path d="{{ $lineD }}" fill="none" stroke="#5B4FE9" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                        @endif

                        <template x-for="(pt, idx) in points" :key="idx">
                            <g>
                                <rect :x="pt.x - 10" y="0" width="20" height="{{ $svgH }}" fill="transparent" class="cursor-pointer" x-on:mouseenter="setActive(idx)" />
                                <line x1="0" y1="{{ $padY }}" x2="0" y2="{{ $svgH - $padY }}" stroke="rgba(255,255,255,0.3)" stroke-width="1" stroke-dasharray="2 2" :transform="'translate(' + pt.x + ', 0)'" x-show="activeIdx === idx" />
                                <circle :cx="pt.x" :cy="pt.y" r="5" fill="#5B4FE9" stroke="#ffffff" stroke-width="2" x-show="activeIdx === idx" />
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

    <!-- Operational Visual Funnels: Status Servis & Piutang Aging Risk Matrix -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
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
                        <a href="/app/servis" class="text-up-primary hover:text-indigo-400 font-semibold">Buka Board Kanban →</a>
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

    <!-- Stock Kritis & Transaksi Terkini -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Top Stok Kritis (Visual Barometer Gauge) -->
        <x-prism.glass-card title="Stok Kritis" subtitle="Item di bawah ambang batas minimum">
            <div class="divide-y divide-white/5 max-h-80 overflow-y-auto pr-1 -mt-1">
                @forelse($stokKritis['items'] as $item)
                    @php
                        $jumlah = (int) ($item->jumlah ?? 0);
                        $min = max(1, (int) ($item->jumlah_minimum ?? 1));
                        $ratio = min(100, round(($jumlah / $min) * 100));
                        $isZero = $jumlah <= 0;
                    @endphp
                    <div class="py-2.5 flex items-center justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold text-white truncate">{{ $item->produk?->nama ?? '-' }}</p>
                            <p class="text-[10px] text-ink-500 truncate">{{ $item->gudang?->nama ?? '-' }}</p>
                            <div class="w-full h-1.5 bg-white/5 rounded-full mt-1.5 overflow-hidden">
                                <div class="h-full rounded-full {{ $isZero ? 'bg-up-red' : ($ratio < 50 ? 'bg-up-accent' : 'bg-up-amber') }}" style="width: {{ max(5, $ratio) }}%"></div>
                            </div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold tabular-nums {{ $isZero ? 'bg-up-red/20 text-up-red border border-up-red/30' : 'bg-up-amber/15 text-up-amber' }}">
                                {{ $jumlah }} / {{ $min }}
                            </span>
                            <span class="block text-[9px] text-ink-500 mt-0.5">{{ $isZero ? 'Habis Total' : 'Kritis' }}</span>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center">
                        <div class="w-10 h-10 rounded-full bg-up-mint/10 border border-up-mint/20 text-up-mint mx-auto flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <p class="text-xs text-ink-400 font-medium mt-2">Semua stok berada dalam batas aman.</p>
                    </div>
                @endforelse
            </div>
        </x-prism.glass-card>

        <!-- Transaksi Terkini Feed -->
        <x-prism.glass-card class="lg:col-span-2" title="Aktivitas Transaksi Terkini" subtitle="Penjualan dan pesanan yang baru masuk di cabang">
            <div class="overflow-x-auto -mt-1">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-[10px] uppercase tracking-wider text-ink-400 font-semibold border-b border-white/5">
                            <th class="pb-2">No. Transaksi</th>
                            <th class="pb-2">Sumber</th>
                            <th class="pb-2">Cabang</th>
                            <th class="pb-2">Waktu</th>
                            <th class="pb-2 text-right">Total Akhir</th>
                            <th class="pb-2 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse($transaksiTerbaru as $t)
                            <tr class="hover:bg-white/[0.02] transition-colors duration-150">
                                <td class="py-2.5 font-mono font-semibold text-white">
                                    {{ $t->no_transaksi }}
                                </td>
                                <td class="py-2.5">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-white/5 border border-white/10 text-ink-300">
                                        {{ ucfirst($t->sumber) }}
                                    </span>
                                </td>
                                <td class="py-2.5 text-ink-400">
                                    {{ $t->cabang?->nama ?? '-' }}
                                </td>
                                <td class="py-2.5 text-ink-500 tabular-nums">
                                    {{ $t->created_at->format('H:i') }}
                                </td>
                                <td class="py-2.5 text-right font-black text-white tabular-nums">
                                    Rp {{ number_format($t->total_akhir, 0, ',', '.') }}
                                </td>
                                <td class="py-2.5 text-center">
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
            </div>
        </x-prism.glass-card>
    </div>

    <!-- Widgets Khusus Role (Kasir, Finance, Super-Admin, Marketing, Staff Gudang) -->
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
                    <a href="/app/pos" class="px-4 py-2 rounded-xl bg-up-primary hover:bg-indigo-600 text-white font-semibold text-xs shadow-md transition-all active:scale-[0.98]">
                        Lanjutkan Transaksi Kasir →
                    </a>
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
