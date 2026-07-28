<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fiscal_emissao_configs')) {
            return;
        }

        Schema::create('fiscal_emissao_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->unique();
            $table->string('provider', 40)->default('focus_nfe');
            $table->enum('environment', ['homologation', 'production'])->default('homologation');
            $table->string('api_url', 500)->nullable();
            $table->text('api_token')->nullable();
            $table->longText('certificado_pfx')->nullable();
            $table->text('certificado_senha')->nullable();
            $table->string('csc_id', 20)->nullable();
            $table->text('csc_token')->nullable();
            $table->unsignedSmallInteger('serie_nfce')->nullable();
            $table->unsignedSmallInteger('serie_nfe')->nullable();
            $table->unsignedInteger('numero_proximo_nfce')->nullable();
            $table->unsignedInteger('numero_proximo_nfe')->nullable();
            $table->boolean('emitir_nfce_pdv')->default(true);
            $table->boolean('emitir_nfe_pedido')->default(false);
            $table->boolean('is_active')->default(false);
            $table->string('status_emissao', 32)->default('not_configured');
            $table->timestamp('last_validated_at')->nullable();
            $table->text('last_validation_message')->nullable();
            $table->json('config_json')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index('status_emissao');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_emissao_configs');
    }
};
