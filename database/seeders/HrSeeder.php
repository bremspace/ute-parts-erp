<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\KomisiTeknisiRule;
use App\Modules\Hr\Models\KpiMetric;
use App\Modules\Hr\Models\Shift;
use App\Modules\Reseller\Models\KomisiSkema;
use App\Modules\Workflow\Models\ApprovalRule;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HrSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // ApprovalRule untuk payroll (F1-1)
        ApprovalRule::updateOrCreate(
            ['entity_type' => 'payroll', 'level' => 1],
            [
                'cabang_id' => null,
                'min_amount' => 10000000,
                'max_amount' => null,
                'approver_role' => 'finance',
                'is_aktif' => true,
            ]
        );

        // Demo Karyawan — idempotent
        $users = [
            ['email' => 'teknisi@uteparts.test', 'nama' => 'Teknisi Utama', 'jabatan' => 'teknisi'],
            ['email' => 'kasir@uteparts.test', 'nama' => 'Kasir Utama', 'jabatan' => 'kasir'],
            ['email' => 'admin@uteparts.test', 'nama' => 'Admin Gudang', 'jabatan' => 'admin'],
            ['email' => 'marketing@uteparts.test', 'nama' => 'Marketing Utama', 'jabatan' => 'marketing'],
        ];

        foreach ($users as $u) {
            $user = User::updateOrCreate(
                ['email' => $u['email']],
                ['name' => $u['nama'], 'password' => bcrypt('password'), 'is_active' => true]
            );

            Karyawan::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'nik' => strtoupper(substr($u['jabatan'], 0, 3)).date('Y').rand(100, 999),
                    'nama' => $u['nama'],
                    'jabatan' => $u['jabatan'],
                    'cabang_id' => session('cabang_id') ?? 1,
                    'tgl_masuk' => '2024-01-01',
                    'gaji_pokok' => $u['jabatan'] === 'teknisi' ? 5000000 : ($u['jabatan'] === 'kasir' ? 4000000 : ($u['jabatan'] === 'marketing' ? 4500000 : 4500000)),
                    'status_aktif' => true,
                    'rekening_bank' => 'BCA-'.rand(100000, 999999),
                ]
            );
        }

        // Demo KomisiTeknisiRule — idempotent
        KomisiTeknisiRule::updateOrCreate(
            ['jabatan_target' => 'teknisi', 'jenis' => 'per_tiket', 'cabang_id' => null],
            [
                'nominal' => 50000,
                'persen' => null,
                'min_status_tiket' => 'selesai',
                'is_aktif' => true,
            ]
        );

        // Demo Shift — idempotent (Pagi, Siang; Malam = overnight 22:00-06:00)
        $shift = session('cabang_id') ?? 1;
        foreach ([
            ['nama' => 'Pagi', 'jam_mulai' => '08:00:00', 'jam_selesai' => '16:00:00'],
            ['nama' => 'Siang', 'jam_mulai' => '13:00:00', 'jam_selesai' => '21:00:00'],
            ['nama' => 'Malam', 'jam_mulai' => '22:00:00', 'jam_selesai' => '06:00:00'],
        ] as $s) {
            Shift::updateOrCreate(
                ['cabang_id' => $shift, 'nama' => $s['nama']],
                ['jam_mulai' => $s['jam_mulai'], 'jam_selesai' => $s['jam_selesai'], 'is_aktif' => true]
            );
        }

        // Demo KPI Metric — whitelist rumus (idempotent)
        foreach ([
            ['kode' => 'tiket_selesai', 'nama' => 'Tiket Servis Selesai', 'rumus' => 'tiket_selesai', 'target' => 20, 'satuan' => 'tiket'],
            ['kode' => 'transaksi_kasir', 'nama' => 'Transaksi Kasir', 'rumus' => 'transaksi_kasir', 'target' => 15, 'satuan' => 'transaksi'],
            ['kode' => 'selisih_kas', 'nama' => 'Selisih Kas (min)', 'rumus' => 'selisih_kas', 'target' => 50000, 'satuan' => 'rp'],
            ['kode' => 'lead_won', 'nama' => 'Lead Won', 'rumus' => 'lead_won', 'target' => 10, 'satuan' => 'lead'],
            ['kode' => 'penjualan_lead_won', 'nama' => 'Nilai Penjualan Lead Won', 'rumus' => 'penjualan_lead_won', 'target' => 50000000, 'satuan' => 'rp'],
        ] as $m) {
            KpiMetric::updateOrCreate(
                ['kode' => $m['kode']],
                ['nama' => $m['nama'], 'rumus' => $m['rumus'], 'target' => $m['target'], 'satuan' => $m['satuan'], 'periode' => 'bulanan', 'is_aktif' => true]
            );
        }

        // Demo Rule Komisi multi-aktor (PRD §4.3) — idempotent
        $ruleKomisi = [
            ['nama' => 'Reseller umum 3% penjualan', 'aktor_tipe' => 'reseller', 'aktor_id' => null, 'trigger_tipe' => 'penjualan', 'kategori' => null, 'tipe' => 'persen', 'nilai' => 3, 'min_amount' => 0, 'cabang_id' => null],
            ['nama' => 'Agen umum 2% penjualan', 'aktor_tipe' => 'agen', 'aktor_id' => null, 'trigger_tipe' => 'penjualan', 'kategori' => null, 'tipe' => 'persen', 'nilai' => 2, 'min_amount' => 0, 'cabang_id' => null],
            ['nama' => 'Marketing lead won Rp50.000', 'aktor_tipe' => 'karyawan', 'aktor_id' => null, 'trigger_tipe' => 'lead_won', 'kategori' => null, 'tipe' => 'nominal', 'nilai' => 50000, 'min_amount' => 0, 'cabang_id' => null],
            ['nama' => 'Teknisi per tiket servis Rp10.000', 'aktor_tipe' => 'karyawan', 'aktor_id' => null, 'trigger_tipe' => 'tiket_servis', 'kategori' => null, 'tipe' => 'nominal', 'nilai' => 10000, 'min_amount' => 0, 'cabang_id' => null],
        ];
        foreach ($ruleKomisi as $r) {
            KomisiSkema::updateOrCreate(
                ['nama' => $r['nama']],
                $r
            );
        }
    }
}
