<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Audit log umum (PRD §6): who, when, what, before/after.
        // Mencatat perubahan harga, stok manual, approval komisi, override status servis, user, tier, COA.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entitas');       // Produk, HargaTier, AkunCOA, StokItem, Komisi, TiketServis, User, TierMembership
            $table->string('aksi');          // create, update, approve, reject, override, adjust, delete
            $table->unsignedBigInteger('entitas_id')->nullable();
            $table->text('deskripsi');
            $table->json('sebelum')->nullable();  // snapshot before
            $table->json('sesudah')->nullable();  // snapshot after
            $table->string('ip')->nullable();
            $table->timestamps();

            $table->index(['entitas', 'entitas_id']);
            $table->index(['user_id']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};