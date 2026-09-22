<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PrevaluationRecords
{
    public function __construct(private readonly DocumentFiles $files) {}

    public function all(): Collection
    {
        $localEvaluations = DB::table('ccyf_prevaluaciones')->get()->keyBy(fn ($item) => $item->origen.':'.$item->registro_id);
        $legacyEvaluations = collect();
        foreach (['cafeteria' => ['tm_preval_cafe_doc', 'id_preval_cafe'], 'fotocopiado' => ['tm_preval_foto_doc', 'id_preval_foto']] as $service => [$table, $id]) {
            $latest = DB::connection('legacy')->table($table)->where('est', 1)->where('campo_doc', '<>', 'observaciones_admin')
                ->orderByRaw('viable_estado IS NULL ASC')->orderByDesc($id)
                ->get(['doc_id', 'usu_preval', 'viable_estado', 'fecha_registro'])->unique('doc_id');
            foreach ($latest as $evaluation) {
                $legacyEvaluations->put($evaluation->doc_id, $evaluation);
            }
        }
        $accounts = DB::table('ccyf_usuarios')->whereNotNull('legacy_usu_id')->get()->keyBy('legacy_usu_id');
        $historical = DB::connection('legacy')->table('tm_documento as d')
            ->leftJoin('tm_usuario as u', 'u.usu_id', '=', 'd.usu_id')
            ->leftJoin('tm_categoria_widi as c', 'c.cat_id', '=', 'd.num_doc')
            ->whereIn('d.trami_id', [3, 4])
            ->get(['d.doc_id', 'd.trami_id', 'd.doc_exter', 'd.doc_estado', 'd.num_doc', 'd.fech_crea', 'd.usu_id',
                'u.usu_area', 'u.ine', 'u.usu_telf', 'c.cat_nom'])
            ->map(function ($item) use ($accounts, $localEvaluations, $legacyEvaluations): object {
                $local = $localEvaluations->get('historico:'.$item->doc_id);
                $legacy = $legacyEvaluations->get($item->doc_id);
                $account = $accounts->get($item->usu_id);
                return (object) [
                    'key' => 'historico-'.$item->doc_id, 'origen' => 'historico', 'registro_id' => (int) $item->doc_id,
                    'servicio' => (int) $item->trami_id === 3 ? 'cafeteria' : 'fotocopiado',
                    'convocatoria_id' => (int) $item->num_doc, 'convocatoria' => $item->cat_nom ?: $item->num_doc,
                    'plantel' => $item->doc_exter, 'nombre' => $account?->usu_area ?: $item->usu_area,
                    'curp' => $account?->curp ?: $item->ine, 'telefono' => $account?->usu_telf ?: $item->usu_telf,
                    'registro_at' => $item->fech_crea, 'estado' => $item->doc_estado,
                    'evaluado' => ($local?->resultado ?? $legacy?->viable_estado) !== null,
                    'resultado' => $local?->resultado ?? $legacy?->viable_estado,
                    'owner' => $local?->evaluador_id ?? $legacy?->usu_preval,
                ];
            });

        $current = DB::table('ccyf_registros as r')
            ->join('ccyf_tipos_servicio as s', 's.id', '=', 'r.servicio_id')
            ->join('ccyf_convocatorias as c', 'c.id', '=', 'r.convocatoria_id')
            ->join('ccyf_planteles as p', 'p.id', '=', 'r.plantel_id')
            ->leftJoin('ccyf_usuarios as u', 'u.usu_id', '=', 'r.usu_id')
            ->where('r.estado', 'Recibido')->whereIn('s.legacy_trami_id', [3, 4])
            ->get(['r.id', 'r.solicitante', 'r.estado', 'r.enviado_at', 's.legacy_trami_id',
                'c.id as convocatoria_id', 'c.numero as convocatoria', 'p.nombre as plantel',
                'u.usu_area', 'u.curp', 'u.usu_telf'])
            ->map(function ($item) use ($localEvaluations): object {
                $evaluation = $localEvaluations->get('actual:'.$item->id);
                return (object) [
                    'key' => 'actual-'.$item->id, 'origen' => 'actual', 'registro_id' => (int) $item->id,
                    'servicio' => (int) $item->legacy_trami_id === 3 ? 'cafeteria' : 'fotocopiado',
                    'convocatoria_id' => (int) $item->convocatoria_id, 'convocatoria' => $item->convocatoria,
                    'plantel' => $item->plantel, 'nombre' => $item->solicitante ?: $item->usu_area,
                    'curp' => $item->curp, 'telefono' => $item->usu_telf,
                    'registro_at' => $item->enviado_at, 'estado' => $item->estado,
                    'evaluado' => $evaluation?->resultado !== null, 'resultado' => $evaluation?->resultado,
                    'owner' => $evaluation?->evaluador_id,
                ];
            });

        return $historical->concat($current)->sortByDesc('registro_at')->values();
    }

    public function find(string $key): ?object
    {
        return $this->all()->first(fn ($item) => $item->key === $key);
    }

    public function detail(object $record): array
    {
        $evaluation = DB::table('ccyf_prevaluaciones')->where('origen', $record->origen)->where('registro_id', $record->registro_id)->first();
        $items = collect();
        $owner = $evaluation?->evaluador_id;
        $result = $evaluation?->resultado;

        if ($evaluation) {
            $items = DB::table('ccyf_prevaluacion_items')->where('prevaluacion_id', $evaluation->id)->get()->keyBy('clave');
        } elseif ($record->origen === 'historico') {
            [$table, $id] = $record->servicio === 'cafeteria' ? ['tm_preval_cafe_doc', 'id_preval_cafe'] : ['tm_preval_foto_doc', 'id_preval_foto'];
            $history = DB::connection('legacy')->table($table)->where('doc_id', $record->registro_id)->where('est', 1)
                ->where('campo_doc', '<>', 'observaciones_admin')->orderByDesc($id)->get();
            $items = $history->unique('campo_doc')->keyBy('campo_doc');
            $owner = $history->first()?->usu_preval;
            $result = $history->first(fn ($item) => $item->viable_estado !== null)?->viable_estado;
        }

        $note = DB::table('ccyf_prevaluacion_notas')->where('origen', $record->origen)->where('registro_id', $record->registro_id)->value('observaciones');
        if ($note === null && $record->origen === 'historico') {
            $table = $record->servicio === 'cafeteria' ? 'tm_preval_cafe_doc' : 'tm_preval_foto_doc';
            $note = DB::connection('legacy')->table($table)->where('doc_id', $record->registro_id)->where('est', 1)
                ->whereNotNull('observaciones_admin')->where('observaciones_admin', '<>', '')
                ->orderByDesc('fecha_observaciones_admin')->value('observaciones_admin');
        }

        return [
            'documents' => $this->documents($record), 'prices' => $this->prices($record),
            'items' => $items, 'owner' => $owner ? (int) $owner : null,
            'result' => $result ? (int) $result : null, 'adminNote' => $note,
        ];
    }

    public function documents(object $record): Collection
    {
        if ($record->origen === 'actual') {
            return DB::table('ccyf_requisitos_documento as req')
                ->join('ccyf_registros as r', 'r.servicio_id', '=', 'req.servicio_id')
                ->leftJoin('ccyf_registro_archivos as file', function ($join): void {
                    $join->on('file.requisito_id', '=', 'req.id')->on('file.registro_id', '=', 'r.id');
                })
                ->where('r.id', $record->registro_id)->where('req.activo', true)
                ->orderBy('req.orden')->get(['req.id', 'req.nombre', 'req.clave'])
                ->map(function ($item) use ($record): object {
                    $file = $this->files->effective('actual', $record->registro_id, 'req-'.$item->id);
                    return (object) ['clave' => 'req-'.$item->id, 'nombre' => $item->nombre,
                        'available' => $file !== null, 'mime' => $file['mime'] ?? null];
                });
        }

        return collect(PrevaluationCatalog::documents($record->servicio))
            ->map(function ($name, $key) use ($record): object {
                $file = $this->files->effective('historico', $record->registro_id, $key);
                return (object) ['clave' => $key, 'nombre' => $name,
                    'available' => $file !== null, 'mime' => $file['mime'] ?? null];
            })->values();
    }

    public function prices(object $record): Collection
    {
        if ($record->origen === 'actual') {
            return DB::table('ccyf_registro_precios')->where('registro_id', $record->registro_id)->orderBy('id')
                ->get(['producto_id', 'producto_nombre', 'precio', 'unidad'])
                ->map(fn ($item) => (object) [
                    'clave' => 'precio-'.$item->producto_id, 'nombre' => $item->producto_nombre,
                    'precio' => $item->precio, 'unidad' => $item->unidad,
                ]);
        }
        $table = $record->servicio === 'cafeteria' ? 'tm_documento_cafeteria' : 'tm_documento_fotocopiado';
        $row = DB::connection('legacy')->table($table)->where('doc_id', $record->registro_id)->where('est', 1)->first();
        return collect(PrevaluationCatalog::prices($record->servicio))
            ->filter(fn ($name, $key) => $row && isset($row->{$key}) && $row->{$key} !== '')
            ->map(fn ($name, $key) => (object) [
                'clave' => $record->servicio === 'cafeteria' ? $key : 'precio_'.$key,
                'nombre' => $name, 'precio' => $row->{$key}, 'unidad' => null,
            ])->values();
    }

    public function filePath(object $record, string $key): ?string
    {
        if (! $this->documents($record)->contains('clave', $key)) return null;
        return $this->files->effective($record->origen, $record->registro_id, $key)['path'] ?? null;
    }
}
