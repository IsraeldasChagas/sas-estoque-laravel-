<?php

/**
 * Rotas API — Módulo Investimento (Tesouraria e Reservas Empresariais)
 * Incluído por routes/api.php
 */

use App\Support\Investimento\InvestimentoCalculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$invCors = fn () => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');

$invModulos = [
    'investimentoDashboard', 'investimentoReservas', 'investimentoSimulador',
    'investimentoCarteira', 'investimentoResgates', 'investimentoRelatorios',
];

$invAuth = function (Request $req) {
    $uid = $req->header('X-Usuario-Id');

    return $uid ? DB::table('usuarios')->where('id', $uid)->where('ativo', 1)->first() : null;
};

$podeInvestimento = function ($u, ?string $modulo = null) use ($invModulos) {
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
        foreach ($invModulos as $m) {
            if (in_array($m, $pm, true)) {
                return true;
            }
        }

        return false;
    }

    return false;
};

$invJson = fn ($data, int $code = 200) => response()->json($data, $code)
    ->header('Access-Control-Allow-Origin', '*');

$invObjetivoLabel = fn (?string $obj) => InvestimentoCalculo::OBJETIVOS[$obj ?? ''] ?? ($obj ?? '—');

$invTipoLabel = fn (?string $tipo) => InvestimentoCalculo::TIPOS[$tipo ?? '']['label'] ?? ($tipo ?? '—');

$invMapReserva = function ($r) use ($invObjetivoLabel) {
    $alerta = InvestimentoCalculo::alertaDataAlvo($r->data_alvo ?? null, $r->objetivo ?? '');

    return [
        'id' => (int) $r->id,
        'unidade_id' => $r->unidade_id ? (int) $r->unidade_id : null,
        'unidade_nome' => $r->unidade_nome ?? null,
        'objetivo' => $r->objetivo,
        'objetivo_label' => $invObjetivoLabel($r->objetivo),
        'valor_inicial' => (float) ($r->valor_inicial ?? 0),
        'aporte_mensal' => (float) ($r->aporte_mensal ?? 0),
        'prazo_meses' => $r->prazo_meses !== null ? (int) $r->prazo_meses : null,
        'data_alvo' => $r->data_alvo,
        'observacoes' => $r->observacoes,
        'ativo' => (bool) ($r->ativo ?? true),
        'alerta_data_alvo' => $alerta,
        'tipos_sugeridos' => array_keys(InvestimentoCalculo::tiposPermitidosParaObjetivo($r->objetivo ?? '')),
        'created_at' => $r->created_at,
        'updated_at' => $r->updated_at,
    ];
};

$invMapCarteira = function ($r) use ($invObjetivoLabel, $invTipoLabel) {
    $est = InvestimentoCalculo::estimarRendimentoCarteira(
        (float) ($r->valor_aplicado ?? 0),
        $r->taxa_contratada !== null ? (float) $r->taxa_contratada : null,
        $r->data_compra ?? date('Y-m-d')
    );

    return [
        'id' => (int) $r->id,
        'unidade_id' => $r->unidade_id ? (int) $r->unidade_id : null,
        'unidade_nome' => $r->unidade_nome ?? null,
        'data_compra' => $r->data_compra,
        'instituicao' => $r->instituicao,
        'tipo_investimento' => $r->tipo_investimento,
        'tipo_label' => $invTipoLabel($r->tipo_investimento),
        'valor_aplicado' => (float) ($r->valor_aplicado ?? 0),
        'taxa_contratada' => $r->taxa_contratada !== null ? (float) $r->taxa_contratada : null,
        'taxa_mensal' => $r->taxa_mensal !== null ? (float) $r->taxa_mensal : null,
        'liquidez' => $r->liquidez,
        'vencimento' => $r->vencimento,
        'reserva_id' => $r->reserva_id ? (int) $r->reserva_id : null,
        'objetivo' => $r->objetivo ?? null,
        'objetivo_label' => $invObjetivoLabel($r->objetivo ?? null),
        'status' => $r->status,
        'observacoes' => $r->observacoes,
        'rendimento_estimado' => $est,
        'created_at' => $r->created_at,
        'updated_at' => $r->updated_at,
    ];
};

