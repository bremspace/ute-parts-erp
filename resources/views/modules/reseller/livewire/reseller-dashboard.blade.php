<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-4 border-b border-white/5 pb-4">
        <div class="flex items-center gap-2 overflow-x-auto scrollbar-none py-1 -my-1 flex-nowrap">
            <button wire:click="$set('activeTab', 'reseller')" class="px-4 py-2.5 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer flex items-center gap-2 {{ $activeTab === 'reseller' ? 'bg-up-accent text-white shadow-md shadow-up-accent/25' : 'bg-white/5 text-ink-300 hover:bg-white/10' }}">
                <span>🤝</span>
                <span>Mitra Reseller & Agen</span>
                <span class="tabular-nums px-2 py-0.5 rounded-full text-[10px] {{ $activeTab === 'reseller' ? 'bg-white/20 text-white' : 'bg-white/10 text-ink-300' }}">
                    {{ $statsMitra['total'] ?? count($resellers) }}
                </span>
            </button>

            <button wire:click="$set('activeTab', 'komisi')" class="px-4 py-2.5 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer flex items-center gap-2 {{ $activeTab === 'komisi' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/5 text-ink-300 hover:bg-white/10' }}">
                <span>💰</span>
                <span>Approval Komisi</span>
                <span class="tabular-nums px-2 py-0.5 rounded-full text-[10px] {{ $activeTab === 'komisi' ? 'bg-white/20 text-white' : 'bg-white/10 text-ink-300' }}">
                    {{ $komisiList->where('status', 'pending')->count() }}
                </span>
            </button>

            <button wire:click="$set('activeTab', 'skema')" class="px-4 py-2.5 rounded-xl text-xs font-bold transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer flex items-center gap-2 {{ $activeTab === 'skema' ? 'bg-up-primary text-white shadow-md shadow-up-primary/25' : 'bg-white/5 text-ink-300 hover:bg-white/10' }}">
                <span>⚙️</span>
                <span>Skema Komisi</span>
            </button>
        </div>

        <div class="flex items-center gap-2">
            <a href="/app/crm" class="px-3.5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-200 hover:text-white text-xs font-semibold flex items-center gap-2 transition active:scale-95 whitespace-nowrap min-h-[44px]" title="Kelola data pelanggan, tier, dan penambahan mitra baru">
                <span>👥</span>
                <span>Kelola di CRM</span>
                <span class="text-[10px] text-ink-500">&rarr;</span>
            </a>

            @if($activeTab === 'komisi')
                <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap overflow-x-auto scrollbar-none py-1 -my-1 w-full xl:w-auto justify-start xl:justify-end">
                    @can('laporan.cabang')
                        <button type="button" wire:click="exportLaporan('xlsx')" class="px-3.5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-200 font-bold text-xs whitespace-nowrap cursor-pointer transition-[transform,background-color] active:scale-[0.97] min-h-[44px] flex items-center">Export Excel</button>
                        <button type="button" wire:click="exportLaporan('csv')" class="px-3.5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-ink-200 font-bold text-xs whitespace-nowrap cursor-pointer transition-[transform,background-color] active:scale-[0.97] min-h-[44px] flex items-center">Export CSV</button>
                    @endcan
                    <span class="px-3 py-2 rounded-xl bg-white/[0.03] border border-white/5 text-xs text-ink-400 font-medium whitespace-nowrap min-h-[44px] flex items-center">
                        {{ count($selectedKomisiIds) }} terpilih
                    </span>
                    <button wire:click="prosesApproval('reject')" class="px-3.5 py-2.5 rounded-xl bg-up-red/15 hover:bg-up-red/25 text-up-red font-bold text-xs border border-up-red/30 transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer flex items-center">Tolak Terpilih</button>
                    <button wire:click="prosesApproval('approve')" class="px-3.5 py-2.5 rounded-xl bg-up-mint hover:opacity-90 text-ink-950 font-bold text-xs shadow-md shadow-up-mint/20 transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap cursor-pointer flex items-center">Setujui Terpilih → Jurnal & Utang</button>
                </div>
            @elseif($activeTab === 'skema')
                <div class="flex items-center gap-2 overflow-x-auto scrollbar-none py-1 -my-1 flex-nowrap">
                    <button wire:click="openSkemaModal" class="px-4 py-2.5 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs cursor-pointer transition-[transform,background-color] active:scale-[0.97] min-h-[44px] whitespace-nowrap flex items-center">+ Tambah Skema</button>
                </div>
            @endif
        </div>
    </div>

    <!-- TAB: Reseller & Agen Dashboard -->
    @if($activeTab === 'reseller')
        <!-- Ringkasan Statistik Mitra -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/5 flex items-center justify-between">
                <div>
                    <p class="text-[11px] text-ink-400 uppercase font-semibold">Total Mitra Terdaftar</p>
                    <p class="text-xl font-bold text-white tabular-nums mt-0.5">{{ number_format($statsMitra['total'] ?? 0) }}</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-lg">🤝</div>
            </div>

            <div class="p-4 rounded-2xl bg-gradient-to-br from-amber-500/10 via-amber-500/5 to-transparent border border-amber-500/20 flex items-center justify-between">
                <div>
                    <p class="text-[11px] text-amber-400 uppercase font-semibold">Mitra Agen B2B / Grosir</p>
                    <p class="text-xl font-bold text-amber-300 tabular-nums mt-0.5">{{ number_format($statsMitra['agen'] ?? 0) }}</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-amber-400/15 flex items-center justify-center text-lg">🏢</div>
            </div>

            <div class="p-4 rounded-2xl bg-up-accent/10 border border-up-accent/25 flex items-center justify-between">
                <div>
                    <p class="text-[11px] text-up-accent uppercase font-semibold">Mitra Reseller Retail</p>
                    <p class="text-xl font-bold text-white tabular-nums mt-0.5">{{ number_format($statsMitra['reseller'] ?? 0) }}</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-up-accent/20 flex items-center justify-center text-lg">🏷️</div>
            </div>
        </div>

        <!-- Toolbar Pencarian & Filter Mitra -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 pt-1">
            <div class="flex-1 max-w-md">
                <x-prism.barcode-scan-input placeholder="Cari nama mitra, telepon, atau kode agen..." model="search" />
            </div>

            <div class="flex items-center gap-2">
                <select wire:model.live="filterMitra" class="px-3.5 py-2.5 rounded-xl glass-input text-xs font-medium min-h-[44px]">
                    <option value="" class="bg-ink-900">Semua Tipe Mitra (Reseller & Agen)</option>
                    <option value="agen" class="bg-ink-900">Khusus Agen B2B</option>
                    <option value="reseller" class="bg-ink-900">Khusus Reseller</option>
                </select>
            </div>
        </div>

        <!-- Grid Kartu Mitra (Reseller & Agen) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            @forelse($resellers as $r)
                <div class="glass-panel glass-panel-hover rounded-2xl p-5 relative overflow-hidden flex flex-col justify-between space-y-4">
                    <!-- Accent bar di bagian atas kartu -->
                    <div class="absolute top-0 left-0 right-0 h-1 {{ $r['is_agen'] ? 'bg-gradient-to-r from-amber-500 via-amber-400 to-yellow-300' : 'bg-gradient-to-r from-up-accent to-orange-400' }}"></div>

                    <div>
                        <!-- Header Kartu -->
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div>
                                <h4 class="font-bold text-white text-base">{{ $r['nama'] }}</h4>
                                <p class="text-xs text-ink-400 font-mono mt-0.5">{{ $r['telepon'] ?? '-' }}</p>
                            </div>
                            <div>
                                @if($r['is_agen'])
                                    <span class="text-[10px] font-bold text-amber-300 bg-amber-400/15 border border-amber-400/35 px-2.5 py-1 rounded-full tracking-wide">
                                        AGEN B2B
                                    </span>
                                @else
                                    <x-prism.tier-badge :tier="$r['tier'] ?? 'Reseller'" />
                                @endif
                            </div>
                        </div>

                        <!-- Tag Identitas Khusus Agen & Referral -->
                        @if($r['is_agen'] && !empty($r['kode_agen']))
                            <div class="mt-2.5 p-2 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-between text-xs">
                                <span class="text-[11px] text-amber-400 font-medium">Kode Agen Unik:</span>
                                <span class="font-mono font-bold text-amber-300 tracking-wider bg-white/5 px-2 py-0.5 rounded border border-white/5">{{ $r['kode_agen'] }}</span>
                            </div>
                        @elseif(!empty($r['referral_kode']))
                            <div class="mt-2 text-[11px] text-ink-400">
                                Referral dari Agen: <span class="font-mono font-semibold text-ink-200">{{ $r['referral_kode'] }}</span>
                            </div>
                        @endif

                        <!-- Informasi Khusus Agen: Jaringan Downline -->
                        @if($r['is_agen'])
                            <div class="grid grid-cols-2 gap-2 mt-3 pt-3 border-t border-white/5 text-center">
                                <div class="p-2 rounded-xl bg-white/[0.02] border border-white/5">
                                    <p class="text-sm font-bold text-white tabular-nums">{{ number_format($r['downline_count']) }}</p>
                                    <p class="text-[9px] text-ink-400 uppercase font-semibold">Downline Binaan</p>
                                </div>
                                <div class="p-2 rounded-xl bg-white/[0.02] border border-white/5">
                                    <p class="text-sm font-bold text-up-mint tabular-nums">Rp {{ number_format($r['downline_omzet'], 0, ',', '.') }}</p>
                                    <p class="text-[9px] text-ink-400 uppercase font-semibold">Omzet Downline</p>
                                </div>
                            </div>
                        @endif

                        <!-- Ringkasan Finansial: Omzet Pribadi, Komisi Pending & Terhutang -->
                        <div class="grid grid-cols-3 gap-2 text-center mt-3 pt-3 border-t border-white/5">
                            <div class="p-2.5 rounded-xl bg-white/[0.02] border border-white/5">
                                <p class="text-sm font-bold text-white tabular-nums">Rp {{ number_format($r['total_belanja'], 0, ',', '.') }}</p>
                                <p class="text-[10px] text-ink-400 uppercase font-semibold">Omzet Belanja</p>
                            </div>
                            <div class="p-2.5 rounded-xl bg-up-amber/5 border border-up-amber/20">
                                <p class="text-sm font-bold text-up-amber tabular-nums">Rp {{ number_format($r['komisi_pending'], 0, ',', '.') }}</p>
                                <p class="text-[10px] text-ink-400 uppercase font-semibold">Pending</p>
                            </div>
                            <div class="p-2.5 rounded-xl bg-up-mint/5 border border-up-mint/20">
                                <p class="text-sm font-bold text-up-mint tabular-nums">Rp {{ number_format($r['komisi_terhutang'], 0, ',', '.') }}</p>
                                <p class="text-[10px] text-ink-400 uppercase font-semibold">Siap Cair</p>
                            </div>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-white/5 flex items-center justify-between">
                        <span class="text-[11px] text-ink-400">ID Mitra #{{ $r['id'] }}</span>
                        <a href="/app/crm?search={{ urlencode($r['nama']) }}" class="text-xs text-up-primary hover:text-white font-semibold transition">
                            Lihat Profil 360° &rarr;
                        </a>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-16 text-ink-400">
                    <svg class="w-10 h-10 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0" />
                    </svg>
                    Belum ada mitra reseller atau agen yang cocok dengan filter. Tambahkan atau tandai pelanggan sebagai agen/reseller di modul CRM.
                </div>
            @endforelse
        </div>
    @endif

    <!-- TAB: Komisi -->
    @if($activeTab === 'komisi')
        <div class="flex items-center gap-2 mb-4 flex-wrap">
            <select wire:model.live="filterStatus" class="px-3.5 py-2.5 rounded-xl glass-input text-xs font-medium w-full sm:w-48 min-h-[44px]">
                <option value="" class="bg-ink-900">Semua Status</option>
                <option value="pending" class="bg-ink-900">Pending</option>
                <option value="disetujui" class="bg-ink-900">Disetujui</option>
                <option value="ditolak" class="bg-ink-900">Ditolak</option>
            </select>
            <select wire:model.live="filterAktor" class="px-3.5 py-2.5 rounded-xl glass-input text-xs font-medium w-full sm:w-56 min-h-[44px]">
                <option value="" class="bg-ink-900">Semua Aktor Komisi</option>
                <option value="reseller" class="bg-ink-900">Reseller (Mitra)</option>
                <option value="agen" class="bg-ink-900">Agen B2B (Referral)</option>
                <option value="karyawan" class="bg-ink-900">Karyawan (Internal/Payroll)</option>
            </select>
        </div>

        <x-prism.data-table :headers="['', 'No. Komisi', 'Penerima Komisi', 'Tipe Aktor', 'Referensi', 'Jumlah Dasar', 'Komisi', 'Status', 'Tanggal']">
            @forelse($komisiList as $k)
                <tr class="hover:bg-white/[0.02] transition-colors">
                    <td class="py-3.5 px-4">
                        @if($k->status === 'pending')
                            <input type="checkbox" wire:change="toggleKomisi({{ $k->id }})" {{ in_array($k->id, $selectedKomisiIds, true) ? 'checked' : '' }} class="accent-up-mint w-4 h-4 cursor-pointer" />
                        @endif
                    </td>
                    <td class="py-3.5 px-4 font-mono font-bold text-white">{{ $k->no_komisi }}</td>
                    <td class="py-3.5 px-4 text-ink-200 font-medium">{{ $k->nama_penerima }}</td>
                    <td class="py-3.5 px-4">
                        @if($k->aktor_tipe === 'karyawan')
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-up-primary/10 text-up-primary">Karyawan / Payroll</span>
                        @elseif($k->aktor_tipe === 'agen')
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/15 text-amber-300 border border-amber-500/30">Agen B2B</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-up-accent/10 text-up-accent">Reseller Mitra</span>
                        @endif
                    </td>
                    <td class="py-3.5 px-4 text-ink-400 text-xs font-mono">{{ $k->referensi_doc }}</td>
                    <td class="py-3.5 px-4 tabular-nums text-ink-200 text-xs">Rp {{ number_format($k->jumlah_transaksi, 0, ',', '.') }}</td>
                    <td class="py-3.5 px-4 tabular-nums font-bold text-up-accent">Rp {{ number_format($k->nominal_komisi, 0, ',', '.') }}</td>
                    <td class="py-3.5 px-4"><x-prism.status-pill :status="$k->status" /></td>
                    <td class="py-3.5 px-4 text-ink-400 text-xs">{{ $k->created_at->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="py-12 text-center text-ink-400">
                        <svg class="w-10 h-10 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                        </svg>
                        Tidak ada data komisi.
                    </td>
                </tr>
            @endforelse

            <x-slot:mobileCards>
                @foreach($komisiList as $k)
                    <div class="p-3.5 rounded-xl bg-white/[0.03] border border-white/5 space-y-2">
                        <div class="flex items-center justify-between">
                            @if($k->status === 'pending')
                                <input type="checkbox" wire:change="toggleKomisi({{ $k->id }})" {{ in_array($k->id, $selectedKomisiIds, true) ? 'checked' : '' }} class="accent-up-mint w-4 h-4 cursor-pointer" />
                            @endif
                            <span class="font-mono font-bold text-white">{{ $k->no_komisi }}</span>
                            <x-prism.status-pill :status="$k->status" />
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-ink-300">{{ $k->nama_penerima }}</span>
                            <span class="font-bold text-up-accent tabular-nums">Rp {{ number_format($k->nominal_komisi, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between items-center text-[11px] pt-1 border-t border-white/5">
                            <span class="text-ink-400 font-mono">{{ $k->referensi_doc }}</span>
                            @if($k->aktor_tipe === 'karyawan')
                                <span class="text-[10px] text-up-primary">Karyawan</span>
                            @elseif($k->aktor_tipe === 'agen')
                                <span class="text-[10px] text-amber-300">Agen B2B</span>
                            @else
                                <span class="text-[10px] text-up-accent">Reseller</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </x-slot:mobileCards>
        </x-prism.data-table>
    @endif

    <!-- TAB: Skema Komisi -->
    @if($activeTab === 'skema')
        <x-prism.data-table :headers="['Nama Skema', 'Kategori Produk', 'Tipe Perhitungan', 'Nilai Komisi', 'Status']">
            @forelse($skemaList as $s)
                <tr class="hover:bg-white/[0.02] transition-colors">
                    <td class="py-3.5 px-4 font-bold text-white">{{ $s->nama }}</td>
                    <td class="py-3.5 px-4 text-ink-300 text-xs">{{ $s->kategori ?: 'Semua Kategori (Global)' }}</td>
                    <td class="py-3.5 px-4 text-xs capitalize">{{ $s->tipe }}</td>
                    <td class="py-3.5 px-4 tabular-nums font-bold text-up-mint">
                        {{ $s->tipe === 'persen' ? number_format($s->nilai, 1) . '%' : 'Rp ' . number_format($s->nilai, 0, ',', '.') }}
                    </td>
                    <td class="py-3.5 px-4">
                        @if($s->is_active)
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-up-mint/10 text-up-mint">Aktif</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-white/5 text-ink-400">Nonaktif</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="py-12 text-center text-ink-400">
                        Belum ada skema komisi global terdaftar.
                    </td>
                </tr>
            @endforelse
        </x-prism.data-table>
    @endif

    <!-- Modal Skema Baru -->
    @if($showSkemaModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="w-full max-w-md glass-panel p-6 rounded-3xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                    <h3 class="text-lg font-bold text-white">Tambah Skema Komisi</h3>
                    <button wire:click="$set('showSkemaModal', false)" class="text-ink-400 hover:text-white cursor-pointer">✕</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Skema *</label>
                        <input type="text" wire:model="skemaForm.nama" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="Komisi LCD 5%" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-ink-300 mb-1.5">Kategori Produk (opsional, kosongkan jika global)</label>
                        <input type="text" wire:model="skemaForm.kategori" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium" placeholder="LCD, Baterai, Flexible..." />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Tipe *</label>
                            <select wire:model="skemaForm.tipe" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-medium">
                                <option value="persen" class="bg-ink-900">Persentase (%)</option>
                                <option value="nominal" class="bg-ink-900">Nominal Tetap (Rp)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-300 mb-1.5">Nilai Komisi *</label>
                            <input type="text" inputmode="numeric" x-format-number wire:model="skemaForm.nilai" class="w-full px-3 py-2.5 rounded-xl glass-input text-xs font-bold tabular-nums" />
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-white/5 mt-5">
                    <button type="button" wire:click="$set('showSkemaModal', false)" class="flex-1 py-3 rounded-xl bg-white/5 text-ink-300 font-semibold text-xs cursor-pointer min-h-[44px]">Batal</button>
                    <button type="button" wire:click="simpanSkema" class="flex-1 py-3 rounded-xl bg-up-primary hover:bg-up-primary/90 text-white font-bold text-xs cursor-pointer min-h-[44px]">Simpan Skema</button>
                </div>
            </div>
        </div>
    @endif
</div>
