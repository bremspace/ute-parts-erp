<?php

namespace App\Modules\Rbac\Livewire;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Hr\Models\AbsensiLog;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\KaryawanKomponenGaji;
use App\Modules\Hr\Models\KomisiTeknisiRule;
use App\Modules\Hr\Models\KpiHasil;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Hr\Models\PayrollSlip;
use App\Modules\Hr\Models\ShiftJadwal;
use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\Transaksi;
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
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokOpname;
use App\Modules\Wms\Models\StokTransfer;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Models\TipeHp;
use Illuminate\Contracts\Pagination\Paginator;
use Livewire\Component;

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
        'retur_pembelian' => ReturnPembelian::class,
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
    public string $filterUser = '';

    /** '' | created | updated | deleted */
    public string $filterAksi = '';

    public string $filterDari = '';

    public string $filterSampai = '';

    public int $halaman = 1;

    public const PER_HALAMAN = 10;

    public function updating(string $property): void
    {
        if (str_starts_with($property, 'filter')) {
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
        $this->filterUser = '';
        $this->filterAksi = '';
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
            'retur_penjualan' => $model->no_return ?? '#'.$model->id,
            'retur_pembelian' => $model->no_return ?? '#'.$model->id,
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

    public function getAktivitasProperty(): Paginator
    {
        if ($this->akses !== 'ok') {
            return new \Illuminate\Pagination\Paginator(collect(), self::PER_HALAMAN, $this->halaman);
        }

        $query = AktivitasLog::query()->orderByDesc('id');

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

        return $query->paginate(self::PER_HALAMAN, ['*'], 'page', $this->halaman);
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
}
