# Ute Parts — CHANGELOG

Semua ringkasan task yang selesai dari `implement-plan.md` (pasca-MVP).
Format: `[Fase X] T-XX — ringkasan`.

## 2026-09-26 — B-15c: N+1 WMS + Servis (foto kanban, transfer, PO, opname, lead, depresiasi) ✅

Tujuh titik N+1 / pemuatan-seluruh-baris ditutup. Angka sebelum → sesudah **diukur langsung di fixture yang sama** (bukan estimasi): payload baris kanban 30 tiket berfoto **12.020.425 byte → 10.790 byte** (±1.100×), query `stok_items` form transfer **6 → 1** untuk M=3 (2 per baris × M), validasi `[WMS-02]` **N → 1**, guard jurnal depresiasi **A → 1** (8 aset: 8 → 1), baris `stok_opname_item` ter-fetch per render **2.000–10.000 → 0** (count jadi subquery), daftar PO siap diterima **tak terbatas → 50** (dengan pesan Indonesia), dropdown produk **tak terbatas (500–2.000) → 300 + scope cabang**.

**Perubahan per file**

- **`app/Modules/Servis/Livewire/ServisBoard.php`** (P0) — query hydrate kanban (tahap-2 B-07) memakai `select()` eksplisit ke konstanta baru `KOLOM_KANBAN` (13 kolom yang benar-benar dirender kartu) sehingga kolom JSON `foto_unit` — **431.406 byte/baris terukur di db_staging** — tidak lagi ikut ter-fetch: 100 tiket ≈ **43MB JSON per render** hilang. **B-07 tetap utuh**: tahap-1 masih `select "id" … order by created_at desc limit 100` (tahap-2 tetap tanpa `order by`, urutan dikembalikan di PHP), `withCount('spareparts')` tetap hidup. Catatan penting: `select()` harus dipanggil **sebelum** `withCount()`, kalau dibalik subquery `spareparts_count` tertimpa dan badge sparepart ikut hilang. Foto tidak hilang dari fitur: `getSelectedTiketProperty()` tetap `select *` + eager load, jadi galeri di modal detail utuh. **Catatan**: kartu kanban tidak lagi menampilkan thumbnail (`$tiket->foto_unit[0]` di blade servis — file di luar scope lane ini, tidak disentuh); belum ada kolom thumbnail, jadi kartu jatuh ke ikon placeholder sampai kolom `foto_thumb`/endpoint tersedia (lihat "Temuan baru").
- **`app/Modules/Wms/Livewire/TransferTab.php`** — `transferStokRows()` 1–2 query `stok_items` **per baris draft** (2M, M = 1–30) → **satu** query batch `whereIn(produk_id) × whereIn(gudang_id)` + `keyBy` map `gudang:produk:varian` (helper baru `petaStokBatch()`); `stok_items` punya unique `(produk_id, sku_variant_id, gudang_id)` jadi map 1:1 → angka & tampilan identik (dibuktikan paritas). `saveTransfer()`: validasi qty per baris juga jadi 1 query batch. Dropdown: `Gudang` **WAJIB di-scope `cabang_id`** (sebelumnya gudang cabang lain bocor ke dropdown asal/tujuan) + limit 100; `Produk` di-scope stok cabang aktif (`whereHas('stokItems.gudang')` **atau** belum punya stok sama sekali, supaya produk baru tetap bisa dipilih) + limit 300 + toast Indonesia sekali saat modal dibuka bila daftar dipotong.
- **`app/Modules/Wms/Livewire/PoTab.php`** — scope + limit yang sama untuk dropdown gudang tujuan & produk PO (produk cabang lain tidak bocor; produk tanpa stok tetap tampil). Alert pemotongan dikirim dari `openPoModal()` (1 query `count` per buka modal, bukan tiap render).
- **`app/Modules/Wms/Controllers/WmsController.php`** — `storeTransfer()` (validasi pra-transaksi `[WMS-02]`): `foreach` + `StokItem::…->first()` per item → **satu** query batch + map `produk:varian`; pesan error Indonesia per item tetap sama. **Write path `kirimTransfer()`/`terimaTransfer()` yang memakai `lockForUpdate()` SENGAJA tidak disentuh.**
- **`app/Modules/Wms/Livewire/OpnameTab.php`** + **`resources/views/modules/wms/livewire/opname-tab.blade.php`** — `with('items.produk')` (2.000–10.000 baris `stok_opname_item` + satu query produk per item, padahal blade cuma butuh **jumlah**) → `withCount('items')`; blade memakai `$opn->items_count`. `approveOpname()` (jalur tulis) tidak disentuh.
- **`app/Modules/Wms/Livewire/GrnTab.php`** — tabel "PO Siap Diterima" (bukan paginator) dibatasi **50 PO terbaru** + toast Indonesia sekali per halaman. Daftar GRN tetap `paginate(15)` + scoping cabang.
- **`app/Modules/Crm/Livewire/LeadKanban.php`** — `select()` eksplisit (12 kolom): `catatan` (text bebas panjang) tidak pernah dirender di board, dan form edit me-load lead ulang sendiri (`openEditModal()`), jadi tidak ikut ter-fetch. **Baris sengaja TIDAK dipotong** dengan limit — `$summary` (total lead, total nilai, conversion rate) dan funnel di blade diturunkan dari koleksi yang sama, jadi limit akan diam-diam mengubah angka KPI penjualan.
- **`app/Modules/Marketplace/Livewire/CustomerAccount.php`** — `select()` eksplisit untuk daftar tiket (**`foto_unit` dibuang**; halaman pelanggan tidak pernah merender foto, hanya status/keluhan/garansi/link tracking) dan untuk daftar komisi. **Baris juga TIDAK dipotong** — kartu statistik header memakai `$servis->count()` dari koleksi yang sama. Sekalian: `render()` kini_me-passthrough `orders`/`servis`/`komisi`; sebelumnya blade memakai variabel yang tidak pernah dikirim sehingga **halaman Akun Saya 500** (`Undefined variable $orders`) — pola pass-explicit memang diwajibkan AGENTS.md.
- **`app/Modules/Akunting/Services/DepresiasiService.php`** — `prosesPeriode()` jadi 2 tahap: filter kandidat di memori dulu, lalu **satu** `JurnalAkuntansi::whereIn('no_jurnal', …)` untuk guard "jurnal sudah ada" (sebelumnya 1 `exists()` per aset, termasuk aset yang memang harus dilewati). Nilai `diproses`/`dilewati`/`total` & seluruh nominal **tidak berubah** — aset yang di-skip guard 1 / "belum dimiliki" tetap dihitung `dilewati`.
- **`app/Modules/Akunting/Jobs/DepresiasiAsetJob.php`** — parameter kedua opsional `?int $cabangId = null` (default `null` = semua cabang, perilaku jadwal bulanan tidak berubah) supaya tombol manual bisa di-scope ke cabang aktif — tanpa itu jurnal bocor lintas cabang. `handle()` meneruskan scope itu ke `prosesPeriode()`; `failed()` tidak berubah.
- **`app/Modules/Akunting/Livewire/AsetRegister.php`** — `jalankanDepresiasi()` **tidak lagi menjalankan service sinkron di dalam request** (±5 query/aset × 20–200 aset menahan worker PHP-LSAPI); sekarang `dispatch(new DepresiasiAsetJob($periode, $cabangId))` + toast Indonesia "diantre". Job tetap idempoten per periode (aman ditekan dua kali).
- **`tests/Feature/WmsServisNplus1Test.php`** (baru, 29 test / 159 assertion) — budget query + guard duplikat bentuk-SQL (`assertSame([], $dupes`) seperti pola B-15a, ditambah: (a) **P0** 30 tiket × foto 400KB → query kanban tidak memuat `foto_unit`, query B-07 tahap-1 tetap `select "id"`, `spareparts_count` tetap ada, payload board < 300KB, pertumbuhan memory < 8MB (terukur ~4MB), dan detail tiket yang dibuka tetap menampilkan foto; (b) konstanta jumlah query kanban terhadap jumlah tiket (5 vs 30) sebagai bukti anti-N+1; (c) paritas: info stok transfer vs implementasi query-per-baris **dan** vs hitungan manual (termasuk stok terkunci draft T-13), pesan error validasi identik, daftar PO/GRN/lead/tiket pelanggan tetap lengkap, rekap opname = jumlah baris asli, dropdown produk/gudang scoped cabang; (d) depresiasi: guard 1 query batch, dispatch antre (request tidak menulis jurnal), job idempoten 2× jalan (jurnal tetap 6 baris, akumulasi tidak dobel).
- **`tests/Feature/AsetRegisterTest.php`** — 3 test yang mengasumsikan jurnal terbentuk sinkron disesuaikan ke alur baru (`Queue::fake()` + `assertPushed(DepresiasiAsetJob)` + menjalankan job lewat `handle()`), ditambah test baru "request tidak menaruh jurnal".

**Temuan baru (tidak diperbaiki — di luar scope B-15c)**

- **Thumbnail kartu kanban servis hilang** sampai ada kolom `foto_thumb` atau endpoint thumbnail. Blade `resources/views/modules/servis/livewire/servis-board.blade.php:58` masih membaca `$tiket->foto_unit[0]`; file itu milik lane lain jadi tidak disentuh di sini. Perbaikannya 1 baris di blade begitu kolom thumbnail tersedia.
- `JurnalService::post()` masih melakukan 1 query `exists()` idempotensi **per jurnal yang diposting** (bukan per aset) — file di luar scope lane ini.
- `DepresiasiAsetJob` dijadwalkan tanpa argumen (`bootstrap/app.php`) → tetap memproses **semua** cabang setiap tgl 1 (bukan bug, hanya catatan).

**Verifikasi** (per-file — suite penuh dilarang, RAM 1GB)

- `tests/Feature/WmsServisNplus1Test.php` → **OK (29 test, 159 assertion)**.
- `tests/Feature/AsetRegisterTest.php` → **OK (27 test, 115 assertion)**.
- Regresi wajib: `ServisListQueryTest` **OK 5 (30)**; `ServisTerimaUnitTest` **OK 9 (29)**; `ServisStateMachineTest` **OK 6 (33)**; `GrnTest` **OK 23 (135)**; `PosStokSinkronTest` **OK 14 (107)**; `StockMutationActorTest` **OK 12 (83)**; `AsetTetapTest` **OK 8 (18)**; `WmsDashboardSplitTest` **OK 7 (23)**; `SemuaHalamanTest` **OK 9 (55)**.
- Regresi tambahan (kode yang disentuh): `MarketplaceNplus1Test` **OK 25 (118)**; `LeadConversionTest` **OK 9 (55)**.
- `./vendor/bin/pint --test` (418 file) → **PASS**.
- Angka sesudah pada fixture yang sama: kanban 30 tiket **7.795 byte** (baris query) / **10.790 byte** (viewData grouped) vs **12.020.425 byte** (`select *`); render kanban **11 query**, konstan untuk 5 maupun 30 tiket; transfer M=3 **1** query `stok_items`; opname 25 item → **0** baris ter-fetch; depresiasi 8 aset → **1** query guard.

## 2026-09-26 — B-15d: N+1 P1/P2 (dashboard, komisi, konfigurasi, funnel, badge, supplier, cycle count, channel, COA) ✅

Sembilan titik N+1 ditutup. Angka sebelum → sesudah diukur langsung (bukan estimasi): dashboard **42 → 2** query per render Livewire kedua, komisi **2K+2 → 4** (konstan terhadap jumlah kategori), konfigurasi **5 → 1**, funnel lead **12 → 1**, badge approval **1 → 0** per halaman backoffice, skor supplier **51 → 23** (5 supplier × 2 PO), baca stok cycle count **20 → 1** (20 item), guard idempotensi channel **1 per order → 1 per batch**. Angka/widget **tidak diubah** — semuanya dibuktikan paritas (lihat bagian Verifikasi).

**Perubahan per file**

- **`app/Modules/Dashboard/Livewire/DashboardIndex.php`** — semua 15 widget di-backing `Cache::remember` lewat helper `cacheWidget()`; TTL konservatif: **60 dtk** untuk widget live/hari-ini (omzet hari ini, antrian servis, stok kritis, piutang jatuh tempo, komisi pending, transaksi terbaru, status servis, treasury, omzet shift kasir) — sama dengan interval `wire:poll.60s` di blade, **120 dtk** untuk PO pending, **300 dtk** untuk agregat periode (tren 30 hari, komposisi kategori, aging piutang, laba bulan ini, market insight). **Key WAJIB memuat `cabang_id` + role (sorted) + tanggal**, ditambah `user_id` + `sesi kas` untuk widget per-aktor (omzet shift kasir) → tidak bisa bocor antar cabang/user dan tidak menyajikan "hari ini" versi kemarin pas tengah malam. Helper `KasSesiState::sesiKasAktif()` tetap dipanggil tiap render (1 query) karena batas omzet shift = waktu sesi dibuka. Logika & angka tiap widget tidak disentuh.
- **`app/Modules/Reseller/Services/KomisiService.php`** — `hitungKomisiDariItems()`: `foreach` kategori → `SkemaKomisiReseller::…->first()` + fallback `SkemaKomisi::…->first()` (**2 query per kategori**, sinkron di dalam write POS) diganti eager-load **2 query sekali** (`orderBy('id')`) lalu pemilihan "baris pertama yang cocok" in-memory. Rumus nominal tidak disentuh; urutan `id` ASC membuat hasil deterministik dan sama dengan `->first()` lama (PK = urutan insert).
- **`app/Modules/Crm/Services/KonfigurasiService.php`** — `getMany(array $kunci)` baru: **1 query `whereIn`** untuk banyak kunci; `get()` dibungkus `Cache::remember(300)` per kunci dengan **`Cache::forget` di `set()`** (satu-satunya jalur tulis, jadi `setPpn()` ikut tercovers). Nilai dibungkus `['nilai' => …]` karena `Cache::remember()` memakai `! is_null()` sebagai penanda hit — tanpa itu kunci yang belum pernah disimpan akan miss terus-menerus.
- **`app/Modules/Crm/Controllers/CrmController.php`** — `GET /api/crm/config` 5× `$config->get()` → 1× `getMany()` (5 query → 1). Bentuk respons & fallback default tidak berubah.
- **`app/Modules/Crm/Services/LeadService.php`** — `getFunnelData()`: 6 tahap × (`count` + `sum`) = **12 query** → **1** `GROUP BY stage` (`COUNT(*)` + `COALESCE(SUM(nilai_estimasi),0)`). Nilai dikembalikan apa adanya (tanpa cast float) supaya tipe keluaran API tetap sama dengan `sum()` lama; scoping `forCabang()` tidak berubah.
- **`resources/views/layouts/backoffice.blade.php`** — badge "Approval Inbox" di-layout (query di **setiap** halaman backoffice, hit-rate ~100%) → `Cache::remember(60)` per `cabang_id` (`approve-workflow` itu per-role, bukan per-user, jadi satu key per cabang cukup).
- **`app/Modules/Wms/Services/SupplierScoringService.php`** — `Supplier::findOrFail()` redundan per supplier di dalam loop `hitungSemua()` dihapus (kontrak publik `hitungSkor()` tetap 404 untuk supplier tidak ada); 3 query PO per supplier digabung jadi **1 query batch** (`poPeriodeGrouped()` + `with('items')` — sebelumnya `hitungAvgHarga()` lazy-load `->items` per PO = N+1 di dalam N), lalu on-time / quality-return / avg-harga dihitung in-memory dengan padanan SQL yang eksplisit didokumentasikan (termasuk `status != 'draft'` yang membuang NULL dan batas `jatuh_tempo >= now()-30d` versi native Carbon, bukan `Collection::where`). Skor, pembulatan, dan baris `supplier_scores` tidak berubah.
- **`app/Modules/Wms/Services/CycleCountService.php`** — `hitung()`: 1× `StokItem::whereKey(…)->first()` **per item sample** (10-500 item) → satu `whereIn` + `keyBy('id')`; `koreksi()`: idem **per baris hasil** → satu `whereIn`. Helper baru `stokItemTerScoped()` / `idSample()` / `idStokDariHasil()`. Guard scoping gudang cabang, klasifikasi minor/major, dan alur state machine F3-7 (minor → koreksi langsung, major → approval) tidak disentuh.
- **`app/Modules/Omnichannel/Services/ChannelSyncService.php`** — `pullOrders()`: 1× `ChannelOrder::…->exists()` **per order** hasil pull → satu `whereIn` (di-chunk 500) lewat helper `orderIdTerproses()`. Order yang baru dibuat di dalam batch yang sama ikut dimasukkan ke guard set, jadi duplikat di dalam 1 pull tetap ter-skip persis seperti sebelumnya (landmine idempotensi).
- **`app/Modules/Akunting/Models/AkunCOA.php`** — `getSaldoAttribute()` tadinya 2× `jurnal()->sum()` per akun (N+1 kalau dipakai di loop; saat ini tidak ada pemanggil). Sekarang: 1 query agregat (bukan 2) **atau 0 query** bila hasil query-nya lewat `scopeWithSaldo()` baru (`withSum('jurnal as saldo_debit'|'saldo_kredit')`), accessor ditandai `@deprecated` + PHPDoc. Nilai `saldo` untuk `saldo_normal` debit maupun kredit tidak berubah.
- **`tests/Feature/Nplus1P2Test.php`** (baru, 12 test / 141 assertion) — budget query (dashboard render ke-2 ≤ 25, komisi konstan terhadap K dan ≤ 6, konfigurasi `getMany` = 1, funnel = 1, guard channel tepat 1 select, `hitungSemua` ≤ 20, badge 0 query kedua) + **paritas angka**: seluruh 16 widget dashboard dibandingkan render dingin vs cache-hit, komisi gold case (4 kategori: override 10% + fallback 5% + nominal per barang 5.000×2 + kategori lain = **24.500**, reseller tanpa override = 5.000), funnel vs hitungan manual per tahap, badge approval = hitungan DB (termasuk `cabang_id` NULL global & exclude cabang lain/status bukan pending), skor supplier vs formula lama + `avg_harga` identik dengan implementasi lama, `SupplierScore` terpisah per cabang, cycle count (selisih/klasifikasi/stok akhir/audit log/jurnal + item gudang cabang lain tidak ikut dikoreksi), biaya admin channel 5% & pemetaan status, saldo COO via `withSaldo()` = nilai accessor lama.

**Temuan baru (tidak diperbaiki — di luar scope B-15d):** tabel `purchase_order` **tidak punya kolom `cabang_id`** (juga `supplier`), padahal `SupplierScoringService` memfilter `->where('cabang_id', …)` dan `SupplierScoreJob` meneruskan `cabangId`. Di SQLite (test) filter itu diam-diam tidak cocok (double-quoted identifier jadi string literal) sehingga test hijau; di **MySQL produksi job tersebut akan error `Unknown column`**. Perlu migrasi penambahan kolom + epic terpisah.

**Verifikasi** (per-file — suite penuh dilarang, RAM 1GB)
- `tests/Feature/Nplus1P2Test.php` → **OK (12 test, 141 assertion)**.
- Regresi wajib: `DashboardSinkronisasiTest` **OK 7 (32)**; `ResellerFase5Test` **OK 2 (9)**; `KomisiMultiAktorTest` **OK 8 (41)**; `ReorderTest` **OK 10 (47)**; `ApprovalEngineTest` **OK 20 (74)**; `CycleCountPageTest` **OK 4 (16)**; `SemuaHalamanTest` **OK 9 (55)**; `SupplierScoringTest` **OK 4 (14)**.
- Regresi tambahan (kode yang disentuh): `TreasuryWidgetTest` **OK 4 (15)**; `BroadcastPromoTest` **OK 8 (36)**; `LeadConversionTest` **OK 9 (55)**; `PpnTest` **OK 23 (102)**; `IntegrasiModulTest` **OK 2 (54)**; `NeracaWidgetDashboardTest` **OK 4 (38)**; `ComputedPropertyFixTest` **OK 4 (5)**; `WmsDashboardSplitTest` **OK 7 (23)**.
- `./vendor/bin/pint --test` untuk 10 berkas yang saya ubah → **PASS**. (2 temuan style tersisa berasal dari lane lain: `app/Modules/Wms/Livewire/TransferTab.php` + `tests/Feature/WmsServisNplus1Test.php` — tidak disentuh.)
- `./vendor/bin/phpstan analyse` untuk 9 berkas yang saya ubah → **bersih untuk seluruh kode B-15d**. Sisa 7 error pada `DashboardIndex.php` (baris 518-552, semuanya di dalam `render()`) adalah **baseline drift yang sudah ada** dari perubahan *uncommitted* lane lain (restrukturisasi P1-A `defaultWidgetData()`/`render()`): `phpstan-baseline.neon` masih mengharapkan pola akses computed-property versi lama (`$treasuryProjection` dll. di dalam `defaultWidgetData()`). Perlu regenerate baseline setelah lane tersebut merge — bukan diperbaiki di B-15d.
- Angka sesudah diukur di fixture 5 role + 2 cabang: dashboard dingin **42** / hangat **2**; komisi K=1/4/8 → **4/4/4** (lama 4/10/18); konfigurasi 5 kunci **1**; funnel **1**; badge 0 setelahTTL; supplier 5×2PO **23** (lama **51**, `avg_harga` sama persis: 110,120,130,140,150); cycle count 20 item **1** (lama 20).

## 2026-09-26 — B-15a: N+1 zona Marketplace (katalog publik, detail produk, tarif kurir) ✅

Katalog & detail produk mengambil stok/harga **satu per baris di dalam loop blade**, dan `resolveOrigins()` mengambil stok **satu per (cabang × item)**. Pada fixture 20 produk × 5 varian × 3 cabang: katalog publik **24 query (guest) / 42 (pelanggan login) → 7 / 6**, detail produk **11 → 4**, `resolveOrigins` **1 + C×I (10 untuk 3×3, 25 untuk 8×3) → 2** dan tidak lagi bergantung pada C maupun I. Angka sebelum = query dasar + N (N = 18 kartu / 5 varian / C×I); angka sesudah diukur langsung.

**Perubahan per file**

- **`app/Modules/Marketplace/Livewire/ShopPage.php`**
  - `getProductsProperty()`: `withCount('stokItems')` → `withSum('stokItems', 'jumlah')` (total stok ikut di subquery SELECT, bukan 18 query terpisah) **+ `with('hargaTier')`** — `PelangganService::rowsHargaTier()` sudah memakai relasi bila ter-load, jadi harga tier cukup **1 query untuk seluruh halaman**. `PelangganService` tidak disentuh.
  - `getStokSummaryProperty()` (baru): **satu** query `stok_items` (LEFT JOIN `gudang` + `cabang`, tanpa filter gudang) diturunkan di PHP menjadi tiga nilai sekaligus — `perCabang`, `total`, `perVariant`. Menggantikan `getStokPerCabangProperty()` (3 query: stok + gudang + cabang) + `getStokTotalProperty()` (1 query) + 1 query per varian di blade. Ketiganya kini getter tipis di atas satu sumber, dan hasilnya **di-memoize per instance** (`private ?array $stokSummaryMemo`, direset di `mount()`) karena pemanggilan `getStokXProperty()` langsung tidak lewat memo `__get` Livewire → tetap **1 query** untuk ketiganya.
  - `getStokPerVariantProperty()` (baru) untuk blade detail.
  - `getHpMerkListProperty()` / `getHpModelListProperty()`: `Cache::remember` (TTL 3600 dtk, key `shop.hp.merk-list.v1` & `shop.hp.model-list.v1[:merk]`) + `pluck('kompatibilitas_hp')` (1 kolom) alih-alih `->get()` seluruh model lengkap. Normalisasi `unique() → sort() → values()` dipertahankan sehingga **nilai & urutan dropdown identik**. Cache tidak di-invalidate saat produk berubah (Produk ada di modul Wms, di luar scope) → worst case dropdown tertinggal ≤ 1 jam.
  - `getProductDetailProperty()`: eager load `hargaTier` (1 query) menggantikan query `harga_tier` terpisah saat pelanggan login.
- **`resources/views/modules/marketplace/livewire/shop-catalog.blade.php`** — `StokItem::where('produk_id',$p->id)->sum('jumlah')` di dalam `@forelse` diganti `(int) ($p->stok_items_sum_jumlah ?? 0)` (alias default `withSum` Laravel ≥ 12). Harga tetap `PricingService::resolve($p, $customer)`.
- **`resources/views/modules/marketplace/livewire/shop-detail.blade.php`** — `StokItem::…->where('sku_variant_id',$v->id)->sum('jumlah')` per varian diganti `(int) ($stokPerVariant[$v->id] ?? 0)`.
- **`app/Modules/Marketplace/Controllers/ShippingController.php`**
  - `resolveOrigins()`: stok per (cabang, produk) diambil dengan **satu** agregat `join gudang … group by(gudang.cabang_id, stok_items.produk_id)`, kecukupan dinilai di PHP. Join ke `gudang` tetap effectively-INNER (setara `whereHas('gudang', …)`), urutan iterasi cabang tetap `Cabang::where('is_active', true)`, dan koordinat `crc32(kode)` tidak disentuh → **daftar origin & koordinatnya identik**.
  - `rates()`: `Produk::find($item['produk_id'])` di dalam loop diganti **satu** `whereIn` + `keyBy('id')` (N item → 1 query).
- **`tests/Feature/MarketplaceNplus1Test.php`** (baru, 25 test / 118 assertion) — fixture 20 produk × 5 varian × 3 cabang dengan `jumlah = 1 + (($i + $j + $g) % 7)` supaya angka stok bisa dihitung manual. Isinya: (a) budget query per halaman (katalog guest ≤ 8, katalog login ≤ 9, detail ≤ 6, `resolveOrigins` ≤ 3); (b) guard anti-N+1 yang lebih tajam — query log dinormalisasi (literal/angka di-mask, deretan placeholder di-collapse) lalu **bentuk SQL yang muncul > 1x = gagal**, jadi query agregat/`whereIn` hanya boleh dieksekusi sekali; (c) correctness — `stok_items_sum_jumlah` vs query lama per produk, stok per varian vs query lama per varian **dan** hitungan manual, stok per cabang vs `groupBy(gudang.cabang_id)` lama (nama/alamat/angka/urutan), stok total vs `sum()` lama, daftar `hp_merk`/`hp_model` vs implementasi lama (termasuk yang terfilter merk), harga tier pelanggan login (jalur relasi ter-load vs jalur query) sama persis, dan HTML katalog/detail menampilkan angka yang benar; (d) `resolveOrigins` menghasilkan array yang **sama** dengan implementasi C×I lama (stok cukup / tidak cukup / item duplikat / cabang nonaktif), plus endpoint `POST /api/shipping/rates` (origin & status 503 saat Biteship belum dikonfigurasi).

**Perilaku yang dijaga:** tidak ada perubahan pada `PelangganService`, POS, WMS, Akunting, atau `routes/web.php`; pemilihan origin/gudang untuk ongkir, harga tier, dan urutan dropdown tidak berubah.

**Verifikasi** (per-file — suite penuh dilarang, RAM 1GB)
- `tests/Feature/MarketplaceNplus1Test.php` → **OK (25 test, 118 assertion)**.
- Regresi: `SemuaHalamanTest` (render `/shop`, `/shop/{slug}`, `/cart`, `/checkout`) → **OK 9 (55)**; `ExampleTest` → **OK 3 (6)**; `Unit/PricingServiceTest` → **OK 3 (9)**; `MarketplacePpnTest` → **OK 10 (51)**; `DuitkuWebhookTest` → **OK 8 (46)**; `ImportProdukAtomikTest` → **OK 7 (50)**; `TransaksiPaidAtTest` → **OK 5 (15)**.
- `./vendor/bin/pint --test` untuk 3 berkas PHP yang diubah → **PASS**.
- Angka sesudah (fixture 20×5×3): katalog guest **7** (cache dingin) / **5** (cache hangat), katalog pelanggan login **6**, detail produk **4**, `resolveOrigins` **2**.

## 2026-09-26 — B-15b: Laporan akunting agregasi di SQL (bukan hydrate semua jurnal) + daftar AR/AP terpaginasi ✅

**Empat temuan N+1 / pemuatan-seluruh-baris yang ditutup (P0 + P1)**
- **P0-1 `AkuntingDashboard::getLabaRugiProperty()`** — `JurnalAkuntansi::with('akun')->get()` menarik **seluruh** baris jurnal periode (produksi 12.000-25.000 baris/bulan → 50.000+ model terhidrasi, ±150-250MB per klik) hanya untuk menjumlah per akun. Sekarang **agregasi di SQL**: `join akun_coa` + `GROUP BY akun_coa_id, kode, nama, tipe, kelompok, saldo_normal` dengan `SUM(debit)/SUM(kredit)`, hasilnya dibungkus `Cache::remember(900, …)` memakai **pola persis** `getNeracaProperty()` dan konvensi key API [API: ACC-05] (`laporan-labarugi-{cabang}-{dari}-{sampai}`). Helper baru `agregatJurnalPeriode()` dipakai bersama.
- **P0-2 `AkuntingDashboard::getArusKasProperty()`** — masalah identik. Sekarang satu query agregat yang sama, sisanya dijumlah per `kode`/`kelompok` di atas hasil agregat. **Semua rumus dipertahankan apa adanya**, termasuk yang terlihat quirky: `kenaikan_piutang/persediaan/utang` = **pergerakan periode** (debit−kredit), bukan saldo akhir; tanda investment/financing; `round(…, 2)` di setiap langkah. Cache 15 menit, key `laporan-aruskas-{cabang}-{dari}-{sampai}`.
- **P0-3 `ExportLaporanService::jurnalsKumulatif()`** → `saldoKumulatifPerAkun()`** — `whereDate('tanggal','<=',$sampai)->get()` **tanpa batas bawah** = kumulatif sejak awal pembukuan (produksi 150.000-300.000 baris/tahun; cold cache = 300k model ≈ OOM di RAM 1GB). Sekarang `SUM(debit)/SUM(kredit)` per akun + join `akun_coa` dalam **satu** query. `neracaSaldo()` membaca agregat ini; bentuk keluaran (array per akun, urut `kode`, `laba_periode_berjalan`, `balance`, `selisih`) tidak berubah sama sekali. Baris jurnal yang akunnya sudah hilang tetap dibuang (dulu `filter()`, sekarang inner join).
- **P1-4 `getPiutangsProperty()` / `getUtangsProperty()`** — `->latest()->get()` (200-1.000 baris) → `simplePaginate(15, ['*'], 'pagePiutang'|'pageUtang')`. Nama halaman dipisah dari `page` (tab jurnal) supaya tabel tidak saling menggeser. **Kolom, urutan, isi baris, dan scoping cabang tidak berubah**; blade tidak butuh total rekap jadi tidak ada query `count()` tambahan. Kedua tabel ini memang tidak punya filter/search (hanya tab + export), jadi tidak ada reset halaman yang perlu.
- **P1-5 `LaporanPajak::getKeluaranRowsProperty()`** — `foreach ($q->get())` seluruh transaksi periode tiap render → dibungkus `Cache::remember(…, now()->addMinutes(15), …)` memakai pola yang sama persis `getRekapProperty()` (key `laporan-pajak-rows:{cabang}:{dari}:{sampai}`). Isi baris (tanggal/DPP/percent/PPN) tidak berubah.

**Ukuran before → sesudah (diukur di test, fixture SQLite in-memory)**

> Angka **peak proses** bergeser antar-run karena bergantung sisa memori proses saat test jalan; yang stabil adalah **selisih query** dan **tambahan memori laporan**.

| Skenario | Query | Memori |
| --- | --- | --- |
| Laba rugi + arus kas, 20.000 baris | **4 → 2** | peak proses **124.613.840 → 78.382.600 byte** (118,8 MB → 74,8 MB); tambahan memori laporan ≈ 0 |
| Laba rugi + arus kas, 40.000 baris | ≤ 50 (terukur **2**) | tambahan memori laporan < 20 MB (terukur **111,80 KB**) |
| `neracaSaldo()`, 60.000 baris | **1** (batas ≤ 3) | tambahan memori **15.456 byte** (batas 64 MB) |
| Laba rugi/arus kas dipanggil ulang (warm cache) | **0** | — |
| Daftar piutang (40 baris) | ≤ 4 | 15 baris/halaman |
| Baris PPN keluar dipanggil ulang | **0** | — |

**Paritas angka (yang paling penting — semua hijau)**
- `tests/Feature/AkuntingNplus1Test.php` (8 test / 82 assertion) mereplikasi **kode lama** (row-level `with('akun')->get()` + `groupBy` di PHP) di dalam test, lalu membandingkan hasil baru dengan hasil lama pada fixture yang sama: setiap akun pendapatan & beban (kode + nama + total), `total_pendapatan`, `total_beban`, `laba_bersih`, plus seluruh kunci arus kas (`laba_bersih`, `arus_kas_operasi/investasi/pendanaan`, `kenaikan_kas_neto`, dan ketiga penyesuaian).
- Neraca: saldo **per akun** dibandingkan dengan `SUM` manual baris-per-baris di PHP atas fixture yang sama (`saldoKumulatifManual()`), termasuk daftar akun yang muncul; lalu widget dashboard dibandingkan dengan `neracaSaldo()` pada 6 kunci + penanda `balance` (paritas yang sebelumnya dikunci `NeracaWidgetDashboardTest`).
- Test mengunci bahwa jurnal **cabang lain** tidak bocor (laba rugi & arus kas), jurnal **sebelum periode** tidak masuk laporan periode tapi **tetap** masuk neraca kumulatif, dan jurnal **masa depan** tidak mengubah neraca hari ini.
- **Tidak bisa diverifikasi:** (a) urutan baris kartu Laba Rugi. Versi lama urutannya datang dari rencana query (scan index `cabang_id,tanggal`), jadi berbeda antar engine DB; versi baru memakai `MIN(id)` (urutan jurnal pertama) yang deterministik. **Angka, label, dan pembagian kelompok akun tidak berubah** — hanya urutan baris. (b) Perilaku di MySQL produksi tidak diuji (test DB = SQLite `:memory:` per `phpunit.xml`); SQL-nya sengaja ditulis portabel (`GROUP BY` semua kolom terpilih, tanpa fungsi-only-MySQL).

**Lane lanjutan (server restart) — 3 test gagal ditutup, tidak ada angka yang berubah**

Kelima test yang dijalankan setelah restart: **3 gagal, 5 hijau**. Ketiganya **bukan selisih angka** — semuanya cacat pengukuran/assertion di file test, sudah dibuktikan oleh test paritas yang membandingkan agregat SQL dengan replikasi kode lama:

| Test gagal | Akar masalah | Perbaikan |
| --- | --- | --- |
| `…laba_rugi_dan_arus_kas_jumlah_query_dan_memori_terkontrol` | `memory_reset_peak_usage()` menyetel peak := **usage saat ini**, bukan nol. Fixture 40.000 baris sudah occupying ±76 MB, jadi `memory_get_peak_usage()` sesudah reset **tidak mungkin** turun di bawah 20 MB — ambang yang diuji mustahil dicapai implementasi mana pun. Delta yang terukur justru **−25.712 byte** (laporan tidak menambah memori). | Ambang **tidak diubah** (tetap 20 MB). Yang diuji diselaraskan ke maksud dokumentasinya sendiri: **tambahan memori** = peak laporan − `memory_get_usage()` saat pengukuran mulai, bukan peak absolut. |
| `…neraca_saldo_agregat_jumlah_query_dan_memori_terkontrol` | Cacat pengukuran yang sama (delta terukur **−681.568 byte**). | Sama: **ambang tetap 64 MB**, diuji terhadap tambahan memori. |
| `…daftar_piutang_dan_utang_terpaginasi_tanpa_mengubah_isi_tabel` | `AbstractPaginator::items()` mengembalikan **array**, bukan `Collection` — di framework ini berlaku untuk `simplePaginate()` **dan** `paginate()`. Jadi `items()->pluck()` gagal untuk paginator apa pun yang dipakai. | Assertion dibungkus `collect($piutang->items())->pluck(...)`. Nilai yang dibandingkan tetap dari query independen (`latest()->limit(15)->pluck(...)`), jadi **tidak ada yang dilonggarkan** dan test kini tidak mengunci kelas paginator tertentu. |

Tidak ada implementasi yang diubah untuk membuat test hijau — ketiga perbaikan ada di sisi test, dan **setiap nilai uang yang dikunci tetap dikunci** (paritas laba rugi & arus kas byte-identik, saldo neraca per akun vs `SUM` manual, isi baris piutang/utang/PPN).

