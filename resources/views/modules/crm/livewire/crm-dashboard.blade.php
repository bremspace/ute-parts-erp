<div class="space-y-6">
    <!-- Header + Filters -->
    <div class="flex flex-col gap-4 border-b border-white/5 pb-4">
        <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between">
            <div class="flex-1">
                <x-prism.barcode-scan-input placeholder="Cari nama, telepon, atau email pelanggan..." model="search" />
            </div>
            <div class="flex items-center gap-2">
                <select wire:model.live="filterTierId" class="px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                    <option value="" class="bg-ink-900">Semua Tier</option>
                    @foreach($tiers as $t)
                        <option value="{{ $t->id }}" class="bg-ink-900">{{ $t->nama }}</option>
                    @endforeach
                </select>
                <label class="flex items-center gap-2 text-xs text-ink-300 font-medium cursor-pointer select-none px-3 py-2.5 rounded-xl bg-white/5 border border-white/10">
                    <input type="checkbox" wire:model.live="filterReseller" class="accent-up-accent w-3.5 h-3.5" />
                    Reseller
                </label>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <x-prism.prism-button variant="mint" size="sm" wire:click="$set('showPelangganBaruModal', true)">
                + Tambah Pelanggan
            </x-prism.prism-button>
            <x-prism.prism-button variant="primary" size="sm" wire:click="openTierModal()">
                + Tambah Tier
            </x-prism.prism-button>
            <x-prism.prism-button variant="ghost" size="sm" wire:click="recalcTier">
                ⟳ Rekalkulasi Tier
            </x-prism.prism-button>
            <x-prism.prism-button variant="accent" size="sm" wire:click="$set('showBroadcastModal', true)">
                📢 Broadcast Promo
            </x-prism.prism-button>
        </div>
    </div>

    <!-- TAB: Pelanggan (DataTable dual-mode) -->
    <x-prism.data-table :headers="['Pelanggan', 'Telepon', 'Tier', 'Belanja 12 Bulan', 'Poin', 'Status', '']">
        @forelse($customers as $c)
            <tr class="hover:bg-white/[0.02] transition-colors">
                <td class="py-3.5 px-4 font-semibold text-white">
                    {{ $c->nama }}
                    @if($c->email)<span class="block text-[11px] text-ink-400 font-normal">{{ $c->email }}</span>@endif
                </td>
                <td class="py-3.5 px-4 text-xs text-ink-300 font-mono">{{ $c->telepon ?? '-' }}</td>
                <td class="py-3.5 px-4">
                    <x-prism.tier-badge :tier="$c->tierMembership?->nama ?? ($c->is_reseller ? 'Reseller' : 'Retail')" />
                </td>
                <td class="py-3.5 px-4 tabular-nums font-semibold text-white text-sm">
                    Rp {{ number_format($c->total_belanja_12bulan, 0, ',', '.') }}
                </td>
                <td class="py-3.5 px-4 tabular-nums text-ink-200 text-sm">{{ number_format($c->poin_loyalty) }} pts</td>
                <td class="py-3.5 px-4">
                    @if($c->is_reseller)
                        <span class="text-[10px] font-bold text-up-accent bg-up-accent/10 border border-up-accent/30 px-2 py-0.5 rounded-full">RESELLER</span>
                    @else
                        <span class="text-[10px] text-ink-400">Member</span>
                    @endif
                </td>
                <td class="py-3.5 px-4">
                    <button wire:click="$set('selectedCustomerId', {{ $c->id }})" class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-up-primary/20 hover:text-white text-ink-300 font-semibold text-[11px] transition-all cursor-pointer">Detail 360°</button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="py-12 text-center text-ink-400">
                    <svg class="w-10 h-10 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857" />
                    </svg>
                    Belum ada pelanggan yang cocok
                </td>
            </tr>
        @endforelse

        <x-slot:mobileCards>
            @foreach($customers as $c)
                <div class="p-3.5 rounded-xl bg-white/[0.03] border border-white/5 space-y-2">
                    <div class="flex items-start justify-between">
                        <div>
                            <h4 class="font-bold text-white text-xs">{{ $c->nama }}</h4>
                            <span class="text-[10px] text-ink-400 font-mono">{{ $c->telepon ?? '-' }}</span>
                        </div>
                        <x-prism.tier-badge :tier="$c->tierMembership?->nama ?? ($c->is_reseller ? 'Reseller' : 'Retail')" />
                    </div>
                    <div class="flex justify-between items-center text-xs pt-2 border-t border-white/5">
                        <span class="text-ink-400">Belanja 12 bln</span>
                        <span class="font-bold text-white tabular-nums">Rp {{ number_format($c->total_belanja_12bulan, 0, ',', '.') }}</span>
                    </div>
                    <button wire:click="$set('selectedCustomerId', {{ $c->id }})" class="w-full py-2 rounded-lg bg-up-primary/10 hover:bg-up-primary/20 text-up-primary font-bold text-[11px] transition-all cursor-pointer">Lihat Detail 360°</button>
                </div>
            @endforeach
        </x-slot:mobileCards>

        <x-slot:pagination>{{ $customers->links() }}</x-slot:pagination>
    </x-prism.data-table>

    <!-- MODAL: TIER -->
    @if($showTierModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">{{ $tierForm['id'] ? 'Edit Tier' : 'Tambah Tier Baru' }}</h3>
                    <button wire:click="$set('showTierModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Tier *</label>
                            <input type="text" wire:model="tierForm.nama" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="Gold" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kode *</label>
                            <input type="text" wire:model="tierForm.kode" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="gold" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Min. Belanja 12 Bulan (Rp) *</label>
                        <input type="text" inputmode="numeric" x-format-number wire:model="tierForm.min_belanja_12bulan" min="0" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-bold tabular-nums" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Diskon %</label>
                            <input type="number" wire:model="tierForm.diskon_persen" step="0.5" min="0" max="100" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-bold tabular-nums" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Poin Multiplier</label>
                            <input type="number" wire:model="tierForm.poin_multiplier" step="0.1" min="0" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-bold tabular-nums" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Urutan</label>
                            <input type="number" wire:model="tierForm.urutan" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                        </div>
                        <div class="flex items-end pb-2">
                            <label class="flex items-center gap-2 text-xs text-ink-300 cursor-pointer">
                                <input type="checkbox" wire:model="tierForm.is_active" class="accent-up-mint w-4 h-4" />
                                Aktif
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showTierModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="simpanTier" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer min-h-[44px]">Simpan Tier</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: BROADCAST -->
    @if($showBroadcastModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Broadcast Promo</h3>
                    <button wire:click="$set('showBroadcastModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Judul *</label>
                        <input type="text" wire:model="broadcastJudul" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="Promo Akhir Bulan" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Pesan *</label>
                        <textarea wire:model="broadcastPesan" rows="3" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="Diskon LCD 10% untuk member Gold..."></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Segmentasi Tier</label>
                            <select wire:model="broadcastTierId" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="" class="bg-ink-900">Semua Tier</option>
                                @foreach($tiers as $t)
                                    <option value="{{ $t->id }}" class="bg-ink-900">{{ $t->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end pb-2">
                            <label class="flex items-center gap-2 text-xs text-ink-300 cursor-pointer">
                                <input type="checkbox" wire:model="broadcastReseller" class="accent-up-accent w-4 h-4" />
                                Hanya Reseller
                            </label>
                        </div>
                    </div>
                    <div class="p-3 rounded-xl bg-up-primary/10 border border-up-primary/30 text-[11px] text-ink-200">
                        Broadcast dikirim <strong>via antrian (queue)</strong> — tidak memperlambat sistem.
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showBroadcastModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="kirimBroadcast" class="flex-1 py-3 rounded-xl bg-up-accent text-white font-bold text-xs cursor-pointer min-h-[44px]">Kirim Broadcast</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: DETAIL 360° -->
    @if($selectedCustomer && $selectedCustomerId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-2xl glass-panel p-6 rounded-3xl relative max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Profil Pelanggan 360°</h3>
                    <button wire:click="$set('selectedCustomerId', null)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="overflow-y-auto pr-1 flex-1 space-y-4">
                    @php $p = $selectedCustomer['pelanggan']; @endphp
                    <!-- Identitas -->
                    <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/5">
                        <div class="flex items-start justify-between">
                            <div>
                                <h4 class="font-bold text-white text-base">{{ $p->nama }}</h4>
                                <p class="text-xs text-ink-400 mt-0.5 font-mono">{{ $p->telepon ?? '-' }}</p>
                                @if($p->email)<p class="text-xs text-ink-400 font-mono">{{ $p->email }}</p>@endif
                            </div>
                            <x-prism.tier-badge :tier="$p->tierMembership?->nama ?? ($p->is_reseller ? 'Reseller' : 'Retail')" />
                        </div>

                        <!-- Progress tier -->
                        @if($selectedCustomer['tier_berikutnya'])
                            <div class="mt-4">
                                <div class="flex justify-between text-[11px] text-ink-300 mb-1.5">
                                    <span>Progress ke <strong class="text-up-primary">{{ $selectedCustomer['tier_berikutnya'] }}</strong></span>
                                    <span class="font-bold tabular-nums">{{ number_format($selectedCustomer['progress_tier'], 1) }}%</span>
                                </div>
                                <div class="h-2.5 rounded-full bg-white/5 overflow-hidden border border-white/10">
                                    <div class="h-full rounded-full bg-gradient-to-r from-up-primary via-indigo-500 to-up-accent transition-all"
                                         style="width: {{ $selectedCustomer['progress_tier'] }}%"></div>
                                </div>
                                <p class="text-[11px] text-up-amber mt-1.5">Sisa belanja menuju tier berikutnya: <strong class="tabular-nums">Rp {{ number_format($selectedCustomer['sisa_belanja'], 0, ',', '.') }}</strong></p>
                            </div>
                        @endif

                        <div class="grid grid-cols-3 gap-2 mt-4 text-center">
                            <div class="p-2.5 rounded-xl bg-white/[0.02] border border-white/5">
                                <p class="text-sm font-bold text-white tabular-nums">{{ number_format($p->poin_loyalty) }}</p>
                                <p class="text-[10px] text-ink-400 uppercase">Poin</p>
                            </div>
                            <div class="p-2.5 rounded-xl bg-white/[0.02] border border-white/5">
                                <p class="text-sm font-bold text-white tabular-nums">Rp {{ number_format($p->total_belanja_12bulan, 0, ',', '.') }}</p>
                                <p class="text-[10px] text-ink-400 uppercase">Belanja/12bln</p>
                            </div>
                            <div class="p-2.5 rounded-xl bg-white/[0.02] border border-white/5">
                                <p class="text-sm font-bold {{ $p->is_reseller ? 'text-up-accent' : 'text-ink-100' }}">{{ $p->is_reseller ? 'Ya' : 'Tidak' }}</p>
                                <p class="text-[10px] text-ink-400 uppercase">Reseller</p>
                            </div>
                        </div>
                    </div>

                    <!-- Riwayat Pembelian -->
                    <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/5">
                        <p class="text-[10px] text-ink-400 uppercase font-bold mb-2">Riwayat Pembelian</p>
                        @forelse($selectedCustomer['transaksi'] as $trx)
                            <div class="flex justify-between text-xs py-1.5 border-b border-white/5 last:border-0">
                                <span class="font-mono text-up-primary">{{ $trx->no_transaksi }}</span>
                                <span class="text-ink-400">{{ $trx->created_at->format('d/m/Y') }}</span>
                                <span class="font-bold text-white tabular-nums">Rp {{ number_format($trx->total_akhir, 0, ',', '.') }}</span>
                            </div>
                        @empty
                            <p class="text-[11px] text-ink-500 py-2">Belum ada transaksi.</p>
                        @endforelse
                    </div>

                    <!-- Riwayat Servis -->
                    <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/5">
                        <p class="text-[10px] text-ink-400 uppercase font-bold mb-2">Riwayat Servis</p>
                        @forelse($selectedCustomer['servis'] as $sv)
                            <div class="flex items-center justify-between text-xs py-1.5 border-b border-white/5 last:border-0 gap-2">
                                <div class="min-w-0">
                                    <span class="font-mono text-up-primary">{{ $sv->no_tiket }}</span>
                                    <span class="text-ink-300 ml-2">{{ $sv->jenis_hp }}</span>
                                </div>
                                <div class="flex items-center gap-1.5 flex-shrink-0">
                                    <x-prism.status-pill :status="$sv->status" />
                                    @if($sv->garansi)
                                        <span class="text-[9px] font-bold text-up-mint bg-up-mint/10 border border-up-mint/30 px-1.5 py-0.5 rounded-full">Garansi</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-[11px] text-ink-500 py-2">Belum ada riwayat servis.</p>
                        @endforelse
                    </div>

                    <!-- Komisi (reseller) -->
                    @if($p->is_reseller)
                        <div class="p-4 rounded-2xl bg-up-accent/5 border border-up-accent/20">
                            <p class="text-[10px] text-up-accent uppercase font-bold mb-2">Komisi Reseller</p>
                            <div class="grid grid-cols-2 gap-2 text-center">
                                <div class="p-2.5 rounded-xl bg-white/[0.02] border border-white/5">
                                    <p class="text-sm font-bold text-up-amber tabular-nums">Rp {{ number_format($selectedCustomer['komisi']->get('pending', 0), 0, ',', '.') }}</p>
                                    <p class="text-[10px] text-ink-400 uppercase">Pending</p>
                                </div>
                                <div class="p-2.5 rounded-xl bg-white/[0.02] border border-white/5">
                                    <p class="text-sm font-bold text-up-mint tabular-nums">Rp {{ number_format($selectedCustomer['komisi']->get('disetujui', 0), 0, ',', '.') }}</p>
                                    <p class="text-[10px] text-ink-400 uppercase">Disetujui</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- [T-04] MODAL: TAMBAH PELANGGAN (CRM-06) -->
    @if($showPelangganBaruModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-sm glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Tambah Pelanggan Baru</h3>
                    <button wire:click="$set('showPelangganBaruModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama *</label>
                        <input type="text" wire:model="pelangganBaruForm.nama" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">No. HP *</label>
                        <input type="text" wire:model="pelangganBaruForm.telepon" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="08xx-xxxx-xxxx" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Email</label>
                        <input type="email" wire:model="pelangganBaruForm.email" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Alamat</label>
                        <textarea wire:model="pelangganBaruForm.alamat" rows="2" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Tanggal Lahir (opsional)</label>
                        <input type="date" wire:model="pelangganBaruForm.tanggal_lahir" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>
                    <label class="flex items-center gap-2 text-xs text-ink-300 cursor-pointer">
                        <input type="checkbox" wire:model="pelangganBaruForm.is_reseller" class="accent-up-accent w-4 h-4" />
                        Jadikan Reseller
                    </label>
                    <p class="text-[10px] text-ink-500">Satu data pelanggan — langsung bisa dipakai di POS & Servis.</p>
                </div>
                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showPelangganBaruModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="simpanPelangganBaruCrm" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer min-h-[44px]">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>