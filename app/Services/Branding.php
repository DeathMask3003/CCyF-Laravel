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
            'document_title' => data_get($saved, 'document_title') ?: 'Información para participantes',
            'document_description' => data_get($saved, 'document_description') ?: 'Consulta este documento antes de realizar tu registro.',
            'document_path' => data_get($saved, 'document_path'),
            'announcement_visible' => (bool) data_get($saved, 'announcement_visible', false),
            'announcement_title' => data_get($saved, 'announcement_title') ?: 'Convocatorias de Cafetería y Fotocopiado',
            'announcement_description' => data_get($saved, 'announcement_description') ?: '',
            'announcement_image_path' => data_get($saved, 'announcement_image_path'),
            'announcement_video_path' => data_get($saved, 'announcement_video_path'),
            'announcement_link_1_label' => data_get($saved, 'announcement_link_1_label') ?: '',
            'announcement_link_1_url' => data_get($saved, 'announcement_link_1_url') ?: '',
            'announcement_link_1_blank' => (bool) data_get($saved, 'announcement_link_1_blank', false),
            'announcement_link_2_label' => data_get($saved, 'announcement_link_2_label') ?: '',
            'announcement_link_2_url' => data_get($saved, 'announcement_link_2_url') ?: '',
            'announcement_link_2_blank' => (bool) data_get($saved, 'announcement_link_2_blank', false),
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

    public function announcementImageFile(object $branding): ?string
    {
        $path = $branding->announcement_image_path;
        if (! is_string($path) || ! preg_match('~^ccyf/branding/announcements/[a-f0-9-]+\.(png|jpg|jpeg|webp)$~i', $path)) {
            return null;
        }

        return Storage::disk('local')->exists($path) ? Storage::disk('local')->path($path) : null;
    }

    public function announcementVideoFile(object $branding): ?string
    {
        $path = $branding->announcement_video_path;
        if (! is_string($path) || ! preg_match('~^ccyf/branding/announcements/[a-f0-9-]+\.(mp4|webm)$~i', $path)) {
            return null;
        }

        return Storage::disk('local')->exists($path) ? Storage::disk('local')->path($path) : null;
    }
}
