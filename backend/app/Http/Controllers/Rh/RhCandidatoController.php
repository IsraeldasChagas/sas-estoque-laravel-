<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Support\Rh\RhAcesso;
use App\Support\Rh\RhAuditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class RhCandidatoController extends Controller
{
    private static ?bool $temDataInscricao = null;

    private const STATUS = [
        'novo',
        'em_analise',
        'entrevista',
        'em_teste',
        'aprovado',
        'em_contratacao',
        'contratado',
        'reprovado',
        'banco_talentos',
    ];

    /**
     * CORS do GET /rh/candidatos/{id}/curriculo (frontend em outro domínio + fetch com Authorization / X-Usuario-Id).
     *
     * IMPORTANTE: response()->file() / response()->download() retornam Symfony BinaryFileResponse.
     * Não use ->header() em cadeia, use $response->headers->set(...) (mesmo padrão do Alvará).
     */
    private function aplicarCorsRespostaCurriculo(SymfonyResponse $response): SymfonyResponse
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->headers->set(
            'Access-Control-Allow-Headers',
            'Content-Type, Authorization, X-Usuario-Id, X-Device-Model, X-Device-Platform'
        );
        $response->headers->set('Access-Control-Expose-Headers', 'Content-Disposition, Content-Type, Content-Length');

        return $response;
    }

    public function index(Request $request)
    {
        if (! RhAcesso::pode($request, 'rh.candidatos')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        if (self::$temDataInscricao === null) {
            self::$temDataInscricao = Schema::hasColumn('rh_candidatos', 'data_inscricao');
        }

        $cols = [
            'rh_candidatos.id',
            'rh_candidatos.nome',
            'rh_candidatos.telefone',
            'rh_candidatos.email',
            'rh_candidatos.cidade',
            'rh_candidatos.bairro',
            'rh_candidatos.status',
            'rh_candidatos.vaga_id',
            'rh_candidatos.unidade',
            'rh_candidatos.consentimento_lgpd',
            'rh_candidatos.consentimento_em',
            'rh_candidatos.created_at',
            'rh_vagas.titulo as vaga_titulo',
        ];
        if (self::$temDataInscricao) {
            $cols[] = 'rh_candidatos.data_inscricao';
        }

        $q = DB::table('rh_candidatos')
            ->leftJoin('rh_vagas', 'rh_candidatos.vaga_id', '=', 'rh_vagas.id')
            ->select($cols)
            ->selectRaw("(rh_candidatos.foto_path IS NOT NULL AND rh_candidatos.foto_path <> '') as tem_foto")
            ->selectRaw("EXISTS (SELECT 1 FROM rh_curriculos cv WHERE cv.candidato_id = rh_candidatos.id AND cv.arquivo_path IS NOT NULL AND cv.arquivo_path <> '') as tem_curriculo")
            ->orderByDesc('rh_candidatos.id');

        if ($request->filled('status')) $q->where('rh_candidatos.status', $request->status);
        if ($request->filled('vaga_id')) $q->where('rh_candidatos.vaga_id', (int) $request->vaga_id);
        if ($request->filled('nome')) {
            $q->where('rh_candidatos.nome', 'like', '%' . $request->nome . '%');
        }

        $limit = (int) $request->query('limit', 0);
        if ($limit > 0) {
            $q->limit(min($limit, 100))->offset(max(0, (int) $request->query('offset', 0)));
        }

        $rows = $q->get();
        foreach ($rows as $row) {
            $thumb = storage_path('app/public/rh/thumbs/cand-'.$row->id.'.jpg');
            $row->foto_url = is_file($thumb) ? 'storage/rh/thumbs/cand-'.$row->id.'.jpg' : null;
        }

        return response()->json($rows)->header('Access-Control-Allow-Origin', '*');
    }

    public function fotosLote(Request $request)
    {
        if (! RhAcesso::pode($request, 'rh.candidatos')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        return response()->json([])->header('Access-Control-Allow-Origin', '*');
    }

    public function show(Request $request, int $id)
    {
        if (! RhAcesso::pode($request, 'rh.candidatos')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        $c = DB::table('rh_candidatos')->where('id', $id)->first();
        if (! $c) return response()->json(['error' => 'Candidato não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');

        $vaga = ! empty($c->vaga_id)
            ? DB::table('rh_vagas')->where('id', $c->vaga_id)->first(['id', 'titulo'])
            : null;
        $curriculo = DB::table('rh_curriculos')
            ->where('candidato_id', $id)
            ->whereNotNull('arquivo_path')
            ->where('arquivo_path', '<>', '')
            ->orderByDesc('id')
            ->first(['id', 'arquivo_nome_original', 'mime']);
        $documentos = DB::table('rh_documentos')
            ->where('candidato_id', $id)
            ->orderByDesc('id')
            ->get(['id', 'tipo', 'arquivo_nome_original', 'mime', 'created_at']);

        $docTokenGerado = $c->documentacao_token_gerado_em ?? null;
        $docPublicaAtiva = (bool) (! empty($c->documentacao_token_hash));
        $temFoto = ! empty($c->foto_path);

        unset(
            $c->experiencia,
            $c->consentimento_ip,
            $c->consentimento_user_agent,
            $c->documentacao_token_hash,
            $c->foto_path
        );

        return response()->json([
            'candidato' => $c,
            'vaga' => $vaga,
            'curriculo' => $curriculo,
            'tem_curriculo' => (bool) $curriculo,
            'tem_foto' => $temFoto,
            'documentos' => $documentos,
            'documentacao_publica_ativa' => $docPublicaAtiva,
            'documentacao_publica_gerada_em' => $docTokenGerado,
        ])->header('Access-Control-Allow-Origin', '*');
    }

    /**
     * Gera link público (token opaco) para o candidato enviar documentos de contratação em PDF.
     * Só permitido com status Aprovado ou Em contratação.
     */
    public function gerarLinkDocumentacao(Request $request, int $id)
    {
        if (! RhAcesso::pode($request, 'rh.candidatos') && ! RhAcesso::pode($request, 'rh.documentos')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        if (! Schema::hasTable('rh_candidatos') || ! Schema::hasColumn('rh_candidatos', 'documentacao_token_hash')) {
            return response()->json([
                'error' => 'Banco desatualizado: rode php artisan migrate (colunas documentacao_token no rh_candidatos).',
            ], 503)->header('Access-Control-Allow-Origin', '*');
        }

        $c = DB::table('rh_candidatos')->where('id', $id)->first();
        if (! $c) {
            return response()->json(['error' => 'Candidato não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
        }

        if (! empty($c->anonimizado_em)) {
            return response()->json(['error' => 'Candidato anonimizado.'], 422)->header('Access-Control-Allow-Origin', '*');
        }

        $status = strtolower(trim((string) ($c->status ?? '')));
        if (! in_array($status, ['aprovado', 'em_contratacao'], true)) {
            return response()->json([
                'error' => 'Salve o candidato com status Aprovado ou Em contratação antes de gerar o link.',
            ], 422)->header('Access-Control-Allow-Origin', '*');
        }

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $now = now();

        try {
            DB::table('rh_candidatos')->where('id', $id)->update([
                'documentacao_token_hash' => $hash,
                'documentacao_token_gerado_em' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'error' => 'Não foi possível gravar o link. Verifique migrations e o banco de dados.',
            ], 500)->header('Access-Control-Allow-Origin', '*');
        }

        // root() em chamadas /api/... vira https://host/api — a rota web /documentacao fica fora do prefixo api.
        $base = rtrim($request->root(), '/');
        if (str_ends_with($base, '/api')) {
            $base = substr($base, 0, -4);
        }
        if ($base === '') {
            $base = rtrim((string) config('app.url'), '/');
            if (str_ends_with($base, '/api')) {
                $base = substr($base, 0, -4);
            }
        }
        $url = $base . '/documentacao/' . $token;

        return response()->json([
            'url' => $url,
            'documentacao_publica_ativa' => true,
            'documentacao_publica_gerada_em' => $now->format('c'),
        ])->header('Access-Control-Allow-Origin', '*');
    }

    public function updateStatus(Request $request, int $id)
    {
        if (! RhAcesso::pode($request, 'rh.candidatos')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        $data = $request->validate([
            'status' => 'required|string|in:' . implode(',', self::STATUS),
        ]);

        $c = DB::table('rh_candidatos')->where('id', $id)->first();
        if (! $c) return response()->json(['error' => 'Candidato não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');

        DB::table('rh_candidatos')->where('id', $id)->update([
            'status' => $data['status'],
            'updated_at' => now(),
        ]);

        DB::table('rh_historico')->insert([
            'candidato_id' => $id,
            'usuario_id' => $request->header('X-Usuario-Id') ? (int) $request->header('X-Usuario-Id') : null,
            'status_antigo' => $c->status,
            'status_novo' => $data['status'],
            'data' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true])->header('Access-Control-Allow-Origin', '*');
    }

    public function updateObservacoes(Request $request, int $id)
    {
        if (! RhAcesso::pode($request, 'rh.candidatos')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        $data = $request->validate([
            'observacoes_internas' => 'nullable|string|max:20000',
        ]);

        if (! DB::table('rh_candidatos')->where('id', $id)->exists()) {
            return response()->json(['error' => 'Candidato não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
        }

        DB::table('rh_candidatos')->where('id', $id)->update([
            'observacoes_internas' => $data['observacoes_internas'] ?? null,
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true])->header('Access-Control-Allow-Origin', '*');
    }

    public function anonymize(Request $request, int $id)
    {
        if (! RhAcesso::pode($request, 'rh.candidatos')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        $c = DB::table('rh_candidatos')->where('id', $id)->first();
        if (! $c) return response()->json(['error' => 'Candidato não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');

        // Remove/anonimiza dados pessoais. Mantém id e vaga_id para métricas e histórico.
        DB::table('rh_candidatos')->where('id', $id)->update([
            'nome' => 'ANONIMIZADO',
            'telefone' => null,
            'email' => null,
            'cidade' => null,
            'bairro' => null,
            'experiencia' => null,
            'ultimo_emprego' => null,
            'disponibilidade' => null,
            'pretensao_salarial' => null,
            'foto_path' => null,
            'observacoes_internas' => null,
            'documentacao_token_hash' => null,
            'documentacao_token_gerado_em' => null,
            'anonimizado_em' => now(),
            'anonimizado_por' => $request->header('X-Usuario-Id') ? (int) $request->header('X-Usuario-Id') : null,
            'updated_at' => now(),
        ]);

        // Apaga arquivos armazenados (currículo e foto/documentos) para reduzir risco LGPD.
        if ($c->foto_path) {
            Storage::disk('public')->delete($c->foto_path);
        }
        Storage::disk('public')->delete('rh/thumbs/cand-'.$id.'.jpg');
        $curriculos = DB::table('rh_curriculos')->where('candidato_id', $id)->get();
        foreach ($curriculos as $cv) {
            if ($cv->arquivo_path) Storage::disk('public')->delete($cv->arquivo_path);
        }
        DB::table('rh_curriculos')->where('candidato_id', $id)->delete();

        $docs = DB::table('rh_documentos')->where('candidato_id', $id)->get();
        foreach ($docs as $d) {
            if ($d->arquivo_path) Storage::disk('public')->delete($d->arquivo_path);
        }
        DB::table('rh_documentos')->where('candidato_id', $id)->delete();

        return response()->json(['ok' => true])->header('Access-Control-Allow-Origin', '*');
    }

    /**
     * Exclusão definitiva (remove candidato + registros relacionados + arquivos).
     * Use quando a intenção for "apagar de vez" e não apenas LGPD/anônimizar.
     */
    public function destroyDefinitivo(Request $request, int $id)
    {
        if (! RhAcesso::pode($request, 'rh.candidatos')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        $c = DB::table('rh_candidatos')->where('id', $id)->first();
        if (! $c) return response()->json(['error' => 'Candidato não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');

        $disk = Storage::disk('public');

        // Coleta paths antes de apagar no banco.
        $paths = [];
        if (! empty($c->foto_path)) $paths[] = $c->foto_path;
        $paths[] = 'rh/thumbs/cand-'.$id.'.jpg';

        $curriculos = DB::table('rh_curriculos')->where('candidato_id', $id)->get();
        foreach ($curriculos as $cv) {
            if (! empty($cv->arquivo_path)) $paths[] = $cv->arquivo_path;
        }

        $docs = DB::table('rh_documentos')->where('candidato_id', $id)->get();
        foreach ($docs as $d) {
            if (! empty($d->arquivo_path)) $paths[] = $d->arquivo_path;
        }

        // Apaga arquivos (best effort).
        $paths = array_values(array_unique(array_filter($paths, fn ($p) => is_string($p) && trim($p) !== '')));

        DB::transaction(function () use ($id) {
            DB::table('rh_historico')->where('candidato_id', $id)->delete();
            DB::table('rh_entrevistas')->where('candidato_id', $id)->delete();
            DB::table('rh_documentos')->where('candidato_id', $id)->delete();
            DB::table('rh_curriculos')->where('candidato_id', $id)->delete();
            DB::table('rh_candidatos')->where('id', $id)->delete();
        });

        $uid = $request->header('X-Usuario-Id') ? (int) $request->header('X-Usuario-Id') : null;
        RhAuditoria::registrar(
            'candidato_excluido',
            'candidato',
            $id,
            $uid,
            $request->ip(),
            [
                'nome' => $c->nome ?? null,
                'vaga_id' => $c->vaga_id ?? null,
                'status' => $c->status ?? null,
            ]
        );

        // Remove arquivos depois que a resposta JSON já foi enviada — a UI some na hora; I/O no disco não trava o usuário.
        $pathsToDelete = $paths;
        app()->terminating(function () use ($disk, $pathsToDelete) {
            foreach ($pathsToDelete as $p) {
                try {
                    $disk->delete($p);
                } catch (\Throwable $_) {
                }
            }
        });

        return response()->json(['ok' => true])->header('Access-Control-Allow-Origin', '*');
    }

    public function downloadCurriculo(Request $request, int $id)
    {
        try {
            if (! RhAcesso::pode($request, 'rh.candidatos')) {
                return $this->aplicarCorsRespostaCurriculo(response()->json(['error' => 'Sem permissão.'], 403));
            }

            if (! DB::table('rh_candidatos')->where('id', $id)->exists()) {
                return $this->aplicarCorsRespostaCurriculo(response()->json(['error' => 'Candidato não encontrado'], 404));
            }

            $cv = DB::table('rh_curriculos')->where('candidato_id', $id)->orderByDesc('id')->first();
            if (! $cv || ! $cv->arquivo_path || ! Storage::disk('public')->exists($cv->arquivo_path)) {
                return $this->aplicarCorsRespostaCurriculo(response()->json(['error' => 'Currículo não encontrado'], 404));
            }

            // IMPORTANTE: não dependa de Storage::path() / arquivo local.
            // Em produção o disk pode não ser "local" (ex.: S3) e path() pode falhar.
            $disk = Storage::disk('public');
            $nome = $cv->arquivo_nome_original ?: ('curriculo-' . $id . '.pdf');
            $forcarDownload = $request->boolean('download', false);

            // Currículo é sempre PDF.
            $mime = 'application/pdf';

            $headers = [
                'Content-Type' => $mime,
                'Content-Security-Policy' => "frame-ancestors 'self' https://*.gruposaborparaense.com.br http://localhost:*",
                'X-Frame-Options' => 'ALLOWALL',
            ];

            $stream = $disk->readStream($cv->arquivo_path);
            if (! $stream) {
                return $this->aplicarCorsRespostaCurriculo(response()->json(['error' => 'Arquivo não encontrado'], 404));
            }

            if ($forcarDownload) {
                $res = response()->streamDownload(function () use ($stream) {
                    try {
                        fpassthru($stream);
                    } finally {
                        if (is_resource($stream)) fclose($stream);
                    }
                }, $nome, $headers);

                return $this->aplicarCorsRespostaCurriculo($res);
            }

            $headers['Content-Disposition'] = 'inline; filename="' . addslashes((string) $nome) . '"';
            $res = response()->stream(function () use ($stream) {
                try {
                    fpassthru($stream);
                } finally {
                    if (is_resource($stream)) fclose($stream);
                }
            }, 200, $headers);

            return $this->aplicarCorsRespostaCurriculo($res);
        } catch (\Throwable $e) {
            return $this->aplicarCorsRespostaCurriculo(response()->json([
                'error' => 'Erro ao baixar currículo',
                'detail' => $e->getMessage(),
            ], 500));
        }
    }

    public function downloadFoto(Request $request, int $id)
    {
        if (! RhAcesso::pode($request, 'rh.candidatos')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        $rel = 'rh/thumbs/cand-'.$id.'.jpg';
        $abs = storage_path('app/public/'.$rel);
        if (! is_file($abs)) {
            $path = DB::table('rh_candidatos')->where('id', $id)->value('foto_path');
            if (! $path || ! $this->gravarMiniatura(Storage::disk('public'), (string) $path, $id) || ! is_file($abs)) {
                return response()->json(['error' => 'Foto não encontrada'], 404)->header('Access-Control-Allow-Origin', '*');
            }
        }

        if ($request->query('preparar') === '1') {
            return response()->json(['url' => 'storage/'.$rel])->header('Access-Control-Allow-Origin', '*');
        }

        return response()->file($abs, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=604800',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    private function gravarMiniatura($disk, string $path, int $id): bool
    {
        $dir = storage_path('app/public/rh/thumbs');
        $dest = $dir.'/cand-'.$id.'.jpg';
        if (is_file($dest) && filesize($dest) > 0) {
            return true;
        }
        if (! function_exists('imagecreatefromstring')) {
            return false;
        }
        try {
            $tamanho = $disk->size($path);
            if (is_numeric($tamanho) && (int) $tamanho > 8000000) {
                return false;
            }
            $raw = $disk->get($path);
        } catch (\Throwable $e) {
            return false;
        }
        if (! is_string($raw) || $raw === '') {
            return false;
        }

        $src = @imagecreatefromstring($raw);
        unset($raw);
        if (! $src) {
            return false;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $max = 96;
        $scale = min($max / max($w, 1), $max / max($h, 1), 1);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        if (! $dst) {
            imagedestroy($src);

            return false;
        }
        $branco = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $nw, $nh, $branco);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $ok = imagejpeg($dst, $dest, 68);
        imagedestroy($dst);

        return $ok && is_file($dest);
    }
}

