<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;

class LegacyPasswordVerifier
{
    public function verify(string $plain, string $stored): bool
    {
        if ($plain === '' || $stored === '') {
            return false;
        }

        $algorithm = password_get_info($stored)['algoName'];

        if (in_array($algorithm, ['bcrypt', 'argon2i', 'argon2id'], true)) {
            return Hash::check($plain, $stored);
        }

        $key = config('ccyf.legacy_password_key');

        if (! is_string($key) || $key === '') {
            return false;
        }

        $binary = base64_decode($stored, true);
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');

        if ($binary === false || $ivLength === false || strlen($binary) <= $ivLength) {
            return false;
        }

        $plainFromLegacy = openssl_decrypt(
            substr($binary, $ivLength),
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA,
            substr($binary, 0, $ivLength),
        );

        return is_string($plainFromLegacy) && hash_equals($plainFromLegacy, $plain);
    }
}