**Verifikasi** (per-file, suite penuh dilarang — RAM 1GB)
- `php artisan test --filter=AkuntingNplus1Test` → **8 passed** (82 assertion).
- Regresi wajib: `NeracaKumulatifTest` → **4 passed** (37); `NeracaWidgetDashboardTest` → **4 passed** (38); `AkuntingIntegritasTest` → **15 passed** (67); `JurnalValidasiDashboardVsApiTest` → **6 passed** (40); `ExportLaporanTest` → **10 passed** (72); `PpnTest` → **23 passed** (102); `JurnalServiceTest` → **4 passed** (8); `RekonsiliasiPerbaikiCommandTest` → **18 passed** (74); `TodosHalamanTest` **tidak ada** di repo → dijalankan `SemuaHalamanTest` → **9 passed** (55).
- `AkuntingDashboardTest` **tidak ada** di repo (dicek via `grep -rln "class AkuntingDashboardTest" tests/` → kosong), jadi tidak dijalankan.
- `./vendor/bin/pint --test` pada 4 file milik lane ini (AkuntingDashboard, ExportLaporanService, LaporanPajak, AkuntingNplus1Test) → **PASS**. `--test` repo penuh masih melaporkan 9 isu style, **semuanya di file lane lain** (Crm, Dashboard, Marketplace, Wms, `MarketplaceNplus1Test`, `WmsServisNplus1Test`, `ScratchMeasureTest`, `TmpMeasureTest`) — sengaja tidak disentuh.

## 2026-09-26 — B-13: `ute:rekonsiliasi-perbaiki` (dry-run default) + template Supervisor berbasis env ✅

**2 keputusan tertunda owner yang diselesaikan. Keduanya "perbaiki tanpa risiko": write di tempat yang tidak butuh keputusan akuntansi, laporan di tempat yang butuh.**

### 1. Rekonsiliasi data live `db_staging` → perintah baru, bukan perbaikan diam-diam

`ute:rekonsiliasi` (B-10c, READ-ONLY) menemukan anomali nyata di staging, tapi tidak ada satu pun yang bisa ditutup tanpa keputusan akuntansi. Diambil pendekatan: **perintah baru yang menampilkan rencana + hanya menulis dua kategori yang benar-benar aman, sisanya murni laporan.**

**`app/Console/Commands/RekonsiliasiPerbaiki.php` (BARU) — `ute:rekonsiliasi-perbaiki`**
- **DRY-RUN itu default.** Tanpa `--jalankan`/`--apply`, seluruh 5 kategori hanya `SELECT`. Bukti: sidik jari (count + md5 isi) seluruh tabel relevan sebelum/sesudah, identik.
- 5 kategori via `--kategori=` (pisahkan koma): `piutang-utang-cabang`, `utang-orphan`, `po-terima-tanpa-jurnal`, `payroll-tanpa-jurnal`, `opname-jurnal-ganda`. Plus `--limit=`, `--yes`.
- **Bisa diperbaiki otomatis (hanya 2):**
  - `piutang-utang-cabang` → isi `cabang_id` NULL dengan cabang default (id terkecil), **logika identik migrasi `2026_09_25_000100`** termasuk "belum ada cabang → skip, jangan isi nilai karangan". Hanya menyentuh `cabang_id`; kolom lain tidak disentuh. (Staging: 5 piutang + 3 utang = 8 baris.)
  - `utang-orphan` → **tandai di `keterangan`**, bukan delete dan bukan mengarang referensi.
- **Hanya laporan (3) — TIDAK PERNAH menulis, bahkan dengan `--jalankan --yes`:** PO `diterima` tanpa jurnal, payroll `selesai`/`dibayar` tanpa jurnal, opname dengan >1 nomor jurnal. Ketiganya tampil lengkap + usulan jurnal (akun, sisi, nominal, `referensi_tipe`/`referensi_id`, nomor jurnal usulan) supaya owner bisa memutuskan sendiri. Exit code 1 selama masih ada temuan manual.
- **Backup-friendly:** rencana "Akan mengubah N baris" dicetak SEBELUM ada query write. `--jalankan` tanpa `--yes` → konfirmasi interaktif; bila konfirmasi tidak bisa dibaca (cron/CI/`--no-interaction`/STDIN bukan TTY/under test) penulisan **DIBATALKAN**, bukan diasumsikan setuju. Semua penulisan satu transaksi DB.
- **Idempoten:** kategori (a) hanya menyentuh `cabang_id IS NULL`; kategori (b) hanya menambahkan penanda yang belum ada. Putaran kedua → 0 baris berubah.
- Tidak pernah `delete`, tidak pernah mengubah `no_jurnal` yang sudah ada, tidak pernah membuat jurnal akuntansi.

**DEVIASI dari brief (dinyatakan, bukan disembunyikan).** Brief meminta orphan Utang di-"set NULL". Tidak bisa: `utang.referensi_id` bertipe `bigint unsigned NOT NULL` (migrasi `2026_09_16_000026`, dikonfirmasi juga di MySQL produksi via `SHOW COLUMNS`) → NULL ditolak database, dan perintah ini tidak boleh membuat migrasi baru. Yang dilakukan: **annotate** di `keterangan` (kolom `catatan` milik tabel `utang`) + `referensi_id` dibuka apa adanya + pesan eksplisit bahwa meng-null-kan butuh migrasi terpisah. Baris yang `referensi_id > 0` tapi dokumen tujuannya hilang **tidak** diperbaiki sama sekali — menunjuk dokumen lain demi "cocok" berarti mengarang data akuntansi.

### 2. Supervisor worker mati (path config salah) → template jadi berbasis environment

Insiden: `/etc/supervisor/conf.d/ute-parts-queue.conf` menunjuk `/var/www/test.uteparts.id` (tidak ada) → `supervisorctl status` = `FATAL ... ENOENT` → 53 `KirimNotifikasiJob` `pending`, WA/email tak pernah terkirim. Template repo punya masalah sama dengan path berbeda (`/var/www/ute-parts`).

- **`deploy/supervisor/ute-parts-queue.conf` (diperbaiki):** semua path jadi `%(ENV_UTE_PATH)s` / `%(ENV_UTE_PHP)s`, diisi lewat baris `environment=` di file itu sendiri ATAU lewat `EnvironmentFile` milik supervisord (supervisord 4.x). **Tidak ada path server lain yang di-hardcode.** Variabel yang absen membuat supervisor menolak start — gagal keras dan terlihat, bukan worker diam-diam jalan di path salah.
- Parameter dinaikkan sesuai RAM 1GB: `numprocs=1`, `--sleep=3`, `--tries=3`, **`--timeout=60`**, `--max-time=3600`, `--max-jobs=1000`, `--memory=256`, `stopwaitsecs=70` (harus > `--timeout` supaya job sempat *graceful*), `stopasgroup/killasgroup=true` (orphan child = kebocoran RAM), rotasi log `queue-worker.log` 10MB × 3, plus `startsecs=5`/`startretries=3` supaya kegagalan start kelihatan sebagai FATAL, bukan "STARTING" lalu diam.
- **`deploy/supervisor/README.md` (BARU):** cara install (`cp` → set `UTE_PATH`/`UTE_PHP`/`user` → `reread && update`), cara set env path lewat supervisord, cara cek (`supervisorctl status`, `queue:monitor`, `queue:work --once`, hitung `jobs`/`failed_jobs`), dan **diagnosa 7 langkah "job tetap pending"**: worker mati/FATAL → path salah → `DB_CONNECTION` worker tidak cocok dengan web (gejala khas: `jobs` di DB lama membengkak, DB baru kosong) → `queue:work` tidak jalan / di-`queue:pause` → permission log (harus diuji **sebagai user yang sama** dengan `user=` di conf) → job timeout → `config:cache` basi.

**Test baru** — `tests/Feature/RekonsiliasiPerbaikiCommandTest.php` (18 test / 74 assertions): terdaftar otomatis; **dry-run tidak mengubah data** (sidik jari count+md5 8 tabel, sama pola `RekonsiliasiCommandTest`); rencana + angka ADR 0011; kategori asing ditolak; `--kategori` & `--limit`; **`--jalankan` tanpa `--yes` dibatalkan & data utuh**; `--jalankan --yes` mengisi `cabang_id` tanpa menyentuh kolom lain; orphan ditandai tanpa delete/tanpa ubah `referensi_id` dan `keterangan` lama dipertahankan; **idempoten** (putaran kedua 0 baris, penanda tidak menumpuk); **kategori laporan tidak pernah menulis jurnal** (sidik jari + hitung `jurnal_akuntansi` tetap, slip tetap NULL); referensi hilang & tipe tak dikenal hanya dilaporkan; belum ada cabang → `[dilewati]`; PO ber jurnal GRN tidak dilaporkan; data bersih → exit 0; tabel hilang → bukan error fatal.

**Verifikasi** (per-file, suite penuh dilarang — RAM 1GB)
- `php artisan test tests/Feature/RekonsiliasiPerbaikiCommandTest.php` → **18 passed** (74).
- Regresi wajib: `--filter=RekonsiliasiCommandTest` → **9 passed** (59); `--filter=PayrollServiceTest` → **6 passed** (22); `--filter=GrnTest` → **23 passed** (135); `--filter=SemuaHalamanTest` → **9 passed** (55).
- `./vendor/bin/pint` (2 file) → **PASS**.
- Dry-run terhadap `db_staging` nyata sudah dijalankan: 11 baris direncanakan (8 `cabang_id` + 3 orphan) dan **sudah diverifikasi ulang bahwa `piutang`/`utang` NULL-branch tetap 5+3, penanda 0 baris, `jurnal_akuntansi` tetap 167, slip payroll tetap 7 NULL** — tidak ada data produksi yang berubah.
- Konfigurasi supervisor divalidasi dengan `supervisord` 4.2.5 memakai config buang-away di `/tmp` (pidfile + log terpisah): dengan placeholder `UTE_PATH` supervisor menolak start di parse-time (membuktikan ekspansi `%(ENV_*)s` bekerja); dengan path yang ada, config parse bersih, program ter-spawn, dan hanya FATAL karena direktori dummy tidak punya `artisan`. **Tidak ada `supervisorctl` yang mengubah state produksi dijalankan** — `/etc/supervisor/conf.d/ute-parts-queue.conf` tetap apa adanya (masih FATAL ENOENT, menunggu tindakan owner).
- **Tidak bisa diverifikasi:** worker produksi benar-benar mengirim WA/email setelah path diperbaiki (butuh tindakan di `/etc/supervisor`, di luar cakupan & dilarang disentuh); apakah 27/20 jurnal opname itu replay atau adjustment sah (butuh keputusan akuntansi); nominal COA `130-01`/`210-01`/`520-01`/`520-08`/`210-02` yang muncul sebagai usulan divalidasi owner; perilaku nyata di MySQL untuk `whereNotLike` pada kolom `keterangan` NULL (SQLite sudah terbukti benar, dan NULL `LIKE` mengecualikan baris sehingga aman).

## 2026-09-25 — B-14: PPN Marketplace dipisah (DPP → 410-01, PPN → 220-01) + role non-teknisi boleh lihat kunci gadget ✅

**Keputusan owner yang dieksekusi (2 item tertunda)**

**(1) PPN marketplace tidak lagi "ditelan" di akun pendapatan.** Webhook Duitku (`PaymentController::prosesLunas()`) membukukan **seluruh** `total_akhir` ke `410-01` tanpa memisah PPN ke `220-01` — berbeda dari POS (`PosController::store()`). Akibatnya laporan penjualan marketplace ≠ POS, akun pajak PPN marketplace selalu nol, dan laporan pajak bulanan (F1-2) under-reported.

- **Definisi kanonik (identik POS, tax-exclusive):** `DPP = subtotal - diskon` → `PPN = DPP × persen` → `total_akhir = DPP + PPN`. Diambil dari definisi POS: DPP berasal dari subtotal (bukan dari `total_akhir`), PPN dihitung dari DPP, dan `total_akhir` adalah nilai yang benar-benar ditagihkan.
- **`app/Modules/Akunting/Services/PajakService.php`** — helper baru `hitungPenjualan(?int $cabangId, float $subtotal, float $diskon = 0.0): array` → `['dpp','ppn_nominal','ppn_percent','enabled','total_akhir']`. Satu-satunya tempat aritmetika DPP/PPN; tidak ada perhitungan yang diduplikasi di controller/service. Memakai `PajakService::hitung()` sehingga ikut aturan `pajak_enabled.{cabang}` / `ppn_enabled.{cabang}` / `ppn_percent.{cabang}` (default nonaktif, default 11%).
- **`app/Modules/Marketplace/Services/OrderService.php`** — PPN dihitung **sekali saat order dibuat** lalu disimpan di `transaksi`: `dpp`, `pajak_nominal`, `ppn_nominal`, `total_akhir` (sebelumnya `pajak_nominal` hardcode 0 dan `total_akhir` = subtotal). `PajakService` di-inject via constructor. Ditambah `previewPajak(int $cabangId, float $subtotal)` supaya halaman cart/checkout bisa menampilkan angka yang sama persis dengan yang ditagihkan.
- **`app/Modules/Marketplace/Controllers/PaymentController.php`** — `PajakService` di-inject; jurnal webhook jadi: debit `110-01` = **`total_akhir`** (nilai yang dibayar pelanggan, TIDAK diubah → rekonsiliasi Duitku tetap `total_akhir` dan `jumlah_bayar` tetap sama), kredit `410-01` = **DPP**, plus baris PPN dari `PajakService::jurnalLines()` → `220-01` kredit. Baris HPP/Persediaan tidak berubah. Helper privat `pisahDppPpn()` menjaga invarian balance `total_akhir = dpp + ppn` dan **tidak menghitung ulang PPN** (non-retroaktif, sesuai kontrak `PajakService`).
- **Perilaku tak berubah bila pajak nonaktif:** PPN 0 → `dpp == total_akhir` → jurnal persis 4 baris seperti sebelumnya (Kas/Pendapatan/HPP/Persediaan), tanpa baris `220-01`.
- **Order lama** (`ppn_nominal` kosong, `dpp` = total) tetap dibukukan penuh ke `410-01` walau pajak diaktifkan belakangan → tidak ada PPN retroaktif. Fallback `total_akhir - dpp` hanya dipakai utk order yang PPN-nya sudah tercakup di total tapi kolom `ppn_nominal`-nya kosong (tetap balance).
- **Idempotensi B-10b tetap berlaku:** callback duplikat tidak membuat baris PPN kedua (ada testnya).

**(2) Role non-teknisi boleh membuka tiket & melihat kunci gadget (B-06 tertunda).** `servis.view` hanya diberikan ke `teknisi`, `admin-toko`, `super-admin`; role `kasir`/`finance`/`staff-gudang`/`marketing` tidak bisa membuka `/app/servis` sama sekali.

- **`database/seeders/RolesAndPermissionsSeeder.php`** (idempoten, `findOrCreate` + `givePermissionTo`, **tanpa permission baru**):
  - **Diberi `servis.view`: `kasir` + `marketing`.** Alasan: (a) `kasir` = meja kasir/loket terima-ambil unit — perlu membuka tiket saat unit diserahkan/diambil, termasuk kunci gadget; (b) `marketing` = perlu melihat status perbaikan (dan kunci gadget bila perlu) untuk menjawab pelanggan. Keduanya **READ-ONLY**: tanpa `servis.create`, `servis.update-status`, `servis.input-sparepart`, `servis.override-status`.
  - **TIDAK diberi: `finance` + `staff-gudang`** (minimum privilege). `finance` fokusnya akunting/piutang/pajak — kunci gadget dan data unit pelanggan tidak diperlukan untuk tugasnya; `staff-gudang` fokusnya stok/opname/transfer, bukan alur servis. Keduanya tetap 403 di halaman dan API servis.
  - `teknisi` **ikut dapat `servis.create`** — konsistensi, bukan perluasan akses: teknisi sebelumnya sudah bisa menerima unit lewat papan kanban (route hanya dijaga `permission:servis.view`) dan API `POST /api/servis` memang mewajibkan `servis.create`. Tanpa ini teknisi ikut kehilangan Receive Unit.
- **`app/Modules/Servis/Livewire/ServisBoard.php`** — route `/app/servis` hanya dijaga `servis.view`, jadi memberi `servis.view` ke kasir/marketing tanpa guard akan membuka jalur mutasi (buat tiket/ubah status) yang memang tidak diberikan. Guard server-side ditambahkan, paralel dengan middleware API: `openTerimaModal()` + `simpanTerima()` → `servis.create`; `updateStatus()` (juga dipakai `dropTicket()`) + `simpanEstimasi()` + `prosesApprove()` → `servis.update-status`; `simpanPekerjaan()` → `servis.input-sparepart`. Helper privat `boleh($permission, $pesan)` dengan pesan Bahasa Indonesia. State machine tidak diubah.
- **`resources/views/modules/servis/livewire/servis-board.blade.php`** — tombol "Terima Unit Servis" dibungkus `@can('servis.create')` supaya role read-only tidak melihat tombol yang pasti ditolak.
- **`routes/web.php` TIDAK disentuh** — route `/app/servis` sudah punya `permission:servis.view` (`routes/web.php:250`), jadi granting permission langsung berlaku tanpa patch route.

**Test**
- Baru `tests/Feature/MarketplacePpnTest.php` (10 test / 51 assertions): order saat pajak aktif → `dpp` 1.000.000 / `ppn_nominal` 110.000 / `total_akhir` 1.110.000; pajak nonaktif → paritas lama (PPN 0, total = subtotal); `previewPajak()` = nominal yang ditagihkan; jurnal webhook **5 baris** (Kas 1.110.000 / 410-01 1.000.000 / 220-01 110.000 / HPP 400.000 / Persediaan 400.000) dan **balance**; **saldo akun 220-01 = 110.000** sedangkan saldo 410-01 = DPP (bukan total); `jumlah_bayar` = `total_akhir` (rekonsiliasi Duitku tak berubah); pajak nonaktif → **4 baris, tanpa 220-01**; order lama tidak dapat PPN retroaktif; webhook duplikat tidak membuat baris PPN kedua; persen per-cabang (5% → 50.000).
- Baru `tests/Feature/ServisViewRoleTest.php` (10 test / 36 assertions): seeder memberi `servis.view` ke kasir + marketing dan TIDAK ke finance/staff-gudang; kasir dan marketing **bisa** buka `/app/servis` (200); kasir melihat kunci gadget lewat `/api/servis/{id}`; marketing melihat kunci di modal detail (ter-mask lalu toggle); `finance` + `staff-gudang` tetap **403** di halaman, `/api/servis`, dan `/api/servis/{id}`; kasir tidak bisa terima unit maupun ubah status lewat Livewire, marketing tidak bisa input pekerjaan, admin-toko **masih bisa** terima unit (regresi).
- Fixture 403 yang tadinya memakai role `kasir` diganti `staff-gudang` (kasir kini entitled) — **semantik tes tetap sama**: role tanpa `servis.view` → 403. Berkas: `tests/Feature/SemuaHalamanTest.php::test_kanban_servis_diblokir_tanpa_permission_servis_view` dan `tests/Feature/ServisTerimaUnitTest.php::test_api_kunci_gadget_diblokir_untuk_role_tanpa_servis_view`.

**Verifikasi** (per-file, suite penuh dilarang — RAM 1GB)
- `tests/Feature/MarketplacePpnTest.php` → **10 passed** (51). `tests/Feature/ServisViewRoleTest.php` → **10 passed** (36).
- Regresi wajib: `PpnTest` → **13 passed** (51); `DuitkuWebhookTest` → **8 passed** (46); `ServisTerimaUnitTest` → **9 passed** (29); `ServisStateMachineTest` → **6 passed** (33); `SemuaHalamanTest` → **9 passed** (55); `TransaksiPaidAtTest` (marketplace/webhook terkait) → **5 passed** (15). `PermissionsTest` tidak ada di repo.
- `./vendor/bin/pint --test` utk 9 berkas yang diubah → **PASS**. `pint --test` untuk seluruh repo masih melaporkan 7 isu style — **semuanya berkas lane lain** (`app/Console/Commands/RekonsiliasiPerbaiki.php`, `Crm/Services/KonfigurasiService.php`, `Dashboard/Livewire/DashboardIndex.php`, `Marketplace/Livewire/ShopPage.php`, `Wms/Livewire/TransferTab.php`, `Wms/Services/CycleCountService.php`, `tests/Feature/MarketplaceNplus1Test.php`) → tidak disentuh agar tidak konflik lintas lane.
- Catatan lingkungan: `tests/Feature/MarketplaceNplus1Test.php` sedang ditulis lane lain dan **belum bisa di-parse** (`$ aggregat`, baris 251) sehingga `php artisan test --filter=...` gagal memuat seluruh suite. Verifikasi B-14 dijalankan per-file dengan `php vendor/bin/phpunit -c phpunit.xml <file>`.

**Tidak bisa diverifikasi**
- Rincian PPN di halaman cart/checkout: `CartCheckout`/`ShopPage` bukan berkas lane ini, jadi nominal yang ditampilkan belum termasuk PPN (patch ada di bawah).
- `PosController` masih menghitung DPP/PPN inline (angka identik dengan helper baru, tapi belum ikut memakai `PajakService::hitungPenjualan()`) — berkas itu bukan milik lane ini.
- Efek visual tombol "Terima Unit" tersembunyi untuk kasir/marketing (hanya markup yang di-assert).

**Patch yang perlu diterapkan orkestrator (berkas di luar kepemilikan lane ini)**
1. `app/Modules/Marketplace/Livewire/CartCheckout.php` — tampilkan DPP/PPN/total incl. pajak di cart dan checkout memakai helper yang sudah ada (`OrderService::previewPajak($cabangId, $subtotal)`), supaya angka di layar sama dengan yang ditagihkan. Tanpa ini pelanggan melihat "Total" = subtotal lalu ditagih subtotal + PPN.
2. `app/Modules/Marketplace/Controllers/ShopController.php::checkout()` — respons sudah memakai `total_akhir` order (otomatis ikut PPN); pertimbangkan juga mengirim `dpp` dan `ppn_nominal`.
3. `app/Modules/Pos/Controllers/PosController.php` (opsional) — ganti aritmetika inline DPP/PPN dengan `PajakService::hitungPenjualan()` agar satu sumber kebenaran.
4. `ShopController::accountServis()` masih mengirim `kunci_terenkripsi` ke pelanggan marketplace (leak pre-existing, sudah tercatat di entri B-06) — `makeHidden(['kunci_terenkripsi'])`.

## 2026-09-25 — B-02 revisi: backorder DIBATALKAN — stok 0 tetap TIDAK bisa dipilih ✅

**Kebijakan owner final (revisi atas versi "backorder disengaja")**
- Sesi sebelumnya sempat mengaktifkan backorder di POS: produk stok kosong boleh dipilih + peringatan amber, qty tidak dibatasi, dan `processTransaction()` mengirim `izinkanNegatif: true` sehingga stok boleh tercatat negatif. **Owner membatalkan kebijakan itu** — kembali ke kontrak asli: **stok 0 = tidak bisa dipilih**.
- Yang TIDAK di-rollback (semua perbaikan B-02 lain tetap utuh): satu kriteria stok antara gauge & add-to-cart (`hitungStokTersedia()`, agregat produk+gudang utk klik kartu), delegasi potong stok ke `StokDeductionService` (`lockForUpdate` + `StokLog` + `StockMutationLog`), scoping & validasi gudang per cabang aktif (anti-tamper lintas cabang), load-more katalog (`BATAS_PRODUK_AWAL` + `muatLebihBanyak`), RBAC route `permission:pos.view|pos.view-own`.

**`app/Modules/Pos/Livewire/PosKasir.php`**
- `STOK_TANPA_GUDANG` **999 → 0**. Nilai 999 adalah jalan bypass "gudang belum dipilih" (produk bisa masuk keranjang tanpa stok). Sekarang 0 → ikut satu kriteria stok, jadi kartu & `addToCart()` sama-sama menolak.
- `addToCart()`: cek stok **di awal, sebelum ada efek apa pun** (tidak ada item, tidak ada PPN yang bergeser). Stok `<= 0` → `return` + alert Bahasa Indonesia: gudang terpilih → *"Stok {nama} habis di gudang ini — pilih gudang lain yang punya stok."*; gudang belum ada → *"Pilih gudang terlebih dahulu untuk melihat & memakai stok {nama}."* Banner peringatan backorder di akhir method dihapus. Batas tambah-qty di item yang sudah ada kembali ke batas keras stok riil (pesan menyebut nama produk + jumlah maks).
- `updateQty()`: batas keras dikembalikan, dan sekarang batasnya **stok riil hasil hitung ulang dari database** (`stok_max` di keranjang adalah prop publik Livewire → bisa di-tamper client, tidak lagi dipercaya). `stok <= 0` → item **dibuang** dari keranjang + pesan Indonesia (menutup jalur "stok habis setelah item masuk" / pindah gudang / resume park).
- `validasiStokKeranjang()` (BARU, private): gate sebelum `DB::transaction` — setiap item wajib punya `stok >= qty` di gudang aktif, pesan Indonesia menyebut produk & angkanya. Tanpa gate ini kegagalan baru ketahuan setelah transaksi dibuat lalu di-rollback dengan pesan teknis. Menutup juga jalur `resumeDitahan()`/payload lama yang menyuntik keranjang stok 0.
- `processTransaction()`: `izinkanNegatif: true` **dihapus** dari pemanggilan `StokDeductionService::kurangi()` → default `false` = stok kurang ditolak. Delegasi ke service (lockForUpdate, satu kriteria baris stok, StokLog + StockMutationLog) tetap.

**Blade**
- `pos-kasir.blade.php` — kartu produk stok 0 kini **terlihat tidak bisa dipilih**, bukan hanya ditolak diam-diam di server: `role="button"` + `aria-disabled="true"`, `wire:click` tidak dirender, `cursor-not-allowed` + redup/grayscale, `title` Bahasa Indonesia yang menjelaskan kenapa (*"Stok {nama} habis di gudang ini — tidak bisa dipilih. Pilih gudang lain yang punya stok."* / *"Pilih gudang terlebih dahulu…"*), pill `Stok kosong` (atau `Pilih gudang dulu`), dan ikon gembok menggantikan tombol "+". Kartu stok ada tetap pakai `StockGauge` + aksi `addToCart`. Banner backorder di keranjang inline juga dihapus (duplikat dari partial) dan badge per item diubah jadi `Stok habis` (penanda, bukan backorder).
- `partials/cart-content.blade.php` — banner peringatan backorder dihapus, badge per item `Stok kosong` → `Stok habis`.

**`app/Modules/Wms/Services/StokDeductionService.php`**
- **Signature publik `kurangi()` tidak diubah** (param `bool $izinkanNegatif = false` tetap, dipakai Marketplace/Servis/import & `PosController`). Hanya docblock yang diperjelas: default `false` = tolak stok kurang; backorder POS dibatalkan owner; parameter tetap default `false` supaya tidak ada jalur pintas ke stok negatif.

**Test — `tests/Feature/PosStokSinkronTest.php`**
- Dua test backorder lama (`test_produk_stok_nol_bisa_ditambahkan_dengan_peringatan`, `test_backorder_mencatat_mutasi_negatif_dan_log_akurat`) **dihapus & diganti** jadi test penolakan — tidak ada lagi test yang menguji stok negatif sebagai perilaku yang diharapkan.
- Test baru: produk stok 0 & produk tanpa baris stok **tidak masuk keranjang** (tanpa efek samping: subtotal & PPN 0, nol transaksi/StokLog/StockMutationLog); **pesan Indonesia** "Stok … habis di gudang ini — pilih gudang lain" + bukti stoknya memang ada di gudang lain (7 unit) lalu produk bisa dipakai setelah pindah gudang; gudang belum ada → "Pilih gudang terlebih dahulu"; **kartu katalog stok 0 tampil terkunci** (`aria-disabled`, "Stok kosong", tooltip Indonesia) & banner backorder hilang; keranjang stok 0 hasil `resume`/payload ditolak saat bayar; **produk stok > 0 tetap bisa** + qty dibatasi stok riil; batas keras tidak bisa ditembus dengan tamper `stok_max`; `stok <= 0` membuang item dari keranjang.
- Fixture ditambah gudang kedua **milik cabang yang sama** (`Gudang Cadang Pusat`) supaya skenario "pilih gudang lain" bisa diuji tanpa melanggar scoping cabang; fixture produk tidak bertambah.
- Total **13 test / 99 assertions**.

**Verifikasi** (per-file, suite penuh dilarang — RAM 1GB)
- `php artisan test tests/Feature/PosStokSinkronTest.php` → **13 passed** (99).
- Regresi wajib: `--filter=SerialNumberTest` → **17 passed** (170); `--filter=StockMutationUserIdTest` → **4 passed** (14); `--filter=StockMutationActorTest` → **12 passed** (83); `--filter=SemuaHalamanTest` → **9 passed** (55).
- `./vendor/bin/pint --test` (3 file: PosKasir, StokDeductionService, PosStokSinkronTest) → **PASS**. `php artisan view:clear` sukses (blade dikompilasi ulang tanpa error).
- **Data live (read-only, `sudo mysql db_staging`):** `stok_items` 216 baris, `MIN(jumlah) = 8`, `SUM(jumlah < 0) = 0`, `SUM(jumlah = 0) = 0` → **tidak ada stok negatif hasil eksperimen backorder**, tidak perlu perbaikan data.
- **Tidak bisa diverifikasi:** tampilan visual kartu terkunci di browser nyata (hanya markup yang di-assert); perilaku API POS-01.
- **Di luar cakupan (file bukan milik lane ini) — perlu keputusan owner:** `app/Modules/Pos/Controllers/PosController.php:316` (API POS-01) **masih mengirim `izinkanNegatif: true`**, jadi endpoint API masih bisa membuat stok negatif walau UI POS sudah menutup backorder. Market-side sudah aman (pakai `kurangiDariGudangTersedia()` yang default `false`).

## 2026-09-25 — B-10i: 9 call site `StockMutationLog` sisanya sekarang ter-actor ✅

**P1-6 (lanjutan B-10f) — jejak pelaku mutasi stok masih bolong di 9 writer**
- B-10b sudah menambah kolom `stock_mutation_log.user_id` (nullable) dan B-10f sudah mengisinya di writer kanonik `StokDeductionService::kurangi()` (juga `ImportProdukService`). Namun **9 call site lain** masih `StockMutationLog::create()` tanpa `user_id` → mutasi stok opname/transfer/stok masuk/GRN/cycle count tetap tidak bisa dibuktikan siapa pelakunya. Kolomnya sudah ada → **tidak perlu migrasi baru**.
- **WmsController** — `kirimTransfer()` (`:175` sumber `transfer:out`), `terimaTransfer()` (`:238` sumber `transfer:in`), `approveOpname()` (`:395` sumber `opname`): `user_id` = `auth()->id()`, nilai yang sama dengan `StokLog.user_id` pada baris sibling di masing-masing blok — jadi SOT dan kartu stok tidak punya dua sumber kebenaran berbeda.
- **OpnameTab** (`:150`) & **TransferTab** (`:224`, `:285`): `auth()->id()` juga (paritas dengan `StokLog.user_id` serta `approver_id`/`approved_by`/`user_penerima_id` yang ditulis di blok yang sama).
- **ProdukService::tambahStokPembelian()** (`:241`): `user_id` = `$userId` yang **sudah ada** di signature (juga dipakai `StokLog` & `JurnalService::post` di fungsi yang sama). **Signature tidak diubah sama sekali** — kedua pemanggil sudah mengisinya (`ProdukTab::tambahStok()` → `auth()->id()`, `PurchaseOrderService::terimaBarang()` → `$userId`). Pemanggil tanpa konteks user (CLI/import) → NULL, bukan aktor karangan.
- **GrnService::tambahStok()** (`:427`): `user_id` = `$actionedBy` (sudah ada di signature, diteruskan dari `selesaikanGrn()`), sama dgn `StokLog.user_id` & jurnal 130-01/210-01. **Tidak perlu parameter baru.**
- **CycleCountService::koreksi()** (`:411`): `user_id` = `$userId` (sudah ada di signature; berasal dari `hitung()` untuk selisih minor atau `terapkanKoreksi()` untuk major) — jadi pelaku cycle count = petugas count/approver, bukan pemohon. **Tidak perlu parameter baru.**
- **SidMigrateToUteParts** (`:776`, jalur CLI): tidak ada sesi, jadi tidak ada user. Memakai **system actor yang sudah jadi konvensi repo** — literal `user_id = 1` yang persis sama dengan `StokLog::create()` 11 baris di atasnya (akun admin hasil `AdminUserSeeder`, auto-increment pertama). Dipakai `1`, bukan NULL, supaya `stok_log` & `stock_mutation_log` hasil migrasi tetap satu pelaku dan jejaknya tetap bisa dibaca. Risiko FK tidak baru: insert `StokLog` dengan `user_id = 1` di baris sebelumnya sudah mensyaratkan user itu ada.
- Konsekuensi: **tidak ada perubahan skema, tidak ada perubahan signature publik, tidak ada breaking change pada call site existing.** Semua 9 tambahan murni kolom yang sudah ada di `#[Fillable]` `StockMutationLog`.

**Test baru**
- `tests/Feature/StockMutationActorTest.php` (12 test / 83 assertions) — memverifikasi `stock_mutation_log.user_id` terisi pelaku yang benar **dan konsisten dengan `stok_log.user_id`** pada operasi yang sama, untuk 3 jalur utama yang diminta + jalur mutasi lain yang disentuh: (1) **opname** API `PUT /api/wms/opname/{id}/approve` dan Livewire `OpnameTab::approveOpname`; (2) **transfer** API kirim + terima dan Livewire `TransferTab::kirimTransfer`/`terimaTransfer` (dua arah, dua mutasi); (3) **stok masuk** `ProdukService::tambahStokPembelian`, `buatProduk` dgn stok awal, dan negatif-test "tanpa konteks user → NULL, bukan aktor fiktif"; plus **GRN** (terima barang + jalur approval `setujuiGrn` → pelaku = approver) dan **cycle count** (koreksi minor + jalur approval → pelaku = approver). Satu regression guard: tidak boleh ada mutasi SOT tanpa `user_id` pada sumber `opname`/`transfer:*`/`grn`/`po` yang jalurnya ter-autentikasi.

**Verifikasi** (per-file, suite penuh dilarang — RAM 1GB)
- `php artisan test tests/Feature/StockMutationActorTest.php` → **12 passed** (83 assertions).
- Regresi wajib: `--filter=PosStokSinkronTest` → **7 passed** (72); `--filter=StockMutationUserIdTest` → **4 passed** (14); `--filter=GrnTest` → **23 passed** (135); `--filter=SerialNumberTest` → **17 passed** (170); `--filter=ReorderTest` → **10 passed** (47); `--filter=SemuaHalamanTest` → **9 passed** (55).
- Regresi tambahan sesuai blast radius: `--filter=WmsDashboardSplitTest` → **7 passed** (23); `--filter=CycleCountPageTest` → **4 passed** (16).
- `./vendor/bin/pint --test` (8 file milik B-10i) → **PASS**.
- **Call site yang tetap tidak bisa diisi aktor (sengaja, di luar cakupan):** kolom nullable dipertahankan untuk proses tanpa identitas manusia — webhook Duitku / job sinkronisasi kanal (SOT ditulis tanpa user nyata, sudah tercakup kontrak "NULL" di `StockMutationUserIdTest`), dan `ProdukService::tambahStokPembelian()` bila dipanggil tanpa `$userId` (skenario CLI/import). Untuk keduanya NULL lebih jujur daripada mengarang user.
- Catatan lintas-lane (tidak diperbaiki di sini): `OpnameTab`/`TransferTab` masih memakai fallback `auth()->id() ?? 1` saat menulis `StokOpname::user_id`, `StokTransfer::user_pengirim_id`, dan `StokTransferItem::created_by`. Fallback "user 1" untuk kolom yang **wajib** itu mencurigakan (menyembunyikan bug "dipanggil tanpa user"), tetapi perbaikannya menyentuh seed/berkas di luar cakupan B-10i. Untuk `stock_mutation_log` sendiri fallback itu **sengaja tidak** dipakai — NULL, bukan user fiktif.

