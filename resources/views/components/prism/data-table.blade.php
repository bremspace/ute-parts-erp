@props([
    'headers' => [],
])

<div class="w-full overflow-hidden rounded-2xl glass-panel min-h-24">
    <!-- Mode Desktop: Wide Table -->
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-sm text-ink-100 border-collapse min-w-[480px]">
            <thead class="bg-white/5 border-b border-white/10 text-xs uppercase tracking-wider font-semibold text-ink-400">
                <tr>
                    @foreach($headers as $header)
                        <th class="py-3.5 px-4 font-semibold">{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                {{ $slot }}
            </tbody>
        </table>
    </div>

    <!-- Mode Mobile/Tablet: Card List Responsive -->
    <div class="block md:hidden p-4 space-y-3">
        {{ $mobileCards ?? $slot }}
    </div>

    @isset($pagination)
        <div class="p-4 border-t border-white/5 bg-white/[0.02]">
            {{ $pagination }}
        </div>
    @endisset
</div>
