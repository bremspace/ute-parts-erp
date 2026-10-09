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
                                                @else
                                                    {{ $request->entity_type }} #{{ $request->entity_id }}
                                                @endif
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                @if($request->entity_type === 'member')
                                                    HP: {{ $request->payload_json['telepon'] ?? '-' }} • Rule: {{ $request->rule?->approver_role }} (Lv{{ $request->rule?->level }})
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
                                            <button wire:click="approveRequest({{ $request->id }})" 
                                                    class="px-3.5 py-1.5 bg-up-mint hover:bg-up-mint/90 text-ink-950 text-xs font-bold rounded-xl transition active:scale-[0.97] min-h-[36px] cursor-pointer"
                                                    :disabled="$processing">
                                                Setujui
                                            </button>
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
</div>