<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Branding
{
    public function current(): object
    {
        try {
            $saved = DB::table('ccyf_branding')->where('id', 1)->first();
        } catch (QueryException) {
            $saved = null;
        }

        return (object) [
            'title' => $saved?->title ?: 'Concurso de Cafetería y Fotocopiado',
            'motto' => $saved?->motto ?: 'Convocatorias claras. Trámites a tu alcance.',
            'logo_path' => $saved?->logo_path,
            'document_title' => $saved?->document_title ?: 'Información para participantes',
            'document_description' => $saved?->document_description ?: 'Consulta este documento antes de realizar tu registro.',
            'document_path' => $saved?->document_path,
        ];
    }

    public function logoUrl(object $branding): string
    {
        return $this->logoFile($branding)
            ? route('branding.logo', ['v' => basename($branding->logo_path)])
            : asset('images/ccyf-default.svg');
    }

    public function logoFile(object $branding): ?string
    {
        $path = $branding->logo_path;
        if (! is_string($path) || ! str_starts_with($path, 'ccyf/branding/') || str_contains($path, '..')) {
            return null;
        }

        return Storage::disk('local')->exists($path) ? Storage::disk('local')->path($path) : null;
    }

    public function documentFile(object $branding): ?string
    {
        $path = $branding->document_path;
        if (! is_string($path) || ! preg_match('~^ccyf/branding/documents/[a-f0-9-]+\.pdf$~i', $path)) {
            return null;
        }

        return Storage::disk('local')->exists($path) ? Storage::disk('local')->path($path) : null;
    }
}
