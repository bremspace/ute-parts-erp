<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Lengkapi profil supplier
        if (Schema::hasTable('supplier')) {
            Schema::table('supplier', function (Blueprint $table) {
                if (! Schema::hasColumn('supplier', 'email')) {
                    $table->string('email', 191)->nullable()->after('telepon');
                }
                if (! Schema::hasColumn('supplier', 'bank_nama')) {
                    $table->string('bank_nama', 100)->nullable()->after('saldo_deposit');
                }
                if (! Schema::hasColumn('supplier', 'nomor_rekening')) {
                    $table->string('nomor_rekening', 100)->nullable()->after('bank_nama');
                }
                if (! Schema::hasColumn('supplier', 'nama_rekening')) {
                    $table->string('nama_rekening', 191)->nullable()->after('nomor_rekening');
                }
                if (! Schema::hasColumn('supplier', 'catatan')) {
                    $table->text('catatan')->nullable()->after('nama_rekening');
                }
            });
        }

        // 2. Buat tabel mutasi riwayat saldo/deposit supplier
        if (! Schema::hasTable('supplier_saldo_mutasi')) {
            Schema::create('supplier_saldo_mutasi', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('supplier_id');
                $table->unsignedBigInteger('cabang_id')->nullable();
                $table->string('referensi_tipe', 100)->nullable();
                $table->unsignedBigInteger('referensi_id')->nullable();
                $table->enum('jenis', ['masuk', 'keluar']);
                $table->decimal('jumlah', 15, 2)->default(0);
                $table->decimal('saldo_sebelum', 15, 2)->default(0);
                $table->decimal('saldo_setelah', 15, 2)->default(0);
                $table->text('keterangan')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();

                $table->foreign('supplier_id')->references('id')->on('supplier')->cascadeOnDelete();
                $table->index(['supplier_id', 'created_at']);
                $table->index(['referensi_tipe', 'referensi_id']);
            });
        }

        // 3. Tambah tracking status pengiriman & penyelesaian pada return_pembelian
        if (Schema::hasTable('return_pembelian')) {
            Schema::table('return_pembelian', function (Blueprint $table) {
                if (! Schema::hasColumn('return_pembelian', 'tanggal_kirim')) {
                    $table->dateTime('tanggal_kirim')->nullable()->after('tanggal');
                }
                if (! Schema::hasColumn('return_pembelian', 'tanggal_selesai')) {
                    $table->dateTime('tanggal_selesai')->nullable()->after('tanggal_kirim');
                }
                if (! Schema::hasColumn('return_pembelian', 'catatan_penyelesaian')) {
                    $table->text('catatan_penyelesaian')->nullable()->after('alasan');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('return_pembelian')) {
            Schema::table('return_pembelian', function (Blueprint $table) {
                $table->dropColumn(['tanggal_kirim', 'tanggal_selesai', 'catatan_penyelesaian']);
            });
        }

        Schema::dropIfExists('supplier_saldo_mutasi');

        if (Schema::hasTable('supplier')) {
            Schema::table('supplier', function (Blueprint $table) {
                $table->dropColumn(['email', 'bank_nama', 'nomor_rekening', 'nama_rekening', 'catatan']);
            });
        }
    }
};
