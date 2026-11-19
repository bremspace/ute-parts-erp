<?php

namespace App\Modules\Hr\Livewire;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Hr\Models\BagiHasil;
use App\Modules\Hr\Models\BagiHasilPeriode;
use App\Modules\Hr\Models\BagiHasilRule;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Services\BagiHasilService;
use App\Modules\Rbac\Models\Cabang;
use App\Traits\ParsesNominal;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * [BH] Bagi Hasil Jasa Teknisi non-gaji — aturan, transaksi per tiket, payout periode.
 * RBAC: kelola-bagi-hasil (super-admin + finance).
 */
#[Layout('layouts.backoffice')]
class BagiHasilPage extends Component
{
    use ParsesNominal;

    public const VALID_TABS = ['rule', 'transaksi', 'payout'];

    public string $activeTab = 'rule';

    public ?string $pesan = null;

    // ===== Rule =====
    public ?int $editId = null;

    public string $nama = '';

    public ?int $ruleCabangId = null;

    public ?int $ruleKaryawanId = null;

    public string $persen = '0';

    public string $minJasa = '0';

    public bool $isAktif = true;

    public string $filterRule = 'semua'; // semua | global | cabang | teknisi

    // ===== Transaksi =====
    public string $filterPeriode = '';

    public string $filterTeknisi = 'semua';

    public string $filterStatus = 'semua';

    // ===== Payout =====
    public string $periode = '';

    public array $periodeList = [];

    public ?array $periodeRecord = null;

    public array $rekap = [];

    public float $totalRekap = 0;

    public string $inputPeriodeBaru = '';

    public bool $showBayarModal = false;

    public string $akunBayarKode = '110-01';

    public string $catatan = '';

    public function mount(?string $tab = null): void
    {
        $tabParam = $tab ?: request('tab');
        if ($tabParam && in_array($tabParam, self::VALID_TABS, true)) {
            $this->activeTab = $tabParam;
        }

        $this->filterPeriode = now()->format('Y-m');
        $this->periode = now()->format('Y-m');
        $this->inputPeriodeBaru = now()->format('Y-m');

        $this->muatPeriodeList();
        $this->muatPayout();
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, self::VALID_TABS, true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->pesan = null;
        $this->dispatch('tab-changed', tab: $tab, url: url('/app/hr/bagi-hasil/'.$tab));
    }

    // ===== Rule CRUD =====
    public function simpanRule(): void
    {
        $this->persen = (string) $this->parseNominal($this->persen);
        $this->minJasa = (string) $this->parseNominal($this->minJasa);

        $this->validate([
            'nama' => 'required|string|max:120',
            'ruleCabangId' => 'nullable|integer|min:1|exists:cabang,id',
            'ruleKaryawanId' => 'nullable|integer|min:1|exists:karyawan,id',
            'persen' => 'required|numeric|min:0|max:100',
            'minJasa' => 'nullable|numeric|min:0',
            'isAktif' => 'boolean',
        ], [
            'persen.max' => 'Persen maksimal 100.',
        ]);

        $data = [
            'nama' => trim($this->nama),
            'cabang_id' => $this->ruleCabangId ?: null,
            'karyawan_id' => $this->ruleKaryawanId ?: null,
            'persen' => (float) $this->persen,
            'min_jasa' => (float) $this->minJasa,
            'is_aktif' => $this->isAktif,
        ];

        if ($this->editId) {
            BagiHasilRule::findOrFail($this->editId)->update($data);
            $this->pesan = 'Aturan bagi hasil diperbarui.';
        } else {
            BagiHasilRule::create($data);
            $this->pesan = 'Aturan bagi hasil baru dibuat.';
        }

        $this->resetRuleForm();
    }

    public function editRule(int $id): void
    {
        $rule = BagiHasilRule::findOrFail($id);
        $this->editId = $rule->id;
        $this->nama = $rule->nama;
        $this->ruleCabangId = $rule->cabang_id;
        $this->ruleKaryawanId = $rule->karyawan_id;
        $this->persen = (string) $rule->persen;
        $this->minJasa = (string) $rule->min_jasa;
        $this->isAktif = (bool) $rule->is_aktif;
        $this->pesan = null;
    }

    public function toggleRule(int $id): void
    {
        $rule = BagiHasilRule::find($id);
        if ($rule) {
            $rule->update(['is_aktif' => ! $rule->is_aktif]);
            $this->pesan = $rule->is_aktif ? 'Aturan diaktifkan.' : 'Aturan dinonaktifkan.';
        }
    }

    public function hapusRule(int $id): void
    {
        BagiHasilRule::where('id', $id)->delete();
        $this->pesan = 'Aturan bagi hasil dihapus.';
    }

    public function resetRuleForm(): void
    {
        $this->editId = null;
        $this->nama = '';
        $this->ruleCabangId = null;
        $this->ruleKaryawanId = null;
        $this->persen = '0';
        $this->minJasa = '0';
        $this->isAktif = true;
    }

    // ===== Transaksi =====
    public function updatedFilterPeriode(): void {}

    // ===== Payout =====
    public function muatPeriodeList(): void
    {
        $this->periodeList = BagiHasilPeriode::withoutCabangScope()
            ->orderByDesc('periode')
            ->pluck('periode', 'id')
            ->toArray();
    }

