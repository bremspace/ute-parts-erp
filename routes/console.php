<?php

use App\Modules\Hr\Jobs\HitungKpiBulananJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('servis:cek-batas-waktu')->everyTenMinutes();
Schedule::command('duitku:rekon')->everyTenMinutes();

// [F3-8b] Otomatis hitung KPI bulanan seluruh karyawan tiap tanggal 1 jam 01:00
Schedule::job(new HitungKpiBulananJob)->monthlyOn(1, '01:00');

// Hybrid Sync Scheduler — aktif pada node mana pun yang punya remote_url.
$shouldRunSync = fn (): bool => (bool) config('sync.enabled')
    && ! empty(config('sync.remote_url'));

Schedule::command('sync:flush --batch')
    ->everyMinute()
    ->withoutOverlapping()
    ->when($shouldRunSync);

Schedule::command('sync:pull')
    ->everyMinute()
    ->withoutOverlapping()
    ->when($shouldRunSync);
