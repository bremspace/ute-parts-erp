<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracking Servis — Ute Parts</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/icon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col items-center justify-start p-4 md:p-8 relative selection:bg-indigo-600 selection:text-white">
    <div class="fixed -top-40 -left-40 w-96 h-96 bg-indigo-500/10 rounded-full blur-[128px] pointer-events-none"></div>
    <div class="fixed -bottom-40 -right-40 w-96 h-96 bg-orange-500/10 rounded-full blur-[128px] pointer-events-none"></div>

    <div class="w-full max-w-2xl relative z-10 my-auto">
        <!-- Brand Header -->
        <div class="text-center mb-6 flex justify-center">
            <x-prism.logo size="lg" layout="vertical" mode="light" title="Status Servis & Perbaikan" subtitle="Pantau perkembangan perbaikan gadget Anda secara transparan" :href="route('shop')" />
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

        @if($tiket)
            <!-- Main Tracking Card -->
            <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-200 p-6 md:p-8 space-y-6">
                <!-- Header Info -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-100 pb-5">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-indigo-600 font-bold text-sm bg-indigo-50 px-2.5 py-1 rounded-lg border border-indigo-100">
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
                    <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800">
                        <div class="flex items-center gap-2 font-bold text-sm">
                            <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            Estimasi Ditolak
                        </div>
                        <p class="text-xs text-rose-700 mt-1">
                            Pengerjaan perbaikan dihentikan karena estimasi biaya ditolak. Silakan hubungi cabang untuk informasi pengambilan unit.
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
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all
                                    {{ $isCurrent ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/40 ring-4 ring-indigo-100' :
                                       ($isCompleted ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-400 border border-slate-200') }}">
                                    @if($isCompleted)
                                        ✓
                                    @else
                                        {{ $i + 1 }}
                                    @endif
                                </div>
                                <span class="text-[11px] mt-1.5 font-medium {{ $isCurrent ? 'text-indigo-600 font-bold' : ($isCompleted ? 'text-slate-700' : 'text-slate-400') }}">
                                    {{ $labels[$step] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Card Approval Estimasi (Jika menunggu_approval) -->
                @if($tiket['status'] === 'menunggu_approval')
                    <div class="rounded-2xl border-2 border-indigo-200 bg-indigo-50/50 p-5 md:p-6 space-y-4">
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <div>
                                <span class="text-xs font-bold text-indigo-700 uppercase tracking-wider bg-indigo-100 px-2 py-0.5 rounded-full">
                                    Memerlukan Persetujuan Anda
                                </span>
                                <h3 class="text-lg font-bold text-slate-900 mt-1">Persetujuan Estimasi Biaya</h3>
                            </div>
                            <div class="text-right">
                                <span class="text-xs text-slate-500 block">Total Estimasi</span>
                                <span class="text-2xl font-black text-indigo-700 tabular-nums">
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
                        <div class="pt-2 flex flex-col sm:flex-row gap-3">
                            <form action="{{ route('servis.tracking.approve', $tiket['token_approval']) }}" method="POST" class="flex-1">
                                @csrf
                                <input type="hidden" name="alasan" value="Disetujui oleh pelanggan via web tracking">
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3.5 rounded-xl bg-up-mint hover:bg-teal-600 text-ink-950 font-bold text-sm shadow-lg shadow-up-mint/25 transition-[transform,background-color] active:scale-[0.97] min-h-[44px] cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Setujui Estimasi & Lanjutkan Perbaikan
                                </button>
                            </form>

                            <form action="{{ route('servis.tracking.reject', $tiket['token_approval']) }}" method="POST" class="sm:w-auto" onsubmit="return confirm('Apakah Anda yakin ingin menolak estimasi perbaikan ini?')">
                                @csrf
                                <input type="hidden" name="alasan" value="Ditolak oleh pelanggan via web tracking">
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-3.5 rounded-xl bg-white hover:bg-rose-50 text-rose-600 border border-rose-200 hover:border-rose-300 font-semibold text-sm transition-[transform,background-color] active:scale-[0.97] min-h-[44px] cursor-pointer">
                                    Tolak Estimasi
                                </button>
                            </form>
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
                    <div class="p-4 rounded-2xl {{ $tiket['garansi']['aktif'] ? 'bg-emerald-50 border border-emerald-200' : 'bg-slate-50 border border-slate-200' }}">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center {{ $tiket['garansi']['aktif'] ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-500' }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-bold {{ $tiket['garansi']['aktif'] ? 'text-emerald-900' : 'text-slate-700' }}">
                                    {{ $tiket['garansi']['aktif'] ? 'Unit dalam Masa Garansi Resmi' : 'Garansi Servis Telah Berakhir' }}
                                </p>
                                <p class="text-xs text-slate-500 tabular-nums mt-0.5">
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
            </div>
        @else
            <!-- Tiket Tidak Ditemukan -->
            <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-200 p-8 text-center">
                <div class="w-14 h-14 rounded-2xl bg-slate-100 text-slate-400 mx-auto flex items-center justify-center mb-3">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="font-bold text-slate-800 text-lg">Tiket Servis Tidak Ditemukan</h3>
                <p class="text-sm text-slate-500 mt-1 max-w-sm mx-auto">
                    Mohon periksa kembali link atau token tracking servis yang Anda buka. Hubungi customer service kami bila membutuhkan bantuan.
                </p>
            </div>
        @endif

        <p class="text-center text-xs text-slate-400 mt-6">
            <a href="{{ route('shop') }}" class="hover:text-indigo-600 transition-colors">← Kembali ke Halaman Utama</a>
        </p>
    </div>
</body>
</html>
