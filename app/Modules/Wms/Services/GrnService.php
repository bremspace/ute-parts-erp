<?php

namespace App\Modules\Wms\Services;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Wms\Models\Grn;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\RiwayatPerubahanHarga;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Workflow\Services\ApprovalService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
 * - [B-10b/P1-6] Finalisasi (auto & approval) mengunci baris GRN dgn lockForUpdate()
 *   di dalam DB::transaction — approve/finalize bersamaan tidak menggandakan
 *   jurnal + stok masuk (subledger Utang ikut terlindungi).
 * - Subledger Utang (AP): finalisasi membuat 1 baris Utang per PO (kunci
 *   referensi_tipe=PurchaseOrder + referensi_id) dengan jumlah = jurnal 210-01
 *   yang diposting — dipakai Laporan Utang & dikurangi PurchaseOrderService::bayarPO.
 *   Bukan jurnal kedua — murni subledger, idempoten terhadap dobel finalize.
 * - [F2-3] Produk sn=true: $snPerProduk wajib — jumlah SN == qty_received, unik,
 *   belum terdaftar; baris nomor_seri dibuat saat GRN finalisasi (kedua jalur).
 *   Konflik SN saat finalisasi approval → ValidationException (bukan Exception mentah).
 */
class GrnService
{
    protected JurnalService $jurnalService;

    protected ApprovalService $approvalService;

    protected NomorSeriService $nomorSeriService;

    protected ?ProdukService $produkService;

    public function __construct(
        JurnalService $jurnalService,
        ApprovalService $approvalService,
        NomorSeriService $nomorSeriService,
        ?ProdukService $produkService = null
    ) {
        $this->jurnalService = $jurnalService;
        $this->approvalService = $approvalService;
        $this->nomorSeriService = $nomorSeriService;
        $this->produkService = $produkService ?? app(ProdukService::class);
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
     * Hitung saldo aktual akun kas/bank pada cabang tertentu.
     */
    public static function hitungSaldoKasCabang(int|string $akunIdOrKode, int $cabangId): float
    {
        $akun = is_numeric($akunIdOrKode)
            ? AkunCOA::find($akunIdOrKode)
            : AkunCOA::where('kode', $akunIdOrKode)->first();

        if (! $akun) {
            return 0.0;
        }

        $sum = DB::table('jurnal_akuntansi')
            ->where('akun_coa_id', $akun->id)
            ->where('cabang_id', $cabangId)
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(kredit), 0) as total_kredit')
            ->first();

        $debit = (float) ($sum->total_debit ?? 0);
        $kredit = (float) ($sum->total_kredit ?? 0);

        return round($akun->saldo_normal === 'debit' ? $debit - $kredit : $kredit - $debit, 2);
    }

    /**
     * Dapatkan daftar akun kas/bank yang aktif dan memiliki saldo mencukupi pada cabang terkait.
     *
     * @return Collection<int, AkunCOA>
     */
    public static function getKasDenganSaldoCukup(float $nominal, int $cabangId): Collection
    {
        $akunList = AkunCOA::whereIn('kelompok', ['kas', 'bank'])
            ->where('is_active', true)
            ->orderBy('kode')
            ->get();

        $cukupList = collect();

        foreach ($akunList as $akun) {
            $saldo = static::hitungSaldoKasCabang($akun->id, $cabangId);
            $akun->saldo_aktual = $saldo;

            if ($nominal <= 0 || $saldo >= $nominal) {
                $cukupList->push($akun);
            }
        }

        return $cukupList;
    }

    /**
     * Dapatkan semua akun kas/bank aktif dengan saldo aktual pada cabang terkait.
     *
     * @return Collection<int, AkunCOA>
     */
    public static function getKasDenganSaldoAktual(int $cabangId): Collection
    {
        $akunList = AkunCOA::whereIn('kelompok', ['kas', 'bank'])
            ->where('is_active', true)
            ->orderBy('kode')
            ->get();

        foreach ($akunList as $akun) {
            $akun->saldo_aktual = static::hitungSaldoKasCabang($akun->id, $cabangId);
        }

        return $akunList;
    }

