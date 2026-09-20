<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garansi', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('tiket_servis_id')->constrained('tiket_servis')->cascadeOnDelete();
            $table->integer('durasi_hari')->default(30);
            $table->date('tanggal_mulai');
            $table->date('tanggal_berakhir');
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['tiket_servis_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garansi');
    }
};
