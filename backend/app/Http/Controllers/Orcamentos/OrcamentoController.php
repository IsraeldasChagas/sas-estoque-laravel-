<?php

namespace App\Http\Controllers\Orcamentos;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OrcamentoController extends Controller
{
    private const TIPOS = ['produto', 'servico', 'equipamento', 'evento', 'buffet', 'mesa', 'locacao', 'mao_obra', 'outro'];

    private const STATUS = ['rascunho', 'pendente', 'em_negociacao', 'aprovado', 'recusado', 'convertido'];

    private const LINHAS = ['produto_servico', 'equipe', 'equipamento', 'consumo'];

    private const FRETES = ['sem_frete', 'retirada', 'entrega', 'montagem', 'desmontagem'];

    public function catalogos(Request $request): JsonResponse
    {
        $this->autorizar($request, 'orcamentosNovo');

        return response()->json([
            'tipos' => self::TIPOS,
            'status' => self::STATUS,
            'tipos_linha' => self::LINHAS,
            'fretes' => self::FRETES,
            'formas_pagamento' => ['pix', 'dinheiro', 'cartao', 'parcelado'],
            'funcoes_equipe' => [
                'Atendente', 'Garçom', 'Recepcionista', 'Caixa', 'Cozinheiro', 'Auxiliar',
                'Supervisor', 'Segurança', 'Limpeza', 'Motorista', 'Montador', 'Desmontador', 'Outro',
            ],
            'equipamentos' => [
                'Mesas', 'Cadeiras', 'Toalhas', 'Pratos', 'Copos', 'Talheres',
                'Rechaud', 'Caixa térmica', 'Tendas', 'Freezer', 'Outro',
            ],
        ]);
    }

