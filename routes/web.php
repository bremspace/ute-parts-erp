<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\TwoFactorSetupController;
use App\Modules\Akunting\Livewire\AkuntingDashboard;
use App\Modules\Akunting\Livewire\AsetRegister;
use App\Modules\Akunting\Livewire\LaporanPajak;
use App\Modules\Crm\Livewire\CrmDashboard;
use App\Modules\Crm\Livewire\LeadKanban;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Dashboard\Livewire\DashboardIndex;
use App\Modules\Hr\Livewire\HrAbsensiKpiPage;
use App\Modules\Hr\Livewire\HrSayaPage;
use App\Modules\Hr\Livewire\KomisiSkemaPage;
use App\Modules\Hr\Livewire\PayrollPage;
use App\Modules\Marketplace\Controllers\PaymentController;
use App\Modules\Marketplace\Livewire\CartCheckout;
use App\Modules\Marketplace\Livewire\CustomerAccount;
use App\Modules\Marketplace\Livewire\ShopPage;
use App\Modules\Omnichannel\Controllers\OmnichannelController;
use App\Modules\Omnichannel\Livewire\OmnichannelCommandCenter;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Rbac\Livewire\SettingsRbac;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Report\Livewire\DrillDownViewer;
use App\Modules\Report\Livewire\ReportBuilder;
use App\Modules\Reseller\Livewire\ResellerDashboard;
use App\Modules\Servis\Livewire\ServisBoard;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Livewire\CycleCountPage;
use App\Modules\Wms\Livewire\LaporanNomorSeri;
use App\Modules\Wms\Livewire\WmsDashboard;
use App\Modules\Wms\Models\Produk;
use App\Modules\Workflow\Livewire\ApprovalInbox;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ===== Zona Marketplace (public storefront, light mode) =====
Route::get('/', fn () => redirect('/shop'));
Route::get('/shop', ShopPage::class)->name('shop');
Route::get('/shop/{slug}', ShopPage::class)->name('shop.detail');

// Tracking servis publik (PRD §4.3: link status via token, tanpa login)
Route::get('/tracking/{token}', function ($token) {
    $tiket = TiketServis::with('garansi')
        ->where('token_approval', $token)
        ->first();

    return view('servis.tracking-publik', [
        'tiket' => $tiket ? [
            'no_tiket' => $tiket->no_tiket,
            'jenis_hp' => $tiket->jenis_hp,
            'status' => $tiket->status,
            'estimasi_biaya' => $tiket->estimasi_biaya,
            'tanggal_terima' => $tiket->tanggal_terima?->format('d/m/Y'),
            'tanggal_selesai' => $tiket->tanggal_selesai?->format('d/m/Y'),
            'cabang' => $tiket->cabang?->nama,
            'garansi' => $tiket->garansi ? [
                'mulai' => $tiket->garansi->tanggal_mulai->format('d/m/Y'),
                'berakhir' => $tiket->garansi->tanggal_berakhir->format('d/m/Y'),
                'aktif' => $tiket->garansi->active,
            ] : null,
        ] : null,
    ]);
})->name('servis.tracking');

// ===== [API: PAY-02] Webhook Duitku — path tanpa prefix /api (PRD §5) =====
// Duitku menembak POST /webhook/duitku dengan json body — bebaskan dari CSRF.
Route::post('/webhook/duitku', [PaymentController::class, 'webhook'])
    ->middleware('throttle:60,1')
    ->withoutMiddleware(ValidateCsrfToken::class);

// ===== [API: OMNI-05] Webhook channel marketplace (publik) =====
Route::post('/webhook/channel/{channelId}', [OmnichannelController::class, 'webhook'])
    ->middleware('throttle:120,1')
    ->withoutMiddleware(ValidateCsrfToken::class);

