<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class GitDeployWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_unauthorized_request(): void
    {
        putenv('GIT_DEPLOY_SECRET=supersecret123');

        $response = $this->postJson('/webhook/git-deploy', []);
        $response->assertStatus(401);
    }

    public function test_rejects_when_secret_not_configured(): void
    {
        putenv('GIT_DEPLOY_SECRET=');
        Config::set('services.git_deploy.secret', null);

        $response = $this->postJson('/webhook/git-deploy', ['secret' => 'anything']);
        $response->assertStatus(403);
    }

    public function test_accepts_valid_github_signature(): void
    {
        $secret = 'testsecretkey123';
        putenv("GIT_DEPLOY_SECRET={$secret}");

        $payload = json_encode(['ref' => 'refs/heads/main']);
        $sig = 'sha256='.hash_hmac('sha256', $payload, $secret);

        $response = $this->call(
            'POST',
            '/webhook/git-deploy',
            [],
            [],
            [],
            [
                'HTTP_X-Hub-Signature-256' => $sig,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        // Akan mencoba menjalankan git pull atau return response (bisa status 200 atau 500 tergantung git di test env)
        $this->assertNotEquals(401, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
