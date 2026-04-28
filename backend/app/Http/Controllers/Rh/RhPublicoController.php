<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RhPublicoController extends Controller
{
    public function showVaga(string $slug)
    {
        $vaga = DB::table('rh_vagas')->where('slug', $slug)->first();
        if (! $vaga || $vaga->status !== 'aberta') {
            abort(404);
        }

        return view('rh.publico.vaga', ['vaga' => $vaga]);
    }

    public function candidatar(Request $request, string $slug)
    {
        $vaga = DB::table('rh_vagas')->where('slug', $slug)->first();
        if (! $vaga || $vaga->status !== 'aberta') {
            return back()->withErrors(['vaga' => 'Vaga indisponível.'])->withInput();
        }

        $data = $request->validate([
            'nome' => 'required|string|max:160',
            'telefone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:160',
            'cidade' => 'nullable|string|max:120',
            'bairro' => 'nullable|string|max:120',
            'experiencia' => 'nullable|string|max:20000',
            'ultimo_emprego' => 'nullable|string|max:160',
            'disponibilidade' => 'nullable|string|max:80',
            'pretensao_salarial' => 'nullable|string|max:80',
            'curriculo' => 'required|file|max:5120', // 5MB
            'foto' => 'nullable|file|max:2048', // 2MB
            'lgpd' => 'accepted',
        ], [
            'lgpd.accepted' => 'Você precisa autorizar o uso dos seus dados para recrutamento e seleção.',
        ]);

        $curriculo = $request->file('curriculo');
        if (! $curriculo || ! $curriculo->isValid()) {
            return back()->withErrors(['curriculo' => 'Currículo inválido.'])->withInput();
        }
        $cvMime = $curriculo->getMimeType() ?: '';
        if (! in_array($cvMime, ['application/pdf'], true)) {
            return back()->withErrors(['curriculo' => 'Envie o currículo em PDF.'])->withInput();
        }

        $foto = $request->file('foto');
        if ($foto && $foto->isValid()) {
            $fotoMime = $foto->getMimeType() ?: '';
            if (! in_array($fotoMime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                return back()->withErrors(['foto' => 'Foto deve ser JPG, PNG ou WEBP.'])->withInput();
            }
        }

        $candidatoId = DB::table('rh_candidatos')->insertGetId([
            'vaga_id' => $vaga->id,
            'nome' => $data['nome'],
            'telefone' => $data['telefone'] ?? null,
            'email' => $data['email'] ?? null,
            'cidade' => $data['cidade'] ?? null,
            'bairro' => $data['bairro'] ?? null,
            'experiencia' => $data['experiencia'] ?? null,
            'ultimo_emprego' => $data['ultimo_emprego'] ?? null,
            'disponibilidade' => $data['disponibilidade'] ?? null,
            'pretensao_salarial' => $data['pretensao_salarial'] ?? null,
            'unidade' => $vaga->unidade ?? null,
            'consentimento_lgpd' => true,
            'consentimento_em' => now(),
            'consentimento_ip' => $request->ip(),
            'consentimento_user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'status' => 'novo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cvPath = $curriculo->store("rh/curriculos/{$candidatoId}", 'public');
        DB::table('rh_curriculos')->insert([
            'candidato_id' => $candidatoId,
            'arquivo_path' => $cvPath,
            'arquivo_nome_original' => $curriculo->getClientOriginalName(),
            'mime' => $cvMime,
            'tamanho_bytes' => $curriculo->getSize(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($foto && $foto->isValid()) {
            $fotoPath = $foto->store("rh/fotos/{$candidatoId}", 'public');
            DB::table('rh_candidatos')->where('id', $candidatoId)->update([
                'foto_path' => $fotoPath,
                'updated_at' => now(),
            ]);
        }

        return redirect()->to("/vagas/{$slug}?ok=1");
    }
}

