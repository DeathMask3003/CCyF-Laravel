<?php

namespace App\Http\Controllers;

use App\Services\LegacyMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ServiceTypeController extends Controller
{
    public function index(Request $request, LegacyMenu $menu): View
    {
        $this->authorizeServices($request, $menu);
        $services = DB::table('ccyf_tipos_servicio')->orderByDesc('activo')->orderBy('nombre')->get();
        $legacyUsage = DB::connection('legacy')->table('tm_documento')
            ->whereNotNull('trami_id')->selectRaw('trami_id, count(*) as total')
            ->groupBy('trami_id')->pluck('total', 'trami_id');
        $catalogUsage = DB::table('ccyf_catalogos')->selectRaw('servicio_id, count(*) as total')
            ->whereNotNull('servicio_id')->groupBy('servicio_id')->pluck('total', 'servicio_id');

        return view('servicios.index', compact('services', 'legacyUsage', 'catalogUsage'));
    }

    public function store(Request $request, LegacyMenu $menu): RedirectResponse
    {
        $this->authorizeServices($request, $menu);
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:50'],
            'descripcion' => ['required', 'string', 'max:200'],
        ]);
        $name = trim($data['nombre']);
        $description = trim($data['descripcion']);
        $this->assertNotBlank($name, $description);
        $this->assertUniqueName($name);

        DB::table('ccyf_tipos_servicio')->insert([
            'nombre' => $name,
            'nombre_clave' => mb_strtolower($name),
            'descripcion' => $description,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Tipo de servicio agregado. Ya puede asignarse a una convocatoria.');
    }

    public function update(int $service, Request $request, LegacyMenu $menu): RedirectResponse
    {
        $this->authorizeServices($request, $menu);
        $this->findService($service);
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:50'],
            'descripcion' => ['required', 'string', 'max:200'],
        ]);
        $name = trim($data['nombre']);
        $description = trim($data['descripcion']);
        $this->assertNotBlank($name, $description);
        $this->assertUniqueName($name, $service);

        DB::table('ccyf_tipos_servicio')->where('id', $service)->update([
            'nombre' => $name,
            'nombre_clave' => mb_strtolower($name),
            'descripcion' => $description,
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Tipo de servicio actualizado.');
    }

    public function toggle(int $service, Request $request, LegacyMenu $menu): RedirectResponse
    {
        $this->authorizeServices($request, $menu);
        $record = $this->findService($service);

        if ($record->activo && DB::table('ccyf_tipos_servicio')->where('activo', true)->count() <= 1) {
            throw ValidationException::withMessages([
                'servicio' => 'Debe quedar al menos un tipo de servicio activo.',
            ]);
        }

        DB::table('ccyf_tipos_servicio')->where('id', $service)
            ->update(['activo' => ! $record->activo, 'updated_at' => now()]);

        return back()->with('status', $record->activo ? 'Tipo de servicio desactivado.' : 'Tipo de servicio activado.');
    }

    private function authorizeServices(Request $request, LegacyMenu $menu): void
    {
        abort_unless($menu->allows($request->user(), 'Asuntos'), 403);
    }

    private function findService(int $id): object
    {
        $service = DB::table('ccyf_tipos_servicio')->where('id', $id)->first();
        abort_unless($service, 404);

        return $service;
    }

    private function assertUniqueName(string $name, ?int $except = null): void
    {
        $exists = DB::table('ccyf_tipos_servicio')
            ->when($except, fn ($query) => $query->where('id', '!=', $except))
            ->where('nombre_clave', mb_strtolower($name))->exists();

        if ($exists) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe un tipo de servicio con ese nombre.']);
        }
    }

    private function assertNotBlank(string $name, string $description): void
    {
        if ($name === '') {
            throw ValidationException::withMessages(['nombre' => 'Escribe el nombre del tipo de servicio.']);
        }
        if ($description === '') {
            throw ValidationException::withMessages(['descripcion' => 'Escribe una descripción breve.']);
        }
    }
}
