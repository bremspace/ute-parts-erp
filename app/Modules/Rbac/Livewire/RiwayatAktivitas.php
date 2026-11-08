<?php

namespace App\Modules\Rbac\Livewire;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Hr\Models\AbsensiLog;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\KaryawanKomponenGaji;
use App\Modules\Hr\Models\KomisiTeknisiRule;
use App\Modules\Hr\Models\KpiHasil;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Hr\Models\PayrollSlip;
use App\Modules\Hr\Models\Shift;
use App\Modules\Hr\Models\ShiftJadwal;
use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Exports\AuditLogExport;
use App\Modules\Rbac\Models\AktivitasLog;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Services\AktivitasCabang;
use App\Modules\Servis\Models\JenisServis;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Grn;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\ReturnPembelian;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokOpname;
use App\Modules\Wms\Models\StokTransfer;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Models\TipeHp;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\Paginator;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * [F1-4] Tab riwayat audit trail per entitas + filter (user, aksi, rentang tanggal).
 *
 * RBAC: permission `lihat-audit-log` (super-admin + finance + admin-toko).
 * Cabang-aware: non-super-admin hanya melihat log entitas milik cabang aktifnya
 * (cek cabang subject) — mode daftar tanpa entity_id juga difilter cabang_id.
 *
 * Catatan repo: computed props (izin/akses/aktivitas) WAJIB dilewat eksplisit di render().
 */
class RiwayatAktivitas extends Component
{
    /** tipe pendek → model kritis yang di-log [F1-4 / §5.4] */
    public const TIPE = [
        'transaksi' => Transaksi::class,
        'servis' => TiketServis::class,
        'jurnal' => JurnalAkuntansi::class,
        'stok' => StokItem::class,
        'po' => PurchaseOrder::class,
        'grn' => Grn::class,
        'piutang' => Piutang::class,
        'utang' => Utang::class,
        'produk' => Produk::class,
        'pelanggan' => Pelanggan::class,
        'supplier' => Supplier::class,
        'opname' => StokOpname::class,
        'transfer' => StokTransfer::class,
        'retur_penjualan' => ReturnPenjualan::class,
        'return_penjualan' => ReturnPenjualan::class,
        'retur_pembelian' => ReturnPembelian::class,
        'return_pembelian' => ReturnPembelian::class,
        'user' => User::class,
        'cabang' => Cabang::class,
        // Master data katalog — CRUD-nya sudah dilog `CatatAktivitas`, tapi tanpa
        // key di sini owner/super-admin tidak bisa menyaring atau membuka log-nya.
        'kategori' => KategoriProduk::class,
        'brand' => Brand::class,
        'kualitas' => KualitasProduk::class,
        'satuan' => SatuanUnit::class,
        'tipe_hp' => TipeHp::class,
        'gudang' => Gudang::class,
        'rak' => Rak::class,
        'jenis_servis' => JenisServis::class,
        'coa' => AkunCOA::class,
        // Modul HR — log-nya sudah ditulis model, tanpa key di sini owner
        // tidak bisa menyaring/membuka per entitas (tidak terlihat sama sekali).
        'karyawan' => Karyawan::class,
        'payroll_periode' => PayrollPeriode::class,
        'payroll_slip' => PayrollSlip::class,
        'komponen_gaji' => KaryawanKomponenGaji::class,
        'absensi' => AbsensiLog::class,
        'kpi_hasil' => KpiHasil::class,
        'shift_jadwal' => ShiftJadwal::class,
        'komisi_teknisi' => KomisiTeknisiRule::class,
    ];

    public string $tipe = '';

    public ?int $entityId = null;

    // ===== Filter =====
    public string $filterCari = '';

    public string $filterUser = '';

    /** '' | created | updated | deleted | penjualan | void | retur_penjualan | dll */
    public string $filterAksi = '';

    /** Filter khusus kategori alur transaksi */
    public string $filterKategoriTransaksi = '';

    /** Filter pencarian produk / SKU di histori transaksi */
    public string $filterProduk = '';

    public string $filterDari = '';

    public string $filterSampai = '';

    public int $halaman = 1;

    public const PER_HALAMAN = 10;

    public function updating(string $property): void
    {
        if (str_starts_with($property, 'filter') || $property === 'tipe') {
            $this->halaman = 1;
        }
    }

    public function halamanBerikutnya(): void
    {
        $this->halaman++;
    }

    public function halamanSebelumnya(): void
    {
        $this->halaman = max(1, $this->halaman - 1);
    }

    public function resetFilter(): void
    {
        $this->filterCari = '';
        $this->filterUser = '';
        $this->filterAksi = '';
        $this->filterKategoriTransaksi = '';
        $this->filterProduk = '';
        $this->filterDari = '';
        $this->filterSampai = '';
        $this->halaman = 1;
    }

    // ===== Computed =====

    public function getIzinProperty(): bool
    {
        return (bool) auth()->user()?->can('lihat-audit-log');
    }

