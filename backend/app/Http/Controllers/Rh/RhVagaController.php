<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Support\Rh\RhAcesso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

class RhVagaController extends Controller
{
    public function index(Request $request)
    {
        if (! RhAcesso::pode($request, 'rh.vagas')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        $q = DB::table('rh_vagas')->orderByDesc('id');

        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('unidade')) $q->where('unidade', 'like', '%' . $request->unidade . '%');
        if ($request->filled('setor')) $q->where('setor', 'like', '%' . $request->setor . '%');
        if ($request->filled('titulo')) $q->where('titulo', 'like', '%' . $request->titulo . '%');

        return response()->json($q->get())->header('Access-Control-Allow-Origin', '*');
    }

    public function store(Request $request)
    {
        if (! RhAcesso::pode($request, 'rh.vagas')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        $data = $request->validate([
            'titulo' => 'required|string|max:160',
            'descricao' => 'required|string|max:20000',
            'requisitos' => 'nullable|string|max:20000',
            'beneficios' => 'nullable|string|max:20000',
            'unidade' => 'nullable|string|max:120',
            'setor' => 'nullable|string|max:120',
            'quantidade' => 'nullable|integer|min:1|max:1000',
            'tipo_contratacao' => 'nullable|string|max:60',
            'status' => 'nullable|string|in:aberta,pausada,encerrada',
        ]);

        $baseSlug = Str::slug($data['titulo']);
        $slug = $baseSlug ?: ('vaga-' . now()->format('YmdHis'));
        $i = 2;
        while (DB::table('rh_vagas')->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $i;
            $i++;
        }

        $id = DB::table('rh_vagas')->insertGetId([
            'titulo' => $data['titulo'],
            'descricao' => $data['descricao'],
            'requisitos' => $data['requisitos'] ?? null,
            'beneficios' => $data['beneficios'] ?? null,
            'unidade' => $data['unidade'] ?? null,
            'setor' => $data['setor'] ?? null,
            'quantidade' => (int) ($data['quantidade'] ?? 1),
            'tipo_contratacao' => $data['tipo_contratacao'] ?? null,
            'status' => $data['status'] ?? 'aberta',
            'slug' => $slug,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(DB::table('rh_vagas')->where('id', $id)->first())
            ->header('Access-Control-Allow-Origin', '*');
    }

    public function update(Request $request, int $id)
    {
        if (! RhAcesso::pode($request, 'rh.vagas')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        $vaga = DB::table('rh_vagas')->where('id', $id)->first();
        if (! $vaga) return response()->json(['error' => 'Vaga não encontrada'], 404)->header('Access-Control-Allow-Origin', '*');

        $data = $request->validate([
            'titulo' => 'sometimes|required|string|max:160',
            'descricao' => 'sometimes|required|string|max:20000',
            'requisitos' => 'nullable|string|max:20000',
            'beneficios' => 'nullable|string|max:20000',
            'unidade' => 'nullable|string|max:120',
            'setor' => 'nullable|string|max:120',
            'quantidade' => 'nullable|integer|min:1|max:1000',
            'tipo_contratacao' => 'nullable|string|max:60',
            'status' => 'nullable|string|in:aberta,pausada,encerrada',
        ]);

        DB::table('rh_vagas')->where('id', $id)->update([
            ...$data,
            'updated_at' => now(),
        ]);

        return response()->json(DB::table('rh_vagas')->where('id', $id)->first())
            ->header('Access-Control-Allow-Origin', '*');
    }

    public function destroy(Request $request, int $id)
    {
        if (! RhAcesso::pode($request, 'rh.vagas')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        $vaga = DB::table('rh_vagas')->where('id', $id)->first();
        if (! $vaga) {
            return response()->json(['error' => 'Vaga não encontrada'], 404)->header('Access-Control-Allow-Origin', '*');
        }

        $disk = Storage::disk('public');

        // Coleta candidatos e arquivos relacionados para apagar do storage.
        $candidatos = DB::table('rh_candidatos')->where('vaga_id', $id)->get();
        $paths = [];
        foreach ($candidatos as $c) {
            if (! empty($c->foto_path)) $paths[] = $c->foto_path;

            $curriculos = DB::table('rh_curriculos')->where('candidato_id', $c->id)->get();
            foreach ($curriculos as $cv) {
                if (! empty($cv->arquivo_path)) $paths[] = $cv->arquivo_path;
            }

            $docs = DB::table('rh_documentos')->where('candidato_id', $c->id)->get();
            foreach ($docs as $d) {
                if (! empty($d->arquivo_path)) $paths[] = $d->arquivo_path;
            }
        }

        $paths = array_values(array_unique(array_filter($paths, fn ($p) => is_string($p) && trim($p) !== '')));
        foreach ($paths as $p) {
            try { $disk->delete($p); } catch (\Throwable $_) {}
        }

        DB::transaction(function () use ($id, $candidatos) {
            $candIds = $candidatos->pluck('id')->map(fn ($v) => (int) $v)->all();
            if (! empty($candIds)) {
                DB::table('rh_historico')->whereIn('candidato_id', $candIds)->delete();
                DB::table('rh_entrevistas')->whereIn('candidato_id', $candIds)->delete();
                DB::table('rh_documentos')->whereIn('candidato_id', $candIds)->delete();
                DB::table('rh_curriculos')->whereIn('candidato_id', $candIds)->delete();
                DB::table('rh_candidatos')->whereIn('id', $candIds)->delete();
            }

            DB::table('rh_vagas')->where('id', $id)->delete();
        });

        return response()->json(['ok' => true])->header('Access-Control-Allow-Origin', '*');
    }

    public function qrcode(Request $request, int $id)
    {
        if (! RhAcesso::pode($request, 'rh.vagas')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        $vaga = DB::table('rh_vagas')->where('id', $id)->first();
        if (! $vaga) return response()->json(['error' => 'Vaga não encontrada'], 404)->header('Access-Control-Allow-Origin', '*');

        $apiBase = rtrim((string) config('app.url'), '/');
        $publicUrl = $apiBase . '/vagas/' . $vaga->slug;

        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($publicUrl)
            ->size(420)
            ->margin(10)
            ->build();

        return response($result->getString(), 200)
            ->header('Content-Type', 'image/png');
    }
}

