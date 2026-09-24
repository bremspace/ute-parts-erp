<?php

namespace App\Modules\Hr\Services;

use App\Modules\Hr\Models\AbsensiLog;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\Shift;
use Illuminate\Support\Carbon;

/**
 * [F3-8b] Absensi Service — clock-in/out idempotent per karyawan+tanggal,
 * penentuan status terlambat berbasis shift, rekap bulanan, auto-tandai absen.
 */
class AbsensiService
{
    /**
     * Clock-in (idempotent). Status 'terlambat' bila shift sudah mulai.
     * Shift overnight (jam_selesai < jam_mulai): terlambat hanya bila jam masuk
     * berada di luar jendela shift (antara jam selesai dan jam mulai).
     */
    public function clockIn(
        int $karyawanId,
        ?int $shiftId = null,
        ?string $lokasi = null,
        ?string $foto = null,
        ?string $catatan = null,
        ?Carbon $waktu = null
    ): AbsensiLog {
        $waktu = $waktu ?: now();
        $tanggal = $waktu->toDateString();
        $jamMasuk = $waktu->format('H:i:s');

        $log = AbsensiLog::firstOrNew(['karyawan_id' => $karyawanId, 'tanggal' => $tanggal]);

        // Idempotent: jam masuk sudah terisi → jangan timpa
        if ($log->jam_masuk) {
            return $log;
        }

        $shift = $shiftId ? Shift::find($shiftId) : null;
        $log->jam_masuk = $jamMasuk;
        $log->shift_id = $shift?->id;
        $log->lokasi = $lokasi;
        $log->self_photo = $foto;
        $log->catatan = $catatan ?: $log->catatan;
        $log->status = $this->tentukanStatus($log->jam_masuk, $shift);

        $log->save();

        return $log->fresh();
    }

    /**
     * Clock-out (idempotent). Bila belum ada log hari itu, dibuat dengan jam keluar saja.
     */
    public function clockOut(int $karyawanId, ?string $catatan = null, ?Carbon $waktu = null): AbsensiLog
    {
        $waktu = $waktu ?: now();
        $tanggal = $waktu->toDateString();

        $log = AbsensiLog::firstOrNew(['karyawan_id' => $karyawanId, 'tanggal' => $tanggal]);

        // Idempotent: jam keluar sudah terisi → jangan timpa
        if ($log->jam_keluar && $log->exists) {
            return $log;
        }

        $log->jam_keluar = $waktu->format('H:i:s');
        if ($catatan) {
            $log->catatan = trim(($log->catatan ?? '').' | '.$catatan, ' |');
        }
        $log->save();

        return $log->fresh();
    }

    /**
     * Cek apakah karyawan sudah clock-in hari ini (gate buka kas).
     */
    public function sudahClockInHariIni(int $karyawanId, ?string $tanggal = null): bool
    {
        return AbsensiLog::where('karyawan_id', $karyawanId)
            ->where('tanggal', $tanggal ?: now()->toDateString())
            ->whereNotNull('jam_masuk')
            ->exists();
    }

    public function logHariIni(int $karyawanId, ?string $tanggal = null): ?AbsensiLog
    {
        return AbsensiLog::where('karyawan_id', $karyawanId)
            ->where('tanggal', $tanggal ?: now()->toDateString())
            ->first();
    }

    /**
     * Tandai status manual (izin/cuti) oleh admin — idempotent.
     */
    public function tandaiStatus(int $karyawanId, string $tanggal, string $status, ?string $catatan = null): AbsensiLog
    {
        if (! in_array($status, [AbsensiLog::STATUS_ABSEN, AbsensiLog::STATUS_IZIN, AbsensiLog::STATUS_CUTI], true)) {
            throw new \InvalidArgumentException("Status manual tidak valid: {$status}");
        }

        return AbsensiLog::updateOrCreate(
            ['karyawan_id' => $karyawanId, 'tanggal' => $tanggal],
            ['status' => $status, 'catatan' => $catatan]
        );
    }

    /**
     * Rekap status absensi karyawan per periode bulanan.
     *
     * @return array{hadir: int, terlambat: int, absen: int, izin: int, cuti: int, total: int}
     */
    public function rekapBulanan(int $karyawanId, string $periode): array
    {
        ['mulai' => $mulai, 'selesai' => $selesai] = $this->rentangPeriode($periode);

        $rows = AbsensiLog::where('karyawan_id', $karyawanId)
            ->whereBetween('tanggal', [$mulai, $selesai])
            ->get();

        $rekap = [
            'hadir' => 0,
            'terlambat' => 0,
            'absen' => 0,
            'izin' => 0,
            'cuti' => 0,
            'total' => $rows->count(),
        ];

        foreach ($rows as $row) {
            if (isset($rekap[$row->status])) {
                $rekap[$row->status]++;
            }
        }

        return $rekap;
    }

    /**
     * Auto-tandai 'absen' utk hari kerja (Sen-Jum, non-future) tanpa log.
     * Basis potongan disiplin payroll (komponen 'potongan', threshold opt-in cabang).
     * Idempotent.
     *
     * @return int jumlah log absen yang dibuat
     */
    public function autoTandaiAbsen(string $periode): int
    {
        ['mulai' => $mulai, 'selesai' => $selesai] = $this->rentangPeriode($periode);
        $sampai = min($selesai, now()->toDateString());

        $karyawanIds = Karyawan::where('status_aktif', true)->pluck('id');
        $dibuat = 0;

        foreach ($karyawanIds as $karyawanId) {
            $hariIni = Carbon::parse($mulai);
            while ($hariIni->toDateString() <= $sampai) {
                $tanggal = $hariIni->toDateString();
                if ($hariIni->isWeekday() && ! AbsensiLog::where('karyawan_id', $karyawanId)->where('tanggal', $tanggal)->exists()) {
                    AbsensiLog::create([
                        'karyawan_id' => $karyawanId,
                        'tanggal' => $tanggal,
                        'status' => AbsensiLog::STATUS_ABSEN,
                        'catatan' => 'Auto absen (tanpa keterangan)',
                    ]);
                    $dibuat++;
                }
                $hariIni->addDay();
            }
        }

        return $dibuat;
    }

    /**
     * Tentukan status clock-in terhadap jam mulai shift.
     */
    protected function tentukanStatus(string $jamMasuk, ?Shift $shift): string
    {
        if (! $shift || ! $shift->jam_mulai) {
            return AbsensiLog::STATUS_HADIR;
        }

        // Shift overnight (22:00-06:00): terlambat hanya bila jam masuk di luar
        // jendela shift (setelah jam selesai dan sebelum jam mulai berikutnya).
        if ($shift->apakahOvernight()) {
            return ($jamMasuk > $shift->jam_selesai && $jamMasuk < $shift->jam_mulai)
                ? AbsensiLog::STATUS_TERLAMBAT
                : AbsensiLog::STATUS_HADIR;
        }

        return $jamMasuk > $shift->jam_mulai
            ? AbsensiLog::STATUS_TERLAMBAT
            : AbsensiLog::STATUS_HADIR;
    }

    /**
     * @return array{mulai: string, selesai: string}
     */
    protected function rentangPeriode(string $periode): array
    {
        return [
            'mulai' => $periode.'-01',
            'selesai' => date('Y-m-t', strtotime($periode.'-01')),
        ];
    }
}
