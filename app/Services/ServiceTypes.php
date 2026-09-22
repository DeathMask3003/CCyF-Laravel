<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceTypes
{
    /** Copy missing legacy services once; local edits are never overwritten. */
    public function importLegacy(): int
    {
        $legacy = DB::connection('legacy')->table('tm_tramite')
            ->orderBy('trami_id')
            ->get(['trami_id', 'trami_nom', 'trami_descrip', 'est', 'fech_crea', 'fech_modif']);

        return DB::transaction(function () use ($legacy): int {
            $imported = 0;
            foreach ($legacy as $service) {
                if (DB::table('ccyf_tipos_servicio')->where('legacy_trami_id', $service->trami_id)->exists()) {
                    continue;
                }

                DB::table('ccyf_tipos_servicio')->insert([
                    'legacy_trami_id' => $service->trami_id,
                    'nombre' => $service->trami_nom,
                    'nombre_clave' => mb_strtolower(trim($service->trami_nom)),
                    'descripcion' => $service->trami_descrip,
                    'plantilla' => $this->templateFor((int) $service->trami_id, (string) $service->trami_nom),
                    'activo' => (bool) $service->est,
                    'created_at' => $service->fech_crea ?: now(),
                    'updated_at' => $service->fech_modif ?: ($service->fech_crea ?: now()),
                ]);
                $imported++;
            }

            $this->linkExistingCatalogs();

            return $imported;
        });
    }

    public function linkExistingCatalogs(): void
    {
        foreach (['cafeteria' => 3, 'fotocopiado' => 4] as $template => $legacyId) {
            $serviceId = DB::table('ccyf_tipos_servicio')->where('legacy_trami_id', $legacyId)->value('id');
            if ($serviceId) {
                DB::table('ccyf_catalogos')->whereNull('servicio_id')->where('tipo', $template)
                    ->update(['servicio_id' => $serviceId, 'updated_at' => now()]);
            }
        }
    }

    private function templateFor(int $legacyId, string $name): ?string
    {
        if ($legacyId === 3) {
            return 'cafeteria';
        }
        if ($legacyId === 4) {
            return 'fotocopiado';
        }

        $name = Str::lower($name);
        return Str::contains($name, 'cafeter') && ! Str::contains($name, 'fotocop') ? 'cafeteria'
            : (Str::contains($name, 'fotocop') && ! Str::contains($name, 'cafeter') ? 'fotocopiado' : null);
    }
}
