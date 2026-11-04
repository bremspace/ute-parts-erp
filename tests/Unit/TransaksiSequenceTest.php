<?php

namespace Tests\Unit;

use App\Models\User;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Services\TransaksiSequenceService;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransaksiSequenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_no_transaksi_sequential_and_unique(): void
    {
        $user = User::factory()->create();

        $cabang = Cabang::create([
            'kode' => 'CB1',
            'nama' => 'Cabang Test 1',
            'is_aktif' => true,
        ]);

        $service = new TransaksiSequenceService;

        $no1 = $service->generateNoTransaksi($cabang->id);
        $this->assertMatchesRegularExpression('/^TRX-C0?1-\d{8}-0001$/', $no1);

        Transaksi::create([
            'no_transaksi' => $no1,
            'cabang_id' => $cabang->id,
            'kasir_id' => $user->id,
            'subtotal' => 10000,
            'diskon_persen' => 0,
            'diskon_nominal' => 0,
            'pajak' => 0,
            'total' => 10000,
            'bayar' => 10000,
            'kembalian' => 0,
            'metode_bayar' => 'cash',
            'status' => 'selesai',
        ]);

        $no2 = $service->generateNoTransaksi($cabang->id);
        $this->assertMatchesRegularExpression('/^TRX-C0?1-\d{8}-0002$/', $no2);
    }
}
