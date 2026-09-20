<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // [T-16] JenisServis lebih lengkap
        Schema::table('jenis_servis', function (Blueprint $table) {
            $table->string('kategori')->default('hardware')->after('kode'); // hardware | software
            $table->integer('estimasi_durasi')->default(120)->after('kategori'); // menit
            $table->boolean('butuh_part')->default(true)->after('estimasi_durasi');
        });

        // [T-17] TiketServisItem — split part & jasa
        Schema::create('tiket_servis_item', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('tiket_servis_id')->constrained('tiket_servis')->cascadeOnDelete();
            $table->enum('tipe', ['part', 'jasa'])->default('jasa');
            $table->foreignId('produk_id')->nullable()->constrained('produk')->nullOnDelete();
            $table->foreignId('sku_variant_id')->nullable()->constrained('sku_variants')->nullOnDelete();
            $table->string('nama_item');
            $table->integer('qty')->default(1);
            $table->decimal('harga', 15, 2)->default(0);
            $table->decimal('hpp', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['tiket_servis_id', 'tipe']);
        });

        // [T-19] Kunci gadget terenkripsi
        Schema::table('tiket_servis', function (Blueprint $table) {
            $table->string('tipe_kunci')->nullable()->after('seri_hp'); // pola | pin | password | tidak_ada
            $table->text('kunci_terenkripsi')->nullable()->after('tipe_kunci'); // encrypted at-rest
        });
    }

    public function down(): void
    {
        Schema::table('tiket_servis', function (Blueprint $table) {
            $table->dropColumn(['tipe_kunci', 'kunci_terenkripsi']);
        });
        Schema::dropIfExists('tiket_servis_item');
        Schema::table('jenis_servis', function (Blueprint $table) {
            $table->dropColumn(['kategori', 'estimasi_durasi', 'butuh_part']);
        });
    }
};