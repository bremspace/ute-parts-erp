<?php

namespace App\Modules\Sync;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\AsetTetap;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Akunting\Models\KasMatching;
use App\Modules\Akunting\Models\PeriodeAkuntansi;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Crm\Models\KampanyeBroadcast;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\PelangganPoinLog;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Hr\Models\AbsensiLog;
use App\Modules\Hr\Models\BagiHasil;
use App\Modules\Hr\Models\BagiHasilPeriode;
use App\Modules\Hr\Models\BagiHasilRule;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\KaryawanKomponenGaji;
use App\Modules\Hr\Models\KaryawanRiwayat;
use App\Modules\Hr\Models\KomisiTeknisiRule;
use App\Modules\Hr\Models\KpiHasil;
use App\Modules\Hr\Models\KpiMetric;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Hr\Models\Shift;
use App\Modules\Hr\Models\ShiftJadwal;
use App\Modules\Omnichannel\Models\Channel;
use App\Modules\Omnichannel\Models\ChannelProductMapping;
use App\Modules\Pos\Models\QrisCabang;
use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\ReturnPenjualanItem;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Reseller\Models\KomisiSkema;
use App\Modules\Reseller\Models\SkemaKomisi;
use App\Modules\Reseller\Models\SkemaKomisiReseller;
use App\Modules\Servis\Models\Garansi;
use App\Modules\Servis\Models\JenisServis;
use App\Modules\Servis\Models\ServisSparepart;
use App\Modules\Servis\Models\ServisStatusLog;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Models\TiketServisEstimasiItem;
use App\Modules\Servis\Models\TiketServisItem;
use App\Modules\Servis\Models\TiketServisProgress;
use App\Modules\Servis\Models\Voucher;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\CycleCountSchedule;
use App\Modules\Wms\Models\CycleCountTask;
use App\Modules\Wms\Models\Grn;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\NomorSeri;
use App\Modules\Wms\Models\NomorSeriEvent;
use App\Modules\Wms\Models\PembayaranSupplier;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\ProdukBiayaCabang;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\ReturnPembelian;
use App\Modules\Wms\Models\ReturnPembelianItem;
use App\Modules\Wms\Models\RiwayatPerubahanHarga;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokOpname;
use App\Modules\Wms\Models\StokOpnameItem;
use App\Modules\Wms\Models\StokTransfer;
use App\Modules\Wms\Models\StokTransferItem;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Workflow\Models\ApprovalRequest;
use App\Modules\Workflow\Models\ApprovalRule;
use Illuminate\Database\Eloquent\Model;

/**
 * Sumber kebenaran tunggal untuk sinkronisasi generik modul backoffice.
 * Menghindari duplikasi daftar model, natural unique key, dan peta FK
 * antara BackofficeModelSyncObserver, SyncObserverBootstrap, migrasi, dan processor.
 */
final class BackofficeSyncRegistry
{
    /**
     * Kolom sensitif/kredensial lokal yang TIDAK BOLEH disinkronkan via outbox.
     *
     * @var list<string>
     */
    public const SENSITIVE_COLUMNS = [
        'password',
        'password_hash',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_backup_codes',
        'api_token',
        'secret',
        'webhook_secret',
        'kredensial',
    ];

