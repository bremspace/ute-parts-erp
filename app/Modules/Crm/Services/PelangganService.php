<?php

namespace App\Modules\Crm\Services;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\PelangganPoinLog;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * [T-36] Single source of truth untuk pembuatan pelanggan (CRM-06, POS quick-add, Servis).
 * Aturan validasi + default tier terendah + is_reseller=false dipusatkan di sini agar
 * semua entry point berperilaku identik.
 *
 * [T-44] resolveHarga() — SATU sumber kebenaran harga jual produk per pelanggan.
 * Semua jalur harga (POS API, POS Livewire, Marketplace, PRICING-01) mengarah ke sini
 * (PricingService mendelegasikan ke method ini).
 *
 * Prioritas resolusi (tidak mengubah harga existing):
 *  1. HargaTier baru eksak  : (produk, sku_variant?, tier pelanggan, tipe_konsumen) → nominal_tetap | harga_jual*(1-persen/100)
 *  2. Legacy reseller       : harga_tier.is_reseller=1 → `harga` (backward-compatible)
 *  3. Legacy tier override  : harga_tier.tier_membership_id = tier pelanggan → `harga`
 *  4. Diskon tier CRM       : tierMembership.diskon_persen → harga_jual*(1-diskon/100)
 *  5. HargaTier tipe-default: (produk, tier NULL, tipe_konsumen) → nominal_tetap | persen_diskon
 *  6. Fallback              : harga_jual_retail (variant lebih dulu)
 */
class PelangganService
{
    public function rules(bool $isUpdate = false, ?int $pelangganId = null): array
    {
        $teleponRule = $isUpdate
            ? 'sometimes|required|string|max:20|unique:pelanggan,telepon,'.$pelangganId
            : 'required|string|max:20|unique:pelanggan,telepon';

        $emailRule = $isUpdate
            ? 'nullable|email|unique:pelanggan,email,'.$pelangganId
            : 'nullable|email|unique:pelanggan,email';

        $kodeAgenRule = $isUpdate
            ? 'sometimes|nullable|string|max:40|unique:pelanggan,kode_agen,'.$pelangganId
            : 'sometimes|nullable|string|max:40|unique:pelanggan,kode_agen';

        return [
            'nama' => $isUpdate ? 'sometimes|required|string|max:255' : 'required|string|max:255',
            'telepon' => $teleponRule,
            'email' => $emailRule,
            'alamat' => 'nullable|string',
            'tanggal_lahir' => 'nullable|date',
            'is_reseller' => 'sometimes|boolean',
            'tipe_konsumen' => 'sometimes|nullable|in:retail,reseller,agen',
            'tier_membership_id' => 'sometimes|nullable|exists:tier_memberships,id',
            'kode_agen' => $kodeAgenRule,
            'referral_kode' => 'sometimes|nullable|string|max:40',
        ];
    }

