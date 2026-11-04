<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Livewire\DatabaseManagement;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DatabaseManagementUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Utama',
            'kode' => 'C01',
            'is_active' => true,
        ]);

        $role = Role::firstOrCreate(['name' => 'super-admin']);
        $this->superAdmin = User::create([
            'name' => 'Super Administrator',
            'email' => 'superadmin@uteparts.id',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole($role);
        $this->superAdmin->cabangs()->attach($this->cabang->id);
    }

    public function test_check_app_updates_opens_modal_with_up_to_date_status(): void
    {
        $this->actingAs($this->superAdmin);

        Http::fake([
            'https://api.github.com/repos/*/compare/*' => Http::response([
                'status' => 'identical',
                'ahead_by' => 0,
                'behind_by' => 0,
                'total_commits' => 0,
                'commits' => [],
            ], 200),
        ]);

        Livewire::withQueryParams(['check_update' => '1'])
            ->test(DatabaseManagement::class)
            ->assertSet('showUpdateModal', true)
            ->assertSet('updateCheckResult.status', 'up_to_date')
            ->assertSee('Aplikasi Anda Sudah Menggunakan Versi Terbaru');
    }

    public function test_check_app_updates_displays_changelog_when_updates_available(): void
    {
        $this->actingAs($this->superAdmin);

        Http::fake([
            'https://api.github.com/repos/*/compare/*' => Http::response([
                'status' => 'ahead',
                'total_commits' => 1,
                'base_commit' => ['sha' => '1234567'],
                'commits' => [
                    [
                        'sha' => 'abcdef1234567890abcdef1234567890abcdef12',
                        'commit' => [
                            'message' => 'feat: fitur baru sistem update',
                            'author' => ['name' => 'Developer', 'date' => now()->toIso8601String()],
                        ],
                    ],
                ],
            ], 200),
        ]);

        Livewire::test(DatabaseManagement::class)
            ->call('checkAppUpdates')
            ->assertSet('showUpdateModal', true)
            ->assertSet('updateCheckResult.status', 'update_available')
            ->assertSet('updateCheckResult.commits_behind', 1)
            ->assertSee('1 Pembaruan Baru Tersedia!')
            ->assertSee('feat: fitur baru sistem update')
            ->assertSee('Pasang Pembaruan Sekarang');
    }
}
