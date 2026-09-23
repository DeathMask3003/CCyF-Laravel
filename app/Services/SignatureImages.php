<?php

namespace App\Services;

use App\Models\LegacyUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class SignatureImages
{
    private const STAFF_ROLES = [3, 4, 5, 17, 18, 20, 21];

    public function canView(LegacyUser $user): bool
    {
        return (bool) $user->est && in_array((int) $user->rol_id, self::STAFF_ROLES, true);
    }

    public function pathFor(LegacyUser $user): ?string
    {
        return $this->pathForLegacyId((int) ($user->legacy_usu_id ?: $user->getKey()));
    }

    public function pathForLegacyId(int $userId): ?string
    {
        $root = realpath((string) config('ccyf.legacy_signatures_root'));
        if (! $root || $userId <= 0) {
            return null;
        }

        foreach (['png', 'jpg', 'jpeg', 'webp'] as $extension) {
            $path = realpath($root.DIRECTORY_SEPARATOR.'users'.DIRECTORY_SEPARATOR.$userId
                .DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'firma.'.$extension);
            if (! $path || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR) || ! is_file($path)) {
                continue;
            }
            $dimensions = @getimagesize($path);
            if (in_array($dimensions['mime'] ?? '', ['image/png', 'image/jpeg', 'image/webp'], true)) {
                return $path;
            }
        }

        return null;
    }

    public function dataUriForLegacyId(int $userId): ?string
    {
        $path = $this->pathForLegacyId($userId);
        if (! $path) {
            return null;
        }

        $source = @imagecreatefromstring(file_get_contents($path));
        if ($source === false) {
            return null;
        }
        $canvas = imagecreatetruecolor(220, 65);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 255, 255, 255, 127));
        $scale = min(220 / imagesx($source), 65 / imagesy($source));
        $width = max(1, (int) round(imagesx($source) * $scale));
        $height = max(1, (int) round(imagesy($source) * $scale));
        imagecopyresampled($canvas, $source, (int) floor((220 - $width) / 2),
            (int) floor((65 - $height) / 2), 0, 0, $width, $height,
            imagesx($source), imagesy($source));
        ob_start();
        imagepng($canvas, null, 6);
        $png = ob_get_clean();
        imagedestroy($source);
        imagedestroy($canvas);

        return $png === false ? null : 'data:image/png;base64,'.base64_encode($png);
    }

    public function save(LegacyUser $user, UploadedFile $file): void
    {
        $root = realpath((string) config('ccyf.legacy_signatures_root'));
        if (! $root) {
            throw ValidationException::withMessages([
                'firma' => 'No está disponible la carpeta de firmas configurada.',
            ]);
        }

        $bytes = file_get_contents($file->getRealPath());
        $image = $bytes === false ? false : @imagecreatefromstring($bytes);
        if ($image === false) {
            throw ValidationException::withMessages(['firma' => 'La imagen de firma no es válida.']);
        }

        $folder = $root.DIRECTORY_SEPARATOR.'users'.DIRECTORY_SEPARATOR
            .(int) ($user->legacy_usu_id ?: $user->getKey()).DIRECTORY_SEPARATOR.'public';
        File::ensureDirectoryExists($folder);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        ob_start();
        imagepng($image, null, 6);
        $png = ob_get_clean();
        imagedestroy($image);
        if ($png === false || file_put_contents($folder.DIRECTORY_SEPARATOR.'firma.png', $png, LOCK_EX) === false) {
            throw ValidationException::withMessages(['firma' => 'No se pudo guardar la firma.']);
        }
    }
}