    public function getAksesProperty(): string
    {
        if (! $this->izin) {
            return 'tanpa-izin';
        }

        if ($this->tipe === '' && $this->entityId === null) {
            return 'ok';
        }

        $modelClass = self::TIPE[$this->tipe] ?? null;
        if (! $modelClass) {
            return 'tidak-dikenal';
        }

        if ($this->entityId !== null) {
            $model = $modelClass::find($this->entityId);
            if (! $model) {
                return 'tidak-dikenal';
            }

            $cabangEntity = AktivitasCabang::untuk($model);
            if ($cabangEntity !== null
                && ! $this->adalahSuperAdmin()
                && $cabangEntity !== (int) session('cabang_id')) {
                return 'cabang-lain';
            }
        }

        return 'ok';
    }

    public function getJudulProperty(): string
    {
        if ($this->akses !== 'ok') {
            return '';
        }

        if ($this->tipe === '' && $this->entityId === null) {
            return 'Seluruh Log Aktivitas Sistem';
        }

        $modelClass = self::TIPE[$this->tipe] ?? null;
        if (! $modelClass || $this->entityId === null) {
            return '';
        }

        $model = $modelClass::find($this->entityId);
        if (! $model) {
            return '';
        }

        return (string) match ($this->tipe) {
            'transaksi' => $model->no_transaksi,
            'servis' => $model->no_tiket.' · '.($model->jenis_hp ?? ''),
            'jurnal' => $model->no_jurnal,
            'piutang' => $model->no_piutang,
            'utang' => $model->no_utang,
            'po' => $model->no_po,
            'produk' => $model->nama,
            'pelanggan' => $model->nama.' · '.($model->telepon ?? ''),
            'supplier' => $model->nama,
            'opname' => $model->no_opname,
            'transfer' => $model->no_transfer,
            'retur_penjualan', 'return_penjualan' => $model->no_return ?? '#'.$model->id,
            'retur_pembelian', 'return_pembelian' => $model->no_return ?? '#'.$model->id,
            'user' => $model->name.' ('.$model->email.')',
            'cabang' => $model->nama.' ('.$model->kode.')',
            'kategori' => $model->nama,
            'brand' => $model->nama,
            'kualitas' => $model->nama,
            'satuan' => $model->kode.' · '.$model->nama,
            'tipe_hp' => ($model->nama ?: $model->model).' ('.$model->merk.')',
            'gudang' => $model->nama.' ('.$model->kode.')',
            'rak' => $model->nama.' ('.$model->kode.')',
            'jenis_servis' => $model->nama.' ('.$model->kode.')',
            'coa' => $model->kode.' · '.$model->nama,
            // HR banyak yang child record — judul WAJIB membawa konteks induknya,
            // karena "2026-10-01" atau "Periode 2026-01" saja tidak menjelaskan siapa.
            // Tanggal ditampilkan apa adanya (ISO): model HR tidak punya cast `date`,
            // jadi `->format()` akan meledak, dan ISO justru lebih mudah diurutkan.
            'karyawan' => $model->nama.' ('.$model->jabatan.')',
            'payroll_periode' => 'Periode '.$model->periode,
            'payroll_slip' => 'Slip gaji · '.$model->karyawan?->nama.' ('.$model->periode?->periode.')',
            'komponen_gaji' => $model->nama.' · '.$model->karyawan?->nama,
            'absensi' => $model->karyawan?->nama.' · '.$model->tanggal.' ('.$model->status.')',
            'kpi_hasil' => $model->karyawan?->nama.' · '.$model->periode,
            'shift_jadwal' => $model->karyawan?->nama.' · '.$model->tanggal.' ('.$model->shift?->nama.')',
            'komisi_teknisi' => $model->jenis.' · '.$model->jabatan_target,
            'stok' => ($model->produk?->nama ?? 'Produk').' · '.($model->gudang?->nama ?? 'Gudang'),
            default => '#'.$model->getKey(),
        };
    }

