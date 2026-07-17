<?php

namespace Tests\Unit;

use App\Services\Delivery\DeliveryPedidoStatusService;
use PHPUnit\Framework\TestCase;

class DeliveryPedidoStatusServiceTest extends TestCase
{
    public function test_transicoes_permitidas_e_terminais(): void
    {
        $service = new DeliveryPedidoStatusService;

        $this->assertTrue($service->podeTransicionar('pendente_loja', 'recebido'));
        $this->assertTrue($service->podeTransicionar('pendente_loja', 'cancelado'));
        $this->assertFalse($service->podeTransicionar('pendente_loja', 'rota'));
        $this->assertTrue($service->podeTransicionar('pronto', 'rota'));
        $this->assertTrue($service->podeTransicionar('rota', 'endereco_nao_encontrado'));
        $this->assertFalse($service->podeTransicionar('entregue', 'cancelado'));
        $this->assertTrue($service->isTerminal('entregue'));
        $this->assertTrue($service->isTerminal('cancelado'));
        $this->assertFalse($service->isTerminal('preparo'));
    }
}
