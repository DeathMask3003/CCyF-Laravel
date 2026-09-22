<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportCcyfContractPermissions extends Command
{
    protected $signature = 'ccyf:import-contratos-permisos';

    protected $description = 'Import UJEIG contract grants without replacing local role choices';

    public function handle(): int
    {
        $grants = DB::connection('legacy')->table('td_medu_detalle as d')
            ->join('tm_mennu as m', 'm.men_id', '=', 'd.men_id')
            ->where('m.men_nom', 'Permisionarios_aceptados_vujeig')->where('m.est', 1)
            ->get(['d.rol_id', 'd.mend_permi']);
        $count = 0;
        foreach ($grants as $grant) {
            $role = DB::table('ccyf_roles')->where('legacy_rol_id', $grant->rol_id)->value('rol_id');
            if (! $role) continue;
            $count += DB::table('ccyf_role_permissions')->insertOrIgnore([
                'rol_id' => $role, 'menu_key' => 'Permisionarios_aceptados_vujeig',
                'allowed' => $grant->mend_permi === 'si', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $root = DB::table('ccyf_roles')->where('legacy_rol_id', 3)->value('rol_id');
        if ($root) {
            $count += DB::table('ccyf_role_permissions')->insertOrIgnore([
                'rol_id' => $root, 'menu_key' => 'Permisionarios_aceptados_vujeig',
                'allowed' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->components->info("Permisos de contratos nuevos: {$count}.");
        return self::SUCCESS;
    }
}
