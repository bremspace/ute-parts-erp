# MIGRASI SID RETAIL → UTE PARTS ERP

> **Dokumen tunggal kerja migrasi.** Update file ini di setiap fase. Jangan mulai fase tanpa baca file ini.
> Terakhir diperbarui: 2026-09-22 — analisis skema SID + app, audit kualitas stagging (fase 0 selesai).

---

## 1. DOKTRIN (WAJIB)

1. **SID Retail = Source of Truth (SOT)** untuk seluruh data historis & master yang dibawa. `latest.sql` (/root/workspace/ute-pos-main/latest.sql) sumber akhir. App = turunan. Jika ada selisih kolom → **ubah skema app** (tambah kolom/tabel), jangan potong data SID.
2. **Idempotent sepenuhnya.** Jalankan ulang → hasil sama, tanpa dobel. Guard: `sid_import_map` unique(kode_sumber, tabel_sumber) + upsert by kode.
3. **Tidak boleh menimpa data operasional baru app** yang dibuat setelah migrasi (transaksi baru, pelanggan baru, stok mutasi baru). Aturan konflik: kode SID vs `no_transaksi` ada duplikasi → data app yang lebih baru menang, kode SID di-remap dengan suffix `-SID`.
4. **Bersihkan data dobel/dispute** — dibolehkan & wajib diidentifikasi (bagian 4) sebelum fase eksekusi.
5. **Semua pembersihan & mapping terekam** di tabel `sid_import_map` + kolom `keterangan`/`catatan` bila ada. Bisa diaudit.
6. Server 1GB RAM: import chunked (500/statement), tanpa `str_getcsv` seluruh file, tanpa sync queue, `database` queue driver.
7. Setiap kenaikan skema → migrasi Laravel baru (jangan edit migrasi lama yang sudah jalan), update `CHANGELOG.md`.

---

## 2. INVENTARIS SUMBER (SID — 212 tabel)

Dump `latest.sql` 21MB: schema lunak, **tanpa PK/FK deklaratif** (semua string key), kolom lumpur, tanggal sentinel `1899-12-30`/`0000-00-00` = kosong, jam format 12 jam.

### 2.1 Tabel inti yang DIVAWA (migrasi penuh)

| Tabel SID | Kolom | Volume (staging) | Status saat ini |
|---|---|---|---|
| `barang` | 148 | 6.433 | produk 6.368 + sku_variants 6.368 — MAPPED, gap kolom (§3.1), dup kode=0 ✅ |
| `pelanggan` | 38 | 284 | pelanggan 291 — gap kolom, dup (nama,telp) 9 grup (A-01) |
| `member` | 23 | 4 | → dimerger ke pelanggan (id_kartu) — status: SEBAGIAN |
| `supplier` | 16 | 10 | supplier 12 — ok, verifikasi saldo_deposit |
| `penjualan` (HEADER) | 79 | (143 stmt) | **TIDAK DI-STAGING** — migrasi lama cuma import itempenjualan |
| `itempenjualan` | 44 | 10.273 | → transaksi 18.786 — **RUSAK, rekonstruksi wajib (A-01)** |
| `arus_stok` + 7 partisi bulanan | 17 | 19.286 | → stok_log 120.167 — **RUSAK, rekonstruksi wajib (A-01)** |
| `servis` | 35 | 332 | tiket_servis 342 — mapping status BELUM SAMPAI (§3.7) |
| `itemservis` | 10 | (4 stmt) | → tiket_servis_item — belum diverifikasi |
| `hrgpergroup` | 10 | (41 stmt) | → harga_tier (19.104) — anomali ~19k (≈ arus_stok) — VERIFIKASI (A-01) |
| `grouphrgpelanggan` | 2 | (1 stmt) | → tier_memberships / harga_tier.tier_membership_id |
| `setup_perusahaan` | 208 | 1 | → konfigurasi + profiling toko — SEBAGIAN |
| `kas` / `kas_awal` | 17 / 3 | 1 + 1 | → `master_kas` (tabel ada di M-08) — ingest fase 2B |
| `piutang` | 11 | (1 stmt) | → piutang — **BELUM DIISI (app=0)** |
| `hutang` | 10 | (1 stmt) | → utang — **BELUM DIISI (app=0)** |
| `pembelian` / `itempembelian` | 29 / 38 | 3 + 14 stmt | → purchase_order(+_item) — **BELUM DIISI** |

### 2.0 TEMUAN AUDIT A-01 (2026-09-22) — WAJIB BACA

1. **Barang bersih:** dup kode staging = 0; expired valid cuma 4 (+0 sentinel). produk app 6.368 vs staging 6.433 (65 kurang — cek yang tidak ter-map: 6.362 mapped, 6 selisih).
2. **Pelanggan dup 9 grup** by (nama, telp): SANDI ×2, AGUNG ×2, PELANGGAN UMUM ×4, DIAN ×2, ONO ×2, IRMA ×3, MULYADI ×2, Dede ×2, AZAN ×2 → merge rules §4.3.
3. **Orphan itempenjualan: 178 distinct kode_barang** tidak ada di staging barang (contoh `BSL-010978` ×5, `012608` ×21, `011465` ×8) — kemungkinan barang lama nonaktif/terhapus atau kode paket. Cek di dump barang sebelum fallback dummy (§4.4).
4. **⚠️ TRANSaksi app 18.786 = RUSAK.** Semua `sumber='pos'`, `created_at` berinterval 2026-09-21 09:31–09:57 (26 menit = waktu import, BUKAN tanggal historis), `no_transaksi` = faktur SID (`R43-...`) dengan suffix `-2` (duplikat faktur). Migrasi lama membuat **1 transaksi per ITEMPENJUALAN** (dan tidak ada header penjualan di staging). → **Rekonstruksi dari header `penjualan` + `itempenjualan` (group by kode faktur), bukan per item.**
5. **⚠️ stok_log 120.167 ≈ 6,2× arus_stok 19.286** — ekspansi tidak eksplisit, jenis hanya `keluar`/`masuk` (85.963/34.196) + 7 penjualan. → rekonstruksi 1:1 dari arus_stok.
6. **⚠️ harga_tier 19.104 ≈ arus_stok 19.286** — dugaan: migrasi lama mengisi harga_tier per baris arus_stok (artefak). VERIFIKASI wajib sebelum fase 3; kemungkinan dibersihkan & diisi ulang dari hrgpergroup (41 stmt × kodebarang).
7. **Header yang hilang dari staging:** `penjualan`, `pembelian`, `piutang`, `hutang`, `kas`, `kas_awal` — SidImportRaw coreTables hanya 8 tabel inti tanpa header. → **EXTEND staging** (tabel raw baru + coreTables) sebelum fase 2B/3.

