<?php

namespace App\Modules\Rbac\Services;

use App\Models\User;
use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\AktivitasLog;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Models\Grn;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\ReturnPembelian;
use App\Modules\Wms\Models\StokOpname;
use App\Modules\Wms\Models\StokTransfer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Layanan Pencatatan Audit Trail Terpusat Berbasis Transaksi (Transaction-Centric)
 * dengan Dual-Indexing pada Metadata (properties->produk_ids).
 *
 * Prinsip:
 * 1. Transaction-Centric: Subjek utama adalah dokumen transaksi (Transaksi, ReturnPenjualan, PO, GRN, dll).
 * 2. Dual-Indexing: Menyimpan array `produk_ids` dan rincian item di `properties` sehingga riwayat
 *    produk dapat menelusuri seluruh transaksi yang melibatkan produk tersebut.
 * 3. Human-Readable: Narasi dan ringkasan dalam Bahasa Indonesia yang lugas dan mudah dipahami orang awam.
 * 4. Scoped Cabang: Setiap log menyimpan `cabang_id` agar aman dari kebocoran data antar cabang.
 */
class TransactionActivityLogService
{
    /**
     * Catat Transaksi Penjualan Kasir (POS).
     */
    public function catatPenjualan(Transaksi $transaksi): ?AktivitasLog
    {
        try {
            $transaksi->loadMissing(['items.produk', 'kasir', 'pelanggan', 'cabang']);

            $items = [];
            $produkIds = [];
            $itemSummaries = [];

            foreach ($transaksi->items as $item) {
                $pId = (int) $item->produk_id;
                $produkIds[] = $pId;
                $nama = $item->produk?->nama ?? "Produk #{$pId}";
                $sku = $item->produk?->sku ?? $item->produk?->barcode ?? '-';
                $qty = (int) $item->jumlah;
                $harga = (float) $item->harga_satuan;
                $subtotal = (float) $item->subtotal;

                $items[] = [
                    'produk_id' => $pId,
                    'sku' => $sku,
                    'nama_produk' => $nama,
                    'qty' => $qty,
                    'harga_satuan' => $harga,
                    'subtotal' => $subtotal,
                ];

                $itemSummaries[] = "{$nama} (x{$qty})";
            }

            $produkIds = array_values(array_unique($produkIds));
            $totalNominal = (float) $transaksi->total_akhir;
            $pelangganNama = $transaksi->pelanggan?->nama ?? 'Pelanggan Umum';
            $kasirNama = $transaksi->kasir?->name ?? 'Kasir';
            $metodeBayar = strtoupper(str_replace('_', ' ', (string) $transaksi->metode_bayar));
            $totalQty = array_sum(array_column($items, 'qty'));

            $ringkasan = sprintf(
                'Penjualan kasir oleh %s kepada %s: %d barang (%s) senilai Rp %s dibayar secara %s.',
                $kasirNama,
                $pelangganNama,
                $totalQty,
                implode(', ', array_slice($itemSummaries, 0, 3)).(count($itemSummaries) > 3 ? ' dkk.' : ''),
                number_format($totalNominal, 0, ',', '.'),
                $metodeBayar
            );

            $properties = [
                'tipe_transaksi' => 'penjualan',
                'no_referensi' => $transaksi->no_transaksi,
                'cabang_id' => $transaksi->cabang_id,
                'produk_ids' => $produkIds,
                'ringkasan' => $ringkasan,
                'items' => $items,
                'total_akhir' => $totalNominal,
                'metode_bayar' => $transaksi->metode_bayar,
                'pelanggan' => $pelangganNama,
                'kasir' => $kasirNama,
            ];

            return $this->tulisLog(
                subject: $transaksi,
                event: 'penjualan',
                description: "Penjualan Kasir {$transaksi->no_transaksi} ({$pelangganNama}) - Rp ".number_format($totalNominal, 0, ',', '.'),
                properties: $properties,
                causer: $transaksi->kasir ?: auth()->user(),
                cabangId: $transaksi->cabang_id
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat log penjualan: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Catat Pembatalan / Void Transaksi Kasir.
     */
    public function catatVoid(Transaksi $transaksi, ?User $voidBy = null, string $alasan = ''): ?AktivitasLog
    {
        try {
            $transaksi->loadMissing(['items.produk', 'pelanggan', 'kasir']);

            $items = [];
            $produkIds = [];
            $itemSummaries = [];

            foreach ($transaksi->items as $item) {
                $pId = (int) $item->produk_id;
                $produkIds[] = $pId;
                $nama = $item->produk?->nama ?? "Produk #{$pId}";
                $qty = (int) $item->jumlah;

                $items[] = [
                    'produk_id' => $pId,
                    'sku' => $item->produk?->sku ?? $item->produk?->barcode ?? '-',
                    'nama_produk' => $nama,
                    'qty' => $qty,
                    'subtotal' => (float) $item->subtotal,
                ];

                $itemSummaries[] = "{$nama} (x{$qty})";
            }

            $produkIds = array_values(array_unique($produkIds));
            $operator = $voidBy?->name ?? (auth()->user()?->name ?? 'Supervisor');
            $totalNominal = (float) $transaksi->total_akhir;

            $ringkasan = sprintf(
                'Transaksi %s senilai Rp %s dibatalkan (VOID) oleh %s. Alasan: "%s". Seluruh stok fisik (%d item) dikembalikan ke gudang.',
                $transaksi->no_transaksi,
                number_format($totalNominal, 0, ',', '.'),
                $operator,
                $alasan ?: 'Tanpa keterangan',
                count($items)
            );

            $properties = [
                'tipe_transaksi' => 'void',
                'no_referensi' => $transaksi->no_transaksi,
                'cabang_id' => $transaksi->cabang_id,
                'produk_ids' => $produkIds,
                'ringkasan' => $ringkasan,
                'alasan' => $alasan,
                'void_by' => $operator,
                'items' => $items,
                'total_akhir' => $totalNominal,
            ];

            return $this->tulisLog(
                subject: $transaksi,
                event: 'void',
                description: "Void Transaksi {$transaksi->no_transaksi} oleh {$operator}: {$alasan}",
                properties: $properties,
                causer: $voidBy ?: auth()->user(),
                cabangId: $transaksi->cabang_id
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat log void: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Catat Retur Penjualan Konsumen (Refund atau Tukar Produk).
     */
    public function catatReturPenjualan(ReturnPenjualan $retur): ?AktivitasLog
    {
        try {
            $retur->loadMissing([
                'items.produk',
                'transaksi.kasir',
                'pelanggan',
                'tukarTransaksi.items.produk',
            ]);

            $itemsRetur = [];
            $produkIds = [];
            $returSummaries = [];

            foreach ($retur->items as $item) {
                $pId = (int) $item->produk_id;
                $produkIds[] = $pId;
                $nama = $item->produk?->nama ?? "Produk #{$pId}";
                $qty = (int) $item->jumlah;
                $kondisi = $item->kondisi_barang ?? 'rusak';
                $alasan = $item->alasan ?? $retur->alasan ?? '-';

                $itemsRetur[] = [
                    'produk_id' => $pId,
                    'sku' => $item->produk?->sku ?? $item->produk?->barcode ?? '-',
                    'nama_produk' => $nama,
                    'qty' => $qty,
                    'harga_satuan' => (float) $item->harga_satuan,
                    'subtotal' => (float) $item->subtotal,
                    'kondisi' => $kondisi,
                    'alasan' => $alasan,
                    'tipe_penyelesaian' => $item->tipe_penyelesaian ?? $retur->tipe_penyelesaian,
                ];

                $returSummaries[] = "{$nama} (x{$qty}, kondisi: {$kondisi})";
            }

            // Dual-indexing pada produk pengganti jika ada tukar produk
            $itemsTukar = [];
            if ($retur->tukarTransaksi && $retur->tukarTransaksi->items) {
                foreach ($retur->tukarTransaksi->items as $tItem) {
                    $pId = (int) $tItem->produk_id;
                    $produkIds[] = $pId;
                    $nama = $tItem->produk?->nama ?? "Produk #{$pId}";
                    $itemsTukar[] = [
                        'produk_id' => $pId,
                        'sku' => $tItem->produk?->sku ?? $tItem->produk?->barcode ?? '-',
                        'nama_produk' => $nama,
                        'qty' => (int) $tItem->jumlah,
                        'harga_satuan' => (float) $tItem->harga_satuan,
                        'subtotal' => (float) $tItem->subtotal,
                    ];
                }
            } elseif (! empty($retur->tukar_items_payload) && is_array($retur->tukar_items_payload)) {
                foreach ($retur->tukar_items_payload as $tItem) {
                    $pId = (int) ($tItem['produk_id'] ?? 0);
                    if ($pId > 0) {
                        $produkIds[] = $pId;
                        $pModel = Produk::find($pId);
                        $itemsTukar[] = [
                            'produk_id' => $pId,
                            'sku' => $pModel?->sku ?? $pModel?->barcode ?? '-',
                            'nama_produk' => $pModel?->nama ?? "Produk #{$pId}",
                            'qty' => (int) ($tItem['jumlah'] ?? 1),
                            'harga_satuan' => (float) ($tItem['harga_satuan'] ?? 0),
                            'subtotal' => (float) (($tItem['jumlah'] ?? 1) * ($tItem['harga_satuan'] ?? 0)),
                        ];
                    }
                }
            }

            $produkIds = array_values(array_unique($produkIds));
            $pelangganNama = $retur->pelanggan?->nama ?? ($retur->transaksi?->pelanggan?->nama ?? 'Konsumen');
            $noTrx = $retur->transaksi?->no_transaksi ?? '-';
            $tipeSelesai = $retur->tipe_penyelesaian === 'tukar_produk' ? 'Tukar Produk (Exchange)' : 'Pengembalian Dana (Refund)';
            $nominalRetur = (float) $retur->jumlah;
            $selisih = (float) ($retur->selisih_nominal ?? 0);

            $ringkasan = sprintf(
                'Retur Penjualan %s untuk transaksi %s oleh %s. Bentuk penyelesaian: %s. Barang diretur: %s.',
                $retur->no_return,
                $noTrx,
                $pelangganNama,
                $tipeSelesai,
                implode(', ', $returSummaries)
            );

            if ($selisih > 0) {
                $ringkasan .= sprintf(' Konsumen membayar selisih tukar barang sebesar Rp %s.', number_format($selisih, 0, ',', '.'));
            }

            $cabangId = $retur->cabang_id ?: ($retur->transaksi?->cabang_id ?: (session('cabang_id') ?: 1));

            $properties = [
                'tipe_transaksi' => 'retur_penjualan',
                'no_referensi' => $retur->no_return,
                'transaksi_id' => $retur->transaksi_id,
                'no_transaksi' => $noTrx,
                'cabang_id' => $cabangId,
                'produk_ids' => $produkIds,
                'ringkasan' => $ringkasan,
                'tipe_penyelesaian' => $retur->tipe_penyelesaian,
                'metode_pengembalian' => $retur->metode_pengembalian,
                'nominal_retur' => $nominalRetur,
                'selisih_nominal' => $selisih,
                'items_retur' => $itemsRetur,
                'items_tukar' => $itemsTukar,
                'pelanggan' => $pelangganNama,
            ];

            return $this->tulisLog(
                subject: $retur,
                event: 'retur_penjualan',
                description: "Retur Penjualan {$retur->no_return} ({$tipeSelesai}) - TRX: {$noTrx}",
                properties: $properties,
                causer: auth()->user(),
                cabangId: $cabangId
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat log retur penjualan: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Catat Purchase Order (PO) Pembelian Barang.
     */
    public function catatPurchaseOrder(PurchaseOrder $po, string $event = 'po_dibuat'): ?AktivitasLog
    {
        try {
            $po->loadMissing(['items.produk', 'supplier', 'gudangTujuan']);

            $items = [];
            $produkIds = [];
            $itemSummaries = [];

            foreach ($po->items as $item) {
                $pId = (int) $item->produk_id;
                $produkIds[] = $pId;
                $nama = $item->produk?->nama ?? "Produk #{$pId}";
                $qty = (int) ($item->jumlah_pesan ?? $item->jumlah ?? 0);
                $harga = (float) ($item->harga_satuan ?? $item->harga_beli ?? 0);
                $subtotal = (float) ($item->subtotal ?? ($qty * $harga));

                $items[] = [
                    'produk_id' => $pId,
                    'sku' => $item->produk?->sku ?? $item->produk?->barcode ?? '-',
                    'nama_produk' => $nama,
                    'qty' => $qty,
                    'harga_satuan' => $harga,
                    'subtotal' => $subtotal,
                ];

                $itemSummaries[] = "{$nama} (x{$qty})";
            }

            $produkIds = array_values(array_unique($produkIds));
            $supplierNama = $po->supplier?->nama ?? 'Supplier';
            $gudangNama = $po->gudangTujuan?->nama ?? 'Gudang Tujuan';
            $pembuatNama = auth()->user()?->name ?? 'Staf Pembelian';
            $totalNominal = (float) ($po->total_nominal ?? array_sum(array_column($items, 'subtotal')));

            $ringkasan = sprintf(
                'Pesanan Pembelian (PO) %s ke vendor %s dibuat oleh %s. Total %d jenis produk senilai Rp %s dialokasikan untuk %s.',
                $po->no_po,
                $supplierNama,
                $pembuatNama,
                count($items),
                number_format($totalNominal, 0, ',', '.'),
                $gudangNama
            );

            $cabangId = $po->gudangTujuan?->cabang_id ?: (session('cabang_id') ?: 1);

            $properties = [
                'tipe_transaksi' => 'po',
                'no_referensi' => $po->no_po,
                'cabang_id' => $cabangId,
                'produk_ids' => $produkIds,
                'ringkasan' => $ringkasan,
                'supplier' => $supplierNama,
                'gudang' => $gudangNama,
                'status' => $po->status,
                'total_nominal' => $totalNominal,
                'items' => $items,
            ];

            return $this->tulisLog(
                subject: $po,
                event: $event,
                description: "Purchase Order {$po->no_po} ke {$supplierNama} - Rp ".number_format($totalNominal, 0, ',', '.'),
                properties: $properties,
                causer: auth()->user(),
                cabangId: $cabangId
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat log PO: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Catat Penerimaan Barang Masuk (GRN) dari PO.
     */
    public function catatGrn(Grn $grn): ?AktivitasLog
    {
        try {
            $grn->loadMissing(['items.produk', 'po.supplier', 'gudang', 'penerima']);

            $items = [];
            $produkIds = [];
            $itemSummaries = [];

            foreach ($grn->items as $item) {
                $pId = (int) $item->produk_id;
                $produkIds[] = $pId;
                $nama = $item->produk?->nama ?? "Produk #{$pId}";
                $qty = (int) $item->jumlah_diterima;

                $items[] = [
                    'produk_id' => $pId,
                    'sku' => $item->produk?->sku ?? $item->produk?->barcode ?? '-',
                    'nama_produk' => $nama,
                    'qty' => $qty,
                    'kondisi' => $item->kondisi ?? 'bagus',
                ];

                $itemSummaries[] = "{$nama} (x{$qty})";
            }

            $produkIds = array_values(array_unique($produkIds));
            $gudangNama = $grn->gudang?->nama ?? 'Gudang';
            $penerimaNama = $grn->penerima?->name ?? (auth()->user()?->name ?? 'Staf Gudang');
            $noPo = $grn->po?->no_po ?? '-';
            $supplierNama = $grn->po?->supplier?->nama ?? 'Supplier';
            $totalQty = array_sum(array_column($items, 'qty'));

            $ringkasan = sprintf(
                'Penerimaan Barang fisik (GRN) %s dari PO %s (%s). Diterima oleh %s di %s dengan total %d unit barang siap masuk inventori.',
                $grn->no_grn,
                $noPo,
                $supplierNama,
                $penerimaNama,
                $gudangNama,
                $totalQty
            );

            $cabangId = $grn->gudang?->cabang_id ?: (session('cabang_id') ?: 1);

            $properties = [
                'tipe_transaksi' => 'grn',
                'no_referensi' => $grn->no_grn,
                'no_po' => $noPo,
                'cabang_id' => $cabangId,
                'produk_ids' => $produkIds,
                'ringkasan' => $ringkasan,
                'supplier' => $supplierNama,
                'gudang' => $gudangNama,
                'items' => $items,
            ];

            return $this->tulisLog(
                subject: $grn,
                event: 'grn_diterima',
                description: "Penerimaan Barang {$grn->no_grn} dari PO {$noPo} ({$totalQty} unit) di {$gudangNama}",
                properties: $properties,
                causer: auth()->user(),
                cabangId: $cabangId
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat log GRN: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Catat Retur Barang ke Supplier / Vendor (WMS).
     */
    public function catatReturnPembelian(ReturnPembelian $retur): ?AktivitasLog
    {
        try {
            $retur->loadMissing(['items.produk', 'supplier', 'gudang', 'user']);

            $items = [];
            $produkIds = [];
            $itemSummaries = [];

            foreach ($retur->items as $item) {
                $pId = (int) $item->produk_id;
                $produkIds[] = $pId;
                $nama = $item->produk?->nama ?? "Produk #{$pId}";
                $qty = (int) $item->jumlah;
                $hargaBeli = (float) ($item->harga_beli ?? $item->harga_satuan ?? 0);

                $items[] = [
                    'produk_id' => $pId,
                    'sku' => $item->produk?->sku ?? $item->produk?->barcode ?? '-',
                    'nama_produk' => $nama,
                    'qty' => $qty,
                    'harga_satuan' => $hargaBeli,
                    'subtotal' => (float) ($item->subtotal ?? ($qty * $hargaBeli)),
                    'alasan' => $item->alasan ?? $retur->alasan ?? '-',
                ];

                $itemSummaries[] = "{$nama} (x{$qty})";
            }

            $produkIds = array_values(array_unique($produkIds));
            $supplierNama = $retur->supplier?->nama ?? 'Supplier';
            $gudangNama = $retur->gudang?->nama ?? 'Gudang';
            $totalQty = array_sum(array_column($items, 'qty'));

            $ringkasan = sprintf(
                'Retur Barang ke Supplier %s (%s): %d unit barang dikeluarkan dari %s untuk dikembalikan. Alasan: "%s".',
                $retur->no_return,
                $supplierNama,
                $totalQty,
                $gudangNama,
                $retur->alasan ?: 'Klaim garansi supplier'
            );

            $cabangId = $retur->gudang?->cabang_id ?: (session('cabang_id') ?: 1);

            $properties = [
                'tipe_transaksi' => 'retur_pembelian',
                'no_referensi' => $retur->no_return,
                'cabang_id' => $cabangId,
                'produk_ids' => $produkIds,
                'ringkasan' => $ringkasan,
                'supplier' => $supplierNama,
                'gudang' => $gudangNama,
                'alasan' => $retur->alasan,
                'items' => $items,
            ];

            return $this->tulisLog(
                subject: $retur,
                event: 'retur_pembelian',
                description: "Retur Pembelian {$retur->no_return} ke {$supplierNama} ({$totalQty} unit)",
                properties: $properties,
                causer: auth()->user(),
                cabangId: $cabangId
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat log retur pembelian: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Catat Mutasi Transfer Stok Antar Gudang / Cabang.
     */
    public function catatTransferStok(StokTransfer $transfer, string $aksi = 'dibuat'): ?AktivitasLog
    {
        try {
            $transfer->loadMissing(['items.produk', 'gudangAsal', 'gudangTujuan', 'pengirim', 'penerima']);

            $items = [];
            $produkIds = [];
            $itemSummaries = [];

            foreach ($transfer->items as $item) {
                $pId = (int) $item->produk_id;
                $produkIds[] = $pId;
                $nama = $item->produk?->nama ?? "Produk #{$pId}";
                $qty = (int) $item->jumlah;

                $items[] = [
                    'produk_id' => $pId,
                    'sku' => $item->produk?->sku ?? $item->produk?->barcode ?? '-',
                    'nama_produk' => $nama,
                    'qty' => $qty,
                ];

                $itemSummaries[] = "{$nama} (x{$qty})";
            }

            $produkIds = array_values(array_unique($produkIds));
            $asal = $transfer->gudangAsal?->nama ?? 'Gudang Asal';
            $tujuan = $transfer->gudangTujuan?->nama ?? 'Gudang Tujuan';
            $totalQty = array_sum(array_column($items, 'qty'));

            $statusText = match ($transfer->status) {
                'dikirim' => 'sedang dalam pengiriman',
                'selesai' => 'telah berhasil diterima di tujuan',
                'dibatalkan' => 'dibatalkan',
                default => 'dalam status draf/menunggu pengiriman',
            };

            $ringkasan = sprintf(
                'Transfer Stok %s: Pemindahan %d unit barang dari %s menuju %s (%s).',
                $transfer->no_transfer,
                $totalQty,
                $asal,
                $tujuan,
                $statusText
            );

            $cabangId = $transfer->gudangAsal?->cabang_id ?: ($transfer->gudangTujuan?->cabang_id ?: (session('cabang_id') ?: 1));

            $properties = [
                'tipe_transaksi' => 'transfer_stok',
                'no_referensi' => $transfer->no_transfer,
                'cabang_id' => $cabangId,
                'produk_ids' => $produkIds,
                'ringkasan' => $ringkasan,
                'gudang_asal' => $asal,
                'gudang_tujuan' => $tujuan,
                'status' => $transfer->status,
                'items' => $items,
            ];

            return $this->tulisLog(
                subject: $transfer,
                event: 'transfer_stok',
                description: "Transfer Stok {$transfer->no_transfer} ({$asal} → {$tujuan})",
                properties: $properties,
                causer: auth()->user(),
                cabangId: $cabangId
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat log transfer stok: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Catat Penyesuaian Fisik Stok Opname.
     */
    public function catatStokOpname(StokOpname $opname): ?AktivitasLog
    {
        try {
            $opname->loadMissing(['items.produk', 'gudang', 'user']);

            $items = [];
            $produkIds = [];
            $selisihCount = 0;

            foreach ($opname->items as $item) {
                $pId = (int) $item->produk_id;
                $produkIds[] = $pId;
                $nama = $item->produk?->nama ?? "Produk #{$pId}";
                $sistem = (int) $item->stok_sistem;
                $fisik = (int) $item->stok_fisik;
                $selisih = (int) $item->selisih;

                if ($selisih !== 0) {
                    $selisihCount++;
                }

                $items[] = [
                    'produk_id' => $pId,
                    'sku' => $item->produk?->sku ?? $item->produk?->barcode ?? '-',
                    'nama_produk' => $nama,
                    'stok_sistem' => $sistem,
                    'stok_fisik' => $fisik,
                    'selisih' => $selisih,
                ];
            }

            $produkIds = array_values(array_unique($produkIds));
            $gudangNama = $opname->gudang?->nama ?? 'Gudang';
            $pemeriksa = $opname->user?->name ?? (auth()->user()?->name ?? 'Staf');

            $ringkasan = sprintf(
                'Stok Opname %s di %s oleh %s: Dari %d produk yang dihitung, ditemukan %d produk dengan selisih fisik dan telah disesuaikan di sistem.',
                $opname->no_opname,
                $gudangNama,
                $pemeriksa,
                count($items),
                $selisihCount
            );

            $cabangId = $opname->gudang?->cabang_id ?: (session('cabang_id') ?: 1);

            $properties = [
                'tipe_transaksi' => 'stok_opname',
                'no_referensi' => $opname->no_opname,
                'cabang_id' => $cabangId,
                'produk_ids' => $produkIds,
                'ringkasan' => $ringkasan,
                'gudang' => $gudangNama,
                'status' => $opname->status,
                'selisih_count' => $selisihCount,
                'items' => $items,
            ];

            return $this->tulisLog(
                subject: $opname,
                event: 'stok_opname',
                description: "Stok Opname {$opname->no_opname} di {$gudangNama} ({$selisihCount} selisih)",
                properties: $properties,
                causer: auth()->user(),
                cabangId: $cabangId
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat log opname: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Catat Aktivitas Tiket Servis (Pengerjaan, Pergantian Sparepart, Selesai).
     */
    public function catatServis(TiketServis $tiket, string $aksi = 'status_diubah', array $parts = []): ?AktivitasLog
    {
        try {
            $tiket->loadMissing(['pelanggan', 'teknisi', 'cabang', 'items.produk']);

            $items = [];
            $produkIds = [];

            // Kumpulkan sparepart yang dipakai pada servis jika ada
            if ($tiket->items) {
                foreach ($tiket->items as $item) {
                    if ($item->tipe === 'part' && $item->produk_id) {
                        $pId = (int) $item->produk_id;
                        $produkIds[] = $pId;
                        $items[] = [
                            'produk_id' => $pId,
                            'nama_produk' => $item->nama_item ?? ($item->produk?->nama ?? "Part #{$pId}"),
                            'qty' => (int) ($item->jumlah ?? 1),
                            'harga_satuan' => (float) ($item->harga_satuan ?? 0),
                            'subtotal' => (float) ($item->subtotal ?? 0),
                        ];
                    }
                }
            }

            foreach ($parts as $p) {
                if (! empty($p['produk_id'])) {
                    $pId = (int) $p['produk_id'];
                    $produkIds[] = $pId;
                    $items[] = [
                        'produk_id' => $pId,
                        'nama_produk' => $p['nama_produk'] ?? "Part #{$pId}",
                        'qty' => (int) ($p['qty'] ?? 1),
                        'harga_satuan' => (float) ($p['harga_satuan'] ?? 0),
                        'subtotal' => (float) (($p['qty'] ?? 1) * ($p['harga_satuan'] ?? 0)),
                    ];
                }
            }

            $produkIds = array_values(array_unique($produkIds));
            $pelangganNama = $tiket->pelanggan?->nama ?? ($tiket->nama_pelanggan ?? 'Konsumen');
            $teknisiNama = $tiket->teknisi?->name ?? 'Teknisi';
            $perangkat = $tiket->jenis_hp ?: 'Unit HP';

            $statusIndo = match ($tiket->status) {
                'antrian' => 'Dalam antrean servis',
                'dicek' => 'Sedang dalam pengecekan / diagnosa kerusakan',
                'menunggu_approval' => 'Menunggu persetujuan biaya dari konsumen',
                'disetujui' => 'Estimasi biaya disetujui konsumen',
                'dikerjakan' => 'Sedang dalam proses perbaikan fisik',
                'qc' => 'Pemeriksaan kualitas hasil perbaikan (QC)',
                'selesai' => 'Perbaikan selesai dan unit siap diambil',
                'sudah_diambil' => 'Unit telah diserahkan kembali kepada konsumen',
                'batal' => 'Servis dibatalkan',
                default => $tiket->status,
            };

            $ringkasan = sprintf(
                'Tiket Servis %s (%s milik %s) ditangani oleh %s. Status terkini: %s. Total estimasi biaya: Rp %s.',
                $tiket->no_tiket,
                $perangkat,
                $pelangganNama,
                $teknisiNama,
                $statusIndo,
                number_format((float) ($tiket->total_biaya ?? $tiket->estimasi_biaya ?? 0), 0, ',', '.')
            );

            if (! empty($items)) {
                $ringkasan .= sprintf(' Sparepart yang terpasang: %d part.', count($items));
            }

            $cabangId = $tiket->cabang_id ?: (session('cabang_id') ?: 1);

            $properties = [
                'tipe_transaksi' => 'servis',
                'no_referensi' => $tiket->no_tiket,
                'cabang_id' => $cabangId,
                'produk_ids' => $produkIds,
                'ringkasan' => $ringkasan,
                'perangkat' => $perangkat,
                'status' => $tiket->status,
                'pelanggan' => $pelangganNama,
                'teknisi' => $teknisiNama,
                'items' => $items,
            ];

            return $this->tulisLog(
                subject: $tiket,
                event: 'servis',
                description: "Servis {$tiket->no_tiket} ({$perangkat}): {$statusIndo}",
                properties: $properties,
                causer: auth()->user(),
                cabangId: $cabangId
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat log servis: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Tulis baris AktivitasLog ke database secara aman dan konsisten.
     */
    protected function tulisLog(
        Model $subject,
        string $event,
        string $description,
        array $properties,
        ?User $causer = null,
        ?int $cabangId = null
    ): AktivitasLog {
        $log = new AktivitasLog;
        $log->log_name = 'transaksi';
        $log->description = $description;
        $log->subject_type = $subject->getMorphClass();
        $log->subject_id = $subject->getKey();
        $log->event = $event;
        $log->causer_type = $causer ? $causer->getMorphClass() : (auth()->user() ? auth()->user()->getMorphClass() : null);
        $log->causer_id = $causer ? $causer->getKey() : auth()->id();
        $log->cabang_id = $cabangId ?: ($properties['cabang_id'] ?? (int) session('cabang_id') ?: null);
        $log->properties = collect($properties);
        $log->save();

        return $log;
    }
}
