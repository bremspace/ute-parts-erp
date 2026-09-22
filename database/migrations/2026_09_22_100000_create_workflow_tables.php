<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel approval_rules
        if (!Schema::hasTable('approval_rules')) {
            Schema::create('approval_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cabang_id')->nullable()->constrained('cabang');
                $table->string('entity_type', 50);
                $table->decimal('min_amount', 14, 2)->nullable();
                $table->decimal('max_amount', 14, 2)->nullable();
                $table->string('approver_role', 50);
                $table->unsignedTinyInteger('level')->default(1);
                $table->boolean('is_aktif')->default(true);
                $table->timestamps();
                $table->index(['entity_type', 'is_aktif']);
            });
        }

        // Tabel approval_requests
        if (!Schema::hasTable('approval_requests')) {
            Schema::create('approval_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('approval_rule_id')->constrained('approval_rules');
                $table->string('entity_type', 50);
                $table->bigInteger('entity_id');
                $table->foreignId('cabang_id')->nullable()->constrained('cabang');
                $table->json('payload_json');
                $table->string('status', 20)->default('pending');
                $table->foreignId('requested_by')->constrained('users');
                $table->foreignId('actioned_by')->nullable()->constrained('users');
                $table->timestamp('actioned_at')->nullable();
                $table->string('catatan', 255)->nullable();
                $table->string('approver_role', 50)->nullable();
                $table->timestamps();
                $table->unique(['entity_type', 'entity_id', 'approval_rule_id']);
                $table->index(['status']);
            });
        }

        // Seed default approval_rules idempoten
        $poRule = \Spatie\Permission\Models\Permission::firstOrCreate([
            'name' => 'approve-workflow',
            'guard_name' => 'web',
        ]);
        $superAdminRole = \Spatie\Permission\Models\Role::where('name', 'super-admin')->first();
        if ($superAdminRole && ! $superAdminRole->hasPermissionTo($poRule)) {
            $superAdminRole->givePermissionTo($poRule);
        }
        $financeRole = \Spatie\Permission\Models\Role::where('name', 'finance')->first();
        if ($financeRole && ! $financeRole->hasPermissionTo($poRule)) {
            $financeRole->givePermissionTo($poRule);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Insert default rules idempoten
        $rules = [
            [
                'cabang_id' => null,
                'entity_type' => 'po',
                'min_amount' => 10000000,
                'max_amount' => null,
                'approver_role' => 'finance',
                'level' => 1,
                'is_aktif' => true,
            ],
            [
                'cabang_id' => null,
                'entity_type' => 'retur',
                'min_amount' => 5000000,
                'max_amount' => null,
                'approver_role' => 'super-admin',
                'level' => 1,
                'is_aktif' => true,
            ],
        ];
        foreach ($rules as $rule) {
            \App\Modules\Workflow\Models\ApprovalRule::firstOrCreate(
                ['entity_type' => $rule['entity_type'], 'cabang_id' => $rule['cabang_id']],
                $rule
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('approval_requests')) {
            Schema::dropIfExists('approval_requests');
        }
        if (Schema::hasTable('approval_rules')) {
            Schema::dropIfExists('approval_rules');
        }
    }
};