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

### Fase 4 — Servis Deepening
- **[T-16]** `JenisServis` lebih lengkap: `kategori(hardware/software)`, `estimasi_durasi`, `butuh_part` (+migration `2026_09_17_000032`). CRUD **editable** di Pengaturan → Master Data (modal tambah/edit + tombol baru).
- **[T-17]** `TiketServisItem` (split `part`/`jasa`), endpoint `POST /api/servis/{id}/pekerjaan` (SERVICE-06c): baris jasa tak sentuh stok; baris part kurangi `StokItem` 1x + StokLog (lockForUpdate). `onSelesai` pakai items utk jurnal akurat (Pendapatan Jasa 420-01 vs Pendapatan Penjualan part 410-01 + HPP) — fallback ke `ServisSparepart` lama. Test integrasi part/jasa + balance.
- **[T-18]** Komponen Blade reusable **`<x-customer-picker>`** — pencarian live + "+ Baru"; dipakai di POS & Terima Unit Servis (satu sumber data, `setPelangganServis` + quick action).
- **[T-19]** Kunci gadget: `tipe_kunci(pola/pin/password/tidak_ada)` + `kunci_terenkripsi` (cast `encrypted` at-rest); input pola grid 3×3 (urutan angka), PIN/password via password field; `SERVICE-03 show` hanya menampilkan kunci utk teknisi yg ditugaskan / admin-toko / super-admin (selain itu di-hidden).
- **[T-20]** Input foto terima unit: atribut `capture="environment"` → buka kamera langsung di HP.

### Fase 5 — CRM & Reseller
- **[T-21]** Reseller daftar + skema komisi custom per kategori: `skema_komisi_reseller` (+migration `2026_09_17_000033`), endpoint `RESELLER-05 POST /api/reseller/daftar` + `RESELLER-06 GET skema/{id}`; `KomisiService` honor override custom → fallback default (test: 10% LCD ≠ 5% default).
- **[T-22]** Strategi loyalitas editable: `konfigurasi` (key-value) + `[API: CRM-07] GET/POST /api/crm/config` — poin earn %, poin redeem rupiah, diskon tier Silver/Gold/Platinum (diterapkan ke tier_memberships, non-retroaktif). Test: update config → tier ter-update.
- **[T-23]** Broadcast kampanye: `kampanye_broadcast` + `notifikasi_keluar.kampanye_broadcast_id` (+migration `2026_09_17_000034`), `BroadcastService` (segment: tier/reseller/belum belanja N hari, template `{nama}`/`{tier}`, kirim sekarang / jadwal, **anti-dobel** via idempotent check, log per penerima), endpoint `[API: CRM-08] POST /api/crm/broadcast/kampanye` + `CRM-09 GET /broadcast/{id}/log`.

### Fase 6 — Akunting
- **[T-24]** Export laporan Excel **async via queue**: `ExportLaporanJob` + `ExportLaporanService` (maatwebsite) untuk Laba Rugi, Neraca, Buku Besar, Stok, Pelanggan, Servis, Piutang, Utang; `[API: ACC-11] POST /api/akunting/export` (menjadwalkan job) + `ACC-11b GET /export/download?path=` (download setelah job). Laporan besar tidak memblokir request (RAM 1GB).

### Fase 7 — RBAC Fleksibel
- **[T-25]** `RbacFlexController`: `[API: RBAC-05] CRUD roles`, `RBAC-06 PUT /roles/{id}/permissions` (matrix editable), `RBAC-07 DELETE` role. UI tab **"Role & Permission"** di Pengaturan: checklist permission per role (reuse `permissionsList`), role baru dgn permission bebas, **guardrail**: super-admin tidak bisa dihapus/dikosongkan (anti lockout) + audit log.

### Fase 8 — Omnichannel Refinement
- **[T-26]** `StockMutationLog` (Source-of-Truth mutasi stok, +migration `2026_09_17_000035`): dicatat oleh **semua** jalur mutasi — `StokDeductionService` (POS/order/duitku-lunas), `ProdukService` (PO/stok awal), transfer kirim/terima, opname approve, servis part (item pekerjaan). `channel_orders.estimasi_biaya_platform` (est. biaya admin, configurable `biaya_persen` per channel, default 5%) + COA baru `520-06 Beban Biaya Admin Marketplace` & `520-07 Selisih Kas`.

### Fase 9 — Dashboard & Analitik
- **[T-27]** `DASH-01` diperluas data siap-chart (server-computed): **Tren Omzet 30 hari** (line/bar), **Komposisi omzet per kategori** (bar), **Status servis per tahap kanban** (bar), **Piutang aging** (bucket 0-30/31-60/61-90/>90), **Stok kritis top-10** (bar). Widget role-aware (`$currentRole` badge); chart SVG ringan tanpa library tambahan (RAM-friendly).