<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RhPublicoController extends Controller
{
    private static function normalizarEmailCandidato(string $email): string
    {
        return strtolower(trim($email));
    }

    /** Só dígitos, para comparar telefone e evitar candidatura duplicada na mesma vaga. */
    private static function normalizarTelefoneDigitos(?string $telefone): string
    {
        return (string) preg_replace('/\D+/', '', (string) $telefone);
    }

    /** Municípios de RO — igual ao select da candidatura pública. */
    private static function cidadesRo(): array
    {
        return [
            'Alta Floresta d\'Oeste',
            'Alto Alegre dos Parecis',
            'Alto Paraíso',
            'Alvorada d\'Oeste',
            'Ariquemes',
            'Buritis',
            'Cabixi',
            'Cacaulândia',
            'Cacoal',
            'Campo Novo de Rondônia',
            'Candeias do Jamari',
            'Castanheiras',
            'Cerejeiras',
            'Chupinguaia',
            'Colorado do Oeste',
            'Corumbiara',
            'Costa Marques',
            'Cujubim',
            'Espigão d\'Oeste',
            'Governador Jorge Teixeira',
            'Guajará-Mirim',
            'Itapuã do Oeste',
            'Jaru',
            'Ji-Paraná',
            'Machadinho d\'Oeste',
            'Ministro Andreazza',
            'Mirante da Serra',
            'Monte Negro',
            'Nova Brasilândia d\'Oeste',
            'Nova Mamoré',
            'Nova União',
            'Novo Horizonte do Oeste',
            'Ouro Preto do Oeste',
            'Parecis',
            'Pimenta Bueno',
            'Pimenteiras do Oeste',
            'Porto Velho',
            'Presidente Médici',
            'Primavera de Rondônia',
            'Rio Crespo',
            'Rolim de Moura',
            'Santa Luzia d\'Oeste',
            'São Felipe d\'Oeste',
            'São Francisco do Guaporé',
            'São Miguel do Guaporé',
            'Seringueiras',
            'Teixeirópolis',
            'Theobroma',
            'Urupá',
            'Vale do Anari',
            'Vale do Paraíso',
            'Vilhena',
        ];
    }

    private function candidaturaQuerRespostaJson(Request $request): bool
    {
        return $request->ajax()
            || $request->wantsJson()
            || $request->expectsJson();
    }

    /**
     * Erros da candidatura pública: JSON para envio via fetch, senão redirect com flash.
     *
     * @param  array<string, string|array<int, string>>  $errors
     */
    private function candidatarErro(Request $request, array $errors)
    {
        if ($this->candidaturaQuerRespostaJson($request)) {
            $flat = collect($errors)->map(fn ($v) => is_array($v) ? $v : [$v])->flatten()->filter()->values()->all();

            return response()->json([
                'message' => $flat[0] ?? 'Não foi possível enviar a candidatura.',
                'errors' => collect($errors)->map(fn ($v) => is_array($v) ? $v : [$v])->all(),
            ], 422);
        }

        return back()->withErrors($errors)->withInput();
    }

    public function indexVagas()
    {
        $vagas = DB::table('rh_vagas')
            ->orderByRaw("CASE status WHEN 'aberta' THEN 0 WHEN 'pausada' THEN 1 ELSE 2 END")
            ->orderBy('titulo')
            ->get();

        return view('rh.publico.vagas', ['vagas' => $vagas]);
    }

    public function showVaga(string $slug)
    {
        $vaga = DB::table('rh_vagas')->where('slug', $slug)->first();
        if (! $vaga) {
            abort(404);
        }

        $vagasAbertas = DB::table('rh_vagas')
            ->where('status', 'aberta')
            ->orderBy('titulo')
            ->get();

        return view('rh.publico.vaga', [
            'vaga' => $vaga,
            'vagasAbertas' => $vagasAbertas,
            'vagaBloqueada' => ($vaga->status !== 'aberta'),
        ]);
    }

    public function qrcodeVaga(string $slug)
    {
        $vaga = DB::table('rh_vagas')->where('slug', $slug)->first();
        if (! $vaga) {
            abort(404);
        }

        $apiBase = rtrim((string) config('app.url'), '/');
        $publicUrl = $apiBase . '/vagas/' . $vaga->slug;

        // QR Code público sem dependências de vendor (evita 500 por diferença de versões no servidor).
        $qrUrl = 'https://quickchart.io/qr?size=420&format=jpg&text=' . urlencode($publicUrl);
        return redirect()->away($qrUrl);
    }

    public function candidatar(Request $request, string $slug)
    {
        $vaga = DB::table('rh_vagas')->where('slug', $slug)->first();
        if (! $vaga || $vaga->status !== 'aberta') {
            return $this->candidatarErro($request, ['vaga' => 'Vaga indisponível no momento.']);
        }

        $cidadesRo = self::cidadesRo();

        // Limite anterior do PDF (5120 KB) + metade (2560 KB) = 7680 KB (~7,5 MB).
        $maxCurriculoKb = 7680;
        $maxFotoKb = 3072; // 2048 + metade (1024), menos erro por foto grande

        $maxCvMb = round($maxCurriculoKb / 1024, 1);
        $maxFotoMb = round($maxFotoKb / 1024, 1);

        $data = $request->validate([
            'vaga_ids' => 'nullable|array',
            'vaga_ids.*' => 'integer',
            'nome' => 'required|string|max:160',
            'telefone' => 'required|string|max:40',
            'email' => 'required|email|max:160',
            'cidade' => ['required', 'string', 'max:120', Rule::in($cidadesRo)],
            'bairro' => 'required|string|max:120',
            'disponibilidade' => 'required|string|in:sim,nao',
            'curriculo' => 'required|file|max:' . $maxCurriculoKb,
            'foto' => 'required|file|max:' . $maxFotoKb,
            'lgpd' => 'accepted',
        ], [
            'required' => 'Preencha o campo :attribute.',
            'string' => 'O campo :attribute deve ser texto válido.',
            'max.string' => 'O campo :attribute aceita no máximo :max caracteres.',
            'email.email' => 'Digite um :attribute válido (exemplo: nome@email.com).',
            'file' => 'O arquivo de :attribute não foi aceito. Envie um arquivo válido.',
            'cidade.in' => 'Selecione uma cidade válida na lista.',
            'disponibilidade.in' => 'Em disponibilidade, selecione Sim ou Não.',
            'curriculo.required' => 'Anexe o currículo em PDF.',
            'curriculo.max' => 'O PDF do currículo é grande demais. O tamanho máximo permitido é ' . $maxCvMb . ' MB. Diminua o arquivo: comprima o PDF, reduza imagens dentro do documento ou remova páginas desnecessárias e tente novamente.',
            'foto.required' => 'Anexe sua foto.',
            'foto.max' => 'A foto é grande demais. O tamanho máximo permitido é ' . $maxFotoMb . ' MB. Use uma foto em qualidade menor ou comprima a imagem (JPG) e tente novamente.',
            'lgpd.accepted' => 'Você precisa autorizar o uso dos seus dados para recrutamento e seleção.',
        ], [
            'nome' => 'nome completo',
            'telefone' => 'WhatsApp',
            'email' => 'e-mail',
            'cidade' => 'cidade',
            'bairro' => 'bairro',
            'disponibilidade' => 'disponibilidade',
            'curriculo' => 'currículo (PDF)',
            'foto' => 'foto',
            'lgpd' => 'autorização de dados',
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
            return $this->candidatarErro($request, ['vaga_ids' => 'Selecione apenas vagas abertas.']);
        }

        $emailNorm = self::normalizarEmailCandidato($data['email']);
        $telDigitos = self::normalizarTelefoneDigitos($data['telefone'] ?? '');

        $existentesMesmasVagas = DB::table('rh_candidatos')
            ->whereIn('vaga_id', $vagaIds)
            ->get(['vaga_id', 'email', 'telefone']);

        $jaInscritoTitulos = [];
        $vagasParaInserir = [];
        foreach ($vagasEscolhidas as $ve) {
            $vid = (int) $ve->id;
            $duplicado = false;
            foreach ($existentesMesmasVagas as $row) {
                if ((int) $row->vaga_id !== $vid) {
                    continue;
                }
                $e = self::normalizarEmailCandidato((string) ($row->email ?? ''));
                $telRow = self::normalizarTelefoneDigitos($row->telefone ?? null);
                $mesmoEmail = $e !== '' && $e === $emailNorm;
                $mesmoTel = $telDigitos !== '' && $telRow !== '' && $telRow === $telDigitos;
                if ($mesmoEmail || $mesmoTel) {
                    $duplicado = true;
                    break;
                }
            }
            if ($duplicado) {
                $jaInscritoTitulos[] = (string) ($ve->titulo ?? 'Vaga #' . $ve->id);
            } else {
                $vagasParaInserir[] = $ve;
            }
        }

        if ($vagasParaInserir === []) {
            $lista = implode(', ', array_unique($jaInscritoTitulos));

            return $this->candidatarErro($request, [
                'duplicate' => 'Você já se candidatou a esta(s) vaga(s) com o mesmo e-mail ou telefone: ' . $lista . '. Em outras vagas diferentes você pode se inscrever.',
            ]);
        }

        $curriculo = $request->file('curriculo');
        if (! $curriculo || ! $curriculo->isValid()) {
            return $this->candidatarErro($request, [
                'curriculo' => 'Não foi possível aceitar o arquivo do currículo. Confira se é PDF, se não está corrompido e se o tamanho é no máximo ' . $maxCvMb . ' MB (comprima o PDF se precisar).',
            ]);
        }
        $cvMime = $curriculo->getMimeType() ?: '';
        if (! in_array($cvMime, ['application/pdf'], true)) {
            return $this->candidatarErro($request, [
                'curriculo' => 'O currículo precisa estar em formato PDF. Converta o arquivo e envie novamente.',
            ]);
        }

        $foto = $request->file('foto');
        if (! $foto || ! $foto->isValid()) {
            return $this->candidatarErro($request, [
                'foto' => 'Não foi possível aceitar a foto. Confira se é JPG ou PNG e se o tamanho é no máximo ' . $maxFotoMb . ' MB.',
            ]);
        }
        $fotoMime = $foto->getMimeType() ?: '';
        if (! in_array($fotoMime, ['image/jpeg', 'image/png'], true)) {
            return $this->candidatarErro($request, [
                'foto' => 'A foto deve ser JPG ou PNG. Envie outro arquivo.',
            ]);
        }

        $origCvName = $curriculo->getClientOriginalName();
        $origFotoName = $foto->getClientOriginalName();

        // Lê os arquivos uma vez para replicar por vaga (uma candidatura por vaga marcada).
        $cvBytes = file_get_contents($curriculo->getRealPath());
        if ($cvBytes === false) {
            return $this->candidatarErro($request, [
                'curriculo' => 'Não foi possível ler o PDF. Tente outro arquivo ou comprima o currículo e envie de novo.',
            ]);
        }
        $fotoBytes = file_get_contents($foto->getRealPath());
        if ($fotoBytes === false) {
            return $this->candidatarErro($request, [
                'foto' => 'Não foi possível ler a foto. Escolha outra imagem e tente novamente.',
            ]);
        }

        foreach ($vagasParaInserir as $vagaEscolhida) {
            $candidatoId = DB::table('rh_candidatos')->insertGetId([
                'vaga_id' => $vagaEscolhida->id,
                'nome' => $data['nome'],
                'telefone' => $data['telefone'] ?? null,
                'email' => $emailNorm,
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

            $ext = $fotoMime === 'image/png' ? 'png' : 'jpg';
            $fotoPath = "rh/fotos/{$candidatoId}/" . (time() . '-' . Str::random(10) . '.' . $ext);
                \Storage::disk('public')->put($fotoPath, $fotoBytes);
                DB::table('rh_candidatos')->where('id', $candidatoId)->update([
                    'foto_path' => $fotoPath,
                    'updated_at' => now(),
                ]);
        }

        if ($this->candidaturaQuerRespostaJson($request)) {
            $aviso = null;
            if ($jaInscritoTitulos !== []) {
                $aviso = 'Você já tinha candidatura com este e-mail ou telefone em: ' . implode(', ', array_unique($jaInscritoTitulos)) . '. As demais vagas selecionadas foram registradas.';
            }

            return response()->json([
                'ok' => true,
                'redirect' => url("/vagas/{$slug}?ok=1"),
                'aviso_parcial' => $aviso,
            ]);
        }

        $redirect = redirect()->to("/vagas/{$slug}?ok=1");
        if ($jaInscritoTitulos !== []) {
            $redirect->with(
                'candidatura_parcial',
                'Você já tinha candidatura com este e-mail ou telefone em: ' . implode(', ', array_unique($jaInscritoTitulos)) . '. As demais vagas selecionadas foram registradas.'
            );
        }

        return $redirect;
    }

    /**
     * Resolve candidato pelo token opaco (hash armazenado). Só status pós-aprovação.
     */
    private function candidatoDocumentacaoPorToken(string $token): ?object
    {
        if (strlen($token) !== 64 || ! ctype_xdigit($token)) {
            return null;
        }

        $hash = hash('sha256', $token);
        $c = DB::table('rh_candidatos')->where('documentacao_token_hash', $hash)->first();
        if (! $c || ! empty($c->anonimizado_em)) {
            return null;
        }

        $status = strtolower(trim((string) ($c->status ?? '')));
        if (! in_array($status, ['aprovado', 'em_contratacao'], true)) {
            return null;
        }

        return $c;
    }

    public function documentacaoForm(string $token)
    {
        $c = $this->candidatoDocumentacaoPorToken($token);
        if (! $c) {
            return response()
                ->view('rh.publico.documentacao', [
                    'invalido' => true,
                    'nome' => '',
                    'tiposOk' => [],
                    'token' => $token,
                ], 404);
        }

        $tiposOk = DB::table('rh_documentos')
            ->where('candidato_id', $c->id)
            ->pluck('tipo')
            ->unique()
            ->values()
            ->all();

        $nome = trim((string) ($c->nome ?? ''));
        $partes = preg_split('/\s+/', $nome, 2) ?: [];
        $nomePrimeiro = $partes[0] !== '' ? $partes[0] : 'Candidato(a)';

        return view('rh.publico.documentacao', [
            'invalido' => false,
            'nome' => $nomePrimeiro,
            'tiposOk' => $tiposOk,
            'token' => $token,
        ]);
    }

    public function documentacaoEnviar(Request $request, string $token)
    {
        $c = $this->candidatoDocumentacaoPorToken($token);
        if (! $c) {
            return back()->withErrors(['arquivo' => 'Link inválido ou indisponível.']);
        }

        $data = $request->validate([
            'tipo' => 'required|string|in:cpf,rg,comprovante,ctps',
            'arquivo' => 'required|file|max:6144',
        ]);

        $f = $request->file('arquivo');
        if (! $f || ! $f->isValid()) {
            return back()->withErrors(['arquivo' => 'Arquivo inválido.'])->withInput();
        }

        $mime = $f->getMimeType() ?: '';
        if ($mime !== 'application/pdf') {
            return back()->withErrors(['arquivo' => 'Envie apenas PDF.'])->withInput();
        }

        $disk = Storage::disk('public');
        $existentes = DB::table('rh_documentos')->where('candidato_id', $c->id)->where('tipo', $data['tipo'])->get();
        foreach ($existentes as $ex) {
            if (! empty($ex->arquivo_path)) {
                try {
                    $disk->delete($ex->arquivo_path);
                } catch (\Throwable $_) {
                }
            }
        }
        DB::table('rh_documentos')->where('candidato_id', $c->id)->where('tipo', $data['tipo'])->delete();

        $path = $f->store("rh/documentos/{$c->id}", 'public');
        DB::table('rh_documentos')->insert([
            'candidato_id' => $c->id,
            'tipo' => $data['tipo'],
            'arquivo_path' => $path,
            'arquivo_nome_original' => $f->getClientOriginalName(),
            'mime' => $mime,
            'tamanho_bytes' => $f->getSize(),
            'enviado_por' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->to('/documentacao/' . $token . '?ok=1');
    }
}

