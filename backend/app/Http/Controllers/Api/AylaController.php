<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AylaAuditLog;
use App\Services\AylaAccessService;
use App\Services\Ayla\AylaConviteService;
use App\Services\AylaApiService;
use App\Services\Ayla\AylaAcaoPendenteService;
use App\Services\Ayla\AylaKanbanService;
use App\Services\Ayla\AylaPatrimonioService;
use App\Services\Ayla\AylaReservasService;
use App\Support\Ayla\AylaResponse;
use App\Support\Ayla\AylaSettings;
use App\Support\Ayla\AylaWriteGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API Ayla v1 — leitura + escrita controlada (reservas via preparar/confirmar).
 */
class AylaController extends Controller
{
    /** Status de compra aceitos em filtros (allow-list). */
    private const STATUS_COMPRA = ['aberta', 'pendente', 'aprovada', 'concluida', 'cancelada', 'rascunho'];

    /** Status do kanban aceitos em filtros (allow-list + aliases). */
    private const STATUS_KANBAN = [
        'planejamento', 'a_fazer', 'em_execucao', 'aguardando', 'finalizado',
        'pendente', 'pendentes', 'em_andamento', 'andamento',
        'concluida', 'concluidas', 'concluido', 'concluidos', 'finalizada', 'finalizadas',
        'bloqueada', 'bloqueadas', 'bloqueado', 'aguardando',
        'atrasado', 'atrasada', 'atrasadas',
    ];

    /** Prioridades do kanban aceitas em filtros. */
    private const PRIORIDADE_KANBAN = ['baixa', 'media', 'alta', 'média'];

    /** Vencimento relativo aceito em filtros do kanban. */
    private const VENCIMENTO_KANBAN = ['hoje', 'amanha', 'amanhã', 'atrasado', 'atrasada', 'atrasadas'];

    /** Situações reais do patrimônio (coluna `situacao`). */
    private const STATUS_PATRIMONIO = ['ativo', 'manutencao', 'baixado', 'vendido', 'quebrado'];

    /** Status reais de reserva de mesa. */
    private const STATUS_RESERVA = [
        'pendente', 'confirmada', 'cancelada', 'cliente_chegou', 'no_show', 'finalizada',
    ];

    public function __construct(private AylaApiService $service) {}

    public function status(Request $request): JsonResponse
    {
        return $this->executar($request, 'ayla.status', function (?int $userId) {
            return [
                'integracao_ativa' => AylaSettings::ativo(),
                'versao' => AylaSettings::versao(),
                'read_only' => AylaSettings::somenteLeitura(),
                'servidor_horario' => now()->toIso8601String(),
                'modulos_liberados' => AylaSettings::modulosLiberados(),
                'usuario_identificado' => $userId !== null,
            ];
        }, 'Ayla operacional.');
    }

    public function unidades(Request $request): JsonResponse
    {
        return $this->consultar($request, 'ayla.unidades', 'consultar_resumo_unidades', ['busca', 'limite'], 'Unidades encontradas.');
    }

    public function produtos(Request $request): JsonResponse
    {
        return $this->executar($request, 'ayla.produtos', function (?int $userId) use ($request) {
            $p = $this->parseArgs($request, ['busca', 'produto_id', 'unidade_id', 'limite']);
            if ($p['erro']) {
                return $p['erro'];
            }
            $args = $p['args'];
            $unidadeId = $args['unidade_id'] ?? null;
            if (! AylaSettings::unidadePermitida($unidadeId)) {
                return ['_erro' => ['Unidade não autorizada.', 'UNIT_NOT_ALLOWED', 403]];
            }

            $busca = $args['busca'] ?? null;
            if ($busca === null || $busca === '') {
                return ['_erro' => ['Informe o parâmetro busca (nome do produto).', 'VALIDATION_ERROR', 422]];
            }

            $toolArgs = ['nome' => $busca];
            $r = $this->service->executarFerramenta('consultar_produto_por_nome', $toolArgs, $userId, $unidadeId);

            return $this->normalizar($r, $args['limite'] ?? 50, 'produtos');
        }, 'Produtos encontrados.');
    }

    public function produtosAbaixoMinimo(Request $request): JsonResponse
    {
        return $this->consultar($request, 'ayla.produtos.abaixo_minimo', 'consultar_produtos_abaixo_estoque_minimo', ['unidade_id', 'limite'], 'Produtos abaixo do mínimo.');
    }

    public function estoque(Request $request): JsonResponse
    {
        return $this->consultar($request, 'ayla.estoque', 'consultar_estoque_por_unidade', ['unidade_id', 'produto_id', 'busca', 'limite'], 'Estoque consultado.');
    }

    public function movimentacoes(Request $request): JsonResponse
    {
        return $this->consultar($request, 'ayla.estoque.movimentacoes', 'consultar_movimentacoes_recentes', ['unidade_id', 'dias', 'limite'], 'Movimentações recentes.');
    }

    public function lotesVencendo(Request $request): JsonResponse
    {
        return $this->consultar($request, 'ayla.lotes.vencendo', 'consultar_lotes_proximos_vencer', ['unidade_id', 'dias', 'limite'], 'Lotes próximos do vencimento.', ['dias' => 30]);
    }

    public function compras(Request $request): JsonResponse
    {
        return $this->consultar($request, 'ayla.compras', 'consultar_compras_recentes', ['unidade_id', 'dias', 'status', 'limite'], 'Compras recentes.');
    }

    public function fornecedores(Request $request): JsonResponse
    {
        return $this->consultar($request, 'ayla.fornecedores', 'consultar_fornecedores', ['busca', 'ativo', 'limite'], 'Fornecedores encontrados.');
    }

    public function dashboard(Request $request): JsonResponse
    {
        return $this->executar($request, 'ayla.dashboard', function (?int $userId) {
            $mapa = [
                'resumo_produtos' => ['consultar_resumo_produtos', []],
                'produtos_abaixo_minimo' => ['consultar_produtos_abaixo_estoque_minimo', []],
                'lotes_vencendo' => ['consultar_lotes_proximos_vencer', ['dias' => 30]],
                'movimentacoes_recentes' => ['consultar_movimentacoes_recentes', ['dias' => 7]],
                'compras_recentes' => ['consultar_compras_recentes', ['limite' => 5]],
                'unidades' => ['consultar_resumo_unidades', []],
            ];

            $data = [];
            foreach ($mapa as $chave => [$tool, $toolArgs]) {
                $r = $this->service->executarFerramenta($tool, $toolArgs, $userId, null);
                $data[$chave] = $r['ok'] ? $r['data'] : ['indisponivel' => true];
            }

            return $data;
        }, 'Visão gerencial resumida.');
    }

