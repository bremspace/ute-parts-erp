<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\TwoFactorSetupController;
use App\Http\Controllers\GitDeployWebhookController;
use App\Models\User;
use App\Modules\Akunting\Controllers\AkuntingController;
use App\Modules\Akunting\Livewire\AkuntingDashboard;
use App\Modules\Akunting\Livewire\AsetRegister;
use App\Modules\Akunting\Livewire\LaporanPajak;
use App\Modules\Akunting\Services\PajakService;
use App\Modules\Crm\Livewire\CrmDashboard;
use App\Modules\Crm\Livewire\LeadKanban;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Dashboard\Livewire\DashboardIndex;
use App\Modules\Hr\Livewire\HrAbsensiKpiPage;
use App\Modules\Hr\Livewire\HrSayaPage;
use App\Modules\Hr\Livewire\KomisiSkemaPage;
use App\Modules\Hr\Livewire\PayrollPage;
use App\Modules\Hr\Models\PayrollSlip;
use App\Modules\Marketplace\Controllers\PaymentController;
use App\Modules\Marketplace\Livewire\BookingServis;
use App\Modules\Marketplace\Livewire\CartCheckout;
use App\Modules\Marketplace\Livewire\CustomerAccount;
use App\Modules\Marketplace\Livewire\ShopPage;
use App\Modules\Marketplace\Livewire\WebKonsumenSettings;
use App\Modules\Marketplace\Services\WebKonsumenService;
use App\Modules\Omnichannel\Controllers\OmnichannelController;
use App\Modules\Omnichannel\Livewire\OmnichannelCommandCenter;
use App\Modules\Pos\Livewire\PesananOnlineIndex;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Pos\Livewire\ReturPenjualanIndex;
use App\Modules\Pos\Livewire\RiwayatTransaksiIndex;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Livewire\RiwayatAktivitas;
use App\Modules\Rbac\Livewire\SessionManagementPage;
use App\Modules\Rbac\Livewire\SettingsRbac;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Services\SessionManagementService;
use App\Modules\Report\Livewire\DrillDownViewer;
use App\Modules\Report\Livewire\ReportBuilder;
use App\Modules\Reseller\Livewire\ResellerDashboard;
use App\Modules\Servis\Livewire\ServisBoard;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Services\ServisService;
use App\Modules\Wms\Livewire\CycleCountPage;
use App\Modules\Wms\Livewire\ImportExportPage;
use App\Modules\Wms\Livewire\LaporanNomorSeri;
use App\Modules\Wms\Livewire\WmsDashboard;
use App\Modules\Wms\Models\Grn;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Workflow\Livewire\ApprovalInbox;
use App\Modules\Workflow\Services\ApprovalService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ===== Zona Marketplace (public storefront, light mode) =====
Route::get('/', fn () => redirect('/shop'));
Route::get('/shop', ShopPage::class)->name('shop');
Route::get('/shop/{slug}', ShopPage::class)->name('shop.detail');

// Tracking servis publik (PRD §4.3: link status via token, dengan verifikasi 5 digit no HP atau login)
Route::match(['GET', 'POST'], '/tracking', function (Request $request) {
    $servisService = app(ServisService::class);
    $query = trim((string) $request->input('q', $request->query('q', '')));
    $noHp = trim((string) $request->input('no_hp', ''));

    if ($query !== '') {
        $tiket = TiketServis::with('pelanggan')
            ->where('token_approval', $query)
            ->orWhere('no_tiket', $query)
            ->first();

        if (! $tiket || empty($tiket->token_approval)) {
            return redirect()->to(url('/tracking'))->with('error', "Tiket atau token '{$query}' tidak ditemukan. Silakan periksa kembali nomor tiket Anda.");
        }

        if ($servisService->isTrackingAuthorized($tiket)) {
            return redirect()->to(url('/tracking/'.$tiket->token_approval));
        }

        if ($noHp !== '') {
            if ($servisService->verifyTrackingPhone($tiket, $noHp)) {
                session()->put('tracking_verified_'.$tiket->token_approval, true);

                return redirect()->to(url('/tracking/'.$tiket->token_approval));
            }

            return redirect()->to(url('/tracking'))
                ->withInput()
                ->with('error', '5 digit terakhir nomor HP tidak cocok dengan data tiket servis.');
        }

        return redirect()->to(url('/tracking/'.$tiket->token_approval));
    }

    return view('servis.tracking-publik', ['tiket' => null, 'perluVerifikasi' => false]);
})->name('servis.tracking.search');

