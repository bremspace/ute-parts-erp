<?php

namespace App\Http\Middleware;

use App\Modules\Crm\Services\KonfigurasiService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupCompleted
{
    /**
     * Memeriksa apakah setup wizard pasca-instalasi awal / factory reset telah diselesaikan.
     * Jika belum selesai dan user memiliki wewenang administratif, arahkan ke /app/setup-wizard.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Jalur pengecualian untuk mencegah redirection loop
        if (
            $request->is('app/setup-wizard*') ||
            $request->is('livewire*') ||
            $request->is('logout') ||
            $request->is('app/logout')
        ) {
            return $next($request);
        }

        $defaultCompleted = app()->runningUnitTests();
        $rawSetup = app(KonfigurasiService::class)->get('app_setup_completed');
        $isSetupCompleted = $rawSetup === null ? $defaultCompleted : filter_var($rawSetup, FILTER_VALIDATE_BOOLEAN);

        if (! $isSetupCompleted) {
            // Super Admin, Owner, atau yang berhak atas pengaturan diarahkan ke wizard dan WAJIB menyelesaikan tanpa bisa di-skip
            if ($user->hasRole(['super-admin', 'owner']) || $user->can('pengaturan.manage')) {
                return redirect()->route('setup.wizard');
            }

            // Staf lain tanpa wewenang setup tidak dapat masuk sebelum inisialisasi selesai
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Sistem sedang dalam tahap inisialisasi awal oleh Administrator. Silakan tunggu hingga setup selesai.',
            ]);
        }

        return $next($request);
    }
}
