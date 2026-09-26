<div class="space-y-6">
    <!-- Header + Periode -->
    <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3 border-b border-white/5 pb-4">
        <div class="flex items-center gap-2 flex-wrap">
            @foreach(['laporan' => 'Laporan', 'jurnal' => 'Jurnal', 'coa' => 'COA', 'piutang' => 'Piutang', 'utang' => 'Utang'] as $kode => $label)
                <button wire:click="$set('activeTab', '{{ $kode }}')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $activeTab === $kode ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/5 text-ink-300 hover:bg-white/10' }}">{{ $label }}</button>
            @endforeach
        </div>

        <div class="flex items-center gap-2">
            <label class="text-[11px] text-ink-400 font-medium">Periode</label>
            <input type="date" wire:model.live="periodeDari" class="px-2.5 py-2 rounded-lg glass-input text-xs font-medium" />
            <span class="text-ink-400 text-xs">—</span>
            <input type="date" wire:model.live="periodeSampai" class="px-2.5 py-2 rounded-lg glass-input text-xs font-medium" />
        </div>
    </div>

    <!-- TAB: LAPORAN -->
    @if($activeTab === 'laporan')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Laba Rugi -->
            <x-prism.glass-card title="Laba Rugi" :subtitle="'Periode ' . $periodeDari . ' — ' . $periodeSampai" circuit="true">
                <div class="space-y-3">
                    <div>
                        <p class="text-[10px] text-up-mint uppercase font-bold mb-1.5">Pendapatan</p>
                        @forelse($labaRugi['pendapatan'] as $p)
                            <div class="flex justify-between text-xs py-1 border-b border-white/5">
                                <span class="text-ink-200">{{ $p['nama'] }} <span class="text-ink-500 font-mono">({{ $p['kode'] }})</span></span>
                                <span class="font-bold text-white tabular-nums">Rp {{ number_format($p['total'], 0, ',', '.') }}</span>
                            </div>
                        @empty
                            <p class="text-[11px] text-ink-500 py-1">Belum ada pendapatan di periode ini.</p>
                        @endforelse
                        <div class="flex justify-between text-xs font-bold pt-1.5 mt-1">
                            <span class="text-up-mint">Total Pendapatan</span>
                            <span class="text-up-mint tabular-nums">Rp {{ number_format($labaRugi['total_pendapatan'], 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div>
                        <p class="text-[10px] text-up-red uppercase font-bold mb-1.5">Beban</p>
                        @forelse($labaRugi['beban'] as $b)
                            <div class="flex justify-between text-xs py-1 border-b border-white/5">
                                <span class="text-ink-200">{{ $b['nama'] }} <span class="text-ink-500 font-mono">({{ $b['kode'] }})</span></span>
                                <span class="font-bold text-white tabular-nums">Rp {{ number_format($b['total'], 0, ',', '.') }}</span>
                            </div>
                        @empty
                            <p class="text-[11px] text-ink-500 py-1">Belum ada beban di periode ini.</p>
                        @endforelse
                        <div class="flex justify-between text-xs font-bold pt-1.5 mt-1">
                            <span class="text-up-red">Total Beban</span>
                            <span class="text-up-red tabular-nums">Rp {{ number_format($labaRugi['total_beban'], 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="p-4 rounded-2xl {{ $labaRugi['laba_bersih'] >= 0 ? 'bg-up-mint/10 border border-up-mint/30' : 'bg-up-red/10 border border-up-red/30' }} flex justify-between items-center">
                        <span class="text-xs font-bold {{ $labaRugi['laba_bersih'] >= 0 ? 'text-up-mint' : 'text-up-red' }} uppercase">Laba Bersih</span>
                        <span class="text-2xl font-black tabular-nums {{ $labaRugi['laba_bersih'] >= 0 ? 'text-up-mint' : 'text-up-red' }}">
                            {{ $labaRugi['laba_bersih'] >= 0 ? '+' : '-' }} Rp {{ number_format(abs($labaRugi['laba_bersih']), 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </x-prism.glass-card>

            <!-- Neraca -->
            <x-prism.glass-card title="Neraca" :subtitle="'Saldo kumulatif s/d ' . $neraca['sampai_tanggal']" circuit="true">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5">
                        <p class="text-[10px] text-ink-400 uppercase font-bold mb-2">Aset</p>
                        @forelse($neraca['aset'] as $a)
                            <div class="flex justify-between text-[11px] py-0.5">
                                <span class="text-ink-300">{{ $a['nama'] }}</span>
                                <span class="text-white tabular-nums">{{ number_format($a['saldo'], 0, ',', '.') }}</span>
                            </div>
                        @empty
                            <p class="text-[11px] text-ink-500">Kosong</p>
                        @endforelse
                        <div class="border-t border-white/10 mt-1.5 pt-1.5 flex justify-between text-xs font-bold">
                            <span class="text-ink-200">Total</span>
                            <span class="text-white tabular-nums">Rp {{ number_format($neraca['total_aset'], 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5">
                        <p class="text-[10px] text-ink-400 uppercase font-bold mb-2">Kewajiban</p>
                        @forelse($neraca['kewajiban'] as $kw)
                            <div class="flex justify-between text-[11px] py-0.5">
                                <span class="text-ink-300">{{ $kw['nama'] }}</span>
                                <span class="text-white tabular-nums">{{ number_format($kw['saldo'], 0, ',', '.') }}</span>
                            </div>
                        @empty
                            <p class="text-[11px] text-ink-500">Kosong</p>
                        @endforelse
                        <div class="border-t border-white/10 mt-1.5 pt-1.5 flex justify-between text-xs font-bold">
                            <span class="text-ink-200">Total</span>
                            <span class="text-white tabular-nums">Rp {{ number_format($neraca['total_kewajiban'], 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5">
                        <p class="text-[10px] text-ink-400 uppercase font-bold mb-2">Ekuitas</p>
                        @forelse($neraca['ekuitas'] as $e)
                            <div class="flex justify-between text-[11px] py-0.5">
                                <span class="text-ink-300">{{ $e['nama'] }}</span>
                                <span class="text-white tabular-nums">{{ number_format($e['saldo'], 0, ',', '.') }}</span>
                            </div>
                        @empty
                            <p class="text-[11px] text-ink-500">Kosong</p>
                        @endforelse
                        {{-- [B-10g] Akun pendapatan & beban belum ditutup ke Laba Ditahan, jadi
                             laba kumulatif ikut dihitung sebagai bagian ekuitas (sumber:
                             ExportLaporanService::neracaSaldo / API ACC-06). --}}
                        <div class="flex justify-between text-[11px] py-0.5">
                            <span class="text-ink-300">Laba Periode Berjalan</span>
                            <span class="text-white tabular-nums">{{ number_format($neraca['laba_periode_berjalan'], 0, ',', '.') }}</span>
                        </div>
                        <div class="border-t border-white/10 mt-1.5 pt-1.5 flex justify-between text-xs font-bold">
                            <span class="text-ink-200">Total</span>
                            <span class="text-white tabular-nums">Rp {{ number_format($neraca['total_ekuitas_bersama_laba'], 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                @php $balanceOk = (bool) $neraca['balance']; $selisihNeraca = (float) $neraca['selisih']; @endphp
                <div class="mt-4 p-3 rounded-xl {{ $balanceOk ? 'bg-up-mint/10 border border-up-mint/30' : 'bg-up-red/10 border border-up-red/30' }} flex flex-wrap items-center gap-2 text-xs">
                    <span class="w-2 h-2 rounded-full {{ $balanceOk ? 'bg-up-mint' : 'bg-up-red' }}"></span>
                    <span class="{{ $balanceOk ? 'text-up-mint' : 'text-up-red' }} font-semibold">
                        {{ $balanceOk ? 'SEIMBANG — Aset = Kewajiban + Ekuitas + Laba Periode Berjalan' : 'TIDAK SEIMBANG — Aset ≠ Kewajiban + Ekuitas + Laba Periode Berjalan' }}
                    </span>
                    <span class="ml-auto tabular-nums {{ $balanceOk ? 'text-up-mint' : 'text-up-red' }} font-semibold">
                        Selisih: {{ $selisihNeraca > 0 ? '+' : ($selisihNeraca < 0 ? '−' : '') }} Rp {{ number_format(abs($selisihNeraca), 0, ',', '.') }}
                    </span>
                </div>
                <p class="mt-2 text-[11px] text-ink-400">
                    Angka saldo kumulatif sejak awal pembukuan s/d {{ $neraca['sampai_tanggal'] }} — bukan perubahan periode.
                </p>
            </x-prism.glass-card>

            <!-- Arus Kas (metode tidak langsung) -->
            <x-prism.glass-card title="Laporan Arus Kas" :subtitle="'Metode Tidak Langsung — ' . $periodeDari . ' s.d. ' . $periodeSampai" circuit="true" class="lg:col-span-2">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Operasi -->
                    <div class="p-4 rounded-xl bg-white/[0.02] border border-white/5">
                        <p class="text-[10px] text-up-mint uppercase font-bold mb-2">Arus Kas Operasi</p>
                        <div class="space-y-1.5 text-xs">
                            <div class="flex justify-between">
                                <span class="text-ink-400">Laba bersih</span>
                                <span class="font-semibold text-white tabular-nums">Rp {{ number_format($arusKas['laba_bersih'], 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-ink-400">Kenaikan piutang</span>
                                <span class="font-semibold text-up-red tabular-nums">({{ number_format($arusKas['penyesuaian']['kenaikan_piutang'], 0, ',', '.') }})</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-ink-400">Kenaikan persediaan</span>
                                <span class="font-semibold text-up-red tabular-nums">({{ number_format($arusKas['penyesuaian']['kenaikan_persediaan'], 0, ',', '.') }})</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-ink-400">Kenaikan utang</span>
                                <span class="font-semibold text-up-mint tabular-nums">+{{ number_format($arusKas['penyesuaian']['kenaikan_utang'], 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between pt-2 border-t border-white/10 font-bold mt-2">
                                <span class="text-up-mint">Kas dari operasi</span>
                                <span class="text-up-mint tabular-nums">Rp {{ number_format($arusKas['arus_kas_operasi'], 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Investasi -->
                    <div class="p-4 rounded-xl bg-white/[0.02] border border-white/5">
                        <p class="text-[10px] text-up-accent uppercase font-bold mb-2">Arus Kas Investasi</p>
                        <div class="space-y-1.5 text-xs">
                            <div class="flex justify-between">
                                <span class="text-ink-400">Pembelian aset tetap & peralatan</span>
                                <span class="font-semibold text-up-accent tabular-nums">Rp {{ number_format($arusKas['arus_kas_investasi'], 0, ',', '.') }}</span>
                            </div>
                            <p class="text-[10px] text-ink-500 mt-3">Beban pendapatan & pembelian aset jangka panjang periode ini.</p>
                        </div>
                    </div>

                    <!-- Pendanaan -->
                    <div class="p-4 rounded-xl bg-white/[0.02] border border-white/5">
                        <p class="text-[10px] text-up-primary uppercase font-bold mb-2">Arus Kas Pendanaan</p>
                        <div class="space-y-1.5 text-xs">
                            <div class="flex justify-between">
                                <span class="text-ink-400">Setoran modal / laba</span>
                                <span class="font-semibold text-up-primary tabular-nums">Rp {{ number_format($arusKas['arus_kas_pendanaan'], 0, ',', '.') }}</span>
                            </div>
                            <p class="text-[10px] text-ink-500 mt-3">Perubahan ekuitas & laba ditahan periode ini.</p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 p-4 rounded-2xl bg-white/[0.03] border border-white/5 flex justify-between items-center">
                    <span class="text-xs font-bold text-ink-200 uppercase tracking-wider">Kenaikan Kas Neto</span>
                    <span class="text-2xl font-black tabular-nums {{ $arusKas['kenaikan_kas_neto'] >= 0 ? 'text-up-mint' : 'text-up-red' }}">
                        {{ $arusKas['kenaikan_kas_neto'] >= 0 ? '+' : '-' }} Rp {{ number_format(abs($arusKas['kenaikan_kas_neto']), 0, ',', '.') }}
                    </span>
                </div>
            </x-prism.glass-card>
        </div>

        <!-- [T-33] Riwayat Sesi Kas -->
        <x-prism.glass-card title="Riwayat Sesi Kas" :subtitle="'50 sesi terakhir — cabang aktif'" circuit="true">
            @php $sumberLabel = ['manual' => 'Manual', 'legacy' => 'Legacy', 'carryover' => 'Lanjutan Shift']; @endphp
            <x-prism.data-table :headers="['Sesi', 'Kasir', 'Saldo Awal', 'Saldo Sistem', 'Saldo Fisik', 'Selisih', 'Sumber', 'Status', 'Dibuka', 'Ditutup']">
                @forelse($kasSesiRiwayat as $s)
                    <tr class="hover:bg-white/[0.02] transition-colors text-xs">
                        <td class="py-3.5 px-4 font-mono font-bold text-white">#{{ $s->id }}</td>
                        <td class="py-3.5 px-4 text-ink-100 font-medium">{{ $s->kasir }}</td>
                        <td class="py-3.5 px-4 tabular-nums text-white">Rp {{ number_format($s->saldo_awal, 0, ',', '.') }}</td>
                        <td class="py-3.5 px-4 tabular-nums text-ink-200">{{ $s->saldo_akhir_sistem !== null ? 'Rp '.number_format($s->saldo_akhir_sistem, 0, ',', '.') : '-' }}</td>
                        <td class="py-3.5 px-4 tabular-nums text-ink-200">{{ $s->saldo_akhir_fisik !== null ? 'Rp '.number_format($s->saldo_akhir_fisik, 0, ',', '.') : '-' }}</td>
                        <td class="py-3.5 px-4 tabular-nums {{ $s->selisih === null ? 'text-ink-500' : (abs($s->selisih) < 0.01 ? 'text-up-mint font-semibold' : 'text-up-amber font-semibold') }}">
                            {{ $s->selisih !== null ? ($s->selisih >= 0 ? '+' : '-') . ' Rp ' . number_format(abs($s->selisih), 0, ',', '.') : '-' }}
                        </td>
                        <td class="py-3.5 px-4">
                            <span class="text-[10px] font-semibold capitalize {{ $s->sumber === 'carryover' ? 'text-up-primary bg-up-primary/10 px-2 py-0.5 rounded-full' : 'bg-white/5 text-ink-300 px-2 py-0.5 rounded-full' }}">
                                {{ $sumberLabel[$s->sumber] ?? $s->sumber }}
                            </span>
                        </td>
                        <td class="py-3.5 px-4"><x-prism.status-pill :status="$s->status" /></td>
                        <td class="py-3.5 px-4 text-ink-300 tabular-nums">{{ $s->dibuka_at ? \Carbon\Carbon::parse($s->dibuka_at)->format('d/m/Y H:i') : '-' }}</td>
                        <td class="py-3.5 px-4 text-ink-300 tabular-nums">{{ $s->ditutup_at ? \Carbon\Carbon::parse($s->ditutup_at)->format('d/m/Y H:i') : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="py-12 text-center text-ink-400">Belum ada riwayat sesi kas untuk cabang ini.</td></tr>
                @endforelse
            </x-prism.data-table>
        </x-prism.glass-card>
    @endif

    <!-- TAB: JURNAL -->
    @if($activeTab === 'jurnal')
        <div class="flex justify-between items-center gap-2 mb-4 flex-wrap">
            <!-- [F2-5] Export laporan jurnal (queue) -->
            @can('laporan.cabang')
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="exportLaporan('jurnal', 'xlsx')" class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-200 font-bold text-[11px] whitespace-nowrap cursor-pointer transition-all">Export Excel</button>
                    <button type="button" wire:click="exportLaporan('jurnal', 'csv')" class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-200 font-bold text-[11px] whitespace-nowrap cursor-pointer transition-all">Export CSV</button>
                </div>
            @endcan
            <x-prism.prism-button wire:click="openJurnalManualModal" size="sm" class="min-h-11">
                + Jurnal Manual
            </x-prism.prism-button>
        </div>

        <x-prism.data-table :headers="['No. Jurnal', 'Akun', 'Sumber', 'Deskripsi', 'Debit', 'Kredit', '']">
            @forelse($jurnals as $j)
                <tr class="hover:bg-white/[0.02] transition-colors text-xs">
                    <td class="py-3.5 px-4 font-mono font-bold text-white">{{ $j->no_jurnal }}</td>
                    <td class="py-3.5 px-4">
                        <span class="text-ink-100 font-medium">{{ $j->akun?->nama }}</span>
                        <span class="block text-[10px] text-ink-500 font-mono">{{ $j->akun?->kode }}</span>
                    </td>
                    <td class="py-3.5 px-4"><x-prism.status-pill :status="$j->sumber" /></td>
                    <td class="py-3.5 px-4 text-ink-300">{{ $j->deskripsi }}</td>
                    <td class="py-3.5 px-4 tabular-nums {{ $j->debit > 0 ? 'text-up-amber font-semibold' : 'text-ink-500' }}">{{ $j->debit > 0 ? 'Rp '.number_format($j->debit, 0, ',', '.') : '-' }}</td>
                    <td class="py-3.5 px-4 tabular-nums {{ $j->kredit > 0 ? 'text-up-mint font-semibold' : 'text-ink-500' }}">{{ $j->kredit > 0 ? 'Rp '.number_format($j->kredit, 0, ',', '.') : '-' }}</td>
                    <td class="py-3.5 px-4">
                        @can('lihat-audit-log')
                            <button wire:click="bukaRiwayat('jurnal', {{ $j->id }})" class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 font-bold text-[10px] cursor-pointer">Riwayat</button>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="py-12 text-center text-ink-400">
                        Belum ada jurnal. Jurnal dibuat otomatis dari transaksi POS, Servis, dan komisi reseller.
                    </td>
                </tr>
            @endforelse
        </x-prism.data-table>
    @endif

    <!-- TAB: COA -->
    @if($activeTab === 'coa')
        <div class="flex justify-end mb-4">
            <button wire:click="openCoaModal" class="px-4 py-2 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs cursor-pointer">+ Tambah Akun</button>
        </div>

        <x-prism.data-table :headers="['Kode', 'Nama Akun', 'Tipe', 'Kelompok', 'Saldo Normal']">
            @forelse($akunCoaList as $akun)
                <tr class="hover:bg-white/[0.02] transition-colors text-xs">
                    <td class="py-3.5 px-4 font-mono font-bold text-white">{{ $akun->kode }}</td>
                    <td class="py-3.5 px-4 text-ink-100 font-medium">{{ $akun->nama }}</td>
                    <td class="py-3.5 px-4">
                        <span class="text-[10px] font-semibold capitalize {{ $akun->tipe === 'aset' ? 'text-up-mint bg-up-mint/10 px-2 py-0.5 rounded-full' : ($akun->tipe === 'beban' ? 'text-up-red bg-up-red/10 px-2 py-0.5 rounded-full' : 'text-up-primary bg-up-primary/10 px-2 py-0.5 rounded-full') }}">
                            {{ $akun->tipe }}
                        </span>
                    </td>
                    <td class="py-3.5 px-4 text-ink-300">{{ $akun->kelompok }}</td>
                    <td class="py-3.5 px-4 text-ink-300">{{ $akun->saldo_normal }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-12 text-center text-ink-400">Belum ada akun COA.</td></tr>
            @endforelse
        </x-prism.data-table>
    @endif

    <!-- TAB: PIUTANG -->
    @if($activeTab === 'piutang')
        <!-- [F2-5] Export laporan piutang (queue) -->
        <div class="flex items-center gap-2 mb-4">
            @can('laporan.cabang')
                <button type="button" wire:click="exportLaporan('piutang', 'xlsx')" class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-200 font-bold text-[11px] whitespace-nowrap cursor-pointer transition-all">Export Excel</button>
                <button type="button" wire:click="exportLaporan('piutang', 'csv')" class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-200 font-bold text-[11px] whitespace-nowrap cursor-pointer transition-all">Export CSV</button>
            @endcan
        </div>
        <x-prism.data-table :headers="['No. Piutang', 'Pelanggan', 'Jumlah', 'Dibayar', 'Sisa', 'Jatuh Tempo', 'Status', '']">
            @forelse($piutangs as $p)
                <tr class="hover:bg-white/[0.02] transition-colors text-xs">
                    <td class="py-3.5 px-4 font-mono font-bold text-white">{{ $p->no_piutang }}</td>
                    <td class="py-3.5 px-4 text-ink-100">{{ $p->pelanggan?->nama }}</td>
                    <td class="py-3.5 px-4 tabular-nums text-white font-semibold">Rp {{ number_format($p->jumlah, 0, ',', '.') }}</td>
                    <td class="py-3.5 px-4 tabular-nums text-up-mint font-semibold">Rp {{ number_format($p->jumlah_dibayar, 0, ',', '.') }}</td>
                    <td class="py-3.5 px-4 tabular-nums font-bold text-up-amber">Rp {{ number_format($p->sisa, 0, ',', '.') }}</td>
                    <td class="py-3.5 px-4">
                        <span class="{{ $p->jatuh_tempo_lewat ? 'text-up-red font-bold' : 'text-ink-300' }} tabular-nums">
                            {{ $p->jatuh_tempo?->format('d/m/Y') ?? '-' }}
                        </span>
                        @if($p->jatuh_tempo_lewat)
                            <span class="block text-[9px] font-bold text-up-red bg-up-red/10 px-1.5 py-0.5 rounded mt-0.5">JATUH TEMPO</span>
                        @endif
                    </td>
                    <td class="py-3.5 px-4"><x-prism.status-pill :status="$p->status" /></td>
                    <td class="py-3.5 px-4">
                        @if($p->sisa > 0)
                            <button wire:click="openBayarPiutangModal({{ $p->id }})" class="px-3 py-1.5 rounded-lg bg-up-primary hover:bg-up-primary-dark text-white font-bold text-[11px] transition-all cursor-pointer">Terima Bayar</button>
                        @endif
                        @can('lihat-audit-log')
                            <button wire:click="bukaRiwayat('piutang', {{ $p->id }})" class="px-2.5 py-1 ml-1 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 font-bold text-[10px] cursor-pointer">Riwayat</button>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-12 text-center text-ink-400">Belum ada piutang. Transaksi POS metode "Kasbon" otomatis membuat piutang.</td></tr>
            @endforelse
        </x-prism.data-table>
    @endif

    <!-- TAB: UTANG -->
    @if($activeTab === 'utang')
        <!-- [F2-5] Export laporan utang (queue) -->
        <div class="flex items-center gap-2 mb-4">
            @can('laporan.cabang')
                <button type="button" wire:click="exportLaporan('utang', 'xlsx')" class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-200 font-bold text-[11px] whitespace-nowrap cursor-pointer transition-all">Export Excel</button>
                <button type="button" wire:click="exportLaporan('utang', 'csv')" class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-200 font-bold text-[11px] whitespace-nowrap cursor-pointer transition-all">Export CSV</button>
            @endcan
        </div>
        <x-prism.data-table :headers="['No. Utang', 'Kreditor', 'Referensi', 'Jumlah', 'Sisa', 'Jatuh Tempo', 'Status', '']">
            @forelse($utangs as $u)
                <tr class="hover:bg-white/[0.02] transition-colors text-xs">
                    <td class="py-3.5 px-4 font-mono font-bold text-white">{{ $u->no_utang }}</td>
                    <td class="py-3.5 px-4 text-ink-100 font-medium">{{ $u->kreditor_nama ?? $u->pelanggan?->nama ?? '-' }}</td>
                    <td class="py-3.5 px-4 text-ink-400">{{ $u->referensi_tipe }}</td>
                    <td class="py-3.5 px-4 tabular-nums text-white font-semibold">Rp {{ number_format($u->jumlah, 0, ',', '.') }}</td>
                    <td class="py-3.5 px-4 tabular-nums font-bold text-up-amber">Rp {{ number_format($u->sisa, 0, ',', '.') }}</td>
                    <td class="py-3.5 px-4 text-ink-300 tabular-nums">{{ $u->jatuh_tempo?->format('d/m/Y') ?? '-' }}</td>
                    <td class="py-3.5 px-4"><x-prism.status-pill :status="$u->status" /></td>
                    <td class="py-3.5 px-4">
                        @if($u->sisa > 0)
                            <button wire:click="openBayarUtangModal({{ $u->id }})" class="px-3 py-1.5 rounded-lg bg-up-mint hover:opacity-90 text-ink-950 font-bold text-[11px] transition-all cursor-pointer">Bayar</button>
                        @endif
                        @can('lihat-audit-log')
                            <button wire:click="bukaRiwayat('utang', {{ $u->id }})" class="px-2.5 py-1 ml-1 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 font-bold text-[10px] cursor-pointer">Riwayat</button>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-12 text-center text-ink-400">Belum ada utang. Komisi reseller yang disetujui otomatis jadi utang.</td></tr>
            @endforelse
        </x-prism.data-table>
    @endif

    <!-- MODAL: JURNAL MANUAL -->
    @if($showJurnalManual)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-3 sm:p-4">
            <x-prism.glass-card
                title="Jurnal Manual"
                subtitle="Pilih akun COA aktif, tentukan sisi, lalu cocokkan nominal debit dan kredit."
                class="w-full max-w-5xl max-h-[92vh] flex flex-col"
            >
                <x-slot:action>
                    <x-prism.prism-button
                        wire:click="tutupJurnalManual"
                        variant="ghost"
                        size="sm"
                        class="min-h-11 min-w-11"
                        aria-label="Tutup formulir jurnal manual"
                        title="Tutup"
                    >
                        ✕
                    </x-prism.prism-button>
                </x-slot:action>

                @php
                    $errorTanggal = $manualValidation['errors']['tanggal'][0] ?? null;
                    $errorDeskripsi = $manualValidation['errors']['deskripsi'][0] ?? null;
                @endphp

                <div class="min-h-0 flex-1 space-y-5 overflow-y-auto pr-1">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="jurnal-tanggal" class="block text-xs font-semibold text-ink-300 mb-1.5">Tanggal *</label>
                            <input
                                id="jurnal-tanggal"
                                type="date"
                                wire:model.live="manualTanggal"
                                aria-describedby="jurnal-error-tanggal"
                                @class(['glass-input w-full rounded-xl px-3 py-3 text-xs font-medium', 'border-up-red/60' => $errorTanggal])
                            />
                            @if($errorTanggal)
                                <p id="jurnal-error-tanggal" class="mt-1.5 text-[11px] font-medium text-up-red">{{ $errorTanggal }}</p>
                            @endif
                        </div>
                        <div>
                            <label for="jurnal-deskripsi" class="block text-xs font-semibold text-ink-300 mb-1.5">Deskripsi *</label>
                            <input
                                id="jurnal-deskripsi"
                                type="text"
                                wire:model.live.debounce.300ms="manualDeskripsi"
                                placeholder="Contoh: Penyesuaian biaya operasional"
                                aria-describedby="jurnal-error-deskripsi"
                                @class(['glass-input w-full rounded-xl px-3 py-3 text-xs font-medium', 'border-up-red/60' => $errorDeskripsi])
                            />
                            @if($errorDeskripsi)
                                <p id="jurnal-error-deskripsi" class="mt-1.5 text-[11px] font-medium text-up-red">{{ $errorDeskripsi }}</p>
                            @endif
                        </div>
                    </div>

                    <div>
                        <div class="mb-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <p class="text-xs font-bold text-ink-200">Baris Entri</p>
                                <p class="mt-0.5 text-[11px] text-ink-400">Akun debit hanya menampilkan akun bersaldo normal debit; sebaliknya untuk kredit.</p>
                            </div>
                            <x-prism.prism-button wire:click="addManualLine" size="sm" class="min-h-11 shrink-0">
                                + Tambah Baris
                            </x-prism.prism-button>
                        </div>

                        <div class="space-y-3">
                            @foreach($manualLines as $idx => $line)
                                @php
                                    $line = is_array($line) ? $line : [];
                                    $sisi = $line['sisi'] ?? 'debit';
                                    $akunPilihan = $akunJurnalOptions->where('saldo_normal', $sisi);
                                    $errorAkun = $manualValidation['errors']['baris'][$idx]['akun_kode'][0] ?? null;
                                    $errorJumlah = $manualValidation['errors']['baris'][$idx]['jumlah'][0] ?? null;
                                @endphp

                                <div
                                    wire:key="jurnal-line-{{ $line['uid'] ?? $idx }}"
                                    class="rounded-2xl border border-white/10 bg-white/[0.025] p-3 sm:p-4"
                                >
                                    <div class="grid grid-cols-1 gap-3 lg:grid-cols-[150px_minmax(240px,1fr)_180px_44px] lg:items-end">
                                        <div>
                                            <div class="mb-1.5 flex items-center justify-between gap-2">
                                                <span class="text-[10px] font-bold uppercase tracking-wider text-ink-400">Baris {{ $idx + 1 }}</span>
                                                <x-prism.status-pill :status="$sisi === 'debit' ? 'pending' : 'aktif'">
                                                    {{ ucfirst($sisi) }}
                                                </x-prism.status-pill>
                                            </div>
                                            <div class="inline-flex w-full rounded-xl border border-white/10 bg-black/20 p-1" role="group" aria-label="Sisi baris {{ $idx + 1 }}">
                                                <x-prism.prism-button
                                                    wire:click="setManualLineSide({{ $idx }}, 'debit')"
                                                    size="sm"
                                                    :aria-pressed="$sisi === 'debit' ? 'true' : 'false'"
                                                    class="min-h-11 flex-1"
                                                    @class([
                                                        '!border-up-amber/30 !bg-up-amber/15 !text-up-amber' => $sisi === 'debit',
                                                        '!border-transparent !bg-transparent !text-ink-400' => $sisi !== 'debit',
                                                    ])
                                                >
                                                    Debit
                                                </x-prism.prism-button>
                                                <x-prism.prism-button
                                                    wire:click="setManualLineSide({{ $idx }}, 'kredit')"
                                                    size="sm"
                                                    :aria-pressed="$sisi === 'kredit' ? 'true' : 'false'"
                                                    class="min-h-11 flex-1"
                                                    @class([
                                                        '!border-up-mint/30 !bg-up-mint/15 !text-up-mint' => $sisi === 'kredit',
                                                        '!border-transparent !bg-transparent !text-ink-400' => $sisi !== 'kredit',
                                                    ])
                                                >
                                                    Kredit
                                                </x-prism.prism-button>
                                            </div>
                                        </div>

                                        <div class="min-w-0">
                                            <label for="jurnal-akun-{{ $idx }}" class="block text-[11px] font-semibold text-ink-300 mb-1.5">Akun COA *</label>
                                            <select
                                                id="jurnal-akun-{{ $idx }}"
                                                wire:model.live="manualLines.{{ $idx }}.akun_kode"
                                                aria-describedby="jurnal-error-akun-{{ $idx }}"
                                                @class(['glass-input w-full rounded-xl px-3 py-3 text-xs font-medium', 'border-up-red/60' => $errorAkun])
                                            >
                                                <option value="" class="bg-ink-900">Pilih kode — nama akun</option>
                                                @foreach($akunPilihan as $akun)
                                                    <option value="{{ $akun->kode }}" class="bg-ink-900">
                                                        {{ $akun->kode }} — {{ $akun->nama }} ({{ ucfirst($akun->tipe) }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            @if($errorAkun)
                                                <p id="jurnal-error-akun-{{ $idx }}" class="mt-1.5 text-[11px] font-medium text-up-red">{{ $errorAkun }}</p>
                                            @endif
                                        </div>

                                        <div>
                                            <label for="jurnal-jumlah-{{ $idx }}" class="block text-[11px] font-semibold text-ink-300 mb-1.5">
                                                Nominal {{ ucfirst($sisi) }} (Rp) *
                                            </label>
                                            <input
                                                id="jurnal-jumlah-{{ $idx }}"
                                                type="text"
                                                inputmode="numeric"
                                                x-format-number
                                                wire:model.live="manualLines.{{ $idx }}.jumlah"
                                                placeholder="0"
                                                aria-describedby="jurnal-error-jumlah-{{ $idx }}"
                                                @class([
                                                    'glass-input w-full rounded-xl px-3 py-3 text-sm font-bold tabular-nums',
                                                    'text-up-amber' => $sisi === 'debit',
                                                    'text-up-mint' => $sisi === 'kredit',
                                                    'border-up-red/60' => $errorJumlah,
                                                ])
                                            />
                                            @if($errorJumlah)
                                                <p id="jurnal-error-jumlah-{{ $idx }}" class="mt-1.5 text-[11px] font-medium text-up-red">{{ $errorJumlah }}</p>
                                            @endif
                                        </div>

                                        <x-prism.prism-button
                                            wire:click="removeManualLine({{ $idx }})"
                                            variant="danger"
                                            size="sm"
                                            :disabled="count($manualLines) <= 2"
                                            class="min-h-11 min-w-11 lg:mb-0"
                                            aria-label="Hapus baris {{ $idx + 1 }}"
                                            title="{{ count($manualLines) > 2 ? 'Hapus baris' : 'Minimal dua baris' }}"
                                        >
                                            Hapus
                                        </x-prism.prism-button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @php
                        $totalDebit = $manualValidation['debit'];
                        $totalKredit = $manualValidation['kredit'];
                        $selisih = $manualValidation['selisih'];
                        $statusValidasi = $manualValidation['balanced'] ? 'aktif' : (($totalDebit + $totalKredit) > 0 ? 'ditolak' : 'pending');
                        $labelValidasi = $manualValidation['balanced']
                            ? 'Seimbang'
                            : (($totalDebit + $totalKredit) > 0
                                ? ($selisih > 0 ? 'Kurang Kredit' : 'Kurang Debit')
                                : 'Belum Ada Nominal');
                    @endphp

                    <x-prism.glass-card
                        title="Verifikasi Otomatis"
                        subtitle="Diperbarui setiap tanggal, akun, sisi, atau nominal berubah."
                        circuit="true"
                        padding="p-4"
                        class="border-white/10"
                    >
                        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <x-prism.status-pill :status="$statusValidasi" size="md">
                                {{ $labelValidasi }}
                            </x-prism.status-pill>
                            <p class="text-[11px] text-ink-400">
                                @if($manualValidation['can_submit'])
                                    Semua baris valid. Jurnal siap ditinjau dan diposting.
                                @else
                                    Selesaikan peringatan berikut sebelum jurnal dapat diposting.
                                @endif
                            </p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="rounded-xl border border-up-amber/20 bg-up-amber/10 p-3">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-up-amber">Total Debit</p>
                                <p class="mt-1 text-lg font-black tabular-nums text-up-amber">Rp {{ number_format($totalDebit, 0, ',', '.') }}</p>
                            </div>
                            <div class="rounded-xl border border-up-mint/20 bg-up-mint/10 p-3">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-up-mint">Total Kredit</p>
                                <p class="mt-1 text-lg font-black tabular-nums text-up-mint">Rp {{ number_format($totalKredit, 0, ',', '.') }}</p>
                            </div>
                            <div @class([
                                'rounded-xl border p-3',
                                'border-up-mint/20 bg-up-mint/10' => $manualValidation['balanced'],
                                'border-up-red/20 bg-up-red/10' => ! $manualValidation['balanced'] && ($totalDebit + $totalKredit) > 0,
                                'border-white/10 bg-white/[0.02]' => ! $manualValidation['balanced'] && ($totalDebit + $totalKredit) === 0,
                            ])>
                                <p @class([
                                    'text-[10px] font-bold uppercase tracking-wider',
                                    'text-up-mint' => $manualValidation['balanced'],
                                    'text-up-red' => ! $manualValidation['balanced'] && ($totalDebit + $totalKredit) > 0,
                                    'text-ink-400' => ! $manualValidation['balanced'] && ($totalDebit + $totalKredit) === 0,
                                ])>Selisih Debit − Kredit</p>
                                <p @class([
                                    'mt-1 text-lg font-black tabular-nums',
                                    'text-up-mint' => $manualValidation['balanced'],
                                    'text-up-red' => ! $manualValidation['balanced'] && ($totalDebit + $totalKredit) > 0,
                                    'text-ink-300' => ! $manualValidation['balanced'] && ($totalDebit + $totalKredit) === 0,
                                ])>
                                    {{ $selisih > 0 ? '+' : ($selisih < 0 ? '−' : '') }} Rp {{ number_format(abs($selisih), 0, ',', '.') }}
                                </p>
                            </div>
                        </div>

                        @if($manualValidation['messages'])
                            <div class="mt-4 rounded-xl border border-up-red/25 bg-up-red/10 p-3" role="alert" aria-live="polite">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-up-red">Yang perlu diperbaiki</p>
                                <ul class="mt-2 space-y-1.5 text-[11px] text-up-red">
                                    @foreach($manualValidation['messages'] as $message)
                                        <li class="flex gap-2">
                                            <span aria-hidden="true">•</span>
                                            <span>{{ $message }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </x-prism.glass-card>
                </div>

                <div class="mt-5 flex flex-col-reverse gap-3 border-t border-white/10 pt-4 sm:flex-row sm:items-center">
                    <x-prism.prism-button wire:click="tutupJurnalManual" variant="ghost" size="lg" class="sm:flex-1">
                        Batal
                    </x-prism.prism-button>
                    <x-prism.prism-button
                        wire:click="tinjauJurnalManual"
                        variant="mint"
                        size="lg"
                        class="sm:flex-[2]"
                        :disabled="! $manualValidation['can_submit']"
                        x-bind:disabled="!{{ $manualValidation['can_submit'] ? 'true' : 'false' }}"
                        wire:loading.attr="disabled"
                        wire:target="tinjauJurnalManual,simpanJurnalManual"
                    >
                        <span wire:loading.remove wire:target="tinjauJurnalManual,simpanJurnalManual">Tinjau &amp; Posting</span>
                        <span wire:loading wire:target="tinjauJurnalManual,simpanJurnalManual">Memeriksa…</span>
                    </x-prism.prism-button>
                </div>
            </x-prism.glass-card>
        </div>
    @endif

    {{-- ConfirmDialog Ute Prism untuk mencegah posting tak sengaja. --}}
    @if($showJurnalConfirmation)
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/85 backdrop-blur-sm p-4">
            <x-prism.glass-card
                title="Konfirmasi Posting Jurnal"
                subtitle="Periksa ringkasan terakhir sebelum jurnal masuk ke buku besar."
                class="w-full max-w-lg"
            >
                <div class="rounded-2xl border border-up-mint/25 bg-up-mint/10 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs font-semibold text-ink-300">Total Jurnal</span>
                        <x-prism.status-pill status="aktif">Siap Diposting</x-prism.status-pill>
                    </div>
                    <p class="mt-3 text-2xl font-black tabular-nums text-up-mint">
                        Rp {{ number_format($manualValidation['debit'], 0, ',', '.') }}
                    </p>
                    <p class="mt-1 text-[11px] text-ink-400">
                        Debit = Kredit · {{ count($manualLines) }} baris entri · {{ $manualTanggal ? \Carbon\Carbon::parse($manualTanggal)->format('d/m/Y') : '-' }}
                    </p>
                </div>

                <div class="mt-4 space-y-2">
                    @foreach($manualLines as $idx => $line)
                        @php
                            $line = is_array($line) ? $line : [];
                            $akunTerpilih = $akunJurnalOptions->firstWhere('kode', $line['akun_kode'] ?? '');
                            $sisi = $line['sisi'] ?? 'debit';
                        @endphp
                        <div class="flex items-center justify-between gap-3 rounded-xl border border-white/10 bg-white/[0.025] px-3 py-2.5">
                            <div class="min-w-0">
                                <p class="truncate text-xs font-semibold text-ink-100">{{ $akunTerpilih?->nama ?? 'Akun tidak ditemukan' }}</p>
                                <p class="font-mono text-[10px] text-ink-400">{{ $akunTerpilih?->kode ?? '-' }}</p>
                            </div>
                            <div class="text-right">
                                <x-prism.status-pill :status="$sisi === 'debit' ? 'pending' : 'aktif'">{{ ucfirst($sisi) }}</x-prism.status-pill>
                                <p class="mt-1 text-xs font-bold tabular-nums {{ $sisi === 'debit' ? 'text-up-amber' : 'text-up-mint' }}">
                                    Rp {{ number_format((float) ($line['jumlah'] ?? 0), 0, ',', '.') }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <p class="mt-4 text-[11px] leading-relaxed text-ink-400">
                    Setelah diposting, jurnal ini menjadi bagian dari buku besar cabang aktif dan tidak dapat diubah dari halaman ini.
                </p>

                <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row">
                    <x-prism.prism-button wire:click="batalTinjauJurnalManual" variant="ghost" size="lg" class="sm:flex-1">
                        Batal, Periksa Lagi
                    </x-prism.prism-button>
                    <x-prism.prism-button
                        wire:click="simpanJurnalManual"
                        variant="mint"
                        size="lg"
                        class="sm:flex-[2]"
                        wire:loading.attr="disabled"
                        wire:target="simpanJurnalManual"
                    >
                        Ya, Posting Sekarang
                    </x-prism.prism-button>
                </div>
            </x-prism.glass-card>
        </div>
    @endif

    <!-- MODAL: COA -->
    @if($showCoaModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Tambah Akun COA</h3>
                    <button wire:click="$set('showCoaModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kode *</label>
                            <input type="text" wire:model="coaForm.kode" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-mono font-medium" placeholder="520-06" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kelompok *</label>
                            <input type="text" wire:model="coaForm.kelompok" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="beban_operasional" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Akun *</label>
                        <input type="text" wire:model="coaForm.nama" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="Beban Admin Bank" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Tipe *</label>
                            <select wire:model="coaForm.tipe" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                @foreach(['aset' => 'Aset', 'kewajiban' => 'Kewajiban', 'ekuitas' => 'Ekuitas', 'pendapatan' => 'Pendapatan', 'beban' => 'Beban'] as $v => $l)
                                    <option value="{{ $v }}" class="bg-ink-900">{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Saldo Normal *</label>
                            <select wire:model="coaForm.saldo_normal" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="debit" class="bg-ink-900">Debit</option>
                                <option value="kredit" class="bg-ink-900">Kredit</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showCoaModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="simpanCoa" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer min-h-[44px]">Simpan Akun</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: BAYAR UTANG -->
    @if($showBayarUtangModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-sm glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Bayar Utang</h3>
                    <button wire:click="$set('showBayarUtangModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                @php $utangAktif = $utangs->firstWhere('id', $utangId); @endphp
                @if($utangAktif)
                    <div class="space-y-4">
                        <div class="p-3.5 rounded-xl bg-white/[0.03] border border-white/5">
                            <div class="flex justify-between text-xs mb-1.5">
                                <span class="text-ink-400">{{ $utangAktif->no_utang }} — {{ $utangAktif->kreditor_nama ?? '-' }}</span>
                                <x-prism.status-pill :status="$utangAktif->status" />
                            </div>
                            <div class="flex justify-between">
                                <span class="text-ink-400 text-xs">Sisa utang</span>
                                <span class="font-bold text-up-amber tabular-nums text-sm">Rp {{ number_format($utangAktif->sisa, 0, ',', '.') }}</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Jumlah Bayar (Rp) *</label>
                            <input type="text" inputmode="numeric" x-format-number wire:model.live="bayarUtangJumlah" step="500" min="1" class="w-full px-4 py-3 rounded-xl glass-input text-lg font-bold tabular-nums" />
                        </div>

                        <div class="flex gap-2 flex-wrap">
                            @foreach([50000, 100000, 500000, $utangAktif->sisa] as $quick)
                                @if($quick > 0)
                                    <button type="button" wire:click="$set('bayarUtangJumlah', {{ $quick }})" class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-xs text-ink-200 border border-white/5 font-semibold cursor-pointer">
                                        @if($quick == $utangAktif->sisa) Lunas @else {{ number_format($quick, 0, ',', '.') }} @endif
                                    </button>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                        <button wire:click="$set('showBayarUtangModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                        <button wire:click="bayarUtang" class="flex-1 py-3 rounded-xl bg-up-mint text-ink-950 font-bold text-xs cursor-pointer min-h-[44px]">Catat Pembayaran</button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- MODAL: TERIMA BAYAR PIUTANG -->
    @if($showBayarPiutangModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-sm glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Terima Pembayaran Piutang</h3>
                    <button wire:click="$set('showBayarPiutangModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                @php $piutangAktif = $piutangs->firstWhere('id', $piutangId); @endphp
                @if($piutangAktif)
                    <div class="space-y-4">
                        <div class="p-3.5 rounded-xl bg-white/[0.03] border border-white/5">
                            <div class="flex justify-between text-xs mb-1.5">
                                <span class="text-ink-400">{{ $piutangAktif->no_piutang }} — {{ $piutangAktif->pelanggan?->nama }}</span>
                                <x-prism.status-pill :status="$piutangAktif->status" />
                            </div>
                            <div class="flex justify-between">
                                <span class="text-ink-400 text-xs">Sisa piutang</span>
                                <span class="font-bold text-up-amber tabular-nums text-sm">Rp {{ number_format($piutangAktif->sisa, 0, ',', '.') }}</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Jumlah Diterima (Rp) *</label>
                            <input type="text" inputmode="numeric" x-format-number wire:model.live="bayarPiutangJumlah" step="500" min="1" class="w-full px-4 py-3 rounded-xl glass-input text-lg font-bold tabular-nums" />
                        </div>

                        <div class="flex gap-2 flex-wrap">
                            @foreach([50000, 100000, 500000, $piutangAktif->sisa] as $quick)
                                @if($quick > 0)
                                    <button type="button" wire:click="$set('bayarPiutangJumlah', {{ $quick }})" class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-xs text-ink-200 border border-white/5 font-semibold cursor-pointer">
                                        @if($quick == $piutangAktif->sisa) Lunas @else {{ number_format($quick, 0, ',', '.') }} @endif
                                    </button>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                        <button wire:click="$set('showBayarPiutangModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                        <button wire:click="bayarPiutang" class="flex-1 py-3 rounded-xl bg-up-mint text-ink-950 font-bold text-xs cursor-pointer min-h-[44px]">Catat Penerimaan</button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- [F1-4] Modal riwayat audit trail per entitas (jurnal / piutang / utang) --}}
    @include('partials.riwayat-modal')
</div>