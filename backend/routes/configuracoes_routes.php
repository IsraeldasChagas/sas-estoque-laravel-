<?php

/**
 * Rotas API — Painel de Configurações do Sistema (SAS-Estoque)
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

$cfgCors = fn () => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');

$cfgAuth = function (Request $req) {
    $uid = $req->header('X-Usuario-Id');

    return $uid ? DB::table('usuarios')->where('id', $uid)->where('ativo', 1)->first() : null;
};

$cfgPodeVer = function ($u) {
    if (! $u) {
        return false;
    }
    $p = strtoupper(trim((string) ($u->perfil ?? '')));

    return in_array($p, ['ADMIN', 'GERENTE'], true);
};

$cfgPodeEditar = function ($u) {
    if (! $u) {
        return false;
    }

    return strtoupper(trim((string) ($u->perfil ?? ''))) === 'ADMIN';
};

$cfgJson = fn ($data, int $code = 200) => response()->json($data, $code)
    ->header('Access-Control-Allow-Origin', '*');

$cfgChavesPermitidas = [
    'empresa_nome', 'empresa_cnpj', 'empresa_email', 'empresa_telefone',
    'empresa_endereco', 'suporte_email', 'observacoes_sistema',
];

$cfgLerTodas = function () use ($cfgChavesPermitidas) {
    if (! Schema::hasTable('sistema_configuracoes')) {
        return array_fill_keys($cfgChavesPermitidas, '');
    }
    $rows = DB::table('sistema_configuracoes')->whereIn('chave', $cfgChavesPermitidas)->pluck('valor', 'chave');
    $out = [];
    foreach ($cfgChavesPermitidas as $k) {
        $out[$k] = (string) ($rows[$k] ?? '');
    }

    return $out;
};

$cfgResumo = function () {
    $count = fn ($t) => Schema::hasTable($t) ? (int) DB::table($t)->count() : 0;

    return [
        'usuarios' => $count('usuarios'),
        'unidades' => $count('unidades'),
        'produtos' => $count('produtos'),
        'funcionarios' => $count('funcionarios'),
        'backups' => 0,
    ];
};

foreach (['/configuracoes-sistema', '/configuracoes-sistema/resumo'] as $p) {
    Route::options($p, $cfgCors);
}

Route::get('/configuracoes-sistema/resumo', function (Request $request) use ($cfgAuth, $cfgPodeVer, $cfgJson, $cfgResumo, $cfgLerTodas) {
    $u = $cfgAuth($request);
    if (! $cfgPodeVer($u)) {
        return $cfgJson(['error' => 'Não autorizado'], 401);
    }
    $resumo = $cfgResumo();
    if (Schema::hasTable('sistema_configuracoes')) {
        $dir = storage_path('app/backups');
        if (is_dir($dir)) {
            $resumo['backups'] = count(glob($dir.'/*.json') ?: []);
        }
    }

    return $cfgJson([
        'config' => $cfgLerTodas(),
        'resumo' => $resumo,
        'usuario' => [
            'id' => (int) $u->id,
            'nome' => $u->nome,
            'perfil' => $u->perfil,
            'pode_editar' => strtoupper((string) ($u->perfil ?? '')) === 'ADMIN',
        ],
    ]);
});

Route::get('/configuracoes-sistema', function (Request $request) use ($cfgAuth, $cfgPodeVer, $cfgJson, $cfgLerTodas) {
    $u = $cfgAuth($request);
    if (! $cfgPodeVer($u)) {
        return $cfgJson(['error' => 'Não autorizado'], 401);
    }

    return $cfgJson(['config' => $cfgLerTodas()]);
});

Route::post('/configuracoes-sistema', function (Request $request) use ($cfgAuth, $cfgPodeEditar, $cfgJson, $cfgChavesPermitidas, $cfgLerTodas) {
    $u = $cfgAuth($request);
    if (! $cfgPodeEditar($u)) {
        return $cfgJson(['error' => 'Somente administrador pode alterar configurações'], 403);
    }
    if (! Schema::hasTable('sistema_configuracoes')) {
        return $cfgJson(['error' => 'Módulo não configurado. Execute as migrations.'], 503);
    }

    $payload = $request->input('config', $request->all());
    if (! is_array($payload)) {
        return $cfgJson(['error' => 'Dados inválidos'], 422);
    }

    foreach ($cfgChavesPermitidas as $chave) {
        if (! array_key_exists($chave, $payload)) {
            continue;
        }
        $valor = trim((string) $payload[$chave]);
        $exists = DB::table('sistema_configuracoes')->where('chave', $chave)->exists();
        if ($exists) {
            DB::table('sistema_configuracoes')->where('chave', $chave)->update([
                'valor' => $valor,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('sistema_configuracoes')->insert([
                'chave' => $chave,
                'valor' => $valor,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    if (Schema::hasTable('audit_logs')) {
        DB::table('audit_logs')->insert([
            'usuario_id' => $u->id,
            'acao' => 'atualizar',
            'recurso' => 'sistema_configuracoes',
            'descricao' => 'Painel de configurações do sistema',
            'created_at' => now(),
        ]);
    }

    return $cfgJson(['ok' => true, 'config' => $cfgLerTodas()]);
});