    public function muatPayout(): void
    {
        $cabangId = currentCabangId();

        $query = BagiHasilPeriode::withoutCabangScope()->where('periode', $this->periode);
        $query = $cabangId ? $query->where('cabang_id', $cabangId) : $query->whereNull('cabang_id');
        $record = $query->first();

        $this->periodeRecord = $record ? [
            'id' => $record->id,
            'periode' => $record->periode,
            'status' => $record->status,
            'total' => (float) $record->total,
            'jumlah_tiket' => (int) $record->jumlah_tiket,
            'no_jurnal_accrue' => $record->no_jurnal_accrue,
            'no_jurnal_bayar' => $record->no_jurnal_bayar,
        ] : null;

        $this->rekap = app(BagiHasilService::class)->rekapTeknisi($this->periode, $cabangId);
        $this->totalRekap = (float) collect($this->rekap)->sum('total_nominal');
    }

    public function updatedPeriode(): void
    {
        $this->muatPayout();
    }

    public function buatPeriode(): void
    {
        $this->validate([
            'inputPeriodeBaru' => 'required|string|size:7|regex:/^\d{4}-\d{2}$/',
        ]);

        app(BagiHasilService::class)->getOrCreatePeriode($this->inputPeriodeBaru, currentCabangId());

        $this->periode = $this->inputPeriodeBaru;
        $this->muatPeriodeList();
        $this->muatPayout();
        $this->pesan = 'Periode '.$this->periode.' siap dikelola.';
    }

    public function hitung(): void
    {
        try {
            $result = app(BagiHasilService::class)->hitungPeriode($this->periode, currentCabangId());
            $this->muatPayout();
            $this->pesan = 'Rekap periode '.$this->periode.' dihitung: '.$result['jumlah_tiket'].' tiket, total Rp '.number_format($result['total'], 0, ',', '.');
        } catch (\Throwable $e) {
            $this->pesan = null;
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function ajukanProses(): void
    {
        try {
            $service = app(BagiHasilService::class);
            $result = $service->ajukanApproval($this->periode, auth()->id(), $this->catatan ?: '', currentCabangId());

            if ($result['needs_approval']) {
                $this->pesan = null;
                $this->dispatch('alert', ['type' => 'warning', 'message' => 'Total Rp '.number_format($result['total'], 0, ',', '.').' di atas ambang — menunggu persetujuan finance.']);

                return;
            }

            $proses = $service->prosesPeriode($this->periode, currentCabangId());
            $this->muatPayout();
            $this->pesan = 'Periode '.$this->periode.' diproses. Jurnal: '.$proses['no_jurnal'];
        } catch (\Throwable $e) {
            $this->pesan = null;
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function prosesUlang(): void
    {
        try {
            $proses = app(BagiHasilService::class)->prosesPeriode($this->periode, currentCabangId());
            $this->muatPayout();
            $this->pesan = $proses['sudah_diproses']
                ? 'Periode sudah pernah diproses. Jurnal: '.$proses['no_jurnal']
                : 'Periode '.$this->periode.' diproses. Jurnal: '.$proses['no_jurnal'];
        } catch (\Throwable $e) {
            $this->pesan = null;
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function bukaBayarModal(): void
    {
        $this->showBayarModal = true;
    }

    public function tutupBayarModal(): void
    {
        $this->showBayarModal = false;
    }

    public function bayar(): void
    {
        try {
            $result = app(BagiHasilService::class)->bayarPeriode($this->periode, $this->akunBayarKode ?: '110-01', currentCabangId());
            $this->showBayarModal = false;
            $this->muatPayout();
            $this->pesan = 'Pembayaran periode '.$this->periode.' berhasil. Jurnal: '.$result['no_jurnal'];
        } catch (\Throwable $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function render(): View
    {
        $rules = BagiHasilRule::with(['cabang', 'karyawan'])
            ->when($this->filterRule === 'global', fn ($q) => $q->whereNull('cabang_id')->whereNull('karyawan_id'))
            ->when($this->filterRule === 'cabang', fn ($q) => $q->whereNull('karyawan_id')->whereNotNull('cabang_id'))
            ->when($this->filterRule === 'teknisi', fn ($q) => $q->whereNotNull('karyawan_id'))
            ->orderByDesc('is_aktif')
            ->orderByDesc('id')
            ->get();

        $transaksiQuery = BagiHasil::with(['karyawan', 'tiketServis'])
            ->when($this->filterPeriode !== '', fn ($q) => $q->where('periode', $this->filterPeriode))
            ->when($this->filterTeknisi !== 'semua', fn ($q) => $q->where('karyawan_id', (int) $this->filterTeknisi))
            ->when($this->filterStatus !== 'semua', fn ($q) => $q->where('status', $this->filterStatus))
            ->orderByDesc('id');

        $transaksis = $transaksiQuery->get()->map(fn (BagiHasil $t) => [
            'id' => $t->id,
            'no_bagi_hasil' => $t->no_bagi_hasil,
            'no_tiket' => $t->tiketServis?->no_tiket ?? '-',
            'karyawan_nama' => $t->karyawan?->nama ?? 'Karyawan #'.$t->karyawan_id,
            'periode' => $t->periode,
            'total_jasa' => (float) $t->total_jasa,
            'persen' => (float) $t->persen,
            'nominal' => (float) $t->nominal,
            'status' => $t->status,
        ]);

        return view('modules.hr.bagi-hasil', [
            'rules' => $rules,
            'transaksis' => $transaksis,
            'rekap' => $this->rekap,
            'totalRekap' => $this->totalRekap,
            'periodeRecord' => $this->periodeRecord,
            'periodeList' => $this->periodeList,
            'karyawans' => Karyawan::where('jabatan', 'teknisi')->orderBy('nama')->get(),
            'cabangList' => Cabang::orderBy('nama')->get(),
            'akunKasBankList' => AkunCOA::whereIn('kelompok', ['kas', 'bank'])->where('is_active', true)->orderBy('kode')->get(),
        ]);
    }
}
