<!-- ===== [POS-SERVIS] MODAL: PEMBAYARAN SERVIS DI KASIR POS ===== -->
@if($showBayarServisModal)
    @php
        $servisTiketSlug = !empty($selectedServisDetail['no_tiket']) ? \Illuminate\Support\Str::slug($selectedServisDetail['no_tiket']) : 'tiket';
    @endphp
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/80 backdrop-blur-md p-4 sm:p-6 overflow-y-auto"
         x-data="{
             showQrisZoom: false,
             downloadQris(format = 'png') {
                 const svgContainer = document.getElementById('qris-svg-render-servis');
                 if (!svgContainer) return;
                 const svg = svgContainer.querySelector('svg');
                 if (!svg) return;
                 const serializer = new XMLSerializer();
                 const svgStr = serializer.serializeToString(svg);

                 if (format === 'svg') {
                     const blob = new Blob([svgStr], { type: 'image/svg+xml;charset=utf-8' });
                     const url = URL.createObjectURL(blob);
                     const a = document.createElement('a');
                     a.href = url;
                     a.download = 'QRIS-SERVIS-{{ $servisTiketSlug }}-' + Date.now() + '.svg';
                     document.body.appendChild(a);
                     a.click();
                     document.body.removeChild(a);
                     URL.revokeObjectURL(url);
                     return;
                 }

                 const img = new Image();
                 const svgBlob = new Blob([svgStr], { type: 'image/svg+xml;charset=utf-8' });
                 const url = URL.createObjectURL(svgBlob);
                 img.onload = () => {
                     const canvas = document.createElement('canvas');
                     canvas.width = 600;
                     canvas.height = 600;
                     const ctx = canvas.getContext('2d');
                     ctx.fillStyle = '#FFFFFF';
                     ctx.fillRect(0, 0, 600, 600);
                     ctx.drawImage(img, 40, 40, 520, 520);
                     URL.revokeObjectURL(url);
                     const pngUrl = canvas.toDataURL('image/png');
                     const a = document.createElement('a');
                     a.href = pngUrl;
                     a.download = 'QRIS-SERVIS-{{ $servisTiketSlug }}-' + Date.now() + '.png';
                     document.body.appendChild(a);
                     a.click();
                     document.body.removeChild(a);
                 };
                 img.src = url;
             }
         }">
        <div class="w-full max-w-2xl bg-white dark:bg-[#0B1120] border border-ink-200/80 dark:border-white/10 p-5 sm:p-6 rounded-3xl relative max-h-[90vh] flex flex-col shadow-2xl my-auto">
            <!-- Header -->
            <div class="flex items-center justify-between pb-3.5 mb-4 border-b border-ink-200/70 dark:border-white/10">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-500 font-bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-ink-900 dark:text-white">Pembayaran Servis di Kasir POS</h3>
                        <p class="text-[11px] text-ink-500 dark:text-ink-400">Penyatuan kas masuk laci kasir untuk pelunasan tiket servis</p>
                    </div>
                </div>
                <button
                    type="button"
                    wire:click="tutupBayarServisModal"
                    class="p-2 rounded-xl text-ink-400 hover:text-ink-900 dark:hover:text-white hover:bg-ink-100 dark:hover:bg-white/5 transition-colors cursor-pointer"
                >✕</button>
            </div>

            <div class="overflow-y-auto flex-1 space-y-4 pr-1">
                @if(! $selectedServisDetail)
                    {{-- STATE 1: PILIH TIKET SERVIS --}}
                    <div class="space-y-3">
                        <div class="relative">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="searchServis"
                                placeholder="Cari no tiket / nama / no hp / tipe hp..."
                                class="w-full px-4 py-2.5 rounded-xl glass-input text-xs font-semibold text-ink-900 dark:text-white border-ink-200/80 dark:border-white/10"
                                autofocus
                            />
                            @if($searchServis)
                                <button type="button" wire:click="$set('searchServis', '')" class="absolute right-3 top-2.5 text-xs text-ink-400 hover:text-ink-900 dark:hover:text-white">✕</button>
                            @endif
                        </div>

                        <div class="space-y-2 max-h-[55vh] overflow-y-auto pr-1">
                            @forelse($daftarServisSiapBayar as $ts)
                                <div class="p-3.5 rounded-2xl bg-ink-50/70 dark:bg-white/[0.02] hover:bg-ink-100 dark:hover:bg-white/[0.05] border border-ink-200/80 dark:border-white/5 transition flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-bold text-ink-900 dark:text-white text-xs">{{ $ts->no_tiket }}</span>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/15 text-blue-500 dark:text-blue-400 border border-blue-500/20">
                                                {{ ucfirst($ts->status) }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-ink-700 dark:text-ink-200 font-medium mt-1">
                                            {{ $ts->pelanggan?->nama ?? $ts->nama_pelanggan ?? 'Pelanggan Umum' }}
                                            <span class="text-ink-400">({{ $ts->pelanggan?->telepon ?? $ts->telepon_pelanggan ?? '-' }})</span>
                                        </p>
                                        <p class="text-[11px] text-ink-500 dark:text-ink-400 mt-0.5 truncate">
                                            {{ $ts->jenis_hp }}{{ $ts->seri_hp ? ' · '.$ts->seri_hp : '' }} — {{ $ts->keluhan }}
                                        </p>
                                    </div>
                                    <div class="text-right flex flex-col items-end gap-1.5 flex-shrink-0">
                                        <span class="font-black text-sm text-up-mint tabular-nums">
                                            Rp {{ number_format($ts->estimasi_biaya ?? 0, 0, ',', '.') }}
                                        </span>
                                        <button
                                            type="button"
                                            wire:click="pilihTiketServis({{ $ts->id }})"
                                            class="px-3.5 py-1.5 rounded-xl bg-up-primary hover:bg-indigo-600 text-white text-xs font-bold transition active:scale-[0.98] cursor-pointer">
                                            Pilih
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <div class="py-12 text-center text-ink-400 text-xs">
                                    Tidak ada tiket servis siap bayar (status selesai/diambil belum lunas) di cabang ini.
                                </div>
                            @endforelse
                        </div>
                    </div>
                @else
                    {{-- STATE 2: DETAIL TAGIHAN & PEMBAYARAN --}}
                    <div class="space-y-4">
                        <!-- Card Info Tiket -->
                        <div class="p-3.5 rounded-2xl bg-ink-50 dark:bg-white/[0.03] border border-ink-200/80 dark:border-white/5 flex items-center justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold text-sm text-ink-900 dark:text-white">{{ $selectedServisDetail['no_tiket'] }}</span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/15 text-blue-500 dark:text-blue-400">
                                        {{ ucfirst($selectedServisDetail['status']) }}
                                    </span>
                                </div>
                                <p class="text-xs text-ink-700 dark:text-ink-200 mt-1 font-medium">
                                    {{ $selectedServisDetail['pelanggan_nama'] }} · {{ $selectedServisDetail['pelanggan_telepon'] }}
                                </p>
                                <p class="text-[11px] text-ink-500 dark:text-ink-400">
                                    Unit: <span class="text-ink-900 dark:text-white font-medium">{{ $selectedServisDetail['jenis_hp'] }}</span>
                                </p>
                            </div>
                            <button
                                type="button"
                                wire:click="$set('selectedServisDetail', null)"
                                class="px-3 py-1.5 rounded-xl bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 text-[11px] font-bold cursor-pointer">
                                Ganti Tiket
                            </button>
                        </div>

                        <!-- Rincian Biaya Servis -->
                        <div>
                            <h4 class="text-xs font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider mb-2">Rincian Pekerjaan & Suku Cadang:</h4>
                            <div class="rounded-2xl border border-ink-200/80 dark:border-white/5 overflow-hidden">
                                <table class="w-full text-left text-xs">
                                    <thead>
                                        <tr class="border-b border-ink-200/80 dark:border-white/10 text-ink-500 text-[10px] bg-ink-50 dark:bg-white/[0.02] uppercase font-bold">
                                            <th class="py-2.5 px-3.5">Item</th>
                                            <th class="py-2.5 px-3.5 text-center">Qty</th>
                                            <th class="py-2.5 px-3.5 text-right">Harga</th>
                                            <th class="py-2.5 px-3.5 text-right">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-ink-200/60 dark:divide-white/5">
                                        @foreach($selectedServisDetail['items'] as $it)
                                            <tr>
                                                <td class="py-2.5 px-3.5 text-ink-900 dark:text-white font-medium">{{ $it['nama'] }}</td>
                                                <td class="py-2.5 px-3.5 text-center text-ink-700 dark:text-white tabular-nums">{{ $it['qty'] }}</td>
                                                <td class="py-2.5 px-3.5 text-right text-ink-600 dark:text-ink-300 tabular-nums">Rp {{ number_format($it['harga'], 0, ',', '.') }}</td>
                                                <td class="py-2.5 px-3.5 text-right text-ink-900 dark:text-white font-bold tabular-nums">Rp {{ number_format($it['subtotal'], 0, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Total Tagihan Banner -->
                        <div class="p-4 rounded-2xl bg-up-mint/10 border border-up-mint/25 flex items-center justify-between">
                            <span class="text-xs font-bold text-ink-700 dark:text-ink-200 uppercase tracking-wider">Total Tagihan Servis</span>
                            <span class="text-2xl font-black text-up-mint tabular-nums">
                                Rp {{ number_format($selectedServisDetail['total'], 0, ',', '.') }}
                            </span>
                        </div>

                        <!-- Metode Pembayaran -->
                        <div>
                            <label class="block text-xs font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider mb-2">Metode Pembayaran</label>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                @foreach(['tunai' => 'Tunai (Kas Laci)', 'transfer' => 'Transfer Bank', 'qris' => 'QRIS', 'split' => 'Split'] as $m => $lbl)
                                    <button
                                        type="button"
                                        wire:click="$set('servisMetodeBayar', '{{ $m }}')"
                                        class="py-2.5 px-2 rounded-xl border text-center text-xs font-bold transition cursor-pointer min-h-[42px] {{ $servisMetodeBayar === $m ? 'bg-up-primary text-white border-up-primary shadow-sm shadow-up-primary/30' : 'bg-ink-100/80 dark:bg-white/[0.02] border-ink-200/80 dark:border-white/5 text-ink-700 dark:text-ink-300 hover:bg-ink-200 dark:hover:bg-white/5' }}">
                                        {{ $lbl }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <!-- Input Pembayaran -->
                        @if($servisMetodeBayar === 'tunai')
                            <div class="space-y-2">
                                <div class="flex items-center justify-between text-xs font-semibold text-ink-700 dark:text-ink-300">
                                    <span>Jumlah Bayar (Tunai)</span>
                                    <div class="flex gap-1.5">
                                        <button type="button" wire:click="$set('servisJumlahBayar', {{ $selectedServisDetail['total'] }})" class="px-2 py-0.5 rounded-md bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-[10px] font-bold text-ink-600 dark:text-ink-300 cursor-pointer">Uang Pas</button>
                                        <button type="button" wire:click="$set('servisJumlahBayar', 50000)" class="px-2 py-0.5 rounded-md bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-[10px] font-bold text-ink-600 dark:text-ink-300 cursor-pointer">50k</button>
                                        <button type="button" wire:click="$set('servisJumlahBayar', 100000)" class="px-2 py-0.5 rounded-md bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-[10px] font-bold text-ink-600 dark:text-ink-300 cursor-pointer">100k</button>
                                        <button type="button" wire:click="$set('servisJumlahBayar', 200000)" class="px-2 py-0.5 rounded-md bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-[10px] font-bold text-ink-600 dark:text-ink-300 cursor-pointer">200k</button>
                                    </div>
                                </div>
                                <input
                                    type="text"
                                    wire:model.live="servisJumlahBayar"
                                    class="w-full px-4 py-3 rounded-2xl glass-input text-lg font-black text-ink-900 dark:text-white tabular-nums border-ink-200/80 dark:border-white/10 min-h-[48px]"
                                    placeholder="0"
                                />
                                @if($servisKembalian > 0)
                                    <div class="p-3 rounded-xl bg-ink-50 dark:bg-white/[0.03] border border-ink-200/80 dark:border-white/5 flex items-center justify-between text-xs">
                                        <span class="text-ink-500 font-medium">Kembalian:</span>
                                        <span class="font-black text-base text-up-mint tabular-nums">Rp {{ number_format($servisKembalian, 0, ',', '.') }}</span>
                                    </div>
                                @endif
                            </div>
                        @elseif($servisMetodeBayar === 'split')
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-ink-700 dark:text-ink-300 mb-1">Porsi Tunai (Kas Laci)</label>
                                    <input
                                        type="text"
                                        wire:model.live="servisSplitTunai"
                                        class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-bold text-ink-900 dark:text-white tabular-nums border-ink-200/80 dark:border-white/10 min-h-[42px]"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-ink-700 dark:text-ink-300 mb-1">Porsi Non-Tunai</label>
                                    <input
                                        type="text"
                                        wire:model.live="servisSplitNonTunai"
                                        class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs font-bold text-ink-900 dark:text-white tabular-nums border-ink-200/80 dark:border-white/10 min-h-[42px]"
                                    />
                                </div>
                            </div>
                        @elseif($servisMetodeBayar === 'qris')
                            <div class="p-4 rounded-2xl bg-ink-50 dark:bg-white/[0.02] border border-ink-200/80 dark:border-white/5 text-center space-y-3" wire:poll.3s="cekStatusQrisOtomatis">
                                @if($qrisKasirSvg)
                                    <div class="flex flex-col items-center">
                                        <!-- QR Display with Zoom Affordance -->
                                        <div
                                            id="qris-svg-render-servis"
                                            @click="showQrisZoom = true"
                                            class="p-3 bg-white rounded-xl shadow border border-ink-200/80 inline-block cursor-pointer group relative hover:ring-2 hover:ring-up-primary transition"
                                            title="Klik untuk memperbesar tampilan QRIS"
                                        >
                                            <div class="w-40 h-40 flex items-center justify-center pointer-events-none">
                                                {!! $qrisKasirSvg !!}
                                            </div>
                                            <div class="absolute inset-0 bg-black/40 rounded-xl opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity text-white text-[11px] font-bold">
                                                🔍 Perbesar
                                            </div>
                                        </div>

                                        <span class="text-base font-black text-up-primary tabular-nums mt-1.5 block">
                                            Rp {{ number_format($selectedServisDetail['total'] ?? 0, 0, ',', '.') }}
                                        </span>
                                        <span class="text-[10px] text-ink-500">Scan QRIS dinamis untuk menyelesaikan pembayaran servis</span>

                                        <!-- Quick Action Toolbar -->
                                        <div class="mt-2.5 flex flex-wrap items-center justify-center gap-1.5">
                                            <button
                                                type="button"
                                                @click="showQrisZoom = true"
                                                class="px-2.5 py-1.5 rounded-lg bg-up-primary/10 hover:bg-up-primary/20 text-up-primary text-xs font-bold transition flex items-center gap-1 cursor-pointer"
                                            >
                                                🔍 Perbesar
                                            </button>
                                            <button
                                                type="button"
                                                @click="downloadQris('png')"
                                                class="px-2.5 py-1.5 rounded-lg bg-ink-100 hover:bg-ink-200 dark:bg-white/10 dark:hover:bg-white/15 text-ink-800 dark:text-ink-200 text-xs font-bold transition flex items-center gap-1 cursor-pointer"
                                                title="Unduh file gambar QR Code"
                                            >
                                                📥 Unduh PNG
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="cekStatusQrisManual"
                                                wire:loading.attr="disabled"
                                                class="px-2.5 py-1.5 rounded-lg bg-ink-200/80 hover:bg-ink-300 dark:bg-white/10 dark:hover:bg-white/15 text-ink-800 dark:text-ink-200 text-xs font-bold transition flex items-center gap-1 cursor-pointer disabled:opacity-50"
                                            >
                                                <span wire:loading.remove wire:target="cekStatusQrisManual">🔄 Cek Status</span>
                                                <span wire:loading wire:target="cekStatusQrisManual" class="inline-flex items-center gap-1">
                                                    <svg class="animate-spin -ml-0.5 mr-1 h-3 w-3 text-ink-800 dark:text-ink-200" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    Memeriksa...
                                                </span>
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="kirimQrisViaWhatsApp"
                                                class="px-2.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition flex items-center gap-1 cursor-pointer"
                                            >
                                                📲 Kirim WA
                                            </button>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-xs text-ink-500">QRIS dinamis belum diatur di menu Pengaturan.</span>
                                @endif
                            </div>
                        @endif

                        <!-- Checkbox Serahkan Unit -->
                        @if($selectedServisDetail['status'] === 'selesai')
                            <label class="flex items-center gap-2.5 p-3 rounded-xl bg-ink-50 dark:bg-white/[0.02] border border-ink-200/80 dark:border-white/5 cursor-pointer">
                                <input
                                    type="checkbox"
                                    wire:model="servisUbahStatusDiambil"
                                    class="w-4 h-4 rounded text-up-primary focus:ring-0 bg-transparent border-ink-300 dark:border-white/20"
                                />
                                <span class="text-xs text-ink-800 dark:text-white font-medium">
                                    Serahkan unit ke pelanggan sekarang (status tiket berubah ke <strong class="text-up-mint">Diambil</strong>)
                                </span>
                            </label>
                        @endif

                        <!-- Catatan -->
                        <div>
                            <label class="block text-xs font-bold text-ink-700 dark:text-ink-300 mb-1">Catatan Tambahan (opsional)</label>
                            <input
                                type="text"
                                wire:model="servisCatatan"
                                placeholder="Contoh: No ref transfer / garansi khusus..."
                                class="w-full px-3.5 py-2.5 rounded-xl glass-input text-xs text-ink-900 dark:text-white border-ink-200/80 dark:border-white/10 min-h-[42px]"
                            />
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex gap-2.5 pt-3.5 border-t border-ink-200/70 dark:border-white/10 mt-4">
                <button
                    type="button"
                    wire:click="tutupBayarServisModal"
                    class="flex-1 py-2.5 rounded-xl bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 font-bold text-xs cursor-pointer min-h-[42px] transition-colors"
                >
                    Tutup
                </button>
                @if($selectedServisDetail)
                    <button
                        type="button"
                        wire:click="prosesBayarServis"
                        class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-up-primary to-indigo-600 hover:opacity-95 text-white font-black text-xs cursor-pointer min-h-[42px] shadow-lg shadow-up-primary/25 transition-transform active:scale-[0.98]"
                    >
                        Proses Bayar & Struk POS
                    </button>
                @endif
            </div>
        </div>

        <!-- FULLSCREEN POPUP ZOOM QRIS SERVIS -->
        <div
            x-show="showQrisZoom"
            x-cloak
            class="fixed inset-0 z-[70] flex items-center justify-center bg-black/90 backdrop-blur-md p-4 sm:p-6"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @keydown.escape.window="showQrisZoom = false"
        >
            <div
                @click.away="showQrisZoom = false"
                class="w-full max-w-md bg-white rounded-3xl p-6 sm:p-8 text-center shadow-2xl relative border-4 border-up-primary/40 space-y-4 text-ink-900"
            >
                <button
                    type="button"
                    @click="showQrisZoom = false"
                    class="absolute top-4 right-4 w-9 h-9 rounded-full bg-ink-100 hover:bg-ink-200 text-ink-700 font-black text-sm flex items-center justify-center transition cursor-pointer"
                >
                    ✕
                </button>

                <div class="space-y-1">
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-up-primary/10 text-up-primary font-black text-[11px] tracking-wider uppercase">
                        <span>QRIS DINAMIS • SERVIS</span>
                    </div>
                    <h3 class="text-xl font-black text-ink-900 uppercase tracking-tight">
                        {{ $qrisMerchantName ?: 'QRIS TOKO' }}
                    </h3>
                    <span class="text-xs text-ink-500 font-mono block">
                        Tiket: {{ $selectedServisDetail['no_tiket'] ?? 'SERVIS' }}
                    </span>
                </div>

                <div class="w-72 h-72 sm:w-80 sm:h-80 mx-auto p-4 bg-white rounded-2xl border-2 border-ink-200 shadow-inner flex items-center justify-center">
                    {!! $qrisKasirSvg !!}
                </div>

                <div>
                    <span class="text-xs text-ink-500 font-semibold block uppercase tracking-wider">Total Pelunasan Servis</span>
                    <span class="text-3xl font-black text-up-primary tabular-nums tracking-tight block">
                        Rp {{ number_format($selectedServisDetail['total'] ?? 0, 0, ',', '.') }}
                    </span>
                    <span class="text-[11px] text-ink-500 block mt-0.5">
                        Nominal terkunci otomatis • Konsumen tidak perlu mengetik nominal
                    </span>
                </div>

                <div class="pt-2 flex flex-wrap items-center justify-center gap-2">
                    <button
                        type="button"
                        @click="downloadQris('png')"
                        class="px-4 py-2.5 rounded-xl bg-ink-900 hover:bg-black text-white font-bold text-xs flex items-center gap-2 shadow-sm transition active:scale-[0.98] cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        <span>Unduh PNG</span>
                    </button>

                    <button
                        type="button"
                        wire:click="kirimQrisViaWhatsApp"
                        class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-2 shadow-sm transition active:scale-[0.98] cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                        </svg>
                        <span>Kirim WA</span>
                    </button>

                    <button
                        type="button"
                        @click="showQrisZoom = false"
                        class="px-4 py-2.5 rounded-xl bg-ink-100 hover:bg-ink-200 text-ink-700 font-bold text-xs transition cursor-pointer"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
