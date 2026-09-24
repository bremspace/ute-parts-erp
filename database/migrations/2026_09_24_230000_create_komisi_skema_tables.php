<?php

use App\Modules\Reseller\Services\KomisiService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * [F3-8c] Generalisasi komisi → multi-aktor (PRD §4.3):
     * - komisi_skema: rule configurable (karyawan/reseller/agen × penjualan/lead_won/tiket_servis/target_kpi).
     * - komisi: + aktor_tipe/aktor_id/komisi_skema_id/lead_id/tiket_servis_id/idempotensi_key
     *   (kompatibilitas lama via default aktor_tipe 'reseller'); pelanggan_id → nullable
     *   (komisi karyawan internal tanpa pelanggan).
     * - MIGRASI DATA: skema reseller lama (skema_komisi + skema_komisi_reseller) →
     *   seed rule baru (idempotent, paritas diuji test).
     */
    public function up(): void
    {
        if (! Schema::hasTable('komisi_skema')) {
            Schema::create('komisi_skema', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('nama');
                $table->string('aktor_tipe', 20)->default('reseller'); // karyawan|reseller|agen
                $table->unsignedBigInteger('aktor_id')->nullable(); // null = rule umum tipe aktor
                $table->string('trigger_tipe', 30)->default('penjualan'); // penjualan|lead_won|tiket_servis|target_kpi
                $table->string('kategori')->nullable(); // null = semua kategori (paritas skema lama)
                $table->string('tipe', 20); // persen|nominal
                $table->decimal('nilai', 15, 2);
                $table->decimal('min_amount', 15, 2)->default(0);
                $table->foreignId('cabang_id')->nullable()->constrained('cabang')->nullOnDelete();
                $table->boolean('is_aktif')->default(true);
                $table->timestamps();

                $table->index(['aktor_tipe', 'trigger_tipe', 'is_aktif']);
            });
        }

        if (Schema::hasTable('komisi')) {
            Schema::table('komisi', function (Blueprint $table) {
                $table->string('aktor_tipe', 20)->default('reseller')->after('skema_komisi_id');
                $table->unsignedBigInteger('aktor_id')->nullable()->after('aktor_tipe');
                $table->foreignId('komisi_skema_id')->nullable()->after('aktor_id')->constrained('komisi_skema')->nullOnDelete();
                $table->foreignId('lead_id')->nullable()->after('komisi_skema_id')->constrained('leads')->nullOnDelete();
                $table->foreignId('tiket_servis_id')->nullable()->after('lead_id')->constrained('tiket_servis')->nullOnDelete();
                $table->string('idempotensi_key', 160)->nullable()->unique()->after('tiket_servis_id');
            });

            // Komisi karyawan internal tanpa pelanggan → pelanggan_id nullable
            Schema::table('komisi', function (Blueprint $table) {
                $table->foreignId('pelanggan_id')->nullable()->change();
            });
        }

        // MIGRASI DATA: skema reseller lama → komisi_skema (idempotent)
        if (Schema::hasTable('skema_komisi')) {
            app(KomisiService::class)->migrasiSkemaResellerKeRuleBaru();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('komisi')) {
            Schema::table('komisi', function (Blueprint $table) {
                $table->dropColumn([
                    'aktor_tipe', 'aktor_id', 'komisi_skema_id', 'lead_id', 'tiket_servis_id', 'idempotensi_key',
                ]);
            });
        }
        Schema::dropIfExists('komisi_skema');
    }
};
