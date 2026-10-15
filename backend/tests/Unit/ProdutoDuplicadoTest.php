<?php

namespace Tests\Unit;

use App\Support\ProdutoDuplicado;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProdutoDuplicadoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('produtos');
        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('codigo_barras')->nullable();
        });
    }

    public function test_nome_igual_com_acento_espaco_ou_maiuscula_e_duplicata(): void
    {
        $id = DB::table('produtos')->insertGetId([
            'nome' => 'Açaí especial',
            'codigo_barras' => 'PROD-1',
        ]);
        DB::table('produtos')->insert([
            'nome' => 'Cupuaçu',
            'codigo_barras' => 'PROD-2',
        ]);

        $this->assertSame('acai especial', ProdutoDuplicado::nomeComparavel('  AÇAÍ   especial '));
        $this->assertSame('cupuacu', ProdutoDuplicado::nomeComparavel('Cupuaçu'));
        $this->assertSame('Já existe um produto com este nome.', ProdutoDuplicado::mensagem('Acai especial'));
        $this->assertSame('Já existe um produto com este nome.', ProdutoDuplicado::mensagem('CUPUAÇU'));
        $this->assertNull(ProdutoDuplicado::mensagem('Açaí especial', $id));
        $this->assertNull(ProdutoDuplicado::mensagem('Farinha de mandioca'));
    }
}