    /**
     * Definisi seluruh tabel backoffice yang disinkronkan secara generik.
     * Keys WAJIB sama persis dengan `$model->getTable()`.
     *
     * @return array<string, array{model: class-string<Model>, unique?: list<list<string>>, extra_fks?: array<string, array{model: class-string<Model>, natural?: list<string>}>, poly_fks?: array<string, string>, cabang_relation?: string}>
     */
    public static function tables(): array
    {
        return [
            // RBAC / Cabang
            'cabang' => [
                'model' => Cabang::class,
                'unique' => [['kode']],
            ],

            // WMS Master & Transaksional Backoffice
            'gudang' => [
                'model' => Gudang::class,
                'unique' => [['kode']],
            ],
            'rak' => [
                'model' => Rak::class,
                'unique' => [['kode']],
                'cabang_relation' => 'gudang.cabang_id',
            ],
            'supplier' => [
                'model' => Supplier::class,
                'unique' => [['kode_lama']],
            ],
            'purchase_order' => [
                'model' => PurchaseOrder::class,
                'unique' => [['no_po'], ['kode_lama']],
                'cabang_relation' => 'gudangTujuan.cabang_id',
            ],
            'purchase_order_item' => [
                'model' => PurchaseOrderItem::class,
                'unique' => [['kode_lama']],
                'cabang_relation' => 'purchaseOrder.gudangTujuan.cabang_id',
            ],
            'grn' => [
                'model' => Grn::class,
                'unique' => [['no_grn']],
            ],
            'pembayaran_supplier' => [
                'model' => PembayaranSupplier::class,
                'cabang_relation' => 'purchaseOrder.gudangTujuan.cabang_id',
            ],
            'stok_transfer' => [
                'model' => StokTransfer::class,
                'unique' => [['no_transfer']],
                'cabang_relation' => 'gudangAsal.cabang_id',
            ],
            'stok_transfer_item' => [
                'model' => StokTransferItem::class,
                'cabang_relation' => 'stokTransfer.gudangAsal.cabang_id',
            ],
            'stok_opname' => [
                'model' => StokOpname::class,
                'unique' => [['no_opname']],
                'cabang_relation' => 'gudang.cabang_id',
            ],
            'stok_opname_item' => [
                'model' => StokOpnameItem::class,
                'cabang_relation' => 'stokOpname.gudang.cabang_id',
            ],
            'return_pembelian' => [
                'model' => ReturnPembelian::class,
                'unique' => [['no_return']],
            ],
            'return_pembelian_item' => [
                'model' => ReturnPembelianItem::class,
                'cabang_relation' => 'returnPembelian.cabang_id',
            ],
            'cycle_count_schedule' => [
                'model' => CycleCountSchedule::class,
                'poly_fks' => ['target_id' => 'cycle_count_target'],
            ],
            'cycle_count_task' => [
                'model' => CycleCountTask::class,
                'unique' => [['no_task'], ['cycle_count_schedule_id', 'tanggal']],
                'poly_fks' => ['target_id' => 'cycle_count_target'],
            ],
            'nomor_seri_produk' => [
                'model' => NomorSeri::class,
                'unique' => [['produk_id', 'nomor_seri']],
            ],
            'nomor_seri_events' => [
                'model' => NomorSeriEvent::class,
            ],
            'produk_biaya_cabang' => [
                'model' => ProdukBiayaCabang::class,
                'unique' => [['produk_id', 'cabang_id']],
            ],
            'riwayat_perubahan_harga' => [
                'model' => RiwayatPerubahanHarga::class,
            ],

            // POS Backoffice (Retur & QRIS)
            'return_penjualan' => [
                'model' => ReturnPenjualan::class,
                'unique' => [['offline_uuid'], ['no_return']],
            ],
            'return_penjualan_item' => [
                'model' => ReturnPenjualanItem::class,
                'cabang_relation' => 'returnPenjualan.cabang_id',
            ],
            'qris_cabang' => [
                'model' => QrisCabang::class,
                'unique' => [['cabang_id']],
            ],

            // Servis Backoffice
            'jenis_servis' => [
                'model' => JenisServis::class,
                'unique' => [['kode']],
            ],
            'garansi' => [
                'model' => Garansi::class,
                'unique' => [['tiket_servis_id']],
                'cabang_relation' => 'tiketServis.cabang_id',
            ],
            'tiket_servis_item' => [
                'model' => TiketServisItem::class,
                'cabang_relation' => 'tiketServis.cabang_id',
            ],
            'tiket_servis_estimasi_item' => [
                'model' => TiketServisEstimasiItem::class,
                'cabang_relation' => 'tiketServis.cabang_id',
            ],
            'servis_status_log' => [
                'model' => ServisStatusLog::class,
                'cabang_relation' => 'tiketServis.cabang_id',
            ],
            'servis_sparepart' => [
                'model' => ServisSparepart::class,
                'cabang_relation' => 'tiketServis.cabang_id',
            ],
            'tiket_servis_progress' => [
                'model' => TiketServisProgress::class,
                'cabang_relation' => 'tiketServis.cabang_id',
            ],
            'vouchers' => [
                'model' => Voucher::class,
                'unique' => [['kode']],
            ],

            // HR & Bagi Hasil
            'karyawan' => [
                'model' => Karyawan::class,
                'unique' => [['nik']],
            ],
            'shift' => [
                'model' => Shift::class,
            ],
            'shift_jadwal' => [
                'model' => ShiftJadwal::class,
                'unique' => [['cabang_id', 'karyawan_id', 'tanggal']],
            ],
            'absensi_log' => [
                'model' => AbsensiLog::class,
                'unique' => [['karyawan_id', 'tanggal']],
                'cabang_relation' => 'karyawan.cabang_id',
            ],
            'kpi_metric' => [
                'model' => KpiMetric::class,
                'unique' => [['kode']],
            ],
            'kpi_hasil' => [
                'model' => KpiHasil::class,
                'unique' => [['karyawan_id', 'kpi_metric_id', 'periode']],
                'cabang_relation' => 'karyawan.cabang_id',
            ],
            'karyawan_komponen_gaji' => [
                'model' => KaryawanKomponenGaji::class,
                'cabang_relation' => 'karyawan.cabang_id',
            ],
            'karyawan_riwayat' => [
                'model' => KaryawanRiwayat::class,
                'cabang_relation' => 'karyawan.cabang_id',
            ],
            'komisi_teknisi_rules' => [
                'model' => KomisiTeknisiRule::class,
            ],
            'bagi_hasil' => [
                'model' => BagiHasil::class,
                'unique' => [['tiket_servis_id']],
            ],
            'bagi_hasil_periode' => [
                'model' => BagiHasilPeriode::class,
            ],
            'bagi_hasil_rules' => [
                'model' => BagiHasilRule::class,
            ],

            // Akunting
            'akun_coa' => [
                'model' => AkunCOA::class,
                'unique' => [['kode']],
                'extra_fks' => [
                    'parent_id' => ['model' => AkunCOA::class, 'natural' => ['kode']],
                ],
            ],
            'aset_tetap' => [
                'model' => AsetTetap::class,
            ],
            'piutang' => [
                'model' => Piutang::class,
                'unique' => [['kode_lama'], ['referensi_tipe', 'referensi_id']],
                'poly_fks' => ['referensi_id' => 'class_column:referensi_tipe'],
            ],
            'utang' => [
                'model' => Utang::class,
                'unique' => [['kode_lama'], ['referensi_tipe', 'referensi_id']],
                'poly_fks' => ['referensi_id' => 'class_column:referensi_tipe'],
            ],
            'periode_akuntansi' => [
                'model' => PeriodeAkuntansi::class,
                'unique' => [['cabang_id', 'tahun', 'bulan']],
            ],
            'kas_matching' => [
                'model' => KasMatching::class,
            ],

            // CRM & Reseller
            'tier_memberships' => [
                'model' => TierMembership::class,
                'unique' => [['kode']],
            ],
            'leads' => [
                'model' => Lead::class,
            ],
            'kampanye_broadcast' => [
                'model' => KampanyeBroadcast::class,
            ],
            'pelanggan_poin_log' => [
                'model' => PelangganPoinLog::class,
            ],
            'komisi_skema' => [
                'model' => KomisiSkema::class,
                'poly_fks' => ['aktor_id' => 'aktor_tipe'],
            ],
            'skema_komisi' => [
                'model' => SkemaKomisi::class,
            ],
            'skema_komisi_reseller' => [
                'model' => SkemaKomisiReseller::class,
                'unique' => [['pelanggan_id', 'kategori']],
            ],
            'komisi' => [
                'model' => Komisi::class,
                'unique' => [['idempotensi_key']],
                'poly_fks' => ['aktor_id' => 'aktor_tipe'],
            ],

            // Omnichannel Config & Workflow
            'channels' => [
                'model' => Channel::class,
            ],
            'channel_product_mapping' => [
                'model' => ChannelProductMapping::class,
                'unique' => [['channel_id', 'produk_id']],
                'cabang_relation' => 'gudang.cabang_id',
            ],
            'approval_rules' => [
                'model' => ApprovalRule::class,
            ],
            'approval_requests' => [
                'model' => ApprovalRequest::class,
                'unique' => [['entity_type', 'entity_id', 'approval_rule_id']],
                'poly_fks' => ['entity_id' => 'class_column:entity_type'],
            ],
        ];
    }

