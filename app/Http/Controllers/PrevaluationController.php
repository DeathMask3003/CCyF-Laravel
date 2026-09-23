<?php

namespace App\Http\Controllers;

use App\Services\LegacyMenu;
use App\Services\DocumentFiles;
use App\Services\InstitutionalPdfHeader;
use App\Services\PrevaluationRecords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

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
            'comparar' => ['nullable', Rule::in(['1'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $all = $records->all();
        $service = $request->query('servicio', 'cafeteria');
        $current = $this->currentCall($service, $all);
        $isAdmin = $menu->allows($request->user(), 'Prevaluaciones_admin');
        $isEvaluator = $menu->allows($request->user(), 'prevaluacion');
        $showEvaluated = $isAdmin && $request->query('estado') === 'evaluado';
        $userIds = $this->userIds($request);
        $available = $all->filter(fn ($item) => $item->servicio === $service && $this->belongsToCall($item, $current));
        $campuses = $current?->id ? DB::table('ccyf_convocatoria_planteles as link')
            ->join('ccyf_planteles as p', 'p.id', '=', 'link.plantel_id')
            ->where('link.convocatoria_id', $current->id)->orderBy('p.nombre')->pluck('p.nombre') : collect();
        if ($campuses->isEmpty()) $campuses = $available->pluck('plantel')->filter()->unique()->sort()->values();
        $available = $available->filter(fn ($item) => $campuses->contains($item->plantel));
        $plantel = $campuses->contains($request->query('plantel')) ? $request->query('plantel') : null;
        $filtered = $plantel ? $available->where('plantel', $plantel) : $available;
        if ($isEvaluator && ! $isAdmin) {
            $filtered = $filtered->filter(fn ($item) => ! $item->evaluado && (! $item->owner || in_array((int) $item->owner, $userIds, true)));
        } else {
            $filtered = $filtered->filter(fn ($item) => $item->evaluado === $showEvaluated);
        }
        if ($request->filled('buscar')) {
            $term = mb_strtolower(trim((string) $request->query('buscar')));
            $filtered = $filtered->filter(fn ($item) => collect([$item->nombre, $item->curp, $item->plantel, $item->convocatoria, $item->registro_id])
                ->contains(fn ($value) => str_contains(mb_strtolower((string) $value), $term)));
        }
        $filtered = $filtered->values();
        $page = max(1, LengthAwarePaginator::resolveCurrentPage());
        $rows = new LengthAwarePaginator($filtered->forPage($page, 20)->values(), $filtered->count(), 20, $page, [
            'path' => $request->url(), 'query' => $request->except(['registro', 'comparar']),
        ]);

        $selected = $request->filled('registro') ? $available->firstWhere('key', $request->query('registro')) : null;
        if ($request->filled('registro')) abort_unless($selected, 404);
        $detail = $selected ? $records->detail($selected) : null;
        $canEvaluate = $selected && $isEvaluator && ! $selected->evaluado && $selected->owner
            && in_array((int) $selected->owner, $userIds, true);
        $comparison = $selected ? $this->comparison($records, $all, $selected) : collect();
        $priceAnalysis = $request->query('comparar') === '1' && $plantel
            ? $this->priceAnalysis($records, $available->where('plantel', $plantel)) : null;

        return view('prevaluaciones.index', compact('rows', 'selected', 'detail', 'service', 'current', 'campuses', 'plantel', 'comparison', 'priceAnalysis', 'isAdmin', 'isEvaluator', 'canEvaluate') + [
            'totalCafe' => $this->visibleCount($all, 'cafeteria', $isAdmin, $showEvaluated, $userIds),
            'totalFoto' => $this->visibleCount($all, 'fotocopiado', $isAdmin, $showEvaluated, $userIds),
        ]);
    }

    public function claim(string $key, Request $request, LegacyMenu $menu, PrevaluationRecords $records): RedirectResponse
    {
        abort_unless($menu->allows($request->user(), 'prevaluacion'), 403);
        $record = $this->record($records, $key);
        abort_unless($this->belongsToCall($record, $this->currentCall($record->servicio, $records->all())), 404);
        abort_if($record->evaluado, 409, 'Este expediente ya fue prevaluado.');
        abort_if($record->owner && ! in_array((int) $record->owner, $this->userIds($request), true),
            409, 'Otro prevaluador ya tomó este expediente.');
        DB::transaction(function () use ($record, $request): void {
            DB::table('ccyf_prevaluaciones')->insertOrIgnore([
                'origen' => $record->origen, 'registro_id' => $record->registro_id,
                'evaluador_id' => $request->user()->getKey(), 'resultado' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $claim = DB::table('ccyf_prevaluaciones')->where('origen', $record->origen)
                ->where('registro_id', $record->registro_id)->lockForUpdate()->first();
            abort_unless($claim && (int) $claim->evaluador_id === (int) $request->user()->getKey()
                && $claim->resultado === null, 409, 'Otro prevaluador ya tomó o terminó este expediente.');
        });
        return redirect()->route('prevaluaciones.index', $this->returnQuery($request, $record));
    }

    public function release(string $key, Request $request, LegacyMenu $menu, PrevaluationRecords $records): RedirectResponse
    {
        abort_unless($menu->allows($request->user(), 'prevaluacion'), 403);
        $record = $this->record($records, $key);
        DB::transaction(function () use ($record, $request): void {
            $claim = DB::table('ccyf_prevaluaciones')->where('origen', $record->origen)
                ->where('registro_id', $record->registro_id)->lockForUpdate()->first();
            abort_unless($claim && (int) $claim->evaluador_id === (int) $request->user()->getKey()
                && $claim->resultado === null, 403);
            DB::table('ccyf_prevaluaciones')->where('id', $claim->id)->delete();
        });
        return redirect()->route('prevaluaciones.index', $request->only(['servicio', 'plantel', 'buscar', 'page']))
            ->with('status', 'Expediente liberado para otro prevaluador.');
    }

    public function save(string $key, Request $request, LegacyMenu $menu, PrevaluationRecords $records): RedirectResponse
    {
        abort_unless($menu->allows($request->user(), 'prevaluacion'), 403);
        $record = $this->record($records, $key);
        $detail = $records->detail($record);
        abort_if($record->evaluado, 409, 'Este expediente ya fue prevaluado.');
        abort_unless($record->owner && in_array((int) $record->owner, $this->userIds($request), true), 403);

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
            $claim = DB::table('ccyf_prevaluaciones')->where('origen', $record->origen)
                ->where('registro_id', $record->registro_id)->lockForUpdate()->first();
            abort_unless($claim && (int) $claim->evaluador_id === (int) $request->user()->getKey()
                && $claim->resultado === null, 409, 'La reserva cambió o el expediente ya terminó.');
            $id = $claim->id;
            foreach ($data['items'] as $keyItem => $item) {
                DB::table('ccyf_prevaluacion_items')->upsert(
                    [['prevaluacion_id' => $id, 'clave' => $keyItem,
                        'cumple' => isset($item['cumple']) && $item['cumple'] !== '' ? (int) $item['cumple'] : null,
                        'comentario' => trim((string) ($item['comentario'] ?? '')),
                        'created_at' => now(), 'updated_at' => now()]],
                    ['prevaluacion_id', 'clave'], ['cumple', 'comentario', 'updated_at'],
                );
            }
            DB::table('ccyf_prevaluaciones')->where('id', $id)->update([
                'resultado' => $data['resultado'] ?? null, 'updated_at' => now(),
            ]);
        });

        $query = $this->returnQuery($request, $record);
        if (isset($data['resultado'])) unset($query['registro']);
        return redirect()->route('prevaluaciones.index', $query)->with('status',
            isset($data['resultado']) ? 'Prevaluación finalizada.' : 'Avance guardado. El expediente sigue reservado para ti.');
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

    public function file(string $key, string $field, Request $request, LegacyMenu $menu, PrevaluationRecords $records, DocumentFiles $files): BinaryFileResponse
    {
        $this->authorizeView($request, $menu);
        $record = $this->record($records, $key);
        abort_unless($records->documents($record)->contains('clave', $field), 404);
        $file = $files->effective($record->origen, $record->registro_id, $field);
        abort_unless($file, 404);
        $inline = in_array($file['mime'], ['application/pdf', 'image/jpeg', 'image/png'], true);
        $response = $inline ? response()->file($file['path'], ['Content-Type' => $file['mime']])
            : response()->download($file['path'], $file['name'], ['Content-Type' => $file['mime']]);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        return $response;
    }

    public function report(string $key, Request $request, LegacyMenu $menu, PrevaluationRecords $records)
    {
        $this->authorizeView($request, $menu);
        $record = $this->record($records, $key);
        $detail = $records->detail($record);
        $owner = $detail['owner'];
        $evaluator = $owner ? DB::table('ccyf_usuarios')
            ->where('usu_id', $owner)->orWhere('legacy_usu_id', $owner)->value('usu_area') : null;
        if (! $evaluator && $owner && $record->origen === 'historico') {
            $evaluator = DB::connection('legacy')->table('tm_usuario')->where('usu_id', $owner)->value('usu_area');
        }

        $html = view('prevaluaciones.report', compact('record', 'detail', 'evaluator'))->render();
        $temp = storage_path('app/mpdf');
        File::ensureDirectoryExists($temp);
        $pdf = new Mpdf([
            'mode' => 'utf-8', 'format' => 'A4', 'tempDir' => $temp,
            'margin_left' => 12, 'margin_right' => 12, 'margin_top' => 31,
            'margin_bottom' => 18, 'margin_header' => 6,
            'default_font' => 'dejavusans',
        ]);
        $pdf->SetTitle('Prevaluación de precios · '.$record->nombre);
        $pdf->SetAuthor('CCyF CoBaEMex');
        InstitutionalPdfHeader::apply($pdf, 185);
        $pdf->SetHTMLFooter('<div style="border-top:1px solid #d8c9ce;padding-top:5px;color:#70656a;font-size:7pt;text-align:right">CCyF · Página {PAGENO} de {nbpg}</div>');
        $pdf->WriteHTML($html);
        $bytes = $pdf->Output('', Destination::STRING_RETURN);

        $response = response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Prevaluacion_'.$record->servicio.'_'.$record->registro_id.'.pdf"',
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

    private function currentCall(string $service, $all): ?object
    {
        $legacyService = $service === 'cafeteria' ? 3 : 4;
        $call = DB::table('ccyf_convocatorias as c')->join('ccyf_tipos_servicio as s', 's.id', '=', 'c.servicio_id')
            ->where('s.legacy_trami_id', $legacyService)
            ->orderByDesc('c.activo')->orderByDesc('c.created_at')->orderByDesc('c.id')
            ->first(['c.id', 'c.legacy_cat_id', 'c.numero', 'c.activo']);
        if ($call) return $call;
        $fallback = $all->where('servicio', $service)->sortByDesc('convocatoria_id')->first();
        return $fallback ? (object) ['id' => $fallback->origen === 'actual' ? $fallback->convocatoria_id : null,
            'legacy_cat_id' => $fallback->origen === 'historico' ? $fallback->convocatoria_id : null,
            'numero' => $fallback->convocatoria, 'activo' => false] : null;
    }

    private function belongsToCall(object $item, ?object $call): bool
    {
        if (! $call) return false;
        $id = $item->origen === 'historico' ? $call->legacy_cat_id : $call->id;
        return $id !== null && (int) $item->convocatoria_id === (int) $id;
    }

    private function visibleCount($all, string $service, bool $isAdmin, bool $showEvaluated, array $userIds): int
    {
        $call = $this->currentCall($service, $all);
        return $all->filter(fn ($item) => $item->servicio === $service && $this->belongsToCall($item, $call)
            && ($isAdmin ? $item->evaluado === $showEvaluated
                : (! $item->evaluado && (! $item->owner || in_array((int) $item->owner, $userIds, true)))))->count();
    }

    private function userIds(Request $request): array
    {
        return array_values(array_unique(array_filter([(int) $request->user()->getKey(), (int) $request->user()->legacy_usu_id])));
    }

    private function priceAnalysis(PrevaluationRecords $records, $peers): array
    {
        $offers = collect();
        $participants = $peers->map(function ($peer) use ($records, $offers): array {
            $prices = collect();
            foreach ($records->prices($peer) as $price) {
                if (! is_numeric($price->precio) || (float) $price->precio < 0) continue;
                $prices[$price->nombre] = (float) $price->precio;
                $offers->push(['item' => $price->nombre, 'price' => (float) $price->precio,
                    'participant' => $peer->nombre ?: 'Sin nombre']);
            }
            return ['name' => $peer->nombre ?: 'Sin nombre', 'key' => $peer->key,
                'total' => $prices->sum(), 'count' => $prices->count()];
        });
        $items = $offers->groupBy('item')->map(function ($rows, $name): array {
            $minimum = $rows->min('price');
            return ['name' => $name, 'minimum' => $minimum,
                'winners' => $rows->where('price', $minimum)->pluck('participant')->unique()->values()];
        })->sortKeys()->values();
        $expected = $items->count();
        $ranked = $participants->map(fn ($row) => $row + ['complete' => $expected > 0 && $row['count'] === $expected])
            ->sort(fn ($a, $b) => ($b['complete'] <=> $a['complete']) ?: ($a['total'] <=> $b['total']) ?: strcmp($a['name'], $b['name']))
            ->values();
        return ['participants' => $ranked, 'items' => $items, 'expected' => $expected];
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