// Auth + akun pelanggan
Route::get('/login-pelanggan', fn () => view('auth.customer-login'))->name('customer.login');
Route::post('/login-pelanggan', function (Request $request) {
    $credentials = $request->validate([
        'telepon' => 'required|string',
        'password' => 'required|string',
    ]);

    if (Auth::guard('customer')->attempt(['telepon' => $credentials['telepon'], 'password' => $credentials['password']])) {
        return redirect()->intended('/checkout');
    }

    return back()->withErrors(['telepon' => 'Telepon atau password salah']);
})->middleware('throttle:10,1')->name('customer.login.post');

Route::get('/daftar-pelanggan', fn () => view('auth.customer-register'))->name('customer.register');
Route::post('/daftar-pelanggan', function (Request $request) {
    $request->validate([
        'nama' => 'required|string|max:255',
        'telepon' => 'required|string|max:20|unique:pelanggan,telepon',
        'password' => 'required|string|min:6',
    ]);

    $pelanggan = Pelanggan::create([
        'nama' => $request->nama,
        'telepon' => $request->telepon,
        'password' => $request->password,
        'is_reseller' => false,
    ]);

    Auth::guard('customer')->login($pelanggan);

    return redirect('/checkout');
})->middleware('throttle:10,1')->name('customer.register.post');

Route::post('/logout-pelanggan', function () {
    Auth::guard('customer')->logout();

    return redirect('/shop');
})->name('customer.logout');

// Keranjang & checkout (wajib login customer) — halaman cart menampilkan form login bila guest
Route::get('/cart', CartCheckout::class)->name('cart');
Route::get('/checkout', CartCheckout::class)->name('checkout');

// Dashboard akun pelanggan (ACCOUNT-01..05)
Route::get('/account', CustomerAccount::class)
    ->middleware('auth:customer')
    ->name('customer.account');

// [T-15] Cetak label barcode (multi-produk via ?ids=1,2,3)
Route::get('/print-barcode', function (Request $request) {
    $ids = collect(explode(',', (string) $request->query('ids', '')))
        ->filter(fn ($v) => is_numeric($v))
        ->map(fn ($v) => (int) $v);

    $produks = $ids->isEmpty()
        ? Produk::where('is_active', true)->limit(20)->get()
        : Produk::whereIn('id', $ids)->get();

    return view('wms.barcode-label', ['produks' => $produks]);
})->middleware('auth')->name('barcode.print');

// ===== Zona Backoffice (internal staff, dark mode) =====
Route::get('/app/login', function () {
    return view('auth.login');
})->name('login');

Route::post('/app/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    // Preserve CSRF token before authentication
    $csrfToken = $request->session()->token();

    if (! Auth::attempt($credentials)) {
        return back()->withErrors(['email' => 'Email atau password salah']);
    }

    if (! auth()->user()->is_active) {
        Auth::logout();

        return back()->withErrors(['email' => 'Akun tidak aktif']);
    }

    // Restore CSRF token after authentication (session regenerated by Auth::attempt)
    $request->session()->put('_token', $csrfToken);

    // [F1-3] Password valid + 2FA aktif → TOTP challenge SEBELUM session grant penuh.
    // Auth::logout() hanya bersihkan auth storage (session data termasuk _token & pending id tetap).
    if (auth()->user()->hasEnabledTwoFactor()) {
        $pendingId = auth()->id();
        Auth::logout();
        session([
            'two_factor_login_id' => $pendingId,
            '_token' => $csrfToken,
        ]);

        return redirect()->route('two-factor.challenge');
    }

    // [F1-3][G-03] Role wajib 2FA (super-admin|admin-toko|finance) tanpa setup →
    // MVP TANPA lock-out: login dilanjutkan + tandai sesi agar banner setup tampil.
    if (auth()->user()->requiresTwoFactor() && ! auth()->user()->hasEnabledTwoFactor()) {
        session(['two_factor_setup_required' => true]);
    }

    // [T-42] Set active cabang sesi secara otomatis (pertama akses)
    $firstCabang = auth()->user()->cabangs()->orderBy('id')->first();
    if ($firstCabang) {
        session([
            'cabang_id' => $firstCabang->id,
            'cabang_nama' => $firstCabang->nama,
        ]);
    }

    return redirect()->intended('/app/dashboard');
})->middleware('throttle:10,1')->name('login.post');