    /**
     * Peta nama kolom FK standar -> model target & kunci natural untuk resolusi lintas node.
     *
     * @return array<string, array{model: class-string<Model>, natural: list<string>, fallback_user?: bool}>
     */
    public static function foreignKeyDefinitions(): array
    {
        return [
            'cabang_id' => ['model' => Cabang::class, 'natural' => ['kode']],
            'cabang_lama_id' => ['model' => Cabang::class, 'natural' => ['kode']],
            'cabang_baru_id' => ['model' => Cabang::class, 'natural' => ['kode']],
            'gudang_id' => ['model' => Gudang::class, 'natural' => ['kode']],
            'gudang_tujuan_id' => ['model' => Gudang::class, 'natural' => ['kode']],
            'gudang_asal_id' => ['model' => Gudang::class, 'natural' => ['kode']],
            'rak_id' => ['model' => Rak::class, 'natural' => ['kode']],
            'supplier_id' => ['model' => Supplier::class, 'natural' => ['kode_lama', 'nama']],
            'produk_id' => ['model' => Produk::class, 'natural' => ['slug', 'barcode']],
            'sku_variant_id' => ['model' => SkuVariant::class, 'natural' => ['sku']],
            'pelanggan_id' => ['model' => Pelanggan::class, 'natural' => ['telepon']],
            'tier_membership_id' => ['model' => TierMembership::class, 'natural' => ['kode']],
            'jenis_servis_id' => ['model' => JenisServis::class, 'natural' => ['kode']],
            'tiket_servis_id' => ['model' => TiketServis::class, 'natural' => ['no_tiket']],
            'used_tiket_id' => ['model' => TiketServis::class, 'natural' => ['no_tiket']],
            'transaksi_id' => ['model' => Transaksi::class, 'natural' => ['offline_uuid', 'no_transaksi']],
            'tukar_transaksi_id' => ['model' => Transaksi::class, 'natural' => ['offline_uuid', 'no_transaksi']],
            'purchase_order_id' => ['model' => PurchaseOrder::class, 'natural' => ['no_po', 'kode_lama']],
            'po_id' => ['model' => PurchaseOrder::class, 'natural' => ['no_po', 'kode_lama']],
            'stok_transfer_id' => ['model' => StokTransfer::class, 'natural' => ['no_transfer']],
            'stok_opname_id' => ['model' => StokOpname::class, 'natural' => ['no_opname']],
            'return_penjualan_id' => ['model' => ReturnPenjualan::class, 'natural' => ['offline_uuid', 'no_return']],
            'return_pembelian_id' => ['model' => ReturnPembelian::class, 'natural' => ['no_return']],
            'payroll_periode_id' => ['model' => PayrollPeriode::class, 'natural' => []],
            'karyawan_id' => ['model' => Karyawan::class, 'natural' => ['nik']],
            'shift_id' => ['model' => Shift::class, 'natural' => []],
            'kpi_metric_id' => ['model' => KpiMetric::class, 'natural' => ['kode']],
            'akun_coa_id' => ['model' => AkunCOA::class, 'natural' => ['kode']],
            'akun_id' => ['model' => AkunCOA::class, 'natural' => ['kode']],
            'jurnal_id' => ['model' => JurnalHeader::class, 'natural' => ['no_jurnal']],
            'skema_komisi_id' => ['model' => SkemaKomisi::class, 'natural' => []],
            'komisi_skema_id' => ['model' => KomisiSkema::class, 'natural' => []],
            'lead_id' => ['model' => Lead::class, 'natural' => []],
            'channel_id' => ['model' => Channel::class, 'natural' => []],
            'approval_rule_id' => ['model' => ApprovalRule::class, 'natural' => []],
            'cycle_count_schedule_id' => ['model' => CycleCountSchedule::class, 'natural' => []],
            'nomor_seri_id' => ['model' => NomorSeri::class, 'natural' => []],
            'tiket_servis_item_id' => ['model' => TiketServisItem::class, 'natural' => []],
            'transaksi_item_id' => ['model' => TransaksiItem::class, 'natural' => []],
            // Kolom referensi user (resolusi via email)
            'user_id' => ['model' => User::class, 'natural' => ['email'], 'fallback_user' => true],
            'approver_id' => ['model' => User::class, 'natural' => ['email'], 'fallback_user' => true],
            'user_pengirim_id' => ['model' => User::class, 'natural' => ['email'], 'fallback_user' => true],
            'user_penerima_id' => ['model' => User::class, 'natural' => ['email'], 'fallback_user' => true],
            'approved_by' => ['model' => User::class, 'natural' => ['email'], 'fallback_user' => true],
            'approved_by_id' => ['model' => User::class, 'natural' => ['email'], 'fallback_user' => true],
            'requested_by' => ['model' => User::class, 'natural' => ['email'], 'fallback_user' => true],
            'actioned_by' => ['model' => User::class, 'natural' => ['email'], 'fallback_user' => true],
            'assigned_to' => ['model' => User::class, 'natural' => ['email'], 'fallback_user' => true],
            'teknisi_id' => ['model' => User::class, 'natural' => ['email'], 'fallback_user' => true],
        ];
    }

