<?php

namespace App\Modules\Servis\Services;

/**
 * State machine untuk tiket servis HP.
 * PRD §4.3: Alur status tidak boleh mundur kecuali admin override + log alasan.
 *
 * Valid forward transitions:
 *   diajukan_online → diterima → diagnosa → menunggu_approval → disetujui → dikerjakan → qc → selesai → diambil
 *   menunggu_approval → ditolak → diagnosa (re-estimasi, allowed forward)
 *
 * Diakui sebagai 'override' jika user punya permission servis.override-status.
 */
class ServisStateMachine
{
    /**
     * Daftar transisi valid (status_dari => [status_ke[]]).
     */
    private const TRANSITIONS = [
        'diajukan_online' => ['diterima'],
        'diterima' => ['diagnosa'],
        'diagnosa' => ['menunggu_approval'],
        'menunggu_approval' => ['disetujui', 'ditolak'],
        'disetujui' => ['dikerjakan'],
        'dikerjakan' => ['qc'],
        'qc' => ['selesai'],
        'selesai' => ['diambil'],
        'ditolak' => ['diagnosa'], // re-estimasi
    ];

    private const STATUS_VALID = [
        'diajukan_online', 'diterima', 'diagnosa', 'menunggu_approval',
        'disetujui', 'ditolak', 'dikerjakan', 'qc', 'selesai', 'diambil',
    ];

    /**
     * Check apakah transisi valid (forward).
     */
    public static function dapatTransisi(string $dari, string $ke): bool
    {
        return in_array($ke, self::TRANSITIONS[$dari] ?? [], true);
    }

    /**
     * Return all valid next statuses dari status saat ini.
     */
    public static function nextValidStatuses(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }

    /**
     * Apakah ini status akhir (terminal)?
     */
    public static function isTerminal(string $status): bool
    {
        return ! isset(self::TRANSITIONS[$status]);
    }

    /**
     * Return semua status valid.
     */
    public static function allStatuses(): array
    {
        return self::STATUS_VALID;
    }

    /**
     * Return daftar status yang tampil di Kanban columns.
     */
    public static function kanbanColumns(): array
    {
        return [
            'diterima' => ['label' => 'Diterima',       'color' => 'ink-400'],
            'diagnosa' => ['label' => 'Diagnosa',       'color' => 'up-primary'],
            'menunggu_approval' => ['label' => 'Menunggu Approval', 'color' => 'up-amber'],
            'disetujui' => ['label' => 'Disetujui',      'color' => 'up-mint'],
            'dikerjakan' => ['label' => 'Dikerjakan',     'color' => 'up-primary'],
            'qc' => ['label' => 'QC',             'color' => 'up-accent'],
            'selesai' => ['label' => 'Selesai',        'color' => 'up-mint'],
            'diambil' => ['label' => 'Diambil',        'color' => 'ink-100'],
        ];
    }
}
