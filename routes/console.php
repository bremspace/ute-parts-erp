<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// [T-23] Schedule eksekusi broadcast terjadwal (tiap menit)
Schedule::command('crm:broadcast-terjadwal')->everyMinute();
