<?php

namespace App\Modules\Wms\Services;

use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Wms\Models\Grn;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Workflow\Services\ApprovalService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * [F2-2] GRN (Goods Received Note) — penerimaan barang dari PO.
 *
 * - Hanya PO status 'dikirim' dan maksimal SATU GRN per PO (wajib lewat GRN —
 *   cegah double stok/jurnal via PoTab/API legacy).
 * - Qty sesuai (semua item lengkap) → langsung terima: jurnal AP + stok masuk
 *   + PO → 'diterima'.
 * - Qty partial/tolak → status draft + approval F1-1 (rule entity_type 'grn');
 *   setujui via ApprovalService::proses (hook) atau tab GRN → jurnal AP + stok masuk.
 * - Idempoten: transisi hanya dari status 'draft'; JurnalService::post menjaga no_jurnal unik.
 * - [F2-3] Produk sn=true: $snPerProduk wajib — jumlah SN == qty_received, unik,
 *   belum terdaftar; baris nomor_seri dibuat saat GRN finalisasi (kedua jalur).
 *   Konflik SN saat finalisasi approval → ValidationException (bukan Exception mentah).
 */
class GrnService
{
    protected JurnalService $jurnalService;

    protected ApprovalService $approvalService;

    protected NomorSeriService $nomorSeriService;

    public function __construct(
        JurnalService $jurnalService,
        ApprovalService $approvalService,
        NomorSeriService $nomorSeriService
    ) {
        $this->jurnalService = $jurnalService;
        $this->approvalService = $approvalService;
        $this->nomorSeriService = $nomorSeriService;
    }

    /**
     * Kunci komposit per baris PO — mencegah tabrakan baris dgn produk sama +
     * sku_variant berbeda (P1-4). Format: "produk_id|sku_variant_id" (0 bila null).
     */
    public static function kunciItem(int|string|null $produkId, int|string|null $skuVariantId = null): string
    {
        return ((int) $produkId).'|'.((int) ($skuVariantId ?? 0));
    }

    /**
     * Terima barang dari PO. Melempar Exception Indonesia bila:
     * - PO bukan status 'dikirim', atau sudah punya GRN (satu PO satu GRN, F2-2),
     * - qty diterima > qty PO (validasi PRD F2-2),
     * - [F2-3] jumlah SN ≠ qty_received utk produk sn=true (GRN tidak dibuat).
     *
     * @param  array<int|string, int>  $itemReceived  [kunci "produk|sku" (komposit) ATAU produk_id => qty]
     * @param  array<int|string, array<int, string>>  $snPerProduk  [kunci sama => [SN, ...]] — hanya utk produk sn=true
     */
    public function inputGudang(PurchaseOrder $po, array $itemReceived, ?int $userId = null, array $snPerProduk = []): Grn
    {
        $cabangId = $po->gudangTujuan?->cabang_id;
        if (! $cabangId) {
            throw new \Exception('PO harus memiliki gudang tujuan dengan cabang_id');
        }

        // [F2-2] Guard penerimaan ganda: hanya PO 'dikirim' & maksimal satu GRN per PO.
        if ($po->status !== 'dikirim') {
            throw new \Exception("PO harus berstatus dikirim untuk diterima via GRN (status saat ini: {$po->status})");
        }

        if (Grn::where('po_id', $po->id)->exists()) {
            throw new \Exception("PO {$po->no_po} sudah memiliki GRN — satu PO hanya boleh diterima sekali (wajib lewat GRN)");
        }

        $pemohon = ApprovalService::pemohon($userId ?? auth()->id());

        return DB::transaction(function () use ($po, $itemReceived, $snPerProduk, $pemohon, $cabangId) {
            $itemData = [];
            $totalHpp = 0;
            $needsApproval = false;

            foreach ($po->items as $poItem) {
                $kodeProduk = $poItem->produk_id;
                // [P1-4] Lookup kunci komposit "produk|sku" dulu; fallback produk_id (pemanggil lama).
                $kunci = static::kunciItem($kodeProduk, $poItem->sku_variant_id);
                $receivedQty = (int) ($itemReceived[$kunci] ?? $itemReceived[$kodeProduk] ?? 0);
                $poQty = (int) $poItem->jumlah;

                if ($receivedQty > $poQty) {
                    throw new \Exception("Qty diterima melebihi qty PO untuk produk ID {$kodeProduk} (PO: {$poQty}, diterima: {$receivedQty})");
                }

                // [F2-3] Validasi SN sebelum GRN dibuat — jumlah ≠ qty / dobel / sudah
                // terdaftar → Exception Indonesia, tidak ada GRN/approval/stok side effect.
                $snList = [];
                if ($receivedQty > 0 && ($poItem->produk?->sn ?? false)) {
                    $snList = array_values($snPerProduk[$kunci] ?? $snPerProduk[$kodeProduk] ?? []);
                    $this->nomorSeriService->validasiUntukGrn((int) $kodeProduk, $snList, $receivedQty);
                }

                if ($receivedQty >= $poQty) {
                    $status = 'lengkap';
                } elseif ($receivedQty > 0) {
                    $status = 'partial';
                } else {
                    $status = 'tolak';
                }

                $itemData[] = [
                    'produk_id' => $kodeProduk,
                    'sku_variant_id' => $poItem->sku_variant_id,
                    'qty_po' => $poQty,
                    'qty_received' => $receivedQty,
                    'harga_beli' => (float) $poItem->harga_beli,
                    'status' => $status,
                    'sn' => $snList, // [F2-3] disimpan utk finalisasi (auto & approval)
                ];

                $totalHpp += $receivedQty * (float) $poItem->harga_beli;

                if ($status !== 'lengkap') {
                    $needsApproval = true;
                }
            }

            $totalHpp = round($totalHpp, 2);

            $grn = Grn::create([
                'no_grn' => $this->jurnalService->generateNoJurnal('grn', $cabangId),
                'po_id' => $po->id,
                'cabang_id' => $cabangId,
                'gudang_id' => $po->gudang_tujuan_id,
                'user_id' => $pemohon,
                'status' => 'draft',
                'total_hpp' => $totalHpp,
                'item_qty_received' => $itemData,
            ]);

            $approval = null;
            if ($needsApproval) {
                $approval = $this->approvalService->ajukan(
                    'grn',
                    $grn->id,
                    $cabangId,
                    [
                        'amount' => $totalHpp,
                        'no_grn' => $grn->no_grn,
                        'po_id' => $po->id,
                        'cabang_id' => $cabangId,
                        'item_data' => json_encode($itemData),
                    ],
                    $pemohon
                );
            }

            // Qty sesuai ATAU rule approval tidak ada/disabled → finalisasi sekarang
            if (! $needsApproval || $approval === null) {
                $this->selesaikanGrn($grn, $pemohon, $needsApproval ? 'tanpa rule approval' : 'qty sesuai');
            }

            return $grn;
        });
    }

