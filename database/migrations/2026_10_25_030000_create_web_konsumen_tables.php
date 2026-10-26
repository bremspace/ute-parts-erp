<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('web_konsumen_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group_key', 50)->index();
            $table->string('setting_key', 100)->unique();
            $table->longText('setting_value')->nullable();
            $table->enum('value_type', ['string', 'boolean', 'integer', 'json', 'image'])->default('string');
            $table->string('deskripsi')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('web_banners', function (Blueprint $table) {
            $table->id();
            $table->string('judul', 150);
            $table->string('badge_label', 50)->nullable();
            $table->string('gambar_desktop');
            $table->string('gambar_mobile')->nullable();
            $table->string('target_url')->nullable();
            $table->enum('posisi', ['hero_slider', 'hero_side', 'mid_catalog', 'popup_modal'])->default('hero_slider');
            $table->unsignedInteger('urutan')->default(0);
            $table->dateTime('tanggal_mulai')->nullable();
            $table->dateTime('tanggal_selesai')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('click_count')->default(0);
            $table->timestamps();

            $table->index(['posisi', 'is_active', 'urutan'], 'idx_banners_posisi_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_banners');
        Schema::dropIfExists('web_konsumen_settings');
    }
};
