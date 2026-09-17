<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Modules\Akunting\Controllers\AkuntingController;
use App\Modules\Crm\Controllers\CrmController;
use App\Modules\Marketplace\Controllers\ImportController;
use App\Modules\Marketplace\Controllers\PaymentController;
use App\Modules\Marketplace\Controllers\ShippingController;
use App\Modules\Marketplace\Controllers\ShopController;
use App\Modules\Omnichannel\Controllers\OmnichannelController;
use App\Modules\Pos\Controllers\PosController;
use App\Modules\Rbac\Controllers\AuthController;
use App\Modules\Rbac\Controllers\RbacController;
use App\Modules\Rbac\Controllers\RbacFlexController;
use App\Modules\Reseller\Controllers\ResellerController;
use App\Modules\Servis\Controllers\ServisController;
use App\Modules\Wms\Controllers\WmsController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// [API: AUTH-01] Login — rate limited (PRD §6)
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// [API: SERVICE-06b] Approve/reject estimasi publik (token, tanpa login)
// [API: SERVICE-07] Booking servis online publik (marketplace)
Route::post('/servis/public/approve/{token}', [ServisController::class, 'publicApprove']);
Route::post('/servis/booking-online', [ServisController::class, 'bookingOnline']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // [API: AUTH-02] Select branch
    Route::post('/select-branch', [AuthController::class, 'selectBranch']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // [API: RBAC-02] My permissions
    Route::get('/me/permissions', [RbacController::class, 'permissions']);

    // [API: RBAC-01] User management
    Route::get('/users', [RbacController::class, 'index'])->middleware('permission:user.view');
    Route::post('/users', [RbacController::class, 'store'])->middleware('permission:user.create');
    Route::put('/users/{user}', [RbacController::class, 'update'])->middleware('permission:user.edit');

    // [API: RBAC-05..07][T-25] Role custom + permission matrix editable
    Route::prefix('rbac')->group(function () {
        Route::get('/roles', [RbacFlexController::class, 'indexRoles'])->middleware('permission:user.view');
        Route::post('/roles', [RbacFlexController::class, 'storeRole'])->middleware('permission:user.create');
        Route::put('/roles/{id}/permissions', [RbacFlexController::class, 'updateRolePermissions'])->middleware('permission:user.edit');
        Route::delete('/roles/{id}', [RbacFlexController::class, 'destroyRole'])->middleware('permission:user.delete');
    });

    // [API: POS-01, POS-02, PRICING-01] Modul POS
    Route::prefix('pos')->group(function () {
        Route::get('/produk', [PosController::class, 'products'])->middleware('permission:pos.view');
        Route::get('/pelanggan', [PosController::class, 'pelanggan'])->middleware('permission:pos.view'); // [T-08] POS-06
        Route::get('/transaksi', [PosController::class, 'index'])->middleware('permission:pos.view'); // [T-03] POS-05
        Route::post('/transaksi', [PosController::class, 'store'])->middleware('permission:pos.create');
        Route::post('/transaksi/{id}/tahan', [PosController::class, 'tahan'])->middleware('permission:pos.create'); // [T-03] POS-04
        Route::post('/transaksi/{id}/resume', [PosController::class, 'resume'])->middleware('permission:pos.create'); // [T-03]
        // [T-09] Kas sesi
        Route::post('/kas/buka', [PosController::class, 'bukaKas'])->middleware('permission:pos.create'); // POS-07
        Route::post('/kas/tutup', [PosController::class, 'tutupKas'])->middleware('permission:pos.create'); // POS-08
        Route::get('/kas/riwayat', [PosController::class, 'riwayatKas'])->middleware('permission:pos.view'); // POS-09
    });
    Route::get('/pricing/{produk_id}', [PosController::class, 'resolvePrice'])->middleware('permission:pos.view');

    // [API: WMS-01..08] Modul WMS
    Route::prefix('wms')->group(function () {
        Route::get('/stok', [WmsController::class, 'stok'])->middleware('permission:wms.view');
        Route::post('/transfer', [WmsController::class, 'storeTransfer'])->middleware('permission:wms.transfer');
        Route::put('/transfer/{id}/kirim', [WmsController::class, 'kirimTransfer'])->middleware('permission:wms.transfer');
        Route::put('/transfer/{id}/terima', [WmsController::class, 'terimaTransfer'])->middleware('permission:wms.transfer');
        Route::post('/opname', [WmsController::class, 'storeOpname'])->middleware('permission:wms.opname');
        Route::post('/opname/{id}/items', [WmsController::class, 'inputOpnameItems'])->middleware('permission:wms.opname');
        Route::put('/opname/{id}/approve', [WmsController::class, 'approveOpname'])->middleware('permission:wms.approve-opname');
        Route::get('/kartu-stok/{produk_id}', [WmsController::class, 'kartuStok'])->middleware('permission:wms.view');

        // [T-10] WMS-09..12: Supplier & PO
        Route::get('/supplier', [WmsController::class, 'supplier'])->middleware('permission:wms.view');
        Route::post('/supplier', [WmsController::class, 'storeSupplier'])->middleware('permission:wms.create');
        Route::get('/po', [WmsController::class, 'indexPo'])->middleware('permission:wms.view');
        Route::post('/po', [WmsController::class, 'storePo'])->middleware('permission:wms.create');
        Route::put('/po/{id}/status', [WmsController::class, 'updatePoStatus'])->middleware('permission:wms.create');
        Route::post('/po/{id}/bayar', [WmsController::class, 'bayarPo'])->middleware('permission:wms.create');

        // [T-15] WMS-14: generate barcode
        Route::post('/produk/{id}/generate-barcode', [WmsController::class, 'generateBarcode'])->middleware('permission:wms.create');
    });

    // [API: SERVICE-01..06] Modul Servis HP
    Route::prefix('servis')->group(function () {
        Route::get('/', [ServisController::class, 'index'])->middleware('permission:servis.view');
        Route::post('/', [ServisController::class, 'store'])->middleware('permission:servis.create');
        Route::get('/{id}', [ServisController::class, 'show'])->middleware('permission:servis.view');
        Route::put('/{id}/status', [ServisController::class, 'updateStatus'])->middleware('permission:servis.update-status');
        Route::post('/{id}/estimasi', [ServisController::class, 'setEstimasi'])->middleware('permission:servis.update-status');
        Route::post('/{id}/sparepart', [ServisController::class, 'inputSparepart'])->middleware('permission:servis.input-sparepart');
        Route::post('/{id}/pekerjaan', [ServisController::class, 'inputPekerjaan'])->middleware('permission:servis.input-sparepart'); // [T-17]
    });

    // [API: CRM-01..05] Modul CRM & Tier
    Route::prefix('crm')->group(function () {
        Route::get('/pelanggan', [CrmController::class, 'index'])->middleware('permission:crm.view');
        Route::post('/pelanggan', [CrmController::class, 'store'])->middleware('permission:crm.create'); // [T-04] CRM-06
        Route::get('/pelanggan/{id}', [CrmController::class, 'show'])->middleware('permission:crm.view');
        Route::get('/tiers', [CrmController::class, 'indexTiers'])->middleware('permission:crm.view');
        Route::post('/tiers', [CrmController::class, 'storeTier'])->middleware('permission:tier.manage');
        Route::put('/tiers/{id}', [CrmController::class, 'updateTier'])->middleware('permission:tier.manage');
        Route::post('/recalc-tier', [CrmController::class, 'recalcTiers'])->middleware('permission:tier.manage');
        Route::match(['get','post'], '/config', [CrmController::class, 'config'])->middleware('permission:tier.manage'); // [T-22] CRM-07
        Route::post('/broadcast/kampanye', [CrmController::class, 'broadcastKampanye'])->middleware('permission:crm.broadcast'); // [T-23] CRM-08
        Route::get('/broadcast/{id}/log', [CrmController::class, 'broadcastLog'])->middleware('permission:crm.broadcast'); // [T-23] CRM-09
        Route::post('/broadcast', [CrmController::class, 'broadcast'])->middleware('permission:crm.broadcast');
    });

    // [API: RESELLER-01..06][T-21] Modul Reseller & Komisi
    Route::prefix('reseller')->group(function () {
        Route::get('/', [ResellerController::class, 'index'])->middleware('permission:reseller.view');
        Route::post('/daftar', [ResellerController::class, 'daftarReseller'])->middleware('permission:reseller.manage');
        Route::get('/komisi', [ResellerController::class, 'indexKomisi'])->middleware('permission:komisi.view');
        Route::post('/komisi/approve', [ResellerController::class, 'approveKomisi'])->middleware('permission:komisi.approve');
        Route::get('/skema-komisi', [ResellerController::class, 'indexSkemaKomisi'])->middleware('permission:reseller.view');
        Route::post('/skema-komisi', [ResellerController::class, 'storeSkemaKomisi'])->middleware('permission:reseller.manage');
        Route::get('/skema/{id}', [ResellerController::class, 'skemaReseller'])->middleware('permission:reseller.view');
    });

    // [API: ACC-01..10] Modul Akunting
    Route::prefix('akunting')->group(function () {
        Route::get('/coa', [AkuntingController::class, 'indexCoa'])->middleware('permission:akunting.view');
        Route::post('/coa', [AkuntingController::class, 'storeCoa'])->middleware('permission:akunting.create');
        Route::put('/coa/{id}', [AkuntingController::class, 'updateCoa'])->middleware('permission:akunting.edit');
        Route::get('/jurnal', [AkuntingController::class, 'indexJurnal'])->middleware('permission:akunting.view');
        Route::post('/jurnal-manual', [AkuntingController::class, 'storeJurnalManual'])->middleware('permission:akunting.create');
        Route::get('/laporan/laba-rugi', [AkuntingController::class, 'labaRugi'])->middleware('permission:laporan.cabang');
        Route::get('/laporan/neraca', [AkuntingController::class, 'neraca'])->middleware('permission:laporan.cabang');
        Route::get('/laporan/arus-kas', [AkuntingController::class, 'arusKas'])->middleware('permission:laporan.cabang');
        Route::post('/export', [AkuntingController::class, 'export'])->middleware('permission:laporan.cabang'); // [T-24] ACC-11
        Route::get('/export/download', [AkuntingController::class, 'exportDownload'])->middleware('permission:laporan.cabang'); // ACC-11b
        Route::get('/buku-besar/{akunId}', [AkuntingController::class, 'bukuBesar'])->middleware('permission:akunting.view');
        Route::get('/piutang', [AkuntingController::class, 'indexPiutang'])->middleware('permission:piutang.view');
        Route::post('/piutang/{id}/bayar', [AkuntingController::class, 'bayarPiutang'])->middleware('permission:piutang.manage');
        Route::get('/utang', [AkuntingController::class, 'indexUtang'])->middleware('permission:utang.view');
        Route::post('/utang/{id}/bayar', [AkuntingController::class, 'bayarUtang'])->middleware('permission:utang.manage');
    });
});

