<?php

namespace Tests;

use App\Modules\Akunting\Services\PajakService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Seed default PPN config for each test
        $this->seedPpnDefaults();
    }

    protected function seedPpnDefaults(): void
    {
        // Seed default PPN config for each test if table exists
        if (Schema::hasTable('konfigurasi')) {
            $service = app(PajakService::class);
            $service->seedDefaults();
        }
    }
}
