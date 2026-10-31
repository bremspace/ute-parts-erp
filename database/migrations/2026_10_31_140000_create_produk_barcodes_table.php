<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('produk_barcodes')) {
            Schema::create('produk_barcodes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('produk_id');
                $table->string('sku', 100)->nullable()->index();
                $table->string('barcode', 150)->index();
                $table->string('label', 50)->default('barcode_1')->index();
                $table->unsignedInteger('urutan')->default(1)->index();
                $table->boolean('is_utama')->default(false)->index();
                $table->timestamps();

                $table->foreign('produk_id')->references('id')->on('produk')->onDelete('cascade');
                $table->index(['produk_id', 'urutan']);
                $table->index(['barcode', 'sku']);
            });
        }

        // Backfill data barcode eksisting dari tabel produk
        if (Schema::hasTable('produk') && Schema::hasTable('produk_barcodes')) {
            DB::table('produk')
                ->where(function ($q) {
                    $q->whereNotNull('barcode')->where('barcode', '!=', '')
                        ->orWhereNotNull('barcode_alt');
                })
                ->select(['id', 'barcode', 'barcode_alt'])
                ->chunkById(500, function ($produks) {
                    $now = now();
                    $inserts = [];
                    $produkIds = $produks->pluck('id')->all();

                    $skus = DB::table('sku_variants')
                        ->whereIn('produk_id', $produkIds)
                        ->where('is_active', true)
                        ->pluck('sku', 'produk_id')
                        ->all();

                    foreach ($produks as $p) {
                        $sku = $skus[$p->id] ?? null;
                        $barcodes = [];

                        if (! empty($p->barcode)) {
                            $barcodes[] = trim((string) $p->barcode);
                        }

                        if (! empty($p->barcode_alt)) {
                            $rawAlt = is_string($p->barcode_alt) ? json_decode($p->barcode_alt, true) : $p->barcode_alt;
                            if (is_array($rawAlt)) {
                                foreach ($rawAlt as $b) {
                                    $trimmed = trim((string) $b);
                                    if ($trimmed !== '' && ! in_array($trimmed, $barcodes, true)) {
                                        $barcodes[] = $trimmed;
                                    }
                                }
                            }
                        }

                        $urutan = 1;
                        foreach ($barcodes as $bc) {
                            $inserts[] = [
                                'produk_id' => $p->id,
                                'sku' => $sku,
                                'barcode' => $bc,
                                'label' => 'barcode_'.$urutan,
                                'urutan' => $urutan,
                                'is_utama' => $urutan === 1,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                            $urutan++;
                        }
                    }

                    if (! empty($inserts)) {
                        DB::table('produk_barcodes')->insert($inserts);
                    }
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produk_barcodes');
    }
};
