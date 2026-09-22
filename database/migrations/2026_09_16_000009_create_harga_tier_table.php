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
        Schema::create('harga_tier', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->foreignId('tier_membership_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_reseller')->default(false);
            $table->decimal('harga', 15, 2);
            $table->timestamps();

            $table->index(['produk_id', 'tier_membership_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('harga_tier');
    }
};
