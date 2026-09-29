<?php

namespace App\Services;

use App\Models\LegacyUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProfilePhotos
{
    public function pathFor(LegacyUser $user): ?string
    {
        $local = Storage::disk('local')->path('profile-photos/'.$user->getKey().'.png');
        if ($this->validImage($local)) return $local;

        if (! $user->legacy_usu_id || ! Schema::connection('legacy')->hasColumn('tm_usuario', 'usu_img')) return null;
        $stored = DB::connection('legacy')->table('tm_usuario')
            ->where('usu_id', $user->legacy_usu_id)->value('usu_img');
        $filename = basename(str_replace('\\', '/', (string) $stored));
        if (! $filename || strtolower($filename) === 'noimage.jpg'
            || ! preg_match('/\.(png|jpe?g|webp)$/i', $filename)) return null;
        $root = realpath((string) config('ccyf.legacy_profile_photos_root'));
        $path = $root ? realpath($root.DIRECTORY_SEPARATOR.$filename) : false;
        return $path && str_starts_with($path, $root.DIRECTORY_SEPARATOR) && $this->validImage($path) ? $path : null;
    }

    public function save(LegacyUser $user, UploadedFile $file): void
    {
        $source = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if ($source === false) throw ValidationException::withMessages(['foto' => 'La imagen no es válida.']);
        $side = min(imagesx($source), imagesy($source));
        $canvas = imagecreatetruecolor(420, 420);
        imagecopyresampled($canvas, $source, 0, 0,
            (int) floor((imagesx($source) - $side) / 2), (int) floor((imagesy($source) - $side) / 2),
            420, 420, $side, $side);
        $folder = Storage::disk('local')->path('profile-photos');
        File::ensureDirectoryExists($folder);
        $target = $folder.DIRECTORY_SEPARATOR.$user->getKey().'.png';
        $temporary = tempnam($folder, 'photo-');
        $saved = $temporary && imagepng($canvas, $temporary, 6);
        imagedestroy($source);
        imagedestroy($canvas);
        if (! $saved || ! @rename($temporary, $target)) {
            if ($temporary) @unlink($temporary);
            throw ValidationException::withMessages(['foto' => 'No se pudo guardar la foto.']);
        }
    }

    private function validImage(string $path): bool
    {
        return is_file($path) && in_array(@getimagesize($path)['mime'] ?? null,
            ['image/png', 'image/jpeg', 'image/webp'], true);
    }
}