$invMapResgate = function ($r) use ($invTipoLabel) {
    return [
        'id' => (int) $r->id,
        'carteira_id' => (int) $r->carteira_id,
        'instituicao' => $r->instituicao ?? null,
        'tipo_investimento' => $r->tipo_investimento ?? null,
        'tipo_label' => $invTipoLabel($r->tipo_investimento ?? null),
        'unidade_nome' => $r->unidade_nome ?? null,
        'data_resgate' => $r->data_resgate,
        'valor_resgatado' => (float) ($r->valor_resgatado ?? 0),
        'valor_bruto' => $r->valor_bruto !== null ? (float) $r->valor_bruto : null,
        'imposto' => (float) ($r->imposto ?? 0),
        'valor_liquido' => $r->valor_liquido !== null ? (float) $r->valor_liquido : null,
        'observacoes' => $r->observacoes,
        'created_at' => $r->created_at,
    ];
};

$invAplicarFiltroUnidade = function ($q, Request $request, string $alias = 'r') {
    if ($request->filled('unidade_id')) {
        $q->where("{$alias}.unidade_id", (int) $request->query('unidade_id'));
    }

    return $q;
};

$invResolverLiquidez = function (string $tipo): string {
    return InvestimentoCalculo::TIPOS[$tipo]['liquidez'] ?? 'media';
};

// CORS
foreach ([
    '/investimento/catalogos', '/investimento/dashboard', '/investimento/simular',
    '/investimento/reservas', '/investimento/reservas/{id}',
    '/investimento/carteira', '/investimento/carteira/{id}',
    '/investimento/resgates', '/investimento/resgates/{id}',
    '/investimento/relatorios',
] as $path) {
    Route::options($path, $invCors);
}

// Catálogos (objetivos, tipos)
Route::get('/investimento/catalogos', function (Request $request) use ($invAuth, $podeInvestimento, $invJson) {
    $u = $invAuth($request);
    if (! $podeInvestimento($u)) {
        return $invJson(['error' => 'Acesso negado'], 403);
    }

    return $invJson([
        'objetivos' => InvestimentoCalculo::OBJETIVOS,
        'tipos' => InvestimentoCalculo::TIPOS,
        'tipos_alta_liquidez' => InvestimentoCalculo::TIPOS_ALTA_LIQUIDEZ,
        'objetivos_alerta_data' => InvestimentoCalculo::OBJETIVOS_ALERTA_DATA,
    ]);
});

