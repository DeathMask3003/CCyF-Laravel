<?php

namespace App\Services;

use App\Models\LegacyUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SatElectronicSignatures
{
    public function __construct(private readonly SignatureImages $images) {}

    public function status(LegacyUser $user): array
    {
        $local = $this->localFiles($user);
        $legacy = $this->legacyFiles($user);
        $files = $local ?: $legacy;
        $certificate = $files ? @openssl_x509_read(file_get_contents($files['certificate'])) : false;
        $parsed = $certificate ? openssl_x509_parse($certificate) : [];
        $manifest = $this->manifest($user);
        $legacyRow = $this->legacyRow($user);
        $imageAvailable = $this->images->pathFor($user) !== null;
        $method = $this->preference($user) ?: ($legacyRow?->method ?? null);
        if ($method === 'efirma' && ! $files) $method = null;
        if ($method === 'imagen' && ! $imageAvailable) $method = null;
        if (! $method) $method = $files ? 'efirma' : ($imageAvailable ? 'imagen' : null);

        return [
            'available' => (bool) $files,
            'imageAvailable' => $imageAvailable,
            'method' => $method,
            'alias' => $local ? ($manifest['alias'] ?? 'Mi e.firma SAT') : ($legacyRow?->alias ?: 'Mi e.firma SAT'),
            'serial' => $parsed['serialNumberHex'] ?? $parsed['serialNumber'] ?? null,
            'validFrom' => isset($parsed['validFrom_time_t']) ? date('d/m/Y', $parsed['validFrom_time_t']) : null,
            'validTo' => isset($parsed['validTo_time_t']) ? date('d/m/Y', $parsed['validTo_time_t']) : null,
            'expired' => isset($parsed['validTo_time_t']) && $parsed['validTo_time_t'] < time(),
        ];
    }

    public function save(LegacyUser $user, UploadedFile $certificate, UploadedFile $key, string $password, string $alias): void
    {
        if (! in_array(strtolower($certificate->getClientOriginalExtension()), ['cer', 'crt', 'pem'], true)
            || ! in_array(strtolower($key->getClientOriginalExtension()), ['key', 'pem'], true)) {
            throw ValidationException::withMessages(['cer' => 'Selecciona archivos .cer y .key válidos.']);
        }
        $certBytes = file_get_contents($certificate->getRealPath());
        $keyBytes = file_get_contents($key->getRealPath());
        if ($certBytes === false || $keyBytes === false) {
            throw ValidationException::withMessages(['cer' => 'No se pudieron leer los archivos.']);
        }
        $certPem = str_contains($certBytes, '-----BEGIN') ? $certBytes
            : "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode($certBytes), 64, "\n")."-----END CERTIFICATE-----\n";
        $keyPem = str_contains($keyBytes, '-----BEGIN') ? $keyBytes
            : "-----BEGIN ENCRYPTED PRIVATE KEY-----\n".chunk_split(base64_encode($keyBytes), 64, "\n")."-----END ENCRYPTED PRIVATE KEY-----\n";
        if (! str_contains($keyPem, '-----BEGIN ENCRYPTED PRIVATE KEY-----')) {
            throw ValidationException::withMessages(['key' => 'La clave privada debe estar cifrada con contraseña.']);
        }
        $x509 = @openssl_x509_read($certPem);
        $privateKey = @openssl_pkey_get_private($keyPem, $password);
        if (! $x509 || ! $privateKey || ! @openssl_x509_check_private_key($x509, $privateKey)) {
            throw ValidationException::withMessages(['key' => 'El certificado, la clave privada o la contraseña no coinciden.']);
        }
        $parsed = openssl_x509_parse($x509);
        if (! $parsed) throw ValidationException::withMessages(['cer' => 'No se pudo leer el certificado.']);

        $folder = $this->userFolder($user);
        File::ensureDirectoryExists($folder, 0700);
        @chmod($folder, 0700);
        $token = Str::lower(Str::random(24));
        $certName = 'certificado-'.$token.'.pem';
        $keyName = 'clave-'.$token.'.pem';
        $certPath = $folder.DIRECTORY_SEPARATOR.$certName;
        $keyPath = $folder.DIRECTORY_SEPARATOR.$keyName;
        if (file_put_contents($certPath, $certPem, LOCK_EX) === false
            || file_put_contents($keyPath, $keyPem, LOCK_EX) === false) {
            @unlink($certPath);
            @unlink($keyPath);
            throw ValidationException::withMessages(['cer' => 'No se pudieron guardar los archivos de la e.firma.']);
        }
        @chmod($certPath, 0600);
        @chmod($keyPath, 0600);
        $manifest = [
            'certificate' => $certName, 'key' => $keyName,
            'alias' => trim($alias) ?: 'Mi e.firma SAT',
            'serial' => $parsed['serialNumberHex'] ?? $parsed['serialNumber'] ?? null,
            'valid_from' => $parsed['validFrom_time_t'] ?? null,
            'valid_to' => $parsed['validTo_time_t'] ?? null,
        ];
        $previous = $this->manifest($user);
        try {
            File::replace($folder.DIRECTORY_SEPARATOR.'current.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        } catch (\Throwable $error) {
            @unlink($certPath);
            @unlink($keyPath);
            throw ValidationException::withMessages(['cer' => 'No se pudo completar el guardado de la e.firma.']);
        }
        foreach (['certificate', 'key'] as $name) {
            $old = $previous[$name] ?? null;
            if (is_string($old) && preg_match('/^(certificado|clave)-[a-z0-9]+\.pem$/', $old)) {
                @unlink($folder.DIRECTORY_SEPARATOR.$old);
            }
        }
        if (! $this->preference($user)) $this->writePreference($user, 'efirma');
    }

    public function setMethod(LegacyUser $user, string $method): void
    {
        $status = $this->status($user);
        if (($method === 'efirma' && ! $status['available'])
            || ($method === 'imagen' && ! $status['imageAvailable'])
            || ! in_array($method, ['efirma', 'imagen'], true)) {
            throw ValidationException::withMessages(['metodo' => 'Primero carga el método de firma seleccionado.']);
        }
        $this->writePreference($user, $method);
    }

    public function delete(LegacyUser $user): void
    {
        $folder = $this->userFolder($user);
        $base = realpath(Storage::disk('local')->path('sat-signatures/users'));
        $resolved = realpath($folder);
        if ($resolved && (! $base || ! str_starts_with($resolved, $base.DIRECTORY_SEPARATOR))) {
            throw ValidationException::withMessages(['efirma' => 'La ruta de la e.firma no es válida.']);
        }
        if ($resolved) {
            foreach (File::files($resolved) as $file) {
                $name = $file->getFilename();
                if (preg_match('/^(certificado|clave)-[a-z0-9]+\.pem$/', $name)
                    || $name === 'current.json') {
                    $this->removeFile($file->getPathname(), $resolved);
                }
            }
        }
        $legacy = $this->legacyFolder($user);
        if ($legacy) {
            foreach (['certificado.pem', 'clave.pem'] as $name) {
                $this->removeFile($legacy.DIRECTORY_SEPARATOR.$name, $legacy);
            }
        }
        if ($user->legacy_usu_id && Schema::connection('legacy')->hasTable('tm_efirmas')) {
            DB::connection('legacy')->table('tm_efirmas')->where('usu_id', $user->legacy_usu_id)
                ->update(['cer_path' => null, 'key_path' => null, 'serial_cert' => null,
                    'valid_from' => null, 'valid_to' => null,
                    'method' => $this->images->pathFor($user) ? 'imagen' : null]);
        }
        $this->writePreference($user, $this->images->pathFor($user) ? 'imagen' : null);
    }

    private function localFiles(LegacyUser $user): ?array
    {
        $manifest = $this->manifest($user);
        $certificate = $manifest['certificate'] ?? null;
        $key = $manifest['key'] ?? null;
        if (! is_string($certificate) || ! is_string($key)
            || ! preg_match('/^certificado-[a-z0-9]+\.pem$/', $certificate)
            || ! preg_match('/^clave-[a-z0-9]+\.pem$/', $key)) return null;
        $folder = $this->userFolder($user);
        $certPath = $folder.DIRECTORY_SEPARATOR.$certificate;
        $keyPath = $folder.DIRECTORY_SEPARATOR.$key;
        return is_file($certPath) && is_file($keyPath)
            ? ['certificate' => $certPath, 'key' => $keyPath] : null;
    }

    private function legacyFiles(LegacyUser $user): ?array
    {
        $folder = $this->legacyFolder($user);
        if (! $folder) return null;
        $certificate = $folder.DIRECTORY_SEPARATOR.'certificado.pem';
        $key = $folder.DIRECTORY_SEPARATOR.'clave.pem';
        return is_file($certificate) && is_file($key)
            ? ['certificate' => $certificate, 'key' => $key] : null;
    }

    private function legacyFolder(LegacyUser $user): ?string
    {
        if (! $user->legacy_usu_id) return null;
        $root = realpath((string) config('ccyf.legacy_signatures_root'));
        $folder = $root ? realpath($root.DIRECTORY_SEPARATOR.'users'.DIRECTORY_SEPARATOR
            .(int) $user->legacy_usu_id.DIRECTORY_SEPARATOR.'secure') : false;
        return $folder && str_starts_with($folder, $root.DIRECTORY_SEPARATOR) ? $folder : null;
    }

    private function legacyRow(LegacyUser $user): ?object
    {
        return $user->legacy_usu_id && Schema::connection('legacy')->hasTable('tm_efirmas')
            ? DB::connection('legacy')->table('tm_efirmas')->where('usu_id', $user->legacy_usu_id)->first()
            : null;
    }

    private function manifest(LegacyUser $user): array
    {
        $path = $this->userFolder($user).DIRECTORY_SEPARATOR.'current.json';
        return is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
    }

    private function preference(LegacyUser $user): ?string
    {
        $path = $this->userFolder($user).DIRECTORY_SEPARATOR.'preference.json';
        $value = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        return in_array($value['method'] ?? null, ['efirma', 'imagen'], true) ? $value['method'] : null;
    }

    private function writePreference(LegacyUser $user, ?string $method): void
    {
        $folder = $this->userFolder($user);
        File::ensureDirectoryExists($folder, 0700);
        File::replace($folder.DIRECTORY_SEPARATOR.'preference.json', json_encode(['method' => $method]));
    }

    private function userFolder(LegacyUser $user): string
    {
        return Storage::disk('local')->path('sat-signatures/users/'.$user->getKey());
    }

    private function removeFile(string $path, string $expectedFolder): void
    {
        if (! is_file($path)) return;
        $resolved = realpath($path);
        if (! $resolved || dirname($resolved) !== $expectedFolder || ! @unlink($resolved)) {
            throw ValidationException::withMessages(['efirma' => 'No se pudo borrar físicamente la e.firma del servidor.']);
        }
    }
}
