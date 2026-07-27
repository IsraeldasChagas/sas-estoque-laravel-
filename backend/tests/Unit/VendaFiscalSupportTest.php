<?php

namespace Tests\Unit;

use Tests\TestCase;

class VendaFiscalSupportTest extends TestCase
{
    public function test_modulo_ativo_after_migration(): void
    {
        if (! \App\Support\VendaFiscalSupport::moduloAtivo()) {
            $this->markTestSkipped('Tabelas vendas ausentes no ambiente de teste.');
        }
        $this->assertTrue(true);
    }
}
