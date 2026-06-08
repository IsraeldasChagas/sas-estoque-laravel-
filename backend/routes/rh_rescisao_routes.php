<?php

/**
 * Rotas API — RH Rescisão Trabalhista
 * Incluído por routes/api.php
 */

use App\Support\Rh\RhRescisaoCalculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$rrCors = fn () => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');

$rrModulos = [
    'rhRescisaoDashboard', 'rhRescisaoSimulador', 'rhRescisaoCalculo',
    'rhRescisaoComparativo', 'rhRescisaoHistorico', 'rhRescisaoRelatorios',
];

$rrAuth = function (Request $req) {
    $uid = $req->header('X-Usuario-Id');

    return $uid ? DB::table('usuarios')->where('id', $uid)->where('ativo', 1)->first() : null;
};

$podeRhRescisao = function ($u, ?string $modulo = null) use ($rrModulos) {
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
        foreach ($rrModulos as $m) {
            if (in_array($m, $pm, true)) {
                return true;
            }
        }

        return in_array('funcionarios', $pm, true);
    }

    return false;
};

$rrJson = fn ($data, int $code = 200) => response()->json($data, $code)
    ->header('Access-Control-Allow-Origin', '*');

$rrMapRescisao = function ($r) {
    $calc = $r->detalhes_calculo ? json_decode($r->detalhes_calculo, true) : null;

    return [
        'id' => (int) $r->id,
        'empresa_id' => $r->empresa_id ? (int) $r->empresa_id : null,
        'unidade_id' => $r->unidade_id ? (int) $r->unidade_id : null,
        'unidade_nome' => $r->unidade_nome ?? null,
        'funcionario_id' => $r->funcionario_id ? (int) $r->funcionario_id : null,
        'funcionario_nome' => $r->funcionario_nome ?? null,
        'cargo' => $r->cargo,
        'salario_base' => (float) ($r->salario_base ?? 0),
        'data_admissao' => $r->data_admissao,
        'data_demissao' => $r->data_demissao,
        'tipo_contrato' => $r->tipo_contrato,
        'tipo_rescisao' => $r->tipo_rescisao,
        'tipo_rescisao_label' => RhRescisaoCalculo::TIPOS_RESCISAO[$r->tipo_rescisao ?? ''] ?? $r->tipo_rescisao,
        'aviso_previo_tipo' => $r->aviso_previo_tipo,
        'dias_trabalhados_mes' => (int) ($r->dias_trabalhados_mes ?? 0),
        'ferias_vencidas' => (float) ($r->ferias_vencidas ?? 0),
        'ferias_proporcionais' => (float) ($r->ferias_proporcionais ?? 0),
        'decimo_terceiro_proporcional' => (float) ($r->decimo_terceiro_proporcional ?? 0),
        'horas_extras' => (float) ($r->horas_extras ?? 0),
        'adicionais' => (float) ($r->adicionais ?? 0),
        'descontos' => (float) ($r->descontos ?? 0),
        'faltas' => (float) ($r->faltas ?? 0),
        'adiantamentos' => (float) ($r->adiantamentos ?? 0),
        'vale_transporte' => (float) ($r->vale_transporte ?? 0),
        'vale_alimentacao' => (float) ($r->vale_alimentacao ?? 0),
        'fgts_mensal' => (float) ($r->fgts_mensal ?? 0),
        'fgts_estimado' => (float) ($r->fgts_estimado ?? 0),
        'multa_fgts_percentual' => (int) ($r->multa_fgts_percentual ?? 0),
        'multa_fgts_valor' => (float) ($r->multa_fgts_valor ?? 0),
        'inss_estimado' => (float) ($r->inss_estimado ?? 0),
        'irrf_estimado' => (float) ($r->irrf_estimado ?? 0),
        'total_bruto' => (float) ($r->total_bruto ?? 0),
        'total_descontos' => (float) ($r->total_descontos ?? 0),
        'total_liquido' => (float) ($r->total_liquido ?? 0),
        'custo_empresa' => (float) ($r->custo_empresa ?? 0),
        'status' => $r->status,
        'observacoes' => $r->observacoes,
        'detalhes_calculo' => $calc,
        'created_at' => $r->created_at,
        'updated_at' => $r->updated_at,
    ];
};

