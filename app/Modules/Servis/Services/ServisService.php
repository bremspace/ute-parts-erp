<?php

namespace App\Modules\Servis\Services;

use App\Models\User;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Pos\Services\PricingService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Services\AuditService;
use App\Modules\Reseller\Services\KomisiService;
use App\Modules\Servis\Models\Garansi;
use App\Modules\Servis\Models\JenisServis;
use App\Modules\Servis\Models\ServisSparepart;
use App\Modules\Servis\Models\ServisStatusLog;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Models\TiketServisEstimasiItem;
use App\Modules\Servis\Models\TiketServisItem;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Services\NomorSeriService;
use App\Modules\Wms\Services\StokDeductionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ServisService
{
    public function __construct(
        protected NotificationService $notifService
    ) {}

    /**
     * [SERVICE-01] Terima unit servis baru.
     */
    public function terimaUnit(array $data, User $user): TiketServis
    {
        $cabangId = $data['cabang_id'] ?? session('cabang_id') ?? $user->cabangs()->first()?->id;
        if (! $cabangId) {
            throw new \Exception('Cabang aktif belum dipilih');
        }

        $today = now()->format('Ymd');
        $count = TiketServis::whereDate('created_at', now()->toDateString())
            ->where('cabang_id', $cabangId)
            ->count() + 1;
        $noTiket = sprintf('SRV-C%02d-%s-%04d', $cabangId, $today, $count);

        $tiket = TiketServis::create([
            'no_tiket' => $noTiket,
            'cabang_id' => $cabangId,
            'jenis_servis_id' => $data['jenis_servis_id'] ?? null,
            'pelanggan_id' => $data['pelanggan_id'] ?? null,
            'nama_pelanggan' => $data['nama_pelanggan'] ?? null,
            'telepon_pelanggan' => $data['telepon_pelanggan'] ?? null,
            'jenis_hp' => $data['jenis_hp'],
            'seri_hp' => $data['seri_hp'] ?? null,
            // [T-19] kunci gadget: terenkripsi at-rest (cast encrypted)
            'tipe_kunci' => $data['tipe_kunci'] ?? null,
            'kunci_terenkripsi' => $data['kunci_terenkripsi'] ?? null,
            'keluhan' => $data['keluhan'],
            'kondisi_fisik' => $data['kondisi_fisik'] ?? null,
            'foto_unit' => $data['foto_unit'] ?? null,
            'status' => 'diterima',
            'sumber' => $data['sumber'] ?? 'walkin',
            'token_approval' => Str::random(64),
            'tanggal_terima' => now(),
        ]);

        $this->logStatus($tiket, null, 'diterima', $user, 'transisi', 'Unit servis diterima');

        $telepon = $tiket->telepon_pelanggan ?? $tiket->pelanggan?->telepon;
        if (filled($telepon)) {
            $this->notifService->kirim(
                'wa',
                $telepon,
                'Unit Servis Diterima',
                "Unit {$tiket->jenis_hp} ({$tiket->no_tiket}) telah kami terima. Pantau perbaikan Anda di: ".url("/tracking/{$tiket->token_approval}"),
                ['tiket_id' => $tiket->id]
            );
        } else {
            $this->notifService->kirim(
                'inapp',
                null,
                'Unit Servis Diterima',
                "Unit {$tiket->jenis_hp} ({$tiket->no_tiket}) telah diterima di cabang.",
                ['tiket_id' => $tiket->id]
            );
        }

        return $tiket;
    }

    /**
     * [SERVICE-07] Booking servis dari marketplace (public, no auth).
     */
    public function bookingOnline(array $data): TiketServis
    {
        $cabangId = $data['cabang_id'] ?? Cabang::where('is_active', true)->first()?->id;

        $today = now()->format('Ymd');
        $count = TiketServis::whereDate('created_at', now()->toDateString())->count() + 1;
        $noTiket = sprintf('SRV-ONL-%s-%04d', $today, $count);

        $tiket = TiketServis::create([
            'no_tiket' => $noTiket,
            'cabang_id' => $cabangId,
            'pelanggan_id' => $data['pelanggan_id'] ?? null,
            'nama_pelanggan' => $data['nama'] ?? null,
            'telepon_pelanggan' => $data['telepon'] ?? null,
            'jenis_hp' => $data['jenis_hp'],
            'seri_hp' => $data['seri_hp'] ?? null,
            'keluhan' => $data['keluhan'],
            'kondisi_fisik' => $data['kondisi_fisik'] ?? null,
            'foto_unit' => $data['foto_unit'] ?? null,
            'status' => 'diajukan_online',
            'sumber' => 'online',
            'token_approval' => Str::random(64),
        ]);

        $this->logStatus($tiket, null, 'diajukan_online', null, 'transisi', 'Booking servis online');

        // Notifikasi ke admin cabang (queued)
        $this->notifService->kirim(
            'inapp', null,
            'Servis Online Baru',
            "Booking servis dari {$tiket->nama_pelanggan} untuk {$tiket->jenis_hp} — menunggu konfirmasi diterima",
            ['tiket_id' => $tiket->id]
        );

        return $tiket;
    }

    /**
     * [SERVICE-03] Update status tiket (state machine enforced).
     * $user nullable — untuk approval publik via token (tanpa login).
     *
     * [B-10a / P0-4] Fail-closed: perubahan status + side effect (termasuk
     * jurnal `onSelesai`) dibungkus SATU DB::transaction. Sebelumnya status
     * di-commit duluan lalu jurnal otomatis di-try/catch `Log::warning()` →
     * tiket bisa "selesai" tanpa jurnal sama sekali. Sekarang jurnal gagal =
     * seluruh transaksi rollback, status TIDAK final.
     *
     * [T-09] Status tujuan WAJIB anggota `ServisStateMachine::allStatuses()`
     * SEBELUM apa pun (termasuk sebelum cek transisi/izin override) — tanpa ini
     * `override` admin bisa menulis status ngawur ke DB sehingga tiket hilang
     * dari seluruh alur (Kanban, state machine, jurnal).
     *
     * [P1-1] Baris tiket DIKUNCI (`SELECT ... FOR UPDATE`) di dalam transaksi
     * dan transisi divalidasi ULANG terhadap status yang sudah terkunci.
     * Validasi di luar transaksi membaca snapshot yang bisa basi: dua request
     * paralel bisa dua-duanya lolos validasi lalu dua-duanya menjalankan side
     * effect (jurnal dobel, pembalikan stok dobel). Kunci + validasi ulang
     * membuat request kedua melihat status hasil request pertama.
     *
     * State machine TIDAK berubah: himpunan & aturan transisi tetap sama.
     */
    public function updateStatus(TiketServis $tiket, string $statusBaru, ?User $user = null, string $alasan = ''): TiketServis
    {
        // Validasi cepat (fail-early, tanpa transaksi) — pesan ramah untuk UI.
        if (! in_array($statusBaru, ServisStateMachine::allStatuses(), true)) {
            throw new \Exception('Status tidak valid');
        }

        $statusLama = $tiket->status;
        if (! ServisStateMachine::dapatTransisi($statusLama, $statusBaru)
            && (! $user || ! $user->can('servis.override-status'))) {
            throw new \Exception(
                "Transisi dari '{$statusLama}' ke '{$statusBaru}' tidak valid. Hanya admin dengan override yang diizinkan."
            );
        }

        // [P1-1] `&$tiket` — objek hasil lock di dalam transaksi dipropagasikan
        // ke pemanggil supaya status yang terlihat pemanggil = status terkunci.
        DB::transaction(function () use (&$tiket, $statusBaru, $user, $alasan) {
            $tiket = TiketServis::whereKey($tiket->id)->lockForUpdate()->firstOrFail();

            // Status otoritatif: yang tertulis di DB saat kunci dipegang.
            $statusLama = $tiket->status;

            // Validasi transisi diulang terhadap status TERKUNCI. Kalau request
            // paralel sudah mengubah status duluan, baris ini yang menolak —
            // bukan lolos diam-diam lalu menjalankan side effect dua kali.
            if (ServisStateMachine::dapatTransisi($statusLama, $statusBaru)) {
                $aksi = 'transisi';
            } elseif ($user && $user->can('servis.override-status')) {
                $aksi = 'override';
            } else {
                throw new \Exception(
                    "Transisi dari '{$statusLama}' ke '{$statusBaru}' tidak valid. Hanya admin dengan override yang diizinkan."
                );
            }

            if ($aksi === 'override' && blank($alasan)) {
                throw new \Exception('Alasan wajib diisi saat melakukan override status');
            }

            // [T-13] Guard transisi diambil: harus lunas
            if ($statusBaru === 'diambil' && $tiket->status_pembayaran !== 'lunas') {
                throw new \Exception('Unit tidak dapat diserahkan/diambil sebelum pembayaran lunas');
            }

            $tiket->update(['status' => $statusBaru]);
            $this->logStatus($tiket, $statusLama, $statusBaru, $user, $aksi, $alasan);

            match ($statusBaru) {
                'menunggu_approval' => $this->onMenungguApproval($tiket),
                'diterima' => $this->onDiterima($tiket),
                // [T-03] Pengembalian stok part saat tiket ditolak (belum ada
                // jurnal onSelesai) — lihat onDitolak().
                'ditolak' => $this->onDitolak($tiket),
                'selesai' => $this->onSelesai($tiket),
                'diambil' => $this->onDiambil($tiket),
                default => null,
            };

            // [P1-5] Lepas klaim SN: saat transisi ke 'ditolak' (valid maupun
            // override), atau override mundur melewati 'disetujui' — tanpa ini SN
            // macet status 'servis' selamanya (tidak bisa dijual, tak terlihat di
            // 'tersedia'). lepasServis TIDAK meng-clear tautan riwayat
            // (tiket_servis_id & tiket_servis_item_id tetap utk trace garansi).
            if ($this->perluLepasSn($statusBaru, $aksi)) {
                app(NomorSeriService::class)->lepasServis((int) $tiket->id);
            }
        }, 3);

        // Notifikasi ke pelanggan — SETELAH commit (jangan kirim bila rollback)
        $labelMap = [
            'diajukan_online' => 'Menunggu Konfirmasi',
            'diterima' => 'Unit Diterima di Cabang',
            'diagnosa' => 'Sedang Didiagnosa Teknisi',
            'menunggu_approval' => 'Menunggu Persetujuan Estimasi Biaya',
            'disetujui' => 'Estimasi Disetujui',
            'dikerjakan' => 'Sedang Diperbaiki',
            'qc' => 'Pemeriksaan Kualitas (QC)',
            'selesai' => 'Perbaikan Selesai (Siap Diambil)',
            'diambil' => 'Unit Telah Diserahkan',
            'ditolak' => 'Estimasi Ditolak',
        ];
        $labelStatus = $labelMap[$statusBaru] ?? $statusBaru;
        $trackingUrl = $tiket->token_approval ? url("/tracking/{$tiket->token_approval}") : '';
        $pesanNotif = "Unit {$tiket->jenis_hp} ({$tiket->no_tiket}): {$labelStatus}.";
        if ($trackingUrl) {
            $pesanNotif .= ' Pantau status perbaikan Anda di '.$trackingUrl;
        }

        $telepon = $tiket->telepon_pelanggan ?? $tiket->pelanggan?->telepon;
        if (filled($telepon)) {
            $this->notifService->kirim(
                'wa',
                $telepon,
                'Status Servis Diperbarui',
                $pesanNotif,
                ['tiket_id' => $tiket->id, 'status' => $statusBaru]
            );
        } else {
            $this->notifService->kirim(
                'inapp',
                null,
                'Status Servis Diperbarui',
                $pesanNotif,
                ['tiket_id' => $tiket->id, 'status' => $statusBaru]
            );
        }

        return $tiket;
    }

    /**
     * [SERVICE-05] Input estimasi biaya → status menunggu_approval.
     *
     * [T-07] Empat perbaikan (P0-7 + P2 "token di-regenerate"):
     *  1. `$statusLama` ditangkap SEBELUM `update()` — sebelumnya `logStatus()`
     *     menerima `$tiket->status` yang sudah 'menunggu_approval', sehingga
     *     timeline mencatat self-loop `menunggu_approval → menunggu_approval`
     *     dan status ASAL hilang permanen.
     *  2. Status + log + token dibungkus SATU `DB::transaction` dan transisi
     *     divalidasi lewat `ServisStateMachine` (tidak lagi bypass).
     *  3. Token approval TIDAK di-regenerate: hanya dibuat bila masih null
     *     (pola `onMenungguApproval`) supaya link yang sudah dibagikan ke
     *     pelanggan tidak mati saat estimasi diedit ulang.
     *  4. Notifikasi dikirim SETELAH commit (tidak terkirim bila rollback).
     */
    public function setEstimasi(TiketServis $tiket, float $biaya, string $alasan, User $user, array $items = []): TiketServis
    {
        $statusLama = $tiket->status;

        $validForEstimate = ['diagnosa', 'menunggu_approval', 'ditolak'];
        if (! in_array($statusLama, $validForEstimate, true)) {
            throw new \Exception("Hanya tiket berstatus 'diagnosa', 'menunggu_approval', atau 'ditolak' yang dapat diestimasi");
        }

        // [T-07] Transisi WAJIB sah menurut state machine (allowlist di atas
        // tetap jadi pesan ramah, bukan sumber kebenaran).
        if (! $this->transisiEstimasiValid($statusLama)) {
            throw new \Exception("Transisi dari '{$statusLama}' ke 'menunggu_approval' tidak valid");
        }

        DB::transaction(function () use ($tiket, $statusLama, &$biaya, $alasan, $user, $items) {
            if (! empty($items)) {
                TiketServisEstimasiItem::where('tiket_servis_id', $tiket->id)->delete();

                $totalDariItems = 0.0;
                $pricingService = app(PricingService::class);

                foreach ($items as $item) {
                    $tipe = $item['tipe'] ?? 'jasa';
                    $qty = isset($item['qty']) ? max(1, (int) $item['qty']) : 1;
                    $harga = isset($item['harga']) ? (float) $item['harga'] : null;
                    $namaItem = $item['nama_item'] ?? '';
                    $produkId = $item['produk_id'] ?? null;
                    $skuVariantId = $item['sku_variant_id'] ?? null;
                    $jenisServisId = $item['jenis_servis_id'] ?? null;

                    if ($tipe === 'part') {
                        $produk = $produkId ? Produk::find($produkId) : null;
                        $variant = $skuVariantId ? SkuVariant::find($skuVariantId) : null;

                        if (! $namaItem && $produk) {
                            $namaItem = $produk->nama;
                        }

                        if ($harga === null && $produk) {
                            $resolved = $pricingService->resolve($produk, $tiket->pelanggan, $variant);
                            $harga = (float) ($resolved['harga'] ?? $produk->harga_jual ?? 0);
                        }
                    } elseif ($tipe === 'jasa') {
                        $jenisServis = $jenisServisId ? JenisServis::find($jenisServisId) : null;

                        if (! $namaItem && $jenisServis) {
                            $namaItem = $jenisServis->nama;
                        }

                        if ($harga === null && $jenisServis) {
                            $harga = (float) ($jenisServis->biaya_jasa ?? 0);
                        }
                    }

                    $hargaFinal = max(0, (float) ($harga ?? 0));
                    $subtotal = $qty * $hargaFinal;
                    $totalDariItems += $subtotal;

                    TiketServisEstimasiItem::create([
                        'tiket_servis_id' => $tiket->id,
                        'tipe' => $tipe,
                        'jenis_servis_id' => $jenisServisId,
                        'produk_id' => $produkId,
                        'sku_variant_id' => $skuVariantId,
                        'nama_item' => $namaItem ?: ($tipe === 'part' ? 'Sparepart' : 'Jasa Servis'),
                        'qty' => $qty,
                        'harga' => $hargaFinal,
                        'subtotal' => $subtotal,
                    ]);
                }

                if ($biaya <= 0) {
                    $biaya = $totalDariItems;
                }
            }

            $tiket->update([
                'estimasi_biaya' => $biaya,
                'alasan_estimasi' => $alasan,
                'status' => 'menunggu_approval',
            ]);

            // Token hanya bila belum ada — link yang sudah dibagikan tetap hidup.
            if (! $tiket->token_approval) {
                $tiket->update(['token_approval' => Str::random(64)]);
            }

            $aksiLog = $statusLama === 'menunggu_approval' ? 'edit_estimasi' : 'transisi';
            $this->logStatus($tiket, $statusLama, 'menunggu_approval', $user, $aksiLog, "Estimasi biaya: Rp {$biaya}");
        }, 3);

        // Notifikasi + link publik approval (SETELAH commit)
        $token = $tiket->token_approval;
        $linkApprove = url("/tracking/{$token}");
        $telepon = $tiket->telepon_pelanggan ?? $tiket->pelanggan?->telepon;
        $pesanEstimasi = "Estimasi biaya perbaikan unit {$tiket->jenis_hp} Anda adalah Rp ".number_format($biaya, 0, ',', '.').". Buka tautan untuk melihat rincian & menyetujui: {$linkApprove}";

        if (filled($telepon)) {
            $this->notifService->kirim(
                'wa',
                $telepon,
                'Estimasi Servis Perlu Approval',
                $pesanEstimasi,
                ['tiket_id' => $tiket->id, 'token' => $token]
            );
        } else {
            $this->notifService->kirim(
                'inapp',
                null,
                'Estimasi Servis Perlu Approval',
                $pesanEstimasi,
                ['tiket_id' => $tiket->id, 'token' => $token]
            );
        }

        return $tiket;
    }

    /**
     * [T-07] Apakah tiket berstatus ini boleh di-estimasi (menuju
     * 'menunggu_approval') menurut state machine?
     *
     * - 'diagnosa' → 'menunggu_approval': transisi forward langsung (valid).
     * - 'ditolak' → re-estimasi: state machine mewajibkan hop
     *   'ditolak → diagnosa' dulu, jadi kedua hop diperiksa (bukan bypass).
     */
    private function transisiEstimasiValid(string $statusLama): bool
    {
        if ($statusLama === 'menunggu_approval') {
            return true;
        }

        if (ServisStateMachine::dapatTransisi($statusLama, 'menunggu_approval')) {
            return true;
        }

        return $statusLama === 'ditolak'
            && ServisStateMachine::dapatTransisi('ditolak', 'diagnosa')
            && ServisStateMachine::dapatTransisi('diagnosa', 'menunggu_approval');
    }

    /**
     * [SERVICE-06] Publik approve/reject (token auth).
     */
    public function approveByToken(string $token, string $action, string $alasan = ''): TiketServis
    {
        $tiket = TiketServis::where('token_approval', $token)->firstOrFail();

        if ($tiket->status !== 'menunggu_approval') {
            throw new \Exception('Tiket tidak dalam status menunggu approval');
        }

        if ($action === 'approve') {
            return $this->updateStatus($tiket, 'disetujui', null, $alasan);
        } else {
            return $this->updateStatus($tiket, 'ditolak', null, $alasan ?: 'Estimasi ditolak oleh pelanggan');
        }
    }

    /**
     * Input sparepart terpakai → kurangi stok + catat log (jalur LEGACY).
     *
     * [T-08] Guard status dipersempit ke ['disetujui','dikerjakan'] — 'diagnosa'
     * DIHAPUS karena potong stok sebelum pelanggan menyetujui estimasi
     * (P0-8). Jalur form pekerjaan (T-17) tetap menerima 'qc' seperti sedia.
     *
     * [T-08] Produk `sn=true` DITOLAK di jalur ini: jalur legacy tidak pernah
     * klaim SN, jadi unit ber-SN bisa terjual ulang. Pesan mengarahkan teknisi
     * ke form pekerjaan (satu-satunya jalur yang mengklaim SN).
     *
     * [T-05] Anti dobel potong stok dua arah: jalur legacy hanya boleh dipakai
     * bila belum ada item part AKTIF via form pekerjaan (`dibatalkan_at` null).
     *
     * [T-06] `gudang_id` WAJIB-branch: gudang harus milik cabang tiket, bukan
     * sekadar `exists` — kalau tidak, stok cabang B terpotong sementara jurnal
     * masuk cabang A.
     */
    public function inputSparepart(TiketServis $tiket, array $items, int $gudangId, User $user): array
    {
        // [P0-2] Tiket yang SUDAH pernah selesai tidak boleh/item baru. Kalau
        // diizinkan, item masuk tapi jurnal `onSelesai` sudah terlanjur
        // ter-post → pendapatan + HPP hilang (silent unjournaled revenue) dan
        // stok terpotong tanpa jurnal. Dicek sebelum guard status karena
        // override mundur (selesai → dikerjakan) membuat status check lolos.
        if ($tiket->tanggal_selesai !== null) {
            throw new \Exception('Tidak dapat menambah item pada tiket yang sudah pernah diselesaikan');
        }

        if (! in_array($tiket->status, ['disetujui', 'dikerjakan'], true)) {
            throw new \Exception('Input sparepart hanya dapat dilakukan saat status disetujui atau dikerjakan');
        }

        // [T-17/T-05] Anti dobel potong stok: jalur legacy hanya boleh dipakai bila
        // belum ada item part AKTIF via form pekerjaan (TiketServisItem). Bila
        // sudah — tolak. Baris yang sudah dibatalkan (reversal) tidak dihitung.
        if (TiketServisItem::where('tiket_servis_id', $tiket->id)
            ->where('tipe', 'part')
            ->whereNull('dibatalkan_at')
            ->exists()) {
            throw new \Exception('Item sudah dicatat via form pekerjaan');
        }

        // [T-06] Gudang harus di cabang yang sama dengan tiket
        $this->validasiGudangCabang($gudangId, (int) $tiket->cabang_id);

        $created = [];

        DB::transaction(function () use ($tiket, $items, $gudangId, $user, &$created) {
            foreach ($items as $item) {
                $produk = Produk::findOrFail($item['produk_id']);
                $qty = (int) $item['jumlah'];

                // [T-08] Jalur legacy tidak mengklaim SN — tolak produk ber-SN
                // agar unit tidak pernah terjual dua kali.
                if ($produk->sn) {
                    throw new \Exception('Produk bernomor seri harus diinput lewat form pekerjaan');
                }

                // [B-03/P1-3] Deduksi via StokDeductionService — kriteria resolusi baris
                // stok sama dgn jalur lain (varian eksak / fallback kanonik tanpa varian),
                // dilengkapi lockForUpdate + StokLog + StockMutationLog (sinkron channel).
                try {
                    app(StokDeductionService::class)->kurangi(
                        produkId: $produk->id,
                        skuVariantId: $item['sku_variant_id'] ?? null,
                        gudangId: $gudangId,
                        qty: $qty,
                        jenis: 'servis',
                        referensiTipe: TiketServis::class,
                        referensiId: $tiket->id,
                        userId: $user->id,
                        catatan: "Servis {$tiket->no_tiket} — sparepart terpakai",
                    );
                } catch (\Exception $e) {
                    // Pesan lama dipertahankan (format yg dikenali teknisi/UI)
                    if (preg_match('/tersedia: (\d+)/', $e->getMessage(), $m)) {
                        throw new \Exception("Stok sparepart {$produk->nama} tidak mencukupi (tersedia: {$m[1]})");
                    }

                    throw $e;
                }

                $sparepart = ServisSparepart::create([
                    'tiket_servis_id' => $tiket->id,
                    'produk_id' => $produk->id,
                    'sku_variant_id' => $item['sku_variant_id'] ?? null,
                    'gudang_id' => $gudangId,
                    'jumlah' => $qty,
                    'harga_satuan' => $item['harga_satuan'] ?? (float) $produk->harga_jual_retail,
                    'hpp' => (float) $produk->harga_beli,
                ]);

                $created[] = $sparepart;
            }
        });

        return $created;
    }

    /**
     * [T-17] Input pekerjaan teknisi — split part & jasa via TiketServisItem.
     * Baris tipe=jasa: tidak menyentuh stok. Baris tipe=part: kurangi StokItem 1x + StokLog.
     *
     * @param  array  $items  [['tipe'=>'part|jasa','produk_id'=>?,'sku_variant_id'=>?,'nama_item'=>string,'qty'=>int,'harga'=>float,'gudang_id'=>(wajib utk part)], ...]
     */
    public function inputPekerjaan(TiketServis $tiket, array $items, User $user): array
    {
        // [P0-2] Tiket yang SUDAH pernah selesai tidak boleh/item baru (lihat
        // catatan lengkap di inputSparepart()).
        if ($tiket->tanggal_selesai !== null) {
            throw new \Exception('Tidak dapat menambah item pada tiket yang sudah pernah diselesaikan');
        }

        if (! in_array($tiket->status, ['disetujui', 'dikerjakan'], true)) {
            throw new \Exception('Input pekerjaan hanya saat status disetujui / dikerjakan');
        }

        // [T-05/P1-3] Anti dobel potong stok SIMETRIS, tapi hanya berlaku bila
        // payload benar-benar berisi baris tipe 'part':
        //  - payload punya baris 'part' DAN sudah ada sparepart legacy AKTIF → tolak
        //    (kedua jalur bisa memotong stok produk yang sama dua kali).
        //  - payload HANYA baris 'jasa' → IZINKAN. Baris jasa tidak menyentuh
        //    stok, jadi tidak ada risiko potong dobel; menolak payload jasa
        //    membuat teknisi tidak bisa sama sekali mencatat jasa pada tiket
        //    yang kebetulan punya sparepart legacy.
        $payloadAdaPart = collect($items)->contains(
            fn ($item) => ($item['tipe'] ?? 'jasa') === 'part'
        );

        if ($payloadAdaPart && ServisSparepart::where('tiket_servis_id', $tiket->id)
            ->whereNull('dibatalkan_at')
            ->exists()) {
            throw new \Exception('Item part sudah dicatat lewat form sparepart. Untuk menambah jasa, kirim baris tipe jasa saja.');
        }

        $created = [];

        DB::transaction(function () use ($tiket, $items, $user, &$created) {
            foreach ($items as $item) {
                $tipe = $item['tipe'] ?? 'jasa';
                $namaItem = $item['nama_item'];
                $qty = (int) ($item['qty'] ?? 1);
                $harga = (float) ($item['harga'] ?? 0);
                $produk = null;

                if ($tipe === 'part') {
                    $produkId = $item['produk_id'] ?? null;
                    if (! $produkId) {
                        throw new \Exception("Item part '{$namaItem}' wajib pilih produk");
                    }
                    $produk = Produk::findOrFail($produkId);
                    $gudangId = $item['gudang_id'] ?? null;
                    if (! $gudangId) {
                        throw new \Exception("Part '{$namaItem}' wajib pilih gudang");
                    }

                    // [T-06] Gudang wajib di cabang yang sama dengan tiket
                    $this->validasiGudangCabang((int) $gudangId, (int) $tiket->cabang_id);

                    // Deduct stok 1x (fee guard: lockForUpdate di StokDeductionService reklarasi)
                    $this->kurangiStokServis($produk->id, $item['sku_variant_id'] ?? null, (int) $gudangId, $qty, $tiket, $user);
                }

                $tiketItem = TiketServisItem::create([
                    'tiket_servis_id' => $tiket->id,
                    'tipe' => $tipe,
                    'produk_id' => $tipe === 'part' ? ($item['produk_id'] ?? null) : null,
                    'sku_variant_id' => $tipe === 'part' ? ($item['sku_variant_id'] ?? null) : null,
                    'nama_item' => $namaItem,
                    'qty' => $qty,
                    'harga' => $harga,
                    'hpp' => $tipe === 'part' && ($item['produk_id'] ?? null)
                        ? (float) (Produk::find($item['produk_id'])?->harga_beli ?? 0)
                        : 0,
                ]);

                // [F2-3] Produk sn=true → SN wajib: validasi (jumlah == qty, tersedia,
                // cabang aktif) + klaim status 'servis' + tautan tiket & item.
                // Throw → rollback seluruh pekerjaan (stok & item ikut batal).
                if ($tipe === 'part' && $produk?->sn) {
                    app(NomorSeriService::class)->klaimServis(
                        array_values($item['sn'] ?? []),
                        (int) $produk->id,
                        (int) $tiket->cabang_id,
                        $qty,
                        (int) $tiket->id,
                        (int) $tiketItem->id
                    );
                }

                $created[] = $tiketItem;
            }
        });

        return $created;
    }

    /**
     * [T-06] Validasi gudang WAJIB berada di cabang yang sama dengan tiket.
     *
     * Sebelum T-06 validasi controller hanya `exists:gudang,id`, jadi part bisa
     * dipotong dari gudang cabang lain sementara jurnal servis masuk ke cabang
     * tiket (stok cabang B berkurang, pembukuan cabang A). Pesan Indonesia.
     */
    private function validasiGudangCabang(int $gudangId, int $cabangIdTiket): void
    {
        $gudang = Gudang::find($gudangId);

        if (! $gudang) {
            throw new \Exception('Gudang tidak ditemukan');
        }

        if ((int) $gudang->cabang_id !== (int) $cabangIdTiket) {
            throw new \Exception('Gudang harus berada di cabang yang sama dengan tiket');
        }
    }

    private function kurangiStokServis(int $produkId, ?int $variantId, int $gudangId, int $qty, TiketServis $tiket, User $user): void
    {
        // [B-03/P1-3] Delegasi ke StokDeductionService — lockForUpdate, kriteria
        // resolusi baris stok konsisten dgn praseleksi, StokLog + StockMutationLog.
        try {
            app(StokDeductionService::class)->kurangi(
                produkId: $produkId,
                skuVariantId: $variantId,
                gudangId: $gudangId,
                qty: $qty,
                jenis: 'servis',
                referensiTipe: TiketServis::class,
                referensiId: $tiket->id,
                userId: $user->id,
                catatan: "Servis {$tiket->no_tiket} — item part",
            );
        } catch (\Exception $e) {
            if (preg_match('/tersedia: (\d+)/', $e->getMessage(), $m)) {
                throw new \Exception("Stok sparepart tidak mencukupi di gudang terpilih (tersedia: {$m[1]})");
            }

            throw $e;
        }
    }

    /**
     * [T-12] Pelunasan pembayaran servis.
     */
    public function bayar(TiketServis $tiket, string $metodePembayaran, User $user, ?string $catatan = null): TiketServis
    {
        return DB::transaction(function () use ($tiket, $metodePembayaran, $user, $catatan) {
            $tiket = TiketServis::whereKey($tiket->id)->lockForUpdate()->firstOrFail();

            if (! in_array($tiket->status, ['selesai', 'diambil'], true)) {
                throw new \Exception("Pembayaran hanya dapat dilakukan untuk servis berstatus 'selesai' atau 'diambil'");
            }

            if ($tiket->status_pembayaran === 'lunas') {
                throw new \Exception('Tiket servis sudah lunas');
            }

            $itemsServis = $tiket->items()->whereNull('dibatalkan_at')->get();
            $sparepartsLegacy = $tiket->spareparts()->whereNull('dibatalkan_at')->get();

            $pendapatanJasa = (float) $itemsServis->where('tipe', 'jasa')->sum(fn ($i) => (float) $i->harga * (int) $i->qty);
            $pendapatanPart = (float) $itemsServis->where('tipe', 'part')->sum(fn ($i) => (float) $i->harga * (int) $i->qty);
            $jualLegacy = (float) $sparepartsLegacy->sum(fn ($sp) => (float) $sp->harga_satuan * (int) $sp->jumlah);

            $jasaServis = $pendapatanJasa > 0 ? $pendapatanJasa : (float) ($tiket->estimasi_biaya ?? 0);
            $totalJualSparepart = $pendapatanPart + $jualLegacy;
            $totalTagihan = $jasaServis + $totalJualSparepart;

            $noJurnal = null;
            if ($totalTagihan > 0) {
                $jurnalService = app(JurnalService::class);
                $noJurnal = $jurnalService->generateNoJurnal('servis-bayar', $tiket->cabang_id);

                $lines = [
                    ['akun_kode' => '110-01', 'debit' => $totalTagihan, 'kredit' => 0], // Kas bertambah
                    ['akun_kode' => '120-01', 'debit' => 0, 'kredit' => $totalTagihan], // Piutang Usaha lunas
                ];

                $jurnalService->post(
                    noJurnal: $noJurnal,
                    tanggal: now(),
                    sumber: 'servis',
                    lines: $lines,
                    deskripsi: "Pelunasan servis {$tiket->no_tiket} ({$metodePembayaran})",
                    cabangId: $tiket->cabang_id,
                    userId: $user->id,
                    referensiTipe: TiketServis::class,
                    referensiId: $tiket->id,
                    idempotencyKey: 'servis-bayar:'.$tiket->id,
                );
            }

            $tiket->update([
                'status_pembayaran' => 'lunas',
                'tanggal_bayar' => now(),
                'metode_pembayaran' => $metodePembayaran,
                'no_jurnal_bayar' => $noJurnal,
            ]);

            $pesanLog = "Pelunasan servis ({$metodePembayaran}): Rp {$totalTagihan}";
            if ($catatan) {
                $pesanLog .= " - {$catatan}";
            }

            $this->logStatus($tiket, $tiket->status, $tiket->status, $user, 'pembayaran', $pesanLog);

            return $tiket;
        }, 3);
    }

    /**
     * Get tiket by token_approval (public tracking).
     */
    public function getByToken(string $token): TiketServis
    {
        return TiketServis::with(['garansi', 'spareparts.produk', 'jenisservis', 'pelanggan'])
            ->where('token_approval', $token)
            ->firstOrFail();
    }

    // --- Private side-effect methods ---

    /**
     * [P1-5] Apakah SN wajib dilepas pada transisi ini?
     * - 'ditolak': selalu (valid dari menunggu_approval / override dari mana pun).
     * - override ke status di luar zona kerja (disetujui/dikerjakan/qc): klaim SN
     *   tidak lagi sah utk tiket ini (mundur melewati disetujui — atau melompat
     *   lewati selesai, krn onSelesai tidak jalan). Idempoten — no-op bila tiket
     *   ini tidak menahan SN status 'servis'.
     */
    private function perluLepasSn(string $statusBaru, string $aksi): bool
    {
        if ($statusBaru === 'ditolak') {
            return true;
        }

        return $aksi === 'override'
            && ! in_array($statusBaru, ['disetujui', 'dikerjakan', 'qc'], true);
    }

    private function onMenungguApproval(TiketServis $tiket): void
    {
        if (! $tiket->token_approval) {
            $tiket->update(['token_approval' => Str::random(64)]);
        }
    }

    private function onDiterima(TiketServis $tiket): void
    {
        if (! $tiket->tanggal_terima) {
            $tiket->update(['tanggal_terima' => now()]);
        }
    }

    /**
     * [T-03] Tiket `ditolak` → kembalikan stok semua part yang terpasang.
     *
     * Syarat pembalikan (fail-safe):
     * - `tanggal_selesai` masih null → jurnal `onSelesai` BELUM pernah dipost,
     *   jadi HPP 510-02 / Persediaan 130-01 belum pernah/cmasuk. Bila jurnal
     *   sudah ada (mis. override mundur setelah selesai), pembalikan stok akan
     *   membuat HPP dobel → karena itu dilewati.
     * - hanya baris part AKTIF (`dibatalkan_at` null): reversal kedua tidak
     *   mungkin menggandakan stok.
     *
     * Baris yang dibalik lalu DITANDAI `dibatalkan_at` (bukan dihapus) supaya
     * tetap bisa ditrace dan tidak ikut dihitung ulang saat jurnal `onSelesai`
     * (T-04) maupun guard anti-dobel (T-05).
     */
    private function onDitolak(TiketServis $tiket): void
    {
        // Jurnal onSelesai sudah pernah dipost → HPP sudah tercatat, jangan balik stok.
        if ($tiket->tanggal_selesai) {
            return;
        }

        $this->restoreStokPartDitolak($tiket);
    }

    /**
     * [T-03] Kembalikan stok part ke gudang ASAL + tandai baris dibatalkan.
     *
     * Sumber qty per baris:
     * - `ServisSparepart` (legacy): `gudang_id` tercatat di baris item.
     * - `TiketServisItem` tipe part (T-17): tabel tidak punya kolom `gudang_id`,
     *   jadi gudang + qty diambil dari `StokLog` dediksi tiket ini
     *   (jenis 'servis', referensi TiketServis) — FAKTA aktual potong stok,
     *   sehingga part yang dibuat tanpa pengurangan (mis. impor/tes) otomatis
     *   tidak mengembalikan apa pun.
     *
     * Pengembalian memakai `StokDeductionService::kembalikan()` (lockForUpdate +
     * StokLog pembalik + StockMutationLog) supaya hasilnya persis ke gudang asal.
     */
    private function restoreStokPartDitolak(TiketServis $tiket): void
    {
        // [P1-1] `lockForUpdate()` — dua request "tolak" paralel untuk tiket yang
        // sama tidak boleh sama-sama membaca baris part yang belum dibatalkan:
        // tanpa lock keduanya akan mengembalikan stok dua kali (stok hantu).
        // Dipanggil dari `onDitolak()` di dalam transaksi `updateStatus()`.
        $itemsPart = TiketServisItem::where('tiket_servis_id', $tiket->id)
            ->where('tipe', 'part')
            ->whereNull('dibatalkan_at')
            ->lockForUpdate()
            ->get();

        $spareparts = ServisSparepart::where('tiket_servis_id', $tiket->id)
            ->whereNull('dibatalkan_at')
            ->lockForUpdate()
            ->get();

        if ($itemsPart->isEmpty() && $spareparts->isEmpty()) {
            return; // no-op aman (mis. ditolak dari menunggu_approval tanpa part)
        }

        // Kunci unik per (produk, gudang, varian) — qty yang SUDAH dikembalikan
        // ikut dikurangi supaya tidak ada produk sama yang balik dua kali walau
        // data lama tercampur (legacy + T-17) atau satu produk diinput 2 baris.
        $dikembalikan = [];
        $target = function (int $produkId, ?int $variantId, int $gudangId, int $qty) use (&$dikembalikan, $tiket): void {
            $key = $produkId.'|'.$gudangId.'|'.($variantId ?? 'null');
            $sudah = $dikembalikan[$key] ?? 0;
            $sisa = $qty - $sudah;

            if ($sisa <= 0) {
                return;
            }

            $dikembalikan[$key] = $sudah + $sisa;

            app(StokDeductionService::class)->kembalikan(
                produkId: $produkId,
                skuVariantId: $variantId,
                gudangId: $gudangId,
                qty: $sisa,
                jenis: 'servis',
                referensiTipe: TiketServis::class,
                referensiId: $tiket->id,
                userId: auth()->id(),
                catatan: "Servis {$tiket->no_tiket} — pembalikan part (ditolak)",
            );
        };

        // 1) Legacy: gudang asal tercatat di baris item.
        foreach ($spareparts as $sp) {
            $target((int) $sp->produk_id, $sp->sku_variant_id, (int) $sp->gudang_id, (int) $sp->jumlah);
        }

        // 2) T-17: gudang + qty dari StokLog dediksi tiket ini.
        foreach ($itemsPart as $item) {
            foreach ($this->dediksiPartDariStokLog($tiket, $item) as $d) {
                $target((int) $item->produk_id, $d['sku_variant_id'], $d['gudang_id'], $d['qty']);
            }
        }

        // Tandai baris dibatalkan (query builder, bukan mass-assignment model:
        // kolom `dibatalkan_at` sengaja tidak ditambahkan ke $fillable).
        TiketServisItem::whereIn('id', $itemsPart->modelKeys())->update(['dibatalkan_at' => now()]);
        ServisSparepart::whereIn('id', $spareparts->modelKeys())->update(['dibatalkan_at' => now()]);
    }

    /**
     * [T-03/P0-1] Rekap NET outflow satu item part dari `StokLog` tiket ini.
     *
     * [P0-1] Rekap memakai saldo NET (outflow + inflow), bukan hanya outflow.
     * Filter `perubahan < 0` membuat siklus `ditolak → re-estimasi → tambah
     * part → ditolak` mengembalikan SELURUH outflow historis sekali lagi: log
     * siklus-1 sudah +2 (pengembalian), tapi filter mengabaikan +2 sehingga
     * outflow siklus-2 (-1)Tetap dihitung penuh → stok hantu. Dengan saldo
     * net, log (-2, +2, -1) = -1 → hanya 1 yang dikembalikan.
     *
     * @return array<int, array{gudang_id:int, sku_variant_id:?int, qty:int}>
     */
    private function dediksiPartDariStokLog(TiketServis $tiket, TiketServisItem $item): array
    {
        if (! $item->produk_id) {
            return [];
        }

        // Sengaja TANPA `where('perubahan', '<', 0)`: baris pengembalian dari
        // siklus ditolak sebelumnya harus ikut terhitung agar saldonya netral.
        $query = StokLog::where('jenis', 'servis')
            ->where('referensi_tipe', TiketServis::class)
            ->where('referensi_id', $tiket->id)
            ->where('produk_id', $item->produk_id);

        if ($item->sku_variant_id !== null) {
            $query->where('sku_variant_id', $item->sku_variant_id);
        }

        return $query->get()
            ->groupBy(fn (StokLog $log) => ((int) $log->gudang_id).'|'.($log->sku_variant_id === null ? 'null' : (int) $log->sku_variant_id))
            ->map(function ($logs) {
                $first = $logs->first();

                // Net outflow = -(total perubahan). Negatif/0 = stok sudah
                // utuh (belum pernah dipotong, atau sudah dikembalikan penuh).
                $netOutflow = -1 * (int) $logs->sum('perubahan');

                return [
                    'gudang_id' => (int) $first->gudang_id,
                    'sku_variant_id' => $first->sku_variant_id === null ? null : (int) $first->sku_variant_id,
                    'qty' => $netOutflow,
                ];
            })
            // [P0-1] Hanya kembalikan bila net outflow benar-benar > 0.
            ->filter(fn (array $d) => $d['qty'] > 0)
            ->values()
            ->all();
    }

    private function onSelesai(TiketServis $tiket): void
    {
        // [T-01] Guard idempotensi. `tanggal_selesai` terisi = jurnal onSelesai
        // pernah dipost. Skenario `selesai → override mundur ke qc → selesai`
        // akan memunculkan onSelesai DUA kali; tanpa guard ini pendapatan & kas
        // ter-post dua kali (unique index no_jurnal tidak menolong karena
        // generateNoJurnal selalu memberi nomor baru).
        $sudahDiposting = $tiket->tanggal_selesai !== null;

        // [P0-2] Fail-closed untuk item yang masuk SETELAH jurnal pernah dipost.
        // Input part/jasa setelah `tanggal_selesai` terisi tidak ikut dalam
        // jurnal mana pun (jurnal onSelesai hanya jalan sekali) dan tidak ada
        // jalur jurnal susulan otomatis → pendapatan hilang tanpa pembukuan.
        // Guard input-side (inputPekerjaan/inputSparepart) menutup jalur ini
        // sebagai jaring pengaman, tapi data lama/import langsung tetap bisa
        // memunculkan state ini, jadi `onSelesai` juga menolak (melempar =
        // rollback transaksi, status tidak final) alih-alih diam-diam meloloskan.
        if ($sudahDiposting) {
            $itemSetelahSelesai = TiketServisItem::where('tiket_servis_id', $tiket->id)
                ->whereNull('dibatalkan_at')
                ->where('created_at', '>', $tiket->tanggal_selesai)
                ->exists();

            if ($itemSetelahSelesai) {
                throw new \RuntimeException(
                    'Tiket sudah pernah diselesaikan. Penambahan item baru setelah selesai memerlukan penyesuaian jurnal manual.'
                );
            }
        }

        if (! $sudahDiposting) {
            $tiket->update(['tanggal_selesai' => now()]);
        }

        // [F2-3] SN terkait tiket ini: kembali 'tersedia' — tautan riwayat
        // (tiket_servis_id & tiket_servis_item_id) TETAP disimpan utk trace garansi.
        app(NomorSeriService::class)->lepasServis((int) $tiket->id);

        // Auto-create garansi dari konfigurasi jenis servis
        $durasiHari = 30; // default
        if ($tiket->jenisServis) {
            $durasiHari = $tiket->jenisServis->durasi_garansi_hari;
        }

        Garansi::updateOrCreate(
            ['tiket_servis_id' => $tiket->id],
            [
                'durasi_hari' => $durasiHari,
                'tanggal_mulai' => now()->toDateString(),
                'tanggal_berakhir' => now()->addDays($durasiHari)->toDateString(),
                'keterangan' => "Garansi {$durasiHari} hari — Servis {$tiket->no_tiket}",
            ]
        );

        // [T-01] Jurnal (dan komisi) sudah pernah dipost untuk tiket ini →
        // jangan post lagi. `tanggal_selesai` TIDAK ditimpa (tetap waktu selesai
        // yang pertama), garansi di atas `updateOrCreate` (idempoten).
        if ($sudahDiposting) {
            return;
        }

        // Jurnal akuntansi otomatis (PRD §4.6):
        // - Pendapatan Jasa Servis (420-01) kredit = estimasi biaya
        // - HPP sparepart terpakai (510-02) debit, Persediaan (130-01) kredit
        // - Kas (110-01) debit = total tagihan (jasa + sparepart)
        //
        // [B-10a / P0-4] 4 baris jurnal TIDAK diubah. Yang berubah: kegagalan
        // jurnal tidak lagi ditelan `Log::warning()` — exception dilempar lagi
        // supaya transaction `updateStatus()` rollback dan status 'selesai'
        // tidak pernah final tanpa jurnal (fail-closed).
        try {
            $jurnalService = app(JurnalService::class);

            $jasaServis = (float) ($tiket->estimasi_biaya ?? 0);

            // [T-04] Baris part yang SUDAH dibatalkan (reversal saat tiket
            // ditolak) TIDAK ikut dihitung — kalau tidak, HPP 510-02 & Persediaan
            // 130-01 dobel-credit pada siklus ditolak → re-estimasi → selesai.
            $itemsServis = $tiket->items()->whereNull('dibatalkan_at')->get();
            $sparepartsLegacy = $tiket->spareparts()->whereNull('dibatalkan_at')->get();

            // [T-17/T-05] Kedua sumber DIABUKAN (bukan cabang if/else): legacy
            // tetap ikut dihitung walau `items()` tidak kosong, dan tidak ada
            // double-count karena guard anti-dobel T-05 membuat satu produk
            // tidak mungkin tercatat di kedua jalur sekaligus.
            $pendapatanJasa = (float) $itemsServis->where('tipe', 'jasa')->sum(fn ($i) => (float) $i->harga * (int) $i->qty);
            $pendapatanPart = (float) $itemsServis->where('tipe', 'part')->sum(fn ($i) => (float) $i->harga * (int) $i->qty);
            $hppPart = (float) $itemsServis->where('tipe', 'part')->sum(fn ($i) => (float) $i->hpp * (int) $i->qty);

            // Nilai jual sparepart (pendapatan penjualan sparepart) & HPP
            $jualLegacy = (float) $sparepartsLegacy->sum(fn ($sp) => (float) $sp->harga_satuan * (int) $sp->jumlah);
            $hppLegacy = (float) $sparepartsLegacy->sum(fn ($sp) => (float) $sp->hpp * (int) $sp->jumlah);

            $totalJualSparepart = $pendapatanPart + $jualLegacy;
            $totalHpp = $hppPart + $hppLegacy;

            // jasa dari items lebih akurat dari estimasi jika diisi
            if ($pendapatanJasa > 0) {
                $jasaServis = $pendapatanJasa;
            }

            $totalTagihan = $jasaServis + $totalJualSparepart;

            if ($totalTagihan > 0) {
                $noJurnal = $jurnalService->generateNoJurnal('servis', $tiket->cabang_id);

                $lines = [
                    ['akun_kode' => '120-01', 'debit' => $totalTagihan, 'kredit' => 0], // Piutang Usaha diakui saat servis selesai
                    ['akun_kode' => '420-01', 'debit' => 0, 'kredit' => $jasaServis], // Pendapatan jasa servis
                ];

                if ($totalJualSparepart > 0) {
                    $lines[] = ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => $totalJualSparepart]; // Pendapatan penjualan sparepart
                }
                if ($totalHpp > 0) {
                    $lines[] = ['akun_kode' => '510-02', 'debit' => $totalHpp, 'kredit' => 0]; // HPP sparepart
                    $lines[] = ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => $totalHpp]; // Persediaan turun (biaya perolehan)
                }

                $jurnalService->post(
                    noJurnal: $noJurnal,
                    tanggal: now(),
                    sumber: 'servis',
                    lines: $lines,
                    deskripsi: "Jurnal servis {$tiket->no_tiket}",
                    cabangId: $tiket->cabang_id,
                    userId: auth()->id(),
                    referensiTipe: TiketServis::class,
                    referensiId: $tiket->id,
                    // [T-01] Kunci idempotensi stabil per tiket: ikat ke unique
                    // index jurnal_header (cabang_key, idempotency_key) sehingga
                    // posting ganda ditegakkan MESIN DB, bukan hanya cek aplikasi.
                    idempotencyKey: 'servis:'.$tiket->id,
                );

                // Komisi reseller: jika servis milik reseller, hitung komisi
                if ($tiket->pelanggan?->is_reseller) {
                    $komisiService = app(KomisiService::class);
                    $komisiService->hitungKomisiDariItems(
                        $tiket->pelanggan,
                        [
                            [
                                'kategori' => 'Servis',
                                'subtotal' => $jasaServis,
                                'jumlah' => 1,
                            ],
                        ],
                        [
                            'jumlah_transaksi' => $totalTagihan,
                            'keterangan' => 'Komisi dari servis '.$tiket->no_tiket,
                        ]
                    );
                }

                // [F3-8c] Komisi multi-aktor trigger tiket_servis (teknisi via
                // engine baru; reseller servis tetap alur lama di atas — idempotent)
                app(KomisiService::class)->hitungKomisiMultiAktor('tiket_servis', ['tiket_servis_id' => $tiket->id]);
            }
        } catch (\Throwable $e) {
            // [B-10a / P0-4] Fail-closed: catat untuk investigasi, lalu lempar
            // lagi. `updateStatus()` membungkus status + side effect dalam satu
            // DB::transaction sehingga jurnal gagal = status TIDAK berubah.
            Log::error("Jurnal otomatis servis gagal: {$e->getMessage()}", [
                'tiket' => $tiket->no_tiket,
            ]);

            throw new \RuntimeException(
                "Jurnal servis {$tiket->no_tiket} gagal diposting ({$e->getMessage()}). Status tidak diubah.",
                0,
                $e
            );
        }
    }

    private function onDiambil(TiketServis $tiket): void
    {
        $tiket->update(['tanggal_diambil' => now()]);
    }

    private function logStatus(
        TiketServis $tiket,
        ?string $dari,
        string $ke,
        ?User $user,
        string $aksi,
        string $alasan
    ): void {
        ServisStatusLog::create([
            'tiket_servis_id' => $tiket->id,
            'status_dari' => $dari,
            'status_ke' => $ke,
            'user_id' => $user?->id,
            'aksi' => $aksi,
            'alasan' => $alasan,
        ]);

        // Audit: override status wajib tercatat (PRD §6)
        if ($aksi === 'override') {
            app(AuditService::class)->catat(
                'TiketServis', 'override', $tiket->id,
                "Override status {$dari} → {$ke} pada {$tiket->no_tiket}: {$alasan}",
                ['status' => $dari], ['status' => $ke]
            );
        }
    }
}
