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
        Schema::create('pelanggan', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nama');
            $table->string('telepon')->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->text('alamat')->nullable();
            $table->foreignId('tier_membership_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_reseller')->default(false);
            $table->decimal('total_belanja_12bulan', 15, 2)->default(0);
            $table->integer('poin_loyalty')->default(0);
            $table->string('password')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestamps();

            $table->index(['tier_membership_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pelanggan');
    }
};
