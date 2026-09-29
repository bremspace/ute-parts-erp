<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel Kategori Produk Hierarkis (Kategori Utama & Sub Kategori)
        if (! Schema::hasTable('kategori_produk')) {
            Schema::create('kategori_produk', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('parent_id')->nullable()->index();
                $table->string('nama', 100);
                $table->string('slug', 120)->unique();
                $table->string('icon', 50)->nullable();
                $table->integer('urutan')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('parent_id')
                    ->references('id')
                    ->on('kategori_produk')
                    ->onDelete('set null');

                $table->index(['parent_id', 'urutan', 'is_active']);
            });
        }

        // 2. Tambah kategori_id dan index performa pada tabel produk untuk ratusan ribu item
        Schema::table('produk', function (Blueprint $table) {
            if (! Schema::hasColumn('produk', 'kategori_id')) {
                $table->unsignedBigInteger('kategori_id')->nullable()->after('kategori')->index();
                $table->foreign('kategori_id')
                    ->references('id')
                    ->on('kategori_produk')
                    ->onDelete('set null');
            }

            // Index performa query pencarian katalog & kasir
            $table->index(['is_active', 'kategori_id'], 'produk_active_kategori_idx');
            $table->index(['is_active', 'brand_id'], 'produk_active_brand_idx');
            $table->index(['is_active', 'kondisi'], 'produk_active_kondisi_idx');
            $table->index(['is_active', 'kualitas_id'], 'produk_active_kualitas_idx');
            $table->index(['is_active', 'harga_jual_retail'], 'produk_active_harga_idx');
            $table->index(['nama'], 'produk_nama_idx');
        });

        // 3. Tabel Kompatibilitas Antar Produk / Substitusi Produk
        if (! Schema::hasTable('kompatibilitas_antar_produk')) {
            Schema::create('kompatibilitas_antar_produk', function (Blueprint $table) {
                $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
                $table->foreignId('kompatibel_produk_id')->constrained('produk')->cascadeOnDelete();
                $table->string('catatan')->nullable(); // misal: 'Substitusi langsung', 'Pinout sama'
                $table->timestamps();

                $table->primary(['produk_id', 'kompatibel_produk_id'], 'kompatibilitas_produk_primary');
                $table->index('kompatibel_produk_id', 'kompatibel_target_idx');
            });
        }

        // 4. Seed Kategori Utama & Sub Kategori standar industri sparepart HP
        $tree = [
            [
                'nama' => 'Sparepart HP',
                'slug' => 'sparepart-hp',
                'icon' => 'device-phone-mobile',
                'urutan' => 1,
                'children' => [
                    ['nama' => 'LCD & Touchscreen', 'slug' => 'lcd-touchscreen', 'icon' => 'tv', 'urutan' => 1],
                    ['nama' => 'Baterai', 'slug' => 'baterai', 'icon' => 'battery-100', 'urutan' => 2],
                    ['nama' => 'Fleksibel & Board', 'slug' => 'fleksibel-board', 'icon' => 'cpu-chip', 'urutan' => 3],
                    ['nama' => 'Kamera', 'slug' => 'kamera', 'icon' => 'camera', 'urutan' => 4],
                    ['nama' => 'IC & Chipset', 'slug' => 'ic-chipset', 'icon' => 'microchip', 'urutan' => 5],
                    ['nama' => 'Speaker & Buzzer', 'slug' => 'speaker-buzzer', 'icon' => 'speaker-wave', 'urutan' => 6],
                    ['nama' => 'Housing & Backdoor', 'slug' => 'housing-backdoor', 'icon' => 'square-3-stack-3d', 'urutan' => 7],
                    ['nama' => 'Konektor & Sim Tray', 'slug' => 'konektor-sim-tray', 'icon' => 'circle-stack', 'urutan' => 8],
                ],
            ],
            [
                'nama' => 'Tools & Servis',
                'slug' => 'tools-servis',
                'icon' => 'wrench-screwdriver',
                'urutan' => 2,
                'children' => [
                    ['nama' => 'Lem & Perekat', 'slug' => 'lem-perekat', 'icon' => 'sparkles', 'urutan' => 1],
                    ['nama' => 'Obeng & Pembuka', 'slug' => 'obeng-pembuka', 'icon' => 'wrench', 'urutan' => 2],
                    ['nama' => 'Solder & Blower', 'slug' => 'solder-blower', 'icon' => 'fire', 'urutan' => 3],
                    ['nama' => 'Tester & Pengukur', 'slug' => 'tester-pengukur', 'icon' => 'bolt', 'urutan' => 4],
                ],
            ],
            [
                'nama' => 'Aksesoris HP',
                'slug' => 'aksesoris-hp',
                'icon' => 'cube',
                'urutan' => 3,
                'children' => [
                    ['nama' => 'Tempered Glass', 'slug' => 'tempered-glass', 'icon' => 'shield-check', 'urutan' => 1],
                    ['nama' => 'Kabel Data & Charger', 'slug' => 'kabel-charger', 'icon' => 'bolt', 'urutan' => 2],
                    ['nama' => 'Casing & Pelindung', 'slug' => 'casing-pelindung', 'icon' => 'rectangle-stack', 'urutan' => 3],
                ],
            ],
        ];

        $now = now();
        foreach ($tree as $parentData) {
            $parentId = DB::table('kategori_produk')->insertGetId([
                'parent_id' => null,
                'nama' => $parentData['nama'],
                'slug' => $parentData['slug'],
                'icon' => $parentData['icon'],
                'urutan' => $parentData['urutan'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($parentData['children'] as $childData) {
                DB::table('kategori_produk')->insert([
                    'parent_id' => $parentId,
                    'nama' => $childData['nama'],
                    'slug' => $childData['slug'],
                    'icon' => $childData['icon'],
                    'urutan' => $childData['urutan'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // 5. Backfill kategori_id pada produk existing
        $mapping = [
            'LCD' => 'lcd-touchscreen',
            'Layar' => 'lcd-touchscreen',
            'Baterai' => 'baterai',
            'Flexible' => 'fleksibel-board',
            'Board' => 'fleksibel-board',
            'IC' => 'ic-chipset',
            'Kamera' => 'kamera',
            'Tools' => 'lem-perekat',
            'Lem' => 'lem-perekat',
        ];

        $allCategories = DB::table('kategori_produk')->pluck('id', 'slug');

        $produks = DB::table('produk')->select('id', 'kategori')->get();
        foreach ($produks as $p) {
            $kategoriStr = $p->kategori ?? '';
            $matchedId = null;

            foreach ($mapping as $keyword => $slug) {
                if (stripos($kategoriStr, $keyword) !== false && isset($allCategories[$slug])) {
                    $matchedId = $allCategories[$slug];
                    break;
                }
            }

            if (! $matchedId && isset($allCategories['lcd-touchscreen'])) {
                $matchedId = $allCategories['lcd-touchscreen'];
            }

            if ($matchedId) {
                DB::table('produk')->where('id', $p->id)->update(['kategori_id' => $matchedId]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kompatibilitas_antar_produk');

        if (Schema::hasTable('produk')) {
            Schema::table('produk', function (Blueprint $table) {
                if (Schema::hasColumn('produk', 'kategori_id')) {
                    $table->dropForeign(['kategori_id']);
                    $table->dropColumn('kategori_id');
                }
                $table->dropIndex('produk_active_kategori_idx');
                $table->dropIndex('produk_active_brand_idx');
                $table->dropIndex('produk_active_kondisi_idx');
                $table->dropIndex('produk_active_kualitas_idx');
                $table->dropIndex('produk_active_harga_idx');
                $table->dropIndex('produk_nama_idx');
            });
        }

        Schema::dropIfExists('kategori_produk');
    }
};
