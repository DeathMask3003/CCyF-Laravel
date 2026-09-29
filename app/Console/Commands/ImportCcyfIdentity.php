<?php

namespace App\Console\Commands;

use App\Services\CcyfPermissions;
use App\Support\LegacyTimestamps;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportCcyfIdentity extends Command
{
    protected $signature = 'ccyf:import-identity';

    protected $description = 'Copy CCyF users, roles and relevant permissions into the local database once';

    public function handle(): int
    {
        if (DB::table('ccyf_usuarios')->exists() || DB::table('ccyf_roles')->exists()) {
            $this->components->warn('Las cuentas locales ya están inicializadas; no se sobrescribieron cambios.');
            return self::SUCCESS;
        }

        $roles = DB::connection('legacy')->table('tm_rol')->get(['rol_id', 'rol_nom', 'est', 'fech_crea', 'fech_modif']);
        $users = DB::connection('legacy')->table('tm_usuario')
            ->get(['usu_id', 'usu_area', 'usu_correo', 'usu_pass', 'rol_id', 'area_id', 'usu_telf', 'est', 'fech_crea', 'fech_modif']);
        $grants = DB::connection('legacy')->table('td_medu_detalle as detalle')
            ->join('tm_mennu as menu', 'menu.men_id', '=', 'detalle.men_id')
            ->where('menu.est', 1)->whereIn('menu.men_nom', array_keys(CcyfPermissions::MODULES))
            ->get(['detalle.rol_id', 'detalle.mend_permi', 'menu.men_nom']);

        $roleIds = $roles->pluck('rol_id')->map(fn ($id) => (int) $id)->all();
        DB::transaction(function () use ($roles, $users, $grants, $roleIds): void {
            foreach ($roles as $role) {
                DB::table('ccyf_roles')->insert([
                    'rol_id' => $role->rol_id, 'legacy_rol_id' => $role->rol_id,
                    'rol_nom' => $role->rol_nom, 'est' => (bool) $role->est,
                    ...LegacyTimestamps::from($role),
                ]);
            }
            foreach ($grants->groupBy(fn ($grant) => $grant->rol_id.':'.$grant->men_nom) as $entries) {
                $grant = $entries->first();
                if (! in_array((int) $grant->rol_id, $roleIds, true)) {
                    continue;
                }
                DB::table('ccyf_role_permissions')->updateOrInsert(
                    ['rol_id' => $grant->rol_id, 'menu_key' => $grant->men_nom],
                    ['allowed' => $entries->contains(fn ($entry) => $entry->mend_permi === 'si'), 'created_at' => now(), 'updated_at' => now()],
                );
            }
            foreach ($users->chunk(100) as $chunk) {
                DB::table('ccyf_usuarios')->insert($chunk->map(fn ($user) => [
                    'usu_id' => $user->usu_id, 'legacy_usu_id' => $user->usu_id,
                    'usu_area' => $user->usu_area ?: 'Usuario '.$user->usu_id,
                    'usu_correo' => $user->usu_correo, 'usu_pass' => $user->usu_pass,
                    'rol_id' => $user->rol_id, 'area_id' => $user->area_id,
                    'usu_telf' => $user->usu_telf, 'est' => (bool) $user->est,
                    ...LegacyTimestamps::from($user),
                ])->all());
            }
        });

        $this->components->info("Se importaron {$users->count()} cuentas y {$roles->count()} roles. La base histórica permanece intacta.");
        return self::SUCCESS;
    }
}
