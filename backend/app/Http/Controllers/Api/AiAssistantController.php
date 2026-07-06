<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiAssistantService;
use App\Support\OpenClaw\OpenClawSettings;
use Illuminate\Http\Request;

class AiAssistantController extends Controller
{
    public function __construct(
        private AiAssistantService $service
    ) {}

    public function estoqueBaixo(Request $request)
    {
        return $this->executarConsulta($request, 'estoque_baixo', function () use ($request) {
            $unidadeId = $request->has('unidade_id') ? (int) $request->input('unidade_id') : null;
            $data = $this->service->estoqueBaixo($unidadeId);
            $total = (int) ($data['total'] ?? 0);
            $msg = $total === 0
                ? 'Nenhum produto abaixo do estoque mínimo.'
                : "{$total} produto(s) abaixo do estoque mínimo.";

            return [$msg, $data];
        });
    }

    public function produtosVencendo(Request $request)
    {
        return $this->executarConsulta($request, 'produtos_vencendo', function () use ($request) {
            $unidadeId = $request->has('unidade_id') ? (int) $request->input('unidade_id') : null;
            $dias = (int) $request->input('dias', 7);
            $data = $this->service->produtosVencendo($unidadeId, $dias);
            $total = (int) ($data['total'] ?? 0);
            $msg = $total === 0
                ? "Nenhum lote vencendo nos próximos {$dias} dias."
                : "{$total} lote(s) vencendo nos próximos {$dias} dias.";

            return [$msg, $data];
        });
    }

    public function produto(Request $request)
    {
        return $this->executarConsulta($request, 'produto', function () use ($request) {
            $data = $this->service->produto($request->all());
            if (isset($data['erro'])) {
                return [$data['erro'], $data, 'erro'];
            }
            $total = (int) ($data['total'] ?? 0);
            $msg = $total === 0
                ? ($data['mensagem'] ?? 'Nenhum produto encontrado.')
                : "{$total} produto(s) encontrado(s).";

            return [$msg, $data];
        });
    }

    public function relatorioUnidade(Request $request, $id)
    {
        return $this->executarConsulta($request, 'relatorio_unidade', function () use ($id) {
            $unidadeId = (int) $id;
            $erroUnidade = $this->service->validarUnidade($unidadeId);
            if ($erroUnidade) {
                return [$erroUnidade, [], 'erro'];
            }

            $data = $this->service->relatorioUnidade($unidadeId);
            if (isset($data['erro'])) {
                return [$data['erro'], $data, 'erro'];
            }

            $msg = "Relatório da unidade {$data['unidade_nome']}: {$data['produtos_com_estoque']} produtos, {$data['produtos_abaixo_minimo']} abaixo do mínimo, {$data['lotes_vencendo']} lotes vencendo.";

            return [$msg, $data];
        });
    }

    public function lancarPerda(Request $request)
    {
        return $this->executarAcao($request, 'lancar_perda', function () use ($request) {
            $unidadeId = (int) ($request->input('unidade_id') ?? $request->input('de_unidade_id') ?? 0);
            $erroUnidade = $this->service->validarUnidade($unidadeId > 0 ? $unidadeId : null);
            if ($erroUnidade) {
                return [$erroUnidade, [], 'erro'];
            }

            $result = $this->service->lancarPerda($request->all());
            $status = ($result['executado'] ?? false) ? 'ok' : 'pendente';

            return [
                $result['mensagem'],
                array_merge(
                    $result['preview'] ?? [],
                    $result['resultado'] ?? [],
                    ['executado' => $result['executado'] ?? false]
                ),
                $status,
            ];
        });
    }

    public function cadastrarCompra(Request $request)
    {
        return $this->executarAcao($request, 'cadastrar_compra', function () use ($request) {
            $unidadeId = $request->has('unidade_id') ? (int) $request->input('unidade_id') : null;
            $erroUnidade = $this->service->validarUnidade($unidadeId);
            if ($erroUnidade) {
                return [$erroUnidade, [], 'erro'];
            }

            $result = $this->service->cadastrarCompra($request->all());
            $status = ($result['executado'] ?? false) ? 'ok' : 'pendente';

            return [
                $result['mensagem'],
                array_merge(
                    $result['preview'] ?? [],
                    $result['resultado'] ?? [],
                    ['executado' => $result['executado'] ?? false]
                ),
                $status,
            ];
        });
    }

    /**
     * @param  callable(): array{0: string, 1: array<string, mixed>, 2?: string}  $fn
     */
    private function executarConsulta(Request $request, string $acao, callable $fn)
    {
        $erro = $this->service->validarAcao($acao);
        if ($erro) {
            return $this->responder($request, $acao, false, $erro, [], 'bloqueado', 403);
        }

        $unidadeId = $request->has('unidade_id') ? (int) $request->input('unidade_id') : null;
        $erroUnidade = $this->service->validarUnidade($unidadeId);
        if ($erroUnidade) {
            return $this->responder($request, $acao, false, $erroUnidade, [], 'erro', 403);
        }

        [$msg, $data, $status] = array_pad($fn(), 3, 'ok');

        return $this->responder(
            $request,
            $acao,
            $status !== 'erro',
            $msg,
            $data,
            $status === 'erro' ? 'erro' : 'ok',
            $status === 'erro' ? 422 : 200
        );
    }

    /**
     * @param  callable(): array{0: string, 1: array<string, mixed>, 2?: string}  $fn
     */
    private function executarAcao(Request $request, string $acao, callable $fn)
    {
        $erro = $this->service->validarAcao($acao);
        if ($erro) {
            return $this->responder($request, $acao, false, $erro, [], 'bloqueado', 403);
        }

        [$msg, $data, $status] = array_pad($fn(), 3, 'ok');

        return $this->responder(
            $request,
            $acao,
            $status !== 'erro',
            $msg,
            $data,
            $status,
            $status === 'erro' ? 422 : 200
        );
    }

    private function responder(
        Request $request,
        string $acao,
        bool $success,
        string $message,
        array $data,
        string $statusLog,
        int $httpCode = 200
    ) {
        $body = [
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ];

        $this->service->registrarLog(
            $acao,
            $request->method().' '.$request->path(),
            $request->except(['password', 'token']),
            $body,
            $statusLog
        );

        return response()->json($body, $httpCode)
            ->header('Access-Control-Allow-Origin', '*');
    }
}
