<!-- ===== [POS-ONLINE-PICKUP] MODAL: PEMBAYARAN PESANAN ONLINE (AMBIL DI TOKO) DI KASIR ===== -->
@if($showPesananOnlineModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/80 backdrop-blur-md p-4 sm:p-6 overflow-y-auto">
        <div class="w-full max-w-2xl bg-white dark:bg-[#0B1120] border border-ink-200/80 dark:border-white/10 p-5 sm:p-6 rounded-3xl relative max-h-[90vh] flex flex-col shadow-2xl my-auto">
            <!-- Header -->
            <div class="flex items-center justify-between pb-3.5 mb-4 border-b border-ink-200/70 dark:border-white/10">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-up-amber/10 border border-up-amber/25 flex items-center justify-center text-up-amber font-bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-ink-900 dark:text-white">Pesanan Online (Ambil di Toko)</h3>
                        <p class="text-[11px] text-ink-500 dark:text-ink-400">Terima pembayaran & serah terima order marketplace / web konsumen di kasir</p>
                    </div>
                </div>
                <button
                    type="button"
                    wire:click="tutupPesananOnlineModal"
                    class="p-2 rounded-xl text-ink-400 hover:text-ink-900 dark:hover:text-white hover:bg-ink-100 dark:hover:bg-white/5 transition-colors cursor-pointer"
                >✕</button>
            </div>

            <div class="overflow-y-auto flex-1 space-y-4 pr-1">
                @if(! $pesananOnlineDetail)
                    {{-- STATE 1: CARI & PILIH PESANAN --}}
                    <div class="space-y-3">
                        <div class="relative">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="pencarianPesananOnline"
                                wire:keydown.enter="pilihPesananOnlinePertama"
                                placeholder="Cari no. order / nama / no. HP pelanggan..."
                                class="w-full px-4 py-2.5 rounded-xl glass-input text-xs font-semibold text-ink-900 dark:text-white border-ink-200/80 dark:border-white/10"
                                autofocus
                            />
                            @if($pencarianPesananOnline)
                                <button type="button" wire:click="$set('pencarianPesananOnline', '')" class="absolute right-3 top-2.5 text-xs text-ink-400 hover:text-ink-900 dark:hover:text-white">✕</button>
                            @endif
                        </div>

                        <div class="space-y-2 max-h-[55vh] overflow-y-auto pr-1">
                            @forelse($pesananOnlineHasil as $po)
                                <div class="p-3.5 rounded-2xl bg-ink-50/70 dark:bg-white/[0.02] hover:bg-ink-100 dark:hover:bg-white/[0.05] border border-ink-200/80 dark:border-white/5 transition flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-mono font-bold text-ink-900 dark:text-white text-xs">{{ $po->no_transaksi }}</span>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                                🏬 Ambil di Toko
                                            </span>
                                            @if($po->status === 'lunas')
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-mint/15 text-up-mint border border-up-mint/30">
                                                    ✅ Lunas — siap serah
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-amber/15 text-up-amber border border-up-amber/30">
                                                    ⏳ Menunggu Bayar
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-ink-700 dark:text-ink-200 font-medium mt-1">
                                            {{ $po->pelanggan?->nama ?? 'Konsumen Umum' }}
                                            <span class="text-ink-400">({{ $po->pelanggan?->telepon ?? '-' }})</span>
                                        </p>
                                        <p class="text-[11px] text-ink-500 dark:text-ink-400 mt-0.5">
                                            {{ $po->items->sum('jumlah') }} item · {{ $po->created_at?->format('d/m/Y H:i') }}
                                        </p>
                                    </div>
                                    <div class="text-right flex flex-col items-end gap-1.5 flex-shrink-0">
                                        <span class="font-black text-sm text-up-mint tabular-nums">
                                            Rp {{ number_format($po->total_akhir, 0, ',', '.') }}
                                        </span>
                                        <button
                                            type="button"
                                            wire:click="pilihPesananOnline({{ $po->id }})"
                                            class="px-3.5 py-1.5 rounded-xl bg-up-primary hover:bg-indigo-600 text-white text-xs font-bold transition active:scale-[0.98] cursor-pointer">
                                            Pilih
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <div class="py-12 text-center text-ink-400 text-xs">
                                    @if($pencarianPesananOnline)
                                        Tidak ditemukan pesanan untuk pencarian "<span class="text-ink-900 dark:text-white font-semibold">{{ $pencarianPesananOnline }}</span>".
                                    @else
                                        Belum ada pesanan pickup di cabang ini.
                                    @endif
                                </div>
                            @endforelse
                        </div>
                    </div>
                @else
                    {{-- STATE 2: RINGKASAN & PEMBAYARAN --}}
                    <div class="space-y-4">
                        <!-- Card Info Pesanan -->
                        <div class="p-3.5 rounded-2xl bg-ink-50 dark:bg-white/[0.03] border border-ink-200/80 dark:border-white/5 flex items-center justify-between">
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-mono font-bold text-sm text-ink-900 dark:text-white">{{ $pesananOnlineDetail['no_transaksi'] }}</span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/15 text-amber-600 dark:text-amber-400">
                                        🏬 Ambil di Toko
                                    </span>
                                    @if($pesananOnlineDetail['sudah_lunas'])
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-mint/15 text-up-mint">
                                            ✅ Lunas
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-amber/15 text-up-amber">
                                            ⏳ Belum Bayar
                                        </span>
                                    @endif
                                </div>
                                <p class="text-xs text-ink-700 dark:text-ink-200 mt-1 font-medium">
                                    {{ $pesananOnlineDetail['pelanggan_nama'] }} · {{ $pesananOnlineDetail['pelanggan_telepon'] }}
                                </p>
                                <p class="text-[11px] text-ink-500 dark:text-ink-400">
                                    Dipesan {{ $pesananOnlineDetail['waktu'] }} · {{ $pesananOnlineDetail['jumlah_item'] }} item
                                </p>
                            </div>
                            <button
                                type="button"
                                wire:click="kembaliKeDaftarPesananOnline"
                                class="px-3 py-1.5 rounded-xl bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 text-[11px] font-bold cursor-pointer">
                                Ganti Pesanan
                            </button>
                        </div>

                        <!-- Daftar Item -->
                        <div>
                            <h4 class="text-xs font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider mb-2">Item yang Diserahkan:</h4>
                            <div class="rounded-2xl border border-ink-200/80 dark:border-white/5 overflow-hidden">
                                <table class="w-full text-left text-xs">
                                    <thead>
                                        <tr class="border-b border-ink-200/80 dark:border-white/10 text-ink-500 text-[10px] bg-ink-50 dark:bg-white/[0.02] uppercase font-bold">
                                            <th class="py-2.5 px-3.5">Item</th>
                                            <th class="py-2.5 px-3.5 text-center">Qty</th>
                                            <th class="py-2.5 px-3.5 text-right">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-ink-200/60 dark:divide-white/5">
                                        @foreach($pesananOnlineDetail['items'] as $it)
                                            <tr>
                                                <td class="py-2.5 px-3.5 text-ink-900 dark:text-white font-medium">{{ $it['nama'] }}</td>
                                                <td class="py-2.5 px-3.5 text-center text-ink-700 dark:text-white tabular-nums">{{ $it['jumlah'] }}</td>
                                                <td class="py-2.5 px-3.5 text-right text-ink-900 dark:text-white font-bold tabular-nums">Rp {{ number_format($it['subtotal'], 0, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Total Tagihan Banner -->
                        <div class="p-4 rounded-2xl bg-up-mint/10 border border-up-mint/25 flex items-center justify-between">
                            <span class="text-xs font-bold text-ink-700 dark:text-ink-200 uppercase tracking-wider">Total Tagihan</span>
                            <span class="text-2xl font-black text-up-mint tabular-nums">
                                Rp {{ number_format($pesananOnlineDetail['total'], 0, ',', '.') }}
                            </span>
                        </div>

                        @if($pesananOnlineDetail['sudah_lunas'])
                            <div class="space-y-2.5">
                                <div class="p-3.5 rounded-2xl bg-up-mint/15 border border-up-mint/30 text-up-mint text-xs font-bold flex items-center gap-2">
                                    <span>✅</span>
                                    <span>Pembayaran Lunas Online — Cukup serahkan barang fisik ke pelanggan</span>
                                </div>
                                <div class="p-3 rounded-2xl bg-ink-50 dark:bg-white/[0.02] border border-ink-200/80 dark:border-white/5 text-[11px] text-ink-600 dark:text-ink-300 space-y-1.5">
                                    <div class="flex justify-between">
                                        <span>Metode Pembayaran Online</span>
                                        <span class="text-ink-900 dark:text-white font-bold">{{ $pesananOnlineDetail['metode_bayar'] }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Tanggal Pelunasan</span>
                                        <span class="text-ink-900 dark:text-white font-bold tabular-nums">{{ $pesananOnlineDetail['tanggal_bayar'] ?? '-' }}</span>
                                    </div>
                                </div>
                            </div>
                        @else
                            <!-- Metode Pembayaran -->
                            <div>
                                <label class="block text-xs font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider mb-2">Metode Pembayaran di Toko</label>
                                <div class="grid grid-cols-3 gap-2">
                                    @foreach(['tunai' => '💵 Tunai', 'qris' => '📱 QRIS', 'transfer' => '🏦 Transfer'] as $m => $lbl)
                                        <button
                                            type="button"
                                            wire:click="$set('metodeBayarOnline', '{{ $m }}')"
                                            class="py-2.5 px-2 rounded-xl border text-center text-xs font-bold transition active:scale-[0.97] cursor-pointer min-h-[44px] {{ $metodeBayarOnline === $m ? 'bg-up-primary text-white border-up-primary shadow-md shadow-up-primary/30' : 'bg-ink-100/80 dark:bg-white/[0.02] border-ink-200/80 dark:border-white/5 text-ink-700 dark:text-ink-300 hover:bg-ink-200 dark:hover:bg-white/5' }}">
                                            {{ $lbl }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            @if($metodeBayarOnline === 'tunai')
                                @if(! $kasAktif)
                                    <div class="p-3 rounded-xl bg-up-amber/15 border border-up-amber/30 text-[11px] text-up-amber font-bold flex items-center gap-2">
                                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                        <span>Sesi kas belum dibuka — buka kas dulu (transaksi tunai diblokir)</span>
                                    </div>
                                @endif

                                <div class="space-y-2">
                                    <div class="flex items-center justify-between text-xs font-semibold text-ink-700 dark:text-ink-300">
                                        <span>Nominal Diterima</span>
                                        <div class="flex gap-1.5">
                                            <button type="button" wire:click="setNominalBayarOnline({{ $pesananOnlineDetail['total'] }})" class="px-2 py-0.5 rounded-md bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-[10px] font-bold text-ink-600 dark:text-ink-300 cursor-pointer">Uang Pas</button>
                                            <button type="button" wire:click="setNominalBayarOnline(50000)" class="px-2 py-0.5 rounded-md bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-[10px] font-bold text-ink-600 dark:text-ink-300 cursor-pointer">50k</button>
                                            <button type="button" wire:click="setNominalBayarOnline(100000)" class="px-2 py-0.5 rounded-md bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-[10px] font-bold text-ink-600 dark:text-ink-300 cursor-pointer">100k</button>
                                            <button type="button" wire:click="setNominalBayarOnline(200000)" class="px-2 py-0.5 rounded-md bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-[10px] font-bold text-ink-600 dark:text-ink-300 cursor-pointer">200k</button>
                                        </div>
                                    </div>
                                    <input
                                        type="text"
                                        inputmode="numeric"
                                        wire:model.live.debounce.300ms="nominalBayarOnline"
                                        class="w-full px-4 py-3 rounded-2xl glass-input text-xl font-black text-ink-900 dark:text-white tabular-nums border-ink-200/80 dark:border-white/10 min-h-[48px]"
                                        placeholder="0"
                                    />
                                    <div class="flex justify-between items-center p-3.5 rounded-2xl bg-ink-50 dark:bg-white/[0.02] border border-ink-200/80 dark:border-white/5">
                                        <span class="text-xs font-bold text-ink-600 dark:text-ink-400 uppercase tracking-wider">Kembalian</span>
                                        <span class="font-black text-xl tabular-nums {{ $kembalianOnline > 0 ? 'text-up-mint' : 'text-ink-900 dark:text-white' }}">
                                            Rp {{ number_format($kembalianOnline, 0, ',', '.') }}
                                        </span>
                                    </div>
                                </div>
                            @else
                                <div class="p-5 rounded-2xl bg-ink-50 dark:bg-white/5 text-center text-xs text-ink-600 dark:text-ink-300 border border-ink-200/80 dark:border-white/5">
                                    <span>Silakan scan QRIS / konfirmasi bukti transfer sebesar</span>
                                    <strong class="text-ink-900 dark:text-white block mt-1.5 text-lg font-black tabular-nums">
                                        Rp {{ number_format($pesananOnlineDetail['total'], 0, ',', '.') }}
                                    </strong>
                                </div>
                            @endif
                        @endif

                        <!-- Edukasi T&C & Garansi Sparepart Online saat Serah Terima -->
                        <div class="mt-4 p-4 rounded-2xl bg-up-primary/5 border border-up-primary/20 space-y-2.5">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-xs text-ink-900 dark:text-white flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-up-mint"></span>
                                    <span>Ketentuan Garansi & T&C Serah Terima Sparepart</span>
                                </span>
                                <span class="px-2 py-0.5 rounded-full bg-up-primary/10 text-up-primary text-[10px] font-bold">Wajib Diinfokan</span>
                            </div>

                            <div class="text-[11px] text-ink-600 dark:text-ink-300 space-y-1.5 bg-white/50 dark:bg-black/20 p-3 rounded-xl border border-black/5 dark:border-white/5">
                                <p class="font-semibold text-ink-900 dark:text-white">Poin T&C yang wajib disampaikan kasir ke konsumen:</p>
                                <ul class="list-disc list-inside space-y-1 text-ink-500 dark:text-ink-300 pl-1">
                                    <li>Masa garansi toko <strong>3 hari kalender</strong> terhitung mulai tanggal penyerahan barang hari ini.</li>
                                    <li>Segel toko & barcode pada sparepart wajib utuh tanpa sobek atau bekas modifikasi/solder.</li>
                                    <li>Garansi gugur bila ada cacat fisik (LCD pecah, fleksibel sobek, terkena air, atau salah pasang).</li>
                                    <li>Simpan struk transaksi ber-QR yang diberikan kasir sebagai bukti sah verifikasi retur anti-fraud.</li>
                                </ul>
                            </div>

                            <label class="flex items-start gap-2.5 p-2.5 rounded-xl border border-up-mint/30 bg-up-mint/10 cursor-pointer hover:bg-up-mint/15 transition select-none">
                                <input
                                    type="checkbox"
                                    wire:model.live="konfirmasiGaransiOnline"
                                    class="mt-0.5 rounded border-up-mint text-up-mint focus:ring-up-mint/30"
                                />
                                <span class="text-xs font-bold text-ink-900 dark:text-white leading-tight">
                                    Saya (Kasir) telah menginfokan masa garansi sparepart & seluruh poin T&C di atas langsung kepada konsumen saat penyerahan barang.
                                </span>
                            </label>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Footer Aksi -->
            <div class="flex gap-2.5 pt-3.5 border-t border-ink-200/70 dark:border-white/10 mt-4">
                <button
                    type="button"
                    wire:click="tutupPesananOnlineModal"
                    class="flex-1 py-2.5 rounded-xl bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 font-bold text-xs cursor-pointer min-h-[42px] transition-colors"
                >
                    Tutup
                </button>
                @if($pesananOnlineDetail)
                    @if($pesananOnlineDetail['sudah_lunas'])
                        <button
                            type="button"
                            wire:click="serahkanPesananOnline"
                            @disabled(! $konfirmasiGaransiOnline)
                            class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-up-mint to-teal-500 hover:opacity-95 text-ink-950 font-black text-xs transition-transform active:scale-[0.98] shadow-md shadow-up-mint/20 cursor-pointer min-h-[42px] disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Serahkan Barang Fisik
                        </button>
                    @else
                        <button
                            type="button"
                            wire:click="bayarOnlineOrder"
                            @disabled(($metodeBayarOnline === 'tunai' && ! $kasAktif) || ! $konfirmasiGaransiOnline)
                            class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-up-mint to-teal-500 hover:opacity-95 text-ink-950 font-black text-xs transition-transform active:scale-[0.98] shadow-md shadow-up-mint/20 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed min-h-[42px]"
                        >
                            Terima Pembayaran &amp; Serahkan
                        </button>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endif
