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
use Carbon\Carbon;
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
            'metode_pengembalian' => 'Metode Pengembalian Dana',
            'tipe_penyelesaian' => 'Bentuk Penyelesaian Retur',
            'status' => 'Status',
            'status_pembayaran' => 'Status Pembayaran',
            'status_garansi' => 'Status Garansi',
            'kondisi_barang' => 'Kondisi Barang',
            'pelanggan_id' => 'ID Pelanggan',
            'cabang_id' => 'ID Cabang',
            'user_id' => 'ID Pengguna',
            'kasir_id' => 'ID Kasir',
            'teknisi_id' => 'ID Teknisi',
            'gudang_id' => 'ID Gudang',
            'produk_id' => 'ID Produk',
            'supplier_id' => 'ID Supplier',
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
        ];

        if (isset($dictionary[$key])) {
            return $dictionary[$key];
        }

        $clean = preg_replace('/_id$/', '', $key);
        $clean = str_replace('_', ' ', (string) $clean);

        return ucwords($clean);
    }

    public static function formatNilai(mixed $value, string $key): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'Ya / Aktif' : 'Tidak / Nonaktif';
        }

        $statusMap = [
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

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $value)) {
            try {
                $c = Carbon::parse($value);

                return str_contains($value, ':') ? $c->format('d/m/Y H:i') : $c->format('d/m/Y');
            } catch (\Exception) {
                return $value;
            }
        }

        if (is_numeric($value) && ! str_ends_with($key, '_id') && ! str_ends_with($key, '_persen') && ! in_array($key, ['qty', 'jumlah', 'garansi_hari', 'poin_loyalty', 'konversi'], true)) {
            if (preg_match('/(total|harga|subtotal|bayar|kembali|nominal|ongkir|gaji|biaya|jumlah)/i', $key)) {
                return 'Rp '.number_format((float) $value, 0, ',', '.');
            }

            return number_format((float) $value, 0, ',', '.');
        }

        if (is_array($value)) {
            return implode(', ', array_map(fn ($k, $v) => is_numeric($k) ? (string) $v : self::labelKolom((string) $k).': '.$v, array_keys($value), array_values($value)));
        }

        return (string) $value;
    }
}
