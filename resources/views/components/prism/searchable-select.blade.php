@props([
    'model',
    'options' => [],
    'placeholder' => 'Pilih...',
    'searchPlaceholder' => 'Cari...',
    'clearable' => true,
    'class' => '',
    'defaultCollapsed' => null, // null (auto), true, false
])

@php
    // Normalisasi opsi ke array [{value, label, display_label?, group?, level?, is_parent?, is_child?, ancestors?, children_count?}]
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

    // Pass 2: Analisis hirarki bertingkat (parentStack, ancestors, is_parent auto-detect)
    $optCount = count($normalizedOptions);
    $parentStack = []; // [level => value]

    for ($i = 0; $i < $optCount; $i++) {
        $curLvl = $normalizedOptions[$i]['level'];
        $nextLvl = ($i + 1 < $optCount) ? $normalizedOptions[$i + 1]['level'] : 0;

        if ($nextLvl > $curLvl) {
            $normalizedOptions[$i]['is_parent'] = true;
        }

        // Simpan hanya parent stack di bawah level saat ini
        $parentStack = array_filter($parentStack, fn($l) => $l < $curLvl, ARRAY_FILTER_USE_KEY);
        $ancestors = array_values($parentStack);
        $normalizedOptions[$i]['ancestors'] = $ancestors;
        $normalizedOptions[$i]['parent_value'] = !empty($ancestors) ? end($ancestors) : null;

        if (!empty($normalizedOptions[$i]['is_parent'])) {
            $parentStack[$curLvl] = $normalizedOptions[$i]['value'];
        }
    }

    // Pass 3: Hitung jumlah sub-item (descendant count) untuk setiap parent
    for ($i = 0; $i < $optCount; $i++) {
        $curVal = $normalizedOptions[$i]['value'];
        $curLvl = $normalizedOptions[$i]['level'];
        $cCount = 0;
        if (!empty($normalizedOptions[$i]['is_parent'])) {
            for ($j = $i + 1; $j < $optCount; $j++) {
                if ($normalizedOptions[$j]['level'] <= $curLvl) {
                    break;
                }
                if (in_array($curVal, $normalizedOptions[$j]['ancestors'] ?? [], true)) {
                    $cCount++;
                }
            }
        }
        $normalizedOptions[$i]['children_count'] = $cCount;
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
        defaultCollapsedProp: @js($defaultCollapsed),
        collapsedParents: {},
        collapsedGroups: {},
        hasHierarchy: false,
        hasGroups: false,

        init() {
            this.hasHierarchy = this.options.some(o => o.level > 0 || o.is_parent);
            this.hasGroups = this.options.some(o => !!o.group);
            this.initCollapseState();

            this.$watch('open', value => {
                if (value) {
                    this.search = '';
                    this.ensureSelectionExpanded();
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

        initCollapseState() {
            const groupNames = [...new Set(this.options.map(o => o.group).filter(Boolean))];
            const cg = {};
            const cp = {};

            if (this.defaultCollapsedProp === true) {
                groupNames.forEach(g => cg[g] = true);
                this.options.filter(o => o.is_parent && o.children_count > 0).forEach(o => cp[o.value] = true);
            } else if (this.defaultCollapsedProp === false) {
                // Semua terbuka
            } else {
                // Default Cerdas: jika group lebih dari 3 (misal Brand HP), mulai collapse agar ringkas
                if (groupNames.length > 3) {
                    groupNames.forEach(g => cg[g] = true);
                }
                // Kategori hirarkis dimulai terbuka agar subkategori langsung terlihat
            }

            this.collapsedGroups = cg;
            this.collapsedParents = cp;
            this.ensureSelectionExpanded();
        },

        ensureSelectionExpanded() {
            if (!this.selectedItem) return;
            if (this.selectedItem.group && this.collapsedGroups[this.selectedItem.group]) {
                this.collapsedGroups[this.selectedItem.group] = false;
            }
            if (this.selectedItem.ancestors && this.selectedItem.ancestors.length > 0) {
                for (const a of this.selectedItem.ancestors) {
                    this.collapsedParents[a] = false;
                }
            }
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
            if (!this.hasGroups) {
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

        isGroupCollapsed(groupName) {
            if (!groupName) return false;
            if (this.search && this.search.trim() !== '') return false;
            return Boolean(this.collapsedGroups[groupName]);
        },

        toggleGroup(groupName) {
            if (!groupName) return;
            this.collapsedGroups[groupName] = !this.isGroupCollapsed(groupName);
        },

        isParentCollapsed(parentVal) {
            if (!parentVal) return false;
            if (this.search && this.search.trim() !== '') return false;
            return Boolean(this.collapsedParents[parentVal]);
        },

        toggleParent(parentVal) {
            if (!parentVal) return;
            this.collapsedParents[parentVal] = !this.isParentCollapsed(parentVal);
        },

        isItemVisible(opt) {
            if (this.search && this.search.trim() !== '') return true;
            if (opt.group && this.isGroupCollapsed(opt.group)) return false;
            if (opt.ancestors && opt.ancestors.length > 0) {
                for (const a of opt.ancestors) {
                    if (this.isParentCollapsed(a)) {
                        return false;
                    }
                }
            }
            return true;
        },

        expandAll() {
            this.collapsedGroups = {};
            this.collapsedParents = {};
        },

        collapseAll() {
            const cg = {};
            for (const opt of this.options) {
                if (opt.group) cg[opt.group] = true;
            }
            const cp = {};
            for (const opt of this.options) {
                if (opt.is_parent && opt.children_count > 0) cp[opt.value] = true;
            }
            this.collapsedGroups = cg;
            this.collapsedParents = cp;
            this.ensureSelectionExpanded();
        },

        get summaryText() {
            if (this.hasGroups) {
                const groupCount = new Set(this.options.map(o => o.group).filter(Boolean)).size;
                return groupCount + ' Kelompok / Brand';
            }
            if (this.hasHierarchy) {
                const rootCount = this.options.filter(o => o.level === 0).length;
                return rootCount + ' Kategori Utama';
            }
            return '';
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

        <!-- Toolbar: Expand All / Collapse All -->
        <div
            x-show="(hasHierarchy || hasGroups) && (!search || search.trim() === '')"
            class="flex items-center justify-between px-2 py-1 text-[10px] text-ink-500 dark:text-ink-400 bg-black/[0.02] dark:bg-white/[0.02] rounded-lg border border-black/5 dark:border-white/5 select-none"
        >
            <span class="font-medium" x-text="summaryText"></span>
            <div class="flex items-center gap-2 font-semibold">
                <button
                    type="button"
                    @click="expandAll()"
                    class="hover:text-up-primary text-ink-600 dark:text-ink-300 transition-colors cursor-pointer"
                >
                    Buka Semua
                </button>
                <span class="text-ink-300 dark:text-ink-600">·</span>
                <button
                    type="button"
                    @click="collapseAll()"
                    class="hover:text-up-primary text-ink-600 dark:text-ink-300 transition-colors cursor-pointer"
                >
                    Tutup Semua
                </button>
            </div>
        </div>

        <!-- Options List -->
        <div x-ref="listContainer" class="max-h-64 overflow-y-auto space-y-0.5 overscroll-contain pr-0.5">
            <template x-for="grp in groupedFilteredOptions" :key="grp.group || '__all__'">
                <div>
                    <!-- Group Header (Optgroup / Brand / Gudang) -->
                    <template x-if="grp.group">
                        <div
                            @click="toggleGroup(grp.group)"
                            class="sticky top-0 z-10 px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-wider text-ink-500 dark:text-ink-400 bg-slate-100/95 dark:bg-ink-900/95 backdrop-blur-sm border-y border-black/5 dark:border-white/5 my-1 flex items-center justify-between cursor-pointer hover:bg-slate-200/90 dark:hover:bg-ink-800/90 transition-colors rounded-lg select-none"
                            :title="isGroupCollapsed(grp.group) ? 'Klik untuk buka ' + grp.group : 'Klik untuk tutup ' + grp.group"
                        >
                            <div class="flex items-center gap-1.5 min-w-0">
                                <svg
                                    class="w-3.5 h-3.5 transition-transform duration-150 shrink-0"
                                    :class="{ '-rotate-90 text-ink-400': isGroupCollapsed(grp.group), 'rotate-0 text-up-primary': !isGroupCollapsed(grp.group) }"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                </svg>
                                <span class="break-words line-clamp-1" x-text="grp.group"></span>
                            </div>
                            <span class="text-[9px] font-mono text-ink-400/80 shrink-0 ml-1.5 px-1.5 py-0.2 rounded-full bg-black/5 dark:bg-white/10" x-text="grp.items.length"></span>
                        </div>
                    </template>

                    <!-- Items in group -->
                    <div x-show="!isGroupCollapsed(grp.group)">
                        <template x-for="opt in grp.items" :key="opt.value">
                            <div
                                x-show="isItemVisible(opt)"
                                @click="select(opt.value)"
                                :data-selected="String(opt.value) === String(selected)"
                                class="px-2.5 py-1.5 rounded-lg flex items-center justify-between text-xs cursor-pointer transition-colors group/item"
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
                                    <template x-if="opt.is_parent && opt.children_count > 0">
                                        <button
                                            type="button"
                                            @click.stop="toggleParent(opt.value)"
                                            class="p-0.5 -my-0.5 rounded hover:bg-black/10 dark:hover:bg-white/10 text-ink-400 hover:text-ink-900 dark:hover:text-white transition-transform cursor-pointer shrink-0 flex items-center justify-center"
                                            :title="isParentCollapsed(opt.value) ? 'Buka sub-kategori' : 'Tutup sub-kategori'"
                                        >
                                            <svg
                                                class="w-3.5 h-3.5 transition-transform duration-150"
                                                :class="{ '-rotate-90 text-ink-400': isParentCollapsed(opt.value), 'rotate-0 text-up-primary': !isParentCollapsed(opt.value) }"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                    </template>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <span
                                                class="break-words line-clamp-2 leading-tight block"
                                                x-text="opt.label"
                                                :title="opt.display_label || opt.label"
                                            ></span>
                                            <span
                                                x-show="opt.children_count > 0"
                                                class="text-[9px] font-mono text-ink-400/80 shrink-0 px-1 py-0.2 rounded bg-black/5 dark:bg-white/5"
                                                x-text="opt.children_count"
                                                :title="opt.children_count + ' sub-item'"
                                            ></span>
                                        </div>
                                        <template x-if="search && search.trim().length > 0 && opt.display_label && opt.display_label !== opt.label">
                                            <span class="text-[10px] text-ink-400 block truncate mt-0.5" x-text="opt.display_label"></span>
                                        </template>
                                    </div>
                                </div>
                                <span x-show="String(opt.value) === String(selected)" class="text-up-mint font-bold text-xs shrink-0 self-center">✓</span>
                            </div>
                        </template>
                    </div>
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