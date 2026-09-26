<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [B-10a / P0-3] Header jurnal akuntansi (1 baris per entri double-entry).
 *
 * Latar belakang: `jurnal_akuntansi` bersifat line-based (1 entri = N baris),
 * sehingga `no_jurnal` yang sama selalu berulang dan tidak bisa punya unique index
 * (live: ±167 baris / ±71 entri). Idempotensi `post()` sebelumnya hanya dicek
 * dengan `exists()` di LUAR transaction → race (dua request bersamaan bisa dua-dua
 * lolos dan menggandakan baris). Counter `generateNoJurnal()` juga `count()+1`
 * tanpa lock.
 *
 * Solusi: tabel header dengan unique key sehingga idempotensi ditegakkan oleh
 * MESIN DB (atomic), dan `post()` mengunci counter/header sebelum menulis header +
 * lines dalam satu transaction.
 *
 * Catatan index: `cabang_id` nullable, tapi MySQL memperlakukan NULL sebagai
 * distinct di unique index (dua jurnal tanpa cabang tidak akan bentrok). Karena itu
 * dipakai kolom bantu `cabang_key` NOT NULL = `cabang_id ?? 0`, dan unique index
 * dipasang pada `(cabang_key, no_jurnal)`.
 *
 * `idempotency_key` nullable: NULL distinct di MySQL sehingga hanya kunci yang
 * terisi yang ikut dide-dup (mencegah pembayaran ganda pada kunci yang sama).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurnal_header', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('no_jurnal', 40);
            $table->foreignId('cabang_id')->nullable()->constrained('cabang')->nullOnDelete();
            // cabang_id ?? 0 — NOT NULL supaya unique index benar-benar ditegakkan
            $table->unsignedBigInteger('cabang_key')->default(0);
            $table->timestamp('tanggal');
            $table->string('sumber');
            $table->string('deskripsi');
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('total_kredit', 15, 2)->default(0);
            $table->unsignedSmallInteger('jumlah_baris')->default(0);
            $table->string('referensi_tipe')->nullable();
            $table->unsignedBigInteger('referensi_id')->nullable();
            $table->string('idempotency_key', 120)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // [P0-3] idempotensi atomic — inti perbaikan
            $table->unique(['cabang_key', 'no_jurnal'], 'jurnal_header_cabang_no_jurnal_unique');
            $table->unique(['cabang_key', 'idempotency_key'], 'jurnal_header_cabang_idempotency_unique');
            $table->index(['no_jurnal']);
            $table->index(['referensi_tipe', 'referensi_id']);
            $table->index(['cabang_id', 'tanggal']);
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('jurnal_header');
    }

    /**
     * Backfill idempoten: satu header per (cabang_id, no_jurnal) dari baris
     * jurnal existing. Tidak mengubah nomor jurnal yang sudah ada.
     */
    private function backfill(): void
    {
        if (! Schema::hasTable('jurnal_akuntansi')) {
            return;
        }

        $rows = [];

        DB::table('jurnal_akuntansi')
            ->select([
                'no_jurnal',
                'cabang_id',
                DB::raw('MIN(tanggal) as tanggal'),
                DB::raw('MIN(sumber) as sumber'),
                DB::raw('MIN(deskripsi) as deskripsi'),
                DB::raw('SUM(debit) as total_debit'),
                DB::raw('SUM(kredit) as total_kredit'),
                DB::raw('COUNT(*) as jumlah_baris'),
                DB::raw('MIN(referensi_tipe) as referensi_tipe'),
                DB::raw('MIN(referensi_id) as referensi_id'),
                DB::raw('MIN(user_id) as user_id'),
            ])
            ->groupBy('no_jurnal', 'cabang_id')
            ->orderBy('no_jurnal')
            ->chunk(200, function ($chunk) use (&$rows) {
                foreach ($chunk as $row) {
                    $rows[] = [
                        'no_jurnal' => $row->no_jurnal,
                        'cabang_id' => $row->cabang_id,
                        'cabang_key' => (int) ($row->cabang_id ?? 0),
                        'tanggal' => $row->tanggal,
                        'sumber' => (string) ($row->sumber ?? 'manual'),
                        'deskripsi' => (string) ($row->deskripsi ?? 'Jurnal '.$row->no_jurnal),
                        'total_debit' => $row->total_debit,
                        'total_kredit' => $row->total_kredit,
                        'jumlah_baris' => (int) $row->jumlah_baris,
                        'referensi_tipe' => $row->referensi_tipe,
                        'referensi_id' => $row->referensi_id === null ? null : (int) $row->referensi_id,
                        'idempotency_key' => null,
                        'user_id' => $row->user_id === null ? null : (int) $row->user_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                // insertOrIgnore -> aman dijalankan ulang (idempoten)
                DB::table('jurnal_header')->insertOrIgnore($rows);
                $rows = [];
            });
    }
};
