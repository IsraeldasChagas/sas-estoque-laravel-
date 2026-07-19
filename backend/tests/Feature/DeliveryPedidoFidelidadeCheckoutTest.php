<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeliveryPedidoFidelidadeCheckoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'fid_resgates', 'fid_recompensas', 'fid_ledger', 'fid_contas', 'fid_programas', 'fid_lgpd_aceites',
            'dlv_pedido_historico', 'dlv_pedido_itens', 'dlv_pedidos', 'dlv_entregadores',
            'dlv_frete_faixas_cep', 'dlv_produto_ingredientes', 'dlv_produto_adicional',
            'dlv_adicionais', 'dlv_produtos', 'dlv_categorias', 'dlv_loja_config', 'unidades',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->nullable();
            $table->timestamps();
        });

        (require database_path('migrations/2026_07_17_150000_create_delivery_tables.php'))->up();
        (require database_path('migrations/2026_07_17_160000_add_estoque_to_delivery_products.php'))->up();
        (require database_path('migrations/2026_07_17_180000_add_public_tracking_to_delivery_orders.php'))->up();
        (require database_path('migrations/2026_07_19_130000_add_vf_checkout_fields_to_delivery.php'))->up();
        (require database_path('migrations/2026_07_19_143000_add_fidelidade_fields_to_dlv_pedidos.php'))->up();
        (require database_path('migrations/2026_07_17_140000_create_fidelidade_tables.php'))->up();

        $unidadeId = (int) DB::table('unidades')->insertGetId([
            'nome' => 'Unidade Delivery',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('dlv_loja_config')->insert([
            'unidade_id' => $unidadeId,
            'slug' => 'loja-fid',
            'nome_loja' => 'Loja Fidelidade',
            'ativo' => 1,
            'aberta' => 1,
            'confirmar_pedidos' => 1,
            'permite_retirada' => 1,
            'frete_modo' => 'fixed',
            'frete_taxa_fixa' => 5,
            'formas_pagamento' => 'dinheiro,pix',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('fid_programas')->insert([
            'unidade_id' => $unidadeId,
            'ativo' => 1,
            'nome_exibicao' => 'Cartão Sabor',
            'modo' => 'selos',
            'pedidos_meta' => 10,
            'pontos_por_selo' => 1,
            'tipo_recompensa_padrao' => 'produto',
            'permite_ajuste_manual' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cat = DB::table('dlv_categorias')->insertGetId([
            'unidade_id' => $unidadeId,
            'nome' => 'Pratos',
            'ordem' => 0,
            'ativo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->productId = (int) DB::table('dlv_produtos')->insertGetId([
            'unidade_id' => $unidadeId,
            'categoria_id' => $cat,
            'nome' => 'Tacacá',
            'preco' => 20,
            'estoque' => 5,
            'ativo' => 1,
            'visivel_loja' => 1,
            'permite_adicionais' => 0,
            'acrescimo_escolhas_min' => 0,
            'max_ingredientes_retirar' => 1,
            'ingredientes_retirar_ui' => 'checkbox',
            'acrescimos_loja_ui' => 'stepper',
            'ordem' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private int $productId;

    public function test_checkout_com_fidelidade_abre_formulario_na_sucesso_e_credita_selo(): void
    {
        $payload = [
            'cliente_nome' => 'Maria Silva',
            'cliente_telefone' => '91988887777',
            'cliente_email' => 'maria@email.com',
            'fulfillment' => 'entrega',
            'endereco_cep' => '66000000',
            'endereco_rua' => 'Rua A',
            'endereco_numero' => '10',
            'endereco_bairro' => 'Centro',
            'endereco_cidade' => 'Belém',
            'endereco_uf' => 'PA',
            'pagamento_forma' => 'dinheiro',
            'fidelidade_quero' => true,
            'subtotal' => 0.01,
            'total' => 0.01,
            'itens' => [[
                'produto_id' => $this->productId,
                'quantidade' => 1,
                'preco' => 0.01,
                'opcoes' => [],
            ]],
        ];

        $checkout = $this->postJson('/loja/loja-fid/checkout', $payload)->assertCreated();
        $pedido = DB::table('dlv_pedidos')->first();

        $this->assertTrue((bool) $pedido->participa_fidelidade);
        $this->assertNull($pedido->fidelidade_selo_creditado_em);

        $this->get($checkout->json('redirect_url'))
            ->assertOk()
            ->assertSee('vfModalFidelidadeSucesso')
            ->assertSee('Termo de consentimento (LGPD)')
            ->assertSee('Ativar cartão e ganhar selo');

        $url = '/loja/loja-fid/sucesso/'.$pedido->codigo_publico.'/'.$pedido->cliente_token.'/fidelidade';

        $this->postJson($url, [
            'fidelidade_nome' => 'Maria Silva Souza',
            'fidelidade_email' => 'maria@email.com',
            'fidelidade_whatsapp' => '91988887777',
            'fidelidade_cpf' => '529.982.247-25',
            'lgpd_autorizo' => 1,
        ])->assertOk()->assertJsonPath('ok', true);

        $pedido = DB::table('dlv_pedidos')->where('id', $pedido->id)->first();
        $this->assertSame('Maria Silva Souza', $pedido->fidelidade_nome);
        $this->assertSame('52998224725', $pedido->fidelidade_cpf);
        $this->assertNotNull($pedido->fidelidade_selo_creditado_em);

        $conta = DB::table('fid_contas')->where('telefone_normalizado', '91988887777')->first();
        $this->assertNotNull($conta);
        $this->assertSame(1, (int) $conta->saldo_selos);
    }
}