## 2026-09-25 — B-10h: Register Aset Tetap punya view + route (F3-3, tidak lagi BLOCKED) ✅

**BLOCKED-features — F3-3 Aset Tetap tidak bisa diakses user sama sekali**
- `App\Modules\Akunting\Livewire\AsetRegister` **sudah ada** lengkap (form tambah aset, konfirmasi disposal, eksekusi disposal via `DepresiasiService`) tetapi `render()` memanggil view `modules.akunting.livewire.aset-register` yang **tidak pernah dibuat** dan **tidak ada route-nya** — jadi fitur mati total (audit B-10).
- **View baru** `resources/views/modules/akunting/livewire/aset-register.blade.php`: rekap nilai buku + progress penyusutan, tabel register (desktop `DataTable` + kartu mobile ADR 0010), modal formulir tambah aset, `ConfirmDialog` disposal, empty state & empty-filter state yang informatif (bukan layar kosong) termasuk banner "cabang aktif belum dipilih". Angka financial `tabular-nums` + format ribuan titik `number_format($v, 0, ',', '.')` (ADR 0011, TIRU pola `akunting-dashboard.blade.php`). Notifikasi lewat event `alert` → `showToast()` yang sudah ada.
- **Route baru** `GET /app/akunting/aset` → `AsetRegister::class`, `->name('aset.register')`, `->middleware('permission:akunting.view')` di group `/app`. **Permission tidak dibuat baru** — `akunting.view/create/approve` sudah ada di `RolesAndPermissionsSeeder` (role `finance` + `super-admin`).

**Perbaikan di `AsetRegister` (minimal, hanya aksi yang memang dipakai UI)**
- `simpan()` **gagal menerima input berformat ribuan** ADR 0011 (`"1.000.000"` ditolak rule `numeric`) → kini ada `parseNominal()` private (helper yang sama dengan `AkuntingDashboard::parseNominal`, disalin bukan dikarang) dan normalisasi dilakukan **sebelum** `validate()`. Nilai kosong/salah sengaja tidak diubah supaya pesan error tetap akurat. Tanpa ini form tidak bisa dikirim lewat browser.
- `jalankanDepresiasi()` (BARU) — depresiasi **manual per periode (YYYY-MM)** scoped cabang aktif, guard `akunting.approve`. Default-nya tetap job bulanan `DepresiasiAsetJob`; aksi ini supaya aset yang baru didaftarkan di tengah bulan tidak menunggu jadwal tgl 1. Panggil service **langsung** (bukan dispatch job) → tidak melanggar larangan sync queue dispatch di request, dan idempoten per periode.
- `toggleForm()` → `resetValidation()` supaya error percobaan sebelumnya tidak muncul lagi saat formulir dibuka ulang; sukses simpan juga bersih error.
- State ConfirmDialog disposal (`disposalHarga`, `disposalAkumulasi`, `disposalSisaBuku`) — dialog sekarang menampilkan angka yang akan di-write-off, bukan cuma nama aset.
- `filterStatus` + `filterStatusOptions` (BARU) — saring tabel per status; **rekap sengaja tetap mencakup seluruh aset cabang** supaya angka di kartu ringkasan tidak ikut berubah-ubah saat filter dipakai. Values di luar daftar diabaikan (tidak bocor ke query).

**Test baru**
- `tests/Feature/AsetRegisterTest.php` (26 test): route terdaftar (URI + `permission:akunting.view` + `auth`), halaman 2xx utk `akunting.view`, 403 utk kasir & redirect utk tamu, view render + `assertViewIs`, empty state informatif, rekap benar, **aset cabang lain tidak bocor**, banner tanpa cabang aktif, tambah aset (termasuk harga berformat `"12.000.000"`), validasi berbahasa Indonesia, error lama dibersihkan, guard `akunting.create`, jalankan depresiasi (jurnal 2 baris + `akumulasi_depresiasi` + `depresiasi_terakhir_bulan` terupdate), **idempoten** (jalankan dua kali → jurnal tidak dobel), periode tidak valid ditolak, guard `akunting.approve`, depresiasi tidak menyentuh aset cabang lain, disposal 2 langkah + jurnal `JRL-DSP-` balance, batal disposal tanpa efek, disposal ditolak utk aset sudah disposal / aset cabang lain, filter status.

**Verifikasi** (per-file, suite penuh dilarang — RAM 1GB)
- `php artisan test tests/Feature/AsetRegisterTest.php` → **26 passed** (110 assertions).
- Regresi wajib: `php artisan test tests/Feature/AsetTetapTest.php` → **8 passed** (18); `php artisan test tests/Feature/SemuaHalamanTest.php` → **9 passed** (55).
- `./vendor/bin/pint` (3 file: view test + komponen + routes) → **PASS**.
- Verifikasi markup: HTML hasil render diperiksa (baris tabel berisi nominal `12.000.000` / `500.000` / `9.000.000`, `role="progressbar"`, kartu mobile `aset-mobile-*`, `x-format-number` + `@error` Indonesia di formulir, ringkasan ConfirmDialog disposal). Tampilan visual di browser nyata **belum** diverifikasi.
- Catatan (di luar scope lane ini, tidak diubah): `AsetRegister` masih `use WithPagination` padahal `render()` memakai `->get()` tanpa `paginate()` — pagination-nya jadi tidak aktif (register aset per cabang kecil, jadi belum bermasalah). `SemuaHalamanTest::$routes` juga tidak ditambah `/app/akunting/aset` (file itu dipakai lane lain; coverage-nya sudah ada di `AsetRegisterTest`).

## 2026-09-25 — B-10g: Satu sumber aturan validasi jurnal manual + widget Neraca ikut laporan ✅

**P2-1 (lanjutan B-10e) — validasi baris jurnal punya DUA sumber aturan (drift)**
- B-10e benar: aturan akun/nominal/sisi sudah dipindah ke `Akunting\Services\ValidasiBarisJurnal` dan dipakai `JurnalService::post()` + `AkuntingController::storeJurnalManual()` ([API: ACC-04]). **Tapi** `AkuntingDashboard::buildManualJournalValidation()` masih menyalin aturan yang sama manual — lengkap dengan kalimatnya sendiri ("Akun tidak tersedia atau sudah nonaktif…", "Nominal harus lebih besar dari nol.", "Jurnal belum balance. Total debit harus sama dengan total kredit."), sehingga user melihat pesan berbeda dari API untuk masalah yang sama persis.
- `buildManualJournalValidation()` kini **tidak menyalin aturan sama sekali**: closure `after()` memanggil `ValidasiBarisJurnal::cekBarisPerBaris()` dan menempelkan pesannya ke field baris yang salah (`akun_kode` / `jumlah` / `sisi`), lalu untuk balance memakai `ValidasiBarisJurnal::pesanBelumBalance()` / `pesanNilaiTransaksiNol()`. Struktur form (tanggal, deskripsi, minimal dua baris, kolom wajib) tetap di UI karena itu aturan form, bukan aturan jurnal.
- `ValidasiBarisJurnal` dapat tambahan **method pesan saja** (`pesanNominalNol`, `pesanSatuSisi`, `pesanBelumBalance`, `pesanNilaiTransaksiNol`) + `cekBarisPerBaris()` yang mengembalikan pesan per indeks baris. `cekBaris()` (dipakai API) tetap ada dengan **signature & hasil persis sama** — sekarang `array_values` flatten dari `cekBarisPerBaris()`, jadi tidak ada lagi dua salinan aturan di dalam service-nya sendiri.
- `gt:0` pada rule Laravel `baris.*.jumlah` dihapus agar nominal-nol tidak lagi dilawan dua pesan; aturan nominal > 0 sekarang hanya dari service. Pesan UI yang berubah (kategori yang sama, teks seragam dgn service): akun tidak/nonaktif → "Akun COA tidak ditemukan: X" / "Akun X — N sedang nonaktif…"; nominal nol → "Nominal jurnal pada akun X harus lebih besar dari nol."; belum balance → "Jurnal tidak balance: debit Rp … ≠ kredit Rp …" (identik dgn pesan `JurnalService::post()`; teks itu tidak diubah karena `JurnalService` milik lane lain — kesamaannya dijaga regression test).
- Tanpa layer abstrak baru: Livewire → `ValidasiBarisJurnal` langsung, seperti API.

**P1-7 (lanjutan B-10e) — widget Neraca dashboard bisa menampilkan "tidak balance" palsu**
- `getNeracaProperty()` menjumlah sendiri saldo akun dan menghitung `total_ekuitas` **tanpa laba periode berjalan**, sementara laporan Neraca canonical (`ExportLaporanService::neracaSaldo()`) menghitung saldo kumulatif + `laba_periode_berjalan`. Akibatnya kartu Neraca dashboard menulis "tidak balance" padahal semua jurnal double-entry benar (atau sebaliknya, saat rugi) → indikator SALAH.
- Widget kini memanggil `ExportLaporanService::neracaSaldo($cabangId, $sampai)` — sumber yang sama dengan export Excel dan API [API: ACC-06] — jadi angka, status, dan selisih tidak mungkin berbeda. Scoping `cabang_id` (cabang aktif dari session) dipertahankan; `periodeSampai` kosong jatuh ke `now()` seperti default API.
- Konsekuensi API: `data.neraca` di [API: ACC-06] tidak berubah sama sekali (sudah memakai `neracaSaldo()` sejak B-10e) — yang diselaraskan hanya widget dashboard.
- Cache: `Cache::remember()` dengan **key dan TTL yang sama persis** dengan `AkuntingController::neraca()` (`laporan-neraca-{cabangId}-{sampai}`, 15 menit, konstanta `NERACA_CACHE_TTL`) — jadi dashboard & API membaca hasil yang sama, dan tidak query berat dua kali. Belum ada key config TTL di repo; kalau suatu saat ada, cukup dibaca di satu tempat itu.
- View (tanpa redesign): subtitle jadi "Saldo kumulatif s/d {tanggal}", kolom Ekuitas tambah baris **Laba Periode Berjalan** dan Total memakai `total_ekuitas_bersama_laba`, bar status memakai `balance`/`selisih` dari laporan dengan teks **SEIMBANG / TIDAK SEIMBANG** + label akun "Aset = Kewajiban + Ekuitas + Laba Periode Berjalan" + **Selisih** (`tabular-nums`, ribuan titik ADR 0011) + catatan "Angka saldo kumulatif sejak awal pembukuan — bukan perubahan periode". Ambang balance ikut pakai yang sama (`abs($selisih) < 0.01`, sebelumnya `< 1`).

**Test baru**
- `tests/Feature/JurnalValidasiDashboardVsApiTest.php` (6 test / 40 assertions) — paritas pesan dashboard ↔ API untuk kasus sama: akun nonaktif, akun tidak ditemukan, sisi tidak sesuai saldo normal, nominal nol (`assertSame` daftar pesan dashboard vs `data.errors` API), belum balance (**dashboard == API 400 == exception `JurnalService::post()`**), plus jurnal sah tetap bisa ditinjau & diposting.
- `tests/Feature/NeracaWidgetDashboardTest.php` (4 test / 38 assertions) — widget dashboard == `neracaSaldo()` per key (`basis`, total, laba berjalan, selisih, `balance`) **dan** nilainya absolut benar (aset 7.900.000 = kewajiban 2.000.000 + ekuitas 5.000.000 + laba 900.000), kartu menampilkan SEIMBANG + selisih + label kumulatif, ledger rusak satu-sisi → TIDAK SEIMBANG + selisih Rp 500.000 (dan widget tetap sama dgn laporan), jurnal cabang lain tidak bocor ke widget.

**Verifikasi** (per-file, suite penuh dilarang — RAM 1GB)
- `php artisan test --filter=JurnalValidasiDashboardVsApiTest` → **6 passed** (40); `--filter=NeracaWidgetDashboardTest` → **4 passed** (38).
- Regresi wajib: `--filter=JurnalManualTest` → **4 passed** (22); `--filter=JurnalManualApiTest` → **8 passed** (28); `--filter=AkuntingIntegritasTest` → **15 passed** (67); `--filter=ExportLaporanTest` → **10 passed** (72); `--filter=NeracaKumulatifTest` → **4 passed** (37); `--filter=SemuaHalamanTest` → **9 passed** (55).
- Regresi tambahan sesuai blast radius: `--filter=JurnalServiceTest` → **4 passed** (8); `--filter=JurnalFailClosedTest` → **13 passed** (50); `--filter=ComputedPropertyFixTest` → **4 passed** (5).
- `./vendor/bin/pint --test` (4 file lane ini) → **PASS**. `vendor/bin/phpstan analyse` 2 file PHP lane ini → **[OK] No errors** (baseline `phpstan-baseline.neon` diselaraskan: 4 entry `AkuntingDashboard` count 3→2 karena `Collection::map()` versi lama dihapus, + 2 entry `property.notFound` untuk computed prop yang dipakai `render()`).
- Tidak bisa diverifikasi: tampilan visual di browser nyata (kartu Neraca) — hanya markup hasil render yang diperiksa; dan `composer qa:stan` **seluruh** app (di luar 2 file lane ini masih ada error milik lane lain, mis. `tests/Feature/DumpAsetHtmlTest.php` belum di-pint).
- Catatan lintas-lane (tidak disentuh): pesan balance & "nilai transaksi > 0" masih ditulis literal di `JurnalService::post()` (berkas milik lane lain). Teksnya identik dengan `ValidasiBarisJurnal::pesanBelumBalance()` / `pesanNilaiTransaksiNol()` dan kesamaan itu dikunci test — kalau teks service suatu saat diubah, test ini akan gagal (indikasi drift yang disengaja).

## 2026-09-25 — B-10f: Fail-closed kas sesi, jejak pelaku mutasi stok, `paid_at`, import atomik ✅

**P0-4 (lanjutan) — `KasSesiState` masih fail-open**
- `bukaKas()` menulis baris sesi dulu lalu memanggil jurnal; `tutupKas()` menandai `status = 'tutup'` **sebelum** `postJurnalSelisih()`. Keduanya menelan exception (`Log::warning`), jadi kas bisa **"tertutup" tanpa jurnal** tanpa jejak apa pun. Kini keduanya dibungkus `DB::transaction` tunggal: sesi + jurnal buka kas, dan update status `tutup` + jurnal selisih, berada di transaksi yang sama → kegagalan jurnal me-rollback semuanya (sesi tidak pernah ada / status tetap `'buka'`, `ditutup_at` & `selisih` tetap NULL).
- `postJurnalKas()` / `postJurnalSelisih()` tidak lagi menelan exception: `Log::error` + lempar `\Exception` **Bahasa Indonesia** ("Gagal memposting jurnal buka kas: …" / "Gagal memposting jurnal selisih kas: …"). Konsumen (`PosController` POS-07/08 dan `PosKasir::submitKas`) sudah `try-catch \Exception` dan akan menampilkan pesan itu ke kasir — **kontrak API tidak berubah**, `sesiKasAktif()` tetap `?object`.
- Dua kondisi yang **bukan** kegagalan sengaja tetap non-melempar: (1) saldo awal `0` → tidak ada nilai untuk diposting (kasir shift nol tetap bisa buka kas — lihat `IntegrasiModulTest` yang memanggil `bukaKas(0, …)`), (2) `jurnal_akuntansi` belum ada (modul akunting belum dimigrasi → environment check, sama seperti `tableExists('kas_sesi')`).
- Akun `110-01` Kas kini `firstOrCreate` (pola yang sama dengan `310-01`/`520-07`) supaya jurnal tetap bisa balance walau COA belum di-seed penuh. `saranClockOut()` dipindah **ke luar** transaksi (setelah commit) — clock-out absensi non-blocking tidak boleh menggagalkan tutup kas.

**P1-6 — `StockMutationLog.user_id` belum terisi di jalur kanonik**
- `StokDeductionService::kurangi()` sudah menerima `?int $userId` dan sudah menuliskannya ke `StokLog`, tetapi `StockMutationLog::create()` tidak mengirimnya → mutasi stok (SOT) tidak bisa dibuktikan pelakunya. Kini dikirim. Kolomnya sudah ada (migrasi B-10b `2026_09_25_030100_add_user_id_to_stock_mutation_log`, nullable) → **tidak perlu migrasi baru**. Signature publik `kurangi()` **tidak** diubah.
- `ImportProdukService` (file milik lane ini) punya defect yang sama pada `StockMutationLog` 'import:excel' → `user_id` juga diisi. 7 call site lain (`WmsController`, `OpnameTab`, `TransferTab`, `ProdukService`, `GrnService`, `CycleCountService`, `SidMigrateToUteParts`) **tetap belum** mengisi `user_id` — di luar kepemilikan file lane ini, dicatat untuk tindak lanjut.

**P1-7 — `transaksi.paid_at` hilang diam-diam**
- **Temuan audit perlu dikoreksi:** kolom `paid_at` **sudah ada** di tabel `transaksi` (migrasi `2026_09_16_000027_create_payment_shipping_tables` menambahkan `payment_reference`, `payment_url`, `paid_at` langsung ke `transaksi`). **Tidak ada tabel payment terpisah** — hanya `pengiriman` yang tabelnya baru.
- Penyebab sebenarnya: `Transaksi` memakai atribut `#[Fillable([...])]` dan ketiga kolom payment **tidak ada** di dalamnya, sehingga Eloquent membuangnya diam-diam. Akibatnya `paid_at` (webhook Duitku + seeder demo) **dan** `payment_reference`/`payment_url` (`PaymentController::create()` [API: PAY-01]) selalu hilang tanpa error.
- **Keputusan: `transaksi.paid_at` tetap sumber kebenaran** — tidak ada duplikasi ke tabel lain dan tidak ada migrasi baru. Penulisan di webhook **tidak** dihapus; yang diperbaiki adalah kontras fillable (+ cast `paid_at` → `datetime`). Diverifikasi ulang seluruh repo: **tidak ada satu pun reader/laporan** yang bergantung pada `transaksi.paid_at` (sebelum ini pun tidak), jadi tidak ada laporan yang rusak maupun yang perlu dibuatkan kolom.

**P1-5 — Import stok & jurnal agregat tidak atomik**
- **Lintas cabang ditolak (opsi (a), paling sederhana & aman untuk RAM 1GB).** Helper baru `cabangTunggalDariRows()` menolak file yang menunjuk gudang dari >1 cabang dengan pesan Indonesia ("Import lintas cabang tidak didukung: file ini menunjuk gudang dari 2 cabang (…). Pisahkan file per cabang lalu import satu per satu agar jurnal stok awal tetap ter-posting ke cabang yang benar."). Pengecekan jalan di `preview()` (user tahu sebelum commit) **dan** di `commit()` **sebelum** ada mutasi apa pun — tidak ada impor setengah jadi. Satu query `Gudang::with('cabang')` (bukan N). Baris tanpa `gudang_id` (master tanpa stok) tidak dihitung sebagai cabang → tetap boleh di-import.
- **Jurnal agregat sekarang ATOMIK dengan mutasi stoknya.** Sebelumnya jurnal 130-01/310-01 di-post **di luar** semua transaksi chunk memakai `$cabangId` dari **baris pertama** — kalau file lintas cabang, seluruh persediaan terbebankan ke satu cabang; kalau chunk gagal, jurnal tetap ada tanpa mutasi. Kini jurnal di-post **di dalam** transaksi chunk yang sama dengan mutasi baris-baris chunk tersebut, memakai `$cabangId` hasil resolusi up-front. `no_jurnal` per chunk: `JRL-IMP-{tanggal}-IL{import_log}` (chunk pertama, format lama dipertahankan agar laporan/assert yang ada tetap cocok) dan `…-C{n}` untuk chunk berikutnya. Chunk 100 baris tetap (bukan satu transaksi raksasa).
- **Savepoint per baris** (nested `DB::transaction`): baris yang gagal tidak lagi menyisakan `Produk`/`SkuVariant`/`stok_items` setengah tertulis di dalam chunk yang sama, sehingga nilai jurnal chunk selalu = mutasi utuh. Bila jurnal sudah pernah diposting (retry parsial), kini ada `Log::warning` alih-alih `return` diam-diam agar bisa direkonsiliasi.
- Bonus (file milik lane ini): auto-detect delimiter CSV **gagal** untuk file 1–2 baris data (sampel kosong → dua hitungan 0 → delimiter selalu `';'`, padahal penulis file bisa `','`). Sekarang fallback ke baris header.

**Migrasi baru (perlu owner jalankan)**
- **Tidak ada.** `stock_mutation_log.user_id` sudah ada dari B-10b, dan `transaksi.paid_at` sudah ada dari `2026_09_16_000027`.
- **Perbaikan prasyarat (bukan fitur):** `2026_09_17_0100_add_ppn_keluaran_account.php` + `2026_09_22_110000_add_ppn_fields_and_reconcile_coa.php` menulis `AkunCOA` lewat Eloquent **sebelum** tabel `activity_log` dibuat (migrasi `2026_09_23_010150`). Setelah B-10d menambahkan `LogsActivity` ke `AkunCOA`, **seluruh suite test gagal** dengan `no such table: activity_log`. Kedua migrasi kini dibungkus `ActivityLogger::withoutLogging()` — migrasi tidak boleh menulis activity log. Tidak ada perubahan skema.

**Test baru**
- `tests/Feature/KasSesiJurnalFailClosedTest.php` (8 test): jurnal buka kas gagal → **tidak ada sesi** sama sekali; saldo awal 0 → tetap berhasil tanpa jurnal; jurnal selisih gagal → status tetap `'buka'`, `ditutup_at`/`selisih` NULL; tutup kas sukses → status `'tutup'` + jurnal 520-07/110-01 balance; tanpa selisih → tidak ada jurnal tambahan; sesi tetap aktif setelah tutup kas gagal (kasir bisa coba lagi); buka kas ganda tidak menghapus sesi lama; scoping per cabang.
- `tests/Feature/StockMutationUserIdTest.php` (4 test): `kurangi()` mengisi `user_id` + relasi `user` ter-resolve; jalur `kurangiDariGudangTersedia()` juga mengisi; `userId = null` tetap boleh (kolom nullable, mis. webhook Duitku); `StokLog` & `StockMutationLog` satu pelaku & delta sama.
- `tests/Feature/TransaksiPaidAtTest.php` (5 test): ketiga kolom payment ada di tabel `transaksi`; `paid_at` tersimpan saat `create` **dan** saat `update` (termasuk dikosongkan oleh callback gagal); `payment_reference`/`payment_url` tidak terbuang; **webhook Duitku benar-benar mengisi `paid_at`**.
- `tests/Feature/ImportProdukAtomikTest.php` (7 test): preview **dan** commit menolak file lintas cabang (pesan Indonesia + nama cabang, tanpa efek samping: 0 produk/stok/log/jurnal); baris tanpa gudang tetap boleh; import satu cabang → stok 11 + `StokLog` 3 + `StockMutationLog` 3 (semua ber-`user_id`) + jurnal 2 baris balance Rp 390.000 di **cabang yang benar**; jurnal gagal → **rollback penuh** (0 produk/stok/log/jurnal, tidak ada jurnal tanpa mutasi); baris duplikat tidak menyisakan produk setengah tertulis dan tidak masuk nilai jurnal; import ulang tidak membuat jurnal kedua.

**Verifikasi** (per-file, suite penuh dilarang — RAM 1GB)
- `--filter=KasSesiJurnalFailClosedTest` → **8 passed** (31 assertions); `--filter=StockMutationUserIdTest` → **4 passed** (14); `--filter=TransaksiPaidAtTest` → **5 passed** (15); `--filter=ImportProdukAtomikTest` → **7 passed** (50).
- Regresi wajib: `--filter=PosStokSinkronTest` → **7 passed** (72) — assertion `assertNull($mutasi->user_id)` yang sengaja menandai "lane lain belum menyetelnya" **diperbarui** jadi `assertSame($kasir->id, $mutasi->user_id)` sesuai kontrak B-10f ini; `--filter=SemuaHalamanTest` → **9 passed** (55); `--filter=WmsImportVerifyTest` → **1 passed** (40); `--filter=WmsImportTemplateDownloadTest` → **2 passed** (12); `--filter=SerialNumberTest` → **17 passed** (170).
- Pendukung: `--filter=DuitkuWebhookTest` → **8 passed** (46); `--filter=IntegrasiModulTest` → **2 passed** (54); `--filter=JurnalFailClosedTest` (+ kas sesi) → **13 passed** (50); `--filter=AbsensiShiftTest` → 6 passed, **1 gagal** (`test_clock_out_mengisi_jam_keluar_idempotent` — **pre-existing & tidak terkait lane ini**: test memakai `Carbon::create(2026, 9, 24, 17, 0, 0)` sedangkan `clockIn()` memakai `now()`, jadi sejak 2026-09-25 `AbsensiService::clockOut()` membuat baris `absensi_log` ber-`tanggal` berbeda dan `AbsensiLog::first()` mengembalikan baris hari ini yang `jam_keluar`-nya NULL. Perbaikan: pakai `now()` di test, bukan di service — dibiarkan untuk lane berikutnya); `--filter=WmsDashboardSplitTest` → **7 passed**; `--filter=ExportLaporanTest` → **10 passed**; `--filter=ReorderTest` → **10 passed**.
- `./vendor/bin/pint --test` → **PASS (405 files)**.
- Catatan lintas-lane: `tests/Feature/TransaksiPaidAtTest.php` (untracked, sama dengan nama file yang pernah disebut di entri B-10d) ditulis ulang oleh lane ini — bila lane Duitku masih menyimpan versi filenya, perlu direkonsiliasi.

## 2026-09-25 — B-10d: Audit trail lengkap (activity log) + depresiasi terjadwal ✅

**P1-3 — Audit trail belum lengkap (spatie/laravel-activitylog)**
- Lima model yang belum ter-log kini memakai `LogsActivity` + `getActivitylogOptions()` dengan `logOnly()` **atribut sensitif saja** (bukan `logAll()`), `logOnlyDirty()`, dan `dontLogEmptyChanges()` (setara `dontSubmitEmptyLogs` di v4) supaya tidak ada baris log kosong memboroskan storage di server 1GB. Deskripsi event Bahasa Indonesia (label aksi reuses `CatatAktivitas::labelAksiAktivitas`):
  - `AkunCOA` → `Akun COA dibuat/diperbarui/dihapus` (`kode`, `nama`, `tipe`, `kelompok`, `saldo_normal`, `parent_id`, `is_active`). Master global → `cabang_id` NULL di log, bukan dikarang.
  - `StokLog` → `Log Stok …` (`jenis`, `jumlah_sebelum`, `perubahan`, `jumlah_setelah`, `gudang_id`, `produk_id`, `sku_variant_id`, referensi). `cabang_id` **diturunkan dari `gudang_id`** oleh `AktivitasCabang`; `catatan` free-text tidak dilog.
  - `ApprovalRequest` → `Permintaan Approval …` (status/scope + `cabang_id` untuk filter riwayat). **`payload_json` tidak pernah dilog** (bisa berisi gaji/komisi/pelanggan).
  - `ServisStatusLog` → `Riwayat Status Servis …` (jejak state machine PRD §4.3: status_dari/ke, aksi, alasan). Tabel ini tidak punya kolom `cabang_id` → log tidak di-stamp cabang (scoping cabang tetap ditegakkan di query tiket).
  - `KampanyeBroadcast` → `Kampanye Broadcast …` (judul, channel, status, jadwal, penghitung). **`pesan` dan `segment` tidak pernah dilog** (isi pesan + filter pelanggan).
- Event **"satu entri jurnal logis berhasil diposting"**. Sebelumnya tidak ada: activity log hanya ada per baris (`JurnalAkuntansi`), sehingga 1 jurnal 4 baris = 4 entri dan tidak ada yang bilang "jurnal ini sudah diposting". `JurnalService::post()` kini, **setelah `DB::transaction` commit**, mencatat 1 activity pada model `JurnalHeader` dengan deskripsi `Jurnal {no} diposting — {n} baris, total debit Rp {x}` dan properties `no_jurnal`, `tanggal`, `cabang_id`, `sumber`, `jumlah_baris`, `total_debit`, `total_kredit`, `referensi_tipe`, `referensi_id`. **Isi baris jurnal tidak ikut dilog** (bisa sensitif + boros). Event memakai `'created'` supaya label UI `AktivitasLog::aksi_label` tetap "Dibuat" dan bisa difilter dropdown aksi. Jurnal yang gagal/rollback tidak menyisakan activity apa pun.
- `config/activitylog.php` → `default_except_attributes` diperluas sebagai *defense-in-depth*: `kunci_terenkripsi`, `payload_json`, `pesan`, `segment`, `password_hash`, `kode_verifikasi`, `two_factor_backup_codes`.ujudnya model yang kelalaian `logAll()`, field tersebut tetap tidak pernah masuk snapshot.
- **`audit_logs` (AuditService) tidak punya `cabang_id` + snapshot kosong**: migrasi baru menambah `cabang_id` (nullable, FK `cabang` → `nullOnDelete`) + index komposit `(entitas, entitas_id, cabang_id)`. `AuditService::catat()` kini (a) menulis `cabang_id` dari `session('cabang_id')` (NULL di CLI/queue/entitas global), (b) menerima argumen berupa Eloquent Model/Collection dan otomatis menjadikannya snapshot array, (c) mengisi `entitas_id` dari primary key model bila argumennya null, dan (d) **membuang field sensitif** dari snapshot (`password`, `kunci_terenkripsi`, `two_factor_backup_codes`, dst). Snapshot yang tetap tidak tersedia dibiarkan **NULL — TIDAK diisi `{}`**: memalsukan snapshot lebih berbahaya daripada kosong, yang wajib tetap tercatat adalah aksinya. Baris lama tidak di-backfill karena tidak ada `session('cabang_id')` di CLI (kolom nullable, diisi ke depan saat request).
- Catatan performa: `StokLog` adalah tabel_append-only dengan volume tinggi (POS/servis/GRN/opname/transfer), jadi menambah 1 baris `activity_log` per mutasi stok. Tetap diminta, tetapi ini tradeoff yang perlu dipantau bila RAM 1GB mulai sesak.

**P1-4 — Depresiasi belum terjadwal & AsetRegister tak punya route**
- `DepresiasiAsetJob` (F3-3) **ada tapi tidak pernah terdaftar di scheduler** sehingga depresiasi aset tetap tidak jalan. kini didaftarkan `monthlyOn(1, '01:30')->withoutOverlapping()` — slot sepi setelah KPI 00:30, jalur `database` queue (bukan sync), idempoten per periode sehingga aman walau dobel. Notifikasi bila gagal **tidak** ditambahkan: `DepresiasiAsetJob` berada di luar lane berkas B-10d, dan pola notifikasi repo (`NotificationService::kirim('inapp', …)`) sudah dipakai job itu untuk kasus sukses.
- **BLOCKED — route `aset.register`**: view `modules.akunting.livewire.aset-register` **belum ada** di repo (hanya `akunting-dashboard.blade.php` dan `laporan-pajak.blade.php` di `resources/views/modules/akunting/livewire/`). Mendaftarkan route sekarang akan menghasilkan HTTP 500 (`ViewNotFoundException`), jadi route sengaja **tidak** ditambahkan dan halaman kosong pun tidak dibuat. Yang perlu dilakukan owner: buat view tersebut, lalu tambahkan `Route::get('/akunting/aset', AsetRegister::class)->name('aset.register')->middleware('permission:akunting.view')` di group `/app` (`akunting.view` **sudah ada** di `RolesAndPermissionsSeeder` — tidak ada permission baru).

**Migrasi baru (perlu owner jalankan)**
- `2026_09_25_040000_add_cabang_id_to_audit_logs_table.php` → `php artisan migrate`. Nullable + `nullOnDelete` + index komposit; aman terhadap baris lama.

**Test baru**
- `tests/Feature/AuditTrailLengkapTest.php` (13 test): create+update ter-log utk kelima model, cabang ter-stamp dari gudang (StokLog) / dari kolom (ApprovalRequest), `pesan`/`segment`/`payload_json` **tidak ada** di snapshot, update tanpa perubahan tidak menambah baris log, event jurnal diposting **tepat 1× per post** (bukan per baris) + properties terisi + tidak memuat data baris, jurnal gagal tidak menyisakan activity, `AuditService` menulis `cabang_id` + snapshot dari Model + membuang field sensitif + tidak mengisi `{}`, migrasi `audit_logs.cabang_id` ada, dan `DepresiasiAsetJob` terdaftar di scheduler (`30 1 1 * *` + `withoutOverlapping`).

**Verifikasi** (per-file, suite penuh dilarang — RAM 1GB)
- `tests/Feature/AuditTrailLengkapTest.php` → **13 passed (72 assertions)**.
- `--filter=ActivityLogTest` → **7 passed (110 assertions)**; `--filter=JurnalServiceTest` → **4 passed**; `--filter=AsetTetapTest` → **8 passed**; `--filter=SemuaHalamanTest` → **9 passed** (regresi wajib).
- Pendukung: `--filter=TwoFactorTest` → **18 passed** (pemakai `AuditService`), `--filter=ApprovalEngineTest` → **20 passed**, `--filter=BroadcastPromoTest` → **8 passed**.
- `./vendor/bin/pint --test` (10 file milik B-10d) → **PASS**.
- Catatan lintas-lane (bukan penyebab, tidak diperbaiki di sini): `tests/Feature/TransaksiPaidAtTest.php` lane Duitku sempat berisi syntax error sehingga `php artisan test --filter=…` (yang memuat seluruh suite) gagal total; test B-10d dijalankan per-path file `./vendor/bin/phpunit tests/Feature/X.php`. `pint --test` atas seluruh repo masih melaporkan 2 file milik lane lain.

## 2026-09-25 — B-10e: Neraca saldo kumulatif, validasi COA API/service, fixture komisi integrasi ✅

**P1-7 — Laporan Neraca tidak menghitung saldo historis**
- `ExportLaporanService::dataNeraca()` hanya mengambil jurnal dari **awal bulan berjalan**, sehingga saldo sebelum awal bulan hilang dan neraca tidak balance untuk periode setelah bulan pertama. Kini `dataNeraca($cabangId, $sampai)` memakai **saldo kumulatif s/d `tanggal_sampai`** lewat helper baru `jurnalsKumulatif()` (query dasar `queryJurnal()` dipisah agar scoping cabang tetap sama). Judul laporan dan PHPDoc menyebut "NERACA (SALDO KUMULATIF s/d …)" supaya tidak disalahbaca sebagai laporan perubahan periode; perbedaan kedua basis itu dijelaskan eksplisit di PHPDoc — laporan **perubahan periode** (laba rugi, arus kas, jurnal umum) tetap memakai rentang `$dari..$sampai`.
- Basis neraca kini **satu sumber**: method publik `ExportLaporanService::neracaSaldo()`. `AkuntingController::neraca()` ([API: ACC-06]) memanggilnya di dalam `Cache::remember`, sehingga angka dashboard/API dan export tidak lagi bisa berbeda.
- Penyeimbangan neraca: akun pendapatan & beban belum ditutup ke Laba Ditahan sehingga `total_aset ≠ kewajiban + ekuitas` walau semua jurnal double-entry benar. `neracaSaldo()` kini menghitung **Laba Periode Berjalan** (pendapatan − beban kumulatif) dan melaporkannya di sisi ekuitas (`laba_periode_berjalan`, `total_ekuitas_bersama_laba`, `selisih`, `balance`) → **Total Aset = Total Kewajiban + Ekuitas + Laba Periode Berjalan**. **Tidak ada akun COA baru**: akun penyeimbang standar sudah ada di `AkunCoaSeeder` — `310-01 Modal Pemilik` + `310-02 Laba Ditahan` (tipe ekuitas) — sehingga tidak ada angka hardcode sama sekali.

