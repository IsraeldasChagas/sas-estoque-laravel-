<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rh_candidatos')) {
            Schema::create('rh_candidatos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('vaga_id')->nullable();

                // Dados permitidos na candidatura inicial (LGPD: sem CPF/RG/CTPS/etc.)
                $table->string('nome', 160);
                $table->string('telefone', 40)->nullable();
                $table->string('email', 160)->nullable();
                $table->string('cidade', 120)->nullable();
                $table->string('bairro', 120)->nullable();
                $table->longText('experiencia')->nullable();
                $table->string('ultimo_emprego', 160)->nullable();
                $table->string('disponibilidade', 80)->nullable();
                $table->string('pretensao_salarial', 80)->nullable();
                $table->string('unidade', 120)->nullable();
                $table->string('foto_path', 255)->nullable(); // foto opcional do candidato (não é documento de contratação)

                // LGPD: consentimento obrigatório
                $table->boolean('consentimento_lgpd')->default(false);
                $table->timestamp('consentimento_em')->nullable();
                $table->string('consentimento_ip', 64)->nullable();
                $table->string('consentimento_user_agent', 255)->nullable();

                // Processo seletivo
                $table->string('status', 30)->default('novo'); // novo, em_analise, entrevista, aprovado, em_contratacao, contratado, reprovado, banco_talentos
                $table->longText('observacoes_internas')->nullable();

                // Anonimização/exclusão
                $table->timestamp('anonimizado_em')->nullable();
                $table->unsignedBigInteger('anonimizado_por')->nullable();

                $table->timestamps();

                $table->index(['vaga_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        // Sem down destrutivo por segurança.
    }
};

