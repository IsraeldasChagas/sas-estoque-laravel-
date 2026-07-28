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
            $itens = self::resumoPorFichaId($fichaTecnicaId);

            return ($itens && ($itens['ingredientes'] ?? []) !== []) ? null
                : 'A ficha escolhida não tem insumos ligados a produtos do estoque. Na ficha, vincule arroz, carne etc. (Produtos).';
        }
        if ($estoqueProdutoIdLegado && $estoqueProdutoIdLegado > 0) {
            return self::mensagemSeSemFicha($estoqueProdutoIdLegado);
        }

        return 'Escolha a ficha técnica deste prato. O estoque baixa os insumos da receita — não cadastre o prato como produto comprado.';
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
            return ['estoque_ok' => false, 'aviso' => 'Vincule a ficha técnica com insumos do estoque.'];
        }
        $faltas = [];
        foreach ($resumo['ingredientes'] ?? [] as $ing) {
            $pid = (int) ($ing['produto_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $saldo = \App\Support\ProducaoEstoqueSupport::saldoDisponivel($pid, $unidadeId);
            if ($saldo === null || $saldo <= 0.0001) {
                $faltas[] = (string) ($ing['nome'] ?? $pid);
            }
        }
        if ($faltas === []) {
            return ['estoque_ok' => true, 'aviso' => null];
        }

        return [
            'estoque_ok' => false,
            'aviso' => 'Insumos sem saldo nesta unidade: '.implode(', ', array_slice($faltas, 0, 5)).(count($faltas) > 5 ? '…' : ''),
        ];
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
        $ingredientes = [];

        foreach (ProducaoFiscalSupport::itensFicha($ficha) as $it) {
            $pid = (int) $it['produto_insumo_id'];
            $prod = DB::table('produtos')->where('id', $pid)->first();
            $nome = $prod->nome ?? $it['nome'] ?? ('#'.$pid);
            $subFicha = Schema::hasColumn('fichas_tecnicas', 'produto_final_id')
                ? DB::table('fichas_tecnicas')->where('produto_final_id', $pid)->orderByDesc('id')->first()
                : null;

            $tipo = self::classificarInsumo($prod, $subFicha !== null);
            $semiAcabado = $subFicha ? self::mapSubIngredientes($subFicha) : null;

            $ingredientes[] = [
                'produto_id' => $pid,
                'nome' => (string) $nome,
                'quantidade_padrao' => (float) $it['quantidade_padrao'],
                'unidade_medida' => $it['unidade_medida'] ?? null,
                'tipo' => $tipo,
                'semi_acabado' => $semiAcabado,
            ];
        }

        return [
            'ficha_id' => (int) $ficha->id,
            'nome_prato' => (string) ($ficha->nome_prato ?? $prodFinal->nome ?? ''),
            'produto_final_id' => $pfId > 0 ? $pfId : null,
            'produto_final_nome' => $prodFinal->nome ?? null,
            'rendimento_quantidade' => $rendimento,
            'ingredientes' => $ingredientes,
            'mensagem' => 'Na venda, o estoque baixa estes insumos (produtos comprados / produzidos). O cardápio só aponta para esta ficha — não precisa cadastrar o prato como produto de estoque.',
            'nota_semi_acabado' => 'Semi-acabado (ex.: farofa): pode existir só na ficha, sem item no cardápio.',
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
