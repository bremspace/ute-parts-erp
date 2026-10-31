<?php

namespace App\Http\Middleware;

use App\Modules\Crm\Services\KonfigurasiService;
use Closure;
use Illuminate\Http\Request;
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
            $request->is('app/logout') ||
            $request->is('app/keamanan*') ||
            $request->is('api/*') ||
            $request->is('webhook/*')
        ) {
            return $next($request);
        }

        $isSetupCompleted = (bool) app(KonfigurasiService::class)->get('app_setup_completed', false);

        if (! $isSetupCompleted) {
            // Super Admin, Owner, atau yang berhak atas pengaturan diarahkan ke wizard
            if ($user->hasRole(['super-admin', 'owner']) || $user->can('pengaturan.edit')) {
                return redirect()->route('setup.wizard');
            }
        }

        return $next($request);
    }
}
