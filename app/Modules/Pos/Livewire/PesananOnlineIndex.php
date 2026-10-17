<?php

namespace App\Modules\Pos\Livewire;

use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Akunting\Services\PajakService;
use App\Modules\Crm\Services\PelangganService;
use App\Modules\Marketplace\Models\Pengiriman;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Services\PembayaranPesananOnlineService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Traits\PunyaRiwayatAktivitas;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Services\StokDeductionService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class PesananOnlineIndex extends Component
{
    use PunyaRiwayatAktivitas;
    use WithPagination;

    public string $statusFilter = 'lunas'; // 'semua' | 'lunas' | 'menunggu_pembayaran' | 'diproses' | 'dikirim' | 'selesai' | 'batal'

    public string $sumberFilter = 'semua'; // 'semua' | 'marketplace' | 'shopee' | 'tokopedia' | 'omni_lain'

    public string $metodeFilter = 'semua'; // 'semua' | 'ambil_ke_toko' | 'pengiriman'

    public string $search = '';

    public ?int $selectedCabangId = null;

    // Modal detail
    public bool $showDetailModal = false;

    public ?Transaksi $selectedOrder = null;

    // Modal kirim kurir
    public bool $showKirimModal = false;

    public ?int $kirimOrderId = null;

    public string $kurirPilihan = 'J&T Express';

    public string $nomorResi = '';

    public string $catatanPengiriman = '';

    // Modal serah terima / pickup kasir toko
    public bool $showPickupModal = false;

    public ?Transaksi $pickupOrder = null;

    public string $metodeBayarPickup = 'tunai'; // 'tunai' | 'qris' | 'edc' | 'transfer'

    public float $nominalBayarPickup = 0.0;

    public float $kembalianPickup = 0.0;

    // Modal pembatalan
    public bool $showBatalModal = false;

    public ?int $batalOrderId = null;

    public string $alasanBatal = '';

    // Struk / Label pengiriman
    public bool $showReceiptModal = false;

    public ?array $receiptData = null;

    protected $queryString = [
        'statusFilter' => ['except' => 'lunas'],
        'sumberFilter' => ['except' => 'semua'],
        'metodeFilter' => ['except' => 'semua'],
        'search' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->selectedCabangId = session('cabang_id');
        if (request()->has('filter')) {
            $this->statusFilter = request()->get('filter');
        }
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSumberFilter(): void
    {
        $this->resetPage();
    }

    public function updatedMetodeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedNominalBayarPickup(): void
    {
        if ($this->pickupOrder) {
            $this->kembalianPickup = max(0.0, (float) $this->nominalBayarPickup - (float) $this->pickupOrder->total_akhir);
        }
    }

    public function getCountsProperty(): array
    {
        $cabangId = $this->selectedCabangId;

        $base = Transaksi::where(function ($q) {
            $q->where('sumber', 'marketplace')
                ->orWhere('sumber', 'like', 'channel:%');
        })->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId));

        if ($this->sumberFilter !== 'semua') {
            if ($this->sumberFilter === 'marketplace') {
                $base->where('sumber', 'marketplace');
            } elseif ($this->sumberFilter === 'shopee') {
                $base->where('sumber', 'channel:shopee');
            } elseif ($this->sumberFilter === 'tokopedia') {
                $base->where('sumber', 'channel:tokopedia');
            } elseif ($this->sumberFilter === 'omni_lain') {
                $base->where('sumber', 'like', 'channel:%')->whereNotIn('sumber', ['channel:shopee', 'channel:tokopedia']);
            }
        }

        if ($this->metodeFilter !== 'semua') {
            if ($this->metodeFilter === 'ambil_ke_toko') {
                $base->where('catatan', 'like', '%ambil_ke_toko%');
            } else {
                $base->where('catatan', 'not like', '%ambil_ke_toko%');
            }
        }

        return [
            'semua' => (clone $base)->count(),
            'menunggu_pembayaran' => (clone $base)->where('status', 'menunggu_pembayaran')->count(),
            'lunas' => (clone $base)->where('status', 'lunas')->count(),
            'diproses' => (clone $base)->where('status', 'diproses')->count(),
            'dikirim' => (clone $base)->whereIn('status', ['dikirim', 'siap_diambil'])->count(),
            'selesai' => (clone $base)->where('status', 'selesai')->count(),
            'batal' => (clone $base)->where('status', 'batal')->count(),
        ];
    }

    public function lihatDetail(int $id): void
    {
        $this->selectedOrder = Transaksi::with(['items.produk', 'pelanggan', 'cabang', 'pengiriman', 'channelOrder.channel'])
            ->findOrFail($id);
        $this->showDetailModal = true;
    }

    public function tutupDetail(): void
    {
        $this->showDetailModal = false;
        $this->selectedOrder = null;
    }

    /**
     * Konfirmasi pembayaran lunas pesanan online (transfer / manual gateway).
     * COA Standar: Debit 120-02 (Kliring Gateway) / 110-01 (Kas), Kredit 410-03 (Pendapatan Web).
     */
    public function konfirmasiLunas(int $id): void
    {
        $transaksi = Transaksi::with(['items.produk', 'pelanggan', 'cabang'])->findOrFail($id);

        if ($transaksi->status !== 'menunggu_pembayaran') {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Pesanan ini sudah bukan status menunggu pembayaran.']);

            return;
        }

        DB::transaction(function () use ($transaksi) {
            $transaksi->update([
                'status' => 'lunas',
                'metode_bayar' => 'transfer_manual',
                'jumlah_bayar' => (float) $transaksi->total_akhir,
                'paid_at' => now(),
            ]);

            // Potong stok produk
            $stokDeduction = app(StokDeductionService::class);
            $cabangId = $transaksi->cabang_id ?: Cabang::firstOrFail()->id;

            foreach ($transaksi->items as $item) {
                $stokDeduction->kurangiDariGudangTersedia(
                    $item->produk_id,
                    $item->sku_variant_id,
                    (int) $item->jumlah,
                    $cabangId,
                    'penjualan',
                    Transaksi::class,
                    $transaksi->id,
                    null,
                    "Order online manual {$transaksi->no_transaksi}"
                );
            }

            // Jurnal akuntansi terintegrasi COA standar
            $jurnalService = app(JurnalService::class);
            $pajakService = app(PajakService::class);

            $totalAkhir = (float) $transaksi->total_akhir;
            $ppnNominal = max(0.0, (float) ($transaksi->ppn_nominal ?? 0));
            $dpp = $ppnNominal > 0 ? round($totalAkhir - $ppnNominal, 2) : $totalAkhir;
            $noJurnal = $jurnalService->generateNoJurnal('pos', $cabangId);

            // Debit Kliring Gateway 120-02 (atau Kas 110-01) / Kredit Pendapatan Web 410-03
            $lines = [
                ['akun_kode' => '120-02', 'debit' => $totalAkhir, 'kredit' => 0],
                ['akun_kode' => '410-03', 'debit' => 0, 'kredit' => $dpp],
            ];
            foreach ($pajakService->jurnalLines($ppnNominal, $noJurnal, (int) $cabangId, 0) as $ppnLine) {
                $lines[] = $ppnLine;
            }

            $totalHpp = (float) $transaksi->items->sum(fn ($i) => (float) $i->hpp * (int) $i->jumlah);
            if ($totalHpp > 0) {
                $lines[] = ['akun_kode' => '510-02', 'debit' => $totalHpp, 'kredit' => 0];
                $lines[] = ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => $totalHpp];
            }

            $jurnalService->post(
                $noJurnal,
                now(),
                'pos',
                $lines,
                "Jurnal order online {$transaksi->no_transaksi} (lunas verifikasi)",
                $cabangId,
                null,
                Transaksi::class,
                $transaksi->id
            );

            // Integrasi CRM
            if ($transaksi->pelanggan_id) {
                app(PelangganService::class)->tambahBelanjaDanPoin($transaksi->pelanggan_id, $totalAkhir);
            }

            // Notifikasi ke Pelanggan
            $notif = app(NotificationService::class);
            $notif->kirim(
                'inapp',
                $transaksi->pelanggan?->email,
                'Pembayaran Terkonfirmasi',
                "Pembayaran untuk pesanan {$transaksi->no_transaksi} telah diverifikasi kasir. Pesanan Anda segera disiapkan.",
                [
                    'transaksi_id' => $transaksi->id,
                    'pelanggan_id' => $transaksi->pelanggan_id,
                    'kategori' => 'transaksi',
                    'action_url' => '/account',
                    'audience' => 'pelanggan',
                ]
            );

            if (! empty($transaksi->pelanggan?->telepon)) {
                $notif->kirim(
                    'wa',
                    $transaksi->pelanggan->telepon,
                    'Pembayaran Diterima',
                    "Halo {$transaksi->pelanggan->nama}, pembayaran pesanan {$transaksi->no_transaksi} sebesar Rp ".number_format($totalAkhir, 0, ',', '.').' telah diverifikasi.',
                    [
                        'transaksi_id' => $transaksi->id,
                        'pelanggan_id' => $transaksi->pelanggan_id,
                        'audience' => 'pelanggan',
                    ]
                );
            }

            $notif->sinkronkanStatusTransaksi($transaksi, 'lunas');
        });

        $this->dispatch('alert', ['type' => 'success', 'message' => "Pesanan {$transaksi->no_transaksi} berhasil dikonfirmasi lunas."]);
    }

    /**
     * Buka modal proses pickup / serah terima kasir toko.
     */
    public function bukaModalPickup(int $id): void
    {
        $this->pickupOrder = Transaksi::with(['items.produk', 'pelanggan', 'cabang'])->findOrFail($id);
        $this->nominalBayarPickup = (float) $this->pickupOrder->total_akhir;
        $this->kembalianPickup = 0.0;
        $this->metodeBayarPickup = 'tunai';
        $this->showPickupModal = true;
    }

    /**
     * Selesaikan proses pickup di kasir: serah terima barang + bayar di kasir (jika belum lunas).
     *
     * Cabang BELUM LUNAS (menunggu_pembayaran) didelegasikan penuh ke
     * `PembayaranPesananOnlineService::bayarDiToko()` (kontrak API beku):
     * validasi status/pickup, guard sesi kas, potong stok sekali, jurnal
     * 110-04/110-02 + 410-03 + PPN + HPP, update status/kasir_id/paid_at,
     * poin loyalty, serta notifikasi konfirmasi pembayaran.
     * Setelah bayar, notifikasi SERAH TERIMA dikirim via
     * `kirimNotifikasiSerahTerima()` (service — satu-satunya sumber notif serah).
     * RuntimeException (Bahasa Indonesia) → alert error, modal tetap terbuka.
     *
     * Cabang SUDAH LUNAS didelegasikan ke
     * `PembayaranPesananOnlineService::serahkanDiToko()` (single source of truth
     * handover — juga dipakai lane kasir `PosKasir::serahkanPesananOnline()`):
     * status→selesai + catatan serah, TANPA stok/TANPA jurnal/TANPA kasir_id
     * (tidak ada uang masuk laci), plus notifikasi konsumen serah-terima.
     */
    public function prosesPickupKasir(): void
    {
        if (! $this->pickupOrder) {
            return;
        }

        $transaksi = $this->pickupOrder;
        $belumLunas = $transaksi->status === 'menunggu_pembayaran';
        $service = app(PembayaranPesananOnlineService::class);

        try {
            DB::transaction(function () use ($transaksi, $belumLunas, $service) {
                if ($belumLunas) {
                    // Delegasi ke service beku — semua langkah pembayaran di sana.
                    $service->bayarDiToko(
                        $transaksi,
                        $this->metodeBayarPickup, // 'tunai' | 'qris' | 'edc' — diterima service apa adanya
                        (float) $this->nominalBayarPickup
                    );
                    // Serah terima fisik setelah bayar: notifikasi konsumen (pindahan dari sini)
                    $service->kirimNotifikasiSerahTerima($transaksi);
                } else {
                    // Sudah lunas (lunas/siap_diambil/diproses): serah terima fisik via service.
                    $service->serahkanDiToko($transaksi);
                }
            });
        } catch (\RuntimeException $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);

            return; // modal pickup tetap terbuka
        }

        // Tampilkan receipt struk kasir
        $this->cetakLabel($transaksi->id);

        $this->showPickupModal = false;
        $this->pickupOrder = null;

        $this->dispatch('alert', ['type' => 'success', 'message' => "Pesanan {$transaksi->no_transaksi} telah selesai diserahkan kepada konsumen."]);
    }

    /**
     * Staf gudang mulai proses kemas pesanan.
     */
    public function mulaiKemas(int $id): void
    {
        $transaksi = Transaksi::with(['pelanggan', 'pengiriman'])->findOrFail($id);

        $transaksi->update(['status' => 'diproses']);

        if ($transaksi->pengiriman) {
            $transaksi->pengiriman->update(['status' => 'diproses']);
        }

        // Notifikasi ke Pelanggan
        $notif = app(NotificationService::class);
        $notif->kirim(
            'inapp',
            $transaksi->pelanggan?->email,
            'Pesanan Sedang Dikemas',
            "Pesanan {$transaksi->no_transaksi} saat ini sedang dikemas dan dipersiapkan oleh tim gudang Ute Parts.",
            [
                'transaksi_id' => $transaksi->id,
                'pelanggan_id' => $transaksi->pelanggan_id,
                'kategori' => 'transaksi',
                'action_url' => '/account',
                'audience' => 'pelanggan',
            ]
        );

        $notif->sinkronkanStatusTransaksi($transaksi, 'diproses');

        $this->dispatch('alert', ['type' => 'success', 'message' => "Pesanan {$transaksi->no_transaksi} dalam proses pengemasan."]);
    }

    /**
     * Buka modal input resi & kurir pengiriman.
     */
    public function bukaModalKirim(int $id): void
    {
        $this->kirimOrderId = $id;
        $transaksi = Transaksi::with('pengiriman')->findOrFail($id);

        if ($transaksi->pengiriman) {
            $this->kurirPilihan = $transaksi->pengiriman->kurir ?: 'J&T Express';
            $this->nomorResi = $transaksi->pengiriman->tracking_id ?: '';
        } else {
            $this->kurirPilihan = 'Kurir Toko / Internal';
            $this->nomorResi = '';
        }

        $this->showKirimModal = true;
    }

    public function simpanPengiriman(): void
    {
        if (! $this->kirimOrderId) {
            return;
        }

        $transaksi = Transaksi::with(['pelanggan', 'pengiriman', 'cabang'])->findOrFail($this->kirimOrderId);

        $isPickup = str_contains(strtolower($transaksi->catatan ?? ''), 'ambil_ke_toko');
        $newStatus = $isPickup ? 'siap_diambil' : 'dikirim';

        DB::transaction(function () use ($transaksi, $newStatus, $isPickup) {
            $transaksi->update(['status' => $newStatus]);

            if ($transaksi->pengiriman) {
                $transaksi->pengiriman->update([
                    'kurir' => $this->kurirPilihan,
                    'tracking_id' => $this->nomorResi,
                    'status' => 'dikirim',
                ]);
            } else {
                Pengiriman::create([
                    'transaksi_id' => $transaksi->id,
                    'kurir' => $this->kurirPilihan,
                    'layanan' => 'Reguler',
                    'ongkir' => 0,
                    'asal_cabang' => $transaksi->cabang?->nama ?? 'Cabang',
                    'nama_penerima' => $transaksi->pelanggan?->nama ?? 'Pelanggan',
                    'alamat_tujuan' => $transaksi->pelanggan?->alamat ?? '-',
                    'telepon_tujuan' => $transaksi->pelanggan?->telepon ?? '-',
                    'tracking_id' => $this->nomorResi,
                    'status' => 'dikirim',
                ]);
            }

            // Notifikasi ke Pelanggan
            $notif = app(NotificationService::class);
            $judul = $isPickup ? 'Pesanan Siap Diambil di Toko' : 'Pesanan Sedang Dikirim';
            $pesan = $isPickup
                ? "Pesanan {$transaksi->no_transaksi} sudah siap diambil di konter {$transaksi->cabang?->nama}."
                : "Pesanan {$transaksi->no_transaksi} telah dikirim via {$this->kurirPilihan} dengan nomor resi {$this->nomorResi}.";

            $notif->kirim(
                'inapp',
                $transaksi->pelanggan?->email,
                $judul,
                $pesan,
                [
                    'transaksi_id' => $transaksi->id,
                    'pelanggan_id' => $transaksi->pelanggan_id,
                    'kategori' => 'transaksi',
                    'action_url' => '/account',
                    'audience' => 'pelanggan',
                ]
            );

            if (! empty($transaksi->pelanggan?->telepon)) {
                $notif->kirim(
                    'wa',
                    $transaksi->pelanggan->telepon,
                    $judul,
                    "Halo {$transaksi->pelanggan->nama}, {$pesan} Terima kasih telah berbelanja di Ute Parts!",
                    [
                        'transaksi_id' => $transaksi->id,
                        'pelanggan_id' => $transaksi->pelanggan_id,
                        'audience' => 'pelanggan',
                    ]
                );
            }

            $notif->sinkronkanStatusTransaksi($transaksi, $newStatus);
        });

        $this->showKirimModal = false;
        $this->kirimOrderId = null;
        $this->nomorResi = '';

        $this->dispatch('alert', ['type' => 'success', 'message' => "Pengiriman pesanan {$transaksi->no_transaksi} telah diperbarui."]);
    }

    /**
     * Tandai pesanan telah selesai diterima oleh konsumen.
     */
    public function selesaikanPesanan(int $id): void
    {
        $transaksi = Transaksi::with(['pelanggan', 'pengiriman'])->findOrFail($id);

        $transaksi->update(['status' => 'selesai']);

        if ($transaksi->pengiriman) {
            $transaksi->pengiriman->update(['status' => 'terkirim']);
        }

        // Notifikasi ke Pelanggan
        $notif = app(NotificationService::class);
        $notif->kirim(
            'inapp',
            $transaksi->pelanggan?->email,
            'Pesanan Selesai',
            "Pesanan {$transaksi->no_transaksi} telah diselesaikan. Terima kasih telah mempercayakan kebutuhan suku cadang Anda kepada Ute Parts!",
            [
                'transaksi_id' => $transaksi->id,
                'pelanggan_id' => $transaksi->pelanggan_id,
                'kategori' => 'transaksi',
                'action_url' => '/account',
                'audience' => 'pelanggan',
            ]
        );

        $notif->sinkronkanStatusTransaksi($transaksi, 'selesai');

        $this->dispatch('alert', ['type' => 'success', 'message' => "Pesanan {$transaksi->no_transaksi} selesai."]);
    }

    /**
     * Modal batalkan pesanan.
     */
    public function bukaModalBatal(int $id): void
    {
        $this->batalOrderId = $id;
        $this->alasanBatal = '';
        $this->showBatalModal = true;
    }

    public function eksekusiBatal(): void
    {
        if (! $this->batalOrderId) {
            return;
        }

        $transaksi = Transaksi::with(['items', 'pelanggan'])->findOrFail($this->batalOrderId);

        DB::transaction(function () use ($transaksi) {
            $wasPaidOrPacked = in_array($transaksi->status, ['lunas', 'diproses', 'dikirim']);

            // Jika stok sudah terpotong, kembalikan stok ke gudang
            if ($wasPaidOrPacked) {
                $gudang = Gudang::where('cabang_id', $transaksi->cabang_id)->first();
                if ($gudang) {
                    foreach ($transaksi->items as $it) {
                        $stokItem = StokItem::firstOrCreate([
                            'gudang_id' => $gudang->id,
                            'produk_id' => $it->produk_id,
                            'sku_variant_id' => $it->sku_variant_id,
                        ], ['jumlah' => 0]);

                        $saldoAwal = (int) $stokItem->jumlah;
                        $stokItem->increment('jumlah', (int) $it->jumlah);

                        StokLog::create([
                            'stok_item_id' => $stokItem->id,
                            'jenis' => 'masuk',
                            'jumlah' => (int) $it->jumlah,
                            'saldo_awal' => $saldoAwal,
                            'saldo_akhir' => $saldoAwal + (int) $it->jumlah,
                            'keterangan' => "Pengembalian stok pembatalan order {$transaksi->no_transaksi}",
                            'user_id' => auth()->id(),
                        ]);
                    }
                }
            }

            $transaksi->update([
                'status' => 'batal',
                'catatan' => trim(($transaksi->catatan ?? '')." [Dibatalkan: {$this->alasanBatal}]"),
            ]);

            // Notifikasi ke Pelanggan
            $notif = app(NotificationService::class);
            $notif->kirim(
                'inapp',
                $transaksi->pelanggan?->email,
                'Pesanan Dibatalkan',
                "Pesanan {$transaksi->no_transaksi} telah dibatalkan. Alasan: ".($this->alasanBatal ?: 'Dibatalkan oleh staf toko').'.',
                [
                    'transaksi_id' => $transaksi->id,
                    'pelanggan_id' => $transaksi->pelanggan_id,
                    'kategori' => 'transaksi',
                    'action_url' => '/account',
                    'audience' => 'pelanggan',
                ]
            );

            $notif->sinkronkanStatusTransaksi($transaksi, 'batal');
        });

        $this->showBatalModal = false;
        $this->batalOrderId = null;
        $this->alasanBatal = '';

        $this->dispatch('alert', ['type' => 'warning', 'message' => "Pesanan {$transaksi->no_transaksi} telah dibatalkan."]);
    }

    /**
     * Siapkan struk kasir & label pengiriman untuk dicetak.
     */
    public function cetakLabel(int $id): void
    {
        $transaksi = Transaksi::with(['items.produk', 'pelanggan', 'cabang', 'pengiriman'])->findOrFail($id);

        $isPickup = str_contains(strtolower($transaksi->catatan ?? ''), 'ambil_ke_toko');

        $this->receiptData = [
            'no_transaksi' => $transaksi->no_transaksi,
            'tanggal' => $transaksi->created_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'),
            'cabang' => $transaksi->cabang?->nama ?? 'Ute Parts Store',
            'pelanggan' => $transaksi->pelanggan?->nama ?? 'Pelanggan Umum',
            'telepon' => $transaksi->pengiriman?->telepon_tujuan ?? $transaksi->pelanggan?->telepon ?? '-',
            'alamat' => $isPickup ? 'Ambil di Konter Toko (Pickup)' : ($transaksi->pengiriman?->alamat_tujuan ?? $transaksi->pelanggan?->alamat ?? 'Alamat pengiriman'),
            'kurir' => $isPickup ? 'Ambil di Toko' : ($transaksi->pengiriman?->kurir ?? 'Ekspedisi'),
            'resi' => $transaksi->pengiriman?->tracking_id ?? '-',
            'is_pickup' => $isPickup,
            'metode_bayar' => strtoupper($transaksi->metode_bayar ?? 'ONLINE'),
            'items' => $transaksi->items->map(fn ($it) => [
                'nama' => $it->produk?->nama ?? 'Produk',
                'sku' => $it->produk?->sku ?? '-',
                'qty' => (int) $it->jumlah,
                'harga' => (float) $it->harga_satuan,
                'subtotal' => (float) $it->subtotal,
            ])->toArray(),
            'total' => (float) $transaksi->total_akhir,
            'bayar' => (float) ($transaksi->jumlah_bayar ?: $transaksi->total_akhir),
            'kembalian' => (float) ($transaksi->kembalian ?? 0),
            'status' => strtoupper($transaksi->status),
        ];

        $this->showReceiptModal = true;
    }

    public function render()
    {
        $cabangId = $this->selectedCabangId;

        $query = Transaksi::with(['items.produk', 'pelanggan', 'cabang', 'pengiriman', 'channelOrder.channel'])
            ->where(function ($q) {
                $q->where('sumber', 'marketplace')
                    ->orWhere('sumber', 'like', 'channel:%');
            })
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->orderBy('created_at', 'desc');

        // Filter Sumber Platform
        if ($this->sumberFilter === 'marketplace') {
            $query->where('sumber', 'marketplace');
        } elseif ($this->sumberFilter === 'shopee') {
            $query->where('sumber', 'channel:shopee');
        } elseif ($this->sumberFilter === 'tokopedia') {
            $query->where('sumber', 'channel:tokopedia');
        } elseif ($this->sumberFilter === 'omni_lain') {
            $query->where('sumber', 'like', 'channel:%')->whereNotIn('sumber', ['channel:shopee', 'channel:tokopedia']);
        }

        // Filter Metode Pengambilan
        if ($this->metodeFilter === 'ambil_ke_toko') {
            $query->where('catatan', 'like', '%ambil_ke_toko%');
        } elseif ($this->metodeFilter === 'pengiriman') {
            $query->where('catatan', 'not like', '%ambil_ke_toko%');
        }

        // Filter Status
        if ($this->statusFilter === 'lunas') {
            $query->where('status', 'lunas');
        } elseif ($this->statusFilter === 'menunggu_pembayaran') {
            $query->where('status', 'menunggu_pembayaran');
        } elseif ($this->statusFilter === 'diproses') {
            $query->where('status', 'diproses');
        } elseif ($this->statusFilter === 'dikirim') {
            $query->whereIn('status', ['dikirim', 'siap_diambil']);
        } elseif ($this->statusFilter === 'selesai') {
            $query->where('status', 'selesai');
        } elseif ($this->statusFilter === 'batal') {
            $query->where('status', 'batal');
        }

        if (! empty($this->search)) {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('no_transaksi', 'like', $term)
                    ->orWhereHas('pelanggan', fn ($sub) => $sub->where('nama', 'like', $term)->orWhere('telepon', 'like', $term))
                    ->orWhereHas('pengiriman', fn ($sub) => $sub->where('tracking_id', 'like', $term)->orWhere('nama_penerima', 'like', $term))
                    ->orWhereHas('channelOrder', fn ($sub) => $sub->where('channel_order_id', 'like', $term));
            });
        }

        $orders = $query->paginate(15);

        return view('modules.pos.livewire.pesanan-online-index', [
            'orders' => $orders,
            'counts' => $this->counts,
            'cabangs' => Cabang::where('is_active', true)->get(),
        ])->layout('layouts.backoffice', ['header' => 'Pesanan Online']);
    }
}
