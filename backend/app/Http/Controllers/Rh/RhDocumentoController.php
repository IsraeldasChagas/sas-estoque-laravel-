<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Support\Rh\RhAcesso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class RhDocumentoController extends Controller
{
    /**
     * CORS do GET /rh/documentos/{id}/download (frontend em outro domínio + fetch com Authorization / X-Usuario-Id).
     * Mesma regra do Alvará: BinaryFileResponse não suporta ->header() fluente.
     */
    private function aplicarCorsRespostaDownload(SymfonyResponse $response): SymfonyResponse
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

    private function podeRhDocsOuCandidatos(Request $request): bool
    {
        return RhAcesso::pode($request, 'rh.documentos') || RhAcesso::pode($request, 'rh.candidatos');
    }

    public function index(Request $request)
    {
        if (! RhAcesso::pode($request, 'rh.documentos')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        $q = DB::table('rh_documentos')
            ->join('rh_candidatos', 'rh_documentos.candidato_id', '=', 'rh_candidatos.id')
            ->leftJoin('rh_vagas', 'rh_candidatos.vaga_id', '=', 'rh_vagas.id')
            ->select('rh_documentos.*', 'rh_candidatos.nome as candidato_nome', 'rh_candidatos.status as candidato_status', 'rh_vagas.titulo as vaga_titulo')
            ->orderByDesc('rh_documentos.id');

        if ($request->filled('candidato_id')) $q->where('rh_documentos.candidato_id', (int) $request->candidato_id);

        return response()->json($q->get())->header('Access-Control-Allow-Origin', '*');
    }

    public function upload(Request $request, int $candidatoId)
    {
        if (! $this->podeRhDocsOuCandidatos($request)) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        $c = DB::table('rh_candidatos')->where('id', $candidatoId)->first();
        if (! $c) return response()->json(['error' => 'Candidato não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');

        // LGPD: documentos só após aprovação / em contratação
        $stUp = strtolower(trim((string) ($c->status ?? '')));
        if (! in_array($stUp, ['aprovado', 'em_contratacao'], true)) {
            return response()->json(['error' => 'Documentos só podem ser enviados após aprovação (Aprovado / Em contratação).'], 422)
                ->header('Access-Control-Allow-Origin', '*');
        }

        $data = $request->validate([
            'tipo' => 'required|string|in:cpf,rg,comprovante,ctps',
            'arquivo' => 'required|file|max:6144', // 6MB
        ]);

        $f = $request->file('arquivo');
        if (! $f || ! $f->isValid()) {
            return response()->json(['error' => 'Arquivo inválido'], 422)->header('Access-Control-Allow-Origin', '*');
        }

        $mime = $f->getMimeType() ?: '';
        $allowed = ['application/pdf', 'image/jpeg', 'image/png'];
        if (! in_array($mime, $allowed, true)) {
            return response()->json(['error' => 'Formato não permitido. Envie PDF, JPG ou PNG.'], 422)->header('Access-Control-Allow-Origin', '*');
        }

        $path = $f->store("rh/documentos/{$candidatoId}", 'public');
        $id = DB::table('rh_documentos')->insertGetId([
            'candidato_id' => $candidatoId,
            'tipo' => $data['tipo'],
            'arquivo_path' => $path,
            'arquivo_nome_original' => $f->getClientOriginalName(),
            'mime' => $mime,
            'tamanho_bytes' => $f->getSize(),
            'enviado_por' => $request->header('X-Usuario-Id') ? (int) $request->header('X-Usuario-Id') : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(DB::table('rh_documentos')->where('id', $id)->first())
            ->header('Access-Control-Allow-Origin', '*');
    }

    public function download(Request $request, int $id)
    {
        try {
            if (! $this->podeRhDocsOuCandidatos($request)) {
                return $this->aplicarCorsRespostaDownload(response()->json(['error' => 'Sem permissão.'], 403));
            }

            $doc = DB::table('rh_documentos')->where('id', $id)->first();
            if (! $doc) return $this->aplicarCorsRespostaDownload(response()->json(['error' => 'Documento não encontrado'], 404));

            $path = $doc->arquivo_path;
            if (! $path || ! Storage::disk('public')->exists($path)) {
                return $this->aplicarCorsRespostaDownload(response()->json(['error' => 'Arquivo não encontrado'], 404));
            }

            // Não dependa de arquivo local: use stream (disk pode ser remoto).
            $disk = Storage::disk('public');
            $nome = $doc->arquivo_nome_original ?: basename((string) $path);
            $forcarDownload = $request->boolean('download', false);

            $mime = $doc->mime ?: 'application/octet-stream';
            $headers = [
                'Content-Type' => $mime,
                'Content-Security-Policy' => "frame-ancestors 'self' https://*.gruposaborparaense.com.br http://localhost:*",
                'X-Frame-Options' => 'ALLOWALL',
            ];

            $stream = $disk->readStream($path);
            if (! $stream) {
                return $this->aplicarCorsRespostaDownload(response()->json(['error' => 'Arquivo não encontrado'], 404));
            }

            if ($forcarDownload) {
                $res = response()->streamDownload(function () use ($stream) {
                    try {
                        fpassthru($stream);
                    } finally {
                        if (is_resource($stream)) fclose($stream);
                    }
                }, $nome, $headers);

                return $this->aplicarCorsRespostaDownload($res);
            }

            $headers['Content-Disposition'] = 'inline; filename="' . addslashes((string) $nome) . '"';
            $res = response()->stream(function () use ($stream) {
                try {
                    fpassthru($stream);
                } finally {
                    if (is_resource($stream)) fclose($stream);
                }
            }, 200, $headers);

            return $this->aplicarCorsRespostaDownload($res);
        } catch (\Throwable $e) {
            return $this->aplicarCorsRespostaDownload(response()->json([
                'error' => 'Erro ao baixar documento',
                'detail' => $e->getMessage(),
            ], 500));
        }
    }

    /**
     * Remove documento (arquivo + registro) para o candidato poder enviar de novo.
     */
    public function destroy(Request $request, int $id)
    {
        if (! RhAcesso::pode($request, 'rh.documentos') && ! RhAcesso::pode($request, 'rh.candidatos')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        $doc = DB::table('rh_documentos')->where('id', $id)->first();
        if (! $doc) {
            return response()->json(['error' => 'Documento não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
        }

        $c = DB::table('rh_candidatos')->where('id', $doc->candidato_id)->first();
        if (! $c) {
            return response()->json(['error' => 'Candidato não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
        }

        if (! empty($c->anonimizado_em)) {
            return response()->json(['error' => 'Candidato anonimizado.'], 422)->header('Access-Control-Allow-Origin', '*');
        }

        $st = strtolower(trim((string) ($c->status ?? '')));
        if (! in_array($st, ['aprovado', 'em_contratacao', 'contratado'], true)) {
            return response()->json([
                'error' => 'Só é permitido excluir documentos com candidato Aprovado, Em contratação ou Contratado.',
            ], 422)->header('Access-Control-Allow-Origin', '*');
        }

        $path = $doc->arquivo_path ?? null;
        if ($path) {
            try {
                Storage::disk('public')->delete($path);
            } catch (\Throwable $_) {
            }
        }

        DB::table('rh_documentos')->where('id', $id)->delete();

        return response()->json(['ok' => true])->header('Access-Control-Allow-Origin', '*');
    }
}

