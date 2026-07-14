<?php

namespace App\Http\Controllers;

use App\Models\AylaAuditLog;
use App\Models\AylaUsuarioAutorizado;
use App\Support\Ayla\AylaSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Painel administrativo do módulo "Ayla IA".
 * Autenticação: X-Usuario-Id (mesmo padrão do painel). ADMIN para alterações;
 * ADMIN/GERENTE para leitura de dashboard e logs. Nunca confia só no frontend.
 */
class AylaUsuarioController extends Controller
{
    /** Módulos que podem ser marcados nas permissões da Ayla. */
    public const MODULOS_DISPONIVEIS = [
        'dashboard' => 'Dashboard',
        'unidades' => 'Unidades',
        'produtos' => 'Produtos',
        'estoque' => 'Estoque',
        'movimentacoes' => 'Movimentações',
        'lotes' => 'Lotes',
        'compras' => 'Compras',
        'fornecedores' => 'Fornecedores',
        'relatorios' => 'Relatórios',
        'financeiro' => 'Financeiro',
        'rh' => 'RH',
        'reservas' => 'Reservas',
        'energia' => 'Energia',
        'patrimonio' => 'Patrimônio',
        'investimentos' => 'Investimentos',
        'logs' => 'Logs',
    ];

    private function usuarioAtual(Request $request): ?object
    {
        $uid = $request->header('X-Usuario-Id');
        if (! $uid || ! ctype_digit((string) $uid)) {
            return null;
        }

        return DB::table('usuarios')->where('id', (int) $uid)->where('ativo', 1)->first();
    }

    private function isAdmin(?object $u): bool
    {
        if (! $u) {
            return false;
        }

        return in_array(strtoupper(trim((string) ($u->perfil ?? ''))), ['ADMIN', 'ADMINISTRADOR'], true);
    }

    private function isGestor(?object $u): bool
    {
        if (! $u) {
            return false;
        }

        return in_array(strtoupper(trim((string) ($u->perfil ?? ''))), ['ADMIN', 'ADMINISTRADOR', 'GERENTE'], true);
    }

    private function json(array $data, int $code = 200)
    {
        return response()->json($data, $code)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }

    private function auditar(?object $u, string $acao, array $resumo, string $status = 'ok', int $http = 200): void
    {
        AylaAuditLog::registrar([
            'user_id' => $u->id ?? null,
            'ip' => request()->ip(),
            'metodo' => request()->method(),
            'rota' => request()->path(),
            'acao' => $acao,
            'payload' => [],
            'resposta_resumo' => $resumo,
            'status' => $status,
            'http_status' => $http,
            'duracao_ms' => null,
        ]);
    }

    // ------------------------------------------------------------------
    // Usuários autorizados
    // ------------------------------------------------------------------

    public function index(Request $request)
    {
        $u = $this->usuarioAtual($request);
        if (! $this->isAdmin($u)) {
            return $this->json(['error' => 'Somente administrador pode acessar.'], 403);
        }

        if (! Schema::hasTable('ayla_usuarios_autorizados')) {
            return $this->json(['usuarios' => [], 'total' => 0]);
        }

        $rows = DB::table('ayla_usuarios_autorizados as a')
            ->leftJoin('usuarios as u', 'a.usuario_id', '=', 'u.id')
            ->leftJoin('unidades as un', 'u.unidade_id', '=', 'un.id')
            ->orderByDesc('a.id')
            ->get([
                'a.*',
                'u.nome as usuario_nome',
                'u.perfil as usuario_perfil',
                'u.email as usuario_email',
                'un.nome as unidade_nome',
            ]);

        return $this->json([
            'total' => $rows->count(),
            'usuarios' => $rows->map(fn ($r) => $this->serializar($r))->all(),
        ]);
    }

