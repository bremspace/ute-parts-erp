<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracking Servis — Ute Parts</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-up-ink-50 text-ink-900 font-sans antialiased min-h-screen flex flex-col items-center justify-center p-4 relative overflow-hidden selection:bg-up-primary selection:text-white">
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-up-primary/20 rounded-full blur-[128px] pointer-events-none"></div>
    <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-up-accent/15 rounded-full blur-[128px] pointer-events-none"></div>

    <div class="w-full max-w-md relative z-10">
        <div class="text-center mb-8">
            <a href="{{ route('shop') }}" class="inline-flex">
                <div class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-tr from-up-primary via-indigo-500 to-up-accent items-center justify-center shadow-xl shadow-up-primary/30 mb-4 border border-white/20">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    </svg>
                </div>
            </a>
            <h1 class="text-2xl font-bold text-ink-900 tracking-wide">Tracking Servis HP</h1>
            <p class="text-sm text-ink-500 mt-1">Pantau status perbaikan unit Anda secara real-time</p>
        </div>

        <div class="bg-white rounded-3xl shadow-xl shadow-ink-100/50 border border-ink-100 p-6 relative overflow-hidden">
            <div class="circuit-line absolute top-0 left-0 w-full h-[2px]"></div>

            @if($tiket)
                <div class="text-center">
                    <span class="font-mono text-up-primary font-bold text-sm">{{ $tiket['no_tiket'] }}</span>
                    <h3 class="font-black text-ink-900 text-lg mt-1">{{ $tiket['jenis_hp'] }}</h3>
                    <p class="text-xs text-ink-400 mt-0.5">{{ $tiket['cabang'] }}</p>
                </div>

                <!-- Status Stepper -->
                <div class="mt-6 space-y-0">
                    @php
                        $flow = ['diterima', 'diagnosa', 'menunggu_approval', 'disetujui', 'dikerjakan', 'qc', 'selesai', 'diambil'];
                        $currentIdx = array_search($tiket['status'], $flow, true);
                        $currentIdx = $currentIdx === false ? -1 : $currentIdx;
                        $labels = ['Diterima', 'Diagnosa', 'Menunggu Approval', 'Disetujui', 'Dikerjakan', 'QC', 'Selesai', 'Diambil'];
                    @endphp

                    <div class="space-y-0">
                        @foreach($flow as $i => $status)
                            <div class="flex items-center gap-3 py-1.5">
                                <div class="flex flex-col items-center">
                                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold border-2
                                        {{ $i < $currentIdx ? 'bg-up-mint border-up-mint text-white' : ($i === $currentIdx ? 'bg-up-primary border-up-primary text-white shadow-lg shadow-up-primary/30' : 'border-ink-200 text-ink-300 bg-white') }}">
                                        {{ $i < $currentIdx ? '✓' : $i + 1 }}
                                    </div>
                                    @if($i < count($flow) - 1)
                                        <div class="w-0.5 h-5 {{ $i < $currentIdx ? 'bg-up-mint' : 'bg-ink-100' }}"></div>
                                    @endif
                                </div>
                                <span class="text-sm font-medium {{ $i <= $currentIdx ? 'text-ink-900 font-bold' : 'text-ink-400' }}">
                                    {{ $labels[$i] }}
                                </span>
                                @if($i === $currentIdx)
                                    <span class="ml-auto text-[10px] font-bold text-up-primary bg-up-primary/10 px-2 py-0.5 rounded-full">SAAT INI</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Info -->
                <div class="mt-5 grid grid-cols-2 gap-3 text-center">
                    <div class="p-3 rounded-xl bg-up-ink-50 border border-ink-100">
                        <p class="text-[10px] text-ink-400 uppercase font-semibold">Estimasi Biaya</p>
                        <p class="font-black text-ink-900 tabular-nums mt-0.5">
                            {{ $tiket['estimasi_biaya'] ? 'Rp '.number_format($tiket['estimasi_biaya'], 0, ',', '.') : 'Belum ada' }}
                        </p>
                    </div>
                    <div class="p-3 rounded-xl bg-up-ink-50 border border-ink-100">
                        <p class="text-[10px] text-ink-400 uppercase font-semibold">Diterima</p>
                        <p class="font-bold text-ink-900 tabular-nums mt-0.5 text-sm">{{ $tiket['tanggal_terima'] ?? '-' }}</p>
                    </div>
                </div>

                @if($tiket['garansi'])
                    <div class="mt-3 p-3 rounded-xl {{ $tiket['garansi']['aktif'] ? 'bg-up-mint/10 border border-up-mint/30' : 'bg-ink-50 border border-ink-100' }}">
                        <div class="flex items-center gap-2">
                            <span class="text-sm">🛡️</span>
                            <div>
                                <p class="text-xs font-bold {{ $tiket['garansi']['aktif'] ? 'text-up-mint' : 'text-ink-400' }}">
                                    {{ $tiket['garansi']['aktif'] ? 'Dalam Garansi' : 'Garansi Berakhir' }}
                                </p>
                                <p class="text-[11px] text-ink-400 tabular-nums">
                                    {{ $tiket['garansi']['mulai'] }} — {{ $tiket['garansi']['berakhir'] }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif
            @else
                <div class="text-center py-6">
                    <svg class="w-12 h-12 mx-auto mb-3 text-ink-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="font-semibold text-ink-700">Tiket servis tidak ditemukan</p>
                    <p class="text-sm text-ink-400 mt-1">Periksa kembali link tracking yang Anda terima.</p>
                </div>
            @endif
        </div>

        <p class="text-center text-xs text-ink-400 mt-6">
            <a href="{{ route('shop') }}" class="hover:text-up-primary">← Kembali ke Ute Parts</a>
        </p>
    </div>
</body>
</html>