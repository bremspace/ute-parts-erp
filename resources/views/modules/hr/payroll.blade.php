<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-up-primary">HR & Payroll</h1>
            <p class="mt-1 text-sm text-ink-500">Kelola periode payroll dan slip karyawan</p>
        </div>
        <div class="mt-3 sm:mt-0 flex gap-2">
            <button wire:click="buatPeriode"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-up-primary hover:bg-up-primary/80 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-up-primary">
                + Buat Periode
            </button>
        </div>
    </div>

    {{-- Periode Selector --}}
    <div class="glass-card p-4 mb-6">
        <div class="flex flex-col sm:flex-row gap-4 items-end">
            <div class="flex-1">
                <label class="block text-sm font-medium text-ink-700 mb-1">Periode</label>
                <select wire:model="periode"
                    class="block w-full rounded-md border-ink-300 shadow-sm focus:border-up-primary focus:ring-up-primary sm:text-sm">
                    <option value="">-- Pilih Periode --</option>
                    @foreach($periodeList as $id => $label)
                        <option value="{{ $label }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-ink-700 mb-1">Status</label>
                <select wire:model="statusFilter"
                    class="block w-full rounded-md border-ink-300 shadow-sm focus:border-up-primary focus:ring-up-primary sm:text-sm">
                    <option value="semua">Semua</option>
                    <option value="draft">Draft</option>
                    <option value="disetujui">Disetujui</option>
                    <option value="dibayar">Dibayar</option>
                </select>
            </div>
            <button wire:click="hitungDraft"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                💵 Hitung Draft
            </button>
            <button wire:click="ajukanApproval"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                📋 Ajukan Approval
            </button>
            <button wire:click="bayarPayroll"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                💰 Bayar
            </button>
        </div>
    </div>

    {{-- Total --}}
    @if($totalGaji > 0)
    <div class="glass-card p-4 mb-6">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-ink-700">Total Payroll Periode {{ $periode }}</span>
            <span class="text-xl font-bold text-up-primary">Rp {{ number_format($totalGaji, 0, ',', '.') }}</span>
        </div>
    </div>
    @endif

    {{-- Slip Table --}}
    <div class="glass-card overflow-hidden">
        <table class="min-w-full divide-y divide-ink-200">
            <thead class="bg-ink-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-ink-500 uppercase tracking-wider">Karyawan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-ink-500 uppercase tracking-wider">Jabatan</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-ink-500 uppercase tracking-wider">Gaji Pokok</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-ink-500 uppercase tracking-wider">Tunjangan</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-ink-500 uppercase tracking-wider">Potongan</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-ink-500 uppercase tracking-wider">Komisi</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-ink-500 uppercase tracking-wider">Total</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-ink-500 uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-ink-200">
                @forelse($slips as $slip)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-ink-900">{{ $slip['karyawan_nama'] }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-ink-500">{{ $slip['karyawan_jabatan'] }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-ink-900">Rp {{ number_format($slip['gaji_pokok'], 0, ',', '.') }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-ink-900">Rp {{ number_format($slip['total_tunjangan'], 0, ',', '.') }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-ink-900">Rp {{ number_format($slip['total_potongan'], 0, ',', '.') }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-ink-900">Rp {{ number_format($slip['total_komisi'], 0, ',', '.') }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-ink-900">Rp {{ number_format($slip['total_gaji'], 0, ',', '.') }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            @if($slip['status'] === 'draft') bg-yellow-100 text-yellow-800
                            @elseif($slip['status'] === 'disetujui') bg-green-100 text-green-800
                            @elseif($slip['status'] === 'dibayar') bg-blue-100 text-blue-800
                            @else bg-gray-100 text-gray-800 @endif">
                            {{ $slip['status'] }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-sm text-ink-500">
                        Tidak ada data slip. Buat periode dan hitung draft terlebih dahulu.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