    public function getBaseQuery()
    {
        $query = AktivitasLog::query()->with(['causer', 'cabang'])->orderByDesc('id');

        // [Dual-Indexing] Jika melihat riwayat produk, sertakan transaksi yang menyangkut produk ini
        if ($this->tipe === 'produk' && $this->entityId !== null) {
            $produkId = (int) $this->entityId;
            $modelClass = self::TIPE['produk'] ?? Produk::class;
            $query->where(function ($q) use ($modelClass, $produkId) {
                $q->where(function ($direct) use ($modelClass, $produkId) {
                    $direct->where('subject_type', $modelClass)
                        ->where('subject_id', $produkId);
                })->orWhereJsonContains('properties->produk_ids', $produkId);
            });
        } else {
            if ($this->tipe !== '') {
                $modelClass = self::TIPE[$this->tipe] ?? null;
                if ($modelClass) {
                    $query->where('subject_type', $modelClass);
                }
            }

            if ($this->entityId !== null) {
                // Scope per entitas: cabang sudah diverifikasi di getAksesProperty()
                $query->where('subject_id', $this->entityId);
            } else {
                $cabangId = (int) session('cabang_id');
                if ($cabangId && ! $this->adalahSuperAdmin()) {
                    $query->where(fn ($q) => $q->where('cabang_id', $cabangId)->orWhereNull('cabang_id'));
                }
            }
        }

        // 1. Pencarian Bebas Terintegrasi (Cari No. Dokumen, Referensi, Produk, Pelanggan, Catatan, dsb)
        $filterCari = trim($this->filterCari);
        if ($filterCari !== '') {
            $query->where(function ($q) use ($filterCari) {
                $q->where('description', 'like', "%{$filterCari}%")
                    ->orWhere('event', 'like', "%{$filterCari}%")
                    ->orWhere('properties->no_referensi', 'like', "%{$filterCari}%")
                    ->orWhere('properties->no_transaksi', 'like', "%{$filterCari}%")
                    ->orWhere('properties->attributes->no_transaksi', 'like', "%{$filterCari}%")
                    ->orWhere('properties->no_tiket', 'like', "%{$filterCari}%")
                    ->orWhere('properties->attributes->no_tiket', 'like', "%{$filterCari}%")
                    ->orWhere('properties->no_po', 'like', "%{$filterCari}%")
                    ->orWhere('properties->attributes->no_po', 'like', "%{$filterCari}%")
                    ->orWhere('properties->no_grn', 'like', "%{$filterCari}%")
                    ->orWhere('properties->attributes->no_grn', 'like', "%{$filterCari}%")
                    ->orWhere('properties->no_opname', 'like', "%{$filterCari}%")
                    ->orWhere('properties->attributes->no_opname', 'like', "%{$filterCari}%")
                    ->orWhere('properties->no_transfer', 'like', "%{$filterCari}%")
                    ->orWhere('properties->attributes->no_transfer', 'like', "%{$filterCari}%")
                    ->orWhere('properties->no_return', 'like', "%{$filterCari}%")
                    ->orWhere('properties->attributes->no_return', 'like', "%{$filterCari}%")
                    ->orWhere('properties->ringkasan', 'like', "%{$filterCari}%");

                $q->orWhereHasMorph('causer', [User::class], function ($u) use ($filterCari) {
                    $u->where('name', 'like', "%{$filterCari}%")
                        ->orWhere('email', 'like', "%{$filterCari}%");
                });

                if (ctype_digit($filterCari)) {
                    $idNum = (int) $filterCari;
                    $q->orWhere('subject_id', $idNum)
                        ->orWhere('causer_id', $idNum)
                        ->orWhereJsonContains('properties->produk_ids', $idNum)
                        ->orWhere('properties->pelanggan_id', $idNum)
                        ->orWhere('properties->supplier_id', $idNum);
                }

                // Match Transaksi
                $matchedTrxIds = Transaksi::where('no_transaksi', 'like', "%{$filterCari}%")->pluck('id')->all();
                if (! empty($matchedTrxIds)) {
                    $q->orWhere(function ($tQ) use ($matchedTrxIds) {
                        $tQ->where('subject_type', Transaksi::class)->whereIn('subject_id', $matchedTrxIds);
                    });
                }

                // Match Tiket Servis
                $matchedTiketIds = TiketServis::where('no_tiket', 'like', "%{$filterCari}%")->pluck('id')->all();
                if (! empty($matchedTiketIds)) {
                    $q->orWhere(function ($sQ) use ($matchedTiketIds) {
                        $sQ->where('subject_type', TiketServis::class)->whereIn('subject_id', $matchedTiketIds);
                    });
                }

                // Match PO
                $matchedPoIds = PurchaseOrder::where('no_po', 'like', "%{$filterCari}%")->pluck('id')->all();
                if (! empty($matchedPoIds)) {
                    $q->orWhere(function ($poQ) use ($matchedPoIds) {
                        $poQ->where('subject_type', PurchaseOrder::class)->whereIn('subject_id', $matchedPoIds);
                    });
                }

                // Match GRN
                $matchedGrnIds = Grn::where('no_grn', 'like', "%{$filterCari}%")->pluck('id')->all();
                if (! empty($matchedGrnIds)) {
                    $q->orWhere(function ($gQ) use ($matchedGrnIds) {
                        $gQ->where('subject_type', Grn::class)->whereIn('subject_id', $matchedGrnIds);
                    });
                }

                // Match Transfer
                $matchedTrfIds = StokTransfer::where('no_transfer', 'like', "%{$filterCari}%")->pluck('id')->all();
                if (! empty($matchedTrfIds)) {
                    $q->orWhere(function ($trfQ) use ($matchedTrfIds) {
                        $trfQ->where('subject_type', StokTransfer::class)->whereIn('subject_id', $matchedTrfIds);
                    });
                }

                // Match Retur Penjualan
                $matchedReturIds = ReturnPenjualan::where('no_return', 'like', "%{$filterCari}%")->pluck('id')->all();
                if (! empty($matchedReturIds)) {
                    $q->orWhere(function ($rQ) use ($matchedReturIds) {
                        $rQ->where('subject_type', ReturnPenjualan::class)->whereIn('subject_id', $matchedReturIds);
                    });
                }

                $matchedProdukIds = Produk::where('nama', 'like', "%{$filterCari}%")
                    ->orWhere('barcode', 'like', "%{$filterCari}%")
                    ->pluck('id')->all();
                if (! empty($matchedProdukIds)) {
                    $q->orWhere(function ($pQ) use ($matchedProdukIds) {
                        $pQ->where('subject_type', Produk::class)->whereIn('subject_id', $matchedProdukIds);
                    });
                    foreach ($matchedProdukIds as $pId) {
                        $q->orWhereJsonContains('properties->produk_ids', (int) $pId);
                    }
                }

                $matchedPelangganIds = Pelanggan::where('nama', 'like', "%{$filterCari}%")
                    ->orWhere('telepon', 'like', "%{$filterCari}%")
                    ->pluck('id')->all();
                if (! empty($matchedPelangganIds)) {
                    $q->orWhere(function ($pQ) use ($matchedPelangganIds) {
                        $pQ->where('subject_type', Pelanggan::class)->whereIn('subject_id', $matchedPelangganIds);
                    });
                    foreach ($matchedPelangganIds as $pelId) {
                        $q->orWhere('properties->pelanggan_id', (int) $pelId);
                    }
                }

                $matchedSupplierIds = Supplier::where('nama', 'like', "%{$filterCari}%")
                    ->pluck('id')->all();
                if (! empty($matchedSupplierIds)) {
                    $q->orWhere(function ($sQ) use ($matchedSupplierIds) {
                        $sQ->where('subject_type', Supplier::class)->whereIn('subject_id', $matchedSupplierIds);
                    });
                    foreach ($matchedSupplierIds as $supId) {
                        $q->orWhere('properties->supplier_id', (int) $supId);
                    }
                }
            });
        }

        // 2. Filter Kategori Alur Transaksi
        if ($this->filterKategoriTransaksi !== '') {
            $kat = $this->filterKategoriTransaksi;
            $query->where(function ($q) use ($kat) {
                $q->where('event', $kat)
                    ->orWhere('properties->tipe_transaksi', $kat);
            });
        }

        // 3. Filter Spesifik Produk / SKU pada transaksi
        $filterProduk = trim($this->filterProduk);
        if ($filterProduk !== '') {
            $pIds = Produk::query()
                ->where('nama', 'like', "%{$filterProduk}%")
                ->orWhere('barcode', 'like', "%{$filterProduk}%")
                ->orWhereHas('skuVariants', fn ($q) => $q->where('sku', 'like', "%{$filterProduk}%"))
                ->pluck('id')
                ->all();

            if (ctype_digit($filterProduk)) {
                $pIds[] = (int) $filterProduk;
            }
            $pIds = array_values(array_unique($pIds));

            $query->where(function ($q) use ($filterProduk, $pIds) {
                if (! empty($pIds)) {
                    $q->where(function ($p) use ($pIds) {
                        $p->where('subject_type', Produk::class)->whereIn('subject_id', $pIds);
                    });
                    foreach ($pIds as $pId) {
                        $q->orWhereJsonContains('properties->produk_ids', (int) $pId);
                    }
                }
                $q->orWhere('description', 'like', "%{$filterProduk}%")
                    ->orWhere('properties', 'like', "%{$filterProduk}%");
            });
        }

        if ($this->filterAksi !== '') {
            $query->where('event', $this->filterAksi);
        }

        if ($this->filterDari !== '') {
            $query->whereDate('created_at', '>=', $this->filterDari);
        }

        if ($this->filterSampai !== '') {
            $query->whereDate('created_at', '<=', $this->filterSampai);
        }

        $filterUser = trim($this->filterUser);
        if ($filterUser !== '') {
            $query->where(function ($q) use ($filterUser) {
                $tipeUser = (new User)->getMorphClass();

                if (ctype_digit($filterUser)) {
                    $q->where('causer_type', $tipeUser)->where('causer_id', (int) $filterUser);

                    return;
                }

                $q->where('causer_type', $tipeUser)
                    ->whereIn('causer_id', User::where('name', 'like', "%{$filterUser}%")->pluck('id'));

                // Pencarian "sistem" = log tanpa causer (job queue / seed / webhook)
                if (stripos($filterUser, 'sistem') !== false) {
                    $q->orWhereNull('causer_id');
                }
            });
        }

        return $query;
    }

