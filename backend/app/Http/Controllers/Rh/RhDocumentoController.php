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
        if (! RhAcesso::pode($request, 'rh.documentos')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        $c = DB::table('rh_candidatos')->where('id', $candidatoId)->first();
        if (! $c) return response()->json(['error' => 'Candidato não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');

        // LGPD: documentos só após aprovação / em contratação
        if (! in_array($c->status, ['aprovado', 'em_contratacao'], true)) {
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
        if (! RhAcesso::pode($request, 'rh.documentos')) {
            return $this->aplicarCorsRespostaDownload(response()->json(['error' => 'Sem permissão.'], 403));
        }

        $doc = DB::table('rh_documentos')->where('id', $id)->first();
        if (! $doc) return $this->aplicarCorsRespostaDownload(response()->json(['error' => 'Documento não encontrado'], 404));

        $path = $doc->arquivo_path;
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return $this->aplicarCorsRespostaDownload(response()->json(['error' => 'Arquivo não encontrado'], 404));
        }

        $fullPath = storage_path('app/public/' . ltrim((string) $path, '/'));
        if (! file_exists($fullPath)) {
            return $this->aplicarCorsRespostaDownload(response()->json(['error' => 'Arquivo não encontrado'], 404));
        }

        $nome = $doc->arquivo_nome_original ?: basename($fullPath);
        $forcarDownload = $request->boolean('download', false);
        if ($forcarDownload) {
            return $this->aplicarCorsRespostaDownload(response()->download($fullPath, $nome));
        }

        $mime = $doc->mime ?: 'application/octet-stream';
        return $this->aplicarCorsRespostaDownload(response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . addslashes((string) $nome) . '"',
            'Content-Security-Policy' => "frame-ancestors 'self' https://*.gruposaborparaense.com.br http://localhost:*",
            'X-Frame-Options' => 'ALLOWALL',
        ]));
    }
}

