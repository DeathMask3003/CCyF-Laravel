<?php

namespace App\Http\Controllers;

use App\Services\LegacyMenu;
use App\Services\PrevaluationRecords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PrevaluationController extends Controller
{
    public function index(Request $request, LegacyMenu $menu, PrevaluationRecords $records): View
    {
        $this->authorizeView($request, $menu);
        $request->validate([
            'servicio' => ['nullable', Rule::in(['cafeteria', 'fotocopiado'])],
            'convocatoria' => ['nullable', 'integer', 'min:1'],
            'plantel' => ['nullable', 'string', 'max:150'],
            'buscar' => ['nullable', 'string', 'max:150'],
            'estado' => ['nullable', Rule::in(['pendiente', 'evaluado'])],
            'registro' => ['nullable', 'regex:/^(historico|actual)-[1-9]\d*$/'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $all = $records->all();
        $service = $request->query('servicio', 'cafeteria');
        $filtered = $all->where('servicio', $service);
        if ($request->filled('convocatoria')) $filtered = $filtered->where('convocatoria_id', (int) $request->query('convocatoria'));
        if ($request->filled('plantel')) $filtered = $filtered->where('plantel', (string) $request->query('plantel'));
        if ($request->filled('estado')) $filtered = $filtered->filter(fn ($item) => $item->evaluado === ($request->query('estado') === 'evaluado'));
        if ($request->filled('buscar')) {
            $term = mb_strtolower(trim((string) $request->query('buscar')));
            $filtered = $filtered->filter(fn ($item) => collect([$item->nombre, $item->curp, $item->plantel, $item->convocatoria, $item->registro_id])
                ->contains(fn ($value) => str_contains(mb_strtolower((string) $value), $term)));
        }
        $filtered = $filtered->values();
        $page = max(1, LengthAwarePaginator::resolveCurrentPage());
        $rows = new LengthAwarePaginator($filtered->forPage($page, 20)->values(), $filtered->count(), 20, $page, [
            'path' => $request->url(), 'query' => $request->query(),
        ]);

        $selected = $request->filled('registro') ? $all->firstWhere('key', $request->query('registro')) : null;
        if ($request->filled('registro')) abort_unless($selected, 404);
        $detail = $selected ? $records->detail($selected) : null;
        $isAdmin = $menu->allows($request->user(), 'Prevaluaciones_admin');
        $isEvaluator = $menu->allows($request->user(), 'prevaluacion');
        $owner = $detail['owner'] ?? null;
        $userLegacyId = (int) ($request->user()->legacy_usu_id ?: $request->user()->getKey());
        $canEvaluate = $selected && $isEvaluator && (! $owner || $owner === $userLegacyId || $owner === (int) $request->user()->getKey());

        $available = $all->where('servicio', $service);
        $convocations = $available->unique('convocatoria_id')->sortByDesc('convocatoria_id')->values();
        $campuses = $available->filter(fn ($item) => ! $request->filled('convocatoria') || $item->convocatoria_id === (int) $request->query('convocatoria'))
            ->pluck('plantel')->filter()->unique()->sort()->values();
        $comparison = $selected ? $this->comparison($records, $all, $selected) : collect();

        return view('prevaluaciones.index', compact('rows', 'selected', 'detail', 'service', 'convocations', 'campuses', 'comparison', 'isAdmin', 'canEvaluate') + [
            'totalCafe' => $all->where('servicio', 'cafeteria')->count(),
            'totalFoto' => $all->where('servicio', 'fotocopiado')->count(),
        ]);
    }

    public function save(string $key, Request $request, LegacyMenu $menu, PrevaluationRecords $records): RedirectResponse
    {
        abort_unless($menu->allows($request->user(), 'prevaluacion'), 403);
        $record = $this->record($records, $key);
        $detail = $records->detail($record);
        $owner = $detail['owner'];
        $userLegacyId = (int) ($request->user()->legacy_usu_id ?: $request->user()->getKey());
        abort_if($owner && $owner !== $userLegacyId && $owner !== (int) $request->user()->getKey(), 403);

        $allowed = $detail['documents']->pluck('clave')->merge($detail['prices']->pluck('clave'))->all();
        $data = $request->validate([
            'resultado' => ['nullable', Rule::in(['1', '2', '3'])],
            'items' => ['required', 'array'],
        ]);
        if (array_diff(array_keys($data['items']), $allowed)) {
            throw ValidationException::withMessages(['items' => 'Se recibieron criterios ajenos a este expediente.']);
        }
        foreach ($data['items'] as $keyItem => $item) {
            if (! is_array($item)) throw ValidationException::withMessages(['items' => 'Revisa los criterios de la evaluación.']);
            validator($item, [
                'cumple' => ['nullable', Rule::in(['1', '0'])],
                'comentario' => ['nullable', 'string', 'max:1000'],
            ])->validate();
        }

        DB::transaction(function () use ($record, $request, $data): void {
            $existing = DB::table('ccyf_prevaluaciones')->where('origen', $record->origen)->where('registro_id', $record->registro_id)->first();
            if ($existing) {
                $id = $existing->id;
                DB::table('ccyf_prevaluaciones')->where('id', $id)->update([
                    'resultado' => $data['resultado'] ?? null, 'updated_at' => now(),
                ]);
            } else {
                $id = DB::table('ccyf_prevaluaciones')->insertGetId([
                    'origen' => $record->origen, 'registro_id' => $record->registro_id,
                    'evaluador_id' => $request->user()->getKey(), 'resultado' => $data['resultado'] ?? null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            foreach ($data['items'] as $keyItem => $item) {
                DB::table('ccyf_prevaluacion_items')->upsert(
                    [['prevaluacion_id' => $id, 'clave' => $keyItem,
                        'cumple' => isset($item['cumple']) && $item['cumple'] !== '' ? (int) $item['cumple'] : null,
                        'comentario' => trim((string) ($item['comentario'] ?? '')),
                        'created_at' => now(), 'updated_at' => now()]],
                    ['prevaluacion_id', 'clave'], ['cumple', 'comentario', 'updated_at'],
                );
            }
        });

        return redirect()->route('prevaluaciones.index', $this->returnQuery($request, $record))->with('status', 'Prevaluación guardada correctamente.');
    }

    public function note(string $key, Request $request, LegacyMenu $menu, PrevaluationRecords $records): RedirectResponse
    {
        abort_unless($menu->allows($request->user(), 'Prevaluaciones_admin'), 403);
        $record = $this->record($records, $key);
        $data = $request->validate(['observaciones' => ['required', 'string', 'max:3000']]);
        DB::table('ccyf_prevaluacion_notas')->upsert(
            [['origen' => $record->origen, 'registro_id' => $record->registro_id,
                'observaciones' => trim($data['observaciones']), 'administrador_id' => $request->user()->getKey(),
                'created_at' => now(), 'updated_at' => now()]],
            ['origen', 'registro_id'], ['observaciones', 'administrador_id', 'updated_at'],
        );
        return redirect()->route('prevaluaciones.index', $this->returnQuery($request, $record))->with('status', 'Observación del administrador guardada.');
    }

    public function file(string $key, string $field, Request $request, LegacyMenu $menu, PrevaluationRecords $records): BinaryFileResponse
    {
        $this->authorizeView($request, $menu);
        $record = $this->record($records, $key);
        $path = $records->filePath($record, $field);
        abort_unless($path, 404);
        $response = response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="documento-'.$record->registro_id.'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        return $response;
    }

    private function comparison(PrevaluationRecords $records, $all, object $selected)
    {
        $peers = $all->filter(fn ($item) => $item->servicio === $selected->servicio &&
            $item->convocatoria_id === $selected->convocatoria_id && $item->plantel === $selected->plantel);
        $minimums = collect();
        foreach ($peers as $peer) {
            foreach ($records->prices($peer) as $price) {
                if (! is_numeric($price->precio)) continue;
                $name = $price->nombre;
                $minimums[$name] = min((float) ($minimums[$name] ?? INF), (float) $price->precio);
            }
        }
        return $minimums;
    }

    private function returnQuery(Request $request, object $record): array
    {
        return $request->only(['servicio', 'convocatoria', 'plantel', 'buscar', 'estado', 'page']) + ['registro' => $record->key];
    }

    private function record(PrevaluationRecords $records, string $key): object
    {
        abort_unless(preg_match('/^(historico|actual)-[1-9]\d*$/', $key), 404);
        return $records->find($key) ?? abort(404);
    }

    private function authorizeView(Request $request, LegacyMenu $menu): void
    {
        abort_unless($menu->allows($request->user(), 'prevaluacion') || $menu->allows($request->user(), 'Prevaluaciones_admin'), 403);
    }
}
