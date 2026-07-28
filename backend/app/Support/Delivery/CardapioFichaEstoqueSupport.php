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
            return 'Escolha o prato cadastrado em Produtos / estoque (produto final), não um insumo avulso.';
        }
        if (self::produtoTemFicha($produtoFinalId)) {
            return null;
        }

        return 'Este produto não tem ficha técnica vinculada. Cadastre a receita em Ficha técnica apontando para o mesmo produto final, ou escolha outro prato na lista.';
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

        $prodFinal = DB::table('produtos')->where('id', $produtoFinalId)->first();
        $rendimento = ProducaoFiscalSupport::rendimentoFicha($ficha);
        $ingredientes = [];

        foreach (ProducaoFiscalSupport::itensFicha($ficha) as $it) {
            $pid = (int) $it['produto_insumo_id'];
            $prod = DB::table('produtos')->where('id', $pid)->first();
            $nome = $prod->nome ?? $it['nome'] ?? ('#'.$pid);
            $subFicha = DB::table('fichas_tecnicas')->where('produto_final_id', $pid)->orderByDesc('id')->first();

            $tipo = self::classificarInsumo($prod, $subFicha !== null);
            $semiAcabado = null;
            if ($subFicha) {
                $semiAcabado = self::mapSubIngredientes($subFicha);
            }

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
            'produto_final_id' => $produtoFinalId,
            'produto_final_nome' => $prodFinal->nome ?? null,
            'rendimento_quantidade' => $rendimento,
            'ingredientes' => $ingredientes,
            'mensagem' => 'Escolha só o prato acima (produto final). Os insumos abaixo vêm da ficha técnica — na venda a baixa segue essa receita (produção / estoque).',
            'nota_semi_acabado' => 'Itens marcados como semi-acabado (ex.: farofa) podem não aparecer no cardápio: entram como componente deste prato. Produza o semi-acabado antes ou coloque os insumos crus direto na ficha do prato principal.',
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
