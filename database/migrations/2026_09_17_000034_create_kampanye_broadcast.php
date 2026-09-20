<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // [T-23] Kampanye broadcast + log per penerima (anti dobel + audit)
        Schema::create('kampanye_broadcast', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('judul');
            $table->text('pesan');
            $table->string('channel'); // wa, email, inapp
            $table->json('segment')->nullable(); // [{tipe: tier|filter, nilai}]
            $table->string('status')->default('draft'); // draft, terjadwal, terkirim, gagal
            $table->timestamp('dijadwalkan_at')->nullable();
            $table->timestamp('dikirim_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('total_target')->default(0);
            $table->integer('total_terkirim')->default(0);
            $table->integer('total_gagal')->default(0);
            $table->timestamps();
        });

        Schema::table('notifikasi_keluar', function (Blueprint $table) {
            $table->unsignedBigInteger('kampanye_broadcast_id')->nullable()->after('tipe');
            $table->foreign('kampanye_broadcast_id')->references('id')->on('kampanye_broadcast')->nullOnDelete();
            $table->index('kampanye_broadcast_id');
        });
    }

    public function down(): void
    {
        Schema::table('notifikasi_keluar', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kampanye_broadcast_id');
        });
        Schema::dropIfExists('kampanye_broadcast');
    }
};
