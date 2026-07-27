<?php

namespace Tests\Unit;

use App\Support\PlanejamentoTributarioSupport;
use App\Support\RegraFiscalSupport;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegraFiscalSupportTest extends TestCase
{
    public function test_modulo_ativo_after_migration(): void
    {
        if (! RegraFiscalSupport::moduloAtivo()) {
            $this->markTestSkipped('Tabela regras_fiscais ausente.');
        }
        $this->assertNotSame('0', RegraFiscalSupport::versaoAtual());
    }

    public function test_calcular_estimativa_percentual(): void
    {
        if (! RegraFiscalSupport::moduloAtivo()) {
            $this->markTestSkipped('Tabela regras_fiscais ausente.');
        }
        $regra = RegraFiscalSupport::regraAplicavel('icms', null, 'venda');
        if (! $regra) {
            $this->markTestSkipped('Sem regra seed icms/venda.');
        }
        $v = RegraFiscalSupport::calcularEstimativa($regra, 1000);
        $this->assertGreaterThan(0, $v);
    }

    public function test_simulador_tres_cenarios_sem_persistir(): void
    {
        if (! RegraFiscalSupport::moduloAtivo()) {
            $this->markTestSkipped('M7 não migrado.');
        }
        $c = DB::table('empresas')->orderBy('id')->value('id');
        if (! $c) {
            $this->markTestSkipped('Sem empresas cadastradas.');
        }
        $res = PlanejamentoTributarioSupport::simularTresCenarios([
            'empresa_c_id' => (int) $c,
            'empresa_b_id' => (int) $c,
            'quantidade' => 10,
            'preco_compra' => 5,
            'preco_venda' => 12,
        ]);
        $this->assertCount(3, $res['cenarios']);
        $this->assertArrayHasKey('comparacao', $res);
    }
}
