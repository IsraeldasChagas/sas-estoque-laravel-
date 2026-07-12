<?php

namespace App\Services;

use App\Support\SasIa\SasIaContext;
use Illuminate\Support\Facades\DB;

/**
 * Serviço da API Ayla (somente leitura).
 *
 * Reutiliza integralmente as ferramentas seguras do SAS IA (SasIaToolService /
 * SasIaModuleQueryService) e o AiAssistantService apenas para o relatório de
 * unidade (leitura). Não copia SQL nem duplica cálculos.
 *
 * O cliente nunca envia o nome da ferramenta: a controller mapeia cada endpoint
 * para uma ferramenta interna permitida desta allow-list.
 */
class AylaApiService
{
    /** Ferramentas read-only que a Ayla pode acionar. */
    private const TOOLS_PERMITIDAS = [
        'consultar_resumo_unidades',
        'consultar_produto_por_nome',
        'consultar_produtos_abaixo_estoque_minimo',
        'consultar_estoque_por_unidade',
        'consultar_movimentacoes_recentes',
        'consultar_lotes_proximos_vencer',
        'consultar_compras_recentes',
        'consultar_fornecedores',
        'consultar_resumo_produtos',
    ];

    public function __construct(
        private SasIaToolService $tools,
        private AiAssistantService $assistant
    ) {}

    /**
     * Executa uma ferramenta SAS IA read-only e normaliza o retorno.
     *
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, data?: array<string, mixed>, code?: string, message?: string, duracao_ms: int}
     */
    public function executarFerramenta(string $tool, array $args, ?int $userId, ?int $unidadeId = null): array
    {
        $inicio = microtime(true);

        if (! in_array($tool, self::TOOLS_PERMITIDAS, true)) {
            return ['ok' => false, 'code' => 'TOOL_NOT_ALLOWED', 'message' => 'Consulta não disponível.', 'duracao_ms' => $this->duracao($inicio)];
        }

        try {
            $ctx = $this->montarContexto($userId, $unidadeId);
            $resultado = $this->tools->executar($ctx, $tool, $args);
        } catch (\Throwable $e) {
            report($e);

            return ['ok' => false, 'code' => 'INTERNAL_ERROR', 'message' => 'Não foi possível processar a consulta.', 'duracao_ms' => $this->duracao($inicio)];
        }

        if (is_array($resultado) && ! empty($resultado['erro'])) {
            return [
                'ok' => false,
                'code' => 'PERMISSION_DENIED',
                'message' => (string) ($resultado['mensagem'] ?? 'Sem permissão para acessar esse dado.'),
                'duracao_ms' => $this->duracao($inicio),
            ];
        }

        return ['ok' => true, 'data' => is_array($resultado) ? $resultado : [], 'duracao_ms' => $this->duracao($inicio)];
    }

    /**
     * Relatório gerencial de uma unidade (read-only) reutilizando AiAssistantService.
     *
     * @return array{ok: bool, data?: array<string, mixed>, code?: string, message?: string, duracao_ms: int}
     */
    public function relatorioUnidade(int $unidadeId, ?int $userId): array
    {
        $inicio = microtime(true);

        try {
            $resultado = $this->assistant->relatorioUnidade($unidadeId);
        } catch (\Throwable $e) {
            report($e);

            return ['ok' => false, 'code' => 'INTERNAL_ERROR', 'message' => 'Não foi possível gerar o relatório.', 'duracao_ms' => $this->duracao($inicio)];
        }

        if (isset($resultado['erro'])) {
            return ['ok' => false, 'code' => 'NOT_FOUND', 'message' => (string) $resultado['erro'], 'duracao_ms' => $this->duracao($inicio)];
        }

        return ['ok' => true, 'data' => $resultado, 'duracao_ms' => $this->duracao($inicio)];
    }

    /**
     * Constrói o contexto SAS IA. Com usuário identificado aplica perfil,
     * permissoes_menu e escopo de unidade. Sem usuário, usa um leitor de
     * sistema (somente leitura) restrito pelas unidades permitidas na config.
     */
    private function montarContexto(?int $userId, ?int $unidadeId): SasIaContext
    {
        if ($userId) {
            $usuario = DB::table('usuarios')->where('id', $userId)->where('ativo', 1)->first();
            if ($usuario) {
                return new SasIaContext($usuario, $unidadeId);
            }
        }

        $sistema = (object) [
            'id' => 0,
            'nome' => 'Ayla',
            'perfil' => 'ADMIN',
            'permissoes_menu' => null,
            'unidade_id' => null,
        ];

        return new SasIaContext($sistema, $unidadeId);
    }

    private function duracao(float $inicio): int
    {
        return (int) round((microtime(true) - $inicio) * 1000);
    }
}
