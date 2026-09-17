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
        Schema::create('gudang', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('cabang_id')->constrained('cabang')->cascadeOnDelete();
            $table->string('nama');
            $table->string('kode')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['cabang_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gudang');
    }
};
