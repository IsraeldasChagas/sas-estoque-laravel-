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

        $vagasAbertas = DB::table('rh_vagas')
            ->where('status', 'aberta')
            ->orderBy('titulo')
            ->get();

        return view('rh.publico.vaga', [
            'vaga' => $vaga,
            'vagasAbertas' => $vagasAbertas,
        ]);
    }

    public function candidatar(Request $request, string $slug)
    {
        $vaga = DB::table('rh_vagas')->where('slug', $slug)->first();
        if (! $vaga || $vaga->status !== 'aberta') {
            return back()->withErrors(['vaga' => 'Vaga indisponível.'])->withInput();
        }

        $data = $request->validate([
            'vaga_ids' => 'nullable|array',
            'vaga_ids.*' => 'integer',
            'nome' => 'required|string|max:160',
            'telefone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:160',
            'cidade' => 'nullable|string|max:120',
            'bairro' => 'nullable|string|max:120',
            'disponibilidade' => 'nullable|string|max:80',
            'curriculo' => 'required|file|max:5120', // 5MB
            'foto' => 'nullable|file|max:2048', // 2MB
            'lgpd' => 'accepted',
        ], [
            'lgpd.accepted' => 'Você precisa autorizar o uso dos seus dados para recrutamento e seleção.',
        ]);

        $vagaIds = $data['vaga_ids'] ?? null;
        if (! is_array($vagaIds) || ! count($vagaIds)) {
            $vagaIds = [$vaga->id];
        }
        $vagaIds = array_values(array_unique(array_map('intval', $vagaIds)));

        // Segurança: só permite candidatar para vagas abertas.
        $vagasEscolhidas = DB::table('rh_vagas')
            ->whereIn('id', $vagaIds)
            ->where('status', 'aberta')
            ->get();
        if (count($vagasEscolhidas) !== count($vagaIds)) {
            return back()->withErrors(['vaga_ids' => 'Selecione apenas vagas abertas.'])->withInput();
        }

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

        $origCvName = $curriculo->getClientOriginalName();
        $origFotoName = $foto && $foto->isValid() ? $foto->getClientOriginalName() : null;

        // Lê os arquivos uma vez para replicar por vaga (uma candidatura por vaga marcada).
        $cvBytes = file_get_contents($curriculo->getRealPath());
        if ($cvBytes === false) {
            return back()->withErrors(['curriculo' => 'Não foi possível ler o currículo.'])->withInput();
        }
        $fotoBytes = null;
        $fotoMime = null;
        if ($foto && $foto->isValid()) {
            $fotoBytes = file_get_contents($foto->getRealPath());
            $fotoMime = $foto->getMimeType() ?: null;
            if ($fotoBytes === false) {
                return back()->withErrors(['foto' => 'Não foi possível ler a foto.'])->withInput();
            }
        }

        foreach ($vagasEscolhidas as $vagaEscolhida) {
            $candidatoId = DB::table('rh_candidatos')->insertGetId([
                'vaga_id' => $vagaEscolhida->id,
                'nome' => $data['nome'],
                'telefone' => $data['telefone'] ?? null,
                'email' => $data['email'] ?? null,
                'cidade' => $data['cidade'] ?? null,
                'bairro' => $data['bairro'] ?? null,
                'disponibilidade' => $data['disponibilidade'] ?? null,
                'unidade' => $vagaEscolhida->unidade ?? null,
                'consentimento_lgpd' => true,
                'consentimento_em' => now(),
                'consentimento_ip' => $request->ip(),
                'consentimento_user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                'status' => 'novo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $cvPath = "rh/curriculos/{$candidatoId}/" . (time() . '-' . Str::random(10) . '.pdf');
            \Storage::disk('public')->put($cvPath, $cvBytes);
            DB::table('rh_curriculos')->insert([
                'candidato_id' => $candidatoId,
                'arquivo_path' => $cvPath,
                'arquivo_nome_original' => $origCvName,
                'mime' => $cvMime,
                'tamanho_bytes' => strlen($cvBytes),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($fotoBytes !== null) {
                $ext = 'jpg';
                if ($fotoMime === 'image/png') $ext = 'png';
                if ($fotoMime === 'image/webp') $ext = 'webp';
                $fotoPath = "rh/fotos/{$candidatoId}/" . (time() . '-' . Str::random(10) . '.' . $ext);
                \Storage::disk('public')->put($fotoPath, $fotoBytes);
                DB::table('rh_candidatos')->where('id', $candidatoId)->update([
                    'foto_path' => $fotoPath,
                    'updated_at' => now(),
                ]);
            }
        }

        return redirect()->to("/vagas/{$slug}?ok=1");
    }
}

