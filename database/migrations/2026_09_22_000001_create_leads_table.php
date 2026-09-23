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
        Schema::create('leads', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('cabang_id')->constrained('cabang')->cascadeOnDelete();
            $table->string('sumber'); // walkin, phone, website, referral, social_media, marketplace, lain
            $table->enum('stage', ['baru', 'kontak', 'kualifikasi', 'negosiasi', 'won', 'lost'])->default('baru');
            $table->string('nama');
            $table->string('telepon')->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->decimal('nilai_estimasi', 15, 2)->default(0);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->string('lost_reason')->nullable(); // alasan lost: harga, kompetitor, tidak_butuh, lain
            $table->foreignId('pelanggan_id')->nullable()->constrained('pelanggan')->nullOnDelete();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->timestamps();

            $table->index(['cabang_id', 'stage']);
            $table->index(['cabang_id', 'assigned_to']);
            $table->index(['cabang_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
