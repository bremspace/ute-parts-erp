<?php

namespace App\Modules\Akunting\Livewire;

use App\Modules\Akunting\Jobs\DepresiasiAsetJob;
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

    /** Nominal aset yang akan di-write-off — hanya untuk ringkasan ConfirmDialog. */
    public float $disposalHarga = 0.0;

    public float $disposalAkumulasi = 0.0;

    public float $disposalSisaBuku = 0.0;

    /** Periode depresiasi manual (YYYY-MM) — default bulan berjalan. */
    public string $periodeDepresiasi = '';

    /** Filter status tabel: semua | aktif | fully_dep | disposal. */
    public string $filterStatus = 'semua';

    /** Opsi filter status (dipakai view + validasi input). */
    public array $filterStatusOptions = [
        'semua' => 'Semua status',
        'aktif' => 'Aktif',
        'fully_dep' => 'Habis umur',
        'disposal' => 'Disposal',
    ];

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
        $this->periodeDepresiasi = now()->format('Y-m');
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
            // Jangan tampilkan error formulir dari percobaan sebelumnya.
            $this->resetValidation();
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

        // [ADR 0011] Input nominal diformat ribuan di sisi klien ("1.000.000") —
        // normalisasi lebih dulu supaya rule `numeric` tidak menolak nilai yang sah.
        // Nilai kosong/salah tidak diubah supaya pesan error tetap akurat.
        $hargaPerolehan = $this->parseNominal($this->form['harga_perolehan'] ?? '');
        if ($hargaPerolehan > 0) {
            $this->form['harga_perolehan'] = $hargaPerolehan;
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

        // $hargaPerolehan dijamin > 0 (dan sudah berupa float) oleh rule min:1 di atas.
        AsetTetap::create([
            'cabang_id' => $cabangId,
            'nama' => $this->form['nama'],
            'kategori' => $this->form['kategori'],
            'harga_perolehan' => $hargaPerolehan,
            'tanggal_perolehan' => $this->form['tanggal_perolehan'],
            'umur_bulan' => (int) $this->form['umur_bulan'],
            'metode' => 'garis_lurus',
            'status' => 'aktif',
            'catatan' => $this->form['catatan'] ?: null,
            'user_id' => auth()->id(),
        ]);

        $this->showForm = false;
        $this->resetForm();
        $this->resetValidation();

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Aset tetap "'.$this->form['nama'].'" berhasil didaftarkan (Rp '.number_format($hargaPerolehan, 0, ',', '.').').',
        ]);
    }

    /**
     * Bersihkan input nominal berformat ribuan (ADR 0011 "1.000.000" / "1.000.000,50")
     * menjadi angka float. Nilai yang tidak bisa diparse dikembalikan apa adanya supaya
     * pesan validasi (mis. "harus berupa angka") tetap akurat.
     */
    private function parseNominal(mixed $value): float
    {
        $teks = str_replace(['Rp', ' '], '', trim((string) $value));

        if ($teks === '') {
            return 0.0;
        }

        // "1.000.000,50" — titik ribuan + koma desimal
        if (str_contains($teks, ',') && str_contains($teks, '.')) {
            $teks = str_replace('.', '', $teks);
            $teks = str_replace(',', '.', $teks);
        } elseif (str_contains($teks, ',')) {
            // "1000,50" — koma desimal
            $teks = str_replace(',', '.', $teks);
        } else {
            // "1.000.000" — titik Ribuan bila semua grup setelah titik berisi 3 digit
            $bagian = explode('.', $teks);
            $ribuan = count($bagian) > 1;
            foreach (array_slice($bagian, 1) as $grup) {
                if (strlen($grup) !== 3) {
                    $ribuan = false;
                    break;
                }
            }
            if ($ribuan) {
                $teks = str_replace('.', '', $teks);
            }
        }

        return is_numeric($teks) ? (float) $teks : 0.0;
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
        $this->disposalHarga = (float) $aset->harga_perolehan;
        $this->disposalAkumulasi = (float) $aset->akumulasi_depresiasi;
        $this->disposalSisaBuku = $aset->sisa_buku;
        $this->showDisposalConfirm = true;
    }

    public function batalDisposal(): void
    {
        $this->showDisposalConfirm = false;
        $this->disposalId = null;
        $this->disposalNama = '';
        $this->disposalHarga = 0.0;
        $this->disposalAkumulasi = 0.0;
        $this->disposalSisaBuku = 0.0;
    }

    /**
     * Jalankan depresiasi satu periode (YYYY-MM) untuk cabang aktif.
     *
     * [B-15c] TIDAK sinkron: sejak ini request hanya mengantrekan
     * `DepresiasiAsetJob` (queue `database` + Supervisor). Sebelumnya service
     * dipanggil langsung di dalam request → ±5 query/aset × 20-200 aset (=400-1.000
     * query) menahan PHP-LSAPI worker. Job tetap idempoten per periode
     * (guard `depresiasi_terakhir_bulan` + cek jurnal existing), jadi aman dijalankan
     * berkali-kali — termasuk saat user menekan tombol dua kali.
     *
     * Defaultnya tetap job bulanan terjadwal — aksi ini untuk user yang butuh susut
     * saat ini (mis. aset baru didaftarkan di tengah bulan) tanpa menunggu jadwal tgl 1.
     */
    public function jalankanDepresiasi(): void
    {
        if (! auth()->user()?->can('akunting.approve')) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda tidak punya izin menjalankan depresiasi aset.']);

            return;
        }

        $cabangId = session('cabang_id');
        if (! $cabangId) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Cabang aktif belum dipilih — pilih cabang terlebih dahulu.']);

            return;
        }

        $this->validate([
            'periodeDepresiasi' => 'required|date_format:Y-m',
        ], [
            'periodeDepresiasi.required' => 'Periode depresiasi wajib diisi.',
            'periodeDepresiasi.date_format' => 'Format periode harus YYYY-MM (contoh: 2026-09).',
        ]);

        try {
            // [B-15c] async — Scoped cabang aktif (bukan semua cabang).
            dispatch(new DepresiasiAsetJob($this->periodeDepresiasi, (int) $cabangId));
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Gagal mengantrekan depresiasi: '.$e->getMessage()]);

            return;
        }

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => "Depresiasi {$this->periodeDepresiasi} diantre — jurnal 530-01 / 130-02 diposting di latar belakang. Notifikasi muncul setelah selesai.",
        ]);
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
        $query = AsetTetap::where('cabang_id', session('cabang_id'));

        // Filter status (opsional) — rekap TETAP mencakup seluruh aset cabang, bukan
        // hanya hasil filter, supaya angka di kartu ringkasan tidak ikut berubah-ubah.
        $filterAktif = array_key_exists($this->filterStatus, $this->filterStatusOptions) && $this->filterStatus !== 'semua';
        if ($filterAktif) {
            $query->where('status', $this->filterStatus);
        }

        $asetTerfilter = $query
            ->orderByRaw("CASE status WHEN 'aktif' THEN 0 WHEN 'fully_dep' THEN 1 ELSE 2 END")
            ->orderBy('nama')
            ->get();

        $semuaAset = $filterAktif
            ? AsetTetap::where('cabang_id', session('cabang_id'))->get()
            : $asetTerfilter;

        $rekap = [
            'jumlah' => $semuaAset->count(),
            'harga' => round($semuaAset->sum('harga_perolehan'), 2),
            'akumulasi' => round($semuaAset->sum('akumulasi_depresiasi'), 2),
            'sisa' => round($semuaAset->sum(fn (AsetTetap $a) => $a->sisa_buku), 2),
        ];

        return view('modules.akunting.livewire.aset-register', [
            'asetRows' => $asetTerfilter,
            'rekap' => $rekap,
            'kategoriOptions' => $this->kategoriOptions,
            'filterStatusOptions' => $this->filterStatusOptions,
            'periodeDepresiasi' => $this->periodeDepresiasi,
        ])->layout('layouts.backoffice', ['header' => 'Aset Tetap & Depresiasi']);
    }
}