// Dashboard
Route::get('/investimento/dashboard', function (Request $request) use ($invAuth, $podeInvestimento, $invJson, $invAplicarFiltroUnidade, $invObjetivoLabel) {
    $u = $invAuth($request);
    if (! $podeInvestimento($u, 'investimentoDashboard')) {
        return $invJson(['error' => 'Acesso negado'], 403);
    }
    if (! Schema::hasTable('investimento_reservas')) {
        return $invJson(['cards' => [], 'por_objetivo' => [], 'proximos_vencimentos' => [], 'alertas' => []]);
    }

    $unidadeId = $request->query('unidade_id');

    $qRes = DB::table('investimento_reservas as r')
        ->leftJoin('unidades as u', 'r.unidade_id', '=', 'u.id')
        ->where('r.ativo', 1);
    if ($unidadeId) {
        $qRes->where('r.unidade_id', (int) $unidadeId);
    }
    $reservas = $qRes->select('r.*', 'u.nome as unidade_nome')->get();

    $totalReservado = 0.0;
    $totalAporteMensal = 0.0;
    $porObjetivo = [];
    $alertas = [];
    foreach ($reservas as $r) {
        $totalReservado += (float) $r->valor_inicial;
        $totalAporteMensal += (float) $r->aporte_mensal;
        $obj = $r->objetivo;
        if (! isset($porObjetivo[$obj])) {
            $porObjetivo[$obj] = ['objetivo' => $obj, 'label' => $invObjetivoLabel($obj), 'total' => 0, 'qtd' => 0];
        }
        $porObjetivo[$obj]['total'] += (float) $r->valor_inicial;
        $porObjetivo[$obj]['qtd']++;
        $alerta = InvestimentoCalculo::alertaDataAlvo($r->data_alvo, $obj);
        if ($alerta) {
            $alertas[] = array_merge($alerta, [
                'reserva_id' => (int) $r->id,
                'objetivo_label' => $invObjetivoLabel($obj),
                'data_alvo' => $r->data_alvo,
                'unidade_nome' => $r->unidade_nome,
            ]);
        }
    }

    $totalAplicado = 0.0;
    $rendimentoEstimado = 0.0;
    $proximosVencimentos = [];
    if (Schema::hasTable('investimento_carteira')) {
        $qCart = DB::table('investimento_carteira as c')
            ->leftJoin('unidades as u', 'c.unidade_id', '=', 'u.id')
            ->leftJoin('investimento_reservas as r', 'c.reserva_id', '=', 'r.id')
            ->where('c.status', 'ativo');
        if ($unidadeId) {
            $qCart->where('c.unidade_id', (int) $unidadeId);
        }
        $carteira = $qCart->select('c.*', 'u.nome as unidade_nome', 'r.objetivo')->get();
        foreach ($carteira as $c) {
            $totalAplicado += (float) $c->valor_aplicado;
            $est = InvestimentoCalculo::estimarRendimentoCarteira(
                (float) $c->valor_aplicado,
                $c->taxa_contratada !== null ? (float) $c->taxa_contratada : null,
                $c->data_compra
            );
            $rendimentoEstimado += $est['rendimento_liquido'];
            if ($c->vencimento) {
                $proximosVencimentos[] = [
                    'id' => (int) $c->id,
                    'instituicao' => $c->instituicao,
                    'vencimento' => $c->vencimento,
                    'valor_aplicado' => (float) $c->valor_aplicado,
                    'unidade_nome' => $c->unidade_nome,
                ];
            }
        }
        usort($proximosVencimentos, fn ($a, $b) => strcmp($a['vencimento'], $b['vencimento']));
        $proximosVencimentos = array_slice($proximosVencimentos, 0, 10);
    }

    return $invJson([
        'cards' => [
            'total_reservado' => round($totalReservado, 2),
            'total_aporte_mensal' => round($totalAporteMensal, 2),
            'total_aplicado' => round($totalAplicado, 2),
            'rendimento_estimado' => round($rendimentoEstimado, 2),
            'qtd_reservas' => count($reservas),
        ],
        'por_objetivo' => array_values($porObjetivo),
        'proximos_vencimentos' => $proximosVencimentos,
        'alertas' => $alertas,
    ]);
});

// Simulador
Route::post('/investimento/simular', function (Request $request) use ($invAuth, $podeInvestimento, $invJson, $invResolverLiquidez) {
    $u = $invAuth($request);
    if (! $podeInvestimento($u, 'investimentoSimulador')) {
        return $invJson(['error' => 'Acesso negado'], 403);
    }

    $d = $request->all();
    $valor = (float) ($d['valor_aplicado'] ?? $d['valor_inicial'] ?? 0);
    $aporte = (float) ($d['aporte_mensal'] ?? 0);
    $prazo = (int) ($d['prazo_meses'] ?? 0);
    $taxaAnual = isset($d['taxa_anual']) && $d['taxa_anual'] !== '' ? (float) $d['taxa_anual'] : null;
    $taxaMensal = isset($d['taxa_mensal']) && $d['taxa_mensal'] !== '' ? (float) $d['taxa_mensal'] : null;
    $tipo = (string) ($d['tipo_investimento'] ?? '');
    $objetivo = (string) ($d['objetivo'] ?? '');

    $resultado = InvestimentoCalculo::simular($valor, $aporte, $prazo, $taxaAnual, $taxaMensal);
    $liquidez = $tipo ? $invResolverLiquidez($tipo) : null;

    $avisos = [];
    if ($objetivo === 'emergencia' && $tipo && ! in_array($tipo, InvestimentoCalculo::TIPOS_ALTA_LIQUIDEZ, true)) {
        $avisos[] = 'Para reserva de emergência, prefira investimentos com alta liquidez (Tesouro Selic, CDB liquidez diária ou Fundo DI).';
    }

    return $invJson(array_merge($resultado, [
        'tipo_investimento' => $tipo,
        'liquidez' => $liquidez,
        'avisos' => $avisos,
        'tipos_sugeridos' => $objetivo
            ? array_keys(InvestimentoCalculo::tiposPermitidosParaObjetivo($objetivo))
            : array_keys(InvestimentoCalculo::TIPOS),
    ]));
});

