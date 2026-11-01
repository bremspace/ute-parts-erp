# PRD: Fitur Interaktif Web Konsumen, Rich Media (GIF & Video), & CMS Knowledge Base Teknis

- **Dokumen ID**: PRD-KONSUMEN-03
- **Modul**: Marketplace, Servis, & CMS Pengaturan Web Konsumen
- **Target URL**:
  - Storefront Konsumen: `/`, `/shop`, `/shop/{slug}`, `/panduan`, `/panduan/{slug}`, `/estimasi-biaya`, `/garansi`, `/cari-gadget`
  - Backoffice CMS: `/app/pengaturan/web-konsumen` (Tab: Banner & Rich Media, Knowledge Base, Ulasan, FAQ & Widget)
- **Status**: Draft Specification & Implementation Ready
- **Prinsip Arsitektur**: TALL Stack (Laravel 13, Tailwind CSS v4, Alpine.js, Livewire v3), Database Queue, Cache Layer untuk 1GB RAM VPS.

---

## 1. Latar Belakang & Analisis Kebutuhan Industri

Industri suku cadang dan servis perangkat gadget (smartphone, tablet, laptop) memiliki karakteristik spesifik:
1. **Tingkat Ketidakpercayaan Konsumen Tinggi**: Konsumen awam cemas akan keaslian suku cadang (original vs tiruan), takut biaya servis "ditembak", serta khawatir klaim garansi dipersulit.
2. **Keterbatasan Pemahaman Teknis Konsumen**: Pembeli kerap kali tidak hafal kode teknis part (contoh: fleksibel charging board sub-PCB vs kabel fleksibel main-sub). Mereka hanya tahu merk dan tipe unit mereka (contoh: *Samsung A52*).
3. **Kebutuhan Bukti Visual Dinamis**: Visual statis (gambar JPG) tidak cukup untuk mendemonstrasikan keunggulan fleksibilitas kabel LCD, respon sentuh 120Hz, kerapatan lem frame, atau proses mikro-soldering. Diperlukan animasi **GIF** dan **Video Loop Pendek**, serta **Video Panduan YouTube**.
4. **Peran Edukasi sebagai Mesin Konversi (Content-to-Commerce)**: Artikel panduan teknis (*Knowledge Base*) bukan sekadar blog gaya hidup, melainkan panduan diagnosa yang langsung mengarahkan pembaca untuk membeli suku cadang terkait atau melakukan booking perbaikan.

---

## 2. Fitur Tambahan Web Konsumen

### 2.1. Knowledge Base & Edukasi Teknis ("Ute Lab Guides")
- **Deskripsi**: Pusat panduan teknis, edukasi perbedaan grade kualitas part (Original Cabutan vs OEM Grade AAA vs Incell), dan tips diagnosa kerusakan mandiri.
- **Dukungan Media**:
  - Thumbnail gambar, GIF animasi, atau video cover pendek.
  - **YouTube Video Embed**: Konsumen dapat menonton video tutorial perbaikan atau review produk secara responsif tanpa meninggalkan halaman.
- **Content-to-Commerce Integration**:
  - Di dalam setiap artikel, admin dapat menyematkan **Produk Rekomendasi Terkait** (kartu produk langsung dengan tombol *Tambah ke Keranjang*) dan tombol **Booking Servis untuk Gejala Ini**.
- **SEO Ready**: Meta title, Open Graph image, semantic schema `Article` & `HowTo` untuk mendatangkan traffic pencarian Google.

### 2.2. Review & Testimoni Pelanggan Terverifikasi
- **Ulasan Produk Sparepart**:
  - Konsumen yang telah membeli (status transaksi selesai) dapat memberikan rating bintang (1–5), ulasan teks, dan upload foto hasil pemasangan suku cadang.
  - Badge *Pembeli Terverifikasi (Verified Buyer)*.
