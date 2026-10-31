<?php

namespace App\Modules\Marketplace\Livewire;

use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Marketplace\Models\WebBanner;
use App\Modules\Marketplace\Services\WebKonsumenService;
use App\Modules\Rbac\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class WebKonsumenSettings extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $activeTab = 'hero'; // hero, branding, banner, stok, kontak, preview

    public string $previewDevice = 'desktop'; // desktop, mobile

    // Upload temporary files
    public $uploadLogo = null;

    public $uploadLoginBg = null;

    public $uploadHeroBg = null;

    public $uploadBannerDesktop = null;

    public $uploadBannerMobile = null;

    // Form states
    public array $brandingForm = [
        'store_logo_url' => '/icons/icon.svg',
        'topbar_active' => true,
        'topbar_text' => 'Garansi Resmi Toko & Kirim Cepat se-Indonesia',
        'topbar_url' => '',
        'customer_login_bg_image' => '',
        'customer_login_bg_overlay' => 'scrim_dark', // scrim_dark, scrim_heavy, frosted_glass
    ];

    public array $heroForm = [
        'hero_active' => true,
        'hero_display_mode' => 'hero_standard', // hero_standard, carousel_replace, hero_and_carousel
        'hero_bg_image' => '',
        'hero_bg_overlay' => 'scrim_dark', // scrim_dark, scrim_heavy, glass_card
        'hero_badge_text' => 'Standard Ute Prism™ Precision Parts',
        'hero_headline' => "Suku Cadang Presisi.\nGaransi Nyata Toko Resmi.",
        'hero_subheadline' => 'Layar LCD grade OEM, modul baterai densitas tinggi, hingga sparepart IC dengan kalibrasi uji pabrik.',
        'hero_right_widget' => 'member_card',
        'hero_features' => [],
        'hero_stats' => [],
    ];

    public array $stockForm = [
        'stock_display_mode' => 'threshold_only',
        'stock_threshold_limit' => 5,
        'show_branch_breakdown' => true,
        'show_sold_counter' => true,
        'show_tier_teaser' => true,
        'catalog_per_page' => 24,
        'allowed_filters' => [
            'enabled' => true,
            'kategori' => true,
            'kondisi' => true,
            'hp' => true,
            'brand' => true,
            'harga' => true,
            'kualitas' => true,
            'stok' => true,
        ],
    ];

    public array $contactForm = [
        'wa_floating_active' => true,
        'wa_number' => '6281234567890',
        'wa_default_msg' => 'Halo Admin, saya ingin tanya sparepart / produk...',
        'operating_hours' => 'Senin - Sabtu: 08:30 - 20:30 WIB',
        'social_links' => [
            'instagram' => '',
            'tiktok' => '',
            'shopee' => '',
            'tokopedia' => '',
        ],
        'popup_active' => false,
        'popup_banner_id' => '',
        'popup_frequency' => 'once_per_day',
    ];

    public array $paymentForm = [
        'payment_mp_duitku_enabled' => true,
        'payment_mp_qris_toko_enabled' => true,
    ];

    // Banner Modal State
    public bool $bannerModalOpen = false;

    public array $bannerForm = [
        'id' => null,
        'judul' => '',
        'badge_label' => '',
        'gambar_desktop' => '',
        'gambar_mobile' => '',
        'target_url' => '',
        'posisi' => 'hero_slider',
        'urutan' => 1,
        'is_active' => true,
        'tanggal_mulai' => null,
        'tanggal_selesai' => null,
    ];

    public function mount(WebKonsumenService $service): void
    {
        $settings = $service->getAll();

        // Populate Branding
        $this->brandingForm['store_logo_url'] = $settings['store_logo_url'] ?? '/icons/icon.svg';
        $this->brandingForm['topbar_active'] = (bool) ($settings['topbar_active'] ?? true);
        $this->brandingForm['topbar_text'] = $settings['topbar_text'] ?? '';
        $this->brandingForm['topbar_url'] = $settings['topbar_url'] ?? '';
        $this->brandingForm['customer_login_bg_image'] = $settings['customer_login_bg_image'] ?? '';
        $this->brandingForm['customer_login_bg_overlay'] = $settings['customer_login_bg_overlay'] ?? 'scrim_dark';

        // Helper defensive parsing array
        $asArray = function ($val, array $default): array {
            if (is_array($val)) {
                return $val;
            }
            if (is_string($val) && $val !== '') {
                $decoded = json_decode($val, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }

            return $default;
        };

        // Populate Hero
        $this->heroForm['hero_active'] = (bool) ($settings['hero_active'] ?? true);
        $this->heroForm['hero_display_mode'] = $settings['hero_display_mode'] ?? 'hero_standard';
        $this->heroForm['hero_bg_image'] = $settings['hero_bg_image'] ?? '';
        $this->heroForm['hero_bg_overlay'] = $settings['hero_bg_overlay'] ?? 'scrim_dark';
        $this->heroForm['hero_badge_text'] = $settings['hero_badge_text'] ?? '';
        $this->heroForm['hero_headline'] = $settings['hero_headline'] ?? '';
        $this->heroForm['hero_subheadline'] = $settings['hero_subheadline'] ?? '';
        $this->heroForm['hero_right_widget'] = $settings['hero_right_widget'] ?? 'member_card';
        $this->heroForm['hero_features'] = $asArray($settings['hero_features'] ?? null, [
            ['icon' => 'qc', 'text' => '100% Diagnostic QC Passed'],
            ['icon' => 'shield', 'text' => 'Garansi Ganti Unit'],
            ['icon' => 'clock', 'text' => 'Ambil Cepat di Cabang'],
        ]);
        $this->heroForm['hero_stats'] = $asArray($settings['hero_stats'] ?? null, [
            ['label' => 'Item Terkatalog', 'type' => 'dynamic_catalog', 'val' => ''],
            ['label' => 'Pick-up Center', 'type' => 'fixed', 'val' => '5 Cabang'],
            ['label' => 'Kepuasan', 'type' => 'fixed', 'val' => '99.4%'],
        ]);

        // Populate Stock & Catalog
        $this->stockForm['stock_display_mode'] = $settings['stock_display_mode'] ?? 'threshold_only';
        $this->stockForm['stock_threshold_limit'] = (int) ($settings['stock_threshold_limit'] ?? 5);
        $this->stockForm['show_branch_breakdown'] = (bool) ($settings['show_branch_breakdown'] ?? true);
        $this->stockForm['show_sold_counter'] = (bool) ($settings['show_sold_counter'] ?? true);
        $this->stockForm['show_tier_teaser'] = (bool) ($settings['show_tier_teaser'] ?? true);
        $this->stockForm['catalog_per_page'] = (int) ($settings['catalog_per_page'] ?? 24);
        $allowedFilters = $asArray($settings['allowed_filters'] ?? null, []);
        if (! empty($allowedFilters)) {
            $this->stockForm['allowed_filters'] = array_merge($this->stockForm['allowed_filters'], $allowedFilters);
        }

        // Populate Contact & Popup
        $this->contactForm['wa_floating_active'] = (bool) ($settings['wa_floating_active'] ?? true);
        $this->contactForm['wa_number'] = $settings['wa_number'] ?? '';
        $this->contactForm['wa_default_msg'] = $settings['wa_default_msg'] ?? '';
        $this->contactForm['operating_hours'] = $settings['operating_hours'] ?? '';
        $socialLinks = $asArray($settings['social_links'] ?? null, []);
        if (! empty($socialLinks)) {
            $this->contactForm['social_links'] = array_merge($this->contactForm['social_links'], $socialLinks);
        }
        $this->contactForm['popup_active'] = (bool) ($settings['popup_active'] ?? false);
        $this->contactForm['popup_banner_id'] = (string) ($settings['popup_banner_id'] ?? '');
        $this->contactForm['popup_frequency'] = $settings['popup_frequency'] ?? 'once_per_day';

        // Populate Payment Options
        $crmConfig = app(KonfigurasiService::class);
        $this->paymentForm['payment_mp_duitku_enabled'] = (bool) ($settings['payment_mp_duitku_enabled'] ?? $crmConfig->get('payment_mp_duitku_enabled', true));
        $this->paymentForm['payment_mp_qris_toko_enabled'] = (bool) ($settings['payment_mp_qris_toko_enabled'] ?? $crmConfig->get('payment_mp_qris_toko_enabled', true));
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function setDevice(string $device): void
    {
        $this->previewDevice = $device;
    }

    public function removeHeroBg(): void
    {
        $this->heroForm['hero_bg_image'] = '';
        $this->uploadHeroBg = null;
    }

    public function removeLoginBg(): void
    {
        $this->brandingForm['customer_login_bg_image'] = '';
        $this->uploadLoginBg = null;
    }

    public function save(WebKonsumenService $service, AuditService $audit): void
    {
        // Handle upload logo bila ada
        if ($this->uploadLogo) {
            $path = $this->uploadLogo->store('web-konsumen', 'public');
            $this->brandingForm['store_logo_url'] = '/storage/'.$path;
            $this->uploadLogo = null;
        }

        // Handle upload customer login background bila ada
        if ($this->uploadLoginBg) {
            $path = $this->uploadLoginBg->store('web-konsumen', 'public');
            $this->brandingForm['customer_login_bg_image'] = '/storage/'.$path;
            $this->uploadLoginBg = null;
        }

        // Handle upload hero background bila ada
        if ($this->uploadHeroBg) {
            $path = $this->uploadHeroBg->store('web-konsumen', 'public');
            $this->heroForm['hero_bg_image'] = '/storage/'.$path;
            $this->uploadHeroBg = null;
        }

        $before = $service->getAll();

        $payload = [
            // Branding
            'store_logo_url' => $this->brandingForm['store_logo_url'],
            'topbar_active' => $this->brandingForm['topbar_active'],
            'topbar_text' => $this->brandingForm['topbar_text'],
            'topbar_url' => $this->brandingForm['topbar_url'],
            'customer_login_bg_image' => $this->brandingForm['customer_login_bg_image'],
            'customer_login_bg_overlay' => $this->brandingForm['customer_login_bg_overlay'],

            // Hero
            'hero_active' => $this->heroForm['hero_active'],
            'hero_display_mode' => $this->heroForm['hero_display_mode'],
            'hero_bg_image' => $this->heroForm['hero_bg_image'],
            'hero_bg_overlay' => $this->heroForm['hero_bg_overlay'],
            'hero_badge_text' => $this->heroForm['hero_badge_text'],
            'hero_headline' => $this->heroForm['hero_headline'],
            'hero_subheadline' => $this->heroForm['hero_subheadline'],
            'hero_right_widget' => $this->heroForm['hero_right_widget'],
            'hero_features' => $this->heroForm['hero_features'],
            'hero_stats' => $this->heroForm['hero_stats'],

            // Stock
            'stock_display_mode' => $this->stockForm['stock_display_mode'],
            'stock_threshold_limit' => $this->stockForm['stock_threshold_limit'],
            'show_branch_breakdown' => $this->stockForm['show_branch_breakdown'],
            'show_sold_counter' => $this->stockForm['show_sold_counter'],

            // Catalog
            'show_tier_teaser' => $this->stockForm['show_tier_teaser'],
            'catalog_per_page' => $this->stockForm['catalog_per_page'],
            'allowed_filters' => $this->stockForm['allowed_filters'],

            // Contact & Popup
            'wa_floating_active' => $this->contactForm['wa_floating_active'],
            'wa_number' => $this->contactForm['wa_number'],
            'wa_default_msg' => $this->contactForm['wa_default_msg'],
            'operating_hours' => $this->contactForm['operating_hours'],
            'social_links' => $this->contactForm['social_links'],
            'popup_active' => $this->contactForm['popup_active'],
            'popup_banner_id' => $this->contactForm['popup_banner_id'],
            'popup_frequency' => $this->contactForm['popup_frequency'],

            // Pembayaran MP Toko
            'payment_mp_duitku_enabled' => (bool) $this->paymentForm['payment_mp_duitku_enabled'],
            'payment_mp_qris_toko_enabled' => (bool) $this->paymentForm['payment_mp_qris_toko_enabled'],
        ];

        $service->saveSettings($payload, Auth::id());

        // Sinkronisasi ke KonfigurasiService
        $crmConfig = app(KonfigurasiService::class);
        $crmConfig->set('payment_mp_duitku_enabled', (bool) $this->paymentForm['payment_mp_duitku_enabled'], 'Aktivasi Gateway Duitku MP');
        $crmConfig->set('payment_mp_qris_toko_enabled', (bool) $this->paymentForm['payment_mp_qris_toko_enabled'], 'Aktivasi QRIS Dinamis Toko MP');

        // Catat Audit Log
        $after = $service->getAll();
        $audit->catat(
            'WebKonsumenSetting',
            'update',
            null,
            'Memperbarui konfigurasi CMS Web Konsumen',
            $before,
            $after
        );

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Konfigurasi web konsumen berhasil disimpan dan cache diperbarui!',
        ]);
    }

    // Banner Actions
    public function openCreateBanner(): void
    {
        $this->bannerForm = [
            'id' => null,
            'judul' => '',
            'badge_label' => '',
            'gambar_desktop' => '',
            'gambar_mobile' => '',
            'target_url' => '/shop',
            'posisi' => 'hero_slider',
            'urutan' => WebBanner::count() + 1,
            'is_active' => true,
            'tanggal_mulai' => null,
            'tanggal_selesai' => null,
        ];
        $this->uploadBannerDesktop = null;
        $this->uploadBannerMobile = null;
        $this->bannerModalOpen = true;
    }

    public function openEditBanner(int $id): void
    {
        $banner = WebBanner::findOrFail($id);
        $this->bannerForm = [
            'id' => $banner->id,
            'judul' => $banner->judul,
            'badge_label' => $banner->badge_label ?? '',
            'gambar_desktop' => $banner->gambar_desktop,
            'gambar_mobile' => $banner->gambar_mobile ?? '',
            'target_url' => $banner->target_url ?? '',
            'posisi' => $banner->posisi,
            'urutan' => $banner->urutan,
            'is_active' => (bool) $banner->is_active,
            'tanggal_mulai' => $banner->tanggal_mulai?->format('Y-m-d\TH:i'),
            'tanggal_selesai' => $banner->tanggal_selesai?->format('Y-m-d\TH:i'),
        ];
        $this->uploadBannerDesktop = null;
        $this->uploadBannerMobile = null;
        $this->bannerModalOpen = true;
    }

    public function saveBanner(WebKonsumenService $service): void
    {
        $this->validate([
            'bannerForm.judul' => 'required|string|max:150',
            'bannerForm.posisi' => 'required|in:hero_slider,hero_side,mid_catalog,popup_modal',
            'bannerForm.urutan' => 'required|integer|min:0',
            'uploadBannerDesktop' => $this->bannerForm['id'] ? 'nullable|image|max:2048' : ($this->bannerForm['gambar_desktop'] ? 'nullable' : 'required|image|max:2048'),
            'uploadBannerMobile' => 'nullable|image|max:2048',
        ]);

        if ($this->uploadBannerDesktop) {
            $path = $this->uploadBannerDesktop->store('web-konsumen/banners', 'public');
            $this->bannerForm['gambar_desktop'] = '/storage/'.$path;
        }

        if ($this->uploadBannerMobile) {
            $path = $this->uploadBannerMobile->store('web-konsumen/banners', 'public');
            $this->bannerForm['gambar_mobile'] = '/storage/'.$path;
        }

        $data = [
            'judul' => $this->bannerForm['judul'],
            'badge_label' => $this->bannerForm['badge_label'] ?: null,
            'gambar_desktop' => $this->bannerForm['gambar_desktop'],
            'gambar_mobile' => $this->bannerForm['gambar_mobile'] ?: null,
            'target_url' => $this->bannerForm['target_url'] ?: null,
            'posisi' => $this->bannerForm['posisi'],
            'urutan' => (int) $this->bannerForm['urutan'],
            'is_active' => (bool) $this->bannerForm['is_active'],
            'tanggal_mulai' => $this->bannerForm['tanggal_mulai'] ?: null,
            'tanggal_selesai' => $this->bannerForm['tanggal_selesai'] ?: null,
        ];

        if ($this->bannerForm['id']) {
            WebBanner::where('id', $this->bannerForm['id'])->update($data);
        } else {
            WebBanner::create($data);
        }

        $service->clearCache();
        $this->bannerModalOpen = false;

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Banner promosi berhasil disimpan!',
        ]);
    }

    public function toggleBannerStatus(int $id, WebKonsumenService $service): void
    {
        $banner = WebBanner::findOrFail($id);
        $banner->update(['is_active' => ! $banner->is_active]);
        $service->clearCache();

        $this->dispatch('alert', [
            'type' => 'info',
            'message' => 'Status banner berhasil diubah.',
        ]);
    }

    public function deleteBanner(int $id, WebKonsumenService $service): void
    {
        $banner = WebBanner::findOrFail($id);
        $banner->delete();
        $service->clearCache();

        $this->dispatch('alert', [
            'type' => 'warning',
            'message' => 'Banner berhasil dihapus.',
        ]);
    }

    public function render()
    {
        $banners = WebBanner::orderBy('posisi')
            ->orderBy('urutan')
            ->orderByDesc('id')
            ->get();

        return view('modules.marketplace.livewire.web-konsumen-settings', [
            'banners' => $banners,
        ])->layout('layouts.backoffice', ['title' => 'Pengaturan Web Konsumen']);
    }
}
