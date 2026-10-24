<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracking Servis — Ute Parts</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/icon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    <script>
        (function () {
            var pref = null;
            try { pref = localStorage.getItem('ute-theme'); } catch (e) {}
            if (!pref) pref = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            var dark = pref === 'dark';
            var root = document.documentElement;
            root.dataset.theme = dark ? 'dark' : 'light';
            root.classList.toggle('dark', dark);
        })();
    </script>

    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#F8FAFC] dark:bg-[#090D16] text-ink-900 dark:text-ink-100 font-sans antialiased min-h-screen flex flex-col items-center justify-start p-4 md:p-8 relative selection:bg-up-primary selection:text-white">
    <!-- Skip to main content (WCAG 2.4.1) -->
    <a href="#tracking-content" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:px-4 focus:py-2 focus:bg-up-primary focus:text-white focus:rounded-xl focus:shadow-lg focus:outline-none">
        Lewati ke konten tracking
    </a>

    <!-- Theme Mode Switcher -->
    <div class="absolute top-4 right-4 z-50">
        <button type="button" x-data="themeManager()" @click="cycleTheme()"
                title="Ganti tema tampilan" aria-label="Ganti tema tampilan"
                class="min-h-[40px] min-w-[40px] p-2 rounded-full bg-white dark:bg-ink-900 border border-slate-200 dark:border-white/10 text-ink-500 hover:text-ink-900 dark:text-ink-400 dark:hover:text-white shadow-sm transition-[background-color,color,transform] duration-140 active:scale-[0.95] cursor-pointer flex items-center justify-center">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-show="theme === 'dark'" x-cloak>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-show="theme === 'light'" x-cloak>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
            </svg>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-show="theme === 'auto'" x-cloak>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
        </button>
    </div>

    <div id="tracking-content" class="w-full max-w-2xl relative z-10 my-auto outline-none" tabindex="-1">
        <!-- Brand Header -->
        <div class="text-center mb-6 flex justify-center">
            <x-prism.logo size="lg" layout="vertical" mode="auto" title="Status Servis & Perbaikan" subtitle="Pantau perkembangan perbaikan gadget Anda secara transparan" :href="route('shop')" />
        </div>

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="mb-4 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-start gap-3 shadow-sm">
                <svg class="w-5 h-5 text-emerald-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <div class="text-sm font-medium">{{ session('success') }}</div>
            </div>
        @endif

        @if(session('info'))
            <div class="mb-4 p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-800 flex items-start gap-3 shadow-sm">
                <svg class="w-5 h-5 text-amber-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="text-sm font-medium">{{ session('info') }}</div>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 flex items-start gap-3 shadow-sm">
                <svg class="w-5 h-5 text-rose-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <div class="text-sm font-medium">{{ session('error') }}</div>
            </div>
        @endif

        @if($perluVerifikasi ?? false)
            <!-- Form Verifikasi 5 Digit No HP -->
            <div class="bg-white dark:bg-[#0B1120] rounded-3xl shadow-xl shadow-slate-200/50 dark:shadow-none border border-slate-200 dark:border-white/10 p-6 md:p-8 text-center max-w-md mx-auto">
                <div class="w-14 h-14 rounded-2xl bg-up-primary/10 text-up-primary mx-auto flex items-center justify-center mb-4 shadow-sm">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>

                <h3 class="font-bold text-slate-900 dark:text-white text-lg">Verifikasi Keamanan Tracking</h3>
                <p class="text-xs text-slate-500 dark:text-ink-400 mt-1 mb-4 leading-relaxed">
                    Untuk melindungi privasi data perbaikan, silakan masukkan <span class="font-bold text-slate-700 dark:text-ink-200">5 digit terakhir nomor HP</span> yang didaftarkan saat servis unit ini.
                </p>

                @if(!empty($tiketSummary))
                    <div class="bg-slate-50 dark:bg-white/[0.03] border border-slate-200/80 dark:border-white/10 rounded-2xl p-3.5 mb-5 text-left text-xs space-y-1.5">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500 dark:text-ink-400">Nomor Tiket:</span>
                            <span class="font-mono font-bold text-up-primary bg-up-primary/10 px-2 py-0.5 rounded border border-up-primary/20">{{ $tiketSummary['no_tiket'] }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500 dark:text-ink-400">Unit:</span>
                            <span class="font-medium text-slate-800 dark:text-white">{{ $tiketSummary['jenis_hp'] }} {{ $tiketSummary['seri_hp'] ?? '' }}</span>
                        </div>
                        @if(!empty($tiketSummary['cabang']))
                            <div class="flex justify-between items-center">
                                <span class="text-slate-500 dark:text-ink-400">Cabang:</span>
                                <span class="text-slate-600 dark:text-ink-300">{{ $tiketSummary['cabang'] }}</span>
                            </div>
                        @endif
                    </div>
                @endif

                <form action="{{ route('servis.tracking.verify', $token) }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label for="no_hp" class="block text-xs font-semibold text-slate-700 dark:text-ink-300 text-left mb-1.5">
                            5 Digit Terakhir No. HP
                        </label>
                        <input
                            type="text"
                            id="no_hp"
                            name="no_hp"
                            inputmode="numeric"
                            pattern="\d*"
                            maxlength="5"
                            placeholder="Contoh: 56789"
                            required
                            autofocus
                            class="w-full text-center tracking-[0.3em] font-mono text-lg font-bold px-4 py-3 rounded-xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-ink-900 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-up-primary focus:bg-white dark:focus:bg-ink-950"
                        />
                    </div>

                    <button type="submit" class="w-full py-3 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-sm shadow-md transition-colors cursor-pointer">
                        Verifikasi & Lihat Tracking
                    </button>
                </form>

                <div class="mt-5 pt-4 border-t border-slate-100 dark:border-white/5 text-xs text-slate-500 dark:text-ink-400">
                    Memiliki akun terdaftar?
                    <a href="{{ route('customer.login') }}" class="font-semibold text-up-primary hover:underline">
                        Login ke akun web
                    </a>
                </div>
            </div>
        @elseif($tiket)
            <!-- Main Tracking Card -->
            <div class="bg-white dark:bg-[#0B1120] rounded-3xl shadow-xl shadow-slate-200/50 dark:shadow-none border border-slate-200 dark:border-white/10 p-6 md:p-8 space-y-6">
                <!-- Header Info -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-100 dark:border-white/5 pb-5">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-up-primary font-bold text-sm bg-up-primary/10 px-2.5 py-1 rounded-lg border border-up-primary/20">
                                {{ $tiket['no_tiket'] }}
                            </span>
                            @if(($tiket['status_pembayaran'] ?? '') === 'lunas')
                                <span class="text-xs font-bold text-emerald-700 bg-emerald-100 px-2.5 py-0.5 rounded-full">
                                    LUNAS
                                </span>
                            @else
                                <span class="text-xs font-medium text-amber-800 bg-amber-100 px-2.5 py-0.5 rounded-full">
                                    Belum Bayar
                                </span>
                            @endif
                        </div>
                        <h2 class="font-bold text-slate-900 text-xl mt-2">
                            {{ $tiket['jenis_hp'] }}
                            @if(!empty($tiket['seri_hp']))
                                <span class="text-slate-500 font-normal text-base">({{ $tiket['seri_hp'] }})</span>
                            @endif
                        </h2>
                        @if($tiket['keluhan'])
                            <p class="text-xs text-slate-500 mt-1 italic">Keluhan: "{{ $tiket['keluhan'] }}"</p>
                        @endif
                    </div>

                    @if(!empty($tiket['cabang']))
                        <div class="sm:text-right text-xs text-slate-500">
                            <p class="font-bold text-slate-800">{{ $tiket['cabang']['nama'] ?? $tiket['cabang'] }}</p>
                            @if(is_array($tiket['cabang']) && !empty($tiket['cabang']['telepon']))
                                <p class="mt-0.5">Telp: {{ $tiket['cabang']['telepon'] }}</p>
                            @endif
                        </div>
                    @endif
                </div>

                <!-- Status Ditolak Banner -->
                @if($tiket['status'] === 'ditolak')
                    <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 space-y-1">
                        <div class="flex items-center gap-2 font-bold text-sm">
                            <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            Estimasi Ditolak
                        </div>
                        <p class="text-xs text-rose-700">
                            Pengerjaan perbaikan dihentikan karena estimasi biaya ditolak oleh Anda.
                        </p>
                        @if(!empty($tiket['alasan_ditolak']))
                            <div class="text-xs text-rose-900 bg-rose-100/70 p-2.5 rounded-xl border border-rose-200/80 mt-1.5">
                                <span class="font-semibold">Alasan Penolakan:</span> "{{ $tiket['alasan_ditolak'] }}"
                            </div>
                        @endif
                        <p class="text-[11px] text-rose-600 mt-1">
                            Silakan datang ke gerai untuk mengambil kembali unit gadget Anda atau hubungi admin cabang jika ingin berkonsultasi opsi perbaikan alternatif.
                        </p>
                    </div>
                @endif

                <!-- Stepper Progres -->
                <div class="py-2">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Progres Pengerjaan</h3>
                    @php
                        $flow = ['diajukan_online', 'diterima', 'diagnosa', 'menunggu_approval', 'disetujui', 'dikerjakan', 'qc', 'selesai', 'diambil'];
                        $labels = [
                            'diajukan_online' => 'Diajukan',
                            'diterima' => 'Diterima',
                            'diagnosa' => 'Diagnosa',
                            'menunggu_approval' => 'Estimasi',
                            'disetujui' => 'Disetujui',
                            'dikerjakan' => 'Dikerjakan',
                            'qc' => 'QC',
                            'selesai' => 'Selesai',
                            'diambil' => 'Diambil',
                        ];

                        $currentIdx = array_search($tiket['status'], $flow, true);
                        $currentIdx = $currentIdx === false ? -1 : $currentIdx;
                    @endphp

                    <div class="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-9 gap-2">
                        @foreach($flow as $i => $step)
                            @php
                                $isCompleted = $currentIdx >= 0 && $i < $currentIdx;
                                $isCurrent = $i === $currentIdx;
                            @endphp
                            <div class="flex flex-col items-center text-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-[background-color,color,box-shadow] duration-140
                                    {{ $isCurrent ? 'bg-up-primary text-white shadow-md shadow-up-primary/40 ring-4 ring-up-primary/20' :
                                       ($isCompleted ? 'bg-up-mint text-ink-950 font-bold' : 'bg-slate-100 dark:bg-white/5 text-slate-400 dark:text-ink-400 border border-slate-200 dark:border-white/10') }}">
                                    @if($isCompleted)
                                        ✓
                                    @else
                                        {{ $i + 1 }}
                                    @endif
                                </div>
                                <span class="text-[11px] mt-1.5 font-medium {{ $isCurrent ? 'text-up-primary font-bold' : ($isCompleted ? 'text-slate-700 dark:text-ink-200' : 'text-slate-400 dark:text-ink-500') }}">
                                    {{ $labels[$step] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Card Approval Estimasi (Jika menunggu_approval) -->
                @if($tiket['status'] === 'menunggu_approval')
                    <div class="rounded-2xl border border-up-primary/30 bg-up-primary/5 dark:bg-up-primary/10 p-5 md:p-6 space-y-4">
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <div>
                                <span class="text-xs font-bold text-up-primary uppercase tracking-wider bg-up-primary/15 px-2.5 py-1 rounded-full">
                                    Memerlukan Persetujuan Anda
                                </span>
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white mt-1">Persetujuan Estimasi Biaya</h3>
                            </div>
                            <div class="text-right">
                                <span class="text-xs text-slate-500 dark:text-ink-400 block">Total Estimasi</span>
                                <span class="text-2xl font-black text-up-primary tabular-nums">
                                    Rp {{ number_format((float) ($tiket['estimasi_biaya'] ?? 0), 0, ',', '.') }}
                                </span>
                            </div>
                        </div>

                        <!-- Rincian Part & Jasa Penawaran -->
                        @if(!empty($tiket['estimasi_items']))
                            <div class="overflow-x-auto rounded-xl border border-indigo-100 bg-white shadow-sm">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-indigo-50/70 text-slate-600 uppercase font-bold text-[10px] tracking-wider border-b border-indigo-100">
                                        <tr>
                                            <th class="p-3">Item / Layanan</th>
                                            <th class="p-3 text-center">Tipe</th>
                                            <th class="p-3 text-center">Qty</th>
                                            <th class="p-3 text-right">Harga</th>
                                            <th class="p-3 text-right">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-indigo-50 text-slate-700">
                                        @foreach($tiket['estimasi_items'] as $item)
                                            <tr>
                                                <td class="p-3 font-medium text-slate-900">{{ $item['nama_item'] }}</td>
                                                <td class="p-3 text-center">
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $item['tipe'] === 'part' ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700' }}">
                                                        {{ strtoupper($item['tipe']) }}
                                                    </span>
                                                </td>
                                                <td class="p-3 text-center tabular-nums">{{ $item['qty'] }}</td>
                                                <td class="p-3 text-right tabular-nums">Rp {{ number_format((float)$item['harga'], 0, ',', '.') }}</td>
                                                <td class="p-3 text-right font-bold tabular-nums text-slate-900">Rp {{ number_format((float)$item['subtotal'], 0, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        <!-- Tombol Aksi Persetujuan -->
                        <div x-data="{ showRejectModal: false }" class="pt-2">
                            <div class="flex flex-col sm:flex-row gap-3">
                                <form action="{{ route('servis.tracking.approve', $tiket['token_approval']) }}" method="POST" class="flex-1">
                                    @csrf
                                    <input type="hidden" name="alasan" value="Disetujui oleh pelanggan via web tracking">
                                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3.5 rounded-xl bg-up-mint hover:bg-teal-600 text-ink-950 font-bold text-sm shadow-sm shadow-up-mint/25 transition-[transform,background-color] active:scale-[0.97] min-h-[44px] cursor-pointer">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Setujui Estimasi & Lanjutkan Perbaikan
                                    </button>
                                </form>

                                <button type="button" @click="showRejectModal = true" class="sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-3.5 rounded-xl bg-white hover:bg-rose-50 text-rose-600 border border-rose-200 hover:border-rose-300 font-semibold text-sm transition-[transform,background-color] active:scale-[0.97] min-h-[44px] cursor-pointer">
                                    Tolak Estimasi
                                </button>
                            </div>

                            <!-- Modal Konfirmasi Penolakan & Alasan -->
                            <div x-show="showRejectModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                                <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" @click="showRejectModal = false"></div>
                                <div class="flex min-h-full items-center justify-center p-4">
                                    <div class="relative bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4 text-left">
                                        <div class="flex items-center justify-between">
                                            <h4 class="font-bold text-slate-900 text-base flex items-center gap-2">
                                                <svg class="w-5 h-5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                </svg>
                                                Tolak Estimasi Biaya
                                            </h4>
                                            <button type="button" @click="showRejectModal = false" class="text-slate-400 hover:text-slate-600 text-sm">✕</button>
                                        </div>

                                        <p class="text-xs text-slate-600">
                                            Jika Anda menolak estimasi ini, pengerjaan servis tidak akan dilanjutkan. Mohon berikan alasan penolakan agar teknisi kami dapat mengevaluasi atau memproses pengembalian unit.
                                        </p>

                                        <form action="{{ route('servis.tracking.reject', $tiket['token_approval']) }}" method="POST" class="space-y-4" x-data="{ alasanPilihan: '', alasanCustom: '' }">
                                            @csrf
                                            <div>
                                                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Pilih Alasan Penolakan</label>
                                                <div class="space-y-1.5 mb-2">
                                                    @foreach([
                                                        'Biaya perbaikan melebihi anggaran / budget',
                                                        'Ingin mempertimbangkan atau membandingkan dulu',
                                                        'Waktu pengerjaan terlalu lama',
                                                        'Unit berencana diganti baru',
                                                        'Lainnya (tulis alasan kustom di bawah)'
                                                    ] as $opsi)
                                                        <label class="flex items-center gap-2 p-2 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer text-xs transition-colors">
                                                            <input
                                                                type="radio"
                                                                name="opsi_radio"
                                                                value="{{ $opsi }}"
                                                                x-model="alasanPilihan"
                                                                @change="if (alasanPilihan !== 'Lainnya (tulis alasan kustom di bawah)') { alasanCustom = alasanPilihan; }"
                                                                class="text-rose-600 focus:ring-rose-500"
                                                            />
                                                            <span class="text-slate-700 font-medium">{{ $opsi }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>

                                                <label class="block text-xs font-semibold text-slate-700 mb-1">Rincian / Catatan Alasan</label>
                                                <textarea
                                                    name="alasan"
                                                    rows="2"
                                                    required
                                                    x-model="alasanCustom"
                                                    placeholder="Tuliskan catatan alasan penolakan..."
                                                    class="w-full text-xs rounded-xl border border-slate-300 p-2.5 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 outline-none"
                                                ></textarea>
                                            </div>

                                            <div class="flex justify-end gap-2 pt-2">
                                                <button type="button" @click="showRejectModal = false" class="px-4 py-2 text-xs font-medium text-slate-600 hover:bg-slate-100 rounded-xl cursor-pointer">
                                                    Batal
                                                </button>
                                                <button type="submit" class="px-4 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl shadow cursor-pointer">
                                                    Konfirmasi Tolak
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Status & Batas Waktu Pengerjaan Teknisi (Live Monitoring) -->
                @if(in_array($tiket['status'], ['dikerjakan', 'qc']) && !empty($tiket['pengerjaan']['pengerjaan_mulai_at']))
                    <div class="p-4 rounded-2xl {{ $tiket['pengerjaan']['is_overdue'] ? 'bg-rose-50 border border-rose-200' : 'bg-indigo-50/60 border border-indigo-100' }}">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center {{ $tiket['pengerjaan']['is_overdue'] ? 'bg-rose-500 text-white' : 'bg-indigo-600 text-white' }} shadow-sm">
                                    <svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-xs font-bold uppercase tracking-wider {{ $tiket['pengerjaan']['is_overdue'] ? 'text-rose-900' : 'text-indigo-950' }}">
                                            {{ $tiket['pengerjaan']['is_overdue'] ? 'Pengerjaan Melewati Batas Waktu (Overdue)' : 'Sedang Dalam Pengerjaan Teknisi' }}
                                        </h4>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold {{ $tiket['pengerjaan']['is_overdue'] ? 'bg-rose-200 text-rose-800' : 'bg-indigo-200/80 text-indigo-800' }}">
                                            SLA: {{ $tiket['pengerjaan']['batas_waktu_menit'] }} Menit
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-0.5">
                                        Mulai: <span class="font-medium text-slate-700">{{ $tiket['pengerjaan']['pengerjaan_mulai_at'] }}</span>
                                        @if(!empty($tiket['pengerjaan']['pengerjaan_deadline_at']))
                                            • Target Selesai: <span class="font-medium text-slate-700">{{ $tiket['pengerjaan']['pengerjaan_deadline_at'] }}</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                @if($tiket['pengerjaan']['is_overdue'])
                                    <span class="text-xs font-bold text-rose-600 bg-rose-100 px-2.5 py-1 rounded-lg">Waktu Habis</span>
                                @else
                                    <span class="text-xs font-bold text-indigo-700 bg-indigo-100/70 px-2.5 py-1 rounded-lg tabular-nums">
                                        Sisa Waktu: ~{{ $tiket['pengerjaan']['sisa_menit'] }} Menit
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Progres Pengerjaan Transparan (Foto & Update Teknisi) -->
                @if(!empty($tiket['progress']))
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                Progres Pengerjaan Unit Real-Time
                            </h3>
                            <span class="text-xs font-bold text-emerald-600 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-full tabular-nums">
                                {{ $tiket['progress'][0]['persentase'] }}% Selesai
                            </span>
                        </div>

                        <!-- Progress Bar Keseluruhan -->
                        <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                            <div class="bg-gradient-to-r from-indigo-500 to-emerald-500 h-2.5 rounded-full transition-[width] duration-250 ease-out" style="width: {{ $tiket['progress'][0]['persentase'] }}%"></div>
                        </div>

                        <!-- Timeline Log Progress -->
                        <div class="space-y-3">
                            @foreach($tiket['progress'] as $p)
                                <div class="p-3.5 rounded-xl border border-slate-200 bg-white shadow-sm space-y-2">
                                    <div class="flex items-center justify-between text-xs">
                                        <div class="flex items-center gap-2">
                                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 tabular-nums">
                                                {{ $p['persentase'] }}%
                                            </span>
                                            <span class="font-bold text-slate-800">{{ $p['judul'] }}</span>
                                        </div>
                                        <span class="text-slate-400 text-[11px] tabular-nums">{{ $p['waktu'] }}</span>
                                    </div>

                                    @if($p['deskripsi'])
                                        <p class="text-xs text-slate-600 pl-1">{{ $p['deskripsi'] }}</p>
                                    @endif

                                    @if(!empty($p['foto']))
                                        <div class="flex flex-wrap gap-2 pt-1">
                                            @foreach($p['foto'] as $fotoItem)
                                                <a href="{{ $fotoItem }}" target="_blank" class="block w-20 h-20 rounded-lg overflow-hidden border border-slate-200 hover:opacity-90 transition-opacity">
                                                    <img src="{{ $fotoItem }}" alt="Foto Progress" class="w-full h-full object-cover">
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Rincian Pekerjaan & Sparepart (Disetujui / Dikerjakan / QC / Selesai) -->
                @if(in_array($tiket['status'], ['disetujui', 'dikerjakan', 'qc', 'selesai', 'diambil']) && !empty($tiket['items']))
                    <div class="space-y-3">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Rincian Komponen & Jasa Terpasang</h3>
                        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-slate-600 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                                    <tr>
                                        <th class="p-3">Nama Pekerjaan / Part</th>
                                        <th class="p-3 text-center">Tipe</th>
                                        <th class="p-3 text-center">Qty</th>
                                        <th class="p-3 text-right">Harga</th>
                                        <th class="p-3 text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    @foreach($tiket['items'] as $item)
                                        <tr>
                                            <td class="p-3 font-medium text-slate-900">{{ $item['nama_item'] }}</td>
                                            <td class="p-3 text-center">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $item['tipe'] === 'part' ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700' }}">
                                                    {{ strtoupper($item['tipe']) }}
                                                </span>
                                            </td>
                                            <td class="p-3 text-center tabular-nums">{{ $item['qty'] }}</td>
                                            <td class="p-3 text-right tabular-nums">Rp {{ number_format((float)$item['harga'], 0, ',', '.') }}</td>
                                            <td class="p-3 text-right font-bold tabular-nums text-slate-900">Rp {{ number_format((float)$item['subtotal'], 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <!-- Garansi Info -->
                @if($tiket['garansi'])
                    <div class="p-4 rounded-2xl {{ $tiket['garansi']['aktif'] ? 'bg-up-mint/10 border border-up-mint/30 dark:bg-up-mint/15' : 'bg-slate-50 dark:bg-white/[0.03] border border-slate-200 dark:border-white/10' }}">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center {{ $tiket['garansi']['aktif'] ? 'bg-up-mint text-ink-950 font-bold' : 'bg-ink-100 dark:bg-white/10 text-ink-500 dark:text-ink-400' }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-bold {{ $tiket['garansi']['aktif'] ? 'text-up-mint' : 'text-slate-700 dark:text-ink-200' }}">
                                    {{ $tiket['garansi']['aktif'] ? 'Unit dalam Masa Garansi Resmi' : 'Garansi Servis Telah Berakhir' }}
                                </p>
                                <p class="text-xs text-slate-500 dark:text-ink-400 tabular-nums mt-0.5">
                                    Periode: {{ $tiket['garansi']['mulai'] }} s.d. {{ $tiket['garansi']['berakhir'] }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Timeline Riwayat Status -->
                @if(!empty($tiket['timeline']))
                    <div class="space-y-3 pt-2">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Riwayat Status Perbaikan</h3>
                        <div class="relative pl-6 space-y-4 before:content-[''] before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
                            @foreach($tiket['timeline'] as $log)
                                <div class="relative">
                                    <div class="absolute -left-6 top-1 w-2.5 h-2.5 rounded-full bg-indigo-600 ring-4 ring-white"></div>
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-bold text-slate-800 capitalize">
                                            {{ str_replace('_', ' ', $log['status_ke']) }}
                                        </span>
                                        <span class="text-slate-400 tabular-nums">{{ $log['created_at'] }}</span>
                                    </div>
                                    @if(!empty($log['catatan']))
                                        <p class="text-xs text-slate-500 mt-0.5">{{ $log['catatan'] }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @php
                    $cabangTelp = $tiket['cabang']['telepon'] ?? null;
                    $pesanTanyaAdmin = "Halo Admin {$tiket['cabang']['nama']}, saya ingin menanyakan perkembangan servis saya dengan nomor tiket #{$tiket['no_tiket']} ({$tiket['jenis_hp']}).";
                    $waLinkAdmin = \App\Support\WhatsAppHelper::buatLink($cabangTelp, $pesanTanyaAdmin);
                @endphp
                @if($waLinkAdmin)
                    <div class="pt-2 border-t border-slate-100 flex justify-center">
                        <a
                            href="{{ $waLinkAdmin }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#25D366] hover:bg-[#20ba59] text-white font-bold text-xs shadow-md transition-[background-color,transform] duration-100 active:scale-[0.98] cursor-pointer no-underline"
                            title="Chat WhatsApp Cabang untuk konsultasi atau tanya info servis"
                        >
                            <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86.174.086.275.073.376-.044.101-.116.433-.506.549-.68.116-.173.231-.144.39-.086s1.011.477 1.184.564.289.13.332.203c.043.072.043.419-.101.824z"/></svg>
                            <span>Tanya Admin Cabang via WhatsApp</span>
                        </a>
                    </div>
                @endif
            </div>
        @else
            <!-- Form Pencarian & Tracking Servis -->
            <div class="bg-white dark:bg-[#0B1120] rounded-3xl shadow-xl shadow-slate-200/50 dark:shadow-none border border-slate-200 dark:border-white/10 p-6 md:p-8 text-center max-w-lg mx-auto">
                <div class="w-14 h-14 rounded-2xl bg-up-primary/10 text-up-primary mx-auto flex items-center justify-center mb-3">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <h3 class="font-bold text-slate-800 dark:text-white text-lg">Lacak Status Servis Gadget</h3>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-ink-400 mt-1 max-w-sm mx-auto mb-6">
                    Masukkan nomor tiket servis dan 5 digit terakhir nomor HP yang didaftarkan untuk memantau perbaikan.
                </p>

                <form action="{{ route('servis.tracking.search') }}" method="POST" class="space-y-3.5 text-left">
                    @csrf
                    <div>
                        <label for="search_q" class="block text-xs font-semibold text-slate-700 dark:text-ink-200 mb-1">
                            Nomor Tiket atau Token Servis <span class="text-up-red">*</span>
                        </label>
                        <input
                            type="text"
                            id="search_q"
                            name="q"
                            value="{{ old('q', request('q')) }}"
                            placeholder="Contoh: SRV-2026-0001 atau token"
                            required
                            autofocus
                            class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-ink-900 text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-up-primary focus:bg-white dark:focus:bg-ink-950"
                        />
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label for="search_no_hp" class="block text-xs font-semibold text-slate-700 dark:text-ink-200">
                                5 Digit Terakhir No. HP Terdaftar
                            </label>
                            @if(auth('customer')->check() || auth('web')->check())
                                <span class="text-[11px] text-up-mint font-semibold">✓ Akun login terdeteksi</span>
                            @endif
                        </div>
                        <input
                            type="text"
                            id="search_no_hp"
                            name="no_hp"
                            inputmode="numeric"
                            pattern="\d*"
                            maxlength="5"
                            value="{{ old('no_hp') }}"
                            placeholder="Contoh: 56789 (opsional jika sudah login)"
                            class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-ink-900 text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-up-primary focus:bg-white dark:focus:bg-ink-950"
                        />
                    </div>

                    <button type="submit" class="w-full py-3 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-sm shadow-md transition-colors cursor-pointer active:scale-[0.98]">
                        Lacak Status Servis
                    </button>
                </form>

                @if(!auth('customer')->check() && !auth('web')->check())
                    <div class="mt-5 pt-4 border-t border-slate-100 dark:border-white/5 text-xs text-slate-500 dark:text-ink-400">
                        Punya akun terdaftar di web?
                        <a href="{{ route('customer.login') }}" class="font-semibold text-up-primary hover:underline">
                            Login di sini
                        </a>
                        untuk tracking otomatis.
                    </div>
                @endif
            </div>
        @endif

        <p class="text-center text-xs text-slate-400 dark:text-ink-400 mt-6">
            <a href="{{ route('shop') }}" class="hover:text-up-primary transition-colors">← Kembali ke Halaman Utama</a>
        </p>
    </div>

    @livewireScripts
</body>
</html>
