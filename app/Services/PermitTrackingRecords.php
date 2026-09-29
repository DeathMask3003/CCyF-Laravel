<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PermitTrackingRecords
{
    public function all(): Collection
    {
        $tracking = DB::table('ccyf_seguimientos')->get()->keyBy(fn ($row) => $row->origen.':'.$row->registro_id);
        $files = DB::table('ccyf_seguimiento_archivos')->get()->groupBy('seguimiento_id');
        $accounts = DB::table('ccyf_usuarios')->whereNotNull('legacy_usu_id')->get()->keyBy('legacy_usu_id');

        $old = DB::connection('legacy')->table('tm_documento as d')
            ->join('tm_categoria_widi as c', 'c.cat_id', '=', 'd.num_doc')
            ->leftJoin('tm_areas as a', 'a.area_nom', '=', 'd.doc_exter')
            ->leftJoin('tm_usuario as u', 'u.usu_id', '=', 'd.usu_id')
            ->whereIn('d.trami_id', [3, 4])
            ->whereNotNull('d.doc_fech_ini')->whereNotNull('d.doc_fech_fin')
            ->where('d.doc_fech_ini', '<>', '0000-00-00')->where('d.doc_fech_fin', '<>', '0000-00-00')
            ->get(['d.doc_id', 'd.trami_id', 'd.usu_id', 'd.doc_exter', 'd.doc_fech_ini', 'd.doc_fech_fin', 'd.doc_designado',
                'd.fech_crea', 'd.est', 'c.cat_nom', 'a.area_nom', 'u.usu_area', 'u.usu_correo',
                'u.usu_telf', 'u.ine', 'u.direcc']);

        $historical = $old->map(function ($row) use ($tracking, $files, $accounts): object {
            $follow = $tracking->get('historico:'.$row->doc_id);
            $account = $accounts->get($row->usu_id);
            return (object) [
                'key' => 'historico-'.$row->doc_id, 'origen' => 'historico', 'registro_id' => (int) $row->doc_id,
                'servicio' => (int) $row->trami_id === 3 ? 'cafeteria' : 'fotocopiado',
                'convocatoria' => $row->cat_nom, 'plantel' => $row->area_nom ?: $row->doc_exter,
                'nombre' => $account?->usu_area ?: $row->usu_area,
                'curp' => $account?->curp ?: $row->ine,
                'direccion' => $account?->direcc ?: $row->direcc,
                'telefono' => $account?->usu_telf ?: $row->usu_telf,
                'correo' => $account?->usu_correo ?: $row->usu_correo,
                'fecha_inicio' => $follow?->fecha_inicio_renovada ?: $row->doc_fech_ini,
                'fecha_fin' => $follow?->fecha_fin_renovada ?: $row->doc_fech_fin,
                'registro_at' => $row->fech_crea,
                'estado' => (int) $row->est === 0 ? 'Designado' : 'Sin datos',
                'doc_designado' => (int) $row->doc_designado,
                'seguimiento' => $follow, 'archivos' => $follow ? ($files->get($follow->id) ?: collect())->keyBy('numero') : collect(),
            ];
        });

        $new = DB::table('ccyf_registros as r')
            ->join('ccyf_tipos_servicio as s', 's.id', '=', 'r.servicio_id')
            ->join('ccyf_convocatorias as c', 'c.id', '=', 'r.convocatoria_id')
            ->join('ccyf_planteles as p', 'p.id', '=', 'r.plantel_id')
            ->leftJoin('ccyf_usuarios as u', 'u.usu_id', '=', 'r.usu_id')
            ->where('r.estado', 'Finalizado')->where('r.decision', 'designado')
            ->whereIn('s.legacy_trami_id', [3, 4])
            ->whereNotNull('r.fecha_inicio')->whereNotNull('r.fecha_fin')
            ->get(['r.id', 'r.usu_id', 'r.solicitante', 'r.fecha_inicio', 'r.fecha_fin', 'r.monto',
                'r.enviado_at', 's.legacy_trami_id', 'c.numero as convocatoria', 'p.nombre as plantel',
                'u.usu_area', 'u.usu_correo', 'u.usu_telf', 'u.curp', 'u.direcc']);

        $current = $new->map(function ($row) use ($tracking, $files): object {
            $follow = $tracking->get('actual:'.$row->id);
            return (object) [
                'key' => 'actual-'.$row->id, 'origen' => 'actual', 'registro_id' => (int) $row->id,
                'servicio' => (int) $row->legacy_trami_id === 3 ? 'cafeteria' : 'fotocopiado',
                'convocatoria' => $row->convocatoria, 'plantel' => $row->plantel,
                'nombre' => $row->usu_area ?: $row->solicitante, 'curp' => $row->curp,
                'direccion' => $row->direcc, 'telefono' => $row->usu_telf, 'correo' => $row->usu_correo,
                'fecha_inicio' => $follow?->fecha_inicio_renovada ?: $row->fecha_inicio,
                'fecha_fin' => $follow?->fecha_fin_renovada ?: $row->fecha_fin,
                'registro_at' => $row->enviado_at,
                'estado' => 'Designado',
                'seguimiento' => $follow, 'archivos' => $follow ? ($files->get($follow->id) ?: collect())->keyBy('numero') : collect(),
                'monto_base' => $row->monto,
            ];
        });

        return $historical->concat($current)->values();
    }

    public function filtered(Request $request): Collection
    {
        $service = $request->query('servicio', 'cafeteria');
        $items = $this->all()->where('servicio', $service);
        if ($request->filled('buscar')) {
            $term = mb_strtolower(trim((string) $request->query('buscar')));
            $items = $items->filter(function ($row) use ($term): bool {
                foreach (['registro_id', 'convocatoria', 'plantel', 'nombre', 'curp', 'direccion', 'telefono', 'correo', 'estado'] as $field) {
                    if (str_contains(mb_strtolower((string) $row->{$field}), $term)) {
                        return true;
                    }
                }
                return false;
            });
        }
        foreach (['mes_inicio' => 'fecha_inicio', 'mes_fin' => 'fecha_fin'] as $query => $field) {
            if ($request->filled($query)) {
                $month = (string) $request->query($query);
                $items = $items->filter(fn ($row) => str_starts_with((string) $row->{$field}, $month));
            }
        }

        $sort = (string) $request->query('orden', 'registro_at');
        $allowed = ['registro_at', 'registro_id', 'convocatoria', 'plantel', 'nombre', 'fecha_inicio', 'fecha_fin', 'monto', 'estado'];
        if (! in_array($sort, $allowed, true)) {
            $sort = 'registro_at';
        }
        $desc = $request->query('direccion', 'desc') !== 'asc';
        return $items->sortBy(fn ($row) => $sort === 'monto' ? (float) ($row->seguimiento?->monto ?? $row->monto_base ?? 0) : $row->{$sort}, SORT_REGULAR, $desc)->values();
    }

    public function find(string $key): ?object
    {
        return $this->all()->first(fn ($row) => $row->key === $key);
    }
}
