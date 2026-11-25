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

// Asymmetric Hybrid Sync Scheduler (hanya aktif pada primary_store yang memiliki remote_url)
$shouldRunPrimarySync = fn (): bool => (bool) config('sync.enabled')
    && config('sync.node_role') === 'primary_store'
    && ! empty(config('sync.remote_url'));

Schedule::command('sync:flush --batch')
    ->everyMinute()
    ->withoutOverlapping()
    ->when($shouldRunPrimarySync);

Schedule::command('sync:pull')
    ->everyMinute()
    ->withoutOverlapping()
    ->when($shouldRunPrimarySync);
