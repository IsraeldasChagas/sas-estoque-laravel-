<?php

namespace App\Services;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiToolLog;
use App\Support\SasIa\SasIaContext;
use App\Support\SasIa\SasIaPrefetchService;
use App\Support\SasIa\SasIaResponseSanitizer;
use Illuminate\Support\Facades\Schema;

/**
 * Orquestra o fluxo de chat: salvar mensagens, chamar OpenAI, executar ferramentas e responder.
 */
class SasIaChatService
{
    private const MAX_TOOL_ROUNDS = 5;

    private const MSG_SEM_PERMISSAO = 'Não encontrei informação suficiente ou você não tem permissão para acessar esse dado.';

    public function __construct(
        private OpenAiService $openAi,
        private SasIaToolService $toolService,
        private SasIaPrefetchService $prefetch
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function processar(SasIaContext $ctx, string $mensagem, ?int $conversationId = null): array
    {
        if (! $this->openAi->isConfigured()) {
            return ['error' => 'Assistente IA não configurado. Defina OPENAI_API_KEY no servidor.', 'code' => 503];
        }

        if (! Schema::hasTable('ai_conversations')) {
            return ['error' => 'Módulo SAS IA não instalado. Execute: php artisan migrate', 'code' => 503];
        }

        if (! $ctx->podePerguntar()) {
            return [
                'error' => 'Limite diário de perguntas atingido ('.$ctx->limiteDiario().'). Tente amanhã.',
                'code' => 429,
                'limite_diario' => $ctx->limiteDiario(),
                'usadas_hoje' => $ctx->perguntasHoje(),
            ];
        }

        $mensagem = trim($mensagem);
        if ($mensagem === '') {
            return ['error' => 'Digite uma mensagem.', 'code' => 422];
        }

        $conversa = $this->obterOuCriarConversa($ctx, $conversationId, $mensagem);

        $userMsg = $this->salvarMensagem($conversa->id, 'user', $mensagem);

        $historico = $this->carregarHistoricoOpenAi($conversa->id, 12);
        $systemPrompt = $this->systemPrompt($ctx).$this->prefetch->blocoParaPrompt($ctx, $mensagem);
        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $historico,
            [['role' => 'user', 'content' => $mensagem]]
        );

        $tools = OpenAiService::toolDefinitions();
        $toolsUsadas = [];
        $totalInput = 0;
        $totalOutput = 0;
        $totalCost = 0.0;

        $respostaFinal = self::MSG_SEM_PERMISSAO;
        $ultimaRodadaUsouTools = false;

        for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
            $result = $this->openAi->chat($messages, $tools, 0.65);
            $totalInput += $result['usage']['prompt_tokens'];
            $totalOutput += $result['usage']['completion_tokens'];
            $totalCost += $result['cost'];

            $assistantMsg = $result['message'];
            $toolCalls = $assistantMsg['tool_calls'] ?? null;

            if (empty($toolCalls)) {
                $respostaFinal = trim((string) ($assistantMsg['content'] ?? ''));
                if ($respostaFinal === '') {
                    $respostaFinal = self::MSG_SEM_PERMISSAO;
                }
                $ultimaRodadaUsouTools = false;
                break;
            }

            $ultimaRodadaUsouTools = true;
            $messages[] = $assistantMsg;

            foreach ($toolCalls as $tc) {
                $fn = $tc['function']['name'] ?? '';
                $argsJson = $tc['function']['arguments'] ?? '{}';
                $args = json_decode($argsJson, true);
                if (! is_array($args)) {
                    $args = [];
                }

                $toolsUsadas[] = $fn;
                $inicio = microtime(true);
                $toolResult = $this->toolService->executar($ctx, $fn, $args);
                $duracao = (int) round((microtime(true) - $inicio) * 1000);

                $this->registrarToolLog(
                    $ctx,
                    $conversa->id,
                    $userMsg->id,
                    $fn,
                    $args,
                    $toolResult,
                    $duracao
                );

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $tc['id'] ?? ('call_'.uniqid()),
                    'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        if ($ultimaRodadaUsouTools || SasIaResponseSanitizer::ehIntermediaria($respostaFinal)) {
            $respostaFinal = $this->finalizarResposta(
                $ctx,
                $conversa,
                $userMsg,
                $messages,
                $toolsUsadas,
                $totalInput,
                $totalOutput,
                $totalCost,
                $respostaFinal
            );
        }

        $respostaFinal = SasIaResponseSanitizer::limparRisadas($respostaFinal);
        if ($respostaFinal === '') {
            $respostaFinal = self::MSG_SEM_PERMISSAO;
        }

        $assistantRecord = $this->salvarMensagem(
            $conversa->id,
            'assistant',
            $respostaFinal,
            $toolsUsadas ? implode(', ', array_unique($toolsUsadas)) : null,
            $totalInput,
            $totalOutput,
            $totalCost
        );

        $conversa->touch();

        return [
            'conversation_id' => $conversa->id,
            'message_id' => $assistantRecord->id,
            'reply' => $respostaFinal,
            'tools_used' => array_values(array_unique($toolsUsadas)),
            'tokens_input' => $totalInput,
            'tokens_output' => $totalOutput,
            'cost_estimate' => round($totalCost, 6),
            'restante_hoje' => max(0, $ctx->limiteDiario() - $ctx->perguntasHoje()),
            'modelo' => $this->openAi->model(),
        ];
    }

