{{-- [F1-4] Modal Riwayat Aktivitas — include dari komponen Livewire pemilik
     (wajib punya trait PunyaRiwayatAktivitas: $showRiwayatModal, $riwayatTipe, $riwayatId). --}}
@if(! empty($showRiwayatModal) && ! empty($riwayatId))
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
        <div class="w-full max-w-3xl glass-panel p-6 rounded-3xl relative max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-4 mb-4 border-b border-white/10">
                <h3 class="text-lg font-bold text-white">Riwayat Aktivitas</h3>
                <button wire:click="tutupRiwayat" class="text-ink-400 hover:text-white" aria-label="Tutup riwayat">✕</button>
            </div>

            @livewire(
                \App\Modules\Rbac\Livewire\RiwayatAktivitas::class,
                ['tipe' => $riwayatTipe, 'entityId' => (int) $riwayatId],
                key('riwayat-aktivitas-'.$riwayatTipe.'-'.$riwayatId)
            )
        </div>
    </div>
@endif
