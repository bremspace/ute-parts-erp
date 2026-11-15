<div class="space-y-6">
    <div class="flex flex-wrap items-center gap-2">
        <select wire:model.live="rentang" class="px-3 py-2.5 rounded-xl glass-input text-xs font-medium min-h-[44px]">
            <option value="7_hari">7 Hari Terakhir</option>
            <option value="30_hari">30 Hari Terakhir</option>
            <option value="90_hari">90 Hari Terakhir</option>
            <option value="semua">Semua</option>
        </select>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
        @php
            $cards = [
                ['label' => 'Total Komplain', 'value' => $total, 'color' => 'text-white'],
                ['label' => 'Garansi Berlaku', 'value' => $garansiBerlaku, 'color' => 'text-up-mint'],
                ['label' => 'Gratis Disetujui', 'value' => $gratisDisetujui, 'color' => 'text-up-mint'],
                ['label' => 'Goodwill Disetujui', 'value' => $goodwillDisetujui, 'color' => 'text-up-amber'],
                ['label' => 'SLA Overdue', 'value' => $overdue, 'color' => 'text-up-red'],
                ['label' => 'Eskalasi', 'value' => $eskalasi, 'color' => 'text-up-red'],
            ];
        @endphp

        @foreach($cards as $card)
            <div class="glass-panel rounded-2xl p-4 border border-white/10">
                <p class="text-[10px] uppercase tracking-wider text-ink-400 font-semibold">{{ $card['label'] }}</p>
                <p class="text-2xl font-bold tabular-nums {{ $card['color'] }} mt-1">{{ $card['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="glass-panel rounded-2xl p-5 border border-white/10">
            <h3 class="text-sm font-bold text-white mb-3">Komplain Berulang per Teknisi</h3>
            <div class="space-y-2">
                @forelse($berulangTeknisi as $row)
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs text-ink-200 truncate">{{ $row['nama'] }}</span>
                        <span class="px-2 py-0.5 rounded-full bg-up-red/15 text-up-red text-[10px] font-bold">{{ $row['jumlah'] }}×</span>
                    </div>
                @empty
                    <p class="text-xs text-ink-400">Tidak ada komplain berulang.</p>
                @endforelse
            </div>
        </div>

        <div class="glass-panel rounded-2xl p-5 border border-white/10">
            <h3 class="text-sm font-bold text-white mb-3">Komplain per Jenis HP</h3>
            <div class="space-y-2">
                @forelse($perJenis as $row)
                    <div class="space-y-1">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs text-ink-200 truncate">{{ $row['nama'] }}</span>
                            <span class="text-[10px] font-bold text-ink-300 tabular-nums">{{ $row['jumlah'] }}</span>
                        </div>
                        <div class="h-1.5 rounded-full bg-white/5 overflow-hidden">
                            <div class="h-full bg-up-primary rounded-full" style="width: {{ $total > 0 ? min(100, round($row['jumlah'] / $total * 100)) : 0 }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-ink-400">Belum ada data.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="glass-panel rounded-2xl border border-white/10 overflow-hidden">
        <div class="px-5 py-4 border-b border-white/10">
            <h3 class="text-sm font-bold text-white">Komplain Terbaru</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-white/5 text-ink-400 uppercase text-[10px] tracking-wider">
                    <tr>
                        <th class="px-4 py-2.5">Tiket</th>
                        <th class="px-4 py-2.5">Asal</th>
                        <th class="px-4 py-2.5">HP</th>
                        <th class="px-4 py-2.5">Garansi</th>
                        <th class="px-4 py-2.5">Gratis</th>
                        <th class="px-4 py-2.5">Goodwill</th>
                        <th class="px-4 py-2.5">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 text-ink-200">
                    @forelse($terbaru as $t)
                        <tr>
                            <td class="px-4 py-2.5 font-semibold text-white">{{ $t->no_tiket }}</td>
                            <td class="px-4 py-2.5">{{ $t->tiketLama?->no_tiket ?? '-' }}</td>
                            <td class="px-4 py-2.5">{{ $t->jenis_hp }}</td>
                            <td class="px-4 py-2.5">{{ $t->garansi_berlaku ? 'Berlaku' : 'Habis' }}</td>
                            <td class="px-4 py-2.5">
                                @if($t->komplain_gratis)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $t->komplain_gratis_approved_at ? 'bg-up-mint/15 text-up-mint' : 'bg-up-amber/15 text-up-amber' }}">
                                        {{ $t->komplain_gratis_approved_at ? 'Disetujui' : 'Pengajuan' }}
                                    </span>
                                @else
                                    <span class="text-ink-500">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                @if($t->goodwill_approved_at)
                                    <span class="text-up-amber font-semibold">Rp {{ number_format((float) $t->goodwill_amount, 0, ',', '.') }}</span>
                                @elseif($t->goodwill_proposed_at)
                                    <span class="text-up-amber/80">Diajukan</span>
                                @else
                                    <span class="text-ink-500">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">{{ $t->status }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-ink-400">Belum ada komplain pasca-servis.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