$rrQueryBase = function () {
    return DB::table('rh_rescisoes as r')
        ->leftJoin('unidades as u', 'r.unidade_id', '=', 'u.id')
        ->leftJoin('funcionarios as f', 'r.funcionario_id', '=', 'f.id')
        ->select('r.*', 'u.nome as unidade_nome', 'f.nome_completo as funcionario_nome');
};

$rrPayloadFromRequest = function (Request $request) {
    return $request->all();
};

$rrSalvarCalculo = function (array $entrada, array $calc, string $status = 'simulacao') {
    $e = $calc['entrada'] ?? RhRescisaoCalculo::normalizarEntrada($entrada);

    return [
        'empresa_id' => $e['empresa_id'],
        'unidade_id' => $e['unidade_id'],
        'funcionario_id' => $e['funcionario_id'],
        'cargo' => $e['cargo'] ?: null,
        'salario_base' => $e['salario_base'],
        'data_admissao' => $e['data_admissao'] ?: null,
        'data_demissao' => $e['data_demissao'] ?: null,
        'tipo_contrato' => $e['tipo_contrato'],
        'tipo_rescisao' => $e['tipo_rescisao'],
        'aviso_previo_tipo' => $e['aviso_previo_tipo'],
        'dias_trabalhados_mes' => $e['dias_trabalhados_mes'],
        'ferias_vencidas' => $e['ferias_vencidas'],
        'ferias_proporcionais' => $e['ferias_proporcionais'],
        'decimo_terceiro_proporcional' => $calc['decimo_terceiro_proporcional'] ?? $e['decimo_terceiro_proporcional'],
        'horas_extras' => $e['horas_extras'],
        'adicionais' => $e['adicionais'],
        'descontos' => $e['descontos'],
        'faltas' => $e['faltas'],
        'adiantamentos' => $e['adiantamentos'],
        'vale_transporte' => $e['vale_transporte'],
        'vale_alimentacao' => $e['vale_alimentacao'],
        'fgts_mensal' => $e['fgts_mensal'],
        'fgts_estimado' => $calc['fgts_estimado'],
        'multa_fgts_percentual' => $calc['multa_fgts_percentual'],
        'multa_fgts_valor' => $calc['multa_fgts_valor'],
        'inss_estimado' => $calc['inss_estimado'],
        'irrf_estimado' => $calc['irrf_estimado'],
        'total_bruto' => $calc['total_bruto'],
        'total_descontos' => $calc['total_descontos'],
        'total_liquido' => $calc['total_liquido'],
        'custo_empresa' => $calc['custo_empresa'],
        'status' => $status,
        'observacoes' => $e['observacoes'] ?: null,
        'detalhes_calculo' => json_encode($calc, JSON_UNESCAPED_UNICODE),
        'updated_at' => now(),
    ];
};

foreach ([
    '/rh/rescisoes/calcular', '/rh/rescisoes/comparar', '/rh/rescisoes/dashboard',
    '/rh/rescisoes/catalogos', '/rh/rescisoes', '/rh/rescisoes/relatorio.pdf',
] as $path) {
    Route::options($path, $rrCors);
}
Route::options('/rh/rescisoes/{id}', $rrCors);
Route::options('/rh/rescisoes/{id}/confirmar', $rrCors);
Route::options('/rh/rescisoes/{id}/pdf', $rrCors);

Route::get('/rh/rescisoes/catalogos', function (Request $request) use ($rrAuth, $podeRhRescisao, $rrJson) {
    $u = $rrAuth($request);
    if (! $podeRhRescisao($u)) {
        return $rrJson(['error' => 'Sem permissão'], 403);
    }

    return $rrJson([
        'tipos_contrato' => RhRescisaoCalculo::TIPOS_CONTRATO,
        'tipos_rescisao' => RhRescisaoCalculo::TIPOS_RESCISAO,
        'aviso_previo' => RhRescisaoCalculo::AVISO_PREVIO,
        'cenarios' => RhRescisaoCalculo::CENARIOS_COMPARATIVO,
        'aviso_legal' => RhRescisaoCalculo::AVISO,
    ]);
});

