@props([
    'stok' => 0,
    'min' => 5,
])

@php
$stokInt = (int) $stok;
$minInt = (int) $min;

if ($stokInt <= 0) {
    $color = 'text-up-red bg-up-red/10 border-up-red/30';
    $label = 'Habis';
} elseif ($stokInt <= $minInt) {
    $color = 'text-up-amber bg-up-amber/10 border-up-amber/30';
    $label = 'Menipis';
} else {
    $color = 'text-up-mint bg-up-mint/10 border-up-mint/30';
    $label = 'Aman';
}
@endphp

<div {{ $attributes->merge(['class' => "inline-flex items-center gap-2 px-2.5 py-1 rounded-lg border {$color}"]) }}>
    <span class="font-bold tabular-nums text-sm">{{ $stokInt }}</span>
    <span class="text-[10px] uppercase tracking-wider font-semibold opacity-80">{{ $label }}</span>
</div>
