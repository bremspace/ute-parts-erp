<?php

namespace App\Modules\Hr\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'cabang_id', 'jabatan_target', 'jenis', 'nominal', 'persen',
    'min_status_tiket', 'is_aktif',
])]
class KomisiTeknisiRule extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'komisi_teknisi_rules';

    protected $casts = ['nominal' => 'decimal:2', 'persen' => 'decimal:2', 'is_aktif' => 'boolean'];

    /**
     * [F1-4] Audit trail aturan komisi teknisi.
     *
     * `cabang_id` ada di tabel sehingga `CatatAktivitas` menyimpan cabang aktif ke
     * activity log — perubahan nominal komisi per cabang bisa disaring dan tidak
     * bocor ke cabang lain.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Aturan Komisi Teknisi');
    }
}
