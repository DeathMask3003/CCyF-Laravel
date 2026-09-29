<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ComplaintRecords
{
    public function participations(): Collection
    {
        $accounts = DB::table('ccyf_usuarios')->get(['usu_id', 'legacy_usu_id', 'usu_area', 'usu_correo'])
            ->keyBy('usu_id');
        $legacyAccounts = $accounts->filter(fn ($user) => $user->legacy_usu_id)->keyBy('legacy_usu_id');
        $legacyUsers = DB::connection('legacy')->table('tm_usuario')->get(['usu_id', 'usu_area', 'usu_correo'])->keyBy('usu_id');
        $calls = DB::connection('legacy')->table('tm_categoria_widi')->pluck('cat_nom', 'cat_id');
        $legacyRatings = DB::connection('legacy')->table('tm_permisionario_queja')
            ->where('queja_estado', 1)->get(['doc_id', 'queja_calificacion', 'creado_en'])->groupBy('doc_id');
        $localRatings = DB::table('ccyf_quejas_participacion')->where('activo', true)
            ->get(['origen', 'registro_id', 'calificacion', 'created_at'])
            ->groupBy(fn ($row) => $row->origen.':'.$row->registro_id);

        $historical = DB::connection('legacy')->table('tm_documento')->whereIn('trami_id', [3, 4])
            ->whereNotNull('doc_exter')->where('doc_exter', '<>', '')
            ->get(['doc_id', 'usu_id', 'trami_id', 'num_doc', 'doc_exter', 'fech_crea'])
            ->map(function ($row) use ($legacyUsers, $legacyAccounts, $calls, $legacyRatings, $localRatings): object {
                $account = $legacyAccounts->get($row->usu_id);
                $user = $legacyUsers->get($row->usu_id);
                $ratings = ($legacyRatings->get($row->doc_id) ?: collect())
                    ->concat($localRatings->get('historico:'.$row->doc_id) ?: collect());
                return $this->makeParticipation('historico', (int) $row->doc_id,
                    $account ? 'local-'.$account->usu_id : 'legacy-'.$row->usu_id,
                    $account?->usu_area ?: ($user?->usu_area ?: 'Permisionario #'.$row->usu_id),
                    $account?->usu_correo ?: $user?->usu_correo,
                    (int) $row->trami_id, $calls->get($row->num_doc) ?: 'Convocatoria '.$row->num_doc,
                    $row->doc_exter, $row->fech_crea, $ratings);
            });

        $current = DB::table('ccyf_registros as r')
            ->join('ccyf_planteles as p', 'p.id', '=', 'r.plantel_id')
            ->join('ccyf_convocatorias as c', 'c.id', '=', 'r.convocatoria_id')
            ->join('ccyf_tipos_servicio as s', 's.id', '=', 'r.servicio_id')
            ->whereIn('s.legacy_trami_id', [3, 4])
            ->get(['r.id', 'r.usu_id', 'r.solicitante', 'r.enviado_at', 'p.nombre as plantel',
                'c.numero as convocatoria', 's.legacy_trami_id'])
            ->map(function ($row) use ($accounts, $localRatings): object {
                $account = $accounts->get($row->usu_id);
                return $this->makeParticipation('actual', (int) $row->id, 'local-'.$row->usu_id,
                    $account?->usu_area ?: $row->solicitante, $account?->usu_correo,
                    (int) $row->legacy_trami_id, $row->convocatoria, $row->plantel,
                    $row->enviado_at, $localRatings->get('actual:'.$row->id) ?: collect());
            });

        return $historical->concat($current)->sortByDesc('fecha')->values();
    }

    public function participation(string $origin, int $id): ?object
    {
        return $this->participations()->first(fn ($row) => $row->origen === $origin && $row->registro_id === $id);
    }

    public function history(object $participation): Collection
    {
        $rows = collect();
        if ($participation->origen === 'historico') {
            $authors = DB::connection('legacy')->table('tm_usuario')->pluck('usu_area', 'usu_id');
            $rows = DB::connection('legacy')->table('tm_permisionario_queja')
                ->where('doc_id', $participation->registro_id)->where('queja_estado', 1)
                ->orderByDesc('queja_id')->get()
                ->map(fn ($row) => (object) [
                    'origen' => 'historico', 'id' => (int) $row->queja_id,
                    'observacion' => $row->queja_obs, 'calificacion' => (int) $row->queja_calificacion,
                    'fecha' => $row->creado_en ?: $row->queja_fecha,
                    'autor' => $authors->get($row->creado_por) ?: 'Usuario histórico',
                    'evidencia' => (bool) $row->queja_evidencia,
                    'evidencia_nombre' => $row->queja_evidencia,
                ]);
        }
        $authors = DB::table('ccyf_usuarios')->pluck('usu_area', 'usu_id');
        $local = DB::table('ccyf_quejas_participacion')
            ->where('origen', $participation->origen)->where('registro_id', $participation->registro_id)
            ->where('activo', true)->orderByDesc('id')->get()
            ->map(fn ($row) => (object) [
                'origen' => 'local', 'id' => (int) $row->id,
                'observacion' => $row->observacion, 'calificacion' => (int) $row->calificacion,
                'fecha' => $row->created_at, 'autor' => $authors->get($row->creado_por) ?: 'Usuario CCyF',
                'evidencia' => (bool) $row->evidencia_ruta,
                'evidencia_nombre' => $row->evidencia_nombre,
            ]);
        return $rows->concat($local)->sortByDesc('fecha')->values();
    }

    public function manual(): Collection
    {
        $historical = DB::connection('legacy')->table('tm_permisionario_queja_manual as q')
            ->leftJoin('tm_areas as a', 'a.area_id', '=', 'q.area_id')
            ->leftJoin('tm_usuario as u', 'u.usu_id', '=', 'q.usu_id')
            ->get(['q.quejam_id', 'q.permisionario', 'q.queja', 'q.fech_crea', 'q.est',
                'a.area_nom', 'u.usu_area'])
            ->map(fn ($row) => (object) [
                'origen' => 'historico', 'id' => (int) $row->quejam_id,
                'permisionario' => $row->permisionario, 'queja' => $row->queja,
                'plantel' => $row->area_nom ?: 'Plantel no disponible',
                'autor' => $row->usu_area ?: 'Usuario histórico',
                'fecha' => $row->fech_crea, 'activo' => (bool) $row->est,
            ]);
        $local = DB::table('ccyf_quejas_manuales as q')
            ->join('ccyf_planteles as p', 'p.id', '=', 'q.plantel_id')
            ->leftJoin('ccyf_usuarios as u', 'u.usu_id', '=', 'q.creado_por')
            ->get(['q.id', 'q.permisionario', 'q.queja', 'q.created_at', 'q.activo',
                'p.nombre as plantel', 'u.usu_area'])
            ->map(fn ($row) => (object) [
                'origen' => 'local', 'id' => (int) $row->id,
                'permisionario' => $row->permisionario, 'queja' => $row->queja,
                'plantel' => $row->plantel, 'autor' => $row->usu_area ?: 'Usuario CCyF',
                'fecha' => $row->created_at, 'activo' => (bool) $row->activo,
            ]);
        return $historical->concat($local)->sortByDesc('fecha')->values();
    }

    private function makeParticipation(string $origin, int $id, string $personKey, string $name, ?string $email,
        int $serviceId, string $call, string $campus, ?string $date, Collection $ratings): object
    {
        $good = $ratings->filter(fn ($rating) => (int) ($rating->queja_calificacion ?? $rating->calificacion) === 1)->count();
        $bad = $ratings->count() - $good;
        return (object) [
            'origen' => $origin, 'registro_id' => $id, 'key' => $origin.'-'.$id,
            'persona_key' => $personKey, 'nombre' => $name, 'correo' => $email,
            'servicio' => $serviceId === 3 ? 'cafeteria' : 'fotocopiado',
            'convocatoria' => $call, 'plantel' => $campus, 'fecha' => $date,
            'buenas' => $good, 'malas' => $bad, 'total' => $ratings->count(),
        ];
    }
}
