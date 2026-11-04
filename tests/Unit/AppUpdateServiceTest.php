<?php

namespace Tests\Unit;

use App\Modules\Rbac\Services\AppUpdateService;
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
}
