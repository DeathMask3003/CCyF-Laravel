<?php

namespace App\Http\Controllers;

use App\Services\FinishedRecords;
use App\Services\FinishedResultPdf;
use App\Services\FinalEvaluationReport;
use App\Services\DocumentFiles;
use App\Services\LegacyMenu;
use App\Services\PrevaluationCatalog;
use App\Services\PrevaluationRecords;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OfficeReviewController extends Controller
{
    public function pending(Request $request, LegacyMenu $menu): View
    {
        abort_unless($menu->allows($request->user(), 'gestionOficio'), 403);

        $query = $this->localRecords()->where('registro.estado', 'Recibido');
        $this->applyFilters($query, $request);
        $records = $query->orderByDesc('registro.enviado_at')->paginate(15)->withQueryString();
        $summary = DB::table('ccyf_registros')->where('estado', 'Recibido')
            ->selectRaw('servicio_id, count(*) total')->groupBy('servicio_id')->pluck('total', 'servicio_id');

        return view('revision.pending', [
            'records' => $records,
            'summary' => $summary,
            'convocations' => $this->convocations(),
            'services' => $this->services(),
        ]);
    }

    public function finished(Request $request, LegacyMenu $menu, FinishedRecords $finishedRecords,
        FinishedResultPdf $resultPdf, FinalEvaluationReport $evaluation): View
    {
        $all = $finishedRecords->forRequest($request, $menu);
        $page = max(1, LengthAwarePaginator::resolveCurrentPage());
        $records = new LengthAwarePaginator($all->forPage($page, 15)->values(), $all->count(), 15, $page, [
            'path' => $request->url(), 'query' => $request->query(),
        ]);
        $records->getCollection()->each(function ($record) use ($resultPdf, $evaluation): void {
            $record->resultado_pdf_disponible = $record->origen === 'historico'
                && $resultPdf->historical((int) $record->id, (int) $record->designado === 1) !== null;
            $service = (int) $record->legacy_servicio_id;
            $record->evaluacion_pdf_disponible = in_array($service, [3, 4], true)
                && $evaluation->source($record->origen, (int) $record->id,
                    $service === 3 ? 'cafeteria' : 'fotocopiado') !== null;
        });
        $canReview = $menu->allows($request->user(), 'buscarOficio') || $menu->allows($request->user(), 'gestionOficio');

        return view('revision.finished', [
            'records' => $records, 'total' => $all->count(), 'canReview' => $canReview,
            'convocations' => $this->convocations(), 'services' => $this->services(),
        ]);
    }

    public function show(int $record, Request $request, LegacyMenu $menu, DocumentFiles $documentFiles, FinalEvaluationReport $evaluation): View
    {
        $registration = $this->localRecords()->where('registro.id', $record)->first();
        abort_unless($registration, 404);
        $this->authorizeRecord($request, $menu, (int) $registration->usu_id, $registration->estado);
        $prices = DB::table('ccyf_registro_precios')->where('registro_id', $record)->orderBy('id')->get();
        $files = DB::table('ccyf_registro_archivos as archivo')
            ->join('ccyf_requisitos_documento as requisito', 'requisito.id', '=', 'archivo.requisito_id')
            ->where('archivo.registro_id', $record)->orderBy('requisito.orden')
            ->get(['archivo.id', 'archivo.requisito_id', 'archivo.nombre_original', 'archivo.mime', 'archivo.bytes', 'requisito.nombre'])
            ->map(function ($item) use ($record, $documentFiles): object {
                $updated = $documentFiles->latest('actual', $record, 'req-'.$item->requisito_id);
                if ($updated) {
                    $item->nombre_original = $updated->original_name;
                    $item->mime = $updated->mime;
                    $item->bytes = $updated->bytes;
                }
                return $item;
            });

        $service = (int) $registration->legacy_servicio_id === 3 ? 'cafeteria' : 'fotocopiado';
        $finalEvaluationAvailable = $registration->estado === 'Finalizado'
            && in_array((int) $registration->legacy_servicio_id, [3, 4], true)
            && $evaluation->source('actual', $record, $service) !== null;

        return view('revision.show', compact('registration', 'prices', 'files', 'finalEvaluationAvailable'));
    }

    public function historical(int $record, Request $request, LegacyMenu $menu, DocumentFiles $documentFiles, FinishedResultPdf $resultPdf, FinalEvaluationReport $evaluation): View
    {
        $registration = DB::connection('legacy')->table('tm_documento as documento')
            ->leftJoin('tm_usuario as usuario', 'usuario.usu_id', '=', 'documento.usu_id')
            ->leftJoin('tm_areas as plantel', 'plantel.area_id', '=', 'documento.area_id')
            ->leftJoin('tm_categoria_widi as convocatoria', 'convocatoria.cat_id', '=', 'documento.num_doc')
            ->leftJoin('tm_tramite as servicio', 'servicio.trami_id', '=', 'documento.trami_id')
            ->where('documento.doc_id', $record)->where('documento.doc_estado', 'Finalizado')
            ->whereIn('documento.trami_id', [3, 4])->first([
                'documento.*', 'usuario.usu_area as solicitante', 'plantel.area_nom as plantel_real',
                'convocatoria.cat_nom as convocatoria_nombre', 'servicio.trami_nom as servicio_nombre',
            ]);
        abort_unless($registration, 404);
        $this->authorizeRecord($request, $menu, (int) $registration->usu_id, 'Finalizado');
        $service = (int) $registration->trami_id === 3 ? 'cafeteria' : 'fotocopiado';
        $requirements = collect(PrevaluationCatalog::documents($service))
            ->filter(fn ($label, $key) => $documentFiles->effective('historico', $record, $key) !== null);
        $previewDocuments = $requirements->map(fn ($label, $key) => [
            'label' => $label,
            'mime' => $documentFiles->effective('historico', $record, $key)['mime'],
            'url' => route('revision.historical-file', [$record, $key]),
        ]);
        $resultadoPdfDisponible = $resultPdf->historical($record, (int) $registration->doc_designado === 1) !== null;
        $finalEvaluationAvailable = $evaluation->source('historico', $record, $service) !== null;

        return view('revision.historical', compact('registration', 'requirements', 'previewDocuments', 'resultadoPdfDisponible', 'finalEvaluationAvailable'));
    }

    public function historicalFinalEvaluationPdf(int $record, Request $request, LegacyMenu $menu,
        FinalEvaluationReport $evaluation, PrevaluationRecords $prevaluations): Response
    {
        $registration = DB::connection('legacy')->table('tm_documento as d')
            ->leftJoin('tm_usuario as u', 'u.usu_id', '=', 'd.usu_id')
            ->leftJoin('tm_areas as p', 'p.area_id', '=', 'd.area_id')
            ->leftJoin('tm_categoria_widi as c', 'c.cat_id', '=', 'd.num_doc')
            ->where('d.doc_id', $record)->where('d.doc_estado', 'Finalizado')
            ->whereIn('d.trami_id', [3, 4])
            ->first(['d.usu_id', 'd.trami_id', 'd.doc_exter', 'u.usu_area', 'p.area_nom', 'c.cat_nom']);
        abort_unless($registration, 404);
        $this->authorizeRecord($request, $menu, (int) $registration->usu_id, 'Finalizado');
        $service = (int) $registration->trami_id === 3 ? 'cafeteria' : 'fotocopiado';
        $source = $evaluation->source('historico', $record, $service);
        abort_unless($source, 404, 'Este expediente no tiene una evaluación final registrada.');

        $item = (object) [
            'origen' => 'historico', 'registro_id' => $record, 'servicio' => $service,
            'convocatoria' => $registration->cat_nom ?: 'Sin convocatoria registrada',
            'plantel' => $registration->doc_exter ?: $registration->area_nom,
            'nombre' => $registration->usu_area,
        ];

        return $evaluation->pdf($item, $source, $prevaluations->detail($item), $request->user());
    }

    public function localFinalEvaluationPdf(int $record, Request $request, LegacyMenu $menu,
        FinalEvaluationReport $evaluation, PrevaluationRecords $prevaluations): Response
    {
        $registration = $this->localRecords()->where('registro.id', $record)
            ->where('registro.estado', 'Finalizado')->first();
        abort_unless($registration, 404);
        $this->authorizeRecord($request, $menu, (int) $registration->usu_id, 'Finalizado');
        abort_unless(in_array((int) $registration->legacy_servicio_id, [3, 4], true), 404);
        $service = (int) $registration->legacy_servicio_id === 3 ? 'cafeteria' : 'fotocopiado';
        $source = $evaluation->source('actual', $record, $service);
        abort_unless($source, 404, 'Este expediente no tiene una evaluación final registrada.');

        $item = (object) [
            'origen' => 'actual', 'registro_id' => $record, 'servicio' => $service,
            'convocatoria' => $registration->convocatoria_nombre,
            'plantel' => $registration->plantel_nombre,
            'nombre' => $registration->solicitante,
        ];

        return $evaluation->pdf($item, $source, $prevaluations->detail($item), $request->user());
    }

    public function historicalResultPdf(int $record, Request $request, LegacyMenu $menu, FinishedResultPdf $resultPdf): BinaryFileResponse
    {
        $registration = DB::connection('legacy')->table('tm_documento')->where('doc_id', $record)
            ->where('doc_estado', 'Finalizado')->whereIn('trami_id', [3, 4])
            ->first(['usu_id', 'doc_designado']);
        abort_unless($registration, 404);
        $this->authorizeRecord($request, $menu, (int) $registration->usu_id, 'Finalizado');

        $path = $resultPdf->historical($record, (int) $registration->doc_designado === 1);
        abort_unless($path, 404, 'No se encontró la carta PDF archivada para este expediente.');

        $response = response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    public function finish(int $record, Request $request, LegacyMenu $menu): RedirectResponse
    {
        abort_unless($menu->allows($request->user(), 'gestionOficio'), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['designado', 'no_designado', 'no_aceptado'])],
            'respuesta' => ['required', 'string', 'max:250'],
            'fecha_inicio' => ['required_if:decision,designado', 'nullable', 'date'],
            'fecha_fin' => ['required_if:decision,designado', 'nullable', 'date', 'after_or_equal:fecha_inicio'],
            'monto' => ['required_if:decision,designado', 'nullable', 'numeric', 'between:0.01,9999999999.99', 'decimal:0,2'],
        ]);
        $designado = $data['decision'] === 'designado';
        $changed = DB::table('ccyf_registros')->where('id', $record)->where('estado', 'Recibido')->update([
            'estado' => 'Finalizado', 'decision' => $data['decision'],
            'respuesta' => trim($data['respuesta']),
            'fecha_inicio' => $designado ? $data['fecha_inicio'] : null,
            'fecha_fin' => $designado ? $data['fecha_fin'] : null,
            'monto' => $designado ? $data['monto'] : null,
            'revisado_por' => $request->user()->getKey(),
            'finalizado_at' => now(), 'updated_at' => now(),
        ]);
        abort_unless($changed, 409, 'Esta propuesta ya fue finalizada.');

        return redirect()->route('revision.finished')->with('status', 'La propuesta quedó finalizada.');
    }

    public function rejectBulk(Request $request, LegacyMenu $menu): RedirectResponse
    {
        abort_unless($menu->allows($request->user(), 'gestionOficio'), 403);
        $data = $request->validate([
            'registros' => ['required', 'array', 'min:1', 'max:100'],
            'registros.*' => ['required', 'integer', 'distinct'],
            'respuesta' => ['required', 'string', 'max:250'],
        ]);
        $ids = array_map('intval', $data['registros']);
        DB::transaction(function () use ($ids, $data, $request): void {
            $changed = DB::table('ccyf_registros')->whereIn('id', $ids)->where('estado', 'Recibido')->update([
                'estado' => 'Finalizado', 'decision' => 'no_aceptado',
                'respuesta' => trim($data['respuesta']), 'revisado_por' => $request->user()->getKey(),
                'finalizado_at' => now(), 'updated_at' => now(),
            ]);
            if ($changed !== count($ids)) {
                throw ValidationException::withMessages(['registros' => 'La selección cambió. Actualiza la página y vuelve a seleccionar las propuestas pendientes.']);
            }
        });

        return redirect()->route('revision.finished')->with('status', count($ids).' propuestas finalizadas como no aceptadas.');
    }

    public function file(int $record, int $file, Request $request, LegacyMenu $menu, DocumentFiles $documentFiles): BinaryFileResponse
    {
        $registration = DB::table('ccyf_registros')->where('id', $record)->first(['usu_id', 'estado']);
        abort_unless($registration, 404);
        $this->authorizeRecord($request, $menu, (int) $registration->usu_id, $registration->estado);
        $document = DB::table('ccyf_registro_archivos')->where('id', $file)->where('registro_id', $record)->first();
        abort_unless($document, 404);
        $current = $documentFiles->effective('actual', $record, 'req-'.$document->requisito_id);
        abort_unless($current, 404);
        return $this->sendDocument($current, $current['mime'] === 'application/pdf' ? 'documento-'.$file.'.pdf' : $current['name']);
    }

    public function historicalFile(int $record, string $key, Request $request, LegacyMenu $menu, DocumentFiles $documentFiles): BinaryFileResponse
    {
        abort_unless(array_key_exists($key, $this->historicalKeys()), 404);
        $registration = DB::connection('legacy')->table('tm_documento')->where('doc_id', $record)
            ->where('doc_estado', 'Finalizado')->whereIn('trami_id', [3, 4])->first(['usu_id']);
        abort_unless($registration, 404);
        $this->authorizeRecord($request, $menu, (int) $registration->usu_id, 'Finalizado');
        $current = $documentFiles->effective('historico', $record, $key);
        abort_unless($current, 404);
        return $this->sendDocument($current, $current['mime'] === 'application/pdf' ? $key.'-'.$record.'.pdf' : $current['name']);
    }

    private function sendDocument(array $file, string $name): BinaryFileResponse
    {
        $inline = in_array($file['mime'], ['application/pdf', 'image/jpeg', 'image/png'], true);
        $response = $inline ? response()->file($file['path'], ['Content-Type' => $file['mime']])
            : response()->download($file['path'], $name, ['Content-Type' => $file['mime']]);
        if ($inline) {
            $response->setContentDisposition('inline', $name);
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        return $response;
    }

    private function localRecords()
    {
        return DB::table('ccyf_registros as registro')
            ->join('ccyf_convocatorias as convocatoria', 'convocatoria.id', '=', 'registro.convocatoria_id')
            ->join('ccyf_tipos_servicio as servicio', 'servicio.id', '=', 'registro.servicio_id')
            ->join('ccyf_planteles as plantel', 'plantel.id', '=', 'registro.plantel_id')
            ->select('registro.*', 'convocatoria.numero as convocatoria_nombre',
                'servicio.nombre as servicio_nombre', 'servicio.legacy_trami_id as legacy_servicio_id',
                'plantel.nombre as plantel_nombre');
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('servicio')) {
            $query->where('registro.servicio_id', (int) $request->input('servicio'));
        }
        if ($request->filled('convocatoria')) {
            $query->where('registro.convocatoria_id', (int) $request->input('convocatoria'));
        }
        if ($request->filled('buscar')) {
            $term = '%'.trim($request->input('buscar')).'%';
            $query->where(function ($query) use ($term): void {
                $query->where('registro.folio', 'like', $term)
                    ->orWhere('registro.solicitante', 'like', $term)
                    ->orWhere('plantel.nombre', 'like', $term)
                    ->orWhere('convocatoria.numero', 'like', $term);
            });
        }
    }

    private function convocations()
    {
        return DB::table('ccyf_convocatorias')->orderByDesc('id')->get(['id', 'numero']);
    }

    private function services()
    {
        return DB::table('ccyf_tipos_servicio')->whereIn('legacy_trami_id', [3, 4])
            ->orWhere('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'legacy_trami_id']);
    }

    private function authorizeRecord(Request $request, LegacyMenu $menu, int $owner, string $state): void
    {
        $canReview = $menu->allows($request->user(), 'gestionOficio') ||
            ($state === 'Finalizado' && $menu->allows($request->user(), 'buscarOficio'));
        $owns = $owner === (int) $request->user()->getKey() && $menu->allows($request->user(), 'NuevoOficio');
        abort_unless($canReview || $owns, 403);
    }

    private function historicalKeys(): array
    {
        return [
            'prop_escrito' => 'Propuesta por escrito', 'acta_nac' => 'Acta de nacimiento',
            'identi_ofici' => 'Identificación oficial', 'domicilio' => 'Comprobante de domicilio',
            'dat_grals' => 'Datos generales', 'dos_cartas' => 'Primera carta de recomendación',
            'ine_cartarecom_1' => 'INE de la primera carta', 'carta_recom2' => 'Segunda carta de recomendación',
            'ine_cartarecom_2' => 'INE de la segunda carta', 'carta_protesta' => 'Carta protesta',
            'menu_aval' => 'Menú avalado', 'forma_prec' => 'Formato de precios',
            'mobiliario' => 'Mobiliario', 'menuali_precio' => 'Menú con precios',
            'marca_porcion' => 'Marcas y porciones',
        ];
    }
}
