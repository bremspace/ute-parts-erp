<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-ink-900 dark:text-white">Bagi Hasil Jasa Teknisi</h1>
            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Teknisi non-gaji mendapat porsi persentase dari jasa tiket servis yang selesai — terintegrasi penuh dengan akunting</p>
        </div>
        <a href="/app/hr/karyawan" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl border border-ink-200 dark:border-white/10 text-ink-600 dark:text-ink-300 hover:bg-black/5 dark:hover:bg-white/5 transition">
            <span>Tandai Teknisi Bagi Hasil</span>
        </a>
    </div>

    @if($pesan)
        <div class="px-4 py-3 rounded-xl bg-up-mint/10 border border-up-mint/30 text-sm text-up-mint font-medium flex items-center gap-2">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ $pesan }}</span>
        </div>
    @endif

    {{-- Tab nav --}}
    <div class="flex items-center gap-1.5 p-1 rounded-2xl bg-black/[0.03] dark:bg-white/[0.04] border border-black/5 dark:border-white/5 w-fit">
        @foreach(['rule' => 'Aturan', 'transaksi' => 'Transaksi', 'payout' => 'Payout'] as $tabKey => $tabLabel)
            <button type="button" wire:click="setTab('{{ $tabKey }}')"
                class="px-4 py-2 rounded-xl text-xs font-semibold transition-all min-h-[40px] {{ $activeTab === $tabKey ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30' : 'text-ink-500 dark:text-ink-400 hover:text-ink-900 dark:hover:text-white' }}">
                {{ $tabLabel }}
            </button>
        @endforeach
    </div>

    {{-- ═══ TAB: RULE ═══ --}}
    @if($activeTab === 'rule')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1">
                <div class="glass-card p-5">
                    <h2 class="text-lg font-bold text-ink-900 dark:text-white mb-1">{{ $editId ? 'Ubah Aturan' : 'Tambah Aturan' }}</h2>
                    <p class="text-xs text-ink-400 dark:text-ink-500 mb-4">Prioritas: per teknisi &gt; per cabang &gt; global.</p>
                    <form wire:submit="simpanRule" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Nama Aturan</label>
                            <input type="text" wire:model="nama" placeholder="mis. Teknisi senior 60%"
                                class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-2">
                            @error('nama') <span class="text-xs text-up-red font-medium mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Cabang <span class="text-ink-400 dark:text-ink-500 font-normal">(opsional)</span></label>
                                <select wire:model="ruleCabangId" class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-2">
                                    <option value="">Semua Cabang</option>
                                    @foreach($cabangList as $cb)
                                        <option value="{{ $cb->id }}">{{ $cb->nama }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Teknisi <span class="text-ink-400 dark:text-ink-500 font-normal">(opsional)</span></label>
                                <select wire:model="ruleKaryawanId" class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-2">
                                    <option value="">Semua Teknisi</option>
                                    @foreach($karyawans as $k)
                                        <option value="{{ $k->id }}">{{ $k->nama }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Persen Jasa (%)</label>
                                <input type="text" inputmode="numeric" x-format-number wire:model="persen" placeholder="mis. 50"
                                    class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-2 tabular-nums">
                                @error('persen') <span class="text-xs text-up-red font-medium mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Min. Jasa (Rp)</label>
                                <input type="text" inputmode="numeric" x-format-number wire:model="minJasa" placeholder="0 = tanpa ambang"
                                    class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-2 tabular-nums">
                            </div>
                        </div>

                        <label class="flex items-center gap-2 text-sm text-ink-700 dark:text-ink-300 cursor-pointer">
                            <input type="checkbox" wire:model="isAktif" class="rounded border-ink-300 dark:border-white/20 bg-white dark:bg-ink-900 text-up-primary focus:ring-up-primary">
                            <span>Aktif</span>
                        </label>

                        <div class="flex items-center gap-2 pt-2">
                            <button type="submit"
                                class="inline-flex justify-center items-center px-4 py-2.5 border border-transparent text-sm font-bold rounded-xl shadow-sm text-white bg-up-primary hover:bg-up-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-up-primary min-h-[44px] active:scale-[0.97] transition flex-1">
                                {{ $editId ? 'Perbarui Aturan' : 'Simpan Aturan' }}
                            </button>
                            @if($editId)
                                <button type="button" wire:click="resetRuleForm"
                                    class="inline-flex justify-center items-center px-4 py-2.5 text-sm font-semibold rounded-xl border border-ink-200 dark:border-white/10 text-ink-700 dark:text-ink-300 hover:bg-black/5 dark:hover:bg-white/5 min-h-[44px] active:scale-[0.97] transition">
                                    Batal
                                </button>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="glass-card p-5">
                    <div class="flex items-center gap-2 mb-4">
                        <select wire:model.live="filterRule" class="rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm text-base sm:text-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 min-h-[44px] px-3 py-2">
                            <option value="semua">Semua Aturan</option>
                            <option value="teknisi">Per Teknisi</option>
                            <option value="cabang">Per Cabang</option>
                            <option value="global">Global</option>
                        </select>
                    </div>

                    <x-prism.dual-scroll>
                        <table class="min-w-full divide-y divide-ink-200 dark:divide-white/10 text-sm">
                            <thead class="bg-ink-50/70 dark:bg-white/[0.03]">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase tracking-wider">Nama</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase tracking-wider">Cakupan</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase tracking-wider">Persen</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase tracking-wider">Min. Jasa</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase tracking-wider">Aktif</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink-100 dark:divide-white/5">
                                @forelse($rules as $rule)
                                    <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors">
                                        <td class="px-4 py-3 text-sm font-medium text-ink-900 dark:text-white">{{ $rule->nama }}</td>
                                        <td class="px-4 py-3 text-sm text-ink-600 dark:text-ink-300">
                                            @if($rule->karyawan)
                                                <span class="font-medium text-ink-900 dark:text-white">{{ $rule->karyawan->nama }}</span>
                                                <span class="text-xs text-ink-400 dark:text-ink-500 block">Teknisi</span>
                                            @elseif($rule->cabang)
                                                <span class="font-medium text-ink-900 dark:text-white">{{ $rule->cabang->nama }}</span>
                                                <span class="text-xs text-ink-400 dark:text-ink-500 block">Cabang</span>
                                            @else
                                                <span class="text-ink-400 dark:text-ink-500">Semua teknisi &amp; cabang</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm tabular-nums text-ink-900 dark:text-white font-semibold">{{ number_format((float) $rule->persen, 2, ',', '.') }}%</td>
                                        <td class="px-4 py-3 text-sm tabular-nums text-ink-600 dark:text-ink-300">{{ $rule->min_jasa > 0 ? 'Rp '.number_format((float) $rule->min_jasa, 0, ',', '.') : '-' }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <button wire:click="toggleRule({{ $rule->id }})" class="cursor-pointer active:scale-95 transition"
                                                title="{{ $rule->is_aktif ? 'Nonaktifkan' : 'Aktifkan' }}">
                                                <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $rule->is_aktif ? 'bg-up-mint/20 text-up-mint' : 'bg-ink-100 text-ink-500 dark:bg-white/10 dark:text-ink-400' }}">
                                                    {{ $rule->is_aktif ? 'Aktif' : 'Nonaktif' }}
                                                </span>
                                            </button>
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm space-x-2 whitespace-nowrap">
                                            <button wire:click="editRule({{ $rule->id }})" class="text-up-primary hover:underline cursor-pointer active:scale-95 transition font-medium">Ubah</button>
                                            <button wire:click="hapusRule({{ $rule->id }})" wire:confirm="Hapus aturan ini?"
                                                class="text-up-red hover:underline cursor-pointer active:scale-95 transition font-medium">Hapus</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center text-sm text-ink-400 dark:text-ink-500">
                                            Belum ada aturan bagi hasil. Tambahkan di form sebelah kiri.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </x-prism.dual-scroll>
                </div>
            </div>
        </div>
    @endif

    {{-- ═══ TAB: TRANSASKSI ═══ --}}
    @if($activeTab === 'transaksi')
        <div class="glass-card p-5 space-y-4">
            <div class="flex flex-wrap items-center gap-2">
                <input type="month" wire:model.live="filterPeriode" class="rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm text-base sm:text-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 min-h-[44px] px-3 py-2">
                <select wire:model.live="filterTeknisi" class="rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm text-base sm:text-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 min-h-[44px] px-3 py-2">
                    <option value="semua">Semua Teknisi</option>
                    @foreach($karyawans as $k)
                        <option value="{{ $k->id }}">{{ $k->nama }}</option>
                    @endforeach
                </select>
                <select wire:model.live="filterStatus" class="rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm text-base sm:text-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 min-h-[44px] px-3 py-2">
                    <option value="semua">Semua Status</option>
                    <option value="pending">Pending</option>
                    <option value="disetujui">Disetujui</option>
                    <option value="cair">Cair</option>
                    <option value="dibatalkan">Dibatalkan</option>
                </select>
                <div class="ml-auto text-xs text-ink-400 dark:text-ink-500">
                    Total: <strong class="text-ink-900 dark:text-white tabular-nums">Rp {{ number_format($transaksis->sum('nominal'), 0, ',', '.') }}</strong>
                    ({{ $transaksis->count() }} tiket)
                </div>
            </div>

            <x-prism.dual-scroll>
                <table class="min-w-full divide-y divide-ink-200 dark:divide-white/10 text-sm">
                    <thead class="bg-ink-50/70 dark:bg-white/[0.03]">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase tracking-wider">No Bagi Hasil</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase tracking-wider">Tiket</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase tracking-wider">Teknisi</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase tracking-wider">Periode</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase tracking-wider">Total Jasa</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase tracking-wider">Persen</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase tracking-wider">Nominal</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100 dark:divide-white/5">
                        @forelse($transaksis as $t)
                            <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors">
                                <td class="px-4 py-3 text-sm font-mono text-ink-900 dark:text-white">{{ $t['no_bagi_hasil'] }}</td>
                                <td class="px-4 py-3 text-sm text-ink-600 dark:text-ink-300 font-mono">{{ $t['no_tiket'] }}</td>
                                <td class="px-4 py-3 text-sm text-ink-600 dark:text-ink-300">{{ $t['karyawan_nama'] }}</td>
                                <td class="px-4 py-3 text-sm tabular-nums text-ink-600 dark:text-ink-300">{{ $t['periode'] }}</td>
                                <td class="px-4 py-3 text-sm tabular-nums text-ink-600 dark:text-ink-300 text-right">Rp {{ number_format($t['total_jasa'], 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-sm tabular-nums text-ink-600 dark:text-ink-300 text-right">{{ number_format($t['persen'], 2, ',', '.') }}%</td>
                                <td class="px-4 py-3 text-sm tabular-nums text-ink-900 dark:text-white font-semibold text-right">Rp {{ number_format($t['nominal'], 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-center">
                                    @php
                                        $warna = match($t['status']) {
                                            'pending' => 'bg-up-amber/15 text-up-amber border-up-amber/20',
                                            'disetujui' => 'bg-up-primary/15 text-up-primary border-up-primary/20',
                                            'cair' => 'bg-up-mint/15 text-up-mint border-up-mint/20',
                                            default => 'bg-black/5 text-ink-500 border-black/10 dark:bg-white/10 dark:text-ink-400 dark:border-white/10',
                                        };
                                    @endphp
                                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full border {{ $warna }}">{{ ucfirst($t['status']) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-sm text-ink-400 dark:text-ink-500">
                                    Belum ada transaksi bagi hasil pada filter ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </x-prism.dual-scroll>
        </div>
    @endif

    {{-- ═══ TAB: PAYOUT ═══ --}}
    @if($activeTab === 'payout')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 space-y-6">
                <div class="glass-card p-5 space-y-4">
                    <h2 class="text-lg font-bold text-ink-900 dark:text-white">Periode Payout</h2>

                    <div>
                        <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Periode</label>
                        <select wire:model.live="periode" class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-2">
                            <option value="">Pilih periode</option>
                            @if($periode && ! in_array($periode, $periodeList))
                                <option value="{{ $periode }}">{{ $periode }}</option>
                            @endif
                            @foreach($periodeList as $id => $p)
                                <option value="{{ $p }}">{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Buat Periode Baru</label>
                        <div class="flex gap-2">
                            <input type="month" wire:model="inputPeriodeBaru" class="flex-1 rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm text-base sm:text-sm min-h-[44px] px-3 py-2">
                            <button wire:click="buatPeriode" class="px-4 py-2 rounded-xl text-sm font-bold bg-ink-900 text-white dark:bg-white dark:text-ink-900 min-h-[44px] active:scale-[0.97] transition">Buat</button>
                        </div>
                        @error('inputPeriodeBaru') <span class="text-xs text-up-red font-medium mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-2 border-t border-black/5 dark:border-white/5">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-semibold text-ink-700 dark:text-ink-300">Status Periode</span>
                            @if($periodeRecord)
                                @php
                                    $warnaPeriode = match($periodeRecord['status']) {
                                        'draft' => 'bg-up-amber/15 text-up-amber border-up-amber/20',
                                        'selesai' => 'bg-up-primary/15 text-up-primary border-up-primary/20',
                                        default => 'bg-up-mint/15 text-up-mint border-up-mint/20',
                                    };
                                @endphp
                                <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full border {{ $warnaPeriode }}">{{ ucfirst($periodeRecord['status']) }}</span>
                            @else
                                <span class="text-xs text-ink-400 dark:text-ink-500">Belum dibuat</span>
                            @endif
                        </div>
                        @if($periodeRecord)
                            <div class="text-xs text-ink-500 dark:text-ink-400 space-y-1">
                                <div>Total: <strong class="text-ink-900 dark:text-white tabular-nums">Rp {{ number_format($periodeRecord['total'], 0, ',', '.') }}</strong> ({{ $periodeRecord['jumlah_tiket'] }} tiket)</div>
                                @if($periodeRecord['no_jurnal_accrue']) <div>Jurnal accrual: <span class="font-mono">{{ $periodeRecord['no_jurnal_accrue'] }}</span></div> @endif
                                @if($periodeRecord['no_jurnal_bayar']) <div>Jurnal bayar: <span class="font-mono">{{ $periodeRecord['no_jurnal_bayar'] }}</span></div> @endif
                            </div>
                        @endif
                    </div>
                </div>

                <div class="glass-card p-5 space-y-3">
                    <h3 class="text-sm font-bold text-ink-900 dark:text-white">Aksi</h3>
                    <button wire:click="hitung" class="w-full px-4 py-2.5 rounded-xl text-sm font-bold border border-ink-200 dark:border-white/10 text-ink-700 dark:text-ink-300 hover:bg-black/5 dark:hover:bg-white/5 min-h-[44px] active:scale-[0.97] transition">
                        1. Hitung Ulang
                    </button>
                    <button wire:click="ajukanProses" class="w-full px-4 py-2.5 rounded-xl text-sm font-bold text-white bg-up-primary hover:bg-up-primary/90 min-h-[44px] active:scale-[0.97] transition">
                        2. Ajukan &amp; Proses (Jurnal Accrual)
                    </button>
                    <button wire:click="bukaBayarModal" class="w-full px-4 py-2.5 rounded-xl text-sm font-bold text-white bg-up-mint hover:bg-up-mint/90 min-h-[44px] active:scale-[0.97] transition">
                        3. Bayar (Jurnal Kas)
                    </button>
                    <p class="text-[11px] text-ink-400 dark:text-ink-500 leading-relaxed">
                        Accrual: debit Beban Bagi Hasil (520-11) / kredit Utang Bagi Hasil Teknisi (210-06). Di atas Rp 10.000.000 wajib persetujuan finance via Approval Engine.
                    </p>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="glass-card p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-bold text-ink-900 dark:text-white">Rekap per Teknisi — {{ $periode ?: 'pilih periode' }}</h2>
                        <div class="text-xs text-ink-400 dark:text-ink-500">
                            Total: <strong class="text-ink-900 dark:text-white tabular-nums">Rp {{ number_format($totalRekap, 0, ',', '.') }}</strong>
                        </div>
                    </div>

                    <x-prism.dual-scroll>
                        <table class="min-w-full divide-y divide-ink-200 dark:divide-white/10 text-sm">
                            <thead class="bg-ink-50/70 dark:bg-white/[0.03]">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase tracking-wider">Teknisi</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase tracking-wider">Tiket</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase tracking-wider">Total Jasa</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase tracking-wider">Bagi Hasil</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink-100 dark:divide-white/5">
                                @forelse($rekap as $r)
                                    <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors">
                                        <td class="px-4 py-3 text-sm font-medium text-ink-900 dark:text-white">{{ $r['nama'] }}</td>
                                        <td class="px-4 py-3 text-sm tabular-nums text-ink-600 dark:text-ink-300 text-right">{{ $r['jumlah_tiket'] }}</td>
                                        <td class="px-4 py-3 text-sm tabular-nums text-ink-600 dark:text-ink-300 text-right">Rp {{ number_format($r['total_jasa'], 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-sm tabular-nums text-ink-900 dark:text-white font-semibold text-right">Rp {{ number_format($r['total_nominal'], 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-sm text-ink-400 dark:text-ink-500">
                                            Tidak ada bagi hasil pending pada periode ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </x-prism.dual-scroll>
                </div>
            </div>
        </div>
    @endif

    {{-- ═══ MODAL BAYAR ═══ --}}
    @if($showBayarModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" wire:click.self="tutupBayarModal">
            <div class="glass-card w-full max-w-md p-6 space-y-4">
                <h3 class="text-lg font-bold text-ink-900 dark:text-white">Bayar Bagi Hasil — {{ $periode }}</h3>
                <div class="text-sm text-ink-600 dark:text-ink-300">
                    Total: <strong class="tabular-nums text-ink-900 dark:text-white">Rp {{ number_format($periodeRecord['total'] ?? 0, 0, ',', '.') }}</strong>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1">Dari Akun Kas / Bank</label>
                    <select wire:model="akunBayarKode" class="block w-full rounded-xl border border-ink-200 dark:border-white/10 bg-white dark:bg-ink-900 text-ink-900 dark:text-white shadow-sm focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 text-base sm:text-sm min-h-[44px] px-3 py-2">
                        @foreach($akunKasBankList as $akun)
                            <option value="{{ $akun->kode }}">{{ $akun->kode }} — {{ $akun->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2 pt-2">
                    <button wire:click="bayar" class="flex-1 px-4 py-2.5 rounded-xl text-sm font-bold text-white bg-up-mint hover:bg-up-mint/90 min-h-[44px] active:scale-[0.97] transition">
                        Konfirmasi Pembayaran
                    </button>
                    <button wire:click="tutupBayarModal" class="px-4 py-2.5 rounded-xl text-sm font-semibold border border-ink-200 dark:border-white/10 text-ink-700 dark:text-ink-300 hover:bg-black/5 dark:hover:bg-white/5 min-h-[44px] active:scale-[0.97] transition">
                        Batal
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
