<?php

use App\Modules\Workflow\Models\ApprovalRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('grn')) {
            Schema::create('grn', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cabang_id')->constrained('cabang');
                $table->foreignId('po_id')->constrained('purchase_order');
                $table->foreignId('gudang_id')->constrained('gudang');
                $table->foreignId('user_id')->constrained('users');
                $table->string('no_grn')->unique();
                $table->decimal('total_hpp', 14, 2)->default(0);
                $table->enum('status', ['draft', 'disetujui', 'ditolak', 'terima'])->default('draft');
                $table->json('item_qty_received'); // per item: {produk_id, qty_received, qty_po, status}
                $table->text('catatan')->nullable();
                $table->timestamps();

                $table->index(['po_id', 'cabang_id']);
                $table->index(['status']);
            });
        }

        // [F2-2] Rule approval GRN (selisih qty partial/tolak) — idempoten, pola seed_workflow_approval_rules
        if (Schema::hasTable('approval_rules')) {
            ApprovalRule::firstOrCreate(
                ['entity_type' => 'grn', 'cabang_id' => null],
                ['min_amount' => 0, 'max_amount' => null, 'approver_role' => 'admin-toko', 'level' => 1, 'is_aktif' => true]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('grn')) {
            Schema::dropIfExists('grn');
        }
    }
};
