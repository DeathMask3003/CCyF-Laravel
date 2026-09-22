<?php

namespace App\Http\Controllers;

use App\Services\FinishedRecords;
use App\Services\LegacyMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    public function finished(Request $request, LegacyMenu $menu, FinishedRecords $finishedRecords): View
    {
        $all = $finishedRecords->forRequest($request, $menu);
        $page = max(1, LengthAwarePaginator::resolveCurrentPage());
        $records = new LengthAwarePaginator($all->forPage($page, 15)->values(), $all->count(), 15, $page, [
            'path' => $request->url(), 'query' => $request->query(),
        ]);
        $canReview = $menu->allows($request->user(), 'buscarOficio') || $menu->allows($request->user(), 'gestionOficio');

        return view('revision.finished', [
            'records' => $records, 'total' => $all->count(), 'canReview' => $canReview,
            'convocations' => $this->convocations(), 'services' => $this->services(),
        ]);
    }

    public function show(int $record, Request $request, LegacyMenu $menu): View
    {
        $registration = $this->localRecords()->where('registro.id', $record)->first();
        abort_unless($registration, 404);
        $this->authorizeRecord($request, $menu, (int) $registration->usu_id, $registration->estado);
        $prices = DB::table('ccyf_registro_precios')->where('registro_id', $record)->orderBy('id')->get();
        $files = DB::table('ccyf_registro_archivos as archivo')
            ->join('ccyf_requisitos_documento as requisito', 'requisito.id', '=', 'archivo.requisito_id')
            ->where('archivo.registro_id', $record)->orderBy('requisito.orden')
            ->get(['archivo.id', 'archivo.nombre_original', 'archivo.bytes', 'requisito.nombre']);

        return view('revision.show', compact('registration', 'prices', 'files'));
    }

    public function historical(int $record, Request $request, LegacyMenu $menu): View
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
        $documents = DB::connection('legacy')->table('td_documentov3')->where('doc_id', $record)
            ->where('est', 1)->orderByDesc('det_id')->first();
        $requirements = $documents ? collect($this->historicalKeys())->filter(fn ($label, $key) => filled($documents->{$key} ?? null)) : collect();

        return view('revision.historical', compact('registration', 'requirements'));
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

    public function file(int $record, int $file, Request $request, LegacyMenu $menu): BinaryFileResponse
    {
        $registration = DB::table('ccyf_registros')->where('id', $record)->first(['usu_id', 'estado']);
        abort_unless($registration, 404);
        $this->authorizeRecord($request, $menu, (int) $registration->usu_id, $registration->estado);
        $document = DB::table('ccyf_registro_archivos')->where('id', $file)->where('registro_id', $record)->first();
        abort_unless($document && Storage::disk('local')->exists($document->ruta), 404);

        return response()->download(Storage::disk('local')->path($document->ruta), 'documento-'.$file.'.pdf', [
            'Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function historicalFile(int $record, string $key, Request $request, LegacyMenu $menu): BinaryFileResponse
    {
        abort_unless(array_key_exists($key, $this->historicalKeys()), 404);
        $registration = DB::connection('legacy')->table('tm_documento')->where('doc_id', $record)
            ->where('doc_estado', 'Finalizado')->whereIn('trami_id', [3, 4])->first(['usu_id']);
        abort_unless($registration, 404);
        $this->authorizeRecord($request, $menu, (int) $registration->usu_id, 'Finalizado');
        $documents = DB::connection('legacy')->table('td_documentov3')->where('doc_id', $record)
            ->where('est', 1)->orderByDesc('det_id')->first();
        $name = $documents ? ($documents->{$key} ?? null) : null;
        abort_unless(is_string($name) && $name !== '' && basename($name) === $name && str_ends_with(strtolower($name), '.pdf'), 404);
        $root = realpath(config('ccyf.legacy_files_root'));
        abort_unless($root, 404);
        $path = realpath($root.DIRECTORY_SEPARATOR.$record.DIRECTORY_SEPARATOR.$name);
        abort_unless($path && str_starts_with($path, $root.DIRECTORY_SEPARATOR) && is_file($path), 404);

        return response()->download($path, $key.'-'.$record.'.pdf', [
            'Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function localRecords()
    {
        return DB::table('ccyf_registros as registro')
            ->join('ccyf_convocatorias as convocatoria', 'convocatoria.id', '=', 'registro.convocatoria_id')
            ->join('ccyf_tipos_servicio as servicio', 'servicio.id', '=', 'registro.servicio_id')
            ->join('ccyf_planteles as plantel', 'plantel.id', '=', 'registro.plantel_id')
            ->select('registro.*', 'convocatoria.numero as convocatoria_nombre',
                'servicio.nombre as servicio_nombre', 'plantel.nombre as plantel_nombre');
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
