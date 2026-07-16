<?php

namespace App\Http\Controllers;

use App\Models\AylaAuditLog;
use App\Models\AylaUsuarioAutorizado;
use App\Services\Ayla\AylaConviteService;
use App\Services\Ayla\AylaTelegramSyncService;
use App\Support\Ayla\AylaTelefone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AylaConviteController extends Controller
{
    public function __construct(
        private readonly AylaConviteService $convites,
        private readonly AylaTelegramSyncService $sync,
    ) {}

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

    private function json(array $data, int $code = 200)
    {
        return response()->json($data, $code)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }

    private function auditar(?object $u, string $acao, array $resumo): void
    {
        AylaAuditLog::registrar([
            'user_id' => $u->id ?? null,
            'ip' => request()->ip(),
            'metodo' => request()->method(),
            'rota' => request()->path(),
            'acao' => $acao,
            'payload' => $this->sanitizar($resumo),
            'resposta_resumo' => $resumo,
            'status' => 'ok',
            'http_status' => 200,
        ]);
    }

    private function sanitizar(array $data): array
    {
        unset($data['convite_token'], $data['token'], $data['bearer']);

        if (isset($data['telefone_telegram'])) {
            $data['telefone_telegram'] = AylaTelefone::mascarar((string) $data['telefone_telegram']);
        }

        return $data;
    }

    private function acessoOrFail(int $id): ?AylaUsuarioAutorizado
    {
        if (! Schema::hasTable('ayla_usuarios_autorizados')) {
            return null;
        }

        return AylaUsuarioAutorizado::query()->find($id);
    }

    public function show(Request $request, $id)
    {
        $u = $this->usuarioAtual($request);
        if (! $this->isAdmin($u)) {
            return $this->json(['error' => 'Somente administrador.'], 403);
        }

        $acesso = $this->acessoOrFail((int) $id);
        if (! $acesso) {
            return $this->json(['error' => 'Registro não encontrado.'], 404);
        }

        return $this->json([
            'success' => true,
            'data' => $this->convites->statusParaPainel($acesso, true),
        ]);
    }

    public function gerar(Request $request, $id)
    {
        $u = $this->usuarioAtual($request);
        if (! $this->isAdmin($u)) {
            return $this->json(['error' => 'Somente administrador.'], 403);
        }

        $acesso = $this->acessoOrFail((int) $id);
        if (! $acesso) {
            return $this->json(['error' => 'Registro não encontrado.'], 404);
        }

        $telefone = $request->input('telefone_telegram', $acesso->telefone_telegram);
        $result = $this->convites->gerar($acesso, (int) $u->id, is_string($telefone) ? $telefone : null);

        if (! ($result['success'] ?? false)) {
            return $this->json($result, 422);
        }

        $data = $result['data'];
        $nome = DB::table('usuarios')->where('id', $acesso->usuario_id)->value('nome');
        $data['mensagem_whatsapp'] = $this->convites->mensagemWhatsApp($acesso->fresh(), $data['convite_url'], $nome);
        $data['whatsapp_url'] = 'https://wa.me/'.AylaTelefone::paraWhatsApp($acesso->fresh()->telefone_telegram).'?text='.rawurlencode($data['mensagem_whatsapp']);

        unset($data['convite_token']);

        $this->auditar($u, 'ayla.convite.criar', [
            'ayla_usuario_id' => $acesso->id,
            'usuario_id' => $acesso->usuario_id,
            'telefone_telegram' => $acesso->telefone_telegram,
            'expira_em' => $data['expira_em'] ?? null,
        ]);

        return $this->json(['success' => true, 'data' => $data]);
    }

    public function renovar(Request $request, $id)
    {
        $u = $this->usuarioAtual($request);
        if (! $this->isAdmin($u)) {
            return $this->json(['error' => 'Somente administrador.'], 403);
        }

        $acesso = $this->acessoOrFail((int) $id);
        if (! $acesso) {
            return $this->json(['error' => 'Registro não encontrado.'], 404);
        }

        $result = $this->convites->renovar($acesso, (int) $u->id);
        if (! ($result['success'] ?? false)) {
            return $this->json($result, 422);
        }

        $data = $result['data'];
        unset($data['convite_token']);

        $this->auditar($u, 'ayla.convite.renovar', [
            'ayla_usuario_id' => $acesso->id,
            'telefone_telegram' => $acesso->telefone_telegram,
        ]);

        return $this->json(['success' => true, 'data' => $data]);
    }

    public function cancelar(Request $request, $id)
    {
        $u = $this->usuarioAtual($request);
        if (! $this->isAdmin($u)) {
            return $this->json(['error' => 'Somente administrador.'], 403);
        }

        $acesso = $this->acessoOrFail((int) $id);
        if (! $acesso) {
            return $this->json(['error' => 'Registro não encontrado.'], 404);
        }

        $n = $this->convites->cancelarPendentes($acesso, (int) $u->id);
        $this->auditar($u, 'ayla.convite.cancelar', ['ayla_usuario_id' => $acesso->id, 'cancelados' => $n]);

        return $this->json(['success' => true, 'cancelados' => $n]);
    }

    public function sincronizar(Request $request, $id)
    {
        $u = $this->usuarioAtual($request);
        if (! $this->isAdmin($u)) {
            return $this->json(['error' => 'Somente administrador.'], 403);
        }

        $acesso = $this->acessoOrFail((int) $id);
        if (! $acesso || ! $acesso->telegram_user_id) {
            return $this->json(['error' => 'Usuário sem Telegram vinculado.'], 422);
        }

        $sync = $this->sync->adicionarAllowlist((string) $acesso->telegram_user_id);
        if ($sync['success'] ?? false) {
            $acesso->update([
                'telegram_sync_status' => 'ok',
                'telegram_sync_erro' => null,
                'telegram_sincronizado_em' => now(),
            ]);
        } else {
            $acesso->update([
                'telegram_sync_status' => 'erro',
                'telegram_sync_erro' => $sync['message'] ?? 'Erro',
            ]);
        }

        $this->auditar($u, 'ayla.telegram.sincronizar', [
            'ayla_usuario_id' => $acesso->id,
            'telegram_user_id' => $acesso->telegram_user_id,
            'ok' => $sync['success'] ?? false,
        ]);

        return $this->json([
            'success' => (bool) ($sync['success'] ?? false),
            'message' => $sync['message'] ?? null,
            'data' => $this->convites->statusParaPainel($acesso->fresh(), true),
        ], ($sync['success'] ?? false) ? 200 : 502);
    }

    public function desvincular(Request $request, $id)
    {
        $u = $this->usuarioAtual($request);
        if (! $this->isAdmin($u)) {
            return $this->json(['error' => 'Somente administrador.'], 403);
        }

        $acesso = $this->acessoOrFail((int) $id);
        if (! $acesso) {
            return $this->json(['error' => 'Registro não encontrado.'], 404);
        }

        $tgId = $acesso->telegram_user_id;
        if ($tgId) {
            $this->sync->removerAllowlist((string) $tgId);
        }

        $acesso->update([
            'telegram_user_id' => null,
            'telegram_username' => null,
            'telegram_nome' => null,
            'telegram_vinculado_em' => null,
            'telegram_sync_status' => null,
            'telegram_sync_erro' => null,
            'telegram_sincronizado_em' => null,
            'status' => 'pendente',
        ]);

        $this->auditar($u, 'ayla.telegram.desvincular', [
            'ayla_usuario_id' => $acesso->id,
            'telegram_user_id' => $tgId,
        ]);

        return $this->json(['success' => true, 'data' => $this->convites->statusParaPainel($acesso->fresh(), true)]);
    }
}
