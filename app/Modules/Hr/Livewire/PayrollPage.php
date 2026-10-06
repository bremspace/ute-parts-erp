<?php

namespace App\Modules\Hr\Livewire;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\KaryawanKomponenGaji;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Hr\Models\PayrollSlip;
use App\Modules\Hr\Services\PayrollService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Workflow\Models\ApprovalRequest;
use App\Traits\ParsesNominal;
use Livewire\Component;
use Livewire\WithPagination;

class PayrollPage extends Component
{
    use ParsesNominal;
    use WithPagination;

    public string $activeTab = 'payroll'; // 'payroll' | 'karyawan'

    public string $periode = '';

    public array $periodeList = [];

    public array $slips = [];

    public float $totalGaji = 0;

    public bool $showApproveModal = false;

    public bool $showBayarModal = false;

    public string $akunBayarKode = '110-01';

    public bool $showBuatPeriodeModal = false;

    public string $inputPeriodeBaru = '';

    public string $statusFilter = 'semua';

    // Detail Slip Modal
    public bool $showDetailSlipModal = false;

    public ?array $detailSlip = null;

    // Master Karyawan Modal (Tambah / Edit)
    public bool $showTambahKaryawanModal = false;

    public ?int $karyawanEditId = null;

    public ?int $inputUserId = null;

    public string $inputNik = '';

    public string $inputNama = '';

    public string $inputJabatan = 'teknisi';

    public ?int $inputCabangId = null;

    public string $inputKaryawanGajiPokok = '0';

    public string $inputTglMasuk = '';

    public string $inputRekeningBank = '';

    public bool $inputStatusAktif = true;

    // Kompensasi Karyawan Modal
    public bool $showKompensasiModal = false;

    public ?int $selectedKaryawanId = null;

    public ?array $selectedKaryawan = null;

    public string $inputGajiPokok = '0';

    public array $komponenList = [];

    public string $newKomponenTipe = 'tunjangan';

    public string $newKomponenNama = '';

    public string $newKomponenNominal = '0';

    public string $searchKaryawan = '';

    protected $listeners = ['periodeRefreshed' => 'mount'];

    public function mount(): void
    {
        $this->periodeList = PayrollPeriode::orderByDesc('periode')->get()->pluck('periode', 'id')->toArray();
        if (empty($this->periode) && ! empty($this->periodeList)) {
            $this->periode = collect($this->periodeList)->first();
        }
        $this->inputPeriodeBaru = now()->format('Y-m');
        $this->inputTglMasuk = now()->toDateString();
        $this->inputCabangId = session('cabang_id') ?: Cabang::value('id');
        $this->loadSlips();
    }

