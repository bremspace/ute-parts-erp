<div class="space-y-6"
    x-data="{
        subModul: @js(in_array($activeTab, ['users', 'role', 'cabang']) ? 'akses' : (in_array($activeTab, ['master_produk', 'master']) ? 'master' : 'bisnis')),
        setSubModul(modul) {
            this.subModul = modul;
            if (modul === 'akses' && !['users', 'role', 'cabang'].includes(@js($activeTab))) {
                $wire.set('activeTab', 'users');
            } else if (modul === 'master' && !['master_produk', 'master'].includes(@js($activeTab))) {
                $wire.set('activeTab', 'master_produk');
            } else if (modul === 'bisnis' && !['loyalitas', 'pajak'].includes(@js($activeTab))) {
                $wire.set('activeTab', 'loyalitas');
            }
        }
    }"
    x-init="$watch('$wire.activeTab', value => {
        if (['users', 'role', 'cabang'].includes(value)) subModul = 'akses';
        else if (['master_produk', 'master'].includes(value)) subModul = 'master';
        else if (['loyalitas', 'pajak'].includes(value)) subModul = 'bisnis';
    })"
>
    <!-- Header with 2-Tier Sub-Modul Tabs and Actions (No Overlapping on Mobile/Desktop) -->
    <div class="border-b border-black/10 dark:border-white/5 pb-4 space-y-3">
        <!-- Tier 1: Sub-Modul Selector -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-1 p-1 bg-black/5 dark:bg-white/[0.04] rounded-2xl border border-black/10 dark:border-white/5 w-full sm:w-auto overflow-x-auto scrollbar-none flex-nowrap">
                <button
                    type="button"
                    @click="setSubModul('akses')"
                    :class="subModul === 'akses' ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-bold' : 'text-ink-400 hover:text-ink-100 dark:hover:text-white'"
                    class="flex-1 sm:flex-initial flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl text-xs transition-[transform,background-color] active:scale-[0.97] min-h-[40px] whitespace-nowrap cursor-pointer"
                >
                    <span>👥</span>
                    <span>Akses & Cabang</span>
                </button>
                <button
                    type="button"
                    @click="setSubModul('master')"
                    :class="subModul === 'master' ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-bold' : 'text-ink-400 hover:text-ink-100 dark:hover:text-white'"
                    class="flex-1 sm:flex-initial flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl text-xs transition-[transform,background-color] active:scale-[0.97] min-h-[40px] whitespace-nowrap cursor-pointer"
                >
                    <span>📦</span>
                    <span>Master Data</span>
                </button>
                <button
                    type="button"
                    @click="setSubModul('bisnis')"
                    :class="subModul === 'bisnis' ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30 font-bold' : 'text-ink-400 hover:text-ink-100 dark:hover:text-white'"
                    class="flex-1 sm:flex-initial flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl text-xs transition-[transform,background-color] active:scale-[0.97] min-h-[40px] whitespace-nowrap cursor-pointer"
                >
                    <span>⚖️</span>
                    <span>Bisnis & Pajak</span>
                </button>
            </div>
        </div>

        <!-- Tier 2: Contextual Tabs & Quick Actions -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
            <!-- Contextual Tabs: smooth horizontal scroll on mobile, scrollbar-none, touch-pan-x -->
            <div class="flex items-center gap-1.5 sm:gap-2 overflow-x-auto pb-1 max-w-full flex-nowrap scrollbar-none scroll-smooth touch-pan-x">
                <!-- Group: Akses & Cabang -->
                <div x-show="subModul === 'akses'" class="flex items-center gap-1.5 sm:gap-2 flex-nowrap">
                    <button
                        wire:click="$set('activeTab', 'users')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'users' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        👥 Manajemen User
                    </button>
                    <button
                        wire:click="$set('activeTab', 'role')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'role' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        🔐 Role & Permission
                    </button>
                    <button
                        wire:click="$set('activeTab', 'cabang')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'cabang' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        🏬 Cabang & Gudang
                    </button>
                </div>

                <!-- Group: Master Data -->
                <div x-show="subModul === 'master'" class="flex items-center gap-1.5 sm:gap-2 flex-nowrap" x-cloak>
                    <button
                        wire:click="$set('activeTab', 'master_produk')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'master_produk' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        📦 Master Produk & Katalog
                    </button>
                    <button
                        wire:click="$set('activeTab', 'master')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'master' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        📋 Master Operasional
                    </button>
                </div>

                <!-- Group: Bisnis & Finansial -->
                <div x-show="subModul === 'bisnis'" class="flex items-center gap-1.5 sm:gap-2 flex-nowrap" x-cloak>
                    <button
                        wire:click="$set('activeTab', 'loyalitas')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'loyalitas' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        💎 Strategi Loyalitas
                    </button>
                    <button
                        wire:click="$set('activeTab', 'pajak')"
                        class="px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer {{ $activeTab === 'pajak' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-black/5 dark:bg-white/5 text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        ⚖️ Pajak & PPN
                    </button>
                </div>
            </div>

            <!-- Tab Action Button: Responsive placement, never overlaps -->
            <div class="flex items-center gap-2 flex-shrink-0 w-full sm:w-auto justify-end">
                @if($activeTab === 'users')
                    <button wire:click="openUserModal()" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer">+ User Baru</button>
                @elseif($activeTab === 'role')
                    <button wire:click="openRoleModal" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer">+ Role Baru</button>
                @elseif($activeTab === 'cabang')
                    <button wire:click="openCabangModal()" class="flex-1 sm:flex-initial px-4 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer">+ Cabang</button>
                    <button wire:click="openGudangModal()" class="flex-1 sm:flex-initial px-4 py-2.5 rounded-xl bg-up-primary text-white font-bold text-xs shadow-md shadow-up-primary/25 transition-[transform,background-color] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer">+ Gudang</button>
                @elseif($activeTab === 'master_produk')
                    @if($masterProdukSubTab === 'kategori')
                        <button wire:click="openKategoriModal()" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer">+ Kategori Baru</button>
                    @elseif($masterProdukSubTab === 'brand')
                        <button wire:click="openBrandModal()" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer">+ Brand Baru</button>
                    @elseif($masterProdukSubTab === 'kualitas')
                        <button wire:click="openKualitasModal()" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer">+ Kualitas Baru</button>
                    @elseif($masterProdukSubTab === 'satuan')
                        <button wire:click="openSatuanModal()" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer">+ Satuan Baru</button>
                    @elseif($masterProdukSubTab === 'kondisi')
                        <button wire:click="openKondisiModal()" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer">+ Kondisi Baru</button>
                    @elseif($masterProdukSubTab === 'tipe_hp')
                        <button wire:click="openTipeHpModal()" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-[transform,opacity] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer">+ Tipe HP Baru</button>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <!-- TAB: USERS -->
    @if($activeTab === 'users')
        <x-prism.data-table :headers="['Nama', 'Email', 'Role', 'Cabang', 'Status', 'Aksi']">
            @forelse($users as $u)
                <tr class="hover:bg-white/[0.02] transition-colors text-xs">
                    <td class="py-3.5 px-4 font-semibold text-white">
                        {{ $u->name }}
                        @if($u->phone)<span class="block text-[10px] text-ink-400 font-normal">{{ $u->phone }}</span>@endif
                    </td>
                    <td class="py-3.5 px-4 text-ink-300 font-mono">{{ $u->email }}</td>
                    <td class="py-3.5 px-4">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-primary/15 text-up-primary border border-up-primary/30">
                            {{ $u->roles->first()?->name ?? '-' }}
                        </span>
                    </td>
                    <td class="py-3.5 px-4 text-ink-300">{{ $u->cabangs->pluck('nama')->join(', ') ?: '-' }}</td>
                    <td class="py-3.5 px-4">
                        <button wire:click="toggleUserActive({{ $u->id }})" class="cursor-pointer">
                            <x-prism.status-pill :status="$u->is_active ? 'aktif' : 'batal'" size="sm" />
                        </button>
                    </td>
                    <td class="py-3.5 px-4">
                        <button wire:click="openUserModal({{ $u->id }})" class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-200 font-semibold text-[11px] cursor-pointer">Edit</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-12 text-center text-ink-400">Belum ada user.</td></tr>
            @endforelse

            <x-slot:pagination>{{ $users->links() }}</x-slot:pagination>
        </x-prism.data-table>

        <!-- Role matrix viewer (read-only summary) -->
        <div class="glass-panel rounded-2xl p-5 mt-4">
            <p class="text-sm font-bold text-white mb-3">Matriks Role & Permission (rekomendasi default PRD §3)</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                @foreach($roles as $r)
                    <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                        <p class="text-xs font-bold text-up-primary">{{ $r->name }}</p>
                        <p class="text-[10px] text-ink-400 mt-1">{{ $r->permissions->count() }} permission</p>
                        <p class="text-[9px] text-ink-500 mt-1 line-clamp-2">{{ $r->permissions->pluck('name')->take(6)->join(', ') }}</p>
                    </div>
                @endforeach
            </div>
            <p class="text-[10px] text-ink-500 mt-3">💡 Ubah akses per role di tab "<strong class="text-up-primary">Role & Permission</strong>".</p>
        </div>
    @endif

    <!-- TAB: ROLE & PERMISSION [T-25] -->
    @if($activeTab === 'role')
        <div class="space-y-4">
            @if($editRoleId)
                @php $roleEdit = $rolesFull->firstWhere('id', $editRoleId); @endphp
                <div class="glass-panel rounded-2xl p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-sm font-bold text-white">Edit Permission — <span class="text-up-primary">{{ $roleEdit?->name }}</span></h4>
                        <button wire:click="$set('editRoleId', null)" class="text-ink-400 hover:text-white text-xs cursor-pointer">✕ tutup</button>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 max-h-96 overflow-y-auto pr-1">
                        @foreach($permissionsList as $perm)
                            <label class="flex items-center gap-2 text-[11px] text-ink-200 cursor-pointer p-1.5 rounded-lg hover:bg-white/5">
                                <input type="checkbox" wire:model="editRolePermissions" value="{{ $perm->name }}" class="accent-up-mint w-3.5 h-3.5" />
                                <span class="font-mono">{{ $perm->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="mt-4 flex gap-2">
                        <button wire:click="$set('editRoleId', null)" class="px-4 py-2 rounded-xl bg-white/5 text-ink-300 text-xs font-semibold cursor-pointer">Batal</button>
                        <button wire:click="saveEditRolePermissions" class="px-4 py-2 rounded-xl bg-up-mint text-ink-950 text-xs font-bold cursor-pointer">Simpan Permission</button>
                    </div>
                    @if($roleEdit?->name === 'super-admin')
                        <p class="text-[10px] text-up-amber mt-2">⚠️ super-admin wajib minimal 1 permission (anti lockout).</p>
                    @endif
                </div>
            @endif

            <x-prism.data-table :headers="['Role', 'Permission', 'Edit']">
                @forelse($rolesFull as $r)
                    <tr class="hover:bg-white/[0.02] transition-colors text-xs">
                        <td class="py-3.5 px-4 font-bold text-white">
                            {{ $r->name }}
                            @if($r->name === 'super-admin')
                                <span class="ml-1 text-[9px] text-up-amber bg-up-amber/10 px-1.5 py-0.5 rounded-full">guardrail</span>
                            @endif
                        </td>
                        <td class="py-3.5 px-4 text-ink-300">{{ $r->permissions->count() }} permission</td>
                        <td class="py-3.5 px-4">
                            <button wire:click="openEditRole({{ $r->id }})" class="px-3 py-1.5 rounded-lg bg-up-primary/20 hover:bg-up-primary/35 text-up-primary font-bold text-[11px] cursor-pointer">Edit</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-12 text-center text-ink-400">Belum ada role.</td></tr>
                @endforelse
            </x-prism.data-table>
        </div>

        <!-- MODAL: ROLE BARU -->
        @if($showRoleModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
                <div class="w-full max-w-lg glass-panel p-6 rounded-3xl relative max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                        <h3 class="text-lg font-bold text-white">Role Baru</h3>
                        <button wire:click="$set('showRoleModal', false)" class="text-ink-400 hover:text-white">✕</button>
                    </div>
                    <div class="mb-3">
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Role *</label>
                        <input type="text" wire:model="roleBaruNama" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="opsi-gudang-regional" />
                    </div>
                    <p class="text-xs font-bold text-ink-300 mb-2">Pilih Permission</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-64 overflow-y-auto pr-1">
                        @foreach($permissionsList as $perm)
                            <label class="flex items-center gap-2 text-[11px] text-ink-200 cursor-pointer p-1.5 rounded-lg hover:bg-white/5">
                                <input type="checkbox" wire:model="roleBaruPermissions" value="{{ $perm->name }}" class="accent-up-mint w-3.5 h-3.5" />
                                <span class="font-mono">{{ $perm->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="flex gap-3 pt-4 border-t border-white/5 mt-4">
                        <button wire:click="$set('showRoleModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                        <button wire:click="saveRoleBaru" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer min-h-[44px]">Simpan Role</button>
                    </div>
                </div>
            </div>
        @endif
    @endif

    <!-- TAB: CABANG & GUDANG -->
    @if($activeTab === 'cabang')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Cabang -->
            <x-prism.glass-card title="Cabang" subtitle="Lokasi toko fisik">
                <div class="space-y-2">
                    @foreach($cabangs as $c)
                        <div class="flex justify-between items-center py-2 border-b border-white/5 last:border-0">
                            <div>
                                <p class="text-xs font-bold text-white">{{ $c->nama }} <span class="text-ink-500 font-mono">({{ $c->kode }})</span></p>
                                <p class="text-[10px] text-ink-400">{{ $c->alamat ?? '-' }} · {{ $c->gudang_count }} gudang · {{ $c->users_count }} staff</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button wire:click="openCabangModal({{ $c->id }})" class="text-[11px] text-up-primary hover:text-indigo-400 font-semibold cursor-pointer">Edit</button>
                                <button wire:click="hapusCabang({{ $c->id }})" wire:confirm="Yakin ingin menghapus/menonaktifkan cabang ini?" class="text-up-red hover:underline text-[11px] cursor-pointer">Hapus</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-prism.glass-card>

            <!-- Gudang -->
            <x-prism.glass-card title="Gudang" subtitle="Lokasi penyimpanan stok">
                <div class="space-y-2">
                    @foreach($gudangs as $g)
                        <div class="flex justify-between items-center py-2 border-b border-white/5 last:border-0">
                            <div>
                                <p class="text-xs font-bold text-white">{{ $g->nama }} <span class="text-ink-500 font-mono">({{ $g->kode }})</span></p>
                                <p class="text-[10px] text-ink-400">{{ $g->cabang?->nama }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button wire:click="openGudangModal({{ $g->id }})" class="text-[11px] text-up-primary hover:text-indigo-400 font-semibold cursor-pointer">Edit</button>
                                <button wire:click="hapusGudang({{ $g->id }})" wire:confirm="Yakin ingin menghapus/menonaktifkan gudang ini?" class="text-up-red hover:underline text-[11px] cursor-pointer">Hapus</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-prism.glass-card>
        </div>
    @endif

    <!-- TAB: STRATEGI LOYALITAS [T-22] -->
    @if($activeTab === 'loyalitas')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-prism.glass-card title="Strategi Loyalitas" subtitle="Rasio poin & diskon per tier — editable tanpa deploy (T-22)">
                <form wire:submit="simpanLoyalitas" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Rasio Earn Poin (% dari transaksi)</label>
                            <input type="number" step="0.01" wire:model="loyalitasForm.poin_earn_persen" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium tabular-nums" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Rasio Redeem (1 poin = Rp ...)</label>
                            <input type="text" inputmode="numeric" x-format-number wire:model="loyalitasForm.poin_redeem_rupiah" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium tabular-nums" />
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Diskon Silver (%)</label>
                            <input type="number" step="0.01" wire:model="loyalitasForm.diskon_silver" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium tabular-nums" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Diskon Gold (%)</label>
                            <input type="number" step="0.01" wire:model="loyalitasForm.diskon_gold" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium tabular-nums" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Diskon Platinum (%)</label>
                            <input type="number" step="0.01" wire:model="loyalitasForm.diskon_platinum" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium tabular-nums" />
                        </div>
                    </div>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs cursor-pointer">Simpan Strategi</button>
                </form>
                <p class="text-[10px] text-up-amber mt-3">⚠️ Perubahan <strong>non-retroaktif</strong> — hanya berlaku untuk transaksi baru, riwayat lama tidak berubah.</p>
            </x-prism.glass-card>

            <x-prism.glass-card title="Skema Komisi Default" subtitle="Fallback komisi reseller bila tidak ada skema custom per reseller">
                <div class="space-y-3">
                    @foreach($skemaKomisiForm as $i => $sk)
                        <div class="flex items-center gap-2 pb-2.5 border-b border-white/5 last:border-0 flex-wrap">
                            <div class="flex-1 min-w-[140px]">
                                <p class="text-xs font-bold text-white">{{ $sk['nama'] }}</p>
                                <p class="text-[10px] text-ink-400">{{ $sk['kategori'] ?: 'Semua kategori' }}</p>
                            </div>
                            <select wire:model="skemaKomisiForm.{{ $i }}.tipe" class="px-2 py-1.5 rounded-lg glass-input text-[11px] font-medium">
                                <option value="persen" class="bg-ink-900">%</option>
                                <option value="nominal" class="bg-ink-900">Rp</option>
                            </select>
                            <input type="number" step="0.01" min="0" wire:model="skemaKomisiForm.{{ $i }}.nilai" class="w-24 px-2 py-1.5 rounded-lg glass-input text-[11px] font-medium tabular-nums" />
                        </div>
                    @endforeach
                </div>
                <p class="text-[10px] text-ink-500 mt-3">Disimpan bersama strategi loyalitas (tombol "Simpan Strategi").</p>
            </x-prism.glass-card>
        </div>
    @endif

    <!-- TAB: KONFIGURASI PAJAK & PPN -->
    @if($activeTab === 'pajak')
        <div class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Form Pengaturan Pajak -->
                <div class="lg:col-span-2 space-y-6">
                    <x-prism.glass-card title="Pengaturan Pajak Aplikasi" subtitle="Pilih apakah aplikasi memungut pajak (PPN/lainnya) atau beroperasi tanpa pajak">
                        <form wire:submit.prevent="simpanPajak" class="space-y-5">
                            <!-- Cakupan Konfigurasi -->
                            <div class="p-4 rounded-xl bg-white/[0.02] border border-white/5 space-y-2">
                                <label class="block text-xs font-semibold text-ink-300">Target Penerapan Konfigurasi</label>
                                <select wire:model.live="pajakForm.target" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                    <option value="global" class="bg-ink-900">🌐 Global (Berlaku untuk Semua Cabang)</option>
                                    @foreach($cabangs as $c)
                                        <option value="{{ $c->id }}" class="bg-ink-900">🏬 Cabang: {{ $c->nama }} ({{ $c->kode }})</option>
                                    @endforeach
                                </select>
                                <p class="text-[11px] text-ink-400">Pilih cabang tertentu bila ada cabang PKP (Pengusaha Kena Pajak) dan cabang non-PKP.</p>
                            </div>

                            <!-- Sakelar Status Pajak -->
                            <div class="p-4 rounded-xl {{ $pajakForm['enabled'] ? 'bg-up-primary/10 border-up-primary/30' : 'bg-white/[0.02] border-white/5' }} border transition-all">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-bold text-white flex items-center gap-2">
                                            <span>Status Pajak:</span>
                                            @if($pajakForm['enabled'])
                                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-up-mint/20 text-up-mint border border-up-mint/30">AKTIF (DENGAN PAJAK)</span>
                                            @else
                                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-white/10 text-ink-300 border border-white/10">NONAKTIF (TANPA PAJAK)</span>
                                            @endif
                                        </p>
                                        <p class="text-xs text-ink-400 mt-1">
                                            {{ $pajakForm['enabled'] ? 'Aplikasi akan menghitung pajak secara otomatis pada POS Kasir & Transaksi Marketplace.' : 'Aplikasi berjalan murni tanpa pungutan pajak. Subtotal = Total bayar tanpa potongan/beban pajak.' }}
                                        </p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" wire:model.live="pajakForm.enabled" class="sr-only peer">
                                        <div class="w-11 h-6 bg-white/20 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-up-mint"></div>
                                    </label>
                                </div>
                            </div>

                            <!-- Detail Parameter Pajak (Muncul saat Pajak Aktif) -->
                            @if($pajakForm['enabled'])
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                                    <div>
                                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama / Label Pajak *</label>
                                        <input type="text" wire:model="pajakForm.nama" placeholder="Contoh: PPN, PB1, Pajak Resto" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                                        <p class="text-[10px] text-ink-400 mt-1">Nama ini dicetak di struk belanja kasir dan faktur penjualan.</p>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Tarif Pajak (%) *</label>
                                        <div class="relative">
                                            <input type="number" step="0.1" min="0" max="100" wire:model="pajakForm.percent" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium tabular-nums pr-8" />
                                            <span class="absolute right-3 top-2.5 text-xs text-ink-400 font-bold">%</span>
                                        </div>
                                        <p class="text-[10px] text-ink-400 mt-1">Standar Indonesia: 11% (atau 12% sesuai regulasi UU HPP).</p>
                                    </div>

                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Sistem Perhitungan Pajak</label>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <label class="p-3.5 rounded-xl border cursor-pointer transition-all {{ $pajakForm['mode'] === 'exclusive' ? 'border-up-primary bg-up-primary/10' : 'border-white/5 bg-white/[0.02] hover:bg-white/5' }}">
                                                <input type="radio" wire:model="pajakForm.mode" value="exclusive" class="sr-only">
                                                <p class="text-xs font-bold text-white">Tax-Exclusive (Tambahan)</p>
                                                <p class="text-[11px] text-ink-400 mt-1">Harga katalog adalah DPP murni. Pajak ditambahkan di akhir kasir (Total = Harga + Pajak).</p>
                                            </label>

                                            <label class="p-3.5 rounded-xl border cursor-pointer transition-all {{ $pajakForm['mode'] === 'inclusive' ? 'border-up-primary bg-up-primary/10' : 'border-white/5 bg-white/[0.02] hover:bg-white/5' }}">
                                                <input type="radio" wire:model="pajakForm.mode" value="inclusive" class="sr-only">
                                                <p class="text-xs font-bold text-white">Tax-Inclusive (Sudah Termasuk)</p>
                                                <p class="text-[11px] text-ink-400 mt-1">Harga katalog sudah termasuk pajak. Pajak dihitung mundur dari nilai bayar konsumen.</p>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="pt-4 border-t border-white/5 flex items-center justify-between">
                                <button type="submit" class="px-6 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs cursor-pointer">
                                    Simpan Pengaturan Pajak
                                </button>
                                <span class="text-[11px] text-ink-400">Target: <strong class="text-white">{{ $pajakForm['target'] === 'global' ? 'Global (Semua Cabang)' : 'Cabang Tertentu' }}</strong></span>
                            </div>
                        </form>
                    </x-prism.glass-card>
                </div>

                <!-- Panel Simulasi & Panduan Akuntansi -->
                <div class="space-y-6">
                    <x-prism.glass-card title="Simulasi Perhitungan" subtitle="Contoh transaksi nominal Rp 100.000">
                        @php
                            $nominalSimulasi = 100000;
                            $tarifSimulasi = (float) ($pajakForm['percent'] ?? 11);
                            $isAktif = (bool) ($pajakForm['enabled'] ?? false);
                            $modeSimulasi = $pajakForm['mode'] ?? 'exclusive';

                            if (! $isAktif) {
                                $dppSim = $nominalSimulasi;
                                $pajakSim = 0;
                                $totalSim = $nominalSimulasi;
                            } elseif ($modeSimulasi === 'inclusive') {
                                $dppSim = round($nominalSimulasi / (1 + ($tarifSimulasi / 100)), 2);
                                $pajakSim = round($nominalSimulasi - $dppSim, 2);
                                $totalSim = $nominalSimulasi;
                            } else {
                                $dppSim = $nominalSimulasi;
                                $pajakSim = round($nominalSimulasi * ($tarifSimulasi / 100), 2);
                                $totalSim = $dppSim + $pajakSim;
                            }
                        @endphp
                        <div class="space-y-3 text-xs">
                            <div class="flex justify-between py-1.5 border-b border-white/5">
                                <span class="text-ink-400">Status Pajak</span>
                                <span class="font-bold {{ $isAktif ? 'text-up-mint' : 'text-ink-300' }}">
                                    {{ $isAktif ? 'Aktif ('.($pajakForm['nama'] ?? 'PPN').' '.$tarifSimulasi.'%)' : 'Nonaktif (Tanpa Pajak)' }}
                                </span>
                            </div>
                            <div class="flex justify-between py-1.5 border-b border-white/5">
                                <span class="text-ink-400">Dasar Pengenaan Pajak (DPP)</span>
                                <span class="font-bold text-white font-mono tabular-nums">Rp {{ number_format($dppSim, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between py-1.5 border-b border-white/5">
                                <span class="text-ink-400">Nilai {{ $pajakForm['nama'] ?? 'Pajak' }}</span>
                                <span class="font-bold {{ $pajakSim > 0 ? 'text-up-mint' : 'text-ink-400' }} font-mono tabular-nums">Rp {{ number_format($pajakSim, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between py-2 border-t border-white/10 font-bold text-sm">
                                <span class="text-white">Total Akhir Ditagih</span>
                                <span class="text-up-primary font-mono tabular-nums">Rp {{ number_format($totalSim, 0, ',', '.') }}</span>
                            </div>
                        </div>

                        <div class="mt-5 p-3 rounded-xl bg-white/[0.02] border border-white/5 text-[11px] space-y-1.5 text-ink-300">
                            <p class="font-bold text-white">Pembukuan Akuntansi:</p>
                            <p>• Pendapatan Penjualan: diakui sebesar <strong class="text-white">DPP</strong> (akun 410-01).</p>
                            <p>• Utang Pajak Keluaran: dialokasikan ke akun <strong class="text-up-mint">220-01</strong>.</p>
                            <p>• Jika nonaktif: 100% total transaksi dibukukan ke pendapatan tanpa pos utang pajak.</p>
                        </div>
                    </x-prism.glass-card>

                    <x-prism.glass-card title="Kepatuhan & Laporan" subtitle="Konektivitas dengan modul Akunting">
                        <p class="text-xs text-ink-300">
                            Seluruh rekonsiliasi PPN Keluaran & PPN Masukan dapat dipantau dan diekspor ke format e-Faktur di menu:
                        </p>
                        <a href="{{ route('laporan.pajak') }}" class="mt-3 inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-up-primary font-bold text-xs transition-colors">
                            <span>Buka Laporan Pajak Bulanan →</span>
                        </a>
                    </x-prism.glass-card>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB: MASTER PRODUK & KATALOG -->
    @if($activeTab === 'master_produk')
        <div class="space-y-4">
            <!-- Sub-tab Bar: Smooth horizontal scrolling, touch-pan-x, active:scale-[0.97] -->
            <div class="flex items-center gap-1.5 sm:gap-2 overflow-x-auto pb-2 border-b border-black/10 dark:border-white/5 scrollbar-none scroll-smooth touch-pan-x flex-nowrap">
                @foreach([
                    'kategori' => ['icon' => '🗂️', 'label' => 'Kategori Hierarkis', 'count' => count($kategoriList)],
                    'brand' => ['icon' => '🏷️', 'label' => 'Brand Produk', 'count' => count($brandList)],
                    'kualitas' => ['icon' => '⭐', 'label' => 'Tingkat Kualitas', 'count' => count($kualitasList)],
                    'satuan' => ['icon' => '📏', 'label' => 'Satuan Unit', 'count' => count($satuanList)],
                    'kondisi' => ['icon' => '🔄', 'label' => 'Kondisi Produk', 'count' => count($kondisiList)],
                    'tipe_hp' => ['icon' => '📱', 'label' => 'Tipe HP Kompatibel', 'count' => count($tipeHpList)],
                ] as $subKey => $subData)
                    <button
                        wire:click="$set('masterProdukSubTab', '{{ $subKey }}')"
                        class="px-3.5 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 transition-[transform,background-color] active:scale-[0.97] min-h-[40px] cursor-pointer whitespace-nowrap {{ $masterProdukSubTab === $subKey ? 'bg-up-primary/20 text-white border border-up-primary/40 font-bold shadow-sm shadow-up-primary/10' : 'bg-black/5 dark:bg-white/5 text-ink-300 hover:bg-black/10 dark:hover:bg-white/10' }}"
                    >
                        <span>{{ $subData['icon'] }}</span>
                        <span>{{ $subData['label'] }}</span>
                        <span class="text-[10px] px-1.5 py-0.5 rounded-full {{ $masterProdukSubTab === $subKey ? 'bg-up-primary text-white' : 'bg-black/10 dark:bg-white/10 text-ink-400' }}">{{ $subData['count'] }}</span>
                    </button>
                @endforeach
            </div>

            <!-- SUB-TAB 1: KATEGORI -->
            @if($masterProdukSubTab === 'kategori')
                <x-prism.glass-card title="Katalog Kategori Berjenjang" subtitle="Kelola kategori utama dan sub-kategori untuk pengelompokan produk">
                    <x-prism.dual-scroll>
                        <table class="w-full text-left text-xs text-ink-200">
                            <thead class="text-ink-400 border-b border-white/10 uppercase text-[10px]">
                                <tr>
                                    <th class="py-2.5 px-3">Urutan</th>
                                    <th class="py-2.5 px-3">Nama Kategori</th>
                                    <th class="py-2.5 px-3">Slug / Path</th>
                                    <th class="py-2.5 px-3">Tipe / Induk</th>
                                    <th class="py-2.5 px-3 text-center">Jml Produk</th>
                                    <th class="py-2.5 px-3 text-center">Status</th>
                                    <th class="py-2.5 px-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @forelse($kategoriList as $k)
                                    <tr class="hover:bg-white/[0.02] transition-colors">
                                        <td class="py-2.5 px-3 font-mono text-ink-400">{{ $k->urutan }}</td>
                                        <td class="py-2.5 px-3">
                                            @if($k->parent_id)
                                                <div class="flex items-center gap-1.5 pl-5">
                                                    <span class="text-ink-500">↳</span>
                                                    <span class="font-medium text-white">{{ $k->nama }}</span>
                                                </div>
                                            @else
                                                <div class="flex items-center gap-2 font-bold text-white">
                                                    <span>{{ $k->icon ?? '📁' }}</span>
                                                    <span>{{ $k->nama }}</span>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="py-2.5 px-3 font-mono text-[11px] text-ink-400">{{ $k->slug }}</td>
                                        <td class="py-2.5 px-3">
                                            @if($k->parent)
                                                <span class="px-2 py-0.5 rounded bg-white/5 text-[10px] text-ink-300">Sub dari {{ $k->parent->nama }}</span>
                                            @else
                                                <span class="px-2 py-0.5 rounded bg-up-primary/15 text-[10px] text-up-primary font-semibold">Kategori Utama</span>
                                            @endif
                                        </td>
                                        <td class="py-2.5 px-3 text-center font-bold tabular-nums {{ $k->produk_count > 0 ? 'text-up-mint' : 'text-ink-500' }}">
                                            {{ $k->produk_count }}
                                        </td>
                                        <td class="py-2.5 px-3 text-center">
                                            <button wire:click="toggleKategoriStatus({{ $k->id }})" class="cursor-pointer">
                                                <x-prism.status-pill :status="$k->is_active ? 'active' : 'inactive'" :label="$k->is_active ? 'Aktif' : 'Nonaktif'" />
                                            </button>
                                        </td>
                                        <td class="py-2.5 px-3 text-right space-x-2">
                                            <button wire:click="openKategoriModal({{ $k->id }})" class="text-up-primary hover:underline font-semibold text-xs cursor-pointer">Edit</button>
                                            <button wire:click="hapusKategori({{ $k->id }})" wire:confirm="Yakin ingin menghapus/menonaktifkan kategori ini?" class="text-up-red hover:underline text-xs cursor-pointer">Hapus</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-6 text-center text-ink-500">Belum ada kategori produk. Klik "+ Kategori Baru" untuk menambahkan.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </x-prism.dual-scroll>
                </x-prism.glass-card>

            <!-- SUB-TAB 2: BRAND -->
            @elseif($masterProdukSubTab === 'brand')
                <x-prism.glass-card title="Master Brand Produk" subtitle="Kelola merek produk suku cadang dan aksesoris">
                    <x-prism.dual-scroll>
                        <table class="w-full text-left text-xs text-ink-200">
                            <thead class="text-ink-400 border-b border-white/10 uppercase text-[10px]">
                                <tr>
                                    <th class="py-2.5 px-3">Nama Brand</th>
                                    <th class="py-2.5 px-3">Keterangan</th>
                                    <th class="py-2.5 px-3 text-center">Jml Produk</th>
                                    <th class="py-2.5 px-3 text-center">Status</th>
                                    <th class="py-2.5 px-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @forelse($brandList as $b)
                                    <tr class="hover:bg-white/[0.02] transition-colors">
                                        <td class="py-2.5 px-3 font-bold text-white">{{ $b->nama }}</td>
                                        <td class="py-2.5 px-3 text-ink-400">{{ $b->keterangan ?? '—' }}</td>
                                        <td class="py-2.5 px-3 text-center font-bold tabular-nums {{ $b->produk_count > 0 ? 'text-up-mint' : 'text-ink-500' }}">
                                            {{ $b->produk_count }}
                                        </td>
                                        <td class="py-2.5 px-3 text-center">
                                            <button wire:click="toggleBrandStatus({{ $b->id }})" class="cursor-pointer">
                                                <x-prism.status-pill :status="$b->is_active ? 'active' : 'inactive'" :label="$b->is_active ? 'Aktif' : 'Nonaktif'" />
                                            </button>
                                        </td>
                                        <td class="py-2.5 px-3 text-right space-x-2">
                                            <button wire:click="openBrandModal({{ $b->id }})" class="text-up-primary hover:underline font-semibold text-xs cursor-pointer">Edit</button>
                                            <button wire:click="hapusBrand({{ $b->id }})" wire:confirm="Yakin ingin menghapus/menonaktifkan brand ini?" class="text-up-red hover:underline text-xs cursor-pointer">Hapus</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-6 text-center text-ink-500">Belum ada data brand produk.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </x-prism.dual-scroll>
                </x-prism.glass-card>

            <!-- SUB-TAB 3: KUALITAS -->
            @elseif($masterProdukSubTab === 'kualitas')
                <x-prism.glass-card title="Master Tingkat Kualitas" subtitle="Klasifikasi kualitas suku cadang (Original, Grade A, OEM, dll)">
                    <x-prism.dual-scroll>
                        <table class="w-full text-left text-xs text-ink-200">
                            <thead class="text-ink-400 border-b border-white/10 uppercase text-[10px]">
                                <tr>
                                    <th class="py-2.5 px-3">Tingkat Kualitas</th>
                                    <th class="py-2.5 px-3">Keterangan</th>
                                    <th class="py-2.5 px-3 text-center">Jml Produk</th>
                                    <th class="py-2.5 px-3 text-center">Status</th>
                                    <th class="py-2.5 px-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @forelse($kualitasList as $k)
                                    <tr class="hover:bg-white/[0.02] transition-colors">
                                        <td class="py-2.5 px-3 font-bold text-white">{{ $k->nama }}</td>
                                        <td class="py-2.5 px-3 text-ink-400">{{ $k->keterangan ?? '—' }}</td>
                                        <td class="py-2.5 px-3 text-center font-bold tabular-nums {{ $k->produk_count > 0 ? 'text-up-mint' : 'text-ink-500' }}">
                                            {{ $k->produk_count }}
                                        </td>
                                        <td class="py-2.5 px-3 text-center">
                                            <button wire:click="toggleKualitasStatus({{ $k->id }})" class="cursor-pointer">
                                                <x-prism.status-pill :status="$k->is_active ? 'active' : 'inactive'" :label="$k->is_active ? 'Aktif' : 'Nonaktif'" />
                                            </button>
                                        </td>
                                        <td class="py-2.5 px-3 text-right space-x-2">
                                            <button wire:click="openKualitasModal({{ $k->id }})" class="text-up-primary hover:underline font-semibold text-xs cursor-pointer">Edit</button>
                                            <button wire:click="hapusKualitas({{ $k->id }})" wire:confirm="Yakin ingin menghapus kualitas ini?" class="text-up-red hover:underline text-xs cursor-pointer">Hapus</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-6 text-center text-ink-500">Belum ada data tingkat kualitas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </x-prism.dual-scroll>
                </x-prism.glass-card>

            <!-- SUB-TAB 4: SATUAN -->
            @elseif($masterProdukSubTab === 'satuan')
                <x-prism.glass-card title="Master Satuan Unit (UOM)" subtitle="Daftar satuan kuantitas barang inventaris (pcs, unit, box, set, dll)">
                    <x-prism.dual-scroll>
                        <table class="w-full text-left text-xs text-ink-200">
                            <thead class="text-ink-400 border-b border-white/10 uppercase text-[10px]">
                                <tr>
                                    <th class="py-2.5 px-3">Kode Satuan</th>
                                    <th class="py-2.5 px-3">Nama Satuan</th>
                                    <th class="py-2.5 px-3 text-center">Status</th>
                                    <th class="py-2.5 px-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @forelse($satuanList as $s)
                                    <tr class="hover:bg-white/[0.02] transition-colors">
                                        <td class="py-2.5 px-3 font-mono font-bold text-up-primary">{{ $s->kode }}</td>
                                        <td class="py-2.5 px-3 text-white font-medium">{{ $s->nama }}</td>
                                        <td class="py-2.5 px-3 text-center">
                                            <button wire:click="toggleSatuanStatus({{ $s->id }})" class="cursor-pointer">
                                                <x-prism.status-pill :status="$s->is_active ? 'active' : 'inactive'" :label="$s->is_active ? 'Aktif' : 'Nonaktif'" />
                                            </button>
                                        </td>
                                        <td class="py-2.5 px-3 text-right space-x-2">
                                            <button wire:click="openSatuanModal({{ $s->id }})" class="text-up-primary hover:underline font-semibold text-xs cursor-pointer">Edit</button>
                                            <button wire:click="hapusSatuan({{ $s->id }})" wire:confirm="Hapus satuan ini?" class="text-up-red hover:underline text-xs cursor-pointer">Hapus</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-6 text-center text-ink-500">Belum ada data satuan unit.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </x-prism.dual-scroll>
                </x-prism.glass-card>

            <!-- SUB-TAB 5: KONDISI -->
            @elseif($masterProdukSubTab === 'kondisi')
                <x-prism.glass-card title="Master Opsi Kondisi Produk" subtitle="Opsi kondisi barang yang muncul pada form registrasi dan katalog">
                    <x-prism.dual-scroll>
                        <table class="w-full text-left text-xs text-ink-200">
                            <thead class="text-ink-400 border-b border-white/10 uppercase text-[10px]">
                                <tr>
                                    <th class="py-2.5 px-3">Kode Internal</th>
                                    <th class="py-2.5 px-3">Nama Label Kondisi</th>
                                    <th class="py-2.5 px-3 text-center">Status</th>
                                    <th class="py-2.5 px-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @forelse($kondisiList as $c)
                                    <tr class="hover:bg-white/[0.02] transition-colors">
                                        <td class="py-2.5 px-3 font-mono text-ink-400">{{ $c['kode'] }}</td>
                                        <td class="py-2.5 px-3 font-bold text-white">{{ $c['nama'] }}</td>
                                        <td class="py-2.5 px-3 text-center">
                                            <button wire:click="toggleKondisiStatus('{{ $c['kode'] }}')" class="cursor-pointer">
                                                <x-prism.status-pill :status="($c['is_active'] ?? true) ? 'active' : 'inactive'" :label="($c['is_active'] ?? true) ? 'Aktif' : 'Nonaktif'" />
                                            </button>
                                        </td>
                                        <td class="py-2.5 px-3 text-right space-x-2">
                                            <button wire:click="openKondisiModal('{{ $c['kode'] }}')" class="text-up-primary hover:underline font-semibold text-xs cursor-pointer">Edit</button>
                                            <button wire:click="hapusKondisi('{{ $c['kode'] }}')" wire:confirm="Hapus kondisi ini?" class="text-up-red hover:underline text-xs cursor-pointer">Hapus</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-6 text-center text-ink-500">Belum ada opsi kondisi.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </x-prism.dual-scroll>
                </x-prism.glass-card>

            <!-- SUB-TAB 6: TIPE HP -->
            @elseif($masterProdukSubTab === 'tipe_hp')
                <x-prism.glass-card title="Master Tipe HP / Perangkat Kompatibel" subtitle="Database tipe perangkat untuk pemetaan kompatibilitas sparepart">
                    <x-prism.dual-scroll>
                        <table class="w-full text-left text-xs text-ink-200">
                            <thead class="text-ink-400 border-b border-white/10 uppercase text-[10px]">
                                <tr>
                                    <th class="py-2.5 px-3">Merek Perangkat</th>
                                    <th class="py-2.5 px-3">Model</th>
                                    <th class="py-2.5 px-3">Nama Lengkap</th>
                                    <th class="py-2.5 px-3 text-center">Jml Part Terkait</th>
                                    <th class="py-2.5 px-3 text-center">Status</th>
                                    <th class="py-2.5 px-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @forelse($tipeHpList as $t)
                                    <tr class="hover:bg-white/[0.02] transition-colors">
                                        <td class="py-2.5 px-3 font-semibold text-white">{{ $t->merk }}</td>
                                        <td class="py-2.5 px-3 font-mono text-ink-300">{{ $t->model }}</td>
                                        <td class="py-2.5 px-3 text-white">{{ $t->nama }}</td>
                                        <td class="py-2.5 px-3 text-center font-bold tabular-nums {{ $t->produk_count > 0 ? 'text-up-mint' : 'text-ink-500' }}">
                                            {{ $t->produk_count }}
                                        </td>
                                        <td class="py-2.5 px-3 text-center">
                                            <button wire:click="toggleTipeHpStatus({{ $t->id }})" class="cursor-pointer">
                                                <x-prism.status-pill :status="$t->is_active ? 'active' : 'inactive'" :label="$t->is_active ? 'Aktif' : 'Nonaktif'" />
                                            </button>
                                        </td>
                                        <td class="py-2.5 px-3 text-right space-x-2">
                                            <button wire:click="openTipeHpModal({{ $t->id }})" class="text-up-primary hover:underline font-semibold text-xs cursor-pointer">Edit</button>
                                            <button wire:click="hapusTipeHp({{ $t->id }})" wire:confirm="Hapus tipe HP ini?" class="text-up-red hover:underline text-xs cursor-pointer">Hapus</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-6 text-center text-ink-500">Belum ada data tipe HP.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </x-prism.dual-scroll>
                </x-prism.glass-card>
            @endif
        </div>
    @endif

    <!-- TAB: MASTER DATA OPERASIONAL -->
    @if($activeTab === 'master')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-prism.glass-card title="Jenis Servis & Garansi" subtitle="Kategori & durasi garansi editable (T-16)">
                <div class="space-y-2">
                    @foreach($jenisServis as $j)
                        <div class="flex justify-between items-center py-2 border-b border-white/5 last:border-0">
                            <div>
                                <p class="text-xs font-bold text-white">{{ $j->nama }}</p>
                                <p class="text-[10px] text-ink-400 font-mono">
                                    {{ $j->kode }} · {{ $j->kategori }} · est. {{ $j->estimasi_durasi }} mnt · Rp {{ number_format($j->biaya_jasa ?? 0, 0, ',', '.') }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-semibold {{ $j->is_part_original ? 'text-up-accent' : 'text-up-mint' }} tabular-nums">
                                    {{ $j->durasi_garansi_hari }} hari
                                </span>
                                @if($j->butuh_part)
                                    <span class="text-[9px] text-up-primary bg-up-primary/10 px-1.5 py-0.5 rounded">part</span>
                                @endif
                                <button wire:click="openJenisServisModal({{ $j->id }})" class="text-[11px] text-up-primary hover:text-indigo-400 font-semibold cursor-pointer">Edit</button>
                                <button wire:click="hapusJenisServis({{ $j->id }})" wire:confirm="Yakin ingin menghapus/menonaktifkan jenis servis ini?" class="text-up-red hover:underline text-[11px] cursor-pointer">Hapus</button>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3">
                    <button wire:click="openJenisServisModal()" class="px-3 py-2 rounded-lg bg-up-mint hover:opacity-90 text-ink-950 font-bold text-[11px] cursor-pointer">+ Jenis Servis Baru</button>
                </div>
            </x-prism.glass-card>

            <x-prism.glass-card title="Chart of Account (COA)" subtitle="Standar retail + servis (PRD §4.6)">
                <div class="max-h-96 overflow-y-auto space-y-1 pr-1">
                    @foreach($coaList as $akun)
                        <div class="flex justify-between py-1.5 border-b border-white/5 last:border-0 text-xs">
                            <span class="font-mono text-up-primary">{{ $akun->kode }}</span>
                            <span class="text-ink-200 flex-1 ml-2">{{ $akun->nama }}</span>
                            <span class="text-[9px] uppercase text-ink-400">{{ $akun->tipe }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="text-[10px] text-ink-500 mt-3">Kelola di Modul Akunting → COA (editable terbatas super-admin/finance).</p>
            </x-prism.glass-card>
        </div>
    @endif

    <!-- MODAL: USER -->
    @if($showUserModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">{{ $userForm['id'] ? 'Edit User' : 'User Baru' }}</h3>
                    <button wire:click="$set('showUserModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama *</label>
                        <input type="text" wire:model="userForm.name" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Email *</label>
                        <input type="email" wire:model="userForm.email" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Telepon</label>
                        <input type="text" wire:model="userForm.phone" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">{{ $userForm['id'] ? 'Password (kosongkan = tidak diubah)' : 'Password *' }}</label>
                        <input type="password" wire:model="userForm.password" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="Min 6 karakter" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Role</label>
                        <select wire:model="userForm.role" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            @foreach($roles as $r)
                                <option value="{{ $r->name }}" class="bg-ink-900">{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Akses Cabang &amp; Cabang Utama</label>
                        <div class="space-y-2">
                            @foreach($cabangs as $c)
                                <div class="flex items-center justify-between p-2 rounded-xl bg-white/[0.02] border border-white/5">
                                    <label class="flex items-center gap-2 text-xs text-ink-200 cursor-pointer">
                                        <input type="checkbox" wire:model.live="userForm.cabang_ids" value="{{ $c->id }}" class="accent-up-mint w-4 h-4" />
                                        {{ $c->nama }}
                                    </label>
                                    @if(in_array($c->id, array_map('intval', $userForm['cabang_ids'] ?? [])))
                                        <label class="flex items-center gap-1.5 text-[11px] text-ink-400 cursor-pointer">
                                            <input type="radio" wire:model="userForm.cabang_default_id" value="{{ $c->id }}" name="user_default_cabang" class="accent-up-primary w-3.5 h-3.5" />
                                            Utama (Default)
                                        </label>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-xs text-ink-300 cursor-pointer">
                        <input type="checkbox" wire:model="userForm.is_active" class="accent-up-mint w-4 h-4" />
                        Akun Aktif
                    </label>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showUserModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="saveUser" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer min-h-[44px]">Simpan User</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: CABANG -->
    @if($showCabangModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">{{ $cabangForm['id'] ? 'Edit Cabang' : 'Cabang Baru' }}</h3>
                    <button wire:click="$set('showCabangModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama *</label>
                        <input type="text" wire:model="cabangForm.nama" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kode *</label>
                        <input type="text" wire:model="cabangForm.kode" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-mono" placeholder="CBG-03" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Alamat</label>
                        <textarea wire:model="cabangForm.alamat" rows="2" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Telepon</label>
                        <input type="text" wire:model="cabangForm.telepon" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs" />
                    </div>
                </div>
                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showCabangModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="saveCabang" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer min-h-[44px]">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: GUDANG -->
    @if($showGudangModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">{{ $gudangForm['id'] ? 'Edit Gudang' : 'Gudang Baru' }}</h3>
                    <button wire:click="$set('showGudangModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Cabang *</label>
                        <select wire:model="gudangForm.cabang_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            <option value="" class="bg-ink-900">Pilih Cabang...</option>
                            @foreach($cabangs as $c)
                                <option value="{{ $c->id }}" class="bg-ink-900">{{ $c->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama *</label>
                        <input type="text" wire:model="gudangForm.nama" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kode *</label>
                        <input type="text" wire:model="gudangForm.kode" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-mono" placeholder="GDG-04" />
                    </div>
                </div>
                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showGudangModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="saveGudang" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer min-h-[44px]">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    <!-- [T-16] MODAL: JENIS SERVIS -->
    @if($showJenisServisModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">{{ $jenisServisForm['id'] ? 'Edit Jenis Servis' : 'Jenis Servis Baru' }}</h3>
                    <button wire:click="$set('showJenisServisModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama *</label>
                        <input type="text" wire:model="jenisServisForm.nama" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kode *</label>
                            <input type="text" wire:model="jenisServisForm.kode" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-mono" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kategori</label>
                            <select wire:model="jenisServisForm.kategori" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs">
                                <option value="hardware" class="bg-ink-900">Hardware</option>
                                <option value="software" class="bg-ink-900">Software</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Estimasi Durasi (menit)</label>
                            <input type="number" wire:model="jenisServisForm.estimasi_durasi" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Durasi Garansi (hari)</label>
                            <input type="number" wire:model="jenisServisForm.durasi_garansi_hari" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Tarif Biaya Jasa Standar (Rp)</label>
                        <input type="text" inputmode="numeric" x-format-number wire:model="jenisServisForm.biaya_jasa" placeholder="0" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-bold tabular-nums" />
                        <p class="text-[10px] text-ink-400 mt-1">Tarif acuan otomatis saat memilih jasa ini pada estimasi servis.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 text-xs text-ink-300 cursor-pointer">
                            <input type="checkbox" wire:model="jenisServisForm.butuh_part" class="accent-up-mint w-4 h-4" /> Butuh part
                        </label>
                        <label class="flex items-center gap-2 text-xs text-ink-300 cursor-pointer">
                            <input type="checkbox" wire:model="jenisServisForm.is_part_original" class="accent-up-accent w-4 h-4" /> Part original
                        </label>
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showJenisServisModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="simpanJenisServis" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer min-h-[44px]">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: KATEGORI PRODUK -->
    @if($showKategoriModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-base font-bold text-white">{{ $kategoriForm['id'] ? 'Edit Kategori Produk' : 'Kategori Produk Baru' }}</h3>
                    <button wire:click="$set('showKategoriModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Kategori *</label>
                        <input type="text" wire:model="kategoriForm.nama" placeholder="cth: Layar & LCD, Baterai..." class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                        @error('kategoriForm.nama') <span class="text-up-red text-[10px] mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kategori Induk (Parent)</label>
                        <select wire:model="kategoriForm.parent_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            <option value="" class="bg-ink-900">— Jadikan Kategori Utama (Tanpa Induk) —</option>
                            @foreach($kategoriTree as $p)
                                @if(!$kategoriForm['id'] || $p->id != $kategoriForm['id'])
                                    <option value="{{ $p->id }}" class="bg-ink-900 font-bold text-white">{{ $p->nama }}</option>
                                @endif
                            @endforeach
                        </select>
                        <p class="text-[10px] text-ink-500 mt-1">Pilih kategori induk jika ingin membuat sub-kategori berjenjang.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Icon / Emoji</label>
                            <input type="text" wire:model="kategoriForm.icon" placeholder="📱 / 🔋 / 🔌" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Urutan Tampil</label>
                            <input type="number" wire:model="kategoriForm.urutan" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs tabular-nums" />
                        </div>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-xs text-ink-300 cursor-pointer pt-1">
                            <input type="checkbox" wire:model="kategoriForm.is_active" class="accent-up-mint w-4 h-4" /> Kategori Aktif
                        </label>
                    </div>
                </div>
                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showKategoriModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer">Batal</button>
                    <button wire:click="simpanKategori" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer">Simpan Kategori</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: BRAND PRODUK -->
    @if($showBrandModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-base font-bold text-white">{{ $brandForm['id'] ? 'Edit Brand' : 'Brand Baru' }}</h3>
                    <button wire:click="$set('showBrandModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Brand *</label>
                        <input type="text" wire:model="brandForm.nama" placeholder="cth: Apple, Samsung, Xiaomi..." class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                        @error('brandForm.nama') <span class="text-up-red text-[10px] mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Keterangan (Opsional)</label>
                        <textarea wire:model="brandForm.keterangan" rows="2" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs"></textarea>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-xs text-ink-300 cursor-pointer pt-1">
                            <input type="checkbox" wire:model="brandForm.is_active" class="accent-up-mint w-4 h-4" /> Brand Aktif
                        </label>
                    </div>
                </div>
                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showBrandModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer">Batal</button>
                    <button wire:click="simpanBrand" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer">Simpan Brand</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: KUALITAS PRODUK -->
    @if($showKualitasModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-base font-bold text-white">{{ $kualitasForm['id'] ? 'Edit Tingkat Kualitas' : 'Tingkat Kualitas Baru' }}</h3>
                    <button wire:click="$set('showKualitasModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Tingkat Kualitas *</label>
                        <input type="text" wire:model="kualitasForm.nama" placeholder="cth: Original Pabrik, Grade A+, Disassembled..." class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                        @error('kualitasForm.nama') <span class="text-up-red text-[10px] mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Keterangan / Standar Mutu</label>
                        <textarea wire:model="kualitasForm.keterangan" rows="2" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs"></textarea>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-xs text-ink-300 cursor-pointer pt-1">
                            <input type="checkbox" wire:model="kualitasForm.is_active" class="accent-up-mint w-4 h-4" /> Kualitas Aktif
                        </label>
                    </div>
                </div>
                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showKualitasModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer">Batal</button>
                    <button wire:click="simpanKualitas" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer">Simpan Kualitas</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: SATUAN UNIT -->
    @if($showSatuanModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-base font-bold text-white">{{ $satuanForm['id'] ? 'Edit Satuan Unit' : 'Satuan Unit Baru' }}</h3>
                    <button wire:click="$set('showSatuanModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kode Satuan *</label>
                            <input type="text" wire:model="satuanForm.kode" placeholder="pcs, roll, set, box" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-mono font-bold" />
                            @error('satuanForm.kode') <span class="text-up-red text-[10px] mt-1">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Satuan *</label>
                            <input type="text" wire:model="satuanForm.nama" placeholder="Pieces, Roll, Set, Box" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                            @error('satuanForm.nama') <span class="text-up-red text-[10px] mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-xs text-ink-300 cursor-pointer pt-1">
                            <input type="checkbox" wire:model="satuanForm.is_active" class="accent-up-mint w-4 h-4" /> Satuan Aktif
                        </label>
                    </div>
                </div>
                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showSatuanModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer">Batal</button>
                    <button wire:click="simpanSatuan" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer">Simpan Satuan</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: KONDISI PRODUK -->
    @if($showKondisiModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-base font-bold text-white">{{ $kondisiForm['is_edit'] ? 'Edit Kondisi Produk' : 'Kondisi Produk Baru' }}</h3>
                    <button wire:click="$set('showKondisiModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Label Kondisi *</label>
                        <input type="text" wire:model="kondisiForm.nama" placeholder="cth: Baru, OEM, Copotan Original..." class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                        @error('kondisiForm.nama') <span class="text-up-red text-[10px] mt-1">{{ $message }}</span> @enderror
                    </div>
                    @if(!$kondisiForm['is_edit'])
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kode Unik (Opsional, auto slug)</label>
                            <input type="text" wire:model="kondisiForm.kode" placeholder="cth: bekas_grade_a" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-mono" />
                        </div>
                    @else
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kode Unik</label>
                            <input type="text" value="{{ $kondisiForm['kode'] }}" disabled class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-mono opacity-50 cursor-not-allowed" />
                        </div>
                    @endif
                    <div>
                        <label class="flex items-center gap-2 text-xs text-ink-300 cursor-pointer pt-1">
                            <input type="checkbox" wire:model="kondisiForm.is_active" class="accent-up-mint w-4 h-4" /> Kondisi Aktif
                        </label>
                    </div>
                </div>
                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showKondisiModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer">Batal</button>
                    <button wire:click="simpanKondisi" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer">Simpan Kondisi</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: TIPE HP -->
    @if($showTipeHpModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-base font-bold text-white">{{ $tipeHpForm['id'] ? 'Edit Tipe HP' : 'Tipe HP Baru' }}</h3>
                    <button wire:click="$set('showTipeHpModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Merek *</label>
                            <input type="text" wire:model="tipeHpForm.merk" list="settings-brand-datalist" placeholder="Apple, Samsung, Oppo..." class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                            @error('tipeHpForm.merk') <span class="text-up-red text-[10px] mt-1">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Model *</label>
                            <input type="text" wire:model="tipeHpForm.model" placeholder="iPhone 13, A54 5G..." class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                            @error('tipeHpForm.model') <span class="text-up-red text-[10px] mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Lengkap (Opsional)</label>
                        <input type="text" wire:model="tipeHpForm.nama" placeholder="Otomatis gabungan Merek + Model jika kosong" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-xs text-ink-300 cursor-pointer pt-1">
                            <input type="checkbox" wire:model="tipeHpForm.is_active" class="accent-up-mint w-4 h-4" /> Tipe HP Aktif
                        </label>
                    </div>
                </div>
                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showTipeHpModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer">Batal</button>
                    <button wire:click="simpanTipeHp" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer">Simpan Tipe HP</button>
                </div>
            </div>
        </div>

        <datalist id="settings-brand-datalist">
            @foreach($brandList as $b)
                <option value="{{ $b->nama }}"></option>
            @endforeach
        </datalist>
    @endif
</div>