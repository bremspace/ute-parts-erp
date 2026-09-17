<?php

namespace App\Modules\Reseller\Services;

use App\Modules\Akunting\Models\Utang;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Reseller\Models\SkemaKomisi;
use Illuminate\Support\Facades\DB;

/**
 * Komisi reseller (PRD §4.5):
 * - Transaksi reseller → hitung komisi → status pending.
 * - Approval finance → jurnal (debit Beban Komisi, kredit Utang Komisi) + entri Utang.
 */
class KomisiService
{
    public function __construct(
        protected JurnalService $jurnalService
    ) {}

    /**
     * Hitung komisi dari item agregat (untuk servis & jalur non-Transaksi).
     *
     * @param array $itemsAgregat [['kategori' => string, 'subtotal' => float, 'jumlah' => int], ...]
     */
    public function hitungKomisiDariItems(Pelanggan $pelanggan, array $itemsAgregat, array $referensi): ?Komisi
    {
        if (!$pelanggan->is_reseller || empty($itemsAgregat)) {
            return null;
        }

        $totalKomisi = 0.0;
        $skemaTerpakai = null;

        foreach ($itemsAgregat as $agregat) {
            $kategori = $agregat['kategori'] ?? 'umum';

            // [T-21] Cek override skema PER RESELLER terlebih dahulu; fallback ke skema default
            $skema = \App\Modules\Reseller\Models\SkemaKomisiReseller::where('pelanggan_id', $pelanggan->id)
                ->where('is_active', true)
                ->where(function ($q) use ($kategori) {
                    $q->whereNull('kategori')->orWhere('kategori', $kategori);
                })
                ->first();

            if (!$skema) {
                $skema = SkemaKomisi::where('is_active', true)
                    ->where(function ($q) use ($kategori) {
                        $q->whereNull('kategori')->orWhere('kategori', $kategori);
                    })
                    ->first();
            }

            if ($skema) {
                $totalKomisi += $skema->tipe === 'persen'
                    ? round((float) $agregat['subtotal'] * ((float) $skema->nilai / 100), 2)
                    : (float) $skema->nilai * (int) $agregat['jumlah'];

                $skemaTerpakai = $skema;
            }
        }

        if ($totalKomisi <= 0) {
            return null;
        }

        $today = now()->format('Ymd');
        $count = Komisi::whereDate('created_at', now()->toDateString())->count() + 1;
        $noKomisi = sprintf('KMS-%s-%04d', $today, $count);

        return Komisi::create([
            'no_komisi'         => $noKomisi,
            'pelanggan_id'      => $pelanggan->id,
            'transaksi_id'      => $referensi['transaksi_id'] ?? null,
            'skema_komisi_id'   => $skemaTerpakai?->id,
            'jumlah_transaksi'  => $referensi['jumlah_transaksi'] ?? array_sum(array_column($itemsAgregat, 'subtotal')),
            'nominal_komisi'    => $totalKomisi,
            'status'            => 'pending',
            'keterangan'        => $referensi['keterangan'] ?? 'Komisi dari transaksi',
        ]);
    }

    /**
     * Hitung komisi untuk transaksi reseller. Panggil saat transaksi POS/marketplace dengan pelanggan is_reseller.
     */
    public function hitungKomisi(Transaksi $transaksi, Pelanggan $pelanggan): ?Komisi
    {
        // Produk dalam transaksi (via items.produk.kategori)
        $grouped = $transaksi->items()->with('produk')->get()
            ->groupBy(fn ($item) => $item->produk?->kategori ?? 'umum');

        $itemsAgregat = [];
        foreach ($grouped as $kategori => $items) {
            $itemsAgregat[] = [
                'kategori' => $kategori,
                'subtotal' => $items->sum(fn ($i) => (float) $i->subtotal),
                'jumlah'   => $items->sum('jumlah'),
            ];
        }

        return $this->hitungKomisiDariItems($pelanggan, $itemsAgregat, [
            'jumlah_transaksi' => $transaksi->total_akhir,
            'keterangan'       => 'Komisi dari transaksi ' . $transaksi->no_transaksi,
            'transaksi_id'     => $transaksi->id,
        ]);
    }

    /**
     * Update status komisi (bulk) + buat jurnal & utang saat disetujui.
     * Butler: yang boleh approve = role finance (dicek di controller).
     *
     * @param array $komisiIds
     * @param string $action approve|reject
     */
    public function prosesApproval(array $komisiIds, string $action, int $userId): array
    {
        $approvedUtangIds = [];

        DB::transaction(function () use ($komisiIds, $action, $userId, &$approvedUtangIds) {
            foreach ($komisiIds as $id) {
                $komisi = Komisi::where('id', $id)->where('status', 'pending')->lockForUpdate()->first();
                if (!$komisi) {
                    continue;
                }

                if ($action === 'reject') {
                    $komisi->update([
                        'status'          => 'ditolak',
                        'approved_by_id'  => $userId,
                        'approved_at'     => now(),
                    ]);

                    app(\App\Modules\Rbac\Services\AuditService::class)->catat(
                        'Komisi', 'reject', $komisi->id,
                        "Komisi {$komisi->no_komisi} ditolak (Rp {$komisi->nominal_komisi})",
                        ['status' => 'pending'], ['status' => 'ditolak']
                    );
                    continue;
                }

                // Approve
                $noJurnal = $this->jurnalService->generateNoJurnal('komisi', null);
                $this->jurnalService->post(
                    $noJurnal,
                    now(),
                    'komisi',
                    [
                        ['akun_kode' => '510-01', 'debit' => (float) $komisi->nominal_komisi, 'kredit' => 0],   // Beban Komisi
                        ['akun_kode' => '210-03', 'debit' => 0, 'kredit' => (float) $komisi->nominal_komisi],   // Utang Komisi
                    ],
                    "Komisi disetujui — {$komisi->no_komisi}",
                    $komisi->transaksi?->cabang_id,
                    $userId,
                    Komisi::class,
                    $komisi->id
                );

                $noUtang = sprintf('UTG-KMS-%s', str_replace('KMS-', '', $komisi->no_komisi));
                Utang::updateOrCreate(
                    [
                        'referensi_tipe' => 'komisi',
                        'referensi_id'   => $komisi->id,
                    ],
                    [
                        'no_utang'       => $noUtang,
                        'pelanggan_id'   => $komisi->pelanggan_id,
                        'kreditor_nama'  => $komisi->pelanggan?->nama,
                        'jumlah'         => (float) $komisi->nominal_komisi,
                        'jumlah_dibayar' => 0,
                        'status'         => 'belum_lunas',
                        'keterangan'     => 'Utang komisi ' . $komisi->no_komisi,
                    ]
                );
                $approvedUtangIds[] = $komisi->id;

                $komisi->update([
                    'status'         => 'disetujui',
                    'approved_by_id' => $userId,
                    'approved_at'    => now(),
                ]);

                app(\App\Modules\Rbac\Services\AuditService::class)->catat(
                    'Komisi', 'approve', $komisi->id,
                    "Komisi {$komisi->no_komisi} disetujui — jurnal {$noJurnal} + utang {$noUtang}",
                    ['status' => 'pending'], ['status' => 'disetujui']
                );
            }
        });

        return $approvedUtangIds;
    }
}