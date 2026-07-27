<?php

namespace Tests\Unit;

use App\Support\FiscalCompraEntradaSupport;
use PHPUnit\Framework\TestCase;

class FiscalCompraEntradaSupportTest extends TestCase
{
    public function test_divergencia_ncm_detectada(): void
    {
        $produto = (object) ['ncm' => '22030000', 'cest' => null, 'cst_icms' => null, 'csosn' => null, 'cfop_entrada_padrao' => null, 'origem_mercadoria' => null];
        $div = FiscalCompraEntradaSupport::divergenciasItem($produto, ['ncm' => '22021000']);
        $this->assertNotEmpty($div);
        $this->assertSame('ncm', $div[0]['campo']);
    }

    public function test_divergencia_nao_dispara_sem_cadastro(): void
    {
        $produto = (object) ['ncm' => null, 'cest' => null, 'cst_icms' => null, 'csosn' => null, 'cfop_entrada_padrao' => null, 'origem_mercadoria' => null];
        $div = FiscalCompraEntradaSupport::divergenciasItem($produto, ['ncm' => '22021000']);
        $this->assertSame([], $div);
    }
}
