<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PatrimonioSubcategoriaTest extends TestCase
{
    private const MIGRACOES = [
        '2026_05_21_100000_create_patrimonio_tables.php',
        '2026_05_25_140000_patrimonio_setores_e_observacoes.php',
        '2026_10_28_090000_add_patrimonio_categoria_seguranca_eletronica.php',
        '2026_10_28_091500_set_icone_patrimonio_categoria_seguranca_eletronica.php',
        '2026_10_28_120000_add_patrimonio_subcategorias.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['patrimonio_inventario_itens', 'patrimonio_inventario', 'patrimonio_historico', 'patrimonio_fotos', 'patrimonio_documentos',
            'patrimonio_manutencoes', 'patrimonio_movimentacoes', 'patrimonios', 'patrimonio_setores', 'patrimonio_categorias', 'unidades', 'usuarios'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::create('usuarios', function (Blueprint $t) {
            $t->id();
            $t->string('nome');
            $t->string('perfil');
            $t->boolean('ativo')->default(true);
            $t->text('permissoes_menu')->nullable();
        });
        Schema::create('unidades', function (Blueprint $t) {
            $t->id();
            $t->string('nome');
        });
        DB::table('usuarios')->insert(['id' => 1, 'nome' => 'Admin', 'perfil' => 'ADMIN', 'ativo' => 1]);
        DB::table('unidades')->insert(['id' => 1, 'nome' => 'Loja Centro']);
        foreach (self::MIGRACOES as $arquivo) {
            (require database_path('migrations/' . $arquivo))->up();
        }
    }

    private function headers(): array
    {
        return ['X-Usuario-Id' => '1'];
    }

    public function test_eletronico_tem_subcategorias_e_patrimonio_guarda_a_escolhida(): void
    {
        $cats = collect($this->withHeaders($this->headers())->getJson('/api/patrimonio/categorias')->assertOk()->json());
        $eletronico = $cats->firstWhere('nome', 'Eletrônico');
        $this->assertNotNull($eletronico);
        $this->assertSame(['TV', 'Celular', 'Tablet', 'Informática', 'Outros'], $eletronico['subcategorias']);
        $this->assertSame([], $cats->firstWhere('nome', 'Veículos')['subcategorias']);

        (require database_path('migrations/2026_10_28_120000_add_patrimonio_subcategorias.php'))->up();
        $this->assertSame(1, DB::table('patrimonio_categorias')->where('nome', 'Eletrônico')->count());

        $id = $this->withHeaders($this->headers())->post('/api/patrimonio/patrimonios', [
            'nome' => 'TV Samsung 50"',
            'categoria_id' => $eletronico['id'],
            'subcategoria' => 'TV',
            'unidade_id' => 1,
        ])->assertStatus(201)->json('id');
        $this->withHeaders($this->headers())->getJson("/api/patrimonio/patrimonios/{$id}")
            ->assertOk()->assertJsonPath('patrimonio.subcategoria', 'TV');

        $this->withHeaders($this->headers())->post("/api/patrimonio/patrimonios/{$id}", ['nome' => 'TV Samsung 50"', 'subcategoria' => 'Outros'])->assertOk();
        $this->assertSame('Outros', DB::table('patrimonios')->where('id', $id)->value('subcategoria'));

        $this->withHeaders($this->headers())->post("/api/patrimonio/patrimonios/{$id}", ['nome' => 'TV Samsung 50"', 'subcategoria' => ''])->assertOk();
        $this->assertNull(DB::table('patrimonios')->where('id', $id)->value('subcategoria'));
    }

    public function test_categoria_aceita_subcategorias_em_texto_ou_lista(): void
    {
        $id = $this->withHeaders($this->headers())->postJson('/api/patrimonio/categorias', [
            'nome' => 'Cozinha',
            'subcategorias' => "Fogão\nGeladeira, fogão ; Exaustor",
        ])->assertStatus(201)->json('id');
        $cat = collect($this->withHeaders($this->headers())->getJson('/api/patrimonio/categorias')->json())->firstWhere('id', $id);
        $this->assertSame(['Fogão', 'Geladeira', 'Exaustor'], $cat['subcategorias']);

        $this->withHeaders($this->headers())->putJson("/api/patrimonio/categorias/{$id}", [
            'nome' => 'Cozinha', 'tipo_campos' => 'geral', 'ordem' => 50, 'subcategorias' => [],
        ])->assertOk();
        $cat = collect($this->withHeaders($this->headers())->getJson('/api/patrimonio/categorias')->json())->firstWhere('id', $id);
        $this->assertSame([], $cat['subcategorias']);
    }
}
