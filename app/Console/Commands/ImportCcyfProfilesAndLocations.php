<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportCcyfProfilesAndLocations extends Command
{
    protected $signature = 'ccyf:import-profiles-locations';

    protected $description = 'Copy historical CCyF profile fields and locations without replacing local edits';

    public function handle(): int
    {
        $profiles = 0;
        DB::connection('legacy')->table('tm_usuario')->orderBy('usu_id')->chunk(100, function ($rows) use (&$profiles): void {
            foreach ($rows as $row) {
                $local = DB::table('ccyf_usuarios')->where('legacy_usu_id', $row->usu_id)->first();
                if (! $local || $local->legacy_profile_imported_at) {
                    continue;
                }
                DB::table('ccyf_usuarios')->where('usu_id', $local->usu_id)->update([
                    'rfc' => $row->rfc ?: null,
                    'curp' => $row->ine ?: null,
                    'ine_clave' => $row->ine2 ?: null,
                    'direcc' => $row->direcc ?: null,
                    'cont_alter' => $row->cont_alter ?: null,
                    'telf_alter' => $row->telf_alter ?: null,
                    'telegram_chat_id' => preg_match('/^\d{5,20}$/', (string) $row->telegram_chat_id) ? $row->telegram_chat_id : null,
                    'legacy_profile_imported_at' => now(),
                ]);
                $profiles++;
            }
        });

        DB::table('ccyf_usuarios')->where('telegram_chat_id', 'Sin Registrar')->update(['telegram_chat_id' => null]);

        $userIds = DB::table('ccyf_usuarios')->whereNotNull('legacy_usu_id')->pluck('usu_id', 'legacy_usu_id');
        $locations = 0;
        DB::connection('legacy')->table('tm_ubicaciones')->where('estado', 1)->orderBy('ubic_id')->chunk(50, function ($rows) use ($userIds, &$locations): void {
            $batch = [];
            foreach ($rows as $row) {
                $userId = $userIds->get($row->usu_id);
                if (! $userId || ! is_numeric($row->latitud) || ! is_numeric($row->longitud)) {
                    continue;
                }
                $batch[] = [
                    'usu_id' => $userId,
                    'legacy_ubic_id' => $row->ubic_id,
                    'latitud' => $row->latitud,
                    'longitud' => $row->longitud,
                    'precision_gps' => $row->precision_gps,
                    'es_aproximada' => (bool) $row->es_aproximada,
                    'ciudad' => $row->ciudad,
                    'region' => $row->region,
                    'pais' => $row->pais,
                    'ip' => $row->ip,
                    'fuente' => $row->fuente,
                    'fecha_registro' => $row->fecha_registro ?: now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if ($batch !== []) {
                $locations += DB::table('ccyf_ubicaciones')->insertOrIgnore($batch);
            }
        });

        $this->components->info("Perfiles importados: {$profiles}. Ubicaciones nuevas: {$locations}.");
        return self::SUCCESS;
    }
}