    public function getAktivitasProperty(): Paginator
    {
        if ($this->akses !== 'ok') {
            return new \Illuminate\Pagination\Paginator(collect(), self::PER_HALAMAN, $this->halaman);
        }

        return $this->getBaseQuery()->paginate(self::PER_HALAMAN, ['*'], 'page', $this->halaman);
    }

    /**
     * Ekspor data audit log ke file Excel (.xlsx) sesuai filter yang aktif
     */
    public function exportExcel(): BinaryFileResponse
    {
        if (! $this->izin || $this->akses !== 'ok') {
            abort(403, 'Anda tidak memiliki akses untuk mengekspor audit log.');
        }

        // Ambil data log sesuai filter yang sedang aktif, dibatasi 5.000 baris agar aman memori 1GB VPS
        $logs = $this->getBaseQuery()->limit(5000)->get();

        $namaFile = 'audit-log-'.($this->tipe ?: 'semua').'-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(new AuditLogExport($logs), $namaFile);
    }

    /**
     * Bypass scoping cabang utk audit trail. `owner` WAJIB ikut di sini: ia
     * pemilik usaha, maka aktivitas di cabang manapun pun harus terlihat —
     * kalau hanya super-admin, CRUD di cabang yang tidak lagi aktif di sesi
     * owner akan hilang dari pembukaannya (= CRUD tak bisa diaudit).
     */
    protected function adalahSuperAdmin(): bool
    {
        return isSuperAdminOrOwner(auth()->user());
    }

