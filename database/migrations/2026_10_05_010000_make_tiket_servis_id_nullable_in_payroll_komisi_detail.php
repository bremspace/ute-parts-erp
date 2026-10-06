<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_komisi_detail')) {
            Schema::table('payroll_komisi_detail', function (Blueprint $table) {
                $table->unsignedBigInteger('tiket_servis_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_komisi_detail')) {
            Schema::table('payroll_komisi_detail', function (Blueprint $table) {
                $table->unsignedBigInteger('tiket_servis_id')->nullable(false)->change();
            });
        }
    }
};
