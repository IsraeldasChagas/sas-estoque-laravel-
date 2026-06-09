<?php

/**
 * Rotas API — Financeiro Gerencial (SAS-Estoque / Grupo Sabor Paraense)
 * Incluído por routes/api.php
 */

use App\Support\Financeiro\FinanceiroGerencialCalculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

$fgCors = fn () => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');

$fgModulos = [
    'financeiroDashboard', 'financeiroFluxoCaixa', 'financeiroContasReceber',
    'financeiroDre', 'financeiroCmv', 'financeiroCentrosCusto',
    'financeiroOrcamento', 'financeiroIndicadores',
];

$fgAuth = function (Request $req) {
    $uid = $req->header('X-Usuario-Id');

    return $uid ? DB::table('usuarios')->where('id', $uid)->where('ativo', 1)->first() : null;
};

$fgPode = function ($u, ?string $modulo = null) use ($fgModulos) {
    if (! $u) {
        return false;
    }
    $p = strtoupper(trim((string) ($u->perfil ?? '')));
    if (in_array($p, ['ADMIN', 'GERENTE', 'ASSISTENTE_ADMINISTRATIVO', 'FINANCEIRO'], true)) {
        return true;
    }
    $pm = $u->permissoes_menu ?? null;
    if (is_string($pm)) {
        $decoded = json_decode($pm, true);
        $pm = is_array($decoded) ? $decoded : null;
    }
    if (is_array($pm) && count($pm)) {
        if ($modulo) {
            return in_array($modulo, $pm, true);
        }
        foreach ($fgModulos as $m) {
            if (in_array($m, $pm, true)) {
                return true;
            }
        }
        foreach (['boletao', 'proventos', 'fechamento', 'fechamentoDash'] as $base) {
            if (in_array($base, $pm, true)) {
                return true;
            }
        }

        return false;
    }

    return false;
};

$fgJson = fn ($data, int $code = 200) => response()->json($data, $code)
    ->header('Access-Control-Allow-Origin', '*');

$fgFiltros = function (Request $req): array {
    $de = $req->query('de') ?: $req->query('data_inicio');
    $ate = $req->query('ate') ?: $req->query('data_fim');
    if (! $de || ! $ate) {
        $pad = FinanceiroGerencialCalculo::periodoPadrao();
        $de = $de ?: $pad['de'];
        $ate = $ate ?: $pad['ate'];
    }
    $unidadeId = $req->query('unidade_id');
    $unidadeId = ($unidadeId !== null && $unidadeId !== '') ? (int) $unidadeId : null;

    return compact('de', 'ate', 'unidadeId');
};

$fgAudit = function ($usuarioId, string $acao, string $recurso, $recursoId = null, ?string $desc = null) {
    if (! Schema::hasTable('audit_logs') || ! $usuarioId) {
        return;
    }
    DB::table('audit_logs')->insert([
        'usuario_id' => $usuarioId,
        'acao' => $acao,
        'recurso' => $recurso,
        'recurso_id' => $recursoId,
        'descricao' => $desc,
        'created_at' => now(),
    ]);
};

$fgAtualizarStatusReceber = function () {
    if (! Schema::hasTable('financeiro_contas_receber')) {
        return;
    }
    $hoje = date('Y-m-d');
    DB::table('financeiro_contas_receber')
        ->whereNull('deleted_at')
        ->where('status', 'aberto')
        ->whereDate('data_vencimento', '<', $hoje)
        ->update(['status' => 'vencido', 'updated_at' => now()]);
};

$fgAtualizarStatusLancamento = function () {
    if (! Schema::hasTable('financeiro_lancamentos')) {
        return;
    }
    $hoje = date('Y-m-d');
    DB::table('financeiro_lancamentos')
        ->whereNull('deleted_at')
        ->where('status', 'previsto')
        ->whereNotNull('data_pagamento')
        ->whereDate('data_pagamento', '<', $hoje)
        ->update(['status' => 'atrasado', 'updated_at' => now()]);
};

