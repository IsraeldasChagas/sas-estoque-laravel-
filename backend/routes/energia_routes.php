<?php

/**
 * Rotas API — Módulo Manutenção / Energia
 * Incluído por routes/api.php
 */

use App\Support\Energia\EnergiaCalculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$energiaCors = fn () => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');

Route::options('/energia/equipamentos', $energiaCors);
Route::options('/energia/equipamentos/{id}', $energiaCors);
Route::options('/energia/dashboard', $energiaCors);
Route::options('/energia/relatorio-pdf', $energiaCors);
Route::options('/energia/relatorio-csv', $energiaCors);

$energiaAuth = function (Request $req) {
    $uid = $req->header('X-Usuario-Id');

    return $uid ? DB::table('usuarios')->where('id', $uid)->where('ativo', 1)->first() : null;
};

$energiaModulos = ['energiaDashboard', 'energiaEquipamentos', 'energiaProjecao', 'energiaRelatorios'];

$podeEnergia = function ($u, ?string $modulo = null) use ($energiaModulos) {
    if (! $u) {
        return false;
    }
    $p = strtoupper(trim((string) ($u->perfil ?? '')));
    if (in_array($p, ['ADMIN', 'GERENTE', 'ASSISTENTE_ADMINISTRATIVO'], true)) {
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
        foreach ($energiaModulos as $m) {
            if (in_array($m, $pm, true)) {
                return true;
            }
        }

        return false;
    }

    return false;
};

$energiaJson = fn ($data, int $code = 200) => response()->json($data, $code)
    ->header('Access-Control-Allow-Origin', '*');

$energiaMapRow = function ($r) {
    return [
        'id' => (int) $r->id,
        'unidade_id' => (int) $r->unidade_id,
        'unidade_nome' => $r->unidade_nome ?? null,
        'setor' => $r->setor,
        'equipamento_nome' => $r->equipamento_nome,
        'equipamento_tipo' => $r->equipamento_tipo,
        'potencia_watts' => (float) $r->potencia_watts,
        'tensao' => (int) $r->tensao,
        'quantidade' => (int) $r->quantidade,
        'horas_por_dia' => (float) $r->horas_por_dia,
        'dias_uso_mes' => (int) $r->dias_uso_mes,
        'valor_kwh' => (float) $r->valor_kwh,
        'consumo_kwh' => (float) $r->consumo_kwh,
        'custo_estimado' => (float) $r->custo_estimado,
        'observacoes' => $r->observacoes,
        'created_at' => $r->created_at,
        'updated_at' => $r->updated_at,
    ];
};

$energiaQueryBase = function () {
    return DB::table('energia_equipamentos_consumo as e')
        ->leftJoin('unidades as u', 'e.unidade_id', '=', 'u.id')
        ->select('e.*', 'u.nome as unidade_nome');
};

$energiaAplicarFiltros = function ($q, Request $request) {
    if ($request->filled('unidade_id')) {
        $q->where('e.unidade_id', (int) $request->query('unidade_id'));
    }
    if ($request->filled('setor')) {
        $q->where('e.setor', 'like', '%' . $request->query('setor') . '%');
    }
    if ($request->filled('equipamento')) {
        $q->where('e.equipamento_nome', 'like', '%' . $request->query('equipamento') . '%');
    }
    if ($request->filled('equipamento_tipo')) {
        $q->where('e.equipamento_tipo', 'like', '%' . $request->query('equipamento_tipo') . '%');
    }
    if ($request->filled('tensao')) {
        $q->where('e.tensao', (int) $request->query('tensao'));
    }
    if ($request->filled('mes')) {
        $mes = $request->query('mes');
        if (preg_match('/^\d{4}-\d{2}$/', $mes)) {
            $q->whereYear('e.created_at', (int) substr($mes, 0, 4))
                ->whereMonth('e.created_at', (int) substr($mes, 5, 2));
        }
    }
    if ($request->query('maior_consumo') === '1') {
        $q->orderByDesc('e.consumo_kwh');
    } elseif ($request->query('maior_custo') === '1') {
        $q->orderByDesc('e.custo_estimado');
    } else {
        $q->orderByDesc('e.updated_at')->orderByDesc('e.id');
    }

    return $q;
};