    public function render()
    {
        return view('modules.rbac.livewire.riwayat-aktivitas', [
            // computed props wajib eksplisit (gotcha repo — jangan akses bare di blade)
            'izin' => $this->izin,
            'akses' => $this->akses,
            'judul' => $this->judul,
            'aktivitas' => $this->aktivitas,
            'labelTipe' => $this->tipe ? strtoupper($this->tipe) : 'AUDIT TRAIL',
            'opsiAksi' => [
                'created' => 'Dibuat',
                'updated' => 'Diperbarui',
                'deleted' => 'Dihapus',
                'penjualan' => 'Penjualan Kasir',
                'void' => 'Void / Dibatalkan',
                'retur_penjualan' => 'Retur Penjualan',
                'po_dibuat' => 'PO Dibuat',
                'grn_diterima' => 'GRN Diterima',
                'retur_pembelian' => 'Retur Supplier',
                'transfer_stok' => 'Transfer Stok',
                'stok_opname' => 'Stok Opname',
                'servis' => 'Tiket Servis',
            ],
            'opsiKategoriTransaksi' => [
                '' => 'Semua Jenis Alur Transaksi',
                'penjualan' => 'Penjualan Kasir (POS)',
                'void' => 'Void / Pembatalan Penjualan',
                'retur_penjualan' => 'Retur Penjualan Konsumen',
                'po' => 'Purchase Order (PO)',
                'grn' => 'Penerimaan Barang (GRN)',
                'retur_pembelian' => 'Retur Barang ke Supplier',
                'transfer_stok' => 'Transfer Stok Antar Gudang',
                'stok_opname' => 'Stok Opname & Penyesuaian',
                'servis' => 'Tiket & Pengerjaan Servis',
            ],
            'opsiTipe' => [
                '' => 'Semua Modul / Entitas',
                'transaksi' => 'POS Transaksi',
                'servis' => 'Tiket Servis',
                'jurnal' => 'Jurnal Akuntansi',
                'stok' => 'Stok Gudang',
                'po' => 'Purchase Order (PO)',
                'grn' => 'Penerimaan Barang (GRN)',
                'piutang' => 'Piutang Usaha (AR)',
                'utang' => 'Utang Usaha (AP)',
                'produk' => 'Katalog Produk',
                'pelanggan' => 'Pelanggan CRM',
                'supplier' => 'Supplier / Vendor',
                'opname' => 'Stok Opname',
                'transfer' => 'Stok Transfer',
                'retur_penjualan' => 'Retur Penjualan',
                'retur_pembelian' => 'Retur Pembelian',
                'user' => 'Pengguna & Akun',
                'cabang' => 'Master Cabang',
                'kategori' => 'Master Kategori Produk',
                'brand' => 'Master Brand',
                'kualitas' => 'Master Tingkat Kualitas',
                'satuan' => 'Master Satuan Unit',
                'tipe_hp' => 'Master Tipe HP',
                'gudang' => 'Master Gudang',
                'rak' => 'Master Rak',
                'jenis_servis' => 'Master Jenis Servis',
                'coa' => 'Master Akun COA',
                // HR
                'karyawan' => 'Data Karyawan',
                'payroll_periode' => 'Periode Payroll',
                'payroll_slip' => 'Slip Gaji',
                'komponen_gaji' => 'Komponen Gaji Karyawan',
                'absensi' => 'Log Absensi',
                'kpi_hasil' => 'Hasil KPI',
                'shift_jadwal' => 'Jadwal Shift',
                'komisi_teknisi' => 'Aturan Komisi Teknisi',
            ],
        ])->layout('layouts.backoffice', ['header' => 'Audit Trail & Log']);
    }

    public static function labelEntitas(?string $subjectType): string
    {
        if (empty($subjectType)) {
            return 'Sistem';
        }

        $base = class_basename($subjectType);
        $map = [
            'Transaksi' => 'Transaksi Penjualan',
            'TiketServis' => 'Tiket Servis',
            'ReturnPenjualan' => 'Retur Penjualan',
            'ReturnPembelian' => 'Retur Pembelian',
            'JurnalAkuntansi' => 'Jurnal Akuntansi',
            'Piutang' => 'Piutang Konsumen',
            'Utang' => 'Utang Supplier',
            'PurchaseOrder' => 'Purchase Order (PO)',
            'Grn' => 'Penerimaan Barang (GRN)',
            'Produk' => 'Produk & SKU',
            'Pelanggan' => 'Pelanggan CRM',
            'Supplier' => 'Supplier / Vendor',
            'StokItem' => 'Stok Barang',
            'StokOpname' => 'Stok Opname',
            'StokTransfer' => 'Transfer Stok',
            'User' => 'Akun Pengguna',
            'Cabang' => 'Cabang Toko',
            'KategoriProduk' => 'Kategori Produk',
            'Brand' => 'Brand / Merek',
            'KualitasProduk' => 'Kualitas Produk',
            'SatuanUnit' => 'Satuan Unit',
            'TipeHp' => 'Tipe / Model HP',
            'Gudang' => 'Gudang Toko',
            'Rak' => 'Rak Gudang',
            'JenisServis' => 'Jenis Servis',
            'AkunCOA' => 'Akun COA',
            'Karyawan' => 'Data Karyawan',
            'PayrollPeriode' => 'Periode Penggajian',
            'PayrollSlip' => 'Slip Gaji',
            'AbsensiLog' => 'Log Absensi',
            'KpiHasil' => 'Hasil Evaluasi KPI',
            'ShiftJadwal' => 'Jadwal Shift',
            'KomisiTeknisiRule' => 'Aturan Komisi Teknisi',
        ];

        return $map[$base] ?? ucwords(str_replace('_', ' ', $base));
    }

