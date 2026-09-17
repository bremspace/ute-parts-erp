<?php

use App\Modules\Akunting\Livewire\AkuntingDashboard;
use App\Modules\Crm\Livewire\CrmDashboard;
use App\Modules\Marketplace\Livewire\CartCheckout;
use App\Modules\Marketplace\Livewire\ShopPage;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Reseller\Livewire\ResellerDashboard;
use App\Modules\Servis\Livewire\ServisBoard;
use App\Modules\Wms\Livewire\WmsDashboard;
use Illuminate\Support\Facades\Route;

// ===== Zona Marketplace (public storefront, light mode) =====
Route::get('/', fn() => redirect('/shop'));
Route::get('/shop', ShopPage::class)->name('shop');
Route::get('/shop/{slug}', ShopPage::class)->name('shop.detail');

// Tracking servis publik (PRD §4.3: link status via token, tanpa login)
Route::get('/tracking/{token}', function ($token) {
    $tiket = App\Modules\Servis\Models\TiketServis::with('garansi')
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
Route::post('/webhook/duitku', [App\Modules\Marketplace\Controllers\PaymentController::class, 'webhook'])
    ->middleware('throttle:60,1')
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

// ===== [API: OMNI-05] Webhook channel marketplace (publik) =====
Route::post('/webhook/channel/{channelId}', [App\Modules\Omnichannel\Controllers\OmnichannelController::class, 'webhook'])
    ->middleware('throttle:120,1')
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

// Auth + akun pelanggan
Route::get('/login-pelanggan', fn() => view('auth.customer-login'))->name('customer.login');
Route::post('/login-pelanggan', function (Illuminate\Http\Request $request) {
    $credentials = $request->validate([
        'telepon' => 'required|string',
        'password' => 'required|string',
    ]);

    if (Auth::guard('customer')->attempt(['telepon' => $credentials['telepon'], 'password' => $credentials['password']])) {
        return redirect()->intended('/checkout');
    }

    return back()->withErrors(['telepon' => 'Telepon atau password salah']);
})->middleware('throttle:10,1')->name('customer.login.post');

Route::get('/daftar-pelanggan', fn() => view('auth.customer-register'))->name('customer.register');
Route::post('/daftar-pelanggan', function (Illuminate\Http\Request $request) {
    $request->validate([
        'nama' => 'required|string|max:255',
        'telepon' => 'required|string|max:20|unique:pelanggan,telepon',
        'password' => 'required|string|min:6',
    ]);

    $pelanggan = App\Modules\Crm\Models\Pelanggan::create([
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
Route::get('/account', App\Modules\Marketplace\Livewire\CustomerAccount::class)
    ->middleware('auth:customer')
    ->name('customer.account');

// ===== Zona Backoffice (internal staff, dark mode) =====
Route::get('/app/login', function () {
    return view('auth.login');
})->name('login');

Route::post('/app/login', function (\Illuminate\Http\Request $request) {
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if (!Auth::attempt($credentials)) {
        return back()->withErrors(['email' => 'Email atau password salah']);
    }

    if (!auth()->user()->is_active) {
        Auth::logout();
        return back()->withErrors(['email' => 'Akun tidak aktif']);
    }

    return redirect()->intended('/app/pos');
})->middleware('throttle:10,1')->name('login.post');

Route::prefix('app')->middleware('auth')->group(function () {
    Route::get('/', fn() => redirect('/app/dashboard'));

    // Dashboard (DASH-01 widget per role)
    Route::get('/dashboard', App\Modules\Dashboard\Livewire\DashboardIndex::class)->name('dashboard');

    // POS Kasir Screen
    Route::get('/pos', PosKasir::class)->name('pos');

    // WMS Gudang & Stok Screen
    Route::get('/wms', WmsDashboard::class)->name('wms');

    // Servis HP Kanban Screen
    Route::get('/servis', ServisBoard::class)->name('servis');

    // CRM & Membership Screen
    Route::get('/crm', CrmDashboard::class)->name('crm');

    // Reseller & Komisi Screen
    Route::get('/reseller', ResellerDashboard::class)->name('reseller');

    // Akunting & Keuangan Screen
    Route::get('/akunting', AkuntingDashboard::class)->name('akunting');

    // Omnichannel Command Center Screen
    Route::get('/omnichannel', App\Modules\Omnichannel\Livewire\OmnichannelCommandCenter::class)->name('omnichannel');

    // Pengaturan & RBAC Screen
    Route::get('/pengaturan', App\Modules\Rbac\Livewire\SettingsRbac::class)->name('pengaturan');
});
