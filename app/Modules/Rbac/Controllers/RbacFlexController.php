<?php

namespace App\Modules\Rbac\Controllers;

use App\Modules\Rbac\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Spatie\Permission\Models\Role;

/**
 * [T-25] RBAC fleksibel: CRUD role custom + update permission matrix.
 * Guardrail: super-admin tidak bisa dihapus / permissionnya dikosongkan (anti lockout).
 */
class RbacFlexController extends Controller
{
    use ApiResponse;

    // [API: RBAC-05] CRUD roles
    public function indexRoles()
    {
        return $this->success(
            Role::with('permissions')->orderBy('name')->get(),
            'Daftar role berhasil dimuat'
        );
    }

    public function storeRole(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ]);

        $role = Role::findOrCreate($request->name);
        if (!empty($request->permissions)) {
            $role->syncPermissions($request->permissions);
        }

        app(AuditService::class)->catat('Role', 'create', $role->id, "Role {$role->name} dibuat");

        return $this->success($role->load('permissions'), 'Role berhasil dibuat', 201);
    }

    // [API: RBAC-06] Update permission role (matrix editable)
    public function updateRolePermissions(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string',
        ]);

        // Guardrail: super-admin tidak boleh kehilangan semua permission (anti lockout)
        if ($role->name === 'super-admin' && empty($request->permissions)) {
            return $this->error('super-admin wajib memiliki minimal 1 permission — mencegah lockout total', 422);
        }

        $role->syncPermissions($request->permissions);

        app(AuditService::class)->catat(
            'Role', 'update', $role->id,
            "Permission role {$role->name} diubah (" . count($request->permissions) . ' permission)'
        );

        return $this->success($role->load('permissions'), 'Permission role diperbarui');
    }

    // [API: RBAC-07] Delete role (guardrail super-admin)
    public function destroyRole(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        if ($role->name === 'super-admin') {
            return $this->error('Role super-admin tidak boleh dihapus (mencegah lockout sistem)', 422);
        }

        if ($role->users()->count() > 0) {
            return $this->error('Role masih dipakai oleh user — hapus/hijrahkan user terlebih dahulu', 422);
        }

        $role->delete();

        return $this->success(null, 'Role dihapus');
    }
}