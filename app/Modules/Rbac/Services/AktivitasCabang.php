<?php

namespace App\Modules\Rbac\Services;

use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
        if ($model instanceof Cabang) {
            return (int) $model->getKey();
        }

        $attributes = $model->getAttributes();

        if (! empty($attributes['cabang_id'])) {
            return (int) $attributes['cabang_id'];
        }

        foreach (['gudang_id', 'gudang_tujuan_id', 'gudang_asal_id'] as $kolom) {
            if (! empty($attributes[$kolom])) {
                $cabangId = Gudang::query()->whereKey($attributes[$kolom])->value('cabang_id');

                if ($cabangId !== null) {
                    return (int) $cabangId;
                }
            }
        }

        if (! empty($attributes['transaksi_id'])) {
            $cabangId = DB::table('transaksi')->where('id', $attributes['transaksi_id'])->value('cabang_id');
            if ($cabangId !== null) {
                return (int) $cabangId;
            }
        }

        return null;
    }
}
