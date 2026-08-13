<?php

namespace Tests\Unit;

use App\Models\FiscalEmissaoConfig;
use App\Support\FiscalEmissaoConfigSupport;
use PHPUnit\Framework\TestCase;

class FiscalEmissaoModoPdvTest extends TestCase
{
    private function cfg(string $modo, bool $ativo = true): FiscalEmissaoConfig
    {
        $c = new FiscalEmissaoConfig;
        $c->is_active = $ativo;
        $c->emitir_nfce_pdv = true;
        $c->provider = 'focus_nfe';
        $c->modo_emissao_pdv = $modo;

        return $c;
    }

    public function test_modo_automatica_emite_por_padrao(): void
    {
        $this->assertTrue(FiscalEmissaoConfigSupport::deveEmitirNoPdv($this->cfg('automatica'), null));
        $this->assertFalse(FiscalEmissaoConfigSupport::deveEmitirNoPdv($this->cfg('automatica'), false));
    }

    public function test_modo_opcional_só_com_marcacao(): void
    {
        $this->assertFalse(FiscalEmissaoConfigSupport::deveEmitirNoPdv($this->cfg('opcional'), null));
        $this->assertTrue(FiscalEmissaoConfigSupport::deveEmitirNoPdv($this->cfg('opcional'), true));
    }

    public function test_modo_desligada_respeita_marcacao_do_operador(): void
    {
        $this->assertFalse(FiscalEmissaoConfigSupport::deveEmitirNoPdv($this->cfg('desligada'), null));
        $this->assertTrue(FiscalEmissaoConfigSupport::deveEmitirNoPdv($this->cfg('desligada'), true));
        $this->assertFalse(FiscalEmissaoConfigSupport::deveEmitirNoPdv($this->cfg('desligada'), false));
    }
}
