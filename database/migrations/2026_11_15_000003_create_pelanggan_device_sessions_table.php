<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sesi perangkat MEMBER (guard `customer`).
     *
     * Tabel terpisah dari `device_sessions` (milik staf) karena kolom
     * `device_sessions.user_id` NOT NULL + FK ke `users`, dan project ini
     * tanpa doctrine/dbal — mengubah nullability lewat Schema::change()
     * tidak bisa dijamin lintas MySQL/SQLite (test memakai SQLite).
     */
    public function up(): void
    {
        Schema::create('pelanggan_device_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pelanggan_id')->constrained('pelanggan')->cascadeOnDelete();
            $table->string('device_name');
            $table->string('device_token')->unique();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_activity');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['pelanggan_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pelanggan_device_sessions');
    }
};