    public function show(Request $request, $id)
    {
        $u = $this->usuarioAtual($request);
        if (! $this->isAdmin($u)) {
            return $this->json(['error' => 'Somente administrador pode acessar.'], 403);
        }

        $row = DB::table('ayla_usuarios_autorizados as a')
            ->leftJoin('usuarios as u', 'a.usuario_id', '=', 'u.id')
            ->leftJoin('unidades as un', 'u.unidade_id', '=', 'un.id')
            ->where('a.id', (int) $id)
            ->first(['a.*', 'u.nome as usuario_nome', 'u.perfil as usuario_perfil', 'u.email as usuario_email', 'un.nome as unidade_nome']);

        if (! $row) {
            return $this->json(['error' => 'Registro não encontrado.'], 404);
        }

        return $this->json(['usuario' => $this->serializar($row)]);
    }

    public function store(Request $request)
    {
        $u = $this->usuarioAtual($request);
        if (! $this->isAdmin($u)) {
            return $this->json(['error' => 'Somente administrador pode cadastrar.'], 403);
        }

        $dados = $this->validarPayload($request);
        if (isset($dados['error'])) {
            return $this->json($dados, 422);
        }

        // Vínculo é sempre com um usuário SAS existente.
        $usuarioSas = DB::table('usuarios')->where('id', $dados['usuario_id'])->first();
        if (! $usuarioSas) {
            return $this->json(['error' => 'Usuário SAS não encontrado.'], 422);
        }

        // Não duplicar vínculo do mesmo usuário SAS.
        if (AylaUsuarioAutorizado::where('usuario_id', $dados['usuario_id'])->exists()) {
            return $this->json(['error' => 'Este usuário já possui acesso Ayla cadastrado.'], 422);
        }

        if ($erro = $this->conflitoTelegram($dados['telegram_user_id'], $dados['status'], null)) {
            return $this->json(['error' => $erro], 422);
        }

        $registro = AylaUsuarioAutorizado::create(array_merge($dados, [
            'autorizado_por' => $u->id,
            'autorizado_em' => $dados['status'] === 'ativo' ? now() : null,
        ]));

        $this->auditar($u, 'ayla.admin.usuario.criar', ['id' => $registro->id, 'usuario_id' => $registro->usuario_id]);

        return $this->json(['ok' => true, 'id' => $registro->id]);
    }

    public function update(Request $request, $id)
    {
        $u = $this->usuarioAtual($request);
        if (! $this->isAdmin($u)) {
            return $this->json(['error' => 'Somente administrador pode alterar.'], 403);
        }

        $registro = AylaUsuarioAutorizado::find((int) $id);
        if (! $registro) {
            return $this->json(['error' => 'Registro não encontrado.'], 404);
        }

        $dados = $this->validarPayload($request, $registro);
        if (isset($dados['error'])) {
            return $this->json($dados, 422);
        }

        if ($erro = $this->conflitoTelegram($dados['telegram_user_id'], $dados['status'], $registro->id)) {
            return $this->json(['error' => $erro], 422);
        }

        if ($dados['status'] === 'ativo' && $registro->status !== 'ativo') {
            $dados['autorizado_por'] = $u->id;
            $dados['autorizado_em'] = now();
        }

        $registro->update($dados);
        $this->auditar($u, 'ayla.admin.usuario.editar', ['id' => $registro->id]);

        return $this->json(['ok' => true]);
    }

    public function status(Request $request, $id)
    {
        $u = $this->usuarioAtual($request);
        if (! $this->isAdmin($u)) {
            return $this->json(['error' => 'Somente administrador pode alterar status.'], 403);
        }

        $registro = AylaUsuarioAutorizado::find((int) $id);
        if (! $registro) {
            return $this->json(['error' => 'Registro não encontrado.'], 404);
        }

        $novo = strtolower(trim((string) $request->input('status', '')));
        if (! in_array($novo, AylaUsuarioAutorizado::STATUS, true)) {
            return $this->json(['error' => 'Status inválido.'], 422);
        }

        if ($novo === 'ativo' && $erro = $this->conflitoTelegram($registro->telegram_user_id, 'ativo', $registro->id)) {
            return $this->json(['error' => $erro], 422);
        }

        $registro->status = $novo;
        if ($novo === 'ativo') {
            $registro->autorizado_por = $u->id;
            $registro->autorizado_em = now();
        }
        $registro->save();

        $this->auditar($u, 'ayla.admin.usuario.status', ['id' => $registro->id, 'status' => $novo]);

        return $this->json(['ok' => true, 'status' => $novo]);
    }