    public function clientes(Request $request): JsonResponse
    {
        $usuario = $this->autorizar($request, 'orcamentosClientes');
        $this->verificarTabelas();

        $query = DB::table('orcamento_clientes')->where('ativo', 1);
        $this->aplicarEscopo($query, $usuario, $request);

        if ($busca = trim((string) $request->query('busca', ''))) {
            $query->where(function ($q) use ($busca) {
                $like = '%'.$busca.'%';
                $q->where('nome', 'like', $like)
                    ->orWhere('telefone', 'like', $like)
                    ->orWhere('whatsapp', 'like', $like)
                    ->orWhere('documento', 'like', $like);
            });
        }

        return response()->json([
            'items' => $query->orderBy('nome')->limit(200)->get(),
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $usuario = $this->autorizar($request, 'orcamentosDashboard');
        $this->verificarTabelas();

        $query = DB::table('orcamentos');
        $this->aplicarEscopo($query, $usuario, $request);
        $rows = $query->orderByDesc('data_orcamento')->orderByDesc('id')->get();

        $contagens = array_fill_keys(self::STATUS, 0);
        $total = 0.0;
        $aprovados = 0;
        $serie = [];
        $clientes = [];

        foreach ($rows as $row) {
            $status = (string) $row->status;
            if (array_key_exists($status, $contagens)) {
                $contagens[$status]++;
            }
            $valor = (float) $row->total;
            $total += $valor;
            if (in_array($status, ['aprovado', 'convertido'], true)) {
                $aprovados++;
            }
            $mes = substr((string) $row->data_orcamento, 0, 7);
            $serie[$mes] = ($serie[$mes] ?? 0) + $valor;
            $nome = (string) $row->cliente_nome_snapshot;
            $clientes[$nome] = ($clientes[$nome] ?? 0) + $valor;
        }

        ksort($serie);
        arsort($clientes);

        return response()->json([
            'resumo' => [
                'total_orcamentos' => $rows->count(),
                'pendentes' => $contagens['pendente'],
                'aprovados' => $contagens['aprovado'],
                'recusados' => $contagens['recusado'],
                'em_negociacao' => $contagens['em_negociacao'],
                'convertidos' => $contagens['convertido'],
                'valor_total' => round($total, 2),
                'ticket_medio' => $rows->count() ? round($total / $rows->count(), 2) : 0,
                'conversao_percentual' => $rows->count() ? round(($aprovados / $rows->count()) * 100, 2) : 0,
            ],
            'evolucao' => collect(array_slice($serie, -7, 7, true))
                ->map(fn ($valor, $mes) => ['mes' => $mes, 'valor' => round($valor, 2)])
                ->values(),
            'ultimos' => $rows->take(5)->map(fn ($row) => $this->resumo($row))->values(),
            'top_clientes' => collect(array_slice($clientes, 0, 5, true))
                ->map(fn ($valor, $nome) => ['nome' => $nome, 'total' => round($valor, 2)])
                ->values(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $usuario = $this->autorizar($request, 'orcamentosLista');
        $this->verificarTabelas();

        $query = DB::table('orcamentos');
        $this->aplicarEscopo($query, $usuario, $request);

        if ($status = trim((string) $request->query('status', ''))) {
            $query->where('status', $status);
        }
        if ($tipo = trim((string) $request->query('tipo', ''))) {
            $query->where('tipo', $tipo);
        }
        if ($clienteId = (int) $request->query('cliente_id', 0)) {
            $query->where('cliente_id', $clienteId);
        }
        if ($inicio = trim((string) $request->query('data_inicio', ''))) {
            $query->whereDate('data_orcamento', '>=', $inicio);
        }
        if ($fim = trim((string) $request->query('data_fim', ''))) {
            $query->whereDate('data_orcamento', '<=', $fim);
        }
        if ($busca = trim((string) $request->query('busca', ''))) {
            $query->where(function ($q) use ($busca) {
                $like = '%'.$busca.'%';
                $q->where('codigo', 'like', $like)
                    ->orWhere('cliente_nome_snapshot', 'like', $like)
                    ->orWhere('responsavel_nome', 'like', $like);
            });
        }

        $limit = max(1, min(200, (int) $request->query('limit', 100)));
        $rows = $query->orderByDesc('data_orcamento')->orderByDesc('id')->limit($limit)->get();

        return response()->json(['items' => $rows->map(fn ($row) => $this->resumo($row))->values()]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $usuario = $this->autorizar($request, 'orcamentosLista');
        $this->verificarTabelas();
        $orcamento = DB::table('orcamentos')->where('id', $id)->first();
        abort_unless($orcamento, 404, 'Orçamento não encontrado.');
        $this->autorizarRegistro($usuario, $orcamento);

        return response()->json($this->completo($orcamento));
    }

    public function store(Request $request): JsonResponse
    {
        $usuario = $this->autorizar($request, 'orcamentosNovo');
        $this->verificarTabelas();
        $payload = $this->validar($request);

        $id = DB::transaction(function () use ($payload, $usuario) {
            $totais = $this->calcular($payload);
            $agora = now();
            $unidadeId = $this->unidadeDoPayload($payload, $usuario);
            $clienteId = $this->salvarCliente($payload['cliente'], $usuario, null, $unidadeId);

            $id = DB::table('orcamentos')->insertGetId(array_merge(
                $this->dadosCabecalho($payload, $totais, $usuario, $clienteId, $unidadeId),
                ['created_at' => $agora, 'updated_at' => $agora]
            ));
            $codigo = 'ORC-'.str_pad((string) ($unidadeId ?: 0), 3, '0', STR_PAD_LEFT).'-'.date('Y').'-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
            DB::table('orcamentos')->where('id', $id)->update(['codigo' => $codigo]);
            $this->salvarLinhas($id, $payload['linhas'] ?? [], $totais['linhas']);
            $this->historico($id, 'criado', ['etapa' => $payload['etapa_wizard'] ?? 1], (int) $usuario->id);

            return $id;
        });

        $orcamento = DB::table('orcamentos')->where('id', $id)->first();

        return response()->json($this->completo($orcamento), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $usuario = $this->autorizar($request, 'orcamentosNovo');
        $this->verificarTabelas();
        $payload = $this->validar($request);

        DB::transaction(function () use ($id, $payload, $usuario) {
            $existente = DB::table('orcamentos')->where('id', $id)->lockForUpdate()->first();
            abort_unless($existente, 404, 'Orçamento não encontrado.');
            $this->autorizarRegistro($usuario, $existente);

            $totais = $this->calcular($payload);
            $unidadeId = $this->unidadeDoPayload($payload, $usuario);
            $clienteId = $this->salvarCliente(
                $payload['cliente'],
                $usuario,
                $existente->cliente_id ? (int) $existente->cliente_id : null,
                $unidadeId
            );
            DB::table('orcamentos')->where('id', $id)->update(array_merge(
                $this->dadosCabecalho($payload, $totais, $usuario, $clienteId, $unidadeId),
                ['updated_at' => now()]
            ));
            DB::table('orcamento_linhas')->where('orcamento_id', $id)->delete();
            $this->salvarLinhas($id, $payload['linhas'] ?? [], $totais['linhas']);
            $this->historico($id, 'atualizado', ['etapa' => $payload['etapa_wizard'] ?? 1], (int) $usuario->id);
        });

        return $this->show($request, $id);
    }

    public function status(Request $request, int $id): JsonResponse
    {
        $usuario = $this->autorizar($request, 'orcamentosLista');
        $validator = Validator::make($request->all(), ['status' => 'required|in:'.implode(',', self::STATUS)]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Status inválido.', 'errors' => $validator->errors()], 422);
        }

        $orcamento = DB::table('orcamentos')->where('id', $id)->first();
        abort_unless($orcamento, 404, 'Orçamento não encontrado.');
        $this->autorizarRegistro($usuario, $orcamento);
        DB::table('orcamentos')->where('id', $id)->update(['status' => $request->input('status'), 'updated_at' => now()]);
        $this->historico($id, 'status_alterado', ['de' => $orcamento->status, 'para' => $request->input('status')], (int) $usuario->id);

        return $this->show($request, $id);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $usuario = $this->autorizar($request, 'orcamentosLista');
        $orcamento = DB::table('orcamentos')->where('id', $id)->first();
        abort_unless($orcamento, 404, 'Orçamento não encontrado.');
        $this->autorizarRegistro($usuario, $orcamento);

        DB::transaction(function () use ($id) {
            DB::table('orcamento_historico')->where('orcamento_id', $id)->delete();
            DB::table('orcamento_linhas')->where('orcamento_id', $id)->delete();
            DB::table('orcamentos')->where('id', $id)->delete();
        });

        return response()->json(['message' => 'Orçamento excluído.']);
    }

    private function validar(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'cliente' => 'required|array',
            'cliente.nome' => 'required|string|max:160',
            'cliente.email' => 'nullable|email|max:160',
            'cliente.documento' => 'nullable|string|max:30',
            'tipo' => 'required|in:'.implode(',', self::TIPOS),
            'status' => 'nullable|in:'.implode(',', self::STATUS),
            'data_orcamento' => 'required|date',
            'validade' => 'nullable|date',
            'frete.tipo' => 'nullable|in:'.implode(',', self::FRETES),
            'frete.valor' => 'nullable|numeric|min:0',
            'financeiro.desconto_percentual' => 'nullable|numeric|min:0|max:100',
            'financeiro.desconto_valor' => 'nullable|numeric|min:0',
            'financeiro.acrescimo_valor' => 'nullable|numeric|min:0',
            'linhas' => 'nullable|array',
            'linhas.*.tipo_linha' => 'required|in:'.implode(',', self::LINHAS),
            'linhas.*.descricao' => 'required|string|max:220',
            'linhas.*.quantidade' => 'required|numeric|min:0.001',
            'linhas.*.valor_unitario' => 'nullable|numeric|min:0',
            'linhas.*.desconto_percentual' => 'nullable|numeric|min:0|max:100',
            'linhas.*.horas' => 'nullable|numeric|min:0',
            'linhas.*.dias' => 'nullable|numeric|min:0',
            'linhas.*.valor_evento' => 'nullable|numeric|min:0',
            'linhas.*.custo_unitario' => 'nullable|numeric|min:0',
            'etapa_wizard' => 'nullable|integer|min:1|max:8',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $request->all();
    }

    private function calcular(array $payload): array
    {
        $subtotais = ['produto_servico' => 0.0, 'equipe' => 0.0, 'equipamento' => 0.0, 'consumo' => 0.0];
        $custos = 0.0;
        $linhasCalculadas = [];

        foreach (($payload['linhas'] ?? []) as $linha) {
            $tipo = (string) $linha['tipo_linha'];
            $quantidade = max(0, (float) ($linha['quantidade'] ?? 0));
            $valor = max(0, (float) ($linha['valor_unitario'] ?? 0));
            $horas = max(0, (float) ($linha['horas'] ?? 0));
            $dias = max(1, (float) ($linha['dias'] ?? 1));
            $valorEvento = max(0, (float) ($linha['valor_evento'] ?? 0));
            $desconto = min(100, max(0, (float) ($linha['desconto_percentual'] ?? 0)));

            $subtotal = match ($tipo) {
                'equipe' => $quantidade * ($valorEvento > 0 ? $valorEvento : $horas * $valor),
                'equipamento' => $quantidade * $dias * $valor,
                default => $quantidade * $valor,
            };
            $subtotal = round($subtotal * (1 - ($desconto / 100)), 2);
            $subtotais[$tipo] += $subtotal;
            $custos += $quantidade * max(0, (float) ($linha['custo_unitario'] ?? 0));
            $linhasCalculadas[] = $subtotal;
        }

        $frete = max(0, (float) data_get($payload, 'frete.valor', 0));
        $base = array_sum($subtotais) + $frete;
        $descontoPercentual = min(100, max(0, (float) data_get($payload, 'financeiro.desconto_percentual', 0)));
        $desconto = round(($base * $descontoPercentual / 100) + max(0, (float) data_get($payload, 'financeiro.desconto_valor', 0)), 2);
        $acrescimo = max(0, (float) data_get($payload, 'financeiro.acrescimo_valor', 0));
        $total = round(max(0, $base - $desconto + $acrescimo), 2);
        $lucro = $custos > 0 ? round($total - $custos, 2) : null;

        return [
            'produto_servico' => round($subtotais['produto_servico'], 2),
            'equipe' => round($subtotais['equipe'], 2),
            'equipamento' => round($subtotais['equipamento'], 2),
            'consumo' => round($subtotais['consumo'], 2),
            'frete' => round($frete, 2),
            'desconto' => $desconto,
            'total' => $total,
            'lucro' => $lucro,
            'margem' => $lucro !== null && $total > 0 ? round(($lucro / $total) * 100, 2) : null,
            'linhas' => $linhasCalculadas,
        ];
    }

    private function dadosCabecalho(array $payload, array $totais, object $usuario, int $clienteId, ?int $unidadeId): array
    {
        return [
            'unidade_id' => $unidadeId,
            'cliente_id' => $clienteId,
            'cliente_nome_snapshot' => trim((string) data_get($payload, 'cliente.nome')),
            'responsavel_nome' => (string) ($usuario->nome ?? ''),
            'usuario_id' => (int) $usuario->id,
            'tipo' => $payload['tipo'],
            'status' => $payload['status'] ?? 'rascunho',
            'data_orcamento' => $payload['data_orcamento'],
            'validade' => $payload['validade'] ?? null,
            'frete_tipo' => data_get($payload, 'frete.tipo', 'sem_frete'),
            'frete_valor' => $totais['frete'],
            'frete_distancia_km' => data_get($payload, 'frete.distancia_km'),
            'frete_observacoes' => data_get($payload, 'frete.observacoes'),
            'desconto_percentual' => data_get($payload, 'financeiro.desconto_percentual', 0),
            'desconto_valor' => data_get($payload, 'financeiro.desconto_valor', 0),
            'acrescimo_valor' => data_get($payload, 'financeiro.acrescimo_valor', 0),
            'forma_pagamento' => data_get($payload, 'financeiro.forma_pagamento'),
            'financeiro_observacoes' => data_get($payload, 'financeiro.observacoes'),
            'subtotal_produtos' => $totais['produto_servico'],
            'subtotal_equipe' => $totais['equipe'],
            'subtotal_equipamentos' => $totais['equipamento'],
            'subtotal_consumo' => $totais['consumo'],
            'subtotal_frete' => $totais['frete'],
            'total_desconto' => $totais['desconto'],
            'total' => $totais['total'],
            'lucro_estimado' => $totais['lucro'],
            'margem_percentual' => $totais['margem'],
            'observacoes' => $payload['observacoes'] ?? null,
            'etapa_wizard' => $payload['etapa_wizard'] ?? 1,
        ];
    }

    private function salvarLinhas(int $orcamentoId, array $linhas, array $subtotais): void
    {
        $agora = now();
        foreach ($linhas as $ordem => $linha) {
            DB::table('orcamento_linhas')->insert([
                'orcamento_id' => $orcamentoId,
                'tipo_linha' => $linha['tipo_linha'],
                'descricao' => trim((string) $linha['descricao']),
                'quantidade' => $linha['quantidade'] ?? 1,
                'unidade_medida' => $linha['unidade_medida'] ?? null,
                'horas' => $linha['horas'] ?? null,
                'dias' => $linha['dias'] ?? null,
                'valor_unitario' => $linha['valor_unitario'] ?? 0,
                'desconto_percentual' => $linha['desconto_percentual'] ?? 0,
                'valor_evento' => $linha['valor_evento'] ?? null,
                'custo_unitario' => $linha['custo_unitario'] ?? null,
                'subtotal' => $subtotais[$ordem] ?? 0,
                'ordem' => $ordem,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);
        }
    }

    private function salvarCliente(array $cliente, object $usuario, ?int $clienteId, ?int $unidadeId): int
    {
        $dados = [
            'unidade_id' => $unidadeId,
            'nome' => trim((string) $cliente['nome']),
            'telefone' => $cliente['telefone'] ?? null,
            'whatsapp' => $cliente['whatsapp'] ?? null,
            'instagram' => $cliente['instagram'] ?? null,
            'email' => $cliente['email'] ?? null,
            'documento' => $cliente['documento'] ?? null,
            'empresa' => $cliente['empresa'] ?? null,
            'origem' => $cliente['origem'] ?? null,
            'observacoes' => $cliente['observacoes'] ?? null,
            'ativo' => 1,
            'usuario_id' => (int) $usuario->id,
            'updated_at' => now(),
        ];

        $idPayload = (int) ($cliente['id'] ?? 0);
        $id = $idPayload ?: ($clienteId ?: 0);
        if ($id && DB::table('orcamento_clientes')->where('id', $id)->exists()) {
            DB::table('orcamento_clientes')->where('id', $id)->update($dados);

            return $id;
        }

        $dados['created_at'] = now();

        return (int) DB::table('orcamento_clientes')->insertGetId($dados);
    }

    private function completo(object $orcamento): array
    {
        $cliente = $orcamento->cliente_id
            ? DB::table('orcamento_clientes')->where('id', $orcamento->cliente_id)->first()
            : null;
        $linhas = DB::table('orcamento_linhas')->where('orcamento_id', $orcamento->id)->orderBy('ordem')->get();

        return array_merge($this->resumo($orcamento), [
            'cliente' => $cliente,
            'linhas' => $linhas,
            'frete' => [
                'tipo' => $orcamento->frete_tipo,
                'valor' => (float) $orcamento->frete_valor,
                'distancia_km' => $orcamento->frete_distancia_km !== null ? (float) $orcamento->frete_distancia_km : null,
                'observacoes' => $orcamento->frete_observacoes,
            ],
            'financeiro' => [
                'desconto_percentual' => (float) $orcamento->desconto_percentual,
                'desconto_valor' => (float) $orcamento->desconto_valor,
                'acrescimo_valor' => (float) $orcamento->acrescimo_valor,
                'forma_pagamento' => $orcamento->forma_pagamento,
                'observacoes' => $orcamento->financeiro_observacoes,
            ],
            'validade' => $orcamento->validade,
            'observacoes' => $orcamento->observacoes,
            'etapa_wizard' => (int) $orcamento->etapa_wizard,
        ]);
    }

    private function resumo(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'codigo' => $row->codigo,
            'cliente_id' => $row->cliente_id ? (int) $row->cliente_id : null,
            'cliente_nome' => $row->cliente_nome_snapshot,
            'tipo' => $row->tipo,
            'status' => $row->status,
            'data_orcamento' => $row->data_orcamento,
            'responsavel_nome' => $row->responsavel_nome,
            'total' => (float) $row->total,
            'subtotal_produtos' => (float) $row->subtotal_produtos,
            'subtotal_equipe' => (float) $row->subtotal_equipe,
            'subtotal_equipamentos' => (float) $row->subtotal_equipamentos,
            'subtotal_consumo' => (float) $row->subtotal_consumo,
            'subtotal_frete' => (float) $row->subtotal_frete,
            'total_desconto' => (float) $row->total_desconto,
            'lucro_estimado' => $row->lucro_estimado !== null ? (float) $row->lucro_estimado : null,
            'margem_percentual' => $row->margem_percentual !== null ? (float) $row->margem_percentual : null,
        ];
    }

    private function historico(int $id, string $acao, array $detalhes, int $usuarioId): void
    {
        DB::table('orcamento_historico')->insert([
            'orcamento_id' => $id,
            'acao' => $acao,
            'detalhes' => json_encode($detalhes, JSON_UNESCAPED_UNICODE),
            'usuario_id' => $usuarioId,
            'created_at' => now(),
        ]);
    }

    private function autorizar(Request $request, string $modulo): object
    {
        $usuario = DB::table('usuarios')->where('id', $request->header('X-Usuario-Id'))->where('ativo', 1)->first();
        abort_unless($usuario, 401, 'Usuário não identificado.');
        $perfil = strtoupper(trim((string) ($usuario->perfil ?? '')));
        if (in_array($perfil, ['ADMIN', 'GERENTE', 'ASSISTENTE_ADMINISTRATIVO'], true)) {
            return $usuario;
        }
        $permissoes = $usuario->permissoes_menu ?? null;
        if (is_string($permissoes)) {
            $permissoes = json_decode($permissoes, true);
        }
        abort_unless(is_array($permissoes) && in_array($modulo, $permissoes, true), 403, 'Sem permissão para este módulo.');

        return $usuario;
    }

    private function aplicarEscopo($query, object $usuario, Request $request): void
    {
        $perfil = strtoupper(trim((string) ($usuario->perfil ?? '')));
        if ($perfil === 'ADMIN' && $request->filled('unidade_id')) {
            $query->where('unidade_id', (int) $request->query('unidade_id'));
        } elseif ($perfil !== 'ADMIN' && ! empty($usuario->unidade_id)) {
            $query->where('unidade_id', (int) $usuario->unidade_id);
        }
    }

    private function autorizarRegistro(object $usuario, object $orcamento): void
    {
        $perfil = strtoupper(trim((string) ($usuario->perfil ?? '')));
        if ($perfil !== 'ADMIN' && ! empty($usuario->unidade_id) && (int) $orcamento->unidade_id !== (int) $usuario->unidade_id) {
            abort(403, 'Sem permissão para este orçamento.');
        }
    }

    private function unidadeDoPayload(array $payload, object $usuario): ?int
    {
        $perfil = strtoupper(trim((string) ($usuario->perfil ?? '')));
        if ($perfil === 'ADMIN' && ! empty($payload['unidade_id'])) {
            return (int) $payload['unidade_id'];
        }

        return ! empty($usuario->unidade_id) ? (int) $usuario->unidade_id : null;
    }

    private function verificarTabelas(): void
    {
        abort_unless(
            Schema::hasTable('orcamentos') && Schema::hasTable('orcamento_clientes') && Schema::hasTable('orcamento_linhas'),
            503,
            'Módulo Orçamentos indisponível. Execute as migrations.'
        );
    }
}
