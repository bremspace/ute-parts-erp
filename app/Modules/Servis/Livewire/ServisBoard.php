<?php

namespace App\Modules\Servis\Livewire;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Servis\Models\JenisServis;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Services\ServisService;
use App\Modules\Servis\Services\ServisStateMachine;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class ServisBoard extends Component
{
    use WithPagination;

    // Search & filter
    public string $search = '';
    public string $filterStatus = ''; // empty = all

    // Terima Unit Modal
    public bool $showTerimaModal = false;
    public array $terimaForm = [
        'pelanggan_id'    => null,
        'nama_pelanggan'  => '',
        'telepon_pelanggan' => '',
        'jenis_servis_id' => null,
        'jenis_hp'        => '',
        'seri_hp'         => '',
        'keluhan'         => '',
        'kondisi_fisik'   => [],
        'foto_unit'       => [], // BASE64 data URLs (min 2)
    ];

    // Foto preview (base64)
    public array $fotoPreviews = [];
    public array $photoInputs = [];

    // Detail Modal
    public ?int $selectedTiketId = null;

    // Estimasi Modal
    public bool $showEstimasiModal = false;
    public ?int $estimasiTiketId = null;
    public float $estimasiBiaya = 0;
    public string $estimasiAlasan = '';

    // Approve/Reject Modal (menunggu_approval)
    public bool $showApproveModal = false;
    public ?int $approveTiketId = null;
    public string $approveAlasan = '';

    // Drag & drop target status
    public ?string $dropTargetStatus = null;
    public ?int $dropTiketId = null;

    public function mount()
    {
        $this->photoInputs = [null, null, null]; // max 3 foto
    }

    public function getStateMachineColumnsProperty(): array
    {
        return ServisStateMachine::kanbanColumns();
    }

    public function getGroupedTiketsProperty(): array
    {
        $query = TiketServis::with(['jenisServis', 'pelanggan', 'teknisi', 'garansi'])
            ->withCount('spareparts')
            ->latest();

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('no_tiket', 'like', "%{$this->search}%")
                  ->orWhere('jenis_hp', 'like', "%{$this->search}%")
                  ->orWhere('nama_pelanggan', 'like', "%{$this->search}%")
                  ->orWhere('telepon_pelanggan', 'like', "%{$this->search}%");
            });
        }

        $semua = $query->limit(100)->get();

        // Group per column status
        $grouped = [];
        foreach (array_keys(ServisStateMachine::kanbanColumns()) as $status) {
            $grouped[$status] = $semua->where('status', $status)->values();
        }

        return $grouped;
    }

    // --- Foto handling (base64, min 2, wajib) ---
    public function handleFotoUpload(int $index, $content)
    {
        if (!$content) return;

        // Data URL: data:image/png;base64,xxx
        $this->photoInputs[$index] = $content;
    }

    public function removeFoto(int $index)
    {
        $this->photoInputs[$index] = null;
    }

    public function getFotoCountProperty(): int
    {
        return count(array_filter($this->photoInputs));
    }

    public function toggleKondisiFisik(string $check)
    {
        $list = $this->terimaForm['kondisi_fisik'] ?? [];
        if (in_array($check, $list, true)) {
            $this->terimaForm['kondisi_fisik'] = array_values(array_diff($list, [$check]));
        } else {
            $this->terimaForm['kondisi_fisik'] = [...$list, $check];
        }
    }

    // --- Terima Unit ---
    public function openTerimaModal()
    {
        $this->terimaForm = [
            'pelanggan_id'    => null,
            'nama_pelanggan'  => '',
            'telepon_pelanggan' => '',
            'jenis_servis_id' => JenisServis::where('is_active', true)->first()?->id,
            'jenis_hp'        => '',
            'seri_hp'         => '',
            'keluhan'         => '',
            'kondisi_fisik'   => [],
            'foto_unit'       => [],
        ];
        $this->photoInputs = [null, null, null];
        $this->showTerimaModal = true;
    }

    public function simpanTerima()
    {
        $this->validate([
            'terimaForm.jenis_hp'       => 'required|string|max:255',
            'terimaForm.keluhan'        => 'required|string',
            'terimaForm.nama_pelanggan' => 'required_if:terimaForm.pelanggan_id,',
            'terimaForm.telepon_pelanggan' => 'nullable|string|max:20',
        ]);

        $foto = array_values(array_filter($this->photoInputs));

        if (count($foto) < 2) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Foto unit wajib minimal 2']);
            return;
        }

        $data = $this->terimaForm;
        $data['foto_unit'] = $foto;

        try {
            $tiket = app(ServisService::class)->terimaUnit($data, auth()->user());
            $this->showTerimaModal = false;
            $this->selectedTiketId = $tiket->id;
            $this->dispatch('alert', ['type' => 'success', 'message' => "Unit diterima — {$tiket->no_tiket}"]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // --- Status update (state machine) ---
    public function updateStatus(int $tiketId, string $statusBaru, string $alasan = '')
    {
        $tiket = TiketServis::findOrFail($tiketId);

        try {
            $tiket = app(ServisService::class)->updateStatus($tiket, $statusBaru, auth()->user(), $alasan);
            $this->dispatch('alert', ['type' => 'success', 'message' => "Status → {$statusBaru}"]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // --- Estimasi ---
    public function openEstimasiModal(int $tiketId)
    {
        $tiket = TiketServis::findOrFail($tiketId);
        $this->estimasiTiketId = $tiketId;
        $this->estimasiBiaya = (float) ($tiket->estimasi_biaya ?? 0);
        $this->estimasiAlasan = '';
        $this->showEstimasiModal = true;
    }

    public function simpanEstimasi()
    {
        $this->validate([
            'estimasiBiaya'  => 'required|numeric|min:0',
            'estimasiAlasan' => 'required|string|min:5',
        ]);

        $tiket = TiketServis::findOrFail($this->estimasiTiketId);

        try {
            app(ServisService::class)->setEstimasi(
                $tiket,
                (float) $this->estimasiBiaya,
                $this->estimasiAlasan,
                auth()->user()
            );
            $this->showEstimasiModal = false;
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Estimasi tersimpan, menunggu approval pelanggan']);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // --- Approve / Reject (admin, dengan alasan) ---
    public function openApproveModal(int $tiketId)
    {
        $this->approveTiketId = $tiketId;
        $this->approveAlasan = '';
        $this->showApproveModal = true;
    }

    public function prosesApprove(string $action)
    {
        $tiket = TiketServis::findOrFail($this->approveTiketId);
        $statusBaru = $action === 'approve' ? 'disetujui' : 'ditolak';

        $this->updateStatus($tiket->id, $statusBaru, $this->approveAlasan ?: ($action === 'approve' ? 'Pelanggan menyetujui estimasi' : 'Estimasi ditolak'));
        $this->showApproveModal = false;
    }

    // --- Drag & drop ---
    public function dropTicket(int $tiketId, string $statusTujuan)
    {
        // Simulate: drop = update status ke kolom tujuan (state machine akan validasi)
        $this->updateStatus($tiketId, $statusTujuan);
    }

    // --- Detail ---
    public function openDetail(int $tiketId)
    {
        $this->selectedTiketId = $tiketId;
    }

    public function getSelectedTiketProperty(): ?TiketServis
    {
        return $this->selectedTiketId
            ? TiketServis::with([
                'jenisServis', 'pelanggan.tierMembership', 'teknisi', 'garansi',
                'statusLogs.user', 'spareparts.produk', 'spareparts.skuVariant', 'cabang',
            ])->find($this->selectedTiketId)
            : null;
    }

    public function render()
    {
        return view('modules.servis.livewire.servis-board', [
            'jenisServisList' => JenisServis::where('is_active', true)->get(),
            'pelangganList'   => Pelanggan::with('tierMembership')->limit(10)->get(),
            'teknisiList'     => User::role(['teknisi', 'admin-toko', 'super-admin'])->get(),
            'gudangList'      => Gudang::where('is_active', true)->get(),
            'produkList'      => Produk::where('is_active', true)->orderBy('nama')->limit(50)->get(),
            'stateMachineColumns' => $this->stateMachineColumns,
            'groupedTikets'   => $this->groupedTikets,
            'selectedTiket'   => $this->selectedTiket,
            'fotoCount'       => $this->fotoCount,
        ])->layout('layouts.backoffice', ['header' => 'Servis HP — Papan Kanban']);
    }
}