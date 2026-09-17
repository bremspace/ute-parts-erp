<?php

namespace App\Modules\Rbac\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Modules\Rbac\Models\Cabang;

class AuthController extends Controller
{
    use ApiResponse;

    // [API: AUTH-01] Login
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            return $this->error('Email atau password salah', 401);
        }

        if (!$user->is_active) {
            Auth::logout();
            return $this->error('Akun tidak aktif', 403);
        }

        $permissions = $user->getAllPermissions()->pluck('name');
        $cabangs = $user->cabangs;

        $data = [
            'user' => $user,
            'permissions' => $permissions,
            'cabangs' => $cabangs,
        ];

        // If user has exactly 1 cabang, auto-set in session
        if ($cabangs->count() === 1) {
            session([
                'cabang_id' => $cabangs->first()->id,
                'cabang_nama' => $cabangs->first()->nama,
            ]);
            $data['cabang_id'] = $cabangs->first()->id;
        }

        return $this->success($data, 'Login berhasil');
    }

    // [API: AUTH-02] Select branch
    public function selectBranch(Request $request)
    {
        $request->validate([
            'cabang_id' => 'required|exists:cabang,id',
        ]);

        $user = $request->user();
        $cabang = Cabang::findOrFail($request->cabang_id);

        // Verify user has access to this cabang
        $hasAccess = $user->cabangs()->where('cabang_id', $request->cabang_id)->exists();

        if (!$hasAccess) {
            return $this->error('Anda tidak memiliki akses ke cabang ini', 403);
        }

        session([
            'cabang_id' => $request->cabang_id,
            'cabang_nama' => $cabang->nama,
        ]);

        return $this->success([
            'cabang' => $cabang,
            'cabang_id' => $cabang->id,
        ], 'Cabang berhasil dipilih');
    }

    // Logout
    public function logout(Request $request)
    {
        session()->flush();
        Auth::logout();

        return $this->success(null, 'Logout berhasil');
    }
}