    public function relatorioUnidade(Request $request, $id): JsonResponse
    {
        return $this->executar($request, 'ayla.relatorios.unidade', function (?int $userId) use ($id) {
            if (! ctype_digit((string) $id) || (int) $id < 1) {
                return ['_erro' => ['Identificador de unidade inválido.', 'VALIDATION_ERROR', 422]];
            }
            $unidadeId = (int) $id;
            if (! AylaSettings::unidadePermitida($unidadeId)) {
                return ['_erro' => ['Unidade não autorizada.', 'UNIT_NOT_ALLOWED', 403]];
            }

            $r = $this->service->relatorioUnidade($unidadeId, $userId);
            if (! $r['ok']) {
                return ['_erro' => [$r['message'] ?? 'Não foi possível gerar o relatório.', $r['code'] ?? 'INTERNAL_ERROR', ($r['code'] ?? '') === 'NOT_FOUND' ? 404 : 500]];
            }

            return $r['data'];
        }, 'Relatório da unidade.');
    }

    /**
     * Kanban administrativo — somente leitura.
     * Filtros: status, prioridade, responsavel, unidade, setor, data, vencimento, texto, limit.
     */
    public function kanban(Request $request, AylaKanbanService $kanban): JsonResponse
    {
        return $this->executar($request, 'ayla.kanban', function (?int $userId) use ($request, $kanban) {
            $p = $this->parseKanbanArgs($request);
            if ($p['erro']) {
                return $p['erro'];
            }

            $args = $p['args'];
            $unidadeId = $args['unidade_id'] ?? null;
            if ($unidadeId === null && ! empty($args['unidade'])) {
                $unidadeId = $kanban->resolverUnidadeIdPorNome((string) $args['unidade']);
                if ($unidadeId) {
                    $args['unidade_id'] = $unidadeId;
                }
            }
            if ($unidadeId !== null && ! AylaSettings::unidadePermitida($unidadeId)) {
                return ['_erro' => ['Unidade não autorizada.', 'UNIT_NOT_ALLOWED', 403]];
            }

            if (! empty($args['unidade']) && $unidadeId === null) {
                return ['_erro' => ['Unidade não encontrada.', 'NOT_FOUND', 404]];
            }

            return $kanban->consultar($args, $userId);
        }, 'Tarefas do kanban consultadas.');
    }

    /**
     * Patrimônio — lista de bens (somente leitura).
     * Filtros: busca, patrimonio_id, unidade_id, categoria, status, responsavel,
     * setor, data_inicio, data_fim, valor_minimo, valor_maximo, limite.
     */
    public function patrimonio(Request $request, AylaPatrimonioService $patrimonio): JsonResponse
    {
        return $this->executar($request, 'ayla.patrimonio', function (?int $userId) use ($request, $patrimonio) {
            $p = $this->parsePatrimonioArgs($request, $patrimonio);
            if ($p['erro']) {
                return $p['erro'];
            }

            $r = $this->service->executarFerramenta('patrimonio_consultar', $p['args'], $userId, $p['args']['unidade_id'] ?? null);

            return $this->normalizar($r, $p['args']['limite'] ?? 50);
        }, 'Consulta patrimonial concluída.');
    }

    /** Patrimônio — resumo geral ou por unidade/categoria. */
    public function patrimonioResumo(Request $request, AylaPatrimonioService $patrimonio): JsonResponse
    {
        return $this->executar($request, 'ayla.patrimonio.resumo', function (?int $userId) use ($request, $patrimonio) {
            $p = $this->parsePatrimonioArgs($request, $patrimonio, ['unidade_id', 'categoria']);
            if ($p['erro']) {
                return $p['erro'];
            }

            $r = $this->service->executarFerramenta('patrimonio_resumo', $p['args'], $userId, $p['args']['unidade_id'] ?? null);

            return $this->normalizar($r, 50);
        }, 'Resumo patrimonial gerado.');
    }

    /** Patrimônio — detalhes de um bem. */
    public function patrimonioDetalhe(Request $request, $id): JsonResponse
    {
        return $this->executar($request, 'ayla.patrimonio.detalhe', function (?int $userId) use ($id) {
            if (! ctype_digit((string) $id) || (int) $id < 1) {
                return ['_erro' => ['Identificador de patrimônio inválido.', 'VALIDATION_ERROR', 422]];
            }

            $r = $this->service->executarFerramenta('patrimonio_detalhar', ['patrimonio_id' => (int) $id], $userId, null);

            return $this->normalizar($r, 50);
        }, 'Detalhes do bem patrimonial.');
    }

    /** Patrimônio — resumo e lista de bens de uma unidade. */
    public function patrimonioUnidade(Request $request, $id): JsonResponse
    {
        return $this->executar($request, 'ayla.patrimonio.unidade', function (?int $userId) use ($id) {
            if (! ctype_digit((string) $id) || (int) $id < 1) {
                return ['_erro' => ['Identificador de unidade inválido.', 'VALIDATION_ERROR', 422]];
            }
            $unidadeId = (int) $id;
            if (! AylaSettings::unidadePermitida($unidadeId)) {
                return ['_erro' => ['Unidade não autorizada.', 'UNIT_NOT_ALLOWED', 403]];
            }

            $r = $this->service->executarFerramenta('patrimonio_por_unidade', ['unidade_id' => $unidadeId], $userId, $unidadeId);

            return $this->normalizar($r, 50);
        }, 'Patrimônio da unidade.');
    }

    /** Patrimônio — alertas (garantia, manutenção, pendências). */
    public function patrimonioAlertas(Request $request, AylaPatrimonioService $patrimonio): JsonResponse
    {
        return $this->executar($request, 'ayla.patrimonio.alertas', function (?int $userId) use ($request, $patrimonio) {
            $p = $this->parsePatrimonioArgs($request, $patrimonio, ['unidade_id']);
            if ($p['erro']) {
                return $p['erro'];
            }

            $r = $this->service->executarFerramenta('patrimonio_alertas', $p['args'], $userId, $p['args']['unidade_id'] ?? null);

            return $this->normalizar($r, 50);
        }, 'Alertas patrimoniais gerados.');
    }

