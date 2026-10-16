# MIGRATION SID RETAIL — Staging RAW (T-42, Fase 1)

> **Scope fase 1 (selesai):** staging snapshot mentah dump POS SID Retail ke tabel
> `sid_retail_raw_*` di `db_staging` + dokumentasi migrasi. **TIDAK** menyentuh
> tabel Ute Parts existing. Mapping ke skema final (fase 2) hanya berupa
> rekomendasi di dokumen ini dan menunggu skema produk komprehensif (T-44).

**Status:** DONE — diverifikasi 2026-09-21 (db_staging)

---

## 1. Sumber Data

| Item | Nilai |
|---|---|
| File | `/root/workspace/ute-pos-main/latest.sql` (20.8 MB) |
| Format | Export teks POS Software-ID (SID Retail), **bukan** mysqldump standard |
| Tabel sumber | 212 tabel, 1.146 statement `INSERT` (total baris 36.623 utk 8 entitas inti) |
| Separator statement | `\r\r` (CR-CR) — **bukan** `\n`; statement bisa berbagi "garis" fisik dengan SQL lain (CREATE/ALTER) |
| Nilai string | kutip ganda `"..."` dengan escape `""` (double-quote) |
| Boolean | `varchar(5)` berisi literal `'True'` / `'False'` |
| Tanggal kosong | `1899-12-30` (default SID), kadang `0000-00-00` |
| Jam | string 12-jam (`"12:00:34 PM"`) |
| PK | string via `ALTER TABLE ... ADD PRIMARY KEY` |

## 2. Tabel Staging yang Dibuat

Migration tunggal `database/migrations/2026_09_21_000045_create_sid_retail_raw_tables.php` — 8 tabel:

| Tabel raw | Sumber dump | Baris | Kunci `kode_sumber` |
|---|---|---|---|
| `sid_retail_raw_barang` | `barang` | **6.433** | `kode` |
| `sid_retail_raw_pelanggan` | `pelanggan` | **284** | `kode` |
| `sid_retail_raw_supplier` | `supplier` | **10** | `kode` |
| `sid_retail_raw_member` | `member` | **4** | `id_kartu` |
| `sid_retail_raw_servis` | `servis` | **332** | `kode` |
| `sid_retail_raw_setup_perusahaan` | `setup_perusahaan` | **1** | konstanta `SETUP-PERUSAHAAN` |
| `sid_retail_raw_itempenjualan` | `itempenjualan` | **10.273** | `kode\|nourut` |
| `sid_retail_raw_arus_stok` | `arus_stok_2_2026`..`_8_2026` (tabel `arus_stok` asli kosong) | **19.286** | `tabel_sumber\|kode` |

Setiap tabel: `id`, `kode_sumber` (string(255) **unique**), `tabel_sumber` (nullable+index;
diisi untuk arus_stok partisi), `payload_json` (LONGTEXT — nilai **asli** persis dump),
`payload_normal` (LONGTEXT — nilai ternormalisasi), `diimport_at` (nullable — diisi fase 2
saat baris dipakai), `timestamps`.

## 3. Command Importer

```sh
# Import 8 tabel inti (default) — idempotent, boleh dijalankan berulang
/usr/bin/php8.5 artisan sid:import-raw

# Dry-run: hitung & tampilkan rencana TANPA menulis DB (mandat PRD §4.11)
/usr/bin/php8.5 artisan sid:import-raw --dry-run

# Opsi lain
#   --tables=barang,pelanggan      tabel spesifik (non-inti → kode_sumber = md5 konten)
#   --all                          semua tabel dalam dump yang punya INSERT
#   --file=/path/latest.sql        lokasi dump berbeda
#   --chunk=500                    ukuran batch INSERT (default 500)
```

Didaftarkan di `bootstrap/app.php` (`->withCommands([..., SidImportRaw::class])`) **tanpa
schedule** — importer adalah operasi manual/sekali-jalan, bukan tugas terjadwal.

### Cara kerja (aman RAM 1GB)
- **Pass 1** — `scanTables()`: ekstrak nama + tipe kolom tiap tabel dari statement
  `CREATE TABLE` (streaming baris-per-baris).
- **Pass 2** — `streamStatements()`: streaming buffer (~1 MB, compact saat >1 MB);
  lokasi statement `INSERT INTO … VALUES` TIDAK mengandalkan posisi awal baris
  (separator dump `\r\r`, bukan `\n` — statement bisa satu "garis fisik" dengan
  CREATE/ALTER); `statementEnd()` menutup statement di `)` depth-0 yang diikuti `;`
  (baris lanjutan `,` diteruskan, bukan dianggap incomplete); `splitRows()` memecah
  VALUES per baris (string-aware, escape `""`); **0 field-count mismatch** divalidasi
  per baris (anti korupsi data — statement berhenti aman bila tidak cocok).