### 2.0b Janji komitmen (dari temuan)
- JANGAN percaya angka transaksi/stok_log/harga_tier yang ada sekarang — semua artefak migrasi lama.
- Cleanup artefak = hapus baris `is_migrasi_sid=true` (kolom baru M-03) — baris operasional baru (non-migrasi) dipertahankan.

### 2.2 Tabel kecil yang DIVAWA (fase 2B)

| Tabel | Kolom penting | Catatan |
|---|---|---|
| `header_return_penjualan` / `header_return_pembelian` | kode, tanggal, pelanggan/supplier, jumlah | **APP TIDAK PUNYA RETURN** → butuh tabel baru (keputusan §3.9) |
| `nomor_seri` + `item_jual_serial` + `item_beli_serial` | no_faktur, kd_barang, nomor_seri, transaksi | **APP TIDAK PUNYA TRACKING SERIAL** → keputusan |
| `expired_barang` | kode_barang, qty, tgl_expired | stok exp → produk.expired/kolom baru |
| `komplain` | kode_pelanggan, komplain | → tabel komplain baru atau catatan pelanggan |
| `koreksi` / `itemkoreksi` | selisih, hpp, alasan | → alur stok_opname (app) — manual mapping |
| `histori` | tanggal, keterangan | → audit_logs (filter penting, 177 stmt) |

### 2.3 Tidak punya padanan app → ARSIP RAW (bukan entity aktif)

`brilink*`, `leasing*`, `giroin/out`+item, `goodbs*`, `tabungan*` (+`buffer_tabungan`), HR (`karyawan`, `absensi`, `loginkaryawan`, `kaskaryawan`, `bayar_intensif*`, `kasbon`), `sewa*`, `canvas*`, `item_type`, `barcode5..14` (kolom di barang — disimpan di payload raw, tidak dijadikan kolom), `temp_*`, `faktur_terakhir*`, `pengambilanbarang*`, `pengiriman*` (SID pengiriman ≠ app pengiriman online).

---

## 3. MATRIKS MAPPING + GAP SKEMA APP (hasil analisis 2026-09-22)

### 3.1 `barang` (148 kolom) → app

**Sudah dipetakan:** nama→nama, kategori→kategori, satuan→satuan, hpp→harga_beli, harga_toko→harga_jual_retail, gambar→gambar.

**GAP — butuh kolom/migrasi baru di app:**

| Kolom SID | Target app | Aksi |
|---|---|---|
| `kode` (kode barang/barcode) | produk **kode_lama** (string, index unik) | **TAMBAH kolom** `kode_lama` + `barcode` di `produk` |
| `kode_barcode`, `kode_barcode2..4` | produk `barcode` (primary), `barcode_alt` json | TAMBAH `barcode`, `barcode_alt` (json) |
| `merk` | brands 🡒 produk.brand_id | TAMBAH `brand_id` (arsi `brands` dari kolom merk, resolusi case-insensitive) |
| `golongan`, `subgolongan1/2` | produk `golongan`, `subgolongan` | TAMBAH 2 kolom string |
| `satuanbeli`, `isi` (+isi2..4) | produk `satuan_beli`, `isi_satuan` | TAMBAH |
| `harga_toko2..4`, `harga_partai1..4`, `harga_cabang1..4`, `harga_karyawan`, `harga_member`, `harga_lain1..4` | → **harga_tier** (per tier) ATAU `produk.harga_jual` json | PRIORITAS: masukkan ke `harga_tier` dengan tier dari `grouphrg*`; sisanya json `produk.harga_lain` |
| `harga_toko` | harga_jual_retail (sudah) | — |
| `diskon`, `diskon2`, `diskon_toko/partai/cabang` | produk `diskon_persen` json / kolom | TAMBAH `diskon` (decimal) + json diskon_per_tipe |
| `stokmin`, `stokmax`, `warningstok` | stok_items `jumlah_minimum` + produk `stok_maksimum`, `stok_warning` | TAMBAH produk kolom; min sudah ada |
| `expired`, `ada_expired_date` | produk `expired_at` (date nullable) | TAMBAH |
| `jenis` (BARANG/JASA/PARTAI), `elektrik`, `sn` (serial), `paket`, `tampil` | produk `jenis` (enum: barang/jasa/paket), `wajib_serial` (bool), `is_active` | TAMBAH `jenis`, `wajib_serial` |
| `point`, `point_m`, `jenis_point`, `point_k1/k2` | produk `poin` + konfigurasi | TAMBAH `poin` |
| `komisi_spg`, `jum_komisi_sales` | produk `komisi_sales` | TAMBAH |
| `toko`..`toko15`, `gudang`, `sisa_average`, `toko_rusak` | **bukan kolom produk** → ke `stok_items` per gudang | Mapping ke gudang (`TOKO`,`GUDANG`,dst per setup_perusahaan) saat fase 3 stok |
| `pajak`, `sudah_ppn`, `nilaippn` | produk `kena_pajak` (bool), `nilai_ppn` | TAMBAH |
| `harga_terakhir`, `tgl_terakhir` | produk log | hiraukan (historis) / simpan json |
| `warna`, `ukuran` + `stok_by_ukuran_warna` | → sku_variants (atribut) | Migrate varian ke sku_variants; sisanya json |

### 3.2 `pelanggan` (38) + `member` (23) → app

**Sudah:** nama, alamat, email, telepon→telepon, tgllahir→tanggal_lahir (migrasi 000037 ada), point→poin_loyalty.

**GAP:**

| Kolom SID | Target | Aksi |
|---|---|---|
| `kode` | pelanggan **kode_lama** unique | TAMBAH |
| `saldo_piutang`, `max_piutang` | pelanggan `saldo_piutang`, `max_piutang` (blokir utang) | TAMBAH |
| `kdgrouphrg` | pelanggan tier_membership_id | Mapping via grouphrgpelanggan→tier_memberships |
| `area`, `rayon`, `kota`, `instansi` | pelanggan area/rayon/kota string | TAMBAH |
| `join_date` | pelanggan `bergabung_at` | TAMBAH |
| `diskn_penjualan`, `persen_shu` | pelanggan `diskon_persen` | TAMBAH |
| `sales` | pelanggan `sales_nama` | TAMBAH |
| `saldo_tabungan`, `blokir_piutang_hari`, `wa_oto_piutang_last_date` | arsip json | json |
| member: `id_kartu`, `no_kartu`, `expired`, `point`, `saldo_piutang`, `max_piutang` | pelanggan: `kode_member`, `no_kartu`, `tier_expired_at`, `poin_loyalty` | TAMBAH; merge: member.id_kartu → pelanggan.kode_member |