// ===== Marketplace publik (tanpa auth) =====
// [API: SHOP-01, SHOP-02] Katalog & detail publik
Route::prefix('shop')->group(function () {
    Route::get('/produk', [ShopController::class, 'produk'])->middleware('throttle:60,1');
    Route::get('/produk/{slug}', [ShopController::class, 'produkDetail'])->middleware('throttle:60,1');
    Route::get('/kategori', [ShopController::class, 'kategoriFilter'])->middleware('throttle:60,1');
});

// ===== Marketplace + Akun pelanggan (wajib auth:customer) =====
Route::middleware('auth:customer')->group(function () {
    // Checkout marketplace (tanpa payment dulu)
    Route::post('/shop/checkout', [ShopController::class, 'checkout']);

    // [API: ACCOUNT-01, ACCOUNT-02] Dashboard pelanggan
    Route::get('/account/orders', [ShopController::class, 'accountOrders']);
    Route::get('/account/servis', [ShopController::class, 'accountServis']);

    // [API: PAY-01] Buat transaksi Duitku
    Route::post('/payment/create', [PaymentController::class, 'create']);
    Route::get('/payment/methods', [PaymentController::class, 'paymentMethods']);
});

// ===== [API: PAY-02] Webhook Duitku — lihat routes/web.php (path tanpa prefix /api) =====
// ===== [API: OMNI-05] Webhook channel — lihat routes/web.php =====

