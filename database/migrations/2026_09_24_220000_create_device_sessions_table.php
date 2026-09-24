<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('device_sessions')) {
            Schema::create('device_sessions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('device_name');
                $table->string('device_token')->unique();
                $table->string('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_activity');
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('device_sessions');
    }
};
