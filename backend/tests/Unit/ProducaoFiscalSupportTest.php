<?php

namespace Tests\Unit;

use App\Support\ProducaoFiscalSupport;
use PHPUnit\Framework\TestCase;

class ProducaoFiscalSupportTest extends TestCase
{
    public function test_rendimento_padrao_um(): void
    {
        $ficha = (object) ['rendimento_quantidade' => null, 'ingredientes_json' => '[]'];
        $this->assertSame(1.0, ProducaoFiscalSupport::rendimentoFicha($ficha));
    }

    public function test_rendimento_customizado(): void
    {
        $ficha = (object) ['rendimento_quantidade' => 10, 'ingredientes_json' => '[]'];
        $this->assertSame(10.0, ProducaoFiscalSupport::rendimentoFicha($ficha));
    }
}
