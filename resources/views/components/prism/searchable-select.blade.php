@props([
    'model',
    'options' => [],
    'placeholder' => 'Pilih...',
    'searchPlaceholder' => 'Cari...',
    'clearable' => true,
    'class' => '',
])

@php
    // Normalisasi opsi ke array [{value, label, display_label?, group?, level?, is_parent?, is_child?}]
    $normalizedOptions = [];
    $rawOptions = is_iterable($options) ? $options : [];

    $isAssociative = false;
    if (is_array($rawOptions)) {
        $keys = array_keys($rawOptions);
        $isAssociative = array_keys($keys) !== $keys;
    }

    foreach ($rawOptions as $key => $item) {
        if (is_object($item)) {
            $val = $item->value ?? $item->id ?? $key;
            $lbl = $item->label ?? $item->nama ?? (string)$val;
            $dispLbl = $item->display_label ?? $lbl;
            $grp = $item->group ?? null;
            $lvl = $item->level ?? 0;
            $isParent = !empty($item->is_parent);
            $isChild = !empty($item->is_child) || $lvl > 0;

            $normalizedOptions[] = [
                'value' => (string) $val,
                'label' => (string) $lbl,
                'display_label' => (string) $dispLbl,
                'group' => $grp ? (string) $grp : null,
                'level' => (int) $lvl,
                'is_parent' => (bool) $isParent,
                'is_child' => (bool) $isChild,
            ];
        } elseif (is_array($item)) {
            if (isset($item['value']) || isset($item['label']) || isset($item['id']) || isset($item['nama'])) {
                $val = $item['value'] ?? $item['id'] ?? $key;
                $lbl = $item['label'] ?? $item['nama'] ?? (string)$val;
                $dispLbl = $item['display_label'] ?? $lbl;
                $grp = $item['group'] ?? null;
                $lvl = $item['level'] ?? 0;
                $isParent = !empty($item['is_parent']);
                $isChild = !empty($item['is_child']) || $lvl > 0;

                $normalizedOptions[] = [
                    'value' => (string) $val,
                    'label' => (string) $lbl,
                    'display_label' => (string) $dispLbl,
                    'group' => $grp ? (string) $grp : null,
                    'level' => (int) $lvl,
                    'is_parent' => (bool) $isParent,
                    'is_child' => (bool) $isChild,
                ];
            } else {
                $val = $key;
                $lbl = (string) $item;
                $normalizedOptions[] = [
                    'value' => (string) $val,
                    'label' => $lbl,
                    'display_label' => $lbl,
                    'group' => null,
                    'level' => 0,
                    'is_parent' => false,
                    'is_child' => false,
                ];
            }
        } elseif ($isAssociative) {
            $normalizedOptions[] = [
                'value' => (string) $key,
                'label' => (string) $item,
                'display_label' => (string) $item,
                'group' => null,
                'level' => 0,
                'is_parent' => false,
                'is_child' => false,
            ];
        } else {
            $normalizedOptions[] = [
                'value' => (string) $item,
                'label' => (string) $item,
                'display_label' => (string) $item,
                'group' => null,
                'level' => 0,
                'is_parent' => false,
                'is_child' => false,
            ];
        }
    }
@endphp

