<?php

namespace App\Services;

class MexicanPhone
{
    public static function local(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if (preg_match('/^521(\d{10})$/', $digits, $matches) || preg_match('/^52(\d{10})$/', $digits, $matches)) {
            return $matches[1];
        }

        return preg_match('/^\d{10}$/', $digits) ? $digits : '';
    }

    public static function store(string $local): string
    {
        return '521'.$local;
    }
}
