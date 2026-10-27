<div class="space-y-6" x-data="{ activeTab: @entangle('activeTab').live }">
    <!-- ===== HEADER: SUBMENU & TABS NAVIGATION ===== -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-white/10 pb-4">
        <!-- Tab Switcher Utama CRM -->
        <div class="flex items-center gap-1.5 p-1 rounded-2xl bg-white/[0.04] border border-white/5 overflow-x-auto scrollbar-none max-w-full flex-nowrap py-1">
            <button
                type="button"
                @click="activeTab = 'pelanggan'"
                wire:click="switchTab('pelanggan')"
                class="flex items-center gap-2 px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-all duration-150 cursor-pointer min-h-[38px] whitespace-nowrap flex-shrink-0"
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
                class="flex items-center gap-2 px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-all duration-150 cursor-pointer min-h-[38px] whitespace-nowrap flex-shrink-0"
                :class="activeTab === 'tier' ? 'bg-up-primary text-white shadow-lg shadow-up-primary/25' : 'text-ink-400 hover:text-white hover:bg-white/5'"
            >
                <span>🏷️</span>
                <span>Tier Loyalty & Poin</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono"
                      :class="activeTab === 'tier' ? 'bg-white/20 text-white' : 'bg-white/10 text-ink-300'">
                    {{ $tiers->count() }}
                </span>
            </button>

            <button
                type="button"
                @click="activeTab = 'broadcast'"
                wire:click="switchTab('broadcast')"
                class="flex items-center gap-2 px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-all duration-150 cursor-pointer min-h-[38px] whitespace-nowrap flex-shrink-0"
                :class="activeTab === 'broadcast' ? 'bg-up-primary text-white shadow-lg shadow-up-primary/25' : 'text-ink-400 hover:text-white hover:bg-white/5'"
            >
                <span>📢</span>
                <span>Riwayat Broadcast</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono"
                      :class="activeTab === 'broadcast' ? 'bg-white/20 text-white' : 'bg-white/10 text-ink-300'">
                    {{ count($riwayatKampanye ?? []) }}
                </span>
            </button>

            <a
                href="/app/crm/leads"
                class="flex items-center gap-2 px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-all duration-150 cursor-pointer min-h-[38px] text-ink-400 hover:text-white hover:bg-white/5 whitespace-nowrap flex-shrink-0"
                title="Buka papan kanban prospek & negosiasi leads"
            >
                <span>🎯</span>
                <span>Pipeline Leads</span>
                <span class="text-[10px] text-ink-500">&rarr;</span>
            </a>
        </div>

        <!-- Quick Submenu Links (Hubungan Modul Lain) -->
        <div class="flex items-center gap-2 overflow-x-auto scrollbar-none flex-nowrap py-0.5 self-start md:self-auto">
            @can('reseller.view')
            <a href="/app/reseller" class="flex items-center gap-2 px-3.5 py-2 rounded-xl bg-up-accent/10 hover:bg-up-accent/20 border border-up-accent/25 text-up-accent hover:text-white text-xs font-semibold transition active:scale-95 whitespace-nowrap min-h-[38px] cursor-pointer" title="Portal mitra reseller, saldo komisi & penarikan">
                <span>🤝</span>
                <span>Mitra Reseller & Agen</span>
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
        <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-3 bg-white/[0.02] border border-white/5 p-3 sm:p-3.5 rounded-2xl">
            <!-- Filter & Pencarian -->
            <div class="flex flex-col md:flex-row items-stretch md:items-center gap-2.5 flex-1 min-w-0">
                <div class="flex-1 min-w-0 sm:min-w-[240px]">
                    <x-prism.barcode-scan-input placeholder="Cari nama, HP, email, kode agen..." model="search" />
                </div>
                <div class="grid grid-cols-2 sm:flex sm:items-center gap-2 w-full sm:w-auto">
                    <select wire:model.live="filterTierId" class="px-3 py-2.5 rounded-xl glass-input text-xs font-medium min-h-[44px] w-full sm:w-auto">
                        <option value="" class="bg-ink-900">Semua Tier</option>
                        @foreach($tiers as $t)
                            <option value="{{ $t->id }}" class="bg-ink-900">{{ $t->nama }}</option>
                        @endforeach
                    </select>

                    <select wire:model.live="filterTipeKonsumen" class="px-3 py-2.5 rounded-xl glass-input text-xs font-medium min-h-[44px] w-full sm:w-auto">
                        <option value="" class="bg-ink-900">Semua Tipe</option>
                        <option value="retail" class="bg-ink-900">Retail</option>
                        <option value="reseller" class="bg-ink-900">Reseller</option>
                        <option value="agen" class="bg-ink-900">Agen B2B</option>
                    </select>

                    <label class="col-span-2 sm:col-span-1 flex items-center justify-center sm:justify-start gap-2 text-xs text-ink-300 font-medium cursor-pointer select-none px-3.5 py-2.5 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 transition min-h-[44px] flex-shrink-0" title="Tampilkan hanya pelanggan yang terdaftar sebagai mitra reseller atau agen">
                        <input type="checkbox" wire:model.live="filterReseller" class="accent-up-accent w-4 h-4 rounded cursor-pointer" />
                        <span class="whitespace-nowrap">Mitra Aktif</span>
                    </label>

                    @if($search !== '' || $filterTierId !== null || $filterReseller || $filterTipeKonsumen !== '')
                    <button type="button" wire:click="resetFilter" class="px-3 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-300 hover:text-white text-xs font-semibold flex items-center justify-center gap-1.5 transition cursor-pointer min-h-[44px]" title="Reset semua filter pencarian">
                        <span>✕</span>
                        <span>Reset</span>
                    </button>
                    @endif
                </div>
            </div>

            <!-- Action Buttons Pelanggan -->
            <div class="flex items-center gap-2 pt-2 xl:pt-0 border-t xl:border-t-0 border-white/5 justify-stretch sm:justify-end flex-wrap sm:flex-nowrap">
                @can('crm.create')
                <button
                    type="button"
                    wire:click="$set('showPelangganBaruModal', true)"
                    class="flex-1 sm:flex-initial px-4 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs flex items-center justify-center gap-2 shadow-lg shadow-up-primary/20 transition active:scale-95 cursor-pointer min-h-[44px] whitespace-nowrap"
                >
                    <span>+ Tambah Pelanggan</span>
                </button>
                @endcan

                @can('crm.broadcast')
                <button
                    type="button"
                    wire:click="$set('showBroadcastModal', true)"
                    class="flex-1 sm:flex-initial px-3.5 py-2.5 rounded-xl bg-up-accent/15 hover:bg-up-accent/25 border border-up-accent/30 text-up-accent font-semibold text-xs flex items-center justify-center gap-1.5 transition active:scale-95 cursor-pointer min-h-[44px] whitespace-nowrap"
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
                        <div class="flex items-center gap-2 flex-wrap">
                            <span>{{ $c->nama }}</span>
                            @if($c->tipe_konsumen === 'agen' || $c->kode_agen)
                                <span class="text-[9px] font-bold text-amber-300 bg-amber-400/15 border border-amber-400/35 px-2 py-0.5 rounded-full tracking-wide">AGEN B2B</span>
                            @elseif($c->is_reseller || $c->tipe_konsumen === 'reseller')
                                <span class="text-[9px] font-bold text-up-accent bg-up-accent/10 border border-up-accent/30 px-1.5 py-0.5 rounded-full">RESELLER</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                            @if($c->email)
                                <span class="text-[11px] text-ink-400 font-normal">{{ $c->email }}</span>
                            @endif
                            @if($c->kode_agen)
                                <span class="text-[10px] font-mono font-semibold text-amber-400 bg-white/5 px-1.5 py-0.5 rounded border border-white/5">Kode: {{ $c->kode_agen }}</span>
                            @endif
                            @if($c->referral_kode)
                                <span class="text-[10px] font-mono text-ink-400">Ref: {{ $c->referral_kode }}</span>
                            @endif
                        </div>
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
                        @if($c->tipe_konsumen === 'agen' || $c->kode_agen)
                            <span class="text-[11px] font-bold text-amber-300">Agen / B2B</span>
                        @elseif($c->is_reseller || $c->tipe_konsumen === 'reseller')
                            <span class="text-[11px] font-semibold text-up-accent">Reseller</span>
                        @else
                            <span class="text-[11px] text-ink-300">Retail</span>
                        @endif
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
                                wire:click="openResetPasswordModal({{ $c->id }})"
                                class="p-1.5 rounded-lg bg-up-primary/10 hover:bg-up-primary/20 text-up-primary border border-up-primary/30 transition text-xs cursor-pointer active:scale-95"
                                title="Ajukan Reset Password Pelanggan"
                            >
                                🔑
                            </button>
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
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <h4 class="font-bold text-white text-sm truncate">{{ $c->nama }}</h4>
                                    @if($c->tipe_konsumen === 'agen' || $c->kode_agen)
                                        <span class="text-[9px] font-bold text-amber-300 bg-amber-400/15 border border-amber-400/35 px-1.5 py-0.5 rounded-full">AGEN B2B</span>
                                    @elseif($c->is_reseller || $c->tipe_konsumen === 'reseller')
                                        <span class="text-[9px] font-bold text-up-accent bg-up-accent/10 border border-up-accent/30 px-1.5 py-0.5 rounded-full">RESELLER</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2 mt-1 text-xs text-ink-400 font-mono flex-wrap">
                                    <span>{{ $c->telepon ?? '-' }}</span>
                                    @if($c->kode_agen)
                                        <span class="text-[10px] text-amber-400 bg-white/5 px-1.5 py-0.5 rounded border border-white/5">Kode: {{ $c->kode_agen }}</span>
                                    @endif
                                    @if($c->referral_kode)
                                        <span class="text-[10px] text-ink-400">Ref: {{ $c->referral_kode }}</span>
                                    @endif
                                </div>
                                @if($c->email)
                                    <span class="block text-[11px] text-ink-500 truncate mt-0.5">{{ $c->email }}</span>
                                @endif
                            </div>
                            <div class="flex-shrink-0">
                                <x-prism.tier-badge :tier="$c->tierMembership?->nama ?? ($c->is_reseller ? 'Reseller' : 'Retail')" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-xs pt-2.5 border-t border-white/5">
                            <div class="p-2 rounded-xl bg-white/[0.02] border border-white/5">
                                <span class="text-ink-400 text-[10px] uppercase font-semibold block">Belanja 12 Bln</span>
                                <span class="font-bold text-white tabular-nums text-xs">Rp {{ number_format($c->total_belanja_12bulan, 0, ',', '.') }}</span>
                            </div>
                            <div class="p-2 rounded-xl bg-white/[0.02] border border-white/5">
                                <span class="text-ink-400 text-[10px] uppercase font-semibold block">Poin Loyalty</span>
                                <span class="font-bold text-up-primary tabular-nums text-xs">{{ number_format($c->poin_loyalty) }} pts</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 pt-1">
                            <button
                                type="button"
                                wire:click="$set('selectedCustomerId', {{ $c->id }})"
                                class="flex-1 py-2.5 rounded-xl bg-up-primary/10 hover:bg-up-primary/20 text-up-primary font-bold text-xs transition duration-140 cursor-pointer text-center active:scale-95 min-h-[40px] flex items-center justify-center gap-1.5"
                            >
                                <span>🔍</span>
                                <span>Detail 360°</span>
                            </button>
                            @can('crm.edit')
                            <button
                                type="button"
                                wire:click="openResetPasswordModal({{ $c->id }})"
                                class="p-2.5 rounded-xl bg-up-primary/10 hover:bg-up-primary/20 text-up-primary border border-up-primary/30 transition text-xs cursor-pointer active:scale-95 min-h-[40px] min-w-[40px] flex items-center justify-center"
                                title="Ajukan Reset Password Pelanggan"
                            >
                                🔑
                            </button>
                            <button
                                type="button"
                                wire:click="openEditPelangganModal({{ $c->id }})"
                                class="p-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-up-mint hover:text-white transition text-xs cursor-pointer active:scale-95 min-h-[40px] min-w-[40px] flex items-center justify-center"
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
                                class="p-2.5 rounded-xl bg-up-red/10 hover:bg-up-red/20 text-up-red border border-up-red/30 transition text-xs cursor-pointer active:scale-95 min-h-[40px] min-w-[40px] flex items-center justify-center"
                                title="Hapus Pelanggan"
                            >
                                🗑️
                            </button>
                            @endif
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
        <div class="p-4 sm:p-5 rounded-2xl bg-white/[0.02] border border-white/10 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    <span>🏷️</span>
                    <span>Tingkatan Membership & Skema Loyalty Reward</span>
                </h3>
                <p class="text-xs text-ink-300 mt-1 max-w-2xl leading-relaxed">
                    Sistem secara otomatis mengevaluasi dan menaikkan tier membership pelanggan berdasarkan akumulasi total belanja 12 bulan terakhir. Setiap tier menentukan persentase potongan harga di kasir POS serta pengali perolehan poin loyalitas belanja.
                </p>
            </div>

            <div class="flex items-center gap-2 w-full md:w-auto flex-wrap sm:flex-nowrap justify-stretch md:justify-end flex-shrink-0 pt-2 md:pt-0 border-t md:border-t-0 border-white/5">
                @can('tier.manage')
                <button
                    type="button"
                    wire:click="openTierModal()"
                    class="flex-1 sm:flex-initial px-4 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs flex items-center justify-center gap-2 shadow-lg shadow-up-primary/20 transition active:scale-95 cursor-pointer min-h-[44px] whitespace-nowrap"
                >
                    <span>+ Tambah Tier</span>
                </button>

                <button
                    type="button"
                    wire:click="recalcTier"
                    wire:confirm="Jalankan rekalkulasi tier untuk semua pelanggan berdasarkan total belanja 12 bulan terakhir?"
                    class="flex-1 sm:flex-initial px-3.5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-200 hover:text-white font-semibold text-xs flex items-center justify-center gap-1.5 transition active:scale-95 cursor-pointer min-h-[44px] whitespace-nowrap"
                    title="Hitung ulang status tier seluruh pelanggan sekarang"
                >
                    <span>⟳ Rekalkulasi Tier</span>
                </button>

                <button
                    type="button"
                    wire:click="resetTierKeDefault"
                    wire:confirm="Kembalikan konfigurasi tier membership bawaan sistem (Silver, Gold, Platinum) ke standar default?"
                    class="flex-1 sm:flex-initial px-3.5 py-2.5 rounded-xl bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 text-amber-300 hover:text-white font-semibold text-xs flex items-center justify-center gap-1.5 transition active:scale-95 cursor-pointer min-h-[44px] whitespace-nowrap"
                    title="Pulihkan standar setting bawaan Silver, Gold, Platinum"
                >
                    <span>↺ Reset Tier Default</span>
                </button>
                @endcan
            </div>
        </div>

        <!-- Panel Konfigurasi Loyalty Global & Diskon Partai -->
        @can('tier.manage')
        <div class="p-4 sm:p-5 rounded-2xl bg-white/[0.02] border border-white/10 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-white/5">
                <div>
                    <h4 class="text-xs font-bold text-white flex items-center gap-2">
                        <span>⚙️</span>
                        <span>Konfigurasi Global Poin Loyalty & Harga Partai Member</span>
                    </h4>
                    <p class="text-[11px] text-ink-400 mt-0.5">
                        Berlaku sebagai nilai default bagi seluruh pelanggan retail/member yang tidak memiliki rasio kustom khusus di tier masing-masing.
                    </p>
                </div>
                <button
                    type="button"
                    wire:click="simpanPengaturanLoyaltyGlobal"
                    class="px-4 py-2 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs flex items-center justify-center gap-1.5 shadow-md shadow-up-primary/20 transition active:scale-95 cursor-pointer self-start sm:self-auto"
                >
                    <span>💾 Simpan Pengaturan Global</span>
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 text-xs">
                <!-- 1. Rasio Earn Poin Global -->
                <div class="p-3 rounded-xl bg-white/5 border border-white/5 space-y-1.5">
                    <label class="block font-semibold text-ink-300 text-[11px]">Rasio Earn Poin (%)</label>
                    <div class="flex items-center gap-1.5">
                        <input
                            type="number"
                            wire:model="poinEarnPersenGlobal"
                            min="0"
                            step="0.01"
                            class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs font-bold tabular-nums text-white"
                        />
                        <span class="text-ink-400 font-bold">%</span>
                    </div>
                    <span class="text-[10px] text-ink-400 block">% transaksi dikonversi jadi reward poin belanja</span>
                </div>

                <!-- 2. Nilai Tukar Poin Global -->
                <div class="p-3 rounded-xl bg-white/5 border border-white/5 space-y-1.5">
                    <label class="block font-semibold text-ink-300 text-[11px]">Nilai Tukar Poin Default (Rp)</label>
                    <div class="flex items-center gap-1.5">
                        <span class="text-ink-400 font-bold">1 pt = Rp</span>
                        <input
                            type="number"
                            wire:model="poinRedeemRupiahGlobal"
                            min="0"
                            step="10"
                            class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs font-bold tabular-nums text-white"
                        />
                    </div>
                    <div class="pt-1 border-t border-white/5">
                        <span class="text-[9px] text-ink-400 block">Maks Potongan Belanja:</span>
                        <div class="flex items-center gap-1">
                            <input
                                type="number"
                                wire:model="poinMaksPotonganPersen"
                                min="1"
                                max="100"
                                class="w-full px-1.5 py-0.5 rounded glass-input text-xs font-bold tabular-nums text-white"
                            />
                            <span class="text-ink-400 text-[10px] font-bold">%</span>
                        </div>
                    </div>
                </div>

                <!-- 2b. Skema Default Mitra & Proteksi Poin -->
                <div class="p-3 rounded-xl bg-up-primary/5 border border-up-primary/20 space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label class="block font-semibold text-up-accent text-[11px]">🏷️ Skema Default Mitra (Global)</label>
                    </div>
                    <div class="grid grid-cols-2 gap-1.5">
                        <div>
                            <span class="text-[9px] text-ink-400 block">Diskon Reseller</span>
                            <div class="flex items-center gap-1">
                                <input
                                    type="number"
                                    wire:model="resellerDefaultPersen"
                                    min="0"
                                    max="100"
                                    step="0.5"
                                    class="w-full px-1.5 py-1 rounded glass-input text-xs font-bold tabular-nums text-white"
                                />
                                <span class="text-ink-400 text-[10px] font-bold">%</span>
                            </div>
                        </div>
                        <div>
                            <span class="text-[9px] text-ink-400 block">Diskon Agen</span>
                            <div class="flex items-center gap-1">
                                <input
                                    type="number"
                                    wire:model="agenDefaultPersen"
                                    min="0"
                                    max="100"
                                    step="0.5"
                                    class="w-full px-1.5 py-1 rounded glass-input text-xs font-bold tabular-nums text-white"
                                />
                                <span class="text-ink-400 text-[10px] font-bold">%</span>
                            </div>
                        </div>
                    </div>
                    <label class="flex items-center gap-1.5 pt-1 border-t border-white/5 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            wire:model="mitraDapatPoin"
                            class="accent-up-primary w-3.5 h-3.5 rounded cursor-pointer"
                        />
                        <span class="text-[10px] {{ $mitraDapatPoin ? 'text-up-mint font-bold' : 'text-ink-400' }}">
                            Mitra Dapat Poin Loyalty
                        </span>
                    </label>
                    <span class="text-[9px] text-ink-500 block">Jika nonaktif, reseller/agen tidak diganjar poin (cegah dobel benefit).</span>
                </div>

                <!-- 3. Status Diskon Partai -->
                <div class="p-3 rounded-xl bg-white/5 border border-white/5 space-y-1.5">
                    <label class="block font-semibold text-ink-300 text-[11px]">Harga Pembelian Partai</label>
                    <label class="flex items-center gap-2 mt-1 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            wire:model.live="hargaPartaiAktif"
                            class="accent-up-mint w-4 h-4 rounded cursor-pointer"
                        />
                        <span class="font-bold {{ $hargaPartaiAktif ? 'text-up-mint' : 'text-ink-400' }}">
                            {{ $hargaPartaiAktif ? 'Aktif Otomatis' : 'Nonaktif' }}
                        </span>
                    </label>
                    @if($hargaPartaiAktif)
                        <div class="pt-1.5 border-t border-white/5 space-y-1">
                            <span class="text-[10px] text-ink-400 block font-medium">Sasaran Pembeli:</span>
                            <select
                                wire:model.live="hargaPartaiBerlakuUntuk"
                                class="w-full px-2 py-1 bg-ink-950/80 border border-white/10 rounded-lg text-xs text-ink-200 focus:outline-none focus:border-up-primary"
                            >
                                <option value="semua">Semua Pembeli (Umum & Member)</option>
                                <option value="member">Khusus Member / Mitra</option>
                            </select>
                        </div>
                    @endif
                    <span class="text-[10px] text-ink-400 block">Pilih metode Qty, Nominal, atau keduanya di samping</span>
                </div>

                <!-- 3. Aturan Partai Berbasis Qty (Opsional) -->
                <div class="p-3 rounded-xl bg-white/5 border border-white/5 space-y-1.5 transition {{ (!$hargaPartaiAktif || !$hargaPartaiOpsiQty) ? 'opacity-60' : '' }}">
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-1.5 cursor-pointer select-none font-semibold text-ink-200 text-[11px]">
                            <input
                                type="checkbox"
                                wire:model.live="hargaPartaiOpsiQty"
                                @disabled(!$hargaPartaiAktif)
                                class="accent-up-mint w-3.5 h-3.5 rounded cursor-pointer"
                            />
                            <span>Metode Qty</span>
                        </label>
                        <span class="text-[9px] px-1.5 py-0.5 rounded {{ $hargaPartaiOpsiQty && $hargaPartaiAktif ? 'bg-up-mint/10 text-up-mint border border-up-mint/20' : 'bg-white/5 text-ink-400 border border-white/10' }}">
                            {{ $hargaPartaiOpsiQty && $hargaPartaiAktif ? 'Aktif' : 'Off' }}
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 pt-1">
                        <div>
                            <span class="text-[10px] text-ink-400 block">Min Qty</span>
                            <input
                                type="number"
                                wire:model="hargaPartaiMinQty"
                                min="1"
                                @disabled(!$hargaPartaiAktif || !$hargaPartaiOpsiQty)
                                class="w-full px-2 py-1.5 rounded-lg glass-input text-xs font-bold tabular-nums text-white disabled:opacity-40 disabled:cursor-not-allowed"
                                placeholder="5"
                            />
                        </div>
                        <div>
                            <span class="text-[10px] text-ink-400 block">Diskon %</span>
                            <input
                                type="number"
                                wire:model="hargaPartaiQtyDiskon"
                                min="0"
                                max="100"
                                step="0.5"
                                @disabled(!$hargaPartaiAktif || !$hargaPartaiOpsiQty)
                                class="w-full px-2 py-1.5 rounded-lg glass-input text-xs font-bold tabular-nums text-up-mint disabled:opacity-40 disabled:cursor-not-allowed"
                                placeholder="3"
                            />
                        </div>
                    </div>
                </div>

                <!-- 4. Aturan Partai Berbasis Nominal (Opsional) -->
                <div class="p-3 rounded-xl bg-white/5 border border-white/5 space-y-1.5 transition {{ (!$hargaPartaiAktif || !$hargaPartaiOpsiNominal) ? 'opacity-60' : '' }}">
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-1.5 cursor-pointer select-none font-semibold text-ink-200 text-[11px]">
                            <input
                                type="checkbox"
                                wire:model.live="hargaPartaiOpsiNominal"
                                @disabled(!$hargaPartaiAktif)
                                class="accent-up-mint w-3.5 h-3.5 rounded cursor-pointer"
                            />
                            <span>Metode Nominal</span>
                        </label>
                        <span class="text-[9px] px-1.5 py-0.5 rounded {{ $hargaPartaiOpsiNominal && $hargaPartaiAktif ? 'bg-up-mint/10 text-up-mint border border-up-mint/20' : 'bg-white/5 text-ink-400 border border-white/10' }}">
                            {{ $hargaPartaiOpsiNominal && $hargaPartaiAktif ? 'Aktif' : 'Off' }}
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 pt-1">
                        <div>
                            <span class="text-[10px] text-ink-400 block">Min Belanja Rp</span>
                            <input
                                type="number"
                                wire:model="hargaPartaiMinNominal"
                                min="0"
                                step="50000"
                                @disabled(!$hargaPartaiAktif || !$hargaPartaiOpsiNominal)
                                class="w-full px-2 py-1.5 rounded-lg glass-input text-xs font-bold tabular-nums text-white disabled:opacity-40 disabled:cursor-not-allowed"
                                placeholder="1000000"
                            />
                        </div>
                        <div>
                            <span class="text-[10px] text-ink-400 block">Diskon %</span>
                            <input
                                type="number"
                                wire:model="hargaPartaiNominalDiskon"
                                min="0"
                                max="100"
                                step="0.5"
                                @disabled(!$hargaPartaiAktif || !$hargaPartaiOpsiNominal)
                                class="w-full px-2 py-1.5 rounded-lg glass-input text-xs font-bold tabular-nums text-up-mint disabled:opacity-40 disabled:cursor-not-allowed"
                                placeholder="2"
                            />
                        </div>
                    </div>
                </div>

                <!-- 5. Buffer Operasional & Packing (SSOT Margin Guard) -->
                <div class="p-3 rounded-xl bg-white/5 border border-white/5 space-y-1.5">
                    <label class="block font-semibold text-ink-300 text-[11px]">🛡️ Buffer Biaya &amp; Error (%)</label>
                    <div class="flex items-center gap-1.5">
                        <input
                            type="number"
                            wire:model="marginGuardBufferPersen"
                            min="0"
                            max="50"
                            step="0.5"
                            class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs font-bold tabular-nums text-up-amber"
                        />
                        <span class="text-ink-400 font-bold">%</span>
                    </div>
                    <span class="text-[10px] text-ink-400 block">Biaya packing, bubble wrap, pembulatan kasir, toleransi error</span>
                </div>

                <!-- 6. Payment Gateway Fee / MDR Toko -->
                <div class="p-3 rounded-xl bg-white/5 border border-white/5 space-y-1.5">
                    <label class="block font-semibold text-ink-300 text-[11px]">💳 MDR Payment Gateway (%)</label>
                    <div class="flex items-center gap-1.5">
                        <input
                            type="number"
                            wire:model="paymentGatewayFeePersen"
                            min="0"
                            max="20"
                            step="0.1"
                            class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs font-bold tabular-nums text-white"
                        />
                        <span class="text-ink-400 font-bold">%</span>
                    </div>
                    <span class="text-[10px] text-ink-400 block">Biaya MDR QRIS / VA jika toko menanggung biaya transaksi</span>
                </div>

                <!-- 7. Estimasi Komisi Penjualan Default -->
                <div class="p-3 rounded-xl bg-white/5 border border-white/5 space-y-1.5">
                    <label class="block font-semibold text-ink-300 text-[11px]">🤝 Komisi Penjualan Default (%)</label>
                    <div class="flex items-center gap-1.5">
                        <input
                            type="number"
                            wire:model="komisiAgenDefaultPersen"
                            min="0"
                            max="50"
                            step="0.5"
                            class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs font-bold tabular-nums text-white"
                        />
                        <span class="text-ink-400 font-bold">%</span>
                    </div>
                    <span class="text-[10px] text-ink-400 block">Komisi penjualan agen jika kategori belum punya skema khusus</span>
                </div>

                <!-- 8. Widget Ringkasan SSOT Proteksi Margin (Span 3 Kolom) -->
                <div class="p-3.5 rounded-xl bg-gradient-to-r from-up-primary/10 via-up-primary/5 to-transparent border border-up-primary/20 space-y-2 sm:col-span-2 lg:col-span-3">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-white text-[11px] flex items-center gap-1.5">
                            <span>🛡️</span>
                            <span>SSOT Beban Potongan &amp; Proteksi Margin Toko</span>
                        </span>
                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-up-primary/20 text-up-primary border border-up-primary/30 font-bold font-mono">
                            Sinkron Otomatis WMS &amp; POS
                        </span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[10px]">
                        <div class="p-2 rounded-lg bg-black/20">
                            <span class="text-ink-400 block">Diskon Member Max:</span>
                            <strong class="text-up-primary text-xs font-mono font-bold">{{ $ringkasanPotongan['max_mitra_diskon'] }}%</strong>
                        </div>
                        <div class="p-2 rounded-lg bg-black/20">
                            <span class="text-ink-400 block">Komisi Penjualan:</span>
                            <strong class="text-up-amber text-xs font-mono font-bold">{{ $ringkasanPotongan['komisi_persen'] }}%</strong>
                        </div>
                        <div class="p-2 rounded-lg bg-black/20">
                            <span class="text-ink-400 block">Buffer &amp; Fee Gateway:</span>
                            <strong class="text-up-mint text-xs font-mono font-bold">{{ $ringkasanPotongan['buffer_biaya_persen'] + $ringkasanPotongan['gateway_fee_persen'] }}%</strong>
                        </div>
                        <div class="p-2 rounded-lg bg-black/20">
                            <span class="text-ink-400 block">Total Potongan Wajib:</span>
                            <strong class="text-up-red text-xs font-mono font-bold">{{ $ringkasanPotongan['total_potongan_persen'] }}%</strong>
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-[10px] pt-1 border-t border-white/5 text-ink-300">
                        <span>Rekomendasi Markup Minimal Retail: <strong class="text-up-mint">+{{ $ringkasanPotongan['min_markup_persen'] }}% dari HPP</strong></span>
                        <a href="/app/wms?tab=monitoring_harga" class="text-up-primary hover:underline font-bold flex items-center gap-1">
                            <span>Buka Audit WMS &rarr;</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endcan

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
                            <div class="flex items-center justify-between">
                                <span class="text-ink-400">Nilai Tukar Poin:</span>
                                @if((float) ($t->poin_redeem_rupiah ?? 0) > 0)
                                    <span class="font-bold text-amber-400 tabular-nums">
                                        Rp {{ number_format($t->poin_redeem_rupiah, 0, ',', '.') }}/pt
                                        <span class="text-[9px] px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-300 border border-amber-500/20 font-normal">Kustom</span>
                                    </span>
                                @else
                                    <span class="font-bold text-ink-300 tabular-nums">
                                        Rp {{ number_format($poinRedeemRupiahGlobal, 0, ',', '.') }}/pt
                                        <span class="text-[9px] px-1.5 py-0.5 rounded bg-white/5 text-ink-400 border border-white/10 font-normal">Default</span>
                                    </span>
                                @endif
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
    <!-- TAB 3: RIWAYAT BROADCAST KAMPANYE -->
    <!-- ========================================== -->
    <div x-show="activeTab === 'broadcast'" x-cloak class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white/[0.02] border border-white/5 p-3.5 rounded-2xl">
            <div>
                <h3 class="text-sm font-bold text-white">Riwayat Kampanye Broadcast</h3>
                <p class="text-xs text-ink-400">Daftar siaran promo dan pengumuman massal yang dikirimkan via antrian sistem.</p>
            </div>
            @can('crm.broadcast')
            <button
                type="button"
                wire:click="$set('showBroadcastModal', true)"
                class="px-4 py-2.5 rounded-xl bg-up-accent hover:bg-up-accent/90 text-white font-bold text-xs flex items-center justify-center gap-1.5 shadow-lg shadow-up-accent/20 transition active:scale-95 cursor-pointer whitespace-nowrap"
            >
                <span>📢</span>
                <span>Buat Broadcast Baru</span>
            </button>
            @endcan
        </div>

        <x-prism.data-table :headers="['Waktu', 'Judul & Pesan', 'Saluran', 'Target Segmen', 'Terkirim / Target', 'Status']">
            @forelse($riwayatKampanye ?? [] as $k)
                <tr class="hover:bg-white/[0.02] transition-colors">
                    <td class="py-3.5 px-4 text-xs font-mono text-ink-300 whitespace-nowrap">
                        {{ $k->created_at->format('d/m/Y H:i') }}
                    </td>
                    <td class="py-3.5 px-4 max-w-xs sm:max-w-md">
                        <p class="font-semibold text-white text-xs">{{ $k->judul }}</p>
                        <p class="text-[11px] text-ink-400 line-clamp-1 mt-0.5">{{ \Illuminate\Support\Str::limit($k->pesan, 80) }}</p>
                    </td>
                    <td class="py-3.5 px-4 whitespace-nowrap">
                        @if($k->channel === 'wa')
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">WhatsApp</span>
                        @elseif($k->channel === 'email')
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/15 text-blue-400 border border-blue-500/30">Email</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-500/15 text-purple-400 border border-purple-500/30">In-App</span>
                        @endif
                    </td>
                    <td class="py-3.5 px-4 text-xs text-ink-300">
                        @if(!empty($k->segment))
                            <span class="text-[11px] text-ink-200">
                                {{ count($k->segment) }} Filter
                            </span>
                        @else
                            <span class="text-[11px] text-ink-400">Semua Pelanggan</span>
                        @endif
                    </td>
                    <td class="py-3.5 px-4 text-xs font-mono tabular-nums whitespace-nowrap">
                        <span class="text-up-mint font-bold">{{ number_format($k->total_terkirim ?? 0) }}</span>
                        <span class="text-ink-500">/</span>
                        <span class="text-white">{{ number_format($k->total_target ?? 0) }}</span>
                        @if($k->total_gagal > 0)
                            <span class="text-up-red text-[10px] ml-1">({{ $k->total_gagal }} gagal)</span>
                        @endif
                    </td>
                    <td class="py-3.5 px-4 whitespace-nowrap">
                        <x-prism.status-pill :status="$k->status" />
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="py-12 text-center text-ink-400 text-xs">
                        Belum ada riwayat kampanye broadcast yang dikirim.
                    </td>
                </tr>
            @endforelse
        </x-prism.data-table>
    </div>

    <!-- ========================================== -->
    <!-- MODAL: TIER (TAMBAH / EDIT) -->
    <!-- ========================================== -->
    @if($showTierModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-3 sm:p-4">
            <div class="w-full max-w-md glass-panel p-4 sm:p-6 rounded-2xl sm:rounded-3xl relative max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 sm:pb-4 mb-3 sm:mb-4 border-b border-white/10">
                    <h3 class="text-base sm:text-lg font-bold text-white">{{ $tierForm['id'] ? 'Edit Tier Membership' : 'Tambah Tier Baru' }}</h3>
                    <button wire:click="$set('showTierModal', false)" class="w-8 h-8 rounded-full bg-white/5 hover:bg-white/10 text-ink-400 hover:text-white flex items-center justify-center transition cursor-pointer text-sm">✕</button>
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
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nilai Tukar 1 Poin (Rp) - Kustom Tier</label>
                        <input type="text" inputmode="numeric" x-format-number wire:model="tierForm.poin_redeem_rupiah" min="0" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-bold tabular-nums" placeholder="Kosongkan untuk pakai default sistem (Rp {{ number_format($poinRedeemRupiahGlobal, 0, ',', '.') }})" />
                        <span class="text-[10px] text-ink-400 mt-1 block">Bila kosong/0, kasir POS otomatis memakai nilai default global (saat ini Rp {{ number_format($poinRedeemRupiahGlobal, 0, ',', '.') }}/pt).</span>
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
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-3 sm:p-4">
            <div class="w-full max-w-md glass-panel p-4 sm:p-6 rounded-2xl sm:rounded-3xl relative">
                <div class="flex items-center justify-between pb-3 sm:pb-4 mb-3 sm:mb-4 border-b border-white/10">
                    <h3 class="text-base sm:text-lg font-bold text-white">Broadcast Promo & Pengumuman</h3>
                    <button wire:click="$set('showBroadcastModal', false)" class="w-8 h-8 rounded-full bg-white/5 hover:bg-white/10 text-ink-400 hover:text-white flex items-center justify-center transition cursor-pointer text-sm">✕</button>
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
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Saluran / Channel *</label>
                        <select wire:model="broadcastChannel" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            <option value="inapp" class="bg-ink-900">In-App Notifikasi</option>
                            <option value="wa" class="bg-ink-900">WhatsApp Gateway</option>
                            <option value="email" class="bg-ink-900">Email Broadcast</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Segmentasi Target Pelanggan</label>
                        <select wire:model.live="broadcastSegmentType" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            <option value="semua" class="bg-ink-900">Semua Pelanggan</option>
                            <option value="tier" class="bg-ink-900">Berdasarkan Tier Membership</option>
                            <option value="reseller" class="bg-ink-900">Khusus Mitra Reseller / Agen</option>
                            <option value="belum_belanja" class="bg-ink-900">Pelanggan Tidak Belanja > X Hari</option>
                            <option value="birthday" class="bg-ink-900">Pelanggan Ulang Tahun Bulan Ini</option>
                        </select>
                    </div>

                    @if($broadcastSegmentType === 'tier')
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Pilih Tier Pelanggan</label>
                        <select wire:model="broadcastTierId" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            <option value="" class="bg-ink-900">Pilih Tier</option>
                            @foreach($tiers as $t)
                                <option value="{{ $t->id }}" class="bg-ink-900">{{ $t->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    @if($broadcastSegmentType === 'belum_belanja')
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Berapa Hari Belum Belanja?</label>
                        <input type="number" wire:model="broadcastHariBelanja" min="1" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="30" />
                    </div>
                    @endif

                    @if($broadcastSegmentType === 'birthday')
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Bulan Ulang Tahun</label>
                        <select wire:model="broadcastBulanLahir" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            @for($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" class="bg-ink-900">{{ \Carbon\Carbon::create(2026, $m, 1)->translatedFormat('F') }}</option>
                            @endfor
                        </select>
                    </div>
                    @endif

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
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-3 sm:p-4">
            <div class="w-full max-w-2xl glass-panel p-4 sm:p-6 rounded-2xl sm:rounded-3xl relative max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between pb-3 sm:pb-4 mb-3 sm:mb-4 border-b border-white/10">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">🔍</span>
                        <h3 class="text-base sm:text-lg font-bold text-white">Profil Pelanggan 360°</h3>
                    </div>
                    <div class="flex items-center gap-2">
                        @if(!empty($selectedCustomer['pelanggan']))
                            <button wire:click="openResetPasswordModal({{ $selectedCustomer['pelanggan']->id }})" 
                                    type="button"
                                    class="px-2.5 py-1.5 rounded-lg bg-up-primary/20 hover:bg-up-primary/30 text-up-primary border border-up-primary/30 text-xs font-semibold flex items-center gap-1.5 cursor-pointer"
                                    title="Ajukan reset kata sandi pelanggan">
                                <span>🔑</span>
                                <span>Reset Password</span>
                            </button>
                            <button wire:click="openEditPelangganModal({{ $selectedCustomer['pelanggan']->id }})" 
                                    type="button"
                                    class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-up-mint border border-up-mint/30 text-xs font-semibold flex items-center gap-1.5 cursor-pointer">
                                <span>✏️</span>
                                <span>Ubah Kategori / Status</span>
                            </button>
                        @endif
                        <button wire:click="$set('selectedCustomerId', null)" class="w-8 h-8 rounded-full bg-white/5 hover:bg-white/10 text-ink-400 hover:text-white flex items-center justify-center transition cursor-pointer text-sm">✕</button>
                    </div>
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
                                <p class="text-sm font-bold {{ $p->tipe_konsumen === 'agen' || $p->kode_agen ? 'text-amber-300' : ($p->is_reseller ? 'text-up-accent' : 'text-ink-100') }}">
                                    {{ $p->tipe_konsumen === 'agen' || $p->kode_agen ? 'Agen B2B' : ($p->is_reseller ? 'Reseller' : 'Retail') }}
                                </p>
                                <p class="text-[10px] text-ink-400 uppercase">Tipe Member</p>
                            </div>
                        </div>

                        @if(!empty($selectedCustomer['upline_agen']))
                            <div class="mt-3 p-2.5 rounded-xl bg-white/[0.02] border border-white/5 flex items-center justify-between text-xs">
                                <span class="text-ink-400">Terdaftar via Agen Referral:</span>
                                <span class="text-amber-400 font-semibold">{{ $selectedCustomer['upline_agen']->nama }} ({{ $p->referral_kode }})</span>
                            </div>
                        @endif
                    </div>

                    <!-- Khusus Agen: Jaringan Downline & Kode Agen -->
                    @if($p->kode_agen || $p->tipe_konsumen === 'agen')
                        <div class="p-4 rounded-2xl bg-gradient-to-br from-amber-500/10 via-amber-500/5 to-transparent border border-amber-500/25">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-base">🏢</span>
                                    <p class="text-xs text-amber-400 uppercase font-bold tracking-wider">Jaringan Downline & Komisi Agen</p>
                                </div>
                                <span class="text-xs font-mono font-bold text-amber-300 bg-amber-400/20 px-2.5 py-1 rounded-lg border border-amber-400/30 w-fit">
                                    Kode Agen: {{ $p->kode_agen ?? '-' }}
                                </span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-center">
                                <div class="p-2.5 rounded-xl bg-white/[0.02] border border-white/5">
                                    <p class="text-base font-bold text-white tabular-nums">{{ number_format($selectedCustomer['downline_count'] ?? 0) }}</p>
                                    <p class="text-[10px] text-ink-400 uppercase">Downline Binaan</p>
                                </div>
                                <div class="p-2.5 rounded-xl bg-white/[0.02] border border-white/5">
                                    <p class="text-base font-bold text-up-mint tabular-nums">Rp {{ number_format($selectedCustomer['downline_omzet'] ?? 0, 0, ',', '.') }}</p>
                                    <p class="text-[10px] text-ink-400 uppercase">Omzet Belanja Downline</p>
                                </div>
                            </div>
                            @if(!empty($selectedCustomer['downlines']) && count($selectedCustomer['downlines']) > 0)
                                <div class="mt-3 pt-3 border-t border-white/5 space-y-1.5">
                                    <p class="text-[10px] text-ink-400 uppercase font-semibold">Daftar Mitra / Pelanggan Downline:</p>
                                    @foreach($selectedCustomer['downlines'] as $dl)
                                        <div class="flex items-center justify-between text-xs py-1.5 px-2.5 rounded-lg bg-white/[0.02] border border-white/5 gap-2">
                                            <span class="text-white font-medium truncate">{{ $dl->nama }} <span class="text-[10px] text-ink-400 font-mono">({{ $dl->telepon ?? '-' }})</span></span>
                                            <span class="text-up-mint font-semibold tabular-nums flex-shrink-0">Rp {{ number_format($dl->total_belanja_12bulan, 0, ',', '.') }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

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

                    <!-- Mutasi Poin Loyalty Terakhir -->
                    <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/5">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-[10px] text-ink-400 uppercase font-bold">Mutasi Poin Loyalty Terakhir</p>
                            <span class="text-[10px] font-mono text-up-mint font-bold tabular-nums">Total: {{ number_format($p->poin_loyalty) }} Poin</span>
                        </div>
                        @forelse($selectedCustomer['poin_logs'] ?? [] as $plog)
                            <div class="flex items-center justify-between text-xs py-2 border-b border-white/5 last:border-0 gap-2">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $plog->poin > 0 ? 'bg-up-mint/15 text-up-mint' : 'bg-up-red/15 text-up-red' }}">
                                            {{ $plog->poin > 0 ? '+'.$plog->poin : $plog->poin }}
                                        </span>
                                        <span class="text-ink-200 text-xs truncate">{{ $plog->keterangan ?: ucfirst($plog->tipe) }}</span>
                                    </div>
                                    <span class="text-ink-500 text-[10px]">{{ $plog->created_at->format('d/m/Y H:i') }}</span>
                                </div>
                                <span class="font-mono text-ink-400 text-[11px] tabular-nums">Sisa: {{ number_format($plog->saldo_akhir) }}</span>
                            </div>
                        @empty
                            <p class="text-[11px] text-ink-500 py-2">Belum ada riwayat mutasi poin loyalty.</p>
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
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-3 sm:p-4">
            <div class="w-full max-w-lg glass-panel p-4 sm:p-6 rounded-2xl sm:rounded-3xl relative max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 sm:pb-4 mb-3 sm:mb-4 border-b border-white/10">
                    <h3 class="text-base sm:text-lg font-bold text-white">Tambah Pelanggan Baru</h3>
                    <button wire:click="$set('showPelangganBaruModal', false)" class="w-8 h-8 rounded-full bg-white/5 hover:bg-white/10 text-ink-400 hover:text-white flex items-center justify-center transition cursor-pointer text-sm">✕</button>
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
                            <select wire:model.live="pelangganBaruForm.tipe_konsumen" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="retail" class="bg-ink-900">Retail (Umum)</option>
                                <option value="reseller" class="bg-ink-900">Reseller</option>
                                <option value="agen" class="bg-ink-900">Agen / Mitra B2B</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @if(($pelangganBaruForm['tipe_konsumen'] ?? '') === 'agen')
                        <div>
                            <label class="block text-xs font-semibold text-amber-300 mb-1.5">Kode Unik Agen</label>
                            <input type="text" wire:model="pelangganBaruForm.kode_agen" placeholder="Otomatis jika kosong" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-mono uppercase" />
                            @error('pelangganBaruForm.kode_agen') <span class="text-up-red text-[10px] mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        @endif
                        <div class="{{ ($pelangganBaruForm['tipe_konsumen'] ?? '') === 'agen' ? '' : 'sm:col-span-2' }}">
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kode Referral Upline (Opsional)</label>
                            <input type="text" wire:model="pelangganBaruForm.referral_kode" placeholder="Masukkan Kode Agen Upline jika ada (misal: AGN-XXXXXX)" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-mono uppercase" />
                            @error('pelangganBaruForm.referral_kode') <span class="text-up-red text-[10px] mt-1 block">{{ $message }}</span> @enderror
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
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-3 sm:p-4">
            <div class="w-full max-w-lg glass-panel p-4 sm:p-6 rounded-2xl sm:rounded-3xl relative max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 sm:pb-4 mb-3 sm:mb-4 border-b border-white/10">
                    <h3 class="text-base sm:text-lg font-bold text-white">Edit Data Pelanggan</h3>
                    <button wire:click="$set('showEditPelangganModal', false)" class="w-8 h-8 rounded-full bg-white/5 hover:bg-white/10 text-ink-400 hover:text-white flex items-center justify-center transition cursor-pointer text-sm">✕</button>
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
                            <select wire:model.live="editPelangganForm.tipe_konsumen" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="retail" class="bg-ink-900">Retail (Umum)</option>
                                <option value="reseller" class="bg-ink-900">Reseller</option>
                                <option value="agen" class="bg-ink-900">Agen / Mitra B2B</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @if(($editPelangganForm['tipe_konsumen'] ?? '') === 'agen')
                        <div>
                            <label class="block text-xs font-semibold text-amber-300 mb-1.5">Kode Unik Agen</label>
                            <input type="text" wire:model="editPelangganForm.kode_agen" placeholder="Otomatis jika kosong" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-mono uppercase" />
                            @error('editPelangganForm.kode_agen') <span class="text-up-red text-[10px] mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        @endif
                        <div class="{{ ($editPelangganForm['tipe_konsumen'] ?? '') === 'agen' ? '' : 'sm:col-span-2' }}">
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kode Referral Upline (Opsional)</label>
                            <input type="text" wire:model="editPelangganForm.referral_kode" placeholder="Kode Agen Upline (misal: AGN-XXXXXX)" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-mono uppercase" />
                            @error('editPelangganForm.referral_kode') <span class="text-up-red text-[10px] mt-1 block">{{ $message }}</span> @enderror
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

                    <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5 text-xs">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-ink-400 font-semibold text-[11px] uppercase">Benefit & Status:</span>
                            @if(($editPelangganForm['tipe_konsumen'] ?? '') === 'agen')
                                <span class="text-[10px] font-bold text-amber-300 bg-amber-400/15 border border-amber-400/35 px-2 py-0.5 rounded-full">AGEN B2B</span>
                            @elseif(($editPelangganForm['tipe_konsumen'] ?? '') === 'reseller')
                                <span class="text-[10px] font-bold text-up-accent bg-up-accent/10 border border-up-accent/30 px-2 py-0.5 rounded-full">MITRA RESELLER</span>
                            @else
                                <span class="text-[10px] font-bold text-up-primary bg-up-primary/10 border border-up-primary/30 px-2 py-0.5 rounded-full">MEMBER BIASA (RETAIL)</span>
                            @endif
                        </div>
                        <p class="text-[11px] text-ink-300 leading-relaxed">
                            @if(($editPelangganForm['tipe_konsumen'] ?? '') === 'agen')
                                Berhak mendapatkan tingkat harga Agen B2B, kepemilikan kode agen unik, serta potensi komisi referral downline.
                            @elseif(($editPelangganForm['tipe_konsumen'] ?? '') === 'reseller')
                                Berhak mendapatkan tingkat harga Reseller pada kasir POS, pesanan online, dan suku cadang servis.
                            @else
                                Konsumen umum. Mengumpulkan poin loyalitas dan berhak atas diskon kasir sesuai Tier Membership yang aktif.
                            @endif
                        </p>
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button type="button" wire:click="$set('showEditPelangganModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button type="button" wire:click="simpanEditPelanggan" class="flex-1 py-3 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs cursor-pointer min-h-[44px]">Simpan Perubahan</button>
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL: AJUKAN RESET PASSWORD PELANGGAN     -->
    <!-- ========================================== -->
    @if($showResetPasswordModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-3 sm:p-4">
            <div class="w-full max-w-lg glass-panel p-5 sm:p-6 rounded-2xl sm:rounded-3xl relative max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-white/10 flex-shrink-0">
                    <div>
                        <h3 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
                            <span>🔑</span>
                            <span>Ajukan Reset Kata Sandi Pelanggan</span>
                        </h3>
                        <p class="text-xs text-ink-400 mt-0.5">Alur lupa password CS WhatsApp &amp; Kasir Toko</p>
                    </div>
                    <button wire:click="closeResetPasswordModal" class="w-8 h-8 rounded-full bg-white/5 hover:bg-white/10 text-ink-400 hover:text-white flex items-center justify-center transition cursor-pointer text-sm">✕</button>
                </div>

                <div class="overflow-y-auto pr-1 flex-1 space-y-4">
                    <!-- Ringkasan Pelanggan -->
                    <div class="p-3.5 rounded-xl bg-white/[0.02] border border-white/5 space-y-2">
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <span class="text-ink-400 block text-[10px] uppercase font-semibold">Nama Konsumen</span>
                                <span class="font-bold text-white text-sm">{{ $resetPasswordForm['nama'] }}</span>
                            </div>
                            <div>
                                <span class="text-ink-400 block text-[10px] uppercase font-semibold">No. HP / WhatsApp</span>
                                <span class="font-bold font-mono text-white text-sm">{{ $resetPasswordForm['telepon'] }}</span>
                            </div>
                        </div>
                        @if(!empty($resetPasswordForm['username']))
                            <div class="pt-2 border-t border-white/5 text-xs">
                                <span class="text-ink-400 text-[10px] uppercase font-semibold">Username:</span>
                                <span class="font-mono font-semibold text-up-primary">&#64;{{ $resetPasswordForm['username'] }}</span>
                            </div>
                        @endif
                    </div>

                    <!-- Input Kata Sandi Baru -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-semibold text-ink-300">
                                Kata Sandi Baru yang Diajukan *
                            </label>
                            <button type="button" wire:click="$set('resetPasswordForm.new_password', '{{ Str::random(8) }}')"
                                    class="text-[11px] text-up-primary hover:underline font-mono">
                                Acak Password Baru
                            </button>
                        </div>
                        <input type="text"
                               wire:model="resetPasswordForm.new_password"
                               placeholder="Minimal 6 karakter"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-black/30 border border-white/10 text-white font-mono text-sm focus:outline-none focus:border-up-primary" />
                        <p class="text-[11px] text-ink-400 mt-1">
                            Setelah di-approve Owner/Admin, konsumen langsung dapat login dengan password ini.
                        </p>
                        @error('resetPasswordForm.new_password') <span class="text-red-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <!-- Alasan / Catatan Pengajuan -->
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">
                            Alasan Permohonan / Catatan CS *
                        </label>
                        <textarea wire:model="resetPasswordForm.alasan"
                                  rows="2"
                                  placeholder="Contoh: Konsumen chat WA lupa sandi, no HP sudah dicocokkan."
                                  class="w-full px-3.5 py-2 rounded-xl bg-black/30 border border-white/10 text-white text-xs focus:outline-none focus:border-up-primary"></textarea>
                        @error('resetPasswordForm.alasan') <span class="text-red-400 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="p-3 rounded-xl bg-up-primary/10 border border-up-primary/20 text-xs text-ink-300 space-y-1">
                        <p class="font-bold text-white flex items-center gap-1.5">
                            <span>🛡️</span>
                            <span>Kontrol Keamanan Approval</span>
                        </p>
                        <p class="text-[11px] leading-relaxed text-ink-300">
                            Pengajuan akan masuk ke antrean <strong>Approval Inbox</strong> backoffice. Kata sandi akun konsumen hanya akan aktif setelah diverifikasi &amp; disetujui oleh Owner, Super Admin, atau Admin Toko.
                        </p>
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-4 flex-shrink-0">
                    <button type="button" wire:click="closeResetPasswordModal" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button type="button" wire:click="ajukanResetPassword" class="flex-1 py-3 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs cursor-pointer min-h-[44px]">Kirim Pengajuan Reset</button>
                </div>
            </div>
        </div>
    @endif
</div>