    /**
     * Reservas de mesas — lista (somente leitura).
     */
    public function reservas(Request $request, AylaReservasService $reservas): JsonResponse
    {
        return $this->executar($request, 'ayla.reservas', function (?int $userId) use ($request, $reservas) {
            $p = $this->parseReservasArgs($request, $reservas);
            if ($p['erro']) {
                return $p['erro'];
            }

            $r = $this->service->executarFerramenta('reservas_consultar', $p['args'], $userId, $p['args']['unidade_id'] ?? null);

            return $this->normalizar($r, $p['args']['limite'] ?? 50);
        }, 'Consulta de reservas concluída.');
    }

    public function reservasResumo(Request $request, AylaReservasService $reservas): JsonResponse
    {
        return $this->executar($request, 'ayla.reservas.resumo', function (?int $userId) use ($request, $reservas) {
            $p = $this->parseReservasArgs($request, $reservas, ['unidade_id', 'unidade']);
            if ($p['erro']) {
                return $p['erro'];
            }

            $r = $this->service->executarFerramenta('reservas_resumo', $p['args'], $userId, $p['args']['unidade_id'] ?? null);

            return $this->normalizar($r, 50);
        }, 'Resumo de reservas gerado.');
    }

    public function reservasDetalhe(Request $request, $id): JsonResponse
    {
        return $this->executar($request, 'ayla.reservas.detalhe', function (?int $userId) use ($id) {
            if (! ctype_digit((string) $id) || (int) $id < 1) {
                return ['_erro' => ['Identificador de reserva inválido.', 'VALIDATION_ERROR', 422]];
            }

            $r = $this->service->executarFerramenta('reservas_detalhar', ['reserva_id' => (int) $id], $userId, null);

            return $this->normalizar($r, 50);
        }, 'Detalhes da reserva.');
    }

    public function reservasUnidade(Request $request, $id): JsonResponse
    {
        return $this->executar($request, 'ayla.reservas.unidade', function (?int $userId) use ($id) {
            if (! ctype_digit((string) $id) || (int) $id < 1) {
                return ['_erro' => ['Identificador de unidade inválido.', 'VALIDATION_ERROR', 422]];
            }
            $unidadeId = (int) $id;
            if (! AylaSettings::unidadePermitida($unidadeId)) {
                return ['_erro' => ['Unidade não autorizada.', 'UNIT_NOT_ALLOWED', 403]];
            }

            $r = $this->service->executarFerramenta('reservas_por_unidade', ['unidade_id' => $unidadeId], $userId, $unidadeId);

            return $this->normalizar($r, 50);
        }, 'Reservas da unidade.');
    }

    public function reservasDisponibilidade(Request $request, AylaReservasService $reservas): JsonResponse
    {
        return $this->executar($request, 'ayla.reservas.disponibilidade', function (?int $userId) use ($request, $reservas) {
            $p = $this->parseDisponibilidadeArgs($request, $reservas);
            if ($p['erro']) {
                return $p['erro'];
            }

            $r = $this->service->executarFerramenta('reservas_disponibilidade', $p['args'], $userId, $p['args']['unidade_id'] ?? null);

            return $this->normalizar($r, 50);
        }, 'Disponibilidade de mesas consultada.');
    }

    public function reservasAlertas(Request $request, AylaReservasService $reservas): JsonResponse
    {
        return $this->executar($request, 'ayla.reservas.alertas', function (?int $userId) use ($request, $reservas) {
            $p = $this->parseReservasArgs($request, $reservas, ['unidade_id', 'unidade']);
            if ($p['erro']) {
                return $p['erro'];
            }

            $r = $this->service->executarFerramenta('reservas_alertas', $p['args'], $userId, $p['args']['unidade_id'] ?? null);

            return $this->normalizar($r, 50);
        }, 'Alertas de reservas gerados.');
    }

    /**
     * Prepara ação de escrita em reservas (não altera dados).
     * Fluxo: preparar → confirmação do usuário → confirmar.
     */
    public function reservasPrepararAcao(Request $request, AylaAcaoPendenteService $acoes): JsonResponse
    {
        return $this->executar($request, 'ayla.reservas.acao.preparar', function (?int $userId) use ($request, $acoes) {
            $telegramId = AylaWriteGuard::telegramDoRequest($request);
            $gate = AylaWriteGuard::autorizarEscrita($userId, $telegramId);
            if (! $gate['ok']) {
                return ['_erro' => [$gate['message'], $gate['code'], $gate['http']]];
            }

            $acao = strtolower(trim((string) $request->input('acao', '')));
            $dados = $request->input('dados', []);
            if (! is_array($dados)) {
                return ['_erro' => ['Campo dados deve ser um objeto.', 'VALIDATION_ERROR', 422]];
            }

            $r = $acoes->preparar(
                ['acao' => $acao, 'dados' => $dados],
                [
                    'usuario_id' => $userId,
                    'telegram_user_id' => $telegramId,
                    'canal' => $request->header('X-Ayla-Channel', 'api'),
                ]
            );

            return $this->normalizarEscrita($r);
        }, 'Ação preparada. Aguardando confirmação.');
    }

    /** Confirma e executa ação pendente (única via de escrita efetiva). */
    public function reservasConfirmarAcao(Request $request, $id, AylaAcaoPendenteService $acoes): JsonResponse
    {
        return $this->executar($request, 'ayla.reservas.acao.confirmar', function (?int $userId) use ($request, $id, $acoes) {
            if (! ctype_digit((string) $id) || (int) $id < 1) {
                return ['_erro' => ['Identificador de ação inválido.', 'VALIDATION_ERROR', 422]];
            }

            $telegramId = AylaWriteGuard::telegramDoRequest($request);
            $gate = AylaWriteGuard::autorizarEscrita($userId, $telegramId);
            if (! $gate['ok']) {
                return ['_erro' => [$gate['message'], $gate['code'], $gate['http']]];
            }

            $r = $acoes->confirmar((int) $id, [
                'usuario_id' => $userId,
                'telegram_user_id' => $telegramId,
            ]);

            return $this->normalizarEscrita($r);
        }, 'Ação confirmada e executada.');
    }

    /** Cancela ação pendente sem alterar reservas. */
    public function reservasCancelarAcao(Request $request, $id, AylaAcaoPendenteService $acoes): JsonResponse
    {
        return $this->executar($request, 'ayla.reservas.acao.cancelar', function (?int $userId) use ($request, $id, $acoes) {
            if (! ctype_digit((string) $id) || (int) $id < 1) {
                return ['_erro' => ['Identificador de ação inválido.', 'VALIDATION_ERROR', 422]];
            }

            $telegramId = AylaWriteGuard::telegramDoRequest($request);
            $gate = AylaWriteGuard::autorizarEscrita($userId, $telegramId);
            if (! $gate['ok']) {
                return ['_erro' => [$gate['message'], $gate['code'], $gate['http']]];
            }

            $r = $acoes->cancelar((int) $id, [
                'usuario_id' => $userId,
                'telegram_user_id' => $telegramId,
            ]);

            return $this->normalizarEscrita($r);
        }, 'Ação pendente cancelada.');
    }

