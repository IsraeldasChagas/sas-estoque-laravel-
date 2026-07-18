<?php

namespace App\Support;

class ReservaMesaAcesso
{
    private const MODULOS = ['reservaDashboard', 'reservaMesa', 'historicoReservas'];

    /** Perfis com reserva de mesa quando permissoes_menu não está personalizado (espelha o frontend). */
    private const PERFIS_PADRAO_COM_RESERVA = [
        'ADMIN',
        'GERENTE',
        'BAR',
        'FINANCEIRO',
        'ASSISTENTE_ADMINISTRATIVO',
        'ATENDENTE',
        'ATENDENTE_CAIXA',
    ];

    public static function temAcessoModulo(?object $usuario): bool
    {
        if (! $usuario || (int) ($usuario->ativo ?? 0) !== 1) {
            return false;
        }

        $pm = $usuario->permissoes_menu ?? null;
        if (is_string($pm)) {
            $decoded = json_decode($pm, true);
            $pm = is_array($decoded) ? $decoded : null;
        }

        if (is_array($pm) && count($pm) > 0) {
            foreach (self::MODULOS as $modulo) {
                if (in_array($modulo, $pm, true)) {
                    return true;
                }
            }

            return false;
        }

        $perfil = strtoupper(trim((string) ($usuario->perfil ?? '')));

        return in_array($perfil, self::PERFIS_PADRAO_COM_RESERVA, true);
    }

    /**
     * Quem está autorizado no módulo Reserva de Mesa pode escolher/operar qualquer unidade
     * (mesmos privilégios de ADMIN/GERENTE apenas neste módulo).
     */
    public static function podeGerenciarTodasUnidades(?object $usuario): bool
    {
        return self::temAcessoModulo($usuario);
    }
}
