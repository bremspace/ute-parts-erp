<?php

namespace App\Modules\Servis\Console\Commands;

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

                    if ($tiket->teknisi?->phone) {
                        $notifService->kirim('wa', $tiket->teknisi->phone, 'Peringatan Batas Waktu Servis', '', [
                            'audience' => 'staf',
                            'user_id' => $tiket->teknisi_id,
                            'cabang_id' => $tiket->cabang_id,
                            'tiket_id' => $tiket->id,
                            'kategori' => 'servis',
                            'wa_template' => 'servis_sla_warning',
                            'wa_judul' => 'Peringatan Batas Waktu Servis',
                            'wa_vars' => [
                                'nama_teknisi' => $tiket->teknisi->name,
                                'no_tiket' => $tiket->no_tiket,
                                'jenis_hp' => $tiket->jenis_hp,
                                'sisa_menit' => $sisaMenit,
                            ],
                        ]);
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

                        // [P1-2/T-02] Aktor SISTEM (user NULL) — integritas audit
                        // trail; dulu bertopeng super-admin/teknisi/User::first().
                        $servisService->updateStatus($tiket, 'ditolak', null, $alasanBatal, 'sistem');

                        $tiket->update(['status_sla_kpi' => 'cancelled_overdue']);

                        // Berikan informasi transparan kepada konsumen
                        $teleponKonsumen = $tiket->telepon_pelanggan ?? $tiket->pelanggan?->telepon;
                        if (filled($teleponKonsumen)) {
                            $notifService->kirim('wa', $teleponKonsumen, 'Informasi Pembatalan Servis', '', [
                                'audience' => 'pelanggan',
                                'pelanggan_id' => $tiket->pelanggan_id,
                                'cabang_id' => $tiket->cabang_id,
                                'tiket_id' => $tiket->id,
                                'kategori' => 'servis',
                                'status' => 'ditolak',
                                'wa_template' => 'servis_batal_otomatis',
                                'wa_judul' => 'Informasi Pembatalan Servis',
                                'wa_vars' => [
                                    'nama' => $tiket->nama_pelanggan ?: ($tiket->pelanggan?->nama ?? 'Pelanggan'),
                                    'no_tiket' => $tiket->no_tiket,
                                    'jenis_hp' => $tiket->jenis_hp,
                                    'nama_cabang' => $tiket->cabang?->nama ?: 'Cabang',
                                    'alasan' => 'melewati batas waktu pengerjaan',
                                ],
                            ]);
                        }

                        $autoCancelCount++;
                        $this->warn("Tiket {$tiket->no_tiket} berhasil auto-cancel karena overdue");
                    } catch (\Throwable $e) {
                        Log::error("Gagal auto-cancel tiket {$tiket->no_tiket}: {$e->getMessage()}");
                    }
                } else {
                    // Opsi Manual: Berikan peringatan overdue ke teknisi & cabang bahwa waktu sudah habis
                    if (! $tiket->warning_overdue_sent_at || $tiket->warning_overdue_sent_at->diffInHours($now) >= 1) {
                        if ($tiket->teknisi?->phone) {
                            $notifService->kirim('wa', $tiket->teknisi->phone, 'Batas Waktu Servis Habis', '', [
                                'audience' => 'staf',
                                'user_id' => $tiket->teknisi_id,
                                'cabang_id' => $tiket->cabang_id,
                                'tiket_id' => $tiket->id,
                                'kategori' => 'servis',
                                'wa_template' => 'servis_sla_habis',
                                'wa_judul' => 'Batas Waktu Servis Habis',
                                'wa_vars' => [
                                    'nama_teknisi' => $tiket->teknisi->name,
                                    'no_tiket' => $tiket->no_tiket,
                                    'jenis_hp' => $tiket->jenis_hp,
                                ],
                            ]);
                        }
                        $tiket->update(['warning_overdue_sent_at' => $now]);
                    }
                }
            }
        }

        $this->info("Pemeriksaan selesai: {$peringatanCount} peringatan terkirim, {$overdueCount} tiket overdue, {$autoCancelCount} auto-cancel.");

        // 2. Komplain pasca-servis yang melewati SLA → ESKALASI regulator
        //    (sekali per tiket; penanda `komplain_eskalasi_at`).
        $komplainOverdue = TiketServis::with(['cabang', 'teknisi'])
            ->where('sumber', 'komplain')
            ->whereIn('status', ['dikerjakan', 'qc'])
            ->whereNotNull('pengerjaan_deadline_at')
            ->where('pengerjaan_deadline_at', '<', $now)
            ->whereNull('komplain_eskalasi_at')
            ->get();

        $eskalasiCount = 0;

        foreach ($komplainOverdue as $tiket) {
            $tiket->update(['komplain_eskalasi_at' => $now]);

            if ($tiket->teknisi?->phone) {
                $notifService->kirim('wa', $tiket->teknisi->phone, 'Eskalasi Komplain Servis', '', [
                    'audience' => 'staf',
                    'user_id' => $tiket->teknisi_id,
                    'cabang_id' => $tiket->cabang_id,
                    'tiket_id' => $tiket->id,
                    'kategori' => 'servis',
                    'wa_template' => 'servis_komplain_eskalasi_teknisi',
                    'wa_judul' => 'Eskalasi Komplain Servis',
                    'wa_vars' => [
                        'nama_teknisi' => $tiket->teknisi->name,
                        'no_tiket' => $tiket->no_tiket,
                        'jenis_hp' => $tiket->jenis_hp,
                    ],
                ]);
            }

            if ($tiket->cabang?->telepon) {
                $notifService->kirim('wa', $tiket->cabang->telepon, 'Eskalasi Komplain Servis', '', [
                    'audience' => 'staf',
                    'cabang_id' => $tiket->cabang_id,
                    'tiket_id' => $tiket->id,
                    'kategori' => 'servis',
                    'wa_template' => 'servis_komplain_eskalasi_cabang',
                    'wa_judul' => 'Eskalasi Komplain Servis',
                    'wa_vars' => [
                        'nama_cabang' => $tiket->cabang?->nama ?: 'Cabang',
                        'no_tiket' => $tiket->no_tiket,
                        'jenis_hp' => $tiket->jenis_hp,
                    ],
                ]);
            }

            Log::warning("Komplain {$tiket->no_tiket} melewati SLA — eskalasi regulator");
            $eskalasiCount++;
        }

        if ($eskalasiCount > 0) {
            $this->warn("{$eskalasiCount} komplain di-eskalasi ke regulator.");
        }

        return self::SUCCESS;
    }
}