- **Testimoni Servis Gadget**:
  - Ulasan pelanggan yang telah mengambil unit servis selesai, mencakup kepuasan hasil pengerjaan teknisi dan transparansi garansi.
- **Moderasi CMS**: Backoffice dapat menyetujui, menyembunyikan, atau menyematkan (*pin as featured*) ulasan terbaik ke halaman utama web konsumen.

### 2.3. Kalkulator Estimasi Biaya Servis Cepat (`/estimasi-biaya`)
- **Alur 3 Langkah**:
  1. *Pilih Gadget*: Merk (cth: iPhone, Samsung, Xiaomi) -> Seri/Model (cth: iPhone 13 Pro).
  2. *Pilih Indikasi Kerusakan*: (cth: Layar Pecah / Blank, Baterai Kembung / Drop, Mati Total / Jalur Charging).
  3. *Kalkulasi Otomatis*: Sistem menampilkan estimasi rentang harga (Biaya Suku Cadang + Biaya Jasa Teknisi) sesuai master data `JenisServis` dan harga part katalog.
- **Call-to-Action**: Tombol langsung membawa parameter ke form `/booking-servis` sehingga konsumen tidak perlu mengetik ulang data keluhan.

### 2.4. Pencarian Suku Cadang per Tipe Gadget (`/cari-gadget`)
- **Fungsi**: "Pilih HP Anda, Temukan Semua Partnya".
- Konsumen memilih merk dan tipe smartphone melalui antarmuka visual (logo merk + daftar seri populer).
- Menampilkan grid katalog yang **otomatis terfilter** hanya untuk suku cadang yang kompatibel dengan tipe HP tersebut (LCD, Baterai, Kamera, Konektor Charger, Backdoor, Sim Tray).

### 2.5. Cek Garansi Mandiri (`/garansi`)
- Form pencarian cepat nomor tiket servis, nomor serial (SN), atau IMEI.
- Menampilkan status garansi aktif, tanggal mulai & berakhir, syarat klaim, serta tombol klaim garansi online jika unit mengalami kendala berulang.

### 2.6. FAQ & Kebijakan Garansi Toko
- Akordeon interaktif yang dikelola via CMS: syarat segel garansi tidak boleh robek, ketentuan garansi tes layar sebelum di-lem permanen, kebijakan retur barang cacat pabrik, dan estimasi waktu ekspedisi.

### 2.7. Tombol Konsultasi WhatsApp Cepat (Smart Floating WhatsApp)
- Widget floating sudut kanan bawah dengan pilihan cabang terdekat.
- Pesan otomatis terformat rapi sesuai konteks halaman yang sedang dibuka:
  - Pada halaman produk: *"Halo Admin, saya mau tanya stok dan kecocokan part [Nama Part] (SKU: xxx) di cabang [Nama Cabang]..."*
  - Pada halaman servis: *"Halo Teknisi, saya ingin konsultasi kerusakan unit [Seri HP] dengan kendala..."*

---

## 3. Spesifikasi Dukungan Rich Media (GIF, Video Pendek, & YouTube)

### 3.1. Kebijakan Format & Tipe Media

| Komponen | Format yang Didukung | Durasi Maksimal / Ukuran | Perilaku Render (Frontend) |
|---|---|---|---|
| **Hero Slider & Banner Promo** | JPG, PNG, WebP, GIF, MP4, WebM | Video maks 8 MB (10–20 detik) | `autoplay`, `muted`, `loop`, `playsinline`. Tidak ada suara agar tidak mengganggu navigasi. |
| **Galeri Produk (Foto Utama & Thumbnails)** | JPG, PNG, WebP, GIF, MP4 | GIF maks 5 MB, Video maks 10 MB | Badge indikator `GIF` atau `VIDEO` pada thumbnail. Pada stage canvas utama, video berputar mulus dengan kontrol play/pause optis. |
| **Cover Knowledge Base** | JPG, WebP, GIF, MP4 | Video maks 8 MB | Loop animasi halus untuk menggambarkan proses teknis. |
| **Isi Panduan Knowledge Base** | Embed YouTube Video | URL standar (`youtube.com/watch?v=...` atau `youtu.be/...`) | Responsive iframe (16:9) dengan privacy-enhanced mode (`youtube-nocookie.com`), lazy-load facade untuk menghemat bandwidth. |

