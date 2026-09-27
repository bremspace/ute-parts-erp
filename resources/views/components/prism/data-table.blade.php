@props([
    'headers' => [],
])

<div class="w-full overflow-hidden rounded-2xl glass-panel min-h-24">
    @isset($mobileCards)
        <!-- Mode Desktop: Wide Table -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-sm text-ink-100 border-collapse min-w-[480px]">
                <thead class="bg-black/5 dark:bg-white/5 border-b border-black/10 dark:border-white/10 text-xs uppercase tracking-wider font-semibold text-ink-400">
                    <tr>
                        @foreach($headers as $header)
                            <th class="py-3.5 px-4 font-semibold">{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/5 dark:divide-white/5">
                    {{ $slot }}
                </tbody>
            </table>
        </div>

        <!-- Mode Mobile: Dedicated Card List -->
        <div class="block md:hidden p-4 space-y-3">
            {{ $mobileCards }}
        </div>
    @else
        <!-- Responsive Horizontal Scroll Table -->
        <div class="overflow-x-auto -mx-1 sm:mx-0">
            <table class="w-full text-left text-sm text-ink-100 border-collapse min-w-[540px]">
                <thead class="bg-black/5 dark:bg-white/5 border-b border-black/10 dark:border-white/10 text-xs uppercase tracking-wider font-semibold text-ink-400">
                    <tr>
                        @foreach($headers as $header)
                            <th class="py-3.5 px-3 sm:px-4 font-semibold whitespace-nowrap">{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/5 dark:divide-white/5">
                    {{ $slot }}
                </tbody>
            </table>
        </div>
    @endisset

    @isset($pagination)
        <div class="p-3 sm:p-4 border-t border-black/10 dark:border-white/5 bg-black/[0.02] dark:bg-white/[0.02]">
            {{ $pagination }}
        </div>
    @endisset
</div>
