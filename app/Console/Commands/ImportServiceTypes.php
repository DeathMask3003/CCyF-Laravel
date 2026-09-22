<?php

namespace App\Console\Commands;

use App\Services\ServiceTypes;
use Illuminate\Console\Command;

class ImportServiceTypes extends Command
{
    protected $signature = 'ccyf:import-service-types';

    protected $description = 'Importa los tipos de servicios heredados sin modificar la base original';

    public function handle(ServiceTypes $services): int
    {
        $this->info('Servicios importados: '.$services->importLegacy());

        return self::SUCCESS;
    }
}
