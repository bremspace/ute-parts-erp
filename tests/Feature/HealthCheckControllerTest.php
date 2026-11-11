<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class HealthCheckControllerTest extends TestCase
{
    public function test_health_check_requires_authorization(): void
    {
        Config::set('app.health_check_secret', 'test-secret-12345');

        $response = $this->getJson('/api/health');
        $response->assertStatus(401)
            ->assertJson([
                'status' => 'unauthorized',
            ]);

        $invalidResponse = $this->withHeaders([
            'X-Health-Check-Secret' => 'wrong-secret',
        ])->getJson('/api/health');

        $invalidResponse->assertStatus(401);
    }

    public function test_health_check_succeeds_with_valid_header(): void
    {
        $secret = 'test-secret-12345';
        Config::set('app.health_check_secret', $secret);

        $response = $this->withHeaders([
            'X-Health-Check-Secret' => $secret,
        ])->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'timestamp',
                'environment',
                'checks' => [
                    'database' => ['status', 'latency_ms'],
                    'cache' => ['status', 'latency_ms'],
                    'storage' => ['status', 'directories'],
                ],
            ]);
    }

    public function test_health_check_succeeds_with_query_token(): void
    {
        $secret = 'test-secret-67890';
        Config::set('app.health_check_secret', $secret);

        $response = $this->getJson('/api/health?token=' . $secret);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'healthy',
            ]);
    }
}