// Reservas CRUD
Route::get('/investimento/reservas', function (Request $request) use ($invAuth, $podeInvestimento, $invJson, $invAplicarFiltroUnidade, $invMapReserva) {
    $u = $invAuth($request);
    if (! $podeInvestimento($u, 'investimentoReservas')) {
        return $invJson(['error' => 'Acesso negado'], 403);
    }
    if (! Schema::hasTable('investimento_reservas')) {
        return $invJson([]);
    }

    $q = DB::table('investimento_reservas as r')
        ->leftJoin('unidades as u', 'r.unidade_id', '=', 'u.id')
        ->select('r.*', 'u.nome as unidade_nome')
        ->orderByDesc('r.id');
    $invAplicarFiltroUnidade($q, $request);
    if ($request->filled('objetivo')) {
        $q->where('r.objetivo', $request->query('objetivo'));
    }
    if ($request->query('ativo') !== null && $request->query('ativo') !== '') {
        $q->where('r.ativo', (int) $request->query('ativo') ? 1 : 0);
    }

    return $invJson($q->get()->map($invMapReserva));
});

Route::post('/investimento/reservas', function (Request $request) use ($invAuth, $podeInvestimento, $invJson, $invMapReserva) {
    $u = $invAuth($request);
    if (! $podeInvestimento($u, 'investimentoReservas')) {
        return $invJson(['error' => 'Acesso negado'], 403);
    }
    if (! Schema::hasTable('investimento_reservas')) {
        return $invJson(['error' => 'Tabela não disponível. Execute as migrations.'], 503);
    }

    $d = $request->all();
    $objetivo = (string) ($d['objetivo'] ?? '');
    if (! isset(InvestimentoCalculo::OBJETIVOS[$objetivo])) {
        return $invJson(['error' => 'Objetivo inválido.'], 422);
    }

    $id = DB::table('investimento_reservas')->insertGetId([
        'unidade_id' => ! empty($d['unidade_id']) ? (int) $d['unidade_id'] : null,
        'objetivo' => $objetivo,
        'valor_inicial' => (float) ($d['valor_inicial'] ?? 0),
        'aporte_mensal' => (float) ($d['aporte_mensal'] ?? 0),
        'prazo_meses' => ! empty($d['prazo_meses']) ? (int) $d['prazo_meses'] : null,
        'data_alvo' => ! empty($d['data_alvo']) ? $d['data_alvo'] : null,
        'observacoes' => $d['observacoes'] ?? null,
        'ativo' => isset($d['ativo']) ? (int) (bool) $d['ativo'] : 1,
        'usuario_id' => $u->id ?? null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $row = DB::table('investimento_reservas as r')
        ->leftJoin('unidades as u', 'r.unidade_id', '=', 'u.id')
        ->where('r.id', $id)
        ->select('r.*', 'u.nome as unidade_nome')
        ->first();

    return $invJson($invMapReserva($row), 201);
});

Route::put('/investimento/reservas/{id}', function (Request $request, $id) use ($invAuth, $podeInvestimento, $invJson, $invMapReserva) {
    $u = $invAuth($request);
    if (! $podeInvestimento($u, 'investimentoReservas')) {
        return $invJson(['error' => 'Acesso negado'], 403);
    }

    $ex = DB::table('investimento_reservas')->where('id', (int) $id)->first();
    if (! $ex) {
        return $invJson(['error' => 'Reserva não encontrada.'], 404);
    }

    $d = $request->all();
    $up = ['updated_at' => now()];
    foreach (['unidade_id', 'valor_inicial', 'aporte_mensal', 'prazo_meses', 'data_alvo', 'observacoes'] as $campo) {
        if (array_key_exists($campo, $d)) {
            $up[$campo] = in_array($campo, ['unidade_id', 'prazo_meses'], true) && $d[$campo] !== '' && $d[$campo] !== null
                ? (int) $d[$campo]
                : (in_array($campo, ['valor_inicial', 'aporte_mensal'], true) ? (float) $d[$campo] : $d[$campo]);
        }
    }
    if (isset($d['objetivo']) && isset(InvestimentoCalculo::OBJETIVOS[$d['objetivo']])) {
        $up['objetivo'] = $d['objetivo'];
    }
    if (array_key_exists('ativo', $d)) {
        $up['ativo'] = (int) (bool) $d['ativo'];
    }

    DB::table('investimento_reservas')->where('id', (int) $id)->update($up);
    $row = DB::table('investimento_reservas as r')
        ->leftJoin('unidades as u', 'r.unidade_id', '=', 'u.id')
        ->where('r.id', (int) $id)
        ->select('r.*', 'u.nome as unidade_nome')
        ->first();

    return $invJson($invMapReserva($row));
});

Route::delete('/investimento/reservas/{id}', function (Request $request, $id) use ($invAuth, $podeInvestimento, $invJson) {
    $u = $invAuth($request);
    if (! $podeInvestimento($u, 'investimentoReservas')) {
        return $invJson(['error' => 'Acesso negado'], 403);
    }

    $n = DB::table('investimento_reservas')->where('id', (int) $id)->delete();
    if (! $n) {
        return $invJson(['error' => 'Reserva não encontrada.'], 404);
    }

    return $invJson(['ok' => true]);
});

// Carteira CRUD
Route::get('/investimento/carteira', function (Request $request) use ($invAuth, $podeInvestimento, $invJson, $invAplicarFiltroUnidade, $invMapCarteira) {
    $u = $invAuth($request);
    if (! $podeInvestimento($u, 'investimentoCarteira')) {
        return $invJson(['error' => 'Acesso negado'], 403);
    }
    if (! Schema::hasTable('investimento_carteira')) {
        return $invJson([]);
    }

    $q = DB::table('investimento_carteira as c')
        ->leftJoin('unidades as u', 'c.unidade_id', '=', 'u.id')
        ->leftJoin('investimento_reservas as r', 'c.reserva_id', '=', 'r.id')
        ->select('c.*', 'u.nome as unidade_nome', 'r.objetivo')
        ->orderByDesc('c.data_compra');
    $invAplicarFiltroUnidade($q, $request, 'c');
    if ($request->filled('status')) {
        $q->where('c.status', $request->query('status'));
    }
    if ($request->filled('reserva_id')) {
        $q->where('c.reserva_id', (int) $request->query('reserva_id'));
    }

    return $invJson($q->get()->map($invMapCarteira));
});

Route::post('/investimento/carteira', function (Request $request) use ($invAuth, $podeInvestimento, $invJson, $invMapCarteira, $invResolverLiquidez) {
    $u = $invAuth($request);
    if (! $podeInvestimento($u, 'investimentoCarteira')) {
        return $invJson(['error' => 'Acesso negado'], 403);
    }
    if (! Schema::hasTable('investimento_carteira')) {
        return $invJson(['error' => 'Tabela não disponível. Execute as migrations.'], 503);
    }

    $d = $request->all();
    $tipo = (string) ($d['tipo_investimento'] ?? '');
    if (! isset(InvestimentoCalculo::TIPOS[$tipo])) {
        return $invJson(['error' => 'Tipo de investimento inválido.'], 422);
    }

    $reservaId = ! empty($d['reserva_id']) ? (int) $d['reserva_id'] : null;
    if ($reservaId) {
        $reserva = DB::table('investimento_reservas')->where('id', $reservaId)->first();
        if ($reserva && $reserva->objetivo === 'emergencia' && ! in_array($tipo, InvestimentoCalculo::TIPOS_ALTA_LIQUIDEZ, true)) {
            return $invJson(['error' => 'Reserva de emergência exige investimento com alta liquidez.'], 422);
        }
    }

    $liquidez = $d['liquidez'] ?? $invResolverLiquidez($tipo);
    $id = DB::table('investimento_carteira')->insertGetId([
        'unidade_id' => ! empty($d['unidade_id']) ? (int) $d['unidade_id'] : null,
        'data_compra' => $d['data_compra'] ?? date('Y-m-d'),
        'instituicao' => trim((string) ($d['instituicao'] ?? '')),
        'tipo_investimento' => $tipo,
        'valor_aplicado' => (float) ($d['valor_aplicado'] ?? 0),
        'taxa_contratada' => isset($d['taxa_contratada']) && $d['taxa_contratada'] !== '' ? (float) $d['taxa_contratada'] : null,
        'taxa_mensal' => isset($d['taxa_mensal']) && $d['taxa_mensal'] !== '' ? (float) $d['taxa_mensal'] : null,
        'liquidez' => $liquidez,
        'vencimento' => ! empty($d['vencimento']) ? $d['vencimento'] : null,
        'reserva_id' => $reservaId,
        'status' => $d['status'] ?? 'ativo',
        'observacoes' => $d['observacoes'] ?? null,
        'usuario_id' => $u->id ?? null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $row = DB::table('investimento_carteira as c')
        ->leftJoin('unidades as u', 'c.unidade_id', '=', 'u.id')
        ->leftJoin('investimento_reservas as r', 'c.reserva_id', '=', 'r.id')
        ->where('c.id', $id)
        ->select('c.*', 'u.nome as unidade_nome', 'r.objetivo')
        ->first();

    return $invJson($invMapCarteira($row), 201);
});

Route::put('/investimento/carteira/{id}', function (Request $request, $id) use ($invAuth, $podeInvestimento, $invJson, $invMapCarteira, $invResolverLiquidez) {
    $u = $invAuth($request);
    if (! $podeInvestimento($u, 'investimentoCarteira')) {
        return $invJson(['error' => 'Acesso negado'], 403);
    }

    $ex = DB::table('investimento_carteira')->where('id', (int) $id)->first();
    if (! $ex) {
        return $invJson(['error' => 'Registro não encontrado.'], 404);
    }

    $d = $request->all();
    $up = ['updated_at' => now()];
    foreach (['unidade_id', 'data_compra', 'instituicao', 'valor_aplicado', 'taxa_contratada', 'taxa_mensal', 'vencimento', 'reserva_id', 'status', 'observacoes'] as $campo) {
        if (array_key_exists($campo, $d)) {
            $up[$campo] = in_array($campo, ['unidade_id', 'reserva_id'], true) && $d[$campo] !== '' && $d[$campo] !== null
                ? (int) $d[$campo]
                : (in_array($campo, ['valor_aplicado', 'taxa_contratada', 'taxa_mensal'], true) ? (float) $d[$campo] : $d[$campo]);
        }
    }
    if (isset($d['tipo_investimento']) && isset(InvestimentoCalculo::TIPOS[$d['tipo_investimento']])) {
        $up['tipo_investimento'] = $d['tipo_investimento'];
        $up['liquidez'] = $d['liquidez'] ?? $invResolverLiquidez($d['tipo_investimento']);
    }

    DB::table('investimento_carteira')->where('id', (int) $id)->update($up);
    $row = DB::table('investimento_carteira as c')
        ->leftJoin('unidades as u', 'c.unidade_id', '=', 'u.id')
        ->leftJoin('investimento_reservas as r', 'c.reserva_id', '=', 'r.id')
        ->where('c.id', (int) $id)
        ->select('c.*', 'u.nome as unidade_nome', 'r.objetivo')
        ->first();

    return $invJson($invMapCarteira($row));
});

Route::delete('/investimento/carteira/{id}', function (Request $request, $id) use ($invAuth, $podeInvestimento, $invJson) {
    $u = $invAuth($request);
    if (! $podeInvestimento($u, 'investimentoCarteira')) {
        return $invJson(['error' => 'Acesso negado'], 403);
    }

    $n = DB::table('investimento_carteira')->where('id', (int) $id)->delete();
    if (! $n) {
        return $invJson(['error' => 'Registro não encontrado.'], 404);
    }

    return $invJson(['ok' => true]);
});

// Resgates CRUD
Route::get('/investimento/resgates', function (Request $request) use ($invAuth, $podeInvestimento, $invJson, $invMapResgate) {
    $u = $invAuth($request);
    if (! $podeInvestimento($u, 'investimentoResgates')) {
        return $invJson(['error' => 'Acesso negado'], 403);
    }
    if (! Schema::hasTable('investimento_resgates')) {
        return $invJson([]);
    }

    $q = DB::table('investimento_resgates as g')
        ->join('investimento_carteira as c', 'g.carteira_id', '=', 'c.id')
        ->leftJoin('unidades as u', 'c.unidade_id', '=', 'u.id')
        ->select('g.*', 'c.instituicao', 'c.tipo_investimento', 'u.nome as unidade_nome')
        ->orderByDesc('g.data_resgate');
    if ($request->filled('unidade_id')) {
        $q->where('c.unidade_id', (int) $request->query('unidade_id'));
    }

    return $invJson($q->get()->map($invMapResgate));
});

Route::post('/investimento/resgates', function (Request $request) use ($invAuth, $podeInvestimento, $invJson, $invMapResgate) {
    $u = $invAuth($request);
    if (! $podeInvestimento($u, 'investimentoResgates')) {
        return $invJson(['error' => 'Acesso negado'], 403);
    }
    if (! Schema::hasTable('investimento_resgates')) {
        return $invJson(['error' => 'Tabela não disponível. Execute as migrations.'], 503);
    }

    $d = $request->all();
    $carteiraId = (int) ($d['carteira_id'] ?? 0);
    $carteira = DB::table('investimento_carteira')->where('id', $carteiraId)->first();
    if (! $carteira) {
        return $invJson(['error' => 'Investimento não encontrado na carteira.'], 404);
    }

    $valorResgatado = (float) ($d['valor_resgatado'] ?? 0);
    $valorBruto = isset($d['valor_bruto']) ? (float) $d['valor_bruto'] : $valorResgatado;
    $imposto = (float) ($d['imposto'] ?? 0);
    $valorLiquido = isset($d['valor_liquido']) ? (float) $d['valor_liquido'] : ($valorBruto - $imposto);

    $id = DB::table('investimento_resgates')->insertGetId([
        'carteira_id' => $carteiraId,
        'data_resgate' => $d['data_resgate'] ?? date('Y-m-d'),
        'valor_resgatado' => $valorResgatado,
        'valor_bruto' => $valorBruto,
        'imposto' => $imposto,
        'valor_liquido' => $valorLiquido,
        'observacoes' => $d['observacoes'] ?? null,
        'usuario_id' => $u->id ?? null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Marca carteira como resgatada se valor total
    if ($valorResgatado >= (float) $carteira->valor_aplicado * 0.99) {
        DB::table('investimento_carteira')->where('id', $carteiraId)->update([
            'status' => 'resgatado',
            'updated_at' => now(),
        ]);
    }

    $row = DB::table('investimento_resgates as g')
        ->join('investimento_carteira as c', 'g.carteira_id', '=', 'c.id')
        ->leftJoin('unidades as u', 'c.unidade_id', '=', 'u.id')
        ->where('g.id', $id)
        ->select('g.*', 'c.instituicao', 'c.tipo_investimento', 'u.nome as unidade_nome')
        ->first();

    return $invJson($invMapResgate($row), 201);
});

Route::delete('/investimento/resgates/{id}', function (Request $request, $id) use ($invAuth, $podeInvestimento, $invJson) {
    $u = $invAuth($request);
    if (! $podeInvestimento($u, 'investimentoResgates')) {
        return $invJson(['error' => 'Acesso negado'], 403);
    }

    $n = DB::table('investimento_resgates')->where('id', (int) $id)->delete();
    if (! $n) {
        return $invJson(['error' => 'Resgate não encontrado.'], 404);
    }

    return $invJson(['ok' => true]);
});

// Relatórios consolidados
Route::get('/investimento/relatorios', function (Request $request) use ($invAuth, $podeInvestimento, $invJson, $invObjetivoLabel) {
    $u = $invAuth($request);
    if (! $podeInvestimento($u, 'investimentoRelatorios')) {
        return $invJson(['error' => 'Acesso negado'], 403);
    }

    $unidadeId = $request->query('unidade_id');
    $totalReservado = 0.0;
    $reservaPorObjetivo = [];
    if (Schema::hasTable('investimento_reservas')) {
        $q = DB::table('investimento_reservas')->where('ativo', 1);
        if ($unidadeId) {
            $q->where('unidade_id', (int) $unidadeId);
        }
        foreach ($q->get() as $r) {
            $totalReservado += (float) $r->valor_inicial;
            $obj = $r->objetivo;
            if (! isset($reservaPorObjetivo[$obj])) {
                $reservaPorObjetivo[$obj] = ['objetivo' => $obj, 'label' => $invObjetivoLabel($obj), 'total' => 0];
            }
            $reservaPorObjetivo[$obj]['total'] += (float) $r->valor_inicial;
        }
    }

    $totalAplicado = 0.0;
    $rendimentoEstimado = 0.0;
    $proximosVencimentos = [];
    if (Schema::hasTable('investimento_carteira')) {
        $q = DB::table('investimento_carteira as c')
            ->leftJoin('unidades as u', 'c.unidade_id', '=', 'u.id')
            ->where('c.status', 'ativo');
        if ($unidadeId) {
            $q->where('c.unidade_id', (int) $unidadeId);
        }
        foreach ($q->select('c.*', 'u.nome as unidade_nome')->get() as $c) {
            $totalAplicado += (float) $c->valor_aplicado;
            $est = InvestimentoCalculo::estimarRendimentoCarteira(
                (float) $c->valor_aplicado,
                $c->taxa_contratada !== null ? (float) $c->taxa_contratada : null,
                $c->data_compra
            );
            $rendimentoEstimado += $est['rendimento_liquido'];
            if ($c->vencimento) {
                $proximosVencimentos[] = [
                    'instituicao' => $c->instituicao,
                    'vencimento' => $c->vencimento,
                    'valor_aplicado' => (float) $c->valor_aplicado,
                    'unidade_nome' => $c->unidade_nome,
                ];
            }
        }
        usort($proximosVencimentos, fn ($a, $b) => strcmp($a['vencimento'], $b['vencimento']));
    }

    $resgatesRealizados = [];
    if (Schema::hasTable('investimento_resgates')) {
        $q = DB::table('investimento_resgates as g')
            ->join('investimento_carteira as c', 'g.carteira_id', '=', 'c.id')
            ->leftJoin('unidades as u', 'c.unidade_id', '=', 'u.id')
            ->select('g.*', 'c.instituicao', 'u.nome as unidade_nome')
            ->orderByDesc('g.data_resgate')
            ->limit(50);
        if ($unidadeId) {
            $q->where('c.unidade_id', (int) $unidadeId);
        }
        $resgatesRealizados = $q->get()->map(fn ($r) => [
            'data_resgate' => $r->data_resgate,
            'instituicao' => $r->instituicao,
            'valor_liquido' => (float) ($r->valor_liquido ?? 0),
            'imposto' => (float) ($r->imposto ?? 0),
            'unidade_nome' => $r->unidade_nome,
        ])->all();
    }

    return $invJson([
        'total_reservado' => round($totalReservado, 2),
        'total_aplicado' => round($totalAplicado, 2),
        'rendimento_estimado' => round($rendimentoEstimado, 2),
        'reserva_por_objetivo' => array_values($reservaPorObjetivo),
        'proximos_vencimentos' => $proximosVencimentos,
        'resgates_realizados' => $resgatesRealizados,
    ]);
});
