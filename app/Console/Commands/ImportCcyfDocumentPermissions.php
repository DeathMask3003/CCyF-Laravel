<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportCcyfDocumentPermissions extends Command
{
    protected $signature = 'ccyf:import-documentacion-permisos';

    protected $description = 'Import documentation menu grants without overwriting local permission choices';

    public function handle(): int
    {
        $grants = DB::connection('legacy')->table('td_medu_detalle as d')
            ->join('tm_mennu as m', 'm.men_id', '=', 'd.men_id')
            ->where('m.men_nom', 'actualiza_docs')->where('m.est', 1)
            ->get(['d.rol_id', 'd.mend_permi']);
        $count = 0;
        foreach ($grants as $grant) {
            $roleId = DB::table('ccyf_roles')->where('legacy_rol_id', $grant->rol_id)->value('rol_id');
            if (! $roleId) continue;
            $count += DB::table('ccyf_role_permissions')->insertOrIgnore([
                'rol_id' => $roleId, 'menu_key' => 'actualiza_docs',
                'allowed' => $grant->mend_permi === 'si', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $root = DB::table('ccyf_roles')->where('legacy_rol_id', 3)->value('rol_id');
        if ($root) {
            $count += DB::table('ccyf_role_permissions')->insertOrIgnore([
                'rol_id' => $root, 'menu_key' => 'actualiza_docs', 'allowed' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->components->info("Permisos de documentación nuevos: {$count}.");
        return self::SUCCESS;
    }
}