$energiaValidarPayload = function (array $d, bool $isUpdate = false) {
    // Horas/dia e dias no mês são opcionais no cadastro (podem ser definidos depois em Projeção de Consumo).
    if (! isset($d['horas_por_dia']) || $d['horas_por_dia'] === '' || $d['horas_por_dia'] === null) {
        $d['horas_por_dia'] = 0;
    }
    if (! isset($d['dias_uso_mes']) || $d['dias_uso_mes'] === '' || $d['dias_uso_mes'] === null) {
        $d['dias_uso_mes'] = 0;
    }
    $rules = [
        'unidade_id' => 'required|integer',
        'setor' => 'required|string|max:120',
        'equipamento_nome' => 'required|string|max:200',
        'equipamento_tipo' => 'nullable|string|max:120',
        'potencia_watts' => 'required|numeric|min:0',
        'tensao' => 'required|integer',
        'quantidade' => 'required|integer|min:1',
        'horas_por_dia' => 'nullable|numeric|min:0|max:24',
        'dias_uso_mes' => 'nullable|integer|min:0|max:31',
        'valor_kwh' => 'required|numeric|min:0',
        'observacoes' => 'nullable|string|max:5000',
    ];
    if ($isUpdate) {
        foreach (array_keys($rules) as $k) {
            if (! array_key_exists($k, $d)) {
                unset($rules[$k]);
            }
        }
    }
    $v = \Illuminate\Support\Facades\Validator::make($d, $rules);
    if ($v->fails()) {
        return ['ok' => false, 'error' => implode(' ', $v->errors()->all())];
    }
    if (! EnergiaCalculo::tensaoValida($d['tensao'] ?? 0)) {
        return ['ok' => false, 'error' => 'Tensão deve ser 110V, 220V ou 380V.'];
    }
    if (! DB::table('unidades')->where('id', (int) $d['unidade_id'])->exists()) {
        return ['ok' => false, 'error' => 'Unidade inválida.'];
    }
    $calc = EnergiaCalculo::calcularTotais($d);

    return ['ok' => true, 'calc' => $calc, 'data' => $d];
};

Route::get('/energia/equipamentos', function (Request $request) use (
    $energiaAuth,
    $podeEnergia,
    $energiaJson,
    $energiaQueryBase,
    $energiaAplicarFiltros,
    $energiaMapRow
) {
    if (! Schema::hasTable('energia_equipamentos_consumo')) {
        return $energiaJson(['error' => 'Tabela energia não disponível. Execute as migrations.'], 503);
    }
    $u = $energiaAuth($request);
    if (! $u) {
        return $energiaJson(['error' => 'Não autorizado'], 401);
    }
    if (! $podeEnergia($u, 'energiaEquipamentos') && ! $podeEnergia($u, 'energiaDashboard') && ! $podeEnergia($u, 'energiaRelatorios')) {
        return $energiaJson(['error' => 'Sem permissão'], 403);
    }
    $q = $energiaAplicarFiltros($energiaQueryBase(), $request);
    $rows = $q->limit(min(max((int) $request->query('limit', 500), 1), 2000))->get();

    return $energiaJson($rows->map($energiaMapRow)->values());
});

Route::get('/energia/equipamentos/{id}', function (Request $request, $id) use ($energiaAuth, $podeEnergia, $energiaJson, $energiaQueryBase, $energiaMapRow) {
    if (! Schema::hasTable('energia_equipamentos_consumo')) {
        return $energiaJson(['error' => 'Tabela energia não disponível.'], 503);
    }
    $u = $energiaAuth($request);
    if (! $u || ! $podeEnergia($u, 'energiaEquipamentos')) {
        return $energiaJson(['error' => 'Sem permissão'], 403);
    }
    $row = $energiaQueryBase()->where('e.id', (int) $id)->first();
    if (! $row) {
        return $energiaJson(['error' => 'Registro não encontrado'], 404);
    }

    return $energiaJson($energiaMapRow($row));
});

