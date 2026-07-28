<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vendas')) {
            Schema::table('vendas', function (Blueprint $table) {
                if (! Schema::hasColumn('vendas', 'url_danfe')) {
                    $table->string('url_danfe', 500)->nullable()->after('chave_acesso');
                }
                if (! Schema::hasColumn('vendas', 'emissao_ref')) {
                    $table->string('emissao_ref', 80)->nullable()->after('url_danfe');
                }
                if (! Schema::hasColumn('vendas', 'serie_documento')) {
                    $table->string('serie_documento', 10)->nullable()->after('numero_documento');
                }
                if (! Schema::hasColumn('vendas', 'emissao_mensagem')) {
                    $table->text('emissao_mensagem')->nullable()->after('status_documento');
                }
            });
        }

        if (! Schema::hasTable('fiscal_emissao_logs')) {
            Schema::create('fiscal_emissao_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('venda_id')->nullable();
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->string('provider', 40)->default('focus_nfe');
                $table->string('ref', 80)->nullable();
                $table->string('status', 32)->nullable();
                $table->text('mensagem')->nullable();
                $table->json('resposta_json')->nullable();
                $table->timestamps();
                $table->index(['venda_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_emissao_logs');
        if (Schema::hasTable('vendas')) {
            Schema::table('vendas', function (Blueprint $table) {
                foreach (['url_danfe', 'emissao_ref', 'serie_documento', 'emissao_mensagem'] as $col) {
                    if (Schema::hasColumn('vendas', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
