<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perbaikan gratis dalam garansi (komplain pasca-servis):
 * - `komplain_gratis` = pengajuan perbaikan gratis (diajukan saat komplain
 *   dibuat dan garansi masih berlaku)
 * - `*_proposed_by/at` = pengajuan (audit)
 * - `*_approved_by/at` = persetujuan regulator (four-eyes: tidak boleh orang
 *   yang sama dengan pengusul)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiket_servis', function (Blueprint $table) {
            if (! Schema::hasColumn('tiket_servis', 'komplain_gratis')) {
                $table->boolean('komplain_gratis')->default(false);
            }

            if (! Schema::hasColumn('tiket_servis', 'komplain_gratis_proposed_by')) {
                $table->foreignId('komplain_gratis_proposed_by')->nullable();
            }

            if (! Schema::hasColumn('tiket_servis', 'komplain_gratis_proposed_at')) {
                $table->timestamp('komplain_gratis_proposed_at')->nullable();
            }

            if (! Schema::hasColumn('tiket_servis', 'komplain_gratis_approved_by')) {
                $table->foreignId('komplain_gratis_approved_by')->nullable();
            }

            if (! Schema::hasColumn('tiket_servis', 'komplain_gratis_approved_at')) {
                $table->timestamp('komplain_gratis_approved_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tiket_servis', function (Blueprint $table) {
            foreach ([
                'komplain_gratis_approved_at',
                'komplain_gratis_approved_by',
                'komplain_gratis_proposed_at',
                'komplain_gratis_proposed_by',
                'komplain_gratis',
            ] as $column) {
                if (Schema::hasColumn('tiket_servis', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
