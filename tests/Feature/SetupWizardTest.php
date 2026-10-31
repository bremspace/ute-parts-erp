<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Rbac\Livewire\SetupWizard;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Services\DatabaseManagementService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SetupWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);
    }

    public function test_uncompleted_setup_redirects_superadmin_to_setup_wizard(): void
    {
        $cabang = Cabang::create([
            'kode' => 'CBG-01',
            'nama' => 'Cabang Pusat',
            'is_active' => true,
        ]);

        $admin = User::create([
            'name' => 'Super Administrator',
            'email' => 'superadmin@uteparts.id',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $admin->assignRole('super-admin');
        $admin->cabangs()->attach($cabang->id, ['is_default' => true]);

        // Pastikan app_setup_completed belum aktif
        app(KonfigurasiService::class)->set('app_setup_completed', false);

        $response = $this->actingAs($admin)
            ->withSession(['cabang_id' => $cabang->id, 'cabang_nama' => $cabang->nama])
            ->get('/app/dashboard');

        $response->assertRedirect(route('setup.wizard'));
    }

    public function test_setup_wizard_page_renders_successfully(): void
    {
        $cabang = Cabang::create([
            'kode' => 'CBG-01',
            'nama' => 'Cabang Pusat',
            'is_active' => true,
        ]);

        $admin = User::create([
            'name' => 'Super Administrator',
            'email' => 'superadmin@uteparts.id',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $admin->assignRole('super-admin');
        $admin->cabangs()->attach($cabang->id, ['is_default' => true]);

        $response = $this->actingAs($admin)
            ->withSession(['cabang_id' => $cabang->id, 'cabang_nama' => $cabang->nama])
            ->get('/app/setup-wizard');

        $response->assertStatus(200);
        $response->assertSee('Setup Wizard Toko');
    }

    public function test_setup_wizard_steps_and_completion_flow(): void
    {
        $cabang = Cabang::create([
            'kode' => 'CBG-01',
            'nama' => 'Cabang Pusat Lama',
            'is_active' => true,
        ]);

        $admin = User::create([
            'name' => 'Super Admin Lama',
            'email' => 'superadmin@uteparts.id',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $admin->assignRole('super-admin');
        $admin->cabangs()->attach($cabang->id, ['is_default' => true]);

        $kasir = User::create([
            'name' => 'Kasir Lama',
            'email' => 'kasir@uteparts.id',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $kasir->assignRole('kasir');

        $teknisi = User::create([
            'name' => 'Teknisi Lama',
            'email' => 'teknisi@uteparts.id',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $teknisi->assignRole('teknisi');

        app(KonfigurasiService::class)->set('app_setup_completed', false);
        session(['cabang_id' => $cabang->id, 'cabang_nama' => $cabang->nama]);

        Livewire::actingAs($admin)
            ->test(SetupWizard::class)
            // Step 1: Profil Toko
            ->assertSet('currentStep', 1)
            ->set('namaToko', 'Bremspace Sentral')
            ->set('domainToko', 'bremspace.com')
            ->assertSet('superAdminEmail', 'superadmin@bremspace.com')
            ->assertSet('kasirEmail', 'kasir@bremspace.com')
            ->assertSet('teknisiEmail', 'teknisi@bremspace.com')
            ->set('namaCabang', 'Cabang Sentral')
            ->set('alamatToko', 'Jl. Komputer Raya No. 99')
            ->set('teleponToko', '081299998888')
            ->set('strukHeader', 'BREMSPACE SENTRAL')
            ->call('nextStep')
            ->assertSet('currentStep', 2)
            // Step 2: Modal Kas & Bank
            ->set('saldoKas', '5000000')
            ->set('saldoBank', '15000000')
            ->set('tanggalModal', '2026-10-31')
            ->set('buatJurnalModal', true)
            ->call('nextStep')
            ->assertSet('currentStep', 3)
            // Step 3: Akun Staf
            ->set('superAdminNama', 'Budi Santoso')
            ->set('superAdminPassword', 'SandiKuat999!')
            ->set('superAdminPasswordConfirmation', 'SandiKuat999!')
            ->set('kasirNama', 'Siti Kasir')
            ->set('kasirPassword', 'SandiKasir123!')
            ->set('teknisiNama', 'Joko Servis')
            ->call('nextStep')
            ->assertSet('currentStep', 4)
            // Step 4: Migrasi Produk (lewati)
            ->call('lewatiMigrasi')
            ->assertSet('currentStep', 5)
            // Step 5: Hardware POS
            ->set('lebarKertas', '58mm')
            ->set('aktifkanQris', false)
            ->call('nextStep')
            ->assertSet('currentStep', 6)
            // Step 6: Selesaikan Setup
            ->call('selesaikanSetup')
            ->assertRedirect(route('pos'));

        // Verifikasi hasil aktivasi
        $this->assertTrue((bool) app(KonfigurasiService::class)->get('app_setup_completed'));

        // Cabang terupdate
        $cabang->refresh();
        $this->assertEquals('Cabang Sentral', $cabang->nama);
        $this->assertEquals('081299998888', $cabang->telepon);

        // User terupdate
        $admin->refresh();
        $this->assertEquals('Budi Santoso', $admin->name);
        $this->assertEquals('superadmin@bremspace.com', $admin->email);
        $this->assertTrue(Hash::check('SandiKuat999!', $admin->password));

        $kasir->refresh();
        $this->assertEquals('Siti Kasir', $kasir->name);
        $this->assertEquals('kasir@bremspace.com', $kasir->email);
        $this->assertTrue(Hash::check('SandiKasir123!', $kasir->password));

        $teknisi->refresh();
        $this->assertEquals('teknisi@bremspace.com', $teknisi->email);

        // Domain config tersimpan
        $this->assertEquals('bremspace.com', app(KonfigurasiService::class)->get('app_domain'));

        // Jurnal modal awal terbentuk
        $jurnal = JurnalHeader::where('sumber', 'manual')
            ->where('cabang_id', $cabang->id)
            ->first();
        $this->assertNotNull($jurnal);
        $this->assertEquals(20000000, (float) $jurnal->total_debit);
        $this->assertEquals(20000000, (float) $jurnal->total_kredit);

        // Setelah completed, akses dashboard tidak diredirect lagi ke wizard
        $response = $this->actingAs($admin)
            ->withSession(['cabang_id' => $cabang->id, 'cabang_nama' => $cabang->nama])
            ->get('/app/dashboard');

        $response->assertStatus(200);
    }

    public function test_factory_reset_resets_setup_completed_flag(): void
    {
        $dbService = app(DatabaseManagementService::class);
        $config = app(KonfigurasiService::class);

        $config->set('app_setup_completed', true);
        $this->assertTrue((bool) $config->get('app_setup_completed'));

        // Jalankan factory reset
        $dbService->resetDatabase('factory_reset');

        // Flag setup awal harus false kembali
        $this->assertFalse((bool) $config->get('app_setup_completed'));
    }

    public function test_setup_wizard_saves_custom_brand_name_and_logo(): void
    {
        Storage::fake('public');

        $cabang = Cabang::create([
            'kode' => 'CBG-01',
            'nama' => 'Cabang Pusat',
            'is_active' => true,
        ]);

        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@bremspace.id',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole('super-admin');

        $logoFile = UploadedFile::fake()->image('logo.png', 100, 100);

        session(['cabang_id' => $cabang->id, 'cabang_nama' => $cabang->nama]);

        Livewire::actingAs($admin)
            ->test(SetupWizard::class)
            ->set('namaToko', 'Bremspace Mega Store')
            ->set('logoFile', $logoFile)
            ->call('nextStep')
            ->assertHasNoErrors()
            ->set('currentStep', 6)
            ->call('selesaikanSetup')
            ->assertRedirect(route('pos'));

        $config = app(KonfigurasiService::class);
        $this->assertEquals('Bremspace Mega Store', $config->get('app_brand_name'));
        $this->assertNotNull($config->get('app_logo'));
        $this->assertStringContainsString('logo/brand-logo-', $config->get('app_logo'));
    }
}
