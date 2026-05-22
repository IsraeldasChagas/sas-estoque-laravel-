<?php

/**
 * Relatórios PDF/CSV — Patrimônio (incluído por patrimonio_routes.php)
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$patH = static fn (?string $s): string => htmlspecialchars((string) ($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$patFmtBrl = static function ($v): string {
    if ($v === null || $v === '') {
        return '—';
    }

    return 'R$ ' . number_format((float) $v, 2, ',', '.');
};

$patFmtData = static function ($d): string {
    if (! $d) {
        return '—';
    }
    $s = substr((string) $d, 0, 10);
    if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) {
        return $s;
    }

    return $m[3] . '/' . $m[2] . '/' . $m[1];
};

$patPdfFromHtml = static function (string $html, string $filename, bool $download = false) {
    $dompdf = new \Dompdf\Dompdf();
    $options = $dompdf->getOptions();
    $options->set('isRemoteEnabled', false);
    $options->set('isHtml5ParserEnabled', true);
    $dompdf->setOptions($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $out = $dompdf->output();
    $disp = $download ? 'attachment' : 'inline';

    return response($out, 200)
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', $disp . '; filename="' . $filename . '"')
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id')
        ->header('Content-Length', (string) strlen($out));
};

$patCsvDownload = static function (array $header, array $rows, string $filename) {
    $lines = [implode(';', $header)];
    foreach ($rows as $row) {
        $lines[] = implode(';', array_map(static fn ($c) => str_replace([';', "\n", "\r"], [',', ' ', ' '], (string) $c), $row));
    }
    $csv = "\xEF\xBB\xBF" . implode("\n", $lines);

    return response($csv, 200, [
        'Content-Type' => 'text/csv; charset=UTF-8',
        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        'Access-Control-Allow-Origin' => '*',
    ]);
};

$patTableStyle = '
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:9pt;color:#212121}
h1{font-size:14pt;color:#0d47a1;margin:0 0 6px}
.meta{font-size:8pt;color:#616161;margin-bottom:12px}
table{width:100%;border-collapse:collapse}
th{background:#1565c0;color:#fff;text-align:left;padding:6px;font-size:8pt}
td{border-bottom:1px solid #e0e0e0;padding:5px;vertical-align:top}
tr:nth-child(even) td{background:#fafafa}
</style>';

$patRelatorioAuth = static function (Request $request) use ($patrimonioAuth, $podePatrimonio) {
    $u = $patrimonioAuth($request);
    if (! $podePatrimonio($u, 'patrimonioRelatorios')) {
        return [null, response('Sem permissão', 403)->header('Access-Control-Allow-Origin', '*')];
    }

    return [$u, null];
};

foreach ([
    '/patrimonio/relatorios/ficha/{id}.pdf',
    '/patrimonio/relatorios/movimentacoes.pdf',
    '/patrimonio/relatorios/movimentacoes.csv',
    '/patrimonio/relatorios/manutencoes.pdf',
    '/patrimonio/relatorios/manutencoes.csv',
    '/patrimonio/relatorios/depreciacao.pdf',
    '/patrimonio/relatorios/depreciacao.csv',
    '/patrimonio/relatorios/por-unidade.pdf',
    '/patrimonio/relatorios/por-unidade.csv',
    '/patrimonio/relatorios/por-categoria.pdf',
    '/patrimonio/relatorios/por-categoria.csv',
    '/patrimonio/relatorios/inventario/{id}.pdf',
    '/patrimonio/relatorios/inventario/{id}.csv',
] as $p) {
    Route::options($p, $patrimonioCors);
}

Route::get('/patrimonio/relatorios/ficha/{id}.pdf', function (Request $request, $id) use (
    $patRelatorioAuth, $patrimonioQueryBase, $patrimonioMapPatrimonio, $patH, $patFmtBrl, $patFmtData, $patTableStyle, $patPdfFromHtml
) {
    [, $err] = $patRelatorioAuth($request);
    if ($err) {
        return $err;
    }
    $r = $patrimonioQueryBase()->where('p.id', $id)->first();
    if (! $r) {
        return response('Não encontrado', 404);
    }
    $p = $patrimonioMapPatrimonio($r);
    $dados = $p['dados_especificos'] ?? [];
    $extras = '';
    if (is_array($dados)) {
        foreach ($dados as $k => $v) {
            if ($v) {
                $extras .= '<tr><td><strong>' . $patH($k) . '</strong></td><td>' . $patH($v) . '</td></tr>';
            }
        }
    }
    $movs = DB::table('patrimonio_movimentacoes')->where('patrimonio_id', $id)->orderByDesc('created_at')->limit(15)->get();
    $movHtml = '';
    foreach ($movs as $m) {
        $movHtml .= '<tr><td>' . $patFmtData($m->created_at) . '</td><td>' . $patH($m->tipo) . '</td><td>' . $patH($m->observacao) . '</td></tr>';
    }
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">' . $patTableStyle . '</head><body>
    <h1>Ficha patrimonial</h1>
    <p class="meta">Código ' . $patH($p['codigo']) . ' — emitido ' . $patH(now()->format('d/m/Y H:i')) . '</p>
    <table><tbody>
    <tr><td><strong>Nome</strong></td><td>' . $patH($p['nome']) . '</td></tr>
    <tr><td><strong>Categoria</strong></td><td>' . $patH($p['categoria_nome']) . '</td></tr>
    <tr><td><strong>Unidade</strong></td><td>' . $patH($p['unidade_nome']) . '</td></tr>
    <tr><td><strong>Responsável</strong></td><td>' . $patH($p['responsavel']) . '</td></tr>
    <tr><td><strong>Situação</strong></td><td>' . $patH($p['situacao']) . '</td></tr>
    <tr><td><strong>Valor compra</strong></td><td>' . $patFmtBrl($p['valor_compra']) . '</td></tr>
    <tr><td><strong>Valor atual</strong></td><td>' . $patFmtBrl($p['valor_atual']) . '</td></tr>
    <tr><td><strong>Depreciação acum.</strong></td><td>' . $patFmtBrl($p['depreciacao']) . '</td></tr>
    ' . $extras . '</tbody></table>
    <h2 style="font-size:11pt;margin-top:14px">Histórico de movimentações</h2>
    <table><thead><tr><th>Data</th><th>Tipo</th><th>Obs.</th></tr></thead><tbody>' . ($movHtml ?: '<tr><td colspan="3">Nenhuma</td></tr>') . '</tbody></table>
    </body></html>';

    return $patPdfFromHtml($html, 'ficha-patrimonio-' . $id . '.pdf', $request->query('download') === '1');
});

Route::get('/patrimonio/relatorios/movimentacoes.pdf', function (Request $request) use ($patRelatorioAuth, $patH, $patFmtData, $patTableStyle, $patPdfFromHtml) {
    [, $err] = $patRelatorioAuth($request);
    if ($err) {
        return $err;
    }
    $rows = DB::table('patrimonio_movimentacoes as m')
        ->join('patrimonios as p', 'm.patrimonio_id', '=', 'p.id')
        ->select('m.*', 'p.codigo', 'p.nome as patrimonio_nome')
        ->orderByDesc('m.created_at')->limit(500)->get();
    $body = '';
    foreach ($rows as $m) {
        $body .= '<tr><td>' . $patFmtData($m->created_at) . '</td><td>' . $patH($m->codigo) . '</td><td>' . $patH($m->patrimonio_nome) . '</td><td>' . $patH($m->tipo) . '</td><td>' . $patH($m->observacao) . '</td></tr>';
    }
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">' . $patTableStyle . '</head><body>
    <h1>Relatório de movimentações</h1><table><thead><tr><th>Data</th><th>Código</th><th>Patrimônio</th><th>Tipo</th><th>Obs.</th></tr></thead><tbody>' . $body . '</tbody></table></body></html>';

    return $patPdfFromHtml($html, 'patrimonio-movimentacoes.pdf', $request->query('download') === '1');
});

Route::get('/patrimonio/relatorios/movimentacoes.csv', function (Request $request) use ($patRelatorioAuth, $patCsvDownload, $patFmtData) {
    [, $err] = $patRelatorioAuth($request);
    if ($err) {
        return $err;
    }
    $rows = DB::table('patrimonio_movimentacoes as m')
        ->join('patrimonios as p', 'm.patrimonio_id', '=', 'p.id')
        ->select('m.*', 'p.codigo', 'p.nome as patrimonio_nome')
        ->orderByDesc('m.created_at')->get();
    $data = [];
    foreach ($rows as $m) {
        $data[] = [$patFmtData($m->created_at), $m->codigo, $m->patrimonio_nome, $m->tipo, $m->observacao];
    }

    return $patCsvDownload(['Data', 'Código', 'Patrimônio', 'Tipo', 'Observação'], $data, 'patrimonio-movimentacoes.csv');
});

Route::get('/patrimonio/relatorios/manutencoes.pdf', function (Request $request) use ($patRelatorioAuth, $patH, $patFmtBrl, $patFmtData, $patTableStyle, $patPdfFromHtml) {
    [, $err] = $patRelatorioAuth($request);
    if ($err) {
        return $err;
    }
    $rows = DB::table('patrimonio_manutencoes as mn')
        ->join('patrimonios as p', 'mn.patrimonio_id', '=', 'p.id')
        ->select('mn.*', 'p.codigo', 'p.nome as patrimonio_nome')
        ->orderByDesc('mn.data_manutencao')->get();
    $body = '';
    foreach ($rows as $m) {
        $body .= '<tr><td>' . $patFmtData($m->data_manutencao) . '</td><td>' . $patH($m->codigo) . '</td><td>' . $patH($m->patrimonio_nome) . '</td><td>' . $patH($m->tipo_manutencao) . '</td><td>' . $patH($m->tecnico) . '</td><td>' . $patFmtBrl($m->custo) . '</td><td>' . $patFmtData($m->proxima_manutencao) . '</td></tr>';
    }
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">' . $patTableStyle . '</head><body>
    <h1>Relatório de manutenções</h1><table><thead><tr><th>Data</th><th>Código</th><th>Patrimônio</th><th>Tipo</th><th>Técnico</th><th>Custo</th><th>Próxima</th></tr></thead><tbody>' . $body . '</tbody></table></body></html>';

    return $patPdfFromHtml($html, 'patrimonio-manutencoes.pdf', $request->query('download') === '1');
});

Route::get('/patrimonio/relatorios/manutencoes.csv', function (Request $request) use ($patRelatorioAuth, $patCsvDownload, $patFmtBrl, $patFmtData) {
    [, $err] = $patRelatorioAuth($request);
    if ($err) {
        return $err;
    }
    $rows = DB::table('patrimonio_manutencoes as mn')
        ->join('patrimonios as p', 'mn.patrimonio_id', '=', 'p.id')
        ->select('mn.*', 'p.codigo', 'p.nome as patrimonio_nome')
        ->orderByDesc('mn.data_manutencao')->get();
    $data = [];
    foreach ($rows as $m) {
        $data[] = [$patFmtData($m->data_manutencao), $m->codigo, $m->patrimonio_nome, $m->tipo_manutencao, $m->tecnico, $m->custo, $patFmtData($m->proxima_manutencao)];
    }

    return $patCsvDownload(['Data', 'Código', 'Patrimônio', 'Tipo', 'Técnico', 'Custo', 'Próxima'], $data, 'patrimonio-manutencoes.csv');
});

Route::get('/patrimonio/relatorios/depreciacao.pdf', function (Request $request) use ($patrimonioQueryBase, $patRelatorioAuth, $patH, $patFmtBrl, $patTableStyle, $patPdfFromHtml) {
    [, $err] = $patRelatorioAuth($request);
    if ($err) {
        return $err;
    }
    $rows = $patrimonioQueryBase()->whereNotNull('p.valor_compra')->orderBy('p.codigo')->get();
    $body = '';
    foreach ($rows as $r) {
        $body .= '<tr><td>' . $patH($r->codigo) . '</td><td>' . $patH($r->nome) . '</td><td>' . $patFmtBrl($r->valor_compra) . '</td><td>' . $patFmtBrl($r->valor_atual) . '</td><td>' . $patFmtBrl($r->depreciacao) . '</td><td>' . $patH($r->vida_util_meses) . '</td></tr>';
    }
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">' . $patTableStyle . '</head><body>
    <h1>Relatório de depreciação</h1><table><thead><tr><th>Código</th><th>Nome</th><th>Compra</th><th>Atual</th><th>Deprec.</th><th>Vida útil (meses)</th></tr></thead><tbody>' . $body . '</tbody></table></body></html>';

    return $patPdfFromHtml($html, 'patrimonio-depreciacao.pdf', $request->query('download') === '1');
});

Route::get('/patrimonio/relatorios/depreciacao.csv', function (Request $request) use ($patrimonioQueryBase, $patRelatorioAuth, $patCsvDownload) {
    [, $err] = $patRelatorioAuth($request);
    if ($err) {
        return $err;
    }
    $rows = $patrimonioQueryBase()->orderBy('p.codigo')->get();
    $data = [];
    foreach ($rows as $r) {
        $data[] = [$r->codigo, $r->nome, $r->valor_compra, $r->valor_atual, $r->depreciacao, $r->vida_util_meses];
    }

    return $patCsvDownload(['Código', 'Nome', 'Valor compra', 'Valor atual', 'Depreciação', 'Vida útil meses'], $data, 'patrimonio-depreciacao.csv');
});

Route::get('/patrimonio/relatorios/por-unidade.pdf', function (Request $request) use ($patRelatorioAuth, $patH, $patTableStyle, $patPdfFromHtml) {
    [, $err] = $patRelatorioAuth($request);
    if ($err) {
        return $err;
    }
    $agg = DB::table('patrimonios as p')
        ->leftJoin('unidades as u', 'p.unidade_id', '=', 'u.id')
        ->select(DB::raw("COALESCE(u.nome,'Sem unidade') as label"), DB::raw('COUNT(*) as qtd'), DB::raw('SUM(COALESCE(p.valor_atual,0)) as valor'))
        ->groupBy('u.nome')->orderByDesc('qtd')->get();
    $body = '';
    foreach ($agg as $a) {
        $body .= '<tr><td>' . $patH($a->label) . '</td><td>' . $a->qtd . '</td><td>R$ ' . number_format((float) $a->valor, 2, ',', '.') . '</td></tr>';
    }
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">' . $patTableStyle . '</head><body>
    <h1>Ativos por unidade</h1><table><thead><tr><th>Unidade</th><th>Qtd</th><th>Valor total</th></tr></thead><tbody>' . $body . '</tbody></table></body></html>';

    return $patPdfFromHtml($html, 'patrimonio-por-unidade.pdf', $request->query('download') === '1');
});

Route::get('/patrimonio/relatorios/por-unidade.csv', function (Request $request) use ($patRelatorioAuth, $patCsvDownload) {
    [, $err] = $patRelatorioAuth($request);
    if ($err) {
        return $err;
    }
    $agg = DB::table('patrimonios as p')
        ->leftJoin('unidades as u', 'p.unidade_id', '=', 'u.id')
        ->select(DB::raw("COALESCE(u.nome,'Sem unidade') as label"), DB::raw('COUNT(*) as qtd'), DB::raw('SUM(COALESCE(p.valor_atual,0)) as valor'))
        ->groupBy('u.nome')->get();
    $data = [];
    foreach ($agg as $a) {
        $data[] = [$a->label, $a->qtd, $a->valor];
    }

    return $patCsvDownload(['Unidade', 'Quantidade', 'Valor total'], $data, 'patrimonio-por-unidade.csv');
});

Route::get('/patrimonio/relatorios/por-categoria.pdf', function (Request $request) use ($patRelatorioAuth, $patH, $patTableStyle, $patPdfFromHtml) {
    [, $err] = $patRelatorioAuth($request);
    if ($err) {
        return $err;
    }
    $agg = DB::table('patrimonios as p')
        ->leftJoin('patrimonio_categorias as c', 'p.categoria_id', '=', 'c.id')
        ->select(DB::raw("COALESCE(c.nome,'Sem categoria') as label"), DB::raw('COUNT(*) as qtd'), DB::raw('SUM(COALESCE(p.valor_atual,0)) as valor'))
        ->groupBy('c.nome')->orderByDesc('qtd')->get();
    $body = '';
    foreach ($agg as $a) {
        $body .= '<tr><td>' . $patH($a->label) . '</td><td>' . $a->qtd . '</td><td>R$ ' . number_format((float) $a->valor, 2, ',', '.') . '</td></tr>';
    }
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">' . $patTableStyle . '</head><body>
    <h1>Patrimônio por categoria</h1><table><thead><tr><th>Categoria</th><th>Qtd</th><th>Valor total</th></tr></thead><tbody>' . $body . '</tbody></table></body></html>';

    return $patPdfFromHtml($html, 'patrimonio-por-categoria.pdf', $request->query('download') === '1');
});

Route::get('/patrimonio/relatorios/por-categoria.csv', function (Request $request) use ($patRelatorioAuth, $patCsvDownload) {
    [, $err] = $patRelatorioAuth($request);
    if ($err) {
        return $err;
    }
    $agg = DB::table('patrimonios as p')
        ->leftJoin('patrimonio_categorias as c', 'p.categoria_id', '=', 'c.id')
        ->select(DB::raw("COALESCE(c.nome,'Sem categoria') as label"), DB::raw('COUNT(*) as qtd'), DB::raw('SUM(COALESCE(p.valor_atual,0)) as valor'))
        ->groupBy('c.nome')->get();
    $data = [];
    foreach ($agg as $a) {
        $data[] = [$a->label, $a->qtd, $a->valor];
    }

    return $patCsvDownload(['Categoria', 'Quantidade', 'Valor total'], $data, 'patrimonio-por-categoria.csv');
});

Route::get('/patrimonio/relatorios/inventario/{id}.pdf', function (Request $request, $id) use ($patRelatorioAuth, $patH, $patFmtData, $patTableStyle, $patPdfFromHtml) {
    [, $err] = $patRelatorioAuth($request);
    if ($err) {
        return $err;
    }
    $inv = DB::table('patrimonio_inventario')->where('id', $id)->first();
    if (! $inv) {
        return response('Não encontrado', 404);
    }
    $itens = DB::table('patrimonio_inventario_itens as it')
        ->join('patrimonios as p', 'it.patrimonio_id', '=', 'p.id')
        ->where('it.inventario_id', $id)
        ->select('it.*', 'p.codigo', 'p.nome as patrimonio_nome')
        ->orderBy('p.nome')->get();
    $body = '';
    foreach ($itens as $it) {
        $body .= '<tr><td>' . $patH($it->codigo) . '</td><td>' . $patH($it->patrimonio_nome) . '</td><td>' . $it->qtd_sistema . '</td><td>' . $patH($it->qtd_encontrada ?? '—') . '</td><td>' . $patH($it->diferenca ?? '—') . '</td><td>' . $patH($it->observacao) . '</td></tr>';
    }
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">' . $patTableStyle . '</head><body>
    <h1>Inventário patrimonial</h1>
    <p class="meta">' . $patH($inv->titulo) . ' — ' . $patFmtData($inv->data_inicio) . '</p>
    <table><thead><tr><th>Código</th><th>Patrimônio</th><th>Qtd sistema</th><th>Qtd encontrada</th><th>Diferença</th><th>Obs.</th></tr></thead><tbody>' . $body . '</tbody></table></body></html>';

    return $patPdfFromHtml($html, 'inventario-' . $id . '.pdf', $request->query('download') === '1');
});

Route::get('/patrimonio/relatorios/inventario/{id}.csv', function (Request $request, $id) use ($patRelatorioAuth, $patCsvDownload) {
    [, $err] = $patRelatorioAuth($request);
    if ($err) {
        return $err;
    }
    $itens = DB::table('patrimonio_inventario_itens as it')
        ->join('patrimonios as p', 'it.patrimonio_id', '=', 'p.id')
        ->where('it.inventario_id', $id)
        ->select('it.*', 'p.codigo', 'p.nome as patrimonio_nome')
        ->get();
    $data = [];
    foreach ($itens as $it) {
        $data[] = [$it->codigo, $it->patrimonio_nome, $it->qtd_sistema, $it->qtd_encontrada, $it->diferenca, $it->observacao];
    }

    return $patCsvDownload(['Código', 'Patrimônio', 'Qtd sistema', 'Qtd encontrada', 'Diferença', 'Observação'], $data, 'inventario-' . $id . '.csv');
});