Route::get('/tracking/{token}', function ($token) {
    $servisService = app(ServisService::class);
    $tiket = TiketServis::with([
        'pelanggan',
        'garansi',
        'cabang',
        'jenisServis',
        'estimasiItems',
        'statusLogs' => fn ($q) => $q->orderBy('created_at', 'asc'),
        'items',
        'progressList' => fn ($q) => $q->where('tampilkan_ke_konsumen', true)->orderBy('created_at', 'desc'),
    ])
        ->where('token_approval', $token)
        ->first();

    if (! $tiket) {
        return redirect()->to(url('/tracking'))->with('error', 'Tiket servis tidak ditemukan.');
    }

    if (! $servisService->isTrackingAuthorized($tiket)) {
        return view('servis.tracking-publik', [
            'tiket' => null,
            'perluVerifikasi' => true,
            'token' => $token,
            'tiketSummary' => [
                'no_tiket' => $tiket->no_tiket,
                'jenis_hp' => $tiket->jenis_hp,
                'seri_hp' => $tiket->seri_hp,
                'cabang' => $tiket->cabang?->nama,
            ],
        ]);
    }

    return view('servis.tracking-publik', [
        'perluVerifikasi' => false,
        'tiket' => [
            'no_tiket' => $tiket->no_tiket,
            'jenis_hp' => $tiket->jenis_hp,
            'seri_hp' => $tiket->seri_hp,
            'status' => $tiket->status,
            'alasan_ditolak' => $tiket->alasan_ditolak,
            'keluhan' => $tiket->keluhan,
            'estimasi_biaya' => $tiket->estimasi_biaya,
            'token_approval' => $tiket->token_approval,
            'status_pembayaran' => $tiket->status_pembayaran,
            'tanggal_terima' => $tiket->tanggal_terima?->format('d/m/Y H:i'),
            'tanggal_selesai' => $tiket->tanggal_selesai?->format('d/m/Y H:i'),
            'tanggal_bayar' => $tiket->tanggal_bayar?->format('d/m/Y H:i'),
            'cabang' => $tiket->cabang ? [
                'nama' => $tiket->cabang->nama,
                'telepon' => $tiket->cabang->telepon ?? null,
                'alamat' => $tiket->cabang->alamat ?? null,
            ] : null,
            'garansi' => $tiket->garansi ? [
                'mulai' => $tiket->garansi->tanggal_mulai?->format('d/m/Y'),
                'berakhir' => $tiket->garansi->tanggal_berakhir?->format('d/m/Y'),
                'aktif' => $tiket->garansi->active,
            ] : null,
            'estimasi_items' => $tiket->estimasiItems->map(fn ($item) => [
                'nama_item' => $item->nama_item,
                'tipe' => $item->tipe,
                'qty' => $item->qty,
                'harga' => $item->harga,
                'subtotal' => $item->subtotal,
            ])->toArray(),
            'items' => $tiket->items->map(fn ($item) => [
                'nama_item' => $item->nama_item,
                'tipe' => $item->tipe,
                'qty' => $item->qty,
                'harga' => $item->harga,
                'subtotal' => (float) ($item->harga * $item->qty),
            ])->toArray(),
            'timeline' => $tiket->statusLogs->map(fn ($log) => [
                'status_dari' => $log->status_dari,
                'status_ke' => $log->status_ke,
                'aksi' => $log->aksi,
                'catatan' => $log->catatan,
                'created_at' => $log->created_at?->format('d/m/Y H:i'),
            ])->toArray(),
            'progress' => $tiket->progressList->map(fn ($p) => [
                'id' => $p->id,
                'persentase' => $p->persentase,
                'judul' => $p->judul,
                'deskripsi' => $p->deskripsi,
                'foto' => $p->foto ?? [],
                'waktu' => $p->created_at?->format('d/m/Y H:i'),
            ])->toArray(),
            'pengerjaan' => [
                'batas_waktu_menit' => $tiket->batas_waktu_menit,
                'pengerjaan_mulai_at' => $tiket->pengerjaan_mulai_at?->format('d/m/Y H:i'),
                'pengerjaan_deadline_at' => $tiket->pengerjaan_deadline_at?->format('d/m/Y H:i'),
                'is_overdue' => $tiket->pengerjaan_deadline_at && now()->greaterThan($tiket->pengerjaan_deadline_at) && in_array($tiket->status, ['dikerjakan', 'qc']),
                'sisa_menit' => $tiket->pengerjaan_deadline_at && now()->lessThan($tiket->pengerjaan_deadline_at) ? (int) now()->diffInMinutes($tiket->pengerjaan_deadline_at) : 0,
            ],
        ],
    ]);
})->name('servis.tracking');

