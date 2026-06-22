<?php

namespace App\Http\Controllers;

use App\Services\SasIaChatService;
use App\Services\SasIaDocumentService;
use App\Support\AiAgentResolver;
use App\Support\SasIa\SasIaBranding;
use App\Support\SasIa\SasIaContext;
use App\Support\SasIa\SasIaDocumentTextExtractor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\AiConversation;
use App\Services\OpenAiService;

/**
 * API do módulo SAS IA — chat, conversas e documentos.
 */
class SasIaController extends Controller
{
    public function __construct(
        private SasIaChatService $chatService,
        private SasIaDocumentService $documentService,
        private OpenAiService $openAi
    ) {}

    /** GET /sas-ia — status do módulo e limites do usuário. */
    public function index(Request $request)
    {
        $usuario = $this->authUsuario($request);
        if (! $usuario) {
            return $this->json(['error' => 'Não autorizado'], 401);
        }

        $ctx = new SasIaContext($usuario, $this->unidadeRequest($request));
        $agent = AiAgentResolver::resolveForModule(AiAgentResolver::DEFAULT_MODULE);
        $branding = SasIaBranding::ler();
        $agenteNome = $agent?->name ?: $branding['nome'];
        $agenteFoto = $agent?->avatar ?: $branding['foto'];

        return $this->json([
            'modulo' => $agenteNome,
            'agente_nome' => $agenteNome,
            'agente_foto' => $agenteFoto,
            'agent_id' => $agent?->id,
            'ativo' => $this->openAi->isConfigured(),
            'modelo' => $agent?->model ?: $this->openAi->model(),
            'limite_diario' => $ctx->limiteDiario(),
            'usadas_hoje' => $ctx->perguntasHoje(),
            'restante_hoje' => $ctx->restanteHoje(),
            'documentos' => count($this->documentService->listarAtivos()),
        ]);
    }

