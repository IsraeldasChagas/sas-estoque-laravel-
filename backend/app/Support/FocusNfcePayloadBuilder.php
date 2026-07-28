<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Monta JSON NFC-e para API Focus a partir de venda + itens. */
final class FocusNfcePayloadBuilder
{
    /** @param object $venda linha vendas */
    /** @param list<object> $itens linhas venda_itens */
    /** @param array<int|string, object> $produtosById */
    public static function build(object $venda, array $itens, object $empresa, object $config, array $produtosById): array
    {
        $cnpj = FiscalCadastroSupport::normalizarCnpj($empresa->cnpj ?? null);
        if (! $cnpj) {
            throw new \InvalidArgumentException('CNPJ da empresa emitente inválido.');
        }

        $tz = new \DateTimeZone('America/Belem');
        $emissao = (new \DateTimeImmutable('now', $tz))->format('Y-m-d\TH:i:sP');

        $payload = [
            'cnpj_emitente' => $cnpj,
            'natureza_operacao' => 'VENDA AO CONSUMIDOR',
            'data_emissao' => $emissao,
            'tipo_documento' => '1',
            'finalidade_emissao' => '1',
            'consumidor_final' => '1',
            'presenca_comprador' => '1',
            'modalidade_frete' => '9',
            'local_destino' => '1',
        ];

        if ($config->serie_nfce) {
            $payload['serie'] = (string) (int) $config->serie_nfce;
        }
        if ($config->numero_proximo_nfce) {
            $payload['numero'] = (string) (int) $config->numero_proximo_nfce;
        }
        if (! empty($config->csc_id) && ! empty($config->csc_token)) {
            $payload['id_csc'] = (string) $config->csc_id;
            $payload['csc'] = (string) $config->csc_token;
        }

        $items = [];
        $num = 0;
        $totalPag = 0.0;
        foreach ($itens as $row) {
            $num++;
            $pid = (int) $row->produto_id;
            $produto = $produtosById[$pid] ?? $produtosById[(string) $pid] ?? null;
            if (! $produto) {
                throw new \InvalidArgumentException('Produto #' . $row->produto_id . ' não encontrado para NFC-e.');
            }
            $ncm = FiscalCadastroSupport::normalizarNcm($produto->ncm ?? null);
            $cfop = FiscalCadastroSupport::normalizarCfop($produto->cfop_saida_padrao ?? null);
            if (! $ncm) {
                throw new \InvalidArgumentException('Produto "' . ($produto->nome ?? $row->produto_id) . '" sem NCM.');
            }
            if (! $cfop) {
                throw new \InvalidArgumentException('Produto "' . ($produto->nome ?? $row->produto_id) . '" sem CFOP de saída.');
            }

            $qtd = (float) $row->quantidade;
            $preco = (float) $row->preco_unitario;
            $bruto = round((float) ($row->valor_total ?? ($preco * $qtd)), 2);
            $totalPag += $bruto;

            $origem = preg_replace('/\D/', '', (string) ($produto->origem_mercadoria ?? '0'));
            if ($origem === '') {
                $origem = '0';
            }

            $csosn = FiscalCadastroSupport::normalizarCsosn($produto->csosn ?? null)
                ?? FiscalCadastroSupport::normalizarCst($produto->cst_icms ?? null)
                ?? '102';

            $items[] = [
                'numero_item' => (string) $num,
                'codigo_produto' => (string) $produto->id,
                'descricao' => mb_substr((string) ($produto->nome ?? 'Item'), 0, 120),
                'cfop' => $cfop,
                'unidade_comercial' => self::unidadeComercial($produto->unidade_base ?? 'UN'),
                'quantidade_comercial' => $qtd,
                'valor_unitario_comercial' => round($preco, 4),
                'valor_bruto' => $bruto,
                'codigo_ncm' => $ncm,
                'icms_origem' => $origem,
                'icms_situacao_tributaria' => $csosn,
                'pis_situacao_tributaria' => '07',
                'cofins_situacao_tributaria' => '07',
            ];
        }

        $payload['items'] = $items;
        $payload['formas_pagamento'] = [
            [
                'forma_pagamento' => self::mapFormaPagamento($venda->forma_pagamento ?? null),
                'valor_pagamento' => round($totalPag, 2),
            ],
        ];

        return $payload;
    }

    private static function unidadeComercial(?string $u): string
    {
        $u = strtoupper(trim((string) $u));
        if ($u === '' || $u === 'UND' || $u === 'UN') {
            return 'UN';
        }
        if (strlen($u) > 6) {
            return 'UN';
        }

        return $u;
    }

    private static function mapFormaPagamento(?string $forma): string
    {
        $f = mb_strtolower(trim((string) $forma));
        if (str_contains($f, 'pix')) {
            return '17';
        }
        if (str_contains($f, 'cred')) {
            return '03';
        }
        if (str_contains($f, 'deb')) {
            return '04';
        }
        if (str_contains($f, 'din')) {
            return '01';
        }

        return '99';
    }
}