**P2-1 — Validasi COA API tidak sinkron dengan UI**
- Aturan validasi jurnal manual yang sebelumnya hanya hidup di form UI Livewire diekstrak ke service kecil `Akunting\Services\ValidasiBarisJurnal` (tanpa layer abstrak berlebihan): `cari()`/`cariAktif()` (akun ada + `is_active`), `cekBaris()` (akun ada → akun aktif → nominal > 0 → satu sisi → sisi sesuai `saldo_normal`), plus method pesan Bahasa Indonesia dengan teks yang sama seperti UI.
- `AkuntingController::storeJurnalManual()` ([API: ACC-04]) sekarang memanggil `ValidasiBarisJurnal::cekBaris()` dan membalas **422 dengan pesan Indonesia** (daftar galat per baris di `data.errors`). Aturan `exists:akun_coa,kode` dilepas karena pesan default Laravel berbahasa Inggris. Kewajiban **balance debit = kredit tetap dijaga** `JurnalService::post()` dan tetap membalas 400 dengan pesan "Jurnal tidak balance…".
- `JurnalService::post()` kini menolak akun **nonaktif** untuk semua sumber jurnal (sebelumnya hanya mencari kode), pesan: "Akun {kode} — {nama} sedang nonaktif dan tidak dapat dipakai untuk jurnal baru." Pesan akun tidak ditemukan tidak diubah ("Akun COA tidak ditemukan: {kode}") agar kontrak call site dan test lama tetap aman. **Aturan sisi TIDAK** dipaksakan di service: alur otomatis sah melakukan entri kontra-sisi (contoh HPP: `510-02` debit / `130-01` Persediaan kredit) — memaksakannya akan mematikan alur POS/servis. Ada test penjaga `test_jurnal_service_tetap_menerima_entri_kontra_sisi`.
- Catatan lanjutan (di luar kepemilikan file B-10e): `Akunting\Livewire\AkuntingDashboard::buildManualJournalValidation()` masih menyalin aturan yang sama. File tersebut milik lane lain dan tidak disentuh — sebaiknya nanti memakai `ValidasiBarisJurnal` agar tiga tempat benar-benar satu sumber.

**P1-8 — `IntegrasiModulTest::test_full_flow_pos_komisi_servis_kasbon_transfer_berseimbang` gagal (pre-existing)**
- Fixture lama hanya men-seed `SkemaKomisi` (tabel legacy), sedangkan jalur produksi `PosController` → `KomisiService::hitungKomisiMultiAktor()` mencocokkan rule `komisi_skema` (aktor_tipe × trigger_tipe + is_aktif) sehingga tabel `komisi` kosong. Yang diperbaiki adalah **fixture, bukan engine produksi**: rule `KomisiSkema` umum reseller ditambahkan (`aktor_tipe` reseller, `trigger_tipe` penjualan, `aktor_id` null = semua reseller, `kategori` null = semua kategori, `tipe` persen `nilai` 5, `min_amount` 0, `is_aktif` true) dan `SkemaKomisi` legacy dipertahankan untuk paritas tabel.
- Test yang namanya "berseimbang" sebelumnya hanya mengecek total global (jurnal tidak balance bisa saling meniadakan di total). Kini **setiap `no_jurnal` dicek debit = kredit** lebih dulu, baru dijumlahkan.

**Test baru**
- `tests/Feature/NeracaKumulatifTest.php` (4 test): 4 jurnal di bulan sebelumnya + 1 jurnal bulan berjalan → `total_aset 7.900.000 = kewajiban 2.000.000 + ekuitas 5.000.000 + laba berjalan 900.000`; export CSV memuat akun yang hanya bergerak di bulan lalu, `STATUS = SEIMBANG`, `SELISIH = 0`; API ACC-06 angkanya identik dengan export; laba rugi tetap berbasis periode.
- `tests/Feature/JurnalManualApiTest.php` (8 test): API menolak akun nonaktif / sisi berlawanan dengan saldo normal / nominal nol / akun tidak ada (semua 422 dengan pesan Indonesia), tetap menolak jurnal tidak balance (400), menerima jurnal balance (201), `JurnalService::post()` menolak akun nonaktif, dan entri kontra-sisi untuk alur otomatis tetap boleh.

**Verifikasi** (per-file, suite penuh dilarang — RAM 1GB)
- `--filter=IntegrasiModulTest` → **2 passed** (54 assertions); sebelumnya 1 failed.
- `tests/Feature/NeracaKumulatifTest.php` → **4 passed** (37 assertions).
- `tests/Feature/JurnalManualApiTest.php` → **8 passed** (28 assertions).
- `tests/Feature/ExportLaporanTest.php` → **10 passed**; `JurnalManualTest` → **4 passed**; `JurnalServiceTest` → **4 passed**; `PpnTest` → **13 passed**; `SemuaHalamanTest` → **9 passed**; `JurnalFailClosedTest` → **5 passed**; `AkuntingIntegritasTest` → **15 passed**.
- `./vendor/bin/pint --test` (7 file milik B-10e) → **PASS**.
- Catatan lintas-lane (bukan penyebab, tidak diperbaiki di sini): `tests/Feature/TransaksiPaidAtTest.php` (lane Duitku `paid_at`) sempat berisi syntax error sehingga `php artisan test --filter=…` (yang memuat seluruh suite) gagal total; test B-10e dijalankan per-path file `php artisan test tests/Feature/X.php`. `PosStokSinkronTest::test_api_pos_mengurangi_stok_lewat_service` gagal pada `assertNull($mutasi->user_id)` karena `StokDeductionService` (lane B-10f) kini mengisi `user_id` — ekspektasi test yang perlu disinkronkan oleh lane pemilik file tersebut.

## 2026-09-25 — B-10b: Integritas data race-safety (webhook Duitku, StokLog POS, PO/GRN) ✅

**P0-5 — Webhook Duitku (Marketplace) belum race-safe**
- `PaymentController::webhook()` memecah proses ke `prosesLunas()` / `prosesGagal()`. Semua operasi (cek status → validasi nominal → potong stok → jurnal → komisi → notifikasi) kini berada dalam SATU `DB::transaction` dengan `lockForUpdate()` pada baris `transaksi`. Dua webhook bersamaan tidak bisa dua-duanya lolos guard status: yang kedua membaca status `lunas` di bawah lock dan mengembalikan `Already processed` tanpa efek samping (tanpa stok, jurnal, komisi, atau notifikasi kedua).
- Validasi nominal ditambahkan: `amount` callback dibandingkan dengan `transaksi->total_akhir` dengan toleransi 1 rupiah untuk pembulatan. Selisih lebih besar ditolak tanpa efek samping apa pun (status tetap `menunggu_pembayaran`, tanpa potong stok/jurnal) dan dikembalikan sebagai HTTP 200 `success: false` dengan pesan Indonesia berisi nominal tagihan vs nominal diterima (format ADR 0011), plus `Log::error` untuk rekonsiliasi manual — selisih nominal permanen sehingga retry tidak menolong.
- Guard idempotensi dipindah ke dalam lock dan digeneralisasi ke konstanta `STATUS_FINAL`; jalur `resultCode` 01–05 (gagal/kadaluarsa) juga kini di-lock sehingga callback gagal yang terlambat tidak dapat menimpa order yang sudah `lunas`.
- Blok catch tidak lagi menulis balik `status => 'menunggu_pembayaran'` secara manual — `DB::transaction` sudah me-rollback, dan update manual berisiko menimpa order yang bersamaan sudah `lunas`.

**P1-2 — StokLog vs StockMutationLog tidak sinkron (API POS)**
- `PosController::store()` (API POS-01) tidak lagi menulis `StokItem` + `StokLog` sendiri. Seluruh pengurangan stok didelegasikan ke `StokDeductionService::kurangi()` (jalur kanonik yang juga dipakai `PosKasir`), persis seperti Livewire: `lockForUpdate()`, satu kriteria resolusi baris stok, `StokLog` **dan** `StockMutationLog`, serta `izinkanNegatif: true` untuk backorder POS yang disengaja.
- Efek samping yang ikut hilang: log yang sebelumnya bisa berbohong (baris stok tidak ada → `jumlah_sebelum` 0 padahal tidak ada baris stok yang terpotong) dan penomoran `no_transaksi` yang tidak terkunci.
- Migrasi additive `2026_09_25_030100_add_user_id_to_stock_mutation_log.php` menambahkan `stock_mutation_log.user_id` (nullable, FK `users` → `nullOnDelete`), selaras dengan `stok_log.user_id`. `StockMutationLog` mendapat `user_id` di `Fillable` dan relasi `user()`.

**P1-6 — PO/GRN/approval tanpa row lock & referensi jurnal**
- `GrnService::setujuiGrn()` dan `tolakGrn()` kini mengambil baris GRN dengan `lockForUpdate()` di dalam transaksi (sebelumnya `findOrFail` di luar transaksi membaca status basi). `selesaikanGrn()` juga mengunci dan membaca ulang baris GRN di bawah lock sehingga jalur auto-finalize `inputGudang()` ikut terlindungi; status instance milik pemanggil disinkronkan kembali agar `inputGudang()` mengembalikan status yang sama dengan database.
- `PurchaseOrderService::terimaBarang()` dan `bayarPO()` mengunci baris PO dengan `lockForUpdate()` lalu memeriksa ulang status/sisa utang di bawah lock — double-submit atau pembayaran dari dua kasir tidak lagi bisa melampaui sisa utang atau menggandakan stok masuk.
- Jurnal penerimaan PO dan jurnal pembayaran PO sekarang di-stamp `referensi_tipe`/`referensi_id` menunjuk `PurchaseOrder` (sebelumnya `null`, sehingga tidak bisa ditelusuri untuk rekonsiliasi AP dan drill-down audit).
- `Utang::create()` pada `PurchaseOrderService::terimaBarang()` mengisi `cabang_id` dari gudang tujuan PO. Baris `Utang` yang sudah terlanjur `NULL` tetap ditangani migrasi backfill B-01 dan tidak diubah di sini.

**Migrasi baru (perlu owner jalankan)**
- `2026_09_25_030100_add_user_id_to_stock_mutation_log.php` → `php artisan migrate`. Nullable + `nullOnDelete`, aman terhadap baris lama.

**Verifikasi**
- `php artisan test --filter=DuitkuWebhookTest` → **8 passed (46 assertions)**: dua/dua webhook bersamaan menghasilkan satu jurnal (4 baris) dan stok berkurang sekali; nominal tidak cocok ditolak dengan pesan Indonesia tanpa efek samping; toleransi 1 rupiah tetap diproses; callback gagal terlambat tidak menimpa order lunas.
- `php artisan test --filter=GrnTest` → **23 passed (135 assertions)**: jurnal terima & bayar PO ter-stamp referensi PO, `Utang.cabang_id` terisi, finalisasi ganda tetap satu jurnal/satu stok/satu Utang.
- `php artisan test --filter=PosStokSinkronTest` → **7 passed (71 assertions)**: API POS mengisi `stock_mutation_log` tanpa regresi `stok_log`, stok cabang lain tidak tersentuh, jurnal tetap balance.
- Regresi: `--filter=SerialNumberTest` → **17 passed (170 assertions)**; `--filter=SemuaHalamanTest` → **9 passed (55 assertions)**.
- `./vendor/bin/pint` → **PASS**.

**Catatan / keputusan owner (tidak diubah)**
- **PPN pada jurnal marketplace**: jurnal order Duitku membukukan seluruh `total_akhir` ke `410-01` tanpa memisahkan PPN ke akun `220-01`, berbeda dari pola POS yang memisahkan DPP (410-01) dan PPN (220-01). Laporan penjualan marketplace jadi tidak konsisten dengan POS dan PPN marketplace tidak tercatat di akun pajak. **Belum diubah — perlu keputusan owner.**
- `transaksi.paid_at`, `payment_reference`, dan `payment_url` tidak ada di tabel `transaksi` (kolomnya ada di tabel payment terpisah) dan tidak ada di `Fillable` `Transaksi`, sehingga penulisan `paid_at` pada webhook selama ini diam-diam dibuang. Di luar scope B-10b (menempel pada lane payment/marketplace).
- `IntegrasiModulTest::test_full_flow_pos_komisi_servis_kasbon_transfer_berseimbang` gagal pada assert komisi (tabel `komisi` kosong). Sudah diverifikasi **pre-existing**: gagal identik setelah `PosController` dikembalikan ke versi `HEAD`, dan tidak ada file komisi/reseller yang termodifikasi di working tree.
- `StockMutationLog.user_id` masih NULL untuk jalur kanonik karena `StokDeductionService` tidak boleh disentuh pada task ini; pengisiannya perlu follow-up lane WMS.

## 2026-09-25 — B-10c: Perintah rekonsiliasi READ-ONLY (`ute:rekonsiliasi`) ✅

**Perubahan**
- File baru `app/Console/Commands/RekonsiliasiAkunting.php` dengan signature `ute:rekonsiliasi`. Terdaftar lewat auto-diskover `app/Console/Commands` (dibuktikan `ute:backup` dan seluruh `sid:*` juga jalan tanpa daftar manual), jadi `routes/console.php` dan `bootstrap/app.php` **tidak** disentuh.
- Perintah ini murni SELECT. Tidak ada `insert`/`update`/`delete`/`migrate`/`seed`, tidak ada penulisan file, tidak ada ekspor CSV. Semua query dijaga `Schema::hasTable`/`hasColumn` sehingga pemeriksaan dilewati (bukan error fatal) bila tabel/kolom belum ada — termasuk `cabang_id` yang mungkin belum ter-backfill.
- Sembilan kategori laporan, masing-masing jadi satu blok tabel `JUDUL | KATEGORI | JUMLAH | SEVERITY | CONTOH-BARIS + DSM`, disusul tabel `PEMERIKSAAN | USULAN PERBAIKAN (dry-run, belum dieksekusi)`. Kategori yang bersih menampilkan `✓ tidak ada anomali`.
  1. `jurnal` — Σ debit ≠ Σ kredit per (cabang, nomor jurnal), `cabang_id` NULL, nomor duplikat per cabang, baris tanpa `referensi_tipe`/`referensi_id`, baris tanpa `user_id`.
  2. `transaksi` — status final (`selesai`, `lunas`) tanpa jurnal, dicocokkan lewat `referensi_tipe` = `Transaksi::class` + `referensi_id` **dan** nomor transaksi pada `deskripsi` jurnal.
  3. `po` — `purchase_order` status `diterima` tanpa jurnal, dicek lewat referensi `PurchaseOrder::class` maupun jurnal GRN (`Grn::class` → `grn.po_id`); contoh memuat ID, nomor, dan total.
  4. `payroll` — `payroll_periode` `selesai`/`dibayar` tanpa jurnal `PayrollPeriode::class`, plus `payroll_slip` yang sudah `approved`/`dibayar` tetapi `jurnal_id` NULL. Slip `draft` sengaja tidak dihitung (NULL memang normal saat draft).
  5. `opname` — jumlah jurnal distinct per `referensi_tipe` = `StokOpname::class`; yang lebih dari satu ditandai sebagai indikasi overposting beserta hitungannya.
  6. `piutang` — `cabang_id` NULL, status tidak sinkron vs `jumlah_dibayar`, `jumlah_dibayar > jumlah`, dan Utang orphan (`referensi_tipe` = `pembelian`/`komisi`/FQCN dengan `referensi_id` 0 atau menunjuk baris yang tidak ada).
  7. `stok` — `stok_log` tanpa pasangan `stock_mutation_log` (dibandingkan per kombinasi gudang + produk + referensi, jumlah baris), `stok_items.jumlah` negatif, dan selisih total `perubahan` vs `delta`.
  8. `audit` — `audit_logs` tanpa `cabang_id`, `audit_logs` tanpa snapshot `sebelum` **dan** `sesudah`, `activity_log` tanpa `attribute_changes`.
  9. `migrasi` — selisih berkas `database/migrations/*.php` vs tabel `migrations`.
- Opsi `--kategori=jurnal,po,payroll` untuk menyaring, `--limit=20` (default) untuk jumlah contoh per kategori. Kategori asing ditolak dengan pesan Indonesia.
- Semua angka format Indonesia `number_format(..., 0, ',', '.')` sesuai ADR 0011, termasuk nilai rupiah dan label `(+N baris lain)` saat contoh dipangkas oleh `--limit`.
- Ringkasan akhir: total anomali per kategori + severity terburuk (`KRITIS`/`SEDANG`/`RINGAN`) dan baris `TOTAL KESELURUHAN`.
- Exit code `self::SUCCESS` (0) bila nihil atau tidak ada kategori KRITIS; `self::FAILURE` (1) bila ada KRITIS, sehingga bisa dipakai sebagai gate CI. `--kategori` tidak valid juga menghasilkan exit 1.

**Cara pakai**

```sh
# Laporan penuh semua kategori
php artisan ute:rekonsiliasi

# Saring beberapa kategori, tampilkan 50 contoh per kategori
php artisan ute:rekonsiliasi --kategori=jurnal,po,payroll --limit=50

# Gate CI: keluar 1 bila ada anomali KRITIS
php artisan ute:rekonsiliasi > rekonsiliasi.txt || echo "ada anomali KRITIS"
```

**Catatan keputusan / asumsi**
- `transaksi` tidak punya status `lunas` di skema saat ini (enum `pending`, `selesai`, `void`, `dibatalkan`). Status `lunas` tetap dimasukkan daftar final agar aman bila skema dikembangkan.
- `purchase_order`, `payroll_periode`, `payroll_slip`, `audit_logs`, dan `stok_log` memang belum punya `cabang_id`; laporan hanya memeriksa kolom yang benar-benar ada, sisanya ditandai `[dilewati]` beserta alasannya.
- `audit_logs` tidak punya kolom `cabang_id` sama sekali, jadi pemeriksaan itu selalu `[dilewati]` sampai ada migrasi penambahan kolom — itu bukan anomali data.
- `utang.referensi_tipe` bersifat transisional: kode berjalan memakai FQCN model (`PurchaseOrder::class`) sementara kolom didokumentasikan sebagai `pembelian`/`komisi`. Kedua bentuk dipetakan ke tabel tujuan.
- `stok_log` dan `stock_mutation_log` sengaja tidak berbagi kunci surrogate. Pencocokan memakai kombinasi gudang + produk + referensi dan membandingkan **jumlah baris**, bukan baris per baris, supaya baris kembar yang sah tidak salah dibaca sebagai anomali.

**Verifikasi**
- `php artisan test --filter=RekonsiliasiCommandTest` → **9 passed (59 assertions)**: registrasi otomatis, data bersih → exit 0 dengan blok `✓ tidak ada anomali` untuk semua kategori, data anomali → exit 1 dengan label tiap kategori dan angka `Rp 1.500.000`, ekspektasi output via `expectsOutputToContain`, filter `--kategori`, batas `--limit` (`(+3 baris lain)`), kategori asing ditolak, **bukti READ-ONLY** lewat sidik jari 14 tabel sebelum dan sesudah dijalankan, serta pemeriksaan yang dilewati tanpa error fatal saat `audit_logs` tidak ada.
- `./vendor/bin/pint app/Console/Commands/RekonsiliasiAkunting.php tests/Feature/RekonsiliasiCommandTest.php` → **PASS 2 files**.
- Audit sumber: tidak ada `insert`/`update`/`delete`/`DB::statement`/`Schema::create|table|drop` di dalam perintah.
- Perintah sengaja **tidak** dijalankan terhadap MySQL live, dan tidak ada `migrate`/`db:seed` yang dipanggil. Seluruh contoh output diambil dari fixture SQLite `:memory:`.

**Di luar scope**
- Tidak ada mode perbaikan (`--fix`); itu disengaja untuk lane ini. Perbaikan tetap lewat alur aplikasi (JurnalService, PurchaseOrderService, StokDeductionService) supaya idempoten.
- `JurnalService`, `AkuntingController`, `AkuntingDashboard`, `PaymentController`, `PosController`, `StokDeductionService`, `StockMutationLog`, `KasSesiState`, `ServisService`, `PayrollService`, `routes/web.php`, dan `bootstrap/app.php` tidak disentuh.

## 2026-09-25 — B-10a: 4 perbaikan P0 integritas akunting ✅

**P0-1 — AR/AP berubah status tanpa jurnal**
- Service baru `app/Modules/Akunting/Services/PembayaranSubledgerService.php` jadi satu-satunya sumber kebenaran `bayarPiutang()`/`bayarUtang()`: `lockForUpdate()` baris subledger, validasi sisa + cabang, update subledger **dan** `JurnalService::post()` dalam **satu** `DB::transaction` (retry 3x), plus `referensi_tipe`/`referensi_id` ke baris pembayaran untuk rekonsiliasi.
- Exception jurnal tidak lagi ditelan `Log::warning()` lagi. `AkuntingController::bayarPiutang/bayarUtang` sekarang mengembalikan 422 (gagal bisnis/jurnal) atau 404 (baris bukan milik cabang) dengan pesan Indonesia.
- `AkuntingDashboard::bayarPiutang/bayarUtang` yang sebelumnya **tidak mem-post jurnal sama sekali** kini memakai service yang sama. Kunci idempotensi (`Str::uuid()` saat modal dibuka, atau header `Idempotency-Key` untuk API) menolak double-submit/retry.
- `openBayarPiutangModal`/`openBayarUtangModal` kini resolve baris dengan scope `cabang_id` (sebelumnya `find($id)` bebas cabang).

**P0-2 — `/app/akunting` tanpa permission & bocor antar cabang**
- Route `routes/web.php:261` dipasang `permission:akunting.view`. Permission yang dipakai **sudah ada** di `RolesAndPermissionsSeeder` (`akunting.view/create/edit/approve`, `piutang.view/manage`, `utang.view/manage`) — tidak ada permission baru.
- Guard server-side `abort_unless(auth()->user()->can(...), 403)` pada setiap method mutasi Livewire: `simpanJurnalManual` (akunting.create), `simpanCoa` (akunting.edit), `bayarPiutang` (piutang.manage), `bayarUtang` (utang.manage), plus kedua method buka modal.
- Branch guard: semua query jurnal/AR/AP/laporan WAJIB `where('cabang_id', ...)` — termasuk `bukuBesar()` yang sebelumnya tanpa filter sama sekali, dan `getJurnalsProperty()` yang sama sekali tidak memfilter cabang. `session('cabang_id')` null kini berarti "tidak match apa pun" (`0`) untuk baca, dan 403 untuk mutasi — bukan "semua cabang".
- `AkuntingController::kasSesi()` tidak lagi menerima `?cabang_id` dari client (bisa melihat sesi kas cabang lain); `export()` memakai `cabangAktif()`.

**P0-3 — Jurnal tanpa idempotensi DB atomic**
- Migrasi baru `2026_09_25_030000_create_jurnal_header_table.php` membuat tabel `jurnal_header` (1 baris per entri) dengan unique `(cabang_key, no_jurnal)` + unique `(cabang_key, idempotency_key)`, plus backfill idempoten dari `jurnal_akuntansi` (group by `cabang_id, no_jurnal`, `insertOrIgnore`). **Nomor jurnal existing tidak diubah.**
- Kenapa `cabang_key` dan bukan `cabang_id` langsung: `cabang_id` nullable, sedangkan MySQL memperlakukan NULL sebagai distinct di unique index — dua jurnal tanpa cabang tidak akan pernah bentrok. `cabang_key` NOT NULL = `cabang_id ?? 0` membuat constraint benar-benar ditegakkan. `jurnal_akuntansi` tetap line-based dan tidak disentuh.
- `JurnalService::post()`: signature tetap sama (+1 parameter opsional `?string $idempotencyKey = null` di posisi terakhir) sehingga 24 call site existing tidak rusak. Klaim header + insert lines kini dalam satu transaction; pelanggaran unique index dari DB diterjemahkan ke pesan `Jurnal {no} sudah pernah diposting (idempotency guard)` yang sama seperti sebelumnya.
- `generateNoJurnal()` (signature tetap) kini baca counter dari `jurnal_header` di dalam transaction dengan `lockForUpdate()` dan memakai `max(suffix)+1`. **LIKE tetap menyertakan `$cabangId`** (landmine).
- Fallback: bila `jurnal_header` belum ada (owner belum migrate), guard lama `exists()` dipakai agar modul lain tidak ikut mati. Cache status tabel di-static per request (server 1GB RAM).

**P0-4 — Fail-open: status commit walau jurnal gagal**
- Servis: `updateStatus()` membungkus update status + log + side effect (termasuk `onSelesai`) + lepas SN dalam satu `DB::transaction`. `onSelesai()` kini `Log::error()` lalu **lempar lagi** `RuntimeException` (pesan Indonesia) alih-alih menelan. Notifikasi pelanggan dipindah ke **setelah** commit. **4 baris jurnal di `onSelesai` tidak diubah. State machine tidak diubah.**
- Payroll: `finalisasiDisetujui()` mem-post jurnal dulu, baru menandai periode `selesai`, dalam satu transaction. `bayarPayroll()` mem-post jurnal pembayaran dulu, baru menandai slip `dibayar` + periode `dibayar`, dalam satu transaction.

**Migrasi baru (owner wajib jalankan)**
```sh
php artisan migrate
# 2026_09_25_030000_create_jurnal_header_table.php
```

**Verifikasi**
- `php artisan test --filter=AkuntingIntegritasTest` → **15 passed (67 assertions)**: bayar AR/AP via Livewire menghasilkan jurnal + subledger sinkron, jurnal gagal → subledger rollback, pembayaran ganda (kunci sama) ditolak, user tanpa permission 403, list AR/AP + buku besar tidak bocor cabang lain, bayar cabang lain 404, `post()` dua kali no_jurnal sama tidak menghasilkan jurnal ganda, backfill idempoten.
- `php artisan test --filter=JurnalFailClosedTest` → **5 passed (19 assertions)**: Servis & Payroll jurnal gagal → status tidak final + happy path tetap jalan.
- Regresi: `JurnalServiceTest` 4 passed, `JurnalManualTest` 4 passed (22), `ServisStateMachineTest` 6 passed (33), `PayrollServiceTest` 6 passed (22), `PayrollApprovalTest` 4 passed, `SemuaHalamanTest` 9 passed (55), `SerialNumberTest` 17 passed (170), `GrnTest` 23 passed (135), `PpnTest` 13 passed, `ExportLaporanTest` 10 passed (72), `PosStokSinkronTest` 7 passed, `ServisTerimaUnitTest` 9 passed, `ServisListQueryTest` 5 passed, `ResellerFase5Test` 2 passed.
- `./vendor/bin/pint --test` → **PASS 396 files**.

**Perubahan test yang tidak bisa dihindari**
- `tests/Feature/JurnalManualTest.php` — user test sebelumnya tanpa role sama sekali; kini dapat role `finance` (permission sudah ada di seeder) karena `simpanJurnalManual` diguard `akunting.create`. Intenti test (validasi balance/saldo normal) tidak berubah.

**Tidak disentuh / catatan**
- `app/Modules/Pos/Services/KasSesiState.php` **tidak diubah** (ditandai lane lain) → P0-4 bagian Kas (`bukaKas` insert sesi lalu `postJurnalKas`, `tutupKas` status `tutup` sebelum `postJurnalSelisih`, dan kedua method yang swallow exception dengan `Log::warning`) masih fail-open. Perlu lane lanjutan: bungkus `bukaKas`/`tutupKas` dalam satu `DB::transaction` dan jadikan `postJurnalKas`/`postJurnalSelisih` throw.
- `PaymentController`, `PosController`, `StokDeductionService`, `StockMutationLog` tidak diubah.
- `IntegrasiModulTest::test_full_flow_pos_komisi_servis_kasbon_transfer_berseimbang` gagal pada assertion komisi (tabel `komisi` kosong). **Sudah gagal sebelum perubahan ini** (dicek via `git stash` pada `JurnalService` + `ServisService` → gagal identik), jadi bukan regresi B-10a.
- `JurnalService::post()` masih throw jika `JurnalAkuntansi` punya `no_jurnal` yang sama (guard legacy dipertahankan) — perilaku ini sama seperti sebelumnya, unique index header menambah jaminan atomic per cabang.
- View Blade Akunting tidak diubah; tombol UI belum disembunyikan per permission (itu kebutuhan desain, bukan gate keamanan — gate sudah ditegakkan server-side).

## 2026-09-25 — B-11: Dropdown COA & verifikasi otomatis jurnal manual ✅

**Perubahan**
- `AkuntingDashboard` kini menyimpan setiap baris sebagai `akun_kode`, `sisi` (debit/kredit), dan `jumlah`; form dimulai dengan dua baris wajib, yaitu debit dan kredit, serta mendukung tambah/hapus baris.
- Dropdown COA memakai `<select>` native ringan dan hanya mengambil akun aktif. Pilihan difilter berdasarkan `saldo_normal`, sehingga akun pendapatan/kewajiban/ekuitas tidak dapat dipilih pada sisi debit dan akun aset/beban tidak dapat dipilih pada sisi kredit.
- Sisi debit/kredit memakai tombol semantik Ute Prism (amber untuk debit, mint untuk kredit). Mengganti sisi membersihkan akun lama agar kombinasi akun/nominal tidak terbawa diam-diam.
- Input nominal memakai directive `x-format-number` ADR 0011 yang sudah ada. Backend juga menerima angka berformat titik dan mengubahnya menjadi angka bersih sebelum diteruskan ke `JurnalService`.
- Panel verifikasi real-time menampilkan total debit, total kredit, selisih, status balance, serta pesan per baris untuk akun kosong/nonaktif, nominal nol, akun pada sisi yang salah, dan jurnal tidak balance. Tombol posting terkunci sampai seluruh validasi lolos.
- Posting manual memakai alur review → `ConfirmDialog` Ute Prism → Toast global, lalu tetap memanggil `JurnalService::generateNoJurnal()` dan `JurnalService::post()`. Validasi client-visible yang sama juga ditegakkan server sebelum nomor jurnal dibuat.

**Verifikasi**
- `php artisan test --filter=JurnalManualTest` → **4 passed (22 assertions)**: tidak balance ditolak, jurnal balance tersimpan dua baris bercabang/user benar, akun pendapatan tidak dapat didebit, dan akun nonaktif tidak muncul di dropdown.
- `php artisan test --filter=JurnalServiceTest` → **4 passed (8 assertions)**.
- `./vendor/bin/pint app/Modules/Akunting/Livewire/AkuntingDashboard.php tests/Feature/JurnalManualTest.php` → **PASS 2 files**.

**Tidak diubah**
- `JurnalService`, format penomoran jurnal scoped cabang, jurnal otomatis POS/Servis/HR, dan modul lain tidak disentuh. View browser belum diverifikasi pada task ini.

## 2026-09-25 — B-09: Sinkronisasi broadcast promo CRM ✅

**Perubahan**
- `NotifikasiKeluar` kini menyimpan `kampanye_broadcast_id` sejak pembuatan outbox, memiliki relasi `kampanyeBroadcast`, dan `KampanyeBroadcast` memiliki relasi balik `notifikasi()`. Index FK sudah tersedia di migration `2026_09_17_000034` sehingga tidak ada skema baru.
- `BroadcastService` memfilter target di SQL, eager-load tier, menulis outbox dengan `chunkById()`, menyimpan campaign FK secara atomic saat create, dan membatasi log ke 100 terbaru dengan metadata pagination.
- `CrmDashboard::kirimBroadcast()` dan endpoint legacy CRM-05 sekarang sama-sama membuat `KampanyeBroadcast` melalui `BroadcastService` (channel default `inapp`); guard server-side `crm.broadcast` ditambahkan dan tombol UI disembunyikan tanpa permission.
- Validasi `dijadwalkan_at` + alias backward-compatible `jadiwalkan_at`, daftar tipe segmen, jadwal tidak lampau, dan pasangan `birthday_month`/`birthday_day` dengan pesan Indonesia.
- Registrasi scheduler `crm:broadcast-terjadwal` dipusatkan ke `bootstrap/app.php` dengan mutex `withoutOverlapping()` + `onOneServer()`.
- Job notifikasi tidak lagi menandai WA tanpa credential atau email tanpa tujuan sebagai `terkirim`; keduanya menjadi `gagal` dengan error Indonesia. Placeholder provider WA tetap tanpa HTTP call.
- Metrik dashboard broadcast kini seluruhnya berdasarkan status lifecycle `kampanye_broadcast` (`draft/terjadwal/terkirim/terkirim_sebagian/gagal`) dan label view diperjelas.

**Verifikasi**
- `php artisan test --filter=BroadcastPromoTest` → **8 passed (36 assertions)**.
- `php artisan test --filter=SemuaHalamanTest` → **9 passed (55 assertions)**.
- `php artisan schedule:list` → satu registrasi `crm:broadcast-terjadwal` dengan mutex.
- `./vendor/bin/pint` → **PASS 387 files**.

**Di luar scope / keputusan owner**
- Provider WA nyata (Fonnte/Wablas/WA Business API) belum dipilih; job hanya menandai credential belum tersedia sebagai gagal.
- Status `terkirim` campaign masih mengikuti semantics async lama; transisi setelah job selesai belum diubah.
- Tidak menambah `cabang_id` ke `pelanggan`/`kampanye_broadcast`; scope global-vs-cabang tetap perlu keputusan owner.
- Kontrak endpoint legacy CRM-05 dipertahankan; tidak menghapus endpoint.
- Konfigurasi Supervisor sistem tidak disentuh. Jika konfigurasi lokal masih menunjuk path supervisor yang salah, path tersebut perlu diperiksa owner di luar task ini.

## 2026-09-25 — B-08: Render `/app/servis` saat memilih pola kunci ✅

**Diagnosis** — `resources/views/modules/servis/livewire/servis-board.blade.php:245-252` memakai `@for($i = 1; $i <= 9; $i++)`, lalu pada baris 249 mengakses `$loop->index`. `@for` tidak menyediakan objek `$loop`; saat modal Terima Unit dirender dengan `terimaForm.tipe_kunci = 'pola'`, `$loop` bernilai null dan Livewire memunculkan `Attempt to read property "index" on null`. Grep seluruh view hanya menemukan satu akses `->index`; sumber data tiket/kanban tidak perlu fallback baru. `groupedTikets` sudah memakai `?? collect()`, sedangkan relasi detail di-eager-load oleh `ServisBoard::getSelectedTiketProperty()`.

**Perubahan**
- `resources/views/modules/servis/livewire/servis-board.blade.php` — `@for` tetap dipakai, tetapi indeks array pola dihitung eksplisit sebagai `{{ $i - 1 }}` (0–8) dan tidak lagi bergantung pada `$loop` yang null. Alur `wire:click` tetap sama.
- `tests/Feature/ServisTerimaUnitTest.php` — menambah regresi render modal dengan tipe kunci `pola`; tiket tanpa sparepart/filter kosong tidak relevan karena kondisi yang dipulihkan adalah loop pola.

State machine, token approval WAIBS, jurnal `onSelesai`, tombol, dan alur terima unit tidak diubah.

**Verifikasi**
- `php artisan test --filter=ServisTerimaUnitTest` → **9 passed (29 assertions)**.
- `php artisan test --filter=ServisListQueryTest` → **5 passed (30 assertions)**.
- `./vendor/bin/pint` → **PASS 386 files**.

## 2026-09-25 — B-07: Error 1038 "Out of sort memory" saat buka daftar tiket servis ✅

**Root cause (terbukti, bukan tebakan)** — `SQLSTATE[HY001] 1038` pada
`select tiket_servis.*, (subquery spareparts_count) … order by created_at desc limit 100`:

- **Row yang di-sort terlalu lebar.** MySQL 8.0.46 mem-packing seluruh baris ke `sort_buffer_size` (**256KB** terukur), sedangkan `tiket_servis.foto_unit` menyimpan base64 foto terima unit — **431.406 byte pada satu baris** (`SELECT MAX(LENGTH(foto_unit))` di `db_staging`, 177 baris). Satu baris saja > sort buffer → 1038 untuk **semua** varian query list.
- **Bukti reproduksi** (read-only, di scratch DB `b07_scratch` hasil salinan skema+data, lalu di-drop): query lama → `ERROR 1038 … Out of sort memory`; `SELECT id, foto_unit … ORDER BY created_at` → 1038; `SELECT id, no_tiket, … (tanpa foto_unit) … ORDER BY` → **OK**; query lama juga 1038 di `db_staging` langsung (`limit 20` tanpa index).
- **Bukti live**: `EXPLAIN` di `db_staging` → `type: ALL`, `possible_keys: NULL`, `Extra: Using filesort`; `SHOW INDEX FROM tiket_servis` → **tidak ada index `created_at`** (ada: PRIMARY, unique `no_tiket`, unique `token_approval`, FK `jenis_servis_id`/`teknisi_id`, `(cabang_id, status)`, `pelanggan_id`).
- **Index saja TIDAK cukup** (temuan penting): dengan `tiket_servis(created_at)` pun `SELECT * … ORDER BY created_at DESC LIMIT 100` masih `type: ALL` + filesort → 1038 (optimizer pilih table scan karena baris sudah dibaca semua), dan `WHERE cabang_id=? AND status=? ORDER BY created_at DESC LIMIT 20` memilih index `(cabang_id, status)` + filesort → 1038 juga. Karena itu fix utamanya di **aplikasi**.