    /** POST /sas-ia/chat */
    public function chat(Request $request)
    {
        $usuario = $this->authUsuario($request);
        if (! $usuario) {
            return $this->json(['error' => 'Não autorizado'], 401);
        }

        $ctx = new SasIaContext($usuario, $this->unidadeRequest($request));
        $mensagem = trim((string) $request->input('message', $request->input('mensagem', '')));
        $conversationId = $request->input('conversation_id');
        $conversationId = ($conversationId !== null && $conversationId !== '') ? (int) $conversationId : null;
        $module = $request->input('module', $request->input('modulo', AiAgentResolver::DEFAULT_MODULE));

        try {
            $result = $this->chatService->processar($ctx, $mensagem, $conversationId, $module);
        } catch (\Throwable $e) {
            report($e);

            return $this->json([
                'error' => 'Falha ao consultar a IA: '.mb_substr($e->getMessage(), 0, 300),
            ], 502);
        }

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['code'] ?? 400);
        }

        return $this->json($result);
    }

    /** POST /sas-ia/upload-documento — cadastro de manual (ADMIN). Aceita JSON ou multipart com arquivo. */
    public function uploadDocumento(Request $request)
    {
        $arquivoSalvo = null;

        try {
            $usuario = $this->authUsuario($request);
            if (! $usuario) {
                return $this->json(['error' => 'Não autorizado'], 401);
            }

            if (strtoupper(trim((string) ($usuario->perfil ?? ''))) !== 'ADMIN') {
                return $this->json(['error' => 'Somente administrador pode cadastrar documentos.'], 403);
            }

            $titulo = trim((string) $request->input('titulo', ''));
            if ($titulo === '') {
                return $this->json(['error' => 'Título é obrigatório.'], 422);
            }

            $conteudo = trim((string) $request->input('conteudo_texto', $request->input('conteudo', '')));
            $arquivoPath = null;

            if ($request->hasFile('arquivo')) {
                $file = $request->file('arquivo');
                if (! $file->isValid()) {
                    $erroUpload = (string) ($file->getErrorMessage() ?: 'Arquivo inválido.');

                    return $this->json(['error' => $erroUpload], 422);
                }
                if ($file->getSize() > 5 * 1024 * 1024) {
                    return $this->json(['error' => 'Arquivo muito grande (máx. 5 MB).'], 422);
                }

                $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
                if (! in_array($ext, SasIaDocumentTextExtractor::extensoesPermitidas(), true)) {
                    return $this->json([
                        'error' => 'Formato não suportado. Use: '.implode(', ', SasIaDocumentTextExtractor::extensoesPermitidas()).'.',
                    ], 422);
                }

                $dir = public_path('uploads/sas-ia/docs');
                if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
                    return $this->json([
                        'error' => 'Não foi possível criar a pasta uploads/sas-ia/docs no servidor. Verifique permissões.',
                    ], 500);
                }

                $nomeArquivo = 'doc_'.time().'_'.uniqid().'.'.$ext;
                try {
                    $file->move($dir, $nomeArquivo);
                } catch (\Throwable $e) {
                    report($e);

                    return $this->json([
                        'error' => 'Não foi possível salvar o arquivo. Verifique permissões da pasta uploads/sas-ia/docs.',
                    ], 500);
                }

                $arquivoSalvo = $dir.DIRECTORY_SEPARATOR.$nomeArquivo;
                $arquivoPath = 'uploads/sas-ia/docs/'.$nomeArquivo;

                try {
                    $extraido = SasIaDocumentTextExtractor::fromPath($arquivoSalvo, $ext);
                } catch (\InvalidArgumentException $e) {
                    @unlink($arquivoSalvo);

                    return $this->json(['error' => $e->getMessage()], 422);
                } catch (\Throwable $e) {
                    report($e);
                    @unlink($arquivoSalvo);

                    return $this->json(['error' => 'Falha ao ler o arquivo. Tente outro formato ou cole o texto.'], 422);
                }

                $conteudo = $conteudo !== ''
                    ? mb_substr($conteudo."\n\n".$extraido, 0, 50000)
                    : $extraido;
            }

            if ($conteudo === '') {
                return $this->json(['error' => 'Envie um arquivo ou cole o texto do documento.'], 422);
            }

            $doc = $this->documentService->criar([
                'titulo' => $titulo,
                'tipo' => $request->input('tipo', 'manual'),
                'conteudo_texto' => mb_substr($conteudo, 0, 50000),
                'arquivo_path' => $arquivoPath,
            ], (int) $usuario->id);

            return $this->json(['ok' => true, 'documento' => [
                'id' => $doc->id,
                'titulo' => $doc->titulo,
                'tipo' => $doc->tipo,
                'tem_arquivo' => ! empty($arquivoPath),
                'tamanho_texto' => mb_strlen((string) $doc->conteudo_texto),
            ]], 201);
        } catch (\Throwable $e) {
            report($e);
            if (! empty($arquivoSalvo) && is_file($arquivoSalvo)) {
                @unlink($arquivoSalvo);
            }

            return $this->json([
                'error' => 'Erro ao salvar documento: '.mb_substr($e->getMessage(), 0, 250),
            ], 500);
        }
    }

    /** GET /sas-ia/conversas */
    public function conversas(Request $request)
    {
        $usuario = $this->authUsuario($request);
        if (! $usuario) {
            return $this->json(['error' => 'Não autorizado'], 401);
        }

        if (! Schema::hasTable('ai_conversations')) {
            return $this->json(['conversas' => []]);
        }

        $lista = AiConversation::query()
            ->where('usuario_id', (int) $usuario->id)
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get(['id', 'titulo', 'unidade_id', 'created_at', 'updated_at']);

        return $this->json(['conversas' => $lista]);
    }

    /** GET /sas-ia/conversas/{id} */
    public function conversaShow(Request $request, int $id)
    {
        $usuario = $this->authUsuario($request);
        if (! $usuario) {
            return $this->json(['error' => 'Não autorizado'], 401);
        }

        $c = AiConversation::query()
            ->where('id', $id)
            ->where('usuario_id', (int) $usuario->id)
            ->with(['messages' => fn ($q) => $q->whereIn('role', ['user', 'assistant'])->orderBy('created_at')])
            ->first();

        if (! $c) {
            return $this->json(['error' => 'Conversa não encontrada.'], 404);
        }

        return $this->json([
            'conversa' => [
                'id' => $c->id,
                'titulo' => $c->titulo,
                'unidade_id' => $c->unidade_id,
                'created_at' => $c->created_at,
                'updated_at' => $c->updated_at,
            ],
            'mensagens' => $c->messages->map(fn ($m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'tool_name' => $m->tool_name,
                'created_at' => $m->created_at,
            ]),
        ]);
    }

    /** DELETE /sas-ia/conversas/{id} — exclui conversa do usuário logado. */
    public function conversaDestroy(Request $request, int $id)
    {
        $usuario = $this->authUsuario($request);
        if (! $usuario) {
            return $this->json(['error' => 'Não autorizado'], 401);
        }

        $c = AiConversation::query()
            ->where('id', $id)
            ->where('usuario_id', (int) $usuario->id)
            ->first();

        if (! $c) {
            return $this->json(['error' => 'Conversa não encontrada.'], 404);
        }

        DB::transaction(function () use ($id) {
            if (Schema::hasTable('ai_tool_logs')) {
                DB::table('ai_tool_logs')->where('conversation_id', $id)->delete();
            }
            if (Schema::hasTable('ai_messages')) {
                DB::table('ai_messages')->where('conversation_id', $id)->delete();
            }
            DB::table('ai_conversations')->where('id', $id)->delete();
        });

        return $this->json(['ok' => true, 'id' => $id]);
    }

    /** GET /sas-ia/documentos — lista para ADMIN. */
    public function documentos(Request $request)
    {
        $usuario = $this->authUsuario($request);
        if (! $usuario) {
            return $this->json(['error' => 'Não autorizado'], 401);
        }

        return $this->json(['documentos' => $this->documentService->listarAtivos()]);
    }

    /** DELETE /sas-ia/documentos/{id} — remove documento da base (ADMIN). */
    public function documentoDestroy(Request $request, int $id)
    {
        $usuario = $this->authUsuario($request);
        if (! $usuario) {
            return $this->json(['error' => 'Não autorizado'], 401);
        }
        if (strtoupper(trim((string) ($usuario->perfil ?? ''))) !== 'ADMIN') {
            return $this->json(['error' => 'Somente administrador pode excluir documentos.'], 403);
        }

        if (! $this->documentService->excluir($id)) {
            return $this->json(['error' => 'Documento não encontrado.'], 404);
        }

        return $this->json(['ok' => true, 'id' => $id]);
    }

    /** GET /sas-ia/config — nome e foto do assistente (todos logados). */
    public function configShow(Request $request)
    {
        $usuario = $this->authUsuario($request);
        if (! $usuario) {
            return $this->json(['error' => 'Não autorizado'], 401);
        }

        $branding = SasIaBranding::ler();
        $isAdmin = strtoupper(trim((string) ($usuario->perfil ?? ''))) === 'ADMIN';

        return $this->json([
            'nome' => $branding['nome'],
            'foto' => $branding['foto'],
            'nome_padrao' => SasIaBranding::DEFAULT_NOME,
            'pode_editar' => $isAdmin,
        ]);
    }

    /** POST /sas-ia/config — salvar nome / remover foto (ADMIN). */
    public function configUpdate(Request $request)
    {
        $usuario = $this->authUsuario($request);
        if (! $usuario) {
            return $this->json(['error' => 'Não autorizado'], 401);
        }
        if (strtoupper(trim((string) ($usuario->perfil ?? ''))) !== 'ADMIN') {
            return $this->json(['error' => 'Somente administrador pode alterar configurações.'], 403);
        }

        if ($request->boolean('remover_foto') || in_array($request->input('remover_foto'), ['1', 'true', 'sim'], true)) {
            SasIaBranding::removerFoto();
        }

        if ($request->has('nome')) {
            SasIaBranding::salvarNome((string) $request->input('nome', ''));
        }

        $branding = SasIaBranding::ler();

        return $this->json(['ok' => true, 'nome' => $branding['nome'], 'foto' => $branding['foto']]);
    }

    /** POST /sas-ia/upload-foto — foto do assistente (ADMIN, multipart). */
    public function uploadFoto(Request $request)
    {
        $usuario = $this->authUsuario($request);
        if (! $usuario) {
            return $this->json(['error' => 'Não autorizado'], 401);
        }
        if (strtoupper(trim((string) ($usuario->perfil ?? ''))) !== 'ADMIN') {
            return $this->json(['error' => 'Somente administrador pode enviar foto.'], 403);
        }

        if (! $request->hasFile('foto')) {
            return $this->json(['error' => 'Selecione uma imagem.'], 422);
        }

        $foto = $request->file('foto');
        if (! $foto->isValid()) {
            return $this->json(['error' => 'Arquivo inválido.'], 422);
        }

        $ext = strtolower($foto->getClientOriginalExtension() ?: 'jpg');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return $this->json(['error' => 'Use JPG, PNG, WebP ou GIF.'], 422);
        }
        if ($foto->getSize() > 2 * 1024 * 1024) {
            return $this->json(['error' => 'Imagem muito grande (máx. 2 MB).'], 422);
        }

        SasIaBranding::removerFoto();

        $dir = public_path('uploads/sas-ia');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $nomeArquivo = 'agente_'.time().'.'.$ext;
        $foto->move($dir, $nomeArquivo);
        $path = 'uploads/sas-ia/'.$nomeArquivo;
        SasIaBranding::salvarFoto($path);

        return $this->json(['ok' => true, 'foto' => $path]);
    }

    private function authUsuario(Request $request): ?object
    {
        $uid = $request->header('X-Usuario-Id');
        if (! $uid) {
            return null;
        }

        return DB::table('usuarios')->where('id', $uid)->where('ativo', 1)->first();
    }

    private function unidadeRequest(Request $request): ?int
    {
        $v = $request->input('unidade_id', $request->query('unidade_id'));
        if ($v === null || $v === '') {
            return null;
        }

        return (int) $v;
    }

    private function json(mixed $data, int $code = 200)
    {
        return response()->json($data, $code)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id');
    }
}