    private function systemPrompt(SasIaContext $ctx): string
    {
        $branding = \App\Support\SasIa\SasIaBranding::ler();
        $nomeAgente = $branding['nome'];
        $nome = $ctx->usuario->nome ?? 'usuário';
        $primeiroNome = trim(explode(' ', $nome)[0] ?: $nome);
        $perfil = $ctx->perfil();
        $unidade = $ctx->unidadeEfetiva();
        $unidadeTxt = $unidade ? "Unidade em foco: ID {$unidade}." : 'Pode consultar todas as unidades permitidas.';

        $msgNeg = self::MSG_SEM_PERMISSAO;

        return <<<TXT
Você é a {$nomeAgente}, assistente do sistema SAS Estoque — Grupo Sabor Paraense.
Usuário: {$nome} (perfil {$perfil}). {$unidadeTxt}
Chame a pessoa de {$primeiroNome} quando fizer sentido. Você pode se apresentar como {$nomeAgente}.

Tom e estilo:
- Converse como uma colega de verdade: frases curtas, leves, com ritmo de WhatsApp no trabalho.
- Comece às vezes com "ah", "olha", "então" — sem repetir a mesma fórmula toda hora.
- Nunca soe robótica: evite "Conforme solicitado", "De acordo com os dados", "Em relação ao seu questionamento", "Segue abaixo".
- Prefira texto corrido; use listas só quando tiver muitos itens para comparar.
- Respostas simples: 2 a 4 frases. Vá direto ao ponto, sem encher linguiça.
- Chame de {$primeiroNome} de forma natural, não em toda mensagem.
- Pode usar emoji leve ocasionalmente (😊 👍), no máximo 1 por resposta — nunca emoji de riso.
- Nunca termine a resposta com risada escrita (kkk, rs, haha, hehe, hue, rsrs) nem com emoji de riso (😂 🤣 😆).
- Nunca diga que vai consultar, verificar ou pedir para aguardar ("aguarde", "um momento", "deixa eu ver"). Chame a ferramenta em silêncio e responda direto com o resultado final.

Regras:
- Responda sempre em português do Brasil.
- Para perguntas sobre números, estoque, vendas, financeiro, RH, reservas, patrimônio, energia, investimento ou cadastros: SEMPRE chame a ferramenta do módulo correspondente antes de responder.
- Para CNPJ, endereço ou dados completos de unidades/empresas: use consultar_resumo_unidades ou consultar_cadastro_geral (tipo unidade).
- Para reserva de mesa: os dados já vêm no contexto (RESERVAS_DE_MESA). Se tem_reservas ou reservas_hoje for true, diga quais são. Se false, diga que não há reserva ativa no período. Nunca diga que não há se os dados mostrarem reservas.
- Para CNPJ e unidades: use os dados UNIDADES_EMPRESAS do contexto ou consultar_resumo_unidades.
- Para buscar qualquer cadastro por nome/CNPJ/CPF: use consultar_cadastro_geral.
- Para procedimentos, regras internas, manuais ou "como fazer" no Grupo Sabor Paraense: SEMPRE chame consultar_manual_documentacao antes de responder.
- Use consultar_resumo_produtos para totais de produtos cadastrados.
- Use consultar_rh_recrutamento_resumo para totais de candidatos/currículos no RH (mesmo número do Dashboard Recrutamento).
- Nunca diga que acessou o banco diretamente; diga que consultou o sistema.
- Use a frase "{$msgNeg}" SOMENTE se a ferramenta retornar erro:true ou mensagem de sem permissão.
- Saudações e dúvidas gerais: responda normalmente, sem usar a frase de permissão.
- Não altere dados; apenas consulte e explique.
- Seja objetivo; evite listas longas quando uma frase resolve.
- Não use markdown, asteriscos (*) ou negrito — escreva texto puro, especialmente em números e valores.
TXT;
    }

