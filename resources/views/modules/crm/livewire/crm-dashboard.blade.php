<div class="space-y-6" x-data="{ activeTab: @entangle('activeTab').live }">
    <!-- ===== HEADER: SUBMENU & TABS NAVIGATION ===== -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-white/10 pb-4">
        <!-- Tab Switcher Utama CRM -->
        <div class="flex items-center gap-1.5 p-1 rounded-2xl bg-white/[0.04] border border-white/5 w-fit">
            <button
                type="button"
                @click="activeTab = 'pelanggan'"
                wire:click="switchTab('pelanggan')"
                class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition-all duration-150 cursor-pointer min-h-[40px]"
                :class="activeTab === 'pelanggan' ? 'bg-up-primary text-white shadow-lg shadow-up-primary/25' : 'text-ink-400 hover:text-white hover:bg-white/5'"
            >
                <span>👥</span>
                <span>Pelanggan & Member</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono"
                      :class="activeTab === 'pelanggan' ? 'bg-white/20 text-white' : 'bg-white/10 text-ink-300'">
                    {{ number_format($totalPelanggan ?? $customers->total()) }}
                </span>
            </button>

            <button
                type="button"
                @click="activeTab = 'tier'"
                wire:click="switchTab('tier')"
                class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition-all duration-150 cursor-pointer min-h-[40px]"
                :class="activeTab === 'tier' ? 'bg-up-primary text-white shadow-lg shadow-up-primary/25' : 'text-ink-400 hover:text-white hover:bg-white/5'"
            >
                <span>🏷️</span>
                <span>Tier Loyalty & Poin</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono"
                      :class="activeTab === 'tier' ? 'bg-white/20 text-white' : 'bg-white/10 text-ink-300'">
                    {{ $tiers->count() }}
                </span>
            </button>
        </div>

        <!-- Quick Submenu Links (Hubungan Modul Lain) -->
        <div class="flex flex-wrap sm:flex-nowrap items-center gap-2">
            <a href="/app/crm/leads" class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-white/[0.03] hover:bg-white/10 border border-white/10 text-ink-200 hover:text-white text-xs font-semibold transition active:scale-95 whitespace-nowrap min-h-[40px] cursor-pointer" title="Kelola prospek & alur negosiasi leads">
                <span>🎯</span>
                <span>Lead Pipeline</span>
                <span class="text-[10px] text-ink-500">&rarr;</span>
            </a>

            @can('reseller.view')
            <a href="/app/reseller" class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-up-accent/10 hover:bg-up-accent/20 border border-up-accent/25 text-up-accent hover:text-white text-xs font-semibold transition active:scale-95 whitespace-nowrap min-h-[40px] cursor-pointer" title="Portal mitra reseller, saldo komisi & penarikan">
                <span>🤝</span>
                <span>Mitra Reseller</span>
                <span class="text-[10px] text-up-accent/70">&rarr;</span>
            </a>
            @endcan
        </div>
    </div>

    <!-- ========================================== -->
    <!-- TAB 1: DATA PELANGGAN & MEMBERSHIP -->
    <!-- ========================================== -->
    <div x-show="activeTab === 'pelanggan'" class="space-y-4">
        <!-- Toolbar: Pencarian, Filter & Action Buttons -->
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
            <!-- Filter & Pencarian -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 flex-1 max-w-3xl">
                <div class="flex-1 min-w-[240px]">
                    <x-prism.barcode-scan-input placeholder="Cari nama, nomor HP, atau email pelanggan..." model="search" />
                </div>
                <div class="flex items-center gap-2">
                    <select wire:model.live="filterTierId" class="px-3 py-2.5 rounded-xl glass-input text-xs font-medium min-h-[44px]">
                        <option value="" class="bg-ink-900">Semua Tier</option>
                        @foreach($tiers as $t)
                            <option value="{{ $t->id }}" class="bg-ink-900">{{ $t->nama }}</option>
                        @endforeach
                    </select>

                    <label class="flex items-center gap-2 text-xs text-ink-300 font-medium cursor-pointer select-none px-3.5 py-2.5 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 transition min-h-[44px]" title="Tampilkan hanya pelanggan yang terdaftar sebagai mitra reseller">
                        <input type="checkbox" wire:model.live="filterReseller" class="accent-up-accent w-4 h-4 rounded cursor-pointer" />
                        <span class="whitespace-nowrap">Hanya Reseller</span>
                    </label>
                </div>
            </div>

            <!-- Action Buttons Pelanggan -->
            <div class="flex items-center gap-2 self-end lg:self-auto">
                @can('crm.create')
                <button
                    type="button"
                    wire:click="$set('showPelangganBaruModal', true)"
                    class="px-4 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs flex items-center gap-2 shadow-lg shadow-up-primary/20 transition active:scale-95 cursor-pointer min-h-[44px]"
                >
                    <span>+ Tambah Pelanggan</span>
                </button>
                @endcan

                @can('crm.broadcast')
                <button
                    type="button"
                    wire:click="$set('showBroadcastModal', true)"
                    class="px-3.5 py-2.5 rounded-xl bg-up-accent/15 hover:bg-up-accent/25 border border-up-accent/30 text-up-accent font-semibold text-xs flex items-center gap-1.5 transition active:scale-95 cursor-pointer min-h-[44px]"
                    title="Kirim pesan massal via WA, Email, atau In-App Notifikasi"
                >
                    <span>📢</span>
                    <span>Broadcast Promo</span>
                </button>
                @endcan
            </div>
        </div>

        <!-- Tabel Pelanggan (Dual-Mode Desktop & Mobile Cards) -->
        <x-prism.data-table :headers="['Pelanggan', 'Telepon / Kontak', 'Tier Membership', 'Total Belanja 12 Bln', 'Poin Loyalty', 'Tipe Konsumen', 'Aksi']">
            @forelse($customers as $c)
                <tr class="hover:bg-white/[0.02] transition-colors">
                    <td class="py-3.5 px-4 font-semibold text-white">
                        <div class="flex items-center gap-2">
                            <span>{{ $c->nama }}</span>
                            @if($c->is_reseller)
                                <span class="text-[9px] font-bold text-up-accent bg-up-accent/10 border border-up-accent/30 px-1.5 py-0.5 rounded-full">RESELLER</span>
                            @endif
                        </div>
                        @if($c->email)
                            <span class="block text-[11px] text-ink-400 font-normal mt-0.5">{{ $c->email }}</span>
                        @endif
                    </td>
                    <td class="py-3.5 px-4 text-xs text-ink-300 font-mono">{{ $c->telepon ?? '-' }}</td>
                    <td class="py-3.5 px-4">
                        <x-prism.tier-badge :tier="$c->tierMembership?->nama ?? ($c->is_reseller ? 'Reseller' : 'Retail')" />
                    </td>
                    <td class="py-3.5 px-4 tabular-nums font-semibold text-white text-sm">
                        Rp {{ number_format($c->total_belanja_12bulan, 0, ',', '.') }}
                    </td>
                    <td class="py-3.5 px-4 tabular-nums text-ink-200 text-sm">
                        <span class="font-bold text-up-primary">{{ number_format($c->poin_loyalty) }}</span> pts
                    </td>
                    <td class="py-3.5 px-4">
                        <span class="text-[11px] text-ink-300 capitalize">{{ $c->tipe_konsumen ?? ($c->is_reseller ? 'reseller' : 'retail') }}</span>
                    </td>
                    <td class="py-3.5 px-4">
                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                wire:click="$set('selectedCustomerId', {{ $c->id }})"
                                class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-up-primary/20 hover:text-white text-ink-300 font-semibold text-[11px] transition duration-140 cursor-pointer active:scale-95 whitespace-nowrap"
                            >
                                Detail 360°
                            </button>

                            @can('crm.edit')
                            <button
                                type="button"
                                wire:click="openEditPelangganModal({{ $c->id }})"
                                class="p-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-up-mint hover:text-white transition text-xs cursor-pointer active:scale-95"
                                title="Edit Data Pelanggan"
                            >
                                ✏️
                            </button>
                            @endcan

                            @if(isSuperAdminOrOwner() || auth()->user()?->can('crm.delete'))
                            <button
                                type="button"
                                wire:click="hapusPelanggan({{ $c->id }})"
                                wire:confirm="Yakin ingin menghapus pelanggan '{{ $c->nama }}'?"
                                class="p-1.5 rounded-lg bg-up-red/10 hover:bg-up-red/20 text-up-red border border-up-red/30 transition text-xs cursor-pointer active:scale-95"
                                title="Hapus Pelanggan"
                            >
                                🗑️
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="py-12 text-center text-ink-400">
                        <svg class="w-10 h-10 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857" />
                        </svg>
                        Belum ada data pelanggan yang cocok dengan pencarian / filter.
                    </td>
                </tr>
            @endforelse

            <x-slot:mobileCards>
                @foreach($customers as $c)
                    <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/5 space-y-3">
                        <div class="flex items-start justify-between">
                            <div>
                                <h4 class="font-bold text-white text-sm">{{ $c->nama }}</h4>
                                <span class="text-xs text-ink-400 font-mono">{{ $c->telepon ?? '-' }}</span>
                            </div>
                            <x-prism.tier-badge :tier="$c->tierMembership?->nama ?? ($c->is_reseller ? 'Reseller' : 'Retail')" />
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs pt-2 border-t border-white/5">
                            <div>
                                <span class="text-ink-400 text-[10px] uppercase block">Belanja 12 Bln</span>
                                <span class="font-bold text-white tabular-nums">Rp {{ number_format($c->total_belanja_12bulan, 0, ',', '.') }}</span>
                            </div>
                            <div>
                                <span class="text-ink-400 text-[10px] uppercase block">Poin Loyalty</span>
                                <span class="font-bold text-up-primary tabular-nums">{{ number_format($c->poin_loyalty) }} pts</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 pt-2">
                            <button
                                type="button"
                                wire:click="$set('selectedCustomerId', {{ $c->id }})"
                                class="flex-1 py-2 rounded-xl bg-up-primary/10 hover:bg-up-primary/20 text-up-primary font-bold text-xs transition duration-140 cursor-pointer text-center"
                            >
                                Lihat Detail 360°
                            </button>
                            @can('crm.edit')
                            <button
                                type="button"
                                wire:click="openEditPelangganModal({{ $c->id }})"
                                class="p-2 rounded-xl bg-white/5 text-up-mint hover:text-white text-xs"
                                title="Edit"
                            >
                                ✏️
                            </button>
                            @endcan
                        </div>
                    </div>
                @endforeach
            </x-slot:mobileCards>

            <x-slot:pagination>{{ $customers->links() }}</x-slot:pagination>
        </x-prism.data-table>
    </div>

    <!-- ========================================== -->
    <!-- TAB 2: KONFIGURASI TIER LOYALTY & BENEFIT -->
    <!-- ========================================== -->
    <div x-show="activeTab === 'tier'" x-cloak class="space-y-4">
        <!-- Header Info & Actions Tier -->
        <div class="p-5 rounded-2xl bg-white/[0.02] border border-white/10 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    <span>🏷️</span>
                    <span>Tingkatan Membership & Skema Loyalty Reward</span>
                </h3>
                <p class="text-xs text-ink-300 mt-1 max-w-2xl leading-relaxed">
                    Sistem secara otomatis mengevaluasi dan menaikkan tier membership pelanggan berdasarkan akumulasi total belanja 12 bulan terakhir. Setiap tier menentukan persentase potongan harga di kasir POS serta pengali perolehan poin loyalitas belanja.
                </p>
            </div>

            <div class="flex items-center gap-2 self-start md:self-auto flex-shrink-0">
                @can('tier.manage')
                <button
                    type="button"
                    wire:click="openTierModal()"
                    class="px-4 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs flex items-center gap-2 shadow-lg shadow-up-primary/20 transition active:scale-95 cursor-pointer min-h-[44px]"
                >
                    <span>+ Tambah Tier</span>
                </button>

                <button
                    type="button"
                    wire:click="recalcTier"
                    wire:confirm="Jalankan rekalkulasi tier untuk semua pelanggan berdasarkan total belanja 12 bulan terakhir?"
                    class="px-3.5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-200 hover:text-white font-semibold text-xs flex items-center gap-1.5 transition active:scale-95 cursor-pointer min-h-[44px]"
                    title="Hitung ulang status tier seluruh pelanggan sekarang"
                >
                    <span>⟳ Rekalkulasi Tier</span>
                </button>
                @endcan
            </div>
        </div>

        <!-- Grid Cards Tier Membership -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @forelse($tiers as $t)
                <div class="p-5 rounded-2xl bg-white/[0.03] border border-white/5 hover:border-white/15 transition flex flex-col justify-between space-y-4 relative overflow-hidden group">
                    <!-- Accent bar di atas kartu -->
                    <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-up-primary to-up-mint"></div>

                    <div>
                        <!-- Header Kartu -->
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span class="text-[10px] uppercase tracking-wider font-bold text-ink-400">Level {{ $t->urutan ?? 1 }}</span>
                                <h4 class="text-base font-bold text-white mt-0.5">{{ $t->nama }}</h4>
                                <span class="text-[11px] font-mono text-ink-400">Kode: {{ $t->kode }}</span>
                            </div>
                            <div>
                                @if($t->is_active)
                                    <span class="text-[10px] font-bold text-up-mint bg-up-mint/10 border border-up-mint/30 px-2 py-0.5 rounded-full">Aktif</span>
                                @else
                                    <span class="text-[10px] font-bold text-ink-400 bg-white/5 border border-white/10 px-2 py-0.5 rounded-full">Nonaktif</span>
                                @endif
                            </div>
                        </div>

                        <!-- Benefit & Syarat -->
                        <div class="mt-4 pt-4 border-t border-white/5 space-y-2.5 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-ink-400">Min. Belanja 12 Bln:</span>
                                <span class="font-bold text-white tabular-nums">Rp {{ number_format($t->min_belanja_12bulan, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-ink-400">Diskon POS Kasir:</span>
                                <span class="font-bold text-up-mint tabular-nums">{{ number_format($t->diskon_persen, 1) }}%</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-ink-400">Pengali Poin Loyalty:</span>
                                <span class="font-bold text-up-primary tabular-nums">{{ number_format($t->poin_multiplier, 1) }}x</span>
                            </div>
                            <div class="flex items-center justify-between pt-1">
                                <span class="text-ink-400">Member Terdaftar:</span>
                                <span class="font-bold text-white tabular-nums bg-white/5 px-2 py-0.5 rounded-lg border border-white/10">
                                    {{ number_format($t->pelanggan_count ?? 0) }} orang
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Tombol Aksi Tier -->
                    @can('tier.manage')
                    <div class="pt-3 border-t border-white/5 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            wire:click="openTierModal({{ $t->id }})"
                            class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-up-primary/20 hover:text-white text-ink-300 font-semibold text-xs transition duration-140 cursor-pointer active:scale-95"
                        >
                            ✏️ Edit Tier
                        </button>

                        @if(($t->pelanggan_count ?? 0) === 0 && (isSuperAdminOrOwner() || auth()->user()?->can('tier.manage')))
                        <button
                            type="button"
                            wire:click="hapusTier({{ $t->id }})"
                            wire:confirm="Yakin ingin menghapus tier '{{ $t->nama }}'?"
                            class="p-1.5 rounded-lg bg-up-red/10 hover:bg-up-red/20 text-up-red border border-up-red/30 transition text-xs cursor-pointer active:scale-95"
                            title="Hapus Tier"
                        >
                            🗑️
                        </button>
                        @endif
                    </div>
                    @endcan
                </div>
            @empty
                <div class="col-span-full py-12 text-center text-ink-400">
                    <p class="text-sm">Belum ada tier membership yang dikonfigurasi.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL: TIER (TAMBAH / EDIT) -->
    <!-- ========================================== -->
    @if($showTierModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">{{ $tierForm['id'] ? 'Edit Tier Membership' : 'Tambah Tier Baru' }}</h3>
                    <button wire:click="$set('showTierModal', false)" class="text-ink-400 hover:text-white cursor-pointer">✕</button>
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
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Diskon Kasir POS (%)</label>
                            <input type="number" wire:model="tierForm.diskon_persen" step="0.5" min="0" max="100" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-bold tabular-nums" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Pengali Poin Loyalty</label>
                            <input type="number" wire:model="tierForm.poin_multiplier" step="0.1" min="0" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-bold tabular-nums" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Urutan Level</label>
                            <input type="number" wire:model="tierForm.urutan" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                        </div>
                        <div class="flex items-end pb-2">
                            <label class="flex items-center gap-2 text-xs text-ink-300 cursor-pointer select-none">
                                <input type="checkbox" wire:model="tierForm.is_active" class="accent-up-mint w-4 h-4 rounded cursor-pointer" />
                                <span>Status Aktif</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 pt-4 border-t border-white/5 mt-5">
                    @if(!empty($tierForm['id']) && (isSuperAdminOrOwner() || auth()->user()?->can('tier.manage')))
                        <button type="button" wire:click="hapusTier({{ $tierForm['id'] }})" wire:confirm="Yakin ingin menghapus tier '{{ $tierForm['nama'] }}'?" class="px-4 py-3 rounded-xl bg-up-red/10 hover:bg-up-red/20 text-up-red border border-up-red/30 font-bold text-xs cursor-pointer min-h-[44px]">
                            🗑️ Hapus Tier
                        </button>
                    @endif
                    <div class="flex-1 flex gap-2 justify-end">
                        <button type="button" wire:click="$set('showTierModal', false)" class="px-4 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                        <button type="button" wire:click="simpanTier" class="px-5 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer min-h-[44px]">Simpan Tier</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL: BROADCAST PROMO MASSAL -->
    <!-- ========================================== -->
    @if($showBroadcastModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Broadcast Promo & Pengumuman</h3>
                    <button wire:click="$set('showBroadcastModal', false)" class="text-ink-400 hover:text-white cursor-pointer">✕</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Judul Pesan *</label>
                        <input type="text" wire:model="broadcastJudul" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="Promo Spesial Gajian Diskon Sparepart" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Isi Pesan *</label>
                        <textarea wire:model="broadcastPesan" rows="3" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="Dapatkan diskon sparepart 10% khusus member minggu ini..."></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Saluran / Channel *</label>
                            <select wire:model="broadcastChannel" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="inapp" class="bg-ink-900">In-App Notifikasi</option>
                                <option value="wa" class="bg-ink-900">WhatsApp Gateway</option>
                                <option value="email" class="bg-ink-900">Email Broadcast</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Target Tier</label>
                            <select wire:model="broadcastTierId" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="" class="bg-ink-900">Semua Tier Pelanggan</option>
                                @foreach($tiers as $t)
                                    <option value="{{ $t->id }}" class="bg-ink-900">{{ $t->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-xs text-ink-300 cursor-pointer select-none">
                            <input type="checkbox" wire:model="broadcastReseller" class="accent-up-accent w-4 h-4 rounded cursor-pointer" />
                            <span>Khusus Mitra Reseller Saja</span>
                        </label>
                    </div>
                    <div class="p-3 rounded-xl bg-up-primary/10 border border-up-primary/30 text-[11px] text-ink-200">
                        ⚡ Pesan otomatis didistribusikan secara asinkron <strong>melalui sistem antrian (database queue)</strong> agar performa server tetap cepat dan stabil.
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button type="button" wire:click="$set('showBroadcastModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button type="button" wire:click="kirimBroadcast" class="flex-1 py-3 rounded-xl bg-up-accent hover:opacity-90 text-white font-bold text-xs cursor-pointer min-h-[44px]">Kirim Broadcast</button>
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- DRAWER / MODAL: DETAIL PELANGGAN 360° -->
    <!-- ========================================== -->
    @if($selectedCustomer && $selectedCustomerId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-2xl glass-panel p-6 rounded-3xl relative max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">🔍</span>
                        <h3 class="text-lg font-bold text-white">Profil Pelanggan 360°</h3>
                    </div>
                    <button wire:click="$set('selectedCustomerId', null)" class="text-ink-400 hover:text-white cursor-pointer">✕</button>
                </div>

                <div class="overflow-y-auto pr-1 flex-1 space-y-4">
                    @php $p = $selectedCustomer['pelanggan']; @endphp

                    <!-- Identitas & Status Member -->
                    <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/5">
                        <div class="flex items-start justify-between">
                            <div>
                                <h4 class="font-bold text-white text-base">{{ $p->nama }}</h4>
                                <p class="text-xs text-ink-400 mt-0.5 font-mono">{{ $p->telepon ?? '-' }}</p>
                                @if($p->email)<p class="text-xs text-ink-400 font-mono">{{ $p->email }}</p>@endif
                                @if($p->alamat)<p class="text-xs text-ink-300 mt-1">{{ $p->alamat }}</p>@endif
                            </div>
                            <x-prism.tier-badge :tier="$p->tierMembership?->nama ?? ($p->is_reseller ? 'Reseller' : 'Retail')" />
                        </div>

                        <!-- Progress Bar Kenaikan Tier -->
                        @if($selectedCustomer['tier_berikutnya'])
                            <div class="mt-4 pt-4 border-t border-white/5">
                                <div class="flex justify-between text-[11px] text-ink-300 mb-1.5">
                                    <span>Progress ke Tier <strong class="text-up-primary">{{ $selectedCustomer['tier_berikutnya'] }}</strong></span>
                                    <span class="font-bold tabular-nums">{{ number_format($selectedCustomer['progress_tier'], 1) }}%</span>
                                </div>
                                <div class="h-2.5 rounded-full bg-white/5 overflow-hidden border border-white/10">
                                    <div class="h-full rounded-full bg-gradient-to-r from-up-primary via-indigo-500 to-up-accent transition-[width] duration-500"
                                         style="width: {{ $selectedCustomer['progress_tier'] }}%"></div>
                                </div>
                                <p class="text-[11px] text-up-amber mt-1.5">Sisa belanja menuju tier berikutnya: <strong class="tabular-nums">Rp {{ number_format($selectedCustomer['sisa_belanja'], 0, ',', '.') }}</strong></p>
                            </div>
                        @endif

                        <!-- Statistik Ringkas -->
                        <div class="grid grid-cols-3 gap-2 mt-4 text-center">
                            <div class="p-2.5 rounded-xl bg-white/[0.02] border border-white/5">
                                <p class="text-sm font-bold text-up-primary tabular-nums">{{ number_format($p->poin_loyalty) }}</p>
                                <p class="text-[10px] text-ink-400 uppercase">Poin Loyalty</p>
                            </div>
                            <div class="p-2.5 rounded-xl bg-white/[0.02] border border-white/5">
                                <p class="text-sm font-bold text-white tabular-nums">Rp {{ number_format($p->total_belanja_12bulan, 0, ',', '.') }}</p>
                                <p class="text-[10px] text-ink-400 uppercase">Belanja 12 Bln</p>
                            </div>
                            <div class="p-2.5 rounded-xl bg-white/[0.02] border border-white/5">
                                <p class="text-sm font-bold {{ $p->is_reseller ? 'text-up-accent' : 'text-ink-100' }}">{{ $p->is_reseller ? 'Reseller' : 'Retail' }}</p>
                                <p class="text-[10px] text-ink-400 uppercase">Tipe Member</p>
                            </div>
                        </div>
                    </div>

                    <!-- Riwayat Pembelian POS Terakhir -->
                    <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/5">
                        <p class="text-[10px] text-ink-400 uppercase font-bold mb-2">Riwayat Transaksi POS Terakhir</p>
                        @forelse($selectedCustomer['transaksi'] as $trx)
                            <div class="flex justify-between items-center text-xs py-2 border-b border-white/5 last:border-0">
                                <div>
                                    <span class="font-mono text-up-primary font-semibold">{{ $trx->no_transaksi }}</span>
                                    <span class="text-ink-400 ml-2 text-[11px]">{{ $trx->created_at->format('d/m/Y H:i') }}</span>
                                </div>
                                <span class="font-bold text-white tabular-nums">Rp {{ number_format($trx->total_akhir ?? $trx->total, 0, ',', '.') }}</span>
                            </div>
                        @empty
                            <p class="text-[11px] text-ink-500 py-2">Belum ada riwayat transaksi belanja.</p>
                        @endforelse
                    </div>

                    <!-- Riwayat Tiket Servis Terakhir -->
                    <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/5">
                        <p class="text-[10px] text-ink-400 uppercase font-bold mb-2">Riwayat Tiket Servis Perangkat</p>
                        @forelse($selectedCustomer['servis'] as $sv)
                            <div class="flex items-center justify-between text-xs py-2 border-b border-white/5 last:border-0 gap-2">
                                <div class="min-w-0">
                                    <span class="font-mono text-up-primary font-semibold">{{ $sv->no_tiket }}</span>
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
                            <p class="text-[11px] text-ink-500 py-2">Belum ada riwayat tiket servis.</p>
                        @endforelse
                    </div>

                    <!-- Ringkasan Komisi Jika Reseller -->
                    @if($p->is_reseller)
                        <div class="p-4 rounded-2xl bg-up-accent/5 border border-up-accent/20">
                            <p class="text-[10px] text-up-accent uppercase font-bold mb-2">Saldo Komisi Mitra Reseller</p>
                            <div class="grid grid-cols-2 gap-2 text-center">
                                <div class="p-2.5 rounded-xl bg-white/[0.02] border border-white/5">
                                    <p class="text-sm font-bold text-up-amber tabular-nums">Rp {{ number_format($selectedCustomer['komisi']->get('pending', 0), 0, ',', '.') }}</p>
                                    <p class="text-[10px] text-ink-400 uppercase">Menunggu Klaim</p>
                                </div>
                                <div class="p-2.5 rounded-xl bg-white/[0.02] border border-white/5">
                                    <p class="text-sm font-bold text-up-mint tabular-nums">Rp {{ number_format($selectedCustomer['komisi']->get('disetujui', 0), 0, ',', '.') }}</p>
                                    <p class="text-[10px] text-ink-400 uppercase">Siap Dicairkan</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL: TAMBAH PELANGGAN BARU -->
    <!-- ========================================== -->
    @if($showPelangganBaruModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Tambah Pelanggan Baru</h3>
                    <button wire:click="$set('showPelangganBaruModal', false)" class="text-ink-400 hover:text-white cursor-pointer">✕</button>
                </div>

                <div class="space-y-3.5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Lengkap *</label>
                            <input type="text" wire:model="pelangganBaruForm.nama" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="Nama Pelanggan / Toko Mitra" />
                            @error('pelangganBaruForm.nama') <span class="text-up-red text-[10px] mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nomor HP / WhatsApp *</label>
                            <input type="text" wire:model="pelangganBaruForm.telepon" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium font-mono" placeholder="08xxxxxxxxxx" />
                            @error('pelangganBaruForm.telepon') <span class="text-up-red text-[10px] mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Email (opsional)</label>
                            <input type="email" wire:model="pelangganBaruForm.email" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="pelanggan@email.com" />
                            @error('pelangganBaruForm.email') <span class="text-up-red text-[10px] mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Alamat Lengkap</label>
                        <textarea wire:model="pelangganBaruForm.alamat" rows="2" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs" placeholder="Alamat pengiriman / domisili..."></textarea>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Tanggal Lahir (opsional)</label>
                            <input type="date" wire:model="pelangganBaruForm.tanggal_lahir" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Tipe Konsumen</label>
                            <select wire:model="pelangganBaruForm.tipe_konsumen" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="retail" class="bg-ink-900">Retail (Umum)</option>
                                <option value="reseller" class="bg-ink-900">Reseller</option>
                                <option value="agen" class="bg-ink-900">Agen / Mitra B2B</option>
                            </select>
                        </div>
                    </div>

                    <div class="pt-2">
                        <label class="flex items-center gap-2 text-xs text-ink-300 cursor-pointer select-none">
                            <input type="checkbox" wire:model="pelangganBaruForm.is_reseller" class="accent-up-accent w-4 h-4 rounded cursor-pointer" />
                            <span>Daftarkan Sebagai Mitra Reseller (Akses Komisi & Harga Khusus)</span>
                        </label>
                    </div>

                    <p class="text-[11px] text-ink-500 pt-1">
                        💡 Data pelanggan otomatis terintegrasi langsung ke sistem Kasir POS, Tiket Servis, dan Marketing.
                    </p>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button type="button" wire:click="$set('showPelangganBaruModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button type="button" wire:click="simpanPelangganBaruCrm" class="flex-1 py-3 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs cursor-pointer min-h-[44px]">Simpan Pelanggan</button>
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL: EDIT DATA PELANGGAN -->
    <!-- ========================================== -->
    @if($showEditPelangganModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Edit Data Pelanggan</h3>
                    <button wire:click="$set('showEditPelangganModal', false)" class="text-ink-400 hover:text-white cursor-pointer">✕</button>
                </div>

                <div class="space-y-3.5">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Lengkap *</label>
                        <input type="text" wire:model="editPelangganForm.nama" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                        @error('editPelangganForm.nama') <span class="text-up-red text-[10px] mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nomor HP / WhatsApp *</label>
                            <input type="text" wire:model="editPelangganForm.telepon" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium font-mono" />
                            @error('editPelangganForm.telepon') <span class="text-up-red text-[10px] mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Email</label>
                            <input type="email" wire:model="editPelangganForm.email" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                            @error('editPelangganForm.email') <span class="text-up-red text-[10px] mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Tier Membership</label>
                            <select wire:model="editPelangganForm.tier_membership_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="" class="bg-ink-900">Tanpa Tier (Retail)</option>
                                @foreach($tiers as $t)
                                    <option value="{{ $t->id }}" class="bg-ink-900">{{ $t->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Tipe Konsumen</label>
                            <select wire:model="editPelangganForm.tipe_konsumen" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="retail" class="bg-ink-900">Retail (Umum)</option>
                                <option value="reseller" class="bg-ink-900">Reseller</option>
                                <option value="agen" class="bg-ink-900">Agen / Mitra B2B</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Alamat</label>
                        <textarea wire:model="editPelangganForm.alamat" rows="2" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Tanggal Lahir (opsional)</label>
                        <input type="date" wire:model="editPelangganForm.tanggal_lahir" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>

                    <div>
                        <label class="flex items-center gap-2 text-xs text-ink-300 cursor-pointer select-none">
                            <input type="checkbox" wire:model="editPelangganForm.is_reseller" class="accent-up-accent w-4 h-4 rounded cursor-pointer" />
                            <span>Status Mitra Reseller Aktif</span>
                        </label>
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button type="button" wire:click="$set('showEditPelangganModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button type="button" wire:click="simpanEditPelanggan" class="flex-1 py-3 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs cursor-pointer min-h-[44px]">Simpan Perubahan</button>
                </div>
            </div>
        </div>
    @endif
</div>
