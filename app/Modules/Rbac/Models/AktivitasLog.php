<?php

namespace App\Modules\Rbac\Models;

use App\Modules\Rbac\Livewire\RiwayatAktivitas;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    /** Relasi ke Cabang */
    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }

    /** Nama Cabang asal aktivitas */
    public function getCabangNamaAttribute(): ?string
    {
        return $this->cabang?->nama;
    }

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
            'retur_penjualan', 'return_penjualan' => 'Retur Konsumen',
            'po', 'po_dibuat' => 'Pesanan PO',
            'grn', 'grn_diterima' => 'Penerimaan GRN',
            'retur_pembelian', 'return_pembelian' => 'Retur Supplier',
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

        return in_array($this->event, ['penjualan', 'void', 'retur_penjualan', 'return_penjualan', 'po', 'grn', 'retur_pembelian', 'return_pembelian', 'transfer_stok', 'stok_opname', 'servis'], true);
    }

    /** Label tipe transaksi yang ramah awam */
    public function getTipeTransaksiLabelAttribute(): string
    {
        $props = $this->normalized_properties;
        $tipe = $props['tipe_transaksi'] ?? $this->event;

        return match ($tipe) {
            'penjualan' => 'Penjualan Kasir POS',
            'void' => 'Void Transaksi (Batal)',
            'retur_penjualan', 'return_penjualan' => 'Retur Penjualan Konsumen',
            'po', 'po_dibuat' => 'Purchase Order (PO)',
            'grn', 'grn_diterima' => 'Penerimaan Barang (GRN)',
            'retur_pembelian', 'return_pembelian' => 'Retur Barang ke Supplier',
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
        if (! empty($props['attributes']['no_transaksi'])) {
            return (string) $props['attributes']['no_transaksi'];
        }
        if (! empty($props['attributes']['no_tiket'])) {
            return (string) $props['attributes']['no_tiket'];
        }
        if (! empty($props['attributes']['no_po'])) {
            return (string) $props['attributes']['no_po'];
        }
        if (! empty($props['attributes']['no_grn'])) {
            return (string) $props['attributes']['no_grn'];
        }
        if (! empty($props['attributes']['no_transfer'])) {
            return (string) $props['attributes']['no_transfer'];
        }
        if (! empty($props['attributes']['no_return'])) {
            return (string) $props['attributes']['no_return'];
        }
        if (! empty($props['attributes']['no_opname'])) {
            return (string) $props['attributes']['no_opname'];
        }

        return null;
    }

    /** Daftar item / produk dalam transaksi */
    public function getItemsListAttribute(): array
    {
        $props = $this->normalized_properties;
        $items = [];
        if (! empty($props['items']) && is_array($props['items'])) {
            $items = $props['items'];
        } elseif (! empty($props['items_retur']) && is_array($props['items_retur'])) {
            $items = $props['items_retur'];
        }

        foreach ($items as &$item) {
            if (is_array($item)) {
                if (empty($item['nama_produk']) && ! empty($item['produk_id'])) {
                    $item['nama_produk'] = RiwayatAktivitas::resolveNamaEntitas('produk_id', $item['produk_id']) ?? ('Produk #'.$item['produk_id']);
                }
                if (empty($item['sku']) && ! empty($item['sku_variant_id'])) {
                    $item['sku'] = RiwayatAktivitas::resolveNamaEntitas('sku_variant_id', $item['sku_variant_id']) ?? ('Varian #'.$item['sku_variant_id']);
                }
            }
        }
        unset($item);

        return $items;
    }

    /** Daftar barang pengganti (tukar produk) dalam retur */
    public function getItemsTukarListAttribute(): array
    {
        $props = $this->normalized_properties;
        $items = [];
        if (! empty($props['items_tukar']) && is_array($props['items_tukar'])) {
            $items = $props['items_tukar'];
        }

        foreach ($items as &$item) {
            if (is_array($item)) {
                if (empty($item['nama_produk']) && ! empty($item['produk_id'])) {
                    $item['nama_produk'] = RiwayatAktivitas::resolveNamaEntitas('produk_id', $item['produk_id']) ?? ('Produk #'.$item['produk_id']);
                }
                if (empty($item['sku']) && ! empty($item['sku_variant_id'])) {
                    $item['sku'] = RiwayatAktivitas::resolveNamaEntitas('sku_variant_id', $item['sku_variant_id']) ?? ('Varian #'.$item['sku_variant_id']);
                }
            }
        }
        unset($item);

        return $items;
    }

    /**
     * Parsing mutasi kolom (diff) lengkap tanpa pemotongan,
     * diformat dalam Bahasa Indonesia yang ramah pengguna awam.
     *
     * @return array<int, array{kunci: string, label: string, sebelum: string, sesudah: string, tipe: string}>
     */
    public function getDaftarMutasiAttribute(): array
    {
        $rawChanges = $this->attribute_changes;
        $changes = is_object($rawChanges) && method_exists($rawChanges, 'get')
            ? $rawChanges
            : collect(is_array($rawChanges) ? $rawChanges : (is_string($rawChanges) ? (json_decode($rawChanges, true) ?: []) : []));

        $sesudah = $changes->get('attributes') ?? [];
        $sebelum = $changes->get('old') ?? [];

        $lewati = [
            'id', 'created_at', 'updated_at', 'password', 'remember_token',
            'two_factor_secret', 'two_factor_recovery_codes',
        ];

        $kunciSemua = array_values(array_unique(array_merge(array_keys($sesudah), array_keys($sebelum))));
        $kunciSemua = array_values(array_filter($kunciSemua, fn ($k) => ! in_array($k, $lewati, true)));

        $hasil = [];
        foreach ($kunciSemua as $key) {
            $valSebelum = $sebelum[$key] ?? null;
            $valSesudah = $sesudah[$key] ?? null;

            // Lewati jika update tapi nilai sebelum dan sesudah identik
            if ($this->event === 'updated' && $valSebelum === $valSesudah) {
                continue;
            }

            // Pada event created, lewati field yang memang tidak diisi (null / '') agar ringkas dan relevan
            if ($this->event === 'created' && ($valSesudah === null || $valSesudah === '')) {
                continue;
            }

            // Pada event deleted, lewati field yang null saat sebelum dihapus
            if ($this->event === 'deleted' && ($valSebelum === null || $valSebelum === '')) {
                continue;
            }

            $tipeMutasi = match ($this->event) {
                'created' => 'tambah',
                'deleted' => 'hapus',
                default => 'ubah',
            };

            $hasil[] = [
                'kunci' => $key,
                'label' => RiwayatAktivitas::labelKolom($key),
                'sebelum' => RiwayatAktivitas::formatNilai($valSebelum, $key),
                'sesudah' => RiwayatAktivitas::formatNilai($valSesudah, $key),
                'tipe' => $tipeMutasi,
            ];
        }

        return $hasil;
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
