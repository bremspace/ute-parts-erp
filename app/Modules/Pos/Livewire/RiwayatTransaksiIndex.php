<?php

namespace App\Modules\Pos\Livewire;

use App\Modules\Crm\Services\PelangganService;
use App\Modules\Marketplace\Services\DuitkuPaymentProcessor;
use App\Modules\Marketplace\Services\DuitkuService;
use App\Modules\Omnichannel\Services\ChannelSyncService;
use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Services\PembayaranPesananOnlineService;
use App\Modules\Pos\Services\TransaksiVoidService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Traits\PunyaRiwayatAktivitas;
use App\Modules\Servis\Models\TiketServis;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class RiwayatTransaksiIndex extends Component
{
    use PunyaRiwayatAktivitas;
    use WithPagination;

    public string $activeTab = 'global'; // 'global' | 'pos' | 'online' | 'servis' | 'mutasi_kas'

    public string $search = '';

    public string $filterTanggal = '';

    public string $filterTanggalPreset = 'today'; // 'today' | 'yesterday' | 'this_week' | 'this_month' | 'custom'

    public string $filterTanggalDari = '';

    public string $filterTanggalSampai = '';

    public string $filterMetode = '';

    public string $filterStatus = '';

    public string $filterPlatform = ''; // '' | 'marketplace' | 'shopee' | 'tokopedia'

    public string $filterMetodePengiriman = ''; // '' | 'pickup' | 'kurir'

    // Modal otorisasi void transaksi
    public bool $showVoidModal = false;

    public ?int $voidTransaksiId = null;

    public ?string $voidNoTransaksi = null;

    public float $voidTotalAkhir = 0;

    public string $voidAlasan = '';

    public string $voidSupervisorAuth = '';

    public string $voidSupervisorPassword = '';

    // Modal detail transaksi / servis / mutasi
    public bool $showDetailModal = false;

    public ?array $selectedDetail = null;

    // Modal struk thermal reusable
    public bool $showReceiptModal = false;

    public ?array $receiptData = null;

    public ?int $completedTransactionId = null;

    protected $queryString = [
        'activeTab' => ['except' => 'global'],
        'search' => ['except' => ''],
        'filterTanggalPreset' => ['except' => 'today'],
        'filterTanggalDari' => ['except' => ''],
        'filterTanggalSampai' => ['except' => ''],
        'filterMetode' => ['except' => ''],
        'filterStatus' => ['except' => ''],
        'filterPlatform' => ['except' => ''],
        'filterMetodePengiriman' => ['except' => ''],
    ];

    public function mount(?string $activeTab = null): void
    {
        $this->activeTab = $activeTab ?? request()->query('tab', request()->query('activeTab', $this->activeTab ?: 'global'));

        $preset = request()->query('preset', request()->query('filterTanggalPreset', ''));
        $dari = request()->query('dari', request()->query('filterTanggalDari', ''));
        $sampai = request()->query('sampai', request()->query('filterTanggalSampai', ''));

        if ($dari || $sampai) {
            $this->filterTanggalPreset = 'custom';
            $this->filterTanggalDari = $dari ?: now()->toDateString();
            $this->filterTanggalSampai = $sampai ?: $this->filterTanggalDari;
        } elseif (in_array($preset, ['today', 'yesterday', 'this_week', 'this_month'], true)) {
            $this->filterTanggalPreset = $preset;
            $this->applyPresetDates($preset);
        } elseif (request()->has('filterTanggal')) {
            $tgl = (string) request()->query('filterTanggal');
            $this->filterTanggalPreset = 'custom';
            $this->filterTanggalDari = $tgl;
            $this->filterTanggalSampai = $tgl;
        } else {
            $this->filterTanggalPreset = 'today';
            $this->applyPresetDates('today');
        }

        $this->syncLegacyFilterTanggal();
    }

    public function updatedFilterTanggalPreset(string $preset): void
    {
        $this->resetPage();
        if ($preset !== 'custom') {
            $this->applyPresetDates($preset);
        } else {
            if (! $this->filterTanggalDari) {
                $this->filterTanggalDari = now()->toDateString();
            }
            if (! $this->filterTanggalSampai) {
                $this->filterTanggalSampai = $this->filterTanggalDari;
            }
        }
        $this->syncLegacyFilterTanggal();
    }

    public function updatedFilterTanggalDari(): void
    {
        $this->filterTanggalPreset = 'custom';
        $this->syncLegacyFilterTanggal();
        $this->resetPage();
    }

    public function updatedFilterTanggalSampai(): void
    {
        $this->filterTanggalPreset = 'custom';
        $this->syncLegacyFilterTanggal();
        $this->resetPage();
    }

    protected function applyPresetDates(string $preset): void
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $now = Carbon::now($tz);

        match ($preset) {
            'yesterday' => (function () use ($now) {
                $y = $now->copy()->subDay()->toDateString();
                $this->filterTanggalDari = $y;
                $this->filterTanggalSampai = $y;
            })(),
            'this_week' => (function () use ($now) {
                $this->filterTanggalDari = $now->copy()->startOfWeek()->toDateString();
                $this->filterTanggalSampai = $now->copy()->endOfWeek()->toDateString();
            })(),
            'this_month' => (function () use ($now) {
                $this->filterTanggalDari = $now->copy()->startOfMonth()->toDateString();
                $this->filterTanggalSampai = $now->copy()->endOfMonth()->toDateString();
            })(),
            default => (function () use ($now) {
                $t = $now->toDateString();
                $this->filterTanggalDari = $t;
                $this->filterTanggalSampai = $t;
            })(),
        };
    }

    protected function syncLegacyFilterTanggal(): void
    {
        $this->filterTanggal = $this->filterTanggalDari ?: now()->toDateString();
    }

    /**
     * Mendapatkan rentang batas waktu [start, end] dalam Carbon UTC
     * berdasarkan input tanggal lokal cabang/aplikasi.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function getDateBoundaries(): array
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $dari = $this->filterTanggalDari ?: now($tz)->toDateString();
        $sampai = $this->filterTanggalSampai ?: $dari;

        if ($dari > $sampai) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        $startLocal = Carbon::parse($dari.' 00:00:00', $tz);
        $endLocal = Carbon::parse($sampai.' 23:59:59', $tz);

        return [$startLocal, $endLocal];
    }

    protected function getActiveCabangId(): ?int
    {
        $id = session('cabang_id');
        if ($id) {
            return (int) $id;
        }

        $userCabang = auth()->user()?->cabangs()->first()?->id;
        if ($userCabang) {
            session(['cabang_id' => $userCabang]);

            return (int) $userCabang;
        }

        $firstCabang = Cabang::value('id');
        if ($firstCabang) {
            session(['cabang_id' => $firstCabang]);

            return (int) $firstCabang;
        }

        return null;
    }

    public function bukaVoidModal(int $id): void
    {
        $cabangId = $this->getActiveCabangId();
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
        $this->resetValidation(['voidAlasan', 'voidSupervisorAuth', 'voidSupervisorPassword']);
    }

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

        $cabangId = $this->getActiveCabangId();
        $transaksi = Transaksi::when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->where('id', $this->voidTransaksiId)
            ->first();

        if (! $transaksi) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Data transaksi tidak ditemukan']);

            return;
        }

        try {
            $voidService = app(TransaksiVoidService::class);
            $supervisor = $voidService->verifikasiSupervisor($this->voidSupervisorAuth, $this->voidSupervisorPassword);
            $voidService->voidTransaksi($transaksi, $supervisor, $this->voidAlasan);

            $noTrx = $transaksi->no_transaksi;
            $this->tutupVoidModal();

            if ($this->selectedDetail && ($this->selectedDetail['id'] ?? null) === $transaksi->id) {
                $this->selectedDetail['status'] = 'void';
            }

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

    public function updatedActiveTab(): void
    {
        $this->resetPage();
        $this->resetValidation();
        $this->filterStatus = '';
        $this->filterMetode = '';
        $this->filterPlatform = '';
        $this->filterMetodePengiriman = '';
    }

    public function updatedFilterPlatform(): void
    {
        $this->resetPage();
    }

    public function updatedFilterMetodePengiriman(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterTanggal(): void
    {
        $this->filterTanggalPreset = 'custom';
        $this->filterTanggalDari = $this->filterTanggal;
        $this->filterTanggalSampai = $this->filterTanggal;
        $this->resetPage();
    }

    public function resetFilter(): void
    {
        $this->reset(['search', 'filterMetode', 'filterStatus', 'filterPlatform', 'filterMetodePengiriman']);
        $this->filterTanggalPreset = 'today';
        $this->applyPresetDates('today');
        $this->syncLegacyFilterTanggal();
        $this->resetPage();
    }

    public function lihatDetailTransaksi(int $id): void
    {
        $cabangId = $this->getActiveCabangId();
        $trx = Transaksi::with(['items.produk', 'pelanggan', 'kasir', 'voidBy', 'tiketServis', 'pengiriman', 'channelOrder.channel', 'returnPenjualan.items.produk', 'piutang'])
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->findOrFail($id);

        $isOnline = in_array($trx->sumber, ['marketplace']) || str_starts_with((string) $trx->sumber, 'channel:');
        $diskonKasirItem = (float) $trx->diskon_nominal;
        if ((float) ($trx->diskon_persen ?? 0) > 0) {
            $diskonKasirItem += round(((float) $trx->subtotal * (float) $trx->diskon_persen) / 100, 2);
        }

        $this->selectedDetail = [
            'tipe' => $isOnline ? 'online' : 'pos',
            'id' => $trx->id,
            'no_transaksi' => $trx->no_transaksi,
            'tanggal' => $trx->created_at?->format('d/m/Y H:i:s'),
            'kasir' => $trx->kasir?->name ?? ($isOnline ? 'Sistem Online' : 'Kasir'),
            'pelanggan' => $trx->pelanggan?->nama ?? 'Pelanggan Umum',
            'metode_bayar' => $trx->metode_bayar,
            'subtotal' => (float) $trx->subtotal,
            'diskon' => $diskonKasirItem,
            'pajak' => (float) $trx->pajak_nominal,
            'total' => (float) $trx->total_akhir,
            'bayar' => (float) $trx->jumlah_bayar,
            'kembalian' => (float) $trx->kembalian,
            'status' => $trx->status,
            'void' => $trx->status === 'void' ? [
                'oleh' => $trx->voidBy?->name ?? '-',
                'pada' => $trx->void_at?->format('d/m/Y H:i:s'),
                'alasan' => $trx->void_alasan,
            ] : null,
            'returns' => $trx->returnPenjualan->map(fn ($r) => [
                'id' => $r->id,
                'no_return' => $r->no_return,
                'tanggal' => $r->tanggal?->format('d/m/Y'),
                'status' => $r->status,
                'tipe_penyelesaian' => $r->tipe_penyelesaian,
                'jumlah' => (float) $r->jumlah,
                'alasan' => $r->alasan,
            ])->toArray(),
            'sumber' => $trx->sumber,
            'catatan' => $trx->catatan,
            'items' => $trx->items->map(fn ($it) => [
                'nama' => $it->produk?->nama ?? 'Produk',
                'sku' => $it->produk?->sku ?? '-',
                'qty' => (int) $it->jumlah,
                'harga' => (float) $it->harga_satuan,
                'subtotal' => (float) $it->subtotal,
            ])->toArray(),
            'tiket_servis_id' => $trx->tiket_servis_id,
            'no_tiket' => $trx->tiketServis?->no_tiket,
            'pengiriman' => $trx->pengiriman ? [
                'kurir' => $trx->pengiriman->kurir,
                'layanan' => $trx->pengiriman->layanan,
                'no_resi' => $trx->pengiriman->tracking_id ?? $trx->pengiriman->no_resi ?? null,
                'status' => $trx->pengiriman->status,
                'ongkir' => (float) $trx->pengiriman->ongkir,
                'nama_penerima' => $trx->pengiriman->nama_penerima,
                'telepon_tujuan' => $trx->pengiriman->telepon_tujuan,
                'alamat_tujuan' => $trx->pengiriman->alamat_tujuan,
            ] : null,
            'channel' => $trx->channelOrder?->channel?->nama,
            'channel_platform' => $trx->channelOrder?->channel?->platform,
            'channel_order_id' => $trx->channelOrder?->channel_order_id,
            'piutang' => $trx->piutang ? [
                'id' => $trx->piutang->id,
                'no_piutang' => $trx->piutang->no_piutang,
                'status' => $trx->piutang->status,
                'jumlah' => (float) $trx->piutang->jumlah,
                'jumlah_dibayar' => (float) $trx->piutang->jumlah_dibayar,
                'sisa' => (float) $trx->piutang->sisa,
                'jatuh_tempo' => $trx->piutang->jatuh_tempo?->format('d/m/Y'),
            ] : null,
        ];

        $this->showDetailModal = true;
    }

    public function lihatDetailServis(int $id): void
    {
        $cabangId = $this->getActiveCabangId();
        $tiket = TiketServis::with(['pelanggan', 'teknisi', 'items', 'spareparts.produk', 'transaksi.kasir', 'cabang'])
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->findOrFail($id);

        $rincian = $tiket->getRincianBiayaLengkap();

        $this->selectedDetail = [
            'tipe' => 'servis',
            'id' => $tiket->id,
            'no_tiket' => $tiket->no_tiket,
            'no_transaksi' => $tiket->transaksi?->no_transaksi,
            'tanggal_masuk' => $tiket->created_at?->format('d/m/Y H:i'),
            'tanggal_selesai' => $tiket->tanggal_selesai?->format('d/m/Y H:i') ?? '-',
            'tanggal_bayar' => $tiket->tanggal_bayar?->format('d/m/Y H:i') ?? '-',
            'pelanggan' => $tiket->pelanggan?->nama ?? $tiket->nama_pelanggan ?? 'Pelanggan Umum',
            'telepon' => $tiket->pelanggan?->telepon ?? $tiket->telepon_pelanggan ?? '-',
            'perangkat' => $tiket->jenis_hp.($tiket->seri_hp ? ' ('.$tiket->seri_hp.')' : ''),
            'keluhan' => $tiket->keluhan ?? '-',
            'teknisi' => $tiket->teknisi?->name ?? 'Belum Ditugaskan',
            'kasir' => $tiket->transaksi?->kasir?->name ?? '-',
            'metode_bayar' => $tiket->metode_pembayaran ?? $tiket->transaksi?->metode_bayar ?? 'tunai',
            'status' => $tiket->status,
            'status_pembayaran' => $tiket->status_pembayaran,
            'no_jurnal' => $tiket->no_jurnal_bayar ?? '-',
            'total_jasa' => (float) $rincian['total_jasa'],
            'total_part' => (float) $rincian['total_part'],
            'total' => (float) $rincian['total'],
            'items' => $rincian['items'],
            'catatan' => $tiket->catatan_teknisi ?? $tiket->keterangan ?? '-',
        ];

        $this->showDetailModal = true;
    }

    public function lihatDetailMutasi(int $id): void
    {
        $cabangId = $this->getActiveCabangId();
        $mutasi = DB::table('kas_mutasi_laci')
            ->leftJoin('users', 'kas_mutasi_laci.user_id', '=', 'users.id')
            ->leftJoin('akun_coa', 'kas_mutasi_laci.akun_lawan_kode', '=', 'akun_coa.kode')
            ->select(
                'kas_mutasi_laci.*',
                'users.name as user_name',
                'akun_coa.nama as akun_nama'
            )
            ->when($cabangId, fn ($q) => $q->where('kas_mutasi_laci.cabang_id', $cabangId))
            ->where('kas_mutasi_laci.id', $id)
            ->first();

        if (! $mutasi) {
            return;
        }

        $this->selectedDetail = [
            'tipe' => 'mutasi',
            'id' => $mutasi->id,
            'no_jurnal' => $mutasi->no_jurnal ?? ('MUTASI-#'.$mutasi->id),
            'jenis' => $mutasi->jenis,
            'nominal' => (float) $mutasi->nominal,
            'tanggal' => Carbon::parse($mutasi->created_at)->format('d/m/Y H:i:s'),
            'operator' => $mutasi->user_name ?? 'Kasir',
            'akun_kode' => $mutasi->akun_lawan_kode ?? '-',
            'akun_nama' => $mutasi->akun_nama ?? 'Kas',
            'keterangan' => $mutasi->keterangan ?? '-',
        ];

        $this->showDetailModal = true;
    }

    public function lihatDetailRetur(int $id): void
    {
        $cabangId = $this->getActiveCabangId();
        $retur = ReturnPenjualan::with(['transaksi.items.produk', 'pelanggan', 'user', 'items.produk', 'tukarTransaksi', 'cabang'])
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->findOrFail($id);

        $this->selectedDetail = [
            'tipe' => 'retur',
            'id' => $retur->id,
            'no_return' => $retur->no_return,
            'no_transaksi' => $retur->transaksi?->no_transaksi ?? '-',
            'transaksi_id' => $retur->transaksi_id,
            'tanggal' => $retur->tanggal?->format('d/m/Y') ?? $retur->created_at?->format('d/m/Y H:i'),
            'pelanggan' => $retur->pelanggan?->nama ?? 'Pelanggan Umum',
            'operator' => $retur->user?->name ?? 'Kasir',
            'tipe_penyelesaian' => $retur->tipe_penyelesaian,
            'metode_pengembalian' => $retur->metode_pengembalian,
            'status' => $retur->status,
            'jumlah' => (float) $retur->jumlah,
            'alasan' => $retur->alasan ?? '-',
            'catatan_qc' => $retur->catatan_qc ?? '-',
            'items' => $retur->items->map(fn ($it) => [
                'nama' => $it->produk?->nama ?? 'Produk',
                'sku' => $it->produk?->sku ?? '-',
                'qty' => (float) $it->jumlah,
                'harga' => (float) $it->harga_satuan,
                'subtotal' => (float) $it->subtotal,
                'kondisi' => $it->kondisi_barang,
                'sn' => $it->nomor_seri ?? [],
            ])->toArray(),
            'tukar_transaksi' => $retur->tukarTransaksi ? [
                'id' => $retur->tukarTransaksi->id,
                'no_transaksi' => $retur->tukarTransaksi->no_transaksi,
                'total' => (float) $retur->tukarTransaksi->total_akhir,
                'selisih' => (float) $retur->selisih_nominal,
            ] : null,
        ];

        $this->showDetailModal = true;
    }

    public function cetakUlangStruk(string $tipe, int $id): void
    {
        $cabangId = $this->getActiveCabangId();

        if ($tipe === 'pos' || $tipe === 'online') {
            $trx = Transaksi::with(['items.produk', 'pelanggan.tierMembership', 'kasir', 'cabang'])
                ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
                ->find($id);

            if (! $trx) {
                return;
            }

            $kasirName = $trx->kasir?->name;
            if (! $kasirName) {
                $kasirName = $trx->sumber === 'marketplace' ? 'Web Store' : (str_starts_with((string) $trx->sumber, 'channel:') ? strtoupper(str_replace('channel:', '', $trx->sumber)) : 'Kasir');
            }

            $pelanggan = $trx->pelanggan;
            $saldoPoin = $pelanggan?->poin_loyalty ?? 0;
            $tipeKonsumen = $pelanggan ? app(PelangganService::class)->tipeKonsumen($pelanggan) : 'retail';

            $items = $trx->items->map(function ($it) {
                $retailPerUnit = (float) ($it->produk?->harga_jual_retail ?? $it->harga_satuan);
                if ($it->konversi > 0 && $it->satuan !== ($it->produk?->satuan ?: 'pcs')) {
                    $retailPerUnit = round($retailPerUnit * (float) $it->konversi, 2);
                }
                $hargaSatuan = (float) $it->harga_satuan;
                $qty = (int) $it->jumlah;
                $diskonPerUnit = max(0, $retailPerUnit - $hargaSatuan);

                return [
                    'nama' => $it->produk?->nama ?? 'Produk',
                    'qty' => $qty,
                    'satuan' => $it->satuan,
                    'konversi' => $it->konversi,
                    'harga' => $hargaSatuan,
                    'harga_retail' => $retailPerUnit,
                    'diskon_per_unit' => $diskonPerUnit,
                    'diskon_total' => $diskonPerUnit * $qty,
                    'subtotal_retail' => $retailPerUnit * $qty,
                    'subtotal' => (float) $it->subtotal,
                ];
            })->toArray();

            $subtotalRetail = array_sum(array_column($items, 'subtotal_retail'));
            $modeReward = $trx->mode_reward_mitra ?? 'diskon_langsung';
            $diskonTier = $modeReward === 'komisi_akumulasi' ? 0.0 : max(0, $subtotalRetail - (float) $trx->subtotal);

            $diskonPartai = (float) ($trx->diskon_partai ?? 0);
            $diskonPoin = (float) ($trx->diskon_poin ?? 0);
            $poinDitukar = (int) ($trx->poin_ditukar ?? 0);
            $komisiMitra = (float) ($trx->komisi_mitra_nominal ?? 0);
            $diskonKasir = (float) $trx->diskon_nominal;
            if ((float) ($trx->diskon_persen ?? 0) > 0) {
                $diskonKasir += round(((float) $trx->subtotal * (float) $trx->diskon_persen) / 100, 2);
            }
            $hematTotal = $diskonTier + $diskonPartai + $diskonPoin + $diskonKasir;

            $this->completedTransactionId = $trx->id;
            $this->receiptData = [
                'no_transaksi' => $trx->no_transaksi,
                'waktu' => $trx->created_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'),
                'kasir' => $kasirName,
                'pelanggan' => $trx->pelanggan?->nama ?? 'Pelanggan Umum',
                'telepon' => $trx->pelanggan?->telepon ?? null,
                'tier' => $trx->pelanggan?->tierMembership?->nama ?? ($tipeKonsumen === 'reseller' ? 'Reseller' : ($tipeKonsumen === 'agen' ? 'Agen' : 'Retail')),
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
                'subtotal' => (float) $trx->subtotal,
                'diskon' => $diskonKasir,
                'diskon_nominal' => (float) $trx->diskon_nominal,
                'diskon_persen' => (float) ($trx->diskon_persen ?? 0),
                'pajak' => (float) $trx->pajak_nominal,
                'ppn_persen' => (float) ($trx->ppn_persen ?? 0),
                'total' => (float) $trx->total_akhir,
                'bayar' => (float) ($trx->jumlah_bayar ?: $trx->total_akhir),
                'kembalian' => (float) ($trx->kembalian ?? 0),
                'hemat_total' => $hematTotal,
                'metode' => strtoupper($trx->metode_bayar ?: 'ONLINE'),
                'cabang' => $trx->cabang?->nama ?? 'Ute Parts Store',
                'garansi' => $trx->garansi_teks ?: null,
            ];

            $this->showReceiptModal = true;
        } elseif ($tipe === 'servis') {
            $tiket = TiketServis::with(['pelanggan', 'cabang', 'transaksi.kasir'])
                ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
                ->find($id);

            if (! $tiket) {
                return;
            }

            $rincian = $tiket->getRincianBiayaLengkap();
            $this->completedTransactionId = $tiket->transaksi?->id;
            $this->receiptData = [
                'no_transaksi' => $tiket->transaksi?->no_transaksi ?? $tiket->no_tiket,
                'no_tiket' => $tiket->no_tiket,
                'jenis_hp' => $tiket->jenis_hp,
                'waktu' => $tiket->tanggal_bayar?->format('d/m/Y H:i') ?? $tiket->tanggal_selesai?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'),
                'kasir' => $tiket->transaksi?->kasir?->name ?? auth()->user()?->name ?? 'Kasir',
                'pelanggan' => $tiket->pelanggan?->nama ?? $tiket->nama_pelanggan ?? 'Pelanggan Umum',
                'telepon' => $tiket->telepon_pelanggan ?? $tiket->pelanggan?->telepon ?? null,
                'items' => array_map(fn ($it) => [
                    'nama' => $it['nama'],
                    'qty' => (int) ($it['qty'] ?? 1),
                    'harga' => (float) ($it['harga'] ?? 0),
                    'subtotal' => (float) ($it['subtotal'] ?? 0),
                ], $rincian['items']),
                'subtotal' => (float) $rincian['total'],
                'diskon' => 0.0,
                'pajak' => 0.0,
                'total' => (float) $rincian['total'],
                'bayar' => (float) ($tiket->transaksi?->jumlah_bayar ?? $rincian['total']),
                'kembalian' => (float) ($tiket->transaksi?->kembalian ?? 0),
                'metode' => strtoupper($tiket->metode_pembayaran ?? $tiket->transaksi?->metode_bayar ?? 'TUNAI'),
                'cabang' => $tiket->cabang?->nama ?? 'Ute Parts Servis',
            ];

        } elseif ($tipe === 'retur') {
            $retur = ReturnPenjualan::with(['items.produk', 'pelanggan', 'user', 'cabang'])
                ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
                ->find($id);

            if (! $retur) {
                return;
            }

            $items = $retur->items->map(fn ($it) => [
                'nama' => '[RETUR] '.($it->produk?->nama ?? 'Produk'),
                'qty' => (int) $it->jumlah,
                'harga' => (float) $it->harga_satuan,
                'subtotal' => (float) $it->subtotal,
            ])->toArray();

            $this->completedTransactionId = $retur->transaksi_id;
            $this->receiptData = [
                'no_transaksi' => $retur->no_return,
                'waktu' => $retur->tanggal?->format('d/m/Y H:i') ?? $retur->created_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'),
                'kasir' => $retur->user?->name ?? 'Kasir',
                'pelanggan' => $retur->pelanggan?->nama ?? 'Pelanggan Umum',
                'telepon' => $retur->pelanggan?->telepon ?? null,
                'items' => $items,
                'subtotal' => (float) $retur->jumlah,
                'diskon' => 0.0,
                'pajak' => 0.0,
                'total' => (float) $retur->jumlah,
                'bayar' => (float) $retur->jumlah,
                'kembalian' => 0.0,
                'metode' => 'RETUR ('.strtoupper($retur->metode_pengembalian ?? 'KAS').')',
                'cabang' => $retur->cabang?->nama ?? 'Ute Parts Store',
            ];

            $this->showReceiptModal = true;
        }
    }

    public function tutupDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedDetail = null;
    }

    public function render()
    {
        $cabangId = $this->getActiveCabangId();
        [$startUtc, $endUtc] = $this->getDateBoundaries();

        // Ringkasan metrik periode / tanggal terpilih
        $ringkasan = [
            'total_penjualan_pos' => 0.0,
            'count_penjualan_pos' => 0,
            'total_pesanan_online' => 0.0,
            'count_pesanan_online' => 0,
            'total_mutasi_masuk' => 0.0,
            'total_mutasi_keluar' => 0.0,
            'total_servis_selesai' => 0.0,
            'count_servis_selesai' => 0,
            'total_arus_masuk' => 0.0,
            'total_kas_bersih' => 0.0,
        ];

        if ($cabangId) {
            // POS penjualan (agregat total & count sekali query)
            $posStats = Transaksi::where('cabang_id', $cabangId)
                ->where(function ($q) {
                    $q->whereNull('sumber')->orWhere('sumber', 'pos');
                })
                ->whereBetween('created_at', [$startUtc, $endUtc])
                ->where('status', 'selesai')
                ->selectRaw('COALESCE(SUM(total_akhir), 0) as total, COUNT(*) as count')
                ->first();

            $ringkasan['total_penjualan_pos'] = (float) ($posStats->total ?? 0);
            $ringkasan['count_penjualan_pos'] = (int) ($posStats->count ?? 0);

            // Pesanan Online (Web Ute Store & Omnichannel)
            $onlineStats = Transaksi::where('cabang_id', $cabangId)
                ->where(function ($q) {
                    $q->where('sumber', 'marketplace')
                        ->orWhere('sumber', 'like', 'channel:%');
                })
                ->whereBetween('created_at', [$startUtc, $endUtc])
                ->whereIn('status', ['lunas', 'diproses', 'dikirim', 'siap_diambil', 'selesai'])
                ->selectRaw('COALESCE(SUM(total_akhir), 0) as total, COUNT(*) as count')
                ->first();

            $ringkasan['total_pesanan_online'] = (float) ($onlineStats->total ?? 0);
            $ringkasan['count_pesanan_online'] = (int) ($onlineStats->count ?? 0);

            // Mutasi kas laci
            $mutasiStats = DB::table('kas_mutasi_laci')
                ->where('cabang_id', $cabangId)
                ->whereBetween('created_at', [$startUtc, $endUtc])
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN jenis = 'masuk' THEN nominal ELSE 0 END), 0) as total_masuk,
                    COALESCE(SUM(CASE WHEN jenis = 'keluar' THEN nominal ELSE 0 END), 0) as total_keluar
                ")
                ->first();

            $ringkasan['total_mutasi_masuk'] = (float) ($mutasiStats->total_masuk ?? 0);
            $ringkasan['total_mutasi_keluar'] = (float) ($mutasiStats->total_keluar ?? 0);

            // Servis selesai & lunas (transaksi pos servis + tiket servis lunas langsung)
            $servisTrxStats = Transaksi::where('cabang_id', $cabangId)
                ->where('sumber', 'servis')
                ->whereBetween('created_at', [$startUtc, $endUtc])
                ->where('status', 'selesai')
                ->selectRaw('COALESCE(SUM(total_akhir), 0) as total, COUNT(*) as count')
                ->first();

            $servisDirectStats = TiketServis::where('cabang_id', $cabangId)
                ->whereNull('transaksi_id')
                ->where('status_pembayaran', 'lunas')
                ->where(function ($q) use ($startUtc, $endUtc) {
                    $q->whereBetween('tanggal_bayar', [$startUtc, $endUtc])
                        ->orWhereBetween('tanggal_selesai', [$startUtc, $endUtc]);
                })
                ->selectRaw('COALESCE(SUM(estimasi_biaya), 0) as total, COUNT(*) as count')
                ->first();

            $servisTrxSum = (float) ($servisTrxStats->total ?? 0);
            $servisTrxCount = (int) ($servisTrxStats->count ?? 0);
            $servisDirectSum = (float) ($servisDirectStats->total ?? 0);
            $servisDirectCount = (int) ($servisDirectStats->count ?? 0);

            $ringkasan['total_servis_selesai'] = $servisTrxSum + $servisDirectSum;
            $ringkasan['count_servis_selesai'] = $servisTrxCount + $servisDirectCount;

            // Total arus masuk & kas bersih
            $ringkasan['total_arus_masuk'] = $ringkasan['total_penjualan_pos'] + $ringkasan['total_pesanan_online'] + $ringkasan['total_servis_selesai'] + $ringkasan['total_mutasi_masuk'];
            $ringkasan['total_kas_bersih'] = $ringkasan['total_arus_masuk'] - $ringkasan['total_mutasi_keluar'];
        }

        // Query per Tab
        $dataList = null;
        if ($this->activeTab === 'global') {
            $dataList = $cabangId ? $this->getGlobalData($cabangId) : null;
        } elseif ($this->activeTab === 'pos') {
            $dataList = $cabangId ? $this->getPosData($cabangId) : null;
        } elseif ($this->activeTab === 'online') {
            $dataList = $cabangId ? $this->getOnlineData($cabangId) : null;
        } elseif ($this->activeTab === 'servis') {
            $dataList = $cabangId ? $this->getServisData($cabangId) : null;
        } elseif ($this->activeTab === 'mutasi_kas') {
            $dataList = $cabangId ? $this->getMutasiData($cabangId) : null;
        }

        return view('modules.pos.livewire.riwayat-transaksi-index', [
            'ringkasan' => $ringkasan,
            'dataList' => $dataList,
        ])->layout('layouts.backoffice', ['header' => 'Riwayat Transaksi']);
    }

    protected function getGlobalData(int $cabangId)
    {
        [$startUtc, $endUtc] = $this->getDateBoundaries();
        $metode = $this->filterMetode;
        $status = $this->filterStatus; // 'semua' | 'pos' | 'online' | 'servis' | 'retur' | 'void' | 'mutasi_masuk' | 'mutasi_keluar'
        $search = trim($this->search);

        // 1. Transaksi POS Reguler
        $qPos = DB::table('transaksi as t')
            ->leftJoin('users as u', 't.kasir_id', '=', 'u.id')
            ->leftJoin('pelanggan as p', 't.pelanggan_id', '=', 'p.id')
            ->where('t.cabang_id', $cabangId)
            ->where(function ($q) {
                $q->whereNull('t.sumber')->orWhere('t.sumber', 'pos');
            })
            ->whereBetween('t.created_at', [$startUtc, $endUtc])
            ->select([
                't.id as id',
                DB::raw("'pos' as jenis"),
                't.no_transaksi as no_referensi',
                't.created_at as waktu',
                DB::raw("COALESCE(p.nama, 'Pelanggan Umum') as pihak"),
                DB::raw("COALESCE(u.name, 'Kasir') as operator"),
                't.metode_bayar as metode',
                't.total_akhir as nominal',
                DB::raw("'in' as arah_kas"),
                't.status as status',
                DB::raw("COALESCE(t.catatan, 'Penjualan Kasir POS') as deskripsi"),
                't.id as target_id',
            ]);

        if ($metode) {
            $qPos->where('t.metode_bayar', $metode);
        }

        // 2. Transaksi Pesanan Online (Web Ute Store & Omnichannel)
        $qOnline = DB::table('transaksi as t')
            ->leftJoin('users as u', 't.kasir_id', '=', 'u.id')
            ->leftJoin('pelanggan as p', 't.pelanggan_id', '=', 'p.id')
            ->where('t.cabang_id', $cabangId)
            ->where(function ($q) {
                $q->where('t.sumber', 'marketplace')
                    ->orWhere('t.sumber', 'like', 'channel:%');
            })
            ->whereBetween('t.created_at', [$startUtc, $endUtc])
            ->select([
                't.id as id',
                DB::raw("'online' as jenis"),
                't.no_transaksi as no_referensi',
                't.created_at as waktu',
                DB::raw("COALESCE(p.nama, 'Pelanggan Online') as pihak"),
                DB::raw("CASE WHEN t.sumber = 'marketplace' THEN 'Web Ute' ELSE COALESCE(u.name, REPLACE(t.sumber, 'channel:', '')) END as operator"),
                't.metode_bayar as metode',
                't.total_akhir as nominal',
                DB::raw("'in' as arah_kas"),
                't.status as status',
                DB::raw("COALESCE(t.catatan, 'Pesanan Online') as deskripsi"),
                't.id as target_id',
            ]);

        if ($metode) {
            $qOnline->where('t.metode_bayar', $metode);
        }

        // 3. Transaksi Servis (Pelunasan via POS)
        $qServisTrx = DB::table('transaksi as t')
            ->leftJoin('users as u', 't.kasir_id', '=', 'u.id')
            ->leftJoin('pelanggan as p', 't.pelanggan_id', '=', 'p.id')
            ->leftJoin('tiket_servis as s', 't.tiket_servis_id', '=', 's.id')
            ->where('t.cabang_id', $cabangId)
            ->where('t.sumber', 'servis')
            ->whereBetween('t.created_at', [$startUtc, $endUtc])
            ->select([
                't.id as id',
                DB::raw("'servis' as jenis"),
                't.no_transaksi as no_referensi',
                't.created_at as waktu',
                DB::raw("COALESCE(p.nama, 'Pelanggan Umum') as pihak"),
                DB::raw("COALESCE(u.name, 'Kasir') as operator"),
                't.metode_bayar as metode',
                't.total_akhir as nominal',
                DB::raw("'in' as arah_kas"),
                't.status as status',
                DB::raw("COALESCE(s.jenis_hp, 'Pelunasan Servis') as deskripsi"),
                DB::raw('COALESCE(s.id, t.id) as target_id'),
            ]);

        if ($metode) {
            $qServisTrx->where('t.metode_bayar', $metode);
        }

        // 3b. Riwayat retur penjualan. `tanggal` adalah tanggal bisnis retur,
        // bukan waktu pembuatan draft, sehingga filter tanggal tetap konsisten.
        $qRetur = DB::table('return_penjualan as r')
            ->leftJoin('users as u', 'r.user_id', '=', 'u.id')
            ->leftJoin('pelanggan as p', 'r.pelanggan_id', '=', 'p.id')
            ->leftJoin('transaksi as t', 'r.transaksi_id', '=', 't.id')
            ->where('r.cabang_id', $cabangId)
            ->whereDate('r.tanggal', '>=', $this->localDate($startUtc))
            ->whereDate('r.tanggal', '<=', $this->localDate($endUtc))
            ->select([
                'r.id as id',
                DB::raw("'retur' as jenis"),
                'r.no_return as no_referensi',
                DB::raw('COALESCE(r.updated_at, r.created_at) as waktu'),
                DB::raw("COALESCE(p.nama, 'Pelanggan Umum') as pihak"),
                DB::raw("COALESCE(u.name, 'Kasir') as operator"),
                DB::raw("COALESCE(r.metode_pengembalian, 'retur') as metode"),
                'r.jumlah as nominal',
                DB::raw("'keluar' as arah_kas"),
                'r.status as status',
                DB::raw("COALESCE(r.alasan, CONCAT('Retur ', COALESCE(t.no_transaksi, '-'))) as deskripsi"),
                'r.id as target_id',
            ]);

        // 3. Servis Direct (TiketServis lunas tanpa transaksi_id)
        $qServisDirect = null;
        if (Schema::hasTable('tiket_servis')) {
            $qServisDirect = DB::table('tiket_servis as s')
                ->leftJoin('users as u', 's.teknisi_id', '=', 'u.id')
                ->leftJoin('pelanggan as p', 's.pelanggan_id', '=', 'p.id')
                ->where('s.cabang_id', $cabangId)
                ->whereNull('s.transaksi_id')
                ->where('s.status_pembayaran', 'lunas')
                ->where(function ($q) use ($startUtc, $endUtc) {
                    $q->whereBetween('s.tanggal_bayar', [$startUtc, $endUtc])
                        ->orWhereBetween('s.tanggal_selesai', [$startUtc, $endUtc])
                        ->orWhereBetween('s.updated_at', [$startUtc, $endUtc]);
                })
                ->select([
                    's.id as id',
                    DB::raw("'servis' as jenis"),
                    's.no_tiket as no_referensi',
                    DB::raw('COALESCE(s.tanggal_bayar, s.tanggal_selesai, s.updated_at) as waktu'),
                    DB::raw("COALESCE(p.nama, s.nama_pelanggan, 'Pelanggan Umum') as pihak"),
                    DB::raw("COALESCE(u.name, 'Teknisi') as operator"),
                    DB::raw("COALESCE(s.metode_pembayaran, 'tunai') as metode"),
                    DB::raw('COALESCE(s.estimasi_biaya, 0) as nominal'),
                    DB::raw("'in' as arah_kas"),
                    's.status_pembayaran as status',
                    DB::raw("COALESCE(s.jenis_hp, 'Pelunasan Servis') as deskripsi"),
                    's.id as target_id',
                ]);

            if ($metode) {
                $qServisDirect->where('s.metode_pembayaran', $metode);
            }
        }

        // 4. Mutasi Kas Laci
        $qMutasi = null;
        if (Schema::hasTable('kas_mutasi_laci')) {
            $qMutasi = DB::table('kas_mutasi_laci as m')
                ->leftJoin('users as u', 'm.user_id', '=', 'u.id')
                ->where('m.cabang_id', $cabangId)
                ->whereBetween('m.created_at', [$startUtc, $endUtc])
                ->select([
                    'm.id as id',
                    DB::raw("CASE WHEN m.jenis = 'masuk' THEN 'mutasi_masuk' ELSE 'mutasi_keluar' END as jenis"),
                    DB::raw("COALESCE(m.no_jurnal, 'KAS-MUTASI') as no_referensi"),
                    'm.created_at as waktu',
                    DB::raw("COALESCE(m.akun_lawan_kode, 'Kas Laci') as pihak"),
                    DB::raw("COALESCE(u.name, 'Kasir') as operator"),
                    DB::raw("'Kas Laci' as metode"),
                    'm.nominal as nominal',
                    'm.jenis as arah_kas',
                    DB::raw("CASE WHEN m.jenis = 'masuk' THEN 'Kas Masuk' ELSE 'Kas Keluar' END as status"),
                    DB::raw("COALESCE(m.keterangan, 'Mutasi Kas Laci') as deskripsi"),
                    'm.id as target_id',
                ]);
        }

        // Susun queries berdasarkan filterStatus jenis
        $queries = [];
        if (! $status || $status === 'semua') {
            $queries = [$qPos, $qOnline, $qServisTrx, $qRetur];
            if ($qServisDirect) {
                $queries[] = $qServisDirect;
            }
            if ($qMutasi && ! $metode) {
                $queries[] = $qMutasi;
            }
        } elseif ($status === 'pos') {
            $queries = [$qPos];
        } elseif ($status === 'online') {
            $queries = [$qOnline];
        } elseif ($status === 'servis') {
            $queries = [$qServisTrx];
            if ($qServisDirect) {
                $queries[] = $qServisDirect;
            }
        } elseif ($status === 'retur') {
            $queries = [$qRetur];
        } elseif ($status === 'void') {
            $qPos->where('t.status', 'void');
            $qOnline->where('t.status', 'void');
            $qServisTrx->where('t.status', 'void');
            $queries = [$qPos, $qOnline, $qServisTrx];
        } elseif ($status === 'mutasi_masuk' && $qMutasi) {
            $qMutasi->where('m.jenis', 'masuk');
            $queries = [$qMutasi];
        } elseif ($status === 'mutasi_keluar' && $qMutasi) {
            $qMutasi->where('m.jenis', 'keluar');
            $queries = [$qMutasi];
        }

        if (empty($queries)) {
            return null;
        }

        $first = array_shift($queries);
        foreach ($queries as $sub) {
            $first->unionAll($sub);
        }

        $base = DB::query()->fromSub($first, 'u');

        if ($search) {
            $base->where(function ($q) use ($search) {
                $q->where('no_referensi', 'like', "%{$search}%")
                    ->orWhere('pihak', 'like', "%{$search}%")
                    ->orWhere('operator', 'like', "%{$search}%")
                    ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        return $base->orderBy('waktu', 'desc')->paginate(15);
    }

    protected function localDate(Carbon $utc): string
    {
        return $utc->copy()->setTimezone(config('app.timezone', 'Asia/Jakarta'))->toDateString();
    }

    protected function getPosData(int $cabangId)
    {
        [$startUtc, $endUtc] = $this->getDateBoundaries();

        $query = Transaksi::with(['kasir', 'pelanggan', 'returnPenjualan', 'piutang'])
            ->where('cabang_id', $cabangId)
            ->where(function ($q) {
                $q->whereNull('sumber')->orWhere('sumber', 'pos');
            })
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->when($this->filterMetode, fn ($q) => $q->where('metode_bayar', $this->filterMetode))
            ->when($this->filterStatus, function ($q) {
                if ($this->filterStatus === 'retur') {
                    $q->has('returnPenjualan');
                } else {
                    $q->where('status', $this->filterStatus);
                }
            })
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('no_transaksi', 'like', "%{$this->search}%")
                        ->orWhereHas('pelanggan', fn ($p) => $p->where('nama', 'like', "%{$this->search}%"))
                        ->orWhereHas('kasir', fn ($k) => $k->where('name', 'like', "%{$this->search}%"));
                });
            })
            ->latest('id');

        return $query->paginate(15);
    }

    protected function getOnlineData(int $cabangId)
    {
        [$startUtc, $endUtc] = $this->getDateBoundaries();

        $query = Transaksi::with(['kasir', 'pelanggan', 'pengiriman', 'channelOrder.channel'])
            ->where('cabang_id', $cabangId)
            ->where(function ($q) {
                $q->where('sumber', 'marketplace')
                    ->orWhere('sumber', 'like', 'channel:%');
            })
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->when($this->filterPlatform, function ($q) {
                if ($this->filterPlatform === 'marketplace') {
                    $q->where('sumber', 'marketplace');
                } else {
                    $platform = $this->filterPlatform;
                    $q->where(function ($sub) use ($platform) {
                        $sub->where('sumber', 'channel:'.$platform)
                            ->orWhereHas('channelOrder.channel', fn ($ch) => $ch->where('platform', $platform));
                    });
                }
            })
            ->when($this->filterMetodePengiriman, function ($q) {
                if ($this->filterMetodePengiriman === 'pickup') {
                    $q->where('catatan', 'like', '%ambil_ke_toko%');
                } elseif ($this->filterMetodePengiriman === 'kurir') {
                    $q->where(function ($sub) {
                        $sub->whereNull('catatan')->orWhere('catatan', 'not like', '%ambil_ke_toko%');
                    });
                }
            })
            ->when($this->filterMetode, fn ($q) => $q->where('metode_bayar', $this->filterMetode))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('no_transaksi', 'like', "%{$this->search}%")
                        ->orWhere('sumber', 'like', "%{$this->search}%")
                        ->orWhereHas('pelanggan', fn ($p) => $p->where('nama', 'like', "%{$this->search}%"))
                        ->orWhereHas('pengiriman', fn ($pr) => $pr->where('tracking_id', 'like', "%{$this->search}%"));
                });
            })
            ->latest('id');

        return $query->paginate(15);
    }

    protected function getServisData(int $cabangId)
    {
        if (! Schema::hasTable('tiket_servis')) {
            return null;
        }

        [$startUtc, $endUtc] = $this->getDateBoundaries();

        $query = TiketServis::with(['pelanggan', 'teknisi', 'transaksi.kasir', 'items', 'spareparts.produk'])
            ->where('cabang_id', $cabangId);

        // Filter status servis
        if ($this->filterStatus === '' || empty($this->filterStatus)) {
            // Default: Rekap Transaksi Servis Selesai & Lunas
            $query->where(function ($q) {
                $q->whereIn('status', ['selesai', 'diambil'])
                    ->orWhere('status_pembayaran', 'lunas');
            });
        } elseif ($this->filterStatus === 'selesai') {
            $query->where('status', 'selesai');
        } elseif ($this->filterStatus === 'diambil') {
            $query->where('status', 'diambil');
        } elseif ($this->filterStatus === 'lunas') {
            $query->where('status_pembayaran', 'lunas');
        } elseif ($this->filterStatus === 'belum_lunas') {
            $query->whereIn('status', ['selesai', 'diambil'])->where('status_pembayaran', '!=', 'lunas');
        }
        // Jika filterStatus === 'semua', tidak memfilter status pengerjaan tiket

        $query->where(function ($q) use ($startUtc, $endUtc) {
            $q->whereBetween('tanggal_selesai', [$startUtc, $endUtc])
                ->orWhereBetween('tanggal_bayar', [$startUtc, $endUtc])
                ->orWhereBetween('created_at', [$startUtc, $endUtc]);
        });

        if ($this->filterMetode) {
            $query->where(function ($q) {
                $q->where('metode_pembayaran', $this->filterMetode)
                    ->orWhereHas('transaksi', fn ($t) => $t->where('metode_bayar', $this->filterMetode));
            });
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('no_tiket', 'like', "%{$this->search}%")
                    ->orWhere('jenis_hp', 'like', "%{$this->search}%")
                    ->orWhere('seri_hp', 'like', "%{$this->search}%")
                    ->orWhereHas('pelanggan', fn ($p) => $p->where('nama', 'like', "%{$this->search}%"))
                    ->orWhereHas('teknisi', fn ($t) => $t->where('name', 'like', "%{$this->search}%"))
                    ->orWhereHas('transaksi', fn ($tr) => $tr->where('no_transaksi', 'like', "%{$this->search}%"));
            });
        }

        return $query->orderByRaw('COALESCE(tanggal_bayar, tanggal_selesai, created_at) DESC')->paginate(15);
    }

    protected function getMutasiData(int $cabangId)
    {
        if (! Schema::hasTable('kas_mutasi_laci')) {
            return null;
        }

        [$startUtc, $endUtc] = $this->getDateBoundaries();

        $query = DB::table('kas_mutasi_laci')
            ->join('users', 'kas_mutasi_laci.user_id', '=', 'users.id')
            ->leftJoin('akun_coa', 'kas_mutasi_laci.akun_lawan_kode', '=', 'akun_coa.kode')
            ->select(
                'kas_mutasi_laci.*',
                'users.name as user_name',
                'akun_coa.nama as akun_nama'
            )
            ->where('kas_mutasi_laci.cabang_id', $cabangId)
            ->whereBetween('kas_mutasi_laci.created_at', [$startUtc, $endUtc])
            ->when($this->filterStatus, fn ($q) => $q->where('kas_mutasi_laci.jenis', $this->filterStatus))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('kas_mutasi_laci.keterangan', 'like', "%{$this->search}%")
                        ->orWhere('kas_mutasi_laci.no_jurnal', 'like', "%{$this->search}%")
                        ->orWhere('kas_mutasi_laci.akun_lawan_kode', 'like', "%{$this->search}%")
                        ->orWhere('users.name', 'like', "%{$this->search}%");
                });
            })
            ->latest('kas_mutasi_laci.id');

        return $query->paginate(15);
    }

    public function sinkronkanStatusOnline(int $id): void
    {
        $cabangId = $this->getActiveCabangId();
        $transaksi = Transaksi::with(['channelOrder.channel', 'pelanggan', 'items'])
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->find($id);

        if (! $transaksi) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Pesanan online tidak ditemukan']);

            return;
        }

        try {
            if ($transaksi->sumber === 'marketplace') {
                $duitku = app(DuitkuService::class);
                $res = $duitku->transactionStatus($transaksi->no_transaksi);
                $statusDuitku = $res['status'] ?? 'unknown';

                if ($statusDuitku === 'sukses') {
                    if (! in_array($transaksi->status, ['lunas', 'diproses', 'dikirim', 'siap_diambil', 'selesai'], true)) {
                        $processor = app(DuitkuPaymentProcessor::class);
                        $processor->prosesLunas($transaksi->no_transaksi, (int) round((float) $transaksi->total_akhir), $res['reference'] ?? null);
                        $this->dispatch('alert', ['type' => 'success', 'message' => "Pesanan {$transaksi->no_transaksi} berhasil disinkronkan: LUNAS"]);
                    } else {
                        $this->dispatch('alert', ['type' => 'info', 'message' => "Pesanan {$transaksi->no_transaksi} status Duitku: Sukses (Status ERP: {$transaksi->status})"]);
                    }
                } elseif ($statusDuitku === 'batal') {
                    if (! in_array($transaksi->status, ['batal', 'dibatalkan', 'kadaluarsa', 'gagal', 'selesai', 'lunas'], true)) {
                        $processor = app(DuitkuPaymentProcessor::class);
                        $processor->prosesGagal($transaksi->no_transaksi, $res['status_code'] ?? '02');
                        $this->dispatch('alert', ['type' => 'warning', 'message' => "Pesanan {$transaksi->no_transaksi} disinkronkan: BATAL di Duitku"]);
                    } else {
                        $this->dispatch('alert', ['type' => 'info', 'message' => "Pesanan {$transaksi->no_transaksi} status Duitku: BATAL"]);
                    }
                } elseif ($statusDuitku === 'pending') {
                    $this->dispatch('alert', ['type' => 'info', 'message' => "Pesanan {$transaksi->no_transaksi} masih menunggu pembayaran di Duitku"]);
                } else {
                    $this->dispatch('alert', ['type' => 'warning', 'message' => "Respon Duitku untuk {$transaksi->no_transaksi}: {$statusDuitku}"]);
                }
            } elseif ($transaksi->channelOrder) {
                $channel = $transaksi->channelOrder->channel;
                if ($channel) {
                    $channelSync = app(ChannelSyncService::class);
                    $adapter = $channelSync->adapterFor($channel->platform);
                    if ($adapter) {
                        $statusOnline = $adapter->getOrderStatus($channel, $transaksi->channelOrder->channel_order_id);
                        if (! empty($statusOnline)) {
                            $mappedStatus = match (strtolower((string) $statusOnline)) {
                                'unpaid', 'pending' => 'menunggu_pembayaran',
                                'ready_to_ship', 'processed', 'processing' => 'diproses',
                                'shipped' => 'dikirim',
                                'completed', 'delivered' => 'selesai',
                                'cancelled', 'canceled' => 'batal',
                                default => $transaksi->status,
                            };
                            if ($mappedStatus !== $transaksi->status && ! in_array($transaksi->status, ['selesai', 'void'], true)) {
                                $transaksi->update(['status' => $mappedStatus]);
                                $transaksi->channelOrder->update(['status_order' => $mappedStatus]);
                                $this->dispatch('alert', ['type' => 'success', 'message' => "Status pesanan channel {$channel->nama} disinkronkan: {$mappedStatus}"]);
                            } else {
                                $this->dispatch('alert', ['type' => 'info', 'message' => "Status pesanan channel {$channel->nama}: {$statusOnline} (mutakhir)"]);
                            }
                        } else {
                            $this->dispatch('alert', ['type' => 'info', 'message' => 'Status channel terkini tidak berubah']);
                        }
                    } else {
                        $this->dispatch('alert', ['type' => 'info', 'message' => "Adapter channel {$channel->platform} tidak aktif"]);
                    }
                }
            } else {
                $this->dispatch('alert', ['type' => 'info', 'message' => "Transaksi {$transaksi->no_transaksi} tidak terhubung gateway online"]);
            }

            if ($this->selectedDetail && ($this->selectedDetail['id'] ?? null) === $id) {
                $fresh = Transaksi::find($id);
                if ($fresh) {
                    $this->selectedDetail['status'] = $fresh->status;
                }
            }
        } catch (\Throwable $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Gagal sinkronisasi: '.$e->getMessage()]);
        }
    }

    public function sinkronkanSemuaOnline(): void
    {
        $cabangId = $this->getActiveCabangId();
        [$startUtc, $endUtc] = $this->getDateBoundaries();

        $pendingOrders = Transaksi::where('cabang_id', $cabangId)
            ->where(function ($q) {
                $q->where('sumber', 'marketplace')
                    ->orWhere('sumber', 'like', 'channel:%');
            })
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->whereIn('status', ['menunggu_pembayaran', 'diproses'])
            ->limit(20)
            ->get();

        if ($pendingOrders->isEmpty()) {
            $this->dispatch('alert', ['type' => 'info', 'message' => 'Semua pesanan online pada periode ini sudah termutakhirkan atau berstatus final.']);

            return;
        }

        $synced = 0;
        foreach ($pendingOrders as $order) {
            $this->sinkronkanStatusOnline($order->id);
            $synced++;
        }

        $this->dispatch('alert', ['type' => 'success', 'message' => "{$synced} pesanan online telah diproses sinkronisasi status."]);
    }

    public function serahkanPesananPickup(int $id): void
    {
        $cabangId = $this->getActiveCabangId();
        $transaksi = Transaksi::with(['items', 'pelanggan'])
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->find($id);

        if (! $transaksi) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Pesanan tidak ditemukan']);

            return;
        }

        try {
            $onlineService = app(PembayaranPesananOnlineService::class);
            $onlineService->serahkanDiToko($transaksi);

            if ($this->selectedDetail && ($this->selectedDetail['id'] ?? null) === $id) {
                $this->selectedDetail['status'] = 'selesai';
            }

            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Pesanan {$transaksi->no_transaksi} berhasil diserahkan ke konsumen di toko.",
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
