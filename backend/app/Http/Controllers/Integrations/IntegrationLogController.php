<?php

namespace App\Http\Controllers\Integrations;

use App\Models\IntegrationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class IntegrationLogController extends IntegrationBaseController
{
    public function index(Request $request)
    {
        $u = $this->authUsuario($request);
        if (! $this->podeVisualizar($u)) {
            return $this->json(['error' => 'Não autorizado.'], 403);
        }

        if (! Schema::hasTable('integration_logs')) {
            return $this->json(['logs' => [], 'total' => 0, 'fase' => 2]);
        }

        $limit = min(200, max(1, (int) $request->query('limit', 50)));
        $provider = $request->query('provider');
        $status = $request->query('status');
        $operation = $request->query('operation');
        $usuarioId = $request->query('usuario_id');
        $unidadeId = $request->query('unidade_id');
        $dataInicio = $request->query('data_inicio');
        $dataFim = $request->query('data_fim');
        $sucesso = $request->query('sucesso');

        $q = IntegrationLog::query()->orderByDesc('created_at');

        if ($provider) {
            $q->where('provider', $provider);
        }
        if ($status) {
            $q->where('status', $status);
        }
        if ($operation) {
            $q->where('operation', $operation);
        }
        if ($usuarioId) {
            $q->where('usuario_id', (int) $usuarioId);
        }
        if ($unidadeId) {
            $q->where('unidade_id', (int) $unidadeId);
        }
        if ($sucesso === '1' || $sucesso === 'true') {
            $q->where('status', 'success');
        } elseif ($sucesso === '0' || $sucesso === 'false') {
            $q->where('status', 'error');
        }
        if ($dataInicio) {
            $q->where('created_at', '>=', $dataInicio);
        }
        if ($dataFim) {
            $q->where('created_at', '<=', $dataFim.' 23:59:59');
        }

        $rows = $q->limit($limit)->get();

        $logs = $rows->map(function (IntegrationLog $log) {
            return [
                'id' => $log->id,
                'data' => $log->created_at?->toIso8601String(),
                'sistema' => $log->provider,
                'operacao' => $log->operation,
                'endpoint' => $log->endpoint,
                'metodo' => $log->http_method,
                'tempo_ms' => $log->response_time_ms,
                'status' => $log->status,
                'http_status' => $log->http_status,
                'mensagem' => $log->message,
                'empresa_id' => $log->empresa_id,
                'unidade_id' => $log->unidade_id,
                'usuario_id' => $log->usuario_id,
                'ip' => $log->ip,
                'tentativa' => $log->attempt_number,
            ];
        });

        return $this->json([
            'logs' => $logs,
            'total' => $logs->count(),
            'fase' => 2,
        ]);
    }
}
