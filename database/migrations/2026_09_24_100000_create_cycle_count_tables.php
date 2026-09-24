<?php

use App\Modules\Workflow\Models\ApprovalRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * [F3-7 / G-18] Cycle count otomatis:
     * - cycle_count_schedule: jadwal per rak/kategori (frekuensi mingguan/bulanan, hari/jam, cabang)
     * - cycle_count_task: snapshot sample item acak (seed + item terpilih) + hasil count + status
     * - rule approval entity_type 'cycle_count' (selisih MAJOR) — idempoten pola GRN (F1-1)
     */
    public function up(): void
    {
        if (! Schema::hasTable('cycle_count_schedule')) {
            Schema::create('cycle_count_schedule', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cabang_id')->constrained('cabang');
                $table->string('nama', 120)->nullable();
                $table->string('tipe_target', 20); // rak | kategori
                $table->unsignedBigInteger('target_id')->nullable(); // rak.id bila tipe_target = rak
                $table->string('target_kategori', 100)->nullable(); // produk.kategori bila tipe_target = kategori
                $table->string('frekuensi', 20); // mingguan | bulanan
                $table->unsignedTinyInteger('hari')->default(1); // mingguan: 1-7 (ISO, Senin=1); bulanan: 1-31
                $table->string('jam', 5)->default('03:00'); // HH:MM — generate hanya setelah jam ini
                $table->unsignedSmallInteger('sample_size')->default(10); // N item acak per run
                $table->unsignedInteger('threshold_unit')->default(3); // minor bila |selisih| <= Y unit
                $table->decimal('threshold_persen', 5, 2)->default(5); // atau |selisih|/stok <= X%
                $table->boolean('is_aktif')->default(true);
                $table->timestamp('last_run_at')->nullable();
                $table->timestamps();

                $table->index(['cabang_id', 'is_aktif']);
            });
        }

        if (! Schema::hasTable('cycle_count_task')) {
            Schema::create('cycle_count_task', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cycle_count_schedule_id')->constrained('cycle_count_schedule')->cascadeOnDelete();
                $table->foreignId('cabang_id')->constrained('cabang');
                $table->string('no_task')->unique();
                $table->date('tanggal');
                $table->string('tipe_target', 20); // snapshot target saat task dibuat
                $table->unsignedBigInteger('target_id')->nullable();
                $table->string('target_kategori', 100)->nullable();
                $table->string('target_label', 150)->nullable(); // label display: "Rak A-01" / "Kategori Sparepart"
                $table->unsignedBigInteger('seed'); // seed shuffle sample (reproducible audit)
                $table->json('sample_items'); // snapshot: [{stok_item_id, produk_id, gudang_id, rak_id, nama, stok_sistem}]
                $table->json('hasil')->nullable(); // hasil count: [+ stok_fisik, selisih, klasifikasi minor|major|cocok]
                $table->string('status', 30)->default('menunggu_count'); // menunggu_count, menunggu_approval, selesai, ditolak
                $table->unsignedInteger('threshold_unit')->default(3); // threshold di-snapshot dari jadwal
                $table->decimal('threshold_persen', 5, 2)->default(5);
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // penghitung
                $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('counted_at')->nullable();
                $table->timestamp('applied_at')->nullable();
                $table->text('catatan')->nullable();
                $table->timestamps();

                // Idempotensi: satu task per jadwal per hari (double-run job tidak dobel task)
                $table->unique(['cycle_count_schedule_id', 'tanggal']);
                $table->index(['cabang_id', 'status']);
            });
        }

        // [F3-7] Rule approval cycle_count (MAJOR) — idempoten, pola seed GRN create_grn_table
        if (Schema::hasTable('approval_rules')) {
            ApprovalRule::firstOrCreate(
                ['entity_type' => 'cycle_count', 'cabang_id' => null],
                ['min_amount' => 0, 'max_amount' => null, 'approver_role' => 'admin-toko', 'level' => 1, 'is_aktif' => true]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cycle_count_task')) {
            Schema::dropIfExists('cycle_count_task');
        }
        if (Schema::hasTable('cycle_count_schedule')) {
            Schema::dropIfExists('cycle_count_schedule');
        }
    }
};