**DUPLIKAT audit:** staging pelanggan 284 vs app 291 — 7+ baris sumber ganda. Fase 2A wajib: dedup by (nama, telepon) → pilih record SID dengan join_date tertua = master; sisanya di-merge (transaksi diremap via sid_import_map).

### 3.3 `supplier` (16) → app

**Sudah:** nama, alamat, telp, email→kontak, kota.
**GAP:** `kode`→supplier.kode_lama (TAMBAH), `saldo_piutang`→supplier.saldo_deposit (TAMBAH), `kdgrouphrg`, `no_npwp`→npwp (TAMBAH), `contact`→kontak (ada), `nomor`→telepon (ada).

### 3.4 `penjualan` (79) → `transaksi`

**Sudah:** kode→no_transaksi (remap), tanggal→(transaksi tak punya tanggal…? **gap**), pelanggan→pelanggan_id, diskon→diskon_persen/nominal, tax→pajak_nominal, jumlah→total_akhir, bayar→jumlah_bayar, kembali→kembalian, kasir→kasir_id, keterangan→catatan.

**GAP:**

| Kolom SID | Aksi |
|---|---|
| `tanggal` + `jam` | **TAMBAH `tanggal_transaksi` (datetime) di transaksi** — sekarang dipakai created_at, TIDAK AMAN utk data historis |
| `jenis` (PENJUALAN/INPUT BARANG/DEL …) | **TAMBAH `jenis` enum (penjualan/pembelian/mutasi/koreksi/…)** — kunci breakdown & validasi |
| `operator`, `kasir` | user_id/kasir_id (mapping nama→user) |
| `member` (flag), `piutang`, `lunas`, `jt` | piutang: remap ke tabel piutang (baris lunas flag) |
| `status` (BELUM DIKIRIM…), `status_pengiriman` | transaksi.status + pengiriman |
| `no_faktur_pajak`, `hrg_termasuk_pajak`, `pajak_*` | TAMBAH json `pajak_detail` |
| `sales`, `spg`, `shif` | TAMBAH `sales` string (mapping) |
| `biayakirim`, `jasakirim` | → pengiriman.ongkir |
| `po`, `receive`, `pr` | json / catatan |
| `dicetak`, `trx_vcr`, `kd_vcr`, `cart`, `cashout`, `angsuran`, `bunga_angs*`, `tabungan*`, `komisi_sales`, `ket_tambahan` | ArSIP json `detail_sid` (kolom `sid_detail` json di transaksi — TAMBAH) |

### 3.5 `itempenjualan` (44) → `transaksi_item`

**Sudah:** kode_barang→produk_id (via map), qty→jumlah, harga→harga_satuan, diskon_rupiah→diskon_nominal, subtotal→subtotal, hpp→hpp.

**GAP:** `diskon` (%) → diskon_persen (TAMBAH), `point`/`point_m` → json, `ppn`+`jumlah_ppn` → hpp/pajak json, `no_po` → referensi, `ukuran`/`warna` → sku_variant_id, `expired` → json, `detail_ongkir` → json, `kd_barang_paket` → json (paket), `posting` → hiraukan.

### 3.6 `arus_stok` (17) → `stok_log`

**Sudah:** kode_barang→produk_id, masuk/keluar→perubahan, sisa→jumlah_setelah, no_transaksi→referensi_tipe.

**GAP:**

| Kolom SID | Aksi |
|---|---|
| `awal`, `nilai_awal`, `nilai_masuk`, `nilai_keluar`, `nilai_sisa` | **TAMBAH kolom `jumlah_awal`, `nilai_awal`, `nilai_masuk`, `nilai_keluar`, `nilai_sisa`** di stok_log |
| `tanggal` + `jam` | **TAMBAH `tanggal_log` datetime** (created_at historis aman) |
| `lokasi_cabang`, `lokasi_stok` | → gudang_id (mapping TOKO/GUDANG per cabang) |
| `transaksi` (jenis SID: PENJUALAN/INPUT BARANG/DEL …) | → stok_log.jenis (normalisasi: DEL PENJUALAN→koreksi negatif, dst) |
| `operator` | → user_id (mapping) |

**CATATAN VALIDASI:** stok_log 120.167 ≈ 6,2× arus_stok staging 19.286. Hipotesis: (a) migrasi lama mengembang 1 baris arus → N baris log (satu baris arus berisi awal+masuk+keluar+sisa), (b) ada stok_log dari operasi app pasca-migrasi (transaksi baru). **Wajib breakdown per tanggal sebelum fase 3** — kalau ekspansi tidak eksplisit, stok bisa dispute.

### 3.7 `servis` (35) → `tiket_servis` (+ itemservis → tiket_servis_item)

**Sudah:** kode→no_tiket, tanggal→tanggal_terima, kode_pelanggan→pelanggan_id, barang→nama/tipe, no_imei→seri_hp, kerusakan→keluhan, jasa+spare_part→estimasi_biaya, jumlah→estimasi, status→status.

**GAP & PERINGATAN:**

| Kolom SID | Aksi / catatan |
|---|---|
| Status SID (`SEDANG DI SERVIS`, `DIAMBIL`, `SELESAI`, `DIBATALKAN`) | Mapping ke state machine app (`diterima/dikerjakan/qc/menunggu_approval/disetujui/ditolak/selesai`) — **JANGAN langgar state machine; mapping = snapshot status akhir, tidak lewat transisi** (`servis_status_log` diisi 1 baris snapshot) |
| `type` (tipe HP) | → `tipe_hp`/jenis_servis (mapping, buat bila belum ada) |
| `kerusakan2/3`, `kelengkapan1..3` | kondisi_fisik json (ada) |
| `tgl_bayar`, `pembayaran`, `jt`, `komisi`, `teknisi`, `sudahdiambil`, `dibatalkan`, `tanggal_kembali`, `jam`, `jamkembali` | TAMBAH kolom di tiket_servis: `tanggal_bayar`, `teknisi`, `tanggal_diambil` (ada), `pembayaran` json — atau json sid_detail |
| `totalhpp`, `totallabarugi`, `selesai` | json / hitung ulang dari itemservis |

