@extends('layouts.backoffice')

@section('title', 'Absensi, Shift & KPI')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-up-primary">Absensi, Shift & KPI</h1>
            <p class="mt-1 text-sm text-ink-500">Roster shift, log absensi, dan KPI karyawan dari data real</p>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="glass-card p-3 mb-6">
        <div class="flex flex-wrap gap-2">
            @foreach(['shift' => 'Shift', 'roster' => 'Roster', 'absensi' => 'Absensi', 'kpi' => 'KPI'] as $key => $label)
                <button wire:click="pilihTab('{{ $key }}')"
                    class="px-4 py-2 text-sm font-medium rounded-md transition
                    {{ $tab === $key ? 'bg-up-primary text-white shadow-sm' : 'text-ink-600 hover:bg-up-primary/10' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Tab: Shift --}}
    @if($tab === 'shift')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="glass-card p-5">
                <h2 class="text-lg font-semibold text-ink-800 mb-4">Tambah / Ubah Shift</h2>
                <form wire:submit="simpanShift" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-ink-700 mb-1">Nama Shift</label>
                        <input type="text" wire:model="shiftNama" placeholder="mis. Pagi"
                            class="block w-full rounded-md border-ink-300 shadow-sm focus:border-up-primary focus:ring-up-primary sm:text-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-ink-700 mb-1">Jam Mulai</label>
                            <input type="time" wire:model="shiftMulai"
                                class="block w-full rounded-md border-ink-300 shadow-sm focus:border-up-primary focus:ring-up-primary sm:text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-ink-700 mb-1">Jam Selesai</label>
                            <input type="time" wire:model="shiftSelesai"
                                class="block w-full rounded-md border-ink-300 shadow-sm focus:border-up-primary focus:ring-up-primary sm:text-sm">
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-ink-700">
                        <input type="checkbox" wire:model="shiftAktif" class="rounded border-ink-300">
                        Aktif
                    </label>
                    <button type="submit"
                        class="w-full inline-flex justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-up-primary hover:bg-up-primary/80 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-up-primary">
                        Simpan Shift
                    </button>
                </form>
                <p class="mt-3 text-xs text-ink-500">Jam selesai < jam mulai = shift lintas hari (overnight).</p>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="glass-card p-5">
                <h2 class="text-lg font-semibold text-ink-800 mb-4">Daftar Shift</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-ink-200 text-sm">
                        <thead class="bg-ink-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-ink-600">Nama</th>
                                <th class="px-3 py-2 text-left font-medium text-ink-600">Jam</th>
                                <th class="px-3 py-2 text-left font-medium text-ink-600">Tipe</th>
                                <th class="px-3 py-2 text-left font-medium text-ink-600">Status</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @forelse($shifts as $s)
                                <tr>
                                    <td class="px-3 py-2 font-medium text-ink-800">{{ $s->nama }}</td>
                                    <td class="px-3 py-2 text-ink-600">{{ substr($s->jam_mulai, 0, 5) }} - {{ substr($s->jam_selesai, 0, 5) }}</td>
                                    <td class="px-3 py-2">
                                        @if($s->apakahOvernight())
                                            <span class="inline-flex px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-800">Overnight</span>
                                        @else
                                            <span class="inline-flex px-2 py-0.5 text-xs rounded-full bg-emerald-100 text-emerald-800">Reguler</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        <button wire:click="toggleShift({{ $s->id }})"
                                            class="inline-flex px-2 py-0.5 text-xs rounded-full {{ $s->is_aktif ? 'bg-emerald-100 text-emerald-800' : 'bg-ink-100 text-ink-500' }}">
                                            {{ $s->is_aktif ? 'Aktif' : 'Nonaktif' }}
                                        </button>
                                    </td>
                                    <td class="px-3 py-2 text-right text-xs text-ink-500">#{{ $s->id }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-3 py-6 text-center text-ink-500">Belum ada shift. Tambahkan di form sebelah kiri.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Tab: Roster --}}
    @if($tab === 'roster')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="glass-card p-5">
                <h2 class="text-lg font-semibold text-ink-800 mb-4">Assign Shift</h2>
                <form wire:submit="simpanRoster" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-ink-700 mb-1">Karyawan</label>
                        <select wire:model="rosterKaryawanId"
                            class="block w-full rounded-md border-ink-300 shadow-sm focus:border-up-primary focus:ring-up-primary sm:text-sm">
                            <option value="">-- Pilih Karyawan --</option>
                            @foreach($karyawans as $k)
                                <option value="{{ $k->id }}">{{ $k->nama }} ({{ $k->jabatan }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-700 mb-1">Tanggal</label>
                        <input type="date" wire:model="rosterTanggal"
                            class="block w-full rounded-md border-ink-300 shadow-sm focus:border-up-primary focus:ring-up-primary sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-700 mb-1">Shift</label>
                        <select wire:model="rosterShiftId"
                            class="block w-full rounded-md border-ink-300 shadow-sm focus:border-up-primary focus:ring-up-primary sm:text-sm">
                            <option value="">-- Pilih Shift --</option>
                            @foreach($shifts->where('is_aktif', true) as $s)
                                <option value="{{ $s->id }}">{{ $s->nama }} ({{ substr($s->jam_mulai, 0, 5) }}-{{ substr($s->jam_selesai, 0, 5) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit"
                        class="w-full inline-flex justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-up-primary hover:bg-up-primary/80 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-up-primary">
                        Simpan Jadwal
                    </button>
                </form>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="glass-card p-5">
                <h2 class="text-lg font-semibold text-ink-800 mb-4">Jadwal Bulan {{ $kpiPeriode }}</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-ink-200 text-sm">
                        <thead class="bg-ink-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-ink-600">Tanggal</th>
                                <th class="px-3 py-2 text-left font-medium text-ink-600">Karyawan</th>
                                <th class="px-3 py-2 text-left font-medium text-ink-600">Shift</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @forelse($roster as $r)
                                <tr>
                                    <td class="px-3 py-2 text-ink-600">{{ \Carbon\Carbon::parse($r->tanggal)->format('d M Y') }}</td>
                                    <td class="px-3 py-2 font-medium text-ink-800">{{ $r->karyawan?->nama ?? '-' }}</td>
                                    <td class="px-3 py-2 text-ink-600">{{ $r->shift?->nama ?? '-' }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <button wire:click="hapusRoster({{ $r->id }})" class="text-xs text-red-600 hover:underline">Hapus</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-3 py-6 text-center text-ink-500">Belum ada jadwal pada bulan ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Tab: Absensi --}}
    @if($tab === 'absensi')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="glass-card p-5">
                <h2 class="text-lg font-semibold text-ink-800 mb-4">Tandai Status Manual</h2>
                <form wire:submit="tandaiStatusAbsensi" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-ink-700 mb-1">Karyawan</label>
                        <select wire:model="absensiKaryawanId"
                            class="block w-full rounded-md border-ink-300 shadow-sm focus:border-up-primary focus:ring-up-primary sm:text-sm">
                            <option value="">-- Pilih Karyawan --</option>
                            @foreach($karyawans as $k)
                                <option value="{{ $k->id }}">{{ $k->nama }} ({{ $k->jabatan }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-700 mb-1">Tanggal</label>
                        <input type="date" wire:model="absensiTanggal"
                            class="block w-full rounded-md border-ink-300 shadow-sm focus:border-up-primary focus:ring-up-primary sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-700 mb-1">Status</label>
                        <select wire:model="absensiStatus"
                            class="block w-full rounded-md border-ink-300 shadow-sm focus:border-up-primary focus:ring-up-primary sm:text-sm">
                            <option value="izin">Izin</option>
                            <option value="cuti">Cuti</option>
                            <option value="absen">Absen</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-700 mb-1">Catatan</label>
                        <textarea wire:model="absensiCatatan" rows="2"
                            class="block w-full rounded-md border-ink-300 shadow-sm focus:border-up-primary focus:ring-up-primary sm:text-sm"></textarea>
                    </div>
                    <button type="submit"
                        class="w-full inline-flex justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-up-primary hover:bg-up-primary/80 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-up-primary">
                        Simpan Status
                    </button>
                    <button type="button" wire:click="autoAbsen"
                        class="w-full inline-flex justify-center px-4 py-2 border border-ink-300 text-sm font-medium rounded-md text-ink-700 bg-white hover:bg-ink-50">
                        Auto Absen Bulan Ini
                    </button>
                </form>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="glass-card p-5">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-2">
                    <h2 class="text-lg font-semibold text-ink-800">Log Absensi {{ $kpiPeriode }}</h2>
                    <select wire:model="absensiFilter"
                        class="rounded-md border-ink-300 shadow-sm focus:border-up-primary focus:ring-up-primary sm:text-sm">
                        <option value="semua">Semua Status</option>
                        <option value="hadir">Hadir</option>
                        <option value="terlambat">Terlambat</option>
                        <option value="absen">Absen</option>
                        <option value="izin">Izin</option>
                        <option value="cuti">Cuti</option>
                    </select>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-ink-200 text-sm">
                        <thead class="bg-ink-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-ink-600">Tanggal</th>
                                <th class="px-3 py-2 text-left font-medium text-ink-600">Karyawan</th>
                                <th class="px-3 py-2 text-left font-medium text-ink-600">Masuk</th>
                                <th class="px-3 py-2 text-left font-medium text-ink-600">Keluar</th>
                                <th class="px-3 py-2 text-left font-medium text-ink-600">Shift</th>
                                <th class="px-3 py-2 text-left font-medium text-ink-600">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @forelse($absensi as $a)
                                <tr>
                                    <td class="px-3 py-2 text-ink-600">{{ \Carbon\Carbon::parse($a->tanggal)->format('d M Y') }}</td>
                                    <td class="px-3 py-2 font-medium text-ink-800">{{ $a->karyawan?->nama ?? '-' }}</td>
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
                                <tr><td colspan="6" class="px-3 py-6 text-center text-ink-500">Belum ada log absensi pada periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Tab: KPI --}}
    @if($tab === 'kpi')
    <div class="glass-card p-5 mb-6">
        <div class="flex flex-col sm:flex-row gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-ink-700 mb-1">Periode (YYYY-MM)</label>
                <input type="text" wire:model="kpiPeriode" placeholder="2026-09"
                    class="block w-40 rounded-md border-ink-300 shadow-sm focus:border-up-primary focus:ring-up-primary sm:text-sm">
            </div>
            <button wire:click="hitungKpi"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-up-primary hover:bg-up-primary/80 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-up-primary">
                Hitung KPI
            </button>
        </div>
        <p class="mt-2 text-xs text-ink-500">KPI dihitung dari data real (tiket servis, transaksi, selisih kas, lead won) — bukan input manual. Rumus whitelist, tanpa eval bebas.</p>
    </div>

    <div class="glass-card p-5">
        <h2 class="text-lg font-semibold text-ink-800 mb-4">Hasil KPI {{ $kpiPeriode }}</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-ink-200 text-sm">
                <thead class="bg-ink-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-ink-600">Karyawan</th>
                        <th class="px-3 py-2 text-left font-medium text-ink-600">Metric</th>
                        <th class="px-3 py-2 text-left font-medium text-ink-600">Nilai Aktual</th>
                        <th class="px-3 py-2 text-left font-medium text-ink-600">Target</th>
                        <th class="px-3 py-2 text-left font-medium text-ink-600">Capaian</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @forelse($kpiHasil as $h)
                        <tr>
                            <td class="px-3 py-2 font-medium text-ink-800">{{ $h->karyawan?->nama ?? '-' }}</td>
                            <td class="px-3 py-2 text-ink-600">{{ $h->metric?->nama ?? $h->metric_id }}</td>
                            <td class="px-3 py-2 text-ink-600">{{ number_format((float) $h->nilai_aktual, 0, ',', '.') }} {{ $h->metric?->satuan }}</td>
                            <td class="px-3 py-2 text-ink-600">{{ number_format((float) ($h->metric?->target ?? 0), 0, ',', '.') }}</td>
                            <td class="px-3 py-2">
                                @php
                                    $persen = (float) $h->persen_capaian;
                                    $warna = $persen >= 100 ? 'text-emerald-700' : ($persen >= 70 ? 'text-amber-700' : 'text-red-700');
                                @endphp
                                <span class="font-semibold {{ $warna }}">{{ number_format($persen, 1, ',', '.') }}%</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-6 text-center text-ink-500">Belum ada hasil KPI. Klik "Hitung KPI".</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection