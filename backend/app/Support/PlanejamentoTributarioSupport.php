<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PlanejamentoTributarioSupport
{
    /** @param array<string, mixed> $p */
    public static function simularTresCenarios(array $p): array
    {
        $qtd = max(0.001, (float) ($p['quantidade'] ?? 1));
        $precoCompra = (float) ($p['preco_compra'] ?? 0);
        $precoVenda = (float) ($p['preco_venda'] ?? 0);
        $custosExtra = (float) ($p['custos_adicionais'] ?? 0);
        $empresaC = (int) ($p['empresa_compradora_c_id'] ?? $p['empresa_c_id'] ?? 0);
        $empresaB = (int) ($p['empresa_b_id'] ?? 0);
        if (! $empresaC && ! empty($p['empresa_compradora_id'])) {
            $empresaC = (int) $p['empresa_compradora_id'];
        }
        if (! $empresaB && ! empty($p['empresa_vendedora_id'])) {
            $empresaB = (int) $p['empresa_vendedora_id'];
        }

        $cenarios = [
            ['id' => 1, 'nome' => 'C compra → C vende', 'compra_empresa' => $empresaC, 'venda_empresa' => $empresaC, 'operacao_entre' => false],
            ['id' => 2, 'nome' => 'B compra → B vende', 'compra_empresa' => $empresaB, 'venda_empresa' => $empresaB, 'operacao_entre' => false],
            ['id' => 3, 'nome' => 'C compra → operação C→B → B vende', 'compra_empresa' => $empresaC, 'venda_empresa' => $empresaB, 'operacao_entre' => true],
        ];

        $resultados = [];
        foreach ($cenarios as $def) {
            if (! $def['compra_empresa'] || ! $def['venda_empresa']) {
                $resultados[] = array_merge($def, ['erro' => 'Informe empresas C e B.', 'ok' => false]);

                continue;
            }
            $resultados[] = self::simularCenario($def, $qtd, $precoCompra, $precoVenda, $custosExtra);
        }

        $cargas = array_filter(array_map(fn ($r) => $r['carga_tributaria_total'] ?? null, $resultados));
        $melhor = null;
        if (count($cargas)) {
            $min = min($cargas);
            foreach ($resultados as $r) {
                if (($r['carga_tributaria_total'] ?? null) === $min) {
                    $melhor = $r['id'];
                    break;
                }
            }
        }

        return [
            'premissas' => $p,
            'cenarios' => $resultados,
            'comparacao' => [
                'menor_carga_estimada_cenario_id' => $melhor,
                'disclaimer' => 'Simulação gerencial — não altera estoque nem documentos. Validar com contador.',
            ],
            'regra_versao' => RegraFiscalSupport::versaoAtual(),
        ];
    }

    public static function salvarCenario(string $nome, ?int $usuarioId, ?int $produtoId, array $premissas, array $resultado): int
    {
        if (! Schema::hasTable('cenarios_tributarios')) {
            return 0;
        }

        return (int) DB::table('cenarios_tributarios')->insertGetId([
            'nome' => $nome,
            'usuario_id' => $usuarioId,
            'produto_id' => $produtoId,
            'premissas_json' => json_encode($premissas),
            'resultado_json' => json_encode($resultado),
            'regra_versao' => RegraFiscalSupport::versaoAtual(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $def */
    protected static function simularCenario(array $def, float $qtd, float $precoCompra, float $precoVenda, float $custosExtra): array
    {
        $custoAquisicao = round($qtd * $precoCompra, 2);
        $receita = round($qtd * $precoVenda, 2);
        $regimeCompra = self::regimeEmpresa((int) $def['compra_empresa']);
        $regimeVenda = self::regimeEmpresa((int) $def['venda_empresa']);

        $tribEntrada = 0.0;
        $creditos = 0.0;
        foreach (['icms', 'pis', 'cofins'] as $t) {
            $regra = RegraFiscalSupport::regraAplicavel($t, $regimeCompra, 'entrada');
            if ($regra) {
                $v = RegraFiscalSupport::calcularEstimativa($regra, $custoAquisicao);
                $tribEntrada += $v;
                $creditos += $v * 0.85;
            }
        }

        $custoOperacao = 0.0;
        $tribInter = 0.0;
        if ($def['operacao_entre']) {
            $valorOp = $custoAquisicao;
            $regraOp = RegraFiscalSupport::regraAplicavel('icms', null, 'operacao_entre_cnpjs');
            if ($regraOp) {
                $tribInter = RegraFiscalSupport::calcularEstimativa($regraOp, $valorOp);
            }
            $custoOperacao = round($valorOp + $tribInter, 2);
        }

        $tribVenda = 0.0;
        foreach (['icms', 'pis', 'cofins'] as $t) {
            $regra = RegraFiscalSupport::regraAplicavel($t, $regimeVenda, 'venda');
            if ($regra) {
                $tribVenda += RegraFiscalSupport::calcularEstimativa($regra, $receita);
            }
        }
        $regraReg = self::regraRegimeEmpresa($regimeVenda);
        if ($regraReg) {
            $tribVenda += RegraFiscalSupport::calcularEstimativa($regraReg, $receita);
        }

        $carga = max(0, $tribEntrada - $creditos + $tribInter + $tribVenda);
        $custoTotal = $custoAquisicao + $custoOperacao + $custosExtra;
        $margem = round($receita - $custoTotal - $carga, 2);

        return array_merge($def, [
            'ok' => true,
            'receita_estimada' => $receita,
            'custo_aquisicao' => $custoAquisicao,
            'custo_operacao_entre_cnpjs' => $custoOperacao,
            'tributos_entrada' => round($tribEntrada, 2),
            'creditos_estimados' => round($creditos, 2),
            'tributos_intermediarios' => round($tribInter, 2),
            'tributos_venda' => round($tribVenda, 2),
            'carga_tributaria_total' => round($carga, 2),
            'margem_estimada' => $margem,
            'regime_compra' => $regimeCompra,
            'regime_venda' => $regimeVenda,
        ]);
    }

    protected static function regimeEmpresa(int $empresaId): ?string
    {
        if (! Schema::hasTable('empresas') || ! $empresaId) {
            return null;
        }

        return DB::table('empresas')->where('id', $empresaId)->value('regime_tributario');
    }

    protected static function regraRegimeEmpresa(?string $regime): ?object
    {
        if (! $regime) {
            return null;
        }
        $trib = match ($regime) {
            'simples_nacional' => 'simples',
            'lucro_presumido' => 'presumido',
            'lucro_real' => 'irpj_csll',
            default => null,
        };
        if (! $trib) {
            return null;
        }

        return RegraFiscalSupport::regraAplicavel($trib, $regime, 'venda');
    }
}
