# PRD & Implementation Plan: Manajemen Web Konsumen & Redesign Storefront (CMS Ute Prism)

- **Dokumen**: PRD-CMS-02
- **Modul**: Marketplace & Pengaturan Backoffice
- **Target URL**:
  - Backoffice: `/app/pengaturan/web-konsumen` (Route: `pengaturan.web-konsumen`, RBAC: `pengaturan.manage`)
  - Konsumen: `/`, `/shop`, `/shop/{slug}` (Route: `shop`, `shop.detail`)
- **Tujuan**: Konfigurasi dinamis tampilan, hero, promo banner, kebijakan stok, & informasi toko tanpa edit kode sumber, dengan standar visual dan motion Ute Prism (Impeccable & Emil Design Engineering).

---

## 1. Surface Modes & Core POV

### Backoffice Console (`/app/pengaturan/web-konsumen`)
- **Mode**: **Operate** (Presisi, densitas tinggi, efisiensi tugas).
- **Karakter**: Tampilan terstruktur, tabbed form tanpa layout shift, pratinjau live split-screen (desktop/mobile toggle), tombol aksi dengan shortcut `Cmd/Ctrl + S`.
- **Motion**: Fungsional murni. Tidak ada transisi dekoratif berulang. Dropdown & switch snappy (<150ms).

### Web Storefront Konsumen (`/shop`, `/shop/{slug}`)
- **Mode**: **Persuade** (Kepercayaan teknis, presisi perkakas & sparepart, Apple Precision editorial).
- **Karakter**: Visual punch, typografi optis (Plus Jakarta Sans + Instrument Sans), tactile interaction pada kartu produk, badge stok informatif tanpa membocorkan rahasia inventaris.
- **Motion (`@emil-design-eng`)**:
  - Kurva tactile: `--ease-out: cubic-bezier(0.23, 1, 0.32, 1)`.
  - Press feedback: `transform: scale(0.97)` pada semua tombol dan kartu produk.
  - Entrance: Tidak pernah `scale(0)` (selalu `scale(0.95)` + `opacity: 0` -> `1`).
  - Staggered grid catalog reveal: 35ms per item.
  - Banner swipe: Pointer capture + damping friction di batas akhir.

---

## 2. Arsitektur Database & DDL

