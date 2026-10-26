<?php

namespace App\Modules\Pos\Livewire;

use App\Models\User;
use App\Models\UserPreference;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Akunting\Services\PajakService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Crm\Services\PelangganService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Pos\Services\HargaFleksibelService;
use App\Modules\Pos\Services\KasSesiState;
use App\Modules\Pos\Services\PembayaranPesananOnlineService;
use App\Modules\Pos\Services\PricingService;
use App\Modules\Pos\Services\TransaksiVoidService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Traits\PunyaRiwayatAktivitas;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Reseller\Services\KomisiService;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Services\ServisService;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\ProdukSatuanKonversi;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\TipeHp;
use App\Modules\Wms\Services\NomorSeriService;
use App\Modules\Wms\Services\StockReservationService;
use App\Modules\Wms\Services\StokDeductionService;
use App\Traits\ParsesNominal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

    // Filter values
    public ?int $filterKategoriId = null;

    public ?int $filterBrandId = null;

    public ?int $filterKualitasId = null;

    public ?int $filterTipeHpId = null;

    public ?string $filterBrandKompatibel = null;

    public ?string $filterModelKompatibel = null;

    public ?string $filterKondisi = null;

    public ?string $filterSatuan = null;

    public ?string $filterAbcClass = null;

    public bool $filterHanyaReadyStok = false;

    // Filter toggle preferences (checkbox custom)
    public array $visibleFilters = [
        'kategori' => false,
        'brand' => false,
        'kualitas' => false,
        'tipe_hp' => false,
        'brand_kompatibel' => false,
        'model_kompatibel' => false,
        'kondisi' => false,
        'satuan' => false,
        'abc_class' => false,
        'stok' => false,
    ];

    public bool $showCustomFilterSettings = false;

    // [B-02/P1-5] Batas katalog yang dimuat (load-more "Muat lebih banyak")
    public int $batasProduk = self::BATAS_PRODUK_AWAL;

    public ?int $selectedCustomerId = null;

    public ?int $selectedGudangId = null;

    // Cart state: [item_key => ['produk_id', 'sku_variant_id', 'nama', 'varian', 'harga', 'qty', 'diskon', 'subtotal', 'stok_max']]
    public array $cart = [];

    // Summary state
    public float $diskonPersen = 0.0;

    public float $diskonNominal = 0.0;

    public int $poinDitukar = 0;

    public float $diskonPoin = 0.0;

    public float $diskonPartai = 0.0;

    public string $alasanPartai = '';

    public string $modeRewardMitra = 'diskon_langsung'; // diskon_langsung | komisi_akumulasi

    // Diskon langsung modal state & Otorisasi Owner/Super Admin
    public bool $showDiskonModal = false;

    public string $tipeDiskonPilihan = 'persen'; // persen | nominal

    public float $tempDiskonPersen = 0.0;

    public string $tempDiskonNominal = '0';

    public ?int $diskonManualAuthorizedById = null;

    public string $diskonManualAuthorizedByName = '';

    public string $diskonManualAlasan = '';

    public string $diskonSupervisorAuth = '';

    public string $diskonSupervisorPassword = '';

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

    public string $riwayatSearch = '';

    // [VOID TRANSAKSI] State Modal Otorisasi Void
    public bool $showVoidModal = false;

    public ?int $voidTransaksiId = null;

    public ?string $voidNoTransaksi = null;

    public float $voidTotalAkhir = 0;

    public string $voidAlasan = '';

    public string $voidSupervisorAuth = '';

    public string $voidSupervisorPassword = '';

    // [T-03] Transaksi ditahan (park)
    public bool $showDitahanPanel = false;

    // [T-08] Pencarian & tambah pelanggan quick
    public string $pelangganSearch = '';

    public bool $showPelangganBaruModal = false;

    public array $pelangganBaruForm = ['nama' => '', 'telepon' => '', 'email' => '', 'alamat' => '', 'tanggal_lahir' => ''];

    // [T-09] Kas sesi modal
    public bool $showKasModal = false;

    // Pengaturan Garansi Sparepart Manual saat Transaksi & Serah Terima Online
    public string $garansiPilihan = '3_hari'; // '3_hari', '7_hari', '14_hari', '30_hari', 'custom', 'tanpa_garansi'

    public ?int $garansiCustomHari = 3;

    public string $catatanGaransi = '';

    public bool $konfirmasiGaransiOnline = false;

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

    // [POS-ONLINE-PICKUP] Pembayaran pesanan online (bayar di toko / pickup) dari layar kasir
    public bool $showPesananOnlineModal = false;

    public string $pencarianPesananOnline = '';

    public ?int $pesananOnlineTerpilih = null;

    public string $metodeBayarOnline = 'tunai'; // tunai | qris | transfer

    public mixed $nominalBayarOnline = 0;

    public function mount()
    {
        // Set default gudang dari active session cabang
        $cabangId = session('cabang_id');
        if ($cabangId) {
            $this->selectedGudangId = Gudang::where('cabang_id', $cabangId)->value('id');
        }

        $this->loadUserPreferences();

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

        // [POS-ONLINE-PICKUP] Direct link dari notifikasi / Pesanan Online index
        if (request()->has('pesanan_online_id') || request()->has('pickup_id')) {
            $orderId = (int) (request()->query('pesanan_online_id') ?: request()->query('pickup_id'));
            $this->bukaPesananOnlineModal();
            if ($orderId > 0) {
                $this->pilihPesananOnline($orderId);
            }
        } elseif (request()->boolean('pesanan_online')) {
            $this->bukaPesananOnlineModal();
        }

        // [POS-RETUR] Direct link untuk proses retur penjualan -> arahkan ke halaman dedicated /app/pos/retur
        if (request()->has('retur_id') || request()->has('transaksi_retur_id')) {
            $returTrxId = (int) (request()->query('retur_id') ?: request()->query('transaksi_retur_id'));
            if ($returTrxId > 0) {
                return redirect()->to("/app/pos/retur?transaksi_id={$returTrxId}");
            }
        } elseif (request()->boolean('retur')) {
            return redirect()->to('/app/pos/retur');
        }
    }

    public function loadUserPreferences(): void
    {
        $userId = auth()->id();
        if ($userId) {
            $pref = UserPreference::getPref($userId, 'pos_kasir_custom_filters');
            if (is_array($pref)) {
                $this->visibleFilters = array_merge($this->visibleFilters, $pref);
            }
        }
    }

    public function updatedVisibleFilters(): void
    {
        $this->simpanPreferensi();
    }

    public function toggleFilter(string $key): void
    {
        if (array_key_exists($key, $this->visibleFilters)) {
            $this->visibleFilters[$key] = ! $this->visibleFilters[$key];
            if (! $this->visibleFilters[$key]) {
                match ($key) {
                    'kategori' => $this->filterKategoriId = null,
                    'brand' => $this->filterBrandId = null,
                    'kualitas' => $this->filterKualitasId = null,
                    'tipe_hp' => $this->filterTipeHpId = null,
                    'brand_kompatibel' => $this->filterBrandKompatibel = null,
                    'model_kompatibel' => $this->filterModelKompatibel = null,
                    'kondisi' => $this->filterKondisi = null,
                    'satuan' => $this->filterSatuan = null,
                    'abc_class' => $this->filterAbcClass = null,
                    'stok' => $this->filterHanyaReadyStok = false,
                    default => null,
                };
                $this->batasProduk = self::BATAS_PRODUK_AWAL;
            }
            $this->simpanPreferensi();
        }
    }

    public function resetCustomFilters(): void
    {
        $this->visibleFilters = [
            'kategori' => false,
            'brand' => false,
            'kualitas' => false,
            'tipe_hp' => false,
            'brand_kompatibel' => false,
            'model_kompatibel' => false,
            'kondisi' => false,
            'satuan' => false,
            'abc_class' => false,
            'stok' => false,
        ];

        $this->filterKategoriId = null;
        $this->filterBrandId = null;
        $this->filterKualitasId = null;
        $this->filterTipeHpId = null;
        $this->filterBrandKompatibel = null;
        $this->filterModelKompatibel = null;
        $this->filterKondisi = null;
        $this->filterSatuan = null;
        $this->filterAbcClass = null;
        $this->filterHanyaReadyStok = false;
        $this->batasProduk = self::BATAS_PRODUK_AWAL;

        $userId = auth()->id();
        if ($userId) {
            UserPreference::setPref($userId, 'pos_kasir_custom_filters', $this->visibleFilters);
        }
    }

    public function resetFilterValues(): void
    {
        $this->filterKategoriId = null;
        $this->filterBrandId = null;
        $this->filterKualitasId = null;
        $this->filterTipeHpId = null;
        $this->filterBrandKompatibel = null;
        $this->filterModelKompatibel = null;
        $this->filterKondisi = null;
        $this->filterSatuan = null;
        $this->filterAbcClass = null;
        $this->filterHanyaReadyStok = false;
        $this->batasProduk = self::BATAS_PRODUK_AWAL;
    }

    protected function simpanPreferensi(): void
    {
        $userId = auth()->id();
        if ($userId) {
            UserPreference::setPref($userId, 'pos_kasir_custom_filters', $this->visibleFilters);
        }
    }

    public function updatedFilterKategoriId(): void
    {
        $this->batasProduk = self::BATAS_PRODUK_AWAL;
    }

    public function updatedFilterBrandId(): void
    {
        $this->batasProduk = self::BATAS_PRODUK_AWAL;
        if ($this->filterKualitasId) {
            $k = KualitasProduk::find($this->filterKualitasId);
            if ($k && $k->brand_id && $this->filterBrandId && (int) $k->brand_id !== (int) $this->filterBrandId) {
                $this->filterKualitasId = null;
            }
        }
    }

    public function updatedFilterKualitasId(): void
    {
        $this->batasProduk = self::BATAS_PRODUK_AWAL;
    }

    public function updatedFilterTipeHpId($val = null): void
    {
        $this->batasProduk = self::BATAS_PRODUK_AWAL;
        if (! empty($val)) {
            $th = TipeHp::find($val);
            if ($th) {
                $this->filterBrandKompatibel = $th->merk;
                $this->filterModelKompatibel = $th->model;
            }
        }
    }

    public function updatedFilterBrandKompatibel($val = null): void
    {
        $this->batasProduk = self::BATAS_PRODUK_AWAL;
        if (! empty($val)) {
            if ($this->filterTipeHpId) {
                $th = TipeHp::find($this->filterTipeHpId);
                if ($th && strcasecmp($th->merk, $val) !== 0) {
                    $this->filterTipeHpId = null;
                }
            }
            if ($this->filterModelKompatibel) {
                $exists = TipeHp::where('merk', $val)->where('model', $this->filterModelKompatibel)->exists()
                    || Produk::where('brand_kompatibel', $val)->where('model_kompatibel', $this->filterModelKompatibel)->exists();
                if (! $exists) {
                    $this->filterModelKompatibel = null;
                }
            }
        }
    }

    public function updatedFilterModelKompatibel($val = null): void
    {
        $this->batasProduk = self::BATAS_PRODUK_AWAL;
        if (! empty($val) && empty($this->filterBrandKompatibel)) {
            $th = TipeHp::where('model', $val)->first();
            if ($th) {
                $this->filterBrandKompatibel = $th->merk;
            } else {
                $prod = Produk::where('model_kompatibel', $val)->first();
                if ($prod && $prod->brand_kompatibel) {
                    $this->filterBrandKompatibel = $prod->brand_kompatibel;
                }
            }
        }
    }

    public function updatedFilterKondisi(): void
    {
        $this->batasProduk = self::BATAS_PRODUK_AWAL;
    }

    public function updatedFilterSatuan(): void
    {
        $this->batasProduk = self::BATAS_PRODUK_AWAL;
    }

    public function updatedFilterAbcClass(): void
    {
        $this->batasProduk = self::BATAS_PRODUK_AWAL;
    }

    public function updatedFilterHanyaReadyStok(): void
    {
        $this->batasProduk = self::BATAS_PRODUK_AWAL;
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

        $stokFisik = (int) StokItem::where('produk_id', $produkId)
            ->where('gudang_id', $gudangId)
            ->when($variantId, fn ($q) => $q->where('sku_variant_id', $variantId))
            ->sum('jumlah');

        $gudang = Gudang::find($gudangId);
        if (! $gudang) {
            return $stokFisik;
        }

        $reserved = app(StockReservationService::class)->getActiveReservation(
            $gudang->cabang_id,
            $produkId,
            $variantId
        );

        return max(0, $stokFisik - $reserved);
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

    public function getSubtotalRetailProperty(): float
    {
        return array_reduce(
            $this->cart,
            fn ($carry, $item) => $carry + (((float) ($item['harga_dasar'] ?? $item['harga'])) * (int) ($item['qty'] ?? 1)),
            0.0
        );
    }

    public function getDiskonTierTotalProperty(): float
    {
        if ($this->modeRewardMitra === 'komisi_akumulasi') {
            return 0.0;
        }

        return max(0.0, $this->subtotalRetail - $this->subtotal);
    }

    public function getHematTotalProperty(): float
    {
        return $this->diskonTierTotal + $this->diskonTotal;
    }

    public function getSubtotalProperty(): float
    {
        return array_reduce($this->cart, fn ($carry, $item) => $carry + ($item['subtotal'] ?? 0), 0.0);
    }

    public function getTotalAkhirProperty(): float
    {
        $dpp = max(0.0, $this->subtotal - $this->diskonTotal);

        return max(0.0, $dpp + $this->pajakNominal);
    }

    /**
     * Rekonsiliasi otomatis PPN per cabang (diaktifkan via konfigurasi pajak_enabled).
     * Dipanggil setelah cart / diskon berubah (add/update/remove/setHargaFleksibel/clear/updatedDiskon*).
     * [F1-2] DPP = subtotal - diskonTotal; pajakNominal = PPN dari DPP.
     */
    private function recalcPajak(): void
    {
        // Pastikan poin ditukar tidak melebihi sisa tagihan belanja
        if ($this->poinDitukar > 0) {
            $saldo = (int) ($this->customer?->poin_loyalty ?? 0);
            $konversi = app(PelangganService::class)->nilaiTukarPoinPelanggan($this->customer);
            $diskonSebelumPoin = $this->diskonNominal + $this->diskonPartai;
            if ($this->diskonPersen > 0) {
                $diskonSebelumPoin += round(($this->subtotal * $this->diskonPersen) / 100, 2);
            }
            $maksRupiah = max(0.0, $this->subtotal - $diskonSebelumPoin);
            $maksPoin = $konversi > 0 ? (int) floor($maksRupiah / $konversi) : 0;
            $this->poinDitukar = min($saldo, min($this->poinDitukar, $maksPoin));
            $this->diskonPoin = round($this->poinDitukar * $konversi, 2);
        }

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

    public function updatedPoinDitukar(mixed $val): void
    {
        $val = max(0, (int) $val);
        $saldo = (int) ($this->customer?->poin_loyalty ?? 0);
        $konversi = app(PelangganService::class)->nilaiTukarPoinPelanggan($this->customer);

        if ($konversi <= 0 || ! $this->customer) {
            $this->poinDitukar = 0;
            $this->diskonPoin = 0.0;
            $this->resetComputedTotals();
            $this->recalcPajak();

            return;
        }

        $diskonSebelumPoin = $this->diskonNominal + $this->diskonPartai;
        if ($this->diskonPersen > 0) {
            $diskonSebelumPoin += round(($this->subtotal * $this->diskonPersen) / 100, 2);
        }
        $maksRupiah = max(0.0, $this->subtotal - $diskonSebelumPoin);
        $maksPoin = (int) floor($maksRupiah / $konversi);

        $this->poinDitukar = min($saldo, min($val, $maksPoin));
        $this->diskonPoin = round($this->poinDitukar * $konversi, 2);

        $this->resetComputedTotals();
        $this->recalcPajak();
    }

    public function tukarSemuaPoin(): void
    {
        $saldo = (int) ($this->customer?->poin_loyalty ?? 0);
        $this->updatedPoinDitukar($saldo);
    }

    public function batalTukarPoin(): void
    {
        $this->poinDitukar = 0;
        $this->diskonPoin = 0.0;
        $this->resetComputedTotals();
        $this->recalcPajak();
    }

    public function toggleModeRewardMitra(): void
    {
        $this->modeRewardMitra = ($this->modeRewardMitra === 'diskon_langsung') ? 'komisi_akumulasi' : 'diskon_langsung';
        $this->recalculateCartPrices();
        $this->hitungDiskonPartai();
        $this->resetComputedTotals();
        $this->recalcPajak();
    }

    public function setModeRewardMitra(string $mode): void
    {
        $this->modeRewardMitra = in_array($mode, ['diskon_langsung', 'komisi_akumulasi'], true) ? $mode : 'diskon_langsung';
        $this->recalculateCartPrices();
        $this->hitungDiskonPartai();
        $this->resetComputedTotals();
        $this->recalcPajak();
    }

    public function getIsUserOwnerOrSuperAdminProperty(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasRole(['super-admin', 'owner']);
    }

    public function toggleModePoinMember(): void
    {
        if ($this->poinDitukar > 0) {
            $this->batalTukarPoin();
        } else {
            $this->tukarSemuaPoin();
        }
    }

    public function openDiskonModal(): void
    {
        $this->tempDiskonPersen = $this->diskonPersen;
        $this->tempDiskonNominal = $this->diskonNominal > 0 ? (string) (int) $this->diskonNominal : '0';
        $this->tipeDiskonPilihan = ($this->diskonNominal > 0 && $this->diskonPersen <= 0) ? 'nominal' : 'persen';
        $this->diskonSupervisorPassword = '';
        $this->showDiskonModal = true;
    }

    public function closeDiskonModal(): void
    {
        $this->showDiskonModal = false;
    }

    public function terapkanDiskonLangsung(): void
    {
        $targetDiskonPersen = 0.0;
        $targetDiskonNominal = 0.0;

        if ($this->tipeDiskonPilihan === 'persen') {
            $targetDiskonPersen = max(0.0, min(100.0, (float) $this->tempDiskonPersen));
        } else {
            $targetDiskonNominal = max(0.0, (float) $this->parseNominal($this->tempDiskonNominal));
        }

        $adaDiskonManual = ($targetDiskonPersen > 0 || $targetDiskonNominal > 0);

        // Diskon manual kasir bersifat opsional dan KHUSUS dengan otorisasi Owner/Superadmin
        if ($adaDiskonManual) {
            if ($this->isUserOwnerOrSuperAdmin) {
                // User login adalah Owner atau Super Admin
                $this->diskonManualAuthorizedById = auth()->id();
                $this->diskonManualAuthorizedByName = auth()->user()->name ?? 'Owner/Super Admin';
            } else {
                // Kasir biasa wajib otorisasi kredensial Owner / Super Admin
                try {
                    $supervisor = $this->verifikasiOtorisasiOwnerSuperAdmin(
                        $this->diskonSupervisorAuth,
                        $this->diskonSupervisorPassword
                    );
                    $this->diskonManualAuthorizedById = $supervisor->id;
                    $this->diskonManualAuthorizedByName = $supervisor->name;
                    $this->diskonSupervisorPassword = '';
                } catch (\Exception $e) {
                    $this->dispatch('alert', [
                        'type' => 'error',
                        'message' => 'Otorisasi Diskon Gagal: '.$e->getMessage(),
                    ]);

                    return;
                }
            }
        } else {
            $this->diskonManualAuthorizedById = null;
            $this->diskonManualAuthorizedByName = '';
            $this->diskonManualAlasan = '';
            $this->diskonSupervisorAuth = '';
            $this->diskonSupervisorPassword = '';
        }

        $this->diskonPersen = $targetDiskonPersen;
        $this->diskonNominal = $targetDiskonNominal;

        $this->resetComputedTotals();
        $this->recalcPajak();
        $this->showDiskonModal = false;

        $potongan = $this->diskonNominal + ($this->diskonPersen > 0 ? round(($this->subtotal * $this->diskonPersen) / 100, 2) : 0);
        $this->dispatch('alert', [
            'type' => 'success',
            'message' => $potongan > 0
                ? 'Diskon manual kasir berhasil diterapkan: -Rp '.number_format($potongan, 0, ',', '.').($this->diskonManualAuthorizedByName ? " (Otorisasi: {$this->diskonManualAuthorizedByName})" : '')
                : 'Diskon manual dihapus',
        ]);
    }

    public function resetDiskonLangsung(): void
    {
        $this->diskonPersen = 0.0;
        $this->diskonNominal = 0.0;
        $this->tempDiskonPersen = 0.0;
        $this->tempDiskonNominal = '0';
        $this->diskonManualAuthorizedById = null;
        $this->diskonManualAuthorizedByName = '';
        $this->diskonManualAlasan = '';
        $this->diskonSupervisorAuth = '';
        $this->diskonSupervisorPassword = '';
        $this->resetComputedTotals();
        $this->recalcPajak();
        $this->showDiskonModal = false;

        $this->dispatch('alert', [
            'type' => 'info',
            'message' => 'Diskon manual kasir di-reset',
        ]);
    }

    protected function verifikasiOtorisasiOwnerSuperAdmin(string $authInput, string $password): User
    {
        $authInput = trim($authInput);
        if (empty($authInput) || empty($password)) {
            throw new \Exception('Username/Email dan Password Owner/Super Admin wajib diisi.');
        }

        $user = User::where('email', $authInput)
            ->orWhere('name', $authInput)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new \Exception('Kredensial Owner/Super Admin salah atau user tidak ditemukan.');
        }

        if (! $user->hasRole(['super-admin', 'owner'])) {
            throw new \Exception("User '{$user->name}' bukan Owner atau Super Admin. Diskon manual kasir hanya boleh diotorisasi oleh Owner atau Super Admin.");
        }

        return $user;
    }

    public function setPresetDiskonPersen(float $p): void
    {
        $this->tipeDiskonPilihan = 'persen';
        $this->tempDiskonPersen = $p;
    }

    public function setPresetDiskonNominal(float $n): void
    {
        $this->tipeDiskonPilihan = 'nominal';
        $this->tempDiskonNominal = (string) (int) $n;
    }

    public function hitungDiskonPartai(): void
    {
        $this->diskonPartai = 0.0;
        $this->alasanPartai = '';

        if (! $this->customer) {
            return;
        }

        $config = app(KonfigurasiService::class);
        if (! $config->hargaPartaiAktif()) {
            return;
        }

        $isMember = $this->customer->tier_membership_id !== null
            || (bool) $this->customer->is_reseller
            || in_array($this->customer->tipe_konsumen, ['reseller', 'agen'], true);

        if (! $isMember) {
            return;
        }

        $totalQty = array_reduce($this->cart, fn ($carry, $item) => $carry + (int) ($item['qty'] ?? 1), 0);
        $subtotal = $this->subtotal;

        $minQty = $config->hargaPartaiMinQty();
        $diskonQtyPersen = $config->hargaPartaiQtyDiskonPersen();

        $minNominal = $config->hargaPartaiMinNominal();
        $diskonNominalPersen = $config->hargaPartaiNominalDiskonPersen();

        $persenTerpilih = 0.0;
        $alasan = '';

        if ($minQty > 0 && $diskonQtyPersen > 0 && $totalQty >= $minQty) {
            $persenTerpilih = $diskonQtyPersen;
            $alasan = "Harga Partai (Qty >= {$minQty} pcs)";
        }

        if ($minNominal > 0 && $diskonNominalPersen > 0 && $subtotal >= $minNominal) {
            if ($diskonNominalPersen > $persenTerpilih) {
                $persenTerpilih = $diskonNominalPersen;
                $alasan = 'Harga Partai (Belanja >= Rp '.number_format($minNominal, 0, ',', '.').')';
            } elseif ($persenTerpilih > 0) {
                $alasan .= ' + Belanja Besar';
            }
        }

        if ($persenTerpilih > 0) {
            $this->diskonPartai = round(($subtotal * $persenTerpilih) / 100, 2);
            $this->alasanPartai = $alasan." ({$persenTerpilih}%)";
        }
    }

    public function recalculateCartPrices(): void
    {
        $pricingService = app(PricingService::class);
        foreach ($this->cart as $key => $item) {
            $produk = Produk::with('satuanKonversi')->find($item['produk_id']);
            $variant = $item['sku_variant_id'] ? SkuVariant::find($item['sku_variant_id']) : null;
            if ($produk) {
                $pricing = $pricingService->resolve($produk, $this->customer, $variant, $this->modeRewardMitra);
                $baseHarga = (float) $pricing['harga'];
                $satuanDasar = $produk->satuan ?: 'pcs';
                $satuanTarget = $item['satuan'] ?? $satuanDasar;
                $konversi = max(0.0001, (float) ($item['konversi'] ?? 1));

                if ($satuanTarget !== $satuanDasar) {
                    $sk = $produk->satuanKonversi ? $produk->satuanKonversi->firstWhere('satuan_kode', $satuanTarget) : null;
                    if ($sk && $sk->harga_jual > 0) {
                        $hargaTierSatuan = round($baseHarga * $konversi, 2);
                        $harga = min((float) $sk->harga_jual, $hargaTierSatuan);
                    } else {
                        $harga = round($baseHarga * $konversi, 2);
                    }
                } else {
                    $harga = $baseHarga;
                }

                $this->cart[$key]['harga'] = $harga;
                $this->cart[$key]['harga_dasar'] = (float) ($pricing['harga_dasar'] * $konversi);
                $this->cart[$key]['diskon'] = (float) ($pricing['diskon_nominal'] ?? 0.0) * $konversi;
                $this->cart[$key]['potensi_komisi'] = (float) ($pricing['potensi_komisi'] ?? 0.0) * $konversi;
                $this->cart[$key]['tier_nama'] = $pricing['tier'] ?? null;
                $this->cart[$key]['subtotal'] = $this->cart[$key]['qty'] * $harga;
            }
        }
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
            $this->subtotalRetail,
            $this->diskonTierTotal,
            $this->hematTotal,
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
        $diskon += $this->diskonPartai;
        $diskon += $this->diskonPoin;

        return min($this->subtotal, $diskon);
    }

    public function getTotalBayarProperty(): float
    {
        return $this->totalAkhir;
    }

    public function getKembalianProperty(): float
    {
        if ($this->metodeBayar === 'tunai') {
            $bayar = (float) $this->parseNominal($this->jumlahBayar);

            return max(0.0, $bayar - (float) $this->totalAkhir);
        }
        if ($this->metodeBayar === 'split') {
            $tunai = (float) $this->parseNominal($this->splitTunai);
            $nonTunai = (float) $this->parseNominal($this->splitNonTunai);
            $totalBayar = $tunai + $nonTunai;

            return max(0.0, $totalBayar - (float) $this->totalAkhir);
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

        // 2. Cek exact match barcode kemasan multi-satuan
        $satuanKonversi = ProdukSatuanKonversi::where('barcode', $q)
            ->whereNotNull('barcode')
            ->where('barcode', '!=', '')
            ->with('produk')
            ->first();

        if ($satuanKonversi && $satuanKonversi->produk && $satuanKonversi->produk->is_active) {
            $this->addToCart($satuanKonversi->produk_id, null, $satuanKonversi->satuan_kode);
            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Item '{$satuanKonversi->produk->nama}' ({$satuanKonversi->satuan_kode}) ditambahkan ke keranjang.",
            ]);
            $this->search = '';

            return;
        }

        // 3. Cek exact match barcode produk, ID (aman non-overflow), atau nama produk
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

    public function addToCart(int $produkId, ?int $variantId = null, ?string $satuanKode = null)
    {
        $produk = Produk::with('satuanKonversi')->findOrFail($produkId);
        $variant = $variantId ? SkuVariant::find($variantId) : null;

        // [B-02/P1-2] Gudang terpilih wajib milik cabang aktif (anti tamper lintas cabang)
        $this->validasiGudangCabang();

        // Resolusi satuan & daftar satuan multi-satuan
        $satuanDasar = $produk->satuan ?: 'pcs';
        $satuanTarget = $satuanKode ?: $satuanDasar;

        // Resolusi harga dasar
        $pricingService = app(PricingService::class);
        $pricing = $pricingService->resolve($produk, $this->customer, $variant, $this->modeRewardMitra);
        $baseHarga = (float) $pricing['harga'];

        $daftarSatuan = [
            [
                'satuan_kode' => $satuanDasar,
                'faktor_konversi' => 1.0,
                'harga_jual' => $baseHarga,
                'is_dasar' => true,
                'label' => strtoupper($satuanDasar).' (1 '.$satuanDasar.')',
            ],
        ];

        $konversiDipakai = null;
        if ($produk->satuanKonversi && $produk->satuanKonversi->isNotEmpty()) {
            foreach ($produk->satuanKonversi as $sk) {
                $bisaJual = ($sk->is_satuan_jual === null || (bool) $sk->is_satuan_jual || $sk->satuan_kode === $satuanTarget);
                if ($bisaJual) {
                    $fKonv = max(0.0001, (float) $sk->faktor_konversi);
                    $hargaKonv = $sk->harga_jual > 0
                        ? (float) $sk->harga_jual
                        : round($baseHarga * $fKonv, 2);

                    $daftarSatuan[] = [
                        'satuan_kode' => $sk->satuan_kode,
                        'faktor_konversi' => $fKonv,
                        'harga_jual' => $hargaKonv,
                        'is_dasar' => false,
                        'label' => strtoupper($sk->satuan_kode).' ('.$fKonv.' '.$satuanDasar.')',
                    ];

                    if ($sk->satuan_kode === $satuanTarget) {
                        $konversiDipakai = $sk;
                    }
                }
            }
        }

        $faktorKonversi = 1.0;
        if ($satuanTarget !== $satuanDasar && $konversiDipakai) {
            $faktorKonversi = max(0.0001, (float) $konversiDipakai->faktor_konversi);
            if ($konversiDipakai->harga_jual > 0) {
                $hargaTierSatuan = round($baseHarga * $faktorKonversi, 2);
                $harga = min((float) $konversiDipakai->harga_jual, $hargaTierSatuan);
            } else {
                $harga = round($baseHarga * $faktorKonversi, 2);
            }
        } else {
            $harga = $baseHarga;
        }

        // Harga fleksibel: hanya superadmin boleh input manual
        if ($produk->harga_fleksibel) {
            if (! $this->canHargaFleksibel) {
                $this->dispatch('alert', [
                    'type' => 'error',
                    'message' => "{$produk->nama} harga fleksibel — perlu superadmin untuk menginput harga",
                ]);

                return;
            }

            $hargaMin = app(HargaFleksibelService::class)->hargaMinimum($produk);
            $harga = max($harga, $hargaMin * $faktorKonversi);
        }

        // Cek stok fisik di gudang aktif
        $stokTersedia = $this->hitungStokTersedia($produkId, $variantId, $this->selectedGudangId);
        $stokMaxSatuan = (int) floor($stokTersedia / $faktorKonversi);

        if ($stokMaxSatuan <= 0) {
            $this->blokirStokKosong($produk);

            return;
        }

        $satuanSuffix = ($satuanTarget !== $satuanDasar) ? "-{$satuanTarget}" : '';
        $itemKey = ($variantId ? "{$produkId}-{$variantId}" : "{$produkId}-0").$satuanSuffix;

        if (isset($this->cart[$itemKey])) {
            if ($this->cart[$itemKey]['qty'] + 1 > $stokMaxSatuan) {
                $pesan = $faktorKonversi > 1
                    ? "Stok {$produk->nama} maksimal {$stokMaxSatuan} {$satuanTarget} di gudang ini (tersedia {$stokTersedia} {$satuanDasar})"
                    : "Stok {$produk->nama} maksimal {$stokTersedia} unit di gudang ini";

                $this->dispatch('alert', [
                    'type' => 'warning',
                    'message' => $pesan,
                ]);

                return;
            }
            $this->cart[$itemKey]['qty'] += 1;
            $this->cart[$itemKey]['subtotal'] = $this->cart[$itemKey]['qty'] * $harga;
            $this->cart[$itemKey]['stok_max'] = $stokMaxSatuan;
        } else {
            $this->cart[$itemKey] = [
                'produk_id' => $produkId,
                'sku_variant_id' => $variantId,
                'nama' => $produk->nama,
                'varian' => $variant?->nama_varian ?? 'Standar',
                'satuan' => $satuanTarget,
                'konversi' => $faktorKonversi,
                'daftar_satuan' => $daftarSatuan,
                'harga' => $harga,
                'harga_dasar' => (float) ($pricing['harga_dasar'] * $faktorKonversi),
                'harga_beli' => (float) ($produk->harga_beli * $faktorKonversi),
                'qty' => 1,
                'diskon' => (float) ($pricing['diskon_nominal'] ?? 0.0) * $faktorKonversi,
                'potensi_komisi' => (float) ($pricing['potensi_komisi'] ?? 0.0) * $faktorKonversi,
                'tier_nama' => $pricing['tier'] ?? null,
                'subtotal' => $harga,
                'stok_max' => $stokMaxSatuan,
                'flex' => (bool) $produk->harga_fleksibel,
                'sn' => (bool) $produk->sn,
                'sn_list' => [],
            ];
        }

        // Reset computed properties cache & recalc pajak SETELAH item masuk keranjang
        $this->hitungDiskonPartai();
        $this->resetComputedTotals();
        $this->recalcPajak();
    }

    /** Ubah catatan item keranjang (Model Gadget / Info Tambahan). */
    public function setCatatanItem(string $itemKey, string $catatan): void
    {
        if (isset($this->cart[$itemKey])) {
            $this->cart[$itemKey]['catatan'] = trim($catatan);
        }
    }

    /** Ubah satuan item keranjang (Multi-Satuan POS). */
    public function ubahSatuanItem(string $itemKey, string $newSatuanKode): void
    {
        if (! isset($this->cart[$itemKey])) {
            return;
        }

        $item = $this->cart[$itemKey];
        $produk = Produk::with('satuanKonversi')->find($item['produk_id']);
        if (! $produk) {
            return;
        }

        $satuanDasar = $produk->satuan ?: 'pcs';
        $variant = $item['sku_variant_id'] ? SkuVariant::find($item['sku_variant_id']) : null;
        $pricing = app(PricingService::class)->resolve($produk, $this->customer, $variant, $this->modeRewardMitra);
        $baseHarga = (float) $pricing['harga'];

        $faktorKonversi = 1.0;
        $harga = $baseHarga;

        if ($newSatuanKode !== $satuanDasar) {
            $sk = $produk->satuanKonversi ? $produk->satuanKonversi->firstWhere('satuan_kode', $newSatuanKode) : null;
            if (! $sk) {
                return;
            }
            $faktorKonversi = max(0.0001, (float) $sk->faktor_konversi);
            if ($sk->harga_jual > 0) {
                $hargaTierSatuan = round($baseHarga * $faktorKonversi, 2);
                $harga = min((float) $sk->harga_jual, $hargaTierSatuan);
            } else {
                $harga = round($baseHarga * $faktorKonversi, 2);
            }
        }

        // Cek stok riil gudang aktif untuk satuan baru
        $stokTersedia = $this->hitungStokTersedia((int) $item['produk_id'], $item['sku_variant_id'] ?: null, $this->selectedGudangId);
        $maxQty = (int) floor($stokTersedia / $faktorKonversi);

        if ($maxQty <= 0) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => "Stok {$produk->nama} tidak mencukupi untuk satuan {$newSatuanKode} di gudang ini (tersedia {$stokTersedia} {$satuanDasar})",
            ]);

            return;
        }

        $currentQty = (int) $item['qty'];
        $newQty = max(1, min($currentQty, $maxQty));

        $satuanSuffix = ($newSatuanKode !== $satuanDasar) ? "-{$newSatuanKode}" : '';
        $newItemKey = ($item['sku_variant_id'] ? "{$item['produk_id']}-{$item['sku_variant_id']}" : "{$item['produk_id']}-0").$satuanSuffix;

        $item['satuan'] = $newSatuanKode;
        $item['konversi'] = $faktorKonversi;
        $item['harga'] = $harga;
        $item['harga_dasar'] = (float) ($pricing['harga_dasar'] * $faktorKonversi);
        $item['harga_beli'] = (float) ($produk->harga_beli * $faktorKonversi);
        $item['diskon'] = (float) ($pricing['diskon_nominal'] ?? 0.0) * $faktorKonversi;
        $item['potensi_komisi'] = (float) ($pricing['potensi_komisi'] ?? 0.0) * $faktorKonversi;
        $item['qty'] = $newQty;
        $item['stok_max'] = $maxQty;
        $item['subtotal'] = $newQty * $harga;

        if ($newItemKey !== $itemKey) {
            unset($this->cart[$itemKey]);
            if (isset($this->cart[$newItemKey])) {
                $mergedQty = min($this->cart[$newItemKey]['qty'] + $newQty, $maxQty);
                $item['qty'] = $mergedQty;
                $item['subtotal'] = $mergedQty * $harga;
            }
            $this->cart[$newItemKey] = $item;
        } else {
            $this->cart[$itemKey] = $item;
        }

        $this->hitungDiskonPartai();
        $this->resetComputedTotals();
        $this->recalcPajak();

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => "Satuan {$produk->nama} diubah ke {$newSatuanKode} (1 {$newSatuanKode} = {$faktorKonversi} {$satuanDasar})",
        ]);
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

        $konversi = max(0.0001, (float) ($item['konversi'] ?? 1));
        $stokMaxSatuan = (int) floor($stokMax / $konversi);

        // Stok habis (atau gudang belum dipilih) → tidak boleh ada item sama sekali
        if ($stokMaxSatuan <= 0) {
            $this->removeFromCart($itemKey);
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => $this->selectedGudangId
                    ? "Stok {$item['nama']} habis di gudang ini — item dihapus dari keranjang. Pilih gudang lain yang punya stok."
                    : 'Pilih gudang terlebih dahulu — item dihapus dari keranjang.',
            ]);

            return;
        }

        if ($newQty > $stokMaxSatuan) {
            $pesan = $konversi > 1
                ? "Stok {$item['nama']} maksimal {$stokMaxSatuan} {$item['satuan']} di gudang ini (tersedia {$stokMax} unit dasar)"
                : "Stok {$item['nama']} maksimal {$stokMaxSatuan} unit di gudang ini";

            $this->dispatch('alert', [
                'type' => 'warning',
                'message' => $pesan,
            ]);

            return;
        }

        // Sinkronkan batas keras di keranjang (dipakai cart-content & resume)
        $this->cart[$itemKey]['stok_max'] = $stokMaxSatuan;
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

        $this->hitungDiskonPartai();
        $this->resetComputedTotals();
        $this->recalcPajak();
    }

    public function removeFromCart(string $itemKey)
    {
        unset($this->cart[$itemKey]);
        $this->hitungDiskonPartai();
        $this->resetComputedTotals();
        $this->recalcPajak();
    }

    public function clearCart()
    {
        $this->cart = [];
        $this->diskonPersen = 0.0;
        $this->diskonNominal = 0.0;
        $this->diskonManualAuthorizedById = null;
        $this->diskonManualAuthorizedByName = '';
        $this->diskonManualAlasan = '';
        $this->diskonSupervisorAuth = '';
        $this->diskonSupervisorPassword = '';
        $this->poinDitukar = 0;
        $this->diskonPoin = 0.0;
        $this->diskonPartai = 0.0;
        $this->alasanPartai = '';
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
        $this->pelangganSearch = '';
        $this->poinDitukar = 0;
        $this->diskonPoin = 0.0;

        $customer = $this->customer;
        if ($customer) {
            $tipe = app(PelangganService::class)->tipeKonsumen($customer);
            if ($tipe === 'reseller' || $tipe === 'agen') {
                $this->modeRewardMitra = app(PelangganService::class)->modeRewardMitra($customer);
            } else {
                $this->modeRewardMitra = 'diskon_langsung';
            }
        } else {
            $this->modeRewardMitra = 'diskon_langsung';
        }

        $this->recalculateCartPrices();
        $this->hitungDiskonPartai();
        $this->resetComputedTotals();
        $this->recalcPajak();
    }

    public function setGaransiPilihan(string $pilihan, ?int $customHari = null): void
    {
        $this->garansiPilihan = $pilihan;
        if ($customHari !== null) {
            $this->garansiCustomHari = $customHari;
        }
    }

    public function hitungDurasiGaransiHari(): int
    {
        return match ($this->garansiPilihan) {
            '3_hari' => 3,
            '7_hari' => 7,
            '14_hari' => 14,
            '30_hari' => 30,
            'tanpa_garansi' => 0,
            'custom' => max(0, (int) ($this->garansiCustomHari ?? 0)),
            default => 3,
        };
    }

    public function generateGaransiTeks(int $durasiHari): string
    {
        if ($durasiHari <= 0) {
            return 'Tanpa Garansi';
        }

        $tanggalBerakhir = now()->addDays($durasiHari)->format('d/m/Y');

        return "{$durasiHari} Hari Toko (s/d {$tanggalBerakhir})";
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
        $this->jumlahBayar = $this->totalAkhir > 0 ? number_format($this->totalAkhir, 0, ',', '.') : '0';
        $this->splitTunai = $this->totalAkhir > 0 ? number_format($this->totalAkhir, 0, ',', '.') : '0';
        $this->splitNonTunai = '0';
        $this->showPaymentModal = true;
    }

    public function setQuickCash(float $nominal)
    {
        $this->jumlahBayar = number_format($nominal, 0, ',', '.');
    }

    public function updatedJumlahBayar($value): void
    {
        // Biarkan teks input apa adanya agar format pemisah ribuan tidak ter-reset saat mengetik
    }

    public function updatedSplitTunai($value): void {}

    public function updatedSplitNonTunai($value): void {}

    /**
     * [B-02/P0-1] Gate stok sebelum potong: seluruh item keranjang harus punya
     * stok >= qty di gudang aktif. Tanpa gate ini, kegagalan baru ketahuan
     * setelah transaksi dibuat lalu di-rollback (pesan teknis "Stok tidak
     * mencukupi (produk ID …)"). Stok selalu dihitung ulang dari database —
     * `stok_max` di keranjang adalah prop publik Livewire yang bisa di-tamper.
     */
    private function validasiStokKeranjang(): ?string
    {
        $dibutuhkanPerProduk = [];
        $namaProduk = [];

        foreach ($this->cart as $item) {
            $pId = (int) $item['produk_id'];
            $vId = $item['sku_variant_id'] ?: 0;
            $prodKey = "{$pId}-{$vId}";
            $konversi = max(0.0001, (float) ($item['konversi'] ?? 1));
            $qtyDasar = (int) round($item['qty'] * $konversi);

            $dibutuhkanPerProduk[$prodKey] = ($dibutuhkanPerProduk[$prodKey] ?? 0) + $qtyDasar;
            $namaProduk[$prodKey] = $item['nama'];
        }

        foreach ($dibutuhkanPerProduk as $prodKey => $totalQtyDasar) {
            [$pId, $vId] = explode('-', $prodKey);
            $stokTersedia = $this->hitungStokTersedia(
                (int) $pId,
                $vId ? (int) $vId : null,
                $this->selectedGudangId
            );

            if ($stokTersedia <= 0) {
                return $this->selectedGudangId
                    ? "Stok {$namaProduk[$prodKey]} habis di gudang ini — hapus dari keranjang atau pilih gudang lain."
                    : 'Pilih gudang terlebih dahulu sebelum membayar.';
            }

            if ($totalQtyDasar > $stokTersedia) {
                return "Stok {$namaProduk[$prodKey]} tidak cukup — tersedia {$stokTersedia} unit dasar, total keranjang {$totalQtyDasar} unit dasar.";
            }
        }

        return null;
    }

    public function processTransaction()
    {
        if (empty($this->cart)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Keranjang masih kosong']);

            return;
        }

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

        // Validasi kasbon / piutang wajib memilih pelanggan
        if ($this->metodeBayar === 'piutang' && empty($this->selectedCustomerId)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Pilih pelanggan terlebih dahulu untuk transaksi kasbon / piutang']);

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

                $pelangganSaatTransaksi = $this->selectedCustomerId ? Pelanggan::with('tierMembership')->find($this->selectedCustomerId) : null;
                $namaTierTransaksi = $pelangganSaatTransaksi?->tierMembership?->nama;
                $tipeKonsumenTransaksi = $pelangganSaatTransaksi ? app(PelangganService::class)->tipeKonsumen($pelangganSaatTransaksi) : 'retail';

                $splitData = $this->metodeBayar === 'split' ? [
                    'tunai' => $this->splitTunai,
                    'non_tunai' => $this->splitNonTunai,
                    'metode_non_tunai' => $this->splitMetodeNonTunai,
                ] : null;

                $durasiGaransi = $this->hitungDurasiGaransiHari();
                $garansiTeks = $this->generateGaransiTeks($durasiGaransi);
                $garansiBerakhir = $durasiGaransi > 0 ? now()->addDays($durasiGaransi)->toDateString() : null;

                $totalKomisiMitra = array_reduce(
                    $this->cart,
                    fn ($carry, $it) => $carry + ((float) ($it['potensi_komisi'] ?? 0) * (int) ($it['qty'] ?? 1)),
                    0.0
                );

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
                    'diskon_manual_authorized_by_id' => $this->diskonManualAuthorizedById,
                    'diskon_manual_alasan' => $this->diskonManualAlasan ?: null,
                    'diskon_poin' => $this->diskonPoin,
                    'poin_ditukar' => $this->poinDitukar,
                    'diskon_partai' => $this->diskonPartai,
                    'mode_reward_mitra' => $this->modeRewardMitra,
                    'komisi_mitra_nominal' => $totalKomisiMitra,
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
                    'durasi_garansi_hari' => $durasiGaransi,
                    'garansi_teks' => $garansiTeks,
                    'garansi_berakhir_pada' => $garansiBerakhir,
                    'catatan_garansi' => $this->catatanGaransi ?: null,
                ]);

                // Pre-load semua produk di cart sekali query (eliminasi N+1 query)
                $produkIds = array_unique(array_column($this->cart, 'produk_id'));
                $produkMap = Produk::whereIn('id', $produkIds)->get()->keyBy('id');

                // Create items & deduct stock
                foreach ($this->cart as $item) {
                    $produk = $produkMap->get($item['produk_id']);
                    $satuanDasar = $produk?->satuan ?: 'pcs';
                    $satuanItem = $item['satuan'] ?? $satuanDasar;
                    $konversi = max(0.0001, (float) ($item['konversi'] ?? 1));
                    $qtyDasar = (int) round($item['qty'] * $konversi);
                    $hppPerSatuan = $produk ? (float) $produk->harga_beli * $konversi : 0;

                    $transaksiItem = TransaksiItem::create([
                        'transaksi_id' => $transaksi->id,
                        'produk_id' => $item['produk_id'],
                        'sku_variant_id' => $item['sku_variant_id'],
                        'jumlah' => $item['qty'],
                        'satuan' => $satuanItem,
                        'konversi' => $konversi,
                        'harga_satuan' => $item['harga'],
                        'diskon_nominal' => $item['diskon'] ?? 0,
                        'subtotal' => $item['subtotal'],
                        'hpp' => $hppPerSatuan,
                        'catatan' => $item['catatan'] ?? null,
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
                            $qtyDasar,
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
                            qty: $qtyDasar,
                            jenis: 'penjualan',
                            referensiTipe: Transaksi::class,
                            referensiId: $transaksi->id,
                            userId: auth()->id(),
                            catatan: "POS Kasir {$transaksi->no_transaksi} ({$item['qty']} {$satuanItem})",
                        );
                    }
                }

                // Customer points & loyalty ledger (sinkron CRM & mutasi tercatat)
                if ($this->poinDitukar > 0 && $this->selectedCustomerId) {
                    app(PelangganService::class)->tukarPoin(
                        $this->selectedCustomerId,
                        $this->poinDitukar,
                        $transaksi->id,
                        "Tukar {$this->poinDitukar} poin pada transaksi {$transaksi->no_transaksi}"
                    );
                }

                if ($this->selectedCustomerId) {
                    app(PelangganService::class)->tambahBelanjaDanPoin(
                        $this->selectedCustomerId,
                        (float) $this->totalAkhir,
                        $transaksi->id
                    );
                }

                // Jurnal akuntansi otomatis (PRD §4.6)
                $jurnalService = app(JurnalService::class);
                $totalHpp = 0.0;
                foreach ($this->cart as $item) {
                    $produk = $produkMap->get($item['produk_id']) ?? Produk::find($item['produk_id']);
                    $konversi = max(0.0001, (float) ($item['konversi'] ?? 1));
                    $hppSatuanDasar = $produk ? (float) $produk->harga_beli : 0;
                    $totalHpp += $hppSatuanDasar * ((int) round($item['qty'] * $konversi));
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

                $pelangganModel = $this->selectedCustomerId ? Pelanggan::with('tierMembership')->find($this->selectedCustomerId) : null;
                $saldoPoin = $pelangganModel?->poin_loyalty ?? 0;
                $tipeKonsumen = $pelangganModel ? app(PelangganService::class)->tipeKonsumen($pelangganModel) : 'retail';

                // Komisi multi-aktor (PRD §4.3): reseller/agen/karyawan marketing via rule aktif
                app(KomisiService::class)->hitungKomisiMultiAktor('penjualan', ['transaksi_id' => $transaksi->id]);

                // Pencatatan komisi akumulasi jika mode komisi_akumulasi aktif & belum dicatat rule engine
                if ($transaksi->mode_reward_mitra === 'komisi_akumulasi' && (float) $transaksi->komisi_mitra_nominal > 0 && $this->selectedCustomerId) {
                    $sudahAda = Komisi::where('transaksi_id', $transaksi->id)->where('pelanggan_id', $this->selectedCustomerId)->exists();
                    if (! $sudahAda) {
                        $today = now()->format('Ymd');
                        $count = Komisi::whereDate('created_at', now()->toDateString())->count() + 1;
                        $noKomisi = sprintf('KMS-%s-%04d', $today, $count);
                        Komisi::create([
                            'no_komisi' => $noKomisi,
                            'pelanggan_id' => $this->selectedCustomerId,
                            'transaksi_id' => $transaksi->id,
                            'aktor_tipe' => $tipeKonsumen === 'agen' ? 'agen' : 'reseller',
                            'aktor_id' => $this->selectedCustomerId,
                            'nominal_komisi' => $transaksi->komisi_mitra_nominal,
                            'jumlah_transaksi' => $transaksi->total_akhir,
                            'status' => Komisi::STATUS_PENDING,
                            'keterangan' => 'Komisi akumulasi mitra transaksi '.$transaksi->no_transaksi,
                        ]);
                    }
                }

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

                $itemsReceipt = array_map(function ($it) {
                    $hargaDasar = (float) ($it['harga_dasar'] ?? $it['harga']);
                    $hargaSatuan = (float) ($it['harga'] ?? 0);
                    $qty = (int) ($it['qty'] ?? 1);
                    $diskonPerUnit = max(0, $hargaDasar - $hargaSatuan);
                    $diskonTotal = $diskonPerUnit * $qty;

                    return array_merge($it, [
                        'harga_retail' => $hargaDasar,
                        'diskon_per_unit' => $diskonPerUnit,
                        'diskon_total' => $diskonTotal,
                        'subtotal_retail' => $hargaDasar * $qty,
                    ]);
                }, array_values($this->cart));

                $totalRetail = array_sum(array_column($itemsReceipt, 'subtotal_retail'));
                $totalDiskonTier = max(0, $totalRetail - $this->subtotal);

                $sisaPoin = $saldoPoin;
                $diskonTierStruk = ($this->modeRewardMitra === 'komisi_akumulasi' ? 0.0 : $totalDiskonTier);
                $diskonKasirLangsung = $this->diskonNominal;
                if ($this->diskonPersen > 0) {
                    $diskonKasirLangsung += round(($this->subtotal * $this->diskonPersen) / 100, 2);
                }
                $hematTotal = $diskonTierStruk + $this->diskonPartai + $this->diskonPoin + $diskonKasirLangsung;

                $this->receiptData = [
                    'no_transaksi' => $transaksi->no_transaksi,
                    'waktu' => now()->format('d/m/Y H:i'),
                    'kasir' => auth()->user()?->name ?? 'Kasir',
                    'pelanggan' => $pelangganSaatTransaksi?->nama ?? 'Umum',
                    'telepon' => $pelangganSaatTransaksi?->telepon ?? null,
                    'tier' => $namaTierTransaksi ?? ($tipeKonsumenTransaksi === 'reseller' ? 'Reseller' : ($tipeKonsumenTransaksi === 'agen' ? 'Agen' : 'Retail')),
                    'tipe_konsumen' => $tipeKonsumenTransaksi,
                    'saldo_poin' => $sisaPoin,
                    'poin_ditukar' => $this->poinDitukar,
                    'diskon_poin' => $this->diskonPoin,
                    'diskon_partai' => $this->diskonPartai,
                    'alasan_partai' => $this->alasanPartai,
                    'mode_reward_mitra' => $this->modeRewardMitra,
                    'komisi_mitra_nominal' => $totalKomisiMitra,
                    'items' => $itemsReceipt,
                    'subtotal_retail' => $totalRetail,
                    'diskon_tier' => $diskonTierStruk,
                    'subtotal' => $this->subtotal,
                    'diskon' => $diskonKasirLangsung,
                    'diskon_nominal' => $this->diskonNominal,
                    'diskon_persen' => $this->diskonPersen,
                    'diskon_manual_authorized_by' => $this->diskonManualAuthorizedByName ?: ($this->diskonManualAuthorizedById ? User::find($this->diskonManualAuthorizedById)?->name : null),
                    'diskon_manual_alasan' => $this->diskonManualAlasan ?: null,
                    'dpp' => $this->dpp,
                    'pajak' => $this->pajakNominal,
                    'ppn_persen' => $this->ppnPersen,
                    'total' => $this->totalAkhir,
                    'bayar' => $transaksi->jumlah_bayar,
                    'kembali' => $this->kembalian,
                    'hemat_total' => $hematTotal,
                    'metode' => strtoupper($this->metodeBayar),
                    'garansi' => $garansiTeks,
                    'cabang' => session('cabang_nama') ?? auth()->user()?->cabang?->nama ?? null,
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

        $pelanggan = $transaksi->pelanggan;
        $saldoPoin = $pelanggan?->poin_loyalty ?? 0;
        $tipeKonsumen = $pelanggan ? app(PelangganService::class)->tipeKonsumen($pelanggan) : 'retail';

        $items = $transaksi->items->map(function ($item) {
            $retailPerUnit = (float) ($item->produk?->harga_jual_retail ?? $item->harga_satuan);
            if ($item->konversi > 0 && $item->satuan !== ($item->produk?->satuan ?: 'pcs')) {
                $retailPerUnit = round($retailPerUnit * (float) $item->konversi, 2);
            }
            $hargaSatuan = (float) $item->harga_satuan;
            $qty = (int) $item->jumlah;
            $diskonPerUnit = max(0, $retailPerUnit - $hargaSatuan);

            return [
                'nama' => $item->produk?->nama ?? 'Produk',
                'sku' => $item->produk?->sku ?? '-',
                'jumlah' => $qty,
                'qty' => $qty,
                'satuan' => $item->satuan,
                'konversi' => $item->konversi,
                'harga' => $hargaSatuan,
                'harga_retail' => $retailPerUnit,
                'diskon_per_unit' => $diskonPerUnit,
                'diskon_total' => $diskonPerUnit * $qty,
                'subtotal_retail' => $retailPerUnit * $qty,
                'subtotal' => (float) $item->subtotal,
                'catatan' => $item->catatan,
            ];
        })->toArray();

        $subtotalRetail = array_sum(array_column($items, 'subtotal_retail'));
        $modeReward = $transaksi->mode_reward_mitra ?? 'diskon_langsung';
        $diskonTier = $modeReward === 'komisi_akumulasi' ? 0.0 : max(0, $subtotalRetail - (float) $transaksi->subtotal);

        $diskonPartai = (float) ($transaksi->diskon_partai ?? 0);
        $diskonPoin = (float) ($transaksi->diskon_poin ?? 0);
        $poinDitukar = (int) ($transaksi->poin_ditukar ?? 0);
        $komisiMitra = (float) ($transaksi->komisi_mitra_nominal ?? 0);
        $diskonKasir = (float) $transaksi->diskon_nominal;
        if ((float) ($transaksi->diskon_persen ?? 0) > 0) {
            $diskonKasir += round(((float) $transaksi->subtotal * (float) $transaksi->diskon_persen) / 100, 2);
        }
        $hematTotal = $diskonTier + $diskonPartai + $diskonPoin + $diskonKasir;

        $this->receiptData = [
            'no_transaksi' => $transaksi->no_transaksi,
            'waktu' => $transaksi->created_at?->format('d/m/Y H:i'),
            'kasir' => $transaksi->kasir?->name ?? 'Kasir',
            'pelanggan' => $transaksi->pelanggan?->nama ?? 'Umum',
            'telepon' => $transaksi->pelanggan?->telepon ?? null,
            'tier' => $transaksi->pelanggan?->tierMembership?->nama ?? ($tipeKonsumen === 'reseller' ? 'Reseller' : ($tipeKonsumen === 'agen' ? 'Agen' : 'Retail')),
            'tipe_konsumen' => $tipeKonsumen,
            'saldo_poin' => $saldoPoin,
            'poin_ditukar' => $poinDitukar,
            'diskon_poin' => $diskonPoin,
            'diskon_partai' => $diskonPartai,
            'mode_reward_mitra' => $modeReward,
            'komisi_mitra_nominal' => $komisiMitra,
            'items' => $items,
            'subtotal_retail' => $subtotalRetail,
            'diskon_tier' => $diskonTier,
            'subtotal' => (float) $transaksi->subtotal,
            'diskon' => $diskonKasir,
            'diskon_nominal' => (float) $transaksi->diskon_nominal,
            'diskon_persen' => (float) ($transaksi->diskon_persen ?? 0),
            'dpp' => (float) $transaksi->subtotal - $diskonKasir,
            'pajak' => (float) $transaksi->pajak_nominal,
            'ppn_persen' => (float) $transaksi->pajak_persen,
            'total' => (float) $transaksi->total_akhir,
            'bayar' => (float) $transaksi->jumlah_bayar,
            'kembali' => (float) $transaksi->kembalian,
            'hemat_total' => $hematTotal,
            'metode' => strtoupper($transaksi->metode_bayar ?? 'TUNAI'),
            'status' => $transaksi->status,
            'void_alasan' => $transaksi->void_alasan,
            'garansi' => $transaksi->garansi_teks,
            'cabang' => $transaksi->cabang?->nama ?? session('cabang_nama') ?? null,
        ];

        $this->completedTransactionId = $transaksi->id;
        $this->showReceiptModal = true;
    }

    /** [VOID TRANSAKSI] Buka modal otorisasi void transaksi. */
    public function bukaVoidModal(int $id): void
    {
        $cabangId = session('cabang_id');
        $transaksi = Transaksi::when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->where('id', $id)
            ->first();

        if (! $transaksi) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Transaksi tidak ditemukan']);

            return;
        }

        if ($transaksi->status !== 'selesai') {
            $this->dispatch('alert', ['type' => 'error', 'message' => "Hanya transaksi berstatus 'selesai' yang dapat di-void (status saat ini: {$transaksi->status})"]);

            return;
        }

        $this->voidTransaksiId = $transaksi->id;
        $this->voidNoTransaksi = $transaksi->no_transaksi;
        $this->voidTotalAkhir = (float) $transaksi->total_akhir;
        $this->voidAlasan = '';
        $this->voidSupervisorAuth = '';
        $this->voidSupervisorPassword = '';
        $this->resetValidation(['voidAlasan', 'voidSupervisorAuth', 'voidSupervisorPassword']);

        $this->showVoidModal = true;
    }

    public function tutupVoidModal(): void
    {
        $this->showVoidModal = false;
        $this->voidTransaksiId = null;
        $this->voidNoTransaksi = null;
    }

    /** [VOID TRANSAKSI] Eksekusi void dengan otorisasi supervisor / admin. */
    public function prosesVoidTransaksi(): void
    {
        $this->validate([
            'voidAlasan' => 'required|string|min:4|max:255',
            'voidSupervisorAuth' => 'required|string',
            'voidSupervisorPassword' => 'required|string',
        ], [
            'voidAlasan.required' => 'Alasan pembatalan (void) wajib diisi',
            'voidAlasan.min' => 'Alasan minimal 4 karakter',
            'voidSupervisorAuth.required' => 'Username/Email Supervisor wajib diisi',
            'voidSupervisorPassword.required' => 'Password Supervisor wajib diisi',
        ]);

        if (! $this->voidTransaksiId) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Tidak ada transaksi yang dipilih untuk di-void']);

            return;
        }

        $cabangId = session('cabang_id');
        $transaksi = Transaksi::when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->where('id', $this->voidTransaksiId)
            ->first();

        if (! $transaksi) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Data transaksi tidak ditemukan']);

            return;
        }

        try {
            $voidService = app(TransaksiVoidService::class);

            // Verifikasi kredensial supervisor
            $supervisor = $voidService->verifikasiSupervisor($this->voidSupervisorAuth, $this->voidSupervisorPassword);

            // Jalankan alur void lengkap
            $voidService->voidTransaksi($transaksi, $supervisor, $this->voidAlasan);

            $noTrx = $transaksi->no_transaksi;
            $this->tutupVoidModal();

            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Transaksi {$noTrx} berhasil di-VOID oleh {$supervisor->name}. Stok dan pembukuan jurnal telah dibalikkan.",
            ]);
        } catch (\Exception $e) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function getRiwayatTransaksiHariIniProperty()
    {
        $cabangId = session('cabang_id') ?: auth()->user()?->cabangs()->first()?->id ?: Cabang::value('id');
        $search = trim($this->riwayatSearch);

        return Transaksi::with(['pelanggan', 'kasir'])
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->when(! empty($search), function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('no_transaksi', 'like', "%{$search}%")
                        ->orWhereHas('pelanggan', fn ($p) => $p->where('nama', 'like', "%{$search}%"));
                });
            }, function ($q) {
                $q->whereDate('created_at', now()->toDateString());
            })
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
                        'poin_ditukar' => $this->poinDitukar,
                        'mode_reward_mitra' => $this->modeRewardMitra,
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
            $pId = (int) ($item['produk_id'] ?? 0);
            $vId = $item['sku_variant_id'] ?? null;
            $satuanItem = $item['satuan'] ?? null;
            $produk = $pId ? Produk::find($pId) : null;
            $satuanDasar = $produk?->satuan ?: 'pcs';
            $satuanSuffix = ($satuanItem && $satuanItem !== $satuanDasar) ? "-{$satuanItem}" : '';

            $key = ($vId ? "{$pId}-{$vId}" : "{$pId}-0").$satuanSuffix;
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
        $this->poinDitukar = (int) ($detail['poin_ditukar'] ?? 0);
        $this->modeRewardMitra = (string) ($detail['mode_reward_mitra'] ?? 'diskon_langsung');
        $this->selectedCustomerId = $transaksi->pelanggan_id;
        $this->hitungDiskonPartai();
        $this->resetComputedTotals();
        $this->recalcPajak(); // [F1-2] resume park → rehitung PPN dari diskon tersimpan

        // Tandai transaksi asli dipindahkan → status 'batal' (tidak dipakai lagi), tanpa jurnal
        $transaksi->update(['status' => 'dibatalkan', 'catatan' => 'Dilanjutkan (resume) oleh '.(auth()->user()?->name ?? 'kasir')]);

        $this->showDitahanPanel = false;

        $this->dispatch('alert', ['type' => 'success', 'message' => 'Transaksi ditahan dilanjutkan — silakan lanjut bayar']);
    }

    /** Alias untuk resumeDitahan agar kompatibel dengan template lama/baru. */
    public function resumeTransaksi(int $id): void
    {
        $this->resumeDitahan($id);
    }

    /** Hapus / batalkan transaksi yang ditahan. */
    public function hapusDitahan(int $id): void
    {
        $transaksi = Transaksi::where('id', $id)
            ->where('cabang_id', session('cabang_id'))
            ->where('status', 'ditahan')
            ->first();

        if (! $transaksi) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Transaksi ditahan tidak ditemukan']);

            return;
        }

        $noTrx = $transaksi->no_transaksi;
        $transaksi->update([
            'status' => 'dibatalkan',
            'catatan' => 'Dibatalkan/dihapus dari kasir oleh '.(auth()->user()?->name ?? 'kasir'),
        ]);

        $this->dispatch('alert', ['type' => 'success', 'message' => "Transaksi ditahan {$noTrx} berhasil dihapus"]);
    }

    public function bukaDitahanPanel(): void
    {
        $this->showDitahanPanel = true;
    }

    public function tutupDitahanPanel(): void
    {
        $this->showDitahanPanel = false;
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
        $term = trim($this->pelangganSearch);
        if (strlen($term) < 1) {
            return collect();
        }

        return Pelanggan::with('tierMembership')
            ->where(function ($q) use ($term) {
                $q->where('nama', 'like', "%{$term}%")
                    ->orWhere('telepon', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
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
                    'telepon' => $tiket->telepon_pelanggan ?? $tiket->pelanggan?->telepon ?? null,
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

    // ==================== [POS-ONLINE-PICKUP] PESANAN ONLINE BAYAR DI TOKO ====================

    /**
     * Query dasar: pesanan marketplace/channel pada cabang aktif, status
     * `menunggu_pembayaran` (menunggu bayar di toko) ATAU `lunas` (sudah bayar
     * online, menunggu serah terima). Metode ambil di toko (pickup kasir) —
     * deteksi selaraskan PesananOnlineIndex: kolom catatan memuat 'ambil_ke_toko'.
     * Sumber: 'marketplace' (web konsumen) atau 'channel:*' (omnichannel).
     */
    private function queryPesananOnlinePickup()
    {
        return Transaksi::with(['pelanggan', 'items.produk'])
            ->where('cabang_id', session('cabang_id'))
            ->whereIn('status', ['menunggu_pembayaran', 'lunas'])
            ->where(function ($q) {
                $q->where('sumber', 'marketplace')
                    ->orWhere('sumber', 'like', 'channel:%');
            })
            ->where('catatan', 'like', '%ambil_ke_toko%');
    }

    /** Jumlah order pickup (menunggu bayar / menunggu serah) utk badge tombol entry (computed, segar tiap render). */
    public function getJumlahPesananOnlineProperty(): int
    {
        return (int) $this->queryPesananOnlinePickup()->count();
    }

    /** Hasil pencarian pesanan online pickup (no. order / pelanggan / telepon / resi / channel ID), limit 15. */
    public function getPesananOnlineHasilProperty()
    {
        $query = $this->queryPesananOnlinePickup();

        $term = trim($this->pencarianPesananOnline);
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($q) use ($like) {
                $q->where('no_transaksi', 'like', $like)
                    ->orWhereHas('pelanggan', fn ($sub) => $sub->where('nama', 'like', $like)->orWhere('telepon', 'like', $like))
                    ->orWhereHas('pengiriman', fn ($sub) => $sub->where('tracking_id', 'like', $like))
                    ->orWhereHas('channelOrder', fn ($sub) => $sub->where('channel_order_id', 'like', $like));
            });
        }

        return $query->latest()->limit(15)->get();
    }

    /** Detail ringkas order terpilih utk modal bayar/serah (guard cabang/status/pickup di server). */
    public function getPesananOnlineDetailProperty(): ?array
    {
        if (! $this->pesananOnlineTerpilih) {
            return null;
        }

        $trx = $this->queryPesananOnlinePickup()->whereKey($this->pesananOnlineTerpilih)->first();
        if (! $trx) {
            return null;
        }

        return [
            'id' => $trx->id,
            'no_transaksi' => $trx->no_transaksi,
            'pelanggan_nama' => $trx->pelanggan?->nama ?? 'Konsumen Umum',
            'pelanggan_telepon' => $trx->pelanggan?->telepon ?? '-',
            'waktu' => $trx->created_at?->format('d/m/Y H:i'),
            'total' => (float) $trx->total_akhir,
            'jumlah_item' => (int) $trx->items->sum('jumlah'),
            'items' => $trx->items->map(fn ($it) => [
                'nama' => $it->produk?->nama ?? 'Produk',
                'jumlah' => (int) $it->jumlah,
                'subtotal' => (float) $it->subtotal,
            ])->values()->all(),
            // [T-SERAH] Sudah lunas online → mode serah terima (bukan bayar)
            'sudah_lunas' => $trx->status === 'lunas',
            'status' => $trx->status,
            'metode_bayar' => strtoupper($trx->metode_bayar ?? '-'),
            'tanggal_bayar' => $trx->paid_at?->format('d/m/Y H:i'),
        ];
    }

    /** Kembalian otomatis utk pembayaran tunai pesanan online (computed). */
    public function getKembalianOnlineProperty(): float
    {
        $detail = $this->pesananOnlineDetail;
        if (! $detail || $this->metodeBayarOnline !== 'tunai') {
            return 0;
        }

        return max(0, (float) $this->parseNominal($this->nominalBayarOnline) - (float) $detail['total']);
    }

    public function bukaPesananOnlineModal(): void
    {
        $this->resetPesananOnlineState();
        $this->showPesananOnlineModal = true;
    }

    public function tutupPesananOnlineModal(): void
    {
        $this->showPesananOnlineModal = false;
        $this->resetPesananOnlineState();
    }

    private function resetPesananOnlineState(): void
    {
        $this->pencarianPesananOnline = '';
        $this->pesananOnlineTerpilih = null;
        $this->metodeBayarOnline = 'tunai';
        $this->nominalBayarOnline = 0;
        $this->konfirmasiGaransiOnline = false;
    }

    public function pilihPesananOnline(int $id): void
    {
        $this->konfirmasiGaransiOnline = false;
        // Guard server-side: order harus lolos filter cabang / status / pickup
        if (! $this->queryPesananOnlinePickup()->whereKey($id)->exists()) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => 'Pesanan tidak ditemukan atau bukan pesanan pickup (menunggu bayar / lunas menunggu serah) di cabang ini',
            ]);

            return;
        }

        $this->pesananOnlineTerpilih = $id;
        $this->metodeBayarOnline = 'tunai';
        // Default nominal diterima = total tagihan (pola "uang pas" di payment modal)
        $total = (float) $this->queryPesananOnlinePickup()->whereKey($id)->value('total_akhir');
        $this->nominalBayarOnline = $total > 0 ? number_format($total, 0, ',', '.') : '0';
    }

    /** Enter di input pencarian → pilih hasil pertama. */
    public function pilihPesananOnlinePertama(): void
    {
        $pertama = $this->pesananOnlineHasil->first();
        if ($pertama) {
            $this->pilihPesananOnline((int) $pertama->id);
        }
    }

    /** Kembali ke daftar hasil dari tampilan bayar (ganti pesanan). */
    public function kembaliKeDaftarPesananOnline(): void
    {
        $this->pesananOnlineTerpilih = null;
        $this->metodeBayarOnline = 'tunai';
        $this->nominalBayarOnline = 0;
    }

    public function setNominalBayarOnline(float $nominal): void
    {
        $this->nominalBayarOnline = number_format($nominal, 0, ',', '.');
    }

    public function updatedNominalBayarOnline($value): void
    {
        // Biarkan teks input apa adanya (pola updatedJumlahBayar — format ribuan tidak reset)
    }

    public function updatedMetodeBayarOnline(): void
    {
        // KembalianOnline dihitung ulang otomatis via computed property
    }

    /**
     * Eksekusi pembayaran pesanan online pickup via service beku:
     * PembayaranPesananOnlineService::bayarDiToko() — service melakukan semua
     * (validasi status/pickup, potong stok, jurnal, status→selesai, kasir_id,
     * sesi kas, notifikasi konsumen). UI hanya lookup + metode + hasil.
     * Setelah sukses: struk bukti serah terima pickup dicetak via modal struk
     * generik kasir (showReceiptModal + receiptData).
     * RuntimeException (Bahasa Indonesia) → alert error, modal tetap terbuka.
     */
    public function bayarOnlineOrder(): void
    {
        $detail = $this->pesananOnlineDetail;
        if (! $detail || ! $this->pesananOnlineTerpilih) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Pilih pesanan online terlebih dahulu']);

            return;
        }

        if (! $this->konfirmasiGaransiOnline) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => 'Kasir wajib mengonfirmasi telah menginfokan T&C & masa garansi sparepart ke pelanggan saat penyerahan barang.',
            ]);

            return;
        }

        // Anti-tamper: order lunas tidak boleh dibayar ulang di kasir
        if (! empty($detail['sudah_lunas'])) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => "Pesanan {$detail['no_transaksi']} sudah lunas — gunakan tombol 'Serahkan Barang'.",
            ]);

            return;
        }

        $transaksi = Transaksi::find($this->pesananOnlineTerpilih);
        if (! $transaksi) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Pesanan tidak ditemukan']);

            return;
        }

        $nominalDiterima = $this->metodeBayarOnline === 'tunai'
            ? (float) $this->parseNominal($this->nominalBayarOnline)
            : (float) $detail['total'];

        // Validasi cepat di UI (service tetap penentu akhir)
        if ($this->metodeBayarOnline === 'tunai' && $nominalDiterima < (float) $detail['total']) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Jumlah bayar kurang dari total tagihan']);

            return;
        }

        try {
            $service = app(PembayaranPesananOnlineService::class);
            $hasil = $service->bayarDiToko($transaksi, $this->metodeBayarOnline, $nominalDiterima);
            // Serah terima fisik saat bayar di konter — samakan jalur PesananOnlineIndex
            $service->kirimNotifikasiSerahTerima($transaksi);

            if (! $transaksi->durasi_garansi_hari) {
                $transaksi->update([
                    'durasi_garansi_hari' => 3,
                    'garansi_teks' => '3 Hari Toko (Sesuai T&C Penyerahan)',
                    'garansi_berakhir_pada' => now()->addDays(3)->toDateString(),
                ]);
            }
        } catch (\RuntimeException $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);

            return;
        } catch (\Throwable $e) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => 'Gagal memproses pembayaran pesanan online: '.$e->getMessage(),
            ]);

            return;
        }

        $kembalian = (float) ($hasil['kembalian'] ?? 0);
        $pesanSukses = "Pembayaran {$detail['no_transaksi']} berhasil — barang dapat diserahkan.";
        if ($kembalian > 0) {
            $pesanSukses .= ' Kembalian Rp '.number_format($kembalian, 0, ',', '.');
        }

        // Struk bukti serah terima pickup (modal struk generik kasir)
        $this->siapkanStrukPickupOnline($transaksi->fresh(['items.produk', 'pelanggan', 'cabang']), $kembalian);

        // Tutup modal + bersihkan state. Riwayat hari-ini (computed prop) dan
        // badge jumlah order ikut segar otomatis pada render berikutnya.
        $this->tutupPesananOnlineModal();
        $this->showReceiptModal = true;
        $this->checkKasSesi();
        $this->dispatch('alert', ['type' => 'success', 'message' => $pesanSukses]);
    }

    /**
     * Serahkan pesanan online pickup YANG SUDAH LUNAS (tanpa pembayaran baru).
     * Delegasi ke `PembayaranPesananOnlineService::serahkanDiToko()` —
     * single source of truth handover (status→selesai, tanpa potong stok,
     * tanpa jurnal kas, tanpa kasir_id, notifikasi konsumen serah terima).
     * Guard server-side: cabang session + status lunas + pickup + sumber online.
     */
    public function serahkanPesananOnline(): void
    {
        if (! $this->konfirmasiGaransiOnline) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => 'Kasir wajib mengonfirmasi telah menginfokan T&C & masa garansi sparepart ke pelanggan saat penyerahan barang.',
            ]);

            return;
        }

        // Guard anti-tamper: harus lolos query cabang/status/pickup/sumber dulu
        $transaksi = $this->queryPesananOnlinePickup()
            ->whereKey($this->pesananOnlineTerpilih)
            ->where('status', 'lunas')
            ->first();

        if (! $transaksi) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => 'Pesanan tidak ditemukan atau bukan pesanan pickup lunas menunggu serah terima',
            ]);

            return;
        }

        try {
            app(PembayaranPesananOnlineService::class)->serahkanDiToko($transaksi);

            if (! $transaksi->durasi_garansi_hari) {
                $transaksi->update([
                    'durasi_garansi_hari' => 3,
                    'garansi_teks' => '3 Hari Toko (Sesuai T&C Penyerahan)',
                    'garansi_berakhir_pada' => now()->addDays(3)->toDateString(),
                ]);
            }
        } catch (\RuntimeException $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);

            return;
        } catch (\Throwable $e) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => 'Gagal menyerahkan pesanan online: '.$e->getMessage(),
            ]);

            return;
        }

        $pesanSukses = "Pesanan {$transaksi->no_transaksi} telah diserahkan kepada konsumen.";

        // Struk bukti serah terima pickup (tanpa pembayaran baru di kasir)
        $this->siapkanStrukPickupOnline($transaksi->fresh(['items.produk', 'pelanggan', 'cabang']));

        $this->tutupPesananOnlineModal();
        $this->showReceiptModal = true;
        $this->dispatch('alert', ['type' => 'success', 'message' => $pesanSukses]);
    }

    /**
     * Susun `receiptData` bukti serah terima pesanan online pickup — memakai
     * MEKANISME struk generik kasir yang sama dengan Bayar Servis / penjualan:
     * state `showReceiptModal` + partial `partials.thermal-receipt-modal`
     * (preview 58mm, cetak Web/BLE 58mm & 80mm, kirim struk via WhatsApp).
     *
     * @param  float  $kembalian  kembalian uang diterima (null bila handover tanpa bayar baru)
     */
    private function siapkanStrukPickupOnline(Transaksi $transaksi, ?float $kembalian = null): void
    {
        $this->receiptData = [
            'no_transaksi' => $transaksi->no_transaksi,
            'cabang' => $transaksi->cabang?->nama,
            'waktu' => now()->format('d/m/Y H:i'),
            'kasir' => auth()->user()?->name ?? 'Kasir',
            'pelanggan' => $transaksi->pelanggan?->nama ?? 'Konsumen Umum',
            'telepon' => $transaksi->pelanggan?->telepon ?? null,
            'tier' => 'Ambil di Toko (Pickup Online)',
            'items' => $transaksi->items->map(fn ($it) => [
                'nama' => $it->produk?->nama ?? 'Produk',
                'sku' => $it->produk?->sku ?? '-',
                'qty' => (int) $it->jumlah,
                'harga' => (float) $it->harga_satuan,
                'subtotal' => (float) $it->subtotal,
                'catatan' => $it->catatan,
            ])->values()->all(),
            'subtotal' => (float) ($transaksi->subtotal ?: $transaksi->total_akhir),
            'diskon' => (float) ($transaksi->diskon_nominal ?? 0),
            'dpp' => (float) ($transaksi->dpp ?? $transaksi->total_akhir),
            'pajak' => (float) ($transaksi->pajak_nominal ?? 0),
            'ppn_persen' => (float) ($transaksi->pajak_persen ?? 0),
            'total' => (float) $transaksi->total_akhir,
            'bayar' => (float) ($transaksi->jumlah_bayar ?: $transaksi->total_akhir),
            'kembali' => (float) ($kembalian ?? $transaksi->kembalian ?? 0),
            // Handover (tanpa bayar baru) ditandai jelas di baris BAYAR struk
            'metode' => $kembalian === null
                ? 'SUDAH DIBAYAR ONLINE'
                : strtoupper($transaksi->metode_bayar ?? 'TUNAI'),
            'garansi' => $transaksi->garansi_teks ?: '3 Hari Toko (Sesuai T&C Penyerahan)',
        ];

        $this->completedTransactionId = $transaksi->id;
    }

    public function render()
    {
        $productsQuery = Produk::query()
            ->where('is_active', true)
            ->with([
                'skuVariants' => fn ($q) => $q->where('is_active', true),
                'tipeHps',
                'satuanKonversi' => fn ($q) => $q->where(fn ($sub) => $sub->where('is_satuan_jual', true)->orWhereNull('is_satuan_jual'))->orderBy('urutan'),
                'kategoriRelasi',
                'hargaTier',
                'brand',
                'kualitas',
            ]);

        if (! empty($this->search)) {
            $productsQuery->cariPintar($this->search);
        } else {
            $productsQuery->urutKetersediaanDanTerlaris($this->selectedGudangId, session('cabang_id'));
        }

        if ($this->visibleFilters['kategori'] && $this->filterKategoriId) {
            $kat = KategoriProduk::with('children')->find($this->filterKategoriId);
            $katIds = $kat ? array_merge([$kat->id], $kat->children->pluck('id')->all()) : [$this->filterKategoriId];
            $productsQuery->whereIn('kategori_id', $katIds);
        }

        if ($this->visibleFilters['brand'] && $this->filterBrandId) {
            $productsQuery->where('brand_id', $this->filterBrandId);
        }

        if ($this->visibleFilters['kualitas'] && $this->filterKualitasId) {
            $productsQuery->where('kualitas_id', $this->filterKualitasId);
        }

        if ($this->visibleFilters['tipe_hp'] && $this->filterTipeHpId) {
            $targetTipe = TipeHp::find($this->filterTipeHpId);
            $productsQuery->where(function ($q) use ($targetTipe) {
                $q->whereHas('tipeHps', fn ($th) => $th->where('tipe_hp.id', $this->filterTipeHpId));
                if ($targetTipe) {
                    $q->orWhere(function ($sub) use ($targetTipe) {
                        $sub->where('brand_kompatibel', $targetTipe->merk)
                            ->where('model_kompatibel', $targetTipe->model);
                    });
                }
            });
        }

        if ($this->visibleFilters['brand_kompatibel'] && $this->filterBrandKompatibel) {
            $productsQuery->where(function ($q) {
                $q->where('brand_kompatibel', $this->filterBrandKompatibel)
                    ->orWhereHas('tipeHps', fn ($th) => $th->where('tipe_hp.merk', $this->filterBrandKompatibel));
            });
        }

        if ($this->visibleFilters['model_kompatibel'] && $this->filterModelKompatibel) {
            $productsQuery->where(function ($q) {
                $q->where('model_kompatibel', $this->filterModelKompatibel)
                    ->orWhereHas('tipeHps', fn ($th) => $th->where('tipe_hp.model', $this->filterModelKompatibel));
            });
        }

        if ($this->visibleFilters['kondisi'] && $this->filterKondisi) {
            $productsQuery->where('kondisi', $this->filterKondisi);
        }

        if ($this->visibleFilters['satuan'] && $this->filterSatuan) {
            $productsQuery->where('satuan', $this->filterSatuan);
        }

        if ($this->visibleFilters['abc_class'] && $this->filterAbcClass) {
            $productsQuery->where('abc_class', $this->filterAbcClass);
        }

        if ($this->visibleFilters['stok'] && $this->filterHanyaReadyStok) {
            $productsQuery->whereHas('stokItems', function ($q) {
                $q->where('jumlah', '>', 0);
                if ($this->selectedGudangId) {
                    $q->where('gudang_id', $this->selectedGudangId);
                } elseif ($cabangId = session('cabang_id')) {
                    $q->whereIn('gudang_id', function ($gq) use ($cabangId) {
                        $gq->select('id')->from('gudang')->where('cabang_id', $cabangId);
                    });
                }
            });
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

        // Cache master filter data 5 menit untuk kecepatan render Livewire
        $brandKompatibelList = Cache::remember('pos_filter_brand_kompatibel', 300, function () {
            return TipeHp::where('is_active', true)
                ->distinct()
                ->pluck('merk')
                ->merge(Brand::where('is_active', true)->pluck('nama'))
                ->merge(Produk::whereNotNull('brand_kompatibel')->where('brand_kompatibel', '!=', '')->distinct()->pluck('brand_kompatibel'))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
        });

        $modelKompatibelList = TipeHp::where('is_active', true)
            ->when($this->filterBrandKompatibel, fn ($q) => $q->where('merk', $this->filterBrandKompatibel))
            ->distinct()
            ->pluck('model')
            ->merge(
                Produk::whereNotNull('model_kompatibel')
                    ->where('model_kompatibel', '!=', '')
                    ->when($this->filterBrandKompatibel, fn ($q) => $q->where('brand_kompatibel', $this->filterBrandKompatibel))
                    ->distinct()
                    ->pluck('model_kompatibel')
            )
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $config = app(KonfigurasiService::class);
        $kondisiList = $config->get('master_produk_kondisi_list') ?? [
            ['kode' => 'baru', 'nama' => 'Baru'],
            ['kode' => 'oem', 'nama' => 'OEM'],
            ['kode' => 'compatible', 'nama' => 'Compatible'],
            ['kode' => 'bekas', 'nama' => 'Bekas / Copotan'],
        ];

        $kategoriList = KategoriProduk::whereNull('parent_id')->with('children')->where('is_active', true)->get();
        $brandList = Brand::where('is_active', true)->orderBy('nama')->get();
        $kualitasList = KualitasProduk::with('brand')
            ->where('is_active', true)
            ->when($this->filterBrandId, fn ($q) => $q->forBrand($this->filterBrandId))
            ->orderByRaw('brand_id IS NULL ASC')
            ->orderBy('nama')
            ->get();
        $tipeHpList = TipeHp::where('is_active', true)
            ->when($this->filterBrandKompatibel, fn ($q) => $q->where('merk', $this->filterBrandKompatibel))
            ->orderBy('merk')
            ->orderBy('model')
            ->get();
        $satuanList = SatuanUnit::where('is_active', true)->orderBy('nama')->get();

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
            // [POS-ONLINE-PICKUP] computed props pesanan online — pass eksplisit (WAIBS)
            'jumlahPesananOnline' => $this->jumlahPesananOnline,
            'pesananOnlineHasil' => $this->pesananOnlineHasil,
            'pesananOnlineDetail' => $this->pesananOnlineDetail,
            'kembalianOnline' => $this->kembalianOnline,
            'kategoriList' => $kategoriList,
            'brandList' => $brandList,
            'kualitasList' => $kualitasList,
            'tipeHpList' => $tipeHpList,
            'brandKompatibelList' => $brandKompatibelList,
            'modelKompatibelList' => $modelKompatibelList,
            'kondisiList' => $kondisiList,
            'satuanList' => $satuanList,
        ])->layout('layouts.backoffice', ['header' => 'Kasir Point of Sale (POS)']);
    }
}
