<?php

namespace App\Modules\Crm\Livewire;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Crm\Services\TierService;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Servis\Models\TiketServis;
use Livewire\Component;
use Livewire\WithPagination;

class CrmDashboard extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $filterTierId = null;
    public bool $filterReseller = false;

    // Detail customer modal
    public ?int $selectedCustomerId = null;

    // Tier modal
    public bool $showTierModal = false;
    public array $tierForm = [
        'id' => null, 'nama' => '', 'kode' => '', 'min_belanja_12bulan' => 0,
        'diskon_persen' => 0, 'poin_multiplier' => 1.0, 'urutan' => 0, 'is_active' => true,
    ];

    // Broadcast modal
    public bool $showBroadcastModal = false;
    public string $broadcastJudul = '';
    public string $broadcastPesan = '';
    public ?int $broadcastTierId = null;
    public bool $broadcastReseller = false;

    public function getTiersProperty()
    {
        return TierMembership::orderBy('urutan')->get();
    }

    public function getCustomersProperty()
    {
        $query = Pelanggan::with('tierMembership')->latest();
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('nama', 'like', "%{$this->search}%")
                  ->orWhere('telepon', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%");
            });
        }
        if ($this->filterTierId) {
            $query->where('tier_membership_id', $this->filterTierId);
        }
        if ($this->filterReseller) {
            $query->where('is_reseller', true);
        }

        return $query->paginate(15);
    }

    // ===== DETAIL 360 =====
    public function getSelectedCustomerProperty(): ?array
    {
        if (!$this->selectedCustomerId) {
            return null;
        }

        $pelanggan = Pelanggan::with('tierMembership')->find($this->selectedCustomerId);
        if (!$pelanggan) {
            return null;
        }

        $transaksi = Transaksi::with('items.produk')
            ->where('pelanggan_id', $pelanggan->id)
            ->latest()->take(8)->get();

        $servis = TiketServis::with('garansi')
            ->where('pelanggan_id', $pelanggan->id)
            ->latest()->take(8)->get();

        $komisi = $pelanggan->is_reseller
            ? $pelanggan->komisi()->selectRaw('status, sum(nominal_komisi) as total')->groupBy('status')->pluck('total', 'status')
            : collect();

        // Progress tier
        $belanja = (float) $pelanggan->total_belanja_12bulan;
        $tiers = TierMembership::where('is_active', true)->orderBy('min_belanja_12bulan')->get();
        $next = $tiers->first(fn ($t) => (float) $t->min_belanja_12bulan > $belanja);
        $progress = 100;
        $sisa = 0;
        if ($next) {
            $prev = (float) ($pelanggan->tierMembership->min_belanja_12bulan ?? 0);
            $range = max(1, (float) $next->min_belanja_12bulan - $prev);
            $progress = round((($belanja - $prev) / $range) * 100, 1);
            $sisa = max(0, (float) $next->min_belanja_12bulan - $belanja);
        }

        return [
            'pelanggan' => $pelanggan,
            'transaksi' => $transaksi,
            'servis' => $servis,
            'komisi' => $komisi,
            'tier_berikutnya' => $next?->nama,
            'progress_tier' => min(100, max(0, $progress)),
            'sisa_belanja' => $sisa,
        ];
    }

    // ===== TIER CRUD =====
    public function openTierModal(?int $id = null)
    {
        if ($id) {
            $tier = TierMembership::findOrFail($id);
            $this->tierForm = $tier->toArray();
        } else {
            $this->tierForm = [
                'id' => null, 'nama' => '', 'kode' => '', 'min_belanja_12bulan' => 0,
                'diskon_persen' => 0, 'poin_multiplier' => 1.0, 'urutan' => 0, 'is_active' => true,
            ];
        }
        $this->showTierModal = true;
    }

    public function simpanTier()
    {
        $this->validate([
            'tierForm.nama' => 'required|string|max:255',
            'tierForm.kode' => 'required|string|max:50',
            'tierForm.min_belanja_12bulan' => 'required|numeric|min:0',
            'tierForm.diskon_persen' => 'required|numeric|min:0|max:100',
            'tierForm.poin_multiplier' => 'required|numeric|min:0',
        ]);

        if ($this->tierForm['id']) {
            TierMembership::findOrFail($this->tierForm['id'])->update($this->tierForm);
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Tier diperbarui']);
        } else {
            TierMembership::create($this->tierForm);
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Tier baru dibuat']);
        }

        $this->showTierModal = false;
    }

    public function recalcTier()
    {
        $updated = app(TierService::class)->recalcSemua();
        $this->dispatch('alert', ['type' => 'success', 'message' => "Rekalkulasi tier: {$updated} pelanggan diperbarui"]);
    }

    // ===== BROADCAST =====
    public function kirimBroadcast()
    {
        $this->validate([
            'broadcastJudul' => 'required|string|max:255',
            'broadcastPesan' => 'required|string',
        ]);

        $query = Pelanggan::query();
        if ($this->broadcastTierId) {
            $query->where('tier_membership_id', $this->broadcastTierId);
        }
        if ($this->broadcastReseller) {
            $query->where('is_reseller', true);
        }

        $count = 0;
        $query->chunkById(100, function ($pelangganList) use (&$count) {
            foreach ($pelangganList as $p) {
                app(NotificationService::class)->kirim(
                    'inapp', null,
                    $this->broadcastJudul,
                    "[Broadcast] {$this->broadcastPesan}",
                    ['pelanggan_id' => $p->id]
                );
                $count++;
            }
        });

        $this->showBroadcastModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => "Broadcast dikirim ke {$count} pelanggan via antrian"]);
    }

    public function render()
    {
        return view('modules.crm.livewire.crm-dashboard')
            ->layout('layouts.backoffice', ['header' => 'CRM & Membership']);
    }
}