    /**
     * Cek apakah user memiliki hak akses / wewenang untuk otorisasi transaksi kas (PO tunai).
     * Memerlukan role: super-admin, owner, admin-toko, finance, atau permission akunting.create/approve-workflow.
     */
    public static function userBolehOtorisasiKas(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['super-admin', 'owner', 'admin-toko', 'finance'])
            || $user->can('akunting.create')
            || $user->can('approve-workflow');
    }

    /**
     * Terima barang dari PO. Melempar Exception Indonesia bila:
     * - PO bukan status 'dikirim', atau sudah punya GRN (satu PO satu GRN, F2-2),
     * - qty diterima > qty PO (validasi PRD F2-2),
     * - [F2-3] jumlah SN ≠ qty_received utk produk sn=true (GRN tidak dibuat).
     *
     * @param  array<int|string, int>  $itemReceived  [kunci "produk|sku" (komposit) ATAU produk_id => qty]
     * @param  array<int|string, array<int, string>>  $snPerProduk  [kunci sama => [SN, ...]] — hanya utk produk sn=true
     * @param  array<int|string, float>  $itemHargaBeli  [kunci sama => harga_beli aktual saat GRN]
     * @param  array<int|string, int|null>  $itemRakId  [kunci sama => rak_id tujuan penerimaan]
     */
    public function inputGudang(
        PurchaseOrder $po,
        array $itemReceived,
        ?int $userId = null,
        array $snPerProduk = [],
        array $itemHargaBeli = [],
        array $itemRakId = [],
        ?string $akunKasBank = null,
        ?string $dibayarOleh = null,
        array $itemHargaOptions = []
    ): Grn {
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
        $userPemohon = User::find($pemohon);

        $isOtoritas = static::userBolehOtorisasiKas($userPemohon);
        $needsApprovalOtoritas = ($po->metode_bayar === 'tunai' && ! $isOtoritas);

        $namaPembayarOtomatis = $po->metode_bayar === 'tunai'
            ? ($userPemohon?->name ?? ($dibayarOleh ?: 'Kasir / Staf'))
            : null;

        return DB::transaction(function () use ($po, $itemReceived, $snPerProduk, $itemHargaBeli, $itemRakId, $pemohon, $cabangId, $akunKasBank, $namaPembayarOtomatis, $needsApprovalOtoritas, $userPemohon, $itemHargaOptions) {
            $itemData = [];
            $totalHpp = 0;
            $needsApproval = $needsApprovalOtoritas;

            foreach ($po->items as $poItem) {
                $kodeProduk = $poItem->produk_id;
                // [P1-4] Lookup kunci komposit "produk|sku" dulu; fallback produk_id (pemanggil lama).
                $kunci = static::kunciItem($kodeProduk, $poItem->sku_variant_id);
                $receivedQty = (int) ($itemReceived[$kunci] ?? $itemReceived[$kodeProduk] ?? 0);
                $poQty = (int) $poItem->jumlah;

                if ($receivedQty > $poQty) {
                    throw new \Exception("Qty diterima melebihi qty PO untuk produk ID {$kodeProduk} (PO: {$poQty}, diterima: {$receivedQty})");
                }

                $konversi = max(0.0001, (float) ($poItem->konversi ?? 1));
                $receivedQtyDasar = (int) round($receivedQty * $konversi);

                // [F2-3] Validasi SN sebelum GRN dibuat — jumlah ≠ qty / dobel / sudah
                // terdaftar → Exception Indonesia, tidak ada GRN/approval/stok side effect.
                $snList = [];
                if ($receivedQty > 0 && ($poItem->produk?->sn ?? false)) {
                    $snList = array_values($snPerProduk[$kunci] ?? $snPerProduk[$kodeProduk] ?? []);
                    $this->nomorSeriService->validasiUntukGrn((int) $kodeProduk, $snList, $receivedQtyDasar);
                }

                if ($receivedQty >= $poQty) {
                    $status = 'lengkap';
                } elseif ($receivedQty > 0) {
                    $status = 'partial';
                } else {
                    $status = 'tolak';
                }

                $hargaBeliAktual = isset($itemHargaBeli[$kunci])
                    ? (float) $itemHargaBeli[$kunci]
                    : (isset($itemHargaBeli[$kodeProduk]) ? (float) $itemHargaBeli[$kodeProduk] : (float) $poItem->harga_beli);

                $rakId = isset($itemRakId[$kunci])
                    ? ($itemRakId[$kunci] ? (int) $itemRakId[$kunci] : null)
                    : (isset($itemRakId[$kodeProduk]) ? ($itemRakId[$kodeProduk] ? (int) $itemRakId[$kodeProduk] : null) : null);

                if (! $rakId && $po->gudang_tujuan_id) {
                    $rakId = $this->resolveDefaultRakId((int) $kodeProduk, (int) $po->gudang_tujuan_id, $poItem->sku_variant_id);
                }

                $opts = $itemHargaOptions[$kunci] ?? $itemHargaOptions[$kodeProduk] ?? [];

                $itemData[] = [
                    'produk_id' => $kodeProduk,
                    'produk_nama' => $poItem->produk?->nama ?? ('Produk #'.$kodeProduk),
                    'sku_variant_id' => $poItem->sku_variant_id,
                    'satuan' => $poItem->satuan ?: ($poItem->produk?->satuan ?: 'pcs'),
                    'konversi' => $konversi,
                    'qty_po' => $poQty,
                    'qty_received' => $receivedQty,
                    'qty_received_dasar' => $receivedQtyDasar,
                    'harga_beli' => $hargaBeliAktual,
                    'harga_po' => (float) $poItem->harga_beli,
                    'status' => $status,
                    'sn' => $snList, // [F2-3] disimpan utk finalisasi (auto & approval)
                    'rak_id' => $rakId,
                    'opsi_harga_beli' => $opts['opsi_harga_beli'] ?? null,
                    'skema_harga_jual' => $opts['skema_harga_jual'] ?? null,
                    'nilai_jual_atau_margin' => $opts['nilai_jual_atau_margin'] ?? null,
                ];

                $totalHpp += $receivedQty * $hargaBeliAktual;

                if ($status !== 'lengkap') {
                    $needsApproval = true;
                }
            }

            $totalHpp = round($totalHpp, 2);

            $grn = Grn::create([
                'no_grn' => $this->generateNoGrn($cabangId),
                'po_id' => $po->id,
                'cabang_id' => $cabangId,
                'gudang_id' => $po->gudang_tujuan_id,
                'user_id' => $pemohon,
                'status' => 'draft',
                'total_hpp' => $totalHpp,
                'akun_kas_bank' => $akunKasBank ?: $po->akun_kas_bank,
                'dibayar_oleh' => $namaPembayarOtomatis,
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
                        'no_po' => $po->no_po,
                        'supplier_nama' => $po->supplier?->nama,
                        'cabang_id' => $cabangId,
                        'is_tunai' => $po->metode_bayar === 'tunai',
                        'akun_kas_bank' => $akunKasBank ?: $po->akun_kas_bank,
                        'petugas_penerima_id' => $pemohon,
                        'petugas_nama' => $userPemohon?->name ?? 'Petugas Penerima',
                        'item_data' => json_encode($itemData),
                    ],
                    $pemohon
                );
            }

            // Qty sesuai & otoritas kas memadai ATAU rule approval tidak ada/disabled → finalisasi sekarang
            if (! $needsApproval || $approval === null) {
                $this->selesaikanGrn($grn, $pemohon, $needsApproval ? 'tanpa rule approval' : 'qty sesuai');
            }

            return $grn;
        });
    }

    /**
     * [B-10b/P1-6] Approve/finalize memakai lockForUpdate() pada baris GRN.
     * `findOrFail` di luar transaksi dulu membaca status basi — dua approver
     * bersamaan (tab + inbox) bisa dua-duanya melihat 'draft' lalu
     * menggandakan jurnal + stok masuk. Lock + re-check status di dalam
     * transaksi membuat yang kedua melihat 'terima' → no-op.
     */
    public function setujuiGrn(int $grnId, ?int $actionedBy, array $options = []): Grn
    {
        return DB::transaction(function () use ($grnId, $actionedBy, $options) {
            $grn = Grn::whereKey($grnId)->lockForUpdate()->firstOrFail();

            if (! empty($options['akun_kas_bank'])) {
                $grn->akun_kas_bank = $options['akun_kas_bank'];
                $grn->save();
            }

            $this->selesaikanGrn(
                $grn,
                $actionedBy ?? ApprovalService::pemohon(),
                'disetujui via approval',
                $options
            );

            return $grn->fresh();
        });
    }

    public function tolakGrn(int $grnId, ?int $actionedBy, string $catatan): Grn
    {
        return DB::transaction(function () use ($grnId, $catatan) {
            $grn = Grn::whereKey($grnId)->lockForUpdate()->firstOrFail();

            if ($grn->status !== 'draft') {
                return $grn; // idempoten — sudah diproses
            }

            $grn->update([
                'status' => 'ditolak',
                'catatan' => trim(($grn->catatan ? $grn->catatan.' | ' : '').$catatan),
            ]);

            return $grn;
        });
    }

    /**
     * Finalisasi GRN: jurnal AP (130-01/210-01) + stok masuk + status 'terima'.
     * Hanya bertransisi dari 'draft' — aman terhadap approve ganda (tab + inbox).
     *
     * [B-10b/P1-6] Selalu dipanggil dari dalam DB::transaction; baris GRN
     * di-lock di sini (bukan cuma di setujuiGrn) supaya jalur auto-finalize
     * inputGudang pun ikut terlindungi.
     */
    protected function selesaikanGrn(Grn $grn, ?int $actionedBy, string $keterangan, array $options = []): void
    {
        // Instance yang dipanggil caller (dikembalikan inputGudang) — status di
        // sana bisa basi, jadi semua operasi memakai salinan hasil lockForUpdate.
        $instancePemanggil = $grn;

        // [B-10b/P1-6] Baris GRN di-lock di sini (bukan cuma di setujuiGrn) supaya
        // jalur auto-finalize inputGudang pun terlindungi dari finalize bersamaan.
        $grn = Grn::whereKey($grn->id)->lockForUpdate()->firstOrFail();

        if ($grn->status !== 'draft') {
            $instancePemanggil->setRawAttributes($grn->getAttributes(), true);

            return;
        }

        $totalHpp = (float) $grn->total_hpp;
        $po = $grn->purchaseOrder()->first();
        $noPo = $po?->no_po ?? '-';

        // [F2-3] SN produk sn=true: validasi ulang + simpan baris nomor_seri 'tersedia'
        // SEBELUM jurnal — konflik (SN sudah terdaftar) → throw → rollback seluruh GRN.
        // Berlaku utk kedua jalur finalisasi: qty-sesuai auto-approve & setujuiGrn/approval.
        foreach ($grn->item_qty_received ?? [] as $item) {
            $qty = (int) ($item['qty_received'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $konversi = max(0.0001, (float) ($item['konversi'] ?? 1));
            $qtyDasar = (int) round($qty * $konversi);

            $snList = array_values($item['sn'] ?? []);
            $produk = Produk::find($item['produk_id'] ?? null);
            if ($produk?->sn) {
                // [P1-11] Konflik SN saat approval jangan melempar Exception mentah (500 +
                // approval stuck) — ValidationException → pesan Indonesia, transaksi rollback,
                // request tetap pending dgn pesan bersih utk approver.
                try {
                    $this->nomorSeriService->validasiUntukGrn((int) $produk->id, $snList, $qtyDasar);
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

        $isKredit = ($po?->metode_bayar ?? 'kredit') === 'kredit';
        $akunKas = $options['akun_kas_bank'] ?? $grn->akun_kas_bank ?: ($po?->akun_kas_bank ?: '110-01');
        $dibayarOleh = $grn->dibayar_oleh ?: (User::find($actionedBy)?->name ?? 'Kasir / Staf');
        $akunKredit = $isKredit ? '210-01' : $akunKas;

        // Validasi saldo kas untuk PO Tunai
        if (! $isKredit && $totalHpp > 0) {
            $saldoKas = static::hitungSaldoKasCabang($akunKas, (int) $grn->cabang_id);
            if ($saldoKas < $totalHpp) {
                $hasMutasi = DB::table('jurnal_akuntansi')
                    ->where('cabang_id', $grn->cabang_id)
                    ->whereExists(function ($q) use ($akunKas) {
                        $q->select(DB::raw(1))
                            ->from('akun_coa')
                            ->whereColumn('akun_coa.id', 'jurnal_akuntansi.akun_coa_id')
                            ->where('akun_coa.kode', $akunKas);
                    })
                    ->exists();

                if ($hasMutasi) {
                    throw new \Exception("Saldo kas {$akunKas} tidak mencukupi untuk pembayaran PO tunai (Saldo: Rp ".number_format($saldoKas, 0, ',', '.').', Dibutuhkan: Rp '.number_format($totalHpp, 0, ',', '.').')');
                }
            }
        }

        // [KAS-LACI] Jika pembayaran PO tunai diarahkan ke Kas Laci (110-04),
        // otomatis catat pengeluaran kas laci kasir agar saldo fisik & sistem klop dan mudah diaudit.
        if (! $isKredit && $akunKas === '110-04' && Schema::hasTable('kas_mutasi_laci')) {
            $this->catatPengeluaranKasLaci($grn, $po, $totalHpp, $actionedBy);
        }

        if ($totalHpp > 0) {
            $keteranganJurnal = $isKredit
                ? "GRN {$grn->no_grn} — {$keterangan} (PO {$noPo})"
                : "GRN {$grn->no_grn} — Pembayaran Tunai oleh {$dibayarOleh} [{$akunKas}] (PO {$noPo})";

            $this->jurnalService->post(
                $grn->no_grn,
                now(),
                'grn',
                [
                    ['akun_kode' => '130-01', 'debit' => $totalHpp, 'kredit' => 0],
                    ['akun_kode' => $akunKredit, 'debit' => 0, 'kredit' => $totalHpp],
                ],
                $keteranganJurnal,
                (int) $grn->cabang_id,
                $actionedBy,
                Grn::class,
                $grn->id
            );

            // Subledger Utang (AP) dicatat HANYA bila metode bayar PO adalah kredit
            if ($isKredit) {
                $this->catatUtang($grn, $po, $totalHpp);
            }
        }

        foreach ($grn->item_qty_received ?? [] as $item) {
            $qty = (int) ($item['qty_received'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $konversi = max(0.0001, (float) ($item['konversi'] ?? 1));
            $qtyDasar = (int) round($qty * $konversi);

            $this->tambahStok($grn, $item, $qtyDasar, $actionedBy, $noPo);

            $produkId = (int) $item['produk_id'];
            $hargaBeliMasuk = (float) ($item['harga_beli'] ?? 0);
            $hargaBeliPerBase = round($hargaBeliMasuk / $konversi, 2);

            // Deteksi & catat perubahan harga dari PO / harga master
            $produk = Produk::find($produkId);
            if ($produk && $hargaBeliMasuk > 0) {
                $hargaBeliLama = (float) $produk->harga_beli;
                $hargaPo = (float) ($item['harga_po'] ?? $hargaBeliLama);
                $hargaPoPerBase = round($hargaPo / $konversi, 2);

                // Perubahan harga: jika harga masuk berbeda dari harga PO atau harga master produk
                if (abs($hargaBeliPerBase - $hargaPoPerBase) > 0.01 || abs($hargaBeliPerBase - $hargaBeliLama) > 0.01) {
                    $baselineHarga = abs($hargaBeliPerBase - $hargaPoPerBase) > 0.01 ? $hargaPoPerBase : $hargaBeliLama;
                    $selisih = $hargaBeliPerBase - $baselineHarga;
                    $persen = $baselineHarga > 0 ? round(($selisih / $baselineHarga) * 100, 2) : 0;

                    RiwayatPerubahanHarga::create([
                        'produk_id' => $produkId,
                        'cabang_id' => $grn->cabang_id,
                        'sumber' => 'grn',
                        'referensi_type' => Grn::class,
                        'referensi_id' => $grn->id,
                        'harga_lama' => $baselineHarga,
                        'harga_baru' => $hargaBeliPerBase,
                        'selisih' => $selisih,
                        'persentase_perubahan' => $persen,
                        'catatan' => "Harga masuk GRN {$grn->no_grn}: Rp ".number_format($hargaBeliPerBase, 0, ',', '.').'/base unit (sebelumnya Rp '.number_format($baselineHarga, 0, ',', '.').')',
                        'user_id' => $actionedBy,
                    ]);
                }
            }

            if ($this->produkService) {
                $opsiMetode = $item['opsi_harga_beli'] ?? null;
                $skemaJual = $item['skema_harga_jual'] ?? null;
                $nilaiJualAtauMargin = isset($item['nilai_jual_atau_margin']) ? (float) $item['nilai_jual_atau_margin'] : null;

                $this->produkService->sesuaikanHargaBeliProduk(
                    $produkId,
                    $qtyDasar,
                    $hargaBeliPerBase,
                    $opsiMetode,
                    $skemaJual,
                    $nilaiJualAtauMargin
                );
            }
        }

        $grn->update(['status' => 'terima']);

        // [F2-2] PO → 'diterima' saat finalisasi — cegah penerimaan ganda
        // via jalur legacy (PoTab::terimaPo / API updatePoStatus).
        // Sinkronkan total PO dengan nilai HPP barang yang diterima agar sisa utang akurat.
        if ($po) {
            $poUpdates = [
                'status' => 'diterima',
                'total' => $totalHpp,
            ];
            if (! $isKredit) {
                $poUpdates['total_dibayar'] = $totalHpp;
                $poUpdates['akun_kas_bank'] = $akunKas;
            }
            $po->update($poUpdates);
        }

        // [B-10b/P1-6] Sinkronkan instance milik caller (inputGudang mengembalikannya)
        // supaya status di memori = status di DB.
        $instancePemanggil->setRawAttributes($grn->getAttributes(), true);
    }

    /**
     * Catat subledger Utang (AP) untuk PO yang baru diterima via GRN.
     *
     * - Mencerminkan jurnal 210-01 yang baru diposting di selesaikanGrn —
     *   TANPA posting jurnal kedua (murni baris subledger).
     * - Idempoten: kunci (referensi_tipe = PurchaseOrder::class, referensi_id = po_id)
     *   — satu baris Utang per PO; dobel finalize (auto qty-sesuai + approve path)
     *   atau row legacy dari PurchaseOrderService::terimaBarang → no-op.
     * - Kunci ini juga yang dibaca PurchaseOrderService::bayarPO utk mengurangi
     *   jumlah_dibayar / status — sehingga pembayaran PO menekan Utang ini.
    /**
     * Catat pengeluaran kas laci kasir otomatis di kas_mutasi_laci saat GRN PO Tunai
     * diarahkan ke akun Kas Laci (110-04).
     */
    protected function catatPengeluaranKasLaci(Grn $grn, ?PurchaseOrder $po, float $totalHpp, ?int $actionedBy): void
    {
        if ($totalHpp <= 0 || ! Schema::hasTable('kas_mutasi_laci')) {
            return;
        }

        // Cari sesi kas terbuka di cabang terkait
        // Prioritas 1: Sesi kas terbuka milik pembuat GRN (kasir penerima barang)
        $sesi = DB::table('kas_sesi')
            ->where('cabang_id', $grn->cabang_id)
            ->where('status', 'buka')
            ->where('user_id', $grn->user_id)
            ->latest('id')
            ->first();

        // Prioritas 2: Sesi kas terbuka apapun di cabang tersebut
        if (! $sesi) {
            $sesi = DB::table('kas_sesi')
                ->where('cabang_id', $grn->cabang_id)
                ->where('status', 'buka')
                ->latest('id')
                ->first();
        }

        if (! $sesi) {
            return; // Tidak ada sesi kas laci terbuka
        }

        $noPo = $po?->no_po ?? '-';
        $kasirId = $sesi->user_id ?? $grn->user_id;

        // Idempoten: Cek apakah sudah pernah dicatat di kas_mutasi_laci untuk GRN ini
        $exists = DB::table('kas_mutasi_laci')
            ->where('kas_sesi_id', $sesi->id)
            ->where('no_jurnal', $grn->no_grn)
            ->where('jenis', 'keluar')
            ->exists();

        if (! $exists) {
            DB::table('kas_mutasi_laci')->insert([
                'kas_sesi_id' => $sesi->id,
                'cabang_id' => $grn->cabang_id,
                'user_id' => $kasirId,
                'jenis' => 'keluar',
                'nominal' => $totalHpp,
                'akun_lawan_kode' => '130-01',
                'keterangan' => "Pengeluaran Kas Laci untuk Pembayaran GRN {$grn->no_grn} (PO {$noPo})",
                'no_jurnal' => $grn->no_grn,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Kirim notifikasi audit ke kasir terkait pemotongan saldo laci
            try {
                app(NotificationService::class)->kirim(
                    'inapp',
                    User::find($kasirId)?->email ?? 'kasir@cabang.local',
                    "Pengeluaran Kas Laci: GRN {$grn->no_grn}",
                    'Uang tunai Rp '.number_format($totalHpp, 0, ',', '.')." telah otomatis dikeluarkan dari laci kasir untuk pembayaran GRN {$grn->no_grn} (PO {$noPo}). Tercatat pada sesi kas #{$sesi->id}.",
                    [
                        'grn_id' => $grn->id,
                        'no_grn' => $grn->no_grn,
                        'kas_sesi_id' => $sesi->id,
                        'nominal' => $totalHpp,
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal kirim notifikasi pengeluaran kas laci: '.$e->getMessage());
            }
        }
    }

    /**
     * Catat Utang (AP) dari GRN kredit.
     *
     * Kontrak:
     * - Jurnal Utang Usaha (Kredit 210-01) SUDAH dicatat di JurnalService; method ini
     *   hanya membuat baris model Utang agar modul Akunting/UtangTab bisa melacak
     *   jatuh tempo + pelunasan bertahap (PurchaseOrderService::bayarPO)
     *   TANPA posting jurnal kedua (murni baris subledger).
     * - Idempoten: kunci (referensi_tipe = PurchaseOrder::class, referensi_id = po_id)
     *   — satu baris Utang per PO; dobel finalize (auto qty-sesuai + approve path)
     *   atau row legacy dari PurchaseOrderService::terimaBarang → no-op.
     * - Kunci ini juga yang dibaca PurchaseOrderService::bayarPO utk mengurangi
     *   jumlah_dibayar / status — sehingga pembayaran PO menekan Utang ini.
     * - cabang_id di-stamp dari GRN utk scoping Laporan/export Utang.
     * - Tanpa Utang bila PO tidak ada (defensif) — jurnal tetap sudah diposting.
     */
    protected function catatUtang(Grn $grn, ?PurchaseOrder $po, float $jumlah): void
    {
        if (! $po) {
            return;
        }

        $ada = Utang::where('referensi_tipe', PurchaseOrder::class)
            ->where('referensi_id', $po->id)
            ->exists();
        if ($ada) {
            return; // idempoten — sudah tercatat (finalize ganda / jalur legacy)
        }

        // Nomor mengikuti pola legacy PurchaseOrderService::terimaBarang (UTG-Ymd-####)
        $count = Utang::whereDate('created_at', now()->toDateString())->count() + 1;

        Utang::create([
            'no_utang' => sprintf('UTG-%s-%04d', now()->format('Ymd'), $count),
            'referensi_tipe' => PurchaseOrder::class,
            'referensi_id' => $po->id,
            'cabang_id' => $grn->cabang_id,
            'kreditor_nama' => $po->supplier?->nama,
            'jumlah' => round($jumlah, 2),
            'jumlah_dibayar' => 0,
            'jatuh_tempo' => $po->jatuh_tempo,
            'status' => 'belum_lunas',
            'keterangan' => "Utang pembelian PO {$po->no_po} — GRN {$grn->no_grn}",
        ]);
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
            $updateStok = ['jumlah' => $setelah];
            if (! empty($item['rak_id'])) {
                $updateStok['rak_id'] = (int) $item['rak_id'];
            }
            $stok->update($updateStok);
        } catch (QueryException $e) {
            if ($this->kunciDuplikat($e)) {
                throw new \Exception('Data stok gudang utk barang ini sudah ada (duplikat) — muat ulang halaman lalu coba lagi');
            }

            throw $e;
        }

        $harga = (float) ($item['harga_beli'] ?? 0);
        $catatanRak = ! empty($item['rak_id']) ? " (Rak: #{$item['rak_id']})" : '';

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
            'catatan' => "GRN {$grn->no_grn} — {$qty} x Rp ".number_format($harga, 0, ',', '.')." (PO {$noPo}){$catatanRak}",
        ]);

        // [B-10i] user_id = petugas yang mem-finalize GRN ($actionedBy, sama dgn
        // StokLog.user_id & jurnal 130-01/210-01 di atas) — mutasi stok harus
        // bisa dibuktikan pelakunya.
        StockMutationLog::create([
            'produk_id' => $item['produk_id'],
            'sku_variant_id' => $item['sku_variant_id'] ?? null,
            'gudang_id' => $grn->gudang_id,
            'user_id' => $actionedBy,
            'delta' => $qty,
            'sumber' => 'grn',
            'referensi_tipe' => Grn::class,
            'referensi_id' => $grn->id,
            'terjadi_at' => now(),
        ]);
    }

    /**
     * Generate nomor dokumen GRN unik per cabang & tanggal.
     * Memastikan counter memeriksa tabel grn secara langsung agar tidak bentrok (duplicate key).
     */
    public function generateNoGrn(?int $cabangId = null): string
    {
        $cabangId = $cabangId ?? 1;
        $date = now()->format('Ymd');
        $like = sprintf('JRL-GRN-%s-%s-%%', $cabangId, $date);

        // Ambil counter terbesar yang sudah terpakai di tabel GRN
        $terpakaiGrn = Grn::where('cabang_id', $cabangId)
            ->where('no_grn', 'like', $like)
            ->lockForUpdate()
            ->pluck('no_grn')
            ->map(function ($no) {
                $parts = explode('-', (string) $no);

                return (int) end($parts);
            })
            ->all();

        // Ambil counter terbesar yang sudah terpakai di jurnal headers jika ada
        $terpakaiJurnal = [];
        if (class_exists(JurnalHeader::class) && Schema::hasTable('jurnal_headers')) {
            $terpakaiJurnal = JurnalHeader::where('cabang_key', $cabangId)
                ->where('no_jurnal', 'like', $like)
                ->pluck('no_jurnal')
                ->map(function ($no) {
                    $parts = explode('-', (string) $no);

                    return (int) end($parts);
                })
                ->all();
        }

        $semuaTerpakai = array_merge($terpakaiGrn, $terpakaiJurnal);
        $next = ($semuaTerpakai ? max($semuaTerpakai) : 0) + 1;

        // Loop safety guard untuk memastikan nomor benar-benar belum pernah dipakai di tabel grn
        do {
            $candidate = sprintf('JRL-GRN-%s-%s-%04d', $cabangId, $date, $next);
            $exists = Grn::where('no_grn', $candidate)->exists();
            if (! $exists) {
                break;
            }
            $next++;
        } while ($exists);

        return $candidate;
    }

    /**
     * Resolusi lokasi rak default untuk produk di gudang tujuan penerimaan.
     * Prioritas:
     * 1. Rak yang sudah digunakan produk/varian di gudang tujuan.
     * 2. Rak yang digunakan produk di gudang lain yang memiliki kode/nama sama di gudang tujuan.
     * 3. Jika gudang tujuan hanya memiliki satu rak aktif, jadikan default otomatis.
     */
    public function resolveDefaultRakId(int $produkId, int $gudangId, ?int $skuVariantId = null): ?int
    {
        // 1. Cari dari stok item di gudang tujuan
        $rakId = StokItem::where('produk_id', $produkId)
            ->where('gudang_id', $gudangId)
            ->when($skuVariantId, fn ($q) => $q->orderByRaw('CASE WHEN sku_variant_id = ? THEN 0 ELSE 1 END', [$skuVariantId]))
            ->whereNotNull('rak_id')
            ->value('rak_id');

        if ($rakId) {
            $isAktif = Rak::where('id', $rakId)->where('gudang_id', $gudangId)->where('is_active', true)->exists();
            if ($isAktif) {
                return (int) $rakId;
            }
        }

        // 2. Cari dari gudang lain, cocokkan nama/kode di gudang tujuan
        $rakIdLain = StokItem::where('produk_id', $produkId)
            ->when($skuVariantId, fn ($q) => $q->orderByRaw('CASE WHEN sku_variant_id = ? THEN 0 ELSE 1 END', [$skuVariantId]))
            ->whereNotNull('rak_id')
            ->value('rak_id');

        if ($rakIdLain) {
            $rakLain = Rak::find($rakIdLain);
            if ($rakLain) {
                if ((int) $rakLain->gudang_id === $gudangId && $rakLain->is_active) {
                    return (int) $rakLain->id;
                }
                $rakSama = Rak::where('gudang_id', $gudangId)
                    ->where(function ($q) use ($rakLain) {
                        $q->where('kode', $rakLain->kode)
                            ->orWhere('nama', $rakLain->nama);
                    })
                    ->where('is_active', true)
                    ->value('id');
                if ($rakSama) {
                    return (int) $rakSama;
                }
            }
        }

        // 3. Jika gudang tujuan hanya memiliki 1 rak aktif
        $raks = Rak::where('gudang_id', $gudangId)->where('is_active', true)->limit(2)->pluck('id');
        if ($raks->count() === 1) {
            return (int) $raks->first();
        }

        return null;
    }

    /** Deteksi pelanggaran kunci unik/duplikat dari QueryException (MySQL 23000 / SQLite UNIQUE). */
    private function kunciDuplikat(QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? null) === '23000'
            || str_contains(strtolower($e->getMessage()), 'unique')
            || str_contains(strtolower($e->getMessage()), 'duplicate');
    }
}
