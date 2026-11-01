<?php

namespace App\Modules\Marketplace\Livewire;

use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Marketplace\Models\WebArticle;
use App\Modules\Marketplace\Models\WebBanner;
use App\Modules\Marketplace\Models\WebFaq;
use App\Modules\Marketplace\Models\WebReview;
use App\Modules\Marketplace\Services\WebKonsumenService;
use App\Modules\Rbac\Services\AuditService;
use App\Modules\Servis\Models\JenisServis;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Services\SmartSearchService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class WebKonsumenSettings extends Component
{
    use WithFileUploads;
    use WithPagination;

    public const VALID_TABS = ['hero', 'branding', 'banner', 'stok', 'panduan', 'review', 'faq', 'kontak', 'pembayaran', 'preview'];

    public string $activeTab = 'hero'; // hero, branding, banner, stok, panduan, review, faq, kontak, pembayaran, preview

    public string $previewDevice = 'desktop'; // desktop, mobile

    // Upload temporary files
    public $uploadLogo = null;

    public $uploadLoginBg = null;

    public $uploadHeroBg = null;

    public $uploadBannerDesktop = null;

    public $uploadBannerMobile = null;

    public $uploadBannerVideoDesktop = null;

    public $uploadBannerVideoMobile = null;

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
        'hero_badge_text' => 'Official Quality Parts & Repair Center',
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
        'tipe_media' => 'image', // image, gif, video
        'gambar_desktop' => '',
        'gambar_mobile' => '',
        'video_desktop' => '',
        'video_mobile' => '',
        'target_url' => '',
        'posisi' => 'hero_slider',
        'urutan' => 1,
        'is_active' => true,
        'tanggal_mulai' => null,
        'tanggal_selesai' => null,
    ];

    // Knowledge Base / Panduan State
    public bool $articleModalOpen = false;

    public string $articleSearch = '';

    public string $articleKategoriFilter = '';

    public $uploadArticleCover = null;

    public $uploadArticleVideo = null;

    // Smart Search untuk Part & Jasa di Modal Artikel
    public string $partSearchQuery = '';

    public string $servisSearchQuery = '';

    public array $articleForm = [
        'id' => null,
        'judul' => '',
        'slug' => '',
        'kategori' => 'panduan_servis',
        'ringkasan' => '',
        'konten_lengkap' => '',
        'tipe_media_cover' => 'image', // image, gif, video
        'cover_image' => '',
        'cover_video' => '',
        'youtube_url' => '',
        'produk_terkait_ids' => [],
        'rekomendasi_servis_id' => null,
        'meta_title' => '',
        'meta_description' => '',
        'is_published' => true,
    ];

    // Review & Moderasi State
    public string $reviewFilter = 'all'; // all, pending, approved, featured

    public string $reviewTipe = 'all'; // all, produk, servis

    public bool $tanggapanModalOpen = false;

    public ?int $selectedReviewId = null;

    public string $adminTanggapanText = '';

    // FAQ State
    public bool $faqModalOpen = false;

    public array $faqForm = [
        'id' => null,
        'kategori' => 'garansi',
        'pertanyaan' => '',
        'jawaban' => '',
        'urutan' => 1,
        'is_active' => true,
    ];

    public function mount(WebKonsumenService $service, ?string $tab = null): void
    {
        $tabParam = $tab ?: request('tab');
        if ($tabParam && in_array($tabParam, self::VALID_TABS, true)) {
            $this->activeTab = $tabParam;
        }

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
        if (! in_array($tab, self::VALID_TABS, true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->dispatch('tab-changed', tab: $tab, url: url('/app/pengaturan/web-konsumen/'.$tab));
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
            'tipe_media' => 'image',
            'gambar_desktop' => '',
            'gambar_mobile' => '',
            'video_desktop' => '',
            'video_mobile' => '',
            'target_url' => '/shop',
            'posisi' => 'hero_slider',
            'urutan' => WebBanner::count() + 1,
            'is_active' => true,
            'tanggal_mulai' => null,
            'tanggal_selesai' => null,
        ];
        $this->uploadBannerDesktop = null;
        $this->uploadBannerMobile = null;
        $this->uploadBannerVideoDesktop = null;
        $this->uploadBannerVideoMobile = null;
        $this->bannerModalOpen = true;
    }

    public function openEditBanner(int $id): void
    {
        $banner = WebBanner::findOrFail($id);
        $this->bannerForm = [
            'id' => $banner->id,
            'judul' => $banner->judul,
            'badge_label' => $banner->badge_label ?? '',
            'tipe_media' => $banner->tipe_media ?? 'image',
            'gambar_desktop' => $banner->gambar_desktop,
            'gambar_mobile' => $banner->gambar_mobile ?? '',
            'video_desktop' => $banner->video_desktop ?? '',
            'video_mobile' => $banner->video_mobile ?? '',
            'target_url' => $banner->target_url ?? '',
            'posisi' => $banner->posisi,
            'urutan' => $banner->urutan,
            'is_active' => (bool) $banner->is_active,
            'tanggal_mulai' => $banner->tanggal_mulai?->format('Y-m-d\TH:i'),
            'tanggal_selesai' => $banner->tanggal_selesai?->format('Y-m-d\TH:i'),
        ];
        $this->uploadBannerDesktop = null;
        $this->uploadBannerMobile = null;
        $this->uploadBannerVideoDesktop = null;
        $this->uploadBannerVideoMobile = null;
        $this->bannerModalOpen = true;
    }

    public function saveBanner(WebKonsumenService $service): void
    {
        $this->validate([
            'bannerForm.judul' => 'required|string|max:150',
            'bannerForm.tipe_media' => 'required|in:image,gif,video',
            'bannerForm.posisi' => 'required|in:hero_slider,hero_side,mid_catalog,popup_modal',
            'bannerForm.urutan' => 'required|integer|min:0',
            'uploadBannerDesktop' => $this->bannerForm['id'] ? 'nullable|mimes:jpeg,png,jpg,webp,gif|max:8192' : ($this->bannerForm['gambar_desktop'] ? 'nullable' : 'required|mimes:jpeg,png,jpg,webp,gif|max:8192'),
            'uploadBannerMobile' => 'nullable|mimes:jpeg,png,jpg,webp,gif|max:8192',
            'uploadBannerVideoDesktop' => 'nullable|mimes:mp4,webm,ogg|max:15360',
            'uploadBannerVideoMobile' => 'nullable|mimes:mp4,webm,ogg|max:15360',
        ]);

        if ($this->uploadBannerDesktop) {
            $path = $this->uploadBannerDesktop->store('web-konsumen/banners', 'public');
            $this->bannerForm['gambar_desktop'] = '/storage/'.$path;
        }

        if ($this->uploadBannerMobile) {
            $path = $this->uploadBannerMobile->store('web-konsumen/banners', 'public');
            $this->bannerForm['gambar_mobile'] = '/storage/'.$path;
        }

        if ($this->uploadBannerVideoDesktop) {
            $path = $this->uploadBannerVideoDesktop->store('web-konsumen/videos', 'public');
            $this->bannerForm['video_desktop'] = '/storage/'.$path;
        }

        if ($this->uploadBannerVideoMobile) {
            $path = $this->uploadBannerVideoMobile->store('web-konsumen/videos', 'public');
            $this->bannerForm['video_mobile'] = '/storage/'.$path;
        }

        $data = [
            'judul' => $this->bannerForm['judul'],
            'badge_label' => $this->bannerForm['badge_label'] ?: null,
            'tipe_media' => $this->bannerForm['tipe_media'],
            'gambar_desktop' => $this->bannerForm['gambar_desktop'],
            'gambar_mobile' => $this->bannerForm['gambar_mobile'] ?: null,
            'video_desktop' => $this->bannerForm['video_desktop'] ?: null,
            'video_mobile' => $this->bannerForm['video_mobile'] ?: null,
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

    // Knowledge Base / Panduan Actions
    public function openCreateArticle(): void
    {
        $this->articleForm = [
            'id' => null,
            'judul' => '',
            'slug' => '',
            'kategori' => 'panduan_servis',
            'ringkasan' => '',
            'konten_lengkap' => '',
            'tipe_media_cover' => 'image',
            'cover_image' => '',
            'cover_video' => '',
            'youtube_url' => '',
            'produk_terkait_ids' => [],
            'rekomendasi_servis_id' => null,
            'meta_title' => '',
            'meta_description' => '',
            'is_published' => true,
        ];
        $this->uploadArticleCover = null;
        $this->uploadArticleVideo = null;
        $this->partSearchQuery = '';
        $this->servisSearchQuery = '';
        $this->articleModalOpen = true;
    }

    public function openEditArticle(int $id): void
    {
        $article = WebArticle::findOrFail($id);
        $this->articleForm = [
            'id' => $article->id,
            'judul' => $article->judul,
            'slug' => $article->slug,
            'kategori' => $article->kategori,
            'ringkasan' => $article->ringkasan,
            'konten_lengkap' => $article->konten_lengkap,
            'tipe_media_cover' => $article->tipe_media_cover ?? 'image',
            'cover_image' => $article->cover_image ?? '',
            'cover_video' => $article->cover_video ?? '',
            'youtube_url' => $article->youtube_url ?? '',
            'produk_terkait_ids' => $article->produk_terkait_ids ?? [],
            'rekomendasi_servis_id' => $article->rekomendasi_servis_id,
            'meta_title' => $article->meta_title ?? '',
            'meta_description' => $article->meta_description ?? '',
            'is_published' => (bool) $article->is_published,
        ];
        $this->uploadArticleCover = null;
        $this->uploadArticleVideo = null;
        $this->partSearchQuery = '';
        $this->servisSearchQuery = '';
        $this->articleModalOpen = true;
    }

    public function toggleProdukTerkait(int $produkId): void
    {
        $current = (array) ($this->articleForm['produk_terkait_ids'] ?? []);
        if (in_array($produkId, $current, true)) {
            $this->articleForm['produk_terkait_ids'] = array_values(array_filter($current, fn ($id) => (int) $id !== $produkId));
        } else {
            $current[] = $produkId;
            $this->articleForm['produk_terkait_ids'] = array_values(array_unique($current));
        }
    }

    public function saveArticle(): void
    {
        $this->validate([
            'articleForm.judul' => 'required|string|max:200',
            'articleForm.kategori' => 'required|string|max:50',
            'articleForm.ringkasan' => 'required|string|max:300',
            'articleForm.konten_lengkap' => 'required|string',
            'uploadArticleCover' => 'nullable|mimes:jpeg,png,jpg,webp,gif|max:8192',
            'uploadArticleVideo' => 'nullable|mimes:mp4,webm,ogg|max:15360',
        ]);

        $slug = trim((string) $this->articleForm['slug']);
        if ($slug === '') {
            $slug = Str::slug($this->articleForm['judul']);
        } else {
            $slug = Str::slug($slug);
        }

        // Pastikan slug unik
        $querySlug = WebArticle::where('slug', $slug);
        if ($this->articleForm['id']) {
            $querySlug->where('id', '!=', $this->articleForm['id']);
        }
        if ($querySlug->exists()) {
            $slug .= '-'.substr(md5((string) mt_rand()), 0, 4);
        }

        if ($this->uploadArticleCover) {
            $path = $this->uploadArticleCover->store('web-konsumen/articles', 'public');
            $this->articleForm['cover_image'] = '/storage/'.$path;
        }

        if ($this->uploadArticleVideo) {
            $path = $this->uploadArticleVideo->store('web-konsumen/articles', 'public');
            $this->articleForm['cover_video'] = '/storage/'.$path;
        }

        $youtubeId = WebArticle::extractYouTubeId($this->articleForm['youtube_url'] ?: null);

        $data = [
            'judul' => $this->articleForm['judul'],
            'slug' => $slug,
            'kategori' => $this->articleForm['kategori'],
            'ringkasan' => $this->articleForm['ringkasan'],
            'konten_lengkap' => $this->articleForm['konten_lengkap'],
            'tipe_media_cover' => $this->articleForm['tipe_media_cover'],
            'cover_image' => $this->articleForm['cover_image'] ?: null,
            'cover_video' => $this->articleForm['cover_video'] ?: null,
            'youtube_url' => $this->articleForm['youtube_url'] ?: null,
            'youtube_video_id' => $youtubeId,
            'produk_terkait_ids' => ! empty($this->articleForm['produk_terkait_ids']) ? array_values(array_map('intval', (array) $this->articleForm['produk_terkait_ids'])) : null,
            'rekomendasi_servis_id' => $this->articleForm['rekomendasi_servis_id'] ?: null,
            'meta_title' => $this->articleForm['meta_title'] ?: null,
            'meta_description' => $this->articleForm['meta_description'] ?: null,
            'penulis_id' => Auth::id(),
            'is_published' => (bool) $this->articleForm['is_published'],
            'published_at' => $this->articleForm['is_published'] ? now() : null,
        ];

        if ($this->articleForm['id']) {
            WebArticle::where('id', $this->articleForm['id'])->update($data);
        } else {
            WebArticle::create($data);
        }

        $this->articleModalOpen = false;

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Artikel panduan berhasil disimpan!',
        ]);
    }

    public function togglePublishArticle(int $id): void
    {
        $article = WebArticle::findOrFail($id);
        $newState = ! $article->is_published;
        $article->update([
            'is_published' => $newState,
            'published_at' => $newState ? now() : $article->published_at,
        ]);

        $this->dispatch('alert', [
            'type' => 'info',
            'message' => $newState ? 'Artikel berhasil dipublikasikan.' : 'Artikel ditarik dari publikasi.',
        ]);
    }

    public function deleteArticle(int $id): void
    {
        $article = WebArticle::findOrFail($id);
        $article->delete();

        $this->dispatch('alert', [
            'type' => 'warning',
            'message' => 'Artikel panduan berhasil dihapus.',
        ]);
    }

    // Review & Moderasi Actions
    public function approveReview(int $id): void
    {
        $review = WebReview::findOrFail($id);
        $review->update([
            'is_approved' => true,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Ulasan disetujui dan kini tampil di web konsumen.',
        ]);
    }

    public function rejectReview(int $id): void
    {
        $review = WebReview::findOrFail($id);
        $review->update([
            'is_approved' => false,
        ]);

        $this->dispatch('alert', [
            'type' => 'info',
            'message' => 'Ulasan disembunyikan dari publik.',
        ]);
    }

    public function toggleFeaturedReview(int $id): void
    {
        $review = WebReview::findOrFail($id);
        $review->update([
            'is_featured' => ! $review->is_featured,
            'is_approved' => true,
        ]);

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => $review->is_featured ? 'Ulasan disematkan sebagai featured testimoni.' : 'Sematkan ulasan dinonaktifkan.',
        ]);
    }

    public function openTanggapanModal(int $id): void
    {
        $review = WebReview::findOrFail($id);
        $this->selectedReviewId = $review->id;
        $this->adminTanggapanText = $review->tanggapan_admin ?? '';
        $this->tanggapanModalOpen = true;
    }

    public function saveTanggapanReview(): void
    {
        if (! $this->selectedReviewId) {
            return;
        }

        $review = WebReview::findOrFail($this->selectedReviewId);
        $review->update([
            'tanggapan_admin' => trim($this->adminTanggapanText) ?: null,
            'approved_by' => Auth::id(),
            'approved_at' => $review->approved_at ?: now(),
        ]);

        $this->tanggapanModalOpen = false;

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Tanggapan resmi toko berhasil disimpan!',
        ]);
    }

    public function deleteReview(int $id): void
    {
        $review = WebReview::findOrFail($id);
        $review->delete();

        $this->dispatch('alert', [
            'type' => 'warning',
            'message' => 'Ulasan berhasil dihapus.',
        ]);
    }

    // FAQ Actions
    public function openCreateFaq(): void
    {
        $this->faqForm = [
            'id' => null,
            'kategori' => 'garansi',
            'pertanyaan' => '',
            'jawaban' => '',
            'urutan' => WebFaq::count() + 1,
            'is_active' => true,
        ];
        $this->faqModalOpen = true;
    }

    public function openEditFaq(int $id): void
    {
        $faq = WebFaq::findOrFail($id);
        $this->faqForm = [
            'id' => $faq->id,
            'kategori' => $faq->kategori,
            'pertanyaan' => $faq->pertanyaan,
            'jawaban' => $faq->jawaban,
            'urutan' => $faq->urutan,
            'is_active' => (bool) $faq->is_active,
        ];
        $this->faqModalOpen = true;
    }

    public function saveFaq(): void
    {
        $this->validate([
            'faqForm.pertanyaan' => 'required|string|max:255',
            'faqForm.jawaban' => 'required|string',
            'faqForm.kategori' => 'required|string|max:50',
            'faqForm.urutan' => 'required|integer|min:0',
        ]);

        $data = [
            'kategori' => $this->faqForm['kategori'],
            'pertanyaan' => $this->faqForm['pertanyaan'],
            'jawaban' => $this->faqForm['jawaban'],
            'urutan' => (int) $this->faqForm['urutan'],
            'is_active' => (bool) $this->faqForm['is_active'],
        ];

        if ($this->faqForm['id']) {
            WebFaq::where('id', $this->faqForm['id'])->update($data);
        } else {
            WebFaq::create($data);
        }

        $this->faqModalOpen = false;

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'FAQ berhasil disimpan!',
        ]);
    }

    public function toggleFaqStatus(int $id): void
    {
        $faq = WebFaq::findOrFail($id);
        $faq->update(['is_active' => ! $faq->is_active]);

        $this->dispatch('alert', [
            'type' => 'info',
            'message' => 'Status FAQ berhasil diperbarui.',
        ]);
    }

    public function deleteFaq(int $id): void
    {
        $faq = WebFaq::findOrFail($id);
        $faq->delete();

        $this->dispatch('alert', [
            'type' => 'warning',
            'message' => 'FAQ berhasil dihapus.',
        ]);
    }

    public function render()
    {
        $banners = WebBanner::orderBy('posisi')
            ->orderBy('urutan')
            ->orderByDesc('id')
            ->get();

        // Articles Query
        $articlesQuery = WebArticle::with('penulis')->latest();
        if ($this->articleSearch !== '') {
            $articlesQuery->where(function ($q) {
                $q->where('judul', 'like', '%'.$this->articleSearch.'%')
                    ->orWhere('ringkasan', 'like', '%'.$this->articleSearch.'%');
            });
        }
        if ($this->articleKategoriFilter !== '') {
            $articlesQuery->where('kategori', $this->articleKategoriFilter);
        }
        $articles = $articlesQuery->paginate(12, ['*'], 'articlesPage');

        // Reviews Query
        $reviewsQuery = WebReview::with(['pelanggan', 'produk', 'tiketServis'])->latest();
        if ($this->reviewFilter === 'pending') {
            $reviewsQuery->where('is_approved', false);
        } elseif ($this->reviewFilter === 'approved') {
            $reviewsQuery->where('is_approved', true);
        } elseif ($this->reviewFilter === 'featured') {
            $reviewsQuery->where('is_featured', true);
        }
        if ($this->reviewTipe !== 'all') {
            $reviewsQuery->where('tipe_ulasan', $this->reviewTipe);
        }
        $reviews = $reviewsQuery->paginate(15, ['*'], 'reviewsPage');

        // FAQs Query
        $faqs = WebFaq::orderBy('kategori')->orderBy('urutan')->get();

        // Autocomplete Options menggunakan SmartSearchService engine terpusat
        $smartSearch = app(SmartSearchService::class);

        $produkQuery = Produk::where('is_active', true)->select('id', 'nama', 'kode_produk', 'harga_jual_retail', 'foto_utama');
        if (trim($this->partSearchQuery) !== '') {
            $smartSearch->applySmartSearch($produkQuery, $this->partSearchQuery);
        }
        $produkList = $produkQuery->limit(20)->get();

        // Ambil produk terpilih jika belum masuk di list
        $selectedIds = (array) ($this->articleForm['produk_terkait_ids'] ?? []);
        if (! empty($selectedIds)) {
            $selectedProduks = Produk::whereIn('id', $selectedIds)->select('id', 'nama', 'kode_produk', 'harga_jual_retail', 'foto_utama')->get();
            $produkList = $selectedProduks->merge($produkList)->unique('id')->values();
        }

        // Jenis Servis Query
        $servisQuery = JenisServis::where('is_active', true)->select('id', 'nama', 'kategori', 'biaya_jasa');
        if (trim($this->servisSearchQuery) !== '') {
            $sq = trim($this->servisSearchQuery);
            $servisQuery->where(function ($q) use ($sq) {
                $q->where('nama', 'like', "%{$sq}%")
                    ->orWhere('kategori', 'like', "%{$sq}%");
            });
        }
        $jenisServisList = $servisQuery->orderBy('nama')->limit(20)->get();

        // Daftar kategori artikel yang pernah dibuat (dinamis & fleksibel)
        $existingCategories = WebArticle::distinct()->pluck('kategori')->filter()->values()->all();
        $defaultCategories = ['panduan_servis', 'edukasi_part', 'tips_trik', 'diagnosa_alat', 'troubleshooting', 'unboxing_review'];
        $allCategories = array_values(array_unique(array_merge($defaultCategories, $existingCategories)));

        return view('modules.marketplace.livewire.web-konsumen-settings', [
            'banners' => $banners,
            'articles' => $articles,
            'reviews' => $reviews,
            'faqs' => $faqs,
            'produkList' => $produkList,
            'jenisServisList' => $jenisServisList,
            'allCategories' => $allCategories,
        ])->layout('layouts.backoffice', ['title' => 'Pengaturan Web Konsumen']);
    }
}