```sql
-- 1. Konfigurasi Key-Value Terkelompok
CREATE TABLE IF NOT EXISTS web_konsumen_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_key VARCHAR(50) NOT NULL, -- 'branding', 'hero', 'stock_policy', 'catalog', 'contact', 'popup'
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value LONGTEXT NULL,
    value_type ENUM('string', 'boolean', 'integer', 'json', 'image') DEFAULT 'string',
    deskripsi VARCHAR(255) NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_group (group_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Banner & Promosi Dinamis
CREATE TABLE IF NOT EXISTS web_banners (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(150) NOT NULL,
    badge_label VARCHAR(50) NULL, -- 'HOT PROMO', 'PROGRADE', 'FLASH SALE'
    gambar_desktop VARCHAR(255) NOT NULL,
    gambar_mobile VARCHAR(255) NULL,
    target_url VARCHAR(255) NULL,
    posisi ENUM('hero_slider', 'hero_side', 'mid_catalog', 'popup_modal') DEFAULT 'hero_slider',
    urutan INT UNSIGNED DEFAULT 0,
    tanggal_mulai DATETIME NULL,
    tanggal_selesai DATETIME NULL,
    is_active BOOLEAN DEFAULT TRUE,
    click_count INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_posisi_active (posisi, is_active, urutan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Matriks Konfigurasi (Kamus Parameter)

| Group | Key | Tipe | Default Value | Pengaruh UI Storefront |
|---|---|---|---|---|
| `branding` | `store_logo_url` | image | `/icons/icon.svg` | Logo navbar dan favicon |
| | `topbar_text` | string | "Garansi Resmi Toko & Kirim Cepat se-Indonesia" | Marquee / announcement header |
| | `topbar_url` | string | `null` | Link tautan running text |
| | `topbar_active` | bool | `true` | Toggle bar pengumuman teratas |
| `hero` | `hero_active` | bool | `true` | Tampilkan / sembunyikan section hero |
| | `hero_badge_text` | string | "Standard Ute Prism™ Precision Parts" | Pill badge bernapas di atas headline |
| | `hero_headline` | string | "Suku Cadang Presisi. Garansi Nyata Toko Resmi." | Judul utama editorial |
| | `hero_subheadline` | string | "Layar LCD grade OEM, modul baterai densitas tinggi..." | Subteks penjelas |
| | `hero_features` | json | `[{"icon":"qc","text":"100% Diagnostic QC Passed"},{"icon":"shield","text":"Garansi Ganti Unit"},{"icon":"clock","text":"Ambil Cepat di Cabang"}]` | 3 kartu trust-badge |
| | `hero_stats` | json | `[{"label":"Item Terkatalog","type":"dynamic_catalog"},{"label":"Pick-up Center","type":"fixed","val":"5 Cabang"},{"label":"Kepuasan","type":"fixed","val":"99.4%"}]` | Statistik pencapaian toko |
| | `hero_right_widget` | enum | `member_card` (`member_card`, `banner_static`, `banner_slider`) | Komponen sisi kanan hero |
| `stock_policy` | `stock_display_mode` | enum | `threshold_only` (`exact_number`, `badge_only`, `threshold_only`) | `exact`: "12 pcs", `badge`: "Tersedia", `threshold`: "Sisa 3 pcs (Segera Habis)" |
| | `stock_threshold_limit` | int | `5` | Ambang batas munculnya peringatan stok kritis |
| | `show_branch_breakdown` | bool | `true` | Dropdown ketersediaan stok per cabang di detail |
| | `show_sold_counter` | bool | `true` | Label volume penjualan ("Terjual 50+") |
| `catalog` | `show_tier_teaser` | bool | `true` | Banner ajakan daftar teknisi/reseller di bawah katalog |
| | `allowed_filters` | json | `{"brand":true,"kategori":true,"kualitas":true,"hp":true,"kondisi":true}` | Filter yang diizinkan tampil di sidebar & mobile sheet |
| | `catalog_per_page` | int | `24` | Jumlah grid produk per pagination |
| `contact` | `wa_floating_active` | bool | `true` | Tombol mengapung WhatsApp di pojok kanan bawah |
| | `wa_number` | string | "6281234567890" | Nomor tujuan CS toko |
| | `wa_default_msg` | string | "Halo Admin Ute Parts, saya ingin konsultasi sparepart..." | Pesan otomatis pembuka WhatsApp |
| | `operating_hours` | string | "Senin - Sabtu: 08:30 - 20:30 WIB" | Jam operasional di footer |
| | `social_links` | json | `{"instagram":"","tiktok":"","shopee":"","tokopedia":""}` | URL akun media sosial resmi |
| `popup` | `popup_active` | bool | `false` | Modal pop-up banner promo |
| | `popup_banner_id` | int | `null` | Banner terpilih dari tabel `web_banners` |
| | `popup_frequency` | enum | `once_per_day` (`once_per_day`, `once_per_session`, `always`) | Aturan muncul kembali via localStorage |

---

## 4. UI/UX & Design Engineering Specification (`@emil-design-eng`)

### 4.1. Audit & Polish Komponen Storefront
| Komponen | Sebelum | Sesudah (Ute Prism Craft) | Alasan Design Engineering |
|---|---|---|---|
| **Katalog Grid Card** | `transition: all 0.2s` | `transition: transform 160ms var(--ease-out), box-shadow 160ms var(--ease-out)` + `:active { transform: scale(0.98); }` | Hindari re-paint properti tak perlu; berikan tactile click responsif instan. |
| **Hero Slider Entrance** | `opacity: 0; transform: scale(0)` | `opacity: 0; transform: scale(0.96) translateY(8px)` ke `1; scale(1) translateY(0)` durasi 240ms | Elemen tidak pernah muncul dari ketiadaan absolut (skala 0 merusak persepsi kedalaman). |
| **Filter Bottom Sheet (Mobile)** | `transition: 0.3s ease-in` | `transform: translateY(100%)` ke `0` memakai `cubic-bezier(0.32, 0.72, 0, 1)` (kurva iOS) | `ease-in` terasa lamban dan terhambat saat jari membuka sheet; kurva iOS terasa reaktif. |
| **Floating WA Button** | Hover bounce berulang | Tombol mantap dengan ripple halus saat pertama kali load, tooltip origin dari sudut kanan-bawah | Animasi perulangan terus-menerus mengganggu konsentrasi belanja. |
| **Stock Status Pill** | Teks biasa tanpa status warna tegas | Micro-pill dengan dot pulse mint (tersedia) atau amber-ring (kritis) | Indikasi instan tanpa memaksa pengguna membaca angka satu per satu. |

---

## 5. Acceptance Criteria & Implementation Checklist

- [x] **T-01** — Migrasi tabel `web_konsumen_settings` dan `web_banners` selesai dan dijalankan tanpa error.
- [x] **T-02** — Model `WebKonsumenSetting` dan `WebBanner` diimplementasikan lengkap dengan casts tipe data.
- [x] **T-03** — Seeder konfigurasi default mengekstraksi semua teks dan link yang saat ini ter-hardcode di katalog dan layout.
- [x] **T-04** — `WebKonsumenService` mengimplementasikan retrieval ter-cache dan invalidasi otomatis.
- [x] **T-05** — Komponen Livewire `WebKonsumenSettings` di backoffice selesai dengan 5 tab fungsional dan live preview state.
- [x] **T-06** — Route `/app/pengaturan/web-konsumen` terdaftar dengan middleware RBAC `pengaturan.manage` dan tautan sidebar backoffice.
- [x] **T-07** — Layout `layouts.marketplace` membaca running text, logo, floating WA, dan script popup promo secara dinamis.
- [x] **T-08** — Template `shop-catalog.blade.php` menampilkan hero section, highlight kartu, statistik, dan banner promo sesuai konfigurasi.
- [x] **T-09** — Template `shop-detail.blade.php` menerapkan kebijakan privasi stok (`stock_display_mode`: exact vs threshold vs badge).
- [x] **T-10** — Micro-interactions dan tactile buttons (`transform: scale(0.97)`, kurva ease-out) diterapkan pada seluruh komponen toko.
- [x] **T-11** — Unit & Feature Test: perubahan setting admin langsung tercermin di halaman katalog dan lolos seluruh test suite.
