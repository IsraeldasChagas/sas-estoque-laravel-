<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

class RhPublicoController extends Controller
{
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

        // Sempre tenta gerar localmente, mas NUNCA deixe estourar 500:
        // se faltar lib/extensão ou ocorrer qualquer erro, cai no gerador externo.
        try {
            if (class_exists(Builder::class)) {
                $result = Builder::create()
                    ->writer(new PngWriter())
                    ->data($publicUrl)
                    ->size(420)
                    ->margin(10)
                    ->build();

                return response($result->getString(), 200)
                    ->header('Content-Type', 'image/png');
            }
        } catch (\Throwable $e) {
            // fallback abaixo
        }

        $qrUrl = 'https://quickchart.io/qr?size=420&format=png&text=' . urlencode($publicUrl);
        return redirect()->away($qrUrl);
    }

    public function candidatar(Request $request, string $slug)
    {
        $vaga = DB::table('rh_vagas')->where('slug', $slug)->first();
        if (! $vaga || $vaga->status !== 'aberta') {
            return back()->withErrors(['vaga' => 'Vaga indisponível.'])->withInput();
        }

        $cidadesRo = self::cidadesRo();

        $data = $request->validate([
            'vaga_ids' => 'nullable|array',
            'vaga_ids.*' => 'integer',
            'nome' => 'required|string|max:160',
            'telefone' => 'required|string|max:40',
            'email' => 'required|email|max:160',
            'cidade' => ['required', 'string', 'max:120', Rule::in($cidadesRo)],
            'bairro' => 'required|string|max:120',
            'disponibilidade' => 'required|string|in:sim,nao',
            'horarios_trabalho' => 'required|string|max:255',
            'curriculo' => 'required|file|max:5120', // 5MB
            'foto' => 'required|file|max:2048', // 2MB
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
        if (! $foto || ! $foto->isValid()) {
            return back()->withErrors(['foto' => 'Foto inválida.'])->withInput();
        }
        $fotoMime = $foto->getMimeType() ?: '';
        if (! in_array($fotoMime, ['image/jpeg', 'image/png'], true)) {
            return back()->withErrors(['foto' => 'Foto deve ser JPG ou PNG.'])->withInput();
        }

        $origCvName = $curriculo->getClientOriginalName();
        $origFotoName = $foto->getClientOriginalName();

        // Lê os arquivos uma vez para replicar por vaga (uma candidatura por vaga marcada).
        $cvBytes = file_get_contents($curriculo->getRealPath());
        if ($cvBytes === false) {
            return back()->withErrors(['curriculo' => 'Não foi possível ler o currículo.'])->withInput();
        }
        $fotoBytes = file_get_contents($foto->getRealPath());
        if ($fotoBytes === false) {
            return back()->withErrors(['foto' => 'Não foi possível ler a foto.'])->withInput();
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
                'horarios_trabalho' => $data['horarios_trabalho'] ?? null,
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

        return redirect()->to("/vagas/{$slug}?ok=1");
    }
}