- **Idempoten** — sebelum insert, `whereIn(kode_sumber)` per chunk; yang sudah ada
  di-skip. Diuji 3x berturut: run-1 36.623 insert, run-2 (fix separator) 1.221 insert
  + 35.402 skip (total DB tepat), run-3 **0 insert / 36.623 skip** → tidak dobel.

### Verifikasi staging (db_staging)
- 8 tabel: jumlah baris **persis** = baseline parser Python (barang 6.433, pelanggan 284,
  supplier 10, member 4, servis 332, setup_perusahaan 1, itempenjualan 10.273, arus_stok 19.286).
- `kode_sumber` unik: **0 duplikat** di semua tabel; `arus_stok.tabel_sumber` tidak ada NULL
  (7 partisi: `arus_stok_2_2026`..`arus_stok_8_2026`).
- Peak memory ~52 MB, total waktu ~17–21 s per pass.

## 4. Normalisasi `payload_normal` (nilai `payload_json` tetap asli utk audit)

| SID raw | → | Normal | Contoh terverifikasi |
|---|---|---|---|
| `NULL` | → | `null` | — |
| `'True'` / `'False'` | → | `true` / `false` | pelanggan `tampil` = `false` |
| `1899-12-30`, `0000-00-00`, `0000-00-00 00:00:00` | → | `null` | pelanggan `tgl_lahir` = `null` |
| `"12:00:34 PM"` (12-jam) | → | `"12:00:34"` (24-jam) | — |
| string numerik dgn tipe kolom numerik (int/decimal/double) | → | int/float | barang `diskon` = `0` (int) |

Tipe kolom numerik diambil dari CREATE TABLE dump saat Pass 1 (map `columnTypes` per
tabel sumber). Sisa nilai tetap string.

## 5. Mapping ke Skema Ute Parts — REKOMENDASI FASE 2

> Daftar kolom sumber = kunci dari `payload_*` (persis dump). Fase 2 memilih kolom yang
> relevan, sisanya bisa disimpan di kolom `data_json` Ute atau diabaikan.

### 5.1 Mapping entitas

| SID Retail (jumlah kolom) | → Ute Parts | Catatan |
|---|---|---|
| `barang` (148 kol) | → `produk` | `kode`→barcode/kode internal, `nama`/`nama2..4`→nama+alias, `kategori`, `satuan`/`satuan2..4`+`isi`, `harga_toko`→`harga_jual_retail`, `harga_beli`→`harga_beli`, `hpp`→HPP awal, `gambar`, `elektrik`/`sn`/`master` → atribut tambahan; `stokmin/max`+`warningstok` → ambang stok; **pastikan validasi final thd skema produk T-44** |
| `pelanggan` (38) | → `pelanggan` | `nama`, `telp`→`telepon`, `email`, `alamat`/`alamat2`, `point`+`sisa`→`poin_loyalty`, `saldo_piutang` → piutang awal (lewat jurnal/piutang), `max_piutang` → limit, `senin`-row `pelanggan_kena_pajak`→pajak; `is_reseller=false` default (reseller SID = field berbeda) |
| `supplier` (16) | → `supplier` | `nama`, `telp`→`telepon`, `alamat`/`alamat2`, `kota`, `contact`→`kontak`, `email`, `no_npwp`→NPWP |
| `servis` (35) | → `tiket_servis` | `kode`→`no_tiket`, `kode_pelanggan`→`pelanggan_id`, `barang`/`type`→`jenis_hp`, `no_imei`→`seri_hp`, `kerusakan`→`keluhan`, `kelengkapan`→`kondisi_fisik`, `status` SID→status tiket (harus via state machine), `jam`/`jamkembali`/`tanggal_kembali`→timestamp, `jasa`/`spare_part`/`jumlah`→estimasi & item servis |
| `itempenjualan` (44) | → `penjualan_item`-like | **tabel transaksi**: mapping butuh tabel induk `penjualan` (header) — lihat §7 |
| `arus_stok` (17) | → `stok_log` (+ `stok` produk) | `kode_barang`, `awal`/`masuk`/`keluar`/`sisa`+`nilai_*` → `jumlah_sebelum`/`perubahan`/`jumlah_setelah`; `transaksi`/`no_transaksi` → `referensi_tipe`/`referensi_id`; **serial per SKU wajib** (anti oversell) |
| `member` (23) | → `pelanggan` (flag reseller/member) | `id_kartu`→kartu, `nama`, `point`, `saldo_piutang`, `max_piutang`, `tgl_lahir` |
| `setup_perusahaan` (208) | → `konfigurasi` / settings | 1 baris konfigurasi cabang: nama, alamat, kota, propinsi, kode akun default (`kd_*`), metode HPP (`metodehpp`), footer nota, dst. |

