<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Goodwill: kompensasi diskon untuk kasus ambigu (mis. komplain dengan
 * penyebab yang tidak jelas antara garansi vs konsumen). Hanya berlaku bila
 * disetujui regulator, four-eyes dengan pengusul.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiket_servis', function (Blueprint $table) {
            if (! Schema::hasColumn('tiket_servis', 'goodwill_amount')) {
                $table->decimal('goodwill_amount', 12, 2)->nullable();
            }

            if (! Schema::hasColumn('tiket_servis', 'goodwill_alasan')) {
                $table->string('goodwill_alasan')->nullable();
            }

            if (! Schema::hasColumn('tiket_servis', 'goodwill_proposed_by')) {
                $table->foreignId('goodwill_proposed_by')->nullable();
            }

            if (! Schema::hasColumn('tiket_servis', 'goodwill_proposed_at')) {
                $table->timestamp('goodwill_proposed_at')->nullable();
            }

            if (! Schema::hasColumn('tiket_servis', 'goodwill_approved_by')) {
                $table->foreignId('goodwill_approved_by')->nullable();
            }

            if (! Schema::hasColumn('tiket_servis', 'goodwill_approved_at')) {
                $table->timestamp('goodwill_approved_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tiket_servis', function (Blueprint $table) {
            foreach ([
                'goodwill_approved_at',
                'goodwill_approved_by',
                'goodwill_proposed_at',
                'goodwill_proposed_by',
                'goodwill_alasan',
                'goodwill_amount',
            ] as $column) {
                if (Schema::hasColumn('tiket_servis', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