<div
    x-data="{
        open: false,
        alignRight: false,
        search: '',
        options: @js($normalizedOptions),
        selected: $wire.entangle('{{ $model }}').live,
        clearable: @js($clearable),
        init() {
            this.$watch('open', value => {
                if (value) {
                    this.search = '';
                    this.$nextTick(() => {
                        const rect = this.$el.getBoundingClientRect();
                        const spaceRight = window.innerWidth - rect.left;
                        this.alignRight = (spaceRight < 340);
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
            if (!this.selectedItem) {
                return '{{ addslashes($placeholder) }}';
            }
            return this.selectedItem.display_label || this.selectedItem.label;
        },
        get filteredOptions() {
            if (!this.search || this.search.trim() === '') {
                return this.options;
            }
            const q = this.search.toLowerCase().trim();
            return this.options.filter(o => {
                const matchLabel = o.label && o.label.toLowerCase().includes(q);
                const matchGroup = o.group && o.group.toLowerCase().includes(q);
                const matchDisplay = o.display_label && o.display_label.toLowerCase().includes(q);
                return matchLabel || matchGroup || matchDisplay;
            });
        },
        get groupedFilteredOptions() {
            const hasGroups = this.options.some(o => !!o.group);
            if (!hasGroups) {
                return [{ group: null, items: this.filteredOptions }];
            }

            const map = new Map();
            for (const opt of this.filteredOptions) {
                const grp = opt.group || '';
                if (!map.has(grp)) {
                    map.set(grp, []);
                }
                map.get(grp).push(opt);
            }

            const result = [];
            map.forEach((items, groupName) => {
                result.push({
                    group: groupName || null,
                    items: items
                });
            });
            return result;
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
    :class="{ 'z-50': open, 'z-10': !open }"
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
            :title="displayLabel"
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
        class="absolute z-50 mt-1 min-w-full sm:min-w-[280px] w-max max-w-[min(480px,94vw)] rounded-2xl bg-white/95 dark:bg-ink-950/95 backdrop-blur-md border border-ink-200 dark:border-white/10 shadow-2xl p-2 space-y-1.5 font-normal normal-case text-left"
        :class="alignRight ? 'right-0' : 'left-0'"
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
        <div x-ref="listContainer" class="max-h-64 overflow-y-auto space-y-0.5 overscroll-contain pr-0.5">
            <template x-for="grp in groupedFilteredOptions" :key="grp.group || '__all__'">
                <div>
                    <!-- Group Header (Optgroup) -->
                    <template x-if="grp.group">
                        <div class="sticky top-0 z-10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-ink-500 dark:text-ink-400 bg-slate-100/95 dark:bg-ink-900/95 backdrop-blur-sm border-y border-black/5 dark:border-white/5 my-1 flex items-center justify-between">
                            <span class="break-words line-clamp-1" x-text="grp.group"></span>
                            <span class="text-[9px] font-mono text-ink-400/80 shrink-0 ml-1.5" x-text="grp.items.length"></span>
                        </div>
                    </template>

                    <!-- Items in group -->
                    <template x-for="opt in grp.items" :key="opt.value">
                        <div
                            @click="select(opt.value)"
                            :data-selected="String(opt.value) === String(selected)"
                            class="px-2.5 py-1.5 rounded-lg flex items-center justify-between text-xs cursor-pointer transition-colors"
                            :class="[
                                String(opt.value) === String(selected)
                                    ? 'bg-up-primary/15 text-up-primary dark:text-white font-bold'
                                    : (opt.is_parent ? 'font-semibold text-ink-900 dark:text-white hover:bg-black/5 dark:hover:bg-white/10 bg-black/[0.02] dark:bg-white/[0.02]' : 'text-ink-700 dark:text-ink-200 hover:bg-black/5 dark:hover:bg-white/10'),
                                opt.level === 1 ? 'pl-5' : '',
                                opt.level >= 2 ? 'pl-8' : ''
                            ]"
                        >
                            <div class="flex items-start gap-1.5 flex-1 min-w-0 mr-2 py-0.5">
                                <template x-if="opt.level === 1">
                                    <span class="text-ink-400/80 select-none text-[11px] shrink-0 mt-0.5">↳</span>
                                </template>
                                <template x-if="opt.level >= 2">
                                    <span class="text-ink-400/80 select-none text-[11px] shrink-0 mt-0.5">↳↳</span>
                                </template>
                                <div class="flex-1 min-w-0">
                                    <span
                                        class="break-words line-clamp-2 leading-tight block"
                                        x-text="opt.label"
                                        :title="opt.display_label || opt.label"
                                    ></span>
                                </div>
                            </div>
                            <span x-show="String(opt.value) === String(selected)" class="text-up-mint font-bold text-xs shrink-0 self-center">✓</span>
                        </div>
                    </template>
                </div>
            </template>

            <!-- Empty Search State -->
            <div x-show="filteredOptions.length === 0" class="py-4 text-center text-xs text-ink-400">
                Tidak ada opsi yang cocok
            </div>
        </div>

        <!-- Result count footer -->
        <div class="pt-1 border-t border-black/5 dark:border-white/5 text-[10px] text-center text-ink-400 flex items-center justify-between px-1">
            <span x-text="filteredOptions.length + ' opsi tersedia'"></span>
            <span x-show="search.length > 0" class="text-up-primary cursor-pointer hover:underline" @click="search = ''">Reset cari</span>
        </div>
    </div>
</div>
