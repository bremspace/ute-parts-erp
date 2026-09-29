<?php

namespace App\Modules\Rbac\Livewire;

use App\Models\User;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\AktivitasLog;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Services\AktivitasCabang;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Models\Grn;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\ReturnPembelian;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokOpname;
use App\Modules\Wms\Models\StokTransfer;
use App\Modules\Wms\Models\Supplier;
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

    protected function adalahSuperAdmin(): bool
    {
        return (bool) auth()->user()?->hasRole('super-admin');
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
            ],
        ])->layout('layouts.backoffice', ['header' => 'Audit Trail & Log']);
    }
}
