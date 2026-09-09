<?php

namespace App\Support;

use Illuminate\Support\Str;

class UuidBin
{
    public static function generate(): string
    {
        return hex2bin(str_replace('-', '', (string) Str::uuid()));
    }

    public static function from($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && strlen($value) === 16) {
            $hex = bin2hex($value);

            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($hex, 4));
        }

        if (is_string($value) && strlen($value) === 32 && ctype_xdigit($value)) {
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(strtolower($value), 4));
        }

        return is_string($value) ? $value : null;
    }

    public static function to($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && strlen($value) === 16) {
            return $value;
        }

        $hex = str_replace('-', '', (string) $value);

        if (strlen($hex) === 32 && ctype_xdigit($hex)) {
            $binary = @hex2bin($hex);

            return $binary === false ? null : $binary;
        }

        return null;
    }
}
