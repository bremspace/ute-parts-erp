{{-- F2-1 Lead Pipeline — kanban Ute Prism. Bindings: LeadKanban.php (render pass-through). --}}
<div class="space-y-6"
     x-data="{
         dragId: null,
         dragStage: null,
         overCol: null,
         pendingStage: null,
         showConfirm: false,
         confirmId: null,
         confirmMsg: '',
         stageLabels: @js(collect($stages)->map(fn ($s) => $s['label'])->all()),
         moveLead(id, stage) {
             const from = this.dragStage;
             this.dragId = null;
             this.dragStage = null;
             this.overCol = null;
             if (from === stage) return;
             this.pendingStage = stage;
             $wire.updateStage(id, stage);
         },
     }"
     @confirm-delete.window="
         confirmId = $event.detail.leadId;
         confirmMsg = $event.detail.message || 'Yakin hapus lead ini? Tindakan ini tidak bisa dibatalkan.';
         showConfirm = true;
     "
     @keydown.escape.window="showConfirm = false">

    <!-- ===== HEADER + FILTERS ===== -->
    <div class="flex flex-col gap-4 border-b border-white/5 pb-4">
        <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between">
            <div class="flex-1">
                <x-prism.barcode-scan-input placeholder="Cari nama, telepon, atau email lead..." model="search" />
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <select wire:model.live="filterSumber" class="px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                    <option value="" class="bg-ink-900">Semua Sumber</option>
                    @foreach($sumberOptions as $key => $label)
                        <option value="{{ $key }}" class="bg-ink-900">{{ $label }}</option>
                    @endforeach
                </select>
                <select wire:model.live="filterAssignedTo" class="px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                    <option value="" class="bg-ink-900">Semua Penanggung Jawab</option>
                    @foreach($salesUsers as $u)
                        <option value="{{ $u->id }}" class="bg-ink-900">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <x-prism.prism-button variant="mint" size="sm" wire:click="openCreateModal">
                + Tambah Lead
            </x-prism.prism-button>
            @if($search !== '' || $filterSumber || $filterAssignedTo)
                <x-prism.prism-button variant="ghost" size="sm"
                    @click="$wire.set('search', ''); $wire.set('filterSumber', null); $wire.set('filterAssignedTo', null)">
                    ✕ Reset Filter
                </x-prism.prism-button>
            @endif
        </div>
    </div>

    <!-- ===== SUMMARY + MINI FUNNEL ===== -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 items-start">
        <div class="xl:col-span-2 grid grid-cols-2 md:grid-cols-3 2xl:grid-cols-5 gap-3">
            <x-prism.glass-card padding="p-4">
                <p class="text-[10px] uppercase tracking-wider text-ink-400 font-bold">Total Leads</p>
                <p class="mt-1 text-2xl font-bold text-white tabular-nums">{{ $summary['total'] }}</p>
            </x-prism.glass-card>
            <x-prism.glass-card padding="p-4">
                <p class="text-[10px] uppercase tracking-wider text-ink-400 font-bold">Total Nilai</p>
                <p class="mt-1 text-xl font-bold text-white tabular-nums">Rp {{ number_format((float) $summary['total_nilai'], 0, ',', '.') }}</p>
            </x-prism.glass-card>
            <x-prism.glass-card padding="p-4">
                <p class="text-[10px] uppercase tracking-wider text-ink-400 font-bold">Nilai Menang</p>
                <p class="mt-1 text-xl font-bold text-up-mint tabular-nums">Rp {{ number_format((float) $summary['won_nilai'], 0, ',', '.') }}</p>
            </x-prism.glass-card>
            <x-prism.glass-card padding="p-4">
                <p class="text-[10px] uppercase tracking-wider text-ink-400 font-bold">Konversi</p>
                <p class="mt-1 text-2xl font-bold text-up-accent tabular-nums">{{ str_replace('.', ',', (string) $summary['conversion_rate']) }}%</p>
            </x-prism.glass-card>
            <x-prism.glass-card padding="p-4">
                <p class="text-[10px] uppercase tracking-wider text-ink-400 font-bold">Kalah</p>
                <p class="mt-1 text-2xl font-bold text-up-red tabular-nums">{{ $summary['lost_count'] }}</p>
            </x-prism.glass-card>
        </div>

        {{-- Mini funnel: diturunkan dari kanbanData (component tidak pass prop `funnel`) --}}
        <x-prism.glass-card title="Mini Funnel" subtitle="Distribusi tahap pipeline" :circuit="true" padding="p-5">
            @php($maxCount = max(1, (int) collect($kanbanData)->map(fn ($c) => $c->count())->max()))
            <div class="space-y-2">
                @foreach($stages as $stageKey => $stageMeta)
                    @php($count = (int) ($kanbanData[$stageKey] ?? collect())->count())
                    @php($barClass = match ($stageMeta['color']) {
                        'primary' => 'bg-up-primary',
                        'mint' => 'bg-up-mint',
                        'amber' => 'bg-up-amber',
                        'red' => 'bg-up-red',
                        default => 'bg-ink-400',
                    })
                    <div class="flex items-center gap-2 text-[11px]">
                        <span class="w-24 truncate text-ink-300">{{ $stageMeta['label'] }}</span>
                        <div class="flex-1 h-3 rounded bg-white/5 overflow-hidden border border-white/5">
                            <div class="h-full {{ $barClass }} transition-all duration-500"
                                 style="width: {{ max(2, (int) round(($count / $maxCount) * 100)) }}%"></div>
                        </div>
                        <span class="tabular-nums text-white font-semibold w-6 text-right">{{ $count }}</span>
                    </div>
                @endforeach
            </div>
        </x-prism.glass-card>
    </div>

    <!-- ===== KANBAN BOARD ===== -->
    <div class="overflow-x-auto pb-2" wire:loading.class="opacity-50 pointer-events-none" wire:target="search, filterSumber, filterAssignedTo, updateLead, createLead, deleteLead, convertLead">
        <div class="flex gap-4 min-w-max items-start">
            @foreach($stages as $stageKey => $stageMeta)
                @php($colLeads = $kanbanData[$stageKey] ?? collect())
                @php($dotClass = match ($stageMeta['color']) {
                    'primary' => 'bg-up-primary',
                    'mint' => 'bg-up-mint',
                    'amber' => 'bg-up-amber',
                    'red' => 'bg-up-red',
                    default => 'bg-ink-400',
                })
                <div class="w-[280px] shrink-0 flex flex-col rounded-2xl glass-panel overflow-hidden max-h-[70vh]"
                     wire:key="col-{{ $stageKey }}"
                     @dragover.prevent="overCol = '{{ $stageKey }}'"
                     @dragleave="overCol = null"
                     @drop.prevent="if (dragId) { moveLead(dragId, '{{ $stageKey }}'); }">

                    <!-- Column Header -->
                    <div class="px-4 py-3 flex items-center justify-between border-b border-white/5 transition-colors"
                         :class="overCol === '{{ $stageKey }}' ? 'bg-up-primary/10' : 'bg-white/[0.02]'">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $dotClass }}"></span>
                            <h3 class="text-xs font-bold text-white uppercase tracking-wider truncate">{{ $stageMeta['label'] }}</h3>
                        </div>
                        <div class="flex items-center gap-1.5 flex-shrink-0">
                            <span class="text-[10px] text-ink-400 tabular-nums font-mono bg-white/5 px-1.5 py-0.5 rounded"
                                  title="Total nilai: Rp {{ number_format((float) $colLeads->sum('nilai_estimasi'), 0, ',', '.') }}">
                                Rp {{ number_format((float) $colLeads->sum('nilai_estimasi'), 0, ',', '.') }}
                            </span>
                            <span class="text-[10px] font-mono text-ink-300 tabular-nums bg-white/5 px-1.5 py-0.5 rounded">{{ $colLeads->count() }}</span>
                        </div>
                    </div>

                    <!-- Column Cards -->
                    <div class="flex-1 overflow-y-auto p-2.5 space-y-2.5">
                        @forelse($colLeads as $lead)
                            @php($ownerName = $lead->assignedTo?->name)
                            @php($initials = $ownerName ? collect(preg_split('/\s+/', trim($ownerName)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') : '')
                            @php($sumberClass = match ($lead->sumber) {
                                'referral' => 'text-up-accent bg-up-accent/10 border-up-accent/30',
                                'marketplace' => 'text-up-primary bg-up-primary/10 border-up-primary/30',
                                'social_media' => 'text-up-mint bg-up-mint/10 border-up-mint/30',
                                'website' => 'text-up-amber bg-up-amber/10 border-up-amber/30',
                                default => 'text-ink-300 bg-white/5 border-white/10',
                            })
                            <div class="group glass-panel glass-panel-hover rounded-xl p-3 cursor-grab select-none active:cursor-grabbing"
                                 draggable="true"
                                 @dragstart="dragId = {{ $lead->id }}; dragStage = '{{ $lead->stage }}'; $event.dataTransfer.setData('text/plain', '{{ $lead->id }}'); $event.dataTransfer.effectAllowed = 'move'"
                                 @dragend="dragId = null; dragStage = null; overCol = null"
                                 wire:key="lead-{{ $lead->id }}">

                                <!-- Nama + Sumber -->
                                <div class="flex items-start justify-between gap-2 mb-1.5">
                                    <h4 class="font-bold text-white text-xs truncate" title="{{ $lead->nama }}">{{ $lead->nama }}</h4>
                                    <span class="text-[9px] font-bold uppercase whitespace-nowrap border px-1.5 py-0.5 rounded-full flex-shrink-0 {{ $sumberClass }}">
                                        {{ $lead->sumber_label }}
                                    </span>
                                </div>

                                <!-- Kontak -->
                                <p class="text-[10px] font-mono text-ink-400 truncate">{{ $lead->telepon ?: '-' }}</p>
                                @if($lead->email)
                                    <p class="text-[10px] font-mono text-ink-500 truncate">{{ $lead->email }}</p>
                                @endif

                                <!-- Nilai estimasi + PIC -->
                                <div class="flex items-center justify-between gap-2 mt-2.5">
                                    <span class="text-sm font-bold tabular-nums {{ $lead->stage === 'won' ? 'text-up-mint' : ($lead->stage === 'lost' ? 'text-up-red' : 'text-up-amber') }}">
                                        Rp {{ number_format((float) $lead->nilai_estimasi, 0, ',', '.') }}
                                    </span>
                                    @if($ownerName)
                                        <span class="flex items-center gap-1.5 min-w-0" title="PIC: {{ $ownerName }}">
                                            <span class="w-5 h-5 rounded-full bg-up-primary/20 border border-up-primary/40 text-up-primary text-[9px] font-bold flex items-center justify-center flex-shrink-0">{{ $initials }}</span>
                                            <span class="text-[10px] text-ink-300 truncate max-w-[90px]">{{ $ownerName }}</span>
                                        </span>
                                    @else
                                        <span class="text-[10px] text-ink-500">Belum ada PIC</span>
                                    @endif
                                </div>

                                <!-- Badge: alasan kalah / relasi pelanggan -->
                                @if($lead->stage === 'lost' && $lead->lost_reason)
                                    <p class="mt-2 text-[9px] font-semibold text-up-red bg-up-red/10 border border-up-red/30 px-1.5 py-0.5 rounded-full inline-block">
                                        Alasan: {{ $lostReasonOptions[$lead->lost_reason] ?? $lead->lost_reason }}
                                    </p>
                                @endif
                                @if($lead->pelanggan_id)
                                    <p class="mt-2 text-[9px] font-semibold text-up-mint bg-up-mint/10 border border-up-mint/30 px-1.5 py-0.5 rounded-full inline-block">
                                        ✓ Terhubung Pelanggan #{{ $lead->pelanggan_id }}
                                    </p>
                                @endif

                                <!-- Konversi (won, belum punya pelanggan) -->
                                @if($lead->stage === 'won' && ! $lead->pelanggan_id)
                                    <button type="button"
                                            wire:click="confirmConvert({{ $lead->id }})"
                                            class="w-full mt-2.5 py-1.5 rounded-lg bg-up-mint/10 hover:bg-up-mint/20 border border-up-mint/30 text-up-mint font-bold text-[10px] transition-all cursor-pointer">
                                        ⇄ Konversi ke Pelanggan
                                    </button>
                                @endif

                                <!-- Aksi -->
                                <div class="flex items-center justify-end gap-1.5 pt-2 mt-2 border-t border-white/5">
                                    <button type="button"
                                            wire:click="openEditModal({{ $lead->id }})"
                                            @click="pendingStage = null"
                                            class="px-2 py-1 rounded-md bg-white/5 hover:bg-up-primary/20 hover:text-white text-ink-300 font-semibold text-[10px] transition-all cursor-pointer"
                                            title="Edit lead">
                                        ✎ Edit
                                    </button>
                                    <button type="button"
                                            wire:click="confirmDelete({{ $lead->id }})"
                                            class="px-2 py-1 rounded-md bg-white/5 hover:bg-up-red/20 hover:text-up-red text-ink-400 font-semibold text-[10px] transition-all cursor-pointer"
                                            title="Hapus lead">
                                        🗑 Hapus
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-8 px-2 text-[10px] text-ink-500">
                                <svg class="w-6 h-6 mx-auto mb-1.5 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                <p>Kosong — tarik kartu<br>ke kolom ini</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- ===== MODAL: TAMBAH LEAD ===== -->
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-lg glass-panel p-6 rounded-3xl border border-white/10 shadow-2xl relative max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Tambah Lead Baru</h3>
                    <button wire:click="$set('showCreateModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama <span class="text-up-red">*</span></label>
                            <input type="text" wire:model="createForm.nama" placeholder="Nama lead / calon pelanggan" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                            @error('createForm.nama')<p class="text-[10px] text-up-red mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Telepon</label>
                            <input type="text" wire:model="createForm.telepon" placeholder="08xx-xxxx-xxxx" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                            @error('createForm.telepon')<p class="text-[10px] text-up-red mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Email</label>
                            <input type="email" wire:model="createForm.email" placeholder="lead@email.com" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                            @error('createForm.email')<p class="text-[10px] text-up-red mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Sumber</label>
                            <select wire:model="createForm.sumber" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                @foreach($sumberOptions as $key => $label)
                                    <option value="{{ $key }}" class="bg-ink-900">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nilai Estimasi (Rp)</label>
                            <input type="number" inputmode="numeric" wire:model="createForm.nilai_estimasi" min="0" step="1000" placeholder="0" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-bold tabular-nums" />
                            @error('createForm.nilai_estimasi')<p class="text-[10px] text-up-red mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Penanggung Jawab</label>
                            <select wire:model="createForm.assigned_to" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="" class="bg-ink-900">— Belum ditugaskan —</option>
                                @foreach($salesUsers as $u)
                                    <option value="{{ $u->id }}" class="bg-ink-900">{{ $u->name }}</option>
                                @endforeach
                            </select>
                            @error('createForm.assigned_to')<p class="text-[10px] text-up-red mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Tahap Awal</label>
                        <select wire:model="createForm.stage" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            @foreach($stages as $stageKey => $stageMeta)
                                <option value="{{ $stageKey }}" class="bg-ink-900">{{ $stageMeta['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Catatan</label>
                        <textarea wire:model="createForm.catatan" rows="2" placeholder="Kebutuhan, minat, atau kontak awal..." class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium"></textarea>
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showCreateModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="createLead" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs shadow-lg shadow-up-primary/25 cursor-pointer min-h-[44px]">Simpan Lead</button>
                </div>
            </div>
        </div>
    @endif

    <!-- ===== MODAL: EDIT LEAD ===== -->
    @if($editingLeadId && $editForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-lg glass-panel p-6 rounded-3xl border border-white/10 shadow-2xl relative max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <div>
                        <h3 class="text-lg font-bold text-white">Edit Lead</h3>
                        <p class="text-xs text-ink-400 mt-0.5">{{ $editForm['nama'] ?? '-' }} · #{{ $editingLeadId }}</p>
                    </div>
                    <button @click="pendingStage = null; $wire.set('editingLeadId', null)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                {{-- Banner konfirmasi drag-drop: stage sudah di-set ke kolom tujuan, persist saat Simpan --}}
                <div x-show="pendingStage !== null" x-cloak x-transition.opacity
                     class="mb-4 p-3 rounded-xl bg-up-amber/10 border border-up-amber/30 text-[11px] text-up-amber leading-relaxed">
                    Kartu dipindahkan ke tahap <strong x-text="stageLabels[pendingStage] ?? pendingStage"></strong>.
                    Periksa data di bawah, lalu klik <strong>Simpan Perubahan</strong> untuk konfirmasi.
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama <span class="text-up-red">*</span></label>
                            <input type="text" wire:model="editForm.nama" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                            @error('editForm.nama')<p class="text-[10px] text-up-red mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Telepon</label>
                            <input type="text" wire:model="editForm.telepon" placeholder="08xx-xxxx-xxxx" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                            @error('editForm.telepon')<p class="text-[10px] text-up-red mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Email</label>
                            <input type="email" wire:model="editForm.email" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                            @error('editForm.email')<p class="text-[10px] text-up-red mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Sumber</label>
                            <select wire:model="editForm.sumber" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                @foreach($sumberOptions as $key => $label)
                                    <option value="{{ $key }}" class="bg-ink-900">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Tahap (Stage)</label>
                            <select wire:model.live="editForm.stage" @change="pendingStage = null" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                @foreach($stages as $stageKey => $stageMeta)
                                    <option value="{{ $stageKey }}" class="bg-ink-900">{{ $stageMeta['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nilai Estimasi (Rp)</label>
                            <input type="number" inputmode="numeric" wire:model="editForm.nilai_estimasi" min="0" step="1000" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-bold tabular-nums" />
                            @error('editForm.nilai_estimasi')<p class="text-[10px] text-up-red mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    @if(($editForm['stage'] ?? '') === 'lost')
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Alasan Kalah</label>
                            <select wire:model="editForm.lost_reason" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="" class="bg-ink-900">— Pilih alasan —</option>
                                @foreach($lostReasonOptions as $key => $label)
                                    <option value="{{ $key }}" class="bg-ink-900">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Penanggung Jawab</label>
                            <select wire:model="editForm.assigned_to" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="" class="bg-ink-900">— Belum ditugaskan —</option>
                                @foreach($salesUsers as $u)
                                    <option value="{{ $u->id }}" class="bg-ink-900">{{ $u->name }}</option>
                                @endforeach
                            </select>
                            @error('editForm.assigned_to')<p class="text-[10px] text-up-red mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Catatan</label>
                            <textarea wire:model="editForm.catatan" rows="2" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium"></textarea>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button @click="pendingStage = null; $wire.set('editingLeadId', null)"
                            class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="updateLead" @click="pendingStage = null"
                            class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs shadow-lg shadow-up-primary/25 cursor-pointer min-h-[44px]">Simpan Perubahan</button>
                </div>
            </div>
        </div>
    @endif

    <!-- ===== MODAL: KONVERSI KE PELANGGAN (won) ===== -->
    @if($showConvertModal)
        @php($convertLead = collect($kanbanData)->flatten()->firstWhere('id', $convertLeadId))
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl border border-white/10 shadow-2xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Konversi ke Pelanggan</h3>
                    <button wire:click="$set('showConvertModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="space-y-3">
                    <p class="text-xs text-ink-200 leading-relaxed">
                        Lead <strong class="text-white">{{ $convertLead->nama ?? '#' . $convertLeadId }}</strong>
                        @if($convertLead)
                            <span class="tabular-nums">dengan estimasi <strong class="text-up-mint">Rp {{ number_format((float) $convertLead->nilai_estimasi, 0, ',', '.') }}</strong></span>
                        @endif
                        akan dibuatkan data <strong class="text-up-mint">Pelanggan</strong> baru. Setelah dikonversi, lead ini tidak bisa dikonversi ulang.
                    </p>
                    <div class="p-3 rounded-xl bg-up-mint/10 border border-up-mint/30 text-[11px] text-up-mint">
                        Pelanggan baru langsung bisa dipakai di POS, Servis, dan loyalti.
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showConvertModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="convertLead" class="flex-1 py-3 rounded-xl bg-up-mint text-ink-950 font-bold text-xs shadow-lg shadow-up-mint/20 cursor-pointer min-h-[44px]">Ya, Konversi</button>
                </div>
            </div>
        </div>
    @endif

    <!-- ===== CONFIRM HAPUS LEAD (Alpine — event `confirm-delete` dari component) ===== -->
    <div x-show="showConfirm" x-cloak x-transition.opacity
         class="fixed inset-0 z-[60] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
        <div x-transition class="w-full max-w-sm glass-panel p-6 rounded-3xl border border-white/10 shadow-2xl relative">
            <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                <h3 class="text-lg font-bold text-white">Hapus Lead</h3>
                <button @click="showConfirm = false" class="text-ink-400 hover:text-white">✕</button>
            </div>
            <p class="text-xs text-ink-200 leading-relaxed" x-text="confirmMsg"></p>
            <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                <button @click="showConfirm = false" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                <button @click="$wire.deleteLead(confirmId); showConfirm = false"
                        class="flex-1 py-3 rounded-xl bg-up-red text-white font-bold text-xs shadow-lg shadow-up-red/25 cursor-pointer min-h-[44px]">Ya, Hapus</button>
            </div>
        </div>
    </div>
</div>
