<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Support\LegacyTimestamps;

class CcyfStructure
{
    public function importLegacy(): array
    {
        return DB::transaction(function (): array {
            $campuses = $this->importCampuses();
            $convocations = $this->importConvocations();
            $assignments = $this->importAssignments();

            return compact('campuses', 'convocations', 'assignments');
        });
    }

    public function key(string $value): string
    {
        return Str::lower(Str::ascii(trim(preg_replace('/\s+/', ' ', $value) ?? $value)));
    }

    private function importCampuses(): int
    {
        $rows = DB::connection('legacy')->table('tm_areas')
            ->where(function ($query): void {
                $query->where('area_nom', 'like', 'Plantel %')
                    ->orWhere('area_nom', 'like', 'Cemsad %')
                    ->orWhere('area_nom', 'like', 'CEMSaD %');
            })->orderBy('area_id')->get();
        $imported = 0;
        foreach ($rows as $row) {
            if (DB::table('ccyf_planteles')->where('legacy_area_id', $row->area_id)->exists()) {
                continue;
            }
            DB::table('ccyf_planteles')->insert([
                'id' => $row->area_id,
                'legacy_area_id' => $row->area_id,
                'nombre' => $row->area_nom,
                'nombre_clave' => $this->key($row->area_nom),
                'correo' => $row->area_correo,
                'direccion' => $row->direccion_plantel,
                'activo' => (bool) $row->est,
                ...LegacyTimestamps::from($row),
            ]);
            $this->importCampusService($row, 3, 'espacio', 'matricula', 'monto', 'garantia');
            $this->importCampusService($row, 4, 'espacio_foto', 'matricula_foto', 'monto_foto', 'garantia_foto');
            $imported++;
        }

        return $imported;
    }

    private function importCampusService(object $row, int $legacyService, string $space, string $enrolment, string $amount, string $deposit): void
    {
        if ($row->{$space} === null && $row->{$enrolment} === null && $row->{$amount} === null && $row->{$deposit} === null) {
            return;
        }
        $serviceId = DB::table('ccyf_tipos_servicio')->where('legacy_trami_id', $legacyService)->value('id');
        if (! $serviceId) {
            return;
        }
        DB::table('ccyf_plantel_servicios')->insert([
            'plantel_id' => $row->area_id,
            'servicio_id' => $serviceId,
            'espacio' => $row->{$space},
            'matricula' => $row->{$enrolment},
            'monto' => $row->{$amount},
            'garantia' => $row->{$deposit},
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function importConvocations(): int
    {
        $rows = DB::connection('legacy')->table('tm_categoria_widi')->orderBy('cat_id')->get();
        $imported = 0;
        foreach ($rows as $row) {
            if (DB::table('ccyf_convocatorias')->where('legacy_cat_id', $row->cat_id)->exists()) {
                continue;
            }
            $serviceId = DB::table('ccyf_catalogos')->where('legacy_cat_id', $row->cat_id)->value('servicio_id')
                ?: $this->inferService((int) $row->cat_id, (string) $row->cat_nom);
            DB::table('ccyf_convocatorias')->insert([
                'id' => $row->cat_id,
                'legacy_cat_id' => $row->cat_id,
                'numero' => trim($row->cat_nom),
                'numero_clave' => $this->key($row->cat_nom),
                'servicio_id' => $serviceId,
                'activo' => (bool) $row->est,
                ...LegacyTimestamps::from($row),
            ]);
            DB::table('ccyf_catalogos')->where('legacy_cat_id', $row->cat_id)->update([
                'convocatoria_id' => $row->cat_id,
                ...($serviceId ? ['servicio_id' => $serviceId] : []),
                'updated_at' => now(),
            ]);
            $imported++;
        }

        return $imported;
    }

    private function importAssignments(): int
    {
        $rows = DB::connection('legacy')->table('tm_subcategoria')->whereNotNull('cats_nom')->get(['cat_id', 'cats_nom']);
        $inserted = 0;
        foreach ($rows as $row) {
            $convocationId = DB::table('ccyf_convocatorias')->where('legacy_cat_id', $row->cat_id)->value('id');
            if (! $convocationId) {
                continue;
            }
            foreach (array_filter(array_map('trim', explode(',', $row->cats_nom))) as $name) {
                $campusId = DB::table('ccyf_planteles')->where('nombre_clave', $this->key($name))->value('id');
                if ($campusId && ! DB::table('ccyf_convocatoria_planteles')->where([
                    'convocatoria_id' => $convocationId, 'plantel_id' => $campusId,
                ])->exists()) {
                    DB::table('ccyf_convocatoria_planteles')->insert([
                        'convocatoria_id' => $convocationId,
                        'plantel_id' => $campusId,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $inserted++;
                }
            }
        }

        return $inserted;
    }

    private function inferService(int $categoryId, string $name): ?int
    {
        $legacyService = DB::connection('legacy')->table('tm_documento')->where('num_doc', (string) $categoryId)
            ->selectRaw('trami_id, count(*) total')->groupBy('trami_id')->orderByDesc('total')->value('trami_id');
        if ($legacyService) {
            return DB::table('ccyf_tipos_servicio')->where('legacy_trami_id', $legacyService)->value('id');
        }
        $template = str_contains($this->key($name), 'cafeter') ? 'cafeteria'
            : (str_contains($this->key($name), 'fotocop') ? 'fotocopiado' : null);

        return $template ? DB::table('ccyf_tipos_servicio')->where('plantilla', $template)->value('id') : null;
    }
}
