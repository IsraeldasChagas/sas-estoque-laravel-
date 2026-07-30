<?php

namespace Tests\Unit;

use App\Support\PdvConfigSupport;
use PHPUnit\Framework\TestCase;

class PdvConfigSegurancaTest extends TestCase
{
    public function test_cartao_exige_nsu_quando_configurado(): void
    {
        $cfg = ['exigir_nsu_cartao' => true, 'exigir_autorizacao_cartao' => false, 'exigir_bandeira_cartao' => false, 'exigir_identificador_pix' => false];

        $this->assertSame(
            'Informe o NSU do cartão (exigido pela configuração do PDV).',
            PdvConfigSupport::validarDadosPagamento('Crédito', [], $cfg)
        );
        $this->assertNull(PdvConfigSupport::validarDadosPagamento('Crédito', ['pagamento_nsu' => '123456'], $cfg));
    }

    public function test_pix_exige_identificador_quando_configurado(): void
    {
        $cfg = ['exigir_nsu_cartao' => false, 'exigir_autorizacao_cartao' => false, 'exigir_bandeira_cartao' => false, 'exigir_identificador_pix' => true];

        $this->assertSame(
            'Informe o identificador da transação PIX (exigido pela configuração do PDV).',
            PdvConfigSupport::validarDadosPagamento('PIX', [], $cfg)
        );
        $this->assertNull(PdvConfigSupport::validarDadosPagamento('PIX', ['pagamento_pix_id' => 'E123'], $cfg));
    }

    public function test_dinheiro_nao_exige_campos(): void
    {
        $cfg = ['exigir_nsu_cartao' => true, 'exigir_autorizacao_cartao' => true, 'exigir_bandeira_cartao' => true, 'exigir_identificador_pix' => true];

        $this->assertNull(PdvConfigSupport::validarDadosPagamento('Dinheiro', [], $cfg));
    }

    public function test_is_forma_cartao(): void
    {
        $this->assertTrue(PdvConfigSupport::isFormaCartao('Crédito'));
        $this->assertTrue(PdvConfigSupport::isFormaCartao('Débito'));
        $this->assertFalse(PdvConfigSupport::isFormaCartao('PIX'));
    }
}
