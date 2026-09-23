<?php

namespace App\Services;

class FinishedResultPdf
{
    public function historical(int $documentId, bool $designated): ?string
    {
        if ($documentId < 1) {
            return null;
        }

        $folder = $designated ? 'permisionario_designado' : 'permisionario_no_designado';
        $filename = ($designated ? 'Carta_Designacion_' : 'Carta_No_Designado_').$documentId.'.pdf';
        $root = realpath(config('ccyf.legacy_reports_root').DIRECTORY_SEPARATOR.$folder);
        $path = $root ? realpath($root.DIRECTORY_SEPARATOR.$filename) : false;

        if (! $root || ! $path || ! str_starts_with(strtolower($path), strtolower($root.DIRECTORY_SEPARATOR)) || ! is_file($path)) {
            return null;
        }

        $handle = @fopen($path, 'rb');
        if (! $handle) {
            return null;
        }
        $signature = fread($handle, 5);
        fclose($handle);

        return $signature === '%PDF-' ? $path : null;
    }
}
