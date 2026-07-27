<?php

namespace Tests\Unit;

use App\Support\FiscalMovimentacaoSupport;
use PHPUnit\Framework\TestCase;

class FiscalMovimentacaoSupportTest extends TestCase
{
    public function test_transferencia_mesma_empresa(): void
    {
        $tipo = FiscalMovimentacaoSupport::mapMotivoToTipoMovimentacao('TRANSFERENCIA', 1, 1);
        $this->assertSame('transferencia_interna', $tipo);
    }

    public function test_transferencia_empresas_diferentes(): void
    {
        $tipo = FiscalMovimentacaoSupport::mapMotivoToTipoMovimentacao('TRANSFERENCIA', 1, 2);
        $this->assertSame('operacao_entre_cnpjs', $tipo);
    }

    public function test_producao_nao_e_perda(): void
    {
        $tipo = FiscalMovimentacaoSupport::mapMotivoToTipoMovimentacao('PRODUCAO', 1, null);
        $this->assertSame('producao', $tipo);
        $evento = FiscalMovimentacaoSupport::mapTipoMovimentacaoToEvento($tipo);
        $this->assertSame('consumo_producao', $evento);
    }

    public function test_status_evento_perda(): void
    {
        $this->assertSame('impacto_potencial', FiscalMovimentacaoSupport::statusEventoInicial('perda'));
        $this->assertSame('sem_impacto', FiscalMovimentacaoSupport::statusEventoInicial('transferencia_interna'));
    }

    public function test_motivo_exige_justificativa(): void
    {
        $this->assertTrue(FiscalMovimentacaoSupport::motivoExigeJustificativa('CONSUMO'));
        $this->assertFalse(FiscalMovimentacaoSupport::motivoExigeJustificativa('PRODUCAO'));
    }
}
