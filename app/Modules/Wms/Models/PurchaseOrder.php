<?php

namespace App\Modules\Wms\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'no_po', 'supplier_id', 'gudang_tujuan_id', 'status', 'metode_bayar', 'akun_kas_bank',
    'jatuh_tempo', 'total', 'total_dibayar', 'catatan',
])]
class PurchaseOrder extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'purchase_order';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Purchase Order');
    }

    protected $casts = [
        'jatuh_tempo' => 'date',
        'total' => 'decimal:2',
        'total_dibayar' => 'decimal:2',
        'status' => 'string',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function gudangTujuan(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_tujuan_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(PembayaranSupplier::class, 'po_id');
    }

    public function grn(): HasOne
    {
        return $this->hasOne(Grn::class, 'po_id');
    }

    public function returnPembelian(): HasMany
    {
        return $this->hasMany(ReturnPembelian::class, 'purchase_order_id');
    }

    public function getSisaAttribute(): float
    {
        return max(0, (float) $this->total - (float) $this->total_dibayar);
    }

    public function generateWaPesananUrl(): ?string
    {
        $supplier = $this->supplier;
        if (! $supplier || empty($supplier->telepon)) {
            return null;
        }

        $phone = preg_replace('/[^0-9]/', '', (string) $supplier->telepon);
        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        }

        $cabangNama = $this->gudangTujuan?->cabang?->nama ?? config('app.name', 'Bremspace');
        $gudangNama = $this->gudangTujuan?->nama ?? '-';
        $tanggal = $this->created_at ? $this->created_at->format('d/m/Y') : date('d/m/Y');

        $text = "*PURCHASE ORDER (PO) - {$cabangNama}*\n";
        $text .= "No. PO: *{$this->no_po}*\n";
        $text .= "Tanggal: {$tanggal}\n";
        $text .= "Kepada: *{$supplier->nama}*\n";
        $text .= "Gudang Tujuan: {$gudangNama}\n";
        $text .= 'Metode Bayar: '.strtoupper($this->metode_bayar)."\n\n";
        $text .= "*Daftar Barang Pesanan:*\n";

        $i = 1;
        foreach ($this->items as $item) {
            $prodNama = $item->produk?->nama ?? ('Produk #'.$item->produk_id);
            $qty = number_format($item->jumlah);
            $satuan = $item->satuan ?: ($item->produk?->satuan ?: 'pcs');
            $harga = number_format((float) $item->harga_beli, 0, ',', '.');
            $subtotal = number_format((float) $item->subtotal, 0, ',', '.');
            $text .= "{$i}. {$prodNama}\n   Qty: {$qty} {$satuan} @ Rp {$harga} = Rp {$subtotal}\n";
            $i++;
        }

        $text .= "\n*Total Estimasi:* Rp ".number_format((float) $this->total, 0, ',', '.')."\n";
        if ($this->catatan) {
            $text .= "Catatan: {$this->catatan}\n";
        }
        $text .= "\nMohon konfirmasi ketersediaan barang dan jadwal pengiriman. Terima kasih.";

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($text);
    }

    public function generateWaPenolakanUrl(?string $alasan = null): ?string
    {
        $supplier = $this->supplier;
        if (! $supplier || empty($supplier->telepon)) {
            return null;
        }

        $phone = preg_replace('/[^0-9]/', '', (string) $supplier->telepon);
        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        }

        $cabangNama = $this->gudangTujuan?->cabang?->nama ?? config('app.name', 'Bremspace');
        $gudangNama = $this->gudangTujuan?->nama ?? '-';
        $tanggal = now()->format('d/m/Y H:i');
        $alasanFinal = $alasan ?: ($this->catatan ?: 'Ketidaksesuaian barang saat pemeriksaan fisik gudang');

        $text = "*PEMBERITAHUAN PENOLAKAN PENERIMAAN BARANG / PO*\n";
        $text .= "No. PO: *{$this->no_po}*\n";
        $text .= "Tanggal Penolakan: {$tanggal}\n";
        $text .= "Supplier: *{$supplier->nama}*\n";
        $text .= "Gudang: {$gudangNama} ({$cabangNama})\n\n";
        $text .= "Dengan ini kami menginformasikan bahwa pengiriman barang untuk No. PO di atas *TIDAK DAPAT DITERIMA / DITOLAK* oleh tim gudang kami.\n\n";
        $text .= "*Alasan Penolakan:*\n{$alasanFinal}\n\n";
        $text .= "*Status PO di Sistem:* Dibatalkan (Tidak ada stok masuk & tidak diterbitkan utang).\n\n";
        $text .= 'Mohon koordinasi dengan tim pengadaan kami untuk tindak lanjut / jadwal penggantian barang. Terima kasih.';

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($text);
    }
}
