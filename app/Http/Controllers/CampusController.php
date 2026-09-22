<?php

namespace App\Http\Controllers;

use App\Services\CcyfStructure;
use App\Services\LegacyMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CampusController extends Controller
{
    public function index(Request $request, LegacyMenu $menu): View
    {
        $this->authorizeCampuses($request, $menu);
        $search = trim((string) $request->query('q'));
        $campuses = DB::table('ccyf_planteles')
            ->when($search !== '', fn ($query) => $query->where(function ($nested) use ($search): void {
                $nested->where('nombre', 'like', "%{$search}%")
                    ->orWhere('correo', 'like', "%{$search}%")
                    ->orWhere('direccion', 'like', "%{$search}%");
            }))
            ->orderByDesc('activo')->orderBy('nombre')->paginate(12)->withQueryString();

        return view('planteles.index', compact('campuses', 'search'));
    }

    public function create(Request $request, LegacyMenu $menu): View
    {
        $this->authorizeCampuses($request, $menu);
        $campus = null;
        $services = DB::table('ccyf_tipos_servicio')->where('activo', true)->orderBy('nombre')->get();
        $conditions = collect();

        return view('planteles.form', compact('campus', 'services', 'conditions'));
    }

    public function store(Request $request, LegacyMenu $menu, CcyfStructure $structure): RedirectResponse
    {
        $this->authorizeCampuses($request, $menu);
        $data = $this->validateCampus($request);
        $name = trim($data['nombre']);
        $this->assertUniqueName($structure->key($name));

        $campusId = DB::transaction(function () use ($data, $name, $structure): int {
            $id = DB::table('ccyf_planteles')->insertGetId([
                'nombre' => $name,
                'nombre_clave' => $structure->key($name),
                'correo' => trim($data['correo'] ?? '') ?: null,
                'direccion' => trim($data['direccion'] ?? '') ?: null,
                'activo' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->syncServices($id, $data['servicios'] ?? []);
            return $id;
        });

        return redirect()->route('planteles.edit', $campusId)->with('status', 'Plantel agregado correctamente.');
    }

    public function edit(int $campus, Request $request, LegacyMenu $menu): View
    {
        $this->authorizeCampuses($request, $menu);
        $campus = $this->findCampus($campus);
        $services = DB::table('ccyf_tipos_servicio')->where('activo', true)->orderBy('nombre')->get();
        $conditions = DB::table('ccyf_plantel_servicios')->where('plantel_id', $campus->id)
            ->get()->keyBy('servicio_id');

        return view('planteles.form', compact('campus', 'services', 'conditions'));
    }

    public function update(int $campus, Request $request, LegacyMenu $menu, CcyfStructure $structure): RedirectResponse
    {
        $this->authorizeCampuses($request, $menu);
        $record = $this->findCampus($campus);
        $data = $this->validateCampus($request);
        $name = trim($data['nombre']);
        $this->assertUniqueName($structure->key($name), $record->id);

        DB::transaction(function () use ($record, $data, $name, $structure): void {
            DB::table('ccyf_planteles')->where('id', $record->id)->update([
                'nombre' => $name,
                'nombre_clave' => $structure->key($name),
                'correo' => trim($data['correo'] ?? '') ?: null,
                'direccion' => trim($data['direccion'] ?? '') ?: null,
                'updated_at' => now(),
            ]);
            $this->syncServices($record->id, $data['servicios'] ?? []);
        });

        return back()->with('status', 'Datos del plantel actualizados.');
    }

    public function toggle(int $campus, Request $request, LegacyMenu $menu): RedirectResponse
    {
        $this->authorizeCampuses($request, $menu);
        $record = $this->findCampus($campus);
        DB::table('ccyf_planteles')->where('id', $record->id)
            ->update(['activo' => ! $record->activo, 'updated_at' => now()]);

        return back()->with('status', $record->activo ? 'Plantel desactivado.' : 'Plantel activado.');
    }

    private function validateCampus(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'correo' => ['nullable', 'email', 'max:100'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'servicios' => ['nullable', 'array'],
            'servicios.*.habilitado' => ['nullable', 'boolean'],
            'servicios.*.espacio' => ['nullable', 'string', 'max:50'],
            'servicios.*.matricula' => ['nullable', 'integer', 'between:0,999999'],
            'servicios.*.monto' => ['nullable', 'numeric', 'between:0,999999999.99', 'decimal:0,2'],
            'servicios.*.garantia' => ['nullable', 'numeric', 'between:0,999999999.99', 'decimal:0,2'],
        ]);
    }

    private function syncServices(int $campusId, array $submitted): void
    {
        $allowed = DB::table('ccyf_tipos_servicio')->where('activo', true)->pluck('id')->map(fn ($id) => (int) $id);
        DB::table('ccyf_plantel_servicios')->where('plantel_id', $campusId)
            ->whereIn('servicio_id', $allowed)->delete();
        foreach ($submitted as $serviceId => $values) {
            if (! $allowed->contains((int) $serviceId) || empty($values['habilitado'])) {
                continue;
            }
            DB::table('ccyf_plantel_servicios')->insert([
                'plantel_id' => $campusId,
                'servicio_id' => $serviceId,
                'espacio' => trim($values['espacio'] ?? '') ?: null,
                'matricula' => $values['matricula'] ?? null,
                'monto' => $values['monto'] ?? null,
                'garantia' => $values['garantia'] ?? null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function authorizeCampuses(Request $request, LegacyMenu $menu): void
    {
        abort_unless($menu->allows($request->user(), 'Areas'), 403);
    }

    private function findCampus(int $id): object
    {
        $campus = DB::table('ccyf_planteles')->where('id', $id)->first();
        abort_unless($campus, 404);
        return $campus;
    }

    private function assertUniqueName(string $key, ?int $except = null): void
    {
        $exists = DB::table('ccyf_planteles')->when($except, fn ($query) => $query->where('id', '!=', $except))
            ->where('nombre_clave', $key)->exists();
        if ($exists) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe un plantel con ese nombre.']);
        }
    }
}