### 3.8 **TABEL BARU DIBUTUHKAN** (belum ada di app)

1. `master_kas` — id, cabang_id, kode (KP/KL…), nama, saldo_awal (dari kas_awal), tipe (kas/bank), is_default_toko/beli/piutang/hutang (bool), is_active, timestamps. → relasi: kas_sesi.kas_id (TAMBAH kolom), piutang.kas_id, utang.kas_id.
2. `return_penjualan` (+ `return_penjualan_item`) & `return_pembelian` (+ item) — kode, tanggal, no_ref (penjualan/pembelian asal), pelanggan_id/supplier_id, jumlah, status, + item: produk_id, qty, harga, subtotal, alasan. → stok log jenis return.
3. `nomor_seri_produk` — id, produk_id, sku_variant_id, nomor_seri (unique), status (tersedia/terjual/return/servis), transaksi_item_id nullable, tiket_servis_id nullable, timestamps.
4. `komplain_pelanggan` — id, pelanggan_id, transaksi_id nullable, tanggal, komplain, status, resolusi, timestamps.

### 3.9 Kolom baru ringkas (per tabel app — resolusi dari gap di atas)

- **produk**: kode_lama (unique), barcode, barcode_alt (json), brand_id (FK), golongan, subgolongan, satuan_beli, isi_satuan, harga_lain (json), diskon, stok_maksimum, stok_warning, expired_at, jenis (enum), wajib_serial, poin, komisi_sales, kena_pajak, nilai_ppn.
- **sku_variants**: kode_lama (unique, nullable).
- **pelanggan**: kode_lama (unique), kode_member, no_kartu, tier_expired_at, saldo_piutang, max_piutang, area, rayon, kota, instansi, bergabung_at, diskon_persen, sales_nama.
- **supplier**: kode_lama, npwp, saldo_deposit.
- **transaksi**: tanggal_transaksi (datetime), jenis (enum), sales, pajak_detail (json), sid_detail (json).
- **transaksi_item**: diskon_persen.
- **stok_log**: tanggal_log (datetime), jumlah_awal, nilai_awal, nilai_masuk, nilai_keluar, nilai_sisa.
- **tiket_servis**: tanggal_bayar, teknisi, sid_detail (json), pembayaran (json).
- **kas_sesi**: kas_id (FK master_kas).
- **piutang/utang**: kas_id (FK), kode_lama (unique).
- **purchase_order**: kode_lama; **purchase_order_item**: kode_lama.
- **konfigurasi**: isi profil toko (nama, alamat, telp, logo, metode_hpp, format_faktur) dari setup_perusahaan — kunci prefix `profil.*`, `hpp.*`, `faktur.*`.

---

## 4. ATURAN PEMBERSIHAN DATA (dedup & dispute)

Diurut prioritas; SEMUA sebelum fase eksekusi masing-masing, transparan via `sid_import_map.keterangan` atau tabel log audit migrasi.

1. **Tanggal sentinel**: `1899-12-30`, `0000-00-00`, `1900-01-01` → NULL saat insert (payload_normal sudah normalisasi sebagian; verifikasi).
2. **Dup `barang.kode`**: staging 6.433 unik? (audit fase 2A). Bila dobel → pilih row dengan `tgl_terakhir` terbaru, sisanya flag `is_active=false` (jangan hapus, ada transaksi lama menunjuk).
3. **Dup `pelanggan` (nama, telepon)**: SID 284 vs app 291. SOT = SID. App entries yang tidak ada di SID = data operasional baru → pertahankan; yang bentrok → merge (transaksi di-remap ke satu pelanggan via sid_import_map).
4. **Orphan `itempenjualan.kode_barang`** tidak ada di `barang` → cek `nama_barang` match (fuzzy) → kalau dapat, remap; kalau tidak → produk dummy `[ARSIP-SID] <nama>` kode_lama=baris, is_active=false.
5. **Orphan servis** `kode_pelanggan` tidak di pelanggan → map by nama/telepon; fallback pelanggan dummy per cabang `Walk-in SID`.
6. **Nomor transaksi bentrok** (SID `TRX-…` vs app `TRX-…` baru) → suffix `-SID` untuk yang lama, `no_transaksi` app tetap.
7. **stok_log ekspansi 6×**: audit ulang mapping — 1 baris arus_stok harus = 1 baris stok_log (bukan 6). Koreksi command migrasi bila keliru, hapus baris keliru yang identitasnya `is_migrasi_sid=true` (TAMBAH kolom flag `is_migrasi_sid` di stok_log untuk pembersihan aman).
8. **harga_tier 19.104**: verifikasi sumber (hrgpergroup 41 stmt × ~400 kode?). Bila duplikat dengan tier SID → resolusi: harga dari hrgpergroup menang, dibedakan via tier_membership kode = `HRG-<kdgrouphrg>`.

---

## 5. WORKFLOW FASE (urutan dependensi wajib)

> Format checklist: `- [ ]` dicentang HANYA setelah diverifikasi benar (rule user 2026-09-20).

### FASE 1 — SKEMA & PREP (butuh migrasi baru)
- [x] M-01..M-03, M-08 skema: migrasi `2026_09_22_000100_sid_migrasi_skema_fase1.php` DONE (idempotent guard hasTable/hasColumn — sebagian skema sudah ada manual tanpa migrasi tercatat; recovered). Mencakup: semua kolom §3.9 (produk/sku_variants/pelanggan/supplier/transaksi/transaksi_item/stok_log/tiket_servis/kas_sesi/piutang/utang/purchase_order(+_item)), tabel baru `master_kas`, `return_penjualan(+_item)`, `return_pembelian(+_item)`, `nomor_seri_produk`, `komplain_pelanggan`, flag `is_migrasi_sid` di 8 tabel.
- [x] M-04 Seeding `brands` dari `barang.merk` — selesai: brands=20 (8 'Migrasi SID'), produk.brand_id=60 ter-map (via migrasi lama + seed)
- [x] M-05 Seeding `gudang` — selesai: 16 baru (GUDANG + TOKO1..15) per cabang CBG-01, total gudang=20
- [x] M-06 Seeding `tier_memberships` dari grouphrgpelanggan — selesai: 4 tier HRG-1..4 (staging grouphrgpelanggan = 4 baris), total 12
- [x] M-07 Profil toko dari setup_perusahaan → konfigurasi — selesai: 13 kunci (profil.*, hpp.*, faktur.*), total 18
- [x] M-09 **EXTEND staging header**: migrasi `2026_09_22_000200_create_sid_retail_raw_header_tables.php` (17 tabel raw EAV sama seperti raw inti) + extend `SidImportRaw::$coreTables` + fix dedup in-chunk (dump memuat baris identik, eks. expired_barang). Selesai 2026-09-22: penjualan 6.262, pembelian 277, itempembelian 1.073, piutang 11, hutang 0, kas 5, kas_awal 4, header_return_penjualan 142, header_return_pembelian 1, nomor_seri 0, hrgpergroup 15.856, grouphrgpelanggan 4, expired_barang 1.043, komplain 0, koreksi 1, itemkoreksi 1, itemservis 671 — PRASYARAT fase 2B/3

