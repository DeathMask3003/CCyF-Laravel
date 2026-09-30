<?php

namespace App\Http\Controllers;

use App\Services\CcyfPermissions;
use App\Services\LegacyMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CcyfRoleController extends Controller
{
    public function index(Request $request, LegacyMenu $menu): View
    {
        $this->authorizeRoles($request, $menu);
        $userCounts = DB::table('ccyf_usuarios')
            ->select('rol_id')
            ->selectRaw('COUNT(*) as usuarios')
            ->groupBy('rol_id');

        $roles = DB::table('ccyf_roles as role')
            ->leftJoinSub($userCounts, 'counts', 'counts.rol_id', '=', 'role.rol_id')
            ->select('role.*')->selectRaw('COALESCE(counts.usuarios, 0) as usuarios')
            ->orderByDesc('role.est')->orderBy('role.rol_nom')->get();

        return view('roles.index', compact('roles'));
    }

    public function create(Request $request, LegacyMenu $menu): View
    {
        $this->authorizeRoles($request, $menu);
        return view('roles.form', [
            'role' => null, 'groups' => CcyfPermissions::groups(), 'selected' => [],
        ]);
    }

    public function store(Request $request, LegacyMenu $menu): RedirectResponse
    {
        $this->authorizeRoles($request, $menu);
        $data = $this->validated($request);
        $this->assertUniqueName($data['nombre']);

        $id = DB::transaction(function () use ($data): int {
            $id = DB::table('ccyf_roles')->insertGetId([
                'rol_nom' => trim($data['nombre']), 'est' => true,
                'created_at' => now(), 'updated_at' => now(),
            ], 'rol_id');
            $this->syncPermissions($id, $data['permisos'] ?? []);
            return $id;
        });

        return redirect()->route('roles.edit', $id)->with('status', 'Rol creado con sus permisos.');
    }

    public function edit(int $role, Request $request, LegacyMenu $menu): View
    {
        $this->authorizeRoles($request, $menu);
        $role = $this->findRole($role);
        $selected = DB::table('ccyf_role_permissions')->where('rol_id', $role->rol_id)
            ->where('allowed', true)->pluck('menu_key')->all();

        return view('roles.form', [
            'role' => $role, 'groups' => CcyfPermissions::groups(), 'selected' => $selected,
        ]);
    }

    public function update(int $role, Request $request, LegacyMenu $menu): RedirectResponse
    {
        $this->authorizeRoles($request, $menu);
        $role = $this->findRole($role);
        $data = $this->validated($request);
        $this->assertUniqueName($data['nombre'], $role->rol_id);

        if ((int) $request->user()->rol_id === (int) $role->rol_id
            && ! in_array('Rol', $data['permisos'] ?? [], true)) {
            throw ValidationException::withMessages(['permisos' => 'Conserva el permiso de Gestión de roles para tu propio rol.']);
        }

        DB::transaction(function () use ($role, $data): void {
            DB::table('ccyf_roles')->where('rol_id', $role->rol_id)
                ->update(['rol_nom' => trim($data['nombre']), 'updated_at' => now()]);
            $this->syncPermissions($role->rol_id, $data['permisos'] ?? []);
        });

        return back()->with('status', 'Rol y permisos actualizados.');
    }

    public function toggle(int $role, Request $request, LegacyMenu $menu): RedirectResponse
    {
        $this->authorizeRoles($request, $menu);
        $role = $this->findRole($role);
        if ($role->est && (int) $request->user()->rol_id === (int) $role->rol_id) {
            throw ValidationException::withMessages(['rol' => 'No puedes desactivar tu propio rol.']);
        }
        if ($role->est && DB::table('ccyf_usuarios')->where('rol_id', $role->rol_id)->where('est', true)->exists()) {
            throw ValidationException::withMessages(['rol' => 'Reasigna o desactiva las cuentas activas antes de desactivar este rol.']);
        }

        DB::table('ccyf_roles')->where('rol_id', $role->rol_id)
            ->update(['est' => ! $role->est, 'updated_at' => now()]);
        return back()->with('status', $role->est ? 'Rol desactivado.' : 'Rol activado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:80'],
            'permisos' => ['nullable', 'array'],
            'permisos.*' => ['string', Rule::in(array_keys(CcyfPermissions::MODULES))],
        ]);
    }

    private function syncPermissions(int $role, array $permissions): void
    {
        DB::table('ccyf_role_permissions')->where('rol_id', $role)->delete();
        foreach (array_unique($permissions) as $key) {
            DB::table('ccyf_role_permissions')->insert([
                'rol_id' => $role, 'menu_key' => $key, 'allowed' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function assertUniqueName(string $name, ?int $except = null): void
    {
        if (DB::table('ccyf_roles')->whereRaw('LOWER(rol_nom) = ?', [mb_strtolower(trim($name))])
            ->when($except, fn ($query) => $query->where('rol_id', '!=', $except))->exists()) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe un rol con ese nombre.']);
        }
    }

    private function findRole(int $id): object
    {
        $role = DB::table('ccyf_roles')->where('rol_id', $id)->first();
        abort_unless($role, 404);
        return $role;
    }

    private function authorizeRoles(Request $request, LegacyMenu $menu): void
    {
        abort_unless($menu->allows($request->user(), 'Rol'), 403);
    }
}
