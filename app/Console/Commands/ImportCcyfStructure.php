<?php

namespace App\Console\Commands;

use App\Services\CcyfStructure;
use Illuminate\Console\Command;

class ImportCcyfStructure extends Command
{
    protected $signature = 'ccyf:import-structure';
    protected $description = 'Importa planteles, convocatorias y sus relaciones sin modificar la base heredada';

    public function handle(CcyfStructure $structure): int
    {
        $result = $structure->importLegacy();
        $this->info("Planteles: {$result['campuses']}; convocatorias: {$result['convocations']}; relaciones: {$result['assignments']}");

        return self::SUCCESS;
    }
}