    public function updatedPeriode(): void
    {
        $this->loadSlips();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['payroll', 'karyawan']) ? $tab : 'payroll';
    }

    public function loadSlips(): void
    {
        if (! $this->periode) {
            $this->slips = [];
            $this->totalGaji = 0;

            return;
        }

        $query = PayrollSlip::with(['karyawan.cabang'])
            ->whereHas('periode', fn ($q) => $q->where('periode', $this->periode));

        if ($this->statusFilter !== 'semua') {
            $query->where('status', $this->statusFilter);
        }

        $this->slips = $query->get()->map(fn ($s) => [
            'id' => $s->id,
            'karyawan_id' => $s->karyawan_id,
            'karyawan_nama' => $s->karyawan?->nama ?? '-',
            'karyawan_nik' => $s->karyawan?->nik ?? '-',
            'karyawan_jabatan' => $s->karyawan?->jabatan ?? '-',
            'karyawan_cabang' => $s->karyawan?->cabang?->nama ?? 'Pusat',
            'gaji_pokok' => (float) $s->gaji_pokok,
            'total_tunjangan' => (float) $s->total_tunjangan,
            'total_potongan' => (float) $s->total_potongan,
            'total_komisi' => (float) $s->total_komisi,
            'total_gaji' => (float) $s->total_gaji,
            'status' => $s->status,
            'jurnal_id' => $s->jurnal_id,
            'rincian' => json_decode($s->rincian, true) ?? [],
        ])->toArray();

        $this->totalGaji = collect($this->slips)->sum('total_gaji');
    }

    public function bukaBuatPeriode(): void
    {
        $this->inputPeriodeBaru = now()->format('Y-m');
        $this->showBuatPeriodeModal = true;
    }

    public function tutupBuatPeriode(): void
    {
        $this->showBuatPeriodeModal = false;
    }

    public function buatPeriode(): void
    {
        $target = $this->inputPeriodeBaru ?: $this->periode;
        $this->inputPeriodeBaru = $target;

        $this->validate(['inputPeriodeBaru' => 'required|string|size:7|regex:/^\d{4}-\d{2}$/']);

        $existing = PayrollPeriode::where('periode', $this->inputPeriodeBaru)->first();
        if ($existing) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Periode sudah ada']);
            $this->periode = $this->inputPeriodeBaru;
            $this->showBuatPeriodeModal = false;
            $this->loadSlips();

            return;
        }

        PayrollPeriode::create([
            'periode' => $this->inputPeriodeBaru,
            'tanggal_mulai' => $this->inputPeriodeBaru.'-01',
            'tanggal_selesai' => date('Y-m-t', strtotime($this->inputPeriodeBaru.'-01')),
            'status' => 'draft',
        ]);

        $this->periode = $this->inputPeriodeBaru;
        $this->showBuatPeriodeModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Periode '.$this->periode.' berhasil dibuat']);
        $this->periodeList = PayrollPeriode::orderByDesc('periode')->get()->pluck('periode', 'id')->toArray();
        $this->loadSlips();
    }

    public function hitungDraft(): void
    {
        if (! $this->periode) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Pilih periode terlebih dahulu']);

            return;
        }

        try {
            $result = app(PayrollService::class)->hitungDraft($this->periode);
            $this->loadSlips();
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Draft payroll periode '.$this->periode.' berhasil dihitung ('.$result['karyawan_diproses'].' karyawan)']);
        } catch (\Throwable $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function bukaApproveModal(): void
    {
        if (! $this->periode) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Pilih periode terlebih dahulu']);

            return;
        }

        $this->showApproveModal = true;
    }

    public function tutupApproveModal(): void
    {
        $this->showApproveModal = false;
    }

    public function ajukanApproval(): void
    {
        if (! $this->periode) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Pilih periode terlebih dahulu']);

            return;
        }

        try {
            $result = app(PayrollService::class)->ajukanApproval($this->periode, auth()->id());

            if ($result['needs_approval']) {
                $this->showApproveModal = true;
                $this->dispatch('alert', ['type' => 'warning', 'message' => 'Approval diperlukan (total > Rp '.number_format($result['threshold'], 0, ',', '.').')']);
            } else {
                $this->finalisasiDisetujui();
            }
        } catch (\Throwable $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function finalisasiDisetujui(): void
    {
        try {
            $result = app(PayrollService::class)->finalisasiDisetujui($this->periode);
            $this->showApproveModal = false;
            $this->loadSlips();
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Payroll periode '.$this->periode.' disetujui. Jurnal: '.$result['no_jurnal']]);
        } catch (\Throwable $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function bukaBayarModal(): void
    {
        if (! $this->periode) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Pilih periode terlebih dahulu']);

            return;
        }

        $this->showBayarModal = true;
    }

    public function tutupBayarModal(): void
    {
        $this->showBayarModal = false;
    }

    public function bayarPayroll(): void
    {
        if (! $this->periode) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Pilih periode terlebih dahulu']);

            return;
        }

        try {
            $result = app(PayrollService::class)->bayarPayroll($this->periode, $this->akunBayarKode ?: '110-01');
            $this->loadSlips();
            $this->showBayarModal = false;
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Pembayaran payroll periode '.$this->periode.' berhasil. Jurnal: '.$result['no_jurnal']]);
        } catch (\Throwable $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function bukaDetailSlip(int $id): void
    {
        $slip = PayrollSlip::with(['karyawan.cabang', 'komisiDetails.tiketServis'])->find($id);
        if (! $slip) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Slip tidak ditemukan']);

            return;
        }

        $rincian = json_decode($slip->rincian, true) ?? [];

        $this->detailSlip = [
            'id' => $slip->id,
            'karyawan_nama' => $slip->karyawan?->nama ?? '-',
            'karyawan_nik' => $slip->karyawan?->nik ?? '-',
            'karyawan_jabatan' => $slip->karyawan?->jabatan ?? '-',
            'karyawan_cabang' => $slip->karyawan?->cabang?->nama ?? 'Pusat',
            'rekening_bank' => $slip->karyawan?->rekening_bank ?? '-',
            'periode' => $this->periode,
            'status' => $slip->status,
            'gaji_pokok' => (float) $slip->gaji_pokok,
            'total_tunjangan' => (float) $slip->total_tunjangan,
            'total_potongan' => (float) $slip->total_potongan,
            'total_komisi' => (float) $slip->total_komisi,
            'total_gaji' => (float) $slip->total_gaji,
            'potongan_absen' => (float) ($rincian['potongan_absen'] ?? 0),
            'komisi_teknisi' => (float) ($rincian['komisi_teknisi'] ?? 0),
            'komisi_internal' => (float) ($rincian['komisi_internal'] ?? 0),
            'jurnal_id' => $slip->jurnal_id,
            'komisi_details' => $slip->komisiDetails->map(fn ($kd) => [
                'no_tiket' => $kd->tiketServis?->no_tiket ?? '-',
                'jenis' => $kd->jenis,
                'nominal' => (float) $kd->nominal,
            ])->toArray(),
        ];

        $this->showDetailSlipModal = true;
    }

    public function tutupDetailSlip(): void
    {
        $this->showDetailSlipModal = false;
        $this->detailSlip = null;
    }

    // ===== Master Karyawan CRUD =====
    public function bukaTambahKaryawan(): void
    {
        $this->karyawanEditId = null;
        $this->inputUserId = null;
        $this->inputNik = 'EMP-'.str_pad((string) (Karyawan::count() + 1), 4, '0', STR_PAD_LEFT);
        $this->inputNama = '';
        $this->inputJabatan = 'teknisi';
        $this->inputCabangId = session('cabang_id') ?: Cabang::value('id');
        $this->inputKaryawanGajiPokok = '0';
        $this->inputTglMasuk = now()->toDateString();
        $this->inputRekeningBank = '';
        $this->inputStatusAktif = true;

        $this->showTambahKaryawanModal = true;
    }

    public function bukaEditKaryawan(int $id): void
    {
        $k = Karyawan::find($id);
        if (! $k) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Karyawan tidak ditemukan']);

            return;
        }

        $this->karyawanEditId = $k->id;
        $this->inputUserId = $k->user_id;
        $this->inputNik = $k->nik;
        $this->inputNama = $k->nama;
        $this->inputJabatan = $k->jabatan;
        $this->inputCabangId = $k->cabang_id;
        $this->inputKaryawanGajiPokok = (string) ((float) $k->gaji_pokok);
        $this->inputTglMasuk = $k->tgl_masuk ? $k->tgl_masuk->toDateString() : now()->toDateString();
        $this->inputRekeningBank = $k->rekening_bank ?? '';
        $this->inputStatusAktif = (bool) $k->status_aktif;

        $this->showTambahKaryawanModal = true;
    }

    public function tutupTambahKaryawan(): void
    {
        $this->showTambahKaryawanModal = false;
        $this->karyawanEditId = null;
    }

    public function simpanKaryawan(): void
    {
        $rules = [
            'inputNama' => 'required|string|max:100',
            'inputNik' => 'required|string|max:50|unique:karyawan,nik,'.($this->karyawanEditId ?: 'NULL').',id',
            'inputJabatan' => 'required|string|in:teknisi,kasir,admin,marketing,manager,staff',
            'inputCabangId' => 'nullable|exists:cabang,id',
            'inputTglMasuk' => 'required|date',
            'inputUserId' => 'nullable|exists:users,id',
        ];

        $this->validate($rules);

        $gaji = $this->parseNominal($this->inputKaryawanGajiPokok);

        Karyawan::updateOrCreate(
            ['id' => $this->karyawanEditId],
            [
                'user_id' => $this->inputUserId ?: null,
                'nik' => trim($this->inputNik),
                'nama' => trim($this->inputNama),
                'jabatan' => $this->inputJabatan,
                'cabang_id' => $this->inputCabangId ?: null,
                'gaji_pokok' => $gaji,
                'tgl_masuk' => $this->inputTglMasuk,
                'rekening_bank' => trim($this->inputRekeningBank) ?: null,
                'status_aktif' => $this->inputStatusAktif,
            ]
        );

        $this->showTambahKaryawanModal = false;
        $this->dispatch('alert', [
            'type' => 'success',
            'message' => $this->karyawanEditId ? 'Data karyawan berhasil diperbarui' : 'Karyawan baru berhasil ditambahkan',
        ]);
    }

    // ===== Kompensasi / Komponen Gaji =====
    public function bukaKompensasi(int $karyawanId): void
    {
        $karyawan = Karyawan::with(['cabang', 'komponenGaji'])->find($karyawanId);
        if (! $karyawan) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Karyawan tidak ditemukan']);

            return;
        }

        $this->selectedKaryawanId = $karyawan->id;
        $this->selectedKaryawan = [
            'id' => $karyawan->id,
            'nama' => $karyawan->nama,
            'nik' => $karyawan->nik,
            'jabatan' => $karyawan->jabatan,
            'cabang' => $karyawan->cabang?->nama ?? 'Pusat',
            'rekening_bank' => $karyawan->rekening_bank,
        ];
        $this->inputGajiPokok = (string) ((float) $karyawan->gaji_pokok);
        $this->komponenList = $karyawan->komponenGaji->toArray();
        $this->newKomponenTipe = 'tunjangan';
        $this->newKomponenNama = '';
        $this->newKomponenNominal = '0';
        $this->showKompensasiModal = true;
    }

    public function tutupKompensasi(): void
    {
        $this->showKompensasiModal = false;
        $this->selectedKaryawanId = null;
        $this->selectedKaryawan = null;
    }

    public function simpanGajiPokok(): void
    {
        if (! $this->selectedKaryawanId) {
            return;
        }

        $nominal = $this->parseNominal($this->inputGajiPokok);
        Karyawan::where('id', $this->selectedKaryawanId)->update([
            'gaji_pokok' => $nominal,
        ]);

        $this->inputGajiPokok = (string) $nominal;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Gaji pokok berhasil diperbarui']);
    }

    public function tambahKomponen(): void
    {
        if (! $this->selectedKaryawanId) {
            return;
        }

        $this->validate([
            'newKomponenTipe' => 'required|in:tunjangan,potongan,bonus',
            'newKomponenNama' => 'required|string|max:100',
        ]);

        $nominal = $this->parseNominal($this->newKomponenNominal);
        if ($nominal <= 0) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Nominal komponen harus lebih besar dari 0']);

            return;
        }

        KaryawanKomponenGaji::create([
            'karyawan_id' => $this->selectedKaryawanId,
            'tipe' => $this->newKomponenTipe,
            'nama' => trim($this->newKomponenNama),
            'nominal_bulanan' => $nominal,
            'is_aktif' => true,
        ]);

        $this->komponenList = KaryawanKomponenGaji::where('karyawan_id', $this->selectedKaryawanId)->get()->toArray();
        $this->newKomponenNama = '';
        $this->newKomponenNominal = '0';
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Komponen gaji berhasil ditambahkan']);
    }

    public function toggleKomponen(int $id): void
    {
        $komponen = KaryawanKomponenGaji::where('id', $id)
            ->where('karyawan_id', $this->selectedKaryawanId)
            ->first();

        if ($komponen) {
            $komponen->update(['is_aktif' => ! $komponen->is_aktif]);
            $this->komponenList = KaryawanKomponenGaji::where('karyawan_id', $this->selectedKaryawanId)->get()->toArray();
        }
    }

    public function hapusKomponen(int $id): void
    {
        KaryawanKomponenGaji::where('id', $id)
            ->where('karyawan_id', $this->selectedKaryawanId)
            ->delete();

        $this->komponenList = KaryawanKomponenGaji::where('karyawan_id', $this->selectedKaryawanId)->get()->toArray();
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Komponen gaji berhasil dihapus']);
    }

    public function render()
    {
        $periodeRecord = PayrollPeriode::where('periode', $this->periode)->first();
        $approvalRequest = null;
        if ($periodeRecord) {
            $approvalRequest = ApprovalRequest::where('entity_type', 'payroll')
                ->where('entity_id', $periodeRecord->id)
                ->latest()
                ->first();
        }

        $karyawanQuery = Karyawan::with(['cabang', 'komponenGaji']);

        if ($this->searchKaryawan !== '') {
            $search = '%'.$this->searchKaryawan.'%';
            $karyawanQuery->where(function ($q) use ($search) {
                $q->where('nama', 'like', $search)
                    ->orWhere('nik', 'like', $search)
                    ->orWhere('jabatan', 'like', $search);
            });
        }

        $karyawans = $karyawanQuery->orderBy('nama')->get()->map(function ($k) {
            $tunjanganAktif = (float) $k->komponenGaji->whereIn('tipe', ['tunjangan', 'bonus'])->where('is_aktif', true)->sum('nominal_bulanan');
            $potonganAktif = (float) $k->komponenGaji->where('tipe', 'potongan')->where('is_aktif', true)->sum('nominal_bulanan');
            $estGaji = (float) $k->gaji_pokok + $tunjanganAktif - $potonganAktif;

            return [
                'id' => $k->id,
                'nik' => $k->nik ?? '-',
                'nama' => $k->nama,
                'jabatan' => $k->jabatan,
                'cabang' => $k->cabang?->nama ?? 'Pusat',
                'gaji_pokok' => (float) $k->gaji_pokok,
                'tunjangan' => $tunjanganAktif,
                'potongan' => $potonganAktif,
                'estimasi_bersih' => max(0, $estGaji),
                'status_aktif' => (bool) $k->status_aktif,
                'rekening_bank' => $k->rekening_bank ?? '-',
                'komponen_count' => $k->komponenGaji->count(),
            ];
        });

        $cabangList = Cabang::all();
        $userList = User::orderBy('name')->get();
        $akunKasBankList = AkunCOA::whereIn('kelompok', ['kas', 'bank'])->where('is_active', true)->orderBy('kode')->get();

        return view('modules.hr.payroll', [
            'slips' => $this->slips,
            'totalGaji' => $this->totalGaji,
            'periodeList' => $this->periodeList,
            'periodeRecord' => $periodeRecord,
            'approvalRequest' => $approvalRequest,
            'statusFilter' => $this->statusFilter,
            'karyawans' => $karyawans,
            'cabangList' => $cabangList,
            'userList' => $userList,
            'akunKasBankList' => $akunKasBankList,
        ])->layout('layouts.backoffice', ['header' => 'HR & Payroll', 'title' => 'HR & Payroll']);
    }
}