    public function setujuiGrn(int $grnId, ?int $actionedBy): Grn
    {
        $grn = Grn::findOrFail($grnId);

        return DB::transaction(function () use ($grn, $actionedBy) {
            $this->selesaikanGrn(
                $grn,
                $actionedBy ?? ApprovalService::pemohon(),
                'disetujui via approval'
            );

            return $grn->fresh();
        });
    }

    public function tolakGrn(int $grnId, ?int $actionedBy, string $catatan): Grn
    {
        $grn = Grn::findOrFail($grnId);

        if ($grn->status !== 'draft') {
            return $grn; // idempoten — sudah diproses
        }

        $grn->update([
            'status' => 'ditolak',
            'catatan' => trim(($grn->catatan ? $grn->catatan.' | ' : '').$catatan),
        ]);

        return $grn;
    }

    /**
     * Finalisasi GRN: jurnal AP (130-01/210-01) + stok masuk + status 'terima'.
     * Hanya bertransisi dari 'draft' — aman terhadap approve ganda (tab + inbox).
     */
    protected function selesaikanGrn(Grn $grn, ?int $actionedBy, string $keterangan): void
    {
        if ($grn->status !== 'draft') {
            return;
        }

        $totalHpp = (float) $grn->total_hpp;
        $noPo = $grn->purchaseOrder?->no_po ?? '-';

        // [F2-3] SN produk sn=true: validasi ulang + simpan baris nomor_seri 'tersedia'
        // SEBELUM jurnal — konflik (SN sudah terdaftar) → throw → rollback seluruh GRN.
        // Berlaku utk kedua jalur finalisasi: qty-sesuai auto-approve & setujuiGrn/approval.
        foreach ($grn->item_qty_received ?? [] as $item) {
            $qty = (int) ($item['qty_received'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $snList = array_values($item['sn'] ?? []);
            $produk = Produk::find($item['produk_id'] ?? null);
            if ($produk?->sn) {
                // [P1-11] Konflik SN saat approval jangan melempar Exception mentah (500 +
                // approval stuck) — ValidationException → pesan Indonesia, transaksi rollback,
                // request tetap pending dgn pesan bersih utk approver.
                try {
                    $this->nomorSeriService->validasiUntukGrn((int) $produk->id, $snList, $qty);
                } catch (\Exception $e) {
                    throw ValidationException::withMessages([
                        'msg' => str_contains($e->getMessage(), 'sudah terdaftar')
                            ? 'SN sudah terdaftar — tolak atau perbaiki GRN ('.$e->getMessage().')'
                            : $e->getMessage(),
                    ]);
                }
                $this->nomorSeriService->simpanDariGrn(
                    $grn,
                    (int) $produk->id,
                    isset($item['sku_variant_id']) ? (int) $item['sku_variant_id'] : null,
                    $snList
                );
            }
        }

        if ($totalHpp > 0) {
            $this->jurnalService->post(
                $grn->no_grn,
                now(),
                'grn',
                [
                    ['akun_kode' => '130-01', 'debit' => $totalHpp, 'kredit' => 0],
                    ['akun_kode' => '210-01', 'debit' => 0, 'kredit' => $totalHpp],
                ],
                "GRN {$grn->no_grn} — {$keterangan} (PO {$noPo})",
                (int) $grn->cabang_id,
                $actionedBy,
                Grn::class,
                $grn->id
            );
        }

        foreach ($grn->item_qty_received ?? [] as $item) {
            $qty = (int) ($item['qty_received'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $this->tambahStok($grn, $item, $qty, $actionedBy, $noPo);
        }

        $grn->update(['status' => 'terima']);

        // [F2-2] PO → 'diterima' saat finalisasi — cegah penerimaan ganda
        // via jalur legacy (PoTab::terimaPo / API updatePoStatus).
        $po = $grn->purchaseOrder()->first();
        if ($po && $po->status !== 'diterima') {
            $po->update(['status' => 'diterima']);
        }
    }

    /**
     * Stok masuk per item — pola kanonik (mirror TransferTab::kirimTransfer):
     * lockForUpdate + StokItem.jumlah + StokLog (sebelum/setelah) + StockMutationLog (SOT).
     * Duplikat kunci unik (race create) → QueryException diterjemahkan ke pesan Indonesia.
     */
    protected function tambahStok(Grn $grn, array $item, int $qty, ?int $actionedBy, string $noPo): void
    {
        $attributes = [
            'gudang_id' => $grn->gudang_id,
            'produk_id' => $item['produk_id'],
            'sku_variant_id' => $item['sku_variant_id'] ?? null,
        ];

        try {
            // [P1-7] Ambil baris terkunci dulu — read-modify-write wajib lock (anti race T-13).
            $stok = StokItem::where($attributes)->lockForUpdate()->first();

            if (! $stok) {
                try {
                    StokItem::create($attributes + ['jumlah' => 0, 'jumlah_minimum' => 0]);
                } catch (QueryException $e) {
                    if (! $this->kunciDuplikat($e)) {
                        throw $e;
                    }
                    // baris dibuat proses lain bersamaan → lanjut ambil dgn lock
                }
                $stok = StokItem::where($attributes)->lockForUpdate()->first();
            }

            if (! $stok) {
                throw new \Exception('Gagal menyimpan stok — data stok barang tidak dapat disimpan, coba lagi');
            }

            $sebelum = (int) $stok->jumlah;
            $setelah = $sebelum + $qty;
            $stok->update(['jumlah' => $setelah]);
        } catch (QueryException $e) {
            if ($this->kunciDuplikat($e)) {
                throw new \Exception('Data stok gudang utk barang ini sudah ada (duplikat) — muat ulang halaman lalu coba lagi');
            }

            throw $e;
        }

        $harga = (float) ($item['harga_beli'] ?? 0);

        StokLog::create([
            'gudang_id' => $grn->gudang_id,
            'produk_id' => $item['produk_id'],
            'sku_variant_id' => $item['sku_variant_id'] ?? null,
            'user_id' => $actionedBy,
            'jenis' => 'GRN',
            'referensi_tipe' => Grn::class,
            'referensi_id' => $grn->id,
            'jumlah_sebelum' => $sebelum,
            'perubahan' => $qty,
            'jumlah_setelah' => $setelah,
            'catatan' => "GRN {$grn->no_grn} — {$qty} x Rp ".number_format($harga, 0, ',', '.')." (PO {$noPo})",
        ]);

        StockMutationLog::create([
            'produk_id' => $item['produk_id'],
            'sku_variant_id' => $item['sku_variant_id'] ?? null,
            'gudang_id' => $grn->gudang_id,
            'delta' => $qty,
            'sumber' => 'grn',
            'referensi_tipe' => Grn::class,
            'referensi_id' => $grn->id,
            'terjadi_at' => now(),
        ]);
    }

    /** Deteksi pelanggaran kunci unik/duplikat dari QueryException (MySQL 23000 / SQLite UNIQUE). */
    private function kunciDuplikat(QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? null) === '23000'
            || str_contains(strtolower($e->getMessage()), 'unique')
            || str_contains(strtolower($e->getMessage()), 'duplicate');
    }
}