    public static function labelKolom(string $key): string
    {
        $dictionary = [
            'no_transaksi' => 'Nomor Transaksi',
            'no_tiket' => 'Nomor Tiket Servis',
            'no_jurnal' => 'Nomor Jurnal',
            'no_po' => 'Nomor PO',
            'no_grn' => 'Nomor GRN',
            'no_return' => 'Nomor Retur',
            'no_opname' => 'Nomor Opname',
            'no_transfer' => 'Nomor Transfer Stok',
            'no_piutang' => 'Nomor Piutang',
            'no_utang' => 'Nomor Utang',
            'total_akhir' => 'Total Tagihan',
            'subtotal' => 'Subtotal',
            'jumlah_bayar' => 'Jumlah Bayar',
            'kembalian' => 'Kembalian',
            'metode_bayar' => 'Metode Bayar',
            'split_detail' => 'Rincian Pembayaran Split',
            'metode_bayar_selisih' => 'Metode Bayar Selisih',
            'pembayaran_detail' => 'Detail Pembayaran',
            'metode_pengembalian' => 'Metode Pengembalian Dana',
            'tipe_penyelesaian' => 'Bentuk Penyelesaian Retur',
            'status' => 'Status',
            'status_pembayaran' => 'Status Pembayaran',
            'status_garansi' => 'Status Garansi',
            'kondisi_barang' => 'Kondisi Barang',
            'pelanggan_id' => 'ID Pelanggan',
            'cabang_id' => 'Cabang Toko',
            'user_id' => 'Pengguna (User)',
            'kasir_id' => 'Kasir',
            'teknisi_id' => 'Teknisi',
            'gudang_id' => 'Gudang',
            'produk_id' => 'Produk',
            'sku_variant_id' => 'Varian SKU',
            'supplier_id' => 'Supplier / Vendor',
            'rak_id' => 'Rak Gudang',
            'karyawan_id' => 'Karyawan',
            'akun_coa_id' => 'Akun COA',
            'jenis_servis_id' => 'Jenis Servis',
            'tiket_servis_id' => 'Tiket Servis',
            'transaksi_id' => 'Transaksi Penjualan',
            'diskon_nominal' => 'Potongan Diskon',
            'diskon_persen' => 'Persen Diskon',
            'pajak_nominal' => 'Nominal Pajak',
            'ppn_persen' => 'Persen PPN',
            'ongkir' => 'Ongkos Kirim',
            'catatan' => 'Catatan',
            'catatan_qc' => 'Catatan QC',
            'keterangan' => 'Keterangan',
            'alasan' => 'Alasan',
            'void_alasan' => 'Alasan Batal / Void',
            'void_by' => 'Dibatalkan Oleh',
            'nama' => 'Nama',
            'telepon' => 'Nomor Telepon',
            'email' => 'Alamat Email',
            'alamat' => 'Alamat',
            'is_active' => 'Status Aktif',
            'is_reseller' => 'Status Reseller',
            'harga_jual_retail' => 'Harga Retail',
            'harga_modal' => 'Harga Modal / HPP',
            'harga_satuan' => 'Harga Satuan',
            'jumlah' => 'Jumlah / Qty',
            'qty' => 'Kuantitas',
            'satuan' => 'Satuan',
            'konversi' => 'Faktor Konversi',
            'poin_loyalty' => 'Poin Loyalitas',
            'saldo_piutang' => 'Saldo Piutang',
            'jatuh_tempo' => 'Jatuh Tempo',
            'tanggal' => 'Tanggal Transaksi',
            'tanggal_masuk' => 'Tanggal Masuk',
            'tanggal_selesai' => 'Tanggal Selesai',
            'tanggal_bayar' => 'Tanggal Bayar',
            'jenis_hp' => 'Tipe / Model HP',
            'keluhan' => 'Keluhan Kerusakan',
            'kelengkapan' => 'Kelengkapan Unit',
            'biaya_jasa' => 'Biaya Jasa',
            'biaya_part' => 'Biaya Sparepart',
            'estimasi_biaya' => 'Estimasi Biaya',
            'garansi_hari' => 'Masa Garansi (Hari)',
            'garansi_teks' => 'Keterangan Garansi',
            'password' => 'Kata Sandi',
            'role' => 'Peran Pengguna',
            'gaji_pokok' => 'Gaji Pokok',
            'tunjangan' => 'Tunjangan',
            'potongan' => 'Potongan',
            'total_gaji' => 'Total Gaji',
            'stok_sistem' => 'Stok Sistem',
            'stok_fisik' => 'Stok Fisik',
            'selisih' => 'Selisih Stok',
            'gudang_asal_id' => 'Gudang Asal',
            'gudang_tujuan_id' => 'Gudang Tujuan',
            'rak_id' => 'Lokasi Rak',
            'kode' => 'Kode',
            'sku' => 'SKU / Barcode',
            'barcode' => 'Barcode',
            'brand_id' => 'Merk / Brand',
            'kategori_id' => 'Kategori Produk',
            'kualitas_id' => 'Kualitas Produk',
            'satuan_id' => 'Satuan Produk',
            'tipe_hp_id' => 'Kompatibilitas Tipe HP',
            'tier_membership_id' => 'Tier Membership',
            'tipe_konsumen' => 'Tipe Konsumen',
            'limit_kredit' => 'Batas Limit Kredit',
            'total_belanja_12bulan' => 'Total Belanja 12 Bulan',
            'tanggal_lahir' => 'Tanggal Lahir',
            'debit' => 'Nominal Debit',
            'kredit' => 'Nominal Kredit',
            'saldo' => 'Saldo',
            'persen_diskon' => 'Persen Diskon',
            'nominal_diskon' => 'Nominal Diskon',
            'total_nominal' => 'Total Nominal',
            'nilai_realisasi' => 'Nilai Realisasi',
            'selisih_nominal' => 'Selisih Nominal',
            'tukar_items_payload' => 'Rincian Barang Pengganti (Tukar)',
            'foto_bukti' => 'Bukti Foto',
            'unit_pengganti_detail' => 'Rincian Unit Pengganti',
            'ekspedisi' => 'Ekspedisi Pengiriman',
            'no_resi' => 'Nomor Resi',
            'penerima_vendor' => 'Penerima Vendor',
            'tanggal_kirim' => 'Tanggal Kirim',
            'tanggal_terima_vendor' => 'Tanggal Terima Vendor',
            'catatan_penyelesaian' => 'Catatan Penyelesaian',
            'kebijakan_retur' => 'Kebijakan Retur',
            'channel' => 'Channel Penjualan',
            'jabatan' => 'Jabatan',
            'departemen' => 'Departemen',
            'tanggal_bergabung' => 'Tanggal Bergabung',
            'shift_id' => 'Shift Kerja',
            'jam_masuk' => 'Jam Masuk',
            'jam_pulang' => 'Jam Pulang',
            'pajak_persen' => 'Persen Pajak',
            'tipe' => 'Tipe / Jenis',
            'token_approval' => 'Token Persetujuan',
        ];

        if (isset($dictionary[$key])) {
            return $dictionary[$key];
        }

        $clean = preg_replace('/_id$/', '', $key);
        $clean = str_replace('_', ' ', (string) $clean);

        return ucwords($clean);
    }

