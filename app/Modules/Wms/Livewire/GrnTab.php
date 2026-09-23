<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Rbac\Traits\PunyaRiwayatAktivitas;
use App\Modules\Wms\Models\Grn;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Services\GrnService;
use App\Modules\Wms\Services\NomorSeriService;
use App\Modules\Workflow\Models\ApprovalRequest;
use App\Modules\Workflow\Services\ApprovalService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * [F2-2] Tab "GRN" — penerimaan barang dari PO.
 * Flow: PO dikirim → input GRN (qty diterima per item, validasi ≤ qty PO)
 * - Qty sesuai → langsung terima + jurnal AP + stok masuk
 * - Partial/tolak → status draft + approval F1-1 (rule entity_type 'grn')
 * - Setujui via dialog tab (permission approve-workflow) atau inbox approval → jurnal AP + stok
 */
class GrnTab extends Component
{
    use PunyaRiwayatAktivitas;

    public bool $showGrnModal = false;

    public array $grnForm = [
        'po_id' => null,
        'item_qty' => [],
        'catatan' => '',
    ];

    public bool $showConfirmDialog = false;

    public string $confirmTitle = '';

    public string $confirmText = '';

    public ?int $confirmGrnId = null;

    public ?int $confirmActionedBy = null;

    public string $filterStatus = 'all';

    public function openGrnModal(int $poId): void
    {
        $po = PurchaseOrder::with(['items.produk', 'gudangTujuan'])->findOrFail($poId);

        // [P0-4] Hanya PO 'dikirim' yang boleh menerima GRN
        if ($po->status !== 'dikirim') {
            $this->dispatch('alert', ['type' => 'error', 'message' => "PO harus berstatus dikirim untuk diterima via GRN (status saat ini: {$po->status})"]);

            return;
        }

        // [P1-1] Scope cabang — PO harus milik cabang aktif di session
        $this->tolakJikaBukanCabang($po->gudangTujuan?->cabang_id, 'PO bukan milik cabang aktif');

        $itemForms = [];
        foreach ($po->items as $poItem) {
            $itemForms[] = [
                'produk_id' => $poItem->produk_id,
                'produk_nama' => $poItem->produk?->nama ?? ('#'.$poItem->produk_id),
                'sku_variant_id' => $poItem->sku_variant_id,
                'harga_beli' => (float) $poItem->harga_beli,
                'qty_po' => (int) $poItem->jumlah,
                'qty_received' => 0,
                // [F2-3] SN wajib utk produk sn=true
                'sn_flag' => (bool) ($poItem->produk?->sn ?? false),
                'sn' => '',
            ];
        }

        $this->grnForm = [
            'po_id' => $poId,
            'item_qty' => $itemForms,
            'catatan' => '',
        ];
        $this->showGrnModal = true;
    }

    public function cancelGrnModal(): void
    {
        $this->showGrnModal = false;
        $this->grnForm = ['po_id' => null, 'item_qty' => [], 'catatan' => ''];
    }

