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
            <x-prism.glass-card title="Neraca" :subtitle="'Sampai ' . $periodeSampai" circuit="true">
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
                        <div class="border-t border-white/10 mt-1.5 pt-1.5 flex justify-between text-xs font-bold">
                            <span class="text-ink-200">Total</span>
                            <span class="text-white tabular-nums">Rp {{ number_format($neraca['total_ekuitas'], 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                @php $balanceOk = abs($neraca['total_aset'] - ($neraca['total_kewajiban'] + $neraca['total_ekuitas'])) < 1; @endphp
                <div class="mt-4 p-3 rounded-xl {{ $balanceOk ? 'bg-up-mint/10 border border-up-mint/30' : 'bg-up-red/10 border border-up-red/30' }} flex items-center gap-2 text-xs">
                    <span class="w-2 h-2 rounded-full {{ $balanceOk ? 'bg-up-mint' : 'bg-up-red' }}"></span>
                    <span class="{{ $balanceOk ? 'text-up-mint' : 'text-up-red' }} font-semibold">
                        {{ $balanceOk ? 'Neraca Balance — Aset = Kewajiban + Ekuitas' : 'Neraca tidak balance! Periksa jurnal.' }}
                    </span>
                </div>
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
        <div class="flex justify-end mb-4">
            <button wire:click="openJurnalManualModal" class="px-4 py-2 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs cursor-pointer">+ Jurnal Manual</button>
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
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-xl glass-panel p-6 rounded-3xl relative max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Jurnal Manual</h3>
                    <button wire:click="$set('showJurnalManual', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="overflow-y-auto pr-1 flex-1 space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Tanggal *</label>
                            <input type="date" wire:model="manualTanggal" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Deskripsi *</label>
                            <input type="text" wire:model="manualDeskripsi" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="Penyesuaian..." />
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <label class="text-xs font-semibold text-ink-300">Baris Entri (debit = kredit)</label>
                            <button wire:click="addManualLine" class="text-up-primary hover:text-indigo-400 text-xs font-bold cursor-pointer">+ Tambah Baris</button>
                        </div>

                        @php
                            $totalDebitManual = collect($manualLines)->sum('debit');
                            $totalKreditManual = collect($manualLines)->sum('kredit');
                        @endphp

                        <div class="space-y-2">
                            @foreach($manualLines as $idx => $line)
                                <div class="flex gap-2 items-center">
                                    <div class="flex-1">
                                        <input type="text" wire:model="manualLines.{{ $idx }}.akun_kode" placeholder="Kode akun (ex: 110-01)" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-mono font-medium" />
                                    </div>
                                    <div class="w-28">
                                        <input type="text" inputmode="numeric" x-format-number wire:model.live="manualLines.{{ $idx }}.debit" step="500" min="0" placeholder="Debit" class="w-full px-2.5 py-2 rounded-xl glass-input text-xs font-bold tabular-nums text-up-amber" />
                                    </div>
                                    <div class="w-28">
                                        <input type="text" inputmode="numeric" x-format-number wire:model.live="manualLines.{{ $idx }}.kredit" step="500" min="0" placeholder="Kredit" class="w-full px-2.5 py-2 rounded-xl glass-input text-xs font-bold tabular-nums text-up-mint" />
                                    </div>
                                    <button wire:click="removeManualLine({{ $idx }})" class="p-2 text-up-red hover:bg-white/5 rounded-lg text-xs cursor-pointer">✕</button>
                                </div>
                            @endforeach
                        </div>

                        <div class="flex justify-between mt-3 p-3 rounded-xl bg-white/[0.02] border border-white/5 text-xs font-bold {{ $totalDebitManual === $totalKreditManual && $totalDebitManual > 0 ? 'text-up-mint' : (round($totalDebitManual,2) !== round($totalKreditManual,2) && ($totalDebitManual + $totalKreditManual) > 0 ? 'text-up-red' : 'text-ink-400') }}">
                            <span>Debit: Rp {{ number_format($totalDebitManual, 0, ',', '.') }}</span>
                            <span>Kredit: Rp {{ number_format($totalKreditManual, 0, ',', '.') }}</span>
                            @if(round($totalDebitManual,2) === round($totalKreditManual,2) && $totalDebitManual > 0)
                                <span>✓ Balance</span>
                            @elseif(($totalDebitManual + $totalKreditManual) > 0)
                                <span>✗ Belum balance</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-4">
                    <button wire:click="$set('showJurnalManual', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="simpanJurnalManual" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer min-h-[44px]">Posting Jurnal</button>
                </div>
            </div>
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