    public function destroy(Request $request, $id)
    {
        $u = $this->usuarioAtual($request);
        if (! $this->isAdmin($u)) {
            return $this->json(['error' => 'Somente administrador pode revogar.'], 403);
        }

        $registro = AylaUsuarioAutorizado::find((int) $id);
        if (! $registro) {
            return $this->json(['error' => 'Registro não encontrado.'], 404);
        }

        // Nunca apaga fisicamente: apenas revoga, preservando auditoria.
        $registro->status = 'revogado';
        $registro->save();

        $this->auditar($u, 'ayla.admin.usuario.revogar', ['id' => $registro->id]);

        return $this->json(['ok' => true, 'status' => 'revogado']);
    }

    /** Define o administrador principal da Ayla (usuário + Telegram do próprio ADMIN). */
    public function adminPrincipal(Request $request)
    {
        $u = $this->usuarioAtual($request);
        if (! $this->isAdmin($u)) {
            return $this->json(['error' => 'Somente administrador pode definir o administrador principal.'], 403);
        }

        $usuarioId = (int) $request->input('usuario_id', $u->id);
        $telegramId = trim((string) $request->input('telegram_user_id', ''));
        $usuarioSas = DB::table('usuarios')->where('id', $usuarioId)->where('ativo', 1)->first();
        if (! $usuarioSas) {
            return $this->json(['error' => 'Usuário SAS inválido.'], 422);
        }
        if ($telegramId !== '' && $erro = $this->conflitoTelegram($telegramId, 'ativo', null, $usuarioId)) {
            return $this->json(['error' => $erro], 422);
        }

        $registro = AylaUsuarioAutorizado::firstOrNew(['usuario_id' => $usuarioId]);
        $registro->telegram_user_id = $telegramId !== '' ? $telegramId : $registro->telegram_user_id;
        $registro->telegram_username = $request->input('telegram_username', $registro->telegram_username);
        $registro->cargo = $registro->cargo ?: 'Administrador principal';
        $registro->modulos_permitidos = array_keys(self::MODULOS_DISPONIVEIS);
        $registro->pode_usar_texto = true;
        $registro->pode_usar_audio = true;
        $registro->pode_consultar_dados = true;
        $registro->pode_executar_acoes = false;
        $registro->status = 'ativo';
        $registro->autorizado_por = $u->id;
        $registro->autorizado_em = now();
        $registro->save();

        $this->auditar($u, 'ayla.admin.admin_principal', ['id' => $registro->id, 'usuario_id' => $usuarioId]);

        return $this->json(['ok' => true, 'id' => $registro->id]);
    }

    // ------------------------------------------------------------------
    // Opções / dashboard / logs / config
    // ------------------------------------------------------------------

    public function opcoes(Request $request)
    {
        $u = $this->usuarioAtual($request);
        if (! $this->isAdmin($u)) {
            return $this->json(['error' => 'Somente administrador pode acessar.'], 403);
        }

        $usuarios = Schema::hasTable('usuarios')
            ? DB::table('usuarios')->where('ativo', 1)->orderBy('nome')->get(['id', 'nome', 'perfil', 'email', 'unidade_id'])
            : collect();
        $unidades = Schema::hasTable('unidades')
            ? DB::table('unidades')->orderBy('nome')->get(['id', 'nome'])
            : collect();

        return $this->json([
            'usuarios' => $usuarios,
            'unidades' => $unidades,
            'modulos_disponiveis' => self::MODULOS_DISPONIVEIS,
            'status_disponiveis' => AylaUsuarioAutorizado::STATUS,
        ]);
    }

