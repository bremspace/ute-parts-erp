<?php

namespace App\Modules\Hr\Livewire;

use App\Modules\Hr\Models\PayrollSlip;
use Livewire\Component;

class PayrollSlipDetail extends Component
{
    public int $slipId;

    public bool $showDetail = false;

    protected $listeners = ['openDetail' => 'open'];

    public function open(int $slipId): void
    {
        $this->slipId = $slipId;
        $this->showDetail = true;
    }

    public function getSlipProperty()
    {
        return PayrollSlip::with('karyawan', 'komisiDetails.tiketServis')->find($this->slipId);
    }

    public function render()
    {
        $slip = $this->slipProperty;
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
