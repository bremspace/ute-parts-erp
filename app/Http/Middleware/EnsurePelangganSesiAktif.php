<?php

namespace App\Http\Middleware;

use App\Modules\Rbac\Services\SessionManagementService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penegakan sesi perangkat member (ISO 27001 A.9.4.2).
 *
 * Sesi guard `customer` hanya sah jika token perangkat yang tersimpan di sesi
 * masih berstatus aktif di `pelanggan_device_sessions`. Daftar " Perangkat &
 * Sesi" di akun member bisa mengakhiri sesi jarak jauh; middleware ini yang
 * menegakkannya.
 *
 * Sesi lama pra-deploy (belum punya token) langsung diberi penanda perangkat
 * sekali tulis, sehingga tidak ada celah bypass.
 */
class EnsurePelangganSesiAktif
{
    public function __construct(private readonly SessionManagementService $sessionService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $member = auth('customer')->user();

        if ($member) {
            $token = (string) $request->session()->get('customer_device_token', '');

            if ($token === '') {
                $userAgent = $request->userAgent() ?? '';
                $token = (string) Str::uuid();
                $this->sessionService->registerPelangganDevice(
                    $member->id,
                    $userAgent ? Str::limit($userAgent, 40) : 'Web Browser',
                    $token,
                    $request->ip(),
                    $userAgent
                );
                $request->session()->put('customer_device_token', $token);
            } elseif (! $this->sessionService->pelangganDeviceAktif($member->id, $token)) {
                auth('customer')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('customer.login');
            } else {
                $this->sessionService->sentuhPelangganDevice($member->id, $token);
            }
        }

        return $next($request);
    }
}
