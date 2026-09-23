<div class="space-y-6">
    <!-- Header + Periode + Export -->
    <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3 border-b border-white/5 pb-4">
        <div>
            <h2 class="text-lg font-semibold text-white tracking-wide">Laporan Pajak Bulanan</h2>
            <p class="text-xs text-ink-400 mt-0.5">Rekap PPN keluaran &amp; masukan — siap ekspor e-Faktur</p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <label class="text-[11px] text-ink-400 font-medium">Periode</label>
            <input type="date" wire:model.live="periodeDari" class="px-2.5 py-2 rounded-lg glass-input text-xs font-medium" />
            <span class="text-ink-400 text-xs">—</span>
            <input type="date" wire:model.live="periodeSampai" class="px-2.5 py-2 rounded-lg glass-input text-xs font-medium" />
            <x-prism.prism-button variant="accent" size="sm" wire:click="exportPajak">
                Export Excel (Queue)
            </x-prism.prism-button>
        </div>
    </div>

    <!-- Status config PPN cabang -->
    <div class="flex items-center gap-2 text-xs">
        <span class="px-2.5 py-1 rounded-lg {{ $config['enabled'] ? 'bg-up-mint/15 text-up-mint border border-up-mint/30' : 'bg-white/5 text-ink-400 border border-white/10' }}">
            PPN {{ $config['enabled'] ? 'AKTIF' : 'NONAKTIF' }} — {{ number_format($config['percent'], 0, ',', '.') }}%
        </span>
        <span class="text-ink-500">Berlaku utk transaksi baru di cabang aktif</span>
    </div>

    <!-- Ringkasan -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-prism.glass-card title="PPN Keluaran" :subtitle="'Periode ' . $periodeDari . ' — ' . $periodeSampai" circuit="true" padding="p-5">
            <p class="text-2xl font-black text-white tabular-nums">Rp {{ number_format($rekap['keluaran_ppn'], 0, ',', '.') }}</p>
            <p class="text-[11px] text-ink-500 mt-1">DPP: Rp {{ number_format($rekap['keluaran_dpp'], 0, ',', '.') }} · {{ $rekap['jumlah_transaksi'] }} transaksi</p>
        </x-prism.glass-card>

        <x-prism.glass-card title="PPN Masukan" :subtitle="'Akun 110-03 · periode sama'" circuit="true" padding="p-5">
            <p class="text-2xl font-black text-up-amber tabular-nums">Rp {{ number_format($rekap['masukan'], 0, ',', '.') }}</p>
            <p class="text-[11px] text-ink-500 mt-1">Dari jurnal PPN Masukan</p>
        </x-prism.glass-card>

        <x-prism.glass-card title="PPN Terutang" subtitle="Keluaran − Masukan" circuit="true" padding="p-5">
            <p class="text-2xl font-black {{ $rekap['terutang'] >= 0 ? 'text-up-mint' : 'text-up-red' }} tabular-nums">
                Rp {{ number_format(abs($rekap['terutang']), 0, ',', '.') }}
            </p>
            <p class="text-[11px] text-ink-500 mt-1">{{ $rekap['terutang'] >= 0 ? 'Harus disetor' : 'Surplus' }}</p>
        </x-prism.glass-card>

        <x-prism.glass-card title="Cache" subtitle="Rekap di-cache 15 menit" circuit="true" padding="p-5">
            <p class="text-[11px] text-ink-400 leading-relaxed">
                Rekap berat di-cache per cabang + periode. Export berjalan via antrian (queue) — tidak memblokir request (RAM 1GB).
            </p>
        </x-prism.glass-card>
    </div>

    <!-- Tabel detail keluaran -->
    <x-prism.glass-card title="Detail PPN Keluaran" subtitle="Transaksi dengan PPN pada periode terpilih" circuit="true">
        <x-prism.data-table :headers="['Tanggal', 'No Transaksi', 'DPP (Rp)', '%', 'PPN (Rp)']">
            @forelse($keluaranRows as $row)
                <tr class="hover:bg-white/[0.03]">
                    <td class="py-3 px-4 text-xs text-ink-300">{{ $row['tanggal'] }}</td>
                    <td class="py-3 px-4 text-xs font-mono text-white">{{ $row['no_transaksi'] }}</td>
                    <td class="py-3 px-4 text-xs text-white tabular-nums text-right">{{ number_format($row['dpp'], 0, ',', '.') }}</td>
                    <td class="py-3 px-4 text-xs text-ink-400 tabular-nums text-right">{{ number_format($row['percent'], 2, ',', '.') }}%</td>
                    <td class="py-3 px-4 text-xs font-bold text-up-mint tabular-nums text-right">{{ number_format($row['ppn'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="py-6 text-center text-[11px] text-ink-500">Belum ada transaksi ber-PPN pada periode ini.</td>
                </tr>
            @endforelse
            @slot('mobileCards')
                @forelse($keluaranRows as $row)
                    <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                        <div class="flex justify-between text-xs">
                            <span class="font-mono text-white">{{ $row['no_transaksi'] }}</span>
                            <span class="text-ink-400">{{ $row['tanggal'] }}</span>
                        </div>
                        <div class="flex justify-between text-[11px] mt-1.5">
                            <span class="text-ink-400">DPP <span class="tabular-nums text-white">Rp {{ number_format($row['dpp'], 0, ',', '.') }}</span></span>
                            <span class="text-up-mint font-bold tabular-nums">Rp {{ number_format($row['ppn'], 0, ',', '.') }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-[11px] text-ink-500 text-center py-4">Belum ada transaksi ber-PPN.</p>
                @endforelse
            @endslot
        </x-prism.data-table>
    </x-prism.glass-card>
</div>
