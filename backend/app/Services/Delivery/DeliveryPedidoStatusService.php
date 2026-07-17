<?php

namespace App\Services\Delivery;

use Illuminate\Validation\ValidationException;

class DeliveryPedidoStatusService
{
    public const TRANSITIONS = [
        'pendente_loja' => ['recebido', 'cancelado'],
        'recebido' => ['preparo', 'cancelado'],
        'preparo' => ['pronto', 'cancelado'],
        'pronto' => ['rota', 'entregue', 'endereco_nao_encontrado', 'cancelado'],
        'rota' => ['entregue', 'endereco_nao_encontrado', 'cancelado'],
        'entregue' => [],
        'cancelado' => [],
        'endereco_nao_encontrado' => [],
    ];

    public function podeTransicionar(string $atual, string $novo): bool
    {
        $atual = strtolower(trim($atual));
        $novo = strtolower(trim($novo));
        $permitidos = self::TRANSITIONS[$atual] ?? null;
        if ($permitidos === null) {
            return false;
        }

        return in_array($novo, $permitidos, true);
    }

    public function validarTransicao(string $atual, string $novo): void
    {
        if (! $this->podeTransicionar($atual, $novo)) {
            throw ValidationException::withMessages([
                'status' => "Transição inválida de {$atual} para {$novo}.",
            ]);
        }
    }

    public function isTerminal(string $status): bool
    {
        $status = strtolower(trim($status));
        $permitidos = self::TRANSITIONS[$status] ?? null;

        return is_array($permitidos) && $permitidos === [];
    }
}
