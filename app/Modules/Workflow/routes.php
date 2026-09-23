<?php

use App\Modules\Workflow\Controllers\ApprovalController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/workflow')->middleware('auth:sanctum')->group(function () {
    Route::post('/ajukan', [ApprovalController::class, 'ajukan']);
    Route::post('/proses/{id}', [ApprovalController::class, 'proses']);
    Route::get('/ada-pending', [ApprovalController::class, 'adaPending']);
});
