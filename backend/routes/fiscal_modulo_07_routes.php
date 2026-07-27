<?php

use App\Support\ApuracaoFiscalSupport;
use App\Support\FiscalConsolidacaoSupport;
use App\Support\PlanejamentoTributarioSupport;
use App\Support\RegraFiscalSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (! function_exists('fiscalM7Filters')) {
    function fiscalM7Filters(Request $request): array
    {
        return array_filter([
            'empresa_id' => $request->filled('empresa_id') ? (int) $request->empresa_id : null,
            'data_ini' => $request->input('data_ini'),
            'data_fim' => $request->input('data_fim'),
        ], fn ($v) => $v !== null && $v !== '');
    }
}

Route::get('/fiscal/modulo-07/meta', function () {
    return response()->json([
        'modulo_ativo' => RegraFiscalSupport::moduloAtivo(),
        'apuracao_ativa' => ApuracaoFiscalSupport::moduloAtivo(),
        'regra_versao' => RegraFiscalSupport::versaoAtual(),
        'disclaimer' => 'Estimativas gerenciais — validação por contador.',
    ]);
});

Route::get('/fiscal/regras', function (Request $request) {
    if (! RegraFiscalSupport::moduloAtivo()) {
        return response()->json([]);
    }
    $q = DB::table('regras_fiscais')->orderByDesc('id');
    if ($request->boolean('somente_ativas', true)) {
        $q->where('ativo', true);
    }

    return response()->json($q->limit(200)->get());
});

Route::get('/fiscal/consolidacao/visao-geral', function (Request $request) {
    return response()->json(FiscalConsolidacaoSupport::visaoGeral(fiscalM7Filters($request)));
});

Route::get('/fiscal/consolidacao/entradas', function (Request $request) {
    return response()->json(FiscalConsolidacaoSupport::listarEntradas(fiscalM7Filters($request)));
});

Route::get('/fiscal/consolidacao/saidas', function (Request $request) {
    return response()->json(FiscalConsolidacaoSupport::listarSaidas(fiscalM7Filters($request)));
});

Route::get('/fiscal/consolidacao/creditos', function (Request $request) {
    return response()->json(FiscalConsolidacaoSupport::listarCreditos(fiscalM7Filters($request)));
});

Route::get('/fiscal/consolidacao/estornos', function (Request $request) {
    return response()->json(FiscalConsolidacaoSupport::listarEstornos(fiscalM7Filters($request)));
});

Route::get('/fiscal/consolidacao/por-cnpj', function (Request $request) {
    return response()->json(FiscalConsolidacaoSupport::porCnpj(fiscalM7Filters($request)));
});

Route::get('/fiscal/consolidacao/estoque-potencial', function (Request $request) {
    return response()->json(FiscalConsolidacaoSupport::estoquePotencial(fiscalM7Filters($request)));
});

Route::get('/fiscal/consolidacao/tributos-recolher', function (Request $request) {
    $vg = FiscalConsolidacaoSupport::visaoGeral(fiscalM7Filters($request));
    $f = fiscalM7Filters($request);
    $itens = [];
    if (Schema::hasTable('tributos_venda')) {
        $q = DB::table('tributos_venda')
            ->select('tipo_tributo', DB::raw('SUM(valor) as total'))
            ->groupBy('tipo_tributo');
        if (! empty($f['empresa_id'])) {
            $q->where('empresa_id', (int) $f['empresa_id']);
        }
        $itens = $q->get()->map(fn ($r) => [
            'tributo' => $r->tipo_tributo,
            'valor_registrado' => (float) $r->total,
        ])->all();
    }

    return response()->json([
        'resumo' => $vg['cards'],
        'por_tributo' => $itens,
        'estimado_recolher' => $vg['cards']['tributos_estimados_recolher'] ?? 0,
    ]);
});

Route::get('/fiscal/apuracao', function (Request $request) {
    if (! ApuracaoFiscalSupport::moduloAtivo()) {
        return response()->json([]);
    }
    $q = DB::table('apuracoes_fiscais')->orderByDesc('id');
    if ($request->filled('empresa_id')) {
        $q->where('empresa_id', (int) $request->empresa_id);
    }

    return response()->json($q->limit(50)->get());
});

Route::get('/fiscal/apuracao/{id}', function (int $id) {
    if (! ApuracaoFiscalSupport::moduloAtivo()) {
        return response()->json(['error' => 'Módulo inativo'], 404);
    }
    $ap = DB::table('apuracoes_fiscais')->where('id', $id)->first();
    if (! $ap) {
        return response()->json(['error' => 'Não encontrada'], 404);
    }
    $itens = DB::table('apuracao_fiscal_itens')->where('apuracao_id', $id)->get();

    return response()->json(['apuracao' => $ap, 'itens' => $itens]);
});

Route::post('/fiscal/apuracao/calcular', function (Request $request) {
    $data = $request->validate([
        'empresa_id' => 'required|integer',
        'periodo_inicio' => 'required|date',
        'periodo_fim' => 'required|date|after_or_equal:periodo_inicio',
    ]);
    $res = ApuracaoFiscalSupport::calcular(
        (int) $data['empresa_id'],
        $data['periodo_inicio'],
        $data['periodo_fim']
    );
    if (! ($res['ok'] ?? false)) {
        return response()->json($res, 422);
    }

    return response()->json($res);
});

Route::patch('/fiscal/apuracao/{id}/validar', function (Request $request, int $id) {
    $uid = (int) $request->header('X-Usuario-Id', 0);
    $valores = $request->input('valores_validados');
    if (! ApuracaoFiscalSupport::validar($id, $uid ?: null, is_array($valores) ? $valores : null)) {
        return response()->json(['error' => 'Falha ao validar'], 422);
    }

    return response()->json(['ok' => true]);
});

Route::post('/fiscal/planejamento/simular', function (Request $request) {
    $data = $request->validate([
        'quantidade' => 'nullable|numeric|min:0.001',
        'preco_compra' => 'nullable|numeric|min:0',
        'preco_venda' => 'nullable|numeric|min:0',
        'custos_adicionais' => 'nullable|numeric|min:0',
        'empresa_c_id' => 'nullable|integer',
        'empresa_b_id' => 'nullable|integer',
        'empresa_compradora_id' => 'nullable|integer',
        'empresa_vendedora_id' => 'nullable|integer',
        'produto_id' => 'nullable|integer',
    ]);
    $result = PlanejamentoTributarioSupport::simularTresCenarios($data);

    return response()->json($result);
});

Route::get('/fiscal/planejamento/cenarios', function (Request $request) {
    if (! Schema::hasTable('cenarios_tributarios')) {
        return response()->json([]);
    }
    $q = DB::table('cenarios_tributarios')->orderByDesc('id');
    if ($request->filled('usuario_id')) {
        $q->where('usuario_id', (int) $request->usuario_id);
    }

    return response()->json($q->limit(30)->get());
});

Route::post('/fiscal/planejamento/cenarios', function (Request $request) {
    $data = $request->validate([
        'nome' => 'required|string|max:200',
        'premissas' => 'required|array',
        'resultado' => 'required|array',
        'produto_id' => 'nullable|integer',
    ]);
    $uid = (int) $request->header('X-Usuario-Id', 0);
    $id = PlanejamentoTributarioSupport::salvarCenario(
        $data['nome'],
        $uid ?: null,
        isset($data['produto_id']) ? (int) $data['produto_id'] : null,
        $data['premissas'],
        $data['resultado']
    );
    if (! $id) {
        return response()->json(['error' => 'Módulo não migrado'], 422);
    }

    return response()->json(['id' => $id]);
});
