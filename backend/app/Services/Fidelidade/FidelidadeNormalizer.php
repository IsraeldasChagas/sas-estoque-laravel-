<?php

namespace App\Services\Fidelidade;

final class FidelidadeNormalizer
{
    /** Somente dígitos; remove 55 e zeros à esquerda mantendo DDD+número (máx. 11). */
    public static function telefone(?string $raw): string
    {
        $d = preg_replace('/\D+/', '', (string) $raw) ?? '';

        if ((strlen($d) === 12 || strlen($d) === 13) && str_starts_with($d, '55')) {
            $d = substr($d, 2);
        }

        while (strlen($d) > 11 && str_starts_with($d, '0')) {
            $d = substr($d, 1);
        }

        return $d;
    }

    /** Onze dígitos ou null. */
    public static function cpf(?string $raw): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $raw) ?? '';

        return strlen($d) === 11 ? $d : null;
    }

    public static function cpfValido(?string $cpf11): bool
    {
        $cpf11 = self::cpf($cpf11) ?? (string) $cpf11;
        if (strlen($cpf11) !== 11 || ! ctype_digit($cpf11)) {
            return false;
        }
        if (preg_match('/^(\d)\1{10}$/', $cpf11)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += (int) $cpf11[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ((int) $cpf11[$t] !== $d) {
                return false;
            }
        }

        return true;
    }

    public static function email(?string $raw): ?string
    {
        $e = strtolower(trim((string) $raw));

        return $e !== '' ? $e : null;
    }

    public static function nome(?string $raw): ?string
    {
        $n = trim((string) $raw);

        return $n !== '' ? mb_substr($n, 0, 160) : null;
    }
}