Route::post('/energia/equipamentos', function (Request $request) use ($energiaAuth, $podeEnergia, $energiaJson, $energiaValidarPayload, $energiaQueryBase, $energiaMapRow) {
    if (! Schema::hasTable('energia_equipamentos_consumo')) {
        return $energiaJson(['error' => 'Tabela energia não disponível.'], 503);
    }
    $u = $energiaAuth($request);
    if (! $u || ! $podeEnergia($u, 'energiaEquipamentos')) {
        return $energiaJson(['error' => 'Sem permissão'], 403);
    }
    $val = $energiaValidarPayload($request->all());
    if (! $val['ok']) {
        return $energiaJson(['error' => $val['error']], 422);
    }
    $d = $val['data'];
    $calc = $val['calc'];
    $id = DB::table('energia_equipamentos_consumo')->insertGetId([
        'unidade_id' => (int) $d['unidade_id'],
        'setor' => trim($d['setor']),
        'equipamento_nome' => trim($d['equipamento_nome']),
        'equipamento_tipo' => isset($d['equipamento_tipo']) ? trim((string) $d['equipamento_tipo']) : null,
        'potencia_watts' => round((float) $d['potencia_watts'], 2),
        'tensao' => (int) $d['tensao'],
        'quantidade' => (int) $d['quantidade'],
        'horas_por_dia' => round((float) $d['horas_por_dia'], 2),
        'dias_uso_mes' => (int) $d['dias_uso_mes'],
        'valor_kwh' => round((float) $d['valor_kwh'], 4),
        'consumo_kwh' => $calc['consumo_kwh'],
        'custo_estimado' => $calc['custo_estimado'],
        'observacoes' => $d['observacoes'] ?? null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $row = $energiaQueryBase()->where('e.id', $id)->first();

    return $energiaJson($energiaMapRow($row), 201);
});

Route::put('/energia/equipamentos/{id}', function (Request $request, $id) use ($energiaAuth, $podeEnergia, $energiaJson, $energiaValidarPayload, $energiaQueryBase, $energiaMapRow) {
    if (! Schema::hasTable('energia_equipamentos_consumo')) {
        return $energiaJson(['error' => 'Tabela energia não disponível.'], 503);
    }
    $u = $energiaAuth($request);
    if (! $u || ! $podeEnergia($u, 'energiaEquipamentos')) {
        return $energiaJson(['error' => 'Sem permissão'], 403);
    }
    $idInt = (int) $id;
    if (! DB::table('energia_equipamentos_consumo')->where('id', $idInt)->exists()) {
        return $energiaJson(['error' => 'Registro não encontrado'], 404);
    }
    $payload = array_merge(
        (array) DB::table('energia_equipamentos_consumo')->where('id', $idInt)->first(),
        $request->all()
    );
    $val = $energiaValidarPayload($payload);
    if (! $val['ok']) {
        return $energiaJson(['error' => $val['error']], 422);
    }
    $d = $val['data'];
    $calc = $val['calc'];
    DB::table('energia_equipamentos_consumo')->where('id', $idInt)->update([
        'unidade_id' => (int) $d['unidade_id'],
        'setor' => trim($d['setor']),
        'equipamento_nome' => trim($d['equipamento_nome']),
        'equipamento_tipo' => isset($d['equipamento_tipo']) ? trim((string) $d['equipamento_tipo']) : null,
        'potencia_watts' => round((float) $d['potencia_watts'], 2),
        'tensao' => (int) $d['tensao'],
        'quantidade' => (int) $d['quantidade'],
        'horas_por_dia' => round((float) $d['horas_por_dia'], 2),
        'dias_uso_mes' => (int) $d['dias_uso_mes'],
        'valor_kwh' => round((float) $d['valor_kwh'], 4),
        'consumo_kwh' => $calc['consumo_kwh'],
        'custo_estimado' => $calc['custo_estimado'],
        'observacoes' => $d['observacoes'] ?? null,
        'updated_at' => now(),
    ]);
    $row = $energiaQueryBase()->where('e.id', $idInt)->first();

    return $energiaJson($energiaMapRow($row));
});

Route::delete('/energia/equipamentos/{id}', function (Request $request, $id) use ($energiaAuth, $podeEnergia, $energiaJson) {
    $u = $energiaAuth($request);
    if (! $u || ! $podeEnergia($u, 'energiaEquipamentos')) {
        return $energiaJson(['error' => 'Sem permissão'], 403);
    }
    $idInt = (int) $id;
    if (! DB::table('energia_equipamentos_consumo')->where('id', $idInt)->exists()) {
        return $energiaJson(['error' => 'Registro não encontrado'], 404);
    }
    DB::table('energia_equipamentos_consumo')->where('id', $idInt)->delete();

    return $energiaJson(['ok' => true]);
});

Route::get('/energia/dashboard', function (Request $request) use (
    $energiaAuth,
    $podeEnergia,
    $energiaJson,
    $energiaQueryBase,
    $energiaAplicarFiltros
) {
    if (! Schema::hasTable('energia_equipamentos_consumo')) {
        return $energiaJson(['error' => 'Tabela energia não disponível.'], 503);
    }
    $u = $energiaAuth($request);
    if (! $u || ! $podeEnergia($u, 'energiaDashboard')) {
        return $energiaJson(['error' => 'Sem permissão'], 403);
    }
    $q = $energiaAplicarFiltros($energiaQueryBase(), $request);
    $rows = $q->get();

    $totalKwh = 0;
    $totalCusto = 0;
    $porUnidade = [];
    $porSetor = [];
    $ranking = [];

    foreach ($rows as $r) {
        $kwh = (float) $r->consumo_kwh;
        $custo = (float) $r->custo_estimado;
        $totalKwh += $kwh;
        $totalCusto += $custo;
        $uid = (string) $r->unidade_id;
        $unome = $r->unidade_nome ?: 'Unidade ' . $uid;
        if (! isset($porUnidade[$uid])) {
            $porUnidade[$uid] = ['unidade_id' => (int) $r->unidade_id, 'unidade_nome' => $unome, 'consumo_kwh' => 0, 'custo_estimado' => 0, 'qtd_equipamentos' => 0];
        }
        $porUnidade[$uid]['consumo_kwh'] += $kwh;
        $porUnidade[$uid]['custo_estimado'] += $custo;
        $porUnidade[$uid]['qtd_equipamentos']++;

        $sk = $uid . '|' . ($r->setor ?? '');
        if (! isset($porSetor[$sk])) {
            $porSetor[$sk] = ['unidade_id' => (int) $r->unidade_id, 'unidade_nome' => $unome, 'setor' => $r->setor, 'consumo_kwh' => 0, 'custo_estimado' => 0];
        }
        $porSetor[$sk]['consumo_kwh'] += $kwh;
        $porSetor[$sk]['custo_estimado'] += $custo;

        $ranking[] = [
            'id' => (int) $r->id,
            'equipamento_nome' => $r->equipamento_nome,
            'unidade_nome' => $unome,
            'setor' => $r->setor,
            'consumo_kwh' => $kwh,
            'custo_estimado' => $custo,
            'horas_por_dia' => (float) $r->horas_por_dia,
        ];
    }

    usort($ranking, fn ($a, $b) => $b['consumo_kwh'] <=> $a['consumo_kwh']);
    $porUnidadeList = array_values($porUnidade);
    usort($porUnidadeList, fn ($a, $b) => $b['consumo_kwh'] <=> $a['consumo_kwh']);
    $porSetorList = array_values($porSetor);
    usort($porSetorList, fn ($a, $b) => $b['consumo_kwh'] <=> $a['consumo_kwh']);

    $maiorUnidade = $porUnidadeList[0] ?? null;
    $maiorSetor = $porSetorList[0] ?? null;
    $maiorEquip = $ranking[0] ?? null;

    $comparativoMes = DB::table('energia_equipamentos_consumo')
        ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as mes')
        ->selectRaw('SUM(consumo_kwh) as consumo_kwh')
        ->selectRaw('SUM(custo_estimado) as custo_estimado')
        ->when($request->filled('unidade_id'), fn ($q) => $q->where('unidade_id', (int) $request->query('unidade_id')))
        ->groupByRaw('DATE_FORMAT(created_at, "%Y-%m")')
        ->orderBy('mes')
        ->limit(12)
        ->get();

    $alertas = [];
    foreach ($rows as $r) {
        $kwh = (float) $r->consumo_kwh;
        $horas = (float) $r->horas_por_dia;
        if ($kwh > 500) {
            $alertas[] = ['tipo' => 'alto_consumo', 'msg' => "Equipamento \"{$r->equipamento_nome}\" ({$r->unidade_nome}) com consumo elevado: " . number_format($kwh, 2, ',', '.') . ' kWh/mês.'];
        }
        if ($horas >= 16) {
            $alertas[] = ['tipo' => 'horas_altas', 'msg' => "\"{$r->equipamento_nome}\" ligado {$horas}h/dia — avalie redução de horas para economia."];
        }
    }
    if ($maiorUnidade) {
        $alertas[] = ['tipo' => 'unidade', 'msg' => 'Unidade com maior consumo: ' . $maiorUnidade['unidade_nome'] . ' (' . number_format($maiorUnidade['consumo_kwh'], 2, ',', '.') . ' kWh).'];
    }
    if ($maiorSetor) {
        $alertas[] = ['tipo' => 'setor', 'msg' => 'Setor com maior gasto: ' . $maiorSetor['setor'] . ' em ' . $maiorSetor['unidade_nome'] . '.'];
    }
    if (count($comparativoMes) >= 2) {
        $last = $comparativoMes[count($comparativoMes) - 1];
        $prev = $comparativoMes[count($comparativoMes) - 2];
        if ((float) $prev->consumo_kwh > 0 && (float) $last->consumo_kwh > (float) $prev->consumo_kwh * 1.1) {
            $alertas[] = ['tipo' => 'tendencia', 'msg' => 'Aumento de consumo no último mês cadastrado (' . $last->mes . ').'];
        }
    }
    if ($maiorEquip && (float) $maiorEquip['horas_por_dia'] > 8) {
        $reducao = EnergiaCalculo::calcularTotais([
            'potencia_watts' => 1000,
            'quantidade' => 1,
            'horas_por_dia' => max(0, (float) $maiorEquip['horas_por_dia'] - 2),
            'dias_uso_mes' => 26,
            'valor_kwh' => 0.8,
        ]);
        $alertas[] = ['tipo' => 'economia', 'msg' => 'Reduzir 2h/dia em equipamentos críticos pode gerar economia significativa. Use Projeção de Consumo para simular.'];
    }

    return $energiaJson([
        'cards' => [
            'consumo_total_kwh' => round($totalKwh, 4),
            'custo_total_estimado' => round($totalCusto, 2),
            'unidade_maior_consumo' => $maiorUnidade,
            'setor_maior_consumo' => $maiorSetor,
            'equipamento_maior_consumo' => $maiorEquip,
            'qtd_equipamentos' => count($rows),
        ],
        'por_unidade' => $porUnidadeList,
        'por_setor' => $porSetorList,
        'ranking_equipamentos' => array_slice($ranking, 0, 15),
        'comparativo_mensal' => $comparativoMes,
        'alertas' => array_slice($alertas, 0, 12),
        'total_registros' => count($rows),
    ]);
});

Route::get('/energia/relatorio-csv', function (Request $request) use ($energiaAuth, $podeEnergia, $energiaQueryBase, $energiaAplicarFiltros) {
    $u = $energiaAuth($request);
    if (! $u || ! $podeEnergia($u, 'energiaRelatorios')) {
        return response('Sem permissão', 403)->header('Access-Control-Allow-Origin', '*');
    }
    $q = $energiaAplicarFiltros($energiaQueryBase(), $request);
    $rows = $q->get();
    $linhas = ["Unidade;Setor;Equipamento;Tipo;Potência (W);Tensão;Qtd;Horas/dia;Dias/mês;Consumo kWh;Custo R$;Valor kWh;Observações"];
    foreach ($rows as $r) {
        $linhas[] = implode(';', [
            $r->unidade_nome,
            $r->setor,
            $r->equipamento_nome,
            $r->equipamento_tipo ?? '',
            number_format((float) $r->potencia_watts, 2, ',', ''),
            $r->tensao . 'V',
            $r->quantidade,
            number_format((float) $r->horas_por_dia, 2, ',', ''),
            $r->dias_uso_mes,
            number_format((float) $r->consumo_kwh, 4, ',', ''),
            number_format((float) $r->custo_estimado, 2, ',', ''),
            number_format((float) $r->valor_kwh, 4, ',', ''),
            str_replace(["\r", "\n", ';'], ' ', (string) ($r->observacoes ?? '')),
        ]);
    }
    $csv = "\xEF\xBB\xBF" . implode("\n", $linhas);

    return response($csv, 200, [
        'Content-Type' => 'text/csv; charset=UTF-8',
        'Content-Disposition' => 'attachment; filename="relatorio-energia.csv"',
        'Access-Control-Allow-Origin' => '*',
    ]);
});

Route::get('/energia/relatorio-pdf', function (Request $request) use ($energiaAuth, $podeEnergia, $energiaQueryBase, $energiaAplicarFiltros) {
    $u = $energiaAuth($request);
    if (! $u || ! $podeEnergia($u, 'energiaRelatorios')) {
        return response('Sem permissão', 403)->header('Access-Control-Allow-Origin', '*');
    }
    $q = $energiaAplicarFiltros($energiaQueryBase(), $request);
    $rows = $q->get();
    $totalKwh = $rows->sum('consumo_kwh');
    $totalCusto = $rows->sum('custo_estimado');
    $trs = '';
    foreach ($rows as $r) {
        $trs .= '<tr><td>' . htmlspecialchars($r->unidade_nome) . '</td><td>' . htmlspecialchars($r->setor) . '</td><td>' . htmlspecialchars($r->equipamento_nome) . '</td><td align="right">' . number_format((float) $r->consumo_kwh, 2, ',', '.') . '</td><td align="right">R$ ' . number_format((float) $r->custo_estimado, 2, ',', '.') . '</td></tr>';
    }
    $html = '<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:10pt}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:6px}th{background:#e3f2fd}</style></head><body>
    <h2>Relatório de Energia — Grupo Sabor Paraense</h2>
    <p>Total: ' . number_format($totalKwh, 2, ',', '.') . ' kWh | Custo: R$ ' . number_format($totalCusto, 2, ',', '.') . ' | Registros: ' . count($rows) . '</p>
    <table><thead><tr><th>Unidade</th><th>Setor</th><th>Equipamento</th><th>Consumo kWh</th><th>Custo</th></tr></thead><tbody>' . ($trs ?: '<tr><td colspan="5">Nenhum registro</td></tr>') . '</tbody></table></body></html>';
    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    return response($dompdf->output(), 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="relatorio-energia.pdf"',
        'Access-Control-Allow-Origin' => '*',
    ]);
});