### 3.2. Penanganan Performa & Resource Server (1GB RAM Guardrails)
- **Kompresi & Validasi Upload**:
  - File video dibatasi maksimal 10 MB. Format disarankan MP4 (H.264 / AAC) atau WebM untuk efisiensi kompresi tinggi.
  - Video di-serve secara direct static asset dengan HTTP cache header `Cache-Control: public, max-age=31536000, immutable`.
- **Lazy Loading Media**:
  - Video dan GIF menggunakan atribut `loading="lazy"` atau Alpine intersection observer (`x-intersect`). Video hanya memuat stream (`preload="metadata"`) ketika memasuki viewport pengguna.
- **YouTube Facade**:
  - Video YouTube tidak me-load iframe berat sebelum pengguna menekan tombol Play (menggunakan poster thumbnail ringan, baru memicu pembuatan iframe saat diklik).

---

## 4. Desain Skema Database & Migrasi

### 4.1. Tabel Media Dinamis Banner (`web_banners` update)
Kolom tambahan untuk mendukung video dan tipe media:
```sql
ALTER TABLE web_banners 
    ADD COLUMN tipe_media ENUM('image', 'gif', 'video') DEFAULT 'image' AFTER badge_label,
    ADD COLUMN video_desktop VARCHAR(255) NULL AFTER gambar_desktop,
    ADD COLUMN video_mobile VARCHAR(255) NULL AFTER gambar_mobile;
```

