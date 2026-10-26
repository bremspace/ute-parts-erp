<?php

namespace Database\Seeders;

use App\Modules\Marketplace\Models\WebBanner;
use App\Modules\Marketplace\Models\WebKonsumenSetting;
use Illuminate\Database\Seeder;

class WebKonsumenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // Branding
            [
                'group_key' => 'branding',
                'setting_key' => 'store_logo_url',
                'setting_value' => '/icons/icon.svg',
                'value_type' => 'image',
                'deskripsi' => 'URL logo toko resmi',
            ],
            [
                'group_key' => 'branding',
                'setting_key' => 'topbar_text',
                'setting_value' => 'Garansi Resmi Toko & Kirim Cepat se-Indonesia',
                'value_type' => 'string',
                'deskripsi' => 'Teks pengumuman di baris paling atas (marquee/header banner)',
            ],
            [
                'group_key' => 'branding',
                'setting_key' => 'topbar_url',
                'setting_value' => '',
                'value_type' => 'string',
                'deskripsi' => 'Tautan pengumuman bar atas',
            ],
            [
                'group_key' => 'branding',
                'setting_key' => 'topbar_active',
                'setting_value' => 'true',
                'value_type' => 'boolean',
                'deskripsi' => 'Tampilkan atau sembunyikan baris pengumuman atas',
            ],
            [
                'group_key' => 'branding',
                'setting_key' => 'customer_login_bg_image',
                'setting_value' => '',
                'value_type' => 'string',
                'deskripsi' => 'URL foto background custom halaman login pelanggan',
            ],
            [
                'group_key' => 'branding',
                'setting_key' => 'customer_login_bg_overlay',
                'setting_value' => 'scrim_dark',
                'value_type' => 'string',
                'deskripsi' => 'Tipe scrim overlay latar belakang login konsumen: scrim_dark, scrim_heavy, atau frosted_glass',
            ],

            // Hero Section
            [
                'group_key' => 'hero',
                'setting_key' => 'hero_active',
                'setting_value' => 'true',
                'value_type' => 'boolean',
                'deskripsi' => 'Tampilkan atau sembunyikan hero section utama',
            ],
            [
                'group_key' => 'hero',
                'setting_key' => 'hero_badge_text',
                'setting_value' => 'Standard Ute Prism™ Precision Parts',
                'value_type' => 'string',
                'deskripsi' => 'Label badge pill bernapas di atas headline hero',
            ],
            [
                'group_key' => 'hero',
                'setting_key' => 'hero_headline',
                'setting_value' => "Suku Cadang Presisi.\nGaransi Nyata Toko Resmi.",
                'value_type' => 'string',
                'deskripsi' => 'Judul headline hero editorial',
            ],
            [
                'group_key' => 'hero',
                'setting_key' => 'hero_subheadline',
                'setting_value' => 'Layar LCD grade OEM, modul baterai densitas tinggi, hingga sparepart IC dengan kalibrasi uji pabrik. Dipersiapkan untuk teknisi profesional dan pengguna gadget teliti.',
                'value_type' => 'string',
                'deskripsi' => 'Teks subheadline editorial hero',
            ],
            [
                'group_key' => 'hero',
                'setting_key' => 'hero_features',
                'setting_value' => json_encode([
                    ['icon' => 'qc', 'text' => '100% Diagnostic QC Passed'],
                    ['icon' => 'shield', 'text' => 'Garansi Ganti Unit'],
                    ['icon' => 'clock', 'text' => 'Ambil Cepat di Cabang'],
                ]),
                'value_type' => 'json',
                'deskripsi' => '3 kartu highlight fitur/kepercayaan di bawah hero',
            ],
            [
                'group_key' => 'hero',
                'setting_key' => 'hero_stats',
                'setting_value' => json_encode([
                    ['label' => 'Item Terkatalog', 'type' => 'dynamic_catalog', 'val' => ''],
                    ['label' => 'Pick-up Center', 'type' => 'fixed', 'val' => '5 Cabang'],
                    ['label' => 'Kepuasan', 'type' => 'fixed', 'val' => '99.4%'],
                ]),
                'value_type' => 'json',
                'deskripsi' => 'Statistik angka di bagian bawah hero',
            ],
            [
                'group_key' => 'hero',
                'setting_key' => 'hero_right_widget',
                'setting_value' => 'member_card',
                'value_type' => 'string',
                'deskripsi' => 'Pilihan widget sisi kanan hero: member_card atau banner_slider',
            ],
            [
                'group_key' => 'hero',
                'setting_key' => 'hero_display_mode',
                'setting_value' => 'hero_standard',
                'value_type' => 'string',
                'deskripsi' => 'Mode tampilan hero: hero_standard, carousel_replace, atau hero_and_carousel',
            ],
            [
                'group_key' => 'hero',
                'setting_key' => 'hero_bg_image',
                'setting_value' => '',
                'value_type' => 'string',
                'deskripsi' => 'URL foto background custom section hero',
            ],
            [
                'group_key' => 'hero',
                'setting_key' => 'hero_bg_overlay',
                'setting_value' => 'scrim_dark',
                'value_type' => 'string',
                'deskripsi' => 'Intensitas overlay gelap untuk keterbacaan teks dinamis: scrim_dark, scrim_heavy, atau glass_card',
            ],

            // Stock Policy
            [
                'group_key' => 'stock_policy',
                'setting_key' => 'stock_display_mode',
                'setting_value' => 'threshold_only',
                'value_type' => 'string',
                'deskripsi' => 'Kebijakan privasi stok: exact_number, badge_only, atau threshold_only',
            ],
            [
                'group_key' => 'stock_policy',
                'setting_key' => 'stock_threshold_limit',
                'setting_value' => '5',
                'value_type' => 'integer',
                'deskripsi' => 'Ambang batas angka stok kritis (Sisa X pcs)',
            ],
            [
                'group_key' => 'stock_policy',
                'setting_key' => 'show_branch_breakdown',
                'setting_value' => 'true',
                'value_type' => 'boolean',
                'deskripsi' => 'Tampilkan ketersediaan stok per cabang di halaman detail',
            ],
            [
                'group_key' => 'stock_policy',
                'setting_key' => 'show_sold_counter',
                'setting_value' => 'true',
                'value_type' => 'boolean',
                'deskripsi' => 'Tampilkan perkiraan unit terjual (contoh: Terjual 50+)',
            ],

            // Catalog
            [
                'group_key' => 'catalog',
                'setting_key' => 'show_tier_teaser',
                'setting_value' => 'true',
                'value_type' => 'boolean',
                'deskripsi' => 'Tampilkan kartu teaser upgrade level reseller/teknisi',
            ],
            [
                'group_key' => 'catalog',
                'setting_key' => 'allowed_filters',
                'setting_value' => json_encode([
                    'brand' => true,
                    'kategori' => true,
                    'kualitas' => true,
                    'hp' => true,
                    'kondisi' => true,
                ]),
                'value_type' => 'json',
                'deskripsi' => 'Filter katalog yang aktif dan boleh digunakan pengunjung',
            ],
            [
                'group_key' => 'catalog',
                'setting_key' => 'catalog_per_page',
                'setting_value' => '24',
                'value_type' => 'integer',
                'deskripsi' => 'Jumlah produk per halaman katalog',
            ],

            // Contact & Footer
            [
                'group_key' => 'contact',
                'setting_key' => 'wa_floating_active',
                'setting_value' => 'true',
                'value_type' => 'boolean',
                'deskripsi' => 'Aktifkan tombol mengapung (floating CTA) WhatsApp',
            ],
            [
                'group_key' => 'contact',
                'setting_key' => 'wa_number',
                'setting_value' => '6281234567890',
                'value_type' => 'string',
                'deskripsi' => 'Nomor WhatsApp resmi Customer Service',
            ],
            [
                'group_key' => 'contact',
                'setting_key' => 'wa_default_msg',
                'setting_value' => 'Halo Admin Ute Parts, saya ingin tanya sparepart...',
                'value_type' => 'string',
                'deskripsi' => 'Template pesan awal saat klik WhatsApp',
            ],
            [
                'group_key' => 'contact',
                'setting_key' => 'operating_hours',
                'setting_value' => 'Senin - Sabtu: 08:30 - 20:30 WIB',
                'value_type' => 'string',
                'deskripsi' => 'Jam operasional toko di footer',
            ],
            [
                'group_key' => 'contact',
                'setting_key' => 'social_links',
                'setting_value' => json_encode([
                    'instagram' => 'https://instagram.com/uteparts',
                    'tiktok' => 'https://tiktok.com/@uteparts',
                    'shopee' => 'https://shopee.co.id/uteparts',
                    'tokopedia' => 'https://tokopedia.com/uteparts',
                ]),
                'value_type' => 'json',
                'deskripsi' => 'Tautan akun media sosial resmi',
            ],

            // Pop-up Modal
            [
                'group_key' => 'popup',
                'setting_key' => 'popup_active',
                'setting_value' => 'false',
                'value_type' => 'boolean',
                'deskripsi' => 'Aktifkan modal popup promosi saat pertama berkunjung',
            ],
            [
                'group_key' => 'popup',
                'setting_key' => 'popup_banner_id',
                'setting_value' => '',
                'value_type' => 'string',
                'deskripsi' => 'ID banner yang ditampilkan di pop-up promo',
            ],
            [
                'group_key' => 'popup',
                'setting_key' => 'popup_frequency',
                'setting_value' => 'once_per_day',
                'value_type' => 'string',
                'deskripsi' => 'Frekuensi kemunculan popup (once_per_day, once_per_session, always)',
            ],
        ];

        foreach ($settings as $s) {
            WebKonsumenSetting::updateOrCreate(
                ['setting_key' => $s['setting_key']],
                $s
            );
        }

        // Contoh default banner promo
        if (WebBanner::count() === 0) {
            WebBanner::create([
                'judul' => 'Promo Spesial Teknisi & Mitra Ute Parts',
                'badge_label' => 'PROMO SPESIAL',
                'gambar_desktop' => '/images/banner-hero-sample.jpg',
                'target_url' => '/shop',
                'posisi' => 'hero_slider',
                'urutan' => 1,
                'is_active' => true,
            ]);
        }
    }
}
