<?php

namespace App\Modules\Akunting\Livewire;

use App\Modules\Akunting\Models\AsetTetap;
use App\Modules\Akunting\Services\DepresiasiService;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * [F3-3] Register Aset Tetap & Depresiasi — Backoffice (desktop-first, Ute Prism).
 *
 * RBAC: halaman route middleware permission:akunting.view;
 * create/disposal aksi butuh permission:akunting.create / akunting.approve.
 * Scoping KETAT: semua query where cabang_id = session('cabang_id') — tanpa if,
 * session null → where null → tidak ada baris (tidak pernah bocor lintas cabang).
 * Posting jurnal disposal via DepresiasiService → JurnalService::post().
 */
class AsetRegister extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public array $form = [];

    public bool $showDisposalConfirm = false;

    public ?int $disposalId = null;

    public string $disposalNama = '';

    /** Opsi kategori aset tetap. */
    public array $kategoriOptions = [
        'gedung' => 'Gedung',
        'peralatan' => 'Peralatan',
        'kendaraan' => 'Kendaraan',
        'it' => 'IT & Elektronik',
        'furnitur' => 'Furnitur',
        'lainnya' => 'Lainnya',
    ];

    public function mount(): void
    {
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->form = [
            'nama' => '',
            'kategori' => 'peralatan',
            'harga_perolehan' => '',
            'tanggal_perolehan' => now()->toDateString(),
            'umur_bulan' => 36,
            'catatan' => '',
        ];
    }

    public function toggleForm(): void
    {
        $this->showForm = ! $this->showForm;
        if ($this->showForm) {
            $this->resetForm();
        }
    }

    /** Simpan aset baru — stamp cabang aktif dari session (wajib). */
    public function simpan(): void
    {
        if (! auth()->user()?->can('akunting.create')) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda tidak punya izin menambah aset tetap.']);

            return;
        }

        $cabangId = session('cabang_id');
        if (! $cabangId) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Cabang aktif belum dipilih — pilih cabang terlebih dahulu.']);

            return;
        }

        $this->validate([
            'form.nama' => 'required|string|max:255',
            'form.kategori' => 'required|string|max:100',
            'form.harga_perolehan' => 'required|numeric|min:1',
            'form.tanggal_perolehan' => 'required|date',
            'form.umur_bulan' => 'required|integer|min:1|max:600',
            'form.catatan' => 'nullable|string|max:1000',
        ], [
            'form.nama.required' => 'Nama aset wajib diisi.',
            'form.nama.max' => 'Nama aset maksimal 255 karakter.',
            'form.harga_perolehan.required' => 'Harga perolehan wajib diisi.',
            'form.harga_perolehan.numeric' => 'Harga perolehan harus berupa angka.',
            'form.harga_perolehan.min' => 'Harga perolehan minimal Rp 1.',
            'form.tanggal_perolehan.required' => 'Tanggal perolehan wajib diisi.',
            'form.tanggal_perolehan.date' => 'Tanggal perolehan tidak valid.',
            'form.umur_bulan.required' => 'Umur depresiasi (bulan) wajib diisi.',
            'form.umur_bulan.integer' => 'Umur depresiasi harus bilangan bulat.',
            'form.umur_bulan.min' => 'Umur depresiasi minimal 1 bulan.',
            'form.umur_bulan.max' => 'Umur depresiasi maksimal 600 bulan (50 tahun).',
            'form.catatan.max' => 'Catatan maksimal 1000 karakter.',
        ]);

        AsetTetap::create([
            'cabang_id' => $cabangId,
            'nama' => $this->form['nama'],
            'kategori' => $this->form['kategori'],
            'harga_perolehan' => $this->form['harga_perolehan'],
            'tanggal_perolehan' => $this->form['tanggal_perolehan'],
            'umur_bulan' => (int) $this->form['umur_bulan'],
            'metode' => 'garis_lurus',
            'status' => 'aktif',
            'catatan' => $this->form['catatan'] ?: null,
            'user_id' => auth()->id(),
        ]);

        $this->showForm = false;
        $this->resetForm();

        $this->dispatch('alert', ['type' => 'success', 'message' => 'Aset tetap berhasil didaftarkan.']);
    }

    /** Buka konfirmasi disposal (2 langkah — pola ConfirmDialog). */
    public function konfirmasiDisposal(int $id): void
    {
        $aset = AsetTetap::where('cabang_id', session('cabang_id'))->find($id);

        if (! $aset) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Aset tidak ditemukan di cabang aktif.']);

            return;
        }

        if ($aset->status === 'disposal') {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Aset ini sudah pernah didisposal.']);

            return;
        }

        $this->disposalId = $aset->id;
        $this->disposalNama = $aset->nama;
        $this->showDisposalConfirm = true;
    }

    public function batalDisposal(): void
    {
        $this->showDisposalConfirm = false;
        $this->disposalId = null;
        $this->disposalNama = '';
    }

    /** Eksekusi disposal → write-off jurnal via JurnalService::post(). */
    public function eksekusiDisposal(): void
    {
        if (! auth()->user()?->can('akunting.approve')) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda tidak punya izin melakukan disposal aset.']);

            return;
        }

        // Scoping: aset hanya boleh dari cabang aktif
        $aset = AsetTetap::where('cabang_id', session('cabang_id'))->find($this->disposalId);

        if (! $aset) {
            $this->batalDisposal();
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Aset tidak ditemukan di cabang aktif.']);

            return;
        }

        try {
            $hasil = app(DepresiasiService::class)->dispose($aset, auth()->id());

            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Aset \"{$aset->nama}\" didisposal — jurnal {$hasil['no_jurnal']} (kerugian Rp ".
                    number_format($hasil['kerugian'], 2, ',', '.').').',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        $this->batalDisposal();
    }

    public function render()
    {
        // Scoping KETAT cabang — session null → where(cabang_id, null) → is null → kosong
        $asetRows = AsetTetap::where('cabang_id', session('cabang_id'))
            ->orderByRaw("CASE status WHEN 'aktif' THEN 0 WHEN 'fully_dep' THEN 1 ELSE 2 END")
            ->orderBy('nama')
            ->get();

        $rekap = [
            'jumlah' => $asetRows->count(),
            'harga' => round($asetRows->sum('harga_perolehan'), 2),
            'akumulasi' => round($asetRows->sum('akumulasi_depresiasi'), 2),
            'sisa' => round($asetRows->sum(fn (AsetTetap $a) => $a->sisa_buku), 2),
        ];

        return view('modules.akunting.livewire.aset-register', [
            'asetRows' => $asetRows,
            'rekap' => $rekap,
            'kategoriOptions' => $this->kategoriOptions,
        ])->layout('layouts.backoffice', ['header' => 'Aset Tetap & Depresiasi']);
    }
}
