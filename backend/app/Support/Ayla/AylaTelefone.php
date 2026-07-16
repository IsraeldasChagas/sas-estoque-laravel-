<?php

namespace App\Support\Ayla;

final class AylaTelefone
{
    /** Normaliza para somente dígitos (DDD + número). */
    public static function normalizar(?string $valor): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $valor);
        if ($digits === '' || $digits === null) {
            return null;
        }

        // Remove código do país 55 se informado.
        if (strlen($digits) === 12 || strlen($digits) === 13) {
            if (str_starts_with($digits, '55')) {
                $digits = substr($digits, 2);
            }
        }

        return $digits;
    }

    /** Valida número brasileiro com DDD (10 ou 11 dígitos). */
    public static function validar(?string $valor): bool
    {
        $n = self::normalizar($valor);
        if ($n === null) {
            return false;
        }

        if (! preg_match('/^[1-9][0-9]{9,10}$/', $n)) {
            return false;
        }

        if (strlen($n) === 11 && $n[2] !== '9') {
            return false;
        }

        return true;
    }

    /** Ex.: (69) 98463-9070 */
    public static function formatar(?string $valor): ?string
    {
        $n = self::normalizar($valor);
        if ($n === null) {
            return null;
        }

        if (strlen($n) === 11) {
            return sprintf('(%s) %s-%s', substr($n, 0, 2), substr($n, 2, 5), substr($n, 7, 4));
        }
        if (strlen($n) === 10) {
            return sprintf('(%s) %s-%s', substr($n, 0, 2), substr($n, 2, 4), substr($n, 6, 4));
        }

        return $n;
    }

    /** Ex.: (69) 9****-9070 */
    public static function mascarar(?string $valor): ?string
    {
        $n = self::normalizar($valor);
        if ($n === null) {
            return null;
        }

        if (strlen($n) === 11) {
            return sprintf('(%s) %s****-%s', substr($n, 0, 2), substr($n, 2, 1), substr($n, 7, 4));
        }
        if (strlen($n) === 10) {
            return sprintf('(%s) ****-%s', substr($n, 0, 2), substr($n, 6, 4));
        }

        return '****';
    }

    /** Dígitos para wa.me (55 + DDD + número). */
    public static function paraWhatsApp(?string $valor): ?string
    {
        $n = self::normalizar($valor);
        if ($n === null) {
            return null;
        }

        return '55'.$n;
    }
}
