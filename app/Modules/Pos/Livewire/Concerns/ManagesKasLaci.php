<?php

namespace App\Modules\Pos\Livewire\Concerns;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Pos\Services\KasSesiState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trait ManagesKasLaci
 *
 * Menangani alur buka kas, tutup kas laci (blind count),
 * dan pencatatan mutasi kas laci non-POS.
 */
trait ManagesKasLaci
{
    public function formatKasSaldoAwal(): void
    {
        $this->kasSaldoAwal = $this->normalizeNominal($this->kasSaldoAwalRaw);
        $this->kasSaldoAwalRaw = $this->formatNominal($this->kasSaldoAwal);
    }

    public function formatKasSaldoFisik(): void
    {
        $this->kasSaldoFisik = $this->normalizeNominal($this->kasSaldoFisikRaw);
        $this->kasSaldoFisikRaw = $this->formatNominal($this->kasSaldoFisik);
    }

    private function formatNominal(float $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    public function prosesKas()
    {
        // Validasi Livewire: nominal wajib angka, tidak negatif
        if ($this->kasModeBuka) {
            $this->validate([
                'kasSaldoAwalRaw' => ['required'],
            ], [], [
                'kasSaldoAwalRaw' => 'Saldo awal',
            ]);
            // Ensure parsed value is valid
            $this->formatKasSaldoAwal();
        } else {
            $this->validate([
                'kasSaldoFisikRaw' => ['required'],
            ], [], [
                'kasSaldoFisikRaw' => 'Saldo fisik akhir',
            ]);
            $this->formatKasSaldoFisik();
        }

        try {
            $svc = app(KasSesiState::class);

            if ($this->kasModeBuka) {
                $cabangId = currentCabangId() ?? auth()->user()?->cabangs()->first()?->id ?? 1;
                $this->kasHasil = $svc->bukaKas(
                    $this->normalizeNominal($this->kasSaldoAwal),
                    $cabangId,
                    auth()->id(),
                    $this->kasSumber,
                    $this->selectedAkunSumber
                );
                $this->checkKasSesi();

                // Sukses: toast + kembali ke tampilan POS (modal tertutup)
                $this->kasHasil = null;
                $this->showKasModal = false;
                $this->dispatch('alert', ['type' => 'success', 'message' => 'Kas berhasil dibuka — silakan mulai transaksi']);
            } else {
                $this->kasHasil = $svc->tutupKas($this->normalizeNominal($this->kasSaldoFisik));
                $this->checkKasSesi();

                // [KAS-LACI] Bila ada selisih → status menunggu_approval
                if (($this->kasHasil['status'] ?? '') === 'menunggu_approval') {
                    $this->showKasModal = false;
                    $this->dispatch('alert', [
                        'type' => 'warning',
                        'message' => 'Terdapat selisih kas — permintaan persetujuan telah dikirim ke owner / manager toko.',
                    ]);
                } else {
                    $this->dispatch('alert', [
                        'type' => 'success',
                        'message' => 'Kas ditutup — saldo fisik didepositkan kembali.',
                    ]);
                }
            }
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    protected function normalizeNominal(mixed $nilai): float
    {
        return $this->parseNominal($nilai);
    }

    public function bukaMutasiKasModal(string $jenis = 'keluar'): void
    {
        $this->mutasiJenis = in_array($jenis, ['masuk', 'keluar'], true) ? $jenis : 'keluar';
        $this->mutasiNominalRaw = '';
        $this->mutasiNominal = 0;
        $this->mutasiKeterangan = '';
        $this->resetValidation(['mutasiNominalRaw', 'mutasiAkunLawan', 'mutasiKeterangan']);

        $this->muatKategoriMutasi();
        $this->muatRiwayatMutasiSesi();
        $this->showMutasiKasModal = true;
    }

    public function updatedMutasiJenis(): void
    {
        $this->muatKategoriMutasi();
    }

    public function muatKategoriMutasi(): void
    {
        if ($this->mutasiJenis === 'keluar') {
            // Pengeluaran kas laci: Beban operasional, perlengkapan toko, setor kas besar
            $this->mutasiKategoriList = [
                ['kode' => '520-05', 'nama' => 'Beban Lain-lain (Operasional Kecil/Konsumsi)', 'kelompok' => 'Beban'],
                ['kode' => '520-03', 'nama' => 'Beban Listrik, Air & Kebersihan', 'kelompok' => 'Beban'],
                ['kode' => '140-01', 'nama' => 'Perlengkapan Toko (ATK/Lakban/Plastik)', 'kelompok' => 'Aset Lancar'],
                ['kode' => '520-04', 'nama' => 'Beban Transport / Marketing Toko', 'kelompok' => 'Beban'],
                ['kode' => '110-01', 'nama' => 'Setor ke Kas Besar (Transfer Kas)', 'kelompok' => 'Kas/Bank'],
            ];
            $this->mutasiAkunLawan = '520-05';
        } else {
            // Pemasukan kas laci: Tambahan modal laci dari kas besar, pendapatan lain-lain
            $this->mutasiKategoriList = [
                ['kode' => '110-01', 'nama' => 'Kas Besar (Tambah Modal Laci)', 'kelompok' => 'Kas/Bank'],
                ['kode' => '430-01', 'nama' => 'Pendapatan Lain-lain (Parkir/Kardus/Tip)', 'kelompok' => 'Pendapatan'],
            ];
            $this->mutasiAkunLawan = '110-01';
        }
    }

    public function muatRiwayatMutasiSesi(): void
    {
        $sesi = app(KasSesiState::class)->sesiKasAktif();
        if (! $sesi || ! Schema::hasTable('kas_mutasi_laci')) {
            $this->riwayatMutasiSesi = [];

            return;
        }

        $this->riwayatMutasiSesi = DB::table('kas_mutasi_laci')
            ->where('kas_sesi_id', $sesi->id)
            ->latest('id')
            ->get()
            ->map(function ($row) {
                $akun = AkunCOA::where('kode', $row->akun_lawan_kode)->first();

                return [
                    'id' => $row->id,
                    'jenis' => $row->jenis,
                    'nominal' => (float) $row->nominal,
                    'akun_lawan' => $akun ? "{$akun->kode} - {$akun->nama}" : $row->akun_lawan_kode,
                    'keterangan' => $row->keterangan,
                    'no_jurnal' => $row->no_jurnal,
                    'jam' => date('H:i', strtotime($row->created_at)),
                ];
            })
            ->toArray();
    }

    public function simpanMutasiKas(): void
    {
        $this->validate([
            'mutasiNominalRaw' => 'required',
            'mutasiAkunLawan' => 'required|string',
            'mutasiKeterangan' => 'required|string|min:3|max:255',
        ], [
            'mutasiNominalRaw.required' => 'Nominal wajib diisi',
            'mutasiAkunLawan.required' => 'Kategori transaksi wajib dipilih',
            'mutasiKeterangan.required' => 'Keterangan transaksi wajib diisi',
            'mutasiKeterangan.min' => 'Keterangan minimal 3 karakter',
        ]);

        $nominal = $this->parseNominal($this->mutasiNominalRaw);
        if ($nominal <= 0) {
            $this->addError('mutasiNominalRaw', 'Nominal harus lebih dari 0');

            return;
        }

        try {
            $svc = app(KasSesiState::class);
            $res = $svc->catatMutasiLaci(
                jenis: $this->mutasiJenis,
                nominal: $nominal,
                akunLawanKode: $this->mutasiAkunLawan,
                keterangan: $this->mutasiKeterangan,
                cabangId: currentCabangId(),
                userId: auth()->id()
            );

            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Mutasi kas {$this->mutasiJenis} Rp ".number_format($nominal, 0, ',', '.')." berhasil dicatat (#{$res['no_jurnal']})",
            ]);

            $this->mutasiNominalRaw = '';
            $this->mutasiNominal = 0;
            $this->mutasiKeterangan = '';
            $this->muatRiwayatMutasiSesi();
            $this->checkKasSesi();
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
