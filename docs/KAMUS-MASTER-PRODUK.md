# Kamus Data Penambahan Master Produk & Usecase Inventori

Dokumen acuan pengisian form tambah produk, skema visual warna, dan usecase operasional inventori di Ute Parts ERP.

---

## 1. Kamus Kolom Form Penambahan Produk

### A. Data Pokok & Identitas Suku Cadang

| Kolom Input | Status | Penjelasan & Format | Contoh Input | Dampak Sistem |
|---|:---:|---|---|---|
| **Nama Produk** | **Wajib** | Nama lengkap suku cadang yang dicetak pada struk kasir, nota servis, dan label barcode. | `LCD + TS Oppo A11 / Realme 5 BPT Black` | Ditampilkan di katalog POS kasir, nota pembayaran, dan pencarian cepat. |
| **Kode Barcode** | Opsional | Angka/kode scan pada kemasan fisik. Jika kosong, sistem membuatkan barcode unik otomatis. | `8991234567890` | Digunakan saat scan barcode fisik via barcode scanner atau kamera HP. |
| **Kategori Produk** | **Wajib** | Pengelompokan hierarkis kategori utama dan sub-kategori suku cadang. | `Sparepart > LCD & Touchscreen` | Pengelompokan laporan omzet, laba per divisi, dan filter katalog POS. |
| **Brand Part** | Opsional | Merek **pabrik pembuat suku cadang** (bukan merek smartphone). | `Olike`, `Hippo`, `Crown`, `BPT`, `Shinetsu` | Memudahkan teknisi memilih suku cadang berdasarkan pabrikan favorit. |
| **Kualitas (Grade)** | Opsional | Tingkat mutu spesifikasi manufaktur suku cadang. | `Original 100%`, `OEM`, `Grade A / High Copy` | Menentukan penawaran opsi harga dan durasi garansi servis ke konsumen. |
| **Kondisi** | **Wajib** | Status fisik & kebaruan unit dengan **indikator visual warna**. | `Baru`, `OEM`, `Compatible`, `Bekas / Copotan`, `Rusak / Kanibal` | Menampilkan badge warna di tabel WMS dan POS kasir (mencegah salah jual). |
| **Satuan Dasar** | **Wajib** | Satuan unit fisik terkecil penyimpanan stok di rak. | `pcs`, `set`, `roll`, `meter` | Satuan acuan pemotongan stok kartu inventori dan stok opname. |
| **Supplier Utama** | Opsional | Pemasok/distributor langganan untuk pengadaan barang ini. | `PT Sumber Jaya Part` | Otomatis terpilih saat pembuatan Surat Pesanan Pembelian (PO). |
| **Kode SKU** | Opsional | Kunci unik laci/barang (*Stock Keeping Unit*). Default: otomatis sama dengan barcode. | `LCD-OP-A11-BPT` | Identifikasi unik barang di seluruh gudang dan cabang. |

---

### B. Kompatibilitas Smartphone (Tipe HP Cocok)

| Komponen Input | Cara Kerja | Contoh Input | Dampak di Kasir & Gudang |
|---|---|---|---|
| **Merek Smartphone** | Dropdown daftar merek yang ada di database, atau klik `+ Merek Baru` jika belum ada. | `Apple`, `Samsung`, `Oppo`, `Realme`, `Infinix` | Menyaring model HP yang relevan secara cascading. |
| **Model / Seri HP** | Dropdown daftar model dari merek terpilih, atau klik `+ Model Baru` jika belum ada. | `A11`, `A5 2020`, `5`, `iPhone 13` | Menghubungkan tipe smartphone target ke 1 master fisik produk. |
| **Tombol + Tambah** | Menambahkan pasangan Merek + Model ke daftar kompatibilitas. Dilengkapi **proteksi anti-duplikat**. | Menghasilkan chip: `[ Oppo A11 ✕ ]`, `[ Realme 5 ✕ ]` | 1 produk fisik otomatis ditemukan saat kasir mencari tipe HP mana pun yang terdaftar. |

---

### C. Harga & Finansial

| Kolom Input | Status | Penjelasan & Format | Contoh Input | Dampak Sistem |
|---|:---:|---|---|---|
| **Metode Harga Beli** | **Wajib** | Cara hitung modal: `Beli Manual` atau `Moving Average PO`. | `Beli Manual` | Menentukan Harga Pokok Penjualan (HPP) untuk laba rugi akuntansi. |
| **Harga Beli (Modal)** | **Wajib** | Nilai modal beli per 1 satuan dasar (angka rupiah murni). | `120000` | Dasar perhitungan nilai aset persediaan di neraca dan laba kotor. |
| **Margin (%)** | Opsional | Persentase laba kotor yang diharapkan dari harga modal. | `30` (artinya laba 30%) | Otomatis mengkalkulasi Harga Jual Retail. |
| **Harga Jual Retail** | **Wajib** | Harga jual standar untuk konsumen umum di kasir POS. | `160000` | Harga default saat item dipilih di kasir POS. |
| **Harga Tier Membership** | Opsional | Harga grosir khusus untuk member (Reseller, Teknisi, Bengkel). | Reseller: `140000`<br>Bengkel: `135000` | Otomatis diterapkan saat kasir memilih data pelanggan member. |

