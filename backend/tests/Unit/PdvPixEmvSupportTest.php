<?php

namespace Tests\Unit;

use App\Support\PdvPixEmvSupport;
use PHPUnit\Framework\TestCase;

class PdvPixEmvSupportTest extends TestCase
{
    public function test_montar_payload_com_valor_e_crc(): void
    {
        $payload = PdvPixEmvSupport::montarPayload([
            'chave' => '12345678901',
            'tipo_chave' => 'cpf',
            'beneficiario' => 'João da Silva',
            'cidade' => 'Belém',
            'txid' => 'PDVTESTE01',
        ], 25.5);

        $this->assertStringStartsWith('000201', $payload);
        $this->assertStringContainsString('br.gov.bcb.pix', $payload);
        $this->assertStringContainsString('12345678901', $payload);
        $this->assertStringContainsString('540525.50', $payload);
        $this->assertMatchesRegularExpression('/6304[0-9A-F]{4}$/', $payload);
        $crc = substr($payload, -4);
        $base = substr($payload, 0, -4);
        $this->assertSame($crc, PdvPixEmvSupport::crc16($base));
    }

    public function test_normalizar_telefone_e_email(): void
    {
        $this->assertSame('91999998888', PdvPixEmvSupport::normalizarChave('(91) 99999-8888', 'telefone'));
        $this->assertSame('pix@loja.com', PdvPixEmvSupport::normalizarChave('PIX@Loja.COM', 'email'));
    }
}