    /**
     * Escrita direta bloqueada — exige fluxo preparar + confirmar.
     */
    public function reservasEscritaBloqueada(): JsonResponse
    {
        return AylaResponse::error(
            'ayla.reservas.escrita',
            'Escrita direta bloqueada. Use POST /reservas/acoes/preparar e depois /reservas/acoes/{id}/confirmar.',
            'CONFIRMATION_REQUIRED',
            403
        );
    }

    /**
     * Validação de acesso para o gateway (VPS). Recebe telegram_user_id e
     * retorna apenas as permissões necessárias. O Telegram ID nunca concede
     * acesso sozinho: precisa estar vinculado a um usuário SAS ativo.
     */
    public function validarAcesso(Request $request, AylaAccessService $access): JsonResponse
    {
        $inicio = microtime(true);
        $acao = 'ayla.acesso.validar';

        $telegramId = trim((string) $request->input('telegram_user_id', ''));
        $username = $request->input('telegram_username');
        if ($telegramId === '') {
            return $this->responder($request, $acao, false, 'Informe telegram_user_id.', 'VALIDATION_ERROR', 422, $inicio, null, []);
        }

        try {
            $res = $access->autorizarTelegram($telegramId, is_string($username) ? $username : null);
        } catch (\Throwable $e) {
            report($e);

            return $this->responder($request, $acao, false, 'Não foi possível validar o acesso.', 'INTERNAL_ERROR', 500, $inicio, null, []);
        }

        $autorizado = ($res['autorizado'] ?? false) === true;
        $mensagem = $autorizado ? 'Usuário autorizado.' : (string) ($res['motivo'] ?? 'Acesso não autorizado.');
        $duracao = (int) round((microtime(true) - $inicio) * 1000);

        AylaAuditLog::registrar([
            'user_id' => $res['usuario_id'] ?? null,
            'ip' => $request->ip(),
            'metodo' => $request->method(),
            'rota' => $request->path(),
            'acao' => $acao,
            'payload' => ['telegram_user_id' => $telegramId, 'telegram_username' => is_string($username) ? $username : null],
            'resposta_resumo' => ['autorizado' => $autorizado, 'motivo' => $res['motivo'] ?? null],
            'status' => $autorizado ? 'ok' : 'negado',
            'http_status' => 200,
            'duracao_ms' => $duracao,
        ]);

        // Sempre 200: o gateway lê data.autorizado. Nunca retorna dados sensíveis.
        return AylaResponse::success($acao, $mensagem, $res, ['duracao_ms' => $duracao]);
    }

    /**
     * Vincula Telegram User ID a partir de convite (/start TOKEN).
     * Autenticação: Bearer AYLA_BRIDGE_TOKEN (middleware ayla.bridge).
     */
    public function vincularTelegram(Request $request, AylaConviteService $convites): JsonResponse
    {
        $inicio = microtime(true);
        $acao = 'ayla.telegram.vincular';

        $token = trim((string) $request->input('convite_token', ''));
        if ($token === '') {
            return AylaResponse::error($acao, 'Informe convite_token.', 'VALIDATION_ERROR', 422);
        }

        $result = $convites->vincularPorToken($token, [
            'telegram_user_id' => (string) $request->input('telegram_user_id', ''),
            'telegram_username' => $request->input('telegram_username'),
            'telegram_nome' => $request->input('telegram_nome'),
        ]);

        $duracao = (int) round((microtime(true) - $inicio) * 1000);
        $ok = (bool) ($result['success'] ?? false);

        AylaAuditLog::registrar([
            'user_id' => $result['data']['usuario_id'] ?? null,
            'ip' => $request->ip(),
            'metodo' => $request->method(),
            'rota' => $request->path(),
            'acao' => $acao,
            'payload' => [
                'telegram_user_id' => $request->input('telegram_user_id'),
                'telegram_username' => $request->input('telegram_username'),
            ],
            'resposta_resumo' => ['success' => $ok, 'sync_ok' => $result['data']['sync_ok'] ?? null],
            'status' => $ok ? 'ok' : 'erro',
            'http_status' => $ok ? 200 : 422,
            'duracao_ms' => $duracao,
        ]);

        if (! $ok) {
            return AylaResponse::error($acao, $result['message'] ?? 'Falha ao vincular.', 'INVITE_ERROR', 422);
        }

        return AylaResponse::success($acao, $result['message'] ?? 'Vinculado.', $result['data'] ?? [], ['duracao_ms' => $duracao]);
    }

    // ---------------------------------------------------------------------
    // Helpers internos
    // ---------------------------------------------------------------------

    /**
     * Wrapper genérico para endpoints que mapeiam 1:1 uma ferramenta read-only.
     *
     * @param  string[]  $permitidos
     * @param  array<string, mixed>  $defaults
     */
    private function consultar(Request $request, string $acao, string $tool, array $permitidos, string $mensagem, array $defaults = []): JsonResponse
    {
        return $this->executar($request, $acao, function (?int $userId) use ($request, $tool, $permitidos, $defaults) {
            $p = $this->parseArgs($request, $permitidos);
            if ($p['erro']) {
                return $p['erro'];
            }
            $args = array_merge($defaults, $p['args']);
            $unidadeId = $args['unidade_id'] ?? null;
            if (! AylaSettings::unidadePermitida($unidadeId)) {
                return ['_erro' => ['Unidade não autorizada.', 'UNIT_NOT_ALLOWED', 403]];
            }

            $limite = $args['limite'] ?? 50;
            $r = $this->service->executarFerramenta($tool, $args, $userId, $unidadeId);

            return $this->normalizar($r, $limite);
        }, $mensagem);
    }

