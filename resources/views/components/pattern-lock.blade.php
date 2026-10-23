@props([
    'wireModel' => 'kunci_terenkripsi',
    'dark' => true,
])

<div
    x-data="{
        sequence: [],
        isDragging: false,
        currentPos: null,
        dots: [
            { id: 1, x: 50,  y: 50,  label: 'Kiri Atas', row: 0, col: 0 },
            { id: 2, x: 150, y: 50,  label: 'Tengah Atas', row: 0, col: 1 },
            { id: 3, x: 250, y: 50,  label: 'Kanan Atas', row: 0, col: 2 },
            { id: 4, x: 50,  y: 150, label: 'Kiri Tengah', row: 1, col: 0 },
            { id: 5, x: 150, y: 150, label: 'Tengah / Pusat', row: 1, col: 1 },
            { id: 6, x: 250, y: 150, label: 'Kanan Tengah', row: 1, col: 2 },
            { id: 7, x: 50,  y: 250, label: 'Kiri Bawah', row: 2, col: 0 },
            { id: 8, x: 150, y: 250, label: 'Tengah Bawah', row: 2, col: 1 },
            { id: 9, x: 250, y: 250, label: 'Kanan Bawah', row: 2, col: 2 },
        ],
        isDark: {{ $dark ? 'true' : 'false' }},
        init() {
            if (!{{ $dark ? 'true' : 'false' }}) {
                this.isDark = document.documentElement.classList.contains('dark');
                if (window.MutationObserver) {
                    const obs = new MutationObserver(() => {
                        this.isDark = document.documentElement.classList.contains('dark');
                    });
                    obs.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
                }
            }
            this.syncFromWire();
            $watch('$wire.{{ $wireModel }}', (val) => {
                if (!val || val === '') {
                    this.sequence = [];
                    this.currentPos = null;
                } else if (Array.isArray(val)) {
                    this.sequence = val.map(Number).filter(n => n >= 1 && n <= 9);
                } else if (typeof val === 'string' && val.includes('-')) {
                    this.sequence = val.split('-').map(Number).filter(n => n >= 1 && n <= 9);
                }
            });
        },
        syncFromWire() {
            let val = $wire.get('{{ $wireModel }}');
            if (Array.isArray(val)) {
                this.sequence = val.map(Number).filter(n => n >= 1 && n <= 9);
            } else if (typeof val === 'string' && val.trim() !== '') {
                this.sequence = val.split('-').map(Number).filter(n => n >= 1 && n <= 9);
            }
        },
        getDot(id) {
            return this.dots.find(d => d.id === id);
        },
        getSvgCoords(e) {
            const svg = this.$refs.svgCanvas;
            if (!svg) return { x: 0, y: 0 };
            const rect = svg.getBoundingClientRect();
            const clientX = e.clientX ?? (e.touches && e.touches[0] ? e.touches[0].clientX : 0);
            const clientY = e.clientY ?? (e.touches && e.touches[0] ? e.touches[0].clientY : 0);
            return {
                x: ((clientX - rect.left) / rect.width) * 300,
                y: ((clientY - rect.top) / rect.height) * 300,
            };
        },
        startDrag(e) {
            this.isDragging = true;
            const pos = this.getSvgCoords(e);
            this.currentPos = pos;
            this.checkHit(pos);
        },
        onDrag(e) {
            if (!this.isDragging) return;
            const pos = this.getSvgCoords(e);
            this.currentPos = pos;
            this.checkHit(pos);
        },
        endDrag() {
            if (!this.isDragging) return;
            this.isDragging = false;
            this.currentPos = null;
            this.syncToWire();
        },
        checkHit(pos) {
            const threshold = 36;
            for (const dot of this.dots) {
                const dist = Math.hypot(pos.x - dot.x, pos.y - dot.y);
                if (dist <= threshold && !this.sequence.includes(dot.id)) {
                    this.sequence.push(dot.id);
                    if (navigator.vibrate) try { navigator.vibrate(15); } catch (_) {}
                    break;
                }
            }
        },
        clickDot(id) {
            if (!this.sequence.includes(id)) {
                this.sequence.push(id);
                this.syncToWire();
            }
        },
        reset() {
            this.sequence = [];
            this.currentPos = null;
            this.syncToWire();
        },
        syncToWire() {
            const result = this.sequence.length > 0 ? this.sequence.join('-') : '';
            $wire.set('{{ $wireModel }}', result);
        },
        get polylinePoints() {
            return this.sequence.map(id => {
                const d = this.getDot(id);
                return `${d.x},${d.y}`;
            }).join(' ');
        },
        get lastDot() {
            if (this.sequence.length === 0) return null;
            return this.getDot(this.sequence[this.sequence.length - 1]);
        },
        get urutanString() {
            return this.sequence.length > 0 ? this.sequence.join(' ➔ ') : '-';
        },
        get alurPosisi() {
            if (this.sequence.length === 0) return '-';
            return this.sequence.map(id => this.getDot(id).label).join(' ➔ ');
        },
        get alurArah() {
            if (this.sequence.length < 2) return this.sequence.length === 1 ? 'Mulai dari ' + this.getDot(this.sequence[0]).label : '-';
            const arahList = [];
            for (let i = 0; i < this.sequence.length - 1; i++) {
                const from = this.getDot(this.sequence[i]);
                const to = this.getDot(this.sequence[i + 1]);
                const dRow = to.row - from.row;
                const dCol = to.col - from.col;
                let dir = '';
                if (dRow === 0 && dCol > 0) dir = 'Kanan ➔';
                else if (dRow === 0 && dCol < 0) dir = 'Kiri ⬅';
                else if (dRow > 0 && dCol === 0) dir = 'Bawah ⬇';
                else if (dRow < 0 && dCol === 0) dir = 'Atas ⬆';
                else if (dRow > 0 && dCol > 0) dir = 'Serong Kanan-Bawah ↘';
                else if (dRow > 0 && dCol < 0) dir = 'Serong Kiri-Bawah ↙';
                else if (dRow < 0 && dCol > 0) dir = 'Serong Kanan-Atas ↗';
                else if (dRow < 0 && dCol < 0) dir = 'Serong Kiri-Atas ↖';
                else dir = `${from.id} ke ${to.id}`;
                arahList.push(`${from.id}➔${to.id} (${dir})`);
            }
            return arahList.join(' • ');
        }
    }"
    class="w-full select-none"
