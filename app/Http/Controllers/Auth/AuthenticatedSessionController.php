<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    /**
     * [T-39] Logout backoffice — hancurkan session web lalu arahkan ke /app/login.
     *
     * Menggantikan POST /api/logout (JSON SPA) yang di sidebar: UI web form
     * dengan CSRF mendapat redirect penuh, bukan respons JSON "Unauthenticated.".
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/app/login');
    }
}