Route::post('/rh/rescisoes/calcular', function (Request $request) use ($rrAuth, $podeRhRescisao, $rrJson, $rrPayloadFromRequest) {
    $u = $rrAuth($request);
    if (! $podeRhRescisao($u, 'rhRescisaoSimulador')) {
        return $rrJson(['error' => 'Sem permissão'], 403);
    }
    $calc = RhRescisaoCalculo::calcular($rrPayloadFromRequest($request));

    return $rrJson($calc);
});

Route::post('/rh/rescisoes/comparar', function (Request $request) use ($rrAuth, $podeRhRescisao, $rrJson, $rrPayloadFromRequest) {
    $u = $rrAuth($request);
    if (! $podeRhRescisao($u, 'rhRescisaoComparativo')) {
        return $rrJson(['error' => 'Sem permissão'], 403);
    }

    return $rrJson(RhRescisaoCalculo::compararCenarios($rrPayloadFromRequest($request)));
});

Route::get('/rh/rescisoes/dashboard', function (Request $request) use ($rrAuth, $podeRhRescisao, $rrJson) {
    $u = $rrAuth($request);
    if (! $podeRhRescisao($u, 'rhRescisaoDashboard')) {
        return $rrJson(['error' => 'Sem permissão'], 403);
    }
    if (! Schema::hasTable('rh_rescisoes')) {
        return $rrJson(['aviso' => RhRescisaoCalculo::AVISO, 'cards' => [], 'graficos' => []]);
    }

    $mes = now()->format('Y-m');
    $unidadeId = $request->query('unidade_id');

    $qMes = DB::table('rh_rescisoes')->where('status', '!=', 'cancelada')
        ->whereRaw("DATE_FORMAT(data_demissao, '%Y-%m') = ?", [$mes]);
    if ($unidadeId) {
        $qMes->where('unidade_id', (int) $unidadeId);
    }

    $totalMes = (clone $qMes)->count();
    $custoMes = (float) (clone $qMes)->sum('custo_empresa');

    $porUnidade = DB::table('rh_rescisoes as r')
        ->leftJoin('unidades as u', 'r.unidade_id', '=', 'u.id')
        ->where('r.status', '!=', 'cancelada')
        ->when($unidadeId, fn ($q) => $q->where('r.unidade_id', (int) $unidadeId))
        ->groupBy('r.unidade_id', 'u.nome')
        ->selectRaw('r.unidade_id, u.nome as unidade_nome, COUNT(*) as total, SUM(r.custo_empresa) as custo')
        ->get();

    $porTipo = DB::table('rh_rescisoes')
        ->where('status', '!=', 'cancelada')
        ->when($unidadeId, fn ($q) => $q->where('unidade_id', (int) $unidadeId))
        ->groupBy('tipo_rescisao')
        ->selectRaw('tipo_rescisao, COUNT(*) as total, SUM(custo_empresa) as custo')
        ->get();

    $porMes = DB::table('rh_rescisoes')
        ->where('status', '!=', 'cancelada')
        ->when($unidadeId, fn ($q) => $q->where('unidade_id', (int) $unidadeId))
        ->whereNotNull('data_demissao')
        ->groupBy(DB::raw("DATE_FORMAT(data_demissao, '%Y-%m')"))
        ->selectRaw("DATE_FORMAT(data_demissao, '%Y-%m') as mes, COUNT(*) as total, SUM(custo_empresa) as custo")
        ->orderBy('mes')
        ->limit(12)
        ->get();

    $ranking = DB::table('rh_rescisoes as r')
        ->leftJoin('funcionarios as f', 'r.funcionario_id', '=', 'f.id')
        ->where('r.status', '!=', 'cancelada')
        ->when($unidadeId, fn ($q) => $q->where('r.unidade_id', (int) $unidadeId))
        ->orderByDesc('r.custo_empresa')
        ->limit(10)
        ->select('r.id', 'r.custo_empresa', 'r.tipo_rescisao', 'f.nome_completo as funcionario_nome', 'r.data_demissao')
        ->get();

    $alertasExperiencia = [];
    $alertasFerias = [];
    if (Schema::hasTable('funcionarios')) {
        $funcs = DB::table('funcionarios')
            ->where('status', 'ativo')
            ->whereNotNull('data_admissao')
            ->when($unidadeId, fn ($q) => $q->where('unidade_id', (int) $unidadeId))
            ->get(['id', 'nome_completo', 'data_admissao', 'unidade_id', 'cargo']);
        foreach ($funcs as $f) {
            try {
                $adm = new \DateTimeImmutable($f->data_admissao);
                $fimExp = $adm->modify('+90 days');
                $dias = (int) (new \DateTimeImmutable('today'))->diff($fimExp)->days;
                if ($fimExp >= new \DateTimeImmutable('today') && $dias <= 15) {
                    $alertasExperiencia[] = [
                        'funcionario_id' => (int) $f->id,
                        'nome' => $f->nome_completo,
                        'dias_restantes' => $dias,
                        'fim_experiencia' => $fimExp->format('Y-m-d'),
                    ];
                }
                $meses = RhRescisaoCalculo::normalizarEntrada([]);
                $anos = (new \DateTimeImmutable('today'))->diff($adm)->y;
                if ($anos >= 1) {
                    $alertasFerias[] = [
                        'funcionario_id' => (int) $f->id,
                        'nome' => $f->nome_completo,
                        'anos' => $anos,
                        'msg' => 'Verificar férias vencidas',
                    ];
                }
            } catch (\Throwable) {
                continue;
            }
        }
    }

    return $rrJson([
        'aviso' => RhRescisaoCalculo::AVISO,
        'cards' => [
            'total_mes' => $totalMes,
            'custo_mes' => round($custoMes, 2),
            'por_unidade' => $porUnidade,
            'por_tipo' => $porTipo->map(fn ($t) => [
                'tipo' => $t->tipo_rescisao,
                'label' => RhRescisaoCalculo::TIPOS_RESCISAO[$t->tipo_rescisao ?? ''] ?? $t->tipo_rescisao,
                'total' => (int) $t->total,
                'custo' => round((float) $t->custo, 2),
            ]),
        ],
        'graficos' => [
            'por_mes' => $porMes,
            'por_unidade' => $porUnidade,
            'por_tipo' => $porTipo,
            'custo_por_mes' => $porMes,
        ],
        'alertas' => [
            'experiencia' => array_slice($alertasExperiencia, 0, 20),
            'ferias_vencidas' => array_slice($alertasFerias, 0, 20),
        ],
        'ranking_custo' => $ranking,
    ]);
});

