<div class="flex flex-col h-[calc(100vh-8.5rem)] overflow-hidden"
     x-data="{ dragId: null, overCol: null, photoIndex: 0 }">

    <!-- ===== TOOLBAR ===== -->
    <div class="flex flex-col sm:flex-row gap-2.5 sm:gap-3 items-stretch sm:items-center justify-between mb-3 flex-shrink-0">
        <div class="flex-1 min-w-0">
            <x-prism.barcode-scan-input placeholder="Scan barcode tiket / cari no. tiket, HP, nama..." model="search" title="Scan Barcode / QR Tiket Servis" />
        </div>

        <div class="flex items-center gap-2 overflow-x-auto scrollbar-none py-1 -my-1 flex-nowrap sm:flex-wrap">
            <select wire:model.live="filterStatus" class="flex-1 sm:flex-initial px-3 py-2.5 rounded-xl glass-input text-xs font-medium min-h-[44px]">
                <option value="" class="bg-ink-900">Semua Status</option>
                @foreach($stateMachineColumns as $kode => $col)
                    <option value="{{ $kode }}" class="bg-ink-900">{{ $col['label'] }}</option>
                @endforeach
            </select>

            <!-- [F2-5] Export laporan servis (queue) -->
            @can('laporan.cabang')
                <div class="flex items-center gap-1.5">
                    <button type="button" wire:click="exportLaporan('xlsx')" title="Export Excel" class="px-3 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-200 font-bold text-xs whitespace-nowrap cursor-pointer transition-[transform,background-color] active:scale-[0.97] min-h-[44px]">
                        <span class="hidden sm:inline">Export Excel</span>
                        <span class="sm:hidden">XLS</span>
                    </button>
                    <button type="button" wire:click="exportLaporan('csv')" title="Export CSV" class="px-3 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-200 font-bold text-xs whitespace-nowrap cursor-pointer transition-[transform,background-color] active:scale-[0.97] min-h-[44px]">
                        <span class="hidden sm:inline">Export CSV</span>
                        <span class="sm:hidden">CSV</span>
                    </button>
                </div>
            @endcan

            @can('servis.create')
                <button
                    type="button"
                    wire:click="openTerimaModal"
                    class="flex-1 sm:flex-initial px-4 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs shadow-md shadow-up-primary/25 flex items-center justify-center gap-2 cursor-pointer active:scale-[0.97] transition-[transform,background-color] min-h-[44px] whitespace-nowrap"
                >
                    <span class="text-base leading-none font-bold">+</span>
                    <span>Terima Unit</span>
                </button>
            @endcan
        </div>
    </div>

    <!-- Mobile Kanban Swipe Hint -->
    <div class="sm:hidden flex items-center justify-between text-[11px] text-ink-400 mb-1.5 px-0.5 flex-shrink-0">
        <span class="inline-flex items-center gap-1">
            <svg class="w-3.5 h-3.5 text-up-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
            </svg>
            Geser horizontal untuk melihat alur status
        </span>
        <span class="text-[10px] font-mono text-ink-500">6 Kolom</span>
    </div>

    <!-- ===== ANTREAN BOOKING ONLINE ===== -->
    @if(count($bookingOnline ?? []) > 0)
        <div class="mb-4 flex-shrink-0 p-4 rounded-2xl bg-up-amber/10 border border-up-amber/30">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-up-amber animate-pulse"></span>
                    <h3 class="text-sm font-bold text-up-amber">
                        Ada {{ count($bookingOnline) }} Pengajuan Servis Online Menunggu Konfirmasi
                    </h3>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($bookingOnline as $b)
                    <div class="p-3 rounded-xl bg-ink-900/60 border border-white/5 flex flex-col justify-between gap-2">
                        <div>
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="font-mono font-bold text-up-primary">{{ $b->no_tiket }}</span>
                                <span class="text-ink-400">{{ $b->created_at?->diffForHumans() }}</span>
                            </div>
                            <div class="text-xs font-semibold text-white">
                                {{ $b->nama_pelanggan ?? $b->pelanggan?->nama ?? 'Anonim' }}
                                @if($b->telepon_pelanggan)
                                    <span class="text-ink-400 font-normal">({{ $b->telepon_pelanggan }})</span>
                                @endif
                            </div>
                            <div class="text-xs font-medium text-up-accent mt-0.5">{{ $b->jenis_hp }}</div>
                            <p class="text-xs text-ink-300 mt-1 line-clamp-2">{{ $b->keluhan }}</p>
                        </div>
                        <div class="pt-2 border-t border-white/5 flex justify-end">
                            <button
                                type="button"
                                wire:click="konfirmasiBookingOnline({{ $b->id }})"
                                class="px-3 py-1.5 rounded-lg bg-up-primary hover:bg-up-primary-dark text-white text-xs font-semibold flex items-center gap-1.5 cursor-pointer active:scale-95 transition-all shadow-sm shadow-up-primary/30"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Konfirmasi & Terima Unit
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- ===== KANBAN BOARD ===== -->
    <div class="flex-1 overflow-x-auto overflow-y-hidden pb-2 snap-x snap-mandatory scroll-smooth touch-pan-x">
        <div class="flex gap-3 sm:gap-4 h-full min-w-max px-0.5">
            @foreach($stateMachineColumns as $status => $col)
                <div class="w-[82vw] sm:w-[320px] lg:w-[280px] snap-center flex-shrink-0 flex flex-col rounded-2xl glass-panel overflow-hidden"
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
                                            <div class="flex items-center gap-1">
                                                @if($tiket->status_pembayaran === 'lunas')
                                                    <span class="bg-up-mint/20 text-up-mint text-[9px] font-bold px-1.5 py-0.5 rounded">LUNAS</span>
                                                @elseif(in_array($tiket->status, ['selesai', 'diambil'], true))
                                                    <a
                                                        href="/app/pos?bayar_servis_id={{ $tiket->id }}"
                                                        class="bg-up-primary text-white text-[10px] font-bold px-2 py-0.5 rounded-lg hover:bg-up-primary/80 cursor-pointer inline-flex items-center gap-1"
                                                        title="Bayar di Kasir POS"
                                                    >Bayar Kasir</a>
                                                @endif
                                                @if($tiket->garansi && $tiket->garansi->active)
                                                    <span class="text-[9px] font-bold text-up-mint bg-up-mint/10 border border-up-mint/30 px-1.5 py-0.5 rounded-full whitespace-nowrap">Dalam Garansi</span>
                                                @endif
                                            </div>
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
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kunci Gadget <span class="text-ink-500 font-normal">(opsional, terenkripsi — terlihat oleh semua staf yang berhak membuka tiket servis)</span></label>
                        <select wire:model="terimaForm.tipe_kunci" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            <option value="" class="bg-ink-900">— Tidak ada / default —</option>
                            <option value="pola" class="bg-ink-900">Pola</option>
                            <option value="pin" class="bg-ink-900">PIN (4-6 digit)</option>
                            <option value="password" class="bg-ink-900">Password</option>
                            <option value="tidak_ada" class="bg-ink-900">Tidak ada</option>
                        </select>

                        @if(($terimaForm['tipe_kunci'] ?? '') === 'pola')
                            <div class="mt-2" x-data="{
                                resetPola() {
                                    $wire.set('terimaForm.kunci_terenkripsi', '');
                                }
                            }">
                                <p class="text-[10px] text-ink-400 mb-1.5">Klik urutan titik pola (1-9):</p>
                                <div class="grid grid-cols-3 gap-2 w-40">
                                    @for($i = 1; $i <= 9; $i++)
                                        <button
                                            type="button"
                                            class="aspect-square rounded-full border border-white/15 bg-white/5 text-xs font-bold text-ink-300 hover:bg-up-primary/30 hover:border-up-primary cursor-pointer transition-colors"
                                            wire:click="$set('terimaForm.kunci_terenkripsi.{{ $i - 1 }}', {{ $i }})"
                                        >{{ $i }}</button>
                                    @endfor
                                </div>
                                <div class="flex items-center justify-between mt-2 pt-1 border-t border-white/5">
                                    <p class="text-[11px] text-up-primary font-mono tabular-nums">
                                        Urutan: <span class="font-bold">{{ is_array($terimaForm['kunci_terenkripsi'] ?? null) ? implode('-', array_filter($terimaForm['kunci_terenkripsi'])) : ($terimaForm['kunci_terenkripsi'] ?? '-') }}</span>
                                    </p>
                                    <button
                                        type="button"
                                        wire:click="$set('terimaForm.kunci_terenkripsi', '')"
                                        class="px-2 py-0.5 rounded text-[10px] font-semibold bg-white/5 text-up-red hover:bg-up-red/10 border border-white/10 cursor-pointer"
                                    >Reset Pola</button>
                                </div>
                            </div>
                        @else
                            <div class="mt-2 relative" x-data="{ showPassword: false }">
                                <input
                                    :type="showPassword ? 'text' : 'password'"
                                    wire:model="terimaForm.kunci_terenkripsi"
                                    placeholder="Masukkan PIN / password — aman terenkripsi"
                                    class="w-full pl-3 pr-10 py-2.5 rounded-xl glass-input text-xs font-medium"
                                />
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-ink-400 hover:text-white p-1 text-sm focus:outline-none cursor-pointer"
                                    :title="showPassword ? 'Sembunyikan' : 'Lihat karakter'"
                                >
                                    <span x-show="!showPassword">👁️</span>
                                    <span x-show="showPassword" style="display: none;">🔒</span>
                                </button>
                            </div>
                        @endif
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Keluhan <span class="text-up-red">*</span></label>
                        <textarea wire:model="terimaForm.keluhan" rows="2" placeholder="Deskripsi keluhan unit..." class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium"></textarea>
                    </div>

                    <!-- [B-06] Checklist Kondisi Fisik — langkah 1 catatan fisik saat terima unit -->
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kondisi Fisik Unit <span class="text-ink-500 font-normal">(opsional)</span></label>
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
                        <p class="text-[10px] text-ink-500 mt-1.5">Langkah 1 dari 2 — <strong class="text-ink-300">centang bagian yang bermasalah/bercacat</strong> (chip menyala = bermasalah). Tidak ada checklist = unit diterima tanpa catatan kerusakan.</p>
                    </div>

                    <!-- [B-06] Foto Unit — OPSIONAL (tanpa foto pun submit tetap jalan) -->
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">
                            Foto Unit <span class="text-ink-500 font-normal">(opsional — depan, belakang, layar)</span>
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
                                        <div class="absolute inset-0 flex flex-col">
                                            <!-- Kamera (capture) -->
                                            <label class="flex-1 flex flex-col items-center justify-center gap-1 cursor-pointer">
                                                <input
                                                    type="file"
                                                    accept="image/*"
                                                    capture="environment"
                                                    class="absolute inset-0 opacity-0 cursor-pointer"
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
                                                <span class="text-[9px] text-ink-500">Kamera</span>
                                            </label>
                                            <!-- Galeri (fallback) -->
                                            <label class="w-full py-1.5 text-center bg-white/5 hover:bg-white/10 border-t border-white/5 text-[9px] font-semibold text-ink-300 cursor-pointer">
                                                <input
                                                    type="file"
                                                    accept="image/*"
                                                    class="absolute inset-0 opacity-0 cursor-pointer"
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
                                                &#128444; Pilih dari galeri
                                            </label>
                                        </div>
                                        <span class="absolute top-1.5 left-1.5 text-[9px] text-ink-500">Foto {{ $idx + 1 }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <p class="text-[10px] mt-1.5 {{ $fotoCount > 0 ? 'text-up-mint' : 'text-ink-500' }}">
                            {{ $fotoCount }}/3 foto terisi — <strong>Langkah 2 dari 2</strong>; boleh dikosongkan (foto opsional).
                        </p>
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-4 flex-shrink-0">
                    <button wire:click="$set('showTerimaModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="simpanTerima" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs shadow-lg shadow-up-primary/25 cursor-pointer min-h-[44px]">Simpan & Terima Unit</button>
                </div>
            </div>
        </div>
    @endif

    <!-- ===== MODAL: QUICK-ADD PELANGGAN BARU [T-18] ===== -->
    @if($showPelangganBaruModal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl border border-white/10 shadow-2xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">+ Pelanggan Baru</h3>
                    <button wire:click="$set('showPelangganBaruModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama <span class="text-up-red">*</span></label>
                        <input type="text" wire:model="pelangganBaruForm.nama" placeholder="Nama pelanggan" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Telepon <span class="text-up-red">*</span></label>
                        <input type="text" wire:model="pelangganBaruForm.telepon" placeholder="08xx-xxxx-xxxx" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Alamat</label>
                        <textarea wire:model="pelangganBaruForm.alamat" rows="2" placeholder="Alamat (opsional)" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium"></textarea>
                    </div>
                    <p class="text-[10px] text-ink-500">Disimpan via <strong class="text-up-primary">PelangganService</strong> (satu sumber dgn CRM-06 & POS) — langsung terpilih di tiket.</p>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showPelangganBaruModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="simpanPelangganBaruServis" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs shadow-lg shadow-up-primary/25 cursor-pointer min-h-[44px]">Simpan Pelanggan</button>
                </div>
            </div>
        </div>
    @endif

    <!-- ===== MODAL: ESTIMASI ===== -->
    @if($showEstimasiModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-2xl glass-panel p-6 rounded-3xl border border-white/10 shadow-2xl relative max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10 flex-shrink-0">
                    <div>
                        <h3 class="text-lg font-bold text-white">Input Rincian Estimasi Biaya</h3>
                        <p class="text-xs text-ink-400">Rincian item jasa & sparepart yang diajukan ke konsumen</p>
                    </div>
                    <button wire:click="$set('showEstimasiModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="overflow-y-auto pr-1 flex-1 space-y-4">
                    <!-- Tabel Item Estimasi -->
                    <div class="p-3.5 rounded-2xl bg-white/[0.02] border border-white/5 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-ink-200 uppercase tracking-wider">Daftar Item Estimasi</span>
                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    wire:click="addEstimasiRow('jasa')"
                                    class="px-2.5 py-1 rounded-lg bg-up-mint/10 hover:bg-up-mint/20 text-up-mint text-[11px] font-semibold border border-up-mint/30 cursor-pointer"
                                >+ Tambah Jasa</button>
                                <button
                                    type="button"
                                    wire:click="addEstimasiRow('part')"
                                    class="px-2.5 py-1 rounded-lg bg-up-primary/10 hover:bg-up-primary/20 text-up-primary text-[11px] font-semibold border border-up-primary/30 cursor-pointer"
                                >+ Tambah Part</button>
                            </div>
                        </div>

                        <div class="space-y-2.5">
                            @forelse($estimasiItems as $idx => $row)
                                <div class="p-2.5 rounded-xl bg-white/[0.03] border border-white/5 space-y-2">
                                    <div class="grid grid-cols-12 gap-2 items-center">
                                        <!-- Tipe Badge -->
                                        <div class="col-span-2">
                                            <span @class([
                                                'inline-block w-full text-center py-1 text-[10px] font-bold rounded-lg border uppercase',
                                                'bg-up-mint/10 text-up-mint border-up-mint/30' => ($row['tipe'] ?? '') === 'jasa',
                                                'bg-up-primary/10 text-up-primary border-up-primary/30' => ($row['tipe'] ?? '') === 'part',
                                            ])>
                                                {{ $row['tipe'] ?? 'jasa' }}
                                            </span>
                                        </div>

                                        <!-- Dropdown Pemilih Jasa / Part -->
                                        <div class="col-span-4">
                                            @if(($row['tipe'] ?? '') === 'part')
                                                <div class="relative">
                                                    <input type="text"
                                                           wire:model="estimasiItems.{{ $idx }}.produk_nama"
                                                           wire:click="bukaPencarianProduk({{ $idx }}, 'servis_estimasi')"
                                                           class="w-full px-2 py-1.5 pr-7 rounded-lg glass-input text-xs font-medium cursor-pointer"
                                                           placeholder="Klik untuk cari produk..."
                                                           readonly>
                                                    @if($row['produk_id'])
                                                        <button type="button"
                                                                wire:click="resetEstimasiPartRow({{ $idx }})"
                                                                class="absolute right-2 top-1/2 -translate-y-1/2 text-ink-400 hover:text-white"
                                                                title="Hapus pilihan produk">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                            </svg>
                                                        </button>
                                                    @else
                                                        <button type="button"
                                                                wire:click="bukaPencarianProduk({{ $idx }}, 'servis_estimasi')"
                                                                class="absolute right-2 top-1/2 -translate-y-1/2 text-ink-400 hover:text-white cursor-pointer"
                                                                title="Cari Produk">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                                            </svg>
                                                        </button>
                                                    @endif
                                                </div>
                                            @else
                                                <select
                                                    wire:model.live="estimasiItems.{{ $idx }}.jenis_servis_id"
                                                    class="w-full px-2 py-1.5 rounded-lg glass-input text-xs font-medium"
                                                >
                                                    <option value="" class="bg-ink-900">— Template Jasa (Opsional) —</option>
                                                    @foreach($jenisServisList as $j)
                                                        <option value="{{ $j->id }}" class="bg-ink-900">
                                                            {{ $j->nama }} (Rp {{ number_format($j->biaya_jasa ?? 0, 0, ',', '.') }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @endif
                                        </div>

                                        <!-- Nama Item -->
                                        <div class="col-span-5">
                                            <input
                                                type="text"
                                                wire:model="estimasiItems.{{ $idx }}.nama_item"
                                                placeholder="Deskripsi item..."
                                                class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs font-medium"
                                            />
                                        </div>

                                        <!-- Hapus Row -->
                                        <div class="col-span-1 text-right">
                                            <button
                                                type="button"
                                                wire:click="removeEstimasiRow({{ $idx }})"
                                                class="text-up-red hover:text-white text-sm cursor-pointer p-1"
                                                title="Hapus baris"
                                            >✕</button>
                                        </div>
                                    </div>

                                    <!-- Baris Qty, Harga, Subtotal -->
                                    <div class="grid grid-cols-12 gap-2 items-center pl-2 border-t border-white/5 pt-1.5 text-xs">
                                        <div class="col-span-3 flex items-center gap-1.5">
                                            <span class="text-[10px] text-ink-400">Qty:</span>
                                            <input
                                                type="number"
                                                min="1"
                                                wire:model.live.debounce.300ms="estimasiItems.{{ $idx }}.qty"
                                                class="w-16 px-2 py-1 rounded-lg glass-input text-xs font-bold text-center"
                                            />
                                        </div>
                                        <div class="col-span-4 flex items-center gap-1.5">
                                            <span class="text-[10px] text-ink-400">Harga:</span>
                                            <input
                                                type="text"
                                                inputmode="numeric"
                                                x-format-number
                                                wire:model.live.debounce.350ms="estimasiItems.{{ $idx }}.harga"
                                                placeholder="0"
                                                class="w-full px-2 py-1 rounded-lg glass-input text-xs font-bold tabular-nums"
                                            />
                                        </div>
                                        <div class="col-span-5 text-right font-bold text-white tabular-nums">
                                            <span class="text-[10px] text-ink-400 font-normal mr-1">Subtotal:</span>
                                            Rp {{ number_format($row['subtotal'] ?? 0, 0, ',', '.') }}
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-4 text-xs text-ink-400">
                                    Belum ada baris item estimasi. Silakan tambah Jasa atau Part di atas.
                                </div>
                            @endforelse
                        </div>

                        <!-- Total Ringkasan -->
                        <div class="flex items-center justify-between pt-3 border-t border-white/10">
                            <span class="text-xs font-bold text-ink-300">Total Estimasi Biaya</span>
                            <div class="text-right">
                                <span class="text-base font-bold text-up-mint tabular-nums">
                                    Rp {{ number_format($estimasiBiaya, 0, ',', '.') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Catatan / Alasan Estimasi <span class="text-ink-500 font-normal">(opsional)</span></label>
                        <textarea wire:model="estimasiAlasan" rows="2" placeholder="Catatan estimasi untuk konsumen (misal: estimasi pengerjaan 1-2 hari)..." class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium"></textarea>
                        @error('estimasiAlasan')
                            <p class="text-[11px] text-up-red mt-1 font-medium">{{ $message }}</p>
                        @enderror
                        @error('estimasiBiaya')
                            <p class="text-[11px] text-up-red mt-1 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="p-3 rounded-xl bg-up-amber/10 border border-up-amber/30 text-[11px] text-up-amber flex items-start gap-2">
                        <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <span>Setelah disimpan, tiket masuk <strong>Menunggu Approval</strong> dan pelanggan dapat approve/reject via link publik tanpa login.</span>
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-4 flex-shrink-0">
                    <button type="button" wire:click="$set('showEstimasiModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button
                        type="button"
                        wire:click="simpanEstimasi"
                        wire:loading.attr="disabled"
                        class="flex-1 py-3 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs shadow-lg shadow-up-primary/25 cursor-pointer min-h-[44px] flex items-center justify-center gap-2"
                    >
                        <span wire:loading.remove wire:target="simpanEstimasi">Simpan Estimasi</span>
                        <span wire:loading wire:target="simpanEstimasi" class="inline-flex items-center gap-1.5">
                            <svg class="animate-spin h-3.5 w-3.5 text-white" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            Menyimpan...
                        </span>
                    </button>
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
                    <button wire:click="$set('showApproveModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="prosesApprove('reject')" class="flex-1 py-3 rounded-xl bg-up-red hover:opacity-90 text-white font-bold text-xs cursor-pointer min-h-[44px]">Tolak</button>
                    <button wire:click="prosesApprove('approve')" class="flex-1 py-3 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs cursor-pointer min-h-[44px]">Setujui</button>
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
                        @can('lihat-audit-log')
                            <button
                                type="button"
                                wire:click="bukaRiwayat('servis', {{ $selectedTiket->id }})"
                                class="text-[10px] font-bold text-up-primary bg-up-primary/10 border border-up-primary/30 hover:bg-up-primary/20 px-2 py-0.5 rounded-full cursor-pointer flex items-center gap-1"
                                title="Lihat Riwayat & Log Mutasi Lengkap"
                            >
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <span>Audit Log</span>
                            </button>
                        @endcan
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
                            <div class="flex items-center justify-between">
                                <p class="text-[10px] text-ink-400 uppercase">Estimasi</p>
                                @if(in_array($selectedTiket->status, ['diagnosa', 'menunggu_approval', 'ditolak'], true))
                                    <button
                                        type="button"
                                        wire:click="openEstimasiModal({{ $selectedTiket->id }})"
                                        class="text-[10px] font-bold text-up-mint hover:underline cursor-pointer"
                                    >{{ $selectedTiket->estimasi_biaya ? 'Ubah' : '+ Input' }}</button>
                                @endif
                            </div>
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

                    <!-- Status Pembayaran -->
                    <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <p class="text-[10px] text-ink-400 uppercase mb-1">Status Pembayaran</p>
                            <div class="flex items-center gap-2">
                                @if($selectedTiket->status_pembayaran === 'lunas')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-up-mint/10 text-up-mint border border-up-mint/30">
                                        LUNAS
                                    </span>
                                    <span class="text-xs text-ink-300">
                                        {{ $selectedTiket->tanggal_bayar?->format('d/m/Y H:i') }} • {{ strtoupper($selectedTiket->metode_pembayaran ?? '-') }}
                                        @if($selectedTiket->no_jurnal_bayar)
                                            <span class="font-mono text-[11px] text-ink-400">({{ $selectedTiket->no_jurnal_bayar }})</span>
                                        @endif
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-up-red/10 text-up-red border border-up-red/30">
                                        BELUM BAYAR
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                wire:click="bukaStrukServis({{ $selectedTiket->id }})"
                                class="px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-ink-200 hover:text-white border border-white/10 font-bold text-xs transition-all cursor-pointer inline-flex items-center gap-1.5"
                                title="Cetak struk thermal 58mm / nota servis"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                <span>Cetak Struk</span>
                            </button>
                        @if($selectedTiket->status_pembayaran !== 'lunas' && in_array($selectedTiket->status, ['selesai', 'diambil'], true))
                            <a
                                href="/app/pos?bayar_servis_id={{ $selectedTiket->id }}"
                                class="px-4 py-2 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs transition-all cursor-pointer self-start sm:self-auto inline-flex items-center gap-1.5 shadow-sm"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                <span>Bayar di Kasir POS</span>
                            </a>
                            @if($selectedTiket->sumber === 'online')
                                <button
                                    type="button"
                                    wire:click="openBayarModal({{ $selectedTiket->id }})"
                                    class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 font-semibold text-xs border border-white/10 cursor-pointer"
                                    title="Konfirmasi Pembayaran Online (Non-Tunai / Marketplace)"
                                >
                                    Bayar Online
                                </button>
                            @endif
                        @endif
                        </div>
                    </div>

                    <!-- [B-06] Kunci Gadget — terlihat utk semua role yg berhak buka tiket, default ter-mask -->
                    <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                        <div class="flex items-center justify-between gap-2 mb-1.5">
                            <p class="text-[10px] text-ink-400 uppercase">Kunci Gadget</p>
                            @if($selectedTiket->kunci_terenkripsi)
                                <button
                                    type="button"
                                    wire:click="toggleKunciGadget"
                                    class="text-[10px] font-bold {{ $bukaKunciGadget ? 'text-up-red' : 'text-up-primary' }} hover:underline cursor-pointer"
                                >{{ $bukaKunciGadget ? 'Sembunyikan' : 'Tampilkan' }}</button>
                            @endif
                        </div>
                        @php
                            $labelTipeKunci = match ($selectedTiket->tipe_kunci) {
                                'pola' => 'Pola',
                                'pin' => 'PIN (4-6 digit)',
                                'password' => 'Password',
                                'tidak_ada' => 'Tidak ada / default',
                                default => null,
                            };
                        @endphp
                        @if($labelTipeKunci)
                            <p class="text-sm font-bold text-white">{{ $labelTipeKunci }}</p>
                        @endif
                        @if($selectedTiket->kunci_terenkripsi)
                            <p class="text-sm font-mono {{ $bukaKunciGadget ? 'text-up-amber' : 'text-ink-500 tracking-[0.3em]' }}">
                                {{ $bukaKunciGadget ? $selectedTiket->kunci_terenkripsi : '••••••••' }}
                            </p>

                            @if($bukaKunciGadget && $selectedTiket->tipe_kunci === 'pola')
                                @php
                                    $polaRaw = explode('-', str_replace(' ', '', (string) $selectedTiket->kunci_terenkripsi));
                                    $polaOrder = [];
                                    foreach ($polaRaw as $urutan => $titik) {
                                        $t = (int) $titik;
                                        if ($t >= 1 && $t <= 9 && !isset($polaOrder[$t])) {
                                            $polaOrder[$t] = $urutan + 1;
                                        }
                                    }
                                @endphp
                                <div class="mt-3 p-3 rounded-xl bg-white/[0.02] border border-white/10 inline-block">
                                    <p class="text-[10px] text-ink-400 mb-2 font-medium">Visual Pola (Urutan Sentuh):</p>
                                    <div class="grid grid-cols-3 gap-2 w-36">
                                        @for($i = 1; $i <= 9; $i++)
                                            @php $urutan = $polaOrder[$i] ?? null; @endphp
                                            <div @class([
                                                'aspect-square rounded-full flex items-center justify-center font-bold text-xs border transition-colors',
                                                'bg-up-primary text-white border-up-primary shadow-md shadow-up-primary/30 ring-2 ring-up-primary/40' => $urutan !== null,
                                                'bg-white/5 text-ink-500 border-white/10' => $urutan === null,
                                            ])>
                                                {{ $urutan ?? $i }}
                                            </div>
                                        @endfor
                                    </div>
                                </div>
                            @endif
                        @elseif($labelTipeKunci)
                            <p class="text-sm text-ink-500">Tidak ada nilai kunci yang tersimpan</p>
                        @endif
                        <p class="text-[10px] text-ink-500 mt-1.5">Data sensitif — hanya untuk keperluan servis di konter, jangan dibagikan ke pelanggan.</p>
                    </div>

                    <!-- Keluhan -->
                    <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                        <p class="text-[10px] text-ink-400 uppercase mb-1">Keluhan</p>
                        <p class="text-sm text-ink-100">{{ $selectedTiket->keluhan }}</p>
                        @if($selectedTiket->alasan_estimasi)
                            <p class="text-[11px] text-up-amber mt-2 pt-2 border-t border-white/5">Rincian estimasi: {{ $selectedTiket->alasan_estimasi }}</p>
                        @endif
                    </div>

                    <!-- [B-06] Kondisi Fisik — selalu tampil (ada empty-state) agar alur catatan jelas -->
                    <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                        <p class="text-[10px] text-ink-400 uppercase mb-1.5">Kondisi Fisik (catatan saat terima unit)</p>
                        @if(count($selectedTiket->kondisi_fisik ?? []) > 0)
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($selectedTiket->kondisi_fisik as $k)
                                    <span class="text-[10px] font-semibold text-up-amber bg-up-amber/10 border border-up-amber/30 px-2 py-0.5 rounded-full">{{ $k }}</span>
                                @endforeach
                            </div>
                            <p class="text-[10px] text-ink-500 mt-1.5">Chip = bagian yang bermasalah saat unit diterima.</p>
                        @else
                            <p class="text-[11px] text-ink-500">Belum ada catatan kondisi fisik — saat terima unit, centang bagian yang bermasalah pada checklist (Layar, Body, dst.).</p>
                        @endif
                    </div>

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

                    <!-- Rincian Estimasi Biaya Konsumen (jika ada) -->
                    @if($selectedTiket->estimasiItems && $selectedTiket->estimasiItems->count() > 0)
                        <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-[10px] text-ink-400 uppercase font-semibold">Rincian Estimasi Biaya ({{ $selectedTiket->estimasiItems->count() }} item)</p>
                                @if(in_array($selectedTiket->status, ['diagnosa', 'menunggu_approval', 'ditolak'], true))
                                    <button
                                        type="button"
                                        wire:click="openEstimasiModal({{ $selectedTiket->id }})"
                                        class="text-[10px] font-bold text-up-mint hover:underline cursor-pointer"
                                    >Edit Rincian</button>
                                @endif
                            </div>
                            <div class="space-y-1.5">
                                @foreach($selectedTiket->estimasiItems as $it)
                                    <div class="flex justify-between text-xs">
                                        <span class="text-ink-100">
                                            <span class="text-[9px] font-bold {{ $it->tipe === 'part' ? 'text-up-amber bg-up-amber/10 border border-up-amber/30' : 'text-up-mint bg-up-mint/10 border border-up-mint/30' }} px-1.5 py-0.5 rounded-full mr-1.5">
                                                {{ strtoupper($it->tipe) }}
                                            </span>
                                            {{ $it->nama_item }} × {{ $it->qty }}
                                        </span>
                                        <span class="font-bold text-white tabular-nums">Rp {{ number_format($it->subtotal ?: ($it->harga * $it->qty), 0, ',', '.') }}</span>
                                    </div>
                                @endforeach
                                <div class="pt-2 border-t border-white/5 flex justify-between text-xs font-bold">
                                    <span class="text-ink-300">Total Estimasi:</span>
                                    <span class="text-up-mint tabular-nums">Rp {{ number_format($selectedTiket->estimasi_biaya ?? 0, 0, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- [T-17] Item Pekerjaan Teknisi (part/jasa) -->
                    @if($selectedTiket->items->count() > 0)
                        <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                            <p class="text-[10px] text-ink-400 uppercase mb-2">Item Pekerjaan ({{ $selectedTiket->items->count() }})</p>
                            <div class="space-y-1.5">
                                @foreach($selectedTiket->items as $it)
                                    <div class="flex justify-between text-xs">
                                        <span class="text-ink-100">
                                            <span class="text-[9px] font-bold {{ $it->tipe === 'part' ? 'text-up-amber bg-up-amber/10 border border-up-amber/30' : 'text-up-mint bg-up-mint/10 border border-up-mint/30' }} px-1.5 py-0.5 rounded-full mr-1.5">
                                                {{ strtoupper($it->tipe) }}
                                            </span>
                                            {{ $it->nama_item }} × {{ $it->qty }}
                                        </span>
                                        <span class="font-bold text-white tabular-nums">Rp {{ number_format($it->harga * $it->qty, 0, ',', '.') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- [T-17] Form input pekerjaan teknisi (part → potong stok, jasa → tagihan) -->
                    @if(in_array($selectedTiket->status, ['disetujui', 'dikerjakan', 'qc'], true))
                        <div class="p-3 rounded-xl bg-white/[0.03] border border-white/5">
                            <p class="text-[10px] text-ink-400 uppercase mb-2">Input Pekerjaan Teknisi</p>
                            @foreach($pekerjaanItems as $idx => $row)
                                <div class="grid grid-cols-12 gap-1.5 mb-2 items-center">
                                    <select wire:model.live="pekerjaanItems.{{ $idx }}.tipe" class="col-span-2 px-2 py-2 rounded-lg glass-input text-[11px] font-medium">
                                        <option value="jasa" class="bg-ink-900">Jasa</option>
                                        <option value="part" class="bg-ink-900">Part</option>
                                    </select>
                                    <div class="col-span-3 relative">
                                        <input type="text"
                                               wire:model="pekerjaanItems.{{ $idx }}.produk_nama"
                                               wire:click="bukaPencarianProduk({{ $idx }}, 'servis_pekerjaan')"
                                               class="w-full px-2 py-2 pr-7 rounded-lg glass-input text-[11px] font-medium cursor-pointer"
                                               placeholder="Klik untuk cari produk..."
                                               readonly>
                                        @if($row['produk_id'])
                                            <button type="button"
                                                    wire:click="resetPekerjaanPartRow({{ $idx }})"
                                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-ink-400 hover:text-white"
                                                    title="Hapus pilihan produk">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        @else
                                            <button type="button"
                                                    wire:click="bukaPencarianProduk({{ $idx }}, 'servis_pekerjaan')"
                                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-ink-400 hover:text-white cursor-pointer"
                                                    title="Cari Produk">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                    <input type="text" wire:model="pekerjaanItems.{{ $idx }}.nama_item" placeholder="Nama item" class="col-span-3 px-2 py-2 rounded-lg glass-input text-[11px] font-medium" />
                                    <input type="number" wire:model="pekerjaanItems.{{ $idx }}.qty" min="1" placeholder="Qty" class="col-span-1 px-2 py-2 rounded-lg glass-input text-[11px] font-medium" />
                                    <input type="text" inputmode="numeric" x-format-number wire:model="pekerjaanItems.{{ $idx }}.harga" placeholder="Harga" class="col-span-2 px-2 py-2 rounded-lg glass-input text-[11px] font-medium tabular-nums" />
                                    <button wire:click="removePekerjaanRow({{ $idx }})" class="col-span-1 text-up-red hover:text-white text-sm cursor-pointer" title="Hapus baris">✕</button>
                                </div>
                                {{-- [F2-3] SN utk produk sn=true — wajib, jumlah = qty --}}
                                @if(($row['tipe'] ?? 'jasa') === 'part')
                                    <div class="mb-2">
                                        <input
                                            type="text"
                                            wire:model="pekerjaanItems.{{ $idx }}.sn"
                                            placeholder="Nomor seri (wajib utk produk SN — pisah koma/baris)"
                                            class="w-full px-2 py-2 rounded-lg glass-input text-[11px] font-mono"
                                        />
                                    </div>
                                @endif
                            @endforeach
                            <div class="flex flex-wrap gap-2">
                                <button wire:click="addPekerjaanRow" class="px-3 py-2 rounded-lg bg-white/5 hover:bg-white/10 text-ink-200 text-[11px] font-semibold border border-white/10 cursor-pointer">+ Baris</button>
                                <button wire:click="simpanPekerjaan" class="px-4 py-2 rounded-lg bg-up-primary hover:bg-up-primary-dark text-white text-[11px] font-bold cursor-pointer">Simpan Item Pekerjaan</button>
                            </div>
                            <p class="text-[10px] text-ink-500 mt-2">Part: stok dikurangi 1x + log. Jasa: hanya tagihan/jurnal. Item part menonaktifkan form sparepart legacy (anti dobel stok).</p>
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

    <!-- ===== MODAL: PEMBAYARAN KASIR SERVIS ===== -->
    @if($showBayarModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-lg glass-panel p-6 rounded-3xl border border-white/10 shadow-2xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <div>
                        <h3 class="text-lg font-bold text-white">Konfirmasi Bayar Online - #{{ $bayarTiketId }}</h3>
                        <p class="text-xs text-ink-400">Pencatatan pembayaran non-tunai / gateway online</p>
                    </div>
                    <button wire:click="$set('showBayarModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="space-y-4">
                    <!-- Info Banner Integrasi Kasir POS -->
                    <div class="p-3 rounded-xl bg-blue-500/10 border border-blue-500/20 text-xs text-blue-300">
                        Pembayaran tunai / di toko wajib melalui <a href="/app/pos?bayar_servis_id={{ $bayarTiketId }}" class="underline font-bold text-white hover:text-blue-200">Kasir POS</a> agar tercatat di sesi kas laci. Form ini khusus pelunasan online / transfer.
                    </div>

                    <!-- Ringkasan Total Tagihan -->
                    <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/5 flex items-center justify-between">
                        <span class="text-xs font-semibold text-ink-300">Total Tagihan Servis</span>
                        <span class="tabular-nums font-bold text-xl text-up-mint">
                            Rp {{ number_format($bayarTotalTagihan, 0, ',', '.') }}
                        </span>
                    </div>

                    <!-- Pemilihan Metode Pembayaran -->
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-2">Metode Pembayaran Online</label>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach([
                                'transfer' => 'Transfer Bank',
                                'qris' => 'QRIS',
                                'kartu' => 'Kartu Debit/Kredit'
                            ] as $metode => $label)
                                <button
                                    type="button"
                                    wire:click="$set('bayarMetode', '{{ $metode }}')"
                                    class="p-3 rounded-xl border text-left transition-all cursor-pointer {{ $bayarMetode === $metode ? 'bg-up-primary/20 border-up-primary text-white font-bold' : 'bg-white/[0.02] border-white/5 text-ink-300 hover:bg-white/[0.05]' }}"
                                >
                                    <div class="text-xs">{{ $label }}</div>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Catatan Opsional -->
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1">Catatan (opsional)</label>
                        <textarea
                            wire:model="bayarCatatan"
                            rows="2"
                            placeholder="Contoh: No referensi transfer / catatan kasir..."
                            class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white"
                        ></textarea>
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="flex gap-2 pt-2">
                        <button
                            type="button"
                            wire:click="$set('showBayarModal', false)"
                            class="flex-1 py-3 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 font-semibold text-xs transition-all cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            wire:click="prosesBayar"
                            class="flex-1 py-3 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs transition-all cursor-pointer"
                        >
                            Konfirmasi Pembayaran Lunas
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Riwayat Aktivitas & Log Mutasi --}}
    @include('partials.riwayat-modal')

    <!-- MODAL PENCARIAN PART SERVIS (inline, bukan sibling) -->
    @if($showCariPartModal)
        <div class="fixed inset-0 z-[70] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-xl glass-panel p-5 rounded-3xl border border-white/10 shadow-2xl relative max-h-[85vh] flex flex-col">
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-white/10 flex-shrink-0">
                    <div>
                        <h3 class="text-base font-bold text-white">Cari Produk / Part</h3>
                        <p class="text-[11px] text-ink-400">Ketik nama produk, barcode, atau tipe HP kompatibel</p>
                    </div>
                    <button wire:click="$set('showCariPartModal', false)" class="text-ink-400 hover:text-white cursor-pointer">✕</button>
                </div>

                <!-- Search Input -->
                <div class="mb-3 flex-shrink-0">
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <svg class="h-4 w-4 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <input type="text"
                               wire:model.live.debounce.300ms="cariPartQuery"
                               class="w-full pl-9 pr-3 py-2.5 rounded-xl glass-input text-xs font-medium"
                               placeholder="Ketik minimal 2 huruf..."
                               autofocus>
                    </div>
                </div>

                <!-- Product List -->
                <div class="overflow-y-auto flex-1 space-y-1 pr-1">
                    @forelse($cariPartResults as $product)
                        <button type="button"
                                wire:click="pilihPartServis({{ $product->id }})"
                                class="w-full flex items-center justify-between gap-3 p-2.5 rounded-xl bg-white/[0.03] hover:bg-white/[0.08] border border-white/5 hover:border-up-primary/30 transition-all cursor-pointer text-left group">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-white group-hover:text-up-primary truncate">{{ $product->nama }}</p>
                                <p class="text-[10px] text-ink-400 mt-0.5">
                                    @if($product->barcode)
                                        <span class="inline-flex items-center rounded bg-white/5 px-1.5 py-0.5 text-[9px] font-medium text-ink-300 mr-1">{{ $product->barcode }}</span>
                                    @endif
                                    @if($product->kategori)
                                        <span>{{ $product->kategori }}</span>
                                    @endif
                                </p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p class="text-xs font-bold text-up-mint tabular-nums">Rp {{ number_format($product->harga_jual_retail ?? 0, 0, ',', '.') }}</p>
                                <p class="text-[10px] text-ink-400">Beli: Rp {{ number_format($product->harga_beli ?? 0, 0, ',', '.') }}</p>
                            </div>
                        </button>
                    @empty
                        <div class="text-center py-8">
                            <svg class="mx-auto h-10 w-10 text-ink-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                            <p class="mt-2 text-xs text-ink-400">
                                @if(strlen($cariPartQuery) < 2)
                                    Ketik minimal 2 karakter untuk mulai mencari...
                                @else
                                    Tidak ada produk ditemukan untuk "{{ $cariPartQuery }}"
                                @endif
                            </p>
                        </div>
                    @endforelse
                </div>

                <div class="pt-3 mt-3 border-t border-white/10 flex justify-end flex-shrink-0">
                    <button type="button"
                            wire:click="$set('showCariPartModal', false)"
                            class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 font-semibold text-xs border border-white/10 cursor-pointer">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- [T-35] Modal Cetak Struk Servis Thermal 58mm / 80mm --}}
    @include('partials.thermal-receipt-modal')
</div>