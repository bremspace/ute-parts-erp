<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Rbac\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\ComponentSlot;
use PragmaRX\Google2FA\Google2FA;

/**
 * [F1-3] Setup UI 2FA (backoffice, Ute Prism).
 * - GET: generate secret bila belum ada; tampilkan secret + otpauth URL (manual entry MVP)
 * - POST aktifkan: verifikasi kode → confirmed_at + 8 backup codes (sekali tampil)
 * - POST nonaktifkan / regen backup: wajib password saat ini
 * - Semua event kunci → NotificationService (queue only, dilarang sync)
 */
class TwoFactorSetupController extends Controller
{
    public function __construct(
        protected NotificationService $notifikasi,
        protected AuditService $audit,
    ) {}

    public function show(Request $request): Response
    {
        $user = $request->user();
        $google2fa = new Google2FA;

        if (! $user->two_factor_secret) {
            $user->two_factor_secret = $google2fa->generateSecretKey();
            $user->save();
        }

        $secret = $user->two_factor_secret;
        $otpauthUrl = $google2fa->getQRCodeUrl('Ute Parts', $user->email, $secret);

        // Plaintext backup codes hanya sekali (pull = get + forget)
        $backupCodes = $request->session()->pull('two_factor_backup_codes');

        $content = view('auth.two-factor-setup', [
            'user' => $user,
            'enabled' => $user->hasEnabledTwoFactor(),
            'secret' => $secret,
            'otpauthUrl' => $otpauthUrl,
            'backupCodes' => is_array($backupCodes) ? $backupCodes : null,
            'remainingBackupCodes' => count($user->two_factor_backup_codes ?? []),
            'required' => $user->requiresTwoFactor(),
        ])->render();

        // Bungkus dgn layout backoffice ($slot harus ComponentSlot agar HTML tidak di-escape)
        return response()->view('layouts.backoffice', [
            'title' => 'Keamanan Akun',
            'header' => 'Verifikasi Dua Faktor (2FA)',
            'slot' => new ComponentSlot($content),
        ]);
    }

    public function enable(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ], [
            'code.size' => 'Kode verifikasi harus 6 digit.',
        ]);

        $user = $request->user();

        if ($user->hasEnabledTwoFactor()) {
            return redirect()->route('two-factor.setup')
                ->with('message', 'Verifikasi dua faktor sudah aktif.');
        }

        if (! filled($user->two_factor_secret)) {
            return back()->withErrors(['code' => 'Secret 2FA belum tersedia. Muat ulang halaman setup.']);
        }

        $google2fa = new Google2FA;

        if (! $google2fa->verifyKey($user->two_factor_secret, $request->input('code'))) {
            return back()->withErrors(['code' => 'Kode verifikasi tidak valid. Pastikan waktu perangkat sinkron.'])
                ->withInput();
        }

        $user->two_factor_confirmed_at = now();
        $user->save();

        $backupCodes = $user->generateTwoFactorBackupCodes(8);

        // Plaintext backup codes → flash session (sekali tampil di GET redirect berikutnya, lalu pull)
        $request->session()->flash('two_factor_backup_codes', $backupCodes);

        $this->notifikasi->kirim(
            'email',
            $user->email,
            'Verifikasi Dua Faktor Diaktifkan',
            'Verifikasi dua faktor (2FA) telah diaktifkan untuk akun backoffice Anda. '
            .'Jika ini bukan Anda, segera ganti password dan nonaktifkan 2FA.',
        );

        $this->audit->catat(
            'user',
            'aktifkan_2fa',
            $user->id,
            "Aktivasi 2FA TOTP untuk {$user->email}",
            ['two_factor_enabled' => false],
            ['two_factor_enabled' => true],
        );

        return redirect()->route('two-factor.setup')
            ->with('message', '2FA aktif! Salin 8 backup code di bawah — hanya tampil sekali.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => 'required|string',
        ], [
            'password.required' => 'Password wajib diisi untuk menonaktifkan 2FA.',
        ]);

        $user = $request->user();

        if (! Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'Password salah.']);
        }

        if (! $user->hasEnabledTwoFactor()) {
            return redirect()->route('two-factor.setup');
        }

        $user->two_factor_secret = null;
        $user->two_factor_confirmed_at = null;
        $user->two_factor_backup_codes = null;
        $user->save();

        $request->session()->forget('two_factor_backup_codes');

        $this->notifikasi->kirim(
            'email',
            $user->email,
            'Verifikasi Dua Faktor Dinonaktifkan',
            'Verifikasi dua faktor (2FA) telah dinonaktifkan untuk akun backoffice Anda. '
            .'Jika ini bukan Anda, segera ganti password.',
        );

        $this->audit->catat(
            'user',
            'nonaktifkan_2fa',
            $user->id,
            "Nonaktifasi 2FA TOTP untuk {$user->email}",
            ['two_factor_enabled' => true],
            ['two_factor_enabled' => false],
        );

        return redirect()->route('two-factor.setup')
            ->with('message', 'Verifikasi dua faktor dinonaktifkan.');
    }

    public function regenerateBackupCodes(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => 'required|string',
        ], [
            'password.required' => 'Password wajib diisi untuk membuat backup code baru.',
        ]);

        $user = $request->user();

        if (! Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'Password salah.']);
        }

        if (! $user->hasEnabledTwoFactor()) {
            return back()->withErrors(['code' => 'Aktifkan 2FA terlebih dahulu.']);
        }

        $backupCodes = $user->generateTwoFactorBackupCodes(8);
        $request->session()->flash('two_factor_backup_codes', $backupCodes);

        $this->notifikasi->kirim(
            'email',
            $user->email,
            'Backup Code 2FA Dibuat Ulang',
            '8 backup code 2FA Anda telah dibuat ulang. Backup code lama tidak berlaku lagi.',
        );

        $this->audit->catat(
            'user',
            'regen_backup_2fa',
            $user->id,
            "Regenerasi backup code 2FA untuk {$user->email}",
            null,
            ['backup_codes_count' => 8],
        );

        return redirect()->route('two-factor.setup')
            ->with('message', 'Backup code baru dibuat. Yang lama tidak berlaku — salin sekarang.');
    }
}
