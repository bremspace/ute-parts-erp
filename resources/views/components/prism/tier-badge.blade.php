@props([
    'tier' => 'Retail', // Silver, Gold, Platinum, Reseller, Retail
])

@php
$tierLower = strtolower($tier);
$styles = match($tierLower) {
    'platinum' => 'bg-gradient-to-r from-slate-200 via-indigo-200 to-purple-200 text-ink-950 font-bold shadow-md shadow-white/10 border border-white/40',
    'gold' => 'bg-gradient-to-r from-amber-400 to-amber-600 text-ink-950 font-bold shadow-md shadow-amber-500/20 border border-amber-300/50',
    'silver' => 'bg-gradient-to-r from-slate-400 to-slate-500 text-white font-semibold border border-slate-300/30',
    'reseller' => 'bg-gradient-to-r from-up-accent to-amber-500 text-white font-bold shadow-md shadow-up-accent/20 border border-up-accent/40',
    default => 'bg-white/10 text-ink-400 border border-white/10',
};
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs {$styles} tracking-wide uppercase"]) }}>
    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
    </svg>
    <span>{{ $tier }}</span>
</span>
