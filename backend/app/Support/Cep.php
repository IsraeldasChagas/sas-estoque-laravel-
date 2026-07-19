<?php

namespace App\Support;

final class Cep
{
    public static function normalizar8(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return strlen($digits) === 8 ? $digits : null;
    }
}
