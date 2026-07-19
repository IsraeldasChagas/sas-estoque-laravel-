<?php

namespace App\Support\Delivery;

final class DeliveryWhatsAppHelper
{
    public static function normalizarTelefoneBr(?string $telefone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $telefone);
        if ($digits === null || $digits === '') {
            return null;
        }

        if (strlen($digits) === 10 || strlen($digits) === 11) {
            $digits = '55'.$digits;
        }

        if (strlen($digits) < 12 || strlen($digits) > 13) {
            return null;
        }

        return $digits;
    }

    public static function urlContato(?string $telefone): ?string
    {
        $digits = self::normalizarTelefoneBr($telefone);
        if ($digits === null) {
            return null;
        }

        return 'https://wa.me/'.$digits;
    }

    public static function urlComTexto(?string $telefone, string $texto): ?string
    {
        $digits = self::normalizarTelefoneBr($telefone);
        if ($digits === null) {
            return null;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($texto);
    }
}
