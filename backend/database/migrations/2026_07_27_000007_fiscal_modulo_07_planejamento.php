<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('regras_fiscais')) {
            Schema::create('regras_fiscais', function (Blueprint $table) {
                $table->id();
                $table->string('nome', 150);
                $table->string('tributo', 20);
                $table->string('regime_tributario', 40)->nullable();
                $table->string('tipo_operacao', 40)->nullable();
                $table->unsignedBigInteger('perfil_tributario_id')->nullable();
                $table->char('uf_origem', 2)->nullable();
                $table->char('uf_destino', 2)->nullable();
                $table->date('vigencia_inicio');
                $table->date('vigencia_fim')->nullable();
                $table->json('configuracao_json');
                $table->boolean('ativo')->default(true);
                $table->unsignedInteger('versao')->default(1);
                $table->text('observacao')->nullable();
                $table->timestamps();
                $table->index(['tributo', 'regime_tributario', 'vigencia_inicio']);
            });
        }

        if (! Schema::hasTable('apuracoes_fiscais')) {
            Schema::create('apuracoes_fiscais', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->date('periodo_inicio');
                $table->date('periodo_fim');
                $table->string('regime_tributario', 40)->nullable();
                $table->string('status', 24)->default('aberta');
                $table->decimal('total_debitos', 14, 2)->default(0);
                $table->decimal('total_creditos', 14, 2)->default(0);
                $table->decimal('total_estornos', 14, 2)->default(0);
                $table->decimal('total_ajustes', 14, 2)->default(0);
                $table->decimal('total_estimado', 14, 2)->default(0);
                $table->decimal('total_validado', 14, 2)->nullable();
                $table->string('regra_versao', 32)->nullable();
                $table->json('snapshot_json')->nullable();
                $table->dateTime('calculado_em')->nullable();
                $table->dateTime('validado_em')->nullable();
                $table->unsignedBigInteger('validado_por')->nullable();
                $table->timestamps();
                $table->index(['empresa_id', 'periodo_inicio', 'periodo_fim']);
            });
        }

        if (! Schema::hasTable('apuracao_fiscal_itens')) {
            Schema::create('apuracao_fiscal_itens', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('apuracao_id');
                $table->string('tributo', 20);
                $table->decimal('debitos', 14, 2)->default(0);
                $table->decimal('creditos', 14, 2)->default(0);
                $table->decimal('estornos', 14, 2)->default(0);
                $table->decimal('ajustes', 14, 2)->default(0);
                $table->decimal('valor_estimado', 14, 2)->default(0);
                $table->decimal('valor_validado', 14, 2)->nullable();
                $table->unsignedBigInteger('regra_fiscal_id')->nullable();
                $table->timestamps();
                $table->index('apuracao_id');
            });
        }

        if (! Schema::hasTable('estornos_fiscais')) {
            Schema::create('estornos_fiscais', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->unsignedBigInteger('evento_fiscal_id')->nullable();
                $table->unsignedBigInteger('credito_fiscal_entrada_id')->nullable();
                $table->unsignedBigInteger('movimentacao_id')->nullable();
                $table->string('tipo_evento', 40)->nullable();
                $table->string('status', 24)->default('nao_analisado');
                $table->decimal('valor_potencial', 14, 2)->default(0);
                $table->text('observacao')->nullable();
                $table->timestamps();
                $table->index(['empresa_id', 'status']);
            });
        }

        if (! Schema::hasTable('cenarios_tributarios')) {
            Schema::create('cenarios_tributarios', function (Blueprint $table) {
                $table->id();
                $table->string('nome', 200);
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->unsignedBigInteger('produto_id')->nullable();
                $table->json('premissas_json');
                $table->json('resultado_json');
                $table->string('regra_versao', 32)->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('regras_fiscais') && DB::table('regras_fiscais')->count() === 0) {
            $vigencia = '2020-01-01';
            $cfg = fn (float $pct, string $base = 'receita') => json_encode([
                'tipo_calculo' => 'percentual_base',
                'base' => $base,
                'percentual_estimado' => $pct,
                'observacao' => 'Parâmetro gerencial — validar com contador',
            ]);
            $rows = [
                ['Estimativa ICMS saída — genérica', 'icms', null, 'venda', $cfg(0.12)],
                ['Estimativa PIS saída — genérica', 'pis', null, 'venda', $cfg(0.0165)],
                ['Estimativa COFINS saída — genérica', 'cofins', null, 'venda', $cfg(0.076)],
                ['Estimativa crédito ICMS entrada — genérica', 'icms', null, 'entrada', $cfg(0.12, 'valor_entrada')],
                ['Simples — estimativa gerencial saída', 'simples', 'simples_nacional', 'venda', $cfg(0.06)],
                ['Lucro presumido — estimativa gerencial saída', 'presumido', 'lucro_presumido', 'venda', $cfg(0.08)],
                ['Lucro real — estimativa gerencial saída', 'irpj_csll', 'lucro_real', 'venda', $cfg(0.15)],
                ['Operação entre empresas — custo estimado', 'icms', null, 'operacao_entre_cnpjs', $cfg(0.04, 'valor_operacao')],
            ];
            foreach ($rows as [$nome, $trib, $reg, $op, $json]) {
                DB::table('regras_fiscais')->insert([
                    'nome' => $nome,
                    'tributo' => $trib,
                    'regime_tributario' => $reg,
                    'tipo_operacao' => $op,
                    'vigencia_inicio' => $vigencia,
                    'configuracao_json' => $json,
                    'ativo' => true,
                    'versao' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cenarios_tributarios');
        Schema::dropIfExists('estornos_fiscais');
        Schema::dropIfExists('apuracao_fiscal_itens');
        Schema::dropIfExists('apuracoes_fiscais');
        Schema::dropIfExists('regras_fiscais');
    }
};
