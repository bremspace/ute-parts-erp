<?php

namespace App\Modules\Rbac\Services;

use App\Modules\Wms\Models\Gudang;
use Illuminate\Database\Eloquent\Model;

/**
 * [F1-4] Resolver cabang_id utk audit trail (activity_log).
 * Ambil dari row subject bila ada; jika tidak, turunkan lewat gudang
 * (StokItem → gudang_id, PurchaseOrder → gudang_tujuan_id).
 * null = entitas global (mis. Produk master lintas cabang).
 */
class AktivitasCabang
{
    public static function untuk(Model $model): ?int
    {
        $attributes = $model->getAttributes();

        if (array_key_exists('cabang_id', $attributes)) {
            return $attributes['cabang_id'] === null ? null : (int) $attributes['cabang_id'];
        }

        foreach (['gudang_id', 'gudang_tujuan_id'] as $kolom) {
            if (! empty($attributes[$kolom])) {
                $cabangId = Gudang::query()->whereKey($attributes[$kolom])->value('cabang_id');

                return $cabangId === null ? null : (int) $cabangId;
            }
        }

        return null;
    }
}
