<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCabangSelected
{
    /**
     * Memastikan request terotentikasi memiliki context cabang_id dan cabang_nama yang sah di session.
     * Bila belum di-set atau cabang sebelumnya tidak valid lagi untuk user, otomatis inisialisasi ke cabang default.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Endpoint otentikasi/preferensi non-operasional dikecualikan dari pemblokiran cabang
        if ($request->is('api/select-branch') || $request->is('api/user/theme') || $request->is('api/logout') || $request->is('logout')) {
            return $next($request);
        }

        $cabangId = session('cabang_id');

        // Validasi: cabang_id harus ada dan user harus berhak mengakses cabang tersebut
        if (! $cabangId || ! $user->bisaAksesCabang((int) $cabangId)) {
            $defaultCabang = $user->cabangDefault();

            if ($defaultCabang) {
                session([
                    'cabang_id' => $defaultCabang->id,
                    'cabang_nama' => $defaultCabang->nama,
                ]);
            } else {
                // User tidak memiliki akses cabang aktif manapun
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Akun Anda belum memiliki penugasan cabang. Hubungi administrator.',
                    ], 403);
                }

                abort(403, 'Akun Anda belum memiliki penugasan cabang. Hubungi administrator.');
            }
        }

        return $next($request);
    }
}
