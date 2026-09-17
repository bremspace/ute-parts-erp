<?php

namespace App\Modules\Akunting\Livewire;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Akunting\Services\JurnalService;
use Livewire\Component;
use Livewire\WithPagination;

class AkuntingDashboard extends Component
{
    use WithPagination;

    public string $activeTab = 'laporan'; // laporan, jurnal, coa, piutang, utang
    public string $periodeDari = '';
    public string $periodeSampai = '';

    // Jurnal manual modal
    public bool $showJurnalManual = false;
    public string $manualTanggal = '';
    public string $manualDeskripsi = '';
    public array $manualLines = [];

    // COA modal
    public bool $showCoaModal = false;
    public array $coaForm = [
        'kode' => '', 'nama' => '', 'tipe' => 'aset', 'kelompok' => '', 'saldo_normal' => 'debit',
    ];

    // Bayar utang modal
    public bool $showBayarUtangModal = false;
    public ?int $utangId = null;
    public float $bayarUtangJumlah = 0;

    // Bayar piutang modal
    public bool $showBayarPiutangModal = false;
    public ?int $piutangId = null;
    public float $bayarPiutangJumlah = 0;

    public function mount()
    {
        $this->periodeDari = now()->startOfMonth()->toDateString();
        $this->periodeSampai = now()->toDateString();
        $this->manualTanggal = now()->toDateString();
        $this->manualLines = [
            ['akun_kode' => '', 'debit' => 0, 'kredit' => 0],
            ['akun_kode' => '', 'debit' => 0, 'kredit' => 0],
        ];
    }

    // ===== LAPORAN =====
    public function getLabaRugiProperty(): array
    {
        $dari = $this->periodeDari;
        $sampai = $this->periodeSampai;
        $cabangId = session('cabang_id');

        $query = JurnalAkuntansi::whereDate('tanggal', '>=', $dari)
            ->whereDate('tanggal', '<=', $sampai);
        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        $jurnals = $query->with('akun')->get();

        $pendapatan = $jurnals->where('akun.tipe', 'pendapatan')->groupBy('akun_coa_id')->map(fn ($rows) => [
            'nama' => $rows->first()->akun?->nama ?? '?',
            'kode' => $rows->first()->akun?->kode ?? '?',
            'total' => round($rows->sum('kredit') - $rows->sum('debit'), 2),
        ])->values();

        $beban = $jurnals->where('akun.tipe', 'beban')->groupBy('akun_coa_id')->map(fn ($rows) => [
            'nama' => $rows->first()->akun?->nama ?? '?',
            'kode' => $rows->first()->akun?->kode ?? '?',
            'total' => round($rows->sum('debit') - $rows->sum('kredit'), 2),
        ])->values();

        return [
            'pendapatan' => $pendapatan,
            'beban' => $beban,
            'total_pendapatan' => round($pendapatan->sum('total'), 2),
            'total_beban' => round($beban->sum('total'), 2),
            'laba_bersih' => round($pendapatan->sum('total') - $beban->sum('total'), 2),
        ];
    }

    public function getNeracaProperty(): array
    {
        $cabangId = session('cabang_id');
        $query = JurnalAkuntansi::whereDate('tanggal', '<=', $this->periodeSampai);
        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        $jurnals = $query->with('akun')->get();

        $grouped = $jurnals->groupBy('akun_coa_id')->map(function ($rows) {
            $akun = $rows->first()->akun;
            if (!$akun) {
                return null;
            }
            $saldo = $akun->saldo_normal === 'debit'
                ? $rows->sum('debit') - $rows->sum('kredit')
                : $rows->sum('kredit') - $rows->sum('debit');
            return [
                'tipe' => $akun->tipe,
                'kelompok' => $akun->kelompok,
                'kode' => $akun->kode,
                'nama' => $akun->nama,
                'saldo' => round($saldo, 2),
            ];
        })->filter()->values();

        return [
            'aset' => $grouped->where('tipe', 'aset')->values(),
            'kewajiban' => $grouped->where('tipe', 'kewajiban')->values(),
            'ekuitas' => $grouped->where('tipe', 'ekuitas')->values(),
            'total_aset' => round($grouped->where('tipe', 'aset')->sum('saldo'), 2),
            'total_kewajiban' => round($grouped->where('tipe', 'kewajiban')->sum('saldo'), 2),
            'total_ekuitas' => round($grouped->where('tipe', 'ekuitas')->sum('saldo'), 2),
        ];
    }

