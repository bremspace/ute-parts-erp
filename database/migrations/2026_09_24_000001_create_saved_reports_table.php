<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('saved_reports')) {
            // [P2-4] Idempotent: tabel sudah terlanjur dibuat tanpa kolom shared
            // (migrasi ini NEW/untracked — pastikan kolom ditambahkan bila belum ada).
            if (! Schema::hasColumn('saved_reports', 'shared')) {
                Schema::table('saved_reports', function (Blueprint $table) {
                    $table->boolean('shared')->nullable()->default(false);
                });
            }

            return;
        }

        Schema::create('saved_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('source_model');
            $table->json('columns');
            $table->json('filters')->nullable();
            $table->json('group_by')->nullable();
            $table->string('export_format')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('cabang_id')->nullable();
            // [P2-4] true = ikut tampil di cabang (untuk user lain dengan cabang sama)
            $table->boolean('shared')->nullable()->default(false);
            $table->timestamps();

            $table->index(['user_id', 'source_model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_reports');
    }
};
