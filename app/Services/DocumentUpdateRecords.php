<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DocumentUpdateRecords
{
    public function all(): Collection
    {
        $accounts = DB::table('ccyf_usuarios')->whereNotNull('legacy_usu_id')->get()->keyBy('legacy_usu_id');
        $historical = DB::connection('legacy')->table('tm_documento as d')
            ->leftJoin('tm_usuario as u', 'u.usu_id', '=', 'd.usu_id')
            ->leftJoin('tm_categoria_widi as c', 'c.cat_id', '=', 'd.num_doc')
            ->whereIn('d.trami_id', [3, 4])
            ->get(['d.doc_id', 'd.usu_id', 'd.trami_id', 'd.doc_exter', 'd.fech_crea', 'd.doc_estado',
                'u.usu_area', 'u.usu_correo', 'u.usu_telf', 'u.rfc', 'u.ine', 'u.ine2', 'c.cat_nom'])
            ->map(function ($item) use ($accounts): object {
                $account = $accounts->get($item->usu_id);
                return (object) [
                    'key' => 'historico-'.$item->doc_id, 'origin' => 'historico',
                    'registration_id' => (int) $item->doc_id, 'owner_id' => (int) $item->usu_id,
                    'service' => (int) $item->trami_id === 3 ? 'cafeteria' : 'fotocopiado',
                    'service_id' => null, 'service_name' => (int) $item->trami_id === 3 ? 'Cafetería' : 'Fotocopiado',
                    'convocation' => $item->cat_nom, 'campus' => $item->doc_exter,
                    'name' => $account?->usu_area ?: $item->usu_area,
                    'email' => $account?->usu_correo ?: $item->usu_correo,
                    'phone' => $account?->usu_telf ?: $item->usu_telf,
                    'rfc' => $account?->rfc ?: $item->rfc,
                    'curp' => $account?->curp ?: $item->ine,
                    'ine' => $account?->ine_clave ?: $item->ine2,
                    'created_at' => $item->fech_crea, 'state' => $item->doc_estado,
                ];
            });

        $actual = DB::table('ccyf_registros as r')
            ->join('ccyf_tipos_servicio as s', 's.id', '=', 'r.servicio_id')
            ->join('ccyf_convocatorias as c', 'c.id', '=', 'r.convocatoria_id')
            ->join('ccyf_planteles as p', 'p.id', '=', 'r.plantel_id')
            ->leftJoin('ccyf_usuarios as u', 'u.usu_id', '=', 'r.usu_id')
            ->whereIn('s.legacy_trami_id', [3, 4])
            ->get(['r.id', 'r.usu_id', 'r.servicio_id', 'r.solicitante', 'r.estado', 'r.enviado_at',
                's.legacy_trami_id', 's.nombre as servicio_nombre', 'c.numero as convocatoria', 'p.nombre as plantel',
                'u.usu_area', 'u.usu_correo', 'u.usu_telf', 'u.rfc', 'u.curp', 'u.ine_clave'])
            ->map(fn ($item): object => (object) [
                'key' => 'actual-'.$item->id, 'origin' => 'actual', 'registration_id' => (int) $item->id,
                'owner_id' => (int) $item->usu_id,
                'service' => (int) $item->legacy_trami_id === 3 ? 'cafeteria' : 'fotocopiado',
                'service_id' => (int) $item->servicio_id, 'service_name' => $item->servicio_nombre,
                'convocation' => $item->convocatoria, 'campus' => $item->plantel,
                'name' => $item->solicitante ?: $item->usu_area,
                'email' => $item->usu_correo, 'phone' => $item->usu_telf,
                'rfc' => $item->rfc, 'curp' => $item->curp, 'ine' => $item->ine_clave,
                'created_at' => $item->enviado_at, 'state' => $item->estado,
            ]);

        return $historical->concat($actual)->sortByDesc('created_at')->values();
    }

    public function find(string $key): ?object
    {
        return $this->all()->firstWhere('key', $key);
    }

    public function fields(object $record, DocumentFiles $files): Collection
    {
        if ($record->origin === 'historico') {
            $definitions = collect(PrevaluationCatalog::documents($record->service))
                ->map(fn ($name, $key) => (object) ['key' => $key, 'name' => $name])->values();
        } else {
            $definitions = DB::table('ccyf_requisitos_documento')->where('servicio_id', $record->service_id)
                ->where('activo', true)->orderBy('orden')->get(['id', 'nombre'])
                ->map(fn ($item) => (object) ['key' => 'req-'.$item->id, 'name' => $item->nombre]);
        }
        return $definitions->map(function ($definition) use ($record, $files): object {
            $effective = $files->effective($record->origin, $record->registration_id, $definition->key);
            $original = $files->original($record->origin, $record->registration_id, $definition->key);
            return (object) ['key' => $definition->key, 'name' => $definition->name,
                'current' => $effective, 'original' => $original,
                'updated' => $effective && $effective['source'] === 'update'];
        });
    }

    public function versions(object $record): Collection
    {
        return DB::table('ccyf_document_updates as rev')
            ->leftJoin('ccyf_usuarios as author', 'author.usu_id', '=', 'rev.uploaded_by')
            ->where('rev.origin', $record->origin)->where('rev.registration_id', $record->registration_id)
            ->orderByDesc('rev.id')->get(['rev.*', 'author.usu_area as uploader']);
    }
}
