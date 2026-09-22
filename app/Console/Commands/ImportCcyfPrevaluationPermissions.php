<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportCcyfPrevaluationPermissions extends Command
{
    protected $signature = 'ccyf:import-prevaluacion-permisos';

    protected $description = 'Import the prevaluator and administrator menu grants without overwriting local changes';

    public function handle(): int
    {
        $grants = DB::connection('legacy')->table('td_medu_detalle as d')
            ->join('tm_mennu as m', 'm.men_id', '=', 'd.men_id')
            ->whereIn('m.men_nom', ['prevaluacion', 'Prevaluaciones_admin'])->where('m.est', 1)
            ->where('d.mend_permi', 'si')->get(['d.rol_id', 'm.men_nom']);
        $count = 0;
        foreach ($grants as $grant) {
            $role = DB::table('ccyf_roles')->where('legacy_rol_id', $grant->rol_id)->value('rol_id');
            if (! $role) continue;
            $count += DB::table('ccyf_role_permissions')->insertOrIgnore([
                'rol_id' => $role, 'menu_key' => $grant->men_nom, 'allowed' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->components->info("Permisos de prevaluación nuevos: {$count}.");
        return self::SUCCESS;
    }
}