    /**
     * Daftar natural key yang diekstrak saat serialisasi model target polymorphic.
     *
     * @return list<string>
     */
    public static function naturalKeysForModel(string $modelClass): array
    {
        return match ($modelClass) {
            Cabang::class => ['kode'],
            Gudang::class => ['kode'],
            Rak::class => ['kode'],
            Brand::class => ['nama'],
            KategoriProduk::class => ['slug'],
            Supplier::class => ['kode_lama', 'nama'],
            Produk::class => ['slug', 'barcode'],
            SkuVariant::class => ['sku'],
            Pelanggan::class => ['telepon'],
            TierMembership::class => ['kode'],
            JenisServis::class => ['kode'],
            TiketServis::class => ['no_tiket'],
            Transaksi::class => ['offline_uuid', 'no_transaksi'],
            PurchaseOrder::class => ['no_po', 'kode_lama'],
            Grn::class => ['no_grn'],
            StokTransfer::class => ['no_transfer'],
            StokOpname::class => ['no_opname'],
            ReturnPenjualan::class => ['offline_uuid', 'no_return'],
            ReturnPembelian::class => ['no_return'],
            Karyawan::class => ['nik'],
            KpiMetric::class => ['kode'],
            AkunCOA::class => ['kode'],
            JurnalHeader::class => ['no_jurnal'],
            Voucher::class => ['kode'],
            User::class => ['email'],
            default => [],
        };
    }

