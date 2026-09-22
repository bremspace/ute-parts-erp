<div class="max-w-4xl mx-auto px-4 sm:px-6 py-8">
    @if(!$customer)
        <div class="text-center py-16 bg-white rounded-2xl border border-ink-100">
            <h2 class="font-black text-ink-900 text-lg">Silakan Masuk</h2>
            <p class="text-sm text-ink-500 mt-2">Login untuk melihat riwayat pesanan, servis, dan komisi Anda.</p>
            <a href="{{ route('customer.login') }}" class="inline-block mt-5 px-6 py-3 rounded-xl bg-up-primary text-white font-bold text-sm hover:bg-up-primary-dark transition-colors">Masuk</a>
        </div>
    @else
        <!-- Profil header -->
        <div class="bg-white rounded-2xl border border-ink-100 p-6 mb-6">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-up-primary to-up-accent flex items-center justify-center text-white font-black text-xl">
                    {{ substr($customer->nama, 0, 1) }}
                </div>
                <div class="flex-1">
                    <h1 class="font-black text-ink-900 text-lg">{{ $customer->nama }}</h1>
                    <p class="text-sm text-ink-500 font-mono">{{ $customer->telepon }}</p>
                </div>
                <div class="text-right">
                    <x-prism.tier-badge :tier="$customer->tierMembership?->nama ?? ($customer->is_reseller ? 'Reseller' : 'Retail')" />
                    <p class="text-xs text-ink-500 mt-2 tabular-nums">{{ $customer->poin_loyalty }} poin</p>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3 mt-5 text-center">
                <div class="p-3 rounded-xl bg-up-ink-50 border border-ink-100">
                    <p class="font-black text-ink-900 tabular-nums text-lg">{{ $orders->count() }}</p>
                    <p class="text-[10px] text-ink-400 uppercase font-semibold">Pesanan</p>
                </div>
                <div class="p-3 rounded-xl bg-up-ink-50 border border-ink-100">
                    <p class="font-black text-ink-900 tabular-nums text-lg">{{ $servis->count() }}</p>
                    <p class="text-[10px] text-ink-400 uppercase font-semibold">Servis</p>
                </div>
                <div class="p-3 rounded-xl bg-up-ink-50 border border-ink-100">
                    <p class="font-black {{ $customer->is_reseller ? 'text-up-accent' : 'text-ink-400' }} tabular-nums text-lg">
                        {{ $customer->is_reseller ? 'Rp ' . number_format($komisi->where('status', 'disetujui')->sum('nominal_komisi') + $komisi->where('status', 'pending')->sum('nominal_komisi'), 0, ',', '.') : '-' }}
                    </p>
                    <p class="text-[10px] text-ink-400 uppercase font-semibold">{{ $customer->is_reseller ? 'Komisi' : '—' }}</p>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex items-center gap-2 mb-5">
            @foreach(['orders' => 'Pesanan', 'servis' => 'Tracking Servis', 'komisi' => ($customer->is_reseller ? 'Komisi Saya' : '')] as $kode => $label)
                @if($label)
                    <button wire:click="$set('activeTab', '{{ $kode }}')" class="px-4 py-2 rounded-xl text-sm font-bold transition-all cursor-pointer {{ $activeTab === $kode ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white border border-ink-200 text-ink-600 hover:bg-ink-50' }}">{{ $label }}</button>
                @endif
            @endforeach
        </div>

        <!-- ORDERS -->
        @if($activeTab === 'orders')
            <div class="space-y-3">
                @forelse($orders as $o)
                    <div class="bg-white rounded-2xl border border-ink-100 p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-mono text-up-primary font-bold text-sm">{{ $o->no_transaksi }}</span>
                                <span class="text-xs text-ink-400 ml-2">{{ $o->created_at->format('d/m/Y H:i') }}</span>
                            </div>
                            <x-prism.status-pill :status="$o->status" />
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach($o->items as $it)
                                <span class="text-[11px] px-2 py-1 rounded-lg bg-up-ink-50 border border-ink-100 text-ink-600">{{ $it->produk?->nama }} ×{{ $it->jumlah }}</span>
                            @endforeach
                        </div>
                        <div class="flex justify-between items-center mt-3 pt-3 border-t border-ink-100">
                            <span class="text-xs text-ink-500">{{ $o->cabang?->nama }}</span>
                            <span class="font-black text-up-primary tabular-nums">Rp {{ number_format($o->total_akhir, 0, ',', '.') }}</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-14 bg-white rounded-2xl border border-ink-100">
                        <p class="font-semibold text-ink-700">Belum ada pesanan</p>
                        <a href="{{ route('shop') }}" class="inline-block mt-4 px-5 py-2.5 rounded-xl bg-up-primary text-white font-bold text-sm hover:bg-up-primary-dark transition-colors">Mulai Belanja</a>
                    </div>
                @endforelse
            </div>
        @endif

        <!-- SERVIS TRACKING -->
        @if($activeTab === 'servis')
            <div class="space-y-3">
                @forelse($servis as $sv)
                    <div class="bg-white rounded-2xl border border-ink-100 p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-mono text-up-primary font-bold text-sm">{{ $sv->no_tiket }}</span>
                                <span class="font-bold text-ink-900 ml-2 text-sm">{{ $sv->jenis_hp }}</span>
                            </div>
                            <x-prism.status-pill :status="$sv->status" />
                        </div>
                        <p class="text-xs text-ink-500 mt-2">{{ $sv->keluhan }}</p>
                        <div class="flex items-center justify-between mt-3 pt-3 border-t border-ink-100">
                            <span class="text-xs {{ $sv->garansi && $sv->garansi->active ? 'text-up-mint font-bold' : 'text-ink-400' }}">
                                {{ $sv->garansi && $sv->garansi->active ? '🛡️ Dalam Garansi s.d ' . $sv->garansi->tanggal_berakhir->format('d/m/Y') : 'Garansi ' . ($sv->garansi ? 'habis' : 'belum ada') }}
                            </span>
                            <a href="{{ url('/tracking/' . $sv->token_approval) }}" target="_blank" class="text-up-primary font-bold text-xs hover:underline">Lihat Tracking →</a>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-14 bg-white rounded-2xl border border-ink-100">
                        <p class="font-semibold text-ink-700">Belum ada servis</p>
                    </div>
                @endforelse
            </div>
        @endif

        <!-- KOMISI -->
        @if($activeTab === 'komisi' && $customer->is_reseller)
            <div class="space-y-3">
                @forelse($komisi as $k)
                    <div class="bg-white rounded-2xl border border-ink-100 p-4">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-up-accent font-bold text-sm">{{ $k->no_komisi }}</span>
                            <x-prism.status-pill :status="$k->status" />
                        </div>
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-xs text-ink-500">{{ $k->keterangan }}</span>
                            <span class="font-black text-up-accent tabular-nums">Rp {{ number_format($k->nominal_komisi, 0, ',', '.') }}</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-14 bg-white rounded-2xl border border-ink-100">
                        <p class="font-semibold text-ink-700">Belum ada komisi</p>
                        <p class="text-xs text-ink-400 mt-1">Komisi otomatis terhitung dari transaksi atas nama Anda.</p>
                    </div>
                @endforelse
            </div>
        @endif
    @endif
</div>