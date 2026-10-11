<?php

namespace App\Modules\Servis\Console\Commands;

use App\Models\User;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Services\ServisService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CekBatasWaktuServisCommand extends Command
{
    protected $signature = 'servis:cek-batas-waktu';

    protected $description = 'Periksa tiket servis yang mendekati atau melewati batas waktu pengerjaan (SLA) & eksekusi auto-cancel atau warning';

    public function handle(NotificationService $notifService, ServisService $servisService): int
    {
        $now = now();

        // 1. Tiket yang sedang 'dikerjakan' dan memiliki deadline pengerjaan
        $tikets = TiketServis::with(['teknisi', 'pelanggan', 'jenisServis'])
            ->where('status', 'dikerjakan')
            ->whereNotNull('pengerjaan_deadline_at')
            ->get();

        $peringatanCount = 0;
        $overdueCount = 0;
        $autoCancelCount = 0;

        foreach ($tikets as $tiket) {
            $deadline = $tiket->pengerjaan_deadline_at;
            $peringatanMenit = $tiket->jenisServis?->peringatan_menit ?? 30;
            $waktuWarning = (clone $deadline)->subMinutes($peringatanMenit);

            // A. Peringatan Mendekati Batas Waktu (antara waktuWarning dan deadline)
            if ($now->greaterThanOrEqualTo($waktuWarning) && $now->lessThan($deadline)) {
                if (! $tiket->warning_overdue_sent_at) {
                    $sisaMenit = max(1, (int) $now->diffInMinutes($deadline, false));
                    $pesanTeknisi = "PERINGATAN BATAS WAKTU: Servis {$tiket->no_tiket} ({$tiket->jenis_hp}) sisa waktu pengerjaan {$sisaMenit} menit lagi! Segera selesaikan atau update progres pengerjaan.";

                    if ($tiket->teknisi?->phone) {
                        $notifService->kirim('wa', $tiket->teknisi->phone, 'Peringatan Batas Waktu Servis', $pesanTeknisi);
                    }

                    $tiket->update(['warning_overdue_sent_at' => $now]);
                    $peringatanCount++;
                    $this->info("Warning dikirim untuk tiket {$tiket->no_tiket} (sisa {$sisaMenit} menit)");
                }
            }

            // B. Batas Waktu Telah Habis (Overdue)
            if ($now->greaterThanOrEqualTo($deadline)) {
                $overdueCount++;

                if ($tiket->status_sla_kpi !== 'overdue' && $tiket->status_sla_kpi !== 'cancelled_overdue') {
                    $tiket->update(['status_sla_kpi' => 'overdue']);
                }

                // Cek apakah opsi auto_cancel aktif
                $autoCancel = $tiket->auto_cancel_overdue || ($tiket->jenisServis?->auto_cancel_jika_overdue ?? false);

                if ($autoCancel) {
                    // Batalkan servis otomatis karena overdue
                    try {
                        $alasanBatal = "Dibatalkan otomatis oleh sistem karena melewati batas waktu pengerjaan ({$tiket->batas_waktu_menit} menit) tanpa penyelesaian.";

                        // Transisi ke status 'ditolak'
                        $systemUser = User::role('super-admin')->first() ?? $tiket->teknisi ?? User::first();
                        $servisService->updateStatus($tiket, 'ditolak', $systemUser, $alasanBatal);

                        $tiket->update(['status_sla_kpi' => 'cancelled_overdue']);

                        // Berikan informasi transparan kepada konsumen
                        $teleponKonsumen = $tiket->telepon_pelanggan ?? $tiket->pelanggan?->telepon;
                        if (filled($teleponKonsumen)) {
                            $pesanKonsumen = "Halo {$tiket->nama_pelanggan}, kami mohon maaf atas ketidaknyamanannya. Pengerjaan servis unit {$tiket->jenis_hp} ({$tiket->no_tiket}) melewati batas waktu estimasi kami dan dibatalkan otomatis demi menjaga standar kualitas dan kepastian unit Anda. Unit siap diambil kembali di cabang, atau silakan hubungi tim kami untuk opsi penjadwalan ulang.";
                            $notifService->kirim('wa', $teleponKonsumen, 'Informasi Pembatalan Servis', $pesanKonsumen);
                        }

                        $autoCancelCount++;
                        $this->warn("Tiket {$tiket->no_tiket} berhasil auto-cancel karena overdue");
                    } catch (\Throwable $e) {
                        Log::error("Gagal auto-cancel tiket {$tiket->no_tiket}: {$e->getMessage()}");
                    }
                } else {
                    // Opsi Manual: Berikan peringatan overdue ke teknisi & cabang bahwa waktu sudah habis
                    if (! $tiket->warning_overdue_sent_at || $tiket->warning_overdue_sent_at->diffInHours($now) >= 1) {
                        $pesanOverdue = "BATAS WAKTU HABIS: Servis {$tiket->no_tiket} ({$tiket->jenis_hp}) telah melewati batas waktu SLA. Mohon segera selesaikan servis atau lakukan koordinasi/penjadwalan ulang dengan konsumen.";
                        if ($tiket->teknisi?->phone) {
                            $notifService->kirim('wa', $tiket->teknisi->phone, 'Batas Waktu Servis Habis', $pesanOverdue);
                        }
                        $tiket->update(['warning_overdue_sent_at' => $now]);
                    }
                }
            }
        }

        $this->info("Pemeriksaan selesai: {$peringatanCount} peringatan terkirim, {$overdueCount} tiket overdue, {$autoCancelCount} auto-cancel.");

        return self::SUCCESS;
    }
}
