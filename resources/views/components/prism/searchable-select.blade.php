@props([
    'model',
    'options' => [],
    'placeholder' => 'Pilih...',
    'searchPlaceholder' => 'Cari...',
    'clearable' => true,
    'class' => '',
])

@php
    // Normalisasi opsi ke array [{value, label, group?}] di sisi PHP
    $normalizedOptions = [];

    // Opsi bisa berupa Collection, array objek, array asosiatif, atau flat array
    $rawOptions = is_iterable($options) ? $options : [];

    // Deteksi tipe iterasi
    $isAssociative = false;
    if (is_array($rawOptions)) {
        $keys = array_keys($rawOptions);
        $isAssociative = array_keys($keys) !== $keys;
    }

    foreach ($rawOptions as $key => $item) {
        if (is_object($item)) {
            $val = $item->value ?? $item->id ?? $key;
            $lbl = $item->label ?? $item->nama ?? (string)$val;
            $grp = $item->group ?? null;
            $normalizedOptions[] = [
                'value' => (string) $val,
                'label' => (string) $lbl,
                'group' => $grp ? (string) $grp : null,
            ];
        } elseif (is_array($item)) {
            if (isset($item['value']) || isset($item['label']) || isset($item['id']) || isset($item['nama'])) {
                $val = $item['value'] ?? $item['id'] ?? $key;
                $lbl = $item['label'] ?? $item['nama'] ?? (string)$val;
                $grp = $item['group'] ?? null;
                $normalizedOptions[] = [
                    'value' => (string) $val,
                    'label' => (string) $lbl,
                    'group' => $grp ? (string) $grp : null,
                ];
            } else {
                // array [value => label] atau multidimensional optgroup
                $val = $key;
                $lbl = (string) $item;
                $normalizedOptions[] = [
                    'value' => (string) $val,
                    'label' => $lbl,
                    'group' => null,
                ];
            }
        } elseif ($isAssociative) {
            $normalizedOptions[] = [
                'value' => (string) $key,
                'label' => (string) $item,
                'group' => null,
            ];
        } else {
            // Flat scalar array: ['iPhone 11', 'iPhone 12'] -> value & label sama
            $normalizedOptions[] = [
                'value' => (string) $item,
                'label' => (string) $item,
                'group' => null,
            ];
        }
    }
@endphp

<div
    x-data="{
        open: false,
        search: '',
        options: @js($normalizedOptions),
        selected: $wire.entangle('{{ $model }}').live,
        clearable: @js($clearable),
        init() {
            // Watch changes to close search or reset search when closed
            this.$watch('open', value => {
                if (value) {
                    this.search = '';
                    this.$nextTick(() => {
                        this.$refs.searchInput?.focus();
                        this.scrollToSelected();
                    });
                }
            });
        },
        get selectedItem() {
            if (this.selected === null || this.selected === undefined || this.selected === '') {
                return null;
            }
            return this.options.find(o => String(o.value) === String(this.selected)) || null;
        },
        get displayLabel() {
            return this.selectedItem ? this.selectedItem.label : '{{ $placeholder }}';
        },
        get filteredOptions() {
            let list = this.options;
            if (this.search && this.search.trim() !== '') {
                const q = this.search.toLowerCase().trim();
                list = list.filter(o => {
                    const matchLabel = o.label && o.label.toLowerCase().includes(q);
                    const matchGroup = o.group && o.group.toLowerCase().includes(q);
                    return matchLabel || matchGroup;
                });
            }
            return list.slice(0, 100);
        },
        get groupedFilteredOptions() {
            const hasGroups = this.options.some(o => !!o.group);
            if (!hasGroups) {
                return { '': this.filteredOptions };
            }
            const groups = {};
            this.filteredOptions.forEach(opt => {
                const grp = opt.group || '';
                if (!groups[grp]) groups[grp] = [];
                groups[grp].push(opt);
            });
            return groups;
        },
        select(val) {
            this.selected = val;
            this.open = false;
        },
        clear() {
            this.selected = '';
            this.open = false;
        },
        scrollToSelected() {
            const activeEl = this.$refs.listContainer?.querySelector('[data-selected=true]');
            if (activeEl) {
                activeEl.scrollIntoView({ block: 'nearest' });
            }
        }
    }"
    class="relative w-full {{ $class }}"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
