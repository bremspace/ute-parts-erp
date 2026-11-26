<div class="max-w-4xl mx-auto px-4 sm:px-6 py-3 sm:py-5 lg:py-10"
     x-data="{
         cartPreview: null,
         cartPreviewNama: '',
         setCartPreview(data) {
             const d = Array.isArray(data) ? data[0] : data;
             this.cartPreview = (d && typeof d === 'object') ? (d.url || null) : (d || null);
             this.cartPreviewNama = (d && typeof d === 'object') ? (d.nama || '') : '';
         }
     }"
     @open-preview.window="setCartPreview($event.detail)"
     @keydown.escape.window="cartPreview = null; cartPreviewNama = ''">
    @if($orderSuccess)
        <!-- ===== ORDER SUCCESS (Clean Editorial Receipt) ===== -->
        <div class="max-w-lg mx-auto text-center py-12 space-y-6">
            <div class="w-20 h-20 mx-auto rounded-3xl bg-up-mint/10 text-up-mint flex items-center justify-center shadow-lg shadow-up-mint/10">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
            </div>

            <div class="space-y-2">
                <span class="text-xs font-bold uppercase tracking-wider text-up-mint">Pemesanan Selesai</span>
                <h1 class="text-2xl sm:text-3xl font-black text-ink-900 dark:text-white tracking-tight">Pesanan Telah Terdaftar</h1>
                <p class="text-xs sm:text-sm text-ink-500 dark:text-ink-400">
                    Nomor referensi pesanan: <strong class="text-ink-900 dark:text-white font-mono">{{ $orderNo }}</strong>
                </p>
            </div>

            <div class="rounded-3xl p-6 bg-white dark:bg-ink-900 border border-ink-100/80 dark:border-white/10 shadow-sm space-y-3">
                <div class="text-xs text-ink-400 font-semibold uppercase tracking-wider">Total Tagihan</div>
                <div class="text-3xl sm:text-4xl font-black text-up-primary tabular-nums">
                    Rp {{ number_format($orderTotal, 0, ',', '.') }}
                </div>
                <div class="p-3.5 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-xs text-ink-700 dark:text-amber-200 text-left">
                    <p class="font-bold flex items-center gap-1.5 mb-1">
                        <span>⏳</span> Menunggu Pembayaran
                    </p>
                    <p class="text-[11px] leading-relaxed text-ink-500 dark:text-ink-400">
                        Sistem kasir & cabang telah menerima daftar suku cadang Anda.
                        Bayar online sekarang lewat menu <strong class="text-ink-700 dark:text-amber-100">Akun Saya → Pesanan</strong>
                        (transfer bank, QRIS, atau e-wallet) agar pesanan segera disiapkan —
                        atau selesaikan pembayaran saat ambil barang / lewat instruksi resmi WhatsApp toko.
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap gap-3 justify-center pt-2">
                <a href="{{ route('customer.account') }}?tab=orders&bayar={{ $orderNo }}"
                   class="px-6 py-3.5 rounded-2xl bg-up-primary text-white font-bold text-xs hover:bg-up-primary-dark transition-[background-color,transform] duration-140 min-h-[44px] flex items-center active:scale-[0.97] shadow-sm shadow-up-primary/25">
                    ⚡ Bayar Sekarang
                </a>
                <a href="{{ route('customer.account') }}?tab=orders"
                   class="px-6 py-3.5 rounded-2xl bg-white dark:bg-ink-900 border border-ink-200/80 dark:border-white/10 text-ink-700 dark:text-ink-300 font-bold text-xs hover:bg-ink-50 transition-[background-color,transform] duration-140 min-h-[44px] flex items-center active:scale-[0.97]">
                    Lihat Status Pesanan
                </a>
                <a href="{{ url('/') }}"
                   class="px-6 py-3.5 rounded-2xl bg-white dark:bg-ink-900 border border-ink-200/80 dark:border-white/10 text-ink-700 dark:text-ink-300 font-bold text-xs hover:bg-ink-50 transition-[background-color,transform] duration-140 min-h-[44px] flex items-center active:scale-[0.97]">
                    Lanjut Belanja
                </a>
            </div>
        </div>
    @elseif($mode === 'checkout')
        <!-- ===== CHECKOUT (Refined Clean 2-Column or Stacked Summary) ===== -->
        <div class="space-y-4 sm:space-y-5 lg:space-y-6">
            <div class="flex items-center justify-between pb-2">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-ink-900 dark:text-white">Penyelesaian Pesanan</h1>
                    <p class="text-xs text-ink-500 dark:text-ink-400">Pastikan detail penerima dan cabang penjemputan telah sesuai.</p>
                </div>
                <a href="{{ route('cart') }}" class="text-xs font-semibold text-up-primary hover:underline flex items-center gap-1">
                    ← Kembali ke Keranjang
                </a>
            </div>

            @auth('customer')
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                    <!-- Form Column -->
                    <div class="lg:col-span-7 space-y-5">
                        <!-- Buyer Profile Card -->
                        <div class="bg-white dark:bg-ink-900 rounded-3xl border border-ink-100/80 dark:border-white/10 p-5 sm:p-6 shadow-[0_4px_20px_rgba(0,0,0,0.02)] space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-ink-400 dark:text-ink-500">Profil Pelanggan</span>
                                <span class="text-[10px] font-bold bg-up-mint/10 text-up-mint px-2.5 py-0.5 rounded-full">TERVERIFIKASI</span>
                            </div>
                            <h3 class="text-base font-bold text-ink-900 dark:text-white">{{ $pelanggan->nama }}</h3>
                            <p class="text-xs text-ink-500 dark:text-ink-400 font-mono">{{ $pelanggan->telepon }}</p>
                        </div>

                        <!-- Delivery / Pickup Method Selection -->
                        <div class="bg-white dark:bg-ink-900 rounded-3xl border border-ink-100/80 dark:border-white/10 p-5 sm:p-6 shadow-[0_4px_20px_rgba(0,0,0,0.02)] space-y-4">
                            <span class="text-xs font-bold uppercase tracking-wider text-ink-400 dark:text-ink-500">Pilihan Pengambilan</span>
                            <div class="flex flex-col sm:flex-row gap-3">
                                <button
                                    type="button"
                                    wire:click="$set('metodeAmbil', 'ambil_ke_toko')"
                                    class="flex-1 p-3.5 sm:p-4 rounded-2xl border-2 text-left transition-[border-color,background-color,transform] duration-140 cursor-pointer active:scale-[0.98] {{ $metodeAmbil === 'ambil_ke_toko' ? 'border-up-primary bg-up-primary/5 dark:bg-up-primary/10' : 'border-ink-200/80 dark:border-white/10 hover:border-ink-400' }}">
                                    <div class="text-lg">🏬</div>
                                    <div class="font-bold text-ink-900 dark:text-white text-xs sm:text-sm mt-1">Ambil di Toko</div>
                                    <div class="text-[11px] text-ink-400 mt-0.5">Bebas biaya ongkir</div>
                                </button>
                                <button
                                    type="button"
                                    wire:click="$set('metodeAmbil', 'pengiriman')"
                                    class="flex-1 p-3.5 sm:p-4 rounded-2xl border-2 text-left transition-[border-color,background-color,transform] duration-140 cursor-pointer active:scale-[0.98] {{ $metodeAmbil === 'pengiriman' ? 'border-up-primary bg-up-primary/5 dark:bg-up-primary/10' : 'border-ink-200/80 dark:border-white/10 hover:border-ink-400' }}">
                                    <div class="text-lg">🚚</div>
                                    <div class="font-bold text-ink-900 dark:text-white text-xs sm:text-sm mt-1">Kirim Ekspedisi</div>
                                    <div class="text-[11px] text-ink-400 mt-0.5">Integrasi kurir Biteship</div>
                                </button>
                            </div>

                            @if($metodeAmbil === 'pengiriman')
                                <!-- Form Alamat Pengiriman -->
                                <div class="p-4 rounded-2xl bg-up-primary/5 border border-up-primary/20 space-y-3">
                                    <div class="text-xs font-bold text-up-primary uppercase tracking-wider flex items-center gap-1.5">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        <span>Tujuan Pengiriman</span>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[11px] font-bold text-ink-600 dark:text-ink-400 mb-1">Nama Penerima</label>
                                            <input type="text" wire:model="namaPenerima" placeholder="Nama lengkap penerima" class="w-full px-3 py-2 rounded-xl bg-white dark:bg-ink-950 border border-ink-200 dark:border-white/10 text-base sm:text-xs font-semibold text-ink-900 dark:text-white outline-none focus:border-up-primary" />
                                            @error('namaPenerima') <span class="text-[10px] text-up-red font-semibold mt-1 block">{{ $message }}</span> @enderror
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold text-ink-600 dark:text-ink-400 mb-1">Nomor WhatsApp / HP</label>
                                            <input type="text" wire:model="teleponPenerima" placeholder="08xxxxxxxxxx" class="w-full px-3 py-2 rounded-xl bg-white dark:bg-ink-950 border border-ink-200 dark:border-white/10 text-base sm:text-xs font-semibold text-ink-900 dark:text-white outline-none focus:border-up-primary font-mono" />
                                            @error('teleponPenerima') <span class="text-[10px] text-up-red font-semibold mt-1 block">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-bold text-ink-600 dark:text-ink-400 mb-1">Alamat Lengkap <span class="text-up-red">*</span></label>
                                        <textarea wire:model="alamatPengiriman" rows="2" placeholder="Jalan, nomor rumah, RT/RW, kelurahan, kecamatan, kota, kode pos" class="w-full px-3 py-2 rounded-xl bg-white dark:bg-ink-950 border border-ink-200 dark:border-white/10 text-base sm:text-xs text-ink-900 dark:text-white outline-none focus:border-up-primary"></textarea>
                                        @error('alamatPengiriman') <span class="text-[10px] text-up-red font-semibold mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            @endif

                            <!-- Branch selector -->
                            <div class="space-y-1.5 pt-2">
                                <label class="block text-xs font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider">
                                    Pilih Lokasi Cabang
                                </label>
                                <div class="relative w-full min-w-0">
                                    <select wire:model.live="selectedCabangId"
                                            class="w-full min-w-0 max-w-full truncate appearance-none px-4 py-3 pr-8 rounded-2xl bg-ink-50 dark:bg-ink-950 border border-ink-200/80 dark:border-white/10 text-base sm:text-sm font-semibold text-ink-900 dark:text-white outline-none focus:border-up-primary transition-[border-color] duration-140 min-h-[44px] cursor-pointer">
                                        @foreach($cabangs as $c)
                                            <option value="{{ $c->id }}">{{ $c->nama }} ({{ $c->kota ?? $c->alamat }})</option>
                                        @endforeach
                                    </select>
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-ink-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <!-- Note textarea -->
                            <div class="space-y-1.5 pt-2">
                                <label class="block text-xs font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider">
                                    Catatan Tambahan (Opsional)
                                </label>
                                <textarea wire:model="catatan" rows="2"
                                          placeholder="Contoh: Saya ambil siang ini sekitar jam 2..."
                                          class="w-full min-w-0 px-4 py-3 rounded-2xl bg-ink-50 dark:bg-ink-950 border border-ink-200/80 dark:border-white/10 text-base sm:text-sm text-ink-900 dark:text-white placeholder-ink-400 outline-none focus:border-up-primary transition-[border-color] duration-140"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Order Summary Column -->
                    <div class="lg:col-span-5 space-y-5 lg:sticky lg:top-24">
                        <div class="bg-white dark:bg-ink-900 rounded-3xl border border-ink-100/80 dark:border-white/10 p-5 sm:p-6 shadow-[0_4px_20px_rgba(0,0,0,0.02)] space-y-4">
                            <span class="text-xs font-bold uppercase tracking-wider text-ink-400 dark:text-ink-500">Rincian Belanja</span>
                            <div class="space-y-3 max-h-72 overflow-y-auto pr-1">
                                @foreach($cart as $itemKey => $item)
                                    @php $tampil = $hargaTampilan[$itemKey] ?? []; @endphp
                                    <div class="flex items-center justify-between gap-3 py-2 border-b border-ink-100/60 dark:border-white/[0.06] last:border-0">
                                        @include('modules.marketplace.livewire.partials.product-image-preview', [
                                            'url' => $item['gambar_preview'] ?? $item['gambar'] ?? null,
                                            'nama' => $item['name'],
                                            'class' => 'w-10 h-10 sm:w-12 sm:h-12',
                                        ])
                                        <div class="min-w-0 space-y-0.5">
                                            <p class="text-xs font-bold text-ink-900 dark:text-white truncate">{{ $item['name'] }}</p>
                                            @if(!empty($item['catatan']))
                                                <p class="text-[10px] font-semibold text-up-primary truncate flex items-center gap-1">
                                                    <span>📱 {{ $item['catatan'] }}</span>
                                                </p>
                                            @endif
                                            <div class="flex items-center gap-1.5 flex-wrap text-[11px]">
                                                <span class="text-ink-400 font-semibold">
                                                    {{ (int) ($tampil['qty_pcs'] ?? $item['qty']) }} pcs ×
                                                    <span class="line-through text-ink-400 tabular-nums">Rp {{ number_format((float) ($tampil['asli_per_pcs'] ?? 0), 0, ',', '.') }}</span>
                                                    @if(($tampil['potongan_per_pcs'] ?? 0) > 0)
                                                        <span class="text-up-mint font-bold tabular-nums">Rp {{ number_format((float) $tampil['akhir_per_pcs'], 0, ',', '.') }}/PCS</span>
                                                    @endif
                                                </span>
                                                @if(!empty($item['breakdown']))
                                                    <span class="px-1.5 py-0.2 rounded bg-up-mint/10 text-up-mint text-[9px] font-bold" title="Box penuh pakai harga box, sisa pcs pakai harga retail">
                                                        {{ $item['breakdown'] }}
                                                    </span>
                                                @endif
                                                @if(($tampil['potongan_total'] ?? 0) > 0)
                                                    <span class="px-1.5 py-0.2 rounded bg-up-mint/15 text-up-mint text-[9px] font-black">
                                                        {{ $tampil['label'] }} -Rp {{ number_format((float) $tampil['potongan_total'], 0, ',', '.') }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            @if(($tampil['potongan_total'] ?? 0) > 0)
                                                <div class="text-[9px] text-ink-400 line-through tabular-nums">
                                                    Rp {{ number_format((float) $tampil['asli_total'], 0, ',', '.') }}
                                                </div>
                                            @endif
                                            <div class="text-xs font-bold text-ink-900 dark:text-white tabular-nums">
                                                Rp {{ number_format((float) ($tampil['akhir_total'] ?? ($item['harga'] * $item['qty'])), 0, ',', '.') }}
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="pt-4 border-t border-ink-100/80 dark:border-white/10 space-y-2 text-xs">
                                @if(($diskonTier ?? 0) > 0)
                                    <div class="flex justify-between items-center text-ink-500">
                                        <span>Subtotal Retail</span>
                                        <span class="tabular-nums line-through">Rp {{ number_format($subtotalRetail, 0, ',', '.') }}</span>
                                    </div>
                                    @if(($potonganKemasan ?? 0) > 0)
                                        <div class="flex justify-between items-center text-up-mint font-semibold">
                                            <span>Potongan Kemasan / Per Box</span>
                                            <span class="tabular-nums">- Rp {{ number_format($potonganKemasan, 0, ',', '.') }}</span>
                                        </div>
                                    @endif
                                    @if(($potonganMember ?? 0) > 0)
                                        <div class="flex justify-between items-center text-up-mint font-semibold">
                                            <span>Potongan Member ({{ $pelanggan->tierMembership?->nama ?? ($pelanggan->tipe_konsumen === 'reseller' ? 'Reseller' : 'Member') }})</span>
                                            <span class="tabular-nums">- Rp {{ number_format($potonganMember, 0, ',', '.') }}</span>
                                        </div>
                                    @endif
                                    <div class="flex justify-between items-center text-ink-700 dark:text-ink-300 font-bold pt-1 border-t border-ink-100/60 dark:border-white/5">
                                        <span>Subtotal Bersih</span>
                                        <span class="tabular-nums text-ink-900 dark:text-white">Rp {{ number_format($subtotal, 0, ',', '.') }}</span>
                                    </div>
                                @else
                                    <div class="flex justify-between items-center text-ink-600 dark:text-ink-400">
                                        <span>Subtotal</span>
                                        <span class="font-bold tabular-nums text-ink-900 dark:text-white">Rp {{ number_format($subtotal, 0, ',', '.') }}</span>
                                    </div>
                                @endif

                                @if(($diskonPartai ?? 0) > 0)
                                    <div class="flex justify-between items-center text-up-accent font-semibold">
                                        <span class="flex items-center gap-1">
                                            <span>📦</span>
                                            <span>{{ $partaiInfo['alasan'] ?? 'Diskon Pembelian Partai' }}</span>
                                        </span>
                                        <span class="tabular-nums">- Rp {{ number_format($diskonPartai, 0, ',', '.') }}</span>
                                    </div>
                                    <div class="flex justify-between items-center text-ink-700 dark:text-ink-300 font-bold pt-1 border-t border-ink-100/60 dark:border-white/5">
                                        <span>DPP (Dasar Pengenaan Pajak)</span>
                                        <span class="tabular-nums text-ink-900 dark:text-white">Rp {{ number_format(max(0, $subtotal - $diskonPartai), 0, ',', '.') }}</span>
                                    </div>
                                @endif

                                @if(!empty($pajakPreview['enabled']) && $pajakPreview['ppn_nominal'] > 0)
                                    <div class="flex justify-between items-center text-ink-500">
                                        <span>PPN ({{ $pajakPreview['ppn_percent'] }}%)</span>
                                        <span class="font-semibold tabular-nums">+ Rp {{ number_format($pajakPreview['ppn_nominal'], 0, ',', '.') }}</span>
                                    </div>
                                @endif
                                <div class="flex justify-between items-center pt-3 border-t border-ink-100/80 dark:border-white/10">
                                    <span class="font-bold text-ink-900 dark:text-white text-sm">Total Akhir</span>
                                    <span class="text-xl font-black text-up-primary tabular-nums">
                                        Rp {{ number_format($pajakPreview['total_akhir'] ?? $subtotal, 0, ',', '.') }}
                                    </span>
                                </div>
                            </div>

                            @if(($potonganKemasan ?? 0) > 0)
                                <div class="p-2.5 rounded-2xl bg-up-mint/10 border border-up-mint/20 text-center text-xs font-bold text-up-mint">
                                    📦 Anda menghemat Rp {{ number_format($potonganKemasan, 0, ',', '.') }} dari potongan kemasan / per box.
                                </div>
                            @endif
                            @if(($potonganMember ?? 0) > 0)
                                <div class="p-2.5 rounded-2xl bg-up-mint/10 border border-up-mint/20 text-center text-xs font-bold text-up-mint">
                                    🎉 Anda menghemat Rp {{ number_format($potonganMember, 0, ',', '.') }} dari potongan member.
                                </div>
                            @endif

                            @if(($potensiKomisi ?? 0) > 0)
                                <div class="p-2.5 rounded-2xl bg-up-amber/10 border border-up-amber/20 text-center text-xs font-bold text-up-amber">
                                    💰 Akumulasi komisi mitra +Rp {{ number_format($potensiKomisi, 0, ',', '.') }} akan tersimpan ke saldo komisi Anda setelah pesanan terverifikasi!
                                </div>
                            @endif

                            @if(($diskonPartai ?? 0) > 0)
                                <div class="p-2.5 rounded-2xl bg-up-accent/10 border border-up-accent/20 text-center text-xs font-bold text-up-accent">
                                    📦 Potongan Pembelian Partai hemat Rp {{ number_format($diskonPartai, 0, ',', '.') }} telah diterapkan!
                                </div>
                            @endif

                            <button
                                type="button"
                                wire:click="placeOrder"
                                class="w-full py-4 rounded-2xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-sm shadow-sm shadow-up-primary/25 transition-[background-color,transform] duration-140 cursor-pointer active:scale-[0.98] min-h-[48px] flex items-center justify-center gap-2">
                                <span>Konfirmasi & Buat Pesanan</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            @else
                <!-- Guest View: Clean Gate -->
                <div class="text-center py-16 bg-white dark:bg-ink-900 rounded-3xl border border-ink-100/80 dark:border-white/10 p-8 space-y-4">
                    <div class="w-16 h-16 mx-auto rounded-3xl bg-up-primary/10 text-up-primary flex items-center justify-center">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <div class="space-y-1">
                        <h2 class="text-lg font-bold text-ink-900 dark:text-white">Masuk untuk Menyelesaikan Pesanan</h2>
                        <p class="text-xs text-ink-500 dark:text-ink-400 max-w-sm mx-auto">
                            Dibutuhkan nomor telepon aktif untuk verifikasi ketersediaan suku cadang dan update status garansi.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3 justify-center pt-2">
                        <a href="{{ route('customer.login') }}"
                           class="px-6 py-3.5 rounded-2xl bg-up-primary text-white font-bold text-xs hover:bg-up-primary-dark transition-[background-color,transform] duration-140 min-h-[44px] flex items-center active:scale-[0.97]">
                            Masuk Akun
                        </a>
                        <a href="{{ route('customer.register') }}"
                           class="px-6 py-3.5 rounded-2xl bg-ink-100 dark:bg-white/10 text-ink-800 dark:text-white font-bold text-xs hover:bg-ink-200 transition-[background-color,transform] duration-140 min-h-[44px] flex items-center active:scale-[0.97]">
                            Daftar Pelanggan Baru
                        </a>
                    </div>
                </div>
            @endauth
        </div>
    @else
        <!-- ===== CART (Clean Table & Order Review) ===== -->
        <div class="space-y-6">
            <div class="flex items-center justify-between pb-2">
                <div>
                    <h1 class="text-xl sm:text-2xl lg:text-3xl font-black tracking-tight text-ink-900 dark:text-white">Keranjang Belanja</h1>
                    <p class="text-xs text-ink-500 dark:text-ink-400">Periksa suku cadang sebelum melanjutkan ke proses pengambilan.</p>
                </div>
            </div>

            @if(count($cart) === 0)
                <div class="py-12 bg-white dark:bg-ink-900 rounded-3xl border border-ink-100/80 dark:border-white/10 p-8">
                    <x-ui.empty-state
                        icon="inbox"
                        title="Keranjang Anda Masih Kosong"
                        description="Cari layar LCD, baterai pengganti, atau pesan jasa servis teknisi profesional."
                    >
                        <x-slot:action>
                            <span class="text-[10px] text-ink-400">Gunakan pencarian di bawah untuk menambah produk.</span>
                        </x-slot:action>
                    </x-ui.empty-state>

                    @if($mode === 'cart')
                        @include('modules.marketplace.livewire.partials.cart-quick-search')
                    @endif

                    @include('modules.marketplace.livewire.partials.cart-quick-products')
                </div>
            @else
                <div class="bg-white dark:bg-ink-900 rounded-3xl border border-ink-100/80 dark:border-white/10 p-3 sm:p-4 lg:p-7 shadow-[0_4px_24px_rgba(0,0,0,0.02)] space-y-2">
                    <div class="space-y-4">
                        @foreach($cart as $itemKey => $item)
                            @php $tampil = $hargaTampilan[$itemKey] ?? []; @endphp
                            <div class="py-2 sm:py-2.5 border-b border-ink-100/70 dark:border-white/[0.06] last:border-0 min-w-0 w-full lg:flex lg:items-center lg:justify-between lg:gap-4">
                                <div class="flex items-start gap-2 sm:gap-3 lg:flex-1 lg:items-center">
                                    @include('modules.marketplace.livewire.partials.product-image-preview', [
                                        'url' => $item['gambar_preview'] ?? $item['gambar'] ?? null,
                                        'nama' => $item['name'],
                                        'class' => 'w-12 h-12 sm:w-14 sm:h-14',
                                    ])
                                    <div class="min-w-0 flex-1 space-y-1">
                                        <h4 class="text-[11px] sm:text-xs lg:text-sm font-bold leading-snug break-words text-ink-900 dark:text-white">{{ $item['name'] }}</h4>
                                        <div class="flex items-center gap-1.5 flex-wrap text-[10px] sm:text-[11px]">
                                            @if(!empty($item['sku']))
                                                <span class="font-mono text-ink-400">{{ $item['sku'] }}</span>
                                            @endif
                                            <span class="font-bold px-1.5 py-0.5 rounded bg-ink-100 dark:bg-white/5 text-ink-500 dark:text-ink-300">PCS</span>
                                            @if(!empty($item['kategori']))
                                                <span class="px-1.5 py-0.5 rounded bg-indigo-500/10 text-indigo-600 dark:text-indigo-300 font-bold">{{ $item['kategori'] }}</span>
                                            @endif
                                            @if(!empty($item['kualitas']))
                                                <span class="px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-700 dark:text-amber-300 font-bold">{{ $item['kualitas'] }}</span>
                                            @endif
                                            @if(!empty($item['brand']))
                                                <span class="px-1.5 py-0.5 rounded bg-ink-100 dark:bg-white/5 text-ink-500 dark:text-ink-300 font-bold">{{ $item['brand'] }}</span>
                                            @endif
                                            @if(!empty($item['kompatibel']))
                                                <span class="px-1.5 py-0.5 rounded bg-up-mint/10 text-up-mint font-bold truncate max-w-full" title="{{ $item['kompatibel'] }}">{{ $item['kompatibel'] }}</span>
                                            @endif
                                            @if(!empty($item['breakdown']))
                                                <span class="px-1.5 py-0.5 rounded bg-up-mint/10 text-up-mint font-bold" title="Box penuh pakai harga box, sisa pcs pakai harga retail">
                                                    {{ $item['breakdown'] }}
                                                </span>
                                            @endif
                                            @if(($tampil['potongan_box'] ?? 0) > 0 && ! ($tampil['is_kemasan'] ?? false))
                                                <button
                                                    type="button"
                                                    wire:click="updateQty('{{ $itemKey }}', {{ (int) ($tampil['box_faktor'] ?? 10) }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="updateQty('{{ $itemKey }}', {{ (int) ($tampil['box_faktor'] ?? 10) }})"
                                                    class="px-2 py-0.5 rounded-lg bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300 font-bold border border-amber-200/70 dark:border-amber-500/20 hover:bg-amber-100 dark:hover:bg-amber-500/20 transition-colors cursor-pointer disabled:opacity-50"
                                                    title="Klik untuk ubah ke 1 box">
                                                    💡 Beli 1 box ({{ (int) $tampil['box_faktor'] }} pcs) hemat Rp {{ number_format((float) $tampil['potongan_box'], 0, ',', '.') }}
                                                </button>
                                            @endif
                                            @if(($tampil['is_kemasan'] ?? false))
                                                <span class="text-up-mint font-medium">• Harga Kemasan</span>
                                            @elseif($item['alasan_harga'] !== 'Harga Retail Standar')
                                                <span class="text-up-mint font-medium">• {{ $item['alasan_harga'] }}</span>
                                            @endif
                                            @if(!empty($item['variant_name']))
                                                <span class="text-ink-400 hidden lg:inline">• {{ $item['variant_name'] }}</span>
                                            @endif
                                            @if(!empty($item['catatan']))
                                                <span class="inline-flex items-center gap-1 font-bold px-2 py-0.5 rounded-md bg-up-primary/10 text-up-primary dark:bg-up-primary/20 dark:text-white border border-up-primary/20">
                                                    📱 {{ $item['catatan'] }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        wire:click.prevent="hapus('{{ $itemKey }}')"
                                        aria-label="Hapus {{ $item['name'] }} dari keranjang"
                                        class="flex w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-ink-100/60 dark:bg-white/5 text-ink-400 hover:text-up-red hover:bg-up-red/10 items-center justify-center text-xs transition-colors cursor-pointer flex-shrink-0"
                                        title="Hapus dari keranjang">
                                        ✕
                                    </button>
                                </div>

                                <div class="mt-2 lg:mt-0 flex items-center justify-between sm:justify-end gap-3 sm:gap-5 w-full sm:w-auto min-w-0">
                                    <!-- Stepper button & manual input -->
                                    <div class="flex items-center gap-0.5 rounded-full bg-ink-100/80 dark:bg-white/[0.06] p-0.5 ring-1 ring-ink-200/70 dark:ring-white/10 shadow-inner flex-shrink-0">
                                        <button
                                            type="button"
                                            wire:click="updateQty('{{ $itemKey }}', {{ max(1, (int) $item['qty'] - 1) }})"
                                            wire:target="updateQty('{{ $itemKey }}', {{ max(1, (int) $item['qty'] - 1) }})"
                                            wire:loading.attr="disabled"
                                            wire:loading.class="bg-up-primary/15 text-up-primary ring-1 ring-up-primary/30"
                                            @if((int) $item['qty'] <= 1) disabled @endif
                                            aria-label="Kurangi jumlah"
                                            class="w-8 h-8 sm:w-7 sm:h-7 rounded-full bg-white dark:bg-ink-900 ring-1 ring-black/5 dark:ring-white/5 shadow-sm hover:bg-ink-50 dark:hover:bg-white/10 font-bold text-ink-700 dark:text-ink-300 transition-all duration-150 flex items-center justify-center cursor-pointer active:scale-90 text-sm disabled:opacity-35 disabled:cursor-not-allowed disabled:hover:bg-white dark:disabled:hover:bg-ink-900">
                                            <span wire:loading.remove wire:target="updateQty('{{ $itemKey }}', {{ max(1, (int) $item['qty'] - 1) }})">−</span>
                                            <svg wire:loading wire:target="updateQty('{{ $itemKey }}', {{ max(1, (int) $item['qty'] - 1) }})" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                                                <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"></path>
                                            </svg>
                                        </button>
                                        <input
                                            type="number"
                                            min="1"
                                            max="{{ (int) ($item['stok_max'] ?? 9999) }}"
                                            value="{{ $item['qty'] }}"
                                            wire:change="updateQty('{{ $itemKey }}', $event.target.value)"
                                            class="w-8 sm:w-9 text-center font-black text-xs text-ink-900 dark:text-white bg-transparent outline-none border-0 focus:ring-0 p-0 tabular-nums [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                            aria-label="Jumlah pesanan"
                                        />
                                        <button
                                            type="button"
                                            wire:click="updateQty('{{ $itemKey }}', {{ (int) $item['qty'] + 1 }})"
                                            wire:target="updateQty('{{ $itemKey }}', {{ (int) $item['qty'] + 1 }})"
                                            wire:loading.attr="disabled"
                                            wire:loading.class="bg-up-primary/15 text-up-primary ring-1 ring-up-primary/30"
                                            aria-label="Tambah jumlah"
                                            class="w-8 h-8 sm:w-7 sm:h-7 rounded-full bg-white dark:bg-ink-900 ring-1 ring-black/5 dark:ring-white/5 shadow-sm hover:bg-up-primary/10 hover:text-up-primary dark:hover:bg-up-primary/20 font-bold text-ink-700 dark:text-ink-300 transition-all duration-150 flex items-center justify-center cursor-pointer active:scale-90 text-sm">
                                            <span wire:loading.remove wire:target="updateQty('{{ $itemKey }}', {{ (int) $item['qty'] + 1 }})">+</span>
                                            <svg wire:loading wire:target="updateQty('{{ $itemKey }}', {{ (int) $item['qty'] + 1 }})" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                                                <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"></path>
                                            </svg>
                                        </button>
                                    </div>

                                    <div class="text-right sm:w-32 min-w-0 space-y-0.5">
                                        <span class="block text-[9px] sm:text-[10px] text-ink-400 tabular-nums whitespace-nowrap">
                                            @
                                            @if(($tampil['potongan_per_pcs'] ?? 0) > 0)
                                                <span class="line-through">Rp {{ number_format((float) $tampil['asli_per_pcs'], 0, ',', '.') }}</span>
                                                <span class="text-up-mint font-bold">Rp {{ number_format((float) $tampil['akhir_per_pcs'], 0, ',', '.') }}</span>
                                            @else
                                                Rp {{ number_format((float) ($tampil['akhir_per_pcs'] ?? $item['harga']), 0, ',', '.') }}
                                            @endif
                                            /PCS
                                        </span>
                                        <span class="text-xs sm:text-sm font-black text-ink-900 dark:text-white tabular-nums block whitespace-nowrap">
                                            Rp {{ number_format((float) ($tampil['akhir_total'] ?? ($item['harga'] * $item['qty'])), 0, ',', '.') }}
                                        </span>
                                        @if(($tampil['potongan_total'] ?? 0) > 0)
                                            <div class="flex items-center justify-end gap-1 text-[10px] flex-wrap">
                                                <span class="line-through text-ink-400 tabular-nums">
                                                    Rp {{ number_format((float) $tampil['asli_total'], 0, ',', '.') }}
                                                </span>
                                                <span class="px-1 py-0.2 rounded bg-up-mint/15 text-up-mint font-black">
                                                    {{ $tampil['label'] }} -Rp {{ number_format((float) $tampil['potongan_total'], 0, ',', '.') }}
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($mode === 'cart')
                        @include('modules.marketplace.livewire.partials.cart-quick-search')
                        @include('modules.marketplace.livewire.partials.cart-quick-products')
                    @endif

                    <div class="pt-4 sm:pt-5 lg:pt-6 border-t border-ink-100/80 dark:border-white/10 flex flex-col sm:flex-row items-center justify-between gap-3 sm:gap-4">
                        <div class="w-full sm:w-auto">
                            <div class="flex items-baseline justify-between sm:block">
                                <div>
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-ink-400">Total belanja</div>
                                    <div class="text-2xl font-black text-up-primary tabular-nums">
                                        Rp {{ number_format(max(0, $subtotal - ($diskonPartai ?? 0)), 0, ',', '.') }}
                                    </div>
                                </div>
                                <span class="text-[10px] text-ink-400">{{ $jumlahItem }} suku cadang</span>
                            </div>

                            <details class="mt-2 rounded-2xl bg-ink-50/70 dark:bg-white/[0.03] border border-ink-200/70 dark:border-white/5 overflow-hidden">
                                <summary class="px-3 py-2 text-[11px] font-bold text-ink-600 dark:text-ink-300 cursor-pointer select-none flex items-center justify-between">
                                    <span>Detail harga</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </summary>
                                <div class="px-3 pb-3 space-y-2 text-[11px] border-t border-ink-200/60 dark:border-white/5 pt-2">
                                    @if(($potonganKemasan ?? 0) > 0 || ($potonganMember ?? 0) > 0)
                                        <div class="flex justify-between gap-3 text-ink-500">
                                            <span>Subtotal Retail</span>
                                            <span class="line-through tabular-nums">Rp {{ number_format($subtotalRetail, 0, ',', '.') }}</span>
                                        </div>
                                    @endif
                                    @if(($potonganKemasan ?? 0) > 0)
                                        <div class="flex justify-between gap-3 text-up-mint font-semibold">
                                            <span>Potongan Kemasan / Per Box</span>
                                            <span class="tabular-nums">- Rp {{ number_format($potonganKemasan, 0, ',', '.') }}</span>
                                        </div>
                                    @endif
                                    @if(($potonganMember ?? 0) > 0)
                                        <div class="flex justify-between gap-3 text-up-mint font-semibold">
                                            <span>Potongan Member</span>
                                            <span class="tabular-nums">- Rp {{ number_format($potonganMember, 0, ',', '.') }}</span>
                                        </div>
                                    @endif
                                    @if(($diskonPartai ?? 0) > 0)
                                        <div class="flex justify-between gap-3 text-ink-500">
                                            <span>Subtotal</span>
                                            <span class="tabular-nums">Rp {{ number_format($subtotal, 0, ',', '.') }}</span>
                                        </div>
                                        <div class="flex justify-between gap-3 text-up-accent font-semibold">
                                            <span>Potongan Partai ({{ $partaiInfo['persen'] ?? 0 }}%)</span>
                                            <span class="tabular-nums">- Rp {{ number_format($diskonPartai, 0, ',', '.') }}</span>
                                        </div>
                                    @endif
                                    @if(($potensiKomisi ?? 0) > 0)
                                        <div class="flex justify-between gap-3 text-up-amber font-semibold">
                                            <span>Komisi Mitra</span>
                                            <span class="tabular-nums">+ Rp {{ number_format($potensiKomisi, 0, ',', '.') }}</span>
                                        </div>
                                    @endif
                                    <div class="flex justify-between gap-3 text-ink-900 dark:text-white font-bold pt-2 border-t border-ink-200/60 dark:border-white/5">
                                        <span>Total</span>
                                        <span class="tabular-nums">Rp {{ number_format(max(0, $subtotal - ($diskonPartai ?? 0)), 0, ',', '.') }}</span>
                                    </div>
                                </div>
                            </details>

                            <div class="hidden text-xs text-ink-500 dark:text-ink-400">
                                Total pesanan terhitung untuk <strong class="text-ink-900 dark:text-white">{{ $jumlahItem }} suku cadang</strong>
                                @if(($potonganKemasan ?? 0) > 0)
                                    <div class="mt-1 text-up-mint font-bold text-[11px]">
                                        📦 Anda menghemat Rp {{ number_format($potonganKemasan, 0, ',', '.') }} dari potongan kemasan / per box
                                    </div>
                                @endif
                                @if(($potonganMember ?? 0) > 0)
                                    <div class="mt-1 text-up-mint font-bold text-[11px]">
                                        🎉 Anda menghemat Rp {{ number_format($potonganMember, 0, ',', '.') }} dari potongan member
                                    </div>
                                @endif
                                @if(($potensiKomisi ?? 0) > 0)
                                    <div class="mt-1 text-up-amber font-bold text-[11px] flex items-center gap-1">
                                        <span>💰</span>
                                        <span>Akumulasi komisi mitra: +Rp {{ number_format($potensiKomisi, 0, ',', '.') }} (Harga Retail)</span>
                                    </div>
                                @endif
                                @if(($diskonPartai ?? 0) > 0)
                                    <div class="mt-1 text-up-accent font-bold text-[11px] flex items-center gap-1">
                                        <span>📦</span>
                                        <span>Potongan partai: {{ $partaiInfo['alasan'] ?? '' }} (-Rp {{ number_format($diskonPartai, 0, ',', '.') }})</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="hidden flex-col items-end gap-1">
                            @if(($potonganKemasan ?? 0) > 0 || ($potonganMember ?? 0) > 0)
                                <div class="flex items-center gap-2 text-xs text-ink-500">
                                    <span>Subtotal Retail:</span>
                                    <span class="line-through tabular-nums">Rp {{ number_format($subtotalRetail, 0, ',', '.') }}</span>
                                </div>
                            @endif
                            @if(($potonganKemasan ?? 0) > 0)
                                <div class="flex items-center gap-2 text-xs text-up-mint font-semibold">
                                    <span>Potongan Kemasan / Per Box:</span>
                                    <span class="tabular-nums">- Rp {{ number_format($potonganKemasan, 0, ',', '.') }}</span>
                                </div>
                            @endif
                            @if(($potonganMember ?? 0) > 0)
                                <div class="flex items-center gap-2 text-xs text-up-mint font-semibold">
                                    <span>Potongan Member:</span>
                                    <span class="tabular-nums">- Rp {{ number_format($potonganMember, 0, ',', '.') }}</span>
                                </div>
                            @endif
                            @if(($diskonPartai ?? 0) > 0)
                                <div class="flex items-center gap-2 text-xs text-ink-500">
                                    <span>Subtotal:</span>
                                    <span class="tabular-nums">Rp {{ number_format($subtotal, 0, ',', '.') }}</span>
                                </div>
                                <div class="flex items-center gap-2 text-xs text-up-accent font-semibold">
                                    <span>Potongan Partai ({{ $partaiInfo['persen'] ?? 0 }}%):</span>
                                    <span class="tabular-nums">- Rp {{ number_format($diskonPartai, 0, ',', '.') }}</span>
                                </div>
                                <div class="flex items-baseline gap-2 pt-1 border-t border-ink-100/60 dark:border-white/5">
                                    <span class="text-xs font-semibold text-ink-400 uppercase tracking-wider">Total Belanja:</span>
                                    <span class="text-2xl font-black text-up-primary tabular-nums">
                                        Rp {{ number_format(max(0, $subtotal - $diskonPartai), 0, ',', '.') }}
                                    </span>
                                </div>
                            @else
                                <div class="flex items-baseline gap-2">
                                    <span class="text-xs font-semibold text-ink-400 uppercase tracking-wider">Subtotal:</span>
                                    <span class="text-2xl font-black text-up-primary tabular-nums">
                                        Rp {{ number_format($subtotal, 0, ',', '.') }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Sticky checkout bar (mobile/tablet/desktop) -->
                <div class="fixed inset-x-0 bottom-[calc(env(safe-area-inset-bottom,0px)+0.75rem)] z-40 pointer-events-none">
                    <div class="max-w-4xl mx-auto px-4 sm:px-6">
                        <button
                            type="button"
                            wire:click="lanjutCheckout"
                            wire:loading.attr="disabled"
                            class="pointer-events-auto w-full rounded-2xl bg-ink-900 dark:bg-white text-white dark:text-ink-950 px-4 py-3.5 flex items-center justify-between gap-3 shadow-2xl active:scale-[0.99] transition-transform cursor-pointer disabled:opacity-60">
                            <span class="text-left min-w-0">
                                <span class="block text-[9px] uppercase tracking-wider text-white/60 dark:text-ink-950/60 font-bold">Total belanja</span>
                                <span class="block text-sm font-black tabular-nums truncate">Rp {{ number_format(max(0, $subtotal - ($diskonPartai ?? 0)), 0, ',', '.') }}</span>
                            </span>
                            <span class="shrink-0 inline-flex items-center gap-1.5 text-xs font-bold">
                                Lanjut Checkout
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                            </span>
                        </button>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <!-- Lightbox preview gambar di keranjang -->
    <div x-show="cartPreview"
         x-cloak
         x-transition.opacity
         class="fixed inset-0 z-[9998] bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 cursor-pointer"
         @click="cartPreview = null; cartPreviewNama = ''">
        <div class="relative max-w-lg w-full max-h-[85vh] rounded-3xl bg-white dark:bg-ink-900 border border-white/10 p-3 shadow-2xl" @click.stop>
            <button type="button"
                    @click="cartPreview = null; cartPreviewNama = ''"
                    aria-label="Tutup preview"
                    class="absolute -top-3 -right-3 z-10 w-9 h-9 rounded-full bg-white dark:bg-ink-900 text-ink-900 dark:text-white border border-ink-200 dark:border-white/10 shadow-xl flex items-center justify-center text-sm font-bold hover:text-up-red cursor-pointer">
                ✕
            </button>
            <template x-if="cartPreview">
                <img :src="cartPreview" :alt="cartPreviewNama || 'Preview produk'" class="w-full h-full max-h-[74vh] object-contain rounded-2xl">
            </template>
            <p x-show="cartPreviewNama"
               x-text="cartPreviewNama"
               class="text-center text-[11px] font-bold text-ink-600 dark:text-ink-300 pt-2 break-words"></p>
        </div>
    </div>
</div>
