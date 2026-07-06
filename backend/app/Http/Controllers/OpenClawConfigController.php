<?php

namespace App\Http\Controllers;

use App\Models\AiAssistantLog;
use App\Support\OpenClaw\OpenClawSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class OpenClawConfigController extends Controller
{
    private function authAdmin(Request $request): ?object
    {
        $uid = $request->header('X-Usuario-Id');
        if (! $uid) {
            return null;
        }
        $u = DB::table('usuarios')->where('id', $uid)->where('ativo', 1)->first();
        $perfil = strtoupper(trim((string) ($u->perfil ?? '')));
        // Compatibilidade: algumas instalações gravam "ADMINISTRADOR" em vez de "ADMIN".
        if (! $u || ! in_array($perfil, ['ADMIN', 'ADMINISTRADOR'], true)) {
            return null;
        }

        return $u;
    }

    private function json(array $data, int $code = 200)
    {
        return response()->json($data, $code)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }

    public function show(Request $request)
    {
        $u = $this->authAdmin($request);
        if (! $u) {
            return $this->json(['error' => 'Somente administrador pode acessar.'], 403);
        }

        $unidades = Schema::hasTable('unidades')
            ? DB::table('unidades')->orderBy('nome')->get(['id', 'nome'])
            : collect();

        return $this->json([
            'config' => OpenClawSettings::paraPainel(),
            'unidades' => $unidades,
        ]);
    }

    public function update(Request $request)
    {
        $u = $this->authAdmin($request);
        if (! $u) {
            return $this->json(['error' => 'Somente administrador pode alterar.'], 403);
        }

        if (! Schema::hasTable('sistema_configuracoes')) {
            return $this->json(['error' => 'Execute as migrations do sistema.'], 503);
        }

        $ativo = $request->input('ativo', $request->input('openclaw_ativo'));
        OpenClawSettings::salvarChave(
            'openclaw_ativo',
            ! empty($ativo) && ! in_array($ativo, [false, '0', 'false', 'off'], true) ? '1' : '0'
        );

        $url = trim((string) $request->input('url', $request->input('openclaw_url', '')));
        OpenClawSettings::salvarChave('openclaw_url', mb_substr($url, 0, 500));

        $unidades = $request->input('unidades_permitidas', $request->input('openclaw_unidades_permitidas', []));
        if (! is_array($unidades)) {
            $unidades = [];
        }
        OpenClawSettings::salvarChave(
            'openclaw_unidades_permitidas',
            json_encode(array_values(array_filter(array_map('intval', $unidades), fn ($id) => $id > 0)))
        );

        $acoes = $request->input('acoes_permitidas', $request->input('openclaw_acoes_permitidas', []));
        if (! is_array($acoes)) {
            $acoes = [];
        }
        $acoesValidas = array_values(array_intersect(
            array_keys(OpenClawSettings::ACOES_DISPONIVEIS),
            array_map('strval', $acoes)
        ));
        OpenClawSettings::salvarChave('openclaw_acoes_permitidas', json_encode($acoesValidas));

        if (Schema::hasTable('audit_logs')) {
            DB::table('audit_logs')->insert([
                'usuario_id' => $u->id,
                'acao' => 'atualizar',
                'recurso' => 'openclaw_config',
                'descricao' => 'Configurações OpenClaw / Assistente IA',
                'created_at' => now(),
            ]);
        }

        return $this->json(['ok' => true, 'config' => OpenClawSettings::paraPainel()]);
    }

    public function gerarToken(Request $request)
    {
        $u = $this->authAdmin($request);
        if (! $u) {
            return $this->json(['error' => 'Somente administrador pode gerar token.'], 403);
        }

        $token = OpenClawSettings::gerarToken();
        OpenClawSettings::salvarChave('openclaw_sas_token', $token);

        return $this->json([
            'ok' => true,
            'token' => $token,
            'token_mascarado' => OpenClawSettings::mascararToken($token),
            'aviso' => 'Copie o token agora. Também configure OPENCLAW_SAS_TOKEN no .env do servidor.',
        ]);
    }

    public function testarConexao(Request $request)
    {
        $u = $this->authAdmin($request);
        if (! $u) {
            return $this->json(['error' => 'Somente administrador pode testar.'], 403);
        }

        $token = OpenClawSettings::tokenEfetivo();
        if ($token === '') {
            return $this->json(['ok' => false, 'message' => 'Gere um token antes de testar.'], 422);
        }

        $apiUrl = rtrim((string) config('app.url'), '/').'/api/ia/estoque-baixo';
        $openclawUrl = trim(OpenClawSettings::ler()['openclaw_url'] ?? '');

        try {
            $resp = Http::timeout(15)
                ->withToken($token)
                ->acceptJson()
                ->get($apiUrl, ['limite' => 3]);

            $apiOk = $resp->successful();
            $apiBody = $resp->json();

            $openclawOk = null;
            $openclawMsg = 'URL do OpenClaw não informada.';
            if ($openclawUrl !== '') {
                try {
                    $oc = Http::timeout(8)->get(rtrim($openclawUrl, '/').'/api/v1/health');
                    $openclawOk = $oc->successful();
                    $openclawMsg = $openclawOk ? 'OpenClaw respondeu OK.' : 'OpenClaw não respondeu (HTTP '.$oc->status().').';
                } catch (\Throwable $e) {
                    $openclawOk = false;
                    $openclawMsg = 'Falha ao contactar OpenClaw: '.mb_substr($e->getMessage(), 0, 120);
                }
            }

            return $this->json([
                'ok' => $apiOk,
                'message' => $apiOk
                    ? 'API SAS-Estoque respondeu corretamente com o token.'
                    : 'API SAS-Estoque retornou erro HTTP '.$resp->status().'.',
                'api' => [
                    'url' => $apiUrl,
                    'status' => $resp->status(),
                    'success' => $apiOk,
                    'preview' => is_array($apiBody) ? ($apiBody['message'] ?? null) : null,
                ],
                'openclaw' => [
                    'url' => $openclawUrl ?: null,
                    'success' => $openclawOk,
                    'message' => $openclawMsg,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'ok' => false,
                'message' => 'Falha no teste: '.mb_substr($e->getMessage(), 0, 200),
            ], 502);
        }
    }

    public function logs(Request $request)
    {
        $u = $this->authAdmin($request);
        if (! $u) {
            return $this->json(['error' => 'Somente administrador pode ver logs.'], 403);
        }

        if (! Schema::hasTable('ai_assistant_logs')) {
            return $this->json(['logs' => [], 'total' => 0]);
        }

        $limit = min(100, max(10, (int) $request->input('limit', 50)));

        $logs = AiAssistantLog::query()
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'origem', 'comando', 'acao', 'status', 'payload', 'resposta', 'created_at']);

        return $this->json([
            'total' => $logs->count(),
            'logs' => $logs,
        ]);
    }
}