    public function dashboard(Request $request)
    {
        $u = $this->usuarioAtual($request);
        if (! $this->isGestor($u)) {
            return $this->json(['error' => 'Sem permissão.'], 403);
        }

        $cfg = AylaSettings::paraPainel();

        $usuarios = ['total' => 0, 'ativos' => 0, 'pendentes' => 0, 'bloqueados' => 0, 'revogados' => 0];
        if (Schema::hasTable('ayla_usuarios_autorizados')) {
            $contagem = DB::table('ayla_usuarios_autorizados')
                ->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');
            $usuarios['ativos'] = (int) ($contagem['ativo'] ?? 0);
            $usuarios['pendentes'] = (int) ($contagem['pendente'] ?? 0);
            $usuarios['bloqueados'] = (int) ($contagem['bloqueado'] ?? 0);
            $usuarios['revogados'] = (int) ($contagem['revogado'] ?? 0);
            $usuarios['total'] = array_sum($contagem->all());
        }

        $consultas = ['hoje' => 0, 'sucesso' => 0, 'erros' => 0, 'tempo_medio_ms' => 0];
        $ultimas = [];
        if (Schema::hasTable('ayla_audit_logs')) {
            $hoje = DB::table('ayla_audit_logs')->whereDate('created_at', today());
            $consultas['hoje'] = (int) (clone $hoje)->count();
            $consultas['sucesso'] = (int) (clone $hoje)->where('status', 'ok')->count();
            $consultas['erros'] = (int) (clone $hoje)->whereIn('status', ['erro', 'negado'])->count();
            $consultas['tempo_medio_ms'] = (int) round((float) (clone $hoje)->avg('duracao_ms'));

            $ultimas = DB::table('ayla_audit_logs as l')
                ->leftJoin('usuarios as us', 'l.user_id', '=', 'us.id')
                ->orderByDesc('l.id')
                ->limit(10)
                ->get(['l.acao', 'l.status', 'l.http_status', 'l.duracao_ms', 'l.created_at', 'us.nome as usuario_nome'])
                ->map(fn ($r) => [
                    'acao' => $r->acao,
                    'status' => $r->status,
                    'http_status' => $r->http_status,
                    'duracao_ms' => $r->duracao_ms,
                    'usuario' => $r->usuario_nome,
                    'quando' => $r->created_at,
                ])->all();
        }

        return $this->json([
            'integracao' => [
                'ativa' => $cfg['ativa'],
                'read_only' => $cfg['read_only'],
                'versao' => $cfg['versao'],
                'token_configurado' => $cfg['token_configurado'],
            ],
            'telegram' => [
                'ativo' => $cfg['telegram_ativo'],
                'bot_username' => $cfg['telegram_bot_username'],
            ],
            'usuarios' => $usuarios,
            'consultas' => $consultas,
            'ultimas_atividades' => $ultimas,
            'ultimo_teste' => [
                'em' => $cfg['ultimo_teste_em'],
                'status' => $cfg['ultimo_status'],
            ],
        ]);
    }

    public function logs(Request $request)
    {
        $u = $this->usuarioAtual($request);
        if (! $this->isGestor($u)) {
            return $this->json(['error' => 'Sem permissão.'], 403);
        }

        if (! Schema::hasTable('ayla_audit_logs')) {
            return $this->json(['logs' => [], 'total' => 0, 'pagina' => 1, 'por_pagina' => 20]);
        }

        $q = DB::table('ayla_audit_logs as l')->leftJoin('usuarios as us', 'l.user_id', '=', 'us.id');

        if ($request->filled('status')) {
            $q->where('l.status', strtolower(trim((string) $request->query('status'))));
        }
        if ($request->filled('acao')) {
            $q->where('l.acao', 'like', '%'.trim((string) $request->query('acao')).'%');
        }
        if ($request->filled('rota')) {
            $q->where('l.rota', 'like', '%'.trim((string) $request->query('rota')).'%');
        }
        if ($request->filled('usuario_id') && ctype_digit((string) $request->query('usuario_id'))) {
            $q->where('l.user_id', (int) $request->query('usuario_id'));
        }
        if ($request->filled('de')) {
            $q->whereDate('l.created_at', '>=', $request->query('de'));
        }
        if ($request->filled('ate')) {
            $q->whereDate('l.created_at', '<=', $request->query('ate'));
        }

        $porPagina = min(100, max(10, (int) $request->query('por_pagina', 20)));
        $pagina = max(1, (int) $request->query('pagina', 1));
        $total = (clone $q)->count();

        $logs = $q->orderByDesc('l.id')
            ->forPage($pagina, $porPagina)
            ->get(['l.id', 'l.acao', 'l.rota', 'l.metodo', 'l.status', 'l.http_status', 'l.duracao_ms', 'l.resposta_resumo', 'l.created_at', 'us.nome as usuario_nome'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'acao' => $r->acao,
                'rota' => $r->rota,
                'metodo' => $r->metodo,
                'status' => $r->status,
                'http_status' => $r->http_status,
                'duracao_ms' => $r->duracao_ms,
                'usuario' => $r->usuario_nome,
                'resumo' => $this->resumoSeguro($r->resposta_resumo),
                'quando' => $r->created_at,
            ])->all();

        return $this->json([
            'total' => $total,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'logs' => $logs,
        ]);
    }

