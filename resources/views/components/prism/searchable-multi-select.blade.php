@props([
    'model',
    'options' => [],
    'placeholder' => 'Cari & pilih...',
])

<div
    x-data="{
        open: false,
        search: '',
        options: @js($options),
        selected: $wire.entangle('{{ $model }}'),
        get filteredOptions() {
            if (!this.search.trim()) return this.options.slice(0, 50);
            const q = this.search.toLowerCase();
            return this.options.filter(o => o.label.toLowerCase().includes(q)).slice(0, 50);
        },
        get selectedLabels() {
            if (!Array.isArray(this.selected)) return [];
            return this.options.filter(o => this.selected.map(Number).includes(Number(o.id)));
        },
        toggle(id) {
            id = Number(id);
            if (!Array.isArray(this.selected)) this.selected = [];
            const idx = this.selected.map(Number).indexOf(id);
            if (idx > -1) {
                this.selected.splice(idx, 1);
            } else {
                this.selected.push(id);
            }
        },
        remove(id) {
            id = Number(id);
            if (!Array.isArray(this.selected)) return;
            const idx = this.selected.map(Number).indexOf(id);
            if (idx > -1) this.selected.splice(idx, 1);
        },
        isSelected(id) {
            if (!Array.isArray(this.selected)) return false;
            return this.selected.map(Number).includes(Number(id));
        }
    }"
    class="relative w-full"
    @click.outside="open = false"
>
    <!-- Container displaying badges + input -->
    <div
        class="min-h-[42px] px-3 py-1.5 rounded-xl glass-input text-xs flex flex-wrap items-center gap-1.5 cursor-pointer border border-white/10 hover:border-white/20 transition-colors"
        @click="open = true; $nextTick(() => $refs.searchInput?.focus())"
    >
        <template x-for="item in selectedLabels" :key="item.id">
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-up-primary/20 text-up-primary border border-up-primary/30 text-[11px] font-medium">
                <span x-text="item.label"></span>
                <button
                    type="button"
                    @click.stop="remove(item.id)"
                    class="text-up-primary/70 hover:text-up-primary hover:font-bold ml-0.5 cursor-pointer"
                >✕</button>
            </span>
        </template>

        <span x-show="selectedLabels.length === 0 && !open" class="text-ink-500 py-1" x-text="'{{ $placeholder }}'"></span>

        <input
            x-ref="searchInput"
            x-show="open"
            type="text"
            x-model="search"
            placeholder="{{ $placeholder }}"
            class="flex-1 min-w-[120px] bg-transparent outline-none text-white text-xs py-1"
            @keydown.escape="open = false"
        />

        <div class="ml-auto text-ink-400 pl-1 shrink-0">
            <svg class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </div>
    </div>

    <!-- Dropdown Panel -->
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute z-50 left-0 right-0 mt-1 max-h-56 overflow-y-auto rounded-xl bg-ink-950/95 backdrop-blur-md border border-white/10 shadow-2xl p-1.5 space-y-0.5"
        style="display: none;"
    >
        <template x-for="opt in filteredOptions" :key="opt.id">
            <div
                @click="toggle(opt.id)"
                class="px-2.5 py-1.5 rounded-lg flex items-center justify-between text-xs cursor-pointer hover:bg-white/10 transition-colors"
                :class="isSelected(opt.id) ? 'bg-up-primary/25 text-white font-semibold' : 'text-ink-200'"
            >
                <span x-text="opt.label"></span>
                <span x-show="isSelected(opt.id)" class="text-up-mint font-bold text-xs">✓</span>
            </div>
        </template>
        <div x-show="filteredOptions.length === 0" class="py-3 text-center text-[11px] text-ink-500">
            Tidak ditemukan item yang cocok
        </div>
    </div>
</div>