    public function simpanGrn(): void
    {
        $this->validate([
            'grnForm.po_id' => 'required|exists:purchase_order,id',
            'grnForm.item_qty' => 'required|array|min:1',
            'grnForm.item_qty.*.produk_id' => 'required|integer',
            'grnForm.item_qty.*.qty_received' => 'required|integer|min:0',
            'grnForm.item_qty.*.sn' => 'nullable|string', // [F2-3]
            'grnForm.catatan' => 'nullable|string',
        ]);

        $po = PurchaseOrder::with('gudangTujuan')->findOrFail($this->grnForm['po_id']);
        $cabangId = $po->gudangTujuan?->cabang_id;

        if (! $cabangId) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'PO harus memiliki gudang tujuan dengan cabang_id']);

            return;
        }

        // [P1-1] Scope cabang — jangan izinkan input GRN utk PO cabang lain
        $this->tolakJikaBukanCabang($cabangId, 'PO bukan milik cabang aktif');

        try {
            // [F2-3] Kumpulkan daftar SN + qty per baris — kunci komposit "produk|sku"
            // (P1-4: baris dgn produk sama + sku_variant berbeda tidak boleh tabrakan).
            $itemReceived = [];
            $snPerProduk = [];
            foreach ($this->grnForm['item_qty'] as $row) {
                $kunci = GrnService::kunciItem($row['produk_id'] ?? 0, $row['sku_variant_id'] ?? null);
                $itemReceived[$kunci] = (int) ($row['qty_received'] ?? 0);

                if ($row['sn_flag'] ?? false) {
                    $snPerProduk[$kunci] = app(NomorSeriService::class)
                        ->parseList((string) ($row['sn'] ?? ''));
                }
            }

            $grn = app(GrnService::class)->inputGudang(
                $po,
                $itemReceived,
                auth()->id(),
                $snPerProduk
            );
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);

            return;
        }

        if (($this->grnForm['catatan'] ?? '') !== '') {
            $grn->update(['catatan' => $this->grnForm['catatan']]);
        }

        $hasSelisih = collect($grn->item_qty_received ?? [])
            ->contains(fn ($item) => ($item['status'] ?? '') !== 'lengkap');

        $this->dispatch('alert', [
            'type' => $hasSelisih ? 'info' : 'success',
            'message' => $hasSelisih
                ? "GRN {$grn->no_grn} dibuat dengan selisih qty — menunggu approval."
                : "GRN {$grn->no_grn} diterima — stok & jurnal AP otomatis dibuat.",
        ]);

        $this->cancelGrnModal();
    }

    public function setujuiGrn(int $grnId): void
    {
        // [P0-1] Gate RBAC di method itu sendiri — jangan andalkan hanya dialog
        abort_unless(auth()->user()?->can('approve-workflow'), 403);

        $grn = $this->grnCabangAktif($grnId); // [P1-1] scope cabang
        $actionedBy = (int) ($this->confirmActionedBy ?? auth()->id());

        try {
            // [P1-2] Bila masih ada ApprovalRequest pending → wajib lewat engine F1-1
            // (hook selesaikanEntity finalisasi GRN + notifikasi pemohon, idempoten).
            $pending = $this->permintaanPending($grn->id);

            if ($pending) {
                app(ApprovalService::class)->proses($pending->id, 'approved', $actionedBy, null);
                $grn = $grn->fresh();
            } else {
                $grn = app(GrnService::class)->setujuiGrn($grn->id, $actionedBy);
            }
        } catch (ValidationException $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => collect($e->errors())->flatten()->implode(' ')]);
            $this->tutupKonfirmasi();

            return;
        }

        $this->dispatch('alert', [
            'type' => $grn->status === 'terima' ? 'success' : 'warning',
            'message' => match ($grn->status) {
                'terima' => "GRN {$grn->no_grn} disetujui — jurnal AP dan stok masuk telah tercatat.",
                'draft' => "GRN {$grn->no_grn} disetujui — menunggu approval berikutnya.",
                default => "GRN {$grn->no_grn} sudah pernah diproses.",
            },
        ]);

        $this->tutupKonfirmasi();
    }

    public function tolakGrn(int $grnId, string $catatan): void
    {
        // [P0-1] Gate RBAC di method itu sendiri — Tolak tanpa dialog tetap terjaga
        abort_unless(auth()->user()?->can('approve-workflow'), 403);

        $grn = $this->grnCabangAktif($grnId); // [P1-1] scope cabang
        $actionedBy = (int) ($this->confirmActionedBy ?? auth()->id());

        try {
            // [P1-2] Pending ApprovalRequest → lewat engine F1-1 supaya inbox tidak
            // tetap pending saat GRN ditolak dari tab.
            $pending = $this->permintaanPending($grn->id);

            if ($pending) {
                app(ApprovalService::class)->proses($pending->id, 'rejected', $actionedBy, $catatan);
                $grn = $grn->fresh();
            } else {
                $grn = app(GrnService::class)->tolakGrn($grn->id, $actionedBy, $catatan);
            }
        } catch (ValidationException $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => collect($e->errors())->flatten()->implode(' ')]);
            $this->tutupKonfirmasi();

            return;
        }

        $this->dispatch('alert', [
            'type' => $grn->status === 'ditolak' ? 'error' : 'warning',
            'message' => "GRN {$grn->no_grn}: ".($grn->status === 'ditolak' ? 'ditolak — '.$catatan : 'sudah pernah diproses'),
        ]);

        $this->tutupKonfirmasi();
    }

    public function bukaKonfirmasiSetuju(int $grnId): void
    {
        // Gate RBAC — sama dengan engine approval F1-1
        abort_unless(auth()->user()?->can('approve-workflow'), 403);

        $grn = $this->grnCabangAktif($grnId); // [P1-1] scope cabang

        $this->confirmGrnId = $grn->id;
        $this->confirmActionedBy = auth()->id();
        $this->confirmTitle = 'Setujui GRN';
        $this->confirmText = 'Setujui GRN ini? Jurnal AP akan terposting dan stok masuk dicatat.';
        $this->showConfirmDialog = true;
    }

    public function tutupKonfirmasi(): void
    {
        $this->showConfirmDialog = false;
        $this->confirmGrnId = null;
    }

    /** [P1-1] Load GRN dgn scope cabang session — id asing → 403. */
    private function grnCabangAktif(int $grnId): Grn
    {
        $grn = Grn::findOrFail($grnId);
        $this->tolakJikaBukanCabang($grn->cabang_id, 'GRN bukan milik cabang aktif');

        return $grn;
    }

    /** [P1-1] abort(403) bila cabang record ≠ cabang aktif session (skip bila session kosong — pola sibling tabs). */
    private function tolakJikaBukanCabang(int|string|null $recordCabangId, string $pesan): void
    {
        $cabangId = session('cabang_id');

        if ($cabangId !== null && $cabangId !== '' && (int) $recordCabangId !== (int) $cabangId) {
            abort(403, $pesan);
        }
    }

    /** [P1-2] ApprovalRequest pending utk GRN ini (null bila tidak ada / sudah diproses). */
    private function permintaanPending(int $grnId): ?ApprovalRequest
    {
        return ApprovalRequest::where('entity_type', 'grn')
            ->where('entity_id', $grnId)
            ->where('status', 'pending')
            ->first();
    }

    public function render()
    {
        $cabangId = session('cabang_id');

        // [P0-4] Hanya PO 'dikirim' siap diterima + [P1-1] scope cabang aktif
        $poList = PurchaseOrder::whereHas('gudangTujuan', function ($q) use ($cabangId) {
            $q->where('is_active', true)
                ->when($cabangId, fn ($qq) => $qq->where('cabang_id', $cabangId));
        })
            ->with(['supplier', 'gudangTujuan'])
            ->where('status', 'dikirim')
            ->latest()
            ->get();

        // [P1-1] GRN dibatasi cabang aktif session
        $grnList = Grn::with(['purchaseOrder.supplier', 'gudang', 'user'])
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->when($this->filterStatus !== 'all', fn ($q) => $q->where('status', $this->filterStatus))
            ->latest()
            ->paginate(15, pageName: 'grn');

        return view('modules.wms.livewire.grn-tab', [
            'poList' => $poList,
            'grnList' => $grnList,
            'filterStatus' => $this->filterStatus,
        ]);
    }
}
