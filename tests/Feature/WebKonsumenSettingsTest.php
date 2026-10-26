<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Marketplace\Livewire\WebKonsumenSettings;
use App\Modules\Marketplace\Models\WebBanner;
use App\Modules\Marketplace\Models\WebKonsumenSetting;
use App\Modules\Marketplace\Services\WebKonsumenService;
use Database\Seeders\RoleUserSeeder;
use Database\Seeders\WebKonsumenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class WebKonsumenSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $kasir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleUserSeeder::class);
        $this->seed(WebKonsumenSeeder::class);

        $this->superAdmin = User::where('email', 'superadmin@uteparts.id')->first()
            ?? User::factory()->create(['email' => 'superadmin@uteparts.id', 'password' => Hash::make('Password123!')]);
        if (! $this->superAdmin->hasRole('super-admin')) {
            $this->superAdmin->assignRole('super-admin');
        }

        $this->kasir = User::where('email', 'kasir@uteparts.id')->first()
            ?? User::factory()->create(['email' => 'kasir@uteparts.id', 'password' => Hash::make('Password123!')]);
        if (! $this->kasir->hasRole('kasir')) {
            $this->kasir->assignRole('kasir');
        }
    }

    public function test_guest_cannot_access_web_konsumen_settings(): void
    {
        $response = $this->get('/app/pengaturan/web-konsumen');
        $response->assertRedirect('/app/login');
    }

    public function test_unauthorized_user_cannot_access_web_konsumen_settings(): void
    {
        $response = $this->actingAs($this->kasir)->get('/app/pengaturan/web-konsumen');
        $response->assertStatus(403);
    }

    public function test_super_admin_can_access_web_konsumen_settings(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/app/pengaturan/web-konsumen');
        $response->assertStatus(200);
        $response->assertSee('Manajemen Halaman Web Konsumen');
    }

    public function test_can_update_hero_and_branding_settings(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(WebKonsumenSettings::class)
            ->set('heroForm.hero_headline', 'Sparepart HP Terlengkap & Presisi')
            ->set('heroForm.hero_badge_text', 'Official Certified Lab')
            ->set('brandingForm.topbar_text', 'Diskon Spesial Hari Ini 20%')
            ->call('save')
            ->assertDispatched('alert');

        $service = app(WebKonsumenService::class);
        $this->assertEquals('Sparepart HP Terlengkap & Presisi', $service->get('hero_headline'));
        $this->assertEquals('Official Certified Lab', $service->get('hero_badge_text'));
        $this->assertEquals('Diskon Spesial Hari Ini 20%', $service->get('topbar_text'));

        $this->assertDatabaseHas('web_konsumen_settings', [
            'setting_key' => 'hero_headline',
            'setting_value' => 'Sparepart HP Terlengkap & Presisi',
        ]);
    }

    public function test_can_manage_banners_crud(): void
    {
        // 1. Create banner
        Livewire::actingAs($this->superAdmin)
            ->test(WebKonsumenSettings::class)
            ->call('openCreateBanner')
            ->set('bannerForm.judul', 'Promo Flash Sale Gajian')
            ->set('bannerForm.badge_label', 'HOT DEAL')
            ->set('bannerForm.gambar_desktop', '/images/banners/gajian.jpg')
            ->set('bannerForm.posisi', 'hero_slider')
            ->set('bannerForm.urutan', 1)
            ->call('saveBanner')
            ->assertDispatched('alert');

        $this->assertDatabaseHas('web_banners', [
            'judul' => 'Promo Flash Sale Gajian',
            'badge_label' => 'HOT DEAL',
            'posisi' => 'hero_slider',
        ]);

        $banner = WebBanner::where('judul', 'Promo Flash Sale Gajian')->first();
        $this->assertNotNull($banner);

        // 2. Toggle status
        Livewire::actingAs($this->superAdmin)
            ->test(WebKonsumenSettings::class)
            ->call('toggleBannerStatus', $banner->id)
            ->assertDispatched('alert');

        $this->assertFalse((bool) $banner->fresh()->is_active);

        // 3. Delete banner
        Livewire::actingAs($this->superAdmin)
            ->test(WebKonsumenSettings::class)
            ->call('deleteBanner', $banner->id)
            ->assertDispatched('alert');

        $this->assertDatabaseMissing('web_banners', ['id' => $banner->id]);
    }

    public function test_stock_policy_formatting_service(): void
    {
        $service = app(WebKonsumenService::class);

        // Mode 1: threshold_only
        $service->saveSettings([
            'stock_display_mode' => 'threshold_only',
            'stock_threshold_limit' => 5,
        ]);

        // Stock 3 <= 5 -> Terbatas / Sisa 3 pcs
        $lowStock = $service->formatStockDisplay(3);
        $this->assertEquals('Sisa 3 pcs', $lowStock['label']);
        $this->assertTrue($lowStock['is_low']);
        $this->assertTrue($lowStock['is_available']);

        // Stock 15 > 5 -> Stok Tersedia (Privasi nominal aman)
        $safeStock = $service->formatStockDisplay(15);
        $this->assertEquals('Stok Tersedia', $safeStock['label']);
        $this->assertFalse($safeStock['is_low']);

        // Stock 0 -> Stok Habis
        $outOfStock = $service->formatStockDisplay(0);
        $this->assertEquals('Stok Habis', $outOfStock['label']);
        $this->assertFalse($outOfStock['is_available']);

        // Mode 2: badge_only
        $service->saveSettings(['stock_display_mode' => 'badge_only']);
        $badgeOnly = $service->formatStockDisplay(2);
        $this->assertEquals('Stok Tersedia', $badgeOnly['label']);

        // Mode 3: exact_number
        $service->saveSettings(['stock_display_mode' => 'exact_number']);
        $exact = $service->formatStockDisplay(42);
        $this->assertEquals('Stok: 42 pcs', $exact['label']);
    }

    public function test_storefront_displays_updated_cms_settings(): void
    {
        $service = app(WebKonsumenService::class);
        $service->saveSettings([
            'topbar_active' => true,
            'topbar_text' => 'Pusat Sparepart Original Se-Indonesia',
            'hero_headline' => 'Solusi Layar & Baterai Presisi',
        ]);

        $response = $this->get('/shop');
        $response->assertStatus(200);
        $response->assertSee('Pusat Sparepart Original Se-Indonesia');
        $response->assertSee('Solusi Layar & Baterai Presisi');
    }

    public function test_can_update_hero_display_mode_and_overlay_settings(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(WebKonsumenSettings::class)
            ->set('heroForm.hero_display_mode', 'carousel_replace')
            ->set('heroForm.hero_bg_image', '/images/heroes/custom-lab.jpg')
            ->set('heroForm.hero_bg_overlay', 'scrim_heavy')
            ->call('save')
            ->assertDispatched('alert');

        $service = app(WebKonsumenService::class);
        $this->assertEquals('carousel_replace', $service->get('hero_display_mode'));
        $this->assertEquals('/images/heroes/custom-lab.jpg', $service->get('hero_bg_image'));
        $this->assertEquals('scrim_heavy', $service->get('hero_bg_overlay'));

        // Test removeHeroBg
        Livewire::actingAs($this->superAdmin)
            ->test(WebKonsumenSettings::class)
            ->set('heroForm.hero_bg_image', '/images/heroes/custom-lab.jpg')
            ->call('removeHeroBg')
            ->assertSet('heroForm.hero_bg_image', '')
            ->assertSet('uploadHeroBg', null);
    }

    public function test_storefront_renders_carousel_replace_mode(): void
    {
        WebBanner::create([
            'judul' => 'Special Spotlight Campaign 2026',
            'posisi' => 'hero_slider',
            'gambar_desktop' => '/images/banners/spotlight.jpg',
            'is_active' => true,
            'urutan' => 1,
        ]);

        $service = app(WebKonsumenService::class);
        $service->saveSettings([
            'hero_active' => true,
            'hero_display_mode' => 'carousel_replace',
        ]);

        $response = $this->get('/shop');
        $response->assertStatus(200);
        $response->assertSee('Special Spotlight Campaign 2026');
        $response->assertSee('aspect-[16/9]');
    }

    public function test_storefront_renders_hero_background_with_overlay(): void
    {
        $service = app(WebKonsumenService::class);
        $service->saveSettings([
            'hero_active' => true,
            'hero_display_mode' => 'hero_standard',
            'hero_bg_image' => '/storage/web-konsumen/test-hero-bg.jpg',
            'hero_bg_overlay' => 'glass_card',
            'hero_headline' => 'Headline Kontras Tinggi Toko',
        ]);

        $response = $this->get('/shop');
        $response->assertStatus(200);
        $response->assertSee('/storage/web-konsumen/test-hero-bg.jpg');
        $response->assertSee('Headline Kontras Tinggi Toko');
    }

    public function test_parsed_value_handles_string_json_gracefully_and_prevents_500(): void
    {
        // Simulasikan kondisi database prod di mana hero_features tersimpan dengan value_type = 'string'
        WebKonsumenSetting::where('setting_key', 'hero_features')->delete();
        WebKonsumenSetting::create([
            'group_key' => 'hero',
            'setting_key' => 'hero_features',
            'setting_value' => json_encode([
                ['icon' => 'qc', 'text' => 'Fitur QC Spesial Prod'],
            ]),
            'value_type' => 'string',
        ]);

        $service = app(WebKonsumenService::class);
        $service->clearCache();

        $features = $service->get('hero_features');
        $this->assertIsArray($features);
        $this->assertEquals('Fitur QC Spesial Prod', $features[0]['text']);

        // Pastikan halaman /shop tidak 500
        $response = $this->get('/shop');
        $response->assertStatus(200);
        $response->assertSee('Fitur QC Spesial Prod');

        // Pastikan saat diedit dan disave oleh Livewire, value_type otomatis menjadi 'json'
        Livewire::actingAs($this->superAdmin)
            ->test(WebKonsumenSettings::class)
            ->set('heroForm.hero_features.0.text', 'Fitur QC Terupdate')
            ->call('save')
            ->assertDispatched('alert');

        $this->assertDatabaseHas('web_konsumen_settings', [
            'setting_key' => 'hero_features',
            'value_type' => 'json',
        ]);
    }
}
