<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('channel_webhook_inbound')) {
            Schema::create('channel_webhook_inbound', function (Blueprint $table) {
                $table->id();
                $table->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
                $table->string('channel_order_id', 100)->nullable();
                $table->string('event', 50)->default('order');
                $table->json('payload');
                $table->string('status', 20)->default('queued'); // queued | processing | processed | failed
                $table->text('error')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index(['channel_id', 'channel_order_id']);
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_webhook_inbound');
    }
};
