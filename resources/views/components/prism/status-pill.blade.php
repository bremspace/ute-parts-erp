@props([
    'status' => 'pending',
    'size' => 'sm',
])

@php
$config = match(strtolower($status)) {
    'selesai', 'diterima', 'disetujui', 'aktif' => [
        'bg' => 'bg-up-mint/15',
        'text' => 'text-up-mint',
        'border' => 'border-up-mint/30',
        'dot' => 'bg-up-mint',
        'label' => ucfirst($status),
    ],
    'dikirim', 'dikerjakan', 'proses' => [
        'bg' => 'bg-up-primary/15',
        'text' => 'text-up-primary',
        'border' => 'border-up-primary/30',
        'dot' => 'bg-up-primary animate-pulse',
        'label' => ucfirst($status),
    ],
    'menunggu_approval', 'menunggu', 'draft', 'pending', 'usulan' => [
        'bg' => 'bg-up-amber/15',
        'text' => 'text-up-amber',
        'border' => 'border-up-amber/30',
        'dot' => 'bg-up-amber',
        'label' => str_replace('_', ' ', ucfirst($status)),
    ],
    'batal', 'ditolak', 'void', 'gagal' => [
        'bg' => 'bg-up-red/15',
        'text' => 'text-up-red',
        'border' => 'border-up-red/30',
        'dot' => 'bg-up-red',
        'label' => ucfirst($status),
    ],
    default => [
        'bg' => 'bg-white/10',
        'text' => 'text-ink-100',
        'border' => 'border-white/15',
        'dot' => 'bg-ink-400',
        'label' => ucfirst($status),
    ],
};

$sizeClass = $size === 'sm' ? 'px-2.5 py-0.5 text-xs gap-1.5' : 'px-3.5 py-1 text-sm gap-2';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full font-medium border {$config['bg']} {$config['text']} {$config['border']} {$sizeClass}"]) }}>
    <span class="w-1.5 h-1.5 rounded-full {{ $config['dot'] }}"></span>
    <span>{{ $slot->isNotEmpty() ? $slot : $config['label'] }}</span>
</span>
