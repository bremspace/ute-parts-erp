@props(['slip' => null, 'rincian' => []])

<div class="glass-card p-6 space-y-4 max-w-xl mx-auto">
    <div class="flex items-center justify-between border-b border-ink-100 dark:border-white/10 pb-3">
        <div>
            <h2 class="text-lg font-bold text-up-primary">Slip Gaji Karyawan</h2>
            <p class="text-xs text-ink-500">Periode: {{ $slip?->periode?->periode ?? '-' }}</p>
        </div>
        <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800">
            {{ ucfirst($slip?->status ?? 'Draft') }}
        </span>
    </div>

    @if($slip)
        <div class="grid grid-cols-2 gap-3 text-xs bg-black/5 dark:bg-white/5 p-3 rounded-xl">
            <div>
                <span class="text-ink-500">Nama:</span>
                <p class="font-semibold text-ink-900 dark:text-white">{{ $slip->karyawan?->nama ?? '-' }}</p>
            </div>
            <div>
                <span class="text-ink-500">NIK:</span>
                <p class="font-semibold text-ink-900 dark:text-white">{{ $slip->karyawan?->nik ?? '-' }}</p>
            </div>
            <div>
                <span class="text-ink-500">Jabatan &amp; Cabang:</span>
                <p class="font-semibold text-ink-900 dark:text-white capitalize">{{ $slip->karyawan?->jabatan ?? '-' }} ({{ $slip->karyawan?->cabang?->nama ?? 'Pusat' }})</p>
            </div>
            <div>
                <span class="text-ink-500">Rekening:</span>
                <p class="font-semibold text-ink-900 dark:text-white">{{ $slip->karyawan?->rekening_bank ?? '-' }}</p>
            </div>
        </div>

        <div class="space-y-2 text-xs">
            <div class="flex justify-between py-1 border-b border-ink-100 dark:border-white/5">
                <span class="text-ink-600 dark:text-ink-400">Gaji Pokok:</span>
                <span class="font-semibold tabular-nums text-ink-900 dark:text-white">Rp {{ number_format($slip->gaji_pokok, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-ink-100 dark:border-white/5">
                <span class="text-emerald-600">Tunjangan &amp; Insentif:</span>
                <span class="font-semibold tabular-nums text-emerald-600">+Rp {{ number_format($slip->total_tunjangan, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-ink-100 dark:border-white/5">
                <span class="text-amber-600">Komisi:</span>
                <span class="font-semibold tabular-nums text-amber-600">+Rp {{ number_format($slip->total_komisi, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-ink-100 dark:border-white/5">
                <span class="text-red-600">Potongan (Absen / Kasbon):</span>
                <span class="font-semibold tabular-nums text-red-600">-Rp {{ number_format($slip->total_potongan, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between py-2 bg-up-primary/10 p-3 rounded-xl text-sm font-bold text-up-primary">
                <span>Total Net Pay:</span>
                <span class="tabular-nums">Rp {{ number_format($slip->total_gaji, 0, ',', '.') }}</span>
            </div>
        </div>
    @endif
</div>