    protected static array $namaCache = [];

    public static function resolveNamaEntitas(string $key, mixed $id): ?string
    {
        if (empty($id) || ! is_numeric($id)) {
            return null;
        }

        $id = (int) $id;
        $cacheKey = "{$key}:{$id}";
        if (array_key_exists($cacheKey, self::$namaCache)) {
            return self::$namaCache[$cacheKey];
        }

        try {
            $nama = match ($key) {
                'produk_id' => Produk::find($id)?->nama,
                'sku_variant_id' => ($v = SkuVariant::with('produk')->find($id)) ? (($v->produk?->nama ? $v->produk->nama.' - ' : '').$v->sku) : null,
                'cabang_id' => Cabang::find($id)?->nama,
                'pelanggan_id' => Pelanggan::find($id)?->nama,
                'supplier_id' => Supplier::find($id)?->nama,
                'gudang_id' => Gudang::find($id)?->nama,
                'rak_id' => Rak::find($id)?->nama,
                'brand_id' => Brand::find($id)?->nama,
                'kategori_id' => KategoriProduk::find($id)?->nama,
                'kualitas_id' => KualitasProduk::find($id)?->nama,
                'satuan_id' => SatuanUnit::find($id)?->nama,
                'tipe_hp_id' => TipeHp::find($id)?->nama,
                'user_id', 'causer_id', 'void_by', 'kasir_id' => User::find($id)?->name ?? Karyawan::find($id)?->nama,
                'teknisi_id', 'karyawan_id' => Karyawan::find($id)?->nama ?? User::find($id)?->name,
                'shift_id' => Shift::find($id)?->nama,
                'akun_coa_id' => ($coa = AkunCOA::find($id)) ? ($coa->kode_akun.' - '.$coa->nama) : null,
                'jenis_servis_id' => JenisServis::find($id)?->nama,
                'tier_membership_id' => TierMembership::find($id)?->nama,
                'tiket_servis_id' => TiketServis::find($id)?->no_tiket,
                'transaksi_id' => Transaksi::find($id)?->no_transaksi,
                default => null,
            };

            $formatted = $nama ? "{$nama} (ID: #{$id})" : null;
            self::$namaCache[$cacheKey] = $formatted;

            return $formatted;
        } catch (\Throwable) {
            self::$namaCache[$cacheKey] = null;

            return null;
        }
    }

