<?php

use App\Modules\Workflow\Models\ApprovalRule;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ApprovalRule::firstOrCreate(
            ['entity_type' => 'reset_password_pelanggan', 'level' => 1],
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
        ApprovalRule::where('entity_type', 'reset_password_pelanggan')->delete();
    }
};
