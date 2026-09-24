<?php

namespace App\Modules\Hr\Livewire;

use App\Modules\Hr\Models\PayrollSlip;
use Livewire\Attributes\Computed;
use Livewire\Component;

class PayrollSlipDetail extends Component
{
    public int $slipId = 0;

    public bool $showDetail = false;

    protected $listeners = ['openDetail' => 'open'];

    /**
     * [PRD §4.3] Buka detail slip dengan pembatasan akses:
     * - super-admin / finance (permission `kelola-payroll` atau `kelola-hr`) → semua slip boleh dilihat;
     * - selain itu (teknisi/karyawan) → HANYA slip milik sendiri (karyawan.user_id === auth id)
     *   DAN cabang karyawan === cabang session; selain itu abort(403).
     */
    public function open(int $slipId): void
    {
        $slip = PayrollSlip::with('karyawan')->find($slipId);

        if (! $slip) {
            $this->showDetail = false;

            return;
        }

        $user = auth()->user();
        $bolehKelola = $user && $user->hasAnyPermission(['kelola-payroll', 'kelola-hr']);

        if (! $bolehKelola) {
            $karyawan = $slip->karyawan;
            $cabangId = session('cabang_id');
            $milikSendiri = $karyawan !== null
                && $karyawan->user_id === $user?->id
                // skip cek cabang bila session kosong — pola GrnTab::tolakJikaBukanCabang
                && ($cabangId === null || $cabangId === '' || (int) $karyawan->cabang_id === (int) $cabangId);

            if (! $milikSendiri) {
                abort(403, 'Slip bukan milik Anda / cabang aktif');
            }
        }

        $this->slipId = $slipId;
        $this->showDetail = true;
    }

    #[Computed]
    public function slip()
    {
        return PayrollSlip::with('karyawan', 'komisiDetails.tiketServis')->find($this->slipId);
    }

    public function render()
    {
        $slip = $this->slip;
        if (! $slip) {
            return view('modules.hr.partials.slip-detail-empty');
        }

        $rincian = json_decode($slip->rincian, true) ?? [];

        return view('modules.hr.partials.slip-detail', [
            'slip' => $slip,
            'rincian' => $rincian,
            'komisiDetails' => $slip->komisiDetails,
        ]);
    }
}
