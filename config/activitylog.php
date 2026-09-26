<?php

use App\Modules\Rbac\Models\AktivitasLog;
use Spatie\Activitylog\Actions\CleanActivityLogAction;
use Spatie\Activitylog\Actions\LogActivityAction;

return [

    /*
     * If set to false, no activities will be saved to the database.
     */
    'enabled' => env('ACTIVITYLOG_ENABLED', true),

    /*
     * When the clean command is executed, all recording activities older than
     * the number of days specified here will be deleted.
     */
    'clean_after_days' => 365,

    /*
     * If no log name is passed to the activity() helper
     * we use this default log name.
     */
    'default_log_name' => 'default',

    /*
     * You can specify an auth driver here that gets user models.
     * If this is null we'll use the current Laravel auth driver.
     */
    'default_auth_driver' => null,

    /*
     * If set to true, the subject relationship on activities
     * will include soft deleted models.
     */
    'include_soft_deleted_subjects' => false,

    /*
     * This model will be used to log activity.
     * It should implement the Spatie\Activitylog\Contracts\Activity interface
     * and extend Illuminate\Database\Eloquent\Model.
     */
    // [F1-4] Model audit kustom (aksesor causerNama → tampil "sistem" bila tanpa auth)
    'activity_model' => AktivitasLog::class,

    /*
     * These attributes will be excluded from logging for all models.
     * Model-specific exclusions via logExcept() are merged with these.
     *
     * [F1-4] Field sensitif tidak pernah ikut tersimpan di before/after JSON.
     *
     * [B-10d / P1-3] Daftar diperluas sebagai defense-in-depth: walaupun ada
     * model yang kelalaian `logAll()`, isi field berikut tetap tidak pernah
     * masuk snapshot activity log.
     * - kunci_terenkripsi: pola/tipe kunci HP pelanggan (sudah encrypted-at-rest)
     * - payload_json: snapshot entitas workflow approval (bisa berisi gaji/komisi)
     * - pesan / segment: isi & filter kampanye broadcast (data pelanggan)
     * - password_hash: cukup `password` saja; hash pun tidak pernah diaudit
     * - kode_verifikasi: OTP login
     */
    'default_except_attributes' => [
        'password',
        'remember_token',
        'token',
        'api_token',
        'secret',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_backup_codes',
        'backup_codes',
        'password_hash',
        'kunci_terenkripsi',
        'payload_json',
        'pesan',
        'segment',
        'kode_verifikasi',
    ],

    /*
     * When enabled, activities are buffered in memory and inserted in a
     * single bulk query after the response has been sent to the client.
     * This can significantly reduce the number of database queries when
     * many activities are logged during a single request.
     *
     * Only enable this if your application logs a high volume of activities
     * per request. Buffered activities will not have an ID until the
     * buffer is flushed.
     */
    'buffer' => [
        'enabled' => env('ACTIVITYLOG_BUFFER_ENABLED', false),
    ],

    /*
     * These action classes can be overridden to customize how activities
     * are logged and cleaned. Your custom classes must extend the originals.
     */
    'actions' => [
        'log_activity' => LogActivityAction::class,
        'clean_log' => CleanActivityLogAction::class,
    ],
];