**Perubahan**

- `app/Modules/Servis/Livewire/ServisBoard.php` — `getGroupedTiketsProperty()` (query `limit 100` pada error) jadi **2 tahap**: (1) `…->latest()->limit(100)->pluck('id')` → ORDER BY/LIMIT hanya menyentuh kolom sempit; (2) hydrate `with([...])->withCount('spareparts')->whereIn('id', $ids)->get()` **tanpa ORDER BY** (tidak memicu filesort) + `sortByDesc('created_at')` di PHP agar urutan `latest()` tetap sama. Filter `status`/`search` tetap di langkah 1.
- `app/Modules/Servis/Controllers/ServisController.php` — `index()` [API: SERVICE-02] pola sama: `paginate(20)` dijalankan pada builder `select('id')`, lalu `setCollection()` diisi model penuh (eager load + `spareparts_count`) → struktur JSON paginator tidak berubah.
- `database/migrations/2026_09_25_020000_add_created_at_indexes_to_tiket_servis_table.php` — index `tiket_servis(created_at)` + `tiket_servis(cabang_id, created_at)`, guard `Schema::hasIndex` (idempotent, aman re-run). Index = biaya sort murah utk step-1 (covering index scan) & query list lain; **bukan** syarat agar error hilang (fix aplikasi sudah jalan tanpa index — diverifikasi langsung di `db_staging`).
- `tests/Feature/ServisListQueryTest.php` — 5 test baru.

**Scope & hal yang TIDAK diubah**

- Papan kanban `ServisBoard` **tidak scoped `cabang_id`** (pre-existing — semua tiket semua cabang tampil); `ServisController::index` scoped hanya bila `session('cabang_id')` ada. Perilaku scope tidak diubah, hanya dilaporkan.
- `spareparts_count` **tetap** (dipakai badge `servis-board.blade.php:112`).
- Pola `->latest()` + row lebar serupa di modul lain (`WmsController`, `GrnTab`, `PoTab`, `CrmController`, `PosController`, `Omnichannel*`, `DashboardIndex`, `ApprovalInbox`) **belum disentuh** — kandidat task lanjutan.
- **Opsional/mitigasi saja (TIDAK dijalankan, butuh konfirmasi owner):** menaikkan `sort_buffer_size` per session (mis. 1–2M) di my.cnf — bukan fix utama karena root cause = row base64, dan config server tidak disentuh di task ini.

**Verifikasi**

- `php artisan test --filter=ServisListQueryTest` → **5 passed (30 assertions)**; `--filter=ServisTerimaUnitTest` → 8 passed; `--filter=ExportLaporanTest` → 10 passed; `--filter=SemuaHalamanTest` → 9 passed.
- `--filter=IntegrasiModulTest` → 1 failed (komisi kosong) **pre-existing** — gagal identik setelah fix B-07 di-revert ke HEAD (bukan regresi B-07).
- `./vendor/bin/pint --test` → **PASS 386 file**; `php artisan migrate --pretend` → migrasi index terdeteksi pending (menunggu owner menjalankan `php artisan migrate` di `db_staging`).

## 2026-09-25 — B-12: Fix computed-property serupa B-05 (`SupplierScoreTable`, `SessionManagementPage`) ✅

**Root cause** — sama persis dengan B-05: `render()` mengakses **nama literal property** yang tidak pernah ada di komponen, padahal getter legacy (pola `getXProperty()`) diekspos Livewire v4 (4.4.5, `SupportLegacyComputedPropertySyntax`) sebagai **magic `$this->x`**:
- `app/Modules/Wms/Livewire/SupplierScoreTable.php:65-66` — `$this->scoresProperty` / `$this->suppliersProperty`, seharusnya `$this->scores` (getter `getScoresProperty():33`) / `$this->suppliers` (getter `getSuppliersProperty():50`).
- `app/Modules/Rbac/Livewire/SessionManagementPage.php:72` — `$this->devicesProperty`, seharusnya `$this->devices` (getter `getDevicesProperty():23`).
- Bukti runtime: akses `$component->scores` → `LengthAwarePaginator` OK; `$component->scoresProperty` → `Livewire\Exceptions\PropertyNotFoundException: Property [$scoresProperty] not found on component`. Begitu dirender, keduanya meledak `PropertyNotFoundException` (persis gejala B-05).

**Temuan penting (di luar pola B-05): view keduanya TIDAK PERNAH ADA.**
- `view('modules.wms.livewire.supplier-score-table', …)` dan `view('modules.rbac.livewire.session-management', …)` → `view()->exists()` = `false`; tidak ada file `.blade.php` yang cocok di seluruh repo (termasuk `git log --all`), tidak ada route & tidak ada `<livewire:…>` yang memakai kedua komponen → keduanya **dead code** (F3-2/F3-4 hanya mengirim komponen+service+model+test, blade & route tidak pernah dibuat).
- Konsekuensi: setelah fix wiring, render kini berhenti di `InvalidArgumentException: View […] not found` (bukan lagi `PropertyNotFoundException`). Karena itu **tidak ada view yang perlu disinkronkan** — memang tidak ada yang bisa disinkronkan.
- Test render memakai klasifikasi pengecualian: jalur sukses `assertViewHas` dipakai **begitu view tersedia**; sementara ini kegagalan wajib terjadi di tahap **view**, dan `PropertyNotFoundException` dianggap **REGRESI B-12** → `$this->fail()`.

**Fix (wiring saja — logika bisnis F3-2/F3-4 tidak disentuh)**
- `SupplierScoreTable::render()` → kirim `$this->scores`, `$this->suppliers` (+ komentar penjelas, pola identik `CycleCountPage.php:227-231`).
- `SessionManagementPage::render()` → kirim `$this->devices` (+ komentar penjelas).
- `phpstan-baseline.neon` — 3 entri `property.notFound` diganti `…$scoresProperty`→`$scores`, `…$suppliersProperty`→`$suppliers`, `…$devicesProperty`→`$devices` (pola sama fix B-05; jumlah/identifier `count: 1` tidak berubah).

**Test** — baru `tests/Feature/ComputedPropertyFixTest` (4 test): computed `$this->scores`/`$this->suppliers`/`$this->devices` teresolusi (paginator/collection); render kedua komponen tanpa `PropertyNotFoundException` (dgn guard `assertViewHas` aktif ketika view dibuat nanti). Regresi dibuktikan: fix di-revert → **2 failed** (`Property [$scoresProperty] not found`, `Property [$devicesProperty] not found`); dengan fix → **4 passed**.
Verifikasi: `php artisan test --filter=ComputedPropertyFixTest` → **4 passed (5 assertions)**; `./vendor/bin/pint` (3 file) → **PASS**; `./vendor/bin/phpstan analyse` 2 file komponen → **No errors** (baseline cocok).

**Tindak lanjut (di luar B-12, butuh task/@designer terpisah):** kedua view + route belum pernah dibuat — komponen tidak bisa dirender sampai `supplier-score-table.blade.php` & `session-management.blade.php` (+ route `/app/…`) diimplementasikan. Setelah itu test B-12 otomatis naik ke jalur `assertViewHas`.

## 2026-09-25 — B-06: Terima unit `/app/servis` — kunci gadget terlihat utk semua role berhak, foto opsional, label checklist kondisi fisik ✅

**Diagnosis (A) kunci gadget disembunyikan dari siapa** — dua lapis:
1. **API `[SERVICE-03]` `ServisController::show()`** (sebelumnya `app/Modules/Servis/Controllers/ServisController.php:91-100`) memblokir `kunci_terenkripsi` kecuali role `super-admin`/`admin-toko` **atau** `teknisi` yang *ditugaskan ke tiket tsb* (`$tiket->teknisi_id === $user->id`) → teknisi lain & role non-teknisi dengan `servis.view` tidak bisa melihat. (Inkonsisten dgn `index()` yang tidak pernah menyembunyikan.)
2. **UI** `servis-board.blade.php` — field hanya ada di **form Terima Unit** (dgn label keliru "hanya teknisi/admin"); **modal Detail Tiket tidak menampilkan kunci sama sekali** → teknisi tidak bisa melihat PIN/pola setelah unit diterima.

**Perubahan (A)**
- `ServisController::show()` — blokir role dihapus; pintu akses = middleware `permission:servis.view` (teknisi, admin-toko, super-admin = semua role yg bisa membuka tiket servis). Enkripsi at-rest (cast `encrypted`) tidak berubah.
- `ServisBoard.php` — property `$bukaKunciGadget` (default `false`) + `toggleKunciGadget()`; di-reset di `openDetail()`.
- `servis-board.blade.php` — kartu **"Kunci Gadget"** baru di modal detail: tipe kunci (Pola/PIN/Password/Tidak ada), nilai **ter-mask `••••••••` by default**, tombol **Tampilkan/Sembunyikan** dirender server-side → plaintext **baru masuk DOM setelah user menekan tombol** (bukan x-show statis), plus catatan "data sensitif, jangan dibagikan ke pelanggan". Label form terima diubah → "(opsional, terenkripsi — terlihat oleh semua staf yang berhak membuka tiket servis)".

**Alur checklist kondisi fisik (B) — ditelusuri dari kode, langkah demi langkah**
1. `servis-board.blade.php:273-291` (modal Terima Unit) — label "Kondisi Fisik Unit (opsional)", 6 chip: `Layar, Body, Baterai, Kamera, Speaker, Watermark` (`$fisikChecks`).
2. Klik chip → `ServisBoard::toggleKondisiFisik($check)` (`ServisBoard.php:174-182`) → add/remove di `terimaForm.kondisi_fisik` (toggle murni, tanpa validasi, tanpa required).
3. `simpanTerima()` (`ServisBoard.php:262-295`) tidak memvalidasi `kondisi_fisik` → diteruskan apa adanya ke `ServisService::terimaUnit()` (`ServisService.php:58`) → disimpan sbg JSON (`kondisi_fisik` cast `array`) pada `tiket_servis`.
4. Chip menyala = bagian bermasalah; **tidak** mempengaruhi state machine, estimasi, maupun jurnal (murni catatan fisik saat terima).
5. Modal detail (`servis-board.blade.php:570-583`) menampilkan chip amber **+ empty-state** "Belum ada catatan kondisi fisik…" (sebelumnya bagian hilang total bila kosong → alur tidak terbaca).
6. Step UI di form: **Langkah 1 = kondisi fisik**, **Langkah 2 = foto (opsional)** — keduanya opsional, submit (`Simpan & Terima Unit`) selalu aktif.

**Perubahan (C) foto opsional**
- `ServisBoard::simpanTerima()` — blokir `count($foto) < 2` ("Foto unit wajib minimal 2") **dihapus**; `foto_unit` = `null` bila kosong; tombol submit tidak bergantung foto.
- `ServisController::store()` `[API: SERVICE-01]` — `'foto_unit' => 'required|array|min:2'` → `'nullable|array'`.
- `servis-board.blade.php` — label "Foto Unit" tanpa `*` + "(opsional — depan, belakang, layar)", counter "N/3 foto terisi — Langkah 2 dari 2; boleh dikosongkan". Upload kamera/galeri tidak diubah (tetap berfungsi).

**Test** — baru `tests/Feature/ServisTerimaUnitTest` (8 test, 27 assertions): submit Livewire tanpa foto → tiket `diterima` + `foto_unit` kosong; API tanpa foto → 201; API dengan foto → 2 foto tersimpan (regresi upload); kunci gadget terlihat utk teknisi tak ditugaskan & admin-toko (200 + nilai sesuai), kasir tanpa `servis.view` → 403; modal detail → `assertDontSee('246813')` saat ter-mask, `assertSee` setelah toggle, kembali mask; checklist toggle ON/OFF → `['Body']` tersimpan.
Verifikasi: `php artisan test --filter=ServisTerimaUnitTest` → **8 passed (27 assertions)**; `--filter=ServisStateMachineTest` → **6 passed (33 assertions)**; `--filter=SemuaHalamanTest` → **9 passed (55 assertions)**; `./vendor/bin/pint --test` → **PASS 384 files**. `--filter=IntegrasiModulTest` → 1 failed/1 passed, **bukti pre-existing** (gagal di step komisi POS, identik saat perubahan B-06 di-stash).

**Catatan / keputusan owner (di luar scope B-06)**
- Role `kasir`, `finance`, `staff-gudang`, `marketing` **tidak punya** `servis.view` (seeder) → memang tidak bisa membuka `/app/servis`; jika owner mau role tsb ikut melihat kunci gadget, harus tambah permission di `RolesAndPermissionsSeeder` (keputusan RBAC).
- `ShopController::accountServis()` (marketplace, `app/Modules/Marketplace/Controllers/ShopController.php:209-215`) mengembalikan model `TiketServis` utuh → `kunci_terenkripsi` ikut terkirim ke pelanggan (leak pre-existing, di luar scope modul Servis — layak task terpisah `makeHidden`).
- Dokumen masih menyebut "foto wajib minimal 2": `PRD-Frontend-UteParts.md:120`, `LOCAL.md:95`, komentar kolom `database/migrations/2026_09_16_000018_create_tiket_servis_table.php:24` — belum diubah (kontrak PRD, butuh persetujuan owner).

## 2026-09-25 — B-05: Fix render `/app/wms/cycle-count` (computed property salah akses) ✅

**Gejala** — halaman `/app/wms/cycle-count` 500: `Livewire\Exceptions\PropertyNotFoundException — Property [$schedulesProperty] not found on component: [app.modules.wms.livewire.cycle-count-page]` (`vendor/livewire/livewire/src/Component.php:127`).

**Root cause** — `CycleCountPage::render()` (`app/Modules/Wms/Livewire/CycleCountPage.php:227-229`) memanggil `$this->schedulesProperty` / `$this->tasksProperty` / `$this->raksProperty` — nama **literal property** yang tidak pernah dideklarasikan. Komponen memakai pola legacy computed `getSchedulesProperty()` / `getTasksProperty()` / `getRaksProperty()` (baris 194/201/218), yang di Livewire v4 (4.4.5, `SupportLegacyComputedPropertySyntax`) diekspos sebagai **magic `$this->schedules`**, `$this->tasks`, `$this->raks` — bukan `$this->schedulesProperty`. Livewire tidak menemukan property literal → exception. Pola benar sudah dipakai modul lain (mis. `CrmDashboard::render()` → `$this->tiers`). Bantahan hipotesis: view `cycle-count.blade.php` menerima `$schedules`/`$tasks` (dipakai baris 40/66) — view & komponen sudah sinkron; yang rusak hanya akses di `render()`. Bukti: `phpstan-baseline.neon` sudah mengabaikan `property.notFound` untuk `$schedulesProperty` (di-baseline, bukan diperbaiki).

**Fix (scope minimal, wiring saja — logika bisnis F3-7 tidak disentuh)**
- `app/Modules/Wms/Livewire/CycleCountPage.php` — `render()` kini kirim `$this->schedules`, `$this->tasks`, `$this->raks` (+ komentar penjelas). Getter legacy dibiarkan; query scoping `cabang_id`, validasi, state machine count tidak diubah.
- `phpstan-baseline.neon` — 3 entri `property.notFound` `CycleCountPage::$raksProperty/$schedulesProperty/$tasksProperty` diganti menjadi `$raks`/`$schedules`/`$tasks` (agar tidak jadi unmatched baseline error).
- Turunan dicek: seluruh property yang dipakai view (`$showScheduleForm`, `$scheduleNama`, `$tipeTarget`, `$frekuensi`, `$sampleSize`, `$threshold*`, `$search`, `$filterStatus`, `$selectedTaskId`, `$fisikPerItem`) ada di komponen; route `wms.cycle-count` (`routes/web.php:244`, `permission:wms.view`) & layout `layouts.backoffice` benar — tidak ada referensi rusak lain.

**Test** — baru `tests/Feature/CycleCountPageTest` (4 test, 16 assertions): GET `/app/wms/cycle-count` 2xx + judul; komponen render dgn `assertViewHas('schedules'|'tasks'|'raks')`; buat jadwal muncul di daftar (computed refresh) + `assertDatabaseHas`; buka form count utk task `menunggu_count` (preview `sample_items`). Regresi dibuktikan: dengan fix di-stash → **4 failed** (exception di `CycleCountPage.php:227`), dengan fix → **4 passed**.
Verifikasi: `php artisan test --filter=CycleCountPageTest` → **4 passed (16 assertions)**; `./vendor/bin/pint --test` → **PASS 382 files**.

**Di luar scope (bug serupa, belum disentuh):** `SupplierScoreTable::render()` (`$scoresProperty`/`$suppliersProperty`) dan `SessionManagementPage::render()` (`$devicesProperty`) punya pola salah yang sama — keduanya belum punya test render; layak task terpisah.


**Gejala** — klik "⬇️ Download Template (.xlsx)" di modal Import Master Produk: file `.xlsx` tidak pernah terunduh, browser hanya menampilkan baris-baris teks (respons error JSON).

