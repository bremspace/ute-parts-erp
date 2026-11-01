<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jurnal_header', function (Blueprint $table) {
            $table->boolean('is_reversed')->default(false)->after('user_id');
            $table->string('reversed_by_no_jurnal', 50)->nullable()->after('is_reversed');
            $table->string('reversal_of_no_jurnal', 50)->nullable()->after('reversed_by_no_jurnal');
            $table->timestamp('reversed_at')->nullable()->after('reversal_of_no_jurnal');
            $table->foreignId('reversed_by_user_id')->nullable()->after('reversed_at')->constrained('users')->nullOnDelete();

            $table->index('is_reversed');
            $table->index('reversal_of_no_jurnal');
        });
    }

    public function down(): void
    {
        Schema::table('jurnal_header', function (Blueprint $table) {
            $table->dropIndex(['is_reversed']);
            $table->dropIndex(['reversal_of_no_jurnal']);
            $table->dropForeign(['reversed_by_user_id']);
            $table->dropColumn([
                'is_reversed',
                'reversed_by_no_jurnal',
                'reversal_of_no_jurnal',
                'reversed_at',
                'reversed_by_user_id',
            ]);
        });
    }
};
