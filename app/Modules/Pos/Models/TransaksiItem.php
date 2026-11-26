<?php

namespace App\Modules\Pos\Models;

use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'transaksi_id', 'produk_id', 'sku_variant_id', 'jumlah',
    'satuan', 'konversi', 'harga_satuan', 'harga_retail', 'diskon_nominal', 'subtotal', 'harga_final', 'hpp',
    'catatan',
])]
class TransaksiItem extends Model
{
    /**
     * @property int $transaksi_id
     * @property int $produk_id
     * @property int|null $sku_variant_id
     * @property int $jumlah
     * @property string $satuan
     * @property float $konversi
     * @property float $harga_satuan
     * @property float|null $harga_retail
     * @property float $diskon_nominal
     * @property float $subtotal
     * @property float|null $harga_final
     * @property float $hpp
     * @property string|null $catatan
     */
    protected $table = 'transaksi_item';

    protected $casts = [
        'jumlah' => 'integer',
        'konversi' => 'decimal:4',
        'harga_satuan' => 'decimal:2',
        'harga_retail' => 'decimal:2',
        'diskon_nominal' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'harga_final' => 'decimal:2',
        'hpp' => 'decimal:2',
    ];

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class);
    }

    /** Jumlah dalam satuan dasar (pcs) = jumlah satuan jual × faktor konversi. */
    public function qtyDasar(): int
    {
        $faktor = max(1.0, (float) ($this->konversi ?? 1));

        return (int) round((int) $this->jumlah * $faktor);
    }

    public function isSatuanKonversi(): bool
    {
        $satuan = strtolower(trim((string) ($this->satuan ?: 'pcs')));

        return $this->qtyDasar() > (int) $this->jumlah && $satuan !== '' && $satuan !== 'pcs';
    }

    /** Label lengkap untuk picking: "1 BOX (10 pcs)". */
    public function qtyLabel(): string
    {
        $satuan = strtoupper(trim((string) ($this->satuan ?: 'pcs'))) ?: 'PCS';
        $label = (int) $this->jumlah.' '.$satuan;

        if ($this->isSatuanKonversi()) {
            $label .= ' ('.$this->qtyDasar().' pcs)';
        }

        return $label;
    }

    /** Label ringkas untuk kartu daftar order: "×1 BOX (10 pcs)". */
    public function qtyRingkas(): string
    {
        $label = '×'.(int) $this->jumlah;
        if ($this->isSatuanKonversi()) {
            $label .= ' '.strtoupper(trim((string) ($this->satuan ?: 'box')))
                .' ('.$this->qtyDasar().' pcs)';
        }

        return $label;
    }

    /** Catatan picking untuk struk/label, bila item dibeli dalam satuan konversi. */
    public function catatanPicking(): ?string
    {
        if (! $this->isSatuanKonversi()) {
            return null;
        }

        return 'Isi '.(int) $this->jumlah.' '.strtoupper(trim((string) $this->satuan))
            .' = '.$this->qtyDasar().' pcs';
    }

    /**
     * @return BelongsTo<Produk, $this>
     */
    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }

    /**
     * @return BelongsTo<SkuVariant, $this>
     */
    public function skuVariant(): BelongsTo
    {
        return $this->belongsTo(SkuVariant::class);
    }
}
