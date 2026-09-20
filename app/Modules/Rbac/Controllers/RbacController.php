<?php

namespace App\Modules\Rbac\Controllers;

use App\Modules\Rbac\Models\Cabang;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Session;

class RbacController extends Controller
{
    use ApiResponse;

    // [API: RBAC-01] List users
    public function index(Request $request)
    {
        $query = User::with('roles');

        // Scope to cabang_id if not super-admin
        $cabangId = session('cabang_id');
        if (!auth()->user()->hasRole('super-admin') && $cabangId) {
            $query->whereHas('cabangs', function ($q) use ($cabangId) {
                $q->where('cabang_id', $cabangId);
            });
        }

        $users = $query->paginate(20);

        return $this->success($users, 'Data pengguna berhasil diambil');
    }

    // [API: RBAC-01] Create user
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|string',
            'cabang_ids' => 'required|array',
            'cabang_ids.*' => 'integer|exists:cabang,id',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);

        $user->assignRole($request->role);

        foreach ($request->cabang_ids as $cabangId) {
            $user->cabangs()->attach($cabangId);
        }

        app(\App\Modules\Rbac\Services\AuditService::class)->catat(
            'User', 'create', $user->id,
            "User {$user->name} dibuat, role {$request->role}"
        );

        return $this->success($user->load('roles', 'cabangs'), 'Pengguna berhasil dibuat', 201);
    }

    // [API: RBAC-01] Update user
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|required|string|min:8',
            'role' => 'sometimes|required|string',
            'cabang_ids' => 'sometimes|required|array',
            'cabang_ids.*' => 'integer|exists:cabang,id',
        ]);

        if ($request->has('name')) {
            $user->name = $request->name;
        }
        if ($request->has('email')) {
            $user->email = $request->email;
        }
        if ($request->has('password')) {
            $user->password = bcrypt($request->password);
        }
        $user->save();

        if ($request->has('role')) {
            $user->syncRoles($request->role);
        }

        if ($request->has('cabang_ids')) {
            $user->cabangs()->sync($request->cabang_ids);
        }

        app(\App\Modules\Rbac\Services\AuditService::class)->catat(
            'User', 'update', $user->id,
            "User {$user->name} diperbarui"
        );

        return $this->success($user->load('roles', 'cabangs'), 'Pengguna berhasil diperbarui');
    }

    // [API: RBAC-02] My permissions
    public function permissions(Request $request)
    {
        $user = $request->user();

        return $this->success([
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'roles' => $user->roles->pluck('name'),
            'cabang' => session('cabang_id') ? Cabang::find(session('cabang_id')) : null,
            'cabang_id' => session('cabang_id'),
        ], 'Data izin berhasil diambil');
    }
}
