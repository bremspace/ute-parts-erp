<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_scores')) {
            Schema::create('supplier_scores', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('supplier_id')->constrained('supplier')->cascadeOnDelete();
                $table->foreignId('cabang_id')->nullable()->constrained('cabang')->nullOnDelete();
                $table->string('periode'); // YYYY-MM
                $table->decimal('on_time_percent', 8, 2)->default(0);
                $table->decimal('quality_return_percent', 8, 2)->default(0);
                $table->decimal('avg_harga', 15, 2)->default(0);
                $table->decimal('total_score', 8, 2)->default(0);
                $table->timestamps();

                $table->unique(['supplier_id', 'periode', 'cabang_id']);
                $table->index(['periode', 'cabang_id', 'total_score']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_scores');
    }
};
