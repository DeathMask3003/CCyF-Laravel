<?php

namespace App\Http\Controllers;

use App\Services\LegacyMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CcyfUserController extends Controller
{
    public function index(Request $request, LegacyMenu $menu): View
    {
        $this->authorizeUsers($request, $menu);
        $search = trim((string) $request->query('q'));
        $roleFilter = (int) $request->query('rol', 0);
        $status = (string) $request->query('estado', '');
        $users = DB::table('ccyf_usuarios as usuario')
            ->leftJoin('ccyf_roles as role', 'role.rol_id', '=', 'usuario.rol_id')
            ->select('usuario.*', 'role.rol_nom', 'role.est as rol_activo')
            ->when($search !== '', fn ($query) => $query->where(function ($nested) use ($search): void {
                $nested->where('usuario.usu_area', 'like', "%{$search}%")
                    ->orWhere('usuario.usu_correo', 'like', "%{$search}%")
                    ->orWhere('usuario.usu_telf', 'like', "%{$search}%");
            }))
            ->when($roleFilter > 0, fn ($query) => $query->where('usuario.rol_id', $roleFilter))
            ->when(in_array($status, ['1', '0'], true), fn ($query) => $query->where('usuario.est', $status))
            ->orderByDesc('usuario.est')->orderBy('usuario.usu_area')
            ->paginate(15)->withQueryString();
        $roles = DB::table('ccyf_roles')->orderByDesc('est')->orderBy('rol_nom')->get();
        $summary = DB::table('ccyf_usuarios')->selectRaw('COUNT(*) as total, SUM(CASE WHEN est = 1 THEN 1 ELSE 0 END) as activos')->first();

        return view('usuarios.index', compact('users', 'roles', 'search', 'roleFilter', 'status', 'summary'));
    }

    public function create(Request $request, LegacyMenu $menu): View
    {
        $this->authorizeUsers($request, $menu);
        return $this->form(null);
    }

    public function store(Request $request, LegacyMenu $menu): RedirectResponse
    {
        $this->authorizeUsers($request, $menu);
        $data = $this->validated($request, false);
        $this->assertUniqueEmail($data['correo']);
        $this->assertArea($data['area_id'] ?? null);

        $id = DB::table('ccyf_usuarios')->insertGetId([
            'usu_area' => trim($data['nombre']), 'usu_correo' => mb_strtolower(trim($data['correo'])),
            'usu_telf' => trim($data['telefono'] ?? '') ?: null,
            'area_id' => $data['area_id'] ?? null, 'rol_id' => $data['rol_id'],
            'usu_pass' => Hash::make($data['password']), 'est' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'usu_id');

        return redirect()->route('usuarios.edit', $id)->with('status', 'Cuenta creada. Ya puede iniciar sesión con su contraseña y el código enviado por correo.');
    }

    public function edit(int $user, Request $request, LegacyMenu $menu): View
    {
        $this->authorizeUsers($request, $menu);
        return $this->form($this->findUser($user));
    }

    public function update(int $user, Request $request, LegacyMenu $menu): RedirectResponse
    {
        $this->authorizeUsers($request, $menu);
        $user = $this->findUser($user);
        $data = $this->validated($request, true);
        $this->assertUniqueEmail($data['correo'], $user->usu_id);
        $this->assertArea($data['area_id'] ?? null, $user->area_id);
        if ((int) $request->user()->getKey() === (int) $user->usu_id
            && (int) $data['rol_id'] !== (int) $user->rol_id) {
            throw ValidationException::withMessages(['rol_id' => 'No puedes cambiar tu propio rol.']);
        }

        $values = [
            'usu_area' => trim($data['nombre']), 'usu_correo' => mb_strtolower(trim($data['correo'])),
            'usu_telf' => trim($data['telefono'] ?? '') ?: null,
            'area_id' => $data['area_id'] ?? null, 'rol_id' => $data['rol_id'],
            'updated_at' => now(),
        ];
        if (! empty($data['password'])) {
            $values['usu_pass'] = Hash::make($data['password']);
        }
        DB::table('ccyf_usuarios')->where('usu_id', $user->usu_id)->update($values);

        return back()->with('status', 'Datos de la cuenta actualizados.');
    }

    public function toggle(int $user, Request $request, LegacyMenu $menu): RedirectResponse
    {
        $this->authorizeUsers($request, $menu);
        $user = $this->findUser($user);
        if ($user->est && (int) $request->user()->getKey() === (int) $user->usu_id) {
            throw ValidationException::withMessages(['usuario' => 'No puedes desactivar tu propia cuenta.']);
        }
        if ($user->est && $this->isLastAdministrator($user)) {
            throw ValidationException::withMessages(['usuario' => 'Debe quedar al menos una cuenta activa con acceso a Gestión de usuarios.']);
        }
        if (! $user->est && ! DB::table('ccyf_roles')->where('rol_id', $user->rol_id)->where('est', true)->exists()) {
            throw ValidationException::withMessages(['usuario' => 'Activa o cambia el rol antes de reactivar esta cuenta.']);
        }

        DB::table('ccyf_usuarios')->where('usu_id', $user->usu_id)
            ->update(['est' => ! $user->est, 'updated_at' => now()]);
        return back()->with('status', $user->est ? 'Cuenta desactivada.' : 'Cuenta activada.');
    }

    private function form(?object $user): View
    {
        $roles = DB::table('ccyf_roles')->where('est', true)->orderBy('rol_nom')->get();
        $areas = DB::connection('legacy')->table('tm_areas')
            ->where(fn ($query) => $query->where('est', 1)->when($user?->area_id, fn ($query) => $query->orWhere('area_id', $user->area_id)))
            ->orderBy('area_nom')->get(['area_id', 'area_nom', 'est']);
        return view('usuarios.form', compact('user', 'roles', 'areas'));
    }

    private function validated(Request $request, bool $editing): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'correo' => ['required', 'email', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'area_id' => ['nullable', 'integer', 'min:1'],
            'rol_id' => ['required', 'integer', Rule::exists('ccyf_roles', 'rol_id')->where('est', 1)],
            'password' => [$editing ? 'nullable' : 'required', 'string', 'min:10', 'max:255', 'confirmed'],
        ]);
    }

    private function assertUniqueEmail(string $email, ?int $except = null): void
    {
        if ($except) {
            $current = DB::table('ccyf_usuarios')->where('usu_id', $except)->value('usu_correo');
            if (mb_strtolower((string) $current) === mb_strtolower(trim($email))) {
                return;
            }
        }
        if (DB::table('ccyf_usuarios')->whereRaw('LOWER(usu_correo) = ?', [mb_strtolower(trim($email))])
            ->when($except, fn ($query) => $query->where('usu_id', '!=', $except))->exists()) {
            throw ValidationException::withMessages(['correo' => 'El correo ya está asociado a otra cuenta.']);
        }
    }

    private function assertArea(?int $area, ?int $current = null): void
    {
        if ($area && $area === $current) {
            return;
        }
        if ($area && ! DB::connection('legacy')->table('tm_areas')->where('area_id', $area)->where('est', 1)->exists()) {
            throw ValidationException::withMessages(['area_id' => 'Selecciona un plantel o departamento válido.']);
        }
    }

    private function isLastAdministrator(object $user): bool
    {
        $roleIds = DB::table('ccyf_role_permissions')->where('menu_key', 'Usuarios')->where('allowed', true)->pluck('rol_id');
        if (! $roleIds->contains($user->rol_id)) {
            return false;
        }
        return DB::table('ccyf_usuarios')->where('est', true)->whereIn('rol_id', $roleIds)
            ->where('usu_id', '!=', $user->usu_id)->count() === 0;
    }

    private function findUser(int $id): object
    {
        $user = DB::table('ccyf_usuarios')->where('usu_id', $id)->first();
        abort_unless($user, 404);
        return $user;
    }

    private function authorizeUsers(Request $request, LegacyMenu $menu): void
    {
        abort_unless($menu->allows($request->user(), 'Usuarios'), 403);
    }
}