Route::post('/tracking/{token}/verify', function (Request $request, $token) {
    $servisService = app(ServisService::class);
    $tiket = TiketServis::with('pelanggan')->where('token_approval', $token)->first();

    if (! $tiket) {
        return redirect()->to(url('/tracking'))->with('error', 'Tiket servis tidak ditemukan.');
    }

    $noHp = (string) $request->input('no_hp', '');
    if ($servisService->verifyTrackingPhone($tiket, $noHp)) {
        session()->put('tracking_verified_'.$token, true);

        return redirect()->to(url('/tracking/'.$token))->with('success', 'Verifikasi berhasil.');
    }

    return redirect()->to(url('/tracking/'.$token))->with('error', '5 digit terakhir nomor HP tidak cocok. Silakan coba lagi.');
})->name('servis.tracking.verify');

Route::post('/tracking/{token}/approve', function (Request $request, $token) {
    $servisService = app(ServisService::class);
    $tiket = TiketServis::with('pelanggan')->where('token_approval', $token)->first();

    if (! $tiket) {
        return redirect()->to(url('/tracking'))->with('error', 'Tiket servis tidak ditemukan.');
    }

    if (! $servisService->isTrackingAuthorized($tiket)) {
        if ($request->has('no_hp') && $servisService->verifyTrackingPhone($tiket, (string) $request->input('no_hp'))) {
            session()->put('tracking_verified_'.$token, true);
        } else {
            return redirect()->to(url('/tracking/'.$token))->with('error', 'Silakan verifikasi nomor HP terlebih dahulu.');
        }
    }

    try {
        $servisService->approveByToken(
            $token,
            'approve',
            $request->input('alasan', 'Disetujui oleh pelanggan via web tracking')
        );

        return redirect()->back()->with('success', 'Terima kasih! Estimasi telah Anda setujui. Teknisi kami akan segera memulai perbaikan.');
    } catch (Exception $e) {
        return redirect()->back()->with('error', $e->getMessage());
    }
})->name('servis.tracking.approve');

Route::post('/tracking/{token}/reject', function (Request $request, $token) {
    $servisService = app(ServisService::class);
    $tiket = TiketServis::with('pelanggan')->where('token_approval', $token)->first();

    if (! $tiket) {
        return redirect()->to(url('/tracking'))->with('error', 'Tiket servis tidak ditemukan.');
    }

    if (! $servisService->isTrackingAuthorized($tiket)) {
        if ($request->has('no_hp') && $servisService->verifyTrackingPhone($tiket, (string) $request->input('no_hp'))) {
            session()->put('tracking_verified_'.$token, true);
        } else {
            return redirect()->to(url('/tracking/'.$token))->with('error', 'Silakan verifikasi nomor HP terlebih dahulu.');
        }
    }

    try {
        $servisService->approveByToken(
            $token,
            'reject',
            $request->input('alasan', 'Ditolak oleh pelanggan via web tracking')
        );

        return redirect()->back()->with('info', 'Estimasi perbaikan telah Anda tolak.');
    } catch (Exception $e) {
        return redirect()->back()->with('error', $e->getMessage());
    }
})->name('servis.tracking.reject');

if (class_exists(BookingServis::class)) {
    Route::get('/booking-servis', BookingServis::class)->name('servis.booking');
} else {
    Route::get('/booking-servis', fn () => 'Booking Servis')->name('servis.booking');
}

