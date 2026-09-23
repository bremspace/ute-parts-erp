<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;

/**
 * [F1-3] Login challenge 2FA — setelah password valid, SEBELUM session grant penuh.
 * Rate limit ~5 percobaan (RateLimiter, cache array di test / redis|file di prod).
 * Mendukung TOTP 6 digit ATAU backup code (consume-on-use).
 */
class TwoFactorChallengeController extends Controller
{
    /** Maks percobaan sebelum diblokir sementara. */
    public const MAX_ATTEMPTS = 5;

    /** Decay RateLimiter (detik). */
    public const DECAY_SECONDS = 60;

    public function show(Request $request): RedirectResponse|View
    {
        $pendingId = $request->session()->get('two_factor_login_id');

        if (Auth::check()) {
            return redirect('/app/dashboard');
        }

        if (! $pendingId) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Sesi verifikasi tidak ditemukan. Silakan login ulang.']);
        }

        $user = User::find($pendingId);

        if (! $user || ! $user->hasEnabledTwoFactor()) {
            $request->session()->forget('two_factor_login_id');

            return redirect()->route('login')
                ->withErrors(['email' => 'Akun tidak memerlukan verifikasi 2FA. Silakan login ulang.']);
        }

        return view('auth.two-factor-challenge', [
            'email' => $user->email,
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function verify(Request $request): RedirectResponse
    {
        $pendingId = $request->session()->get('two_factor_login_id');

        if (! $pendingId) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Sesi verifikasi tidak ditemukan. Silakan login ulang.']);
        }

        $user = User::find($pendingId);

        if (! $user || ! $user->hasEnabledTwoFactor()) {
            $request->session()->forget('two_factor_login_id');

            return redirect()->route('login')
                ->withErrors(['email' => 'Akun tidak memerlukan verifikasi 2FA. Silakan login ulang.']);
        }

        $request->validate([
            'code' => 'required|string|max:32',
        ]);

        $throttleKey = $this->throttleKey($user, $request);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'code' => "Terlalu banyak percobaan verifikasi. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        if (! $this->codeIsValid($user, $request->input('code'))) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            $remaining = self::MAX_ATTEMPTS - RateLimiter::attempts($throttleKey);

            return back()->withErrors([
                'code' => $remaining > 0
                    ? "Kode verifikasi salah. Sisa percobaan: {$remaining}."
                    : 'Kode verifikasi salah. Terlalu banyak percobaan, tunggu sebentar.',
            ])->withInput();
        }

        RateLimiter::clear($throttleKey);

        // Grant session penuh (Auth::login → regenerate session ID)
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('two_factor_login_id');

        // Restore CSRF token pasca-regenerate (pola sama dgn login closure)
        $csrfToken = $request->session()->token();
        if (filled($csrfToken)) {
            $request->session()->put('_token', $csrfToken);
        }

        // [T-42] Set active cabang (identik dgn login closure)
        $firstCabang = $user->cabangs()->orderBy('id')->first();
        if ($firstCabang) {
            session([
                'cabang_id' => $firstCabang->id,
                'cabang_nama' => $firstCabang->nama,
            ]);
        }

        return redirect()->intended('/app/dashboard');
    }

    protected function throttleKey(User $user, Request $request): string
    {
        return sprintf('2fa-challenge:%d:%s', $user->id, $request->ip());
    }

    /**
     * Coba TOTP 6 digit lebih dulu; jika gagal / input non-digit → backup code.
     */
    protected function codeIsValid(User $user, string $code): bool
    {
        $trimmed = trim($code);

        if (preg_match('/^\d{6}$/', $trimmed) === 1) {
            try {
                $google2fa = new Google2FA;
                if ($google2fa->verifyKey($user->two_factor_secret, $trimmed)) {
                    return true;
                }
            } catch (\Throwable) {
                // jatuh ke backup code di bawah
            }
        }

        return $user->confirmTwoFactorBackupCode($trimmed);
    }
}
