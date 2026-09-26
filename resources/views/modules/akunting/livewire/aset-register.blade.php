{{--
    [F3-3] Register Aset Tetap & Depresiasi — Ute Prism (backoffice, desktop-first, dark).

    Data & aksi yang dipakai HANYA yang ada di AsetRegister:
      - variabel view : $asetRows (Collection AsetTetap), $rekap, $kategoriOptions,
                        $filterStatusOptions, $periodeDepresiasi
      - aksi          : toggleForm, simpan, jalankanDepresiasi,
                        konfirmasiDisposal, eksekusiDisposal, batalDisposal
      - state         : $showForm, $form, $showDisposalConfirm, $disposalNama,
                        $disposalHarga, $disposalAkumulasi, $disposalSisaBuku, $filterStatus
    Angkafinancial: tabular-nums + format ribuan titik (ADR 0011) — number_format($v, 0, ',', '.').
--}}
@php
    $kategoriLabel = $kategoriOptions;
    $kategoriWarna = [
        'gedung' => 'text-up-mint bg-up-mint/10 border-up-mint/25',
        'kendaraan' => 'text-up-accent bg-up-accent/10 border-up-accent/25',
        'it' => 'text-up-primary bg-up-primary/10 border-up-primary/25',
        'peralatan' => 'text-up-amber bg-up-amber/10 border-up-amber/25',
        'furnitur' => 'text-up-primary bg-up-primary/10 border-up-primary/25',
        'lainnya' => 'text-ink-300 bg-white/5 border-white/10',
    ];
    $persenSusut = $rekap['harga'] > 0 ? min(100, round(($rekap['akumulasi'] / $rekap['harga']) * 100, 1)) : 0.0;
    $tanpaCabang = ! session('cabang_id');
@endphp

