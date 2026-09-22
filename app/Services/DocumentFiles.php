<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentFiles
{
    public function latest(string $origin, int $registrationId, string $field): ?object
    {
        return DB::table('ccyf_document_updates')->where('origin', $origin)
            ->where('registration_id', $registrationId)->where('field_key', $field)
            ->orderByDesc('id')->first();
    }

    public function effective(string $origin, int $registrationId, string $field): ?array
    {
        $update = $this->latest($origin, $registrationId, $field);
        if ($update && Storage::disk('local')->exists($update->path)) {
            return ['path' => Storage::disk('local')->path($update->path), 'name' => $update->original_name,
                'mime' => $update->mime, 'source' => 'update', 'version' => $update->id];
        }
        return $this->original($origin, $registrationId, $field);
    }

    public function original(string $origin, int $registrationId, string $field): ?array
    {
        $path = $origin === 'historico'
            ? $this->historicalOriginal($registrationId, $field)
            : $this->actualOriginal($registrationId, $field);
        if (! $path) return null;
        return ['path' => $path, 'name' => basename($path), 'mime' => $this->mimeFor($path),
            'source' => 'original', 'version' => null];
    }

    public function versionPath(object $version): ?string
    {
        return Storage::disk('local')->exists($version->path) ? Storage::disk('local')->path($version->path) : null;
    }

    private function historicalOriginal(int $registrationId, string $field): ?string
    {
        if (! array_key_exists($field, PrevaluationCatalog::DOCUMENTS)) return null;
        $row = DB::connection('legacy')->table('td_documentov3')->where('doc_id', $registrationId)
            ->where('est', 1)->orderByDesc('det_id')->first();
        $name = $row->{$field} ?? null;
        if (! is_string($name) || $name === '' || basename($name) !== $name) return null;
        if (! preg_match('/\.(pdf|jpg|jpeg|png|doc|docx|xls|xlsx)$/i', $name)) return null;
        $root = realpath(config('ccyf.legacy_files_root'));
        $path = $root ? realpath($root.DIRECTORY_SEPARATOR.$registrationId.DIRECTORY_SEPARATOR.$name) : false;
        return $root && $path && str_starts_with($path, $root.DIRECTORY_SEPARATOR) && is_file($path) ? $path : null;
    }

    private function actualOriginal(int $registrationId, string $field): ?string
    {
        if (! preg_match('/^req-([1-9]\d*)$/', $field, $match)) return null;
        $file = DB::table('ccyf_registro_archivos')->where('registro_id', $registrationId)
            ->where('requisito_id', (int) $match[1])->first();
        return $file && Storage::disk('local')->exists($file->ruta) ? Storage::disk('local')->path($file->ruta) : null;
    }

    private function mimeFor(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf', 'jpg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/octet-stream',
        };
    }
}
