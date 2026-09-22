<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CcyfPreflight extends Command
{
    protected $signature = 'ccyf:preflight {--source= : Ruta de la copia PHP heredada} {--check-db : Consultar el esquema de la base heredada}';

    protected $description = 'Comprueba la copia de CCyF y su esquema sin modificar archivos ni bases de datos';

    public function handle(): int
    {
        $source = $this->option('source') ?: config('ccyf.legacy_root');

        if (! is_string($source) || $source === '' || ! is_dir($source)) {
            $this->error('Configura CCYF_LEGACY_ROOT con la ruta de la copia heredada.');

            return self::FAILURE;
        }

        $source = realpath($source);
        $required = [
            'index.php',
            'config/conexion.php',
            'models/Usuario.php',
            'controller/usuario.php',
            'alba/home/index.php',
            'ccyf.sql',
        ];
        $missing = [];

        foreach ($required as $relative) {
            if (! is_file($source.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative))) {
                $missing[] = $relative;
            }
        }

        if ($missing !== []) {
            $this->error('Faltan archivos de la copia: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $sql = file_get_contents($source.DIRECTORY_SEPARATOR.'ccyf.sql');

        if ($sql === false) {
            $this->error('No se pudo leer ccyf.sql.');

            return self::FAILURE;
        }

        preg_match_all('/^CREATE TABLE `([^`]+)`/m', $sql, $matches);
        $tables = array_values(array_unique($matches[1]));
        $missingTables = array_values(array_diff(config('ccyf.expected_tables'), $tables));

        $this->info('Laravel: '.app()->version());
        $this->line('Copia heredada: '.$source);
        $this->line('Tablas detectadas en ccyf.sql: '.count($tables));

        if ($missingTables !== []) {
            $this->error('El SQL no contiene tablas clave: '.implode(', ', $missingTables));

            return self::FAILURE;
        }

        if ($this->option('check-db')) {
            if (! config('database.connections.legacy.database')) {
                $this->error('Configura LEGACY_DB_DATABASE antes de consultar la base heredada.');

                return self::FAILURE;
            }

            try {
                $database = DB::connection('legacy')->getDatabaseName();
                $missingDatabaseTables = array_values(array_filter(
                    config('ccyf.expected_tables'),
                    fn (string $table): bool => ! Schema::connection('legacy')->hasTable($table),
                ));
            } catch (Throwable $exception) {
                $this->error('No se pudo consultar el esquema de la base heredada: '.$exception->getMessage());

                return self::FAILURE;
            }

            if ($missingDatabaseTables !== []) {
                $this->error('La base '.$database.' no contiene: '.implode(', ', $missingDatabaseTables));

                return self::FAILURE;
            }

            $this->info('Tablas clave presentes en la base '.$database.'.');
        }

        $this->info('Verificación de solo lectura completada.');

        return self::SUCCESS;
    }
}
