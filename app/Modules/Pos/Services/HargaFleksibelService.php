<?php

namespace App\Modules\Pos\Services;

use App\Modules\Wms\Models\Produk;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

class HargaFleksibelService
{
    /**
     * Validasi input harga fleksibel.
     *
     * @throws ValidationException
     */
    public function validasi(Produk $produk, float $harga, ?Authenticatable $user): void
    {
        // 1) Produk fleksibel tapi user tidak punya permission
        if ($produk->harga_fleksibel) {
            if (! $user || ! $user->hasPermissionTo('atur-harga-fleksibel')) {
                throw ValidationException::withMessages([
                    'harga' => 'Harga fleksibel hanya dapat diinput oleh superadmin',
                ]);
            }

            // 2) Harga tidak boleh di bawah HPP (modal) jika HPP > 0
            $minimum = $this->hargaMinimum($produk);
            if ($produk->harga_beli > 0 && $harga < $minimum) {
                throw ValidationException::withMessages([
                    'harga' => 'Harga tidak boleh lebih rendah dari modal (Rp '.number_format($minimum, 0, ',', '.').')',
                ]);
            }
        }
    }

    /**
     * Harga minimum yang diizinkan (HPP atau 0).
     */
    public function hargaMinimum(Produk $produk): float
    {
        return max((float) $produk->harga_beli, 0);
    }
}
