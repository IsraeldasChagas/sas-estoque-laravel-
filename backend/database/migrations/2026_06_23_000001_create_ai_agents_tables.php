<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public const MODULES = [
        'atendimento',
        'rh',
        'financeiro',
        'restaurante',
        'pericia',
        'administrativo',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('ai_agents')) {
            Schema::create('ai_agents', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('role', 160)->nullable();
                $table->text('description')->nullable();
                $table->longText('system_prompt');
                $table->string('avatar', 500)->nullable();
                $table->string('model', 80)->default('gpt-4o-mini');
                $table->decimal('temperature', 3, 2)->default(0.65);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('is_active');
            });
        }

        if (! Schema::hasTable('ai_agent_modules')) {
            Schema::create('ai_agent_modules', function (Blueprint $table) {
                $table->id();
                $table->string('module_key', 40)->unique();
                $table->unsignedBigInteger('agent_id')->nullable();
                $table->timestamps();

                $table->index('agent_id');
            });
        }

        $this->seedDefaultAgent();
    }

    private function seedDefaultAgent(): void
    {
        if (! Schema::hasTable('ai_agents') || DB::table('ai_agents')->exists()) {
            return;
        }

        $prompt = <<<'TXT'
Você é a Rafaela Almeida, assistente virtual do sistema SAS Estoque — Grupo Sabor Paraense.

Personalidade:
- Colega de trabalho acolhedora, objetiva e confiável.
- Tom leve, como WhatsApp no escritório: frases curtas, naturais, sem formalidade excessiva.
- Pode usar "ah", "olha", "então" ocasionalmente, sem repetir a mesma fórmula sempre.
- Chame a pessoa pelo primeiro nome quando fizer sentido.
- Emoji leve no máximo 1 por resposta (😊 👍). Nunca risada escrita (kkk, rs, haha) nem emoji de riso (😂 🤣).
- Nunca termine pedindo para aguardar: consulte o sistema em silêncio e responda com o resultado final.

Regras de atuação:
- Responda sempre em português do Brasil.
- Para números, estoque, vendas, financeiro, RH, reservas, patrimônio, energia, investimento ou cadastros: use as ferramentas do sistema antes de responder.
- Para CNPJ, endereço ou dados de unidades: use consultar_resumo_unidades ou consultar_cadastro_geral.
- Para reservas de mesa: use os dados de RESERVAS_DE_MESA no contexto.
- Para procedimentos e manuais internos: use consultar_manual_documentacao.
- Nunca diga que acessou o banco diretamente; diga que consultou o sistema.
- Não altere dados; apenas consulte e explique.
- Texto puro, sem markdown, asteriscos ou negrito.
- Se não houver permissão ou dado: diga que não encontrou informação suficiente ou que não tem permissão.
- Quando perguntarem o que você faz, suas habilidades ou como pode ajudar: explique de forma clara o que consegue consultar e orientar no SAS Estoque.
TXT;

        $now = now();
        $agentId = DB::table('ai_agents')->insertGetId([
            'name' => 'Rafaela Almeida',
            'role' => 'Assistente administrativa',
            'description' => 'Agente padrão do SAS IA — atendimento geral, consultas ao sistema e orientação no dia a dia.',
            'system_prompt' => $prompt,
            'avatar' => null,
            'model' => 'gpt-4o-mini',
            'temperature' => 0.65,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (! Schema::hasTable('ai_agent_modules')) {
            return;
        }

        foreach (self::MODULES as $module) {
            DB::table('ai_agent_modules')->insert([
                'module_key' => $module,
                'agent_id' => $agentId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agent_modules');
        Schema::dropIfExists('ai_agents');
    }
};
