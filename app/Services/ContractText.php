<?php

namespace App\Services;

class ContractText
{
    public static function personName(?string $value): string
    {
        $text = self::spaces($value);
        if ($text === '') return '';

        $text = mb_convert_case(mb_strtolower($text, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        $particles = ['de', 'del', 'la', 'las', 'los', 'y', 'e', 'da', 'do', 'dos', 'van', 'von'];
        $words = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($words as $index => $word) {
            if ($index > 0 && in_array(mb_strtolower($word, 'UTF-8'), $particles, true)) {
                $words[$index] = mb_strtolower($word, 'UTF-8');
            }
        }

        return implode('', $words);
    }

    public static function readable(?string $value): string
    {
        $text = self::spaces($value);
        if ($text === '' || mb_strtoupper($text, 'UTF-8') !== $text) return $text;

        $text = self::personName($text);
        return preg_replace_callback('/\b(Cobaem|Cemsad|Ine|Rfc|Curp|Cp|Ii|Iii|Iv|Vi|Vii|Viii|Ix|X)\b/u',
            fn (array $match): string => mb_strtoupper($match[0], 'UTF-8'), $text) ?? $text;
    }

    private static function spaces(?string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $value) ?? (string) $value);
    }
}
