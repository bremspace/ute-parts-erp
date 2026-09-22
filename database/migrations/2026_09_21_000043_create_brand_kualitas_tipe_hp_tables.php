<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [T-44] Database produk komprehensif (Request #17).
 * - Tabel baru: brands, kualitas_produk, tipe_hp + pivot kompatibilitas_produk_tipe_hp.
 * - produk: brand_id, kualitas_id (nullable FK — backward compatible).
 * - pelanggan: tipe_konsumen (retail|reseller|agen, default retail).
 * - harga_tier: perluasan skema lama → sku_variant_id, tipe_konsumen, persen_diskon,
 *   nominal_tetap + unique(produk_id, tier_membership_id, tipe_konsumen).
 *   Kolom lama (harga, is_reseller) TIDAK dihapus/diubah (backward compatible).
 * - Seed default: satuan_unit (kosong di staging), brands, kualitas_produk, tipe_hp,
 *   dan 1 baris harga_tier per produk × tipe_konsumen (nominal = harga_jual_retail;
 *   produk master tidak punya kolom harga_reseller/harga_agen — fallback harga_jual).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nama')->unique();
            $table->string('keterangan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('kualitas_produk', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nama')->unique();
            $table->string('keterangan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tipe_hp', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('merk');
            $table->string('model');
            $table->string('nama')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['merk', 'model']);
            $table->index(['merk']);
        });

        // Pivot M2M produk ↔ tipe_hp (kompatibilitas terstruktur; kolom JSON lama tetap dipertahankan)
        Schema::create('kompatibilitas_produk_tipe_hp', function (Blueprint $table) {
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->foreignId('tipe_hp_id')->constrained('tipe_hp')->cascadeOnDelete();
            $table->primary(['produk_id', 'tipe_hp_id']);
        });

        Schema::table('produk', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('kategori')->constrained('brands')->nullOnDelete();
            $table->foreignId('kualitas_id')->nullable()->after('brand_id')->constrained('kualitas_produk')->nullOnDelete();
        });

        Schema::table('pelanggan', function (Blueprint $table) {
            $table->enum('tipe_konsumen', ['retail', 'reseller', 'agen'])
                ->default('retail')
                ->after('is_reseller');
        });

        Schema::table('harga_tier', function (Blueprint $table) {
            $table->foreignId('sku_variant_id')->nullable()->after('produk_id')->constrained('sku_variants')->nullOnDelete();
            $table->enum('tipe_konsumen', ['retail', 'reseller', 'agen'])->nullable()->after('tier_membership_id');
            $table->decimal('persen_diskon', 8, 2)->nullable()->after('harga');
            $table->decimal('nominal_tetap', 15, 2)->nullable()->after('persen_diskon');
        });

        // Backfill tipe_konsumen dari kolom legacy is_reseller
        DB::table('harga_tier')
            ->where('is_reseller', true)
            ->update(['tipe_konsumen' => 'reseller']);
        DB::table('harga_tier')
            ->whereNull('tipe_konsumen')
            ->update(['tipe_konsumen' => 'retail']);

        // Backfill pelanggan reseller existing
        DB::table('pelanggan')
            ->where('is_reseller', true)
            ->where('tipe_konsumen', 'retail')
            ->update(['tipe_konsumen' => 'reseller']);

        // Unique per (produk, tier, tipe_konsumen) — diverifikasi tidak ada duplikat existing
        Schema::table('harga_tier', function (Blueprint $table) {
            $table->unique(['produk_id', 'tier_membership_id', 'tipe_konsumen'], 'harga_tier_produk_tier_tipe_unique');
        });

        $this->seedDefaults();
    }

    private function seedDefaults(): void
    {
        // --- Satuan unit (referensi) ---
        $satuan = ['pcs', 'box', 'unit', 'set', 'pasang', 'botol', 'roll', 'meter', 'lembar', 'liter'];
        if (DB::table('satuan_unit')->count() === 0) {
            foreach ($satuan as $s) {
                DB::table('satuan_unit')->insert([
                    'kode' => $s, 'nama' => ucfirst($s), 'is_active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // --- Brand default ---
        $brands = ['Apple', 'Samsung', 'Xiaomi', 'Oppo', 'Vivo', 'Realme', 'Infinix', 'Tecno', 'Google', 'Huawei', 'Universal', 'Umum'];
        foreach ($brands as $b) {
            if (! DB::table('brands')->where('nama', $b)->exists()) {
                DB::table('brands')->insert([
                    'nama' => $b, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // --- Kualitas produk default (SID: Original, Grade A/B, Refurbished, Compatible) ---
        $kualitas = ['Original', 'Grade A', 'Grade B', 'Refurbished', 'Compatible'];
        foreach ($kualitas as $k) {
            if (! DB::table('kualitas_produk')->where('nama', $k)->exists()) {
                DB::table('kualitas_produk')->insert([
                    'nama' => $k, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // --- Tipe HP default (merk + model umum) ---
        $tipeHp = [
            ['Apple', 'iPhone 15'], ['Apple', 'iPhone 14'], ['Apple', 'iPhone 13'], ['Apple', 'iPhone 12'], ['Apple', 'iPhone 11'],
            ['Samsung', 'Galaxy A54'], ['Samsung', 'Galaxy S23'], ['Samsung', 'Galaxy A13'], ['Samsung', 'Galaxy S24'],
            ['Xiaomi', 'Redmi Note 12'], ['Xiaomi', 'Redmi 13C'], ['Xiaomi', 'Redmi Note 13'], ['Xiaomi', 'Poco X6'],
            ['Oppo', 'Reno 11'], ['Oppo', 'A78'], ['Oppo', 'A18'],
            ['Vivo', 'Y17s'], ['Vivo', 'V27'], ['Vivo', 'Y36'],
            ['Realme', 'C67'], ['Realme', '11 Pro'], ['Realme', 'Note 50'],
            ['Infinix', 'Hot 40i'], ['Infinix', 'Note 30'], ['Infinix', 'Hot 40'],
            ['Tecno', 'Spark 20'], ['Tecno', 'Camon 30'],
            ['Google', 'Pixel 7'], ['Google', 'Pixel 8'],
            ['Huawei', 'Nova 11i'],
            ['Universal', 'Semua Tipe HP'],
        ];
        foreach ($tipeHp as [$merk, $model]) {
            if (! DB::table('tipe_hp')->where('merk', $merk)->where('model', $model)->exists()) {
                DB::table('tipe_hp')->insert([
                    'merk' => $merk, 'model' => $model, 'nama' => "{$merk} {$model}",
                    'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // --- Seed harga_tier default: 1 baris per produk × tipe_konsumen ---
        // sku_variants/produk tidak punya harga_reseller/harga_agen → nominal = harga_jual_retail.
        // updateOrCreate berbasis (produk_id, tier NULL, tipe_konsumen) supaya tidak bentrok
        // dengan baris legacy is_reseller (= tipe_konsumen 'reseller' setelah backfill).
        foreach (DB::table('produk')->pluck('id') as $produkId) {
            $hargaJual = (float) (DB::table('produk')->where('id', $produkId)->value('harga_jual_retail') ?? 0);
            foreach (['retail', 'reseller', 'agen'] as $tipe) {
                $exists = DB::table('harga_tier')
                    ->where('produk_id', $produkId)
                    ->whereNull('tier_membership_id')
                    ->where('tipe_konsumen', $tipe)
                    ->exists();
                if ($exists) {
                    // Baris legacy is_reseller — hanya isi nominal_tetap bila masih kosong,
                    // kolom legacy `harga` tidak disentuh.
                    DB::table('harga_tier')
                        ->where('produk_id', $produkId)
                        ->whereNull('tier_membership_id')
                        ->where('tipe_konsumen', $tipe)
                        ->whereNull('nominal_tetap')
                        ->update(['nominal_tetap' => $hargaJual, 'updated_at' => now()]);
                } else {
                    DB::table('harga_tier')->insert([
                        'produk_id' => $produkId,
                        'sku_variant_id' => null,
                        'tier_membership_id' => null,
                        'is_reseller' => $tipe === 'reseller',
                        'tipe_konsumen' => $tipe,
                        'harga' => $hargaJual,
                        'nominal_tetap' => $hargaJual,
                        'persen_diskon' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('harga_tier', function (Blueprint $table) {
            $table->dropUnique('harga_tier_produk_tier_tipe_unique');
            $table->dropConstrainedForeignId('sku_variant_id');
        });
        Schema::table('harga_tier', function (Blueprint $table) {
            $table->dropColumn(['tipe_konsumen', 'persen_diskon', 'nominal_tetap']);
        });
        Schema::table('pelanggan', fn (Blueprint $t) => $t->dropColumn('tipe_konsumen'));
        Schema::table('produk', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
            $table->dropConstrainedForeignId('kualitas_id');
        });
        Schema::dropIfExists('kompatibilitas_produk_tipe_hp');
        Schema::dropIfExists('tipe_hp');
        Schema::dropIfExists('kualitas_produk');
        Schema::dropIfExists('brands');
    }
};