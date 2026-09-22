<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    @if($orderSuccess)
        <!-- ===== ORDER SUCCESS ===== -->
        <div class="max-w-lg mx-auto text-center py-10">
            <div class="w-16 h-16 mx-auto rounded-full bg-up-mint/15 flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-up-mint" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h1 class="text-2xl font-black text-ink-900">Pesanan Dibuat!</h1>
            <p class="text-sm text-ink-500 mt-2">Nomor pesanan <strong class="text-up-primary font-mono">{{ $orderNo }}</strong></p>
            <p class="text-3xl font-black text-up-primary tabular-nums mt-3">Rp {{ number_format($orderTotal, 0, ',', '.') }}</p>

            <div class="mt-6 p-4 rounded-2xl bg-up-amber/10 border border-up-amber/30 text-left">
                <p class="text-xs font-bold text-up-amber uppercase tracking-wider mb-1.5">⏳ Menunggu Pembayaran</p>
                <p class="text-xs text-ink-600 leading-relaxed">
                    Pembayaran melalui <strong>Duitku</strong> (VA / QRIS / e-wallet) akan aktif pada tahap integrasi pembayaran.
                    Stok akan dikunci dan pesanan diproses otomatis setelah pembayaran terverifikasi.
                </p>
            </div>

            <div class="flex gap-3 mt-6 justify-center">
                <a href="{{ route('shop') }}" class="px-5 py-3 rounded-xl bg-up-primary text-white font-bold text-sm hover:bg-up-primary-dark transition-colors min-h-[44px] flex items-center">Lanjut Belanja</a>
                <a href="{{ route('cart') }}" class="px-5 py-3 rounded-xl border border-ink-200 text-ink-700 font-bold text-sm hover:bg-ink-50 transition-colors min-h-[44px] flex items-center">Lihat Keranjang</a>
            </div>
        </div>
    @elseif($mode === 'checkout')
        <!-- ===== CHECKOUT ===== -->
        <div class="max-w-3xl mx-auto">
            <h1 class="text-2xl font-black text-ink-900 mb-6">Checkout</h1>

            @auth('customer')
                <div class="bg-white rounded-2xl border border-ink-100 p-5 mb-5 flex items-center justify-between">
                    <div>
                        <p class="text-xs text-ink-400 uppercase tracking-wider font-semibold">Pesanan atas nama</p>
                        <p class="font-bold text-ink-900">{{ $pelanggan->nama }}</p>
                        <p class="text-xs text-ink-500 font-mono">{{ $pelanggan->telepon }}</p>
                    </div>
                    <span class="text-[10px] font-bold bg-up-mint/10 text-up-mint px-2.5 py-1 rounded-full">AKUN AKTIF</span>
                </div>

                <!-- Items -->
                <div class="bg-white rounded-2xl border border-ink-100 p-5 mb-5">
                    <p class="text-xs text-ink-400 uppercase tracking-wider font-semibold mb-3">Ringkasan Pesanan</p>
                    <div class="space-y-3">
                        @foreach($cart as $idx => $item)
                            <div class="flex items-center justify-between gap-3 py-2 border-b border-ink-50 last:border-0">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <button wire:click="hapus({{ $item['variant_id'] ?? 'null' }})" class="text-ink-300 hover:text-up-red text-xs flex-shrink-0" title="Hapus">✕</button>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-ink-800 truncate">{{ $item['name'] }}</p>
                                        @if($item['variant_name'])<p class="text-[11px] text-ink-400">{{ $item['variant_name'] }}</p>@endif
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 flex-shrink-0">
                                    <div class="flex items-center gap-1 bg-ink-50 rounded-lg px-1.5 py-1">
                                        <button wire:click="updateQty({{ $item['variant_id'] ?? 'null' }}, {{ $item['qty'] - 1 }})" class="w-6 h-6 rounded-md text-ink-700 font-bold hover:bg-ink-100 cursor-pointer text-xs">−</button>
                                        <span class="w-6 text-center font-bold tabular-nums text-xs">{{ $item['qty'] }}</span>
                                        <button wire:click="updateQty({{ $item['variant_id'] ?? 'null' }}, {{ $item['qty'] + 1 }})" class="w-6 h-6 rounded-md text-ink-700 font-bold hover:bg-ink-100 cursor-pointer text-xs">+</button>
                                    </div>
                                    <span class="text-sm font-black text-ink-900 tabular-nums w-24 text-right">Rp {{ number_format($item['harga'] * $item['qty'], 0, ',', '.') }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex justify-between items-center pt-4 mt-2 border-t border-ink-100">
                        <span class="font-bold text-ink-900">Total</span>
                        <span class="text-xl font-black text-up-primary tabular-nums">Rp {{ number_format($subtotal, 0, ',', '.') }}</span>
                    </div>
                </div>

                <!-- Pilihan ambil -->
                <div class="bg-white rounded-2xl border border-ink-100 p-5 mb-5">
                    <p class="text-xs text-ink-400 uppercase tracking-wider font-semibold mb-3">Metode Pengambilan</p>
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <button wire:click="$set('metodeAmbil', 'ambil_ke_toko')" class="p-4 rounded-xl border-2 text-center transition-all cursor-pointer {{ $metodeAmbil === 'ambil_ke_toko' ? 'border-up-primary bg-up-primary/5' : 'border-ink-100 hover:border-ink-300' }}">
                            <p class="font-bold text-ink-900 text-sm">🏬 Ambil di Toko</p>
                            <p class="text-[11px] text-ink-500 mt-1">Gratis, pilih cabang terdekat</p>
                        </button>
                        <button wire:click="$set('metodeAmbil', 'pengiriman')" class="p-4 rounded-xl border-2 text-center transition-all cursor-pointer {{ $metodeAmbil === 'pengiriman' ? 'border-up-primary bg-up-primary/5' : 'border-ink-100 hover:border-ink-300' }}">
                            <p class="font-bold text-ink-900 text-sm">🚚 Dikirim</p>
                            <p class="text-[11px] text-ink-500 mt-1">Kurir via Biteship (Menyusul)</p>
                        </button>
                    </div>

                    <label class="block text-xs font-bold text-ink-700 uppercase tracking-wider mb-1.5">Cabang Pengambilan</label>
                    <select wire:model="selectedCabangId" class="w-full px-3.5 py-3 rounded-xl bg-white border border-ink-100 text-sm font-medium outline-none focus:border-up-primary">
                        @foreach($cabangs as $c)
                            <option value="{{ $c->id }}" class="text-ink-900">{{ $c->nama }} — {{ $c->alamat }}</option>
                        @endforeach
                    </select>

                    <label class="block text-xs font-bold text-ink-700 uppercase tracking-wider mt-4 mb-1.5">Catatan (opsional)</label>
                    <textarea wire:model="catatan" rows="2" class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-ink-100 text-sm outline-none focus:border-up-primary" placeholder="Catatan untuk cabang..."></textarea>
                </div>

                <button wire:click="placeOrder" class="w-full py-4 rounded-2xl bg-gradient-to-r from-up-primary to-indigo-600 text-white font-black text-base hover:from-up-primary-dark shadow-lg shadow-up-primary/25 transition-all cursor-pointer active:scale-[0.99]">
                    Buat Pesanan — Rp {{ number_format($subtotal, 0, ',', '.') }}
                </button>

                @if($riwayatOrders->count() > 0)
                    <div class="mt-8 bg-white rounded-2xl border border-ink-100 p-5">
                        <p class="text-xs text-ink-400 uppercase tracking-wider font-semibold mb-3">Pesanan Terakhir</p>
                        <div class="space-y-2">
                            @foreach($riwayatOrders as $o)
                                <div class="flex justify-between text-sm py-1.5 border-b border-ink-50 last:border-0">
                                    <span class="font-mono text-up-primary">{{ $o->no_transaksi }}</span>
                                    <span class="text-[11px] text-ink-400 bg-ink-50 px-2 py-0.5 rounded-full">menunggu_pembayaran</span>
                                    <span class="font-bold tabular-nums">Rp {{ number_format($o->total_akhir, 0, ',', '.') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @else
                <!-- Guest → wajib login -->
                <div class="text-center py-10 bg-white rounded-2xl border border-ink-100">
                    <svg class="w-14 h-14 mx-auto mb-4 text-up-primary/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <h2 class="text-lg font-black text-ink-900">Masuk untuk Checkout</h2>
                    <p class="text-sm text-ink-500 mt-1.5 max-w-sm mx-auto">
                        Checkout wajib menggunakan akun pelanggan. Daftar sekali dengan nomor HP — prosesnya 1 langkah cepat.
                    </p>
                    <div class="flex gap-3 justify-center mt-6">
                        <a href="{{ route('customer.login') }}" class="px-6 py-3 rounded-xl bg-up-primary text-white font-bold text-sm hover:bg-up-primary-dark transition-colors">Masuk</a>
                        <a href="{{ route('customer.register') }}" class="px-6 py-3 rounded-xl bg-up-accent text-white font-bold text-sm hover:opacity-90 transition-colors">Daftar Baru</a>
                    </div>
                </div>
            @endauth
        </div>
    @else
        <!-- ===== CART ===== -->
        <div class="max-w-3xl mx-auto">
            <h1 class="text-2xl font-black text-ink-900 mb-6">Keranjang Belanja</h1>

            @if(count($cart) === 0)
                <div class="text-center py-16 bg-white rounded-2xl border border-ink-100">
                    <svg class="w-14 h-14 mx-auto mb-4 text-ink-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <h2 class="font-bold text-ink-800">Keranjang masih kosong</h2>
                    <p class="text-sm text-ink-400 mt-1">Jelajahi katalog dan temukan sparepart yang Anda butuhkan.</p>
                    <a href="{{ route('shop') }}" class="inline-block mt-5 px-6 py-3 rounded-xl bg-up-primary text-white font-bold text-sm hover:bg-up-primary-dark transition-colors">Lihat Katalog</a>
                </div>
            @else
                <div class="bg-white rounded-2xl border border-ink-100 p-5 mb-5">
                    <div class="space-y-3">
                        @foreach($cart as $item)
                            <div class="flex items-center justify-between gap-3 py-2.5 border-b border-ink-50 last:border-0">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <a href="#" wire:click.prevent="hapus({{ $item['variant_id'] ?? 'null' }})" class="text-ink-300 hover:text-up-red text-xs flex-shrink-0">✕</a>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-ink-800 truncate">{{ $item['name'] }}</p>
                                        <p class="text-[11px] text-ink-400">{{ $item['variant_name'] ?? 'Standar' }}{{ $item['alasan_harga'] !== 'Harga Retail Standar' ? ' · ' . $item['alasan_harga'] : '' }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 flex-shrink-0">
                                    <div class="flex items-center gap-1 bg-ink-50 rounded-lg px-1.5 py-1">
                                        <button wire:click="updateQty({{ $item['variant_id'] ?? 'null' }}, {{ $item['qty'] - 1 }})" class="w-6 h-6 rounded-md text-ink-700 font-bold hover:bg-ink-100 cursor-pointer text-xs">−</button>
                                        <span class="w-6 text-center font-bold tabular-nums text-xs">{{ $item['qty'] }}</span>
                                        <button wire:click="updateQty({{ $item['variant_id'] ?? 'null' }}, {{ $item['qty'] + 1 }})" class="w-6 h-6 rounded-md text-ink-700 font-bold hover:bg-ink-100 cursor-pointer text-xs">+</button>
                                    </div>
                                    <span class="text-sm font-black text-ink-900 tabular-nums w-24 text-right">Rp {{ number_format($item['harga'] * $item['qty'], 0, ',', '.') }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex justify-between items-center pt-4 mt-2 border-t border-ink-100">
                        <span class="text-sm font-bold text-ink-700">{{ $jumlahItem }} item</span>
                        <span class="text-xl font-black text-up-primary tabular-nums">Rp {{ number_format($subtotal, 0, ',', '.') }}</span>
                    </div>
                </div>

                <button wire:click="lanjutCheckout" class="w-full py-4 rounded-2xl bg-gradient-to-r from-up-primary to-indigo-600 text-white font-black text-base hover:from-up-primary-dark shadow-lg shadow-up-primary/25 transition-all cursor-pointer active:scale-[0.99]">
                    Lanjut Checkout →
                </button>
            @endif
        </div>
    @endif
</div>