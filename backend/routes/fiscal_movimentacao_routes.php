<?php

use App\Support\FiscalMovimentacaoSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Route::get('/fiscal/movimentacoes/meta', function () {
    return response()->json([
        'tipos_movimentacao' => FiscalMovimentacaoSupport::TIPOS_MOVIMENTACAO,
        'tipos_evento' => FiscalMovimentacaoSupport::TIPOS_EVENTO,
        'status_evento' => FiscalMovimentacaoSupport::STATUS_EVENTO,
        'status_documental' => FiscalMovimentacaoSupport::STATUS_DOCUMENTAL,
        'motivos_saida' => FiscalMovimentacaoSupport::MOTIVOS_SAIDA,
        'labels' => FiscalMovimentacaoSupport::labelsTipoMovimentacao(),
        'modulo_ativo' => FiscalMovimentacaoSupport::moduloAtivo(),
    ]);
});

Route::get('/fiscal/eventos', function (Request $request) {
    if (! Schema::hasTable('eventos_fiscais')) {
        return response()->json([]);
    }

    $q = DB::table('eventos_fiscais')
        ->leftJoin('produtos', 'eventos_fiscais.produto_id', '=', 'produtos.id')
        ->leftJoin('unidades', 'eventos_fiscais.unidade_id', '=', 'unidades.id')
        ->select(
            'eventos_fiscais.*',
            'produtos.nome as produto_nome',
            'unidades.nome as unidade_nome'
        );

    if ($request->filled('empresa_id')) {
        $q->where('eventos_fiscais.empresa_id', (int) $request->empresa_id);
    }
    if ($request->filled('tipo_evento')) {
        $q->where('eventos_fiscais.tipo_evento', $request->tipo_evento);
    }
    if ($request->filled('status')) {
        $q->where('eventos_fiscais.status', $request->status);
    }
    if ($request->filled('data_ini')) {
        $q->where('eventos_fiscais.data_evento', '>=', $request->data_ini);
    }
    if ($request->filled('data_fim')) {
        $q->where('eventos_fiscais.data_evento', '<=', $request->data_fim . ' 23:59:59');
    }

    $limit = min(200, max(1, (int) $request->input('limit', 50)));

    return response()->json(
        $q->orderByDesc('eventos_fiscais.id')->limit($limit)->get()
    );
});

Route::get('/fiscal/relatorio/perdas', function (Request $request) {
    if (! FiscalMovimentacaoSupport::moduloAtivo()) {
        return response()->json(['itens' => [], 'totais' => ['quantidade' => 0, 'custo' => 0]]);
    }

    $tipos = ['perda', 'avaria', 'vencimento', 'extravio', 'furto'];
    $q = DB::table('movimentacoes')
        ->leftJoin('produtos', 'movimentacoes.produto_id', '=', 'produtos.id')
        ->leftJoin('unidades', 'movimentacoes.de_unidade_id', '=', 'unidades.id')
        ->whereIn('movimentacoes.tipo_movimentacao', $tipos)
        ->select(
            'movimentacoes.*',
            'produtos.nome as produto_nome',
            'unidades.nome as unidade_nome'
        );

    if ($request->filled('empresa_id')) {
        $q->where('movimentacoes.empresa_origem_id', (int) $request->empresa_id);
    }
    if ($request->filled('data_ini')) {
        $q->where('movimentacoes.data_mov', '>=', $request->data_ini);
    }
    if ($request->filled('data_fim')) {
        $q->where('movimentacoes.data_mov', '<=', $request->data_fim . ' 23:59:59');
    }

    $itens = $q->orderByDesc('movimentacoes.id')->limit(500)->get();
    $custo = $itens->sum(fn ($r) => (float) ($r->custo_total ?? ((float) $r->custo_unitario * (float) $r->qtd)));
    $qtd = $itens->sum(fn ($r) => (float) $r->qtd);

    return response()->json([
        'itens' => $itens,
        'totais' => ['quantidade' => $qtd, 'custo' => round($custo, 2)],
    ]);
});

Route::get('/fiscal/relatorio/transferencias', function (Request $request) {
    if (! FiscalMovimentacaoSupport::moduloAtivo()) {
        return response()->json(['internas' => [], 'entre_cnpjs' => []]);
    }

    $base = DB::table('movimentacoes')
        ->leftJoin('produtos', 'movimentacoes.produto_id', '=', 'produtos.id')
        ->leftJoin('unidades as uo', 'movimentacoes.de_unidade_id', '=', 'uo.id')
        ->leftJoin('unidades as ud', 'movimentacoes.para_unidade_id', '=', 'ud.id')
        ->where('movimentacoes.tipo', 'TRANSFERENCIA')
        ->select(
            'movimentacoes.*',
            'produtos.nome as produto_nome',
            'uo.nome as unidade_origem_nome',
            'ud.nome as unidade_destino_nome'
        );

    if ($request->filled('data_ini')) {
        $base->where('movimentacoes.data_mov', '>=', $request->data_ini);
    }
    if ($request->filled('data_fim')) {
        $base->where('movimentacoes.data_mov', '<=', $request->data_fim . ' 23:59:59');
    }

    $rows = $base->orderByDesc('movimentacoes.id')->limit(500)->get();

    return response()->json([
        'internas' => $rows->where('tipo_movimentacao', 'transferencia_interna')->values(),
        'entre_cnpjs' => $rows->where('tipo_movimentacao', 'operacao_entre_cnpjs')->values(),
    ]);
});

Route::post('/fiscal/movimentacoes/{id}/nfe', function (Request $request, $id) {
    $uid = $request->header('X-Usuario-Id');
    $u = $uid ? DB::table('usuarios')->where('id', $uid)->where('ativo', 1)->first() : null;
    $perfil = strtoupper(trim((string) ($u->perfil ?? '')));
    if (! in_array($perfil, ['ADMIN', 'ADMINISTRADOR', 'GERENTE'], true)) {
        return response()->json(['error' => 'Sem permissão para emitir NF-e.'], 403)
            ->header('Access-Control-Allow-Origin', '*');
    }
    $result = \App\Services\Fiscal\FiscalNfeTransferenciaService::emitirParaMovimentacao((int) $id);
    $code = ($result['emitida'] ?? false) ? 200 : 422;

    return response()->json($result, $code)->header('Access-Control-Allow-Origin', '*');
});