### 5.2 Kolom SID yang TIDAK punya padanan langsung di Ute Parts (perlu kolom JSON/abai)

- `barang`: sistem harga jual bertingkat SID (harga `toko/toko2..4`×`partai`×`cabang`×`lain`,
  margin per tier, `diskon_*`, `point_k1/k2`, `hp_*`, `tokoN` multi-gudang 1..15, `barcode5..14`,
  `shu_*`, `resep/tuslah`) — **tidak semua** masuk skema produk Ute saat ini; simpan utuh di
  kolom JSON asal bila dibutuhkan analitik.
- `setup_perusahaan`: 208 kolom konfigurasi legacy SID — hanya subset (nama/alamat/kontak/
  pajak/kode akun) yang dipetakan; sisanya dokumentasi historis di `payload_json`.
- `itempenjualan`: kolom POS-spesifik (`nourut`, `kd_barang_paket`, `detail_deb_cc`,
  `detail_disc_global`, `shu_koperasi`, `master_elektrik`, `lokasistok`) tidak memiliki
  padanan 1:1 — dipetakan selektif.

## 6. Relasi yang TERDENORMALISASI di SID (waspada saat fase 2)

| Relasi SID | Bentuk | Implikasi fase 2 |
|---|---|---|
| `itempenjualan.kode_barang`/`nama_barang` | denormalisasi dari `barang` | Validasi ulang kode barang; sebagian `kode_barang` bisa kosong/berubah dari snapshot `barang` |
| `servis.kode_pelanggan` | FK logis ke `pelanggan.kode` | Pelanggan bisa sudah dihapus → simpan `nama` dgn snapshot (sudah ada di dump: `nama_pelanggan`-style) |
| `arus_stok.kode` | **nomor urut per partisi** (TRX restart per bulan) | `kode_sumber` = `tabel_sumber\|kode` (prefix partisi) — jangan gunakan `kode` saja |
| `arus_stok.*` + `barang.harga` | saldo & nilai (`awal/masuk/keluar/sisa` + `nilai_*`) disimpan per baris | Kalau `stok_log` Ute hanya menyimpan delta, hitung ulang saldo dari delta — jangan double-count |
| `servis.jasa`/`spare_part` | teks bebas, bukan FK | Parsing ke `TiketServisItem` (`tipe` = `part` / `jasa`) butuh aturan manual/kamus |
| `setup_perusahaan` | 1 baris, teks footer/WA template ber-`\r\n` | Konversi line-ending saat insert agar tidak membawa CR legacy |

## 7. Urutan Import yang Disarankan (fase 2) + Perlindungan

1. `supplier` → 3. `barang` (butuh supplier_id) → 4. `pelanggan` + `member`
   (member = subset pelanggan) → 5. `tiket_servis` (butuh pelanggan) →
   6. header penjualan + `itempenjualan` → 7. `arus_stok` sebagai `stok_log`
   (`diimport_at` diisi per baris — importer fase 2 memproses yang `IS NULL` saja,
   rollback aman kalau mapping berubah).
2. **Dry-run wajib** sebelum commit import fase 2 (`--dry-run` atau preview Excel/CSV —
   mandat PRD §4.11).
3. Baris yang sudah `diimport_at NOT NULL` **tidak boleh** di-import ulang
   (idempotensi dipertahankan via `kode_sumber` unique + `whereNull('diimport_at')`).
4. `payload_json` dibiarkan utuh sebagai audit trail; jangan diubah oleh fase 2.

## 8. Tabel SID yang TIDAK di-staging (log-only / tidak relevan)

Import **--all** (generik, `kode_sumber` = md5 konten) tersedia tetapi fase 1 sengaja
membatasi ke 8 entitas inti. Tabel non-inti contohnya: `labarugi` (92 stmt), `histori`
(177 stmt), `penjualan` (143 stmt), `pembelian`, `pengeluaran`, `piutang`, `kas`,
`postingtrx`, `hrgpergroup`, `itempembelian`, `itemservis`, `data_kecamatan/kota/provinsi`,
`temp_*`, `buffer_*`, dll. — sekitar 200 tabel sisanya bersifat transaksional/konfigurasi
legacy; dokumentasi skema lengkapnya terekam otomatis di Pass 1 importer (212 tabel).

**Skala:** dump SID = 212 tabel vs ≈ 66 tabel Ute Parts yang dibuat lewat 49 migrasi →
hanya subset kecil yang relevan untuk operasional ERP (barang, pelanggan/supplier,
transaksi penjualan, stok, servis); sisanya sejarah aplikasi POS tersebut.