<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\StokOpname;
use App\Modules\Wms\Models\StokOpnameItem;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * [F1-8 / S-01] Tab "Stock Opname" — dipecah dari WmsDashboard (paritas perilaku).
 */
class OpnameTab extends Component
{
    public ?int $filterGudangId = null;

    // New Opname Modal state
    public bool $showOpnameModal = false;

    public ?int $opnameGudangId = null;

    public ?int $opnameRakId = null; // [T-14] scope opname per rak

    public string $opnameCatatan = '';

    public array $opnameRows = []; // [['produk_id' => ..., 'nama' => ..., 'stok_sistem' => ..., 'stok_fisik' => ...]]

    // Active Opname View / Review
    public ?int $selectedOpnameId = null;

    public function mount()
    {
        $cabangId = session('cabang_id');
        if ($cabangId) {
            $this->filterGudangId = Gudang::where('cabang_id', $cabangId)->value('id');
        }
    }

    // Quick action header "+ Mulai Stock Opname" (dari shell WmsDashboard via $dispatch)
    #[On('wms-opname-baru')]
    public function openNewOpnameModal()
    {
        $this->opnameGudangId = $this->filterGudangId;
        $this->opnameRakId = null;
        $this->opnameCatatan = '';

        // Pre-fill with all products in warehouse (optionally filtered by rak)
        $stokQuery = StokItem::where('gudang_id', $this->filterGudangId);
        if ($this->opnameRakId) {
            $stokQuery->where('rak_id', $this->opnameRakId);
        }
        $products = Produk::where('is_active', true)
            ->whereHas('stokItems', fn ($q) => $q->where('gudang_id', $this->filterGudangId)
                ->when($this->opnameRakId, fn ($q2) => $q2->where('rak_id', $this->opnameRakId)))
            ->take(20)->get();
        $this->opnameRows = [];

        foreach ($products as $prod) {
            $stok = $this->opnameGudangId
                ? StokItem::where('produk_id', $prod->id)->where('gudang_id', $this->opnameGudangId)
                    ->when($this->opnameRakId, fn ($q) => $q->where('rak_id', $this->opnameRakId))
                    ->value('jumlah') ?? 0
                : 0;

            $this->opnameRows[] = [
                'produk_id' => $prod->id,
                'nama' => $prod->nama,
                'stok_sistem' => $stok,
                'stok_fisik' => $stok,
                'selisih' => 0,
            ];
        }

        $this->showOpnameModal = true;
    }

    public function updateOpnameFisik(int $index, $value)
    {
        $val = (int) $value;
        $this->opnameRows[$index]['stok_fisik'] = $val;
        $this->opnameRows[$index]['selisih'] = $val - $this->opnameRows[$index]['stok_sistem'];
    }

    public function saveOpname()
    {
        $this->validate([
            'opnameGudangId' => 'required|exists:gudang,id',
            'opnameRakId' => 'nullable|exists:rak,id',
            'opnameRows' => 'required|array|min:1',
        ]);

        DB::transaction(function () {
            $today = now()->format('Ymd');
            $count = StokOpname::whereDate('created_at', now()->toDateString())->count() + 1;
            $noOpname = sprintf('OPN-%s-%04d', $today, $count);

            $opname = StokOpname::create([
                'no_opname' => $noOpname,
                'gudang_id' => $this->opnameGudangId,
                'rak_id' => $this->opnameRakId,
                'user_id' => auth()->id() ?? 1,
                'status' => 'menunggu_approval',
                'catatan' => $this->opnameCatatan,
            ]);

            foreach ($this->opnameRows as $row) {
                StokOpnameItem::create([
                    'stok_opname_id' => $opname->id,
                    'produk_id' => $row['produk_id'],
                    'sku_variant_id' => null,
                    'rak_id' => $this->opnameRakId, // [T-14] scope per rak
                    'stok_sistem' => $row['stok_sistem'],
                    'stok_fisik' => $row['stok_fisik'],
                    'selisih' => $row['selisih'],
                ]);
            }
        });

        $this->showOpnameModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Stock opname tersimpan & menunggu approval']);
    }

    public function approveOpname(int $opnameId)
    {
        $opname = StokOpname::with('items')->findOrFail($opnameId);

        DB::transaction(function () use ($opname) {
            foreach ($opname->items as $item) {
                $stok = StokItem::firstOrCreate(
                    [
                        'gudang_id' => $opname->gudang_id,
                        'produk_id' => $item->produk_id,
                        'sku_variant_id' => $item->sku_variant_id,
                        'rak_id' => $item->rak_id ?? $opname->rak_id, // [T-14] scope rak
                    ],
                    ['jumlah' => 0, 'jumlah_minimum' => 0]
                );

                $sebelum = $stok->jumlah;
                $stok->update(['jumlah' => $item->stok_fisik]);

                if ($item->selisih !== 0) {
                    // [B-10i] user_id = approver opname (sama dgn StokLog & approver_id
                    // di bawah) — mutasi stok harus bisa dibuktikan pelakunya.
                    StockMutationLog::create([
                        'produk_id' => $item->produk_id,
                        'sku_variant_id' => $item->sku_variant_id,
                        'gudang_id' => $opname->gudang_id,
                        'user_id' => auth()->id(),
                        'delta' => $item->selisih,
                        'sumber' => 'opname',
                        'referensi_tipe' => StokOpname::class,
                        'referensi_id' => $opname->id,
                        'terjadi_at' => now(),
                    ]);

                    StokLog::create([
                        'gudang_id' => $opname->gudang_id,
                        'produk_id' => $item->produk_id,
                        'sku_variant_id' => $item->sku_variant_id,
                        'user_id' => auth()->id(),
                        'jenis' => 'opname',
                        'referensi_tipe' => StokOpname::class,
                        'referensi_id' => $opname->id,
                        'jumlah_sebelum' => $sebelum,
                        'perubahan' => $item->selisih,
                        'jumlah_setelah' => $item->stok_fisik,
                        'catatan' => "Approval Opname {$opname->no_opname}",
                    ]);
                }
            }

            $opname->update([
                'status' => 'disetujui',
                'approver_id' => auth()->id() ?? 1,
                'tanggal_approval' => now(),
            ]);
        });

        $this->dispatch('alert', ['type' => 'success', 'message' => 'Stock opname disetujui & stok sistem berhasil diselaraskan']);
    }

    public function render()
    {
        $gudangs = Gudang::where('is_active', true)->get();

        // Opname Query
        // [B-15c] Blade hanya butuh JUMLAH item per opname → `withCount('items')`
        // (1 subquery COUNT) menggantikan `with('items.produk')` yang menarik
        // 2.000-10.000 baris `stok_opname_item` + satu query produk per item sia-sia.
        $opnames = StokOpname::with(['gudang', 'pembuat', 'approver'])
            ->withCount('items')
            ->latest()
            ->take(20)
            ->get();

        return view('modules.wms.livewire.opname-tab', [
            'gudangs' => $gudangs,
            'opnames' => $opnames,
            'raks' => Rak::with('gudang')->get(),
        ]);
    }
}
