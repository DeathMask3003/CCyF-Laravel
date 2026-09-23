<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            $legacy = DB::connection('legacy');
            if (! $legacy->getSchemaBuilder()->hasTable('tm_mennu')
                || ! $legacy->getSchemaBuilder()->hasTable('td_medu_detalle')) {
                return;
            }
            $grants = $legacy->table('td_medu_detalle as detalle')
                ->join('tm_mennu as menu', 'menu.men_id', '=', 'detalle.men_id')
                ->where('menu.men_nom', 'convocatorias')->where('menu.est', 1)
                ->where('detalle.mend_permi', 'si')->pluck('detalle.rol_id')->unique();
        } catch (\Throwable) {
            // Una instalación nueva puede migrarse antes de conectar la base histórica.
            return;
        }
        foreach ($grants as $roleId) {
            if (! DB::table('ccyf_roles')->where('rol_id', $roleId)->exists()
                || DB::table('ccyf_role_permissions')->where('rol_id', $roleId)->where('menu_key', 'convocatorias')->exists()) {
                continue;
            }
            DB::table('ccyf_role_permissions')->insert([
                'rol_id' => $roleId, 'menu_key' => 'convocatorias', 'allowed' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('ccyf_role_permissions')->where('menu_key', 'convocatorias')->delete();
    }
};