>
    <!-- Trigger Button -->
    <button
        type="button"
        @click="open = !open"
        class="w-full min-w-0 max-w-full text-left flex items-center justify-between gap-1.5 px-3 py-2 rounded-xl glass-input text-xs font-medium text-ink-900 dark:text-ink-100 bg-white/80 dark:bg-ink-900/60 border border-black/10 dark:border-white/10 hover:border-black/20 dark:hover:border-white/20 transition-[border-color,box-shadow] min-h-[38px] sm:min-h-[40px] cursor-pointer outline-none focus:border-up-primary focus:ring-2 focus:ring-up-primary/15"
        :aria-expanded="open"
    >
        <span
            class="truncate block flex-1"
            :class="selectedItem ? 'text-ink-900 dark:text-white font-semibold' : 'text-ink-400 dark:text-ink-400 font-normal'"
            x-text="displayLabel"
        ></span>

        <div class="flex items-center gap-1 shrink-0 text-ink-400">
            <!-- Clear Button -->
            <template x-if="clearable && selectedItem">
                <span
                    role="button"
                    tabindex="0"
                    @click.stop="clear()"
                    class="p-0.5 rounded hover:text-up-red hover:bg-up-red/10 cursor-pointer transition-colors"
                    title="Kosongkan Pilihan"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </span>
            </template>

            <!-- Dropdown Caret -->
            <svg
                class="w-3.5 h-3.5 transition-transform duration-150"
                :class="{ 'rotate-180 text-up-primary': open }"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </div>
    </button>

    <!-- Dropdown Panel -->
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
        class="absolute z-50 left-0 right-0 mt-1 min-w-[200px] max-w-[95vw] rounded-2xl bg-white/95 dark:bg-ink-950/95 backdrop-blur-md border border-ink-200 dark:border-white/10 shadow-2xl p-2 space-y-1.5 font-normal normal-case text-left"
        style="display: none;"
    >
        <!-- Search Input Header -->
        <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-ink-400">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </span>
            <input
                x-ref="searchInput"
                type="text"
                x-model="search"
                placeholder="{{ $searchPlaceholder }}"
                class="w-full pl-8 pr-7 py-1.5 rounded-lg glass-input text-xs bg-white/80 dark:bg-ink-900/80 text-ink-900 dark:text-white border border-black/10 dark:border-white/10 focus:border-up-primary outline-none transition-colors"
                @keydown.enter.prevent="if (filteredOptions.length > 0) select(filteredOptions[0].value)"
            />
            <button
                x-show="search.length > 0"
                type="button"
                @click="search = ''; $refs.searchInput.focus()"
                class="absolute inset-y-0 right-0 pr-2 flex items-center text-ink-400 hover:text-ink-600 dark:hover:text-ink-200 text-xs cursor-pointer"
            >✕</button>
        </div>

        <!-- Options List -->
        <div x-ref="listContainer" class="max-h-56 overflow-y-auto space-y-0.5 overscroll-contain pr-0.5">
            <template x-for="(opts, grp) in groupedFilteredOptions" :key="grp">
                <div>
                    <!-- Group Header if group is present -->
                    <template x-if="grp !== ''">
                        <div class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-ink-400 dark:text-ink-500 bg-black/5 dark:bg-white/5 rounded-md my-0.5" x-text="grp"></div>
                    </template>

                    <!-- Items in group -->
                    <template x-for="opt in opts" :key="opt.value">
                        <div
                            @click="select(opt.value)"
                            :data-selected="String(opt.value) === String(selected)"
                            class="px-2.5 py-1.5 rounded-lg flex items-center justify-between text-xs cursor-pointer transition-colors"
                            :class="String(opt.value) === String(selected)
                                ? 'bg-up-primary/15 text-up-primary dark:text-white font-bold'
                                : 'text-ink-700 dark:text-ink-200 hover:bg-black/5 dark:hover:bg-white/10'"
                        >
                            <span class="truncate mr-2" x-text="opt.label"></span>
                            <span x-show="String(opt.value) === String(selected)" class="text-up-mint font-bold text-xs shrink-0">✓</span>
                        </div>
                    </template>
                </div>
            </template>

            <!-- Empty Search State -->
            <div x-show="filteredOptions.length === 0" class="py-4 text-center text-xs text-ink-400">
                Tidak ada opsi yang cocok
            </div>
        </div>

        <!-- Pagination / Result Count footer if truncated -->
        <div x-show="options.length > 100 && filteredOptions.length >= 100" class="pt-1 border-t border-black/5 dark:border-white/5 text-[10px] text-center text-ink-400">
            Menampilkan 100 opsi teratas. Ketik kata kunci untuk mempersempit.
        </div>
    </div>
</div>
