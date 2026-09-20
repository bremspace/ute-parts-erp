<?php

namespace App\Modules\Pos\Livewire;

use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Services\PelangganService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Pos\Services\KasSesiState;
use App\Modules\Pos\Services\PricingService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Reseller\Services\KomisiService;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class PosKasir extends Component
{
    // [T-09] state sesi kas
    public $kasAktif = null;

    // Search & Filter state
    public string $search = '';

    public ?int $selectedCustomerId = null;

    public ?int $selectedGudangId = null;

    // Cart state: [item_key => ['produk_id', 'sku_variant_id', 'nama', 'varian', 'harga', 'qty', 'diskon', 'subtotal', 'stok_max']]
    public array $cart = [];

    // Summary state
    public float $diskonPersen = 0.0;

    public float $diskonNominal = 0.0;

    public float $pajakNominal = 0.0;

    // Payment modal state
    public bool $showPaymentModal = false;

    public string $metodeBayar = 'tunai'; // tunai, transfer, qris, split

    public float $jumlahBayar = 0.0;

    public string $catatan = '';

    // Split payment details
    public float $splitTunai = 0.0;

    public float $splitNonTunai = 0.0;

    public string $splitMetodeNonTunai = 'qris';

    // Receipt modal state
    public bool $showReceiptModal = false;

    public ?int $completedTransactionId = null;

    public ?array $receiptData = null;

    // [T-03] Transaksi ditahan (park)
    public bool $showDitahanPanel = false;

    // [T-08] Pencarian & tambah pelanggan quick
    public string $pelangganSearch = '';

    public bool $showPelangganBaruModal = false;

    public array $pelangganBaruForm = ['nama' => '', 'telepon' => '', 'email' => '', 'alamat' => '', 'tanggal_lahir' => ''];

    // [T-09] Kas sesi modal
    public bool $showKasModal = false;

    public bool $kasModeBuka = true;

    public float $kasSaldoAwal = 0;

    public float $kasSaldoFisik = 0;

    public ?array $kasHasil = null;

    // [T-33] sumber saldo awal sesi: manual | carryover (diisi dari sesi tutup sebelumnya)
    public string $kasSumber = 'manual';

    public function mount()
    {
        // Set default gudang dari active session cabang
        $cabangId = session('cabang_id');
        if ($cabangId) {
            $this->selectedGudangId = Gudang::where('cabang_id', $cabangId)->value('id');
        }

        // [T-09] Deteksi sesi kas terbuka utk user/cabang
        $this->checkKasSesi();
    }

    public function checkKasSesi(): void
    {
        $this->kasAktif = app(KasSesiState::class)->sesiKasAktif();
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
    public function scanEnter()
    {
        $q = trim($this->search);
        if ($q === '') {
            return;
        }

        $variant = SkuVariant::where('sku', $q)->where('is_active', true)->first();
        if ($variant) {
            $this->addToCart($variant->produk_id, $variant->id);
            $this->search = '';

            return;
        }

        $produk = Produk::where('id', $q)->orWhere('nama', $q)->first();
        if ($produk) {
            $this->addToCart($produk->id);
            $this->search = '';
        }
    }

    public function addToCart(int $produkId, ?int $variantId = null)
    {
        $produk = Produk::findOrFail($produkId);
        $variant = $variantId ? SkuVariant::find($variantId) : null;
        $itemKey = $variantId ? "{$produkId}-{$variantId}" : "{$produkId}-0";

        // Cek stok tersedia
        $stokTersedia = 999;
        if ($this->selectedGudangId) {
            $stokTersedia = StokItem::where('produk_id', $produkId)
                ->where('sku_variant_id', $variantId)
                ->where('gudang_id', $this->selectedGudangId)
                ->value('jumlah') ?? 0;

            if ($stokTersedia <= 0) {
                $this->dispatch('alert', ['type' => 'error', 'message' => "Stok produk {$produk->nama} habis"]);

                return;
            }
        }

        // Resolusi harga
        $pricingService = app(PricingService::class);
        $pricing = $pricingService->resolve($produk, $this->customer, $variant);
        $harga = $pricing['harga'];

        if (isset($this->cart[$itemKey])) {
            if ($this->cart[$itemKey]['qty'] + 1 > $stokTersedia) {
                $this->dispatch('alert', ['type' => 'warning', 'message' => 'Stok maksimal tercapai']);

                return;
            }
            $this->cart[$itemKey]['qty'] += 1;
            $this->cart[$itemKey]['subtotal'] = $this->cart[$itemKey]['qty'] * $harga;
        } else {
            $this->cart[$itemKey] = [
                'produk_id' => $produkId,
                'sku_variant_id' => $variantId,
                'nama' => $produk->nama,
                'varian' => $variant?->nama_varian ?? 'Standar',
                'harga' => $harga,
                'qty' => 1,
                'diskon' => 0.0,
                'subtotal' => $harga,
                'stok_max' => $stokTersedia,
            ];
        }
    }

    public function updateQty(string $itemKey, int $delta)
    {
        if (! isset($this->cart[$itemKey])) {
            return;
        }

        $newQty = $this->cart[$itemKey]['qty'] + $delta;
        if ($newQty <= 0) {
            $this->removeFromCart($itemKey);

            return;
        }

        if ($newQty > $this->cart[$itemKey]['stok_max']) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Maksimal stok tercapai']);

            return;
        }

        $this->cart[$itemKey]['qty'] = $newQty;
        $this->cart[$itemKey]['subtotal'] = $newQty * $this->cart[$itemKey]['harga'];
    }

    public function removeFromCart(string $itemKey)
    {
        unset($this->cart[$itemKey]);
    }

    public function clearCart()
    {
        $this->cart = [];
        $this->diskonPersen = 0.0;
        $this->diskonNominal = 0.0;
        $this->pajakNominal = 0.0;
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
    }

    public function openPaymentModal()
    {
        if (empty($this->cart)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Keranjang transaksi masih kosong']);

            return;
        }
        $this->jumlahBayar = $this->totalAkhir;
        $this->splitTunai = $this->totalAkhir;
        $this->splitNonTunai = 0.0;
        $this->showPaymentModal = true;
    }

    public function setQuickCash(float $nominal)
    {
        $this->jumlahBayar = $nominal;
    }

    public function processTransaction()
    {
        $cabangId = session('cabang_id') ?? auth()->user()?->cabangs()->first()?->id ?? 1;

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
                    'pajak_nominal' => $this->pajakNominal,
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

                    TransaksiItem::create([
                        'transaksi_id' => $transaksi->id,
                        'produk_id' => $item['produk_id'],
                        'sku_variant_id' => $item['sku_variant_id'],
                        'jumlah' => $item['qty'],
                        'harga_satuan' => $item['harga'],
                        'diskon_nominal' => $item['diskon'],
                        'subtotal' => $item['subtotal'],
                        'hpp' => $produk ? (float) $produk->harga_beli : 0,
                    ]);

                    if ($this->selectedGudangId) {
                        $stok = StokItem::where('produk_id', $item['produk_id'])
                            ->where('sku_variant_id', $item['sku_variant_id'])
                            ->where('gudang_id', $this->selectedGudangId)
                            ->first();

                        $sebelum = $stok ? $stok->jumlah : 0;
                        $setelah = $sebelum - $item['qty'];

                        if ($stok) {
                            $stok->update(['jumlah' => $setelah]);
                        }

                        StokLog::create([
                            'gudang_id' => $this->selectedGudangId,
                            'produk_id' => $item['produk_id'],
                            'sku_variant_id' => $item['sku_variant_id'],
                            'user_id' => auth()->id(),
                            'jenis' => 'penjualan',
                            'referensi_tipe' => Transaksi::class,
                            'referensi_id' => $transaksi->id,
                            'jumlah_sebelum' => $sebelum,
                            'perubahan' => -$item['qty'],
                            'jumlah_setelah' => $setelah,
                            'catatan' => "POS Kasir {$transaksi->no_transaksi}",
                        ]);
                    }
                }

                // Customer points & spending increment
                if ($this->selectedCustomerId) {
                    $pelanggan = Pelanggan::find($this->selectedCustomerId);
                    if ($pelanggan) {
                        $pelanggan->increment('total_belanja_12bulan', $this->totalAkhir);
                        $mult = $pelanggan->tierMembership ? (float) $pelanggan->tierMembership->poin_multiplier : 1.0;
                        $poin = (int) floor(($this->totalAkhir / 1000) * $mult);
                        if ($poin > 0) {
                            $pelanggan->increment('poin_loyalty', $poin);
                        }
                    }
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
                $lines = [
                    // Kasbon (piutang) → debit Piutang Usaha 120-01, bukan Kas
                    ['akun_kode' => $kasbon ? '120-01' : '110-01', 'debit' => $this->totalAkhir, 'kredit' => 0],
                    ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => $this->totalAkhir],
                ];
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

                // Komisi reseller (PRD §4.5)
                if ($this->selectedCustomerId) {
                    $pelanggan = Pelanggan::find($this->selectedCustomerId);
                    if ($pelanggan && $pelanggan->is_reseller) {
                        app(KomisiService::class)->hitungKomisi($transaksi, $pelanggan);
                    }
                }

                // Kasbon → catat Piutang (AR)
                if ($kasbon && $this->selectedCustomerId) {
                    $countPiutang = Piutang::whereDate('created_at', now()->toDateString())->count() + 1;
                    Piutang::create([
                        'no_piutang' => sprintf('AR-%s-%04d', now()->format('Ymd'), $countPiutang),
                        'pelanggan_id' => $this->selectedCustomerId,
                        'transaksi_id' => $transaksi->id,
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

    // ==================== [T-03] PARK / TAHAN ====================

    /** Simpan keranjang sebagai transaksi status 'ditahan' (F6) — belum kurangi stok. */
    public function tahanTransaksi()
    {
        if (empty($this->cart)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Keranjang kosong, tidak ada yang ditahan']);

            return;
        }

        $cabangId = session('cabang_id') ?? auth()->user()?->cabangs()->first()?->id ?? 1;

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
            $this->cart[$key] = $item;
        }
        $this->diskonPersen = (float) ($detail['diskon_persen'] ?? 0);
        $this->diskonNominal = (float) ($detail['diskon_nominal'] ?? 0);
        $this->selectedCustomerId = $transaksi->pelanggan_id;

        // Tandai transaksi asli dipindahkan → status 'batal' (tidak dipakai lagi), tanpa jurnal
        $transaksi->update(['status' => 'dibatalkan', 'catatan' => 'Dilanjutkan (resume) oleh '.(auth()->user()?->name ?? 'kasir')]);

        $this->dispatch('alert', ['type' => 'success', 'message' => 'Transaksi ditahan dilanjutkan — silakan lanjut bayar']);
    }

    // ==================== [T-08] PELANGGAN QUICK-ADD ====================

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
        $this->kasModeBuka = true;
        $this->kasHasil = null;

        // [T-33] Carryover: saldo awal shift berikutnya = saldo_akhir_fisik sesi tutup terakhir
        // (prioritas sesi milik kasir ini, fallback sesi bersama legacy user_id NULL)
        $prev = DB::table('kas_sesi')
            ->where('cabang_id', session('cabang_id'))
            ->where('status', 'tutup')
            ->whereNotNull('saldo_akhir_fisik')
            ->where('user_id', auth()->id())
            ->latest('id')
            ->first();

        if (! $prev) {
            $prev = DB::table('kas_sesi')
                ->where('cabang_id', session('cabang_id'))
                ->where('status', 'tutup')
                ->whereNotNull('saldo_akhir_fisik')
                ->whereNull('user_id')
                ->latest('id')
                ->first();
        }

        $this->kasSumber = $prev ? 'carryover' : 'manual';
        $this->kasSaldoAwal = $prev ? (float) $prev->saldo_akhir_fisik : 0;
        $this->showKasModal = true;
    }

    public function tutupKasModal()
    {
        $this->kasModeBuka = false;
        $sesi = $this->kasAktif;
        $this->kasSaldoFisik = (float) ($sesi->saldo_akhir_sistem ?? $sesi->saldo_awal ?? 0);
        $this->kasHasil = null;
        $this->showKasModal = true;
    }

    public function prosesKas()
    {
        try {
            $svc = app(KasSesiState::class);
            if ($this->kasModeBuka) {
                $this->kasHasil = $svc->bukaKas((float) $this->kasSaldoAwal, session('cabang_id'), auth()->id(), $this->kasSumber);
            } else {
                $this->kasHasil = $svc->tutupKas((float) $this->kasSaldoFisik);
            }
            $this->checkKasSesi();
            $this->dispatch('alert', ['type' => 'success', 'message' => $this->kasModeBuka ? 'Kas dibuka' : 'Kas ditutup'.($this->kasHasil['selisih'] != 0 ? ' — selisih tercatat' : '')]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function render()
    {
        $productsQuery = Produk::query()
            ->where('is_active', true)
            ->with(['skuVariants' => fn ($q) => $q->where('is_active', true)]);

        if (! empty($this->search)) {
            $search = $this->search;
            $productsQuery->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('brand_kompatibel', 'like', "%{$search}%")
                    ->orWhere('model_kompatibel', 'like', "%{$search}%")
                    ->orWhereHas('skuVariants', fn ($sq) => $sq->where('sku', 'like', "%{$search}%"));
            });
        }

        $products = $productsQuery->take(16)->get();
        $customers = Pelanggan::with('tierMembership')->take(10)->get();

        // Explicitly pass all data needed by the view
        return view('modules.pos.livewire.pos-kasir', [
            'products' => $products,
            'customers' => $customers,
            'pelangganCari' => $this->pelangganCari,
            'ditahanList' => $this->ditahanList,
            'kasAktif' => $this->kasAktif,
            'total' => $this->total,
            'diskonTotal' => $this->diskonTotal,
            'totalBayar' => $this->totalBayar,
            'subtotal' => $this->subtotal,
        ])->layout('layouts.backoffice', ['header' => 'Kasir Point of Sale (POS)']);
    }
}