    public function config(Request $request)
    {
        $u = $this->usuarioAtual($request);
        if (! $this->isAdmin($u)) {
            return $this->json(['error' => 'Somente administrador pode acessar.'], 403);
        }

        $unidades = Schema::hasTable('unidades')
            ? DB::table('unidades')->orderBy('nome')->get(['id', 'nome'])
            : collect();

        return $this->json([
            'config' => AylaSettings::paraPainel(),
            'unidades' => $unidades,
        ]);
    }

    public function updateConfig(Request $request)
    {
        $u = $this->usuarioAtual($request);
        if (! $this->isAdmin($u)) {
            return $this->json(['error' => 'Somente administrador pode alterar.'], 403);
        }
        if (! Schema::hasTable('sistema_configuracoes')) {
            return $this->json(['error' => 'Execute as migrations do sistema.'], 503);
        }

        $bool = fn ($v) => ! empty($v) && ! in_array($v, [false, '0', 'false', 'off', 'nao'], true) ? '1' : '0';

        AylaSettings::salvarChave('ayla_ativa', $bool($request->input('ativa')));
        AylaSettings::salvarChave('ayla_read_only', $bool($request->input('read_only', true)));
        AylaSettings::salvarChave('ayla_api_url', mb_substr(trim((string) $request->input('api_url', '')), 0, 500));
        AylaSettings::salvarChave('ayla_gateway_url', mb_substr(trim((string) $request->input('gateway_url', '')), 0, 500));

        $rate = (int) $request->input('rate_limit', 60);
        AylaSettings::salvarChave('ayla_rate_limit', (string) max(1, min(1000, $rate)));

        $unidades = $request->input('unidades_globais', []);
        if (! is_array($unidades)) {
            $unidades = [];
        }
        AylaSettings::salvarChave('ayla_unidades_globais', json_encode(
            array_values(array_filter(array_map('intval', $unidades), fn ($id) => $id > 0))
        ));

        AylaSettings::salvarChave('ayla_msg_nao_autorizado', mb_substr((string) $request->input('msg_nao_autorizado', ''), 0, 500));
        AylaSettings::salvarChave('ayla_msg_boas_vindas', mb_substr((string) $request->input('msg_boas_vindas', ''), 0, 500));

        AylaSettings::salvarChave('ayla_telegram_ativo', $bool($request->input('telegram_ativo')));
        AylaSettings::salvarChave('ayla_telegram_bot_username', mb_substr(trim((string) $request->input('telegram_bot_username', '')), 0, 120));

        AylaSettings::salvarChave('ayla_audio_ativo', $bool($request->input('audio_ativo')));
        $provider = strtolower(trim((string) $request->input('audio_provider', 'openai')));
        AylaSettings::salvarChave('ayla_audio_provider', in_array($provider, ['openai', 'microsoft'], true) ? $provider : 'openai');
        AylaSettings::salvarChave('ayla_audio_voice', mb_substr(trim((string) $request->input('audio_voice', '')), 0, 60));
        AylaSettings::salvarChave('ayla_audio_inbound_only', $bool($request->input('audio_inbound_only')));

        $this->auditar($u, 'ayla.admin.config.salvar', ['ok' => true]);

        return $this->json(['ok' => true, 'config' => AylaSettings::paraPainel()]);
    }

