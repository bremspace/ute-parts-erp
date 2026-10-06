<?php

namespace App\Modules\Rbac\Controllers;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Services\SessionManagementService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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

        if (! Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            return $this->error('Email atau password salah', 401);
        }

        if (! $user->is_active) {
            Auth::logout();

            return $this->error('Akun tidak aktif', 403);
        }

        $permissions = $user->getAllPermissions()->pluck('name');
        $cabangs = $user->daftarCabangAkses();

        $data = [
            'user' => $user,
            'permissions' => $permissions,
            'cabangs' => $cabangs,
        ];

        // Auto-set session ke cabang default atau cabang aktif pertama
        $defaultCabang = $user->cabangDefault();
        if ($defaultCabang) {
            session([
                'cabang_id' => $defaultCabang->id,
                'cabang_nama' => $defaultCabang->nama,
            ]);
            $data['cabang_id'] = $defaultCabang->id;
            $data['cabang_nama'] = $defaultCabang->nama;
        }

        // [F3-4] Catat sesi perangkat
        $deviceToken = (string) Str::uuid();
        $userAgent = $request->userAgent() ?? '';
        $deviceName = $userAgent ? Str::limit($userAgent, 40) : 'API Client';
        app(SessionManagementService::class)->registerDevice(
            $user->id,
            $deviceName,
            $deviceToken,
            $request->ip(),
            $userAgent
        );
        $data['device_token'] = $deviceToken;

        return $this->success($data, 'Login berhasil');
    }

    // [API: AUTH-03] Update theme preference (T-32)
    public function updateTheme(Request $request)
    {
        $theme = $request->input('theme');

        if (! in_array($theme, ['light', 'dark', 'auto'], true)) {
            return $this->error('Tema tidak valid', 422);
        }

        $user = $request->user();
        $user->theme_preference = $theme;
        $user->save();

        return $this->success(['theme' => $theme], 'Preferensi tema disimpan');
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
        $hasAccess = $user->bisaAksesCabang((int) $request->cabang_id);

        if (! $hasAccess) {
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