### 4.2. Tabel Artikel Knowledge Base / Blog (`web_articles`)
```sql
CREATE TABLE IF NOT EXISTS web_articles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    ringkasan VARCHAR(300) NOT NULL,
    konten_lengkap LONGTEXT NOT NULL,
    kategori VARCHAR(50) NOT NULL DEFAULT 'panduan_servis', -- 'panduan_servis', 'edukasi_part', 'tips_trik'
    
    -- Visual Cover
    tipe_media_cover ENUM('image', 'gif', 'video') DEFAULT 'image',
    cover_image VARCHAR(255) NULL,
    cover_video VARCHAR(255) NULL,
    
    -- Video YouTube
    youtube_url VARCHAR(255) NULL,
    youtube_video_id VARCHAR(50) NULL,
    
    -- Content-to-Commerce Relations
    produk_terkait_ids JSON NULL, -- Array of product IDs: [12, 45, 88]
    rekomendasi_servis_id BIGINT UNSIGNED NULL, -- FK ke jenis_servis
    
    -- Status Publikasi & SEO
    meta_title VARCHAR(150) NULL,
    meta_description VARCHAR(200) NULL,
    penulis_id BIGINT UNSIGNED NULL,
    is_published BOOLEAN DEFAULT FALSE,
    published_at TIMESTAMP NULL,
    views_count INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_slug (slug),
    INDEX idx_kategori (kategori),
    INDEX idx_published (is_published, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.3. Tabel Review & Testimoni Pelanggan (`web_reviews`)
```sql
CREATE TABLE IF NOT EXISTS web_reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pelanggan_id BIGINT UNSIGNED NOT NULL,
    tipe_ulasan ENUM('produk', 'servis') NOT NULL,
    
    -- Target Reference
    produk_id BIGINT UNSIGNED NULL,
    transaksi_id BIGINT UNSIGNED NULL,
    tiket_servis_id BIGINT UNSIGNED NULL,
    
    rating TINYINT UNSIGNED NOT NULL, -- 1 s/d 5
    judul_ulasan VARCHAR(150) NULL,
    isi_ulasan TEXT NOT NULL,
    
    -- Media Bukti / Hasil Pengerjaan (Multiple Foto / GIF)
    media_foto JSON NULL, -- Array URL gambar/gif: ["/storage/reviews/rev1.jpg"]
    
    -- Status & Moderasi Backoffice
    is_verified_purchase BOOLEAN DEFAULT TRUE,
    is_approved BOOLEAN DEFAULT FALSE,
    is_featured BOOLEAN DEFAULT FALSE, -- Ditampilkan di homepage/landing
    tanggapan_admin TEXT NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at TIMESTAMP NULL,
    
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_produk (produk_id, is_approved),
    INDEX idx_servis (tiket_servis_id, is_approved),
    INDEX idx_featured (is_featured, is_approved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.4. Tabel FAQ Interaktif (`web_faqs`)
```sql
CREATE TABLE IF NOT EXISTS web_faqs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kategori VARCHAR(50) NOT NULL DEFAULT 'garansi', -- 'garansi', 'servis', 'pengiriman', 'part_original'
    pertanyaan VARCHAR(255) NOT NULL,
    jawaban TEXT NOT NULL,
    urutan INT UNSIGNED DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_kategori (kategori, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. Antarmuka CMS Backoffice (`/app/pengaturan/web-konsumen`)

Pengaturan diperluas dengan tab-tab terorganisir:

1. **Tab "Banner & Promosi"**:
   - Mendukung pemilihan tipe media: Gambar, GIF Animasi, atau Video Pendek MP4/WebM.
   - Form upload file video desktop dan mobile dengan preview live.
   - Penentuan URL tujuan dan label badge (cth: *120Hz ULTRA SPEED*, *ORIGINAL CABUTAN*).
2. **Tab "Knowledge Base / Panduan"**:
   - Editor artikel ramah pengguna (judul, slug otomatis, kategori, ringkasan, dan konten).
   - Input YouTube URL dengan auto-parsing ID video dan preview thumbnail instan.
   - Selector produk terkait (multi-select autocomplete produk katalog untuk disematkan di kaki artikel).
   - Upload cover media (Gambar / GIF / Video pendek).
   - Switch publikasi dan jadwal rilis.
3. **Tab "Review & Moderasi"**:
   - Daftar ulasan masuk dari pembeli dan pelanggan servis.
   - Tombol cepat: *Setujui (Approve)*, *Tolak*, *Sematkan ke Beranda (Pin to Featured)*, dan form beri tanggapan resmi dari toko.
4. **Tab "FAQ & Garansi"**:
   - CRUD daftar pertanyaan dan jawaban umum. Urutan drag-and-drop atau nomor urut.
5. **Tab "Widget Kontak & WhatsApp"**:
   - Konfigurasi nomor WhatsApp per cabang, template salam pesan otomatis, dan jam kerja aktif.

---

## 6. Antarmuka & Rute Storefront Konsumen

| Rute / URL | Komponen Livewire / View | Deskripsi Fitur Konsumen |
|---|---|---|
| `/panduan` | `Marketplace\Livewire\KnowledgeBaseIndex` | Direktori panduan teknis, filter kategori, search bar, & highlight video tutorial YouTube. |
| `/panduan/{slug}` | `Marketplace\Livewire\KnowledgeBaseDetail` | Halaman artikel lengkap, YouTube embed player, GIF/video ilustrasi, produk kompatibel, dan tombol booking servis. |
| `/estimasi-biaya` | `Marketplace\Livewire\EstimasiBiayaServis` | Kalkulator interaktif estimasi biaya servis gadget berdasarkan merk, seri, dan jenis kerusakan. |
| `/cari-gadget` | `Marketplace\Livewire\DevicePartFinder` | Pemilih visual merk dan model gadget untuk menemukan semua part pendukung. |
| `/garansi` | `Marketplace\Livewire\CekGaransiPublik` | Form cek sisa masa garansi dan panduan klaim garansi toko. |
| `/shop/{slug}` (Update) | `Marketplace\Livewire\ShopPage` | Tambahan tab ulasan pembeli terverifikasi, video demonstrasi part, dan tombol tanya teknisi via WhatsApp. |

---

## 7. Desain Komponen UI "Ute Prism" & Motion Guidelines

1. **Video & GIF Container (`GlassMediaFrame`)**:
   - Sudut membulat modern (`rounded-2xl` hingga `rounded-3xl`).
   - Border halus translucent (`border border-white/10 dark:border-white/[0.08]`).
   - Indikator tactile: Tombol kontrol play/pause minimalis dengan efek blur backdrop (`backdrop-blur-md bg-black/40 text-white`).
2. **YouTube Video Facade**:
   - Menggunakan elemen pembungkus berasio 16:9 (`aspect-video`).
   - Menampilkan gambar cover resolusi tinggi `hqdefault.jpg` dari YouTube CDN sebelum diklik.
   - Saat tombol *Play* ditekan, memicu pemuatan iframe dengan transisi `opacity-0` -> `opacity-100` berdurasi 200ms (`cubic-bezier(0.23, 1, 0.32, 1)`).
3. **Kartu Artikel Knowledge Base (`GuideCard`)**:
   - Efek hover tactile: `transform: translateY(-2px) scale-[1.01]`.
   - Chip badge kategori beraksen warna token: `--up-primary` (Panduan), `--up-mint` (Edukasi Part), `--up-accent` (Tips Perawatan).
4. **Floating WhatsApp Widget**:
   - Posisi tetap di kanan bawah (`fixed bottom-20 sm:bottom-6 right-4 sm:right-6 z-40`).
   - Tombol pill pulsing hijau lembut (`bg-[#25D366] text-white shadow-lg hover:shadow-xl`).
   - Popup drawer mini saat diklik: menampilkan pilihan cabang, status online/offline, dan opsi chat langsung.

---

## 8. Rencana Implementasi Bertahap (Implementation Roadmap)

- [x] **Fase 1: Skema Database & Migrasi Media Kaya**
  - Buat migration update tabel `web_banners` (kolom `tipe_media`, `video_desktop`, `video_mobile`).
  - Buat migration tabel `web_articles`, `web_reviews`, dan `web_faqs`.
  - Update model Eloquent dan relasi antar modul (Produk, Pelanggan, TiketServis).

- [x] **Fase 2: Backend CMS Pengaturan Web Konsumen**
  - Implementasikan tab Knowledge Base di `WebKonsumenSettings`.
  - Implementasikan upload dan penyimpanan media GIF & video di Storage disk publik.
  - Implementasikan tab moderasi Review dan manajemen FAQ.

- [x] **Fase 3: Storefront Knowledge Base & YouTube Embed**
  - Buat komponen `KnowledgeBaseIndex` dan `KnowledgeBaseDetail`.
  - Implementasikan Alpine YouTube facade player (responsif, zero-lag, lazy-loaded).
  - Tambahkan widget rekomendasi part dan tombol booking di akhir artikel.

- [x] **Fase 4: Rich Media pada Hero Banner & Galeri Produk**
  - Update `shop-catalog.blade.php` agar hero slider merender tag `<video>` otomatis jika `tipe_media === 'video'` atau `gif`.
  - Update `shop-detail.blade.php` untuk mendukung file video pendek dan GIF pada visual preview utama.

- [x] **Fase 5: Fitur Interaktif Tambahan Konsumen**
  - Buat halaman Kalkulator Estimasi Biaya Servis (`/estimasi-biaya`).
  - Buat modul Ulasan & Testimoni pada detail produk dan tracking servis.
  - Tambahkan widget Smart Floating WhatsApp di layout utama `marketplace.blade.php`.
  - Uji performa pada lingkungan 1GB RAM (memory limit, page weight, cache headers).