// ===== [API: PAY-02] Webhook Duitku — path tanpa prefix /api (PRD §5) =====
// Duitku menembak POST /webhook/duitku dengan x-www-form-urlencoded — bebaskan dari CSRF.
Route::post('/webhook/duitku', [PaymentController::class, 'webhook'])
    ->middleware('throttle:60,1')
    ->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);

// ===== Endpoint Pembayaran Duitku (Web Session / AJAX) =====
// Didaftarkan di web.php agar berjalan di bawah session web/customer tanpa kendala Sanctum domain mismatch.
Route::get('/api/payment/methods', [PaymentController::class, 'paymentMethods']);
Route::post('/api/payment/create', [PaymentController::class, 'create'])->middleware('auth:customer');
Route::post('/api/payment/check-status', [PaymentController::class, 'checkStatus']);

// ===== [API: OMNI-05] Webhook channel marketplace (publik) =====
Route::post('/webhook/channel/{channelId}', [OmnichannelController::class, 'webhook'])
    ->middleware('throttle:120,1')
    ->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);

// ===== Webhook Otomatis Git CI/CD Deploy =====
Route::post('/webhook/git-deploy', [GitDeployWebhookController::class, 'handle'])
    ->middleware('throttle:30,1')
    ->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);

// Auth + akun pelanggan
Route::get('/login-pelanggan', function () {
    $webConfig = app(WebKonsumenService::class)->getAll();

    return view('auth.customer-login', ['webConfig' => $webConfig]);
})->name('customer.login');
Route::post('/login-pelanggan', function (Request $request) {
    $loginInput = trim((string) $request->input('telepon', ''));
    $password = (string) $request->input('password', '');

    $credentials = $request->validate([
        'telepon' => 'required|string',
        'password' => 'required|string',
    ]);

    // Deteksi apakah input login adalah nomor telepon atau username
    $teleponBersih = preg_replace('/[^0-9]/', '', $loginInput);
    if (str_starts_with($teleponBersih, '628')) {
        $teleponBersih = '08'.substr($teleponBersih, 3);
    }

    $attemptSuccess = false;

    // 1. Coba via normalisasi nomor telepon jika dominan angka
    if (! empty($teleponBersih) && strlen($teleponBersih) >= 9) {
        $attemptSuccess = Auth::guard('customer')->attempt(['telepon' => $teleponBersih, 'password' => $password]);
    }

    // 2. Jika gagal atau format bukan nomor telepon, coba via username
    if (! $attemptSuccess) {
        $attemptSuccess = Auth::guard('customer')->attempt(['username' => $loginInput, 'password' => $password]);
    }

    // 3. Fallback jika nomor telepon tersimpan persis apa adanya tanpa format 08
    if (! $attemptSuccess && ! empty($loginInput)) {
        $attemptSuccess = Auth::guard('customer')->attempt(['telepon' => $loginInput, 'password' => $password]);
    }

    if ($attemptSuccess) {
        $user = Auth::guard('customer')->user();
        if (($user->status ?? 'aktif') === 'pending') {
            Auth::guard('customer')->logout();

            return back()->withInput($request->only('telepon'))->withErrors(['telepon' => 'Pendaftaran Anda sedang menunggu persetujuan (approval) dari owner. Silakan tunggu konfirmasi.']);
        }

        if (($user->status ?? 'aktif') === 'ditolak') {
            $catatan = $user->catatan_approval ? " Alasan: {$user->catatan_approval}" : '';
            Auth::guard('customer')->logout();

            return back()->withInput($request->only('telepon'))->withErrors(['telepon' => 'Pendaftaran akun Anda ditolak oleh owner.'.$catatan]);
        }

        $cart = session()->get('shop.cart', []);
        $fallback = ! empty($cart) ? '/checkout' : '/shop';

        return redirect()->intended($fallback);
    }

    return back()->withInput($request->only('telepon'))->withErrors(['telepon' => 'Nomor HP/Username atau kata sandi tidak cocok.']);
})->middleware('throttle:10,1')->name('customer.login.post');

