<?php

/**
 * Rotas API — Assistente IA (OpenAI / ChatGPT)
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

$iaCors = fn () => response()->json([])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');

$iaAuth = function (Request $req) {
    $uid = $req->header('X-Usuario-Id');

    return $uid ? DB::table('usuarios')->where('id', $uid)->where('ativo', 1)->first() : null;
};

$iaJson = fn ($data, int $code = 200) => response()->json($data, $code)
    ->header('Access-Control-Allow-Origin', '*');

$iaChaves = ['ia_ativo', 'ia_api_key', 'ia_modelo', 'ia_instrucoes'];

$iaLerConfig = function () use ($iaChaves) {
    $defaults = [
        'ia_ativo' => '0',
        'ia_api_key' => '',
        'ia_modelo' => 'gpt-4o-mini',
        'ia_instrucoes' => '',
    ];
    if (! Schema::hasTable('sistema_configuracoes')) {
        return $defaults;
    }
    $rows = DB::table('sistema_configuracoes')->whereIn('chave', $iaChaves)->pluck('valor', 'chave');
    $out = [];
    foreach ($iaChaves as $k) {
        $out[$k] = (string) ($rows[$k] ?? $defaults[$k] ?? '');
    }

    return $out;
};

$iaSalvarChave = function (string $chave, string $valor) {
    if (! Schema::hasTable('sistema_configuracoes')) {
        return false;
    }
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

    return true;
};

$iaMascararApiKey = function (string $key): string {
    $key = trim($key);
    if ($key === '') {
        return '';
    }
    if (strlen($key) <= 8) {
        return '********';
    }

    return str_repeat('•', max(8, strlen($key) - 4)).substr($key, -4);
};

$iaPromptSistema = function (array $cfg): string {
    $extra = trim((string) ($cfg['ia_instrucoes'] ?? ''));
    $base = <<<'TXT'
Você é o assistente virtual do sistema SAS Estoque do Grupo Sabor Paraense.
Responda sempre em português do Brasil, de forma clara e objetiva.

Você AJUDA o usuário a entender e usar o sistema. Você NÃO altera dados, NÃO executa ações e NÃO invente números ou registros.
Se não souber algo com certeza, diga para confirmar no sistema ou com o administrador.

Módulos que o sistema possui:
- Estoque: produtos, lotes, locais, movimentações (entrada, consumo, produção, transferência, perda)
- Compras e fornecedores
- Financeiro: boletos, despesas fixas, vale/consumo, fechamento de caixa, fluxo, DRE, custo de saídas de estoque
- RH: funcionários, folha, proventos, recrutamento, rescisão
- Reservas de mesas
- Patrimônio, energia, investimentos
- Configurações e usuários (perfil ADMIN)

Na primeira interação ou quando perguntarem o que você faz, explique brevemente que pode tirar dúvidas sobre como usar esses módulos.
TXT;

    if ($extra !== '') {
        $base .= "\n\nInstruções adicionais da empresa:\n".$extra;
    }

    return $base;
};

foreach (['/ia/config', '/ia/status', '/ia/chat'] as $p) {
    Route::options($p, $iaCors);
}

Route::get('/ia/status', function (Request $request) use ($iaAuth, $iaJson, $iaLerConfig) {
    $u = $iaAuth($request);
    if (! $u) {
        return $iaJson(['error' => 'Não autorizado'], 401);
    }
    $cfg = $iaLerConfig();
    $ativo = in_array($cfg['ia_ativo'], ['1', 'true', 'sim', 'on'], true);
    $temChave = trim($cfg['ia_api_key']) !== '';

    return $iaJson([
        'ativo' => $ativo && $temChave,
        'modelo' => $cfg['ia_modelo'] ?: 'gpt-4o-mini',
    ]);
});

Route::get('/ia/config', function (Request $request) use ($iaAuth, $iaJson, $iaLerConfig, $iaMascararApiKey) {
    $u = $iaAuth($request);
    if (! $u) {
        return $iaJson(['error' => 'Não autorizado'], 401);
    }
    $isAdmin = strtoupper(trim((string) ($u->perfil ?? ''))) === 'ADMIN';
    if (! $isAdmin) {
        return $iaJson(['error' => 'Somente administrador pode ver configurações da IA'], 403);
    }
    $cfg = $iaLerConfig();

    return $iaJson([
        'config' => [
            'ia_ativo' => in_array($cfg['ia_ativo'], ['1', 'true', 'sim', 'on'], true),
            'ia_api_key' => $iaMascararApiKey($cfg['ia_api_key']),
            'ia_api_key_configurada' => trim($cfg['ia_api_key']) !== '',
            'ia_modelo' => $cfg['ia_modelo'] ?: 'gpt-4o-mini',
            'ia_instrucoes' => $cfg['ia_instrucoes'],
        ],
        'modelos_sugeridos' => ['gpt-4o-mini', 'gpt-4o', 'gpt-4.1-mini', 'gpt-4.1'],
    ]);
});

Route::post('/ia/config', function (Request $request) use ($iaAuth, $iaJson, $iaLerConfig, $iaSalvarChave, $iaMascararApiKey) {
    $u = $iaAuth($request);
    if (! $u) {
        return $iaJson(['error' => 'Não autorizado'], 401);
    }
    if (strtoupper(trim((string) ($u->perfil ?? ''))) !== 'ADMIN') {
        return $iaJson(['error' => 'Somente administrador pode alterar configurações da IA'], 403);
    }
    if (! Schema::hasTable('sistema_configuracoes')) {
        return $iaJson(['error' => 'Tabela sistema_configuracoes não encontrada. Execute as migrations.'], 503);
    }

    $payload = $request->all();
    $atual = $iaLerConfig();

    $iaSalvarChave('ia_ativo', ! empty($payload['ia_ativo']) && ! in_array($payload['ia_ativo'], [false, '0', 'false', 'off'], true) ? '1' : '0');

    $modelo = trim((string) ($payload['ia_modelo'] ?? 'gpt-4o-mini'));
    if ($modelo === '') {
        $modelo = 'gpt-4o-mini';
    }
    $iaSalvarChave('ia_modelo', mb_substr($modelo, 0, 80));

    $instrucoes = trim((string) ($payload['ia_instrucoes'] ?? ''));
    $iaSalvarChave('ia_instrucoes', mb_substr($instrucoes, 0, 4000));

    $novaChave = trim((string) ($payload['ia_api_key'] ?? ''));
    if (! empty($payload['ia_api_key_limpar'])) {
        $iaSalvarChave('ia_api_key', '');
    } elseif ($novaChave !== '' && ! str_contains($novaChave, '•') && ! preg_match('/^\*+/', $novaChave)) {
        $iaSalvarChave('ia_api_key', $novaChave);
    }

    if (Schema::hasTable('audit_logs')) {
        DB::table('audit_logs')->insert([
            'usuario_id' => $u->id,
            'acao' => 'atualizar',
            'recurso' => 'ia_config',
            'descricao' => 'Configurações do assistente IA (OpenAI)',
            'created_at' => now(),
        ]);
    }

    $cfg = $iaLerConfig();

    return $iaJson([
        'ok' => true,
        'config' => [
            'ia_ativo' => in_array($cfg['ia_ativo'], ['1', 'true', 'sim', 'on'], true),
            'ia_api_key' => $iaMascararApiKey($cfg['ia_api_key']),
            'ia_api_key_configurada' => trim($cfg['ia_api_key']) !== '',
            'ia_modelo' => $cfg['ia_modelo'] ?: 'gpt-4o-mini',
            'ia_instrucoes' => $cfg['ia_instrucoes'],
        ],
    ]);
});

Route::post('/ia/chat', function (Request $request) use ($iaAuth, $iaJson, $iaLerConfig, $iaPromptSistema) {
    $u = $iaAuth($request);
    if (! $u) {
        return $iaJson(['error' => 'Não autorizado'], 401);
    }

    $cfg = $iaLerConfig();
    $ativo = in_array($cfg['ia_ativo'], ['1', 'true', 'sim', 'on'], true);
    $apiKey = trim($cfg['ia_api_key'] ?? '');
    if (! $ativo || $apiKey === '') {
        return $iaJson(['error' => 'Assistente IA desativado ou sem chave API configurada. Peça ao administrador.'], 503);
    }

    $mensagem = trim((string) ($request->input('message') ?? ''));
    if ($mensagem === '') {
        return $iaJson(['error' => 'Digite uma mensagem.'], 422);
    }
    if (mb_strlen($mensagem) > 4000) {
        return $iaJson(['error' => 'Mensagem muito longa (máx. 4000 caracteres).'], 422);
    }

    $historico = $request->input('history', []);
    if (! is_array($historico)) {
        $historico = [];
    }

    $messages = [
        ['role' => 'system', 'content' => $iaPromptSistema($cfg)],
    ];

    $count = 0;
    foreach (array_slice($historico, -10) as $h) {
        if (! is_array($h)) {
            continue;
        }
        $role = ($h['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
        $content = trim((string) ($h['content'] ?? ''));
        if ($content === '') {
            continue;
        }
        $messages[] = ['role' => $role, 'content' => mb_substr($content, 0, 4000)];
        $count++;
    }

    $messages[] = ['role' => 'user', 'content' => $mensagem];

    $modelo = trim($cfg['ia_modelo'] ?? '') ?: 'gpt-4o-mini';

    try {
        $resp = Http::timeout(90)
            ->withToken($apiKey)
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $modelo,
                'messages' => $messages,
                'max_tokens' => 900,
                'temperature' => 0.4,
            ]);

        if (! $resp->successful()) {
            $body = $resp->json();
            $msgApi = is_array($body) ? ($body['error']['message'] ?? $resp->body()) : $resp->body();
            \Log::warning('OpenAI chat error: '.$msgApi);

            return $iaJson(['error' => 'Erro na API OpenAI: '.mb_substr((string) $msgApi, 0, 300)], 502);
        }

        $data = $resp->json();
        $reply = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
        if ($reply === '') {
            return $iaJson(['error' => 'Resposta vazia da IA.'], 502);
        }

        return $iaJson([
            'reply' => $reply,
            'modelo' => $modelo,
        ]);
    } catch (\Throwable $e) {
        \Log::error('IA chat: '.$e->getMessage());

        return $iaJson(['error' => 'Falha ao contactar OpenAI. Verifique a chave e a conexão do servidor.'], 502);
    }
});
