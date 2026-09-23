<?php

namespace App\Modules\Rbac\Traits;

/**
 * [F1-4] State + aksi buka/tutup modal Riwayat Aktivitas.
 * Dipakai komponen Livewire yang menampilkan detail entitas
 * (AkuntingDashboard, ProdukTab, PoTab, StokTab, PosKasir).
 *
 * Render modal via @include('partials.riwayat-modal') di blade komponen tsb.
 */
trait PunyaRiwayatAktivitas
{
    public bool $showRiwayatModal = false;

    /** tipe pendek entitas — lihat RiwayatAktivitas::TIPE */
    public string $riwayatTipe = '';

    public ?int $riwayatId = null;

    public function bukaRiwayat(string $tipe, int $id): void
    {
        $this->riwayatTipe = $tipe;
        $this->riwayatId = $id;
        $this->showRiwayatModal = true;
    }

    public function tutupRiwayat(): void
    {
        $this->showRiwayatModal = false;
    }
}
