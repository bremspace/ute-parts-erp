<?php

use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\TipeHp;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Sinkronisasi master Brands dengan data brand_kompatibel produk
        $brandsInProduk = DB::table('produk')
            ->whereNotNull('brand_kompatibel')
            ->where('brand_kompatibel', '!=', '')
            ->distinct()
            ->pluck('brand_kompatibel');

        foreach ($brandsInProduk as $brandNama) {
            $brand = Brand::firstOrCreate(['nama' => $brandNama], ['is_active' => true]);
            DB::table('produk')
                ->where('brand_kompatibel', $brandNama)
                ->where(function ($q) use ($brand) {
                    $q->whereNull('brand_id')->orWhere('brand_id', '!=', $brand->id);
                })
                ->update(['brand_id' => $brand->id]);
        }

        // 2. Rapikan hierarki parent_id kategori SID agar masuk ke Kategori Induk standar
        $mapToStandard = [
            2 => ['LCD', 'TS', 'TOUCHSCREEN', 'TOUCH SCREEN', 'DISPLAY'],
            3 => ['BATTERY', 'BATERAI', 'BATRE'],
            4 => ['FLEX', 'FLEKSIBEL', 'BOARD', 'PCB', 'KEYBOARD', 'RAGUM', 'FINGERPRINT', 'FACE ID', 'SWITCH'],
            5 => ['CAMERA', 'KAMERA', 'CAM'],
            6 => ['IC', 'CHIPSET', 'WICK SOLDER', 'SOLDER WICK'],
            7 => ['SPEAKER', 'BUZZER'],
            8 => ['BACKDOOR', 'BACK DOOR', 'BACK COVER', 'BEZZEL', 'FRAME', 'HOUSING', 'CASING', 'GLASS + FRAME', 'BUTTON VOLUME', 'TOMBOL LUAR', 'GLASS ONLY', 'MIDDLE'],
            9 => ['SIM TRAY', 'SIM TRY', 'KONEKTOR', 'CONNECTOR', 'PLUG'],
            10 => ['TOOL', 'OBENG', 'SOLDER', 'BLOWER', 'LEM', 'TINER', 'APOMETER', 'TESTER', 'MULTITESTER', 'CAIRAN PEMBERSIH', 'FLUX', 'PINSET', 'TANG POTONG', 'DOUBLE TAPE', 'JUMPIRE WIRE', 'SOLATIP ANTI PANAS', 'PASTE', 'TIMAH', 'ISOLASI'],
            15 => ['ANTIGORES', 'TEMPERED GLASS', 'TG', 'KABEL', 'CHARGER', 'ADAPTOR', 'HEADSET', 'HANDSFREE', 'EARPHONE', 'TWS', 'POWER BANK', 'PRIVACY', 'CLEAR/BENING', 'PHONE HOLDER', 'MOUSE'],
        ];

        $catsUnderUmum = KategoriProduk::where('parent_id', 19)->get();
        foreach ($catsUnderUmum as $c) {
            $upper = strtoupper($c->nama);
            foreach ($mapToStandard as $targetParentId => $keywords) {
                foreach ($keywords as $kw) {
                    if (str_contains($upper, $kw)) {
                        $c->parent_id = $targetParentId;
                        $c->save();
                        break 2;
                    }
                }
            }
        }

        // 3. Tautkan model HP spesifik pada relasi pivot dan model_kompatibel produk
        $allTipeHp = TipeHp::whereNotIn('model', ['Universal', 'Semua Tipe HP'])->get();
        foreach ($allTipeHp as $th) {
            $matchingProduks = Produk::where('nama', 'like', '%'.$th->model.'%')->get();
            foreach ($matchingProduks as $p) {
                $exists = DB::table('kompatibilitas_produk_tipe_hp')
                    ->where('produk_id', $p->id)
                    ->where('tipe_hp_id', $th->id)
                    ->exists();

                if (! $exists) {
                    DB::table('kompatibilitas_produk_tipe_hp')->insert([
                        'produk_id' => $p->id,
                        'tipe_hp_id' => $th->id,
                    ]);
                }

                $curJson = is_array($p->kompatibilitas_hp) ? $p->kompatibilitas_hp : (json_decode($p->kompatibilitas_hp ?? '[]', true) ?: []);
                $hasModel = false;
                foreach ($curJson as $item) {
                    if (($item['model'] ?? '') === $th->model) {
                        $hasModel = true;
                        break;
                    }
                }
                if (! $hasModel) {
                    $curJson[] = ['merk' => $th->merk, 'model' => $th->model];
                    $p->kompatibilitas_hp = $curJson;
                    if (! $p->model_kompatibel || $p->model_kompatibel === 'Universal') {
                        $p->model_kompatibel = $th->model;
                    }
                    $p->save();
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down needed for data alignment
    }
};
