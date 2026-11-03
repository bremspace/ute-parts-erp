<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Scripts\License\LicenseVerifier;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureLicenseValid
 *
 * Middleware penjaga lisensi hardware:
 * Memastikan aplikasi hanya dapat dibuka jika lisensi mesin aktif dan sah.
 * Jika file dipindahkan ke PC lain tanpa aktivasi ulang, akses langsung diblokir.
 */
class EnsureLicenseValid
{
    public function handle(Request $request, Closure $next): Response
    {
        // Jalur bebas lisensi untuk installer web, aset statis, dan unit tests
        if (
            $request->is('install.php*') ||
            $request->is('build/*') ||
            $request->is('favicon.ico') ||
            $request->is('robots.txt') ||
            app()->runningUnitTests()
        ) {
            return $next($request);
        }

        // Jalur berkas lisensi
        $licenseFile = storage_path('license.key');
        if (! file_exists($licenseFile)) {
            // Jika belum berlisensi dan belum selesai setup wizard, izinkan setup
            if (! file_exists(storage_path('installed.lock'))) {
                return $next($request);
            }

            return $this->renderLockoutResponse('Lisensi sistem belum diaktifkan pada perangkat ini.');
        }

        // Muat kelas verifier jika belum ter-autoload
        $hwFile = base_path('scripts/license/HardwareIdentifier.php');
        $verifierFile = base_path('scripts/license/LicenseVerifier.php');
        if (file_exists($hwFile)) {
            require_once $hwFile;
        }
        if (file_exists($verifierFile)) {
            require_once $verifierFile;
        }

        if (class_exists(LicenseVerifier::class)) {
            if (! LicenseVerifier::isLicensed()) {
                return $this->renderLockoutResponse('Identitas hardware mesin tidak cocok dengan lisensi terdaftar (Anti-Duplikasi Mesin).');
            }
        }

        return $next($request);
    }

    private function renderLockoutResponse(string $reason): Response
    {
        $html = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lisensi Terkunci — Bremspace ERP & POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-white min-h-screen flex items-center justify-center p-4 font-sans">
    <div class="max-w-md w-full bg-slate-900 border border-rose-500/30 rounded-2xl p-6 text-center space-y-4 shadow-2xl">
        <div class="w-12 h-12 bg-rose-500/20 text-rose-400 rounded-full flex items-center justify-center mx-auto text-2xl font-bold">
            !
        </div>
        <h1 class="text-xl font-bold text-white tracking-tight">Sistem Terkunci: Lisensi Tidak Sah</h1>
        <p class="text-xs text-slate-400">{$reason}</p>
        <div class="p-3 bg-slate-950 border border-slate-800 rounded-lg text-xs text-slate-300 text-left space-y-1">
            <div class="font-bold text-indigo-400">Cara Mengaktifkan:</div>
            <div>1. Buka folder instalasi sistem di komputer host.</div>
            <div>2. Jalankan <code>start-install.bat</code> atau <code>start-install.sh</code>.</div>
            <div>3. Masukkan Kode Aktivasi resmi dari Developer Bremspace.</div>
        </div>
        <div class="text-[11px] text-slate-500">Bremspace ERP &amp; POS System &bull; Hardware Protection</div>
    </div>
</body>
</html>
HTML;

        return response($html, 403);
    }
}
