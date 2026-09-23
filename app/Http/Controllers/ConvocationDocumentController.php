<?php

namespace App\Http\Controllers;

use App\Services\ConvocationDocuments;
use App\Services\CcyfStructure;
use App\Services\LegacyMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ConvocationDocumentController extends Controller
{
    public function index(Request $request, LegacyMenu $menu): View
    {
        $this->authorizeModule($request, $menu);
        $service = $request->query('servicio');
        $search = trim((string) $request->query('q'));
        $query = DB::table('ccyf_convocatoria_documentos as documento')
            ->join('ccyf_convocatorias as convocatoria', 'convocatoria.id', '=', 'documento.convocatoria_id')
            ->leftJoin('ccyf_tipos_servicio as servicio', 'servicio.id', '=', 'convocatoria.servicio_id')
            ->select('documento.*', 'convocatoria.numero', 'servicio.nombre as servicio_nombre')
            ->selectSub(function ($sub): void {
                $sub->from('ccyf_convocatoria_documento_planteles')
                    ->whereColumn('documento_id', 'documento.id')->selectRaw('count(*)');
            }, 'planteles_total')
            ->when(in_array($service, ['cafeteria', 'fotocopiado'], true),
                fn ($q) => $q->where('servicio.nombre', 'like', $service === 'cafeteria' ? 'Cafeter%' : 'Fotocopi%'))
            ->when($search !== '', fn ($q) => $q->where(function ($nested) use ($search): void {
                $nested->where('documento.titulo', 'like', "%{$search}%")
                    ->orWhere('convocatoria.numero', 'like', "%{$search}%");
            }));
        $documents = $query->orderByDesc('documento.id')->paginate(12)->withQueryString();
        $totals = DB::table('ccyf_convocatoria_documentos as d')
            ->join('ccyf_convocatorias as c', 'c.id', '=', 'd.convocatoria_id')
            ->leftJoin('ccyf_tipos_servicio as s', 's.id', '=', 'c.servicio_id')
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when s.nombre like 'Cafeter%' then 1 else 0 end) as cafeteria")
            ->selectRaw("sum(case when s.nombre like 'Fotocopi%' then 1 else 0 end) as fotocopiado")
            ->first();
        return view('emision-convocatorias.index', compact('documents', 'totals', 'search', 'service'));
    }

    public function create(Request $request, LegacyMenu $menu, ConvocationDocuments $documents): View
    {
        $this->authorizeModule($request, $menu);
        $calls = $this->calls();
        $selectedId = (int) $request->query('convocatoria', $calls->first()?->id ?? 0);
        $call = $calls->firstWhere('id', $selectedId);
        if ($selectedId && ! $call) {
            abort(404);
        }
        $campuses = $call ? $documents->campuses($call->id, true) : collect();
        $snapshots = collect();
        $document = null;
        $template = $call ? $documents->templateFor((object) ['id' => $call->servicio_id, 'nombre' => $call->servicio_nombre]) : null;
        return view('emision-convocatorias.form', compact('calls', 'call', 'campuses', 'snapshots', 'document', 'template'));
    }

    public function store(Request $request, LegacyMenu $menu, ConvocationDocuments $documents): RedirectResponse
    {
        $this->authorizeModule($request, $menu);
        [$call, $data, $campuses] = $this->validatedDocument($request, $documents, true);
        $id = DB::transaction(function () use ($request, $call, $data, $campuses): int {
            $id = DB::table('ccyf_convocatoria_documentos')->insertGetId([
                'convocatoria_id' => $call->id,
                'titulo' => $data['titulo'], 'detalles_html' => $data['detalles_html'],
                'font_family' => $data['font_family'], 'font_size' => $data['font_size'],
                'creado_por' => $request->user()->getKey(), 'actualizado_por' => $request->user()->getKey(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->insertCampuses($id, $campuses);
            return $id;
        });
        return redirect()->route('emision.edit', $id)->with('status', 'Convocatoria guardada. Revisa el PDF antes de compartirlo.');
    }

    public function edit(int $document, Request $request, LegacyMenu $menu, ConvocationDocuments $documents): View
    {
        $this->authorizeModule($request, $menu);
        $record = $this->find($document);
        $call = $this->findCall((int) $record->convocatoria_id);
        $calls = $this->calls();
        $campuses = $documents->campuses($call->id);
        $snapshots = DB::table('ccyf_convocatoria_documento_planteles as fila')
            ->leftJoin('ccyf_planteles as plantel', 'plantel.id', '=', 'fila.plantel_id')
            ->where('fila.documento_id', $document)
            ->get(['fila.*', 'plantel.legacy_area_id'])
            ->keyBy(fn ($row) => $row->legacy_area_id ?: $row->plantel_id);
        $template = $documents->templateFor((object) ['id' => $call->servicio_id, 'nombre' => $call->servicio_nombre]);
        return view('emision-convocatorias.form', ['document' => $record] + compact('calls', 'call', 'campuses', 'snapshots', 'template'));
    }

    public function update(int $document, Request $request, LegacyMenu $menu, ConvocationDocuments $documents): RedirectResponse
    {
        $this->authorizeModule($request, $menu);
        $record = $this->find($document);
        [$call, $data, $campuses] = $this->validatedDocument($request, $documents, false, (int) $record->convocatoria_id);
        DB::transaction(function () use ($request, $record, $data, $campuses): void {
            DB::table('ccyf_convocatoria_documentos')->where('id', $record->id)->update([
                'titulo' => $data['titulo'], 'detalles_html' => $data['detalles_html'],
                'font_family' => $data['font_family'], 'font_size' => $data['font_size'],
                'actualizado_por' => $request->user()->getKey(), 'updated_at' => now(),
            ]);
            DB::table('ccyf_convocatoria_documento_planteles')->where('documento_id', $record->id)->delete();
            $this->insertCampuses($record->id, $campuses);
        });
        return back()->with('status', 'Cambios guardados. El PDF se actualizó con estos datos.');
    }

    public function previewDraft(Request $request, LegacyMenu $menu, ConvocationDocuments $documents): Response
    {
        $this->authorizeModule($request, $menu);
        [$call, $data, $campuses] = $this->validatedDocument($request, $documents, true, null, true);
        return $this->pdfResponse($documents->pdf($call, $data['titulo'], $data['detalles_html'], $campuses,
            $data['font_family'], (float) $data['font_size']), false, 'vista-previa-convocatoria.pdf');
    }

    public function pdf(int $document, Request $request, LegacyMenu $menu, ConvocationDocuments $documents): Response
    {
        $this->authorizeModule($request, $menu);
        $record = $this->find($document);
        $call = $this->findCall((int) $record->convocatoria_id);
        $campuses = DB::table('ccyf_convocatoria_documento_planteles')
            ->where('documento_id', $document)->orderBy('nombre')->get();
        $download = $request->boolean('descargar');
        return $this->pdfResponse($documents->pdf($call, $record->titulo, $record->detalles_html, $campuses,
            $record->font_family, (float) $record->font_size),
            $download, 'convocatoria-'.$document.'.pdf');
    }

    public function template(string $service, Request $request, LegacyMenu $menu, ConvocationDocuments $documents): View
    {
        $this->authorizeModule($request, $menu);
        $serviceRecord = $this->serviceForSlug($service);
        $template = $request->boolean('base')
            ? (object) ['cuerpo_html' => $documents->defaultTemplate($serviceRecord->nombre),
                'font_family' => 'dejavusans', 'font_size' => 9, 'updated_at' => null]
            : $documents->templateFor($serviceRecord);
        return view('emision-convocatorias.template', compact('service', 'serviceRecord', 'template'));
    }

    public function saveTemplate(string $service, Request $request, LegacyMenu $menu,
        ConvocationDocuments $documents): RedirectResponse
    {
        $this->authorizeModule($request, $menu);
        $serviceRecord = $this->serviceForSlug($service);
        $data = $request->validate([
            'cuerpo_html' => ['required', 'string', 'min:100', 'max:100000'],
            'font_family' => ['required', Rule::in(array_keys(ConvocationDocuments::FONT_FAMILIES))],
            'font_size' => ['required', 'numeric', 'between:7,14', 'multiple_of:0.5'],
        ]);
        $body = $documents->cleanHtml($data['cuerpo_html']);
        if (mb_strlen(trim(strip_tags($body))) < 100) {
            throw ValidationException::withMessages(['cuerpo_html' => 'La plantilla necesita al menos 100 caracteres de texto.']);
        }
        $values = ['cuerpo_html' => $body, 'font_family' => $data['font_family'],
            'font_size' => $data['font_size'], 'actualizado_por' => $request->user()->getKey(),
            'updated_at' => now()];
        $query = DB::table('ccyf_convocatoria_plantillas')->where('servicio_id', $serviceRecord->id);
        if ($query->exists()) {
            $query->update($values);
        } else {
            DB::table('ccyf_convocatoria_plantillas')->insert($values +
                ['servicio_id' => $serviceRecord->id, 'created_at' => now()]);
        }
        return redirect()->route('emision.template', $service)->with('status', 'Plantilla guardada. Las nuevas convocatorias usarán esta versión.');
    }

    private function validatedDocument(Request $request, ConvocationDocuments $documents, bool $creation,
        ?int $fixedCallId = null, bool $preview = false): array
    {
        $data = $request->validate([
            'convocatoria_id' => ['required', 'integer', Rule::exists('ccyf_convocatorias', 'id')],
            'titulo' => ['required', 'string', 'max:200'],
            'detalles_html' => ['required', 'string', 'max:100000'],
            'font_family' => ['required', Rule::in(array_keys(ConvocationDocuments::FONT_FAMILIES))],
            'font_size' => ['required', 'numeric', 'between:7,14', 'multiple_of:0.5'],
            'planteles' => ['required', 'array'],
        ]);
        $call = $this->findCall((int) $data['convocatoria_id']);
        if ($fixedCallId && $call->id !== $fixedCallId) {
            throw ValidationException::withMessages(['convocatoria_id' => 'No se puede cambiar el número de una convocatoria guardada.']);
        }
        if ($creation && ! $preview && ! $call->activo) {
            throw ValidationException::withMessages(['convocatoria_id' => 'Selecciona una convocatoria activa.']);
        }
        $eligible = $documents->campuses($call->id, $creation)->keyBy('id');
        $selected = collect();
        foreach ($data['planteles'] as $id => $row) {
            if (! is_array($row) || empty($row['seleccionado'])) {
                continue;
            }
            $campus = $eligible->get((int) $id);
            if (! $campus) {
                throw ValidationException::withMessages(['planteles' => 'Hay un plantel que no está disponible en el catálogo de planteles.']);
            }
            $validated = validator($row, [
                'direccion' => ['nullable', 'string', 'max:500'],
                'espacio' => ['required', 'string', 'max:100'],
                'matricula' => ['nullable', 'integer', 'between:0,999999'],
                'monto' => ['required', 'numeric', 'between:0,999999999.99', 'decimal:0,2'],
                'garantia' => ['nullable', 'numeric', 'between:0,999999999.99', 'decimal:0,2'],
                'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            ], [
                'espacio.required' => 'Captura el espacio de '.$campus->nombre.'.',
                'monto.required' => 'Captura el monto mensual de '.$campus->nombre.'.',
                'fecha_inicio.required' => 'Captura la fecha de inicio de '.$campus->nombre.'.',
                'fecha_inicio.date_format' => 'La fecha de inicio de '.$campus->nombre.' no es válida.',
            ])->validate();
            $selected->push((object) [
                'legacy_area_id' => $campus->id, 'nombre' => $campus->nombre,
                'correo' => $campus->correo,
                'direccion' => trim($validated['direccion'] ?? '') ?: null,
                'espacio' => trim($validated['espacio']), 'matricula' => $validated['matricula'] ?? null,
                'monto' => $validated['monto'], 'garantia' => $validated['garantia'] ?? null,
                'fecha_inicio' => $validated['fecha_inicio'],
            ]);
        }
        if ($selected->isEmpty()) {
            throw ValidationException::withMessages(['planteles' => 'Selecciona al menos un plantel.']);
        }
        $details = $documents->cleanHtml($data['detalles_html']);
        if (trim(strip_tags($details)) === '') {
            throw ValidationException::withMessages(['detalles_html' => 'Escribe los requisitos y las bases de la convocatoria.']);
        }
        $data['titulo'] = trim($data['titulo']);
        $data['detalles_html'] = $details;
        return [$call, $data, $selected];
    }

    private function insertCampuses(int $documentId, Collection $campuses): void
    {
        foreach ($campuses as $campus) {
            $plantelId = DB::table('ccyf_planteles')
                ->where('legacy_area_id', $campus->legacy_area_id)->value('id');
            if (! $plantelId) {
                $key = app(CcyfStructure::class)->key($campus->nombre);
                if (DB::table('ccyf_planteles')->where('nombre_clave', $key)->exists()) {
                    $key .= '-area-'.$campus->legacy_area_id;
                }
                DB::table('ccyf_planteles')->insertOrIgnore([
                    'legacy_area_id' => $campus->legacy_area_id, 'nombre' => $campus->nombre,
                    'nombre_clave' => $key, 'correo' => $campus->correo,
                    'direccion' => $campus->direccion, 'activo' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $plantelId = DB::table('ccyf_planteles')
                    ->where('legacy_area_id', $campus->legacy_area_id)->value('id');
                if (! $plantelId) {
                    throw ValidationException::withMessages(['planteles' => 'No fue posible registrar el plantel seleccionado.']);
                }
            }
            DB::table('ccyf_convocatoria_documento_planteles')->insert([
                'documento_id' => $documentId, 'plantel_id' => $plantelId,
                'nombre' => $campus->nombre, 'direccion' => $campus->direccion,
                'espacio' => $campus->espacio, 'matricula' => $campus->matricula,
                'monto' => $campus->monto, 'garantia' => $campus->garantia,
                'fecha_inicio' => $campus->fecha_inicio,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function calls(): Collection
    {
        return DB::table('ccyf_convocatorias as convocatoria')
            ->join('ccyf_tipos_servicio as servicio', 'servicio.id', '=', 'convocatoria.servicio_id')
            ->select('convocatoria.*', 'servicio.nombre as servicio_nombre')
            ->where('convocatoria.activo', true)->where('servicio.activo', true)
            ->orderByDesc('convocatoria.id')->get();
    }

    private function findCall(int $id): object
    {
        $call = DB::table('ccyf_convocatorias as convocatoria')
            ->join('ccyf_tipos_servicio as servicio', 'servicio.id', '=', 'convocatoria.servicio_id')
            ->where('convocatoria.id', $id)
            ->select('convocatoria.*', 'servicio.nombre as servicio_nombre')->first();
        abort_unless($call, 404);
        return $call;
    }

    private function find(int $id): object
    {
        $record = DB::table('ccyf_convocatoria_documentos')->where('id', $id)->first();
        abort_unless($record, 404);
        return $record;
    }

    private function serviceForSlug(string $slug): object
    {
        abort_unless(in_array($slug, ['cafeteria', 'fotocopiado'], true), 404);
        $service = DB::table('ccyf_tipos_servicio')->where('nombre', 'like', $slug === 'cafeteria' ? 'Cafeter%' : 'Fotocopi%')->first();
        abort_unless($service, 404);
        return $service;
    }

    private function authorizeModule(Request $request, LegacyMenu $menu): void
    {
        abort_unless($menu->allows($request->user(), 'convocatorias'), 403);
    }

    private function pdfResponse(string $pdf, bool $download, string $filename): Response
    {
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
