<?php

namespace App\Modules\Rbac\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot bertipe untuk relasi User::cabangs() (tabel user_cabang).
 * Kolom sesuai migration 2026_09_16_000002: id (bigIncrements),
 * user_id, cabang_id, is_default (boolean), timestamps.
 *
 * @property bool $is_default
 */
class CabangUserPivot extends Pivot
{
    protected $table = 'user_cabang';

    public $incrementing = true;
}
