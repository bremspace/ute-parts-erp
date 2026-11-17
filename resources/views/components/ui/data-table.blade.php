@props([
    'headers' => [],       // Array of header names or ['label' => '...', 'align' => 'left|center|right', 'class' => '...']
    'empty' => false,      // Boolean or count
    'emptyMessage' => 'Tidak ada data ditemukan.',
    'emptyIcon' => null,
    'stickyHeader' => true,
    'target' => null,      // wire:target for loading state
])

<div class="w-full flex flex-col rounded-2xl border border-ink-200/80 dark:border-white/[0.08] bg-white dark:bg-ink-900/90 shadow-[0_1px_3px_0_rgba(0,0,0,0.03)] dark:shadow-[0_4px_16px_-2px_rgba(0,0,0,0.3)] overflow-hidden">
    {{-- Optional Toolbar Slot --}}
    @isset($toolbar)
        <div class="p-3 sm:p-4 border-b border-ink-100 dark:border-white/[0.06] bg-ink-50/40 dark:bg-white/[0.01]">
            {{ $toolbar }}
        </div>
    @endisset

    {{-- Mobile View Slot (when provided, hides desktop table on small screens) --}}
    @isset($mobile)
        <div class="block md:hidden divide-y divide-ink-100 dark:divide-white/[0.06]">
            @if($target)
                <div wire:loading.delay wire:target="{{ $target }}" class="p-4 space-y-3">
                    <x-ui.skeleton variant="card" />
                    <x-ui.skeleton variant="card" />
                </div>
                <div wire:loading.remove wire:target="{{ $target }}">
                    {{ $mobile }}
                </div>
            @else
                {{ $mobile }}
            @endif
        </div>
    @endisset

    {{-- Desktop / Standard Responsive Table Container --}}
    <div class="{{ isset($mobile) ? 'hidden md:block' : 'block' }} w-full overflow-x-auto overflow-y-visible">
        <table class="w-full text-left text-xs sm:text-sm border-collapse min-w-full">
            @if(!empty($headers))
                <thead class="{{ $stickyHeader ? 'sticky top-0 z-10' : '' }} bg-ink-50/90 dark:bg-ink-950/90 backdrop-blur-xs text-ink-600 dark:text-ink-400 font-semibold border-b border-ink-200/80 dark:border-white/[0.08] select-none text-[11px] uppercase tracking-wider">
                    <tr>
                        @foreach($headers as $h)
                            @php
                                $label = is_array($h) ? ($h['label'] ?? '') : $h;
                                $align = is_array($h) ? ($h['align'] ?? 'left') : 'left';
                                $alignClass = match($align) {
                                    'right' => 'text-right',
                                    'center' => 'text-center',
                                    default => 'text-left',
                                };
                                $customClass = is_array($h) ? ($h['class'] ?? '') : '';
                            @endphp
                            <th scope="col" class="px-4 py-3 {{ $alignClass }} {{ $customClass }}">
                                {{ $label }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
            @endif

            <tbody class="divide-y divide-ink-100 dark:divide-white/[0.06] text-ink-800 dark:text-ink-200">
                @if($target)
                    <tr wire:loading.delay.class.remove="hidden" wire:target="{{ $target }}" class="hidden">
                        <td colspan="{{ count($headers) ?: 10 }}" class="p-6">
                            <div class="space-y-3">
                                <x-ui.skeleton variant="table-row" />
                                <x-ui.skeleton variant="table-row" />
                                <x-ui.skeleton variant="table-row" />
                            </div>
                        </td>
                    </tr>
                @endif

                @if($empty)
                    <tr>
                        <td colspan="{{ count($headers) ?: 10 }}" class="py-12 text-center text-ink-400">
                            <x-ui.empty-state
                                :title="$emptyMessage"
                                :description="null"
                                :icon="$emptyIcon"
                            />
                        </td>
                    </tr>
                @else
                    {{ $slot }}
                @endif
            </tbody>
        </table>
    </div>

    {{-- Optional Pagination / Footer Slot --}}
    @isset($pagination)
        <div class="p-3 sm:px-4 sm:py-3 border-t border-ink-100 dark:border-white/[0.06] bg-ink-50/30 dark:bg-white/[0.01]">
            {{ $pagination }}
        </div>
    @endisset
</div>
