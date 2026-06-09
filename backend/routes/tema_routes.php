<?php

/**
 * Rotas API — Tema de cores (global + por usuário)
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$TEMA_PADRAO = [
    'menuBg' => '#070403',
    'menuAccent' => '#de4309',
    'topbarBg' => '#ffffff',
    'pageBg' => '#f6f7fb',
    'pagePrimary' => '#0047ab',
];

$TEMA_CHAVES = array_keys($TEMA_PADRAO);

$temaCors = fn () => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, PUT, POST, DELETE, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');

$temaJson = fn ($data, int $code = 200) => response()->json($data, $code)
    ->header('Access-Control-Allow-Origin', '*');

$temaAuth = function (Request $req) {
    $uid = $req->header('X-Usuario-Id');

    return $uid ? DB::table('usuarios')->where('id', $uid)->where('ativo', 1)->first() : null;
};

$temaNormalizar = function ($raw) use ($TEMA_PADRAO, $TEMA_CHAVES) {
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : null;
    }
    if (! is_array($raw)) {
        return null;
    }
    $out = [];
    foreach ($TEMA_CHAVES as $k) {
        $v = trim((string) ($raw[$k] ?? ''));
        if (! preg_match('/^#[0-9a-fA-F]{6}$/', $v)) {
            return null;
        }
        $out[$k] = strtolower($v);
    }

    return $out;
};

$temaLerGlobal = function () use ($TEMA_PADRAO, $temaNormalizar) {
    if (! Schema::hasTable('sistema_configuracoes')) {
        return $TEMA_PADRAO;
    }
    $row = DB::table('sistema_configuracoes')->where('chave', 'tema_cores_global')->value('valor');

    return $temaNormalizar($row) ?? $TEMA_PADRAO;
};

$temaLerUsuario = function ($usuarioId) use ($temaNormalizar) {
    if (! $usuarioId || ! Schema::hasTable('usuarios') || ! Schema::hasColumn('usuarios', 'tema_cores')) {
        return null;
    }
    $raw = DB::table('usuarios')->where('id', $usuarioId)->value('tema_cores');

    return $temaNormalizar($raw);
};

$temaSalvarGlobal = function (array $cfg) {
    if (! Schema::hasTable('sistema_configuracoes')) {
        return false;
    }
    $json = json_encode($cfg, JSON_UNESCAPED_UNICODE);
    $exists = DB::table('sistema_configuracoes')->where('chave', 'tema_cores_global')->exists();
    if ($exists) {
        DB::table('sistema_configuracoes')->where('chave', 'tema_cores_global')->update([
            'valor' => $json,
            'updated_at' => now(),
        ]);
    } else {
        DB::table('sistema_configuracoes')->insert([
            'chave' => 'tema_cores_global',
            'valor' => $json,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return true;
};

foreach (['/tema-cores', '/usuarios/me/tema-cores', '/configuracoes-sistema/tema-global'] as $p) {
    Route::options($p, $temaCors);
}

Route::get('/tema-cores', function (Request $request) use ($temaAuth, $temaJson, $temaLerGlobal, $temaLerUsuario, $TEMA_PADRAO) {
    $global = $temaLerGlobal();
    $u = $temaAuth($request);
    $usuario = $u ? $temaLerUsuario($u->id) : null;
    $efetivo = $usuario ?? $global;
    $fonte = $usuario ? 'usuario' : ($global !== $TEMA_PADRAO ? 'global' : 'padrao');

    return $temaJson([
        'global' => $global,
        'usuario' => $usuario,
        'efetivo' => $efetivo,
        'fonte' => $fonte,
        'pode_editar_global' => $u && strtoupper((string) ($u->perfil ?? '')) === 'ADMIN',
    ]);
});

Route::put('/usuarios/me/tema-cores', function (Request $request) use ($temaAuth, $temaJson, $temaNormalizar) {
    $u = $temaAuth($request);
    if (! $u) {
        return $temaJson(['error' => 'Não autenticado'], 401);
    }
    if (! Schema::hasColumn('usuarios', 'tema_cores')) {
        return $temaJson(['error' => 'Módulo não configurado. Execute as migrations.'], 503);
    }

    $cfg = $temaNormalizar($request->input('tema', $request->all()));
    if (! $cfg) {
        return $temaJson(['error' => 'Cores inválidas'], 422);
    }

    $upd = ['tema_cores' => json_encode($cfg, JSON_UNESCAPED_UNICODE)];
    if (Schema::hasColumn('usuarios', 'updated_at')) {
        $upd['updated_at'] = now();
    }
    DB::table('usuarios')->where('id', $u->id)->update($upd);

    if (Schema::hasTable('audit_logs')) {
        DB::table('audit_logs')->insert([
            'usuario_id' => $u->id,
            'acao' => 'atualizar',
            'recurso' => 'tema_cores_usuario',
            'descricao' => 'Tema de cores pessoal atualizado',
            'created_at' => now(),
        ]);
    }

    return $temaJson(['ok' => true, 'tema' => $cfg]);
});

Route::delete('/usuarios/me/tema-cores', function (Request $request) use ($temaAuth, $temaJson, $temaLerGlobal) {
    $u = $temaAuth($request);
    if (! $u) {
        return $temaJson(['error' => 'Não autenticado'], 401);
    }
    if (Schema::hasColumn('usuarios', 'tema_cores')) {
        $upd = ['tema_cores' => null];
        if (Schema::hasColumn('usuarios', 'updated_at')) {
            $upd['updated_at'] = now();
        }
        DB::table('usuarios')->where('id', $u->id)->update($upd);
    }

    return $temaJson(['ok' => true, 'efetivo' => $temaLerGlobal()]);
});

Route::post('/configuracoes-sistema/tema-global', function (Request $request) use ($temaAuth, $temaJson, $temaNormalizar, $temaSalvarGlobal) {
    $u = $temaAuth($request);
    if (! $u || strtoupper((string) ($u->perfil ?? '')) !== 'ADMIN') {
        return $temaJson(['error' => 'Somente administrador pode definir o tema global'], 403);
    }

    $cfg = $temaNormalizar($request->input('tema', $request->all()));
    if (! $cfg) {
        return $temaJson(['error' => 'Cores inválidas'], 422);
    }
    if (! $temaSalvarGlobal($cfg)) {
        return $temaJson(['error' => 'Módulo não configurado. Execute as migrations.'], 503);
    }

    if (Schema::hasTable('audit_logs')) {
        DB::table('audit_logs')->insert([
            'usuario_id' => $u->id,
            'acao' => 'atualizar',
            'recurso' => 'tema_cores_global',
            'descricao' => 'Tema de cores global do sistema atualizado',
            'created_at' => now(),
        ]);
    }

    return $temaJson(['ok' => true, 'tema' => $cfg]);
});
