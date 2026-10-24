<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-ink-900 dark:text-white">Absensi, Shift &amp; KPI</h1>
            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Roster shift, log absensi, dan KPI karyawan dari data real</p>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="glass-card p-2 sm:p-2.5">
        <div class="flex items-center gap-2 overflow-x-auto scrollbar-none py-1 -my-1 flex-nowrap">
            @foreach(['shift' => 'Shift', 'roster' => 'Roster', 'absensi' => 'Absensi', 'kpi' => 'KPI'] as $key => $label)
                <button wire:click="pilihTab('{{ $key }}')"
                    class="px-4 py-2.5 text-xs sm:text-sm font-bold rounded-xl transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer flex items-center justify-center
                    {{ $tab === $key ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'text-ink-600 dark:text-ink-400 hover:bg-black/5 dark:hover:bg-white/5 hover:text-ink-900 dark:hover:text-white' }}">
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
                <h2 class="text-lg font-bold text-ink-900 dark:text-white mb-4">Tambah / Ubah Shift</h2>
                <form wire:submit="simpanShift" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Nama Shift</label>
                        <input type="text" wire:model="shiftNama" placeholder="mis. Pagi"
                            class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-sm min-h-[44px] px-3 py-2">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Jam Mulai</label>
                            <input type="time" wire:model="shiftMulai"
                                class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-sm min-h-[44px] px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Jam Selesai</label>
                            <input type="time" wire:model="shiftSelesai"
                                class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-sm min-h-[44px] px-3 py-2">
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-ink-700 dark:text-ink-300 cursor-pointer">
                        <input type="checkbox" wire:model="shiftAktif" class="rounded border-ink-300 dark:border-white/20 bg-white dark:bg-ink-900 text-up-primary focus:ring-up-primary">
                        <span>Aktif</span>
                    </label>
                    <button type="submit"
                        class="w-full inline-flex justify-center items-center px-4 py-2.5 border border-transparent text-sm font-bold rounded-xl shadow-sm text-white bg-up-primary hover:bg-up-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-up-primary active:scale-[0.97] transition min-h-[44px]">
                        Simpan Shift
                    </button>
                </form>
                <p class="mt-3 text-xs text-ink-500 dark:text-ink-400">Jam selesai &lt; jam mulai = shift lintas hari (overnight).</p>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="glass-card p-5">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white mb-4">Daftar Shift</h2>
                <x-prism.dual-scroll>
                    <table class="min-w-full divide-y divide-ink-200 dark:divide-white/10 text-sm">
                        <thead class="bg-ink-50/70 dark:bg-white/[0.03]">
                            <tr>
                                <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Nama</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Jam</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Tipe</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Status</th>
                                <th class="px-3 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100 dark:divide-white/5">
                            @forelse($shifts as $s)
                                <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors">
                                    <td class="px-3 py-2.5 font-medium text-ink-900 dark:text-white">{{ $s->nama }}</td>
                                    <td class="px-3 py-2.5 text-ink-600 dark:text-ink-300 tabular-nums">{{ substr($s->jam_mulai, 0, 5) }} - {{ substr($s->jam_selesai, 0, 5) }}</td>
                                    <td class="px-3 py-2.5">
                                        @if($s->apakahOvernight())
                                            <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">Overnight</span>
                                        @else
                                            <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">Reguler</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <button wire:click="toggleShift({{ $s->id }})"
                                            class="inline-flex px-2.5 py-0.5 text-xs font-semibold rounded-full cursor-pointer active:scale-95 transition {{ $s->is_aktif ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-ink-100 text-ink-600 dark:bg-white/10 dark:text-ink-400' }}">
                                            {{ $s->is_aktif ? 'Aktif' : 'Nonaktif' }}
                                        </button>
                                    </td>
                                    <td class="px-3 py-2.5 text-right text-xs text-ink-400 dark:text-ink-500 font-mono">#{{ $s->id }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-3 py-8 text-center text-ink-400 dark:text-ink-500">Belum ada shift. Tambahkan di form sebelah kiri.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-prism.dual-scroll>
            </div>
        </div>
    </div>
    @endif

    {{-- Tab: Roster --}}
    @if($tab === 'roster')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="glass-card p-5">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white mb-4">Assign Shift</h2>
                <form wire:submit="simpanRoster" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Karyawan</label>
                        <select wire:model="rosterKaryawanId"
                            class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-sm min-h-[44px] px-3 py-2">
                            <option value="">-- Pilih Karyawan --</option>
                            @foreach($karyawans as $k)
                                <option value="{{ $k->id }}">{{ $k->nama }} ({{ $k->jabatan }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Tanggal</label>
                        <input type="date" wire:model="rosterTanggal"
                            class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-sm min-h-[44px] px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Shift</label>
                        <select wire:model="rosterShiftId"
                            class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-sm min-h-[44px] px-3 py-2">
                            <option value="">-- Pilih Shift --</option>
                            @foreach($shifts->where('is_aktif', true) as $s)
                                <option value="{{ $s->id }}">{{ $s->nama }} ({{ substr($s->jam_mulai, 0, 5) }}-{{ substr($s->jam_selesai, 0, 5) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit"
                        class="w-full inline-flex justify-center items-center px-4 py-2.5 border border-transparent text-sm font-bold rounded-xl shadow-sm text-white bg-up-primary hover:bg-up-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-up-primary active:scale-[0.97] transition min-h-[44px]">
                        Simpan Jadwal
                    </button>
                </form>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="glass-card p-5">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white mb-4">Jadwal Bulan {{ $kpiPeriode }}</h2>
                <x-prism.dual-scroll>
                    <table class="min-w-full divide-y divide-ink-200 dark:divide-white/10 text-sm">
                        <thead class="bg-ink-50/70 dark:bg-white/[0.03]">
                            <tr>
                                <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Tanggal</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Karyawan</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Shift</th>
                                <th class="px-3 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100 dark:divide-white/5">
                            @forelse($roster as $r)
                                <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors">
                                    <td class="px-3 py-2.5 text-ink-600 dark:text-ink-300">{{ \Carbon\Carbon::parse($r->tanggal)->format('d M Y') }}</td>
                                    <td class="px-3 py-2.5 font-medium text-ink-900 dark:text-white">{{ $r->karyawan?->nama ?? '-' }}</td>
                                    <td class="px-3 py-2.5 text-ink-600 dark:text-ink-300">{{ $r->shift?->nama ?? '-' }}</td>
                                    <td class="px-3 py-2.5 text-right">
                                        <button wire:click="hapusRoster({{ $r->id }})" class="text-xs font-semibold text-up-red hover:underline cursor-pointer active:scale-95 transition">Hapus</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-3 py-8 text-center text-ink-400 dark:text-ink-500">Belum ada jadwal pada bulan ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-prism.dual-scroll>
            </div>
        </div>
    </div>
    @endif

    {{-- Tab: Absensi --}}
    @if($tab === 'absensi')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="glass-card p-5">
                <h2 class="text-lg font-bold text-ink-900 dark:text-white mb-4">Tandai Status Manual</h2>
                <form wire:submit="tandaiStatusAbsensi" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Karyawan</label>
                        <select wire:model="absensiKaryawanId"
                            class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-sm min-h-[44px] px-3 py-2">
                            <option value="">-- Pilih Karyawan --</option>
                            @foreach($karyawans as $k)
                                <option value="{{ $k->id }}">{{ $k->nama }} ({{ $k->jabatan }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Tanggal</label>
                        <input type="date" wire:model="absensiTanggal"
                            class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-sm min-h-[44px] px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Status</label>
                        <select wire:model="absensiStatus"
                            class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-sm min-h-[44px] px-3 py-2">
                            <option value="izin">Izin</option>
                            <option value="cuti">Cuti</option>
                            <option value="absen">Absen</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Catatan</label>
                        <textarea wire:model="absensiCatatan" rows="2"
                            class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-sm px-3 py-2"></textarea>
                    </div>
                    <button type="submit"
                        class="w-full inline-flex justify-center items-center px-4 py-2.5 border border-transparent text-sm font-bold rounded-xl shadow-sm text-white bg-up-primary hover:bg-up-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-up-primary active:scale-[0.97] transition min-h-[44px]">
                        Simpan Status
                    </button>
                    <button type="button" wire:click="autoAbsen"
                        class="w-full inline-flex justify-center items-center px-4 py-2.5 border border-ink-200 dark:border-white/10 text-sm font-semibold rounded-xl text-ink-700 dark:text-ink-200 bg-white dark:bg-ink-900 hover:bg-ink-50 dark:hover:bg-white/5 active:scale-[0.97] transition min-h-[44px]">
                        Auto Absen Bulan Ini
                    </button>
                </form>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="glass-card p-5">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-2">
                    <h2 class="text-lg font-bold text-ink-900 dark:text-white">Log Absensi {{ $kpiPeriode }}</h2>
                    <select wire:model="absensiFilter"
                        class="rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-sm min-h-[44px] px-3 py-2">
                        <option value="semua">Semua Status</option>
                        <option value="hadir">Hadir</option>
                        <option value="terlambat">Terlambat</option>
                        <option value="absen">Absen</option>
                        <option value="izin">Izin</option>
                        <option value="cuti">Cuti</option>
                    </select>
                </div>
                <x-prism.dual-scroll>
                    <table class="min-w-full divide-y divide-ink-200 dark:divide-white/10 text-sm">
                        <thead class="bg-ink-50/70 dark:bg-white/[0.03]">
                            <tr>
                                <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Tanggal</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Karyawan</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Masuk</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Keluar</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Shift</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100 dark:divide-white/5">
                            @forelse($absensi as $a)
                                <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors">
                                    <td class="px-3 py-2.5 text-ink-600 dark:text-ink-300">{{ \Carbon\Carbon::parse($a->tanggal)->format('d M Y') }}</td>
                                    <td class="px-3 py-2.5 font-medium text-ink-900 dark:text-white">{{ $a->karyawan?->nama ?? '-' }}</td>
                                    <td class="px-3 py-2.5 text-ink-600 dark:text-ink-300 tabular-nums">{{ $a->jam_masuk ? substr($a->jam_masuk, 0, 5) : '-' }}</td>
                                    <td class="px-3 py-2.5 text-ink-600 dark:text-ink-300 tabular-nums">{{ $a->jam_keluar ? substr($a->jam_keluar, 0, 5) : '-' }}</td>
                                    <td class="px-3 py-2.5 text-ink-600 dark:text-ink-300">{{ $a->shift?->nama ?? '-' }}</td>
                                    <td class="px-3 py-2.5">
                                        @php
                                            $warna = match($a->status) {
                                                'terlambat' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
                                                'absen' => 'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300',
                                                'izin' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/40 dark:text-sky-300',
                                                'cuti' => 'bg-purple-100 text-purple-800 dark:bg-purple-950/40 dark:text-purple-300',
                                                default => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
                                            };
                                        @endphp
                                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $warna }}">{{ ucfirst($a->status) }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-3 py-8 text-center text-ink-400 dark:text-ink-500">Belum ada log absensi pada periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-prism.dual-scroll>
            </div>
        </div>
    </div>
    @endif

    {{-- Tab: KPI --}}
    @if($tab === 'kpi')
    <div class="glass-card p-5">
        <div class="flex flex-col sm:flex-row gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Periode (YYYY-MM)</label>
                <input type="text" wire:model="kpiPeriode" placeholder="2026-09"
                    class="block w-40 rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-sm min-h-[44px] px-3 py-2">
            </div>
            <button wire:click="hitungKpi"
                class="inline-flex items-center justify-center px-4 py-2.5 border border-transparent text-sm font-bold rounded-xl shadow-sm text-white bg-up-primary hover:bg-up-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-up-primary active:scale-[0.97] transition min-h-[44px]">
                Hitung KPI
            </button>
        </div>
        <p class="mt-2 text-xs text-ink-500 dark:text-ink-400">KPI dihitung dari data real (tiket servis, transaksi, selisih kas, lead won) — bukan input manual. Rumus whitelist, tanpa eval bebas.</p>
    </div>

    <div class="glass-card p-5">
        <h2 class="text-lg font-bold text-ink-900 dark:text-white mb-4">Hasil KPI {{ $kpiPeriode }}</h2>
        <x-prism.dual-scroll>
            <table class="min-w-full divide-y divide-ink-200 dark:divide-white/10 text-sm">
                <thead class="bg-ink-50/70 dark:bg-white/[0.03]">
                    <tr>
                        <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Karyawan</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Metric</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Nilai Aktual</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Target</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-ink-700 dark:text-ink-300">Capaian</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100 dark:divide-white/5">
                    @forelse($kpiHasil as $h)
                        <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors">
                            <td class="px-3 py-2.5 font-medium text-ink-900 dark:text-white">{{ $h->karyawan?->nama ?? '-' }}</td>
                            <td class="px-3 py-2.5 text-ink-600 dark:text-ink-300">{{ $h->metric?->nama ?? $h->metric_id }}</td>
                            <td class="px-3 py-2.5 text-ink-600 dark:text-ink-300 tabular-nums">{{ number_format((float) $h->nilai_aktual, 0, ',', '.') }} {{ $h->metric?->satuan }}</td>
                            <td class="px-3 py-2.5 text-ink-600 dark:text-ink-300 tabular-nums">{{ number_format((float) ($h->metric?->target ?? 0), 0, ',', '.') }}</td>
                            <td class="px-3 py-2.5">
                                @php
                                    $persen = (float) $h->persen_capaian;
                                    $warna = $persen >= 100 ? 'text-up-mint font-bold' : ($persen >= 70 ? 'text-up-amber font-semibold' : 'text-up-red font-semibold');
                                @endphp
                                <span class="tabular-nums {{ $warna }}">{{ number_format($persen, 1, ',', '.') }}%</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-8 text-center text-ink-400 dark:text-ink-500">Belum ada hasil KPI. Klik "Hitung KPI".</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-prism.dual-scroll>
    </div>
    @endif
</div>