    /**
     * Executa o corpo do endpoint com identificação de usuário, auditoria,
     * tratamento de erro padronizado e medição de duração.
     */
    private function executar(Request $request, string $acao, callable $fn, string $mensagemOk): JsonResponse
    {
        $inicio = microtime(true);

        $userId = null;
        $uidHeader = $request->header('X-Usuario-Id');
        if ($uidHeader !== null && $uidHeader !== '') {
            if (! ctype_digit((string) $uidHeader)) {
                return $this->responder($request, $acao, false, 'Usuário inválido.', 'INVALID_USER', 401, $inicio, null, []);
            }
            $usuario = DB::table('usuarios')->where('id', (int) $uidHeader)->where('ativo', 1)->first();
            if (! $usuario) {
                return $this->responder($request, $acao, false, 'Usuário inválido ou inativo.', 'INVALID_USER', 401, $inicio, null, []);
            }
            $userId = (int) $usuario->id;
        }

        try {
            $resultado = $fn($userId);
        } catch (\Throwable $e) {
            report($e);

            return $this->responder($request, $acao, false, 'Não foi possível processar a solicitação.', 'INTERNAL_ERROR', 500, $inicio, $userId, []);
        }

        if (is_array($resultado) && isset($resultado['_erro'])) {
            [$msg, $code, $http] = $resultado['_erro'];

            return $this->responder($request, $acao, false, $msg, $code, $http, $inicio, $userId, []);
        }

        $data = is_array($resultado) ? $resultado : [];

        return $this->responder($request, $acao, true, $mensagemOk, null, 200, $inicio, $userId, $data);
    }

    /**
     * Normaliza retorno do serviço em data ou tupla de erro (_erro).
     *
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>
     */
    private function normalizar(array $r, int $limite, ?string $listaKey = null): array
    {
        if (! ($r['ok'] ?? false)) {
            $http = match ($r['code'] ?? '') {
                'PERMISSION_DENIED' => 403,
                'TOOL_NOT_ALLOWED' => 403,
                'NOT_FOUND' => 404,
                'VALIDATION_ERROR' => 422,
                default => 500,
            };

            return ['_erro' => [$r['message'] ?? 'Consulta indisponível.', $r['code'] ?? 'INTERNAL_ERROR', $http]];
        }

        $data = $r['data'] ?? [];

        // Aplica o limite máximo do cliente sobre a lista principal, se houver.
        if ($listaKey !== null && isset($data[$listaKey]) && is_array($data[$listaKey])) {
            $data[$listaKey] = array_slice($data[$listaKey], 0, $limite);
            if (isset($data['total'])) {
                $data['total'] = count($data[$listaKey]);
            }
        }

        return $data;
    }

    /**
     * Normaliza retorno de serviços de escrita (ok/code/message/data).
     *
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>
     */
    private function normalizarEscrita(array $r): array
    {
        if ($r['ok'] ?? false) {
            return $r['data'] ?? [];
        }

        $http = match ($r['code'] ?? '') {
            'PERMISSION_DENIED', 'WRITE_NOT_ALLOWED', 'READ_ONLY', 'UNIT_NOT_ALLOWED', 'CONFIRMATION_REQUIRED' => 403,
            'NOT_FOUND' => 404,
            'VALIDATION_ERROR', 'CONFLICT', 'NO_AVAILABILITY', 'EXPIRED', 'CANCELLED', 'ALREADY_EXECUTED', 'INVALID_STATE' => 422,
            'MIGRATION_REQUIRED' => 503,
            'INVALID_USER' => 401,
            default => 422,
        };

        return ['_erro' => [$r['message'] ?? 'Falha na ação.', $r['code'] ?? 'EXECUTION_ERROR', $http]];
    }

    /**
     * Lê e valida parâmetros da query string.
     *
     * @param  string[]  $permitidos
     * @return array{args: array<string, mixed>, erro: null|array{_erro: array{0:string,1:string,2:int}}}
     */
    private function parseArgs(Request $request, array $permitidos): array
    {
        $args = [];

        if (in_array('limite', $permitidos, true)) {
            $v = $request->query('limite');
            if ($v !== null && $v !== '') {
                if (! ctype_digit((string) $v) || (int) $v < 1 || (int) $v > 50) {
                    return $this->erroParam('limite');
                }
                $args['limite'] = (int) $v;
            }
        }

        if (in_array('dias', $permitidos, true)) {
            $v = $request->query('dias');
            if ($v !== null && $v !== '') {
                if (! ctype_digit((string) $v) || (int) $v < 1 || (int) $v > 365) {
                    return $this->erroParam('dias');
                }
                $args['dias'] = (int) $v;
            }
        }

        if (in_array('unidade_id', $permitidos, true)) {
            $v = $request->query('unidade_id');
            if ($v !== null && $v !== '') {
                if (! ctype_digit((string) $v) || (int) $v < 1) {
                    return $this->erroParam('unidade_id');
                }
                $args['unidade_id'] = (int) $v;
            }
        }

        if (in_array('produto_id', $permitidos, true)) {
            $v = $request->query('produto_id');
            if ($v !== null && $v !== '') {
                if (! ctype_digit((string) $v) || (int) $v < 1) {
                    return $this->erroParam('produto_id');
                }
                $args['produto_id'] = (int) $v;
            }
        }

        if (in_array('busca', $permitidos, true)) {
            $v = $request->query('busca');
            if ($v !== null) {
                $v = trim((string) $v);
                if (mb_strlen($v) > 120) {
                    return $this->erroParam('busca');
                }
                if ($v !== '') {
                    $args['busca'] = $v;
                }
            }
        }

        if (in_array('status', $permitidos, true)) {
            $v = $request->query('status');
            if ($v !== null && $v !== '') {
                $v = strtolower(trim((string) $v));
                if (! in_array($v, self::STATUS_COMPRA, true)) {
                    return $this->erroParam('status');
                }
                $args['status'] = $v;
            }
        }

        if (in_array('ativo', $permitidos, true)) {
            $v = $request->query('ativo');
            if ($v !== null && $v !== '') {
                if (! in_array(strtolower((string) $v), ['0', '1', 'true', 'false'], true)) {
                    return $this->erroParam('ativo');
                }
                $args['ativo'] = in_array(strtolower((string) $v), ['1', 'true'], true) ? 1 : 0;
            }
        }

        return ['args' => $args, 'erro' => null];
    }