    public function generateUniqueKodeAgen(): string
    {
        do {
            $kode = 'AGN-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        } while (Pelanggan::where('kode_agen', $kode)->exists());

        return $kode;
    }

    public function create(array $data): Pelanggan
    {
        $validated = Validator::make($data, $this->rules())->validate();

        $tierTerendah = TierMembership::where('is_active', true)
            ->orderBy('min_belanja_12bulan')
            ->first();

        $isReseller = (bool) ($validated['is_reseller'] ?? false);
        $tipeKonsumen = $validated['tipe_konsumen'] ?? ($isReseller ? 'reseller' : 'retail');
        if ($tipeKonsumen === 'agen' || $tipeKonsumen === 'reseller') {
            $isReseller = true;
        }

        $kodeAgen = ! empty($validated['kode_agen']) ? strtoupper(trim($validated['kode_agen'])) : null;
        if ($tipeKonsumen === 'agen' && empty($kodeAgen)) {
            $kodeAgen = $this->generateUniqueKodeAgen();
        }

        $referralKode = ! empty($validated['referral_kode']) ? strtoupper(trim($validated['referral_kode'])) : null;

        $pelanggan = Pelanggan::create([
            'nama' => $validated['nama'],
            'telepon' => $validated['telepon'],
            'email' => $validated['email'] ?? null,
            'alamat' => $validated['alamat'] ?? null,
            'tanggal_lahir' => $validated['tanggal_lahir'] ?? null,
            'tier_membership_id' => $validated['tier_membership_id'] ?? $tierTerendah?->id,
            'is_reseller' => $isReseller,
            'tipe_konsumen' => $tipeKonsumen,
            'kode_agen' => $kodeAgen,
            'referral_kode' => $referralKode,
            'total_belanja_12bulan' => 0,
            'poin_loyalty' => 0,
        ]);

        return $pelanggan->load('tierMembership');
    }

    public function update(Pelanggan $pelanggan, array $data): Pelanggan
    {
        $validated = Validator::make($data, $this->rules(true, $pelanggan->id))->validate();

        $updateData = [];
        if (array_key_exists('nama', $validated)) {
            $updateData['nama'] = $validated['nama'];
        }
        if (array_key_exists('telepon', $validated)) {
            $updateData['telepon'] = $validated['telepon'];
        }
        if (array_key_exists('email', $validated)) {
            $updateData['email'] = $validated['email'] ?: null;
        }
        if (array_key_exists('alamat', $validated)) {
            $updateData['alamat'] = $validated['alamat'] ?: null;
        }
        if (array_key_exists('tanggal_lahir', $validated)) {
            $updateData['tanggal_lahir'] = $validated['tanggal_lahir'] ?: null;
        }
        if (array_key_exists('tier_membership_id', $validated)) {
            $updateData['tier_membership_id'] = $validated['tier_membership_id'] ?: null;
        }
        if (array_key_exists('kode_agen', $validated)) {
            $updateData['kode_agen'] = $validated['kode_agen'] ? strtoupper(trim($validated['kode_agen'])) : null;
        }
        if (array_key_exists('referral_kode', $validated)) {
            $updateData['referral_kode'] = $validated['referral_kode'] ? strtoupper(trim($validated['referral_kode'])) : null;
        }

        if (array_key_exists('tipe_konsumen', $validated)) {
            $tipe = $validated['tipe_konsumen'] ?: 'retail';
            $updateData['tipe_konsumen'] = $tipe;
            $updateData['is_reseller'] = in_array($tipe, ['reseller', 'agen'], true);

            if ($tipe === 'agen') {
                if (empty($pelanggan->kode_agen) && empty($updateData['kode_agen'])) {
                    $updateData['kode_agen'] = $this->generateUniqueKodeAgen();
                }
            } else {
                // Bila diturunkan ke retail/reseller (bukan agen), kosongkan kode agen bila tidak spesifik diinput
                if (! array_key_exists('kode_agen', $validated) || empty($validated['kode_agen'])) {
                    $updateData['kode_agen'] = null;
                }
            }
        } elseif (array_key_exists('is_reseller', $validated)) {
            $isReseller = (bool) $validated['is_reseller'];
            $updateData['is_reseller'] = $isReseller;
            $updateData['tipe_konsumen'] = $isReseller ? 'reseller' : 'retail';

            if (! $isReseller && (! array_key_exists('kode_agen', $validated) || empty($validated['kode_agen']))) {
                $updateData['kode_agen'] = null;
            }
        }

        if (($updateData['tipe_konsumen'] ?? $pelanggan->tipe_konsumen) === 'agen') {
            $updateData['is_reseller'] = true;
            if (empty($pelanggan->kode_agen) && empty($updateData['kode_agen'])) {
                $updateData['kode_agen'] = $this->generateUniqueKodeAgen();
            }
        }

        $pelanggan->update($updateData);

        return $pelanggan->fresh('tierMembership');
    }

    public function delete(Pelanggan $pelanggan): bool
    {
        $hasTransaksi = Transaksi::where('pelanggan_id', $pelanggan->id)->exists();
        $hasServis = TiketServis::where('pelanggan_id', $pelanggan->id)->exists();
        $hasKomisi = Komisi::where('pelanggan_id', $pelanggan->id)->exists();

        if ($hasTransaksi || $hasServis || $hasKomisi) {
            throw new \DomainException(
                "Pelanggan '{$pelanggan->nama}' memiliki riwayat transaksi penjualan, tiket servis, atau komisi sehingga tidak dapat dihapus demi integritas pembukuan dan audit."
            );
        }

        return (bool) $pelanggan->delete();
    }

    /**
     * Tambah akumulasi belanja & poin loyalty pelanggan serta cek rekalkulasi tier.
     * Single source of truth untuk POS kasir, pembayaran marketplace online, dan pelunasan servis.
     */
    public function tambahBelanjaDanPoin(Pelanggan|int $pelanggan, float $nominal, ?int $transaksiId = null): void
    {
        if ($nominal <= 0) {
            return;
        }

        $pelangganId = $pelanggan instanceof Pelanggan ? $pelanggan->id : $pelanggan;

        DB::transaction(function () use ($pelangganId, $nominal, $transaksiId) {
            $model = Pelanggan::where('id', $pelangganId)->lockForUpdate()->first();
            if (! $model) {
                return;
            }

            $model->increment('total_belanja_12bulan', $nominal);

            $tierService = app(TierService::class);
            $poin = $tierService->hitungPoin($nominal, $model->tierMembership);
            if ($poin > 0) {
                $saldoSebelum = (int) $model->poin_loyalty;
                $saldoSesudah = $saldoSebelum + $poin;
                $model->update(['poin_loyalty' => $saldoSesudah]);

                PelangganPoinLog::create([
                    'pelanggan_id' => $model->id,
                    'transaksi_id' => $transaksiId,
                    'tipe' => PelangganPoinLog::TIPE_EARNED,
                    'poin' => $poin,
                    'saldo_sebelum' => $saldoSebelum,
                    'saldo_sesudah' => $saldoSesudah,
                    'keterangan' => 'Perolehan poin dari transaksi'.($transaksiId ? " #{$transaksiId}" : ''),
                    'created_by' => auth()->id(),
                ]);
            }

            $tierService->recalcSatu($model);
        });
    }

    /**
     * Potong poin loyalty pelanggan untuk penukaran diskon (atomic & recorded).
     */
    public function tukarPoin(Pelanggan|int $pelanggan, int $poinDitukar, ?int $transaksiId = null, string $keterangan = ''): bool
    {
        if ($poinDitukar <= 0) {
            return true;
        }

        $pelangganId = $pelanggan instanceof Pelanggan ? $pelanggan->id : $pelanggan;

        return DB::transaction(function () use ($pelangganId, $poinDitukar, $transaksiId, $keterangan) {
            $model = Pelanggan::where('id', $pelangganId)->lockForUpdate()->first();
            if (! $model) {
                throw new \DomainException('Pelanggan tidak ditemukan untuk penukaran poin.');
            }

            $saldoSebelum = (int) $model->poin_loyalty;
            if ($saldoSebelum < $poinDitukar) {
                throw new \DomainException("Saldo poin pelanggan tidak mencukupi (Tersedia: {$saldoSebelum}, Diminta: {$poinDitukar})");
            }

            $saldoSesudah = $saldoSebelum - $poinDitukar;
            $model->update(['poin_loyalty' => $saldoSesudah]);

            PelangganPoinLog::create([
                'pelanggan_id' => $model->id,
                'transaksi_id' => $transaksiId,
                'tipe' => PelangganPoinLog::TIPE_REDEEMED,
                'poin' => -$poinDitukar,
                'saldo_sebelum' => $saldoSebelum,
                'saldo_sesudah' => $saldoSesudah,
                'keterangan' => $keterangan ?: 'Penukaran poin pada transaksi'.($transaksiId ? " #{$transaksiId}" : ''),
                'created_by' => auth()->id(),
            ]);

            return true;
        });
    }

    /**
     * Kembalikan poin loyalty yang telah ditukar (misalnya saat transaksi di-void/batal).
     */
    public function kembalikanPoin(Pelanggan|int $pelanggan, int $poinDikembalikan, ?int $transaksiId = null, string $keterangan = ''): void
    {
        if ($poinDikembalikan <= 0) {
            return;
        }

        $pelangganId = $pelanggan instanceof Pelanggan ? $pelanggan->id : $pelanggan;

        DB::transaction(function () use ($pelangganId, $poinDikembalikan, $transaksiId, $keterangan) {
            $model = Pelanggan::where('id', $pelangganId)->lockForUpdate()->first();
            if (! $model) {
                return;
            }

            $saldoSebelum = (int) $model->poin_loyalty;
            $saldoSesudah = $saldoSebelum + $poinDikembalikan;
            $model->update(['poin_loyalty' => $saldoSesudah]);

            PelangganPoinLog::create([
                'pelanggan_id' => $model->id,
                'transaksi_id' => $transaksiId,
                'tipe' => PelangganPoinLog::TIPE_REFUND,
                'poin' => $poinDikembalikan,
                'saldo_sebelum' => $saldoSebelum,
                'saldo_sesudah' => $saldoSesudah,
                'keterangan' => $keterangan ?: 'Pengembalian poin (transaksi void/batal)'.($transaksiId ? " #{$transaksiId}" : ''),
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Tarik kembali poin perolehan belanja yang dibatalkan karena transaksi di-void.
     */
    public function tarikPoinBatal(Pelanggan|int $pelanggan, int $poinBatal, ?int $transaksiId = null, string $keterangan = ''): void
    {
        if ($poinBatal <= 0) {
            return;
        }

        $pelangganId = $pelanggan instanceof Pelanggan ? $pelanggan->id : $pelanggan;

        DB::transaction(function () use ($pelangganId, $poinBatal, $transaksiId, $keterangan) {
            $model = Pelanggan::where('id', $pelangganId)->lockForUpdate()->first();
            if (! $model) {
                return;
            }

            $saldoSebelum = (int) $model->poin_loyalty;
            $potong = min($saldoSebelum, $poinBatal);
            if ($potong <= 0) {
                return;
            }

            $saldoSesudah = $saldoSebelum - $potong;
            $model->update(['poin_loyalty' => $saldoSesudah]);

            PelangganPoinLog::create([
                'pelanggan_id' => $model->id,
                'transaksi_id' => $transaksiId,
                'tipe' => PelangganPoinLog::TIPE_PENYESUAIAN,
                'poin' => -$potong,
                'saldo_sebelum' => $saldoSebelum,
                'saldo_sesudah' => $saldoSesudah,
                'keterangan' => $keterangan ?: 'Pembatalan poin perolehan belanja (transaksi void)'.($transaksiId ? " #{$transaksiId}" : ''),
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Tipe konsumen efektif pelanggan (retail|reseller|agen).
     * Kolom baru `tipe_konsumen` (default retail); fallback legacy is_reseller.
     */
    public function tipeKonsumen(?Pelanggan $pelanggan): string
    {
        if (! $pelanggan) {
            return 'retail';
        }
        $tipe = $pelanggan->tipe_konsumen;
        if (! $tipe) {
            $tipe = $pelanggan->is_reseller ? 'reseller' : 'retail';
        }

        return in_array($tipe, ['retail', 'reseller', 'agen'], true) ? $tipe : 'retail';
    }

    /**
     * Mode reward mitra (diskon_langsung | komisi_akumulasi).
     */
    public function modeRewardMitra(?Pelanggan $pelanggan): string
    {
        if (! $pelanggan) {
            return 'diskon_langsung';
        }

        if (! empty($pelanggan->mode_reward_mitra)) {
            return $pelanggan->mode_reward_mitra;
        }

        $config = app(KonfigurasiService::class);

        return $config->mitraRewardMode();
    }

    /**
     * Nilai tukar 1 poin loyalitas menjadi rupiah untuk pelanggan.
     * Mengutamakan rasio khusus pada tier membership pelanggan (bila dikonfigurasi),
     * atau fallback ke konfigurasi global sistem (default Rp 100).
     */
    public function nilaiTukarPoinPelanggan(?Pelanggan $pelanggan): float
    {
        if ($pelanggan && $pelanggan->tierMembership && (float) $pelanggan->tierMembership->poin_redeem_rupiah > 0) {
            return (float) $pelanggan->tierMembership->poin_redeem_rupiah;
        }

        return app(KonfigurasiService::class)->poinRedeemRupiah();
    }

    /**
     * Resolusi harga final per produk + pelanggan (SATU SUMBER KEBENARAN).
     *
     * @return array{harga: float, harga_dasar: float, diskon_nominal: float, potensi_komisi: float, mode_reward: string, alasan: string, tier: string|null}
     */
    public function resolveHarga(Produk $produk, ?Pelanggan $pelanggan = null, ?SkuVariant $variant = null, ?string $overrideModeReward = null): array
    {
        $hargaDasar = $variant && $variant->harga_jual_retail !== null
            ? (float) $variant->harga_jual_retail
            : (float) $produk->harga_jual_retail;

        if (! $pelanggan) {
            return [
                'harga' => $hargaDasar,
                'harga_dasar' => $hargaDasar,
                'diskon_nominal' => 0.0,
                'potensi_komisi' => 0.0,
                'mode_reward' => 'diskon_langsung',
                'alasan' => 'Harga Retail Standar',
                'tier' => null,
            ];
        }

        $tipe = $this->tipeKonsumen($pelanggan);
        $modeReward = ($tipe === 'reseller' || $tipe === 'agen')
            ? ($overrideModeReward ?: $this->modeRewardMitra($pelanggan))
            : 'diskon_langsung';

        // [B-FIX] `Model::getConnectionResolver()` adalah static GLOBAL: hanya
        // di-set sekali saat container Laravel boot dan tidak pernah
        // dikembalikan null. Jadi "resolver !== null" TIDAK berarti "DB siap" —
        // di konteks tanpa container (mis. unit test murni yang memanggil
        // PricingService langsung) resolver masih tertinggal tapi container-nya
        // sudah mati, sehingga query DB berikutnya lempar
        // "Target class [config] does not exist". Cek container juga supaya
        // price resolution tetap pure/kalkulatif saat DB tidak benar-benar bisa
        // dipakai (harga retail + diskon tier sudah cukup untuk fallback).
        $hasDb = Model::getConnectionResolver() !== null
            && Container::getInstance()->bound('config');
        $rows = $this->rowsHargaTier($produk, $variant, $hasDb);

        // Jika mode komisi_akumulasi aktif untuk reseller/agen:
        // Hitung harga tier mitra secara shadow untuk menentukan komisi, tetapi harga tagihan tetap retail.
        if ($modeReward === 'komisi_akumulasi' && ($tipe === 'reseller' || $tipe === 'agen')) {
            $hargaTierMitra = null;
            if ($pelanggan->tier_membership_id) {
                $eksak = $this->filterRows($rows, tierId: $pelanggan->tier_membership_id, tipe: $tipe)
                    ->first(fn ($r) => $r->nominal_tetap !== null || $r->persen_diskon !== null);
                if ($eksak) {
                    $hasilMitra = $this->hitungDariTierBaru($eksak, $hargaDasar, '', null);
                    $hargaTierMitra = $hasilMitra['harga'];
                }
            }

            if ($hargaTierMitra === null) {
                $tipeDefault = $this->filterRows($rows, tierId: null, tipe: $tipe)
                    ->first(fn ($r) => ($r->nominal_tetap !== null || $r->persen_diskon !== null) && $r->is_reseller !== true);
                if ($tipeDefault) {
                    $hasilMitra = $this->hitungDariTierBaru($tipeDefault, $hargaDasar, '', null);
                    $hargaTierMitra = $hasilMitra['harga'];
                }
            }

            if ($hargaTierMitra === null && $pelanggan->is_reseller) {
                $legacyReseller = $rows->first(fn ($r) => $r->is_reseller === true && $r->tier_membership_id === null);
                if ($legacyReseller && (float) $legacyReseller->harga > 0) {
                    $hargaTierMitra = (float) $legacyReseller->harga;
                }
            }

            $potensiKomisi = $hargaTierMitra !== null ? max(0.0, round($hargaDasar - $hargaTierMitra, 2)) : 0.0;

            return [
                'harga' => $hargaDasar,
                'harga_dasar' => $hargaDasar,
                'diskon_nominal' => 0.0,
                'potensi_komisi' => $potensiKomisi,
                'mode_reward' => 'komisi_akumulasi',
                'alasan' => 'Harga Retail (Komisi Akumulasi '.($tipe === 'reseller' ? 'Reseller' : 'Agen').')',
                'tier' => $tipe === 'reseller' ? 'Reseller' : 'Agen',
            ];
        }

        // 1. HargaTier baru — eksak (produk [+ varian], tier pelanggan, tipe_konsumen)
        if ($pelanggan->tier_membership_id) {
            $eksak = $this->filterRows($rows, tierId: $pelanggan->tier_membership_id, tipe: $tipe)
                ->first(fn ($r) => $r->nominal_tetap !== null || $r->persen_diskon !== null);
            if ($eksak) {
                return $this->hitungDariTierBaru($eksak, $hargaDasar, 'Harga Khusus Tier ', $pelanggan->tierMembership?->nama);
            }
        }

        // 2. Legacy reseller (harga_tier.is_reseller=1 → `harga`), hanya bila pelanggan reseller
        if ($pelanggan->is_reseller) {
            $reseller = $rows->first(fn ($r) => $r->is_reseller === true && $r->tier_membership_id === null);
            if ($reseller && $reseller->harga !== null) {
                $final = (float) $reseller->harga;
                // Baris legacy yang sudah di-seed nominal_tetap (placeholder) tidak boleh
                // mengalahkan harga legacy — `harga` adalah sumber untuk jalur legacy.
                if ($final > 0) {
                    return [
                        'harga' => $final,
                        'harga_dasar' => $hargaDasar,
                        'diskon_nominal' => max(0, $hargaDasar - $final),
                        'alasan' => 'Harga Khusus Reseller',
                        'tier' => 'Reseller',
                    ];
                }
            }
        }

        // 3. Legacy tier override (harga_tier.tier_membership_id = tier pelanggan → `harga`)
        if ($pelanggan->tier_membership_id) {
            $tierOverride = $this->filterRows($rows, tierId: $pelanggan->tier_membership_id)
                ->first(fn ($r) => $r->harga !== null && $r->nominal_tetap === null && $r->persen_diskon === null);
            if ($tierOverride) {
                $final = (float) $tierOverride->harga;

                return [
                    'harga' => $final,
                    'harga_dasar' => $hargaDasar,
                    'diskon_nominal' => max(0, $hargaDasar - $final),
                    'alasan' => 'Harga Khusus Tier '.($pelanggan->tierMembership?->nama ?? ''),
                    'tier' => $pelanggan->tierMembership?->nama,
                ];
            }
        }

        // 4. Diskon tier CRM (diskon_persen) — perilaku existing dipertahankan
        $tier = $pelanggan->relationLoaded('tierMembership')
            ? $pelanggan->tierMembership
            : ($hasDb ? $pelanggan->tierMembership : null);
        if ($tier && (float) $tier->diskon_persen > 0) {
            $diskonNominal = round(($hargaDasar * (float) $tier->diskon_persen) / 100, 2);
            $finalHarga = max(0, $hargaDasar - $diskonNominal);

            return [
                'harga' => $finalHarga,
                'harga_dasar' => $hargaDasar,
                'diskon_nominal' => $diskonNominal,
                'alasan' => "Diskon {$tier->diskon_persen}% Tier {$tier->nama}",
                'tier' => $tier->nama,
            ];
        }

        // 5. HargaTier tipe-default (produk, tier NULL, tipe_konsumen) — [T-44]
        $tipeDefault = $this->filterRows($rows, tierId: null, tipe: $tipe)
            ->first(fn ($r) => ($r->nominal_tetap !== null || $r->persen_diskon !== null)
                && $r->is_reseller !== true); // baris legacy reseller = bukan harga tipe-default
        if ($tipeDefault) {
            // Nominal placeholder hasil seed = harga_jual → sama dengan fallback; tetap dievaluasi
            // agar struktur harga per tipe_konsumen konsisten (harga tidak berubah vs perilaku lama).
            return $this->hitungDariTierBaru($tipeDefault, $hargaDasar, 'Harga Khusus ', null);
        }

        // 6. Fallback retail
        return [
            'harga' => $hargaDasar,
            'harga_dasar' => $hargaDasar,
            'diskon_nominal' => 0.0,
            'potensi_komisi' => 0.0,
            'mode_reward' => 'diskon_langsung',
            'alasan' => 'Harga Retail Standar',
            'tier' => null,
        ];
    }

    /**
     * Kumpulkan baris HargaTier produk (via relasi bila sudah di-load, else DB bila tersedia).
     */
    protected function rowsHargaTier(Produk $produk, ?SkuVariant $variant, bool $hasDb): Collection
    {
        $rows = collect();
        if ($produk->relationLoaded('hargaTier')) {
            $rows = $produk->hargaTier;
        } elseif ($hasDb && $produk->id) {
            $query = HargaTier::where('produk_id', $produk->id);
            if ($variant && $variant->id) {
                $query->where(function ($q) use ($variant) {
                    $q->whereNull('sku_variant_id')->orWhere('sku_variant_id', $variant->id);
                });
            }
            $rows = $query->get();
        }
        if ($variant && $variant->id && $rows->count()) {
            // Prioritaskan baris spesifik varian di atas baris produk-umum
            $spesifik = $rows->where('sku_variant_id', $variant->id)->values();
            $umum = $rows->whereNull('sku_variant_id')->values();

            return $spesifik->count() ? $spesifik->merge($umum) : $rows;
        }

        return $rows;
    }

    protected function filterRows(Collection $rows, ?int $tierId = null, ?string $tipe = null): Collection
    {
        return $rows->filter(function ($r) use ($tierId, $tipe) {
            // tier: tierId null → hanya baris tanpa tier (tipe-default); non-null → eksak
            $tierMatch = $tierId === null
                ? ($r->tier_membership_id === null)
                : ((int) $r->tier_membership_id === $tierId);
            $tipeMatch = $tipe === null || ($r->tipe_konsumen ?: 'retail') === $tipe;

            return $tierMatch && $tipeMatch;
        });
    }

    protected function hitungDariTierBaru(HargaTier $row, float $hargaDasar, string $alasanPrefix, ?string $tierNama): array
    {
        if ($row->nominal_tetap !== null) {
            $final = (float) $row->nominal_tetap;
            $alasan = $alasanPrefix.($tierNama ?? $this->tipeLabel($row->tipe_konsumen));
            $tier = $tierNama ?? ($row->tipe_konsumen === 'reseller' ? 'Reseller' : null);
        } else {
            $final = round($hargaDasar * (1 - (float) ($row->persen_diskon ?? 0) / 100), 2);
            $alasan = $alasanPrefix.($tierNama ?? $this->tipeLabel($row->tipe_konsumen));
            $tier = $tierNama ?? ($row->tipe_konsumen === 'reseller' ? 'Reseller' : null);
        }

        return [
            'harga' => max(0, $final),
            'harga_dasar' => $hargaDasar,
            'diskon_nominal' => max(0, $hargaDasar - max(0, $final)),
            'potensi_komisi' => 0.0,
            'mode_reward' => 'diskon_langsung',
            'alasan' => $alasan,
            'tier' => $tier,
        ];
    }

    protected function tipeLabel(?string $tipe): string
    {
        return match ($tipe) {
            'reseller' => 'Reseller',
            'agen' => 'Agen',
            default => 'Retail',
        };
    }
}