Route::get('/rh/rescisoes', function (Request $request) use ($rrAuth, $podeRhRescisao, $rrJson, $rrQueryBase, $rrMapRescisao) {
    $u = $rrAuth($request);
    if (! $podeRhRescisao($u, 'rhRescisaoHistorico')) {
        return $rrJson(['error' => 'Sem permissão'], 403);
    }
    if (! Schema::hasTable('rh_rescisoes')) {
        return $rrJson([]);
    }

    $q = $rrQueryBase();
    if ($request->filled('unidade_id')) {
        $q->where('r.unidade_id', (int) $request->query('unidade_id'));
    }
    if ($request->filled('funcionario_id')) {
        $q->where('r.funcionario_id', (int) $request->query('funcionario_id'));
    }
    if ($request->filled('status')) {
        $q->where('r.status', $request->query('status'));
    }
    if ($request->filled('tipo_rescisao')) {
        $q->where('r.tipo_rescisao', $request->query('tipo_rescisao'));
    }
    if ($request->filled('data_inicio')) {
        $q->where('r.data_demissao', '>=', $request->query('data_inicio'));
    }
    if ($request->filled('data_fim')) {
        $q->where('r.data_demissao', '<=', $request->query('data_fim'));
    }

    $rows = $q->orderByDesc('r.id')->limit(500)->get();

    return $rrJson($rows->map(fn ($r) => $rrMapRescisao($r))->values());
});

