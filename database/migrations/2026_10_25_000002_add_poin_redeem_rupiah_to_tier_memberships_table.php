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
        Schema::table('tier_memberships', function (Blueprint $table) {
            $table->decimal('poin_redeem_rupiah', 15, 2)->nullable()->after('poin_multiplier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tier_memberships', function (Blueprint $table) {
            $table->dropColumn('poin_redeem_rupiah');
        });
    }
};