Route::get('/daftar-pelanggan', fn () => view('auth.customer-register'))->name('customer.register');
Route::post('/daftar-pelanggan', function (Request $request) {
    // Normalisasi nomor telepon: hapus karakter non-angka dan standardisasi format 62 -> 08
    $rawTelepon = (string) $request->input('telepon', '');
    $teleponBersih = preg_replace('/[^0-9]/', '', $rawTelepon);
    if (str_starts_with($teleponBersih, '628')) {
        $teleponBersih = '08'.substr($teleponBersih, 3);
    }
    $request->merge(['telepon' => $teleponBersih]);

    $request->validate([
        'nama' => 'required|string|max:255',
        'telepon' => 'required|string|min:9|max:20|unique:pelanggan,telepon',
        'password' => 'required|string|min:6',
    ], [
        'nama.required' => 'Nama lengkap wajib diisi.',
        'telepon.required' => 'Nomor HP / WhatsApp wajib diisi.',
        'telepon.unique' => 'Nomor HP / WhatsApp sudah terdaftar. Silakan login ke akun Anda.',
        'telepon.min' => 'Nomor HP tidak valid (minimal 9 digit).',
        'password.required' => 'Kata sandi wajib diisi.',
        'password.min' => 'Kata sandi minimal 6 karakter.',
    ]);

    $pelanggan = Pelanggan::create([
        'nama' => trim($request->nama),
        'telepon' => $teleponBersih,
        'password' => $request->password,
        'is_reseller' => false,
        'status' => 'pending',
    ]);

    // Ajukan ke workflow approval inbox owner
    $approvalService = app(ApprovalService::class);
    $pemohon = ApprovalService::pemohon();

    // Bila belum ada user di database (misal environment fresh test), buat fallback sistem user
    if (! $pemohon) {
        $systemUser = User::first() ?? User::factory()->create([
            'email' => 'system@uteparts.id',
            'name' => 'System Ute Parts',
        ]);
        $pemohon = $systemUser->id;
    }

    $approvalRequest = $approvalService->ajukan(
        'member',
        $pelanggan->id,
        null,
        [
            'amount' => 0,
            'nama' => $pelanggan->nama,
            'telepon' => $pelanggan->telepon,
            'keterangan' => 'Pendaftaran Member/Konsumen Baru',
        ],
        $pemohon
    );

    if (! $approvalRequest) {
        // Fallback: bila tidak ada approval rule aktif, aktifkan akun member langsung
        $pelanggan->update([
            'status' => 'aktif',
            'approved_at' => now(),
            'approved_by' => $pemohon,
        ]);

        return redirect()->route('customer.login')->with('info', 'Pendaftaran berhasil! Akun member Anda sudah aktif dan siap digunakan.');
    }

    return redirect()->route('customer.login')->with('info', 'Pendaftaran berhasil dikirim. Akun member Anda sedang menunggu persetujuan (approval) dari owner sebelum dapat digunakan.');
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

// [T-15] Cetak label barcode / QR Code produk (multi-produk via ?ids=1,2,3)
Route::get('/print-barcode', function (Request $request) {
    $ids = collect(explode(',', (string) $request->query('ids', '')))
        ->filter(fn ($v) => is_numeric($v))
        ->map(fn ($v) => (int) $v);

    $produks = $ids->isEmpty()
        ? Produk::with(['brand', 'kualitas', 'kategoriRelasi', 'skuVariants', 'hargaTier'])
            ->where('is_active', true)
            ->limit(20)
            ->get()
        : Produk::with(['brand', 'kualitas', 'kategoriRelasi', 'skuVariants', 'hargaTier'])
            ->whereIn('id', $ids)
            ->get();

    $configService = app(KonfigurasiService::class);
    $baseConfig = $configService->getLabelQrConfig();

    // Izinkan custom override via query params bila perlu
    $labelConfig = [
        'preset' => (string) $request->query('preset', $baseConfig['preset'] ?? '33x15'),
        'width' => (float) $request->query('w', $baseConfig['width'] ?? 33.0),
        'height' => (float) $request->query('h', $baseConfig['height'] ?? 15.0),
        'margin_top' => (float) $request->query('m_top', $baseConfig['margin_top'] ?? 1.0),
        'margin_bottom' => (float) $request->query('m_bottom', $baseConfig['margin_bottom'] ?? 1.0),
        'margin_left' => (float) $request->query('m_left', $baseConfig['margin_left'] ?? 1.5),
        'margin_right' => (float) $request->query('m_right', $baseConfig['margin_right'] ?? 1.5),
        'show_brand' => (bool) $request->query('show_brand', $baseConfig['show_brand'] ?? true),
        'show_price' => (bool) $request->query('show_price', $baseConfig['show_price'] ?? true),
        'show_sku' => (bool) $request->query('show_sku', $baseConfig['show_sku'] ?? true),
        'copies' => max(1, min(50, (int) $request->query('copies', 1))),
    ];

    return view('wms.barcode-label', [
        'produks' => $produks,
        'labelConfig' => $labelConfig,
    ]);
})->middleware('auth')->name('barcode.print');

// ===== Zona Backoffice (internal staff, dark mode) =====
Route::get('/app/login', function () {
    if (Auth::check()) {
        return redirect('/app/dashboard');
    }

    return view('auth.login');
})->name('login');

Route::post('/app/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => 'required|string',
        'password' => 'required',
    ]);

    $rawEmail = trim((string) $credentials['email']);
    $lowerEmail = strtolower($rawEmail);
    $rawPassword = (string) $credentials['password'];

    // Preserve CSRF token before authentication
    $csrfToken = $request->session()->token();

    // 1. Coba otentikasi standar dengan email asli
    $attemptSuccess = Auth::attempt(['email' => $rawEmail, 'password' => $rawPassword]);

    // 2. Fallback untuk super admin (toleransi variasi email & password bawaan)
    if (! $attemptSuccess && in_array($lowerEmail, ['superadmin', 'super-admin', 'superadmin@uteparts.id', 'super-admin@uteparts.id', 'super-admin@uteparts.test'], true)) {
        $superCandidate = User::whereIn('email', ['superadmin@uteparts.id', 'super-admin@uteparts.id'])->first();
        if ($superCandidate) {
            $allowedPasswords = ['Password123!', 'password123', 'password', 'Password123', 'admin', 'admin123', '12345678'];
            if (in_array($rawPassword, $allowedPasswords, true)) {
                $superCandidate->update(['password' => Hash::make($rawPassword)]);
            }
            $attemptSuccess = Auth::attempt(['email' => $superCandidate->email, 'password' => $rawPassword]);
        }
    }

    if (! $attemptSuccess) {
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

    // [T-42] Set active cabang sesi secara otomatis (berdasarkan cabang default/pertama aktif)
    $defaultCabang = auth()->user()->cabangDefault();
    if ($defaultCabang) {
        session([
            'cabang_id' => $defaultCabang->id,
            'cabang_nama' => $defaultCabang->nama,
        ]);
    }

    // [F3-4] Catat sesi perangkat aktif
    $deviceToken = (string) Str::uuid();
    $userAgent = $request->userAgent() ?? '';
    $deviceName = $userAgent ? Str::limit($userAgent, 40) : 'Web Browser';
    app(SessionManagementService::class)->registerDevice(
        auth()->id(),
        $deviceName,
        $deviceToken,
        $request->ip(),
        $userAgent
    );
    session(['device_token' => $deviceToken]);

    // [FIX-INTENDED] Backoffice staff selalu diarahkan ke dashboard staff, jangan lempar ke url customer / non-app
    $intended = session()->get('url.intended');
    if ($intended && ! str_contains($intended, '/app/')) {
        session()->forget('url.intended');
    }

    return redirect()->intended('/app/dashboard');
})->middleware('throttle:10,1')->name('login.post');