Route::get('/rh/rescisoes/{id}', function (Request $request, $id) use ($rrAuth, $podeRhRescisao, $rrJson, $rrQueryBase, $rrMapRescisao) {
    $u = $rrAuth($request);
    if (! $podeRhRescisao($u)) {
        return $rrJson(['error' => 'Sem permissão'], 403);
    }
    $r = $rrQueryBase()->where('r.id', (int) $id)->first();
    if (! $r) {
        return $rrJson(['error' => 'Não encontrado'], 404);
    }
    $cenarios = Schema::hasTable('rh_rescisao_cenarios')
        ? DB::table('rh_rescisao_cenarios')->where('rescisao_id', (int) $id)->get()
        : collect();

    return $rrJson([
        'rescisao' => $rrMapRescisao($r),
        'cenarios' => $cenarios,
    ]);
});

Route::post('/rh/rescisoes', function (Request $request) use ($rrAuth, $podeRhRescisao, $rrJson, $rrPayloadFromRequest, $rrSalvarCalculo, $rrQueryBase, $rrMapRescisao) {
    $u = $rrAuth($request);
    if (! $podeRhRescisao($u, 'rhRescisaoSimulador')) {
        return $rrJson(['error' => 'Sem permissão'], 403);
    }
    if (! Schema::hasTable('rh_rescisoes')) {
        return $rrJson(['error' => 'Módulo não configurado. Execute migration.'], 503);
    }

    $entrada = $rrPayloadFromRequest($request);
    $calc = RhRescisaoCalculo::calcular($entrada);
    $status = in_array($request->input('status'), ['simulacao', 'calculada', 'confirmada'], true)
        ? $request->input('status') : 'simulacao';

    $row = $rrSalvarCalculo($entrada, $calc, $status);
    $row['criado_por'] = $u->id;
    $row['created_at'] = now();
    $id = DB::table('rh_rescisoes')->insertGetId($row);

    if ($request->boolean('salvar_cenarios') && Schema::hasTable('rh_rescisao_cenarios')) {
        $comp = RhRescisaoCalculo::compararCenarios($entrada);
        foreach ($comp['cenarios'] as $c) {
            DB::table('rh_rescisao_cenarios')->insert([
                'rescisao_id' => $id,
                'tipo_cenario' => $c['tipo_cenario'],
                'total_bruto' => $c['total_bruto'],
                'total_descontos' => $c['total_descontos'],
                'total_liquido' => $c['total_liquido'],
                'custo_empresa' => $c['custo_empresa'],
                'fgts_estimado' => $c['fgts_estimado'],
                'multa_fgts_valor' => $c['multa_fgts_valor'],
                'detalhes' => json_encode($c['detalhes'], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    $r = $rrQueryBase()->where('r.id', $id)->first();

    return $rrJson($rrMapRescisao($r), 201);
});

Route::put('/rh/rescisoes/{id}', function (Request $request, $id) use ($rrAuth, $podeRhRescisao, $rrJson, $rrPayloadFromRequest, $rrSalvarCalculo, $rrQueryBase, $rrMapRescisao) {
    $u = $rrAuth($request);
    if (! $podeRhRescisao($u)) {
        return $rrJson(['error' => 'Sem permissão'], 403);
    }
    if (! DB::table('rh_rescisoes')->where('id', (int) $id)->exists()) {
        return $rrJson(['error' => 'Não encontrado'], 404);
    }

    $entrada = $rrPayloadFromRequest($request);
    $calc = RhRescisaoCalculo::calcular($entrada);
    $status = $request->input('status');
    if (! in_array($status, ['simulacao', 'calculada', 'confirmada', 'paga', 'cancelada'], true)) {
        $status = DB::table('rh_rescisoes')->where('id', (int) $id)->value('status') ?: 'simulacao';
    }

    DB::table('rh_rescisoes')->where('id', (int) $id)->update($rrSalvarCalculo($entrada, $calc, $status));
    $r = $rrQueryBase()->where('r.id', (int) $id)->first();

    return $rrJson($rrMapRescisao($r));
});

Route::post('/rh/rescisoes/{id}/confirmar', function (Request $request, $id) use ($rrAuth, $podeRhRescisao, $rrJson, $rrQueryBase, $rrMapRescisao) {
    $u = $rrAuth($request);
    if (! $podeRhRescisao($u)) {
        return $rrJson(['error' => 'Sem permissão'], 403);
    }
    $r = DB::table('rh_rescisoes')->where('id', (int) $id)->first();
    if (! $r) {
        return $rrJson(['error' => 'Não encontrado'], 404);
    }
    DB::table('rh_rescisoes')->where('id', (int) $id)->update(['status' => 'confirmada', 'updated_at' => now()]);
    $row = $rrQueryBase()->where('r.id', (int) $id)->first();

    return $rrJson($rrMapRescisao($row));
});

Route::delete('/rh/rescisoes/{id}', function (Request $request, $id) use ($rrAuth, $podeRhRescisao, $rrJson) {
    $u = $rrAuth($request);
    if (! $podeRhRescisao($u)) {
        return $rrJson(['error' => 'Sem permissão'], 403);
    }
    DB::table('rh_rescisoes')->where('id', (int) $id)->update(['status' => 'cancelada', 'updated_at' => now()]);

    return $rrJson(['sucesso' => true]);
});

Route::get('/rh/rescisoes/{id}/pdf', function (Request $request, $id) use ($rrAuth, $podeRhRescisao, $rrQueryBase, $rrMapRescisao) {
    $u = $rrAuth($request);
    if (! $podeRhRescisao($u, 'rhRescisaoRelatorios')) {
        return response()->json(['error' => 'Sem permissão'], 403)->header('Access-Control-Allow-Origin', '*');
    }
    $r = $rrQueryBase()->where('r.id', (int) $id)->first();
    if (! $r) {
        return response()->json(['error' => 'Não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
    }
    $m = $rrMapRescisao($r);
    $h = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
    body{font-family:DejaVu Sans,sans-serif;font-size:10pt}h1{color:#0d47a1;font-size:14pt}
    table{width:100%;border-collapse:collapse;margin:8px 0}td,th{border:1px solid #ddd;padding:6px}
    .aviso{background:#fff3e0;padding:8px;border-radius:6px;font-size:9pt}
    </style></head><body>
    <h1>Rescisão Trabalhista — '.$h($m['funcionario_nome'] ?? 'Funcionário').'</h1>
    <p class="aviso">'.RhRescisaoCalculo::AVISO.'</p>
    <table><tr><th>Campo</th><th>Valor</th></tr>
    <tr><td>Unidade</td><td>'.$h($m['unidade_nome']).'</td></tr>
    <tr><td>Cargo</td><td>'.$h($m['cargo']).'</td></tr>
    <tr><td>Tipo rescisão</td><td>'.$h($m['tipo_rescisao_label']).'</td></tr>
    <tr><td>Total bruto</td><td>R$ '.number_format($m['total_bruto'], 2, ',', '.').'</td></tr>
    <tr><td>Total descontos</td><td>R$ '.number_format($m['total_descontos'], 2, ',', '.').'</td></tr>
    <tr><td>Total líquido</td><td><strong>R$ '.number_format($m['total_liquido'], 2, ',', '.').'</strong></td></tr>
    <tr><td>Custo empresa</td><td>R$ '.number_format($m['custo_empresa'], 2, ',', '.').'</td></tr>
    </table></body></html>';

    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $pdf = $dompdf->output();

    return response($pdf, 200)
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'inline; filename="rescisao-'.$id.'.pdf"')
        ->header('Access-Control-Allow-Origin', '*');
});
