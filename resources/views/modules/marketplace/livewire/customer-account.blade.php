<div class="max-w-4xl mx-auto px-4 sm:px-6 py-8" wire:poll.8s>
    {{-- [T-05] Factory alur pembayaran Duitku (PAY-01). Definisi di window supaya
         selalu tersedia walau tab Orders belum pernah dirender (morph Livewire
         tidak mengevaluasi ulang <script> biasa). Semua fetch pakai
         credentials:'include' (Sanctum SPA cookie, same-origin). --}}
    <script>
        window.duitkuPay = function () {
            return {
                showBayar: false,
                orderBayar: null,      // { id, no, total }
                metodeList: [],        // dari GET /api/payment/methods — dinamis, tidak hardcode
                metodeDipilih: null,
                kategoriDipilih: 'qris',
                loadingMetode: false,
                loadingCreate: false,
                errorBayar: '',        // pesan bebas; 'PAYMENT_UNAVAILABLE' = gateway belum dikonfigurasi (503)
                infoBayar: null,       // { payment_url, va_number, qr_string, expiry, sandbox, kode, namaMetode, badge, kategori }

                init() {
                    const params = new URLSearchParams(window.location.search);
                    const noOrder = params.get('bayar');
                    if (noOrder) {
                        this.$nextTick(() => {
                            const btn = document.querySelector(`[data-order-no="${noOrder}"]`);
                            if (btn) {
                                btn.click();
                            }
                        });
                    }
                },

                rupiah(n) {
                    // Format ribuan titik (ADR 0011)
                    return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
                },

                csrf() {
                    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                },

                get kategoriList() {
                    const order = ['qris', 'va', 'ewallet', 'retail', 'kartu'];
                    const labelMap = {
                        'qris': '⚡ QRIS',
                        'va': 'Transfer Bank (VA)',
                        'ewallet': 'E-Wallet',
                        'retail': 'Gerai Retail',
                        'kartu': 'Kartu Kredit',
                    };
                    const existing = new Set(this.metodeList.map(m => m.kategori || 'va'));
                    return order.filter(k => existing.has(k)).map(k => ({
                        key: k,
                        label: labelMap[k] || k.toUpperCase(),
                        count: this.metodeList.filter(m => (m.kategori || 'va') === k).length
                    }));
                },

                get filteredMetode() {
                    return this.metodeList.filter(m => (m.kategori || 'va') === this.kategoriDipilih);
                },

                pilihKategori(k) {
                    this.kategoriDipilih = k;
                    const items = this.filteredMetode;
                    if (items.length > 0 && (!this.metodeDipilih || !items.some(m => m.kode === this.metodeDipilih))) {
                        this.metodeDipilih = items[0].kode;
                    }
                },

                metodeTerpilihObj() {
                    return this.metodeList.find(m => m.kode === this.metodeDipilih) || null;
                },

                /** Langkah 1: buka panel + muat daftar metode dinamis utk nominal order ini. */
                async bukaBayar(id, no, total) {
                    if (this.loadingCreate) return;
                    this.orderBayar = { id, no, total };
                    this.metodeList = [];
                    this.metodeDipilih = null;
                    this.errorBayar = '';
                    this.infoBayar = null;
                    this.showBayar = true;
                    this.loadingMetode = true;

                    try {
                        const res = await fetch('/api/payment/methods?amount=' + Math.round(total), {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'include',
                        });
                        const body = await res.json().catch(() => null);

                        if (res.status === 503) {
                            this.errorBayar = 'PAYMENT_UNAVAILABLE';
                            return;
                        }
                        if (!res.ok || !body || !body.success) {
                            throw new Error((body && body.message) || 'Daftar metode pembayaran gagal dimuat.');
                        }

                        this.metodeList = Array.isArray(body.data) ? body.data : [];
                        if (!this.metodeList.length) {
                            this.errorBayar = 'PAYMENT_UNAVAILABLE';
                            return;
                        }

                        const katKeys = this.kategoriList.map(k => k.key);
                        this.kategoriDipilih = katKeys.includes('qris') ? 'qris' : (katKeys[0] || 'va');
                        const defaultItems = this.filteredMetode;
                        this.metodeDipilih = defaultItems.length > 0 ? defaultItems[0].kode : this.metodeList[0].kode;
                    } catch (e) {
                        this.errorBayar = (e && e.message) || 'Tidak bisa terhubung ke layanan pembayaran. Periksa koneksi internet Anda lalu coba lagi.';
                        if (window.showToast) showToast(this.errorBayar, 'error');
                    } finally {
                        this.loadingMetode = false;
                    }
                },

                /** Langkah 2: POST /api/payment/create — anti double-submit (guard loadingCreate). */
                async konfirmasiBayar() {
                    if (this.loadingCreate || !this.metodeDipilih || !this.orderBayar) return;
                    this.loadingCreate = true;
                    this.errorBayar = '';

                    try {
                        const res = await fetch('/api/payment/create', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrf(),
                            },
                            credentials: 'include',
                            body: JSON.stringify({
                                no_transaksi: this.orderBayar.no,
                                payment_method: this.metodeDipilih,
                            }),
                        });
                        const body = await res.json().catch(() => null);

                        if (res.status === 503) {
                            this.errorBayar = 'PAYMENT_UNAVAILABLE';
                            this.loadingCreate = false;
                            return;
                        }
                        if (!res.ok || !body || !body.success) {
                            throw new Error((body && body.message) || 'Pembayaran gagal diproses. Silakan coba lagi.');
                        }

                        const d = body.data || {};
                        const selectedObj = this.metodeTerpilihObj();
                        this.infoBayar = {
                            payment_url: d.payment_url || d.paymentUrl || null,
                            va_number: d.va_number || d.vaNumber || null,
                            qr_string: d.qr_string || d.qrString || null,
                            expiry: d.expiry || d.expiryTime || null,
                            sandbox: !!d.sandbox,
                            kode: this.metodeDipilih,
                            namaMetode: selectedObj ? selectedObj.nama : this.metodeDipilih,
                            badge: selectedObj ? selectedObj.badge : this.metodeDipilih,
                            kategori: selectedObj ? selectedObj.kategori : 'va',
                        };

                        this.loadingCreate = false;
                    } catch (e) {
                        this.loadingCreate = false;
                        this.errorBayar = (e && e.message) || 'Pembayaran gagal diproses. Silakan coba lagi.';
                        if (window.showToast) showToast(this.errorBayar, 'error');
                    }
                },

                bukaHalamanPembayaran() {
                    if (this.infoBayar && this.infoBayar.payment_url) {
                        window.location.href = this.infoBayar.payment_url;
                    }
                },

                async salinVa() {
                    if (!this.infoBayar || !this.infoBayar.va_number) return;
                    try {
                        await navigator.clipboard.writeText(String(this.infoBayar.va_number));
                        if (window.showToast) showToast('Nomor rekening berhasil disalin', 'success');
                    } catch (e) {
                        if (window.showToast) showToast('Gagal menyalin otomatis — salin manual ya.', 'warning');
                    }
                },

                /** "Saya Sudah Bayar" / tutup setelah menunggu → sinkronkan status live dari server/Duitku. */
                async selesaiMenunggu() {
                    const noOrder = this.orderBayar ? this.orderBayar.no : null;
                    this.showBayar = false;
                    this.loadingCreate = false;

                    if (noOrder) {
                        try {
                            const res = await fetch('/api/payment/check-status', {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': this.csrf(),
                                },
                                credentials: 'include',
                                body: JSON.stringify({ no_transaksi: noOrder }),
                            });
                            const body = await res.json().catch(() => null);
                            if (body && body.data && body.data.is_lunas) {
                                if (window.showToast) showToast('Pembayaran berhasil diverifikasi lunas!', 'success');
                            } else if (body && body.message) {
                                if (window.showToast) showToast(body.message, 'info');
                            }
                        } catch (e) {
                            // Abaikan kendala jaringan, refresh Livewire tetap jalan
                        }
                    }

                    if (this.$wire) this.$wire.$refresh();
                },

                tutupBayar() {
                    if (this.loadingCreate) return; // jangan tutup saat transisi redirect
                    this.showBayar = false;
                    this.loadingCreate = false;
                    // Muat ulang status order (mis. webhook sudah masuk saat pengguna kembali dari Duitku)
                    if (this.$wire) this.$wire.$refresh();
                },

                formatExpiry(s) {
                    if (!s) return '';
                    const dt = new Date(String(s).replace(' ', 'T'));
                    if (isNaN(dt.getTime())) return String(s);
                    return dt.toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
                },
            };
        };
    </script>
    @if(!$customer)
        <div class="text-center py-16 bg-white dark:bg-ink-900 rounded-3xl border border-ink-100 dark:border-white/10 p-8 shadow-sm">
            <h2 class="font-black text-ink-900 dark:text-white text-lg">Silakan Masuk</h2>
            <p class="text-sm text-ink-500 dark:text-ink-400 mt-2">Login untuk melihat riwayat pesanan, servis, dan komisi Anda.</p>
            <a href="{{ route('customer.login') }}" class="inline-block mt-5 px-6 py-3 rounded-2xl bg-up-primary text-white font-bold text-sm hover:bg-up-primary-dark transition-colors shadow-md shadow-up-primary/20 min-h-[44px]">Masuk</a>
        </div>
    @else
        <!-- Profil header -->
        <div class="bg-white dark:bg-ink-900 rounded-3xl border border-ink-100 dark:border-white/10 p-6 mb-6 shadow-sm">
            <div class="flex items-center gap-4">
                <div class="relative group">
                    @if($customer->avatar)
                        <img src="{{ $customer->avatar }}" alt="{{ $customer->nama }}" class="w-14 h-14 rounded-2xl object-cover border border-slate-200 dark:border-white/10 shadow-md">
                    @else
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-up-primary to-up-accent flex items-center justify-center text-white font-black text-xl shadow-md">
                            {{ substr($customer->nama, 0, 1) }}
                        </div>
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <h1 class="font-black text-ink-900 dark:text-white text-lg truncate">{{ $customer->nama }}</h1>
                        @if($customer->username)
                            <span class="text-xs px-2 py-0.5 rounded-full bg-up-primary/10 text-up-primary font-mono font-semibold">
                                &#64;{{ $customer->username }}
                            </span>
                        @endif
                    </div>
                    <p class="text-sm text-ink-500 dark:text-ink-400 font-mono">{{ $customer->telepon }}</p>
                </div>
                <div class="text-right flex-shrink-0">
                    <x-prism.tier-badge :tier="$customer->tierMembership?->nama ?? ($customer->is_reseller ? 'Reseller' : 'Retail')" />
                    <p class="text-xs text-ink-500 dark:text-ink-400 mt-2 tabular-nums">{{ $customer->poin_loyalty }} poin</p>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-5 text-center">
                <button wire:click="$set('activeTab', 'orders')" class="p-3 rounded-xl bg-ink-50 dark:bg-white/5 border border-ink-100 dark:border-white/10 hover:border-up-primary transition-[transform,border-color] active:scale-[0.98] cursor-pointer text-left min-h-[44px]">
                    <p class="font-black text-ink-900 dark:text-white tabular-nums text-lg">{{ $orders->count() }}</p>
                    <p class="text-[10px] text-ink-400 uppercase font-semibold">Pesanan</p>
                </button>
                <button wire:click="$set('activeTab', 'servis')" class="p-3 rounded-xl bg-ink-50 dark:bg-white/5 border border-ink-100 dark:border-white/10 hover:border-up-primary transition-[transform,border-color] active:scale-[0.98] cursor-pointer text-left min-h-[44px]">
                    <p class="font-black text-ink-900 dark:text-white tabular-nums text-lg">{{ $servis->count() }}</p>
                    <p class="text-[10px] text-ink-400 uppercase font-semibold">Servis</p>
                </button>
                <button wire:click="$set('activeTab', 'membership')" class="p-3 rounded-xl bg-ink-50 dark:bg-white/5 border border-ink-100 dark:border-white/10 hover:border-up-primary transition-[transform,border-color] active:scale-[0.98] cursor-pointer text-left min-h-[44px]">
                    <p class="font-black text-up-primary tabular-nums text-lg">{{ number_format($customer->poin_loyalty ?? 0, 0, ',', '.') }}</p>
                    <p class="text-[10px] text-ink-400 uppercase font-semibold">Poin Loyalty</p>
                </button>
                <button wire:click="$set('activeTab', '{{ $customer->is_reseller ? 'komisi' : 'membership' }}')" class="p-3 rounded-xl bg-ink-50 dark:bg-white/5 border border-ink-100 dark:border-white/10 hover:border-up-primary transition-[transform,border-color] active:scale-[0.98] cursor-pointer text-left min-h-[44px]">
                    <p class="font-black {{ $customer->is_reseller ? 'text-up-accent' : 'text-up-mint' }} tabular-nums text-lg truncate">
                        {{ $customer->is_reseller ? 'Rp ' . number_format($komisi->where('status', 'disetujui')->sum('nominal_komisi') + $komisi->where('status', 'pending')->sum('nominal_komisi'), 0, ',', '.') : 'Rp ' . number_format((float) ($customer->total_belanja_12bulan ?? 0), 0, ',', '.') }}
                    </p>
                    <p class="text-[10px] text-ink-400 uppercase font-semibold">{{ $customer->is_reseller ? 'Komisi' : 'Belanja 12 Bln' }}</p>
                </button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex items-center gap-2 mb-5 overflow-x-auto scrollbar-none py-1 -my-1 flex-nowrap">
            @php
                $unreadNotifCount = $notifikasi->whereNull('read_at')->count();
                $tabList = [
                    'orders' => 'Pesanan',
                    'servis' => 'Tracking Servis',
                    'notifikasi' => 'Pemberitahuan',
                    'membership' => 'Status Membership & Poin',
                    'profil' => 'Profil & Keamanan',
                ];
                if ($customer->is_reseller) {
                    $tabList['komisi'] = 'Komisi Saya';
                }
            @endphp
            @foreach($tabList as $kode => $label)
                <button wire:click="$set('activeTab', '{{ $kode }}')" class="px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer flex items-center gap-1.5 {{ $activeTab === $kode ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white dark:bg-ink-900 border border-ink-200 dark:border-white/10 text-ink-600 dark:text-ink-300 hover:bg-ink-50 dark:hover:bg-white/5' }}">
                    <span>{{ $label }}</span>
                    @if($kode === 'notifikasi' && $unreadNotifCount > 0)
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold bg-up-red text-white">
                            {{ $unreadNotifCount }}
                        </span>
                    @endif
                </button>
            @endforeach
        </div>

        <!-- ORDERS -->
        @if($activeTab === 'orders')
            <div class="space-y-3" x-data="duitkuPay()">
                @forelse($orders as $o)
                    <div class="bg-white dark:bg-ink-900 rounded-3xl border border-ink-100 dark:border-white/10 p-5 shadow-sm space-y-3">
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <div>
                                <span class="font-mono text-up-primary font-bold text-sm">{{ $o->no_transaksi }}</span>
                                <span class="text-xs text-ink-400 ml-2">{{ $o->created_at->format('d/m/Y H:i') }}</span>
                            </div>
                            <x-prism.status-pill :status="$o->status" />
                        </div>

                        <!-- Status Details & Tracking Banner -->
                        @if($o->status === 'menunggu_pembayaran')
                            <div class="p-3.5 rounded-2xl bg-up-amber/10 border border-up-amber/20 text-xs text-ink-700 dark:text-amber-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                                <div>
                                    <p class="font-bold text-up-amber flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-up-amber animate-pulse"></span>
                                        <span>Menunggu Pembayaran</span>
                                    </p>
                                    <p class="text-[11px] text-ink-500 dark:text-ink-400 mt-0.5">Selesaikan pembayaran online agar barang segera disiapkan oleh tim gudang.</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        data-order-no="{{ $o->no_transaksi }}"
                                        x-on:click="bukaBayar({{ $o->id }}, '{{ $o->no_transaksi }}', {{ (float) $o->total_akhir }})"
                                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold text-white bg-up-primary hover:bg-indigo-600 shadow-sm transition-colors cursor-pointer"
                                    >
                                        Bayar Sekarang
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="batalkanPesanan({{ $o->id }})"
                                        wire:confirm="Batalkan pesanan ini?"
                                        class="px-2.5 py-1.5 rounded-xl text-xs font-bold text-ink-500 hover:text-up-red transition-colors cursor-pointer"
                                    >
                                        Batal
                                    </button>
                                </div>
                            </div>
                        @elseif($o->status === 'lunas')
                            <div class="p-3 rounded-2xl bg-up-primary/10 border border-up-primary/20 text-xs text-ink-700 dark:text-indigo-200 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-up-primary animate-pulse"></span>
                                <span>Pembayaran Terverifikasi — Menunggu giliran pengemasan di toko.</span>
                            </div>
                        @elseif($o->status === 'diproses')
                            <div class="p-3 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-xs text-ink-700 dark:text-indigo-200 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-indigo-400 animate-pulse"></span>
                                <span>Sedang Dikemas — Tim staf kami sedang menyiapkan suku cadang Anda.</span>
                            </div>
                        @elseif($o->status === 'dikirim')
                            <div class="p-3.5 rounded-2xl bg-sky-500/10 border border-sky-500/20 text-xs text-ink-700 dark:text-sky-200 space-y-1">
                                <div class="font-bold text-sky-400 flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                    <span>Paket Sedang Dikirim via {{ $o->pengiriman?->kurir ?? 'Ekspedisi' }}</span>
                                </div>
                                <p class="text-[11px] text-ink-600 dark:text-ink-300">
                                    Nomor Resi: <strong class="font-mono text-up-primary">{{ $o->pengiriman?->tracking_id ?: 'Dalam penjemputan kurir' }}</strong>
                                </p>
                            </div>
                        @elseif($o->status === 'siap_diambil')
                            <div class="p-3.5 rounded-2xl bg-up-mint/10 border border-up-mint/20 text-xs text-ink-700 dark:text-emerald-200 space-y-1">
                                <div class="font-bold text-up-mint flex items-center gap-1.5">
                                    <span>🏬</span>
                                    <span>Siap Diambil di Konter Toko</span>
                                </div>
                                <p class="text-[11px] text-ink-600 dark:text-ink-300">
                                    Suku cadang sudah siap diambil di cabang <strong>{{ $o->cabang?->nama }}</strong>. Tunjukkan nomor pesanan ini ke kasir.
                                </p>
                            </div>
                        @elseif($o->status === 'selesai')
                            <div class="p-3 rounded-2xl bg-up-mint/10 border border-up-mint/20 text-xs text-ink-700 dark:text-emerald-200 flex items-center gap-2">
                                <span class="text-up-mint font-bold">✓</span>
                                <span>Pesanan Selesai. Terima kasih telah berbelanja di Ute Parts!</span>
                            </div>
                        @elseif($o->status === 'batal')
                            <div class="p-3 rounded-2xl bg-up-red/10 border border-up-red/20 text-xs text-up-red flex items-center gap-2">
                                <span>✕</span>
                                <span>Pesanan Telah Dibatalkan.</span>
                            </div>
                        @endif

                        <div class="flex flex-wrap gap-1.5">
                            @foreach($o->items as $it)
                                <span class="text-[11px] px-2.5 py-1 rounded-xl bg-ink-50 dark:bg-white/5 border border-ink-100 dark:border-white/10 text-ink-600 dark:text-ink-300">{{ $it->produk?->nama }} ×{{ $it->jumlah }}</span>
                            @endforeach
                        </div>

                        <div class="flex justify-between items-center pt-3 border-t border-ink-100 dark:border-white/10 text-xs">
                            <span class="text-ink-500 dark:text-ink-400">{{ $o->cabang?->nama }}</span>
                            <span class="font-black text-up-primary tabular-nums text-sm">Rp {{ number_format($o->total_akhir, 0, ',', '.') }}</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-14 bg-white dark:bg-ink-900 rounded-3xl border border-ink-100 dark:border-white/10 p-6 shadow-sm">
                        <p class="font-semibold text-ink-700 dark:text-ink-300">Belum ada pesanan</p>
                        <a href="{{ route('shop') }}" class="inline-block mt-4 px-5 py-2.5 rounded-2xl bg-up-primary text-white font-bold text-sm hover:bg-up-primary-dark transition-colors min-h-[44px]">Mulai Belanja</a>
                    </div>
                @endforelse

                {{-- ===== [T-05] Panel Pembayaran Duitku (mobile-first bottom sheet) =====
                     Alur: pilih metode (GET /methods, dinamis + fee) → konfirmasi →
                     POST /create → redirect payment_url. Layar "menunggu" muncul bila
                     VA/QRIS ikut balasan (VA bisa disalin; QRIS instruksi scan).
                     503 (gateway belum dikonfigurasi) → pesan anggun, alur manual tetap jalan. --}}
                <div
                    x-show="showBayar"
                    x-cloak
                    class="fixed inset-0 z-50 flex items-end sm:items-center justify-center"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Pembayaran Pesanan"
                >
                    <!-- Backdrop -->
                    <div
                        class="absolute inset-0 bg-ink-950/60 backdrop-blur-sm"
                        x-on:click="tutupBayar()"
                        aria-hidden="true"
                    ></div>

                    <!-- Sheet -->
                    <div
                        x-transition:enter="transition duration-200 ease-out"
                        x-transition:enter-start="translate-y-8 opacity-0 sm:translate-y-0 sm:scale-[0.97]"
                        x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
                        x-transition:leave="transition duration-150 ease-out"
                        x-transition:leave-start="translate-y-0 opacity-100 sm:scale-100"
                        x-transition:leave-end="translate-y-8 opacity-0 sm:translate-y-0 sm:scale-[0.97]"
                        class="relative w-full sm:max-w-md max-h-[92vh] overflow-y-auto bg-white dark:bg-ink-900 border border-ink-100 dark:border-white/10 rounded-t-3xl sm:rounded-3xl p-5 sm:p-6 shadow-2xl"
                    >
                        <!-- Header -->
                        <div class="flex items-start justify-between gap-3 mb-4">
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-ink-400">Pembayaran Pesanan</p>
                                <p class="font-mono text-up-primary font-bold text-sm truncate" x-text="orderBayar ? orderBayar.no : ''"></p>
                                <p class="text-lg font-black text-ink-900 dark:text-white tabular-nums mt-0.5" x-text="orderBayar ? rupiah(orderBayar.total) : ''"></p>
                            </div>
                            <button
                                type="button"
                                x-on:click="tutupBayar()"
                                class="w-9 h-9 flex-shrink-0 rounded-xl bg-ink-50 dark:bg-white/5 text-ink-400 hover:text-up-red flex items-center justify-center transition-colors cursor-pointer"
                                aria-label="Tutup"
                            >
                                ✕
                            </button>
                        </div>

                        {{-- ===== Gateway belum dikonfigurasi (503) / daftar kosong ===== --}}
                        <div x-show="errorBayar === 'PAYMENT_UNAVAILABLE'" class="p-4 rounded-2xl bg-ink-50 dark:bg-white/5 border border-ink-100 dark:border-white/10 text-center space-y-2">
                            <p class="text-base">🛍️</p>
                            <p class="text-sm font-bold text-ink-900 dark:text-white">Pembayaran Online Belum Tersedia</p>
                            <p class="text-xs text-ink-500 dark:text-ink-400 leading-relaxed">
                                Layanan pembayaran online sedang tidak aktif. Pesanan Anda tetap tersimpan —
                                selesaikan pembayaran saat ambil barang atau lewat instruksi resmi WhatsApp Ute Parts.
                            </p>
                            <button
                                type="button"
                                x-on:click="tutupBayar()"
                                class="mt-2 px-5 py-2.5 rounded-2xl bg-white dark:bg-ink-950 border border-ink-200/80 dark:border-white/10 text-ink-700 dark:text-ink-300 font-bold text-xs hover:bg-ink-50 transition-colors cursor-pointer min-h-[44px]"
                            >
                                Mengerti
                            </button>
                        </div>

                        {{-- ===== Layar instruksi bayar (VA / QRIS / E-Wallet / URL) ===== --}}
                        <div x-show="infoBayar && errorBayar !== 'PAYMENT_UNAVAILABLE'" class="space-y-4">
                            <div class="p-3.5 rounded-2xl bg-up-mint/10 border border-up-mint/20 text-xs text-ink-700 dark:text-emerald-200">
                                <p class="font-bold text-up-mint flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-up-mint animate-pulse"></span>
                                    <span x-text="'Tagihan ' + (infoBayar ? (infoBayar.namaMetode || 'Pembayaran') : 'Pembayaran') + ' Dibuat'"></span>
                                    <span x-show="infoBayar && infoBayar.sandbox" class="ml-auto px-2 py-0.5 rounded-full text-[10px] font-bold bg-up-amber/20 text-up-amber">Mode Sandbox</span>
                                </p>
                                <p class="text-[11px] text-ink-500 dark:text-ink-400 mt-1">
                                    Selesaikan pembayaran sebelum batas waktu agar pesanan Anda segera diproses gudang.
                                </p>
                            </div>

                            <!-- Nomor VA + tombol salin (Virtual Account) -->
                            <div x-show="infoBayar && infoBayar.va_number" class="p-4 rounded-2xl bg-ink-50 dark:bg-white/5 border border-ink-100 dark:border-white/10 space-y-2">
                                <div class="flex items-center justify-between">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-ink-400">Nomor Virtual Account</p>
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-up-primary/10 text-up-primary" x-text="infoBayar ? infoBayar.badge : ''"></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <code class="flex-1 min-w-0 text-base sm:text-lg font-black font-mono text-ink-900 dark:text-white tabular-nums tracking-wide break-all" x-text="infoBayar ? infoBayar.va_number : ''"></code>
                                    <button
                                        type="button"
                                        x-on:click="salinVa()"
                                        class="flex-shrink-0 px-3.5 py-2 rounded-xl bg-up-primary text-white text-xs font-bold hover:bg-up-primary-dark transition-colors cursor-pointer shadow-sm shadow-up-primary/20 active:scale-95"
                                    >
                                        Salin
                                    </button>
                                </div>
                                <p class="text-[11px] text-ink-500 dark:text-ink-400">
                                    Buka m-Banking atau ATM bank terkait, pilih menu <strong>Transfer Virtual Account</strong> lalu masukkan nomor di atas.
                                </p>
                            </div>

                            <!-- QRIS: Instruksi Scan Universal -->
                            <div x-show="infoBayar && infoBayar.kategori === 'qris'" class="p-4 rounded-2xl bg-up-primary/5 border border-up-primary/20 text-xs text-ink-600 dark:text-indigo-200 space-y-2">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-black bg-up-primary text-white tracking-wider">QRIS</span>
                                    <p class="font-bold text-up-primary">Scan QRIS Semua Bank & E-Wallet</p>
                                </div>
                                <p class="text-[11px] leading-relaxed text-ink-500 dark:text-ink-400">
                                    Dapat dibayar dari <strong>BCA, Mandiri, BRI, BNI, DANA, GoPay, OVO, ShopeePay</strong>, dan semua aplikasi yang mendukung QRIS.
                                </p>
                                <div class="pt-1">
                                    <button
                                        type="button"
                                        x-on:click="bukaHalamanPembayaran()"
                                        class="w-full py-2.5 rounded-xl bg-up-primary text-white font-bold text-xs hover:bg-up-primary-dark transition-colors cursor-pointer shadow-sm shadow-up-primary/20"
                                    >
                                        Tampilkan QR Code Pembayaran ↗
                                    </button>
                                </div>
                            </div>

                            <!-- E-Wallet / Retail / Kartu (Redirect URL) -->
                            <div x-show="infoBayar && infoBayar.kategori !== 'qris' && !infoBayar.va_number && infoBayar.payment_url" class="p-4 rounded-2xl bg-ink-50 dark:bg-white/5 border border-ink-100 dark:border-white/10 space-y-2.5">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-bold text-ink-900 dark:text-white" x-text="infoBayar ? infoBayar.namaMetode : ''"></p>
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-up-accent/10 text-up-accent" x-text="infoBayar ? infoBayar.badge : ''"></span>
                                </div>
                                <p class="text-[11px] text-ink-500 dark:text-ink-400 leading-relaxed">
                                    Klik tombol di bawah untuk membuka halaman otorisasi pembayaran resmi.
                                </p>
                                <button
                                    type="button"
                                    x-on:click="bukaHalamanPembayaran()"
                                    class="w-full py-2.5 rounded-xl bg-up-primary text-white font-bold text-xs hover:bg-up-primary-dark transition-colors cursor-pointer shadow-sm shadow-up-primary/20 flex items-center justify-center gap-1.5"
                                >
                                    <span>Lanjut ke Halaman Pembayaran</span>
                                    <span>↗</span>
                                </button>
                            </div>

                            <!-- Sandbox Simulator Helper (Khusus VA di mode uji coba) -->
                            <div x-show="infoBayar && infoBayar.sandbox && infoBayar.va_number" class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-[11px] text-amber-800 dark:text-amber-200 space-y-1">
                                <p class="font-bold flex items-center gap-1">
                                    <span>🧪</span>
                                    <span>Uji Coba Pelunasan Sandbox Duitku</span>
                                </p>
                                <p class="text-[10px] text-ink-600 dark:text-amber-300/80">
                                    Ingin tes pesanan langsung lunas otomatis? Buka simulator Duitku, tempel nomor VA di atas, lalu klik Bayar.
                                </p>
                                <a
                                    href="https://sandbox.duitku.com/payment/demo/demosuccesstransaction.aspx"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-block mt-1 font-bold text-up-primary underline hover:text-indigo-600"
                                >
                                    Buka Halaman Uji Coba VA Duitku ↗
                                </a>
                            </div>

                            <!-- Batas waktu -->
                            <p x-show="infoBayar && infoBayar.expiry" class="text-[11px] text-ink-400 text-center">
                                Batas waktu pembayaran: <span class="font-semibold tabular-nums text-ink-700 dark:text-ink-200" x-text="infoBayar ? formatExpiry(infoBayar.expiry) : ''"></span>
                            </p>

                            <div class="space-y-2 pt-1">
                                <button
                                    type="button"
                                    x-on:click="selesaiMenunggu()"
                                    class="w-full py-3.5 rounded-2xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-sm shadow-sm shadow-up-primary/25 transition-[background-color,transform] duration-140 cursor-pointer active:scale-[0.98] min-h-[48px]"
                                >
                                    Saya Sudah Bayar — Cek Status
                                </button>
                                <button
                                    type="button"
                                    x-on:click="tutupBayar()"
                                    class="w-full py-2.5 rounded-2xl bg-white dark:bg-ink-950 border border-ink-200/80 dark:border-white/10 text-ink-600 dark:text-ink-400 font-semibold text-xs hover:bg-ink-50 transition-colors cursor-pointer min-h-[40px]"
                                >
                                    Bayar Nanti
                                </button>
                            </div>
                        </div>

                        {{-- ===== Pilih metode: Segmented Tabs & Compact Grid ===== --}}
                        <div x-show="!infoBayar && errorBayar !== 'PAYMENT_UNAVAILABLE'" class="space-y-4">
                            <!-- Loading daftar metode -->
                            <div x-show="loadingMetode" class="space-y-2.5">
                                <p class="text-[11px] text-ink-400 animate-pulse">Memuat metode pembayaran…</p>
                                <div class="h-10 rounded-xl bg-ink-50 dark:bg-white/5 border border-ink-100 dark:border-white/10 animate-pulse"></div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="h-16 rounded-xl bg-ink-50 dark:bg-white/5 border border-ink-100 dark:border-white/10 animate-pulse"></div>
                                    <div class="h-16 rounded-xl bg-ink-50 dark:bg-white/5 border border-ink-100 dark:border-white/10 animate-pulse"></div>
                                </div>
                            </div>

                            <div x-show="!loadingMetode" class="space-y-3">
                                <!-- Segmented Category Tabs -->
                                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none border-b border-ink-100 dark:border-white/10 text-xs">
                                    <template x-for="cat in kategoriList" :key="cat.key">
                                        <button
                                            type="button"
                                            x-on:click="pilihKategori(cat.key)"
                                            class="px-3 py-1.5 rounded-xl font-bold whitespace-nowrap transition-all duration-140 cursor-pointer flex items-center gap-1.5"
                                            :class="kategoriDipilih === cat.key
                                                ? 'bg-up-primary text-white shadow-sm shadow-up-primary/20'
                                                : 'text-ink-600 dark:text-ink-400 hover:bg-ink-100 dark:hover:bg-white/5'"
                                        >
                                            <span x-text="cat.label"></span>
                                            <span
                                                class="px-1.5 py-0.2 rounded-full text-[10px] font-mono tabular-nums"
                                                :class="kategoriDipilih === cat.key ? 'bg-white/20 text-white' : 'bg-ink-200/60 dark:bg-white/10 text-ink-500'"
                                                x-text="cat.count"
                                            ></span>
                                        </button>
                                    </template>
                                </div>

                                <!-- Compact Grid Metode -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-[300px] overflow-y-auto pr-1" role="radiogroup" aria-label="Pilihan metode">
                                    <template x-for="m in filteredMetode" :key="m.kode">
                                        <button
                                            type="button"
                                            x-on:click="metodeDipilih = m.kode"
                                            class="p-3 rounded-2xl border-2 text-left flex items-start justify-between gap-2.5 transition-[border-color,background-color] duration-140 cursor-pointer active:scale-[0.99]"
                                            :class="metodeDipilih === m.kode
                                                ? 'border-up-primary bg-up-primary/5 dark:bg-up-primary/10 shadow-sm'
                                                : 'border-ink-200/80 dark:border-white/10 hover:border-ink-300 dark:hover:border-white/20'"
                                            :aria-checked="metodeDipilih === m.kode"
                                            role="radio"
                                        >
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-1.5 mb-0.5">
                                                    <span
                                                        class="px-1.5 py-0.5 rounded text-[10px] font-extrabold tracking-wider"
                                                        :class="metodeDipilih === m.kode ? 'bg-up-primary text-white' : 'bg-ink-100 dark:bg-white/10 text-ink-700 dark:text-ink-300'"
                                                        x-text="m.badge || m.kode"
                                                    ></span>
                                                    <span class="text-xs font-bold text-ink-900 dark:text-white truncate" x-text="m.nama"></span>
                                                </div>
                                                <p x-show="m.deskripsi" class="text-[10px] text-ink-400 dark:text-ink-500 line-clamp-1" x-text="m.deskripsi"></p>
                                                <div class="mt-1 flex items-center gap-2">
                                                    <span x-show="Number(m.fee) > 0" class="text-[10px] text-up-amber font-semibold tabular-nums" x-text="'Fee +' + rupiah(m.fee)"></span>
                                                    <span x-show="!Number(m.fee)" class="text-[10px] text-up-mint font-semibold">Bebas Biaya</span>
                                                </div>
                                            </div>
                                            <span
                                                class="w-4 h-4 mt-0.5 flex-shrink-0 rounded-full border-2 flex items-center justify-center transition-colors"
                                                :class="metodeDipilih === m.kode ? 'border-up-primary bg-up-primary' : 'border-ink-300 dark:border-white/20'"
                                            >
                                                <span x-show="metodeDipilih === m.kode" class="w-1.5 h-1.5 rounded-full bg-white"></span>
                                            </span>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <!-- Error inline (mis. jaringan / respons gagal) -->
                            <div x-show="!loadingMetode && errorBayar && errorBayar !== 'PAYMENT_UNAVAILABLE'" class="p-3 rounded-2xl bg-up-red/10 border border-up-red/20 text-[11px] text-up-red font-semibold">
                                <span x-text="errorBayar"></span>
                            </div>

                            <!-- Tombol konfirmasi -->
                            <button
                                type="button"
                                x-on:click="konfirmasiBayar()"
                                x-bind:disabled="loadingCreate || !metodeDipilih || loadingMetode"
                                x-bind:class="(!metodeDipilih || loadingCreate || loadingMetode) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-up-primary-dark shadow-sm shadow-up-primary/25 cursor-pointer'"
                                class="w-full py-3.5 rounded-2xl bg-up-primary text-white font-bold text-sm transition-[opacity,background-color,transform] duration-140 active:scale-[0.98] min-h-[48px] flex items-center justify-center gap-2"
                            >
                                <svg x-show="loadingCreate" class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                </svg>
                                <span x-text="loadingCreate ? 'Menghubungi penyedia pembayaran…' : 'Lanjut ke Pembayaran'"></span>
                            </button>

                            <p class="text-[10px] text-ink-400 text-center leading-relaxed">
                                Pembayaran diproses dengan aman melalui gateway resmi. Status pesanan diperbarui otomatis setelah lunas.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- NOTIFIKASI KONSUMEN -->
        @if($activeTab === 'notifikasi')
            <div class="space-y-3">
                @forelse($notifikasi as $notif)
                    <div
                        wire:click="markNotifikasiRead({{ $notif->id }})"
                        class="bg-white dark:bg-ink-900 rounded-3xl border transition-all duration-150 p-4 sm:p-5 shadow-sm cursor-pointer {{ is_null($notif->read_at) ? 'border-up-primary/40 bg-up-primary/[0.02]' : 'border-ink-100 dark:border-white/10 opacity-75' }}"
                    >
                        <div class="flex items-start gap-3">
                            <div class="p-2 rounded-xl flex-shrink-0 {{ is_null($notif->read_at) ? 'bg-up-primary/10 text-up-primary' : 'bg-black/5 dark:bg-white/5 text-ink-400' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2 mb-1">
                                    <h4 class="text-xs font-bold text-ink-900 dark:text-white truncate">{{ $notif->judul }}</h4>
                                    <span class="text-[10px] text-ink-400 font-mono">{{ $notif->created_at?->diffForHumans() }}</span>
                                </div>
                                <p class="text-xs text-ink-600 dark:text-ink-300 leading-relaxed">{{ $notif->konten }}</p>
                                @if(is_null($notif->read_at))
                                    <div class="mt-2 flex items-center gap-1 text-[10px] text-up-primary font-bold">
                                        <span class="w-1.5 h-1.5 rounded-full bg-up-primary animate-pulse"></span>
                                        <span>Baru</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-14 bg-white dark:bg-ink-900 rounded-3xl border border-ink-100 dark:border-white/10 p-6 shadow-sm">
                        <p class="font-semibold text-ink-700 dark:text-ink-300">Belum ada pemberitahuan</p>
                        <p class="text-xs text-ink-400 mt-1">Status pesanan dan servis Anda akan ditampilkan di sini.</p>
                    </div>
                @endforelse
            </div>
        @endif

        <!-- SERVIS TRACKING -->
        @if($activeTab === 'servis')
            <div class="space-y-3">
                @forelse($servis as $sv)
                    <div class="bg-white dark:bg-ink-900 rounded-3xl border border-ink-100 dark:border-white/10 p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-mono text-up-primary font-bold text-sm">{{ $sv->no_tiket }}</span>
                                <span class="font-bold text-ink-900 dark:text-white ml-2 text-sm">{{ $sv->jenis_hp }}</span>
                            </div>
                            <x-prism.status-pill :status="$sv->status" />
                        </div>
                        <p class="text-xs text-ink-500 dark:text-ink-400 mt-2">{{ $sv->keluhan }}</p>
                        @if($sv->estimasi_biaya !== null || $sv->status_pembayaran)
                            <div class="flex flex-wrap items-center gap-2 mt-2 pt-2 border-t border-ink-50 dark:border-white/5 text-xs">
                                @if($sv->estimasi_biaya !== null)
                                    <span class="text-ink-500 dark:text-ink-400">
                                        Estimasi Biaya: <strong class="text-ink-900 dark:text-white tabular-nums">Rp {{ number_format($sv->estimasi_biaya, 0, ',', '.') }}</strong>
                                    </span>
                                @endif
                                @if($sv->status_pembayaran)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $sv->status_pembayaran === 'lunas' ? 'bg-up-mint/10 text-up-mint' : 'bg-up-amber/10 text-up-amber' }}">
                                        {{ $sv->status_pembayaran === 'lunas' ? 'Lunas' : 'Belum Bayar' }}
                                    </span>
                                @endif
                            </div>
                        @endif
                        <div class="flex items-center justify-between mt-3 pt-3 border-t border-ink-100 dark:border-white/10">
                            <span class="text-xs {{ $sv->garansi && $sv->garansi->active ? 'text-up-mint font-bold' : 'text-ink-400' }}">
                                {{ $sv->garansi && $sv->garansi->active ? '🛡️ Dalam Garansi s.d ' . $sv->garansi->tanggal_berakhir->format('d/m/Y') : 'Garansi ' . ($sv->garansi ? 'habis' : 'belum ada') }}
                            </span>
                            @if($sv->token_approval)
                                <a href="{{ url('/tracking/' . $sv->token_approval) }}" target="_blank" class="text-up-primary font-bold text-xs hover:underline">Lihat Tracking & Persetujuan →</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center py-14 bg-white dark:bg-ink-900 rounded-3xl border border-ink-100 dark:border-white/10 p-6 shadow-sm">
                        <p class="font-semibold text-ink-700 dark:text-ink-300">Belum ada servis</p>
                    </div>
                @endforelse
            </div>
        @endif

        <!-- KOMISI -->
        @if($activeTab === 'komisi' && $customer->is_reseller)
            <div class="space-y-3">
                @forelse($komisi as $k)
                    <div class="bg-white dark:bg-ink-900 rounded-3xl border border-ink-100 dark:border-white/10 p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-up-accent font-bold text-sm">{{ $k->no_komisi }}</span>
                            <x-prism.status-pill :status="$k->status" />
                        </div>
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-xs text-ink-500 dark:text-ink-400">{{ $k->keterangan }}</span>
                            <span class="font-black text-up-accent tabular-nums">Rp {{ number_format($k->nominal_komisi, 0, ',', '.') }}</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-14 bg-white dark:bg-ink-900 rounded-3xl border border-ink-100 dark:border-white/10 p-6 shadow-sm">
                        <p class="font-semibold text-ink-700 dark:text-ink-300">Belum ada komisi</p>
                        <p class="text-xs text-ink-400 mt-1">Komisi otomatis terhitung dari transaksi atas nama Anda.</p>
                    </div>
                @endforelse
            </div>
        @endif
        <!-- MEMBERSHIP & POIN LOYALTY -->
        @if($activeTab === 'membership')
            <div class="space-y-6">
                <!-- Status Saat Ini & Keuntungan -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Kartu Tier -->
                    <div class="bg-white dark:bg-ink-900 rounded-2xl border border-ink-100 dark:border-white/10 p-6 relative overflow-hidden">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <span class="text-xs text-ink-400 uppercase tracking-wider font-semibold">Tingkat Membership</span>
                                <h3 class="text-xl font-black text-ink-900 dark:text-white mt-0.5">{{ $membershipInfo['tierName'] }}</h3>
                            </div>
                            <x-prism.tier-badge :tier="$membershipInfo['tierName']" />
                        </div>

                        <div class="space-y-2.5 pt-2 border-t border-ink-100 dark:border-white/10 text-xs">
                            <div class="flex justify-between items-center">
                                <span class="text-ink-500 dark:text-ink-400">🏷️ Potongan Harga Katalog:</span>
                                <span class="font-bold text-ink-900 dark:text-white">{{ $membershipInfo['diskonPersen'] > 0 ? $membershipInfo['diskonPersen'] . '%' : 'Harga Normal' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-ink-500 dark:text-ink-400">⚡ Pengali Poin Loyalty:</span>
                                <span class="font-bold text-up-primary">{{ $membershipInfo['poinMultiplier'] }}x Poin</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-ink-500 dark:text-ink-400">💰 Akumulasi Belanja 12 Bulan:</span>
                                <span class="font-bold text-ink-900 dark:text-white tabular-nums">Rp {{ number_format($membershipInfo['totalBelanja'], 0, ',', '.') }}</span>
                            </div>
                        </div>

                        <!-- Progress Bar ke Tier Berikutnya -->
                        <div class="mt-5 pt-4 border-t border-ink-100 dark:border-white/10">
                            @if($membershipInfo['nextTier'])
                                <div class="flex justify-between items-center text-xs mb-1.5">
                                    <span class="text-ink-500 dark:text-ink-400">Target ke <strong class="text-ink-900 dark:text-white">{{ $membershipInfo['nextTier']->nama }}</strong></span>
                                    <span class="font-bold text-up-primary tabular-nums">{{ $membershipInfo['progress'] }}%</span>
                                </div>
                                <div class="w-full h-2 rounded-full bg-ink-100 dark:bg-white/10 overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-up-primary to-up-mint rounded-full transition-[width] duration-250 ease-out" style="width: {{ $membershipInfo['progress'] }}%"></div>
                                </div>
                                <p class="text-[11px] text-ink-400 mt-2">
                                    Belanja <strong class="text-ink-800 dark:text-ink-200 tabular-nums">Rp {{ number_format($membershipInfo['kekurangan'], 0, ',', '.') }}</strong> lagi untuk naik tingkat.
                                </p>
                            @else
                                <div class="p-3 rounded-xl bg-up-mint/10 border border-up-mint/20 text-center">
                                    <p class="text-xs font-bold text-up-mint">👑 Tingkat Membership Tertinggi</p>
                                    <p class="text-[11px] text-ink-500 dark:text-ink-400 mt-0.5">Anda menikmati diskon dan multiplier poin maksimal.</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Kartu Poin Loyalty -->
                    <div class="bg-white dark:bg-ink-900 rounded-2xl border border-ink-100 dark:border-white/10 p-6 flex flex-col justify-between">
                        <div>
                            <span class="text-xs text-ink-400 uppercase tracking-wider font-semibold">Saldo Poin Loyalty</span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <span class="text-3xl font-black text-up-primary tabular-nums">{{ number_format($membershipInfo['poinLoyalty'], 0, ',', '.') }}</span>
                                <span class="text-sm font-bold text-ink-500">Poin</span>
                            </div>
                            <p class="text-xs text-ink-500 dark:text-ink-400 mt-3 leading-relaxed">
                                Poin diperoleh otomatis dari setiap transaksi belanja suku cadang atau servis yang telah lunas.
                            </p>
                        </div>

                        <div class="mt-4 p-3.5 rounded-xl bg-ink-50 dark:bg-white/5 border border-ink-100 dark:border-white/10 text-xs space-y-1.5">
                            <p class="font-bold text-ink-900 dark:text-white">💡 Cara Perolehan Poin:</p>
                            <p class="text-ink-500 dark:text-ink-400">
                                Setiap kelipatan <strong class="text-ink-800 dark:text-ink-200">Rp 1.000</strong> belanja = <strong>1 poin dasar</strong> × multiplier tier (<strong class="text-up-primary">{{ $membershipInfo['poinMultiplier'] }}x</strong>).
                            </p>
                            <p class="text-[11px] text-ink-400">
                                Contoh: Belanja Rp 100.000 memperoleh <span class="font-bold text-up-primary tabular-nums">{{ (int)(100 * $membershipInfo['poinMultiplier']) }} poin</span>.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Perbandingan Seluruh Tingkatan Level -->
                <div class="bg-white dark:bg-ink-900 rounded-2xl border border-ink-100 dark:border-white/10 p-6">
                    <h3 class="text-base font-bold text-ink-900 dark:text-white mb-1">Tingkatan Level Membership</h3>
                    <p class="text-xs text-ink-500 dark:text-ink-400 mb-4">Level dievaluasi otomatis berdasarkan akumulasi total belanja Anda selama 12 bulan terakhir.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <!-- Level Retail / Dasar -->
                        <div class="p-4 rounded-xl border {{ !$membershipInfo['currentTier'] && !$customer->is_reseller ? 'border-up-primary bg-up-primary/5 ring-2 ring-up-primary/20' : 'border-ink-100 dark:border-white/10 bg-ink-50 dark:bg-white/5' }}">
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-black text-ink-900 dark:text-white text-sm">Retail</span>
                                @if(!$membershipInfo['currentTier'] && !$customer->is_reseller)
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-up-primary text-white">Aktif</span>
                                @endif
                            </div>
                            <p class="text-[11px] text-ink-400">Syarat: <span class="font-semibold text-ink-700 dark:text-ink-300">Belanja awal</span></p>
                            <div class="mt-3 pt-2 border-t border-ink-100 dark:border-white/10 text-xs space-y-1">
                                <p class="text-ink-600 dark:text-ink-300">Diskon: <strong class="text-ink-900 dark:text-white">0%</strong></p>
                                <p class="text-ink-600 dark:text-ink-300">Multiplier: <strong class="text-ink-900 dark:text-white">1.0x</strong></p>
                            </div>
                        </div>

                        <!-- Tier dari Database -->
                        @foreach($membershipInfo['allTiers'] as $tier)
                            @php
                                $isCurrent = $membershipInfo['currentTier']?->id === $tier->id;
                            @endphp
                            <div class="p-4 rounded-xl border {{ $isCurrent ? 'border-up-primary bg-up-primary/5 ring-2 ring-up-primary/20' : 'border-ink-100 dark:border-white/10 bg-ink-50 dark:bg-white/5' }}">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="font-black text-ink-900 dark:text-white text-sm">{{ $tier->nama }}</span>
                                    @if($isCurrent)
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-up-primary text-white">Aktif</span>
                                    @endif
                                </div>
                                <p class="text-[11px] text-ink-400">
                                    Min. Belanja: <strong class="text-ink-700 dark:text-ink-300 tabular-nums">Rp {{ number_format($tier->min_belanja_12bulan, 0, ',', '.') }}</strong>
                                </p>
                                <div class="mt-3 pt-2 border-t border-ink-100 dark:border-white/10 text-xs space-y-1">
                                    <p class="text-ink-600 dark:text-ink-300">Diskon: <strong class="text-ink-900 dark:text-white">{{ $tier->diskon_persen }}%</strong></p>
                                    <p class="text-ink-600 dark:text-ink-300">Multiplier: <strong class="text-up-primary">{{ $tier->poin_multiplier }}x</strong></p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <!-- PROFIL & KEAMANAN AKUN -->
        @if($activeTab === 'profil')
            <div class="space-y-6">
                <!-- Card 1: Data Profil & Foto Profil -->
                <div class="bg-white dark:bg-ink-900 rounded-3xl border border-ink-100 dark:border-white/10 p-6 sm:p-8 shadow-sm">
                    <div class="border-b border-ink-100 dark:border-white/10 pb-4 mb-6">
                        <h2 class="text-base sm:text-lg font-black text-ink-900 dark:text-white">Profil Konsumen</h2>
                        <p class="text-xs text-ink-500 dark:text-ink-400 mt-0.5">Kelola informasi identitas akun, username, dan foto profil Anda.</p>
                    </div>

                    <form wire:submit.prevent="simpanProfil" class="space-y-5">
                        <!-- Foto Profil Avatar -->
                        <div>
                            <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300 uppercase tracking-wider mb-2">Foto Profil (Avatar)</label>
                            <div class="flex items-center gap-4 flex-wrap sm:flex-nowrap">
                                <div class="relative w-16 h-16 rounded-2xl bg-ink-100 dark:bg-white/5 border border-ink-200 dark:border-white/10 overflow-hidden flex items-center justify-center flex-shrink-0">
                                    @if($avatarUpload)
                                        <img src="{{ $avatarUpload->temporaryUrl() }}" alt="Preview" class="w-full h-full object-cover">
                                    @elseif($customer->avatar)
                                        <img src="{{ $customer->avatar }}" alt="{{ $customer->nama }}" class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full bg-gradient-to-tr from-up-primary to-up-accent flex items-center justify-center text-white font-black text-xl">
                                            {{ substr($customer->nama, 0, 1) }}
                                        </div>
                                    @endif
                                </div>

                                <div class="space-y-2 flex-1 min-w-[200px]">
                                    <input type="file" wire:model="avatarUpload" accept="image/*"
                                           class="text-xs text-ink-500 dark:text-ink-400 file:mr-3 file:py-2 file:px-3.5 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-up-primary/10 file:text-up-primary hover:file:bg-up-primary/20 cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <p class="text-[11px] text-ink-400">Format JPG/PNG/WebP, maksimal 2MB.</p>
                                        @if($customer->avatar || $avatarUpload)
                                            <button type="button" wire:click="hapusAvatar" wire:confirm="Yakin ingin menghapus foto profil ini?"
                                                    class="text-[11px] text-up-red hover:underline font-semibold cursor-pointer">
                                                Hapus Foto
                                            </button>
                                        @endif
                                    </div>
                                    @error('avatarUpload') <p class="text-xs text-up-red font-medium">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Nama Lengkap -->
                            <div>
                                <label for="nama" class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1.5">Nama Lengkap</label>
                                <input type="text" id="nama" wire:model="nama" required
                                       class="w-full px-4 py-2.5 rounded-xl bg-ink-50 dark:bg-white/5 border border-ink-200 dark:border-white/10 focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 outline-none text-sm font-medium text-ink-900 dark:text-white" />
                                @error('nama') <p class="text-xs text-up-red font-medium mt-1">{{ $message }}</p> @enderror
                            </div>

                            <!-- Username Unik -->
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label for="username" class="block text-xs font-semibold text-ink-700 dark:text-ink-300">Username (Opsional)</label>
                                    <span class="text-[10px] text-ink-400 font-mono">Bisa digunakan untuk login</span>
                                </div>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-ink-400 font-mono text-sm">@</span>
                                    <input type="text" id="username" wire:model="username" placeholder="budi_parts"
                                           class="w-full pl-8 pr-4 py-2.5 rounded-xl bg-ink-50 dark:bg-white/5 border border-ink-200 dark:border-white/10 focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 outline-none text-sm font-medium text-ink-900 dark:text-white placeholder:text-ink-400 font-mono" />
                                </div>
                                @error('username') <p class="text-xs text-up-red font-medium mt-1">{{ $message }}</p> @enderror
                            </div>

                            <!-- Nomor Telepon (Readonly) -->
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="block text-xs font-semibold text-ink-700 dark:text-ink-300">Nomor WhatsApp / HP</label>
                                    <span class="text-[10px] text-ink-400">Terdaftar</span>
                                </div>
                                <input type="text" value="{{ $customer->telepon }}" readonly disabled
                                       class="w-full px-4 py-2.5 rounded-xl bg-ink-100 dark:bg-white/5 border border-ink-200 dark:border-white/5 text-ink-500 font-mono text-sm cursor-not-allowed" />
                                <p class="text-[10px] text-ink-400 mt-1">Untuk ubah nomor HP, silakan hubungi Customer Service toko.</p>
                            </div>

                            <!-- Email -->
                            <div>
                                <label for="email" class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1.5">Email (Opsional)</label>
                                <input type="email" id="email" wire:model="email" placeholder="budi@contoh.com"
                                       class="w-full px-4 py-2.5 rounded-xl bg-ink-50 dark:bg-white/5 border border-ink-200 dark:border-white/10 focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 outline-none text-sm font-medium text-ink-900 dark:text-white placeholder:text-ink-400" />
                                @error('email') <p class="text-xs text-up-red font-medium mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <!-- Alamat Pengiriman Utama -->
                        <div>
                            <label for="alamat" class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1.5">Alamat Lengkap</label>
                            <textarea id="alamat" wire:model="alamat" rows="2" placeholder="Nama jalan, nomor rumah, RT/RW, kelurahan, kecamatan, kota..."
                                      class="w-full px-4 py-2.5 rounded-xl bg-ink-50 dark:bg-white/5 border border-ink-200 dark:border-white/10 focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 outline-none text-sm font-medium text-ink-900 dark:text-white placeholder:text-ink-400"></textarea>
                            @error('alamat') <p class="text-xs text-up-red font-medium mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex justify-end pt-2">
                            <button type="submit" wire:loading.attr="disabled"
                                    class="px-5 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs sm:text-sm shadow-md shadow-up-primary/20 transition active:scale-[0.98] min-h-[44px] cursor-pointer flex items-center gap-2">
                                <span wire:loading.remove wire:target="simpanProfil">Simpan Perubahan Profil</span>
                                <span wire:loading wire:target="simpanProfil">Menyimpan...</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Card 2: Ubah Kata Sandi Akun -->
                <div class="bg-white dark:bg-ink-900 rounded-3xl border border-ink-100 dark:border-white/10 p-6 sm:p-8 shadow-sm">
                    <div class="border-b border-ink-100 dark:border-white/10 pb-4 mb-6">
                        <h2 class="text-base sm:text-lg font-black text-ink-900 dark:text-white">Ubah Kata Sandi</h2>
                        <p class="text-xs text-ink-500 dark:text-ink-400 mt-0.5">Pastikan kata sandi Anda kuat untuk mengamankan riwayat transaksi dan poin loyalty.</p>
                    </div>

                    <form wire:submit.prevent="updatePassword" class="space-y-4 max-w-xl">
                        <div>
                            <label for="currentPassword" class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1.5">Kata Sandi Saat Ini</label>
                            <input type="password" id="currentPassword" wire:model="currentPassword" required placeholder="••••••••"
                                   class="w-full px-4 py-2.5 rounded-xl bg-ink-50 dark:bg-white/5 border border-ink-200 dark:border-white/10 focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 outline-none text-sm font-medium text-ink-900 dark:text-white" />
                            @error('currentPassword') <p class="text-xs text-up-red font-medium mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="newPassword" class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1.5">Kata Sandi Baru</label>
                                <input type="password" id="newPassword" wire:model="newPassword" required placeholder="Minimal 6 karakter"
                                       class="w-full px-4 py-2.5 rounded-xl bg-ink-50 dark:bg-white/5 border border-ink-200 dark:border-white/10 focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 outline-none text-sm font-medium text-ink-900 dark:text-white" />
                                @error('newPassword') <p class="text-xs text-up-red font-medium mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="newPassword_confirmation" class="block text-xs font-semibold text-ink-700 dark:text-ink-300 mb-1.5">Konfirmasi Kata Sandi Baru</label>
                                <input type="password" id="newPassword_confirmation" wire:model="newPassword_confirmation" required placeholder="Ulangi kata sandi baru"
                                       class="w-full px-4 py-2.5 rounded-xl bg-ink-50 dark:bg-white/5 border border-ink-200 dark:border-white/10 focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 outline-none text-sm font-medium text-ink-900 dark:text-white" />
                            </div>
                        </div>

                        <div class="pt-2 flex items-center justify-between flex-wrap gap-3">
                            <button type="submit" wire:loading.attr="disabled"
                                    class="px-5 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs sm:text-sm shadow-md shadow-up-primary/20 transition active:scale-[0.98] min-h-[44px] cursor-pointer flex items-center gap-2">
                                <span wire:loading.remove wire:target="updatePassword">Perbarui Kata Sandi</span>
                                <span wire:loading wire:target="updatePassword">Memproses...</span>
                            </button>

                            <p class="text-[11px] text-ink-400">Lupa kata sandi saat ini? Hubungi CS Toko untuk verifikasi dan reset akun.</p>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endif
</div>