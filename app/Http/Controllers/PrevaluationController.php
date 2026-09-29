<?php

namespace App\Http\Controllers;

use App\Models\LegacyUser;
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
            'resumen' => ['nullable', Rule::in(['1'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $all = $records->all();
        $service = $request->query('servicio', 'cafeteria');
        $current = $this->currentCall($service, $all);
        $isAdmin = $menu->allows($request->user(), 'Prevaluaciones_admin');
        $isEvaluator = $menu->allows($request->user(), 'prevaluacion');
        $showEvaluated = $request->query('estado') === 'evaluado';
        $userIds = $this->userIds($request);
        $available = $all->filter(fn ($item) => $item->servicio === $service && $this->belongsToCall($item, $current));
        $campuses = $this->campusesForCall($current, $available);
        $assignments = $this->assignmentsFor($service, $current);
        if ($isEvaluator && ! $isAdmin && $assignments->isNotEmpty()) {
            $campuses = $campuses->filter(fn ($campus) => (int) ($assignments->get($campus)?->evaluador_id) === (int) $request->user()->getKey())->values();
        }
        $available = $available->filter(fn ($item) => $campuses->contains($item->plantel));
        $plantel = $campuses->contains($request->query('plantel')) ? $request->query('plantel') : null;
        $filtered = $plantel ? $available->where('plantel', $plantel) : $available;
        if ($isEvaluator && ! $isAdmin) {
            $filtered = $filtered->filter(fn ($item) => $showEvaluated
                ? $item->evaluado && $item->owner && in_array((int) $item->owner, $userIds, true)
                : ! $item->evaluado && (! $item->owner || in_array((int) $item->owner, $userIds, true)));
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
        if ($selected && ! $isAdmin) {
            $this->authorizeAssignment($selected, $request, $records);
            abort_if($selected->owner && ! in_array((int) $selected->owner, $userIds, true), 404);
        }
        $detail = $selected ? $records->detail($selected) : null;
        $canEvaluate = $selected && $isEvaluator && $selected->owner
            && in_array((int) $selected->owner, $userIds, true);
        $viewedFields = $selected ? DB::table('ccyf_prevaluacion_vistas')
            ->where('origen', $selected->origen)->where('registro_id', $selected->registro_id)
            ->where('usuario_id', $request->user()->getKey())->pluck('clave')->all() : [];
        $comparison = $selected ? $this->comparison($records, $all, $selected) : collect();
        $priceAnalysis = $request->query('comparar') === '1' && $plantel
            ? $this->priceAnalysis($records, $available->where('plantel', $plantel)) : null;
        abort_if($request->query('resumen') === '1' && ! $isAdmin, 403);
        $summaryRows = $isAdmin && $request->query('resumen') === '1'
            ? $this->summaryRows($records, $plantel ? $available->where('plantel', $plantel) : $available) : null;

        return view('prevaluaciones.index', compact('rows', 'selected', 'detail', 'service', 'current', 'campuses', 'plantel', 'comparison', 'priceAnalysis', 'summaryRows', 'isAdmin', 'isEvaluator', 'canEvaluate', 'assignments', 'viewedFields') + [
            'totalCafe' => $this->visibleCount($all, 'cafeteria', $isAdmin, $showEvaluated, $userIds, (int) $request->user()->getKey()),
            'totalFoto' => $this->visibleCount($all, 'fotocopiado', $isAdmin, $showEvaluated, $userIds, (int) $request->user()->getKey()),
        ]);
    }

    public function assignments(Request $request, LegacyMenu $menu, PrevaluationRecords $records): View
    {
        abort_unless($menu->allows($request->user(), 'Prevaluaciones_admin'), 403);
        $request->validate(['servicio' => ['nullable', Rule::in(['cafeteria', 'fotocopiado'])]]);
        $service = $request->query('servicio', 'cafeteria');
        $all = $records->all();
        $current = $this->currentCall($service, $all);
        $available = $all->filter(fn ($item) => $item->servicio === $service && $this->belongsToCall($item, $current));
        $assignments = $this->assignmentsFor($service, $current);
        $campuses = $this->campusesForCall($current, $available);
        $assignmentRows = $campuses->map(fn ($campus) => (object) [
            'name' => $campus, 'evaluator_id' => $assignments->get($campus)?->evaluador_id,
            'pending' => $available->where('plantel', $campus)->filter(fn ($item) => ! $item->evaluado)->count(),
            'inProgress' => $available->where('plantel', $campus)->filter(fn ($item) => ! $item->evaluado && $item->owner)->count(),
        ])->sortBy(fn ($row) => $row->evaluator_id ? 1 : 0)->values();
        $evaluators = LegacyUser::query()->where('est', true)->orderBy('usu_area')->get()
            ->filter(fn (LegacyUser $user) => $menu->allows($user, 'prevaluacion'))->values();

        return view('prevaluaciones.assignments', compact('service', 'current', 'assignmentRows', 'evaluators') + [
            'assignedCount' => $assignmentRows->filter(fn ($row) => $row->evaluator_id)->count(),
            'pendingCount' => $assignmentRows->sum('pending'),
            'inProgressCount' => $assignmentRows->sum('inProgress'),
        ]);
    }

    public function assign(Request $request, LegacyMenu $menu, PrevaluationRecords $records): RedirectResponse
    {
        abort_unless($menu->allows($request->user(), 'Prevaluaciones_admin'), 403);
        $data = $request->validate([
            'servicio' => ['required', Rule::in(['cafeteria', 'fotocopiado'])],
            'convocatoria_id' => ['required', 'integer', 'min:1'],
            'plantel' => ['required', 'string', 'max:150'],
            'evaluador_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $all = $records->all();
        $call = $this->currentCall($data['servicio'], $all);
        $callId = $this->callId($call);
        abort_unless($callId && (int) $data['convocatoria_id'] === $callId, 409, 'La convocatoria vigente cambió. Actualiza la página.');
        $available = $all->filter(fn ($item) => $item->servicio === $data['servicio'] && $this->belongsToCall($item, $call));
        abort_unless($this->campusesForCall($call, $available)->contains($data['plantel']), 422, 'El plantel no pertenece a la convocatoria vigente.');

        $evaluatorId = (int) ($data['evaluador_id'] ?? 0);
        $evaluator = $evaluatorId ? LegacyUser::find($evaluatorId) : null;
        if ($evaluatorId) abort_unless($evaluator && $menu->allows($evaluator, 'prevaluacion'), 422, 'Selecciona un prevaluador activo.');
        $evaluatorIds = $evaluator ? array_values(array_unique(array_filter([(int) $evaluator->getKey(), (int) $evaluator->legacy_usu_id]))) : [];
        DB::transaction(function () use ($data, $call, $callId, $evaluatorId, $evaluatorIds, $request, $records): void {
            $query = DB::table('ccyf_prevaluador_planteles')->where('servicio', $data['servicio'])
                ->where('convocatoria_id', $callId)->where('plantel', $data['plantel']);
            $existing = $query->lockForUpdate()->first();
            $busy = $records->all()->contains(fn ($item) => $item->servicio === $data['servicio']
                && $this->belongsToCall($item, $call) && $item->plantel === $data['plantel']
                && ! $item->evaluado && $item->owner && ! in_array((int) $item->owner, $evaluatorIds, true));
            abort_if($busy, 409, 'Hay expedientes en revisión. Finalízalos o libéralos antes de cambiar este plantel.');
            if (! $evaluatorId) {
                $remaining = DB::table('ccyf_prevaluador_planteles')->where('servicio', $data['servicio'])
                    ->where('convocatoria_id', $callId)->count();
                abort_if($existing && $remaining === 1, 409,
                    'No puedes retirar la última asignación: dejaría todos los planteles abiertos a cualquier prevaluador.');
                $query->delete();
                return;
            }
            DB::table('ccyf_prevaluador_planteles')->upsert([[
                'servicio' => $data['servicio'], 'convocatoria_id' => $callId,
                'plantel' => $data['plantel'], 'evaluador_id' => $evaluatorId,
                'asignado_por' => $request->user()->getKey(), 'created_at' => now(), 'updated_at' => now(),
            ]], ['servicio', 'convocatoria_id', 'plantel'], ['evaluador_id', 'asignado_por', 'updated_at']);
        });

        return redirect()->route('prevaluaciones.assignments', ['servicio' => $data['servicio']])
            ->with('status', $evaluatorId ? 'Plantel asignado al prevaluador.' : 'Asignación retirada.');
    }

    public function assignBulk(Request $request, LegacyMenu $menu, PrevaluationRecords $records): RedirectResponse
    {
        abort_unless($menu->allows($request->user(), 'Prevaluaciones_admin'), 403);
        $data = $request->validate([
            'servicio' => ['required', Rule::in(['cafeteria', 'fotocopiado'])],
            'convocatoria_id' => ['required', 'integer', 'min:1'],
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.plantel' => ['required', 'string', 'max:150'],
            'assignments.*.evaluador_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $all = $records->all();
        $call = $this->currentCall($data['servicio'], $all);
        $callId = $this->callId($call);
        abort_unless($callId && (int) $data['convocatoria_id'] === $callId, 409,
            'La convocatoria vigente cambió. Actualiza la página.');
        $available = $all->filter(fn ($item) => $item->servicio === $data['servicio'] && $this->belongsToCall($item, $call));
        $campuses = $this->campusesForCall($call, $available)->sort()->values()->all();
        $submitted = collect($data['assignments'])->pluck('plantel');
        abort_unless($submitted->count() === count($campuses)
            && $submitted->unique()->count() === count($campuses)
            && $submitted->sort()->values()->all() === $campuses, 422,
            'La lista de planteles cambió. Actualiza la página antes de guardar.');

        $evaluatorIds = collect($data['assignments'])->pluck('evaluador_id')->filter()->unique()->map(fn ($id) => (int) $id);
        $evaluators = LegacyUser::query()->where('est', true)->whereIn('usu_id', $evaluatorIds->all())->get()
            ->filter(fn (LegacyUser $user) => $menu->allows($user, 'prevaluacion'))->keyBy(fn (LegacyUser $user) => (int) $user->getKey());
        abort_unless($evaluators->count() === $evaluatorIds->count(), 422, 'Selecciona únicamente prevaluadores activos.');
        $targets = collect($data['assignments'])->mapWithKeys(fn ($row) => [$row['plantel'] => (int) ($row['evaluador_id'] ?? 0)]);

        $changed = DB::transaction(function () use ($data, $call, $callId, $targets, $evaluators, $request, $records): int {
            $existing = DB::table('ccyf_prevaluador_planteles')->where('servicio', $data['servicio'])
                ->where('convocatoria_id', $callId)->lockForUpdate()->get()->keyBy('plantel');
            $changes = $targets->filter(fn ($id, $campus) => $id !== (int) ($existing->get($campus)?->evaluador_id));
            if ($existing->isNotEmpty() && $targets->filter()->isEmpty()) {
                abort(409, 'No puedes retirar todas las asignaciones: dejaría los planteles abiertos a cualquier prevaluador.');
            }
            $active = $records->all()->filter(fn ($item) => $item->servicio === $data['servicio']
                && $this->belongsToCall($item, $call) && ! $item->evaluado && $item->owner);
            foreach ($changes as $campus => $evaluatorId) {
                $evaluator = $evaluators->get($evaluatorId);
                $allowedIds = $evaluator ? array_values(array_unique(array_filter([
                    (int) $evaluator->getKey(), (int) $evaluator->legacy_usu_id,
                ]))) : [];
                abort_if($active->contains(fn ($item) => $item->plantel === $campus
                    && ! in_array((int) $item->owner, $allowedIds, true)), 409,
                    'Hay expedientes en revisión en '.$campus.'. Finalízalos o libéralos antes de reasignarlo.');
            }
            foreach ($changes as $campus => $evaluatorId) {
                if (! $evaluatorId) {
                    DB::table('ccyf_prevaluador_planteles')->where('servicio', $data['servicio'])
                        ->where('convocatoria_id', $callId)->where('plantel', $campus)->delete();
                    continue;
                }
                DB::table('ccyf_prevaluador_planteles')->upsert([[
                    'servicio' => $data['servicio'], 'convocatoria_id' => $callId,
                    'plantel' => $campus, 'evaluador_id' => $evaluatorId,
                    'asignado_por' => $request->user()->getKey(), 'created_at' => now(), 'updated_at' => now(),
                ]], ['servicio', 'convocatoria_id', 'plantel'], ['evaluador_id', 'asignado_por', 'updated_at']);
            }
            return $changes->count();
        });

        return redirect()->route('prevaluaciones.assignments', ['servicio' => $data['servicio']])
            ->with('status', $changed ? "Se guardaron {$changed} asignaciones." : 'No había cambios pendientes.');
    }

    public function claim(string $key, Request $request, LegacyMenu $menu, PrevaluationRecords $records): RedirectResponse
    {
        abort_unless($menu->allows($request->user(), 'prevaluacion'), 403);
        $record = $this->record($records, $key);
        abort_unless($this->belongsToCall($record, $this->currentCall($record->servicio, $records->all())), 404);
        $this->authorizeAssignment($record, $request, $records);
        abort_if($record->evaluado, 409, 'Este expediente ya fue prevaluado.');
        abort_if($record->owner && ! in_array((int) $record->owner, $this->userIds($request), true),
            409, 'Otro prevaluador ya tomó este expediente.');
        DB::transaction(function () use ($record, $request, $records): void {
            $this->authorizeAssignment($record, $request, $records, true);
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
        $this->authorizeAssignment($record, $request, $records);
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
        $this->authorizeAssignment($record, $request, $records);
        $detail = $records->detail($record);
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
            if ($record->origen === 'historico' && $record->evaluado) {
                DB::table('ccyf_prevaluaciones')->insertOrIgnore([
                    'origen' => $record->origen, 'registro_id' => $record->registro_id,
                    'evaluador_id' => $request->user()->getKey(), 'resultado' => null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $claim = DB::table('ccyf_prevaluaciones')->where('origen', $record->origen)
                ->where('registro_id', $record->registro_id)->lockForUpdate()->first();
            abort_unless($claim && in_array((int) $claim->evaluador_id, $this->userIds($request), true),
                409, 'La reserva cambió o el expediente pertenece a otro prevaluador.');
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
        if (isset($data['resultado'])) {
            unset($query['registro']);
            $query['estado'] = 'evaluado';
        } else {
            $query['estado'] = 'pendiente';
        }
        return redirect()->route('prevaluaciones.index', $query)->with('status',
            isset($data['resultado']) ? 'Prevaluación guardada. Puedes corregirla en «Mis prevaluados».' : 'Avance guardado. El expediente sigue reservado para ti.');
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
        $this->authorizeRecordAccess($record, $request, $menu, $records);
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

    public function viewed(string $key, string $field, Request $request, LegacyMenu $menu, PrevaluationRecords $records, DocumentFiles $files)
    {
        $this->authorizeView($request, $menu);
        $record = $this->record($records, $key);
        $this->authorizeRecordAccess($record, $request, $menu, $records);
        $document = $records->documents($record)->firstWhere('clave', $field);
        abort_unless($document && $document->available && $document->mime === 'application/pdf'
            && $files->effective($record->origen, $record->registro_id, $field), 404);

        DB::table('ccyf_prevaluacion_vistas')->insertOrIgnore([
            'origen' => $record->origen, 'registro_id' => $record->registro_id,
            'usuario_id' => $request->user()->getKey(), 'clave' => $field,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->noContent();
    }

    public function report(string $key, Request $request, LegacyMenu $menu, PrevaluationRecords $records)
    {
        $this->authorizeView($request, $menu);
        $record = $this->record($records, $key);
        $this->authorizeRecordAccess($record, $request, $menu, $records);
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

    public function annex(Request $request, LegacyMenu $menu, PrevaluationRecords $records)
    {
        abort_unless($menu->allows($request->user(), 'Prevaluaciones_admin'), 403);
        $data = $request->validate(['servicio' => ['required', Rule::in(['cafeteria', 'fotocopiado'])]]);
        $all = $records->all();
        $service = $data['servicio'];
        $current = $this->currentCall($service, $all);
        abort_unless($current, 404);
        $available = $all->filter(fn ($item) => $item->servicio === $service && $this->belongsToCall($item, $current));
        $rows = $this->summaryRows($records, $available);
        $groups = $rows->groupBy(fn ($row) => $row->record->plantel ?: 'Sin plantel')->sortKeys();

        $html = view('prevaluaciones.annex', compact('service', 'current', 'rows', 'groups'))->render();
        $temp = storage_path('app/mpdf');
        File::ensureDirectoryExists($temp);
        $pdf = new Mpdf([
            'mode' => 'utf-8', 'format' => 'Letter', 'tempDir' => $temp,
            'margin_left' => 15, 'margin_right' => 15, 'margin_top' => 31,
            'margin_bottom' => 18, 'margin_header' => 6,
            'default_font' => 'dejavusans',
        ]);
        $pdf->SetTitle('Anexo global de prevaluaciones · '.$current->numero);
        $pdf->SetAuthor('CCyF CoBaEMex');
        InstitutionalPdfHeader::apply($pdf, 180);
        $pdf->SetHTMLFooter('<div style="border-top:1px solid #d8c9ce;padding-top:5px;color:#70656a;font-size:7pt;text-align:right">CCyF · Página {PAGENO} de {nbpg}</div>');
        $pdf->WriteHTML($html);
        $bytes = $pdf->Output('', Destination::STRING_RETURN);
        $response = response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Anexo_global_'.$service.'_'.$this->callId($current).'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        return $response;
    }

    private function summaryRows(PrevaluationRecords $records, $available)
    {
        $users = DB::table('ccyf_usuarios')->get(['usu_id', 'legacy_usu_id', 'usu_area']);
        $legacyNames = DB::connection('legacy')->table('tm_usuario')->pluck('usu_area', 'usu_id');
        return $available->map(function ($record) use ($records, $users, $legacyNames) {
            $detail = $records->detail($record);
            $owner = $detail['owner'] ?: $record->owner;
            $evaluator = $users->first(fn ($user) => (int) $user->usu_id === (int) $owner ||
                ($user->legacy_usu_id && (int) $user->legacy_usu_id === (int) $owner))?->usu_area
                ?: $legacyNames->get($owner);
            $date = null;
            $local = DB::table('ccyf_prevaluaciones')->where('origen', $record->origen)
                ->where('registro_id', $record->registro_id)->first(['updated_at']);
            if ($local) $date = $local->updated_at;
            elseif ($record->origen === 'historico') {
                $table = $record->servicio === 'cafeteria' ? 'tm_preval_cafe_doc' : 'tm_preval_foto_doc';
                $date = DB::connection('legacy')->table($table)->where('doc_id', $record->registro_id)
                    ->where('est', 1)->max('fecha_registro');
            }
            return (object) [
                'record' => $record, 'detail' => $detail, 'evaluator' => $evaluator,
                'evaluatedFields' => $detail['items']->filter(fn ($item) => $item->cumple !== null)->count(),
                'date' => $date,
            ];
        })->sortBy(fn ($row) => mb_strtolower(($row->record->plantel ?: '').' '.$row->record->nombre))->values();
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

    private function visibleCount($all, string $service, bool $isAdmin, bool $showEvaluated, array $userIds, int $userId): int
    {
        $call = $this->currentCall($service, $all);
        $assignments = $isAdmin ? collect() : $this->assignmentsFor($service, $call);
        return $all->filter(fn ($item) => $item->servicio === $service && $this->belongsToCall($item, $call)
            && ($isAdmin || $assignments->isEmpty() || (int) ($assignments->get($item->plantel)?->evaluador_id) === $userId)
            && ($isAdmin ? $item->evaluado === $showEvaluated
                : ($showEvaluated
                    ? $item->evaluado && $item->owner && in_array((int) $item->owner, $userIds, true)
                    : ! $item->evaluado && (! $item->owner || in_array((int) $item->owner, $userIds, true)))))->count();
    }

    private function callId(?object $call): ?int
    {
        $id = $call?->id ?: $call?->legacy_cat_id;
        return $id ? (int) $id : null;
    }

    private function campusesForCall(?object $call, $available)
    {
        $campuses = $call?->id ? DB::table('ccyf_convocatoria_planteles as link')
            ->join('ccyf_planteles as p', 'p.id', '=', 'link.plantel_id')
            ->where('link.convocatoria_id', $call->id)->orderBy('p.nombre')->pluck('p.nombre') : collect();
        return $campuses->isEmpty() ? $available->pluck('plantel')->filter()->unique()->sort()->values() : $campuses;
    }

    private function assignmentsFor(string $service, ?object $call)
    {
        $callId = $this->callId($call);
        return $callId ? DB::table('ccyf_prevaluador_planteles')->where('servicio', $service)
            ->where('convocatoria_id', $callId)->get()->keyBy('plantel') : collect();
    }

    private function authorizeAssignment(object $record, Request $request, PrevaluationRecords $records, bool $lock = false): void
    {
        $call = $this->currentCall($record->servicio, $records->all());
        $assignments = $this->assignmentsFor($record->servicio, $call);
        if ($assignments->isEmpty()) return;
        $assignment = $assignments->get($record->plantel);
        if ($lock && $assignment) {
            $assignment = DB::table('ccyf_prevaluador_planteles')->where('id', $assignment->id)->lockForUpdate()->first();
        }
        abort_unless($assignment && (int) $assignment->evaluador_id === (int) $request->user()->getKey(),
            403, 'Este plantel está asignado a otro prevaluador.');
    }

    private function authorizeRecordAccess(object $record, Request $request, LegacyMenu $menu, PrevaluationRecords $records): void
    {
        if ($menu->allows($request->user(), 'Prevaluaciones_admin')) return;
        $this->authorizeAssignment($record, $request, $records);
        abort_if($record->owner && ! in_array((int) $record->owner, $this->userIds($request), true), 403);
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
