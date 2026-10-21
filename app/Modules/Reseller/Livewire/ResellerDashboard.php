<?php

namespace App\Modules\Reseller\Livewire;

use App\Modules\Akunting\Jobs\ExportLaporanJob;
use App\Modules\Akunting\Services\ExportLaporanService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Reseller\Models\SkemaKomisi;
use App\Modules\Reseller\Services\KomisiService;
use App\Traits\ParsesNominal;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithPagination;

class ResellerDashboard extends Component
{
    use ParsesNominal;
    use WithPagination;

    public string $activeTab = 'reseller'; // reseller, komisi, skema

    // Mitra filter & search
    public string $filterMitra = ''; // '', 'reseller', 'agen'

    public string $search = '';

    // Komisi filter
    public string $filterStatus = '';

    public string $filterAktor = ''; // '', 'reseller', 'agen', 'karyawan'

    public array $selectedKomisiIds = [];

    protected $queryString = [
        'activeTab' => ['except' => 'reseller'],
        'filterMitra' => ['except' => ''],
        'search' => ['except' => ''],
        'filterStatus' => ['except' => ''],
        'filterAktor' => ['except' => ''],
    ];

    // Skema komisi modal
    public bool $showSkemaModal = false;

    public array $skemaForm = ['id' => null, 'nama' => '', 'kategori' => '', 'tipe' => 'persen', 'nilai' => 5, 'is_active' => true];

    public function getResellersProperty()
    {
        $query = Pelanggan::with(['tierMembership', 'komisi', 'downlines'])
            ->where(function ($q) {
                $q->where('is_reseller', true)
                    ->orWhere('tipe_konsumen', 'agen')
                    ->orWhereNotNull('kode_agen');
            });

        if ($this->filterMitra === 'agen') {
            $query->where(function ($q) {
                $q->where('tipe_konsumen', 'agen')
                    ->orWhereNotNull('kode_agen');
            });
        } elseif ($this->filterMitra === 'reseller') {
            $query->where(function ($q) {
                $q->where('tipe_konsumen', '!=', 'agen')
                    ->orWhereNull('tipe_konsumen');
            })->whereNull('kode_agen');
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('telepon', 'like', "%{$this->search}%")
                    ->orWhere('kode_agen', 'like', "%{$this->search}%");
            });
        }

        return $query->latest()
            ->get()
            ->map(function ($r) {
                $isAgen = $r->tipe_konsumen === 'agen' || ! empty($r->kode_agen);
                $downlines = $r->downlines;

                return [
                    'id' => $r->id,
                    'nama' => $r->nama,
                    'telepon' => $r->telepon,
                    'is_agen' => $isAgen,
                    'tipe_mitra' => $isAgen ? 'agen' : 'reseller',
                    'kode_agen' => $r->kode_agen,
                    'referral_kode' => $r->referral_kode,
                    'downline_count' => $downlines->count(),
                    'downline_omzet' => (float) $downlines->sum('total_belanja_12bulan'),
                    'total_belanja' => $r->total_belanja_12bulan,
                    'tier' => $r->tierMembership?->nama,
                    'komisi_terhutang' => $r->komisi->where('status', 'disetujui')->sum('nominal_komisi'),
                    'komisi_pending' => $r->komisi->where('status', 'pending')->sum('nominal_komisi'),
                ];
            });
    }

    public function getKomisiListProperty()
    {
        $query = Komisi::with(['pelanggan', 'approver', 'transaksi', 'karyawan', 'tiketServis', 'lead'])
            ->latest();

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterAktor) {
            $query->where('aktor_tipe', $this->filterAktor);
        }

        return $query->get();
    }

    public function getSkemaListProperty()
    {
        return SkemaKomisi::orderBy('id')->get();
    }

    /** [F2-5] Export laporan komisi reseller via queue & unduh langsung. */
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
            jenis: 'komisi',
            periodeDari: null,
            periodeSampai: null,
            cabangId: $cabangId,
            akunId: null,
            userId: $userId,
            format: $fmt,
        ));

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Export komisi selesai — berkas mulai diunduh.',
        ]);

        $path = app(ExportLaporanService::class)->export(
            'komisi', null, null, $cabangId, null, $fmt, $userId
        );
        $fullPath = Storage::disk('local')->path($path);

        return response()->download($fullPath, 'laporan-komisi-'.now()->format('Ymd-His').'.'.$fmt);
    }

    public function toggleKomisi(int $id)
    {
        if (in_array($id, $this->selectedKomisiIds, true)) {
            $this->selectedKomisiIds = array_values(array_diff($this->selectedKomisiIds, [$id]));
        } else {
            $this->selectedKomisiIds[] = $id;
        }
    }

    public function prosesApproval(string $action)
    {
        // [RBAC] Rute /app/reseller hanya `reseller.view` (dimiliki marketing),
        // tapi prosesApproval mem-post jurnal (510-01/210-03) + Utang lewat
        // KomisiService. Otoritas approve komisi = `komisi.approve` (finance),
        // sama seperti API POST /api/reseller/komisi/approve.
        if (! auth()->user()?->can('komisi.approve')) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda tidak memiliki izin approval komisi.']);

            return;
        }

        if (empty($this->selectedKomisiIds)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Pilih minimal satu komisi terlebih dahulu']);

            return;
        }

        try {
            $approved = app(KomisiService::class)->prosesApproval($this->selectedKomisiIds, $action, auth()->id());
            $this->selectedKomisiIds = [];
            $this->dispatch('alert', [
                'type' => 'success',
                'message' => $action === 'approve'
                    // count(), bukan interpolasi array — `{$approved}` memicu
                    // "Array to string conversion" yang ditangkap catch di bawah,
                    // sehingga approval BERHASIL justru menampilkan toast error.
                    ? count($approved).' komisi disetujui — jurnal & utang dibuat otomatis'
                    : count($approved).' komisi ditolak',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function openSkemaModal()
    {
        $this->skemaForm = ['id' => null, 'nama' => '', 'kategori' => '', 'tipe' => 'persen', 'nilai' => 5, 'is_active' => true];
        $this->showSkemaModal = true;
    }

    public function simpanSkema()
    {
        $this->skemaForm['nilai'] = $this->parseNominal($this->skemaForm['nilai'] ?? 0);

        $this->validate([
            'skemaForm.nama' => 'required|string|max:255',
            'skemaForm.tipe' => 'required|in:persen,nominal',
            'skemaForm.nilai' => 'required|numeric|min:0',
        ]);

        SkemaKomisi::create($this->skemaForm);
        $this->showSkemaModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Skema komisi ditambahkan']);
    }

    public function render()
    {
        $semuaMitra = Pelanggan::where(function ($q) {
            $q->where('is_reseller', true)
                ->orWhere('tipe_konsumen', 'agen')
                ->orWhereNotNull('kode_agen');
        })->get();

        $totalMitra = $semuaMitra->count();
        $totalAgen = $semuaMitra->filter(fn ($m) => $m->tipe_konsumen === 'agen' || ! empty($m->kode_agen))->count();
        $totalReseller = $totalMitra - $totalAgen;

        return view('modules.reseller.livewire.reseller-dashboard', [
            'resellers' => $this->resellers,
            'komisiList' => $this->komisiList,
            'skemaList' => $this->skemaList,
            'statsMitra' => [
                'total' => $totalMitra,
                'agen' => $totalAgen,
                'reseller' => $totalReseller,
            ],
        ])->layout('layouts.backoffice', ['header' => 'Reseller & Komisi']);
    }
}
