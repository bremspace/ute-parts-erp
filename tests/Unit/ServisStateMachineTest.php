<?php

namespace Tests\Unit;

use App\Modules\Servis\Services\ServisStateMachine;
use PHPUnit\Framework\TestCase;

class ServisStateMachineTest extends TestCase
{
    public function test_valid_forward_transitions(): void
    {
        $valid = [
            ['diajukan_online', 'diterima'],
            ['diterima', 'diagnosa'],
            ['diagnosa', 'menunggu_approval'],
            ['menunggu_approval', 'disetujui'],
            ['menunggu_approval', 'ditolak'],
            ['disetujui', 'dikerjakan'],
            ['dikerjakan', 'qc'],
            ['qc', 'selesai'],
            ['selesai', 'diambil'],
            ['ditolak', 'diagnosa'], // re-estimasi
        ];

        foreach ($valid as [$dari, $ke]) {
            $this->assertTrue(
                ServisStateMachine::dapatTransisi($dari, $ke),
                "Transisi {$dari} → {$ke} harus VALID"
            );
        }
    }

    public function test_backward_transitions_are_rejected(): void
    {
        $backward = [
            ['diagnosa', 'diterima'],
            ['menunggu_approval', 'diagnosa'],
            ['disetujui', 'menunggu_approval'],
            ['dikerjakan', 'disetujui'],
            ['qc', 'dikerjakan'],
            ['selesai', 'qc'],
            ['diambil', 'selesai'],
            ['diterima', 'diajukan_online'],
        ];

        foreach ($backward as [$dari, $ke]) {
            $this->assertFalse(
                ServisStateMachine::dapatTransisi($dari, $ke),
                "Transisi mundur {$dari} → {$ke} harus DITOLAK (tanpa override)"
            );
        }
    }

    public function test_invalid_unknown_status_is_rejected(): void
    {
        $this->assertFalse(ServisStateMachine::dapatTransisi('diterima', 'tidak_ada_status'));
        $this->assertFalse(ServisStateMachine::dapatTransisi('unknown', 'diterima'));
    }

    public function test_next_valid_statuses_reflects_flow_order(): void
    {
        $this->assertEquals(['diterima'], ServisStateMachine::nextValidStatuses('diajukan_online'));
        $this->assertEquals(['disetujui', 'ditolak'], ServisStateMachine::nextValidStatuses('menunggu_approval'));
        $this->assertEquals(['diambil'], ServisStateMachine::nextValidStatuses('selesai'));
    }

    public function test_terminal_status_has_no_forward_moves(): void
    {
        $this->assertTrue(ServisStateMachine::isTerminal('diambil'));
        $this->assertFalse(ServisStateMachine::isTerminal('diterima'));
    }

    public function test_kanban_columns_cover_all_active_flow_states(): void
    {
        $columns = ServisStateMachine::kanbanColumns();

        foreach (['diterima', 'diagnosa', 'menunggu_approval', 'disetujui', 'dikerjakan', 'qc', 'selesai', 'diambil'] as $status) {
            $this->assertArrayHasKey($status, $columns, "Kolom kanban {$status} wajib ada");
        }
    }
}