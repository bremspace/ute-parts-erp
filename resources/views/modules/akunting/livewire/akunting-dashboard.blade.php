<div class="space-y-6 w-full min-w-0 max-w-full overflow-hidden">
    <!-- Header + Periode (Responsive, No Overlap) -->
    <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3 border-b border-black/10 dark:border-white/5 pb-4">
        <!-- Navigation Tabs: Horizontal Scroll on Mobile, No Overlapping -->
        <div class="flex items-center gap-1.5 sm:gap-2 overflow-x-auto pb-1 max-w-full flex-nowrap scrollbar-none scroll-smooth touch-pan-x -mx-1 px-1 sm:mx-0 sm:px-0">
            @foreach([
                'laporan' => 'Laporan',
                'jurnal' => 'Jurnal',
                'buku-besar' => 'Buku Besar / Mutasi',
                'coa' => 'COA',
                'piutang' => 'Piutang',
                'utang' => 'Utang',
                'matching-kas' => 'Matching Kas',
                'diagnosa-neraca' => 'Diagnosa Neraca',
                'tutup-buku' => 'Tutup Buku',
                'neraca-saldo' => 'Neraca Saldo (4K)',
                'rekonsiliasi-bank' => 'Rekonsiliasi Bank',
            ] as $kode => $label)
                <a
                    href="{{ url('/app/akunting/' . $kode) }}"
                    wire:click.prevent="pilihTab('{{ $kode }}')"
                    class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer flex-shrink-0 inline-flex items-center justify-center {{ $activeTab === $kode ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-700 dark:text-ink-300 hover:text-ink-900 dark:hover:text-white border border-black/10 dark:border-white/10 hover:bg-black/10 dark:hover:bg-white/10' }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <!-- Filter & Periode Controls: Responsive Stack on Mobile -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full lg:w-auto flex-shrink-0">
            @if(auth()->user()?->can('laporan.konsolidasi'))
                <div class="flex items-center bg-black/5 dark:bg-white/5 p-1 rounded-xl border border-black/10 dark:border-white/5 text-xs font-semibold w-full sm:w-auto">
                    <button type="button" wire:click="setCakupanLaporan('cabang')"
                            class="flex-1 sm:flex-initial px-3 py-1.5 rounded-lg transition-all cursor-pointer text-center whitespace-nowrap {{ $cakupanLaporan === 'cabang' ? 'bg-up-primary text-white shadow-sm' : 'text-ink-600 dark:text-ink-400 hover:text-ink-900 dark:hover:text-ink-200' }}">
                        {{ session('cabang_nama', 'Cabang Ini') }}
                    </button>
                    <button type="button" wire:click="setCakupanLaporan('konsolidasi')"
                            class="flex-1 sm:flex-initial px-3 py-1.5 rounded-lg transition-all cursor-pointer flex items-center justify-center gap-1.5 whitespace-nowrap {{ $cakupanLaporan === 'konsolidasi' ? 'bg-up-accent text-white shadow-sm' : 'text-ink-600 dark:text-ink-400 hover:text-ink-900 dark:hover:text-ink-200' }}">
                        <span>Konsolidasi</span>
                    </button>
                </div>
            @endif

            <div class="flex items-center gap-2 w-full sm:w-auto">
                <label class="text-[11px] text-ink-400 font-medium hidden sm:inline">Periode</label>
                <div class="flex items-center gap-1.5 w-full sm:w-auto">
                    <input type="date" wire:model.live="periodeDari" class="flex-1 sm:w-32 px-2.5 py-1.5 rounded-xl glass-input text-xs font-medium min-h-[40px]" />
                    <span class="text-ink-400 text-xs">—</span>
                    <input type="date" wire:model.live="periodeSampai" class="flex-1 sm:w-32 px-2.5 py-1.5 rounded-xl glass-input text-xs font-medium min-h-[40px]" />
                </div>
                <button type="button" wire:click="segarkanLaporan" title="Sinkronkan & Segarkan Laporan"
                        class="p-2 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 border border-black/10 dark:border-white/10 text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-white transition-all cursor-pointer min-h-[40px] flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                </button>
            </div>
        </div>
    </div>

    <!-- TAB: LAPORAN -->
    @if($activeTab === 'laporan')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Laba Rugi -->
            <x-prism.glass-card title="Laba Rugi" :subtitle="($cakupanLaporan === 'konsolidasi' ? '[Konsolidasi Seluruh Cabang] ' : '') . 'Periode ' . $periodeDari . ' — ' . $periodeSampai" circuit="true">
                <div class="space-y-3">
                    <div>
                        <p class="text-[10px] text-up-mint uppercase font-bold mb-1.5">Pendapatan</p>
                        @forelse($labaRugi['pendapatan'] as $p)
                            @php
                                $pNama = is_array($p) ? ($p['nama'] ?? $p['akun'] ?? '') : (is_object($p) ? ($p->nama ?? $p->akun ?? '') : (string) $p);
                                $pKode = is_array($p) ? ($p['kode'] ?? '') : (is_object($p) ? ($p->kode ?? '') : '');
                                $pTotal = is_array($p) ? ($p['total'] ?? 0) : (is_object($p) ? ($p->total ?? 0) : 0);
                            @endphp
                            <div class="flex justify-between text-xs py-1 border-b border-black/5 dark:border-white/5">
                                <span class="text-ink-700 dark:text-ink-200">{{ $pNama }} @if($pKode)<span class="text-ink-500 font-mono">({{ $pKode }})</span>@endif</span>
                                <span class="font-bold text-ink-900 dark:text-white tabular-nums">Rp {{ number_format($pTotal, 0, ',', '.') }}</span>
                            </div>
                        @empty
                            <p class="text-[11px] text-ink-500 py-1">Belum ada pendapatan di periode ini.</p>
                        @endforelse
                        <div class="flex justify-between text-xs font-bold pt-1.5 mt-1">
                            <span class="text-up-mint">Total Pendapatan</span>
                            <span class="text-up-mint tabular-nums">Rp {{ number_format($labaRugi['total_pendapatan'] ?? 0, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div>
                        <p class="text-[10px] text-up-red uppercase font-bold mb-1.5">Beban</p>
                        @forelse($labaRugi['beban'] as $b)
                            @php
                                $bNama = is_array($b) ? ($b['nama'] ?? $b['akun'] ?? '') : (is_object($b) ? ($b->nama ?? $b->akun ?? '') : (string) $b);
                                $bKode = is_array($b) ? ($b['kode'] ?? '') : (is_object($b) ? ($b->kode ?? '') : '');
                                $bTotal = is_array($b) ? ($b['total'] ?? 0) : (is_object($b) ? ($b->total ?? 0) : 0);
                            @endphp
                            <div class="flex justify-between text-xs py-1 border-b border-black/5 dark:border-white/5">
                                <span class="text-ink-700 dark:text-ink-200">{{ $bNama }} @if($bKode)<span class="text-ink-500 font-mono">({{ $bKode }})</span>@endif</span>
                                <span class="font-bold text-ink-900 dark:text-white tabular-nums">Rp {{ number_format($bTotal, 0, ',', '.') }}</span>
                            </div>
                        @empty
                            <p class="text-[11px] text-ink-500 py-1">Belum ada beban di periode ini.</p>
                        @endforelse
                        <div class="flex justify-between text-xs font-bold pt-1.5 mt-1">
                            <span class="text-up-red">Total Beban</span>
                            <span class="text-up-red tabular-nums">Rp {{ number_format($labaRugi['total_beban'] ?? 0, 0, ',', '.') }}</span>
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
            <x-prism.glass-card title="Neraca" :subtitle="($cakupanLaporan === 'konsolidasi' ? '[Konsolidasi Seluruh Cabang] ' : '') . 'Saldo kumulatif s/d ' . $neraca['sampai_tanggal']" circuit="true">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="p-3 rounded-xl bg-black/[0.02] dark:bg-white/[0.02] border border-black/10 dark:border-white/5">
                        <p class="text-[10px] text-ink-500 dark:text-ink-400 uppercase font-bold mb-2">Aset</p>
                        @forelse($neraca['aset'] as $a)
                            @if(!is_array($a) && !is_object($a)) @continue @endif
                            @php
                                $aNama = is_array($a) ? ($a['nama'] ?? '') : ($a->nama ?? '');
                                $aSaldo = is_array($a) ? ($a['saldo'] ?? 0) : ($a->saldo ?? 0);
                            @endphp
                            <div class="flex justify-between text-[11px] py-0.5">
                                <span class="text-ink-600 dark:text-ink-300">{{ $aNama }}</span>
                                <span class="text-ink-900 dark:text-white tabular-nums font-semibold">{{ number_format($aSaldo, 0, ',', '.') }}</span>
                            </div>
                        @empty
                            <p class="text-[11px] text-ink-500">Kosong</p>
                        @endforelse
                        <div class="border-t border-black/10 dark:border-white/10 mt-1.5 pt-1.5 flex justify-between text-xs font-bold">
                            <span class="text-ink-700 dark:text-ink-200">Total</span>
                            <span class="text-ink-900 dark:text-white tabular-nums">Rp {{ number_format($neraca['total_aset'] ?? 0, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-black/[0.02] dark:bg-white/[0.02] border border-black/10 dark:border-white/5">
                        <p class="text-[10px] text-ink-500 dark:text-ink-400 uppercase font-bold mb-2">Kewajiban</p>
                        @forelse($neraca['kewajiban'] as $kw)
                            @if(!is_array($kw) && !is_object($kw)) @continue @endif
                            @php
                                $kwNama = is_array($kw) ? ($kw['nama'] ?? '') : ($kw->nama ?? '');
                                $kwSaldo = is_array($kw) ? ($kw['saldo'] ?? 0) : ($kw->saldo ?? 0);
                            @endphp
                            <div class="flex justify-between text-[11px] py-0.5">
                                <span class="text-ink-600 dark:text-ink-300">{{ $kwNama }}</span>
                                <span class="text-ink-900 dark:text-white tabular-nums font-semibold">{{ number_format($kwSaldo, 0, ',', '.') }}</span>
                            </div>
                        @empty
                            <p class="text-[11px] text-ink-500">Kosong</p>
                        @endforelse
                        <div class="border-t border-black/10 dark:border-white/10 mt-1.5 pt-1.5 flex justify-between text-xs font-bold">
                            <span class="text-ink-700 dark:text-ink-200">Total</span>
                            <span class="text-ink-900 dark:text-white tabular-nums">Rp {{ number_format($neraca['total_kewajiban'] ?? 0, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-black/[0.02] dark:bg-white/[0.02] border border-black/10 dark:border-white/5">
                        <p class="text-[10px] text-ink-500 dark:text-ink-400 uppercase font-bold mb-2">Ekuitas</p>
                        @forelse($neraca['ekuitas'] as $e)
                            @if(!is_array($e) && !is_object($e)) @continue @endif
                            @php
                                $eNama = is_array($e) ? ($e['nama'] ?? '') : ($e->nama ?? '');
                                $eSaldo = is_array($e) ? ($e['saldo'] ?? 0) : ($e->saldo ?? 0);
                            @endphp
                            <div class="flex justify-between text-[11px] py-0.5">
                                <span class="text-ink-600 dark:text-ink-300">{{ $eNama }}</span>
                                <span class="text-ink-900 dark:text-white tabular-nums font-semibold">{{ number_format($eSaldo, 0, ',', '.') }}</span>
                            </div>
                        @empty
                            <p class="text-[11px] text-ink-500">Kosong</p>
                        @endforelse
                        {{-- [B-10g] Akun pendapatan & beban belum ditutup ke Laba Ditahan, jadi
                             laba kumulatif ikut dihitung sebagai bagian ekuitas (sumber:
                             ExportLaporanService::neracaSaldo / API ACC-06). --}}
                        <div class="flex justify-between text-[11px] py-0.5">
                            <span class="text-ink-600 dark:text-ink-300">Laba Periode Berjalan</span>
                            <span class="text-ink-900 dark:text-white tabular-nums font-semibold">{{ number_format($neraca['laba_periode_berjalan'], 0, ',', '.') }}</span>
                        </div>
                        <div class="border-t border-black/10 dark:border-white/10 mt-1.5 pt-1.5 flex justify-between text-xs font-bold">
                            <span class="text-ink-700 dark:text-ink-200">Total</span>
                            <span class="text-ink-900 dark:text-white tabular-nums">Rp {{ number_format($neraca['total_ekuitas_bersama_laba'], 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                @php $balanceOk = (bool) $neraca['balance']; $selisihNeraca = (float) $neraca['selisih']; @endphp
                <div class="mt-4 p-3 rounded-xl {{ $balanceOk ? 'bg-up-mint/10 border border-up-mint/30' : 'bg-up-red/10 border border-up-red/30' }} flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="w-2 h-2 rounded-full {{ $balanceOk ? 'bg-up-mint' : 'bg-up-red' }}"></span>
                        <span class="{{ $balanceOk ? 'text-up-mint' : 'text-up-red' }} font-semibold">
                            {{ $balanceOk ? 'SEIMBANG — Aset = Kewajiban + Ekuitas + Laba Periode Berjalan' : 'TIDAK SEIMBANG — Aset ≠ Kewajiban + Ekuitas + Laba Periode Berjalan' }}
                        </span>
                        <span class="tabular-nums {{ $balanceOk ? 'text-up-mint' : 'text-up-red' }} font-semibold ml-1">
                            Selisih: {{ $selisihNeraca > 0 ? '+' : ($selisihNeraca < 0 ? '−' : '') }} Rp {{ number_format(abs($selisihNeraca), 0, ',', '.') }}
                        </span>
                    </div>
                    <button type="button" wire:click="jalankanDiagnosa" class="px-3 py-1.5 rounded-lg {{ $balanceOk ? 'bg-up-mint text-ink-950' : 'bg-up-red text-white' }} font-bold text-xs hover:opacity-90 transition cursor-pointer flex items-center justify-center gap-1.5 shadow-sm whitespace-nowrap">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                        <span>Diagnosa Cerdas</span>
                    </button>
                </div>
                <p class="mt-2 text-[11px] text-ink-400">
                    Angka saldo kumulatif sejak awal pembukuan s/d {{ $neraca['sampai_tanggal'] }} — bukan perubahan periode.
                </p>
            </x-prism.glass-card>

            <!-- Arus Kas (metode tidak langsung) -->
            <x-prism.glass-card title="Laporan Arus Kas" :subtitle="($cakupanLaporan === 'konsolidasi' ? '[Konsolidasi Seluruh Cabang] ' : '') . 'Metode Tidak Langsung — ' . $periodeDari . ' s.d. ' . $periodeSampai" circuit="true" class="lg:col-span-2">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Operasi -->
                    <div class="p-4 rounded-xl bg-black/[0.02] dark:bg-white/[0.02] border border-black/10 dark:border-white/5">
                        <p class="text-[10px] text-up-mint uppercase font-bold mb-2">Arus Kas Operasi</p>
                        <div class="space-y-1.5 text-xs">
                            <div class="flex justify-between">
                                <span class="text-ink-500 dark:text-ink-400">Laba bersih</span>
                                <span class="font-semibold text-ink-900 dark:text-white tabular-nums">Rp {{ number_format($arusKas['laba_bersih'], 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-ink-500 dark:text-ink-400">Kenaikan piutang</span>
                                <span class="font-semibold text-up-red tabular-nums">({{ number_format($arusKas['penyesuaian']['kenaikan_piutang'], 0, ',', '.') }})</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-ink-500 dark:text-ink-400">Kenaikan persediaan</span>
                                <span class="font-semibold text-up-red tabular-nums">({{ number_format($arusKas['penyesuaian']['kenaikan_persediaan'], 0, ',', '.') }})</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-ink-500 dark:text-ink-400">Kenaikan utang</span>
                                <span class="font-semibold text-up-mint tabular-nums">+{{ number_format($arusKas['penyesuaian']['kenaikan_utang'], 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between pt-2 border-t border-black/10 dark:border-white/10 font-bold mt-2">
                                <span class="text-up-mint">Kas dari operasi</span>
                                <span class="text-up-mint tabular-nums">Rp {{ number_format($arusKas['arus_kas_operasi'], 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Investasi -->
                    <div class="p-4 rounded-xl bg-black/[0.02] dark:bg-white/[0.02] border border-black/10 dark:border-white/5">
                        <p class="text-[10px] text-up-accent uppercase font-bold mb-2">Arus Kas Investasi</p>
                        <div class="space-y-1.5 text-xs">
                            <div class="flex justify-between">
                                <span class="text-ink-500 dark:text-ink-400">Pembelian aset tetap & peralatan</span>
                                <span class="font-semibold text-up-accent tabular-nums">Rp {{ number_format($arusKas['arus_kas_investasi'], 0, ',', '.') }}</span>
                            </div>
                            <p class="text-[10px] text-ink-500 mt-3">Beban pendapatan & pembelian aset jangka panjang periode ini.</p>
                        </div>
                    </div>

                    <!-- Pendanaan -->
                    <div class="p-4 rounded-xl bg-black/[0.02] dark:bg-white/[0.02] border border-black/10 dark:border-white/5">
                        <p class="text-[10px] text-up-primary uppercase font-bold mb-2">Arus Kas Pendanaan</p>
                        <div class="space-y-1.5 text-xs">
                            <div class="flex justify-between">
                                <span class="text-ink-500 dark:text-ink-400">Setoran modal / laba</span>
                                <span class="font-semibold text-up-primary tabular-nums">Rp {{ number_format($arusKas['arus_kas_pendanaan'], 0, ',', '.') }}</span>
                            </div>
                            <p class="text-[10px] text-ink-500 mt-3">Perubahan ekuitas & laba ditahan periode ini.</p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 p-4 rounded-2xl bg-black/[0.02] dark:bg-white/[0.03] border border-black/10 dark:border-white/5 flex justify-between items-center">
                    <span class="text-xs font-bold text-ink-700 dark:text-ink-200 uppercase tracking-wider">Kenaikan Kas Neto</span>
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
                    <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors text-xs">
                        <td class="py-3.5 px-4 font-mono font-bold text-ink-900 dark:text-white">#{{ $s->id }}</td>
                        <td class="py-3.5 px-4 text-ink-800 dark:text-ink-100 font-medium">{{ $s->kasir }}</td>
                        <td class="py-3.5 px-4 tabular-nums text-ink-900 dark:text-white">Rp {{ number_format($s->saldo_awal, 0, ',', '.') }}</td>
                        <td class="py-3.5 px-4 tabular-nums text-ink-700 dark:text-ink-200">{{ $s->saldo_akhir_sistem !== null ? 'Rp '.number_format($s->saldo_akhir_sistem, 0, ',', '.') : '-' }}</td>
                        <td class="py-3.5 px-4 tabular-nums text-ink-700 dark:text-ink-200">{{ $s->saldo_akhir_fisik !== null ? 'Rp '.number_format($s->saldo_akhir_fisik, 0, ',', '.') : '-' }}</td>
                        <td class="py-3.5 px-4 tabular-nums {{ $s->selisih === null ? 'text-ink-500' : (abs($s->selisih) < 0.01 ? 'text-up-mint font-semibold' : 'text-up-amber font-semibold') }}">
                            {{ $s->selisih !== null ? ($s->selisih >= 0 ? '+' : '-') . ' Rp ' . number_format(abs($s->selisih), 0, ',', '.') : '-' }}
                        </td>
                        <td class="py-3.5 px-4">
                            <span class="text-[10px] font-semibold capitalize {{ $s->sumber === 'carryover' ? 'text-up-primary bg-up-primary/10 px-2 py-0.5 rounded-full' : 'bg-black/5 dark:bg-white/5 text-ink-600 dark:text-ink-300 px-2 py-0.5 rounded-full' }}">
                                {{ $sumberLabel[$s->sumber] ?? $s->sumber }}
                            </span>
                        </td>
                        <td class="py-3.5 px-4"><x-prism.status-pill :status="$s->status" /></td>
                        <td class="py-3.5 px-4 text-ink-600 dark:text-ink-300 tabular-nums">{{ $s->dibuka_at ? \Carbon\Carbon::parse($s->dibuka_at)->format('d/m/Y H:i') : '-' }}</td>
                        <td class="py-3.5 px-4 text-ink-600 dark:text-ink-300 tabular-nums">{{ $s->ditutup_at ? \Carbon\Carbon::parse($s->ditutup_at)->format('d/m/Y H:i') : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="py-12 text-center text-ink-400">Belum ada riwayat sesi kas untuk cabang ini.</td></tr>
                @endforelse
            </x-prism.data-table>
        </x-prism.glass-card>
    @endif

    <!-- TAB: JURNAL -->
    @if($activeTab === 'jurnal')
        <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3 mb-4 flex-wrap">
            <div class="flex items-center gap-2 flex-wrap flex-1">
                <!-- Search input -->
                <div class="relative min-w-[200px] flex-1 max-w-xs">
                    <input type="text" wire:model.live.debounce.300ms="jurnalSearch" placeholder="Cari No. Jurnal / Deskripsi / Akun..."
                           class="w-full px-3 py-2 rounded-xl glass-input text-xs placeholder:text-ink-500 focus:border-up-primary focus:outline-none min-h-[40px]">
                </div>

                <!-- Filter Sumber -->
                <select wire:model.live="jurnalFilterSumber" class="px-3 py-2 rounded-xl glass-input text-xs font-medium focus:border-up-primary focus:outline-none min-h-[40px]">
                    <option value="">Semua Sumber</option>
                    <option value="manual">Manual</option>
                    <option value="pos">POS Kasir</option>
                    <option value="servis">Servis</option>
                    <option value="pembelian">Pembelian PO</option>
                    <option value="pembayaran">Pembayaran Subledger</option>
                    <option value="komisi">Komisi</option>
                    <option value="opname">Opname</option>
                    <option value="kas">Kas</option>
                </select>

                <!-- [F2-5] Export laporan jurnal (queue) -->
                @can('laporan.cabang')
                    <div class="flex items-center gap-1.5">
                        <button type="button" wire:click="exportLaporan('jurnal', 'xlsx')" class="px-3 py-2 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 border border-black/10 dark:border-white/10 text-ink-700 dark:text-ink-200 hover:text-ink-900 dark:hover:text-white font-bold text-[11px] whitespace-nowrap cursor-pointer transition-all min-h-[40px]">Export Excel</button>
                        <button type="button" wire:click="exportLaporan('jurnal', 'csv')" class="px-3 py-2 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 border border-black/10 dark:border-white/10 text-ink-700 dark:text-ink-200 hover:text-ink-900 dark:hover:text-white font-bold text-[11px] whitespace-nowrap cursor-pointer transition-all min-h-[40px]">Export CSV</button>
                    </div>
                @endcan
            </div>

            <x-prism.prism-button wire:click="openJurnalManualModal" size="sm" class="min-h-[40px]">
                + Jurnal Manual
            </x-prism.prism-button>
        </div>

        <x-prism.data-table :headers="['No. Jurnal', 'Akun', 'Sumber', 'Deskripsi', 'Debit', 'Kredit', '']">
            @forelse($jurnals as $j)
                <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors text-xs">
                    <td class="py-3.5 px-4 font-mono font-bold text-ink-900 dark:text-white">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span>{{ $j->no_jurnal }}</span>
                            @if($j->header?->status_approval === 'pending')
                                <span class="px-1.5 py-0.5 rounded bg-up-amber/15 border border-up-amber/30 text-up-amber text-[9px] font-bold" title="Menunggu Approval Maker-Checker">PENDING APPROVAL</span>
                            @elseif($j->header?->status_approval === 'ditolak')
                                <span class="px-1.5 py-0.5 rounded bg-up-red/15 border border-up-red/30 text-up-red text-[9px] font-bold" title="Ditolak Approver">DITOLAK</span>
                            @endif
                            @if($j->header?->is_reversed)
                                <span class="px-1.5 py-0.5 rounded bg-up-red/10 border border-up-red/20 text-up-red text-[9px] font-bold" title="Dibalik oleh {{ $j->header->reversed_by_no_jurnal }}">DIBALIK</span>
                            @elseif($j->header?->reversal_of_no_jurnal)
                                <span class="px-1.5 py-0.5 rounded bg-up-mint/10 border border-up-mint/20 text-up-mint text-[9px] font-bold" title="Pembalik dari {{ $j->header->reversal_of_no_jurnal }}">PEMBALIK</span>
                            @endif
                        </div>
                    </td>
                    <td class="py-3.5 px-4">
                        <button type="button" wire:click="lihatMutasiAkun({{ $j->akun_coa_id }})" class="text-left group cursor-pointer" title="Klik untuk lihat mutasi buku besar">
                            <span class="text-ink-800 dark:text-ink-100 font-medium group-hover:text-up-primary transition-colors">{{ $j->akun?->nama }}</span>
                            <span class="block text-[10px] text-ink-500 font-mono group-hover:text-up-primary/80 transition-colors">{{ $j->akun?->kode }} ↗</span>
                        </button>
                    </td>
                    <td class="py-3.5 px-4"><x-prism.status-pill :status="$j->sumber" /></td>
                    <td class="py-3.5 px-4 text-ink-600 dark:text-ink-300">
                        <div>{{ $j->deskripsi }}</div>
                        @if($j->header?->lampiran_path)
                            <div class="mt-1">
                                <a href="{{ asset('storage/' . $j->header->lampiran_path) }}" target="_blank" class="inline-flex items-center gap-1 text-[10px] text-up-primary hover:underline font-medium">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                    <span>Lampiran: {{ $j->header->lampiran_nama ?: 'Berkas Bukti' }}</span>
                                </a>
                            </div>
                        @endif
                    </td>
                    <td class="py-3.5 px-4 tabular-nums {{ $j->debit > 0 ? 'text-up-amber font-semibold' : 'text-ink-500' }}">{{ $j->debit > 0 ? 'Rp '.number_format($j->debit, 0, ',', '.') : '-' }}</td>
                    <td class="py-3.5 px-4 tabular-nums {{ $j->kredit > 0 ? 'text-up-mint font-semibold' : 'text-ink-500' }}">{{ $j->kredit > 0 ? 'Rp '.number_format($j->kredit, 0, ',', '.') : '-' }}</td>
                    <td class="py-3.5 px-4">
                        <div class="flex items-center gap-1.5">
                            <button wire:click="lihatMutasiAkun({{ $j->akun_coa_id }})" class="px-2.5 py-1 rounded-lg bg-up-primary/10 hover:bg-up-primary/20 text-up-primary border border-up-primary/25 font-bold text-[10px] cursor-pointer" title="Cek Mutasi Buku Besar">Mutasi</button>
                            @can('akunting.approve')
                                @if($j->header?->status_approval === 'pending')
                                    <button type="button" wire:click="setujuiJurnal('{{ $j->no_jurnal }}')" class="px-2.5 py-1 rounded-lg bg-up-mint/15 hover:bg-up-mint/25 text-up-mint border border-up-mint/30 font-bold text-[10px] cursor-pointer" title="Setujui Jurnal Ini (Maker-Checker)">Setujui</button>
                                    <button type="button" wire:click="tolakJurnal('{{ $j->no_jurnal }}')" class="px-2.5 py-1 rounded-lg bg-up-red/15 hover:bg-up-red/25 text-up-red border border-up-red/30 font-bold text-[10px] cursor-pointer" title="Tolak Jurnal Ini">Tolak</button>
                                @endif
                                @if(!($j->header?->is_reversed) && $j->sumber !== 'reversal')
                                    <button type="button" wire:click="bukaModalReversal('{{ $j->no_jurnal }}')" class="px-2.5 py-1 rounded-lg bg-up-red/10 hover:bg-up-red/20 text-up-red border border-up-red/25 font-bold text-[10px] cursor-pointer" title="Balik Jurnal Ini (Reversal / Storno)">Balik</button>
                                @endif
                            @endcan
                            @can('lihat-audit-log')
                                <button wire:click="bukaRiwayat('jurnal', {{ $j->id }})" class="px-2.5 py-1 rounded-lg bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 border border-black/10 dark:border-white/10 text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-white font-bold text-[10px] cursor-pointer">Riwayat</button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="py-12 text-center text-ink-400">
                        Belum ada jurnal yang sesuai filter pencarian.
                    </td>
                </tr>
            @endforelse
            <x-slot:pagination>{{ $jurnals->links() }}</x-slot:pagination>
        </x-prism.data-table>
    @endif

    <!-- TAB: BUKU BESAR / MUTASI -->
    @if($activeTab === 'buku-besar')
        <div class="space-y-4">
            <!-- Filter & Toolbar Buku Besar -->
            <x-prism.glass-card>
                <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 flex-1">
                        <!-- Akun Selector -->
                        <div class="min-w-[240px] flex-1">
                            <label class="block text-[11px] font-bold text-ink-500 dark:text-ink-400 mb-1">Pilih Akun (COA):</label>
                            <select wire:model.live="selectedBukuBesarAkunId" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium focus:border-up-primary focus:outline-none min-h-[40px]">
                                @foreach($akunJurnalOptions as $opt)
                                    <option value="{{ $opt->id }}">
                                        {{ $opt->kode }} — {{ $opt->nama }} ({{ ucfirst($opt->tipe) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Rentang Tanggal -->
                        <div class="flex items-center gap-2">
                            <div>
                                <label class="block text-[11px] font-bold text-ink-500 dark:text-ink-400 mb-1">Dari:</label>
                                <input type="date" wire:model.live="bukuBesarDari" class="px-2.5 py-1.5 rounded-xl glass-input text-xs font-mono focus:border-up-primary focus:outline-none min-h-[40px]">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-ink-500 dark:text-ink-400 mb-1">Sampai:</label>
                                <input type="date" wire:model.live="bukuBesarSampai" class="px-2.5 py-1.5 rounded-xl glass-input text-xs font-mono focus:border-up-primary focus:outline-none min-h-[40px]">
                            </div>
                        </div>
                    </div>

                    <!-- Export Buttons -->
                    @can('laporan.cabang')
                        <div class="flex items-center gap-2 self-end md:self-center">
                            <button type="button" wire:click="exportLaporan('buku_besar', 'xlsx')" class="px-3 py-2 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 border border-black/10 dark:border-white/10 text-ink-700 dark:text-ink-200 hover:text-ink-900 dark:hover:text-white font-bold text-[11px] whitespace-nowrap cursor-pointer transition-all min-h-[40px]">
                                Export Excel
                            </button>
                            <button type="button" wire:click="exportLaporan('buku_besar', 'csv')" class="px-3 py-2 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 border border-black/10 dark:border-white/10 text-ink-700 dark:text-ink-200 hover:text-ink-900 dark:hover:text-white font-bold text-[11px] whitespace-nowrap cursor-pointer transition-all min-h-[40px]">
                                Export CSV
                            </button>
                        </div>
                    @endcan
                </div>
            </x-prism.glass-card>

            @if($bukuBesarData)
                @php
                    $bAkun = $bukuBesarData['akun'];
                @endphp
                <!-- Info Akun & Rekap Saldo -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="p-3.5 rounded-2xl bg-black/[0.03] dark:bg-white/[0.03] border border-black/10 dark:border-white/10">
                        <span class="text-[10px] uppercase font-bold text-ink-500 dark:text-ink-400 block mb-0.5">Akun Terpilih</span>
                        <div class="font-bold text-ink-900 dark:text-white text-sm truncate">{{ $bAkun->nama }}</div>
                        <div class="font-mono text-xs text-up-primary mt-0.5">{{ $bAkun->kode }} · Saldo Normal: {{ strtoupper($bAkun->saldo_normal) }}</div>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-black/[0.03] dark:bg-white/[0.03] border border-black/10 dark:border-white/10">
                        <span class="text-[10px] uppercase font-bold text-ink-500 dark:text-ink-400 block mb-0.5">Saldo Awal (sebelum {{ \Carbon\Carbon::parse($bukuBesarData['dari'])->format('d/m/Y') }})</span>
                        <div class="font-bold text-ink-900 dark:text-white text-sm tabular-nums">
                            Rp {{ number_format($bukuBesarData['saldo_awal'], 0, ',', '.') }}
                        </div>
                        <span class="text-[10px] text-ink-500">Saldo kumulatif awal</span>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-black/[0.03] dark:bg-white/[0.03] border border-black/10 dark:border-white/10">
                        <span class="text-[10px] uppercase font-bold text-ink-500 dark:text-ink-400 block mb-0.5">Total Mutasi Periode</span>
                        <div class="text-xs space-y-0.5">
                            <div class="flex justify-between text-up-amber font-semibold tabular-nums">
                                <span>Debit (+):</span>
                                <span>Rp {{ number_format($bukuBesarData['total_debit'], 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between text-up-mint font-semibold tabular-nums">
                                <span>Kredit (-):</span>
                                <span>Rp {{ number_format($bukuBesarData['total_kredit'], 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-black/[0.03] dark:bg-white/[0.03] border border-black/10 dark:border-white/10">
                        <span class="text-[10px] uppercase font-bold text-ink-500 dark:text-ink-400 block mb-0.5">Saldo Akhir</span>
                        <div class="font-black text-base tabular-nums {{ $bukuBesarData['saldo_akhir'] >= 0 ? 'text-up-mint' : 'text-up-red' }}">
                            Rp {{ number_format($bukuBesarData['saldo_akhir'], 0, ',', '.') }}
                        </div>
                        <span class="text-[10px] text-ink-500">Per {{ \Carbon\Carbon::parse($bukuBesarData['sampai'])->format('d/m/Y') }}</span>
                    </div>
                </div>

                <!-- Tabel Baris Mutasi -->
                <x-prism.data-table :headers="['Tanggal', 'No. Jurnal', 'Sumber', 'Deskripsi / Keterangan', 'Debit', 'Kredit', 'Saldo Berjalan']">
                    @forelse($bukuBesarData['mutasi'] as $m)
                        <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors text-xs">
                            <td class="py-3 px-4 font-mono text-ink-600 dark:text-ink-300 whitespace-nowrap">{{ $m['tanggal'] }}</td>
                            <td class="py-3 px-4 font-mono font-bold text-ink-900 dark:text-white whitespace-nowrap">{{ $m['no_jurnal'] }}</td>
                            <td class="py-3 px-4"><x-prism.status-pill :status="$m['sumber']" /></td>
                            <td class="py-3 px-4 text-ink-700 dark:text-ink-200">
                                {{ $m['deskripsi'] }}
                                @if(!empty($m['user']))
                                    <span class="block text-[10px] text-ink-500">Oleh: {{ $m['user'] }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 tabular-nums {{ $m['debit'] > 0 ? 'text-up-amber font-semibold' : 'text-ink-500' }}">
                                {{ $m['debit'] > 0 ? 'Rp '.number_format($m['debit'], 0, ',', '.') : '-' }}
                            </td>
                            <td class="py-3 px-4 tabular-nums {{ $m['kredit'] > 0 ? 'text-up-mint font-semibold' : 'text-ink-500' }}">
                                {{ $m['kredit'] > 0 ? 'Rp '.number_format($m['kredit'], 0, ',', '.') : '-' }}
                            </td>
                            <td class="py-3 px-4 tabular-nums font-bold text-ink-900 dark:text-white">
                                Rp {{ number_format($m['saldo'], 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-ink-400">
                                Tidak ada mutasi transaksi pada periode ini untuk akun {{ $bAkun->nama }} ({{ $bAkun->kode }}).
                            </td>
                        </tr>
                    @endforelse
                </x-prism.data-table>
            @endif
        </div>
    @endif

    <!-- TAB: COA -->
    @if($activeTab === 'coa')
        <div class="flex justify-end mb-4">
            <button wire:click="openCoaModal" class="px-4 py-2 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs cursor-pointer">+ Tambah Akun</button>
        </div>

        <x-prism.data-table :headers="['Kode', 'Nama Akun', 'Tipe', 'Kelompok', 'Saldo Normal', 'Aksi']">
            @forelse($akunCoaList as $akun)
                <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors text-xs">
                    <td class="py-3.5 px-4 font-mono font-bold text-ink-900 dark:text-white">{{ $akun->kode }}</td>
                    <td class="py-3.5 px-4 text-ink-800 dark:text-ink-100 font-medium">
                        {{ $akun->nama }}
                        @if(! $akun->is_active)
                            <span class="block text-[9px] font-bold text-up-red bg-up-red/10 px-1.5 py-0.5 rounded mt-0.5 w-fit">NONAKTIF</span>
                        @endif
                    </td>
                    <td class="py-3.5 px-4">
                        <span class="text-[10px] font-semibold capitalize {{ $akun->tipe === 'aset' ? 'text-up-mint bg-up-mint/10 px-2 py-0.5 rounded-full' : ($akun->tipe === 'beban' ? 'text-up-red bg-up-red/10 px-2 py-0.5 rounded-full' : 'text-up-primary bg-up-primary/10 px-2 py-0.5 rounded-full') }}">
                            {{ $akun->tipe }}
                        </span>
                    </td>
                    <td class="py-3.5 px-4 text-ink-600 dark:text-ink-300">{{ $akun->kelompok }}</td>
                    <td class="py-3.5 px-4 text-ink-600 dark:text-ink-300">{{ $akun->saldo_normal }}</td>
                    <td class="py-3.5 px-4">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <button type="button" wire:click="lihatMutasiAkun({{ $akun->id }})" class="px-2.5 py-1.5 rounded-lg bg-up-primary/10 hover:bg-up-primary/20 text-up-primary border border-up-primary/25 font-bold text-[10px] cursor-pointer active:scale-[0.97]" title="Lihat Mutasi Buku Besar Akun Ini">Mutasi</button>
                            @can('akunting.edit')
                                <button type="button" wire:click="openEditCoaModal({{ $akun->id }})" class="px-2.5 py-1.5 rounded-lg bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 border border-black/10 dark:border-white/10 text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-white font-bold text-[10px] cursor-pointer active:scale-[0.97]" title="Edit Akun COA">Edit</button>
                            @endcan
                            @if(isSuperAdminOrOwner())
                                <button type="button" wire:click="hapusCoa({{ $akun->id }})" wire:confirm="Yakin ingin menghapus akun COA '{{ $akun->kode }} — {{ $akun->nama }}'? Jika akun memiliki riwayat jurnal atau akun anak, status akan dinonaktifkan." class="px-2.5 py-1.5 rounded-lg bg-up-red/10 hover:bg-up-red/20 text-up-red border border-up-red/30 font-bold text-[10px] cursor-pointer whitespace-nowrap active:scale-[0.97]" title="Hapus Akun COA">Hapus</button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-12 text-center text-ink-400">Belum ada akun COA.</td></tr>
            @endforelse

            <x-slot:pagination>{{ $akunCoaList->links() }}</x-slot:pagination>
        </x-prism.data-table>
    @endif

    <!-- TAB: PIUTANG -->
    @if($activeTab === 'piutang')
        <!-- [F2-5] Export laporan piutang (queue) -->
        <div class="flex items-center gap-2 mb-4">
            @can('laporan.cabang')
                <button type="button" wire:click="exportLaporan('piutang', 'xlsx')" class="px-3 py-2 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 border border-black/10 dark:border-white/10 text-ink-700 dark:text-ink-200 hover:text-ink-900 dark:hover:text-white font-bold text-[11px] whitespace-nowrap cursor-pointer transition-all">Export Excel</button>
                <button type="button" wire:click="exportLaporan('piutang', 'csv')" class="px-3 py-2 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 border border-black/10 dark:border-white/10 text-ink-700 dark:text-ink-200 hover:text-ink-900 dark:hover:text-white font-bold text-[11px] whitespace-nowrap cursor-pointer transition-all">Export CSV</button>
            @endcan
        </div>
        <x-prism.data-table :headers="['No. Piutang', 'Pelanggan', 'Jumlah', 'Dibayar', 'Sisa', 'Jatuh Tempo', 'Status', '']">
            @forelse($piutangs as $p)
                <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors text-xs">
                    <td class="py-3.5 px-4 font-mono font-bold text-ink-900 dark:text-white">{{ $p->no_piutang }}</td>
                    <td class="py-3.5 px-4 text-ink-800 dark:text-ink-100">{{ $p->pelanggan?->nama }}</td>
                    <td class="py-3.5 px-4 tabular-nums text-ink-900 dark:text-white font-semibold">Rp {{ number_format($p->jumlah, 0, ',', '.') }}</td>
                    <td class="py-3.5 px-4 tabular-nums text-up-mint font-semibold">Rp {{ number_format($p->jumlah_dibayar, 0, ',', '.') }}</td>
                    <td class="py-3.5 px-4 tabular-nums font-bold text-up-amber">Rp {{ number_format($p->sisa, 0, ',', '.') }}</td>
                    <td class="py-3.5 px-4">
                        <span class="{{ $p->jatuh_tempo_lewat ? 'text-up-red font-bold' : 'text-ink-600 dark:text-ink-300' }} tabular-nums">
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
                            <button wire:click="bukaRiwayat('piutang', {{ $p->id }})" class="px-2.5 py-1 ml-1 rounded-lg bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 border border-black/10 dark:border-white/10 text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-white font-bold text-[10px] cursor-pointer">Riwayat</button>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-12 text-center text-ink-400">Belum ada piutang. Transaksi POS metode "Kasbon" otomatis membuat piutang.</td></tr>
            @endforelse
        </x-prism.data-table>
    @endif

    <!-- TAB: UTANG -->
    @if($activeTab === 'utang')
        <!-- [F2-5] Export laporan utang (queue) -->
        <div class="flex items-center gap-2 mb-4">
            @can('laporan.cabang')
                <button type="button" wire:click="exportLaporan('utang', 'xlsx')" class="px-3 py-2 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 border border-black/10 dark:border-white/10 text-ink-700 dark:text-ink-200 hover:text-ink-900 dark:hover:text-white font-bold text-[11px] whitespace-nowrap cursor-pointer transition-all">Export Excel</button>
                <button type="button" wire:click="exportLaporan('utang', 'csv')" class="px-3 py-2 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 border border-black/10 dark:border-white/10 text-ink-700 dark:text-ink-200 hover:text-ink-900 dark:hover:text-white font-bold text-[11px] whitespace-nowrap cursor-pointer transition-all">Export CSV</button>
            @endcan
        </div>
        <x-prism.data-table :headers="['No. Utang', 'Kreditor', 'Referensi', 'Jumlah', 'Sisa', 'Jatuh Tempo', 'Status', '']">
            @forelse($utangs as $u)
                <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors text-xs">
                    <td class="py-3.5 px-4 font-mono font-bold text-ink-900 dark:text-white">{{ $u->no_utang }}</td>
                    <td class="py-3.5 px-4 text-ink-800 dark:text-ink-100 font-medium">{{ $u->kreditor_nama ?? $u->pelanggan?->nama ?? '-' }}</td>
                    <td class="py-3.5 px-4 text-ink-500 dark:text-ink-400">{{ $u->referensi_tipe }}</td>
                    <td class="py-3.5 px-4 tabular-nums text-ink-900 dark:text-white font-semibold">Rp {{ number_format($u->jumlah, 0, ',', '.') }}</td>
                    <td class="py-3.5 px-4 tabular-nums font-bold text-up-amber">Rp {{ number_format($u->sisa, 0, ',', '.') }}</td>
                    <td class="py-3.5 px-4 text-ink-600 dark:text-ink-300 tabular-nums">{{ $u->jatuh_tempo?->format('d/m/Y') ?? '-' }}</td>
                    <td class="py-3.5 px-4"><x-prism.status-pill :status="$u->status" /></td>
                    <td class="py-3.5 px-4">
                        @if($u->sisa > 0)
                            <button wire:click="openBayarUtangModal({{ $u->id }})" class="px-3 py-1.5 rounded-lg bg-up-mint hover:opacity-90 text-ink-950 font-bold text-[11px] transition-all cursor-pointer">Bayar</button>
                        @endif
                        @can('lihat-audit-log')
                            <button wire:click="bukaRiwayat('utang', {{ $u->id }})" class="px-2.5 py-1 ml-1 rounded-lg bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 border border-black/10 dark:border-white/10 text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-white font-bold text-[10px] cursor-pointer">Riwayat</button>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-12 text-center text-ink-400">Belum ada utang. Komisi reseller yang disetujui otomatis jadi utang.</td></tr>
            @endforelse
        </x-prism.data-table>
    @endif

    <!-- TAB: MATCHING KAS -->
    @if($activeTab === 'matching-kas')
        <div class="space-y-6">
            <!-- Header matching kas -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-ink-900 dark:text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-up-mint" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>Monitoring &amp; Pencocokan Kas Real vs Aplikasi</span>
                    </h2>
                    <p class="text-xs text-ink-500 dark:text-ink-400 mt-1">Bandingkan saldo kas buku besar (GL) aplikasi dengan uang fisik aktual di laci/brankas atau saldo mutasi rekening koran bank.</p>
                </div>
                <div>
                    <button type="button" wire:click="bukaFormMatching"
                        class="px-4 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs shadow-md shadow-up-primary/25 transition flex items-center gap-2 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        <span>+ Rekonsiliasi / Opname Kas</span>
                    </button>
                </div>
            </div>

            <!-- Overview Akun Kas & Bank -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($kasAccounts as $acc)
                    <div class="p-4 rounded-xl bg-black/[0.02] dark:bg-white/[0.03] border border-black/10 dark:border-white/10 hover:border-black/20 dark:hover:border-white/20 transition space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-xs font-bold text-up-accent">{{ $acc['kode'] }}</span>
                            <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded-md bg-black/5 dark:bg-white/5 text-ink-600 dark:text-ink-300">{{ $acc['kelompok'] }}</span>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-ink-900 dark:text-white">{{ $acc['nama'] }}</h3>
                            <p class="text-xl font-bold text-ink-900 dark:text-white tabular-nums mt-1">Rp {{ number_format($acc['saldo'], 0, ',', '.') }}</p>
                            <span class="text-[11px] text-ink-500 dark:text-ink-400">Saldo Buku Besar (GL) Sistem</span>
                        </div>
                        <div class="pt-2 border-t border-black/5 dark:border-white/5 flex justify-end">
                            <button type="button" wire:click="bukaFormMatching({{ $acc['id'] }})"
                                class="text-xs text-up-mint hover:text-up-mint/80 font-bold transition flex items-center gap-1 cursor-pointer">
                                <span>Cocokkan Fisik</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Tabel Riwayat Pencocokan Kas Real -->
            <x-prism.glass-card title="Riwayat Pencocokan &amp; Opname Kas Real" subtitle="Daftar audit rekonsiliasi kas fisik vs catatan buku besar sistem" circuit="true">
                <x-prism.dual-scroll>
                    <table class="w-full text-xs text-left min-w-[780px] whitespace-nowrap">
                        <thead>
                            <tr class="border-b border-black/10 dark:border-white/10 text-ink-500 dark:text-ink-400 font-semibold uppercase text-[10px]">
                                <th class="py-3 px-4">Tanggal</th>
                                <th class="py-3 px-4">Cabang</th>
                                <th class="py-3 px-4">Akun Kas / Bank</th>
                                <th class="py-3 px-4">Auditor / Petugas</th>
                                <th class="py-3 px-4 text-right">Saldo Sistem (GL)</th>
                                <th class="py-3 px-4 text-right">Saldo Fisik (Real)</th>
                                <th class="py-3 px-4 text-right">Selisih</th>
                                <th class="py-3 px-4 text-center">Status</th>
                                <th class="py-3 px-4">Catatan</th>
                                <th class="py-3 px-4 text-center">Tindak Lanjut</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-black/5 dark:divide-white/5">
                            @forelse($kasMatchings as $m)
                                <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition">
                                    <td class="py-3.5 px-4 font-mono text-ink-700 dark:text-ink-200">{{ $m->tanggal->format('d/m/Y') }}</td>
                                    <td class="py-3.5 px-4 text-ink-600 dark:text-ink-300 font-medium whitespace-nowrap">{{ $m->cabang?->nama ?? '-' }}</td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-bold text-ink-900 dark:text-white">{{ $m->akun?->nama ?? '-' }}</div>
                                        <div class="text-[10px] text-ink-500 dark:text-ink-400 font-mono">{{ $m->akun?->kode ?? '-' }}</div>
                                    </td>
                                    <td class="py-3.5 px-4 text-ink-600 dark:text-ink-300">{{ $m->user?->name ?? 'Sistem' }}</td>
                                    <td class="py-3.5 px-4 text-right tabular-nums text-ink-900 dark:text-white">Rp {{ number_format($m->saldo_sistem, 0, ',', '.') }}</td>
                                    <td class="py-3.5 px-4 text-right tabular-nums text-ink-900 dark:text-white font-semibold">Rp {{ number_format($m->saldo_fisik, 0, ',', '.') }}</td>
                                    <td class="py-3.5 px-4 text-right tabular-nums font-bold {{ abs($m->selisih) < 0.01 ? 'text-up-mint' : ($m->selisih > 0 ? 'text-up-amber' : 'text-up-red') }}">
                                        {{ $m->selisih > 0 ? '+' : ($m->selisih < 0 ? '−' : '') }} Rp {{ number_format(abs($m->selisih), 0, ',', '.') }}
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        @php
                                            $statusBadge = match($m->status) {
                                                'cocok' => 'bg-up-mint/15 text-up-mint border border-up-mint/30',
                                                'disesuaikan' => 'bg-up-primary/15 text-up-primary border border-up-primary/30',
                                                default => 'bg-up-red/15 text-up-red border border-up-red/30'
                                            };
                                        @endphp
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $statusBadge }}">
                                            {{ $m->status === 'cocok' ? 'Cocok' : ($m->status === 'disesuaikan' ? 'Disesuaikan' : 'Selisih') }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-ink-600 dark:text-ink-400 max-w-xs truncate">{{ $m->catatan ?: '-' }}</td>
                                    <td class="py-3.5 px-4 text-center">
                                        @if($m->status === 'selisih' && ! $m->jurnal_id)
                                            <button type="button" wire:click="postingPenyesuaianKas({{ $m->id }})"
                                                class="px-2.5 py-1 rounded-lg bg-up-amber hover:bg-up-amber/90 text-ink-950 font-bold text-[10px] transition cursor-pointer shadow-sm">
                                                Posting Penyesuaian
                                            </button>
                                        @elseif($m->jurnal_id)
                                            <span class="text-[10px] text-up-mint font-mono font-semibold">✓ Jurnal #{{ $m->jurnal_id }}</span>
                                        @else
                                            <span class="text-[10px] text-ink-500">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="py-10 text-center text-ink-400">
                                        Belum ada riwayat pencocokan kas. Klik <strong>"+ Rekonsiliasi / Opname Kas"</strong> untuk mencocokkan saldo kas fisik dengan sistem.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-prism.dual-scroll>
            </x-prism.glass-card>
        </div>
    @endif

    <!-- TAB: DIAGNOSA CERDAS NERACA -->
    @if($activeTab === 'diagnosa-neraca')
        <div class="space-y-6">
            <!-- Header diagnosa -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-up-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                        <span>Sistem Kecerdasan Diagnosa Neraca &amp; Integritas Transaksi</span>
                    </h2>
                    <p class="text-xs text-ink-400 mt-1">Audit otomatis seluruh transaksi (Jurnal, POS, Servis, WMS/PO, HR Payroll, Kas Laci) untuk mendeteksi penyebab ketidakseimbangan neraca dan membagikannya ke departemen terkait.</p>
                </div>
                <div>
                    <button type="button" wire:click="jalankanDiagnosa"
                        class="px-4 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs shadow-md shadow-up-primary/25 transition flex items-center gap-2 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                        <span>Jalankan Ulang Diagnosa</span>
                    </button>
                </div>
            </div>

            @if($hasilDiagnosa)
                <!-- Kartu Skor & Ringkasan Cerdas -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="p-4 rounded-xl {{ $hasilDiagnosa['is_balance'] ? 'bg-up-mint/10 border border-up-mint/30' : 'bg-up-red/10 border border-up-red/30' }} space-y-1">
                        <span class="text-[10px] uppercase font-bold tracking-wider {{ $hasilDiagnosa['is_balance'] ? 'text-up-mint' : 'text-up-red' }}">Status Neraca Saldo</span>
                        <div class="text-xl font-bold {{ $hasilDiagnosa['is_balance'] ? 'text-up-mint' : 'text-up-red' }}">
                            {{ $hasilDiagnosa['is_balance'] ? 'SEIMBANG' : 'TIDAK SEIMBANG' }}
                        </div>
                        <p class="text-xs text-ink-300 tabular-nums">
                            Selisih: {{ $hasilDiagnosa['selisih'] > 0 ? '+' : ($hasilDiagnosa['selisih'] < 0 ? '−' : '') }} Rp {{ number_format(abs($hasilDiagnosa['selisih']), 0, ',', '.') }}
                        </p>
                    </div>

                    <div class="p-4 rounded-xl bg-white/[0.03] border border-white/10 space-y-1">
                        <span class="text-[10px] uppercase font-bold tracking-wider text-ink-400">Skor Kesehatan Akuntansi</span>
                        <div class="text-2xl font-bold tabular-nums {{ $hasilDiagnosa['skor_kesehatan'] >= 90 ? 'text-up-mint' : ($hasilDiagnosa['skor_kesehatan'] >= 70 ? 'text-up-amber' : 'text-up-red') }}">
                            {{ $hasilDiagnosa['skor_kesehatan'] }}%
                        </div>
                        <p class="text-[11px] text-ink-400">Integritas double-entry &amp; sinkronisasi</p>
                    </div>

                    <div class="p-4 rounded-xl bg-white/[0.03] border border-white/10 space-y-1">
                        <span class="text-[10px] uppercase font-bold tracking-wider text-ink-400">Temuan Anomali</span>
                        <div class="text-2xl font-bold tabular-nums text-white">
                            {{ $hasilDiagnosa['total_temuan'] }} <span class="text-xs font-normal text-ink-400">isu</span>
                        </div>
                        <p class="text-[11px] text-ink-400">
                            <span class="text-up-red font-bold">{{ $hasilDiagnosa['jumlah_kritis'] }} Kritis</span> • 
                            <span class="text-up-amber font-bold">{{ $hasilDiagnosa['jumlah_peringatan'] }} Peringatan</span>
                        </p>
                    </div>

                    <div class="p-4 rounded-xl bg-white/[0.03] border border-white/10 space-y-1">
                        <span class="text-[10px] uppercase font-bold tracking-wider text-ink-400">Waktu Analisis</span>
                        <div class="text-sm font-bold text-white mt-1">{{ $hasilDiagnosa['waktu_analisis'] }}</div>
                        <p class="text-[11px] text-ink-400">Snapshot transaksi real-time</p>
                    </div>
                </div>

                <!-- Kesimpulan Naratif AI / Audit Engine -->
                <div class="p-4 rounded-xl border {{ $hasilDiagnosa['is_balance'] ? 'border-up-mint/30 bg-up-mint/[0.04]' : 'border-up-red/30 bg-up-red/[0.04]' }} space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold uppercase tracking-wider {{ $hasilDiagnosa['is_balance'] ? 'text-up-mint' : 'text-up-red' }}">
                            💡 Analisa Otomatis Sistem Cerdas:
                        </span>
                    </div>
                    <p class="text-xs text-ink-200 leading-relaxed font-medium">
                        {{ $hasilDiagnosa['kesimpulan_ai'] }}
                    </p>
                </div>

                <!-- Rangkuman Tanggung Jawab Departemen / Bagian Terkait -->
                <div>
                    <h3 class="text-xs uppercase font-bold tracking-wider text-ink-500 dark:text-ink-400 mb-3">Distribusi Tindak Lanjut per Bagian / Departemen</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                        @foreach($hasilDiagnosa['ringkasan_bagian'] as $namaBagian => $dataBagian)
                            <div class="p-3.5 rounded-xl bg-black/[0.02] dark:bg-white/[0.02] border border-black/10 dark:border-white/5 space-y-2">
                                <div class="text-xs font-bold text-ink-900 dark:text-white truncate" title="{{ $namaBagian }}">{{ $namaBagian }}</div>
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-ink-500 dark:text-ink-400">Total Isu:</span>
                                    <span class="font-bold tabular-nums {{ $dataBagian['total'] > 0 ? 'text-up-amber' : 'text-up-mint' }}">{{ $dataBagian['total'] }}</span>
                                </div>
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-ink-500 dark:text-ink-400">Kritis:</span>
                                    <span class="font-bold tabular-nums {{ $dataBagian['kritis'] > 0 ? 'text-up-red' : 'text-ink-400' }}">{{ $dataBagian['kritis'] }}</span>
                                </div>
                                <div class="pt-1.5 border-t border-black/5 dark:border-white/5 text-[11px] flex justify-between">
                                    <span class="text-ink-500">Dampak:</span>
                                    <span class="tabular-nums font-semibold text-ink-700 dark:text-ink-200">Rp {{ number_format($dataBagian['dampak'], 0, ',', '.') }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Daftar Rincian Temuan & Rekomendasi Tindak Lanjut -->
                <x-prism.glass-card title="Daftar Temuan, Akar Penyebab &amp; Solusi Rekomendasi" subtitle="Daftar masalah yang perlu ditindaklanjuti untuk menjaga keseimbangan neraca" circuit="true">
                    <div class="space-y-4">
                        @forelse($hasilDiagnosa['daftar_temuan'] as $item)
                            <div class="p-4 rounded-xl bg-black/[0.02] dark:bg-white/[0.02] border {{ $item['tingkat'] === 'kritis' ? 'border-up-red/30' : 'border-up-amber/30' }} space-y-3">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-md {{ $item['tingkat'] === 'kritis' ? 'bg-up-red/20 text-up-red' : 'bg-up-amber/20 text-up-amber' }}">
                                            {{ strtoupper($item['tingkat']) }}
                                        </span>
                                        <h4 class="text-xs font-bold text-ink-900 dark:text-white">{{ $item['judul'] }}</h4>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="text-[11px] font-semibold text-up-accent bg-up-accent/10 px-2 py-0.5 rounded-lg border border-up-accent/20">
                                            Bagian: {{ $item['bagian'] }}
                                        </span>
                                        @if($item['dampak_nominal'] > 0)
                                            <span class="text-xs font-bold tabular-nums text-ink-900 dark:text-white">
                                                Dampak: Rp {{ number_format($item['dampak_nominal'], 0, ',', '.') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div class="text-xs text-ink-600 dark:text-ink-300 space-y-1">
                                    <p><strong class="text-ink-800 dark:text-ink-200">Akar Masalah:</strong> {{ $item['penyebab'] }}</p>
                                    <p><strong class="text-up-mint">Rekomendasi Tindakan:</strong> {{ $item['rekomendasi'] }}</p>
                                </div>

                                @if(!empty($item['solusi_otomatis_tersedia']) && !empty($item['auto_fix_key']))
                                    <div class="pt-2 border-t border-black/5 dark:border-white/5 flex justify-end">
                                        <button type="button" wire:click="eksekusiSolusiDiagnosa('{{ $item['auto_fix_key'] }}', @js($item['payload'] ?? []))"
                                            class="px-3 py-1.5 rounded-lg bg-up-mint hover:bg-up-mint/90 text-ink-950 font-bold text-xs transition cursor-pointer flex items-center gap-1.5 shadow-sm">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                            <span>Tindak Lanjut Otomatis (Perbaiki)</span>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="py-8 text-center text-xs text-ink-400">
                                ✓ Luar biasa! Tidak terdeteksi anomali apapun. Seluruh modul dan jurnal transaksi dalam keadaan seimbang dan sinkron.
                            </div>
                        @endforelse
                    </div>
                </x-prism.glass-card>
            @else
                <div class="p-12 text-center glass-card space-y-3">
                    <p class="text-sm text-ink-300 font-medium">Klik tombol di bawah untuk menjalankan analisa menyeluruh pada seluruh transaksi aplikasi.</p>
                    <button type="button" wire:click="jalankanDiagnosa"
                        class="px-5 py-2.5 rounded-xl bg-up-primary text-white font-bold text-xs shadow-md shadow-up-primary/25 hover:bg-up-primary/90 transition cursor-pointer">
                        🔍 Mulai Diagnosa Cerdas Sekarang
                    </button>
                </div>
            @endif
        </div>
    @endif

    <!-- TAB: TUTUP BUKU & KONTROL PERIODE (ISO / AUDIT) -->
    @if($activeTab === 'tutup-buku')
        <div class="space-y-6">
            <!-- Filter Pemilihan Periode -->
            <x-prism.glass-card title="Pengendalian Periode Akuntansi & Tutup Buku" subtitle="Kunci transaksi historis dan pindahkan saldo pendapatan & beban ke Laba Ditahan untuk memenuhi standar audit ISO.">
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div>
                            <label class="block text-[11px] font-semibold text-ink-400 mb-1">Bulan</label>
                            <select wire:model.live="tutupBukuBulan" class="px-3 py-2 rounded-xl glass-input text-xs font-semibold">
                                @foreach([
                                    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                                    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                                    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                                ] as $num => $nama)
                                    <option value="{{ $num }}">{{ $nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-ink-400 mb-1">Tahun</label>
                            <input type="number" wire:model.live="tutupBukuTahun" min="2020" max="2035" class="w-24 px-3 py-2 rounded-xl glass-input text-xs font-semibold tabular-nums" />
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        @if($periodeAktif && $periodeAktif->isClosed())
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-up-red/10 border border-up-red/20 text-up-red font-bold text-xs">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                PERIODE TERKUNCI (CLOSED)
                            </span>
                            @if(isSuperAdminOrOwner(auth()->user()))
                                <button type="button" wire:click="bukaModalBukaBuku"
                                    class="px-4 py-2 rounded-xl bg-black/10 dark:bg-white/10 hover:bg-black/20 dark:hover:bg-white/20 text-ink-700 dark:text-ink-200 border border-black/10 dark:border-white/10 text-xs font-bold transition cursor-pointer">
                                    🔓 Buka Kembali Periode
                                </button>
                            @endif
                        @else
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-up-mint/10 border border-up-mint/20 text-up-mint font-bold text-xs">
                                <span class="w-2 h-2 rounded-full bg-up-mint animate-pulse"></span>
                                PERIODE TERBUKA (OPEN)
                            </span>
                            @can('akunting.approve')
                                <button type="button" wire:click="bukaModalTutupBuku"
                                    class="px-4 py-2 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs shadow-md shadow-up-primary/25 transition cursor-pointer flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    <span>Tutup Buku Periode Ini</span>
                                </button>
                            @endcan
                        @endif
                    </div>
                </div>

                @if($periodeAktif && $periodeAktif->isClosed())
                    <div class="mt-4 p-4 rounded-xl bg-up-red/[0.04] border border-up-red/15 text-xs text-ink-300 space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-white">Informasi Penguncian:</span>
                            <span class="text-ink-400">Ditutup pada: {{ $periodeAktif->closed_at?->format('d/m/Y H:i') }}</span>
                        </div>
                        <p>Oleh: <strong class="text-white">{{ $periodeAktif->closedByUser?->name ?? 'Sistem' }}</strong></p>
                        @if($periodeAktif->no_jurnal_penutup)
                            <p>No. Jurnal Penutup: <span class="font-mono text-up-mint font-bold">{{ $periodeAktif->no_jurnal_penutup }}</span></p>
                        @endif
                        @if($periodeAktif->catatan)
                            <p class="italic text-ink-400">Catatan: "{{ $periodeAktif->catatan }}"</p>
                        @endif
                        <p class="text-[11px] text-ink-400 pt-1 border-t border-white/5">Seluruh penambahan dan perubahan jurnal transaksi pada periode ini diblokir secara mutlak.</p>
                    </div>
                @endif
            </x-prism.glass-card>

            <!-- Tabel Riwayat Penutupan Buku -->
            <x-prism.glass-card title="Riwayat Penutupan Buku Cabang Ini" subtitle="Daftar rekam jejak penguncian buku bulanan untuk pemenuhan kepatuhan ISO 9001 / ISO 27001.">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-black/5 dark:bg-white/5 text-ink-400 uppercase text-[10px] tracking-wider">
                            <tr>
                                <th class="px-4 py-3">Periode</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Waktu Penutupan</th>
                                <th class="px-4 py-3">Otorisator</th>
                                <th class="px-4 py-3">Jurnal Penutup</th>
                                <th class="px-4 py-3">Catatan Audit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-black/5 dark:divide-white/5">
                            @forelse($riwayatTutupBuku as $r)
                                <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition">
                                    <td class="px-4 py-3 font-bold text-ink-900 dark:text-white">
                                        {{ $r->label_periode }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($r->isClosed())
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-up-red/10 text-up-red text-[11px] font-bold">
                                                🔒 Ditutup
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-up-mint/10 text-up-mint text-[11px] font-bold">
                                                🟢 Terbuka
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-ink-400">
                                        {{ $r->closed_at ? $r->closed_at->format('d/m/Y H:i') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-ink-300">
                                        {{ $r->closedByUser?->name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 font-mono text-ink-200">
                                        {{ $r->no_jurnal_penutup ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-ink-400 italic max-w-xs truncate">
                                        {{ $r->catatan ?? '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-ink-400 text-xs">
                                        Belum ada riwayat penutupan buku untuk cabang ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-prism.glass-card>
        </div>
    @endif

    <!-- TAB: NERACA SALDO 4 KOLOM (TRIAL BALANCE) -->
    @if($activeTab === 'neraca-saldo')
        <div class="space-y-6">
            <!-- Filter & Aksi Ekspor -->
            <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3 flex-wrap">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-semibold text-ink-300">Periode Neraca Saldo:</span>
                    <input type="date" wire:model.live="neracaSaldoDari" class="px-2.5 py-1.5 rounded-xl glass-input text-xs font-medium min-h-[40px]" />
                    <span class="text-ink-400 text-xs">—</span>
                    <input type="date" wire:model.live="neracaSaldoSampai" class="px-2.5 py-1.5 rounded-xl glass-input text-xs font-medium min-h-[40px]" />
                    <button type="button" wire:click="segarkanLaporan" class="px-3 py-2 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 text-xs font-bold text-white border border-white/10 cursor-pointer min-h-[40px]">
                        Segarkan
                    </button>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="export('neraca_saldo', 'xlsx')" class="px-3.5 py-2 rounded-xl bg-up-mint/20 hover:bg-up-mint/30 border border-up-mint/40 text-up-mint text-xs font-bold transition-all cursor-pointer min-h-[40px] flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Export Excel
                    </button>
                    <button type="button" wire:click="export('neraca_saldo', 'csv')" class="px-3.5 py-2 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 border border-black/10 dark:border-white/10 text-ink-300 text-xs font-bold transition-all cursor-pointer min-h-[40px]">
                        Export CSV
                    </button>
                </div>
            </div>

            <!-- Ringkasan Status Balance -->
            @if($neracaSaldoData)
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div class="p-4 rounded-2xl bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10">
                        <div class="text-[10px] uppercase font-bold text-ink-400">Total Saldo Awal (D / K)</div>
                        <div class="text-sm font-bold text-ink-900 dark:text-white tabular-nums mt-1">
                            Rp {{ number_format($neracaSaldoData['total']['saldo_awal_debit'], 0, ',', '.') }}
                        </div>
                    </div>
                    <div class="p-4 rounded-2xl bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10">
                        <div class="text-[10px] uppercase font-bold text-ink-400">Total Mutasi Periode (D)</div>
                        <div class="text-sm font-bold text-up-amber tabular-nums mt-1">
                            Rp {{ number_format($neracaSaldoData['total']['mutasi_debit'], 0, ',', '.') }}
                        </div>
                    </div>
                    <div class="p-4 rounded-2xl bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10">
                        <div class="text-[10px] uppercase font-bold text-ink-400">Total Mutasi Periode (K)</div>
                        <div class="text-sm font-bold text-up-mint tabular-nums mt-1">
                            Rp {{ number_format($neracaSaldoData['total']['mutasi_kredit'], 0, ',', '.') }}
                        </div>
                    </div>
                    <div class="p-4 rounded-2xl {{ $neracaSaldoData['is_balanced'] ? 'bg-up-mint/10 border border-up-mint/30' : 'bg-up-red/10 border border-up-red/30' }}">
                        <div class="text-[10px] uppercase font-bold {{ $neracaSaldoData['is_balanced'] ? 'text-up-mint' : 'text-up-red' }}">
                            {{ $neracaSaldoData['is_balanced'] ? '✓ STATUS SEIMBANG' : '⚠ TIDAK SEIMBANG' }}
                        </div>
                        <div class="text-sm font-bold {{ $neracaSaldoData['is_balanced'] ? 'text-up-mint' : 'text-up-red' }} tabular-nums mt-1">
                            Selisih: Rp {{ number_format(abs($neracaSaldoData['total']['mutasi_debit'] - $neracaSaldoData['total']['mutasi_kredit']), 2, ',', '.') }}
                        </div>
                    </div>
                </div>

                <!-- Tabel Neraca Saldo 4 Kolom -->
                <x-prism.glass-card title="Kertas Kerja Neraca Saldo 4 Kolom" subtitle="Format standar ISO/Audit: Menampilkan Saldo Awal, Pergerakan Mutasi Debit/Kredit, dan Saldo Akhir Buku Besar.">
                    <div class="overflow-x-auto -mx-4 sm:mx-0">
                        <table class="w-full text-left text-xs whitespace-nowrap">
                            <thead>
                                <tr class="border-b border-black/10 dark:border-white/10 text-[10px] uppercase text-ink-400">
                                    <th rowspan="2" class="py-3 px-3">Kode Akun</th>
                                    <th rowspan="2" class="py-3 px-3">Nama Akun</th>
                                    <th rowspan="2" class="py-3 px-2 text-center">Pos</th>
                                    <th colspan="2" class="py-2 px-3 text-center border-b border-white/5 bg-white/[0.02]">Saldo Awal</th>
                                    <th colspan="2" class="py-2 px-3 text-center border-b border-white/5 bg-white/[0.04]">Mutasi Periode</th>
                                    <th colspan="2" class="py-2 px-3 text-center border-b border-white/5 bg-white/[0.02]">Saldo Akhir</th>
                                </tr>
                                <tr class="border-b border-black/10 dark:border-white/10 text-[10px] uppercase text-ink-400">
                                    <th class="py-2 px-3 text-right">Debit</th>
                                    <th class="py-2 px-3 text-right">Kredit</th>
                                    <th class="py-2 px-3 text-right text-up-amber">Debit</th>
                                    <th class="py-2 px-3 text-right text-up-mint">Kredit</th>
                                    <th class="py-2 px-3 text-right">Debit</th>
                                    <th class="py-2 px-3 text-right">Kredit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-black/5 dark:divide-white/5 font-mono">
                                @forelse($neracaSaldoData['akun'] as $row)
                                    <tr class="hover:bg-black/5 dark:hover:bg-white/[0.02] transition-colors">
                                        <td class="py-2.5 px-3 font-bold text-ink-900 dark:text-white">{{ $row['kode'] }}</td>
                                        <td class="py-2.5 px-3 font-sans text-ink-700 dark:text-ink-200">{{ $row['nama'] }}</td>
                                        <td class="py-2.5 px-2 text-center text-[10px] uppercase font-bold text-ink-500">{{ substr($row['saldo_normal'], 0, 1) }}</td>
                                        <td class="py-2.5 px-3 text-right tabular-nums text-ink-400">{{ $row['saldo_awal_debit'] > 0 ? number_format($row['saldo_awal_debit'], 0, ',', '.') : '-' }}</td>
                                        <td class="py-2.5 px-3 text-right tabular-nums text-ink-400">{{ $row['saldo_awal_kredit'] > 0 ? number_format($row['saldo_awal_kredit'], 0, ',', '.') : '-' }}</td>
                                        <td class="py-2.5 px-3 text-right tabular-nums font-semibold {{ $row['mutasi_debit'] > 0 ? 'text-up-amber' : 'text-ink-500' }}">{{ $row['mutasi_debit'] > 0 ? number_format($row['mutasi_debit'], 0, ',', '.') : '-' }}</td>
                                        <td class="py-2.5 px-3 text-right tabular-nums font-semibold {{ $row['mutasi_kredit'] > 0 ? 'text-up-mint' : 'text-ink-500' }}">{{ $row['mutasi_kredit'] > 0 ? number_format($row['mutasi_kredit'], 0, ',', '.') : '-' }}</td>
                                        <td class="py-2.5 px-3 text-right tabular-nums font-bold text-ink-900 dark:text-white">{{ $row['saldo_akhir_debit'] > 0 ? number_format($row['saldo_akhir_debit'], 0, ',', '.') : '-' }}</td>
                                        <td class="py-2.5 px-3 text-right tabular-nums font-bold text-ink-900 dark:text-white">{{ $row['saldo_akhir_kredit'] > 0 ? number_format($row['saldo_akhir_kredit'], 0, ',', '.') : '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="py-8 text-center text-ink-500 font-sans text-xs">Tidak ada mutasi buku besar pada periode ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="border-t-2 border-black/20 dark:border-white/20 font-mono font-bold bg-black/5 dark:bg-white/[0.03]">
                                <tr>
                                    <td colspan="3" class="py-3 px-3 font-sans text-right uppercase text-[11px] text-ink-300">TOTAL</td>
                                    <td class="py-3 px-3 text-right tabular-nums">{{ number_format($neracaSaldoData['total']['saldo_awal_debit'], 0, ',', '.') }}</td>
                                    <td class="py-3 px-3 text-right tabular-nums">{{ number_format($neracaSaldoData['total']['saldo_awal_kredit'], 0, ',', '.') }}</td>
                                    <td class="py-3 px-3 text-right tabular-nums text-up-amber">{{ number_format($neracaSaldoData['total']['mutasi_debit'], 0, ',', '.') }}</td>
                                    <td class="py-3 px-3 text-right tabular-nums text-up-mint">{{ number_format($neracaSaldoData['total']['mutasi_kredit'], 0, ',', '.') }}</td>
                                    <td class="py-3 px-3 text-right tabular-nums">{{ number_format($neracaSaldoData['total']['saldo_akhir_debit'], 0, ',', '.') }}</td>
                                    <td class="py-3 px-3 text-right tabular-nums">{{ number_format($neracaSaldoData['total']['saldo_akhir_kredit'], 0, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </x-prism.glass-card>
            @endif
        </div>
    @endif

    <!-- TAB: REKONSILIASI BANK MULTI-FORMAT (PDF, CSV, XLSX) -->
    @if($activeTab === 'rekonsiliasi-bank')
        <div class="space-y-6">
            <div class="flex justify-between items-center gap-3 flex-wrap">
                <div>
                    <h3 class="text-sm font-bold text-ink-900 dark:text-white">Rekonsiliasi Rekening Koran Bank</h3>
                    <p class="text-xs text-ink-400">Pencocokan otomatis transaksi bank (e-Statement PDF BCA/Mandiri, CSV, Excel) dengan buku besar.</p>
                </div>
                <button type="button" wire:click="$set('showUploadRekonModal', true)" class="px-4 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs shadow-md shadow-up-primary/25 cursor-pointer min-h-[44px] flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    Import Rekening Koran (PDF / CSV)
                </button>
            </div>

            <!-- Sesi Terpilih / Detail Rekonsiliasi -->
            @if($detailRekonsiliasi)
                <x-prism.glass-card title="Sesi Rekonsiliasi: {{ $detailRekonsiliasi->nama_bank }} ({{ $detailRekonsiliasi->nama_file }})" subtitle="Periode {{ $detailRekonsiliasi->periode_dari->format('d/m/Y') }} — {{ $detailRekonsiliasi->periode_sampai->format('d/m/Y') }} | Akun: {{ $detailRekonsiliasi->akun?->kode }} - {{ $detailRekonsiliasi->akun?->nama }}">
                    <x-slot:action>
                        <button wire:click="$set('selectedRekonId', null)" class="px-3 py-1.5 rounded-lg bg-black/5 dark:bg-white/5 hover:bg-black/10 text-xs font-bold text-ink-300 cursor-pointer">✕ Tutup Detail</button>
                    </x-slot:action>

                    <div class="space-y-4">
                        <!-- Progress Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="p-3.5 rounded-xl bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10">
                                <div class="text-[10px] uppercase font-bold text-ink-400">Total Baris Mutasi</div>
                                <div class="text-lg font-bold text-white tabular-nums mt-0.5">{{ $detailRekonsiliasi->total_baris_bank }} Transaksi</div>
                            </div>
                            <div class="p-3.5 rounded-xl bg-up-mint/10 border border-up-mint/30">
                                <div class="text-[10px] uppercase font-bold text-up-mint">Cocok Otomatis (Matched)</div>
                                <div class="text-lg font-bold text-up-mint tabular-nums mt-0.5">{{ $detailRekonsiliasi->total_matched }} ({{ $detailRekonsiliasi->total_baris_bank > 0 ? round(($detailRekonsiliasi->total_matched / $detailRekonsiliasi->total_baris_bank) * 100) : 0 }}%)</div>
                            </div>
                            <div class="p-3.5 rounded-xl bg-up-amber/10 border border-up-amber/30">
                                <div class="text-[10px] uppercase font-bold text-up-amber">Belum Cocok (Unmatched)</div>
                                <div class="text-lg font-bold text-up-amber tabular-nums mt-0.5">{{ max(0, $detailRekonsiliasi->total_baris_bank - $detailRekonsiliasi->total_matched) }} Transaksi</div>
                            </div>
                        </div>

                        <!-- Tabel Items Rekonsiliasi -->
                        <div class="overflow-x-auto -mx-4 sm:mx-0">
                            <table class="w-full text-left text-xs whitespace-nowrap">
                                <thead>
                                    <tr class="border-b border-black/10 dark:border-white/10 text-[10px] uppercase text-ink-400">
                                        <th class="py-2.5 px-3">Tanggal</th>
                                        <th class="py-2.5 px-3">Deskripsi Rekening Koran</th>
                                        <th class="py-2.5 px-2 text-center">Tipe</th>
                                        <th class="py-2.5 px-3 text-right">Nominal</th>
                                        <th class="py-2.5 px-3 text-center">Status</th>
                                        <th class="py-2.5 px-3">No. Jurnal Terkait</th>
                                        <th class="py-2.5 px-3 text-center">Aksi Penyesuaian</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-black/5 dark:divide-white/5">
                                    @foreach($detailRekonsiliasi->items as $it)
                                        <tr class="hover:bg-black/5 dark:hover:bg-white/[0.02]">
                                            <td class="py-2.5 px-3 font-mono text-ink-400">{{ $it->tanggal->format('d/m/Y') }}</td>
                                            <td class="py-2.5 px-3 text-ink-900 dark:text-ink-100 max-w-sm truncate" title="{{ $it->deskripsi }}">{{ $it->deskripsi }}</td>
                                            <td class="py-2.5 px-2 text-center">
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold {{ $it->tipe === 'CR' ? 'bg-up-mint/20 text-up-mint' : 'bg-up-amber/20 text-up-amber' }}">
                                                    {{ $it->tipe === 'CR' ? 'CR (Masuk)' : 'DB (Keluar)' }}
                                                </span>
                                            </td>
                                            <td class="py-2.5 px-3 text-right font-mono font-bold tabular-nums {{ $it->tipe === 'CR' ? 'text-up-mint' : 'text-up-amber' }}">
                                                Rp {{ number_format($it->nominal, 0, ',', '.') }}
                                            </td>
                                            <td class="py-2.5 px-3 text-center">
                                                @if($it->status === 'matched')
                                                    <span class="px-2 py-0.5 rounded-full bg-up-mint/15 text-up-mint text-[10px] font-bold">COCOK</span>
                                                @elseif($it->status === 'adjusted')
                                                    <span class="px-2 py-0.5 rounded-full bg-up-primary/15 text-up-primary text-[10px] font-bold">DISESUAIKAN</span>
                                                @else
                                                    <span class="px-2 py-0.5 rounded-full bg-up-red/15 text-up-red text-[10px] font-bold">BELUM COCOK</span>
                                                @endif
                                            </td>
                                            <td class="py-2.5 px-3 font-mono text-xs text-ink-300">
                                                {{ $it->no_jurnal_matched ?? '—' }}
                                            </td>
                                            <td class="py-2.5 px-3 text-center">
                                                @if($it->status === 'unmatched')
                                                    <div class="flex items-center justify-center gap-1.5">
                                                        @if($it->tipe === 'DB')
                                                            <button type="button" wire:click="bukaModalPenyesuaian({{ $it->id }}, 'biaya_admin')" class="px-2 py-1 rounded-lg bg-up-amber/15 hover:bg-up-amber/25 text-up-amber text-[10px] font-bold cursor-pointer">
                                                                + Biaya Admin
                                                            </button>
                                                        @else
                                                            <button type="button" wire:click="bukaModalPenyesuaian({{ $it->id }}, 'pendapatan_bunga')" class="px-2 py-1 rounded-lg bg-up-mint/15 hover:bg-up-mint/25 text-up-mint text-[10px] font-bold cursor-pointer">
                                                                + Bunga Bank
                                                            </button>
                                                        @endif
                                                    </div>
                                                @else
                                                    <span class="text-ink-500 text-[10px]">Terselesaikan</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </x-prism.glass-card>
            @endif

            <!-- Riwayat Sesi Rekonsiliasi -->
            <x-prism.glass-card title="Daftar Sesi Rekonsiliasi Rekening Koran" subtitle="Pilih sesi untuk melihat rincian transaksi bank dan melakukan jurnal penyesuaian.">
                <div class="overflow-x-auto -mx-4 sm:mx-0">
                    <table class="w-full text-left text-xs whitespace-nowrap">
                        <thead>
                            <tr class="border-b border-black/10 dark:border-white/10 text-[10px] uppercase text-ink-400">
                                <th class="py-3 px-4">Nama Bank</th>
                                <th class="py-3 px-4">Berkas Sumber</th>
                                <th class="py-3 px-4">Akun COA</th>
                                <th class="py-3 px-4">Periode Mutasi</th>
                                <th class="py-3 px-4 text-center">Total Baris</th>
                                <th class="py-3 px-4 text-center">Tingkat Kecocokan</th>
                                <th class="py-3 px-4">Tanggal Import</th>
                                <th class="py-3 px-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-black/5 dark:divide-white/5">
                            @if($rekonsiliasiList)
                                @forelse($rekonsiliasiList as $rk)
                                    <tr class="hover:bg-black/5 dark:hover:bg-white/[0.02]">
                                        <td class="py-3 px-4 font-bold text-ink-900 dark:text-white flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full {{ $rk->total_matched === $rk->total_baris_bank ? 'bg-up-mint' : 'bg-up-amber' }}"></span>
                                            {{ $rk->nama_bank }}
                                        </td>
                                        <td class="py-3 px-4 text-ink-400">
                                            <span class="px-1.5 py-0.5 rounded text-[10px] uppercase font-mono bg-white/5 border border-white/10 text-ink-300 mr-1">{{ $rk->format_file }}</span>
                                            {{ $rk->nama_file }}
                                        </td>
                                        <td class="py-3 px-4 font-mono text-ink-300">{{ $rk->akun?->kode }} - {{ $rk->akun?->nama }}</td>
                                        <td class="py-3 px-4 text-ink-400">{{ $rk->periode_dari->format('d/m/Y') }} s/d {{ $rk->periode_sampai->format('d/m/Y') }}</td>
                                        <td class="py-3 px-4 text-center font-mono">{{ $rk->total_baris_bank }}</td>
                                        <td class="py-3 px-4 text-center">
                                            <span class="font-bold tabular-nums {{ $rk->total_matched === $rk->total_baris_bank ? 'text-up-mint' : 'text-up-amber' }}">
                                                {{ $rk->total_matched }} / {{ $rk->total_baris_bank }} ({{ $rk->total_baris_bank > 0 ? round(($rk->total_matched / $rk->total_baris_bank) * 100) : 0 }}%)
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-ink-500">{{ $rk->created_at->format('d/m/Y H:i') }}</td>
                                        <td class="py-3 px-4 text-center">
                                            <button type="button" wire:click="pilihRekonsiliasi({{ $rk->id }})" class="px-3 py-1.5 rounded-lg bg-up-primary/15 hover:bg-up-primary/25 text-up-primary text-xs font-bold cursor-pointer">
                                                Buka Rincian
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="py-8 text-center text-ink-400 text-xs">
                                            Belum ada data rekonsiliasi rekening koran. Klik "Import Rekening Koran" untuk memulai.
                                        </td>
                                    </tr>
                                @endforelse
                            @endif
                        </tbody>
                    </table>
                </div>
            </x-prism.glass-card>
        </div>
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
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
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
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Lampiran Bukti (PDF/Foto) *</label>
                            <input
                                type="file"
                                wire:model="manualLampiran"
                                accept=".pdf,.jpg,.jpeg,.png"
                                class="w-full text-xs text-ink-300 file:mr-2 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-[11px] file:font-semibold file:bg-up-primary file:text-white hover:file:bg-up-primary/80 cursor-pointer"
                            />
                            <div wire:loading wire:target="manualLampiran" class="text-[10px] text-up-amber mt-1">Mengunggah berkas...</div>
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
                    <h3 class="text-lg font-bold text-white">{{ ! empty($coaForm['id']) ? 'Edit Akun COA' : 'Tambah Akun COA' }}</h3>
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

    <!-- MODAL: FORM MATCHING / REKONSILIASI KAS REAL -->
    @if($showFormMatchingModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-sm p-4 overflow-y-auto">
            <x-prism.glass-card
                title="Pencocokan Kas Real (Opname Fisik vs Sistem)"
                subtitle="Hitung fisik kas aktual di laci kasir/brankas atau mutasi bank, dan bandingkan dengan catatan buku besar."
                class="w-full max-w-2xl max-h-[92vh] flex flex-col my-auto"
            >
                <x-slot:action>
                    <button wire:click="tutupFormMatching" class="text-ink-400 hover:text-white p-2 cursor-pointer">✕</button>
                </x-slot:action>

                <div class="space-y-4 overflow-y-auto pr-1">
                    <!-- Pilihan Cabang, Akun & Tanggal -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Cabang *</label>
                            <select wire:model.live="matchingCabangId" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium bg-ink-900 text-white">
                                @foreach($daftarCabang as $cb)
                                    <option value="{{ $cb->id }}" class="bg-ink-900">{{ $cb->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Akun Kas / Bank *</label>
                            <select wire:model.live="selectedAkunKasId" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium bg-ink-900 text-white">
                                @foreach($kasAccounts as $k)
                                    <option value="{{ $k['id'] }}" class="bg-ink-900">{{ $k['kode'] }} — {{ $k['nama'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Tanggal Cut-off / Opname *</label>
                            <input type="date" wire:model.live="matchingTanggal" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium text-white" />
                        </div>
                    </div>

                    <!-- Saldo Sistem GL vs Fisik Real -->
                    <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/10 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-ink-400 font-semibold">Saldo Tercatat di Sistem (GL):</span>
                            <span class="text-sm font-bold text-white tabular-nums">Rp {{ number_format($saldoSistemKas, 0, ',', '.') }}</span>
                        </div>

                        <!-- Toggle Mode Pecahan Uang -->
                        <div class="flex items-center justify-between pt-2 border-t border-white/5">
                            <span class="text-xs text-ink-300">Gunakan Hitungan Pecahan Uang Kertas &amp; Logam:</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" wire:model.live="usePecahanMode" class="sr-only peer" />
                                <div class="w-9 h-5 bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-up-mint"></div>
                            </label>
                        </div>

                        @if($usePecahanMode)
                            <!-- Input Rincian Pecahan -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2">
                                @foreach(['100000' => 'Rp 100.000', '50000' => 'Rp 50.000', '20000' => 'Rp 20.000', '10000' => 'Rp 10.000', '5000' => 'Rp 5.000', '2000' => 'Rp 2.000', '1000' => 'Rp 1.000'] as $nom => $lbl)
                                    <div>
                                        <label class="block text-[10px] text-ink-400 mb-1">{{ $lbl }} (lbr)</label>
                                        <input type="number" min="0" wire:model.live="rincianPecahan.{{ $nom }}" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs font-mono text-center" />
                                    </div>
                                @endforeach
                                <div>
                                    <label class="block text-[10px] text-ink-400 mb-1">Total Koin (Rp)</label>
                                    <input type="number" min="0" step="100" wire:model.live="rincianPecahan.koin" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs font-mono text-center" />
                                </div>
                            </div>
                        @endif

                        <!-- Input Saldo Fisik -->
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Total Uang Fisik Aktual (Rp) *</label>
                            <input type="text" inputmode="numeric" x-format-number wire:model.live="saldoFisikKasInput" {{ $usePecahanMode ? 'readonly' : '' }}
                                class="w-full px-4 py-3 rounded-xl glass-input text-lg font-bold tabular-nums text-white {{ $usePecahanMode ? 'bg-white/5 opacity-80' : '' }}" />
                        </div>

                        <!-- Indikator Selisih -->
                        <div class="p-3.5 rounded-xl {{ abs($selisihKas) < 0.01 ? 'bg-up-mint/10 border border-up-mint/30' : ($selisihKas > 0 ? 'bg-up-amber/10 border border-up-amber/30' : 'bg-up-red/10 border border-up-red/30') }} flex items-center justify-between">
                            <div class="text-xs font-bold {{ abs($selisihKas) < 0.01 ? 'text-up-mint' : ($selisihKas > 0 ? 'text-up-amber' : 'text-up-red') }}">
                                {{ abs($selisihKas) < 0.01 ? '✓ SALDO COCOK' : ($selisihKas > 0 ? 'LEBIH FISIK (+)' : 'SELISIH TEKOR (−)') }}
                            </div>
                            <div class="text-sm font-bold tabular-nums {{ abs($selisihKas) < 0.01 ? 'text-up-mint' : ($selisihKas > 0 ? 'text-up-amber' : 'text-up-red') }}">
                                {{ $selisihKas > 0 ? '+' : ($selisihKas < 0 ? '−' : '') }} Rp {{ number_format(abs($selisihKas), 0, ',', '.') }}
                            </div>
                        </div>
                    </div>

                    <!-- Catatan / Berita Acara -->
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Catatan / Keterangan Pencocokan</label>
                        <textarea wire:model="catatanMatchingKas" rows="2" class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white" placeholder="Contoh: Opname kas harian shift malam, tidak ditemukan selisih..."></textarea>
                    </div>
                </div>

                <div class="mt-5 flex gap-3 border-t border-white/10 pt-4">
                    <button type="button" wire:click="tutupFormMatching" class="flex-1 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button type="button" wire:click="simpanMatchingKas" class="flex-1 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs shadow-md shadow-up-primary/25 cursor-pointer min-h-[44px]">Simpan Hasil Pencocokan</button>
                </div>
            </x-prism.glass-card>
        </div>
    @endif

    <!-- MODAL: KONFIRMASI TUTUP BUKU -->
    @if($showTutupBukuModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-sm p-4 overflow-y-auto">
            <x-prism.glass-card
                title="Konfirmasi Penutupan Buku Periode"
                subtitle="Verifikasi pra-cek neraca dan jurnal penutup otomatis sebelum mengunci periode buku."
                class="w-full max-w-2xl max-h-[92vh] flex flex-col my-auto"
            >
                <x-slot:action>
                    <button wire:click="$set('showTutupBukuModal', false)" class="text-ink-400 hover:text-white p-2 cursor-pointer">✕</button>
                </x-slot:action>

                <div class="space-y-4 overflow-y-auto pr-1">
                    <!-- Status Neraca Pra-cek -->
                    <div class="p-3.5 rounded-xl {{ ($tutupBukuPraCek['neraca_balance'] ?? false) ? 'bg-up-mint/10 border border-up-mint/30' : 'bg-up-red/10 border border-up-red/30' }} flex items-center justify-between">
                        <div>
                            <div class="text-xs font-bold {{ ($tutupBukuPraCek['neraca_balance'] ?? false) ? 'text-up-mint' : 'text-up-red' }}">
                                {{ ($tutupBukuPraCek['neraca_balance'] ?? false) ? '✓ NERACA SEIMBANG (BALANCE)' : '⚠ NERACA TIDAK SEIMBANG' }}
                            </div>
                            <div class="text-[11px] text-ink-300 mt-0.5">
                                {{ $tutupBukuPraCek['pesan'] ?? '' }}
                            </div>
                        </div>
                        <div class="text-xs font-bold tabular-nums text-white">
                            Selisih: Rp {{ number_format(abs($tutupBukuPraCek['selisih_neraca'] ?? 0), 2, ',', '.') }}
                        </div>
                    </div>

                    <!-- Ringkasan Nominal yang Ditutup -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="p-3 rounded-xl bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10">
                            <span class="text-[11px] text-ink-400 font-semibold block">Total Pendapatan</span>
                            <span class="text-sm font-bold text-up-mint tabular-nums">
                                Rp {{ number_format($tutupBukuPraCek['total_pendapatan'] ?? 0, 0, ',', '.') }}
                            </span>
                        </div>
                        <div class="p-3 rounded-xl bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10">
                            <span class="text-[11px] text-ink-400 font-semibold block">Total Beban</span>
                            <span class="text-sm font-bold text-up-red tabular-nums">
                                Rp {{ number_format($tutupBukuPraCek['total_beban'] ?? 0, 0, ',', '.') }}
                            </span>
                        </div>
                        <div class="p-3 rounded-xl bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10">
                            <span class="text-[11px] text-ink-400 font-semibold block">Laba / (Rugi) Bersih</span>
                            <span class="text-sm font-bold {{ ($tutupBukuPraCek['laba_rugi_bersih'] ?? 0) >= 0 ? 'text-up-mint' : 'text-up-red' }} tabular-nums">
                                Rp {{ number_format($tutupBukuPraCek['laba_rugi_bersih'] ?? 0, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>

                    <!-- Keterangan Jurnal Penutup Otomatis -->
                    <div class="p-3 rounded-xl bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 text-xs text-ink-300 space-y-1">
                        <p class="font-semibold text-ink-900 dark:text-white">Dampak Penutupan Buku:</p>
                        <p>1. Menghasilkan Jurnal Penutup (Closing Entry): saldo akun pendapatan didebit dan beban dikredit menjadi Rp 0.</p>
                        <p>2. Laba / Rugi Bersih (Rp {{ number_format($tutupBukuPraCek['laba_rugi_bersih'] ?? 0, 0, ',', '.') }}) dipindahkan ke akun <strong class="text-up-primary font-mono">310-02 (Laba Ditahan)</strong>.</p>
                        <p>3. Periode bulan tersebut dikunci permanen. Transaksi baru tidak dapat dimasukkan ke periode ini.</p>
                    </div>

                    <!-- Input Catatan Otorisator -->
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Catatan Penutupan Buku (Opsional)</label>
                        <textarea wire:model="tutupBukuCatatan" rows="2" class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white" placeholder="Contoh: Tutup buku bulanan telah direview dan disetujui audit internal..."></textarea>
                    </div>
                </div>

                <div class="mt-5 flex gap-3 border-t border-black/10 dark:border-white/10 pt-4">
                    <button type="button" wire:click="$set('showTutupBukuModal', false)" class="flex-1 py-2.5 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 text-ink-600 dark:text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">
                        Batal
                    </button>
                    @if($tutupBukuPraCek['siap_tutup'] ?? false)
                        <button type="button" wire:click="eksekusiTutupBuku" class="flex-1 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs shadow-md shadow-up-primary/25 cursor-pointer min-h-[44px] flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            <span>Konfirmasi & Kunci Periode</span>
                        </button>
                    @else
                        <button type="button" disabled class="flex-1 py-2.5 rounded-xl bg-ink-800 text-ink-500 font-bold text-xs cursor-not-allowed min-h-[44px]">
                            Tidak Dapat Ditutup (Neraca Belum Seimbang)
                        </button>
                    @endif
                </div>
            </x-prism.glass-card>
        </div>
    @endif

    <!-- MODAL: BUKA KEMBALI PERIODE BUKU -->
    @if($showBukaBukuModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-sm p-4 overflow-y-auto">
            <x-prism.glass-card
                title="Buka Kembali Periode Akuntansi"
                subtitle="Otorisasi Super Admin: Pembukaan periode akan memposting Jurnal Pembalik otomatis."
                class="w-full max-w-lg flex flex-col my-auto"
            >
                <x-slot:action>
                    <button wire:click="$set('showBukaBukuModal', false)" class="text-ink-400 hover:text-white p-2 cursor-pointer">✕</button>
                </x-slot:action>

                <div class="space-y-4">
                    <div class="p-3.5 rounded-xl bg-up-amber/10 border border-up-amber/30 text-xs text-up-amber space-y-1">
                        <p class="font-bold">PERINGATAN AUDIT (ISO 27001):</p>
                        <p>Membuka kembali buku periode yang sudah ditutup akan memposting Jurnal Pembalik (Reversal) atas jurnal penutup sebelumnya, dan memungkinkan mutasi baru.</p>
                        <p>Setiap pembukaan periode dicatat secara permanen di Audit Trail.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Alasan Pembukaan Kembali * (Wajib untuk ISO Audit)</label>
                        <textarea wire:model="bukaBukuAlasan" rows="3" class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white" placeholder="Contoh: Revisi faktur pajak masukan berdasarkan temuan audit internal..."></textarea>
                    </div>
                </div>

                <div class="mt-5 flex gap-3 border-t border-black/10 dark:border-white/10 pt-4">
                    <button type="button" wire:click="$set('showBukaBukuModal', false)" class="flex-1 py-2.5 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 text-ink-600 dark:text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">
                        Batal
                    </button>
                    <button type="button" wire:click="eksekusiBukaBuku" class="flex-1 py-2.5 rounded-xl bg-up-red hover:bg-up-red/90 text-white font-bold text-xs shadow-md shadow-up-red/25 cursor-pointer min-h-[44px]">
                        Konfirmasi Buka Kembali
                    </button>
                </div>
            </x-prism.glass-card>
        </div>
    @endif

    <!-- MODAL: REVERSAL / PEMBALIK JURNAL (ISO / AUDIT) -->
    @if($showReversalModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-sm p-4 overflow-y-auto">
            <x-prism.glass-card
                title="Pembalik Jurnal (Reversal / Storno)"
                subtitle="Kepatuhan ISO: Jurnal posted tidak dapat dihapus/diedit. Koreksi dilakukan dengan membalik posisi Debit & Kredit."
                class="w-full max-w-lg flex flex-col my-auto"
            >
                <x-slot:action>
                    <button wire:click="$set('showReversalModal', false)" class="text-ink-400 hover:text-white p-2 cursor-pointer">✕</button>
                </x-slot:action>

                <div class="space-y-4">
                    <div class="p-3.5 rounded-xl bg-up-amber/10 border border-up-amber/30 text-xs text-up-amber space-y-1">
                        <p class="font-bold">INFORMASI REVERSAL:</p>
                        <p>Nomor Jurnal Asal: <strong class="font-mono text-white">{{ $reversalNoJurnal }}</strong></p>
                        <p>Sistem akan membuat jurnal baru dengan tanggal hari ini yang membalik seluruh baris transaksi (Debit menjadi Kredit, Kredit menjadi Debit).</p>
                        <p>Status jurnal asal akan ditandai <span class="text-up-red font-bold">DIBALIK</span> dan tidak dapat di-reversal ulang.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Alasan Pembalikan Jurnal * (Wajib untuk Rekam Audit ISO)</label>
                        <textarea wire:model="reversalAlasan" rows="3" class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white" placeholder="Contoh: Kesalahan pemilihan akun kas/bank pada saat input POS..."></textarea>
                    </div>
                </div>

                <div class="mt-5 flex gap-3 border-t border-black/10 dark:border-white/10 pt-4">
                    <button type="button" wire:click="$set('showReversalModal', false)" class="flex-1 py-2.5 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 text-ink-600 dark:text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">
                        Batal
                    </button>
                    <button type="button" wire:click="eksekusiReversal" class="flex-1 py-2.5 rounded-xl bg-up-red hover:bg-up-red/90 text-white font-bold text-xs shadow-md shadow-up-red/25 cursor-pointer min-h-[44px]">
                        Konfirmasi Balik Jurnal
                    </button>
                </div>
            </x-prism.glass-card>
        </div>
    @endif

    <!-- MODAL: UPLOAD REKENING KORAN BANK MULTI-FORMAT (PDF, CSV, XLSX) -->
    @if($showUploadRekonModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-sm p-4 overflow-y-auto">
            <x-prism.glass-card
                title="Import Rekening Koran Bank"
                subtitle="Dukung format e-Statement PDF (BCA, Mandiri, BRI, BNI), CSV KlikBCA, dan Excel."
                class="w-full max-w-lg flex flex-col my-auto"
            >
                <x-slot:action>
                    <button wire:click="$set('showUploadRekonModal', false)" class="text-ink-400 hover:text-white p-2 cursor-pointer">✕</button>
                </x-slot:action>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Bank</label>
                        <select wire:model="rekonNamaBank" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium text-white">
                            <option value="BCA">Bank Central Asia (BCA)</option>
                            <option value="Mandiri">Bank Mandiri</option>
                            <option value="BRI">Bank BRI</option>
                            <option value="BNI">Bank BNI</option>
                            <option value="Lainnya">Bank Lainnya</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Akun Buku Besar Bank di Sistem *</label>
                        <select wire:model="rekonAkunId" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium text-white">
                            <option value="">-- Pilih Akun Bank --</option>
                            @foreach($akunBankOptions as $ab)
                                <option value="{{ $ab->id }}">{{ $ab->kode }} - {{ $ab->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nomor Rekening (Opsional)</label>
                        <input type="text" wire:model="rekonNomorRekening" placeholder="Contoh: 8010293841" class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white" />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Berkas Rekening Koran (PDF / CSV / XLSX) *</label>
                        <input type="file" wire:model="rekonFile" accept=".pdf,.csv,.txt,.xlsx,.xls" class="w-full text-xs text-ink-300 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-up-primary file:text-white hover:file:bg-up-primary/80 cursor-pointer" />
                        <div wire:loading wire:target="rekonFile" class="text-xs text-up-amber mt-1">Mengunggah dan membaca berkas...</div>
                        <p class="text-[11px] text-ink-400 mt-1">Sistem cerdas akan otomatis mengekstrak tanggal, mutasi DB/CR, dan nominal transaksi dari layout berkas.</p>
                    </div>
                </div>

                <div class="mt-5 flex gap-3 border-t border-black/10 dark:border-white/10 pt-4">
                    <button type="button" wire:click="$set('showUploadRekonModal', false)" class="flex-1 py-2.5 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">
                        Batal
                    </button>
                    <button type="button" wire:click="uploadRekonsiliasiBank" wire:loading.attr="disabled" class="flex-1 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs shadow-md shadow-up-primary/25 cursor-pointer min-h-[44px]">
                        Mulai Analisa & Matching
                    </button>
                </div>
            </x-prism.glass-card>
        </div>
    @endif

    <!-- MODAL: PENYESUAIAN ITEM REKONSILIASI (BIAYA ADMIN / BUNGA) -->
    @if($penyesuaianModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-sm p-4 overflow-y-auto">
            <x-prism.glass-card
                title="Jurnal Penyesuaian Rekonsiliasi Bank"
                subtitle="Posting jurnal 1-klik untuk menyelesaikan selisih mutasi rekening koran."
                class="w-full max-w-md flex flex-col my-auto"
            >
                <x-slot:action>
                    <button wire:click="$set('penyesuaianModalOpen', false)" class="text-ink-400 hover:text-white p-2 cursor-pointer">✕</button>
                </x-slot:action>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Jenis Penyesuaian</label>
                        <select wire:model="penyesuaianJenis" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium text-white">
                            <option value="biaya_admin">Biaya Administrasi Bank (Beban)</option>
                            <option value="pendapatan_bunga">Pendapatan Bunga Bank (Pendapatan)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Keterangan Tambahan (Opsional)</label>
                        <textarea wire:model="penyesuaianKeterangan" rows="2" class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white" placeholder="Contoh: Biaya admin bulanan kartu debit BCA..."></textarea>
                    </div>
                </div>

                <div class="mt-5 flex gap-3 border-t border-black/10 dark:border-white/10 pt-4">
                    <button type="button" wire:click="$set('penyesuaianModalOpen', false)" class="flex-1 py-2.5 rounded-xl bg-black/5 dark:bg-white/5 hover:bg-black/10 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">
                        Batal
                    </button>
                    <button type="button" wire:click="eksekusiPenyesuaian" class="flex-1 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs shadow-md shadow-up-primary/25 cursor-pointer min-h-[44px]">
                        Posting Penyesuaian
                    </button>
                </div>
            </x-prism.glass-card>
        </div>
    @endif

    <script nonce="{{ request()->attributes->get(\'csp_nonce\') }}">
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('tab-changed', (event) => {
                if (event.url && window.location.href !== event.url) {
                    window.history.pushState(null, '', event.url);
                }
            });

            window.addEventListener('popstate', () => {
                const parts = window.location.pathname.split('/');
                const last = parts[parts.length - 1];
                if (last && @this) {
                    @this.call('pilihTab', last);
                }
            });
        });
    </script>
</div>