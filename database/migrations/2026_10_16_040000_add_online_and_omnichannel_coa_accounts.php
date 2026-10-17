<?php

use App\Modules\Akunting\Models\AkunCOA;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $accounts = [
            // Aset lancar / Kliring & Saldo Channel
            ['kode' => '120-02', 'nama' => 'Piutang / Kliring Duitku Gateway', 'tipe' => 'aset', 'kelompok' => 'piutang', 'saldo_normal' => 'debit'],
            ['kode' => '120-03', 'nama' => 'Piutang / Saldo Channel Shopee', 'tipe' => 'aset', 'kelompok' => 'piutang', 'saldo_normal' => 'debit'],
            ['kode' => '120-04', 'nama' => 'Piutang / Saldo Channel Tokopedia & Lainnya', 'tipe' => 'aset', 'kelompok' => 'piutang', 'saldo_normal' => 'debit'],

            // Pendapatan Online & Omnichannel
            ['kode' => '410-03', 'nama' => 'Pendapatan Penjualan Web Marketplace Ute', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '410-04', 'nama' => 'Pendapatan Penjualan Omnichannel', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit'],

            // Beban Platform & Payment Gateway
            ['kode' => '520-06', 'nama' => 'Beban Biaya Admin Marketplace', 'tipe' => 'beban', 'kelompok' => 'biaya_marketplace', 'saldo_normal' => 'debit'],
            ['kode' => '520-10', 'nama' => 'Beban Fee Transaksi Payment Gateway', 'tipe' => 'beban', 'kelompok' => 'beban_operasional', 'saldo_normal' => 'debit'],
        ];

        foreach ($accounts as $acc) {
            AkunCOA::firstOrCreate(
                ['kode' => $acc['kode']],
                [
                    'nama' => $acc['nama'],
                    'tipe' => $acc['tipe'],
                    'kelompok' => $acc['kelompok'],
                    'saldo_normal' => $acc['saldo_normal'],
                    'is_active' => true,
                ]
            );
        }
    }

    public function down(): void
    {
        // Tetap dipertahankan untuk integritas data akuntansi
    }
};
