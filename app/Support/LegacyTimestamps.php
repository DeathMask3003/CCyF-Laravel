<?php

namespace App\Support;

use DateTimeImmutable;

class LegacyTimestamps
{
    public static function date(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    public static function from(object $row): array
    {
        $created = self::valid($row->fech_crea ?? null) ?? now();

        return [
            'created_at' => $created,
            'updated_at' => self::valid($row->fech_modif ?? null) ?? $created,
        ];
    }

    private static function valid(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);

        return $date && $date->format('Y-m-d H:i:s') === $value ? $value : null;
    }
}
