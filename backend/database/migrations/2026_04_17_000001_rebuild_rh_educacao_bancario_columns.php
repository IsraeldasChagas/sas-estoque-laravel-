<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Garante colunas de RH (escolaridade, formação JSON, dados bancários).
     *
     * IMPORTANTE: uma versão anterior desta migration fazia dropColumn nessas colunas
     * e as recriava vazias. Em produção isso apagava permanentemente educação e
     * dados bancários de todos os funcionários. Nunca use drop+recreate para
     * "reordenar" colunas em tabela com dados reais.
     */
    public function up(): void
    {
        if (! Schema::hasTable('funcionarios')) {
            return;
        }

        Schema::table('funcionarios', function (Blueprint $table) {
            if (! Schema::hasColumn('funcionarios', 'escolaridade')) {
                $table->string('escolaridade', 80)->nullable();
            }
            if (! Schema::hasColumn('funcionarios', 'formacao_json')) {
                $table->longText('formacao_json')->nullable();
            }
            if (! Schema::hasColumn('funcionarios', 'banco')) {
                $table->string('banco', 100)->nullable();
            }
            if (! Schema::hasColumn('funcionarios', 'agencia')) {
                $table->string('agencia', 20)->nullable();
            }
            if (! Schema::hasColumn('funcionarios', 'conta')) {
                $table->string('conta', 20)->nullable();
            }
            if (! Schema::hasColumn('funcionarios', 'conta_digito')) {
                $table->string('conta_digito', 5)->nullable();
            }
            if (! Schema::hasColumn('funcionarios', 'pix')) {
                $table->string('pix', 100)->nullable();
            }
        });
    }

    /**
     * Não remove colunas: rollback não deve apagar dados de RH.
     */
    public function down(): void
    {
        //
    }
};
