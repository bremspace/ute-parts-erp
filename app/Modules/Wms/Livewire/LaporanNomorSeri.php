<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Wms\Models\NomorSeri;
use Livewire\Component;

/**
 * [F2-3] Laporan Histori Nomor Seri (trace garansi).
 *
 * DataTable SN + rantai riwayat: GRN → transaksi penjualan → tiket servis → garansi.
 * RBAC: permission laporan.cabang (route middleware — pola laporan existing).
 * Scope: cabang aktif (session cabang_id). Pencarian: nomor seri (scan/ ketik).
 * Desktop-first backoffice, komponen Ute Prism (DataTable/StatusPill).
 */
class LaporanNomorSeri extends Component
{
    public string $search = '';

    /** Rantai histori utk 1 SN (dipakai view utk kolom trace). */
    private function riwayat(NomorSeri $sn): array
    {
        $tiket = $sn->tiketServis ?? $sn->tiketServisItem?->tiketServis;
        $garansi = $tiket?->garansi;

        return [
            'grn' => $sn->keterangan, // "GRN {no_grn}" — sumber masuk stok
            'tanggal_masuk' => $sn->created_at?->format('d/m/Y'),
            'no_transaksi' => $sn->transaksiItem?->transaksi?->no_transaksi,
            'tanggal_jual' => $sn->transaksiItem?->transaksi?->created_at?->format('d/m/Y'),
            'no_tiket' => $tiket?->no_tiket,
            'status_tiket' => $tiket?->status,
            'garansi_sampai' => $garansi?->tanggal_berakhir?->format('d/m/Y'),
        ];
    }

    public function render()
    {
        $cabangId = session('cabang_id');

        $rows = NomorSeri::query()
            ->with([
                'produk',
                'transaksiItem.transaksi',
                'tiketServisItem.tiketServis.garansi',
                'tiketServis.garansi',
            ])
            // [F2-3] Cabang scoping ketat — tidak ada bocor lintas cabang
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->when(
                trim($this->search) !== '',
                fn ($q) => $q->where('nomor_seri', 'like', '%'.trim($this->search).'%')
            )
            ->orderByDesc('id')
            ->paginate(15, pageName: 'sn');

        // Rantai histori per baris (computed — pass eksplisit ke view, WAIBS)
        $riwayat = [];
        foreach ($rows as $sn) {
            $riwayat[$sn->id] = $this->riwayat($sn);
        }

        return view('modules.wms.livewire.laporan-nomor-seri', [
            'rows' => $rows,
            'riwayat' => $riwayat,
            'search' => $this->search,
        ])->layout('layouts.backoffice', ['header' => 'Laporan Histori Nomor Seri']);
    }
}
