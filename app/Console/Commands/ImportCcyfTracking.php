<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportCcyfTracking extends Command
{
    protected $signature = 'ccyf:import-permisionarios';

    protected $description = 'Import active historical permit tracking and menu grants without replacing local changes';

    public function handle(): int
    {
        $legacy = DB::connection('legacy');
        $grants = $legacy->table('td_medu_detalle as detalle')
            ->join('tm_mennu as menu', 'menu.men_id', '=', 'detalle.men_id')
            ->where('menu.men_nom', 'seguimiento_permisionarios')->where('menu.est', 1)
            ->get(['detalle.rol_id', 'detalle.mend_permi']);
        foreach ($grants as $grant) {
            $role = DB::table('ccyf_roles')->where('legacy_rol_id', $grant->rol_id)->first();
            if ($role) {
                DB::table('ccyf_role_permissions')->insertOrIgnore([
                    'rol_id' => $role->rol_id, 'menu_key' => 'seguimiento_permisionarios',
                    'allowed' => $grant->mend_permi === 'si', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        $count = 0;
        $rows = $legacy->table('tm_seguimiento_permisio')->where('est', 1)->whereNotNull('doc_id')
            ->orderBy('detapermi_id')->get();
        foreach ($rows as $row) {
            $existing = DB::table('ccyf_seguimientos')->where('origen', 'historico')->where('registro_id', $row->doc_id)->first();
            if ($existing) {
                continue;
            }
            $id = DB::table('ccyf_seguimientos')->insertGetId([
                'origen' => 'historico', 'registro_id' => $row->doc_id,
                'legacy_detapermi_id' => $row->detapermi_id,
                'metros_cuadrados' => $row->metros_cuadrados,
                'matricula' => $row->matricula, 'monto' => $row->monto,
                'convocatoria1' => $row->convocatoria1 ?: null,
                'convocatoria2' => $row->convocatoria2 ?: null,
                'convocatoria3' => $row->convocatoria3 ?: null,
                'pagosalmes' => $row->pagosalmes, 'construidapor' => $row->construidapor,
                'servicioenergia' => $row->servicioenergia, 'observaciones' => $row->observaciones,
                'created_at' => $row->fech_crea ?: now(), 'updated_at' => $row->fech_modif ?: now(),
            ]);
            $files = $legacy->table('td_archivos_permisio')->where('detapermi_id', $row->detapermi_id)
                ->where('est', 1)->orderByDesc('fech_crea')->orderByDesc('archivo_id')->first();
            if ($files) {
                foreach (range(1, 5) as $number) {
                    $path = $files->{'archivo'.$number};
                    if (is_string($path) && $path !== '') {
                        DB::table('ccyf_seguimiento_archivos')->insert([
                            'seguimiento_id' => $id, 'numero' => $number, 'origen' => 'historico',
                            'ruta' => $path, 'nombre' => basename(str_replace('\\', '/', $path)),
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
            }
            $count++;
        }

        $this->components->info("Seguimientos históricos nuevos: {$count}. Permisos sincronizados sin sobrescribir cambios.");
        return self::SUCCESS;
    }
}