// OPTIONS
foreach ([
    '/financeiro/dashboard', '/financeiro/fluxo-caixa', '/financeiro/contas-receber',
    '/financeiro/dre', '/financeiro/cmv', '/financeiro/centros-custo',
    '/financeiro/orcamento', '/financeiro/indicadores', '/financeiro/categorias',
    '/financeiro/clientes',
] as $path) {
    Route::options($path, $fgCors);
    Route::options($path.'/{id}', $fgCors);
}

// GET /financeiro/dashboard
Route::get('/financeiro/dashboard', function (Request $request) use ($fgAuth, $fgPode, $fgJson, $fgFiltros) {
    $u = $fgAuth($request);
    if (! $u || ! $fgPode($u, 'financeiroDashboard')) {
        return $fgJson(['error' => 'Não autorizado'], 401);
    }
    $f = $fgFiltros($request);
    $dados = FinanceiroGerencialCalculo::consolidarPeriodo($f['de'], $f['ate'], $f['unidadeId']);

    return $fgJson([
        'periodo' => ['de' => $f['de'], 'ate' => $f['ate']],
        'unidade_id' => $f['unidadeId'],
        ...$dados,
    ]);
});

// GET/POST /financeiro/fluxo-caixa
Route::get('/financeiro/fluxo-caixa', function (Request $request) use ($fgAuth, $fgPode, $fgJson, $fgFiltros, $fgAtualizarStatusLancamento) {
    $u = $fgAuth($request);
    if (! $u || ! $fgPode($u, 'financeiroFluxoCaixa')) {
        return $fgJson(['error' => 'Não autorizado'], 401);
    }
    if (! Schema::hasTable('financeiro_lancamentos')) {
        return $fgJson(['lancamentos' => [], 'relatorio' => FinanceiroGerencialCalculo::fluxoCaixaRelatorio(null, null, null)]);
    }
    $fgAtualizarStatusLancamento();
    $f = $fgFiltros($request);
    $q = DB::table('financeiro_lancamentos as l')
        ->leftJoin('financeiro_categorias as c', 'l.categoria_id', '=', 'c.id')
        ->leftJoin('financeiro_centros_custo as cc', 'l.centro_custo_id', '=', 'cc.id')
        ->leftJoin('unidades as u', 'l.unidade_id', '=', 'u.id')
        ->whereNull('l.deleted_at')
        ->select('l.*', 'c.nome as categoria_nome', 'cc.nome as centro_custo_nome', 'u.nome as unidade_nome');
    if ($f['de']) {
        $q->where(function ($sub) use ($f) {
            $sub->whereDate('l.data_pagamento', '>=', $f['de'])
                ->orWhere(function ($s) use ($f) {
                    $s->whereNull('l.data_pagamento')->whereDate('l.data_competencia', '>=', $f['de']);
                });
        });
    }
    if ($f['ate']) {
        $q->where(function ($sub) use ($f) {
            $sub->whereDate('l.data_pagamento', '<=', $f['ate'])
                ->orWhere(function ($s) use ($f) {
                    $s->whereNull('l.data_pagamento')->whereDate('l.data_competencia', '<=', $f['ate']);
                });
        });
    }
    if ($f['unidadeId']) {
        $q->where('l.unidade_id', $f['unidadeId']);
    }
    if ($tipo = $request->query('tipo')) {
        $q->where('l.tipo', $tipo);
    }
    if ($st = $request->query('status')) {
        $q->where('l.status', $st);
    }
    $lista = $q->orderByDesc('l.data_competencia')->orderByDesc('l.id')->get();
    $relatorio = FinanceiroGerencialCalculo::fluxoCaixaRelatorio($f['de'], $f['ate'], $f['unidadeId']);

    return $fgJson([
        'periodo' => ['de' => $f['de'], 'ate' => $f['ate']],
        'lancamentos' => $lista,
        'relatorio' => $relatorio,
    ]);
});