>
    <!-- Petunjuk Interaktif -->
    <div class="flex items-center justify-between mb-2">
        <p class="text-[11px] {{ $dark ? 'text-ink-300' : 'text-ink-600 dark:text-ink-300' }} font-medium flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-up-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11.5V14m0-2.5v-6a1.5 1.5 0 113 0m-3 6a1.5 1.5 0 00-3 0v2a7.5 7.5 0 0015 0v-5a1.5 1.5 0 00-3 0m-6-3V11m0-5.5v-1a1.5 1.5 0 013 0v1m0 0V11m0-5.5a1.5 1.5 0 013 0v3m0 0V11" />
            </svg>
            <span>Geser / swipe pola antar titik (seperti di HP Android):</span>
        </p>
        <button
            type="button"
            @click="reset()"
            class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-up-red/10 text-up-red hover:bg-up-red/20 border border-up-red/20 cursor-pointer transition-colors"
        >
            Reset Pola
        </button>
    </div>

    <!-- Area Kanvas Pola Interaktif -->
    <div class="flex flex-col sm:flex-row gap-4 items-center sm:items-start justify-center">
        <div class="relative w-64 h-64 sm:w-60 sm:h-60 rounded-2xl {{ $dark ? 'bg-ink-950' : 'bg-slate-100 dark:bg-ink-950' }} border-2 border-up-primary/40 shadow-xl flex items-center justify-center p-2 touch-none">
            <svg
                x-ref="svgCanvas"
                viewBox="0 0 300 300"
                class="w-full h-full cursor-crosshair"
                style="touch-action: none;"
                @pointerdown="startDrag($event)"
                @pointermove="onDrag($event)"
                @pointerup="endDrag($event)"
                @pointercancel="endDrag($event)"
            >
                <defs>
                    <filter id="glow-pola" x="-20%" y="-20%" width="140%" height="140%">
                        <feDropShadow dx="0" dy="0" stdDeviation="3" flood-color="#8B5CF6" flood-opacity="0.8"/>
                    </filter>
                </defs>
                <!-- Grid background lines (decorative subtle) -->
                <line x1="50" y1="50" x2="250" y2="50" :stroke="isDark ? 'rgba(255, 255, 255, 0.15)' : 'rgba(0, 0, 0, 0.15)'" stroke-dasharray="4,4" />
                <line x1="50" y1="150" x2="250" y2="150" :stroke="isDark ? 'rgba(255, 255, 255, 0.15)' : 'rgba(0, 0, 0, 0.15)'" stroke-dasharray="4,4" />
                <line x1="50" y1="250" x2="250" y2="250" :stroke="isDark ? 'rgba(255, 255, 255, 0.15)' : 'rgba(0, 0, 0, 0.15)'" stroke-dasharray="4,4" />
                <line x1="50" y1="50" x2="50" y2="250" :stroke="isDark ? 'rgba(255, 255, 255, 0.15)' : 'rgba(0, 0, 0, 0.15)'" stroke-dasharray="4,4" />
                <line x1="150" y1="50" x2="150" y2="250" :stroke="isDark ? 'rgba(255, 255, 255, 0.15)' : 'rgba(0, 0, 0, 0.15)'" stroke-dasharray="4,4" />
                <line x1="250" y1="50" x2="250" y2="250" :stroke="isDark ? 'rgba(255, 255, 255, 0.15)' : 'rgba(0, 0, 0, 0.15)'" stroke-dasharray="4,4" />

                <!-- Polyline connecting selected dots -->
                <polyline
                    :points="polylinePoints"
                    fill="none"
                    stroke="#8B5CF6"
                    stroke-width="7"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    filter="url(#glow-pola)"
                    class="transition-all"
                />

                <!-- Line following dragging finger/mouse -->
                <template x-if="isDragging && lastDot && currentPos">
                    <line
                        :x1="lastDot.x"
                        :y1="lastDot.y"
                        :x2="currentPos.x"
                        :y2="currentPos.y"
                        stroke="#A78BFA"
                        stroke-width="5"
                        stroke-linecap="round"
                        stroke-dasharray="6,4"
                        opacity="0.9"
                    />
                </template>

                <!-- 9 Touch Dots -->
                <template x-for="dot in dots" :key="dot.id">
                    <g :transform="`translate(${dot.x}, ${dot.y})`" class="cursor-pointer" @click="clickDot(dot.id)">
                        <!-- Outer Touch Area Ring -->
                        <circle
                            r="34"
                            fill="transparent"
                        />

                        <!-- Outer Visual Ring -->
                        <circle
                            r="24"
                            :fill="sequence.includes(dot.id) ? 'rgba(139, 92, 246, 0.35)' : (isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)')"
                            :stroke="sequence.includes(dot.id) ? '#8B5CF6' : (isDark ? 'rgba(255, 255, 255, 0.3)' : 'rgba(0, 0, 0, 0.25)')"
                            stroke-width="2.5"
                        />

                        <!-- Inner Core Dot -->
                        <circle
                            r="10"
                            :fill="sequence.includes(dot.id) ? '#FFFFFF' : (isDark ? 'rgba(255, 255, 255, 0.7)' : 'rgba(0, 0, 0, 0.6)')"
                            :stroke="sequence.includes(dot.id) ? '#7C3AED' : 'none'"
                            stroke-width="2"
                        />

                        <!-- Step Order Badge if visited -->
                        <template x-if="sequence.includes(dot.id)">
                            <g transform="translate(14, -14)">
                                <circle r="11" fill="#EA580C" stroke="#FFFFFF" stroke-width="1.5" />
                                <text
                                    text-anchor="middle"
                                    dy="4"
                                    fill="#FFFFFF"
                                    font-size="11"
                                    font-weight="bold"
                                    x-text="sequence.indexOf(dot.id) + 1"
                                ></text>
                            </g>
                        </template>

                        <!-- Node Number (subtle background) -->
                        <template x-if="!sequence.includes(dot.id)">
                            <text
                                text-anchor="middle"
                                dy="4"
                                :fill="isDark ? 'rgba(255, 255, 255, 0.5)' : 'rgba(0, 0, 0, 0.5)'"
                                font-size="11"
                                font-weight="bold"
                                x-text="dot.id"
                            ></text>
                        </template>
                    </g>
                </template>
            </svg>
        </div>

        <!-- Terjemahan Langsung di Bawah / Samping Pola -->
        <div class="flex-1 w-full space-y-2">
            <!-- 1. Urutan Angka -->
            <div class="p-2.5 rounded-xl {{ $dark ? 'bg-white/[0.03] border border-white/5' : 'bg-slate-50 dark:bg-white/[0.03] border border-slate-200 dark:border-white/5' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider {{ $dark ? 'text-ink-400' : 'text-slate-500 dark:text-ink-400' }}">Urutan Titik (1-9)</span>
                    <span class="text-[10px] font-mono font-bold text-up-amber tabular-nums" x-text="sequence.length + ' titik terpilih'"></span>
                </div>
                <p class="text-xs font-mono font-bold text-up-primary mt-1 tracking-wider" x-text="urutanString"></p>
            </div>

            <!-- 2. Terjemahan Posisi Grid -->
            <div class="p-2.5 rounded-xl {{ $dark ? 'bg-white/[0.03] border border-white/5' : 'bg-slate-50 dark:bg-white/[0.03] border border-slate-200 dark:border-white/5' }}">
                <span class="text-[10px] font-bold uppercase tracking-wider {{ $dark ? 'text-ink-400' : 'text-slate-500 dark:text-ink-400' }}">Alur Posisi</span>
                <p class="text-[11px] font-medium {{ $dark ? 'text-ink-200' : 'text-slate-700 dark:text-ink-200' }} mt-1 leading-relaxed" x-text="alurPosisi"></p>
            </div>

            <!-- 3. Terjemahan Arah Gerak -->
            <div class="p-2.5 rounded-xl {{ $dark ? 'bg-white/[0.03] border border-white/5' : 'bg-slate-50 dark:bg-white/[0.03] border border-slate-200 dark:border-white/5' }}">
                <span class="text-[10px] font-bold uppercase tracking-wider {{ $dark ? 'text-ink-400' : 'text-slate-500 dark:text-ink-400' }}">Arah Gerak Pola</span>
                <p class="text-[11px] font-semibold text-up-mint mt-1 leading-relaxed" x-text="alurArah"></p>
            </div>
        </div>
    </div>
</div>
