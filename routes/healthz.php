<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| GET /healthz (F1-7 — Ops healthcheck)
|--------------------------------------------------------------------------
| Endpoint sistem untuk monitoring: cek koneksi database + koneksi queue.
| Hanya mengembalikan boolean — tidak ada data tenant/cross-branch (PRD §2).
| Diregistrasi dari bootstrap/app.php via withRouting(then: ...) — routes/web.php
| tidak disentuh (hot file).
*/

Route::get('/healthz', function () {
    $db = false;
    $queue = false;

    try {
        DB::select('select 1');
        $db = true;
    } catch (Throwable) {
        $db = false;
    }

    try {
        // size() membuktikan backend queue bisa diakses (tabel jobs terbaca
        // untuk driver database; sync/redis lolos tanpa error).
        Queue::connection()->size(config('queue.connections.'.config('queue.default').'.queue', 'default'));
        $queue = true;
    } catch (Throwable) {
        $queue = false;
    }

    $ok = $db && $queue;

    return response()->json([
        'success' => $ok,
        'data' => [
            'db' => $db,
            'queue' => $queue,
        ],
        'message' => $ok ? 'Sistem sehat' : 'Salah satu pemeriksaan gagal',
    ], $ok ? 200 : 503);
});
