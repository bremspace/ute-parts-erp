@props([
    'size' => 'md', // sm, md, lg
    'withText' => true,
    'mode' => 'auto', // dark, light, auto
    'layout' => 'horizontal', // horizontal, vertical
    'title' => 'UTE PARTS',
    'subtitle' => null,
    'href' => null,
    'textClass' => '',
])

@php
$id = uniqid('up-logo-');

$sizes = [
    'sm' => [
        'box' => 'w-7 h-7 rounded-lg min-h-[28px] min-w-[28px]',
        'icon' => 'w-4 h-4',
        'title' => 'text-sm font-bold tracking-wider leading-none',
        'subtitle' => 'text-[9px] tracking-widest uppercase font-semibold leading-none mt-1',
        'gap' => $layout === 'vertical' ? 'gap-2.5' : 'gap-2',
    ],
    'md' => [
        'box' => 'w-9 h-9 rounded-xl min-h-[36px] min-w-[36px]',
        'icon' => 'w-5 h-5',
        'title' => 'text-base font-bold tracking-wider leading-none',
        'subtitle' => 'text-[10px] tracking-widest uppercase font-semibold leading-none mt-1.5',
        'gap' => $layout === 'vertical' ? 'gap-3' : 'gap-3',
    ],
    'lg' => [
        'box' => 'w-14 h-14 rounded-2xl min-h-[56px] min-w-[56px] shadow-xl',
        'icon' => 'w-8 h-8',
        'title' => 'text-2xl font-bold tracking-wide leading-tight',
        'subtitle' => 'text-sm tracking-normal font-normal leading-tight mt-1.5',
        'gap' => $layout === 'vertical' ? 'gap-4' : 'gap-4',
    ],
];

$conf = $sizes[$size] ?? $sizes['md'];

$titleColor = match ($mode) {
    'dark' => 'text-white',
    'light' => 'text-ink-900',
    default => 'text-ink-900 dark:text-white',
};

$subtitleColor = match ($mode) {
    'dark' => 'text-ink-400',
    'light' => 'text-ink-500',
    default => 'text-ink-500 dark:text-ink-400',
};

// Accent subtitle color if standard brand subtitle
$isBrandSubtitle = in_array(strtolower((string)$subtitle), [
    'backoffice erp',
    'sparepart & servis hp',
    'erp & pos'
]);
if ($isBrandSubtitle) {
    $subtitleColor = 'text-up-accent';
}

$directionClass = $layout === 'vertical' ? 'flex flex-col items-center text-center' : 'inline-flex items-center';
$wrapperTag = $href ? 'a' : 'div';
@endphp

<{{ $wrapperTag }}
    @if($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => "{$directionClass} {$conf['gap']} select-none group transition-opacity duration-150"]) }}
>
    <!-- Precision Hardware Prism Emblem Badge -->
    <div class="{{ $conf['box'] }} bg-gradient-to-tr from-up-primary via-indigo-600 to-up-accent flex items-center justify-center shadow-lg shadow-up-primary/25 border border-white/20 flex-shrink-0 group-hover:shadow-up-primary/40 transition-shadow">
        <svg class="{{ $conf['icon'] }}" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <defs>
                <linearGradient id="u-{{ $id }}" x1="0%" y1="0%" x2="80%" y2="100%">
                    <stop offset="0%" stop-color="#FFFFFF" />
                    <stop offset="100%" stop-color="#DCD6FE" />
                </linearGradient>
                <linearGradient id="p-{{ $id }}" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#FFF3E8" />
                    <stop offset="100%" stop-color="#FFBE88" />
                </linearGradient>
                <linearGradient id="mint-{{ $id }}" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#34D399" />
                    <stop offset="100%" stop-color="#1FBF8F" />
                </linearGradient>
            </defs>

            <!-- 'U' Track -->
            <path d="M 18 18
                     L 29 18
                     L 29 58
                     C 29 67 35 73 44 73
                     C 50 73 54 70 54 65
                     L 54 48
                     L 65 48
                     L 65 65
                     C 65 77 56 84 44 84
                     C 28 84 18 74 18 58
                     Z"
                  fill="url(#u-{{ $id }})" />

            <!-- 'P' Loop & Stem -->
            <path d="M 36 18
                     L 66 18
                     C 77 18 84 25 84 35
                     C 84 45 77 52 66 52
                     L 47 52
                     L 47 74
                     L 36 74
                     Z
                     M 47 28.5
                     L 64 28.5
                     C 68.5 28.5 72.5 31 72.5 35
                     C 72.5 39 68.5 41.5 64 41.5
                     L 47 41.5
                     Z"
                  fill="url(#p-{{ $id }})" />

            <!-- Interlocking Facet Bridge -->
            <path d="M 47 48 L 54 48 L 54 52 L 47 52 Z" fill="#FFFFFF" opacity="0.85" />

            <!-- Optical Refraction Mint Diamond -->
            <polygon points="41.5,30 45.5,34.5 41.5,39 37.5,34.5" fill="url(#mint-{{ $id }})" />
            <circle cx="41.5" cy="34.5" r="1.3" fill="#FFFFFF" />
        </svg>
    </div>

    @if($withText)
        <div class="min-w-0 {{ $textClass }}">
            <span class="{{ $conf['title'] }} {{ $titleColor }} block truncate tracking-wider font-bold">{{ $title }}</span>
            @if($subtitle)
                <span class="{{ $conf['subtitle'] }} {{ $subtitleColor }} block truncate">{{ $subtitle }}</span>
            @endif
        </div>
    @endif
</{{ $wrapperTag }}>
