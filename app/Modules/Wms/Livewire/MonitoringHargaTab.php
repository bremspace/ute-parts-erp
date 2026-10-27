<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Services\MarginGuardService;
use Livewire\Component;

class MonitoringHargaTab extends Component
{
    public string $search = '';

    public string $filterStatus = 'semua'; // semua, bahaya, peringatan, aman

    public string $filterKategori = '';

    public array $selectedIds = [];

    public bool $selectAll = false;

    public int $perPage = 25;

    public int $page = 1;

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStatus' => ['except' => 'semua'],
        'filterKategori' => ['except' => ''],
    ];

    public function mount(): void
    {
        if (! $this->canAccess()) {
            abort(403, 'Akses terbatas: Menu Monitoring Margin hanya dapat diakses oleh Owner dan Super Admin.');
        }
    }

    public function updatingSearch(): void
    {
        $this->page = 1;
    }

    public function updatingFilterStatus(): void
    {
        $this->page = 1;
    }

    public function updatingFilterKategori(): void
    {
        $this->page = 1;
    }

    public function toggleSelectAll(): void
    {
        $this->selectAll = ! $this->selectAll;
        if ($this->selectAll) {
            $scan = app(MarginGuardService::class)->scanProduk([
                'search' => $this->search,
                'status' => $this->filterStatus,
                'kategori' => $this->filterKategori,
            ]);
            $this->selectedIds = $scan['items']->pluck('produk_id')->all();
        } else {
            $this->selectedIds = [];
        }
    }

    private function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return isSuperAdminOrOwner($user) || $user->can('pengaturan.manage') || $user->can('wms.view_cost');
    }

    private function canManagePrice(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return isSuperAdminOrOwner($user) || $user->can('pengaturan.manage') || $user->can('wms.create');
    }

    public function terapkanSatu(int $produkId): void
    {
        if (! $this->canManagePrice()) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Hanya Owner atau Super Admin yang memiliki akses mengubah harga produk.']);

            return;
        }

        $service = app(MarginGuardService::class);
        $sukses = $service->terapkanRekomendasi($produkId);

        if ($sukses) {
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Harga produk berhasil disesuaikan ke rekomendasi aman']);
        } else {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Harga sudah sesuai atau produk tidak ditemukan']);
        }
    }

    public function terapkanTerpilih(): void
    {
        if (! $this->canManagePrice()) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Hanya Owner atau Super Admin yang memiliki akses mengubah harga produk.']);

            return;
        }

        if (empty($this->selectedIds)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Pilih minimal satu produk untuk disesuaikan']);

            return;
        }

        $service = app(MarginGuardService::class);
        $count = $service->bulkTerapkanRekomendasi($this->selectedIds);
        $this->selectedIds = [];
        $this->selectAll = false;

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => "{$count} produk berhasil disesuaikan ke rekomendasi harga aman",
        ]);
    }

    public function terapkanSemuaBerisiko(): void
    {
        if (! $this->canManagePrice()) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Hanya Owner atau Super Admin yang memiliki akses mengubah harga produk.']);

            return;
        }

        $service = app(MarginGuardService::class);
        $scan = $service->scanProduk();
        $berisikoIds = $scan['items']
            ->filter(fn ($item) => in_array($item['status'], ['bahaya', 'peringatan'], true))
            ->pluck('produk_id')
            ->all();

        if (empty($berisikoIds)) {
            $this->dispatch('alert', ['type' => 'info', 'message' => 'Seluruh produk saat ini sudah memiliki margin aman!']);

            return;
        }

        $count = $service->bulkTerapkanRekomendasi($berisikoIds);

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => "Semua produk berisiko ({$count} item) berhasil disesuaikan ke harga aman",
        ]);
    }

    public function setPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function render()
    {
        if (! $this->canAccess()) {
            abort(403, 'Akses terbatas: Menu Monitoring Margin hanya dapat diakses oleh Owner dan Super Admin.');
        }

        $service = app(MarginGuardService::class);
        $params = $service->getParameterMargin($this->filterKategori ?: null);

        $scan = $service->scanProduk([
            'search' => $this->search,
            'status' => $this->filterStatus,
            'kategori' => $this->filterKategori,
        ]);

        $allItems = $scan['items'];
        $totalItems = $allItems->count();
        $totalPages = max(1, (int) ceil($totalItems / $this->perPage));
        $this->page = min($this->page, $totalPages);

        $pagedItems = $allItems->slice(($this->page - 1) * $this->perPage, $this->perPage);

        $kategoriList = KategoriProduk::where('is_active', true)->orderBy('nama')->get();

        return view('modules.wms.livewire.monitoring-harga-tab', [
            'params' => $params,
            'scanSummary' => $scan,
            'items' => $pagedItems,
            'totalItems' => $totalItems,
            'totalPages' => $totalPages,
            'currentPage' => $this->page,
            'kategoriList' => $kategoriList,
        ]);
    }
}