// [T-39] Logout backoffice — POST web form (CSRF) menggantikan /api/logout (JSON SPA).
// Redirect penuh ke /app/login; session dihancurkan di AuthenticatedSessionController.
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::prefix('app')->middleware('auth')->group(function () {
    Route::get('/', fn () => redirect('/app/dashboard'));

    // [T-02] Ganti cabang aktif — perbarui session + kembali ke halaman asal (full reload supaya semua modul re-query)
    Route::post('/pilih-cabang', function (Request $request) {
        $request->validate(['cabang_id' => 'required|exists:cabang,id']);

        $cabang = Cabang::findOrFail($request->cabang_id);
        $user = auth()->user();

        if (! $user->cabangs()->where('cabang_id', $cabang->id)->exists()) {
            abort(403, 'Anda tidak memiliki akses ke cabang ini');
        }

        session([
            'cabang_id' => $cabang->id,
            'cabang_nama' => $cabang->nama,
        ]);

        return redirect()->to($request->input('back', url()->previous() ?: '/app/dashboard'));
    })->name('pilih-cabang');

    // Dashboard (DASH-01 widget per role)
    Route::get('/dashboard', DashboardIndex::class)->name('dashboard');

    // POS Kasir Screen — RBAC: permission pos.view (admin-toko/super-admin) ATAU
    // pos.view-own (role kasir) — pola pipe spatie, sama dgn permission:role|role
    Route::get('/pos', PosKasir::class)
        ->name('pos')
        ->middleware('permission:pos.view|pos.view-own');

    // WMS Gudang & Stok Screen — [F2-2] RBAC: permission wms.view (pola servis.view/crm.view)
    Route::get('/wms', WmsDashboard::class)
        ->name('wms')
        ->middleware('permission:wms.view');

    // [F3-7] Cycle Count Otomatis (G-18) — jadwal CRUD + daftar task count
    // RBAC: permission wms.view utk lihat; mutasi (jadwal/count) di-gate wms.opname di komponen
    Route::get('/wms/cycle-count', CycleCountPage::class)
        ->name('wms.cycle-count')
        ->middleware('permission:wms.view');

    // Servis HP Kanban Screen — [T-06] wajib role dgn permission servis.view (staf)
    Route::get('/servis', ServisBoard::class)->name('servis')->middleware('permission:servis.view');

    // CRM & Membership Screen
    Route::get('/crm', CrmDashboard::class)->name('crm');

    // Lead Pipeline Kanban — [F2-1] wajib permission crm.view
    Route::get('/crm/leads', LeadKanban::class)->name('crm.leads')->middleware('permission:crm.view');

    // Reseller & Komisi Screen
    Route::get('/reseller', ResellerDashboard::class)->name('reseller');

    // Akunting & Keuangan Screen
    // [B-10a / P0-2] sebelumnya hanya `auth` (grup parent) → Livewire bisa post
    // jurnal manual, buat COA, dan bayar AR/AP tanpa permission. Permission WAJIB
    // pakai yang sudah ada di RolesAndPermissionsSeeder (tidak ada permission baru).
    Route::get('/akunting', AkuntingDashboard::class)
        ->name('akunting')
        ->middleware('permission:akunting.view');

    // [F3-3] Register Aset Tetap & Depresiasi — halaman terpisah dari dashboard
    // akunting (sebelumnya komponennya ada tapi tanpa view & tanpa route → tidak
    // bisa diakses user sama sekali, audit B-10).
    // RBAC: permission akunting.view. Mutasi di-gate di komponen AsetRegister:
    // akunting.create (tambah aset), akunting.approve (depresiasi + disposal).
    Route::get('/akunting/aset', AsetRegister::class)
        ->name('aset.register')
        ->middleware('permission:akunting.view');

    // [F1-2] Laporan Pajak Bulanan — RBAC: permission laporan.cabang (finance + admin-toko)
    Route::get('/laporan-pajak', LaporanPajak::class)
        ->name('laporan.pajak')
        ->middleware('permission:laporan.cabang');

    // Omnichannel Command Center Screen
    Route::get('/omnichannel', OmnichannelCommandCenter::class)->name('omnichannel');

    // Pengaturan & RBAC Screen
    Route::get('/pengaturan', SettingsRbac::class)->name('pengaturan');

    // [F2-4] BI Drill-down & Custom Report Builder
    Route::get('/laporan', ReportBuilder::class)->name('laporan')->middleware('permission:laporan.cabang');
    Route::get('/laporan/drill/{model}/{id?}', DrillDownViewer::class)->name('laporan.drill')->middleware('permission:laporan.cabang');

    // [F2-3] Laporan Histori Nomor Seri (trace garansi) — RBAC: permission laporan.cabang (pola laporan existing)
    Route::get('/laporan/nomor-seri', LaporanNomorSeri::class)
        ->name('laporan.nomor-seri')
        ->middleware('permission:laporan.cabang');

    // [F3-8] HR & Payroll Screen — [PRD §4.3] RBAC `kelola-payroll` (super-admin + finance saja);
    // teknisi lihat slip sendiri hanya via guard komponen PayrollSlipDetail (user_id + cabang)
    Route::get('/hr/payroll', PayrollPage::class)
        ->name('hr.payroll')
        ->middleware('permission:kelola-payroll');

    // [F3-8b] HR Absensi, Shift & KPI — admin/finance (kelola-hr)
    Route::get('/hr/absensi', HrAbsensiKpiPage::class)
        ->name('hr.absensi')
        ->middleware('permission:kelola-hr');

    // [F3-8b] Absensi & KPI karyawan (lihat milik sendiri)
    Route::get('/hr/saya', HrSayaPage::class)
        ->name('hr.saya')
        ->middleware('permission:hr.lihat-sendiri');

    // [F3-8c] Rule builder komisi multi-aktor — admin/finance (kelola-hr)
    Route::get('/hr/komisi-skema', KomisiSkemaPage::class)
        ->name('hr.komisi-skema')
        ->middleware('permission:kelola-hr');

    // [F1-1] Workflow Approval Inbox — RBAC: permission approve-workflow (spatie middleware)
    Route::get('/approvals', ApprovalInbox::class)
        ->name('approvals')
        ->middleware('permission:approve-workflow');
});

// ===== [F1-3] 2FA TOTP — login challenge (guest, setelah password valid) =====
Route::get('/app/login/2fa', [TwoFactorChallengeController::class, 'show'])
    ->name('two-factor.challenge');
Route::post('/app/login/2fa', [TwoFactorChallengeController::class, 'verify'])
    ->middleware('throttle:15,1')
    ->name('two-factor.challenge.post');

// ===== [F1-3] 2FA setup UI — wajib login backoffice =====
Route::middleware('auth')->prefix('app/keamanan')->group(function () {
    Route::get('/dua-faktor', [TwoFactorSetupController::class, 'show'])->name('two-factor.setup');
    Route::post('/dua-faktor/aktifkan', [TwoFactorSetupController::class, 'enable'])->name('two-factor.enable');
    Route::post('/dua-faktor/nonaktifkan', [TwoFactorSetupController::class, 'disable'])->name('two-factor.disable');
    Route::post('/dua-faktor/kode-cadangan', [TwoFactorSetupController::class, 'regenerateBackupCodes'])->name('two-factor.backup-codes');
});
