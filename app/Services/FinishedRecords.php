<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinishedRecords
{
    public function forRequest(Request $request, LegacyMenu $menu): Collection
    {
        $canReview = ! $menu->isContestant($request->user())
            && ($menu->allows($request->user(), 'buscarOficio') || $menu->allows($request->user(), 'gestionOficio'));
        abort_unless($canReview || $menu->allows($request->user(), 'NuevoOficio')
            || $menu->allows($request->user(), 'buscarOficio'), 403);

        $local = DB::table('ccyf_registros as registro')
            ->join('ccyf_convocatorias as convocatoria', 'convocatoria.id', '=', 'registro.convocatoria_id')
            ->join('ccyf_tipos_servicio as servicio', 'servicio.id', '=', 'registro.servicio_id')
            ->join('ccyf_planteles as plantel', 'plantel.id', '=', 'registro.plantel_id')
            ->where('registro.estado', 'Finalizado');
        if (! $canReview) {
            $local->where('registro.usu_id', $request->user()->getKey());
        }
        $localRows = $local->get([
            'registro.*', 'convocatoria.numero as convocatoria_nombre',
            'servicio.nombre as servicio_nombre', 'servicio.legacy_trami_id as legacy_servicio_id',
            'plantel.nombre as plantel_nombre',
        ]);

        $historical = DB::connection('legacy')->table('tm_documento as documento')
            ->leftJoin('tm_usuario as usuario', 'usuario.usu_id', '=', 'documento.usu_id')
            ->leftJoin('tm_areas as plantel', 'plantel.area_id', '=', 'documento.area_id')
            ->leftJoin('tm_categoria_widi as convocatoria', 'convocatoria.cat_id', '=', 'documento.num_doc')
            ->leftJoin('tm_tramite as servicio', 'servicio.trami_id', '=', 'documento.trami_id')
            ->where('documento.doc_estado', 'Finalizado')->whereIn('documento.trami_id', [3, 4]);
        if (! $canReview) {
            $historical->where('documento.usu_id', $request->user()->legacy_usu_id ?: $request->user()->getKey());
        }
        $historicalRows = $historical->get([
            'documento.doc_id as id', 'documento.usu_id', 'documento.doc_exter as plantel_nombre',
            'documento.trami_id as legacy_servicio_id', 'documento.num_doc as legacy_convocatoria_id',
            'documento.doc_estado as estado', 'documento.doc_designado as designado',
            'documento.doc_respuesta as respuesta', 'documento.fech_concluido as finalizado_at',
            'documento.fech_crea as enviado_at', 'usuario.usu_area as solicitante',
            'usuario.usu_correo as correo', 'usuario.usu_telf as telefono',
            'convocatoria.cat_nom as convocatoria_nombre', 'servicio.trami_nom as servicio_nombre',
            'plantel.area_nom as plantel_real',
        ]);

        $contacts = DB::table('ccyf_usuarios')
            ->whereIn('usu_id', $localRows->pluck('usu_id')->unique()->all())
            ->get(['usu_id', 'usu_correo', 'usu_telf'])->keyBy('usu_id');
        $services = DB::table('ccyf_tipos_servicio')->whereNotNull('legacy_trami_id')
            ->pluck('id', 'legacy_trami_id');
        $convocations = DB::table('ccyf_convocatorias')->whereNotNull('legacy_cat_id')
            ->pluck('id', 'legacy_cat_id');

        $localRows->each(function ($row) use ($contacts): void {
            $contact = $contacts->get($row->usu_id);
            $row->correo = $contact?->usu_correo;
            $row->telefono = $contact?->usu_telf;
            $row->origen = 'actual';
            $row->fecha_orden = $row->finalizado_at;
            $row->estado_texto = $this->stateText($row->decision, $row->respuesta);
            $row->folio_original = $row->folio;
            $row->documento_url = route('revision.show', $row->id);
        });
        $historicalRows->each(function ($row) use ($services, $convocations): void {
            $row->folio = 'HIST-'.str_pad((string) $row->id, 5, '0', STR_PAD_LEFT);
            $row->folio_original = $row->enviado_at
                ? date('m-Y', strtotime($row->enviado_at)).'-'.$row->id : (string) $row->id;
            $row->plantel_nombre = $row->plantel_nombre ?: $row->plantel_real;
            $row->servicio_id = $services->get($row->legacy_servicio_id);
            $row->convocatoria_id = $convocations->get($row->legacy_convocatoria_id);
            $row->decision = (int) $row->designado === 1 ? 'Designado'
                : (str_contains(mb_strtolower((string) $row->respuesta), 'no aceptad') ? 'no_aceptado' : 'No designado');
            $row->origen = 'historico';
            $row->fecha_orden = $row->finalizado_at ?: $row->enviado_at;
            $row->estado_texto = $this->stateText($row->decision, $row->respuesta);
            $row->documento_url = route('revision.historical', $row->id);
        });

        $rows = $localRows->concat($historicalRows);
        if ($request->filled('servicio')) {
            $rows = $rows->filter(fn ($row) => (int) $row->servicio_id === (int) $request->input('servicio'));
        }
        if ($request->filled('convocatoria')) {
            $rows = $rows->filter(fn ($row) => (int) $row->convocatoria_id === (int) $request->input('convocatoria'));
        }
        if ($request->filled('buscar')) {
            $search = mb_strtolower(trim((string) $request->input('buscar')));
            $rows = $rows->filter(function ($row) use ($search): bool {
                foreach (['folio', 'folio_original', 'solicitante', 'correo', 'telefono', 'servicio_nombre', 'convocatoria_nombre', 'plantel_nombre', 'estado_texto'] as $key) {
                    if (str_contains(mb_strtolower((string) ($row->{$key} ?? '')), $search)) {
                        return true;
                    }
                }
                return false;
            });
        }

        return $rows->sortByDesc('fecha_orden')->values();
    }

    private function stateText(?string $decision, ?string $answer): string
    {
        if ($decision === 'no_aceptado' || str_contains(mb_strtolower((string) $answer), 'no aceptad') || str_contains(mb_strtolower((string) $answer), 'no acepta')) {
            return 'Finalizado - Propuesta no aceptada';
        }
        if (in_array($decision, ['designado', 'Designado'], true) || str_contains(mb_strtolower((string) $answer), 'aceptad') || str_contains(mb_strtolower((string) $answer), 'acepta')) {
            return 'Finalizado - Propuesta aceptada';
        }
        return 'Finalizado';
    }
}
