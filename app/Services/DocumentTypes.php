<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DocumentTypes
{
    /** Copy missing legacy types once; local edits are never overwritten. */
    public function importLegacy(): int
    {
        $legacy = DB::connection('legacy')->table('tm_tipo')
            ->orderBy('tipo_id')->get(['tipo_id', 'tipo_nom', 'est', 'fech_crea', 'fech_modif']);

        return DB::transaction(function () use ($legacy): int {
            $imported = 0;
            foreach ($legacy as $type) {
                if (DB::table('ccyf_tipos_documento')->where('legacy_tipo_id', $type->tipo_id)->exists()) {
                    continue;
                }

                DB::table('ccyf_tipos_documento')->insert([
                    'legacy_tipo_id' => $type->tipo_id,
                    'nombre' => $type->tipo_nom,
                    'nombre_clave' => mb_strtolower(trim($type->tipo_nom)),
                    'activo' => (bool) $type->est,
                    'created_at' => $type->fech_crea ?: now(),
                    'updated_at' => $type->fech_modif ?: ($type->fech_crea ?: now()),
                ]);
                $imported++;
            }

            return $imported;
        });
    }
}
