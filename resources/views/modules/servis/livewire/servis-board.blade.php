<div class="flex flex-col h-[calc(100dvh-9.5rem)] md:h-[calc(100dvh-7.5rem)] max-h-[calc(100dvh-7.5rem)] overflow-hidden"
     x-data="{ dragId: null, overCol: null, photoIndex: 0, bookingOpen: false }">

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

            <!-- [Kanban Histori] Filter Periode Transaksi Diambil -->
            <select wire:model.live="periodeHistori" class="flex-1 sm:flex-initial px-3 py-2.5 rounded-xl glass-input text-xs font-medium min-h-[44px]" title="Filter Periode Histori Transaksi Diambil">
                <option value="7_hari" class="bg-ink-900">Histori: 7 Hari Terakhir</option>
                <option value="hari_ini" class="bg-ink-900">Histori: Hari Ini</option>
                <option value="30_hari" class="bg-ink-900">Histori: 30 Hari Terakhir</option>
                <option value="semua" class="bg-ink-900">Histori: Semua Transaksi</option>
            </select>

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
        <span class="text-[10px] font-mono text-ink-500">{{ count($stateMachineColumns) }} Kolom</span>
    </div>

    <!-- ===== ANTREAN BOOKING ONLINE ===== -->
    @if(count($bookingOnline ?? []) > 0)
        <div class="mb-2.5 flex-shrink-0 p-3 rounded-2xl bg-up-amber/10 border border-up-amber/30 transition-[opacity,transform] duration-140">
            <div class="flex items-center justify-between cursor-pointer select-none" @click="bookingOpen = !bookingOpen">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-up-amber animate-pulse"></span>
                    <h3 class="text-xs sm:text-sm font-bold text-up-amber">
                        Ada {{ count($bookingOnline) }} Pengajuan Servis Online Menunggu Konfirmasi
                    </h3>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="text-[11px] font-semibold text-up-amber hover:underline flex items-center gap-1 cursor-pointer">
                        <span x-text="bookingOpen ? 'Ciutkan Antrean ▲' : 'Buka Antrean ({{ count($bookingOnline) }}) ▼'"></span>
                    </button>
                </div>
            </div>
            <div x-show="bookingOpen" x-cloak class="mt-2.5 pt-2.5 border-t border-up-amber/20 max-h-56 overflow-y-auto overscroll-contain pr-1">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2.5">
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
                            <div class="pt-2 border-t border-white/5 flex items-center justify-end gap-1.5 flex-wrap">
                                <button
                                    type="button"
                                    wire:click="openIntakeBookingModal({{ $b->id }})"
                                    class="px-3 py-1.5 rounded-lg bg-up-primary hover:bg-up-primary-dark text-white text-xs font-semibold flex items-center gap-1.5 cursor-pointer active:scale-95 transition-[background-color,transform] duration-100 shadow-sm shadow-up-primary/30"
                                    title="Lakukan pemeriksaan fisik unit, cek kondisi, dan serah terima resmi"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Periksa & Terima Fisik
                                </button>
                                <button
                                    type="button"
                                    wire:click="konfirmasiBookingOnline({{ $b->id }})"
                                    class="px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-ink-300 hover:text-white text-xs font-medium border border-white/10 cursor-pointer active:scale-95 transition-[background-color,color,transform] duration-100"
                                    title="Terima langsung tanpa form inspeksi"
                                >
                                    Terima Cepat
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <!-- ===== KANBAN BOARD ===== -->
    <x-prism.dual-scroll class="flex-1 min-h-0 overflow-y-hidden pb-1 touch-pan-x">
        <div class="flex gap-3 sm:gap-4 h-full min-w-max px-0.5 items-start">
            @foreach($stateMachineColumns as $status => $col)
                <div class="w-[82vw] sm:w-[320px] lg:w-[280px] snap-center flex-shrink-0 flex flex-col rounded-2xl glass-panel overflow-hidden h-[calc(100dvh-13.5rem)] sm:h-[calc(100dvh-12.5rem)] lg:h-[calc(100dvh-11.5rem)] min-h-[380px]"
                     @dragover.prevent="overCol = '{{ $status }}'"
                     @dragleave="overCol = null"
                     @drop.prevent="if (dragId) { $wire.dropTicket(dragId, '{{ $status }}'); dragId = null; overCol = null; }">
                    <!-- Column Header -->
                    @php
                        $dotColor = match($col['color'] ?? '') {
                            'up-primary' => 'bg-up-primary',
                            'up-amber' => 'bg-up-amber',
                            'up-red' => 'bg-up-red',
                            'up-mint' => 'bg-up-mint',
                            'up-accent' => 'bg-up-accent',
                            'ink-100' => 'bg-ink-100',
                            default => 'bg-ink-400',
                        };
                    @endphp
                    <div class="px-4 py-3 flex items-center justify-between border-b border-white/5 flex-shrink-0"
                         :class="overCol === '{{ $status }}' ? 'bg-up-primary/10' : 'bg-white/[0.02]'">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full {{ $dotColor }}"></span>
                            <h3 class="text-xs font-bold text-white uppercase tracking-wider">{{ $col['label'] }}</h3>
                            <span class="text-[10px] font-mono text-ink-400 tabular-nums bg-white/5 px-1.5 py-0.5 rounded">
                                {{ ($groupedTikets[$status] ?? collect())->count() }}
                            </span>
                            @if($status === 'diambil')
                                <span class="text-[9px] font-semibold text-ink-300 bg-white/10 px-1.5 py-0.5 rounded border border-white/5" title="Filter Periode Histori Transaksi">
                                    {{ match($periodeHistori) {
                                        'hari_ini' => 'Hari Ini',
                                        '30_hari' => '30 Hari',
                                        'semua' => 'Semua',
                                        default => '7 Hari'
                                    } }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Column Cards -->
                    <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain p-2.5 space-y-2.5">
                        @forelse(($groupedTikets[$status] ?? collect()) as $tiket)
                            @php
                                $foto = $tiket->foto_unit[0] ?? null;
                                $nextStatuses = \App\Modules\Servis\Services\ServisStateMachine::nextValidStatuses($tiket->status);
                            @endphp
                            <div
                                x-data="{ dragging: false }"
                                class="group glass-panel glass-panel-hover rounded-xl p-3 cursor-pointer select-none transition-all duration-150 hover:border-up-primary/40 hover:shadow-lg hover:shadow-up-primary/5 active:cursor-grabbing"
                                draggable="true"
                                @dragstart="dragging = true; dragId = {{ $tiket->id }}"
                                @dragend="setTimeout(() => { dragging = false }, 100); dragId = null; overCol = null"
                                @click="if (!dragging) { $wire.openDetail({{ $tiket->id }}) }"
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
                                                    @canany(['pos.view', 'pos.view-own', 'pos.create'])
                                                    <a
                                                        href="/app/pos?bayar_servis_id={{ $tiket->id }}"
                                                        @click.stop
                                                        @mousedown.stop
                                                        draggable="false"
                                                        class="bg-up-primary text-white text-[10px] font-bold px-2 py-0.5 rounded-lg hover:bg-up-primary/80 cursor-pointer inline-flex items-center gap-1"
                                                        title="Bayar di Kasir POS"
                                                    >Bayar Kasir</a>
                                                    @endcanany
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
                                        @click.stop
                                        @mousedown.stop
                                        draggable="false"
                                        wire:click="openDetail({{ $tiket->id }})"
                                        class="text-ink-500 hover:text-up-primary text-sm transition-opacity flex-shrink-0 cursor-pointer p-0.5 rounded hover:bg-white/5"
                                        title="Lihat Detail Tiket"
                                    >
                                        ↗
                                    </button>
                                </div>

                                <!-- Keluhan -->
                                <p class="text-[11px] text-ink-300 line-clamp-2 mb-2 leading-snug">{{ $tiket->keluhan }}</p>

                                <!-- SLA / Batas Waktu Badge untuk status dikerjakan/qc -->
                                @if(in_array($tiket->status, ['dikerjakan', 'qc']))
                                    @if($tiket->pengerjaan_deadline_at)
                                        @php
                                            $isOverdue = now()->greaterThan($tiket->pengerjaan_deadline_at);
                                            $sisaMnt = max(0, (int) now()->diffInMinutes($tiket->pengerjaan_deadline_at, false));
                                        @endphp
                                        <div class="mb-2 p-1.5 rounded-lg {{ $isOverdue ? 'bg-up-red/15 border border-up-red/30' : ($sisaMnt <= 30 ? 'bg-up-amber/15 border border-up-amber/30 animate-pulse' : 'bg-up-primary/10 border border-up-primary/20') }} flex items-center justify-between text-[9px]">
                                            <span class="flex items-center gap-1 font-semibold {{ $isOverdue ? 'text-up-red' : ($sisaMnt <= 30 ? 'text-up-amber' : 'text-up-primary') }}">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                {{ $isOverdue ? 'Waktu Habis (Overdue)' : ($sisaMnt <= 30 ? "Segera Habis (~{$sisaMnt}m)" : "SLA: ~{$sisaMnt}m") }}
                                            </span>
                                            <button
                                                type="button"
                                                @click.stop
                                                @mousedown.stop
                                                draggable="false"
                                                wire:click="openProgressModal({{ $tiket->id }})"
                                                class="font-bold underline text-ink-200 hover:text-white cursor-pointer px-1 py-0.5 rounded hover:bg-white/10"
                                                title="Update progress pengerjaan teknisi"
                                            >
                                                + Update
                                            </button>
                                        </div>
                                    @else
                                        <div class="mb-2 p-1.5 rounded-lg bg-up-primary/10 border border-up-primary/20 flex items-center justify-between text-[9px]">
                                            <span class="flex items-center gap-1 font-medium text-up-primary">
                                                <span class="w-1.5 h-1.5 rounded-full bg-up-primary animate-pulse"></span>
                                                Progres Pengerjaan
                                            </span>
                                            <button
                                                type="button"
                                                @click.stop
                                                @mousedown.stop
                                                draggable="false"
                                                wire:click="openProgressModal({{ $tiket->id }})"
                                                class="font-bold text-up-primary hover:text-white underline cursor-pointer px-1 py-0.5 rounded hover:bg-up-primary/20"
                                                title="Update progress pengerjaan teknisi"
                                            >
                                                + Update
                                            </button>
                                        </div>
                                    @endif
                                @endif

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
                                @if($tiket->status === 'ditolak')
                                    <div class="flex flex-wrap gap-1.5 pt-2 border-t border-white/5">
                                        <button
                                            type="button"
                                            @click.stop
                                            @mousedown.stop
                                            draggable="false"
                                            wire:click="revisiEstimasiDitolak({{ $tiket->id }})"
                                            class="px-2.5 py-1 rounded-lg bg-up-primary hover:bg-up-primary-dark text-white font-bold text-[10px] transition-[background-color,transform] duration-100 active:scale-[0.98] cursor-pointer flex items-center gap-1 shadow-sm shadow-up-primary/20"
                                            title="Ubah status ke Diagnosa untuk ajukan revisi estimasi"
                                        >
                                            Revisi Estimasi
                                        </button>
                                        <button
                                            type="button"
                                            @click.stop
                                            @mousedown.stop
                                            draggable="false"
                                            wire:click="openBatalReturModal({{ $tiket->id }})"
                                            class="px-2.5 py-1 rounded-lg bg-up-red/10 hover:bg-up-red text-up-red hover:text-white font-bold text-[10px] border border-up-red/30 transition-[background-color,color,transform] duration-100 active:scale-[0.98] cursor-pointer flex items-center gap-1"
                                            title="Batalkan servis dan serahkan unit kembali ke pelanggan"
                                        >
                                            Kembalikan Unit
                                        </button>
                                    </div>
                                @elseif(count($nextStatuses) > 0)
                                    <div class="flex flex-wrap gap-1.5 pt-2 border-t border-white/5">
                                        @foreach($nextStatuses as $ns)
                                            @if($ns === 'menunggu_approval')
                                                <button
                                                    type="button"
                                                    @click.stop
                                                    @mousedown.stop
                                                    draggable="false"
                                                    wire:click="openEstimasiModal({{ $tiket->id }})"
                                                    class="px-2.5 py-1 rounded-lg bg-up-primary hover:bg-up-primary-dark text-white font-bold text-[10px] transition-[background-color,transform] duration-100 active:scale-[0.98] cursor-pointer"
                                                >+ Estimasi</button>
                                            @elseif($ns === 'disetujui' || $ns === 'ditolak')
                                                <button
                                                    type="button"
                                                    @click.stop
                                                    @mousedown.stop
                                                    draggable="false"
                                                    wire:click="openApproveModal({{ $tiket->id }})"
                                                    class="px-2.5 py-1 rounded-lg {{ $ns === 'disetujui' ? 'bg-up-mint hover:opacity-90 text-ink-950' : 'bg-up-red hover:opacity-90 text-white' }} font-bold text-[10px] transition-[opacity,transform] duration-100 active:scale-[0.98] cursor-pointer"
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
                                                    @click.stop
                                                    @mousedown.stop
                                                    draggable="false"
                                                    wire:click="updateStatus({{ $tiket->id }}, '{{ $ns }}')"
                                                    class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-ink-200 hover:text-white font-semibold text-[10px] border border-white/10 transition-[background-color,color,transform] duration-100 active:scale-[0.98] cursor-pointer"
                                                >{{ $label }}</button>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                                @if($status === 'diambil' && ($tiket->tanggal_diambil || $tiket->updated_at))
                                    <div class="text-[9px] text-ink-400 pt-1.5 border-t border-white/5 flex items-center justify-between">
                                        <span class="text-ink-500">Selesai diambil:</span>
                                        <span class="font-medium text-ink-300 tabular-nums">
                                            {{ ($tiket->tanggal_diambil ?? $tiket->updated_at)->format('d/m/Y H:i') }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="text-center py-8 text-[10px] text-ink-500">
                                <svg class="w-6 h-6 mx-auto mb-1.5 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                @if($status === 'diambil')
                                    <p>Tidak ada riwayat diambil<br><span class="text-ink-400">({{ match($periodeHistori) {
                                        'hari_ini' => 'Hari Ini',
                                        '30_hari' => '30 Hari Terakhir',
                                        'semua' => 'Semua',
                                        default => '7 Hari Terakhir'
                                    } }})</span></p>
                                @else
                                    <p>Kosong — tarik kartu<br>atau terima unit baru</p>
                                @endif
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </x-prism.dual-scroll>

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
                        <select wire:model.live="terimaForm.tipe_kunci" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                            <option value="" class="bg-ink-900">— Tidak ada / default —</option>
                            <option value="pola" class="bg-ink-900">Pola (Swipe / Geser Pola)</option>
                            <option value="pin" class="bg-ink-900">PIN (4-6 digit)</option>
                            <option value="password" class="bg-ink-900">Password</option>
                            <option value="tidak_ada" class="bg-ink-900">Tidak ada</option>
                        </select>

                        @if(($terimaForm['tipe_kunci'] ?? '') === 'pola')
                            <div class="mt-3">
                                <x-pattern-lock wire-model="terimaForm.kunci_terenkripsi" :dark="true" />
                            </div>
                        @elseif(($terimaForm['tipe_kunci'] ?? '') === 'pin' || ($terimaForm['tipe_kunci'] ?? '') === 'password')
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
                                        'px-3 py-1.5 rounded-lg text-[11px] font-semibold border transition-[background-color,color,border-color,transform] duration-100 cursor-pointer active:scale-95',
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

                    <!-- ===== [ESTIMASI BIAYA LANGSUNG DI PENERIMAAN] ===== -->
                    <div class="pt-3 border-t border-white/10 space-y-3">
                        <div class="flex items-center justify-between p-3 rounded-2xl bg-white/[0.03] border border-white/5">
                            <div class="flex items-center gap-2.5">
                                <span class="w-8 h-8 rounded-xl bg-up-primary/20 text-up-primary flex items-center justify-center font-bold text-sm">Rp</span>
                                <div>
                                    <div class="text-xs font-bold text-white flex items-center gap-1.5">
                                        Sertakan Estimasi Biaya Langsung
                                        <span class="text-[9px] px-1.5 py-0.5 rounded bg-up-mint/15 text-up-mint font-semibold uppercase tracking-wider">Cepat</span>
                                    </div>
                                    <div class="text-[10px] text-ink-400">Rincikan jasa/part di awal agar konsumen bisa langsung menyetujui biaya.</div>
                                </div>
                            </div>
                            <button
                                type="button"
                                wire:click="toggleTerimaEstimasi"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors cursor-pointer {{ $terimaSertakanEstimasi ? 'bg-up-primary' : 'bg-white/10' }}"
                            >
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $terimaSertakanEstimasi ? 'translate-x-6' : 'translate-x-1' }}"></span>
                            </button>
                        </div>

                        @if($terimaSertakanEstimasi)
                            <div class="p-3.5 rounded-2xl bg-white/[0.02] border border-white/10 space-y-3 animate-fade-in">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-ink-200 uppercase tracking-wider">Item Estimasi Awal</span>
                                    <div class="flex gap-2">
                                        <button
                                            type="button"
                                            wire:click="addTerimaEstimasiRow('jasa')"
                                            class="px-2.5 py-1 rounded-lg bg-up-mint/10 hover:bg-up-mint/20 text-up-mint text-[11px] font-semibold border border-up-mint/30 cursor-pointer"
                                        >+ Jasa</button>
                                        <button
                                            type="button"
                                            wire:click="addTerimaEstimasiRow('part')"
                                            class="px-2.5 py-1 rounded-lg bg-up-primary/10 hover:bg-up-primary/20 text-up-primary text-[11px] font-semibold border border-up-primary/30 cursor-pointer"
                                        >+ Part</button>
                                    </div>
                                </div>

                                <div class="space-y-2.5">
                                    @forelse($terimaEstimasiItems as $idx => $row)
                                        <div class="p-2.5 rounded-xl bg-white/[0.03] border border-white/5 space-y-2">
                                            <div class="grid grid-cols-12 gap-2 items-center">
                                                <div class="col-span-2">
                                                    <span @class([
                                                        'inline-block w-full text-center py-1 text-[10px] font-bold rounded-lg border uppercase',
                                                        'bg-up-mint/10 text-up-mint border-up-mint/30' => ($row['tipe'] ?? '') === 'jasa',
                                                        'bg-up-primary/10 text-up-primary border-up-primary/30' => ($row['tipe'] ?? '') === 'part',
                                                    ])>
                                                        {{ $row['tipe'] ?? 'jasa' }}
                                                    </span>
                                                </div>

                                                <div class="col-span-4">
                                                    @if(($row['tipe'] ?? '') === 'part')
                                                        <div class="relative">
                                                            <input type="text"
                                                                   wire:model="terimaEstimasiItems.{{ $idx }}.produk_nama"
                                                                   wire:click="bukaPencarianProduk({{ $idx }}, 'servis_terima_estimasi')"
                                                                   class="w-full px-2 py-1.5 pr-7 rounded-lg glass-input text-xs font-medium cursor-pointer"
                                                                   placeholder="Cari part..."
                                                                   readonly>
                                                            @if($row['produk_id'])
                                                                <button type="button"
                                                                        wire:click="resetTerimaEstimasiPartRow({{ $idx }})"
                                                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-ink-400 hover:text-white"
                                                                        title="Hapus part">
                                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                                    </svg>
                                                                </button>
                                                            @else
                                                                <button type="button"
                                                                        wire:click="bukaPencarianProduk({{ $idx }}, 'servis_terima_estimasi')"
                                                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-ink-400 hover:text-white cursor-pointer"
                                                                        title="Cari Part">
                                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                                                    </svg>
                                                                </button>
                                                            @endif
                                                        </div>
                                                    @else
                                                        <select
                                                            wire:model.live="terimaEstimasiItems.{{ $idx }}.jenis_servis_id"
                                                            class="w-full px-2 py-1.5 rounded-lg glass-input text-xs font-medium"
                                                        >
                                                            <option value="" class="bg-ink-900">— Template Jasa —</option>
                                                            @foreach($jenisServisList as $j)
                                                                <option value="{{ $j->id }}" class="bg-ink-900">
                                                                    {{ $j->nama }} (Rp {{ number_format($j->biaya_jasa ?? 0, 0, ',', '.') }})
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    @endif
                                                </div>

                                                <div class="col-span-5">
                                                    <input
                                                        type="text"
                                                        wire:model="terimaEstimasiItems.{{ $idx }}.nama_item"
                                                        placeholder="Deskripsi item..."
                                                        class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs font-medium"
                                                    />
                                                </div>

                                                <div class="col-span-1 text-right">
                                                    <button
                                                        type="button"
                                                        wire:click="removeTerimaEstimasiRow({{ $idx }})"
                                                        class="text-up-red hover:text-white text-sm cursor-pointer p-1"
                                                        title="Hapus baris"
                                                    >✕</button>
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-12 gap-2 items-center pl-2 border-t border-white/5 pt-1.5 text-xs">
                                                <div class="col-span-3 flex items-center gap-1.5">
                                                    <span class="text-[10px] text-ink-400">Qty:</span>
                                                    <input
                                                        type="number"
                                                        min="1"
                                                        wire:model.live.debounce.300ms="terimaEstimasiItems.{{ $idx }}.qty"
                                                        class="w-16 px-2 py-1 rounded-lg glass-input text-xs font-bold text-center"
                                                    />
                                                </div>
                                                <div class="col-span-4 flex items-center gap-1.5">
                                                    <span class="text-[10px] text-ink-400">Harga:</span>
                                                    <input
                                                        type="text"
                                                        inputmode="numeric"
                                                        x-format-number
                                                        wire:model.live.debounce.350ms="terimaEstimasiItems.{{ $idx }}.harga"
                                                        placeholder="0"
                                                        @if(($row['tipe'] ?? '') === 'part' && ! $canEditHargaPart) readonly @endif
                                                        class="w-full px-2 py-1 rounded-lg glass-input text-xs font-bold tabular-nums {{ (($row['tipe'] ?? '') === 'part' && ! $canEditHargaPart) ? 'opacity-80 bg-white/5 cursor-not-allowed' : '' }}"
                                                    />
                                                </div>
                                                <div class="col-span-5 text-right font-bold text-white tabular-nums">
                                                    <span class="text-[10px] text-ink-400 font-normal mr-1">Subtotal:</span>
                                                    Rp {{ number_format($row['subtotal'] ?? 0, 0, ',', '.') }}
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-center py-3 text-xs text-ink-400">
                                            Belum ada baris item estimasi. Klik "+ Jasa" atau "+ Part" di atas.
                                        </div>
                                    @endforelse
                                </div>

                                <div class="flex items-center justify-between pt-3 border-t border-white/10">
                                    <span class="text-xs font-bold text-ink-300">Total Estimasi Awal</span>
                                    <div class="text-right">
                                        <span class="text-base font-bold text-up-mint tabular-nums">
                                            Rp {{ number_format($terimaEstimasiBiaya, 0, ',', '.') }}
                                        </span>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-ink-300 mb-1">Catatan Estimasi <span class="text-ink-500 font-normal">(opsional)</span></label>
                                    <input type="text" wire:model="terimaEstimasiAlasan" placeholder="Catatan estimasi untuk konsumen (misal: pengerjaan 1 hari)..." class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium" />
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 p-2.5 rounded-xl bg-white/[0.02] border border-white/5">
                                    <div>
                                        <label class="block text-[10px] font-semibold text-ink-400 mb-1">SLA Batas Waktu (Menit):</label>
                                        <input type="number" min="15" step="15" wire:model="terimaEstimasiBatasWaktu" placeholder="120" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs font-bold" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-semibold text-ink-400 mb-1">Opsi Overdue:</label>
                                        <select wire:model="terimaEstimasiAutoCancel" class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs font-medium">
                                            <option value="0" class="bg-ink-900">Peringatan Manual</option>
                                            <option value="1" class="bg-ink-900">Auto-Cancel Sistem</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="p-2.5 rounded-xl bg-white/[0.03] border border-white/5 space-y-1.5">
                                    <label class="block text-[11px] font-semibold text-ink-300">Status Persetujuan Awal Konsumen:</label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <label class="flex items-center gap-2 p-2 rounded-xl border cursor-pointer text-xs transition-colors {{ $terimaEstimasiMode === 'menunggu_approval' ? 'bg-up-amber/15 border-up-amber text-up-amber font-semibold' : 'bg-white/[0.02] border-white/5 text-ink-300' }}">
                                            <input type="radio" value="menunggu_approval" wire:model.live="terimaEstimasiMode" class="sr-only" />
                                            <span>&#9203; Menunggu Approval (Kirim Link)</span>
                                        </label>
                                        <label class="flex items-center gap-2 p-2 rounded-xl border cursor-pointer text-xs transition-colors {{ $terimaEstimasiMode === 'disetujui' ? 'bg-up-mint/15 border-up-mint text-up-mint font-semibold' : 'bg-white/[0.02] border-white/5 text-ink-300' }}">
                                            <input type="radio" value="disetujui" wire:model.live="terimaEstimasiMode" class="sr-only" />
                                            <span>&#10003; Langsung Disetujui di Konter</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        @endif
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
                                                @if(($row['tipe'] ?? '') === 'part' && ! $canEditHargaPart) readonly @endif
                                                class="w-full px-2 py-1 rounded-lg glass-input text-xs font-bold tabular-nums {{ (($row['tipe'] ?? '') === 'part' && ! $canEditHargaPart) ? 'opacity-80 bg-white/5 cursor-not-allowed' : '' }}"
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

                    <!-- Konfigurasi SLA & Batas Waktu Pengerjaan -->
                    <div class="p-3.5 rounded-2xl bg-white/[0.02] border border-white/5 space-y-3">
                        <span class="text-xs font-bold text-ink-200 uppercase tracking-wider flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-up-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            SLA & Batas Waktu Pengerjaan Teknisi
                        </span>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-center">
                            <div>
                                <label class="block text-[11px] font-medium text-ink-300 mb-1">Batas Waktu (Menit)</label>
                                <input
                                    type="number"
                                    min="15"
                                    step="15"
                                    wire:model="estimasiBatasWaktuMenit"
                                    placeholder="Contoh: 120"
                                    class="w-full px-3 py-2 rounded-xl glass-input text-xs font-bold"
                                />
                                <span class="text-[10px] text-ink-500 mt-0.5 block">Diambil dari master jasa atau sesuaikan</span>
                            </div>
                            <div>
                                <label class="block text-[11px] font-medium text-ink-300 mb-1">Garansi Custom (Hari)</label>
                                <input
                                    type="number"
                                    min="0"
                                    wire:model="estimasiDurasiGaransiCustom"
                                    placeholder="Default master (cth: 30)"
                                    class="w-full px-3 py-2 rounded-xl glass-input text-xs font-bold"
                                />
                                <span class="text-[10px] text-ink-500 mt-0.5 block">Kosongkan utk default. Khusus jasa: tanpa garansi.</span>
                            </div>
                            <div>
                                <label class="block text-[11px] font-medium text-ink-300 mb-1">Jika Batas Waktu Habis</label>
                                <select wire:model="estimasiAutoCancelOverdue" class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium">
                                    <option value="0" class="bg-ink-900">Peringatan Manual (Tetap Berjalan)</option>
                                    <option value="1" class="bg-ink-900">Auto-Cancel & Beritahu Konsumen</option>
                                </select>
                                <span class="text-[10px] text-ink-500 mt-0.5 block">Opsi proteksi kepastian waktu pelanggan</span>
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

    <!-- ===== MODAL: INPUT PROGRESS PENGERJAAN TEKNISI ===== -->
    @if($showProgressModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-lg glass-panel p-6 rounded-3xl border border-white/10 shadow-2xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <div>
                        <h3 class="text-lg font-bold text-white">Unggah Progres Pengerjaan</h3>
                        <p class="text-xs text-ink-400">Pembaruan progres kerja teknisi & transparansi ke konsumen</p>
                    </div>
                    <button wire:click="$set('showProgressModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-semibold text-ink-300">Persentase Selesai</label>
                            <span class="text-xs font-bold text-up-mint tabular-nums">{{ $progressPersentase }}%</span>
                        </div>
                        <input type="range" min="5" max="100" step="5" wire:model.live="progressPersentase" class="w-full accent-up-primary cursor-pointer">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Judul Tahapan / Progres <span class="text-up-red">*</span></label>
                        <input type="text" wire:model="progressJudul" placeholder="Contoh: Pembongkaran unit & tes modul LCD baru..." class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" />
                        @error('progressJudul') <span class="text-[10px] text-up-red">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Deskripsi / Catatan Pengerjaan</label>
                        <textarea wire:model="progressDeskripsi" rows="2" placeholder="Catatan teknis hasil pengecekan..." class="w-full px-3 py-2 rounded-xl glass-input text-xs font-medium"></textarea>
                    </div>

                    <!-- Upload Foto Progress -->
                    <div x-data="{
                        pickFile() {
                            this.$refs.fileInput.click();
                        },
                        fileSelected(e) {
                            const file = e.target.files[0];
                            if (!file) return;
                            const reader = new FileReader();
                            reader.onload = (event) => {
                                $wire.handleProgressFotoUpload(event.target.result);
                            };
                            reader.readAsDataURL(file);
                        }
                    }">
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Dokumentasi Foto Pengerjaan</label>
                        <input type="file" x-ref="fileInput" accept="image/*" class="hidden" @change="fileSelected">

                        <div class="flex flex-wrap gap-2 items-center">
                            @foreach($progressFoto as $fIdx => $fotoData)
                                <div class="relative w-16 h-16 rounded-xl overflow-hidden border border-white/10 group">
                                    <img src="{{ $fotoData }}" class="w-full h-full object-cover">
                                    <button type="button" wire:click="removeProgressFoto({{ $fIdx }})" class="absolute inset-0 bg-black/60 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity text-xs font-bold">
                                        ✕
                                    </button>
                                </div>
                            @endforeach

                            <button type="button" @click="pickFile" class="w-16 h-16 rounded-xl border border-dashed border-white/20 hover:border-up-primary bg-white/5 hover:bg-white/10 flex flex-col items-center justify-center text-ink-400 hover:text-white transition-[background-color,border-color,color] duration-140 cursor-pointer">
                                <span class="text-base leading-none font-bold">+</span>
                                <span class="text-[9px] mt-0.5">Foto</span>
                            </button>
                        </div>
                    </div>

                    <div class="pt-2">
                        <label class="flex items-center gap-2 cursor-pointer text-xs text-ink-200">
                            <input type="checkbox" wire:model="progressTampilkanKonsumen" class="rounded border-white/10 text-up-primary focus:ring-0">
                            <span>Tampilkan di halaman web tracking publik konsumen</span>
                        </label>
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button wire:click="$set('showProgressModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button wire:click="simpanProgress" class="flex-1 py-3 rounded-xl bg-up-primary text-white font-bold text-xs shadow-lg shadow-up-primary/25 cursor-pointer min-h-[44px]">Simpan Progres</button>
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
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Pilih Alasan Penolakan Cepat</label>
                        <div class="space-y-1 mb-2.5">
                            @foreach([
                                'Pelanggan menolak harga / biaya melebihi anggaran',
                                'Pelanggan ingin mempertimbangkan / membandingkan dulu',
                                'Estimasi waktu pengerjaan terlalu lama',
                                'Pelanggan memutuskan untuk mengganti unit baru',
                            ] as $opsiReject)
                                <button
                                    type="button"
                                    wire:click="$set('approveAlasan', '{{ $opsiReject }}')"
                                    class="w-full text-left p-2 rounded-xl bg-white/[0.03] hover:bg-white/10 border border-white/5 text-[11px] text-ink-200 transition-colors cursor-pointer flex items-center justify-between"
                                >
                                    <span>{{ $opsiReject }}</span>
                                    <span class="text-[10px] text-up-primary">Pilih</span>
                                </button>
                            @endforeach
                        </div>

                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Rincian Alasan <span class="text-ink-500 font-normal">(wajib jika tolak)</span></label>
                        <textarea wire:model="approveAlasan" rows="2" placeholder="Ex: Pelanggan setuju via konfirmasi telepon, atau: pelanggan menolak harga..." class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium"></textarea>
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
                        @if(in_array($selectedTiket->status, ['dikerjakan', 'qc']))
                            <button
                                type="button"
                                wire:click="openProgressModal({{ $selectedTiket->id }})"
                                class="text-[10px] font-bold text-white bg-up-primary hover:bg-up-primary-dark border border-up-primary/40 px-2.5 py-0.5 rounded-full cursor-pointer flex items-center gap-1 shadow-sm shadow-up-primary/20 transition-all duration-100 active:scale-95"
                                title="Catat catatan / foto progres pengerjaan untuk konsumen"
                            >
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                <span>+ Update Progres</span>
                            </button>
                        @endif
                    </div>
                    <button wire:click="$set('selectedTiketId', null)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="overflow-y-auto pr-1 flex-1 space-y-5">
                    <!-- Banner & Workflow Estimasi Ditolak -->
                    @if($selectedTiket->status === 'ditolak')
                        <div class="p-4 rounded-2xl bg-up-red/10 border border-up-red/30 space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full bg-up-red animate-pulse"></span>
                                        <h4 class="text-sm font-bold text-up-red">Estimasi Servis Ditolak oleh Pelanggan</h4>
                                    </div>
                                    <p class="text-xs text-ink-300 mt-1">
                                        Alasan: <span class="font-semibold text-white">{{ $selectedTiket->alasan_ditolak ?? 'Pelanggan belum menyetujui biaya / rincian estimasi' }}</span>
                                    </p>
                                    <p class="text-[11px] text-ink-400 mt-0.5">
                                        Pilih tindak lanjut: Ajukan revisi estimasi alternatif (kembali ke diagnosa), atau batalkan servis dan serahkan unit kembali ke pemilik.
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 pt-2 border-t border-white/10 flex-wrap">
                                <button
                                    type="button"
                                    wire:click="revisiEstimasiDitolak({{ $selectedTiket->id }})"
                                    class="px-3 py-1.5 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white text-xs font-bold flex items-center gap-1.5 cursor-pointer shadow-sm shadow-up-primary/30 transition-[background-color,transform] duration-100 active:scale-95"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    Revisi Estimasi (Diagnosa Ulang)
                                </button>
                                <button
                                    type="button"
                                    wire:click="openBatalReturModal({{ $selectedTiket->id }})"
                                    class="px-3 py-1.5 rounded-xl bg-up-red/15 hover:bg-up-red/25 text-up-red border border-up-red/30 text-xs font-bold flex items-center gap-1.5 cursor-pointer transition-[background-color,color,transform] duration-100 active:scale-95"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"></path></svg>
                                    Batalkan Servis & Kembalikan Unit (Pengambilan)
                                </button>
                            </div>
                        </div>
                    @endif

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
                            @php
                                $telpPelanggan = $selectedTiket->telepon_pelanggan ?? $selectedTiket->pelanggan?->telepon;
                            @endphp
                            @if($telpPelanggan)
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <span class="text-[11px] text-ink-400 font-mono">{{ $telpPelanggan }}</span>
                                    <a
                                        href="https://wa.me/{{ \App\Support\WhatsAppHelper::formatNomor($telpPelanggan) }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-[#25D366] hover:underline text-[10px] inline-flex items-center gap-0.5"
                                        title="Buka Chat WhatsApp"
                                    >
                                        <span>WA</span>
                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                    </a>
                                </div>
                            @else
                                <p class="text-[11px] text-ink-500">-</p>
                            @endif
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
                        <div class="flex items-center gap-2 flex-wrap">
                            @php
                                $telpTiket = $selectedTiket->telepon_pelanggan ?? $selectedTiket->pelanggan?->telepon;
                                $namaTiket = $selectedTiket->nama_pelanggan ?? $selectedTiket->pelanggan?->nama ?? 'Pelanggan';
                                $waTiketLink = null;
                                if ($telpTiket) {
                                    $tokenTiket = $selectedTiket->token_approval ?? '';
                                    if ($selectedTiket->status === 'menunggu_approval' && $selectedTiket->estimasi_biaya) {
                                        $draftWa = \App\Support\WhatsAppHelper::draftEstimasiServis(
                                            $namaTiket,
                                            $selectedTiket->no_tiket,
                                            $selectedTiket->jenis_hp,
                                            (float) $selectedTiket->estimasi_biaya,
                                            $selectedTiket->alasan_estimasi ?? 'Pengerjaan modul',
                                            $tokenTiket
                                        );
                                        $waTiketLink = \App\Support\WhatsAppHelper::buatLink($telpTiket, $draftWa);
                                        $waLabel = 'Kirim Approval WA';
                                    } elseif (in_array($selectedTiket->status, ['selesai', 'diambil'], true)) {
                                        $draftWa = \App\Support\WhatsAppHelper::draftServisSelesai(
                                            $namaTiket,
                                            $selectedTiket->no_tiket,
                                            $selectedTiket->jenis_hp,
                                            (float) ($selectedTiket->biaya_total ?? $selectedTiket->estimasi_biaya ?? 0),
                                            $tokenTiket
                                        );
                                        $waTiketLink = \App\Support\WhatsAppHelper::buatLink($telpTiket, $draftWa);
                                        $waLabel = 'Kirim Kabar Selesai WA';
                                    } elseif ($tokenTiket) {
                                        $draftWa = \App\Support\WhatsAppHelper::draftTerimaServis(
                                            $namaTiket,
                                            $selectedTiket->no_tiket,
                                            $selectedTiket->jenis_hp,
                                            $tokenTiket
                                        );
                                        $waTiketLink = \App\Support\WhatsAppHelper::buatLink($telpTiket, $draftWa);
                                        $waLabel = 'Kirim Nota WA';
                                    }
                                }
                            @endphp

                            @if($waTiketLink)
                                <a
                                    href="{{ $waTiketLink }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="px-3.5 py-2 rounded-xl bg-[#25D366] hover:bg-[#20ba59] text-white font-bold text-xs transition-[background-color,transform] duration-100 active:scale-[0.98] cursor-pointer inline-flex items-center gap-1.5 shadow-sm no-underline"
                                    title="Kirim notifikasi via WhatsApp langsung (HP / Web)"
                                >
                                    <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86.174.086.275.073.376-.044.101-.116.433-.506.549-.68.116-.173.231-.144.39-.086s1.011.477 1.184.564.289.13.332.203c.043.072.043.419-.101.824z"/></svg>
                                    <span>{{ $waLabel ?? 'Kirim WA' }}</span>
                                </a>
                            @endif

                            <button
                                type="button"
                                wire:click="bukaStrukServis({{ $selectedTiket->id }})"
                                class="px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-ink-200 hover:text-white border border-white/10 font-bold text-xs transition-[background-color,color,transform] duration-100 active:scale-[0.98] cursor-pointer inline-flex items-center gap-1.5"
                                title="Cetak struk thermal 58mm / nota servis"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                <span>Cetak Struk</span>
                            </button>
                        @if($selectedTiket->status_pembayaran !== 'lunas' && in_array($selectedTiket->status, ['selesai', 'diambil'], true))
                            @canany(['pos.view', 'pos.view-own', 'pos.create'])
                            <a
                                href="/app/pos?bayar_servis_id={{ $selectedTiket->id }}"
                                class="px-4 py-2 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs transition-[background-color,transform] duration-100 active:scale-[0.98] cursor-pointer self-start sm:self-auto inline-flex items-center gap-1.5 shadow-sm"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                <span>Bayar di Kasir POS</span>
                            </a>
                            @endcanany
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
                                    $terjemahan = $selectedTiket->terjemahan_pola;
                                    $polaRaw = explode('-', str_replace(' ', '', (string) $selectedTiket->kunci_terenkripsi));
                                    $polaOrder = [];
                                    foreach ($polaRaw as $urutan => $titik) {
                                        $t = (int) $titik;
                                        if ($t >= 1 && $t <= 9 && !isset($polaOrder[$t])) {
                                            $polaOrder[$t] = $urutan + 1;
                                        }
                                    }

                                    // Koordinat titik 1..9 untuk SVG (viewBox 300x300)
                                    $coords = [
                                        1 => [50, 50],   2 => [150, 50],  3 => [250, 50],
                                        4 => [50, 150],  5 => [150, 150], 6 => [250, 150],
                                        7 => [50, 250],  8 => [150, 250], 9 => [250, 250],
                                    ];

                                    $polylinePts = [];
                                    foreach ($polaRaw as $titik) {
                                        $t = (int) $titik;
                                        if (isset($coords[$t])) {
                                            $polylinePts[] = $coords[$t][0] . ',' . $coords[$t][1];
                                        }
                                    }
                                    $polyString = implode(' ', $polylinePts);
                                @endphp
                                <div class="mt-3 p-3.5 rounded-2xl bg-white/[0.02] border border-white/10 space-y-3">
                                    <p class="text-[10px] text-ink-400 font-bold uppercase tracking-wider">Visual Pola (Urutan Sentuh):</p>
                                    
                                    <div class="flex flex-col sm:flex-row gap-4 items-center">
                                        <!-- SVG Visual Pola dengan Jalur Penghubung -->
                                        <div class="w-48 h-48 rounded-2xl bg-ink-950 border-2 border-up-primary/40 shadow-xl p-2 flex-shrink-0">
                                            <svg viewBox="0 0 300 300" class="w-full h-full">
                                                <defs>
                                                    <filter id="detail-glow-pola" x="-20%" y="-20%" width="140%" height="140%">
                                                        <feDropShadow dx="0" dy="0" stdDeviation="3" flood-color="#8B5CF6" flood-opacity="0.8"/>
                                                    </filter>
                                                </defs>
                                                <!-- Grid background lines -->
                                                <line x1="50" y1="50" x2="250" y2="50" stroke="rgba(255, 255, 255, 0.15)" stroke-dasharray="4,4" />
                                                <line x1="50" y1="150" x2="250" y2="150" stroke="rgba(255, 255, 255, 0.15)" stroke-dasharray="4,4" />
                                                <line x1="50" y1="250" x2="250" y2="250" stroke="rgba(255, 255, 255, 0.15)" stroke-dasharray="4,4" />
                                                <line x1="50" y1="50" x2="50" y2="250" stroke="rgba(255, 255, 255, 0.15)" stroke-dasharray="4,4" />
                                                <line x1="150" y1="50" x2="150" y2="250" stroke="rgba(255, 255, 255, 0.15)" stroke-dasharray="4,4" />
                                                <line x1="250" y1="50" x2="250" y2="250" stroke="rgba(255, 255, 255, 0.15)" stroke-dasharray="4,4" />

                                                @if(!empty($polyString))
                                                    <polyline
                                                        points="{{ $polyString }}"
                                                        fill="none"
                                                        stroke="#8B5CF6"
                                                        stroke-width="7"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        filter="url(#detail-glow-pola)"
                                                    />
                                                @endif
                                                @for($i = 1; $i <= 9; $i++)
                                                    @php
                                                        $cx = $coords[$i][0];
                                                        $cy = $coords[$i][1];
                                                        $order = $polaOrder[$i] ?? null;
                                                    @endphp
                                                    <g transform="translate({{ $cx }}, {{ $cy }})">
                                                        <circle
                                                            r="24"
                                                            fill="{{ $order !== null ? 'rgba(139, 92, 246, 0.35)' : 'rgba(255, 255, 255, 0.08)' }}"
                                                            stroke="{{ $order !== null ? '#8B5CF6' : 'rgba(255, 255, 255, 0.3)' }}"
                                                            stroke-width="2.5"
                                                        />
                                                        <circle
                                                            r="10"
                                                            fill="{{ $order !== null ? '#FFFFFF' : 'rgba(255, 255, 255, 0.7)' }}"
                                                            stroke="{{ $order !== null ? '#7C3AED' : 'none' }}"
                                                            stroke-width="2"
                                                        />
                                                        @if($order !== null)
                                                            <g transform="translate(14, -14)">
                                                                <circle r="11" fill="#EA580C" stroke="#FFFFFF" stroke-width="1.5" />
                                                                <text text-anchor="middle" dy="4" fill="#FFFFFF" font-size="11" font-weight="bold">{{ $order }}</text>
                                                            </g>
                                                        @else
                                                            <text text-anchor="middle" dy="4" fill="rgba(255, 255, 255, 0.5)" font-size="11" font-weight="bold">{{ $i }}</text>
                                                        @endif
                                                    </g>
                                                @endfor
                                            </svg>
                                        </div>

                                        <!-- Terjemahan Langsung di Bawah/Samping -->
                                        <div class="flex-1 w-full space-y-2">
                                            <div class="p-2 rounded-xl bg-white/[0.03] border border-white/5">
                                                <span class="text-[9px] font-bold text-ink-400 uppercase tracking-wider">Urutan Nomor Titik</span>
                                                <p class="text-xs font-mono font-bold text-up-primary mt-0.5">{{ $terjemahan['urutan'] ?? $selectedTiket->kunci_terenkripsi }}</p>
                                            </div>
                                            <div class="p-2 rounded-xl bg-white/[0.03] border border-white/5">
                                                <span class="text-[9px] font-bold text-ink-400 uppercase tracking-wider">Alur Posisi</span>
                                                <p class="text-[11px] font-medium text-ink-200 mt-0.5 leading-snug">{{ $terjemahan['posisi'] ?? '-' }}</p>
                                            </div>
                                            <div class="p-2 rounded-xl bg-white/[0.03] border border-white/5">
                                                <span class="text-[9px] font-bold text-ink-400 uppercase tracking-wider">Arah Gerak</span>
                                                <p class="text-[11px] font-semibold text-up-mint mt-0.5 leading-snug">{{ $terjemahan['arah'] ?? '-' }}</p>
                                            </div>
                                        </div>
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

                    <!-- Status SLA & Batas Waktu Pengerjaan Teknisi -->
                    @if($selectedTiket->batas_waktu_menit || $selectedTiket->pengerjaan_mulai_at)
                        <div class="p-3.5 rounded-xl {{ $selectedTiket->pengerjaan_deadline_at && now()->greaterThan($selectedTiket->pengerjaan_deadline_at) && in_array($selectedTiket->status, ['dikerjakan', 'qc']) ? 'bg-up-red/10 border border-up-red/30' : 'bg-up-primary/10 border border-up-primary/30' }}">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-bold uppercase tracking-wider {{ $selectedTiket->pengerjaan_deadline_at && now()->greaterThan($selectedTiket->pengerjaan_deadline_at) && in_array($selectedTiket->status, ['dikerjakan', 'qc']) ? 'text-up-red' : 'text-up-primary' }}">
                                    KPI & Batas Waktu Pengerjaan Teknisi (SLA)
                                </span>
                                @if(in_array($selectedTiket->status, ['dikerjakan', 'qc']))
                                    <button
                                        type="button"
                                        wire:click="openProgressModal({{ $selectedTiket->id }})"
                                        class="px-2.5 py-1 rounded-lg bg-up-primary hover:bg-up-primary-dark text-white font-bold text-[10px] shadow cursor-pointer transition-[background-color,transform] duration-100 active:scale-95"
                                    >
                                        + Unggah Progress & Foto
                                    </button>
                                @endif
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                                <div>
                                    <span class="text-[10px] text-ink-400 block">SLA Maksimal:</span>
                                    <span class="font-bold text-white">{{ $selectedTiket->batas_waktu_menit ?? '-' }} Menit</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-ink-400 block">Mulai Pengerjaan:</span>
                                    <span class="font-medium text-ink-200">{{ $selectedTiket->pengerjaan_mulai_at?->format('d/m/Y H:i') ?? '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-ink-400 block">Target Deadline:</span>
                                    <span class="font-medium {{ $selectedTiket->pengerjaan_deadline_at && now()->greaterThan($selectedTiket->pengerjaan_deadline_at) && in_array($selectedTiket->status, ['dikerjakan', 'qc']) ? 'text-up-red font-bold' : 'text-ink-200' }}">
                                        {{ $selectedTiket->pengerjaan_deadline_at?->format('d/m/Y H:i') ?? '-' }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-ink-400 block">SLA KPI Status:</span>
                                    <span class="font-bold {{ $selectedTiket->status_sla_kpi === 'on_time' ? 'text-up-mint' : ($selectedTiket->status_sla_kpi === 'overdue' ? 'text-up-red' : 'text-ink-300') }}">
                                        {{ match($selectedTiket->status_sla_kpi) {
                                            'on_time' => 'Tepat Waktu (On-Time)',
                                            'overdue' => 'Terlambat (Overdue)',
                                            'cancelled_overdue' => 'Auto-Cancelled Overdue',
                                            default => (in_array($selectedTiket->status, ['dikerjakan', 'qc']) ? 'Sedang Berjalan' : '-')
                                        } }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Progres Pengerjaan Teknisi (Foto & Log) -->
                    @if($selectedTiket->progressList->count() > 0)
                        <div class="p-3.5 rounded-xl bg-white/[0.03] border border-white/5 space-y-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] text-ink-400 uppercase font-bold">Log Progres Pengerjaan & Dokumentasi Foto</span>
                                <span class="text-xs font-bold text-up-mint tabular-nums">
                                    {{ $selectedTiket->progressList->first()?->persentase }}% Selesai
                                </span>
                            </div>
                            <div class="w-full bg-white/10 rounded-full h-2 overflow-hidden">
                                <div class="bg-gradient-to-r from-up-primary to-up-mint h-2 rounded-full transition-[width] duration-300" style="width: {{ $selectedTiket->progressList->first()?->persentase }}%"></div>
                            </div>
                            <div class="space-y-2 mt-2 max-h-48 overflow-y-auto pr-1">
                                @foreach($selectedTiket->progressList as $pr)
                                    <div class="p-2.5 rounded-xl bg-white/[0.02] border border-white/5 space-y-1.5 text-xs">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-up-primary/20 text-up-primary border border-up-primary/30">
                                                    {{ $pr->persentase }}%
                                                </span>
                                                <strong class="text-white">{{ $pr->judul }}</strong>
                                                @if($pr->tampilkan_ke_konsumen)
                                                    <span class="text-[9px] text-up-mint bg-up-mint/10 px-1 py-0.2 rounded border border-up-mint/20">Publik</span>
                                                @endif
                                            </div>
                                            <span class="text-[10px] text-ink-500 tabular-nums">{{ $pr->created_at->format('d/m H:i') }}</span>
                                        </div>
                                        @if($pr->deskripsi)
                                            <p class="text-[11px] text-ink-300">{{ $pr->deskripsi }}</p>
                                        @endif
                                        @if(!empty($pr->foto))
                                            <div class="flex flex-wrap gap-1.5 pt-1">
                                                @foreach($pr->foto as $ft)
                                                    <a href="{{ $ft }}" target="_blank" class="w-14 h-14 rounded-lg overflow-hidden border border-white/10 block hover:opacity-80">
                                                        <img src="{{ $ft }}" alt="Foto Progress" class="w-full h-full object-cover">
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- [T-17] Item Pekerjaan Teknisi (part/jasa) -->
                    @php
                        $activeItems = $selectedTiket->items->whereNull('dibatalkan_at');
                        $canceledItems = $selectedTiket->items->whereNotNull('dibatalkan_at');
                        $totalBiayaPekerjaan = $activeItems->sum(fn ($i) => (float) $i->harga * (int) $i->qty);
                    @endphp
                    @if($selectedTiket->items->count() > 0)
                        <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/5 space-y-2.5">
                            <div class="flex items-center justify-between">
                                <p class="text-[10px] text-ink-400 font-bold uppercase tracking-wider">Item Pekerjaan & Suku Cadang Terpasang ({{ $activeItems->count() }})</p>
                                <span class="text-xs font-bold text-up-mint tabular-nums">Total: Rp {{ number_format($totalBiayaPekerjaan, 0, ',', '.') }}</span>
                            </div>
                            <div class="space-y-1.5">
                                @foreach($activeItems as $it)
                                    <div class="flex items-center justify-between text-xs p-2 rounded-xl bg-white/[0.02] border border-white/5">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="text-[9px] font-bold {{ $it->tipe === 'part' ? 'text-up-amber bg-up-amber/10 border border-up-amber/30' : 'text-up-mint bg-up-mint/10 border border-up-mint/30' }} px-1.5 py-0.5 rounded-full flex-shrink-0">
                                                {{ strtoupper($it->tipe) }}
                                            </span>
                                            <span class="text-ink-100 truncate font-medium">
                                                {{ $it->nama_item }}
                                            </span>
                                            <span class="text-ink-400 text-[11px] tabular-nums">
                                                ({{ $it->qty }} × Rp {{ number_format($it->harga, 0, ',', '.') }})
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-3 flex-shrink-0">
                                            <span class="font-bold text-white tabular-nums">Rp {{ number_format($it->harga * $it->qty, 0, ',', '.') }}</span>
                                            @if(in_array($selectedTiket->status, ['disetujui', 'dikerjakan', 'qc'], true))
                                                <button
                                                    type="button"
                                                    wire:click="batalkanItem({{ $it->id }})"
                                                    wire:confirm="Batalkan item '{{ $it->nama_item }}'? Stok suku cadang akan dikembalikan ke gudang jika berupa part."
                                                    class="text-up-red/70 hover:text-up-red text-[11px] px-1.5 py-0.5 rounded hover:bg-up-red/10 cursor-pointer"
                                                    title="Batalkan item pekerjaan ini"
                                                >
                                                    Batalkan
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach

                                @if($canceledItems->isNotEmpty())
                                    <div class="pt-2 border-t border-white/5 space-y-1">
                                        <p class="text-[9px] text-ink-500 uppercase font-semibold">Item Dibatalkan ({{ $canceledItems->count() }})</p>
                                        @foreach($canceledItems as $c)
                                            <div class="flex justify-between text-[11px] text-ink-500 line-through">
                                                <span>[{{ strtoupper($c->tipe) }}] {{ $c->nama_item }} × {{ $c->qty }}</span>
                                                <span class="tabular-nums">Rp {{ number_format($c->harga * $c->qty, 0, ',', '.') }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- [T-17] Form input pekerjaan teknisi (part → potong stok, jasa → tagihan) -->
                    @if(in_array($selectedTiket->status, ['disetujui', 'dikerjakan', 'qc'], true))
                        <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/5 space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-xs font-bold text-white">Input Tambahan Jasa / Suku Cadang</h4>
                                    <p class="text-[10px] text-ink-400">Tambahkan jasa perbaikan atau part pengganti selama proses pengerjaan</p>
                                </div>
                                @if($selectedTiket->estimasiItems->count() > 0)
                                    <button
                                        type="button"
                                        wire:click="importEstimasiKePekerjaan"
                                        class="px-2.5 py-1 rounded-xl bg-white/5 hover:bg-white/10 text-up-primary border border-up-primary/30 text-[10px] font-bold cursor-pointer transition-colors"
                                        title="Muat rincian dari estimasi yang disetujui"
                                    >
                                        Impor dari Estimasi
                                    </button>
                                @endif
                            </div>

                            @foreach($pekerjaanItems as $idx => $row)
                                <div class="p-2.5 rounded-xl bg-black/20 border border-white/5 space-y-2">
                                    <div class="grid grid-cols-12 gap-2 items-center">
                                        {{-- Tipe --}}
                                        <div class="col-span-3 sm:col-span-2">
                                            <select wire:model.live="pekerjaanItems.{{ $idx }}.tipe" class="w-full px-2 py-1.5 rounded-lg glass-input text-xs font-semibold">
                                                <option value="jasa" class="bg-ink-900 text-up-mint">Jasa</option>
                                                <option value="part" class="bg-ink-900 text-up-amber">Part</option>
                                            </select>
                                        </div>

                                        {{-- Jasa Template / Nama Item --}}
                                        @if(($row['tipe'] ?? 'jasa') === 'jasa')
                                            <div class="col-span-9 sm:col-span-5 flex gap-1.5">
                                                @if($jenisServisList->isNotEmpty())
                                                    <select
                                                        wire:change="pilihJenisServisPekerjaan({{ $idx }}, $event.target.value)"
                                                        class="w-1/3 px-2 py-1.5 rounded-lg glass-input text-[11px] text-ink-300"
                                                    >
                                                        <option value="">Pilih template...</option>
                                                        @foreach($jenisServisList as $js)
                                                            <option value="{{ $js->id }}" class="bg-ink-900">{{ $js->nama }}</option>
                                                        @endforeach
                                                    </select>
                                                @endif
                                                <input
                                                    type="text"
                                                    wire:model="pekerjaanItems.{{ $idx }}.nama_item"
                                                    placeholder="Nama pekerjaan / jasa"
                                                    class="flex-1 px-2.5 py-1.5 rounded-lg glass-input text-xs font-medium"
                                                />
                                            </div>
                                        @else
                                            {{-- Part: Cari Produk & Gudang --}}
                                            <div class="col-span-9 sm:col-span-5 flex gap-1.5">
                                                <div class="flex-1 relative">
                                                    <input
                                                        type="text"
                                                        wire:model="pekerjaanItems.{{ $idx }}.produk_nama"
                                                        wire:click="bukaPencarianProduk({{ $idx }}, 'servis_pekerjaan')"
                                                        class="w-full px-2.5 py-1.5 pr-7 rounded-lg glass-input text-xs font-medium cursor-pointer"
                                                        placeholder="Pilih part / produk..."
                                                        readonly
                                                    />
                                                    @if($row['produk_id'])
                                                        <button
                                                            type="button"
                                                            wire:click="resetPekerjaanPartRow({{ $idx }})"
                                                            class="absolute right-2 top-1/2 -translate-y-1/2 text-ink-400 hover:text-white"
                                                            title="Hapus pilihan part"
                                                        >
                                                            ✕
                                                        </button>
                                                    @else
                                                        <button
                                                            type="button"
                                                            wire:click="bukaPencarianProduk({{ $idx }}, 'servis_pekerjaan')"
                                                            class="absolute right-2 top-1/2 -translate-y-1/2 text-ink-400 hover:text-white cursor-pointer"
                                                        >
                                                            🔍
                                                        </button>
                                                    @endif
                                                </div>
                                                @if(count($gudangList ?? []) > 1)
                                                    <select wire:model="pekerjaanItems.{{ $idx }}.gudang_id" class="w-28 px-1.5 py-1.5 rounded-lg glass-input text-[11px] font-medium">
                                                        @foreach($gudangList as $g)
                                                            <option value="{{ $g->id }}" class="bg-ink-900">{{ $g->nama }}</option>
                                                        @endforeach
                                                    </select>
                                                @endif
                                            </div>
                                        @endif

                                        {{-- Qty --}}
                                        <div class="col-span-3 sm:col-span-1">
                                            <input type="number" wire:model="pekerjaanItems.{{ $idx }}.qty" min="1" placeholder="Qty" class="w-full px-2 py-1.5 rounded-lg glass-input text-xs font-medium text-center" />
                                        </div>

                                        {{-- Harga Satuan --}}
                                        <div class="col-span-7 sm:col-span-3">
                                            <input
                                                type="text"
                                                inputmode="numeric"
                                                x-format-number
                                                wire:model="pekerjaanItems.{{ $idx }}.harga"
                                                placeholder="Tarif (Rp)"
                                                @if(($row['tipe'] ?? '') === 'part' && ! $canEditHargaPart) readonly @endif
                                                class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs font-medium tabular-nums {{ (($row['tipe'] ?? '') === 'part' && ! $canEditHargaPart) ? 'opacity-80 bg-white/5 cursor-not-allowed' : '' }}"
                                            />
                                        </div>

                                        {{-- Hapus Baris --}}
                                        <div class="col-span-2 sm:col-span-1 text-right">
                                            <button
                                                type="button"
                                                wire:click="removePekerjaanRow({{ $idx }})"
                                                class="text-up-red/70 hover:text-up-red text-xs p-1.5 rounded hover:bg-up-red/10 cursor-pointer"
                                                title="Hapus baris ini"
                                            >
                                                ✕
                                            </button>
                                        </div>
                                    </div>

                                    {{-- SN jika part --}}
                                    @if(($row['tipe'] ?? 'jasa') === 'part')
                                        <div>
                                            <input
                                                type="text"
                                                wire:model="pekerjaanItems.{{ $idx }}.sn"
                                                placeholder="Nomor Seri (SN) jika produk memiliki serial number (pisahkan dengan koma atau baris baru)"
                                                class="w-full px-2.5 py-1 rounded-lg glass-input text-[11px] font-mono text-ink-300"
                                            />
                                        </div>
                                    @endif
                                </div>
                            @endforeach

                            <div class="flex items-center justify-between pt-1">
                                <button
                                    type="button"
                                    wire:click="addPekerjaanRow"
                                    class="px-3 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-200 text-xs font-semibold border border-white/10 cursor-pointer flex items-center gap-1"
                                >
                                    <span>+ Baris Item</span>
                                </button>
                                <button
                                    type="button"
                                    wire:click="simpanPekerjaan"
                                    wire:loading.attr="disabled"
                                    class="px-4 py-2 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white text-xs font-bold cursor-pointer shadow-lg shadow-up-primary/25 flex items-center gap-1.5 active:scale-95 transition-all"
                                >
                                    <span wire:loading.remove wire:target="simpanPekerjaan">Simpan Item Pekerjaan</span>
                                    <span wire:loading wire:target="simpanPekerjaan">Menyimpan...</span>
                                </button>
                            </div>
                            <p class="text-[10px] text-ink-500">
                                ℹ️ Part langsung memotong stok 1x di gudang cabang aktif. Jasa dicatat sebagai tagihan & laporan pendapatan.
                            </p>
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
                        Pembayaran tunai / di toko wajib melalui @canany(['pos.view', 'pos.view-own', 'pos.create'])<a href="/app/pos?bayar_servis_id={{ $bayarTiketId }}" class="underline font-bold text-white hover:text-blue-200">Kasir POS</a>@else<strong>Kasir POS</strong>@endcanany agar tercatat di sesi kas laci. Form ini khusus pelunasan online / transfer.
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
                                    class="p-3 rounded-xl border text-left transition-[background-color,border-color,color] duration-140 cursor-pointer {{ $bayarMetode === $metode ? 'bg-up-primary/20 border-up-primary text-white font-bold' : 'bg-white/[0.02] border-white/5 text-ink-300 hover:bg-white/[0.05]' }}"
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
                            class="flex-1 py-3 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 font-semibold text-xs transition-[background-color,color] duration-140 cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            wire:click="prosesBayar"
                            class="flex-1 py-3 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs transition-[background-color,transform] duration-100 active:scale-[0.98] cursor-pointer"
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
                                class="w-full flex items-center justify-between gap-3 p-2.5 rounded-xl bg-white/[0.03] hover:bg-white/[0.08] border border-white/5 hover:border-up-primary/30 transition-[background-color,border-color,transform] duration-100 active:scale-[0.99] cursor-pointer text-left group">
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

    <!-- ===== MODAL: PEMERIKSAAN & PENERIMAAN FISIK UNIT (INTAKE BOOKING) ===== -->
    @if($showIntakeBookingModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-2xl glass-panel p-6 rounded-3xl border border-white/10 shadow-2xl relative max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10 flex-shrink-0">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-up-primary animate-pulse"></span>
                            <h3 class="text-base font-bold text-white">Pemeriksaan Fisik & Penerimaan Unit</h3>
                        </div>
                        <p class="text-xs text-ink-400 mt-0.5">Verifikasi fisik unit saat diserahkan langsung oleh konsumen di gerai (Tiket: {{ $intakeForm['no_tiket'] }})</p>
                    </div>
                    <button wire:click="$set('showIntakeBookingModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="overflow-y-auto pr-1 flex-1 space-y-4">
                    <!-- Info Pelanggan & Unit Booking -->
                    <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/5 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div>
                            <span class="text-[10px] text-ink-400 uppercase font-semibold">Pemilik / Pengaju</span>
                            <p class="font-bold text-white mt-0.5">{{ $intakeForm['nama_pelanggan'] ?: 'Pelanggan Umum' }}</p>
                            <p class="text-ink-400 font-mono mt-0.5">{{ $intakeForm['telepon_pelanggan'] ?: '-' }}</p>
                        </div>
                        <div>
                            <span class="text-[10px] text-ink-400 uppercase font-semibold">Keluhan Diajukan Online</span>
                            <p class="text-ink-200 mt-0.5">{{ $intakeForm['keluhan'] }}</p>
                        </div>
                    </div>

                    <!-- Verifikasi Merek & Seri / IMEI -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1">Merek & Model Aktual <span class="text-up-red">*</span></label>
                            <input
                                type="text"
                                wire:model="intakeForm.jenis_hp"
                                class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white"
                                placeholder="Contoh: iPhone 13 Pro"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1">Seri / Warna / No. IMEI</label>
                            <input
                                type="text"
                                wire:model="intakeForm.seri_hp"
                                class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white"
                                placeholder="Contoh: Sierra Blue / IMEI"
                            />
                        </div>
                    </div>

                    <!-- Kategori & Teknisi Penanggung Jawab -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1">Kategori Layanan Servis</label>
                            <select wire:model="intakeForm.jenis_servis_id" class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white">
                                <option value="" class="bg-ink-900">-- Pilih Kategori --</option>
                                @foreach($jenisServisList as $js)
                                    <option value="{{ $js->id }}" class="bg-ink-900">{{ $js->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1">Teknisi Penerima / Ditugaskan</label>
                            <select wire:model="intakeForm.teknisi_id" class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white">
                                <option value="" class="bg-ink-900">-- Pilih Teknisi --</option>
                                @foreach($teknisiList as $tek)
                                    <option value="{{ $tek->id }}" class="bg-ink-900">{{ $tek->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Kunci Layar Gadget & Uji Pola Langsung -->
                    <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/5 space-y-2">
                        <label class="block text-xs font-semibold text-ink-300">Kunci Layar Gadget</label>
                        <select wire:model.live="intakeForm.tipe_kunci" class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white">
                            <option value="" class="bg-ink-900">— Tidak ada / default —</option>
                            <option value="pola" class="bg-ink-900">Pola (Swipe / Geser Titik)</option>
                            <option value="pin" class="bg-ink-900">PIN Angka</option>
                            <option value="password" class="bg-ink-900">Password</option>
                            <option value="tidak_ada" class="bg-ink-900">Tidak Ada Kunci</option>
                        </select>

                        @if(($intakeForm['tipe_kunci'] ?? '') === 'pola')
                            <div class="mt-2">
                                <x-pattern-lock wire-model="intakeForm.kunci_terenkripsi" :dark="true" />
                            </div>
                        @elseif(in_array($intakeForm['tipe_kunci'] ?? '', ['pin', 'password'], true))
                            <div class="mt-2">
                                <input
                                    type="text"
                                    wire:model="intakeForm.kunci_terenkripsi"
                                    placeholder="Masukkan nilai {{ strtoupper($intakeForm['tipe_kunci']) }}"
                                    class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white"
                                />
                            </div>
                        @endif
                    </div>

                    <!-- Checklist Pemeriksaan Fisik Aktual Teknisi -->
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Checklist Kondisi Fisik Aktual (Saat Diterima Teknisi)</label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            @foreach([
                                'Layar lecet / gores',
                                'Layar retak / pecah',
                                'LCD garis / tompel',
                                'Touchscreen sebagian error',
                                'Bodi / casing dent baret',
                                'Backdoor retak / renggang',
                                'Tombol power/vol macet',
                                'Port charger longgar/kotor',
                                'Kamera buram / retak',
                                'Indikasi bekas air / lembap',
                                'Kondisi mati total saat terima',
                            ] as $itemFisik)
                                <label class="flex items-center gap-2 p-2 rounded-xl border cursor-pointer text-xs transition-colors {{ in_array($itemFisik, $intakeForm['kondisi_fisik'] ?? []) ? 'bg-up-amber/15 border-up-amber text-up-amber font-semibold' : 'bg-white/[0.02] border-white/5 text-ink-300' }}">
                                    <input
                                        type="checkbox"
                                        wire:click="toggleIntakeKondisiFisik('{{ $itemFisik }}')"
                                        {{ in_array($itemFisik, $intakeForm['kondisi_fisik'] ?? []) ? 'checked' : '' }}
                                        class="rounded text-up-primary focus:ring-up-primary"
                                    />
                                    <span>{{ $itemFisik }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <!-- Kelengkapan yang Diterima -->
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kelengkapan Unit yang Diterima di Konter</label>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            @foreach([
                                'Unit Batangan Saja',
                                'Kabel / Charger',
                                'Dus / Box Asli',
                                'SIM Tray / SIM Card',
                                'Softcase / Casing',
                            ] as $kel)
                                <label class="flex items-center gap-2 p-2 rounded-xl border cursor-pointer text-xs transition-colors {{ in_array($kel, $intakeForm['kelengkapan'] ?? []) ? 'bg-up-mint/15 border-up-mint text-up-mint font-semibold' : 'bg-white/[0.02] border-white/5 text-ink-300' }}">
                                    <input
                                        type="checkbox"
                                        wire:click="toggleIntakeKelengkapan('{{ $kel }}')"
                                        {{ in_array($kel, $intakeForm['kelengkapan'] ?? []) ? 'checked' : '' }}
                                        class="rounded text-up-mint focus:ring-up-mint"
                                    />
                                    <span>{{ $kel }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <!-- Catatan Fisik Tambahan -->
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1">Catatan Khusus Teknisi Saat Penerimaan</label>
                        <textarea
                            wire:model="intakeForm.catatan_intake"
                            rows="2"
                            placeholder="Misal: Diterima dalam keadaan baterai 0%, ada baret dalam di pojok kanan atas, diserahkan langsung oleh konsumen..."
                            class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white"
                        ></textarea>
                    </div>
                </div>

                <div class="pt-4 mt-4 border-t border-white/10 flex justify-end gap-2 flex-shrink-0">
                    <button
                        type="button"
                        wire:click="$set('showIntakeBookingModal', false)"
                        class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 font-semibold text-xs transition-colors cursor-pointer"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        wire:click="simpanIntakeBooking"
                        class="px-5 py-2 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs transition-[background-color,transform] duration-100 shadow-md shadow-up-primary/30 cursor-pointer flex items-center gap-1.5 active:scale-95"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        Simpan & Terima Fisik Unit
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ===== MODAL: BATAL SERVIS & KEMBALIKAN UNIT (ESTIMASI DITOLAK) ===== -->
    @if($showBatalReturModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-lg glass-panel p-6 rounded-3xl border border-white/10 shadow-2xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <div>
                        <h3 class="text-base font-bold text-white">Batalkan Servis & Kembalikan Unit</h3>
                        <p class="text-xs text-ink-400 mt-0.5">Penyerahan kembali unit gadget kepada pelanggan tanpa perbaikan</p>
                    </div>
                    <button wire:click="$set('showBatalReturModal', false)" class="text-ink-400 hover:text-white">✕</button>
                </div>

                <div class="space-y-4">
                    <div class="p-3.5 rounded-2xl bg-up-red/10 border border-up-red/20 text-xs text-ink-200 space-y-1">
                        <p class="font-bold text-up-red">Perhatian:</p>
                        <p>Tiket servis akan diubah menjadi status <strong>Diambil</strong> (Selesai/Ditutup tanpa perbaikan). Unit fisik dirakit kembali dalam kondisi semula dan diserahkan kepada konsumen.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1">Catatan Serah Terima Pengembalian Unit</label>
                        <textarea
                            wire:model="batalReturCatatan"
                            rows="3"
                            class="w-full px-3 py-2 rounded-xl glass-input text-xs text-white"
                            placeholder="Catatan penyerahan unit kembali ke pemilik..."
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            wire:click="$set('showBatalReturModal', false)"
                            class="px-4 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 font-semibold text-xs transition-colors cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            wire:click="prosesBatalRetur"
                            class="px-5 py-2.5 rounded-xl bg-up-red hover:bg-rose-700 text-white font-bold text-xs transition-[background-color,transform] duration-100 shadow-md shadow-up-red/30 cursor-pointer flex items-center gap-1.5 active:scale-95"
                        >
                            Konfirmasi Serah Terima & Tutup Tiket
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- [T-35] Modal Cetak Struk Servis Thermal 58mm / 80mm --}}
    @include('partials.thermal-receipt-modal')
</div>