    public function gerarToken(Request $request)
    {
        $u = $this->usuarioAtual($request);
        if (! $this->isAdmin($u)) {
            return $this->json(['error' => 'Somente administrador pode gerar token.'], 403);
        }

        $token = AylaSettings::gerarToken();
        AylaSettings::salvarChave(AylaSettings::CHAVE_TOKEN, $token);

        $this->auditar($u, 'ayla.admin.token.gerar', ['gerado' => true]);

        return $this->json([
            'ok' => true,
            'token' => $token,
            'token_mascarado' => AylaSettings::mascararToken($token),
            'aviso' => 'Copie o token agora — ele não será mostrado novamente. Atualize também AYLA_SAS_TOKEN no .env do servidor e na VPS do OpenClaw.',
            'env_prioritario' => trim((string) config('ayla.token', '')) !== '',
        ]);
    }

    public function testarConexao(Request $request)
    {
        $u = $this->usuarioAtual($request);
        if (! $this->isAdmin($u)) {
            return $this->json(['error' => 'Somente administrador pode testar.'], 403);
        }

        $token = AylaSettings::tokenEfetivo();
        if ($token === '') {
            return $this->json(['ok' => false, 'message' => 'Gere um token antes de testar.'], 422);
        }

        $apiUrl = rtrim((string) config('app.url'), '/').'/api/ayla/v1/status';

        try {
            $resp = Http::timeout(15)->withToken($token)->acceptJson()->get($apiUrl);
            $ok = $resp->successful();
            $body = $resp->json();

            $status = $ok ? 'online' : 'erro';
            AylaSettings::salvarChave('ayla_ultimo_teste_em', now()->toDateTimeString());
            AylaSettings::salvarChave('ayla_ultimo_status', $status);
            $this->auditar($u, 'ayla.admin.testar', ['ok' => $ok, 'http' => $resp->status()], $ok ? 'ok' : 'erro', $resp->status());

            return $this->json([
                'ok' => $ok,
                'message' => $ok ? 'A Ayla respondeu corretamente.' : 'A API respondeu com erro (HTTP '.$resp->status().').',
                'detalhe' => [
                    'read_only' => is_array($body) ? ($body['data']['read_only'] ?? null) : null,
                    'versao' => is_array($body) ? ($body['data']['versao'] ?? null) : null,
                ],
            ]);
        } catch (\Throwable $e) {
            AylaSettings::salvarChave('ayla_ultimo_teste_em', now()->toDateTimeString());
            AylaSettings::salvarChave('ayla_ultimo_status', 'offline');

            return $this->json([
                'ok' => false,
                'message' => 'Não foi possível conectar à API da Ayla.',
            ], 502);
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function validarPayload(Request $request, ?AylaUsuarioAutorizado $existente = null): array
    {
        $usuarioId = (int) $request->input('usuario_id', $existente->usuario_id ?? 0);
        if ($usuarioId < 1) {
            return ['error' => 'Selecione um usuário do SAS.'];
        }

        $status = strtolower(trim((string) $request->input('status', $existente->status ?? 'pendente')));
        if (! in_array($status, AylaUsuarioAutorizado::STATUS, true)) {
            return ['error' => 'Status inválido.'];
        }

        $telegramId = trim((string) $request->input('telegram_user_id', $existente->telegram_user_id ?? ''));
        if ($telegramId !== '' && ! preg_match('/^[0-9]{3,32}$/', $telegramId)) {
            return ['error' => 'Telegram User ID deve conter apenas números.'];
        }

        $modulos = $request->input('modulos_permitidos', $existente->modulos_permitidos ?? []);
        if (! is_array($modulos)) {
            $modulos = [];
        }
        $modulos = array_values(array_intersect(array_keys(self::MODULOS_DISPONIVEIS), array_map('strval', $modulos)));

        $unidades = $request->input('unidades_permitidas', $existente->unidades_permitidas ?? []);
        if (! is_array($unidades)) {
            $unidades = [];
        }
        $unidades = array_values(array_filter(array_map('intval', $unidades), fn ($id) => $id > 0));

        $bool = fn ($k, $def) => filter_var($request->input($k, $def), FILTER_VALIDATE_BOOLEAN);

        return [
            'usuario_id' => $usuarioId,
            'telegram_user_id' => $telegramId !== '' ? $telegramId : null,
            'telegram_username' => mb_substr(trim((string) $request->input('telegram_username', $existente->telegram_username ?? '')), 0, 120) ?: null,
            'telegram_nome' => mb_substr(trim((string) $request->input('telegram_nome', $existente->telegram_nome ?? '')), 0, 160) ?: null,
            'cargo' => mb_substr(trim((string) $request->input('cargo', $existente->cargo ?? '')), 0, 120) ?: null,
            'unidades_permitidas' => $unidades,
            'modulos_permitidos' => $modulos,
            'pode_usar_texto' => $bool('pode_usar_texto', $existente->pode_usar_texto ?? true),
            'pode_usar_audio' => $bool('pode_usar_audio', $existente->pode_usar_audio ?? true),
            'pode_consultar_dados' => $bool('pode_consultar_dados', $existente->pode_consultar_dados ?? true),
            // Escrita liberada apenas se a API não estiver em somente leitura.
            'pode_executar_acoes' => AylaSettings::somenteLeitura()
                ? false
                : $bool('pode_executar_acoes', $existente->pode_executar_acoes ?? false),
            'status' => $status,
            'observacoes' => mb_substr(trim((string) $request->input('observacoes', $existente->observacoes ?? '')), 0, 1000) ?: null,
        ];
    }

    private function conflitoTelegram(?string $telegramId, string $status, ?int $ignoreId, ?int $mesmoUsuarioId = null): ?string
    {
        $telegramId = trim((string) $telegramId);
        if ($telegramId === '' || $status !== 'ativo') {
            return null;
        }

        $q = AylaUsuarioAutorizado::where('telegram_user_id', $telegramId)->where('status', 'ativo');
        if ($ignoreId) {
            $q->where('id', '!=', $ignoreId);
        }
        if ($mesmoUsuarioId) {
            $q->where('usuario_id', '!=', $mesmoUsuarioId);
        }

        return $q->exists() ? 'Já existe um acesso ativo com este Telegram User ID.' : null;
    }

    /** @return array<string, mixed> */
    private function serializar(object $r): array
    {
        return [
            'id' => $r->id,
            'usuario_id' => $r->usuario_id,
            'usuario_nome' => $r->usuario_nome ?? null,
            'usuario_perfil' => $r->usuario_perfil ?? null,
            'usuario_email' => $r->usuario_email ?? null,
            'unidade_nome' => $r->unidade_nome ?? null,
            'telegram_user_id' => $r->telegram_user_id,
            'telegram_username' => $r->telegram_username,
            'telegram_nome' => $r->telegram_nome,
            'cargo' => $r->cargo,
            'unidades_permitidas' => $this->jsonArr($r->unidades_permitidas),
            'modulos_permitidos' => $this->jsonArr($r->modulos_permitidos),
            'pode_usar_texto' => (bool) $r->pode_usar_texto,
            'pode_usar_audio' => (bool) $r->pode_usar_audio,
            'pode_consultar_dados' => (bool) $r->pode_consultar_dados,
            'pode_executar_acoes' => (bool) $r->pode_executar_acoes,
            'status' => $r->status,
            'ultimo_acesso_em' => $r->ultimo_acesso_em,
            'autorizado_em' => $r->autorizado_em,
            'observacoes' => $r->observacoes,
        ];
    }

    /** @return array<int, mixed> */
    private function jsonArr($valor): array
    {
        if (is_array($valor)) {
            return $valor;
        }
        if (is_string($valor) && $valor !== '') {
            $d = json_decode($valor, true);

            return is_array($d) ? $d : [];
        }

        return [];
    }

    private function resumoSeguro($valor): array
    {
        $arr = $this->jsonArr($valor);
        unset($arr['authorization'], $arr['token'], $arr['password'], $arr['senha'], $arr['api_key'], $arr['secret'], $arr['cpf']);

        return $arr;
    }
}