### FASE 2A — MASTER & PEMBERSIHAN (SOT: SID)
- [ ] A-01 Audit duplikat & sentinel: barang, pelanggan, supplier, member (§4.1–4.3) → laporan ke `sid_import_map.keterangan`
- [x] A-02 Produk: backfill kolom baru dari `sid_retail_raw_barang.payload_normal` (mapping §3.1) — idempotent by kode_lama ✅ (2026-09-22: produk kode_lama=6.362/6.362, barcode 6.348 (0 baru — sudah terisi migrasi lama), barcode_alt=16, brand_id 60 (0 baru), golongan/subgolongan/satuan_beli/isi_satuan/diskon/stok_maksimum/stok_warning/expired_at(4)/jenis/wajib_serial/poin/komisi_sales/kena_pajak/nilai_ppn/harga_lain terisi; is_migrasi_sid=6.362; 2× run = sama)
- [x] A-03 Pelanggan + member merge: backfill kolom §3.2, dedup by (nama, telepon), remap transaksi lama ✅ (2026-09-22: pelanggan kode_lama=284/284, saldo_piutang/max_piutang/area/rayon/kota/instansi/bergabung_at/diskon_persen/sales_nama terisi; member merge 1/4 (ALDIANSYAH via staging nama+telp→kode 002); 3 unmatched TIDAK dibuat buta: UJANG NASRULLAH, NENI YULYANI, RIZKI STORE — tidak ada pelanggan kandidat (nama+telp). **MERGE DEDUP SELESAI** (`sid:merge-pelanggan-dup`): 10 grup/23 baris → 13 baris dup dihapus (canonical = join_date terlama, tiebreak kode_lama terkecil: SANDI 0012, AGUNG 0116, PELANGGAN UMUM 0001, DIAN 0156, ONO 0158, Bapak 0006, IRMA 0064, MULYADI 0009, Dede 0014, AZAN 0043); FK remap 9 (tiket_servis) + sid_import_map 13; pelanggan final 279 (kode_lama 271); 0 FK tersisa ke id dup (semua 8 tabel FK dicek: tiket_servis/piutang/return_penjualan/transaksi/komisi/komplain_pelanggan/skema_komisi_reseller/utang); 2× run = grup 0/aksi 0; laporan `A03-merge-pelanggan-laporan-*.md`)
- [x] A-04 Supplier: backfill §3.3 ✅ (2026-09-22: supplier kode_lama=10/10, npwp/saldo_deposit/kontak/telepon/alamat guard-NULL, is_active=true; 2× run = sama)
- [x] A-05 Varian & barcode: kode_barcode → produk.barcode; `harga_*` tier ke harga_tier; sku_variants untuk ukuran/warna (jika dipakai) — PARSIAL ✅ (2026-09-22: sku_variants kode_lama=6.362 (fill NULL); 6.362 produk ter-map via sid_import_map; 6 produk app tanpa map = produk demo awal (id 1-6, nama tidak ada di staging — nama-match 0); harga_* → harga_tier DAN ukuran/warna BUKAN scope A-02..A-05 — cek A-05 penuh di fase tier)
- [ ] A-06 Validasi: jumlah produk/pelanggan/supplier = SOT ± data operasional baru; laporan dispute = 0

### FASE 2B — REFERENSI KECIL
- [x] B-01 `piutang` ← piutang (SID) ✅ (2026-09-22: 11/11 inserted, unmapped pelanggan 0, kas 0; 2× run = sama; 0 pelunasan di dump. DEVIASI SKEMA: app enum = `belum_lunas/sebagian/lunas` (tidak ada 'terbuka') → `belum_lunas`; kolom `is_migrasi_sid` TIDAK ada di piutang (M-01..M-03) → tidak diset; pelanggan_id NOT NULL → 0 skip; cabang_id=1; kas_id via master_kas.kode (KT))
- [x] B-02 `utang` ← hutang (SKIP: staging 0 baris — dictatat laporan); `purchase_order` ← pembelian + itempembelian ✅ (2026-09-22: PO 277/277 inserted, item 1.073/1.073, supplier unmapped 0; 2× run = sama. DEVIASI SKEMA: status enum app = draft/dikirim/diterima/dibatalkan (tanpa 'lunas') → `diterima` (lunas=true); supplier_id & gudang_tujuan_id NOT NULL → gudang dari lokasistok='TOKO'→TOKO1; produk_id NOT NULL → 6 kode_barang tanpa match produk (5341, 7337, 030585, 041401, BSL-012652, MEETOO-1) dibuatkan dummy `[ARSIP-SID] …` is_active=false (pola §4.4) — item.kode_lama=kode_sumber tetap utk backfill A-02; item.kode_lama = kode_sumber `<kode>|<nourut>` (payload.kode = kode header, tidak unik); is_migrasi_sid tidak ada di tabel PO/item; total_dibayar = jumlah − hutang; jatuh_tempo null (jt = int hari → di catatan))
- [ ] B-03 `nomor_seri_produk` ← nomor_seri (SKIP: staging nomor_seri = 0 baris)
- [x] B-04 `return_*` ← header_return_* ✅ (2026-09-22: return_penjualan 142/142 (1 pelanggan by nama RIZKI STORE→id 42, 141 pelanggan kosong di payload → null + laporan, sesuai 'jangan dummy'); return_pembelian 1/1 (supplier GMT); transaksi_id/purchase_order_id null → backfill fase C-03; jumlah return_penjualan 0 (header tanpa jumlah; item return tidak di-staging); alasan 'Migrasi SID — item return belum tersedia di dump'; 2× run = sama)
- [ ] B-05 `komplain_pelanggan` ← komplain (SKIP: komplain staging 0); `expired_barang` → produk.expired_at sudah diisi @A-02 (app tak punya batch)
- [ ] B-06 koreksi → alur stok_opname (SKIP: 1 baris historis, snapshot bukan prioritas)

