@extends('layouts.backoffice')

@section('title', 'Absensi & KPI Saya')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-up-primary">Absensi & KPI Saya</h1>
            <p class="mt-1 text-sm text-ink-500">Periode {{ $karyawan ? $periode : '' }}</p>
        </div>
    </div>

    @if(! $karyawan)
        <div class="glass-card p-8 text-center">
            <p class="text-ink-600">Akun Anda belum terhubung ke data karyawan (tidak ditemukan karyawan aktif untuk user ini).</p>
            <p class="mt-2 text-sm text-ink-500">Hubungi admin HR untuk pengaturan akun.</p>
        </div>
    @else
        {{-- Rekap --}}
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
            @foreach([
                ['hadir', 'Hadir', 'text-emerald-700 bg-emerald-50'],
                ['terlambat', 'Terlambat', 'text-amber-700 bg-amber-50'],
                ['absen', 'Absen', 'text-red-700 bg-red-50'],
                ['izin', 'Izin', 'text-sky-700 bg-sky-50'],
                ['cuti', 'Cuti', 'text-purple-700 bg-purple-50'],
            ] as [$key, $label, $style])
                <div class="glass-card p-4 {{ $style }} rounded-lg">
                    <p class="text-2xl font-bold">{{ $rekap[$key] ?? 0 }}</p>
                    <p class="text-sm font-medium">{{ $label }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Log absensi --}}
            <div class="glass-card p-5">
                <h2 class="text-lg font-semibold text-ink-800 mb-4">Log Absensi</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-ink-200 text-sm">
                        <thead class="bg-ink-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-ink-600">Tanggal</th>
                                <th class="px-3 py-2 text-left font-medium text-ink-600">Masuk</th>
                                <th class="px-3 py-2 text-left font-medium text-ink-600">Keluar</th>
                                <th class="px-3 py-2 text-left font-medium text-ink-600">Shift</th>
                                <th class="px-3 py-2 text-left font-medium text-ink-600">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @forelse($logs as $a)
                                <tr>
                                    <td class="px-3 py-2 text-ink-600">{{ \Carbon\Carbon::parse($a->tanggal)->format('d M Y') }}</td>
                                    <td class="px-3 py-2 text-ink-600">{{ $a->jam_masuk ? substr($a->jam_masuk, 0, 5) : '-' }}</td>
                                    <td class="px-3 py-2 text-ink-600">{{ $a->jam_keluar ? substr($a->jam_keluar, 0, 5) : '-' }}</td>
                                    <td class="px-3 py-2 text-ink-600">{{ $a->shift?->nama ?? '-' }}</td>
                                    <td class="px-3 py-2">
                                        @php
                                            $warna = match($a->status) {
                                                'terlambat' => 'bg-amber-100 text-amber-800',
                                                'absen' => 'bg-red-100 text-red-800',
                                                'izin' => 'bg-sky-100 text-sky-800',
                                                'cuti' => 'bg-purple-100 text-purple-800',
                                                default => 'bg-emerald-100 text-emerald-800',
                                            };
                                        @endphp
                                        <span class="inline-flex px-2 py-0.5 text-xs rounded-full {{ $warna }}">{{ ucfirst($a->status) }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-3 py-6 text-center text-ink-500">Belum ada log absensi pada periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- KPI --}}
            <div class="glass-card p-5">
                <h2 class="text-lg font-semibold text-ink-800 mb-4">KPI Saya</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-ink-200 text-sm">
                        <thead class="bg-ink-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-ink-600">Metric</th>
                                <th class="px-3 py-2 text-left font-medium text-ink-600">Nilai</th>
                                <th class="px-3 py-2 text-left font-medium text-ink-600">Capaian</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @forelse($kpi as $h)
                                <tr>
                                    <td class="px-3 py-2 font-medium text-ink-800">{{ $h->metric?->nama ?? '-' }}</td>
                                    <td class="px-3 py-2 text-ink-600">{{ number_format((float) $h->nilai_aktual, 0, ',', '.') }} {{ $h->metric?->satuan }}</td>
                                    <td class="px-3 py-2">
                                        @php
                                            $persen = (float) $h->persen_capaian;
                                            $warna = $persen >= 100 ? 'text-emerald-700' : ($persen >= 70 ? 'text-amber-700' : 'text-red-700');
                                        @endphp
                                        <span class="font-semibold {{ $warna }}">{{ number_format($persen, 1, ',', '.') }}%</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-3 py-6 text-center text-ink-500">Belum ada KPI untuk periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection