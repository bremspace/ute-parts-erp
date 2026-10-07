<?php

namespace App\Modules\Pos\Livewire;

use App\Modules\Pos\Models\Transaksi;
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

    public string $activeTab = 'global'; // 'global' | 'pos' | 'servis' | 'mutasi_kas'

    public string $search = '';

    public string $filterTanggal = '';

    public string $filterMetode = '';

    public string $filterStatus = '';

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
        'filterTanggal' => ['except' => ''],
        'filterMetode' => ['except' => ''],
        'filterStatus' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->activeTab = request()->query('tab', request()->query('activeTab', 'global'));
        $this->filterTanggal = request()->query('filterTanggal', now()->toDateString());
    }

    public function updatedActiveTab(): void
    {
        $this->resetPage();
        $this->resetValidation();
        $this->filterStatus = '';
        $this->filterMetode = '';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterTanggal(): void
    {
        $this->resetPage();
    }

    public function resetFilter(): void
    {
        $this->reset(['search', 'filterMetode', 'filterStatus']);
        $this->filterTanggal = now()->toDateString();
        $this->resetPage();
    }

    public function lihatDetailTransaksi(int $id): void
    {
        $cabangId = session('cabang_id');
        $trx = Transaksi::with(['items.produk', 'pelanggan', 'kasir', 'tiketServis'])
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->findOrFail($id);

        $this->selectedDetail = [
            'tipe' => 'pos',
            'id' => $trx->id,
            'no_transaksi' => $trx->no_transaksi,
            'tanggal' => $trx->created_at?->format('d/m/Y H:i:s'),
            'kasir' => $trx->kasir?->name ?? 'Kasir',
            'pelanggan' => $trx->pelanggan?->nama ?? 'Pelanggan Umum',
            'metode_bayar' => $trx->metode_bayar,
            'subtotal' => (float) $trx->subtotal,
            'diskon' => (float) $trx->diskon_nominal,
            'pajak' => (float) $trx->pajak_nominal,
            'total' => (float) $trx->total_akhir,
            'bayar' => (float) $trx->jumlah_bayar,
            'kembalian' => (float) $trx->kembalian,
            'status' => $trx->status,
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
        ];

        $this->showDetailModal = true;
    }

    public function lihatDetailServis(int $id): void
    {
        $cabangId = session('cabang_id');
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
        $cabangId = session('cabang_id');
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

    public function cetakUlangStruk(string $tipe, int $id): void
    {
        $cabangId = session('cabang_id');

        if ($tipe === 'pos') {
            $trx = Transaksi::with(['items.produk', 'pelanggan', 'kasir', 'cabang'])
                ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
                ->find($id);

            if (! $trx) {
                return;
            }

            $this->completedTransactionId = $trx->id;
            $this->receiptData = [
                'no_transaksi' => $trx->no_transaksi,
                'waktu' => $trx->created_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'),
                'kasir' => $trx->kasir?->name ?? 'Kasir',
                'pelanggan' => $trx->pelanggan?->nama ?? 'Pelanggan Umum',
                'telepon' => $trx->pelanggan?->telepon ?? null,
                'items' => $trx->items->map(fn ($it) => [
                    'nama' => $it->produk?->nama ?? 'Produk',
                    'qty' => (int) $it->jumlah,
                    'harga' => (float) $it->harga_satuan,
                    'subtotal' => (float) $it->subtotal,
                ])->toArray(),
                'subtotal' => (float) $trx->subtotal,
                'diskon' => (float) $trx->diskon_nominal,
                'pajak' => (float) $trx->pajak_nominal,
                'total' => (float) $trx->total_akhir,
                'bayar' => (float) $trx->jumlah_bayar,
                'kembalian' => (float) $trx->kembalian,
                'metode' => strtoupper($trx->metode_bayar),
                'cabang' => $trx->cabang?->nama ?? 'Ute Parts Store',
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
        $cabangId = session('cabang_id');

        // Ringkasan metrik hari ini / tanggal terpilih
        $ringkasan = [
            'total_penjualan_pos' => 0.0,
            'count_penjualan_pos' => 0,
            'total_mutasi_masuk' => 0.0,
            'total_mutasi_keluar' => 0.0,
            'total_servis_selesai' => 0.0,
            'count_servis_selesai' => 0,
            'total_arus_masuk' => 0.0,
            'total_kas_bersih' => 0.0,
        ];

        if ($cabangId) {
            $tgl = $this->filterTanggal ?: now()->toDateString();

            // POS penjualan
            $ringkasan['total_penjualan_pos'] = (float) Transaksi::where('cabang_id', $cabangId)
                ->where(function ($q) {
                    $q->whereNull('sumber')->orWhere('sumber', 'pos');
                })
                ->whereDate('created_at', $tgl)
                ->where('status', 'selesai')
                ->sum('total_akhir');

            $ringkasan['count_penjualan_pos'] = Transaksi::where('cabang_id', $cabangId)
                ->where(function ($q) {
                    $q->whereNull('sumber')->orWhere('sumber', 'pos');
                })
                ->whereDate('created_at', $tgl)
                ->where('status', 'selesai')
                ->count();

            // Mutasi kas laci
            if (Schema::hasTable('kas_mutasi_laci')) {
                $ringkasan['total_mutasi_masuk'] = (float) DB::table('kas_mutasi_laci')
                    ->where('cabang_id', $cabangId)
                    ->whereDate('created_at', $tgl)
                    ->where('jenis', 'masuk')
                    ->sum('nominal');

                $ringkasan['total_mutasi_keluar'] = (float) DB::table('kas_mutasi_laci')
                    ->where('cabang_id', $cabangId)
                    ->whereDate('created_at', $tgl)
                    ->where('jenis', 'keluar')
                    ->sum('nominal');
            }

            // Servis selesai & lunas (transaksi pos servis + tiket servis lunas langsung)
            if (Schema::hasTable('tiket_servis')) {
                $servisTrxSum = (float) Transaksi::where('cabang_id', $cabangId)
                    ->where('sumber', 'servis')
                    ->whereDate('created_at', $tgl)
                    ->where('status', 'selesai')
                    ->sum('total_akhir');

                $servisTrxCount = Transaksi::where('cabang_id', $cabangId)
                    ->where('sumber', 'servis')
                    ->whereDate('created_at', $tgl)
                    ->where('status', 'selesai')
                    ->count();

                $servisDirectSum = (float) TiketServis::where('cabang_id', $cabangId)
                    ->whereNull('transaksi_id')
                    ->where('status_pembayaran', 'lunas')
                    ->where(function ($q) use ($tgl) {
                        $q->whereDate('tanggal_bayar', $tgl)
                            ->orWhereDate('tanggal_selesai', $tgl);
                    })
                    ->sum('estimasi_biaya');

                $servisDirectCount = TiketServis::where('cabang_id', $cabangId)
                    ->whereNull('transaksi_id')
                    ->where('status_pembayaran', 'lunas')
                    ->where(function ($q) use ($tgl) {
                        $q->whereDate('tanggal_bayar', $tgl)
                            ->orWhereDate('tanggal_selesai', $tgl);
                    })
                    ->count();

                $ringkasan['total_servis_selesai'] = $servisTrxSum + $servisDirectSum;
                $ringkasan['count_servis_selesai'] = $servisTrxCount + $servisDirectCount;
            }

            // Total arus masuk & kas bersih
            $ringkasan['total_arus_masuk'] = $ringkasan['total_penjualan_pos'] + $ringkasan['total_servis_selesai'] + $ringkasan['total_mutasi_masuk'];
            $ringkasan['total_kas_bersih'] = $ringkasan['total_arus_masuk'] - $ringkasan['total_mutasi_keluar'];
        }

        // Query per Tab
        $dataList = null;
        if ($this->activeTab === 'global') {
            $dataList = $cabangId ? $this->getGlobalData($cabangId) : null;
        } elseif ($this->activeTab === 'pos') {
            $dataList = $cabangId ? $this->getPosData($cabangId) : null;
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
        $tanggal = $this->filterTanggal;
        $metode = $this->filterMetode;
        $status = $this->filterStatus; // 'semua' | 'pos' | 'servis' | 'mutasi_masuk' | 'mutasi_keluar'
        $search = trim($this->search);

        // 1. Transaksi POS Reguler
        $qPos = DB::table('transaksi as t')
            ->leftJoin('users as u', 't.kasir_id', '=', 'u.id')
            ->leftJoin('pelanggan as p', 't.pelanggan_id', '=', 'p.id')
            ->where('t.cabang_id', $cabangId)
            ->where(function ($q) {
                $q->whereNull('t.sumber')->orWhere('t.sumber', 'pos');
            })
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

        if ($tanggal) {
            $qPos->whereDate('t.created_at', $tanggal);
        }
        if ($metode) {
            $qPos->where('t.metode_bayar', $metode);
        }

        // 2. Transaksi Servis (Pelunasan via POS)
        $qServisTrx = DB::table('transaksi as t')
            ->leftJoin('users as u', 't.kasir_id', '=', 'u.id')
            ->leftJoin('pelanggan as p', 't.pelanggan_id', '=', 'p.id')
            ->leftJoin('tiket_servis as s', 't.tiket_servis_id', '=', 's.id')
            ->where('t.cabang_id', $cabangId)
            ->where('t.sumber', 'servis')
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

        if ($tanggal) {
            $qServisTrx->whereDate('t.created_at', $tanggal);
        }
        if ($metode) {
            $qServisTrx->where('t.metode_bayar', $metode);
        }

        // 3. Servis Direct (TiketServis lunas tanpa transaksi_id)
        $qServisDirect = null;
        if (Schema::hasTable('tiket_servis')) {
            $qServisDirect = DB::table('tiket_servis as s')
                ->leftJoin('users as u', 's.teknisi_id', '=', 'u.id')
                ->leftJoin('pelanggan as p', 's.pelanggan_id', '=', 'p.id')
                ->where('s.cabang_id', $cabangId)
                ->whereNull('s.transaksi_id')
                ->where('s.status_pembayaran', 'lunas')
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

            if ($tanggal) {
                $qServisDirect->where(function ($q) use ($tanggal) {
                    $q->whereDate('s.tanggal_bayar', $tanggal)
                        ->orWhereDate('s.tanggal_selesai', $tanggal)
                        ->orWhereDate('s.updated_at', $tanggal);
                });
            }
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

            if ($tanggal) {
                $qMutasi->whereDate('m.created_at', $tanggal);
            }
        }

        // Susun queries berdasarkan filterStatus jenis
        $queries = [];
        if (! $status || $status === 'semua') {
            $queries = [$qPos, $qServisTrx];
            if ($qServisDirect) {
                $queries[] = $qServisDirect;
            }
            if ($qMutasi && ! $metode) {
                $queries[] = $qMutasi;
            }
        } elseif ($status === 'pos') {
            $queries = [$qPos];
        } elseif ($status === 'servis') {
            $queries = [$qServisTrx];
            if ($qServisDirect) {
                $queries[] = $qServisDirect;
            }
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

    protected function getPosData(int $cabangId)
    {
        $query = Transaksi::with(['kasir', 'pelanggan'])
            ->where('cabang_id', $cabangId)
            ->where(function ($q) {
                $q->whereNull('sumber')->orWhere('sumber', 'pos');
            })
            ->when($this->filterTanggal, fn ($q) => $q->whereDate('created_at', $this->filterTanggal))
            ->when($this->filterMetode, fn ($q) => $q->where('metode_bayar', $this->filterMetode))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
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

    protected function getServisData(int $cabangId)
    {
        if (! Schema::hasTable('tiket_servis')) {
            return null;
        }

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

        if ($this->filterTanggal) {
            $query->where(function ($q) {
                $q->whereDate('tanggal_selesai', $this->filterTanggal)
                    ->orWhereDate('tanggal_bayar', $this->filterTanggal)
                    ->orWhereDate('created_at', $this->filterTanggal);
            });
        }

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

        $query = DB::table('kas_mutasi_laci')
            ->join('users', 'kas_mutasi_laci.user_id', '=', 'users.id')
            ->leftJoin('akun_coa', 'kas_mutasi_laci.akun_lawan_kode', '=', 'akun_coa.kode')
            ->select(
                'kas_mutasi_laci.*',
                'users.name as user_name',
                'akun_coa.nama as akun_nama'
            )
            ->where('kas_mutasi_laci.cabang_id', $cabangId)
            ->when($this->filterTanggal, fn ($q) => $q->whereDate('kas_mutasi_laci.created_at', $this->filterTanggal))
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
}