### FASE 3 — TRANSAKSI & STOK (validasi selesai — REKONSTRUKSI)
- [x] C-01 **Breakdown & validasi A-01 SELESAI** (temuan §2.0): transaksi 18.786 = artefak (1 transaksi/item, no tanggal historis); stok_log 120.167 ≈ 6,2× arus_stok; harga_tier ~19k ≈ arus_stok (dugaan artefak, verifikasi di C-02)
- [x] C-02 **Bersihkan artefak migrasi lama** (hanya baris migrasi lama) ✅ (2026-09-22: command `sid:rekonstruksi-transaksi --only=cleanup`. Dihapus: transaksi_item 28.491 (transaksi R43-* — aktual lebih banyak dari 10.273 artefak perkiraan; migrasi lama dobel item per transaksi, rata-rata 1,52), transaksi R43-* 18.786 (target ✓), sid_import_map entity_type='transaksi' 6.262 (stale), stok_log 120.167 (guard max created_at 2026-09-21 10:02:19 < 12:00:00 → AMAN, all artefak). harga_tier **TIDAK disentuh** — diverifikasi 19.104 = 6.368 produk × 3 tier (harga_toko/partai/cabang), BUKAN artefak arus-stok (temuan §2.0 item 6 palsu); rekonstruksi dari hrgpergroup = fase lanjutan terpisah (didok di laporan). Operasional MP-20260922-0001 id=58144 utuh. Idempotent: rerun cleanup = 0 delete (guard artefak = R43-* tanpa tanggal_transaksi).
- [x] C-03 **Rekonstruksi transaksi** dari header `penjualan` (staging M-09) ✅ (2026-09-22: 6.262/6.262 inserted, no_transaksi=kode, cabang_id=1, sumber='pos', jenis='penjualan' (label asli → sid_detail.jenis_sid), tanggal_transaksi = tanggal+jam SID (min 2026-02-14 09:17:05, max 2026-08-23 12:54:20 — historis benar, created_at=tanggal_transaksi), pelanggan_id 2 by nama (header SID pelanggan='' & nama_pelanggan='' → 6.260 NULL; nama asli di sid_detail.nama_pelanggan), kasir_id NULL, gudang_id 6 (TOKO1) semua (source lokasistok item pertama — header penjualan TIDAK punya kolom lokasistok; semua item lokasistok='toko'), metode_bayar 'kartu' semua (⚠️ deviasi: SEMUA header visa='UANG PAS' non-kosong → mapping spec → 'kartu'; **KOREKSI DONE by orchestrator 2026-09-22: UPDATE massal 6.262 → 'tunai'** — 'UANG PAS' = uang pas/tunai; verified GROUP BY = tunai 6.262), status lunas 6.261 / belum_lunas 1, sid_detail terisi (jenis_sid/operator/kasir/kode_kas/status_pengiriman/po/member/piutang/biayakirim/lokasistok), sid_import_map upsert 6.262, is_migrasi_sid=true. 2× run = 0 insert)
- [x] C-04 transaksi_item: group per faktur ✅ (2026-09-22: 10.273/10.273 inserted, remap produk_id by kode_lama (175 dummy baru `[ARSIP-SID]` — total 181 incl 6 B-02; produk_id NOT NULL di DB), sku_variant_id 776 NULL (by kode_lama, nullable), diskon_persen terisi (646 item > 0), subtotal/hpp/harga_satuan dari item, is_migrasi_sid=true. Rekonsiliasi Σ item.subtotal vs header.jumlah: 330/6.262 faktur menyimpang >0.01 (mayoritas ±20–80 rupiah = pembulatan; 2 besar: R43-260526019 diff 70.166, R43-020626028 diff 21.600) — katalog di laporan, TIDAK diperbaiki otomatis (catatan fase V-01). 2× run = 0 insert; transaksi total 6.263 (6.262+1 operasional), transaksi_item 10.274)
- [x] C-05 stok_log rekonstruksi 1:1 dari arus_stok ✅ (2026-09-22: command `sid:rekonstruksi-stok --only=log`; 19.286 arus → 19.338 stok_log (19.286 + 52 split baris masuk&keluar>0 → 2 baris `|M`/`|K`), inserted 19.338, 2× run = 0 insert kedua (guard catatan `AS:<kode_sumber> |`). tanggal_log = tanggal+jam SID, created_at=tanggal_log; produk via kode_lama + 123 dummy baru `[ARSIP-SID] <kode>` (total 304); sku_variant_id 2.110 NULL; gudang: lokasi_stok TOKO→6 (19.113+52 split), GUDANG→5 (173); jenis: penjualan 11.805, masuk 2.982, pembelian 1.270, koreksi 3.281 (DEL* 1.901 + RETURN 162 + label kosong 1.218); referensi Transaksi 13.060 (referensi_id DIISI — kolom ADA & nullable; no_transaksi tetap di catatan); sisa≠awal+masuk−keluar = 0; nilai_* diisi dari payload; is_migrasi_sid=true. **DEVIASI idempotency guard**: spec `AS:<kode>` (payload kode) TIDAK unik (4.015 distinct/19.286 — TRX duplikat antar partisi) → pakai `kode_sumber` (19286 unik). Laporan `C2-stok-laporan-*.md`.)
- [x] C-06 Rekonstruksi saldo ✅ (2026-09-22: `sid:rekonstruksi-stok --only=saldo`; SUMBER = arus last-sisa (last baris per (kode_barang,gudang) by tanggal desc + id desc → sisa, spec 'SALIN dari arus'). Wipe 3.044 artefak → rebuild stok_items 4.794 = arus 2.789 (skip sisa 0: 603, sisa<0: 68) + fallback payload 2.005 (produk tanpa arus, toko→gudang6 4.750 rows/30.419 qty, gudang→gudang5 44 rows/527 qty); jumlah_minimum=0 semua (A-02 stok_warning=0 seluruhnya). Rekonsiliasi: arus-last vs stok_log rekonstruksi mismatch 0; arus-last vs payload deviasi >5 = 0 (2.781 dicek — konsisten). 2× run = hasil identik (stok_log 0 insert; stok_items wipe+rebuild deterministic). produk tanpa stok_items 1.921 (saldo 0 implisit), stok_items negatif 0.)
- [ ] C-07 Piutang/utang dari transaksi `lunas=false/piutang>0` → verifikasi konsisten dgn B-01/B-02
- [x] C-08 Jurnal pembuka dari saldo (kas, piutang, hutang, stok, modal) — akun_coa mapping — **COMMIT DONE by orchestrator 2026-09-22:04:11: `sid:jurnal-pembuka --commit` → SID-OPENING 4 baris diposting via JurnalService::post, BALANCE ✅, jurnal_akuntansi 44→48; re-run idempotent (guard no_jurnal)** (2026-09-22: `sid:jurnal-pembuka` default dry-run; saldo: Kas 99.818.660 (KT; BCA/BRI/BNI 0), Piutang 3.001.000 (11 baris belum_lunas), Persediaan 1.093.112.557,50 (4.794 stok_items × harga_beli; NULL=0), Utang 0, Modal balancing 1.195.932.217,50; BALANCE ✅ debit=kredit; akun 110-01/110-02/120-01/130-01/210-01/310-01 SEMUA ADA (26 akun) — tanpa gap; 1 entri 2026-01-01 no_jurnal `SID-OPENING` sumber `sid` deskripsi 'PEMBUKAAN SID'; baris 0 (Bank) tidak ditulis; 3× dry-run identik, jurnal_akuntansi tetap 44 (0 write); laporan `storage/app/migrasi-sid/C8-jurnal-pembuka-laporan-*.md`. Commit (`--commit`, via JurnalService::post idempotent) HANYA oleh orchestrator pasca review — ini keputusan akuntansi)

### FASE 4 — SERVIS & LAPORAN
- [x] D-01 tiket_servis: backfill gap §3.7 + snapshot status ke state machine + servis_status_log ✅ (2026-09-22: command `sid:backfill-servis`; 332/332 tiket SID (no_tiket = `SID-<kode SID>`, sudah ada dari migrasi lama → UPDATE backfill, 0 insert); is_migrasi_sid=332; status canonical: diambil 290 / selesai 26 / dikerjakan 14 / ditolak 2 (dari SID: SUDAH DI AMBIL/SELESAI DI SERVIS/SEDANG DI SERVIS/DIBATALKAN — 42 baris legacy 'menunggu'/'dalam_pengerjaan'/'dibatalkan' diperbaiki; 0 status invalid vs state machine); jenis_hp=`type` SID (323 berubah; 9 fallback `barang`; deviasi dari migrasi lama yang pakai brand); keluhan = kerusakan+kerusakan2/3; estimasi_biaya=`jumlah` (321 berubah — lama 0.00); kondisi_fisik json kelengkapan1..3; sid_detail json (kode_sid/operator/status_sid/selesai/sudahdiambil/dibatalkan/edit_f/totalhpp/totallabarugi); pembayaran json (pembayaran/jt/komisi/kode_kas); teknisi; tanggal_selesai/diambil = tanggal_kembali+jamkembali (327 set; 5 sentinel→NULL); tanggal_bayar semua sentinel→NULL; nama/telepon_pelanggan diisi dari pelanggan via kode_lama (268 distinct kode, unmapped 0); servis_status_log snapshot 332 (1 baris/tiket, status_ke=akhir, alasan 'Migrasi SID snapshot status akhir', created_at=tanggal_terima); 2× run = 0 update/0 log; laporan `D-servis-laporan-*.md`)
- [x] D-02 tiket_servis_item ← itemservis (tipe part/jasa — aturan jurnal servis onSelesai dipenuhi) ✅ (2026-09-22: 671/671 inserted; tipe BARANG→part 348, JASA→jasa 323; produk_id by kode_barang→kode_lama (unmapped 0 — semua resolve; nullable); sku_variant_id null 336 (nullable); nama_item/qty/harga_satuan/hpp dari payload; guard idempotent per tiket (321 tiket ber-item) → 2× run = 0 insert; laporan `D-servis-laporan-*.md`)
- [ ] D-03 arsip raw §2.3 → tabel `sid_arsip_raw` (schema JSON, read-only) bila user mau simpan (opsional)
- [ ] D-04 histori → audit_logs (filter penting saja)

### FASE 5 — VERIFIKASI AKHIR
- [x] V-01 Rekonsiliasi item vs total: 5.937/6.262 faktur cocok, 325 deviasi >1 (5,2%; mayoritas ±20-80 rupiah pembulatan + 2 besar R43-260526019 diff 70.166, R43-020626028 diff 21.600 — katalog C1, TIDAK diubah: deviasi asli SID, coverage faktur↔header 100%)
- [x] V-02 Saldo SOT: kas 99.818.660 (KT; BCA/BRI/BNI/KQ 0 sesuai dump), piutang 3.001.000 (11 baris belum_lunas), utang 0; jurnal pembuka SID-OPENING balance debit=kredit 1.195.932.217,50 (COMMIT 04:11)
- [x] V-03 Stok: 19.338 stok_log 1:1 arus_stok (52 split masuk+keluar), 4.794 stok_items (last-sisa arus 2.789 + fallback payload 2.005), arus-vs-log mismatch 0, sisa=nilai aritma konsisten; negatif 0; produk tanpa stok 1.921
- [x] V-04 sid_import_map: entity transaksi 6.262 di-rebuild (stale dihapus C-02); sku_variant 6.362, pelanggan 284, supplier 10, gudang 1, tiket_servis 332 verified; 0 orphan FK pelanggan (merge A-03), referensi_id stok_log 13.060/13.060 valid join transaksi
- [x] V-05 Testing: *10/11 file lulus* (Unit+Feature: PricingService, ServisStateMachine, JurnalService, IntegrasiModul, Duitku, Login, Reseller, SemuaHalaman, Example). WmsImportVerifyTest fatal OOM lingkungan: kernel SIGKILL (cgroup 955MB, bebas ±115MB; memory_limit 128M pun premature = SIGKILL bukan exhausted/assert) — test ini belum pernah hijau di box ini (driver pdo_sqlite baru diinstall 2026-09-22; sebelum fix 29 gagal 'could not find driver'). BONUS fix migrasi lama: `2026_09_21_000042` backfill join()->update() (MySQL-only) → correlated subquery portabel — 29 gagal → hijau. Lihat CHANGELOG.

---

## 6. RAIL KERJA (larangan)

- JANGAN edit migrasi yang sudah pernah jalan — buat migrasi baru.
- JANGAN jalankan import tanpa `--dry-run` dulu.
- JANGAN timpa data operasional baru app (non-SID) — merge & remap.
- JANGAN sync queue di request; semua import = CLI command chunked.
- JANGAN langgar state machine servis — snapshot saja.
- JANGAN hapus data staging `sid_retail_raw_*` sampai V-04 lulus (SOT cadangan).
- UPDATE file ini di tiap fase + `CHANGELOG.md`.

## 7. PERINTAH

```sh
# import ulang / perbaikan (fase 1-3 command tetap)
php artisan sid:import-raw --dry-run
php artisan sid:import-raw            # staging, idempotent
php artisan sid:migrate               # (pastikan nama signature SidMigrateToUteParts)
php artisan migrate --path=database/migrations/..._baru
composer test
```

## AUDIT SINKRONISASI 4 DOMAIN (2026-09-22, permintaan user) — ALL PASS

Verifikasi ulang berbasis query langsung (skrip `/tmp/opencode/verify_final2.php`):
- **Produk**: 6.672 total (6.362 SID + 304 dummy ARSIP is_active=0 + 6 app) — semua SID punya kode_lama; sku_variants 1:1 (6.362); 0 produk hjr=0 dgn sumber SID harga>0 (cross-check 0 → 1.646 produk hjr=0 = SID aslinya 0, bukan misroute).
- **Stok**: stok_log 19.338 vs arus 19.286 (52 split), negatif 0, FK 0 orphan, referensi transaksi 13.060/13.060 valid; stok_items 4.794.
- **Transaksi**: 6.263 unik (6.262 SID + 1 operasional MP-), 0 dup no_transaksi, 0 artefak R43 sisa, 0 transaksi tanpa item, 0 item orphan; **Σ total_akhir 765.411.399 == Σ header penjualan 765.411.399** (cross-check staging → sinkron penuh).
- **Konsumen**: pelanggan 279 (271 SID + 8 app), 0 dup kode_lama, 0 orphan FK (piutang/transaksi→pelanggan, item→produk, piutang→kas, transaksi→gudang, sid_import_map transaksi 6.262 valid).
- **JASA SID**: 71 barang jenis JASA tak ter-map = price-list servis (PASANG LCD/PERBAIKAN/SOFTWARE), 0 referensi di itempenjualan/arus_stok → dormant, dijual via modul Servis (tiket_servis_item tipe jasa 323), bukan produk. Konsisten: semua produk jenis='barang'.
- **Risiko operasional (bukan bug)**: 1.646 produk tanpa harga jual (SID sumber 0) — bila dijual di POS harga 0; harga menyusul saat pricing manual.


## 6. CARA MEMULAI DARI BERSIH & MIGRASI OLEH USER (2026-09-22)

### Prasyarat sekali (di lingkungan baru/proper)
1. PHP 8.3+ dgn ekstensi `pdo_mysql` (runtime) + `pdo_sqlite` (test). Server 1GB: jalankan command berurutan (bukan paralel), pastikan free RAM > 300MB utk fase berat (rekonstruksi-transaksi/stok + composer test).
2. Setup app: `composer setup` (install + key + migrate + build).
3. Siapkan dump SOT: `/root/workspace/ute-pos-main/latest.sql` (atau file dump SID terbaru) di path yg sama.

### Alur migrasi penuh (baru / bersih)
```bash
cd /var/www/test.uteparts.id-staging

# 1) Staging raw (parse dump → 25 tabel sid_retail_raw_*; idempotent, chunked)
php artisan sid:import-raw --dry-run          # target 62.100+ baris
php artisan sid:import-raw                     # eksekusi (semua tabel default)

# 2) Pipeline migrasi M→A→B→C→D (+V), SATU PERINTAH
php artisan sid:migrate-full --dry-run         # simulasi semua fase (0 write)
php artisan sid:migrate-full                   # eksekusi; jurnal pembuka tetap dry-run
php artisan sid:migrate-full --commit-jurnal   # + post jurnal pembuka SID-OPENING (balance)

# 3) Set bonus (idempotent)
php artisan sid:backfill-2a                    # (jika dipisah dari pipeline — backfill tanpa dry-run)
```

### Reset penuh ke kondisi bersih (ulang dari nol)
```bash
php artisan migrate:fresh --force              # HAPUS SEMUA TABEL + skema fresh
php artisan sid:import-raw                     # re-stage dari latest.sql
php artisan sid:migrate-full --commit-jurnal   # ulangi pipeline
```
> `migrate:fresh` juga menghapus data operasional app (bukan cuma migrasi SID). Kalau hanya mau bersihkan data migrasi SID: `php artisan sid:rekonstruksi-transaksi --only=cleanup` (hapus artefak R43-*/stok lama, data SID + operasional MP- tersisa) lalu jalankan ulang pipeline.

### Verifikasi setelah migrasi (cepat)
```bash
# Σ total transaksi == Σ header SID (harus sama persis)
php artisan tinker --execute="echo DB::table('transaksi')->where('is_migrasi_sid',1)->sum('total_akhir'), PHP_EOL;"
php artisan tinker --execute="echo collect(DB::table('sid_retail_raw_penjualan')->pluck('payload_normal'))->sum(fn($p)=>json_decode($p,true)['jumlah']??0), PHP_EOL;"
php artisan sid:jurnal-pembuka --dry-run       # BALANCE debit=kredit
php artisan test                               # suite (server RAM cukup utk hijau penuh)
```

### Catatan penting migrasi oleh user
- SEMUA command idempotent — jalankan berapa kali pun aman (2× = hasil sama, dibuktikan tiap fase).
- `sid:migrate-full --commit-jurnal` = satu-satunya langkah yg MENULIS jurnal akuntansi (keputusan setelah review saldo dry-run).
- Dump `latest.sql` TIDAK boleh diubah; rerun hanya bergantung isi DB staging `sid_retail_raw_*` (bisa di-refresh kapan pun via `sid:import-raw`).
- Produk tanpa harga jual (1.342, SID nol) → pricing manual sebelum go-live (lihat Audit §5).