    /**
     * Lê e valida parâmetros de consulta do kanban.
     *
     * @return array{args: array<string, mixed>, erro: null|array{_erro: array{0:string,1:string,2:int}}}
     */
    private function parseKanbanArgs(Request $request): array
    {
        $args = [];

        $limite = $request->query('limit', $request->query('limite'));
        if ($limite !== null && $limite !== '') {
            if (! ctype_digit((string) $limite) || (int) $limite < 1 || (int) $limite > 50) {
                return $this->erroParam('limit');
            }
            $args['limit'] = (int) $limite;
        }

        $unidadeId = $request->query('unidade_id');
        if ($unidadeId !== null && $unidadeId !== '') {
            if (! ctype_digit((string) $unidadeId) || (int) $unidadeId < 1) {
                return $this->erroParam('unidade_id');
            }
            $args['unidade_id'] = (int) $unidadeId;
        }

        $unidade = $request->query('unidade');
        if ($unidade !== null) {
            $unidade = trim((string) $unidade);
            if (mb_strlen($unidade) > 120) {
                return $this->erroParam('unidade');
            }
            if ($unidade !== '') {
                $args['unidade'] = $unidade;
            }
        }

        $status = $request->query('status');
        if ($status !== null && $status !== '') {
            $statusNorm = mb_strtolower(trim((string) $status));
            $statusNorm = str_replace([' ', '-'], '_', $statusNorm);
            if (! in_array($statusNorm, self::STATUS_KANBAN, true)) {
                return $this->erroParam('status');
            }
            $args['status'] = $statusNorm;
        }

        $prioridade = $request->query('prioridade');
        if ($prioridade !== null && $prioridade !== '') {
            $prioNorm = mb_strtolower(trim((string) $prioridade));
            if (! in_array($prioNorm, self::PRIORIDADE_KANBAN, true)) {
                return $this->erroParam('prioridade');
            }
            $args['prioridade'] = $prioNorm;
        }

        $responsavel = $request->query('responsavel');
        if ($responsavel !== null) {
            $responsavel = trim((string) $responsavel);
            if (mb_strlen($responsavel) > 120) {
                return $this->erroParam('responsavel');
            }
            if ($responsavel !== '') {
                $args['responsavel'] = $responsavel;
            }
        }

        $setor = $request->query('setor');
        if ($setor !== null) {
            $setor = trim((string) $setor);
            if (mb_strlen($setor) > 80) {
                return $this->erroParam('setor');
            }
            if ($setor !== '') {
                $args['setor'] = $setor;
            }
        }

        $texto = $request->query('texto');
        if ($texto !== null) {
            $texto = trim((string) $texto);
            if (mb_strlen($texto) > 120) {
                return $this->erroParam('texto');
            }
            if ($texto !== '') {
                $args['texto'] = $texto;
            }
        }

        $data = $request->query('data');
        if ($data !== null && $data !== '') {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $data)) {
                return $this->erroParam('data');
            }
            $args['data'] = (string) $data;
        }

        $vencimento = $request->query('vencimento');
        if ($vencimento !== null && $vencimento !== '') {
            $vencNorm = mb_strtolower(trim((string) $vencimento));
            if (! in_array($vencNorm, self::VENCIMENTO_KANBAN, true)) {
                return $this->erroParam('vencimento');
            }
            $args['vencimento'] = $vencNorm;
        }

        return ['args' => $args, 'erro' => null];
    }

    /**
     * Lê e valida parâmetros de consulta do patrimônio.
     *
     * @param  string[]  $apenas  Restringe aos parâmetros informados (padrão: todos)
     * @return array{args: array<string, mixed>, erro: null|array{_erro: array{0:string,1:string,2:int}}}
     */
    private function parsePatrimonioArgs(Request $request, AylaPatrimonioService $patrimonio, array $apenas = []): array
    {
        $todos = $apenas === [];
        $permite = fn (string $p) => $todos || in_array($p, $apenas, true);
        $args = [];

        if ($permite('limite')) {
            $limite = $request->query('limite', $request->query('limit'));
            if ($limite !== null && $limite !== '') {
                if (! ctype_digit((string) $limite) || (int) $limite < 1 || (int) $limite > 50) {
                    return $this->erroParam('limite');
                }
                $args['limite'] = (int) $limite;
            }
        }

        if ($permite('patrimonio_id')) {
            $v = $request->query('patrimonio_id');
            if ($v !== null && $v !== '') {
                if (! ctype_digit((string) $v) || (int) $v < 1) {
                    return $this->erroParam('patrimonio_id');
                }
                $args['patrimonio_id'] = (int) $v;
            }
        }

        if ($permite('unidade_id')) {
            $v = $request->query('unidade_id');
            if ($v !== null && $v !== '') {
                if (! ctype_digit((string) $v) || (int) $v < 1) {
                    return $this->erroParam('unidade_id');
                }
                if (! AylaSettings::unidadePermitida((int) $v)) {
                    return ['args' => [], 'erro' => ['_erro' => ['Unidade não autorizada.', 'UNIT_NOT_ALLOWED', 403]]];
                }
                $args['unidade_id'] = (int) $v;
            }
        }

        if ($permite('unidade')) {
            $v = $request->query('unidade');
            if ($v !== null && trim((string) $v) !== '' && empty($args['unidade_id'])) {
                $v = trim((string) $v);
                if (mb_strlen($v) > 120) {
                    return $this->erroParam('unidade');
                }
                $uid = $patrimonio->resolverUnidadeIdPorNome($v);
                if ($uid === null) {
                    return ['args' => [], 'erro' => ['_erro' => ['Unidade não encontrada.', 'NOT_FOUND', 404]]];
                }
                if (! AylaSettings::unidadePermitida($uid)) {
                    return ['args' => [], 'erro' => ['_erro' => ['Unidade não autorizada.', 'UNIT_NOT_ALLOWED', 403]]];
                }
                $args['unidade_id'] = $uid;
            }
        }

        if ($permite('categoria')) {
            $v = $request->query('categoria');
            if ($v !== null && trim((string) $v) !== '') {
                $v = trim((string) $v);
                if (mb_strlen($v) > 120) {
                    return $this->erroParam('categoria');
                }
                $args['categoria'] = $v;
            }
        }

        if ($permite('setor')) {
            $v = $request->query('setor');
            if ($v !== null && trim((string) $v) !== '') {
                $v = trim((string) $v);
                if (mb_strlen($v) > 120) {
                    return $this->erroParam('setor');
                }
                $args['setor'] = $v;
            }
        }

        if ($permite('status')) {
            $v = $request->query('status');
            if ($v !== null && $v !== '') {
                $v = strtolower(trim((string) $v));
                if (! in_array($v, self::STATUS_PATRIMONIO, true)) {
                    return $this->erroParam('status');
                }
                $args['status'] = $v;
            }
        }

        if ($permite('responsavel')) {
            $v = $request->query('responsavel');
            if ($v !== null && trim((string) $v) !== '') {
                $v = trim((string) $v);
                if (mb_strlen($v) > 120) {
                    return $this->erroParam('responsavel');
                }
                $args['responsavel'] = $v;
            }
        }

        if ($permite('busca')) {
            $v = $request->query('busca');
            if ($v !== null && trim((string) $v) !== '') {
                $v = trim((string) $v);
                if (mb_strlen($v) > 120) {
                    return $this->erroParam('busca');
                }
                $args['busca'] = $v;
            }
        }

        foreach (['data_inicio', 'data_fim'] as $campoData) {
            if ($permite($campoData)) {
                $v = $request->query($campoData);
                if ($v !== null && $v !== '') {
                    if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v)) {
                        return $this->erroParam($campoData);
                    }
                    $args[$campoData] = (string) $v;
                }
            }
        }

        foreach (['valor_minimo', 'valor_maximo'] as $campoValor) {
            if ($permite($campoValor)) {
                $v = $request->query($campoValor);
                if ($v !== null && $v !== '') {
                    if (! is_numeric($v) || (float) $v < 0) {
                        return $this->erroParam($campoValor);
                    }
                    $args[$campoValor] = (float) $v;
                }
            }
        }

        return ['args' => $args, 'erro' => null];
    }

    /**
     * Valida filtros de reservas (query string).
     *
     * @param  string[]  $apenas  Se não vazio, só esses parâmetros são lidos.
     * @return array{args: array<string, mixed>, erro: null|array{_erro: array{0:string,1:string,2:int}}}
     */
    private function parseReservasArgs(Request $request, AylaReservasService $reservas, array $apenas = []): array
    {
        $todos = $apenas === [];
        $permite = fn (string $p) => $todos || in_array($p, $apenas, true);
        $args = [];

        if ($permite('limite')) {
            $limite = $request->query('limite', $request->query('limit'));
            if ($limite !== null && $limite !== '') {
                if (! ctype_digit((string) $limite) || (int) $limite < 1 || (int) $limite > 50) {
                    return $this->erroParam('limite');
                }
                $args['limite'] = (int) $limite;
            }
        }

        if ($permite('reserva_id')) {
            $v = $request->query('reserva_id');
            if ($v !== null && $v !== '') {
                if (! ctype_digit((string) $v) || (int) $v < 1) {
                    return $this->erroParam('reserva_id');
                }
                $args['reserva_id'] = (int) $v;
            }
        }

        if ($permite('mesa_id')) {
            $v = $request->query('mesa_id');
            if ($v !== null && $v !== '') {
                if (! ctype_digit((string) $v) || (int) $v < 1) {
                    return $this->erroParam('mesa_id');
                }
                $args['mesa_id'] = (int) $v;
            }
        }

        if ($permite('unidade_id')) {
            $v = $request->query('unidade_id');
            if ($v !== null && $v !== '') {
                if (! ctype_digit((string) $v) || (int) $v < 1) {
                    return $this->erroParam('unidade_id');
                }
                if (! AylaSettings::unidadePermitida((int) $v)) {
                    return ['args' => [], 'erro' => ['_erro' => ['Unidade não autorizada.', 'UNIT_NOT_ALLOWED', 403]]];
                }
                $args['unidade_id'] = (int) $v;
            }
        }

        if ($permite('unidade')) {
            $v = $request->query('unidade');
            if ($v !== null && trim((string) $v) !== '' && empty($args['unidade_id'])) {
                $v = trim((string) $v);
                if (mb_strlen($v) > 120) {
                    return $this->erroParam('unidade');
                }
                $uid = $reservas->resolverUnidadeIdPorNome($v);
                if ($uid === null) {
                    return ['args' => [], 'erro' => ['_erro' => ['Unidade não encontrada.', 'NOT_FOUND', 404]]];
                }
                if (! AylaSettings::unidadePermitida($uid)) {
                    return ['args' => [], 'erro' => ['_erro' => ['Unidade não autorizada.', 'UNIT_NOT_ALLOWED', 403]]];
                }
                $args['unidade_id'] = $uid;
            }
        }

        if ($permite('status')) {
            $v = $request->query('status');
            if ($v !== null && $v !== '') {
                $v = strtolower(trim((string) $v));
                if (! in_array($v, self::STATUS_RESERVA, true)) {
                    return $this->erroParam('status');
                }
                $args['status'] = $v;
            }
        }

        foreach (['data', 'data_inicio', 'data_fim'] as $campoData) {
            if ($permite($campoData)) {
                $v = $request->query($campoData);
                if ($v !== null && $v !== '') {
                    if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v)) {
                        return $this->erroParam($campoData);
                    }
                    $args[$campoData] = (string) $v;
                }
            }
        }

        if ($permite('data_inicio') && $permite('data_fim')
            && isset($args['data_inicio'], $args['data_fim'])
            && $args['data_inicio'] > $args['data_fim']) {
            return ['args' => [], 'erro' => ['_erro' => ['data_inicio não pode ser posterior a data_fim.', 'VALIDATION_ERROR', 422]]];
        }

        if ($permite('data') && isset($args['data']) && (isset($args['data_inicio']) || isset($args['data_fim']))) {
            return ['args' => [], 'erro' => ['_erro' => ['Não combine data com data_inicio/data_fim.', 'VALIDATION_ERROR', 422]]];
        }

        foreach (['cliente', 'busca'] as $campoTexto) {
            if ($permite($campoTexto)) {
                $v = $request->query($campoTexto);
                if ($v !== null && trim((string) $v) !== '') {
                    $v = trim((string) $v);
                    if (mb_strlen($v) > 120) {
                        return $this->erroParam($campoTexto);
                    }
                    $args[$campoTexto] = $v;
                }
            }
        }

        if ($permite('telefone')) {
            $v = $request->query('telefone');
            if ($v !== null && trim((string) $v) !== '') {
                $v = preg_replace('/\D+/', '', (string) $v) ?? '';
                if ($v === '' || strlen($v) > 20) {
                    return $this->erroParam('telefone');
                }
                $args['telefone'] = $v;
            }
        }

        foreach (['quantidade_minima', 'quantidade_maxima'] as $campoQtd) {
            if ($permite($campoQtd)) {
                $v = $request->query($campoQtd);
                if ($v !== null && $v !== '') {
                    if (! ctype_digit((string) $v) || (int) $v < 1) {
                        return $this->erroParam($campoQtd);
                    }
                    $args[$campoQtd] = (int) $v;
                }
            }
        }

        if (isset($args['quantidade_minima'], $args['quantidade_maxima'])
            && $args['quantidade_minima'] > $args['quantidade_maxima']) {
            return ['args' => [], 'erro' => ['_erro' => ['quantidade_minima não pode ser maior que quantidade_maxima.', 'VALIDATION_ERROR', 422]]];
        }

        foreach (['horario_inicio', 'horario_fim'] as $campoHora) {
            if ($permite($campoHora)) {
                $v = $request->query($campoHora);
                if ($v !== null && $v !== '') {
                    $norm = $this->normalizarHorario((string) $v);
                    if ($norm === null) {
                        return $this->erroParam($campoHora);
                    }
                    $args[$campoHora] = $norm;
                }
            }
        }

        if (isset($args['horario_inicio'], $args['horario_fim'])
            && $args['horario_inicio'] > $args['horario_fim']) {
            return ['args' => [], 'erro' => ['_erro' => ['horario_inicio não pode ser posterior a horario_fim.', 'VALIDATION_ERROR', 422]]];
        }

        return ['args' => $args, 'erro' => null];
    }

    /**
     * Valida parâmetros de disponibilidade (unidade_id, data, horario obrigatórios).
     *
     * @return array{args: array<string, mixed>, erro: null|array{_erro: array{0:string,1:string,2:int}}}
     */
    private function parseDisponibilidadeArgs(Request $request, AylaReservasService $reservas): array
    {
        $args = [];

        $unidadeId = $request->query('unidade_id');
        if ($unidadeId === null || $unidadeId === '') {
            $unidadeNome = $request->query('unidade');
            if ($unidadeNome !== null && trim((string) $unidadeNome) !== '') {
                $uid = $reservas->resolverUnidadeIdPorNome(trim((string) $unidadeNome));
                if ($uid === null) {
                    return ['args' => [], 'erro' => ['_erro' => ['Unidade não encontrada.', 'NOT_FOUND', 404]]];
                }
                $unidadeId = $uid;
            } else {
                return ['args' => [], 'erro' => ['_erro' => ['unidade_id é obrigatório.', 'VALIDATION_ERROR', 422]]];
            }
        }
        if (! ctype_digit((string) $unidadeId) || (int) $unidadeId < 1) {
            return $this->erroParam('unidade_id');
        }
        if (! AylaSettings::unidadePermitida((int) $unidadeId)) {
            return ['args' => [], 'erro' => ['_erro' => ['Unidade não autorizada.', 'UNIT_NOT_ALLOWED', 403]]];
        }
        $args['unidade_id'] = (int) $unidadeId;

        $data = $request->query('data');
        if ($data === null || $data === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $data)) {
            return ['args' => [], 'erro' => ['_erro' => ['data é obrigatória (YYYY-MM-DD).', 'VALIDATION_ERROR', 422]]];
        }
        $args['data'] = (string) $data;

        $horario = $request->query('horario');
        if ($horario === null || $horario === '') {
            return ['args' => [], 'erro' => ['_erro' => ['horario é obrigatório.', 'VALIDATION_ERROR', 422]]];
        }
        $norm = $this->normalizarHorario((string) $horario);
        if ($norm === null) {
            return $this->erroParam('horario');
        }
        $args['horario'] = $norm;

        $qtd = $request->query('quantidade_pessoas', $request->query('qtd_pessoas'));
        if ($qtd !== null && $qtd !== '') {
            if (! ctype_digit((string) $qtd) || (int) $qtd < 1) {
                return $this->erroParam('quantidade_pessoas');
            }
            $args['quantidade_pessoas'] = (int) $qtd;
        }

        $dur = $request->query('duracao_minutos');
        if ($dur !== null && $dur !== '') {
            if (! ctype_digit((string) $dur) || (int) $dur < 1 || (int) $dur > 480) {
                return $this->erroParam('duracao_minutos');
            }
            $args['duracao_minutos'] = (int) $dur;
        }

        return ['args' => $args, 'erro' => null];
    }

    /** Aceita HH:MM ou HH:MM:SS; retorna HH:MM:SS ou null. */
    private function normalizarHorario(string $hora): ?string
    {
        $hora = trim($hora);
        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $hora, $m)) {
            $h = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $i = $m[2];
            $s = isset($m[3]) ? $m[3] : '00';

            return "{$h}:{$i}:{$s}";
        }

        return null;
    }

    /** @return array{args: array<string, mixed>, erro: array{_erro: array{0:string,1:string,2:int}}} */
    private function erroParam(string $param): array
    {
        return ['args' => [], 'erro' => ['_erro' => ['Parâmetro inválido: '.$param.'.', 'VALIDATION_ERROR', 422]]];
    }

    /**
     * Monta a resposta padronizada e registra a auditoria (nunca quebra a request).
     *
     * @param  array<string, mixed>  $data
     */
    private function responder(Request $request, string $acao, bool $ok, string $mensagem, ?string $code, int $http, float $inicio, ?int $userId, array $data): JsonResponse
    {
        $duracao = (int) round((microtime(true) - $inicio) * 1000);

        AylaAuditLog::registrar([
            'user_id' => $userId,
            'ip' => $request->ip(),
            'metodo' => $request->method(),
            'rota' => $request->path(),
            'acao' => $acao,
            'payload' => $this->payloadAuditoria($request),
            'resposta_resumo' => [
                'ok' => $ok,
                'message' => $mensagem,
                'code' => $code,
                'contagem' => $this->contagem($data),
            ],
            'status' => $ok ? 'ok' : 'erro',
            'http_status' => $http,
            'duracao_ms' => $duracao,
        ]);

        $meta = ['duracao_ms' => $duracao];
        $canal = $request->header('X-Ayla-Channel');
        if ($canal) {
            $meta['channel'] = substr((string) $canal, 0, 40);
        }

        if ($ok) {
            return AylaResponse::success($acao, $mensagem, $data, $meta, $http);
        }

        return AylaResponse::error($acao, $mensagem, $code ?? 'ERROR', $http, $meta);
    }

    /** @return array<string, mixed> */
    private function payloadAuditoria(Request $request): array
    {
        $sender = $request->header('X-Ayla-Sender-Id');
        $canal = $request->header('X-Ayla-Channel');

        return array_filter([
            'query' => $request->query(),
            'channel' => $canal ? substr((string) $canal, 0, 40) : null,
            'sender_id' => $sender ? substr((string) $sender, 0, 80) : null,
        ], fn ($v) => $v !== null && $v !== []);
    }

    /**
     * Resumo de contagens (sem dados sensíveis) para auditoria.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, int>
     */
    private function contagem(array $data): array
    {
        $out = [];
        foreach ($data as $chave => $valor) {
            if (is_array($valor)) {
                $out[$chave] = count($valor);
            }
        }
        if (isset($data['total'])) {
            $out['total'] = (int) $data['total'];
        }
        if (isset($data['retornadas'])) {
            $out['retornadas'] = (int) $data['retornadas'];
        }

        return $out;
    }
}
