<?php

namespace Tests\Unit;

use App\Support\FocusNfeTransferenciaPayloadBuilder;
use PHPUnit\Framework\TestCase;

class FocusNfeTransferenciaPayloadBuilderTest extends TestCase
{
    public function test_cfop_interestadual_vira_6xxx(): void
    {
        $this->assertSame('6102', FocusNfeTransferenciaPayloadBuilder::cfopTransferencia('5102', false));
        $this->assertSame('5102', FocusNfeTransferenciaPayloadBuilder::cfopTransferencia('5102', true));
        $this->assertSame('5102', FocusNfeTransferenciaPayloadBuilder::cfopTransferencia(null, true));
        $this->assertSame('6102', FocusNfeTransferenciaPayloadBuilder::cfopTransferencia(null, false));
    }

    public function test_monta_nfe_entre_cnpjs(): void
    {
        $origem = (object) [
            'cnpj' => '56.936.257/0001-04',
            'uf' => 'RO',
            'razao_social' => 'Origem LTDA',
        ];
        $destino = (object) [
            'cnpj' => '04.052.123/0001-90',
            'uf' => 'PA',
            'razao_social' => 'Destino LTDA',
            'inscricao_estadual' => '123',
            'logradouro' => 'Av. Nazare',
            'numero' => '100',
            'bairro' => 'Centro',
            'cep' => '66010000',
            'municipio' => 'Belem',
        ];
        $produto = (object) [
            'id' => 7,
            'nome' => 'Item teste',
            'ncm' => '2106.90.90',
            'cfop_saida_padrao' => '5102',
            'csosn' => '102',
            'origem_mercadoria' => '0',
            'unidade_base' => 'UN',
        ];
        $config = (object) [
            'environment' => 'homologation',
            'serie_nfe' => 1,
            'numero_proximo_nfe' => 50,
        ];

        $payload = FocusNfeTransferenciaPayloadBuilder::build($origem, $destino, $produto, $config, 2, 10.5, 88);

        $this->assertSame('56936257000104', $payload['cnpj_emitente']);
        $this->assertSame('04052123000190', $payload['cnpj_destinatario']);
        $this->assertSame('2', $payload['local_destino']);
        $this->assertSame('6102', $payload['items'][0]['cfop']);
        $this->assertSame('90', $payload['formas_pagamento'][0]['forma_pagamento']);
        $this->assertSame('50', $payload['numero']);
        $this->assertStringContainsString('HOMOLOGACAO', $payload['nome_destinatario']);
    }

    public function test_exige_endereco_destino(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        FocusNfeTransferenciaPayloadBuilder::build(
            (object) ['cnpj' => '56936257000104', 'uf' => 'RO'],
            (object) ['cnpj' => '04052123000190', 'uf' => 'PA', 'municipio' => 'Belem'],
            (object) ['id' => 1, 'nome' => 'X', 'ncm' => '21069090', 'cfop_saida_padrao' => '5102'],
            (object) ['environment' => 'production'],
            1,
            1,
            1
        );
    }
}
