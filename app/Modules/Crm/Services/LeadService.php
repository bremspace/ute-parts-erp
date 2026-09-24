<?php

namespace App\Modules\Crm\Services;

use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Reseller\Services\KomisiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LeadService
{
    public function __construct(
        protected PelangganService $pelangganService,
        protected NotificationService $notificationService
    ) {}

    /**
     * Validation rules for lead.
     */
    public function rules(bool $isUpdate = false, ?int $leadId = null): array
    {
        $teleponRule = $isUpdate
            ? 'nullable|string|max:20|unique:leads,telepon,'.$leadId
            : 'nullable|string|max:20';

        $emailRule = $isUpdate
            ? 'nullable|email|unique:leads,email,'.$leadId
            : 'nullable|email';

        return [
            'cabang_id' => 'required|exists:cabang,id',
            'sumber' => 'required|string|in:walkin,phone,website,referral,social_media,marketplace,lain',
            'stage' => 'sometimes|in:baru,kontak,kualifikasi,negosiasi,won,lost',
            'nama' => 'required|string|max:255',
            'telepon' => $teleponRule,
            'email' => $emailRule,
            'nilai_estimasi' => 'sometimes|numeric|min:0',
            'assigned_to' => 'nullable|exists:users,id',
            'catatan' => 'nullable|string',
            'lost_reason' => 'nullable|string|in:harga,kompetitor,tidak_butuh,lain',
            'pelanggan_id' => 'nullable|exists:pelanggan,id',
        ];
    }

    /**
     * Create a new lead.
     */
    public function create(array $data): Lead
    {
        $validated = Validator::make($data, $this->rules())->validate();

        // Default stage is 'baru'
        $validated['stage'] = $validated['stage'] ?? 'baru';

        return Lead::create($validated);
    }

    /**
     * Update lead.
     */
    public function update(Lead $lead, array $data): Lead
    {
        $validated = Validator::make($data, $this->rules(true, $lead->id))->validate();

        // Handle stage transitions
        $oldStage = $lead->stage;
        $newStage = $validated['stage'] ?? $lead->stage;

        if ($oldStage !== $newStage) {
            $this->handleStageTransition($lead, $oldStage, $newStage, $validated);
        }

        $lead->update($validated);

        // [F3-8c] Trigger komisi lead_won (PRD §4.3) — setelah stage tersimpan,
        // match rule karyawan/reseller/agen; idempotent per trigger+rule+aktor
        if ($oldStage !== 'won' && ($validated['stage'] ?? null) === 'won') {
            app(KomisiService::class)->hitungKomisiMultiAktor('lead_won', ['lead_id' => $lead->id]);
        }

        return $lead->fresh();
    }

    /**
     * Handle stage transition logic.
     */
    protected function handleStageTransition(Lead $lead, string $oldStage, string $newStage, array &$validated): void
    {
        $now = now();

        match ($newStage) {
            'won' => $validated['won_at'] = $now,
            'lost' => $validated['lost_at'] = $now,
            default => null,
        };

        // Notify assigned user on stage change
        if ($lead->assigned_to) {
            $this->notificationService->kirim(
                'inapp',
                $lead->assigned_to,
                'Lead Berubah Tahap',
                "Lead '{$lead->nama}' berubah dari {$lead->getStageLabelFromStage($oldStage)} ke {$lead->getStageLabelFromStage($newStage)}",
                ['lead_id' => $lead->id, 'old_stage' => $oldStage, 'new_stage' => $newStage]
            );
        }
    }

    /**
     * Convert won lead to Pelanggan.
     */
    public function convertToPelanggan(Lead $lead): Pelanggan
    {
        if (! $lead->canConvert()) {
            throw new \InvalidArgumentException('Lead tidak dapat dikonversi: harus stage Won dan belum memiliki pelanggan.');
        }

        return DB::transaction(function () use ($lead) {
            $pelanggan = $this->pelangganService->create([
                'nama' => $lead->nama,
                'telepon' => $lead->telepon,
                'email' => $lead->email,
                'alamat' => null,
                'tanggal_lahir' => null,
                'is_reseller' => false,
                'tipe_konsumen' => 'retail',
            ]);

            $lead->update([
                'pelanggan_id' => $pelanggan->id,
                'stage' => 'won',
                'won_at' => $lead->won_at ?? now(),
            ]);

            // Notify assigned user
            if ($lead->assigned_to) {
                $this->notificationService->kirim(
                    'inapp',
                    $lead->assigned_to,
                    'Lead Dikonversi ke Pelanggan',
                    "Lead '{$lead->nama}' berhasil dikonversi ke pelanggan #{$pelanggan->id}",
                    ['lead_id' => $lead->id, 'pelanggan_id' => $pelanggan->id]
                );
            }

            return $pelanggan;
        });
    }

    /**
     * Get leads grouped by stage for kanban.
     */
    public function getKanbanData(?int $cabangId = null, ?int $assignedTo = null): array
    {
        $query = Lead::with(['assignedTo', 'pelanggan'])
            ->forCabang($cabangId)
            ->orderBy('stage')
            ->orderBy('created_at', 'desc');

        if ($assignedTo) {
            $query->where('assigned_to', $assignedTo);
        }

        $leads = $query->get();

        $stages = ['baru', 'kontak', 'kualifikasi', 'negosiasi', 'won', 'lost'];
        $grouped = [];

        foreach ($stages as $stage) {
            $grouped[$stage] = $leads->where('stage', $stage)->values();
        }

        // Calculate summary stats
        $totalValue = $leads->sum('nilai_estimasi');
        $wonValue = $leads->where('stage', 'won')->sum('nilai_estimasi');
        $lostCount = $leads->where('stage', 'lost')->count();

        return [
            'leads' => $grouped,
            'summary' => [
                'total' => $leads->count(),
                'total_nilai' => $totalValue,
                'won_nilai' => $wonValue,
                'lost_count' => $lostCount,
                'conversion_rate' => $leads->count() > 0 ? round(($leads->where('stage', 'won')->count() / $leads->count()) * 100, 1) : 0,
            ],
        ];
    }

    /**
     * Get funnel data for dashboard.
     */
    public function getFunnelData(?int $cabangId = null): array
    {
        $stages = ['baru', 'kontak', 'kualifikasi', 'negosiasi', 'won', 'lost'];
        $data = [];

        foreach ($stages as $stage) {
            $count = Lead::forCabang($cabangId)->where('stage', $stage)->count();
            $value = Lead::forCabang($cabangId)->where('stage', $stage)->sum('nilai_estimasi');
            $data[$stage] = [
                'label' => Lead::getStageLabelFromStageStatic($stage),
                'count' => $count,
                'value' => $value,
            ];
        }

        return $data;
    }

    /**
     * Bulk update stage (for drag-and-drop).
     */
    public function bulkUpdateStage(array $leadIds, string $newStage, ?int $cabangId = null): int
    {
        $allowedStages = ['baru', 'kontak', 'kualifikasi', 'negosiasi', 'won', 'lost'];
        if (! in_array($newStage, $allowedStages)) {
            throw new \InvalidArgumentException('Stage tidak valid.');
        }

        return DB::transaction(function () use ($leadIds, $newStage, $cabangId) {
            $leads = Lead::forCabang($cabangId)->whereIn('id', $leadIds)->get();
            $updated = 0;

            foreach ($leads as $lead) {
                $oldStage = $lead->stage;
                if ($oldStage !== $newStage) {
                    $updateData = ['stage' => $newStage];
                    $this->handleStageTransition($lead, $oldStage, $newStage, $updateData);
                    $lead->update($updateData);
                    $updated++;
                }
            }

            return $updated;
        });
    }

    /**
     * Delete lead.
     */
    public function delete(Lead $lead): bool
    {
        if ($lead->pelanggan_id) {
            throw new \InvalidArgumentException('Tidak bisa menghapus lead yang sudah dikonversi ke pelanggan.');
        }

        return $lead->delete();
    }
}
