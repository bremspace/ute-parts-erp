<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

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
        // Seed default PPN config for each test
        $service = app(\App\Modules\Akunting\Services\PajakService::class);
        $service->seedDefaults();
    }
}