---

### D. Fitur Lanjutan (Opsional)

| Kolom Input | Kapan Digunakan | Contoh Penggunaan |
|---|---|---|
| **Serial Number (SN / IMEI)** | Wajibkan input SN fisik setiap kali barang masuk atau keluar. | Baterai bergaransi unik, unit mesin copotan, atau sparepart berseri. |
| **Substitusi Produk** | Rekomendasi barang pengganti jika stok produk ini kosong di kasir. | Jika `LCD BPT` kosong, kasir diarahkan menawarkan `LCD Crown`. |
| **Satuan Kemasan (Dus/Box)** | Pembelian grosir dus yang otomatis dikonversi ke eceran pcs. | 1 Box isi 10 pcs; beli 2 box otomatis menambah 20 pcs ke stok eceran. |

---

## 2. Skema Warna Status Kondisi (Tampilan POS & WMS)

Status kondisi produk tampil sebagai **badge visual warna** di tabel inventori WMS, kartu katalog, dan layar kasir POS:

| Kode | Label Kondisi | Badge Visual | Warna Hex | Tampilan & Usecase di Toko |
|---|---|:---:|:---:|---|
| `baru` | **Baru** | 🟢 Emerald | `#10b981` | **Segel Pabrik / Fresh Stock.** Tampil hijau terang. Berhak atas garansi tukar unit toko standar. |
| `oem` | **OEM** | 🔵 Blue | `#3b82f6` | **Original Equipment Manufacturer.** Part baru dari pabrikan rekanan resmi perakit perangkat. |
| `compatible` | **Compatible** | 🔷 Cyan | `#06b6d4` | **Part Baru Pihak Ketiga.** Suku cadang baru pengganti (aftermarket) dengan harga lebih ekonomis. |
| `bekas` | **Bekas / Copotan** | 🟡 Amber | `#f59e0b` | **Unit Copotan Mesin Normal.** Bekas pakai, fungsi teruji normal. Garansi tes di tempat / terbatas. |
| `rusak` | **Rusak / Kanibal** | 🔴 Rose | `#ef4444` | **Bahan Kanibal Part Teknisi.** Komponen rusak untuk cabutan IC / soket oleh teknisi internal. Non-garansi. |

---

## 3. Usecases Pada Penambahan Produk & Manajemen Stok

### Usecase 1: Prinsip 1 Laci Fisik = 1 Master Produk (Cegah Ghost Out-of-Stock)
* **Masalah:** 1 modul LCD fisik yang sama dapat dipasang pada HP *Oppo A11*, *Oppo A5 2020*, dan *Realme 5*.
* **Penerapan di Ute Parts:** Produk diinput **1 kali saja** sebagai 1 SKU (misal: `LCD-OP-A11-BPT`), lalu ketiga model HP ditambahkan ke daftar kompatibilitas.
* **Hasil:** Stok fisik di laci tersimpan utuh 10 pcs. Kasir yang melayani pemilik Oppo A11 maupun Realme 5 sama-sama melihat stok nyata 10 pcs tanpa risiko stok terpecah atau salah tolak pembeli.

### Usecase 2: Identifikasi Cepat Kasir Melalui Warna Status
* Kasir mencari layar sentuh di POS. Pada layar pencarian, muncul dua opsi:
  * `LCD iPhone 11 (Baru)` dengan badge 🟢 **Hijau Emerald** $\rightarrow$ kasir tawarkan harga baru resmi garansi 30 hari.
  * `LCD iPhone 11 (Copotan)` dengan badge 🟡 **Kuning Amber** $\rightarrow$ kasir tawarkan sebagai opsi hemat copotan original garansi tes.
* Warna visual mencegah kasir salah mengambil barang di rak atau salah memberikan janji garansi kepada konsumen.

### Usecase 3: Multi-Tier Harga Otomatis Saat Penambahan Produk
* Saat mendaftarkan produk, admin mengisi Harga Retail Rp 160.000, Tier Reseller Rp 140.000, dan Tier Bengkel Rp 135.000.
* Di kasir POS, kasir cukup memilih nama pelanggan. Jika pelanggan terdaftar sebagai teknisi/bengkel rekanan, harga otomatis turun ke Rp 135.000 tanpa perlu tawar-menawar manual atau kalkulator kasir.

### Usecase 4: Alur Pengadaan & Pemotongan Stok Akurat
* **Stok Masuk (Inbound / PO):** Pembelian 10 pcs dari supplier masuk ke gudang utama, otomatis menambah kuantitas stok laci dan mencatat jurnal akuntansi persediaan.
* **Penjualan Kasir POS:** Setiap penjualan (baik via scan barcode maupun pencarian nama model HP) memotong stok laci fisik secara real-time dan mencatat HPP penjualan akuntansi.