**Root cause (2 lapis, keduanya di `app/Modules/Wms/Exports/ImportProdukTemplateExport.php`)**
1. **TypeError** — `Excel::download()` (vendor maatwebsite/excel) mengetik argumen #1 sebagai `Maatwebsite\Excel\Concerns\Export`; class hanya `implements WithMultipleSheets` (interface ini TIDAK extends `Export`) → `Argument #1 ($export) must be of type …\Concerns\Export, ImportProdukTemplateExport given`. Karena route di `routes/api.php` kena `shouldRenderJsonWhen(api/*)`, exception jadi respons JSON → inilah "baris data" yang tampil, file tidak dibuat.
2. **Compile error (muncul setelah #1 dibuka)** — kedua anonymous sheet `collection()` tidak punya return type, padahal `FromCollection::collection(): Enumerable` → `E_COMPILE_ERROR Declaration of …::collection() must be compatible with …: Illuminate\Support\Enumerable`.

**Fix (scope minimal, 2 file)**
- `ImportProdukTemplateExport` kini `implements Export, WithMultipleSheets`, kedua `collection()` diberi return type `: Enumerable` (+ import). Konten template tidak diubah: header tetap = `ImportProdukService::templateColumns()` (17 kolom), 2 baris contoh, sheet "Petunjuk".
- Route/controller (`WmsController::downloadTemplateProduk`, `GET /api/wms/produk/import/template`) dan link blade tidak diubah — sudah benar (`Excel::download` → `template-import-produk.xlsx`, `Content-Disposition: attachment`).

**Test** — baru `tests/Feature/WmsImportTemplateDownloadTest` (2 test, 12 assertions): respons 200 + content-type `…spreadsheetml.sheet` + `attachment; … template-import-produk.xlsx` + magic bytes `PK`; isi workbook (2 sheet, judul, header == `templateColumns()`, baris contoh sejajar header).
Verifikasi: `--filter=WmsImportTemplateDownloadTest` **2 passed**; `--filter=WmsImportVerifyTest` **1 passed (40 assertions)**; `./vendor/bin/pint --test` → **PASS 381 files**.

## 2026-09-25 — B-02 + B-03 Fase A: Akurasi stok POS/WMS (kode saja, tanpa repair data live) ✅

**P0 — alur jual di POS**
- **P0-1 backorder stok nol** — `PosKasir::addToCart()` tidak lagi hard-block stok 0; item masuk keranjang dgn **peringatan amber** (banner cart + pill "Stok kosong" per item & di kartu katalog). Cap qty hanya saat `stok_max > 0`; `updateQty()` ikut dgn aturan sama. `processTransaction()`/`tahanTransaksi()` tdk memblokir stok nol — potongan lewat `StokDeductionService::kurangi(..., izinkanNegatif: true)` (kebijakan owner: checkout boleh, stok bisa negatif).
- **P0-2 satu kriteria stok** — varian null = `SUM` produk+gudang lintas semua baris varian (sama persis dgn gauge kartu); varian id = baris persis. Helper baru `hitungStokTersedia()` / `petaStokKatalog()`.
- **P0-3 delegasi potong stok** — `PosKasir` tidak lagi tulis `StokLog` manual → `StokDeductionService::kurangi()` (satu sumber kebenaran StokLog + StockMutationLog).

**P1 — akurasi & scoping**
- **P1-1/P1-3 baris kanonik** — `StokDeductionService`: row resolusi utk varian null = prefer baris `sku_variant_id IS NULL`, else baris `jumlah` maks (tie-break id terkecil) — berlaku di jalur SQL (`cariBarisStok`) & koleksi (`barisKanonik`) supaya preselect == eksekusi; `kurangiDariGudangTersedia()` preselect ikut diperbaiki (dulu pilih baris mana saja → mutasi bisa 0→-qty sementara log berbeda). Auto-create baris `StokItem` `jumlah = 0` bila hilang → log tak pernah bohong.
- **P1-2** — `StokItem` row + `sku_variant_id` ikut ditulis di log (traceable per varian).
- **P1-4** — `ChannelSyncService`: fallback `StokItem::sum` saat `StockMutationLog` kosong (channel sync tdk lagi kirim stok 0 salah).
- **P1-5** — Katalog `take(16)` → load-more (`BATAS_PRODUK_AWAL`, `muatLebihBanyak()`, `updatingSearch()` reset batas) — tak ada lagi hard-stop 16 produk aktif.
- **P1-6** — `StokTab` (WMS): gudang aktif + `$filterGudangId` di-scope `cabang_id` (+ `whereHas('gudang', cabang)` fallback) — stok lintas cabang bocor ke UI cabang lain tertutup.

**P2**
- **P2-1** — `ProdukDanStokSeeder` idempoten: varian `firstOrCreate(['produk_id','nama_varian'])` + SKU deterministik `SKU-{produk_id}-{SLUG}` (re-run tdk duplikat varian).
- **P2-2** — Route `/app/pos` → `permission:pos.view|pos.view-own` (admin-toko/super-admin vs kasir; middleware Spatie split `|`).

**Servis** — `ServisService::inputSparepart`/`kurangiStokServis` delegasi ke `StokDeductionService`, pesan error legacy dipertahankan via `preg_match('/tersedia: (\d+)/')` re-throw.

**Test** — `tests/Feature/PosStokSinkronTest` (6 test, 53 assertions): add-to-cart bervarian == gauge, stok nol dgn peringatan, backorder mutasi negatif + log akurat, potong stok StokLog+mutation log, gudang lintas cabang ditolak, katalog load-more.
Verifikasi: `--filter=PosStokSinkronTest` **6 passed**; `--filter=SemuaHalamanTest` **9 passed**; `--filter=SerialNumberTest` **17 passed**; `--filter=IntegrasiModulTest` **1 failed (pre-existing — gagal identik di tree bersih via `git stash`, komisi multi-aktor tak dpt rule `komisi_skema` di fixture) + 1 passed**; `./vendor/bin/pint` → **PASS 380 files**.

**Di luar scope (keputusan owner/Phase B):** repair 216 varian duplikat live, re-import katalog SID, dedup data.

## 2026-09-25 — B-01: Sinkronisasi/Akurasi/Realtime Dashboard Backoffice (`/app/dashboard`) ✅

Fix akurasi + konsistensi skoping + realtime pada `DashboardIndex` (widget via `getXProperty()`):

**P0 — akurasi omzet & piutang**
- **Omzet hitung transaksi marketplace `lunas`** — konstanta `DashboardIndex::STATUS_OMZET = ['selesai','lunas']` dipakai di 4 titik (Omzet Hari Ini, Tren 30 hari, Komposisi Kategori, Omzet Shift). Sebelumnya `status='selesai'` saja → transaksi marketplace (`PaymentController` menutup order dgn `lunas`, jurnal tetap dibuat) tidak masuk omzet padahal masuk "Laba Bersih".
- **Piutang Jatuh Tempo scoped cabang** — `getPiutangJatuhTempoProperty` sekarang `where('cabang_id', session('cabang_id'))` (konsisten dgn Ringkasan Keuangan & Treasury dalam 1 halaman); `chartPiutangAging` ikut di-scope.
- **BUG BARU terbukti: "Total Piutang" selalu Rp 0** — `selectRaw('SUM(...) as sisa')->value('sisa')` menimpa/kena accessor `Piutang::getSisaAttribute` (yang baca `jumlah` tak ter-select) → hasil 0. Fix: alias `total_sisa`.
- **Migrasi backfill** `2026_09_25_000100_backfill_cabang_id_piutang_utang` — `UPDATE piutang/utang SET cabang_id = <cabang id terkecil> WHERE cabang_id IS NULL`; idempoten (WHERE NULL), skip bila belum ada cabang, `down()` sengaja kosong. `IntegrationSeeder` (piutang + utang) kini mengisi `cabang_id`.

**P1 — role, scoping, realtime**
- **`render()` tidak lagi `match($role)`** → semua variabel widget punya **default aman** (`defaultWidgetData()`), lalu data asli di-evaluate per `hasRole()` yang persis sama dgn `@role` di blade. super-admin kini memuat `treasuryProjection` (dulu → kartu Rp 0), user multi-role tidak 500 di `@forelse($poPending['items'])` / `@foreach($marketInsight['tier'])`.
- **Realtime**: `wire:poll.60s` di root element (1 poll, bukan per-widget) + guard `function_exists('colorHsl')` (view ini pernah di-render >1× dalam 1 process → fatal redeclare).
- **Stok kritis selaras `ReorderService`**: `jumlah < jumlah_minimum AND jumlah_minimum > 0` (dulu `<=` tanpa guard minimum 0); label blade disesuaikan.
- **Scoping**: `getTransaksiTerbaruProperty` → scoped cabang; fallback Omzet Shift tanpa sesi kas → scoped cabang; `komisi` tetap **global by design** (skema tanpa `cabang_id`, sama dgn halaman Reseller) + label "semua cabang" di blade.
- **Drill-down** (Piutang/Transaksi/StokItem/Treasury) dibungkus `@can('laporan.cabang')` → kasir/teknisi/staff-gudang/marketing tak lagi lihat link 403.
- **PO Pending** kini `whereIn(['usulan','draft','dikirim'])` (PO hasil reorder otomatis ikut); label kartu disamakan.

**P2**
- Chart status servis: 7 query → **1 GROUP BY** + status `diajukan_online` ditambahkan.
- Kartu Omzet Hari Ini: jumlah transaksi diberi label "(semua cabang)" (angka global, bukan scoped).
- `serverStatus` (env/cache/queue) disembunyikan utk role selain `super-admin`.

**Test** — `tests/Feature/DashboardSinkronisasiTest` (7 test, 32 assertions): omzet `lunas` (hari ini/tren/kategori/shift), piutang scoped + konsistensi Ringkasan Keuangan, migrasi backfill idempoten (2× `up()`), super-admin dpt Treasury ≠ 0 (`viewData('treasuryProjection')` + `assertSee('12.321.000')`), render multi-role tanpa undefined variable, stok kritis selaras ReorderService.
Verifikasi: `php artisan test --filter=DashboardSinkronisasiTest` → **7 passed**; `--filter=SemuaHalamanTest` → **9 passed**; `--filter=TreasuryWidgetTest` → **4 passed**; `./vendor/bin/pint --test` → **PASS 379 files**.

## 2026-09-24 — Review owner/hardening Fase 4.1–4.3 (perbaikan temuan review dua-sumbu) ✅

Review dua-sumbu (Standards vs Spec, fixed point `d3beaa6...HEAD` = `4cda9f1`) menemukan 5 temuan spec + 7 smell standards; semua temuan substansial diperbaiki (smell judgement-call non-kritis dibiarkan, terdokumentasi di bawah):

**Spec — diperbaiki:**
1. **Double-count komisi teknisi (temuan terburuk)** — `hitungKaryawan` menjumlah `komisiTeknisi + komisiInternal` tanpa dedup: satu tiket dibayar 2× (KomisiTeknisiRule Rp50.000 + rule `komisi_skema` tiket_servis, keduanya di-seed). Fix: **engine-wins per tiket** — `hitungKomisiTeknisi` skip tiket yg sudah punya baris `komisi` engine (status pending/disetujui, window periode; dedup via `whereNotNull('tiket_servis_id')` — kolom `trigger_tipe` tidak ada di tabel `komisi`, hanya di `komisi_skema`). Rule `komisi_skema` teknisi tiket dihapus dari HrSeeder (jadi **3 rule demo**); trigger `tiket_servis` tetap tersedia via rule builder.
2. **§4.2 alur 4 potongan absen — dari partial ke implemented** — `AbsensiService::potonganAbsen()` (count log `absen`/periode, over threshold → nominal/hari × kelebihan; pure & idempotent) + config baru `config/hr.php` (`threshold` 3, `nominal_per_hari` 25.000, `cabang_aktif` [] = **opt-in per cabang, default nonaktif semua**); masuk `hitungPotongan(karyawanId, periode)` → `total_potongan` + key `potongan_absen` di rincian slip.
3. **RBAC `kelola-payroll` (PRD §4.3 L166)** — permission baru di RolesAndPermissionsSeeder → super-admin + finance; route `/app/hr/payroll` gate `kelola-hr` → `kelola-payroll`; `payroll.view` dicabut dari admin-toko. Slip detail `PayrollSlipDetail::open()` di-guard: non-`kelola-payroll`/`kelola-hr` hanya boleh lihat slip where `karyawan.user_id == auth` AND `cabang_id == session cabang` (else 403). Baru: `PayrollPermissionTest` 3 test (admin/finance 200, admin-toko 403, teknisi slip sendiri saja).
4. **`finalizasiDisetujui` bypass F1-1** — guard: total gaji > `THRESHOLD_APPROVAL` (Rp10jt) wajib ada `ApprovalRequest` payroll status `disetujui`, else `DomainException`. Rename typo → **`finalisasiDisetujui`** + docblock side-effect (flip status + post jurnal).
5. **lead_won konversi — false positive, tanpa fix** — verifikasi: `canConvert()` mensyaratkan stage sudah 'won' (hook `update()` sudah terpicu duluan); `'stage' => 'won'` di konversi = no-op re-set.

**Standards — diperbaiki:**
- **Duplicated rule-matching (temuan terburuk)** — 3 salinan query rule di `KomisiService` → helper `queryRuleAktif()`; `matchRule` + `hitungKomisiTargetKpi` pakai helper; **fork `min_amount` per-trigger sengaja dipertahankan** (engine: subtotal kategori; target_kpi: `nilai_aktual` + gate `persen_capaian ≥ 100`) — didokumentasikan di helper; legacy `hitungKomisiDariItems` zero-diff (paritas utuh).
- **Primitive obsession ringan** — konstanta `Komisi::STATUS_{PENDING,DISETUJUI,DITOLAK}` + `PayrollPeriode::STATUS_{DRAFT,DIPROSES,SELESAI,DIBAYAR}` dipakai di kode yg disentuh.
- **Mysterious name** — rename finalisasi (lihat #4).

**Bug laten terungkap & ikut diperbaiki saat implementasi:**
- Enum `payroll_periode.status` = `draft/diproses/selesai/dibayar` — nilai `'disetujui'` lama **selalu melanggar CHECK** (tak pernah ketahuan karena tak ada test finalisasi) → kini `STATUS_SELESAI`.
- `PayrollSlip.jurnal_id` menyimpan string `no_jurnal` ke kolom FK → kini simpan `$jurnalRows[0]->id` (FK id baris jurnal).
- `payroll.blade` pakai `@extends` v2 di komponen Livewire v3 (multiple root → 500 semua user) + `PayrollSlip` tanpa relasi `periode()`/`komisiDetails()` (500) → convert single-root + `->layout('layouts.backoffice')` + relasi ditambahkan.

**Tidak diperbaiki (judgement call, dibiarkan):** duplikasi `rentangPeriode` Absensi/Kpi/Payroll (3 tempat, ringan), `KasSesiState` re-implement clock-in/out (feature envy ringan — ganti ke AbsensiService = luas scope POS), shotgun-surgery trigger hooks (YAGNI, call sites defensible), `min_amount`/`cabang_id` spec generality (diminta spec — reviewer salah baca).

- Verifikasi (2026-09-24): PayrollServiceTest **6/22**, PayrollApprovalTest **4/13**, AbsensiShiftTest **7/17**, KpiHitungTest **6/22**, RosterTest **3/7**, KomisiMultiAktorTest **8/41**, ResellerFase5Test **2/9**, LeadConversionTest **9/55**, DuitkuWebhookTest **4/16**, SemuaHalamanTest **9/55**, PayrollPermissionTest baru **3/7** — semua PASS; `view:cache` OK; `pint --test` PASS 17 file; seeder re-run idempotent di db_staging (RolesAndPermissions + Hr; kelola-payroll aktif, 3 rule komisi).

## 2026-09-24 — PRD-Advanced Fase 4.3: Komisi/Insentif Multi-Aktor (F3-8c) ✅

- **Migrasi** `2026_09_24_230000_create_komisi_skema_tables.php`: tabel baru `komisi_skema` (rule configurable: `aktor_tipe` karyawan/reseller/agen, `aktor_id` nullable = rule umum tipe aktor, `trigger_tipe` penjualan/lead_won/tiket_servis/target_kpi, `kategori` nullable = paritas skema lama, `tipe` persen/nominal, `nilai`, `min_amount`, `cabang_id` nullable, `is_aktif`) + perluasan tabel `komisi` (`aktor_tipe` default `reseller` — kompatibel lama, `aktor_id`, `komisi_skema_id`, `lead_id`, `tiket_servis_id`, `idempotensi_key` unique; `pelanggan_id` → nullable utk komisi karyawan internal tanpa pelanggan). Migrasi + `2026_09_24_230100_add_referral_kode_to_pelanggan_table.php`: `pelanggan.kode_agen` (unique, identitas agen) + `pelanggan.referral_kode` (index, kode agen yang dipakai pembeli).
- **MIGRASI DATA**: `KomisiService::migrasiSkemaResellerKeRuleBaru()` — skema reseller lama (`skema_komisi` + `skema_komisi_reseller` override per pelanggan) disalin ke `komisi_skema` (aktor_tipe=reseller, trigger=penjualan), idempotent; dipanggil dari migrasi; paritas lama→baru diuji test.
- **Engine**: `KomisiService::hitungKomisiMultiAktor(trigger, konteks)` — resolve entitas trigger, kandidat aktor (penjualan: reseller `is_reseller` + agen via referral + karyawan pemilik lead won; lead_won: assigned_to + reseller + agen; tiket_servis: teknisi via `teknisi_id`→`karyawan.user_id`), agregasi per kategori produk (paritas alur lama: prioritas rule spesifik `aktor_id` → umum `null`, override reseller → skema default), `firstOrCreate` idempotensi_key `trigger:refId:skemaId:aktorTipe:aktorId`.
- **Trigger integrasi**: POS API `PosController` + `PosKasir` + marketplace `PaymentController` (lunas Duitku) → engine `penjualan` (menggantikan panggilan `hitungKomisi` reseller; rule lama otomatis tersedia via migrasi skema — paritas nominal teruji); `LeadService::update` saat stage → `won` → `lead_won`; `ServisService::onSelesai` → `tiket_servis` (teknisi; komisi reseller servis tetap alur lama `hitungKomisiDariItems` — tanpa duplikat); `HitungKpiBulananJob` setelah hitung KPI → `target_kpi` (persen_capaian ≥ 100 & nilai_aktual ≥ min_amount; persen dihitung dari gaji pokok; kategori rule = kpi_metric_id).
- **Payroll**: `PayrollService` + `hitungKomisiInternal` — komisi karyawan (status disetujui/pending) masuk komponen komisi slip (rincian baru `komisi_internal` + `komisi_teknisi`; `total_komisi` = teknisi + internal). Komisi reseller/agen tetap alur saldo & payout existing (pelanggan_id mengarah ke pelanggan agen utk payout).
- **UI**: rule builder `KomisiSkemaPage` Livewire di `/app/hr/komisi-skema` (permission `kelola-hr` — super-admin/finance/admin-toko; PRD RBAC `kelola-payroll` sudah ada utk approval payroll): CRUD rule (aktor, trigger, kategori opsional, tipe persen/nominal, min_amount, aktif), filter daftar, toggle/hapus; nav sidebar "HR & Payroll".
- **Seeder**: HrSeeder + 4 rule komisi demo (reseller 3%, agen 2%, marketing lead won Rp50rb, teknisi per tiket Rp10rb) idempotent (updateOrCreate); dijalankan di db_staging.
- Verifikasi (2026-09-24): **KomisiMultiAktorTest 8/8 PASS (41 assertions)** — migrasi skema→rule + idempotent, paritas engine vs alur lama (12.500 = 10.000 override LCD + 2.500 default Aki), rule match multi-aktor (reseller 3rb + agen 5rb + karyawan 2rb dari satu transaksi), idempotent per trigger+rule, tiket servis teknisi, lead won, target KPI, komisi internal masuk payroll; suite terdampak hijau (Reseller/Payroll/Absensi/KPI/Roster/Duitku/Lead/StateMachine **43/43**); `pint --test` **PASS 17 file**; migrasi + seed jalan di db_staging (4 rule demo, kolom pelanggan baru).
- **Catatan**: full-suite `vendor/bin/phpunit` tidak stabil di server RAM 1GB (fatal/premature end saat akumulasi 281 test; test yang "gagal" di run penuh lulus saat diisolasi — mis. PricingServiceTest, SemuaHalamanTest) — verifikasi per fitur via `--filter` tetap metode resmi (sesuai requirement).

## 2026-09-24 — PRD-Advanced Fase 4.2: Absensi, Shift & KPI (F3-8b) ✅

- **Migrasi** `2026_09_24_220000_create_hr_absensi_kpi_tables.php`: 5 tabel — `shift` (jam_mulai/jam_selesai, overnight = selesai < mulai), `shift_jadwal` (roster, unique cabang+karyawan+tanggal), `absensi_log` (unique karyawan+tanggal, status hadir/terlambat/absen/izin/cuti, self_photo/lokasi nullable), `kpi_metric` (rumus whitelist kode), `kpi_hasil` (unique karyawan+metric+periode). Plus perluasan enum jabatan karyawan + `marketing` (MySQL: ALTER enum; SQLite: rebuild kolom jadi string karena env sqlite = varchar+CHECK).
- **AbsensiService**: `clockIn`/`clockOut` idempotent per karyawan+tanggal (jam masuk/keluar tidak ditimpa), status `terlambat` via perbandingan jam masuk vs jam_mulai shift (overnight: terlambat hanya bila di luar jendela shift), `tandaiStatus` (izin/cuti/absen admin), `rekapBulanan`, `autoTandaiAbsen` (basis potongan disiplin komponen payroll, threshold opt-in cabang).
- **KpiService**: rumus WAJIB whitelist (`tiket_selesai` teknisi, `transaksi_kasir`/`selisih_kas` kasir, `lead_won`/`penjualan_lead_won` marketing) — BUKAN eval bebas; selisih_kas inverted (makin kecil makin baik); nilai dihitung dari data real; `hitung()` idempotent via updateOrCreate.
- **Job**: `HitungKpiBulananJob` (queue database) dijadwalkan `monthlyOn(1, '00:30')` di bootstrap/app.php.
- **Integrasi POS (KasSesiState)**: `bukaKas` → gate "Wajib clock-in absensi sebelum membuka kas" (khusus user terdaftar karyawan aktif; fallback lembut bila modul HR belum migrasi); `tutupKas` → saran clock-out otomatis (isi jam_keluar + catatan, non-blocking).
- **Fix konsistensi**: `PayrollService::hitungKomisiTeknisi` memakai `tiket_servis.teknisi_id = karyawan.user_id` (teknisi_id → users), konsisten dgn KPI. Fix bug laten `HrSeeder` import `App\Modules\Rbac\Models\User` (tidak ada) → `App\Models\User`. Fix `RolesAndPermissionsSeeder`: permission `kelola-hr`/`payroll.*`/`hr.lihat-sendiri` kini dibuat di daftar `$permissions` (sebelumnya hanya dipakai givePermissionTo → seeder gagal di DB bersih).
- **RBAC**: permission baru `hr.lihat-sendiri` (kelola-hr, super-admin, finance, admin-toko, kasir, teknisi, marketing, staff-gudang) — karyawan lihat absensi+KPI sendiri; admin via `kelola-hr` existing.
- **UI**: `HrAbsensiKpiPage` (tab Shift/Roster/Absensi/KPI + hitung KPI manual) di `/app/hr/absensi`; `HrSayaPage` (rekap + log + KPI sendiri) di `/app/hr/saya`.
- **Seeder**: HrSeeder + shift demo (Pagi/Siang/Malam-overnight), 5 KpiMetric, karyawan marketing; dijalankan di db_staging + enum terverifikasi.
- Verifikasi (2026-09-24): **AbsensiShiftTest 6, KpiHitungTest 6, RosterTest 3 = 16/16 (46 assertions)**; suite HR gabungan **22/22 (65 assertions)**; `pint --test` **PASS 26 file** disentuh; route `/app/hr/absensi` + `/app/hr/saya` terdaftar.

## 2026-09-23 — Fase 2 follow-up: Sinkron Utang subledger saat GRN finalize ✅

- **Masalah** (flag dari lane fix-4): `GrnService::selesaikanGrn` posting jurnal AP (130-01/210-01) tapi tidak membuat baris `Utang` subledger (legacy `terimaBarang` yang dulu membuat kini tertutup utk PO `dikirim`) → Laporan/Export Utang underreport semua penerimaan via GRN.
- **Fix** (lane fix-6, reuse sesi GRN): `GrnService::catatUtang()` dipanggil tepat di guard yang sama dgn jurnal (`totalHpp > 0`) — subledger ≡ GL. Semantik identik legacy: 1 Utang per PO, idempotency key `(referensi_tipe=PurchaseOrder::class, referensi_id=po_id)`, `no_utang` pola `UTG-Ymd-####`, `status=belum_lunas`, `cabang_id` dari GRN, amount = kredit 210-01 (tanpa gate `metode_bayar` — jurnal GRN selalu kredit 210-01, jadi subledger ikut selalu). Tidak ada jurnal ganda; tidak ada file Akunting diubah (`Utang` sudah fillable + LogsActivity).
- **Deviasi tercatat**: Utang kini dibuat utk PO tunai juga (legacy hanya kredit) — konsisten dgn jurnal; `cabang_id` distamp (legacy NULL → tereksklusi dari export cabang-scoped); keterangan tambah `— GRN {no_grn}`.
- Verifikasi (orkestrator, 2026-09-23): **GrnTest 21 (+3: auto-finalisasi Utang==jurnal, idempoten double-approve, bayarPO kurangi subledger partial+lunas), SemuaHalamanTest 9 (+assert Utang after GRN & bayar), ExportLaporanTest 10, PpnTest 13 — semua hijau**; `pint --test` PASS 303 file.

## 2026-09-23 — PRD-Advanced Fase 3: F3-1 Field-Level Security + F3-6 Treasury Widget ✅

- **F3-1 (G-02)** — Field-level security: `canSeeField()` global helper (`app/Helpers/functions.php`, autoload via composer.json `files`) checks spatie dot-notation `lihat.{field}` permission + finance role override for `harga_beli/margin/profit/cost_price` + super-admin bypass. Blade directives `@cansee('field')` / `@cannotsee('field')` / `@endcansee` registered in `AppServiceProvider::boot()`. Applied to `pos-kasir.blade.php`, `produk-tab.blade.php`, `grn-tab.blade.php`, `po-tab.blade.php`, `cart-content.blade.php`. Livewire components strip sensitive props in `render()` via `canSeeField()` guard (pattern: `$hargaBeli = canSeeField('harga_beli') ? $this->harga_beli : null`).
- **F3-6 (G-06)** — Treasury widget: `DashboardIndex::getTreasuryProjectionProperty()` computes 30-day cash projection = `saldo_kas` (JurnalAkuntansi `akun_coa_id`=110-01 balance) + `piutang_30d` (status≠lunas, jatuh_tempo≤30d) − `utang_30d` (status∈{belum_lunas,sebagian}, jatuh_tempo≤30d). Dashboard blade shows 4-card widget (Saldo Kas, Piutang 30d, Utang 30d, Proyeksi) for `@role('finance')` and `@role('super-admin')`. Test: `TreasuryWidgetTest` 4/4 (finance role calculates correctly, scoping by cabang prevents cross-branch leak, renders in dashboard, hidden from kasir).
- **Fix**: `DashboardIndex` `sum(fn ($j) => ...)` closure not supported by SQLite → replaced with `DB::raw('debit - kredit')`; `akun_id` column name bug → `akun_coa_id`; `sum('sisa')` accessor not included in SQL → replaced with `DB::raw('jumlah - jumlah_dibayar')`. `@cansee`/`@cannotsee` directives initially missing `cannotsee` → added. `canSeeField` function initially defined inside `AppServiceProvider::boot()` causing redeclaration → moved to `app/Helpers/functions.php`.
- Verifikasi (2026-09-23): **TreasuryWidgetTest 4/4, FieldSecurityTest 7/7, SemuaHalamanTest 9/9, semua F2 regression 14 suite 165+ test hijau**; `pint --test` **PASS 325 file**.

## 2026-09-23 — PRD-Advanced Fase 2: Review Mandiri @oracle → Fix 2 P0 + 2 P1 + 3 P2 ✅ FASE 2 TERVERIFIKASI

- **Review spec read-only** (@oracle `ora-1`) atas F2-1..F2-5 vs PRD (§4/§5/§7): verdict awal **BELUM AMAN centang** — 2 P0 production-breaking lolos hardening review sebelumnya, 2 P1, 3 P2. Semua diperbaiki 5 lane write-scope disjoint (fix-1..5) + 1 follow-up (fix-4 revive).
- **P0 F2-1 — session key Lead**: kode Lead membaca `session('cabang_aktif_id')` tapi aplikasi hanya pernah menulis `session('cabang_id')` → production: kanban kosong + create lead 422 (test masking: LeadConversionTest set key yang sama). Fix: 3 read-site (`Lead::scopeForCabang`, `LeadKanban::createLead`, `CrmController::storeLead`) → `cabang_id`; test pakai key kanonik + 1 regresi baru (scoping + create dengan `cabang_id` saja); **P1** activity log Lead (pola `Grn`); **P2** migrasi leads `hasTable` guard.
- **P0 F2-5 — ekspor 403**: `ExportLaporanJob` tidak meneruskan `userId` → worker auth=null → prefix `0_` → ownership check 403 (test masking: handle() in-process saat masih login). Fix: forward `$this->userId` ke `export()` (pola `ReportExportJob`); regresi simulasi worker logout: prefix benar, owner 200 / user lain 403; mutation check terbukti test gagal tanpa fix.
- **P1 F2-2 — bypass API terima PO**: `PUT /wms/po/{id}/status` → `terimaBarang` menerima PO `dikirim` tanpa GRN → stok+jurnal tanpa approval (UI PoTab sudah ditutup, API lolos). Fix defense-in-depth: guard `ValidationException` di `PurchaseOrderService::terimaBarang` + 422 controller + cabang guard endpoint; route ops lain (`dikirim`/`dibatalkan`) tetap hidup. `SemuaHalamanTest::test_po_kredit_diterima_dan_dibayar` di-rewrite ke jalur GRN kanonik (assert bypass 422, jurnal `sumber='grn'` + `no_jurnal=no_grn`, leg 110-01 pembayaran ikut di-assert — lebih kuat dari sebelumnya).
- **P1 F2-3 — SN tanpa UI**: `produk.sn` tidak bisa diaktifkan dari form; tambah stok tanpa input SN. Fix: toggle SN di form produk (+guard create `stok_awal>0` bila sn=true), textarea SN wajib di modal tambah stok bila sn=true (validasi count sebelum tulis DB, persist `tersedia` cabang-scoped, reuse `NomorSeriService`); **P2** activity log `NomorSeri` + `NomorSeriEvent`.
- **P2 F2-4 — polish drill-down**: breadcrumb current dicocokkan dgn `currentModel` tampilan; nilai detail/tabel format ADR 0011 (titik ribuan, ID/kode/sku/barcode tetap raw, bool → Ya/Tidak).
- **Observasi terbuka (belum blocking)**: jalur GRN tidak membuat baris `Utang` subledger (hanya jurnal AP 210-01) — `bayarPO` null-guard tetap jalan, tapi Laporan Utang bisa underreport penerimaan via GRN. Tunda ke Fase 3 / putuskan apakah GRN finalize wajib sync `Utang`.
- Verifikasi gabungan final (orkestrator, 2026-09-23, tree terintegrasi semua lane): **LeadConversion 9, Grn 18, SerialNumber 17, DrillDown 26, Export 10, ApprovalEngine 20, SemuaHalaman 9, Ppn 13, JurnalService 4, Reorder 10, WmsSplit 7, ActivityLog 7, IntegrasiModul 2, Pricing 3, ServisStateMachine 6, HargaFleksibel 7 — 16 suite / 168 test semua hijau**; `pint --test` **PASS 303 file** (termasuk 33 file formatting yang berantakan sebelumnya). Regresi baru: Lead +1, Grn +3, SerialNumber +4, Export +1.
- **✅ FASE 2 TERVERIFIKASI** — F2-1..F2-5 aman ditandai centang di PRD (checkbox sudah [x], kini berbasis review + test aktual).

## 2026-09-24 — PRD-Advanced Fase 3: F3-8 HR Sederhana (Lengkap) ✅

- **F3-8 (G-xx)** — HR & Payroll lengkap: 6 model (Karyawan, KaryawanKomponenGaji, KomisiTeknisiRule, PayrollPeriode, PayrollSlip, PayrollKomisiDetail) + PayrollService extension (hitungDraft, ajukanApproval via F1-1 ApprovalEngine, finalizasiDisetujui, approvePayroll, bayarPayroll). Migration 6 tables with correct FK constraints. COA 520-08 (Beban Komisi) added to AkunCoaSeeder. HrSeeder (Karyawan demo + KomisiTeknisiRule + ApprovalRule). Route `/app/hr/payroll` with `permission:kelola-hr`. Livewire components `PayrollPage` + `PayrollSlipDetail` + blade views (Ute Prism). Tests 6/6 PASS (PayrollServiceTest 4/4: gaji calculation, idempotent, periode, non-teknisi; PayrollApprovalTest 2/2: threshold approval logic). `pint --test` clean.
- **Deviasi**: 520-02 conflict resolved (was Beban Sewa Toko; 520-08 added for Beban Komisi). Migration FK fixes needed (payroll_periode, jurnal_akuntansi table references).
- Verifikasi (2026-09-24): **6/6 test PASS (6 assertions)**, pint clean.

## 2026-09-23 — Fase 2 Hardening: Review Bug Mandiri → Fix P0/P1 ✅

- **Deep bug review independen** (@oracle `ora-1`) atas seluruh changeset Fase 2: **5 P0, 12 P1, 11 P2** — verdict **BLOCK** (green test tidak menutup jalur: method gate Livewire, cross-cabang read, filter UI). P0+P1 diperbaiki oleh 3 lane write-scope disjoint (fix-15/16/17, model mimo); **11 P2 dikerjakan juga di pass lanjutan (fix-18/19/20) — kini semua 28 temuan (5 P0 + 12 P1 + 11 P2) selesai, tidak ada yang ditunda**.
- **P0 (5/5 fixed)**: (1) `GrnTab::setujuiGrn/tolakGrn` tanpa RBAC → `abort_unless(can('approve-workflow'))` + `@can` tombol Tolak + route `/wms` → `permission:wms.view`; (2) IDOR drill detail → guard `itemInCabang()` shared `CABANG_SCOPE` → 403 lintas cabang; (3) `ReportBuilderService` heuristic `getFillable()` → peta eksplisit `CABANG_SCOPE` (kolom/relasi per model, fail-closed `where 1=0` saat scope tak dikenal); (4) penerimaan PO ganda → guard status `dikirim` + maks 1 GRN per PO + finalisasi men-set PO `diterima` + path terima langsung `PoTab::terimaPo` ditutup ("wajib lewat GRN"); (5) `ReportBuilder` kehilangan method `addFilter/removeFilter/drillDown` + listeners → diimplementasi dengan kontrak filter `{field,op,value}` + whitelist kolom (`getAvailableColumns`) & operator.
- **P1 (12/12 fixed)**: scoping cabang `grnList/poList` + `tolakJikaBukanCabang` pada tiap aksi; aksi tab di-route lewat `ApprovalService::proses` (inbox + tab sinkron, hook finalisasi idempoten); flag SN POS di-re-derive server-side dari `Produk` (anti-tamper Livewire prop); SN dilepas saat servis `ditolak` + override keluar zona kerja (SN tidak macet `servis`); snapshot-on-overwrite ke tabel append-only **`nomor_seri_events`** (riwayat jual/servis permanen, LaporanNomorSeri tanpa ubah); kunci item GRN komposit `produk_id|sku_variant_id` (PO 2 varian sama); `tambahStok` `lockForUpdate` + QueryException → pesan Indonesia; konflik SN saat approve → `ValidationException` (bukan `Exception` mentah, approval tidak stuck); cache key export + `md5(columns+filters+groupBy+format)`; URL notifikasi export → `/api/akunting/export/download` (sebelumnya 404); `groupBy` aman MySQL `ONLY_FULL_GROUP_BY` (select grup + `count(*) as jumlah`).
- **P2 (11/11 fixed, pass lanjutan fix-18/19/20)**: scoping saved-report `(milik saya) OR (cabang aktif AND shared)` + kolom `shared` idempoten + checkbox "Bagikan ke cabang" (+ root tunggal blade — bug multi-root Livewire 3 ketahuan saat menyentuh file); heuristik `strlen>2` dihapus (whitelist field/operator sudah dari P0-5/P1-10); `ReportSeeder` cabang via `$user->cabangs()->first()` (session null di console); `DrillDownController` **dihapus** sebagai dead code (bukti: grep `F2-4-0` di PRD = 0 hit) + `mount()` kini panggil `loadDetail($id)` (guard 403 tetap); `laporan.kustom` dihapus dari seeder (PRD-Backend §3 tidak mendefinisikannya, `/laporan` tetap `laporan.cabang`); export job: notice "dipotong maksimal 10.000 baris" + full-dataset cache dihapus (baca → tulis langsung); POS: `updateQty` prune `sn_list` + warning, guard `snItemKey` lintas item, `resumeDitahan` flag SN selalu recompute (`array_merge`, bukan `+`); migrasi index `nomor_seri_produk` di-guard `Schema::hasIndex` + `down()` langkah independen (komentor lama "MySQL auto-dedupe" ternyata salah); filename ekspor `{userId}_{jenis}_{Ymd-His}_{uniq}` (bentrok detik hilang) + prune `exports/` >7 hari (server 1GB) + **ownership check download**: basename wajib `^(\d+)_` == `auth()->id()` selain itu 403 — kontrak prefix `{userId}_` dipenuhi **kedua sisi** (`ExportLaporanService` + `ReportExportJob`).
- Verifikasi gabungan final (orkestrator, 2026-09-23, tree terintegrasi semua lane): **GrnTest 15, DrillDownTest 26 (95 asersi), SerialNumberTest 13 (122 asersi), ExportLaporanTest 9 (66 asersi), ApprovalEngineTest 20, SemuaHalamanTest 9, PpnTest 13, WmsSplit 7, Reorder 10, IntegrasiModul 2, ServisStateMachine 6 — 130 test / 11 suite semua hijau**; `pint --test` **PASS 48 file**. Test regresi baru total: GrnTest +7 (RBAC 403, dobel-GRN, PO non-dikirim, PO→diterima, sinkron inbox tab, PoTab ditutup, permission route), DrillDownTest +10 (IDOR 403, scope StokItem, lifecycle filter, URL unduh, groupBy, parity peta + private-report leak, notice truncate, prefix userId, mount id), SerialNumberTest +6 (tamper `sn=false`, release saat ditolak, history permanen + prune qty, guard lintas item, resume recompute), ExportLaporanTest +4 (filename unik, ownership 403, prune 7 hari, prefix auth).

## 2026-09-23 — PRD-Advanced Fase 2 (F2-5 Export Lengkap Semua Laporan) ✅ FASE 2 LENGKAP

- **F2-5 (G-09)**: `ExportLaporanService` di-extend menutup **7 jenis laporan** — stok, transaksi, jurnal, piutang, utang, servis, komisi — param `format` (xlsx via maatwebsite/laravel-excel / **CSV via `fputcsv` RAM-friendly**), method baru `dataJurnal`/`dataTransaksi`/`dataKomisi`, `dataBukuBesar`/`dataPiutangUtang` cabang-scoped. `ExportLaporanJob` (+`format` ctor) — **queue `database` saja, tidak pernah sync di request**. Scoping ketat `where cabang_id` (baris legacy `cabang_id` NULL dikecualikan — tidak pernah bocor lintas cabang); komisi scoped via `whereHas('transaksi', cabang_id)` + `KomisiService` kini stamp `cabang_id` pada `Utang::updateOrCreate`.
- **UI**: tombol **Export Excel/CSV** di 5 permukaan Livewire — `StokTab`, `ServisBoard`, `ResellerDashboard`, `AkuntingDashboard`, `DrillDownViewer` — dijaga `@can('laporan.cabang')`, dispatch job + toast Indonesia (listener `alert` satu-satunya di app.js). **Satu jalur unduh** memakai mekanisme yang sudah ada (`GET /api/akunting/export/download?path=base64`) — tanpa mekanisme kedua.
- **Bug pre-existing ikut diperbaiki**: `AkuntingController::exportDownload` salah path (`storage_path('app/…')` tidak pernah cocok dengan disk root `storage/app/private`) → kini `Storage::disk('local')->path()`; DrillDownViewer `$breadcrumbs->isEmpty()` → `count(...) === 0` (pre-existing 500).
- Verifikasi (orkestrator, 2026-09-23): **ExportLaporanTest 5/5 (55 asersi)** — payload queue, handle + scoping 2 cabang + URL inapp, table-driven 6 jenis, default xlsx, tombol di 5 permukaan; regresi **DrillDown 16, Grn 7, SerialNumber 7, SemuaHalaman 9, ApprovalEngine 20** — semua hijau; `pint --test` **PASS 10 file**. Implementasi lane fix-14 (model mimo).
- **✅ FASE 2 SELESAI** — F2-1 s/d F2-5 semuanya tercentang & terverifikasi (2026-09-23).

## 2026-09-23 — PRD-Advanced Fase 2 (F2-3 Serial Number)

- **F2-3 Serial Number (G-08)**: flag `produk.sn` (migrasi idempoten `hasColumn` + cast boolean + relasi `nomorSeris()`). Tabel nyata = **`nomor_seri_produk`** (bukan `nomor_seri` sebagaimana tertulis di PRD — ditemukan saat discovery): migrasi ekstensi idempoten menambah `cabang_id` (scoping), `tiket_servis_item_id` (nullable, indexed), index `status`. Lifecycle status **tersedia → terjual → servis → garansi** di `NomorSeri` model + `NomorSeriService` (`parseList`, `validasiUntukGrn`, `simpanDariGrn`, `klaimJual`, `klaimServis`, `lepasServis`, `cariTersedia`, `tolakJikaAdaDobel`).
- **Integrasi GRN [F2-2 → F2-3]**: `GrnTab` modal — textarea SN per baris item `sn=true` (list newline/koma); `GrnService::inputGudang` validasi SN **sebelum `Grn::create`** (reject = nol side effect; count ≠ qty → error Indonesia, GRN tidak jadi); SN di-claim saat `selesaikanGrn` → **kedua path** (qty-sesuai auto & approval `setujuiGrn`) menghasilkan baris SN `tersedia`, tanpa menyentuh Workflow (SN menumpang di JSON `item_qty_received['sn']` yang sudah ada). Tidak ada tipe jurnal baru — jurnal tetap hanya via `JurnalService::post`.
- **POS**: `snCari` autocomplete (partial `sn-control.blade.php`), gate `validasiSnKeranjang` di `openPaymentModal`, `klaimJual` **di dalam** `processTransaction` `DB::transaction` (kegagalan SN → rollback penjualan utuh); `render()` passing computed props eksplisit.
- **Servis**: `klaimServis` di `inputPekerjaan` (rollback stok bila throw), `lepasServis` di `onSelesai` (kembali `tersedia`, link riwayat dipertahankan); input SN per baris part di `ServisBoard`.
- **Trace laporan garansi**: `LaporanNomorSeri` Livewire + view Ute Prism (DataTable/StatusPill, pencarian SN, riwayat GRN → penjualan → servis → garansi), route `GET /app/laporan/nomor-seri` (permission `laporan.cabang` yang sudah ada — tanpa ubah seeder), cabang-scoped.
- Deviasi tercatat: tidak ada generic "stok masuk lain GRN" untuk di-hook (opname=korreksi, transfer=antar gudang, import=master-only); kolom baru di SQLite tanpa FK constraint (di-index saja).
- Verifikasi (orkestrator, 2026-09-23): **SerialNumberTest 7/7 (81 asersi)** — GRN auto+approval, count≠qty & dobel reject, POS 3 mode reject + terjual link, servis → kembali tersedia + garansi, laporan trace + scoping; regresi **GrnTest 7, ServisStateMachine 6, ApprovalEngine 20, SemuaHalaman 9, IntegrasiModul 2, Ppn 13** — semua hijau; `pint --test` **PASS 13 file**. Implementasi lane fix-13 (model mimo) setelah 3 percobaan model lain gagal (2 collapse, 1 batal).

## 2026-09-23 — PRD-Advanced Fase 2 (F2-2 GRN)

- **F2-2 GRN (G-07)**: flow PO dikirim → GRN wajib. `GrnService` — validasi **qty diterima ≤ qty PO** per item; qty sesuai → finalisasi otomatis: jurnal AP (`130-01` debit / `210-01` kredit via `JurnalService::post`, idempoten per `no_grn`) + stok masuk pola kanonik TransferTab (`StokItem.firstOrCreate` + `StokLog` **jenis `GRN`** dengan sebelum/setelah + `StockMutationLog` sumber `grn`); partial/tolak → status **`draft`** + `ApprovalService::ajukan('grn', ...)` (rule global `entity_type 'grn'`, min 0, admin-toko — seed idempoten di migrasi `create_grn_table`). Hook **`ApprovalService::selesaikanEntity`**: rantai final disetujui → `setujuiGrn`, ditolak → `tolakGrn` — keduanya **hanya transisi dari `draft`** (approve ganda tab+inbox idempoten, tidak dobel jurnal/stok). Model `Grn` + activity log F1-4 (`CatatAktivitas`/`LogsActivity`, `opsilogAktivitas('GRN')`), data per-item di JSON `item_qty_received`. Livewire **`GrnTab`** + view Ute Prism (filter status, kartu PO siap diterima dengan tombol Input GRN, tabel GRN + pill status, modal input qty per item, konfirmasi setujui gate `@can('approve-workflow')`, tolak via prompt alasan, riwayat F1-4) — di-wire sebagai **tab ke-6 `WmsDashboard`** (nav + mount pola tab lama). Peta `RiwayatAktivitas::TIPE` + `grn`.
- **Bug yang diperbaiki saat penyelesaian F2-2** (lane fix-7 berhenti sebelum finishing, lane lanjutan no-op — diselesaikan orkestrator langsung): `GrnTab` parse-fatal (`public int $confirmGrnId = null`, default property `auth()->id()`), import `Cabang` hilang sementara `render()` memanggilnya, validasi `grnForm.item_qty.*` salah tipe (elemen array), `array_column` tanpa key `produk_id`, helper `item()` tidak ada, kolom `$po->gudangTujuan_id` salah → `gudang_tujuan_id`, `generateNoJurnal` dipanggil static (method instance), stok qty **tidak pernah ditambah** (kini StokItem + StokLog + StockMutationLog), `stok_log.jumlah_sebelum` NOT NULL diisi null, relasi `$grn->po` tidak ada → `purchaseOrder`, status pending `disetujui` → `draft`, `Grn` fillable `cabang_id`/`catatan` + import `App\Models\User`, view/wiring/`GrnTest` tidak dibuat, `GrnItem` (model yatim tanpa tabel) dihapus.
- Verifikasi (orkestrator, 2026-09-23): **`GrnTest` 7/7 (38 asersi)** — auto-terima + jurnal balance + stok masuk, partial → approval request (admin-toko, cabang-scoped), tolak semua amount 0 tetap diajukan, **approve via engine F1-1 → hook → jurnal+stok + idempoten ganda**, reject → `ditolak` tanpa jurnal, validasi ≤ PO, UI smoke modal; regresi **ApprovalEngine 20, JurnalService 4, Reorder 10, WmsSplit 7, SemuaHalaman 9, DrillDown 16, LeadConversion 8** — semua hijau; `pint --dirty` **PASS 21 file**.

## 2026-09-23 — PRD-Advanced Fase 2 (F2-4 BI Drill-down + Custom Report Builder)

- **F2-4 BI Drill-down + Report Builder (G-09)**: modul baru `app/Modules/Report/` — migration idempoten `saved_reports` (scoped `user_id`+`cabang_id`), model `SavedReport`, `ReportBuilderService` (whitelist ketat 8 model dasar + 4 sub-model — tolak input user di luar whitelist, tanpa instansiasi class sembarang; query builder cabang-scoped), `ReportExportJob` (`ShouldQueue`, xlsx/csv — tidak pernah sync di request), `DrillDownController` (JSON API drill-down chain → transaksi/item), Livewire `ReportBuilder` + `DrillDownViewer` + view Ute Prism (GlassCard/DataTable/tabular-nums/ribuan titik), `ReportSeeder` (laporan contoh), permission `laporan.kustom` (seeder, pola `atur-harga-fleksibel`), route `/laporan` + `/laporan/drill/{model}/{id?}`; widget dashboard: tambah link drill-down di 3 widget (`DashboardIndex::getDrillDownUrl`/`getDrillDownModels`) tanpa restrukturisasi logika widget lama.
- **Fix selama F2-4**: import `ReportBuilder.php` salah (`Illuminate\Foundation\Livewire\Component` → `Livewire\Component`) — sempat mematikan route boot sehingga semua `php artisan test` gagal; diperbaiki, diverifikasi ulang.
- Verifikasi (orkestrator, 2026-09-23): `DrillDownTest` 16/16 (43 asersi — whitelist rejection, scoping, saved report CRUD, export ter-queue bukan sync), `SemuaHalamanTest` 9/9, regresi `LeadConversionTest` 8/8; Pint bersih untuk semua file tersentuh. Catatan: `pint --test` repo-wide masih menunjuk 32 file pre-existing (Sid* command, migrasi lama) — di-defer ke pass repo-wide setelah F2-2 selesai agar tidak menabrak file yang sedang diedit.

## 2026-09-22 — PRD-Advanced Fase 2 (F2-1 Lead Pipeline)

- **F2-1 Lead Pipeline CRM (G-06)**: migration idempoten `leads` (FK `cabang`/`users`/`pelanggan` singular, index `[cabang_id, stage]` dll), model `Lead` (stage enum baru/kontak/kualifikasi/negosiasi/won/lost, sumber 7 jenis, `scopeForCabang`, label/color accessor, `canConvert`), `LeadService` (create/update + validasi, `handleStageTransition` → `won_at`/`lost_at` + notif via queue (tidak pernah sync), `convertToPelanggan` (PelangganService::create dalam transaksi), `getKanbanData`, `getFunnelData`, `bulkUpdateStage`), endpoint API **CRM-10..18** di `CrmController` (index/store/show/update/destroy/bulk-stage/convert/kanban/funnel) dengan `permission:crm.*` — urutan route static sebelum `/leads/{id}` (fix shadowing), permission **`crm.delete`** ditambahkan ke seeder (master + marketing). Livewire **`LeadKanban`** (`/app/crm/leads`, `permission:crm.view`) + blade Ute Prism: 6 kolom, drag-drop HTML/Alpine → `updateStage()` satu langkah (side-effect lewat LeadService), summary + mini funnel, filter search/sumber/sales, modal create/edit, konfirmasi konversi→Pelanggan & hapus; copy Bahasa Indonesia, `tabular-nums`. Test **`LeadConversionTest` 8/8 hijau** (51 asersi: CRUD, won/lost timestamp, konversi, bulk, kanban/funnel scoping, cabang isolation, stage invalid 422), `Queue::fake()`.
- **Bug production ditemukan & diperbaiki selama F2-1**: (1) `LeadService::rules()` `exists:cabangs,id` → `cabang` (tabel singular — semua POST/PUT lead 500); (2) `Lead`/`LeadKanban` import `App\Modules\Rbac\Models\User` tidak ada → `App\Models\User`; (3) `ExportLaporanService` import `App\Modules\Crm\Models\Transaksi` tidak ada → `App\Modules\Pos\Models\Transaksi` (rekapan PPN pernah 500); (4) `KonfigurasiService` duplikat blok `seedPpnDefaults` di luar class → parse error (pre-existing fatal saat import); (5) `PpnTest` tidak ter-discover di PHPUnit 12 (`@test` docblock dihapus) → rename `test_*` + `RefreshDatabase` + ganti `Transaksi::factory()` (factory tidak ada) dengan `Transaksi::create` + helper `createCabang` — **PpnTest 9/9 hijau** (sebelumnya 0 jalan).
- Verifikasi: `LeadConversionTest` 8/8, `PpnTest` 9/9, regresi `ApprovalEngineTest` 20/20, `JurnalServiceTest` 4/4, `ResellerFase5Test` 2/2; `migrate` sukses di `db_staging`; Pint bersih semua file tersentuh.

## 2026-09-22 — PRD-Advanced Fase 1 (F1-1, F1-2, F1-3, F1-4, F1-5, F1-6, F1-7, F1-8)

- **F1-5 Reorder Otomatis**: migration idempotent enum `status` PO + `usulan` (MySQL MODIFY / SQLite rebuild; `down()` tolak bila masih ada baris usulan); `ReorderService` — query stok `jumlah < jumlah_minimum` (min>0, produk aktif), anti-duplikat (skip bila produk sudah ada di PO draft/usulan), resolusi supplier terakhir (PO item terbaru → fallback terfrequency), grouping per supplier+gudang, buat PO usulan (`PO-Ymd-NNN`, qty = minimum−stok, termin supplier), notifikasi queue `NotificationService::kirim('inapp', …)` Bahasa Indonesia; `ReorderOtomatisJob` (`ShouldQueue`) schedule `dailyAt('03:00')` di `bootstrap/app.php` (3 entri lama intact); observer `PurchaseOrderObserver` skip status `dibatalkan`/`usulan` (usulan tak auto-fire approval F1-1); `PoTab::konfirmasiUsulan` — validasi status, set `draft`, `ApprovalService::ajukan` idempotent (hanya ≥ threshold); tombol Konfirmasi warna `bg-up-accent`, StatusPill `usulan` amber; ruang riwayat F1-4 di PoTab dipertahankan. `ReorderTest` 10/10 (47 asersi), `WmsDashboardSplitTest` 7/7, `ApprovalEngineTest` 20/20, `schedule:list` 4 entri, Pint 7 file bersih.
- **Fix bug production pre-existing (final battery Fase 1)**: `ImportProdukService::konteksDuplikat` mem-build peta SKU/barcode file-penuh lalu `validateRow` cek `isset` — baris apa pun selalu dianggap duplikat diri sendiri → **preview selalu `valid=0`, import produk 100% rusak di HEAD** (pre-existing, tersembunyi karena test selalu gagal lebih awal). Perbaiki jadi akumulasi per-baris **setelah** validasi (`catatKonteks`, `use &$konteks` di commit) — duplikat in-file hanya terdeteksi pada kemunculan berikutnya; kemunculan pertama sah kecuali sudah ada di DB. Test `WmsImportVerifyTest` ikut diperbaiki: baris invalid dipadding `array_fill_keys($kolom)` (CSV maatwebsite positional — baris sparse bergeser kolom), `assertSame` float expected, run-1 commit via `ImportProdukExcelJob` (sync) karena notifikasi hanya dari job + salinan file utk run-2 (job unlink), `ImportLog` status `proses` (enum CHECK). **`WmsImportVerifyTest` 1/1 (40 asersi) hijau — pertama kali di box ini.**

- **F1-4 Audit Trail** (`spatie/laravel-activitylog` ^5.1): model model kritis — Transaksi, JurnalAkuntansi, Piutang, Utang, StokItem, PurchaseOrder, Produk — via trait `CatatAktivitas` (logAll + logOnlyDirty, create/delete full snapshot, update dirty only); kolom `cabang_id` terindeks di-resolve via row/gudang (`AktivitasCabang`), causer auth → `"sistem"` utk job/seed; `default_except_attributes` blokir token/secret/2FA fields. UI reusable `RiwayatAktivitas` (filter pengguna/aksi/tanggal, manual pagination anti-bentrok WithPagination, komputasi eksplisit di render) + modal partial di-embed di 5 view (akunting dashboard, produk-tab, po-tab, stok-tab, pos-kasir receipt — ruang PoTab ditinggalkan utk F1-5); RBAC `lihat-audit-log` idempotent (super-admin/finance/admin-toko) + `@can` di tiap tombol. `ActivityLogTest` 7/7 (110 asersi), regresi `PpnTest` 13/13 + `ApprovalEngineTest` 20/20 + `JurnalServiceTest` 4/4 hijau, `SemuaHalamanTest` 9/9 (embed view tercompile), Pint 22 file bersih. Gap: halaman global audit-log full-page (terpisah bila dibutuhkan), tombol riwayat mobile-card StokTab belum ada.

- **F1-3 2FA TOTP** (`pragmarx/google2fa` ^9.1): migration idempotent 3 kolom users (`two_factor_secret` encrypted, `two_factor_confirmed_at`, `two_factor_backup_codes` bcrypt JSON 8x consume-on-use); login challenge sebelum session grant (pending-id, RateLimiter 5×60s, TOTP → fallback backup code, pesan Indonesia); setup UI `/app/keamanan/dua-faktor` (secret + otpauth manual entry, confirm-to-activate, flash 8 kode sekali, regen/disable dgn password) + amber banner force-setup utk super-admin/admin-toko/finance TANPA lock-out (kebijakan MVP: challenge hanya saat 2FA aktif, utk semua role); event ubah kunci → `NotificationService` queue + AuditService. `TwoFactorTest` 18/18 (107 asersi), `LoginFlowTest` 4/4, `ApprovalEngineTest` 20/20, Pint bersih, double-migrate idempotent, 6 route `two-factor.*` terdaftar. QR image di-utamakan manual entry (MVP).

- **F1-2 Pajak Otomatis** (PajakService rewrite): config per-cabang via kunci `pajak_enabled.{cabang}`/`ppn_percent.{cabang}` (default 11, fallback key lama `ppn_enabled.*` backward-compat, opt-in default false); migration idempotent `ppn_nominal`+`dpp` di transaksi + reconcile COA (`220-01` PPN Keluaran seeder idempotent); `hitung(?cabangId, dpp)` → PosKasir wiring (dpp = subtotal − diskon, tampil `pajakNominal`/`ppnPersen`); baris jurnal via `JurnalService` (220-01 kredit, balance teruji); laporan pajak bulanan Livewire `LaporanPajak` + route `/app/laporan-pajak` + rekap export (`ExportLaporanService`). Fix pre-existing di scope: `KonfigurasiService` blok duplikat `seedPpnDefaults` (ParseError HEAD), `PpnTest` → 13 `test_*` methods (PHPUnit 12). Verifikasi: `PpnTest` 13/13 (51 asersi), `JurnalServiceTest` 4/4, `ApprovalEngineTest` 20/20, Pint bersih (3 file style Pos pre-existing ikut dibersihkan; `PricingServiceTest`/`HargaFleksibelTest` tetap hijau).

- **F1-8 Split WmsDashboard** (S-01): `WmsDashboard.php` 1043 → 21 baris (shell tab host) + 5 komponen fokus `StokTab`/`TransferTab`/`OpnameTab`/`PoTab`/`ProdukTab` + blade per-tab — ekstraksi verbatim (audit independen: byte-identical vs HEAD utk semua segmen, 7 pasang dispatch event `#[On]]` cocok, 37 method/properti original terakomodasi); children selalu mounted (toggle `hidden`) → state lintas tab persisten = paritas perilaku. `WmsDashboardSplitTest` 7/7 hijau, `SemuaHalamanTest` 9/9 (termasuk `/app/wms`), Pint bersih. Bug pre-existing beresin di reconcile: `WmsImportVerifyTest` — return type `collection(): Enumerable` (maatwebsite/excel 4.0.3) + path baca disk `local` (`storage/app/private`); chown `storage/app/private/{prints,import-tmp}` → www-data (was root:700). Catatan risiko diterima: pagination stok/produk terpisah per tab (URL `?page=` shared — kosmetik), first paint mount 5 children (biaya query awal ~setara dgn render lama tanpa guard).

- **F1-1 Approval Engine** (module Workflow — verifikasi + gap): rule engine cabang-scoped (`cabang_id = X OR NULL`), amount range null-safe, multi-level (`lanjutLevelBerikut` rekursif, reject hentikan rantai), idempotent 1-request-per-(entity,rule); auto-fire via 4 Eloquent observer terdaftar di `AppServiceProvider` — PO ≥10jt (finance L1), Retur Penjualan/Pembelian ≥5jt (super-admin), diskon transaksi ≥1jt (admin-toko; tanpa edit PosKasir); observer try/catch (gagal approve tidak merusak save) + skip `is_migrasi_sid`; inbox `/app/approvals` RBAC `permission:approve-workflow` (kasir 403), filter cabang+role, badge pending count di sidebar; notif via `NotificationService` + `KirimNotifikasiJob` queue (tidak pernah sync). Permission `approve-workflow` idempotent (migration 100010 + seeder, pola `atur-harga-fleksibel`), granted super-admin/finance/admin-toko. `ApprovalEngineTest` 20/20 (74 asersi) hijau; Pint bersih. **Catatan**: approval bersifat audit-only (observer post-save — pending tidak memblokir persistensi dokumen); blocking flow menyusul bila dibutuhkan (mis. F2-2 GRN).

- **F1-6 Backup** (`ute:backup`): mysqldump gzip → `storage/app/backups/` (stream 8KB RAM-safe, kredensial via env `MYSQL_PWD` — password tidak di argv), rotasi 7 hari idempoten (`--keep-days`), schedule `dailyAt('02:00')` di `bootstrap/app.php` withSchedule, notif hasil via `NotificationService` + `Queue::later` (tidak pernah sync). Verifikasi: run manual sukses — dump 6,4 MB `db_staging` valid (gzip -t OK); `BackupCommandTest` 5/5 hijau; cron `schedule:run` dipasang di box staging (crontab sebelumnya kosong); `storage/app/backups` chown www-data.
- **F1-7 Ops config**: `deploy/supervisor/ute-parts-queue.conf` (numprocs=1 sesuai RAM 1GB; ⚠️ live supervisor masih 2 proses `_00/_01` — rekonsiliasi saat implement deploy), `deploy/logrotate/ute-parts.conf` (harian, rotate 7, copytruncate), route `GET /healthz` cek db + queue (output hanya boolean, tanpa data tenant) terdaftar via `withRouting(then:)` tanpa menyentuh `routes/web.php`; `/up` tetap. `HealthzTest` 3/3 hijau; `curl /healthz` → 200 `{"db":true,"queue":true}`; Pint bersih.

## 2026-09-22 — Migrasi SID Fase 3 penutup (C-05..C-08) + Verifikasi V-01..V-05

- **C-05 stok_log rekonstruksi** (SidRekonstruksiStok): 19.286 arus_stok → 19.338 stok_log (1:1 + 52 split masuk&keluar), tanggal_log historis 02-14..08-23, jenis penjualan 11.805/masuk 2.982/pembelian 1.270/koreksi 3.281, gudang TOKO→6/GUDANG→5, referensi_id 13.060 transaksi valid, dummy [ARSIP-SID] 123.
- **C-06 stok_items**: wipe 3.044 artefak → rebuild 4.794 (last-sisa arus 2.789 + fallback payload toko/gudang 2.005), deviasi arus-vs-log 0, negatif 0.
- **C-08 jurnal pembuka** (SidJurnalPembuka): SID-OPENING 4 baris COMMIT 2026-09-22 04:11, balance 1.195.932.217,50 (Kas 99.818.660 + Piutang 3.001.000 + Persediaan 1.093.112.557,50 vs Modal), no_jurnal deterministik idempotent.
- **C-07/koreksi metode_bayar**: seluruh header visa=UANG PAS → 6.262 transaksi UPDATE massal kartu→tunai (orchestrator).
- **V-01..V-05**: deviasi item-vs-total 325/6.262 (katalog, dipertahankan sengaja); saldo SOT cocok; stok konsisten; sid_import_map 0 orphan; testing 10/11 file lulus.
- **BONUS fix migrasi lama** `2026_09_21_000042` (MySQL-only join()->update()) → correlated subquery portabel SQLite: 29 gagal → hijau.
- **Catatan lingkungan**: WmsImportVerifyTest (Excel import) fatal OOM di server cgroup 955MB (SIGKILL, bebas ±115MB; stack opencode 400MB) — bukan kegagalan logika; belum pernah hijau di box ini (sebelumnya 29 gagal could not find driver; pdo_sqlite diinstall 2026-09-22). Rerun di env dgn RAM cukup utk hijau penuh.

- **Audit sinkronisasi 4 domain (produk/stok/transaksi/konsumen)**: ALL PASS — Σ total transaksi 765.411.399 = Σ header SID; 0 orphan FK lintas modul; 0 dup no_transaksi; 1.646 produk hjr=0 = sumber SID 0 (bukan misroute); 71 barang JASA = price-list servis dormant (dijual via modul Servis).

- **Command wrapper `sid:migrate-full`** (pipeline M→A→B→C→D+J satu perintah, --dry-run/--only/--step/--commit-jurnal) + dokumentasi §6 MIGRASI-SID.md: cara mulai dari bersih (migrate:fresh + import-raw + migrate-full) & migrasi langsung oleh user. Temuan harga: 1.342 produk SID hjr=0 = sumber SID 0 (0 produk punya harga terlewat; 1.331 flag nol_price_diskon=true) — bukan bug mapping.

- **Fitur Harga Jual Fleksibel** (2026-09-22): kolom `produk.harga_fleksibel`, permission `atur-harga-fleksibel` (super-admin), `HargaFleksibelService` guard ≥ HPP, PosKasir integrasi (kasir diblokir, superadmin modal input), WmsDashboard toggle, command `sid:map-jasa-produk` map 71 JASA SID → `JenisServis` (SOT) + 1.342 produk harga 0 flag fleksibel, 7 unit test hijau. Akuntansi aman: `transaksi_item.harga_satuan` + `hpp` existing → margin akurat tanpa ubah jurnal.

## 2026-09-22 — Migrasi SID Fase 4 (D-01/D-02) + A-03 merge pelanggan

- **D-01 tiket_servis backfill** (`sid:backfill-servis`): 332/332 tiket SID di-UPDATE (0 insert), `is_migrasi_sid=332`, status → state machine app (diambil 290 / selesai 26 / dikerjakan 14 / ditolak 2; 42 baris legacy `menunggu`/`dalam_pengerjaan`/`dibatalkan` dikoreksi; 0 invalid). jenis_hp=`type` SID, keluhan=kerusakan+2/3, estimasi_biaya=`jumlah`, kondisi_fisik json, sid_detail + pembayaran json, teknisi, tanggal_selesai/diambil dari tanggal_kembali+jamkembali (327), nama/telepon dari pelanggan via kode_lama (unmapped 0).
- **D-01 servis_status_log snapshot**: 332 baris (1/tiket, status_ke=status akhir, alasan `Migrasi SID snapshot status akhir`, created_at=tanggal_terima). 2× run = 0 update/0 log.
- **D-02 tiket_servis_item** (`sid:backfill-servis`): 671/671 (part 348 / jasa 323), produk_id by kode_lama (0 unmapped), sku null 336. 2× run = 0 insert (guard per tiket).
- **A-03 merge dedup pelanggan** (`sid:merge-pelanggan-dup`): 10 grup/23 baris → 13 dup dihapus (canonical = join tertua/tiebreak kode terkecil: 0012/0116/0001/0156/0158/0006/0064/0009/0014/0043); FK remap 9 (tiket_servis) + sid_import_map 13; pelanggan 292→279 (kode_lama 271); 0 FK sisa ke id dup. 2× run = 0 aksi.
- Laporan: `storage/app/migrasi-sid/D-servis-laporan-*.md`, `A03-merge-pelanggan-laporan-*.md`. Command baru: `SidBackfillServis.php`, `SidMergePelangganDup.php` (chunked, RAM 1GB aman).

**2026-09-22 — Migrasi SID Fase 3 penutup**: jurnal pembuka SID-OPENING diposting (4 baris, balance 1.195.932.217,50; Kas 99.818.660 + Piutang 3.001.000 + Persediaan 1.093.112.557,50 vs Modal 1.195.932.217,50), command `sid:jurnal-pembuka` (dry-run/commit).
## 2026-09-22

### Migrasi SID Retail (MIGRASI-SID.md) — Fase 3 C-05..C-06 (rekonstruksi stok)
- **C-05 stok_log 1:1 dari arus_stok** (`sid:rekonstruksi-stok --only=log`): 19.286 arus → 19.338 stok_log (19.286 + 52 split baris masuk&keluar>0 → 2 baris `|M`/`|K` urut seb/per/ses benar). tanggal_log = tanggal+jam SID (created_at=historis), jenis mapping (penjualan 11.805 / masuk 2.982 / pembelian 1.270 / koreksi 3.281), gudang TOKO→6 & GUDANG→5 dari lokasi_stok, referensi Transaksi 13.060 (referensi_id diisi), nilai_awal/masuk/keluar/sisa + jumlah_awal, dummy `[ARSIP-SID]` 123 baru, is_migrasi_sid. Idempotent: 2× run = 0 insert (guard `AS:<kode_sumber>` — payload kode tidak unik, deviasi terdokumentasi).
- **C-06 stok_items saldo** (`sid:rekonstruksi-stok --only=saldo`): sumber arus last-sisa per (kode,gudang). Wipe 3.044 artefak → rebuild 4.794 = arus 2.789 (skip 603 sisa 0 / 68 negatif) + fallback payload 2.005 (toko→gudang6, gudang→gudang5, >0 saja). Rekonsiliasi: arus-last vs stok_log rekonstruksi mismatch 0; vs payload deviasi >5 = 0 (2.781 dicek). jumlah_minimum 0 (stok_warning A-02 = 0 semua). Produk tanpa stok_items 1.921 (saldo 0 implisit), stok_items negatif 0. Deterministik: rerun = wipe+rebuild identik.
- Command baru `app/Console/Commands/SidRekonstruksiStok.php` (chunked 500, RAM aman utk server 1GB). Laporan: `storage/app/migrasi-sid/C2-stok-laporan-*.md`. Analisa awal (label dist, pola masuk/keluar, lokasi, distinct kode_barang 3.341) ada di laporan.

### Migrasi SID Retail (MIGRASI-SID.md) — Fase 3 C-02..C-04 (rekonstruksi transaksi)
- **C-02 Cleanup artefak** (`sid:rekonstruksi-transaksi --only=cleanup`): hapus transaksi_item 28.491, transaksi R43-* 18.786, sid_import_map stale (entity_type='transaksi') 6.262, stok_log 120.167 (guard max created_at aman). harga_tier dipertahankan (19.104 = 6.368 produk × 3 tier — bukan artefak). Operasional MP-20260922-0001 utuh.
- **C-03 Rekonstruksi transaksi** dari header `penjualan` staging: 6.262 transaksi (no_transaksi=faktur SID, tanggal_transaksi historis 2026-02-14..2026-08-23, gudang TOKO1, status lunas 6.261/1 belum_lunas, sid_detail, is_migrasi_sid). ⚠️ deviasi: visa='UANG PAS' semua → metode_bayar='kartu'.
- **C-04 transaksi_item**: 10.273 item, dummy produk `[ARSIP-SID]` 175 baru (total 181 incl 6 B-02), sku null 776, rekonsiliasi deviasi 330/6.262 faktur (katalog laporan).
- Command baru `app/Console/Commands/SidRekonstruksiTransaksi.php` (idempotent: 2× run = 0 insert/0 delete; guard cleanup = R43-* tanpa tanggal_transaksi). Laporan: `storage/app/migrasi-sid/C1-rekonstruksi-laporan-*.md`.

### Migrasi SID Retail (MIGRASI-SID.md) — Fase 0 & Fase 1
- **Fase 0 (analisis & rencana)** — `MIGRASI-SID.md` dibuat: doktrin SOT (SID = sumber kebenaran), inventory 212 tabel SID, matriks mapping vs skema app, aturan pembersihan data, workflow fase M/A/B/C/D/V ber-checkbox.
- **Temuan audit A-01**: transaksi app 18.786 = artefak migrasi lama (1 transaksi per item, tanggal = waktu import 26 menit, bukan historis); stok_log 120.167 ≈ 6,2× arus_stok; harga_tier ~19k ≈ arus_stok (dugaan artefak); dup kode barang = 0; pelanggan dup 9 grup; orphan itempenjualan 178 kode.
- **Fase 1 skema** — migrasi `2026_09_22_000100_sid_migrasi_skema_fase1.php` (idempotent): kolom SID backfill di produk/sku_variants/pelanggan/supplier/transaksi/transaksi_item/stok_log/tiket_servis/kas_sesi/piutang/utang/purchase_order(+_item), tabel baru `master_kas`, `return_penjualan(+_item)`, `return_pembelian(+_item)`, `nomor_seri_produk`, `komplain_pelanggan`, flag `is_migrasi_sid` (cleanup aman).

## 2026-09-21

### Fase 11 — T-42 (fase 1: staging RAW + dokumentasi migrasi SID Retail)
- **Migration** `2026_09_21_000045_create_sid_retail_raw_tables.php` — 8 tabel staging
  (`sid_retail_raw_barang|pelanggan|supplier|member|servis|itempenjualan|arus_stok|setup_perusahaan`)
  berisi `kode_sumber` (unique) + `tabel_sumber` (nullable, diisi utk partisi arus_stok) +
  `payload_json` (nilai asli dump) + `payload_normal` (ternormalisasi) + `diimport_at` (diisi fase 2) + timestamps.
  (Dinomer-ulang 000043→000045 karena 000043 sudah dipakai task lain `create_brand_kualitas_tipe_hp_tables`.)
- **Importer** `app/Console/Commands/SidImportRaw.php` (registered via `bootstrap/app.php`, tanpa schedule):
  parse TEKS dump `/root/workspace/ute-pos-main/latest.sql` (20.8 MB, separator statement `\r\r` BUKAN `\n` —
  statement bisa satu "garis fisik" dengan CREATE/ALTER → marker INSERT dicari di mana pun + boundary-check
  huruf sebelumnya `\r`/`\n`/`;`; 1146/1146 statement terverifikasi). Streaming buffer ~1 MB + insert chunk 500;
  3 pass idempoten utk 8 tabel inti; normalisasi `payload_normal`: `True/False`→bool, `1899-12-30`/`0000-00-00`→null,
  jam 12-jam→24-jam, string numerik→int/float (tipe kolom dari CREATE TABLE dump); anti-korupsi: count field
  mismatch → statement berhenti aman. Fix 4 bug pra-jalan (handle tidak panggil streamStatements; statementEnd
  return null pd baris lanjutan `,`; columnType cache kosong; flushPending pakai nama tabel tanpa prefix).
- **Verifikasi staging db_staging PASS:** migrate DONE; import 3x → run-1 35.402, run-2 +1.221 fix separator,
  run-3 **insert=0 / skip=36.623** (idempoten, tidak dobel); total per tabel PERSIS baseline parser Python
  (barang 6.433, pelanggan 284, supplier 10, member 4, servis 332, setup_perusahaan 1, itempenjualan 10.273,
  arus_stok 19.286 dari partisi 2..8_2026); `kode_sumber` duplikat = 0 semua tabel; `--dry-run` OK (35.402→fix
  →36.623 dibaca, 0 tulis); `php -l` 3 file clean; peak memory ~52 MB.
- **Docs** `MIGRATION_SID_RETAIL.md` — mapping rekomendasi fase 2 (barang→produk, pelanggan→pelanggan,
  supplier→supplier, servis→tiket_servis, arus_stok→stok_log, member→pelanggan, setup_perusahaan→konfigurasi,
  itempenjualan→penjualan_item), kolom SID tanpa padanan, transformasi tipe §4, relasi terdenormalisasi §6,
  urutan import + dry-run §7, tabel log-only §8 (212 tabel SID vs ≈66 tabel Ute Parts).
- Fase 2 (mapping ke skema Ute Parts) **TIDAK disentuh** — menunggu skema produk T-44.

### Fase 11 — T-38, T-39, T-45, T-46 (Frontend Responsif & UX)
- **[T-39] Logout sidebar via web session, bukan JSON SPA**
  - Route baru `POST /logout` (web, auth middleware) → `Auth\AuthenticatedSessionController@destroy` (Breeze-style) — dibuatkan `app/Http/Controllers/Auth/AuthenticatedSessionController.php` (sebelumnya tidak ada).
  - Sidebar (`resources/views/layouts/backoffice.blade.php:194`) ganti action dari `/api/logout` ke `route('logout')` + CSRF → redirect penuh ke `/app/login`; hilang pesan JSON "Unauthenticated.".
  - Verifikasi: `artisan route:list --path=logout` → `POST logout › Auth\AuthenticatedSessionController@destroy` ✓.
- **[T-45] Buka Kas saat login — separator + submit feedback**
  - Akar masalah "submit tidak ada respon": event `$dispatch('alert', …)` dari Livewire **tidak punya listener** → toast tak pernah muncul. Ditambahkan listener global di `resources/js/app.js` (`livewire:init` → `Livewire.on('alert', …)` → `showToast`) + `--animate-slide-up` + keyframes di `@theme` app.css (sebelumnya kelas `animate-slide-up` tidak terdefinisi).
  - `PosKasir::prosesKas()`: validasi Livewire `required|numeric|min:0` per mode + helper `normalizeNominal()` (pengaman ganda separator ribuan ID "1.000.000" → 1000000, desimal koma→titik; `toRaw()` di `format-number.js` tetap jalur utama angka bersih). Sukses buka → modal tertutup + toast "Kas berhasil dibuka…"; gagal → toast error + modal tetap terbuka. `bukaKasModal/tutupKasModal` `resetValidation`.
  - Blade modal kas: autofocus + select input nominal (`x-ref` + `x-init`), `Enter` = submit (`@keydown.enter.prevent="$wire.prosesKas()"`), pesan `@error` inline merah. Perilaku transaksi non-tunai tidak disentuh.
- **[T-46] Sidebar hilang di PC/tablet**
  - Penyebab: `x-show="sidebarOpen"` (display:none saat false) menimpa kelas `lg:translate-x-0` → sidebar disembunyikan di SEMUA viewport saat load.
  - Ulang jadi 3 kondisi: `<768px` drawer slide-in + backdrop ganti jadi `md:hidden`; `768–1023px` kolapsibel icon-rail (`sidebar-collapsed`, toggle via hamburger, state `ute-sidebar-md-collapsed` di localStorage, CSS scoped `@media 768–1023.9px` di prism-tokens.css: sembunyikan label/badge/user-meta, nav ikon di tengah, footer user kolom); `>=1024px` selalu tampil penuh (md:static md:translate-x-0 + transition lebarnya).
  - z-index: sidebar z-50 (mobile) / md:z-30 > header z-20 > konten; konten mengikuti lebar flex (tanpa geser/tertutup); `overflow-x-clip` di body; tombol hamburger `p-2.5` (≥44px) toggle drawer/collapse sesuai lebar viewport; nav link `min-height:44px`.
- **[T-38] Responsif global & performa (layout + komponen bersama)**
  - Eliminasi horizontal scroll 320px: layout marketplace — brand text `hidden xs:block`, link Katalog/Masuk/Keluar compact + ikon di <sm, `min-h-11` (44px) semua kontrol header, `gap` lebih rapat, kontainer `px-3 sm:px-6`.
  - Font: `display=swap` pada Bunny CDN di backoffice, marketplace, login.
  - CLS: `DataTable` `min-h-24` + mode desktop `min-w-[480px]` dalam wrapper `overflow-x-auto` (scroll internal, tidak menggembung halaman).
  - Touch target: hamburger, ADD POS (`px-3 py-2`), semua nav marketplace `min-h-11`, sidebar nav `min-h-11`.
  - Cek performa: debounce search POS/customer `wire:model.live.debounce.250ms` sudah ada (barcode-scan-input:16, customer-picker:15); tidak ada listener scroll non-passive di JS shared; img di scope shared tidak ada (lazy loading perlu di blade modul lain — lihat laporan lintas modul).
  - **Laporan lintas modul (jangan edit lane lain)** — detail di laporan akhir sesi: `servis-board.blade.php` kanban w-[280px] horizontal scroll (intentional), img `:66/:297/:486` tanpa `loading="lazy"`; `shop-catalog.blade.php:131`, `shop-detail.blade.php:19` img tanpa lazy; beberapa grid `grid-cols-2/3` tanpa breakpoint di crm/rbac (bisa sesak di 320px).
  - Verifikasi: `php -l` 3 file PHP clean; `npm run build` SUCCESS (app CSS 118.46 kB, JS 12.37 kB, PWA precache 10 entries).

### Bug Fix Dashboard — T-47 (Unknown column 'sisa' di piutang)
- **Bug:** `/app/dashboard` error `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'sisa'` dari `select sum(sisa) from piutang where status != lunas` — query agregasi dashboard memakai kolom `sisa` yang belum ada di tabel.
- **Migration** `2026_09_21_000041_add_sisa_to_piutang_table` (single file): kolom `piutang.sisa` decimal(20,2) default 0 `after('jumlah_dibayar')` + backfill `CASE WHEN jumlah - jumlah_dibayar > 0 THEN ... ELSE 0 END` (ANSI — portabel MySQL & SQLite; `MAX(x, 0)` ditolak MySQL staging SQLSTATE 1064). Idempotent via `Schema::hasColumn` guard (DDL MySQL non-transaksional — run gagal sebelumnya meninggalkan kolom tanpa tercatat). Migrasi dijalankan di db_staging (`artisan migrate --force`) DONE.
- **Model** `Akunting/Models/Piutang`: accessor `getSisaAttribute()` (computed `max(0, jumlah - jumlah_dibayar)`) + hook `saving` menjaga kolom `sisa` konsisten di semua create/update. `Utang` sudah punya accessor serupa.
- **Query pemakai `sisa` dirapikan agar konsisten:** `DashboardIndex::getRingkasanKeuanganProperty` → `selectRaw('SUM(jumlah - jumlah_dibayar) as sisa')` (tak lagi menyentuh kolom); `chartPiutangAging` baca accessor `$p->sisa`.
- **Akurasi aging diperbaiki:** umur = hari TERLEWAT dari `jatuh_tempo` (`max(0, (int) $p->jatuh_tempo->diffInDays($now, false))`) — sebelumnya `now->diffInDays(...)` (absolut) salah memasukkan invoice yang belum jatuh tempo ke bucket 31-60/61-90; kini belum jatuh tempo → bucket 0-30. `diffInWholeDays` tidak dipakai (tidak ada di Carbon 3).
- **Verifikasi staging PASS:** migrasi DONE; `SUM(jumlah - jumlah_dibayar)`, `sum('sisa')`, accessor & kolom identik (1150000/2900000 uji); chart aging [0-30=600000, 31-60=150000, 61-90=300000, >90=400000] sesuai data uji 5 row (dibersihkan setelah uji); `php -l` semua file diubah clean.

### Fase 11 — T-40 & T-41 (Stok WMS: PO-only stok masuk + Transfer audit trail)
- **[T-40] Stok masuk wajib via PO Supplier; jalur manual "Tambah Stok" disembunyikan untuk non-super-admin**
  - `WmsDashboard` (`app/Modules/Wms/Livewire/WmsDashboard.php`): helper `isSuperAdmin()` (guard role `super-admin`); `openTambahStokModal`/`simpanTambahStok`/`simpanProduk` (`stok_awal`/`gudang` fields) menolak non-super-admin → toast error "Stok masuk hanya via PO Supplier". `ProdukService` TIDAK diblokir (tetap dipakai jalur PO & import T-43 nanti) — blokir hanya di lapisan UI + permission.
  - Blade (`wms-dashboard.blade.php`): tombol "+ Stok" di-gate `@if($isSuperAdmin)` + label "Stok masuk hanya via PO Supplier" untuk non-super-admin; info box kondisional; field "Stok Awal (Pembelian)" hanya tampil untuk super-admin.
  - PO = satu-satunya jalur stok masuk: `PurchaseOrderService::terimaBarang` → `ProdukService::tambahStokPembelian` dengan override `smlSumber='po:receive'` + `smlReferensiTipe/Id=PurchaseOrder`. PO kredit → record `Utang` + `PembayaranSupplier` parsial (jurnal Utang debit/Kas kredit); jurnal balance diverifikasi via `IntegrasiModulTest::test_po_kredit_diterima_dan_dibayar` + `test_tambah_produk_dengan_stok_awal_membuat_jurnal_pembelian` (2 passed, 49 assertions).
  - SML sumber jalur lain (pos/servis/marketplace/opname) TIDAK diubah — hanya jalur transfer & PO-receive yang diset sumber baru.
- **[T-41] Transfer antar gudang: audit trail + live stok info + validasi qty ≤ stok tersedia**
  - Migration `2026_09_21_000042_add_transfer_audit_columns` (single file): `stok_transfer.approved_by` (nullable FK users) + `approved_at` (nullable timestamp); `stok_transfer_item.created_by` (nullable FK users). `created_by` transfer = `user_pengirim_id`, `received_by` = `user_penerima_id` (kolom existing). **Runned di db_staging** (`artisan migrate --path=...` DONE; kolom terverifikasi via SHOW COLUMNS).
  - `StokTransfer`: `approver()` relasi + Fillable/casts diperluas; `StokTransferItem`: `created_by` di Fillable.
  - WmsDashboard & WmsController: `storeTransfer` validasi per-item qty ≤ `stok_tersedia` (`stok_fisik − pendingLockedByGudang`, pola T-13) → 422 dengan pesan jalur item; `kirimTransfer` `lockForUpdate` + guard pending-locked (allow draft sendiri) + set `approved_by`/`approved_at` + SML `transfer:out` (delta −qty); `terimaTransfer` SML `transfer:in` (delta +qty). `referensi_id` SML = `stok_transfer_id`.
  - UI transfer modal: form + Alpine `validasiStok()` (client) + server; select gudang/produk `wire:model.live`; kolom live "Stok Sumber / Stok Tersedia / Estimasi Stok Baru Tujuan" (`transferStokRows` di-pass eksplisit di `render()` — Livewire 4 WAIBS); error per-baris Alpine + Laravel; submit `@submit.prevent="validasiStok() && $wire.saveTransfer()"`. Daftar transfer menampilkan approver.
  - Routing: TIDAK ada route baru (PO `POST /api/wms/po`, `PUT /api/wms/po/{id}/status`, `POST /api/wms/po/{id}/bayar`, transfer `PUT /api/wms/transfer/{id}/kirim`, `PUT .../terima` sudah ada).
  - Verifikasi: `php -l` clean 7 file; `IntegrasiModulTest` 2 passed (49 assertions), `SemuaHalamanTest` 9 passed (43 assertions); verifikasi temp (T-41 flow draft→kirim→terima + T-40 guard, 28 assertions) PASS lalu file uji dihapus (di luar scope-write).

## 2026-09-20

### Gap Verifikasi Servis — T-17, T-18, T-20
- **[T-17] Form pekerjaan teknisi (part/jasa) di modal detail tiket**
  - Livewire `ServisBoard`: `pekerjaanItems` array, `addPekerjaanRow`/`removePekerjaanRow`/`simpanPekerjaan` → memanggil `ServisService::inputPekerjaan` (endpoint `POST /api/servis/{id}/pekerjaan` sudah ada SERVICE-06c).
  - Blade: grid row `tipe(part/jasa)` + `produk_id picker` (part) + `nama_item` + `qty` + `harga` + hapus baris.
  - Part: `kurangiStokServis` (lockForUpdate 1x) + `StokLog` + `StockMutationLog`; Jasa: hanya tagihan/jurnal (tidak sentuh stok).
  - **Guard anti-dobel stok**: `inputSparepart` (legacy, route `POST /api/servis/{id}/sparepart`) menolak bila sudah ada `TiketServisItem tipe=part` → error "Item sudah dicatat via form pekerjaan".
  - Jurnal `onSelesai` pakai items (Pendapatan Jasa 420-01 vs Pendapatan Penjualan part 410-01 + HPP 510-02/130-01) — fallback ke `ServisSparepart` legacy.
  - Unit test `test_pekerjaan_servis_split_part_jasa`: 15 assertions PASS (part -1 stok, jasa tanpa stok, jurnal balance).

- **[T-18] Quick-add Pelanggan Baru di form terima unit (modal ringkas)**
  - Ganti `openPelangganBaruServis` alert lama → modal (nama, telepon, alamat) submit ke `PelangganService::create` (CRM-06 single source).
  - Reuse `<x-customer-picker>` di form terima unit (sudah dipakai) + hasil quick-add muncul di daftar pencarian langsung.
  - `PelangganService` validasi unique telepon, auto tier terendah, `is_reseller=false` default.

- **[T-20] Input foto galeri + kamera (dual input)**
  - Terima unit: setiap slot foto punya 2 label — "Kamera" (`capture="environment"`) + "Pilih dari galeri" (input biasa).
  - Responsif, fallback galeri bila device tidak support capture. Pertahankan validasi min 2 foto.

### Gap Verifikasi Akunting/Omnichannel/Dashboard — T-24, T-26, T-27
- **[T-24] Export Laporan — jenis `arus_kas` + GET/POST + Notifikasi persist**
  - Controller (`AkuntingController::export`): validasi `jenis` tambah `arus_kas`; input ambil via `$request->input()` biar GET/POST ambil sama; response: notifikasi + link download `/api/akunting/export/download` (route :149 sudah ada). **Route GET `/api/akunting/export` BELUM didaftarkan** (di luar scope fixer) — frontend butuh `Route::match(['get','post'])` agar query param `?jenis=&periode_dari=&periode_sampai=` jalan.
  - Service (`ExportLaporanService::dataArusKas`): query kas masuk/keluar per hari dari jurnal akun `110-01` (kas) — sederhana, RAM-friendly.
  - Job (`ExportLaporanJob`): **ganti session flash rapuh** dengan `NotificationService::kirim('inapp', ...)` → persist `notifikasi_keluar` + queue (DB driver + Supervisor), payload berisi `url` download (base64 path). Error juga lewat notifikasi.
- **[T-26] SOT Stok + Biaya Admin Marketplace**
  - `ChannelSyncService::syncStokSemuaChannel`: **baca `StockMutationLog` urut `terjadi_at`** (delta kumulatif per produk+gudang) **BUKAN `StokItem::sum`** — konsisten dengan buku mutasi. Serialized per SKU (lock mapping) tetap dipertahankan. `StockMutationLog` dipakai via `get()->sum('delta')` (ponytail: incremental watermark).
  - `ChannelOrder` model: tambah `estimasi_biaya_platform` ke `Fillable` (sebelum mass-assign drop → simpan 0).
  - `ChannelSyncService::prosesBiayaAdmin(ChannelOrder)`: jurnal **Debit 520-06 "Beban Biaya Admin Marketplace" / Kredit 110-01 Kas** (COA firstOrCreate fallback, pola `KasSesiState`). **Idempoten**: `referensi_tipe='channel_order' + referensi_id` unik → duplikat callback/webhook tidak dobel posting.
  - `ChannelSyncService::pullOrders`: status `selesai` → auto `prosesBiayaAdmin`.
  - `OmnichannelController::webhook`: `estimasi_biaya_platform` dihitung dari payload + `kredensial['biaya_persen']` (fallback 5%); status `COMPLETED` → update `selesai` + `prosesBiayaAdmin` (idempoten). Duplicate webhook `COMPLETED` juga panggil.
- **[T-27] Dashboard Per Role**
  - `DashboardIndex`: `limit(8)→10` stok kritis; 4 computed properties role-scoped (lazy eval):
    - `kasir` → `omzetShiftKasir`: omzet shift sendiri dari `KasSesiState::sesiKasAktif()` (scoped `kasir_id` + `created_at >= dibuka_at`), tanpa sesi fallback hari ini.
    - `finance` → `ringkasanKeuangan`: laba bersih bulan ini (jurnal) + total piutang + lewat tempo.
    - `marketing` → `marketInsight`: komposisi tier pelanggan (server `groupBy`) + performa broadcast (dari `notifikasi_keluar`).
    - `staff-gudang` → `poPending`: PO status `draft|dikirim` (Wms PO status valid) per cabang.
  - Blade (`dashboard-index.blade.php`): render via `@role('kasir|finance|marketing|staff-gudang')` — hanya widget role terkini dievaluasi (query ringan). **Chart lib tidak ditambah** — SVG/CSS server-computed (aman RAM 1GB).
- **PERLU `StockMutationLog` di (diluar scope saya — laporan ke owner):**
  - `ServisService:226-240` (`inputSparepart`) — kurangi StokItem tanpa `StockMutationLog`.
  - `PosController:263` (transaksi selesai API) — item created, deduct stok via service/loop, tanpa log mutasi.
  - `PosKasir:389` (Livewire POS) — deduct StokItem langsung tanpa `StockMutationLog`.
- **Route dibutuhkan (scope luar fixer):**
  - `Route::match(['get','post'], '/akunting/export', [AkuntingController::class, 'export'])` agar GET query param jalan (sekarang hanya POST di routes/api.php:148).
- **DDL usulan (hanya catatan — JANGAN jalankan migrate fresh):**
  - `notifikasi_keluar`: opsional `user_id` kolom (PK foreign) bila perlu query per user cepat; sekarang `payload['user_id']` di JSON cukup.
  - `channel_orders.estimasi_biaya_platform` sudah ada via migration `000035`.

### Gap Verifikasi POS — T-07, T-09, T-18 (reuse customer-picker)
- **[T-07]** `GET /api/pos/produk` (`PosController::products`, API POS-02) — mode ringkas utk autocomplete/debounce via parameter baru `q` (alias `query`): respons ±6 field (`id`, `nama`, `sku`, `harga`, `stok`, `foto`), server-side `limit 10`, harga via `PricingService` (tier/reseller tetap berlaku). Jalur legacy param `search` + paginasi 24 tidak diubah → kompatibel pemakaian existing. SKU diambil dari varian aktif pertama; `foto` = `foto[0]` (gallery T-11) fallback `gambar`.
- **[T-09]** Livewire `PosKasir::processTransaction` kini validasi sesi kas utk `metode_bayar=tunai` (`KasSesiState::isActiveSesi()`, sama dgn API POS-01 `PosController:184-190`) → error + paksa buka modal buka kas (`bukaKasModal()`), transaksi dibatalkan. Auto-open modal buka kas saat `mount()` bila sesi tidak aktif (fallback `isActiveSesi()` = true saat tabel `kas_sesi` belum ada → tidak muncul). Non-tunai (transfer/qris/piutang/split) tidak divalidasi — sesuai aturan existing API.
- **[T-18]** Blok pencarian/tambah pelanggan inline di `pos-kasir.blade.php` (187-239) diganti `<x-customer-picker>` (reusable, sama seperti Servis `servis-board.blade.php:182`): `wireModel="pelangganSearch"`, `selectAction="setPelanggan"`, `addAction="openPelangganBaru"`, `:results="$pelangganCari"`. Tambah method `PosKasir::openPelangganBaru()` (reset form + buka modal create CRM-06); `simpanPelangganBaru` via `PelangganService` tetap dipakai → hasil langsung terpilih (`setPelanggan`) + muncul di keranjang. Select "Pelanggan Umum"/daftar member & tombol reset ✕ dipertahankan.
- Route dibutuhkan (sudah ada, tidak diubah): `GET /api/pos/produk` (`routes/api.php:63`, `permission:pos.view`). Verifikasi: `php -l` OK, `artisan view:cache` PASS, Pint 1 fix (import `KasSesiState`).

### Rilis Fase 10 ke PROD (T-28..T-37 ✅)
- **Rilis 635c66d live di prod** (`https://66.42.48.27`) — seluruh Fase 10: Sanctum SPA auth fix (statefulApi+session), T-32 theme toggle, T-33 kas-akunting, T-34 ribuan, T-35 thermal print, T-36 customer sync, T-37 birthday+tahan.
- Migrate prod DONE (3 kolom baru: `users.theme_preference`, `kas_sesi.sumber`, `pelanggan.tanggal_lahir`); `npm run build` + config/route/view:cache; queue workers restart via `supervisorctl restart ute-parts-queue:*`.
- **Verifikasi live prod PASS**: /shop, /app/login, /manifest.json, /offline.html → 200; /app/pos → 302 (auth); login 302 → /app/pos; `POST /api/user/theme` 200 (DB=dark); `POST /api/select-branch` 200; build assets 200.
- **Auto-deploy DIMATIKAN** (permintaan user): timer `ute-parts-deploy.timer` stop+disable → rilis prod manual.
- **deploy.sh 2 bug difix** (commit `c47ef10`): (a) `rm -rf` sebelum `mv public/build` (cegah Permission denied → 6× deploy mati senyap di 10:26–10:46); (b) early-exit "Tidak ada perubahan" kini memulihkan build yang dipindah ke backup. Uji ulang PASS ("Pulihkan public/build dari backup").
- Sisa plan: tidak ada — Fase 10 = fase terakhir implement-plan.md; semua tiket T-28..T-37 status done.

## 2026-09-20

### Fase 10 — Thermal Print Backend (T-35, sisi server)
- **[T-35]** [ADR 0009] `App\Modules\Pos\Services\EscPosWriter` (baru, ~190 baris, pure PHP tanpa dependensi): builder byte ESC/POS 58mm — init `ESC @`, alignment `ESC a`, bold `ESC E`, ukuran `GS !`, wrap 32 kolom, barcode Code128 `GS k` (m=73, panjang 2-byte little-endian) dari `no_transaksi`, feed + cut `GS V B 0`; teks di-transliterasi non-ASCII→ASCII (é→e, ±→+/-) agar aman di cp437; deterministik, tanpa akses DB.
- **[T-35]** `App\Modules\Pos\Jobs\PrintThermalJob` (ShouldQueue, queue `default`/database): load `transaksi` + `items.produk/skuVariant` + kasir + cabang → susun data struk → byte via `EscPosWriter`. Jika `THERMAL_PRINTER` terisi → kirim `lp -d {printer} -o raw` (stdin pipe, symfony/process), sukses/gagal di-log; jika kosong (staging) → artefak `.bin` ke `storage/app/private/prints/` (disk local Laravel 13) + `Log::info`. Tidak pernah sync di request.
- **[T-35]** Endpoint `[API: POS-10] POST /api/pos/transaksi/{id}/print` (`permission:pos.view`): `PosController::printStruk` — scope cabang sesi, 404 bila tidak ketemu, hanya `dispatch` (non-blocking) → `Struk masuk antrian cetak`. `THERMAL_PRINTER=` ditambah di `.env` (staging, kosong; `QUEUE_CONNECTION=database` sudah ada).
- Verifikasi: EscPosWriter sample (starts `ESC @`, GS k, cut — 0x15=21 len barcode OK); unit 9 PASS; E2E staging login→print→queue:work→artefak `storage/app/private/prints/TRX-C01-20260919-0008_*.bin` PASS.

## 2026-09-20

### Fase 10 — Ribuan Separator (T-34)
- **[T-34]** [ADR 0011] Direktif Alpine `x-format-number` (`resources/js/components/format-number.js`, daftar via `alpine:init` di `resources/js/app.js`): separators ribuan Indonesia (`.` berkelompok-3) tampil live saat ketik; desimal pakai koma (`1.000.000,50`) didukung; paste `1.000.000`/`1000000` dinormalisasi.
- **[T-34]** Model tetap bersih: Livewire `wire:model` di-bind sbg `x-model` saat `interceptInit` (selalu lebih dulu dari atribut `x-*`) → `x-model` membaca nilai mentah; direktif hanya format ulang tampilan + koreksi model lewat `el._x_model.set(toRaw(...))` utk kasus paste berformat; sinkron model→tampilan via effect reaktif (quick-cash `setQuickCash`/`$set`, respons server) tanpa lag (<50ms, murni client-side).
- **[T-34]** Diterapkan ke 10 input nominal: POS `jumlahBayar`/`splitTunai`/`splitNonTunai`/`kasSaldoAwal`/`kasSaldoFisik`, Akunting `manualLines.*.debit`/`kredit`/`bayarUtangJumlah`/`bayarPiutangJumlah`, CRM `tierForm.min_belanja_12bulan`; `type="number"` → `type="text" inputmode="numeric"` (spinner browser hilang, keypad numerik tetap). Marketplace: belum ada view checkout (hanya auth/components/layouts/modules) — dilewati. Verifikasi: build PASS, bundle berisi registrasi direktif, logic 11 kasus uji PASS, view:cache 10/10 input ter-compile.

## 2026-09-20

### Fase 10 — Kas-Akunting Sync Verification (T-33)
- **[T-33]** Migration `2026_09_20_000036_add_sumber_to_kas_sesi_table` — kolom `kas_sesi.sumber` (`manual|legacy|carryover`, default `manual`) utk lacak asal saldo awal shift.
- **[T-33]** `KasSesiState`: buka kas jurnal kini Debit `110-01` Kas / Kredit `310-01` Modal Pemilik (hapus akun keliru `310-03`); idempotent per-kasir (user_id atau sesi bersama NULL), multi-kasir diperbolehkan dalam cabang sama; `sesiKasAktif()` scoped ke kasir aktif; akumulasi tutup kas di-scope ke `kasir_id` sesi (cabang-wide utk sesi bersama); selisih jurnal pindah `520-06` → `520-07` Selisih Kas; `riwayat()` dukung limit.
- **[T-33]** Carryover shift: modal buka kas prefill `saldo_akhir_fisik` sesi tutup terakhir (prioritas kasir sendiri, fallback sesi bersama), `sumber=carryover` otomatis.
- **[T-33]** Laporan Akunting: section "Riwayat Sesi Kas" (50 terakhir per cabang) di tab Laporan + endpoint `[API] GET /api/akunting/kas-sesi` (permission `laporan.cabang`) → `AkuntingController::kasSesi`.
- **[T-33]** E2E staging: buka/tutup kas + jurnal balance (110-01↔310-01, 110-01↔520-07), multi-kasir 2 user, carryover, idempotent per-kasir — PASS (data uji dibersihkan). Unit: 9 test (Pricing + Servis State Machine) tetap PASS.

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

---

## 2026-09-19

### Fase 10 — Keamanan & Optimasi UI
- **[T-28]** Security scan komprehensif + hardening:
  - `composer audit` / `npm audit`: 0 vulnerabilities
  - **SecurityHeaders middleware**: CSP (Laravel 13 + Livewire + Alpine + Vite), HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy
  - **ForceHttps middleware**: HTTP → HTTPS redirect, skip local/health checks
  - **Global rate limiting**: `throttle:60,1` di semua `/api/*` (login `10,1`)
  - **Session/Sanctum hardening**: secure cookies, token expiration 24h, FORCE_HTTPS
  - Bootstrap: middleware registered for web & api

- **[T-29]** Sidebar Ganti Cabang Fix:
  - Modal ganti cabang sekarang memanggil **API AUTH-02** (`POST /api/select-branch`) via `fetch` + CSRF
  - Alpine.js `branchSwitcher` component dengan loading state & error handling
  - On success: `window.location.reload()` → semua modul (Dashboard, POS, WMS) re-query cabang baru
  - Event global `open-branch-modal` untuk buka modal dari sidebar button
  - Cleanup: hapus `x-data` lama di body tag, pakai component `branchSwitcher()`

- **[T-30]** Responsive UI optimization mobile-first:
  - **Tailwind 4 breakpoints**: tambah `xxs: 320px`, `xs: 375px` di `@theme`
  - **CSS touch-friendly**: `.glass-input` min-height 44px, font-size 16px mobile (anti-zoom iOS), button min 44x44px
  - **Backoffice layout**: hamburger menu mobile, sidebar off-canvas (slide kiri + overlay), padding responsive `p-3 lg:p-6`
  - **POS Kasir**: grid produk responsive `grid-cols-1 xxs:grid-cols-2 xl:grid-cols-3`, toolbar flex-wrap, **cart panel jadi bottom sheet mobile** (drag handle, overlay, sticky toggle button), desktop tetap side panel
  - **Cart partial baru**: `resources/views/modules/pos/livewire/partials/cart-content.blade.php` dengan quantity controls 44x44px, action buttons min-h-44px
  - Semua form input & button min 44x44px di mobile, font-size 16px anti-zoom iOS

- **[T-31]** PWA Basic Install:
  - **Manifest**: `manifest.json` dengan name, short_name, icons (SVG + PNG 192/512), shortcuts POS/WMS/Servis
  - **Service Worker**: `vite-plugin-pwa` generateSW, precache 8 entries (147 KiB), runtime caching (Google Fonts 1yr, images 30d, API 5min network-first)
  - **Offline Page**: `public/offline.html` branded, auto-retry, daftar fitur offline
  - **App.js**: SW registration, update notification toast, online/offline detection, periodic update check
  - **Layouts**: Backoffice & Marketplace dengan manifest link, theme-color, apple-touch-icon, apple-mobile-web-app-capable
  - **Icons**: SVG (any maskable) + PNG 192x192 & 512x512

## 2026-09-20

### Fase 10 — Bug Fixes & Stabilisasi:
- **POS Kasir computed properties fix**: Memperbaiki passing computed properties (`total`, `diskonTotal`, `totalBayar`, `subtotal`) ke view dengan explicit passing di `render()` method
- **Sidebar cabang Alpine.js fix**: Memperbaiki `@if` Blade directive di dalam script tag → ganti ke `x-show` Alpine.js
- **Login backoffice HTTPS fix**: Memperbaiki CSRF token mismatch saat login via HTTPS dengan self-signed cert (preserve CSRF token sebelum/after `Auth::attempt()`)
- **Session config fix**: `SESSION_SAME_SITE=none` untuk cross-origin HTTPS di IP, `SESSION_SECURE_COOKIE=true`
- **HTTPS di IP VPS**: Self-signed cert untuk 66.42.48.27, nginx config dengan HTTP→HTTPS redirect untuk IP, domain tetap di port 80
- **Asset URL dynamic**: Middleware `SetAssetUrl` untuk serve asset via IP (HTTP) atau domain (HTTPS)
- **TrustProxies middleware**: Agar Laravel mengenali IP asli di belakang nginx proxy
- **Dynamic ASSET_URL**: Middleware `SetAssetUrl` set `app.asset_url` berdasarkan host request (IP vs domain)

### Fase 10 — Dark/Light Theme Toggle (T-32):
- **Token remap runtime**: `html[data-theme="light"]` membalik `--color-ink-*` (950→#F8FAFC … 50→#1E293B) + `@custom-variant dark` di `app.css` → utility `dark:` berbasis class, seluruh UI re-theme tanpa menyentuh blade modul
- **theme.js**: komponen Alpine `themeManager` (dark/light/auto) — localStorage instan + sinkron DB via `POST /api/user/theme` (hanya staf login), mode `auto` ikuti `prefers-color-scheme` live, fallback zona (backoffice dark / marketplace light)
- **FOUC prevention**: bootstrap inline <script> di `<head>` kedua layout (pref server > localStorage > default zona, set `data-theme` + `.dark` sebelum paint)
- **Toggle button**: backoffice header + marketplace nav — sun/moon/monitor SVG, hint & aria-label Indonesia, area sentuh ≥44px
- **Surface fixes layout**: backoffice (sidebar/header/modal) & marketplace (navbar/footer) pasang pasangan `light/dark:` (mis. `text-ink-50 dark:text-white`, `border-black/10 dark:border-white/5`); token `ink-200/300` ditambahkan ke @theme; glass/input/scrollbar theme-aware di `prism-tokens.css`
EOF
### Fase 10 — Customer Create POS Sync CRM (T-36):
- **PelangganService** (`app/Modules/Crm/Services/PelangganService.php`): single source of truth pembuatan pelanggan — rules terpusat (nama, telepon unique, email unique), tier default terendah, is_reseller=false, terima `tanggal_lahir` + override `tier_membership_id`, load tierMembership
- **Pemakaian**: `POST /api/crm/pelanggan` (CRM-06) + Livewire POS (`PosKasir::simpanPelangganBaru`) + Livewire CRM (`CrmDashboard::simpanPelangganBaruCrm`) — tiga duplikasi difold jadi satu servis; respons/status kode tidak berubah
- **Pelanggan baru langsung tersedia** di pencarian POS & Servis (query DB langsung, tanpa reload)

### Fase 10 — Birthday Field + Tahan Fix (T-37):
- **`tanggal_lahir`** (nullable date) di `pelanggan` table (migration `2026_09_20_000037`), Fillable + cast `date` di model, input `<input type="date">` di form CRM + modal POS (ADR 0012)
- **Birthday targeting kampanye**: `BroadcastService::resolveTarget` — segment `birthday_month` (1-12) + `birthday_day` (1-31) → `whereMonth`/`whereDay` di `tanggal_lahir`; perbaikan bug: filter `where('is_active', true)` pada pelanggan DIHAPUS (kolom tidak ada → "Column not found" selalu)
- **Tahan fix**: shortcut F6 (`@keydown.window.f6` → `tahanTransaksi`) + panel "Transaksi Ditahan" auto-terbuka setelah tahan (`showDitahanPanel = true`)

### Fase 10 — PWA Gap Fix (T-31):
- **Dead SW registration dihapus**: `resources/js/app.js` registrasi manual `navigator.serviceWorker.register('/sw.js')` (404 — SW asli di `/build/sw.js`) dihapus. Registrasi kini SATU jalur: `import { registerSW } from 'virtual:pwa-register'` (VitePWA generateSW) + `injectRegister: 'script-defer'` (file `registerSW.js` tetap di-generate sebagai artifact, tanpa script-tag injection karena Laravel tidak punya HTML build-time). Update-notification custom dihapus (registerType autoUpdate sudah handle), online/offline toast dipertahankan
- **Offline fallback ter-wire**: `workbox.navigateFallback: '/offline.html'` + `navigateFallbackDenylist: [/^\/api\//, /^\/build\//]` + `additionalManifestEntries: [{ url: '/offline.html', revision: null }]` → `public/offline.html` ter-precache (build: precache 10 entries, termuat `/offline.html`)
- **Cache strategy sesuai AC**: runtimeCaching baru `assets-cache` StaleWhileRevalidate untuk `/\.(?:css|js|mjs)$/` (30d); API tetap NetworkFirst (5min, timeout 10s); fonts Google CacheFirst 1yr; images CacheFirst 30d
- **Build diverifikasi** (`npm run build`): `public/build/sw.js` mengandung navigateFallback `/offline.html` + denylist api/build + StaleWhileRevalidate + NetworkFirst; `public/build/registerSW.js` ada; manifest (public/manifest.json + build/manifest.webmanifest) lengkap — name, short_name, icons 192/512, theme_color `#5B4FE9`, background_color, display standalone
- **Known limitation (belum di scope)**: SW scope = `/build/` (base laravel-vite-plugin). Untuk kontrol penuh `/app/*`, perlu header `Service-Worker-Allowed: /` di nginx untuk `/build/sw.js` lalu set `scope: '/'` di VitePWA config

## 2026-09-20 (Gap Verifikasi WMS/Marketplace)

### Gap Verifikasi WMS/Marketplace — T-10, T-11, T-12, T-13, T-14
- **[T-10] Selesaikan alur supplier+PO lengkap**
  - **WmsController**: `updateSupplier` (PUT), `destroySupplier` (DELETE) — WMS-09 CRUD lengkap. `storeSupplier` (POST) sudah ada. `supplier` (GET) index sudah ada.
  - **PurchaseOrderService::terimaBarang**: sekarang `postJurnal=false` di `tambahStokPembelian` (hindari dobel jurnal), jurnal agregat diposting di service: **Debit Persediaan 130-01 / Kredit Utang 210-01 (kredit) ATAU Kas 110-01 (tunai)** — balance. PO kredit → **Otomatis buat record `Utang` (modul Akunting)** dengan `referensi_tipe=PurchaseOrder`, `kreditor_nama=supplier.nama`, `jumlah=totalHPP`, `jatuh_tempo=po.jatuh_tempo`. PO tunai → tidak buat Utang.
  - **PurchaseOrderService::bayarPO**: selain jurnal Utang debit/Kas kredit, **update record `Utang` (jumlah_dibayar, status → sebagian/lunas)**. Model `Utang` sudah ada (`app/Modules/Akunting/Models/Utang.php` + migration `2026_09_16_000026`), kolom: `po_id` via `referensi_id`, `pelanggan/supplier` via `kreditor_nama`, `jumlah`, `jatuh_tempo`, `sisa` (accessor), `status`.
  - Endpoint `WMS-11` (terima barang) akan didaftarkan pusat; method controller `updatePoStatus(action=diterima)` siap.

- **[T-11] Marketplace filter kompatibilitas HP dari `kompatibilitas_hp[]` JSON**
  - `ShopPage.php`: tambah computed property `hpMerkList` (ekstrak merk unik dari JSON produk) + `hpModelList` (ekstrak model, filtered by merk jika dipilih). `getProductsProperty()`: filter via `JSON_CONTAINS(kompatibilitas_hp, ['merk'=>...])` / `['model'=>...]` — **prioritaskan data terstruktur**; legacy `brand_kompatibel`/`model_kompatibel` tetap dipertahankan (tidak dihapus).
  - `shop-catalog.blade.php`: tambah 2 dropdown "Merk HP" & "Model HP" di filter bar (6 kolom grid), `wire:model.live="filterHpMerk"` + `filterHpModel`.

- **[T-12] Rak CRUD + field rak_id di form stok/transfer/opname**
  - Migration `2026_09_20_000040_add_wms_rak_opname_lock`: `stok_transfer_item.rak_id`, `stok_opname.rak_id`, `stok_opname_item.rak_id`, COA `520-08 Selisih Stok (Opname)`.
  - **WmsController**: `indexRak` (GET scope cabang), `storeRak`, `updateRak`, `destroyRak` — CRUD lengkap, guard relasi (stok/transfer/opname terkait → tidak bisa hapus).
  - **WmsDashboard (Livewire)**: `tambahStokForm.rak_id` → dikirim ke `ProdukService::tambahStokPembelian(rakId)` → `firstOrCreate` dengan `rak_id`. Transfer form: item row ada dropdown `rak_id` (filter gudang tujuan). Opname form: `opnameRakId` (opsional scope sesi per rak).
  - Blade `wms-dashboard`: select Rak di modal Tambah Stok, Transfer item row, Opname modal.

- **[T-13] Transfer pending → stok asal terkunci (guard oversell)**
  - `StokTransfer::pendingLockedByGudang($gudangId, $excludeId?)` & `pendingLockedFor(StokItem, $excludeId?)`: hitung qty draft transfer per produk/varian di gudang asal.
  - `StokDeductionService::kurangi`: **guard cek pending locked** — throw `Exception` "stok tidak mencukupi karena X unit terkunci transfer pending" bila `stok->jumlah - locked < qty`. POS, Servis, Marketplace order semua lewat service ini → terproteksi.
  - `WmsController::stok` (API WMS-01): tambah `stok_dikunci` virtual attribute per item (qty draft pending).
  - `WmsController::kirimTransfer`: guard lock exclude current transfer (allow kirim own draft).
  - Pertahankan `DB::transaction + lockForUpdate` existing.

- **[T-14] Opname scope rak + jurnal penyesuaian balance**
  - `storeOpname`: optional `rak_id` (scope sesi per rak). `inputOpnameItems`: per-item `rak_id` (scope per item), `stok_sistem` dibaca dari `StokItem` per rak.
  - `StokOpname` model: relasi `rak()` + `items()` sudah terupdate.
  - `approveOpname` (WmsController): selisih ≠ 0 → **buat jurnal balance pakai `JurnalService`** (service yang sudah ada). Pola: `selisih>0` (fisik>sistem) → **Debit 130-01 Persediaan / Kredit 520-08 Selisih Stok**; `selisih<0` (fisik<sistem) → **Debit 520-08 / Kredit 130-01**. `StokLog` + `StockMutationLog` tetap tercatat per item. COA `520-08` di-create via migration.

- **Tests**: 38 passed (187 assertions) — termasuk `test_po_kredit_diterima_dan_dibayar`, `test_tambah_produk_dengan_stok_awal_membuat_jurnal_pembelian`, `test_halaman_marketplace_render`.

### ROUTES DIBUTUHKAN (scope luar fixer, catat di sini untuk owner):
- `Route::put('/wms/supplier/{id}', [WmsController::class, 'updateSupplier'])->middleware('permission:wms.update');`
- `Route::delete('/wms/supplier/{id}', [WmsController::class, 'destroySupplier'])->middleware('permission:wms.delete');`
- `Route::get('/wms/rak', [WmsController::class, 'indexRak'])->middleware('permission:wms.view');`
- `Route::post('/wms/rak', [WmsController::class, 'storeRak'])->middleware('permission:wms.create');`
- `Route::put('/wms/rak/{id}', [WmsController::class, 'updateRak'])->middleware('permission:wms.update');`
- `Route::delete('/wms/rak/{id}', [WmsController::class, 'destroyRak'])->middleware('permission:wms.delete');`
- `Route::put('/wms/transfer/{id}/kirim', ...)` — sudah ada (WMS-03)
- `Route::put('/wms/transfer/{id}/terima', ...)` — sudah ada (WMS-04)
- `Route::put('/wms/po/{id}/status', ...)` — sudah ada (WMS-10/11)
- `Route::post('/wms/po/{id}/bayar', ...)` — sudah ada (WMS-12)

### Gap Verifikasi CRM/Reseller/Seeder — T-01, T-22, T-23, T-36
- **[T-01] TransaksiDemoSeeder: lengkap semua jenis transaksi + jurnal balance**
  - Tambah PO Supplier kredit & tunai (`PurchaseOrder`, `PurchaseOrderItem`) + `PembayaranSupplier` parsial untuk kredit + jurnal yang balance:
    - PO kredit: 130-01 debit / 210-01 kredit → bayar parsial: 210-01 debit / 110-01 kredit
    - PO tunai: 130-01 debit / 110-01 kredit
  - Servis `onSelesai` jurnal otomatis via `ServisService::updateStatus` (state machine transitions): 3 jurnal untuk 3 tiket 'selesai' (Kas 110-01 / Pendapatan Jasa 420-01).
  - Transfer gudang: existing (tanpa jurnal, hanya stok log).
  - Stock Opname: selisih -1 → jurnal **520-07 (Selisih Kas) debit / 130-01 (Persediaan) kredit** @ HPP produk.
  - Docblock updated: "semua jenis transaksi + jurnal".
  - Verified: **ALL JURNALS BALANCED ✓**, idempotent (`sudahAdaDemo` + `firstOrCreate` guards).

- **[T-22] Pengaturan "Strategi Loyalitas" di SettingsRbac (halaman RBAC)**
  - Tab baru `loyalitas` di `settings-rbac.blade.php` + state di `SettingsRbac.php` (`loyalitasForm`, `skemaKomisiForm`, `mount()`, `simpanLoyalitas()`).
  - Form: rasio earn poin (% transaksi), rasio redeem (1 poin = Rp X), diskon % per tier (Silver/Gold/Platinum), skema komisi default (persen/nominal per kategori).
  - GET `/api/crm/config`: **baca nilai TERSIMPAN dari tabel `konfigurasi`**, fallback `defaults()` bila NULL (tidak hardcode).
  - PUT `/api/crm/config`: simpan ke `konfigurasi` + update `TierMembership.diskon_persen` + `SkemaKomisi.nilai`.
  - Non-retroaktif: komentar di blade + controller.
  - Fix: `KonfigurasiService::set()` ganti `updateOrInsert` dengan `COALESCE` subquery (MySQL error) → dua step `update`/`insert`.

- **[T-23] Eksekusi broadcast terjadwal via scheduler**
  - Command `crm:broadcast-terjadwal` (`app/Modules/Crm/Console/Commands/EksekusiBroadcastTerjadwal.php`): ambil `KampanyeBroadcast` status `terjadwal` & `dijadwalkan_at <= now()` → `BroadcastService::kirimSekarang()` (channel WA/Email/InApp, personalisasi `{nama}`/`{tier}`) → update status + log per penerima (`NotifikasiKeluar.kampanye_broadcast_id`).
  - Daftar di `routes/console.php` + `bootstrap/app.php` schedule tiap menit (`->everyMinute()`).
  - Queue-only: notifikasi lewat `NotificationService::kirim` → `KirimNotifikasiJob` (DB queue + Supervisor), **tidak sync di request**.

- **[T-36] ResellerController::daftarReseller pakai PelangganService::create**
  - Ganti `Pelanggan::create` langsung → `PelangganService::create()` (single source of truth).
  - Validasi terpusat: `nama`, `telepon` unique, `email` unique, `tanggal_lahir`, `tier_membership_id` optional, `is_reseller=true` tetap dipertahankan.
  - Custom skema komisi per kategori tetap disimpan ke `skema_komisi_reseller`.


### Fase 11 — T-44 & T-43 (Database Produk Komprehensif + Batch Import Excel)
- **[T-44] Database Produk Komprehensif (Request #17)** — Skema produk diperluas mengakomodasi SID Retail & kebutuhan bisnis:
  - Migration `2026_09_21_000043_create_brand_kualitas_tipe_hp_tables` (Ran): tabel `brands` (12 seeded), `kualitas_produk` (5: Original, Grade A/B, Refurbished, Compatible), `tipe_hp` (31 seeded Apple/Samsung/Xiaomi/Oppo/Vivo/Realme/Infinix/Tecno/Google/Huawei/Universal), pivot `kompatibilitas_produk_tipe_hp` (M2M terstruktur). `produk` + `brand_id`/`kualitas_id` nullable FK. `pelanggan.tipe_konsumen` (retail|reseller|agen, default retail) — backfill dari `is_reseller`. `harga_tier`: kolom baru `sku_variant_id`, `tipe_konsumen`, `persen_diskon`, `nominal_tetap`; unique `(produk_id, tier_membership_id, tipe_konsumen)`; backfill `tipe_konsumen` dari legacy `is_reseller`; seed 1 baris per produk × tipe_konsumen (nominal = `harga_jual_retail`).
  - Models: `Brand`, `KualitasProduk`, `TipeHp` (M2M `produk.tipeHps()`), `Produk` relasi baru, `HargaTier` (baru per tipe_konsumen + tier). Backward compatible: kolom legacy `harga`/`is_reseller` dipertahankan.
  - `PelangganService::resolveHarga(produk, pelanggan)` — SATU sumber kebenaran harga (prioritas: HargaTier eksak tier+tipe > legacy reseller > legacy tier override > dison tier CRM > HargaTier tipe-default > fallback retail). Terverifikasi tinker: reseller 880k vs retail 1.1M (20% diskon).
  - Form Master Produk (WmsDashboard): field brand, kualitas, tipe HP (multiple), harga tier per tipe_konsumen (retail/reseller/agen) — validasi barcode unique, satuan wajib, minimal 1 harga tier.

- **[T-43] Batch Upload Produk via Excel (Request #16)** — Template → Preview (dry-run) → Commit via queue:
  - Migration `2026_09_21_000044_create_import_log_table` (Ran): tabel `import_log` (detail JSON, status proses/sukses/gagal).
  - Template export `ImportProdukTemplateExport` (Maatwebsite/Excel): sheet 1 = header + 2 contoh baris (LCD iPhone 13 Original, Baterai Samsung A54 Grade A); sheet 2 = petunjuk (12 poin, terminologi Ute Parts: stok awal = inisialisasi modal stok awal jurnal 130-01/310-01, stok masuk harian tetap lewat PO).
  - Service `ImportProdukService`: parse CSV (fgetcsv, TANPA PhpSpreadsheet, RAM 1GB) + xlsx; validasi per baris (SKU/barcode unique, satuan/gudang/rak FK, harga numeric ≥ 0, stok_awal > 0 ⇒ gudang_id wajib); preview 5 baris + error_rows detail; commit chunk 100 baris/transaction; brand/kualitas/tipe_hp firstOrCreate natural key; produk+sku_variant+harga_tier(3 tipe)+stok_items+StokLog+StockMutationLog(sumber='import:excel'); jurnal agregat stok awal Debit 130-01 / Kredit 310-01 balance, no_jurnal deterministik per import_log (idempoten); notifikasi_keluar via queue (inapp) + detail per baris di import_log.
  - Job `ImportProdukExcelJob` (ShouldQueue, DB queue, chunk 100): handle commit + update import_log + notifikasi + cleanup file tmp.
  - API: GET `/wms/produk/import/template`, POST `/wms/produk/import/preview`, POST `/wms/produk/import/commit`, GET `/wms/produk/import/log/{id}` — middleware `permission:wms.create|view`.
  - Livewire `WmsDashboard`: modal Import Excel (upload → preview → commit), tombol "📥 Import Excel" di tab Master Produk.
  - Verifikasi: migrations DONE; models seeded; `resolveHarga` PASS; `php -l` semua file clean; `WmsImportVerifyTest` menyentuh resolveHarga, preview (10 valid/4 invalid), commit (produk+sku+harga_tier×3+stok+SML+jurnal balance+notifikasi), idempoten (run ulang 0 sukses/14 gagal, tidak dobel).


### Fase 11 — T-42 (fase 2: mapping SID → Ute Parts) + T-44 & T-43
- **Migration** `2026_09_21_000046_create_sid_import_map_table.php` — tabel `sid_import_map` (kode_sumber, tabel_sumber, entity_type, entity_id, unique mapping SID→Ute Parts).
- **Command** `sid:migrate-ute-parts` (with `--dry-run` flag per PRD §4.11) — migrasi lengkap dari 8 tabel `sid_retail_raw_*` ke skema Ute Parts final:
  - **Referensi (1)**: `setup_perusahaan` → `gudang` (PUSAT).
  - **Master Barang (6.362 produk)**: `sid_retail_raw_barang` (filter `jenis=BARANG`, 6.362 of 6.433 raw) → `produk` + `sku_variants` (1:1) + `harga_tier` (retail/reseller/agen) menggunakan field SID: `harga_toko` (retail), `harga_member/harga_partai/harga_karyawan` (reseller), `harga_toko2/harga_partai2/harga_cabang` (agen). Brand dari `merk` (20 brands), kualitas auto-tier harga (5: Original/Grade A/B/Refurbished/Compatible), satuan dari `satuan`, kategori dari `golongan>subgolongan1`, barcode dari `kode_barcode`. Tipe HP pivot (31 seeded) dikosongkan (disiapkan utk fase 2 marketplace).
  - **Master Pelanggan (291)**: `sid_retail_raw_pelanggan` (284) + `member` (4, merge by telepon) → `pelanggan` dengan `tier_membership` (GROUP 1-4), `tipe_konsumen` (reseller jika `member=Y` + `plafon>0`), piutang awal dari `saldo_piutang` (SID raw data all cash → 0 piutang).
  - **Master Supplier (12)**: `sid_retail_raw_supplier` (10) → `supplier` + utang awal dari `hutang/saldo_hutang` (SID raw all cash → 0 utang).
  - **Transaksi Penjualan (6.262 faktur unik, 9.497 items termap)**: `sid_retail_raw_itempenjualan` group by base faktur (strip `|line`) → `transaksi` + `transaksi_item` (lookup `sid_import_map` barang). ~7% items di-skip (barang JASA/servis atau kode tidak ter-map).
  - **Stok Log (17.176)**: `sid_retail_raw_arus_stok` (19.286) → `stok_items` + `stok_log` + `stock_mutation_log` (sumber `sid_migration`), lookup gudang via `lokasi_stok` map, referensi transaksi via `no_transaksi` di `sid_import_map`. ~2k di-skip (delta=0 atau barang tidak termap).
  - **Servis (332)**: `sid_retail_raw_servis` → `tiket_servis` (status map: SEDANG DI SERVIS→dalam_pengerjaan, SUDAH DI AMBIL→diambil, DIBATALKAN→dibatalkan, MENUNGGU APPROVAL→menunggu_approval) + `teknisi` seed ke `users`.
- **Verifikasi aktual (db_staging)**: Produk 6.368 (3.235 existing + 3.133 SID), SkuVariant 6.368, HargaTier 19.104 (3×), Pelanggan 291 (284 SID + 4 member + 3 existing), Supplier 12 (10 SID + 2 existing), Transaksi 18.786 (12.524 existing + 6.262 SID), TransaksiItem 28.491 (18.994 existing + 9.497 SID), StokItem 3.044, StokLog 120.167 (68.712 existing + 51.455 SID re-run with refs), TiketServis 342 (10 existing + 332 SID), Piutang 0, Utang 0, SidImportMap 13.251 entries. Idempoten: re-run → 0 insert baru (guard unique `kode_sumber+tabel_sumber`). Semua test suite PASS (kecuali `WmsImportVerifyTest` temporary test memori).

