<?php

namespace App\Http\Controllers;

use App\Services\CcyfStructure;
use App\Services\LegacyMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ConvocationController extends Controller
{
    public function index(Request $request, LegacyMenu $menu): View
    {
        $this->authorizeConvocations($request, $menu);
        $convocations = DB::table('ccyf_convocatorias as convocatoria')
            ->leftJoin('ccyf_tipos_servicio as servicio', 'servicio.id', '=', 'convocatoria.servicio_id')
            ->select('convocatoria.*', 'servicio.nombre as servicio_nombre')
            ->selectSub(function ($query): void {
                $query->from('ccyf_convocatoria_planteles')
                    ->whereColumn('convocatoria_id', 'convocatoria.id')
                    ->selectRaw('count(*)');
            }, 'planteles_total')
            ->selectSub(function ($query): void {
                $query->from('ccyf_catalogos')
                    ->whereColumn('convocatoria_id', 'convocatoria.id')
                    ->selectRaw('max(id)');
            }, 'catalogo_id')
            ->orderByDesc('convocatoria.id')->get();

        return view('convocatorias.index', compact('convocations'));
    }

    public function create(Request $request, LegacyMenu $menu): View
    {
        $this->authorizeConvocations($request, $menu);
        return $this->formView(null);
    }

    public function store(Request $request, LegacyMenu $menu, CcyfStructure $structure): RedirectResponse
    {
        $this->authorizeConvocations($request, $menu);
        $data = $this->validateConvocation($request);
        $number = trim($data['numero']);
        $this->assertUniqueNumber($structure->key($number));

        $id = DB::transaction(function () use ($data, $number, $structure): int {
            $id = DB::table('ccyf_convocatorias')->insertGetId([
                'numero' => $number,
                'numero_clave' => $structure->key($number),
                'servicio_id' => $data['servicio_id'],
                'activo' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->syncCampuses($id, $data['planteles']);
            return $id;
        });

        return redirect()->route('convocatorias.edit', $id)->with('status', 'Número de convocatoria creado. Ahora puedes preparar sus productos y precios.');
    }

    public function edit(int $convocation, Request $request, LegacyMenu $menu): View
    {
        $this->authorizeConvocations($request, $menu);
        return $this->formView($this->findConvocation($convocation));
    }

    public function update(int $convocation, Request $request, LegacyMenu $menu, CcyfStructure $structure): RedirectResponse
    {
        $this->authorizeConvocations($request, $menu);
        $record = $this->findConvocation($convocation);
        $data = $this->validateConvocation($request);
        $number = trim($data['numero']);
        $this->assertUniqueNumber($structure->key($number), $record->id);

        if ((int) $record->servicio_id !== (int) $data['servicio_id']
            && DB::table('ccyf_catalogos')->where('convocatoria_id', $record->id)->exists()) {
            throw ValidationException::withMessages([
                'servicio_id' => 'No puedes cambiar el servicio porque esta convocatoria ya tiene productos configurados.',
            ]);
        }

        DB::transaction(function () use ($record, $data, $number, $structure): void {
            DB::table('ccyf_convocatorias')->where('id', $record->id)->update([
                'numero' => $number,
                'numero_clave' => $structure->key($number),
                'servicio_id' => $data['servicio_id'],
                'updated_at' => now(),
            ]);
            $this->syncCampuses($record->id, $data['planteles']);
        });

        return back()->with('status', 'Convocatoria actualizada.');
    }

    public function toggle(int $convocation, Request $request, LegacyMenu $menu): RedirectResponse
    {
        $this->authorizeConvocations($request, $menu);
        $record = $this->findConvocation($convocation);
        if (! $record->activo) {
            $serviceReady = $record->servicio_id && DB::table('ccyf_tipos_servicio')
                ->where('id', $record->servicio_id)->where('activo', true)->exists();
            $hasActiveCampus = DB::table('ccyf_convocatoria_planteles as asignacion')
                ->join('ccyf_planteles as plantel', 'plantel.id', '=', 'asignacion.plantel_id')
                ->where('asignacion.convocatoria_id', $record->id)->where('plantel.activo', true)->exists();
            if (! $serviceReady || ! $hasActiveCampus) {
                throw ValidationException::withMessages([
                    'convocatoria' => 'Asigna un servicio activo y al menos un plantel activo antes de habilitar la convocatoria.',
                ]);
            }
        }
        DB::table('ccyf_convocatorias')->where('id', $record->id)
            ->update(['activo' => ! $record->activo, 'updated_at' => now()]);

        return back()->with('status', $record->activo ? 'Convocatoria desactivada.' : 'Convocatoria activada.');
    }

    private function formView(?object $convocation): View
    {
        $services = DB::table('ccyf_tipos_servicio')->where('activo', true)->orderBy('nombre')->get();
        $campuses = DB::table('ccyf_planteles')->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);
        $selected = $convocation ? DB::table('ccyf_convocatoria_planteles')
            ->where('convocatoria_id', $convocation->id)->pluck('plantel_id')->map(fn ($id) => (int) $id)->all() : [];
        $hasCatalog = $convocation ? DB::table('ccyf_catalogos')->where('convocatoria_id', $convocation->id)->exists() : false;

        return view('convocatorias.form', compact('convocation', 'services', 'campuses', 'selected', 'hasCatalog'));
    }

    private function validateConvocation(Request $request): array
    {
        return $request->validate([
            'numero' => ['required', 'string', 'max:100'],
            'servicio_id' => ['required', 'integer', Rule::exists('ccyf_tipos_servicio', 'id')->where('activo', true)],
            'planteles' => ['required', 'array', 'min:1'],
            'planteles.*' => ['integer', Rule::exists('ccyf_planteles', 'id')->where('activo', true)],
        ]);
    }

    private function syncCampuses(int $convocationId, array $campuses): void
    {
        DB::table('ccyf_convocatoria_planteles')->where('convocatoria_id', $convocationId)->delete();
        foreach (array_unique(array_map('intval', $campuses)) as $campusId) {
            DB::table('ccyf_convocatoria_planteles')->insert([
                'convocatoria_id' => $convocationId, 'plantel_id' => $campusId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function authorizeConvocations(Request $request, LegacyMenu $menu): void
    {
        abort_unless($menu->allows($request->user(), 'Categorias_widi'), 403);
    }

    private function findConvocation(int $id): object
    {
        $record = DB::table('ccyf_convocatorias')->where('id', $id)->first();
        abort_unless($record, 404);
        return $record;
    }

    private function assertUniqueNumber(string $key, ?int $except = null): void
    {
        $exists = DB::table('ccyf_convocatorias')->when($except, fn ($query) => $query->where('id', '!=', $except))
            ->where('numero_clave', $key)->exists();
        if ($exists) {
            throw ValidationException::withMessages(['numero' => 'Ya existe una convocatoria con ese número o nombre.']);
        }
    }
}
