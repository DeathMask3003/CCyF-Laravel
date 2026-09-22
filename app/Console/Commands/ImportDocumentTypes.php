<?php

namespace App\Console\Commands;

use App\Services\DocumentTypes;
use Illuminate\Console\Command;

class ImportDocumentTypes extends Command
{
    protected $signature = 'ccyf:import-document-types';

    protected $description = 'Importa los tipos de documento heredados sin modificar la base original';

    public function handle(DocumentTypes $types): int
    {
        $this->info('Tipos importados: '.$types->importLegacy());

        return self::SUCCESS;
    }
}
