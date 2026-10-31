<?php

namespace App\Modules\Rbac\Models;

use Illuminate\Support\Enumerable;
use Spatie\Activitylog\Models\Activity;

/**
 * [F1-4] Baris audit trail (tabel activity_log, package spatie/laravel-activitylog).
 * Dipilih lewat config('activitylog.activity_model').
 *
 * causerNama: "sistem" bila tidak ada user login (job queue / seed / webhook) —
 * bukan "null" supaya jelas di UI riwayat.
 */
class AktivitasLog extends Activity
{
    public function getCauserNamaAttribute(): string
    {
        return $this->causer?->name ?? 'sistem';
    }

    /** Label aksi Bahasa Indonesia (created → Dibuat, dst). */
    public function getAksiLabelAttribute(): string
    {
        return match ($this->event) {
            'created' => 'Dibuat',
            'updated' => 'Diperbarui',
            'deleted' => 'Dihapus',
            'restored' => 'Dipulihkan',
            'penjualan' => 'Penjualan Kasir',
            'void' => 'Void / Batal',
            'retur_penjualan' => 'Retur Konsumen',
            'po', 'po_dibuat' => 'Pesanan PO',
            'grn', 'grn_diterima' => 'Penerimaan GRN',
            'retur_pembelian' => 'Retur Supplier',
            'transfer_stok' => 'Transfer Stok',
            'stok_opname' => 'Stok Opname',
            'servis' => 'Tiket Servis',
            default => (string) ($this->event ?: 'Aktivitas'),
        };
    }

    /** Normalisasi properties menjadi array murni (baik dari Collection maupun JSON array) */
    public function getNormalizedPropertiesAttribute(): array
    {
        $props = $this->properties;
        if ($props instanceof Enumerable) {
            return $props->all();
        }
        if (is_array($props)) {
            return $props;
        }
        if (is_string($props)) {
            $decoded = json_decode($props, true);

            return is_array($decoded) ? $decoded : [];
        }

        return (array) $props;
    }

    /** Cek apakah log ini berformat Transaction-Centric */
    public function getIsTransaksiAttribute(): bool
    {
        $props = $this->normalized_properties;
        if (! empty($props['tipe_transaksi']) || ! empty($props['items']) || ! empty($props['items_retur'])) {
            return true;
        }

        return in_array($this->event, ['penjualan', 'void', 'retur_penjualan', 'po', 'grn', 'retur_pembelian', 'transfer_stok', 'stok_opname', 'servis'], true);
    }

    /** Label tipe transaksi yang ramah awam */
    public function getTipeTransaksiLabelAttribute(): string
    {
        $props = $this->normalized_properties;
        $tipe = $props['tipe_transaksi'] ?? $this->event;

        return match ($tipe) {
            'penjualan' => 'Penjualan Kasir POS',
            'void' => 'Void Transaksi (Batal)',
            'retur_penjualan' => 'Retur Penjualan Konsumen',
            'po', 'po_dibuat' => 'Purchase Order (PO)',
            'grn', 'grn_diterima' => 'Penerimaan Barang (GRN)',
            'retur_pembelian' => 'Retur Barang ke Supplier',
            'transfer_stok' => 'Transfer Stok Antar Gudang',
            'stok_opname' => 'Penyesuaian Stok Opname',
            'servis' => 'Aktivitas Tiket Servis',
            default => $this->aksi_label,
        };
    }

    /** Ringkasan manusiawi dalam Bahasa Indonesia */
    public function getRingkasanAttribute(): string
    {
        $props = $this->normalized_properties;
        if (! empty($props['ringkasan'])) {
            return (string) $props['ringkasan'];
        }

        return (string) $this->description;
    }

    /** Nomor referensi dokumen jika ada */
    public function getNoReferensiAttribute(): ?string
    {
        $props = $this->normalized_properties;
        if (! empty($props['no_referensi'])) {
            return (string) $props['no_referensi'];
        }
        if (! empty($props['no_transaksi'])) {
            return (string) $props['no_transaksi'];
        }

        return null;
    }

    /** Daftar item / produk dalam transaksi */
    public function getItemsListAttribute(): array
    {
        $props = $this->normalized_properties;
        if (! empty($props['items']) && is_array($props['items'])) {
            return $props['items'];
        }
        if (! empty($props['items_retur']) && is_array($props['items_retur'])) {
            return $props['items_retur'];
        }

        return [];
    }

    /**
     * Scope Dual-Indexing: Temukan seluruh log di mana produk terkait
     * menjadi subjek langsung ATAU tercatat dalam metadata properties->produk_ids.
     */
    public function scopeUntukProduk($query, int $produkId)
    {
        return $query->where(function ($q) use ($produkId) {
            $q->where(function ($sub) use ($produkId) {
                $sub->where('subject_type', 'App\Modules\Wms\Models\Produk')
                    ->where('subject_id', $produkId);
            })->orWhereJsonContains('properties->produk_ids', $produkId);
        });
    }
}
