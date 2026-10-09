<?php

use App\Modules\Workflow\Models\ApprovalRule;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);

        ApprovalRule::firstOrCreate(
            ['entity_type' => 'member', 'level' => 1],
            [
                'cabang_id' => null,
                'min_amount' => 0,
                'max_amount' => null,
                'approver_role' => 'owner',
                'is_aktif' => true,
            ]
        );
    }

    public function down(): void
    {
        ApprovalRule::where('entity_type', 'member')->delete();
    }
};
