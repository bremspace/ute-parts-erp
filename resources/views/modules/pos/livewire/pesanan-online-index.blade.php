<div class="space-y-6" wire:poll.30s>
    <!-- Header & Metric Cards -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-ink-900 dark:text-white tracking-tight flex items-center gap-2.5">
                <div class="p-2 rounded-xl bg-up-primary/10 text-up-primary dark:bg-up-primary/20">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                </div>
                <span>Pesanan Online &amp; Omnichannel</span>
            </h1>
            <p class="text-xs text-ink-500 dark:text-ink-400 mt-1">
                Fulfillment pesanan Web Konsumen Ute, Shopee, Tokopedia, serah terima pickup kasir toko, dan pelacakan kurir.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <select wire:model.live="selectedCabangId" class="px-3 py-2 rounded-xl bg-white dark:bg-ink-900 border border-ink-200 dark:border-white/10 text-xs font-bold text-ink-900 dark:text-white outline-none cursor-pointer">
                <option value="">Semua Cabang</option>
                @foreach($cabangs as $c)
                    <option value="{{ $c->id }}">{{ $c->nama }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Quick Metrics Bar -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <!-- Perlu Dikemas (Lunas) - Priority Blinking -->
        <div wire:click="$set('statusFilter', 'lunas')" class="p-4 rounded-2xl border transition-all duration-150 cursor-pointer relative overflow-hidden {{ $statusFilter === 'lunas' ? 'bg-up-primary/10 border-up-primary ring-2 ring-up-primary/20' : 'bg-white dark:bg-ink-900 border-ink-200/80 dark:border-white/10 hover:border-up-primary/50' }}">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-ink-500 dark:text-ink-400">Siap Dikemas</span>
                @if($counts['lunas'] > 0)
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-up-primary opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-up-primary animate-pulse"></span>
                    </span>
                @endif
            </div>
            <p class="text-2xl font-black text-up-primary mt-1 tabular-nums">{{ $counts['lunas'] }}</p>
            <p class="text-[10px] text-ink-400 mt-0.5">Sudah lunas, perlu packing</p>
        </div>

        <!-- Menunggu Pembayaran -->
        <div wire:click="$set('statusFilter', 'menunggu_pembayaran')" class="p-4 rounded-2xl border transition-all duration-150 cursor-pointer {{ $statusFilter === 'menunggu_pembayaran' ? 'bg-up-amber/10 border-up-amber ring-2 ring-up-amber/20' : 'bg-white dark:bg-ink-900 border-ink-200/80 dark:border-white/10 hover:border-up-amber/50' }}">
            <span class="text-[11px] font-bold uppercase tracking-wider text-ink-500 dark:text-ink-400">Menunggu Bayar</span>
            <p class="text-2xl font-black text-up-amber mt-1 tabular-nums">{{ $counts['menunggu_pembayaran'] }}</p>
            <p class="text-[10px] text-ink-400 mt-0.5">Gateway / Pay at Counter</p>
        </div>

        <!-- Sedang Dikemas -->
        <div wire:click="$set('statusFilter', 'diproses')" class="p-4 rounded-2xl border transition-all duration-150 cursor-pointer {{ $statusFilter === 'diproses' ? 'bg-indigo-500/10 border-indigo-500 ring-2 ring-indigo-500/20' : 'bg-white dark:bg-ink-900 border-ink-200/80 dark:border-white/10 hover:border-indigo-500/50' }}">
            <span class="text-[11px] font-bold uppercase tracking-wider text-ink-500 dark:text-ink-400">Sedang Dikemas</span>
            <p class="text-2xl font-black text-indigo-400 mt-1 tabular-nums">{{ $counts['diproses'] }}</p>
            <p class="text-[10px] text-ink-400 mt-0.5">Gudang menyiapkan item</p>
        </div>

        <!-- Dikirim / Siap Diambil -->
        <div wire:click="$set('statusFilter', 'dikirim')" class="p-4 rounded-2xl border transition-all duration-150 cursor-pointer {{ $statusFilter === 'dikirim' ? 'bg-sky-500/10 border-sky-500 ring-2 ring-sky-500/20' : 'bg-white dark:bg-ink-900 border-ink-200/80 dark:border-white/10 hover:border-sky-500/50' }}">
            <span class="text-[11px] font-bold uppercase tracking-wider text-ink-500 dark:text-ink-400">Pengiriman / Ready</span>
            <p class="text-2xl font-black text-sky-400 mt-1 tabular-nums">{{ $counts['dikirim'] }}</p>
            <p class="text-[10px] text-ink-400 mt-0.5">Kurir / Siap Ambil Toko</p>
        </div>

        <!-- Selesai -->
        <div wire:click="$set('statusFilter', 'selesai')" class="p-4 rounded-2xl border transition-all duration-150 cursor-pointer {{ $statusFilter === 'selesai' ? 'bg-up-mint/10 border-up-mint ring-2 ring-up-mint/20' : 'bg-white dark:bg-ink-900 border-ink-200/80 dark:border-white/10 hover:border-up-mint/50' }}">
            <span class="text-[11px] font-bold uppercase tracking-wider text-ink-500 dark:text-ink-400">Pesanan Selesai</span>
            <p class="text-2xl font-black text-up-mint mt-1 tabular-nums">{{ $counts['selesai'] }}</p>
            <p class="text-[10px] text-ink-400 mt-0.5">Terkirim / Diambil di Toko</p>
        </div>

        <!-- Total Semua -->
        <div wire:click="$set('statusFilter', 'semua')" class="p-4 rounded-2xl border transition-all duration-150 cursor-pointer {{ $statusFilter === 'semua' ? 'bg-ink-200 dark:bg-ink-800 border-ink-400' : 'bg-white dark:bg-ink-900 border-ink-200/80 dark:border-white/10 hover:border-ink-400' }}">
            <span class="text-[11px] font-bold uppercase tracking-wider text-ink-500 dark:text-ink-400">Total Semua</span>
            <p class="text-2xl font-black text-ink-900 dark:text-white mt-1 tabular-nums">{{ $counts['semua'] }}</p>
            <p class="text-[10px] text-ink-400 mt-0.5">Web + Omnichannel</p>
        </div>
    </div>

    <!-- Filter & Search Panel -->
    <div class="bg-white dark:bg-ink-900 rounded-3xl border border-ink-100 dark:border-white/10 p-4 shadow-sm space-y-3">
        <!-- Baris 1: Filter Status Tabs -->
        <div class="flex items-center justify-between flex-wrap gap-2 pb-3 border-b border-ink-100 dark:border-white/10">
            <div class="flex items-center gap-1 overflow-x-auto scrollbar-none py-1">
                @php
                    $tabs = [
                        'lunas' => 'Siap Dikemas',
                        'menunggu_pembayaran' => 'Menunggu Bayar',
                        'diproses' => 'Sedang Dikemas',
                        'dikirim' => 'Pengiriman / Pickup',
                        'selesai' => 'Selesai',
                        'batal' => 'Dibatalkan',
                        'semua' => 'Semua',
                    ];
                @endphp
                @foreach($tabs as $k => $label)
                    <button
                        type="button"
                        wire:click="$set('statusFilter', '{{ $k }}')"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap cursor-pointer transition-colors {{ $statusFilter === $k ? 'bg-up-primary text-white shadow-sm shadow-up-primary/30' : 'text-ink-600 dark:text-ink-400 hover:bg-black/5 dark:hover:bg-white/5' }}"
                    >
                        {{ $label }}
                        @if(isset($counts[$k]) && $counts[$k] > 0 && $k !== 'semua')
                            <span class="ml-1 text-[10px] px-1.5 py-0.2 rounded-full {{ $statusFilter === $k ? 'bg-white/20 text-white' : 'bg-black/10 dark:bg-white/10 text-ink-400' }}">
                                {{ $counts[$k] }}
                            </span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Baris 2: Filter Channel Sumber, Metode Ambil & Search Box -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex items-center gap-2 w-full sm:w-auto flex-wrap">
                <!-- Dropdown Sumber Platform -->
                <select wire:model.live="sumberFilter" class="px-3 py-1.5 rounded-xl bg-ink-50 dark:bg-ink-950 border border-ink-200 dark:border-white/10 text-xs font-bold text-ink-900 dark:text-white outline-none cursor-pointer">
                    <option value="semua">Semua Platform (Web &amp; Omni)</option>
                    <option value="marketplace">🌐 Web Ute Parts Store</option>
                    <option value="shopee">🧡 Shopee</option>
                    <option value="tokopedia">💚 Tokopedia</option>
                    <option value="omni_lain">📦 Omnichannel Lainnya</option>
                </select>

                <!-- Dropdown Metode Pengambilan -->
                <select wire:model.live="metodeFilter" class="px-3 py-1.5 rounded-xl bg-ink-50 dark:bg-ink-950 border border-ink-200 dark:border-white/10 text-xs font-bold text-ink-900 dark:text-white outline-none cursor-pointer">
                    <option value="semua">Semua Metode Ambil</option>
                    <option value="ambil_ke_toko">🏬 Ambil di Toko (Pickup Kasir)</option>
                    <option value="pengiriman">🚚 Kirim Ekspedisi / Kurir</option>
                </select>
            </div>

            <!-- Search Field -->
            <div class="w-full sm:w-72 relative">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="No. Order, Pelanggan, Resi, Channel ID..."
                    class="w-full px-3.5 py-2 pl-9 rounded-xl bg-ink-50 dark:bg-ink-950 border border-ink-200 dark:border-white/10 text-xs font-medium text-ink-900 dark:text-white outline-none focus:border-up-primary"
                />
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-ink-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Orders Table / List -->
    <div class="bg-white dark:bg-ink-900 rounded-3xl border border-ink-100 dark:border-white/10 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-ink-50/70 dark:bg-white/[0.02] border-b border-ink-200/70 dark:border-white/10 text-ink-500 dark:text-ink-400 uppercase tracking-wider font-bold text-[10px]">
                    <tr>
                        <th class="px-4 py-3.5">Sumber &amp; No. Pesanan</th>
                        <th class="px-4 py-3.5">Pelanggan / Cabang</th>
                        <th class="px-4 py-3.5">Metode &amp; Pengiriman</th>
                        <th class="px-4 py-3.5">Item Barang</th>
                        <th class="px-4 py-3.5">Total Akhir</th>
                        <th class="px-4 py-3.5">Status</th>
                        <th class="px-4 py-3.5 text-right">Aksi Alur Kerja</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100 dark:divide-white/5">
                    @forelse($orders as $o)
                        @php
                            $isPickup = str_contains(strtolower($o->catatan ?? ''), 'ambil_ke_toko');
                            $isShopee = str_contains($o->sumber, 'shopee');
                            $isTokopedia = str_contains($o->sumber, 'tokopedia');
                            $isChannel = str_starts_with($o->sumber, 'channel:');
                        @endphp
                        <tr class="hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors">
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-1.5 mb-1">
                                    @if($isShopee)
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-orange-500/15 text-orange-400 border border-orange-500/30">
                                            🧡 Shopee
                                        </span>
                                    @elseif($isTokopedia)
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                                            💚 Tokopedia
                                        </span>
                                    @elseif($isChannel)
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-500/15 text-indigo-400 border border-indigo-500/30">
                                            📦 {{ ucfirst(str_replace('channel:', '', $o->sumber)) }}
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-up-primary/15 text-up-primary border border-up-primary/30">
                                            🌐 Web Ute
                                        </span>
                                    @endif
                                </div>
                                <span class="font-mono font-bold text-ink-900 dark:text-white">{{ $o->no_transaksi }}</span>
                                <div class="text-[10px] text-ink-400 mt-0.5">
                                    {{ $o->created_at?->format('d/m/Y H:i') }}
                                </div>
                            </td>

                            <td class="px-4 py-3.5">
                                <div class="font-bold text-ink-900 dark:text-white">
                                    {{ $o->pelanggan?->nama ?? ($o->channelOrder?->payload['buyer_username'] ?? 'Konsumen Umum') }}
                                </div>
                                <div class="text-[10px] text-ink-400 font-mono">{{ $o->pelanggan?->telepon ?? '-' }}</div>
                                <div class="text-[10px] text-ink-500 font-semibold mt-0.5">
                                    Cabang: {{ $o->cabang?->nama ?? 'Pusat' }}
                                </div>
                            </td>

                            <td class="px-4 py-3.5">
                                @if($isPickup)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-bold bg-amber-500/10 text-amber-500 border border-amber-500/20">
                                        <span>🏬 Ambil di Toko (Pickup)</span>
                                    </span>
                                    <div class="text-[10px] text-ink-500 dark:text-ink-400 mt-0.5 font-medium">
                                        Konter Kasir {{ $o->cabang?->nama }}
                                    </div>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-bold bg-sky-500/10 text-sky-400 border border-sky-500/20">
                                        <span>🚚 Kirim Ekspedisi</span>
                                    </span>
                                    @if($o->pengiriman?->kurir)
                                        <div class="text-[10px] font-medium text-ink-600 dark:text-ink-300 mt-1">
                                            {{ $o->pengiriman->kurir }}
                                            @if($o->pengiriman->tracking_id)
                                                — <span class="font-mono text-up-primary">{{ $o->pengiriman->tracking_id }}</span>
                                            @endif
                                        </div>
                                    @endif
                                @endif
                            </td>

                            <td class="px-4 py-3.5">
                                <div class="space-y-1">
                                    @foreach($o->items->take(2) as $it)
                                        <div class="text-[11px] text-ink-800 dark:text-ink-200 truncate max-w-xs">
                                            {{ $it->produk?->nama }} <span class="font-mono text-ink-400">×{{ $it->jumlah }}</span>
                                        </div>
                                    @endforeach
                                    @if($o->items->count() > 2)
                                        <div class="text-[10px] text-ink-400 font-bold">+{{ $o->items->count() - 2 }} item lainnya</div>
                                    @endif
                                </div>
                            </td>

                            <td class="px-4 py-3.5">
                                <div class="font-black text-ink-900 dark:text-white tabular-nums">
                                    Rp {{ number_format($o->total_akhir, 0, ',', '.') }}
                                </div>
                                <div class="text-[10px] text-ink-400 uppercase font-mono">
                                    {{ $o->metode_bayar ?: 'Duitku' }}
                                </div>
                            </td>

                            <td class="px-4 py-3.5">
                                @if($o->status === 'menunggu_pembayaran')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-up-amber/15 text-up-amber border border-up-amber/30">
                                        <span class="w-1.5 h-1.5 rounded-full bg-up-amber animate-pulse"></span>
                                        <span>Menunggu Bayar</span>
                                    </span>
                                @elseif($o->status === 'lunas')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-up-primary/15 text-up-primary border border-up-primary/30">
                                        <span class="relative flex h-1.5 w-1.5">
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-up-primary opacity-75"></span>
                                            <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-up-primary animate-pulse"></span>
                                        </span>
                                        <span>{{ $isPickup ? 'Siap Diambil di Toko' : 'Siap Dikemas' }}</span>
                                    </span>
                                @elseif($o->status === 'diproses')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-indigo-500/15 text-indigo-400 border border-indigo-500/30">
                                        <span>Sedang Dikemas</span>
                                    </span>
                                @elseif($o->status === 'dikirim')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-sky-500/15 text-sky-400 border border-sky-500/30">
                                        <span>Sedang Dikirim</span>
                                    </span>
                                @elseif($o->status === 'siap_diambil')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-up-mint/15 text-up-mint border border-up-mint/30">
                                        <span>Siap Diambil</span>
                                    </span>
                                @elseif($o->status === 'selesai')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-up-mint/15 text-up-mint border border-up-mint/30">
                                        <span>Selesai</span>
                                    </span>
                                @elseif($o->status === 'batal')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-up-red/15 text-up-red border border-up-red/30">
                                        <span>Dibatalkan</span>
                                    </span>
                                @endif
                            </td>

                            <td class="px-4 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                    <!-- Tombol Detail -->
                                    <button
                                        type="button"
                                        wire:click="lihatDetail({{ $o->id }})"
                                        class="p-1.5 rounded-lg text-ink-500 hover:text-ink-900 dark:hover:text-white hover:bg-black/5 dark:hover:bg-white/5 transition-colors cursor-pointer"
                                        title="Detail Pesanan"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>

                                    <!-- Cetak Label / Struk -->
                                    <button
                                        type="button"
                                        wire:click="cetakLabel({{ $o->id }})"
                                        class="p-1.5 rounded-lg text-ink-500 hover:text-ink-900 dark:hover:text-white hover:bg-black/5 dark:hover:bg-white/5 transition-colors cursor-pointer"
                                        title="Cetak Label / Struk"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                    </button>

                                    <!-- KASUS A: METODE AMBIL DI TOKO (PICKUP) -->
                                    @if($isPickup)
                                        @if(in_array($o->status, ['menunggu_pembayaran', 'lunas', 'siap_diambil', 'diproses']))
                                            <button
                                                type="button"
                                                wire:click="bukaModalPickup({{ $o->id }})"
                                                class="px-2.5 py-1 rounded-lg text-xs font-bold text-white bg-amber-500 hover:bg-amber-600 shadow-sm transition-colors cursor-pointer flex items-center gap-1"
                                            >
                                                <span>🏬</span>
                                                <span>{{ $o->status === 'menunggu_pembayaran' ? 'Bayar & Pickup Kasir' : 'Serahkan Barang' }}</span>
                                            </button>
                                        @endif
                                    @else
                                        <!-- KASUS B: METODE PENGIRIMAN EKSPEDISI / KURIR -->
                                        @if($o->status === 'menunggu_pembayaran')
                                            <button
                                                type="button"
                                                wire:click="konfirmasiLunas({{ $o->id }})"
                                                wire:confirm="Konfirmasi pembayaran pesanan {{ $o->no_transaksi }} sudah lunas?"
                                                class="px-2.5 py-1 rounded-lg text-xs font-bold text-white bg-up-mint hover:bg-emerald-600 shadow-sm transition-colors cursor-pointer"
                                            >
                                                Verifikasi Lunas
                                            </button>
                                        @elseif($o->status === 'lunas')
                                            <button
                                                type="button"
                                                wire:click="mulaiKemas({{ $o->id }})"
                                                class="px-2.5 py-1 rounded-lg text-xs font-bold text-white bg-up-primary hover:bg-indigo-600 shadow-sm transition-colors cursor-pointer flex items-center gap-1"
                                            >
                                                <span>Mulai Kemas</span>
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                            </button>
                                        @elseif($o->status === 'diproses')
                                            <button
                                                type="button"
                                                wire:click="bukaModalKirim({{ $o->id }})"
                                                class="px-2.5 py-1 rounded-lg text-xs font-bold text-white bg-sky-500 hover:bg-sky-600 shadow-sm transition-colors cursor-pointer flex items-center gap-1"
                                            >
                                                <span>Kirim / Resi</span>
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                            </button>
                                        @elseif($o->status === 'dikirim')
                                            <button
                                                type="button"
                                                wire:click="selesaikanPesanan({{ $o->id }})"
                                                wire:confirm="Tandai pesanan {{ $o->no_transaksi }} telah selesai diterima konsumen?"
                                                class="px-2.5 py-1 rounded-lg text-xs font-bold text-white bg-up-mint hover:bg-emerald-600 shadow-sm transition-colors cursor-pointer"
                                            >
                                                Selesaikan
                                            </button>
                                        @endif
                                    @endif

                                    <!-- Batalkan Pesanan -->
                                    @if(!in_array($o->status, ['selesai', 'batal']))
                                        <button
                                            type="button"
                                            wire:click="bukaModalBatal({{ $o->id }})"
                                            class="p-1 rounded-lg text-ink-400 hover:text-up-red hover:bg-up-red/10 transition-colors cursor-pointer"
                                            title="Batalkan Pesanan"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center">
                                <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-black/5 dark:bg-white/5 text-ink-400 flex items-center justify-center">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                                </div>
                                <p class="text-xs font-bold text-ink-900 dark:text-white">Tidak ada pesanan</p>
                                <p class="text-[11px] text-ink-400 mt-1">Belum ada pesanan dengan filter status dan platform ini.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($orders->hasPages())
            <div class="p-4 border-t border-ink-100 dark:border-white/5">
                {{ $orders->links() }}
            </div>
        @endif
    </div>

    <!-- MODAL DETAIL PESANAN -->
    @if($showDetailModal && $selectedOrder)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-ink-950/70 backdrop-blur-xs">
            <div class="bg-white dark:bg-[#0B1120] rounded-3xl border border-ink-200/80 dark:border-white/10 shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden animate-scale-in">
                <!-- Header -->
                <div class="p-4 sm:p-5 border-b border-ink-100 dark:border-white/10 flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-up-primary">Detail Pesanan</span>
                            @if(str_contains($selectedOrder->sumber, 'shopee'))
                                <span class="px-2 py-0.2 rounded-md text-[10px] font-bold bg-orange-500/15 text-orange-400 border border-orange-500/30">Shopee</span>
                            @elseif(str_contains($selectedOrder->sumber, 'tokopedia'))
                                <span class="px-2 py-0.2 rounded-md text-[10px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">Tokopedia</span>
                            @else
                                <span class="px-2 py-0.2 rounded-md text-[10px] font-bold bg-up-primary/15 text-up-primary border border-up-primary/30">Web Ute</span>
                            @endif
                        </div>
                        <h3 class="text-base font-black text-ink-900 dark:text-white font-mono mt-0.5">{{ $selectedOrder->no_transaksi }}</h3>
                    </div>
                    <button type="button" wire:click="tutupDetail" class="p-2 rounded-xl text-ink-400 hover:text-ink-900 dark:hover:text-white hover:bg-black/5 dark:hover:bg-white/5 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-4 sm:p-5 overflow-y-auto space-y-4 text-xs">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 p-3.5 rounded-2xl bg-ink-50 dark:bg-white/[0.02] border border-ink-100 dark:border-white/5">
                        <div>
                            <span class="text-ink-400">Pelanggan:</span>
                            <p class="font-bold text-ink-900 dark:text-white mt-0.5">{{ $selectedOrder->pelanggan?->nama ?? 'Konsumen Umum' }}</p>
                        </div>
                        <div>
                            <span class="text-ink-400">No. Kontak / WA:</span>
                            <p class="font-bold text-ink-900 dark:text-white mt-0.5 font-mono">{{ $selectedOrder->pelanggan?->telepon ?? '-' }}</p>
                        </div>
                        <div>
                            <span class="text-ink-400">Status Transaksi:</span>
                            <p class="font-bold text-up-primary mt-0.5 uppercase">{{ str_replace('_', ' ', $selectedOrder->status) }}</p>
                        </div>
                    </div>

                    @if($selectedOrder->channelOrder)
                        <div class="p-3.5 rounded-2xl bg-orange-500/5 border border-orange-500/20 space-y-1">
                            <span class="text-[11px] font-bold text-orange-400 uppercase tracking-wider">Informasi Omnichannel</span>
                            <div class="grid grid-cols-2 gap-2 text-ink-700 dark:text-ink-300">
                                <div><span class="text-ink-400">Platform:</span> <span class="font-semibold">{{ $selectedOrder->channelOrder->channel?->nama ?? 'Channel' }}</span></div>
                                <div><span class="text-ink-400">Channel Order ID:</span> <span class="font-mono font-bold">{{ $selectedOrder->channelOrder->channel_order_id }}</span></div>
                                <div><span class="text-ink-400">Estimasi Fee Platform:</span> <span class="font-semibold text-rose-400">Rp {{ number_format($selectedOrder->channelOrder->estimasi_biaya_platform, 0, ',', '.') }}</span></div>
                            </div>
                        </div>
                    @endif

                    @if($selectedOrder->pengiriman)
                        <div class="p-3.5 rounded-2xl bg-sky-500/5 border border-sky-500/20 space-y-2">
                            <span class="text-[11px] font-bold text-sky-400 uppercase tracking-wider">Informasi Pengiriman</span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-ink-700 dark:text-ink-300">
                                <div>
                                    <span class="text-ink-400">Penerima:</span>
                                    <p class="font-semibold">{{ $selectedOrder->pengiriman->nama_penerima }} ({{ $selectedOrder->pengiriman->telepon_tujuan }})</p>
                                </div>
                                <div>
                                    <span class="text-ink-400">Kurir / No. Resi:</span>
                                    <p class="font-semibold">{{ $selectedOrder->pengiriman->kurir }} — <span class="font-mono text-up-primary">{{ $selectedOrder->pengiriman->tracking_id ?: 'Belum ada resi' }}</span></p>
                                </div>
                                <div class="sm:col-span-2">
                                    <span class="text-ink-400">Alamat Tujuan:</span>
                                    <p class="font-semibold leading-relaxed">{{ $selectedOrder->pengiriman->alamat_tujuan }}</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Daftar Item -->
                    <div>
                        <span class="text-[11px] font-bold text-ink-400 uppercase tracking-wider">Daftar Suku Cadang</span>
                        <div class="mt-2 divide-y divide-ink-100 dark:divide-white/5 border border-ink-100 dark:border-white/10 rounded-2xl overflow-hidden">
                            @foreach($selectedOrder->items as $it)
                                <div class="p-3 flex items-center justify-between gap-3 bg-white dark:bg-ink-950">
                                    <div class="min-w-0">
                                        <p class="font-bold text-ink-900 dark:text-white truncate">{{ $it->produk?->nama }}</p>
                                        <p class="text-[10px] text-ink-400 font-mono">SKU: {{ $it->produk?->sku ?? '-' }}</p>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <p class="font-bold text-ink-900 dark:text-white tabular-nums">Rp {{ number_format($it->subtotal, 0, ',', '.') }}</p>
                                        <p class="text-[10px] text-ink-400">{{ $it->jumlah }} × Rp {{ number_format($it->harga_satuan, 0, ',', '.') }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Total Calculation -->
                    <div class="pt-3 border-t border-ink-100 dark:border-white/10 space-y-1.5 text-right">
                        <div class="flex justify-between text-ink-500">
                            <span>Subtotal</span>
                            <span class="tabular-nums font-semibold">Rp {{ number_format($selectedOrder->subtotal, 0, ',', '.') }}</span>
                        </div>
                        @if((float) $selectedOrder->ppn_nominal > 0)
                            <div class="flex justify-between text-ink-500">
                                <span>PPN</span>
                                <span class="tabular-nums font-semibold">Rp {{ number_format($selectedOrder->ppn_nominal, 0, ',', '.') }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between text-base font-black text-ink-900 dark:text-white pt-1 border-t border-ink-100 dark:border-white/10">
                            <span>Total Akhir</span>
                            <span class="tabular-nums text-up-primary">Rp {{ number_format($selectedOrder->total_akhir, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="p-4 border-t border-ink-100 dark:border-white/10 bg-ink-50/50 dark:bg-white/[0.02] flex justify-end gap-2">
                    <button type="button" wire:click="tutupDetail" class="px-4 py-2 rounded-xl border border-ink-200 dark:border-white/10 font-bold text-xs text-ink-700 dark:text-ink-300 hover:bg-black/5 dark:hover:bg-white/5 cursor-pointer">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL SERAH TERIMA & KASIR PICKUP TOKO -->
    @if($showPickupModal && $pickupOrder)
        @php
            $isBelumLunas = $pickupOrder->status === 'menunggu_pembayaran';
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-ink-950/70 backdrop-blur-xs">
            <div class="bg-white dark:bg-[#0B1120] rounded-3xl border border-ink-200/80 dark:border-white/10 shadow-2xl w-full max-w-lg p-5 space-y-4 animate-scale-in">
                <div class="flex items-center justify-between pb-2 border-b border-ink-100 dark:border-white/10">
                    <h3 class="text-sm font-bold text-ink-900 dark:text-white flex items-center gap-2">
                        <span class="text-lg">🏬</span>
                        <span>{{ $isBelumLunas ? 'Pembayaran & Serah Terima Kasir Toko' : 'Serah Terima Pesanan Pickup' }}</span>
                    </h3>
                    <button type="button" wire:click="$set('showPickupModal', false)" class="text-ink-400 hover:text-ink-900 dark:hover:text-white cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-3 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-xs text-amber-700 dark:text-amber-300 space-y-1">
                    <div class="flex justify-between">
                        <span>No. Pesanan:</span>
                        <strong class="font-mono text-ink-900 dark:text-white">{{ $pickupOrder->no_transaksi }}</strong>
                    </div>
                    <div class="flex justify-between">
                        <span>Pelanggan:</span>
                        <strong class="text-ink-900 dark:text-white">{{ $pickupOrder->pelanggan?->nama ?? 'Konsumen Umum' }}</strong>
                    </div>
                    <div class="flex justify-between">
                        <span>Total Tagihan:</span>
                        <strong class="text-sm font-black text-up-primary tabular-nums">Rp {{ number_format($pickupOrder->total_akhir, 0, ',', '.') }}</strong>
                    </div>
                </div>

                <!-- Checklist Barang Serah Terima -->
                <div class="text-xs space-y-1.5">
                    <span class="font-bold text-[11px] text-ink-500 uppercase tracking-wider">Periksa Item yang Diserahkan:</span>
                    <div class="max-h-36 overflow-y-auto space-y-1 border border-ink-200 dark:border-white/10 rounded-xl p-2 bg-ink-50/50 dark:bg-white/[0.02]">
                        @foreach($pickupOrder->items as $it)
                            <div class="flex justify-between items-center text-[11px] py-1 border-b border-ink-100 dark:border-white/5 last:border-0">
                                <span class="font-medium text-ink-900 dark:text-white truncate">✓ {{ $it->produk?->nama }}</span>
                                <span class="font-mono font-bold text-up-primary">×{{ $it->jumlah }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Jika Belum Lunas: Pilihan Pembayaran Fisik Toko -->
                @if($isBelumLunas)
                    <div class="space-y-3 pt-2 border-t border-ink-100 dark:border-white/10 text-xs">
                        <div>
                            <label class="block text-[11px] font-bold text-ink-600 dark:text-ink-400 mb-1.5">Metode Bayar di Kasir Toko</label>
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    type="button"
                                    wire:click="$set('metodeBayarPickup', 'tunai')"
                                    class="p-2.5 rounded-xl border text-center transition-all cursor-pointer {{ $metodeBayarPickup === 'tunai' ? 'border-up-primary bg-up-primary/10 text-up-primary font-bold' : 'border-ink-200 dark:border-white/10 text-ink-700 dark:text-ink-300' }}"
                                >
                                    💵 Tunai (Kas Laci)
                                </button>
                                <button
                                    type="button"
                                    wire:click="$set('metodeBayarPickup', 'qris')"
                                    class="p-2.5 rounded-xl border text-center transition-all cursor-pointer {{ $metodeBayarPickup === 'qris' ? 'border-up-primary bg-up-primary/10 text-up-primary font-bold' : 'border-ink-200 dark:border-white/10 text-ink-700 dark:text-ink-300' }}"
                                >
                                    📱 QRIS Toko
                                </button>
                                <button
                                    type="button"
                                    wire:click="$set('metodeBayarPickup', 'edc')"
                                    class="p-2.5 rounded-xl border text-center transition-all cursor-pointer {{ $metodeBayarPickup === 'edc' ? 'border-up-primary bg-up-primary/10 text-up-primary font-bold' : 'border-ink-200 dark:border-white/10 text-ink-700 dark:text-ink-300' }}"
                                >
                                    💳 EDC Debit/Kredit
                                </button>
                            </div>
                        </div>

                        @if($metodeBayarPickup === 'tunai')
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-ink-600 dark:text-ink-400 mb-1">Nominal Diterima</label>
                                    <input
                                        type="number"
                                        wire:model.live.debounce.200ms="nominalBayarPickup"
                                        class="w-full px-3 py-2 rounded-xl bg-ink-50 dark:bg-ink-950 border border-ink-200 dark:border-white/10 text-xs font-mono font-bold text-ink-900 dark:text-white outline-none focus:border-up-primary"
                                    />
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-ink-600 dark:text-ink-400 mb-1">Kembalian</label>
                                    <div class="px-3 py-2 rounded-xl bg-ink-100 dark:bg-white/5 text-xs font-mono font-black text-up-mint">
                                        Rp {{ number_format($kembalianPickup, 0, ',', '.') }}
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="p-2.5 rounded-xl bg-up-mint/10 text-up-mint text-xs font-semibold flex items-center gap-1.5">
                        <span>✓</span>
                        <span>Pesanan ini sudah lunas via pembayaran online. Tinggal serahkan barang fisik.</span>
                    </div>
                @endif

                <div class="flex justify-end gap-2 pt-2 border-t border-ink-100 dark:border-white/10">
                    <button type="button" wire:click="$set('showPickupModal', false)" class="px-3.5 py-2 rounded-xl border border-ink-200 dark:border-white/10 text-xs font-bold text-ink-700 dark:text-ink-300 cursor-pointer">
                        Batal
                    </button>
                    <button type="button" wire:click="prosesPickupKasir" class="px-4 py-2 rounded-xl bg-up-mint hover:bg-emerald-600 text-xs font-bold text-white shadow-md shadow-up-mint/25 transition-colors cursor-pointer flex items-center gap-1">
                        <span>✓</span>
                        <span>{{ $isBelumLunas ? 'Terima Pembayaran & Serahkan' : 'Konfirmasi Serah Terima' }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL KIRIM & INPUT RESI -->
    @if($showKirimModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-ink-950/70 backdrop-blur-xs">
            <div class="bg-white dark:bg-[#0B1120] rounded-3xl border border-ink-200/80 dark:border-white/10 shadow-2xl w-full max-w-md p-5 space-y-4 animate-scale-in">
                <div class="flex items-center justify-between pb-2 border-b border-ink-100 dark:border-white/10">
                    <h3 class="text-sm font-bold text-ink-900 dark:text-white flex items-center gap-2">
                        <svg class="w-4 h-4 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        <span>Input Pengiriman &amp; Resi</span>
                    </h3>
                    <button type="button" wire:click="$set('showKirimModal', false)" class="text-ink-400 hover:text-ink-900 dark:hover:text-white cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block text-[11px] font-bold text-ink-600 dark:text-ink-400 mb-1">Pilih Ekspedisi / Kurir</label>
                        <select wire:model="kurirPilihan" class="w-full px-3 py-2 rounded-xl bg-ink-50 dark:bg-ink-950 border border-ink-200 dark:border-white/10 text-xs font-semibold text-ink-900 dark:text-white outline-none focus:border-up-primary">
                            <option value="J&T Express">J&amp;T Express</option>
                            <option value="JNE Express">JNE Express</option>
                            <option value="SiCepat">SiCepat Express</option>
                            <option value="AnterAja">AnterAja</option>
                            <option value="Shopee Xpress">Shopee Xpress</option>
                            <option value="Biteship Instant">Biteship Instant</option>
                            <option value="GoSend">GoSend (Gojek)</option>
                            <option value="GrabExpress">GrabExpress</option>
                            <option value="Kurir Toko / Internal">Kurir Toko / Internal</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-ink-600 dark:text-ink-400 mb-1">Nomor Resi / Bukti Tracking</label>
                        <input
                            type="text"
                            wire:model="nomorResi"
                            placeholder="Contoh: JT98231024823"
                            class="w-full px-3 py-2 rounded-xl bg-ink-50 dark:bg-ink-950 border border-ink-200 dark:border-white/10 text-xs font-mono font-bold text-ink-900 dark:text-white outline-none focus:border-up-primary"
                        />
                        <p class="text-[10px] text-ink-400 mt-1">Nomor resi otomatis dikirim via WhatsApp dan notifikasi akun konsumen.</p>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-ink-100 dark:border-white/10">
                    <button type="button" wire:click="$set('showKirimModal', false)" class="px-3.5 py-2 rounded-xl border border-ink-200 dark:border-white/10 text-xs font-bold text-ink-700 dark:text-ink-300 cursor-pointer">
                        Batal
                    </button>
                    <button type="button" wire:click="simpanPengiriman" class="px-4 py-2 rounded-xl bg-sky-500 hover:bg-sky-600 text-xs font-bold text-white shadow-md shadow-sky-500/25 transition-colors cursor-pointer">
                        Kirim &amp; Beritahu Konsumen
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL BATALKAN PESANAN -->
    @if($showBatalModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-ink-950/70 backdrop-blur-xs">
            <div class="bg-white dark:bg-[#0B1120] rounded-3xl border border-ink-200/80 dark:border-white/10 shadow-2xl w-full max-w-md p-5 space-y-4 animate-scale-in">
                <div class="flex items-center justify-between pb-2 border-b border-ink-100 dark:border-white/10">
                    <h3 class="text-sm font-bold text-up-red flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span>Batalkan Pesanan</span>
                    </h3>
                    <button type="button" wire:click="$set('showBatalModal', false)" class="text-ink-400 hover:text-ink-900 dark:hover:text-white cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <p class="text-ink-600 dark:text-ink-300">
                        Jika pesanan sudah lunas atau diproses, stok yang telah terpotong akan <strong>otomatis dikembalikan</strong> ke saldo gudang.
                    </p>
                    <div>
                        <label class="block text-[11px] font-bold text-ink-600 dark:text-ink-400 mb-1">Alasan Pembatalan</label>
                        <textarea
                            wire:model="alasanBatal"
                            rows="2"
                            placeholder="Contoh: Stok barang habis / konsumen meminta pembatalan..."
                            class="w-full px-3 py-2 rounded-xl bg-ink-50 dark:bg-ink-950 border border-ink-200 dark:border-white/10 text-xs text-ink-900 dark:text-white outline-none focus:border-up-red"
                        ></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-ink-100 dark:border-white/10">
                    <button type="button" wire:click="$set('showBatalModal', false)" class="px-3.5 py-2 rounded-xl border border-ink-200 dark:border-white/10 text-xs font-bold text-ink-700 dark:text-ink-300 cursor-pointer">
                        Kembali
                    </button>
                    <button type="button" wire:click="eksekusiBatal" class="px-4 py-2 rounded-xl bg-up-red hover:bg-rose-600 text-xs font-bold text-white shadow-md shadow-up-red/25 transition-colors cursor-pointer">
                        Konfirmasi Batalkan
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL CETAK LABEL / STRUK KASIR -->
    @if($showReceiptModal && $receiptData)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-ink-950/70 backdrop-blur-xs">
            <div class="bg-white rounded-3xl border border-ink-200 shadow-2xl w-full max-w-sm p-6 text-ink-900 text-xs font-mono space-y-4 animate-scale-in">
                <div class="text-center pb-3 border-b border-dashed border-ink-300">
                    <h2 class="text-base font-black tracking-tight">UTE PARTS STORE</h2>
                    <p class="text-[10px] text-ink-500">{{ $receiptData['cabang'] }}</p>
                    <p class="text-[10px] text-ink-500 mt-1 uppercase">{{ $receiptData['is_pickup'] ? 'BUKTI SERAH TERIMA PICKUP TOKO' : 'SLIP PACKING & PENGIRIMAN' }}</p>
                    <p class="text-xs font-black text-up-primary mt-1">{{ $receiptData['no_transaksi'] }}</p>
                </div>

                <div class="space-y-1 text-[11px] pb-3 border-b border-dashed border-ink-300">
                    <div class="flex justify-between"><span>Pelanggan:</span><span class="font-bold">{{ $receiptData['pelanggan'] }}</span></div>
                    <div class="flex justify-between"><span>Kontak:</span><span>{{ $receiptData['telepon'] }}</span></div>
                    <div class="flex justify-between"><span>Metode:</span><span class="font-bold">{{ $receiptData['kurir'] }}</span></div>
                    @if($receiptData['resi'] !== '-')
                        <div class="flex justify-between"><span>No. Resi:</span><span class="font-bold text-up-primary">{{ $receiptData['resi'] }}</span></div>
                    @endif
                    <div class="flex justify-between"><span>Bayar:</span><span class="font-bold">{{ $receiptData['metode_bayar'] }}</span></div>
                </div>

                <div class="space-y-1.5 pb-3 border-b border-dashed border-ink-300 text-[11px]">
                    <div class="font-bold text-[10px] text-ink-500 uppercase tracking-wider mb-1">Daftar Suku Cadang:</div>
                    @foreach($receiptData['items'] as $it)
                        <div class="flex items-start justify-between gap-2">
                            <span>[✓] {{ $it['nama'] }}</span>
                            <span class="font-bold">×{{ $it['qty'] }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="space-y-1 text-xs font-black pt-1">
                    <div class="flex justify-between">
                        <span>Total Tagihan:</span>
                        <span>Rp {{ number_format($receiptData['total'], 0, ',', '.') }}</span>
                    </div>
                    @if($receiptData['is_pickup'])
                        <div class="flex justify-between text-[11px] font-normal text-ink-600">
                            <span>Bayar:</span>
                            <span>Rp {{ number_format($receiptData['bayar'], 0, ',', '.') }}</span>
                        </div>
                        @if($receiptData['kembalian'] > 0)
                            <div class="flex justify-between text-[11px] font-normal text-ink-600">
                                <span>Kembalian:</span>
                                <span>Rp {{ number_format($receiptData['kembalian'], 0, ',', '.') }}</span>
                            </div>
                        @endif
                    @endif
                </div>

                <div class="flex gap-2 pt-3">
                    <button type="button" wire:click="$set('showReceiptModal', false)" class="flex-1 py-2 rounded-xl border border-ink-300 text-xs font-bold font-sans text-ink-700 cursor-pointer">
                        Tutup
                    </button>
                    <button type="button" onclick="window.print()" class="flex-1 py-2 rounded-xl bg-ink-900 text-white text-xs font-bold font-sans hover:bg-black cursor-pointer">
                        Cetak Struk
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
