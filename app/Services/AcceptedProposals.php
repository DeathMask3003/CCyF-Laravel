<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AcceptedProposals
{
    public function all(): Collection
    {
        $accounts = DB::table('ccyf_usuarios')->whereNotNull('legacy_usu_id')->get()->keyBy('legacy_usu_id');
        $campuses = DB::connection('legacy')->table('tm_areas')->get(['area_nom', 'direccion_plantel'])
            ->keyBy('area_nom');
        $historical = DB::connection('legacy')->table('tm_documento as d')
            ->leftJoin('tm_usuario as u', 'u.usu_id', '=', 'd.usu_id')
            ->leftJoin('tm_categoria_widi as c', 'c.cat_id', '=', 'd.num_doc')
            ->whereIn('d.trami_id', [3, 4])->where('d.doc_estado', 'Finalizado')->where('d.est', 0)
            ->where('d.doc_respuesta', 'like', '%aceptad%')
            ->whereRaw('lower(d.doc_respuesta) not like ?', ['%no aceptad%'])
            ->get(['d.doc_id', 'd.usu_id', 'd.trami_id', 'd.doc_exter', 'd.num_doc', 'd.fech_crea',
                'd.doc_fech_ini', 'd.doc_fech_fin', 'd.monto', 'd.doc_respuesta',
                'd.contrato_enviado', 'd.contrato_fech_envio', 'u.usu_area', 'u.usu_correo',
                'u.usu_telf', 'u.direcc', 'u.ine2', 'u.cont_alter', 'u.telf_alter', 'c.cat_nom'])
            ->map(function ($row) use ($accounts, $campuses): object {
                $account = $accounts->get($row->usu_id);
                return (object) [
                    'key' => 'historico-'.$row->doc_id, 'origin' => 'historico',
                    'id' => (int) $row->doc_id, 'service_id' => (int) $row->trami_id,
                    'service' => (int) $row->trami_id === 3 ? 'cafeteria' : 'fotocopiado',
                    'folio' => $row->fech_crea ? date('m-Y', strtotime($row->fech_crea)).'-'.$row->doc_id : (string) $row->doc_id,
                    'name' => $account?->usu_area ?: $row->usu_area,
                    'email' => $account?->usu_correo ?: $row->usu_correo,
                    'phone' => $account?->usu_telf ?: $row->usu_telf,
                    'address' => $account?->direcc ?: $row->direcc,
                    'ine' => $account?->ine_clave ?: $row->ine2,
                    'alternate' => $row->cont_alter, 'alternate_phone' => $row->telf_alter,
                    'campus' => $row->doc_exter, 'campus_address' => $campuses->get($row->doc_exter)?->direccion_plantel,
                    'convocation' => $row->cat_nom ?: $row->num_doc, 'amount' => $row->monto,
                    'starts' => $row->doc_fech_ini, 'ends' => $row->doc_fech_fin,
                    'created_at' => $row->fech_crea,
                    'legacy_sent' => (bool) $row->contrato_enviado,
                    'legacy_sent_at' => $row->contrato_enviado ? $row->contrato_fech_envio : null,
                ];
            });

        $current = DB::table('ccyf_registros as r')
            ->join('ccyf_tipos_servicio as s', 's.id', '=', 'r.servicio_id')
            ->join('ccyf_convocatorias as c', 'c.id', '=', 'r.convocatoria_id')
            ->join('ccyf_planteles as p', 'p.id', '=', 'r.plantel_id')
            ->leftJoin('ccyf_usuarios as u', 'u.usu_id', '=', 'r.usu_id')
            ->where('r.estado', 'Finalizado')->whereIn('s.legacy_trami_id', [3, 4])
            ->where(function ($query): void {
                $query->whereIn('r.decision', ['designado', 'Designado'])
                    ->orWhere(function ($accepted): void {
                        $accepted->where('r.respuesta', 'like', '%aceptad%')
                            ->whereRaw('lower(r.respuesta) not like ?', ['%no aceptad%']);
                    });
            })
            ->get(['r.id', 'r.folio', 'r.solicitante', 'r.enviado_at', 'r.fecha_inicio', 'r.fecha_fin',
                'r.monto', 's.legacy_trami_id', 'c.numero as convocatoria', 'p.nombre as plantel',
                'p.direccion as plantel_direccion', 'u.usu_area', 'u.usu_correo', 'u.usu_telf',
                'u.direcc', 'u.ine_clave'])
            ->map(fn ($row): object => (object) [
                'key' => 'actual-'.$row->id, 'origin' => 'actual', 'id' => (int) $row->id,
                'service_id' => (int) $row->legacy_trami_id,
                'service' => (int) $row->legacy_trami_id === 3 ? 'cafeteria' : 'fotocopiado',
                'folio' => $row->folio ?: 'CCYF-'.str_pad((string) $row->id, 5, '0', STR_PAD_LEFT),
                'name' => $row->solicitante ?: $row->usu_area, 'email' => $row->usu_correo,
                'phone' => $row->usu_telf, 'address' => $row->direcc, 'ine' => $row->ine_clave,
                'alternate' => null, 'alternate_phone' => null,
                'campus' => $row->plantel, 'campus_address' => $row->plantel_direccion,
                'convocation' => $row->convocatoria, 'amount' => $row->monto,
                'starts' => $row->fecha_inicio, 'ends' => $row->fecha_fin,
                'created_at' => $row->enviado_at, 'legacy_sent' => false, 'legacy_sent_at' => null,
            ]);

        $deliveries = DB::table('ccyf_contract_deliveries')->get()->keyBy(fn ($item) => $item->origin.'-'.$item->registration_id);
        $terms = DB::table('ccyf_contract_terms')->orderByDesc('id')->get()
            ->unique(fn ($item) => $item->origin.'-'.$item->registration_id)
            ->keyBy(fn ($item) => $item->origin.'-'.$item->registration_id);
        return $historical->concat($current)->map(function ($row) use ($deliveries, $terms): object {
            $row->terms = $terms->get($row->key);
            if ($row->terms) {
                foreach (['amount','starts','ends','campus_address','email','phone','address','ine','alternate','alternate_phone'] as $field) {
                    if ($row->terms->{$field} !== null) $row->{$field} = $row->terms->{$field};
                }
            }
            $row->delivery = $deliveries->get($row->key);
            $row->sent_at = $row->legacy_sent_at ?: $row->delivery?->sent_at;
            $row->sent = $row->legacy_sent || (bool) $row->delivery?->sent_at;
            return $row;
        })->sortByDesc('created_at')->values();
    }

    public function find(string $key): ?object
    {
        return $this->all()->firstWhere('key', $key);
    }

    public function missing(object $row, bool $forSending = false): array
    {
        $fields = ['name' => 'Nombre del permisionario', 'campus' => 'Plantel',
            'campus_address' => 'Dirección del plantel', 'amount' => 'Monto mensual',
            'starts' => 'Inicio de vigencia', 'ends' => 'Fin de vigencia'];
        if ($forSending) $fields['email'] = 'Correo electrónico';
        $missing = [];
        foreach ($fields as $property => $label) {
            if ($row->{$property} === null || trim((string) $row->{$property}) === '') $missing[] = $label;
        }
        if ($row->starts && $row->ends && strtotime($row->ends) <= strtotime($row->starts)) {
            $missing[] = 'Vigencia válida';
        }
        if ($row->amount !== null && (float) $row->amount <= 0) $missing[] = 'Monto mensual mayor a cero';
        if ($forSending && $row->email && ! filter_var($row->email, FILTER_VALIDATE_EMAIL)) $missing[] = 'Correo electrónico válido';
        return $missing;
    }
}