<div class="space-y-6">
    <!-- ═══ HEADER: judul, filter, aksi utama ═══ -->
    <div class="flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-4 border-b border-white/5 pb-4">
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-semibold text-white tracking-wide">Aset Tetap &amp; Depresiasi</h2>
                <span class="text-[10px] font-mono text-ink-500 bg-white/5 border border-white/10 px-1.5 py-0.5 rounded">F3-3</span>
            </div>
            <p class="text-xs text-ink-400 mt-0.5">
                Penyusutan garis lurus per bulan — jurnal beban <span class="font-mono">530-01</span> / akumulasi <span class="font-mono">130-02</span> diposting otomatis tiap awal bulan oleh job terjadwal.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <!-- Filter status (Livewire nyata — AsetRegister::$filterStatus) -->
            <div class="flex items-center gap-2">
                <label for="aset-filter-status" class="text-[11px] text-ink-400 font-medium whitespace-nowrap">Status</label>
                <select
                    id="aset-filter-status"
                    wire:model.live="filterStatus"
                    class="px-2.5 py-2 rounded-lg glass-input text-xs font-medium min-h-11"
                >
                    @foreach($filterStatusOptions as $value => $label)
                        <option value="{{ $value }}" class="bg-ink-900">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @can('akunting.approve')
                <!-- Jalankan depresiasi manual (idempoten per periode) -->
                <div class="flex items-center gap-2">
                    <label for="aset-periode-depresiasi" class="text-[11px] text-ink-400 font-medium whitespace-nowrap">Periode</label>
                    <input
                        id="aset-periode-depresiasi"
                        type="month"
                        wire:model.live="periodeDepresiasi"
                        aria-describedby="aset-help-periode"
                        @class(['glass-input rounded-lg px-2.5 py-2 text-xs font-medium min-h-11 tabular-nums', 'border-up-red/60' => $errors->has('periodeDepresiasi')])
                    />
                    <x-prism.prism-button
                        wire:click="jalankanDepresiasi"
                        variant="mint"
                        size="sm"
                        class="min-h-11"
                        wire:loading.attr="disabled"
                        wire:target="jalankanDepresiasi"
                    >
                        <span wire:loading.remove wire:target="jalankanDepresiasi">Jalankan Depresiasi</span>
                        <span wire:loading wire:target="jalankanDepresiasi">Memproses…</span>
                    </x-prism.prism-button>
                </div>
            @endcan

            @can('akunting.create')
                <x-prism.prism-button
                    wire:click="toggleForm"
                    size="sm"
                    class="min-h-11"
                    wire:loading.attr="disabled"
                    wire:target="toggleForm"
                    :aria-expanded="$showForm ? 'true' : 'false'"
                    aria-controls="aset-form-tambah"
                >
                    {{ $showForm ? 'Tutup Formulir' : '+ Tambah Aset' }}
                </x-prism.prism-button>
            @endcan
        </div>
    </div>

    @if($tanpaCabang)
        {{-- Error state: tanpa cabang aktif SEMUA data kosong, jangan tampilkan sebagai "belum ada aset". --}}
        <div class="rounded-2xl border border-up-amber/30 bg-up-amber/10 p-4 flex flex-col sm:flex-row sm:items-center gap-3" role="alert">
            <span class="w-2.5 h-2.5 rounded-full bg-up-amber shrink-0" aria-hidden="true"></span>
            <div class="min-w-0">
                <p class="text-sm font-bold text-up-amber">Cabang aktif belum dipilih</p>
                <p class="text-[11px] text-ink-300 mt-0.5">
                    Tanpa cabang aktif, register aset tidak boleh menampilkan data cabang mana pun.
                    Pilih cabang lewat tombol <span class="font-semibold text-ink-100">Ganti</span> di sidebar atas.
                </p>
            </div>
        </div>
    @endif

    @can('akunting.approve')
        @if($errors->has('periodeDepresiasi'))
            <p class="text-[11px] font-medium text-up-red" role="alert">{{ $errors->first('periodeDepresiasi') }}</p>
        @endif
    @endcan

    <!-- ═══ REKAP: nilai buku (hero) + 3 stat ringkas ═══ -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <x-prism.glass-card title="Nilai Buku Tercatat" subtitle="Harga perolehan − akumulasi depresiasi" circuit="true" padding="p-5" class="xl:col-span-2">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <p class="text-3xl font-black tabular-nums text-white leading-none">
                    Rp {{ number_format($rekap['sisa'], 0, ',', '.') }}
                </p>
                <x-prism.status-pill status="{{ $persenSusut > 0 ? 'proses' : 'pending' }}" size="md">
                    {{ number_format($persenSusut, 1, ',', '.') }}% tersusut
                </x-prism.status-pill>
            </div>

            {{-- Progress penyusutan --}}
            <div class="mt-4">
                <div
                    class="h-2 w-full rounded-full bg-white/[0.06] overflow-hidden border border-white/5"
                    role="progressbar"
                    aria-label="Proportion akumulasi depresiasi terhadap harga perolehan"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="{{ $persenSusut }}"
                >
                    <div
                        class="h-full rounded-full bg-gradient-to-r from-up-primary via-up-primary to-up-accent transition-[width] duration-500"
                        style="width: {{ max($persenSusut, $rekap['harga'] > 0 ? 1.5 : 0) }}%"
                    ></div>
                </div>
                <div class="mt-2 flex justify-between text-[10px] text-ink-500 tabular-nums">
                    <span>Akumulasi Rp {{ number_format($rekap['akumulasi'], 0, ',', '.') }}</span>
                    <span>Perolehan Rp {{ number_format($rekap['harga'], 0, ',', '.') }}</span>
                </div>
            </div>
        </x-prism.glass-card>

        <x-prism.glass-card title="Jumlah Aset" subtitle="Cabang aktif" circuit="true" padding="p-5">
            <p class="text-3xl font-black tabular-nums text-up-primary leading-none">{{ number_format($rekap['jumlah'], 0, ',', '.') }}</p>
            <p class="text-[11px] text-ink-500 mt-2">
                @if($asetRows->count() !== $rekap['jumlah'])
                    {{ number_format($asetRows->count(), 0, ',', '.') }} ditampilkan berfilter
                @else
                    unit aset &amp; alat
                @endif
            </p>
        </x-prism.glass-card>

        <x-prism.glass-card title="Belum Terdisposisi" subtitle="Aset aktif yang masih disusut" circuit="true" padding="p-5">
            @php $jumlahAktif = $asetRows->where('status', 'aktif')->count(); @endphp
            <p class="text-3xl font-black tabular-nums text-up-amber leading-none">{{ number_format($jumlahAktif, 0, ',', '.') }}</p>
            <p class="text-[11px] text-ink-500 mt-2">
                @if($jumlahAktif > 0)
                    Otomatis ikut depresiasi tiap periode
                @else
                    Tidak ada aset aktif saat ini
                @endif
            </p>
        </x-prism.glass-card>
    </div>

    <!-- ═══ TABEL REGISTER ═══ -->
    <x-prism.glass-card
        title="Register Aset Tetap"
        subtitle="Urut: aset aktif dulu, lalu habis umur, lalu disposal"
        circuit="true"
        padding="p-4"
    >
        <x-prism.data-table :headers="['Aset', 'Perolehan', 'Harga Perolehan', 'Susut/Bulan', 'Akumulasi', 'Sisa Buku', 'Terakhir Diproses', 'Status', '']">
            @forelse($asetRows as $aset)
                @php
                    $harga = (float) $aset->harga_perolehan;
                    $sisaBuku = $aset->sisa_buku;
                    $akumulasi = (float) $aset->akumulasi_depresiasi;
                    $persenAset = $harga > 0 ? min(100, round(($akumulasi / $harga) * 100, 1)) : 0.0;
                    $bolehDisposal = $aset->status !== 'disposal';
                @endphp
                <tr wire:key="aset-{{ $aset->id }}" class="hover:bg-white/[0.02] transition-colors text-xs">
                    <td class="py-3 px-4 align-top">
                        <p class="font-semibold text-white leading-tight">{{ $aset->nama }}</p>
                        <span class="inline-block mt-1 text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded border {{ $kategoriWarna[$aset->kategori] ?? $kategoriWarna['lainnya'] }}">
                            {{ $kategoriLabel[$aset->kategori] ?? $aset->kategori }}
                        </span>
                        @if($aset->catatan)
                            <p class="text-[10px] text-ink-500 mt-1 max-w-[22ch] truncate" title="{{ $aset->catatan }}">{{ $aset->catatan }}</p>
                        @endif
                    </td>
                    <td class="py-3 px-4 align-top text-ink-300 whitespace-nowrap">
                        {{ $aset->tanggal_perolehan?->format('d/m/Y') ?? '-' }}
                        <span class="block text-[10px] text-ink-500 tabular-nums">{{ number_format($aset->umur_bulan, 0, ',', '.') }} bulan</span>
                    </td>
                    <td class="py-3 px-4 align-top text-right tabular-nums text-white font-semibold whitespace-nowrap">
                        {{ number_format($harga, 0, ',', '.') }}
                    </td>
                    <td class="py-3 px-4 align-top text-right tabular-nums text-ink-300 whitespace-nowrap">
                        {{ number_format($aset->depresiasi_bulanan, 0, ',', '.') }}
                    </td>
                    <td class="py-3 px-4 align-top text-right whitespace-nowrap">
                        <span class="tabular-nums text-up-amber font-semibold">{{ number_format($akumulasi, 0, ',', '.') }}</span>
                        <span class="mt-1 block h-1 w-16 ml-auto rounded-full bg-white/[0.06] overflow-hidden" aria-hidden="true">
                            <span class="block h-full rounded-full bg-up-accent/70" style="width: {{ max($persenAset, 2) }}%"></span>
                        </span>
                    </td>
                    <td class="py-3 px-4 align-top text-right tabular-nums font-bold whitespace-nowrap {{ $sisaBuku > 0 ? 'text-white' : 'text-ink-500' }}">
                        {{ number_format($sisaBuku, 0, ',', '.') }}
                    </td>
                    <td class="py-3 px-4 align-top text-ink-400 tabular-nums whitespace-nowrap">
                        {{ $aset->depresiasi_terakhir_bulan ?: '—' }}
                    </td>
                    <td class="py-3 px-4 align-top whitespace-nowrap">
                        @if($aset->status === 'aktif')
                            <x-prism.status-pill status="aktif">Aktif</x-prism.status-pill>
                        @elseif($aset->status === 'fully_dep')
                            <x-prism.status-pill status="pending">Habis Umur</x-prism.status-pill>
                        @else
                            <x-prism.status-pill status="ditolak">Disposal</x-prism.status-pill>
                        @endif
                    </td>
                    <td class="py-3 px-4 align-top text-right whitespace-nowrap">
                        @if($bolehDisposal)
                            @can('akunting.approve')
                                <x-prism.prism-button
                                    wire:click="konfirmasiDisposal({{ $aset->id }})"
                                    variant="ghost"
                                    size="sm"
                                    class="!px-2.5 !py-1 text-up-red hover:!bg-up-red/15"
                                    wire:loading.attr="disabled"
                                    wire:target="konfirmasiDisposal({{ $aset->id }})"
                                >
                                    Disposal
                                </x-prism.prism-button>
                            @endcan
                        @else
                            <span class="text-[10px] text-ink-500">Selesai</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="py-12 px-4">
                        @if($rekap['jumlah'] > 0)
                            {{-- Filter tidak_match — tetap informatif, ada jalan keluar. --}}
                            <div class="flex flex-col items-center gap-2 text-center">
                                <p class="text-sm font-semibold text-ink-200">Tidak ada aset berstatus "{{ $filterStatusOptions[$filterStatus] ?? $filterStatus }}"</p>
                                <p class="text-[11px] text-ink-400 max-w-md">
                                    Cabang ini punya {{ number_format($rekap['jumlah'], 0, ',', '.') }} aset, tapi tidak ada yang cocok dengan filter ini.
                                </p>
                                <x-prism.prism-button wire:click="$set('filterStatus', 'semua')" variant="ghost" size="sm" class="mt-1 min-h-11">
                                    Tampilkan Semua Status
                                </x-prism.prism-button>
                            </div>
                        @else
                            {{-- Benar-benar kosong — jelaskan kenapa + jalan masuk. --}}
                            <div class="flex flex-col items-center gap-2 text-center">
                                <p class="text-sm font-semibold text-ink-200">Belum ada aset tetap di cabang ini</p>
                                <p class="text-[11px] text-ink-400 max-w-md">
                                    Daftarkan aset (mis. laptop, lift, rak gudang) supaya penyusutan per bulan mulai dihitung
                                    dan jurnal beban depresiasi ikut terbentuk. Depresiasi berjalan otomatis tiap awal bulan.
                                </p>
                                @can('akunting.create')
                                    @unless($tanpaCabang)
                                        <x-prism.prism-button wire:click="toggleForm" size="sm" class="mt-1 min-h-11">
                                            + Tambah Aset Pertama
                                        </x-prism.prism-button>
                                    @endunless
                                @else
                                    <p class="text-[11px] text-ink-500 mt-1">
                                        Anda punya izin melihat, tapi belum berizin menambah aset (butuh <span class="font-mono">akunting.create</span>) — hubungi admin toko.
                                    </p>
                                @endcan
                            </div>
                        @endif
                    </td>
                </tr>
            @endforelse

            {{-- Mobile / tablet (ADR 0010): kartu, bukan tabel --}}
            @slot('mobileCards')
                @forelse($asetRows as $aset)
                    @php
                        $harga = (float) $aset->harga_perolehan;
                        $sisaBuku = $aset->sisa_buku;
                    @endphp
                    <div wire:key="aset-mobile-{{ $aset->id }}" class="p-3.5 rounded-xl bg-white/[0.03] border border-white/5 space-y-2.5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-white truncate">{{ $aset->nama }}</p>
                                <p class="text-[10px] text-ink-500 mt-0.5">
                                    {{ $kategoriLabel[$aset->kategori] ?? $aset->kategori }} ·
                                    {{ $aset->tanggal_perolehan?->format('d/m/Y') ?? '-' }} ·
                                    {{ number_format($aset->umur_bulan, 0, ',', '.') }} bln
                                </p>
                            </div>
                            @if($aset->status === 'aktif')
                                <x-prism.status-pill status="aktif">Aktif</x-prism.status-pill>
                            @elseif($aset->status === 'fully_dep')
                                <x-prism.status-pill status="pending">Habis Umur</x-prism.status-pill>
                            @else
                                <x-prism.status-pill status="ditolak">Disposal</x-prism.status-pill>
                            @endif
                        </div>

                        <div class="grid grid-cols-2 gap-x-3 gap-y-1.5 text-[11px]">
                            <div>
                                <p class="text-ink-500">Harga perolehan</p>
                                <p class="tabular-nums text-white font-semibold">Rp {{ number_format($harga, 0, ',', '.') }}</p>
                            </div>
                            <div>
                                <p class="text-ink-500">Susut / bulan</p>
                                <p class="tabular-nums text-ink-200">Rp {{ number_format($aset->depresiasi_bulanan, 0, ',', '.') }}</p>
                            </div>
                            <div>
                                <p class="text-ink-500">Akumulasi</p>
                                <p class="tabular-nums text-up-amber">Rp {{ number_format($aset->akumulasi_depresiasi, 0, ',', '.') }}</p>
                            </div>
                            <div>
                                <p class="text-ink-500">Sisa buku</p>
                                <p class="tabular-nums text-white font-bold">Rp {{ number_format($sisaBuku, 0, ',', '.') }}</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-2 pt-1 border-t border-white/5">
                            <span class="text-[10px] text-ink-500 tabular-nums">
                                Terakhir: {{ $aset->depresiasi_terakhir_bulan ?: 'belum pernah' }}
                            </span>
                            @if($aset->status !== 'disposal')
                                @can('akunting.approve')
                                    <x-prism.prism-button
                                        wire:click="konfirmasiDisposal({{ $aset->id }})"
                                        variant="ghost"
                                        size="sm"
                                        class="!px-2.5 !py-1 min-h-9 text-up-red"
                                        wire:loading.attr="disabled"
                                        wire:target="konfirmasiDisposal({{ $aset->id }})"
                                    >
                                        Disposal
                                    </x-prism.prism-button>
                                @endcan
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="py-8 text-center text-[11px] text-ink-400">
                        {{ $rekap['jumlah'] > 0 ? 'Tidak ada aset yang cocok dengan filter ini.' : 'Belum ada aset tetap di cabang ini.' }}
                    </p>
                @endforelse
            @endslot
        </x-prism.data-table>
    </x-prism.glass-card>

    <!-- ═══ MODAL: FORMULIR TAMBAH ASET ═══ -->
    @if($showForm)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-3 sm:p-4"
            @keydown.escape.window="toggleForm"
        >
            <x-prism.glass-card
                title="Daftarkan Aset Tetap"
                subtitle="Penyusutan dihitung garis lurus: harga perolehan ÷ umur ekonomis."
                class="w-full max-w-2xl"
            >
                <x-slot:action>
                    <x-prism.prism-button
                        wire:click="toggleForm"
                        variant="ghost"
                        size="sm"
                        class="min-h-11 min-w-11"
                        aria-label="Tutup formulir tambah aset"
                        title="Tutup"
                    >
                        ✕
                    </x-prism.prism-button>
                </x-slot:action>

                @if($errors->any())
                    {{-- Ringkasan error: informatif, bukan layar kosong. --}}
                    <div class="mb-4 rounded-xl border border-up-red/30 bg-up-red/10 p-3" role="alert" aria-live="polite">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-up-red">Formulir belum lengkap</p>
                        <ul class="mt-2 space-y-1 text-[11px] text-up-red">
                            @foreach($errors->all() as $pesan)
                                <li class="flex gap-2">
                                    <span aria-hidden="true">•</span>
                                    <span>{{ $pesan }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="space-y-4">
                    <div>
                        <label for="aset-nama" class="block text-xs font-semibold text-ink-300 mb-1.5">Nama Aset *</label>
                        <input
                            id="aset-nama"
                            type="text"
                            x-ref="namaAset"
                            x-init="$nextTick(() => $refs.namaAset?.focus())"
                            wire:model="form.nama"
                            placeholder="Contoh: Laptop Kasir 01"
                            autocomplete="off"
                            aria-describedby="aset-error-nama"
                            @class(['glass-input w-full rounded-xl px-3 py-3 text-xs font-medium', 'border-up-red/60' => $errors->has('form.nama')])
                        />
                        @error('form.nama')
                            <p id="aset-error-nama" class="mt-1.5 text-[11px] font-medium text-up-red" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="aset-kategori" class="block text-xs font-semibold text-ink-300 mb-1.5">Kategori *</label>
                            <select
                                id="aset-kategori"
                                wire:model="form.kategori"
                                aria-describedby="aset-help-kategori"
                                @class(['glass-input w-full rounded-xl px-3 py-3 text-xs font-medium', 'border-up-red/60' => $errors->has('form.kategori')])
                            >
                                @foreach($kategoriOptions as $value => $label)
                                    <option value="{{ $value }}" class="bg-ink-900">{{ $label }}</option>
                                @endforeach
                            </select>
                            <p id="aset-help-kategori" class="mt-1.5 text-[10px] text-ink-500">Dipakai untuk pengelompokan di laporan aset.</p>
                            @error('form.kategori')
                                <p class="mt-1.5 text-[11px] font-medium text-up-red" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="aset-harga" class="block text-xs font-semibold text-ink-300 mb-1.5">Harga Perolehan (Rp) *</label>
                            <input
                                id="aset-harga"
                                type="text"
                                inputmode="numeric"
                                x-format-number
                                wire:model="form.harga_perolehan"
                                placeholder="0"
                                aria-describedby="aset-error-harga"
                                @class(['glass-input w-full rounded-xl px-3 py-3 text-sm font-bold tabular-nums text-white', 'border-up-red/60' => $errors->has('form.harga_perolehan')])
                            />
                            @error('form.harga_perolehan')
                                <p id="aset-error-harga" class="mt-1.5 text-[11px] font-medium text-up-red" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="aset-tanggal" class="block text-xs font-semibold text-ink-300 mb-1.5">Tanggal Perolehan *</label>
                            <input
                                id="aset-tanggal"
                                type="date"
                                wire:model="form.tanggal_perolehan"
                                aria-describedby="aset-error-tanggal"
                                @class(['glass-input w-full rounded-xl px-3 py-3 text-xs font-medium tabular-nums', 'border-up-red/60' => $errors->has('form.tanggal_perolehan')])
                            />
                            @error('form.tanggal_perolehan')
                                <p id="aset-error-tanggal" class="mt-1.5 text-[11px] font-medium text-up-red" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="aset-umur" class="block text-xs font-semibold text-ink-300 mb-1.5">Umur Ekonomis (bulan) *</label>
                            <input
                                id="aset-umur"
                                type="number"
                                min="1"
                                max="600"
                                step="1"
                                wire:model="form.umur_bulan"
                                placeholder="36"
                                aria-describedby="aset-help-umur"
                                @class(['glass-input w-full rounded-xl px-3 py-3 text-xs font-medium tabular-nums', 'border-up-red/60' => $errors->has('form.umur_bulan')])
                            />
                            @php $sisaTahun = ((int) ($form['umur_bulan'] ?? 0)) > 0 ? round((int) $form['umur_bulan'] / 12, 1) : 0; @endphp
                            <p id="aset-help-umur" class="mt-1.5 text-[10px] text-ink-500">
                                Maks 600 bulan (50 tahun).@if($sisaTahun > 0) Setara ± {{ number_format($sisaTahun, 1, ',', '.') }} tahun.@endif
                            </p>
                            @error('form.umur_bulan')
                                <p class="mt-1.5 text-[11px] font-medium text-up-red" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="aset-catatan" class="block text-xs font-semibold text-ink-300 mb-1.5">Catatan <span class="text-ink-500 font-normal">(opsional)</span></label>
                        <textarea
                            id="aset-catatan"
                            rows="2"
                            wire:model="form.catatan"
                            placeholder="mis. Nomor seri, kondisi, lokasi penyimpanan"
                            aria-describedby="aset-error-catatan"
                            @class(['glass-input w-full rounded-xl px-3 py-3 text-xs font-medium resize-y', 'border-up-red/60' => $errors->has('form.catatan')])
                        ></textarea>
                        @error('form.catatan')
                            <p id="aset-error-catatan" class="mt-1.5 text-[11px] font-medium text-up-red" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-3 border-t border-white/10 pt-4 sm:flex-row">
                    <x-prism.prism-button wire:click="toggleForm" variant="ghost" size="lg" class="sm:flex-1">
                        Batal
                    </x-prism.prism-button>
                    <x-prism.prism-button
                        wire:click="simpan"
                        size="lg"
                        class="sm:flex-[2]"
                        wire:loading.attr="disabled"
                        wire:target="simpan"
                    >
                        <span wire:loading.remove wire:target="simpan">Simpan Aset Tetap</span>
                        <span wire:loading wire:target="simpan">Menyimpan…</span>
                    </x-prism.prism-button>
                </div>
            </x-prism.glass-card>
        </div>
    @endif

    <!-- ═══ CONFIRMDIALOG: DISPOSAL (WRITE-OFF JURNAL) ═══ -->
    @if($showDisposalConfirm)
        <div
            class="fixed inset-0 z-[60] flex items-center justify-center bg-black/85 backdrop-blur-sm p-4"
            @keydown.escape.window="batalDisposal"
        >
            <x-prism.glass-card
                title="Konfirmasi Disposal Aset"
                subtitle="Aset dikeluarkan dari buku dan jurnal write-off langsung diposting."
                class="w-full max-w-lg"
            >
                <div class="rounded-2xl border border-up-red/25 bg-up-red/10 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-up-red mb-1">Aset</p>
                    <p class="text-sm font-bold text-white leading-tight">{{ $disposalNama }}</p>
                </div>

                <dl class="mt-4 space-y-2 text-xs">
                    <div class="flex items-center justify-between gap-3 border-b border-white/5 pb-2">
                        <dt class="text-ink-300">Harga perolehan <span class="text-[10px] text-ink-500">(dikredit)</span></dt>
                        <dd class="tabular-nums text-white font-semibold">Rp {{ number_format($disposalHarga, 0, ',', '.') }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3 border-b border-white/5 pb-2">
                        <dt class="text-ink-300">Akumulasi depresiasi <span class="text-[10px] text-ink-500">(didebit)</span></dt>
                        <dd class="tabular-nums text-up-amber font-semibold">Rp {{ number_format($disposalAkumulasi, 0, ',', '.') }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-ink-300">Sisa buku <span class="text-[10px] text-ink-500">(didebit, jadi kerugian)</span></dt>
                        <dd class="tabular-nums text-up-red font-bold">Rp {{ number_format($disposalSisaBuku, 0, ',', '.') }}</dd>
                    </div>
                </dl>

                <p class="mt-4 text-[11px] leading-relaxed text-ink-400">
                    Aset berhenti disusut, statusnya jadi <span class="text-ink-200">disposal</span>, dan jurnal write-off tidak dapat diubah dari halaman ini.
                    @if($disposalSisaBuku > 0)
                        Kerugian sebesar Rp {{ number_format($disposalSisaBuku, 0, ',', '.') }} akan membebani laporan laba rugi bulan berjalan.
                    @endif
                </p>

                <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row">
                    <x-prism.prism-button wire:click="batalDisposal" variant="ghost" size="lg" class="sm:flex-1">
                        Batal, Periksa Lagi
                    </x-prism.prism-button>
                    <x-prism.prism-button
                        wire:click="eksekusiDisposal"
                        variant="danger"
                        size="lg"
                        class="sm:flex-[2]"
                        wire:loading.attr="disabled"
                        wire:target="eksekusiDisposal"
                    >
                        <span wire:loading.remove wire:target="eksekusiDisposal">Ya, Buang Aset Ini</span>
                        <span wire:loading wire:target="eksekusiDisposal">Memosting…</span>
                    </x-prism.prism-button>
                </div>
            </x-prism.glass-card>
        </div>
    @endif
</div>
