<?php

namespace App\Support\Delivery;

use App\Support\ProducaoFiscalSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class CardapioFichaEstoqueSupport
{
    public static function produtoTemFicha(int $produtoFinalId): bool
    {
        if ($produtoFinalId <= 0 || ! Schema::hasTable('fichas_tecnicas')) {
            return false;
        }
        if (! Schema::hasColumn('fichas_tecnicas', 'produto_final_id')) {
            return false;
        }

        return DB::table('fichas_tecnicas')->where('produto_final_id', $produtoFinalId)->exists();
    }

    public static function mensagemSeSemFicha(int $produtoFinalId): ?string
    {
        if ($produtoFinalId <= 0) {
            return null;
        }
        if (self::produtoTemFicha($produtoFinalId)) {
            return null;
        }

        return 'Este produto não tem ficha técnica vinculada.';
    }

    public static function fichaExiste(int $fichaId): bool
    {
        return $fichaId > 0 && Schema::hasTable('fichas_tecnicas')
            && DB::table('fichas_tecnicas')->where('id', $fichaId)->exists();
    }

    /** Valida vínculo de item do cardápio tipo prato (ficha direta — fluxo normal). */
    public static function mensagemSePratoSemReceita(?int $fichaTecnicaId, ?int $estoqueProdutoIdLegado): ?string
    {
        if ($fichaTecnicaId && $fichaTecnicaId > 0) {
            if (! self::fichaExiste($fichaTecnicaId)) {
                return 'Ficha técnica não encontrada. Escolha outra receita ou cadastre em Ficha técnica.';
            }
            $ficha = DB::table('fichas_tecnicas')->where('id', $fichaTecnicaId)->first();
            if ($ficha && self::ingredientesDaReceita($ficha) === []) {
                return 'A ficha escolhida não tem ingredientes cadastrados. Inclua na Ficha técnica.';
            }

            return null;
        }
        if ($estoqueProdutoIdLegado && $estoqueProdutoIdLegado > 0) {
            return self::mensagemSeSemFicha($estoqueProdutoIdLegado);
        }

        return 'Escolha a ficha técnica deste prato.';
    }

    /**
     * Lista completa da receita (nome na ficha), com ou sem produto no estoque.
     *
     * @return list<array<string, mixed>>
     */
    public static function ingredientesDaReceita(object $ficha): array
    {
        $json = json_decode($ficha->ingredientes_json ?? '[]', true);
        $out = [];
        if (is_array($json)) {
            foreach ($json as $ing) {
                if (! is_array($ing)) {
                    continue;
                }
                $nome = trim((string) ($ing['nome'] ?? ''));
                if ($nome === '') {
                    continue;
                }
                $pid = isset($ing['produto_id']) && is_numeric($ing['produto_id']) ? (int) $ing['produto_id'] : 0;
                if ($pid <= 0 && isset($ing['id']) && is_numeric($ing['id'])) {
                    $candidato = (int) $ing['id'];
                    if (DB::table('produtos')->where('id', $candidato)->exists()) {
                        $pid = $candidato;
                    }
                }
                if ($pid <= 0) {
                    $pid = (int) (DB::table('produtos')->where('nome', $nome)->value('id') ?? 0);
                }
                $rawQ = $ing['quantidade'] ?? null;
                $quantidade = ($rawQ !== null && $rawQ !== '' && is_numeric($rawQ)) ? (float) $rawQ : null;
                $out[] = self::enriquecerLinhaReceita($nome, $pid, $quantidade, $ing['unidade_medida'] ?? null);
            }
        }
        if ($out !== []) {
            return $out;
        }

        foreach (ProducaoFiscalSupport::itensFicha($ficha, false) as $it) {
            $pid = (int) $it['produto_insumo_id'];
            $nome = (string) ($it['nome'] ?? DB::table('produtos')->where('id', $pid)->value('nome') ?? ('#'.$pid));
            $out[] = self::enriquecerLinhaReceita($nome, $pid, (float) $it['quantidade_padrao'], $it['unidade_medida'] ?? null);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function resumoPorFichaId(int $fichaId): ?array
    {
        if (! self::fichaExiste($fichaId)) {
            return null;
        }
        $ficha = DB::table('fichas_tecnicas')->where('id', $fichaId)->first();

        return self::montarResumo($ficha);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function resumoPorProdutoFinal(int $produtoFinalId): ?array
    {
        if ($produtoFinalId <= 0 || ! Schema::hasTable('fichas_tecnicas')) {
            return null;
        }
        if (! Schema::hasColumn('fichas_tecnicas', 'produto_final_id')) {
            return null;
        }

        $ficha = DB::table('fichas_tecnicas')
            ->where('produto_final_id', $produtoFinalId)
            ->orderByDesc('id')
            ->first();
        if (! $ficha) {
            return null;
        }

        return self::montarResumo($ficha, $produtoFinalId);
    }

    /** @return array{estoque_ok: bool, aviso: ?string} */
    public static function avaliarSaldoInsumos(int $fichaId, int $unidadeId): array
    {
        $resumo = self::resumoPorFichaId($fichaId);
        if (! $resumo || $unidadeId <= 0) {
            return ['estoque_ok' => true, 'aviso' => null];
        }
        $semVinculo = [];
        $faltas = [];
        foreach ($resumo['ingredientes'] ?? [] as $ing) {
            $nome = (string) ($ing['nome'] ?? '');
            if (($ing['vinculo_estoque'] ?? '') === 'sem_produto') {
                $semVinculo[] = $nome;

                continue;
            }
            $pid = (int) ($ing['produto_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $saldo = \App\Support\ProducaoEstoqueSupport::saldoDisponivel($pid, $unidadeId);
            if ($saldo === null || $saldo <= 0.0001) {
                $faltas[] = $nome !== '' ? $nome : (string) $pid;
            }
        }
        $partes = [];
        if ($semVinculo !== []) {
            $partes[] = 'Sem produto no estoque: '.implode(', ', array_slice($semVinculo, 0, 4)).(count($semVinculo) > 4 ? '…' : '');
        }
        if ($faltas !== []) {
            $partes[] = 'Sem saldo nesta unidade: '.implode(', ', array_slice($faltas, 0, 4)).(count($faltas) > 4 ? '…' : '');
        }
        if ($partes === []) {
            return ['estoque_ok' => true, 'aviso' => null];
        }

        return ['estoque_ok' => false, 'aviso' => implode(' · ', $partes)];
    }

    /**
     * @return array<string, mixed>
     */
    private static function montarResumo(object $ficha, ?int $produtoFinalId = null): array
    {
        $pfId = $produtoFinalId ?? (Schema::hasColumn('fichas_tecnicas', 'produto_final_id') && $ficha->produto_final_id
            ? (int) $ficha->produto_final_id : 0);
        $prodFinal = $pfId > 0 ? DB::table('produtos')->where('id', $pfId)->first() : null;
        $rendimento = ProducaoFiscalSupport::rendimentoFicha($ficha);
        $ingredientes = self::ingredientesDaReceita($ficha);
        $semVinculo = count(array_filter($ingredientes, fn ($i) => ($i['vinculo_estoque'] ?? '') === 'sem_produto'));
        $avisos = [];
        if ($semVinculo > 0) {
            $avisos[] = "{$semVinculo} ingrediente(s) ainda sem cadastro/vínculo em Produtos (estoque).";
        }

        return [
            'ficha_id' => (int) $ficha->id,
            'nome_prato' => (string) ($ficha->nome_prato ?? $prodFinal->nome ?? ''),
            'produto_final_id' => $pfId > 0 ? $pfId : null,
            'produto_final_nome' => $prodFinal->nome ?? null,
            'rendimento_quantidade' => $rendimento,
            'ingredientes' => $ingredientes,
            'avisos_estoque' => $avisos,
            'mensagem' => 'Ingredientes da ficha técnica. Aviso de saldo só vale para itens já ligados a Produtos.',
            'nota_semi_acabado' => 'Semi-acabado (ex.: farofa): pode existir só na ficha, sem item no cardápio.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function enriquecerLinhaReceita(string $nome, int $pid, ?float $quantidade, ?string $unidade): array
    {
        $prod = $pid > 0 ? DB::table('produtos')->where('id', $pid)->first() : null;
        $subFicha = ($pid > 0 && Schema::hasColumn('fichas_tecnicas', 'produto_final_id'))
            ? DB::table('fichas_tecnicas')->where('produto_final_id', $pid)->orderByDesc('id')->first()
            : null;
        $tipo = $pid > 0 ? self::classificarInsumo($prod, $subFicha !== null) : 'receita';
        $semiAcabado = $subFicha ? self::mapSubIngredientes($subFicha) : null;

        return [
            'produto_id' => $pid > 0 ? $pid : null,
            'nome' => $prod->nome ?? $nome,
            'quantidade_padrao' => $quantidade,
            'unidade_medida' => $unidade ? (string) $unidade : null,
            'tipo' => $tipo,
            'vinculo_estoque' => $pid > 0 ? 'ok' : 'sem_produto',
            'semi_acabado' => $semiAcabado,
        ];
    }

    /**
     * @return list<array{produto_id: int, nome: string, quantidade_padrao: float, unidade_medida: ?string}>
     */
    private static function mapSubIngredientes(object $subFicha): array
    {
        $out = [];
        foreach (ProducaoFiscalSupport::itensFicha($subFicha) as $subIt) {
            $spid = (int) $subIt['produto_insumo_id'];
            $sprod = DB::table('produtos')->where('id', $spid)->first();
            $out[] = [
                'produto_id' => $spid,
                'nome' => (string) ($sprod->nome ?? $subIt['nome'] ?? ('#'.$spid)),
                'quantidade_padrao' => (float) $subIt['quantidade_padrao'],
                'unidade_medida' => $subIt['unidade_medida'] ?? null,
            ];
        }

        return $out;
    }

    private static function classificarInsumo(?object $prod, bool $temFichaPropria): string
    {
        if ($temFichaPropria) {
            return 'semi_acabado';
        }
        if ($prod && Schema::hasColumn('produtos', 'tipo_fiscal')) {
            $tipo = strtolower(trim((string) ($prod->tipo_fiscal ?? '')));
            if (in_array($tipo, ['revenda', 'mercadoria_revenda'], true)) {
                return 'revenda';
            }
        }

        return 'insumo';
    }
}
