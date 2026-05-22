<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('patrimonio_categorias')) {
            Schema::create('patrimonio_categorias', function (Blueprint $table) {
                $table->id();
                $table->string('nome', 120);
                $table->string('slug', 80)->unique();
                $table->string('icone', 40)->nullable();
                $table->unsignedSmallInteger('ordem')->default(0);
                $table->boolean('ativo')->default(true);
                $table->string('tipo_campos', 40)->nullable(); // veiculo, informatica, refrigeracao, geral
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('patrimonios')) {
            Schema::create('patrimonios', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 40)->unique();
                $table->string('qr_token', 64)->unique();
                $table->string('nome', 500);
                $table->string('numero_serial', 120)->nullable();
                $table->unsignedBigInteger('categoria_id')->nullable();
                $table->string('marca', 120)->nullable();
                $table->string('modelo', 120)->nullable();
                $table->string('cor', 60)->nullable();
                $table->unsignedInteger('quantidade')->default(1);
                $table->unsignedBigInteger('unidade_id')->nullable();
                $table->string('setor', 120)->nullable();
                $table->string('responsavel', 255)->nullable();
                $table->unsignedBigInteger('funcionario_id')->nullable();
                $table->string('situacao', 30)->default('ativo'); // ativo, manutencao, baixado, vendido, quebrado
                $table->decimal('valor_compra', 14, 2)->nullable();
                $table->date('data_compra')->nullable();
                $table->unsignedSmallInteger('vida_util_meses')->nullable();
                $table->decimal('valor_atual', 14, 2)->nullable();
                $table->decimal('depreciacao', 14, 2)->nullable();
                $table->string('fornecedor', 255)->nullable();
                $table->string('numero_nf', 80)->nullable();
                $table->json('dados_especificos')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();
                $table->index(['unidade_id', 'situacao']);
                $table->index('categoria_id');
            });
        }

        if (! Schema::hasTable('patrimonio_movimentacoes')) {
            Schema::create('patrimonio_movimentacoes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('patrimonio_id');
                $table->string('tipo', 40); // transferencia, emprestimo, devolucao, troca_unidade, troca_responsavel, baixa
                $table->unsignedBigInteger('unidade_origem_id')->nullable();
                $table->unsignedBigInteger('unidade_destino_id')->nullable();
                $table->string('responsavel_anterior', 255)->nullable();
                $table->string('responsavel_novo', 255)->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->text('observacao')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['patrimonio_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('patrimonio_manutencoes')) {
            Schema::create('patrimonio_manutencoes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('patrimonio_id');
                $table->string('tipo_manutencao', 30); // preventiva, corretiva
                $table->text('descricao')->nullable();
                $table->string('tecnico', 255)->nullable();
                $table->decimal('custo', 14, 2)->nullable();
                $table->date('data_manutencao');
                $table->date('proxima_manutencao')->nullable();
                $table->string('anexo_path', 500)->nullable();
                $table->string('anexo_nome', 255)->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();
                $table->index(['patrimonio_id', 'data_manutencao']);
            });
        }

        if (! Schema::hasTable('patrimonio_documentos')) {
            Schema::create('patrimonio_documentos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('patrimonio_id');
                $table->string('tipo', 40)->default('outro'); // nf, garantia, crlv, contrato, outro
                $table->string('nome', 255);
                $table->string('path', 500);
                $table->string('mime', 120)->nullable();
                $table->unsignedBigInteger('tamanho')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index('patrimonio_id');
            });
        }

        if (! Schema::hasTable('patrimonio_fotos')) {
            Schema::create('patrimonio_fotos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('patrimonio_id');
                $table->string('path', 500);
                $table->string('nome', 255)->nullable();
                $table->unsignedSmallInteger('ordem')->default(0);
                $table->timestamp('created_at')->useCurrent();
                $table->index('patrimonio_id');
            });
        }

        if (! Schema::hasTable('patrimonio_historico')) {
            Schema::create('patrimonio_historico', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('patrimonio_id');
                $table->string('acao', 80);
                $table->json('detalhes')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['patrimonio_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('patrimonio_inventario')) {
            Schema::create('patrimonio_inventario', function (Blueprint $table) {
                $table->id();
                $table->string('titulo', 255);
                $table->unsignedBigInteger('unidade_id')->nullable();
                $table->string('status', 20)->default('aberto'); // aberto, fechado
                $table->date('data_inicio')->nullable();
                $table->date('data_fim')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('patrimonio_inventario_itens')) {
            Schema::create('patrimonio_inventario_itens', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('inventario_id');
                $table->unsignedBigInteger('patrimonio_id');
                $table->string('localizacao', 255)->nullable();
                $table->unsignedInteger('qtd_sistema')->default(1);
                $table->unsignedInteger('qtd_encontrada')->nullable();
                $table->integer('diferenca')->nullable();
                $table->text('observacao')->nullable();
                $table->string('foto_path', 500)->nullable();
                $table->timestamp('conferido_em')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();
                $table->unique(['inventario_id', 'patrimonio_id']);
            });
        }

        $this->seedCategoriasPadrao();
    }

    private function seedCategoriasPadrao(): void
    {
        if (! Schema::hasTable('patrimonio_categorias')) {
            return;
        }
        if (DB::table('patrimonio_categorias')->exists()) {
            return;
        }
        $now = now();
        $cats = [
            ['nome' => 'Veículos', 'slug' => 'veiculos', 'icone' => '🚗', 'ordem' => 1, 'tipo_campos' => 'veiculo'],
            ['nome' => 'Informática', 'slug' => 'informatica', 'icone' => '💻', 'ordem' => 2, 'tipo_campos' => 'informatica'],
            ['nome' => 'Refrigeração', 'slug' => 'refrigeracao', 'icone' => '❄️', 'ordem' => 3, 'tipo_campos' => 'refrigeracao'],
            ['nome' => 'Móveis', 'slug' => 'moveis', 'icone' => '🪑', 'ordem' => 4, 'tipo_campos' => 'geral'],
            ['nome' => 'Equipamentos', 'slug' => 'equipamentos', 'icone' => '⚙️', 'ordem' => 5, 'tipo_campos' => 'geral'],
            ['nome' => 'Ferramentas', 'slug' => 'ferramentas', 'icone' => '🔧', 'ordem' => 6, 'tipo_campos' => 'geral'],
            ['nome' => 'Celulares', 'slug' => 'celulares', 'icone' => '📱', 'ordem' => 7, 'tipo_campos' => 'geral'],
            ['nome' => 'Painéis solares', 'slug' => 'paineis-solares', 'icone' => '☀️', 'ordem' => 8, 'tipo_campos' => 'geral'],
            ['nome' => 'Outros', 'slug' => 'outros', 'icone' => '📦', 'ordem' => 99, 'tipo_campos' => 'geral'],
        ];
        foreach ($cats as $c) {
            DB::table('patrimonio_categorias')->insert(array_merge($c, [
                'ativo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('patrimonio_inventario_itens');
        Schema::dropIfExists('patrimonio_inventario');
        Schema::dropIfExists('patrimonio_historico');
        Schema::dropIfExists('patrimonio_fotos');
        Schema::dropIfExists('patrimonio_documentos');
        Schema::dropIfExists('patrimonio_manutencoes');
        Schema::dropIfExists('patrimonio_movimentacoes');
        Schema::dropIfExists('patrimonios');
        Schema::dropIfExists('patrimonio_categorias');
    }
};
