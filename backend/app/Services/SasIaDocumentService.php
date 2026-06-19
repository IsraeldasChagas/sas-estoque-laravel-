<?php

namespace App\Services;

use App\Models\AiDocument;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastro e busca de manuais/documentos para o agente SAS IA.
 */
class SasIaDocumentService
{
    /**
     * Busca documentos ativos por termo (para ferramenta da IA).
     *
     * @return array<string, mixed>
     */
    public function buscarParaIa(string $consulta): array
    {
        if (! Schema::hasTable('ai_documents')) {
            return ['documentos' => [], 'total' => 0];
        }

        $q = AiDocument::query()->where('ativo', true);

        if ($consulta !== '') {
            $termo = '%'.$consulta.'%';
            $q->where(function ($w) use ($termo) {
                $w->where('titulo', 'like', $termo)
                    ->orWhere('conteudo_texto', 'like', $termo);
            });
        }

        $docs = $q->orderByDesc('updated_at')->limit(8)->get();

        return [
            'total' => $docs->count(),
            'documentos' => $docs->map(fn (AiDocument $d) => [
                'id' => $d->id,
                'titulo' => $d->titulo,
                'tipo' => $d->tipo,
                'trecho' => mb_substr(strip_tags($d->conteudo_texto), 0, 1500),
            ])->all(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function listarAtivos(): array
    {
        if (! Schema::hasTable('ai_documents')) {
            return [];
        }

        return AiDocument::query()
            ->where('ativo', true)
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get(['id', 'titulo', 'tipo', 'updated_at'])
            ->map(fn (AiDocument $d) => [
                'id' => $d->id,
                'titulo' => $d->titulo,
                'tipo' => $d->tipo,
                'updated_at' => $d->updated_at?->toIso8601String(),
            ])
            ->all();
    }

    public function criar(array $data, int $usuarioId): AiDocument
    {
        return AiDocument::create([
            'titulo' => mb_substr(trim($data['titulo']), 0, 255),
            'tipo' => in_array($data['tipo'] ?? '', ['manual', 'procedimento', 'regra'], true)
                ? $data['tipo']
                : 'manual',
            'conteudo_texto' => $data['conteudo_texto'] ?? '',
            'arquivo_path' => $data['arquivo_path'] ?? null,
            'ativo' => true,
            'usuario_id' => $usuarioId,
        ]);
    }
}
