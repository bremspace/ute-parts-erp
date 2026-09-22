<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 border-b border-white/5 pb-4">
        <div class="flex items-center gap-2">
            <button wire:click="$set('activeTab', 'reseller')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $activeTab === 'reseller' ? 'bg-up-accent text-white shadow-md shadow-up-accent/25' : 'bg-white/5 text-ink-300 hover:bg-white/10' }}">Dashboard Reseller</button>
            <button wire:click="$set('activeTab', 'komisi')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $activeTab === 'komisi' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/5 text-ink-300 hover:bg-white/10' }}">
                Komisi <span class="ml-1 tabular-nums">{{ $komisiList->where('status', 'pending')->count() }}</span>
            </button>
            <button wire:click="$set('activeTab', 'skema')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $activeTab === 'skema' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/5 text-ink-300 hover:bg-white/10' }}">Skema Komisi</button>
        </div>

        @if($activeTab === 'komisi')
            <div class="flex items-center gap-2">
                <span class="text-[11px] text-ink-400 font-medium">
                    {{ count($selectedKomisiIds) }} terpilih
                </span>
                <button wire:click="prosesApproval('reject')" class="px-3 py-2 rounded-xl bg-up-red/15 hover:bg-up-red/25 text-up-red font-bold text-xs border border-up-red/30 transition-all cursor-pointer">Tolak Terpilih</button>
                <button wire:click="prosesApproval('approve')" class="px-3 py-2 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-all cursor-pointer">Setujui Terpilih → Jurnal & Utang</button>
            </div>
        @elseif($activeTab === 'skema')
            <button wire:click="openSkemaModal" class="px-4 py-2 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs cursor-pointer">+ Tambah Skema</button>
        @endif
    </div>

    <!-- TAB: Reseller Dashboard -->
    @if($activeTab === 'reseller')
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            @forelse($resellers as $r)
                <div class="glass-panel glass-panel-hover rounded-2xl p-5 relative overflow-hidden">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <h4 class="font-bold text-white">{{ $r['nama'] }}</h4>
                            <p class="text-xs text-ink-400 font-mono mt-0.5">{{ $r['telepon'] ?? '-' }}</p>
                        </div>
                        <x-prism.tier-badge :tier="$r['tier'] ?? 'Reseller'" />
                    </div>

                    <div class="grid grid-cols-3 gap-2 text-center mt-3">
                        <div class="p-2.5 rounded-xl bg-white/[0.02] border border-white/5">
                            <p class="text-sm font-bold text-white tabular-nums">Rp {{ number_format($r['total_belanja'], 0, ',', '.') }}</p>
                            <p class="text-[10px] text-ink-400 uppercase">Omzet</p>
                        </div>
                        <div class="p-2.5 rounded-xl bg-up-amber/5 border border-up-amber/20">
                            <p class="text-sm font-bold text-up-amber tabular-nums">Rp {{ number_format($r['komisi_pending'], 0, ',', '.') }}</p>
                            <p class="text-[10px] text-ink-400 uppercase">Pending</p>
                        </div>
                        <div class="p-2.5 rounded-xl bg-up-mint/5 border border-up-mint/20">
                            <p class="text-sm font-bold text-up-mint tabular-nums">Rp {{ number_format($r['komisi_terhutang'], 0, ',', '.') }}</p>
                            <p class="text-[10px] text-ink-400 uppercase">Terhutang</p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-16 text-ink-400">
                    <svg class="w-10 h-10 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0" />
                    </svg>
                    Belum ada reseller terdaftar. Tandai pelanggan sebagai reseller di modul CRM.
                </div>
            @endforelse
        </div>
    @endif

    <!-- TAB: Komisi -->
    @if($activeTab === 'komisi')
        <div class="flex items-center gap-2 mb-4">
            <select wire:model.live="filterStatus" class="px-3 py-2 rounded-xl glass-input text-xs font-medium w-44">
                <option value="" class="bg-ink-900">Semua Status</option>
                <option value="pending" class="bg-ink-900">Pending</option>
                <option value="disetujui" class="bg-ink-900">Disetujui</option>
                <option value="ditolak" class="bg-ink-900">Ditolak</option>
            </select>
        </div>

        <x-prism.data-table :headers="['', 'No. Komisi', 'Reseller', 'Transaksi', 'Jumlah Transaksi', 'Komisi', 'Status', 'Tanggal']">
            @forelse($komisiList as $k)
                <tr class="hover:bg-white/[0.02] transition-colors">
                    <td class="py-3.5 px-4">
                        @if($k->status === 'pending')
                            <input type="checkbox" wire:change="toggleKomisi({{ $k->id }})" {{ in_array($k->id, $selectedKomisiIds, true) ? 'checked' : '' }} class="accent-up-mint w-4 h-4 cursor-pointer" />
                        @endif
                    </td>
                    <td class="py-3.5 px-4 font-mono font-bold text-white">{{ $k->no_komisi }}</td>
                    <td class="py-3.5 px-4 text-ink-200 font-medium">{{ $k->pelanggan?->nama }}</td>
                    <td class="py-3.5 px-4 text-ink-400 text-xs">{{ $k->transaksi?->no_transaksi ?? '-' }}</td>
                    <td class="py-3.5 px-4 tabular-nums text-ink-200 text-xs">Rp {{ number_format($k->jumlah_transaksi, 0, ',', '.') }}</td>
                    <td class="py-3.5 px-4 tabular-nums font-bold text-up-accent">Rp {{ number_format($k->nominal_komisi, 0, ',', '.') }}</td>
                    <td class="py-3.5 px-4"><x-prism.status-pill :status="$k->status" /></td>
                    <td class="py-3.5 px-4 text-ink-400 text-xs">{{ $k->created_at->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="py-12 text-center text-ink-400">
                        <svg class="w-10 h-10 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                        </svg>
                        Tidak ada data komisi.
                    </td>
                </tr>
            @endforelse

            <x-slot:mobileCards>
                @foreach($komisiList as $k)
                    <div class="p-3.5 rounded-xl bg-white/[0.03] border border-white/5 space-y-2">
                        <div class="flex items-center justify-between">
                            @if($k->status === 'pending')
                                <input type="checkbox" wire:change="toggleKomisi({{ $k->id }})" {{ in_array($k->id, $selectedKomisiIds, true) ? 'checked' : '' }} class="accent-up-mint w-4 h-4 cursor-pointer" />
                            @endif
                            <span class="font-mono font-bold text-white">{{ $k->no_komisi }}</span>
                            <x-prism.status-pill :status="$k->status" />
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-ink-300">{{ $k->pelanggan?->nama }}</span>
                            <span class="font-bold text-up-accent tabular-nums">Rp {{ number_format($k->nominal_komisi, 0, ',', '.') }}</span>
                        </div>
                    </div>
                @endforeach
            </x-slot:mobileCards>
        </x-prism.data-table>
    @endif

    <!-- TAB: Skema Komisi -->
    @if($activeTab === 'skema')
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($skemaList as $s)
                <div class="glass-panel rounded-2xl p-5">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-bold text-white">{{ $s->nama }}</h4>
                        <x-prism.status-pill :status="$s->is_active ? 'aktif' : 'draft'" />
                    </div>
                    <p class="text-xs text-ink-400 mb-3">Kategori: <strong class="text-ink-200">{{ $s->kategori ?? 'Semua' }}</strong></p>
                    <div class="p-3 rounded-xl bg-white/[0.02] border border-white/5 text-center">
                        <span class="text-xl font-black {{ $s->tipe === 'persen' ? 'text-up-primary' : 'text-up-accent' }} tabular-nums">
                            {{ $s->tipe === 'persen' ? $s->nilai.'%' : 'Rp '.number_format($s->nilai, 0, ',', '.') }}
                        </span>
                        <span class="block text-[10px] text-ink-400 uppercase mt-0.5">{{ $s->tipe === 'persen' ? 'dari transaksi' : 'per item terjual' }}</span>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-16 text-ink-400">
                    Belum ada skema komisi. Tambahkan untuk mengaktifkan komisi reseller.
                </div>
            @endforelse
        </div>
    @endif

    <!-- MODAL: Skema Komisi -->
    @if($showSkemaModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Tambah Skema Komisi</h3>
                    <button wire:click="$set('showSkemaModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Skema *</label>
                        <input type="text" wire:model="skemaForm.nama" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="Komisi LCD" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kategori (kosongkan = semua)</label>
                        <input type="text" wire:model="skemaForm.kategori" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="LCD / Layar" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Tipe *</label>
                            <select wire:model="skemaForm.tipe" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="persen" class="bg-ink-900">Persen (%)</option>
                                <option value="nominal" class="bg-ink-900">Nominal (Rp)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nilai *</label>
                            <input type="number" wire:model="skemaForm.nilai" step="500" min="0" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-bold tabular-nums" />
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showSkemaModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="simpanSkema" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer min-h-[44px]">Simpan Skema</button>
                </div>
            </div>
        </div>
    @endif
</div>