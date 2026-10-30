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
            { id: 1, x: 45,  y: 45,  label: 'Kiri Atas', row: 0, col: 0 },
            { id: 2, x: 135, y: 45,  label: 'Tengah Atas', row: 0, col: 1 },
            { id: 3, x: 225, y: 45,  label: 'Kanan Atas', row: 0, col: 2 },
            { id: 4, x: 45,  y: 135, label: 'Kiri Tengah', row: 1, col: 0 },
            { id: 5, x: 135, y: 135, label: 'Tengah', row: 1, col: 1 },
            { id: 6, x: 225, y: 135, label: 'Kanan Tengah', row: 1, col: 2 },
            { id: 7, x: 45,  y: 225, label: 'Kiri Bawah', row: 2, col: 0 },
            { id: 8, x: 135, y: 225, label: 'Tengah Bawah', row: 2, col: 1 },
            { id: 9, x: 225, y: 225, label: 'Kanan Bawah', row: 2, col: 2 },
        ],
        // Android LockPatternView: intermediate collinear dots automatically connect
        intermediates: {
            '1-3': 2, '3-1': 2,
            '4-6': 5, '6-4': 5,
            '7-9': 8, '9-7': 8,
            '1-7': 4, '7-1': 4,
            '2-8': 5, '8-2': 5,
            '3-9': 6, '9-3': 6,
            '1-9': 5, '9-1': 5,
            '3-7': 5, '7-3': 5
        },
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
                x: ((clientX - rect.left) / rect.width) * 270,
                y: ((clientY - rect.top) / rect.height) * 270,
            };
        },
        startDrag(e) {
            try {
                if (e.target && e.target.setPointerCapture && e.pointerId !== undefined) {
                    e.target.setPointerCapture(e.pointerId);
                }
            } catch (_) {}

            this.isDragging = true;
            this.sequence = [];
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
        endDrag(e) {
            if (!this.isDragging) return;
            this.isDragging = false;
            this.currentPos = null;
            this.syncToWire();
        },
        checkHit(pos) {
            const hitRadius = 32;
            for (const dot of this.dots) {
                const dist = Math.hypot(pos.x - dot.x, pos.y - dot.y);
                if (dist <= hitRadius && !this.sequence.includes(dot.id)) {
                    // Check collinear intermediate dot like Android LockPatternView
                    if (this.sequence.length > 0) {
                        const lastId = this.sequence[this.sequence.length - 1];
                        const pair = `${lastId}-${dot.id}`;
                        const intermediate = this.intermediates[pair];
                        if (intermediate && !this.sequence.includes(intermediate)) {
                            this.sequence.push(intermediate);
                        }
                    }
                    this.sequence.push(dot.id);
                    if (navigator.vibrate) try { navigator.vibrate(12); } catch (_) {}
                    break;
                }
            }
        },
        clickDot(id) {
            if (!this.sequence.includes(id)) {
                if (this.sequence.length > 0) {
                    const lastId = this.sequence[this.sequence.length - 1];
                    const pair = `${lastId}-${id}`;
                    const intermediate = this.intermediates[pair];
                    if (intermediate && !this.sequence.includes(intermediate)) {
                        this.sequence.push(intermediate);
                    }
                }
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
        }
    }"
    class="w-full select-none"
>
    <!-- Android Lock Screen Authentic Pattern Container -->
    <div class="flex flex-col items-center justify-center space-y-3.5">
        <!-- Status & Instruction Header -->
        <div class="w-full flex items-center justify-between text-xs px-1">
            <div class="flex items-center gap-2">
                <div class="w-2 h-2 rounded-full" :class="sequence.length > 0 ? 'bg-up-mint animate-pulse' : 'bg-slate-400'"></div>
                <span class="font-medium text-ink-600 dark:text-ink-300" x-text="sequence.length === 0 ? 'Tarik pola kunci layar (Android)' : 'Pola tersambung: ' + sequence.length + ' titik'"></span>
            </div>
            <button
                type="button"
                @click="reset()"
                x-show="sequence.length > 0"
                x-cloak
                class="px-2.5 py-1 rounded-xl text-[11px] font-bold text-up-red hover:bg-up-red/10 border border-up-red/20 cursor-pointer transition-colors"
            >
                Reset Pola
            </button>
        </div>

        <!-- Android Lock Screen Pad Frame -->
        <div class="relative w-full max-w-[290px] aspect-square rounded-3xl p-3.5 flex items-center justify-center shadow-lg transition-all duration-200 border"
             :class="isDark ? 'bg-[#0B1120] border-slate-800 shadow-[0_12px_36px_rgba(0,0,0,0.6)]' : 'bg-slate-900 border-slate-800 text-white shadow-xl'">

            <!-- Ambient Glow Effect -->
            <div class="absolute inset-0 rounded-3xl bg-gradient-to-br from-indigo-500/10 via-transparent to-purple-500/10 pointer-events-none"></div>

            <!-- SVG Pattern Canvas -->
            <svg
                x-ref="svgCanvas"
                viewBox="0 0 270 270"
                class="w-full h-full cursor-crosshair touch-none select-none relative z-10"
                style="touch-action: none;"
                @pointerdown="startDrag($event)"
                @pointermove="onDrag($event)"
                @pointerup="endDrag($event)"
                @pointercancel="endDrag($event)"
            >
                <defs>
                    <!-- Android Glowing Line Filter -->
                    <filter id="android-glow" x="-20%" y="-20%" width="140%" height="140%">
                        <feDropShadow dx="0" dy="0" stdDeviation="3.5" flood-color="#818CF8" flood-opacity="0.85"/>
                    </filter>
                    <radialGradient id="dot-active-grad" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stop-color="#C7D2FE" />
                        <stop offset="60%" stop-color="#818CF8" />
                        <stop offset="100%" stop-color="#6366F1" />
                    </radialGradient>
                </defs>

                <!-- Line connecting visited dots (Android style smooth polyline) -->
                <polyline
                    :points="polylinePoints"
                    fill="none"
                    stroke="#818CF8"
                    stroke-width="5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    filter="url(#android-glow)"
                    class="transition-all"
                />

                <!-- Live Drag line following finger -->
                <template x-if="isDragging && lastDot && currentPos">
                    <line
                        :x1="lastDot.x"
                        :y1="lastDot.y"
                        :x2="currentPos.x"
                        :y2="currentPos.y"
                        stroke="#A5B4FC"
                        stroke-width="4"
                        stroke-linecap="round"
                        stroke-dasharray="4,3"
                        opacity="0.9"
                    />
                </template>

                <!-- 9 Android Pattern Dots -->
                <template x-for="dot in dots" :key="dot.id">
                    <g :transform="`translate(${dot.x}, ${dot.y})`" class="cursor-pointer" @click="clickDot(dot.id)">
                        <!-- Outer Touch Area (Transparent Hit Target) -->
                        <circle r="36" fill="transparent" />

                        <!-- Active Outer Halo Ring (Android ripple style) -->
                        <circle
                            r="22"
                            :fill="sequence.includes(dot.id) ? 'rgba(129, 140, 248, 0.22)' : 'transparent'"
                            :stroke="sequence.includes(dot.id) ? 'rgba(129, 140, 248, 0.7)' : 'transparent'"
                            stroke-width="2"
                            class="transition-all duration-150"
                        />

                        <!-- Default Outer Ring Guide (Subtle Android Ring) -->
                        <circle
                            r="18"
                            fill="none"
                            :stroke="sequence.includes(dot.id) ? 'transparent' : 'rgba(255, 255, 255, 0.12)'"
                            stroke-width="1.5"
                        />

                        <!-- Center Dot (The iconic Android dot) -->
                        <circle
                            :r="sequence.includes(dot.id) ? 8 : 5.5"
                            :fill="sequence.includes(dot.id) ? 'url(#dot-active-grad)' : 'rgba(255, 255, 255, 0.75)'"
                            class="transition-all duration-150"
                        />

                        <!-- Inner highlight for active dot -->
                        <template x-if="sequence.includes(dot.id)">
                            <circle r="3" fill="#FFFFFF" />
                        </template>
                    </g>
                </template>
            </svg>
        </div>

        <!-- Pattern Sequence Result Footer -->
        <div class="w-full p-2.5 rounded-2xl bg-ink-50 dark:bg-white/[0.03] border border-ink-100 dark:border-white/10 flex items-center justify-between text-xs">
            <span class="text-[11px] font-semibold text-ink-500 dark:text-ink-400">Urutan Terdaftar:</span>
            <span class="font-mono font-bold text-xs text-up-primary tracking-wide tabular-nums" x-text="urutanString"></span>
        </div>
    </div>
</div>
