<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AylaAuditLog;
use App\Services\AylaApiService;
use App\Support\Ayla\AylaResponse;
use App\Support\Ayla\AylaSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API Ayla v1 — somente leitura.
 * Todos os endpoints reutilizam ferramentas SAS IA já existentes.
 */
class AylaController extends Controller
{
    /** Status de compra aceitos em filtros (allow-list). */
    private const STATUS_COMPRA = ['aberta', 'pendente', 'aprovada', 'concluida', 'cancelada', 'rascunho'];

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

        return $out;
    }
}
