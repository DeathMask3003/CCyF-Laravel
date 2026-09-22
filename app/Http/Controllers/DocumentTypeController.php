<?php

namespace App\Http\Controllers;

use App\Services\LegacyMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DocumentTypeController extends Controller
{
    public function index(Request $request, LegacyMenu $menu): View
    {
        $this->authorizeTypes($request, $menu);

        $types = DB::table('ccyf_tipos_documento')->orderByDesc('activo')->orderBy('nombre')->get();
        $legacyUsage = DB::connection('legacy')->table('tm_documento')
            ->whereNotNull('tipo_id')->selectRaw('tipo_id, count(*) as total')
            ->groupBy('tipo_id')->pluck('total', 'tipo_id');

        return view('tipos.index', compact('types', 'legacyUsage'));
    }

    public function store(Request $request, LegacyMenu $menu): RedirectResponse
    {
        $this->authorizeTypes($request, $menu);
        $data = $request->validate(['nombre' => ['required', 'string', 'max:50']]);
        $name = trim($data['nombre']);
        $this->assertNameIsNotBlank($name);
        $this->assertUniqueName($name);

        DB::table('ccyf_tipos_documento')->insert([
            'nombre' => $name,
            'nombre_clave' => mb_strtolower($name),
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Tipo de documento agregado.');
    }

    public function update(int $type, Request $request, LegacyMenu $menu): RedirectResponse
    {
        $this->authorizeTypes($request, $menu);
        $this->findType($type);
        $data = $request->validate(['nombre' => ['required', 'string', 'max:50']]);
        $name = trim($data['nombre']);
        $this->assertNameIsNotBlank($name);
        $this->assertUniqueName($name, $type);

        DB::table('ccyf_tipos_documento')->where('id', $type)
            ->update(['nombre' => $name, 'nombre_clave' => mb_strtolower($name), 'updated_at' => now()]);

        return back()->with('status', 'Tipo de documento actualizado.');
    }

    public function toggle(int $type, Request $request, LegacyMenu $menu): RedirectResponse
    {
        $this->authorizeTypes($request, $menu);
        $record = $this->findType($type);

        if ($record->activo && DB::table('ccyf_tipos_documento')->where('activo', true)->count() <= 1) {
            throw ValidationException::withMessages([
                'tipo' => 'Debe quedar al menos un tipo activo para registrar oficios.',
            ]);
        }

        DB::table('ccyf_tipos_documento')->where('id', $type)
            ->update(['activo' => ! $record->activo, 'updated_at' => now()]);

        return back()->with('status', $record->activo ? 'Tipo desactivado.' : 'Tipo activado.');
    }

    private function authorizeTypes(Request $request, LegacyMenu $menu): void
    {
        abort_unless($menu->allows($request->user(), 'Tipo'), 403);
    }

    private function findType(int $id): object
    {
        $type = DB::table('ccyf_tipos_documento')->where('id', $id)->first();
        abort_unless($type, 404);

        return $type;
    }

    private function assertUniqueName(string $name, ?int $except = null): void
    {
        $matches = DB::table('ccyf_tipos_documento')
            ->when($except, fn ($query) => $query->where('id', '!=', $except))
            ->where('nombre_clave', mb_strtolower($name))
            ->exists();

        if ($matches) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe un tipo de documento con ese nombre.']);
        }
    }

    private function assertNameIsNotBlank(string $name): void
    {
        if ($name === '') {
            throw ValidationException::withMessages(['nombre' => 'Escribe el nombre del tipo de documento.']);
        }
    }
}
