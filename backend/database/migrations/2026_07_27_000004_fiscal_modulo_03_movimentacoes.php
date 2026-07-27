<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('movimentacoes')) {
            Schema::table('movimentacoes', function (Blueprint $table) {
                if (! Schema::hasColumn('movimentacoes', 'tipo_movimentacao')) {
                    $table->string('tipo_movimentacao', 40)->nullable()->after('motivo');
                    $table->index('tipo_movimentacao');
                }
                if (! Schema::hasColumn('movimentacoes', 'empresa_origem_id')) {
                    $table->unsignedBigInteger('empresa_origem_id')->nullable()->after('de_unidade_id');
                    $table->index('empresa_origem_id');
                }
                if (! Schema::hasColumn('movimentacoes', 'empresa_destino_id')) {
                    $table->unsignedBigInteger('empresa_destino_id')->nullable()->after('para_unidade_id');
                    $table->index('empresa_destino_id');
                }
                if (! Schema::hasColumn('movimentacoes', 'status_movimentacao')) {
                    $table->string('status_movimentacao', 24)->default('processada')->after('tipo_movimentacao');
                }
                if (! Schema::hasColumn('movimentacoes', 'status_documental')) {
                    $table->string('status_documental', 24)->nullable()->after('status_movimentacao');
                }
                if (! Schema::hasColumn('movimentacoes', 'numero_documento')) {
                    $table->string('numero_documento', 60)->nullable();
                }
                if (! Schema::hasColumn('movimentacoes', 'chave_acesso_documento')) {
                    $table->string('chave_acesso_documento', 44)->nullable();
                }
                if (! Schema::hasColumn('movimentacoes', 'modelo_documento')) {
                    $table->string('modelo_documento', 4)->nullable();
                }
                if (! Schema::hasColumn('movimentacoes', 'motivo_detalhe')) {
                    $table->text('motivo_detalhe')->nullable();
                }
                if (! Schema::hasColumn('movimentacoes', 'setor_destino')) {
                    $table->string('setor_destino', 120)->nullable();
                }
                if (! Schema::hasColumn('movimentacoes', 'numero_ocorrencia')) {
                    $table->string('numero_ocorrencia', 60)->nullable();
                }
                if (! Schema::hasColumn('movimentacoes', 'custo_total')) {
                    $table->decimal('custo_total', 14, 4)->nullable();
                }
            });
        }

        if (! Schema::hasTable('eventos_fiscais')) {
            Schema::create('eventos_fiscais', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->unsignedBigInteger('unidade_id')->nullable();
                $table->unsignedBigInteger('movimentacao_id')->nullable();
                $table->unsignedBigInteger('produto_id')->nullable();
                $table->unsignedBigInteger('lote_id')->nullable();
                $table->string('tipo_evento', 40);
                $table->string('origem_evento', 40)->default('movimentacao_estoque');
                $table->string('status', 32)->default('pendente_analise');
                $table->dateTime('data_evento');
                $table->decimal('valor_base', 14, 4)->default(0);
                $table->decimal('valor_estimado', 14, 4)->nullable();
                $table->text('observacao')->nullable();
                $table->timestamps();
                $table->index(['empresa_id', 'data_evento']);
                $table->index('movimentacao_id');
                $table->index('tipo_evento');
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_fiscais');

        if (Schema::hasTable('movimentacoes')) {
            Schema::table('movimentacoes', function (Blueprint $table) {
                foreach ([
                    'custo_total',
                    'numero_ocorrencia',
                    'setor_destino',
                    'motivo_detalhe',
                    'modelo_documento',
                    'chave_acesso_documento',
                    'numero_documento',
                    'status_documental',
                    'status_movimentacao',
                    'empresa_destino_id',
                    'empresa_origem_id',
                    'tipo_movimentacao',
                ] as $col) {
                    if (Schema::hasColumn('movimentacoes', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