    /**
     * Resolve kelas target untuk kolom FK polimorfik berdasarkan payload/atribut baris.
     *
     * @param  array<string, mixed>  $attributes
     * @return class-string<Model>|null
     */
    public static function resolvePolymorphicModelClass(string $polySpec, array $attributes): ?string
    {
        if (str_starts_with($polySpec, 'class_column:')) {
            $typeCol = substr($polySpec, strlen('class_column:'));
            $class = $attributes[$typeCol] ?? null;

            return is_string($class) && class_exists($class) && is_subclass_of($class, Model::class)
                ? $class
                : null;
        }

        if ($polySpec === 'cycle_count_target') {
            return match ($attributes['tipe_target'] ?? null) {
                'rak' => Rak::class,
                'brand' => Brand::class,
                'kategori' => KategoriProduk::class,
                default => null,
            };
        }

        if ($polySpec === 'aktor_tipe') {
            return match ($attributes['aktor_tipe'] ?? null) {
                'karyawan' => Karyawan::class,
                'agen', 'reseller', 'pelanggan' => Pelanggan::class,
                default => null,
            };
        }

        return null;
    }

    /**
     * Daftar seluruh kelas model yang diamati secara generik.
     *
     * @return list<class-string<Model>>
     */
    public static function models(): array
    {
        return array_values(array_map(
            static fn (array $def): string => $def['model'],
            self::tables()
        ));
    }

    /**
     * Daftar seluruh nama tabel fisik yang butuh kolom `sync_uuid`.
     *
     * @return list<string>
     */
    public static function tableNames(): array
    {
        return array_keys(self::tables());
    }
}
