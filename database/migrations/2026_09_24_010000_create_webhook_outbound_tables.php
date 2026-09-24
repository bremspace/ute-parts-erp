<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * [F3-5] Webhook outbound (G-16) — endpoint + delivery log ringan.
 * Idempotent (Schema::hasTable guard) — pola create_workflow_tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tabel endpoint tujuan webhook outbound
        if (! Schema::hasTable('webhook_endpoint')) {
            Schema::create('webhook_endpoint', function (Blueprint $table) {
                $table->id();
                $table->string('nama', 100);
                $table->string('url', 500);
                $table->text('secret'); // cast 'encrypted' di model (text, BUKAN json)
                $table->json('events'); // whitelist event (array nilai WebhookEvent)
                $table->boolean('is_aktif')->default(true);
                $table->foreignId('cabang_id')->nullable()->constrained('cabang'); // null = lintas cabang
                $table->timestamp('last_delivery_at')->nullable();
                $table->string('last_status', 20)->nullable(); // delivered | retry | failed
                $table->timestamps();
                $table->index('is_aktif');
            });
        }

        // Log pengiriman ringan — TANPA payload penuh (sensibel): hash + rowid context saja
        if (! Schema::hasTable('webhook_delivery')) {
            Schema::create('webhook_delivery', function (Blueprint $table) {
                $table->id();
                $table->foreignId('webhook_endpoint_id')->constrained('webhook_endpoint')->cascadeOnDelete();
                $table->string('event', 50);
                $table->string('payload_hash', 64)->nullable(); // sha256 raw body
                $table->json('context')->nullable(); // rowid sumber (mis. transaksi_id) — bukan payload
                $table->string('status', 20)->default('pending'); // pending|delivered|retry|failed
                $table->unsignedTinyInteger('attempt')->default(0);
                $table->unsignedSmallInteger('response_code')->nullable();
                $table->text('error')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();
                $table->index('status');
                $table->index('event');
            });
        }

        // Permission kelola-webhook (idempotent — pola atur-harga-fleksibel)
        $permission = Permission::firstOrCreate([
            'name' => 'kelola-webhook',
            'guard_name' => 'web',
        ]);
        foreach (['super-admin', 'admin-toko'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role && ! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (Schema::hasTable('webhook_delivery')) {
            Schema::dropIfExists('webhook_delivery');
        }
        if (Schema::hasTable('webhook_endpoint')) {
            Schema::dropIfExists('webhook_endpoint');
        }
        // Permission dibiarkan (idempoten, tidak merusak role lain)
    }
};
