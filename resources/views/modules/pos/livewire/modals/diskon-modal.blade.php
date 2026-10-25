<!-- MODAL PENGATURAN DISKON & SKEMA REWARD TRANSAKSI -->
@if($showDiskonModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/80 backdrop-blur-md p-4 sm:p-6 overflow-y-auto"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="w-full max-w-lg bg-white dark:bg-[#0B1120] border border-ink-200/80 dark:border-white/10 p-5 sm:p-6 rounded-3xl shadow-2xl relative my-auto max-h-[92vh] flex flex-col"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95">
            <!-- Header Modal -->
            <div class="flex items-center justify-between pb-3.5 mb-3 border-b border-ink-200/70 dark:border-white/10 shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-up-primary/15 border border-up-primary/30 flex items-center justify-center text-up-primary">
                        <span class="text-base">🎁</span>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-ink-900 dark:text-white">Skema Diskon & Reward Transaksi</h3>
                        <p class="text-[11px] text-ink-500 dark:text-ink-400">Atur skema reward poin member, komisi mitra, atau diskon manual</p>
                    </div>
                </div>
                <button
                    type="button"
                    wire:click="closeDiskonModal"
                    class="p-2 rounded-xl text-ink-400 hover:text-ink-900 dark:hover:text-white hover:bg-ink-100 dark:hover:bg-white/5 transition-colors cursor-pointer"
                >✕</button>
            </div>

            <!-- Scrollable Content -->
            <div class="overflow-y-auto pr-1 space-y-4 flex-1">
                <!-- 1. SKEMA REWARD OTOMATIS: MEMBER (POIN LOYALTY) -->
                @if($customer)
                    @php
                        $ratePoin = app(\App\Modules\Crm\Services\PelangganService::class)->nilaiTukarPoinPelanggan($customer);
                        $saldoPoinKonsumen = (int) ($customer->poin_loyalty ?? 0);
                        $isMitra = $customer->is_reseller || in_array($customer->tipe_konsumen, ['reseller', 'agen'], true);
                    @endphp

                    <div class="p-4 rounded-2xl bg-ink-50/80 dark:bg-white/[0.03] border border-ink-200/80 dark:border-white/10 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-sm text-ink-900 dark:text-white">👤 {{ $customer->nama }}</span>
                                <span class="px-2 py-0.5 rounded-md bg-up-primary text-white text-[10px] font-black uppercase">
                                    {{ $customer->tierMembership?->nama ?? ($customer->tipe_konsumen === 'reseller' ? 'Reseller' : ($customer->tipe_konsumen === 'agen' ? 'Agen' : 'Member')) }}
                                </span>
                            </div>
                            <span class="text-xs text-ink-500">Saldo: <strong class="text-up-amber tabular-nums">{{ number_format($saldoPoinKonsumen) }} pts</strong></span>
                        </div>

                        <!-- Opsi Skema Poin Loyalty Member -->
                        <div class="pt-2 border-t border-ink-200/60 dark:border-white/5 space-y-2">
                            <label class="block text-[11px] font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider">
                                Skema Poin Loyalitas Member:
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    wire:click="batalTukarPoin"
                                    class="p-2.5 rounded-xl border text-left transition cursor-pointer flex flex-col gap-0.5 {{ $poinDitukar === 0 ? 'bg-up-primary/10 border-up-primary text-up-primary dark:text-white shadow-sm' : 'bg-white dark:bg-white/5 border-ink-200 dark:border-white/10 text-ink-600 dark:text-ink-400 hover:border-ink-300' }}"
                                >
                                    <span class="text-xs font-bold flex items-center gap-1.5">
                                        <span>🎁</span>
                                        <span>Simpan Poin</span>
                                    </span>
                                    <span class="text-[10px] opacity-80">Akumulasi saldo (tidak dipotong)</span>
                                </button>
                                <button
                                    type="button"
                                    wire:click="tukarSemuaPoin"
                                    class="p-2.5 rounded-xl border text-left transition cursor-pointer flex flex-col gap-0.5 {{ $poinDitukar > 0 ? 'bg-up-mint/15 border-up-mint text-up-mint dark:text-up-mint shadow-sm' : 'bg-white dark:bg-white/5 border-ink-200 dark:border-white/10 text-ink-600 dark:text-ink-400 hover:border-ink-300' }}"
                                >
                                    <span class="text-xs font-bold flex items-center gap-1.5">
                                        <span>🏷️</span>
                                        <span>Diskon dari Poin</span>
                                    </span>
                                    <span class="text-[10px] opacity-80">Tukar jadi potongan belanja</span>
                                </button>
                            </div>

                            @if($poinDitukar > 0)
                                <div class="mt-2 p-3 rounded-xl bg-up-mint/10 border border-up-mint/20 space-y-2">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-ink-700 dark:text-ink-300">Poin Ditukar:</span>
                                        <div class="flex items-center gap-2">
                                            <input
                                                type="number"
                                                wire:model.live.debounce.250ms="poinDitukar"
                                                min="0"
                                                max="{{ $saldoPoinKonsumen }}"
                                                class="w-20 px-2 py-1 rounded-lg glass-input text-xs font-bold tabular-nums text-right border-ink-200/80 dark:border-white/10"
                                            />
                                            <span class="text-ink-500 text-xs">pts</span>
                                        </div>
                                    </div>
                                    <div class="flex justify-between text-xs font-bold text-up-mint pt-1 border-t border-up-mint/20">
                                        <span>Nilai Diskon Poin:</span>
                                        <span class="tabular-nums">-Rp {{ number_format($diskonPoin, 0, ',', '.') }}</span>
                                    </div>
                                    <div class="flex justify-between items-center text-[10px] text-ink-400 pt-0.5">
                                        <span>Rate: 1 poin = Rp {{ number_format($ratePoin) }}</span>
                                        <button type="button" wire:click="batalTukarPoin" class="text-up-red hover:underline font-semibold cursor-pointer">Batal Tukar</button>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Opsi Skema Mitra (Reseller / Agen) -->
                        @if($isMitra)
                            <div class="pt-2.5 border-t border-ink-200/60 dark:border-white/5 space-y-2">
                                <label class="block text-[11px] font-bold text-ink-700 dark:text-ink-300 uppercase tracking-wider">
                                    Skema Reward Mitra ({{ strtoupper($customer->tipe_konsumen) }}):
                                </label>
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        type="button"
                                        wire:click="setModeRewardMitra('diskon_langsung')"
                                        class="p-2.5 rounded-xl border text-left transition cursor-pointer flex flex-col gap-0.5 {{ $modeRewardMitra === 'diskon_langsung' ? 'bg-up-mint/15 border-up-mint text-up-mint dark:text-up-mint shadow-sm' : 'bg-white dark:bg-white/5 border-ink-200 dark:border-white/10 text-ink-600 dark:text-ink-400 hover:border-ink-300' }}"
                                    >
                                        <span class="text-xs font-bold flex items-center gap-1.5">
                                            <span>🏷️</span>
                                            <span>Diskon Langsung</span>
                                        </span>
                                        <span class="text-[10px] opacity-80">Harga dipotong langsung di kasir</span>
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="setModeRewardMitra('komisi_akumulasi')"
                                        class="p-2.5 rounded-xl border text-left transition cursor-pointer flex flex-col gap-0.5 {{ $modeRewardMitra === 'komisi_akumulasi' ? 'bg-up-amber/15 border-up-amber text-up-amber dark:text-up-amber shadow-sm' : 'bg-white dark:bg-white/5 border-ink-200 dark:border-white/10 text-ink-600 dark:text-ink-400 hover:border-ink-300' }}"
                                    >
                                        <span class="text-xs font-bold flex items-center gap-1.5">
                                            <span>💰</span>
                                            <span>Simpan Komisi</span>
                                        </span>
                                        <span class="text-[10px] opacity-80">Harga retail, selisih jadi saldo komisi</span>
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                <!-- 2. DISKON MANUAL KASIR (OPSIONAL & DILINDUNGI OTORISASI OWNER/SUPERADMIN) -->
                <div class="p-4 rounded-2xl bg-ink-50/70 dark:bg-white/[0.02] border border-ink-200/80 dark:border-white/10 space-y-3" x-data="{ openManual: {{ ($diskonPersen > 0 || $diskonNominal > 0) ? 'true' : 'false' }} }">
                    <div class="flex items-center justify-between cursor-pointer" @click="openManual = !openManual">
                        <div class="flex items-center gap-2">
                            <span class="text-sm">🔒</span>
                            <div>
                                <span class="font-bold text-xs text-ink-900 dark:text-white">Diskon Manual Kasir (Khusus / Override)</span>
                                <p class="text-[10px] text-ink-400">Opsional — memerlukan otorisasi Owner atau Super Admin</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5">
                            @if($diskonPersen > 0 || $diskonNominal > 0)
                                <span class="px-2 py-0.5 rounded-md bg-up-amber/20 text-up-amber text-[10px] font-bold">
                                    Aktif: {{ $diskonPersen > 0 ? $diskonPersen.'%' : 'Rp '.number_format($diskonNominal) }}
                                </span>
                            @endif
                            <span class="text-xs text-ink-400" x-text="openManual ? '▲' : '▼'"></span>
                        </div>
                    </div>

                    <div x-show="openManual" x-collapse class="pt-3 border-t border-ink-200/60 dark:border-white/5 space-y-3">
                        <!-- Switch Tipe Diskon Manual -->
                        <div>
                            <div class="grid grid-cols-2 gap-2 p-1 bg-ink-100 dark:bg-white/5 rounded-xl border border-ink-200/80 dark:border-white/10 mb-2">
                                <button
                                    type="button"
                                    wire:click="$set('tipeDiskonPilihan', 'persen')"
                                    class="py-1.5 px-3 rounded-lg text-xs font-bold transition cursor-pointer flex items-center justify-center gap-1.5 {{ $tipeDiskonPilihan === 'persen' ? 'bg-white dark:bg-ink-900 text-up-primary shadow-sm' : 'text-ink-500 hover:text-ink-900 dark:hover:text-white' }}"
                                >
                                    <span>Persen (%)</span>
                                </button>
                                <button
                                    type="button"
                                    wire:click="$set('tipeDiskonPilihan', 'nominal')"
                                    class="py-1.5 px-3 rounded-lg text-xs font-bold transition cursor-pointer flex items-center justify-center gap-1.5 {{ $tipeDiskonPilihan === 'nominal' ? 'bg-white dark:bg-ink-900 text-up-primary shadow-sm' : 'text-ink-500 hover:text-ink-900 dark:hover:text-white' }}"
                                >
                                    <span>Nominal (Rp)</span>
                                </button>
                            </div>

                            @if($tipeDiskonPilihan === 'persen')
                                <div class="space-y-1.5">
                                    <div class="relative">
                                        <input
                                            type="number"
                                            wire:model.live.debounce.200ms="tempDiskonPersen"
                                            min="0"
                                            max="100"
                                            step="0.5"
                                            class="w-full px-3.5 py-2.5 rounded-xl glass-input text-lg font-black tabular-nums text-ink-900 dark:text-white border-ink-200/80 dark:border-white/15 focus:ring-2 focus:ring-up-amber/30 pr-9"
                                            placeholder="0"
                                        />
                                        <span class="absolute right-3.5 top-1/2 -translate-y-1/2 font-bold text-ink-400 text-sm">%</span>
                                    </div>
                                    <div class="grid grid-cols-5 gap-1.5">
                                        @foreach([3, 5, 10, 15, 20] as $presetP)
                                            <button
                                                type="button"
                                                wire:click="setPresetDiskonPersen({{ $presetP }})"
                                                class="py-1.5 px-1 rounded-lg bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-xs font-bold tabular-nums text-ink-800 dark:text-ink-200 border border-ink-200/80 dark:border-white/10 transition active:scale-95 cursor-pointer text-center"
                                            >
                                                {{ $presetP }}%
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <div class="space-y-1.5">
                                    <input
                                        type="text"
                                        inputmode="numeric"
                                        x-format-number
                                        wire:model.live.debounce.200ms="tempDiskonNominal"
                                        class="w-full px-3.5 py-2.5 rounded-xl glass-input text-lg font-black tabular-nums text-ink-900 dark:text-white border-ink-200/80 dark:border-white/15 focus:ring-2 focus:ring-up-amber/30"
                                        placeholder="0"
                                    />
                                    <div class="grid grid-cols-4 gap-1.5">
                                        @foreach([5000, 10000, 20000, 50000] as $presetN)
                                            <button
                                                type="button"
                                                wire:click="setPresetDiskonNominal({{ $presetN }})"
                                                class="py-1.5 px-1 rounded-lg bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-xs font-bold tabular-nums text-ink-800 dark:text-ink-200 border border-ink-200/80 dark:border-white/10 transition active:scale-95 cursor-pointer text-center"
                                            >
                                                {{ number_format($presetN / 1000) }}rb
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Form Input Alasan Diskon Manual -->
                        <div>
                            <label class="block text-[11px] font-semibold text-ink-600 dark:text-ink-400 mb-1">Alasan Diskon Manual (Opsional):</label>
                            <input
                                type="text"
                                wire:model="diskonManualAlasan"
                                placeholder="Contoh: Promo khusus toko, Clearance sale, dll"
                                class="w-full px-3 py-2 rounded-xl glass-input text-xs text-ink-900 dark:text-white border-ink-200/80 dark:border-white/15"
                            />
                        </div>

                        <!-- Verifikasi Otorisasi Super Admin / Owner -->
                        @php
                            $isOwnerSuperAdmin = $this->isUserOwnerOrSuperAdmin;
                        @endphp

                        @if($isOwnerSuperAdmin)
                            <div class="p-2.5 rounded-xl bg-up-mint/10 border border-up-mint/30 flex items-center gap-2 text-xs text-up-mint">
                                <span>🛡️</span>
                                <span>Terotentikasi: Anda login sebagai <strong>{{ auth()->user()->name }}</strong> (Owner/Super Admin). Diskon dapat langsung disetujui.</span>
                            </div>
                        @else
                            <div class="p-3 rounded-xl bg-up-amber/10 border border-up-amber/30 space-y-2">
                                <div class="flex items-center gap-1.5 text-xs font-bold text-up-amber">
                                    <span>🔐</span>
                                    <span>Otorisasi Owner / Super Admin Diperlukan</span>
                                </div>
                                <p class="text-[10px] text-ink-500 dark:text-ink-400">
                                    Kasir tidak diizinkan memberi diskon manual tanpa persetujuan Owner atau Super Admin.
                                </p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1">
                                    <div>
                                        <label class="block text-[10px] font-semibold text-ink-600 dark:text-ink-400 mb-0.5">Username / Email Owner</label>
                                        <input
                                            type="text"
                                            wire:model="diskonSupervisorAuth"
                                            autocomplete="username"
                                            placeholder="superadmin@uteparts.id"
                                            class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs text-ink-900 dark:text-white border-ink-200/80 dark:border-white/15"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-semibold text-ink-600 dark:text-ink-400 mb-0.5">Password Owner</label>
                                        <input
                                            type="password"
                                            wire:model="diskonSupervisorPassword"
                                            autocomplete="current-password"
                                            placeholder="••••••••"
                                            class="w-full px-2.5 py-1.5 rounded-lg glass-input text-xs text-ink-900 dark:text-white border-ink-200/80 dark:border-white/15"
                                        />
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Tombol Terapkan Diskon Manual -->
                        <div class="flex items-center justify-between pt-1">
                            @if($diskonPersen > 0 || $diskonNominal > 0)
                                <button
                                    type="button"
                                    wire:click="resetDiskonLangsung"
                                    class="text-xs text-up-red hover:underline font-semibold cursor-pointer"
                                >
                                    Hapus Diskon Manual
                                </button>
                            @else
                                <span></span>
                            @endif
                            <button
                                type="button"
                                wire:click="terapkanDiskonLangsung"
                                class="px-4 py-2 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs shadow-md shadow-up-primary/20 cursor-pointer transition active:scale-95 flex items-center gap-1.5"
                            >
                                <span>Setujui & Terapkan</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 3. LIVE SIMULASI ESTIMASI TOTAL -->
                @php
                    $potonganSimulasiManual = 0.0;
                    if ($tipeDiskonPilihan === 'persen') {
                        $p = max(0.0, min(100.0, (float) $tempDiskonPersen));
                        $potonganSimulasiManual = round(($subtotal * $p) / 100, 2);
                    } else {
                        $n = max(0.0, (float) preg_replace('/[^0-9]/', '', (string) $tempDiskonNominal));
                        $potonganSimulasiManual = min($subtotal, $n);
                    }
                    $estimasiTotalAkhir = max(0.0, $subtotal - $potonganSimulasiManual - $diskonPartai - $diskonPoin);
                @endphp

                <div class="p-3.5 rounded-2xl bg-up-primary/5 border border-up-primary/20 space-y-1.5 text-xs">
                    <div class="flex justify-between text-ink-600 dark:text-ink-400">
                        <span>Subtotal Keranjang:</span>
                        <span class="font-bold tabular-nums text-ink-900 dark:text-white">Rp {{ number_format($subtotal, 0, ',', '.') }}</span>
                    </div>
                    @if($diskonPartai > 0)
                        <div class="flex justify-between text-up-mint font-semibold">
                            <span>- Diskon Partai (Member):</span>
                            <span class="tabular-nums">-Rp {{ number_format($diskonPartai, 0, ',', '.') }}</span>
                        </div>
                    @endif
                    @if($diskonPoin > 0)
                        <div class="flex justify-between text-emerald-500 font-semibold">
                            <span>- Tukar Poin Member ({{ number_format($poinDitukar) }} pts):</span>
                            <span class="tabular-nums">-Rp {{ number_format($diskonPoin, 0, ',', '.') }}</span>
                        </div>
                    @endif
                    @if($potonganSimulasiManual > 0)
                        <div class="flex justify-between text-up-amber font-semibold">
                            <span>- Diskon Manual Kasir:</span>
                            <span class="tabular-nums">-Rp {{ number_format($potonganSimulasiManual, 0, ',', '.') }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between text-ink-900 dark:text-white font-black pt-1.5 border-t border-up-primary/15 text-sm">
                        <span>Estimasi Total Akhir:</span>
                        <span class="text-up-mint tabular-nums">Rp {{ number_format($estimasiTotalAkhir, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>

            <!-- Footer Modal Action -->
            <div class="flex items-center gap-2 justify-end pt-3 border-t border-ink-200/70 dark:border-white/10 shrink-0">
                <button
                    type="button"
                    wire:click="closeDiskonModal"
                    class="px-5 py-2.5 rounded-xl bg-ink-100 hover:bg-ink-200 dark:bg-white/5 dark:hover:bg-white/10 text-ink-700 dark:text-ink-300 font-semibold text-xs cursor-pointer min-h-[42px]"
                >
                    Tutup
                </button>
            </div>
        </div>
    </div>
@endif
