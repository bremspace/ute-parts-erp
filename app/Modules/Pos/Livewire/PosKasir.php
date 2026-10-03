<?php

namespace App\Modules\Pos\Livewire;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Akunting\Services\PajakService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Services\PelangganService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Pos\Services\HargaFleksibelService;
use App\Modules\Pos\Services\KasSesiState;
use App\Modules\Pos\Services\PricingService;
use App\Modules\Pos\Services\ReturnPenjualanService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Traits\PunyaRiwayatAktivitas;
use App\Modules\Reseller\Services\KomisiService;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Services\ServisService;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Services\NomorSeriService;
use App\Modules\Wms\Services\StokDeductionService;
use App\Traits\ParsesNominal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class PosKasir extends Component
{
    use ParsesNominal;
    use PunyaRiwayatAktivitas;

    /** [B-02/P1-5] Jumlah produk per muat katalog (load-more). */
    public const BATAS_PRODUK_AWAL = 16;

    /**
     * [B-02] Stok saat belum ada gudang aktif = 0 (bukan batas longgar).
     * Kebijakan owner final: stok 0 = produk TIDAK BISA DIPILIH (hard-block,
     * backorder dibatalkan). Nilai 0 ikut Testosterone "satu kriteria stok"
     * supaya kartu katalog & addToCart() tidak punya jalan bypass lewat
     * "gudang belum dipilih" — pemanggil wajib pesan "Pilih gudang terlebih
     * dahulu".
     */
    public const STOK_TANPA_GUDANG = 0;

    // [T-09] state sesi kas
    public $kasAktif = null;

    // Search & Filter state
    public string $search = '';

    // [B-02/P1-5] Batas katalog yang dimuat (load-more "Muat lebih banyak")
    public int $batasProduk = self::BATAS_PRODUK_AWAL;

    public ?int $selectedCustomerId = null;

    public ?int $selectedGudangId = null;

    // Cart state: [item_key => ['produk_id', 'sku_variant_id', 'nama', 'varian', 'harga', 'qty', 'diskon', 'subtotal', 'stok_max']]
    public array $cart = [];

    // Summary state
    public float $diskonPersen = 0.0;

    public float $diskonNominal = 0.0;

    public float $pajakNominal = 0.0;

    // [F1-2] DPP (dasar pengenaan PPN) = subtotal - diskon — ikut pajakNominal di totalAkhir
    public float $dpp = 0.0;

    public float $ppnPersen = 0.0;

    // Payment modal state
    public bool $showPaymentModal = false;

    public string $metodeBayar = 'tunai'; // tunai, transfer, qris, split

    public mixed $jumlahBayar = 0.0;

    public string $catatan = '';

    // Split payment details
    public mixed $splitTunai = 0.0;

    public mixed $splitNonTunai = 0.0;

    public string $splitMetodeNonTunai = 'qris';

    // Receipt modal state
    public bool $showReceiptModal = false;

    public ?int $completedTransactionId = null;

    public ?array $receiptData = null;

    // Riwayat transaksi modal state
    public bool $showRiwayatTransaksiModal = false;

    // Retur Penjualan modal state
    public bool $showReturPenjualanModal = false;

    public ?int $selectedTransaksiReturId = null;

    public array $returItemInputs = [];

    public string $returAlasan = '';

    public string $returMetodePengembalian = 'kas';

    // [T-03] Transaksi ditahan (park)
    public bool $showDitahanPanel = false;

    // [T-08] Pencarian & tambah pelanggan quick
    public string $pelangganSearch = '';

    public bool $showPelangganBaruModal = false;

    public array $pelangganBaruForm = ['nama' => '', 'telepon' => '', 'email' => '', 'alamat' => '', 'tanggal_lahir' => ''];

    // [T-09] Kas sesi modal
    public bool $showKasModal = false;

    public bool $kasModeBuka = true;

    // Raw input values (user types "1.000.000" → stored as "1000000")
    public string $kasSaldoAwalRaw = '';

    public string $kasSaldoFisikRaw = '';

    // Parsed numeric values for backend
    public float $kasSaldoAwal = 0;

    public float $kasSaldoFisik = 0;

    public ?array $kasHasil = null;

    // [T-33] sumber saldo awal sesi: manual | carryover (diisi dari sesi tutup sebelumnya)
    public string $kasSumber = 'manual';

    // [KAS-LACI] Sumber dana dropdown + pending approval state
    public array $akunSumberList = [];

    public string $selectedAkunSumber = '110-01';

    public bool $kasPendingApproval = false;

    // [KAS-LACI-MUTASI] Modal Mutasi Kas Laci (In/Out non-POS)
    public bool $showMutasiKasModal = false;

    public string $mutasiJenis = 'keluar'; // 'masuk' | 'keluar'

    public string $mutasiNominalRaw = '';

    public float $mutasiNominal = 0;

    public string $mutasiAkunLawan = '';

    public string $mutasiKeterangan = '';

    public array $mutasiKategoriList = [];

    public array $riwayatMutasiSesi = [];

    // [F2-3] Scan/autocomplete nomor seri utk item keranjang sn=true
    public string $snSearch = '';

    public string $snItemKey = '';

    // Harga fleksibel permission guard
    public bool $canHargaFleksibel = false;

    // [POS-SERVIS] Integrasi pembayaran servis di kasir POS
    public bool $showBayarServisModal = false;

    public string $searchServis = '';

    public ?int $selectedServisId = null;

    public ?array $selectedServisDetail = null;

    public string $servisMetodeBayar = 'tunai';

    public mixed $servisJumlahBayar = 0;

    public mixed $servisKembalian = 0;

    public mixed $servisSplitTunai = 0;

    public mixed $servisSplitNonTunai = 0;

    public string $servisSplitMetodeNonTunai = 'qris';

    public bool $servisUbahStatusDiambil = true;

    public string $servisCatatan = '';

    public function mount()
    {
        // Set default gudang dari active session cabang
        $cabangId = session('cabang_id');
        if ($cabangId) {
            $this->selectedGudangId = Gudang::where('cabang_id', $cabangId)->value('id');
        }

        // [T-09] Deteksi sesi kas terbuka utk user/cabang
        $this->checkKasSesi();

        // [T-09] Auto-open modal buka kas saat masuk POS bila sesi belum aktif (transaksi tunai diblokir)
        if (! app(KasSesiState::class)->isActiveSesi()) {
            $this->bukaKasModal();
        }

        // Harga fleksibel permission
        $this->canHargaFleksibel = auth()->user()?->hasPermissionTo('atur-harga-fleksibel') ?? false;

        // [POS-SERVIS] Direct link dari Kanban Servis / Riwayat
        if (request()->has('bayar_servis_id')) {
            $this->bukaBayarServisModal((int) request()->query('bayar_servis_id'));
        } elseif (request()->has('tiket_servis_id')) {
            $this->bukaBayarServisModal((int) request()->query('tiket_servis_id'));
        }
    }

    /**
     * [B-02/P1-2] Gudang wajib milik cabang aktif (session cabang_id).
     * selectedGudangId adalah prop publik Livewire → bisa di-tamper lintas cabang;
     * bila tidak valid → reset ke gudang cabang ini + pesan Indonesia.
     */
    private function validasiGudangCabang(): void
    {
        $cabangId = session('cabang_id');
        if (! $cabangId) {
            return;
        }

        if ($this->selectedGudangId
            && Gudang::where('id', $this->selectedGudangId)->where('cabang_id', $cabangId)->exists()) {
            return;
        }

        $gudangCabang = Gudang::where('cabang_id', $cabangId)->orderByDesc('is_active')->orderBy('id')->value('id');

        if ($this->selectedGudangId !== $gudangCabang) {
            $this->dispatch('alert', [
                'type' => 'warning',
                'message' => 'Gudang tidak sesuai cabang aktif — diganti ke gudang cabang ini',
            ]);
        }

        $this->selectedGudangId = $gudangCabang;
    }

    /**
     * [B-02/P0-1] SATU kriteria stok utk gauge kartu, addToCart() & updateQty():
     * - varian terisi (scan barcode) → baris varian eksak;
     * - tanpa varian (klik kartu) → agregat produk+gudang (SUM seluruh baris varian),
     *   sama persis dgn angka yang dilihat kasir di kartu.
     *
     * Tanpa gudang → 0 (STOK_TANPA_GUDANG): produk tidak boleh dipilih sebelum
     * kasir memilih gudang.
     */
    private function hitungStokTersedia(int $produkId, ?int $variantId, ?int $gudangId): int
    {
        if (! $gudangId) {
            return self::STOK_TANPA_GUDANG;
        }

        return (int) StokItem::where('produk_id', $produkId)
            ->where('gudang_id', $gudangId)
            ->when($variantId, fn ($q) => $q->where('sku_variant_id', $variantId))
            ->sum('jumlah');
    }

    /**
     * [B-02/P1-5] Peta stok per produk utk katalog (sekali query — hindari N+1
     * saat load-more), kriteria sama dgn hitungStokTersedia().
     *
     * @return array<int, int>
     */
    private function petaStokKatalog(Collection $products): array
    {
        if (! $this->selectedGudangId || $products->isEmpty()) {
            return [];
        }

        return StokItem::whereIn('produk_id', $products->pluck('id'))
            ->where('gudang_id', $this->selectedGudangId)
            ->groupBy('produk_id')
            ->selectRaw('produk_id, SUM(jumlah) AS total')
            ->pluck('total', 'produk_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    public function checkKasSesi(): void
    {
        $svc = app(KasSesiState::class);
        $this->kasAktif = $svc->sesiKasAktif();

        // [KAS-LACI] Cek apakah ada sesi berstatus menunggu_approval untuk kasir ini
        if (! $this->kasAktif) {
            $cabangId = session('cabang_id') ?? auth()->user()?->cabangs()->first()?->id ?? 1;
            $this->kasPendingApproval = DB::table('kas_sesi')
                ->where('cabang_id', $cabangId)
                ->where('status', 'menunggu_approval')
                ->where('user_id', auth()->id())
                ->exists();
        } else {
            $this->kasPendingApproval = false;
        }
    }

    public function getKasAktifProperty()
    {
        return app(KasSesiState::class)->sesiKasAktif();
    }

    public function getCustomerProperty()
    {
        return $this->selectedCustomerId
            ? Pelanggan::with('tierMembership')->find($this->selectedCustomerId)
            : null;
    }

    public function getSubtotalProperty(): float
    {
        return array_reduce($this->cart, fn ($carry, $item) => $carry + ($item['subtotal'] ?? 0), 0.0);
    }

    public function getTotalAkhirProperty(): float
    {
        $sub = $this->subtotal;
        $diskon = $this->diskonNominal;
        if ($this->diskonPersen > 0) {
            $diskon += round(($sub * $this->diskonPersen) / 100, 2);
        }

        return max(0.0, $sub - $diskon + $this->pajakNominal);
    }

    /**
     * Rekonsiliasi otomatis PPN per cabang (diaktifkan via konfigurasi pajak_enabled).
     * Dipanggil setelah cart / diskon berubah (add/update/remove/setHargaFleksibel/clear/updatedDiskon*).
     * [F1-2] DPP = subtotal - diskonTotal; pajakNominal = PPN dari DPP.
     */
    private function recalcPajak(): void
    {
        $cabangId = session('cabang_id');
        $pajakService = app(PajakService::class);
        $dpp = max(0.0, $this->subtotal - $this->diskonTotal);
        $this->dpp = $dpp;

        $enabled = $cabangId ? $pajakService->enabled($cabangId) : false;

        if (! $enabled) {
            $this->pajakNominal = 0.0;
            $this->ppnPersen = 0.0;

            return;
        }

        $result = $pajakService->hitung($cabangId, $dpp);
        $this->pajakNominal = $result['ppn_nominal'];
        $this->ppnPersen = $result['ppn_percent'];
    }

    // [F1-2] Diskon berubah → wajib rehitung PPN (DPP berubah)
    public function updatedDiskonPersen(): void
    {
        $this->resetComputedTotals();
        $this->recalcPajak();
    }

    public function updatedDiskonNominal(): void
    {
        $this->resetComputedTotals();
        $this->recalcPajak();
    }

    /**
     * Reset Livewire memoized computed property cache.
     * Wajib dipanggil saat cart/diskon berubah agar getter getXxxProperty()
     * mengevaluasi state keranjang terbaru pada request yang sama.
     */
    private function resetComputedTotals(): void
    {
        unset(
            $this->subtotal,
            $this->totalAkhir,
            $this->total,
            $this->diskonTotal,
            $this->totalBayar,
            $this->kembalian,
            $this->customer
        );
    }

    // For cart partial
    public function getTotalProperty(): float
    {
        return $this->subtotal;
    }

    public function getDiskonTotalProperty(): float
    {
        $diskon = $this->diskonNominal;
        if ($this->diskonPersen > 0) {
            $diskon += round(($this->subtotal * $this->diskonPersen) / 100, 2);
        }

        return $diskon;
    }

    public function getTotalBayarProperty(): float
    {
        return $this->totalAkhir;
    }

    public function getKembalianProperty(): float
    {
        if ($this->metodeBayar === 'tunai') {
            return max(0.0, $this->jumlahBayar - $this->totalAkhir);
        }
        if ($this->metodeBayar === 'split') {
            $totalBayar = $this->splitTunai + $this->splitNonTunai;

            return max(0.0, $totalBayar - $this->totalAkhir);
        }

        return 0.0;
    }

    /** [T-07] Enter = scan barcode/SKU exact-match → auto add ke keranjang. */
    public function scanEnter(): void
    {
        $q = trim($this->search);
        if ($q === '') {
            return;
        }

        // 1. Cek exact match barcode atau SKU pada varian aktif
        $variant = SkuVariant::where('is_active', true)
            ->where(function ($query) use ($q) {
                $query->where('barcode', $q)->orWhere('sku', $q);
            })
            ->first();

        if ($variant) {
            $this->addToCart($variant->produk_id, $variant->id);
            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Item '{$variant->nama_varian}' ditambahkan ke keranjang.",
            ]);
            $this->search = '';

            return;
        }

        // 2. Cek exact match barcode produk, ID (aman non-overflow), atau nama produk
        $produk = Produk::where('is_active', true)
            ->where(function ($query) use ($q) {
                $query->where('barcode', $q)
                    ->when(is_numeric($q) && (float) $q <= 2147483647, fn ($sub) => $sub->orWhere('id', (int) $q))
                    ->orWhere('nama', $q);
            })
            ->first();

        if ($produk) {
            $this->addToCart($produk->id);
            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Produk '{$produk->nama}' ditambahkan ke keranjang.",
            ]);
            $this->search = '';

            return;
        }

        // Jika tidak ditemukan: KOSONGKAN kembali search agar katalog kasir TIDAK hilang/freeze
        $this->search = '';

        $this->dispatch('alert', [
            'type' => 'warning',
            'message' => "Barcode/SKU '{$q}' belum terdaftar di sistem.",
        ]);

        $this->dispatch('ute:barcode-not-found', ['code' => $q]);
    }

    /** Scan barcode langsung dari scanner kamera mobile / hardware keyboard wedge. */
    public function scanBarcodeDirect(string $code): void
    {
        $this->search = trim($code);
        $this->scanEnter();
    }

    /**
     * [B-02/P0-1] Hard-block stok kosong (kebijakan owner final — backorder
     * dibatalkan): produk dgn stok 0 di gudang aktif TIDAK BOLEH masuk keranjang.
     * Pesan berbahasa Indonesia; kalau masalahnya gudang belum dipilih, kasir
     * diberi tahu untuk memilih gudang lebih dulu.
     */
    private function blokirStokKosong(Produk $produk): void
    {
        $this->dispatch('alert', [
            'type' => 'error',
            'message' => $this->selectedGudangId
                ? "Stok {$produk->nama} habis di gudang ini — pilih gudang lain yang punya stok."
                : "Pilih gudang terlebih dahulu untuk melihat & memakai stok {$produk->nama}.",
        ]);
    }

    public function addToCart(int $produkId, ?int $variantId = null)
    {
        $produk = Produk::findOrFail($produkId);
        $variant = $variantId ? SkuVariant::find($variantId) : null;
        $itemKey = $variantId ? "{$produkId}-{$variantId}" : "{$produkId}-0";

        // [B-02/P1-2] Gudang terpilih wajib milik cabang aktif (anti tamper lintas cabang)
        $this->validasiGudangCabang();

        // [B-02/P0-1] Hard-block stok kosong — dicek SEBELUM ada efek apa pun
        // (tidak ada item, tidak ada PPN yang bergeser). Stok dgn kriteria yg
        // sama persis dgn gauge di kartu produk.
        $stokTersedia = $this->hitungStokTersedia($produkId, $variantId, $this->selectedGudangId);
        if ($stokTersedia <= 0) {
            $this->blokirStokKosong($produk);

            return;
        }

        // Resolusi harga
        $pricingService = app(PricingService::class);
        $pricing = $pricingService->resolve($produk, $this->customer, $variant);
        $harga = $pricing['harga'];

        // Harga fleksibel: hanya superadmin boleh input manual
        if ($produk->harga_fleksibel) {
            if (! $this->canHargaFleksibel) {
                $this->dispatch('alert', [
                    'type' => 'error',
                    'message' => "{$produk->nama} harga fleksibel — perlu superadmin untuk menginput harga",
                ]);

                return;
            }

            // Superadmin: pakai harga minimum (HPP) sebagai default, flag flex=true
            $hargaMin = app(HargaFleksibelService::class)->hargaMinimum($produk);
            $harga = max($harga, $hargaMin);
        }

        if (isset($this->cart[$itemKey])) {
            // Batas keras = stok tersedia riil di gudang aktif
            if ($this->cart[$itemKey]['qty'] + 1 > $stokTersedia) {
                $this->dispatch('alert', [
                    'type' => 'warning',
                    'message' => "Stok {$produk->nama} maksimal {$stokTersedia} unit di gudang ini",
                ]);

                return;
            }
            $this->cart[$itemKey]['qty'] += 1;
            $this->cart[$itemKey]['subtotal'] = $this->cart[$itemKey]['qty'] * $harga;
            $this->cart[$itemKey]['stok_max'] = $stokTersedia;
        } else {
            $this->cart[$itemKey] = [
                'produk_id' => $produkId,
                'sku_variant_id' => $variantId,
                'nama' => $produk->nama,
                'varian' => $variant?->nama_varian ?? 'Standar',
                'harga' => $harga,
                'qty' => 1,
                'diskon' => (float) ($pricing['diskon_nominal'] ?? 0.0),
                'subtotal' => $harga,
                'stok_max' => $stokTersedia,
                'flex' => $produk->harga_fleksibel ? true : false,
                // [F2-3] sn=true → wajib isi SN sebelum bayar
                'sn' => (bool) $produk->sn,
                'sn_list' => [],
            ];
        }

        // Reset computed properties cache & recalc pajak SETELAH item masuk keranjang
        $this->resetComputedTotals();
        $this->recalcPajak();
    }

    /** [B-02/P1-5] Load-more katalog (seluruh produk aktif bisa diakses). */
    public function muatLebihBanyak(): void
    {
        $this->batasProduk += self::BATAS_PRODUK_AWAL;
    }

    /** Pencarian berubah → muat ulang katalog dari awal (batas default). */
    public function updatingSearch(): void
    {
        $this->batasProduk = self::BATAS_PRODUK_AWAL;
    }

    // ==================== [F2-3] NOMOR SERI ====================

    /** Autocomplete SN tersedia utk item keranjang aktif (scope cabang + produk). */
    public function getSnCariProperty()
    {
        if ($this->snItemKey === '' || ! isset($this->cart[$this->snItemKey])) {
            return collect();
        }

        return app(NomorSeriService::class)->cariTersedia(
            (int) (session('cabang_id') ?? 0) ?: null,
            (int) $this->cart[$this->snItemKey]['produk_id'],
            trim($this->snSearch)
        );
    }

    public function setSnItemKey(string $itemKey): void
    {
        if ($this->snItemKey !== $itemKey) {
            $this->snItemKey = $itemKey;
            $this->snSearch = '';
        }
    }

    /** Tambah SN dari input scan/teks (Enter / tombol +). */
    public function pilihSn(string $itemKey): void
    {
        $this->tambahSnKeItem($itemKey, trim($this->snSearch));
    }

    /** Tambah SN dari daftar hasil autocomplete. */
    public function pilihSnLangsung(string $itemKey, string $sn): void
    {
        $this->tambahSnKeItem($itemKey, trim($sn));
    }

    public function hapusSn(string $itemKey, int $idx): void
    {
        if (! isset($this->cart[$itemKey]['sn_list'])) {
            return;
        }
        unset($this->cart[$itemKey]['sn_list'][$idx]);
        $this->cart[$itemKey]['sn_list'] = array_values($this->cart[$itemKey]['sn_list']);
    }

    private function tambahSnKeItem(string $itemKey, string $sn): void
    {
        if ($sn === '' || ! isset($this->cart[$itemKey])) {
            return;
        }

        // [P2-2] SN hanya boleh masuk ke item yang sedang aktif di input SN —
        // snSearch di-typed utk item A tidak boleh masuk ke item B (payload
        // klien bisa memanggil pilihSn/pilihSnLangsung dgn itemKey sembarang).
        if ($itemKey !== $this->snItemKey) {
            $this->dispatch('alert', [
                'type' => 'warning',
                'message' => 'SN tidak ditambahkan — pencarian SN sedang aktif untuk item lain',
            ]);

            return;
        }

        $list = $this->cart[$itemKey]['sn_list'] ?? [];
        if (in_array($sn, $list, true)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => "SN {$sn} sudah dipilih"]);

            return;
        }
        if (count($list) >= (int) $this->cart[$itemKey]['qty']) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Jumlah SN sudah sama dengan qty — kurangi qty atau hapus SN dulu']);

            return;
        }

        $this->cart[$itemKey]['sn_list'] = [...$list, $sn];
        $this->snSearch = '';
    }

    /**
     * [P1-3] Flag sn TIDAK dipercaya dari state publik Livewire ($cart bisa
     * di-tamper client → sn=false utk melewat klaim). Selalu derive ulang dari
     * data produk di server — pola sama dgn resumeDitahan.
     */
    private function snProdukDariItem(array $item): bool
    {
        return (bool) (Produk::find($item['produk_id'] ?? 0)?->sn);
    }

    /** [F2-3] Gate sebelum modal bayar — item sn=true wajib SN lengkap (== qty). */
    private function validasiSnKeranjang(): ?string
    {
        foreach ($this->cart as $item) {
            if (! $this->snProdukDariItem($item)) {
                continue;
            }
            $jumlah = count($item['sn_list'] ?? []);
            $qty = (int) $item['qty'];
            if ($jumlah !== $qty) {
                return "Produk {$item['nama']} wajib {$qty} nomor seri — baru terisi {$jumlah}. Scan/tambah SN dulu.";
            }
        }

        return null;
    }

    /**
     * [B-02/P0-1] Batas keras qty = stok RIIL di gudang aktif (kriteria sama
     * dgn addToCart). `stok_max` di keranjang adalah prop publik Livewire yang
     * bisa di-tamper client → selalu dihitung ulang dari database, bukan
     * dipercaya. Stok 0 = item tidak boleh ada → item dibuang dari keranjang
     * (mis. setelah pindah gudang / resume transaksi ditahan).
     */
    public function updateQty(string $itemKey, int $delta)
    {
        if (! isset($this->cart[$itemKey])) {
            return;
        }

        $newQty = (int) $this->cart[$itemKey]['qty'] + $delta;
        if ($newQty <= 0) {
            $this->removeFromCart($itemKey);

            return;
        }

        $item = $this->cart[$itemKey];
        $stokMax = $this->hitungStokTersedia(
            (int) $item['produk_id'],
            $item['sku_variant_id'] ?: null,
            $this->selectedGudangId
        );

        // Stok habis (atau gudang belum dipilih) → tidak boleh ada item sama sekali
        if ($stokMax <= 0) {
            $this->removeFromCart($itemKey);
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => $this->selectedGudangId
                    ? "Stok {$item['nama']} habis di gudang ini — item dihapus dari keranjang. Pilih gudang lain yang punya stok."
                    : 'Pilih gudang terlebih dahulu — item dihapus dari keranjang.',
            ]);

            return;
        }

        if ($newQty > $stokMax) {
            $this->dispatch('alert', [
                'type' => 'warning',
                'message' => "Stok {$item['nama']} maksimal {$stokMax} unit di gudang ini",
            ]);

            return;
        }

        // Sinkronkan batas keras di keranjang (dipakai cart-content & resume)
        $this->cart[$itemKey]['stok_max'] = $stokMax;
        $this->cart[$itemKey]['qty'] = $newQty;
        $this->cart[$itemKey]['subtotal'] = $newQty * $this->cart[$itemKey]['harga'];

        // [P2-1] Qty turun → sn_list dipangkas (keep first N) agar count == qty
        // segera, bukan baru ketahuan di gate bayar. Qty naik tidak di-fill —
        // gate di openPaymentModal tetap menghitung ulang count == qty.
        $snList = array_values($this->cart[$itemKey]['sn_list'] ?? []);
        if (count($snList) > $newQty) {
            $terbuang = count($snList) - $newQty;
            $this->cart[$itemKey]['sn_list'] = array_slice($snList, 0, $newQty);
            $this->dispatch('alert', [
                'type' => 'warning',
                'message' => "{$terbuang} SN dihapus dari {$this->cart[$itemKey]['nama']} karena qty dikurangi",
            ]);
        }

        $this->resetComputedTotals();
        $this->recalcPajak();
    }

    public function removeFromCart(string $itemKey)
    {
        unset($this->cart[$itemKey]);
        $this->resetComputedTotals();
        $this->recalcPajak();
    }

    public function clearCart()
    {
        $this->cart = [];
        $this->diskonPersen = 0.0;
        $this->diskonNominal = 0.0;
        $this->pajakNominal = 0.0;
        $this->dpp = 0.0;
        $this->ppnPersen = 0.0;
        $this->resetComputedTotals();
        $this->recalcPajak();
    }

    /**
     * Update harga item fleksibel di keranjang (hanya superadmin).
     */
    public function setHargaFleksibel(string $itemKey, float $harga): void
    {
        if (! isset($this->cart[$itemKey])) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Item tidak ditemukan di keranjang']);

            return;
        }

        $item = $this->cart[$itemKey];
        if (! $item['flex']) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Item ini tidak menggunakan harga fleksibel']);

            return;
        }

        $produk = Produk::find($item['produk_id']);
        if (! $produk) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Produk tidak ditemukan']);

            return;
        }

        // Validasi input
        if ($harga < 0) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Harga tidak boleh negatif']);

            return;
        }

        try {
            app(HargaFleksibelService::class)->validasi($produk, $harga, auth()->user());
        } catch (ValidationException $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);

            return;
        }

        $this->cart[$itemKey]['harga'] = $harga;
        $this->cart[$itemKey]['subtotal'] = $this->cart[$itemKey]['qty'] * $harga;
        $this->resetComputedTotals();
        $this->recalcPajak();
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Harga fleksibel diupdate']);
    }

    /**
     * Validasi seluruh keranjang: item fleksibel sudah punya harga valid.
     * Dipanggil di awal saveTransaksi/processTransaction.
     */
    private function validasiCartHargaFleksibel(): ?string
    {
        foreach ($this->cart as $item) {
            if (! ($item['flex'] ?? false)) {
                continue;
            }

            $produk = Produk::find($item['produk_id']);
            if (! $produk) {
                return 'Produk tidak ditemukan di keranjang';
            }

            // Harga sudah diset saat addToCart (harga minimum), tapi guard double-check
            try {
                app(HargaFleksibelService::class)->validasi($produk, (float) $item['harga'], auth()->user());
            } catch (ValidationException $e) {
                return $e->getMessage();
            }
        }

        return null;
    }

    public function setPelanggan(?int $customerId)
    {
        $this->selectedCustomerId = $customerId;
        // Recalculate cart prices when customer/tier changes
        $pricingService = app(PricingService::class);
        foreach ($this->cart as $key => $item) {
            $produk = Produk::find($item['produk_id']);
            $variant = $item['sku_variant_id'] ? SkuVariant::find($item['sku_variant_id']) : null;
            if ($produk) {
                $pricing = $pricingService->resolve($produk, $this->customer, $variant);
                $this->cart[$key]['harga'] = $pricing['harga'];
                $this->cart[$key]['subtotal'] = $this->cart[$key]['qty'] * $pricing['harga'];
            }
        }
        $this->resetComputedTotals();
        $this->recalcPajak();
    }

    public function openPaymentModal()
    {
        if (empty($this->cart)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Keranjang transaksi masih kosong']);

            return;
        }

        // [F2-3] Item sn=true wajib SN lengkap sebelum bayar
        $snError = $this->validasiSnKeranjang();
        if ($snError) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $snError]);

            return;
        }

        $this->resetComputedTotals();
        $this->recalcPajak();
        $this->jumlahBayar = $this->totalAkhir;
        $this->splitTunai = $this->totalAkhir;
        $this->splitNonTunai = 0.0;
        $this->showPaymentModal = true;
    }

    public function setQuickCash(float $nominal)
    {
        $this->jumlahBayar = $nominal;
    }

    public function updatedJumlahBayar($value): void
    {
        $this->jumlahBayar = $this->parseNominal($value);
    }

    public function updatedSplitTunai($value): void
    {
        $this->splitTunai = $this->parseNominal($value);
    }

    public function updatedSplitNonTunai($value): void
    {
        $this->splitNonTunai = $this->parseNominal($value);
    }

    /**
     * [B-02/P0-1] Gate stok sebelum potong: seluruh item keranjang harus punya
     * stok >= qty di gudang aktif. Tanpa gate ini, kegagalan baru ketahuan
     * setelah transaksi dibuat lalu di-rollback (pesan teknis "Stok tidak
     * mencukupi (produk ID …)"). Stok selalu dihitung ulang dari database —
     * `stok_max` di keranjang adalah prop publik Livewire yang bisa di-tamper.
     */
    private function validasiStokKeranjang(): ?string
    {
        foreach ($this->cart as $item) {
            $stokTersedia = $this->hitungStokTersedia(
                (int) $item['produk_id'],
                $item['sku_variant_id'] ?: null,
                $this->selectedGudangId
            );
            $qty = (int) $item['qty'];

            if ($stokTersedia <= 0) {
                return $this->selectedGudangId
                    ? "Stok {$item['nama']} habis di gudang ini — hapus dari keranjang atau pilih gudang lain."
                    : 'Pilih gudang terlebih dahulu sebelum membayar.';
            }

            if ($qty > $stokTersedia) {
                return "Stok {$item['nama']} tidak cukup — tersedia {$stokTersedia} unit, keranjang {$qty} unit.";
            }
        }

        return null;
    }

    public function processTransaction()
    {
        $cabangId = session('cabang_id') ?? auth()->user()?->cabangs()->first()?->id ?? 1;

        // [B-02/P1-2] Gudang wajib milik cabang aktif — validasi ulang sebelum potong stok
        $this->validasiGudangCabang();

        // [B-02/P0-1] Hard-block stok: tidak ada lagi jalur backorder di POS
        $stokError = $this->validasiStokKeranjang();
        if ($stokError) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $stokError]);

            return;
        }

        // Validasi harga fleksibel di keranjang
        $flexError = $this->validasiCartHargaFleksibel();
        if ($flexError) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $flexError]);

            return;
        }

        // [T-09] Validasi sesi kas utk pembayaran tunai (sama dgn API POS-01) — wajib buka kas dulu
        if ($this->metodeBayar === 'tunai' && ! app(KasSesiState::class)->isActiveSesi()) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Kas belum dibuka — buka sesi kas terlebih dahulu sebelum transaksi tunai']);
            $this->bukaKasModal();

            return;
        }

        $this->jumlahBayar = (float) $this->parseNominal($this->jumlahBayar);
        $this->splitTunai = (float) $this->parseNominal($this->splitTunai);
        $this->splitNonTunai = (float) $this->parseNominal($this->splitNonTunai);

        if ($this->metodeBayar === 'tunai' && $this->jumlahBayar < $this->totalAkhir) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Jumlah bayar kurang dari total belanja']);

            return;
        }

        if ($this->metodeBayar === 'split' && ($this->splitTunai + $this->splitNonTunai) < $this->totalAkhir) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Total pembayaran split kurang']);

            return;
        }

        try {
            DB::transaction(function () use ($cabangId) {
                $today = now()->format('Ymd');
                $countToday = Transaksi::whereDate('created_at', now()->toDateString())
                    ->where('cabang_id', $cabangId)
                    ->count() + 1;
                $noTransaksi = sprintf('TRX-C%02d-%s-%04d', $cabangId, $today, $countToday);

                $splitData = $this->metodeBayar === 'split' ? [
                    'tunai' => $this->splitTunai,
                    'non_tunai' => $this->splitNonTunai,
                    'metode_non_tunai' => $this->splitMetodeNonTunai,
                ] : null;

                $transaksi = Transaksi::create([
                    'no_transaksi' => $noTransaksi,
                    'cabang_id' => $cabangId,
                    'kasir_id' => auth()->id() ?? 1,
                    'pelanggan_id' => $this->selectedCustomerId,
                    'gudang_id' => $this->selectedGudangId,
                    'sumber' => 'pos',
                    'subtotal' => $this->subtotal,
                    'diskon_persen' => $this->diskonPersen,
                    'diskon_nominal' => $this->diskonNominal,
                    'dpp' => $this->dpp,
                    'pajak_nominal' => $this->pajakNominal,
                    'ppn_nominal' => $this->pajakNominal,
                    'total_akhir' => $this->totalAkhir,
                    'metode_bayar' => $this->metodeBayar,
                    'jumlah_bayar' => $this->metodeBayar === 'split' ? ($this->splitTunai + $this->splitNonTunai) : $this->jumlahBayar,
                    'kembalian' => $this->kembalian,
                    'split_detail' => $splitData,
                    'status' => 'selesai',
                    'catatan' => $this->catatan,
                ]);

                // Create items & deduct stock
                foreach ($this->cart as $item) {
                    $produk = Produk::find($item['produk_id']);

                    $transaksiItem = TransaksiItem::create([
                        'transaksi_id' => $transaksi->id,
                        'produk_id' => $item['produk_id'],
                        'sku_variant_id' => $item['sku_variant_id'],
                        'jumlah' => $item['qty'],
                        'harga_satuan' => $item['harga'],
                        'diskon_nominal' => $item['diskon'],
                        'subtotal' => $item['subtotal'],
                        'hpp' => $produk ? (float) $produk->harga_beli : 0,
                    ]);

                    // [F2-3] Klaim SN → status terjual + tautan transaksi_item
                    // (validasi: jumlah == qty, unik, ada, tersedia, cabang aktif).
                    // Throw → seluruh transaksi rollback (tidak ada transaksi/jurnal/stok).
                    // [P1-3] Flag sn di-derive ulang dari produk — $cart[i]['sn']
                    // adalah prop publik Livewire yg bisa di-tamper client.
                    if ($this->snProdukDariItem($item)) {
                        app(NomorSeriService::class)->klaimJual(
                            $item['sn_list'] ?? [],
                            (int) $item['produk_id'],
                            (int) $cabangId,
                            (int) $item['qty'],
                            $transaksiItem->id
                        );
                    }

                    if ($this->selectedGudangId) {
                        // [B-02/P1-1] Delegasi ke StokDeductionService: lockForUpdate,
                        // satu kriteria resolusi baris stok, StokLog + StockMutationLog
                        // (untuk sinkron channel).
                        // [B-02/P0-1] izinkanNegatif TIDAK lagi diaktifkan (backorder
                        // dibatalkan owner) → default false = stok kurang DITOLAK.
                        // Garis pertahanan kedua: validasiStokKeranjang() di atas
                        // sudah menolak stok 0/kurang sebelum transaksi dibuat.
                        app(StokDeductionService::class)->kurangi(
                            produkId: (int) $item['produk_id'],
                            skuVariantId: $item['sku_variant_id'] ?: null,
                            gudangId: (int) $this->selectedGudangId,
                            qty: (int) $item['qty'],
                            jenis: 'penjualan',
                            referensiTipe: Transaksi::class,
                            referensiId: $transaksi->id,
                            userId: auth()->id(),
                            catatan: "POS Kasir {$transaksi->no_transaksi}",
                        );
                    }
                }

                // Customer points & spending increment (sinkron CRM)
                if ($this->selectedCustomerId) {
                    app(PelangganService::class)->tambahBelanjaDanPoin($this->selectedCustomerId, (float) $this->totalAkhir);
                }

                // Jurnal akuntansi otomatis (PRD §4.6)
                $jurnalService = app(JurnalService::class);
                $totalHpp = 0.0;
                foreach ($this->cart as $item) {
                    $produk = Produk::find($item['produk_id']);
                    $hppSatuan = $produk ? (float) $produk->harga_beli : 0;
                    $totalHpp += $hppSatuan * (int) $item['qty'];
                }
                $noJurnal = $jurnalService->generateNoJurnal('pos', $cabangId);
                $kasbon = $this->metodeBayar === 'piutang';
                $ppnNominal = (float) $this->pajakNominal;
                $dpp = (float) $this->dpp;

                // [F1-2] Balance: debit totalAkhir = kredit (DPP pendapatan + PPN 220-01)
                // Saat PPN aktif: 410-01 kredit = DPP (bukan totalAkhir) agar seimbang dgn baris 220-01.
                // Akun debit disesuaikan dengan metode bayar:
                // - tunai   : 110-04 (Kas Laci)
                // - piutang : 120-01 (Piutang Usaha)
                // - bank/qris/transfer : 110-02 (Bank)
                // - split   : baris terpisah sesuai porsi tunai (110-04) & non-tunai (110-02)
                $debitLines = [];
                if ($kasbon) {
                    $debitLines[] = ['akun_kode' => '120-01', 'debit' => $this->totalAkhir, 'kredit' => 0];
                } elseif ($this->metodeBayar === 'tunai') {
                    $debitLines[] = ['akun_kode' => '110-04', 'debit' => $this->totalAkhir, 'kredit' => 0];
                } elseif ($this->metodeBayar === 'split') {
                    if ((float) $this->splitTunai > 0) {
                        $debitLines[] = ['akun_kode' => '110-04', 'debit' => (float) $this->splitTunai, 'kredit' => 0];
                    }
                    if ((float) $this->splitNonTunai > 0) {
                        $debitLines[] = ['akun_kode' => '110-02', 'debit' => (float) $this->splitNonTunai, 'kredit' => 0];
                    }
                } else {
                    // transfer / qris langsung ke Bank
                    $debitLines[] = ['akun_kode' => '110-02', 'debit' => $this->totalAkhir, 'kredit' => 0];
                }

                $lines = array_merge($debitLines, [
                    ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => $ppnNominal > 0 ? $dpp : (float) $this->totalAkhir],
                ]);
                // PPN Keluaran → akun 220-01 (kontrak AC F1-2)
                foreach (app(PajakService::class)->jurnalLines($ppnNominal, $noJurnal, $cabangId, auth()->id() ?? 0) as $ppnLine) {
                    $lines[] = $ppnLine;
                }
                if ($totalHpp > 0) {
                    $lines[] = ['akun_kode' => '510-02', 'debit' => $totalHpp, 'kredit' => 0];
                    $lines[] = ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => $totalHpp];
                }
                $jurnalService->post(
                    $noJurnal,
                    now(),
                    'pos',
                    $lines,
                    "Jurnal POS {$noTransaksi}",
                    $cabangId,
                    auth()->id(),
                    Transaksi::class,
                    $transaksi->id
                );

                // Komisi multi-aktor (PRD §4.3): reseller/agen/karyawan marketing via rule aktif
                app(KomisiService::class)->hitungKomisiMultiAktor('penjualan', ['transaksi_id' => $transaksi->id]);

                // Kasbon → catat Piutang (AR)
                if ($kasbon && $this->selectedCustomerId) {
                    $countPiutang = Piutang::where('cabang_id', $cabangId)
                        ->whereDate('created_at', now()->toDateString())
                        ->count() + 1;
                    Piutang::create([
                        'no_piutang' => sprintf('AR-%s-%04d', now()->format('Ymd'), $countPiutang),
                        'pelanggan_id' => $this->selectedCustomerId,
                        'transaksi_id' => $transaksi->id,
                        'cabang_id' => $cabangId,
                        'jumlah' => $this->totalAkhir,
                        'jumlah_dibayar' => 0,
                        'jatuh_tempo' => now()->addDays(30)->toDateString(),
                        'status' => 'belum_lunas',
                        'keterangan' => 'Kasbon POS '.$noTransaksi,
                    ]);
                }

                $this->receiptData = [
                    'no_transaksi' => $transaksi->no_transaksi,
                    'waktu' => now()->format('d/m/Y H:i'),
                    'kasir' => auth()->user()?->name ?? 'Kasir',
                    'pelanggan' => $this->customer?->nama ?? 'Umum',
                    'tier' => $this->customer?->tierMembership?->nama ?? 'Retail',
                    'items' => array_values($this->cart),
                    'subtotal' => $this->subtotal,
                    'diskon' => $this->diskonNominal,
                    'dpp' => $this->dpp,
                    'pajak' => $this->pajakNominal,
                    'ppn_persen' => $this->ppnPersen,
                    'total' => $this->totalAkhir,
                    'bayar' => $transaksi->jumlah_bayar,
                    'kembali' => $this->kembalian,
                    'metode' => strtoupper($this->metodeBayar),
                ];

                $this->completedTransactionId = $transaksi->id;
            });

            $this->showPaymentModal = false;
            $this->showReceiptModal = true;
            $this->clearCart();
            $this->checkKasSesi();
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Gagal memproses transaksi: '.$e->getMessage()]);
        }
    }

    public function bukaRiwayatTransaksi(): void
    {
        $this->showRiwayatTransaksiModal = true;
    }

    public function tutupRiwayatTransaksi(): void
    {
        $this->showRiwayatTransaksiModal = false;
    }

    public function lihatDetailTransaksi(int $id): void
    {
        $cabangId = session('cabang_id');
        $transaksi = Transaksi::with(['items.produk', 'pelanggan.tierMembership', 'kasir'])
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->findOrFail($id);

        $items = $transaksi->items->map(function ($item) {
            return [
                'nama' => $item->produk?->nama ?? 'Produk',
                'sku' => $item->produk?->sku ?? '-',
                'jumlah' => (int) $item->jumlah,
                'harga' => (float) $item->harga_satuan,
                'subtotal' => (float) $item->subtotal,
            ];
        })->toArray();

        $this->receiptData = [
            'no_transaksi' => $transaksi->no_transaksi,
            'waktu' => $transaksi->created_at?->format('d/m/Y H:i'),
            'kasir' => $transaksi->kasir?->name ?? 'Kasir',
            'pelanggan' => $transaksi->pelanggan?->nama ?? 'Umum',
            'tier' => $transaksi->pelanggan?->tierMembership?->nama ?? 'Retail',
            'items' => $items,
            'subtotal' => (float) $transaksi->subtotal,
            'diskon' => (float) $transaksi->diskon_nominal,
            'dpp' => (float) $transaksi->subtotal - (float) $transaksi->diskon_nominal,
            'pajak' => (float) $transaksi->pajak_nominal,
            'ppn_persen' => (float) $transaksi->pajak_persen,
            'total' => (float) $transaksi->total_akhir,
            'bayar' => (float) $transaksi->jumlah_bayar,
            'kembali' => (float) $transaksi->kembalian,
            'metode' => strtoupper($transaksi->metode_bayar ?? 'TUNAI'),
        ];

        $this->completedTransactionId = $transaksi->id;
        $this->showReceiptModal = true;
    }

    public function bukaModalRetur(int $transaksiId): void
    {
        $this->selectedTransaksiReturId = $transaksiId;
        $this->returAlasan = '';
        $this->returMetodePengembalian = 'kas';
        $this->returItemInputs = [];

        $transaksi = Transaksi::with(['items.produk'])->find($transaksiId);
        if (! $transaksi) {
            return;
        }

        foreach ($transaksi->items as $item) {
            $this->returItemInputs[$item->id] = [
                'transaksi_item_id' => $item->id,
                'produk_nama' => $item->produk?->nama ?? 'Produk',
                'qty_beli' => (float) $item->jumlah,
                'harga_final' => (float) ($item->harga_final ?? $item->harga_satuan ?? 0),
                'jumlah' => 0,
                'sn_raw' => '',
            ];
        }

        $this->showReturPenjualanModal = true;
    }

    public function tutupModalRetur(): void
    {
        $this->showReturPenjualanModal = false;
        $this->selectedTransaksiReturId = null;
        $this->returItemInputs = [];
    }

    public function simpanReturPenjualan(): void
    {
        $this->validate([
            'selectedTransaksiReturId' => 'required|exists:transaksi,id',
            'returAlasan' => 'required|string|max:500',
            'returMetodePengembalian' => 'required|in:kas,piutang,saldo',
        ]);

        $items = [];
        foreach ($this->returItemInputs as $item) {
            $qty = (float) ($item['jumlah'] ?? 0);
            if ($qty > 0) {
                $snList = [];
                if (! empty($item['sn_raw'])) {
                    $snList = preg_split('/[\r\n,;]+/', (string) $item['sn_raw']) ?: [];
                    $snList = array_values(array_filter(array_map('trim', $snList)));
                }

                $items[] = [
                    'transaksi_item_id' => (int) $item['transaksi_item_id'],
                    'jumlah' => $qty,
                    'sn' => $snList,
                ];
            }
        }

        if (empty($items)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Pilih minimal 1 barang dengan jumlah > 0 untuk diretur.']);

            return;
        }

        $transaksi = Transaksi::findOrFail($this->selectedTransaksiReturId);

        try {
            $retur = app(ReturnPenjualanService::class)->buatRetur(
                $transaksi,
                $items,
                $this->returAlasan,
                $this->returMetodePengembalian,
                auth()->id()
            );

            $this->dispatch('alert', [
                'type' => 'success',
                'message' => $retur->status === 'selesai'
                    ? "Retur {$retur->no_return} berhasil diproses — stok bertambah & kas/piutang disesuaikan."
                    : "Retur {$retur->no_return} dibuat — menunggu approval supervisor.",
            ]);

            $this->tutupModalRetur();
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function getRiwayatTransaksiHariIniProperty()
    {
        $cabangId = session('cabang_id');

        return Transaksi::with(['pelanggan', 'kasir'])
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->whereDate('created_at', now()->toDateString())
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    // ==================== [T-03] PARK / TAHAN ====================

    /** Simpan keranjang sebagai transaksi status 'ditahan' (F6) — belum kurangi stok. */
    public function tahanTransaksi()
    {
        if (empty($this->cart)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Keranjang kosong, tidak ada yang ditahan']);

            return;
        }

        $cabangId = session('cabang_id') ?? auth()->user()?->cabangs()->first()?->id ?? 1;

        // [B-02/P1-2] Simpan keranjang tertahan dgn gudang cabang aktif
        $this->validasiGudangCabang();

        try {
            DB::transaction(function () use ($cabangId) {
                $today = now()->format('Ymd');
                $count = Transaksi::whereDate('created_at', now()->toDateString())
                    ->where('cabang_id', $cabangId)->count() + 1;
                $no = sprintf('TRX-C%02d-%s-T%04d', $cabangId, $today, $count);

                $transaksi = Transaksi::create([
                    'no_transaksi' => $no,
                    'cabang_id' => $cabangId,
                    'kasir_id' => auth()->id() ?? 1,
                    'pelanggan_id' => $this->selectedCustomerId,
                    'gudang_id' => $this->selectedGudangId,
                    'sumber' => 'pos',
                    'subtotal' => $this->subtotal,
                    'diskon_persen' => $this->diskonPersen,
                    'diskon_nominal' => $this->diskonNominal,
                    'dpp' => $this->dpp,
                    'pajak_nominal' => $this->pajakNominal,
                    'ppn_nominal' => $this->pajakNominal,
                    'total_akhir' => $this->totalAkhir,
                    'metode_bayar' => 'ditahan',
                    'jumlah_bayar' => 0,
                    'kembalian' => 0,
                    'split_detail' => [
                        'cart' => array_values($this->cart),
                        'diskon_persen' => $this->diskonPersen,
                        'diskon_nominal' => $this->diskonNominal,
                        'catatan' => 'Ditahan (park) oleh '.(auth()->user()?->name ?? 'kasir'),
                    ],
                    'status' => 'ditahan',
                    'catatan' => 'Transaksi ditahan (park)',
                ]);
            });

            $this->clearCart();
            $this->showDitahanPanel = true; // [T-37] panel tertahan langsung terbuka setelah F6
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Transaksi ditahan — dapat dilanjutkan kasir lain di cabang ini']);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /** Daftar transaksi ditahan (scope cabang aktif — POS-05). */
    public function getDitahanListProperty()
    {
        return Transaksi::with('kasir')
            ->where('cabang_id', session('cabang_id'))
            ->where('status', 'ditahan')
            ->latest()
            ->get();
    }

    /** Lanjutkan transaksi yang ditahan → isi ulang keranjang, status duplikat jadi draft biasa. */
    public function resumeDitahan(int $id)
    {
        $transaksi = Transaksi::where('id', $id)
            ->where('cabang_id', session('cabang_id'))
            ->where('status', 'ditahan')
            ->firstOrFail();

        $detail = $transaksi->split_detail ?? [];
        $cartItems = $detail['cart'] ?? [];

        if (empty($cartItems)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Data keranjang ditahan tidak ditemukan']);

            return;
        }

        // Muat ulang keranjang ke state (tanpa duplikat — reset dulu)
        $this->clearCart();
        foreach ($cartItems as $item) {
            $key = isset($item['sku_variant_id']) && $item['sku_variant_id']
                ? $item['produk_id'].'-'.$item['sku_variant_id']
                : $item['produk_id'].'-0';
            // [F2-3][P2-3] Compat keranjang lama — flag sn SELALU di-derive ulang
            // dari produk (server-side). Array + mempertahankan key kiri (payload
            // lama), jadi pakai array_merge agar 'sn' selalu nilai segar.
            $this->cart[$key] = array_merge($item, [
                'sn' => $this->snProdukDariItem($item),
                'sn_list' => $item['sn_list'] ?? [],
            ]);
        }
        $this->diskonPersen = (float) ($detail['diskon_persen'] ?? 0);
        $this->diskonNominal = (float) ($detail['diskon_nominal'] ?? 0);
        $this->resetComputedTotals();
        $this->recalcPajak(); // [F1-2] resume park → rehitung PPN dari diskon tersimpan
        $this->selectedCustomerId = $transaksi->pelanggan_id;

        // Tandai transaksi asli dipindahkan → status 'batal' (tidak dipakai lagi), tanpa jurnal
        $transaksi->update(['status' => 'dibatalkan', 'catatan' => 'Dilanjutkan (resume) oleh '.(auth()->user()?->name ?? 'kasir')]);

        $this->dispatch('alert', ['type' => 'success', 'message' => 'Transaksi ditahan dilanjutkan — silakan lanjut bayar']);
    }

    // ==================== [T-08] PELANGGAN QUICK-ADD ====================

    /** [T-18] Dipanggil komponen <x-customer-picker> → buka modal create pelanggan. */
    public function openPelangganBaru()
    {
        $this->pelangganBaruForm = ['nama' => '', 'telepon' => '', 'email' => '', 'alamat' => '', 'tanggal_lahir' => ''];
        $this->showPelangganBaruModal = true;
    }

    public function getPelangganCariProperty()
    {
        if (strlen($this->pelangganSearch) < 2) {
            return collect();
        }

        return Pelanggan::with('tierMembership')
            ->where(function ($q) {
                $q->where('nama', 'like', "%{$this->pelangganSearch}%")
                    ->orWhere('telepon', 'like', "%{$this->pelangganSearch}%")
                    ->orWhere('email', 'like', "%{$this->pelangganSearch}%");
            })
            ->limit(8)
            ->get();
    }

    public function simpanPelangganBaru()
    {
        $this->validate([
            'pelangganBaruForm.nama' => 'required|string|max:255',
            'pelangganBaruForm.telepon' => 'required|string|max:20|unique:pelanggan,telepon',
            'pelangganBaruForm.email' => 'nullable|email|unique:pelanggan,email',
        ]);

        $pelanggan = app(PelangganService::class)->create([
            'nama' => $this->pelangganBaruForm['nama'],
            'telepon' => $this->pelangganBaruForm['telepon'],
            'email' => $this->pelangganBaruForm['email'] ?: null,
            'alamat' => $this->pelangganBaruForm['alamat'] ?: null,
            'tanggal_lahir' => $this->pelangganBaruForm['tanggal_lahir'] ?: null,
            'is_reseller' => false,
        ]);

        $this->setPelanggan($pelanggan->id);
        $this->showPelangganBaruModal = false;
        $this->pelangganBaruForm = ['nama' => '', 'telepon' => '', 'email' => '', 'alamat' => '', 'tanggal_lahir' => ''];

        $this->dispatch('alert', ['type' => 'success', 'message' => 'Pelanggan baru disimpan & dipilih (sinkron ke CRM)']);
    }

    // ==================== [T-09] KAS SESI ====================

    public function bukaKasModal()
    {
        $this->resetValidation(['kasSaldoAwal', 'kasSaldoFisik']);
        $this->kasModeBuka = true;
        $this->kasHasil = null;

        // [T-33] Carryover: saldo awal shift berikutnya = saldo_akhir_fisik sesi tutup terakhir
        // (prioritas sesi milik kasir ini, fallback sesi bersama legacy user_id NULL)
        $cabangId = session('cabang_id') ?? auth()->user()?->cabangs()->first()?->id ?? 1;

        $prev = DB::table('kas_sesi')
            ->where('cabang_id', $cabangId)
            ->where('status', 'tutup')
            ->whereNotNull('saldo_akhir_fisik')
            ->where('user_id', auth()->id())
            ->latest('id')
            ->first();

        if (! $prev) {
            $prev = DB::table('kas_sesi')
                ->where('cabang_id', $cabangId)
                ->where('status', 'tutup')
                ->whereNotNull('saldo_akhir_fisik')
                ->whereNull('user_id')
                ->latest('id')
                ->first();
        }

        $this->kasSumber = $prev ? 'carryover' : 'manual';
        $this->kasSaldoAwal = $prev ? (float) $prev->saldo_akhir_fisik : 0;
        $this->kasSaldoAwalRaw = $this->formatNominal($this->kasSaldoAwal);
        $this->formatKasSaldoAwal(); // Ensure display is formatted

        // [KAS-LACI] Muat daftar sumber dana aktif (kas & bank, kecuali Kas Laci)
        $this->akunSumberList = AkunCOA::withSaldo()
            ->where('is_active', true)
            ->whereIn('kelompok', ['kas', 'bank'])
            ->where('kode', '!=', '110-04')
            ->orderBy('kode')
            ->get()
            ->map(fn ($a) => [
                'kode' => $a->kode,
                'nama' => $a->nama,
                'saldo' => $a->saldo_normal === 'debit'
                    ? (float) (($a->saldo_debit ?? 0) - ($a->saldo_kredit ?? 0))
                    : (float) (($a->saldo_kredit ?? 0) - ($a->saldo_debit ?? 0)),
            ])
            ->toArray();

        $this->selectedAkunSumber = '110-01'; // default Kas Besar
        $this->showKasModal = true;
    }

    public function tutupKasModal()
    {
        $this->resetValidation(['kasSaldoAwalRaw', 'kasSaldoFisikRaw']);
        $this->kasModeBuka = false;
        // [KAS-LACI / BLIND COUNT] Kosongkan input saldo fisik — kasir wajib hitung manual,
        // tidak boleh melihat saldo sistem sebelum input.
        $this->kasSaldoFisik = 0;
        $this->kasSaldoFisikRaw = '';
        $this->kasHasil = null;
        $this->showKasModal = true;
    }

    /**
     * Format raw input (1.000.000) to display format, parse to numeric.
     * Called on blur to show formatted value in input.
     */
    public function formatKasSaldoAwal(): void
    {
        $this->kasSaldoAwal = $this->normalizeNominal($this->kasSaldoAwalRaw);
        $this->kasSaldoAwalRaw = $this->formatNominal($this->kasSaldoAwal);
    }

    public function formatKasSaldoFisik(): void
    {
        $this->kasSaldoFisik = $this->normalizeNominal($this->kasSaldoFisikRaw);
        $this->kasSaldoFisikRaw = $this->formatNominal($this->kasSaldoFisik);
    }

    private function formatNominal(float $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    /**
     * [T-45] Submit modal kas — validasi + feedback yang jelas.
     * Buka kas: toast sukses "Kas berhasil dibuka" lalu modal tertutup (kembali
     * ke tampilan POS). Tutup kas: tampil rekap selisih + toast.
     * Validasi gagal: toast error + modal tetap terbuka.
     */
    public function prosesKas()
    {
        // Validasi Livewire: nominal wajib angka, tidak negatif
        if ($this->kasModeBuka) {
            $this->validate([
                'kasSaldoAwalRaw' => ['required', 'string'],
            ], [], [
                'kasSaldoAwalRaw' => 'Saldo awal',
            ]);
            // Ensure parsed value is valid
            $this->formatKasSaldoAwal();
        } else {
            $this->validate([
                'kasSaldoFisikRaw' => ['required', 'string'],
            ], [], [
                'kasSaldoFisikRaw' => 'Saldo fisik akhir',
            ]);
            $this->formatKasSaldoFisik();
        }

        try {
            $svc = app(KasSesiState::class);

            if ($this->kasModeBuka) {
                $this->kasHasil = $svc->bukaKas(
                    $this->normalizeNominal($this->kasSaldoAwal),
                    session('cabang_id') ?? auth()->user()?->cabangs()->first()?->id ?? 1,
                    auth()->id(),
                    $this->kasSumber,
                    $this->selectedAkunSumber
                );
                $this->checkKasSesi();

                // Sukses: toast + kembali ke tampilan POS (modal tertutup)
                $this->kasHasil = null;
                $this->showKasModal = false;
                $this->dispatch('alert', ['type' => 'success', 'message' => 'Kas berhasil dibuka — silakan mulai transaksi']);
            } else {
                $this->kasHasil = $svc->tutupKas($this->normalizeNominal($this->kasSaldoFisik));
                $this->checkKasSesi();

                // [KAS-LACI] Bila ada selisih → status menunggu_approval
                if (($this->kasHasil['status'] ?? '') === 'menunggu_approval') {
                    $this->showKasModal = false;
                    $this->dispatch('alert', [
                        'type' => 'warning',
                        'message' => 'Terdapat selisih kas — permintaan persetujuan telah dikirim ke owner / manager toko.',
                    ]);
                } else {
                    $this->dispatch('alert', [
                        'type' => 'success',
                        'message' => 'Kas ditutup — saldo fisik didepositkan kembali.',
                    ]);
                }
            }
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * [T-45] Normalisasi nominal dari input ber-separator ribuan/desimal Indonesia.
     * x-format-number (toRaw) sudah mengirim angka bersih lewat model; fungsi ini
     * jadi pengaman ganda bila nilai berformat sempat terkirim ke server
     * (mis. paste cepat sebelum koreksi listener berjalan).
     * - "1.000.000"   → 1000000
     * - "1.000.000,50"→ 1000000.5
     * - "1000000.50"  → 1000000.5 (desimal titik tidak diutak-atik)
     */
    protected function normalizeNominal(mixed $nilai): float
    {
        return $this->parseNominal($nilai);
    }

    /**
     * [KAS-LACI-MUTASI] Buka modal mutasi kas laci non-POS.
     */
    public function bukaMutasiKasModal(string $jenis = 'keluar'): void
    {
        $this->mutasiJenis = in_array($jenis, ['masuk', 'keluar'], true) ? $jenis : 'keluar';
        $this->mutasiNominalRaw = '';
        $this->mutasiNominal = 0;
        $this->mutasiKeterangan = '';
        $this->resetValidation(['mutasiNominalRaw', 'mutasiAkunLawan', 'mutasiKeterangan']);

        $this->muatKategoriMutasi();
        $this->muatRiwayatMutasiSesi();
        $this->showMutasiKasModal = true;
    }

    public function updatedMutasiJenis(): void
    {
        $this->muatKategoriMutasi();
    }

    public function muatKategoriMutasi(): void
    {
        if ($this->mutasiJenis === 'keluar') {
            // Pengeluaran kas laci: Beban operasional, perlengkapan toko, setor kas besar
            $this->mutasiKategoriList = [
                ['kode' => '520-05', 'nama' => 'Beban Lain-lain (Operasional Kecil/Konsumsi)', 'kelompok' => 'Beban'],
                ['kode' => '520-03', 'nama' => 'Beban Listrik, Air & Kebersihan', 'kelompok' => 'Beban'],
                ['kode' => '140-01', 'nama' => 'Perlengkapan Toko (ATK/Lakban/Plastik)', 'kelompok' => 'Aset Lancar'],
                ['kode' => '520-04', 'nama' => 'Beban Transport / Marketing Toko', 'kelompok' => 'Beban'],
                ['kode' => '110-01', 'nama' => 'Setor ke Kas Besar (Transfer Kas)', 'kelompok' => 'Kas/Bank'],
            ];
            $this->mutasiAkunLawan = '520-05';
        } else {
            // Pemasukan kas laci: Tambahan modal laci dari kas besar, pendapatan lain-lain
            $this->mutasiKategoriList = [
                ['kode' => '110-01', 'nama' => 'Kas Besar (Tambah Modal Laci)', 'kelompok' => 'Kas/Bank'],
                ['kode' => '430-01', 'nama' => 'Pendapatan Lain-lain (Parkir/Kardus/Tip)', 'kelompok' => 'Pendapatan'],
            ];
            $this->mutasiAkunLawan = '110-01';
        }
    }

    public function muatRiwayatMutasiSesi(): void
    {
        $sesi = app(KasSesiState::class)->sesiKasAktif();
        if (! $sesi || ! Schema::hasTable('kas_mutasi_laci')) {
            $this->riwayatMutasiSesi = [];

            return;
        }

        $this->riwayatMutasiSesi = DB::table('kas_mutasi_laci')
            ->where('kas_sesi_id', $sesi->id)
            ->latest('id')
            ->get()
            ->map(function ($row) {
                $akun = AkunCOA::where('kode', $row->akun_lawan_kode)->first();

                return [
                    'id' => $row->id,
                    'jenis' => $row->jenis,
                    'nominal' => (float) $row->nominal,
                    'akun_lawan' => $akun ? "{$akun->kode} - {$akun->nama}" : $row->akun_lawan_kode,
                    'keterangan' => $row->keterangan,
                    'no_jurnal' => $row->no_jurnal,
                    'jam' => date('H:i', strtotime($row->created_at)),
                ];
            })
            ->toArray();
    }

    public function simpanMutasiKas(): void
    {
        $this->validate([
            'mutasiNominalRaw' => 'required',
            'mutasiAkunLawan' => 'required|string',
            'mutasiKeterangan' => 'required|string|min:3|max:255',
        ], [
            'mutasiNominalRaw.required' => 'Nominal wajib diisi',
            'mutasiAkunLawan.required' => 'Kategori transaksi wajib dipilih',
            'mutasiKeterangan.required' => 'Keterangan transaksi wajib diisi',
            'mutasiKeterangan.min' => 'Keterangan minimal 3 karakter',
        ]);

        $nominal = $this->parseNominal($this->mutasiNominalRaw);
        if ($nominal <= 0) {
            $this->addError('mutasiNominalRaw', 'Nominal harus lebih dari 0');

            return;
        }

        try {
            $svc = app(KasSesiState::class);
            $res = $svc->catatMutasiLaci(
                jenis: $this->mutasiJenis,
                nominal: $nominal,
                akunLawanKode: $this->mutasiAkunLawan,
                keterangan: $this->mutasiKeterangan,
                cabangId: session('cabang_id'),
                userId: auth()->id()
            );

            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Mutasi kas {$this->mutasiJenis} Rp ".number_format($nominal, 0, ',', '.')." berhasil dicatat (#{$res['no_jurnal']})",
            ]);

            $this->mutasiNominalRaw = '';
            $this->mutasiNominal = 0;
            $this->mutasiKeterangan = '';
            $this->muatRiwayatMutasiSesi();
            $this->checkKasSesi();
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // --- [POS-SERVIS] Integrasi Pembayaran Servis di Kasir POS ---

    public function getDaftarServisSiapBayarProperty()
    {
        $cabangId = session('cabang_id');

        return TiketServis::with(['pelanggan', 'items', 'spareparts.produk'])
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->whereIn('status', ['selesai', 'diambil'])
            ->where('status_pembayaran', '!=', 'lunas')
            ->when($this->searchServis, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('no_tiket', 'like', "%{$this->searchServis}%")
                        ->orWhere('jenis_hp', 'like', "%{$this->searchServis}%")
                        ->orWhere('seri_hp', 'like', "%{$this->searchServis}%")
                        ->orWhere('nama_pelanggan', 'like', "%{$this->searchServis}%")
                        ->orWhere('telepon_pelanggan', 'like', "%{$this->searchServis}%")
                        ->orWhereHas('pelanggan', fn ($p) => $p->where('nama', 'like', "%{$this->searchServis}%")->orWhere('telepon', 'like', "%{$this->searchServis}%"));
                });
            })
            ->latest()
            ->limit(20)
            ->get();
    }

    public function bukaBayarServisModal(?int $id = null): void
    {
        $this->showBayarServisModal = true;
        if ($id) {
            $this->pilihTiketServis($id);
        }
    }

    public function tutupBayarServisModal(): void
    {
        $this->showBayarServisModal = false;
        $this->selectedServisId = null;
        $this->selectedServisDetail = null;
        $this->searchServis = '';
    }

    public function pilihTiketServis(int $id): void
    {
        $cabangId = session('cabang_id');
        $tiket = TiketServis::with(['pelanggan', 'items', 'spareparts.produk'])
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->findOrFail($id);

        $this->selectedServisId = $tiket->id;
        $rincian = $tiket->getRincianBiayaLengkap();
        $jasa = $rincian['total_jasa'];
        $part = $rincian['total_part'];
        $total = $rincian['total'];
        $rincianItems = $rincian['items'];

        $this->selectedServisDetail = [
            'id' => $tiket->id,
            'no_tiket' => $tiket->no_tiket,
            'pelanggan_nama' => $tiket->pelanggan?->nama ?? $tiket->nama_pelanggan ?? 'Pelanggan Umum',
            'pelanggan_telepon' => $tiket->pelanggan?->telepon ?? $tiket->telepon_pelanggan ?? '-',
            'jenis_hp' => $tiket->jenis_hp,
            'seri_hp' => $tiket->seri_hp,
            'keluhan' => $tiket->keluhan,
            'status' => $tiket->status,
            'status_pembayaran' => $tiket->status_pembayaran,
            'jasa' => $jasa,
            'part' => $part,
            'total' => $total,
            'items' => $rincianItems,
        ];

        $this->servisJumlahBayar = $total;
        $this->servisSplitTunai = $total;
        $this->servisSplitNonTunai = 0;
        $this->hitungKembalianServis();
    }

    public function hitungKembalianServis(): void
    {
        if (! $this->selectedServisDetail) {
            $this->servisKembalian = 0;

            return;
        }

        $total = (float) $this->selectedServisDetail['total'];

        if ($this->servisMetodeBayar === 'tunai') {
            $bayar = (float) $this->parseNominal($this->servisJumlahBayar);
            $this->servisKembalian = max(0, $bayar - $total);
        } elseif ($this->servisMetodeBayar === 'split') {
            $tunai = (float) $this->parseNominal($this->servisSplitTunai);
            $nonTunai = (float) $this->parseNominal($this->servisSplitNonTunai);
            $this->servisKembalian = max(0, ($tunai + $nonTunai) - $total);
        } else {
            $this->servisKembalian = 0;
        }
    }

    public function updatedServisJumlahBayar(): void
    {
        $this->hitungKembalianServis();
    }

    public function updatedServisMetodeBayar(): void
    {
        $this->hitungKembalianServis();
    }

    public function updatedServisSplitTunai(): void
    {
        $this->hitungKembalianServis();
    }

    public function updatedServisSplitNonTunai(): void
    {
        $this->hitungKembalianServis();
    }

    public function prosesBayarServis(): void
    {
        if (! $this->selectedServisDetail || ! $this->selectedServisId) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Pilih tiket servis terlebih dahulu']);

            return;
        }

        $cabangId = session('cabang_id') ?? auth()->user()?->cabangs()->first()?->id ?? 1;
        $total = (float) $this->selectedServisDetail['total'];

        // Validasi sesi kas untuk tunai / split tunai
        $adaPorsiTunai = $this->servisMetodeBayar === 'tunai' || ($this->servisMetodeBayar === 'split' && (float) $this->parseNominal($this->servisSplitTunai) > 0);
        if ($adaPorsiTunai && ! app(KasSesiState::class)->isActiveSesi()) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Kas belum dibuka — buka sesi kas terlebih dahulu sebelum menerima pembayaran tunai']);
            $this->bukaKasModal();

            return;
        }

        // Validasi kecukupan bayar
        $jumlahBayar = (float) $this->parseNominal($this->servisJumlahBayar);
        $splitTunai = (float) $this->parseNominal($this->servisSplitTunai);
        $splitNonTunai = (float) $this->parseNominal($this->servisSplitNonTunai);

        if ($this->servisMetodeBayar === 'tunai' && $jumlahBayar < $total) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Jumlah bayar kurang dari total tagihan servis']);

            return;
        }

        if ($this->servisMetodeBayar === 'split' && ($splitTunai + $splitNonTunai) < $total) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Total pembayaran split kurang dari total tagihan servis']);

            return;
        }

        $splitData = $this->servisMetodeBayar === 'split' ? [
            'tunai' => $splitTunai,
            'non_tunai' => $splitNonTunai,
            'metode_non_tunai' => $this->servisSplitMetodeNonTunai,
        ] : null;

        try {
            DB::transaction(function () use ($cabangId, $total, $jumlahBayar, $splitTunai, $splitNonTunai, $splitData) {
                $tiket = TiketServis::whereKey($this->selectedServisId)->lockForUpdate()->firstOrFail();

                $today = now()->format('Ymd');
                $countToday = Transaksi::whereDate('created_at', now()->toDateString())
                    ->where('cabang_id', $cabangId)
                    ->count() + 1;
                $noTransaksi = sprintf('TRX-C%02d-%s-%04d', $cabangId, $today, $countToday);

                $transaksi = Transaksi::create([
                    'no_transaksi' => $noTransaksi,
                    'cabang_id' => $cabangId,
                    'kasir_id' => auth()->id() ?? 1,
                    'pelanggan_id' => $tiket->pelanggan_id,
                    'sumber' => 'servis',
                    'tiket_servis_id' => $tiket->id,
                    'subtotal' => $total,
                    'diskon_persen' => 0,
                    'diskon_nominal' => 0,
                    'dpp' => $total,
                    'pajak_nominal' => 0,
                    'ppn_nominal' => 0,
                    'total_akhir' => $total,
                    'metode_bayar' => $this->servisMetodeBayar,
                    'jumlah_bayar' => $this->servisMetodeBayar === 'split' ? ($splitTunai + $splitNonTunai) : $jumlahBayar,
                    'kembalian' => $this->servisKembalian,
                    'split_detail' => $splitData,
                    'status' => 'selesai',
                    'catatan' => "Pelunasan Servis {$tiket->no_tiket} ({$tiket->jenis_hp})".($this->servisCatatan ? ' - '.$this->servisCatatan : ''),
                ]);

                // Eksekusi pelunasan tiket servis (jurnal kas/bank debit, piutang kredit)
                app(ServisService::class)->bayar(
                    $tiket,
                    $this->servisMetodeBayar,
                    auth()->user(),
                    $this->servisCatatan ?: "Kasir POS {$noTransaksi}",
                    $splitData,
                    $transaksi->id
                );

                // Update status ke 'diambil' jika unit diserahkan
                if ($this->servisUbahStatusDiambil && $tiket->status === 'selesai') {
                    app(ServisService::class)->updateStatus(
                        $tiket,
                        'diambil',
                        auth()->user(),
                        "Unit diserahkan kepada pelanggan saat pelunasan di kasir POS ({$noTransaksi})"
                    );
                }

                // Data struk thermal POS
                $this->receiptData = [
                    'no_transaksi' => $transaksi->no_transaksi,
                    'no_tiket' => $tiket->no_tiket,
                    'jenis_hp' => $tiket->jenis_hp,
                    'waktu' => now()->format('d/m/Y H:i'),
                    'kasir' => auth()->user()?->name ?? 'Kasir',
                    'pelanggan' => $this->selectedServisDetail['pelanggan_nama'],
                    'tier' => 'Servis HP ('.$tiket->jenis_hp.')',
                    'items' => $this->selectedServisDetail['items'],
                    'subtotal_jasa' => (float) ($this->selectedServisDetail['jasa'] ?? 0),
                    'subtotal_part' => (float) ($this->selectedServisDetail['part'] ?? 0),
                    'subtotal' => $total,
                    'diskon' => 0,
                    'dpp' => $total,
                    'pajak' => 0,
                    'ppn_persen' => 0,
                    'total' => $total,
                    'bayar' => $transaksi->jumlah_bayar,
                    'kembali' => $this->servisKembalian,
                    'metode' => strtoupper($this->servisMetodeBayar),
                ];

                $this->completedTransactionId = $transaksi->id;
            });

            $this->showBayarServisModal = false;
            $this->showReceiptModal = true;
            $this->checkKasSesi();
            $this->dispatch('alert', ['type' => 'success', 'message' => 'Pembayaran servis berhasil diproses di kasir POS.']);
        } catch (\Throwable $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Gagal memproses pembayaran servis: '.$e->getMessage()]);
        }
    }

    public function render()
    {
        $productsQuery = Produk::query()
            ->where('is_active', true)
            ->with([
                'skuVariants' => fn ($q) => $q->where('is_active', true),
                'tipeHps',
                'kategoriRelasi',
                'hargaTier',
            ]);

        if (! empty($this->search)) {
            $productsQuery->cariPintar($this->search);
        }

        // [B-02/P1-5] Load-more: ambil batas+1 utk tahu masih ada sisanya
        $products = $productsQuery->take($this->batasProduk + 1)->get();
        $adaLebihBanyak = $products->count() > $this->batasProduk;
        $products = $products->take($this->batasProduk)->values();

        $customers = Pelanggan::with('tierMembership')->take(10)->get();

        // [B-02/P1-2] Dropdown gudang HANYA milik cabang aktif
        $gudangs = Gudang::where('cabang_id', session('cabang_id'))
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->get();

        // [B-02/P0-2] Peta stok katalog — kriteria sama dgn addToCart (satu query)
        $stokPerProduk = $this->petaStokKatalog($products);

        // Explicitly pass all data needed by the view
        return view('modules.pos.livewire.pos-kasir', [
            'products' => $products,
            'gudangs' => $gudangs,
            'stokPerProduk' => $stokPerProduk,
            'adaLebihBanyak' => $adaLebihBanyak,
            'batasProduk' => $this->batasProduk,
            'customers' => $customers,
            'pelangganCari' => $this->pelangganCari,
            'ditahanList' => $this->ditahanList,
            'kasAktif' => $this->kasAktif,
            'total' => $this->total,
            'diskonTotal' => $this->diskonTotal,
            'dpp' => $this->dpp,
            'pajakNominal' => $this->pajakNominal,
            'ppnPersen' => $this->ppnPersen,
            'pajakNama' => app(PajakService::class)->getNama(session('cabang_id')),
            'totalBayar' => $this->totalBayar,
            'subtotal' => $this->subtotal,
            'totalAkhir' => $this->totalAkhir,
            'kembalian' => $this->kembalian,
            'customer' => $this->customer,
            'riwayatTransaksiHariIni' => $this->riwayatTransaksiHariIni,
            // [F2-3] computed props SN — pass eksplisit (WAIBS)
            'snCari' => $this->snCari,
            'snItemKey' => $this->snItemKey,
            'daftarServisSiapBayar' => $this->daftarServisSiapBayar,
        ])->layout('layouts.backoffice', ['header' => 'Kasir Point of Sale (POS)']);
    }
}
