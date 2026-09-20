<?php

use App\Modules\Akunting\Livewire\AkuntingDashboard;
use App\Modules\Crm\Livewire\CrmDashboard;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Dashboard\Livewire\DashboardIndex;
use App\Modules\Marketplace\Controllers\PaymentController;
use App\Modules\Marketplace\Livewire\CartCheckout;
use App\Modules\Marketplace\Livewire\CustomerAccount;
use App\Modules\Marketplace\Livewire\ShopPage;
use App\Modules\Omnichannel\Controllers\OmnichannelController;
use App\Modules\Omnichannel\Livewire\OmnichannelCommandCenter;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Rbac\Livewire\SettingsRbac;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Reseller\Livewire\ResellerDashboard;
use App\Modules\Servis\Livewire\ServisBoard;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Livewire\WmsDashboard;
use App\Modules\Wms\Models\Produk;
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

    return redirect()->intended('/app/pos');
})->middleware('throttle:10,1')->name('login.post');

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

    // POS Kasir Screen
    Route::get('/pos', PosKasir::class)->name('pos');

    // WMS Gudang & Stok Screen
    Route::get('/wms', WmsDashboard::class)->name('wms');

    // Servis HP Kanban Screen — [T-06] wajib role dgn permission servis.view (staf)
    Route::get('/servis', ServisBoard::class)->name('servis')->middleware('permission:servis.view');

    // CRM & Membership Screen
    Route::get('/crm', CrmDashboard::class)->name('crm');

    // Reseller & Komisi Screen
    Route::get('/reseller', ResellerDashboard::class)->name('reseller');

    // Akunting & Keuangan Screen
    Route::get('/akunting', AkuntingDashboard::class)->name('akunting');

    // Omnichannel Command Center Screen
    Route::get('/omnichannel', OmnichannelCommandCenter::class)->name('omnichannel');

    // Pengaturan & RBAC Screen
    Route::get('/pengaturan', SettingsRbac::class)->name('pengaturan');
});
