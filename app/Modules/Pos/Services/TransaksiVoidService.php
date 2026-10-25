<?php

namespace App\Modules\Pos\Services;

use App\Models\User;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Services\PelangganService;
use App\Modules\Crm\Services\TierService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Wms\Models\NomorSeri;
use App\Modules\Wms\Services\NomorSeriService;
use App\Modules\Wms\Services\StokDeductionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TransaksiVoidService
{
    /**
     * Eksekusi void pada transaksi POS yang sudah 'selesai'.
     *
     * @param  Transaksi  $transaksi  Model transaksi yang akan di-void
     * @param  User  $authorizerUser  User yang mengotorisasi (supervisor/admin)
     * @param  string  $alasan  Alasan void / pembatalan transaksi
     * @return Transaksi Model transaksi setelah di-void
     *
     * @throws \Exception Jika validasi gagal
     */
    public function voidTransaksi(Transaksi $transaksi, User $authorizerUser, string $alasan): Transaksi
    {
        if ($transaksi->status !== 'selesai') {
            throw new \Exception("Transaksi tidak dapat di-void karena status saat ini adalah '{$transaksi->status}' (hanya transaksi 'selesai' yang dapat di-void).");
        }

        if (empty(trim($alasan))) {
            throw new \Exception('Alasan pembatalan (void) wajib diisi.');
        }

        return DB::transaction(function () use ($transaksi, $authorizerUser, $alasan) {
            $cabangId = (int) $transaksi->cabang_id;
            $gudangId = (int) ($transaksi->gudang_id ?? 1);
            $stokService = app(StokDeductionService::class);
            $nomorSeriService = app(NomorSeriService::class);

            // 1. Reversal Stok Gudang & Reversal Nomor Seri (SN)
            $transaksi->loadMissing('items.produk');
            foreach ($transaksi->items as $item) {
                $produkId = (int) $item->produk_id;
                $skuVariantId = $item->sku_variant_id ? (int) $item->sku_variant_id : null;
                $qty = (int) $item->jumlah;
                $konversi = max(0.0001, (float) ($item->konversi ?? 1));
                $qtyDasar = (int) round($qty * $konversi);

                if ($qtyDasar > 0) {
                    $stokService->kembalikan(
                        $produkId,
                        $skuVariantId,
                        $gudangId,
                        $qtyDasar,
                        'void_pos',
                        Transaksi::class,
                        $transaksi->id,
                        $authorizerUser->id,
                        "Void transaksi {$transaksi->no_transaksi}: {$alasan}"
                    );
                }

                // Cek nomor seri yang diklaim untuk item transaksi ini
                $claimedSns = NomorSeri::where('transaksi_item_id', $item->id)->pluck('nomor_seri')->all();
                if (! empty($claimedSns)) {
                    $nomorSeriService->returPenjualan($claimedSns, $produkId, $cabangId);
                }
            }

            // 2. Reversal Jurnal Akuntansi (Double-Entry Reversal)
            $jurnalLama = JurnalAkuntansi::where('referensi_tipe', Transaksi::class)
                ->where('referensi_id', $transaksi->id)
                ->get();

            if ($jurnalLama->isNotEmpty()) {
                $jurnalService = app(JurnalService::class);
                $noJurnalVoid = $jurnalService->generateNoJurnal('pos', $cabangId);

                // Buat entri pembalik: apa yang tadinya debit jadi kredit, yang kredit jadi debit
                $lines = [];
                foreach ($jurnalLama as $j) {
                    $akunKode = $j->akun?->kode;
                    if (! $akunKode) {
                        continue;
                    }

                    $debitLama = (float) $j->debit;
                    $kreditLama = (float) $j->kredit;

                    // Balikkan
                    $lines[] = [
                        'akun_kode' => $akunKode,
                        'debit' => $kreditLama,
                        'kredit' => $debitLama,
                    ];
                }

                if (! empty($lines)) {
                    $jurnalService->post(
                        $noJurnalVoid,
                        now(),
                        'pos',
                        $lines,
                        "Pembalik Jurnal Void POS {$transaksi->no_transaksi} — {$alasan}",
                        $cabangId,
                        $authorizerUser->id,
                        Transaksi::class,
                        $transaksi->id
                    );
                }
            }

            // 3. Batalkan Piutang Pelanggan (jika transaksi kasbon / piutang)
            $piutang = Piutang::where('transaksi_id', $transaksi->id)->first();
            if ($piutang) {
                $piutang->update([
                    'status' => 'lunas', // Ditutup karena transaksi di-void
                    'keterangan' => trim(($piutang->keterangan ?? '')." [Dibatalkan / Void oleh {$authorizerUser->name}: {$alasan}]"),
                ]);
            }

            // 4. Batalkan Komisi Terkait (proteksi komisi yang sudah cair)
            $komisiList = Komisi::where('transaksi_id', $transaksi->id)->get();
            foreach ($komisiList as $komisi) {
                if ($komisi->status === Komisi::STATUS_CAIR) {
                    throw new \DomainException(
                        "Transaksi {$transaksi->no_transaksi} tidak dapat dibatalkan (void) karena komisi sebesar Rp ".
                        number_format((float) $komisi->nominal_komisi, 0, ',', '.').
                        " ({$komisi->no_komisi}) sudah dicairkan. Harap batalkan administrasi pencairan komisi terlebih dahulu."
                    );
                }

                // Jika sudah disetujui dan ada entri utang belum lunas, batalkan utangnya
                if ($komisi->status === Komisi::STATUS_DISETUJUI) {
                    Utang::where('referensi_tipe', 'komisi')
                        ->where('referensi_id', $komisi->id)
                        ->where('status', 'belum_lunas')
                        ->update([
                            'status' => 'batal',
                            'keterangan' => trim(($komisi->keterangan ?? '')." [Batal void transaksi {$transaksi->no_transaksi}]"),
                        ]);
                }

                $komisi->update([
                    'status' => Komisi::STATUS_DITOLAK,
                    'keterangan' => trim(($komisi->keterangan ?? '')." [Dibatalkan karena transaksi {$transaksi->no_transaksi} di-void: {$alasan}]"),
                ]);
            }

            // 5. Kembalikan Poin yang Ditukar & Batalkan Akumulasi Belanja CRM Pelanggan
            if ($transaksi->pelanggan_id) {
                $pelanggan = Pelanggan::find($transaksi->pelanggan_id);
                if ($pelanggan) {
                    $pelangganService = app(PelangganService::class);
                    $poinDitukar = (int) ($transaksi->poin_ditukar ?? 0);
                    if ($poinDitukar > 0) {
                        $pelangganService->kembalikanPoin(
                            $pelanggan,
                            $poinDitukar,
                            $transaksi->id,
                            "Pengembalian {$poinDitukar} poin karena transaksi {$transaksi->no_transaksi} di-void: {$alasan}"
                        );
                    }

                    $totalAkhir = (float) $transaksi->total_akhir;
                    $pelanggan->decrement('total_belanja_12bulan', min((float) $pelanggan->total_belanja_12bulan, $totalAkhir));

                    $tierService = app(TierService::class);
                    $poinEarned = $tierService->hitungPoin($totalAkhir, $pelanggan->tierMembership);
                    if ($poinEarned > 0) {
                        $pelangganService->tarikPoinBatal(
                            $pelanggan,
                            $poinEarned,
                            $transaksi->id,
                            "Pembatalan {$poinEarned} poin belanja transaksi {$transaksi->no_transaksi} di-void: {$alasan}"
                        );
                    }

                    $tierService->recalcSatu($pelanggan);
                }
            }

            // 6. Update Status Transaksi menjadi 'void'
            $transaksi->update([
                'status' => 'void',
                'void_by_id' => $authorizerUser->id,
                'void_at' => now(),
                'void_alasan' => $alasan,
                'catatan' => trim(($transaksi->catatan ?? '').' [VOID pada '.now()->format('d/m/Y H:i')." oleh {$authorizerUser->name}: {$alasan}]"),
            ]);

            return $transaksi;
        });
    }

    /**
     * Verifikasi otorisasi kredensial supervisor / admin untuk melakukan void.
     *
     * @param  string  $usernameOrEmail  Username / Email supervisor
     * @param  string  $password  Password supervisor
     * @return User Supervisor yang valid
     *
     * @throws \Exception Jika kredensial salah atau tidak punya otorisasi
     */
    public function verifikasiSupervisor(string $usernameOrEmail, string $password): User
    {
        $user = User::where('email', $usernameOrEmail)
            ->orWhere('name', $usernameOrEmail)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new \Exception('Kredensial supervisor salah atau tidak ditemukan.');
        }

        // Cek permission / role supervisor: super-admin, owner, admin-toko, finance, atau permission pos.transaksi
        $allowedRoles = ['super-admin', 'owner', 'admin-toko', 'finance'];
        $hasRole = $user->hasRole($allowedRoles);
        $hasPermission = $user->can('pos.transaksi') || $user->can('lihat-audit-log');

        if (! $hasRole && ! $hasPermission) {
            throw new \Exception("User '{$user->name}' tidak memiliki hak akses supervisor/admin untuk mengotorisasi void transaksi.");
        }

        return $user;
    }
}
