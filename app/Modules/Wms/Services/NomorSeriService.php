<?php

namespace App\Modules\Wms\Services;

use App\Modules\Wms\Models\Grn;
use App\Modules\Wms\Models\NomorSeri;
use App\Modules\Wms\Models\NomorSeriEvent;
use Illuminate\Support\Collection;

/**
 * [F2-3] Layanan Nomor Seri — parse, validasi, klaim (jual/servis), lepas, autocomplete.
 *
 * Aturan:
 * - Produk sn=true: jumlah SN wajib sama dengan qty (diterima/terjual/dipakai).
 * - Klaim selalu lockForUpdate + validasi status 'tersedia' + cabang aktif.
 * - Pesan error berbahasa Indonesia; semua pemanggilan dalam transaksi DB pemanggil.
 */
class NomorSeriService
{
    /**
     * Parse input bebas (newline / koma / titik-koma / spasi) → daftar SN unik terurut.
     *
     * @return array<int, string>
     */
    public function parseList(string $raw): array
    {
        $parts = preg_split('/[\r\n,;]+/', $raw) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '' && ! in_array($p, $out, true)) {
                $out[] = $p;
            }
        }

        return $out;
    }

    /**
     * Validasi daftar SN utk GRN (sebelum GRN dibuat & saat finalisasi).
     * Lempar \Exception bila jumlah ≠ qty, ada dobel, atau SN sudah terdaftar.
     *
     * @param  array<int, string>  $snList
     */
    public function validasiUntukGrn(int $produkId, array $snList, int $qty): void
    {
        $jumlahSku = count($snList);
        if ($jumlahSku !== $qty) {
            throw new \Exception(
                "Jumlah nomor seri ({$jumlahSku}) harus sama dengan qty diterima ({$qty})"
            );
        }

        $this->tolakJikaAdaDobel($snList);

        $sudahAda = NomorSeri::where('produk_id', $produkId)
            ->whereIn('nomor_seri', $snList)
            ->pluck('nomor_seri');

        if ($sudahAda->isNotEmpty()) {
            throw new \Exception('Nomor seri sudah terdaftar di sistem: '.$sudahAda->implode(', '));
        }
    }

    /**
     * Simpan baris nomor_seri status 'tersedia' — dipanggil saat GRN finalisasi
     * (auto-approve qty-sesuai & approval setujuiGrn — keduanya lewat selesaikanGrn).
     *
     * @param  array<int, string>  $snList
     */
    public function simpanDariGrn(Grn $grn, int $produkId, ?int $skuVariantId, array $snList): void
    {
        foreach ($snList as $sn) {
            NomorSeri::create([
                'cabang_id' => $grn->cabang_id,
                'produk_id' => $produkId,
                'sku_variant_id' => $skuVariantId,
                'nomor_seri' => $sn,
                'status' => NomorSeri::STATUS_TERSEDIA,
                'keterangan' => "GRN {$grn->no_grn}",
            ]);
        }
    }

    /**
     * Klaim SN utk penjualan POS → status 'terjual' + tautan transaksi_item.
     * Validasi: jumlah == qty, unik, ada, status 'tersedia', cabang aktif.
     *
     * @param  array<int, string>  $snList
     */
    public function klaimJual(array $snList, int $produkId, int $cabangId, int $qty, int $transaksiItemId): void
    {
        $this->validasiJumlahDanUnik($snList, $qty);

        foreach ($snList as $sn) {
            $row = NomorSeri::where('produk_id', $produkId)
                ->where('nomor_seri', $sn)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                throw new \Exception("Nomor seri {$sn} tidak ditemukan untuk produk ini");
            }
            if ($row->status !== NomorSeri::STATUS_TERSEDIA) {
                throw new \Exception("Nomor seri {$sn} berstatus '{$row->status}' — hanya SN 'tersedia' yang dapat dijual");
            }
            if ($row->cabang_id !== null && (int) $row->cabang_id !== $cabangId) {
                throw new \Exception("Nomor seri {$sn} bukan milik cabang aktif");
            }

            // [P1-6] Snapshot link LAMA sebelum ditimpa (resale / klaim ulang)
            $this->snapshotLinkDitimpa($row, NomorSeriEvent::AKSI_KLAIM_JUAL, [
                'transaksi_item_id' => $transaksiItemId,
            ]);

            $row->update([
                'status' => NomorSeri::STATUS_TERJUAL,
                'transaksi_item_id' => $transaksiItemId,
                'cabang_id' => $cabangId,
            ]);
        }
    }

    /**
     * Klaim SN utk item pekerjaan tiket servis → status 'servis' + tautan tiket & item.
     *
     * @param  array<int, string>  $snList
     */
    public function klaimServis(array $snList, int $produkId, int $cabangId, int $qty, int $tiketServisId, int $tiketServisItemId): void
    {
        $this->validasiJumlahDanUnik($snList, $qty);

        foreach ($snList as $sn) {
            $row = NomorSeri::where('produk_id', $produkId)
                ->where('nomor_seri', $sn)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                throw new \Exception("Nomor seri {$sn} tidak ditemukan untuk produk ini");
            }
            if ($row->status !== NomorSeri::STATUS_TERSEDIA) {
                throw new \Exception("Nomor seri {$sn} berstatus '{$row->status}' — hanya SN 'tersedia' yang dapat dipakai servis");
            }
            if ($row->cabang_id !== null && (int) $row->cabang_id !== $cabangId) {
                throw new \Exception("Nomor seri {$sn} bukan milik cabang aktif");
            }

            // [P1-6] Snapshot link LAMA sebelum ditimpa (kunjungan servis ke-2+)
            $this->snapshotLinkDitimpa($row, NomorSeriEvent::AKSI_KLAIM_SERVIS, [
                'tiket_servis_id' => $tiketServisId,
                'tiket_servis_item_id' => $tiketServisItemId,
            ]);

            $row->update([
                'status' => NomorSeri::STATUS_SERVIS,
                'cabang_id' => $cabangId,
                'tiket_servis_id' => $tiketServisId,
                'tiket_servis_item_id' => $tiketServisItemId,
            ]);
        }
    }

    /**
     * Tiket selesai → SN kembali 'tersedia'. Tautan riwayat (tiket/item) TETAP disimpan
     * agar trace laporan histori & garansi tidak hilang.
     *
     * @return int jumlah SN yg dilepas
     */
    public function lepasServis(int $tiketServisId): int
    {
        return NomorSeri::where('tiket_servis_id', $tiketServisId)
            ->where('status', NomorSeri::STATUS_SERVIS)
            ->update(['status' => NomorSeri::STATUS_TERSEDIA]);
    }

    /**
     * Autocomplete SN (POS & Tiket) — status tersedia + cabang aktif (+ filter produk opsional).
     *
     * @return Collection<int, NomorSeri>
     */
    public function cariTersedia(?int $cabangId, ?int $produkId, string $q, int $limit = 8): Collection
    {
        return NomorSeri::query()
            ->where('status', NomorSeri::STATUS_TERSEDIA)
            ->when($cabangId, fn ($query) => $query->where('cabang_id', $cabangId))
            ->when($produkId, fn ($query) => $query->where('produk_id', $produkId))
            ->when($q !== '', fn ($query) => $query->where('nomor_seri', 'like', "%{$q}%"))
            ->with('produk')
            ->orderBy('nomor_seri')
            ->limit($limit)
            ->get();
    }

    /**
     * [P1-6] Sebelum kolom link ditimpa klaim baru: bila ada nilai LAMA non-null
     * yg berbeda dari nilai baru → tulis 1 baris riwayat append-only
     * (nomor_seri_events) berisi SEMUA link lama + status saat itu. Dengan ini
     * rantai permanen (2 kunjungan servis / resale) tetap terbaca walau kolom
     * terkini di nomor_seri_produk menunjuk klaim terakhir.
     *
     * @param  array<string, int|null>  $linkBaru  kolom link yg akan ditimpa
     */
    private function snapshotLinkDitimpa(NomorSeri $row, string $aksi, array $linkBaru): void
    {
        $ditimpa = false;
        foreach ($linkBaru as $kolom => $nilaiBaru) {
            $nilaiLama = $row->{$kolom} !== null ? (int) $row->{$kolom} : null;
            if ($nilaiLama !== null && $nilaiLama !== (int) $nilaiBaru) {
                $ditimpa = true;
                break;
            }
        }

        if (! $ditimpa) {
            return;
        }

        NomorSeriEvent::create([
            'nomor_seri_id' => $row->id,
            'cabang_id' => $row->cabang_id,
            'aksi' => $aksi,
            'status_sebelum' => $row->status,
            'transaksi_item_id' => $row->transaksi_item_id,
            'tiket_servis_id' => $row->tiket_servis_id,
            'tiket_servis_item_id' => $row->tiket_servis_item_id,
        ]);
    }

    /**
     * Validasi jumlah & ketiadaan dobel dalam satu daftar klaim.
     *
     * @param  array<int, string>  $snList
     */
    private function validasiJumlahDanUnik(array $snList, int $qty): void
    {
        $jumlah = count($snList);
        if ($jumlah !== $qty) {
            throw new \Exception(
                "Jumlah nomor seri ({$jumlah}) harus sama dengan qty ({$qty})"
            );
        }

        $this->tolakJikaAdaDobel($snList);
    }

    /**
     * Tolak bila ada SN sama lebih dari sekali dalam daftar.
     *
     * @param  array<int, string>  $snList
     */
    private function tolakJikaAdaDobel(array $snList): void
    {
        $hitung = array_count_values($snList);
        $dupes = array_keys(array_filter($hitung, fn (int $c) => $c > 1));
        if ($dupes !== []) {
            throw new \Exception('Nomor seri dobel: '.implode(', ', $dupes));
        }
    }
}