    public static function formatNilai(mixed $value, string $key): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'Ya / Aktif' : 'Tidak / Nonaktif';
        }

        if ($key === 'split_detail') {
            $arr = is_string($value) ? json_decode($value, true) : (array) $value;
            if (empty($arr)) {
                return '—';
            }
            $tunai = (float) ($arr['tunai'] ?? 0);
            $nonTunai = (float) ($arr['non_tunai'] ?? 0);
            $metodeNon = strtoupper(str_replace('_', ' ', (string) ($arr['metode_non_tunai'] ?? 'Non-Tunai')));

            return sprintf('Tunai: Rp %s | %s: Rp %s', number_format($tunai, 0, ',', '.'), $metodeNon, number_format($nonTunai, 0, ',', '.'));
        }

        if (is_numeric($value) && (str_ends_with($key, '_id') || in_array($key, ['void_by', 'kasir_id', 'teknisi_id', 'causer_id'], true))) {
            $resolved = self::resolveNamaEntitas($key, (int) $value);
            if ($resolved) {
                return $resolved;
            }

            return '#'.$value;
        }

        $statusMap = [
            'aktif' => 'Aktif',
            'nonaktif' => 'Nonaktif',
            'lunas' => 'Lunas',
            'belum_lunas' => 'Belum Lunas',
            'selesai' => 'Selesai',
            'batal' => 'Dibatalkan',
            'void' => 'Dibatalkan (Void)',
            'draft' => 'Draf / Menunggu',
            'disetujui' => 'Disetujui',
            'ditolak' => 'Ditolak',
            'refund' => 'Pengembalian Dana (Refund)',
            'tukar_produk' => 'Tukar Produk (Exchange)',
            'antrian' => 'Dalam Antrean',
            'dicek' => 'Sedang Diperiksa',
            'menunggu_persetujuan' => 'Menunggu Persetujuan',
            'dikerjakan' => 'Sedang Dikerjakan',
            'siap_diambil' => 'Siap Diambil',
            'sudah_diambil' => 'Sudah Diambil',
            'kas' => 'Kas Toko',
            'piutang' => 'Potong Piutang',
            'rusak' => 'Barang Rusak',
            'normal' => 'Barang Normal',
        ];

        if (is_string($value) && isset($statusMap[strtolower($value)])) {
            return $statusMap[strtolower($value)];
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?(\.\d+)?Z?)?$/', $value)) {
            try {
                $c = Carbon::parse($value);

                return (str_contains($value, ':') || str_contains($value, 'T')) ? $c->format('d/m/Y H:i') : $c->format('d/m/Y');
            } catch (\Exception) {
                return $value;
            }
        }

        if (is_numeric($value) && ! str_ends_with($key, '_id') && ! str_ends_with($key, '_persen') && ! in_array($key, ['qty', 'jumlah', 'garansi_hari', 'poin_loyalty', 'konversi'], true) && ! preg_match('/(telepon|phone|hp|wa|barcode|sku|token|kode|nik|pos)/i', $key)) {
            if (preg_match('/(total|harga|subtotal|bayar|kembali|nominal|ongkir|gaji|biaya|jumlah)/i', $key)) {
                return 'Rp '.number_format((float) $value, 0, ',', '.');
            }

            return number_format((float) $value, 0, ',', '.');
        }

        if (is_array($value)) {
            if (empty($value)) {
                return '—';
            }

            // Cek apakah seluruh elemen adalah skalar
            $isAssoc = ! array_is_list($value);
            $allScalar = true;
            foreach ($value as $v) {
                if (is_array($v) || is_object($v)) {
                    $allScalar = false;
                    break;
                }
            }

            if ($allScalar) {
                if ($isAssoc) {
                    return implode(', ', array_map(
                        fn ($k, $v) => self::labelKolom((string) $k).': '.(is_bool($v) ? ($v ? 'Ya' : 'Tidak') : (string) $v),
                        array_keys($value),
                        array_values($value)
                    ));
                }

                return implode(', ', array_map(fn ($v) => is_bool($v) ? ($v ? 'Ya' : 'Tidak') : (string) $v, $value));
            }

            // Jika array of arrays / multi-dimensi (seperti item retur, tukar_items_payload, dll)
            if (array_is_list($value)) {
                $count = count($value);
                $samples = [];
                foreach ($value as $item) {
                    if (is_array($item)) {
                        $label = $item['nama_produk'] ?? $item['nama'] ?? $item['sku'] ?? $item['nama_part'] ?? $item['keterangan'] ?? null;
                        $qty = $item['qty'] ?? $item['jumlah'] ?? null;
                        if ($label) {
                            $samples[] = $qty ? "{$label} (x{$qty})" : (string) $label;
                        }
                    }
                }
                if (! empty($samples)) {
                    return implode(', ', $samples);
                }

                return "{$count} data tercatat";
            }

            // Objek / asosiatif bertingkat
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_object($value)) {
            if ($value instanceof \DateTimeInterface) {
                return $value->format('d/m/Y H:i');
            }
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }

            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }
}
