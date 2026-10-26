<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\StokOpname;
use App\Modules\Wms\Models\StokOpnameItem;
use App\Modules\Wms\Services\ScanResolverService;
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

    public string $opnameTipeTarget = 'rak'; // 'rak', 'kategori', 'brand', 'semua'

    public ?int $opnameKategoriId = null;

    public ?int $opnameBrandId = null;

    public string $opnameSearch = '';

    public string $opnameScanInput = '';

    public ?int $activeItemIndex = null;

    public string $activeItemFisik = '';

    public string $filterStatusHitung = 'semua'; // 'semua', 'terhitung', 'belum'

    public string $opnameCatatan = '';

    public array $opnameRows = []; // [['produk_id' => ..., 'nama' => ..., 'stok_sistem' => ..., 'stok_fisik' => ...]]

    // Active Opname View / Review
    public ?int $selectedOpnameId = null;

    public bool $showDetailModal = false;

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
        $this->opnameTipeTarget = 'rak';
        $this->opnameKategoriId = null;
        $this->opnameBrandId = null;
        $this->opnameSearch = '';
        $this->opnameScanInput = '';
        $this->activeItemIndex = null;
        $this->activeItemFisik = '';
        $this->filterStatusHitung = 'semua';
        $this->opnameCatatan = '';

        $this->muatItemOpname();

        $this->showOpnameModal = true;
    }

    public function updatedOpnameGudangId(): void
    {
        $this->muatItemOpname();
    }

    public function updatedOpnameRakId(): void
    {
        $this->muatItemOpname();
    }

    public function updatedOpnameTipeTarget(): void
    {
        $this->muatItemOpname();
    }

    public function updatedOpnameKategoriId(): void
    {
        $this->muatItemOpname();
    }

    public function updatedOpnameBrandId(): void
    {
        $this->muatItemOpname();
    }

    /**
     * Muat item yang akan di-opname sesuai filter target yang dipilih (Rak, Kategori, Brand, Semua).
     */
    public function muatItemOpname(): void
    {
        $gudangId = $this->opnameGudangId ?: $this->filterGudangId;
        if (! $gudangId) {
            $this->opnameRows = [];

            return;
        }

        $query = Produk::query()
            ->with(['skuVariants' => fn ($q) => $q->where('is_active', true), 'brand', 'kategoriRelasi'])
            ->where('is_active', true)
            ->whereHas('stokItems', function ($q) use ($gudangId) {
                $q->where('gudang_id', $gudangId);
                if ($this->opnameTipeTarget === 'rak' && $this->opnameRakId) {
                    $q->where('rak_id', $this->opnameRakId);
                }
            });

        if ($this->opnameTipeTarget === 'kategori' && $this->opnameKategoriId) {
            $kat = KategoriProduk::with('children')->find($this->opnameKategoriId);
            $katIds = $kat ? array_merge([$kat->id], $kat->children->pluck('id')->all()) : [$this->opnameKategoriId];
            $query->whereIn('kategori_id', $katIds);
        } elseif ($this->opnameTipeTarget === 'brand' && $this->opnameBrandId) {
            $query->where('brand_id', $this->opnameBrandId);
        }

        $products = $query->take(100)->get();
        $this->opnameRows = [];

        foreach ($products as $prod) {
            $stok = StokItem::where('produk_id', $prod->id)
                ->where('gudang_id', $gudangId)
                ->when($this->opnameTipeTarget === 'rak' && $this->opnameRakId, fn ($q) => $q->where('rak_id', $this->opnameRakId))
                ->value('jumlah') ?? 0;

            $sku = $prod->skuVariants->first()?->sku ?: $prod->kode;
            $barcode = $prod->barcode ?: $prod->skuVariants->first()?->barcode;

            // Blind Opname: stok_fisik kosong di awal
            $this->opnameRows[] = [
                'produk_id' => $prod->id,
                'nama' => $prod->nama,
                'sku' => $sku,
                'barcode' => $barcode,
                'brand' => $prod->brand?->nama,
                'kategori' => $prod->kategoriRelasi?->nama,
                'stok_sistem' => (int) $stok,
                'stok_fisik' => '',
                'selisih' => 0,
            ];
        }
    }

    public function tambahFisik(int $index): void
    {
        if (isset($this->opnameRows[$index])) {
            $current = is_numeric($this->opnameRows[$index]['stok_fisik']) ? (int) $this->opnameRows[$index]['stok_fisik'] : 0;
            $new = $current + 1;
            $this->updateOpnameFisik($index, $new);
            if ($this->activeItemIndex === $index) {
                $this->activeItemFisik = (string) $new;
            }
        }
    }

    public function kurangFisik(int $index): void
    {
        if (isset($this->opnameRows[$index])) {
            $current = is_numeric($this->opnameRows[$index]['stok_fisik']) ? (int) $this->opnameRows[$index]['stok_fisik'] : 0;
            $new = max(0, $current - 1);
            $this->updateOpnameFisik($index, $new);
            if ($this->activeItemIndex === $index) {
                $this->activeItemFisik = (string) $new;
            }
        }
    }

    public function pilihItemAktif(int $index): void
    {
        if (isset($this->opnameRows[$index])) {
            $this->activeItemIndex = $index;
            $val = $this->opnameRows[$index]['stok_fisik'];
            $this->activeItemFisik = is_numeric($val) ? (string) $val : '';
            $this->dispatch('focus-active-qty');
        }
    }

    public function tambahActiveFisik(): void
    {
        $current = is_numeric($this->activeItemFisik) ? (int) $this->activeItemFisik : 0;
        $new = $current + 1;
        $this->activeItemFisik = (string) $new;
        if ($this->activeItemIndex !== null) {
            $this->updateOpnameFisik($this->activeItemIndex, $new);
        }
    }

    public function kurangActiveFisik(): void
    {
        $current = is_numeric($this->activeItemFisik) ? (int) $this->activeItemFisik : 0;
        $new = max(0, $current - 1);
        $this->activeItemFisik = (string) $new;
        if ($this->activeItemIndex !== null) {
            $this->updateOpnameFisik($this->activeItemIndex, $new);
        }
    }

    public function updatedActiveItemFisik($val): void
    {
        if ($this->activeItemIndex !== null && isset($this->opnameRows[$this->activeItemIndex])) {
            $qty = $val === '' ? '' : (int) $val;
            $this->updateOpnameFisik($this->activeItemIndex, $qty);
        }
    }

    public function simpanDanLanjut(): void
    {
        if ($this->activeItemIndex !== null && isset($this->opnameRows[$this->activeItemIndex])) {
            $qty = is_numeric($this->activeItemFisik) ? (int) $this->activeItemFisik : 0;
            $this->updateOpnameFisik($this->activeItemIndex, $qty);
            $nama = $this->opnameRows[$this->activeItemIndex]['nama'];
            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Hitungan '{$nama}' ({$qty} unit) tersimpan. Siap scan barang berikutnya.",
            ]);
        }

        $this->activeItemIndex = null;
        $this->activeItemFisik = '';
        $this->opnameScanInput = '';
        $this->dispatch('focus-scan-input');
    }

    public function lewatiActiveItem(): void
    {
        $this->activeItemIndex = null;
        $this->activeItemFisik = '';
        $this->opnameScanInput = '';
        $this->dispatch('focus-scan-input');
    }

    public function scanBarcodeDirect(string $code): void
    {
        $clean = app(ScanResolverService::class)->cleanCode($code);
        if ($clean === '') {
            return;
        }

        $foundIdx = null;
        foreach ($this->opnameRows as $idx => $r) {
            if (($r['barcode'] && strcasecmp((string) $r['barcode'], $clean) === 0) ||
                ($r['sku'] && strcasecmp((string) $r['sku'], $clean) === 0) ||
                (stripos((string) $r['nama'], $clean) !== false)) {
                $foundIdx = $idx;
                break;
            }
        }

        if ($foundIdx !== null) {
            $this->activeItemIndex = $foundIdx;
            $current = is_numeric($this->opnameRows[$foundIdx]['stok_fisik']) ? (int) $this->opnameRows[$foundIdx]['stok_fisik'] : 0;
            $newQty = $current + 1;
            $this->activeItemFisik = (string) $newQty;
            $this->updateOpnameFisik($foundIdx, $newQty);

            $nama = $this->opnameRows[$foundIdx]['nama'];
            $this->dispatch('alert', ['type' => 'success', 'message' => "Produk '{$nama}' aktif. Hitungan: {$newQty} unit."]);
            $this->dispatch('focus-active-qty');
        } else {
            // Coba cari produk di database yang ada di gudang ini
            $gudangId = $this->opnameGudangId ?: $this->filterGudangId;
            $prod = Produk::where('is_active', true)
                ->where(function ($q) use ($clean) {
                    $q->where('barcode', $clean)
                        ->orWhere('sku', $clean)
                        ->orWhere('kode', $clean)
                        ->orWhereHas('skuVariants', fn ($sv) => $sv->where('barcode', $clean)->orWhere('sku', $clean));
                })
                ->first();

            if (! $prod) {
                $prod = Produk::where('is_active', true)->cariPintar($clean)->first();
            }

            if ($prod) {
                $stok = StokItem::where('produk_id', $prod->id)->where('gudang_id', $gudangId)->value('jumlah') ?? 0;
                $newIndex = count($this->opnameRows);
                $this->opnameRows[] = [
                    'produk_id' => $prod->id,
                    'nama' => $prod->nama,
                    'sku' => $prod->skuVariants->first()?->sku ?: $prod->kode,
                    'barcode' => $prod->barcode,
                    'brand' => $prod->brand?->nama,
                    'kategori' => $prod->kategoriRelasi?->nama,
                    'stok_sistem' => (int) $stok,
                    'stok_fisik' => 1,
                    'selisih' => 1 - (int) $stok,
                ];
                $this->activeItemIndex = $newIndex;
                $this->activeItemFisik = '1';
                $this->dispatch('alert', ['type' => 'success', 'message' => "Produk '{$prod->nama}' ditambahkan ke list opname dengan hitungan 1."]);
                $this->dispatch('focus-active-qty');
            } else {
                $this->dispatch('alert', ['type' => 'error', 'message' => "Barcode/SKU '{$clean}' tidak ditemukan."]);
            }
        }

        $this->opnameScanInput = '';
    }

    public function updateOpnameFisik(int $index, $value)
    {
        $val = $value === '' ? '' : (int) $value;
        $this->opnameRows[$index]['stok_fisik'] = $val;
        $stokFisik = is_numeric($val) ? (int) $val : 0;
        $this->opnameRows[$index]['selisih'] = $stokFisik - (int) ($this->opnameRows[$index]['stok_sistem'] ?? 0);
    }

    public function saveOpname()
    {
        if (! $this->boleh('wms.opname', 'Anda tidak memiliki izin mengajukan stok opname.')) {
            return;
        }

        $this->validate([
            'opnameGudangId' => 'required|exists:gudang,id',
            'opnameRakId' => 'nullable|exists:rak,id',
            'opnameRows' => 'required|array|min:1',
        ]);

        $gudang = Gudang::find($this->opnameGudangId);
        $cabangId = session('cabang_id');
        $user = auth()->user();
        if ($cabangId && $gudang && ! isSuperAdminOrOwner($user) && (int) $gudang->cabang_id !== (int) $cabangId) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Gudang opname bukan milik cabang aktif.']);

            return;
        }

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
                $stokFisik = is_numeric($row['stok_fisik']) ? (int) $row['stok_fisik'] : 0;
                $stokSistem = (int) ($row['stok_sistem'] ?? 0);
                $selisih = $stokFisik - $stokSistem;

                StokOpnameItem::create([
                    'stok_opname_id' => $opname->id,
                    'produk_id' => $row['produk_id'],
                    'sku_variant_id' => null,
                    'rak_id' => $this->opnameRakId, // [T-14] scope per rak
                    'stok_sistem' => $stokSistem,
                    'stok_fisik' => $stokFisik,
                    'selisih' => $selisih,
                ]);
            }
        });

        $this->showOpnameModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Stock opname tersimpan & menunggu approval']);
    }

    public function approveOpname(int $opnameId)
    {
        if (! $this->boleh('wms.approve-opname', 'Anda tidak memiliki izin menyetujui stok opname.')) {
            return;
        }

        $opname = StokOpname::with(['items.produk', 'gudang'])->findOrFail($opnameId);

        if ($opname->status !== 'menunggu_approval') {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Hanya stock opname berstatus menunggu approval yang dapat disetujui.']);

            return;
        }

        // [FOUR-EYES PRINCIPLE] Pembuat pengajuan dilarang menyetujui dokumen buatannya sendiri
        if ($opname->user_id && $opname->user_id === auth()->id() && ! isSuperAdminOrOwner()) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => 'Prinsip 4-Mata (Four-Eyes Principle): Pembuat stock opname tidak boleh menyetujui dokumen buatannya sendiri demi mencegah fraud. Persetujuan harus dilakukan oleh staf lain atau atasan.',
            ]);

            return;
        }

        DB::transaction(function () use ($opname) {
            $jurnalLines = [];
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

                    // Jurnal penyesuaian keuangan persis prinsip akuntansi:
                    $hargaBeli = (float) ($item->produk?->harga_beli ?? 0);
                    $nominal = round(abs($item->selisih) * $hargaBeli, 2);

                    if ($nominal > 0) {
                        $jurnalLines[] = $item->selisih > 0
                            ? ['akun_kode' => '130-01', 'debit' => $nominal, 'kredit' => 0]
                            : ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => $nominal];
                        $jurnalLines[] = $item->selisih > 0
                            ? ['akun_kode' => '520-08', 'debit' => 0, 'kredit' => $nominal]
                            : ['akun_kode' => '520-08', 'debit' => $nominal, 'kredit' => 0];
                    }
                }
            }

            if (! empty($jurnalLines)) {
                $cabangId = $opname->gudang?->cabang_id ?? session('cabang_id');
                $jurnalService = app(JurnalService::class);
                $jurnalService->post(
                    $jurnalService->generateNoJurnal('opname', $cabangId),
                    now(),
                    'opname',
                    $jurnalLines,
                    "Penyesuaian Stock Opname {$opname->no_opname}",
                    $cabangId,
                    auth()->id(),
                    StokOpname::class,
                    $opname->id
                );
            }

            $opname->update([
                'status' => 'disetujui',
                'approver_id' => auth()->id() ?? 1,
                'tanggal_approval' => now(),
            ]);
        });

        $this->dispatch('alert', ['type' => 'success', 'message' => 'Stock opname disetujui & stok sistem berhasil diselaraskan']);
    }

    public function bukaDetail(int $opnameId): void
    {
        $this->selectedOpnameId = $opnameId;
        $this->showDetailModal = true;
    }

    public function tutupDetail(): void
    {
        $this->selectedOpnameId = null;
        $this->showDetailModal = false;
    }

    protected function boleh(string $permission, string $pesan = 'Anda tidak memiliki hak akses untuk tindakan ini.'): bool
    {
        if (auth()->user()?->can($permission)) {
            return true;
        }

        $this->dispatch('alert', ['type' => 'error', 'message' => $pesan]);

        return false;
    }

    public function render()
    {
        $cabangId = session('cabang_id');
        $gudangs = Gudang::when($cabangId, fn ($q, $c) => $q->where('cabang_id', $c))
            ->where('is_active', true)
            ->get();

        // Opname Query
        // [B-15c] Blade hanya butuh JUMLAH item per opname → `withCount('items')`
        // (1 subquery COUNT) menggantikan `with('items.produk')` yang menarik
        // 2.000-10.000 baris `stok_opname_item` + satu query produk per item sia-sia.
        $opnames = StokOpname::with(['gudang', 'pembuat', 'approver'])
            ->withCount('items')
            ->latest()
            ->take(20)
            ->get();

        $selectedOpname = null;
        $mutasiLogs = collect();
        if ($this->showDetailModal && $this->selectedOpnameId) {
            $selectedOpname = StokOpname::with([
                'gudang',
                'rak',
                'pembuat',
                'approver',
                'items.produk',
                'items.skuVariant',
                'items.rak',
            ])->find($this->selectedOpnameId);

            $mutasiLogs = StokLog::with(['gudang', 'produk', 'skuVariant', 'user'])
                ->where('referensi_tipe', StokOpname::class)
                ->where('referensi_id', $this->selectedOpnameId)
                ->orderBy('created_at')
                ->get();
        }

        return view('modules.wms.livewire.opname-tab', [
            'gudangs' => $gudangs,
            'opnames' => $opnames,
            'raks' => Rak::with('gudang')->get(),
            'kategoris' => KategoriProduk::where('is_active', true)->orderBy('nama')->get(),
            'brands' => Brand::where('is_active', true)->orderBy('nama')->get(),
            'selectedOpname' => $selectedOpname,
            'mutasiLogs' => $mutasiLogs,
        ]);
    }
}