    // ===== JURNAL =====
    public function getJurnalsProperty()
    {
        return JurnalAkuntansi::with(['akun', 'cabang'])
            ->latest('tanggal')
            ->paginate(25);
    }

    public function openJurnalManualModal()
    {
        $this->showJurnalManual = true;
    }

    public function addManualLine()
    {
        $this->manualLines[] = ['akun_kode' => '', 'debit' => 0, 'kredit' => 0];
    }

    public function removeManualLine(int $index)
    {
        if (count($this->manualLines) <= 2) return;
        unset($this->manualLines[$index]);
        $this->manualLines = array_values($this->manualLines);
    }

    public function simpanJurnalManual()
    {
        try {
            $noJurnal = app(JurnalService::class)->generateNoJurnal('manual', session('cabang_id'));
            app(JurnalService::class)->post(
                $noJurnal,
                $this->manualTanggal,
                'manual',
                $this->manualLines,
                $this->manualDeskripsi,
                session('cabang_id'),
                auth()->id()
            );
            $this->showJurnalManual = false;
            $this->dispatch('alert', ['type' => 'success', 'message' => "Jurnal manual {$noJurnal} diposting"]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ===== COA =====
    public function getCoaListProperty()
    {
        return AkunCOA::orderBy('kode')->get();
    }

    public function openCoaModal()
    {
        $this->coaForm = ['kode' => '', 'nama' => '', 'tipe' => 'aset', 'kelompok' => '', 'saldo_normal' => 'debit'];
        $this->showCoaModal = true;
    }

    public function simpanCoa()
    {
        $this->validate([
            'coaForm.kode' => 'required|string|max:20|unique:akun_coa,kode',
            'coaForm.nama' => 'required|string|max:255',
            'coaForm.tipe' => 'required|in:aset,kewajiban,ekuitas,pendapatan,beban',
            'coaForm.kelompok' => 'required|string|max:100',
            'coaForm.saldo_normal' => 'required|in:debit,kredit',
        ]);

        AkunCOA::create($this->coaForm);
        $this->showCoaModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Akun COA ditambahkan']);
    }

    // ===== PIUTANG / UTANG =====
    public function getPiutangsProperty()
    {
        return Piutang::with('pelanggan')->latest()->get();
    }

    public function getArusKasProperty(): array
    {
        $dari = $this->periodeDari;
        $sampai = $this->periodeSampai;
        $cabangId = session('cabang_id');

        $query = JurnalAkuntansi::whereDate('tanggal', '>=', $dari)
            ->whereDate('tanggal', '<=', $sampai);
        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        $jurnals = $query->with('akun')->get();

        $labaBersih = round(
            $jurnals->where('akun.tipe', 'pendapatan')->sum(fn($j) => (float) $j->kredit - (float) $j->debit)
            - $jurnals->where('akun.tipe', 'beban')->sum(fn($j) => (float) $j->debit - (float) $j->kredit),
            2
        );

        $piutangDelta = round($jurnals->where('akun.kode', '120-01')->sum('debit') - $jurnals->where('akun.kode', '120-01')->sum('kredit'), 2);
        $persediaanDelta = round($jurnals->where('akun.kode', '130-01')->sum('debit') - $jurnals->where('akun.kode', '130-01')->sum('kredit'), 2);
        $utangDelta = round(
            $jurnals->where('akun.kode', '210-01')->sum('kredit') - $jurnals->where('akun.kode', '210-01')->sum('debit')
            + $jurnals->where('akun.kode', '210-03')->sum('kredit') - $jurnals->where('akun.kode', '210-03')->sum('debit'),
            2
        );

        $arusKasOperasi = round($labaBersih - $piutangDelta - $persediaanDelta + $utangDelta, 2);

        $arusInvestasi = round(
            -$jurnals->where('akun.kelompok', 'aset_tetap')->sum(fn($j) => (float) $j->debit - (float) $j->kredit)
            - $jurnals->where('akun.kelompok', 'peralatan')->sum(fn($j) => (float) $j->debit - (float) $j->kredit)
            - $jurnals->where('akun.kelompok', 'perlengkapan')->sum(fn($j) => (float) $j->debit - (float) $j->kredit),
            2
        );

        $arusPendanaan = round(
            $jurnals->where('akun.kelompok', 'modal')->sum(fn($j) => (float) $j->kredit - (float) $j->debit)
            + $jurnals->where('akun.kelompok', 'laba_ditahan')->sum(fn($j) => (float) $j->kredit - (float) $j->debit),
            2
        );

        return [
            'laba_bersih' => $labaBersih,
            'penyesuaian' => [
                'kenaikan_piutang' => -$piutangDelta,
                'kenaikan_persediaan' => -$persediaanDelta,
                'kenaikan_utang' => $utangDelta,
            ],
            'arus_kas_operasi' => $arusKasOperasi,
            'arus_kas_investasi' => $arusInvestasi,
            'arus_kas_pendanaan' => $arusPendanaan,
            'kenaikan_kas_neto' => round($arusKasOperasi + $arusInvestasi + $arusPendanaan, 2),
        ];
    }

    public function openBayarPiutangModal(int $id)
    {
        $this->piutangId = $id;
        $piutang = Piutang::find($id);
        $this->bayarPiutangJumlah = (float) $piutang?->sisa ?? 0;
        $this->showBayarPiutangModal = true;
    }

    public function bayarPiutang()
    {
        $piutang = Piutang::findOrFail($this->piutangId);
        $dibayar = (float) $piutang->jumlah_dibayar + $this->bayarPiutangJumlah;

        if ($dibayar > (float) $piutang->jumlah) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Pembayaran melebihi sisa piutang']);
            return;
        }

        $piutang->update([
            'jumlah_dibayar' => $dibayar,
            'status' => $dibayar >= (float) $piutang->jumlah ? 'lunas' : 'sebagian',
        ]);

        $this->showBayarPiutangModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Penerimaan piutang dicatat']);
    }

    public function getUtangsProperty()
    {
        return Utang::latest()->get();
    }

    public function openBayarUtangModal(int $id)
    {
        $this->utangId = $id;
        $utang = Utang::find($id);
        $this->bayarUtangJumlah = (float) $utang?->sisa ?? 0;
        $this->showBayarUtangModal = true;
    }

    public function bayarUtang()
    {
        $utang = Utang::findOrFail($this->utangId);
        $dibayar = (float) $utang->jumlah_dibayar + $this->bayarUtangJumlah;

        if ($dibayar > (float) $utang->jumlah) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Pembayaran melebihi sisa utang']);
            return;
        }

        $utang->update([
            'jumlah_dibayar' => $dibayar,
            'status' => $dibayar >= (float) $utang->jumlah ? 'lunas' : 'sebagian',
        ]);

        $this->showBayarUtangModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Pembayaran utang dicatat']);
    }

    public function render()
    {
        return view('modules.akunting.livewire.akunting-dashboard', [
            'akunCoaList' => $this->coaList,
            'labaRugi' => $this->labaRugi,
            'neraca' => $this->neraca,
            'arusKas' => $this->arusKas,
            'jurnals' => $this->jurnals,
            'piutangs' => $this->piutangs,
            'utangs' => $this->utangs,
        ])->layout('layouts.backoffice', ['header' => 'Akunting & Keuangan']);
    }
}