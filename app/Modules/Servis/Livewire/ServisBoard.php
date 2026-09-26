<?php

namespace App\Modules\Servis\Livewire;

use App\Models\User;
use App\Modules\Akunting\Jobs\ExportLaporanJob;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Services\PelangganService;
use App\Modules\Servis\Models\JenisServis;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Services\ServisService;
use App\Modules\Servis\Services\ServisStateMachine;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Services\NomorSeriService;
use Livewire\Component;
use Livewire\WithPagination;

class ServisBoard extends Component
{
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
        'estimasi_biaya',
        'created_at',
    ];

    // Search & filter
    public string $search = '';

    public string $filterStatus = ''; // empty = all

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

    // Drag & drop target status
    public ?string $dropTargetStatus = null;

    public ?int $dropTiketId = null;

    // [T-17] Form pekerjaan teknisi (item part/jasa) di modal detail
    public array $pekerjaanItems = [];

    public ?int $pekerjaanGudangId = null;

    public function mount()
    {
        $this->photoInputs = [null, null, null]; // max 3 foto
    }

    /** [F2-5] Export laporan servis via queue (async — jangan sinkron di request). */
    public function exportLaporan(string $format = 'xlsx'): void
    {
        if (! auth()->user()?->can('laporan.cabang')) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda tidak punya izin export laporan']);

            return;
        }

        dispatch(new ExportLaporanJob(
            jenis: 'servis',
            periodeDari: null,
            periodeSampai: null,
            cabangId: session('cabang_id'),
            akunId: null,
            userId: auth()->id(),
            format: $format === 'csv' ? 'csv' : 'xlsx',
        ));

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Export servis diantre — notifikasi + link unduh muncul setelah selesai.',
        ]);
    }

    public function getStateMachineColumnsProperty(): array
    {
        return ServisStateMachine::kanbanColumns();
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
        $base = TiketServis::query();

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
        $ids = (clone $base)->latest()->limit(100)->pluck('id');

        // Tahap 2: hydrate row SEMPIT + eager load + withCount TANPA ORDER BY
        // (`whereIn` tidak memicu filesort), urutan dikembalikan di PHP.
        // [B-15c] select eksplisit → `foto_unit` (JSON 431KB/baris) TIDAK ikut ter-fetch.
        // WAJIB `select()` SEBELUM `withCount()`: withCount menambahkan subquery ke
        // daftar kolom yang sudah ada (kalau select dipanggil belakangan, subquery-nya
        // tertimpa → `spareparts_count` hilang).
        $semua = TiketServis::with(['jenisServis', 'pelanggan', 'teknisi', 'garansi'])
            ->select(self::KOLOM_KANBAN)
            ->withCount('spareparts')
            ->whereIn('id', $ids)
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
            $this->showTerimaModal = false;
            $this->selectedTiketId = $tiket->id;
            $this->dispatch('alert', ['type' => 'success', 'message' => "Unit diterima — {$tiket->no_tiket}"]);
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

        $tiket = TiketServis::findOrFail($tiketId);

        try {
            $tiket = app(ServisService::class)->updateStatus($tiket, $statusBaru, auth()->user(), $alasan);
            $this->dispatch('alert', ['type' => 'success', 'message' => "Status → {$statusBaru}"]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // --- Estimasi ---
    public function openEstimasiModal(int $tiketId)
    {
        $tiket = TiketServis::findOrFail($tiketId);
        $this->estimasiTiketId = $tiketId;
        $this->estimasiBiaya = (float) ($tiket->estimasi_biaya ?? 0);
        $this->estimasiAlasan = '';
        $this->showEstimasiModal = true;
    }

    public function simpanEstimasi()
    {
        // [B-14] Sama dgn API [SERVICE-05] → `permission:servis.update-status`.
        if (! $this->boleh('servis.update-status', 'Anda tidak punya izin/input estimasi biaya servis')) {
            return;
        }

        $this->validate([
            'estimasiBiaya' => 'required|numeric|min:0',
            'estimasiAlasan' => 'required|string|min:5',
        ]);

        $tiket = TiketServis::findOrFail($this->estimasiTiketId);

        try {
            app(ServisService::class)->setEstimasi(
                $tiket,
                (float) $this->estimasiBiaya,
                $this->estimasiAlasan,
                auth()->user()
            );
            $this->showEstimasiModal = false;
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Estimasi tersimpan, menunggu approval pelanggan']);
        } catch (\Exception $e) {
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
        if (! $this->boleh('servis.update-status', 'Anda tidak punya izin menyetujui/menolak estimasi servis')) {
            return;
        }

        $tiket = TiketServis::findOrFail($this->approveTiketId);
        $statusBaru = $action === 'approve' ? 'disetujui' : 'ditolak';

        $this->updateStatus($tiket->id, $statusBaru, $this->approveAlasan ?: ($action === 'approve' ? 'Pelanggan menyetujui estimasi' : 'Estimasi ditolak'));
        $this->showApproveModal = false;
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
        $this->selectedTiketId = $tiketId;
        $this->bukaKunciGadget = false; // [B-06] selalu mulai ter-mask

        // [T-17] init satu baris kosong + gudang default cabang sesi
        $this->pekerjaanItems = [$this->pekerjaanRowBaru()];
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

    /** [T-17] Simpan item pekerjaan → ServisService::inputPekerjaan (part: stok 1x, jasa: tagihan) */
    public function simpanPekerjaan()
    {
        $tiket = TiketServis::findOrFail($this->selectedTiketId);

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
                'harga' => (float) ($row['harga'] ?? 0),
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
        return $this->selectedTiketId
            ? TiketServis::with([
                'jenisServis', 'pelanggan.tierMembership', 'teknisi', 'garansi',
                'statusLogs.user', 'spareparts.produk', 'spareparts.skuVariant', 'cabang',
                'items', // [T-17]
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

    public function render()
    {
        return view('modules.servis.livewire.servis-board', [
            'jenisServisList' => JenisServis::where('is_active', true)->get(),
            'pelangganList' => Pelanggan::with('tierMembership')->limit(10)->get(),
            'teknisiList' => User::role(['teknisi', 'admin-toko', 'super-admin'])->get(),
            'gudangList' => Gudang::where('is_active', true)->get(),
            'produkList' => Produk::where('is_active', true)->orderBy('nama')->limit(50)->get(),
            'stateMachineColumns' => $this->stateMachineColumns,
            'groupedTikets' => $this->groupedTikets,
            'selectedTiket' => $this->selectedTiket,
            'fotoCount' => $this->fotoCount,
            'pelangganCariServis' => $this->pelangganCariServis,
        ])->layout('layouts.backoffice', ['header' => 'Servis HP — Papan Kanban']);
    }
}
