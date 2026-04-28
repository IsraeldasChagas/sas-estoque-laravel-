<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Support\Rh\RhAcesso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RhCandidatoController extends Controller
{
    private const STATUS = [
        'novo',
        'em_analise',
        'entrevista',
        'aprovado',
        'em_contratacao',
        'contratado',
        'reprovado',
        'banco_talentos',
    ];

    public function index(Request $request)
    {
        if (! RhAcesso::pode($request, 'rh.candidatos')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        $q = DB::table('rh_candidatos')
            ->leftJoin('rh_vagas', 'rh_candidatos.vaga_id', '=', 'rh_vagas.id')
            ->select(
                'rh_candidatos.*',
                'rh_vagas.titulo as vaga_titulo',
                'rh_vagas.slug as vaga_slug',
                DB::raw('(SELECT cv.arquivo_path FROM rh_curriculos cv WHERE cv.candidato_id = rh_candidatos.id ORDER BY cv.id DESC LIMIT 1) as curriculo_path')
            )
            ->orderByDesc('rh_candidatos.id');

        if ($request->filled('status')) $q->where('rh_candidatos.status', $request->status);
        if ($request->filled('vaga_id')) $q->where('rh_candidatos.vaga_id', (int) $request->vaga_id);
        if ($request->filled('nome')) $q->where('rh_candidatos.nome', 'like', '%' . $request->nome . '%');
        if ($request->filled('email')) $q->where('rh_candidatos.email', 'like', '%' . $request->email . '%');
        if ($request->filled('telefone')) $q->where('rh_candidatos.telefone', 'like', '%' . $request->telefone . '%');

        $rows = $q->get();
        // Garante flags corretas (arquivo pode ter sido removido/anônimo/LGPD).
        foreach ($rows as $r) {
            $cvPath = $r->curriculo_path ?? null;
            $r->tem_curriculo = (bool) ($cvPath && Storage::disk('public')->exists($cvPath));
            $r->tem_foto = (bool) (! empty($r->foto_path) && Storage::disk('public')->exists($r->foto_path));
            unset($r->curriculo_path);
        }

        return response()->json($rows)->header('Access-Control-Allow-Origin', '*');
    }

    public function show(Request $request, int $id)
    {
        if (! RhAcesso::pode($request, 'rh.candidatos')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        $c = DB::table('rh_candidatos')->where('id', $id)->first();
        if (! $c) return response()->json(['error' => 'Candidato não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');

        $vaga = $c->vaga_id ? DB::table('rh_vagas')->where('id', $c->vaga_id)->first() : null;
        $curriculo = DB::table('rh_curriculos')->where('candidato_id', $id)->orderByDesc('id')->first();
        if ($curriculo && (! $curriculo->arquivo_path || ! Storage::disk('public')->exists($curriculo->arquivo_path))) {
            $curriculo = null;
        }

        $fotoOk = false;
        if (! empty($c->foto_path) && Storage::disk('public')->exists($c->foto_path)) {
            $fotoOk = true;
        }
        $entrevistas = DB::table('rh_entrevistas')->where('candidato_id', $id)->orderByDesc('id')->get();
        $documentos = DB::table('rh_documentos')->where('candidato_id', $id)->orderByDesc('id')->get();
        $historico = DB::table('rh_historico')->where('candidato_id', $id)->orderByDesc('id')->get();

        return response()->json([
            'candidato' => $c,
            'vaga' => $vaga,
            'curriculo' => $curriculo,
            'tem_curriculo' => (bool) $curriculo,
            'tem_foto' => $fotoOk,
            'entrevistas' => $entrevistas,
            'documentos' => $documentos,
            'historico' => $historico,
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
            'anonimizado_em' => now(),
            'anonimizado_por' => $request->header('X-Usuario-Id') ? (int) $request->header('X-Usuario-Id') : null,
            'updated_at' => now(),
        ]);

        // Apaga arquivos armazenados (currículo e foto/documentos) para reduzir risco LGPD.
        if ($c->foto_path) {
            Storage::disk('public')->delete($c->foto_path);
        }
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

    public function downloadCurriculo(Request $request, int $id)
    {
        if (! RhAcesso::pode($request, 'rh.candidatos')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        if (! DB::table('rh_candidatos')->where('id', $id)->exists()) {
            return response()->json(['error' => 'Candidato não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
        }

        $cv = DB::table('rh_curriculos')->where('candidato_id', $id)->orderByDesc('id')->first();
        if (! $cv || ! $cv->arquivo_path || ! Storage::disk('public')->exists($cv->arquivo_path)) {
            return response()->json(['error' => 'Currículo não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
        }

        $res = Storage::disk('public')->download($cv->arquivo_path, $cv->arquivo_nome_original ?: basename($cv->arquivo_path));
        $res->headers->set('Access-Control-Allow-Origin', '*');
        $res->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $res->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id, X-Device-Model, X-Device-Platform');
        $res->headers->set('Access-Control-Expose-Headers', 'Content-Disposition, Content-Type, Content-Length');
        return $res;
    }

    public function downloadFoto(Request $request, int $id)
    {
        if (! RhAcesso::pode($request, 'rh.candidatos')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        $c = DB::table('rh_candidatos')->where('id', $id)->first();
        if (! $c) return response()->json(['error' => 'Candidato não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');

        $path = $c->foto_path ?? null;
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return response()->json(['error' => 'Foto não encontrada'], 404)->header('Access-Control-Allow-Origin', '*');
        }

        // Para <img> renderizar corretamente, precisamos responder com o mime real.
        $mime = Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';
        $content = Storage::disk('public')->get($path);

        $res = response($content, 200)
            ->header('Content-Type', $mime)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id, X-Device-Model, X-Device-Platform')
            ->header('Access-Control-Expose-Headers', 'Content-Type, Content-Length');

        return $res;
    }
}

