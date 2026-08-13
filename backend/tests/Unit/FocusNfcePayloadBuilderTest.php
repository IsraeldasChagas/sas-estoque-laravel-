<?php

namespace Tests\Unit;

use App\Support\FocusNfcePayloadBuilder;
use PHPUnit\Framework\TestCase;

class FocusNfcePayloadBuilderTest extends TestCase
{
    public function test_monta_payload_com_item_e_pagamento(): void
    {
        $venda = (object) [
            'id' => 1,
            'forma_pagamento' => 'PIX',
        ];
        $itens = [
            (object) [
                'produto_id' => 10,
                'quantidade' => 2,
                'preco_unitario' => 15.5,
                'valor_total' => 31.0,
            ],
        ];
        $empresa = (object) ['cnpj' => '04.052.123/0001-90'];
        $config = (object) [
            'serie_nfce' => 1,
            'numero_proximo_nfce' => 100,
            'csc_id' => '1',
            'csc_token' => 'ABC123',
        ];
        $produtos = [
            10 => (object) [
                'id' => 10,
                'nome' => 'Prato teste',
                'ncm' => '2106.90.90',
                'cfop_saida_padrao' => '5102',
                'csosn' => '102',
                'origem_mercadoria' => '0',
                'unidade_base' => 'UN',
            ],
        ];

        $payload = FocusNfcePayloadBuilder::build($venda, $itens, $empresa, $config, $produtos);

        $this->assertSame('04052123000190', $payload['cnpj_emitente']);
        $this->assertCount(1, $payload['items']);
        $this->assertSame('21069090', $payload['items'][0]['codigo_ncm']);
        $this->assertSame('17', $payload['formas_pagamento'][0]['forma_pagamento']);
        $this->assertSame('100', $payload['numero']);
        $this->assertSame('00143710', $payload['codigo_unico']);
    }
}
