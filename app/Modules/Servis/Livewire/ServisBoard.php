<?php

namespace App\Modules\Servis\Livewire;

use App\Models\User;
use App\Modules\Akunting\Jobs\ExportLaporanJob;
use App\Modules\Akunting\Services\ExportLaporanService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Services\PelangganService;
use App\Modules\Rbac\Traits\PunyaRiwayatAktivitas;
use App\Modules\Servis\Models\JenisServis;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Services\ServisService;
use App\Modules\Servis\Services\ServisStateMachine;
use App\Modules\Wms\Livewire\ProductSearchModal;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Services\NomorSeriService;
use App\Traits\ParsesNominal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class ServisBoard extends Component
{
    use ParsesNominal;
    use PunyaRiwayatAktivitas;
    use WithPagination;

    /**
     * [B-15c] Kolom yang dipakai KARTU kanban (lihat servis-board.blade.php: card loop).
     *
     * P0: `select *` ikut menarik kolom JSON `foto_unit` — base64 foto 431.406 byte/baris
     * terukur di db_staging → 100 tiket ≈ 43MB JSON per render kanban (1GB RAM, 2-3
     * LSAPI worker → GC pressure + risk OOM). `foto_unit` TIDAK boleh masuk select kartu.
     * Foto tetap tampil saat detail dibuka: `getSelectedTiketProperty()` (:479) query
     * terpisah `select *` + eager load, jadi galeri foto tidak berubah.
     *
     * Kolom relasi (jenis_servis_id / pelanggan_id / teknisi_id / cabang_id) wajib ikut
     * agar eager load `jenisServis` / `pelanggan` / `teknisi` tetap jalan; `created_at`
     * dipakai `sortByDesc()` tahap-2 (B-07: TIDAK boleh `order by` kolom lebar).
     */
    public const KOLOM_KANBAN = [
        'id',
        'no_tiket',
        'cabang_id',
        'jenis_servis_id',
        'pelanggan_id',
        'teknisi_id',
        'nama_pelanggan',
        'telepon_pelanggan',
        'jenis_hp',
        'keluhan',
        'status',
        'status_pembayaran',
        'estimasi_biaya',
        'batas_waktu_menit',
        'pengerjaan_mulai_at',
        'pengerjaan_deadline_at',
        'status_sla_kpi',
        'created_at',
        'updated_at',
        'tanggal_diambil',
    ];

    // Search & filter
    public string $search = '';

    public string $filterStatus = ''; // empty = all

    // [Kanban Histori] Manajemen Histori Transaksi di Kanban: '7_hari' (default), 'hari_ini', '30_hari', 'semua'
    public string $periodeHistori = '7_hari';

    // Terima Unit Modal
    public bool $showTerimaModal = false;

    // [T-18] customer picker reusable (sama seperti POS)
    public string $pelangganSearch = '';

    // [T-18] quick-add pelanggan baru dari terima unit (CRM-06 path)
    public bool $showPelangganBaruModal = false;

    public array $pelangganBaruForm = ['nama' => '', 'telepon' => '', 'alamat' => ''];

    public array $terimaForm = [
        'pelanggan_id' => null,
        'nama_pelanggan' => '',
        'telepon_pelanggan' => '',
        'jenis_servis_id' => null,
        'jenis_hp' => '',
        'seri_hp' => '',
        // [T-19] kunci gadget
        'tipe_kunci' => null,
        'kunci_terenkripsi' => '',
        'keluhan' => '',
        'kondisi_fisik' => [],
        'foto_unit' => [], // BASE64 data URLs — [B-06] opsional (0-3 foto)
    ];

    // Foto preview (base64)
    public array $fotoPreviews = [];

    public array $photoInputs = [];

    // Terima Unit Estimasi Langsung State
    public bool $terimaSertakanEstimasi = false;

    public array $terimaEstimasiItems = [];

    public float $terimaEstimasiBiaya = 0;

    public string $terimaEstimasiAlasan = '';

    public string $terimaEstimasiMode = 'menunggu_approval'; // 'menunggu_approval' atau 'disetujui'

    public ?int $terimaEstimasiBatasWaktu = null;

    public bool $terimaEstimasiAutoCancel = false;

    // Detail Modal
    public ?int $selectedTiketId = null;

    // [B-06] Toggle "Tampilkan" kunci gadget di modal detail — default false,
    // plaintext baru dirender (masuk DOM) setelah user menekan tombol.
    public bool $bukaKunciGadget = false;

    // Estimasi Modal
    public bool $showEstimasiModal = false;

    public ?int $estimasiTiketId = null;

    public float $estimasiBiaya = 0;

    public string $estimasiAlasan = '';

    // Approve/Reject Modal (menunggu_approval)
    public bool $showApproveModal = false;

    public ?int $approveTiketId = null;

    public string $approveAlasan = '';

    // [Workflow Ditolak] Retur / Batal Servis Modal
    public bool $showBatalReturModal = false;

    public ?int $batalReturTiketId = null;

    public string $batalReturCatatan = '';

    public float $batalReturBiaya = 0;

    public string $batalReturMetode = 'tunai';

    // [Intake Booking Online] Modal Pemeriksaan & Penerimaan Fisik
    public bool $showIntakeBookingModal = false;

    public ?int $intakeBookingTiketId = null;

    public array $intakeForm = [
        'tiket_id' => null,
        'no_tiket' => '',
        'nama_pelanggan' => '',
        'telepon_pelanggan' => '',
        'jenis_hp' => '',
        'seri_hp' => '',
        'jenis_servis_id' => null,
        'keluhan' => '',
        'tipe_kunci' => '',
        'kunci_terenkripsi' => '',
        'kondisi_fisik' => [],
        'kelengkapan' => [],
        'teknisi_id' => null,
        'catatan_intake' => '',
    ];

    public array $intakePhotoInputs = [null, null, null];

    // Drag & drop target status
    public ?string $dropTargetStatus = null;

    public ?int $dropTiketId = null;

    // [T-17] Form pekerjaan teknisi (item part/jasa) di modal detail
    public array $pekerjaanItems = [];

    // [T-09d] Form estimasi multi-baris (part & jasa)
    public array $estimasiItems = [];

    // [KPI & Batas Waktu Estimasi]
    public ?int $estimasiBatasWaktuMenit = null;

    public bool $estimasiAutoCancelOverdue = false;

    public ?int $estimasiDurasiGaransiCustom = null;

    // [Progress Kerja Teknisi Modal]
    public bool $showProgressModal = false;

    public ?int $progressTiketId = null;

    public int $progressPersentase = 50;

    public string $progressJudul = '';

    public string $progressDeskripsi = '';

    public bool $progressTampilkanKonsumen = true;

    public array $progressFoto = [];

    // Modal Pencarian Part Servis
    public bool $showCariPartModal = false;

    public string $cariPartQuery = '';

    public ?int $cariPartIndex = null;

    public string $cariPartContext = 'servis_estimasi';

    // Modal Pembayaran Servis
    public bool $showBayarModal = false;

    public ?int $bayarTiketId = null;

    public string $bayarMetode = 'tunai'; // tunai, transfer, qris, kartu

    public string $bayarCatatan = '';

    public float $bayarTotalTagihan = 0;

    // Modal Struk Thermal Servis
    public bool $showReceiptModal = false;

    public ?array $receiptData = null;

    public ?int $pekerjaanGudangId = null;

    public function mount()
    {
        $this->photoInputs = [null, null, null]; // max 3 foto
    }

    #[On('produk-dipilih-global')]
    public function onProdukDipilihGlobal($targetIndex = null, $context = null, $produkId = null): void
    {
        $idx = null;
        if (is_array($targetIndex)) {
            $context = $targetIndex['context'] ?? $context;
            $produkId = $targetIndex['produkId'] ?? $produkId;
            $idx = $targetIndex['targetIndex'] ?? null;
        } else {
            $idx = $targetIndex;
        }

        if (! $produkId || $idx === null) {
            return;
        }

        $produk = Produk::find($produkId);
        if (! $produk) {
            return;
        }

        if ($context === 'servis_estimasi') {
            if (isset($this->estimasiItems[$idx])) {
                $this->estimasiItems[$idx]['produk_id'] = $produkId;
                $this->estimasiItems[$idx]['produk_nama'] = $produk->nama;
                $this->estimasiItems[$idx]['nama_item'] = $produk->nama;
                $tiket = $this->estimasiTiketId ? $this->tiketScopedOrNull($this->estimasiTiketId) : null;
                $pelanggan = $tiket?->pelanggan;
                $resolved = app(ServisService::class)->resolveHargaPartDefault($produk, $pelanggan);
                $this->estimasiItems[$idx]['harga'] = (float) ($resolved['harga'] ?? $produk->harga_jual_retail ?? $produk->harga_jual ?? 0);
                $this->recalculateEstimasiTotal();
            }
        } elseif ($context === 'servis_terima_estimasi') {
            if (isset($this->terimaEstimasiItems[$idx])) {
                $this->terimaEstimasiItems[$idx]['produk_id'] = $produkId;
                $this->terimaEstimasiItems[$idx]['produk_nama'] = $produk->nama;
                $this->terimaEstimasiItems[$idx]['nama_item'] = $produk->nama;
                $pelanggan = ! empty($this->terimaForm['pelanggan_id']) ? Pelanggan::find($this->terimaForm['pelanggan_id']) : null;
                $resolved = app(ServisService::class)->resolveHargaPartDefault($produk, $pelanggan);
                $this->terimaEstimasiItems[$idx]['harga'] = (float) ($resolved['harga'] ?? $produk->harga_jual_retail ?? $produk->harga_jual ?? 0);
                $this->recalculateTerimaEstimasiTotal();
            }
        } elseif ($context === 'servis_pekerjaan') {
            if (isset($this->pekerjaanItems[$idx])) {
                $this->pekerjaanItems[$idx]['produk_id'] = $produkId;
                $this->pekerjaanItems[$idx]['produk_nama'] = $produk->nama;
                $this->pekerjaanItems[$idx]['nama_item'] = $produk->nama;
                $tiket = $this->selectedTiketId ? $this->tiketScopedOrNull($this->selectedTiketId) : null;
                $pelanggan = $tiket?->pelanggan;
                $resolved = app(ServisService::class)->resolveHargaPartDefault($produk, $pelanggan);
                $this->pekerjaanItems[$idx]['harga'] = (float) ($resolved['harga'] ?? $produk->harga_jual_retail ?? $produk->harga_jual ?? 0);
            }
        }
    }

    public function resetEstimasiPartRow(int $idx): void
    {
        if (isset($this->estimasiItems[$idx])) {
            $this->estimasiItems[$idx]['produk_id'] = null;
            $this->estimasiItems[$idx]['produk_nama'] = '';
            $this->estimasiItems[$idx]['nama_item'] = '';
            $this->estimasiItems[$idx]['harga'] = 0;
            $this->recalculateEstimasiTotal();
        }
    }

    public function resetTerimaEstimasiPartRow(int $idx): void
    {
        if (isset($this->terimaEstimasiItems[$idx])) {
            $this->terimaEstimasiItems[$idx]['produk_id'] = null;
            $this->terimaEstimasiItems[$idx]['produk_nama'] = '';
            $this->terimaEstimasiItems[$idx]['nama_item'] = '';
            $this->terimaEstimasiItems[$idx]['harga'] = 0;
            $this->recalculateTerimaEstimasiTotal();
        }
    }

    public function resetPekerjaanPartRow(int $idx): void
    {
        if (isset($this->pekerjaanItems[$idx])) {
            $this->pekerjaanItems[$idx]['produk_id'] = null;
            $this->pekerjaanItems[$idx]['produk_nama'] = '';
            $this->pekerjaanItems[$idx]['nama_item'] = '';
            $this->pekerjaanItems[$idx]['harga'] = 0;
        }
    }

    #[On('buka-pencarian-produk')]
    public function bukaPencarianProduk($targetIndex = null, $context = 'servis'): void
    {
        if (is_array($targetIndex)) {
            $context = $targetIndex['context'] ?? $context;
            $targetIndex = $targetIndex['targetIndex'] ?? null;
        }

        $this->cariPartIndex = (int) $targetIndex;
        $this->cariPartContext = $context;
        $this->cariPartQuery = '';
        $this->showCariPartModal = true;
    }

    public function getCariPartResultsProperty()
    {
        if (! $this->showCariPartModal) {
            return collect();
        }

        $query = Produk::where('is_active', true);

        if (strlen($this->cariPartQuery) >= 2) {
            $query->cariPintar($this->cariPartQuery);
        }

        return $query->orderBy('nama')->limit(30)->get();
    }

    public function pilihPartServis(int $produkId): void
    {
        $produk = Produk::find($produkId);
        if (! $produk) {
            return;
        }

        $idx = $this->cariPartIndex;
        $context = $this->cariPartContext;

        if ($context === 'servis_estimasi' && isset($this->estimasiItems[$idx])) {
            $this->estimasiItems[$idx]['produk_id'] = $produkId;
            $this->estimasiItems[$idx]['produk_nama'] = $produk->nama;
            $this->estimasiItems[$idx]['nama_item'] = $produk->nama;
            $tiket = $this->estimasiTiketId ? $this->tiketScopedOrNull($this->estimasiTiketId) : null;
            $pelanggan = $tiket?->pelanggan;
            $resolved = app(ServisService::class)->resolveHargaPartDefault($produk, $pelanggan);
            $this->estimasiItems[$idx]['harga'] = (float) ($resolved['harga'] ?? $produk->harga_jual_retail ?? 0);
            $this->recalculateEstimasiTotal();
        } elseif ($context === 'servis_terima_estimasi' && isset($this->terimaEstimasiItems[$idx])) {
            $this->terimaEstimasiItems[$idx]['produk_id'] = $produkId;
            $this->terimaEstimasiItems[$idx]['produk_nama'] = $produk->nama;
            $this->terimaEstimasiItems[$idx]['nama_item'] = $produk->nama;
            $pelanggan = ! empty($this->terimaForm['pelanggan_id']) ? Pelanggan::find($this->terimaForm['pelanggan_id']) : null;
            $resolved = app(ServisService::class)->resolveHargaPartDefault($produk, $pelanggan);
            $this->terimaEstimasiItems[$idx]['harga'] = (float) ($resolved['harga'] ?? $produk->harga_jual_retail ?? 0);
            $this->recalculateTerimaEstimasiTotal();
        } elseif ($context === 'servis_pekerjaan' && isset($this->pekerjaanItems[$idx])) {
            $this->pekerjaanItems[$idx]['produk_id'] = $produkId;
            $this->pekerjaanItems[$idx]['produk_nama'] = $produk->nama;
            $this->pekerjaanItems[$idx]['nama_item'] = $produk->nama;
            $tiket = $this->selectedTiketId ? $this->tiketScopedOrNull($this->selectedTiketId) : null;
            $pelanggan = $tiket?->pelanggan;
            $resolved = app(ServisService::class)->resolveHargaPartDefault($produk, $pelanggan);
            $this->pekerjaanItems[$idx]['harga'] = (float) ($resolved['harga'] ?? $produk->harga_jual_retail ?? 0);
        }

        $this->showCariPartModal = false;
        $this->cariPartQuery = '';
    }

    /** [F2-5] Export laporan servis via queue & unduh langsung. */
    public function exportLaporan(string $format = 'xlsx')
    {
        if (! auth()->user()?->can('laporan.cabang')) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda tidak punya izin export laporan']);

            return null;
        }

        $cabangId = session('cabang_id');
        $userId = auth()->id();
        $fmt = $format === 'csv' ? 'csv' : 'xlsx';

        dispatch(new ExportLaporanJob(
            jenis: 'servis',
            periodeDari: null,
            periodeSampai: null,
            cabangId: $cabangId,
            akunId: null,
            userId: $userId,
            format: $fmt,
        ));

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Export servis selesai — berkas mulai diunduh.',
        ]);

        $path = app(ExportLaporanService::class)->export(
            'servis', null, null, $cabangId, null, $fmt, $userId
        );
        $fullPath = Storage::disk('local')->path($path);

        return response()->download($fullPath, 'laporan-servis-'.now()->format('Ymd-His').'.'.$fmt);
    }

    public function getStateMachineColumnsProperty(): array
    {
        return ServisStateMachine::kanbanColumns();
    }

    /**
     * [T-06 / P0-10] Query `TiketServis` yang WAJIB dibatasi ke cabang aktif sesi.
     *
     * Constraint proyek: semua query wajib scope `cabang_id` (cabang aktif di
     * session) — tanpa ini papan kanban & modal detail membocorkan tiket cabang
     * lain (bukti: review P0-10, `docs/review-servis-plan-perbaikan.md:53`).
     * Polanya sama dengan `ServisController::index()`: `session('cabang_id')`
     * null → filter dilewati (super-admin belum memilih cabang).
     * Konsisten dengan `exportLaporan()` yang mengirim `cabangId: session('cabang_id')`.
     *
     * Helper ini MENEMPELKAN `where cabang_id` ke query yang sudah ada — urutan
     * kondisi SQL pun penting: query hydrate tahap-2 di bawah harus tetap
     * diawali `where "id" in (...)` supaya regression test N+1/B-07
     * (`WmsServisNplus1Test`) terus mengenali query tersebut.
     *
     * @param  Builder<TiketServis>  $query
     * @return Builder<TiketServis>
     */
    private function scopeTiket(Builder $query): Builder
    {
        $cabangId = session('cabang_id');

        return $cabangId ? $query->where('cabang_id', $cabangId) : $query;
    }

    /**
     * Query dasar `TiketServis` yang sudah ter-scope cabang aktif.
     *
     * @return Builder<TiketServis>
     */
    private function queryTiketScoped(): Builder
    {
        return $this->scopeTiket(TiketServis::query());
    }

    /**
     * TiketScoped + `findOrFail` — tiket cabang lain TIDAK ditemukan (lempar 404).
     *
     * @throws ModelNotFoundException
     */
    private function tiketScoped(?int $id): TiketServis
    {
        return $this->queryTiketScoped()->findOrFail($id);
    }

    /** Versi nullable (dipakai detail modal agar tidak melempar exception ke UI). */
    private function tiketScopedOrNull(?int $id): ?TiketServis
    {
        return $id ? $this->queryTiketScoped()->find($id) : null;
    }

    public function getGroupedTiketsProperty(): array
    {
        // [B-07] Dua tahap — ORDER BY + LIMIT hanya boleh menyentuh kolom SEMPIT.
        // Root cause QueryException 1038 "Out of sort memory": MySQL mem-packing
        // seluruh baris yang di-sort ke dalam `sort_buffer_size` (256KB), sedangkan
        // `foto_unit` berisi base64 foto (terukur 431.406 byte/baris di db_staging)
        // → satu baris saja sudah melebihi sort buffer → SEMUA varian query list
        // (tanpa filter / status / search) gagal walau index sudah ada, karena
        // optimizer memilih table scan + filesort saat SELECT menyertakan kolom lebar.
        // [T-06 / P0-10] Scope cabang aktif sesi — WAJIB, papan kanban tidak boleh
        // menampilkan tiket cabang lain (pola `ServisController::index()`).
        $base = $this->queryTiketScoped();

        if ($this->filterStatus) {
            $base->where('status', $this->filterStatus);
        }

        if ($this->search) {
            $base->where(function ($q) {
                $q->where('no_tiket', 'like', "%{$this->search}%")
                    ->orWhere('jenis_hp', 'like', "%{$this->search}%")
                    ->orWhere('nama_pelanggan', 'like', "%{$this->search}%")
                    ->orWhere('telepon_pelanggan', 'like', "%{$this->search}%");
            });
        }

        // Tahap 1: hanya `id` (baris sempit) → sort muat di sort buffer,
        // dan dengan index `created_at` jadi covering index scan (tanpa filesort).
        // Manajemen Histori Transaksi:
        // - Jika staf mencari (barcode/keyword): cari ke semua status tiket
        // - Jika filter status aktif: ikuti filter status (+ periode jika 'diambil')
        // - Papan utama: proses kerja aktif (status != 'diambil') selalu ditampilkan penuh
        //   tanpa tenggelam oleh arsip lama, sedangkan kolom histori ('diambil') difilter
        //   berdasarkan periode yang dipilih (default 7 hari terakhir).
        if ($this->search) {
            $ids = (clone $base)->latest()->limit(100)->pluck('id');
        } elseif ($this->filterStatus) {
            if ($this->filterStatus === 'diambil') {
                $qHistori = clone $base;
                $this->applyPeriodeHistoriFilter($qHistori);
                $ids = $qHistori->latest()->limit(100)->pluck('id');
            } else {
                $ids = (clone $base)->latest()->limit(100)->pluck('id');
            }
        } else {
            $activeIds = (clone $base)
                ->where('status', '!=', 'diambil')
                ->latest()
                ->limit(150)
                ->pluck('id');

            $qHistori = (clone $base)->where('status', 'diambil');
            $this->applyPeriodeHistoriFilter($qHistori);
            $historyIds = $qHistori->latest()->limit(50)->pluck('id');

            $ids = $activeIds->merge($historyIds)->unique()->values();
        }

        // Tahap 2: hydrate row SEMPIT + eager load + withCount TANPA ORDER BY
        // (`whereIn` tidak memicu filesort), urutan dikembalikan di PHP.
        // [B-15c] select eksplisit → `foto_unit` (JSON 431KB/baris) TIDAK ikut ter-fetch.
        // WAJIB `select()` SEBELUM `withCount()`: withCount menambahkan subquery ke
        // daftar kolom yang sudah ada (kalau select dipanggil belakangan, subquery-nya
        // tertimpa → `spareparts_count` hilang).
        $hydrate = TiketServis::with(['jenisServis', 'pelanggan', 'teknisi', 'garansi'])
            ->select(self::KOLOM_KANBAN)
            ->withCount('spareparts')
            ->whereIn('id', $ids);

        // [T-06 / P0-10] Scope cabang juga dipasang di tahap-2 (defense in depth:
        // `whereIn` sudah berisi id dari query scoped, tapi tidak boleh jadi
        // satu-satunya penjaga). Ditambahkan SESUDAH `whereIn` agar bentuk SQL
        // query hydrate tetap `... where "id" in (...) ...` (kontrak regression
        // test B-07 / N+1).
        $semua = $this->scopeTiket($hydrate)
            ->get()
            ->sortByDesc('created_at')
            ->values();

        // Group per column status
        $grouped = [];
        foreach (array_keys(ServisStateMachine::kanbanColumns()) as $status) {
            $grouped[$status] = $semua->where('status', $status)->values();
        }

        return $grouped;
    }

    /**
     * Filter periode untuk kolom histori transaksi ('diambil').
     */
    private function applyPeriodeHistoriFilter(Builder $query): void
    {
        match ($this->periodeHistori) {
            'hari_ini' => $query->where(function (Builder $q) {
                $today = now()->toDateString();
                $q->where(fn ($sub) => $sub->whereNotNull('tanggal_diambil')->whereDate('tanggal_diambil', $today))
                    ->orWhere(fn ($sub) => $sub->whereNull('tanggal_diambil')->whereDate('updated_at', $today));
            }),
            '30_hari' => $query->where(function (Builder $q) {
                $since = now()->subDays(30)->startOfDay();
                $q->where(fn ($sub) => $sub->whereNotNull('tanggal_diambil')->where('tanggal_diambil', '>=', $since))
                    ->orWhere(fn ($sub) => $sub->whereNull('tanggal_diambil')->where('updated_at', '>=', $since));
            }),
            'semua' => null,
            default => $query->where(function (Builder $q) { // '7_hari' default
                $since = now()->subDays(7)->startOfDay();
                $q->where(fn ($sub) => $sub->whereNotNull('tanggal_diambil')->where('tanggal_diambil', '>=', $since))
                    ->orWhere(fn ($sub) => $sub->whereNull('tanggal_diambil')->where('updated_at', '>=', $since));
            }),
        };
    }

    /**
     * Antrean booking servis online yang menunggu konfirmasi cabang.
     */
    public function getBookingOnlineProperty()
    {
        // Dua tahap seperti kanban (B-07): ORDER BY created_at hanya di query id sempit
        $ids = $this->scopeTiket(
            TiketServis::where('status', 'diajukan_online')
        )->latest()->limit(50)->pluck('id');

        return TiketServis::with('pelanggan')
            ->select(self::KOLOM_KANBAN)
            ->whereIn('id', $ids)
            ->get()
            ->sortByDesc('created_at')
            ->values();
    }

    /**
     * Konfirmasi tiket dari booking online ke diterima.
     */
    public function konfirmasiBookingOnline(int $tiketId)
    {
        $tiket = $this->tiketScoped($tiketId);

        app(ServisService::class)->updateStatus(
            $tiket,
            'diterima',
            auth()->user(),
            'Dikonfirmasi staf dari booking online'
        );

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => "Tiket {$tiket->no_tiket} berhasil dikonfirmasi dan masuk ke status Diterima.",
        ]);
    }

    public function openIntakeBookingModal(int $tiketId): void
    {
        $tiket = $this->tiketScoped($tiketId);
        $this->intakeBookingTiketId = $tiketId;
        $this->intakeForm = [
            'tiket_id' => $tiket->id,
            'no_tiket' => $tiket->no_tiket,
            'nama_pelanggan' => $tiket->nama_pelanggan ?? $tiket->pelanggan?->nama ?? '',
            'telepon_pelanggan' => $tiket->telepon_pelanggan ?? $tiket->pelanggan?->telepon ?? '',
            'jenis_hp' => $tiket->jenis_hp,
            'seri_hp' => $tiket->seri_hp ?? '',
            'jenis_servis_id' => $tiket->jenis_servis_id,
            'keluhan' => $tiket->keluhan,
            'tipe_kunci' => $tiket->tipe_kunci ?? '',
            'kunci_terenkripsi' => $tiket->kunci_terenkripsi ?? '',
            'kondisi_fisik' => is_array($tiket->kondisi_fisik) ? $tiket->kondisi_fisik : [],
            'kelengkapan' => [],
            'teknisi_id' => $tiket->teknisi_id ?? auth()->id(),
            'catatan_intake' => '',
        ];
        $this->intakePhotoInputs = [null, null, null];
        $this->showIntakeBookingModal = true;
    }

    public function toggleIntakeKondisiFisik(string $check): void
    {
        $list = $this->intakeForm['kondisi_fisik'] ?? [];
        if (in_array($check, $list, true)) {
            $this->intakeForm['kondisi_fisik'] = array_values(array_diff($list, [$check]));
        } else {
            $this->intakeForm['kondisi_fisik'] = [...$list, $check];
        }
    }

    public function toggleIntakeKelengkapan(string $item): void
    {
        $list = $this->intakeForm['kelengkapan'] ?? [];
        if (in_array($item, $list, true)) {
            $this->intakeForm['kelengkapan'] = array_values(array_diff($list, [$item]));
        } else {
            $this->intakeForm['kelengkapan'] = [...$list, $item];
        }
    }

    public function handleIntakeFotoUpload(int $index, $content): void
    {
        if ($content) {
            $this->intakePhotoInputs[$index] = $content;
        }
    }

    public function removeIntakeFoto(int $index): void
    {
        $this->intakePhotoInputs[$index] = null;
    }

    public function simpanIntakeBooking(): void
    {
        if (! $this->boleh('servis.update-status', 'Anda tidak punya izin menerima unit servis')) {
            return;
        }

        $this->validate([
            'intakeForm.jenis_hp' => 'required|string|max:255',
            'intakeForm.keluhan' => 'required|string',
        ]);

        $tiket = $this->tiketScoped($this->intakeBookingTiketId);

        $foto = array_values(array_filter($this->intakePhotoInputs));

        $kondisiFisik = $this->intakeForm['kondisi_fisik'] ?? [];
        if (! empty($this->intakeForm['kelengkapan'])) {
            $kondisiFisik[] = 'Kelengkapan: '.implode(', ', $this->intakeForm['kelengkapan']);
        }

        $catatanPemeriksaan = trim((string) ($this->intakeForm['catatan_intake'] ?? ''));
        $kunciVal = $this->intakeForm['kunci_terenkripsi'] ?? '';
        if (is_array($kunciVal)) {
            $kunciVal = implode('-', $kunciVal);
        }

        $tiket->update([
            'jenis_hp' => $this->intakeForm['jenis_hp'],
            'seri_hp' => $this->intakeForm['seri_hp'] ?: null,
            'jenis_servis_id' => $this->intakeForm['jenis_servis_id'] ?: null,
            'teknisi_id' => $this->intakeForm['teknisi_id'] ?: null,
            'tipe_kunci' => $this->intakeForm['tipe_kunci'] ?: null,
            'kunci_terenkripsi' => $kunciVal ?: null,
            'kondisi_fisik' => $kondisiFisik ?: null,
            'foto_unit' => $foto ?: $tiket->foto_unit,
            'tanggal_terima' => now(),
            'catatan_admin' => $catatanPemeriksaan ?: $tiket->catatan_admin,
        ]);

        $alasanLog = 'Pemeriksaan fisik unit selesai, unit diterima oleh '.(auth()->user()?->name ?? 'staf');
        if ($catatanPemeriksaan) {
            $alasanLog .= " — Catatan: {$catatanPemeriksaan}";
        }

        app(ServisService::class)->updateStatus(
            $tiket,
            'diterima',
            auth()->user(),
            $alasanLog
        );

        $this->showIntakeBookingModal = false;
        $this->selectedTiketId = $tiket->id;
        $this->dispatch('alert', [
            'type' => 'success',
            'message' => "Unit fisik {$tiket->no_tiket} berhasil diperiksa & diterima di gerai.",
        ]);
    }

    // --- Foto handling (base64, opsional — [B-06] tanpa batas minimum) ---
    public function handleFotoUpload(int $index, $content)
    {
        if (! $content) {
            return;
        }

        // Data URL: data:image/png;base64,xxx
        $this->photoInputs[$index] = $content;
    }

    public function removeFoto(int $index)
    {
        $this->photoInputs[$index] = null;
    }

    public function getFotoCountProperty(): int
    {
        return count(array_filter($this->photoInputs));
    }

    public function toggleKondisiFisik(string $check)
    {
        $list = $this->terimaForm['kondisi_fisik'] ?? [];
        if (in_array($check, $list, true)) {
            $this->terimaForm['kondisi_fisik'] = array_values(array_diff($list, [$check]));
        } else {
            $this->terimaForm['kondisi_fisik'] = [...$list, $check];
        }
    }

    // --- Terima Unit ---
    public function openTerimaModal()
    {
        // [B-14] Route `/app/servis` hanya dijaga `permission:servis.view`; sejak
        // role non-teknisi (kasir/marketing) boleh punya permission itu, aksi
        // yang mengubah data WAJIB diguard sendiri di sini (paralel dengan
        // middleware API `permission:servis.create`).
        if (! $this->boleh('servis.create', 'Anda tidak punya izin menerima unit servis')) {
            return;
        }

        $this->terimaForm = [
            'pelanggan_id' => null,
            'nama_pelanggan' => '',
            'telepon_pelanggan' => '',
            'jenis_servis_id' => JenisServis::where('is_active', true)->first()?->id,
            'jenis_hp' => '',
            'seri_hp' => '',
            'tipe_kunci' => null,
            'kunci_terenkripsi' => '',
            'keluhan' => '',
            'kondisi_fisik' => [],
            'foto_unit' => [],
        ];
        $this->pelangganSearch = '';
        $this->photoInputs = [null, null, null];
        $this->terimaSertakanEstimasi = false;
        $this->terimaEstimasiItems = [];
        $this->terimaEstimasiBiaya = 0;
        $this->terimaEstimasiAlasan = '';
        $this->terimaEstimasiMode = 'menunggu_approval';
        $this->terimaEstimasiBatasWaktu = JenisServis::where('is_active', true)->first()?->batas_waktu_menit ?? 120;
        $this->terimaEstimasiAutoCancel = JenisServis::where('is_active', true)->first()?->auto_cancel_jika_overdue ?? false;
        $this->showTerimaModal = true;
    }

    /** [T-18] hasil pencarian pelanggan utk customer-picker */
    public function getPelangganCariServisProperty()
    {
        if (strlen($this->pelangganSearch) < 2) {
            return collect();
        }

        return Pelanggan::with('tierMembership')
            ->where(function ($q) {
                $q->where('nama', 'like', "%{$this->pelangganSearch}%")
                    ->orWhere('telepon', 'like', "%{$this->pelangganSearch}%");
            })
            ->limit(8)
            ->get();
    }

    /** pilih pelanggan dari picker (terima unit) */
    public function setPelangganServis(?int $id)
    {
        $this->terimaForm['pelanggan_id'] = $id;
        if ($id) {
            $p = Pelanggan::find($id);
            $this->terimaForm['nama_pelanggan'] = $p?->nama ?? '';
            $this->terimaForm['telepon_pelanggan'] = $p?->telepon ?? '';
        }
        $this->pelangganSearch = '';
    }

    /** [T-18] buka modal quick-add pelanggan baru dari terima unit — satu sumber (CRM-06 path) */
    public function openPelangganBaruServis()
    {
        $this->pelangganBaruForm = ['nama' => '', 'telepon' => '', 'alamat' => ''];
        $this->showPelangganBaruModal = true;
    }

    /** [T-18] simpan pelanggan baru via PelangganService (sama dgn CRM-06 / POS) → langsung dipilih */
    public function simpanPelangganBaruServis()
    {
        $this->validate([
            'pelangganBaruForm.nama' => 'required|string|max:255',
            'pelangganBaruForm.telepon' => 'required|string|max:20|unique:pelanggan,telepon',
        ]);

        try {
            $pelanggan = app(PelangganService::class)->create([
                'nama' => $this->pelangganBaruForm['nama'],
                'telepon' => $this->pelangganBaruForm['telepon'],
                'alamat' => $this->pelangganBaruForm['alamat'] ?: null,
            ]);
            $this->setPelangganServis($pelanggan->id);
            $this->showPelangganBaruModal = false;
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Pelanggan baru disimpan & dipilih (sinkron CRM)']);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function simpanTerima()
    {
        if (! $this->boleh('servis.create', 'Anda tidak punya izin menerima unit servis')) {
            return;
        }

        $this->validate([
            'terimaForm.jenis_hp' => 'required|string|max:255',
            'terimaForm.keluhan' => 'required|string',
            'terimaForm.nama_pelanggan' => 'required_if:terimaForm.pelanggan_id,',
            'terimaForm.telepon_pelanggan' => 'nullable|string|max:20',
        ]);

        $foto = array_values(array_filter($this->photoInputs));

        $data = $this->terimaForm;
        // [B-06] foto unit opsional — boleh 0, submit tidak bergantung pada foto
        $data['foto_unit'] = $foto ?: null;

        // [T-19] pola kunci → simpan urutan angka, bukan objek
        if (is_array($data['kunci_terenkripsi'])) {
            $data['kunci_terenkripsi'] = implode('-', $data['kunci_terenkripsi']);
        }

        try {
            $tiket = app(ServisService::class)->terimaUnit($data, auth()->user());

            // Jika estimasi langsung disertakan di form terima unit
            if ($this->terimaSertakanEstimasi && ! empty($this->terimaEstimasiItems)) {
                $cleanedItems = [];
                foreach ($this->terimaEstimasiItems as $it) {
                    $tipe = ($it['tipe'] ?? 'jasa') === 'part' ? 'part' : 'jasa';
                    $qty = max(1, (int) ($it['qty'] ?? 1));
                    $harga = max(0, $this->parseNominal($it['harga'] ?? 0));
                    $nama = trim((string) ($it['nama_item'] ?? ''));

                    if ($nama === '' && empty($it['produk_id']) && empty($it['jenis_servis_id']) && $harga <= 0) {
                        continue;
                    }

                    $cleanedItems[] = [
                        'tipe' => $tipe,
                        'jenis_servis_id' => ! empty($it['jenis_servis_id']) ? (int) $it['jenis_servis_id'] : null,
                        'produk_id' => ! empty($it['produk_id']) ? (int) $it['produk_id'] : null,
                        'sku_variant_id' => ! empty($it['sku_variant_id']) ? (int) $it['sku_variant_id'] : null,
                        'nama_item' => $nama ?: ($tipe === 'part' ? 'Sparepart' : 'Jasa Servis'),
                        'qty' => $qty,
                        'harga' => $harga,
                        'subtotal' => $qty * $harga,
                    ];
                }

                if (! empty($cleanedItems)) {
                    $alasanEst = trim($this->terimaEstimasiAlasan) ?: 'Estimasi biaya awal saat terima unit fisik';

                    // Transisi flow normal state machine: terima (diterima) -> diagnosa -> setEstimasi (menunggu_approval)
                    $tiket = app(ServisService::class)->updateStatus(
                        $tiket,
                        'diagnosa',
                        auth()->user(),
                        'Mulai pemeriksaan diagnosa unit saat penerimaan'
                    );

                    $tiket = app(ServisService::class)->setEstimasi(
                        $tiket,
                        (float) $this->terimaEstimasiBiaya,
                        $alasanEst,
                        auth()->user(),
                        $cleanedItems,
                        $this->terimaEstimasiBatasWaktu,
                        $this->terimaEstimasiAutoCancel
                    );

                    // Jika konsumen walk-in langsung setuju di tempat (misal ganti baterai/LCD langsung sepakat)
                    if ($this->terimaEstimasiMode === 'disetujui') {
                        $tiket = app(ServisService::class)->updateStatus(
                            $tiket,
                            'disetujui',
                            auth()->user(),
                            'Estimasi biaya langsung disetujui konsumen saat penerimaan unit di konter'
                        );
                    }
                }
            }

            $this->showTerimaModal = false;
            $this->selectedTiketId = $tiket->id;
            $msg = $this->terimaSertakanEstimasi
                ? "Unit diterima & estimasi awal tersimpan ({$tiket->status}) — {$tiket->no_tiket}"
                : "Unit diterima — {$tiket->no_tiket}";
            $this->dispatch('alert', ['type' => 'success', 'message' => $msg]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // --- Status update (state machine) ---
    public function updateStatus(int $tiketId, string $statusBaru, string $alasan = '')
    {
        // [B-14] Guard permission level Livewire (route hanya `servis.view`).
        // Override Mundur tetap butuh `servis.override-status` (dicek di
        // ServisService::updateStatus).
        if (! $this->boleh('servis.update-status', 'Anda tidak punya izin mengubah status tiket servis')) {
            return;
        }

        $tiket = $this->tiketScoped($tiketId);

        try {
            $tiket = app(ServisService::class)->updateStatus($tiket, $statusBaru, auth()->user(), $alasan);
            $this->dispatch('alert', ['type' => 'success', 'message' => "Status → {$statusBaru}"]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // --- Estimasi ---
    public function estimasiRowBaru(string $tipe = 'jasa'): array
    {
        return [
            'tipe' => $tipe,
            'jenis_servis_id' => null,
            'produk_id' => null,
            'nama_item' => '',
            'qty' => 1,
            'harga' => 0,
            'subtotal' => 0,
        ];
    }

    public function addEstimasiRow(string $tipe = 'jasa'): void
    {
        $this->estimasiItems[] = $this->estimasiRowBaru($tipe);
        $this->recalculateEstimasiTotal();
    }

    public function removeEstimasiRow(int $idx): void
    {
        unset($this->estimasiItems[$idx]);
        $this->estimasiItems = array_values($this->estimasiItems);
        $this->recalculateEstimasiTotal();
    }

    // --- Terima Estimasi Langsung Helpers ---
    public function toggleTerimaEstimasi(): void
    {
        $this->terimaSertakanEstimasi = ! $this->terimaSertakanEstimasi;
        if ($this->terimaSertakanEstimasi && empty($this->terimaEstimasiItems)) {
            $defaultJenisId = $this->terimaForm['jenis_servis_id'] ?: JenisServis::where('is_active', true)->first()?->id;
            $defaultJenis = $defaultJenisId ? JenisServis::find($defaultJenisId) : null;
            $hargaDefault = (float) ($defaultJenis?->biaya_jasa ?? 0);

            $this->terimaEstimasiItems = [[
                'tipe' => 'jasa',
                'jenis_servis_id' => $defaultJenis?->id,
                'produk_id' => null,
                'nama_item' => $defaultJenis?->nama ?? 'Jasa Servis',
                'qty' => 1,
                'harga' => $hargaDefault,
                'subtotal' => $hargaDefault,
            ]];
            $this->terimaEstimasiBiaya = $hargaDefault;
        }
    }

    public function addTerimaEstimasiRow(string $tipe = 'jasa'): void
    {
        $this->terimaEstimasiItems[] = $this->estimasiRowBaru($tipe);
        $this->recalculateTerimaEstimasiTotal();
    }

    public function removeTerimaEstimasiRow(int $idx): void
    {
        unset($this->terimaEstimasiItems[$idx]);
        $this->terimaEstimasiItems = array_values($this->terimaEstimasiItems);
        $this->recalculateTerimaEstimasiTotal();
    }

    public function updatedTerimaEstimasiItems($value, $key): void
    {
        $parts = explode('.', (string) $key);
        $idx = (int) ($parts[0] ?? 0);
        $field = $parts[1] ?? '';

        if (! isset($this->terimaEstimasiItems[$idx])) {
            return;
        }

        $row = &$this->terimaEstimasiItems[$idx];

        if ($field === 'harga') {
            if ($this->canEditHargaPart || ($row['tipe'] ?? 'jasa') !== 'part') {
                $row['harga'] = $this->parseNominal($value);
            } else {
                if (! empty($row['produk_id'])) {
                    $produk = Produk::find($row['produk_id']);
                    $pelanggan = ! empty($this->terimaForm['pelanggan_id']) ? Pelanggan::find($this->terimaForm['pelanggan_id']) : null;
                    $resolved = $produk ? app(ServisService::class)->resolveHargaPartDefault($produk, $pelanggan) : null;
                    $row['harga'] = (float) ($resolved['harga'] ?? $produk?->harga_jual_retail ?? $produk?->harga_jual ?? 0);
                }
            }
        } elseif ($field === 'produk_id' && ($row['tipe'] ?? '') === 'part') {
            if ($value) {
                $produk = Produk::find($value);
                if ($produk) {
                    $row['nama_item'] = $produk->nama;
                    $pelanggan = ! empty($this->terimaForm['pelanggan_id']) ? Pelanggan::find($this->terimaForm['pelanggan_id']) : null;
                    $resolved = app(ServisService::class)->resolveHargaPartDefault($produk, $pelanggan);
                    $row['harga'] = (float) ($resolved['harga'] ?? $produk->harga_jual_retail ?? $produk->harga_jual ?? 0);
                }
            } else {
                $row['nama_item'] = '';
                $row['harga'] = 0;
            }
        } elseif ($field === 'jenis_servis_id' && ($row['tipe'] ?? '') === 'jasa') {
            if ($value) {
                $jenis = JenisServis::find($value);
                if ($jenis) {
                    $row['nama_item'] = $jenis->nama;
                    $row['harga'] = (float) ($jenis->biaya_jasa ?? 0);
                }
            } else {
                $row['nama_item'] = '';
                $row['harga'] = 0;
            }
        }

        $this->recalculateTerimaEstimasiTotal();
    }

    private function recalculateTerimaEstimasiTotal(): void
    {
        foreach ($this->terimaEstimasiItems as $i => $row) {
            $qty = max(1, (int) ($row['qty'] ?? 1));
            $harga = $this->parseNominal($row['harga'] ?? 0);
            $this->terimaEstimasiItems[$i]['qty'] = $qty;
            $this->terimaEstimasiItems[$i]['harga'] = $harga;
            $this->terimaEstimasiItems[$i]['subtotal'] = $qty * $harga;
        }

        $this->terimaEstimasiBiaya = (float) array_sum(array_column($this->terimaEstimasiItems, 'subtotal'));
    }

    public function updatedEstimasiItems($value, $key): void
    {
        $parts = explode('.', (string) $key);
        $idx = (int) ($parts[0] ?? 0);
        $field = $parts[1] ?? '';

        if (! isset($this->estimasiItems[$idx])) {
            return;
        }

        $row = &$this->estimasiItems[$idx];

        if ($field === 'harga') {
            if ($this->canEditHargaPart || ($row['tipe'] ?? 'jasa') !== 'part') {
                $row['harga'] = $this->parseNominal($value);
            } else {
                if (! empty($row['produk_id'])) {
                    $produk = Produk::find($row['produk_id']);
                    $tiket = $this->estimasiTiketId ? $this->tiketScopedOrNull($this->estimasiTiketId) : null;
                    $pelanggan = $tiket?->pelanggan;
                    $resolved = $produk ? app(ServisService::class)->resolveHargaPartDefault($produk, $pelanggan) : null;
                    $row['harga'] = (float) ($resolved['harga'] ?? $produk?->harga_jual_retail ?? $produk?->harga_jual ?? 0);
                }
            }
        } elseif ($field === 'produk_id' && ($row['tipe'] ?? '') === 'part') {
            if ($value) {
                $produk = Produk::find($value);
                if ($produk) {
                    $row['nama_item'] = $produk->nama;
                    $tiket = $this->estimasiTiketId ? $this->tiketScopedOrNull($this->estimasiTiketId) : null;
                    $pelanggan = $tiket?->pelanggan;
                    $resolved = app(ServisService::class)->resolveHargaPartDefault($produk, $pelanggan);
                    $row['harga'] = (float) ($resolved['harga'] ?? $produk->harga_jual_retail ?? $produk->harga_jual ?? 0);
                }
            } else {
                $row['nama_item'] = '';
                $row['harga'] = 0;
            }
        } elseif ($field === 'jenis_servis_id' && ($row['tipe'] ?? '') === 'jasa') {
            if ($value) {
                $jenis = JenisServis::find($value);
                if ($jenis) {
                    $row['nama_item'] = $jenis->nama;
                    $row['harga'] = (float) ($jenis->biaya_jasa ?? 0);
                }
            } else {
                $row['nama_item'] = '';
                $row['harga'] = 0;
            }
        }

        $this->recalculateEstimasiTotal();
    }

    private function recalculateEstimasiTotal(): void
    {
        foreach ($this->estimasiItems as $i => $row) {
            $qty = max(1, (int) ($row['qty'] ?? 1));
            $harga = $this->parseNominal($row['harga'] ?? 0);
            $this->estimasiItems[$i]['qty'] = $qty;
            $this->estimasiItems[$i]['harga'] = $harga;
            $this->estimasiItems[$i]['subtotal'] = $qty * $harga;
        }

        $this->estimasiBiaya = (float) array_sum(array_column($this->estimasiItems, 'subtotal'));
    }

    public function openEstimasiModal(int $tiketId)
    {
        $tiket = $this->tiketScoped($tiketId);
        $this->estimasiTiketId = $tiketId;
        $this->estimasiAlasan = $tiket->alasan_estimasi ?: '';
        $this->estimasiBatasWaktuMenit = $tiket->batas_waktu_menit ?: ($tiket->jenisServis?->batas_waktu_menit ?? 120);
        $this->estimasiAutoCancelOverdue = $tiket->auto_cancel_overdue ?: ($tiket->jenisServis?->auto_cancel_jika_overdue ?? false);
        $this->estimasiDurasiGaransiCustom = $tiket->durasi_garansi_custom !== null ? (int) $tiket->durasi_garansi_custom : null;

        $existingItems = $tiket->estimasiItems()->get();
        if ($existingItems->isNotEmpty()) {
            $this->estimasiItems = $existingItems->map(fn ($it) => [
                'tipe' => $it->tipe,
                'jenis_servis_id' => $it->jenis_servis_id,
                'produk_id' => $it->produk_id,
                'nama_item' => $it->nama_item,
                'qty' => $it->qty,
                'harga' => (float) $it->harga,
                'subtotal' => (float) $it->subtotal,
            ])->toArray();
            $this->estimasiBiaya = (float) array_sum(array_column($this->estimasiItems, 'subtotal'));
        } else {
            $defaultJenisId = $tiket->jenis_servis_id ?: JenisServis::where('is_active', true)->first()?->id;
            $defaultJenis = $defaultJenisId ? JenisServis::find($defaultJenisId) : null;
            $hargaDefault = (float) ($defaultJenis?->biaya_jasa ?? 0);

            $this->estimasiItems = [[
                'tipe' => 'jasa',
                'jenis_servis_id' => $defaultJenis?->id,
                'produk_id' => null,
                'nama_item' => $defaultJenis?->nama ?? 'Jasa Servis',
                'qty' => 1,
                'harga' => $hargaDefault,
                'subtotal' => $hargaDefault,
            ]];
            $this->estimasiBiaya = $hargaDefault ?: (float) ($tiket->estimasi_biaya ?? 0);
        }

        $this->showEstimasiModal = true;
    }

    public function simpanEstimasi()
    {
        // [B-14] Sama dgn API [SERVICE-05] → `permission:servis.update-status`.
        if (! $this->boleh('servis.update-status', 'Anda tidak punya izin input estimasi biaya servis')) {
            return;
        }

        if (! $this->estimasiTiketId) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Tiket servis tidak valid']);

            return;
        }

        $this->recalculateEstimasiTotal();

        $alasan = trim((string) $this->estimasiAlasan);
        if ($alasan === '') {
            $alasan = 'Estimasi perbaikan unit';
        }

        $cleanedItems = [];
        foreach ($this->estimasiItems as $it) {
            $tipe = ($it['tipe'] ?? 'jasa') === 'part' ? 'part' : 'jasa';
            $qty = max(1, (int) ($it['qty'] ?? 1));
            $harga = max(0, $this->parseNominal($it['harga'] ?? 0));
            $nama = trim((string) ($it['nama_item'] ?? ''));

            if ($nama === '' && empty($it['produk_id']) && empty($it['jenis_servis_id']) && $harga <= 0) {
                continue;
            }

            $cleanedItems[] = [
                'tipe' => $tipe,
                'jenis_servis_id' => ! empty($it['jenis_servis_id']) ? (int) $it['jenis_servis_id'] : null,
                'produk_id' => ! empty($it['produk_id']) ? (int) $it['produk_id'] : null,
                'sku_variant_id' => ! empty($it['sku_variant_id']) ? (int) $it['sku_variant_id'] : null,
                'nama_item' => $nama ?: ($tipe === 'part' ? 'Sparepart' : 'Jasa Servis'),
                'qty' => $qty,
                'harga' => $harga,
                'subtotal' => $qty * $harga,
            ];
        }

        if (empty($cleanedItems)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Minimal tambahkan 1 baris item estimasi']);

            return;
        }

        $tiket = $this->tiketScoped($this->estimasiTiketId);

        try {
            $tiket = app(ServisService::class)->setEstimasi(
                $tiket,
                (float) $this->estimasiBiaya,
                $alasan,
                auth()->user(),
                $cleanedItems,
                $this->estimasiBatasWaktuMenit,
                $this->estimasiAutoCancelOverdue,
                $this->estimasiDurasiGaransiCustom
            );
            $this->showEstimasiModal = false;
            $this->selectedTiketId = $tiket->id;
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Estimasi tersimpan, menunggu approval pelanggan']);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // --- Progress Pengerjaan Teknisi ---
    public function openProgressModal(int $tiketId): void
    {
        $tiket = $this->tiketScoped($tiketId);
        $this->progressTiketId = $tiketId;
        $this->progressPersentase = 50;
        $this->progressJudul = '';
        $this->progressDeskripsi = '';
        $this->progressTampilkanKonsumen = true;
        $this->progressFoto = [];
        $this->showProgressModal = true;
    }

    public function handleProgressFotoUpload($content): void
    {
        if ($content) {
            $this->progressFoto[] = $content;
        }
    }

    public function removeProgressFoto(int $index): void
    {
        unset($this->progressFoto[$index]);
        $this->progressFoto = array_values($this->progressFoto);
    }

    public function simpanProgress(): void
    {
        if (! $this->boleh('servis.update-status', 'Anda tidak punya izin mencatat progress servis')) {
            return;
        }

        if (! $this->progressTiketId) {
            return;
        }

        $tiket = $this->tiketScoped($this->progressTiketId);

        $this->validate([
            'progressPersentase' => 'required|integer|min:0|max:100',
            'progressJudul' => 'required|string|min:3|max:100',
            'progressDeskripsi' => 'nullable|string|max:500',
        ]);

        try {
            app(ServisService::class)->catatProgress(
                $tiket,
                auth()->user(),
                $this->progressPersentase,
                $this->progressJudul,
                $this->progressDeskripsi,
                $this->progressFoto,
                $this->progressTampilkanKonsumen
            );

            $this->showProgressModal = false;
            $this->selectedTiketId = $tiket->id;
            $this->dispatch('alert', ['type' => 'success', 'message' => "Progress {$this->progressPersentase}% berhasil disimpan & diunggah"]);
        } catch (\Throwable $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // --- Approve / Reject (admin, dengan alasan) ---
    public function openApproveModal(int $tiketId)
    {
        $this->approveTiketId = $tiketId;
        $this->approveAlasan = '';
        $this->showApproveModal = true;
    }

    public function prosesApprove(string $action)
    {
        if (! $this->boleh('servis.approve-estimasi', 'Anda tidak punya izin menyetujui/menolak estimasi servis')) {
            return;
        }

        $tiket = $this->tiketScoped($this->approveTiketId);
        $statusBaru = $action === 'approve' ? 'disetujui' : 'ditolak';

        $this->updateStatus($tiket->id, $statusBaru, $this->approveAlasan ?: ($action === 'approve' ? 'Pelanggan menyetujui estimasi' : 'Estimasi ditolak'));
        $this->showApproveModal = false;
    }

    public function openBatalReturModal(int $tiketId): void
    {
        $tiket = $this->tiketScoped($tiketId);
        $this->batalReturTiketId = $tiketId;
        $this->batalReturCatatan = 'Servis dibatalkan setelah estimasi ditolak. Unit dirakit kembali dan diserahkan kepada konsumen.';
        $this->batalReturBiaya = 0;
        $this->batalReturMetode = 'tunai';
        $this->showBatalReturModal = true;
    }

    public function prosesBatalRetur(): void
    {
        if (! $this->boleh('servis.update-status', 'Anda tidak punya izin mengubah status tiket servis')) {
            return;
        }

        $tiket = $this->tiketScoped($this->batalReturTiketId);

        $alasan = trim($this->batalReturCatatan) ?: 'Unit diserahkan kembali ke pelanggan tanpa perbaikan';

        try {
            app(ServisService::class)->updateStatus($tiket, 'diambil', auth()->user(), $alasan);
            $this->showBatalReturModal = false;
            $this->selectedTiketId = $tiket->id;
            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Servis ditutup: Unit {$tiket->no_tiket} telah diserahkan kembali kepada pelanggan.",
            ]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function revisiEstimasiDitolak(int $tiketId): void
    {
        if (! $this->boleh('servis.update-status', 'Anda tidak punya izin mengubah status tiket servis')) {
            return;
        }

        $tiket = $this->tiketScoped($tiketId);

        try {
            app(ServisService::class)->updateStatus($tiket, 'diagnosa', auth()->user(), 'Revisi estimasi perbaikan setelah penolakan pelanggan');
            $this->openEstimasiModal($tiketId);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // --- Pembayaran Servis ---
    public function openBayarModal(int $tiketId): void
    {
        $tiket = $this->tiketScoped($tiketId);

        if (! in_array($tiket->status, ['selesai', 'diambil'], true)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Pembayaran hanya dapat dilakukan saat unit sudah selesai atau diambil']);

            return;
        }

        if ($tiket->status_pembayaran === 'lunas') {
            $this->dispatch('alert', ['type' => 'info', 'message' => 'Tiket servis ini sudah lunas']);

            return;
        }

        $rincian = $tiket->getRincianBiayaLengkap();

        $this->bayarTotalTagihan = $rincian['total'];
        $this->bayarTiketId = $tiketId;
        $this->bayarMetode = 'tunai';
        $this->bayarCatatan = '';
        $this->showBayarModal = true;
    }

    public function prosesBayar(): void
    {
        if (! $this->boleh('servis.update-status', 'Anda tidak punya izin memproses pembayaran servis')) {
            return;
        }

        $tiket = $this->tiketScoped($this->bayarTiketId);

        try {
            app(ServisService::class)->bayar($tiket, $this->bayarMetode, auth()->user(), $this->bayarCatatan);
            $this->showBayarModal = false;
            $this->dispatch('alert', ['type' => 'success', 'message' => "Pembayaran servis {$tiket->no_tiket} berhasil dicatat (Lunas)."]);
            $this->bukaStrukServis($tiket->id);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function bukaStrukServis(int $tiketId): void
    {
        $tiket = $this->tiketScopedOrNull($tiketId);
        if (! $tiket) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Tiket servis tidak ditemukan']);

            return;
        }

        $rincian = $tiket->getRincianBiayaLengkap();
        $cabang = $tiket->cabang;

        $this->receiptData = [
            'no_transaksi' => $tiket->transaksi?->no_transaksi ?? $tiket->no_tiket,
            'no_tiket' => $tiket->no_tiket,
            'jenis_hp' => $tiket->jenis_hp,
            'seri_hp' => $tiket->seri_hp,
            'waktu' => ($tiket->tanggal_bayar ?? $tiket->tanggal_terima ?? now())->format('d/m/Y H:i'),
            'kasir' => auth()->user()?->name ?? 'Kasir / Staf',
            'pelanggan' => $tiket->nama_pelanggan ?? $tiket->pelanggan?->nama ?? 'Pelanggan Umum',
            'telepon' => $tiket->telepon_pelanggan ?? $tiket->pelanggan?->telepon ?? null,
            'tier' => 'Servis HP ('.$tiket->jenis_hp.')',
            'items' => $rincian['items'],
            'subtotal_jasa' => $rincian['total_jasa'],
            'subtotal_part' => $rincian['total_part'],
            'subtotal' => $rincian['total'],
            'diskon' => 0,
            'dpp' => $rincian['total'],
            'pajak' => 0,
            'ppn_persen' => 0,
            'total' => $rincian['total'],
            'bayar' => $rincian['total'],
            'kembali' => 0,
            'metode' => strtoupper($tiket->metode_pembayaran ?? 'TUNAI'),
            'status_pembayaran' => $tiket->status_pembayaran ?? 'belum_bayar',
            'garansi' => $tiket->garansi ? ($tiket->garansi->durasi_hari.' Hari (s/d '.$tiket->garansi->tanggal_berakhir?->format('d/m/Y').')') : null,
        ];

        $this->showReceiptModal = true;
    }

    // --- Drag & drop ---
    public function dropTicket(int $tiketId, string $statusTujuan)
    {
        // Simulate: drop = update status ke kolom tujuan (state machine akan validasi)
        $this->updateStatus($tiketId, $statusTujuan);
    }

    // --- Detail ---
    public function openDetail(int $tiketId)
    {
        // [T-06 / P0-10] Tiket harus milik cabang aktif sesi — kalau tidak, detail
        // (termasuk kunci gadget terenkripsi) tidak boleh dibuka sama sekali.
        if (! $this->tiketScopedOrNull($tiketId)) {
            $this->selectedTiketId = null;
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Tiket servis tidak ditemukan di cabang aktif']);

            return;
        }

        $this->selectedTiketId = $tiketId;
        $this->bukaKunciGadget = false; // [B-06] selalu mulai ter-mask

        $tiket = $this->tiketScopedOrNull($tiketId);

        // [T-09d] Prefill ke input pekerjaan teknisi dari estimasiItems jika ada
        $estimasiItems = $tiket?->estimasiItems()->get();
        if ($estimasiItems && $estimasiItems->isNotEmpty()) {
            $this->pekerjaanItems = $estimasiItems->map(fn ($it) => [
                'tipe' => $it->tipe,
                'produk_id' => $it->produk_id,
                'nama_item' => $it->nama_item,
                'qty' => $it->qty,
                'harga' => (float) $it->harga,
                'gudang_id' => null,
                'sn' => '',
            ])->toArray();
        } else {
            // [T-17] init satu baris kosong
            $this->pekerjaanItems = [$this->pekerjaanRowBaru()];
        }

        if (! $this->pekerjaanGudangId) {
            $cabangId = session('cabang_id');
            $this->pekerjaanGudangId = $cabangId
                ? Gudang::where('cabang_id', $cabangId)->where('is_active', true)->value('id')
                : Gudang::where('is_active', true)->value('id');
        }
    }

    /** [B-06] Toggle tampil/sembunyi kunci gadget (sensitif) di modal detail */
    public function toggleKunciGadget(): void
    {
        $this->bukaKunciGadget = ! $this->bukaKunciGadget;
    }

    private function pekerjaanRowBaru(): array
    {
        return [
            'tipe' => 'jasa',
            'produk_id' => null,
            'nama_item' => '',
            'qty' => 1,
            'harga' => 0,
            'gudang_id' => null,
            'sn' => '', // [F2-3] daftar SN utk produk sn=true (newline/koma)
        ];
    }

    public function addPekerjaanRow()
    {
        $this->pekerjaanItems[] = $this->pekerjaanRowBaru();
    }

    public function removePekerjaanRow(int $idx)
    {
        unset($this->pekerjaanItems[$idx]);
        $this->pekerjaanItems = array_values($this->pekerjaanItems);
    }

    public function updatedPekerjaanItems($value, $key): void
    {
        $parts = explode('.', (string) $key);
        $idx = (int) ($parts[0] ?? 0);
        $field = $parts[1] ?? '';

        if (! isset($this->pekerjaanItems[$idx])) {
            return;
        }

        $row = &$this->pekerjaanItems[$idx];

        if ($field === 'harga') {
            if ($this->canEditHargaPart || ($row['tipe'] ?? 'jasa') !== 'part') {
                $row['harga'] = $this->parseNominal($value);
            } else {
                if (! empty($row['produk_id'])) {
                    $produk = Produk::find($row['produk_id']);
                    $tiket = $this->selectedTiketId ? $this->tiketScopedOrNull($this->selectedTiketId) : null;
                    $pelanggan = $tiket?->pelanggan;
                    $resolved = $produk ? app(ServisService::class)->resolveHargaPartDefault($produk, $pelanggan) : null;
                    $row['harga'] = (float) ($resolved['harga'] ?? $produk?->harga_jual_retail ?? $produk?->harga_jual ?? 0);
                }
            }
        } elseif ($field === 'produk_id' && ($row['tipe'] ?? '') === 'part') {
            if ($value) {
                $produk = Produk::find($value);
                if ($produk) {
                    $row['nama_item'] = $produk->nama;
                    $tiket = $this->selectedTiketId ? $this->tiketScopedOrNull($this->selectedTiketId) : null;
                    $pelanggan = $tiket?->pelanggan;
                    $resolved = app(ServisService::class)->resolveHargaPartDefault($produk, $pelanggan);
                    $row['harga'] = (float) ($resolved['harga'] ?? $produk->harga_jual_retail ?? $produk->harga_jual ?? 0);
                }
            }
        }
    }

    /** [T-17] Simpan item pekerjaan → ServisService::inputPekerjaan (part: stok 1x, jasa: tagihan) */
    public function simpanPekerjaan()
    {
        $tiket = $this->tiketScoped($this->selectedTiketId);

        if (! $this->boleh('servis.input-sparepart', 'Anda tidak punya izin input sparepart/pekerjaan')) {
            return;
        }

        $items = [];
        foreach ($this->pekerjaanItems as $row) {
            $tipe = ($row['tipe'] ?? 'jasa') === 'part' ? 'part' : 'jasa';
            $produkId = $tipe === 'part' ? ($row['produk_id'] ?? null) : null;

            $namaItem = trim($row['nama_item'] ?? '');
            if ($tipe === 'part' && $produkId && $namaItem === '') {
                $namaItem = (string) (Produk::find($produkId)?->nama ?? '');
            }
            if ($namaItem === '') {
                continue; // baris kosong dilewati
            }

            $items[] = [
                'tipe' => $tipe,
                'produk_id' => $produkId,
                'nama_item' => $namaItem,
                'qty' => max(1, (int) ($row['qty'] ?? 1)),
                'harga' => max(0, $this->parseNominal($row['harga'] ?? 0)),
                'gudang_id' => $tipe === 'part' ? ($row['gudang_id'] ?? $this->pekerjaanGudangId) : null,
                // [F2-3] SN utk produk sn=true — divalidasi di ServisService::inputPekerjaan
                'sn' => app(NomorSeriService::class)->parseList((string) ($row['sn'] ?? '')),
            ];
        }

        if (count($items) === 0) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Tambah minimal 1 baris item pekerjaan (isi nama item)']);

            return;
        }

        try {
            app(ServisService::class)->inputPekerjaan($tiket, $items, auth()->user());
            $this->pekerjaanItems = [$this->pekerjaanRowBaru()];
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Item pekerjaan dicatat — part: stok berkurang 1x, jasa: tagihan']);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function getSelectedTiketProperty(): ?TiketServis
    {
        // [T-06 / P0-10] Scope cabang aktif sesi (bukan `TiketServis::find()` polos).
        return $this->selectedTiketId
            ? $this->queryTiketScoped()
                ->with([
                    'jenisServis', 'pelanggan.tierMembership', 'teknisi', 'garansi',
                    'statusLogs.user', 'spareparts.produk', 'spareparts.skuVariant', 'cabang',
                    'items', // [T-17]
                    'estimasiItems',
                ])->find($this->selectedTiketId)
            : null;
    }

    /**
     * [B-14] Guard permission server-side utk aksi mutasi di papan kanban.
     * Route `/app/servis` hanya dijaga `permission:servis.view`; sekarang role
     * non-teknisi (kasir/marketing) juga punya permission itu → tanpa guard ini
     * mereka bisa membuat tiket / ubah status lewat Livewire, padahal hak tsb
     * (servis.create, servis.update-status) memang TIDAK diberikan.
     *
     * @return bool true = boleh lanjut; false = sudah dispatch alert ke user.
     */
    private function boleh(string $permission, string $pesan): bool
    {
        if (auth()->user()?->can($permission)) {
            return true;
        }

        $this->dispatch('alert', ['type' => 'error', 'message' => $pesan]);

        return false;
    }

    public function getCanEditHargaPartProperty(): bool
    {
        return isSuperAdminOrOwner();
    }

    public function getProdukEstimasiListProperty()
    {
        // Produk: tidak perlu lagi load semua produk ke dropdown.
        // Pencarian produk global via ProductSearchModal.
        return collect();
    }

    public function render()
    {
        return view('modules.servis.livewire.servis-board', [
            'jenisServisList' => JenisServis::where('is_active', true)->get(),
            'pelangganList' => Pelanggan::with('tierMembership')->limit(10)->get(),
            'teknisiList' => User::role(['teknisi', 'admin-toko', 'super-admin'])->get(),
            'gudangList' => Gudang::where('is_active', true)->get(),
            'produkList' => $this->produkEstimasiList,
            'produkEstimasiList' => $this->produkEstimasiList,
            'stateMachineColumns' => $this->stateMachineColumns,
            'groupedTikets' => $this->groupedTikets,
            'selectedTiket' => $this->selectedTiket,
            'fotoCount' => $this->fotoCount,
            'canEditHargaPart' => $this->canEditHargaPart,
            'pelangganCariServis' => $this->pelangganCariServis,
            'bookingOnline' => $this->bookingOnline,
            'cariPartResults' => $this->cariPartResults,
        ])->layout('layouts.backoffice', ['header' => 'Servis HP — Papan Kanban']);
    }
}
