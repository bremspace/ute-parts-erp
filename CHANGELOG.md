# Ute Parts — CHANGELOG

Semua ringkasan task yang selesai dari `implement-plan.md` (pasca-MVP).
Format: `[Fase X] T-XX — ringkasan`.

## 2026-09-17

### Fase 0 — Fondasi Data Uji
- **[T-01]** `RoleUserSeeder` — 1 user per role PRD §3 (`super-admin/admin-toko/kasir/teknisi/staff-gudang/finance/marketing` @ `*.uteparts.test` / `password`), multi-cabang, idempotent.
- **[T-01]** `TransaksiDemoSeeder` — semua jenis transaksi: POS (tunai/transfer/tier Silver-Gold-Platinum/reseller+komisi), marketplace lunas (dummy-paid), channel shopee dummy, 10 tiket servis di tiap status (+ expired & masa garansi), transfer pending+selesai, opname selisih, broadcast contoh. Semua jurnal balance (debit=kredit) — idempotent (guard `DEMO-`).

### Fase 1 — Bug Kritis
- **[T-02]** Ganti cabang: nama cabang aktif disimpan di session (`cabang_nama`), route web `POST /app/pilih-cabang`, modal Alpine di sidebar → full reload agar Dashboard/POS/WMS re-query; tab aktif persist di refresh.
- **[T-03]** Park POS: tombol "Tahan (F6)" menyimpan keranjang ke `Transaksi(status=ditahan)` + snapshot di `split_detail`, panel "Transaksi Ditahan" list per cabang + tombol Lanjutkan (resume → muat ulang keranjang). Endpoint `[API: POS-04]` tahan & `POS-05` daftar.
- **[T-04]** Tambah pelanggan CRM: `[API: CRM-06] POST /api/crm/pelanggan` + modal "Tambah Pelanggan" di halaman CRM (is_reseller toggle, tier terendah otomatis), langsung tampil tanpa reload.
- **[T-05]** Hapus tombol "Tambah Produk" dobel di halaman Master Produk (header + search bar) — tersisa 1.
- **[T-06]** RBAC tracking: kanban `/app/servis` kini `middleware('permission:servis.view')` (staf tanpa akses → 403, teruji); halaman `CustomerAccount` menampilkan servis hanya milik pelanggan login (`pelanggan_id` check); tracking publik `/tracking/{token}` hanya info minimal (status+estimasi, tanpa data pribadi/foto — cek kode existing).

### Fase 2 — POS & Kas
- **[T-07]** POS pencarian: combobox barcode/search (debounce 250ms), **Enter = scan barcode** → exact-match SKU → auto-add keranjang (`scanEnter` + tombol ADD).
- **[T-08]** POS quick-add pelanggan: `[API: POS-06] GET /api/pos/pelanggan?search=` + pencarian live + modal "+ Baru" → reuse `CRM-06` (satu sumber data).
- **[T-09]** Kas sesi: entitas baru `kas_sesi` (+migration `2026_09_17_000030`), `Pos\Services\KasSesiState` — buka (jurnal Debit Kas/Kredit Modal Kas Shift), tutup (saldo sistem = saldo awal + transaksi tunai selama shift, selisih → jurnal "Selisih Kas"), **transaksi POS tunai diblokir 422 tanpa kas terbuka**, UI banner + modal buka/tutup di POS. Endpoint `[API: POS-07/08/09]`.

### Fase 3 — WMS Deepening
- **[T-10]** Supplier + PurchaseOrder + PembayaranSupplier (entitas + migration `2026_09_17_000031`), `PurchaseOrderService`: PO diterima → stok bertambah (reuse `ProdukService::tambahStokPembelian`) + jurnal (Persediaan debit / Kas-Utang kredit); PO kredit → utang; bayar parsial → jurnal Utang debit/Kas kredit + sisa terupdate. Endpoint `[API: WMS-09..12]`, tab "PO & Supplier" di WMS (daftar PO + status pill + aksi Kirim/Terima/Bayar + modal buat PO & supplier). Test alur PO kredit penuh.
- **[T-11]** Master produk: field `barcode` (unique), `foto[]`, `meta_title`, `meta_description`, `kompatibilitas_hp[]` (terstruktur) + tabel referensi `satuan_unit` + `sku_variants.satuan_kode/barcode`. Produk fillable diperluas.
- **[T-12]** Manajemen Rak: entitas `Rak`, `stok_items.rak_id` (nullable FK), modal "Atur Rak" di tab Stok WMS + CRUD (`simpanRak`).
- **[T-13]** Transfer antar gudang: tambah `lockForUpdate()` pada stok asal & tujuan dalam DB transaction (anti race/oversell saat proses kirim/terima bersamaan).
- **[T-14]** *(item opname barcode: alur opname existing + komponen BarcodeScanInput sudah reusable dari POS — pelacakan histori via tabel opname + logs)*.
- **[T-15]** Auto-generate barcode (`UTP-{id}-{checksum}`) + library `picqer/php-barcode-generator`, endpoint `[API: WMS-14]` + halaman cetak label multi-produk `/print-barcode?ids=1,2` (print-friendly, SVG code128) + tombol Generate & Cetak di Master Produk.

### Fase 4 — Servis Deepening (berikutnya)