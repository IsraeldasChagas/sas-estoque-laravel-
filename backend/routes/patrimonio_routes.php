<?php

/**
 * Rotas API — Módulo Patrimônio
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

$patrimonioCors = fn () => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');

$patrimonioModulos = [
    'patrimonioDashboard', 'patrimonios', 'patrimonioCategorias', 'patrimonioMovimentacoes',
    'patrimonioManutencoes', 'patrimonioInventario', 'patrimonioRelatorios', 'patrimonioConfiguracoes',
];

$patrimonioAuth = function (Request $req) {
    $uid = $req->header('X-Usuario-Id');

    return $uid ? DB::table('usuarios')->where('id', $uid)->where('ativo', 1)->first() : null;
};

$podePatrimonio = function ($u, ?string $modulo = null) use ($patrimonioModulos) {
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
        foreach ($patrimonioModulos as $m) {
            if (in_array($m, $pm, true)) {
                return true;
            }
        }

        return false;
    }

    return false;
};

$patJson = fn ($data, int $code = 200) => response()->json($data, $code)
    ->header('Access-Control-Allow-Origin', '*');

$patrimonioRegistrarHistorico = function (int $patrimonioId, string $acao, ?array $detalhes, ?int $usuarioId) {
    if (! Schema::hasTable('patrimonio_historico')) {
        return;
    }
    DB::table('patrimonio_historico')->insert([
        'patrimonio_id' => $patrimonioId,
        'acao' => $acao,
        'detalhes' => $detalhes ? json_encode($detalhes, JSON_UNESCAPED_UNICODE) : null,
        'usuario_id' => $usuarioId,
        'created_at' => now(),
    ]);
};

$patCalcularDepreciacao = static function (?float $valorCompra, ?string $dataCompra, ?int $vidaUtilMeses): array {
    $valorCompra = $valorCompra !== null ? (float) $valorCompra : 0.0;
    if ($valorCompra <= 0 || ! $vidaUtilMeses || $vidaUtilMeses < 1) {
        return ['valor_atual' => $valorCompra > 0 ? $valorCompra : null, 'depreciacao' => 0];
    }
    $mesesUso = 0;
    if ($dataCompra && preg_match('/^\d{4}-\d{2}-\d{2}/', $dataCompra)) {
        try {
            $inicio = new \DateTime(substr($dataCompra, 0, 10));
            $hoje = new \DateTime('today');
            $mesesUso = max(0, ($inicio->diff($hoje)->y * 12) + $inicio->diff($hoje)->m);
        } catch (\Throwable $e) {
            $mesesUso = 0;
        }
    }
    $depMensal = $valorCompra / $vidaUtilMeses;
    $depAcum = min($valorCompra, $depMensal * $mesesUso);
    $valorAtual = max(0, round($valorCompra - $depAcum, 2));

    return ['valor_atual' => $valorAtual, 'depreciacao' => round($depAcum, 2)];
};

$patrimonioGerarCodigo = function (?int $unidadeId) {
    $prefix = 'PAT-' . ($unidadeId ? str_pad((string) $unidadeId, 3, '0', STR_PAD_LEFT) : '000') . '-' . date('Y') . '-';
    $last = DB::table('patrimonios')->where('codigo', 'like', $prefix . '%')->orderByDesc('id')->value('codigo');
    $seq = 1;
    if ($last && preg_match('/-(\d+)$/', $last, $m)) {
        $seq = (int) $m[1] + 1;
    }

    return $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
};

$patrimonioMapPatrimonio = function ($r) {
    $dados = $r->dados_especificos ?? null;
    if (is_string($dados)) {
        $decoded = json_decode($dados, true);
        $dados = is_array($decoded) ? $decoded : null;
    }

    return [
        'id' => (int) $r->id,
        'codigo' => $r->codigo,
        'qr_token' => $r->qr_token,
        'nome' => $r->nome,
        'numero_serial' => $r->numero_serial,
        'categoria_id' => $r->categoria_id ? (int) $r->categoria_id : null,
        'categoria_nome' => $r->categoria_nome ?? null,
        'categoria_tipo_campos' => $r->tipo_campos ?? null,
        'marca' => $r->marca,
        'modelo' => $r->modelo,
        'cor' => $r->cor,
        'quantidade' => (int) ($r->quantidade ?? 1),
        'unidade_id' => $r->unidade_id ? (int) $r->unidade_id : null,
        'unidade_nome' => $r->unidade_nome ?? null,
        'setor' => $r->setor,
        'responsavel' => $r->responsavel,
        'funcionario_id' => $r->funcionario_id ? (int) $r->funcionario_id : null,
        'situacao' => $r->situacao,
        'valor_compra' => $r->valor_compra !== null ? (float) $r->valor_compra : null,
        'data_compra' => $r->data_compra,
        'vida_util_meses' => $r->vida_util_meses ? (int) $r->vida_util_meses : null,
        'valor_atual' => $r->valor_atual !== null ? (float) $r->valor_atual : null,
        'depreciacao' => $r->depreciacao !== null ? (float) $r->depreciacao : null,
        'fornecedor' => $r->fornecedor,
        'numero_nf' => $r->numero_nf,
        'dados_especificos' => $dados,
        'created_at' => $r->created_at,
        'updated_at' => $r->updated_at,
    ];
};

$patrimonioQueryBase = function () {
    return DB::table('patrimonios as p')
        ->leftJoin('patrimonio_categorias as c', 'p.categoria_id', '=', 'c.id')
        ->leftJoin('unidades as u', 'p.unidade_id', '=', 'u.id')
        ->select(
            'p.*',
            'c.nome as categoria_nome',
            'c.tipo_campos',
            'u.nome as unidade_nome'
        );
};

foreach ([
    '/patrimonio/dashboard',
    '/patrimonio/categorias',
    '/patrimonio/categorias/{id}',
    '/patrimonio/patrimonios',
    '/patrimonio/patrimonios/{id}',
    '/patrimonio/patrimonios/qr/{token}',
    '/patrimonio/patrimonios/{id}/movimentacoes',
    '/patrimonio/manutencoes',
    '/patrimonio/manutencoes/{id}',
    '/patrimonio/inventario',
    '/patrimonio/inventario/{id}',
    '/patrimonio/inventario/{id}/itens',
    '/patrimonio/inventario/itens/{itemId}',
    '/patrimonio/relatorios/resumo',
] as $optPath) {
    Route::options($optPath, $patrimonioCors);
}

// ——— Dashboard ———
Route::get('/patrimonio/dashboard', function (Request $request) use ($patrimonioAuth, $podePatrimonio, $patJson) {
    $u = $patrimonioAuth($request);
    if (! $podePatrimonio($u, 'patrimonioDashboard')) {
        return $patJson(['message' => 'Sem permissão'], 403);
    }
    if (! Schema::hasTable('patrimonios')) {
        return $patJson(['message' => 'Módulo patrimônio não instalado. Execute a migration.'], 503);
    }

    $q = DB::table('patrimonios as p')->leftJoin('patrimonio_categorias as c', 'p.categoria_id', '=', 'c.id');
    if ($request->filled('unidade_id')) {
        $q->where('p.unidade_id', (int) $request->query('unidade_id'));
    }

    $total = (clone $q)->count();
    $valorTotal = (float) ((clone $q)->sum('p.valor_atual') ?: 0);
    $emManutencao = (clone $q)->where('p.situacao', 'manutencao')->count();
    $baixados = (clone $q)->whereIn('p.situacao', ['baixado', 'vendido', 'quebrado'])->count();

    $porCategoria = (clone $q)
        ->select('c.nome as label', DB::raw('COUNT(*) as total'))
        ->groupBy('c.nome')
        ->orderByDesc('total')
        ->limit(12)
        ->get();

    $porUnidade = DB::table('patrimonios as p')
        ->leftJoin('unidades as u', 'p.unidade_id', '=', 'u.id')
        ->when($request->filled('unidade_id'), fn ($qq) => $qq->where('p.unidade_id', (int) $request->query('unidade_id')))
        ->select(DB::raw("COALESCE(u.nome, 'Sem unidade') as label"), DB::raw('COUNT(*) as total'))
        ->groupBy('u.nome')
        ->orderByDesc('total')
        ->get();

    $ultimasMov = Schema::hasTable('patrimonio_movimentacoes')
        ? DB::table('patrimonio_movimentacoes as m')
            ->join('patrimonios as p', 'm.patrimonio_id', '=', 'p.id')
            ->select('m.*', 'p.nome as patrimonio_nome', 'p.codigo')
            ->orderByDesc('m.created_at')
            ->limit(8)
            ->get()
        : collect();

    $alertasManut = Schema::hasTable('patrimonio_manutencoes')
        ? DB::table('patrimonio_manutencoes as mn')
            ->join('patrimonios as p', 'mn.patrimonio_id', '=', 'p.id')
            ->whereNotNull('mn.proxima_manutencao')
            ->where('mn.proxima_manutencao', '<=', now()->addDays(30)->toDateString())
            ->select('mn.*', 'p.nome as patrimonio_nome', 'p.codigo')
            ->orderBy('mn.proxima_manutencao')
            ->limit(10)
            ->get()
        : collect();

    $garantiasVencendo = collect();
    $limiteGarantia = now()->addDays(60)->toDateString();
    $qGarant = DB::table('patrimonios')
        ->select('codigo', 'nome', 'dados_especificos')
        ->where('situacao', 'ativo')
        ->whereNotNull('dados_especificos');
    if ($request->filled('unidade_id')) {
        $qGarant->where('unidade_id', (int) $request->query('unidade_id'));
    }
    foreach ($qGarant->orderByDesc('id')->limit(300)->get() as $patRow) {
        $dados = $patRow->dados_especificos ?? null;
        if (is_string($dados)) {
            $dados = json_decode($dados, true);
        }
        if (! is_array($dados)) {
            continue;
        }
        $venc = $dados['vencimento_ipva'] ?? $dados['vencimento_garantia'] ?? null;
        if ($venc && $venc <= $limiteGarantia) {
            $garantiasVencendo->push([
                'codigo' => $patRow->codigo,
                'nome' => $patRow->nome,
                'tipo_alerta' => isset($dados['vencimento_ipva']) ? 'IPVA / documento' : 'Garantia',
                'data_vencimento' => $venc,
            ]);
        }
    }

    return $patJson([
        'total_patrimonios' => $total,
        'valor_total_ativos' => $valorTotal,
        'em_manutencao' => $emManutencao,
        'baixados' => $baixados,
        'por_categoria' => $porCategoria,
        'por_unidade' => $porUnidade,
        'ultimas_movimentacoes' => $ultimasMov,
        'alertas_manutencao' => $alertasManut,
        'garantias_vencendo' => $garantiasVencendo->take(15)->values(),
    ]);
});

// ——— Categorias ———
Route::get('/patrimonio/categorias', function (Request $request) use ($patrimonioAuth, $podePatrimonio, $patJson) {
    $u = $patrimonioAuth($request);
    if (! $podePatrimonio($u, 'patrimonioCategorias')) {
        return $patJson(['message' => 'Sem permissão'], 403);
    }
    $rows = DB::table('patrimonio_categorias')->orderBy('ordem')->orderBy('nome')->get();

    return $patJson($rows);
});

Route::post('/patrimonio/categorias', function (Request $request) use ($patrimonioAuth, $podePatrimonio, $patJson) {
    $u = $patrimonioAuth($request);
    if (! $podePatrimonio($u, 'patrimonioCategorias')) {
        return $patJson(['message' => 'Sem permissão'], 403);
    }
    $nome = trim((string) $request->input('nome', ''));
    if ($nome === '') {
        return $patJson(['message' => 'Nome obrigatório'], 422);
    }
    $slug = Str::slug($nome);
    $id = DB::table('patrimonio_categorias')->insertGetId([
        'nome' => $nome,
        'slug' => $slug . '-' . time(),
        'icone' => $request->input('icone'),
        'ordem' => (int) $request->input('ordem', 50),
        'ativo' => $request->boolean('ativo', true),
        'tipo_campos' => $request->input('tipo_campos', 'geral'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $patJson(['message' => 'Categoria criada', 'id' => $id], 201);
});

Route::put('/patrimonio/categorias/{id}', function (Request $request, $id) use ($patrimonioAuth, $podePatrimonio, $patJson) {
    $u = $patrimonioAuth($request);
    if (! $podePatrimonio($u, 'patrimonioCategorias')) {
        return $patJson(['message' => 'Sem permissão'], 403);
    }
    DB::table('patrimonio_categorias')->where('id', $id)->update([
        'nome' => $request->input('nome'),
        'icone' => $request->input('icone'),
        'ordem' => $request->input('ordem'),
        'ativo' => $request->boolean('ativo', true),
        'tipo_campos' => $request->input('tipo_campos'),
        'updated_at' => now(),
    ]);

    return $patJson(['message' => 'Categoria atualizada']);
});

Route::delete('/patrimonio/categorias/{id}', function (Request $request, $id) use ($patrimonioAuth, $podePatrimonio, $patJson) {
    $u = $patrimonioAuth($request);
    if (! $podePatrimonio($u, 'patrimonioCategorias')) {
        return $patJson(['message' => 'Sem permissão'], 403);
    }
    $emUso = DB::table('patrimonios')->where('categoria_id', $id)->exists();
    if ($emUso) {
        return $patJson(['message' => 'Categoria em uso por patrimônios'], 409);
    }
    DB::table('patrimonio_categorias')->where('id', $id)->delete();

    return $patJson(['message' => 'Categoria excluída']);
});

// ——— Patrimônios ———
Route::get('/patrimonio/patrimonios', function (Request $request) use ($patrimonioAuth, $podePatrimonio, $patJson, $patrimonioQueryBase, $patrimonioMapPatrimonio) {
    $u = $patrimonioAuth($request);
    if (! $podePatrimonio($u, 'patrimonios')) {
        return $patJson(['message' => 'Sem permissão'], 403);
    }
    $q = $patrimonioQueryBase();
    if ($request->filled('unidade_id')) {
        $q->where('p.unidade_id', (int) $request->query('unidade_id'));
    }
    if ($request->filled('categoria_id')) {
        $q->where('p.categoria_id', (int) $request->query('categoria_id'));
    }
    if ($request->filled('situacao')) {
        $q->where('p.situacao', $request->query('situacao'));
    }
    if ($request->filled('setor')) {
        $setor = trim((string) $request->query('setor'));
        if ($setor !== '') {
            $q->where('p.setor', 'like', '%' . $setor . '%');
        }
    }
    if ($request->filled('busca')) {
        $b = '%' . $request->query('busca') . '%';
        $q->where(function ($qq) use ($b) {
            $qq->where('p.nome', 'like', $b)
                ->orWhere('p.codigo', 'like', $b)
                ->orWhere('p.numero_serial', 'like', $b);
        });
    }
    $rows = $q->orderByDesc('p.updated_at')->limit(500)->get();

    return $patJson($rows->map($patrimonioMapPatrimonio));
});

Route::get('/patrimonio/patrimonios/qr/{token}', function (Request $request, $token) use ($patrimonioAuth, $podePatrimonio, $patJson, $patrimonioQueryBase, $patrimonioMapPatrimonio) {
    $u = $patrimonioAuth($request);
    if (! $podePatrimonio($u)) {
        return $patJson(['message' => 'Sem permissão'], 403);
    }
    $r = $patrimonioQueryBase()->where('p.qr_token', $token)->first();
    if (! $r) {
        return $patJson(['message' => 'Patrimônio não encontrado'], 404);
    }
    $id = (int) $r->id;
    $mov = DB::table('patrimonio_movimentacoes')->where('patrimonio_id', $id)->orderByDesc('created_at')->limit(20)->get();
    $man = DB::table('patrimonio_manutencoes')->where('patrimonio_id', $id)->orderByDesc('data_manutencao')->limit(10)->get();
    $hist = DB::table('patrimonio_historico')->where('patrimonio_id', $id)->orderByDesc('created_at')->limit(30)->get();

    return $patJson([
        'patrimonio' => $patrimonioMapPatrimonio($r),
        'movimentacoes' => $mov,
        'manutencoes' => $man,
        'historico' => $hist,
    ]);
});

Route::get('/patrimonio/patrimonios/{id}', function (Request $request, $id) use ($patrimonioAuth, $podePatrimonio, $patJson, $patrimonioQueryBase, $patrimonioMapPatrimonio) {
    $u = $patrimonioAuth($request);
    if (! $podePatrimonio($u, 'patrimonios')) {
        return $patJson(['message' => 'Sem permissão'], 403);
    }
    $r = $patrimonioQueryBase()->where('p.id', $id)->first();
    if (! $r) {
        return $patJson(['message' => 'Não encontrado'], 404);
    }
    $fotos = DB::table('patrimonio_fotos')->where('patrimonio_id', $id)->orderBy('ordem')->get();
    $docs = DB::table('patrimonio_documentos')->where('patrimonio_id', $id)->orderByDesc('id')->get();

    return $patJson([
        'patrimonio' => $patrimonioMapPatrimonio($r),
        'fotos' => $fotos,
        'documentos' => $docs,
    ]);
});

Route::post('/patrimonio/patrimonios', function (Request $request) use (
    $patrimonioAuth, $podePatrimonio, $patJson, $patrimonioGerarCodigo, $patrimonioRegistrarHistorico, $patCalcularDepreciacao
) {
    $u = $patrimonioAuth($request);
    if (! $podePatrimonio($u, 'patrimonios')) {
        return $patJson(['message' => 'Sem permissão'], 403);
    }
    $nome = trim((string) $request->input('nome', ''));
    if ($nome === '') {
        return $patJson(['message' => 'Nome obrigatório'], 422);
    }
    $unidadeId = $request->filled('unidade_id') ? (int) $request->input('unidade_id') : null;
    $dadosEsp = $request->input('dados_especificos');
    if (is_string($dadosEsp)) {
        $dadosEsp = json_decode($dadosEsp, true);
    }
    $valorCompra = $request->input('valor_compra') !== null && $request->input('valor_compra') !== ''
        ? (float) $request->input('valor_compra') : null;
    $vidaUtil = $request->input('vida_util_meses') ? (int) $request->input('vida_util_meses') : null;
    $dataCompra = $request->input('data_compra') ?: null;
    $dep = $patCalcularDepreciacao($valorCompra, $dataCompra, $vidaUtil);
    $valorAtual = $request->input('valor_atual') !== null && $request->input('valor_atual') !== ''
        ? (float) $request->input('valor_atual') : $dep['valor_atual'];

    $id = DB::table('patrimonios')->insertGetId([
        'codigo' => $patrimonioGerarCodigo($unidadeId),
        'qr_token' => Str::random(32),
        'nome' => $nome,
        'numero_serial' => $request->input('numero_serial'),
        'categoria_id' => $request->input('categoria_id') ?: null,
        'marca' => $request->input('marca'),
        'modelo' => $request->input('modelo'),
        'cor' => $request->input('cor'),
        'quantidade' => max(1, (int) $request->input('quantidade', 1)),
        'unidade_id' => $unidadeId,
        'setor' => $request->input('setor'),
        'responsavel' => $request->input('responsavel'),
        'funcionario_id' => $request->input('funcionario_id') ?: null,
        'situacao' => $request->input('situacao', 'ativo'),
        'valor_compra' => $valorCompra,
        'data_compra' => $dataCompra,
        'vida_util_meses' => $vidaUtil,
        'valor_atual' => $valorAtual,
        'depreciacao' => $request->input('depreciacao') !== null && $request->input('depreciacao') !== ''
            ? (float) $request->input('depreciacao') : $dep['depreciacao'],
        'fornecedor' => $request->input('fornecedor'),
        'numero_nf' => $request->input('numero_nf'),
        'dados_especificos' => $dadosEsp ? json_encode($dadosEsp, JSON_UNESCAPED_UNICODE) : null,
        'usuario_id' => $u->id ?? null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $patrimonioRegistrarHistorico($id, 'cadastro', ['nome' => $nome], $u->id ?? null);

    $ordemFoto = 0;
    if ($request->hasFile('foto')) {
        $file = $request->file('foto');
        $path = $file->store('patrimonio/fotos', 'public');
        DB::table('patrimonio_fotos')->insert([
            'patrimonio_id' => $id,
            'path' => $path,
            'nome' => $file->getClientOriginalName(),
            'ordem' => $ordemFoto++,
            'created_at' => now(),
        ]);
    }
    if ($request->hasFile('fotos')) {
        foreach ($request->file('fotos') as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }
            $path = $file->store('patrimonio/fotos', 'public');
            DB::table('patrimonio_fotos')->insert([
                'patrimonio_id' => $id,
                'path' => $path,
                'nome' => $file->getClientOriginalName(),
                'ordem' => $ordemFoto++,
                'created_at' => now(),
            ]);
        }
    }
    if ($request->hasFile('documento')) {
        $file = $request->file('documento');
        $path = $file->store('patrimonio/documentos', 'public');
        DB::table('patrimonio_documentos')->insert([
            'patrimonio_id' => $id,
            'tipo' => $request->input('documento_tipo', 'outro'),
            'nome' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'tamanho' => $file->getSize(),
            'created_at' => now(),
        ]);
    }
    $tipoDocLote = $request->input('documento_tipo', 'outro');
    if ($request->hasFile('documentos')) {
        foreach ($request->file('documentos') as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }
            $path = $file->store('patrimonio/documentos', 'public');
            DB::table('patrimonio_documentos')->insert([
                'patrimonio_id' => $id,
                'tipo' => $tipoDocLote,
                'nome' => $file->getClientOriginalName(),
                'path' => $path,
                'mime' => $file->getClientMimeType(),
                'tamanho' => $file->getSize(),
                'created_at' => now(),
            ]);
        }
    }

    return $patJson(['message' => 'Patrimônio cadastrado', 'id' => $id], 201);
});

Route::post('/patrimonio/patrimonios/{id}', function (Request $request, $id) use (
    $patrimonioAuth, $podePatrimonio, $patJson, $patrimonioRegistrarHistorico, $patCalcularDepreciacao
) {
    $u = $patrimonioAuth($request);
    if (! $podePatrimonio($u, 'patrimonios')) {
        return $patJson(['message' => 'Sem permissão'], 403);
    }
    $row = DB::table('patrimonios')->where('id', $id)->first();
    if (! $row) {
        return $patJson(['message' => 'Não encontrado'], 404);
    }
    $dadosEsp = $request->input('dados_especificos');
    if (is_string($dadosEsp)) {
        $dadosEsp = json_decode($dadosEsp, true);
    }
    $valorCompra = $request->has('valor_compra') ? (float) $request->input('valor_compra') : (float) ($row->valor_compra ?? 0);
    $vidaUtil = $request->has('vida_util_meses') ? (int) $request->input('vida_util_meses') : (int) ($row->vida_util_meses ?? 0);
    $dataCompra = $request->input('data_compra') ?: $row->data_compra;
    $dep = $patCalcularDepreciacao($valorCompra ?: null, $dataCompra, $vidaUtil ?: null);
    $data = array_filter([
        'nome' => $request->input('nome'),
        'numero_serial' => $request->input('numero_serial'),
        'categoria_id' => $request->input('categoria_id'),
        'marca' => $request->input('marca'),
        'modelo' => $request->input('modelo'),
        'cor' => $request->input('cor'),
        'quantidade' => $request->input('quantidade'),
        'unidade_id' => $request->input('unidade_id'),
        'setor' => $request->input('setor'),
        'responsavel' => $request->input('responsavel'),
        'situacao' => $request->input('situacao'),
        'valor_compra' => $request->has('valor_compra') ? $request->input('valor_compra') : null,
        'data_compra' => $request->input('data_compra'),
        'vida_util_meses' => $request->has('vida_util_meses') ? $request->input('vida_util_meses') : null,
        'valor_atual' => $request->has('valor_atual') ? $request->input('valor_atual') : $dep['valor_atual'],
        'depreciacao' => $request->has('depreciacao') ? $request->input('depreciacao') : $dep['depreciacao'],
        'fornecedor' => $request->input('fornecedor'),
        'numero_nf' => $request->input('numero_nf'),
        'dados_especificos' => $dadosEsp ? json_encode($dadosEsp, JSON_UNESCAPED_UNICODE) : null,
        'updated_at' => now(),
    ], fn ($v) => $v !== null && $v !== '');

    DB::table('patrimonios')->where('id', $id)->update($data);
    $patrimonioRegistrarHistorico((int) $id, 'edicao', $data, $u->id ?? null);

    $ordemFoto = (int) DB::table('patrimonio_fotos')->where('patrimonio_id', $id)->max('ordem');
    if ($request->hasFile('foto')) {
        $file = $request->file('foto');
        $path = $file->store('patrimonio/fotos', 'public');
        DB::table('patrimonio_fotos')->insert([
            'patrimonio_id' => $id,
            'path' => $path,
            'nome' => $file->getClientOriginalName(),
            'ordem' => ++$ordemFoto,
            'created_at' => now(),
        ]);
    }
    if ($request->hasFile('fotos')) {
        foreach ($request->file('fotos') as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }
            $path = $file->store('patrimonio/fotos', 'public');
            DB::table('patrimonio_fotos')->insert([
                'patrimonio_id' => $id,
                'path' => $path,
                'nome' => $file->getClientOriginalName(),
                'ordem' => ++$ordemFoto,
                'created_at' => now(),
            ]);
        }
    }
    if ($request->hasFile('documento')) {
        $file = $request->file('documento');
        $path = $file->store('patrimonio/documentos', 'public');
        DB::table('patrimonio_documentos')->insert([
            'patrimonio_id' => $id,
            'tipo' => $request->input('documento_tipo', 'outro'),
            'nome' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'tamanho' => $file->getSize(),
            'created_at' => now(),
        ]);
    }
    $tipoDocLote = $request->input('documento_tipo', 'outro');
    if ($request->hasFile('documentos')) {
        foreach ($request->file('documentos') as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }
            $path = $file->store('patrimonio/documentos', 'public');
            DB::table('patrimonio_documentos')->insert([
                'patrimonio_id' => $id,
                'tipo' => $tipoDocLote,
                'nome' => $file->getClientOriginalName(),
                'path' => $path,
                'mime' => $file->getClientMimeType(),
                'tamanho' => $file->getSize(),
                'created_at' => now(),
            ]);
        }
    }

    return $patJson(['message' => 'Patrimônio atualizado']);
});

Route::delete('/patrimonio/patrimonios/{id}', function (Request $request, $id) use ($patrimonioAuth, $podePatrimonio, $patJson, $patrimonioRegistrarHistorico) {
    $u = $patrimonioAuth($request);
    if (! $podePatrimonio($u, 'patrimonios')) {
        return $patJson(['message' => 'Sem permissão'], 403);
    }
    DB::table('patrimonios')->where('id', $id)->delete();
    $patrimonioRegistrarHistorico((int) $id, 'exclusao', null, $u->id ?? null);

    return $patJson(['message' => 'Patrimônio excluído']);
});

// ——— Movimentações ———
Route::get('/patrimonio/patrimonios/{id}/movimentacoes', function (Request $request, $id) use ($patrimonioAuth, $podePatrimonio, $patJson) {
    $u = $patrimonioAuth($request);
    if (! $podePatrimonio($u, 'patrimonioMovimentacoes')) {
        return $patJson(['message' => 'Sem permissão'], 403);
    }
    $rows = DB::table('patrimonio_movimentacoes')->where('patrimonio_id', $id)->orderByDesc('created_at')->get();

    return $patJson($rows);
});

Route::post('/patrimonio/patrimonios/{id}/movimentacoes', function (Request $request, $id) use ($patrimonioAuth, $podePatrimonio, $patJson, $patrimonioRegistrarHistorico) {
    $u = $patrimonioAuth($request);
    if (! $podePatrimonio($u, 'patrimonioMovimentacoes')) {
        return $patJson(['message' => 'Sem permissão'], 403);
    }
    $tipo = $request->input('tipo', 'transferencia');
    $mid = DB::table('patrimonio_movimentacoes')->insertGetId([
        'patrimonio_id' => $id,
        'tipo' => $tipo,
        'unidade_origem_id' => $request->input('unidade_origem_id'),
        'unidade_destino_id' => $request->input('unidade_destino_id'),
        'responsavel_anterior' => $request->input('responsavel_anterior'),
        'responsavel_novo' => $request->input('responsavel_novo'),
        'usuario_id' => $u->id ?? null,
        'observacao' => $request->input('observacao'),
        'created_at' => now(),
    ]);

    $upd = [];
    if ($request->filled('unidade_destino_id')) {
        $upd['unidade_id'] = (int) $request->input('unidade_destino_id');
    }
    if ($request->filled('responsavel_novo')) {
        $upd['responsavel'] = $request->input('responsavel_novo');
    }
    if ($tipo === 'baixa') {
        $upd['situacao'] = 'baixado';
    }
    if (count($upd)) {
        $upd['updated_at'] = now();
        DB::table('patrimonios')->where('id', $id)->update($upd);
    }

    $patrimonioRegistrarHistorico((int) $id, 'movimentacao', ['tipo' => $tipo, 'movimentacao_id' => $mid], $u->id ?? null);

    return $patJson(['message' => 'Movimentação registrada', 'id' => $mid], 201);
});

Route::get('/patrimonio/movimentacoes', function (Request $request) use ($patrimonioAuth, $podePatrimonio, $patJson) {
    $u = $patrimonioAuth($request);
    if (! $podePatrimonio($u, 'patrimonioMovimentacoes')) {
        return $patJson(['message' => 'Sem permissão'], 403);
    }
    $q = DB::table('patrimonio_movimentacoes as m')
        ->join('patrimonios as p', 'm.patrimonio_id', '=', 'p.id')
        ->leftJoin('unidades as uo', 'm.unidade_origem_id', '=', 'uo.id')
        ->leftJoin('unidades as ud', 'm.unidade_destino_id', '=', 'ud.id')
        ->select('m.*', 'p.nome as patrimonio_nome', 'p.codigo', 'uo.nome as unidade_origem_nome', 'ud.nome as unidade_destino_nome');
    if ($request->filled('patrimonio_id')) {
        $q->where('m.patrimonio_id', (int) $request->query('patrimonio_id'));
    }
    $rows = $q->orderByDesc('m.created_at')->limit(200)->get();

    return $patJson($rows);
});

// ——— Manutenções ———
Route::get('/patrimonio/manutencoes', function (Request $request) use ($patrimonioAuth, $podePatrimonio, $patJson) {
    $u = $patrimonioAuth($request);
    if (! $podePatrimonio($u, 'patrimonioManutencoes')) {
        return $patJson(['message' => 'Sem permissão'], 403);
    }
    $q = DB::table('patrimonio_manutencoes as mn')
        ->join('patrimonios as p', 'mn.patrimonio_id', '=', 'p.id')
        ->select('mn.*', 'p.nome as patrimonio_nome', 'p.codigo');
    if ($request->filled('patrimonio_id')) {
        $q->where('mn.patrimonio_id', (int) $request->query('patrimonio_id'));
    }
    $rows = $q->orderByDesc('mn.data_manutencao')->limit(200)->get();

    return $patJson($rows);
});

Route::post('/patrimonio/manutencoes', function (Request $request) use ($patrimonioAuth, $podePatrimonio, $patJson, $patrimonioRegistrarHistorico) {
    $u = $patrimonioAuth($request);
    if (! $podePatrimonio($u, 'patrimonioManutencoes')) {
        return $patJson(['message' => 'Sem permissão'], 403);
    }
    $pid = (int) $request->input('patrimonio_id');
    if ($pid < 1) {
        return $patJson(['message' => 'Patrimônio obrigatório'], 422);
    }
    $anexoPath = null;
    $anexoNome = null;
    if ($request->hasFile('anexo')) {
        $file = $request->file('anexo');
        $anexoPath = $file->store('patrimonio/manutencoes', 'public');
        $anexoNome = $file->getClientOriginalName();
    }
    $id = DB::table('patrimonio_manutencoes')->insertGetId([
        'patrimonio_id' => $pid,
        'tipo_manutencao' => $request->input('tipo_manutencao', 'preventiva'),
        'descricao' => $request->input('descricao'),
        'tecnico' => $request->input('tecnico'),
        'custo' => $request->input('custo'),
        'data_manutencao' => $request->input('data_manutencao', now()->toDateString()),
        'proxima_manutencao' => $request->input('proxima_manutencao'),
        'anexo_path' => $anexoPath,
        'anexo_nome' => $anexoNome,
        'usuario_id' => $u->id ?? null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('patrimonios')->where('id', $pid)->update(['situacao' => 'manutencao', 'updated_at' => now()]);
    $patrimonioRegistrarHistorico($pid, 'manutencao', ['manutencao_id' => $id], $u->id ?? null);

    return $patJson(['message' => 'Manutenção registrada', 'id' => $id], 201);
});

// ——— Inventário ———
Route::get('/patrimonio/inventario', function (Request $request) use ($patrimonioAuth, $podePatrimonio, $patJson) {
    $u = $patrimonioAuth($request);
    if (! $podePatrimonio($u, 'patrimonioInventario')) {
        return $patJson(['message' => 'Sem permissão'], 403);
    }
    $rows = DB::table('patrimonio_inventario as i')
        ->leftJoin('unidades as u', 'i.unidade_id', '=', 'u.id')
        ->select('i.*', 'u.nome as unidade_nome')
        ->orderByDesc('i.id')
        ->limit(100)
        ->get();

    return $patJson($rows);
});

Route::post('/patrimonio/inventario', function (Request $request) use ($patrimonioAuth, $podePatrimonio, $patJson) {
    $u = $patrimonioAuth($request);
    if (! $podePatrimonio($u, 'patrimonioInventario')) {
        return $patJson(['message' => 'Sem permissão'], 403);
    }
    $titulo = trim((string) $request->input('titulo', 'Inventário ' . date('d/m/Y')));
    $unidadeId = $request->filled('unidade_id') ? (int) $request->input('unidade_id') : null;
    $id = DB::table('patrimonio_inventario')->insertGetId([
        'titulo' => $titulo,
        'unidade_id' => $unidadeId,
        'status' => 'aberto',
        'data_inicio' => now()->toDateString(),
        'usuario_id' => $u->id ?? null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $patQ = DB::table('patrimonios')->where('situacao', 'ativo');
    if ($unidadeId) {
        $patQ->where('unidade_id', $unidadeId);
    }
    foreach ($patQ->get() as $p) {
        DB::table('patrimonio_inventario_itens')->insert([
            'inventario_id' => $id,
            'patrimonio_id' => $p->id,
            'localizacao' => $p->setor,
            'qtd_sistema' => $p->quantidade ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return $patJson(['message' => 'Inventário iniciado', 'id' => $id], 201);
});

Route::get('/patrimonio/inventario/{id}/itens', function (Request $request, $id) use ($patrimonioAuth, $podePatrimonio, $patJson) {
    $u = $patrimonioAuth($request);
    if (! $podePatrimonio($u, 'patrimonioInventario')) {
        return $patJson(['message' => 'Sem permissão'], 403);
    }
    $rows = DB::table('patrimonio_inventario_itens as it')
        ->join('patrimonios as p', 'it.patrimonio_id', '=', 'p.id')
        ->where('it.inventario_id', $id)
        ->select('it.*', 'p.nome as patrimonio_nome', 'p.codigo')
        ->orderBy('p.nome')
        ->get();

    return $patJson($rows);
});

Route::post('/patrimonio/inventario/{id}/fechar', function (Request $request, $id) use ($patrimonioAuth, $podePatrimonio, $patJson) {
    $u = $patrimonioAuth($request);
    if (! $podePatrimonio($u, 'patrimonioInventario')) {
        return $patJson(['message' => 'Sem permissão'], 403);
    }
    DB::table('patrimonio_inventario')->where('id', $id)->update([
        'status' => 'fechado',
        'data_fim' => now()->toDateString(),
        'updated_at' => now(),
    ]);

    return $patJson(['message' => 'Inventário encerrado']);
});

Route::post('/patrimonio/inventario/itens/{itemId}/foto', function (Request $request, $itemId) use ($patrimonioAuth, $podePatrimonio, $patJson) {
    $u = $patrimonioAuth($request);
    if (! $podePatrimonio($u, 'patrimonioInventario')) {
        return $patJson(['message' => 'Sem permissão'], 403);
    }
    if (! $request->hasFile('foto')) {
        return $patJson(['message' => 'Envie uma foto'], 422);
    }
    $file = $request->file('foto');
    $path = $file->store('patrimonio/inventario', 'public');
    DB::table('patrimonio_inventario_itens')->where('id', $itemId)->update([
        'foto_path' => $path,
        'updated_at' => now(),
    ]);

    return $patJson(['message' => 'Foto salva', 'path' => $path]);
});

Route::get('/patrimonio/arquivos/{tipo}/{arquivoId}', function (Request $request, $tipo, $arquivoId) use ($patrimonioAuth, $podePatrimonio) {
    $u = $patrimonioAuth($request);
    if (! $podePatrimonio($u)) {
        return response('Sem permissão', 403);
    }
    if ($tipo === 'foto') {
        $row = DB::table('patrimonio_fotos')->where('id', $arquivoId)->first();
    } elseif ($tipo === 'documento') {
        $row = DB::table('patrimonio_documentos')->where('id', $arquivoId)->first();
    } else {
        return response('Tipo inválido', 400);
    }
    if (! $row || ! $row->path) {
        return response('Não encontrado', 404);
    }
    $path = storage_path('app/public/' . $row->path);
    if (! is_file($path)) {
        return response('Arquivo ausente', 404);
    }

    return response()->file($path)->header('Access-Control-Allow-Origin', '*');
});

Route::delete('/patrimonio/arquivos/{tipo}/{arquivoId}', function (Request $request, $tipo, $arquivoId) use ($patrimonioAuth, $podePatrimonio, $patJson) {
    $u = $patrimonioAuth($request);
    if (! $podePatrimonio($u, 'patrimonios')) {
        return $patJson(['message' => 'Sem permissão'], 403);
    }
    if ($tipo === 'foto') {
        $row = DB::table('patrimonio_fotos')->where('id', $arquivoId)->first();
        if ($row && $row->path && Storage::disk('public')->exists($row->path)) {
            Storage::disk('public')->delete($row->path);
        }
        DB::table('patrimonio_fotos')->where('id', $arquivoId)->delete();
    } elseif ($tipo === 'documento') {
        $row = DB::table('patrimonio_documentos')->where('id', $arquivoId)->first();
        if ($row && $row->path && Storage::disk('public')->exists($row->path)) {
            Storage::disk('public')->delete($row->path);
        }
        DB::table('patrimonio_documentos')->where('id', $arquivoId)->delete();
    }

    return $patJson(['message' => 'Arquivo removido']);
});

Route::put('/patrimonio/inventario/itens/{itemId}', function (Request $request, $itemId) use ($patrimonioAuth, $podePatrimonio, $patJson) {
    $u = $patrimonioAuth($request);
    if (! $podePatrimonio($u, 'patrimonioInventario')) {
        return $patJson(['message' => 'Sem permissão'], 403);
    }
    $qtdEnc = $request->input('qtd_encontrada');
    $qtdSis = (int) DB::table('patrimonio_inventario_itens')->where('id', $itemId)->value('qtd_sistema');
    $diff = $qtdEnc !== null ? ((int) $qtdEnc - $qtdSis) : null;
    DB::table('patrimonio_inventario_itens')->where('id', $itemId)->update([
        'localizacao' => $request->input('localizacao'),
        'qtd_encontrada' => $qtdEnc,
        'diferenca' => $diff,
        'observacao' => $request->input('observacao'),
        'conferido_em' => now(),
        'usuario_id' => $u->id ?? null,
        'updated_at' => now(),
    ]);

    return $patJson(['message' => 'Item conferido']);
});

// ——— Relatório resumo (CSV) ———
Route::get('/patrimonio/relatorios/resumo', function (Request $request) use ($patrimonioAuth, $podePatrimonio, $patrimonioQueryBase) {
    $u = $patrimonioAuth($request);
    if (! $podePatrimonio($u, 'patrimonioRelatorios')) {
        return response('Sem permissão', 403);
    }
    $rows = $patrimonioQueryBase()->orderBy('p.codigo')->get();
    $lines = ["codigo;nome;categoria;unidade;situacao;valor_atual"];
    foreach ($rows as $r) {
        $lines[] = implode(';', [
            $r->codigo,
            str_replace(';', ',', $r->nome),
            $r->categoria_nome ?? '',
            $r->unidade_nome ?? '',
            $r->situacao,
            $r->valor_atual ?? '',
        ]);
    }
    $csv = implode("\n", $lines);

    return response($csv, 200, [
        'Content-Type' => 'text/csv; charset=UTF-8',
        'Content-Disposition' => 'attachment; filename="patrimonio-resumo.csv"',
        'Access-Control-Allow-Origin' => '*',
    ]);
});

require __DIR__ . '/patrimonio_reports.php';