    /**
     * Completa a resposta quando o modelo só consultou ferramentas ou pediu para aguardar.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, string>  $toolsUsadas
     */
    private function finalizarResposta(
        SasIaContext $ctx,
        AiConversation $conversa,
        AiMessage $userMsg,
        array $messages,
        array &$toolsUsadas,
        int &$totalInput,
        int &$totalOutput,
        float &$totalCost,
        string $respostaAtual
    ): string {
        $msgs = $messages;
        if ($respostaAtual !== '' && $respostaAtual !== self::MSG_SEM_PERMISSAO) {
            $msgs[] = ['role' => 'assistant', 'content' => $respostaAtual];
        }

        $instrucao = empty($toolsUsadas)
            ? 'Consulte o sistema com as ferramentas necessárias e responda ao usuário agora, de forma completa e direta. Não peça para aguardar.'
            : 'Com base nos dados que você já consultou, responda ao usuário agora de forma completa e direta. Não peça para aguardar nem diga que vai consultar.';

        $msgs[] = ['role' => 'user', 'content' => $instrucao];
        $tools = OpenAiService::toolDefinitions();

        for ($tentativa = 0; $tentativa < 3; $tentativa++) {
            $result = $this->openAi->chat($msgs, $tools, 0.5);
            $totalInput += $result['usage']['prompt_tokens'];
            $totalOutput += $result['usage']['completion_tokens'];
            $totalCost += $result['cost'];

            $assistantMsg = $result['message'];
            $toolCalls = $assistantMsg['tool_calls'] ?? null;

            if (! empty($toolCalls)) {
                $msgs[] = $assistantMsg;
                foreach ($toolCalls as $tc) {
                    $fn = $tc['function']['name'] ?? '';
                    $argsJson = $tc['function']['arguments'] ?? '{}';
                    $args = json_decode($argsJson, true);
                    if (! is_array($args)) {
                        $args = [];
                    }

                    $toolsUsadas[] = $fn;
                    $inicio = microtime(true);
                    $toolResult = $this->toolService->executar($ctx, $fn, $args);
                    $duracao = (int) round((microtime(true) - $inicio) * 1000);

                    $this->registrarToolLog(
                        $ctx,
                        $conversa->id,
                        $userMsg->id,
                        $fn,
                        $args,
                        $toolResult,
                        $duracao
                    );

                    $msgs[] = [
                        'role' => 'tool',
                        'tool_call_id' => $tc['id'] ?? ('call_'.uniqid()),
                        'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE),
                    ];
                }

                continue;
            }

            $texto = trim((string) ($assistantMsg['content'] ?? ''));
            if ($texto !== '' && ! SasIaResponseSanitizer::ehIntermediaria($texto)) {
                return $texto;
            }

            if ($texto !== '') {
                $msgs[] = ['role' => 'assistant', 'content' => $texto];
            }
        }

        $result = $this->openAi->chat($msgs, [], 0.45);
        $totalInput += $result['usage']['prompt_tokens'];
        $totalOutput += $result['usage']['completion_tokens'];
        $totalCost += $result['cost'];

        $texto = trim((string) ($result['message']['content'] ?? ''));
        if ($texto !== '' && ! SasIaResponseSanitizer::ehIntermediaria($texto)) {
            return $texto;
        }

        return $respostaAtual !== '' && $respostaAtual !== self::MSG_SEM_PERMISSAO
            ? $respostaAtual
            : self::MSG_SEM_PERMISSAO;
    }

    private function obterOuCriarConversa(SasIaContext $ctx, ?int $conversationId, string $primeiraMsg): AiConversation
    {
        if ($conversationId) {
            $c = AiConversation::query()
                ->where('id', $conversationId)
                ->where('usuario_id', $ctx->usuarioId())
                ->first();
            if ($c) {
                return $c;
            }
        }

        $titulo = mb_substr($primeiraMsg, 0, 80);

        return AiConversation::create([
            'usuario_id' => $ctx->usuarioId(),
            'unidade_id' => $ctx->unidadeEfetiva(),
            'titulo' => $titulo,
        ]);
    }

    private function salvarMensagem(
        int $conversationId,
        string $role,
        string $content,
        ?string $toolName = null,
        int $tokensIn = 0,
        int $tokensOut = 0,
        float $cost = 0
    ): AiMessage {
        return AiMessage::create([
            'conversation_id' => $conversationId,
            'role' => $role,
            'content' => $content,
            'tool_name' => $toolName,
            'tokens_input' => $tokensIn,
            'tokens_output' => $tokensOut,
            'cost_estimate' => $cost,
            'created_at' => now(),
        ]);
    }

    /** @return array<int, array{role: string, content: string}> */
    private function carregarHistoricoOpenAi(int $conversationId, int $limite): array
    {
        $rows = AiMessage::query()
            ->where('conversation_id', $conversationId)
            ->whereIn('role', ['user', 'assistant'])
            ->orderByDesc('id')
            ->limit($limite)
            ->get()
            ->reverse()
            ->values();

        $out = [];
        foreach ($rows as $r) {
            if ($r->content) {
                $out[] = ['role' => $r->role, 'content' => $r->content];
            }
        }

        return $out;
    }

    /** @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $result
     */
    private function registrarToolLog(
        SasIaContext $ctx,
        int $conversationId,
        int $messageId,
        string $toolName,
        array $args,
        array $result,
        int $durationMs
    ): void {
        if (! Schema::hasTable('ai_tool_logs')) {
            return;
        }

        $summary = json_encode($result, JSON_UNESCAPED_UNICODE);
        if (mb_strlen($summary) > 2000) {
            $summary = mb_substr($summary, 0, 2000).'…';
        }

        AiToolLog::create([
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'usuario_id' => $ctx->usuarioId(),
            'tool_name' => $toolName,
            'params_json' => $args,
            'result_summary' => $summary,
            'success' => empty($result['erro']),
            'duration_ms' => $durationMs,
            'created_at' => now(),
        ]);
    }
}
