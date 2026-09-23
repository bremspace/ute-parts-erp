<?php

namespace App\Modules\Rbac\Traits;

use App\Modules\Rbac\Services\AktivitasCabang;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Support\LogOptions;

/**
 * [F1-4] Pelengkap Spatie\Activitylog\Models\Concerns\LogsActivity utk model kritis
 * (Transaksi, JurnalAkuntansi, StokItem, PurchaseOrder, Piutang, Utang, Produk).
 *
 * Pakai bersama trait LogsActivity + method getActivitylogOptions() di model:
 *
 *   use CatatAktivitas;
 *   use LogsActivity;
 *
 *   public function getActivitylogOptions(): LogOptions
 *   {
 *       return $this->opsilogAktivitas('Transaksi');
 *   }
 *
 * - before/after JSON penuh (attribute_changes.attributes + .old) — logAll()
 * - field sensitif dibuang oleh config('activitylog.default_except_attributes')
 * - causer = auth user (default Spatie); tanpa auth (job/seed) → null → "sistem" di UI
 * - cabang_id row disimpan ke properties + kolom activity_log.cabang_id utk filter
 */
trait CatatAktivitas
{
    public function opsilogAktivitas(string $nama): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            // Update → simpan hanya field yang berubah (hemat storage di server 1GB);
            // create/delete tetap snapshot penuh. before = old, after = attributes.
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event) => $nama.' '.self::labelAksiAktivitas($event));
    }

    public static function labelAksiAktivitas(string $event): string
    {
        return match ($event) {
            'created' => 'dibuat',
            'updated' => 'diperbarui',
            'deleted' => 'dihapus',
            'restored' => 'dipulihkan',
            default => $event,
        };
    }

    /**
     * Hook Spatie — dipanggil tepat sebelum baris activity disimpan.
     *
     * @param  Model  $activity
     */
    public function beforeActivityLogged($activity, string $event): void
    {
        $cabangId = AktivitasCabang::untuk($this);

        if ($cabangId === null) {
            return;
        }

        $activity->properties = collect($activity->properties)->put('cabang_id', $cabangId);
        $activity->cabang_id = $cabangId;
    }
}
