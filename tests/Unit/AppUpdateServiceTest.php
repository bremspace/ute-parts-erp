<?php

namespace Tests\Unit;

use App\Modules\Rbac\Services\AppUpdateService;
use Dotenv\Dotenv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppUpdateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_current_version_info_returns_sanitized_structure(): void
    {
        $service = app(AppUpdateService::class);
        $info = $service->getCurrentVersionInfo();

        $this->assertIsArray($info);
        $this->assertArrayHasKey('commit', $info);
        $this->assertArrayHasKey('date', $info);
        $this->assertArrayHasKey('message', $info);
        $this->assertArrayHasKey('branch', $info);
        $this->assertStringNotContainsString('fatal:', $info['commit']);
        $this->assertStringNotContainsString('fatal:', $info['branch']);
    }

    public function test_get_repo_owner_and_name_returns_valid_tuple(): void
    {
        $service = app(AppUpdateService::class);
        [$owner, $repo] = $service->getRepoOwnerAndName();

        $this->assertNotEmpty($owner);
        $this->assertNotEmpty($repo);
        $this->assertSame('bremspace', $owner);
        $this->assertSame('ute-parts-erp', $repo);
    }

    public function test_check_for_updates_detects_up_to_date_via_github_api(): void
    {
        Http::fake([
            'https://api.github.com/repos/*/compare/*' => Http::response([
                'status' => 'identical',
                'ahead_by' => 0,
                'behind_by' => 0,
                'total_commits' => 0,
                'commits' => [],
            ], 200),
        ]);

        $service = app(AppUpdateService::class);
        $res = $service->checkForUpdates();

        $this->assertFalse($res['has_update']);
        $this->assertSame('up_to_date', $res['status']);
        $this->assertSame(0, $res['commits_behind']);
        $this->assertEmpty($res['changelog']);
    }

    public function test_check_for_updates_detects_new_commits_via_github_api(): void
    {
        Http::fake([
            'https://api.github.com/repos/*/compare/*' => Http::response([
                'status' => 'ahead',
                'total_commits' => 2,
                'base_commit' => ['sha' => '1111111'],
                'commits' => [
                    [
                        'sha' => '2222222333333333333333333333333333333333',
                        'commit' => [
                            'message' => "First feature update\nDetails here",
                            'author' => ['name' => 'Alice', 'date' => now()->subHours(2)->toIso8601String()],
                        ],
                    ],
                    [
                        'sha' => '4444444555555555555555555555555555555555',
                        'commit' => [
                            'message' => 'Second bugfix update',
                            'author' => ['name' => 'Bob', 'date' => now()->subHour()->toIso8601String()],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = app(AppUpdateService::class);
        $res = $service->checkForUpdates();

        $this->assertTrue($res['has_update']);
        $this->assertSame('update_available', $res['status']);
        $this->assertSame(2, $res['commits_behind']);
        $this->assertCount(2, $res['changelog']);
        $this->assertSame('4444444', $res['latest_hash']);
        $this->assertSame('Second bugfix update', $res['changelog'][0]['subject']);
    }

    public function test_apply_update_returns_safe_message_when_in_docker(): void
    {
        $mock = $this->getMockBuilder(AppUpdateService::class)
            ->onlyMethods(['isDocker'])
            ->getMock();
        $mock->method('isDocker')->willReturn(true);

        $res = $mock->applyUpdate();

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Docker Container', $res['message']);
        $this->assertStringContainsString('update-system', $res['message']);
    }

    public function test_get_git_token_falls_back_to_embedded_token_when_env_empty(): void
    {
        putenv('GITHUB_DEPLOY_TOKEN');
        putenv('GIT_TOKEN');

        $service = app(AppUpdateService::class);
        $token = $service->getGitToken();

        $this->assertNotEmpty($token);
        $this->assertStringStartsWith('github_pat_', $token);
    }

    public function test_update_env_safely_escapes_special_characters_and_preserves_dotenv_validity(): void
    {
        $service = app(AppUpdateService::class);
        $tempEnv = tempnam(sys_get_temp_dir(), 'env_test_');
        file_put_contents($tempEnv, "APP_NAME=\"Bremspace\"\nAPP_ENV=testing\n");

        // Override base_path temporarily or test logic directly
        $content = file_get_contents($tempEnv);
        $values = [
            'SAFE_KEY' => 'SimpleValue',
            'SPACE_KEY' => 'Value With Spaces',
            'BACKSLASH_KEY' => 'Path\\With\\Backslashes',
            'SPECIAL_CHARS' => 'P@ssw0rd!"$#',
        ];

        foreach ($values as $key => $value) {
            $valueStr = (string) $value;
            $valueStr = str_replace(["\r", "\n"], '', $valueStr);
            if (preg_match('/[ #\t"\'\\\\]/', $valueStr) || $valueStr === '') {
                $escaped = addcslashes($valueStr, '"\\$');
                $formatted = '"'.$escaped.'"';
            } else {
                $formatted = $valueStr;
            }
            $pattern = '/^'.preg_quote($key, '/').'=.*/m';
            if (preg_match($pattern, $content)) {
                $content = preg_replace_callback($pattern, fn () => "{$key}={$formatted}", $content);
            } else {
                $content .= "\n{$key}={$formatted}";
            }
        }
        file_put_contents($tempEnv, $content);

        // Verify Dotenv can parse it without error
        $parsed = Dotenv::createImmutable(dirname($tempEnv), basename($tempEnv))->load();
        $this->assertIsArray($parsed);
        $this->assertSame('SimpleValue', $parsed['SAFE_KEY']);
        $this->assertSame('Value With Spaces', $parsed['SPACE_KEY']);
        $this->assertSame('Path\\With\\Backslashes', $parsed['BACKSLASH_KEY']);

        @unlink($tempEnv);
    }

    public function test_check_for_updates_retries_github_api_without_token_on_401(): void
    {
        Http::fake([
            'https://api.github.com/repos/*/compare/*' => Http::sequence()
                ->push(['message' => 'Bad credentials'], 401)
                ->push([
                    'status' => 'identical',
                    'ahead_by' => 0,
                    'behind_by' => 0,
                    'total_commits' => 0,
                    'commits' => [],
                ], 200),
        ]);

        $service = app(AppUpdateService::class);
        $res = $service->checkForUpdates();

        $this->assertFalse($res['has_update']);
        $this->assertSame('up_to_date', $res['status']);
    }

    public function test_check_for_updates_falls_back_to_ls_remote_when_git_fetch_head_permission_denied(): void
    {
        Http::fake([
            'https://api.github.com/*' => Http::response(['message' => 'Not Found'], 404),
        ]);

        $sha = 'abcdef1234567890abcdef1234567890abcdef12';
        $service = new class($sha) extends AppUpdateService
        {
            public bool $fetchCalled = false;

            public function __construct(private string $sha) {}

            protected function isGitWritable(): bool
            {
                return false;
            }

            protected function runGit(array $arguments): array
            {
                $cmd = implode(' ', $arguments);
                if (str_contains($cmd, 'fetch')) {
                    $this->fetchCalled = true;

                    return ['status' => 1, 'output' => "error:cannot open '.git/FETCH_HEAD': Permission denied"];
                }
                if (str_contains($cmd, 'ls-remote')) {
                    return ['status' => 0, 'output' => "{$this->sha}\trefs/heads/main"];
                }
                if (str_contains($cmd, 'rev-parse --short HEAD')) {
                    return ['status' => 0, 'output' => '1111111'];
                }
                if (str_contains($cmd, 'rev-parse HEAD')) {
                    return ['status' => 0, 'output' => '1111111222222222222222222222222222222222'];
                }
                if (str_contains($cmd, 'rev-parse --abbrev-ref HEAD')) {
                    return ['status' => 0, 'output' => 'main'];
                }
                if (str_contains($cmd, 'remote get-url origin')) {
                    return ['status' => 0, 'output' => 'https://github.com/bremspace/ute-parts-erp.git'];
                }

                return ['status' => 0, 'output' => ''];
            }
        };

        $res = $service->checkForUpdates();

        $this->assertFalse($service->fetchCalled);
        $this->assertTrue($res['has_update']);
        $this->assertSame('update_available', $res['status']);
        $this->assertSame('abcdef1', $res['latest_hash']);
    }

    public function test_check_for_updates_falls_back_to_ls_remote_up_to_date_when_fetch_returns_permission_denied(): void
    {
        Http::fake([
            'https://api.github.com/*' => Http::response(['message' => 'API error'], 500),
        ]);

        $sha = 'abcdef1234567890abcdef1234567890abcdef12';
        $service = new class($sha) extends AppUpdateService
        {
            public function __construct(private string $sha) {}

            protected function isGitWritable(): bool
            {
                return true;
            }

            protected function runGit(array $arguments): array
            {
                $cmd = implode(' ', $arguments);
                if (str_contains($cmd, 'fetch')) {
                    return ['status' => 128, 'output' => "error:cannot open '.git/FETCH_HEAD': Permission denied"];
                }
                if (str_contains($cmd, 'ls-remote')) {
                    return ['status' => 0, 'output' => "{$this->sha}\trefs/heads/main"];
                }
                if (str_contains($cmd, 'rev-parse --short HEAD')) {
                    return ['status' => 0, 'output' => 'abcdef1'];
                }
                if (str_contains($cmd, 'rev-parse HEAD')) {
                    return ['status' => 0, 'output' => $this->sha];
                }
                if (str_contains($cmd, 'rev-parse --abbrev-ref HEAD')) {
                    return ['status' => 0, 'output' => 'main'];
                }
                if (str_contains($cmd, 'remote get-url origin')) {
                    return ['status' => 0, 'output' => 'https://github.com/bremspace/ute-parts-erp.git'];
                }

                return ['status' => 0, 'output' => ''];
            }
        };

        $res = $service->checkForUpdates();

        $this->assertFalse($res['has_update']);
        $this->assertSame('up_to_date', $res['status']);
    }

    public function test_apply_update_returns_actionable_message_on_git_permission_denied(): void
    {
        $service = new class extends AppUpdateService
        {
            protected function runGit(array $arguments): array
            {
                $cmd = implode(' ', $arguments);
                if (str_contains($cmd, 'pull')) {
                    return ['status' => 1, 'output' => "error:cannot open '.git/FETCH_HEAD': Permission denied"];
                }
                if (str_contains($cmd, 'rev-parse --abbrev-ref HEAD')) {
                    return ['status' => 0, 'output' => 'main'];
                }

                return ['status' => 0, 'output' => 'dev'];
            }
        };

        $res = $service->applyUpdate();

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('sudo chown -R', $res['message']);
        $this->assertStringContainsString('git pull origin main', $res['message']);
    }
}
