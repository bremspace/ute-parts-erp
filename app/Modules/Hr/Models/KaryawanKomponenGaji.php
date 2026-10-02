<?php

namespace App\Modules\Hr\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['karyawan_id', 'tipe', 'nama', 'nominal_bulanan', 'is_aktif'])]
class KaryawanKomponenGaji extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'karyawan_komponen_gaji';

    protected $casts = ['nominal_bulanan' => 'decimal:2', 'is_aktif' => 'boolean'];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    /**
     * [F1-4] Audit trail komponen gaji (tunjangan/potongan/bonus) per karyawan.
     *
     * Volume rendah: hanya menulis saat admin menambah/mengubah komponen, bukan
     * per slip. Efek bersihnya tetap terekam di `PayrollSlip` (total_tunjangan /
     * total_potongan), tapi perubahan struktur gaji baru terlihat di slip bulan
     *berikutnya — jadi log di sini yang menutup celah "gaji quietly dinaikkan".
     */
    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Komponen Gaji Karyawan');
    }
}
