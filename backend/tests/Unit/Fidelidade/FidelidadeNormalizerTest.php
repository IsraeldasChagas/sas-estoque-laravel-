<?php

namespace Tests\Unit\Fidelidade;

use App\Services\Fidelidade\FidelidadeNormalizer;
use PHPUnit\Framework\TestCase;

class FidelidadeNormalizerTest extends TestCase
{
    public function test_telefone_remove_pais_e_mascara(): void
    {
        $this->assertSame('69984639070', FidelidadeNormalizer::telefone('+55 (69) 98463-9070'));
        $this->assertSame('11987654321', FidelidadeNormalizer::telefone('011987654321'));
    }

    public function test_cpf_valido_e_invalido(): void
    {
        $this->assertSame('52998224725', FidelidadeNormalizer::cpf('529.982.247-25'));
        $this->assertTrue(FidelidadeNormalizer::cpfValido('52998224725'));
        $this->assertFalse(FidelidadeNormalizer::cpfValido('11111111111'));
        $this->assertFalse(FidelidadeNormalizer::cpfValido('12345678901'));
    }
}
