<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pdv_chaves_pix')) {
            Schema::create('pdv_chaves_pix', function (Blueprint $table) {
                $table->id();
                $table->string('apelido', 80)->nullable();
                $table->string('tipo_pessoa', 8)->default('pj'); // pf | pj
                $table->string('tipo_chave', 16); // cpf | cnpj | email | telefone | aleatoria
                $table->string('chave', 180);
                $table->string('beneficiario', 160);
                $table->string('cidade', 40)->default('BELEM');
                $table->string('documento', 20)->nullable();
                $table->boolean('ativo')->default(true);
                $table->boolean('padrao')->default(false);
                $table->unsignedSmallInteger('ordem')->default(0);
                $table->timestamps();
                $table->index(['ativo', 'ordem']);
            });
        }

        if (Schema::hasTable('vendas') && ! Schema::hasColumn('vendas', 'pagamento_pix_chave_id')) {
            Schema::table('vendas', function (Blueprint $table) {
                $table->unsignedBigInteger('pagamento_pix_chave_id')->nullable()->after('pagamento_pix_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vendas') && Schema::hasColumn('vendas', 'pagamento_pix_chave_id')) {
            Schema::table('vendas', function (Blueprint $table) {
                $table->dropColumn('pagamento_pix_chave_id');
            });
        }
        Schema::dropIfExists('pdv_chaves_pix');
    }
};
