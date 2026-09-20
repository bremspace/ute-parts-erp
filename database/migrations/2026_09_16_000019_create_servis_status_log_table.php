<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servis_status_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('tiket_servis_id')->constrained('tiket_servis')->cascadeOnDelete();
            $table->string('status_dari')->nullable();
            $table->string('status_ke');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('aksi')->default('transisi'); // transisi, approve, reject, override
            $table->text('alasan')->nullable();
            $table->timestamps();

            $table->index(['tiket_servis_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servis_status_log');
    }
};