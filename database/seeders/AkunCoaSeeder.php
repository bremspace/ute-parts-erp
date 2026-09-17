<?php

namespace Database\Seeders;

use App\Modules\Akunting\Models\AkunCOA;
use Illuminate\Database\Seeder;

/**
 * Chart of Account standar retail + servis (PRD §4.6).
 * Kode akun wajib konsisten dengan JurnalService:
 *  110-01 Kas, 130-01 Persediaan, 210-03 Utang Komisi,
 *  410-01 Pendapatan Penjualan, 420-01 Pendapatan Jasa Servis,
 *  510-01 Beban Komisi, 510-02 HPP.
 */
class AkunCoaSeeder extends Seeder
{
    public function run(): void
    {
        $coa = [
            // ASET
            ['kode' => '110-01', 'nama' => 'Kas',                          'tipe' => 'aset',      'kelompok' => 'kas',         'saldo_normal' => 'debit'],
            ['kode' => '110-02', 'nama' => 'Bank',                         'tipe' => 'aset',      'kelompok' => 'bank',        'saldo_normal' => 'debit'],
            ['kode' => '120-01', 'nama' => 'Piutang Usaha',                'tipe' => 'aset',      'kelompok' => 'piutang',     'saldo_normal' => 'debit'],
            ['kode' => '130-01', 'nama' => 'Persediaan Barang Dagang',     'tipe' => 'aset',      'kelompok' => 'persediaan',  'saldo_normal' => 'debit'],
            ['kode' => '140-01', 'nama' => 'Perlengkapan Toko',            'tipe' => 'aset',      'kelompok' => 'perlengkapan','saldo_normal' => 'debit'],
            ['kode' => '150-01', 'nama' => 'Peralatan Servis',             'tipe' => 'aset',      'kelompok' => 'peralatan',   'saldo_normal' => 'debit'],
            ['kode' => '160-01', 'nama' => 'Aktiva Tetap',                 'tipe' => 'aset',      'kelompok' => 'aset_tetap',  'saldo_normal' => 'debit'],

            // KEWAJIBAN
            ['kode' => '210-01', 'nama' => 'Utang Usaha',                  'tipe' => 'kewajiban',  'kelompok' => 'utang_usaha', 'saldo_normal' => 'kredit'],
            ['kode' => '210-02', 'nama' => 'Utang Gaji',                   'tipe' => 'kewajiban',  'kelompok' => 'utang_gaji',  'saldo_normal' => 'kredit'],
            ['kode' => '210-03', 'nama' => 'Utang Komisi',                 'tipe' => 'kewajiban',  'kelompok' => 'utang_komisi','saldo_normal' => 'kredit'],
            ['kode' => '220-01', 'nama' => 'Pajak Dibayar Dimuka',         'tipe' => 'kewajiban',  'kelompok' => 'pajak',       'saldo_normal' => 'kredit'],

            // EKUITAS
            ['kode' => '310-01', 'nama' => 'Modal Pemilik',                'tipe' => 'ekuitas',   'kelompok' => 'modal',        'saldo_normal' => 'kredit'],
            ['kode' => '310-02', 'nama' => 'Laba Ditahan',                 'tipe' => 'ekuitas',   'kelompok' => 'laba_ditahan','saldo_normal' => 'kredit'],

            // PENDAPATAN
            ['kode' => '410-01', 'nama' => 'Pendapatan Penjualan',         'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '410-02', 'nama' => 'Retur Penjualan',              'tipe' => 'pendapatan', 'kelompok' => 'retur_penjualan',      'saldo_normal' => 'kredit'],
            ['kode' => '420-01', 'nama' => 'Pendapatan Jasa Servis',       'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_jasa',      'saldo_normal' => 'kredit'],

            // BEBAN
            ['kode' => '510-01', 'nama' => 'Beban Komisi Reseller',        'tipe' => 'beban', 'kelompok' => 'beban_komisi',      'saldo_normal' => 'debit'],
            ['kode' => '510-02', 'nama' => 'Harga Pokok Penjualan (HPP)',  'tipe' => 'beban', 'kelompok' => 'hpp',              'saldo_normal' => 'debit'],
            ['kode' => '520-01', 'nama' => 'Beban Gaji Karyawan',          'tipe' => 'beban', 'kelompok' => 'beban_operasional','saldo_normal' => 'debit'],
            ['kode' => '520-02', 'nama' => 'Beban Sewa Toko',              'tipe' => 'beban', 'kelompok' => 'beban_operasional','saldo_normal' => 'debit'],
            ['kode' => '520-03', 'nama' => 'Beban Listrik & Air',          'tipe' => 'beban', 'kelompok' => 'beban_operasional','saldo_normal' => 'debit'],
            ['kode' => '520-04', 'nama' => 'Beban Marketing & Iklan',      'tipe' => 'beban', 'kelompok' => 'beban_operasional','saldo_normal' => 'debit'],
            ['kode' => '520-05', 'nama' => 'Beban Lain-lain',              'tipe' => 'beban', 'kelompok' => 'beban_operasional','saldo_normal' => 'debit'],
        ];

        foreach ($coa as $data) {
            AkunCOA::firstOrCreate(
                ['kode' => $data['kode']],
                ['nama' => $data['nama'], 'tipe' => $data['tipe'], 'kelompok' => $data['kelompok'], 'saldo_normal' => $data['saldo_normal']]
            );
        }
    }
}