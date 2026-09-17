<div class="flex flex-col h-[calc(100vh-8.5rem)] overflow-hidden"
     x-data="{ dragId: null, overCol: null, photoIndex: 0 }">

    <!-- ===== TOOLBAR ===== -->
    <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between mb-4 flex-shrink-0">
        <div class="flex-1">
            <x-prism.barcode-scan-input placeholder="Cari tiket: no. tiket, jenis HP, nama pelanggan..." model="search" />
        </div>

        <div class="flex items-center gap-2">
            <select wire:model.live="filterStatus" class="px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                <option value="" class="bg-ink-900">Semua Status</option>
                @foreach($stateMachineColumns as $kode => $col)
                    <option value="{{ $kode }}" class="bg-ink-900">{{ $col['label'] }}</option>
                @endforeach
            </select>

            <button
                type="button"
                wire:click="openTerimaModal"
                class="px-4 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs shadow-md shadow-up-primary/25 flex items-center gap-2 cursor-pointer active:scale-95 transition-all"
            >
                <span class="text-base leading-none">+</span> Terima Unit Servis
            </button>
        </div>
    </div>

    <!-- ===== KANBAN BOARD ===== -->
    <div class="flex-1 overflow-x-auto overflow-y-hidden pb-2">
        <div class="flex gap-4 h-full min-w-max">
            @foreach($stateMachineColumns as $status => $col)
                <div class="w-[280px] flex-shrink-0 flex flex-col rounded-2xl glass-panel overflow-hidden"
                     @dragover.prevent="overCol = '{{ $status }}'"
                     @dragleave="overCol = null"
                     @drop.prevent="if (dragId) { $wire.dropTicket(dragId, '{{ $status }}'); dragId = null; overCol = null; }">
                    <!-- Column Header -->
                    <div class="px-4 py-3 flex items-center justify-between border-b border-white/5"
                         :class="overCol === '{{ $status }}' ? 'bg-up-primary/10' : 'bg-white/[0.02]'">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-{{ $col['color'] }}"></span>
                            <h3 class="text-xs font-bold text-white uppercase tracking-wider">{{ $col['label'] }}</h3>
                            <span class="text-[10px] font-mono text-ink-400 tabular-nums bg-white/5 px-1.5 py-0.5 rounded">
                                {{ ($groupedTikets[$status] ?? collect())->count() }}
                            </span>
                        </div>
                    </div>

                    <!-- Column Cards -->
                    <div class="flex-1 overflow-y-auto p-2.5 space-y-2.5">
                        @forelse(($groupedTikets[$status] ?? collect()) as $tiket)
                            @php
                                $foto = $tiket->foto_unit[0] ?? null;
                                $nextStatuses = \App\Modules\Servis\Services\ServisStateMachine::nextValidStatuses($tiket->status);
                            @endphp
                            <div
                                class="group glass-panel glass-panel-hover rounded-xl p-3 cursor-grab select-none"
                                draggable="true"
                                @dragstart="dragId = {{ $tiket->id }}"
                                @dragend="dragId = null; overCol = null"
                                wire:key="tiket-{{ $tiket->id }}"
                            >
                                <!-- Card Header: Foto + No Tiket -->
                                <div class="flex items-start gap-2.5 mb-2">
                                    <div class="w-11 h-11 rounded-lg overflow-hidden flex-shrink-0 bg-white/5 border border-white/10">
                                        @if($foto)
                                            <img src="{{ $foto }}" alt="Unit {{ $tiket->jenis_hp }}" class="w-full h-full object-cover">
                                        @else
                                            <svg class="w-5 h-5 m-auto text-ink-500 mt-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                            </svg>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="font-mono text-[10px] text-up-primary font-bold truncate">{{ $tiket->no_tiket }}</span>
                                            @if($tiket->garansi && $tiket->garansi->active)
                                                <span class="text-[9px] font-bold text-up-mint bg-up-mint/10 border border-up-mint/30 px-1.5 py-0.5 rounded-full whitespace-nowrap">Dalam Garansi</span>
                                            @endif
                                        </div>
                                        <h4 class="font-bold text-white text-xs truncate">{{ $tiket->jenis_hp }}</h4>
                                        <p class="text-[10px] text-ink-400 truncate">{{ $tiket->nama_pelanggan ?? $tiket->pelanggan?->nama ?? 'Guest' }}</p>
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="openDetail({{ $tiket->id }})"
                                        class="text-ink-500 hover:text-up-primary text-sm opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0"
                                        title="Lihat Detail"
                                    >
                                        ↗
                                    </button>
                                </div>

                                <!-- Keluhan -->
                                <p class="text-[11px] text-ink-300 line-clamp-2 mb-2.5 leading-snug">{{ $tiket->keluhan }}</p>

                                <!-- Info Row -->
                                <div class="flex items-center justify-between text-[10px] text-ink-400 mb-2.5">
                                    @if($tiket->estimasi_biaya)
                                        <span class="tabular-nums font-semibold text-up-mint">
                                            Rp {{ number_format($tiket->estimasi_biaya, 0, ',', '.') }}
                                        </span>
                                    @else
                                        <span class="text-ink-500">Belum ada estimasi</span>
                                    @endif
                                    <span class="flex items-center gap-1.5">
                                        @if($tiket->spareparts_count > 0)
                                            <span class="text-up-amber font-mono">&#128295; {{ $tiket->spareparts_count }}</span>
                                        @endif
                                        @if($tiket->teknisi)
                                            <span title="Teknisi: {{ $tiket->teknisi->name }}">{{ $tiket->teknisi->name }}</span>
                                        @endif
                                    </span>
                                </div>

                                <!-- Next Status Actions -->
                                @if(count($nextStatuses) > 0)
                                    <div class="flex flex-wrap gap-1.5 pt-2 border-t border-white/5">
                                        @foreach($nextStatuses as $ns)
                                            @if($ns === 'menunggu_approval')
                                                <button
                                                    type="button"
                                                    wire:click="openEstimasiModal({{ $tiket->id }})"
                                                    class="px-2.5 py-1 rounded-lg bg-up-primary hover:bg-up-primary-dark text-white font-bold text-[10px] transition-all cursor-pointer"
                                                >+ Estimasi</button>
                                            @elseif($ns === 'disetujui' || $ns === 'ditolak')
                                                <button
                                                    type="button"
                                                    wire:click="openApproveModal({{ $tiket->id }})"
                                                    class="px-2.5 py-1 rounded-lg {{ $ns === 'disetujui' ? 'bg-up-mint hover:opacity-90 text-ink-950' : 'bg-up-red hover:opacity-90 text-white' }} font-bold text-[10px] transition-all cursor-pointer"
                                                >{{ $ns === 'disetujui' ? 'Setujui' : 'Tolak' }}</button>
                                            @else
                                                @php
                                                    $label = match ($ns) {
                                                        'diterima'   => 'Terima',
                                                        'diagnosa'   => 'Diagnosa',
                                                        'dikerjakan' => 'Kerjakan',
                                                        'qc'         => 'Ke QC',
                                                        'selesai'    => 'Selesai',
                                                        'diambil'    => 'Diambil',
                                                        default      => ucfirst($ns),
                                                    };
                                                @endphp
                                                <button
                                                    type="button"
                                                    wire:click="updateStatus({{ $tiket->id }}, '{{ $ns }}')"
                                                    class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-ink-200 hover:text-white font-semibold text-[10px] border border-white/10 transition-all cursor-pointer"
                                                >{{ $label }}</button>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="text-center py-8 text-[10px] text-ink-500">
                                <svg class="w-6 h-6 mx-auto mb-1.5 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                <p>Kosong — tarik kartu<br>atau terima unit baru</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- ===== PELANGGAN SAAT INI & PERMISSION GUARD ===== -->

    <!-- ===== MODAL: TERIMA UNIT ===== -->
    @if($showTerimaModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-2xl glass-panel p-6 rounded-3xl border border-white/10 shadow-2xl relative max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10 flex-shrink-0">
                    <h3 class="text-lg font-bold text-white">Terima Unit Servis</h3>
                    <button wire:click="$set('showTerimaModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="overflow-y-auto pr-1 space-y-4 flex-1">
                    <!-- Pelanggan Terdaftar [T-18: reusable picker, sama seperti POS] -->
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Pelanggan <span class="text-ink-500 font-normal">(cari / isi data guest)</span></label>
                        <x-customer-picker
                            wireModel="pelangganSearch"
                            selectAction="setPelangganServis"
                            addAction="openPelangganBaruServis"
                            :results="$pelangganCariServis"
                        />
                        @if($terimaForm['pelanggan_id'])
                            <button wire:click="setPelangganServis(null)" class="mt-1 text-[10px] text-up-red hover:underline cursor-pointer">✕ lepaskan pelanggan terpilih</button>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Pelanggan <span class="text-up-red">*</span></label>
                            <input type="text" wire:model="terimaForm.nama_pelanggan" placeholder="Nama pemilik unit" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">No. Telepon</label>
                            <input type="text" wire:model="terimaForm.telepon_pelanggan" placeholder="08xx-xxxx-xxxx" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Jenis Servis</label>
                            <select wire:model="terimaForm.jenis_servis_id" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                @foreach($jenisServisList as $j)
                                    <option value="{{ $j->id }}" class="bg-ink-900">{{ $j->nama }} ({{ $j->durasi_garansi_hari }} hari garansi)</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Jenis HP <span class="text-up-red">*</span></label>
                            <input type="text" wire:model="terimaForm.jenis_hp" placeholder="Ex: iPhone 13, Samsung A52" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Seri / IMEI</label>
                        <input type="text" wire:model="terimaForm.seri_hp" placeholder="Nomor seri / IMEI (opsional)" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>

                    <!-- [T-19] Kunci Gadget (terenkripsi) -->
                    <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kunci Gadget <span class="text-ink-500 font-normal">(opsional, terenkripsi — hanya teknisi/admin)</span></label>
                        <select wire:model="terimaForm.tipe_kunci" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            <option value="" class="bg-ink-900">— Tidak ada / default —</option>
                            <option value="pola" class="bg-ink-900">Pola</option>
                            <option value="pin" class="bg-ink-900">PIN (4-6 digit)</option>
                            <option value="password" class="bg-ink-900">Password</option>
                            <option value="tidak_ada" class="bg-ink-900">Tidak ada</option>
                        </select>

                        @if(($terimaForm['tipe_kunci'] ?? '') === 'pola')
                            <div class="mt-2">
                                <p class="text-[10px] text-ink-400 mb-1.5">Klik urutan titik pola (1-9):</p>
                                <div class="grid grid-cols-3 gap-2 w-40" x-data="{}">
                                    @for($i = 1; $i <= 9; $i++)
                                        <button
                                            type="button"
                                            class="aspect-square rounded-full border border-white/15 bg-white/5 text-xs font-bold text-ink-300 hover:bg-up-primary/30 hover:border-up-primary cursor-pointer"
                                            wire:click="$set('terimaForm.kunci_terenkripsi.{{ $loop->index }}', {{ $i }})"
                                            @click="$el.classList.toggle('bg-up-primary')"
                                        >{{ $i }}</button>
                                    @endfor
                                </div>
                                <p class="text-[10px] text-up-primary mt-1 font-mono tabular-nums">
                                    {{ is_array($terimaForm['kunci_terenkripsi'] ?? null) ? implode('-', $terimaForm['kunci_terenkripsi']) : ($terimaForm['kunci_terenkripsi'] ?? '') }}
                                </p>
                            </div>
                        @else
                            <input
                                type="password"
                                wire:model="terimaForm.kunci_terenkripsi"
                                placeholder="Masukkan PIN / password / pola (angka) — aman terenkripsi"
                                class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium mt-2"
                            />
                        @endif
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Keluhan <span class="text-up-red">*</span></label>
                        <textarea wire:model="terimaForm.keluhan" rows="2" placeholder="Deskripsi keluhan unit..." class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium"></textarea>
                    </div>

                    <!-- Checklist Kondisi Fisik -->
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Checklist Kondisi Fisik</label>
                        <div class="flex flex-wrap gap-2">
                            @php $fisikChecks = ['Layar', 'Body', 'Baterai', 'Kamera', 'Speaker', 'Watermark']; @endphp
                            @foreach($fisikChecks as $check)
                                <button
                                    type="button"
                                    @class([
                                        'px-3 py-1.5 rounded-lg text-[11px] font-semibold border transition-all cursor-pointer active:scale-95',
                                        'bg-up-primary text-white border-up-primary' => in_array($check, $terimaForm['kondisi_fisik'] ?? [], true),
                                        'bg-white/5 text-ink-300 border-white/10 hover:bg-white/10' => !in_array($check, $terimaForm['kondisi_fisik'] ?? [], true),
                                    ])
                                    wire:click="toggleKondisiFisik('{{ $check }}')"
                                >{{ $check }}</button>
                            @endforeach
                        </div>
                        <p class="text-[10px] text-ink-500 mt-1.5">Tandai kerusakan/kondisi yang terlihat pada unit.</p>
                    </div>

                    <!-- Foto Unit (wajib min 2) -->
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">
                            Foto Unit <span class="text-up-red">*</span>
                            <span class="text-ink-500 font-normal">— minimal 2 foto (depan, belakang, layar)</span>
                        </label>
                        <div class="grid grid-cols-3 gap-2" x-init="photoIndex = 0">
                            @foreach($photoInputs as $idx => $foto)
                                <div class="relative aspect-square rounded-xl border border-dashed border-white/15 bg-white/[0.02] overflow-hidden flex items-center justify-center">
                                    @if($foto)
                                        <img src="{{ $foto }}" alt="Foto {{ $idx + 1 }}" class="w-full h-full object-cover">
                                        <button
                                            type="button"
                                            wire:click="removeFoto({{ $idx }})"
                                            class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-black/70 text-up-red hover:bg-black/90 flex items-center justify-center text-xs cursor-pointer"
                                        >✕</button>
                                        <span class="absolute bottom-1.5 left-1.5 text-[9px] font-bold text-white bg-black/60 px-1.5 py-0.5 rounded">
                                            Foto {{ $idx + 1 }}
                                        </span>
                                    @else
                                        <input
                                            type="file"
                                            accept="image/*"
                                            capture="environment"
                                            class="absolute inset-0 opacity-0 cursor-pointer"
                                            x-ref="fotoInput{{ $idx }}"
                                            @change="
                                                const file = $event.target.files[0];
                                                if (file) {
                                                    const reader = new FileReader();
                                                    reader.onload = (e) => {
                                                        $wire.handleFotoUpload({{ $idx }}, e.target.result);
                                                    };
                                                    reader.readAsDataURL(file);
                                                }
                                            "
                                        />
                                        <svg class="w-8 h-8 text-ink-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span class="absolute bottom-1.5 text-[9px] text-ink-500">Foto {{ $idx + 1 }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <p class="text-[10px] mt-1.5 {{ $fotoCount >= 2 ? 'text-up-mint' : 'text-up-amber' }}">
                            {{ $fotoCount }} dari 2 foto minimum terisi
                        </p>
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-4 flex-shrink-0">
                    <button wire:click="$set('showTerimaModal', false)" class="flex-1 py-2.5 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer">Batal</button>
                    <button wire:click="simpanTerima" class="flex-1 py-2.5 rounded-xl bg-up-primary text-white font-bold text-xs shadow-lg shadow-up-primary/25 cursor-pointer">Simpan & Terima Unit</button>
                </div>
            </div>
        </div>
    @endif

    <!-- ===== MODAL: ESTIMASI ===== -->
    @if($showEstimasiModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl border border-white/10 shadow-2xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Input Estimasi Biaya</h3>
                    <button wire:click="$set('showEstimasiModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Estimasi Biaya (Rp) <span class="text-up-red">*</span></label>
                        <input type="number" wire:model.live="estimasiBiaya" min="0" step="500" placeholder="0" class="w-full px-4 py-3 rounded-xl glass-input text-lg font-bold tabular-nums" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Alasan / Rincian Estimasi <span class="text-up-red">*</span></label>
                        <textarea wire:model="estimasiAlasan" rows="3" placeholder="Ex: Ganti LCD Rp 350.000 + jasa Rp 50.000..." class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium"></textarea>
                    </div>

                    <div class="p-3 rounded-xl bg-up-amber/10 border border-up-amber/30 text-[11px] text-up-amber flex items-start gap-2">
                        <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <span>Setelah disimpan, tiket masuk <strong>Menunggu Approval</strong> dan pelanggan dapat approve/reject via link publik tanpa login.</span>
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showEstimasiModal', false)" class="flex-1 py-2.5 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer">Batal</button>
                    <button wire:click="simpanEstimasi" class="flex-1 py-2.5 rounded-xl bg-up-primary text-white font-bold text-xs shadow-lg shadow-up-primary/25 cursor-pointer">Simpan Estimasi</button>
                </div>
            </div>
        </div>
    @endif

    <!-- ===== MODAL: APPROVE / REJECT ===== -->
    @if($showApproveModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl border border-white/10 shadow-2xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Approve / Tolak Estimasi</h3>
                    <button wire:click="$set('showApproveModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Alasan <span class="text-ink-500 font-normal">(wajib jika tolak)</span></label>
                        <textarea wire:model="approveAlasan" rows="3" placeholder="Ex: Pelanggan setuju via link, atau: pelanggan menolak harga..." class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium"></textarea>
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showApproveModal', false)" class="flex-1 py-2.5 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer">Batal</button>
                    <button wire:click="prosesApprove('reject')" class="flex-1 py-2.5 rounded-xl bg-up-red hover:opacity-90 text-white font-bold text-xs cursor-pointer">Tolak</button>
                    <button wire:click="prosesApprove('approve')" class="flex-1 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs cursor-pointer">Setujui</button>
                </div>
            </div>
        </div>
    @endif

    <!-- ===== MODAL: DETAIL TIKET ===== -->
    @if($selectedTiketId && $selectedTiket)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-3xl glass-panel p-6 rounded-3xl border border-white/10 shadow-2xl relative max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10 flex-shrink-0">
                    <div class="flex items-center gap-3">
                        <h3 class="text-lg font-bold text-white">{{ $selectedTiket->no_tiket }}</h3>
                        <x-prism.status-pill :status="$selectedTiket->status" />
                        @if($selectedTiket->garansi)
                            <span class="text-[10px] font-bold {{ $selectedTiket->garansi->active ? 'text-up-mint bg-up-mint/10 border border-up-mint/30' : 'text-ink-500 bg-white/5 border border-white/10' }} px-2 py-0.5 rounded-full">
                                {{ $selectedTiket->garansi->active ? 'Dalam Garansi' : 'Garansi Habis' }}
                            </span>
                        @endif
                    </div>
                    <button wire:click="$set('selectedTiketId', null)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="overflow-y-auto pr-1 flex-1 space-y-5">
                    <!-- Foto Galeri -->
                    @if(count($selectedTiket->foto_unit ?? []) > 0)
                        <div class="flex gap-2 flex-wrap">
                            @foreach($selectedTiket->foto_unit as $f)
                                <img src="{{ $f }}" alt="Foto unit" class="w-24 h-24 object-cover rounded-xl border border-white/10">
                            @endforeach
                        </div>
                    @endif

                    <!-- Info Utama -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                            <p class="text-[10px] text-ink-400 uppercase">Jenis HP</p>
                            <p class="text-sm font-bold text-white">{{ $selectedTiket->jenis_hp }}</p>
                        </div>
                        <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                            <p class="text-[10px] text-ink-400 uppercase">Pelanggan</p>
                            <p class="text-sm font-bold text-white">{{ $selectedTiket->nama_pelanggan ?? $selectedTiket->pelanggan?->nama ?? 'Guest' }}</p>
                            <p class="text-[11px] text-ink-400">{{ $selectedTiket->telepon_pelanggan ?? $selectedTiket->pelanggan?->telepon }}</p>
                        </div>
                        <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                            <p class="text-[10px] text-ink-400 uppercase">Estimasi</p>
                            <p class="text-sm font-bold {{ $selectedTiket->estimasi_biaya ? 'text-up-mint' : 'text-ink-400' }} tabular-nums">
                                {{ $selectedTiket->estimasi_biaya ? 'Rp ' . number_format($selectedTiket->estimasi_biaya, 0, ',', '.') : 'Belum ada' }}
                            </p>
                        </div>
                        <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                            <p class="text-[10px] text-ink-400 uppercase">Jenis Servis</p>
                            <p class="text-sm font-bold text-white">{{ $selectedTiket->jenisServis?->nama ?? '-' }}</p>
                        </div>
                        <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                            <p class="text-[10px] text-ink-400 uppercase">Teknisi</p>
                            <p class="text-sm font-bold text-white">{{ $selectedTiket->teknisi?->name ?? 'Belum ditugaskan' }}</p>
                        </div>
                        <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                            <p class="text-[10px] text-ink-400 uppercase">Di terima</p>
                            <p class="text-sm font-bold text-white">{{ $selectedTiket->tanggal_terima?->format('d/m/Y H:i') ?? '-' }}</p>
                        </div>
                    </div>

                    <!-- Keluhan -->
                    <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                        <p class="text-[10px] text-ink-400 uppercase mb-1">Keluhan</p>
                        <p class="text-sm text-ink-100">{{ $selectedTiket->keluhan }}</p>
                        @if($selectedTiket->alasan_estimasi)
                            <p class="text-[11px] text-up-amber mt-2 pt-2 border-t border-white/5">Rincian estimasi: {{ $selectedTiket->alasan_estimasi }}</p>
                        @endif
                    </div>

                    <!-- Kondisi Fisik -->
                    @if(count($selectedTiket->kondisi_fisik ?? []) > 0)
                        <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                            <p class="text-[10px] text-ink-400 uppercase mb-1.5">Checklist Kondisi Fisik</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($selectedTiket->kondisi_fisik as $k)
                                    <span class="text-[10px] font-semibold text-up-amber bg-up-amber/10 border border-up-amber/30 px-2 py-0.5 rounded-full">{{ $k }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Sparepart Terpakai -->
                    @if($selectedTiket->spareparts->count() > 0)
                        <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                            <p class="text-[10px] text-ink-400 uppercase mb-2">Sparepart Terpakai</p>
                            <div class="space-y-1.5">
                                @foreach($selectedTiket->spareparts as $sp)
                                    <div class="flex justify-between text-xs">
                                        <span class="text-ink-100">{{ $sp->produk?->nama }} × {{ $sp->jumlah }}</span>
                                        <span class="font-bold text-white tabular-nums">Rp {{ number_format($sp->harga_satuan * $sp->jumlah, 0, ',', '.') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Garansi -->
                    @if($selectedTiket->garansi)
                        <div class="p-3 rounded-xl bg-up-mint/5 border border-up-mint/20">
                            <p class="text-[10px] text-up-mint uppercase font-bold mb-1">Garansi Servis</p>
                            <p class="text-xs text-ink-100">
                                Berlaku {{ $selectedTiket->garansi->tanggal_mulai->format('d/m/Y') }} — {{ $selectedTiket->garansi->tanggal_berakhir->format('d/m/Y') }}
                                ({{ $selectedTiket->garansi->durasi_hari }} hari)
                            </p>
                        </div>
                    @endif

                    <!-- Status Timeline -->
                    <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                        <p class="text-[10px] text-ink-400 uppercase mb-3">Riwayat Status</p>
                        <div class="space-y-2.5">
                            @foreach($selectedTiket->statusLogs as $log)
                                <div class="flex gap-3 items-start">
                                    <div class="w-2 h-2 rounded-full mt-1.5 {{ $log->aksi === 'override' ? 'bg-up-red' : 'bg-up-primary' }} flex-shrink-0"></div>
                                    <div class="min-w-0">
                                        <p class="text-xs text-ink-100">
                                            <strong class="text-white font-mono text-[11px]">
                                                {{ $log->status_dari ? $log->status_dari . ' → ' : '' }}{{ $log->status_ke }}
                                            </strong>
                                            @if($log->aksi === 'override')
                                                <span class="ml-1.5 text-[9px] font-bold text-up-red bg-up-red/10 px-1.5 py-0.5 rounded">OVERRIDE</span>
                                            @endif
                                        </p>
                                        @if($log->alasan)
                                            <p class="text-[11px] text-ink-400">{{ $log->alasan }}</p>
                                        @endif
                                        <p class="text-[10px] text-ink-500 mt-0.5">
                                            {{ $log->created_at->format('d/m/Y H:i') }} — {{ $log->user?->name ?? 'Publik' }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>