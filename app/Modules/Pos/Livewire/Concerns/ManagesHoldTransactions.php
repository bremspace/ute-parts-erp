<?php

namespace App\Modules\Pos\Livewire\Concerns;

use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Produk;
use Illuminate\Support\Facades\DB;

/**
 * Trait ManagesHoldTransactions
 *
 * Menangani fungsi Park/Tahan transaksi di kasir POS (F6),
 * resume transaksi tertahan, dan pembatalan transaksi tertahan.
 */
trait ManagesHoldTransactions
{
    /** Simpan keranjang sebagai transaksi status 'ditahan' (F6) — belum kurangi stok. */
    public function tahanTransaksi()
    {
        if (empty($this->cart)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Keranjang kosong, tidak ada yang ditahan']);

            return;
        }

        $cabangId = currentCabangId() ?? auth()->user()?->cabangs()->first()?->id ?? 1;

        // [B-02/P1-2] Simpan keranjang tertahan dgn gudang cabang aktif
        $this->validasiGudangCabang();

        try {
            DB::transaction(function () use ($cabangId) {
                $today = now()->format('Ymd');
                $count = Transaksi::whereDate('created_at', now()->toDateString())
                    ->where('cabang_id', $cabangId)->count() + 1;
                $no = sprintf('TRX-C%02d-%s-T%04d', $cabangId, $today, $count);

                Transaksi::create([
                    'no_transaksi' => $no,
                    'cabang_id' => $cabangId,
                    'kasir_id' => auth()->id() ?? 1,
                    'pelanggan_id' => $this->selectedCustomerId,
                    'gudang_id' => $this->selectedGudangId,
                    'sumber' => 'pos',
                    'subtotal' => $this->subtotal,
                    'diskon_persen' => $this->diskonPersen,
                    'diskon_nominal' => $this->diskonNominal,
                    'dpp' => $this->dpp,
                    'pajak_nominal' => $this->pajakNominal,
                    'ppn_nominal' => $this->pajakNominal,
                    'total_akhir' => $this->totalAkhir,
                    'metode_bayar' => 'ditahan',
                    'jumlah_bayar' => 0,
                    'kembalian' => 0,
                    'split_detail' => [
                        'cart' => array_values($this->cart),
                        'diskon_persen' => $this->diskonPersen,
                        'diskon_nominal' => $this->diskonNominal,
                        'poin_ditukar' => $this->poinDitukar,
                        'mode_reward_mitra' => $this->modeRewardMitra,
                        'catatan' => 'Ditahan (park) oleh '.(auth()->user()?->name ?? 'kasir'),
                    ],
                    'status' => 'ditahan',
                    'catatan' => 'Transaksi ditahan (park)',
                ]);
            });

            $this->clearCart();
            $this->showDitahanPanel = true; // [T-37] panel tertahan langsung terbuka setelah F6
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Transaksi ditahan — dapat dilanjutkan kasir lain di cabang ini']);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /** Daftar transaksi ditahan (scope cabang aktif — POS-05). */
    public function getDitahanListProperty()
    {
        $cabangId = (int) (currentCabangId() ?: auth()->user()?->cabangs()->first()?->id ?: Cabang::value('id'));

        return Transaksi::with('kasir')
            ->where('cabang_id', $cabangId)
            ->where('status', 'ditahan')
            ->latest()
            ->get();
    }

    /** Lanjutkan transaksi yang ditahan → isi ulang keranjang, status duplikat jadi draft biasa. */
    public function resumeDitahan(int $id)
    {
        $cabangId = (int) (currentCabangId() ?: auth()->user()?->cabangs()->first()?->id ?: Cabang::value('id'));
        $transaksi = Transaksi::where('id', $id)
            ->where('cabang_id', $cabangId)
            ->where('status', 'ditahan')
            ->firstOrFail();

        $detail = $transaksi->split_detail ?? [];
        $cartItems = $detail['cart'] ?? [];

        if (empty($cartItems)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Data keranjang ditahan tidak ditemukan']);

            return;
        }

        // Muat ulang keranjang ke state (tanpa duplikat — reset dulu)
        $this->clearCart();
        foreach ($cartItems as $item) {
            $pId = (int) ($item['produk_id'] ?? 0);
            $vId = $item['sku_variant_id'] ?? null;
            $satuanItem = $item['satuan'] ?? null;
            $produk = $pId ? Produk::with('skuVariants')->find($pId) : null;
            $variant = $vId ? SkuVariant::find($vId) : null;
            $satuanDasar = $produk?->satuan ?: 'pcs';
            $satuanSuffix = ($satuanItem && $satuanItem !== $satuanDasar) ? "-{$satuanItem}" : '';

            $key = ($vId ? "{$pId}-{$vId}" : "{$pId}-0").$satuanSuffix;
            $itemSku = $item['sku'] ?? null;
            if (empty($itemSku) && $produk) {
                $itemSku = $variant?->sku ?: ($produk->sku ?: ($produk->barcode ?: ($produk->kode_lama ?: 'PRD-'.str_pad((string) $pId, 6, '0', STR_PAD_LEFT))));
            }
            // [F2-3][P2-3] Compat keranjang lama — flag sn SELALU di-derive ulang
            // dari produk (server-side). Array + mempertahankan key kiri (payload
            // lama), jadi pakai array_merge agar 'sn' selalu nilai segar.
            $this->cart[$key] = array_merge($item, [
                'sku' => $itemSku,
                'sn' => $this->snProdukDariItem($item),
                'sn_list' => $item['sn_list'] ?? [],
            ]);
        }
        $this->diskonPersen = (float) ($detail['diskon_persen'] ?? 0);
        $this->diskonNominal = (float) ($detail['diskon_nominal'] ?? 0);
        $this->poinDitukar = (int) ($detail['poin_ditukar'] ?? 0);
        $this->modeRewardMitra = (string) ($detail['mode_reward_mitra'] ?? 'diskon_langsung');
        $this->selectedCustomerId = $transaksi->pelanggan_id;
        $this->resetComputedTotals();
        $this->hitungDiskonPartai();
        $this->resetComputedTotals();
        $this->recalcPajak(); // [F1-2] resume park → rehitung PPN dari diskon tersimpan

        // Tandai transaksi asli dipindahkan → status 'dibatalkan' (tidak dipakai lagi), tanpa jurnal
        $transaksi->update(['status' => 'dibatalkan', 'catatan' => 'Dilanjutkan (resume) oleh '.(auth()->user()?->name ?? 'kasir')]);

        $this->showDitahanPanel = false;

        $this->dispatch('alert', ['type' => 'success', 'message' => 'Transaksi ditahan dilanjutkan — silakan lanjut bayar']);
    }

    /** Alias untuk resumeDitahan agar kompatibel dengan template lama/baru. */
    public function resumeTransaksi(int $id): void
    {
        $this->resumeDitahan($id);
    }

    /** Hapus / batalkan transaksi yang ditahan. */
    public function hapusDitahan(int $id): void
    {
        $cabangId = (int) (currentCabangId() ?: auth()->user()?->cabangs()->first()?->id ?: Cabang::value('id'));
        $transaksi = Transaksi::where('id', $id)
            ->where('cabang_id', $cabangId)
            ->where('status', 'ditahan')
            ->first();

        if (! $transaksi) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Transaksi ditahan tidak ditemukan']);

            return;
        }

        $noTrx = $transaksi->no_transaksi;
        $transaksi->update([
            'status' => 'dibatalkan',
            'catatan' => 'Dibatalkan/dihapus dari kasir oleh '.(auth()->user()?->name ?? 'kasir'),
        ]);

        $this->dispatch('alert', ['type' => 'success', 'message' => "Transaksi ditahan {$noTrx} berhasil dihapus"]);
    }

    public function bukaDitahanPanel(): void
    {
        $this->showDitahanPanel = true;
    }

    public function tutupDitahanPanel(): void
    {
        $this->showDitahanPanel = false;
    }
}
