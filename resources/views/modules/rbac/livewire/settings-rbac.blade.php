<div class="space-y-6">
    <!-- Tabs -->
    <div class="flex items-center gap-2 border-b border-white/5 pb-4 flex-wrap">
        @foreach(['users' => '👥 Manajemen User', 'cabang' => '🏬 Cabang & Gudang', 'master' => '📋 Master Data'] as $kode => $label)
            <button wire:click="$set('activeTab', '{{ $kode }}')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $activeTab === $kode ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/5 text-ink-300 hover:bg-white/10' }}">{{ $label }}</button>
        @endforeach

        <div class="ml-auto">
            @if($activeTab === 'users')
                <button wire:click="openUserModal()" class="px-4 py-2 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs cursor-pointer">+ User Baru</button>
            @elseif($activeTab === 'cabang')
                <button wire:click="openCabangModal()" class="px-4 py-2 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs cursor-pointer mr-2">+ Cabang</button>
                <button wire:click="openGudangModal()" class="px-4 py-2 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer">+ Gudang</button>
            @endif
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

        <!-- Role matrix viewer -->
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
        </div>
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
                            <button wire:click="openCabangModal({{ $c->id }})" class="text-[11px] text-up-primary hover:text-indigo-400 font-semibold cursor-pointer">Edit</button>
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
                            <button wire:click="openGudangModal({{ $g->id }})" class="text-[11px] text-up-primary hover:text-indigo-400 font-semibold cursor-pointer">Edit</button>
                        </div>
                    @endforeach
                </div>
            </x-prism.glass-card>
        </div>
    @endif

    <!-- TAB: MASTER DATA -->
    @if($activeTab === 'master')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-prism.glass-card title="Jenis Servis & Garansi" subtitle="Kategori & durasi garansi editable (T-16)">
                <div class="space-y-2">
                    @foreach($jenisServis as $j)
                        <div class="flex justify-between items-center py-2 border-b border-white/5 last:border-0">
                            <div>
                                <p class="text-xs font-bold text-white">{{ $j->nama }}</p>
                                <p class="text-[10px] text-ink-400 font-mono">
                                    {{ $j->kode }} · {{ $j->kategori }} · est. {{ $j->estimasi_durasi }} mnt
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
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Akses Cabang</label>
                        <div class="space-y-1.5">
                            @foreach($cabangs as $c)
                                <label class="flex items-center gap-2 text-xs text-ink-200 cursor-pointer">
                                    <input type="checkbox" wire:model="userForm.cabang_ids" value="{{ $c->id }}" class="accent-up-mint w-4 h-4" />
                                    {{ $c->nama }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-xs text-ink-300 cursor-pointer">
                        <input type="checkbox" wire:model="userForm.is_active" class="accent-up-mint w-4 h-4" />
                        Akun Aktif
                    </label>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showUserModal', false)" class="flex-1 py-2.5 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer">Batal</button>
                    <button wire:click="saveUser" class="flex-1 py-2.5 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer">Simpan User</button>
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
                    <button wire:click="$set('showCabangModal', false)" class="flex-1 py-2.5 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer">Batal</button>
                    <button wire:click="saveCabang" class="flex-1 py-2.5 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer">Simpan</button>
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
                    <button wire:click="$set('showGudangModal', false)" class="flex-1 py-2.5 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer">Batal</button>
                    <button wire:click="saveGudang" class="flex-1 py-2.5 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer">Simpan</button>
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
                    <button wire:click="$set('showJenisServisModal', false)" class="flex-1 py-2.5 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer">Batal</button>
                    <button wire:click="simpanJenisServis" class="flex-1 py-2.5 rounded-xl bg-up-primary text-white font-bold text-xs cursor-pointer">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>