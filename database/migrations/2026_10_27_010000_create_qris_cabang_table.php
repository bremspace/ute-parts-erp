<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qris_cabang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->nullable()->unique()->constrained('cabang')->nullOnDelete();
            $table->text('payload_static');
            $table->string('merchant_name');
            $table->string('merchant_city')->default('JAKARTA');
            $table->string('postal_code')->nullable();
            $table->string('nmid')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qris_cabang');
    }
};
