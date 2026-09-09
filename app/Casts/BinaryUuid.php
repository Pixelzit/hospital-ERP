<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class BinaryUuid implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && strlen($value) === 36) {
            return $value;
        }

        if (is_string($value) && strlen($value) === 16) {
            $hex = bin2hex($value);

            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($hex, 4));
        }

        return is_string($value) ? $value : null;
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && strlen($value) === 16) {
            return $value;
        }

        if (is_string($value) && strlen($value) === 36) {
            $binary = @hex2bin(str_replace('-', '', $value));

            return $binary === false ? $value : $binary;
        }

        return $value;
    }
}
