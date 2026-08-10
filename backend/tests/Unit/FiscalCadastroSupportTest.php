<?php

namespace Tests\Unit;

use App\Support\FiscalCadastroSupport;
use PHPUnit\Framework\TestCase;

class FiscalCadastroSupportTest extends TestCase
{
    public function test_status_fiscal_pendente_sem_tipo(): void
    {
        $this->assertSame('pendente', FiscalCadastroSupport::statusFiscalProduto(['tipo_fiscal' => null]));
    }

    public function test_status_fiscal_incompleto_com_tipo_sem_ncm(): void
    {
        $this->assertSame('incompleto', FiscalCadastroSupport::statusFiscalProduto([
            'tipo_fiscal' => 'revenda',
            'ncm' => null,
            'origem_mercadoria' => '0',
        ]));
    }

    public function test_status_fiscal_completo(): void
    {
        $this->assertSame('completo', FiscalCadastroSupport::statusFiscalProduto([
            'tipo_fiscal' => 'insumo',
            'ncm' => '10063021',
            'origem_mercadoria' => '0',
        ]));
    }

    public function test_normalizar_ncm(): void
    {
        $this->assertSame('10063021', FiscalCadastroSupport::normalizarNcm('1006.30.21'));
        $this->assertNull(FiscalCadastroSupport::normalizarNcm('123'));
    }

    public function test_normalizar_csosn_tres_digitos(): void
    {
        $this->assertSame('102', FiscalCadastroSupport::normalizarCsosn('102'));
        $this->assertSame('500', FiscalCadastroSupport::normalizarCsosn('500'));
        $this->assertSame('500', FiscalCadastroSupport::normalizarCsosn('0500'));
        $this->assertSame('102', FiscalCadastroSupport::normalizarCsosn('0102'));
        $this->assertNull(FiscalCadastroSupport::normalizarCsosn('060'));
        $this->assertNull(FiscalCadastroSupport::normalizarCsosn('999'));
    }
}