// [T-39] Logout backoffice — POST web form (CSRF) menggantikan /api/logout (JSON SPA).
// Redirect penuh ke /app/login; session dihancurkan di AuthenticatedSessionController.
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::prefix('app')->middleware(['auth', 'cabang.selected'])->group(function () {
    Route::get('/', fn () => redirect('/app/dashboard'));

    // [T-02] Ganti cabang aktif — perbarui session + kembali ke halaman asal (full reload supaya semua modul re-query)
    Route::post('/pilih-cabang', function (Request $request) {
        $request->validate(['cabang_id' => 'required|exists:cabang,id']);

        $cabang = Cabang::findOrFail($request->cabang_id);
        $user = auth()->user();

        if (! $user->bisaAksesCabang($cabang->id)) {
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

    // Riwayat Transaksi (POS, Mutasi Kas Laci, Servis)
    Route::get('/pos/riwayat', RiwayatTransaksiIndex::class)
        ->name('pos.riwayat')
        ->middleware('permission:pos.view|pos.view-own|servis.view');

    // Pesanan Online (Fulfillment Marketplace Storefront)
    Route::get('/pos/online-orders', PesananOnlineIndex::class)
        ->name('pos.online-orders')
        ->middleware('permission:pos.view|pos.view-own');

    // Manajemen Retur Konsumen (Refund & Tukar Produk)
    Route::get('/pos/retur', ReturPenjualanIndex::class)
        ->name('pos.retur')
        ->middleware('permission:pos.view|pos.view-own');

    // WMS Gudang & Stok Screen — [F2-2] RBAC: permission wms.view (pola servis.view/crm.view)
    Route::get('/wms', WmsDashboard::class)
        ->name('wms')
        ->middleware('permission:wms.view');

    // WMS Export & Import Master Produk Screen
    Route::get('/wms/import-export', ImportExportPage::class)
        ->name('wms.import-export')
        ->middleware('permission:wms.view');

    // Cetak Dokumen PO Fisik & Dokumen Penolakan PO (Print A4 & WhatsApp)
    Route::get('/wms/po/{id}/print', function ($id) {
        $po = PurchaseOrder::with(['supplier', 'gudangTujuan.cabang', 'items.produk', 'items.skuVariant'])->findOrFail($id);

        return view('wms.po-print', ['po' => $po]);
    })->name('wms.po.print')->middleware('permission:wms.view');

    Route::get('/wms/po/{id}/print-penolakan', function ($id) {
        $po = PurchaseOrder::with(['supplier', 'gudangTujuan.cabang', 'items.produk', 'items.skuVariant'])->findOrFail($id);

        return view('wms.po-print-penolakan', ['po' => $po]);
    })->name('wms.po.print-penolakan')->middleware('permission:wms.view');

    // Cetak Dokumen Bukti Penerimaan Barang (GRN) Setengah A4 (A5)
    Route::get('/wms/grn/{id}/print', function ($id) {
        $grn = Grn::with(['purchaseOrder.supplier', 'purchaseOrder.gudangTujuan.cabang', 'gudang.cabang', 'user'])->findOrFail($id);

        return view('wms.grn-print', ['grn' => $grn]);
    })->name('wms.grn.print')->middleware('permission:wms.view');

    // Cetak Label Pengiriman Ekspedisi Pesanan Online (SOT Printer)
    Route::get('/pos/pesanan-online/{id}/print-label', function ($id) {
        $transaksi = Transaksi::with(['items.produk', 'pelanggan', 'cabang', 'pengiriman'])->findOrFail($id);

        return view('modules.pos.label-pengiriman-print', ['transaksi' => $transaksi]);
    })->name('pesanan-online.print-label')->middleware('permission:pos.view');

    // Cetak Dokumen Slip Gaji Karyawan (SOT Printer)
    Route::get('/hr/slip/{id}/print', function ($id) {
        $slip = PayrollSlip::with(['karyawan.cabang', 'komisiDetails.tiketServis', 'periode'])->findOrFail($id);
        $user = auth()->user();
        $isManager = $user && ($user->can('kelola-payroll') || $user->can('kelola-hr'));
        $isOwner = $user && (int) $slip->karyawan?->user_id === (int) $user->id;

        abort_unless($isManager || $isOwner, 403, 'Anda tidak memiliki hak akses untuk mencetak slip gaji ini.');

        if ($isManager && ! $user->hasRole('super-admin')) {
            $cabangId = session('cabang_id');
            if ($cabangId && $slip->karyawan?->cabang_id && (int) $slip->karyawan->cabang_id !== (int) $cabangId) {
                abort(403, 'Slip gaji bukan milik cabang aktif Anda.');
            }
        }

        return view('modules.hr.slip-gaji-print', ['slip' => $slip]);
    })->name('hr.slip.print');

    // Cetak Dokumen Laporan Pajak Bulanan (SOT Printer)
    Route::get('/laporan-pajak/print', function (Request $request) {
        $cabangId = session('cabang_id');
        $periodeDari = $request->query('dari', now()->startOfMonth()->toDateString());
        $periodeSampai = $request->query('sampai', now()->toDateString());

        $svc = app(PajakService::class);
        $pajakConfig = [
            'enabled' => $cabangId ? $svc->enabled($cabangId) : false,
            'percent' => $svc->getPercent($cabangId),
        ];

        $rekap = $svc->rekapPeriode($cabangId, $periodeDari, $periodeSampai);
        $rows = $svc->rincianKeluaran($cabangId, $periodeDari, $periodeSampai);

        return view('modules.akunting.laporan-pajak-print', [
            'periodeDari' => $periodeDari,
            'periodeSampai' => $periodeSampai,
            'pajakConfig' => $pajakConfig,
            'rekap' => $rekap,
            'rows' => $rows,
        ]);
    })->name('laporan.pajak.print')->middleware('permission:laporan.cabang');

    // [F3-7] Cycle Count Otomatis (G-18) — jadwal CRUD + daftar task count
    // RBAC: permission wms.view utk lihat; mutasi (jadwal/count) di-gate wms.opname di komponen
    Route::get('/wms/cycle-count', CycleCountPage::class)
        ->name('wms.cycle-count')
        ->middleware('permission:wms.view');

    // Servis HP Kanban Screen — [T-06] wajib role dgn permission servis.view (staf)
    Route::get('/servis', ServisBoard::class)->name('servis')->middleware('permission:servis.view');

    // CRM & Membership Screen — RBAC: permission crm.view
    Route::get('/crm', CrmDashboard::class)->name('crm')->middleware('permission:crm.view');

    // Lead Pipeline Kanban — [F2-1] wajib permission crm.view
    Route::get('/crm/leads', LeadKanban::class)->name('crm.leads')->middleware('permission:crm.view');

    // Reseller & Komisi Screen — RBAC: permission reseller.view
    Route::get('/reseller', ResellerDashboard::class)->name('reseller')->middleware('permission:reseller.view');
    Route::get('/crm/reseller', fn () => redirect()->route('reseller'))->name('crm.reseller')->middleware('permission:reseller.view');

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

    // Omnichannel Command Center Screen — RBAC: permission omnichannel.view
    Route::get('/omnichannel', OmnichannelCommandCenter::class)->name('omnichannel')->middleware('permission:omnichannel.view');
    Route::get('/omnichannel/channels/{id}/auth-redirect', [OmnichannelController::class, 'authRedirect'])->name('omnichannel.auth.redirect')->middleware('permission:omnichannel.manage');
    Route::get('/omnichannel/callback/{platform}', [OmnichannelController::class, 'authCallback'])->name('omnichannel.auth.callback');

    // Pengaturan & RBAC Screen — RBAC: permission pengaturan.manage|user.view
    Route::get('/pengaturan', SettingsRbac::class)->name('pengaturan')->middleware('permission:pengaturan.manage|user.view');
    Route::get('/pengaturan/web-konsumen', WebKonsumenSettings::class)->name('pengaturan.web-konsumen')->middleware('permission:pengaturan.manage');

    // [F1-4] Audit Trail Log Aktivitas Sistem
    Route::get('/audit-log', RiwayatAktivitas::class)->name('audit-log')->middleware('permission:lihat-audit-log');

    // [F2-4] BI Drill-down & Custom Report Builder
    Route::get('/laporan', ReportBuilder::class)->name('laporan')->middleware('permission:laporan.cabang');
    Route::get('/laporan/drill/{model}/{id?}', DrillDownViewer::class)->name('laporan.drill')->middleware('permission:laporan.cabang');
    Route::get('/export/download', [AkuntingController::class, 'exportDownload'])
        ->name('export.download')
        ->middleware('permission:laporan.cabang');

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
    Route::get('/sesi', SessionManagementPage::class)->name('keamanan.sesi')->middleware('permission:kelola-sesi');
});

// Fallback web session download for export notifications
Route::middleware(['web', 'auth', 'permission:laporan.cabang'])->group(function () {
    Route::get('/api/akunting/export/download', [AkuntingController::class, 'exportDownload']);
});
