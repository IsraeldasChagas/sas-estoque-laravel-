<?php

namespace Tests\Unit;

use App\Support\Delivery\CardapioFichaEstoqueSupport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CardapioFichaEstoqueSupportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('fichas_tecnicas');
        Schema::dropIfExists('produtos');
        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('tipo_fiscal')->nullable();
        });
        Schema::create('fichas_tecnicas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('produto_final_id')->nullable();
            $table->string('nome_prato', 500);
            $table->decimal('rendimento_quantidade', 14, 4)->nullable();
            $table->longText('ingredientes_json');
            $table->timestamps();
        });
    }

    public function test_resumo_lista_insumos_e_semi_acabado(): void
    {
        $pratoId = DB::table('produtos')->insertGetId(['nome' => 'Filé com farofa']);
        $farofaId = DB::table('produtos')->insertGetId(['nome' => 'Farofa pronta']);
        $farinhaId = DB::table('produtos')->insertGetId(['nome' => 'Farinha', 'tipo_fiscal' => 'insumo']);
        $cocaId = DB::table('produtos')->insertGetId(['nome' => 'Coca lata', 'tipo_fiscal' => 'revenda']);

        DB::table('fichas_tecnicas')->insert([
            'produto_final_id' => $farofaId,
            'nome_prato' => 'Farofa',
            'rendimento_quantidade' => 1,
            'ingredientes_json' => json_encode([
                ['produto_id' => $farinhaId, 'nome' => 'Farinha', 'quantidade' => 0.5, 'unidade_medida' => 'kg'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ingPrato = json_encode([
            ['produto_id' => $farofaId, 'nome' => 'Farofa pronta', 'quantidade' => 0.2, 'unidade_medida' => 'kg'],
            ['produto_id' => $cocaId, 'nome' => 'Coca', 'quantidade' => 1, 'unidade_medida' => 'un'],
        ]);
        DB::table('fichas_tecnicas')->insert([
            'produto_final_id' => $pratoId,
            'nome_prato' => 'Filé com farofa',
            'rendimento_quantidade' => 1,
            'ingredientes_json' => $ingPrato,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resumo = CardapioFichaEstoqueSupport::resumoPorProdutoFinal($pratoId);
        $this->assertNotNull($resumo);
        $this->assertSame('Filé com farofa', $resumo['nome_prato']);
        $this->assertCount(2, $resumo['ingredientes']);

        $farofa = collect($resumo['ingredientes'])->firstWhere('tipo', 'semi_acabado');
        $this->assertNotNull($farofa);
        $this->assertSame($farofaId, $farofa['produto_id']);
        $this->assertCount(1, $farofa['semi_acabado']);

        $revenda = collect($resumo['ingredientes'])->firstWhere('tipo', 'revenda');
        $this->assertNotNull($revenda);
        $this->assertSame($cocaId, $revenda['produto_id']);

        $this->assertNull(CardapioFichaEstoqueSupport::mensagemSeSemFicha($pratoId));
        $this->assertNotNull(CardapioFichaEstoqueSupport::mensagemSeSemFicha(999));
    }
}
