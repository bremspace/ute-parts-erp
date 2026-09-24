<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fase 1 migrasi SID Retail -> Ute Parts (MIGRASI-SID.md §3.8-3.9, M-01..M-08).
// IDEMPOTENT: guard hasTable/hasColumn — sebagian skema sudah dibuat manual sebelumnya
// (master_kas, return_*, nomor_seri_produk, komplain_pelanggan, kolom produk).
// Tetap aman untuk migrate:fresh (guard false -> create penuh).

return new class extends Migration
{
    public function up(): void
    {
        // ---- B. TABEL BARU (jika belum ada) ----

        if (! Schema::hasTable('master_kas')) {
            Schema::create('master_kas', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('cabang_id')->nullable();
                $table->string('kode', 25);
                $table->string('nama', 100);
                $table->decimal('saldo_awal', 15, 2)->default(0);
                $table->string('tipe', 20)->default('kas'); // kas/bank
                $table->boolean('is_default_toko')->default(false);
                $table->boolean('is_default_beli')->default(false);
                $table->boolean('is_default_piutang')->default(false);
                $table->boolean('is_default_hutang')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['cabang_id', 'kode'], 'master_kas_cabang_kode_unique');
                $table->foreign('cabang_id')->references('id')->on('cabang')->onDelete('set null');
            });
        }

        if (! Schema::hasTable('return_penjualan')) {
            Schema::create('return_penjualan', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('no_return', 50)->unique();
                $table->unsignedBigInteger('transaksi_id')->nullable();
                $table->unsignedBigInteger('pelanggan_id')->nullable();
                $table->date('tanggal')->nullable();
                $table->decimal('jumlah', 15, 2)->default(0);
                $table->string('status', 20)->default('selesai');
                $table->text('alasan')->nullable();
                $table->boolean('is_migrasi_sid')->default(false);
                $table->timestamps();

                $table->foreign('transaksi_id')->references('id')->on('transaksi')->onDelete('set null');
                $table->foreign('pelanggan_id')->references('id')->on('pelanggan')->onDelete('set null');
            });
        }

        if (! Schema::hasTable('return_penjualan_item')) {
            Schema::create('return_penjualan_item', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('return_penjualan_id');
                $table->unsignedBigInteger('produk_id')->nullable();
                $table->unsignedBigInteger('sku_variant_id')->nullable();
                $table->decimal('jumlah', 15, 2);
                $table->decimal('harga_satuan', 15, 2);
                $table->decimal('subtotal', 15, 2);
                $table->decimal('hpp', 15, 2)->nullable();
                $table->timestamps();

                $table->foreign('return_penjualan_id')->references('id')->on('return_penjualan')->onDelete('cascade');
                $table->foreign('produk_id')->references('id')->on('produk')->onDelete('set null');
                $table->foreign('sku_variant_id')->references('id')->on('sku_variants')->onDelete('set null');
            });
        }

        if (! Schema::hasTable('return_pembelian')) {
            Schema::create('return_pembelian', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('no_return', 50)->unique();
                $table->unsignedBigInteger('purchase_order_id')->nullable();
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->date('tanggal')->nullable();
                $table->decimal('jumlah', 15, 2)->default(0);
                $table->string('status', 20)->default('selesai');
                $table->text('alasan')->nullable();
                $table->boolean('is_migrasi_sid')->default(false);
                $table->timestamps();

                $table->foreign('purchase_order_id')->references('id')->on('purchase_order')->onDelete('set null');
                $table->foreign('supplier_id')->references('id')->on('supplier')->onDelete('set null');
            });
        }

        if (! Schema::hasTable('return_pembelian_item')) {
            Schema::create('return_pembelian_item', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('return_pembelian_id');
                $table->unsignedBigInteger('produk_id')->nullable();
                $table->unsignedBigInteger('sku_variant_id')->nullable();
                $table->decimal('jumlah', 15, 2);
                $table->decimal('harga_beli', 15, 2);
                $table->decimal('subtotal', 15, 2);
                $table->timestamps();

                $table->foreign('return_pembelian_id')->references('id')->on('return_pembelian')->onDelete('cascade');
                $table->foreign('produk_id')->references('id')->on('produk')->onDelete('set null');
                $table->foreign('sku_variant_id')->references('id')->on('sku_variants')->onDelete('set null');
            });
        }

        if (! Schema::hasTable('nomor_seri_produk')) {
            Schema::create('nomor_seri_produk', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('produk_id')->nullable();
                $table->unsignedBigInteger('sku_variant_id')->nullable();
                $table->string('nomor_seri', 100);
                $table->string('status', 20)->default('tersedia'); // tersedia/terjual/return/servis
                $table->unsignedBigInteger('transaksi_item_id')->nullable();
                $table->unsignedBigInteger('tiket_servis_id')->nullable();
                $table->text('keterangan')->nullable();
                $table->timestamps();

                $table->unique(['produk_id', 'nomor_seri'], 'nomor_seri_produk_unique');
                $table->foreign('produk_id')->references('id')->on('produk')->onDelete('set null');
                $table->foreign('sku_variant_id')->references('id')->on('sku_variants')->onDelete('set null');
                $table->foreign('transaksi_item_id')->references('id')->on('transaksi_item')->onDelete('set null');
                $table->foreign('tiket_servis_id')->references('id')->on('tiket_servis')->onDelete('set null');
            });
        }

        if (! Schema::hasTable('komplain_pelanggan')) {
            Schema::create('komplain_pelanggan', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('pelanggan_id')->nullable();
                $table->unsignedBigInteger('transaksi_id')->nullable();
                $table->date('tanggal')->nullable();
                $table->text('komplain');
                $table->string('status', 20)->default('terbuka');
                $table->text('resolusi')->nullable();
                $table->timestamps();

                $table->foreign('pelanggan_id')->references('id')->on('pelanggan')->onDelete('set null');
                $table->foreign('transaksi_id')->references('id')->on('transaksi')->onDelete('set null');
            });
        }

        // ---- A. TAMBAH KOLOM (jika belum ada) ----
        $this->addColumns('produk', [
            ['kode_lama', fn (Blueprint $t) => $t->string('kode_lama', 25)->nullable()->unique()],
            ['barcode', fn (Blueprint $t) => $t->string('barcode', 50)->nullable()->unique()],
            ['barcode_alt', fn (Blueprint $t) => $t->json('barcode_alt')->nullable()],
            ['brand_id', function (Blueprint $t) {
                $t->unsignedBigInteger('brand_id')->nullable();
                $t->foreign('brand_id')->references('id')->on('brands')->onDelete('set null');
            }],
            ['golongan', fn (Blueprint $t) => $t->string('golongan', 50)->nullable()],
            ['subgolongan', fn (Blueprint $t) => $t->string('subgolongan', 50)->nullable()],
            ['satuan_beli', fn (Blueprint $t) => $t->string('satuan_beli', 25)->nullable()],
            ['isi_satuan', fn (Blueprint $t) => $t->decimal('isi_satuan', 15, 2)->nullable()],
            ['harga_lain', fn (Blueprint $t) => $t->json('harga_lain')->nullable()],
            ['diskon', fn (Blueprint $t) => $t->decimal('diskon', 10, 2)->nullable()],
            ['stok_maksimum', fn (Blueprint $t) => $t->decimal('stok_maksimum', 15, 2)->nullable()],
            ['stok_warning', fn (Blueprint $t) => $t->decimal('stok_warning', 15, 2)->nullable()],
            ['expired_at', fn (Blueprint $t) => $t->date('expired_at')->nullable()],
            ['jenis', fn (Blueprint $t) => $t->string('jenis', 20)->default('barang')],
            ['wajib_serial', fn (Blueprint $t) => $t->boolean('wajib_serial')->default(false)],
            ['poin', fn (Blueprint $t) => $t->decimal('poin', 10, 2)->nullable()],
            ['komisi_sales', fn (Blueprint $t) => $t->decimal('komisi_sales', 10, 2)->nullable()],
            ['kena_pajak', fn (Blueprint $t) => $t->boolean('kena_pajak')->default(false)],
            ['nilai_ppn', fn (Blueprint $t) => $t->decimal('nilai_ppn', 10, 2)->nullable()],
            ['is_migrasi_sid', fn (Blueprint $t) => $t->boolean('is_migrasi_sid')->default(false)],
        ]);

        $this->addColumns('sku_variants', [
            ['kode_lama', fn (Blueprint $t) => $t->string('kode_lama', 25)->nullable()->unique()],
        ]);

        $this->addColumns('pelanggan', [
            ['kode_lama', fn (Blueprint $t) => $t->string('kode_lama', 25)->nullable()->unique()],
            ['kode_member', fn (Blueprint $t) => $t->string('kode_member', 50)->nullable()->unique()],
            ['no_kartu', fn (Blueprint $t) => $t->string('no_kartu', 50)->nullable()],
            ['tier_expired_at', fn (Blueprint $t) => $t->date('tier_expired_at')->nullable()],
            ['saldo_piutang', fn (Blueprint $t) => $t->decimal('saldo_piutang', 15, 2)->nullable()->default(0)],
            ['max_piutang', fn (Blueprint $t) => $t->decimal('max_piutang', 15, 2)->nullable()->default(0)],
            ['area', fn (Blueprint $t) => $t->string('area', 50)->nullable()],
            ['rayon', fn (Blueprint $t) => $t->string('rayon', 50)->nullable()],
            ['kota', fn (Blueprint $t) => $t->string('kota', 50)->nullable()],
            ['instansi', fn (Blueprint $t) => $t->string('instansi', 50)->nullable()],
            ['bergabung_at', fn (Blueprint $t) => $t->date('bergabung_at')->nullable()],
            ['diskon_persen', fn (Blueprint $t) => $t->decimal('diskon_persen', 10, 2)->nullable()->default(0)],
            ['sales_nama', fn (Blueprint $t) => $t->string('sales_nama', 50)->nullable()],
            ['is_migrasi_sid', fn (Blueprint $t) => $t->boolean('is_migrasi_sid')->default(false)],
        ]);

        $this->addColumns('supplier', [
            ['kode_lama', fn (Blueprint $t) => $t->string('kode_lama', 25)->nullable()->unique()],
            ['npwp', fn (Blueprint $t) => $t->string('npwp', 50)->nullable()],
            ['saldo_deposit', fn (Blueprint $t) => $t->decimal('saldo_deposit', 15, 2)->nullable()->default(0)],
        ]);

        $this->addColumns('transaksi', [
            ['tanggal_transaksi', fn (Blueprint $t) => $t->dateTime('tanggal_transaksi')->nullable()->index()],
            ['jenis', fn (Blueprint $t) => $t->string('jenis', 25)->default('penjualan')->index()],
            ['sales', fn (Blueprint $t) => $t->string('sales', 50)->nullable()],
            ['pajak_detail', fn (Blueprint $t) => $t->json('pajak_detail')->nullable()],
            ['sid_detail', fn (Blueprint $t) => $t->json('sid_detail')->nullable()],
            ['is_migrasi_sid', fn (Blueprint $t) => $t->boolean('is_migrasi_sid')->default(false)],
        ]);

        $this->addColumns('transaksi_item', [
            ['diskon_persen', fn (Blueprint $t) => $t->decimal('diskon_persen', 10, 2)->nullable()->default(0)],
            ['is_migrasi_sid', fn (Blueprint $t) => $t->boolean('is_migrasi_sid')->default(false)],
        ]);

        $this->addColumns('stok_log', [
            ['tanggal_log', fn (Blueprint $t) => $t->dateTime('tanggal_log')->nullable()->index()],
            ['jumlah_awal', fn (Blueprint $t) => $t->decimal('jumlah_awal', 15, 2)->nullable()],
            ['nilai_awal', fn (Blueprint $t) => $t->decimal('nilai_awal', 15, 2)->nullable()],
            ['nilai_masuk', fn (Blueprint $t) => $t->decimal('nilai_masuk', 15, 2)->nullable()],
            ['nilai_keluar', fn (Blueprint $t) => $t->decimal('nilai_keluar', 15, 2)->nullable()],
            ['nilai_sisa', fn (Blueprint $t) => $t->decimal('nilai_sisa', 15, 2)->nullable()],
            ['is_migrasi_sid', fn (Blueprint $t) => $t->boolean('is_migrasi_sid')->default(false)],
        ]);

        $this->addColumns('tiket_servis', [
            ['tanggal_bayar', fn (Blueprint $t) => $t->date('tanggal_bayar')->nullable()],
            ['teknisi', fn (Blueprint $t) => $t->string('teknisi', 50)->nullable()],
            ['pembayaran', fn (Blueprint $t) => $t->json('pembayaran')->nullable()],
            ['sid_detail', fn (Blueprint $t) => $t->json('sid_detail')->nullable()],
            ['is_migrasi_sid', fn (Blueprint $t) => $t->boolean('is_migrasi_sid')->default(false)],
        ]);

        $this->addColumns('kas_sesi', [
            ['kas_id', function (Blueprint $t) {
                $t->unsignedBigInteger('kas_id')->nullable();
                $t->foreign('kas_id')->references('id')->on('master_kas')->onDelete('set null');
            }],
        ]);

        $this->addColumns('piutang', [
            ['kas_id', function (Blueprint $t) {
                $t->unsignedBigInteger('kas_id')->nullable();
                $t->foreign('kas_id')->references('id')->on('master_kas')->onDelete('set null');
            }],
            ['kode_lama', fn (Blueprint $t) => $t->string('kode_lama', 25)->nullable()->unique()],
        ]);

        $this->addColumns('utang', [
            ['kas_id', function (Blueprint $t) {
                $t->unsignedBigInteger('kas_id')->nullable();
                $t->foreign('kas_id')->references('id')->on('master_kas')->onDelete('set null');
            }],
            ['kode_lama', fn (Blueprint $t) => $t->string('kode_lama', 25)->nullable()->unique()],
        ]);

        $this->addColumns('purchase_order', [
            ['kode_lama', fn (Blueprint $t) => $t->string('kode_lama', 25)->nullable()->unique()],
        ]);

        $this->addColumns('purchase_order_item', [
            ['kode_lama', fn (Blueprint $t) => $t->string('kode_lama', 25)->nullable()->unique()],
        ]);
    }

    /**
     * Tambah kolom hanya jika belum ada (hasColumn guard).
     *
     * @param  array<int, array{0: string, 1: Closure}>  $columns
     */
    private function addColumns(string $table, array $columns): void
    {
        $missing = array_filter($columns, fn ($c) => ! Schema::hasColumn($table, $c[0]));
        if (! $missing) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($missing) {
            foreach ($missing as [$name, $def]) {
                $def($t);
            }
        });
    }

    public function down(): void
    {
        // Migrasi fase 1 bersifat idempotent; down tidak wajib mengembalikan kondisi
        // manual sebelumnya. Hanya drop tabel yang dibuat di sini (guard).
        foreach (['komplain_pelanggan', 'nomor_seri_produk', 'return_pembelian_item', 'return_pembelian', 'return_penjualan_item', 'return_penjualan', 'master_kas'] as $t) {
            if (Schema::hasTable($t)) {
                Schema::dropIfExists($t);
            }
        }
    }
};
