@props([
    'size' => 'md', // sm, md, lg
    'withText' => true,
    'mode' => 'auto', // dark, light, auto
    'layout' => 'horizontal', // horizontal, vertical
    'title' => null,
    'subtitle' => null,
    'href' => null,
    'textClass' => '',
    'image' => null,
])

@php
$id = uniqid('prism-logo-');

$resolvedTitle = $title ?: config('app.name', 'BREMSPACE');

// Lazy-resolve logo image jika belum diberikan
$logoImage = $image;
if ($logoImage === null) {
    try {
        $logoImage = app(\App\Modules\Crm\Services\KonfigurasiService::class)->getAppLogo();
    } catch (\Throwable) {
        $logoImage = null;
    }
}

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
    <!-- Brand / Prism Emblem Badge -->
    <div class="{{ $conf['box'] }} bg-gradient-to-tr from-up-primary via-indigo-600 to-up-accent flex items-center justify-center shadow-lg shadow-up-primary/25 border border-white/20 flex-shrink-0 group-hover:shadow-up-primary/40 transition-shadow overflow-hidden">
        @if(!empty($logoImage))
            <img src="{{ $logoImage }}" alt="{{ $resolvedTitle }}" class="w-full h-full object-contain p-1 rounded-inherit">
        @else
            <svg class="{{ $conf['icon'] }}" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <defs>
                    <linearGradient id="facet-top-{{ $id }}" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#FFFFFF" stop-opacity="0.95" />
                        <stop offset="100%" stop-color="#DCD6FE" stop-opacity="0.8" />
                    </linearGradient>
                    <linearGradient id="facet-left-{{ $id }}" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#818CF8" />
                        <stop offset="100%" stop-color="#4F46E5" />
                    </linearGradient>
                    <linearGradient id="facet-right-{{ $id }}" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#FFBE88" />
                        <stop offset="100%" stop-color="#E8873B" />
                    </linearGradient>
                    <linearGradient id="facet-center-{{ $id }}" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#34D399" />
                        <stop offset="100%" stop-color="#1FBF8F" />
                    </linearGradient>
                </defs>

                <!-- Modern Geometric Diamond Prism Facets -->
                <!-- Top Cap Facet -->
                <polygon points="50,14 82,32 50,48 18,32" fill="url(#facet-top-{{ $id }})" />
                <!-- Left Lower Facet -->
                <polygon points="18,32 50,48 50,86 18,66" fill="url(#facet-left-{{ $id }})" />
                <!-- Right Lower Facet -->
                <polygon points="50,48 82,32 82,66 50,86" fill="url(#facet-right-{{ $id }})" />
                <!-- Center Core Spark -->
                <polygon points="50,38 60,48 50,58 40,48" fill="url(#facet-center-{{ $id }})" opacity="0.95" />
                <circle cx="50" cy="48" r="2" fill="#FFFFFF" />
            </svg>
        @endif
    </div>

    @if($withText)
        <div class="min-w-0 {{ $textClass }}">
            <span class="{{ $conf['title'] }} {{ $titleColor }} block truncate tracking-wider font-bold">{{ $resolvedTitle }}</span>
            @if($subtitle)
                <span class="{{ $conf['subtitle'] }} {{ $subtitleColor }} block truncate">{{ $subtitle }}</span>
            @endif
        </div>
    @endif
</{{ $wrapperTag }}>