Route::post('/financeiro/fluxo-caixa', function (Request $request) use ($fgAuth, $fgPode, $fgJson, $fgAudit) {
    $u = $fgAuth($request);
    if (! $u || ! $fgPode($u, 'financeiroFluxoCaixa')) {
        return $fgJson(['error' => 'Não autorizado'], 401);
    }
    if (! Schema::hasTable('financeiro_lancamentos')) {
        return $fgJson(['error' => 'Módulo não configurado. Execute as migrations.'], 503);
    }
    $v = Validator::make($request->all(), [
        'tipo' => 'required|in:entrada,saida',
        'valor' => 'required|numeric|min:0.01',
        'data_competencia' => 'required|date',
        'status' => 'nullable|in:previsto,realizado,atrasado,cancelado',
        'unidade_id' => 'nullable|integer',
        'categoria_id' => 'nullable|integer',
        'centro_custo_id' => 'nullable|integer',
    ]);
    if ($v->fails()) {
        return $fgJson(['error' => $v->errors()->first()], 422);
    }
    $data = $v->validated();
    $id = DB::table('financeiro_lancamentos')->insertGetId([
        'unidade_id' => $data['unidade_id'] ?? null,
        'categoria_id' => $data['categoria_id'] ?? null,
        'centro_custo_id' => $data['centro_custo_id'] ?? null,
        'tipo' => $data['tipo'],
        'valor' => round((float) $data['valor'], 2),
        'descricao' => $request->input('descricao'),
        'forma_pagamento' => $request->input('forma_pagamento'),
        'data_competencia' => $data['data_competencia'],
        'data_pagamento' => $request->input('data_pagamento'),
        'status' => $data['status'] ?? 'previsto',
        'observacao' => $request->input('observacao'),
        'anexo_path' => $request->input('anexo_path'),
        'criado_por' => $u->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $fgAudit($u->id, 'criar', 'financeiro_lancamentos', $id, 'Lançamento fluxo de caixa');

    return $fgJson(DB::table('financeiro_lancamentos')->where('id', $id)->first(), 201);
});

Route::put('/financeiro/fluxo-caixa/{id}', function (Request $request, $id) use ($fgAuth, $fgPode, $fgJson, $fgAudit) {
    $u = $fgAuth($request);
    if (! $u || ! $fgPode($u, 'financeiroFluxoCaixa')) {
        return $fgJson(['error' => 'Não autorizado'], 401);
    }
    $row = DB::table('financeiro_lancamentos')->where('id', $id)->whereNull('deleted_at')->first();
    if (! $row) {
        return $fgJson(['error' => 'Não encontrado'], 404);
    }
    $upd = array_filter([
        'unidade_id' => $request->input('unidade_id'),
        'categoria_id' => $request->input('categoria_id'),
        'centro_custo_id' => $request->input('centro_custo_id'),
        'tipo' => $request->input('tipo'),
        'valor' => $request->has('valor') ? round((float) $request->input('valor'), 2) : null,
        'descricao' => $request->input('descricao'),
        'forma_pagamento' => $request->input('forma_pagamento'),
        'data_competencia' => $request->input('data_competencia'),
        'data_pagamento' => $request->input('data_pagamento'),
        'status' => $request->input('status'),
        'observacao' => $request->input('observacao'),
        'updated_at' => now(),
    ], fn ($v) => $v !== null);
    DB::table('financeiro_lancamentos')->where('id', $id)->update($upd);
    $fgAudit($u->id, 'atualizar', 'financeiro_lancamentos', $id, 'Atualização fluxo de caixa');

    return $fgJson(DB::table('financeiro_lancamentos')->where('id', $id)->first());
});

Route::delete('/financeiro/fluxo-caixa/{id}', function (Request $request, $id) use ($fgAuth, $fgPode, $fgJson, $fgAudit) {
    $u = $fgAuth($request);
    if (! $u || ! $fgPode($u, 'financeiroFluxoCaixa')) {
        return $fgJson(['error' => 'Não autorizado'], 401);
    }
    $row = DB::table('financeiro_lancamentos')->where('id', $id)->whereNull('deleted_at')->first();
    if (! $row) {
        return $fgJson(['error' => 'Não encontrado'], 404);
    }
    DB::table('financeiro_lancamentos')->where('id', $id)->update(['deleted_at' => now(), 'updated_at' => now()]);
    $fgAudit($u->id, 'excluir', 'financeiro_lancamentos', $id, 'Soft delete fluxo de caixa');

    return $fgJson(['ok' => true]);
});

// Clientes
Route::get('/financeiro/clientes', function (Request $request) use ($fgAuth, $fgPode, $fgJson) {
    $u = $fgAuth($request);
    if (! $u || ! $fgPode($u, 'financeiroContasReceber')) {
        return $fgJson(['error' => 'Não autorizado'], 401);
    }
    if (! Schema::hasTable('financeiro_clientes')) {
        return $fgJson([]);
    }
    $q = DB::table('financeiro_clientes')->where('ativo', true);
    if ($uid = $request->query('unidade_id')) {
        $q->where(function ($s) use ($uid) {
            $s->where('unidade_id', (int) $uid)->orWhereNull('unidade_id');
        });
    }

    return $fgJson($q->orderBy('nome')->get());
});

Route::post('/financeiro/clientes', function (Request $request) use ($fgAuth, $fgPode, $fgJson, $fgAudit) {
    $u = $fgAuth($request);
    if (! $u || ! $fgPode($u, 'financeiroContasReceber')) {
        return $fgJson(['error' => 'Não autorizado'], 401);
    }
    $v = Validator::make($request->all(), ['nome' => 'required|string|max:255']);
    if ($v->fails()) {
        return $fgJson(['error' => $v->errors()->first()], 422);
    }
    $id = DB::table('financeiro_clientes')->insertGetId([
        'nome' => $request->input('nome'),
        'documento' => $request->input('documento'),
        'email' => $request->input('email'),
        'telefone' => $request->input('telefone'),
        'unidade_id' => $request->input('unidade_id'),
        'observacoes' => $request->input('observacoes'),
        'ativo' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $fgAudit($u->id, 'criar', 'financeiro_clientes', $id, 'Cliente financeiro');

    return $fgJson(DB::table('financeiro_clientes')->where('id', $id)->first(), 201);
});

// GET/POST /financeiro/contas-receber
Route::get('/financeiro/contas-receber', function (Request $request) use ($fgAuth, $fgPode, $fgJson, $fgFiltros, $fgAtualizarStatusReceber) {
    $u = $fgAuth($request);
    if (! $u || ! $fgPode($u, 'financeiroContasReceber')) {
        return $fgJson(['error' => 'Não autorizado'], 401);
    }
    if (! Schema::hasTable('financeiro_contas_receber')) {
        return $fgJson(['contas' => [], 'inadimplencia' => ['quantidade' => 0, 'valor' => 0]]);
    }
    $fgAtualizarStatusReceber();
    $f = $fgFiltros($request);
    $q = DB::table('financeiro_contas_receber as cr')
        ->leftJoin('financeiro_clientes as cl', 'cr.cliente_id', '=', 'cl.id')
        ->leftJoin('unidades as u', 'cr.unidade_id', '=', 'u.id')
        ->whereNull('cr.deleted_at')
        ->select('cr.*', 'cl.nome as cliente_nome', 'u.nome as unidade_nome');
    if ($f['de']) {
        $q->whereDate('cr.data_vencimento', '>=', $f['de']);
    }
    if ($f['ate']) {
        $q->whereDate('cr.data_vencimento', '<=', $f['ate']);
    }
    if ($f['unidadeId']) {
        $q->where('cr.unidade_id', $f['unidadeId']);
    }
    if ($st = $request->query('status')) {
        $q->where('cr.status', $st);
    }
    $contas = $q->orderBy('cr.data_vencimento')->get();
    $inad = FinanceiroGerencialCalculo::contasReceberVencidas($f['unidadeId']);
    $previstos = $contas->where('status', 'aberto')->sum('valor');

    return $fgJson([
        'periodo' => ['de' => $f['de'], 'ate' => $f['ate']],
        'contas' => $contas,
        'inadimplencia' => $inad,
        'recebimentos_previstos' => round((float) $previstos, 2),
    ]);
});

Route::post('/financeiro/contas-receber', function (Request $request) use ($fgAuth, $fgPode, $fgJson, $fgAudit) {
    $u = $fgAuth($request);
    if (! $u || ! $fgPode($u, 'financeiroContasReceber')) {
        return $fgJson(['error' => 'Não autorizado'], 401);
    }
    $v = Validator::make($request->all(), [
        'valor' => 'required|numeric|min:0.01',
        'data_vencimento' => 'required|date',
        'total_parcelas' => 'nullable|integer|min:1|max:60',
    ]);
    if ($v->fails()) {
        return $fgJson(['error' => $v->errors()->first()], 422);
    }
    $totalParcelas = (int) ($request->input('total_parcelas') ?? 1);
    $valorTotal = round((float) $request->input('valor'), 2);
    $valorParcela = round($valorTotal / $totalParcelas, 2);
    $ids = [];
    $parentId = null;
    $baseDate = $request->input('data_vencimento');
    for ($i = 1; $i <= $totalParcelas; $i++) {
        $venc = date('Y-m-d', strtotime($baseDate.' +'.($i - 1).' month'));
        $id = DB::table('financeiro_contas_receber')->insertGetId([
            'cliente_id' => $request->input('cliente_id'),
            'unidade_id' => $request->input('unidade_id'),
            'parent_id' => $parentId,
            'descricao' => $request->input('descricao') ?: ('Parcela '.$i.'/'.$totalParcelas),
            'valor' => $valorParcela,
            'parcela_num' => $i,
            'total_parcelas' => $totalParcelas,
            'data_vencimento' => $venc,
            'forma_recebimento' => $request->input('forma_recebimento'),
            'status' => 'aberto',
            'observacao' => $request->input('observacao'),
            'criado_por' => $u->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($i === 1) {
            $parentId = $id;
        }
        $ids[] = $id;
    }
    $fgAudit($u->id, 'criar', 'financeiro_contas_receber', $parentId, "Conta a receber {$totalParcelas} parcela(s)");

    return $fgJson(['ids' => $ids, 'parent_id' => $parentId], 201);
});

Route::put('/financeiro/contas-receber/{id}', function (Request $request, $id) use ($fgAuth, $fgPode, $fgJson, $fgAudit) {
    $u = $fgAuth($request);
    if (! $u || ! $fgPode($u, 'financeiroContasReceber')) {
        return $fgJson(['error' => 'Não autorizado'], 401);
    }
    $row = DB::table('financeiro_contas_receber')->where('id', $id)->whereNull('deleted_at')->first();
    if (! $row) {
        return $fgJson(['error' => 'Não encontrado'], 404);
    }
    $upd = ['updated_at' => now()];
    foreach (['status', 'data_recebimento', 'forma_recebimento', 'observacao'] as $col) {
        if ($request->has($col)) {
            $upd[$col] = $request->input($col);
        }
    }
    if ($request->input('status') === 'recebido' && empty($upd['data_recebimento'])) {
        $upd['data_recebimento'] = date('Y-m-d');
    }
    DB::table('financeiro_contas_receber')->where('id', $id)->update($upd);
    $fgAudit($u->id, 'atualizar', 'financeiro_contas_receber', $id, 'Atualização conta a receber');

    return $fgJson(DB::table('financeiro_contas_receber')->where('id', $id)->first());
});

// GET /financeiro/dre
Route::get('/financeiro/dre', function (Request $request) use ($fgAuth, $fgPode, $fgJson, $fgFiltros) {
    $u = $fgAuth($request);
    if (! $u || ! $fgPode($u, 'financeiroDre')) {
        return $fgJson(['error' => 'Não autorizado'], 401);
    }
    $f = $fgFiltros($request);
    $catId = $request->query('categoria_id') ? (int) $request->query('categoria_id') : null;
    $dre = FinanceiroGerencialCalculo::dre($f['de'], $f['ate'], $f['unidadeId'], $catId);

    return $fgJson([
        'periodo' => ['de' => $f['de'], 'ate' => $f['ate']],
        'unidade_id' => $f['unidadeId'],
        'categoria_id' => $catId,
        'dre' => $dre,
    ]);
});

// GET /financeiro/cmv
Route::get('/financeiro/cmv', function (Request $request) use ($fgAuth, $fgPode, $fgJson, $fgFiltros) {
    $u = $fgAuth($request);
    if (! $u || ! $fgPode($u, 'financeiroCmv')) {
        return $fgJson(['error' => 'Não autorizado'], 401);
    }
    $f = $fgFiltros($request);
    $cmv = FinanceiroGerencialCalculo::cmvDetalhado($f['de'], $f['ate'], $f['unidadeId']);

    return $fgJson([
        'periodo' => ['de' => $f['de'], 'ate' => $f['ate']],
        'unidade_id' => $f['unidadeId'],
        ...$cmv,
    ]);
});

// GET/POST /financeiro/centros-custo
Route::get('/financeiro/centros-custo', function (Request $request) use ($fgAuth, $fgPode, $fgJson) {
    $u = $fgAuth($request);
    if (! $u || ! ($fgPode($u, 'financeiroCentrosCusto') || $fgPode($u, 'financeiroFluxoCaixa'))) {
        return $fgJson(['error' => 'Não autorizado'], 401);
    }
    if (! Schema::hasTable('financeiro_centros_custo')) {
        return $fgJson([]);
    }
    $centrosPadrao = ['Administrativo', 'Manutenção', 'Estoque', 'Outros'];
    $q = DB::table('financeiro_centros_custo');
    if ($request->query('ativos') !== '0') {
        $q->where('ativo', true);
    }
    if ($request->query('padrao') === '1') {
        $q->whereIn('nome', $centrosPadrao);
    }

    return $fgJson($q->orderBy('nome')->get());
});

Route::post('/financeiro/centros-custo', function (Request $request) use ($fgAuth, $fgPode, $fgJson, $fgAudit) {
    $u = $fgAuth($request);
    if (! $u || ! $fgPode($u, 'financeiroCentrosCusto')) {
        return $fgJson(['error' => 'Não autorizado'], 401);
    }
    $v = Validator::make($request->all(), ['nome' => 'required|string|max:120']);
    if ($v->fails()) {
        return $fgJson(['error' => $v->errors()->first()], 422);
    }
    $id = DB::table('financeiro_centros_custo')->insertGetId([
        'nome' => $request->input('nome'),
        'codigo' => $request->input('codigo'),
        'ativo' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $fgAudit($u->id, 'criar', 'financeiro_centros_custo', $id, 'Centro de custo');

    return $fgJson(DB::table('financeiro_centros_custo')->where('id', $id)->first(), 201);
});

Route::put('/financeiro/centros-custo/{id}', function (Request $request, $id) use ($fgAuth, $fgPode, $fgJson, $fgAudit) {
    $u = $fgAuth($request);
    if (! $u || ! $fgPode($u, 'financeiroCentrosCusto')) {
        return $fgJson(['error' => 'Não autorizado'], 401);
    }
    DB::table('financeiro_centros_custo')->where('id', $id)->update([
        'nome' => $request->input('nome'),
        'codigo' => $request->input('codigo'),
        'ativo' => $request->has('ativo') ? (bool) $request->input('ativo') : true,
        'updated_at' => now(),
    ]);
    $fgAudit($u->id, 'atualizar', 'financeiro_centros_custo', $id, 'Centro de custo');

    return $fgJson(DB::table('financeiro_centros_custo')->where('id', $id)->first());
});

// GET/POST /financeiro/orcamento
Route::get('/financeiro/orcamento', function (Request $request) use ($fgAuth, $fgPode, $fgJson) {
    $u = $fgAuth($request);
    if (! $u || ! $fgPode($u, 'financeiroOrcamento')) {
        return $fgJson(['error' => 'Não autorizado'], 401);
    }
    $competencia = $request->query('competencia') ?: FinanceiroGerencialCalculo::competenciaAtual();
    $unidadeId = $request->query('unidade_id') ? (int) $request->query('unidade_id') : null;
    $comparativo = FinanceiroGerencialCalculo::orcamentoComparativo($competencia, $unidadeId);
    $evolucao = [];
    if (Schema::hasTable('financeiro_orcamentos')) {
        $q = DB::table('financeiro_orcamentos')->where('competencia', 'like', substr($competencia, 0, 4).'%');
        if ($unidadeId) {
            $q->where('unidade_id', $unidadeId);
        }
        $evolucao = $q->orderBy('competencia')->get()->map(function ($r) use ($unidadeId) {
            $comp = FinanceiroGerencialCalculo::orcamentoComparativo($r->competencia, $unidadeId);

            return [
                'competencia' => $r->competencia,
                'meta_faturamento' => (float) $r->meta_faturamento,
                'realizado_faturamento' => $comp['realizado']['faturamento'],
            ];
        })->values()->all();
    }

    return $fgJson([
        'competencia' => $competencia,
        'comparativo' => $comparativo,
        'evolucao_mensal' => $evolucao,
        'orcamentos' => Schema::hasTable('financeiro_orcamentos')
            ? DB::table('financeiro_orcamentos')->orderByDesc('competencia')->limit(24)->get()
            : [],
    ]);
});

Route::post('/financeiro/orcamento', function (Request $request) use ($fgAuth, $fgPode, $fgJson, $fgAudit) {
    $u = $fgAuth($request);
    if (! $u || ! $fgPode($u, 'financeiroOrcamento')) {
        return $fgJson(['error' => 'Não autorizado'], 401);
    }
    $v = Validator::make($request->all(), [
        'competencia' => 'required|regex:/^\d{4}-\d{2}$/',
        'meta_faturamento' => 'nullable|numeric|min:0',
        'meta_despesa' => 'nullable|numeric|min:0',
        'meta_lucro' => 'nullable|numeric|min:0',
    ]);
    if ($v->fails()) {
        return $fgJson(['error' => $v->errors()->first()], 422);
    }
    $unidadeId = $request->input('unidade_id') ?: null;
    $comp = $request->input('competencia');
    $exists = DB::table('financeiro_orcamentos')
        ->where('competencia', $comp)
        ->where(function ($q) use ($unidadeId) {
            if ($unidadeId) {
                $q->where('unidade_id', $unidadeId);
            } else {
                $q->whereNull('unidade_id');
            }
        })->first();
    $payload = [
        'meta_faturamento' => round((float) $request->input('meta_faturamento', 0), 2),
        'meta_despesa' => round((float) $request->input('meta_despesa', 0), 2),
        'meta_lucro' => round((float) $request->input('meta_lucro', 0), 2),
        'observacoes' => $request->input('observacoes'),
        'updated_at' => now(),
    ];
    if ($exists) {
        DB::table('financeiro_orcamentos')->where('id', $exists->id)->update($payload);
        $id = $exists->id;
        $fgAudit($u->id, 'atualizar', 'financeiro_orcamentos', $id, 'Orçamento empresarial');
    } else {
        $id = DB::table('financeiro_orcamentos')->insertGetId(array_merge($payload, [
            'unidade_id' => $unidadeId,
            'competencia' => $comp,
            'criado_por' => $u->id,
            'created_at' => now(),
        ]));
        $fgAudit($u->id, 'criar', 'financeiro_orcamentos', $id, 'Orçamento empresarial');
    }

    return $fgJson(DB::table('financeiro_orcamentos')->where('id', $id)->first());
});

// GET /financeiro/indicadores
Route::get('/financeiro/indicadores', function (Request $request) use ($fgAuth, $fgPode, $fgJson, $fgFiltros) {
    $u = $fgAuth($request);
    if (! $u || ! $fgPode($u, 'financeiroIndicadores')) {
        return $fgJson(['error' => 'Não autorizado'], 401);
    }
    $f = $fgFiltros($request);
    $ind = FinanceiroGerencialCalculo::indicadores($f['de'], $f['ate'], $f['unidadeId']);

    return $fgJson([
        'periodo' => ['de' => $f['de'], 'ate' => $f['ate']],
        'unidade_id' => $f['unidadeId'],
        'indicadores' => $ind,
    ]);
});

// Categorias (auxiliar fluxo de caixa)
Route::get('/financeiro/categorias', function (Request $request) use ($fgAuth, $fgPode, $fgJson) {
    $u = $fgAuth($request);
    if (! $u || ! $fgPode($u)) {
        return $fgJson(['error' => 'Não autorizado'], 401);
    }
    if (! Schema::hasTable('financeiro_categorias')) {
        return $fgJson([]);
    }
    $q = DB::table('financeiro_categorias')->where('ativo', true);
    if ($tipo = $request->query('tipo')) {
        $q->where('tipo', $tipo);
    }

    return $fgJson($q->orderBy('ordem')->orderBy('nome')->get());
});
