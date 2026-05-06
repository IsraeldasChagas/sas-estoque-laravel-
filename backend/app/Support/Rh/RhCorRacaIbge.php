<?php

namespace App\Support\Rh;

/** Autodeclaração de cor ou raça nos moldes usuais (IBGE). */
final class RhCorRacaIbge
{
    /**
     * @return list<string> valores gravados em texto no cadastro
     */
    public static function opcoes(): array
    {
        return [
            'Branca',
            'Preta',
            'Parda',
            'Amarela',
            'Indígena',
            'Prefiro não declarar',
        ];
    }
}
