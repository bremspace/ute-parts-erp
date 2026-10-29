<div class="p-4 sm:p-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white">Approval Inbox</h1>
            <p class="text-gray-600 dark:text-gray-400 text-xs sm:text-sm mt-0.5">Permintaan persetujuan yang perlu diproses</p>
        </div>
        <div class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">
            Total: <span class="font-semibold">{{ $requests->total() }}</span> permintaan
        </div>
    </div>

    <div class="mb-4">
        <div class="relative">
            <input wire:model.live.debounce.300ms="search" 
                   type="text" 
                   placeholder="Cari entity, jumlah, nomor PO..." 
                   class="w-full px-4 py-2.5 pl-10 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm min-h-[44px]">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    @if($requests->isEmpty())
        <div class="text-center py-12">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-800 mb-4">
                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Tidak ada permintaan approval</h3>
            <p class="text-gray-500 dark:text-gray-400 mt-1">Semua permintaan telah diproses</p>
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <x-prism.dual-scroll>
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Entity</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Jumlah</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Diajukan Oleh</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Waktu</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($requests as $request)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                                                <span class="text-blue-600 dark:text-blue-400 font-semibold">
                                                    {{ substr($request->entity_type, 0, 1) }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                @if($request->entity_type === 'member')
                                                    Member: {{ $request->payload_json['nama'] ?? ('#' . $request->entity_id) }}
                                                @elseif($request->entity_type === 'reset_password_pelanggan')
                                                    Reset Password: {{ $request->payload_json['nama'] ?? ('Pelanggan #' . $request->entity_id) }}
                                                @else
                                                    {{ $request->entity_type }} #{{ $request->entity_id }}
                                                @endif
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                @if($request->entity_type === 'member')
                                                    HP: {{ $request->payload_json['telepon'] ?? '-' }} • Rule: {{ $request->rule?->approver_role }} (Lv{{ $request->rule?->level }})
                                                @elseif($request->entity_type === 'reset_password_pelanggan')
                                                    HP: {{ $request->payload_json['telepon'] ?? '-' }} • CS/Staf: {{ $request->requestedBy?->name ?? '-' }}
                                                @else
                                                    Rule: {{ $request->rule?->approver_role }} (Lv{{ $request->rule?->level }})
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-mono text-gray-900 dark:text-white">
                                        Rp {{ number_format($request->payload_json['amount'] ?? 0, 0, ',', '.') }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-white">
                                        {{ $request->requestedBy->name ?? '-' }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $request->requestedBy->email ?? '-' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-xs text-ink-300 dark:text-ink-300">
                                        {{ $request->created_at?->format('d/m/Y H:i') ?? '-' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        // Status PRD F1-1: pending / disetujui / ditolak (+ alias lama approved/rejected)
                                        $statusColors = [
                                            'pending' => 'bg-up-amber/15 text-up-amber border border-up-amber/30',
                                            'disetujui' => 'bg-up-mint/15 text-up-mint border border-up-mint/30',
                                            'ditolak' => 'bg-up-red/15 text-up-red border border-up-red/30',
                                            'approved' => 'bg-up-mint/15 text-up-mint border border-up-mint/30',
                                            'rejected' => 'bg-up-red/15 text-up-red border border-up-red/30',
                                        ];
                                        $statusLabels = [
                                            'pending' => 'Menunggu',
                                            'disetujui' => 'Disetujui',
                                            'ditolak' => 'Ditolak',
                                            'approved' => 'Disetujui',
                                            'rejected' => 'Ditolak',
                                        ];
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $statusColors[$request->status] ?? 'bg-white/10 text-ink-300' }}">
                                        {{ $statusLabels[$request->status] ?? ucfirst($request->status) }}
                                    </span>
                                    @if($request->catatan)
                                        <p class="text-[11px] text-ink-400 mt-1 max-w-[200px] truncate" title="{{ $request->catatan }}">
                                            💬 {{ $request->catatan }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    @if($request->status === 'pending')
                                        <div class="flex items-center space-x-2">
                                            @if($request->entity_type === 'member')
                                                <button wire:click="openApproveMemberModal({{ $request->id }})" 
                                                        class="px-3.5 py-1.5 bg-up-mint hover:bg-up-mint/90 text-ink-950 text-xs font-bold rounded-xl transition active:scale-[0.97] min-h-[36px] cursor-pointer flex items-center gap-1.5"
                                                        :disabled="$processing">
                                                    <span>✓</span>
                                                    <span>Setujui & Kategori</span>
                                                </button>
                                            @elseif($request->entity_type === 'reset_password_pelanggan')
                                                <button wire:click="openApproveResetPasswordModal({{ $request->id }})" 
                                                        class="px-3.5 py-1.5 bg-up-primary hover:bg-up-primary-dark text-white text-xs font-bold rounded-xl transition active:scale-[0.97] min-h-[36px] cursor-pointer flex items-center gap-1.5"
                                                        :disabled="$processing">
                                                    <span>🔑</span>
                                                    <span>Setujui Reset</span>
                                                </button>
                                            @elseif($request->entity_type === 'grn')
                                                <button wire:click="openApproveGrnModal({{ $request->id }})" 
                                                        class="px-3.5 py-1.5 bg-up-mint hover:bg-up-mint/90 text-ink-950 text-xs font-bold rounded-xl transition active:scale-[0.97] min-h-[36px] cursor-pointer flex items-center gap-1.5"
                                                        :disabled="$processing">
                                                    <span>📦</span>
                                                    <span>Setujui GRN</span>
                                                </button>
                                            @else
                                                <button wire:click="approveRequest({{ $request->id }})" 
                                                        class="px-3.5 py-1.5 bg-up-mint hover:bg-up-mint/90 text-ink-950 text-xs font-bold rounded-xl transition active:scale-[0.97] min-h-[36px] cursor-pointer"
                                                        :disabled="$processing">
                                                    Setujui
                                                </button>
                                            @endif
                                            <button wire:click="openRejectModal({{ $request->id }})"
                                                    class="px-3.5 py-1.5 bg-up-red/15 hover:bg-up-red/25 border border-up-red/30 text-up-red text-xs font-bold rounded-xl transition active:scale-[0.97] min-h-[36px] cursor-pointer"
                                                    :disabled="$processing">
                                                Tolak
                                            </button>
                                        </div>
                                    @else
                                        <div class="text-xs text-ink-400">
                                            <span>{{ $request->actioned_at?->format('d/m/Y H:i') ?? '-' }}</span>
                                            @if($request->actionedBy)
                                                <span class="block text-[10px] text-ink-500">oleh {{ $request->actionedBy->name }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-prism.dual-scroll>
        </div>

        <div class="mt-4">
            {{ $requests->links() }}
        </div>
    @endif

    <!-- Reject Modal -->
    @if($showRejectModal)
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4">
            <div class="relative mx-auto p-5 border border-white/10 w-full max-w-md shadow-2xl rounded-2xl bg-white dark:bg-gray-800">
                <div class="text-center">
                    <h3 class="text-lg leading-6 font-bold text-gray-900 dark:text-white">Konfirmasi Penolakan</h3>
                    <div class="mt-2 px-2 py-3 text-left">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                            Berikan alasan penolakan (wajib) agar kasir/pemohon mengetahui tindak lanjut yang perlu dilakukan:
                        </p>
                        <textarea wire:model="catatan" 
                                  class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500 focus:border-transparent text-sm"
                                  rows="3"
                                  placeholder="Contoh: Tolong hitung ulang uang fisik di laci kasir..."></textarea>
                        @error('catatan')
                            <p class="mt-1 text-xs text-red-500 font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-200 dark:border-gray-700">
                        <button wire:click="closeRejectModal"
                                type="button"
                                class="px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 text-xs font-semibold rounded-xl cursor-pointer">
                            Batal
                        </button>
                        <button wire:click="rejectRequest"
                                type="button"
                                :disabled="$processing"
                                class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-xl shadow-sm cursor-pointer">
                            Tolak Permintaan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Approve Member Modal -->
    @if($showApproveMemberModal)
        <div class="fixed inset-0 bg-black/75 backdrop-blur-sm overflow-y-auto h-full w-full z-50 flex items-center justify-center p-3 sm:p-4">
            <div class="relative mx-auto p-5 sm:p-6 border border-gray-200 dark:border-white/10 w-full max-w-lg shadow-2xl rounded-2xl sm:rounded-3xl bg-white dark:bg-gray-800 text-left max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-gray-200 dark:border-white/10 flex-shrink-0">
                    <div>
                        <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span>👤</span>
                            <span>Persetujuan Pendaftaran Member</span>
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Tetapkan kategori dan tier untuk menentukan benefit & hak istimewa harga
                        </p>
                    </div>
                    <button wire:click="closeApproveMemberModal" 
                            type="button" 
                            class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 dark:bg-white/5 dark:hover:bg-white/10 text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white flex items-center justify-center transition cursor-pointer text-sm">
                        ✕
                    </button>
                </div>

                <div class="overflow-y-auto pr-1 flex-1 space-y-4">
                    <!-- Ringkasan Calon Member -->
                    <div class="p-3.5 rounded-xl bg-gray-50 dark:bg-white/[0.03] border border-gray-200 dark:border-white/5">
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <span class="text-gray-500 dark:text-gray-400 block text-[10px] uppercase font-semibold">Nama Lengkap</span>
                                <span class="font-bold text-gray-900 dark:text-white text-sm">{{ $memberApprovalForm['nama'] }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400 block text-[10px] uppercase font-semibold">No. HP / WhatsApp</span>
                                <span class="font-bold font-mono text-gray-900 dark:text-white text-sm">{{ $memberApprovalForm['telepon'] }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Kategori Member (Benefit & Privilege) -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">
                            Kategori & Hak Istimewa Member *
                        </label>
                        <div class="grid grid-cols-1 gap-2.5">
                            <!-- Retail -->
                            <label class="p-3 rounded-xl border cursor-pointer transition flex items-start gap-3 {{ $memberApprovalForm['tipe_konsumen'] === 'retail' ? 'border-up-primary bg-up-primary/10 dark:bg-up-primary/15' : 'border-gray-200 dark:border-white/5 hover:border-gray-300 dark:hover:border-white/10 bg-white dark:bg-white/[0.02]' }}">
                                <input type="radio" wire:model.live="memberApprovalForm.tipe_konsumen" value="retail" class="mt-0.5 accent-up-primary cursor-pointer" />
                                <div class="text-xs">
                                    <div class="font-bold text-gray-900 dark:text-white">Member Biasa (Retail / Konsumen)</div>
                                    <div class="text-gray-500 dark:text-gray-400 text-[11px] mt-0.5 leading-relaxed">
                                        Pelanggan ritel umum. Berhak atas akumulasi belanja 12 bulan, perolehan poin loyalitas, serta diskon belanja sesuai Tier Membership.
                                    </div>
                                </div>
                            </label>

                            <!-- Reseller -->
                            <label class="p-3 rounded-xl border cursor-pointer transition flex items-start gap-3 {{ $memberApprovalForm['tipe_konsumen'] === 'reseller' ? 'border-up-accent bg-up-accent/10 dark:bg-up-accent/15' : 'border-gray-200 dark:border-white/5 hover:border-gray-300 dark:hover:border-white/10 bg-white dark:bg-white/[0.02]' }}">
                                <input type="radio" wire:model.live="memberApprovalForm.tipe_konsumen" value="reseller" class="mt-0.5 accent-up-accent cursor-pointer" />
                                <div class="text-xs">
                                    <div class="font-bold text-gray-900 dark:text-white flex items-center gap-1.5">
                                        <span>Mitra Reseller</span>
                                        <span class="text-[9px] font-bold text-up-accent bg-up-accent/10 border border-up-accent/30 px-1.5 py-0.5 rounded-full">HARGA RESELLER</span>
                                    </div>
                                    <div class="text-gray-500 dark:text-gray-400 text-[11px] mt-0.5 leading-relaxed">
                                        Mitra penjualan toko/bengkel rekanan. Otomatis mendapatkan tarif harga khusus Reseller pada katalog POS, web toko, dan suku cadang servis.
                                    </div>
                                </div>
                            </label>

                            <!-- Agen B2B -->
                            <label class="p-3 rounded-xl border cursor-pointer transition flex items-start gap-3 {{ $memberApprovalForm['tipe_konsumen'] === 'agen' ? 'border-amber-400 bg-amber-400/10 dark:bg-amber-400/15' : 'border-gray-200 dark:border-white/5 hover:border-gray-300 dark:hover:border-white/10 bg-white dark:bg-white/[0.02]' }}">
                                <input type="radio" wire:model.live="memberApprovalForm.tipe_konsumen" value="agen" class="mt-0.5 accent-amber-400 cursor-pointer" />
                                <div class="text-xs">
                                    <div class="font-bold text-gray-900 dark:text-white flex items-center gap-1.5">
                                        <span>Agen / Mitra B2B</span>
                                        <span class="text-[9px] font-bold text-amber-400 bg-amber-400/15 border border-amber-400/35 px-1.5 py-0.5 rounded-full">HARGA AGEN + DOWNLINE</span>
                                    </div>
                                    <div class="text-gray-500 dark:text-gray-400 text-[11px] mt-0.5 leading-relaxed">
                                        Tingkat kemitraan B2B tertinggi. Mendapatkan harga khusus Agen, kode agen unik untuk merekrut downline, serta hak komisi penjualan jaringan.
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Tier Membership -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                            Tier Membership (Level Diskon & Poin)
                        </label>
                        <select wire:model="memberApprovalForm.tier_membership_id" 
                                class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 text-xs font-medium">
                            <option value="">Tanpa Tier Khusus / Tier Dasar</option>
                            @foreach($tiers as $t)
                                <option value="{{ $t->id }}">
                                    {{ $t->nama }} (Diskon POS: {{ number_format($t->diskon_persen, 1) }}%, Poin: {{ number_format($t->poin_multiplier, 1) }}x)
                                </option>
                            @endforeach
                        </select>
                        <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-1">
                            Diskon tier diterapkan pada kasir POS sesuai strategi loyalitas.
                        </p>
                    </div>

                    <!-- Khusus Agen: Kode Unik & Referral Upline -->
                    @if($memberApprovalForm['tipe_konsumen'] === 'agen')
                        <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/20 space-y-3">
                            <div class="text-xs font-bold text-amber-400 flex items-center gap-1.5">
                                <span>🏢</span>
                                <span>Konfigurasi Keagenan</span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                        Kode Agen Unik (Opsional)
                                    </label>
                                    <input type="text" 
                                           wire:model="memberApprovalForm.kode_agen" 
                                           placeholder="Otomatis jika kosong" 
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-mono text-xs uppercase" />
                                    @error('memberApprovalForm.kode_agen') <span class="text-red-500 text-[10px] block mt-0.5">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                        Kode Referral Upline (Opsional)
                                    </label>
                                    <input type="text" 
                                           wire:model="memberApprovalForm.referral_kode" 
                                           placeholder="Misal: AGN-XXXXXX" 
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-mono text-xs uppercase" />
                                    @error('memberApprovalForm.referral_kode') <span class="text-red-500 text-[10px] block mt-0.5">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Catatan Approval -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                            Catatan Persetujuan (Opsional)
                        </label>
                        <textarea wire:model="memberApprovalForm.catatan" 
                                  rows="2" 
                                  placeholder="Contoh: Disetujui sebagai agen perwakilan area..."
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 text-xs"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-4 border-t border-gray-200 dark:border-white/10 mt-4 flex-shrink-0">
                    <button wire:click="closeApproveMemberModal"
                            type="button"
                            class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 text-xs font-semibold rounded-xl cursor-pointer">
                        Batal
                    </button>
                    <button wire:click="confirmApproveMember"
                            type="button"
                            :disabled="$processing"
                            class="px-5 py-2.5 bg-up-mint hover:bg-up-mint/90 text-ink-950 text-xs font-bold rounded-xl shadow-sm transition active:scale-[0.98] cursor-pointer flex items-center gap-1.5">
                        <span>✓</span>
                        <span>Setujui & Aktifkan Member</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Approve Reset Password Modal -->
    @if($showApproveResetPasswordModal)
        <div class="fixed inset-0 bg-black/75 backdrop-blur-sm overflow-y-auto h-full w-full z-50 flex items-center justify-center p-3 sm:p-4">
            <div class="relative mx-auto p-5 sm:p-6 border border-gray-200 dark:border-white/10 w-full max-w-lg shadow-2xl rounded-2xl sm:rounded-3xl bg-white dark:bg-gray-800 text-left max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-gray-200 dark:border-white/10 flex-shrink-0">
                    <div>
                        <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span>🔑</span>
                            <span>Persetujuan Reset Kata Sandi Pelanggan</span>
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Permohonan reset password via CS WhatsApp atau Toko
                        </p>
                    </div>
                    <button wire:click="closeApproveResetPasswordModal" 
                            type="button" 
                            class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 dark:bg-white/5 dark:hover:bg-white/10 text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white flex items-center justify-center transition cursor-pointer text-sm">
                        ✕
                    </button>
                </div>

                <div class="overflow-y-auto pr-1 flex-1 space-y-4">
                    <!-- Ringkasan Pelanggan -->
                    <div class="p-3.5 rounded-xl bg-gray-50 dark:bg-white/[0.03] border border-gray-200 dark:border-white/5 space-y-2">
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <span class="text-gray-500 dark:text-gray-400 block text-[10px] uppercase font-semibold">Nama Konsumen</span>
                                <span class="font-bold text-gray-900 dark:text-white text-sm">{{ $resetPasswordApprovalForm['nama'] }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400 block text-[10px] uppercase font-semibold">No. HP / WhatsApp</span>
                                <span class="font-bold font-mono text-gray-900 dark:text-white text-sm">{{ $resetPasswordApprovalForm['telepon'] }}</span>
                            </div>
                        </div>
                        @if(!empty($resetPasswordApprovalForm['username']) && $resetPasswordApprovalForm['username'] !== '-')
                            <div class="pt-2 border-t border-gray-200 dark:border-white/5 text-xs">
                                <span class="text-gray-500 dark:text-gray-400 text-[10px] uppercase font-semibold">Username:</span>
                                <span class="font-mono font-semibold text-up-primary">&#64;{{ $resetPasswordApprovalForm['username'] }}</span>
                            </div>
                        @endif
                        <div class="pt-2 border-t border-gray-200 dark:border-white/5 text-xs">
                            <span class="text-gray-500 dark:text-gray-400 block text-[10px] uppercase font-semibold">Alasan / Catatan Pengajuan</span>
                            <span class="text-gray-700 dark:text-gray-300 font-medium">{{ $resetPasswordApprovalForm['alasan'] }}</span>
                        </div>
                    </div>

                    <!-- Password Baru yang Ditetapkan -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                            Kata Sandi Baru untuk Konsumen *
                        </label>
                        <input type="text" 
                               wire:model="resetPasswordApprovalForm.new_password" 
                               placeholder="Minimal 6 karakter" 
                               class="w-full px-3.5 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-mono text-sm focus:ring-2 focus:ring-up-primary focus:outline-none" />
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                            Kata sandi ini akan dienkripsi dan disimpan permanen. Konsumen dapat langsung login menggunakan password ini.
                        </p>
                        @error('resetPasswordApprovalForm.new_password') <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <!-- Catatan Approval -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                            Catatan Persetujuan (Opsional)
                        </label>
                        <textarea wire:model="resetPasswordApprovalForm.catatan" 
                                  rows="2" 
                                  placeholder="Contoh: Terverifikasi via WhatsApp resmi atau KTP di toko..."
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 text-xs"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-4 border-t border-gray-200 dark:border-white/10 mt-4 flex-shrink-0">
                    <button wire:click="closeApproveResetPasswordModal"
                            type="button"
                            class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 text-xs font-semibold rounded-xl cursor-pointer">
                        Batal
                    </button>
                    <button wire:click="approveResetPasswordSubmit"
                            type="button"
                            :disabled="$processing"
                            class="px-5 py-2.5 bg-up-primary hover:bg-up-primary-dark text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-[0.98] cursor-pointer flex items-center gap-1.5">
                        <span>✓</span>
                        <span>Setujui & Perbarui Password</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Approve GRN Modal (PO Tunai & Penerimaan) -->
    @if($showApproveGrnModal)
        <div class="fixed inset-0 bg-black/75 backdrop-blur-sm overflow-y-auto h-full w-full z-50 flex items-center justify-center p-3 sm:p-4">
            <div class="relative mx-auto p-5 sm:p-6 border border-gray-200 dark:border-white/10 w-full max-w-lg shadow-2xl rounded-2xl sm:rounded-3xl bg-white dark:bg-gray-800 text-left max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-gray-200 dark:border-white/10 flex-shrink-0">
                    <div>
                        <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span>📦</span>
                            <span>Persetujuan Penerimaan GRN: {{ $grnApprovalForm['no_grn'] }}</span>
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Otorisasi penerimaan fisik &amp; pembayaran kas pengadaan barang
                        </p>
                    </div>
                    <button wire:click="closeApproveGrnModal" 
                            type="button" 
                            class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 dark:bg-white/5 dark:hover:bg-white/10 text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white flex items-center justify-center transition cursor-pointer text-sm">
                        ✕
                    </button>
                </div>

                <div class="overflow-y-auto pr-1 flex-1 space-y-4">
                    <!-- Ringkasan GRN & PO -->
                    <div class="p-3.5 rounded-xl bg-gray-50 dark:bg-white/[0.03] border border-gray-200 dark:border-white/5 space-y-2">
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <span class="text-gray-500 dark:text-gray-400 block text-[10px] uppercase font-semibold">Nomor PO</span>
                                <span class="font-bold font-mono text-gray-900 dark:text-white text-sm">{{ $grnApprovalForm['no_po'] }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400 block text-[10px] uppercase font-semibold">Supplier</span>
                                <span class="font-bold text-gray-900 dark:text-white text-sm">{{ $grnApprovalForm['supplier_nama'] }}</span>
                            </div>
                        </div>
                        <div class="pt-2 border-t border-gray-200 dark:border-white/5 flex items-center justify-between text-xs">
                            <span class="text-gray-500 dark:text-gray-400 text-[10px] uppercase font-semibold">Total Pembayaran / HPP:</span>
                            <span class="font-mono font-bold text-up-mint text-base">Rp {{ number_format($grnApprovalForm['amount'], 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <!-- Metode / Sumber Kas (Hanya Kas yang Cukup) -->
                    @if($grnApprovalForm['is_tunai'])
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                                    Arahkan Sumber Kas / Bank (Saldo Cukup) *
                                </label>
                                <span class="text-[10px] text-gray-500 font-mono">Dibutuhkan: Rp {{ number_format($grnApprovalForm['amount'], 0, ',', '.') }}</span>
                            </div>
                            <select wire:model.live="grnApprovalForm.akun_kas_bank" class="w-full px-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-xs font-medium min-h-[44px]">
                                @forelse($kasCukupList ?? [] as $ak)
                                    <option value="{{ $ak->kode }}" class="bg-gray-900 text-white">
                                        [{{ $ak->kode }}] {{ $ak->nama }} ({{ ucfirst($ak->kelompok) }}) — Saldo: Rp {{ number_format($ak->saldo_aktual ?? 0, 0, ',', '.') }}
                                    </option>
                                @empty
                                    <option value="" disabled selected class="bg-gray-900 text-gray-400">
                                        ⚠️ Tidak ada kas/bank dengan saldo cukup
                                    </option>
                                @endforelse
                            </select>
                            <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-1.5 leading-relaxed">
                                💡 Jika diarahkan ke <strong>Kas Laci (110-04)</strong>, uang kasir otomatis tercatat keluar di mutasi kas laci sehingga closing shift kasir tidak selisih dan audit trail tercatat jelas.
                            </p>
                        </div>
                    @endif

                    <!-- Catatan Approval -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                            Catatan Persetujuan (Opsional)
                        </label>
                        <textarea wire:model="grnApprovalForm.catatan" 
                                  rows="2" 
                                  placeholder="Contoh: Disetujui dikeluarkan dari kas laci kasir cabang..." 
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-xs"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-4 mt-4 border-t border-gray-200 dark:border-white/10 flex-shrink-0">
                    <button wire:click="closeApproveGrnModal"
                            type="button"
                            class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 dark:bg-white/5 dark:hover:bg-white/10 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded-xl transition cursor-pointer">
                        Batal
                    </button>
                    <button wire:click="submitApproveGrn"
                            type="button"
                            :disabled="$processing"
                            class="px-5 py-2.5 bg-up-mint hover:bg-up-mint/90 text-ink-950 text-xs font-bold rounded-xl shadow-sm transition active:scale-[0.98] cursor-pointer flex items-center gap-1.5">
                        <span>✓</span>
                        <span>Setujui &amp; Eksekusi GRN</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>