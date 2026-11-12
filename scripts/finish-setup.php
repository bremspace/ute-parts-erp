<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    app(\App\Modules\Crm\Services\KonfigurasiService::class)->set('app_setup_completed', 'true');
    echo "SUCCESS|Setup operasional toko ditandai selesai\n";
} catch (\Throwable $e) {
    echo "ERROR|" . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
