<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Rbac\Traits\PunyaRiwayatAktivitas;
use App\Modules\Wms\Models\Grn;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Services\GrnService;
use App\Modules\Wms\Services\NomorSeriService;
use App\Modules\Workflow\Models\ApprovalRequest;
use App\Modules\Workflow\Services\ApprovalService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
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

    /**
     * [B-15c] Batas tabel "PO Siap Diterima". Tanpa batas, daftar PO 'dikirim'
     * menarik seluruh riwayat tiap render (ratusan baris) padahal tabel ini bukan
     * paginator. Dipotong → user diberi tahu sekali per halaman (properti di bawah).
     */
    public const PO_SIAP_DITERIMA_LIMIT = 50;

    /** [B-15c] Supaya toast "dipotong" tidak diulang tiap re-render. */
    public bool $sudahPeringatPoDipotong = false;

    public bool $showGrnModal = false;

    public string $barcodeInput = '';

    public ?int $activeItemIndex = null;

    public string $activeItemQty = '';

    public array $gudangRaks = [];

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

    public bool $showTolakDialog = false;

    public ?int $tolakGrnId = null;

    public ?string $tolakGrnNo = null;

    public string $alasanTolak = '';

    public bool $showTolakPoDialog = false;

    public ?int $tolakPoId = null;

    public ?string $tolakPoNo = null;

    public ?string $tolakPoSupplier = null;

    public string $alasanTolakPo = '';

    public string $filterStatus = 'all';

    public ?int $detailGrnId = null;

    public function mount(): void
    {
        if ($poId = session('wms_grn_po_id')) {
            $this->openGrnModal((int) $poId);
        }
    }

    public function bukaDetailGrn(int $id): void
    {
        $this->detailGrnId = $id;
    }

    public function tutupDetailGrn(): void
    {
        $this->detailGrnId = null;
    }

    #[On('buka-grn-po')]
    public function onBukaGrnPo(int $poId): void
    {
        $this->openGrnModal($poId);
    }

    public function openGrnModal(int $poId): void
    {
        $po = PurchaseOrder::with(['items.produk.skuVariants', 'items.skuVariant', 'gudangTujuan'])->findOrFail($poId);

        // [P0-4] Hanya PO 'dikirim' yang boleh menerima GRN
        if ($po->status !== 'dikirim') {
            $this->dispatch('alert', ['type' => 'error', 'message' => "PO harus berstatus dikirim untuk diterima via GRN (status saat ini: {$po->status})"]);

            return;
        }

        // [P1-1] Scope cabang — PO harus milik cabang aktif di session
        $this->tolakJikaBukanCabang($po->gudangTujuan?->cabang_id, 'PO bukan milik cabang aktif');

        if ($po->metode_bayar === 'tunai' && ! GrnService::userBolehOtorisasiKas(auth()->user())) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => 'PO ini bertipe pembayaran tunai. Anda tidak memiliki role/hak akses untuk mengotorisasi pembayaran kas (memerlukan role Kasir, Finance, atau Admin Toko).',
            ]);

            return;
        }

        $this->gudangRaks = Rak::where('gudang_id', $po->gudang_tujuan_id)
            ->where('is_active', true)
            ->orderBy('nama')
            ->get(['id', 'nama', 'kode'])
            ->toArray();

        $itemForms = [];
        foreach ($po->items as $poItem) {
            $prod = $poItem->produk;
            $var = $poItem->skuVariant;

            // Cari rak default dari stok yang sudah ada di gudang tujuan
            $stokExisting = StokItem::where('produk_id', $poItem->produk_id)
                ->where('gudang_id', $po->gudang_tujuan_id)
                ->first();

            $konversi = max(0.0001, (float) ($poItem->konversi ?? 1));
            $satuanNama = $poItem->satuan ?: ($prod?->satuan ?: 'pcs');
            $qtyPoSatuan = (float) $poItem->jumlah;
            $rawJumlahDasar = (float) ($poItem->jumlah_dasar ?? 0);
            $qtyPoDasar = $rawJumlahDasar > 0
                ? (int) round($rawJumlahDasar)
                : (int) round($qtyPoSatuan * $konversi);

            $itemForms[] = [
                'produk_id' => $poItem->produk_id,
                'produk_nama' => $prod?->nama ?? ('#'.$poItem->produk_id),
                'satuan' => $satuanNama,
                'satuan_dasar' => $prod?->satuan ?: 'pcs',
                'konversi' => $konversi,
                'barcode' => $prod?->barcode ?: ($var?->barcode ?: ''),
                'sku' => $var?->sku ?: '',
                'sku_variant_id' => $poItem->sku_variant_id,
                'harga_beli' => (float) $poItem->harga_beli,
                'qty_po' => $qtyPoSatuan,
                'qty_po_dasar' => $qtyPoDasar,
                'qty_received' => 0,
                'rak_id' => $stokExisting?->rak_id,
                // [F2-3] SN wajib utk produk sn=true
                'sn_flag' => (bool) ($prod?->sn ?? false),
                'sn' => '',
            ];
        }

        $this->barcodeInput = '';
        $this->activeItemIndex = null;
        $this->activeItemQty = '';
        $this->grnForm = [
            'po_id' => $poId,
            'is_cash' => $po->metode_bayar === 'tunai',
            'akun_kas_bank' => $po->akun_kas_bank ?: '110-01',
            'dibayar_oleh' => auth()->user()?->name ?? 'Kasir / Staf',
            'item_qty' => $itemForms,
            'catatan' => '',
        ];
        $this->showGrnModal = true;
    }

    public function scanBarcodeDirect(string $code): void
    {
        $this->prosesBarcode($code);
    }

    public function prosesBarcode(?string $code = null): void
    {
        $barcode = trim($code ?: $this->barcodeInput);
        $this->barcodeInput = '';

        if (empty($barcode) || empty($this->grnForm['item_qty'])) {
            return;
        }

        $matchedIndex = null;
        foreach ($this->grnForm['item_qty'] as $idx => $item) {
            if (! empty($item['barcode']) && strcasecmp($item['barcode'], $barcode) === 0) {
                $matchedIndex = $idx;
                break;
            }
            if (! empty($item['sku']) && strcasecmp($item['sku'], $barcode) === 0) {
                $matchedIndex = $idx;
                break;
            }
            if (! empty($item['produk_nama']) && stripos($item['produk_nama'], $barcode) !== false) {
                $matchedIndex = $idx;
                break;
            }
        }

        if ($matchedIndex === null) {
            $prod = Produk::where('barcode', $barcode)->first();
            if (! $prod) {
                $varian = SkuVariant::where('barcode', $barcode)->orWhere('sku', $barcode)->first();
                if ($varian) {
                    $prod = $varian->produk;
                }
            }

            if ($prod) {
                foreach ($this->grnForm['item_qty'] as $idx => $item) {
                    if ((int) $item['produk_id'] === (int) $prod->id) {
                        $matchedIndex = $idx;
                        break;
                    }
                }
            }
        }

        if ($matchedIndex === null) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => "Barcode/SKU '{$barcode}' tidak ditemukan pada item PO ini!",
            ]);
            $this->dispatch('ute:barcode-not-found');

            return;
        }

        $this->activeItemIndex = $matchedIndex;
        $item = &$this->grnForm['item_qty'][$matchedIndex];
        $qtyPo = (int) $item['qty_po'];
        $currentReceived = (int) $item['qty_received'];

        if ($currentReceived >= $qtyPo) {
            $this->activeItemQty = (string) $qtyPo;
            $this->dispatch('alert', [
                'type' => 'warning',
                'message' => "Item '{$item['produk_nama']}' sudah diterima lengkap ({$qtyPo}/{$qtyPo} pcs).",
            ]);
            $this->dispatch('ute:barcode-beep', ['success' => false]);
            $this->dispatch('focus-active-grn-qty');

            return;
        }

        $newReceived = $currentReceived + 1;
        $item['qty_received'] = $newReceived;
        $this->activeItemQty = (string) $newReceived;

        if ($item['sn_flag'] ?? false) {
            if (strcasecmp($barcode, $item['barcode'] ?? '') !== 0 && strcasecmp($barcode, $item['sku'] ?? '') !== 0) {
                $existingSn = trim($item['sn'] ?? '');
                $item['sn'] = $existingSn === '' ? $barcode : $existingSn."\n".$barcode;
            }
        }

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => "✓ Scan berhasil: {$item['produk_nama']} ({$item['qty_received']}/{$qtyPo} pcs)",
        ]);
        $this->dispatch('ute:barcode-beep', ['success' => true]);
        $this->dispatch('focus-active-grn-qty');
    }

    public function pilihItemGrn(int $idx): void
    {
        if (isset($this->grnForm['item_qty'][$idx])) {
            $this->activeItemIndex = $idx;
            $rcv = (int) ($this->grnForm['item_qty'][$idx]['qty_received'] ?? 0);
            $this->activeItemQty = $rcv > 0 ? (string) $rcv : '1';
            if ($rcv === 0) {
                $this->grnForm['item_qty'][$idx]['qty_received'] = 1;
            }
            $this->dispatch('focus-active-grn-qty');
        }
    }

    public function tambahActiveQtyGrn(): void
    {
        if ($this->activeItemIndex !== null && isset($this->grnForm['item_qty'][$this->activeItemIndex])) {
            $item = &$this->grnForm['item_qty'][$this->activeItemIndex];
            $max = (int) $item['qty_po'];
            $current = (int) $item['qty_received'];
            $new = min($max, $current + 1);
            $item['qty_received'] = $new;
            $this->activeItemQty = (string) $new;
        }
    }

    public function kurangActiveQtyGrn(): void
    {
        if ($this->activeItemIndex !== null && isset($this->grnForm['item_qty'][$this->activeItemIndex])) {
            $item = &$this->grnForm['item_qty'][$this->activeItemIndex];
            $current = (int) $item['qty_received'];
            $new = max(0, $current - 1);
            $item['qty_received'] = $new;
            $this->activeItemQty = (string) $new;
        }
    }

    public function updatedActiveItemQty($val): void
    {
        if ($this->activeItemIndex !== null && isset($this->grnForm['item_qty'][$this->activeItemIndex])) {
            $item = &$this->grnForm['item_qty'][$this->activeItemIndex];
            $max = (int) $item['qty_po'];
            $qty = $val === '' ? 0 : max(0, min($max, (int) $val));
            $item['qty_received'] = $qty;
        }
    }

    public function setActiveItemLengkap(): void
    {
        if ($this->activeItemIndex !== null && isset($this->grnForm['item_qty'][$this->activeItemIndex])) {
            $item = &$this->grnForm['item_qty'][$this->activeItemIndex];
            $max = (int) $item['qty_po'];
            $item['qty_received'] = $max;
            $this->activeItemQty = (string) $max;
        }
    }

    public function simpanDanLanjutGrn(): void
    {
        if ($this->activeItemIndex !== null && isset($this->grnForm['item_qty'][$this->activeItemIndex])) {
            $item = &$this->grnForm['item_qty'][$this->activeItemIndex];
            $max = (int) $item['qty_po'];
            $qty = is_numeric($this->activeItemQty) ? max(0, min($max, (int) $this->activeItemQty)) : 0;
            $item['qty_received'] = $qty;
            $nama = $item['produk_nama'];
            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Penerimaan '{$nama}' ({$qty}/{$max} {$item['satuan']}) disimpan. Siap scan barang berikutnya.",
            ]);
        }

        $this->activeItemIndex = null;
        $this->activeItemQty = '';
        $this->barcodeInput = '';
        $this->dispatch('focus-grn-scan-input');
    }

    public function lewatiActiveGrn(): void
    {
        $this->activeItemIndex = null;
        $this->activeItemQty = '';
        $this->barcodeInput = '';
        $this->dispatch('focus-grn-scan-input');
    }

    public function terimaSemuaItem(): void
    {
        foreach ($this->grnForm['item_qty'] as $idx => $item) {
            $this->grnForm['item_qty'][$idx]['qty_received'] = (int) $item['qty_po'];
        }
        if ($this->activeItemIndex !== null && isset($this->grnForm['item_qty'][$this->activeItemIndex])) {
            $this->activeItemQty = (string) $this->grnForm['item_qty'][$this->activeItemIndex]['qty_received'];
        }
    }

    public function setItemLengkap(int $idx): void
    {
        if (isset($this->grnForm['item_qty'][$idx])) {
            $this->grnForm['item_qty'][$idx]['qty_received'] = (int) $this->grnForm['item_qty'][$idx]['qty_po'];
            if ($this->activeItemIndex === $idx) {
                $this->activeItemQty = (string) $this->grnForm['item_qty'][$idx]['qty_received'];
            }
        }
    }

    public function tambahQtyItem(int $idx, int $delta = 1): void
    {
        if (isset($this->grnForm['item_qty'][$idx])) {
            $max = (int) $this->grnForm['item_qty'][$idx]['qty_po'];
            $current = (int) $this->grnForm['item_qty'][$idx]['qty_received'];
            $newVal = max(0, min($max, $current + $delta));
            $this->grnForm['item_qty'][$idx]['qty_received'] = $newVal;
            if ($this->activeItemIndex === $idx) {
                $this->activeItemQty = (string) $newVal;
            }
        }
    }

    public function cancelGrnModal(): void
    {
        $this->showGrnModal = false;
        $this->activeItemIndex = null;
        $this->activeItemQty = '';
        $this->barcodeInput = '';
        $this->grnForm = ['po_id' => null, 'item_qty' => [], 'catatan' => ''];
    }

    public function simpanGrn(): void
    {
        abort_unless(
            auth()->user()?->can('wms.receive-po') || auth()->user()?->can('wms.create'),
            403,
            'Anda tidak memiliki izin penerimaan barang (GRN).'
        );

        $this->validate([
            'grnForm.po_id' => 'required|exists:purchase_order,id',
            'grnForm.akun_kas_bank' => 'nullable|string',
            'grnForm.dibayar_oleh' => 'nullable|string|max:100',
            'grnForm.item_qty' => 'required|array|min:1',
            'grnForm.item_qty.*.produk_id' => 'required|integer',
            'grnForm.item_qty.*.qty_received' => 'required|integer|min:0',
            'grnForm.item_qty.*.harga_beli' => 'nullable|numeric|min:0',
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

        if ($po->metode_bayar === 'tunai') {
            if (! GrnService::userBolehOtorisasiKas(auth()->user())) {
                $this->dispatch('alert', [
                    'type' => 'error',
                    'message' => 'Anda tidak memiliki role/hak akses untuk mengotorisasi transaksi pembayaran kas tunai PO ini. Memerlukan role Kasir, Finance, atau Admin Toko.',
                ]);

                return;
            }
            $this->grnForm['dibayar_oleh'] = auth()->user()?->name ?? 'Kasir / Staf';
        }

        try {
            // [F2-3] Kumpulkan daftar SN + qty per baris — kunci komposit "produk|sku"
            // (P1-4: baris dgn produk sama + sku_variant berbeda tidak boleh tabrakan).
            $itemReceived = [];
            $hargaPerProduk = [];
            $snPerProduk = [];
            $itemRakId = [];
            foreach ($this->grnForm['item_qty'] as $row) {
                $kunci = GrnService::kunciItem($row['produk_id'] ?? 0, $row['sku_variant_id'] ?? null);
                $itemReceived[$kunci] = (int) ($row['qty_received'] ?? 0);
                if (isset($row['harga_beli']) && $row['harga_beli'] !== null && $row['harga_beli'] !== '') {
                    $hargaPerProduk[$kunci] = (float) $row['harga_beli'];
                }

                if (! empty($row['rak_id'])) {
                    $itemRakId[$kunci] = (int) $row['rak_id'];
                }

                if ($row['sn_flag'] ?? false) {
                    $snPerProduk[$kunci] = app(NomorSeriService::class)
                        ->parseList((string) ($row['sn'] ?? ''));
                }
            }

            $grn = app(GrnService::class)->inputGudang(
                $po,
                $itemReceived,
                auth()->id(),
                $snPerProduk,
                $hargaPerProduk,
                $itemRakId,
                $this->grnForm['akun_kas_bank'] ?? null,
                $this->grnForm['dibayar_oleh'] ?? null
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

        if ($grn->purchaseOrder?->metode_bayar === 'tunai' && ! GrnService::userBolehOtorisasiKas(auth()->user())) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => 'GRN ini terkait pembayaran PO Tunai. Anda tidak memiliki role/hak akses untuk mengotorisasi transaksi kas.',
            ]);

            return;
        }

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

    public function bukaKonfirmasiTolak(int $grnId): void
    {
        abort_unless(auth()->user()?->can('approve-workflow'), 403);

        $grn = $this->grnCabangAktif($grnId);

        $this->tolakGrnId = $grn->id;
        $this->tolakGrnNo = $grn->no_grn;
        $this->alasanTolak = '';
        $this->resetErrorBag('alasanTolak');
        $this->showTolakDialog = true;
    }

    public function tutupKonfirmasiTolak(): void
    {
        $this->showTolakDialog = false;
        $this->tolakGrnId = null;
        $this->tolakGrnNo = null;
        $this->alasanTolak = '';
        $this->resetErrorBag('alasanTolak');
    }

    public function konfirmasiTolakGrn(): void
    {
        if (! $this->tolakGrnId) {
            return;
        }

        $alasan = trim($this->alasanTolak);
        if ($alasan === '') {
            $this->addError('alasanTolak', 'Alasan penolakan GRN wajib diisi agar tercatat di audit trail');

            return;
        }

        $grnId = $this->tolakGrnId;
        $this->tutupKonfirmasiTolak();
        $this->tolakGrn($grnId, $alasan);
    }

    public function bukaKonfirmasiTolakPo(int $poId): void
    {
        $po = PurchaseOrder::with(['supplier', 'gudangTujuan'])->findOrFail($poId);
        $this->tolakJikaBukanCabang($po->gudangTujuan?->cabang_id, 'PO bukan milik cabang aktif');

        $this->tolakPoId = $po->id;
        $this->tolakPoNo = $po->no_po;
        $this->tolakPoSupplier = $po->supplier?->nama;
        $this->alasanTolakPo = '';
        $this->resetErrorBag('alasanTolakPo');
        $this->showTolakPoDialog = true;
    }

    public function tutupKonfirmasiTolakPo(): void
    {
        $this->showTolakPoDialog = false;
        $this->tolakPoId = null;
        $this->tolakPoNo = null;
        $this->tolakPoSupplier = null;
        $this->alasanTolakPo = '';
        $this->resetErrorBag('alasanTolakPo');
    }

    public function konfirmasiTolakPo(): void
    {
        if (! $this->tolakPoId) {
            return;
        }

        $alasan = trim($this->alasanTolakPo);
        if ($alasan === '') {
            $this->addError('alasanTolakPo', 'Alasan penolakan PO wajib diisi agar tercatat di audit trail');

            return;
        }

        $po = PurchaseOrder::with('gudangTujuan')->findOrFail($this->tolakPoId);
        $this->tolakJikaBukanCabang($po->gudangTujuan?->cabang_id, 'PO bukan milik cabang aktif');

        $catatanLama = $po->catatan ? $po->catatan.' | ' : '';
        $po->update([
            'status' => 'dibatalkan',
            'catatan' => $catatanLama.'[Ditolak di Pintu Gudang: '.$alasan.']',
        ]);

        $this->dispatch('alert', [
            'type' => 'warning',
            'message' => "PO {$po->no_po} ditolak & dibatalkan: {$alasan}",
        ]);

        $this->tutupKonfirmasiTolakPo();
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
        // [B-15c] Dipotong 50 baris terbaru (tabel ini bukan paginator) + toast
        // Indonesia sekali per halaman kalau memang ada yang tidak ditampilkan.
        $poList = PurchaseOrder::whereHas('gudangTujuan', function ($q) use ($cabangId) {
            $q->where('is_active', true)
                ->when($cabangId, fn ($qq) => $qq->where('cabang_id', $cabangId));
        })
            ->with(['supplier', 'gudangTujuan'])
            ->where('status', 'dikirim')
            ->latest()
            ->limit(self::PO_SIAP_DITERIMA_LIMIT + 1)
            ->get();

        $poDipotong = $poList->count() > self::PO_SIAP_DITERIMA_LIMIT;
        $poList = $poList->take(self::PO_SIAP_DITERIMA_LIMIT);

        if ($poDipotong && ! $this->sudahPeringatPoDipotong) {
            $this->sudahPeringatPoDipotong = true;

            $this->dispatch('alert', [
                'type' => 'info',
                'message' => 'Daftar PO siap diterima dibatasi '.self::PO_SIAP_DITERIMA_LIMIT.
                    ' PO terbaru. Saring dulu dengan filter status di bawah bila perlu melihat PO lama.',
            ]);
        }

        // [P1-1] GRN dibatasi cabang aktif session
        $baseGrnQuery = Grn::query()->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId));
        $countDraft = (clone $baseGrnQuery)->where('status', 'draft')->count();
        $countTerima = (clone $baseGrnQuery)->where('status', 'terima')->count();
        $countDitolak = (clone $baseGrnQuery)->where('status', 'ditolak')->count();
        $countTotal = (clone $baseGrnQuery)->count();
        $countPoSiapTerima = $poList->count();

        $grnList = (clone $baseGrnQuery)
            ->with(['purchaseOrder.supplier', 'gudang', 'user'])
            ->when($this->filterStatus !== 'all', fn ($q) => $q->where('status', $this->filterStatus))
            ->latest()
            ->paginate(15, pageName: 'grn');

        $detailGrn = null;
        if ($this->detailGrnId) {
            $detailGrn = Grn::with(['purchaseOrder.supplier', 'purchaseOrder.items.produk', 'gudang', 'user'])
                ->find($this->detailGrnId);
        }

        return view('modules.wms.livewire.grn-tab', [
            'poList' => $poList,
            'grnList' => $grnList,
            'detailGrn' => $detailGrn,
            'filterStatus' => $this->filterStatus,
            'countDraft' => $countDraft,
            'countTerima' => $countTerima,
            'countDitolak' => $countDitolak,
            'countTotal' => $countTotal,
            'countPoSiapTerima' => $countPoSiapTerima,
            'akunKasBankList' => AkunCOA::whereIn('kelompok', ['kas', 'bank'])->where('is_active', true)->orderBy('kode')->get(),
        ]);
    }
}
