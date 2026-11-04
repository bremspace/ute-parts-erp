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
        // 1. Dukungan Rich Media (GIF, Video Pendek) di Banner
        if (Schema::hasTable('web_banners')) {
            Schema::table('web_banners', function (Blueprint $table) {
                if (! Schema::hasColumn('web_banners', 'tipe_media')) {
                    $table->enum('tipe_media', ['image', 'gif', 'video'])->default('image')->after('badge_label');
                }
                if (! Schema::hasColumn('web_banners', 'video_desktop')) {
                    $table->string('video_desktop')->nullable()->after('gambar_desktop');
                }
                if (! Schema::hasColumn('web_banners', 'video_mobile')) {
                    $table->string('video_mobile')->nullable()->after('gambar_mobile');
                }
            });
        }

        // 2. Artikel & Panduan Edukasi Teknis (Knowledge Base)
        if (! Schema::hasTable('web_articles')) {
            Schema::create('web_articles', function (Blueprint $table) {
                $table->id();
                $table->string('judul', 200);
                $table->string('slug', 220)->unique();
                $table->string('ringkasan', 300);
                $table->longText('konten_lengkap');
                $table->string('kategori', 50)->default('panduan_servis');

                // Visual Cover
                $table->enum('tipe_media_cover', ['image', 'gif', 'video'])->default('image');
                $table->string('cover_image')->nullable();
                $table->string('cover_video')->nullable();

                // Video YouTube
                $table->string('youtube_url')->nullable();
                $table->string('youtube_video_id', 50)->nullable();

                // Content-to-Commerce Relations
                $table->json('produk_terkait_ids')->nullable();
                $table->unsignedBigInteger('rekomendasi_servis_id')->nullable();

                // SEO & Status Publikasi
                $table->string('meta_title', 150)->nullable();
                $table->string('meta_description', 200)->nullable();
                $table->unsignedBigInteger('penulis_id')->nullable();
                $table->boolean('is_published')->default(false);
                $table->timestamp('published_at')->nullable();
                $table->unsignedInteger('views_count')->default(0);
                $table->timestamps();

                $table->index('kategori');
                $table->index(['is_published', 'published_at']);
            });
        }

        // 3. Review & Testimoni Pelanggan Terverifikasi
        if (! Schema::hasTable('web_reviews')) {
            Schema::create('web_reviews', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pelanggan_id');
                $table->enum('tipe_ulasan', ['produk', 'servis']);

                // Target Reference
                $table->unsignedBigInteger('produk_id')->nullable();
                $table->unsignedBigInteger('transaksi_id')->nullable();
                $table->unsignedBigInteger('tiket_servis_id')->nullable();

                $table->unsignedTinyInteger('rating'); // 1 - 5
                $table->string('judul_ulasan', 150)->nullable();
                $table->text('isi_ulasan');

                // Media Bukti (Array Gambar / GIF)
                $table->json('media_foto')->nullable();

                // Status & Moderasi Backoffice
                $table->boolean('is_verified_purchase')->default(true);
                $table->boolean('is_approved')->default(false);
                $table->boolean('is_featured')->default(false);
                $table->text('tanggapan_admin')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                $table->index(['produk_id', 'is_approved']);
                $table->index(['tiket_servis_id', 'is_approved']);
                $table->index(['is_featured', 'is_approved']);
            });
        }

        // 4. FAQ Interaktif & Kebijakan Garansi
        if (! Schema::hasTable('web_faqs')) {
            Schema::create('web_faqs', function (Blueprint $table) {
                $table->id();
                $table->string('kategori', 50)->default('garansi');
                $table->string('pertanyaan', 255);
                $table->text('jawaban');
                $table->unsignedInteger('urutan')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['kategori', 'is_active']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_faqs');
        Schema::dropIfExists('web_reviews');
        Schema::dropIfExists('web_articles');

        Schema::table('web_banners', function (Blueprint $table) {
            $table->dropColumn(['tipe_media', 'video_desktop', 'video_mobile']);
        });
    }
};