// ===== [API: SHIP-01] Biteship ongkir + buat pengiriman =====
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/shipping/rates', [ShippingController::class, 'rates'])->middleware('permission:pos.view');
    Route::post('/shipping/create', [ShippingController::class, 'createShipment'])->middleware('permission:pos.view');
});

// ===== [API: IMPORT] Migrasi data Excel (PRD §4.11) =====
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('import')->group(function () {
        Route::post('/produk/preview', [ImportController::class, 'previewProduk'])->middleware('permission:wms.create');
        Route::post('/produk/commit', [ImportController::class, 'commitProduk'])->middleware('permission:wms.create');
    });
});

// ===== [API: OMNI-01..06] Omnikanal (wajib role super-admin/admin-toko) =====
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/omnichannel/channels', [OmnichannelController::class, 'indexChannels'])->middleware('permission:omnichannel.view');
    Route::post('/omnichannel/channels', [OmnichannelController::class, 'storeChannel'])->middleware('permission:omnichannel.manage');
    Route::get('/omnichannel/channels/{id}/status', [OmnichannelController::class, 'statusChannel'])->middleware('permission:omnichannel.view');
    Route::get('/omnichannel/channels/{id}/health', [OmnichannelController::class, 'health'])->middleware('permission:omnichannel.view');
    Route::post('/omnichannel/channels/{id}/mapping', [OmnichannelController::class, 'mapping'])->middleware('permission:omnichannel.manage');
    Route::get('/omnichannel/orders', [OmnichannelController::class, 'orders'])->middleware('permission:omnichannel.view');
    Route::post('/omnichannel/sync-stock', [OmnichannelController::class, 'syncStock'])->middleware('permission:omnichannel.manage');

    // [API: SERVICE-08] Tracking servis publik
    Route::get('/servis/tracking/{token}', [ServisController::class, 'trackingPublik']);
});
